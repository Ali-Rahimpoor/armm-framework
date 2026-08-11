<?php

declare(strict_types=1);

namespace ARMM\Http;

/**
 * پیام‌های پیش‌فرض خطاهای HTTP، در یک‌جا نگه‌داری می‌شوند.
 *
 * پیش از این، هر پیام (مثل «موردی یافت نشد») هم در HttpException و هم
 * در JsonResponse به‌صورت جداگانه هاردکد شده بود. این یعنی دو منبع
 * حقیقت موازی برای یک مفهوم واحد داشتیم، و همین باعث شده بود که پیام
 * «forbidden» در این دو کلاس واقعاً با هم فرق کند (یکی «اجازه‌ی دسترسی
 * ندارید»، دیگری «شما اجازه‌ی دسترسی به این بخش را ندارید»)، بدون آن‌که
 * این ناهماهنگی عمدی باشد.
 *
 * از این به بعد، هم HttpException (که throw می‌شود) و هم JsonResponse
 * (که return می‌شود) پیش‌فرض‌هایشان را از همین یک کلاس می‌خوانند.
 */
final class ErrorMessages
{
    public const NOT_FOUND = 'موردی یافت نشد';
    public const BAD_REQUEST = 'درخواست نامعتبر است';
    public const UNAUTHORIZED = 'ابتدا وارد حساب کاربری خود شوید';
    public const FORBIDDEN = 'شما اجازه‌ی دسترسی به این بخش را ندارید';
    public const VALIDATION_FAILED = 'اطلاعات ارسالی معتبر نیست';
    public const SERVER_ERROR = 'خطایی در سرور رخ داده است';
    public const CREATED = 'با موفقیت ایجاد شد';

    private function __construct()
    {
        // این کلاس فقط نگه‌دارنده‌ی ثابت‌هاست و نباید نمونه‌سازی شود.
    }
}
