# Auto Sync Files

**TYPO3 13.4 LTS and 14.3 LTS – Auto Sync Files Extension**

| Extension | TYPO3 | PHP | Status |
|---|---|---|---|
| 14.x | 13.4, 14.3 | 8.2 – 8.5 | current |
| 13.0.x | 13.4 | 8.2 – 8.5 | replaced by 14.x |
| 12.0.x | 12.4 | 8.1+ | no longer maintained (branch `12.x`) |

This extension periodically downloads external files via the TYPO3 Scheduler to your local webspace so that you always have the newest version available. It is especially useful for caching external resources and improving performance. Additionally, the extension offers an optional **Download & Extract** mode which downloads a compressed archive (ZIP, TAR, TAR.GZ) and extracts it into a target folder, either merging it with the existing files (default) or replacing them.

---

## Requirements

- **TYPO3:** 13.4 LTS or 14.3 LTS (for TYPO3 12.4 use the 12.0.x releases)
- **PHP:** 8.2 – 8.5
- **TYPO3 System Extensions:** `scheduler`, `extbase`
- **PHP Extensions:**
  - `ext-zip` – required for ZIP archive extraction
  - `ext-phar` – required for TAR / TAR.GZ extraction (enabled by default)
  - `ext-zlib` – needed for `.tar.gz` / `.tgz` archives

---

## Installation

### Via Composer

The package is available on [Packagist](https://packagist.org/packages/id/auto-sync-files):

```bash
composer require id/auto-sync-files:^14.0
```

> Up to version 14.0.0 the Composer package was named `ingeniumdesign/auto-sync-files`. To switch, run `composer remove ingeniumdesign/auto-sync-files` and `composer require id/auto-sync-files:^14.0`. The extension key `auto_sync_files`, the scheduler tasks and their settings stay the same.

Version 14.x works with TYPO3 13.4 and 14.3. For TYPO3 12.4 use `^12.0`.

### Classic mode (without Composer)

1. Download the extension from the [TYPO3 Extension Repository](https://extensions.typo3.org/extension/auto_sync_files) or take the file `auto_sync_files_<version>.zip` attached to a [GitHub release](https://github.com/ingeniumdesign/auto_sync_files/releases). (The "Source code" archives that GitHub generates automatically cannot be uploaded in the Extension Manager.)
2. Install it via **Admin Tools > Extensions** (upload the ZIP) or extract it into `typo3conf/ext/auto_sync_files/`.
3. Activate the extension in **Admin Tools > Extensions**.

---

## Features

- **Download Only:** Download an external file and store it locally.
- **Download & Extract:** Download a compressed archive from a remote URL, extract it, and either merge into or replace the contents of a target folder.
- **Optional Cache Clearing:** Clear the TYPO3 frontend cache after a file change.
- **Hash-Based Skip:** Both tasks skip downstream work when the source hasn't changed (SHA-256 compare). Saves cache flushes on short cron intervals.
- **Multilingual UI:** Backend labels and validation messages are localised (English source, German translation included).
- **Safety Checks:** URL scheme whitelist (only `http://` and `https://`), Zip-Slip checks for ZIP and TAR, target path must be inside the TYPO3 public directory, symlink-safe deletion, incomplete extractions are rejected.
- **Flexible Configuration:** Configure all options via the TYPO3 Scheduler backend (**System > Scheduler**).

---

## Example 1: Download Only – Google Analytics 4 (`gtag.js`)

A classic use case for the Download Only mode is hosting the Google Analytics 4 tag library locally instead of loading it directly from Google's servers. This improves caching behavior and gives you full control over when the file is updated.

### Setup

1. **Add a new Scheduler Task:**
   In the TYPO3 backend, go to **System > Scheduler** and add a new task.
   Select **"Auto Sync Files: Download only"** as the task type.

2. **Configure the Task:**
   - **Download URL:** Set this to the URL of the GA4 tag library, including your Measurement ID.
     _Example:_ `https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX`
   - **Local path:** Set this to the absolute path where the file should be stored. It must be inside the TYPO3 public directory, and its folder must already exist; the field's placeholder shows the public path of your installation.
     _Example:_ `/var/www/html/public/fileadmin/Templates/Assets/JavaScript/gtag.js`
   - **Clear cache:** Enabled by default. The frontend caches are flushed when a new version is detected (only if the downloaded file differs from the stored file).

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
   Select **"Auto Sync Files: Download & extract"** as the task type.

2. **Configure the Task:**
   - **Download URL:** Set this to the URL of the compressed archive (ZIP, TAR, or TAR.GZ).
     _Example:_ `https://example.com/downloads/assets.zip`
   - **Local path:** Specify the **absolute path** to the target folder where the archive should be extracted.
     _Example:_ `/var/www/html/public/fileadmin/sync/external_assets/`

     > ℹ️ The target folder itself is **never** deleted — only its contents are managed according to the **Replace mode** option below. If the folder does not exist yet, it will be created on first run; its parent folder must already exist and be writable (e.g. create `fileadmin/sync/` first).

   - **Replace mode:** Controls how the existing contents of the target folder are handled.
     - **Off (default):** *Merge mode* — files from the archive overwrite same-named entries in the target folder; additional files already present in the target folder are kept untouched.
     - **On:** *Replace mode* — all existing files and subfolders inside the target folder are deleted and replaced by the archive contents, leaving a 1:1 copy of the archive. The deletion only happens after the archive has been extracted successfully; if extraction fails, the target folder is left untouched.

     > ⚠️ **Warning (Replace mode):** Any files you may have placed manually in the target folder will be lost on the next run. Always use a dedicated subfolder — never set the local path to the root of `/fileadmin/` or any other critical directory. Replace mode is refused for the TYPO3 public directory itself and for `typo3temp/`; Merge mode into the public directory is allowed.

   - **Clear cache:** Enabled by default. The frontend caches are flushed after the target folder has been updated.

3. **Result:**
   On each scheduled run the archive is downloaded, hashed against the last successful run (skipped entirely if unchanged), extracted into a temporary directory, and then either merged into or replacing the contents of the target folder — depending on your Replace mode setting.

---

## How It Works

- **Download Only Task:**
  Downloads the specified external file. If a new version is detected (SHA-256 comparison with the existing local file), it replaces the old file and optionally clears the cache.

- **Download & Extract Task:**
  Downloads a compressed archive and extracts it into a temporary directory. ZIP files are compared with the size and CRC32 checksum stored in the archive, TAR / TAR.GZ files only with the size in the TAR header; an empty archive counts as an error. Only if the extraction succeeded completely is the target folder updated: in Replace mode its current contents are deleted first, in Merge mode (default) the extracted files are merged in. The archive hash is stored only after a fully successful run, so a failed run is retried on the next execution.

**Logging:** Errors during execution are logged via the TYPO3 LogManager. With the default configuration they end up in `var/log/typo3_*.log` (classic mode: `typo3temp/var/log/`). Logging can be configured in `config/system/additional.php` (classic mode: `typo3conf/system/additional.php`). The log channel depends on the TYPO3 version: on TYPO3 13.4 the tasks log via the scheduler's channel `TYPO3\CMS\Scheduler\Task\AbstractTask`, so configure writers under `$GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3']['CMS']['Scheduler']` (this also affects other scheduler tasks); on TYPO3 14.3 they log via their own class names, so a configuration under `$GLOBALS['TYPO3_CONF_VARS']['LOG']['ID']['AutoSyncFiles']` works there.

---

## Important Notes

- **Data Loss Warning:**
  When using the Download & Extract mode **with Replace mode enabled**, the entire contents of the specified local folder will be deleted before the new files are placed. In default Merge mode, only same-named files are overwritten — additional files remain untouched.
  **Never set the local path to the root of critical directories like `/fileadmin/`** — only use dedicated subfolders.

- **Configuration:**
  Both task types are configured via the TYPO3 Scheduler. Ensure you provide a valid URL and an absolute local path.

- **Supported Archive Formats:**
  ZIP (`.zip`), TAR (`.tar`), and TAR.GZ (`.tar.gz`, `.tgz`). ZIP is the most reliable format (see "Known Limitations").

---

## Known Limitations

- **TAR / TAR.GZ archives whose paths start with `./`** (e.g. created with `tar czf archive.tgz ./*`) are rejected with "The archive does not contain any files". Versions up to 12.0.6 extracted such archives (archives created from `.`, e.g. `tar czf archive.tgz .`, never worked). Create the archive from inside the folder without the `./` prefix (e.g. `cd folder && tar -czf ../archive.tgz -- *`; `*` does not include hidden files) or use ZIP.
- **TAR has no checksum per file:** damaged content in a plain `.tar` is not detected, and a TAR that is cut off exactly at a file boundary is accepted with fewer files (this also applies to a cut-off `.tar.gz`; damaged compressed data in a `.tar.gz` is detected). Symbolic and hard links are not supported: they are extracted as empty files or make the extraction fail. For Replace mode, ZIP is the most reliable format.
- A ZIP archive that contains only folders and no files is not treated as empty. In Replace mode the target folder then only contains these empty folders.
- On Windows servers, ZIP archives whose folder entries end with `\` (created by old .NET tools) fail the verification.
- If Replace mode has already emptied the target folder and moving the new files fails (e.g. disk full or missing permissions), the target folder stays empty or partly filled until the next successful run.
- Replace mode is only refused for the TYPO3 public directory itself and for `typo3temp/`. Other system folders (e.g. `typo3conf/` in classic mode) are not protected; never use them as target folder.
- Symbolic links in the local path are resolved: the real path must be inside the TYPO3 public directory. Folders that link to a location outside it (e.g. a shared `fileadmin` in deployment setups) cannot be used as target.
- Download only: if the server answers with status 200 but sends an empty file, the local file is replaced by an empty file.
- TYPO3 14.3: the tasks are still registered the classic way (`SC_OPTIONS` and additional field providers). This works, but TYPO3 14 logs a deprecation message whenever a task form is opened or saved. TYPO3 v15 will need native TCA task types ([#7](https://github.com/ingeniumdesign/auto_sync_files/issues/7)).

---

## Upgrading from TYPO3 13.4 to 14.3

Version 14.x supports both TYPO3 versions, so the extension can stay the same while you upgrade the core. TYPO3 14 stores scheduler tasks in a new format; an upgrade wizard converts existing tasks. Task UIDs and settings are kept.

1. On TYPO3 13.4, update this extension to 14.x first (Composer: `^14.0`).
2. Pause the scheduler cron job.
3. Upgrade the TYPO3 core to 14.3 and run the database compare.
4. Run the upgrade wizard "Migrate the contents of the tx_scheduler_task database table into a more structured form." (identifier `schedulerDatabaseStorageMigration`) in **Admin Tools > Upgrade**, or `vendor/bin/typo3 upgrade:run schedulerDatabaseStorageMigration`.
5. Check the task list in **System > Scheduler**. Tasks that were due while the wizard had not run yet are disabled by TYPO3; enable them again, their settings are kept.
6. Start the scheduler cron job again.

---

## Upgrading from TYPO3 12.4 (extension 12.0.x)

Version 14.x supports TYPO3 13.4 and 14.3; upgrade from TYPO3 12.4 to 13.4 first. Existing scheduler tasks keep working: task classes and their settings are unchanged.

1. Pause the scheduler cron job while you upgrade the TYPO3 core.
2. Update TYPO3 and this extension in the same step (Composer: `^14.0`; classic mode: install 14.x before the scheduler runs on TYPO3 13.4 for the first time). The scheduler disables tasks whose class cannot be loaded, so the extension must be available before the first scheduler run.
3. Flush all caches, then check the task list in **System > Scheduler**: all Auto Sync Files tasks should still be enabled.
4. If a task shows up as disabled anyway, enable it again; its settings are kept.
5. Download & Extract tasks created with 12.0.5 or older always replaced the contents of the target folder. Since 12.0.6 these tasks run in Merge mode. If you want the old behaviour, open the task and enable **Replace mode** before its first run after the upgrade (otherwise that run stores the archive hash, and later runs skip the unchanged archive).
6. Download & Extract tasks that use TAR / TAR.GZ archives with paths starting with `./` fail since 12.0.7; see "Known Limitations".
7. Tasks created with version 1.0.0 (class `ID\AutoSyncFiles\Task\Task`) cannot be loaded any more and must be created again.

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
