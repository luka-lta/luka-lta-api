<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;
use Traversable;

/** @implements IteratorAggregate<int, LineItem> */
class LineItems implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<LineItem> */
    private readonly array $items;

    private function __construct(LineItem ...$items)
    {
        $this->items = array_values($items);
    }

    public static function from(LineItem ...$items): self
    {
        return new self(...$items);
    }

    /**
     * Erwartet pro Eintrag: description, quantity, unit (optional), unitPrice (Euro als String).
     * Positionen werden in Eingabereihenfolge ab 1 nummeriert.
     *
     * @param array<mixed> $items
     */
    public static function fromRequestArray(array $items): self
    {
        if ($items === []) {
            throw new ApiValidationException('At least one line item is required', 400);
        }

        $lineItems = [];
        foreach (array_values($items) as $index => $item) {
            $lineItems[] = self::parseRequestItem($index + 1, $item);
        }

        return new self(...$lineItems);
    }

    public function getTotal(): Money
    {
        $total = Money::fromCents(0);
        foreach ($this->items as $item) {
            $total = $total->add($item->getLineTotal());
        }

        return $total;
    }

    public function negated(): self
    {
        return new self(...array_map(static fn (LineItem $item): LineItem => $item->negated(), $this->items));
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn (LineItem $item): array => $item->toArray(), $this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<int, LineItem> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function parseRequestItem(int $position, mixed $item): LineItem
    {
        if (!is_array($item)) {
            throw new ApiValidationException('Line item must be an object', 400);
        }

        $description = $item['description'] ?? null;
        $quantity = $item['quantity'] ?? null;
        $unit = $item['unit'] ?? '';
        $unitPrice = $item['unitPrice'] ?? null;
        if (!is_string($description) || !is_string($unit)) {
            throw new ApiValidationException('Line item description and unit must be strings', 400);
        }
        if (!is_string($quantity) && !is_int($quantity)) {
            throw new ApiValidationException('Line item quantity must be a string or integer', 400);
        }
        if (!is_string($unitPrice) && !is_int($unitPrice)) {
            throw new ApiValidationException('Line item unitPrice must be a string or integer', 400);
        }

        return LineItem::create(
            $position,
            $description,
            Quantity::fromDecimalString((string)$quantity),
            $unit,
            Money::fromEuroString((string)$unitPrice),
        );
    }
}
