<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التحديثات والأدلة، المراجعات والاعتمادات، طلبات التغيير، الإجراءات التصحيحية وملاحظات المتابعة،
 * لقطات الأرباع، التنبيهات، سجل التدقيق، عمليات التصدير.
 */
return new class extends Migration
{
    public function up(): void
    {
        // سجل زمني لا يُمحى من الواجهة: لا توجد مسارات تعديل أو حذف للتحديثات
        Schema::create('progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('indicator_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->string('period_label')->nullable();
            $table->decimal('actual_value', 14, 2)->nullable();
            $table->unsignedInteger('participants')->nullable(); // للمتوسط المرجح
            $table->text('achieved')->nullable();
            $table->text('not_achieved')->nullable();
            $table->text('delay_reason')->nullable();
            $table->text('obstacles')->nullable();
            $table->text('support_needed')->nullable();
            $table->boolean('claims_completion')->default(false);
            // pending | approved | returned
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->boolean('is_adjustment')->default(false);
            $table->foreignId('change_request_id')->nullable();
            $table->unsignedInteger('plan_version_no')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['plan_id', 'status']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('progress_update_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        // سجل دورة الاعتماد: إرسال، إعادة، توصية، اعتماد، تفعيل، إغلاق
        Schema::create('plan_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('from_status');
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->unsignedInteger('version_no')->default(0);
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('delegation_id')->nullable()->constrained('delegations');
            $table->timestamps();
        });

        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // plan_amendment | closed_quarter_result
            $table->text('reason');
            $table->json('changes');   // [{entity, id, field, label, old, new}]
            $table->json('impact')->nullable();
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->unsignedInteger('base_version_no')->default(0);
            $table->unsignedInteger('resulting_version_no')->nullable();
            $table->timestamps();
        });

        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('indicator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('reason');
            $table->foreignId('owner_user_id')->constrained('users');
            $table->date('due_on');
            $table->text('expected_result');
            $table->string('status')->default('open'); // open | done | cancelled
            $table->text('result_note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('follow_up_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('follow_up_notes')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users');
            $table->text('body');
            $table->timestamps();
        });

        // لقطة الربع عند الإقفال (مع مراجعات لاحقة عبر طلب تغيير)
        Schema::create('quarter_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('plan_version_no');
            $table->json('data');
            $table->json('rules');
            $table->foreignId('change_request_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['plan_id', 'quarter', 'revision']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dedupe_key']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreignId('plan_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('planning_year_id')->constrained();
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->string('scope');  // plan_full|plan_quarter|results_annual|results_quarter|objective|indicator|project|year_package|org_summary
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('format'); // pdf|docx|xlsx|csv|zip
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('plan_version_no')->nullable();
            $table->string('plan_status')->nullable();
            $table->timestamp('data_as_of');
            $table->json('rules')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();
        });

        Schema::create('export_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['export_downloads', 'exports', 'audit_logs', 'alerts', 'quarter_snapshots', 'follow_up_notes', 'corrective_actions', 'change_requests', 'plan_reviews', 'attachments', 'progress_updates'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
