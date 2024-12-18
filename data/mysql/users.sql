CREATE TABLE `users`
(
    `user_id`    int          NOT NULL,
    `email`      varchar(100) NOT NULL,
    `password`   VARCHAR(255)         NOT NULL,
    `avatar_url` text,
    `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime     NULL ON UPDATE current_timestamp
);

ALTER TABLE `users`
    ADD PRIMARY KEY (`user_id`);

ALTER TABLE `users`
    MODIFY `user_id` int NOT NULL AUTO_INCREMENT;
