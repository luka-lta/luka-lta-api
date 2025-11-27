<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\WebTracking\Tracking;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiInvalidArgumentException;

class TrackingProperties
{
    public function __construct(
        private readonly ?array $value,
        private readonly ?string $jsonString,
    ) {
    }

    public static function from(
        ?string $jsonString,
    ): self {
        if ($jsonString === null) {
            return new self(null, null);
        }

        $data = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiInvalidArgumentException(
                'Invalid JSON string provided for TrackingProperties',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        return new self($data, $jsonString);
    }

    public static function fromPayload(array $payload): self
    {
        $jsonString = isset($payload['properties']) ? (string)$payload['properties'] : null;

        if ($jsonString === null) {
            return new self(null, null);
        }

        $data = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiInvalidArgumentException(
                'Invalid JSON string provided for TrackingProperties',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        return new self($data, $jsonString);
    }

    public function getValue(): ?array
    {
        return $this->value;
    }

    public function getJsonString(): ?string
    {
        return $this->jsonString;
    }
}
