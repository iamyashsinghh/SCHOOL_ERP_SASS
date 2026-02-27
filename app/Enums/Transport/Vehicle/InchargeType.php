<?php

namespace App\Enums\Transport\Vehicle;

use App\Concerns\HasEnum;

enum InchargeType: string
{
    use HasEnum;

    case SUPERVISOR = 'supervisor';
    case DRIVER = 'driver';
    case CONDUCTOR = 'conductor';
    case HELPER = 'helper';
    case OTHER = 'other';

    public static function translation(): string
    {
        return 'transport.vehicle.incharge.types.';
    }
}
