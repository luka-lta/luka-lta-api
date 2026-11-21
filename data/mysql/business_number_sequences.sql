-- Fortlaufende Belegnummern je Praefix (RE = Rechnung, AN = Angebot) und Jahr.
-- last_value wird beim Vergeben transaktional hochgezaehlt (lueckenlos).
CREATE TABLE `business_number_sequences`
(
    `prefix`     enum ('RE', 'AN') NOT NULL,
    `year`       smallint          NOT NULL,
    `last_value` int               NOT NULL DEFAULT 0,
    PRIMARY KEY (`prefix`, `year`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
