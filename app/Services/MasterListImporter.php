<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\MasterListEntry;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
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
            $now = now();

            foreach (array_chunk($rows, 500) as $chunk) {
                $uniIds = array_column($chunk, 'university_id');
                $existingEntries = MasterListEntry::query()
                    ->whereIn('university_id', $uniIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('university_id');

                $claimedEntryIds = $existingEntries
                    ->filter(fn (MasterListEntry $e) => $e->isClaimed())
                    ->pluck('id')
                    ->all();

                $claimedStudentsByMasterId = $claimedEntryIds !== []
                    ? Student::query()->whereIn('master_list_id', $claimedEntryIds)->get()->keyBy('master_list_id')
                    : collect();

                $newRows = [];

                foreach ($chunk as $row) {
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

                    /** @var MasterListEntry|null $entry */
                    $entry = $existingEntries->get($row['university_id']);

                    if ($entry === null) {
                        $newRows[] = [
                            'university_id' => $row['university_id'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ] + $attributes;
                        $created++;
                        continue;
                    }

                    // A registered student keeps their own name; the master list only moves them along.
                    if ($entry->isClaimed()) {
                        unset($attributes['full_name'], $attributes['institutional_email']);
                        $this->syncRegisteredStudent($claimedStudentsByMasterId->get($entry->id), $attributes);
                    }

                    $entry->update($attributes);
                    $updated++;
                }

                if ($newRows !== []) {
                    MasterListEntry::insert($newRows);
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }

    private function syncRegisteredStudent(?Student $student, array $attributes): void
    {
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

            // Fast native row validation (avoids instantiating 40,000 Validator objects on bulk CSV imports)
            $rowErrors = $this->validateRow($row);

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

    /** @return list<string> */
    private function validateRow(array $row): array
    {
        $errors = [];

        if ($row['university_id'] === '' || mb_strlen($row['university_id']) > 30) {
            $errors[] = 'The university id field is required and must not exceed 30 characters.';
        }
        if ($row['full_name'] === '' || mb_strlen($row['full_name']) > 150) {
            $errors[] = 'The full name field is required and must not exceed 150 characters.';
        }
        if ($row['institutional_email'] !== '' && (mb_strlen($row['institutional_email']) > 190 || filter_var($row['institutional_email'], FILTER_VALIDATE_EMAIL) === false)) {
            $errors[] = 'The institutional email field must be a valid email address.';
        }
        if ($row['department_code'] === '') {
            $errors[] = 'The department code field is required.';
        }
        if ($row['branch_code'] === '') {
            $errors[] = 'The branch code field is required.';
        }
        if (! in_array($row['admission_type'], ['REGULAR', 'LATERAL'], true)) {
            $errors[] = 'The selected admission type is invalid.';
        }

        $admYear = filter_var($row['admission_year'], FILTER_VALIDATE_INT);
        $gradYear = filter_var($row['graduation_year'], FILTER_VALIDATE_INT);
        $sem = filter_var($row['current_semester'], FILTER_VALIDATE_INT);

        if ($admYear === false || $admYear < 2000 || $admYear > 2100) {
            $errors[] = 'The admission year field must be between 2000 and 2100.';
        }
        if ($gradYear === false || ($admYear !== false && $gradYear <= $admYear)) {
            $errors[] = 'The graduation year field must be greater than admission year.';
        }
        if ($sem === false || $sem < 1 || $sem > 8) {
            $errors[] = 'The current semester field must be between 1 and 8.';
        }

        return $errors;
    }
}
