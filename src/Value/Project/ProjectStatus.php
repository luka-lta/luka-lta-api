<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

enum ProjectStatus: string
{
    case DEVELOPMENT = 'development';
    case BETA = 'beta';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case ARCHIVED = 'archived';

    public static function fromString(string $value): self
    {
        $status = self::tryFrom($value);

        if ($status === null) {
            throw new ApiInvalidArgumentException(
                sprintf('Invalid project status: %s', $value),
                400,
            );
        }

        return $status;
    }
}
