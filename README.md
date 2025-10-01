# MXMed 2025 · Reconstrucción Dev (desde base)
Esta versión separa CSS y JS desde el HTML base validado, manteniendo el diseño **pixel‑perfect**.

## Estructura
- `index.html` — Igual al base pero enlazando assets externos.
- `assets/css/style.css` — CSS extraído de los `<style>` inline.
- `assets/js/app.js` — JS extraído de los `<script>` inline sin `src`.
- (CDNs y enlaces originales del base se preservan)

## Cómo correr (Wamp)
1. Copia esta carpeta en `C:\wamp64\www\mxmed2025_recon_dev\`
2. Abre `http://localhost/mxmed2025_recon_dev/`

## Notas
- Si el HTML base contenía estilos inline por atributo (ej. `style="..."`), se conservan tal cual.
- Si notas cualquier diferencia visual, indícame el elemento y lo ajusto.
