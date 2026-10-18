# Estado actual — México Médico

## Ancla y alcance

- Fecha de verificación documental: **2026-10-17**.
- Baseline fuente verificado: `f92f37f1958af5140a96c04cb52c5c1ce7af8e01` (CTX-SYNC01 publicado; inicio del prototipo ASE-VIS01).
- Rama fuente: `ux/consultation-step2-vitals-r1`; al iniciar ASE-VIS01, HEAD/upstream coinciden y worktree limpio. El prototipo vive sólo en `prototype/ase-vis01-advisor-capability-matrix`, worktree separado `mxmed-ase-vis01-prototype`; no se integra a la fuente.
- Worktree: `/Users/circulodigital/Documents/GitHub/mxmed-step2-vitals-r1`; no confundir con el checkout hermano `mxmed-esqueleto-visual`.
- Aplicación original: `https://localhost:8443/private-app.php`; [shell](../private-app.php), [router documental](README.md).
- ASE-ALIGN01 V2 alinea capacidades individuales de asesores con tres operaciones rutinarias de acreditación; no activa normal-local. El ancla es el HEAD fuente inspeccionado, no el hash futuro del cierre.

## Producto existente

- Sistema sustancialmente desarrollado en integración/refinamiento; no se está reconstruyendo.
- Existen Agenda/Consultorio/Maps, Operadores, pacientes/expediente, documentos/Recetas V2, Media, Billing y Suscripciones.
- UI, servicios y writers se localizan en el [router](README.md#mapas-actuales-del-sistema--ctx-map02): 119 capacidades funcionales, 18 componentes AWS y once journeys; existencia no certifica operación completa.
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
| ACT03 | **Source provisioning implementado y verificado en QA aislada**; [cierre](identity/ACT03-governed-professional-sources.md#cierre-verificado). Resultado histórico **PARTIAL_MXMED_ACT03** preservado. |
| ASE-ALIGN01 V2 | Política individual aprobada en [IW01](product-completion/iw01-internal-governance-foundation.md); MFA/consumidores/guard y workspace originales alineados; QA aislada completa. Sin activación real. |
| CTX-SYNC01 | [Regla permanente](../AGENTS.md#sincronización-obligatoria-de-contexto--ctx-sync01) y [campos/gate de entrega](MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md#context-sync) obligatorios en cada cambio autorizado; revisión documental manual, sin nueva QA de producto ni activación. |
| ASE-VIS01 | [Prototipo UI-3 aislado](identity/ASE-VIS01-advisor-matrix-prototype.md): directorio izquierdo/matriz derecha aprobados como concepto, datos ficticios y cambios en memoria. QA documentada en su contrato; apariencia final pendiente del Director, sin adapter ni administración real integrada. |

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
- **Selectores globales no montados:** el hecho documentado en [ACT03](identity/ACT03-governed-professional-sources.md#teléfonos-free-contratos-distintos) permanece; por sí solo no demuestra una operación requerida ausente. ACT04-RECON02 no añade un editor global ni cambia PUB-COR02. Owner/administrator es la política preparada de escritura de Consultorio; CTX-MAP03 distingue su falta de enforcement por rol en el consumidor ordinario actual. No elevar roles ni interpretar esa brecha como aprobación de permisos.
- **Maps:** selector existente preservado. La evidencia ACT03 sitúa errores `util/search_impl` del loader externo en visitas CLAIM al perfil público; no demuestra un defecto del selector privado. Sin sesión Safari privada disponible ni configuración efectiva acreditada, su fallo normal-local queda no reproducido; no se cambia proveedor/configuración.
- **Documentos históricos:** HTML congelado puede compartir CSS actual; cualquier cambio futuro debe evaluar compatibilidad de presentación/paginación y firma.
- **Finanzas:** [mapa Stripe/PAC](contexto/STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md) recupera confirmación de pago y activación explícita separadas; writes Identity/retorno/renovación conservan brechas. PAC seleccionado: SW primario, Finkok secundario; adapter/operación pendientes, no selección nueva.
- **Clínica:** issuer/binding C4 implementado; consumidor profesional todavía deniega incondicionalmente. Alta paciente tiene disparidad de autorización fuente `unresolved`; no se hizo probe ni se cambió autoridad. [Mapa clínico](contexto/FUNCIONES_CLINICAS_MXMED.md).
- **Infra:** IaC y cierres físicos históricos C3/SES recuperados; packaging actual omite shell/workflows y hay divergencia DB env. AWS/Stripe remotos no verificados. [Mapa AWS](contexto/AWS_Y_ENTORNOS_MXMED.md).
- **Casos Agenda:** [CTX-MAP03](contexto/AGENDA_CANALES_Y_PERMISOS_MXMED.md) traza CTA integrado público y matriz por acción; waitlist/no-show no están en los cinco grants de operador. Call Center conserva contrato futuro y consumidor no acreditado; política por rol, scope waitlist y atribución ordinaria tienen brechas fuente explícitas, sin probes ni permisos nuevos.
- **Comercial/asesores:** [trazabilidad](contexto/TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md) preserva ventas, captación, Call Center, cupones y Billing separados. ASE-ALIGN01 aprueba combinaciones individuales sin subtipos ni cupo arbitrario de asesores/capacidades; no heredan las dos plazas del operador médico. Director/MASTER delegado conservan gobierno, ADVISOR no recibe autoridad por clase ni autogrants. La matriz conceptual tiene ahora un [prototipo UI-3 aislado ASE-VIS01](identity/ASE-VIS01-advisor-matrix-prototype.md); la pantalla/adapter reales siguen sin implementar y la aprobación visual final permanece pendiente. Grants de revisión/fuentes y revocación son independientes; MFA sujeto/uso se separan del registrador independiente y operaciones sensibles. Runtime real sigue inactivo.

## Evidencia reciente y reconciliación de tests

- [ACT03](identity/ACT03-governed-professional-sources.md#cierre-verificado) registra 127 checks nativos de fuentes, 16 de contactos FREE y 12 journeys de fuente → PUB-ENR03 → C4 en Chromium/WebKit y tres tamaños.
- Documenta también regresiones aisladas Identity, Operadores, Agenda, VID01/CRD02 y correcciones; no son QA física Safari del Director ni activación real.
- ASE-ALIGN01 V2: **464 checks nativos** (139 asesores/MFA/MVCC, 132 ACT03, 50 guard, dos schema, 89 enrollment, 36 comercial y 16 contactos) y seis recorridos pareados Chromium/WebKit en tres tamaños pasan. Workspace original, grants independientes y operaciones sensibles preservados; cero datos reales importados/escritos y recursos de ensayo eliminados.
- Regresiones suplementarias: gobierno/ceremonia/HTTP y 200 checks de Operadores con nueve subtests pasan; checksums `mxmed`/QA05 iguales. AUTH02A actual falla sólo por README histórico en su gate de diff: test/pins intactos pasan completos en copia temporal con ese README original y 250 fuentes clínicas actuales idénticas; copia eliminada. Sin relajación de pins.
- Evidencia ASE privada: `~/Library/Application Support/MXMED/ase-align01-v2/20261017T211943Z/final-acceptance`; no copiar fixtures, conexiones, documentos o secretos a Git. Evidencia histórica ACT03 permanece en su cierre.
- ACT04-RECON02: [test de auditoría](../modules/platform/tests/PhysicianCredentialAuditPolicyTest.php) actualizado al catálogo de 57, conservando digest de las 52 políticas publicadas y metadata OP-IMP07 exacta. Doble PDO adaptado a lectura de target/rutinas; cinco denegaciones de deriva verificadas, cero conexiones SQL reales.
- [Test de hero/contactos](../modules/profiles/tests/PublicProfileHeroConsultorioContactLayoutTest.php): comprueba glifos requeridos en la solicitud de fuente correcta, admitiendo orden/glifos adicionales; conserva las demás assertions de presentación y privacidad.
- [AUTH02A_29](../modules/clinical/qa/clin_auth02a_contract.php), modo `--security-regression`: admite sólo la fixture UX-ALIGN01 exacta, ligada por SHA-256 a su entrega. No cambia baseline productivo, pins clínicos, writer, autoridad ni exclusiones generales.
- En ACT04 pasaron los tres tests corregidos, Consultorio y visibilidad/contactos FREE. Son evidencia histórica de expectativas/dobles, no activación. Para la ejecución AUTH02A de ASE rige el caveat de README anterior; el modo histórico de entrega conserva sus restricciones originales.
- Contactos administrativos: 14 comprobaciones Chromium/WebKit del markup y módulo JS originales con red sintética en memoria (carga, teléfono/WhatsApp, flags privados, entrada inválida y 403 sin falso éxito). No acredita autoridad/persistencia SQL ni Safari físico; no hubo pestaña privada Safari disponible. Probes negativos conservan rechazo de glifos ausentes y deriva de fixture/pin clínico.

## Cobertura, autorizaciones y continuidad

- CTX-MAP02: censo de 2.967 paths/19 raíces/doce módulos, 183 entradas candidatas y 49 declaraciones literales (no universo dinámico); [cobertura y doce retrieval checks](contexto/COBERTURA_Y_PENDIENTES_MXMED.md).
- CTX-MAP03: 35 casos (diez Agenda, quince Billing, diez comerciales), matriz de cinco canales y nueve ejemplos documentales; [respuestas y validación](contexto/COBERTURA_Y_PENDIENTES_MXMED.md#ctx-map03). No son nuevas funciones ni tests de producto.
- Metadata heredada de CTX-MAP02, no consultada de nuevo en SQL: 128 tablas, cero vistas, 1.734 columnas, 138 FK, 35 triggers/once rutinas. Unión fuente/física de 204 nombres; todos con dominio; nueve sin CREATE quedan explícitos y 36 gaps léxicos de writer se reconciliaron por revisión dirigida. [Datos](contexto/DATOS_Y_MIGRACIONES_MXMED.md).
- CTX-MAP03 sólo documentación y lectura dirigida de fuente/metadata ya publicada; sin filas reales, conexión SQL nueva, tests de producto, AWS/Stripe, envíos ni reinicios. Se conservan mapas antiguos con avisos de sucesor y ACT04 como tests corregidos.
- Siguiente autorización operacional: despliegue/cohortes/staff/MFA/custodias/proveedores reales por separado; ASE-ALIGN01 V2 no los habilita. ASE-VIS01 cubre sólo el prototipo reversible de la matriz de asesores; aprobación visual e integración al workspace original siguen pendientes. Los mapas no autorizan DDL, C4, owner, firma ni operadores.
- Incertidumbres: remoto/proveedores/runtime físico; variantes SQL/rutas dinámicas; UI parciales, atribución comercial y lineage físico. Decisiones comerciales/seguridad aprobadas no se reabren por README históricos.
- Inicio de nueva tarea: verificar Git/instrucciones, leer este estado, seguir un dominio del router y contrastar decisión/UI/writer antes de proponer cambios.
- Cierre: baseline/post HEAD, alcance, comportamiento, pruebas y entorno, límites, referencias y autorizaciones pendientes; usar la [plantilla existente](MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md).
- Mantenimiento **manual y obligatorio como parte de cada cambio autorizado**: revisar contexto, actualizar sólo IDs/journeys/objetos y documentos impactados y este resumen ante cambios materiales. Registrar los diez campos de entrega; `BLOCKED_INCOMPLETE` impide COMPLETE y `NO_IMPACT` requiere razón precisa. Validar coherencia, evidencia y enlaces, conservando historia en Git/contratos.
- ChatGPT puede consultar los mismos archivos publicados cuando tenga acceso o se adjunten; no hay memoria automática compartida con Codex.
- [Historia y decisiones](README.md#historia-decisiones-y-continuidad) se recuperan bajo demanda; el contenido histórico se conserva con avisos de sucesor donde corresponde.
