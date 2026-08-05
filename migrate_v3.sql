-- =============================================
-- MK Brahman — Migration v3
-- Multiple images per profile
-- Run once in phpMyAdmin or MySQL CLI
-- =============================================

USE mk_brahman;

-- -----------------------------------------------
-- PROFILE IMAGES TABLE (multiple images per profile)
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS profile_images (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    profile_id  INT(11)      NOT NULL,
    filename    VARCHAR(255) NOT NULL COMMENT 'Filename in uploads/profiles/',
    sort_order  TINYINT      NOT NULL DEFAULT 0 COMMENT '0 = primary/cover photo',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
    INDEX idx_profile (profile_id),
    INDEX idx_sort    (profile_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- Migrate existing profile_image → profile_images
-- (copies current single image into new table as sort_order=0)
-- -----------------------------------------------
INSERT IGNORE INTO profile_images (profile_id, filename, sort_order)
SELECT id, profile_image, 0
FROM   profiles
WHERE  profile_image IS NOT NULL AND profile_image != '';

-- Verify
SELECT 'Migration v3 complete. profile_images table created.' AS status;
SELECT COUNT(*) AS migrated_images FROM profile_images;
