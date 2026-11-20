<?php

use Fendinger\PagestreeSection\PagestreeField;
use Kirby\Cms\App;

load([
    PagestreeField::class => 'lib/PagestreeField.php'
], __DIR__);

App::plugin('fendinger/pagestree-section', [
    'fields' => [
        'pagestree' => PagestreeField::class
    ]
]);
