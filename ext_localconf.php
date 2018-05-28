<?php


$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['ID\AutoSyncFiles\Task\Task'] = array(
    'extension' => $_EXTKEY,
    'title' => 'Sync a file from another Webpage',
    'description' => 'Download a file from another Webpage periodically to your own Webspace, e.g. the Google-Analytics-Script',
    'additionalFields' => 'ID\AutoSyncFiles\Task\Fields'
);