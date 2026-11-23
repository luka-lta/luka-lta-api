-- Absenderdaten fuer Belege (Singleton: settings_id ist immer 1). Es gibt
-- bewusst keine Seed-Zeile; fehlt die Zeile, gilt das Business-Modul als
-- nicht konfiguriert.
CREATE TABLE `business_settings`
(
    `settings_id`               tinyint      NOT NULL,
    `company_name`              varchar(150) NOT NULL,
    `owner_name`                varchar(150) NOT NULL,
    `street`                    varchar(150) NOT NULL,
    `postal_code`               varchar(20)  NOT NULL,
    `city`                      varchar(100) NOT NULL,
    `email`                     varchar(255) NOT NULL,
    `phone`                     varchar(50)      NULL DEFAULT NULL,
    `tax_number`                varchar(50)  NOT NULL,
    `bank_name`                 varchar(100) NOT NULL,
    `iban`                      varchar(34)  NOT NULL,
    `bic`                       varchar(11)      NULL DEFAULT NULL,
    `logo_object_key`           varchar(255)     NULL DEFAULT NULL,
    `default_payment_term_days` smallint     NOT NULL DEFAULT 14,
    `footer_text`               text             NULL,
    `updated_at`                datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`settings_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
