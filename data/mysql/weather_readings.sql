-- Append-only history of fetched "current conditions" snapshots. One row per
-- cron fetch; lets the widget show "last updated" / staleness and, later, a
-- trend without re-calling the provider.
CREATE TABLE `weather_readings`
(
    `id`                            bigint           NOT NULL AUTO_INCREMENT,
    `temperature_celsius`           decimal(4, 1)    NOT NULL,
    `apparent_temperature_celsius`  decimal(4, 1)    NOT NULL,
    `condition_code`                smallint         NOT NULL,
    `humidity_percent`              tinyint unsigned NOT NULL,
    `precipitation_mm`              decimal(5, 2)    NOT NULL DEFAULT 0,
    `cloud_cover_percent`           tinyint unsigned NOT NULL DEFAULT 0,
    `pressure_msl_hpa`              decimal(6, 1)    NOT NULL DEFAULT 0,
    `wind_speed_kmh`                decimal(5, 1)    NOT NULL DEFAULT 0,
    `wind_direction_degrees`        smallint unsigned NOT NULL DEFAULT 0,
    `wind_gusts_kmh`                decimal(5, 1)    NOT NULL DEFAULT 0,
    `uv_index`                      decimal(4, 1)    NULL     DEFAULT NULL,
    `is_day`                        tinyint(1)       NOT NULL DEFAULT 1,
    `fetched_at`                    datetime         NOT NULL,
    PRIMARY KEY (`id`),
    KEY `weather_readings_fetched_at` (`fetched_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

-- Single-row cache of the hourly + daily forecast payload, overwritten on every
-- cron fetch. JSON blobs rather than relational rows: these are short-lived
-- forecast snapshots, not something queried historically.
CREATE TABLE `weather_forecast_cache`
(
    `id`             tinyint unsigned NOT NULL,
    `hourly_payload` json             NOT NULL,
    `daily_payload`  json             NOT NULL,
    `fetched_at`     datetime         NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
