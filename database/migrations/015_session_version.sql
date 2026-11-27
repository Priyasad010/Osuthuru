-- Raising an account's version signs out every device that holds an older one
-- (after a password change or reset, a block, or on request).
ALTER TABLE `user` ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE `admin` ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 1;
