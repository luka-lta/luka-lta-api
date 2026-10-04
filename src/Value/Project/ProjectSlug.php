<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class ProjectSlug
{
    /** Entspricht der Spaltenbreite von projects.slug */
    private const int MAX_LENGTH = 100;

    private const string PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private function __construct(
        private readonly string $value,
    ) {
        if (trim($value) === '') {
            throw new ApiInvalidArgumentException('Project slug cannot be empty.', 400);
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new ApiInvalidArgumentException('Project slug must not exceed 100 characters.', 400);
        }

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new ApiInvalidArgumentException(
                'Project slug may only contain lowercase letters, digits and single hyphens.',
                400,
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public static function fromName(string $name): self
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));

        return new self(trim($slug, '-'));
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
