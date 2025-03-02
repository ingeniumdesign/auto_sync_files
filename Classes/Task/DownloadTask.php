<?php

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Cache\CacheManager;

class DownloadTask extends AbstractTask
{
    public $auto_sync_files_file_url = '';
    public $auto_sync_files_local_path = '';
    public $auto_sync_files_clear_cache = '';

    public function execute(): bool
    {
        if ($this->auto_sync_files_local_path === '' || $this->auto_sync_files_file_url === '') {
            return false;
        }

        $newFile = @file_get_contents($this->auto_sync_files_file_url);
        if ($newFile === false) {
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
}
