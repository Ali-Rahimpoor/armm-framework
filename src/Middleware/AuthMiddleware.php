<?php

declare(strict_types=1);

namespace ARMM\Middleware;

use ARMM\Auth\Auth;
use ARMM\Http\JsonResponse;
use ARMM\Http\Request;
use ARMM\Http\Response;
use Closure;

/**
 * درخواست‌هایی که به این Middleware مقید شده‌اند، تنها در صورتی به
 * Controller می‌رسند که کاربر لاگین کرده باشد؛ در غیر این صورت،
 * بلافاصله 401 Unauthorized برمی‌گردد و زنجیره متوقف می‌شود.
 *
 * این دقیقاً همان لایه‌ای است که در نسخه‌ی قبلی نبود: به‌جای این‌که هر
 * Controller جداگانه و به‌صورت پراکنده چک لاگین بودن را انجام دهد (و
 * ممکن است در یک Controller جدید فراموش شود)، این چک یک‌بار و در سطح
 * تعریف Route اعمال می‌شود و دیگر نمی‌توان فراموشش کرد.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return JsonResponse::unauthorized();
        }

        return $next($request);
    }
}
