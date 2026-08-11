<?php

declare(strict_types=1);

namespace ARMM\Config;

use RuntimeException;

/**
 * بارگذاری و دسترسی متمرکز به فایل‌های پیکربندی (config/*.php).
 *
 * تفاوت مهم نسبت به نسخه‌ی اول: get() اگر کلید درخواستی وجود نداشته
 * باشد، به‌جای برگرداندن خاموشِ null، یک Exception واضح پرتاب می‌کند.
 * این تصمیم عمدی است: یک مقدار پیکربندی گم‌شده (مثلاً به‌خاطر یک غلط
 * تایپی در نام کلید) باید همان لحظه‌ی استفاده باعث خطای روشن شود، نه
 * این‌که ساعت‌ها بعد، در یک لایه‌ی کاملاً نامرتبط (مثل درون یک درخواست
 * cURL) به‌صورت یک خطای مبهم ظاهر شود.
 */
final class Config
{
    private array $items = [];
    private bool $loaded = false;

    public function __construct(private string $configPath)
    {
    }

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        foreach (glob(rtrim($this->configPath, '/') . '/*.php') ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $this->items[$name] = require $file;
        }

        $this->loaded = true;
    }

    public function get(string $file, string $key): mixed
    {
        if (!array_key_exists($file, $this->items)) {
            throw new RuntimeException("فایل پیکربندی «{$file}» بارگذاری نشده یا وجود ندارد.");
        }

        if (!array_key_exists($key, $this->items[$file])) {
            throw new RuntimeException("کلید «{$key}» در فایل پیکربندی «{$file}» تعریف نشده است.");
        }

        return $this->items[$file][$key];
    }

    /**
     * مثل get، ولی در صورت نبود کلید، Exception پرتاب نمی‌کند و
     * مقدار پیش‌فرض را برمی‌گرداند. برای تنظیمات واقعاً اختیاری استفاده شود.
     */
    public function getOr(string $file, string $key, mixed $default = null): mixed
    {
        return $this->items[$file][$key] ?? $default;
    }
}
