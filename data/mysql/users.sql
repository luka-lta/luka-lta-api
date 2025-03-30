CREATE TABLE `users`
(
    `user_id`     int          NOT NULL,
    `email`       varchar(100) NOT NULL,
    `username`    varchar(50)  NOT NULL,
    `password`    varchar(255) NOT NULL,
    `avatar_url`  text,
    `is_active`   tinyint(1)   NOT NULL DEFAULT '1',
    `last_active` datetime              DEFAULT NULL,
    `created_at`  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  datetime              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `users`
    ADD PRIMARY KEY (`user_id`),
    ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `users`
    MODIFY `user_id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `avatar_url`, `created_at`, `updated_at`)
VALUES (1, 'luka-lta', 'luka@luka-lta.dev', '$2y$10$/gORHZGQQuL/9S9oN9fbjem.oJMC4fTplBJR.9PjQdQ1dpTbc0fIS', NULL,
        '2025-01-23 10:59:37', NULL);
