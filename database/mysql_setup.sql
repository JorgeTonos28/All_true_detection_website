CREATE DATABASE IF NOT EXISTS all_true_detection
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Create a dedicated database user through the hosting control panel or secret manager.
-- Grant access only to this application database and store the generated password in .env.
-- Do not provision database accounts with credentials committed to source control.
