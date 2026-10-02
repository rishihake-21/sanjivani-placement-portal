<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\MasterListEntry;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CSV import of the student master list (all-or-nothing: any invalid row rejects the whole file).
 *
 * Columns: university_id, full_name, institutional_email, department_code, branch_code,
 *          admission_type (REGULAR|LATERAL), admission_year, graduation_year, current_semester
 *
 * Re-importing is safe (upsert by university_id). For students who already registered, only
 * department, branch and current_semester are synced - this is how semester rollover reaches them.
 */
class MasterListImporter
{
    private const REQUIRED = [
        'university_id', 'full_name', 'institutional_email', 'department_code', 'branch_code',
        'admission_type', 'admission_year', 'graduation_year', 'current_semester',
    ];

    public function __construct(private AuditLogger $audit)
    {
    }

    /** @return array{created:int, updated:int} */
    public function import(string $path, ?string $batch = null): array
    {
        $rows = $this->parse($path);

        return DB::transaction(function () use ($rows, $batch) {
            $created = $updated = 0;

            foreach ($rows as $row) {
                $attributes = [
                    'full_name' => $row['full_name'],
                    'institutional_email' => $row['institutional_email'] ?: null,
                    'department_id' => $row['_department_id'],
                    'branch_id' => $row['_branch_id'],
                    'admission_type' => $row['admission_type'],
                    'admission_year' => (int) $row['admission_year'],
                    'graduation_year' => (int) $row['graduation_year'],
                    'current_semester' => (int) $row['current_semester'],
                    'import_batch' => $batch,
                ];

                $entry = MasterListEntry::query()->where('university_id', $row['university_id'])->lockForUpdate()->first();

                if ($entry === null) {
                    MasterListEntry::create(['university_id' => $row['university_id']] + $attributes);
                    $created++;
                    continue;
                }

                // A registered student keeps their own name; the master list only moves them along.
                if ($entry->isClaimed()) {
                    unset($attributes['full_name'], $attributes['institutional_email']);
                    $this->syncRegisteredStudent($entry, $attributes);
                }

                $entry->update($attributes);
                $updated++;
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }

    private function syncRegisteredStudent(MasterListEntry $entry, array $attributes): void
    {
        $student = Student::query()->where('master_list_id', $entry->id)->first();
        if ($student === null) {
            return;
        }

        $new = [
            'department_id' => $attributes['department_id'],
            'branch_id' => $attributes['branch_id'],
            'current_semester' => $attributes['current_semester'],
        ];
        $old = array_intersect_key($student->getAttributes(), $new);

        if ($old != $new) {
            $student->forceFill($new)->save();
            $this->audit->record('student.master_list_sync', $student, $student->id, $old, $new);
        }
    }

    /** @return list<array<string, mixed>> */
    private function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => ['The file could not be read.']]);
        }

        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header ?: []);

        $missing = array_diff(self::REQUIRED, $header);
        if ($missing !== []) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => ['Missing column(s): ' . implode(', ', $missing)]]);
        }

        $departments = Department::query()->pluck('id', 'code');
        $branches = Branch::query()->get(['id', 'code', 'department_id'])->keyBy('code');

        $rows = [];
        $errors = [];
        $seen = [];
        $line = 1;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            $row = array_map('trim', array_combine($header, array_slice(array_pad($cells, count($header), ''), 0, count($header))));
            $row['university_id'] = strtoupper($row['university_id']);
            $row['admission_type'] = strtoupper($row['admission_type']);

            $validator = Validator::make($row, [
                'university_id' => ['required', 'string', 'max:30'],
                'full_name' => ['required', 'string', 'max:150'],
                'institutional_email' => ['nullable', 'email', 'max:190'],
                'department_code' => ['required', 'string'],
                'branch_code' => ['required', 'string'],
                'admission_type' => ['required', 'in:REGULAR,LATERAL'],
                'admission_year' => ['required', 'integer', 'between:2000,2100'],
                'graduation_year' => ['required', 'integer', 'gt:admission_year'],
                'current_semester' => ['required', 'integer', 'between:1,8'],
            ]);

            $rowErrors = $validator->errors()->all();

            if ($rowErrors === []) {
                $deptId = $departments->get($row['department_code']);
                $branch = $branches->get($row['branch_code']);

                if ($deptId === null) {
                    $rowErrors[] = "unknown department_code '{$row['department_code']}'";
                } elseif ($branch === null || (int) $branch->department_id !== (int) $deptId) {
                    $rowErrors[] = "branch_code '{$row['branch_code']}' does not belong to department '{$row['department_code']}'";
                }
                if ($row['admission_type'] === 'LATERAL' && (int) $row['current_semester'] < 3) {
                    $rowErrors[] = 'lateral-entry students start in semester 3';
                }
                if (isset($seen[$row['university_id']])) {
                    $rowErrors[] = "duplicate university_id (also on line {$seen[$row['university_id']]})";
                }
            }

            if ($rowErrors !== []) {
                $errors[] = "Line {$line}: " . implode('; ', $rowErrors);
                continue;
            }

            $seen[$row['university_id']] = $line;
            $row['_department_id'] = (int) $deptId;
            $row['_branch_id'] = $branch->id;
            $rows[] = $row;
        }
        fclose($handle);

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 50)]);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => ['The file has no data rows.']]);
        }

        return $rows;
    }
}
