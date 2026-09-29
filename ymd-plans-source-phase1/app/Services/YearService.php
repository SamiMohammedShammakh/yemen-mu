<?php

namespace App\Services;

use App\Models\PlanningYear;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إدارة سنوات التخطيط: الإنشاء بأرباعها الأربعة، تعديل البيانات، والتفعيل والإغلاق وإعادة الفتح بسبب موثق.
 * حالات السنة: draft (مسودة) → open (نشطة) → closed (مغلقة)، وإعادة الفتح closed → open بسبب مسجل.
 */
class YearService
{
    public const EDITABLE = ['name', 'owner_user_id', 'plans_due_on', 'description'];

    /**
     * ينشئ سنة تخطيط بأرباعها الأربعة.
     * التوقيع متوافق مع الاستدعاءات السابقة: create(2027, $user) ينشئ سنة ميلادية كاملة نشطة كما كان.
     */
    public function create(int $year, User $by, array $data = []): PlanningYear
    {
        $start = Carbon::parse($data['starts_on'] ?? "$year-01-01")->startOfDay();
        $end = Carbon::parse($data['ends_on'] ?? "$year-12-31")->startOfDay();
        $this->assertSpan($start, $end);

        return DB::transaction(function () use ($year, $by, $data, $start, $end) {
            $y = PlanningYear::create([
                'year' => $year,
                'name' => $data['name'] ?? ('سنة التخطيط ' . $year),
                'starts_on' => $start,
                'ends_on' => $end,
                'owner_user_id' => $data['owner_user_id'] ?? null,
                'plans_due_on' => $data['plans_due_on'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'open',
                'created_by' => $by->id,
            ]);
            foreach ($this->quarterRanges($start, $end) as $q => [$qs, $qe]) {
                $y->quarters()->create(['number' => $q, 'starts_on' => $qs, 'ends_on' => $qe, 'status' => 'open']);
            }
            Audit::log('year.create', $y, null, $y->only(['year', 'name', 'status', 'owner_user_id', 'plans_due_on']) + [
                'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(),
            ]);

            return $y;
        });
    }

    /** أربعة أرباع متتالية بثلاثة أشهر لكل ربع من تاريخ البداية، وينتهي الرابع بتاريخ نهاية السنة */
    public function quarterRanges(Carbon $start, Carbon $end): array
    {
        $out = [];
        for ($q = 1; $q <= 4; $q++) {
            $qs = $start->copy()->addMonthsNoOverflow(3 * ($q - 1));
            $qe = $q < 4 ? $start->copy()->addMonthsNoOverflow(3 * $q)->subDay() : $end->copy();
            $out[$q] = [$qs, $qe];
        }

        return $out;
    }

    private function assertSpan(Carbon $start, Carbon $end): void
    {
        if ($end->lte($start)) {
            throw ValidationException::withMessages(['ends_on' => 'تاريخ نهاية السنة يجب أن يكون بعد تاريخ بدايتها.']);
        }
        $months = $start->diffInMonths($end->copy()->addDay());
        if ($months < 10 || $months > 15) {
            throw ValidationException::withMessages(['ends_on' => 'مدة سنة التخطيط يجب أن تكون بين 10 و15 شهرًا (أربعة أرباع).']);
        }
    }

    /** تعديل بيانات السنة. تغيير التواريخ مسموح فقط قبل إقفال أي ربع، ويعيد حساب مواعيد الأرباع */
    public function update(PlanningYear $y, User $by, array $data): PlanningYear
    {
        if ($y->isClosed()) {
            throw ValidationException::withMessages(['year' => 'السنة مغلقة. أعد فتحها بسبب موثق قبل التعديل.']);
        }

        return DB::transaction(function () use ($y, $by, $data) {
            $y->load('quarters');
            $old = $y->only(self::EDITABLE) + ['starts_on' => $y->starts_on?->toDateString(), 'ends_on' => $y->ends_on?->toDateString()];
            $y->fill(array_intersect_key($data, array_flip(self::EDITABLE)));

            $start = isset($data['starts_on']) ? Carbon::parse($data['starts_on'])->startOfDay() : $y->starts_on;
            $end = isset($data['ends_on']) ? Carbon::parse($data['ends_on'])->startOfDay() : $y->ends_on;
            $datesChanged = $start && $end && ($start->toDateString() !== $old['starts_on'] || $end->toDateString() !== $old['ends_on']);
            if ($datesChanged) {
                if ($y->quarters->contains(fn ($q) => $q->isClosed())) {
                    throw ValidationException::withMessages(['starts_on' => 'لا يمكن تغيير تواريخ السنة بعد إقفال أحد أرباعها.']);
                }
                $this->assertSpan($start, $end);
                $y->starts_on = $start;
                $y->ends_on = $end;
                foreach ($this->quarterRanges($start, $end) as $n => [$qs, $qe]) {
                    $y->quarters->firstWhere('number', $n)?->update(['starts_on' => $qs, 'ends_on' => $qe]);
                }
            }
            $y->save();
            $new = $y->only(self::EDITABLE) + ['starts_on' => $y->starts_on?->toDateString(), 'ends_on' => $y->ends_on?->toDateString()];
            $diffOld = array_diff_assoc(array_map('strval', $old), array_map('strval', $new));
            if ($diffOld) {
                Audit::log('year.update', $y, array_intersect_key($old, $diffOld), array_intersect_key($new, $diffOld));
            }

            return $y;
        });
    }

    public const TRANSITIONS = [
        'activate' => ['from' => ['draft'], 'to' => 'open', 'label' => 'تفعيل السنة'],
        'close' => ['from' => ['open'], 'to' => 'closed', 'label' => 'إغلاق السنة'],
        'reopen' => ['from' => ['closed'], 'to' => 'open', 'label' => 'إعادة فتح السنة'],
    ];

    public function transition(PlanningYear $y, User $by, string $action, ?string $reason = null): PlanningYear
    {
        $t = self::TRANSITIONS[$action] ?? null;
        abort_unless($t, 404);
        if (! in_array($y->status, $t['from'], true)) {
            throw ValidationException::withMessages(['status' => 'لا يمكن «' . $t['label'] . '» والسنة في حالة «' . $y->statusLabel() . '».']);
        }
        if ($action === 'close') {
            $open = $y->quarters()->where('status', 'open')->pluck('number');
            if ($open->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => 'أقفل كل الأرباع أولًا. الأرباع المفتوحة: ' . $open->map(fn ($n) => 'ر' . $n)->implode('، ') . '.']);
            }
        }
        if ($action === 'reopen' && ! trim((string) $reason)) {
            throw ValidationException::withMessages(['reason' => 'إعادة فتح سنة مغلقة تتطلب سببًا أو سندًا موثقًا.']);
        }

        return DB::transaction(function () use ($y, $by, $action, $t, $reason) {
            $from = $y->status;
            $y->status = $t['to'];
            if ($action === 'close') {
                $y->closed_at = now();
                $y->closed_by = $by->id;
            }
            $y->save();
            Audit::log('year.' . $action, $y, ['status' => $from], ['status' => $y->status, 'reason' => $reason]);

            return $y;
        });
    }
}
