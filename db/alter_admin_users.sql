-- Add last_login and last_activity columns to admin_users table
ALTER TABLE admin_users
ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL;
