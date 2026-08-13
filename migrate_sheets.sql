-- =============================================
-- MK Brahman — Migration: Google Sheets Sync
-- Run once in phpMyAdmin or MySQL CLI
-- =============================================

-- Add new fields from Google Sheet
ALTER TABLE profiles
    ADD COLUMN IF NOT EXISTS jaat        VARCHAR(80)  DEFAULT NULL  COMMENT 'जात (Caste from Sheet)',
    ADD COLUMN IF NOT EXISTS rashi       VARCHAR(50)  DEFAULT NULL  COMMENT 'रास (Rashi/Zodiac from Sheet)',
    ADD COLUMN IF NOT EXISTS mobile_2    VARCHAR(20)  DEFAULT NULL  COMMENT 'मोबाईल 2',
    ADD COLUMN IF NOT EXISTS mobile_3    VARCHAR(20)  DEFAULT NULL  COMMENT 'मोबाईल 3',
    ADD COLUMN IF NOT EXISTS mobile_4    VARCHAR(20)  DEFAULT NULL  COMMENT 'मोबाईल 4',
    ADD COLUMN IF NOT EXISTS sheet_img_1 TEXT         DEFAULT NULL  COMMENT 'Google Drive image URL 1',
    ADD COLUMN IF NOT EXISTS sheet_img_2 TEXT         DEFAULT NULL  COMMENT 'Google Drive image URL 2',
    ADD COLUMN IF NOT EXISTS sheet_img_3 TEXT         DEFAULT NULL  COMMENT 'Google Drive image URL 3',
    ADD COLUMN IF NOT EXISTS sheet_img_4 TEXT         DEFAULT NULL  COMMENT 'Google Drive image URL 4',
    ADD COLUMN IF NOT EXISTS sheets_synced_at DATETIME DEFAULT NULL COMMENT 'Last synced from Google Sheets';

-- Also add profile_photo column if not exists (backward compat)
ALTER TABLE profiles
    ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT NULL;

-- Verify (simple check without INFORMATION_SCHEMA)
SELECT 'Sheets migration complete. New columns added successfully.' AS status;
SELECT COUNT(*) AS total_profiles FROM profiles;
