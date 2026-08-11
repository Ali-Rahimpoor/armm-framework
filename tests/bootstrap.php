<?php

declare(strict_types=1);

/**
 * این فایل فقط برای تست محلی داخل این محیط است، جایی که Composer در
 * دسترس نیست. در پروژه‌ی واقعی، به‌جای این فایل، از vendor/autoload.php
 * که خود Composer می‌سازد استفاده می‌شود.
 */
spl_autoload_register(function (string $class) {
    $prefix = 'ARMM\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
