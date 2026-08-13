/**
 * MK Brahman — Google Apps Script API
 * =====================================
 * HOW TO DEPLOY:
 *  1. Open your Google Sheet → Extensions → Apps Script
 *  2. Paste this entire file, replacing any existing code
 *  3. Click Deploy → New deployment → Web App
 *     - Execute as: Me
 *     - Who has access: Anyone
 *  4. Copy the Web App URL and paste it into includes/sheets-service.php
 *
 * SHEET TAB NAMES (important!):
 *  - Tab for boys  → name it: मुलगे   (or Boys)
 *  - Tab for girls → name it: मुली    (or Girls)
 *  If you only have one tab, gender will be set to 1 (boy) by default.
 *
 * COLUMN ORDER in each tab (DO NOT change order):
 *  A: जन्म       B: अ. क्र.  C: उंची    D: नाव     E: पगार
 *  F: शिक्षण    G: ठिकाण    H: जात     I: गोत्र   J: रास
 *  K: मोबाईल 1  L: मोबाईल 2  M: मोबाईल 3  N: मोबाईल 4
 *  O: इमेज 1    P: इमेज 2   Q: इमेज 3   R: इमेज 4
 */

// ── Column indices (0-based) ─────────────────────────────────────────────────
var COL = {
  BIRTH_YEAR : 0,   // A — जन्म
  REG_NO     : 1,   // B — अ. क्र.
  HEIGHT     : 2,   // C — उंची
  NAME       : 3,   // D — नाव
  SALARY     : 4,   // E — पगार
  EDUCATION  : 5,   // F — शिक्षण
  CITY       : 6,   // G — ठिकाण
  JAAT       : 7,   // H — जात
  GOTRA      : 8,   // I — गोत्र
  RASHI      : 9,   // J — रास
  MOBILE_1   : 10,  // K — मोबाईल 1
  MOBILE_2   : 11,  // L — मोबाईल 2
  MOBILE_3   : 12,  // M — मोबाईल 3
  MOBILE_4   : 13,  // N — मोबाईल 4
  IMAGE_1    : 14,  // O — इमेज 1
  IMAGE_2    : 15,  // P — इमेज 2
  IMAGE_3    : 16,  // Q — इमेज 3
  IMAGE_4    : 17,  // R — इमेज 4
};

// ── Main HTTP GET handler ────────────────────────────────────────────────────

/**
 * Handles all GET requests to this Web App.
 *
 * Supported ?action= values:
 *   getAll          — all profiles (all tabs)
 *   getAll&sheet=X  — profiles from one tab only
 *   getByRegNo&reg_no=MKB001
 *   search&q=राहुल
 *   sheetNames      — list tab names + row counts
 *   ping            — health check
 */
function doGet(e) {
  try {
    var action = (e.parameter.action || 'getAll').trim();

    if (action === 'ping') {
      return ok({ message: 'MK Brahman Apps Script is running', time: new Date().toISOString() });
    }

    if (action === 'sheetNames') {
      return ok(getSheetNames());
    }

    if (action === 'getAll') {
      var sheetParam = (e.parameter.sheet || '').trim();
      return ok(getAllProfiles(sheetParam));
    }

    if (action === 'getByRegNo') {
      var regNo = (e.parameter.reg_no || '').trim();
      if (!regNo) return err('reg_no parameter is required');
      return ok(getProfileByRegNo(regNo));
    }

    if (action === 'search') {
      var q     = (e.parameter.q     || '').trim();
      var sheet = (e.parameter.sheet || '').trim();
      return ok(searchProfiles(q, sheet));
    }

    return err('Unknown action: ' + action);

  } catch (ex) {
    return err('Server error: ' + ex.message);
  }
}

// ── Business logic ───────────────────────────────────────────────────────────

function getAllProfiles(sheetName) {
  var ss     = SpreadsheetApp.getActiveSpreadsheet();
  var sheets = sheetName
    ? [ss.getSheetByName(sheetName)].filter(Boolean)
    : ss.getSheets();

  var all = [];
  sheets.forEach(function(s) {
    var gender   = detectGender(s.getName());
    var profiles = readSheet(s, gender);
    all = all.concat(profiles);
  });

  return { status: 'ok', count: all.length, profiles: all };
}

function getProfileByRegNo(regNo) {
  var ss     = SpreadsheetApp.getActiveSpreadsheet();
  var sheets = ss.getSheets();

  for (var i = 0; i < sheets.length; i++) {
    var gender   = detectGender(sheets[i].getName());
    var profiles = readSheet(sheets[i], gender);
    for (var j = 0; j < profiles.length; j++) {
      if (profiles[j].registration_no === regNo) {
        return { status: 'ok', profile: profiles[j] };
      }
    }
  }
  return { status: 'not_found', profile: null };
}

function searchProfiles(query, sheetName) {
  var data = getAllProfiles(sheetName);
  if (!query) return data;

  var q        = query.toLowerCase();
  var filtered = data.profiles.filter(function(p) {
    return (p.name            || '').toLowerCase().indexOf(q) !== -1 ||
           (p.city            || '').toLowerCase().indexOf(q) !== -1 ||
           (p.gotra           || '').toLowerCase().indexOf(q) !== -1 ||
           (p.registration_no || '').toLowerCase().indexOf(q) !== -1 ||
           (p.mobile_1        || '').indexOf(query) !== -1           ||
           (p.mobile_2        || '').indexOf(query) !== -1;
  });

  return { status: 'ok', count: filtered.length, profiles: filtered };
}

function getSheetNames() {
  var ss    = SpreadsheetApp.getActiveSpreadsheet();
  var names = ss.getSheets().map(function(s) {
    return {
      name   : s.getName(),
      gender : detectGender(s.getName()),
      rows   : Math.max(0, s.getLastRow() - 1)
    };
  });
  return { status: 'ok', sheets: names };
}

// ── Sheet reading ────────────────────────────────────────────────────────────

function readSheet(sheet, gender) {
  var lastRow = sheet.getLastRow();
  if (lastRow < 2) return [];  // only header or empty

  var lastCol = Math.max(sheet.getLastColumn(), 18);
  var values  = sheet.getRange(2, 1, lastRow - 1, lastCol).getValues();

  var profiles = [];
  values.forEach(function(row, idx) {
    var regNo = String(row[COL.REG_NO] || '').trim();
    var name  = String(row[COL.NAME]   || '').trim();
    if (!regNo && !name) return;  // skip blank rows

    var h       = parseHeight(String(row[COL.HEIGHT] || ''));
    var salary  = parseSalary(String(row[COL.SALARY] || ''));
    var birthYr = parseBirthYear(String(row[COL.BIRTH_YEAR] || ''));

    var images = [
      normalizeDriveUrl(String(row[COL.IMAGE_1] || '')),
      normalizeDriveUrl(String(row[COL.IMAGE_2] || '')),
      normalizeDriveUrl(String(row[COL.IMAGE_3] || '')),
      normalizeDriveUrl(String(row[COL.IMAGE_4] || '')),
    ].filter(function(u) { return u !== ''; });

    profiles.push({
      row_number      : idx + 2,
      registration_no : regNo || ('ROW' + (idx + 2)),
      gender          : String(gender),
      birth_year      : birthYr,
      name            : name,
      height_ft       : h.ft,
      height_in       : h.inches,
      salary          : salary,
      education       : String(row[COL.EDUCATION] || '').trim(),
      city            : String(row[COL.CITY]      || '').trim(),
      jaat            : String(row[COL.JAAT]       || '').trim(),
      gotra           : String(row[COL.GOTRA]      || '').trim(),
      rashi           : String(row[COL.RASHI]      || '').trim(),
      mobile_1        : String(row[COL.MOBILE_1]   || '').trim(),
      mobile_2        : String(row[COL.MOBILE_2]   || '').trim(),
      mobile_3        : String(row[COL.MOBILE_3]   || '').trim(),
      mobile_4        : String(row[COL.MOBILE_4]   || '').trim(),
      image_1         : images[0] || '',
      image_2         : images[1] || '',
      image_3         : images[2] || '',
      image_4         : images[3] || '',
      sheet_name      : sheet.getName(),
    });
  });

  return profiles;
}

// ── Parsers ──────────────────────────────────────────────────────────────────

/**
 * Detect gender from sheet tab name.
 * Returns 1 (boy) or 2 (girl).
 */
function detectGender(name) {
  var n = name.toLowerCase();
  if (n.indexOf('मुली') !== -1 || n.indexOf('girl') !== -1 || n.indexOf('female') !== -1) return 2;
  if (n.indexOf('मुलग') !== -1 || n.indexOf('boy')  !== -1 || n.indexOf('male')   !== -1) return 1;
  return 1;  // default: boy
}

/**
 * Parse height string to {ft, inches}.
 * Handles: "5'6\"", "5'6", "5.6", "5 6", "168cm", "5"
 */
function parseHeight(raw) {
  raw = raw.replace(/["""'']/g, "'").trim();

  // Pattern: 5'6 or 5'6"
  var m = raw.match(/^(\d)\s*['`]\s*(\d{1,2})/);
  if (m) return { ft: parseInt(m[1]), inches: parseInt(m[2]) };

  // Pattern: 5.6 (dot separator)
  m = raw.match(/^(\d)\.(\d{1,2})$/);
  if (m) return { ft: parseInt(m[1]), inches: parseInt(m[2]) };

  // Pattern: "5 6" (space separated)
  m = raw.match(/^(\d)\s+(\d{1,2})$/);
  if (m) return { ft: parseInt(m[1]), inches: parseInt(m[2]) };

  // Centimeters (e.g. 168)
  m = raw.match(/^(\d{3})\s*cm?$/i);
  if (m) {
    var totalIn = Math.round(parseInt(m[1]) / 2.54);
    return { ft: Math.floor(totalIn / 12), inches: totalIn % 12 };
  }

  // Single digit — feet only
  m = raw.match(/^(\d)$/);
  if (m) return { ft: parseInt(m[1]), inches: 0 };

  return { ft: 5, inches: 0 };  // fallback
}

/**
 * Parse salary to integer (lakhs).
 * Handles "5L", "5 लाख", "500000", "5.5"
 */
function parseSalary(raw) {
  raw = raw.trim().toLowerCase()
           .replace(/लाख|lakh|l\b/g, '')
           .replace(/[^0-9.]/g, '');
  if (!raw) return 0;
  var n = parseFloat(raw);
  // If value looks like full rupees (> 1000), convert to lakhs
  if (n > 1000) n = n / 100000;
  return Math.round(n);
}

/**
 * Normalize birth year.
 * "1995" → "95", "2001" → "01", "95" → "95"
 */
function parseBirthYear(raw) {
  raw = raw.trim().replace(/[^0-9]/g, '');
  if (raw.length === 4) return raw.slice(2);  // 1995 → 95
  if (raw.length === 2) return raw;           // 95 → 95
  if (raw.length === 1) return '0' + raw;     // 1 → 01
  return raw;
}

/**
 * Convert any Google Drive share URL to a direct embeddable URL.
 * Returns '' for non-Drive or empty URLs.
 */
function normalizeDriveUrl(raw) {
  raw = raw.trim();
  if (!raw) return '';

  // Already a direct view URL
  if (raw.indexOf('drive.google.com/uc') !== -1) return raw;

  // Share URL: https://drive.google.com/file/d/FILE_ID/view
  var m = raw.match(/\/file\/d\/([a-zA-Z0-9_-]+)/);
  if (m) return 'https://drive.google.com/uc?export=view&id=' + m[1];

  // Open URL: https://drive.google.com/open?id=FILE_ID
  m = raw.match(/[?&]id=([a-zA-Z0-9_-]+)/);
  if (m) return 'https://drive.google.com/uc?export=view&id=' + m[1];

  // If it's just a file ID (alphanumeric, 25-44 chars)
  if (/^[a-zA-Z0-9_-]{25,44}$/.test(raw)) {
    return 'https://drive.google.com/uc?export=view&id=' + raw;
  }

  // Return as-is (could be a direct image URL)
  return raw;
}

// ── Response helpers ─────────────────────────────────────────────────────────

function ok(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}

function err(message) {
  return ContentService
    .createTextOutput(JSON.stringify({ status: 'error', message: message }))
    .setMimeType(ContentService.MimeType.JSON);
}
