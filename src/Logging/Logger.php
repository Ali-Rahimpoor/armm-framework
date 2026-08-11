<?php

declare(strict_types=1);

namespace ARMM\Logging;

/**
 * ثبت خطاها و رویدادها در فایل. همان طراحی ساده‌ای که در پروژه‌ی قبلی
 * (مینی‌شاپ) جواب داد و عمداً پیچیده‌تر نشده: یک فایل واحد، سطوح ساده،
 * و ثبت کامل جزئیات Exception (پیام، فایل، خط، Stack Trace).
 */
final class Logger
{
    public function __construct(private string $logFile)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function exception(\Throwable $e, array $context = []): void
    {
        $message = sprintf(
            '%s: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        $context['trace'] = $e->getTraceAsString();

        $this->write('EXCEPTION', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $date = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $line = "[{$date}] {$level}: {$message}{$contextString}" . PHP_EOL;

        file_put_contents($this->logFile, $line, FILE_APPEND);
    }
}
