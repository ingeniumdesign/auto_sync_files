<?php

/*
 * This file is part of the package ID\AutoSyncFiles.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Auto Sync Files',
    'description' => 'Downloads externals files periodically via Scheduler to your Webspace thus you do always have the newest version of a file locally stored. This can be really useful to improve caching performance. Or download a compressed file, unzip the file, delete the existing files.',
    'category' => 'plugin',
    'constraints' => [
        'depends' => [
            'typo3'    => '12.4.0-12.4.99',
            'scheduler' => '12.4.0-12.4.99'
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
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
	'version' => '12.0.3',
];
