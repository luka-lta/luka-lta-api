-- Projekt-Bilder. Die DB haelt nur die MinIO-Referenz, nie die Bytes.
-- logo/cover sind pro Projekt Singleton (applikationsseitig erzwungen, da eine
-- gemeinsame Unique-Constraint die mehrfachen screenshot-Zeilen brechen wuerde).
CREATE TABLE `project_assets`
(
    `asset_id`   char(36)                                   NOT NULL,
    `project_id` char(36)                                   NOT NULL,
    `type`       enum ('logo', 'cover', 'screenshot')       NOT NULL,
    `object_key` varchar(255)                               NOT NULL,
    `alt_text`   varchar(150)                                   NULL DEFAULT NULL,
    `sort_order` int                                        NOT NULL DEFAULT 0,
    `created_at` datetime                                   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`asset_id`),
    KEY `project_asset_lookup` (`project_id`, `type`, `sort_order`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
