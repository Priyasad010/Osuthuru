-- Suwoda HerbHub V2 - Phase 7A: admin analytics support and Sri Lanka time

-- The application now works in Asia/Colombo (UTC+05:30). DATETIME values that
-- were filled from the server clock were stored in UTC, so move them across.
-- (TIMESTAMP columns are converted by MySQL itself.)
UPDATE `orders` SET `order_date` = `order_date` + INTERVAL 330 MINUTE WHERE `order_date` IS NOT NULL;
UPDATE `password_resets` SET `expires_at` = `expires_at` + INTERVAL 330 MINUTE,
  `used_at` = `used_at` + INTERVAL 330 MINUTE;
UPDATE `stock_alert` SET `notified_at` = `notified_at` + INTERVAL 330 MINUTE WHERE `notified_at` IS NOT NULL;

-- Needed for the "new customers" figure
ALTER TABLE `user` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `orders`
  ADD INDEX `idx_orders_date` (`order_date`),
  ADD INDEX `idx_orders_status` (`status`);
