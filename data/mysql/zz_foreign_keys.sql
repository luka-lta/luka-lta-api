ALTER TABLE `api_keys`
    ADD CONSTRAINT `created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `preview_access_tokens`
    ADD CONSTRAINT `created_by_preview` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `blog_posts`
    ADD CONSTRAINT `blog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;

ALTER TABLE `blog_post_tags`
    ADD CONSTRAINT `fk_bpt_blog_id` FOREIGN KEY (`blog_id`) REFERENCES `blog_posts` (`blog_id`) ON DELETE CASCADE;

ALTER TABLE `blog_post_tags`
    ADD CONSTRAINT `fk_bpt_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `blog_tags` (`tag_id`) ON DELETE CASCADE;

ALTER TABLE `homelab_host_metrics`
    ADD CONSTRAINT `fk_host_metric_host` FOREIGN KEY (`host_id`) REFERENCES `homelab_hosts` (`host_id`) ON DELETE CASCADE;

ALTER TABLE `homelab_containers`
    ADD CONSTRAINT `fk_container_host` FOREIGN KEY (`host_id`) REFERENCES `homelab_hosts` (`host_id`) ON DELETE CASCADE;

ALTER TABLE `homelab_container_metrics`
    ADD CONSTRAINT `fk_container_metric_container` FOREIGN KEY (`container_id`) REFERENCES `homelab_containers` (`container_id`) ON DELETE CASCADE;

ALTER TABLE `homelab_container_restarts`
    ADD CONSTRAINT `fk_container_restart_container` FOREIGN KEY (`container_id`) REFERENCES `homelab_containers` (`container_id`) ON DELETE CASCADE;

ALTER TABLE `homelab_container_health_checks`
    ADD CONSTRAINT `fk_container_health_check_container` FOREIGN KEY (`container_id`) REFERENCES `homelab_containers` (`container_id`) ON DELETE CASCADE;

ALTER TABLE `homelab_container_logs`
    ADD CONSTRAINT `fk_container_log_container` FOREIGN KEY (`container_id`) REFERENCES `homelab_containers` (`container_id`) ON DELETE CASCADE;

-- SET NULL (not CASCADE): an event must survive the container/host it was about
-- being removed — "container X was removed" needs to stay readable afterwards.
ALTER TABLE `homelab_events`
    ADD CONSTRAINT `fk_event_container` FOREIGN KEY (`container_id`) REFERENCES `homelab_containers` (`container_id`) ON DELETE SET NULL;

ALTER TABLE `homelab_events`
    ADD CONSTRAINT `fk_event_host` FOREIGN KEY (`host_id`) REFERENCES `homelab_hosts` (`host_id`) ON DELETE SET NULL;

ALTER TABLE `notification_reads`
    ADD CONSTRAINT `fk_notification_read_alert` FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`alert_id`) ON DELETE CASCADE;

ALTER TABLE `notification_reads`
    ADD CONSTRAINT `fk_notification_read_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;