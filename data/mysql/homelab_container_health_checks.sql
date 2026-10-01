CREATE TABLE `homelab_container_health_checks`
(
    `health_check_id` bigint                                            NOT NULL AUTO_INCREMENT,
    `container_id`    varchar(64)                                       NOT NULL,
    `status`          enum ('healthy', 'warning', 'unhealthy', 'none')  NOT NULL,
    `message`         varchar(255)                                      NOT NULL,
    `checked_at`       datetime                                         NOT NULL,
    PRIMARY KEY (`health_check_id`),
    KEY `health_check_container` (`container_id`, `checked_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
