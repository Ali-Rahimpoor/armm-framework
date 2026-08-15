<?php

declare(strict_types=1);

namespace ARMM\Storage;

use ARMM\Config\Config;
use RuntimeException;

/**
 * ذخیره‌سازی یک UploadedFile روی دیسک، با نام یکتا و مسیر قابل‌تنظیم.
 *
 * مسیر ذخیره‌سازی از config/storage.php خوانده می‌شود؛ اگر تعریف نشده
 * باشد، پیش‌فرض «public/uploads» (نسبت به basePath پروژه) است — یعنی
 * سازنده‌ی API از همان روز اول، بدون هیچ تنظیمی، یک آدرس URL مستقیم و
 * قابل‌دسترس برای هر تصویر آپلودشده دارد. با تغییر همان یک کلید config
 * می‌تواند مسیر را به هر جای دیگری (حتی خارج از public) منتقل کند.
 *
 * نام‌گذاری فایل همیشه با یک شناسه‌ی تصادفی (uniqid + random_bytes)
 * انجام می‌شود، نه نام اصلی فایل؛ این هم از برخورد نام جلوگیری می‌کند
 * و هم از حدس‌زدن مسیر فایل‌های دیگران توسط کاربر مخرب.
 */
final class FileStorage
{
    private const DEFAULT_UPLOAD_SUBPATH = 'public/uploads';

    public function __construct(
        private Config $config,
        private string $basePath,
        private ?ImageProcessor $imageProcessor = null
    ) {
    }

    /**
     * فایل آپلودشده را در $subdirectory (مثلاً «products») ذخیره می‌کند.
     * اگر $thumbnailSize داده شود، یک نسخه‌ی thumbnail هم در همان
     * مسیر با پیشوند «thumb_» ساخته می‌شود.
     *
     * @return array{path: string, url: string, thumbnail_path: ?string, thumbnail_url: ?string}
     *   مسیر فایل نسبت به ریشه‌ی پروژه، و URL نسبی قابل استفاده در پاسخ API
     */
    public function store(UploadedFile $file, string $subdirectory = '', ?int $thumbnailSize = null): array
    {
        $uploadRoot = $this->resolveUploadRoot();
        $targetDir = rtrim($uploadRoot . '/' . trim($subdirectory, '/'), '/');

        $this->ensureDirectoryExists($targetDir);

        $filename = $this->generateUniqueFilename($file);
        $destination = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file->tmpPath(), $destination)) {
            throw new RuntimeException('انتقال فایل آپلودشده به مسیر نهایی ممکن نشد.');
        }

        $result = [
            'path' => $this->toRelativePath($destination),
            'url' => $this->toPublicUrl($destination),
            'thumbnail_path' => null,
            'thumbnail_url' => null,
        ];

        if ($thumbnailSize !== null) {
            $thumbnailDestination = $targetDir . '/thumb_' . $filename;
            $this->imageProcessor()->thumbnail($destination, $thumbnailDestination, $thumbnailSize);

            $result['thumbnail_path'] = $this->toRelativePath($thumbnailDestination);
            $result['thumbnail_url'] = $this->toPublicUrl($thumbnailDestination);
        }

        return $result;
    }

    /**
     * یک فایل قبلاً ذخیره‌شده را حذف می‌کند (مثلاً هنگام حذف یک محصول).
     * مسیر باید همان «path» نسبی‌ای باشد که store() برگردانده است.
     */
    public function delete(string $relativePath): void
    {
        $fullPath = rtrim($this->basePath, '/') . '/' . ltrim($relativePath, '/');

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    private function resolveUploadRoot(): string
    {
        $configuredPath = $this->config->getOr('storage', 'upload_path', null);

        $relativeOrAbsolute = $configuredPath ?? self::DEFAULT_UPLOAD_SUBPATH;

        // اگر سازنده‌ی API یک مسیر مطلق در config داده باشد (شروع با /)،
        // همان استفاده می‌شود؛ در غیر این صورت نسبت به basePath پروژه محاسبه می‌شود.
        if (str_starts_with($relativeOrAbsolute, '/')) {
            return rtrim($relativeOrAbsolute, '/');
        }

        return rtrim($this->basePath, '/') . '/' . trim($relativeOrAbsolute, '/');
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("ساخت پوشه‌ی «{$directory}» برای ذخیره‌سازی ممکن نشد.");
        }
    }

    private function generateUniqueFilename(UploadedFile $file): string
    {
        $fromMime = $this->extensionFromMimeType($file->mimeType());
        $fromName = $file->extension();
        $extension = $fromMime ?? ($fromName !== '' ? $fromName : 'bin');

        return uniqid('img_', true) . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    }

    private function extensionFromMimeType(?string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };
    }

    /**
     * مسیر فایل نسبت به basePath پروژه (برای ذخیره در دیتابیس، مستقل
     * از این‌که سرور فعلی کجا نصب شده است).
     */
    private function toRelativePath(string $fullPath): string
    {
        $normalizedBase = rtrim($this->basePath, '/');

        if (str_starts_with($fullPath, $normalizedBase . '/')) {
            return substr($fullPath, strlen($normalizedBase) + 1);
        }

        return $fullPath;
    }

    /**
     * اگر فایل داخل پوشه‌ی public ذخیره شده باشد، یک URL نسبی قابل‌استفاده
     * مستقیم در مرورگر می‌سازد (مثلاً «/uploads/products/img_xxx.jpg»).
     * اگر خارج از public باشد (سازنده‌ی API مسیر را عوض کرده)، null
     * برمی‌گرداند چون آن فایل باید از طریق یک Route اختصاصی سرو شود، نه مستقیم.
     */
    private function toPublicUrl(string $fullPath): ?string
    {
        $publicRoot = rtrim($this->basePath, '/') . '/public';

        if (!str_starts_with($fullPath, $publicRoot . '/')) {
            return null;
        }

        $relative = ltrim(substr($fullPath, strlen($publicRoot)), '/');

        return $relative === '' ? null : '/' . $relative;
    }

    private function imageProcessor(): ImageProcessor
    {
        return $this->imageProcessor ??= new ImageProcessor();
    }
}
