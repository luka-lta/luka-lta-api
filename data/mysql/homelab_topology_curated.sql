-- Kuratierte Topologie: Kanten werden nicht mehr aus Labels/Netzen berechnet, sondern
-- verbinden ausschließlich gepflegte Knoten. Ein Knoten kann auf einen Host oder einen
-- Service (Compose-Projekt + -Service, stabil über Redeploys) verweisen und übernimmt
-- dann dessen Live-Status. Änderungen kommen als Proposal (z. B. von Claude per MCP)
-- und werden im Dashboard geprüft und übernommen.

-- 1. Stabiler Service-Schlüssel für Container (Docker-IDs ändern sich bei jedem Recreate)
ALTER TABLE `homelab_containers`
    ADD COLUMN `service_key` varchar(191) NULL DEFAULT NULL AFTER `name`,
    ADD KEY `container_service_key` (`host_id`, `service_key`);

UPDATE `homelab_containers`
SET `service_key` = IF(
    JSON_UNQUOTE(JSON_EXTRACT(`labels`, '$."com.docker.compose.project"')) IS NOT NULL
        AND JSON_UNQUOTE(JSON_EXTRACT(`labels`, '$."com.docker.compose.service"')) IS NOT NULL,
    CONCAT(
        JSON_UNQUOTE(JSON_EXTRACT(`labels`, '$."com.docker.compose.project"')),
        '::',
        JSON_UNQUOTE(JSON_EXTRACT(`labels`, '$."com.docker.compose.service"'))
    ),
    `name`
);

ALTER TABLE `homelab_containers`
    MODIFY COLUMN `service_key` varchar(191) NOT NULL;

-- 2. Knoten können auf Host oder Service verweisen
ALTER TABLE `homelab_topology_nodes`
    ADD COLUMN `ref_type`        enum ('none', 'host', 'service') NOT NULL DEFAULT 'none' AFTER `name`,
    ADD COLUMN `ref_host_id`     varchar(64)                      NULL     DEFAULT NULL AFTER `ref_type`,
    ADD COLUMN `ref_service_key` varchar(191)                     NULL     DEFAULT NULL AFTER `ref_host_id`,
    ADD KEY `topology_node_ref` (`ref_type`, `ref_host_id`, `ref_service_key`);

-- 3. Bisherige manuelle Pflege als Referenz-Knoten übernehmen:
--    Hosts mit geändertem Typ oder in manuellen Kanten, Container mit Rolle oder in manuellen Kanten.
INSERT INTO `homelab_topology_nodes` (`node_id`, `type`, `name`, `status`, `ref_type`, `ref_host_id`)
SELECT UUID(), h.`node_type`, h.`name`, 'unknown', 'host', h.`host_id`
FROM `homelab_hosts` h
WHERE h.`node_type` <> 'server'
   OR h.`host_id` IN (SELECT e.`source_id` FROM `homelab_topology_edges` e WHERE e.`source_type` = 'host')
   OR h.`host_id` IN (SELECT e.`target_id` FROM `homelab_topology_edges` e WHERE e.`target_type` = 'host');

INSERT INTO `homelab_topology_nodes`
    (`node_id`, `type`, `name`, `status`, `ref_type`, `ref_host_id`, `ref_service_key`)
SELECT UUID(), COALESCE(c.`node_role`, 'container'), c.`name`, 'unknown', 'service', c.`host_id`, c.`service_key`
FROM `homelab_containers` c
WHERE c.`node_role` IS NOT NULL
   OR c.`container_id` IN (SELECT e.`source_id` FROM `homelab_topology_edges` e WHERE e.`source_type` = 'container')
   OR c.`container_id` IN (SELECT e.`target_id` FROM `homelab_topology_edges` e WHERE e.`target_type` = 'container');

-- 4. Kanten auf Knoten-zu-Knoten umstellen
ALTER TABLE `homelab_topology_edges`
    ADD COLUMN `source_node_id` varchar(64) NULL DEFAULT NULL AFTER `edge_id`,
    ADD COLUMN `target_node_id` varchar(64) NULL DEFAULT NULL AFTER `source_node_id`;

UPDATE `homelab_topology_edges` e
SET e.`source_node_id` = CASE e.`source_type`
        WHEN 'node' THEN e.`source_id`
        WHEN 'host' THEN (
            SELECT n.`node_id` FROM `homelab_topology_nodes` n
            WHERE n.`ref_type` = 'host' AND n.`ref_host_id` = e.`source_id`
            LIMIT 1
        )
        WHEN 'container' THEN (
            SELECT n.`node_id` FROM `homelab_topology_nodes` n
            JOIN `homelab_containers` c
                ON n.`ref_type` = 'service' AND n.`ref_host_id` = c.`host_id` AND n.`ref_service_key` = c.`service_key`
            WHERE c.`container_id` = e.`source_id`
            LIMIT 1
        )
    END,
    e.`target_node_id` = CASE e.`target_type`
        WHEN 'node' THEN e.`target_id`
        WHEN 'host' THEN (
            SELECT n.`node_id` FROM `homelab_topology_nodes` n
            WHERE n.`ref_type` = 'host' AND n.`ref_host_id` = e.`target_id`
            LIMIT 1
        )
        WHEN 'container' THEN (
            SELECT n.`node_id` FROM `homelab_topology_nodes` n
            JOIN `homelab_containers` c
                ON n.`ref_type` = 'service' AND n.`ref_host_id` = c.`host_id` AND n.`ref_service_key` = c.`service_key`
            WHERE c.`container_id` = e.`target_id`
            LIMIT 1
        )
    END;

-- Kanten, deren Endpunkt nicht mehr existiert, waren schon vorher verwaist
DELETE FROM `homelab_topology_edges`
WHERE `source_node_id` IS NULL
   OR `target_node_id` IS NULL
   OR `source_node_id` NOT IN (SELECT n.`node_id` FROM `homelab_topology_nodes` n)
   OR `target_node_id` NOT IN (SELECT n.`node_id` FROM `homelab_topology_nodes` n);

ALTER TABLE `homelab_topology_edges`
    DROP COLUMN `source_type`,
    DROP COLUMN `source_id`,
    DROP COLUMN `target_type`,
    DROP COLUMN `target_id`,
    MODIFY COLUMN `source_node_id` varchar(64) NOT NULL,
    MODIFY COLUMN `target_node_id` varchar(64) NOT NULL,
    ADD CONSTRAINT `fk_topology_edge_source` FOREIGN KEY (`source_node_id`)
        REFERENCES `homelab_topology_nodes` (`node_id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_topology_edge_target` FOREIGN KEY (`target_node_id`)
        REFERENCES `homelab_topology_nodes` (`node_id`) ON DELETE CASCADE;

-- 5. Rolle/Typ leben jetzt am kuratierten Knoten
ALTER TABLE `homelab_containers` DROP COLUMN `node_role`;
ALTER TABLE `homelab_hosts` DROP COLUMN `node_type`;

-- 6. Proposals: Änderungsvorschläge, die erst nach Review übernommen werden
CREATE TABLE `homelab_topology_proposals`
(
    `proposal_id` char(36)                                               NOT NULL,
    `title`       varchar(150)                                           NOT NULL,
    `summary`     text                                                   NULL     DEFAULT NULL,
    `status`      enum ('draft', 'applied', 'rejected', 'superseded')   NOT NULL DEFAULT 'draft',
    `created_by`  varchar(100)                                           NOT NULL,
    `created_at`  datetime                                               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at` datetime                                               NULL     DEFAULT NULL,
    PRIMARY KEY (`proposal_id`),
    KEY `topology_proposal_status` (`status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

CREATE TABLE `homelab_topology_proposal_items`
(
    `item_id`     char(36)                                                                         NOT NULL,
    `proposal_id` char(36)                                                                         NOT NULL,
    `position`    int                                                                              NOT NULL,
    `operation`   enum ('add_node', 'update_node', 'remove_node', 'add_edge', 'remove_edge')      NOT NULL,
    `payload`     json                                                                             NOT NULL,
    `reasoning`   text                                                                             NOT NULL,
    `evidence`    json                                                                             NULL     DEFAULT NULL,
    `status`      enum ('pending', 'applied', 'rejected', 'failed')                               NOT NULL DEFAULT 'pending',
    `failure`     varchar(255)                                                                     NULL     DEFAULT NULL,
    PRIMARY KEY (`item_id`),
    KEY `topology_proposal_item_proposal` (`proposal_id`, `position`),
    CONSTRAINT `fk_topology_proposal_item_proposal` FOREIGN KEY (`proposal_id`)
        REFERENCES `homelab_topology_proposals` (`proposal_id`) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

-- 7. Permission für den MCP-Endpoint
INSERT INTO `permissions` (`permission_id`, `permission_name`, `permission_description`)
VALUES (101, 'Propose Homelab Topology', 'Read homelab inventory and submit topology proposals via MCP');
