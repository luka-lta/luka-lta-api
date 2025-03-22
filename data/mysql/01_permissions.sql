CREATE TABLE `permissions`
(
    `permission_id`          int          NOT NULL,
    `permission_name`        varchar(100) NOT NULL,
    `permission_description` varchar(255) NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;


INSERT INTO `permissions` (`permission_id`, `permission_name`, `permission_description`)
VALUES (1, 'Create links', 'Create new links for LinkCollection'),
       (2, 'Delete Links', 'Delete links from LinkCollection'),
       (3, 'Edit Links', 'Edit Links from the LinkCollection'),
       (4, 'Read Links', 'Get all links from the LinkCollection'),
       (5, 'Read Clicks', 'Get all Clicks from the LinkCollection'),
       (6, 'Create Api keys', 'Create new Api keys for Api access'),
       (7, 'Read Api keys', 'Read Api keys'),
       (8, 'Read Permissions', 'Read the permissions');

ALTER TABLE `permissions`
    ADD PRIMARY KEY (`permission_id`),
    ADD UNIQUE KEY `name` (`permission_name`);

ALTER TABLE `permissions`
    MODIFY `permission_id` int NOT NULL AUTO_INCREMENT,
    AUTO_INCREMENT = 5;
