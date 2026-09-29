<?php

namespace App\Services;

use App\Models\Plan;
use App\Support\Fmt;

/**
 * يتحقق من اكتمال الخطة قبل الإرسال ويشرح كل حقل ناقص بلغة واضحة.
 */
class PlanValidator
{
    public const TOLERANCE = 0.01;

    /**
     * @param  bool  $requireStrategicLinks  يُفرض الربط بالأهداف الاستراتيجية عند الإعداد والإرسال فقط؛
     *                                        لا يُفرض عند الاعتماد أو محاكاة طلبات التعديل حتى لا تتعطل خطط قائمة.
     * @return array<int, array{field:string, message:string, url:?string}>
     */
    public function errors(Plan $plan, bool $requireStrategicLinks = true): array
    {
        $plan->load(['objectives.indicators.targets']);
        $e = [];
        $add = function (string $field, string $msg, ?string $url = null) use (&$e) {
            $e[] = ['field' => $field, 'message' => $msg, 'url' => $url];
        };
        $editUrl = route('plans.edit', $plan);

        if (! trim((string) $plan->scope_description)) {
            $add('scope_description', 'وصف نطاق العمل مطلوب.', $editUrl);
        }
        if (! trim((string) $plan->overall_outcome)) {
            $add('overall_outcome', 'النتيجة العامة المتوقعة من الخطة مطلوبة.', $editUrl);
        }
        if ($plan->objectives->isEmpty()) {
            $add('objectives', 'أضف هدفًا تفصيليًا واحدًا على الأقل.', $editUrl);

            return $e;
        }
        $sum = $plan->objectives->sum(fn ($o) => (float) $o->weight);
        foreach ($plan->objectives as $o) {
            if ($o->weight === null || $o->weight <= 0) {
                $add("objectives.{$o->id}.weight", "حدد وزنًا أكبر من صفر للهدف «{$o->title}».", $editUrl);
            }
        }
        if (abs($sum - 100) > self::TOLERANCE) {
            $add('objectives.weight_sum', 'مجموع أوزان الأهداف يجب أن يساوي 100%، والمجموع الحالي ' . Fmt::num($sum) . '%.', $editUrl);
        }

        // الربط بالأهداف الاستراتيجية: إلزامي متى وُجدت أهداف استراتيجية فعّالة تغطي سنة الخطة
        $plan->loadMissing('year');
        if ($requireStrategicLinks && \App\Models\StrategicGoal::usableFor($plan->year->year)->exists()) {
            foreach ($plan->objectives as $o) {
                if (! $o->strategic_goal_id) {
                    $add("objectives.{$o->id}.strategic_goal_id", "اربط الهدف «{$o->title}» بهدف استراتيجي من أهداف الجمعية.", $editUrl . '#obj-' . $o->id);
                }
            }
        }

        foreach ($plan->objectives as $o) {
            if ($o->indicators->isEmpty()) {
                $add("objectives.{$o->id}.indicators", "الهدف «{$o->title}» لا يحتوي مؤشرًا قابلًا للقياس. أضف مؤشرًا واحدًا على الأقل.", route('indicators.create', [$plan, 'objective' => $o->id]));
                continue;
            }
            $weighted = $o->indicators->filter(fn ($i) => $i->weight !== null);
            if ($weighted->isNotEmpty()) {
                if ($weighted->count() !== $o->indicators->count()) {
                    $add("objectives.{$o->id}.indicator_weights", "في الهدف «{$o->title}» حُددت أوزان لبعض المؤشرات فقط؛ حدد وزنًا لكل مؤشر أو اترك الأوزان فارغة جميعها.", $editUrl);
                } elseif (abs($o->indicators->sum('weight') - 100) > self::TOLERANCE) {
                    $add("objectives.{$o->id}.indicator_weights", "مجموع أوزان مؤشرات الهدف «{$o->title}» يجب أن يساوي 100%، والمجموع الحالي " . Fmt::num($o->indicators->sum('weight')) . '%.', $editUrl);
                }
            }
            foreach ($o->indicators as $i) {
                $u = route('indicators.edit', $i);
                $n = "المؤشر «{$i->name}»";
                $req = [
                    'definition' => 'التعريف', 'unit' => 'وحدة القياس', 'direction' => 'اتجاه التحسن', 'kind' => 'نوع المؤشر',
                    'data_source' => 'مصدر البيانات', 'verification_method' => 'طريقة التحقق', 'frequency' => 'دورية التحديث',
                    'owner_user_id' => 'مالك المؤشر',
                ];
                foreach ($req as $f => $label) {
                    if ($i->$f === null || trim((string) $i->$f) === '') {
                        $add("indicators.{$i->id}.$f", "$n: حقل «{$label}» مطلوب.", $u);
                    }
                }
                if ($i->baseline === null) {
                    $add("indicators.{$i->id}.baseline", "$n: حقل «خط الأساس» مطلوب (أدخل 0 إن لم تتوفر قيمة سابقة).", $u);
                }
                if ($i->direction === 'range') {
                    if ($i->range_min === null || $i->range_max === null || $i->range_min > $i->range_max) {
                        $add("indicators.{$i->id}.range", "$n: حدد الحد الأدنى والأعلى للنطاق بشكل صحيح.", $u);
                    }
                } elseif ($i->annual_target === null) {
                    $add("indicators.{$i->id}.annual_target", "$n: حقل «المستهدف السنوي» مطلوب.", $u);
                }
                $missingQ = [];
                for ($q = 1; $q <= 4; $q++) {
                    if ($i->measuredInQuarter($q) && $i->targetFor($q) === null) {
                        $missingQ[] = $q;
                    }
                }
                if ($missingQ) {
                    $add("indicators.{$i->id}.targets", "$n: مستهدف الربع " . implode('، ', $missingQ) . ' مطلوب.', $u);
                }
                $sumsQuarters = $i->kind === 'cumulative' || ($i->kind === 'periodic' && $i->aggregation === 'sum');
                if (! $missingQ && $sumsQuarters && $i->annual_target !== null) {
                    $qs = collect(range(1, 4))->sum(fn ($q) => $i->targetFor($q) ?? 0);
                    if (abs($qs - $i->annual_target) > self::TOLERANCE) {
                        $add("indicators.{$i->id}.targets_sum", "$n: مجموع مستهدفات الأرباع (" . Fmt::num($qs) . ') لا يساوي المستهدف السنوي (' . Fmt::num($i->annual_target) . ').', $u);
                    }
                }
                if ($i->kind === 'point' && $i->aggregation === 'sum') {
                    $add("indicators.{$i->id}.aggregation", "$n: لا يجوز جمع قيم مؤشر يقاس في نقطة زمنية؛ اختر المتوسط المرجح أو آخر قيمة.", $u);
                }
            }
        }

        return $e;
    }
}
