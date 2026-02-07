CREATE TABLE `active_sessions` (
                                   `session_id` varchar(255) NOT NULL,
                                   `site_id` int NOT NULL,
                                   `user_id` varchar(255) NOT NULL,
                                   `start_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                   `last_activity` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `active_sessions`
    ADD PRIMARY KEY (`session_id`);