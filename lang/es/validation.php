<?php

return [
    'accepted' => 'Debe aceptar :attribute.', 'required' => 'El campo :attribute es obligatorio.', 'required_without' => 'Complete :attribute o :values.',
    'email' => 'Indique un correo válido en :attribute.', 'string' => 'El campo :attribute debe ser texto.', 'integer' => 'El campo :attribute debe ser un entero.',
    'numeric' => 'El campo :attribute debe ser un número.', 'boolean' => 'El campo :attribute no es válido.', 'array' => 'El campo :attribute debe ser una lista.',
    'confirmed' => 'La confirmación de :attribute no coincide.', 'current_password' => 'La contraseña actual no es correcta.', 'unique' => 'El valor de :attribute ya está registrado.',
    'exists' => 'La opción de :attribute no está disponible.', 'in' => 'La opción de :attribute no es válida.', 'enum' => 'La opción de :attribute no es válida.',
    'distinct' => 'No repita valores en :attribute.', 'url' => 'Indique una URL válida en :attribute.', 'file' => 'Seleccione un archivo válido en :attribute.',
    'image' => 'El archivo :attribute debe ser una imagen.', 'mimes' => 'El archivo :attribute debe tener uno de estos formatos: :values.',
    'extensions' => 'La extensión de :attribute debe ser una de estas: :values.', 'dimensions' => 'Las dimensiones de :attribute no son válidas.',
    'date' => 'La fecha de :attribute no es válida.', 'after_or_equal' => ':attribute debe ser posterior o igual a :date.',
    'before_or_equal' => ':attribute debe ser anterior o igual a :date.', 'decimal' => 'El campo :attribute admite :decimal decimales.', 'regex' => 'El formato de :attribute no es válido.',
    'min' => ['numeric' => ':attribute debe ser al menos :min.', 'string' => ':attribute debe tener al menos :min caracteres.', 'array' => 'Seleccione al menos :min opciones en :attribute.', 'file' => ':attribute debe pesar al menos :min KB.'],
    'max' => ['numeric' => ':attribute no puede superar :max.', 'string' => ':attribute admite hasta :max caracteres.', 'array' => ':attribute admite hasta :max elementos.', 'file' => ':attribute admite hasta :max KB.'],
    'password' => ['mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.', 'numbers' => 'La contraseña debe incluir números.', 'letters' => 'La contraseña debe incluir letras.', 'symbols' => 'La contraseña debe incluir símbolos.', 'uncompromised' => 'Elija otra contraseña.'],
    'attributes' => ['name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña', 'company' => 'empresa', 'subject' => 'asunto', 'details' => 'descripción', 'body' => 'mensaje', 'consent' => 'autorización', 'terms' => 'términos y privacidad', 'items' => 'servicios', 'scopes' => 'permisos', 'summary' => 'resumen', 'logo' => 'logo', 'favicon' => 'favicon', 'file' => 'archivo', 'role' => 'rol', 'amount' => 'importe', 'reference' => 'referencia', 'tax_percent' => 'impuesto', 'billing_email' => 'correo de facturación', 'billing_address' => 'dirección de facturación'],
];
