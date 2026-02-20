<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception\Formatter;

use LukaLtaApi\Exception\FormattableException;
use LukaLtaApi\Util\CliUtils;
use Throwable;

class ExceptionFormatter
{
    public function __construct(
        private readonly CliUtils $cliUtils,
    ) {}

    public function formatException(Throwable $throwable): void
    {
        if ($throwable instanceof FormattableException) {
            $isInteractiveErr = $this->cliUtils->isInteractiveErr();
            $this->cliUtils->writeErr(match ($isInteractiveErr) {
                    true  => $throwable->formatHuman(),
                    false => $throwable->format(),
            } . PHP_EOL);

            return;
        }

        $this->cliUtils->writeErr($throwable . PHP_EOL);
    }
}
