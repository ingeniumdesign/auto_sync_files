<?php

declare(strict_types=1);

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Registry;

class DownloadAndExtractTask extends AbstractTask
{
    use AutoSyncTaskTrait;

    public string $auto_sync_files_file_url = '';
    public string $auto_sync_files_local_path = '';
    public string $auto_sync_files_clear_cache = '';
    /**
     * Wenn 'on': Zielordner-Inhalt wird komplett geloescht, sobald das Archiv erfolgreich
     * entpackt wurde (vor dem Einfuegen der neuen Dateien).
     * Wenn 'off' (oder leer, Default): Merge-Modus.
     */
    public string $auto_sync_files_replace_folder_contents = '';

    private const REGISTRY_NAMESPACE = 'tx_auto_sync_files';

    public function execute(): bool
    {
        if ($this->auto_sync_files_local_path === '' || $this->auto_sync_files_file_url === '') {
            return $this->logAndReturnFalse('error.noTargetOrUrl');
        }

        if (!$this->isValidDownloadUrl($this->auto_sync_files_file_url)) {
            return $this->logAndReturnFalse('error.invalidUrl', [$this->auto_sync_files_file_url]);
        }

        if (!$this->isPathWithinPublicRoot($this->auto_sync_files_local_path)) {
            return $this->logAndReturnFalse('error.pathOutsidePublic', [Environment::getPublicPath(), $this->auto_sync_files_local_path]);
        }

        if (is_link($this->auto_sync_files_local_path)) {
            return $this->logAndReturnFalse('error.targetIsSymlink', [$this->auto_sync_files_local_path]);
        }

        // Pre-Check: Schreibrechte VOR teurem Download (aber noch NICHT loeschen!)
        if (is_dir($this->auto_sync_files_local_path)) {
            if (!is_writable($this->auto_sync_files_local_path)) {
                return $this->logAndReturnFalse('error.targetNotWritable', [$this->auto_sync_files_local_path]);
            }
        } else {
            $parentDir = dirname($this->auto_sync_files_local_path);
            if (!is_dir($parentDir) || !is_writable($parentDir)) {
                return $this->logAndReturnFalse('error.parentNotWritable', [$parentDir]);
            }
        }

        // Eindeutige Temp-Pfade: taskUid + Run-ID (Schutz auch bei multiple=true)
        $taskUid = $this->getTaskUid();
        $runId = bin2hex(random_bytes(4));
        $tempSuffix = ($taskUid > 0 ? (string)$taskUid : 'new') . '_' . $runId;
        $fileExtension = $this->getArchiveExtension($this->auto_sync_files_file_url);
        $tempFile = Environment::getPublicPath() . "/typo3temp/auto_sync_files_archive_{$tempSuffix}{$fileExtension}";
        $tempExtractDir = Environment::getPublicPath() . "/typo3temp/auto_sync_files_extract_{$tempSuffix}/";

        $replaceMode = $this->auto_sync_files_replace_folder_contents === 'on';

        // Replace-Mode darf weder den Webroot selbst noch typo3temp/ leeren: das wuerde die
        // TYPO3-Installation bzw. das eigene Arbeitsverzeichnis loeschen. Merge in den Webroot bleibt erlaubt.
        if ($replaceMode && $this->isProtectedReplaceTarget($this->auto_sync_files_local_path)) {
            return $this->logAndReturnFalse('error.replaceTargetNotAllowed', [$this->auto_sync_files_local_path]);
        }

        try {
            // 1. Streaming-Download direkt in Temp-Datei
            try {
                $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
                $response = $requestFactory->request(
                    $this->auto_sync_files_file_url,
                    'GET',
                    ['sink' => $tempFile]
                );
                if ($response->getStatusCode() !== 200) {
                    return $this->logAndReturnFalse('error.httpStatus', [$response->getStatusCode()]);
                }
            } catch (\Exception $e) {
                return $this->logAndReturnFalse('error.downloadFailed', [$e->getMessage()]);
            }

            if (!is_file($tempFile)) {
                return $this->logAndReturnFalse('error.tempArchiveMissing');
            }
            $size = filesize($tempFile);
            if ($size === false || $size === 0) {
                return $this->logAndReturnFalse('error.tempArchiveEmpty');
            }

            // 2. Hash-Vergleich: Skip wenn Archiv bitidentisch mit letztem Lauf.
            $newHash = hash_file('sha256', $tempFile);
            if ($newHash === false) {
                return $this->logAndReturnFalse('error.hashFailed');
            }

            $useHashCache = $taskUid > 0;
            $registry = $useHashCache ? GeneralUtility::makeInstance(Registry::class) : null;
            $registryKey = 'archive_hash_' . $taskUid;

            if ($useHashCache) {
                $lastHash = (string)$registry->get(self::REGISTRY_NAMESPACE, $registryKey, '');

                if ($newHash === $lastHash
                    && is_dir($this->auto_sync_files_local_path)
                    && !$this->isFolderEmpty($this->auto_sync_files_local_path)
                ) {
                    return true;
                }
            }

            // 3. In das Temp-Verzeichnis entpacken (mit Zip-Slip-Schutz).
            //    Der Zielordner wird erst angefasst, wenn das Entpacken vollstaendig geklappt hat:
            //    ein defektes oder unvollstaendiges Archiv laesst den bisherigen Stand unveraendert.
            if (!is_dir($tempExtractDir)) {
                GeneralUtility::mkdir_deep($tempExtractDir);
            }
            if (!$this->extractArchive($tempFile, $tempExtractDir)) {
                return false;
            }

            // 4. Zielverzeichnis vorbereiten (nur loeschen, wenn Replace-Mode aktiv)
            if (is_dir($this->auto_sync_files_local_path)) {
                if ($replaceMode) {
                    $this->deleteFolderContents($this->auto_sync_files_local_path);
                }
            } else {
                GeneralUtility::mkdir_deep($this->auto_sync_files_local_path);
                if (!is_dir($this->auto_sync_files_local_path)) {
                    return $this->logAndReturnFalse('error.targetMkdirFailed', [$this->auto_sync_files_local_path]);
                }
            }

            // 5. Entpackte Dateien ins Zielverzeichnis bringen
            $movedSuccessfully = $replaceMode
                ? $this->moveFolderContents($tempExtractDir, $this->auto_sync_files_local_path)
                : $this->mergeFolderContents($tempExtractDir, $this->auto_sync_files_local_path);

            if (!$movedSuccessfully) {
                return false;
            }

            // 6. Hash erst nach vollstaendigem Erfolg persistieren (sonst wird der naechste Lauf
            //    nicht faelschlich uebersprungen), danach Cache leeren
            if ($useHashCache) {
                $registry->set(self::REGISTRY_NAMESPACE, $registryKey, $newHash);
            }

            if ($this->auto_sync_files_clear_cache === 'on') {
                $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
                $cacheManager->flushCachesInGroup('pages');
            }

            return true;
        } finally {
            // Cleanup: Temp-Dateien IMMER entfernen, auch im Fehlerfall
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
     * Ziele, fuer die der Replace-Mode gesperrt ist: der TYPO3-Public-Root selbst und typo3temp/.
     * Existiert der Zielordner noch nicht, kann er keines von beiden sein.
     */
    private function isProtectedReplaceTarget(string $targetPath): bool
    {
        $target = realpath($targetPath);
        if ($target === false) {
            return false;
        }
        $publicRoot = realpath(Environment::getPublicPath());
        $tempRoot = realpath(Environment::getPublicPath() . '/typo3temp');

        return $target === $publicRoot || ($tempRoot !== false && $target === $tempRoot);
    }

    /**
     * Prueft, ob ein Verzeichnis leer ist (nur '.' und '..' enthaelt).
     */
    private function isFolderEmpty(string $folder): bool
    {
        if (!is_dir($folder)) {
            return true;
        }
        $entries = scandir($folder);
        if ($entries === false) {
            return true;
        }
        return count(array_diff($entries, ['.', '..'])) === 0;
    }

    /**
     * Ermittelt die Archiv-Dateiendung aus einer URL oder einem lokalen Dateinamen.
     */
    private function getArchiveExtension(string $urlOrFilename): string
    {
        $path = parse_url($urlOrFilename, PHP_URL_PATH);
        $filename = basename(is_string($path) && $path !== '' ? $path : $urlOrFilename);

        return match (true) {
            str_ends_with($filename, '.tar.gz') => '.tar.gz',
            str_ends_with($filename, '.tgz')    => '.tgz',
            str_ends_with($filename, '.tar')    => '.tar',
            default                             => '.zip',
        };
    }

    /**
     * Entpackt ein Archiv (ZIP, TAR, TAR.GZ) in das Zielverzeichnis.
     */
    private function extractArchive(string $archiveFile, string $targetDir): bool
    {
        return match ($this->getArchiveExtension($archiveFile)) {
            '.zip'                    => $this->extractZip($archiveFile, $targetDir),
            '.tar', '.tar.gz', '.tgz' => $this->extractTar($archiveFile, $targetDir),
            default                   => $this->logAndReturnFalse('error.unknownArchiveFormat'),
        };
    }

    /**
     * ZIP-Entpacker mit Zip-Slip-Schutz.
     */
    private function extractZip(string $archiveFile, string $targetDir): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            return $this->logAndReturnFalse('error.zipExtensionMissing');
        }

        $zip = new \ZipArchive();
        if ($zip->open($archiveFile) !== true) {
            return $this->logAndReturnFalse('error.zipOpenFailed');
        }

        if ($zip->numFiles === 0) {
            $zip->close();
            return $this->logAndReturnFalse('error.archiveEmpty');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (!$this->isSafeArchivePath($name)) {
                // Cleanup VOR return - kann nicht logAndReturnFalse nutzen, weil $zip->close() dazwischen muss
                $zip->close();
                $this->logKey('error.zipSlipZip', [$name]);
                return false;
            }
        }

        // ZipArchive bricht beim ersten fehlerhaften Eintrag mit false ab. Bereits geschriebene
        // Dateien liegen nur im Temp-Verzeichnis und werden im finally-Block von execute() entfernt.
        if ($zip->extractTo($targetDir) !== true) {
            $status = $zip->getStatusString();
            $zip->close();
            return $this->logAndReturnFalse('error.zipExtractFailed', [$status]);
        }

        // Je nach PHP-Version meldet extractTo() beschaedigte Eintraege oder abgebrochene
        // Schreibvorgaenge nicht (u. a. PHP 8.2 und 8.3). Deshalb jede entpackte Datei gegen
        // Groesse und CRC32 aus dem Archiv pruefen.
        $baseDir = rtrim($targetDir, '/\\') . '/';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $zip->close();
                return $this->logAndReturnFalse('error.extractVerifyFailed', ['#' . $i]);
            }
            $name = (string)$stat['name'];
            if (str_ends_with($name, '/')) {
                continue;
            }
            $file = $baseDir . $name;
            if (!is_file($file)
                || filesize($file) !== (int)$stat['size']
                || hash_file('crc32b', $file) !== sprintf('%08x', ((int)$stat['crc']) & 0xFFFFFFFF)
            ) {
                $zip->close();
                return $this->logAndReturnFalse('error.extractVerifyFailed', [$name]);
            }
        }

        $zip->close();
        return true;
    }

    /**
     * TAR/TAR.GZ-Entpacker mit Zip-Slip-Schutz.
     */
    private function extractTar(string $archiveFile, string $targetDir): bool
    {
        if (!class_exists(\PharData::class)) {
            return $this->logAndReturnFalse('error.pharExtensionMissing');
        }

        try {
            $phar = new \PharData($archiveFile);

            // Phar-Stream-Wrapper erwartet Forward-Slashes (auch unter Windows)
            $normalizedArchive = str_replace('\\', '/', $archiveFile);
            $pharPrefix = 'phar://' . $normalizedArchive . '/';
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    'phar://' . $normalizedArchive,
                    \FilesystemIterator::SKIP_DOTS
                )
            );
            $expectedSizes = [];
            foreach ($iterator as $entry) {
                $relPath = substr((string)$entry->getPathname(), strlen($pharPrefix));
                if (!$this->isSafeArchivePath($relPath)) {
                    return $this->logAndReturnFalse('error.zipSlipTar', [$relPath]);
                }
                if ($entry->isFile()) {
                    $expectedSizes[$relPath] = $entry->getSize();
                }
            }
            if ($expectedSizes === []) {
                return $this->logAndReturnFalse('error.archiveEmpty');
            }

            // PharData::extractTo() wirft bei Fehlern eine Exception (siehe catch unten)
            $phar->extractTo($targetDir, null, true);

            // Jede entpackte Datei gegen die Groesse im Archiv pruefen
            $baseDir = rtrim($targetDir, '/\\') . '/';
            foreach ($expectedSizes as $relPath => $size) {
                $file = $baseDir . $relPath;
                if (!is_file($file) || ($size !== false && filesize($file) !== $size)) {
                    return $this->logAndReturnFalse('error.extractVerifyFailed', [$relPath]);
                }
            }
            return true;
        } catch (\Exception $e) {
            return $this->logAndReturnFalse('error.tarExtractFailed', [$e->getMessage()]);
        }
    }

    /**
     * Prueft, ob ein Archiv-interner Pfad sicher ist (keine absoluten Pfade, kein Path-Traversal).
     */
    private function isSafeArchivePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:#', $normalized) === 1) {
            return false;
        }

        foreach (explode('/', $normalized) as $part) {
            if ($part === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Rekursives Loeschen aller Dateien & Ordner innerhalb eines Verzeichnisses.
     * Symlinks werden NIEMALS verfolgt, sondern direkt entfernt.
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
     * Verschiebt alle Dateien & Unterordner (Replace-Mode: Ziel ist leer).
     */
    private function moveFolderContents(string $source, string $destination): bool
    {
        if (!is_dir($source)) {
            return $this->logAndReturnFalse('error.moveSourceMissing', [$source]);
        }
        foreach (scandir($source) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = $source . DIRECTORY_SEPARATOR . $file;
            $destPath = $destination . DIRECTORY_SEPARATOR . $file;
            if (!@rename($srcPath, $destPath)) {
                return $this->logAndReturnFalse('error.renameFailed', [$srcPath, $destPath]);
            }
        }
        return true;
    }

    /**
     * Rekursive Verschmelzung von $source in $destination (Merge-Mode).
     */
    private function mergeFolderContents(string $source, string $destination): bool
    {
        if (!is_dir($source)) {
            return $this->logAndReturnFalse('error.mergeSourceMissing', [$source]);
        }
        if (!is_dir($destination)) {
            GeneralUtility::mkdir_deep($destination);
            if (!is_dir($destination)) {
                return $this->logAndReturnFalse('error.mergeTargetMkdirFailed', [$destination]);
            }
        }

        foreach (scandir($source) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $srcPath = $source . DIRECTORY_SEPARATOR . $entry;
            $destPath = $destination . DIRECTORY_SEPARATOR . $entry;

            // Quelle ist ein Ordner (kein Symlink): rekursiv mergen
            if (!is_link($srcPath) && is_dir($srcPath)) {
                // Wenn Ziel ein Symlink ist (egal ob auf Datei oder Ordner), zuerst entfernen.
                // Sonst wuerde der rekursive Merge dem Symlink folgen und ausserhalb des
                // Zielordners hineinschreiben.
                if (is_link($destPath)) {
                    @unlink($destPath);
                } elseif (is_file($destPath)) {
                    @unlink($destPath);
                }
                if (!$this->mergeFolderContents($srcPath, $destPath)) {
                    return false;
                }
                @rmdir($srcPath);
                continue;
            }

            // Quelle ist eine Datei oder Symlink: Ziel ggf. entfernen, dann rename
            if (file_exists($destPath) || is_link($destPath)) {
                if (is_link($destPath)) {
                    @unlink($destPath);
                } elseif (is_dir($destPath)) {
                    $this->deleteFolderContents($destPath);
                    @rmdir($destPath);
                } else {
                    @unlink($destPath);
                }
            }

            if (!@rename($srcPath, $destPath)) {
                return $this->logAndReturnFalse('error.renameFailedMerge', [$srcPath, $destPath]);
            }
        }

        return true;
    }
}
