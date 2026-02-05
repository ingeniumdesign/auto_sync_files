<?php

declare(strict_types=1);

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;

class DownloadAndExtractTask extends AbstractTask
{
    public string $auto_sync_files_file_url = '';
    public string $auto_sync_files_local_path = '';
    public string $auto_sync_files_clear_cache = '';

    public function execute(): bool
    {
        if ($this->auto_sync_files_local_path === '' || $this->auto_sync_files_file_url === '') {
            $this->log("FEHLER: Kein Zielpfad oder Download-URL angegeben.");
            return false;
        }

        // Überprüfe, ob das Zielverzeichnis existiert
        if (is_dir($this->auto_sync_files_local_path)) {
            // Prüfe, ob Schreibrechte vorhanden sind
            if (!is_writable($this->auto_sync_files_local_path)) {
                $this->log("FEHLER: Keine Schreibrechte für das bestehende Verzeichnis: " . $this->auto_sync_files_local_path);
                return false;
            }
            // Falls vorhanden, lösche den Inhalt
            $this->deleteFolderContents($this->auto_sync_files_local_path);
        } else {
            // Prüfe, ob das übergeordnete Verzeichnis existiert
            $parentDir = dirname($this->auto_sync_files_local_path);
            if (!is_writable($parentDir)) {
                $this->log("FEHLER: Keine Schreibrechte für das übergeordnete Verzeichnis: " . $parentDir);
                return false;
            }

            // Versuche, das Verzeichnis zu erstellen
            if (!mkdir($this->auto_sync_files_local_path, 0755, true)) {
                $this->log("FEHLER: Konnte das Zielverzeichnis nicht erstellen: " . $this->auto_sync_files_local_path);
                return false;
            }
        }

        // Download der Archiv-Datei
        try {
            $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
            $response = $requestFactory->request($this->auto_sync_files_file_url, 'GET');
            if ($response->getStatusCode() !== 200) {
                $this->log("FEHLER: HTTP Status " . $response->getStatusCode());
                return false;
            }
            $archiveContent = $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->log("FEHLER: Konnte Datei nicht herunterladen. " . $e->getMessage());
            return false;
        }

        // Temporäre Datei & Entpack-Verzeichnis
        $fileExtension = $this->getArchiveExtension($this->auto_sync_files_file_url);
        $tempFile = Environment::getPublicPath() . '/typo3temp/auto_sync_files_archive' . $fileExtension;
        $tempExtractDir = Environment::getPublicPath() . '/typo3temp/auto_sync_files_extract/';

        if (@file_put_contents($tempFile, $archiveContent) === false) {
            $this->log("FEHLER: Konnte temporäre Archiv-Datei nicht speichern.");
            return false;
        }
        if (!is_dir($tempExtractDir)) {
            mkdir($tempExtractDir, 0755, true);
        }

        // Archiv entpacken
        if (!$this->extractArchive($tempFile, $tempExtractDir)) {
            return false;
        }

        // Entpackte Dateien sicher verschieben
        $this->moveFolderContents($tempExtractDir, $this->auto_sync_files_local_path);

        // Cache leeren
        if ($this->auto_sync_files_clear_cache === 'on') {
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $cacheManager->flushCachesInGroup('pages');
        }

        // Temporäre Dateien entfernen
        $this->deleteFolderContents($tempExtractDir);
        @unlink($tempFile);

        return true;
    }

    /**
     * Ermittelt die Dateiendung aus der URL
     */
    private function getArchiveExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $filename = basename($path);

        if (str_ends_with($filename, '.tar.gz')) {
            return '.tar.gz';
        }
        if (str_ends_with($filename, '.tgz')) {
            return '.tgz';
        }
        if (str_ends_with($filename, '.tar')) {
            return '.tar';
        }
        return '.zip';
    }

    /**
     * Entpackt ein Archiv (ZIP, TAR, TAR.GZ) in das Zielverzeichnis
     */
    private function extractArchive(string $archiveFile, string $targetDir): bool
    {
        $extension = $this->getArchiveExtension($archiveFile);

        // ZIP-Archiv
        if ($extension === '.zip') {
            $zip = new \ZipArchive();
            if ($zip->open($archiveFile) !== true) {
                $this->log("FEHLER: Konnte ZIP-Archiv nicht öffnen");
                return false;
            }
            $zip->extractTo($targetDir);
            $zip->close();
            return true;
        }

        // TAR / TAR.GZ / TGZ-Archiv
        if (in_array($extension, ['.tar', '.tar.gz', '.tgz'], true)) {
            try {
                $phar = new \PharData($archiveFile);
                $phar->extractTo($targetDir, null, true);
                return true;
            } catch (\Exception $e) {
                $this->log("FEHLER: Konnte TAR-Archiv nicht entpacken. " . $e->getMessage());
                return false;
            }
        }

        $this->log("FEHLER: Unbekanntes Archiv-Format");
        return false;
    }


    /**
     * Sicheres, rekursives Löschen aller Dateien & Ordner innerhalb eines Verzeichnisses
     */
    private function deleteFolderContents(string $folder): void
    {
        if (!is_dir($folder)) {
            return;
        }
        foreach (scandir($folder) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $folder . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                $this->deleteFolderContents($filePath);
                rmdir($filePath);
            } else {
                unlink($filePath);
            }
        }
    }

    /**
     * Sicheres Verschieben aller Dateien & Unterordner
     */
    private function moveFolderContents(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }
        foreach (scandir($source) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = $source . DIRECTORY_SEPARATOR . $file;
            $destPath = $destination . DIRECTORY_SEPARATOR . $file;
            rename($srcPath, $destPath);
        }
    }

    /**
     * Loggt die Nachricht über den TYPO3 LogManager.
     */
    private function log(string $message): void
    {
        $this->logger?->error($message);
    }
}
