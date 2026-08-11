<?php

declare(strict_types=1);

namespace ARMM\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * یک Dependency Injection Container ساده.
 *
 * چرا لازم است؟
 * بدون Container، برای ساختن یک Controller که خودش به یک Service وابسته
 * است و آن Service به دو Repository، باید دستی زنجیره‌ی کامل را بسازی:
 *
 *   new ProjectController(new ProjectService(new ProjectRepository(), new FileUploadService()))
 *
 * با Container، فقط می‌گویی "یک ProjectController بهم بده"، و Container با
 * نگاه‌کردن به Constructor هر کلاس (از طریق Reflection)، خودش می‌فهمد هر
 * پارامتر به چه کلاسی نیاز دارد و آن را هم می‌سازد (Auto-wiring).
 */
final class Container
{
    /** @var array<string, Closure> کلاس‌هایی که باید با یک Closure سفارشی ساخته شوند */
    private array $bindings = [];

    /** @var array<string, object> نمونه‌های تک‌قلو (Singleton) که فقط یک‌بار ساخته می‌شوند */
    private array $singletons = [];

    /** @var array<string, true> کلاس‌هایی که باید همیشه Singleton رفتار کنند */
    private array $singletonFlags = [];

    /**
     * تعریف صریح یک روش ساخت سفارشی برای یک کلاس/اینترفیس.
     * مفید است وقتی Auto-wiring کافی نیست، مثلاً وقتی یک Interface
     * باید به یک پیاده‌سازی خاص (مثلاً ZarinpalGateway) نگاشت شود.
     */
    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * مثل bind، ولی نتیجه فقط یک‌بار ساخته می‌شود و برای همه‌ی
     * درخواست‌های بعدی همان نمونه برگردانده می‌شود.
     */
    public function singleton(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
        $this->singletonFlags[$abstract] = true;
    }

    /**
     * یک نمونه‌ی از پیش ساخته‌شده را مستقیماً به عنوان singleton ثبت می‌کند
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->singletons[$abstract] = $instance;
        $this->singletonFlags[$abstract] = true;
    }

    /**
     * آیا برای این abstract یک binding، singleton، یا instance صریح
     * از قبل ثبت شده است؟ (بدون تلاش برای auto-resolve کردن آن)
     *
     * مفید برای جاهایی که فریم‌ورک می‌خواهد یک پیش‌فرض هوشمند تنظیم کند
     * (مثلاً CorsMiddleware) اما فقط در صورتی که کاربر خودش قبلاً همان
     * abstract را صریحاً override نکرده باشد.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->singletons[$abstract]);
    }

    /**
     * ساختن (یا بازیابی) یک نمونه از کلاس داده‌شده.
     */
    public function make(string $abstract): object
    {
        if (isset($this->singletons[$abstract])) {
            return $this->singletons[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $object = ($this->bindings[$abstract])($this);

            if (isset($this->singletonFlags[$abstract])) {
                $this->singletons[$abstract] = $object;
            }

            return $object;
        }

        $object = $this->autoResolve($abstract);

        if (isset($this->singletonFlags[$abstract])) {
            $this->singletons[$abstract] = $object;
        }

        return $object;
    }

    /**
     * ساخت خودکار یک کلاس با تحلیل Constructor آن از طریق Reflection.
     * برای هر پارامتر Constructor که خودش یک کلاس/اینترفیس است، دوباره
     * make() را صدا می‌زند (بازگشتی)، تا کل زنجیره‌ی وابستگی ساخته شود.
     */
    private function autoResolve(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("کلاس «{$class}» پیدا نشد و نمی‌توان آن را ساخت.");
        }

        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("کلاس «{$class}» قابل ساخت نیست (اینترفیس یا abstract است و binding ندارد).");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = array_map(
            fn (ReflectionParameter $param) => $this->resolveParameter($param, $class),
            $constructor->getParameters()
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    private function resolveParameter(ReflectionParameter $param, string $forClass): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
            return null;
        }

        throw new RuntimeException(
            "پارامتر «{$param->getName()}» در کلاس «{$forClass}» یک نوع ساده (built-in) دارد "
            . "و مقدار پیش‌فرض ندارد؛ Container نمی‌تواند آن را به‌صورت خودکار مقداردهی کند. "
            . "برای این کلاس یک binding دستی تعریف کنید."
        );
    }
}
