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

    public function isPollable(): bool
    {
        return match ($this) {
            self::SentToBroker, self::PendingRouting, self::PartiallyFilled => true,
            default => false,
        };
    }

    public static function fromBrokerStatus(string $brokerStatus): ?self
    {
        return match ($brokerStatus) {
            'PENDING_ROUTING' => self::PendingRouting,
            'PARTIALLY_FILLED' => self::PartiallyFilled,
            'FILLED' => self::Filled,
            'REJECTED' => self::Rejected,
            default => null,
        };
    }
}
