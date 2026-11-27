-- Shop details an administrator enters (bank account, contact details) and
-- the messages sent from the contact page.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `site_setting` (
    `name` VARCHAR(50) NOT NULL PRIMARY KEY,
    `value` VARCHAR(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_message` (
    `message_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `message` VARCHAR(2000) NOT NULL,
    `handled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_contact_open` (`handled`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
