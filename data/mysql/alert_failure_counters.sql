CREATE TABLE `alert_failure_counters`
(
    `fingerprint`       char(64)     NOT NULL,
    `consecutive_count` int unsigned NOT NULL DEFAULT 0,
    `updated_at`        datetime     NOT NULL,
    PRIMARY KEY (`fingerprint`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
