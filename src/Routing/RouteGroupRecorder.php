<?php

declare(strict_types=1);

namespace ARMM\Routing;

/**
 * یک شیء کمکی موقت که فقط داخل بستن (Closure) متد Router::group در
 * دسترس است. باعث می‌شود بتوان به‌جای تکرار middleware() روی هر Route
 * به‌طور جداگانه، یک بار برای کل گروه نوشت:
 *
 *   $router->group([AuthMiddleware::class], function (RouteGroupRecorder $r) {
 *       $r->post('/projects', [ProjectController::class, 'store']);
 *       $r->put('/projects/{id}', [ProjectController::class, 'update']);
 *       $r->delete('/projects/{id}', [ProjectController::class, 'destroy']);
 *   });
 */
final class RouteGroupRecorder
{
    use HttpVerbShortcuts;

    public function __construct(
        private Router $router,
        private array $middleware
    ) {
    }

    protected function register(string $method, string $pattern, array|\Closure $handler): Route
    {
        return $this->router->registerWithMiddleware($method, $pattern, $handler, $this->middleware);
    }
}
