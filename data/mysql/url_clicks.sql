CREATE TABLE `url_clicks`
(
    `click_id`   int      NOT NULL,
    `click_tag`  char(16) NOT NULL,
    `url`        text     NOT NULL,
    `clicked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` varchar(45)       DEFAULT NULL,
    `user_agent` text,
    `referrer`   text
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `url_clicks`
    ADD PRIMARY KEY (`click_id`),
    ADD KEY `click_tag` (`click_tag`);

ALTER TABLE `url_clicks`
    MODIFY `click_id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `url_clicks`
    ADD CONSTRAINT `click_tag` FOREIGN KEY (`click_tag`) REFERENCES `link_collection` (`click_tag`) ON DELETE RESTRICT ON UPDATE RESTRICT;
