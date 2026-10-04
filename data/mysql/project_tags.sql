-- Wiederverwendbares Tag-Dictionary fuer Projekte. Struktur gespiegelt von
-- `blog_tags`; eigene Tabelle statt Mitnutzung, damit Blog und Portfolio
-- entkoppelt bleiben. Collation utf8mb4_0900_ai_ci ist case-insensitive, die
-- Unique-Keys deduplizieren damit "Analytics" und "analytics" automatisch.
CREATE TABLE `project_tags`
(
    `tag_id`     int         NOT NULL AUTO_INCREMENT,
    `name`       varchar(50) NOT NULL,
    `slug`       varchar(50) NOT NULL,
    `created_at` datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tag_id`),
    UNIQUE KEY `uq_project_tags_name` (`name`),
    UNIQUE KEY `uq_project_tags_slug` (`slug`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
