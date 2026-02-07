<?php

namespace LukaLtaApi\Value\WebTracking\Tracking;

use Fig\Http\Message\StatusCodeInterface;
use JsonException;
use LukaLtaApi\Exception\ApiInvalidArgumentException;

class Properties
{
    private const int MAX_LENGTH = 2048;

    private function __construct(
        private array $value,
        private string $jsonString,
    ) {
        if (strlen($jsonString) > self::MAX_LENGTH) {
            throw new ApiInvalidArgumentException(
                'Properties exceed maximum length',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function from(string $jsonString): Properties
    {
        try {
            $decoded = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiInvalidArgumentException(
                'Properties must be valid JSON: ' . $exception->getMessage(),
                StatusCodeInterface::STATUS_BAD_REQUEST,
                $exception
            );
        }

        return new self($decoded, $jsonString);
    }

    public static function fromPayload(array $payload): self
    {
        $jsonString = isset($payload['properties']) ? (string)$payload['properties'] : '{}';

        try {
            $decoded = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiInvalidArgumentException(
                'Properties must be valid JSON: ' . $exception->getMessage(),
                StatusCodeInterface::STATUS_BAD_REQUEST,
                $exception
            );
        }

        return new self($decoded, $jsonString);
    }

    public function getValue(): array
    {
        return $this->value;
    }

    public function getJsonString(): string
    {
        return $this->jsonString;
    }
}