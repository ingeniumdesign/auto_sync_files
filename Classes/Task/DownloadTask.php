<?php

declare(strict_types=1);

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;

class DownloadTask extends AbstractTask
{
    use AutoSyncTaskTrait;

    public string $auto_sync_files_file_url = '';
    public string $auto_sync_files_local_path = '';
    public string $auto_sync_files_clear_cache = '';

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

        // Eindeutige Temp-Datei pro Run (Schutz auch bei multiple=true)
        $taskUid = $this->getTaskUid();
        $runId = bin2hex(random_bytes(4));
        $tempSuffix = ($taskUid > 0 ? (string)$taskUid : 'new') . '_' . $runId;
        $tempFile = Environment::getPublicPath() . "/typo3temp/auto_sync_files_download_{$tempSuffix}.tmp";

        try {
            // Streaming-Download direkt in Temp-Datei (konstanter Memory-Verbrauch)
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
                return $this->logAndReturnFalse('error.tempFileMissing');
            }

            // Hash-Vergleich (SHA-256, chunked read - konstanter Memory)
            $newHash = hash_file('sha256', $tempFile);
            $oldHash = is_file($this->auto_sync_files_local_path)
                ? hash_file('sha256', $this->auto_sync_files_local_path)
                : null;

            if ($newHash === false) {
                return $this->logAndReturnFalse('error.hashFailed');
            }

            // Wenn identisch: nichts tun, kein Cache-Flush
            if ($oldHash !== false && $oldHash !== null && $oldHash === $newHash) {
                return true;
            }

            // Parent-Verzeichnis bei Bedarf anlegen
            $parentDir = dirname($this->auto_sync_files_local_path);
            if (!is_dir($parentDir)) {
                GeneralUtility::mkdir_deep($parentDir);
                if (!is_dir($parentDir)) {
                    return $this->logAndReturnFalse('error.parentMkdirFailed', [$parentDir]);
                }
            }

            // Atomares Verschieben via rename. Falls cross-filesystem fehlschlaegt, Fallback auf copy.
            if (!@rename($tempFile, $this->auto_sync_files_local_path)) {
                if (!@copy($tempFile, $this->auto_sync_files_local_path)) {
                    return $this->logAndReturnFalse('error.writeFailed', [$this->auto_sync_files_local_path]);
                }
            }

            // Cache nur leeren, wenn sich tatsaechlich etwas geaendert hat
            if ($this->auto_sync_files_clear_cache === 'on') {
                $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
                $cacheManager->flushCachesInGroup('pages');
            }

            return true;
        } finally {
            // Cleanup: Temp-Datei IMMER entfernen
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}
