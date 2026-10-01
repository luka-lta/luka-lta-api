-- Topology support: physical/role classification on existing entities,
-- plus generic polymorphic nodes/edges for infrastructure that isn't a
-- Docker host or container (Internet, Cloudflare, manual annotations).

ALTER TABLE `homelab_hosts`
    ADD COLUMN `node_type` varchar(50) NOT NULL DEFAULT 'server' AFTER `name`;

ALTER TABLE `homelab_containers`
    ADD COLUMN `networks`  json         NULL DEFAULT NULL AFTER `environment`,
    ADD COLUMN `labels`    json         NULL DEFAULT NULL AFTER `networks`,
    ADD COLUMN `node_role` varchar(50)  NULL DEFAULT NULL AFTER `labels`;

CREATE TABLE `homelab_topology_nodes`
(
    `node_id`    varchar(64)                                      NOT NULL,
    `type`       varchar(50)                                      NOT NULL,
    `name`       varchar(100)                                     NOT NULL,
    `status`     enum ('healthy', 'warning', 'offline', 'unknown') NOT NULL DEFAULT 'unknown',
    `metadata`   json                                             NULL     DEFAULT NULL,
    `created_at` datetime                                         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime                                         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`node_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

CREATE TABLE `homelab_topology_edges`
(
    `edge_id`     char(36)                                                                     NOT NULL,
    `source_type` enum ('host', 'container', 'node')                                            NOT NULL,
    `source_id`   varchar(64)                                                                   NOT NULL,
    `target_type` enum ('host', 'container', 'node')                                            NOT NULL,
    `target_id`   varchar(64)                                                                   NOT NULL,
    `relation`    enum ('routes_to', 'depends_on', 'connects_to', 'hosted_on', 'exposes')       NOT NULL,
    `metadata`    json                                                                          NULL DEFAULT NULL,
    `created_at`  datetime                                                                      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`edge_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
