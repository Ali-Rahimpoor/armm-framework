<?php

declare(strict_types=1);

namespace ARMM\Http;

/**
 * نمایانگر یک پاسخ HTTP: کد وضعیت، هدرها، و بدنه.
 * به‌جای echo کردن مستقیم و صدا زدن پراکنده‌ی http_response_code،
 * یک شیء Response ساخته می‌شود و در انتهای چرخه‌ی درخواست، Application
 * آن را واقعاً به خروجی می‌فرستد (send). این جداسازی باعث می‌شود
 * Controller ها قابل تست باشند: می‌توان Response را بررسی کرد
 * بدون آنکه واقعاً چیزی echo شده باشد.
 */
class Response
{
    protected int $statusCode;
    protected array $headers;
    protected string $body;

    public function __construct(string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * پاسخ را واقعاً به خروجی می‌فرستد: هدرها، کد وضعیت، و بدنه.
     * این تنها جایی در کل فریم‌ورک است که echo/header مستقیم رخ می‌دهد.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->body;
    }
}
