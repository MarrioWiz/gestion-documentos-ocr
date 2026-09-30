<?php

return [
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'before_or_equal' => 'La :attribute no puede ser posterior a hoy.',
    'date' => 'La :attribute no es una fecha válida.',
    'email' => 'El :attribute debe ser un correo válido.',
    'file' => 'El :attribute debe ser un archivo.',
    'in' => 'El valor de :attribute no es válido.',
    'max' => [
        'file' => 'El :attribute no puede pesar más de :max KB.',
        'string' => 'El :attribute no puede tener más de :max caracteres.',
    ],
    'mimes' => 'El :attribute debe ser de tipo: :values.',
    'min' => [
        'string' => 'La :attribute debe tener al menos :min caracteres.',
    ],
    'password' => [
        'min' => 'La :attribute debe tener al menos :min caracteres.',
    ],
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'Ese :attribute ya está registrado.',

    'attributes' => [
        'archivo' => 'archivo',
        'curp' => 'CURP',
        'email' => 'correo',
        'entidad_nacimiento' => 'entidad de nacimiento',
        'fecha_nacimiento' => 'fecha de nacimiento',
        'name' => 'nombre',
        'nombre_completo' => 'nombre completo',
        'numero_documento' => 'número de documento',
        'password' => 'contraseña',
        'tipo_documento' => 'tipo de documento',
    ],
];
