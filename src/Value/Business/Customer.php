<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;

class Customer implements JsonSerializable
{
    private const int MAX_COMPANY_NAME_LENGTH = 150;
    private const int MAX_CONTACT_NAME_LENGTH = 150;
    private const int MAX_STREET_LENGTH = 150;
    private const int MAX_POSTAL_CODE_LENGTH = 20;
    private const int MAX_CITY_LENGTH = 100;
    private const int MAX_EMAIL_LENGTH = 255;
    private const int MAX_VAT_ID_LENGTH = 30;

    /** Notizen sind ein TEXT-Feld (65535 Byte), Zeichenlimit mit Puffer für Multibyte */
    private const int MAX_NOTES_LENGTH = 10000;

    private const string DEFAULT_COUNTRY = 'DE';

    private function __construct(
        private readonly CustomerId $customerId,
        private readonly ?string $companyName,
        private readonly ?string $contactName,
        private readonly string $street,
        private readonly string $postalCode,
        private readonly string $city,
        private readonly string $country,
        private readonly ?string $email,
        private readonly ?string $vatId,
        private readonly ?string $notes,
        private readonly ?string $archivedAt,
    ) {
    }

    /** @param array<string, mixed> $data camelCase-Felder des Requests */
    public static function fromRequest(array $data, ?CustomerId $customerId = null): self
    {
        $companyName = self::optionalString($data, 'companyName', self::MAX_COMPANY_NAME_LENGTH);
        $contactName = self::optionalString($data, 'contactName', self::MAX_CONTACT_NAME_LENGTH);
        if ($companyName === null && $contactName === null) {
            throw new ApiValidationException('Either companyName or contactName is required', 400);
        }

        return new self(
            $customerId ?? CustomerId::generate(),
            $companyName,
            $contactName,
            self::requiredString($data, 'street', self::MAX_STREET_LENGTH),
            self::requiredString($data, 'postalCode', self::MAX_POSTAL_CODE_LENGTH),
            self::requiredString($data, 'city', self::MAX_CITY_LENGTH),
            self::parseCountry($data['country'] ?? null),
            self::parseEmail($data['email'] ?? null),
            self::optionalString($data, 'vatId', self::MAX_VAT_ID_LENGTH),
            self::optionalString($data, 'notes', self::MAX_NOTES_LENGTH),
            null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromDatabase(array $row): self
    {
        return new self(
            CustomerId::fromString((string) $row['customer_id']),
            $row['company_name'],
            $row['contact_name'],
            (string) $row['street'],
            (string) $row['postal_code'],
            (string) $row['city'],
            (string) $row['country'],
            $row['email'],
            $row['vat_id'],
            $row['notes'],
            $row['archived_at'],
        );
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }

    /** Firma, sonst Ansprechpartner (mindestens eines von beiden ist garantiert) */
    public function getDisplayName(): string
    {
        return $this->companyName ?? (string) $this->contactName;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /**
     * Adresszeilen für PDF und Snapshot.
     *
     * @return list<string>
     */
    public function toAddressLines(): array
    {
        $lines = [];
        if ($this->companyName !== null) {
            $lines[] = $this->companyName;
        }
        if ($this->contactName !== null) {
            $lines[] = $this->contactName;
        }
        $lines[] = $this->street;
        $lines[] = $this->postalCode . ' ' . $this->city;
        if ($this->country !== self::DEFAULT_COUNTRY) {
            $lines[] = $this->country;
        }

        return $lines;
    }

    /** @return array<string, mixed> */
    public function toDatabaseRow(): array
    {
        return [
            'customer_id' => $this->customerId->asString(),
            'company_name' => $this->companyName,
            'contact_name' => $this->contactName,
            'street' => $this->street,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'email' => $this->email,
            'vat_id' => $this->vatId,
            'notes' => $this->notes,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'customerId' => $this->customerId->asString(),
            'companyName' => $this->companyName,
            'contactName' => $this->contactName,
            'street' => $this->street,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'email' => $this->email,
            'vatId' => $this->vatId,
            'notes' => $this->notes,
            'archivedAt' => $this->archivedAt,
            'displayName' => $this->getDisplayName(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $key, int $maxLength): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiValidationException(sprintf('%s must be a string', $key), 400);
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw new ApiValidationException(sprintf('%s must be at most %d characters', $key, $maxLength), 400);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function requiredString(array $data, string $key, int $maxLength): string
    {
        $value = self::optionalString($data, $key, $maxLength);
        if ($value === null) {
            throw new ApiValidationException(sprintf('%s is required', $key), 400);
        }

        return $value;
    }

    private static function parseCountry(mixed $value): string
    {
        if ($value === null) {
            return self::DEFAULT_COUNTRY;
        }
        if (!is_string($value)) {
            throw new ApiValidationException('country must be a string', 400);
        }

        $value = trim($value);
        if ($value === '') {
            return self::DEFAULT_COUNTRY;
        }
        if (preg_match('/^[A-Z]{2}$/', $value) !== 1) {
            throw new ApiValidationException('country must be 2 uppercase letters', 400);
        }

        return $value;
    }

    private static function parseEmail(mixed $value): ?string
    {
        $email = self::optionalString(['email' => $value], 'email', self::MAX_EMAIL_LENGTH);
        if ($email === null) {
            return null;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiValidationException('email must be a valid email address', 400);
        }

        return $email;
    }
}
