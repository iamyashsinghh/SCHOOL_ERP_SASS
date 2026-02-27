<?php

namespace App\Enums\Helpdesk\Ticket;

use App\Concerns\HasEnum;
use App\Contracts\HasColor;

enum Status: string implements HasColor
{
    use HasEnum;

    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public static function translation(): string
    {
        return 'helpdesk.ticket.statuses.';
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'info',
            self::IN_PROGRESS => 'secondary',
            self::ON_HOLD => 'warning',
            self::RESOLVED => 'primary',
            self::CLOSED => 'success',
        };
    }
}
