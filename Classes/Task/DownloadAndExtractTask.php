<?php

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;

class DownloadAndExtractTask extends AbstractTask
{
    public $auto_sync_files_file_url = '';
    public $auto_sync_files_local_path = '';
    public $auto_sync_files_clear_cache = '';

    public function execute(): bool
    {
        if ($this->auto_sync_files_local_path === '' || $this->auto_sync_files_file_url === '') {
            return false;
        }

        // Download des Archivs
        $archiveContent = @file_get_contents($this->auto_sync_files_file_url);
        if ($archiveContent === false) {
            return false;
        }

        // Temporäre Datei und Verzeichnis definieren
        $tempFile = Environment::getPublicPath() . '/typo3temp/auto_sync_files_archive.zip';
        $tempExtractDir = Environment::getPublicPath() . '/typo3temp/auto_sync_files_extract/';

        if (@file_put_contents($tempFile, $archiveContent) === false) {
            return false;
        }
        if (!is_dir($tempExtractDir)) {
            mkdir($tempExtractDir, 0755, true);
        }

        // Archiv entpacken
        $unzipCommand = 'unzip -o ' . escapeshellarg($tempFile) . ' -d ' . escapeshellarg($tempExtractDir);
        shell_exec($unzipCommand);

        // Sicherheitsprüfung: Nicht das fileadmin-Root löschen!
        $publicPath = Environment::getPublicPath();
        $fileadminRoot = realpath($publicPath . '/fileadmin');
        $targetRealPath = realpath($this->auto_sync_files_local_path);
        if ($targetRealPath !== false && $fileadminRoot !== false) {
            if ($targetRealPath === $fileadminRoot) {
                throw new \RuntimeException('Das Löschen des fileadmin-Root-Verzeichnisses ist nicht erlaubt.');
            }
        }

        // Altes Zielverzeichnis löschen und neu anlegen
        if (is_dir($this->auto_sync_files_local_path)) {
            shell_exec('rm -rf ' . escapeshellarg($this->auto_sync_files_local_path));
        }
        mkdir($this->auto_sync_files_local_path, 0755, true);

        // Entpackte Dateien ins Ziel kopieren
        shell_exec('cp -r ' . escapeshellarg($tempExtractDir) . '/* ' . escapeshellarg($this->auto_sync_files_local_path));

        if ($this->auto_sync_files_clear_cache === 'on') {
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $cacheManager->flushCachesInGroup('pages');
        }

        // Temporäre Dateien entfernen
        shell_exec('rm -rf ' . escapeshellarg($tempExtractDir));
        @unlink($tempFile);

        return true;
    }
}
