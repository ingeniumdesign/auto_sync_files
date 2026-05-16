<?php

defined('TYPO3') or die();

$extensionKey = 'auto_sync_files';
$llPath = 'LLL:EXT:auto_sync_files/Resources/Private/Language/locallang.xlf:';

// Task: Download only
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ID\AutoSyncFiles\Task\DownloadTask::class] = [
    'extension' => $extensionKey,
    'title' => $llPath . 'task.downloadOnly.title',
    'description' => $llPath . 'task.downloadOnly.description',
    'additionalFields' => \ID\AutoSyncFiles\Task\DownloadTaskAdditionalFieldProvider::class,
];

// Task: Download & Extract
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ID\AutoSyncFiles\Task\DownloadAndExtractTask::class] = [
    'extension' => $extensionKey,
    'title' => $llPath . 'task.downloadAndExtract.title',
    'description' => $llPath . 'task.downloadAndExtract.description',
    'additionalFields' => \ID\AutoSyncFiles\Task\DownloadAndExtractTaskAdditionalFieldProvider::class,
];
