-- Zuordnung Projekt <-> Tag. Beim Loeschen eines Projekts verschwindet nur die
-- Zuordnung, der Tag bleibt im Dictionary erhalten — genau das macht ihn
-- wiederverwendbar.
CREATE TABLE `project_tag_assignments`
(
    `assignment_id` int      NOT NULL AUTO_INCREMENT,
    `project_id`    char(36) NOT NULL,
    `tag_id`        int      NOT NULL,
    PRIMARY KEY (`assignment_id`),
    UNIQUE KEY `uq_project_tag_assignment` (`project_id`, `tag_id`),
    KEY `project_tag_reverse` (`tag_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
