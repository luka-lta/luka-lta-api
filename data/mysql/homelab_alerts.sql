CREATE TABLE `homelab_alerts`
(
    `alert_id`     char(36)                         NOT NULL,
    `severity`     enum ('critical', 'warning', 'info') NOT NULL,
    `title`        varchar(150)                     NOT NULL,
    `description`  text                              NOT NULL,
    `container_id` varchar(64)                       NULL DEFAULT NULL,
    `host_id`      varchar(64)                        NULL DEFAULT NULL,
    `created_at`   datetime                           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at`  datetime                            NULL DEFAULT NULL,
    PRIMARY KEY (`alert_id`),
    KEY `alert_container` (`container_id`),
    KEY `alert_host` (`host_id`),
    KEY `alert_unresolved` (`resolved_at`, `created_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
