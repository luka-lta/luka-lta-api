<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Business\BusinessSettings;
use PDO;
use PDOException;

class BusinessSettingsRepository
{
    /** Die Tabelle ist ein Singleton, die Zeile hat immer diese ID */
    private const int SETTINGS_ID = 1;

    private const string SETTINGS_SELECT = <<<SQL
        SELECT
            s.company_name,
            s.owner_name,
            s.street,
            s.postal_code,
            s.city,
            s.email,
            s.phone,
            s.tax_number,
            s.bank_name,
            s.iban,
            s.bic,
            s.logo_object_key,
            s.default_payment_term_days,
            s.footer_text
        FROM business_settings s
        WHERE s.settings_id = :settings_id
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadSettings(): ?BusinessSettings
    {
        try {
            $stmt = $this->pdo->prepare(self::SETTINGS_SELECT);
            $stmt->execute(['settings_id' => self::SETTINGS_ID]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch business settings', previous: $exception);
        }

        return $row !== false ? BusinessSettings::fromDatabase($row) : null;
    }

    /** Upsert auf die Singleton-Zeile */
    public function save(BusinessSettings $settings): void
    {
        $sql = <<<SQL
            INSERT INTO business_settings
                (settings_id, company_name, owner_name, street, postal_code, city, email, phone, tax_number,
                 bank_name, iban, bic, logo_object_key, default_payment_term_days, footer_text)
            VALUES
                (:settings_id, :company_name, :owner_name, :street, :postal_code, :city, :email, :phone,
                 :tax_number, :bank_name, :iban, :bic, :logo_object_key, :default_payment_term_days,
                 :footer_text)
            ON DUPLICATE KEY UPDATE
                company_name = VALUES(company_name),
                owner_name = VALUES(owner_name),
                street = VALUES(street),
                postal_code = VALUES(postal_code),
                city = VALUES(city),
                email = VALUES(email),
                phone = VALUES(phone),
                tax_number = VALUES(tax_number),
                bank_name = VALUES(bank_name),
                iban = VALUES(iban),
                bic = VALUES(bic),
                logo_object_key = VALUES(logo_object_key),
                default_payment_term_days = VALUES(default_payment_term_days),
                footer_text = VALUES(footer_text)
        SQL;

        try {
            $this->pdo->prepare($sql)->execute(
                ['settings_id' => self::SETTINGS_ID] + $settings->toDatabaseRow()
            );
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to save business settings', previous: $exception);
        }
    }
}
