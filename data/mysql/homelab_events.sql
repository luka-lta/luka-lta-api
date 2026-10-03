-- Unified, append-only timeline of everything that happened across the homelab
-- (container added/removed/restarted, alert raised/resolved, host went
-- offline/online, ...). Alerts/restarts/health checks stay in their own tables
-- for their specific detail columns; this table exists so "what happened last
-- night" is a single query instead of joining three tables by time window.
CREATE TABLE `homelab_events`
(
    `event_id`     bigint                                NOT NULL AUTO_INCREMENT,
    `type`         varchar(50)                           NOT NULL,
    `severity`     enum ('critical', 'warning', 'info')  NOT NULL DEFAULT 'info',
    `title`        varchar(150)                          NOT NULL,
    `description`  text                                  NOT NULL,
    `container_id` varchar(64)                           NULL     DEFAULT NULL,
    `host_id`      varchar(64)                           NULL     DEFAULT NULL,
    `metadata`     json                                  NULL     DEFAULT NULL,
    `occurred_at`  datetime                              NOT NULL,
    PRIMARY KEY (`event_id`),
    KEY `event_container` (`container_id`, `occurred_at`),
    KEY `event_host` (`host_id`, `occurred_at`),
    KEY `event_occurred` (`occurred_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
