<?php

namespace LukaLtaApi\Value\Result;

use LukaLtaApi\Slim\ResultInterface;

class JsonResult implements ResultInterface
{
    private function __construct(
        private readonly string $message,
        private ?array $fields = null,
    ) {}

    public static function from(
        string $message,
        ?array $fields = null,
    ): self {
        return new self($message, $fields);
    }

    public function addField(string|int $key, mixed $value): void
    {
        $this->fields[$key] = $value;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        $defaultMessage = [
            'message' => $this->message,
        ];

        if ($this->fields) {
            foreach ($this->fields as $key => $value) {
                $defaultMessage[$key] = $value;
            }
        }

        return $defaultMessage;
    }
}
