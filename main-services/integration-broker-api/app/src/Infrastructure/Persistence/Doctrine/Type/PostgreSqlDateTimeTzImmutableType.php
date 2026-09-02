<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\PhpDateTimeMappingType;
use Doctrine\DBAL\Types\Type;
use Exception;

/**
 * PostgreSQL TIMESTAMPTZ is returned as text like "2026-09-02 22:11:14.594594+00".
 * Stock DateTimeTzImmutableType only accepts "Y-m-d H:i:sO" ("…14+0000").
 */
final class PostgreSqlDateTimeTzImmutableType extends Type implements PhpDateTimeMappingType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTimeTzTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($platform->getDateTimeTzFormatString());
        }

        throw InvalidType::new(
            $value,
            static::class,
            ['null', DateTimeInterface::class],
        );
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value)) {
            throw InvalidType::new(
                $value,
                static::class,
                ['null', 'string', DateTimeImmutable::class],
            );
        }

        $format = $platform->getDateTimeTzFormatString();
        $dateTime = DateTimeImmutable::createFromFormat($format, $value);
        if ($dateTime !== false) {
            return $dateTime;
        }

        $normalized = preg_replace('/([+-]\d{2})$/', '$1:00', $value) ?? $value;

        try {
            return new DateTimeImmutable($normalized);
        } catch (Exception $exception) {
            throw InvalidFormat::new($value, static::class, $format, $exception);
        }
    }
}
