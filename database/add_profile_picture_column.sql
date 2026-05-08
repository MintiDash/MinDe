-- Add profile picture column to users table for profile management feature
ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER contact_num;

-- Add comment to track the change
-- Profile pictures will be stored in Assets/images/profiles/ directory
