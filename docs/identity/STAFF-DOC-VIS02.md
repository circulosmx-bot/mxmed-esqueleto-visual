# STAFF-DOC-VIS02 — examen documental y correcciones

Baseline verificado limpio `15abdce80876f3f123b8d5954dae64ce1a1f40e6`, [STAFF-DOC-VIS01](STAFF-DOC-VIS01.md). Rama nueva `feature/mxmed-staff-doc-vis02`, worktree `mxmed-staff-doc-vis02`.

## Alcance y decisión UI-2

Solicitud expresa del Director STAFF-DOC-VIS02: navegación al ampliar, confirmación explícita del examen, invitación legible, correcciones y cancelación comprensibles. Pantallas existentes Equipo interno y permisos, visor y solicitud personal. Antes: cierre ambiguo, invitación técnica y motivo de corrección poco contextual. Ahora: volver al conjunto, cerrar sin confirmar o confirmar examen, fechas autoritativas e instrucciones visibles para la persona. Sin cambio general de shell, permisos ni activación. La alternativa de sólo cambiar copy no puede producir un acknowledgement explícito persistente. Riesgos: atribuir una revisión al conjunto equivocado, confundir consulta con aceptación, afirmar entrega externa inexistente. Reversión: detener exclusivamente este runner y volver al worktree VIS01 intacto; no downgrade de datos ni cambios a 8091. Aceptación visual del Director PENDING.

## Fuentes, autoridad y separación

- `PRODUCT_POLICY`: examen, aceptación/rechazo, admisión y grants son decisiones distintas. Esta tarea no convierte el examen en aceptación ni agrega una condición de admisión. Se conservan [consolidación](ASE-ADM02-FUNCTIONAL-CONSOLIDATION.md), [invitación](ASE-ADM02-IMP02-staff-invitation-binding.md), [custodia privada](ASE-ADM02-IMP03-private-staff-media.md) y [step-up por acción](STAFF-CONTROL07.md).
- `SOURCE_IMPLEMENTATION`: el READ existente consume su prueba al obtener bytes y audita consulta; no expresa examen terminado. No se puede derivar el acknowledgement de abrir/cerrar. `StaffWorkflow/Service::acknowledge` usa el mismo UnitOfWork, director actual, caso bloqueado, conjunto vigente y mecanismo de intent/challenge/consume. Acción ACK con contraseña/MFA nuevos, sin reciclar proof de READ. Requiere READ reciente del mismo actor, sesión, versión de credencial y conjunto; mantiene la ventana canónica de cinco minutos. Si vence, consultar de nuevo. No exige ampliar cada imagen.
- `CONNECTIVITY`: botón del overview → intención ACK exacta → contraseña/MFA → endpoint `acknowledge` → recibo exacto y recarga. Cerrar/Escape no ejecutan writes. Desde ampliación Escape o Volver a los documentos recuperan todas las caras; cerrar sólo desde overview. Bytes se limpian al cerrar/cambiar/abandonar. Advisor/Master no adquieren autoridad.
- `DATA`: [migración aditiva](../../modules/identity/db/migrations/2026_10_21_01_staff_document_ack.sql), sin modificar migraciones históricas. `auth_staff_document_acknowledgements` inmutable: intent, caso/versión, set, actor, credencial, rol DIRECTOR revalidado, audit y timestamp UTC del servidor. Se reutiliza auditoría StaffAdmission, sin segundo sistema de seguridad. `document_ack_current` exige currentSet válido y coincidencia exacta; reemplazo de bytes, nueva entrega o cambio de perfil invalidan vigencia sin borrar historia. Retry de un ACK ya consumido sólo recupera su recibo; no marca otro conjunto ni reutiliza proof para writes.
- `OPERATIONAL_STATUS`: sólo nueva composición owned sintética loopback. DDL y privilegio SELECT/INSERT del ledger únicamente en su DB desechable. No migración de instalación real, correo externo, personas reales, merge ni promoción.

## Invitación

Fuente `auth_staff_admission_invitations`: issued_at, expires_at, state, consumed_account_id/consumed_at y delivery_state. Aceptación sólo CONSUMED por el destinatario autenticado; tiene precedencia sobre expiración histórica. `expired` se calcula con reloj canónico de servidor, no inventa validez. Fechas en español mexicano y America/Mexico_City; referencia técnica secundaria, ningún token ni digest en la proyección.

`delivered_at` nuevo y nullable registra exclusivamente el instante de ACK del puerto de transporte, en la misma transacción que delivery_state y auditoría. No backfill de fechas históricas desconocidas. El único adapter de este entorno escribe un sobre privado local para el worker sintético: la interfaz dice **preparación local sintética confirmada**, nunca ENVIADA/correo entregado a una persona. No existe un adapter externo autorizado aquí. Preparada/registrada, aceptada, vencida, revocada y reemplazada conservan su significado; no inferir recepción por abrir la invitación ni por existir un archivo.

## Correcciones verificables

`StaffWorkflow/Service::decide` conserva la decisión CORRECTIONS y razón en `auth_staff_identity_reviews`, con set_id, actor/credencial, credencial del solicitante, audit y hora. El conjunto referencia case_id/case_version inmutable. La fila del caso conserva PERSON_CONFIRMED; **Correcciones solicitadas** es una proyección del último resultado vigente, no un estado inventado en auth_staff_admission_cases.

El reader `view` permite al Director autorizado o a la cuenta exacta vinculada, con autoridad personal vigente. `staff-applicant.js` presenta motivo e instrucciones desde `/staff-admission.php`. No se dispara correo ni otra notificación al solicitar correcciones. El mensaje queda disponible al entrar/refrescar la solicitud; no se afirma entrega proactiva.

La persona puede corregir datos mediante el writer `correct`, confirmar su versión y subir/reenviar el conjunto completo mediante `submit`. Para imágenes, reemplaza ambas caras, conserva el ID lógico de documento cuando corresponde y crea nueva versión/conjunto; no edita historial. `currentSet` invalida revisión/ack anteriores por versión o watermark. El Director vuelve al mismo caso, ve la nueva entrega y el antecedente con actor/auditoría; READ/ACK/decisión y admisión conservan autenticación independiente. Cancelar incorporación mantiene el writer, confirmación, revocación efectiva e historia; no equivale a baja de staff activo.

## Verificación y revisión local

53 checks PASS: 43 de composición y flujo en [QA](../evidence/staff-doc-vis02/qa.json), más 4 de [login/logout/login y diálogo responsive](../evidence/staff-doc-vis02/responsive-stepup.json) y 6 [regresiones de etiquetas](../evidence/staff-doc-vis02/invitation-labels.json). Estas últimas verifican la función de presentación real después del ajuste final de precedencia para estados revocados/reemplazados; no simulan autorización ni sustituyen la prueba canónica del navegador. [Navegadores y geometría](../evidence/staff-doc-vis02/workflow.json), [manifiesto final](../evidence/staff-doc-vis02/final-verification.json). Chromium nativo, 1366×768, 390×844 y 1366×420; WebKit 1366×768 y 390×844. Safari físico NOT_RUN. Se conservaron fixtures canónicos entre intentos; no se reconstruyeron cuentas ni se sustituyeron pruebas fallidas por mocks. La repetición final completó todos los checks del flujo. No se heredan los 52 PASS de VIS01 ni se ejecuta QA histórica general. Durante la integración se corrigió el argumento de cookies del nuevo adapter HTTP antes de obtener éxito; los fallos previos no produjeron acknowledgements falsos. La prueba de audit usa el mapper canónico (SHA-256 del UUID en la columna física, UUID original en metadata), no un segundo registro de auditoría.

## Guía local del Director

URL: `https://127.0.0.1:51619/internal/media-review/index.php?section=equipo`.
Cuenta: `qa_director@example.test`. Martha Figueroa Rosado conserva el caso pendiente, la corrección y su reenvío; no se admitió ni se concedieron permisos a Martha. Irene permanece en la sesión Advisor independiente.

Caducidad exacta: **2026-10-22 09:41:15 -06:00, America/Mexico_City**. El launcher admite hasta 24 horas de infraestructura desde el lanzamiento; no extiende Identity, invitaciones ni MFA.

```sh
cd /Users/circulodigital/Documents/GitHub/mxmed-staff-doc-vis02
python3 scripts/staff-doc-vis02-review.py /tmp/mxmed-ase-adm02imp01-8_2vsi3o credentials-director
python3 scripts/staff-doc-vis02-review.py /tmp/mxmed-ase-adm02imp01-8_2vsi3o mfa-director
python3 scripts/staff-doc-vis02-review.py /tmp/mxmed-ase-adm02imp01-8_2vsi3o open
python3 scripts/staff-doc-vis02-review.py /tmp/mxmed-ase-adm02imp01-8_2vsi3o stop
```

Los dos primeros comandos copian al portapapeles, sin imprimir secretos. Cada código debe ser vigente y no consumido; si acabas de usar uno, espera al siguiente intervalo de 30 segundos. `open` abre/enfoca Chromium con contextos Director/Advisor independientes; no renueva un token vencido en una ventana ya abierta. Si venció, inicia sesión mediante Identity con la contraseña recuperada, o cierra esa ventana y ejecuta `open-director`. No es necesario recrear infraestructura mientras esté sana y vigente.

Martha → Revisar incorporación → contraseña y MFA → documentos. Ampliar una cara → Volver a los documentos. Cerrar documentos no registra examen. Confirmar revisión de documentos exige su nueva verificación y muestra Documentos revisados, sin aceptar identidad ni admitir. Solicitar correcciones deja las indicaciones en la solicitud autenticada (`/staff-admission.php`); no envía correo. Consultar nuevamente el caso muestra la nueva entrega y conserva el antecedente.

`stop` sólo señala a este runner propietario; no mata procesos ajenos ni modifica el original. Tras su vencimiento, estos comandos rechazan la instancia; no reviven sesiones expiradas.

## CTX-SYNC01

- CONTEXT_BASELINE: VIS01 `15abdce80876f3f123b8d5954dae64ce1a1f40e6`.
- AFFECTED_CAPABILITY_IDS: ID-02, ID-03, ID-07, COM-10.
- AFFECTED_JOURNEY_IDS: J09.
- AFFECTED_DATA_OBJECTS: review intents, document acknowledgements, admission invitations (delivery timestamp); document sets, identity reviews y profiles conservan writers.
- AFFECTED_CONTEXT_FILES: estado, router, inventario, interacciones, datos/migraciones, registro visual, contrato VIS01 y este contrato.
- CONTEXT_CHANGES_IMPLEMENTED: separación examen/decisión, procedencia de fechas, recorrido de corrección y límites operacionales.
- CONTEXT_VERIFICATION: QA dirigida PASS, diff completo, fuentes y enlaces relativos contrastados; integridad de fuentes fijadas y recursos protegidos PASS.
- CONTEXT_SYNC_STATUS: PASS_UPDATED.
- DOCUMENTATION_COMMIT: mismo commit de implementación y evidencia de esta rama.
- REMAINING_CONTEXT_GAPS: ninguno dentro del alcance; aceptación visual PENDING, Safari físico y activación real no realizados.

## Sucesor documental STAFF-DOC-LIFECYCLE03

[LIFECYCLE03](STAFF-DOC-LIFECYCLE03.md) sucede la navegación hacia documentos y habilita consulta histórica/actualización propia post-admisión en una rama nueva. Este documento conserva la evidencia histórica VIS02; su fixture pendiente puede haber sido admitido posteriormente por el Director durante la revisión.
