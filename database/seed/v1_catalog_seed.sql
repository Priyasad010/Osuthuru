-- Osuthuru - Demo Catalogue Seed (categories, products, discounts, blog)
-- Load after the migrations with:  docker compose exec web php bin/seed.php
-- Herbs and health concerns come from the migrations.
-- Product text states what an item is and contains. It makes no health claims;
-- anything more must be entered by an admin after practitioner review.
SET NAMES utf8mb4;

INSERT IGNORE INTO `category` (`category_id`, `name`, `name_si`, `image`) VALUES
(1, 'Herbal Oils', 'ඖෂධීය තෙල්', '/assets/images/catalog/categories/oils.jpg'),
(2, 'Ayurvedic Teas', 'ආයුර්වේද තේ', '/assets/images/catalog/categories/teas.jpg'),
(3, 'Herbal Powders', 'ඖෂධීය චූර්ණ සහ කුඩු', '/assets/images/catalog/categories/powders.jpg'),
(4, 'Soaps & Skincare', 'සබන් සහ සමේ සත්කාර', '/assets/images/catalog/categories/soaps.jpg'),
(5, 'Herbal Balms', 'ඖෂධීය බාම්', '/assets/images/catalog/categories/balms.jpg'),
(6, 'Wellness Syrups', 'ඖෂධීය පැනිය සහ සිරප්', '/assets/images/catalog/categories/syrups.jpg'),
(7, 'Hair Care', 'කෙස් සත්කාර', '/assets/images/catalog/categories/hair.jpg'),
(11, 'Baby Care', 'ළදරු සත්කාර', '/assets/images/catalog/categories/baby.jpg');

-- Names, sizes and pack facts come from the labels. Prices were looked up in
-- November 2026; see docs/PRICE_SOURCES.md.
INSERT IGNORE INTO `product` (`product_id`, `product_name`, `product_name_si`, `category_id`, `price`, `quantity`, `image`, `description`, `description_si`) VALUES
(1, 'Aliver Neem Oil 60ml', 'Aliver කොහොඹ තෙල් 60ml', 1, 2290.00, 118, '/assets/images/catalog/products/neem-oil.jpg', 'Cold-pressed, unrefined neem (Azadirachta indica) oil in a 60 ml dropper bottle.', 'සීතල ක්‍රමයෙන් මිරිකාගත්, පිරිපහදු නොකළ කොහොඹ (Azadirachta indica) තෙල්. බිංදු දැමිය හැකි 60 ml බෝතලයක්.'),
(2, 'Pinda Oil 180ml', 'පිණ්ඩ තෛලය 180ml', 1, 1800.00, 95, '/assets/images/catalog/products/pinda-oil.jpg', 'Pinda Thailaya, a traditional Ayurvedic oil, in a 180 ml bottle.', 'සාම්ප්‍රදායික ආයුර්වේද තෙලක් වන පිණ්ඩ තෛලය. 180 ml බෝතලයක්.'),
(12, 'Maha Narayana Oil', 'මහා නාරායන තෛලය', 1, 1600.00, 97, '/assets/images/catalog/products/mahanarayana-oil.jpg', 'Maha Narayana Thailaya, a classical Ayurvedic massage oil, made by Wickramarachchi Laboratories.', 'වික්‍රමආරච්චි ලැබොරටරීස් නිෂ්පාදනයක් වන, සාම්ප්‍රදායික ආයුර්වේද සම්බාහන තෙලක් වන මහා නාරායන තෛලය.'),
(15, 'Beam Ranawara (Senna) Tea - 20 bags', 'Beam රණවරා තේ - මලු 20', 2, 490.00, 150, '/assets/images/catalog/products/senna-tea.jpg', '20 tea bags of 2 g (40 g). Made from Ranawara flowers (Cassia auriculata) and green tea.', 'ග්‍රෑම් 2 බැගින් තේ මලු 20 (40 g). රණවරා මල් (Cassia auriculata) සහ හරිත තේ වලින් සැකසූ.'),
(16, 'Beam Butterfly Pea Flower Drink - 20 bags', 'Beam නිල් කටරොළු මල් පානය - මලු 20', 2, 450.00, 137, '/assets/images/catalog/products/nil-katarolu-drink.jpg', '20 tea bags of 1.5 g (30 g). Made from Nil Katarolu flowers (Clitoria ternatea).', 'ග්‍රෑම් 1.5 බැගින් තේ මලු 20 (30 g). නිල් කටරොළු මල් (Clitoria ternatea) වලින් සැකසූ.'),
(17, 'Link Natural Gotukola Tea - 25 bags', 'Link Natural ගොටුකොළ තේ - මලු 25', 2, 400.00, 96, '/assets/images/catalog/products/gotukola-tea.jpg', '25 herbal tea bags of Gotukola (Centella asiatica). Caffeine free.', 'ගොටුකොළ (Centella asiatica) ඖෂධීය තේ මලු 25. කැෆේන් රහිතයි.'),
(18, 'Siddhalepa Kekulu Toothpaste 40g', 'Siddhalepa කැකුළු දන්තාලේප 40g', 11, 180.00, 243, '/assets/images/catalog/products/kekulu-toothpaste.jpg', 'A herbal toothpaste for children, 40 g. Listed ingredients include munamal (Mimusops elengi), liquorice (Glycyrrhiza glabra) and cumin.', 'ළමුන් සඳහා වූ ඖෂධීය දන්තාලේපයක්, 40 g. සඳහන් අමුද්‍රව්‍ය අතර මූණමල් (Mimusops elengi), වැල්මී (Glycyrrhiza glabra) සහ සූදුරු වේ.'),
(19, 'Swadeshi Khomba Baby Milk Soap 60g', 'Swadeshi කොහොඹ බේබි කිරි සබන් 60g', 11, 130.00, 296, '/assets/images/catalog/products/kohomba-baby-soap.jpg', 'A baby soap with kohomba (neem), coconut milk and aloe vera, 60 g.', 'කොහොඹ, පොල්කිරි සහ කෝමාරිකා අඩංගු ළදරු සබන්, 60 g.'),
(20, 'Beam Camphor Balm 15g', 'Beam කැම්පර් බාම් 15g', 5, 200.00, 139, '/assets/images/catalog/products/camphor-balm.jpg', 'Sarsapathi oil, cinnamon oil, menthol and camphor in a beeswax base, 15 g. For external use only.', 'මීඉටි පදනමක සර්ෂප තෙල්, කුරුඳු තෙල්, මෙන්තෝල් සහ කපුරු අඩංගුයි, 15 g. බාහිර භාවිතය සඳහා පමණි.'),
(101, 'Healthy Harvest Heen Bovitiya Powder 100g', 'Healthy Harvest හීන් බෝවිටියා කුඩු 100g', 3, 1050.00, 50, '/assets/images/catalog/products/heenbovitiya-powder.jpg', 'Dehydrated Heen Bovitiya (Osbeckia octandra) powder, 100 g. Store in a cool, dry place.', 'විජලනය කළ හීන් බෝවිටියා (Osbeckia octandra) කුඩු, 100 g. සිසිල්, වියළි ස්ථානයක තබන්න.'),
(102, 'Healthy Harvest Yaki Naran Powder 100g', 'Healthy Harvest යකි නාරං කුඩු 100g', 3, 1050.00, 50, '/assets/images/catalog/products/yakinaran-powder.jpg', 'Dehydrated Yaki Naran (Atalantia ceylanica) powder, 100 g. Store in a cool, dry place.', 'විජලනය කළ යකි නාරං (Atalantia ceylanica) කුඩු, 100 g. සිසිල්, වියළි ස්ථානයක තබන්න.'),
(103, 'Healthy Harvest Gotukola Powder 100g', 'Healthy Harvest ගොටුකොළ කුඩු 100g', 3, 1050.00, 50, '/assets/images/catalog/products/gotukola-powder.jpg', 'Dehydrated Gotukola (Centella asiatica) powder, 100 g. Store in a cool, dry place.', 'විජලනය කළ ගොටුකොළ (Centella asiatica) කුඩු, 100 g. සිසිල්, වියළි ස්ථානයක තබන්න.'),
(104, 'Nature''s Secrets Herbal Hair Oil 100ml', 'Nature''s Secrets ඖෂධීය හිසකෙස් තෙල් 100ml', 7, 395.00, 50, '/assets/images/catalog/products/herbal-hair-oil.jpg', 'A herbal hair oil in a 100 ml bottle. For external use only.', '100 ml බෝතලයක ඖෂධීය හිසකෙස් තෙල්. බාහිර භාවිතය සඳහා පමණි.'),
(105, 'Nature''s Secrets Herbal Hair Tonic 100ml', 'Nature''s Secrets ඖෂධීය හෙයා ටොනික් 100ml', 7, 250.00, 50, '/assets/images/catalog/products/herbal-hair-tonic.jpg', 'A hair tonic with neem, fenugreek and amla, 100 ml. Labelled paraben free. For external use only.', 'කොහොඹ, උළුහාල් සහ නෙල්ලි අඩංගු හෙයා ටොනික්, 100 ml. පැරබෙන් රහිත ලෙස සඳහන්. බාහිර භාවිතය සඳහා පමණි.'),
(106, 'Nature''s Secrets Godapara Anti-Dandruff Shampoo 200ml', 'Nature''s Secrets ගොඩපර ඇන්ටි ඩැන්ඩ්‍රෆ් ෂැම්පු 200ml', 7, 760.00, 50, '/assets/images/catalog/products/godapara-shampoo.jpg', 'An anti-dandruff shampoo with godapara, 200 ml. For external use only.', 'ගොඩපර අඩංගු ඇන්ටි ඩැන්ඩ්‍රෆ් ෂැම්පු, 200 ml. බාහිර භාවිතය සඳහා පමණි.'),
(107, 'Herbal Blooms Limee Shampoo 80ml', 'Herbal Blooms ලයිමී ෂැම්පු 80ml', 7, 585.00, 50, '/assets/images/catalog/products/limee-shampoo.jpg', 'A lime and fenugreek shampoo, 80 ml. The pack states no SLES, fragrance or paraben. For external use only.', 'දෙහි සහ උළුහාල් ෂැම්පු, 80 ml. SLES, සුවඳකාරක සහ පැරබෙන් රහිත බව පැකට්ටුවේ සඳහන්. බාහිර භාවිතය සඳහා පමණි.'),
(108, 'Panda Baby Shampoo with Nelli and Neem 100ml', 'Panda බේබි ෂැම්පු - නෙල්ලි සහ කොහොඹ 100ml', 11, 320.00, 50, '/assets/images/catalog/products/panda-baby-shampoo.jpg', 'A baby shampoo with nelli and neem, 100 ml. The pack states no parabens and soap free.', 'නෙල්ලි සහ කොහොඹ අඩංගු ළදරු ෂැම්පු, 100 ml. පැරබෙන් සහ සබන් රහිත බව පැකට්ටුවේ සඳහන්.');

INSERT IGNORE INTO `product_herb` (`product_id`, `herb_id`) VALUES
(1, 1),
(17, 3),
(19, 1),
(19, 8),
(20, 10),
(101, 9),
(103, 3),
(105, 1),
(105, 4),
(108, 1),
(108, 4);

INSERT IGNORE INTO `discount` (`discount_id`, `product_id`, `discount_percentage`, `start_date`, `end_date`) VALUES
(2, 16, 10.00, '2025-09-13 00:00:00', '2025-09-13 23:59:59');

INSERT IGNORE INTO `blog` (`blog_id`, `title`, `image`, `content`, `published_date`) VALUES
(1, 'What Is Ayurveda?', '/assets/images/catalog/blog/ayurveda.jpg', 'Introduction\r\nAyurveda is one of the world’s oldest holistic systems. Originating in India and widely practised in Sri Lanka, Ayurveda emphasises balance between the body, mind and spirit. The word “Ayurveda” comes from the Sanskrit words “Ayur” (life) and “Veda” (knowledge or science), meaning the “Science of Life”.\r\n\r\nCore principles\r\nAyurveda describes five elements: Earth (Prithvi), Water (Jala), Fire (Teja), Air (Vayu) and Space (Akasha). In this tradition they combine to form three doshas: Vata, Pitta and Kapha. A person’s own combination of doshas is called their Prakriti.\r\n', '2025-07-21 12:30:25');

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

