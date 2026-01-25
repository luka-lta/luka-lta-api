CREATE TABLE `events` (
                          `event_id` int NOT NULL,
                          `site_id` int NOT NULL,
                          `occurred_on` datetime NOT NULL,
                          `session_id` varchar(255) NOT NULL,
                          `user_id` varchar(255) NOT NULL,
                          `hostname` varchar(255) DEFAULT NULL,
                          `pathname` varchar(255) DEFAULT NULL,
                          `url_parameters` json NOT NULL,
                          `page_title` varchar(255) DEFAULT NULL,
                          `referrer` varchar(255) DEFAULT NULL,
                          `channel` varchar(255) DEFAULT NULL,
                          `browser` varchar(255) NOT NULL,
                          `browser_version` varchar(10) NOT NULL,
                          `os` varchar(255) NOT NULL,
                          `os_version` varchar(10) NOT NULL,
                          `language` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
                          `country` char(2) DEFAULT NULL,
                          `region` varchar(30) DEFAULT NULL,
                          `city` varchar(255) DEFAULT NULL,
                          `lat` float DEFAULT NULL,
                          `lon` float DEFAULT NULL,
                          `screen_width` int DEFAULT NULL,
                          `screen_height` int DEFAULT NULL,
                          `device_type` varchar(255) NOT NULL,
                          `type` enum('pageview','custom_event','performance','outbound','error') NOT NULL DEFAULT 'pageview',
                          `event_name` varchar(50) DEFAULT NULL,
                          `props` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `events`
    MODIFY `event_id` int NOT NULL AUTO_INCREMENT;
