CREATE TABLE `api_key_permissions`
(
    `id`          int NOT NULL AUTO_INCREMENT,
    `api_key_id`  int NOT NULL,
    `permission_id` int NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `api_key_permission_unique` (`api_key_id`, `permission_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
