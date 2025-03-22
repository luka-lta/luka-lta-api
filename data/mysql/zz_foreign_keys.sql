ALTER TABLE `api_keys`
    ADD CONSTRAINT `created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `preview_access_tokens`
    ADD CONSTRAINT `created_by_preview` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;