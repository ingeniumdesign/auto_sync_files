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

    private $logFile;

    public function execute(): bool
    {
        $this->logFile = Environment::getPublicPath() . '/typo3temp/auto_sync_files.log';
        $this->log("Start DownloadAndExtractTask");

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
            $this->log("Leere Zielverzeichnis: " . $this->auto_sync_files_local_path);
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
            $this->log("Zielverzeichnis erstellt: " . $this->auto_sync_files_local_path);
        }

        // Download der ZIP-Datei
        $this->log("Lade Datei herunter: " . $this->auto_sync_files_file_url);
        $archiveContent = @file_get_contents($this->auto_sync_files_file_url);
        if ($archiveContent === false) {
            $this->log("FEHLER: Konnte Datei nicht herunterladen.");
            return false;
        }

        // Temporäre Datei & Entpack-Verzeichnis
        $tempFile = Environment::getPublicPath() . '/typo3temp/auto_sync_files_archive.zip';
        $tempExtractDir = Environment::getPublicPath() . '/typo3temp/auto_sync_files_extract/';

        if (@file_put_contents($tempFile, $archiveContent) === false) {
            $this->log("FEHLER: Konnte temporäre ZIP-Datei nicht speichern.");
            return false;
        }
        if (!is_dir($tempExtractDir)) {
            mkdir($tempExtractDir, 0755, true);
        }

        // ZIP-Archiv entpacken
        $this->log("Entpacke Archiv in: " . $tempExtractDir);
        $unzipCommand = 'unzip -o ' . escapeshellarg($tempFile) . ' -d ' . escapeshellarg($tempExtractDir);
        $this->executeCommand($unzipCommand, "FEHLER: Konnte ZIP nicht entpacken");

        // Entpackte Dateien sicher verschieben
        $this->log("Verschiebe entpackte Dateien nach: " . $this->auto_sync_files_local_path);
        $this->moveFolderContents($tempExtractDir, $this->auto_sync_files_local_path);

        // Cache leeren
        if ($this->auto_sync_files_clear_cache === 'on') {
            $this->log("Leere TYPO3-Cache");
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $cacheManager->flushCachesInGroup('pages');
        }

        // Temporäre Dateien entfernen
        $this->log("Entferne temporäre Dateien.");
        $this->deleteFolderContents($tempExtractDir);
        @unlink($tempFile);

        $this->log("DownloadAndExtractTask erfolgreich abgeschlossen.");
        return true;
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
     * Führt einen Shell-Befehl aus und speichert das Ergebnis im Log.
     */
    private function executeCommand(string $command, string $errorMessage): void
    {
        $this->log("Führe Befehl aus: " . $command);
        $output = shell_exec($command . ' 2>&1');
        if ($output !== null) {
            $this->log("BEFEHL AUSGABE: " . trim($output));
        } else {
            $this->log($errorMessage);
        }
    }

    /**
     * Speichert eine Nachricht in der Log-Datei.
     */
    private function log(string $message): void
    {
        $timestamp = date("Y-m-d H:i:s");
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}
