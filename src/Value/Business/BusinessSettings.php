<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;

class BusinessSettings implements JsonSerializable
{
    private const int MAX_COMPANY_NAME_LENGTH = 150;
    private const int MAX_OWNER_NAME_LENGTH = 150;
    private const int MAX_STREET_LENGTH = 150;
    private const int MAX_POSTAL_CODE_LENGTH = 20;
    private const int MAX_CITY_LENGTH = 100;
    private const int MAX_EMAIL_LENGTH = 255;
    private const int MAX_PHONE_LENGTH = 50;
    private const int MAX_TAX_NUMBER_LENGTH = 50;
    private const int MAX_BANK_NAME_LENGTH = 100;

    /** Fußtext ist ein TEXT-Feld (65535 Byte), Zeichenlimit mit Puffer für Multibyte */
    private const int MAX_FOOTER_TEXT_LENGTH = 10000;

    /** Obergrenze für das Standard-Zahlungsziel in Tagen (ein Jahr) */
    private const int MAX_PAYMENT_TERM_DAYS = 365;

    private const int DEFAULT_PAYMENT_TERM_DAYS = 14;

    /** Eingabelänge inkl. Leerzeichen (IBAN wird gruppiert eingegeben, Spalte fasst 34 Zeichen) */
    private const int MAX_IBAN_INPUT_LENGTH = 50;

    private const int MAX_BIC_INPUT_LENGTH = 20;

    /** Ländercode, zwei Prüfziffern, 11 bis 30 alphanumerische Zeichen (ohne Prüfsummenvalidierung) */
    private const string IBAN_PATTERN = '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/';

    /** BIC besteht aus 8 oder 11 alphanumerischen Zeichen */
    private const string BIC_PATTERN = '/^[A-Z0-9]{8}([A-Z0-9]{3})?$/';

    private function __construct(
        private readonly string $companyName,
        private readonly string $ownerName,
        private readonly string $street,
        private readonly string $postalCode,
        private readonly string $city,
        private readonly string $email,
        private readonly ?string $phone,
        private readonly string $taxNumber,
        private readonly string $bankName,
        private readonly string $iban,
        private readonly ?string $bic,
        private readonly ?string $logoObjectKey,
        private readonly int $defaultPaymentTermDays,
        private readonly ?string $footerText,
    ) {
    }

    /** @param array<string, mixed> $data camelCase-Felder des Requests */
    public static function fromRequest(array $data, ?string $logoObjectKey = null): self
    {
        return new self(
            self::requiredString($data, 'companyName', self::MAX_COMPANY_NAME_LENGTH),
            self::requiredString($data, 'ownerName', self::MAX_OWNER_NAME_LENGTH),
            self::requiredString($data, 'street', self::MAX_STREET_LENGTH),
            self::requiredString($data, 'postalCode', self::MAX_POSTAL_CODE_LENGTH),
            self::requiredString($data, 'city', self::MAX_CITY_LENGTH),
            self::parseEmail($data),
            self::optionalString($data, 'phone', self::MAX_PHONE_LENGTH),
            self::requiredString($data, 'taxNumber', self::MAX_TAX_NUMBER_LENGTH),
            self::requiredString($data, 'bankName', self::MAX_BANK_NAME_LENGTH),
            self::parseIban($data),
            self::parseBic($data),
            $logoObjectKey,
            self::parsePaymentTermDays($data['defaultPaymentTermDays'] ?? null),
            self::optionalString($data, 'footerText', self::MAX_FOOTER_TEXT_LENGTH),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromDatabase(array $row): self
    {
        return new self(
            (string) $row['company_name'],
            (string) $row['owner_name'],
            (string) $row['street'],
            (string) $row['postal_code'],
            (string) $row['city'],
            (string) $row['email'],
            $row['phone'],
            (string) $row['tax_number'],
            (string) $row['bank_name'],
            (string) $row['iban'],
            $row['bic'],
            $row['logo_object_key'],
            (int) $row['default_payment_term_days'],
            $row['footer_text'],
        );
    }

    /** Pflichtangaben für einen Beleg; eine per Parsen erzeugte Instanz erfüllt das immer */
    public function isComplete(): bool
    {
        return $this->companyName !== ''
            && $this->street !== ''
            && $this->postalCode !== ''
            && $this->city !== ''
            && $this->taxNumber !== ''
            && $this->iban !== '';
    }

    public function hasLogo(): bool
    {
        return $this->logoObjectKey !== null;
    }

    public function getLogoObjectKey(): ?string
    {
        return $this->logoObjectKey;
    }

    public function getDefaultPaymentTermDays(): int
    {
        return $this->defaultPaymentTermDays;
    }

    public function getFooterText(): ?string
    {
        return $this->footerText;
    }

    public function withLogoObjectKey(?string $logoObjectKey): self
    {
        $copy = $this;

        return new self(
            $copy->companyName,
            $copy->ownerName,
            $copy->street,
            $copy->postalCode,
            $copy->city,
            $copy->email,
            $copy->phone,
            $copy->taxNumber,
            $copy->bankName,
            $copy->iban,
            $copy->bic,
            $logoObjectKey,
            $copy->defaultPaymentTermDays,
            $copy->footerText,
        );
    }

    /**
     * Absenderdaten, die in einen Beleg eingefroren werden. Die Schlüssel müssen stabil bleiben.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'companyName' => $this->companyName,
            'ownerName' => $this->ownerName,
            'addressLines' => [
                $this->street,
                $this->postalCode . ' ' . $this->city,
            ],
            'email' => $this->email,
            'phone' => $this->phone,
            'taxNumber' => $this->taxNumber,
            'bankName' => $this->bankName,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'footerText' => $this->footerText,
        ];
    }

    /** @return array<string, mixed> */
    public function toDatabaseRow(): array
    {
        return [
            'company_name' => $this->companyName,
            'owner_name' => $this->ownerName,
            'street' => $this->street,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'email' => $this->email,
            'phone' => $this->phone,
            'tax_number' => $this->taxNumber,
            'bank_name' => $this->bankName,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'logo_object_key' => $this->logoObjectKey,
            'default_payment_term_days' => $this->defaultPaymentTermDays,
            'footer_text' => $this->footerText,
        ];
    }

    /**
     * Der Object-Key des Logos wird bewusst nicht ausgegeben.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'companyName' => $this->companyName,
            'ownerName' => $this->ownerName,
            'street' => $this->street,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'email' => $this->email,
            'phone' => $this->phone,
            'taxNumber' => $this->taxNumber,
            'bankName' => $this->bankName,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'defaultPaymentTermDays' => $this->defaultPaymentTermDays,
            'footerText' => $this->footerText,
            'hasLogo' => $this->hasLogo(),
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

    /** @param array<string, mixed> $data */
    private static function parseEmail(array $data): string
    {
        $email = self::requiredString($data, 'email', self::MAX_EMAIL_LENGTH);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiValidationException('email must be a valid email address', 400);
        }

        return $email;
    }

    /** @param array<string, mixed> $data */
    private static function parseIban(array $data): string
    {
        $raw = self::requiredString($data, 'iban', self::MAX_IBAN_INPUT_LENGTH);
        $iban = strtoupper((string) preg_replace('/\s+/', '', $raw));
        if (preg_match(self::IBAN_PATTERN, $iban) !== 1) {
            throw new ApiValidationException('iban is not a valid IBAN', 400);
        }

        return $iban;
    }

    /** @param array<string, mixed> $data */
    private static function parseBic(array $data): ?string
    {
        $raw = self::optionalString($data, 'bic', self::MAX_BIC_INPUT_LENGTH);
        if ($raw === null) {
            return null;
        }

        $bic = strtoupper((string) preg_replace('/\s+/', '', $raw));
        if (preg_match(self::BIC_PATTERN, $bic) !== 1) {
            throw new ApiValidationException('bic must be 8 or 11 alphanumeric characters', 400);
        }

        return $bic;
    }

    private static function parsePaymentTermDays(mixed $value): int
    {
        if ($value === null) {
            return self::DEFAULT_PAYMENT_TERM_DAYS;
        }
        if (!is_int($value)) {
            throw new ApiValidationException('defaultPaymentTermDays must be an integer', 400);
        }
        if ($value < 0 || $value > self::MAX_PAYMENT_TERM_DAYS) {
            throw new ApiValidationException(
                sprintf('defaultPaymentTermDays must be between 0 and %d', self::MAX_PAYMENT_TERM_DAYS),
                400,
            );
        }

        return $value;
    }
}
