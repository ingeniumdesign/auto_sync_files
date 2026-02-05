<?php

declare(strict_types=1);

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Cache\CacheManager;

class DownloadTask extends AbstractTask
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

        try {
            $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
            $response = $requestFactory->request($this->auto_sync_files_file_url, 'GET');
            if ($response->getStatusCode() !== 200) {
                $this->log("FEHLER: HTTP Status " . $response->getStatusCode());
                return false;
            }
            $newFile = $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->log("FEHLER: Konnte Datei nicht herunterladen. " . $e->getMessage());
            return false;
        }

        if (!file_exists($this->auto_sync_files_local_path)) {
            @file_put_contents($this->auto_sync_files_local_path, '');
        }

        $oldFile = @file_get_contents($this->auto_sync_files_local_path);
        if ($oldFile === $newFile) {
            return true;
        }

        $succ = @file_put_contents($this->auto_sync_files_local_path, $newFile);

        if ($this->auto_sync_files_clear_cache === 'on') {
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $cacheManager->flushCachesInGroup('pages');
        }

        return (bool)$succ;
    }

    /**
     * Loggt die Nachricht über den TYPO3 LogManager.
     */
    private function log(string $message): void
    {
        $this->logger?->error($message);
    }
}
