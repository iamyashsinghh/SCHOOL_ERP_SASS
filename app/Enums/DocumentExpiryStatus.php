<?php

namespace App\Enums;

use App\Concerns\HasEnum;
use App\Contracts\HasColor;

enum DocumentExpiryStatus: string implements HasColor
{
    use HasEnum;

    case EXPIRED = 'expired';
    case EXPIRING_SOON = 'expiring_soon';
    case VALID = 'valid';

    public static function translation(): string
    {
        return 'contact.expiry_statuses.';
    }

    public function color(): string
    {
        return match ($this) {
            self::EXPIRED => 'danger',
            self::EXPIRING_SOON => 'warning',
            self::VALID => 'success',
        };
    }
}
