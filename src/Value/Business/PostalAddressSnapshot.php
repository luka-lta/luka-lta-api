<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use JsonSerializable;
use LukaLtaApi\Exception\ApiDatabaseException;

/**
 * Eingefrorene Empfängerdaten eines festgeschriebenen Belegs.
 * Die Schlüssel landen als JSON in der DB und müssen stabil bleiben.
 */
class PostalAddressSnapshot implements JsonSerializable
{
    /** @param list<string> $addressLines */
    private function __construct(
        private readonly string $displayName,
        private readonly array $addressLines,
    ) {
    }

    public static function fromCustomer(Customer $customer): self
    {
        return new self($customer->getDisplayName(), $customer->toAddressLines());
    }

    /** @param array<mixed> $data dekodiertes JSON aus der DB */
    public static function fromArray(array $data): self
    {
        $displayName = $data['displayName'] ?? null;
        $addressLines = $data['addressLines'] ?? null;
        if (!is_string($displayName) || !is_array($addressLines)) {
            throw new ApiDatabaseException('Invalid customer snapshot');
        }

        $lines = [];
        foreach ($addressLines as $line) {
            if (!is_string($line)) {
                throw new ApiDatabaseException('Invalid customer snapshot');
            }
            $lines[] = $line;
        }

        return new self($displayName, $lines);
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /** @return list<string> */
    public function getAddressLines(): array
    {
        return $this->addressLines;
    }

    /** @return array{displayName: string, addressLines: list<string>} */
    public function toArray(): array
    {
        return [
            'displayName' => $this->displayName,
            'addressLines' => $this->addressLines,
        ];
    }

    /** @return array{displayName: string, addressLines: list<string>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
