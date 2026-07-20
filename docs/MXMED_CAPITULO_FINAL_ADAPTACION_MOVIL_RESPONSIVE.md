# MXMed — Capítulo final de adaptación móvil y responsive

**Contrato:** `MXMED_MOBILE_RESPONSIVE_FINALIZATION_CHAPTER_V2`

**Estado:** `MOBILE_RESPONSIVE_FINALIZATION_PENDING`

**Clasificación actual:** `UI-0 — NO_UI_IMPACT`

**Clasificación de la ejecución futura:** `UI-3 — UI_VISUAL_CHANGE_REQUIRES_APPROVAL`

**Baseline oficial:** `recovery/mxmed-pre-22-known-good` @ `e4f7d515cba4ae47fcdbd44cd55ce610466b982a`

**URL oficial:** http://127.0.0.1:8091/

## 1. Decisión y propósito

Durante el desarrollo funcional se permiten aproximaciones responsive razonables y cada actividad con frontend debe evitar regresiones móviles evidentes. Esos smoke tests son intermedios: no constituyen aprobación móvil final.

La adaptación integral para teléfonos se resolverá en este capítulo específico al finalizar el desarrollo funcional o inmediatamente antes del cierre de lanzamiento. La dirección visual y UX pertenece al director. El capítulo no ralentiza el avance actual, pero no puede omitirse antes de declarar el producto listo para lanzamiento.

El estado actual es documental y no cambia UI, código, runtime, configuración ni el contador del programa.

## 2. Posición en el programa

- Avance oficial y candidato actual: `2/22`.
- Actividad 3: `NO INICIADA`.
- El capítulo es un gate transversal; no crea automáticamente una Actividad 23.
- Debe coordinarse dentro o inmediatamente antes de las actividades finales de QA mediante una asignación explícita.
- Ningún smoke intermedio puede registrarse como `FINAL_MOBILE_APPROVED`.

## 3. Obligaciones intermedias por actividad

Toda actividad con frontend registra estos campos y ejecuta un smoke mínimo en un viewport representativo:

- ausencia de overflow horizontal;
- controles críticos alcanzables;
- navegación básica utilizable;
- textos sin cortes críticos;
- formularios principales operables;
- modales dentro del viewport;
- touch targets razonables;
- ausencia de regresión respecto del baseline aprobado.

El resultado permitido es `INTERIM_MOBILE_SMOKE_ONLY`. Si aparece una diferencia visual deliberada, se detiene el trabajo y se reclasifica como `UI-3` con `STOP_UI_SCOPE_ESCALATION_REQUIRED`.

## 4. Alcance del capítulo final

La revisión final cubrirá, como mínimo:

- navegación global, menús, encabezados y subheaders;
- panel privado y perfil público;
- Suscripciones;
- Agenda pública y privada;
- Pacientes, Expediente y Recetas;
- formularios, tablas, tarjetas y modales;
- selectores de fecha/hora y carga de archivos;
- teclado virtual, scroll y orientación vertical/horizontal;
- safe areas, accesibilidad táctil, estados vacío/error/loading;
- dashboard de operadores cuando exista.

No se asume que una superficie esté aprobada sólo por haber pasado un smoke intermedio.

## 5. Matriz inicial de viewports y dispositivos

La siguiente matriz es un punto de partida sujeto a dirección, no una lista inmutable:

| Grupo | Viewports iniciales |
|---|---|
| Teléfonos | 320, 360, 375, 390, 412 y 430 px |
| Tablets/transición | 768, 820 y 1024 px |
| Navegadores | Safari iOS, Chrome Android y desktop emulado |
| Validación adicional | dispositivos físicos representativos cuando estén disponibles |

La ejecución final deberá cubrir orientación vertical y horizontal, teclado virtual, scroll, safe areas y touch targets, además del viewport.

## 6. Proceso futuro UI-3

La ejecución requiere, en orden:

1. inventario de superficies;
2. auditoría de problemas;
3. priorización;
4. wireframes cuando sean necesarios;
5. prototipo reversible;
6. branch/worktree separado y puerto `8140+`;
7. comparación con 8091;
8. múltiples viewports y dispositivos;
9. pruebas táctiles, teclado, focus y accesibilidad;
10. revisión del director;
11. aprobación visual expresa;
12. integración;
13. QA posterior;
14. rollback probado.

La frase de aprobación futura es exactamente: **“Apruebo visualmente la adaptación móvil final.”** Sin ella no se integra, promociona ni sustituye 8091.

## 7. Protección y salida

El capítulo no autoriza cambios silenciosos en layout, navegación, cards, tipografía, copy, formularios, breakpoints ni 8091. Ninguna actividad técnica puede usarlo como justificación para adelantar un rediseño móvil.

Para cerrar el capítulo se requiere inventario completo, evidencia de todos los viewports y superficies, ausencia de regresiones críticas, accesibilidad táctil verificada, revisión del director, aprobación visual expresa, checkpoint y rollback probado. Hasta entonces el estado permanece `MOBILE_RESPONSIVE_FINALIZATION_PENDING`.
