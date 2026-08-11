<?php

declare(strict_types=1);

namespace ARMM\Exceptions;

use ARMM\Http\ErrorMessages;
use RuntimeException;

/**
 * یک Exception با کد وضعیت HTTP مشخص. وقتی یک Controller یا Service
 * این را پرتاب می‌کند (به‌جای Exception عمومی)، Application می‌داند
 * دقیقاً باید چه کد وضعیتی به کاربر برگرداند، به‌جای این‌که همه‌چیز را
 * به یک 500 عمومی تبدیل کند.
 *
 * مثال استفاده در یک Service:
 *   throw HttpException::notFound('پروژه‌ای با این شناسه پیدا نشد');
 */
class HttpException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $statusCode,
        private array $errors = []
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public static function notFound(string $message = ErrorMessages::NOT_FOUND): self
    {
        return new self($message, 404);
    }

    public static function badRequest(string $message = ErrorMessages::BAD_REQUEST): self
    {
        return new self($message, 400);
    }

    public static function unauthorized(string $message = ErrorMessages::UNAUTHORIZED): self
    {
        return new self($message, 401);
    }

    public static function forbidden(string $message = ErrorMessages::FORBIDDEN): self
    {
        return new self($message, 403);
    }

    public static function validation(array $errors, string $message = ErrorMessages::VALIDATION_FAILED): self
    {
        return new self($message, 422, $errors);
    }
}
