# Estado actual — México Médico

## Ancla y alcance

- Fecha de verificación documental: **2026-10-17**.
- Baseline fuente verificado: `04744a9b3ebf0ff644979e8617d09f67bc4f398f` (CTX-IMP01 publicado sobre ACT03).
- Rama: `ux/consultation-step2-vitals-r1`; al iniciar ACT04-RECON02, HEAD/upstream/remoto coinciden y worktree limpio.
- Worktree: `/Users/circulodigital/Documents/GitHub/mxmed-step2-vitals-r1`; no confundir con el checkout hermano `mxmed-esqueleto-visual`.
- Aplicación original: `https://localhost:8443/private-app.php`; [shell](../private-app.php), [router documental](README.md).
- ACT04-RECON02 reconcilia componentes y corrige tres tests históricos; no cambia UI, writers ni runtime. El ancla es su baseline, no el hash futuro de este archivo.

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

- **Contactos privados:** Información personal ya conecta teléfono/WhatsApp administrativos con `doctor_contact_points`; Consultorio conecta teléfonos/WhatsApp de consulta con `consultorios.telefonos_json/whatsapp`. Seguridad/OTP conserva su autoridad separada. FREE puede almacenar datos privados; el DTO público conserva el ocultamiento por plan.
- **Selectores globales no montados:** el hecho documentado en [ACT03](identity/ACT03-governed-professional-sources.md#teléfonos-free-contratos-distintos) permanece; por sí solo no demuestra una operación requerida ausente. ACT04-RECON02 no añade un editor global ni cambia PUB-COR02. Consultorio mantiene permisos de escritura owner/administrator; no elevar al collaborator para probarlo.
- **Maps:** selector existente preservado. La evidencia ACT03 sitúa errores `util/search_impl` del loader externo en visitas CLAIM al perfil público; no demuestra un defecto del selector privado. Sin sesión Safari privada disponible ni configuración efectiva acreditada, su fallo normal-local queda no reproducido; no se cambia proveedor/configuración.
- **Documentos históricos:** HTML congelado puede compartir CSS actual; cualquier cambio futuro debe evaluar compatibilidad de presentación/paginación y firma.
- **Billing:** existen perfiles, archivo y borradores; [FISC02B](billing/FISC02B_ISSUANCE_CORE.md) mantiene timbrado sujeto a decisión/contrato PAC. No inferir emisión productiva.
- Los estados de servicios y datos pueden cambiar después del baseline; revalidar sólo lo necesario dentro del alcance autorizado.

## Evidencia reciente y reconciliación de tests

- [ACT03](identity/ACT03-governed-professional-sources.md#cierre-verificado) registra 127 checks nativos de fuentes, 16 de contactos FREE y 12 journeys de fuente → PUB-ENR03 → C4 en Chromium/WebKit y tres tamaños.
- Documenta también regresiones aisladas Identity, Operadores, Agenda, VID01/CRD02 y correcciones; no son QA física Safari del Director ni activación real.
- Evidencia detallada privada/manifiestos: ubicación en el cierre ACT03; no copiar logs, documentos, conexiones o secretos a Git.
- ACT04-RECON02: [test de auditoría](../modules/platform/tests/PhysicianCredentialAuditPolicyTest.php) actualizado al catálogo de 57, conservando digest de las 52 políticas publicadas y metadata OP-IMP07 exacta. Doble PDO adaptado a lectura de target/rutinas; cinco denegaciones de deriva verificadas, cero conexiones SQL reales.
- [Test de hero/contactos](../modules/profiles/tests/PublicProfileHeroConsultorioContactLayoutTest.php): comprueba glifos requeridos en la solicitud de fuente correcta, admitiendo orden/glifos adicionales; conserva las demás assertions de presentación y privacidad.
- [AUTH02A_29](../modules/clinical/qa/clin_auth02a_contract.php), modo `--security-regression`: admite sólo la fixture UX-ALIGN01 exacta, ligada por SHA-256 a su entrega. No cambia baseline productivo, pins clínicos, writer, autoridad ni exclusiones generales.
- Los tres tests corregidos pasan; también contratos de Consultorio y visibilidad/contactos FREE. Son correcciones de expectativas/dobles obsoletos, no evidencia de regresión productiva ni nueva activación. El modo histórico de entrega de AUTH02A conserva sus restricciones originales.
- Contactos administrativos: 14 comprobaciones Chromium/WebKit del markup y módulo JS originales con red sintética en memoria (carga, teléfono/WhatsApp, flags privados, entrada inválida y 403 sin falso éxito). No acredita autoridad/persistencia SQL ni Safari físico; no hubo pestaña privada Safari disponible. Probes negativos conservan rechazo de glifos ausentes y deriva de fixture/pin clínico.

## Foco, autorizaciones y continuidad

- Alcance autorizado **ACT04-RECON02**: inspección dirigida, correcciones verificadas de los tres tests y continuidad de este estado; commit/push normal. Contexto CTX-IMP01 preservado.
- Siguiente paso de producto aprobado: **NONE**. ACT04-RECON02 no autoriza un despliegue ACT04, DDL, SMS, staff/MFA, C4 ni activación de operadores.
- Decisiones pendientes: sólo si se requiere una operación global distinta de los campos existentes, precisar su propósito/UX; diagnóstico Maps con acceso/configuración apropiados y eventual piloto real con sus prerrequisitos.
- Anotaciones de README/guías antiguas, nuevos skills/instrucciones anidadas y automatización requieren alcance posterior; no forman parte de CTX-IMP01.
- Inicio de nueva tarea: verificar Git/instrucciones, leer este estado, seguir un dominio del router y contrastar decisión/UI/writer antes de proponer cambios.
- Cierre: baseline/post HEAD, alcance, comportamiento, pruebas y entorno, límites, referencias y autorizaciones pendientes; usar la [plantilla existente](MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md).
- Mantenimiento **manual y sólo autorizado**: reemplazar el resumen vigente después de un cambio material; anclar fecha/baseline verificados y preservar la historia en Git/contratos.
- ChatGPT puede consultar los mismos archivos publicados cuando tenga acceso o se adjunten; no hay memoria automática compartida con Codex.
- [Historia y decisiones](README.md#historia-decisiones-y-continuidad) se recuperan bajo demanda; los documentos antiguos permanecen intactos.
