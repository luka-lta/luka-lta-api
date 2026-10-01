CREATE TABLE `homelab_container_restarts`
(
    `restart_id`    bigint       NOT NULL AUTO_INCREMENT,
    `container_id`  varchar(64)  NOT NULL,
    `reason`        varchar(255) NOT NULL,
    `occurred_at`   datetime     NOT NULL,
    PRIMARY KEY (`restart_id`),
    KEY `restart_container` (`container_id`, `occurred_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
