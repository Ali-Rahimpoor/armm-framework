<?php

declare(strict_types=1);

namespace ARMM\Http;

/**
 * یک شیء Request، دور تمام اطلاعات ورودی یک درخواست HTTP می‌پیچد:
 * پارامترهای Query String، بدنه‌ی درخواست (چه فرم سنتی چه JSON خام)،
 * هدرها، متد HTTP، و مسیر URL.
 *
 * چرا این کلاس لازم است؟
 * در یک REST API، بدنه‌ی درخواست معمولاً JSON خام است، نه یک فرم سنتی؛
 * یعنی $_POST برای درخواست‌های JSON همیشه خالی می‌ماند. این کلاس تشخیص
 * می‌دهد بدنه چگونه ارسال شده و به‌صورت یکدست در اختیار برنامه قرار می‌دهد.
 */
final class Request
{
    private array $query;
    private array $body;
    private array $server;
    private array $headers;
    private array $files;
    private array $routeParams = [];

    private function __construct(
        array $query,
        array $body,
        array $server,
        array $headers,
        array $files
    ) {
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->headers = $headers;
        $this->files = $files;
    }

    /**
     * ساخت یک Request از روی وضعیت واقعی PHP (سوپرگلوبال‌ها)؛
     * این نقطه‌ی ورودی است که Application هنگام شروع هر درخواست صدا می‌زند.
     */
    public static function capture(): self
    {
        $server = $_SERVER;
        $headers = self::parseHeaders($server);
        $body = self::parseBody($server, $headers);

        return new self($_GET, $body, $server, $headers, $_FILES);
    }

    /**
     * ساخت دستی یک Request (بسیار مفید برای تست‌نویسی، بدون نیاز به
     * سوپرگلوبال‌های واقعی PHP).
     */
    public static function create(
        string $method,
        string $uri,
        array $query = [],
        array $body = [],
        array $headers = []
    ): self {
        $server = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
        ];

        // هدرهای دستی هم باید با همان قاعده‌ی parseHeaders() (کلید همیشه
        // بزرگ‌حروف) نرمال شوند، وگرنه Request::create(headers: ['origin' => ...])
        // در header() با strtoupper($name) پیدا نمی‌شود.
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtoupper($name)] = $value;
        }

        return new self($query, $body, $server, $normalizedHeaders, []);
    }

    private static function parseHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                // همیشه با حروف بزرگ ذخیره می‌شود تا با strtoupper() در
                // header() هماهنگ باشد، مستقل از این‌که مقدار HTTP_* در
                // $_SERVER چطور بوده (که همیشه بزرگ است) یا Request::create()
                // دستی با حروف کوچک صدا زده شده باشد.
                $headers[strtoupper($name)] = $value;
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = $server['CONTENT_TYPE'];
        }

        return $headers;
    }

    private static function parseBody(array $server, array $headers): array
    {
        $method = $server['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET' || $method === 'HEAD') {
            return [];
        }

        $contentType = $headers['CONTENT-TYPE'] ?? '';

        // اگر بدنه از نوع JSON است، از php://input می‌خوانیم
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '', true);

            return is_array($decoded) ? $decoded : [];
        }

        // در غیر این صورت، فرم سنتی است؛ از $_POST استفاده می‌کنیم
        return $_POST;
    }

    /**
     * یک مقدار را از بدنه‌ی درخواست (JSON یا فرم) می‌خواند
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * یک مقدار را از Query String (?key=value) می‌خواند
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * تمام بدنه‌ی درخواست را به‌صورت آرایه برمی‌گرداند
     */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * فقط کلیدهای مشخص‌شده را از بدنه استخراج می‌کند
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->body, array_flip($keys));
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtoupper($name)] ?? $default;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * پارامترهای استخراج‌شده از خود مسیر Route (مثلاً {id} در /projects/{id})
     * توسط Router پر می‌شود، نه توسط خود Request.
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('AUTHORIZATION', '');
        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
