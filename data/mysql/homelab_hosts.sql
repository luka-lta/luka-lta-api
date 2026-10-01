CREATE TABLE `homelab_hosts`
(
    `host_id`               varchar(64)  NOT NULL,
    `name`                  varchar(100) NOT NULL,
    `cpu_usage_percent`     decimal(5,2) NOT NULL DEFAULT '0.00',
    `memory_used_gb`        decimal(8,2) NOT NULL DEFAULT '0.00',
    `memory_total_gb`       decimal(8,2) NOT NULL DEFAULT '0.00',
    `disk_used_gb`          decimal(10,2) NOT NULL DEFAULT '0.00',
    `disk_total_gb`         decimal(10,2) NOT NULL DEFAULT '0.00',
    `load_average_1`        decimal(6,2) NOT NULL DEFAULT '0.00',
    `load_average_5`        decimal(6,2) NOT NULL DEFAULT '0.00',
    `load_average_15`       decimal(6,2) NOT NULL DEFAULT '0.00',
    `temperature_celsius`   decimal(5,2) NULL     DEFAULT NULL,
    `uptime_seconds`        bigint       NOT NULL DEFAULT '0',
    `created_at`            datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `homelab_hosts`
    ADD PRIMARY KEY (`host_id`);
