<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Asset;

use Countable;
use Generator;
use IteratorAggregate;

class ProjectAssets implements IteratorAggregate, Countable
{
    private readonly array $assets;

    private function __construct(ProjectAsset ...$assets)
    {
        $this->assets = $assets;
    }

    public static function from(ProjectAsset ...$assets): self
    {
        return new self(...$assets);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function getIterator(): Generator
    {
        yield from $this->assets;
    }

    public function count(): int
    {
        return count($this->assets);
    }

    public function ofType(ProjectAssetType $type): ?ProjectAsset
    {
        foreach ($this->assets as $asset) {
            if ($asset->getType() === $type) {
                return $asset;
            }
        }

        return null;
    }

    /** @return ProjectAsset[] */
    public function allOfType(ProjectAssetType $type): array
    {
        return array_values(
            array_filter($this->assets, static fn(ProjectAsset $asset) => $asset->getType() === $type)
        );
    }

    public function toArray(string $assetBaseUrl): array
    {
        return array_map(static fn(ProjectAsset $asset) => $asset->toArray($assetBaseUrl), $this->assets);
    }
}
