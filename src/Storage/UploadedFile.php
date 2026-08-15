<?php

declare(strict_types=1);

namespace ARMM\Storage;

/**
 * یک شیء ساده دور یک آیتم خام $_FILES می‌پیچد.
 *
 * چرا این کلاس لازم است؟
 * Request::file() یک آرایه‌ی خام PHP برمی‌گرداند
 * (['name' => ..., 'type' => ..., 'tmp_name' => ..., 'error' => ..., 'size' => ...]).
 * کار مستقیم با این آرایه چند مشکل دارد:
 *   - کلید «type» مقداری است که خودِ کلاینت فرستاده (Content-Type هدر
 *     فایل)، نه نوع واقعی محتوا؛ به‌راحتی قابل جعل است (کافی‌ست کلاینت
 *     یک فایل .php را با هدر image/png بفرستد).
 *   - کد بررسی خطا (UPLOAD_ERR_*) در همه‌جا باید تکرار شود.
 * این کلاس این جزئیات را یک‌جا جمع می‌کند و یک API تمیز به بقیه‌ی
 * فریم‌ورک (FileValidator، FileStorage) می‌دهد.
 */
final class UploadedFile
{
    private ?string $realMimeType = null;
    private bool $realMimeTypeResolved = false;

    public function __construct(
        private string $originalName,
        private string $clientMimeType,
        private string $tmpPath,
        private int $error,
        private int $size
    ) {
    }

    /**
     * ساخت از روی یک ورودی خام $_FILES (همان چیزی که Request::file()
     * برمی‌گرداند). اگر کلید موردنظر اصلاً وجود نداشته باشد، null است.
     */
    public static function fromArray(?array $raw): ?self
    {
        if ($raw === null || !isset($raw['tmp_name'])) {
            return null;
        }

        return new self(
            originalName: (string) ($raw['name'] ?? ''),
            clientMimeType: (string) ($raw['type'] ?? ''),
            tmpPath: (string) $raw['tmp_name'],
            error: (int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE),
            size: (int) ($raw['size'] ?? 0)
        );
    }

    /**
     * آیا این فایل واقعاً از طریق HTTP POST آپلود شده (و نه مثلاً یک
     * مسیر دلخواه روی دیسک که کاربر بدخواه به تمام‌عمد به tmp_name داده)،
     * و آیا PHP هنگام آپلود آن هیچ خطایی ثبت نکرده؟
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && $this->tmpPath !== ''
            && is_uploaded_file($this->tmpPath);
    }

    public function errorCode(): int
    {
        return $this->error;
    }

    /**
     * پیام فارسیِ خطای آپلود، برای نمایش مستقیم در پاسخ به کاربر.
     */
    public function errorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK => '',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل از حد مجاز سرور بیشتر است',
            UPLOAD_ERR_PARTIAL => 'فایل به‌طور کامل آپلود نشد',
            UPLOAD_ERR_NO_FILE => 'هیچ فایلی ارسال نشده است',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه‌ی موقت سرور برای آپلود در دسترس نیست',
            UPLOAD_ERR_CANT_WRITE => 'نوشتن فایل روی دیسک سرور ممکن نشد',
            UPLOAD_ERR_EXTENSION => 'یک افزونه‌ی PHP آپلود را متوقف کرد',
            default => 'خطای ناشناخته در آپلود فایل',
        };
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    /**
     * پسوند فایل بر اساس نام اصلی (فقط برای نمایش/نام‌گذاری مفید است،
     * هرگز نباید مبنای تصمیم امنیتی باشد — از mimeType() برای آن استفاده کن).
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function size(): int
    {
        return $this->size;
    }

    public function tmpPath(): string
    {
        return $this->tmpPath;
    }

    /**
     * نوع محتوایی که خودِ کلاینت هنگام آپلود اعلام کرده. قابل جعل است؛
     * فقط برای لاگ یا نمایش استفاده شود، هرگز برای تصمیم امنیتی.
     */
    public function clientMimeType(): string
    {
        return $this->clientMimeType;
    }

    /**
     * نوع محتوای واقعیِ فایل، با خواندن چند بایت اول خودِ فایل روی دیسک
     * (نه هدری که کلاینت فرستاده). این تنها راه قابل‌اعتماد برای تشخیص
     * این‌که فایل واقعاً یک تصویر است، مستقل از پسوند یا هدر جعلی.
     * نتیجه cache می‌شود چون finfo برای هر فایل فقط یک‌بار لازم است باز شود.
     */
    public function mimeType(): ?string
    {
        if ($this->realMimeTypeResolved) {
            return $this->realMimeType;
        }

        $this->realMimeTypeResolved = true;

        if ($this->tmpPath === '' || !is_readable($this->tmpPath)) {
            return $this->realMimeType = null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return $this->realMimeType = null;
        }

        $detected = finfo_file($finfo, $this->tmpPath);
        finfo_close($finfo);

        return $this->realMimeType = ($detected !== false ? $detected : null);
    }
}
