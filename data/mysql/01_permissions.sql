CREATE TABLE `permissions`
(
    `id`          int          NOT NULL,
    `name`        varchar(100) NOT NULL,
    `description` varchar(255) NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;


INSERT INTO `permissions` (`id`, `name`, `description`)
VALUES (1, 'Create links', 'Create new links for LinkCollection'),
       (2, 'Delete Links', 'Delete links from LinkCollection'),
       (3, 'Edit Links', 'Edit Links from the LinkCollection'),
       (4, 'Read Links', 'Get all links from the LinkCollection');

ALTER TABLE `permissions`
    ADD PRIMARY KEY (`id`),
    ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `permissions`
    MODIFY `id` int NOT NULL AUTO_INCREMENT,
    AUTO_INCREMENT = 5;
