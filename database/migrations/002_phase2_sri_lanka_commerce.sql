-- Suwoda HerbHub V2 - Phase 2 Migration: Commerce for Sri Lanka
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Delivery Zones (by Sri Lankan district)
CREATE TABLE IF NOT EXISTS `delivery_zone` (
  `zone_id` INT AUTO_INCREMENT PRIMARY KEY,
  `district` VARCHAR(100) NOT NULL UNIQUE,
  `fee` DECIMAL(10,2) NOT NULL DEFAULT 350.00,
  `free_over` DECIMAL(10,2) NOT NULL DEFAULT 5000.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed 25 Sri Lankan Districts
INSERT IGNORE INTO `delivery_zone` (`district`, `fee`, `free_over`) VALUES
('Colombo', 250.00, 4000.00),
('Gampaha', 300.00, 4500.00),
('Kalutara', 350.00, 5000.00),
('Kandy', 400.00, 5500.00),
('Matale', 450.00, 6000.00),
('Nuwara Eliya', 500.00, 6500.00),
('Galle', 400.00, 5500.00),
('Matara', 450.00, 6000.00),
('Hambantota', 500.00, 6500.00),
('Jaffna', 600.00, 7500.00),
('Kilinochchi', 600.00, 7500.00),
('Mannar', 600.00, 7500.00),
('Vavuniya', 550.00, 7000.00),
('Mullaitivu', 600.00, 7500.00),
('Batticaloa', 550.00, 7000.00),
('Ampara', 550.00, 7000.00),
('Trincomalee', 550.00, 7000.00),
('Kurunegala', 400.00, 5500.00),
('Puttalam', 450.00, 6000.00),
('Anuradhapura', 500.00, 6500.00),
('Polonnaruwa', 500.00, 6500.00),
('Badulla', 500.00, 6500.00),
('Moneragala', 550.00, 7000.00),
('Ratnapura', 450.00, 6000.00),
('Kegalle', 400.00, 5500.00);

-- 2. Coupons & Redemptions
CREATE TABLE IF NOT EXISTS `coupon` (
  `coupon_id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value` DECIMAL(10,2) NOT NULL,
  `min_order` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_uses` INT NOT NULL DEFAULT 100,
  `used_count` INT NOT NULL DEFAULT 0,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupon_redemption` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `coupon_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `order_id` INT NOT NULL,
  `redeemed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_coupon_user` (`coupon_id`, `user_id`),
  CONSTRAINT `fk_redemption_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupon` (`coupon_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_redemption_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_redemption_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Orders: Shipping snapshot, delivery fee, discount, coupon, extended status
ALTER TABLE `orders`
  ADD COLUMN `shipping_name` VARCHAR(120) NULL AFTER `stripe_session_id`,
  ADD COLUMN `shipping_phone` VARCHAR(30) NULL AFTER `shipping_name`,
  ADD COLUMN `shipping_address` TEXT NULL AFTER `shipping_phone`,
  ADD COLUMN `district` VARCHAR(100) NULL AFTER `shipping_address`,
  ADD COLUMN `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `district`,
  ADD COLUMN `discount_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `delivery_fee`,
  ADD COLUMN `coupon_id` INT NULL AFTER `discount_total`,
  MODIFY COLUMN `status` ENUM('Pending','Confirmed','Packed','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  ADD CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupon` (`coupon_id`) ON DELETE SET NULL;

-- 4. Order Status History (timeline)
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `status` ENUM('Pending','Confirmed','Packed','Out for Delivery','Delivered','Cancelled') NOT NULL,
  `note` VARCHAR(255) NULL,
  `changed_by_admin_id` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_osh_order` (`order_id`),
  CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_osh_admin` FOREIGN KEY (`changed_by_admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bank Transfer Slips
CREATE TABLE IF NOT EXISTS `bank_slip` (
  `slip_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `status` ENUM('submitted','verified','rejected') NOT NULL DEFAULT 'submitted',
  `reviewed_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_bank_slip_order` (`order_id`),
  CONSTRAINT `fk_bank_slip_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bank_slip_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Wishlist & Back-in-Stock Alerts
CREATE TABLE IF NOT EXISTS `wishlist` (
  `user_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `product_id`),
  CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_alert` (
  `alert_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notified_at` DATETIME NULL,
  INDEX `idx_stock_alert_product_pending` (`product_id`, `notified_at`),
  CONSTRAINT `fk_stock_alert_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_alert_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Discount start_date & end_date become DATETIME for flash sales
ALTER TABLE `discount`
  MODIFY COLUMN `start_date` DATETIME NULL,
  MODIFY COLUMN `end_date` DATETIME NULL;

SET FOREIGN_KEY_CHECKS = 1;
