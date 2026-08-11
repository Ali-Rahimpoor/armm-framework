<?php

declare(strict_types=1);

namespace App\Controllers;

use ARMM\Exceptions\HttpException;
use ARMM\Http\JsonResponse;
use ARMM\Http\Request;

/**
 * یک مثال ساده و واقعی از Controller در پروژه‌ای که از فریم‌ورک ARMM
 * استفاده می‌کند. توجه کن:
 *   - هیچ چک لاگین‌بودنی داخل این کلاس نیست؛ آن مسئولیت AuthMiddleware
 *     است که در routes/api.php روی مسیرهای نوشتنی اعمال می‌شود.
 *   - همیشه JsonResponse برگردانده می‌شود، نه HTML.
 *   - برای خطاهای قابل‌پیش‌بینی (مثل «پیدا نشد»)، HttpException پرتاب
 *     می‌شود؛ Application خودش آن را می‌گیرد و به JSON با کد وضعیت
 *     درست تبدیل می‌کند.
 */
final class ProjectController
{
    // در پروژه‌ی واقعی، این‌جا یک ProjectService از طریق Constructor
    // تزریق می‌شود (Container خودش می‌سازدش)، دقیقاً مثل پروژه‌ی مینی‌شاپ.

    public function index(Request $request): JsonResponse
    {
        // در پروژه‌ی واقعی: $projects = $this->service->getAll();
        $projects = [
            ['id' => 1, 'title' => 'مینی‌شاپ'],
            ['id' => 2, 'title' => 'فریم‌ورک ARMM'],
        ];

        return JsonResponse::success($projects);
    }

    public function show(Request $request): JsonResponse
    {
        $id = (int) $request->routeParam('id');

        if ($id !== 1 && $id !== 2) {
            throw HttpException::notFound('پروژه‌ای با این شناسه پیدا نشد');
        }

        return JsonResponse::success(['id' => $id, 'title' => 'نمونه پروژه']);
    }

    public function store(Request $request): JsonResponse
    {
        $title = trim((string) $request->input('title', ''));

        if ($title === '') {
            throw HttpException::validation(['title' => 'عنوان پروژه الزامی است']);
        }

        // در پروژه‌ی واقعی: $project = $this->service->create($title);
        return JsonResponse::created(['id' => 3, 'title' => $title]);
    }
}
