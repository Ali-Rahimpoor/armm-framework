<?php

declare(strict_types=1);

namespace ARMM\Routing;

/**
 * نمایانگر یک مسیر ثبت‌شده: متد HTTP، الگوی مسیر، Handler (کنترلر و متد)،
 * و لیست Middleware هایی که پیش از رسیدن به Handler باید اجرا شوند.
 *
 * برخلاف یک آرایه‌ی خام، این شیء امکان نوشتن زنجیره‌ای و خوانا را می‌دهد:
 *   $router->post('/projects', [ProjectController::class, 'store'])
 *          ->middleware(AuthMiddleware::class);
 */
final class Route
{
    private array $middleware = [];

    /** @var array{0: string, 1: string[]}|null نتیجه‌ی cache‌شده‌ی compile(), فقط یک‌بار محاسبه می‌شود */
    private ?array $compiled = null;

    public function __construct(
        private string $method,
        private string $pattern,
        private array|\Closure $handler
    ) {
    }

    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge(
            $this->middleware,
            is_array($middleware) ? $middleware : [$middleware]
        );

        return $this;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    public function handler(): array|\Closure
    {
        return $this->handler;
    }

    /** @return string[] */
    public function middlewareList(): array
    {
        return $this->middleware;
    }

    /**
     * تبدیل الگوی مسیر (مثل /projects/{id}) به یک Regex قابل تطبیق،
     * و استخراج نام پارامترها (مثل ["id"]).
     *
     * قبل از این، Router در یک dispatch واحد ممکن بود این متد را تا
     * چهار بار برای همان Route صدا بزند (در match، extractParams، و...)
     * و هر بار preg_replace_callback دوباره اجرا می‌شد. چون الگوی یک
     * Route بعد از ساختنش هرگز تغییر نمی‌کند، نتیجه یک‌بار محاسبه و
     * cache می‌شود.
     *
     * @return array{0: string, 1: string[]} [regex, paramNames]
     */
    public function compile(): array
    {
        if ($this->compiled !== null) {
            return $this->compiled;
        }

        $paramNames = [];

        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                return '([^/]+)';
            },
            $this->pattern
        );

        return $this->compiled = ['#^' . $regex . '$#u', $paramNames];
    }
}
