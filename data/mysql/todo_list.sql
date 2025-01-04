CREATE TABLE `todo_list`
(
    `todo_id`     int                                                NOT NULL,
    `owner_id`    int                                                NOT NULL,
    `title`       varchar(255)                                       NOT NULL,
    `description` text,
    `status`      enum ('open','in_progress','completed','archived') NOT NULL DEFAULT 'open',
    `priority`    enum ('low','medium','high')                       NOT NULL DEFAULT 'medium',
    `due_date`    date                                                        DEFAULT NULL,
    `created_at`  timestamp                                          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  timestamp                                          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `todo_list`
    ADD PRIMARY KEY (`todo_id`);

ALTER TABLE `todo_list`
    MODIFY `todo_id` int NOT NULL AUTO_INCREMENT;
COMMIT;
