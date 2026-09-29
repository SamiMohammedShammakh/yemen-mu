# نتائج الاختبارات

**التشغيل:** 26 سبتمبر 2026 — PHP 8.4.21، PHPUnit 12.5.36، قاعدة SQLite جديدة في الذاكرة لكل اختبار مع البيانات التجريبية، وتاريخ مثبّت على 26-09-2026 (الربع الأول مغلق، الثاني مستحق، الثالث لم ينتهِ).

**النتيجة:** ✅ **25 اختبارًا ناجحًا من 25 — 340 تحققًا — لا إخفاقات** (17 ثانية).

لإعادة التشغيل: `php vendor/bin/phpunit --testdox`

## مطابقة حالات الاختبار المطلوبة

| # | الحالة المطلوبة | الاختبار | ما يتحقق منه | النتيجة |
|---|---|---|---|---|
| 1 | يدخل كل مستخدم إلى مساحة منصبه الصحيحة | `PermissionTest::each_user_lands_in_own_position_workspace`، `user_with_two_positions_can_switch_only_to_own_workspaces` | دخول المناصب السبعة وفتح لوحة المنصب؛ مدير النظام إلى إدارة الحسابات؛ تبديل صاحب منصبين بين مساحتيه فقط ورفض مساحة غيره (403) | ✅ |
| 2 | لا يرى نائب الرئيس خطط الآخرين إلا إذا أُضيف رسميًا للإدارة التنفيذية | `vice_president_needs_explicit_executive_membership` | 403 على خطة الهندسة ولوحة الرئيس، وخطة واحدة في API؛ بعد المنح يرى 5 خطط ويُسجل المانح والوقت؛ بعد الإيقاف يعود 403 ويُسجل في التدقيق | ✅ |
| 3 | يرى الرئيس والإدارة التنفيذية المعتمدة ومسؤول التخطيط جميع الخطط | `president_planning_and_executive_see_all_plans` | صفحة كل خطة ونتائجها في API لكل واحد منهم | ✅ |
| 4 | لا يصل مسؤول إلى خطة أخرى بتغيير الرابط أو API أو تنزيل ملفها | `restricted_user_cannot_reach_other_plan_by_any_route`، `system_admin_manages_accounts_but_cannot_read_plans`، `approval_delegate_sees_only_plans_awaiting_decision` | 12 رابط صفحة (خطة، تبويب الملفات، ربع، مراجعة، نسخة، هدف، مؤشر، مشروع، مرفق، تنزيل ملف، طلب تعديل، معاينة ملف) و3 مسارات API كلها 403؛ رفض إنشاء ملف أو تحديث أو نقل مهمة أو ملاحظة؛ البحث والقوائم والتقارير وسجل الملفات لا تكشف أسماء الخطة الأخرى؛ مدير النظام لا يقرأ الخطط؛ المفوّض لا يرى إلا الخطط التي تنتظر قراره | ✅ |
| 5 | لا تُرسل خطة إذا لم تكتمل المؤشرات أو الأوزان أو المستهدفات | `incomplete_plan_cannot_be_submitted_and_each_gap_is_explained`، `indicator_weights_inside_objective_must_total_100` | رفض الإرسال مع رسائل: مجموع الأوزان 90%، هدف بلا مؤشر، مصدر بيانات مفقود، مستهدف الربع 4 مفقود، أوزان مؤشرات الهدف 90%؛ بعد الاستكمال تُرسل | ✅ |
| 6 | تظهر خطة السنة بأرباعها الأربعة وبالمستهدف السنوي والمرحلي الصحيح | `four_quarters_and_cumulative_targets_and_worked_example`، `due_quarter_without_approved_data_shows_missing_not_zero` | أربعة أرباع؛ المستهدف التراكمي 2، 5، 8، 12؛ **المثال: 80% مقابل مستهدف النصف الأول، فجوة برنامج واحد، 33.3% مقابل السنوي**؛ رضا المتدربين متوسط مرجح 79% لا جمع؛ الربع الثالث «لم يستحق بعد» والمستحق بلا بيانات «بيانات ناقصة» لا صفر؛ إنجاز الهدف الموزون 85.5% | ✅ |
| 7 | لا يدخل التحديث بانتظار التحقق في الإنجاز المعتمد | `pending_update_is_excluded_until_planning_approves_it`، `completion_claim_waits_for_verification_and_updates_are_immutable`، `closed_quarter_rejects_direct_updates`، `quarter_close_blocked_while_evidence_pending` | التحديث المعلق لا يُحتسب (السنوي يبقى 4)؛ صاحب المنصب لا يعتمد تحديثه؛ الإعادة تتطلب سببًا؛ بعد الاعتماد يصبح التراكمي 5 من 8 = 62.5%؛ إعلان الاكتمال يبقى «بانتظار التحقق»؛ التحديث المسجل لا يُعدّل؛ الربع المغلق يرفض التحديث المباشر؛ لا يُقفل ربع فيه أدلة معلقة | ✅ |
| 8 | لا يغيّر طلب تعديل الخطة نتائج الأرباع المغلقة دون حفظ النسخ والسجل | `amendment_keeps_versions_and_closed_quarter_snapshots`، `closed_quarter_result_change_adds_snapshot_revision_and_keeps_original`، `deferred_task_stays_late_in_original_quarter`، `copy_structure_to_new_year_without_progress_or_approval` | التعديل المباشر بعد الاعتماد 403؛ الطلب يحفظ القديم والجديد والأثر؛ صاحب المنصب لا يعتمده؛ اعتماد الرئيس ينشئ v2 وتبقى v1 بقيمها؛ نتيجة الربع الأول المغلق ثابتة بلقطتها؛ الربع المفتوح يُحسب بالجديد؛ تعديل نتيجة ربع مغلق ينشئ المراجعة 2 ويبقي الأصلية؛ المهمة المنقولة تبقى متأخرة في ربعها الأصلي؛ نسخ الهيكل لسنة جديدة بلا إنجاز أو أدلة أو اعتماد | ✅ |
| 9 | صاحب المنصب ينزّل خطته ونتائجها فقط، وأصحاب الصلاحية الشاملة أي خطة | `owner_downloads_only_own_plan_and_global_users_download_any`، `permission_is_rechecked_at_download_time` | المعاينة تعرض المنصب والنسخة والحالة وتاريخ البيانات؛ إنشاء وتنزيل PDF/DOCX/XLSX/CSV/ZIP لخطته؛ رفض خطة أخرى والتقرير الشامل وعنصر من خطة أخرى؛ الرئيس والتخطيط ينزلان أي خطة والتقرير الشامل؛ تسجيل الإنشاء والتنزيل؛ سحب العضوية يمنع تنزيل ملف أُنشئ سابقًا | ✅ |
| 10 | تطابق الملفات الأرقام المعروضة وتوضح السنة والربع والنسخة وحالة الاعتماد | `files_match_system_numbers_and_carry_header`، `draft_plan_file_is_marked_as_draft_and_editing_file_changes_nothing`، `closed_quarter_file_uses_snapshot_numbers` | CSV: رأس (المنصب، 2026، الربع الثاني، v1، معتمدة) وصف المؤشر 5 / 4 / 80% / 33.3%؛ XLSX: ورقة بيانات رقمية بنفس الأرقام وإنجاز الخطة 91.3% ورأس النسخة والحالة واتجاه RTL؛ DOCX: رأس الصفحة بكل البيانات؛ PDF: 91.3% و80%؛ ZIP: فهرس + الخطة + تقارير الأرباع الأربعة؛ ملف المسودة موسوم «مسودة» وتعديله لا يغيّر الخطة؛ ملف الربع المغلق من لقطة الإقفال | ✅ |

## المخرجات الخام

```
PHPUnit 12.5.36 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.21
Configuration: /home/claude/jamiya/phpunit.xml

.........................                                         25 / 25 (100%)

Time: 00:17.189, Memory: 93.00 MB

Download (Tests\Feature\Download)
 ✔ Owner downloads only own plan and global users download any
 ✔ Permission is rechecked at download time
 ✔ Files match system numbers and carry header
 ✔ Draft plan file is marked as draft and editing file changes nothing
 ✔ Closed quarter file uses snapshot numbers

Permission (Tests\Feature\Permission)
 ✔ Each user lands in own position workspace
 ✔ User with two positions can switch only to own workspaces
 ✔ Vice president needs explicit executive membership
 ✔ President planning and executive see all plans
 ✔ Restricted user cannot reach other plan by any route
 ✔ System admin manages accounts but cannot read plans
 ✔ Approval delegate sees only plans awaiting decision
 ✔ Inactive account cannot log in

Plan Rules (Tests\Feature\PlanRules)
 ✔ Incomplete plan cannot be submitted and each gap is explained
 ✔ Indicator weights inside objective must total 100
 ✔ Four quarters and cumulative targets and worked example
 ✔ Due quarter without approved data shows missing not zero
 ✔ Pending update is excluded until planning approves it
 ✔ Completion claim waits for verification and updates are immutable
 ✔ Closed quarter rejects direct updates
 ✔ Deferred task stays late in original quarter
 ✔ Amendment keeps versions and closed quarter snapshots
 ✔ Closed quarter result change adds snapshot revision and keeps original
 ✔ Copy structure to new year without progress or approval
 ✔ Quarter close blocked while evidence pending

OK (25 tests, 340 assertions)
```

## فحوص يدوية إضافية

- فتح كل شاشة (38 رابطًا) بحسابات: الشؤون الهندسية، التخطيط، الرئيس، نائب الرئيس، مدير النظام — دون أخطاء، مع 403 في المواضع المتوقعة.
- إنشاء كل تركيبة نطاق × صيغة (33 ملفًا) بنجاح، ومراجعة PDF بصريًا: تشكيل عربي صحيح بخط Cairo، شعار الجمعية ورأس الملف في كل صفحة، ترقيم الصفحات.
- مراجعة الواجهة على عرض 1366 بكسل (حاسوب) و390 بكسل (جوال).
