<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول — نظام الخطط السنوية والمتابعة</title>
    <link rel="icon" href="{{ asset('img/logo-mark.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
</head>
<body>
<div class="auth">
    <section class="side">
        <div>
            <img class="mark" src="{{ asset('img/logo-mark.png') }}" alt="" style="border-radius:14px;background:#fff;padding:6px">
            <h2>نظام الخطط السنوية <span class="accent">والمتابعة</span></h2>
            <p>مساحة عمل لكل منصب في جمعية المهندسين اليمنيين في تركيا: خطة سنوية مقسمة إلى أربعة أرباع، مؤشرات قابلة للقياس، تحقق من الأدلة، وتقارير قابلة للتنزيل.</p>
            <ul>
                <li>كل مسؤول يرى خطته ومهامه وملفاته فقط.</li>
                <li>الإنجاز الرسمي يُحتسب من التحديثات المعتمدة فقط.</li>
                <li>كل نسخة معتمدة وكل ربع مغلق محفوظ بسجله.</li>
            </ul>
        </div>
        <p class="small foot" style="color:#8f959a">YEMENLİ MÜHENDİSLER DERNEĞİ — TÜRKİYE</p>
    </section>
    <section class="form">
        <div class="box">
            <img class="logo" src="{{ asset('img/logo-full.png') }}" alt="جمعية المهندسين اليمنيين — تركيا">
            <h1>تسجيل الدخول</h1>
            <p class="muted small">ادخل بحسابك لتُفتح لك لوحة عمل منصبك.</p>
            @if ($errors->any())
                <div class="flash err" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ url('/login') }}">
                @csrf
                <div class="field">
                    <label class="f" for="email">البريد الإلكتروني</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" dir="ltr">
                </div>
                <div class="field">
                    <label class="f" for="password">كلمة المرور</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" dir="ltr">
                </div>
                <label class="check" style="margin-bottom:1rem"><input type="checkbox" name="remember" value="1"> تذكرني على هذا الجهاز</label>
                <button class="btn" style="width:100%">دخول</button>
            </form>
        </div>
    </section>
</div>
</body>
</html>
