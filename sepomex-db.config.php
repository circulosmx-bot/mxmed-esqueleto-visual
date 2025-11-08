<?php
// Configuración local por defecto para WAMP (ajústala si tu MySQL tiene clave)
return [
  'driver' => 'mysql',
  'mysql' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'sepomex',
    'user' => 'root',
    'pass' => '', // si tu root tiene contraseña, colócala aquí
    'charset' => 'utf8mb4',
  ],
  'sqlite' => [
    'path' => __DIR__ . '/assets/data/sepomex.sqlite'
  ],
];

