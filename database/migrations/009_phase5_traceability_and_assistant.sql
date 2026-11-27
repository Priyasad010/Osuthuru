-- Suwoda HerbHub V2 - Phase 5: QR batch traceability and AI assistant

-- ---------------------------------------------------------------- Batches
CREATE TABLE `product_batch` (
  `batch_id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `batch_code` VARCHAR(60) NOT NULL UNIQUE,
  `manufactured_on` DATE NOT NULL,
  `expires_on` DATE NOT NULL,
  `source_location` VARCHAR(150) NULL,
  `supplier_name` VARCHAR(150) NULL,
  `lab_report_path` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `public_token` CHAR(32) NOT NULL UNIQUE,
  `status` ENUM('active','recalled') NOT NULL DEFAULT 'active',
  -- Packs in the batch and packs assigned to orders (first-expiry-first-out)
  `units` INT UNSIGNED NOT NULL DEFAULT 0,
  `units_allocated` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_batch_product_expiry` (`product_id`, `expires_on`),
  INDEX `idx_batch_expiry` (`expires_on`),
  CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scans keep salted hashes only, never the address or browser text
CREATE TABLE `batch_scan` (
  `scan_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `batch_id` INT NOT NULL,
  `ip_hash` CHAR(64) NOT NULL,
  `ua_hash` CHAR(64) NOT NULL,
  `scanned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_scan_batch_ip` (`batch_id`, `ip_hash`),
  CONSTRAINT `fk_scan_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batch` (`batch_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `order_item`
  ADD COLUMN `batch_id` INT NULL,
  ADD CONSTRAINT `fk_order_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batch` (`batch_id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------- Assistant
-- Chat is deleted after 30 days
CREATE TABLE `chat_session` (
  `session_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `ip_hash` CHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_chat_session_ip` (`ip_hash`),
  CONSTRAINT `fk_chat_session_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_message` (
  `message_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `role` ENUM('user','assistant') NOT NULL,
  `content` TEXT NOT NULL,
  `weak_match` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_chat_message_session` (`session_id`, `message_id`),
  INDEX `idx_chat_message_created` (`created_at`),
  CONSTRAINT `fk_chat_message_session` FOREIGN KEY (`session_id`) REFERENCES `chat_session` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assistant_cache` (
  `question_hash` CHAR(64) NOT NULL PRIMARY KEY,
  `answer_json` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Words that lead a question to a health topic (English, Sinhala, Singlish). Admin editable.
CREATE TABLE `concern_keyword` (
  `keyword_id` INT AUTO_INCREMENT PRIMARY KEY,
  `concern_id` INT NOT NULL,
  `keyword` VARCHAR(100) NOT NULL,
  UNIQUE KEY `uq_concern_keyword` (`concern_id`, `keyword`),
  CONSTRAINT `fk_keyword_concern` FOREIGN KEY (`concern_id`) REFERENCES `health_concern` (`concern_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `concern_keyword` (`concern_id`, `keyword`)
SELECT c.concern_id, k.keyword
FROM (
    SELECT 'cold-cough' AS slug, 'cold' AS keyword
    UNION ALL SELECT 'cold-cough', 'cough'
    UNION ALL SELECT 'cold-cough', 'flu'
    UNION ALL SELECT 'cold-cough', 'sore throat'
    UNION ALL SELECT 'cold-cough', 'kassa'
    UNION ALL SELECT 'cold-cough', 'sema'
    UNION ALL SELECT 'cold-cough', 'hembirissawa'
    UNION ALL SELECT 'cold-cough', 'කැස්ස'
    UNION ALL SELECT 'cold-cough', 'සෙම'
    UNION ALL SELECT 'cold-cough', 'හෙම්බිරිස්සා'
    UNION ALL SELECT 'cold-cough', 'ප්‍රතිශ්‍යා'
    UNION ALL SELECT 'digestion', 'digestion'
    UNION ALL SELECT 'digestion', 'stomach'
    UNION ALL SELECT 'digestion', 'gastric'
    UNION ALL SELECT 'digestion', 'constipation'
    UNION ALL SELECT 'digestion', 'bada'
    UNION ALL SELECT 'digestion', 'ajeerna'
    UNION ALL SELECT 'digestion', 'බඩ'
    UNION ALL SELECT 'digestion', 'ජීර්ණ'
    UNION ALL SELECT 'digestion', 'මළබද්ධ'
    UNION ALL SELECT 'skin-care', 'skin'
    UNION ALL SELECT 'skin-care', 'face'
    UNION ALL SELECT 'skin-care', 'hama'
    UNION ALL SELECT 'skin-care', 'සම'
    UNION ALL SELECT 'skin-care', 'මුහුණ'
    UNION ALL SELECT 'hair-care', 'hair'
    UNION ALL SELECT 'hair-care', 'scalp'
    UNION ALL SELECT 'hair-care', 'dandruff'
    UNION ALL SELECT 'hair-care', 'konde'
    UNION ALL SELECT 'hair-care', 'isakes'
    UNION ALL SELECT 'hair-care', 'කෙස්'
    UNION ALL SELECT 'hair-care', 'කොණ්ඩ'
    UNION ALL SELECT 'hair-care', 'හිසකෙස්'
    UNION ALL SELECT 'stress-sleep', 'stress'
    UNION ALL SELECT 'stress-sleep', 'stressed'
    UNION ALL SELECT 'stress-sleep', 'sleep'
    UNION ALL SELECT 'stress-sleep', 'insomnia'
    UNION ALL SELECT 'stress-sleep', 'ninda'
    UNION ALL SELECT 'stress-sleep', 'nidi'
    UNION ALL SELECT 'stress-sleep', 'නින්ද'
    UNION ALL SELECT 'stress-sleep', 'ආතති'
    UNION ALL SELECT 'joint-comfort', 'joint'
    UNION ALL SELECT 'joint-comfort', 'knee'
    UNION ALL SELECT 'joint-comfort', 'muscle'
    UNION ALL SELECT 'joint-comfort', 'sandhi'
    UNION ALL SELECT 'joint-comfort', 'සන්ධි'
    UNION ALL SELECT 'joint-comfort', 'මාංශ'
    UNION ALL SELECT 'immunity', 'immunity'
    UNION ALL SELECT 'immunity', 'immune'
    UNION ALL SELECT 'immunity', 'prathishakthi'
    UNION ALL SELECT 'immunity', 'ප්‍රතිශක්ති'
) AS k
JOIN `health_concern` c ON c.slug = CONVERT(k.slug USING utf8mb4) COLLATE utf8mb4_unicode_ci;
