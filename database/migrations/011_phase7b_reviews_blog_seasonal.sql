-- Suwoda HerbHub V2 - Phase 7B: reviews, blog and seasonal picks

-- One review per customer and product (older duplicates are removed first)
DELETE r1 FROM `review` r1
JOIN `review` r2 ON r2.user_id = r1.user_id AND r2.product_id = r1.product_id AND r2.review_id > r1.review_id;
ALTER TABLE `review` ADD UNIQUE KEY `uq_review_user_product` (`user_id`, `product_id`);

CREATE TABLE `review_helpful` (
  `review_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  PRIMARY KEY (`review_id`, `user_id`),
  CONSTRAINT `fk_helpful_review` FOREIGN KEY (`review_id`) REFERENCES `review` (`review_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_helpful_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `review_image` (
  `image_id` INT AUTO_INCREMENT PRIMARY KEY,
  `review_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  INDEX `idx_review_image_review` (`review_id`),
  CONSTRAINT `fk_review_image_review` FOREIGN KEY (`review_id`) REFERENCES `review` (`review_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blog_tag` (
  `tag_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(80) NOT NULL UNIQUE,
  `name_si` VARCHAR(80) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blog_tag_map` (
  `blog_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  PRIMARY KEY (`blog_id`, `tag_id`),
  INDEX `idx_blog_tag_map_tag` (`tag_id`),
  CONSTRAINT `fk_btm_blog` FOREIGN KEY (`blog_id`) REFERENCES `blog` (`blog_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_btm_tag` FOREIGN KEY (`tag_id`) REFERENCES `blog_tag` (`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blog_product` (
  `blog_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  PRIMARY KEY (`blog_id`, `product_id`),
  INDEX `idx_blog_product_product` (`product_id`),
  CONSTRAINT `fk_bp_blog` FOREIGN KEY (`blog_id`) REFERENCES `blog` (`blog_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bp_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dates as month and day ("0501"); a range may wrap past the new year
CREATE TABLE `seasonal_collection` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(150) NOT NULL,
  `name_si` VARCHAR(150) NOT NULL DEFAULT '',
  `start_mmdd` CHAR(4) NOT NULL,
  `end_mmdd` CHAR(4) NOT NULL,
  `concern_id` INT NOT NULL,
  INDEX `idx_seasonal_concern` (`concern_id`),
  CONSTRAINT `fk_seasonal_concern` FOREIGN KEY (`concern_id`) REFERENCES `health_concern` (`concern_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
