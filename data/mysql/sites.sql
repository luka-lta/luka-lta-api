CREATE TABLE `sites`
(
    `site_id`              int          NOT NULL,
    `name`                 varchar(255) NOT NULL,
    `domain`               varchar(255) NOT NULL,
    `created_at`           datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           datetime              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `created_by`           varchar(255)          DEFAULT NULL,

    `public`               tinyint(1)    NOT NULL DEFAULT '0',
    `block_bots`           tinyint(1)    NOT NULL DEFAULT '1',

    `excluded_ips`         json          NOT NULL DEFAULT (json_array()),
    `excluded_countries`   json          NOT NULL DEFAULT (json_array()),

    `web_vitals`           tinyint(1)    NOT NULL DEFAULT '0',
    `track_errors`         tinyint(1)    NOT NULL DEFAULT '0',
    `track_outbound`       tinyint(1)    NOT NULL DEFAULT '1',
    `track_url_params`     tinyint(1)    NOT NULL DEFAULT '1',
    `track_initial`        tinyint(1)    NOT NULL DEFAULT '1',
    `track_spa_navigation` tinyint(1)    NOT NULL DEFAULT '1',
    `track_ip`             tinyint(1)    NOT NULL DEFAULT '0'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `sites`
    ADD PRIMARY KEY (`site_id`);

ALTER TABLE `sites`
    MODIFY `site_id` int NOT NULL AUTO_INCREMENT;
