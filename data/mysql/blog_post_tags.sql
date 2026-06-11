CREATE TABLE `blog_post_tags`
(
    `post_tag_id` int      NOT NULL AUTO_INCREMENT,
    `blog_id`     char(36) NOT NULL,
    `tag_id`      int      NOT NULL,
    PRIMARY KEY (`post_tag_id`),
    UNIQUE KEY `uq_blog_post_tags` (`blog_id`, `tag_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
