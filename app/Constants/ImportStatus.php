<?php

namespace App\Constants;

final class ImportStatus
{
    public const PENDING     = 'pending';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED   = 'completed';
    public const FAILED      = 'failed';

    /**
     * Get all available statuses
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::IN_PROGRESS,
            self::COMPLETED,
            self::FAILED,
        ];
    }
}
