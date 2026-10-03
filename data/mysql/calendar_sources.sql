-- A configured calendar to sync. `type` picks the provider at sync time
-- (CalendarProviderInterface implementation) — adding Google/Outlook later is a
-- new `type` value and a new provider class, no schema change.
CREATE TABLE `calendar_sources`
(
    `id`         int unsigned                 NOT NULL AUTO_INCREMENT,
    `type`       enum ('ics')                 NOT NULL,
    `name`       varchar(100)                 NOT NULL,
    `url`        varchar(1024)                NOT NULL,
    `color`      varchar(20)                  NULL     DEFAULT NULL,
    `is_enabled` tinyint(1)                   NOT NULL DEFAULT 1,
    `created_at` datetime                     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime                     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
