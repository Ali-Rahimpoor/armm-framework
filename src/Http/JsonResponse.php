<?php

declare(strict_types=1);

namespace ARMM\Http;

/**
 * پاسخ استاندارد یک REST API. تمام Controller های پروژه‌ی API باید
 * از این کلاس (به‌جای Response خام) استفاده کنند تا خروجی همه‌ی
 * Endpoint ها یک ساختار یکدست داشته باشد:
 *   موفقیت:  {"success": true,  "data": ..., "message": ...}
 *   خطا:     {"success": false, "message": ..., "errors": {...}}
 */
final class JsonResponse extends Response
{
    public function __construct(array $payload, int $statusCode = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';

        parent::__construct(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $statusCode,
            $headers
        );
    }

    public static function success(mixed $data = null, string $message = '', int $statusCode = 200): self
    {
        return new self([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function created(mixed $data = null, string $message = ErrorMessages::CREATED): self
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(): self
    {
        return new self([], 204);
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): self
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        return new self($payload, $statusCode);
    }

    public static function unauthorized(string $message = ErrorMessages::UNAUTHORIZED): self
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = ErrorMessages::FORBIDDEN): self
    {
        return self::error($message, 403);
    }

    public static function notFound(string $message = ErrorMessages::NOT_FOUND): self
    {
        return self::error($message, 404);
    }

    public static function validationFailed(array $errors, string $message = ErrorMessages::VALIDATION_FAILED): self
    {
        return self::error($message, 422, $errors);
    }

    public static function serverError(string $message = ErrorMessages::SERVER_ERROR): self
    {
        return self::error($message, 500);
    }
}
