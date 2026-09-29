<?php

namespace App\Http\Controllers;

use App\Models\PlanningYear;
use App\Support\Access;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function update(Request $request)
    {
        $u = $request->user();
        if ($ws = $request->input('ws')) {
            abort_unless(isset(Access::workspaces($u)[$ws]), 403, 'لا تملك صلاحية على مساحة العمل هذه.');
            session(['ws' => $ws]);

            return redirect()->route('home');
        }
        if ($request->filled('year_id')) {
            $y = PlanningYear::findOrFail($request->integer('year_id'));
            session(['year_id' => $y->id]);
        }
        if ($request->filled('quarter')) {
            session(['quarter' => max(1, min(4, $request->integer('quarter')))]);
        }

        // اختيار الربع من الشريط العلوي يلغي أي ربع مثبت في رابط الصفحة
        $prev = url()->previous();
        $parts = parse_url($prev);
        parse_str($parts['query'] ?? '', $qs);
        unset($qs['quarter'], $qs['page']);
        $to = strtok($prev, '?') . ($qs ? '?' . http_build_query($qs) : '');

        return redirect($to);
    }
}
