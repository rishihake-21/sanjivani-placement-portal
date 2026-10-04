<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\PlacementDrive;
use Illuminate\Support\Facades\DB;

/**
 * Renders the TPO pages. Pages only carry the data needed to build the screen (company and department lists,
 * batch years); everything else is loaded by the page script from the JSON API, so each rule lives in one place.
 */
class TpoPageController extends Controller
{
    private const STAGES = [
        'APPLIED' => 'Applied', 'SHORTLISTED' => 'Shortlisted', 'APTITUDE' => 'Aptitude', 'TECHNICAL' => 'Technical', 'HR' => 'HR',
        'SELECTED' => 'Selected', 'OFFER_RECEIVED' => 'Offer received', 'PLACED' => 'Placed', 'REJECTED' => 'Rejected', 'WITHDRAWN' => 'Withdrawn',
    ];

    public function dashboard()
    {
        return view('tpo.dashboard', ['years' => $this->years()]);
    }

    public function companies()
    {
        return view('tpo.companies.index');
    }

    public function drives()
    {
        return view('tpo.drives.index', ['companies' => $this->companyOptions(), 'years' => $this->years()]);
    }

    public function createDrive()
    {
        return view('tpo.drives.form', [
            'driveId' => null,
            'companies' => $this->companyOptions(true),
            'departments' => Department::query()->where('is_active', true)->with(['branches' => fn ($q) => $q->where('is_active', true)->orderBy('name')])->orderBy('name')->get(),
            'years' => $this->futureYears(),
        ]);
    }

    public function editDrive(int $id)
    {
        PlacementDrive::query()->findOrFail($id);

        return view('tpo.drives.form', [
            'driveId' => $id,
            'companies' => $this->companyOptions(false),   // an existing drive may point at a company that was deactivated later
            'departments' => Department::query()->with(['branches' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get(),
            'years' => $this->futureYears(PlacementDrive::query()->whereKey($id)->value('graduation_year')),
        ]);
    }

    public function showDrive(int $id)
    {
        PlacementDrive::query()->findOrFail($id);

        return view('tpo.drives.show', ['driveId' => $id]);
    }

    public function applications()
    {
        return view('tpo.applications.index', [
            'companies' => $this->companyOptions(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'stages' => self::STAGES,
        ]);
    }

    public function showApplication(int $id)
    {
        return view('tpo.applications.show', ['applicationId' => $id]);
    }

    public function placements()
    {
        return view('tpo.placements.index', [
            'companies' => $this->companyOptions(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'years' => $this->years(),
        ]);
    }

    public function reports()
    {
        return view('tpo.reports.index', [
            'years' => $this->years(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ---------------------------------------------------------------------------------------------

    private function companyOptions(bool $activeOnly = false)
    {
        return Company::query()->when($activeOnly, fn ($q) => $q->where('is_active', true))->orderBy('name')->get(['id', 'name']);
    }

    /** Distinct graduation years of registered students. */
    private function studentYears(): array
    {
        return DB::table('students')->distinct()->pluck('graduation_year')->map(fn ($y) => (int) $y)->all();
    }

    /** Batches that exist in the data, newest first, plus the current and next batch so a fresh install has options. */
    private function years(): array
    {
        $now = (int) date('Y');

        return collect($this->studentYears())->merge([$now, $now + 1, $now + 2, $now + 3])->unique()->sortDesc()->values()->all();
    }

    /** Batches a new drive can target: this year to six years ahead (matches the API rule). */
    private function futureYears(?int $include = null): array
    {
        $now = (int) date('Y');
        $years = range($now - 1, $now + 6);
        if ($include !== null && ! in_array($include, $years, true)) {
            $years[] = $include;
        }
        sort($years);

        return $years;
    }
}
