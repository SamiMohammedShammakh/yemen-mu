<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required'], [], ['email' => 'البريد الإلكتروني', 'password' => 'كلمة المرور']);
        if (! Auth::attempt($cred + ['is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة أو الحساب موقوف.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->forget(['ws', 'year_id', 'quarter']);
        $request->user()->forceFill(['last_login_at' => now()])->save();
        Audit::log('auth.login', $request->user());

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        Audit::log('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
