CREATE TABLE `blog_posts`
(
    `blog_id`    char(36)     NOT NULL,
    `user_id`    int          NOT NULL,
    `title`      varchar(100) NOT NULL,
    `content`    text         NOT NULL,
    `created_at` datetime     NOT NULL,
    `updated_at` datetime DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `blog_posts`
    ADD PRIMARY KEY (`blog_id`),
    ADD KEY `blog_user` (`user_id`);

