CREATE TABLE `homelab_container_metrics`
(
    `container_metric_id` bigint                   NOT NULL AUTO_INCREMENT,
    `container_id`        varchar(64)               NOT NULL,
    `metric_type`         enum ('cpu', 'memory')    NOT NULL,
    `value`               decimal(8,2)              NOT NULL,
    `recorded_at`         datetime                  NOT NULL,
    PRIMARY KEY (`container_metric_id`),
    KEY `container_metric_lookup` (`container_id`, `metric_type`, `recorded_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
