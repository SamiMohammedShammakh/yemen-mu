<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\Plan;
use App\Services\ChangeRequestService;
use App\Services\Results;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IndicatorController extends Controller
{
    private function rules(): array
    {
        return [
            'objective_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'definition' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
            'direction' => ['required', Rule::in(array_keys(Indicator::DIRECTIONS))],
            'range_min' => 'nullable|numeric',
            'range_max' => 'nullable|numeric',
            'kind' => ['required', Rule::in(array_keys(Indicator::KINDS))],
            'aggregation' => ['required', Rule::in(array_keys(Indicator::AGGREGATIONS))],
            'baseline' => 'nullable|numeric',
            'annual_target' => 'nullable|numeric',
            'targets' => 'array',
            'targets.*' => 'nullable|numeric',
            'data_source' => 'nullable|string|max:255',
            'verification_method' => 'nullable|string|max:255',
            'frequency' => ['nullable', Rule::in(array_keys(Indicator::FREQUENCIES))],
            'required_evidence' => 'nullable|string',
            'owner_user_id' => 'nullable|exists:users,id',
            'weight' => 'nullable|numeric|min:0|max:100',
        ];
    }

    private const LABELS = ['name' => 'اسم المؤشر', 'annual_target' => 'المستهدف السنوي', 'targets.*' => 'مستهدف الربع', 'weight' => 'الوزن', 'baseline' => 'خط الأساس'];

    private function form(Plan $plan, ?Indicator $indicator)
    {
        $plan->load(['objectives', 'position', 'year']);
        $owners = Access::usersHoldingPosition($plan->position_id);
        if ($indicator?->owner && ! $owners->contains('id', $indicator->owner_user_id)) {
            $owners->push($indicator->owner);
        }

        return view('indicators.form', compact('plan', 'indicator', 'owners'));
    }

    public function create(Plan $plan)
    {
        $this->canEdit($plan);

        return $this->form($plan, null);
    }

    public function edit(Indicator $indicator)
    {
        $this->canEdit($indicator->plan);

        return $this->form($indicator->plan, $indicator->load('targets'));
    }

    private function save(Request $r, Plan $plan, ?Indicator $ind)
    {
        $d = $r->validate($this->rules(), [], self::LABELS);
        abort_unless($plan->objectives()->whereKey($d['objective_id'])->exists(), 422);
        if ($d['kind'] === 'point' && $d['aggregation'] === 'sum') {
            return back()->withInput()->withErrors(['aggregation' => 'لا يجوز جمع قيم مؤشر يقاس في نقطة زمنية. اختر المتوسط المرجح أو آخر قيمة.']);
        }
        if ($d['kind'] === 'cumulative') {
            $d['aggregation'] = 'sum';
        }
        $targets = $d['targets'] ?? [];
        unset($d['targets']);
        $old = $ind ? $ind->load('targets')->toArray() : null;
        $ind = $ind ? tap($ind)->update($d) : $plan->indicators()->create($d + ['sort' => $plan->indicators()->count()]);
        for ($q = 1; $q <= 4; $q++) {
            $ind->targets()->updateOrCreate(['quarter' => $q], ['target' => ($targets[$q] ?? null) === '' ? null : ($targets[$q] ?? null)]);
        }
        Audit::log($old ? 'indicator.update' : 'indicator.create', $ind, $old, $d + ['targets' => $targets]);

        return redirect(route('plans.edit', $plan) . '#obj-' . $ind->objective_id)->with('ok', 'حُفظ المؤشر.');
    }

    public function store(Request $r, Plan $plan)
    {
        $this->canEdit($plan);

        return $this->save($r, $plan, null);
    }

    public function update(Request $r, Indicator $indicator)
    {
        $this->canEdit($indicator->plan);

        return $this->save($r, $indicator->plan, $indicator);
    }

    public function destroy(Indicator $indicator)
    {
        $plan = $indicator->plan;
        $this->canEdit($plan);
        Audit::log('indicator.delete', $indicator, $indicator->toArray(), null);
        $indicator->delete();

        return redirect()->route('plans.edit', $plan)->with('ok', 'حُذف المؤشر.');
    }

    public function show(Indicator $indicator)
    {
        $plan = $indicator->plan;
        $this->canView($plan);
        $u = auth()->user();
        $plan->load(['position', 'year.quarters']);
        $indicator->load(['targets', 'objective', 'owner']);
        $q = $this->selectedQuarter();
        $res = new Results();
        $result = $res->findIndicator($res->plan($plan, $q), $indicator->id);
        $rows = $res->indicatorQuarterRows($plan, $indicator);
        $updates = $indicator->updates()->with(['creator', 'reviewer', 'attachments'])->get();
        $canUpdate = Access::canPostUpdate($u, $plan);
        $canChange = app(ChangeRequestService::class)->canRequest($u, $plan);
        $openQuarters = $plan->year->quarters->where('status', 'open')->pluck('number');
        $closedQuarters = $plan->year->quarters->where('status', 'closed')->pluck('number');

        return view('indicators.show', compact('plan', 'indicator', 'q', 'result', 'rows', 'updates', 'canUpdate', 'canChange', 'openQuarters', 'closedQuarters'));
    }
}
