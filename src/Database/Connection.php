<?php

declare(strict_types=1);

namespace ARMM\Database;

use ARMM\Config\Config;
use PDO;
use PDOException;

/**
 * یک factory ساده برای ساختن اتصال PDO.
 * تنظیمات از طریق Config خوانده می‌شوند (config/database.php)، نه هاردکد.
 *
 * ATTR_EMULATE_PREPARES عمداً false است: باعث می‌شود Prepared Statement
 * های واقعیِ خود درایور MySQL استفاده شوند، که در برابر SQL Injection
 * مقاوم‌تر است. نکته‌ی یادگرفته‌شده از پروژه‌ی قبلی: وقتی این تنظیم
 * false است، هر Named Placeholder (مثل :id) باید دقیقاً یک‌بار در هر
 * کوئری ظاهر شود؛ در غیر این صورت خطای «Invalid parameter number» رخ
 * می‌دهد.
 *
 * توجه: این کلاس دیگر خودش singleton نگه نمی‌دارد (پیش از این یک
 * static $instance موازی با singleton خودِ Container داشت که باعث
 * می‌شد دو منبع حقیقت جدا برای «یک اتصال PDO» وجود داشته باشد و
 * ریست‌کردن یکی، آن دیگری را بی‌خبر می‌گذاشت). singleton بودن اتصال،
 * تنها مسئولیت Container است — این‌جا فقط new PDO(...) ساخته می‌شود؛
 * ببین Application::boot() که آن را با $container->singleton(...) ثبت می‌کند.
 */
final class Connection
{
    public static function make(Config $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config->get('database', 'host'),
            $config->get('database', 'dbname'),
            $config->getOr('database', 'charset', 'utf8mb4')
        );

        try {
            return new PDO(
                $dsn,
                $config->get('database', 'username'),
                $config->get('database', 'password'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new PDOException('اتصال به دیتابیس ناموفق بود: ' . $e->getMessage(), (int) $e->getCode());
        }
    }
}
