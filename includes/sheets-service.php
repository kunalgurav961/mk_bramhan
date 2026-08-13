<?php
/**
 * MK Brahman — Google Sheets Service Layer
 * ==========================================
 * Single place for all Apps Script API calls.
 * All other PHP files use this — never call the Apps Script URL directly.
 *
 * USAGE:
 *   require_once 'includes/sheets-service.php';
 *   $result = SheetsService::getAll();
 *   $result = SheetsService::sync(getDB());
 */

// ── CONFIGURATION ────────────────────────────────────────────────────────────
// Paste your deployed Apps Script Web App URL here (after you deploy it):
define('SHEETS_SCRIPT_URL', 'https://script.google.com/macros/s/AKfycbxbUGnj1ShT1cLkEdkEray45jo7SGLaf3ESEWLSEzHeFdmeaNrPbkianc6NKYFMZTv6/exec');  // <-- FILL THIS IN after deployment

// Sync behaviour
define('SHEETS_TIMEOUT',    20);   // seconds to wait for Apps Script response
define('SHEETS_DOWNLOAD_IMAGES', true);  // download Drive images locally on sync

// ── SheetsService class ──────────────────────────────────────────────────────

class SheetsService {

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Ping the Apps Script — returns true if reachable.
     */
    public static function ping(): bool {
        $res = self::call(['action' => 'ping']);
        return isset($res['message']);
    }

    /**
     * Fetch all profiles from Google Sheets (raw, not synced to DB).
     * @param  string $sheet  Optional tab name to filter
     * @return array  ['status'=>'ok', 'count'=>N, 'profiles'=>[...]]
     */
    public static function getAll(string $sheet = ''): array {
        $params = ['action' => 'getAll'];
        if ($sheet !== '') $params['sheet'] = $sheet;
        return self::call($params);
    }

    /**
     * Fetch available sheet/tab names from the spreadsheet.
     */
    public static function getSheetNames(): array {
        return self::call(['action' => 'sheetNames']);
    }

    /**
     * Sync all profiles from Google Sheets into MySQL.
     * Upserts by registration_no (inserts new, updates existing).
     *
     * @param  mysqli $conn   Active DB connection
     * @param  string $sheet  Optional: sync only one tab
     * @return array  ['added'=>N, 'updated'=>N, 'skipped'=>N, 'errors'=>[...]]
     */
    public static function sync(mysqli $conn, string $sheet = ''): array {
        $data = self::getAll($sheet);

        if (($data['status'] ?? '') !== 'ok') {
            return [
                'added'   => 0, 'updated' => 0, 'skipped' => 0,
                'errors'  => ['Apps Script error: ' . ($data['message'] ?? 'unknown')],
            ];
        }

        $result = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($data['profiles'] as $p) {
            try {
                self::upsertProfile($conn, $p, $result);
            } catch (Throwable $e) {
                $result['errors'][] = ($p['registration_no'] ?? '?') . ': ' . $e->getMessage();
                $result['skipped']++;
            }
        }

        return $result;
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    /**
     * Upsert one profile row into MySQL.
     * Modifies $result counters in-place.
     */
    private static function upsertProfile(mysqli $conn, array $p, array &$result): void {
        $reg = trim($p['registration_no'] ?? '');
        if ($reg === '') { $result['skipped']++; return; }

        // Check existing
        $check = $conn->prepare("SELECT id FROM profiles WHERE registration_no = ? LIMIT 1");
        $check->bind_param('s', $reg);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        // Resolve images
        $imgFile = '';
        if (SHEETS_DOWNLOAD_IMAGES) {
            $imgFile = self::downloadImage($p['image_1'] ?? '', $reg . '_1');
        } else {
            $imgFile = $p['image_1'] ?? '';
        }

        // Map fields
        $gender    = (int)($p['gender']    ?? 1);
        $birthYr   = substr(trim($p['birth_year'] ?? ''), 0, 4);
        $name      = substr(trim($p['name']      ?? ''), 0, 100);
        $gotra     = substr(trim($p['gotra']     ?? ''), 0, 80);
        $heightFt  = (int)($p['height_ft'] ?? 5);
        $heightIn  = (int)($p['height_in'] ?? 0);
        $salary    = (int)($p['salary']    ?? 0);
        $education = substr(trim($p['education'] ?? ''), 0, 100);
        $city      = substr(trim($p['city']      ?? ''), 0, 80);
        $jaat      = substr(trim($p['jaat']      ?? ''), 0, 80);
        $rashi     = substr(trim($p['rashi']     ?? ''), 0, 50);
        $mob1      = substr(trim($p['mobile_1']  ?? ''), 0, 20);
        $mob2      = substr(trim($p['mobile_2']  ?? ''), 0, 20);
        $mob3      = substr(trim($p['mobile_3']  ?? ''), 0, 20);
        $mob4      = substr(trim($p['mobile_4']  ?? ''), 0, 20);
        $img1      = $p['image_1'] ?? '';
        $img2      = $p['image_2'] ?? '';
        $img3      = $p['image_3'] ?? '';
        $img4      = $p['image_4'] ?? '';

        if ($existing) {
            // UPDATE
            $profileId = (int)$existing['id'];
            $stmt = $conn->prepare("
                UPDATE profiles SET
                    gender = ?, birth_year = ?, name = ?, gotra = ?,
                    height_ft = ?, height_in = ?, salary = ?,
                    education = ?, city = ?, jaat = ?, rashi = ?,
                    mobile_no = ?, mobile_2 = ?, mobile_3 = ?, mobile_4 = ?,
                    sheet_img_1 = ?, sheet_img_2 = ?, sheet_img_3 = ?, sheet_img_4 = ?,
                    profile_image = COALESCE(NULLIF(profile_image,''), ?),
                    sheets_synced_at = NOW()
                WHERE registration_no = ?
            ");
            // Format: i(gender) sss(birthYr,name,gotra) iii(ft,in,salary) s×14(education…reg)
            $stmt->bind_param(
                'isssiiissssssssssssss',
                $gender, $birthYr, $name, $gotra,
                $heightFt, $heightIn, $salary,
                $education, $city, $jaat, $rashi,
                $mob1, $mob2, $mob3, $mob4,
                $img1, $img2, $img3, $img4,
                $imgFile, $reg
            );
            $stmt->execute();
            $result['updated']++;

            // Sync gallery images (profile_images table)
            self::syncGalleryImages($conn, $profileId, $p, $reg);

        } else {
            // INSERT
            $stmt = $conn->prepare("
                INSERT INTO profiles
                    (registration_no, gender, birth_year, name, gotra,
                     height_ft, height_in, salary, education, city,
                     jaat, rashi, mobile_no, mobile_2, mobile_3, mobile_4,
                     sheet_img_1, sheet_img_2, sheet_img_3, sheet_img_4,
                     profile_image, status, sheets_synced_at)
                VALUES (?, ?, ?, ?, ?,  ?, ?, ?, ?, ?,  ?, ?, ?, ?, ?,  ?,
                        ?, ?, ?, ?,  ?, 1, NOW())
            ");
            // Format: s(reg) i(gender) sss(birthYr,name,gotra) iii(ft,in,salary) s×13(education…imgFile)
            $stmt->bind_param(
                'sisssiiisssssssssssss',
                $reg, $gender, $birthYr, $name, $gotra,
                $heightFt, $heightIn, $salary, $education, $city,
                $jaat, $rashi, $mob1, $mob2, $mob3, $mob4,
                $img1, $img2, $img3, $img4, $imgFile
            );
            $stmt->execute();
            $profileId = $conn->insert_id;
            $result['added']++;

            // Sync gallery images
            self::syncGalleryImages($conn, $profileId, $p, $reg);
        }
    }

    /**
     * Sync Google Drive image URLs into the profile_images table.
     * Downloads images locally if SHEETS_DOWNLOAD_IMAGES is true.
     */
    private static function syncGalleryImages(mysqli $conn, int $profileId, array $p, string $reg): void {
        // Remove existing sheet-synced images for this profile
        $del = $conn->prepare(
            "DELETE FROM profile_images WHERE profile_id = ? AND filename LIKE 'sheet_%'"
        );
        $del->bind_param('i', $profileId);
        $del->execute();

        $imageKeys = ['image_1', 'image_2', 'image_3', 'image_4'];
        $order = 0;

        foreach ($imageKeys as $key) {
            $url = trim($p[$key] ?? '');
            if ($url === '') continue;

            $filename = '';
            if (SHEETS_DOWNLOAD_IMAGES) {
                $filename = self::downloadImage($url, $reg . '_' . ($order + 1));
            }

            if ($filename === '') {
                // Store URL as filename so we can detect it as external
                $filename = 'sheet_' . md5($url) . '.url';
                // Store the actual URL in a sidecar if needed
            }

            $ins = $conn->prepare(
                "INSERT IGNORE INTO profile_images (profile_id, filename, sort_order) VALUES (?, ?, ?)"
            );
            $ins->bind_param('isi', $profileId, $filename, $order);
            $ins->execute();
            $order++;
        }
    }

    /**
     * Download an image from a URL (Google Drive or other) and save it locally.
     * Returns the saved filename, or '' on failure.
     */
    private static function downloadImage(string $url, string $prefix): string {
        if ($url === '') return '';

        $dir = __DIR__ . '/../uploads/profiles/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        // Determine extension from URL or default to jpg
        $ext = 'jpg';
        if (preg_match('/\.(jpg|jpeg|png|webp|gif)(\?|$)/i', $url, $m)) {
            $ext = strtolower($m[1]);
        }

        $safePrefix = preg_replace('/[^a-z0-9_]/i', '_', $prefix);
        $filename   = 'sheet_' . $safePrefix . '.' . $ext;
        $localPath  = $dir . $filename;

        // Skip if already downloaded
        if (file_exists($localPath) && filesize($localPath) > 500) return $filename;

        // Download
        $ctx = stream_context_create([
            'http' => [
                'timeout'      => 15,
                'user_agent'   => 'MKBrahman-Sync/1.0',
                'follow_location' => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $bytes = @file_get_contents($url, false, $ctx);
        if ($bytes === false || strlen($bytes) < 500) return '';

        // Basic image type check
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_buffer($finfo, $bytes);
        finfo_close($finfo);
        if (!str_starts_with($mime, 'image/')) return '';

        file_put_contents($localPath, $bytes);
        return $filename;
    }

    /**
     * Call the Apps Script Web App and return decoded JSON.
     */
    private static function call(array $params): array {
        $url = SHEETS_SCRIPT_URL;

        if ($url === '') {
            return ['status' => 'error', 'message' => 'Apps Script URL not configured in includes/sheets-service.php'];
        }

        $fullUrl = $url . '?' . http_build_query($params);

        $ctx = stream_context_create([
            'http' => [
                'timeout'    => SHEETS_TIMEOUT,
                'user_agent' => 'MKBrahman-PHP/1.0',
                'follow_location' => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $raw = @file_get_contents($fullUrl, false, $ctx);

        if ($raw === false) {
            return ['status' => 'error', 'message' => 'Could not reach Apps Script. Check URL and deployment.'];
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            return ['status' => 'error', 'message' => 'Invalid JSON from Apps Script: ' . substr($raw, 0, 200)];
        }

        return $decoded;
    }
}
