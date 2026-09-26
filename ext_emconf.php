<?php

/*
 * This file is part of the package ID\AutoSyncFiles.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Auto Sync Files',
    'description' => 'Downloads external files periodically via the TYPO3 Scheduler into your web space, so you always have the newest version stored locally (e.g. to improve caching). Optional Download & Extract mode: downloads a ZIP / TAR / TAR.GZ archive and merges it into, or replaces, the contents of a target folder.',
    'category' => 'plugin',
    'constraints' => [
        'depends' => [
            'typo3'     => '13.4.0-13.4.99',
            'scheduler' => '13.4.0-13.4.99',
            'extbase'   => '13.4.0-13.4.99',
            'php'       => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
    'autoload' => [
        'psr-4' => [
            'ID\\AutoSyncFiles\\' => 'Classes',
        ],
    ],
    'state' => 'stable',
    'author' => 'Sebastian Schmal',
    'author_email' => 'info@ingeniumdesign.de',
    'author_company' => 'INGENIUMDESIGN',
    'version' => '13.0.0',
];
