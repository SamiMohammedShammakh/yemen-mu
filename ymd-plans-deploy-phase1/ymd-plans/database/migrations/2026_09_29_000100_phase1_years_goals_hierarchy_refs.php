<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة الأولى من التطوير — تغييرات إضافية فقط:
 * - بيانات إدارة سنة التخطيط (اسم، تواريخ، مسؤول، موعد اعتماد الخطط، حالة مسودة/نشطة/مغلقة).
 * - جدول الأهداف الاستراتيجية للجمعية وربط أهداف الخطط بها.
 * - مستوى «النشاط» تحت المبادرة (projects.parent_id).
 * - ترقيم مرجعي ثابت (ref) للخطط والأهداف والمبادرات والأنشطة والمهام، ورمز مختصر لكل منصب.
 *
 * لا يُحذف أي عمود أو سجل، ولا تُغيَّر أي قيمة قائمة سوى تعبئة الأعمدة الجديدة الفارغة.
 * قيمة حالة السنة الحالية 'open' تبقى كما هي وتعني «نشطة».
 */
return new class extends Migration
{
    private const POSITION_CODES = [
        'president' => 'PRS', 'vice_president' => 'VPR', 'secretary' => 'SEC', 'finance_admin' => 'FIN',
        'planning' => 'PLN', 'pr_media' => 'MED', 'engineering' => 'ENG',
    ];

    public function up(): void
    {
        Schema::table('positions', function (Blueprint $t) {
            $t->string('ref_code', 8)->nullable()->after('code');
        });

        Schema::table('planning_years', function (Blueprint $t) {
            $t->string('name')->nullable()->after('year');
            $t->date('starts_on')->nullable()->after('name');
            $t->date('ends_on')->nullable()->after('starts_on');
            $t->foreignId('owner_user_id')->nullable()->after('ends_on')->constrained('users')->nullOnDelete();
            $t->date('plans_due_on')->nullable()->after('owner_user_id');
            $t->text('description')->nullable()->after('plans_due_on');
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('strategic_goals', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 20)->unique();
            $t->string('title');
            $t->text('description')->nullable();
            $t->unsignedSmallInteger('from_year')->nullable();
            $t->unsignedSmallInteger('to_year')->nullable();
            $t->string('status')->default('active'); // active | archived
            $t->unsignedSmallInteger('sort')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // آخر رقم مستخدم لكل بادئة، حتى لا يُعاد استخدام رقم عنصر محذوف
        Schema::create('ref_sequences', function (Blueprint $t) {
            $t->string('prefix', 60)->primary();
            $t->unsignedInteger('last')->default(0);
            $t->timestamps();
        });

        Schema::table('plans', function (Blueprint $t) {
            $t->string('ref', 40)->nullable()->unique()->after('id');
        });
        Schema::table('objectives', function (Blueprint $t) {
            $t->string('ref', 50)->nullable()->unique()->after('id');
            $t->foreignId('strategic_goal_id')->nullable()->after('plan_id')->constrained('strategic_goals')->nullOnDelete();
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->string('ref', 60)->nullable()->unique()->after('id');
            $t->foreignId('parent_id')->nullable()->after('objective_id')->constrained('projects')->nullOnDelete();
        });
        Schema::table('tasks', function (Blueprint $t) {
            $t->string('ref', 60)->nullable()->unique()->after('id');
        });

        $this->backfill();
    }

    /** تعبئة الأعمدة الجديدة للسجلات القائمة فقط (الأعمدة الفارغة)، بترتيب الإنشاء */
    private function backfill(): void
    {
        foreach (DB::table('positions')->whereNull('ref_code')->get() as $p) {
            DB::table('positions')->where('id', $p->id)->update(['ref_code' => self::POSITION_CODES[$p->code] ?? strtoupper(substr(preg_replace('/[^a-z]/i', '', $p->code), 0, 3)) . $p->id]);
        }

        foreach (DB::table('planning_years')->get() as $y) {
            $q = DB::table('quarters')->where('planning_year_id', $y->id);
            DB::table('planning_years')->where('id', $y->id)->update([
                'name' => $y->name ?? ('سنة التخطيط ' . $y->year),
                'starts_on' => $y->starts_on ?? ($q->min('starts_on') ?? $y->year . '-01-01'),
                'ends_on' => $y->ends_on ?? ((clone $q)->max('ends_on') ?? $y->year . '-12-31'),
            ]);
        }

        $codes = DB::table('positions')->pluck('ref_code', 'id');
        $years = DB::table('planning_years')->pluck('year', 'id');
        foreach (DB::table('plans')->whereNull('ref')->orderBy('id')->get() as $plan) {
            $planRef = 'PL-' . $years[$plan->planning_year_id] . '-' . $codes[$plan->position_id];
            if (DB::table('plans')->where('ref', $planRef)->exists()) {
                $planRef .= '-' . $plan->id;
            }
            DB::table('plans')->where('id', $plan->id)->update(['ref' => $planRef]);

            $n = 0;
            foreach (DB::table('objectives')->where('plan_id', $plan->id)->orderBy('sort')->orderBy('id')->get() as $o) {
                DB::table('objectives')->where('id', $o->id)->update(['ref' => $planRef . '-O' . (++$n)]);
            }
            $n = 0;
            foreach (DB::table('projects')->where('plan_id', $plan->id)->whereNull('parent_id')->orderBy('id')->get() as $p) {
                DB::table('projects')->where('id', $p->id)->update(['ref' => $planRef . '-' . ($p->type === 'project' ? 'P' : 'I') . str_pad((string) (++$n), 2, '0', STR_PAD_LEFT)]);
            }
            $n = 0;
            foreach (DB::table('tasks')->where('plan_id', $plan->id)->orderBy('id')->get() as $t) {
                DB::table('tasks')->where('id', $t->id)->update(['ref' => $planRef . '-T' . str_pad((string) (++$n), 3, '0', STR_PAD_LEFT)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropUnique(['ref']);
            $t->dropColumn('ref');
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->dropForeign(['parent_id']);
            $t->dropUnique(['ref']);
            $t->dropColumn(['ref', 'parent_id']);
        });
        Schema::table('objectives', function (Blueprint $t) {
            $t->dropForeign(['strategic_goal_id']);
            $t->dropUnique(['ref']);
            $t->dropColumn(['ref', 'strategic_goal_id']);
        });
        Schema::table('plans', function (Blueprint $t) {
            $t->dropUnique(['ref']);
            $t->dropColumn('ref');
        });
        Schema::dropIfExists('strategic_goals');
        Schema::dropIfExists('ref_sequences');
        Schema::table('planning_years', function (Blueprint $t) {
            $t->dropForeign(['owner_user_id']);
            $t->dropForeign(['closed_by']);
            $t->dropColumn(['name', 'starts_on', 'ends_on', 'owner_user_id', 'plans_due_on', 'description', 'closed_at', 'closed_by']);
        });
        Schema::table('positions', function (Blueprint $t) {
            $t->dropColumn('ref_code');
        });
    }
};
