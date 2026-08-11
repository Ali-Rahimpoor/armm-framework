<?php

declare(strict_types=1);

namespace ARMM\Routing;

/**
 * نگهدارنده‌ی لیست Route های ثبت‌شده، جدا از منطق تطبیق (Matching).
 * این تفکیک باعث می‌شود Router سبک بماند و بتوان بعداً استراتژی
 * جست‌وجو (مثلاً گروه‌بندی بر اساس متد HTTP، یا حتی کش کردن) را بدون
 * دست‌زدن به منطق ثبت مسیرها تغییر داد.
 */
final class RouteCollection
{
    /** @var Route[] */
    private array $routes = [];

    public function add(Route $route): Route
    {
        $this->routes[] = $route;
        return $route;
    }

    /** @return Route[] */
    public function forMethod(string $method): array
    {
        return array_values(array_filter(
            $this->routes,
            fn (Route $route) => $route->method() === strtoupper($method)
        ));
    }

    /** @return Route[] */
    public function all(): array
    {
        return $this->routes;
    }
}
