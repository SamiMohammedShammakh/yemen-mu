<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * إنشاء أول حساب لمدير النظام التقني (أو حساب الرئيس) عند النشر، دون أي كلمة مرور ثابتة في الشيفرة.
 * مدير النظام يدير الحسابات والمناصب فقط ولا يرى الخطط.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email? : البريد الإلكتروني} {--name=مدير النظام التقني : الاسم}';

    protected $description = 'إنشاء حساب مدير النظام التقني بكلمة مرور تُدخل بشكل مخفي';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('البريد الإلكتروني لمدير النظام');
        $password = $this->secret('كلمة المرور (12 حرفًا على الأقل، حروف وأرقام ورموز)');
        $confirm = $this->secret('أعد كتابة كلمة المرور');

        $v = Validator::make(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            ['email' => 'required|email|unique:users,email', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->symbols()]],
        );
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $u = User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'is_system_admin' => true, 'is_active' => true]);
        Audit::log('user.create', $u, null, ['email' => $email, 'is_system_admin' => true, 'via' => 'artisan admin:create']);
        $this->info("أُنشئ حساب مدير النظام: {$email}");
        $this->line('ادخل به، ثم أنشئ حسابات المناصب من «الحسابات والمناصب» وأسند منصب الرئيس ومسؤول التخطيط.');

        return self::SUCCESS;
    }
}
