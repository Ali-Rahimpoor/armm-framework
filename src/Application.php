<?php

declare(strict_types=1);

namespace ARMM;

use ARMM\Auth\Auth;
use ARMM\Config\Config;
use ARMM\Container\Container;
use ARMM\Exceptions\HttpException;
use ARMM\Http\JsonResponse;
use ARMM\Http\Request;
use ARMM\Http\Response;
use ARMM\Logging\Logger;
use ARMM\Middleware\CorsMiddleware;
use ARMM\Routing\Router;
use Throwable;

/**
 * نقطه‌ی مرکزی فریم‌ورک: راه‌اندازی Session، Config، Container، Logger،
 * ثبت مسیرها، و اجرای کامل چرخه‌ی یک درخواست HTTP.
 *
 * یک پروژه‌ی مصرف‌کننده (مثلاً API سایت شخصی) معمولاً فقط این چند خط را
 * در public/index.php می‌نویسد:
 *
 *   require __DIR__ . '/../vendor/autoload.php';
 *   $app = new ARMM\Application(basePath: dirname(__DIR__));
 *   $app->loadRoutes(__DIR__ . '/../routes/api.php');
 *   $app->run();
 */
final class Application
{
    private Container $container;
    private Config $config;
    private Logger $logger;
    private Router $router;
    private bool $booted = false;

    public function __construct(private string $basePath)
    {
        $this->container = new Container();
        $this->config = new Config($this->basePath . '/config');
        $this->router = new Router($this->container);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * راه‌اندازی هسته: بارگذاری تنظیمات، ساخت Logger، شروع Session،
     * و ثبت این سرویس‌ها در Container تا سایر کلاس‌ها (Controller ها،
     * Middleware ها) بتوانند به‌صورت خودکار به آن‌ها دسترسی پیدا کنند.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->config->load();

        $logPath = $this->config->getOr('app', 'log_path', $this->basePath . '/storage/logs/error.log');
        $this->logger = new Logger($logPath);

        $cookieOptions = $this->config->getOr('app', 'session_cookie', []);
        Auth::boot($cookieOptions);

        date_default_timezone_set(
            $this->config->getOr('app', 'timezone', 'UTC')
        );

        $this->container->instance(Config::class, $this->config);
        $this->container->instance(Logger::class, $this->logger);
        $this->container->instance(Container::class, $this->container);

        $this->container->singleton(\PDO::class, function (Container $c) {
            return \ARMM\Database\Connection::make($c->make(Config::class));
        });

        // اگر فایل config/app.php کلید cors_allowed_origins را تعریف کرده
        // باشد، CorsMiddleware به‌صورت خودکار روی همان لیست wire می‌شود؛
        // دیگر لازم نیست کاربر خودش این binding را در public/index.php
        // دستی بنویسد و فراموشش کند. اگر پیش از این خودِ کاربر با
        // $app->container()->bind(CorsMiddleware::class, ...) این را
        // صریحاً override کرده باشد، همان تعریف او باقی می‌ماند و این‌جا
        // چیزی رویش نمی‌نویسیم.
        if (
            $this->config->getOr('app', 'cors_allowed_origins', null) !== null
            && !$this->container->has(CorsMiddleware::class)
        ) {
            $this->container->bind(CorsMiddleware::class, function (Container $c) {
                return new CorsMiddleware(
                    $c->make(Config::class)->getOr('app', 'cors_allowed_origins', [])
                );
            });
        }

        $this->booted = true;
    }

    /**
     * بارگذاری فایل تعریف مسیرها. آن فایل به یک متغیر $router دسترسی دارد.
     */
    public function loadRoutes(string $routesFile): void
    {
        $router = $this->router;
        require $routesFile;
    }

    /**
     * اجرای کامل چرخه‌ی درخواست: گرفتن Request واقعی، اجرای Router،
     * و ارسال Response. هر خطای مدیریت‌نشده در این‌جا گرفته و لاگ می‌شود
     * تا کاربر هرگز خطای خام PHP را نبیند.
     */
    public function run(): void
    {
        $this->boot();

        $request = Request::capture();
        $response = $this->handle($request);
        $response->send();
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return JsonResponse::error($e->getMessage(), $e->statusCode(), $e->errors());
        } catch (Throwable $e) {
            $this->logger->exception($e);
            return JsonResponse::serverError();
        }
    }
}
