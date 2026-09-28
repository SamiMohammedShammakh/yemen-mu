<?php

namespace App\Http\Middleware;

use App\Models\Alert;
use App\Support\Access;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ShareContext
{
    public function handle(Request $request, Closure $next)
    {
        if ($u = $request->user()) {
            Access::flush();
            $ctx = Workspace::resolve($u);
            app()->instance(Workspace::class, $ctx);
            View::share('ctx', $ctx);
            View::share('unreadAlerts', Alert::where('user_id', $u->id)->whereNull('read_at')->count());
        }

        return $next($request);
    }
}
