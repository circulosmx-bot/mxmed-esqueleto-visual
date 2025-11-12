Guía de configuración UTF-8 en WAMP (referencia)

Objetivo: asegurar UTF‑8 de extremo a extremo (Apache, PHP, MySQL y aplicación).

1) Apache (project level ya aplicado)
- En este proyecto se usa `.htaccess` con:
  - `AddDefaultCharset UTF-8`
  - `AddType 'text/html; charset=UTF-8' .html`
  - `AddType 'text/css; charset=UTF-8' .css`
  - `AddType 'application/javascript; charset=UTF-8' .js`
  - `php_value default_charset "UTF-8"`

Opción global (opcional):
- Editar `c:\wamp64\bin\apache\apacheX.Y.Z\conf\httpd.conf` y asegurar:
  - `AddDefaultCharset UTF-8`
- Reiniciar Apache desde WAMP Manager después de guardar.

2) PHP
- Nivel proyecto: los endpoints ya envían `Content-Type` con `charset=utf-8` y se fija `default_charset` vía `.htaccess`.
- Nivel global (opcional): en `c:\wamp64\bin\apache\apacheX.Y.Z\bin\php.ini` (o `c:\wamp64\bin\php\phpX.Y.Z\php.ini`), verificar:
  - `default_charset = "UTF-8"`
- Reiniciar servicios después de guardar.

3) MySQL
- Nivel conexión (ya aplicado en la app):
  - DSN incluye `charset=utf8mb4` y se ejecuta `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci` cuando aplica.
- Nivel base de datos (recomendado):
  - Crear BD con: `CREATE DATABASE ... DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`.
- Nivel servidor (opcional, global): editar `c:\wamp64\bin\mysql\mysqlX.Y.Z\my.ini` y agregar/ajustar:
  ```ini
  [mysqld]
  character-set-server = utf8mb4
  collation-server     = utf8mb4_unicode_ci

  [client]
  default-character-set = utf8mb4
  ```
- Reiniciar MySQL desde WAMP Manager.

4) Archivos del proyecto
- PHP: verificados sin BOM.
- HTML: incluyen `<meta charset="utf-8">`.

Notas
- `utf8mb4` es el juego de caracteres recomendado para cubrir todo Unicode (incluye emojis), preferible a `utf8` de MySQL.
- Evitar reemplazos globales de texto en runtime; usar fuentes y archivos guardados en UTF‑8.

