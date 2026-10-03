<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Tag;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class ProjectTagName
{
    /** Maximale Laenge entspricht der Spaltenbreite von project_tags.name */
    private const int MAX_LENGTH = 50;

    private function __construct(
        private readonly string $value,
    ) {
        if (trim($value) === '') {
            throw new ApiInvalidArgumentException('Project tag name cannot be empty.', 400);
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new ApiInvalidArgumentException('Project tag name must not exceed 50 characters.', 400);
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
