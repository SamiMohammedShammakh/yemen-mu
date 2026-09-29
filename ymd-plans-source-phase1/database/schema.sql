-- مخطط قاعدة بيانات نظام الخطط السنوية والمتابعة (SQLite). المصدر المرجعي: database/migrations

CREATE TABLE "alerts" ("id" integer primary key autoincrement not null, "user_id" integer not null, "plan_id" integer, "type" varchar not null, "title" varchar not null, "body" text, "url" varchar, "dedupe_key" varchar, "read_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade, foreign key("plan_id") references "plans"("id") on delete cascade);

CREATE TABLE "attachments" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "progress_update_id" integer, "task_id" integer, "original_name" varchar not null, "path" varchar not null, "mime" varchar, "size" integer not null default '0', "sha256" varchar, "uploaded_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("progress_update_id") references "progress_updates"("id") on delete cascade, foreign key("task_id") references "tasks"("id") on delete set null, foreign key("uploaded_by") references "users"("id"));

CREATE TABLE "audit_logs" ("id" integer primary key autoincrement not null, "user_id" integer, "action" varchar not null, "entity_type" varchar, "entity_id" integer, "plan_id" integer, "old_values" text, "new_values" text, "ip" varchar, "created_at" datetime not null default CURRENT_TIMESTAMP, foreign key("user_id") references "users"("id") on delete set null);

CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));

CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"));

CREATE TABLE "change_requests" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "type" varchar not null, "reason" text not null, "changes" text not null, "impact" text, "quarter" integer, "status" varchar not null default 'pending', "requested_by" integer not null, "decided_by" integer, "decided_at" datetime, "decision_note" text, "base_version_no" integer not null default '0', "resulting_version_no" integer, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("requested_by") references "users"("id"), foreign key("decided_by") references "users"("id"));

CREATE TABLE "corrective_actions" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "indicator_id" integer, "task_id" integer, "title" varchar not null, "reason" text not null, "owner_user_id" integer not null, "due_on" date not null, "expected_result" text not null, "status" varchar not null default 'open', "result_note" text, "created_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("indicator_id") references "indicators"("id") on delete set null, foreign key("task_id") references "tasks"("id") on delete set null, foreign key("owner_user_id") references "users"("id"), foreign key("created_by") references "users"("id"));

CREATE TABLE "delegations" ("id" integer primary key autoincrement not null, "user_id" integer not null, "permission" varchar not null default 'approve_plans', "document_ref" varchar not null, "starts_on" date not null, "ends_on" date, "granted_by" integer not null, "revoked_by" integer, "revoked_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade, foreign key("granted_by") references "users"("id"), foreign key("revoked_by") references "users"("id"));

CREATE TABLE "executive_memberships" ("id" integer primary key autoincrement not null, "user_id" integer not null, "granted_by" integer not null, "granted_at" datetime not null, "grant_reason" text, "revoked_by" integer, "revoked_at" datetime, "revoke_reason" text, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade, foreign key("granted_by") references "users"("id"), foreign key("revoked_by") references "users"("id"));

CREATE TABLE "export_downloads" ("id" integer primary key autoincrement not null, "export_id" integer not null, "user_id" integer not null, "ip" varchar, "created_at" datetime not null default CURRENT_TIMESTAMP, foreign key("export_id") references "exports"("id") on delete cascade, foreign key("user_id") references "users"("id"));

CREATE TABLE "exports" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "user_id" integer not null, "plan_id" integer, "planning_year_id" integer not null, "quarter" integer, "scope" varchar not null, "subject_id" integer, "format" varchar not null, "file_path" varchar not null, "file_name" varchar not null, "size" integer not null default '0', "plan_version_no" integer, "plan_status" varchar, "data_as_of" datetime not null, "rules" text, "download_count" integer not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id"), foreign key("plan_id") references "plans"("id") on delete set null, foreign key("planning_year_id") references "planning_years"("id"));

CREATE TABLE "failed_jobs" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "connection" varchar not null, "queue" varchar not null, "payload" text not null, "exception" text not null, "failed_at" datetime not null default CURRENT_TIMESTAMP);

CREATE TABLE "follow_up_notes" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "parent_id" integer, "from_user_id" integer not null, "body" text not null, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("parent_id") references "follow_up_notes"("id") on delete cascade, foreign key("from_user_id") references "users"("id"));

CREATE TABLE "indicator_targets" ("id" integer primary key autoincrement not null, "indicator_id" integer not null, "quarter" integer not null, "target" numeric, "created_at" datetime, "updated_at" datetime, foreign key("indicator_id") references "indicators"("id") on delete cascade);

CREATE TABLE "indicators" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "objective_id" integer not null, "name" varchar not null, "definition" text, "unit" varchar, "direction" varchar not null default 'higher', "range_min" numeric, "range_max" numeric, "kind" varchar not null default 'cumulative', "aggregation" varchar not null default 'sum', "baseline" numeric, "annual_target" numeric, "data_source" varchar, "verification_method" varchar, "frequency" varchar, "required_evidence" text, "owner_user_id" integer, "weight" numeric, "sort" integer not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("objective_id") references "objectives"("id") on delete cascade, foreign key("owner_user_id") references "users"("id") on delete set null);

CREATE TABLE "job_batches" ("id" varchar not null, "name" varchar not null, "total_jobs" integer not null, "pending_jobs" integer not null, "failed_jobs" integer not null, "failed_job_ids" text not null, "options" text, "cancelled_at" integer, "created_at" integer not null, "finished_at" integer, primary key ("id"));

CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);

CREATE TABLE "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);

CREATE TABLE "objectives" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "title" varchar not null, "description" text, "weight" numeric, "sort" integer not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade);

CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));

CREATE TABLE "plan_reviews" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "action" varchar not null, "from_status" varchar not null, "to_status" varchar not null, "note" text, "version_no" integer not null default '0', "user_id" integer not null, "delegation_id" integer, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("user_id") references "users"("id"), foreign key("delegation_id") references "delegations"("id"));

CREATE TABLE "plan_versions" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "version_no" integer not null, "snapshot" text not null, "reason" text, "change_request_id" integer, "approved_by" integer, "delegation_id" integer, "effective_from" datetime not null, "effective_to" datetime, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("approved_by") references "users"("id"), foreign key("delegation_id") references "delegations"("id"));

CREATE TABLE "planning_years" ("id" integer primary key autoincrement not null, "year" integer not null, "status" varchar not null default 'open', "created_by" integer, "created_at" datetime, "updated_at" datetime, foreign key("created_by") references "users"("id"));

CREATE TABLE "plans" ("id" integer primary key autoincrement not null, "planning_year_id" integer not null, "position_id" integer not null, "owner_user_id" integer not null, "scope_description" text, "overall_outcome" text, "risks" text, "resources" text, "status" varchar not null default 'draft', "current_version" integer not null default '0', "copied_from_plan_id" integer, "created_at" datetime, "updated_at" datetime, foreign key("planning_year_id") references "planning_years"("id"), foreign key("position_id") references "positions"("id"), foreign key("owner_user_id") references "users"("id"), foreign key("copied_from_plan_id") references "plans"("id") on delete set null);

CREATE TABLE "position_user" ("id" integer primary key autoincrement not null, "user_id" integer not null, "position_id" integer not null, "is_primary" tinyint(1) not null default '1', "starts_on" date, "ends_on" date, "assigned_by" integer, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade, foreign key("position_id") references "positions"("id") on delete cascade, foreign key("assigned_by") references "users"("id") on delete set null);

CREATE TABLE "positions" ("id" integer primary key autoincrement not null, "code" varchar not null, "name" varchar not null, "global_view" tinyint(1) not null default '0', "is_planning" tinyint(1) not null default '0', "is_president" tinyint(1) not null default '0', "sort" integer not null default '0', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "progress_updates" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "indicator_id" integer, "task_id" integer, "quarter" integer not null, "period_label" varchar, "actual_value" numeric, "participants" integer, "achieved" text, "not_achieved" text, "delay_reason" text, "obstacles" text, "support_needed" text, "claims_completion" tinyint(1) not null default '0', "status" varchar not null default 'pending', "reviewed_by" integer, "reviewed_at" datetime, "review_note" text, "is_adjustment" tinyint(1) not null default '0', "change_request_id" integer, "plan_version_no" integer not null default '0', "created_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("indicator_id") references "indicators"("id") on delete cascade, foreign key("task_id") references "tasks"("id") on delete cascade, foreign key("reviewed_by") references "users"("id"), foreign key("created_by") references "users"("id"));

CREATE TABLE "projects" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "objective_id" integer, "type" varchar not null default 'project', "name" varchar not null, "description" text, "responsible" varchar, "owner_user_id" integer, "starts_on" date, "ends_on" date, "resources" text, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("objective_id") references "objectives"("id") on delete set null, foreign key("owner_user_id") references "users"("id") on delete set null);

CREATE TABLE "quarter_snapshots" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "quarter" integer not null, "revision" integer not null default '1', "plan_version_no" integer not null, "data" text not null, "rules" text not null, "change_request_id" integer, "created_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("created_by") references "users"("id"));

CREATE TABLE "quarters" ("id" integer primary key autoincrement not null, "planning_year_id" integer not null, "number" integer not null, "starts_on" date not null, "ends_on" date not null, "status" varchar not null default 'open', "closed_at" datetime, "closed_by" integer, "created_at" datetime, "updated_at" datetime, foreign key("planning_year_id") references "planning_years"("id") on delete cascade, foreign key("closed_by") references "users"("id"));

CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));

CREATE TABLE "status_rules" ("id" integer primary key autoincrement not null, "version" integer not null, "on_track_min" numeric not null, "follow_up_min" numeric not null, "is_active" tinyint(1) not null default '0', "created_by" integer, "created_at" datetime, "updated_at" datetime, foreign key("created_by") references "users"("id"));

CREATE TABLE "task_deferrals" ("id" integer primary key autoincrement not null, "task_id" integer not null, "from_quarter" integer not null, "to_quarter" integer not null, "reason" text not null, "owner_user_id" integer, "new_due_on" date, "created_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("task_id") references "tasks"("id") on delete cascade, foreign key("owner_user_id") references "users"("id"), foreign key("created_by") references "users"("id"));

CREATE TABLE "tasks" ("id" integer primary key autoincrement not null, "plan_id" integer not null, "project_id" integer, "title" varchar not null, "description" text, "responsible" varchar, "owner_user_id" integer, "quarter" integer not null, "original_quarter" integer not null, "due_on" date, "required_evidence" text, "status" varchar not null default 'planned', "completed_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("plan_id") references "plans"("id") on delete cascade, foreign key("project_id") references "projects"("id") on delete set null, foreign key("owner_user_id") references "users"("id") on delete set null);

CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "phone" varchar, "is_active" tinyint(1) not null default '1', "is_system_admin" tinyint(1) not null default '0', "last_login_at" datetime, "remember_token" varchar, "created_at" datetime, "updated_at" datetime);

CREATE UNIQUE INDEX "alerts_user_id_dedupe_key_unique" on "alerts" ("user_id", "dedupe_key");

CREATE INDEX "audit_logs_entity_type_entity_id_index" on "audit_logs" ("entity_type", "entity_id");

CREATE INDEX "cache_expiration_index" on "cache" ("expiration");

CREATE INDEX "cache_locks_expiration_index" on "cache_locks" ("expiration");

CREATE UNIQUE INDEX "exports_uuid_unique" on "exports" ("uuid");

CREATE INDEX "failed_jobs_connection_queue_failed_at_index" on "failed_jobs" ("connection", "queue", "failed_at");

CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid");

CREATE UNIQUE INDEX "indicator_targets_indicator_id_quarter_unique" on "indicator_targets" ("indicator_id", "quarter");

CREATE INDEX "jobs_queue_index" on "jobs" ("queue");

CREATE UNIQUE INDEX "plan_versions_plan_id_version_no_unique" on "plan_versions" ("plan_id", "version_no");

CREATE UNIQUE INDEX "planning_years_year_unique" on "planning_years" ("year");

CREATE UNIQUE INDEX "plans_planning_year_id_position_id_unique" on "plans" ("planning_year_id", "position_id");

CREATE INDEX "position_user_position_id_ends_on_index" on "position_user" ("position_id", "ends_on");

CREATE UNIQUE INDEX "positions_code_unique" on "positions" ("code");

CREATE INDEX "progress_updates_plan_id_status_index" on "progress_updates" ("plan_id", "status");

CREATE UNIQUE INDEX "quarter_snapshots_plan_id_quarter_revision_unique" on "quarter_snapshots" ("plan_id", "quarter", "revision");

CREATE UNIQUE INDEX "quarters_planning_year_id_number_unique" on "quarters" ("planning_year_id", "number");

CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");

CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");

CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");

