-- Suwoda HerbHub V2 - Fix: remove health claims that migration 003 hard-coded
-- Health text must come from admin-entered data (see AGENTS.md).
-- Every statement matches the exact seeded value, so text an admin has since
-- edited is left untouched.

-- 1. Herb uses (English and Sinhala are cleared separately)
UPDATE `herb` SET `uses` = NULL WHERE `uses` IN (
  'Natural blood purifier, treats acne and skin infections, anti-fungal, strengthens immunity and promotes oral health.',
  'Powerful natural anti-inflammatory and antioxidant. Boosts immunity, brightens skin tone, and supports joint comfort.',
  'Known as the brain tonic. Enhances memory and focus, relieves anxiety, and promotes wound healing and skin vitality.',
  'One of the richest natural sources of Vitamin C. Boosts immune system, aids digestion, slows aging, and nourishes hair.',
  'King of medicines in Ayurveda. Relieves constipation, detoxifies the digestive tract, and supports natural metabolism.',
  'Traditional household remedy for colds, flu, and fever. Soothes indigestion and relieves bloating and abdominal gas.',
  'Ancient rejuvenator. Reduces stress and cortisol levels, enhances vitality, stamina, and supports sound sleep.',
  'Cools the body, soothes burns, deeply hydrates skin, supports digestive harmony, and nourishes hair follicles.',
  'Renowned Sri Lankan endemic herb supporting liver health, detoxifying organs, and managing healthy blood sugar.',
  'True Ceylon cinnamon. Regulates blood sugar metabolism, fights bad cholesterol, and promotes cardiovascular health.'
);

UPDATE `herb` SET `uses_si` = NULL WHERE `uses_si` IN (
  'ස්වාභාවික රුධිර පිරිසිදුකාරකයකි, කුරුලෑ සහ සමේ රෝග සමනය කරයි, ප්‍රතිශක්තිය වඩවයි.',
  'ප්‍රබල ප්‍රතිප්‍රදාහජනක සහ ප්‍රතිඔක්සිකාරක ගුණයෙන් යුක්තය. ප්‍රතිශක්තිය සහ සමේ පැහැපත් බව වැඩි කරයි.',
  'මතක ශක්තිය සහ ධාරණ ශක්තිය වර්ධනය කරයි. මානසික ආතතිය අවම කර ආයුෂ වඩවයි.',
  'ස්වභාවික විටමින් C බහුලව අඩංගුය. ප්‍රතිශක්තිය වඩවයි, ආහාර ජීර්ණය පහසු කර කෙස් වර්ධනය කරයි.',
  'ආයුර්වේදයේ ප්‍රධාන ඖෂධයකි. මළබද්ධය සමනය කර ශරීරයේ විෂ ඉවත් කරයි.',
  'සෙම්ප්‍රතිශ්‍යාව, උණ සහ සෙම සමනය සඳහා සාම්ප්‍රදායික පානයකි. ජීර්ණය පහසු කරයි.',
  'මානසික ආතතිය දුරු කරයි, ශරීරයේ ශක්තිය සහ ප්‍රාණවත් බව වර්ධනය කර සුවදායක නින්දක් ලබාදෙයි.',
  'ශරීරය සිසිල් කරයි, සමේ තුවාල සුවපත් කරයි, කෙස් කළඹ නිරෝගී කරයි.',
  'අක්මාවේ සෞඛ්‍යයට සහ සෙංගමාලය සමනය සඳහා විශේෂිත ශ්‍රී ලාංකීය ඖෂධීය පැළෑටියකි.',
  'රුධිරයේ සීනි මට්ටම පාලනය කරයි, හෘද සෞඛ්‍යය සුරකියි, ප්‍රණීත සුවඳ සහ රසය ගෙනදෙයි.'
);

-- 2. Sinhala product descriptions written by migration 003
UPDATE `product` SET `description_si` = NULL WHERE `description_si` IN (
  'සන්ධි වේදනා සහ දැවිල්ල සමනය කරන සාම්ප්‍රදායික ආයුර්වේද තෙලකි.',
  'සමේ රෝග, කුරුලෑ සහ හිස්හොරි සඳහා ස්වාභාවික කොහොඹ තෙල්.',
  'මාංශපේශී සහ වාත රෝග සඳහා ගුණදායක පාරම්පරික ඖෂධීය තෙලකි.',
  'මතක ශක්තිය සහ මොළයේ ක්‍රියාකාරිත්වය වර්ධනය කරන ස්වාභාවික සිරප් වර්ගයකි.',
  'බිළිඳුන්ගේ සංවේදී සමට ආරක්ෂාව සලසන ස්වභාවික කොහොඹ සබන්.'
);

-- 3. 003 named every Gotukola product "syrup", including the tea
UPDATE `product` SET `product_name_si` = 'ස්වාභාවික ගොටුකොළ තේ'
WHERE `product_name` = 'Natural Gotukola Tea' AND `product_name_si` = 'ගොටුකොළ සිරප්';

-- 4. 003 linked Pinda Oil to the herb Aralu without any source for that ingredient
DELETE ph FROM `product_herb` ph
JOIN `product` p ON p.product_id = ph.product_id
JOIN `herb` h ON h.herb_id = ph.herb_id
WHERE p.product_name LIKE '%Pinda%' AND h.name = 'Aralu';

-- 5. Concern names that mentioned a disease or symptom
UPDATE `health_concern` SET `name_en` = 'Skin Care', `name_si` = 'සමේ සත්කාර'
WHERE `slug` = 'skin-care' AND `name_en` = 'Skin Care & Infections';

UPDATE `health_concern` SET `name_en` = 'Joint Comfort', `name_si` = 'සන්ධි සුවපහසුව'
WHERE `slug` = 'joint-comfort' AND `name_en` = 'Joint Comfort & Muscle Pain';
