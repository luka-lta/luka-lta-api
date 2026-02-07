CREATE TABLE `tracking_user_alias` (
  `id` int NOT NULL,
  `site_id` int NOT NULL,
  `anonymous_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


ALTER TABLE `tracking_user_alias`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tracking_user_alias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
