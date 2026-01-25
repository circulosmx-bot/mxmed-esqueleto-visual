Guía rápida para usar SEPOMEX local

1) Configura BD
   - Copia `sepomex-db.config.sample.php` a `sepomex-db.config.php` y ajusta credenciales.
   - Elije driver `mysql` (WAMP) o `sqlite` (archivo local).

2) Descarga catálogo
   - Descarga el TXT oficial (CPdescarga.txt) desde Correos de México.
   - Colócalo en `assets/data/sepomex/CPdescarga.txt` (crea la carpeta `sepomex/` si no existe).

3) Importa
   - Abre en el navegador: `/sepomex-import.php` (o `/sepomex-import.php?method=insert&limit=20000` para prueba rápida).
   - Para MySQL intenta `LOAD DATA LOCAL INFILE` por defecto; si falla, usa método `insert`.

4) Prueba endpoint local
   - `/sepomex-local.php?cp=20230` debe devolver colonias/municipio/estado.
   - El frontend usa `sepomex-local.php` primero; si responde, ya no intentará la API externa.

