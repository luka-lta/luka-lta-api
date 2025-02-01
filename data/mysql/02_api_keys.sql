CREATE TABLE `api_keys`
(
    `key_id`         int          NOT NULL,
    `origin`     varchar(200) NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int          NOT NULL,
    `expires_at` datetime     NULL     DEFAULT NULL,
    `api_key`    text         NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `api_keys`
    ADD PRIMARY KEY (`key_id`),
    ADD KEY `created_by` (`created_by`);

ALTER TABLE `api_keys`
    MODIFY `key_id` int NOT NULL AUTO_INCREMENT;

