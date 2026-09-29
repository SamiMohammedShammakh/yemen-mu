<?php

namespace App\Support;

use App\Models\PlanningYear;
use App\Models\Position;
use App\Models\User;

/**
 * سياق العمل: مساحة العمل الحالية، السنة، والربع — محفوظة في الجلسة وقابلة للتبديل من أعلى الصفحات.
 */
class Workspace
{
    public function __construct(
        public array $workspaces,
        public ?array $current,
        public ?PlanningYear $year,
        public int $quarter,
        public $years,
    ) {}

    public static function resolve(User $u): self
    {
        $ws = Access::workspaces($u);
        $key = session('ws');
        if (! $key || ! isset($ws[$key])) {
            $primary = Access::positions($u)->first(fn ($p) => $p->pivot->is_primary) ?? Access::positions($u)->first();
            $key = $primary ? 'pos:' . $primary->id : array_key_first($ws);
            session(['ws' => $key]);
        }
        $years = PlanningYear::orderByDesc('year')->get();
        $year = $years->firstWhere('id', session('year_id'))
            ?? $years->firstWhere('year', (int) now()->year)
            ?? $years->sortBy('year')->first(fn ($y) => $y->year >= now()->year)
            ?? $years->first();
        $quarter = (int) session('quarter', 0);
        if ($quarter < 1 || $quarter > 4) {
            $quarter = $year && $year->year == now()->year ? (int) ceil(now()->month / 3) : ($year && $year->year < now()->year ? 4 : 1);
        }

        return new self($ws, $key ? $ws[$key] : null, $year, $quarter, $years);
    }

    public function position(): ?Position
    {
        return ($this->current['type'] ?? null) === 'position' ? $this->current['position'] : null;
    }

    public static function quarterName(int $q): string
    {
        return ['', 'الربع الأول', 'الربع الثاني', 'الربع الثالث', 'الربع الرابع'][$q] ?? '';
    }
}
