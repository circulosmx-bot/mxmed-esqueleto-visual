# CONSENTIMIENTO INFORMADO — MXMed

## 1. Objetivo del módulo
El módulo de Consentimiento informado permite capturar, emitir, firmar, anexar identidad y consultar consentimientos clínico-legales en un flujo canónico único basado en `clinical_documents`, manteniendo trazabilidad para historial, lectura clínica y versión imprimible.

## 2. Flujo de usuario (wizard / vista completa)
- Entrada principal desde `Registrar actividad clínica` hacia el flujo real de consentimiento (`#t-consent`).
- Dos modos de captura sobre estado compartido:
  - `Modo guiado` (wizard).
  - `Vista completa` (documento editable integral).
- Acciones finales:
  - `Guardar borrador` (`status=draft`).
  - `Emitir consentimiento` (`status=granted`) con validaciones visibles.
- Los consentimientos previos se muestran como histórico de referencia debajo del panel de creación.

## 3. Estructura de datos (payload)
Documento canónico:
- `document_type=consentimiento_informado`
- persistido vía `/api/clinical/index.php/documents`

Estructura funcional base (resumen):
- `status`: `draft | granted`
- `title`, `summary`, `context`
- `payload`:
  - datos clínico-legales capturados en wizard/vista completa
  - datos de firmante (calidad jurídica y relación)
  - testigos
  - `rendered_text` (redacción legal final)
  - `signatures` (firma local y/o remota)
  - anexos de identidad del firmante (referencias relacionadas)

Notas de compatibilidad:
- Se mantiene retrocompatibilidad con consentimientos previos.
- El modelo está preparado para extender firma y anexos sin romper contrato actual.

## 4. Redacción legal
- El consentimiento se ensambla con plantilla legal estructurada a partir de los datos capturados.
- `rendered_text` concentra la redacción final para lectura clínica/legal.
- La redacción alimenta tanto `viewer.php` como la versión imprimible en `document.php`.

## 5. Firma
### 5.1 Firma local
- Captura en canvas dentro del módulo (mouse/touch).
- Guardado en `payload.signatures` con metadatos de origen y fecha.
- Render en viewer y documento imprimible cuando existe.

### 5.2 Firma remota por QR
- Flujo tokenizado con sesión móvil, QR y polling en escritorio.
- Operación validada en red local.
- Al completar, la firma remota se integra al consentimiento en estructura canónica de firmas.

## 6. Anexos de identidad
### 6.1 Flujo QR
- Sección de anexos para identidad del firmante.
- Captura remota por QR con sesión móvil (cámara o selección de archivo).
- Polling en escritorio para reflejar recepción y estado de sesión.

### 6.2 Almacenamiento
- Anexos tratados como documentos clínicos relacionados, no incrustados forzosamente en el cuerpo legal.
- Referencias de anexos visibles en lectura del consentimiento de forma ordenada.

## 7. Viewer
- Lectura clínica/legal limpia en `modules/clinical/ui/viewer.php`.
- Sin bloque JSON técnico en vista normal.
- Muestra:
  - encabezado legal,
  - datos del paciente,
  - contenido del consentimiento,
  - estado (`draft` / `granted`),
  - firmas,
  - anexos de identidad.

## 8. Documento imprimible
- Versión carta en `modules/clinical/ui/document.php`.
- Acciones visibles: imprimir, descargar, versión imprimible.
- Mantiene firma digital si existe; si no, conserva línea de firma física.

## 9. Integración a historial
- Los consentimientos se reflejan en `Historial de Atención` como eventos clínicos.
- Tienen icono propio y acceso a apertura de detalle.
- No se rompe la lectura de otros tipos documentales.

## 10. Consideraciones UX
- El flujo prioriza creación/edición del consentimiento actual; histórico abajo como referencia.
- Validaciones de emisión son explícitas y visibles para evitar errores silenciosos.
- Estados QR muestran progreso claro (pendiente, recibido, completado).
- Microcopy clínico y no técnico para reducir fricción operativa.

## 11. Decisiones de diseño
- Fuente de verdad única: `clinical_documents`.
- Un solo flujo canónico de guardado para wizard y vista completa.
- Reutilización de patrón QR existente (token + móvil + polling), sin arquitectura paralela.
- Lectura legal separada de representación técnica del payload.

## 12. Backlog futuro (sin implementar)
- Revocación formal (`revoked`) con trazabilidad de cambios.
- Endurecimiento legal adicional según lineamientos regulatorios aplicables (incluyendo COFEPRIS cuando corresponda).
- Obligatoriedad de datos de contacto desde puertas de entrada del paciente (alta, agenda, operadora), no desde consentimiento.
- Firma digital certificada.
- Exportación PDF firmado final.
