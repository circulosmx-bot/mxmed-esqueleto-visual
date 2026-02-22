# Clinical Encounters: URL Encoding de `encounter_key`

`encounter_key` puede contener caracteres reservados como `:` y `#`.
Para llamadas HTTP, siempre envíalo **URL-encoded** en el path.

Ejemplos:

```bash
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/encounters/enc%3A2"
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/encounters/appt%3A{appt_id}%23enc%3A2"
```

Nota:
- En navegador, el fragmento `#...` no se envía al servidor.
- Por eso, en `encounter_key` compuesto (`appt:{id}#enc:{id}`), el `#` debe ir como `%23`.
- En terminal, usa la URL entre comillas para evitar interpretación del shell.
