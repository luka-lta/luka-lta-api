ALTER TABLE `api_keys`
    ADD COLUMN `label`       varchar(100) NOT NULL DEFAULT '' AFTER `key_id`,
    ADD COLUMN `key_suffix`  char(4)      NOT NULL DEFAULT '' AFTER `api_key`;

INSERT INTO `permissions` (`permission_id`, `permission_name`, `permission_description`)
VALUES (100, 'Ingest Homelab Metrics', 'Push homelab host and container metrics via API key');
