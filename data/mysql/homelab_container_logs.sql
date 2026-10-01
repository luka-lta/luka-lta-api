CREATE TABLE `homelab_container_logs`
(
    `log_id`       bigint                        NOT NULL AUTO_INCREMENT,
    `container_id` varchar(64)                   NOT NULL,
    `level`        enum ('info', 'warn', 'error') NOT NULL,
    `message`      text                          NOT NULL,
    `logged_at`    datetime                       NOT NULL,
    PRIMARY KEY (`log_id`),
    KEY `log_container` (`container_id`, `logged_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
