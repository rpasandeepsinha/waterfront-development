<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    //default hash driver. supported: bcrypt, argon, argon2id
    'driver' => 'bcrypt',

    'bcrypt' => [
        'rounds' => Env::get('BCRYPT_ROUNDS', 10),
    ],

    'argon' => [
        'memory' => 1024,
        'threads' => 2,
        'time' => 2,
    ],
];
