-- Suwoda HerbHub V2 - Phase 3 Migration: Herbal Knowledge and Bilingual Search
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Add Sinhala columns to product, category, herb, blog
ALTER TABLE `product`
  ADD COLUMN `product_name_si` VARCHAR(255) NULL AFTER `product_name`,
  ADD COLUMN `description_si` TEXT NULL AFTER `description`;

ALTER TABLE `category`
  ADD COLUMN `name_si` VARCHAR(255) NULL AFTER `name`;

ALTER TABLE `herb`
  ADD COLUMN `name_si` VARCHAR(150) NULL AFTER `name`,
  ADD COLUMN `uses_si` TEXT NULL AFTER `uses`;

ALTER TABLE `blog`
  ADD COLUMN `title_si` VARCHAR(255) NULL AFTER `title`,
  ADD COLUMN `content_si` TEXT NULL AFTER `content`;

-- 2. Herb ↔ Product association table
CREATE TABLE IF NOT EXISTS `product_herb` (
  `product_id` INT NOT NULL,
  `herb_id` INT NOT NULL,
  PRIMARY KEY (`product_id`, `herb_id`),
  CONSTRAINT `fk_ph_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ph_herb` FOREIGN KEY (`herb_id`) REFERENCES `herb` (`herb_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Health Concerns
CREATE TABLE IF NOT EXISTS `health_concern` (
  `concern_id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `name_en` VARCHAR(150) NOT NULL,
  `name_si` VARCHAR(150) NOT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'bi-heart-pulse',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Herb ↔ Health Concern association table
CREATE TABLE IF NOT EXISTS `herb_concern` (
  `herb_id` INT NOT NULL,
  `concern_id` INT NOT NULL,
  PRIMARY KEY (`herb_id`, `concern_id`),
  CONSTRAINT `fk_hc_herb` FOREIGN KEY (`herb_id`) REFERENCES `herb` (`herb_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hc_concern` FOREIGN KEY (`concern_id`) REFERENCES `health_concern` (`concern_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Fulltext search indexes
ALTER TABLE `product`
  ADD FULLTEXT KEY `ft_product` (`product_name`, `description`);

ALTER TABLE `herb`
  ADD FULLTEXT KEY `ft_herb` (`name`, `scientific_name`, `uses`);

-- 6. Seed Health Concerns
INSERT INTO `health_concern` (`concern_id`, `slug`, `name_en`, `name_si`, `icon`) VALUES
(1, 'cold-cough', 'Cold & Cough', 'සෙම්ප්‍රතිශ්‍යාව සහ කැස්ස', 'bi-thermometer-half'),
(2, 'digestion', 'Digestion & Gut Health', 'ආහාර ජීර්ණය සහ උදරාබාධ', 'bi-shield-check'),
(3, 'skin-care', 'Skin Care & Infections', 'සමේ සත්කාර සහ ආසාදන', 'bi-flower1'),
(4, 'hair-care', 'Hair Care & Scalp', 'කෙස් කළඹ සහ හිස්කබල', 'bi-scissors'),
(5, 'stress-sleep', 'Stress & Sleep', 'මානසික ආතතිය සහ නින්ද', 'bi-moon-stars'),
(6, 'joint-comfort', 'Joint Comfort & Muscle Pain', 'සන්ධි සහ මාංශපේශී වේදනා', 'bi-bandaid'),
(7, 'immunity', 'Immunity & Vitality', 'ප්‍රතිශක්තිය සහ ප්‍රාණවත් බව', 'bi-capsule')
ON DUPLICATE KEY UPDATE `name_en`=VALUES(`name_en`), `name_si`=VALUES(`name_si`);

-- 7. Seed Herbs (including Neem/Kohomba, Turmeric/Kaha, Gotukola, Nelli, Aralu etc.)
INSERT INTO `herb` (`herb_id`, `name`, `name_si`, `scientific_name`, `uses`, `uses_si`, `image`) VALUES
(1, 'Neem (Kohomba)', 'කොහොඹ', 'Azadirachta indica', 'Natural blood purifier, treats acne and skin infections, anti-fungal, strengthens immunity and promotes oral health.', 'ස්වාභාවික රුධිර පිරිසිදුකාරකයකි, කුරුලෑ සහ සමේ රෝග සමනය කරයි, ප්‍රතිශක්තිය වඩවයි.', 'BinKohomba.jpg'),
(2, 'Turmeric (Kaha)', 'කහ', 'Curcuma longa', 'Powerful natural anti-inflammatory and antioxidant. Boosts immunity, brightens skin tone, and supports joint comfort.', 'ප්‍රබල ප්‍රතිප්‍රදාහජනක සහ ප්‍රතිඔක්සිකාරක ගුණයෙන් යුක්තය. ප්‍රතිශක්තිය සහ සමේ පැහැපත් බව වැඩි කරයි.', 'turmeric.jpg'),
(3, 'Gotu Kola', 'ගොටුකොළ', 'Centella asiatica', 'Known as the brain tonic. Enhances memory and focus, relieves anxiety, and promotes wound healing and skin vitality.', 'මතක ශක්තිය සහ ධාරණ ශක්තිය වර්ධනය කරයි. මානසික ආතතිය අවම කර ආයුෂ වඩවයි.', 'Gotukola.png'),
(4, 'Amla (Nelli)', 'නෙල්ලි', 'Phyllanthus emblica', 'One of the richest natural sources of Vitamin C. Boosts immune system, aids digestion, slows aging, and nourishes hair.', 'ස්වභාවික විටමින් C බහුලව අඩංගුය. ප්‍රතිශක්තිය වඩවයි, ආහාර ජීර්ණය පහසු කර කෙස් වර්ධනය කරයි.', 'Amla.png'),
(5, 'Aralu', 'අරළු', 'Terminalia chebula', 'King of medicines in Ayurveda. Relieves constipation, detoxifies the digestive tract, and supports natural metabolism.', 'ආයුර්වේදයේ ප්‍රධාන ඖෂධයකි. මළබද්ධය සමනය කර ශරීරයේ විෂ ඉවත් කරයි.', 'Aralu.JPG'),
(6, 'Coriander (Kottamalli)', 'කොත්තමල්ලි', 'Coriandrum sativum', 'Traditional household remedy for colds, flu, and fever. Soothes indigestion and relieves bloating and abdominal gas.', 'සෙම්ප්‍රතිශ්‍යාව, උණ සහ සෙම සමනය සඳහා සාම්ප්‍රදායික පානයකි. ජීර්ණය පහසු කරයි.', 'koththamalli.jpg'),
(7, 'Ashwagandha (Amukkara)', 'අමුක්කරා', 'Withania somnifera', 'Ancient rejuvenator. Reduces stress and cortisol levels, enhances vitality, stamina, and supports sound sleep.', 'මානසික ආතතිය දුරු කරයි, ශරීරයේ ශක්තිය සහ ප්‍රාණවත් බව වර්ධනය කර සුවදායක නින්දක් ලබාදෙයි.', 'ashwagandha.jpg'),
(8, 'Aloe Vera (Komarika)', 'කෝමාරිකා', 'Aloe barbadensis miller', 'Cools the body, soothes burns, deeply hydrates skin, supports digestive harmony, and nourishes hair follicles.', 'ශරීරය සිසිල් කරයි, සමේ තුවාල සුවපත් කරයි, කෙස් කළඹ නිරෝගී කරයි.', 'Aloe Vera.jpg'),
(9, 'Bovitiya', 'බෝවිටියා', 'Osbeckia octandra', 'Renowned Sri Lankan endemic herb supporting liver health, detoxifying organs, and managing healthy blood sugar.', 'අක්මාවේ සෞඛ්‍යයට සහ සෙංගමාලය සමනය සඳහා විශේෂිත ශ්‍රී ලාංකීය ඖෂධීය පැළෑටියකි.', 'Bovitiya.jpg'),
(10, 'Ceylon Cinnamon (Kurundu)', 'කුරුඳු', 'Cinnamomum verum', 'True Ceylon cinnamon. Regulates blood sugar metabolism, fights bad cholesterol, and promotes cardiovascular health.', 'රුධිරයේ සීනි මට්ටම පාලනය කරයි, හෘද සෞඛ්‍යය සුරකියි, ප්‍රණීත සුවඳ සහ රසය ගෙනදෙයි.', 'dawal-kurundu.jpg')
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`), `name_si`=VALUES(`name_si`), `scientific_name`=VALUES(`scientific_name`),
  `uses`=VALUES(`uses`), `uses_si`=VALUES(`uses_si`), `image`=VALUES(`image`);

-- 8. Map Herbs to Health Concerns
INSERT IGNORE INTO `herb_concern` (`herb_id`, `concern_id`) VALUES
(1, 3), -- Neem -> Skin Care
(1, 4), -- Neem -> Hair Care
(1, 7), -- Neem -> Immunity
(2, 3), -- Turmeric -> Skin Care
(2, 6), -- Turmeric -> Joint Comfort
(2, 7), -- Turmeric -> Immunity
(3, 5), -- Gotu Kola -> Stress & Sleep
(3, 7), -- Gotu Kola -> Immunity
(4, 2), -- Amla -> Digestion
(4, 4), -- Amla -> Hair Care
(4, 7), -- Amla -> Immunity
(5, 2), -- Aralu -> Digestion
(6, 1), -- Coriander -> Cold & Cough
(6, 2), -- Coriander -> Digestion
(7, 5), -- Ashwagandha -> Stress & Sleep
(7, 7), -- Ashwagandha -> Immunity
(8, 3), -- Aloe Vera -> Skin Care
(8, 4), -- Aloe Vera -> Hair Care
(9, 2), -- Bovitiya -> Digestion
(9, 7), -- Bovitiya -> Immunity
(10, 2), -- Cinnamon -> Digestion
(10, 7); -- Cinnamon -> Immunity

-- 9. Update Sinhala names for Categories
UPDATE `category` SET `name_si` = 'ආයුර්වේද තේ' WHERE `name` LIKE '%Tea%';
UPDATE `category` SET `name_si` = 'ළදරු සත්කාර' WHERE `name` LIKE '%Baby%';
UPDATE `category` SET `name_si` = 'සත්කාර ආලේපන සහ බාම්' WHERE `name` LIKE '%Balm%';
UPDATE `category` SET `name_si` = 'කෙස් සත්කාර' WHERE `name` LIKE '%Hair%';
UPDATE `category` SET `name_si` = 'ඖෂධීය චූර්ණ සහ කුඩු' WHERE `name` LIKE '%Powder%';
UPDATE `category` SET `name_si` = 'ඖෂධීය තෙල්' WHERE `name` LIKE '%Oil%';
UPDATE `category` SET `name_si` = 'සබන් සහ සමේ සත්කාර' WHERE `name` LIKE '%Skin%' OR `name` LIKE '%Soap%';
UPDATE `category` SET `name_si` = 'ඖෂධීය පැනිය සහ සිරප්' WHERE `name` LIKE '%Syrup%';
UPDATE `category` SET `name_si` = 'මුඛ සත්කාර' WHERE `name` LIKE '%Oral%';

-- 10. Update Sinhala names for Products & Map to Herbs
UPDATE `product` SET `product_name_si` = 'සුවෝදා පින්ද තෙල්', `description_si` = 'සන්ධි වේදනා සහ දැවිල්ල සමනය කරන සාම්ප්‍රදායික ආයුර්වේද තෙලකි.' WHERE `product_name` LIKE '%Pinda%';
UPDATE `product` SET `product_name_si` = 'සුවෝදා කොහොඹ තෙල්', `description_si` = 'සමේ රෝග, කුරුලෑ සහ හිස්හොරි සඳහා ස්වාභාවික කොහොඹ තෙල්.' WHERE `product_name` LIKE '%Neem%';
UPDATE `product` SET `product_name_si` = 'සුවෝදා මහානාරායන තෙල්', `description_si` = 'මාංශපේශී සහ වාත රෝග සඳහා ගුණදායක පාරම්පරික ඖෂධීය තෙලකි.' WHERE `product_name` LIKE '%Mahanarayana%';
UPDATE `product` SET `product_name_si` = 'ගොටුකොළ සිරප්', `description_si` = 'මතක ශක්තිය සහ මොළයේ ක්‍රියාකාරිත්වය වර්ධනය කරන ස්වාභාවික සිරප් වර්ගයකි.' WHERE `product_name` LIKE '%Gotukola%';
UPDATE `product` SET `product_name_si` = 'කොහොඹ ළදරු සබන්', `description_si` = 'බිළිඳුන්ගේ සංවේදී සමට ආරක්ෂාව සලසන ස්වභාවික කොහොඹ සබන්.' WHERE `product_name` LIKE '%Khomba%' OR `product_name` LIKE '%Baby Soap%';

-- Link existing products to herbs
INSERT IGNORE INTO `product_herb` (`product_id`, `herb_id`)
SELECT p.product_id, 1 FROM `product` p WHERE p.product_name LIKE '%Neem%' OR p.product_name LIKE '%Khomba%';

INSERT IGNORE INTO `product_herb` (`product_id`, `herb_id`)
SELECT p.product_id, 3 FROM `product` p WHERE p.product_name LIKE '%Gotukola%';

INSERT IGNORE INTO `product_herb` (`product_id`, `herb_id`)
SELECT p.product_id, 5 FROM `product` p WHERE p.product_name LIKE '%Pinda%' OR p.product_name LIKE '%Aralu%';

SET FOREIGN_KEY_CHECKS = 1;
