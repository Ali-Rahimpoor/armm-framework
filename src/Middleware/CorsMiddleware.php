<?php

declare(strict_types=1);

namespace ARMM\Middleware;

use ARMM\Http\Request;
use ARMM\Http\Response;
use Closure;

/**
 * افزودن هدرهای CORS لازم برای این‌که یک فرانت روی دامنه/پورت دیگر
 * (مثلاً React روی localhost:3000) بتواند با کوکی (credentials) به این
 * API درخواست بزند.
 *
 * نکته‌ی مهم امنیتی: وقتی Allow-Credentials برابر true است، مرورگرها
 * اجازه نمی‌دهند Allow-Origin برابر «*» باشد؛ باید origin دقیق فرانت
 * ذکر شود. به همین دلیل این کلاس یک لیست سفید (Whitelist) از origin های
 * مجاز می‌گیرد، نه یک ستاره‌ی باز.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @param string[] $allowedOrigins */
    public function __construct(
        private array $allowedOrigins,
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization']
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('ORIGIN', '');
        $isAllowed = is_string($origin) && in_array($origin, $this->allowedOrigins, true);

        // درخواست‌های Preflight مرورگر (OPTIONS) باید بدون رسیدن به
        // Controller، مستقیماً با هدرهای CORS پاسخ داده شوند.
        if ($request->method() === 'OPTIONS') {
            $response = new Response('', 204);
        } else {
            $response = $next($request);
        }

        if ($isAllowed) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
        }

        return $response;
    }
}
