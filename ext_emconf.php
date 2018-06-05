<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Auto Sync Files',
    'description' => 'Downloads externals files periodically via Scheduler to your Webspace thus you do always have the newest version of a file locally stored. This can be really useful to improve caching performance.',
    'category' => 'plugin',
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
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearCacheOnLoad' => 1,
	'author' => 'Sebastian Schmal',
	'author_email' => 'info@ingeniumdesign.de',
	'author_company' => 'INGENIUMDESIGN',
	'version' => '1.0.0',
];