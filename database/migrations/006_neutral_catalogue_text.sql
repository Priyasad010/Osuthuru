-- Suwoda HerbHub V2 - Fix: replace V1 catalogue text that made health claims
-- The new text only states what each product is and which ingredients the old
-- text listed. It also repairs the double-encoded bullet characters.
-- Rows are matched on the original wording, so edited descriptions are kept.

UPDATE `product` SET `description` = 'Neem Oil (Neem Thaila) is an Ayurvedic oil.'
WHERE `description` LIKE 'Neem Oil (Neem Thaila) is a powerful Ayurvedic oil%';

UPDATE `product` SET `description` = 'Pinda Oil (Pinda Thaila) is a blend of Ayurvedic oils.'
WHERE `description` LIKE 'Pinda oil (Pinda thaila) is a blend of Ayurvedic oils that soothe%';

UPDATE `product` SET `description` = 'Mahanarayana Oil is a classical Ayurvedic massage oil.'
WHERE `description` LIKE 'Mahanarayana Oil is a classical Ayurvedic massage oil used to%';

UPDATE `product` SET `description` = 'A herbal blend made from Ranawara flowers (Cassia auriculata) and green tea.'
WHERE `description` LIKE 'Made from Ranawara flowers%known for%';

UPDATE `product` SET `description` = 'A herbal drink made from Nil Katarolu flowers (Clitoria ternatea).'
WHERE `description` LIKE '%Made from Nil Katarolu flowers%packed with%';

UPDATE `product` SET `description` = 'A traditional Ayurvedic herbal tea made from Gotukola (Centella asiatica).'
WHERE `description` LIKE '%Made from Gotukola (Centella asiatica)%healing properties%';

UPDATE `product` SET `description` = 'A fluoride-free herbal toothpaste for children, made with ingredients such as clove, munamal and miswak.'
WHERE `description` LIKE 'Specially formulated for children, this gentle herbal toothpaste%';

UPDATE `product` SET `description` = 'A baby soap made with kohomba (neem), coconut milk and aloe vera.'
WHERE `description` LIKE 'A gentle baby soap with kohomba (neem)%';

UPDATE `product` SET `description` = 'A herbal cream made with cinnamon oil, menthol, camphor and beeswax. For external application.'
WHERE `description` LIKE 'Camphor Cream is a soothing herbal preparation%';

-- Sinhala descriptions, only where none exists
UPDATE `product` SET `description_si` = 'කොහොඹ තෙල් (Neem Thaila) ආයුර්වේද තෙලකි.'
WHERE `description_si` IS NULL AND `description` = 'Neem Oil (Neem Thaila) is an Ayurvedic oil.';

UPDATE `product` SET `description_si` = 'පිණ්ඩ තෛලය ආයුර්වේද තෙල් මිශ්‍රණයකි.'
WHERE `description_si` IS NULL AND `description` = 'Pinda Oil (Pinda Thaila) is a blend of Ayurvedic oils.';

UPDATE `product` SET `description_si` = 'මහානාරායන තෛලය සාම්ප්‍රදායික ආයුර්වේද සම්බාහන තෙලකි.'
WHERE `description_si` IS NULL AND `description` = 'Mahanarayana Oil is a classical Ayurvedic massage oil.';

UPDATE `product` SET `description_si` = 'රණවරා මල් (Cassia auriculata) සහ හරිත තේ වලින් සැකසූ ඖෂධීය මිශ්‍රණයකි.'
WHERE `description_si` IS NULL AND `description` = 'A herbal blend made from Ranawara flowers (Cassia auriculata) and green tea.';

UPDATE `product` SET `description_si` = 'නිල් කටරොළු මල් (Clitoria ternatea) වලින් සැකසූ ඖෂධීය පානයකි.'
WHERE `description_si` IS NULL AND `description` = 'A herbal drink made from Nil Katarolu flowers (Clitoria ternatea).';

UPDATE `product` SET `description_si` = 'ගොටුකොළ (Centella asiatica) වලින් සැකසූ සාම්ප්‍රදායික ආයුර්වේද තේ පානයකි.'
WHERE `description_si` IS NULL AND `description` = 'A traditional Ayurvedic herbal tea made from Gotukola (Centella asiatica).';

UPDATE `product` SET `description_si` = 'කරාබුනැටි, මූණමල් සහ මිස්වාක් වැනි අමුද්‍රව්‍ය අඩංගු, ෆ්ලෝරයිඩ් රහිත ළමා ඖෂධීය දන්තාලේපයකි.'
WHERE `description_si` IS NULL AND `description` = 'A fluoride-free herbal toothpaste for children, made with ingredients such as clove, munamal and miswak.';

UPDATE `product` SET `description_si` = 'කොහොඹ, පොල්කිරි සහ කෝමාරිකා අඩංගු ළදරු සබන් වර්ගයකි.'
WHERE `description_si` IS NULL AND `description` = 'A baby soap made with kohomba (neem), coconut milk and aloe vera.';

UPDATE `product` SET `description_si` = 'කුරුඳු තෙල්, මෙන්තෝල්, කපුරු සහ මීඉටි අඩංගු ඖෂධීය ආලේපනයකි. බාහිර ආලේපනය සඳහා පමණි.'
WHERE `description_si` IS NULL AND `description` = 'A herbal cream made with cinnamon oil, menthol, camphor and beeswax. For external application.';

UPDATE `category` SET `name` = 'Herbal Balms', `name_si` = 'ඖෂධීය බාම්'
WHERE `name` = 'Pain Relief Balms';
