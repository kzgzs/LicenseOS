<?php

return [
    'required' => 'The :attribute field is required.',
    'unique' => 'The :attribute has already been taken.',
    'numeric' => 'The :attribute must be a number.',
    'integer' => 'The :attribute must be an integer.',
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'string' => 'The :attribute must be at least :min characters.',
    ],
    'max' => [
        'numeric' => 'The :attribute must not be greater than :max.',
        'string' => 'The :attribute must not be greater than :max characters.',
        'file' => 'The :attribute must not be greater than :max kilobytes.',
    ],
    'in' => 'The selected :attribute is invalid.',
    'regex' => 'The :attribute format is invalid.',
    'url' => 'The :attribute must be a valid URL.',
    'file' => 'The :attribute must be a file.',
    'mimes' => 'The :attribute must be a file of type: :values.',
    'array' => 'The :attribute must be an array.',
    'boolean' => 'The :attribute must be true or false.',
    'exists' => 'The selected :attribute is invalid.',
    'string' => 'The :attribute must be a string.',
    'attributes' => [
        'name' => 'product name',
        'slug' => 'slug',
        'type' => 'product type',
        'price' => 'base price',
        'version' => 'version',
        'file' => 'package ZIP',
    ],
];