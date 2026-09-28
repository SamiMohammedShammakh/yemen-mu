<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\Objective;
use App\Models\Project;
use App\Models\Task;
use App\Support\Access;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** البحث مقيّد بنطاق الخطط المرئية للمستخدم — لا يظهر اسم أي عنصر من خطة أخرى */
    public static function run($user, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $plans = Access::visiblePlans($user)->select('id');
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $with = ['plan.position', 'plan.year'];

        return [
            'objectives' => Objective::whereIn('plan_id', $plans)->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))->with($with)->limit(20)->get(),
            'indicators' => Indicator::whereIn('plan_id', $plans)->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('definition', 'like', $like))->with($with)->limit(20)->get(),
            'projects' => Project::whereIn('plan_id', $plans)->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like))->with($with)->limit(20)->get(),
            'tasks' => Task::whereIn('plan_id', $plans)->where('title', 'like', $like)->with($with)->limit(20)->get(),
        ];
    }

    public function index(Request $r)
    {
        $term = (string) $r->query('q', '');
        $results = self::run($r->user(), $term);

        return view('search', compact('term', 'results'));
    }
}
