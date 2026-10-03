-- Single-row config: the coordinates the weather widget fetches for. Kept in its
-- own table (not hardcoded in env/code) so it can be changed later from a
-- Settings page without a deploy.
CREATE TABLE `weather_location`
(
    `id`         tinyint unsigned NOT NULL,
    `city`       varchar(100)     NOT NULL,
    `latitude`   decimal(8, 5)    NOT NULL,
    `longitude`  decimal(8, 5)    NOT NULL,
    `timezone`   varchar(64)      NOT NULL DEFAULT 'auto',
    `updated_at` datetime         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

INSERT INTO `weather_location` (`id`, `city`, `latitude`, `longitude`, `timezone`)
VALUES (1, 'Hameln', 52.10484, 9.35758, 'Europe/Berlin');
