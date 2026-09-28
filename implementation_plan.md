# Implementation Plan: 4 Changes to MK Brahman

## Summary

Four changes across the codebase: (1) salary display in thousands like Instagram, (2) two new columns `varn` and `chashma`, (3) new `registration_year` column for nondani kramank, (4) fix the broken image delete button on edit page.

---

## Change 1: पगार (Salary) — Display in Thousands Format (30k, 80k, 1.5L)

Salary is stored in thousands (e.g., `30` = ₹30,000, `300` = ₹3,00,000). Currently displayed as `30L` which is wrong — it should show `30k`, `300k`, `1.5L` etc.

### Proposed Changes

#### [MODIFY] [format-helpers.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/includes/format-helpers.php)
- Add a new `fmtSalaryShort(int $val): string` function:
  - `0` → `—`
  - `< 100` → `{val}k` (e.g., `30` → `30k`, `80` → `80k`)
  - `100` to `999` → `{val}k` (e.g., `300` → `300k`)
  - `>= 1000` → convert to lakhs: if divisible by 100 → `{val/100}L` (e.g., `1500` → `15L`), else → `{val/100}L` with decimal (e.g., `1550` → `15.5L`)
  
> [!IMPORTANT]
> The salary is stored in **thousands**. So `30` means ₹30,000 = 30k, and `1500` means ₹15,00,000 = 15L. Please confirm this interpretation is correct.

#### [MODIFY] [admin-dashboard.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/admin-dashboard.php) — Line 315
- Replace `<?= htmlspecialchars($row['salary']) ?>L` → `<?= fmtSalaryShort((int)$row['salary']) ?>`

#### [MODIFY] [index.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/index.php) — Line 275
- Replace `<?= htmlspecialchars($row['salary']) ?>L` → `<?= fmtSalaryShort((int)$row['salary']) ?>`

#### [MODIFY] [shortlisted.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/shortlisted.php) — Line 141
- Replace `<?= htmlspecialchars($row['salary']) ?>L` → `<?= fmtSalaryShort((int)$row['salary']) ?>`

#### [MODIFY] [search.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/search.php) — Line 239
- Replace `<?= htmlspecialchars($row['salary']) ?>L` → `<?= fmtSalaryShort((int)$row['salary']) ?>`

#### [MODIFY] [profile.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/profile.php) — Line 68-71
- Update the `fmtSalary()` local function to use the new format:
  - `0` → `उपलब्ध नाही`
  - Otherwise → `fmtSalaryShort($val) . '/वर्ष'`

---

## Change 2: New Columns — वर्ण (Varn/Color) & चष्मा (Chashma/Glasses)

Two new DB columns: `varn` (free text for skin color) and `chashma` (yes/no for glasses).

### Proposed Changes

#### [MODIFY] [db.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/includes/db.php)
- Add auto-migration for both new columns:
  - `varn VARCHAR(50) DEFAULT NULL` after `weight`
  - `chashma TINYINT(1) NOT NULL DEFAULT 0` after `varn`

#### [MODIFY] [setup.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/setup.php)
- Add `varn` and `chashma` columns to the `CREATE TABLE` schema.

#### [MODIFY] [add-profile.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/add-profile.php)
- Add `varn` text field and `chashma` dropdown (हो/नाही) to the form.
- Include both in the INSERT query.

#### [MODIFY] [edit-profile.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/edit-profile.php)
- Add `varn` text field and `chashma` dropdown to the edit form.
- Include both in the UPDATE query.

#### [MODIFY] [profile.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/profile.php)
- Display `वर्ण` and `चष्मा` in the personal info section.

#### [MODIFY] [upload-csv.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/upload-csv.php)
- Add `varn` and `chashma` to `ALL_COLS` so CSV import supports them.

#### [MODIFY] [export-shortlisted.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/export-shortlisted.php)
- Add वर्ण and चष्मा to CSV export columns.

---

## Change 3: नोंदणी क्रमांक (Registration Number) — `year.regNo` Format

A new `registration_year` column will store the registration year (e.g., `1993`). The display format will be `registrationYear.registrationNo` (e.g., `1993.01`). The existing `birth_year.regNo` combo in `fmtBirthReg()` will be replaced.

### Proposed Changes

#### [MODIFY] [db.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/includes/db.php)
- Add auto-migration for `registration_year VARCHAR(4) DEFAULT NULL` after `registration_no`.

#### [MODIFY] [setup.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/setup.php)
- Add `registration_year` column to schema.

#### [MODIFY] [format-helpers.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/includes/format-helpers.php)
- Update `fmtBirthReg()` → rename to `fmtNondaniKramank(string $regYear, string $regNo): string`
  - Returns `$regYear . '.' . $regNo` (e.g., `1993.01`)
  - Falls back to just `$regNo` if `$regYear` is empty.

#### [MODIFY] [admin-dashboard.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/admin-dashboard.php)
- Add `registration_year` to the SELECT query.
- Update display column to use `fmtNondaniKramank($row['registration_year'], $row['registration_no'])`.
- Update column header label from `जन्म / रजिस्टर no` to `नोंदणी क्र.`.

#### [MODIFY] [index.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/index.php)
- Add `registration_year` to SELECT query.
- Update display to use `fmtNondaniKramank()`.

#### [MODIFY] [shortlisted.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/shortlisted.php)
- Add `registration_year` to SELECT query.
- Update display to use `fmtNondaniKramank()`.

#### [MODIFY] [search.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/search.php)
- Add `registration_year` to SELECT query.
- Update display to use `fmtNondaniKramank()`.

#### [MODIFY] [add-profile.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/add-profile.php)
- Add `registration_year` text field to the form (label: "नोंदणी वर्ष").
- Include in INSERT query.

#### [MODIFY] [edit-profile.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/edit-profile.php)
- Add `registration_year` text field to the form.
- Include in UPDATE query.

#### [MODIFY] [upload-csv.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/upload-csv.php)
- Add `registration_year` to `ALL_COLS`.

#### [MODIFY] [export-shortlisted.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/export-shortlisted.php)
- Add `registration_year` to CSV export.

---

## Change 4: Fix — Image Delete Cross Button Not Working on Edit Page

### Root Cause

In [edit-profile.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/edit-profile.php#L32-L55), the delete handler has a **double-delete bug**:

```php
// Line 40-44: Three delete attempts — first is wrong, second and third are redundant
$conn->prepare("DELETE FROM profile_images WHERE id = ?")->execute() ||    // ← wrong: no bind
$conn->query("DELETE FROM profile_images WHERE id = $imgId");               // ← runs always, deletes
$del = $conn->prepare("DELETE FROM profile_images WHERE id = ?");           // ← redundant
$del->bind_param('i', $imgId);
$del->execute();
```

The first `prepare()->execute()` on line 40 calls `execute()` **without binding** `$imgId`, which throws an error or executes improperly. The `||` fallback then triggers a raw SQL query. Then a third delete is attempted. This chain likely causes a `mysqli` exception that prevents the JSON response from being sent back, so the JavaScript `deleteImg()` function never gets `{ok: true}` back, and the card is never removed from the DOM.

### Proposed Fix

#### [MODIFY] [edit-profile.php](file:///Users/kunalgurav/Desktop/Coding%20Stuff/mk_bramhan/mk_bramhan/edit-profile.php#L38-L51)
Replace lines 38-51 with a clean single delete:

```php
if ($row) {
    deleteProfileImage($row['filename']);
    $del = $conn->prepare("DELETE FROM profile_images WHERE id = ?");
    $del->bind_param('i', $imgId);
    $del->execute();
    // Update primary image on profiles table
    $imgs = fetchImages($conn, $id);
    $primary = $imgs[0]['filename'] ?? null;
    $up = $conn->prepare("UPDATE profiles SET profile_image = ? WHERE id = ?");
    $up->bind_param('si', $primary, $id);
    $up->execute();
}
```

---

## Verification Plan

### Manual Verification
1. **Salary display**: Check listing pages (index, admin-dashboard, shortlisted, search) show values like `30k`, `300k`, `1.5L` instead of `30L`.
2. **Varn/Chashma**: Add a new profile with varn and chashma fields → verify they appear on the profile detail page.
3. **Nondani Kramank**: Edit a profile to set `registration_year` → verify listing pages show `1993.01` format.
4. **Image delete**: Go to edit page → click the ✕ button → verify the image card is removed without page reload.
