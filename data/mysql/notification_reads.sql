CREATE TABLE `notification_reads`
(
    `user_id`  int      NOT NULL,
    `alert_id` char(36) NOT NULL,
    `read_at`  datetime NOT NULL,
    PRIMARY KEY (`user_id`, `alert_id`),
    KEY `notification_read_alert` (`alert_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
