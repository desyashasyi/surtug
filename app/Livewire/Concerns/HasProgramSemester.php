<?php

namespace App\Livewire\Concerns;

use App\Models\FetNet\AcademicYear;
use App\Models\FetNet\Semester;

/**
 * Shared semester context for follower pages.
 * Master page (Data→Activities) writes to session; followers read via this trait.
 */
trait HasProgramSemester
{
    public ?int  $academicYearId      = null;
    public ?int  $semesterId          = null;
    public array $academicYearOptions = [];
    public array $semesterOptions     = [];

    protected function mountSemesterContext(?int $clientId): void
    {
        if (! $clientId) return;

        $lastSemester = Semester::whereHas('academicYear', fn($q) => $q->where('client_id', $clientId))
            ->orderByDesc('created_at')->first();

        $ays = AcademicYear::where('client_id', $clientId)->orderByDesc('year_start')->get();

        $this->academicYearOptions = $ays->map(fn($ay) => ['id' => $ay->id, 'name' => $ay->label])->toArray();

        $storedAy = session('program.academic_year_id');
        $this->academicYearId = ($storedAy && collect($this->academicYearOptions)->contains('id', $storedAy))
            ? $storedAy
            : ($lastSemester?->academic_year_id
                ?? $ays->firstWhere('is_active', true)?->id
                ?? $ays->first()?->id);

        $this->loadProgramSemesters();
        $this->persistSemester();
    }

    protected function loadProgramSemesters(): void
    {
        if (! $this->academicYearId) { $this->semesterOptions = []; return; }

        $mn = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
               7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];

        $semesters = Semester::where('academic_year_id', $this->academicYearId)
            ->orderByDesc('created_at')->get();

        $this->semesterOptions = $semesters->map(function ($s) use ($mn) {
            $period = ($s->start_month && $s->end_month)
                ? ' (' . ($mn[$s->start_month] ?? '?') . '–' . ($mn[$s->end_month] ?? '?') . ')' : '';
            return ['id' => $s->id, 'name' => ($s->name ?? ($s->semester == 1 ? 'Odd' : 'Even')) . $period];
        })->toArray();

        $storedSem = session('program.semester_id');
        if ($storedSem && collect($this->semesterOptions)->contains('id', $storedSem)) {
            $this->semesterId = $storedSem;
        } elseif ($semesters->isNotEmpty()) {
            $this->semesterId = $semesters->first()->id;
        }
    }

    protected function persistSemester(): void
    {
        session([
            'program.academic_year_id' => $this->academicYearId,
            'program.semester_id'      => $this->semesterId,
        ]);
    }

    public function updatedAcademicYearId(): void
    {
        $this->semesterId = null;
        $this->loadProgramSemesters();
        $this->persistSemester();
    }

    public function updatedSemesterId(): void
    {
        $this->persistSemester();
    }
}
