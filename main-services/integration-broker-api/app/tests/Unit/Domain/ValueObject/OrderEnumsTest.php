<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\OrderDirection;
use App\Domain\ValueObject\OrderStatus;
use App\Domain\ValueObject\OrderType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderEnumsTest extends TestCase
{
    public function testDirectionFromString(): void
    {
        self::assertSame(OrderDirection::Buy, OrderDirection::fromString(' buy '));
        self::assertSame(OrderDirection::Sell, OrderDirection::fromString('SELL'));
    }

    public function testDirectionRejectsInvalid(): void
    {
        $this->expectException(InvalidValueException::class);
        OrderDirection::fromString('HOLD');
    }

    public function testTypeFromString(): void
    {
        self::assertSame(OrderType::Market, OrderType::fromString(' market '));
    }

    public function testTypeRejectsInvalid(): void
    {
        $this->expectException(InvalidValueException::class);
        OrderType::fromString('LIMIT');
    }

    #[DataProvider('terminalProvider')]
    public function testIsTerminal(OrderStatus $status, bool $expected): void
    {
        self::assertSame($expected, $status->isTerminal());
    }

    /** @return iterable<string, array{OrderStatus, bool}> */
    public static function terminalProvider(): iterable
    {
        yield 'filled' => [OrderStatus::Filled, true];
        yield 'failed' => [OrderStatus::Failed, true];
        yield 'rejected' => [OrderStatus::Rejected, true];
        yield 'new' => [OrderStatus::New, false];
        yield 'sent' => [OrderStatus::SentToBroker, false];
    }

    #[DataProvider('pollableProvider')]
    public function testIsPollable(OrderStatus $status, bool $expected): void
    {
        self::assertSame($expected, $status->isPollable());
    }

    /** @return iterable<string, array{OrderStatus, bool}> */
    public static function pollableProvider(): iterable
    {
        yield 'sent' => [OrderStatus::SentToBroker, true];
        yield 'pending' => [OrderStatus::PendingRouting, true];
        yield 'partial' => [OrderStatus::PartiallyFilled, true];
        yield 'new' => [OrderStatus::New, false];
        yield 'filled' => [OrderStatus::Filled, false];
    }

    #[DataProvider('brokerStatusProvider')]
    public function testFromBrokerStatus(string $input, ?OrderStatus $expected): void
    {
        self::assertSame($expected, OrderStatus::fromBrokerStatus($input));
    }

    /** @return iterable<string, array{string, ?OrderStatus}> */
    public static function brokerStatusProvider(): iterable
    {
        yield 'pending' => ['PENDING_ROUTING', OrderStatus::PendingRouting];
        yield 'partial' => ['PARTIALLY_FILLED', OrderStatus::PartiallyFilled];
        yield 'filled' => ['FILLED', OrderStatus::Filled];
        yield 'rejected' => ['REJECTED', OrderStatus::Rejected];
        yield 'unknown' => ['SOMETHING_ELSE', null];
    }
}
