<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المناصب، إسناد المناصب للمستخدمين، عضوية الإدارة التنفيذية، التفويضات، قواعد الحالات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            // الرؤية الشاملة بحكم المنصب: الرئيس ومسؤول التخطيط فقط (نائب الرئيس = false)
            $table->boolean('global_view')->default(false);
            $table->boolean('is_planning')->default(false);
            $table->boolean('is_president')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('position_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['position_id', 'ends_on']);
        });

        // سجل عضوية الإدارة التنفيذية: كل منح أو إيقاف يبقى محفوظًا
        Schema::create('executive_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('granted_at');
            $table->text('grant_reason')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->timestamps();
        });

        // تفويض صلاحية اعتماد الخطط بوثيقة مرجعية
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission')->default('approve_plans');
            $table->string('document_ref');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('granted_by')->constrained('users');
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // حدود حالات الإنجاز القابلة للضبط، بنسخ محفوظة
        Schema::create('status_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version');
            $table->decimal('on_track_min', 6, 2);      // >= يسير حسب الخطة
            $table->decimal('follow_up_min', 6, 2);     // >= يحتاج متابعة، وما دونه متأخر
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_rules');
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('executive_memberships');
        Schema::dropIfExists('position_user');
        Schema::dropIfExists('positions');
    }
};
