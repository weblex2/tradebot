<?php
namespace App\Services;

use App\Models\BotLog;
use Illuminate\Support\Facades\Log;

class BotLogger
{
    public static function info(string $channel, string $message, array $context = [], ?int $sourceId = null, ?int $analysisId = null, ?int $executionId = null): void
    {
        self::write('info', $channel, $message, $context, $sourceId, $analysisId, $executionId);
    }

    public static function warning(string $channel, string $message, array $context = [], ?int $sourceId = null, ?int $analysisId = null, ?int $executionId = null): void
    {
        self::write('warning', $channel, $message, $context, $sourceId, $analysisId, $executionId);
    }

    public static function error(string $channel, string $message, array $context = [], ?int $sourceId = null, ?int $analysisId = null, ?int $executionId = null): void
    {
        self::write('error', $channel, $message, $context, $sourceId, $analysisId, $executionId);
    }

    public static function debug(string $channel, string $message, array $context = [], ?int $sourceId = null, ?int $analysisId = null, ?int $executionId = null): void
    {
        if (!config('app.debug')) return;
        self::write('debug', $channel, $message, $context, $sourceId, $analysisId, $executionId);
    }

    private static function write(string $level, string $channel, string $message, array $context, ?int $sourceId, ?int $analysisId, ?int $executionId): void
    {
        try {
            BotLog::create([
                'channel'      => $channel,
                'level'        => $level,
                'message'      => mb_substr($message, 0, 255),
                'context'      => empty($context) ? null : $context,
                'source_id'    => $sourceId,
                'analysis_id'  => $analysisId,
                'execution_id' => $executionId,
            ]);
        } catch (\Throwable $e) {
            Log::error('BotLogger::write failed', ['msg' => $e->getMessage()]);
        }
    }
}
