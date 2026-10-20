<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\MasterListEntry;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * All-or-nothing CSV import of the official student roster.
 *
 * Canonical columns: university_id, full_name, institutional_email, program_code,
 * department_code, division_code, admission_type, admission_year, graduation_year,
 * current_semester.
 * Legacy branch_code-only files are still accepted during rollout.
 */
class MasterListImporter
{
    private const REQUIRED = [
        'university_id', 'full_name', 'institutional_email', 'department_code',
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
                    'division_id' => $row['_division_id'],
                    'branch_id' => $row['_branch_id'],
                    'admission_type' => $row['admission_type'],
                    'admission_year' => (int) $row['admission_year'],
                    'graduation_year' => (int) $row['graduation_year'],
                    'current_semester' => (int) $row['current_semester'],
                    'import_batch' => $batch,
                ];

                $entry = MasterListEntry::query()
                    ->where('university_id', $row['university_id'])
                    ->lockForUpdate()
                    ->first();

                if ($entry === null) {
                    MasterListEntry::create(['university_id' => $row['university_id']] + $attributes);
                    $created++;
                    continue;
                }

                // A registered student keeps their own name; the master list controls academic placement.
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
            'division_id' => $attributes['division_id'],
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
        $header = array_map(
            fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))),
            $header ?: []
        );

        $hasDivisionCode = in_array('division_code', $header, true);
        $hasLegacyBranchCode = in_array('branch_code', $header, true);
        $missing = array_diff(self::REQUIRED, $header);
        if (! $hasDivisionCode && ! $hasLegacyBranchCode) {
            $missing[] = 'division_code (or legacy branch_code)';
        }
        if ($missing !== []) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => ['Missing column(s): ' . implode(', ', $missing)]]);
        }

        // Do not key by code: department codes may legitimately repeat in separate programmes,
        // and division codes may repeat in separate departments.
        $departments = Department::query()->with('program:id,code')->get(['id', 'code', 'program_id', 'is_active']);
        $divisions = Division::query()->get(['id', 'code', 'department_id', 'is_active']);
        $legacyBranches = Branch::query()->get(['id', 'code', 'department_id', 'is_active']);

        $rows = [];
        $errors = [];
        $seen = [];
        $line = 1;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            $row = array_map(
                'trim',
                array_combine($header, array_slice(array_pad($cells, count($header), ''), 0, count($header)))
            );
            $row['university_id'] = strtoupper($row['university_id'] ?? '');
            $row['institutional_email'] = trim((string) ($row['institutional_email'] ?? '')) ?: null;
            $row['admission_type'] = strtoupper($row['admission_type'] ?? '');
            $row['program_code'] = strtoupper($row['program_code'] ?? '');
            $row['department_code'] = strtoupper($row['department_code'] ?? '');
            $row['division_code'] = strtoupper($row['division_code'] ?? '');
            $row['branch_code'] = strtoupper($row['branch_code'] ?? '');

            $validator = Validator::make($row, [
                'university_id' => ['required', 'string', 'max:30'],
                'full_name' => ['required', 'string', 'max:150'],
                'institutional_email' => ['nullable', 'email', 'max:190'],
                'program_code' => ['nullable', 'string', 'max:20'],
                'department_code' => ['required', 'string', 'max:20'],
                'division_code' => ['nullable', 'string', 'max:30'],
                'branch_code' => ['nullable', 'string', 'max:20'],
                'admission_type' => ['required', 'in:REGULAR,LATERAL'],
                'admission_year' => ['required', 'integer', 'between:2000,2100'],
                'graduation_year' => ['required', 'integer', 'gt:admission_year'],
                'current_semester' => ['required', 'integer', 'between:1,8'],
            ]);

            $rowErrors = $validator->errors()->all();
            if ($hasDivisionCode && $row['program_code'] === '') {
                $rowErrors[] = 'program_code is required for programme/department/division roster imports.';
            }
            if ($row['division_code'] === '' && $row['branch_code'] === '') {
                $rowErrors[] = 'division_code is required (legacy files may use branch_code).';
            }

            $department = null;
            $division = null;
            $legacyBranch = null;

            if ($rowErrors === []) {
                if ($row['division_code'] !== '') {
                    // Canonical input identifies the department by both its code and programme.
                    $department = $departments->first(fn (Department $candidate) =>
                        $candidate->code === $row['department_code']
                        && $candidate->program?->code === $row['program_code']
                    );

                    if ($department === null) {
                        $sameCode = $departments->first(fn (Department $candidate) => $candidate->code === $row['department_code']);
                        $rowErrors[] = $sameCode === null
                            ? "unknown department_code '{$row['department_code']}'"
                            : "department_code '{$row['department_code']}' does not belong to program_code '{$row['program_code']}'";
                    } elseif (! $department->is_active) {
                        $rowErrors[] = "department_code '{$row['department_code']}' is inactive";
                    }

                    if ($department !== null) {
                        $division = $divisions->first(fn (Division $candidate) =>
                            $candidate->code === $row['division_code']
                            && (int) $candidate->department_id === (int) $department->id
                        );

                        if ($division === null) {
                            $rowErrors[] = "division_code '{$row['division_code']}' does not belong to department '{$row['department_code']}' in programme '{$row['program_code']}'";
                        } elseif (! $division->is_active) {
                            $rowErrors[] = "division_code '{$row['division_code']}' is inactive";
                        }
                    }

                    // A legacy alias, if provided alongside canonical columns, must still point
                    // to the same department rather than overriding the canonical assignment.
                    if ($hasLegacyBranchCode && $row['branch_code'] !== '') {
                        $legacyBranch = $legacyBranches->firstWhere('code', $row['branch_code']);
                        if ($legacyBranch === null) {
                            $rowErrors[] = "unknown legacy branch_code '{$row['branch_code']}'";
                        } elseif (! $legacyBranch->is_active) {
                            $rowErrors[] = "legacy branch_code '{$row['branch_code']}' is inactive";
                        } elseif ($department !== null && (int) $legacyBranch->department_id !== (int) $department->id) {
                            $rowErrors[] = "legacy branch_code '{$row['branch_code']}' does not belong to canonical department '{$row['department_code']}'";
                        }
                    }
                } else {
                    // Legacy input only provides branch_code. Resolve the department from that
                    // branch's real parent, not by the department code alone.
                    $legacyBranch = $legacyBranches->firstWhere('code', $row['branch_code']);
                    if ($legacyBranch === null) {
                        $rowErrors[] = "unknown legacy branch_code '{$row['branch_code']}'";
                    } elseif (! $legacyBranch->is_active) {
                        $rowErrors[] = "legacy branch_code '{$row['branch_code']}' is inactive";
                    } else {
                        $department = $departments->firstWhere('id', $legacyBranch->department_id);

                        if ($department === null) {
                            $rowErrors[] = "legacy branch_code '{$row['branch_code']}' has no department";
                        } else {
                            // Old CSE-AIML files labelled this child branch as CSE. After the
                            // hierarchy migration its canonical department is AIML; accept either
                            // old or new label only for this exact compatibility alias.
                            $legacyAimlAlias = $legacyBranch->code === 'CSE-AIML';
                            $departmentLabelMatches = $row['department_code'] === $department->code
                                || ($legacyAimlAlias && $row['department_code'] === 'CSE');

                            if (! $departmentLabelMatches) {
                                $rowErrors[] = "branch_code '{$row['branch_code']}' does not belong to department_code '{$row['department_code']}'";
                            }
                            if ($row['program_code'] !== '' && $department->program?->code !== $row['program_code']) {
                                $rowErrors[] = "department_code '{$department->code}' does not belong to program_code '{$row['program_code']}'";
                            }
                            if (! $department->is_active) {
                                $rowErrors[] = "department_code '{$department->code}' is inactive";
                            }

                            $defaultCode = substr(strtoupper((string) $department->code), 0, 18) . '-UNASSIGNED';
                            $division = $divisions->first(fn (Division $candidate) =>
                                (int) $candidate->department_id === (int) $department->id
                                && $candidate->code === $defaultCode
                                && $candidate->is_active
                            ) ?? $divisions->first(fn (Division $candidate) =>
                                (int) $candidate->department_id === (int) $department->id && $candidate->is_active
                            );

                            if ($division === null) {
                                $rowErrors[] = "no active division exists for department '{$department->code}'";
                            }
                        }
                    }
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
            $row['_department_id'] = (int) $department->id;
            $row['_division_id'] = (int) $division->id;
            $row['_branch_id'] = $legacyBranch?->id;
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
