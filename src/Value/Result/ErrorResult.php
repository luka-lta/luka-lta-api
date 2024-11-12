<?php

namespace LukaLtaApi\Value\Result;

use LukaLtaApi\Slim\ResultInterface;
use Throwable;

class ErrorResult implements ResultInterface
{
    private bool $isProduction;

    private function __construct(
        private readonly Throwable $throwable,
    ) {
        $this->isProduction = getenv('APP_ENV') === 'production';
    }

    public static function from(Throwable $throwable): self
    {
        return new self($throwable);
    }

    public function toArray(): array
    {
        $errorMessage = [
            'error' => $this->throwable->getMessage(),
        ];

        if ($this->isProduction) {
            $errorMessage['error'] = 'An error occurred';

            return [
                'error' => $errorMessage['error'],
            ];
        }

        return [
            'error' => $errorMessage['error'],
            'topic' => get_class($this->throwable),
            'code' => $this->throwable->getCode(),
            'file' => $this->throwable->getFile(),
            'line' => $this->throwable->getLine(),
            'trace' => $this->throwable->getTrace(),
        ];
    }
}
