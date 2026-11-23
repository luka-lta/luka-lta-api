-- Rechnungen (Standard, Anzahlung, Schluss, Storno). invoice_number wird erst
-- beim Ausstellen vergeben (Entwurf: NULL). Storno: alle Betraege negativ.
-- total_cents = subtotal_cents - deposit_deduction_cents. is_imported markiert
-- nachgetragene Altrechnungen.
CREATE TABLE `business_invoices`
(
    `invoice_id`              char(36)                                                    NOT NULL,
    `invoice_number`          varchar(20)                                                     NULL DEFAULT NULL,
    `kind`                    enum ('standard', 'deposit', 'final', 'cancellation')       NOT NULL DEFAULT 'standard',
    `status`                  enum ('draft', 'open', 'paid', 'cancelled', 'issued')       NOT NULL DEFAULT 'draft',
    `customer_id`             char(36)                                                    NOT NULL,
    `offer_id`                char(36)                                                        NULL DEFAULT NULL,
    `deposit_invoice_id`      char(36)                                                        NULL DEFAULT NULL,
    `cancels_invoice_id`      char(36)                                                        NULL DEFAULT NULL,
    `invoice_date`            date                                                        NOT NULL,
    `service_date`            date                                                            NULL DEFAULT NULL,
    `service_period_start`    date                                                            NULL DEFAULT NULL,
    `service_period_end`      date                                                            NULL DEFAULT NULL,
    `payment_term_days`       smallint                                                    NOT NULL,
    `due_date`                date                                                            NULL DEFAULT NULL,
    `intro_text`              text                                                            NULL,
    `closing_text`            text                                                            NULL,
    `subtotal_cents`          int                                                         NOT NULL DEFAULT 0,
    `deposit_deduction_cents` int                                                         NOT NULL DEFAULT 0,
    `total_cents`             int                                                         NOT NULL DEFAULT 0,
    `paid_at`                 date                                                            NULL DEFAULT NULL,
    `is_imported`             tinyint(1)                                                  NOT NULL DEFAULT 0,
    `customer_snapshot`       json                                                            NULL DEFAULT NULL,
    `issuer_snapshot`         json                                                            NULL DEFAULT NULL,
    `pdf_object_key`          varchar(255)                                                    NULL DEFAULT NULL,
    `created_at`              datetime                                                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              datetime                                                    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`invoice_id`),
    UNIQUE KEY `uq_invoice_number` (`invoice_number`),
    KEY `invoice_customer` (`customer_id`),
    KEY `invoice_offer` (`offer_id`),
    KEY `invoice_deposit` (`deposit_invoice_id`),
    KEY `invoice_cancels` (`cancels_invoice_id`),
    KEY `invoice_status` (`status`, `due_date`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
