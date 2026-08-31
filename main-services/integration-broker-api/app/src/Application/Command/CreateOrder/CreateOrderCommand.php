<?php

declare(strict_types=1);

namespace App\Application\Command\CreateOrder;

use App\Application\Exception\InvalidParameter;
use App\Domain\ValueObject\ClientId;
use App\Domain\ValueObject\IdempotencyKey;
use App\Domain\ValueObject\OrderDirection;
use App\Domain\ValueObject\OrderType;
use App\Domain\ValueObject\Quantity;
use App\Domain\ValueObject\Ticker;
use Webmozart\Assert\Assert;

final readonly class CreateOrderCommand
{
    public function __construct(
        public IdempotencyKey $idempotencyKey,
        public ClientId $clientId,
        public Ticker $ticker,
        public OrderDirection $direction,
        public OrderType $orderType,
        public Quantity $quantity,
    ) {
    }

    /** @param array<string, mixed> $requestData */
    public static function createFromRawValues(
        array $requestData,
        string $idempotencyKey,
        string $clientId,
    ): self {
        if ($idempotencyKey === '') {
            throw InvalidParameter::missing('Idempotency-Key');
        }

        Assert::keyExists($requestData, 'ticker');
        Assert::keyExists($requestData, 'direction');
        Assert::keyExists($requestData, 'quantity');
        Assert::keyExists($requestData, 'type');

        Assert::string($requestData['ticker']);
        Assert::string($requestData['direction']);
        Assert::integerish($requestData['quantity']);
        Assert::string($requestData['type']);

        return new self(
            idempotencyKey: IdempotencyKey::fromString($idempotencyKey),
            clientId: ClientId::fromString($clientId),
            ticker: Ticker::fromString($requestData['ticker']),
            direction: OrderDirection::fromString($requestData['direction']),
            orderType: OrderType::fromString($requestData['type']),
            quantity: Quantity::fromInt((int) $requestData['quantity']),
        );
    }
}
