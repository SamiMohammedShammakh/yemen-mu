<?php

/*
 * نقطة الدخول الوحيدة الظاهرة للزوار.
 * كل ملفات التطبيق (.env و config و database و storage و vendor) في مجلد ymd-plans
 * المجاور لـ public_html، أي خارج جذر الويب.
 *
 * إن رفعت مجلد التطبيق باسم أو مكان مختلف، عدّل السطر التالي فقط.
 */
$appPath = __DIR__ . '/../ymd-plans';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (! is_file($appPath . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('Application folder not found. Check $appPath in public_html/index.php');
}

// وضع الصيانة (php artisan down)
if (file_exists($maintenance = $appPath . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath . '/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath . '/bootstrap/app.php';

// الملفات العامة (CSS والخطوط والصور) في هذا المجلد وليست في ymd-plans/public
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
