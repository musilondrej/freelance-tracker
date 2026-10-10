<?php

declare(strict_types=1);

// Only rules used by the app are translated; anything else falls back to the framework's English messages.
return [
    'accepted' => 'Pole :attribute musí být přijato.',
    'after' => 'Pole :attribute musí být datum po :date.',
    'after_or_equal' => 'Pole :attribute musí být datum :date nebo pozdější.',
    'array' => 'Pole :attribute musí být seznam.',
    'before' => 'Pole :attribute musí být datum před :date.',
    'before_or_equal' => 'Pole :attribute musí být datum :date nebo dřívější.',
    'between' => [
        'numeric' => 'Pole :attribute musí být mezi :min a :max.',
        'string' => 'Pole :attribute musí mít :min až :max znaků.',
    ],
    'boolean' => 'Pole :attribute musí být ano, nebo ne.',
    'confirmed' => 'Potvrzení pole :attribute se neshoduje.',
    'current_password' => 'Heslo není správné.',
    'date' => 'Pole :attribute musí být platné datum.',
    'date_format' => 'Pole :attribute musí být ve formátu :format.',
    'decimal' => 'Pole :attribute musí mít :decimal desetinných míst.',
    'different' => 'Pole :attribute a :other se musí lišit.',
    'digits' => 'Pole :attribute musí mít :digits číslic.',
    'digits_between' => 'Pole :attribute musí mít :min až :max číslic.',
    'email' => 'Pole :attribute musí být platná e-mailová adresa.',
    'enum' => 'Zvolená hodnota pole :attribute není platná.',
    'exists' => 'Zvolená hodnota pole :attribute neexistuje.',
    'filled' => 'Pole :attribute musí být vyplněno.',
    'gt' => [
        'numeric' => 'Pole :attribute musí být větší než :value.',
    ],
    'gte' => [
        'numeric' => 'Pole :attribute musí být alespoň :value.',
    ],
    'in' => 'Zvolená hodnota pole :attribute není platná.',
    'integer' => 'Pole :attribute musí být celé číslo.',
    'lt' => [
        'numeric' => 'Pole :attribute musí být menší než :value.',
    ],
    'lte' => [
        'numeric' => 'Pole :attribute může být nejvýše :value.',
    ],
    'max' => [
        'array' => 'Pole :attribute může mít nejvýše :max položek.',
        'numeric' => 'Pole :attribute může být nejvýše :max.',
        'string' => 'Pole :attribute může mít nejvýše :max znaků.',
    ],
    'min' => [
        'array' => 'Pole :attribute musí mít alespoň :min položek.',
        'numeric' => 'Pole :attribute musí být alespoň :min.',
        'string' => 'Pole :attribute musí mít alespoň :min znaků.',
    ],
    'numeric' => 'Pole :attribute musí být číslo.',
    'password' => [
        'letters' => 'Heslo musí obsahovat alespoň jedno písmeno.',
        'mixed' => 'Heslo musí obsahovat velké i malé písmeno.',
        'numbers' => 'Heslo musí obsahovat alespoň jedno číslo.',
        'symbols' => 'Heslo musí obsahovat alespoň jeden speciální znak.',
        'uncompromised' => 'Toto heslo se objevilo v úniku dat. Zvolte jiné.',
    ],
    'regex' => 'Formát pole :attribute není platný.',
    'required' => 'Pole :attribute je povinné.',
    'required_if' => 'Pole :attribute je povinné, když :other je :value.',
    'required_with' => 'Pole :attribute je povinné, když je vyplněno :values.',
    'required_without' => 'Pole :attribute je povinné, když není vyplněno :values.',
    'same' => 'Pole :attribute a :other se musí shodovat.',
    'size' => [
        'string' => 'Pole :attribute musí mít :size znaků.',
    ],
    'string' => 'Pole :attribute musí být text.',
    'unique' => 'Hodnota pole :attribute už je použitá.',
    'url' => 'Pole :attribute musí být platná URL adresa.',

    'custom' => [],

    'attributes' => [],
];
