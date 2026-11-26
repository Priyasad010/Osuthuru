-- Suwoda HerbHub V2 - Fix: remember whether an order has taken stock
-- Cancelling an order must return stock only if stock was actually deducted.

ALTER TABLE `orders`
  ADD COLUMN `stock_deducted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;

-- Backfill existing orders: COD takes stock at placement, others when paid.
UPDATE `orders`
SET `stock_deducted` = 1
WHERE `status` <> 'Cancelled'
  AND (`payment_method` = 'cod' OR `payment_status` = 'paid');
