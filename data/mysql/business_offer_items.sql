-- Positionen eines Angebots. Menge in Hundertstel (150 = 1,5), Betraege in Cent.
CREATE TABLE `business_offer_items`
(
    `item_id`             char(36)     NOT NULL,
    `offer_id`          char(36)     NOT NULL,
    `position`            smallint     NOT NULL,
    `description`         text         NOT NULL,
    `quantity_hundredths` int          NOT NULL,
    `unit`                varchar(30)  NOT NULL,
    `unit_price_cents`    int          NOT NULL,
    `line_total_cents`    int          NOT NULL,
    PRIMARY KEY (`item_id`),
    KEY `offer_item_parent` (`offer_id`, `position`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
