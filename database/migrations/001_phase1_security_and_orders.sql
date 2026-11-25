-- Suwoda HerbHub V2 - Phase 1 Migration
-- Security tables (login throttling, password resets) and orders payment updates

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Login Attempts (for rate limiting / brute force protection)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL,
  `ip_hash` VARCHAR(64) NOT NULL,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  INDEX `idx_login_throttle` (`email`, `ip_hash`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Password Resets (token-based, single-use, 30m expiry)
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_token_hash` (`token_hash`),
  INDEX `idx_user_expires` (`user_id`, `expires_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Orders: Add payment_status, payment_method, stripe_session_id
ALTER TABLE `orders`
  ADD COLUMN `payment_status` ENUM('unpaid','paid','failed','refunded') NOT NULL DEFAULT 'unpaid' AFTER `total_price`,
  ADD COLUMN `payment_method` ENUM('card','cod','bank_transfer','payhere') NOT NULL DEFAULT 'card' AFTER `payment_status`,
  ADD COLUMN `stripe_session_id` VARCHAR(255) NULL AFTER `payment_method`,
  ADD INDEX `idx_orders_payment_status` (`payment_status`),
  ADD INDEX `idx_orders_stripe_session` (`stripe_session_id`);

-- 4. User: Add 'blocked' to status ENUM
ALTER TABLE `user`
  MODIFY COLUMN `status` ENUM('active','inactive','blocked') NOT NULL DEFAULT 'inactive';

SET FOREIGN_KEY_CHECKS = 1;
