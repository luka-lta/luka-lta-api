-- Real-world health checks (e.g. unbound/dig output) and log lines can exceed
-- varchar(255); widen to TEXT to match homelab_container_logs.message.
ALTER TABLE `homelab_container_health_checks`
    MODIFY COLUMN `message` TEXT NOT NULL;
