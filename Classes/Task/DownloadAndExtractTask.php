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

        // SSRF-Schutz: Nur http(s)-URLs erlauben
        if (!$this->isValidDownloadUrl($this->auto_sync_files_file_url)) {
            $this->log("FEHLER: Ungueltige Download-URL (nur http:// und https:// erlaubt): " . $this->auto_sync_files_file_url);
            return false;
        }

        // Path-Validierung: Zielpfad MUSS innerhalb des TYPO3 Public Path liegen.
        // Schuetzt davor, dass bei Fehlkonfiguration (z.B. "/var/" oder "/etc/")
        // versehentlich kritische Systemverzeichnisse geleert werden.
        if (!$this->isPathWithinPublicRoot($this->auto_sync_files_local_path)) {
            $this->log("FEHLER: Zielpfad muss innerhalb des TYPO3 Public Path (" . Environment::getPublicPath() . ") liegen: " . $this->auto_sync_files_local_path);
            return false;
        }

        // Zielverzeichnis vorbereiten
        if (is_dir($this->auto_sync_files_local_path)) {
            if (!is_writable($this->auto_sync_files_local_path)) {
                $this->log("FEHLER: Keine Schreibrechte fuer das bestehende Verzeichnis: " . $this->auto_sync_files_local_path);
                return false;
            }
            $this->deleteFolderContents($this->auto_sync_files_local_path);
        } else {
            $parentDir = dirname($this->auto_sync_files_local_path);
            if (!is_writable($parentDir)) {
                $this->log("FEHLER: Keine Schreibrechte fuer das uebergeordnete Verzeichnis: " . $parentDir);
                return false;
            }
            GeneralUtility::mkdir_deep($this->auto_sync_files_local_path);
            if (!is_dir($this->auto_sync_files_local_path)) {
                $this->log("FEHLER: Konnte das Zielverzeichnis nicht erstellen: " . $this->auto_sync_files_local_path);
                return false;
            }
        }

        // Eindeutige Temp-Pfade pro Scheduler-Task-UID.
        // Verhindert Race Conditions, wenn mehrere Tasks dieser Extension parallel laufen.
        $taskUid = $this->getTaskUid();
        $fileExtension = $this->getArchiveExtension($this->auto_sync_files_file_url);
        $tempFile = Environment::getPublicPath() . "/typo3temp/auto_sync_files_archive_{$taskUid}{$fileExtension}";
        $tempExtractDir = Environment::getPublicPath() . "/typo3temp/auto_sync_files_extract_{$taskUid}/";

        try {
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

            if (@file_put_contents($tempFile, $archiveContent) === false) {
                $this->log("FEHLER: Konnte temporaere Archiv-Datei nicht speichern.");
                return false;
            }
            if (!is_dir($tempExtractDir)) {
                GeneralUtility::mkdir_deep($tempExtractDir);
            }

            // Archiv entpacken (mit Zip-Slip-Schutz)
            if (!$this->extractArchive($tempFile, $tempExtractDir)) {
                return false;
            }

            // Entpackte Dateien ins Zielverzeichnis verschieben
            $this->moveFolderContents($tempExtractDir, $this->auto_sync_files_local_path);

            // Cache leeren
            if ($this->auto_sync_files_clear_cache === 'on') {
                $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
                $cacheManager->flushCachesInGroup('pages');
            }

            return true;
        } finally {
            // Cleanup: Temp-Dateien IMMER entfernen, auch im Fehlerfall.
            // Verhindert, dass typo3temp/ ueber die Zeit mit Muell vollaeuft.
            if (is_dir($tempExtractDir)) {
                $this->deleteFolderContents($tempExtractDir);
                @rmdir($tempExtractDir);
            }
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Validiert die Download-URL anhand einer Schema-Whitelist (nur http/https).
     * Verhindert SSRF-Angriffe ueber file://, phar://, gopher:// und aehnliche Wrapper.
     */
    private function isValidDownloadUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Stellt sicher, dass der Zielpfad innerhalb des TYPO3 Public Path liegt.
     * Verhindert, dass beim Loeschen versehentlich Systemverzeichnisse betroffen sind.
     */
    private function isPathWithinPublicRoot(string $targetPath): bool
    {
        $publicRoot = realpath(Environment::getPublicPath());
        if ($publicRoot === false) {
            return false;
        }

        // Bestehende Pfade: realpath direkt aufloesen.
        // Noch nicht existierende Pfade: parent muss aufloesbar sein.
        if (file_exists($targetPath)) {
            $resolved = realpath($targetPath);
        } else {
            $parent = realpath(dirname($targetPath));
            if ($parent === false) {
                return false;
            }
            $resolved = $parent . DIRECTORY_SEPARATOR . basename($targetPath);
        }

        if ($resolved === false || $resolved === '') {
            return false;
        }

        // Pfad MUSS innerhalb von publicRoot liegen (oder gleich sein)
        return $resolved === $publicRoot
            || str_starts_with($resolved, $publicRoot . DIRECTORY_SEPARATOR);
    }

    /**
     * Ermittelt die Archiv-Dateiendung aus einer URL oder einem lokalen Dateinamen.
     */
    private function getArchiveExtension(string $urlOrFilename): string
    {
        $path = parse_url($urlOrFilename, PHP_URL_PATH);
        $filename = basename(is_string($path) && $path !== '' ? $path : $urlOrFilename);

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
     * Entpackt ein Archiv (ZIP, TAR, TAR.GZ) in das Zielverzeichnis.
     */
    private function extractArchive(string $archiveFile, string $targetDir): bool
    {
        $extension = $this->getArchiveExtension($archiveFile);

        if ($extension === '.zip') {
            return $this->extractZip($archiveFile, $targetDir);
        }
        if (in_array($extension, ['.tar', '.tar.gz', '.tgz'], true)) {
            return $this->extractTar($archiveFile, $targetDir);
        }

        $this->log("FEHLER: Unbekanntes Archiv-Format");
        return false;
    }

    /**
     * ZIP-Entpacker mit Zip-Slip-Schutz: validiert alle Eintrags-Pfade
     * BEVOR extractTo() aufgerufen wird.
     */
    private function extractZip(string $archiveFile, string $targetDir): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->log("FEHLER: PHP-Erweiterung 'zip' ist nicht installiert");
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($archiveFile) !== true) {
            $this->log("FEHLER: Konnte ZIP-Archiv nicht oeffnen");
            return false;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (!$this->isSafeArchivePath($name)) {
                $zip->close();
                $this->log("FEHLER: Unsicherer Pfad im ZIP-Archiv abgewiesen (Zip-Slip): " . $name);
                return false;
            }
        }

        $zip->extractTo($targetDir);
        $zip->close();
        return true;
    }

    /**
     * TAR/TAR.GZ-Entpacker mit Zip-Slip-Schutz: iteriert ueber das Phar-Archiv
     * via Stream-Wrapper und validiert alle Eintrags-Pfade vor extractTo().
     */
    private function extractTar(string $archiveFile, string $targetDir): bool
    {
        if (!class_exists(\PharData::class)) {
            $this->log("FEHLER: PHP-Erweiterung 'phar' ist nicht verfuegbar");
            return false;
        }

        try {
            $phar = new \PharData($archiveFile);

            $pharPrefix = 'phar://' . $archiveFile . '/';
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    'phar://' . $archiveFile,
                    \FilesystemIterator::SKIP_DOTS
                )
            );
            foreach ($iterator as $entry) {
                $relPath = substr((string)$entry->getPathname(), strlen($pharPrefix));
                if (!$this->isSafeArchivePath($relPath)) {
                    $this->log("FEHLER: Unsicherer Pfad im TAR-Archiv abgewiesen (Zip-Slip): " . $relPath);
                    return false;
                }
            }

            $phar->extractTo($targetDir, null, true);
            return true;
        } catch (\Exception $e) {
            $this->log("FEHLER: Konnte TAR-Archiv nicht entpacken. " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prueft, ob ein Archiv-interner Pfad sicher ist:
     * - keine absoluten Pfade (Unix oder Windows)
     * - keine '..'-Komponenten (Path-Traversal)
     */
    private function isSafeArchivePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        // Absolute Pfade ablehnen
        if (str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:#', $normalized) === 1) {
            return false;
        }

        // '..' in jeder Pfadkomponente ablehnen
        foreach (explode('/', $normalized) as $part) {
            if ($part === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Rekursives Loeschen aller Dateien & Ordner innerhalb eines Verzeichnisses.
     * WICHTIG: Symlinks werden NIEMALS verfolgt - sie werden direkt entfernt.
     * Andernfalls koennte ein Symlink im Zielverzeichnis dazu fuehren, dass
     * Daten ausserhalb des Zielverzeichnisses geloescht werden.
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

            if (is_link($filePath)) {
                @unlink($filePath);
            } elseif (is_dir($filePath)) {
                $this->deleteFolderContents($filePath);
                @rmdir($filePath);
            } else {
                @unlink($filePath);
            }
        }
    }

    /**
     * Verschiebt alle Dateien & Unterordner von $source nach $destination.
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
     * Loggt die Nachricht ueber den TYPO3 LogManager.
     */
    private function log(string $message): void
    {
        $this->logger?->error($message);
    }
}
