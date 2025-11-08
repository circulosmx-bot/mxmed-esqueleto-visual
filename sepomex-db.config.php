<?php
// Configuración local por defecto para WAMP (ajústala si tu MySQL tiene clave)
return [
  'driver' => 'mysql',
  'mysql' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'sepomex',
    'user' => 'mxmed',
    'pass' => 'Decaf321*', // usuario propio para la app
    'charset' => 'utf8mb4',
  ],
  'sqlite' => [
    'path' => __DIR__ . '/assets/data/sepomex.sqlite'
  ],
];
