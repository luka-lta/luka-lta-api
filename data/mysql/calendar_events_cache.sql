-- Normalized events from the last sync of each source. Fully replaced per
-- source on every sync (delete-then-insert) rather than diffed — ICS feeds
-- don't give us a cheap "what changed" signal, and the event volume is small.
CREATE TABLE `calendar_events_cache`
(
    `id`           bigint        NOT NULL AUTO_INCREMENT,
    `source_id`    int unsigned  NOT NULL,
    `external_uid` varchar(255)  NOT NULL,
    `title`        varchar(255)  NOT NULL,
    `starts_at`    datetime      NOT NULL,
    `ends_at`      datetime      NOT NULL,
    `is_all_day`   tinyint(1)    NOT NULL DEFAULT 0,
    `location`     varchar(255)  NULL     DEFAULT NULL,
    `description`  text          NULL     DEFAULT NULL,
    `attendees`    json          NULL     DEFAULT NULL,
    `url`          varchar(1024) NULL     DEFAULT NULL,
    `synced_at`    datetime      NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `calendar_events_source_uid` (`source_id`, `external_uid`),
    KEY `calendar_events_starts_at` (`starts_at`),
    CONSTRAINT `fk_calendar_event_source` FOREIGN KEY (`source_id`) REFERENCES `calendar_sources` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
