<?php
// Copia a sepomex-db.config.php y edita credenciales/driver
return [
  // Driver: 'mysql' o 'sqlite'
  'driver' => 'mysql',
  // Para MySQL
  'mysql' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'sepomex',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
  ],
  // Para SQLite (si prefieres un archivo local)
  'sqlite' => [
    'path' => __DIR__ . '/assets/data/sepomex.sqlite'
  ],
];

