<?php

declare(strict_types=1);

namespace ARMM\Storage;

use ARMM\Config\Config;
use ARMM\Exceptions\HttpException;

/**
 * اعتبارسنجی یک UploadedFile پیش از ذخیره‌سازی.
 *
 * قوانین (فرمت‌های مجاز، حداکثر حجم) از config/storage.php خوانده
 * می‌شوند تا سازنده‌ی هر API بتواند بدون دست‌زدن به کد فریم‌ورک،
 * این مقادیر را برای پروژه‌ی خودش تنظیم کند. اگر فایل config یا کلید
 * موردنظر تعریف نشده باشد، از یک پیش‌فرض عمومیِ معقول استفاده می‌شود
 * (Config::getOr به‌جای Config::get، دقیقاً برای همین منظور).
 *
 * تشخیص نوع فایل همیشه بر اساس محتوای واقعی (finfo روی خودِ فایل روی
 * دیسک) انجام می‌شود، نه بر اساس پسوند نام فایل یا هدر Content-Type
 * کلاینت — چون هر دوی این‌ها به‌راحتی توسط کلاینت قابل جعل‌اند.
 */
final class FileValidator
{
    /** پیش‌فرض وقتی config/storage.php کلید allowed_mime_types را تعریف نکرده باشد */
    private const DEFAULT_ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** پیش‌فرض حداکثر حجم مجاز، بر حسب بایت (اینجا: ۵ مگابایت) */
    private const DEFAULT_MAX_SIZE_BYTES = 5 * 1024 * 1024;

    public function __construct(private Config $config)
    {
    }

    /**
     * اگر فایل نامعتبر باشد HttpException::validation پرتاب می‌کند
     * (که Application خودش به پاسخ ۴۲۲ استاندارد تبدیلش می‌کند)؛
     * در غیر این صورت بی‌صدا برمی‌گردد.
     */
    public function validate(?UploadedFile $file): void
    {
        if ($file === null || $file->errorCode() === UPLOAD_ERR_NO_FILE) {
            throw HttpException::validation(['image' => 'هیچ تصویری ارسال نشده است']);
        }

        if (!$file->isValid()) {
            throw HttpException::validation(['image' => $file->errorMessage()]);
        }

        $maxSize = (int) $this->config->getOr('storage', 'max_size_bytes', self::DEFAULT_MAX_SIZE_BYTES);
        if ($file->size() > $maxSize) {
            $maxMb = round($maxSize / 1024 / 1024, 1);
            throw HttpException::validation([
                'image' => "حجم تصویر نباید بیشتر از {$maxMb} مگابایت باشد",
            ]);
        }

        $allowedMimeTypes = $this->config->getOr('storage', 'allowed_mime_types', self::DEFAULT_ALLOWED_MIME_TYPES);
        $realMimeType = $file->mimeType();

        if ($realMimeType === null || !in_array($realMimeType, $allowedMimeTypes, true)) {
            throw HttpException::validation([
                'image' => 'فرمت تصویر مجاز نیست. فرمت‌های مجاز: ' . implode(', ', $allowedMimeTypes),
            ]);
        }
    }
}
