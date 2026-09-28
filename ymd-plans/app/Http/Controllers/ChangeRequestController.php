<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\Plan;
use App\Services\ChangeRequestService;
use App\Support\Access;
use Illuminate\Http\Request;

class ChangeRequestController extends Controller
{
    public function index()
    {
        $u = auth()->user();
        $crs = ChangeRequest::whereIn('plan_id', Access::visiblePlans($u)->select('id'))->with(['plan.position', 'plan.year', 'requester'])->latest()->paginate(30);

        return view('change-requests.index', compact('crs'));
    }

    public function create(Request $r, Plan $plan)
    {
        $this->canView($plan);
        abort_unless(app(ChangeRequestService::class)->canRequest($r->user(), $plan), 403, 'طلب التعديل متاح لصاحب المنصب ومسؤول التخطيط على خطة معتمدة أو نشطة.');
        $plan->load(['objectives.indicators.targets', 'year.quarters', 'position']);
        $type = $r->query('type', 'plan_amendment');
        $indicator = $type === 'closed_quarter_result' ? $plan->indicators()->findOrFail($r->integer('indicator')) : null;

        return view('change-requests.create', compact('plan', 'type', 'indicator'));
    }

    public function store(Request $r, Plan $plan)
    {
        $this->canView($plan);
        $svc = app(ChangeRequestService::class);
        $d = $r->validate(['type' => 'required|in:plan_amendment,closed_quarter_result', 'reason' => 'required|string|max:3000'], [], ['reason' => 'سبب التعديل']);
        if ($d['type'] === 'plan_amendment') {
            $cr = $svc->requestAmendment($r->user(), $plan, $d['reason'], $r->only(['objectives', 'indicators']));
        } else {
            $x = $r->validate(['indicator_id' => 'required|integer', 'quarter' => 'required|integer|between:1,4', 'new_value' => 'required|numeric', 'participants' => 'nullable|integer|min:0'],
                [], ['new_value' => 'القيمة الجديدة']);
            $ind = $plan->indicators()->findOrFail($x['indicator_id']);
            $cr = $svc->requestClosedQuarterResult($r->user(), $plan, $ind, (int) $x['quarter'], (float) $x['new_value'], $x['participants'] ?? null, $d['reason']);
        }

        return redirect()->route('change-requests.show', $cr)->with('ok', 'قُدّم طلب التعديل وهو بانتظار قرار الرئيس أو المفوّض.');
    }

    public function show(ChangeRequest $changeRequest)
    {
        $this->canView($changeRequest->plan);
        $changeRequest->load(['plan.position', 'plan.year', 'requester', 'decider']);
        $canDecide = $changeRequest->status === 'pending' && Access::canApprove(auth()->user());

        return view('change-requests.show', ['cr' => $changeRequest, 'canDecide' => $canDecide]);
    }

    public function decide(Request $r, ChangeRequest $changeRequest)
    {
        $this->canView($changeRequest->plan);
        $d = $r->validate(['decision' => 'required|in:approve,reject', 'decision_note' => 'nullable|string|max:2000']);
        $cr = app(ChangeRequestService::class)->decide($r->user(), $changeRequest, $d['decision'] === 'approve', $d['decision_note'] ?? null);

        return back()->with('ok', $cr->status === 'approved'
            ? ($cr->type === 'plan_amendment' ? 'اعتُمد التعديل وحُفظت النسخة v' . $cr->resulting_version_no . ' مع إبقاء النسخ السابقة ولقطات الأرباع المغلقة.' : 'اعتُمدت التسوية وحُفظت مراجعة جديدة للقطة الربع.')
            : 'رُفض طلب التعديل.');
    }
}
