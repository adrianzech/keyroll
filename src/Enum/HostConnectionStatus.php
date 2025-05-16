<?php

declare(strict_types=1);

namespace App\Enum;

enum HostConnectionStatus: string
{
    case SUCCESSFUL = 'successful';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
    case CHECKING = 'checking';

    /**
     * Gets the translation key for the status label.
     */
    public function getLabelKey(): string
    {
        return match ($this) {
            self::SUCCESSFUL => 'host.status.successful',
            self::FAILED => 'host.status.failed',
            self::UNKNOWN => 'host.status.unknown',
            self::CHECKING => 'host.status.checking',
        };
    }

    /**
     * Gets the CSS badge class for the status.
     */
    public function getBadgeClass(): string
    {
        return match ($this) {
            self::SUCCESSFUL => 'badge-success',
            self::FAILED => 'badge-error',
            self::CHECKING => 'badge-info',
            self::UNKNOWN => 'badge-warning',
        };
    }
}
