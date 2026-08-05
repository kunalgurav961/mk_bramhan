<?php
/**
 * MK Brahman — Image Upload & Processing Helper
 * Handles secure upload, resize, and compression.
 * Falls back to JPEG if WebP is not available on this server.
 */

// Use realpath-based absolute path to avoid issues with includes/../ resolution
define('UPLOAD_DIR',   rtrim(realpath(__DIR__ . '/../uploads/profiles') ?: __DIR__ . '/../uploads/profiles', '/') . '/');
define('UPLOAD_URL',   'uploads/profiles/');
define('MAX_WIDTH',    800);
define('JPEG_QUALITY', 82);
define('WEBP_QUALITY', 80);
define('ALLOWED_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']);
define('MAX_FILE_SIZE', 8 * 1024 * 1024); // 8 MB

/** Check if WebP output is supported on this server */
function supportsWebp(): bool {
    return function_exists('imagewebp');
}

/**
 * Process and save an uploaded profile image.
 *
 * @param  array  $file      $_FILES['profile_image'] element
 * @param  string $prefix    Filename prefix (e.g. registration number)
 * @return array             ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function processProfileImage(array $file, string $prefix = ''): array
{
    // ── 1. Basic upload error check ───────────────────────────────────────
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'फाइल php.ini limit पेक्षा मोठी आहे.',
            UPLOAD_ERR_FORM_SIZE  => 'फाइल form limit पेक्षा मोठी आहे.',
            UPLOAD_ERR_PARTIAL    => 'फाइल अपूर्ण अपलोड झाली.',
            UPLOAD_ERR_NO_FILE    => 'कोणतीही फाइल निवडली नाही.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temp directory सापडला नाही.',
            UPLOAD_ERR_CANT_WRITE => 'फाइल लिहिणे शक्य झाले नाही.',
        ];
        return ['success' => false, 'filename' => null,
                'error'   => $messages[$file['error']] ?? 'अज्ञात upload error.'];
    }

    // ── 2. File size check ────────────────────────────────────────────────
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'filename' => null,
                'error'   => 'फाइल 8MB पेक्षा मोठी आहे.'];
    }

    // ── 3. MIME type via finfo (server-side, not trusting browser) ────────
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        return ['success' => false, 'filename' => null,
                'error'   => 'फक्त JPG, PNG, WEBP फाइल स्वीकारल्या जातात. मिळाले: ' . $mimeType];
    }

    // ── 4. Validate it's actually an image ────────────────────────────────
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'filename' => null,
                'error'   => 'अवैध इमेज फाइल.'];
    }

    // ── 5. Ensure upload directory exists ────────────────────────────────
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0777, true)) {
            return ['success' => false, 'filename' => null,
                    'error'   => 'Upload directory तयार करणे शक्य झाले नाही: ' . UPLOAD_DIR];
        }
    }

    // Check if directory is writable
    if (!is_writable(UPLOAD_DIR)) {
        return ['success' => false, 'filename' => null,
                'error'   => 'Upload directory मध्ये write permission नाही. Admin ला सांगा: chmod 777 uploads/profiles'];
    }

    // ── 6. Choose output format: WebP if available, else JPEG ────────────
    $useWebp  = supportsWebp();
    $ext      = $useWebp ? 'webp' : 'jpg';
    $safePrefix = preg_replace('/[^a-z0-9_-]/i', '_', $prefix);
    $filename   = ($safePrefix ? $safePrefix . '_' : '') . uniqid('', true) . '.' . $ext;
    $destPath   = UPLOAD_DIR . $filename;

    // ── 7. Load source image ──────────────────────────────────────────────
    $src = loadImageFromFile($file['tmp_name'], $mimeType);
    if ($src === false) {
        return ['success' => false, 'filename' => null,
                'error'   => 'इमेज प्रोसेस करणे शक्य झाले नाही.'];
    }

    // ── 8. Auto-rotate based on EXIF (JPEG only) ─────────────────────────
    if (in_array($mimeType, ['image/jpeg', 'image/jpg'])) {
        $src = autoRotateImage($src, $file['tmp_name']);
    }

    // ── 9. Resize to max 800px width ─────────────────────────────────────
    $src = resizeImage($src, MAX_WIDTH);

    // ── 10. Save image (WebP if supported, else JPEG) ────────────────────
    $saved = false;
    if ($useWebp) {
        $saved = imagewebp($src, $destPath, WEBP_QUALITY);
    } else {
        $saved = imagejpeg($src, $destPath, JPEG_QUALITY);
    }

    imagedestroy($src);

    if (!$saved) {
        $reason = !is_writable(UPLOAD_DIR) ? ' (Write permission नाही)' : ' (GD library error)';
        return ['success' => false, 'filename' => null,
                'error'   => 'इमेज सेव्ह करणे शक्य झाले नाही.' . $reason];
    }

    chmod($destPath, 0644);

    return ['success' => true, 'filename' => $filename, 'error' => null];
}

/**
 * Load a GD image resource from a file path and MIME type.
 */
function loadImageFromFile(string $path, string $mimeType): \GdImage|false
{
    return match ($mimeType) {
        'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
        'image/png'               => imagecreatefrompng($path),
        'image/webp'              => function_exists('imagecreatefromwebp')
                                        ? imagecreatefromwebp($path)
                                        : false,
        default                   => false,
    };
}

/**
 * Auto-rotate JPEG image based on EXIF orientation data.
 */
function autoRotateImage(\GdImage $image, string $filePath): \GdImage
{
    if (!function_exists('exif_read_data')) return $image;

    $exif        = @exif_read_data($filePath);
    $orientation = $exif['Orientation'] ?? 1;

    $rotated = match ((int)$orientation) {
        3       => imagerotate($image, 180, 0),
        6       => imagerotate($image, -90, 0),
        8       => imagerotate($image, 90, 0),
        default => $image,
    };

    if ($rotated !== $image) {
        imagedestroy($image);
    }

    return $rotated ?: $image;
}

/**
 * Resize a GD image to a maximum width, preserving aspect ratio.
 */
function resizeImage(\GdImage $src, int $maxWidth): \GdImage
{
    $origW = imagesx($src);
    $origH = imagesy($src);

    if ($origW <= $maxWidth) return $src; // No resize needed

    $ratio = $maxWidth / $origW;
    $newW  = $maxWidth;
    $newH  = (int)round($origH * $ratio);

    $dst = imagecreatetruecolor($newW, $newH);

    // Preserve transparency
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($src);

    return $dst;
}

/**
 * Delete an existing profile image from disk.
 */
function deleteProfileImage(?string $filename): void
{
    if (!$filename) return;
    $path = UPLOAD_DIR . basename($filename);
    if (file_exists($path)) {
        @unlink($path);
    }
}

/**
 * Return public URL for a profile image.
 * Falls back to default avatar if not set.
 */
function getProfileImageUrl(?string $filename): string
{
    if ($filename && file_exists(UPLOAD_DIR . basename($filename))) {
        return UPLOAD_URL . htmlspecialchars(basename($filename));
    }
    return 'assets/img/default-avatar.svg';
}
