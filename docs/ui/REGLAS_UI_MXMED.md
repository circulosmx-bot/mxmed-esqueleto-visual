# Reglas UI MXMed

## Propósito
Este documento define la **metodología obligatoria** para cambios UI/UX y para redactar prompts de trabajo en MXMed.

## Referencia obligatoria
- `docs/ui/GLOSARIO_UI_MXMED.md`

## Reglas operables
- Antes de cualquier cambio UI: buscar definición, consultar glosario, y si no existe término, documentarlo primero.
- Antes de cualquier cambio funcional: buscar `DECISION_*.md`; no duplicar contratos, entidades ni fuentes de verdad.
- Diferenciación blindada: “Historia Clínica” y “Historial de Atención” se tratan como conceptos distintos según glosario.
- Los cambios deben ser pequeños y reversibles: 1 commit por capa (UI, API, docs, etc.).
- Toda entrega debe incluir: prompt para Codex, comandos de terminal ejecutables y lista concreta de qué revisar.

## UI Preflight
- Confirmar términos en `docs/ui/GLOSARIO_UI_MXMED.md`.
- Verificar ubicación exacta del cambio en UI (archivo, sección, selector/tab).
- Revisar si existe patrón visual reutilizable antes de crear uno nuevo.
- Definir impacto en modo standalone vs embed (si aplica).
- Validar nomenclatura visible (labels, títulos, tabs) contra glosario.
- Preparar QA mínimo: búsqueda (`rg`), revisión visual y diff acotado.
- Asegurar reversibilidad (alcance acotado y commit pequeño).

## Funcional Preflight
- Revisar `docs/clinical/DECISION_*.md` y contratos aplicables.
- Confirmar que no se alteran contratos JSON ni rutas sin decisión explícita.
- Verificar que no se duplica lógica entre módulos.
- Mantener separación de capas (UI vs gateway/API vs DB).
- Definir comportamiento esperado en error/empty state antes de editar.
- Preparar validación técnica mínima (`php -l`, curl, smoke flow).
- Documentar supuestos y límites del cambio en la entrega.

## Dónde buscar
- `docs/ui/...`
- `docs/clinical/...`
- `modules/_partials/...`
- `modules/clinical/ui/...`
