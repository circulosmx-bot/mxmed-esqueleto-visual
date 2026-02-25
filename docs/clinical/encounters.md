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
- En PHP para path segments usa `rawurlencode($encounterKey)` (helper API: `clinical_url_encode_key()`).

## Seed QA local: 1 `clinical_document` para Encounter

Ejemplo SQL mínimo para sembrar un documento ligado a una cita existente.

```sql
INSERT INTO clinical_documents (
  document_uuid,
  document_type,
  title,
  version,
  status,
  patient_id,
  encounter_id,
  appointment_id,
  care_setting,
  payload_json,
  summary,
  edited_flag,
  event_datetime,
  widget_group,
  printable,
  created_at,
  updated_at,
  generated_at,
  signed_at,
  created_by_user_id
) VALUES (
  UUID(),
  'note',
  'Nota demo QA',
  1,
  'signed',
  'p_0c874aa9cbad',
  '3',
  'fe61cdd67e97dcfde3a70c02',
  'consulta',
  JSON_OBJECT('source', 'qa', 'kind', 'seed'),
  'Documento demo para validar timeline/encounter',
  0,
  NOW(),
  'documentos_clinicos',
  1,
  NOW(),
  NOW(),
  NOW(),
  NOW(),
  'qa_seed'
);
```

Validación sugerida:

```bash
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/encounters/appt%3Afe61cdd67e97dcfde3a70c02"
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/patients/p_0c874aa9cbad/timeline?include=agenda,clinical&limit=20"
```
