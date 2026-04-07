# Documentos Clínicos Base V1 (MXMed)

## Objetivo
Estandarizar una base reutilizable para documentos clínicos sin romper el flujo actual de `consentimiento_informado`.

## Contrato de definición documental
Cada documento se describe con:

- `document_type`
- `title`
- `subtitle_field`
- `blocks`
- `signatures_required`
- `attachments_allowed`
- `render_order`
- `print_profile`

Implementación actual:

- Frontend runtime: `assets/js/app.js` (`CLINICAL_DOCUMENT_DEFINITIONS`)
- Render UI/PDF bridge: `modules/clinical/ui/document.php` (`clinical_doc_definition_registry`)

## Payload canónico (envolvente compatible)
Se mantiene payload legacy y se agrega:

- `payload.canonical_document.meta`
- `payload.canonical_document.context`
- `payload.canonical_document.content`
- `payload.canonical_document.signatures`
- `payload.canonical_document.attachments`
- `payload.canonical_document.render`

Esto permite adopción progresiva sin migración destructiva.

## Render imprimible base
Se consolida perfil de impresión reutilizable por definición:

- tipografía base (Inter/Helvetica/Arial)
- tamaño base
- line-height

El consentimiento sigue su layout aprobado, pero ahora toma perfil desde definición para preparar herencia a nuevos tipos.

## Helpers reutilizables introducidos
En `assets/js/app.js`:

- `getClinicalDocumentDefinition(documentType)`
- `buildClinicalCanonicalPayload({ ... })`
- `buildClinicalDocumentBody({ ... })`

En `modules/clinical/ui/document.php`:

- `clinical_doc_definition_registry()`
- `clinical_doc_get_definition(string $documentType)`

## Alcance de esta fase
- No se implementan nuevos documentos.
- No se altera backend estructural.
- No se rompe wizard de consentimiento.
- Se deja base lista para Interconsulta / Informe médico / Responsiva / Certificados.
