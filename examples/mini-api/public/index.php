<?php

declare(strict_types=1);

// در پروژه‌ی واقعی: require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../../../tests/bootstrap.php';
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use ARMM\Application;

$app = new Application(basePath: dirname(__DIR__));
$app->loadRoutes(__DIR__ . '/../routes/api.php');
$app->run();
