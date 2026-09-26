# Changelog

All notable changes to this extension are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
The major version follows the supported TYPO3 version: **13.x = TYPO3 13.4 LTS**, 12.0.x = TYPO3 12.4, 1.0.0 = TYPO3 8.7.

## [14.0.0] - 2026-09-26

TYPO3 14.3 LTS support ([#6]). Version 14.x supports **TYPO3 13.4 and 14.3** with the same code; the code for TYPO3 13.4 is unchanged.

### Added
- TYPO3 14.3 LTS support. The tasks keep the classic registration (`SC_OPTIONS` and additional field providers); on TYPO3 14.3 this works, but TYPO3 logs a deprecation message when a task form is opened or saved. Native TCA task types follow before TYPO3 v15 ([#7]).

### Changed
- Constraints: TYPO3 `^13.4 || ^14.3` (`composer.json`) and `13.4.0-14.3.99` (`ext_emconf.php`); PHP stays 8.2 or newer (TYPO3 13.4 and 14.3 both support PHP 8.2 – 8.5).
- `composer.json` declares the extension version and `providesPackages`, so TYPO3 14 no longer needs `ext_emconf.php`; the description starts with the title "Auto Sync Files".

### Fixed
- Task forms on TYPO3 14.3: switches showed "on" when an existing task was edited, because TYPO3 14 passes the stored values ('on' / 'off') to the form. Saving such a task unchanged would have enabled Replace mode.
- Task forms on TYPO3 14.3: new tasks start with "Clear cache" enabled, as on TYPO3 13.4.

### Upgrade notes
- TYPO3 13.4 installations can update from 13.0.x without further steps.
- Upgrading the core from 13.4 to 14.3: update the extension to 14.x first, then run the TYPO3 upgrade wizard `schedulerDatabaseStorageMigration`. Details: README, section "Upgrading from TYPO3 13.4 to 14.3".

## [13.0.1] - 2026-09-26

Documentation release; the code is unchanged since 13.0.0.

### Documentation
- README: Composer installation via [Packagist](https://packagist.org/packages/ingeniumdesign/auto-sync-files); the GitHub VCS repository entry is no longer needed.

## [13.0.0] - 2026-09-26

TYPO3 13.4 LTS support ([#5]). TYPO3 12.4 installations use the 12.0.x releases (last release: [12.0.7]).

### Changed
- **Requires TYPO3 13.4 LTS and PHP 8.2 or newer** (`composer.json`, `ext_emconf.php`; TYPO3 13.4 supports PHP 8.2 – 8.5). TYPO3 12.4 is no longer supported by 13.x.
- Extension icon moved to `Resources/Public/Icons/Extension.svg`; TYPO3 13.4 no longer reads `ext_icon.svg` from the extension root.
- `ext-zlib` is suggested (needed for `.tar.gz` / `.tgz` archives).
- Scheduler task forms use the field types of the TYPO3 13.4 backend (text fields and switches). Each task type has its own field IDs, so labels no longer point to the hidden field of the other task type in the "Add task" form.
- Language files use XLIFF 1.2 like the TYPO3 core. All English and German texts were revised: consistent terms, German written as German instead of word-for-word translations, and the Download & Extract description no longer suggests that extraction is optional.

### Fixed
- Download & Extract: incomplete or damaged ZIP archives are treated as a failure. The result of `extractTo()` is checked, and every extracted file is compared with the size and CRC32 checksum stored in the archive. TAR / TAR.GZ files are only compared with the size in the TAR header (see README, "Known Limitations"). An empty archive counts as an error.
- Download & Extract: the target folder is only touched after the archive has been extracted completely. A failed extraction leaves the target folder untouched, and the next run tries again.
- Download & Extract: Replace mode also works when the target folder and `typo3temp/` are on different file systems (e.g. `fileadmin` as a separate volume). The emptied target folder is now filled like in Merge mode instead of renaming whole directories.
- Download & Extract: the stored archive hash is removed before the target folder is changed, so a run that aborts halfway is not skipped next time.
- Download & Extract: when a ZIP entry cannot be read or written, the log says so instead of "(No error)".
- Task forms keep the entered values when the form is shown again after a validation error.
- Leading and trailing spaces in the download URL and the local path are removed before the task is saved. Before, a URL with a trailing space passed the form check but failed on every run, and a path with a trailing space created a second file or folder.
- The log message for a target path outside the public directory now also mentions that the parent folder must exist.

### Added
- Replace mode is refused for the TYPO3 public directory itself and for `typo3temp/` (checked when saving the task and at runtime). Merge mode into the public directory stays allowed.
- This changelog.

### Documentation
- README: requirements for TYPO3 13.4, Composer installation via the GitHub VCS repository, classic-mode installation with the ZIP attached to GitHub releases, new section "Upgrading from TYPO3 12.4" (including the Merge mode change for tasks from 12.0.5 or older), logging configuration in `config/system/additional.php`, the parent folder of a target folder must exist, new section "Known Limitations".

### Upgrade notes
- Existing scheduler tasks keep working: task classes and their settings are unchanged.
- Pause the scheduler cron job during the core upgrade and install 13.x before the scheduler runs on TYPO3 13.4 for the first time; the scheduler disables tasks whose class cannot be loaded.
- Details: README, section "Upgrading from TYPO3 12.4 (extension 12.0.x)".

## [12.0.7] - 2026-09-26

Final release for TYPO3 12.4 (branch `12.x`).

### Fixed
- Download & Extract: incomplete or damaged ZIP archives are treated as a failure (result of `extractTo()` checked, every extracted file compared with size and CRC32). TAR / TAR.GZ files are only compared with the size in the TAR header. An empty archive counts as an error.
- Download & Extract: the target folder is only touched after the archive has been extracted completely; a failed extraction leaves it untouched and is retried.

### Added
- Replace mode is refused for the TYPO3 public directory itself and for `typo3temp/`.

### Documentation
- README: SHA-256 comparison for Download Only, logging configuration in `config/system/additional.php`.

## [12.0.6] - 2026-05-16

Security and stability release ([#4]).

### Security
- URL scheme whitelist: only `http://` and `https://` downloads.
- The target path must be inside the TYPO3 public directory and must not be a symlink.
- Zip-Slip checks for ZIP and TAR entries before extraction.
- Symlink-safe deletion and merging.
- Unique temporary paths per task and run (safe for parallel runs).

### Added
- Replace Mode checkbox for Download & Extract. **The default is Merge mode** (behaviour change: 12.0.5 always replaced the contents of the target folder).
- Hash-based skip for archives: an unchanged archive is not extracted again (SHA-256 hash stored in the TYPO3 registry). Download Only now compares SHA-256 hashes.
- Localisation of all labels, validation and log messages (English source, German translation); backend flash messages and URL format validation when saving a task.
- `composer.json` (PHP ^8.1).

### Changed
- Streaming downloads with constant memory usage.
- Download and hash check happen before the target folder is touched; temporary files are always cleaned up.
- Code modernisation for PHP 8.1 (shared `AutoSyncTaskTrait`, `match` expressions); deprecated `ext_emconf.php` keys removed; `.gitignore` for an extension repository.
- README: Google Analytics 4 example, requirements, installation and license sections.

## [12.0.5] - 2026-02-05

### Added
- TAR and TAR.GZ support (`.tar`, `.tar.gz`, `.tgz`) with archive format detection by file extension.

### Changed
- TYPO3 `RequestFactory` instead of `file_get_contents()`.
- PHP `ZipArchive` / `PharData` instead of `shell_exec('unzip')`.
- Error logging via the TYPO3 LogManager; typed properties and `declare(strict_types=1)`.
- Removed the deprecated `clearCacheOnLoad` from `ext_emconf.php`.

## [12.0.4] - 2025-03-16

Published in the TER only (no Git tag).

### Changed
- Download & Extract: reworked extraction into the target folder with detailed logging to `typo3temp/auto_sync_files.log`.

## [12.0.3] - 2025-03-02

TYPO3 12.4 support ([#3]) and the new Download & Extract task ([#2]).

### Added
- Download & Extract task for ZIP archives.

### Changed
- Refactored for TYPO3 12.4, improved scheduler form fields and validation, protection against deleting critical directories.
- Issue [#1] ("Execution of task failed") was closed with this release.

## [1.0.0] - 2018-12-05

Initial release for TYPO3 8.7: a scheduler task that periodically downloads a file into the local web space (published in the TER). Includes the fix for tasks failing on some servers and the TYPO3 base-path helper in the task form.

[14.0.0]: https://github.com/ingeniumdesign/auto_sync_files/releases/tag/14.0.0
[13.0.1]: https://github.com/ingeniumdesign/auto_sync_files/releases/tag/13.0.1
[13.0.0]: https://github.com/ingeniumdesign/auto_sync_files/releases/tag/13.0.0
[12.0.7]: https://github.com/ingeniumdesign/auto_sync_files/releases/tag/12.0.7
[12.0.6]: https://github.com/ingeniumdesign/auto_sync_files/releases/tag/12.0.6
[12.0.5]: https://github.com/ingeniumdesign/auto_sync_files/releases/tag/12.0.5
[12.0.4]: https://extensions.typo3.org/extension/auto_sync_files
[12.0.3]: https://github.com/ingeniumdesign/auto_sync_files/releases/tag/12.0.3
[1.0.0]: https://extensions.typo3.org/extension/auto_sync_files
[#1]: https://github.com/ingeniumdesign/auto_sync_files/issues/1
[#2]: https://github.com/ingeniumdesign/auto_sync_files/issues/2
[#3]: https://github.com/ingeniumdesign/auto_sync_files/issues/3
[#4]: https://github.com/ingeniumdesign/auto_sync_files/issues/4
[#5]: https://github.com/ingeniumdesign/auto_sync_files/issues/5
[#6]: https://github.com/ingeniumdesign/auto_sync_files/issues/6
[#7]: https://github.com/ingeniumdesign/auto_sync_files/issues/7
