# Agenda Module (bootstrapping v1)

Esta carpeta agrupa los componentes técnicos básicos del módulo Agenda Médica v1.
- `routes.php`: describe los endpoints planificados.
- `controllers/`: controladores que responden 501 (no implementado aún).
- `services/`: servicios compartidos vacíos.
- `repositories/`: repositorios con stubs para futuros accesos.
- `validators/`: validadores de requests pendientes.

El objetivo es preparar la base y dejar guardadas las convenciones antes de implementar la lógica.

- El módulo reutiliza `api/_lib/db.php` y su helper `mxmed_pdo()` para obtener la conexión PDO sin reconfigurar el proyecto.
