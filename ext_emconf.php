<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Auto Sync Files',
    'description' => 'This Extension downloads externals files periodically via Scheduler to your Webspace thus you do always have the newest version of a file locally stored. This can be really useful to improve caching performance.',
    'category' => 'plugin',
    'author' => 'Sebastian Schmal',
    'author_email' => 'info@ingeniumdesign.de',
    'author_company' => 'INGENIUMDESIGN',
    'version' => '1.0.0',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'constraints' => array(
        'depends' => [
            'typo3' => '8.7.0-8.7.99',
        ],
        'conflicts' => array(
        ),
        'suggests' => array(
        ),
    ),
    'autoload' => [
        'psr-4' => [
            'ID\\AutoSyncFiles\\' => 'Classes'
        ],
    ],
);