<?php

Kirby::plugin('fendinger/pagestree-section', [
    'sections' => [
        'pagestree' => require __DIR__ . '/sections/pagestree.php',
    ]
]);
