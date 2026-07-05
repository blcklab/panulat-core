<?php

declare(strict_types=1);

return [
    'bad-request' => [
        'title' => 'Hindi Maunawaan ang Hiling',
        'detail' => 'Hindi maunawaan ang ipinadalang hiling.',
    ],
    'not-found' => [
        'title' => 'Hindi Natagpuan',
        'detail' => 'Walang tugmang ruta o tala ang nahanap.',
    ],
    'unauthorized' => [
        'title' => 'Kailangan ng Pagkilala',
        'detail' => 'Magpadala ng wastong token upang magpatuloy.',
    ],
    'forbidden' => [
        'title' => 'Bawal ang Aksyon',
        'detail' => 'Wala kang pahintulot para gawin ito.',
    ],
    'too-many-requests' => [
        'title' => 'Sandali Muna',
        'detail' => 'Masyado nang madalas ang hiling. Subukan muli maya-maya.',
    ],
    'payload-too-large' => [
        'title' => 'Masyadong Malaki ang Hiling',
        'detail' => 'Masyadong malaki ang request body.',
    ],
    'validation-error' => [
        'title' => 'Hindi Pumasa ang Datos',
        'detail' => 'May mali sa ipinadalang datos.',
    ],
    'internal-server-error' => [
        'title' => 'May Hindi Inaasahang Suliranin',
        'detail' => 'May hindi inaasahang nangyari.',
    ],
];
