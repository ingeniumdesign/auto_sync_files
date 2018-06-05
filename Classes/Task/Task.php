<?php

namespace ID\AutoSyncFiles\Task;

class Task extends \TYPO3\CMS\Scheduler\Task\AbstractTask
{
    public function execute()
    {
        // Download File
        $newFile = file_get_contents($this->auto_sync_files_file_url);

        if (!file_exists($this->auto_sync_files_local_path)) {
            file_put_contents($this->auto_sync_files_local_path, '');
        }

        $oldFile = file_get_contents($this->auto_sync_files_local_path);

        if ($newFile === $oldFile) {
            return true; // Nothing todo here. Both files are still identical
        }

        // Save new file
        $succ = file_put_contents($this->auto_sync_files_local_path, $newFile);

        // Clear Cache
        if ($this->auto_sync_files_clear_cache == 'on') {
            $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Object\ObjectManager');
            $cacheService = $objectManager->get('TYPO3\\CMS\\Extbase\\Service\\CacheService');
            $cacheService->clearPageCache();
        }

        return $succ;
    }
}