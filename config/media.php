<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media-Enabled Models
    |--------------------------------------------------------------------------
    |
    | Registry of models that support media uploads via the polymorphic
    | MediaController. Each entry defines the model class and allowed
    | media collections.
    |
    */
    'models' => [
        'characters' => [
            'class' => \App\Models\Character::class,
            'collections' => ['portrait', 'token'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */
    'max_file_size' => 2048, // 2MB in KB

    'accepted_mimetypes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
];
