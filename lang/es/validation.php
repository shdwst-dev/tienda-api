<?php

// Mensajes de validación en español. Lo que no está aquí cae al inglés de Laravel (fallback_locale).

return [

    'array' => 'El campo :attribute no tiene un formato válido.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'email' => 'Escribe un correo electrónico válido.',
    'exists' => 'El :attribute seleccionado no es válido.',
    'in' => 'El :attribute seleccionado no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elemento(s).',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'Ya existe un registro con ese :attribute.',

    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'nombre' => 'nombre',
        'descripcion' => 'descripción',
        'categoria' => 'categoría',
        'precio' => 'precio',
        'stock' => 'stock',
        'direccion_envio' => 'dirección de envío',
        'items' => 'productos',
        'items.*.producto_id' => 'producto',
        'items.*.cantidad' => 'cantidad',
        'estado' => 'estado',
        'buscar' => 'búsqueda',
    ],

];
