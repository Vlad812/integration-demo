<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum OrderStatus: string
{
    case New = 'NEW';
    case Retrying = 'RETRYING';
    case SentToBroker = 'SENT_TO_BROKER';
    case PendingRouting = 'PENDING_ROUTING';
    case PartiallyFilled = 'PARTIALLY_FILLED';
    case Filled = 'FILLED';
    case Failed = 'FAILED';
    case Rejected = 'REJECTED';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Filled, self::Failed, self::Rejected => true,
            default => false,
        };
    }
}
