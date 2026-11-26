-- Suwoda HerbHub V2 - Phase 4: Dosha quiz and safety checker
-- No customer health answers are stored: there is deliberately no table for them.

-- ---------------------------------------------------------------- Dosha quiz
CREATE TABLE `quiz_setting` (
  `id` TINYINT NOT NULL PRIMARY KEY,
  `published` TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `quiz_setting` (`id`, `published`) VALUES (1, 0);

CREATE TABLE `quiz_question` (
  `question_id` INT AUTO_INCREMENT PRIMARY KEY,
  `text_en` VARCHAR(255) NOT NULL,
  `text_si` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quiz_option` (
  `option_id` INT AUTO_INCREMENT PRIMARY KEY,
  `question_id` INT NOT NULL,
  `text_en` VARCHAR(255) NOT NULL,
  `text_si` VARCHAR(255) NOT NULL DEFAULT '',
  `vata` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `pitta` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `kapha` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  INDEX `idx_quiz_option_question` (`question_id`),
  CONSTRAINT `fk_quiz_option_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_question` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scores only (percentages). Individual answers are never saved.
CREATE TABLE `quiz_result` (
  `result_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `session_token` CHAR(32) NOT NULL,
  `vata` TINYINT UNSIGNED NOT NULL,
  `pitta` TINYINT UNSIGNED NOT NULL,
  `kapha` TINYINT UNSIGNED NOT NULL,
  `dominant` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_quiz_result_token` (`session_token`),
  INDEX `idx_quiz_result_user` (`user_id`, `result_id`),
  CONSTRAINT `fk_quiz_result_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Result description and lifestyle tips, entered by an admin
CREATE TABLE `dosha_profile` (
  `dosha` ENUM('vata','pitta','kapha') NOT NULL PRIMARY KEY,
  `description_en` TEXT NULL,
  `description_si` TEXT NULL,
  `tips_en` TEXT NULL,
  `tips_si` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `dosha_profile` (`dosha`) VALUES ('vata'), ('pitta'), ('kapha');

CREATE TABLE `product_dosha` (
  `product_id` INT NOT NULL,
  `dosha` ENUM('vata','pitta','kapha') NOT NULL,
  `strength` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`product_id`, `dosha`),
  CONSTRAINT `chk_product_dosha_strength` CHECK (`strength` BETWEEN 1 AND 3),
  CONSTRAINT `fk_product_dosha_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Safety checker
CREATE TABLE `safety_flag` (
  `flag_id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name_en` VARCHAR(150) NOT NULL,
  `name_si` VARCHAR(150) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `safety_flag` (`code`, `name_en`, `name_si`) VALUES
('pregnant', 'I am pregnant', 'මම ගර්භණී වෙමි'),
('breastfeeding', 'I am breastfeeding', 'මම මව්කිරි දෙමි'),
('child_under_12', 'Buying for a child under 12', 'වයස අවුරුදු 12ට අඩු දරුවෙකු සඳහා'),
('diabetes_medicine', 'Taking diabetes medicine', 'දියවැඩියා ඖෂධ ගනිමි'),
('blood_pressure_medicine', 'Taking blood-pressure medicine', 'රුධිර පීඩන ඖෂධ ගනිමි'),
('blood_thinners', 'Taking blood thinners', 'රුධිරය තුනී කරන ඖෂධ ගනිමි'),
('surgery_soon', 'Surgery within 2 weeks', 'සති 2ක් ඇතුළත ශල්‍යකර්මයක් ඇත'),
('known_allergy', 'I have a known allergy', 'මට දන්නා අසාත්මිකතාවක් ඇත');

CREATE TABLE `herb_safety` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `herb_id` INT NOT NULL,
  `flag_id` INT NOT NULL,
  `severity` ENUM('info','caution','avoid') NOT NULL DEFAULT 'caution',
  `note_en` TEXT NULL,
  `note_si` TEXT NULL,
  `source_note` VARCHAR(500) NULL,
  `published` TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY `uq_herb_safety` (`herb_id`, `flag_id`),
  CONSTRAINT `fk_herb_safety_herb` FOREIGN KEY (`herb_id`) REFERENCES `herb` (`herb_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_herb_safety_flag` FOREIGN KEY (`flag_id`) REFERENCES `safety_flag` (`flag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Quiz draft
-- DRAFT content: the quiz stays hidden until an admin publishes it, which should
-- happen only after a qualified practitioner has reviewed questions and points.
INSERT INTO `quiz_question` (`question_id`, `text_en`, `text_si`, `sort_order`) VALUES
(1, 'How would you describe your body frame?', 'ඔබේ ශරීර ස්වභාවය විස්තර කරන්නේ කෙසේද?', 1),
(2, 'What is your skin usually like?', 'ඔබේ සම සාමාන්‍යයෙන් කෙබඳුද?', 2),
(3, 'What is your hair usually like?', 'ඔබේ කෙස් සාමාන්‍යයෙන් කෙබඳුද?', 3),
(4, 'How is your appetite?', 'ඔබේ ආහාර රුචිය කෙබඳුද?', 4),
(5, 'How is your digestion usually?', 'ඔබේ ආහාර ජීර්ණය සාමාන්‍යයෙන් කෙබඳුද?', 5),
(6, 'How do you usually sleep?', 'ඔබ සාමාන්‍යයෙන් නිදාගන්නේ කෙසේද?', 6),
(7, 'How is your energy through the day?', 'දවස පුරා ඔබේ ශක්තිය කෙබඳුද?', 7),
(8, 'Which weather do you find least comfortable?', 'ඔබට වඩාත්ම අපහසු කාලගුණය කුමක්ද?', 8),
(9, 'How do you tend to feel under stress?', 'මානසික පීඩනයකදී ඔබට සාමාන්‍යයෙන් දැනෙන්නේ කෙසේද?', 9),
(10, 'How do you usually speak?', 'ඔබ සාමාන්‍යයෙන් කතා කරන්නේ කෙසේද?', 10),
(11, 'How do you learn and remember?', 'ඔබ ඉගෙනගෙන මතක තබාගන්නේ කෙසේද?', 11),
(12, 'What is your usual pace of activity?', 'ඔබේ සාමාන්‍ය ක්‍රියාකාරී වේගය කෙබඳුද?', 12);

INSERT INTO `quiz_option` (`question_id`, `text_en`, `text_si`, `vata`, `pitta`, `kapha`) VALUES
(1, 'Slim; I find it hard to gain weight', 'සිහින්ය; බර වැඩි කරගැනීම අපහසුය', 1, 0, 0),
(1, 'Medium build', 'මධ්‍යම ශරීරයකි', 0, 1, 0),
(1, 'Broad or solid; I gain weight easily', 'පුළුල් හෝ තරමක් තරබාරුය; පහසුවෙන් බර වැඩිවේ', 0, 0, 1),
(2, 'Dry or rough', 'වියළි හෝ රළුය', 1, 0, 0),
(2, 'Warm, sensitive or easily red', 'උණුසුම්, සංවේදී හෝ පහසුවෙන් රතු වේ', 0, 1, 0),
(2, 'Soft, smooth or oily', 'මෘදු, සිනිඳු හෝ තෙල් සහිතය', 0, 0, 1),
(3, 'Dry or frizzy', 'වියළි හෝ අවුල් වන සුළුය', 1, 0, 0),
(3, 'Fine, early greying or thinning', 'සිහින්ය; ඉක්මනින් පැසීම හෝ තුනී වීම', 0, 1, 0),
(3, 'Thick and glossy', 'ඝන සහ දිලිසෙන සුළුය', 0, 0, 1),
(4, 'Irregular; it changes from day to day', 'අක්‍රමවත්ය; දිනෙන් දින වෙනස් වේ', 1, 0, 0),
(4, 'Strong; I dislike missing meals', 'තදය; ආහාර වේලක් මඟහැරීමට අකමැතිය', 0, 1, 0),
(4, 'Steady and mild; I can skip a meal easily', 'ස්ථාවර සහ අඩුය; වේලක් පහසුවෙන් මඟහැරිය හැක', 0, 0, 1),
(5, 'Variable, sometimes with gas', 'වෙනස් වන සුළුය, සමහරවිට වායු ගතිය ඇත', 1, 0, 0),
(5, 'Quick', 'වේගවත්ය', 0, 1, 0),
(5, 'Slow, with a heavy feeling', 'මන්දගාමීය, බර ගතියක් දැනේ', 0, 0, 1),
(6, 'Light and easily disturbed', 'සැහැල්ලුය; පහසුවෙන් බාධා වේ', 1, 0, 0),
(6, 'Moderate; I wake and fall asleep again', 'මධ්‍යස්ථය; අවදි වී නැවත නිදාගනිමි', 0, 1, 0),
(6, 'Deep and long', 'ගැඹුරු සහ දිගු නින්දකි', 0, 0, 1),
(7, 'Comes in bursts and runs out quickly', 'එකවර වැඩිවී ඉක්මනින් අවසන් වේ', 1, 0, 0),
(7, 'Focused and intense', 'එකලස් සහ තීව්‍රය', 0, 1, 0),
(7, 'Steady and lasting', 'ස්ථාවර සහ කල්පවතින සුළුය', 0, 0, 1),
(8, 'Cold and windy', 'සීතල සහ සුළං සහිත', 1, 0, 0),
(8, 'Hot', 'උණුසුම්', 0, 1, 0),
(8, 'Cool and damp', 'සිසිල් සහ තෙත් සහිත', 0, 0, 1),
(9, 'Worried or restless', 'කනස්සල්ල හෝ නොසන්සුන් බව', 1, 0, 0),
(9, 'Irritated or impatient', 'කෝපය හෝ ඉවසීම නැති බව', 0, 1, 0),
(9, 'Calm but withdrawn', 'සන්සුන් නමුත් හුදෙකලා වීම', 0, 0, 1),
(10, 'Fast and talkative', 'වේගයෙන් සහ බොහෝ කතා කරමි', 1, 0, 0),
(10, 'Clear and direct', 'පැහැදිලිව සහ කෙළින්ම', 0, 1, 0),
(10, 'Slow and calm', 'සෙමින් සහ සන්සුන්ව', 0, 0, 1),
(11, 'I learn quickly and forget quickly', 'ඉක්මනින් ඉගෙනගෙන ඉක්මනින් අමතක වේ', 1, 0, 0),
(11, 'I understand sharply and remember well', 'තියුණුව තේරුම්ගෙන හොඳින් මතක තබාගනිමි', 0, 1, 0),
(11, 'I learn slowly and remember for a long time', 'සෙමින් ඉගෙනගෙන දිගු කලක් මතක තබාගනිමි', 0, 0, 1),
(12, 'Quick and always moving', 'වේගවත්ය; නිතර චලනය වෙමි', 1, 0, 0),
(12, 'Purposeful and competitive', 'අරමුණු සහිත සහ තරඟකාරීය', 0, 1, 0),
(12, 'Relaxed and unhurried', 'ලිහිල් සහ හදිසි නොවන', 0, 0, 1);
