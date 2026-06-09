CREATE TABLE `blog_tags`
(
    `tag_id`     int          NOT NULL AUTO_INCREMENT,
    `name`       varchar(50)  NOT NULL,
    `slug`       varchar(50)  NOT NULL,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tag_id`),
    UNIQUE KEY `uq_blog_tags_slug` (`slug`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
