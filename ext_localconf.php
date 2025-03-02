<?php

defined('TYPO3') or die();

$extensionKey = 'auto_sync_files';

// Task: Download only
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ID\AutoSyncFiles\Task\DownloadTask::class] = [
    'extension' => $extensionKey,
    'title' => 'Auto Sync Files: Download Only',
    'description' => 'Download a file from another Webpage periodically to your own Webspace, e.g. the Google-Analytics-Script',
    'additionalFields' => \ID\AutoSyncFiles\Task\DownloadTaskAdditionalFieldProvider::class,
];

// Task: Download & Extract
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ID\AutoSyncFiles\Task\DownloadAndExtractTask::class] = [
    'extension' => $extensionKey,
    'title' => 'Auto Sync Files: Download & Extract',
    'description' => 'Download file(s), then optionally extract or replace existing files or folders.',
    'additionalFields' => \ID\AutoSyncFiles\Task\DownloadAndExtractTaskAdditionalFieldProvider::class,
];
