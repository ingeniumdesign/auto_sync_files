<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Download Scripts Cronjob',
    'description' => 'This Extension Downloads external JavaScript-Librarys regularly so you can include them from the server and it can be cached.',
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
            'ID\\Downloadscriptstask\\' => 'Classes'
        ],
    ],
);