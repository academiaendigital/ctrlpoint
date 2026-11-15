<?php
// Copia este archivo como app/config.php (o, mejor, como ctrlplus-config.php en tu carpeta
// de inicio de HostGator, fuera de public_html) y completa los datos de cPanel > Bases de datos MySQL.
// Nunca subas config.php a GitHub (está en .gitignore).
return [
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'nombre'  => 'usuario_ctrlplus',   // nombre completo con el prefijo de cPanel
        'usuario' => 'usuario_panel',
        'clave'   => 'CAMBIAR',
    ],
    // true cuando el dominio tenga SSL activo (AutoSSL de HostGator)
    'forzar_https' => true,
    // Clave de un solo uso para instalar.php (mínimo 16 caracteres). Bórrala después de crear las usuarias.
    'clave_instalacion' => '',
];
