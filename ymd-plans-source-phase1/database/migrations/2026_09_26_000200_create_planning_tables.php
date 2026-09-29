<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سنوات التخطيط والأرباع، الخطط ونسخها، الأهداف والمؤشرات والمستهدفات الربعية، المشاريع والمهام.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('status')->default('open'); // open | closed
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('quarters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number'); // 1..4
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('open'); // open | closed
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['planning_year_id', 'number']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_year_id')->constrained();
            $table->foreignId('position_id')->constrained();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->text('scope_description')->nullable();
            $table->text('overall_outcome')->nullable();
            $table->text('risks')->nullable();
            $table->text('resources')->nullable();
            // draft | submitted | returned | recommended | approved | active | closed
            $table->string('status')->default('draft');
            $table->unsignedInteger('current_version')->default(0); // 0 = لم تُعتمد بعد
            $table->foreignId('copied_from_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamps();
            $table->unique(['planning_year_id', 'position_id']);
        });

        // لقطة كاملة لكل نسخة معتمدة من الخطة
        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->json('snapshot');
            $table->text('reason')->nullable();
            $table->foreignId('change_request_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('delegation_id')->nullable()->constrained('delegations');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'version_no']);
        });

        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('objective_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('definition')->nullable();
            $table->string('unit')->nullable();
            $table->string('direction')->default('higher'); // higher | lower | range
            $table->decimal('range_min', 14, 2)->nullable();
            $table->decimal('range_max', 14, 2)->nullable();
            $table->string('kind')->default('cumulative'); // cumulative | periodic | point
            // periodic: sum|average|last — point: weighted_average|average|last
            $table->string('aggregation')->default('sum');
            $table->decimal('baseline', 14, 2)->nullable();
            $table->decimal('annual_target', 14, 2)->nullable();
            $table->string('data_source')->nullable();
            $table->string('verification_method')->nullable();
            $table->string('frequency')->nullable(); // monthly|quarterly|semiannual|annual
            $table->text('required_evidence')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('weight', 6, 2)->nullable(); // وزن المؤشر داخل الهدف (اختياري)
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // مستهدف كل ربع: قيمة الفترة (للتراكمي يُحسب المستهدف التراكمي بالجمع)
        Schema::create('indicator_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->decimal('target', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['indicator_id', 'quarter']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('objective_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('project'); // initiative | project
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('responsible')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('resources')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('responsible')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->unsignedTinyInteger('original_quarter');
            $table->date('due_on')->nullable();
            $table->text('required_evidence')->nullable();
            // planned | in_progress | pending_verification | done
            $table->string('status')->default('planned');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('task_deferrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('from_quarter');
            $table->unsignedTinyInteger('to_quarter');
            $table->text('reason');
            $table->foreignId('owner_user_id')->nullable()->constrained('users');
            $table->date('new_due_on')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['task_deferrals', 'tasks', 'projects', 'indicator_targets', 'indicators', 'objectives', 'plan_versions', 'plans', 'quarters', 'planning_years'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
