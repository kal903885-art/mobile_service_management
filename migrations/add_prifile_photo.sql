-- Run this once against your existing database before using the new
-- "add a profile photo" feature. It adds one column to the users table
-- to store the uploaded photo's filename.

ALTER TABLE users
    ADD COLUMN profile_photo VARCHAR(255) NULL AFTER phone;