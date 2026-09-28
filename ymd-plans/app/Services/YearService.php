<?php

namespace App\Services;

use App\Models\PlanningYear;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class YearService
{
    /** ينشئ سنة تخطيط بأرباعها الأربعة */
    public function create(int $year, User $by): PlanningYear
    {
        return DB::transaction(function () use ($year, $by) {
            $y = PlanningYear::create(['year' => $year, 'status' => 'open', 'created_by' => $by->id]);
            for ($q = 1; $q <= 4; $q++) {
                $start = Carbon::create($year, ($q - 1) * 3 + 1, 1);
                $y->quarters()->create(['number' => $q, 'starts_on' => $start, 'ends_on' => $start->copy()->addMonths(3)->subDay(), 'status' => 'open']);
            }
            Audit::log('year.create', $y, null, ['year' => $year]);

            return $y;
        });
    }
}
