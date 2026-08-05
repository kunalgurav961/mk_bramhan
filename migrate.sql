-- =============================================
-- MK Brahman — Migration v2
-- Run this once to add new columns & indexes
-- =============================================

USE mk_brahman;

-- -----------------------------------------------
-- Add new columns to profiles table
-- -----------------------------------------------
ALTER TABLE profiles
    ADD COLUMN IF NOT EXISTS profile_image   VARCHAR(255) DEFAULT NULL COMMENT 'WebP image filename in uploads/profiles/',
    ADD COLUMN IF NOT EXISTS father_name     VARCHAR(100) DEFAULT NULL COMMENT 'Father name in Marathi',
    ADD COLUMN IF NOT EXISTS mother_name     VARCHAR(100) DEFAULT NULL COMMENT 'Mother name in Marathi',
    ADD COLUMN IF NOT EXISTS family_details  TEXT         DEFAULT NULL COMMENT 'Family details in Marathi',
    ADD COLUMN IF NOT EXISTS about_me        TEXT         DEFAULT NULL COMMENT 'About self in Marathi';

-- -----------------------------------------------
-- Performance indexes for fast search
-- -----------------------------------------------
ALTER TABLE profiles
    ADD INDEX IF NOT EXISTS idx_gender      (gender),
    ADD INDEX IF NOT EXISTS idx_birth_year  (birth_year),
    ADD INDEX IF NOT EXISTS idx_status      (status),
    ADD INDEX IF NOT EXISTS idx_gender_year (gender, birth_year),
    ADD INDEX IF NOT EXISTS idx_salary      (salary),
    ADD INDEX IF NOT EXISTS idx_created     (created_at);

-- Composite index for common search patterns
ALTER TABLE profiles
    ADD INDEX IF NOT EXISTS idx_search_composite (status, gender, birth_year, shortlisted);

-- -----------------------------------------------
-- Fix: ensure occupation column exists
-- -----------------------------------------------
ALTER TABLE profiles
    MODIFY COLUMN occupation VARCHAR(100) DEFAULT NULL;

-- -----------------------------------------------
-- Migration v4: Add mobile_no column
-- -----------------------------------------------
ALTER TABLE profiles
    ADD COLUMN IF NOT EXISTS mobile_no VARCHAR(15) DEFAULT NULL COMMENT 'Contact mobile number';

-- -----------------------------------------------
-- Verify changes
-- -----------------------------------------------
DESCRIBE profiles;
