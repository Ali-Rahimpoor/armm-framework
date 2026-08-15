<?php

declare(strict_types=1);

namespace App\Controllers;

use ARMM\Http\JsonResponse;
use ARMM\Http\Request;
use ARMM\Storage\FileStorage;
use ARMM\Storage\FileValidator;

/**
 * نمونه‌ی کامل یک Endpoint آپلود تصویر با ARMM.
 *
 * نکته: تصویر همیشه جدا از فیلدهای دیگر (مثل «title») ارسال می‌شود،
 * یعنی درخواست باید multipart/form-data باشد، نه application/json.
 * روی کلاینت یعنی چیزی شبیه:
 *
 *   const form = new FormData();
 *   form.append('image', fileInput.files[0]);
 *   form.append('title', 'محصول جدید');
 *   fetch('/products', { method: 'POST', body: form });
 */
final class ImageUploadController
{
    public function __construct(
        private FileValidator $validator,
        private FileStorage $storage
    ) {
    }

    public function upload(Request $request): JsonResponse
    {
        $image = $request->uploadedFile('image');

        // اگر نامعتبر باشد (فرمت غیرمجاز، حجم زیاد، خطای آپلود، ...)
        // این‌جا یک HttpException::validation پرتاب می‌شود و Application
        // خودش آن را به پاسخ ۴۲۲ استاندارد تبدیل می‌کند؛ نیازی به
        // try/catch دستی در این‌جا نیست.
        $this->validator->validate($image);

        // thumbnailSize: 200 یعنی علاوه بر تصویر اصلی، یک نسخه‌ی
        // ۲۰۰×۲۰۰ هم به‌صورت خودکار ساخته می‌شود. اگر thumbnail لازم
        // نداری، این پارامتر را حذف کن (پیش‌فرض null است).
        $result = $this->storage->store($image, subdirectory: 'products', thumbnailSize: 200);

        return JsonResponse::created([
            'path' => $result['path'],
            'url' => $result['url'],
            'thumbnail_url' => $result['thumbnail_url'],
        ], 'تصویر با موفقیت آپلود شد');
    }
}
