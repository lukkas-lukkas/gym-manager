<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Only the rules used by the application are translated. Any rule missing
    | here falls back to the English message (APP_FALLBACK_LOCALE).
    |
    */

    'after' => 'O campo :attribute deve ser uma data posterior a :date.',
    'before' => 'O campo :attribute deve ser uma data anterior a :date.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'email' => 'Informe um e-mail válido.',
    'max' => [
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Este :attribute já está em uso.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Values
    |--------------------------------------------------------------------------
    */

    'values' => [
        'birth_date' => [
            'today' => 'hoje',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'birth_date' => 'data de nascimento',
        'email' => 'e-mail',
        'name' => 'nome',
        'password' => 'senha',
    ],

];
