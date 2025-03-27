CREATE TABLE `link_collection`
(
    `link_id`        int         NOT NULL,
    `click_tag`      char(16)    NOT NULL,
    `displayname`    varchar(30) NOT NULL,
    `description`    text,
    `url`            text        NOT NULL,
    `is_active`      tinyint(1)  NOT NULL DEFAULT '0',
    `created_at`     datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     timestamp   NULL     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `icon_name`      varchar(50)          DEFAULT NULL,
    `display_order`  int         NOT NULL DEFAULT '0',
    `deactivated`    tinyint(1)  NOT NULL DEFAULT '0',
    `deactivated_at` datetime             DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `link_collection`
    ADD PRIMARY KEY (`link_id`),
    ADD UNIQUE KEY `click_tag_2` (`click_tag`),
    ADD KEY `click_tag` (`click_tag`);

ALTER TABLE `link_collection`
    MODIFY `link_id` int NOT NULL AUTO_INCREMENT;
