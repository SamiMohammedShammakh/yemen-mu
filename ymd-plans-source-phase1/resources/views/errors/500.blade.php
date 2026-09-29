<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>حدث خطأ غير متوقع</title><link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3"></head>
<body><div style="max-width:520px;margin:12vh auto;padding:0 1rem;text-align:center">
<img src="{{ asset('img/logo-full.png') }}" alt="" style="height:64px;margin-bottom:1.5rem">
<h1>حدث خطأ غير متوقع</h1>
<p class="muted">@if (500 == 403){{ $exception->getMessage() ?: 'هذه الصفحة أو الملف خارج نطاق صلاحياتك.' }}@elseif (500 == 404)قد يكون الرابط غير صحيح أو أن العنصر لم يعد موجودًا.@elseif (500 == 419)أعد تحميل الصفحة ثم حاول مرة أخرى.@else نعتذر، حاول لاحقًا.@endif</p>
<a class="btn" href="{{ url('/') }}">العودة إلى لوحتي</a>
</div></body></html>
