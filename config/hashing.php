<?php

return [


    // Argon supports the full 128-character registration limit. ArgonHasher still
    // verifies legacy bcrypt hashes through password_verify when verify is false.
    'driver' => 'argon',


    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
    ],


    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
        'verify' => false,
    ],

];
