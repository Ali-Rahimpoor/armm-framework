<?php

declare(strict_types=1);

namespace ARMM\Routing;

use ARMM\Container\Container;
use ARMM\Http\JsonResponse;
use ARMM\Http\Request;
use ARMM\Http\Response;
use ARMM\Middleware\MiddlewareInterface;
use RuntimeException;

/**
 * مسئول ثبت مسیرها و پیدا کردن/اجرای مسیر منطبق با یک درخواست ورودی.
 *
 * جریان کار dispatch:
 *   1. بین Route های همان متد HTTP، اولین موردی که با URI تطبیق دارد پیدا می‌شود
 *   2. پارامترهای استخراج‌شده از URL (مثل {id}) داخل Request قرار می‌گیرند
 *   3. زنجیره‌ی Middleware های آن Route (اگر باشند) به ترتیب اجرا می‌شوند
 *   4. در انتهای زنجیره، Controller از طریق Container ساخته و متد موردنظر صدا زده می‌شود
 */
final class Router
{
    use HttpVerbShortcuts;

    private RouteCollection $routes;

    public function __construct(private Container $container)
    {
        $this->routes = new RouteCollection();
    }

    protected function register(string $method, string $pattern, array|\Closure $handler): Route
    {
        return $this->routes->add(new Route($method, $pattern, $handler));
    }

    /**
     * گروهی از Route ها که همگی یک یا چند Middleware مشترک دارند
     * (مثلاً همه‌ی مسیرهای مدیریتی که نیاز به AuthMiddleware دارند).
     */
    public function group(array $middleware, \Closure $callback): void
    {
        $recorder = new RouteGroupRecorder($this, $middleware);
        $callback($recorder);
    }

    /** برای استفاده‌ی داخلی توسط RouteGroupRecorder */
    public function registerWithMiddleware(string $method, string $pattern, array|\Closure $handler, array $middleware): Route
    {
        $route = $this->register($method, $pattern, $handler);
        $route->middleware($middleware);
        return $route;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $this->normalizeUri($request->uri());

        // مرورگرها پیش از هر درخواست غیرساده‌ی cross-origin، یک درخواست
        // OPTIONS (Preflight) می‌فرستند که باید بدون نیاز به تطبیق مسیر
        // واقعی، توسط CorsMiddleware پاسخ داده شود.
        if ($method === 'OPTIONS') {
            $route = $this->findFirstMatch($uri) ?? new Route('OPTIONS', $uri, fn () => new Response('', 204));
        } else {
            $route = $this->match($method, $uri);
        }

        if ($route === null) {
            return JsonResponse::notFound('این مسیر پیدا نشد');
        }

        if ($method !== 'OPTIONS') {
            [, $paramNames] = $route->compile();
            $params = $this->extractParams($route, $uri, $paramNames);
            $request->setRouteParams($params);
        }

        return $this->runPipeline($route, $request);
    }

    /**
     * حذف اسلش انتهایی از URI (به‌جز خود مسیر ریشه‌ی «/») تا
     * domain.ir/products و domain.ir/products/ هر دو با یک Route
     * تطبیق پیدا کنند. Route ها همیشه بدون اسلش انتهایی ثبت می‌شوند،
     * پس این نرمال‌سازی باید همیشه پیش از match() انجام شود.
     */
    private function normalizeUri(string $uri): string
    {
        if ($uri === '/') {
            return $uri;
        }

        return rtrim($uri, '/') ?: '/';
    }

    private function match(string $method, string $uri): ?Route
    {
        foreach ($this->routes->forMethod($method) as $route) {
            [$regex] = $route->compile();
            if (preg_match($regex, $uri)) {
                return $route;
            }
        }

        return null;
    }

    /** برای درخواست OPTIONS: پیدا کردن یک Route با همان URI در هر متدی، فقط برای گرفتن Middleware های CORS */
    private function findFirstMatch(string $uri): ?Route
    {
        foreach ($this->routes->all() as $route) {
            [$regex] = $route->compile();
            if (preg_match($regex, $uri)) {
                return $route;
            }
        }

        return null;
    }

    private function extractParams(Route $route, string $uri, array $paramNames): array
    {
        [$regex] = $route->compile();
        preg_match($regex, $uri, $matches);
        array_shift($matches);

        return array_combine($paramNames, $matches) ?: [];
    }

    /**
     * اجرای زنجیره‌ی Middleware ها و در نهایت خود Handler.
     * از انتها به ابتدا پیچیده می‌شود تا هر Middleware بتواند $next را
     * صدا بزند و کنترل را به Middleware بعدی (یا در نهایت Handler) بدهد.
     */
    private function runPipeline(Route $route, Request $request): Response
    {
        $pipeline = array_reduce(
            array_reverse($route->middlewareList()),
            function (\Closure $next, string $middlewareClass) {
                return function (Request $request) use ($next, $middlewareClass) {
                    /** @var MiddlewareInterface $middleware */
                    $middleware = $this->container->make($middlewareClass);
                    return $middleware->handle($request, $next);
                };
            },
            fn (Request $request) => $this->invokeHandler($route, $request)
        );

        return $pipeline($request);
    }

    private function invokeHandler(Route $route, Request $request): Response
    {
        $handler = $route->handler();

        if ($handler instanceof \Closure) {
            return $handler($request);
        }

        [$controllerClass, $methodName] = $handler;
        $controller = $this->container->make($controllerClass);

        if (!method_exists($controller, $methodName)) {
            throw new RuntimeException("متد «{$methodName}» در کنترلر «{$controllerClass}» پیدا نشد.");
        }

        $result = $controller->{$methodName}($request);

        if (!$result instanceof Response) {
            throw new RuntimeException(
                "متد «{$controllerClass}::{$methodName}» باید یک شیء Response برگرداند."
            );
        }

        return $result;
    }
}
