-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `suwoda` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create app user with read/write privileges
CREATE USER IF NOT EXISTS 'app'@'%' IDENTIFIED BY 'app_secret';
GRANT ALL PRIVILEGES ON `suwoda`.* TO 'app'@'%';

-- Create read-only user for Python ML service
CREATE USER IF NOT EXISTS 'ml_ro'@'%' IDENTIFIED BY 'ml_ro_secret';
-- Its table rights are granted after the migrations by: php bin/grant_ml_user.php

FLUSH PRIVILEGES;
