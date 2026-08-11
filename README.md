# ARMM — Agile REST Micro Module

یک فریم‌ورک سبک PHP، طراحی‌شده برای ساخت REST API. `ARMM` هسته‌ی مسیریابی،
تزریق وابستگی، میان‌افزار (Middleware)، احراز هویت، و اتصال دیتابیس را
فراهم می‌کند تا بتوانی مستقیم روی منطق پروژه‌ات تمرکز کنی.

## ویژگی‌ها

- **مسیریابی مبتنی بر Regex** با پشتیبانی از پارامتر (`/projects/{id}`) و گروه‌بندی مسیرها
- **Dependency Injection Container** با Auto-wiring (از طریق Reflection) — دیگر نیازی به ساختن دستی زنجیره‌ی وابستگی‌ها نیست
- **Middleware Pipeline** برای احراز هویت، CORS، و هر منطق مشترک دیگر
- **Request/Response Object** که بدنه‌ی JSON و فرم سنتی را یکسان مدیریت می‌کند
- **JsonResponse** با فرمت خروجی یکدست برای موفقیت/خطا
- **HttpException** برای پرتاب خطاهای HTTP معنادار از هر لایه‌ای از کد
- **Auth** مبتنی بر Session، مناسب پروژه‌هایی با تعداد کاربر محدود (مثل پنل ادمین شخصی)
- **Config** با خطای صریح روی کلید گم‌شده (نه `null` خاموش)
- **Logger** ساده برای ثبت خطا و رویداد

## نصب

```bash
composer require armm/framework
```

یا، تا وقتی روی Packagist ثبت نشده، مستقیم از گیت‌هاب:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/your-username/armm-framework" }
    ],
    "require": {
        "armm/framework": "dev-main"
    }
}
```

## شروع سریع

```php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';

use ARMM\Application;

$app = new Application(basePath: dirname(__DIR__));
$app->loadRoutes(__DIR__ . '/../routes/api.php');
$app->run();
```

```php
// routes/api.php
use App\Controllers\ProjectController;
use ARMM\Middleware\AuthMiddleware;

$router->get('/projects', [ProjectController::class, 'index']);
$router->get('/projects/{id}', [ProjectController::class, 'show']);

$router->group([AuthMiddleware::class], function ($admin) {
    $admin->post('/projects', [ProjectController::class, 'store']);
    $admin->put('/projects/{id}', [ProjectController::class, 'update']);
    $admin->delete('/projects/{id}', [ProjectController::class, 'destroy']);
});
```

```php
// app/Controllers/ProjectController.php
use ARMM\Exceptions\HttpException;
use ARMM\Http\JsonResponse;
use ARMM\Http\Request;

final class ProjectController
{
    public function __construct(private ProjectService $service) {} // Container خودش می‌سازد

    public function index(Request $request): JsonResponse
    {
        return JsonResponse::success($this->service->getAll());
    }

    public function show(Request $request): JsonResponse
    {
        $project = $this->service->find((int) $request->routeParam('id'));

        if (!$project) {
            throw HttpException::notFound('پروژه پیدا نشد');
        }

        return JsonResponse::success($project);
    }
}
```

یک مثال کامل و اجراشدنی در [`examples/mini-api`](examples/mini-api) موجود است.

## پیکربندی

فایل‌های `config/*.php` باید یک آرایه‌ی associative برگردانند. نام فایل، نام گروه تنظیمات می‌شود:

```php
// config/app.php
return [
    'timezone' => 'Asia/Tehran',
    'cors_allowed_origins' => ['http://localhost:3000'],
];
```

```php
$app->config()->get('app', 'timezone');       // 'Asia/Tehran'، یا Exception اگر کلید نبود
$app->config()->getOr('app', 'debug', false); // مقدار پیش‌فرض اگر کلید نبود
```

### CORS

اگر کلید `cors_allowed_origins` را در `config/app.php` تعریف کنی، `Application::boot()`
خودش `CorsMiddleware` را با همان لیست wire می‌کند — نیازی به binding دستی نیست. فقط باید
این middleware را روی route هایی که فرانت باید بتواند صدایشان بزند اضافه کنی:

```php
$router->get('/projects', [ProjectController::class, 'index'])
    ->middleware(CorsMiddleware::class);
```

اگر می‌خواهی رفتار پیش‌فرض (allowedMethods یا allowedHeaders دیگر) را خودت کنترل کنی، پیش
از `$app->run()` می‌توانی صریحاً override کنی:

```php
$app->container()->bind(CorsMiddleware::class, function ($c) {
    return new CorsMiddleware(
        allowedOrigins: ['https://example.com'],
        allowedMethods: ['GET', 'POST'],
    );
});
```

## معماری هسته

| مسیر | مسئولیت |
|---|---|
| `src/Routing/` | تعریف، ثبت، و تطبیق مسیرها |
| `src/Http/` | Request، Response، JsonResponse |
| `src/Middleware/` | قرارداد Middleware + پیاده‌سازی‌های Auth و CORS |
| `src/Container/` | Dependency Injection Container با Auto-wiring |
| `src/Database/` | اتصال Singleton به PDO |
| `src/Config/` | بارگذاری و خواندن تنظیمات |
| `src/Auth/` | احراز هویت مبتنی بر Session |
| `src/Logging/` | ثبت خطا و رویداد در فایل |
| `src/Exceptions/` | HttpException برای خطاهای معنادار HTTP |
| `src/Application.php` | نقطه‌ی مرکزی که همه‌چیز را به هم وصل می‌کند |

برای توضیح تصمیم‌های معماری (چرا Container؟ چرا Middleware؟ چرا Config صریحاً خطا می‌دهد؟) به کامنت‌های بالای هر کلاس مراجعه کن — هرکدام دلیل طراحی خودشان را توضیح می‌دهند.

## تست

```bash
php tests/manual_e2e_test.php
```

این اسکریپت کل چرخه‌ی Router → Middleware → Container → Response را با ۲۰ سناریوی واقعی تست می‌کند (بدون نیاز به فریم‌ورک تست جداگانه).

## نسخه‌بندی و انتشار روی Packagist

1. `composer.json` را نهایی کن (نام، توضیحات، لایسنس)
2. یک تگ نسخه بزن: `git tag v1.0.0 && git push --tags`
3. در [packagist.org](https://packagist.org) ثبت‌نام کن و ریپازیتوری گیت‌هاب را submit کن
4. برای هر آپدیت بعدی، فقط یک تگ نسخه‌ی جدید بزن؛ Packagist خودش تشخیص می‌دهد

## مجوز

MIT
