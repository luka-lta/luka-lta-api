CREATE TABLE `homelab_containers`
(
    `container_id`          varchar(64)                                               NOT NULL,
    `host_id`                varchar(64)                                              NOT NULL,
    `name`                   varchar(100)                                             NOT NULL,
    `image`                  varchar(255)                                             NOT NULL,
    `status`                 enum ('running', 'stopped', 'warning', 'unhealthy')     NOT NULL,
    `health_status`          enum ('healthy', 'warning', 'unhealthy', 'none')        NOT NULL DEFAULT 'none',
    `started_at`             datetime                                                 NULL     DEFAULT NULL,
    `cpu_usage_percent`      decimal(5,2)                                             NOT NULL DEFAULT '0.00',
    `memory_used_mb`         decimal(10,2)                                            NOT NULL DEFAULT '0.00',
    `memory_limit_mb`        decimal(10,2)                                            NOT NULL DEFAULT '0.00',
    `network_in_mbps`        decimal(8,2)                                             NOT NULL DEFAULT '0.00',
    `network_out_mbps`       decimal(8,2)                                             NOT NULL DEFAULT '0.00',
    `restart_count`          int                                                      NOT NULL DEFAULT '0',
    `last_health_check_at`   datetime                                                 NULL     DEFAULT NULL,
    `ports`                  json                                                     NULL     DEFAULT NULL,
    `volumes`                json                                                     NULL     DEFAULT NULL,
    `environment`            json                                                     NULL     DEFAULT NULL,
    `created_at`             datetime                                                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             datetime                                                 NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `homelab_containers`
    ADD PRIMARY KEY (`container_id`),
    ADD KEY `container_host` (`host_id`);
