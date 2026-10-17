# Estado actual — México Médico

## Ancla y alcance

- Fecha de verificación documental: **2026-10-17**.
- Baseline fuente verificado: `e3baebe40ed5f0967712355c9399859c5056070e` (ACT03 publicado).
- Rama: `ux/consultation-step2-vitals-r1`; al iniciar CTX-IMP01, HEAD/upstream/remoto coinciden y worktree limpio.
- Worktree: `/Users/circulodigital/Documents/GitHub/mxmed-step2-vitals-r1`; no confundir con el checkout hermano `mxmed-esqueleto-visual`.
- Aplicación original: `https://localhost:8443/private-app.php`; [shell](../private-app.php), [router documental](README.md).
- CTX-IMP01 sólo crea contexto documental. Este snapshot no constituye nueva QA funcional ni inspección de datos, ni contiene el hash futuro de su propio commit.

## Producto existente

- Sistema sustancialmente desarrollado en integración/refinamiento; no se está reconstruyendo.
- Existen Agenda/Consultorio/Maps, Operadores, pacientes/expediente, documentos/Recetas V2, Media, Billing y Suscripciones.
- Sus UI, servicios y writers se localizan en el [router por dominio](README.md#componentes-y-contratos); su existencia no certifica operación completa de todo el dominio.
- La aplicación privada usa Identity y resolución clínica canónicas. Preservar ámbito médico/paciente, sesiones y decisiones visuales existentes.
- Recetas conserva editor, renderer, firma, historial/snapshots y lifecycle; [evaluación de presentación](clinical/UX-ALIGN01-prescription-presentation-assessment.md).
- Operadores conserva panel original, alias, historial, dos plazas y cinco grants Agenda; gestión sujeta a owner/servicio habilitado. No hay activación completa acreditada por este documento.
- Suscripciones y DTO público separan fuente comercial, capacidades y publicación. El widget de revisión no cambia una suscripción real.

## Hitos recientes: fuente y evidencia, no activación implícita

| Hito | Clasificación actual y referencia |
|---|---|
| GOV-SIM02 | Política/servicios y QA documentados; preservar separación médico/owner/firma. [Contrato](identity/GOV-SIM02-physician-ownership-session.md). Su antigua ausencia del issuer fue sucedida por C4. |
| C4-EV01 | Evidencia cifrada, revisión protegida y controles implementados; [fundación](identity/C4-EV01-protected-evidence-foundation.md). Runtime normal-local inactivo. |
| C4-ADM02-IMP02 | Issuer/admisión/binding canónicos implementados con QA aislada; [contrato](identity/C4-ADM02-IMP02-physician-authority-issuer.md). No asignan owner automáticamente. |
| C4-UI01 | Integración del solicitante y staff originales, QA aislada; [UI](identity/C4-UI01-original-physician-onboarding.md). No equivale a operación real habilitada. |
| C4-ACT01 | Preparación de despliegue y ensayo **históricos**; [paquete](identity/C4-ACT01-operational-activation-package.md). Sus fingerprints no son autorización actual. |
| PUB-ENR02 | Origen confiable e intake persistente implementados; [contrato](identity/PUB-ENR02-pending-enrollment.md). Normal-local inactivo. |
| PUB-ENR03 | Resolución gobernada y handoff C4 implementados; [contrato](identity/PUB-ENR03-reviewed-resolution-handoff.md). Preservar CLAIM/FORMAR PARTE. |
| PUB-COR01 | Intake seguro y revisión humana implementados; [contrato](profiles/PUB-COR01-secure-intake-human-review.md). Normal-local inactivo. |
| PUB-COR02 | Aplicación tipada y recuperación implementadas para teléfono global publicado; [contrato](profiles/PUB-COR02-governed-typed-application.md). No aplica teléfonos FREE ocultos. |
| ACT02 | Factory/guard, fuente comercial y ensayo integrado implementados; [paquete](identity/ACT02-integrated-public-workflows.md). Descripción del writer gobernado faltante es histórica de ACT02. |
| ACT03 | **Source provisioning implementado y verificado en QA aislada**; [cierre](identity/ACT03-governed-professional-sources.md#cierre-verificado). Resultado global permanece **PARTIAL_MXMED_ACT03**. |

## Estado operacional y migraciones

- Normal-local original: HTTPS 8443 → PHP 8091; Identity PHP 8092 y Valkey 6385 según evidencia reciente. No reiniciar para tareas documentales.
- C4, enrollment, correcciones y provisioning ACT03 permanecen **IMPLEMENTED_BUT_INACTIVE** en normal-local.
- [OperationalRuntime](../modules/identity/Operational/OperationalRuntime.php) sólo compone con instalación privada y aprobación; deshabilitado no significa ausente.
- CTX-AUD02 confirmó ausencia de `installation.json`, `authorization.json` y `trust.json` en el directorio privado `public-workflows`; no es permiso para crearlos.
- ACT02 prepara diez etapas ordenadas: Identity 01–06, correcciones 01/02, fuente FREE explícita y metadata reader; [manifiesto en fuente](../modules/identity/Operational/DeploymentPackage.php).
- ACT03 añade la etapa [2026_10_17_07_professional_source_provisioning.sql](../modules/identity/db/migrations/2026_10_17_07_professional_source_provisioning.sql): cinco tablas, FKs/checks, siete triggers y vínculo de evidencia C4.
- No se aplicaron esas migraciones a `mxmed` en ACT02/ACT03. Preservar objetos previos OP-ACT02A; no replay ciego ni backfill inferido.
- Operación real exige autorización específica, preflight físico/fingerprints vigentes, backup/restauración, principal mínimo y definers revisados.
- También requiere políticas/custodia/retención, proveedor SMS aprobado, llaves privadas, cohortes, staff/grants y MFA reales gobernados.
- Un manifiesto, una tabla existente o pruebas desechables no satisfacen esa autorización. Ownership y firma clínica conservan sus gates propios.

## Brechas técnicas vigentes

- **Teléfono global de consulta:** API/writer existen; `mx-admin-phone` edita contacto administrativo. Handlers JS globales existen, pero faltan `mx-dg-public-contact-list`/`mx-dg-contact-create-form` en el shell. [Distinción ACT03](identity/ACT03-governed-professional-sources.md#teléfonos-free-contratos-distintos).
- **Maps:** selector de Consultorio ya existe; ACT03 conserva errores del loader externo en tres visitas CLAIM WebKit. No se acredita aceptación completa de esas páginas.
- **Documentos históricos:** HTML congelado puede compartir CSS actual; cualquier cambio futuro debe evaluar compatibilidad de presentación/paginación y firma.
- **Billing:** existen perfiles, archivo y borradores; [FISC02B](billing/FISC02B_ISSUANCE_CORE.md) mantiene timbrado sujeto a decisión/contrato PAC. No inferir emisión productiva.
- Los estados de servicios y datos pueden cambiar después del baseline; revalidar sólo lo necesario dentro del alcance autorizado.

## Evidencia reciente y fallos conocidos

- [ACT03](identity/ACT03-governed-professional-sources.md#cierre-verificado) registra 127 checks nativos de fuentes, 16 de contactos FREE y 12 journeys de fuente → PUB-ENR03 → C4 en Chromium/WebKit y tres tamaños.
- Documenta también regresiones aisladas Identity, Operadores, Agenda, VID01/CRD02 y correcciones; no son QA física Safari del Director ni activación real.
- Evidencia detallada privada/manifiestos: ubicación en el cierre ACT03; no copiar logs, documentos, conexiones o secretos a Git.
- Fallo histórico: `modules/platform/tests/PhysicianCredentialAuditPolicyTest.php` fija 52 políticas frente a 57 documentadas en el cierre.
- Fallo histórico: `modules/profiles/tests/PublicProfileHeroConsultorioContactLayoutTest.php` exige un literal anterior de icono.
- Fallo histórico: `modules/clinical/qa/clin_auth02a_contract.php --security-regression`, caso `AUTH02A_29`, por fixture de presentación preexistente.
- No relajar esas pruebas ni declarar sus runners completos verdes. CTX-IMP01 no las reejecuta ni cambia producto.

## Foco, autorizaciones y continuidad

- Foco actual autorizado: **CTX-IMP01**, únicamente `AGENTS.md`, `docs/README.md` y este estado; validación documental, commit y push normal.
- Siguiente paso de producto aprobado: **NONE**. ACT04 no fue presentado/aprobado; no inferir sus instrucciones.
- Decisiones pendientes: alcance de integración del teléfono global, tratamiento de incidencias Maps/tests históricos y eventual piloto/despliegue real con sus prerrequisitos.
- Anotaciones de README/guías antiguas, nuevos skills/instrucciones anidadas y automatización requieren alcance posterior; no forman parte de CTX-IMP01.
- Inicio de nueva tarea: verificar Git/instrucciones, leer este estado, seguir un dominio del router y contrastar decisión/UI/writer antes de proponer cambios.
- Cierre: baseline/post HEAD, alcance, comportamiento, pruebas y entorno, límites, referencias y autorizaciones pendientes; usar la [plantilla existente](MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md).
- Mantenimiento **manual y sólo autorizado**: reemplazar el resumen vigente después de un cambio material; anclar fecha/baseline verificados y preservar la historia en Git/contratos.
- ChatGPT puede consultar los mismos archivos publicados cuando tenga acceso o se adjunten; no hay memoria automática compartida con Codex.
- [Historia y decisiones](README.md#historia-decisiones-y-continuidad) se recuperan bajo demanda; los documentos antiguos permanecen intactos.
