<?php

declare(strict_types=1);

namespace ARMM\Middleware;

use ARMM\Http\Request;
use ARMM\Http\Response;
use Closure;

/**
 * قرارداد مشترک هر Middleware.
 *
 * یک Middleware کاری قبل (و می‌تواند بعد) از رسیدن درخواست به Controller
 * انجام می‌دهد: چک احراز هویت، افزودن هدرهای CORS، محدودسازی نرخ درخواست، و...
 *
 * $next یک Closure است که باقی‌ی زنجیره (Middleware بعدی، یا در نهایت
 * خود Controller) را اجرا می‌کند. اگر یک Middleware $next را صدا نزند،
 * درخواست همان‌جا متوقف می‌شود (مثلاً وقتی AuthMiddleware تشخیص می‌دهد
 * کاربر لاگین نکرده و بلافاصله 401 برمی‌گرداند).
 */
interface MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response;
}
