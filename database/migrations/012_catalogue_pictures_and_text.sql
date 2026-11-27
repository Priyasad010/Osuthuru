-- Catalogue pictures, product names, pack facts and prices.
-- Rows are changed only while they still hold the picture path imported from V1
-- ("../..."), so anything an admin has since replaced is left alone.
SET NAMES utf8mb4;

UPDATE `product` SET `product_name` = 'Aliver Neem Oil 60ml', `product_name_si` = 'Aliver කොහොඹ තෙල් 60ml', `price` = 2290.00,
    `description` = 'Cold-pressed, unrefined neem (Azadirachta indica) oil in a 60 ml dropper bottle.',
    `description_si` = 'සීතල ක්‍රමයෙන් මිරිකාගත්, පිරිපහදු නොකළ කොහොඹ (Azadirachta indica) තෙල්. බිංදු දැමිය හැකි 60 ml බෝතලයක්.',
    `image` = '/assets/images/catalog/products/neem-oil.jpg'
WHERE `product_id` = 1 AND `image` LIKE '../%';
UPDATE `product` SET `product_name` = 'Pinda Oil 180ml', `product_name_si` = 'පිණ්ඩ තෛලය 180ml', `price` = 1800.00,
    `description` = 'Pinda Thailaya, a traditional Ayurvedic oil, in a 180 ml bottle.',
    `description_si` = 'සාම්ප්‍රදායික ආයුර්වේද තෙලක් වන පිණ්ඩ තෛලය. 180 ml බෝතලයක්.',
    `image` = '/assets/images/catalog/products/pinda-oil.jpg'
WHERE `product_id` = 2 AND `image` LIKE '../%';
UPDATE `product` SET `product_name` = 'Maha Narayana Oil', `product_name_si` = 'මහා නාරායන තෛලය', `price` = 1600.00,
    `description` = 'Maha Narayana Thailaya, a classical Ayurvedic massage oil, made by Wickramarachchi Laboratories.',
    `description_si` = 'වික්‍රමආරච්චි ලැබොරටරීස් නිෂ්පාදනයක් වන, සාම්ප්‍රදායික ආයුර්වේද සම්බාහන තෙලක් වන මහා නාරායන තෛලය.',
    `image` = '/assets/images/catalog/products/mahanarayana-oil.jpg'
WHERE `product_id` = 12 AND `image` LIKE '../%';
UPDATE `product` SET `product_name` = 'Beam Ranawara (Senna) Tea - 20 bags', `product_name_si` = 'Beam රණවරා තේ - මලු 20', `price` = 490.00,
    `description` = '20 tea bags of 2 g (40 g). Made from Ranawara flowers (Cassia auriculata) and green tea.',
    `description_si` = 'ග්‍රෑම් 2 බැගින් තේ මලු 20 (40 g). රණවරා මල් (Cassia auriculata) සහ හරිත තේ වලින් සැකසූ.',
    `image` = '/assets/images/catalog/products/senna-tea.jpg'
WHERE `product_id` = 15 AND `image` LIKE '../%';
UPDATE `product` SET `product_name` = 'Beam Butterfly Pea Flower Drink - 20 bags', `product_name_si` = 'Beam නිල් කටරොළු මල් පානය - මලු 20', `price` = 450.00,
    `description` = '20 tea bags of 1.5 g (30 g). Made from Nil Katarolu flowers (Clitoria ternatea).',
    `description_si` = 'ග්‍රෑම් 1.5 බැගින් තේ මලු 20 (30 g). නිල් කටරොළු මල් (Clitoria ternatea) වලින් සැකසූ.',
    `image` = '/assets/images/catalog/products/nil-katarolu-drink.jpg'
WHERE `product_id` = 16 AND `image` LIKE '../%';
UPDATE `product` SET `product_name` = 'Link Natural Gotukola Tea - 25 bags', `product_name_si` = 'Link Natural ගොටුකොළ තේ - මලු 25', `price` = 400.00,
    `description` = '25 herbal tea bags of Gotukola (Centella asiatica). Caffeine free.',
    `description_si` = 'ගොටුකොළ (Centella asiatica) ඖෂධීය තේ මලු 25. කැෆේන් රහිතයි.',
    `image` = '/assets/images/catalog/products/gotukola-tea.jpg'
WHERE `product_id` = 17 AND `image` LIKE '../%';
UPDATE `product` SET `product_name` = 'Siddhalepa Kekulu Toothpaste 40g', `product_name_si` = 'Siddhalepa කැකුළු දන්තාලේප 40g', `price` = 180.00,
    `description` = 'A herbal toothpaste for children, 40 g. Listed ingredients include munamal (Mimusops elengi), liquorice (Glycyrrhiza glabra) and cumin.',
    `description_si` = 'ළමුන් සඳහා වූ ඖෂධීය දන්තාලේපයක්, 40 g. සඳහන් අමුද්‍රව්‍ය අතර මූණමල් (Mimusops elengi), වැල්මී (Glycyrrhiza glabra) සහ සූදුරු වේ.',
    `image` = '/assets/images/catalog/products/kekulu-toothpaste.jpg'
WHERE `product_id` = 18 AND `image` LIKE '../%';
UPDATE `product` SET `product_name` = 'Swadeshi Khomba Baby Milk Soap 60g', `product_name_si` = 'Swadeshi කොහොඹ බේබි කිරි සබන් 60g', `price` = 130.00,
    `description` = 'A baby soap with kohomba (neem), coconut milk and aloe vera, 60 g.',
    `description_si` = 'කොහොඹ, පොල්කිරි සහ කෝමාරිකා අඩංගු ළදරු සබන්, 60 g.',
    `image` = '/assets/images/catalog/products/kohomba-baby-soap.jpg'
WHERE `product_id` = 19 AND `image` LIKE '../%';
UPDATE `product` SET `product_name` = 'Beam Camphor Balm 15g', `product_name_si` = 'Beam කැම්පර් බාම් 15g', `price` = 200.00,
    `description` = 'Sarsapathi oil, cinnamon oil, menthol and camphor in a beeswax base, 15 g. For external use only.',
    `description_si` = 'මීඉටි පදනමක සර්ෂප තෙල්, කුරුඳු තෙල්, මෙන්තෝල් සහ කපුරු අඩංගුයි, 15 g. බාහිර භාවිතය සඳහා පමණි.',
    `image` = '/assets/images/catalog/products/camphor-balm.jpg'
WHERE `product_id` = 20 AND `image` LIKE '../%';
UPDATE `category` SET `image` = '/assets/images/catalog/categories/oils.jpg' WHERE `category_id` = 1 AND (`image` IS NULL OR `image` LIKE '../%');
UPDATE `category` SET `image` = '/assets/images/catalog/categories/teas.jpg' WHERE `category_id` = 2 AND (`image` IS NULL OR `image` LIKE '../%');
UPDATE `category` SET `image` = '/assets/images/catalog/categories/powders.jpg' WHERE `category_id` = 3 AND (`image` IS NULL OR `image` LIKE '../%');
UPDATE `category` SET `image` = '/assets/images/catalog/categories/soaps.jpg' WHERE `category_id` = 4 AND (`image` IS NULL OR `image` LIKE '../%');
UPDATE `category` SET `image` = '/assets/images/catalog/categories/balms.jpg' WHERE `category_id` = 5 AND (`image` IS NULL OR `image` LIKE '../%');
UPDATE `category` SET `image` = '/assets/images/catalog/categories/syrups.jpg' WHERE `category_id` = 6 AND (`image` IS NULL OR `image` LIKE '../%');
UPDATE `category` SET `image` = '/assets/images/catalog/categories/hair.jpg' WHERE `category_id` = 7 AND (`image` IS NULL OR `image` LIKE '../%');
UPDATE `category` SET `image` = '/assets/images/catalog/categories/baby.jpg' WHERE `category_id` = 11 AND (`image` IS NULL OR `image` LIKE '../%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/neem.jpg' WHERE `scientific_name` = 'Azadirachta indica' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/turmeric.jpg' WHERE `scientific_name` = 'Curcuma longa' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/gotukola.jpg' WHERE `scientific_name` = 'Centella asiatica' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/amla.jpg' WHERE `scientific_name` = 'Phyllanthus emblica' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/aralu.jpg' WHERE `scientific_name` = 'Terminalia chebula' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/coriander.jpg' WHERE `scientific_name` = 'Coriandrum sativum' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/ashwagandha.jpg' WHERE `scientific_name` = 'Withania somnifera' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/aloe-vera.jpg' WHERE `scientific_name` = 'Aloe barbadensis miller' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/bovitiya.jpg' WHERE `scientific_name` = 'Osbeckia octandra' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `herb` SET `image` = '/assets/images/catalog/herbs/cinnamon.jpg' WHERE `scientific_name` = 'Cinnamomum verum' AND (`image` IS NULL OR `image` NOT LIKE '/%');
UPDATE `blog` SET `image` = '/assets/images/catalog/blog/ayurveda.jpg' WHERE `blog_id` = 1 AND `image` LIKE '../%';
-- A discount imported with no duration
DELETE FROM `discount` WHERE `discount_id` = 2 AND `start_date` = `end_date`;
