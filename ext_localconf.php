<?php


$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['ID\Downloadscriptstask\Task\Task'] = array(
    'extension' => $_EXTKEY,
    'title' => 'Download of a Script-File',
    'description' => 'This task is doing something really important',
    'additionalFields' => 'ID\Downloadscriptstask\Task\Fields'
);