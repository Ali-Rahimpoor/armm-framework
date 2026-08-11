<?php

declare(strict_types=1);

namespace ARMM\Routing;

/**
 * متدهای کوتاه get/post/put/patch/delete که هم در Router و هم در
 * RouteGroupRecorder به یک شکل نیاز بودند. پیش از این، هر دو کلاس
 * این پنج متد را کلمه‌به‌کلمه تکرار کرده بودند و تنها یک خط از بدنه‌شان
 * فرق داشت؛ این یعنی افزودن یک متد HTTP جدید (مثلاً head) باید در دو
 * جای جدا انجام می‌شد و فراموش‌کردن یکی از آن دو، باگی ساکت می‌ساخت.
 *
 * با این Trait، هر دو کلاس فقط باید متد انتزاعی register() را با
 * منطق خاص خودشان پیاده کنند؛ خود پنج متد HTTP فقط یک‌بار این‌جا
 * نوشته شده‌اند.
 */
trait HttpVerbShortcuts
{
    public function get(string $pattern, array|\Closure $handler): Route
    {
        return $this->register('GET', $pattern, $handler);
    }

    public function post(string $pattern, array|\Closure $handler): Route
    {
        return $this->register('POST', $pattern, $handler);
    }

    public function put(string $pattern, array|\Closure $handler): Route
    {
        return $this->register('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, array|\Closure $handler): Route
    {
        return $this->register('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, array|\Closure $handler): Route
    {
        return $this->register('DELETE', $pattern, $handler);
    }

    abstract protected function register(string $method, string $pattern, array|\Closure $handler): Route;
}
