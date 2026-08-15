<?php

declare(strict_types=1);

namespace ARMM\Storage;

use RuntimeException;

/**
 * تغییر سایز و تولید thumbnail برای یک تصویر روی دیسک، با استفاده از
 * افزونه‌ی GD (بخشی از خودِ PHP، نیازی به composer package جدید ندارد).
 *
 * چرا جدا از FileStorage؟
 * «ذخیره‌سازی فایل» (تعیین مسیر، نام یکتا، move) و «پردازش تصویر»
 * (تغییر ابعاد، فشرده‌سازی) دو مسئولیت متفاوت‌اند. جدا نگه‌داشتنشان
 * یعنی اگر فردا کسی بخواهد فقط منطق پردازش را جای دیگری (مثلاً یک
 * صف پس‌زمینه) استفاده کند، مجبور به وابستگی به کل FileStorage نیست.
 *
 * این کلاس همیشه نسبت تصویر (aspect ratio) را حفظ می‌کند؛ عرض/ارتفاع
 * دریافتی به‌عنوان «حداکثر ابعاد» در نظر گرفته می‌شود، نه ابعاد دقیق.
 */
final class ImageProcessor
{
    public function __construct()
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException(
                'افزونه‌ی GD در PHP فعال نیست؛ برای تغییر سایز/تولید thumbnail لازم است.'
            );
        }
    }

    /**
     * تصویر مبدأ را می‌خواند، در محدوده‌ی $maxWidth × $maxHeight
     * (با حفظ نسبت ابعاد) کوچک می‌کند، و در $destinationPath ذخیره می‌کند.
     * اگر تصویر مبدأ از قبل کوچک‌تر یا هم‌اندازه‌ی این محدوده باشد،
     * بدون تغییر (فقط با همان فرمت مقصد) ذخیره می‌شود، نه بزرگ‌نمایی.
     *
     * @throws RuntimeException اگر فایل مبدأ قابل خواندن یا رمزگشایی نباشد
     */
    public function resize(string $sourcePath, string $destinationPath, int $maxWidth, int $maxHeight): void
    {
        $source = $this->loadImage($sourcePath);
        [$sourceWidth, $sourceHeight] = [imagesx($source->resource), imagesy($source->resource)];

        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1.0);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        // حفظ شفافیت برای PNG و WebP (وگرنه پس‌زمینه‌ی شفاف مشکی می‌شود)
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled(
            $resized,
            $source->resource,
            0, 0, 0, 0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $this->saveImage($resized, $destinationPath, $source->mimeType);

        imagedestroy($source->resource);
        imagedestroy($resized);
    }

    /**
     * میان‌بر برای تولید یک نسخه‌ی thumbnail (پیش‌فرض ۲۰۰×۲۰۰) از تصویر.
     */
    public function thumbnail(string $sourcePath, string $destinationPath, int $size = 200): void
    {
        $this->resize($sourcePath, $destinationPath, $size, $size);
    }

    /**
     * @return object{resource: \GdImage, mimeType: string}
     */
    private function loadImage(string $path): object
    {
        $mimeType = (string) mime_content_type($path);

        $resource = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            'image/gif' => imagecreatefromgif($path),
            default => throw new RuntimeException("فرمت تصویر «{$mimeType}» برای پردازش پشتیبانی نمی‌شود."),
        };

        if ($resource === false) {
            throw new RuntimeException('خواندن تصویر برای پردازش ممکن نشد؛ فایل ممکن است خراب باشد.');
        }

        return (object) ['resource' => $resource, 'mimeType' => $mimeType];
    }

    private function saveImage(\GdImage $image, string $destinationPath, string $mimeType): void
    {
        $saved = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $destinationPath, 85),
            'image/png' => imagepng($image, $destinationPath, 6),
            'image/webp' => imagewebp($image, $destinationPath, 85),
            'image/gif' => imagegif($image, $destinationPath),
            default => false,
        };

        if ($saved === false) {
            throw new RuntimeException('ذخیره‌ی تصویر پردازش‌شده روی دیسک ممکن نشد.');
        }
    }
}
