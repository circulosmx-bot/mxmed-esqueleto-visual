# Estado actual — México Médico

## Ancla y alcance

- Fecha de verificación documental: **2026-10-20**.
- Baseline oficial fuente verificado: `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`; rama oficial/upstream/remoto coincidentes y limpios al inicio ASE-INT01 R2.
- Hito base ASE-INT01: rama aislada `feature/ase-int01-internal-team-readonly`, worktree `/Users/circulodigital/Documents/GitHub/mxmed-ase-int01-readonly`. Fuente oficial `mxmed-step2-vitals-r1` y prototipo visual312ee388 conservados; no confundir con `mxmed-esqueleto-visual`.
- Aplicación original `https://localhost:8443/private-app.php` sin cambios. [ASE-INT01 R2](identity/ASE-INT01-R2-internal-team-readonly.md): sección Equipo del staff original con Identity canónica, sólo lecturas autorizadas, preview aislado8141, sin merge/promoción.
- Aprobación visual R2/COM/ADM registrada con la respuesta real “apruebo”; no autoriza admisión, grants, documentos, finanzas ni operación real. El ancla no pretende ser el hash futuro del cierre.

## Hito previo aislado ASE-INT02A

- Feature `feature/ase-int02a-governed-advisor-grants`, worktree `mxmed-ase-int02a-governed-grants`, padre `09d3aefcf20266641d81aa128cec05f4a9e4ca03`. Oficial, padre y prototipo conservados.
- [ASE-INT02A](identity/ASE-INT02A-governed-advisor-grants.md): POST individual grant/revoke a ADVISOR activo, Identity/CSRF canónicos, mismo writer con precondición opcional bajo locks y auditoría atómica por request. Master exige management y delegación exacta. Gate de mutación deshabilitado por defecto.
- UI original del equipo sigue **sólo consulta**, sin guardado grupal ni cambio visual; la ausencia histórica de adapter de commands queda sucedida únicamente para esta API aislada. 122 checks sintéticos PASS, siete carreras reales, evidencia/descarte propios; no prueba ni activa datos/runtimes reales.
- Siguiente fase: autorización de conexión UI con confirmación y reconciliación de resultados parciales. ADM/D01–D08, readers pendientes, Master, finanzas y activación mantienen gates separados.

## Hito previo aislado ASE-INT02B

- Hito ASE-INT02B: `feature/ase-int02b-team-permission-ui`, worktree `mxmed-ase-int02b-team-permission-ui`, padre `05e428cf4354b1f722090c7532ced3784e0d4985`. Los anchors anteriores describen sus hitos históricos; no son la rama actual de esta entrega.
- [ASE-INT02B](identity/ASE-INT02B-team-permission-ui.md) conecta la matriz aprobada a GET read/CSRF y POST individuales canónicos. Borrador por target, descarte explícito, confirmación, secuencia, éxito parcial e incertidumbre con lectura autoritativa; sin retries ni compensaciones.
- Disponibilidad servidor y bootstrap; Master sólo delegación exacta, ocultos UNKNOWN. Gate deshabilitado por defecto; sólo QA sintética nueva lo habilita. Gobierno, admisión, documentos, delegaciones Master, finanzas y consumidores incompletos siguen sin conexión en esta UI.
- QA real con MySQL/Valkey nuevos, audit y carreras de dos conexiones; Chromium/WebKit en cuatro tamaños. CSS aprobados preservados; UI-2 de comportamiento/copy autorizado. Safari físico NOT_RUN y móvil interino. Sin merge, despliegue o operación real. La deuda UI de ASE-INT02A queda sucedida sólo dentro de esta feature.

## Sucesor aislado ASE-INT03A

- Entrega ASE-INT03A: `feature/ase-int03a-advisor-lifecycle`, worktree `mxmed-ase-int03a-advisor-lifecycle`, padre `36eeb51655a1c24248405feae579cefc06ff3b44`.
- [ASE-INT03A](identity/ASE-INT03A-advisor-lifecycle.md) conecta sólo suspender/reactivar Advisors mediante el mismo writer y boundary HTTP, con scope de categoría/estado/cuenta/autoridad revalidado bajo locks. Grants e historia conservados; no habilita cuentas desactivadas ni restaura grants revocados.
- Botones/modal aprobados, borrador de permisos protegido, refresh y ACK incierto sin retry. 223 checks dirigidos, seis carreras SQL y ocho combinaciones Chromium/WebKit; CSS aprobado preservado. Safari físico NOT_RUN y móvil interino.
- Gate default off; recursos sintéticos propios dispuestos, cinco worktrees/listeners/pointer protegidos sin cambios. Sin merge, promoción, operación real, Master/admisión o decisiones comerciales D06.

## Preparación histórica ASE-INT03B-PREP01

- Entrega documental en `feature/ase-int03b-master-governance-prep`, worktree `mxmed-ase-int03b-master-governance-prep`, padre exacto `b23d4b924d1170fc250afe3ed01555838ee684c8`. [Contrato privilegiado Master](identity/ASE-INT03B-PREP01-master-privileged-authorization.md): autoridad aprobada, operaciones directas existentes y flujo HTTP/MFA específico aún no implementado.
- Allowlist MFA y CHECK de casos no admiten Master; ampliar sólo nombres no basta: falta Director-only, contexto exacto y consumo proof/mutación/audit en una transacción. Inspección detecta reemplazo no acumulativo del CHECK en ACT03; requiere rehearsal posterior, sin afirmar schema instalado.
- UI-2 propuesta pendiente, controles Master deshabilitados; admisión/identidad y D01–D08 siguen separados. Sólo fuente/documentación inspeccionadas, sin QA de producto nueva, DDL/DML, MFA real, merge, despliegue o activación.

## Sucesor aislado ASE-INT03B-IMP01

- Feature `feature/ase-int03b-master-privileged-backend`, padre exacto `e71f3618772ff652a40bb597d45c199d5e851d75`. [Fundación Master](identity/ASE-INT03B-IMP01-master-security-foundation.md): cuatro decisiones aprobadas; seis operaciones Director-only con caso, contraseña, MFA y proof/writer/audit en un PDO. Concepto UI-2 aprobado; conexión sucedida por IMP02 aislado.
- Promoción producto **BLOCKED_PENDING_STAFF_IDENTITY_REVIEW**; snapshot positivo sólo en fixture CLI sintético. Gate propio default off. Controles Master, layout y derechos históricos preservados; no activación real.
- Rehearsal de CHECK acumulativo en MySQL nuevo; ACT02 con filas de corrección → ACT03 se detiene por incompatibilidad histórica, conservando datos. No declara todas las rutas PASS. 321 checks PASS y nueve carreras observadas; límites exactos en contrato/evidencia. Infra nueva dispuesta y siete worktrees/listeners/pointer preservados.

## Sucesor aislado ASE-INT03B-IMP02

- Feature `feature/ase-int03b-master-ui`, padre exacto `ca8a6181db01eb32ee4e19b07fa46c0b53c4f1c7`. [Conexión UI-2 autorizada](identity/ASE-INT03B-IMP02-master-ui-integration.md) sobre modal/controles/familias existentes; sin rediseño ni segundo writer.
- Cinco acciones elegibles con caso/contraseña/MFA y reconciliación; propuesta Master visible pero **BLOCKED_PENDING_STAFF_IDENTITY_REVIEW**. Borradores/contexto protegidos, secretos sólo en memoria, no retries. Gates propios off fuera de QA.
- QA sintética cerrada: 812 checks PASS, nueve carreras, Chromium/WebKit en cuatro viewports y 64 capturas. Infra propia dispuesta y ocho worktrees/listeners/pointer preservados. Route D sigue BLOCKED_HISTORICAL_ACT03_CHECK. Sin merge, deploy, staff/MFA/DB reales ni runtime original modificado; móvil interino y Safari físico NOT_RUN.

## Sucesor aislado ACT03-CHECK-COMPAT01

- Feature `feature/act03-governance-check-compat01`, padre exacto `e3182791fd414c1195e3a7bb64bbb60d20d00530`. [Sucesor SQL](identity/ACT03-CHECK-COMPAT01-historical-case-check.md) sustituye la ruta del CHECK ACT03 antes del fallo histórico, conservando fuentes/evidencia anteriores.
- Paquete ACT02 intacto → CHECK compatible 11 → mismos objetos ACT03; Master 17 requiere su extensión separada. UI/writers/MFA intactos. 408 checks SQL PASS en A–H; D conserva correcciones y H recupera mediante backup verificado en datadir nuevo, con replay in-place bloqueado. Diez datadirs finales retirados y nueve worktrees protegidos sin cambios; sin conexiones reales, merge, despliegue o activación.

## Política vigente ASE-ADM02-POL01 — UI-0

- Padre exacto `732938df607dc508f50f14e564ada86bba4590ca`; entrega documental aislada `feature/ase-adm02-staff-admission-policy`. [Contrato de admisión e identidad staff](identity/ASE-ADM02-POL01-staff-admission-policy.md) registra la respuesta explícita “apruebo” a siete **principios**: documento individual, revisor autorizado, custodia privada, aceptación personal, cambios versionados, invitación individual y activación condicionada.
- Sucede las referencias históricas a siete principios ADM pendientes; **no resuelve enteramente** detalles legales/retención/catálogo extranjero/delegación/implementación. Dos canales convergen en un caso; Identity, gobierno, MFA y audit canónicos preservados. D01–D08 comerciales intactos.
- Fuente y conexión sin cambios: `execute(..., 'register', ...)` crea Advisor ACTIVE y necesita futura guarda de admisión transaccional; snapshot Master sólo cuenta/credencial/reviewVersion, reader producto indisponible. Appointment real sigue bloqueado. Legacy ACTIVE sin revisión no se etiqueta verificado ni se suspende masivamente.
- Sólo inspección y verificación documental; sin QA de producto, SQL, archivos personales, grants/MFA/cuentas/envíos reales o activación. Fases ADM-IMP01..05 propuestas, cada una requiere autorización; intake real cerrado mientras custodia/retención esenciales sigan pendientes.

## Sucesor aislado ASE-ADM02-IMP01 — UI-0

- Padre f58e227da39eca2ae6a7d8b6e6a48615737d3ab5; feature `feature/ase-adm02-staff-case-foundation`. [Caso/perfil canónicos](identity/ASE-ADM02-IMP01-staff-case-foundation.md): preparación Director, versiones personales/procedencia, readers exactos y recibos idempotentes con audit atómico; tres tablas/cuatro triggers, sin registro staff.
- Confirmación sólo cuenta legítimamente vinculada; producto no implementa binding y casos preparados no se pueden confirmar hasta ADM-IMP02. Master sin nueva autoridad; cero review/aprobación/grants/activación. Gate default off y autorización de despliegue/custodia/retención requeridos; principios aprobados no resuelven detalles legales.
- 101 checks HTTP/SQL sintéticos PASS, tres escenarios concurrentes, dos regresiones audit. MySQL/Valkey/TLS nuevos retirados; once worktrees/listeners/pointer preservados. UI original y composición Master/port indisponible intactos. Sin DB/datos reales, merge o deploy.

## Sucesor aislado ASE-ADM02-IMP02 — UI-0

- Padre2330be886bc984d8ce89c5952113d540bdf1f3cb; feature `feature/ase-adm02-staff-invitation-binding`. [Invitación y binding](identity/ASE-ADM02-IMP02-staff-invitation-binding.md): Director autoriza destinatario mediante HMAC por caso; token con expiración/revocación/reemisión, aceptación autenticada/verificada y consumo/vínculo/audit/recibo atómicos. Reutiliza caso/versionado IMP01, sin registro staff.
- Registro/verificación/login canónicos probados con cuenta nueva sintética; cuenta existente conservada. Perfil propio confirmado/corregido por HTTP IMP01. La composición HTTP de invitaciones permanece indisponible por políticas productivas no ratificadas; ninguna excepción positiva por env/fixture.
- 201 checks PASS, siete escenarios concurrentes y dos regresiones audit; límites en contrato/evidencia. Recursos propios dispuestos y doce worktrees/listeners/pointer protegidos. UI R2/COM/ADM, Master fail-closed y COMPAT01 preservados. Sin cuentas/envíos/DB/staff/grants reales, merge, deploy o activación.

## Sucesor aislado ASE-ADM02-IMP03 — UI-0

- Padre27e0da02a4daf15d3c1a32ab5590e2527e835b52; feature `feature/ase-adm02-private-staff-media`. [Media staff privada](identity/ASE-ADM02-IMP03-private-staff-media.md): retrato WebP privado y evidencia JPEG/PNG validada, propósitos/AAD distintos, versiones inmutables, solicitante exacto, audit y reconciliación de huérfanos/ACK/ausencias. Sin writer público ni modificación de casos/invitaciones.
- 312 checks PASS, nueve escenarios concurrentes y tres tests audit; recursos propios dispuestos. Producto sin endpoints de upload/download; composición indisponible por políticas pendientes. PDF no implementado; no reviewer/identidad aprobada, Master readiness, UI, datos reales ni activación. Trece worktrees anteriores y runtimes protegidos; pruebas sólo en infraestructura nueva propia.

## Consolidación funcional ASE-ADM02 — UI-2 autorizada

- Base58f3fcf5, rama `feature/ase-adm02-functional-consolidation`. El Director ratifica P01–P10 y autoriza cerrar el flujo funcional existente; [contrato y evidencia](identity/ASE-ADM02-FUNCTIONAL-CONSOLIDATION.md).
- Solicitud/invitación/vínculo/confirmación canónicos, media privada con documentos y caras versionadas, revisión Director con contraseña/MFA, correcciones, admisión mediante InternalGovernanceService y proyección en equipo. Cero grants automáticos; matriz individual existente. Master conserva gates propios y fuente de revisión indisponible en composición operacional.
- Conexión UI sólo en router de instancia nueva propia; endpoint normal cerrado. No datos reales, delivery externo, DDL persistente, merge, despliegue ni cambio del runtime original. Pendientes únicamente gates de producción y formatos/catálogo expresamente diferidos.

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
| ASE-INT01 R2 | Diseño aprobado312ee388 integrado como lectura canónica en rama/infra aisladas; [contrato y evidencia](identity/ASE-INT01-R2-internal-team-readonly.md). Sin datos personales/documentales/comerciales conectados, writers ni activación. |
| CTX-SYNC01 | [Regla permanente](../AGENTS.md#sincronización-obligatoria-de-contexto--ctx-sync01) y [campos/gate de entrega](MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md#context-sync) obligatorios en cada cambio autorizado; revisión documental manual, sin nueva QA de producto ni activación. |

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
- **Comercial/asesores:** [trazabilidad](contexto/TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md) preserva ventas, captación, Call Center, cupones y Billing separados. ASE-ALIGN01 aprueba combinaciones individuales sin subtipos ni cupo arbitrario de asesores/capacidades; no heredan las dos plazas del operador médico. Director/MASTER delegado conservan gobierno, ADVISOR no recibe autoridad por clase ni autogrants. La antigua ausencia de UI queda sucedida por la matriz ASE-INT01, el backend ASE-INT02A y su conexión individual ASE-INT02B únicamente en features aisladas, con gate apagado por defecto. Grants de revisión/fuentes y revocación son independientes; MFA sujeto/uso se separan del registrador independiente y operaciones sensibles. Runtime real sigue inactivo.

## Evidencia reciente y reconciliación de tests

- ASE-INT01 R2: 76 checks HTTP/SQL, 176 navegador, ocho retorno-login y ocho comparaciones visuales PASS; [evidencia](identity/ASE-INT01-R2-internal-team-readonly.md#qa-aislada-revisión-y-reversibilidad). MySQL/Valkey nuevos propios; ningún dato real leído/escrito, Safari físico NOT_RUN, móvil interino; no amplía evidencia histórica de otras fases.

- Recuperación dirigida ASE-INT01 R2 sobre `230dc03610bd0c235e2c35d8d9d19bfe5b14d9d6`: la auditoría no había localizado salida independiente de los ocho checks de retorno-login. Una **nueva ejecución posterior a la recuperación**, sólo Node VM, obtuvo ocho PASS, exit 0 y stderr vacío; [registro independiente y hashes](evidence/ase-int01/login-return-recovery.json), [alcance del cierre](identity/ASE-INT01-R2-internal-team-readonly.md#recuperación-dirigida-de-evidencia). Cierra únicamente esa brecha de evidencia; resultados originales preservados, sin repetir SQL/navegador ni modificar producto. Diferencias visuales pendientes del Director, Safari físico NOT_RUN, móvil final PENDING y activación FALSE.

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
- Siguiente autorización operacional: despliegue/cohortes/staff/MFA/custodias/proveedores reales por separado; ASE-ALIGN01 V2 no los habilita. ASE-INT01 R2 conecta únicamente la matriz de lectura en preview aislado; writers y operación requieren fase separada. Los mapas no autorizan DDL, C4, owner, firma ni operadores.
- Incertidumbres: remoto/proveedores/runtime físico; variantes SQL/rutas dinámicas; UI parciales, atribución comercial y lineage físico. Decisiones comerciales/seguridad aprobadas no se reabren por README históricos.
- Inicio de nueva tarea: verificar Git/instrucciones, leer este estado, seguir un dominio del router y contrastar decisión/UI/writer antes de proponer cambios.
- Cierre: baseline/post HEAD, alcance, comportamiento, pruebas y entorno, límites, referencias y autorizaciones pendientes; usar la [plantilla existente](MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md).
- Mantenimiento **manual y obligatorio como parte de cada cambio autorizado**: revisar contexto, actualizar sólo IDs/journeys/objetos y documentos impactados y este resumen ante cambios materiales. Registrar los diez campos de entrega; `BLOCKED_INCOMPLETE` impide COMPLETE y `NO_IMPACT` requiere razón precisa. Validar coherencia, evidencia y enlaces, conservando historia en Git/contratos.
- ChatGPT puede consultar los mismos archivos publicados cuando tenga acceso o se adjunten; no hay memoria automática compartida con Codex.
- [Historia y decisiones](README.md#historia-decisiones-y-continuidad) se recuperan bajo demanda; el contenido histórico se conserva con avisos de sucesor donde corresponde.

Cierre ASE-ADM02-FUNCTIONAL-CONSOLIDATION: 41 checks integrados PASS, revisión visual automatizada Chromium/WebKit y CTX-SYNC01 PASS_UPDATED; fuentes/14 worktrees/listeners protegidos. Evidencia final en el contrato enlazado. Activación productiva permanece cerrada.

## Corrección dirigida ASE-ADM02-UXFUNC01

[UXFUNC01](identity/ASE-ADM02-UXFUNC01.md), base66056af7: alta visible y formulario limpio; confirmaciones con nombre autorizado; restricciones de permisos explícitas; baja definitiva de Advisor exclusiva del Director, distinta de suspensión, por writer/auditoría canónicos. Estado terminal REMOVED, sin borrar Identity/historia ni permitir reactivación. Migración sólo preparada/aplicada al ensayo desechable. Master mantiene su autorización independiente. Verificación dirigida PASS: 35 checks, ocho pruebas de seguridad y Chromium/WebKit a 1366/820/390; CTX-SYNC01 PASS_UPDATED. 8091 y consolidación anterior preservados.

## ASE-ADM02-UXFUNC02

Directorio Activos por defecto, estados históricos separados y solicitudes pendientes mediante flujo existente; writer y autoridad sin cambios. 27 checks integrados y 11 smoke checks PASS, sin activación. Véase [UXFUNC02](identity/ASE-ADM02-UXFUNC02.md).

## ASE-ADM02-UXFLOW03

Preparación en asistente aprobado de cuatro pasos, revisión contextual separada y edición personal del integrante existente. Actor de reauth derivado de sesión en servidor. 39 checks funcionales y 11 de presentación PASS; producción inactiva. Véase [UXFLOW03](identity/ASE-ADM02-UXFLOW03.md).

## ASE-ADM02-PANEL04

[Panel contextual](identity/ASE-ADM02-PANEL04.md), base UXFLOW03 f9fe6950: encabezado/rol/logout canónicos, alta debajo de lista, secciones solicitadas y lectura mínima de historial existente. Permisos/admisión/MFA/baja preservados. No se encontró asignación vendedor ni relación asesor–cliente: no conexión financiera ni comisiones inventadas. Sólo ensayo sintético; aceptación visual del Director pendiente.

## ASE-ADM02-PANEL05

[Corrección dirigida](identity/ASE-ADM02-PANEL05.md), base PANEL04 b356b06e: cierre del asistente, lectura de resultado incierto, validación/feedback de invitación sintética, categorías/filtro compacto, búsqueda de nombres autorizados antes de paginar y explicación de permisos suspendidos. Sin nueva autoridad ni activación; 88 checks + 5 adicionales PASS, Chromium/WebKit en tres tamaños. Aceptación visual pendiente según contrato.

## ASE-ADM02-PANEL06

[Corrección focalizada](identity/ASE-ADM02-PANEL06.md), base PANEL05 e4f4fb04: diálogos acotados a viewport y cierre/foco consistente; edición parcial del mismo perfil versionado con género/contacto no verificado, fotografía privada StaffMedia y avatar independiente; login/logout Identity originales conectados al ensayo. Diez capacidades editables existentes y seis restricciones justificadas, sin ampliar autoridad. Migración sólo en infraestructura propia sintética, sin tocar 8091. 816 comprobaciones dirigidas + 14 smoke finales PASS; Chromium/WebKit y límites exactos en el contrato. Aceptación visual del Director pendiente.

## ASE-ADM02-PANEL07

[Corrección funcional](identity/ASE-ADM02-PANEL07.md), base PANEL06 419fe1fc: CSRF canónico fresco por acción, errores contextuales y recuperación de sesión; revisión existente con caso/estado/versiones y MFA preservado; listbox compacto compartido, contención y zoom nativo 125 % Chromium/WebKit; guía de login exclusivamente sintética. Mismos writers/permisos: dos correcciones reservadas a Director/Master y cuatro funciones de perfiles sin consumidor. QA dirigida y límites en contrato; fuente oficial/8091 preservados, sin nueva migración ni producción. Aceptación visual pendiente.

## PANEL08 — revisión dirigida de interacciones

[PANEL08](identity/ASE-ADM02-PANEL08.md), baseline ec8024df: rama aislada con shell modal de encabezado/cuerpo/pie, imagen opcional en los cuatro pasos y retrato privado de preparación limitado al Director creador. Los seis permisos de correcciones/perfiles se documentan individualmente sin ampliar contratos ni inventar consumidores. Verificación dirigida:111 checks funcionales/seguridad y18 zoom nativo PASS,38 recorridos con fondo/pie medidos,22 worktrees/listeners protegidos. Sin activación real ni aceptación visual del Director.

## Sucesor aislado ASE-ADM02-OPER09

- Baseline PANEL08 `f486c42bd5272246a4a6b110d18affba641b29ff`; rama `feature/ase-adm02-oper09-first-functional-advisor`. [OPER09](identity/ASE-ADM02-OPER09.md): la emulación de 900 px excedía la ventana macOS de 870 px; el lanzador usa ahora el viewport nativo y limita la ventana completa al área disponible. Formulario completo y siete secciones conservados.
- Un Advisor ficticio nuevo completa Identity/invitación/confirmación/evidencia/READ/ACCEPT/ADMIT canónicos. Director concede/revoca `media_review_read` desde UI; Advisor independiente consume el inbox y REVIEW existentes. SOURCE sin grant, revocación, suspensión y sesión cerrada denegados por servidor. Cuenta reactivada y permiso explícito conservados para inspección.
- Preview propio loopback/TLS, sin workspace Advisor nuevo, sin runtime original/8091/DB oficiales ni producción. No TTL de sesión/MFA extendido. La edición posterior a admisión conserva la aceptación histórica pero no habilita nuevos envíos de identidad; ese límite no se modifica. Director visual PENDING; seis capacidades no disponibles de PANEL08 siguen sin habilitar.

## Integración aislada ASE-ADVISOR-WORKSPACE01-R2

- Baseline OPER09 `5ca9bfa24b081afdc652d7f65443f3b80979f34e`; feature `feature/ase-advisor-workspace01-r2-top-tabs`. [Contrato R2](identity/ASE-ADVISOR-WORKSPACE01-R2.md): médico seleccionado → pestañas superiores → panel; sustituye el menú lateral propuesto en WORKSPACE01 interrumpido. El Director considera resuelto el modal OPER09 para esta etapa salvo regresión; aceptación visual del workspace nuevo **PENDING**.
- Alcance declarado: Resumen e Imágenes con consumidores Media existentes, seis grants separados, búsqueda/pendientes limitados a recursos accesibles por ID y sesión/autoridad revalidadas. Los siete dominios restantes se mapean con su dependencia exacta; no se convierten en editores staff ni conceden ownership, clínica, cartera o permisos comerciales.
- `circulodigital@msn.com` no existe en la DB owned OPER09 inspeccionada; no se consultaron credenciales ni bases originales. Sesiones Identity sintéticas separadas preservan Director/Advisor; el modelo actual no permite cambiar de clase staff por selector de sesión. No afirmar cumplido el login solicitado.
- **COMPLETE_ISOLATED_SYNTHETIC:** 57 checks (cuatro setup, nueve admisión, 44 workspace), seis facultades Media efectivamente usadas, aislamiento entre dos médicos, seis pendientes útiles y 24 eventos audit Media. Además, seis smoke suplementarios y 13 checks de retorno-login pasan por separado. Chromium nativo y Chromium/WebKit en tres tamaños pasan; 24 worktrees/listeners protegidos permanecen iguales. Dos ventanas nativas entregadas, Advisor con médico/Imágenes/REVIEW visibles; preview hasta 2026-10-19 22:06:13 UTC−06:00. Sin merge oficial, activación productiva ni cambios al runtime original/8091; CTX-SYNC01 PASS_UPDATED y aceptación visual nueva PENDING.
- Regresión dirigida descubierta al consultar eventos Media del integrante: `ActivityProjection` indexaba `operation` nulo y PHP 8.5 contaminaba el JSON Team con un aviso deprecado. Guarda de lectura nula corregida, sin rediseño ni cambio de writer/audit; el segmento workspace repetido pasa, preservando la admisión ya completada. Director/pie modal sin regresión; no se ejecutó la suite histórica.

## Integración aislada ASE-ADVISOR-WORKSPACE02 — completa en entorno sintético

- Baseline R2 `0dbccc946476f7f5e8e4de000e7f186913069a80`; feature `feature/ase-advisor-workspace02-profile-requests`. [Contrato WORKSPACE02](identity/ASE-ADVISOR-WORKSPACE02.md) prioriza CLAIM FREE y solicitud nueva, con Solicitudes/Historial dentro del mismo contexto; búsqueda/inbox se contraen al seleccionar, Resumen/Imágenes permanecen.
- Cuatro grants individuales de solicitud, evidencia y resolución por tipo; no poderes por rol ni reutilización de Media/C4. Intake/evidencia canónicos; ledger de decisión separado de acreditación; perfil nuevo sólo mediante aplicación aprobada, hidden/FREE/no público. Ownership mantiene C4, caso R3 exacto y aceptación/step-up del solicitante; ningún revisor recibe membresía médica ni autoridad de transferencia general.
- Historial proyecta eventos canónicos compartidos con Team y Media, sin notas/documentos/secretos; estado APPROVED y titularidad pendiente/activa se distinguen. Siete objetos aditivos y CHECK acumulativo sólo en DB owned nueva. [Ensayo principal](evidence/ase-advisor-workspace02/qa.json) 51 PASS (incluye 37 de navegador), [ownership](evidence/ase-advisor-workspace02/ownership-browser.json) 23 PASS con ambos solicitantes owner reales y [complemento UI](evidence/ase-advisor-workspace02/ui-supplement.json) 10 PASS. Seguridad final 20 PASS; no se heredan PASS R2. Preview loopback `https://127.0.0.1:55846`, vence 2026-10-19 23:01:05 UTC−06:00; [controles owned](identity/ASE-ADVISOR-WORKSPACE02.md#verificación-y-preview). Activación productiva false, runtime original/8091 protegido y aceptación visual PENDING. [Manifiesto de cierre](evidence/ase-advisor-workspace02/final-verification.json): resultados, fuente final y preservación del entorno original comprobados.

## ASE-ADVISOR-WORKSPACE03 — corrección aislada

Baseline WORKSPACE02 `40b8691f94636909bfb448655939e91387f59fe5`. [Contrato WORKSPACE03](identity/ASE-ADVISOR-WORKSPACE03.md): una tarjeta por ID médico o aplicación sin materializar, once pestañas y contador de tareas accionables separado del total visible. Grant mínimo de resumen limitado a médicos ya accesibles mediante trabajo autorizado; sin cartera, consumidores nuevos ni detalles restringidos. Verificación dirigida: 53 checks principales y 10 complementarios PASS; no activación normal-local/producción ni cambios a 8091.

## STAFF-CONTROL04 — incorporación y soporte aislados

Baseline WORKSPACE03 `8d0dfef72d45253fc9827e22a1f451f6abd3c981`; rama `feature/mxmed-staff-control04-cross-surface`. [Contrato y guía](identity/STAFF-CONTROL04.md) sucede las once pestañas por quince áreas y conecta selección geográfica canónica, candidatos visibles, intenciones versionadas sin acceso anticipado y simulación legítima del solicitante. Admisión y grants siguen siendo decisiones explícitas distintas. Login sin `next` corregido sólo en composición sintética; no importa la cuenta habitual. Índice de seis superficies, dos contextos operativos y cuatro indisponibles en este runtime. QA y límites exactos en el contrato; activación productiva false, 8091 intacto y aceptación visual PENDING.

## STAFF-CONTROL05 — corrección contextual aislada

[Contrato e inventario](identity/STAFF-CONTROL05.md). Estado contextual de incorporación, lectura de progreso independiente y facultades previstas limitadas a once claves operativas. Editar no emite invitaciones; cancelar invalida aceptación sin borrar historia ni afectar staff admitido. Quince áreas distinguen cuatro coberturas operativas (Historial parcial) y once integraciones pendientes; índice conserva dos contextos independientes y cuatro no habilitados.

## STAFF-CONTROL06 — selección y reautenticación contextual

[Contrato](identity/STAFF-CONTROL06.md), baseline STAFF-CONTROL05 `ab6b78349c26b9f80906065dedfdb1210449726f`. Selección cuenta/caso coherente en panel derecho; cuenta propia canónica identificada en contraseña/MFA; typeahead desde selector cerrado/abierto sin alterar Geography. Diálogos limitados a operaciones enfocadas y evidencia protegida; revisión, admisión y grants independientes. Sólo composición owned sintética; 8091/WORKSPACE02/03 sin cambios y aceptación visual PENDING.

## STAFF-CONTROL06-R2 — ajuste visual dirigido

[Contrato R2](identity/STAFF-CONTROL06-R2.md), base `cbc92d964240a595e2804b4134fbe290cf02e226`. Cabecera compacta, lista neutral «Integrantes» con estados por tarjeta y alta junto al filtro. Ayuda explícita de contraseña/MFA y comandos sólo en la composición sintética. Sin cambios de autorización, writers o catálogo; aceptación visual pendiente y producción inactiva.

## STAFF-CONTROL07 — navegación y step-up por acción

[Contrato](identity/STAFF-CONTROL07.md), baseline `01d17ac8726f827841fcc8fc83c24b9f9ea003a6` con encabezado STAFF. Regiones de lista/detalle estables, controles persistentes, carga accesible y descarte de respuestas previas. Contraseña/MFA siguen siendo específicos de cada operación protegida; no hay ticket canónico reutilizable entre READ, decisión y admisión. Composición nueva owned, sin activación ni promoción a 8091.
