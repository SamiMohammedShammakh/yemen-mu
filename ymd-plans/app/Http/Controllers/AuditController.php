<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Access;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        abort_unless(Access::canSeeAudit($u), 403);
        $q = AuditLog::with('user')->latest('id');
        if (! Access::hasGlobalView($u)) {
            // مدير النظام التقني: سجل الحسابات والصلاحيات فقط، دون محتوى الخطط
            $q->whereNull('plan_id')->where(fn ($w) => $w->where('action', 'like', 'user.%')->orWhere('action', 'like', 'executive.%')
                ->orWhere('action', 'like', 'delegation.%')->orWhere('action', 'like', 'assignment.%')->orWhere('action', 'like', 'auth.%'));
        }
        if ($a = $r->query('action')) {
            $q->where('action', 'like', $a . '%');
        }
        $logs = $q->paginate(50)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
