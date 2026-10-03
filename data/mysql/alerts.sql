CREATE TABLE `alerts`
(
    `alert_id`          char(36)                              NOT NULL,
    `fingerprint`       char(64)                              NOT NULL,
    `source`            varchar(32)                           NOT NULL,
    `source_id`         varchar(64)                            NULL DEFAULT NULL,
    `type`              varchar(64)                           NOT NULL,
    `severity`          enum ('critical', 'warning', 'info')  NOT NULL,
    `title`             varchar(150)                          NOT NULL,
    `description`       text                                  NOT NULL,
    `context`           json                                   NULL DEFAULT NULL,
    `occurrence_count`  int unsigned                          NOT NULL DEFAULT 1,
    `first_occurred_at` datetime                              NOT NULL,
    `last_occurred_at`  datetime                              NOT NULL,
    `resolved_at`       datetime                               NULL DEFAULT NULL,
    PRIMARY KEY (`alert_id`),
    KEY `alert_fingerprint` (`fingerprint`),
    KEY `alert_source` (`source`, `source_id`),
    KEY `alert_unresolved` (`resolved_at`, `last_occurred_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
