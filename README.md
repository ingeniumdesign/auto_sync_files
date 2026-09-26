# Auto Sync Files

**TYPO3 12.4.x – Auto Sync Files Extension**

This extension periodically downloads external files via the TYPO3 Scheduler to your local webspace so that you always have the newest version available. It is especially useful for caching external resources and improving performance. Additionally, the extension offers an optional **Download & Extract** mode which downloads a compressed archive (ZIP, TAR, TAR.GZ) and extracts its contents, replacing existing files.

---

## Requirements

- **TYPO3:** 12.4.x (only — no support for v10, v11, v13 or v14)
- **PHP:** 8.1 or higher (enforced via `composer.json`)
- **TYPO3 System Extensions:** `scheduler`, `extbase`
- **PHP Extensions:**
  - `ext-zip` – required for ZIP archive extraction
  - `ext-phar` – required for TAR / TAR.GZ extraction (enabled by default)

---

## Installation

### Via Composer (recommended)

```bash
composer require ingeniumdesign/auto-sync-files
```

### Manual

1. Download the extension from the [TYPO3 Extension Repository](https://extensions.typo3.org/) or [GitHub](https://github.com/ingeniumdesign/auto_sync_files/).
2. Extract the archive into `typo3conf/ext/auto_sync_files/`.
3. Activate the extension in the TYPO3 backend under **Admin Tools > Extensions**.

---

## Features

- **Download Only:** Download an external file and store it locally.
- **Download & Extract:** Download a compressed archive from a remote URL, extract it, and either merge into or replace the contents of a target folder.
- **Optional Cache Clearing:** Clear the TYPO3 frontend cache after a file change.
- **Hash-Based Skip:** Both tasks skip downstream work when the source hasn't changed (SHA-256 compare). Saves cache flushes on short cron intervals.
- **Multilingual UI:** Backend labels and validation messages are localised (English source, German translation included).
- **Security Hardening:** SSRF schema whitelist (http/https), Zip-Slip protection for ZIP and TAR, path validation against TYPO3 public root, symlink-safe deletion.
- **Flexible Configuration:** Configure all options via the TYPO3 Scheduler backend (**System > Scheduler**).

---

## Example 1: Download Only – Google Analytics 4 (`gtag.js`)

A classic use case for the Download Only mode is hosting the Google Analytics 4 tag library locally instead of loading it directly from Google's servers. This improves caching behavior and gives you full control over when the file is updated.

### Setup

1. **Add a new Scheduler Task:**
   In the TYPO3 backend, go to **System > Scheduler** and add a new task.
   Select **"Auto Sync Files: Download Only"** as the task type.

2. **Configure the Task:**
   - **Download URL:** Set this to the URL of the GA4 tag library, including your Measurement ID.
     _Example:_ `https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX`
   - **Local Path:** Set this to the absolute path where the file should be stored.
     _Example:_ `/var/www/html/fileadmin/Templates/Assets/JavaScript/gtag.js`
   - **Clear Cache:** Tick the checkbox if you want the frontend cache to be cleared when a new version is detected (cache is only cleared if the downloaded file differs from the stored file).

3. **Include the Synced File in TypoScript:**
   ```typoscript
   page.includeJSFooter.googleAnalytics = fileadmin/Templates/Assets/JavaScript/gtag.js
   ```

4. **Initialize Google Analytics 4:**
   Add the GA4 initialization snippet to your template. It uses the locally hosted `gtag.js` included above:
   ```javascript
   window.dataLayer = window.dataLayer || [];
   function gtag(){ dataLayer.push(arguments); }
   gtag('js', new Date());
   gtag('config', 'G-XXXXXXXXXX', {
       'anonymize_ip': true
   }); // Replace G-XXXXXXXXXX with your GA4 Measurement ID
   ```

> **Note on local GA4 hosting:** The `gtag.js` loader may dynamically request additional Google scripts at runtime, so local hosting reduces the *initial* third-party request but does not eliminate all external calls. For fully first-party tracking, consider a server-side tagging setup or a privacy-friendly alternative such as [Matomo](https://matomo.org/).

---

## Example 2: Download & Extract – External Asset Archive

A common use case is to keep a folder in your TYPO3 project in sync with a remotely hosted archive — for example a third-party asset bundle, a corporate design package, or any other set of files that gets updated periodically on an external server.

### Setup

1. **Add a new Scheduler Task:**
   In the TYPO3 backend, go to **System > Scheduler** and add a new task.
   Select **"Auto Sync Files: Download & Extract"** as the task type.

2. **Configure the Task:**
   - **Download URL:** Set this to the URL of the compressed archive (ZIP, TAR, or TAR.GZ).
     _Example:_ `https://example.com/downloads/assets.zip`
   - **Local Path:** Specify the **absolute path** to the target folder where the archive should be extracted.
     _Example:_ `/var/www/html/fileadmin/sync/external_assets/`

     > ℹ️ The target folder itself is **never** deleted — only its contents are managed according to the **Replace Mode** option below. If the folder does not exist yet, it will be created on first run.

   - **Replace Mode (Zielordner komplett ersetzen):** Controls how the existing contents of the target folder are handled.
     - **Unchecked (default):** *Merge mode* — files from the archive overwrite same-named entries in the target folder; additional files already present in the target folder are kept untouched.
     - **Checked:** *Replace mode* — all existing files and subfolders inside the target folder are deleted and replaced by the archive contents, leaving a 1:1 copy of the archive. The deletion only happens after the archive has been extracted successfully; if extraction fails, the target folder is left untouched.

     > ⚠️ **Warning (Replace mode):** Any files you may have placed manually in the target folder will be lost on the next run. Always use a dedicated subfolder — never set the local path to the root of `/fileadmin/` or any other critical directory. Replace mode is refused for the TYPO3 public directory itself and for `typo3temp/`, because it would delete the TYPO3 installation; Merge mode into the public directory is allowed.

   - **Clear Cache:** Tick the checkbox to clear the TYPO3 frontend cache after the update.

3. **Result:**
   On each scheduled run the archive is downloaded, hashed against the last successful run (skipped entirely if unchanged), extracted into a temporary directory, and then either merged into or replacing the contents of the target folder — depending on your Replace Mode setting.

---

## How It Works

- **Download Only Task:**
  Downloads the specified external file. If a new version is detected (SHA-256 comparison with the existing local file), it replaces the old file and optionally clears the cache.

- **Download & Extract Task:**
  Downloads a compressed archive and extracts it into a temporary directory. Every extracted file is verified against the archive (size, and for ZIP also the CRC32 checksum); an empty archive counts as an error. Only if the extraction succeeded completely is the target folder updated: in Replace mode its current contents are deleted first, in Merge mode (default) the extracted files are merged in. The archive hash is stored only after a fully successful run, so a failed run is retried on the next execution.

**Logging:** Errors during execution are logged via the TYPO3 LogManager. With the default configuration they end up in `var/log/typo3_*.log` (classic mode: `typo3temp/var/log/`). Logging can be configured in `config/system/additional.php` (classic mode: `typo3conf/system/additional.php`).

---

## Important Notes

- **Data Loss Warning:**
  When using the Download & Extract mode **with Replace Mode enabled**, the entire contents of the specified local folder will be deleted before the new files are placed. In default Merge mode, only same-named files are overwritten — additional files remain untouched.
  **Never set the Local Path to the root of critical directories like `/fileadmin/`** — only use dedicated subfolders.

- **Configuration:**
  Both task types are configured via the TYPO3 Scheduler. Ensure you provide a valid URL and an absolute local path.

- **Supported Archive Formats:**
  ZIP (`.zip`), TAR (`.tar`), and TAR.GZ (`.tar.gz`, `.tgz`).

---

## License

This extension is released under the **MIT License**. See the [LICENSE](LICENSE) file for full details.

---

## Contact & Communication

### GitHub

[Auto Sync Files on GitHub](https://github.com/ingeniumdesign/auto_sync_files/)

### Agency

**INGENIUMDESIGN**
TYPO3 – Internetagentur
65510 Idstein
[https://www.ingeniumdesign.de/](https://www.ingeniumdesign.de/)
info@ingeniumdesign.de

### Donate

- **Amazon:** [Amazon Wishlist](https://www.amazon.de/hz/wishlist/ls/13RT2BFNRP05)
- **PayPal:** [paypal.me/INGENIUMDESIGN](https://www.paypal.me/INGENIUMDESIGN/)

---

## Live References

We are searching for live references or live examples of the Auto Sync Files Extension. Please contact us if you're using it!

**Links/References:**
[https://www.baukasten-typo3.de/](https://www.baukasten-typo3.de/) – by INGENIUMDESIGN
