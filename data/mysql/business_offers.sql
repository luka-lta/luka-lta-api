-- Angebote. offer_number wird erst beim Versenden vergeben (Entwurf: NULL).
-- Snapshots frieren Kunden- und Absenderdaten zum Versandzeitpunkt ein.
CREATE TABLE `business_offers`
(
    `offer_id`          char(36)                                          NOT NULL,
    `offer_number`      varchar(20)                                           NULL DEFAULT NULL,
    `customer_id`       char(36)                                          NOT NULL,
    `status`            enum ('draft', 'sent', 'accepted', 'rejected')    NOT NULL DEFAULT 'draft',
    `offer_date`        date                                              NOT NULL,
    `valid_until`       date                                              NOT NULL,
    `intro_text`        text                                                  NULL,
    `closing_text`      text                                                  NULL,
    `total_cents`       int                                               NOT NULL DEFAULT 0,
    `customer_snapshot` json                                                  NULL DEFAULT NULL,
    `issuer_snapshot`   json                                                  NULL DEFAULT NULL,
    `pdf_object_key`    varchar(255)                                          NULL DEFAULT NULL,
    `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`offer_id`),
    UNIQUE KEY `uq_offer_number` (`offer_number`),
    KEY `offer_customer` (`customer_id`),
    KEY `offer_status` (`status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
