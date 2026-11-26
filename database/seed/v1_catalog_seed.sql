-- Suwoda HerbHub V2 - Demo Catalogue Seed (categories, products, discounts, blog)
-- Load after the migrations with:  docker compose exec web php bin/seed.php
-- Herbs and health concerns come from the migrations.
-- Product text states what an item is and contains. It makes no health claims;
-- anything more must be entered by an admin after practitioner review.
SET NAMES utf8mb4;

INSERT IGNORE INTO `category` (`category_id`, `name`, `name_si`, `image`) VALUES
(1, 'Herbal Oils', 'ඖෂධීය තෙල්', '../assets/images/herbal_oils.jpg'),
(2, 'Ayurvedic Teas', 'ආයුර්වේද තේ', '../assets/images/ayurvedic tea.jpg'),
(3, 'Herbal Powders', 'ඖෂධීය චූර්ණ සහ කුඩු', '../assets/images/herbal powders.jpg'),
(4, 'Soaps & Skincare', 'සබන් සහ සමේ සත්කාර', '../assets/images/soap&skin.jpg'),
(5, 'Herbal Balms', 'ඖෂධීය බාම්', '../assets/images/balms.jpg'),
(6, 'Wellness Syrups', 'ඖෂධීය පැනිය සහ සිරප්', '../assets/images/syrup.jpg'),
(7, 'Hair Care', 'කෙස් සත්කාර', '../assets/images/hair care.jpg'),
(11, 'Baby Care', 'ළදරු සත්කාර', '../uploads/categories/1753353235_baby_care.jpg');

INSERT IGNORE INTO `product` (`product_id`, `product_name`, `product_name_si`, `category_id`, `price`, `quantity`, `image`, `description`, `description_si`) VALUES
(1, 'Neem Oil', 'සුවෝදා කොහොඹ තෙල්', 1, 1850.00, 118, '../uploads/products/1753084677_NeemOil.png', 'Neem Oil (Neem Thaila) is an Ayurvedic oil.', 'කොහොඹ තෙල් (Neem Thaila) ආයුර්වේද තෙලකි.'),
(2, 'Pinda Oil', 'සුවෝදා පින්ද තෙල්', 1, 1900.00, 95, '../uploads/products/1753083984_PindaOil.png', 'Pinda Oil (Pinda Thaila) is a blend of Ayurvedic oils.', 'පිණ්ඩ තෛලය ආයුර්වේද තෙල් මිශ්‍රණයකි.'),
(12, 'Mahanarayana Oil', 'සුවෝදා මහානාරායන තෙල්', 1, 1600.00, 97, '../uploads/products/1753085109_MahanarayanaOil.jpg', 'Mahanarayana Oil is a classical Ayurvedic massage oil.', 'මහානාරායන තෛලය සාම්ප්‍රදායික ආයුර්වේද සම්බාහන තෙලකි.'),
(15, 'Senna Tea', NULL, 2, 450.00, 150, '../uploads/products/Senna-Tea-570x570.jpg', 'A herbal blend made from Ranawara flowers (Cassia auriculata) and green tea.', 'රණවරා මල් (Cassia auriculata) සහ හරිත තේ වලින් සැකසූ ඖෂධීය මිශ්‍රණයකි.'),
(16, 'Nil Katarolu Flowers Drink', NULL, 2, 450.00, 137, '../uploads/products/Nilkatarolu-570x570.jpg', 'A herbal drink made from Nil Katarolu flowers (Clitoria ternatea).', 'නිල් කටරොළු මල් (Clitoria ternatea) වලින් සැකසූ ඖෂධීය පානයකි.'),
(17, 'Natural Gotukola Tea', 'ස්වාභාවික ගොටුකොළ තේ', 2, 400.00, 96, '../uploads/products/Gotukola.jpg', 'A traditional Ayurvedic herbal tea made from Gotukola (Centella asiatica).', 'ගොටුකොළ (Centella asiatica) වලින් සැකසූ සාම්ප්‍රදායික ආයුර්වේද තේ පානයකි.'),
(18, 'Kekulu Toothpaste', NULL, 11, 200.00, 243, '../uploads/products/kekulu-toothpaste.png', 'A fluoride-free herbal toothpaste for children, made with ingredients such as clove, munamal and miswak.', 'කරාබුනැටි, මූණමල් සහ මිස්වාක් වැනි අමුද්‍රව්‍ය අඩංගු, ෆ්ලෝරයිඩ් රහිත ළමා ඖෂධීය දන්තාලේපයකි.'),
(19, 'Kohomba Baby Soap', 'කොහොඹ ළදරු සබන්', 11, 320.00, 296, '../uploads/products/Khomba-Baby-Soap-Milk.png', 'A baby soap made with kohomba (neem), coconut milk and aloe vera.', 'කොහොඹ, පොල්කිරි සහ කෝමාරිකා අඩංගු ළදරු සබන් වර්ගයකි.'),
(20, 'Camphor Balms', NULL, 5, 270.00, 139, '../uploads/products/Camphor-Cream-570x570.png', 'A herbal cream made with cinnamon oil, menthol, camphor and beeswax. For external application.', 'කුරුඳු තෙල්, මෙන්තෝල්, කපුරු සහ මීඉටි අඩංගු ඖෂධීය ආලේපනයකි. බාහිර ආලේපනය සඳහා පමණි.');

INSERT IGNORE INTO `product_herb` (`product_id`, `herb_id`) VALUES
(1, 1),
(17, 3);

INSERT IGNORE INTO `discount` (`discount_id`, `product_id`, `discount_percentage`, `start_date`, `end_date`) VALUES
(2, 16, 10.00, '2025-09-13 00:00:00', '2025-09-13 23:59:59');

INSERT IGNORE INTO `blog` (`blog_id`, `title`, `image`, `content`, `published_date`) VALUES
(1, 'What Is Ayurveda?', '../assets/images/uploads/Blogs1753081225_Blog1.jpg', 'Introduction\r\nAyurveda is one of the world’s oldest holistic systems. Originating in India and widely practised in Sri Lanka, Ayurveda emphasises balance between the body, mind and spirit. The word “Ayurveda” comes from the Sanskrit words “Ayur” (life) and “Veda” (knowledge or science), meaning the “Science of Life”.\r\n\r\nCore principles\r\nAyurveda describes five elements: Earth (Prithvi), Water (Jala), Fire (Teja), Air (Vayu) and Space (Akasha). In this tradition they combine to form three doshas: Vata, Pitta and Kapha. A person’s own combination of doshas is called their Prakriti.\r\n', '2025-07-21 12:30:25');

-- SAMPLE safety entries to show how the admin screen works.
-- They are NOT medical information and stay unpublished (hidden from customers).
INSERT IGNORE INTO `herb_safety` (`herb_id`, `flag_id`, `severity`, `note_en`, `note_si`, `source_note`, `published`)
SELECT h.herb_id, f.flag_id, sample.severity,
       'SAMPLE - not medical information. Replace with practitioner-reviewed text.',
       'නියැදියකි - වෛද්‍ය තොරතුරු නොවේ. සමාලෝචනය කළ පෙළක් යොදන්න.',
       'SAMPLE - no source', 0
FROM (
    SELECT 'Azadirachta indica' AS herb, 'pregnant' AS flag, 'avoid' AS severity
    UNION ALL SELECT 'Centella asiatica', 'child_under_12', 'caution'
    UNION ALL SELECT 'Curcuma longa', 'blood_thinners', 'info'
) AS sample
JOIN `herb` h ON h.scientific_name = CONVERT(sample.herb USING utf8mb4) COLLATE utf8mb4_general_ci
JOIN `safety_flag` f ON f.code = CONVERT(sample.flag USING utf8mb4) COLLATE utf8mb4_unicode_ci;

