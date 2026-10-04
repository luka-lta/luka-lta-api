-- Portfolio-Projekte. Zentrale Quelle der Wahrheit; das oeffentliche Frontend
-- hardcodet keine Projekte mehr. `status` ist beschreibend (Badge), `is_visible`
-- ist der harte Schalter fuer die oeffentliche Sichtbarkeit — bewusst getrennt,
-- damit ein Projekt z.B. als 'archived' markiert aber weiter sichtbar sein kann.
CREATE TABLE `projects`
(
    `project_id`        char(36)                                                            NOT NULL,
    `name`              varchar(100)                                                        NOT NULL,
    `slug`              varchar(100)                                                        NOT NULL,
    `short_description` varchar(255)                                                            NULL DEFAULT NULL,
    `description`       text                                                                    NULL,
    `status`            enum ('development', 'beta', 'active', 'paused', 'archived')         NOT NULL DEFAULT 'development',
    `is_visible`        tinyint(1)                                                          NOT NULL DEFAULT 1,
    `category`          varchar(50)                                                             NULL DEFAULT NULL,
    `tech_stack`        json                                                                    NULL DEFAULT NULL,
    `website_url`       varchar(1024)                                                           NULL DEFAULT NULL,
    `live_label`        varchar(100)                                                            NULL DEFAULT NULL,
    `repository_url`    varchar(1024)                                                           NULL DEFAULT NULL,
    `repository_owner`  varchar(100)                                                            NULL DEFAULT NULL,
    `repository_name`   varchar(100)                                                            NULL DEFAULT NULL,
    `demo_url`          varchar(1024)                                                           NULL DEFAULT NULL,
    `documentation_url` varchar(1024)                                                           NULL DEFAULT NULL,
    `role`              varchar(100)                                                            NULL DEFAULT NULL,
    `project_year`      smallint                                                                NULL DEFAULT NULL,
    `is_client_project` tinyint(1)                                                          NOT NULL DEFAULT 0,
    `metadata`          json                                                                    NULL DEFAULT NULL,
    `sort_order`        int                                                                 NOT NULL DEFAULT 0,
    `created_at`        datetime                                                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        datetime                                                            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`project_id`),
    UNIQUE KEY `uq_project_slug` (`slug`),
    KEY `project_sort` (`sort_order`),
    KEY `project_visibility` (`is_visible`, `status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
