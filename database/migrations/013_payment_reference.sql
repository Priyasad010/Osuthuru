-- The payment gateway's own number for a payment, needed to find or refund it
ALTER TABLE `orders` ADD COLUMN `payment_reference` VARCHAR(64) NULL AFTER `stripe_session_id`;
