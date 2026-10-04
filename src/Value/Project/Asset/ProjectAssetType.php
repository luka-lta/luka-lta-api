<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Asset;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

enum ProjectAssetType: string
{
    case LOGO = 'logo';
    case COVER = 'cover';
    case SCREENSHOT = 'screenshot';

    public static function fromString(string $value): self
    {
        $type = self::tryFrom($value);

        if ($type === null) {
            throw new ApiInvalidArgumentException(
                sprintf('Invalid project asset type: %s', $value),
                400,
            );
        }

        return $type;
    }

    /** logo und cover existieren pro Projekt genau einmal und werden beim Upload ersetzt */
    public function isSingleton(): bool
    {
        return $this !== self::SCREENSHOT;
    }
}
