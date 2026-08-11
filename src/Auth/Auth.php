<?php

declare(strict_types=1);

namespace ARMM\Auth;

/**
 * احراز هویت مبتنی بر Session، برای APIهایی که یک فرانت (مثل React) از
 * طریق کوکی به آن‌ها متصل می‌شود و نیازی به JWT/Token جداگانه نیست
 * (مناسب برای سناریوهایی با تعداد کاربر محدود و شناخته‌شده، مثل پنل
 * ادمین شخصی).
 *
 * نکات امنیتی رعایت‌شده:
 * - session_regenerate_id بعد از لاگین موفق، برای جلوگیری از Session Fixation
 * - کوکی با httponly (غیرقابل خواندن از جاوااسکریپت، در برابر XSS)
 * - کوکی با samesite قابل‌تنظیم (برای هماهنگی با فرانت روی دامنه/پورت دیگر)
 */
final class Auth
{
    private static bool $sessionStarted = false;

    public static function boot(array $cookieOptions = []): void
    {
        if (self::$sessionStarted || session_status() === PHP_SESSION_ACTIVE) {
            self::$sessionStarted = true;
            return;
        }

        $defaults = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None',
        ];

        session_set_cookie_params(array_merge($defaults, $cookieOptions));
        session_start();

        self::$sessionStarted = true;
    }

    /**
     * ثبت لاگین موفق یک کاربر در Session.
     * شناسه‌ی Session عمداً بازتولید می‌شود تا یک شناسه‌ی از قبل شناخته‌شده
     * (که ممکن است پیش از لاگین توسط مهاجم تنظیم شده باشد) دیگر معتبر نباشد.
     */
    public static function login(int $userId, array $extra = []): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        foreach ($extra as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }
}
