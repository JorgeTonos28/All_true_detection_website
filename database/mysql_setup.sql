CREATE DATABASE IF NOT EXISTS all_true_detection
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'alltrue_admin'@'localhost' IDENTIFIED BY 'ChangeThisStrongPassword!';
GRANT ALL PRIVILEGES ON all_true_detection.* TO 'alltrue_admin'@'localhost';
FLUSH PRIVILEGES;
