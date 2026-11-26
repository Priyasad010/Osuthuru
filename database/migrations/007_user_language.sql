-- Suwoda HerbHub V2 - Remember each customer's language so emails can use it

ALTER TABLE `user`
  ADD COLUMN `lang` VARCHAR(5) NOT NULL DEFAULT 'en' AFTER `status`;
