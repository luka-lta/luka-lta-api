ALTER TABLE `api_keys`
    ADD CONSTRAINT `created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;