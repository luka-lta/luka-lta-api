CREATE TABLE `homelab_host_metrics`
(
    `host_metric_id` bigint                                                           NOT NULL AUTO_INCREMENT,
    `host_id`        varchar(64)                                                      NOT NULL,
    `metric_type`    enum ('cpu', 'memory', 'disk', 'network_in', 'network_out')      NOT NULL,
    `value`          decimal(8,2)                                                     NOT NULL,
    `recorded_at`    datetime                                                         NOT NULL,
    PRIMARY KEY (`host_metric_id`),
    KEY `host_metric_lookup` (`host_id`, `metric_type`, `recorded_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
