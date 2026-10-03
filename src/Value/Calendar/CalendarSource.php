<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Calendar;

use JsonSerializable;

class CalendarSource implements JsonSerializable
{
    private function __construct(
        private readonly ?int   $id,
        private readonly string $type,
        private readonly string $name,
        private readonly string $url,
        private readonly ?string $color,
        private readonly bool   $isEnabled,
    ) {
    }

    public static function create(string $type, string $name, string $url, ?string $color): self
    {
        return new self(null, $type, $name, $url, $color, true);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['type'],
            $row['name'],
            $row['url'],
            $row['color'],
            (bool) $row['is_enabled'],
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'url' => $this->url,
            'color' => $this->color,
            'isEnabled' => $this->isEnabled,
        ];
    }
}
