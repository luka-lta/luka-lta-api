-- Kunden fuer Angebote und Rechnungen. Mindestens einer von company_name und
-- contact_name ist fachlich Pflicht (wird in der Anwendung geprueft). Kunden
-- mit Belegen werden archiviert (archived_at) statt geloescht.
CREATE TABLE `business_customers`
(
    `customer_id`  char(36)     NOT NULL,
    `company_name` varchar(150)     NULL DEFAULT NULL,
    `contact_name` varchar(150)     NULL DEFAULT NULL,
    `street`       varchar(150) NOT NULL,
    `postal_code`  varchar(20)  NOT NULL,
    `city`         varchar(100) NOT NULL,
    `country`      char(2)      NOT NULL DEFAULT 'DE',
    `email`        varchar(255)     NULL DEFAULT NULL,
    `vat_id`       varchar(30)      NULL DEFAULT NULL,
    `notes`        text             NULL,
    `archived_at`  datetime         NULL DEFAULT NULL,
    `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`customer_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
