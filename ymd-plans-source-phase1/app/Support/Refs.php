<?php

namespace App\Support;

use App\Models\Objective;
use App\Models\Plan;
use App\Models\Project;
use App\Models\StrategicGoal;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * الترقيم المرجعي الثابت. يُعيَّن مرة واحدة عند الإنشاء ولا يتغير بعدها ولا يُعاد استخدامه:
 *   الهدف الاستراتيجي  SG-01
 *   الخطة              PL-2026-ENG
 *   هدف الخطة          PL-2026-ENG-O1
 *   المبادرة/المشروع    PL-2026-ENG-I01 / PL-2026-ENG-P02 (عدّاد مشترك)
 *   النشاط             PL-2026-ENG-I01-A01
 *   المهمة             PL-2026-ENG-T001
 */
class Refs
{
    /**
     * الرقم التالي = أكبر من (آخر رقم مسجّل) و(أكبر رقم موجود فعلًا) + 1؛ لا يُعاد استخدام رقم محذوف.
     * $letters: بادئات فرعية تتشارك عدّادًا واحدًا (المبادرات I والمشاريع P في الخطة نفسها: I01، P02، I03).
     */
    private static function next(string $table, string $base, int $pad, array $letters = [''], ?string $letter = null): string
    {
        $letter ??= $letters[0];
        $max = 0;
        foreach ($letters as $l) {
            $prefix = $base . $l;
            // لا نعتمد على تهريب LIKE (يختلف بين SQLite و MySQL): نوسّع البحث ثم نتحقق من البادئة حرفيًا
            foreach (DB::table($table)->where('ref', 'like', $prefix . '%')->pluck('ref') as $ref) {
                if (! str_starts_with($ref, $prefix)) {
                    continue;
                }
                $tail = substr($ref, strlen($prefix));
                if (ctype_digit($tail)) {
                    $max = max($max, (int) $tail);
                }
            }
        }

        $key = $base . implode('|', $letters);
        $seq = DB::table('ref_sequences')->where('prefix', $key)->lockForUpdate()->value('last') ?? 0;
        $n = max($max, (int) $seq) + 1;
        DB::table('ref_sequences')->updateOrInsert(['prefix' => $key], ['last' => $n, 'updated_at' => now()]);

        return $base . $letter . str_pad((string) $n, $pad, '0', STR_PAD_LEFT);
    }

    public static function strategicGoal(): string
    {
        return self::next('strategic_goals', 'SG-', 2);
    }

    public static function plan(Plan $p): string
    {
        $year = $p->year ?? \App\Models\PlanningYear::find($p->planning_year_id);
        $pos = $p->position ?? \App\Models\Position::find($p->position_id);
        $ref = 'PL-' . $year->year . '-' . ($pos->ref_code ?: strtoupper($pos->code));

        return DB::table('plans')->where('ref', $ref)->exists() ? $ref . '-' . uniqid() : $ref;
    }

    private static function planRef(int $planId): string
    {
        $ref = Plan::whereKey($planId)->value('ref');
        if (! $ref) {
            $plan = Plan::findOrFail($planId);
            $plan->ref = self::plan($plan);
            $plan->saveQuietly();
            $ref = $plan->ref;
        }

        return $ref;
    }

    public static function objective(Objective $o): string
    {
        return self::next('objectives', self::planRef($o->plan_id) . '-O', 1);
    }

    public static function project(Project $p): string
    {
        if ($p->parent_id) {
            $parentRef = Project::whereKey($p->parent_id)->value('ref');

            return self::next('projects', $parentRef . '-A', 2);
        }

        return self::next('projects', self::planRef($p->plan_id) . '-', 2, ['I', 'P'], $p->type === 'project' ? 'P' : 'I');
    }

    public static function task(Task $t): string
    {
        return self::next('tasks', self::planRef($t->plan_id) . '-T', 3);
    }
}
