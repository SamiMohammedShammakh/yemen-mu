<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\Plan;
use App\Models\QuarterSnapshot;

/**
 * المصدر الموحد للنتائج المعروضة في الشاشات والملفات:
 * الربع المغلق يُقرأ من لقطته المحفوظة (آخر مراجعة)، والربع المفتوح يُحسب مباشرة.
 * بذلك تطابق الملفات ما يعرضه النظام، ولا يغيّر تعديلُ الخطة نتائجَ ربع مغلق.
 */
class Results
{
    private array $memo = [];

    public function __construct(private ?Calculator $calc = null)
    {
        $this->calc ??= new Calculator();
    }

    public function calculator(): Calculator { return $this->calc; }

    public function snapshot(Plan $plan, int $q): ?QuarterSnapshot
    {
        return QuarterSnapshot::where('plan_id', $plan->id)->where('quarter', $q)->orderByDesc('revision')->first();
    }

    public function plan(Plan $plan, int $q): array
    {
        $key = $plan->id . ':' . $q;
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }
        $plan->loadMissing('year.quarters');
        $quarter = $plan->year->quarters->firstWhere('number', $q);
        if ($quarter && $quarter->isClosed() && ($snap = $this->snapshot($plan, $q))) {
            $data = $snap->data;
            $data['source'] = 'snapshot';
            $data['snapshot_revision'] = $snap->revision;
            $data['snapshot_at'] = $snap->created_at->toIso8601String();

            return $this->memo[$key] = $data;
        }
        $plan->unsetRelation('objectives');

        return $this->memo[$key] = $this->calc->plan($plan, $q);
    }

    public function findIndicator(array $planResult, int $indicatorId): ?array
    {
        foreach ($planResult['objectives'] as $o) {
            foreach ($o['indicators'] as $i) {
                if ($i['id'] === $indicatorId) {
                    return $i;
                }
            }
        }

        return null;
    }

    public function findObjective(array $planResult, int $objectiveId): ?array
    {
        foreach ($planResult['objectives'] as $o) {
            if ($o['id'] === $objectiveId) {
                return $o;
            }
        }

        return null;
    }

    /** صفوف الأرباع الأربعة لمؤشر: كل ربع كما هو معتمد (لقطة للمغلق، مباشر للمفتوح) */
    public function indicatorQuarterRows(Plan $plan, Indicator $ind): array
    {
        $rows = [];
        for ($q = 1; $q <= 4; $q++) {
            $r = $this->findIndicator($this->plan($plan, $q), $ind->id);
            $rows[$q] = $r ? $r['period'] + ['annual' => $r['annual'], 'source' => $this->plan($plan, $q)['source']] : null;
        }

        return $rows;
    }
}
