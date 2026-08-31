<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

final class PrettyJsonFormatter extends JsonFormatter
{
    public function __construct()
    {
        parent::__construct(
            batchMode: self::BATCH_MODE_NEWLINES,
            appendNewline: true,
            ignoreEmptyContextAndExtra: true,
        );
        $this->setJsonPrettyPrint(true);
    }

    public function format(LogRecord $record): string
    {
        return parent::format($record) . "\n";
    }
}
