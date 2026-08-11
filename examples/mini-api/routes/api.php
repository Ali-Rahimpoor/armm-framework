<?php

/**
 * @var \ARMM\Routing\Router $router این متغیر توسط Application::loadRoutes تزریق می‌شود
 */

use App\Controllers\ProjectController;
use ARMM\Middleware\AuthMiddleware;
use ARMM\Middleware\CorsMiddleware;

// CorsMiddleware اگر cors_allowed_origins در config/app.php تعریف شده
// باشد، به‌صورت خودکار توسط Application::boot() ساخته می‌شود؛ این‌جا فقط
// باید بگوییم کدام مسیرها باید از آن استفاده کنند.

// ---- مسیرهای عمومی: هر بازدیدکننده، بدون لاگین ----
$router->get('/projects', [ProjectController::class, 'index'])
    ->middleware(CorsMiddleware::class);

$router->get('/projects/{id}', [ProjectController::class, 'show'])
    ->middleware(CorsMiddleware::class);

// ---- مسیرهای خصوصی: فقط خودت، پس از لاگین ----
$router->group([CorsMiddleware::class, AuthMiddleware::class], function ($admin) {
    $admin->post('/projects', [ProjectController::class, 'store']);
    // $admin->put('/projects/{id}', [ProjectController::class, 'update']);
    // $admin->delete('/projects/{id}', [ProjectController::class, 'destroy']);
});
