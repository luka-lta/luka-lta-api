<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class ProjectName
{
    /** Entspricht der Spaltenbreite von projects.name */
    private const int MAX_LENGTH = 100;

    private function __construct(
        private readonly string $value,
    ) {
        if (trim($value) === '') {
            throw new ApiInvalidArgumentException('Project name cannot be empty.', 400);
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new ApiInvalidArgumentException('Project name must not exceed 100 characters.', 400);
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
