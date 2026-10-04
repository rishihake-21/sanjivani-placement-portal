<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * View helpers for the student screens. Pure PHP (no framework calls) so every rule that the
 * screens depend on can be tested without booting Laravel.
 *
 * Inputs are the plain arrays that the module's API resources produce
 * (StudentResource, AcademicRecordResource, ExperienceResource, DocumentResource) and the
 * ProfileCompletenessService summary.
 */
final class StudentUi
{
    public const LEVEL_ORDER = ['TENTH', 'TWELFTH', 'DIPLOMA', 'DEGREE_SEM'];
    public const LEVEL_LABEL = ['TENTH' => '10th', 'TWELFTH' => '12th', 'DIPLOMA' => 'Diploma'];

    public const ADMISSION = ['REGULAR' => 'Regular', 'LATERAL' => 'Lateral'];
    public const GENDER = ['MALE' => 'Male', 'FEMALE' => 'Female', 'OTHER' => 'Other', 'PREFER_NOT_TO_SAY' => 'Prefer not to say'];
    public const EXP_TYPE = ['INTERNSHIP' => 'Internship', 'JOB' => 'Job', 'PROJECT_WORK' => 'Project work', 'TRAINING' => 'Training'];
    public const DOC_TYPE = [
        'RESUME' => 'Resume',
        'MARKSHEET_10TH' => '10th marksheet',
        'MARKSHEET_12TH' => '12th marksheet',
        'MARKSHEET_DIPLOMA' => 'Diploma marksheet',
        'MARKSHEET_SEMESTER' => 'Semester marksheet',
        'CERT_INTERNSHIP' => 'Internship certificate',
        'CERT_EXPERIENCE' => 'Experience certificate',
        'CERT_OTHER' => 'Other certificate',
    ];

    /** kind => value => [badge variant, label]. Variants: ok, wait, no, neutral. */
    public const BADGES = [
        'rec' => [
            'VERIFIED' => ['ok', 'Verified'], 'PENDING' => ['wait', 'In review'], 'REJECTED' => ['no', 'Rejected'],
            'DRAFT' => ['neutral', 'Draft'], 'SELF_DECLARED' => ['neutral', 'Self-declared (unverified)'],
            'SUPERSEDED' => ['neutral', 'Superseded'], 'MISSING' => ['neutral', 'Not added'],
            'OPTIONAL_EMPTY' => ['neutral', 'Optional, not added'],
        ],
        'doc' => [
            'APPROVED' => ['ok', 'Approved'], 'PENDING' => ['wait', 'In review'], 'REJECTED' => ['no', 'Rejected'],
            'SUPERSEDED' => ['neutral', 'Superseded'],
        ],
        'sec' => [
            'COMPLETE' => ['ok', 'Complete'], 'IN_REVIEW' => ['wait', 'In review'], 'ACTION_REQUIRED' => ['no', 'Action required'],
            'INCOMPLETE' => ['neutral', 'Incomplete'], 'OPTIONAL_EMPTY' => ['neutral', 'Optional, not added'],
        ],
        'dec' => ['APPROVED' => ['ok', 'Approved'], 'REJECTED' => ['no', 'Rejected'], 'UNLOCKED' => ['neutral', 'Unlocked']],
    ];

    /** Completeness section key => [letter, name, route, anchor]. */
    public const SECTIONS = [
        'A_identity' => ['A', 'Identity', 'student.profile', 'sec-identity'],
        'B_contact' => ['B', 'Contact', 'student.profile', 'sec-contact'],
        'C_academic' => ['C', 'Academic', 'student.academic', 'sec-academic'],
        'D_skills_links' => ['D', 'Skills and links', 'student.profile', 'sec-skills'],
        'E_experience' => ['E', 'Experience', 'student.experience', 'sec-experience'],
        'F_preferences' => ['F', 'Preferences', 'student.profile', 'sec-preferences'],
        'G_resume' => ['G', 'Resume', 'student.documents', 'sec-upload'],
    ];

    /** Sidebar: route name => [label, icon]. */
    public const NAV = [
        'student.dashboard' => ['Dashboard', 'dash'],
        'student.profile' => ['Profile', 'user'],
        'student.academic' => ['Academic records', 'book'],
        'student.experience' => ['Experience', 'brief'],
        'student.documents' => ['Documents', 'file'],
        'student.notifications' => ['Notifications', 'bell'],
    ];

    // ------------------------------------------------------------------ escaping, badges, icons

    public static function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function badge(string $kind, ?string $value): string
    {
        [$variant, $label] = self::BADGES[$kind][$value] ?? ['neutral', (string) $value];

        return '<span class="badge b-' . $variant . '">' . self::e($label) . '</span>';
    }

    public static function revBadge(string $status): string
    {
        [$variant, $label] = self::BADGES['rec'][$status] ?? ['neutral', $status];

        return '<span class="badge b-' . $variant . '">Revision: ' . self::e($label) . '</span>';
    }

    public static function icon(string $name, string $class = ''): string
    {
        return '<svg class="icon' . ($class !== '' ? ' ' . self::e($class) : '') . '" aria-hidden="true"><use href="#i-' . self::e($name) . '"/></svg>';
    }

    // ------------------------------------------------------------------ labels and formats

    public static function levelLabel(string $level, int|string|null $semester = null): string
    {
        return $level === 'DEGREE_SEM' ? 'Semester ' . $semester : (self::LEVEL_LABEL[$level] ?? $level);
    }

    public static function docType(?string $t): string { return self::DOC_TYPE[$t] ?? (string) $t; }
    public static function expType(?string $t): string { return self::EXP_TYPE[$t] ?? (string) $t; }
    public static function admission(?string $t): string { return self::ADMISSION[$t] ?? (string) $t; }

    public static function when(mixed $v): ?DateTimeImmutable
    {
        if ($v === null || $v === '') {
            return null;
        }
        $d = $v instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($v) : new DateTimeImmutable((string) $v);

        return $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    public static function date(mixed $v): string
    {
        $d = self::when($v);

        return $d ? $d->format('j M Y') : '';
    }

    public static function dateTime(mixed $v): string
    {
        $d = self::when($v);

        return $d ? $d->format('j M Y, g:i a') : '';
    }

    public static function size(int|float|null $bytes): string
    {
        $bytes = (float) $bytes;

        return $bytes < 1048576 ? (int) round($bytes / 1024) . ' KB' : number_format($bytes / 1048576, 1) . ' MB';
    }

    public static function num2(mixed $n): string
    {
        return number_format((float) $n, 2, '.', '');
    }

    /** "456.00" -> "456", "456.50" -> "456.5" (decimal columns come back from the API as two-decimal strings). */
    public static function trimNum(mixed $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }

    /** "1 Feb 2026 – 30 Apr 2026" or "… – Ongoing". Plain text; escape in the view. */
    public static function expDates(array $e): string
    {
        $end = ! empty($e['is_ongoing']) ? 'Ongoing' : self::date($e['end_date'] ?? null);

        return self::date($e['start_date'] ?? null) . ' – ' . $end;
    }

    // ------------------------------------------------------------------ completeness

    /** @return list<array{key:string,letter:string,name:string,status:string,hint:string,route:string,anchor:string}> */
    public static function sections(array $completeness): array
    {
        $out = [];
        foreach (self::SECTIONS as $key => [$letter, $name, $route, $anchor]) {
            $s = $completeness['sections'][$key] ?? ['status' => 'INCOMPLETE', 'hint' => ''];
            $out[] = ['key' => $key, 'letter' => $letter, 'name' => $name, 'status' => $s['status'], 'hint' => $s['hint'], 'route' => $route, 'anchor' => $anchor];
        }

        return $out;
    }

    public static function section(array $completeness, string $key): array
    {
        foreach (self::sections($completeness) as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }

        return [];
    }

    public static function blockers(array $completeness): array
    {
        $out = [];
        foreach ($completeness['blockers'] ?? [] as $b) {
            [$letter, $name, $route, $anchor] = self::SECTIONS[$b['section']];
            $out[] = ['name' => $name, 'hint' => $b['hint'], 'status' => $b['status'], 'route' => $route, 'anchor' => $anchor];
        }

        return $out;
    }

    public static function sectionsDone(array $completeness): int
    {
        return count(array_filter(self::sections($completeness), fn ($s) => $s['status'] === 'COMPLETE'));
    }

    // ------------------------------------------------------------------ academic records

    private static function live(array $records): array
    {
        return array_values(array_filter($records, fn ($r) => $r['status'] !== 'SUPERSEDED'));
    }

    private static function semOf(array $r): ?int
    {
        return $r['semester'] === null ? null : (int) $r['semester'];
    }

    /** @return list<array{0:string,1:?int}> keys a student may add, given their admission type and semester */
    public static function allowedKeys(array $student): array
    {
        $first = $student['admission_type'] === 'LATERAL' ? 3 : 1;
        $keys = [['TENTH', null], ['TWELFTH', null]];
        if ($student['admission_type'] === 'LATERAL') {
            $keys[] = ['DIPLOMA', null];
        }
        for ($s = $first; $s <= (int) $student['current_semester']; $s++) {
            $keys[] = ['DEGREE_SEM', $s];
        }

        return $keys;
    }

    /** Keys that still have no live record, in display order. */
    public static function availableKeys(array $student, array $records): array
    {
        $live = self::live($records);

        return array_values(array_filter(self::allowedKeys($student), function ($k) use ($live) {
            foreach ($live as $r) {
                if ($r['level'] === $k[0] && self::semOf($r) === $k[1]) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * One row per required record (from the completeness items) plus optional ones:
     * a lateral student's 12th, and any other record that is not required (e.g. the current semester).
     *
     * @return list<array<string,mixed>>
     */
    public static function academicRows(array $student, array $completeness, array $records): array
    {
        $live = self::live($records);
        $items = $completeness['sections']['C_academic']['items'] ?? [];
        $byLabel = [];
        foreach ($items as $i) {
            $byLabel[$i['record']] = $i;
        }

        $keys = [];
        foreach ($items as $i) {
            [$lv, $sem] = self::keyFromLabel($i['record']);
            $keys[] = ['lv' => $lv, 'sem' => $sem, 'required' => true];
        }
        $has = fn ($lv, $sem) => count(array_filter($keys, fn ($k) => $k['lv'] === $lv && $k['sem'] === $sem)) > 0;
        if ($student['admission_type'] === 'LATERAL' && ! $has('TWELFTH', null)) {
            $keys[] = ['lv' => 'TWELFTH', 'sem' => null, 'required' => false];
        }
        foreach ($live as $r) {
            if (! $has($r['level'], self::semOf($r))) {
                $keys[] = ['lv' => $r['level'], 'sem' => self::semOf($r), 'required' => false];
            }
        }
        usort($keys, fn ($a, $b) => [array_search($a['lv'], self::LEVEL_ORDER), $a['sem'] ?? 0] <=> [array_search($b['lv'], self::LEVEL_ORDER), $b['sem'] ?? 0]);

        $rows = [];
        foreach ($keys as $k) {
            $forKey = array_values(array_filter($live, fn ($r) => $r['level'] === $k['lv'] && self::semOf($r) === $k['sem']));
            $verified = null;
            $open = null;
            foreach ($forKey as $r) {
                if ($r['status'] === 'VERIFIED' && $verified === null) {
                    $verified = $r;
                } elseif ($r['status'] !== 'VERIFIED' && $open === null) {
                    $open = $r;
                }
            }
            $label = self::levelLabel($k['lv'], $k['sem']);
            $status = $verified ? 'VERIFIED' : ($open ? $open['status'] : ($k['required'] ? 'MISSING' : 'OPTIONAL_EMPTY'));
            $rows[] = [
                'lv' => $k['lv'], 'sem' => $k['sem'], 'label' => $label, 'required' => $k['required'], 'status' => $status,
                'verified' => $verified, 'open' => $open,
                'revision' => ($byLabel[$label]['revision_in_progress'] ?? null) ?: ($verified && $open ? $open['status'] : null),
                'shown' => $verified ?: $open,
                'action' => $open ?: $verified,
            ];
        }

        return $rows;
    }

    /** @return array{0:string,1:?int} */
    private static function keyFromLabel(string $label): array
    {
        if (str_starts_with($label, 'Semester ')) {
            return ['DEGREE_SEM', (int) substr($label, 9)];
        }
        $flip = array_flip(self::LEVEL_LABEL);

        return [$flip[$label] ?? 'TENTH', null];
    }

    /** edit = draft or rejected, revise = verified and not locked, view = everything else. */
    public static function acadMode(array $rec): string
    {
        if (in_array($rec['status'], ['DRAFT', 'REJECTED'], true)) {
            return 'edit';
        }

        return $rec['status'] === 'VERIFIED' && empty($rec['locked']) ? 'revise' : 'view';
    }

    public static function expMode(array $e): string
    {
        if (in_array($e['status'], ['SELF_DECLARED', 'DRAFT', 'REJECTED'], true)) {
            return 'edit';
        }

        return $e['status'] === 'VERIFIED' ? 'revise' : 'view';
    }

    /** The rejection to show under a row's status badge: only an open row that was rejected has one. */
    public static function rowRejection(array $row): ?array
    {
        return ($row['open'] && $row['open']['status'] === 'REJECTED') ? ($row['open']['rejection'] ?? null) : null;
    }

    /**
     * The one action button for an academic row.
     * type: add (open the "new" drawer on this row's key) | submit (POST submit) | open (open that record's drawer) | none.
     *
     * @return array{type:string,label:string,rec:?array,focus:bool,disabled:bool,done:string}
     */
    public static function acadAction(array $row, bool $full, bool $locked): array
    {
        $rec = $row['action'];
        $none = ['type' => 'none', 'label' => '', 'rec' => $rec, 'focus' => false, 'disabled' => false, 'done' => ''];
        $label = self::levelLabel($row['lv'], $row['sem']);

        if (! $rec) {
            return ['type' => 'add', 'label' => 'Add record', 'disabled' => $locked] + $none;
        }
        if ($rec['status'] === 'DRAFT') {
            if (($rec['document']['status'] ?? null) === 'PENDING') {
                return ['type' => 'submit', 'label' => 'Submit', 'disabled' => $locked, 'done' => $label . ' submitted for review.'] + $none;
            }

            return ['type' => 'open', 'label' => 'Upload marksheet', 'focus' => true, 'disabled' => $locked] + $none;
        }
        if ($rec['status'] === 'REJECTED') {
            return ['type' => 'open', 'label' => 'Edit and resubmit', 'disabled' => $locked] + $none;
        }
        if ($rec['status'] === 'VERIFIED' && empty($rec['locked'])) {
            return ['type' => 'open', 'label' => 'Edit', 'disabled' => $locked] + $none;
        }

        return $full ? ['type' => 'open', 'label' => 'View'] + $none : $none;
    }

    /**
     * Buttons for an experience row: label, whether the drawer should focus the file input,
     * and whether the button is disabled while the profile is locked ("View" never is).
     *
     * @return list<array{label:string,focus:bool,disabled:bool}>
     */
    public static function expActions(array $e, bool $locked): array
    {
        return match ($e['status']) {
            'SELF_DECLARED' => [
                ['label' => 'Edit', 'focus' => false, 'disabled' => $locked],
                ['label' => 'Attach certificate', 'focus' => true, 'disabled' => $locked],
            ],
            'DRAFT' => [['label' => 'Edit and submit', 'focus' => false, 'disabled' => $locked]],
            'REJECTED' => [['label' => 'Edit and resubmit', 'focus' => false, 'disabled' => $locked]],
            'VERIFIED' => [['label' => 'Edit', 'focus' => false, 'disabled' => $locked]],
            default => [['label' => 'View', 'focus' => false, 'disabled' => false]],
        };
    }

    /** Plain text for a detail list: an en dash when the value is missing (0 is a real value). */
    public static function orDash(mixed $v): string
    {
        return $v === null || $v === '' ? '–' : (string) $v;
    }

    /** Rejection reason line used in notifications: "Label: free text". */
    public static function reasonLine(array $n): string
    {
        $parts = array_filter([$n['reason_label'] ?? null, $n['reason'] ?? null], fn ($p) => $p !== null && $p !== '');

        return implode(': ', $parts);
    }

    /** Marks as shown in tables: "91.20%" for percentage levels, "8.10 SGPA" for semesters, null when empty. */
    public static function marks(?array $rec): ?string
    {
        if ($rec === null) {
            return null;
        }
        if ($rec['level'] === 'DEGREE_SEM') {
            return $rec['sgpa'] !== null ? self::num2($rec['sgpa']) . ' SGPA' : null;
        }

        return $rec['percentage'] !== null ? self::num2($rec['percentage']) . '%' : null;
    }
}
