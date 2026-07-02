<?php

use Fendinger\PagestreeSection\MoveFix;
use Kirby\Cms\App;

require_once __DIR__ . '/lib/MoveFix.php';

App::plugin('fendinger/pagestree-section', [
    'sections' => [
        'pagestree' => require __DIR__ . '/sections/pagestree.php',
    ],
    'hooks' => [
        // Kirby core only accepts parents with a `pages` section as
        // move targets (see PageRules::move). Patch the blueprints
        // for the move dialog and page tree routes so parents with a
        // `pagestree` section become valid targets, too.
        'route:before' => function ($route, $path, $method) {
            if (MoveFix::isMoveRoute($path) === true) {
                MoveFix::patchBlueprints();
            }
        }
    ]
]);
