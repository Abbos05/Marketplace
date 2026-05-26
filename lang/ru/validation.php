<?php

return [
    'accepted' => 'Необходимо принять :attribute.',
    'required' => 'Поле :attribute обязательно для заполнения.',
    'string' => 'Поле :attribute должно быть строкой.',
    'max' => [
        'string' => 'Поле :attribute не должно быть длиннее :max символов.',
        'array' => 'Поле :attribute не должно содержать больше :max элементов.',
    ],
    'min' => [
        'string' => 'Поле :attribute должно содержать не менее :min символов.',
        'array' => 'Поле :attribute должно содержать не менее :min элементов.',
    ],
    'in' => 'Выбранное значение для :attribute ошибочно.',
    'exists' => 'Выбранное значение для :attribute некорректно.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'email' => 'Поле :attribute должно быть действительным email-адресом.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'array' => 'Поле :attribute должно быть массивом.',
];
