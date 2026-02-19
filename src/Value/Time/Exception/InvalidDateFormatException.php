<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Time\Exception;

use LukaLtaApi\Exception\ApiException;
use Throwable;

class InvalidDateFormatException extends ApiException
{
    public function __construct(
        private readonly string  $input,
        private readonly ?string $format,
        ?Throwable               $previous = null,
    ) {
        $message = sprintf(
            'Date "%s" is not a valid format%s',
            $this->input,
            $this->format === null ? '' : sprintf(' (%s)', $this->format),
        );

        parent::__construct($message, previous: $previous);
    }

    public static function fromInputAndFormat(string $input, string $format, ?Throwable $previous = null): self
    {
        return new self($input, $format, previous: $previous);
    }

    public static function fromInput(string $input, ?Throwable $previous = null): self
    {
        return new self($input, null, previous: $previous);
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }
}
