<?php

use Fendinger\PagestreeSection\Assets;
use Fendinger\PagestreeSection\MoveFix;
use Kirby\Cms\App;

$kirby = App::instance();
$major = (int)$kirby->version();

require_once __DIR__ . '/lib/Tree.php';
require_once __DIR__ . '/lib/Assets.php';

Assets::publish($major);

if ($major >= 6) {
    require_once __DIR__ . '/lib/PagestreeField.php';

    // Existing `sections: type: pagestree` blueprints keep working.
    // Kirby maps the section onto this field while it loads the blueprint.
    \Kirby\Blueprint\Normalizer::$sectionFields['pagestree'] = 'pagestree';

    App::plugin('fendinger/pagestree-section', [
        'fields' => [
            'pagestree' => Fendinger\PagestreeSection\PagestreeField::class,
        ],
    ]);

    return;
}

require_once __DIR__ . '/lib/MoveFix.php';

App::plugin('fendinger/pagestree-section', [
    'sections' => [
        'pagestree' => require __DIR__ . '/sections/pagestree.php',
    ],
    'hooks' => [
        // Kirby 5 only accepts parents with a `pages` section as
        // move targets. Inject a synthetic pages section next to
        // every pagestree section while the move dialog is built.
        'route:before' => function ($route, $path, $method) {
            if (MoveFix::isMoveRoute($path) === true) {
                MoveFix::patchBlueprints();
            }
        }
    ]
]);
