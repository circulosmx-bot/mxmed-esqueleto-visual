# Plan de implementación · Agenda Médica v1
**Proyecto:** México Médico (mxmed-esqueleto-visual)
**Módulo:** Agenda Médica
**Versión:** v1.0 (draft)
**Fecha:** 2026-01-30

## Roadmap fases I–VI
1. Fase I: Bootstrap técnico (estructura, SQL stubs, endpoints read-only) – completada en esta rama.
2. Fase II: Implementar repositorios + servicios conectando a la base de datos y migraciones.
3. Fase III: Motor de disponibilidad A/B/C + bitácora real de slots.
4. Fase IV: Pagos online y flags (pending_payment, verify proofs).
5. Fase V: UI dinámica (FullCalendar + vistas reactivas, validaciones en tiempo real).
6. Fase VI: Integraciones externas (Google/Outlook, pasarelas, notificaciones automáticas).

## Nota
FullCalendar se integra hasta Fase VI; en Fase I solo dejamos la base técnica.

## Criterios de esta fase (I.1)
- Estructura de módulos (controllers/services/repositories/validators).
- SQL stubs bajo database/agenda.
- Endpoints read-only con respuesta 501.
- Documentación en docs/agenda/19_implementacion_plan.md y otros capítulos.

## Qué NO se hizo aún
- No se implementó lógica de disponibilidad A/B/C.
- No hubo conexión real a base de datos ni bitácora persistente.
- No se desplegó UI dinámica ni integración con librerías externas.
