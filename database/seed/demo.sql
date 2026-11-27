-- Osuthuru - DEMONSTRATION DATA. Never load this into a real shop.
--   docker compose exec web php bin/seed.php --demo
--
-- All health, safety and dosha text here is placeholder text marked DEMO or
-- SAMPLE. It is NOT medical information.
-- Safe to run again: rows are only added when missing.
SET NAMES utf8mb4;

-- Customers (password for all three: DemoPass123!)
INSERT IGNORE INTO `user` (`user_id`, `name`, `email`, `password`, `phone`, `address`, `status`, `created_at`) VALUES
(9001, 'Demo Nimali', 'nimali@demo.invalid', '$2y$10$JA6/Mkvx9o6aeFxuiuS/X.cJOLcgfNaVMYY9TYhuS3V9vcXFgLzX.', '0770000001', '12 Temple Road, Kandy', 'active', NOW() - INTERVAL 40 DAY),
(9002, 'Demo Kasun', 'kasun@demo.invalid', '$2y$10$JA6/Mkvx9o6aeFxuiuS/X.cJOLcgfNaVMYY9TYhuS3V9vcXFgLzX.', '0770000002', '45 Galle Road, Colombo', 'active', NOW() - INTERVAL 12 DAY),
(9003, 'Demo Fathima', 'fathima@demo.invalid', '$2y$10$JA6/Mkvx9o6aeFxuiuS/X.cJOLcgfNaVMYY9TYhuS3V9vcXFgLzX.', '0770000003', '8 Beach Road, Galle', 'active', NOW() - INTERVAL 3 DAY);

INSERT IGNORE INTO `user_category` (`userCategory_id`, `user_id`, `category_id`) VALUES
(9001, 9001, 2), (9002, 9002, 1), (9003, 9003, 11);

-- 90 orders spread over the last 45 days (the forecast model needs 30 complete days)
INSERT IGNORE INTO `orders` (`order_id`, `user_id`, `order_date`, `total_price`, `payment_status`, `payment_method`,
    `shipping_name`, `shipping_phone`, `shipping_address`, `district`, `delivery_fee`, `status`)
WITH RECURSIVE s(n) AS (SELECT 0 UNION ALL SELECT n + 1 FROM s WHERE n < 89)
SELECT 90000 + n,
       9001 + (n % 3),
       LEAST(NOW(), CURDATE() - INTERVAL (n % 45) DAY + INTERVAL (9 + (n % 8)) HOUR),
       0,
       IF(n < 4, 'unpaid', 'paid'),
       IF(n % 4 = 0, 'bank_transfer', 'cod'),
       'Demo customer', '0770000000', 'Demo address', ELT(1 + (n % 3), 'Colombo', 'Kandy', 'Galle'),
       250,
       CASE WHEN n < 2 THEN 'Pending' WHEN n < 4 THEN 'Confirmed' ELSE 'Delivered' END
FROM s;

INSERT IGNORE INTO `order_item` (`order_item_id`, `order_id`, `product_id`, `quantity`, `unit_price`, `subtotal`)
WITH RECURSIVE s(n) AS (SELECT 0 UNION ALL SELECT n + 1 FROM s WHERE n < 179),
picks AS (
    SELECT n,
           90000 + (n DIV 2) AS order_id,
           ELT(1 + ((n * 7 + (n DIV 5)) % 9), 1, 2, 12, 15, 16, 17, 18, 19, 20) AS product_id,
           1 + (n % 3) AS quantity
    FROM s
)
SELECT 90000 + picks.n, picks.order_id, picks.product_id, picks.quantity, p.price, p.price * picks.quantity
FROM picks
JOIN `product` p ON p.product_id = picks.product_id;

UPDATE `orders` o
SET o.total_price = o.delivery_fee + (SELECT COALESCE(SUM(i.subtotal), 0) FROM `order_item` i WHERE i.order_id = o.order_id)
WHERE o.order_id BETWEEN 90000 AND 90089;

INSERT IGNORE INTO `order_status_history` (`id`, `order_id`, `status`, `note`, `created_at`)
SELECT o.order_id, o.order_id, o.status, 'Demo order', o.order_date
FROM `orders` o WHERE o.order_id BETWEEN 90000 AND 90089;

-- Batches: one genuine, one expiring soon, one recalled (tokens are random)
INSERT IGNORE INTO `product_batch` (`batch_id`, `product_id`, `batch_code`, `manufactured_on`, `expires_on`, `source_location`, `supplier_name`, `public_token`, `status`, `units`) VALUES
(9001, 1, 'DEMO-NEEM-01', CURDATE() - INTERVAL 60 DAY, CURDATE() + INTERVAL 300 DAY, 'Demo garden, Anuradhapura', 'Demo supplier', LOWER(HEX(RANDOM_BYTES(16))), 'active', 100),
(9002, 17, 'DEMO-TEA-02', CURDATE() - INTERVAL 340 DAY, CURDATE() + INTERVAL 20 DAY, 'Demo garden, Matale', 'Demo supplier', LOWER(HEX(RANDOM_BYTES(16))), 'active', 40),
(9003, 20, 'DEMO-BALM-03', CURDATE() - INTERVAL 90 DAY, CURDATE() + INTERVAL 200 DAY, 'Demo workshop, Colombo', 'Demo supplier', LOWER(HEX(RANDOM_BYTES(16))), 'recalled', 25);

-- Placeholder herb text, only where none has been entered
UPDATE `herb` SET `uses` = 'DEMO TEXT - replace with practitioner-reviewed information.' WHERE `uses` IS NULL;
UPDATE `herb` SET `uses_si` = 'නියැදි පෙළක් - වෛද්‍යවරයෙකු සමාලෝචනය කළ තොරතුරු යොදන්න.' WHERE `uses_si` IS NULL;

-- Dosha quiz, profiles and product matches
UPDATE `quiz_setting` SET `published` = 1 WHERE `id` = 1;
UPDATE `dosha_profile` SET
    `description_en` = 'DEMO TEXT - an admin enters the reviewed description of this type here.',
    `description_si` = 'නියැදි පෙළක් - මෙම වර්ගයේ සමාලෝචනය කළ විස්තරය පරිපාලක මෙහි ඇතුළත් කරයි.'
WHERE `description_en` IS NULL;
INSERT IGNORE INTO `product_dosha` (`product_id`, `dosha`, `strength`) VALUES
(1, 'pitta', 2), (2, 'vata', 3), (12, 'vata', 2), (15, 'pitta', 1), (16, 'pitta', 3), (17, 'kapha', 2), (19, 'pitta', 1), (20, 'kapha', 3);

-- Publish the clearly marked SAMPLE safety entries so the checker can be shown
UPDATE `herb_safety` SET `published` = 1 WHERE `note_en` LIKE 'SAMPLE - not medical information%';

-- Coupon, seasonal pick, blog extras and reviews
INSERT IGNORE INTO `coupon` (`coupon_id`, `code`, `type`, `value`, `min_order`, `max_uses`, `starts_at`, `ends_at`, `active`) VALUES
(9001, 'DEMO10', 'percent', 10, 1000, 100, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 90 DAY, 1);

INSERT IGNORE INTO `seasonal_collection` (`id`, `name_en`, `name_si`, `start_mmdd`, `end_mmdd`, `concern_id`)
SELECT 9001, 'Demo seasonal picks', 'නියැදි කාලීන තේරීම්', '0101', '1231', concern_id FROM `health_concern` WHERE `slug` = 'immunity';

INSERT IGNORE INTO `blog_tag` (`tag_id`, `name_en`, `name_si`) VALUES (9001, 'Ayurveda basics', 'ආයුර්වේද මූලිකාංග');
INSERT IGNORE INTO `blog_tag_map` (`blog_id`, `tag_id`) SELECT blog_id, 9001 FROM `blog` WHERE `blog_id` = 1;
INSERT IGNORE INTO `blog_product` (`blog_id`, `product_id`) SELECT blog_id, 1 FROM `blog` WHERE `blog_id` = 1;

INSERT IGNORE INTO `review` (`review_id`, `user_id`, `product_id`, `rating`, `comment`, `review_date`)
SELECT 9000 + ROW_NUMBER() OVER (ORDER BY picked.user_id, picked.product_id), picked.user_id, picked.product_id, 4,
       'DEMO review: arrived well packed.', NOW() - INTERVAL 2 DAY
FROM (
    SELECT o.user_id, MIN(i.product_id) AS product_id
    FROM `orders` o JOIN `order_item` i ON i.order_id = o.order_id
    WHERE o.status = 'Delivered' AND o.order_id BETWEEN 90000 AND 90089
    GROUP BY o.user_id
) AS picked;
