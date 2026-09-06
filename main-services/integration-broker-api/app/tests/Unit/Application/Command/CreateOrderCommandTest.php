<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command;

use App\Application\Command\CreateOrder\CreateOrderCommand;
use App\Application\Exception\InvalidParameter;
use App\Domain\Exception\InvalidValueException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreateOrderCommandTest extends TestCase
{
    public function testCreateFromRawValuesHappyPath(): void
    {
        $command = CreateOrderCommand::createFromRawValues(
            [
                'ticker' => 'aapl',
                'direction' => 'BUY',
                'quantity' => 10,
                'type' => 'MARKET',
            ],
            '550e8400-e29b-41d4-a716-446655440000',
            'client-1',
        );

        self::assertSame('AAPL', $command->ticker->toString());
        self::assertSame('BUY', $command->direction->value);
        self::assertSame(10, $command->quantity->toInt());
        self::assertSame('MARKET', $command->orderType->value);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $command->idempotencyKey->toString());
        self::assertSame('client-1', $command->clientId->toString());
    }

    public function testMissingIdempotencyKey(): void
    {
        $this->expectException(InvalidParameter::class);
        CreateOrderCommand::createFromRawValues(
            ['ticker' => 'AAPL', 'direction' => 'BUY', 'quantity' => 1, 'type' => 'MARKET'],
            '',
            'client-1',
        );
    }

    public function testMissingBodyField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CreateOrderCommand::createFromRawValues(
            ['ticker' => 'AAPL', 'direction' => 'BUY', 'quantity' => 1],
            '550e8400-e29b-41d4-a716-446655440000',
            'client-1',
        );
    }

    public function testInvalidQuantityPropagates(): void
    {
        $this->expectException(InvalidValueException::class);
        CreateOrderCommand::createFromRawValues(
            ['ticker' => 'AAPL', 'direction' => 'BUY', 'quantity' => 0, 'type' => 'MARKET'],
            '550e8400-e29b-41d4-a716-446655440000',
            'client-1',
        );
    }
}
