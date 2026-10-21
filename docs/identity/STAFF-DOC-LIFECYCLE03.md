# STAFF-DOC-LIFECYCLE03 — consulta y ciclo documental staff

Baseline limpio `e88a6dd387a3e9491cb99e6738cac55ba86c42c1`, [VIS02](STAFF-DOC-VIS02.md). Feature aislada `feature/mxmed-staff-doc-lifecycle03`; worktree `mxmed-staff-doc-lifecycle03`. Sin cambios al padre ni a 8091.

## Ficha UI-2 y alcance autorizado

Solicitud expresa del Director: consulta persistente en DOCUMENTACIÓN, separación actual/historial, actualización propia versionada y coherencia después de admisión. Pantallas: Equipo interno y permisos, visor existente y solicitud personal. Antes, el botón documental remitía a incorporación; el CTA principal elegía ACCEPT/ADMIT tras READ. Ahora la consulta tiene entrada independiente y no cambia de significado al avanzar la admisión. La alternativa de copy solamente no conecta lectura histórica ni envío después del alta. Riesgos: mostrar otro conjunto, trasladar una decisión vieja a una versión nueva o permitir carga administrativa sin autoridad. Se conserva shell, scroll, autenticación por operación y writers. Reversión: detener sólo el nuevo runner y volver a VIS02 intacto, sin downgrade ni borrado de registros. Aceptación visual PENDING.

## Diagnóstico y fuentes

`staff-workflow.js::renderCase` priorizaba ADMIT, ACCEPT, READ. Tras un READ correcto, ACCEPT podía reemplazar la acción que abría imágenes: autenticar una decisión no vuelve a mostrar documentos. `internal-team.js` llevaba desde DOCUMENTACIÓN al caso de incorporación, incluso ya admitido. El READ canónico anterior ya admitía nuevas intenciones sobre el conjunto vigente después de admisión; no era un permiso de lectura de una sola vez. Cada **proof** sí es deliberadamente de un uso.

El lector sólo resolvía `currentSet`; no podía leer conjuntos anteriores ni la última entrega cuando un upload posterior invalidaba su watermark. `submit` rechazaba cualquier caso con completion, aunque StaffMedia ya permitía a su titular cargar versiones. Esas son brechas verificadas en fuente. No se atribuye retrospectivamente un error de sesión/red al incidente del Director sin evidencia de su petición exacta.

## Fuentes y fronteras

- PRODUCT_POLICY: consulta, examen ACK, decisión, admisión y grants siguen separados. Actualizar documentos no implica otro ingreso ni suspensión automática. El alcance actual autoriza sólo la integración sintética; no fija plazos legales ni facultades nuevas para Master/Advisor.
- SOURCE_IMPLEMENTATION: `StaffWorkflow::consultationSet` resuelve caso + set exactos, actor propietario del set, referencia legal y vínculo Identity vigente. READ conserva Director ACTIVE, no autorrevisión, caso no revocado, contraseña/MFA exactos, sesión/credencial, expiración, consumo y audit atómicos. No cambia eligibility de ACK/decisiones: sólo conjunto vigente. Bytes conservan storage key, propósito, cuenta, hash, AEAD y lectura privada existentes.
- CONNECTIVITY: DOCUMENTACIÓN ACTUAL y HISTORIAL DOCUMENTAL → Consultar documentos → password/MFA → mismo endpoint evidence → recibo y respuesta ligados a caso/set/versión → visor existente. El historial no ofrece ACK ni decisión sobre un conjunto antiguo. El detalle del admitido mantiene controles ordinarios y cuenta de grants.
- DATA: mismos conjuntos, manifests, assets inmutables, revisiones, acknowledgements y completions. Sin DDL nuevo. Metadata histórica muestra tipo/lados/versión, fecha real, justificación registrada y decisión/actor/fecha por set; no inventa fechas de archivo ni nombres desde referencias de cuenta.
- OPERATIONAL_STATUS: infraestructura nueva loopback owned, datos example.test y figuras geométricas. Endpoint original cerrado, no migración real, invitaciones externas, producción ni merge.

## Actualización propia y retención

La persona entra a `/staff-admission.php` con su cuenta vinculada. Puede sustituir el tipo existente, corregir una imagen o agregar INE/pasaporte justificado dentro del catálogo existente. Se presentan las caras requeridas, se mantiene ID lógico al sustituir y se conservan los otros documentos del conjunto cuando pertenecen a la misma versión personal. El motivo queda en la justificación canónica, sin una tabla paralela. No carga por impersonación del Director.

`submit` permite el caso ya admitido conservando confirmación personal, versión esperada, vínculo, aceptación y autoridad propia. Una nueva entrega no reutiliza ACK ni ACCEPT anteriores. El historial previo permanece; nueva revisión usa su propia intención. La completion previa y `internal_staff`/grants no son escritos por la actualización. Edición personal post-admisión mantiene su writer específico, no se abre el antiguo correct desde la UI documental.

Retención/custodia permanecen en la metadata cifrada y políticas inyectadas de StaffMedia; sin UPDATE/DELETE de originales, nuevo esquema de llaves ni plazo perpetuo. El historial es consultable únicamente mientras sus bytes existan legalmente y la autorización actual sea válida. Fallos de disponibilidad/integridad fallan cerrados, no inventan documentos ni retiran registros para ocultar un fallo. Legal/custodia/retención/eliminación reales siguen gates de producción no resueltos por QA.

## Verificación

Composición y recorrido principal: 34 checks PASS en [qa.json](../evidence/staff-doc-lifecycle03/qa.json), incluidos los 19 resultados funcionales conservados en [core-observed.json](../evidence/staff-doc-lifecycle03/core-observed.json) y ocho combinaciones Chromium/WebKit a 1366×768, 820×900, 390×844 y 1366×420. [Recorrido](../evidence/staff-doc-lifecycle03/workflow.json) y [auditoría física](../evidence/staff-doc-lifecycle03/audit.json). Safari físico NOT_RUN. Ronda suplementaria: 16 checks PASS de [seguridad y recuperación](../evidence/staff-doc-lifecycle03/security.json): login/logout/login real, password/MFA inválidos, decisiones históricas denegadas, revisión y aceptación de reemplazo activo, no impersonación, retry exacto de submit/media y adición de pasaporte justificado conservando INE. La última entrega contiene INE + pasaporte sintéticos pendientes de nueva revisión; Martha sigue ACTIVE, cero grants y una sola completion. [Verificación final](../evidence/staff-doc-lifecycle03/final-verification.json). No se ejecutaron suites históricas generales.

Incidencias del ensayo conservadas: el preflight rechazó hashes cambiados durante la preparación de esta nueva instancia (se actualizó sólo su manifiesto owned); el primer selector de QA alcanzó un botón oculto del panel aparcado, y luego se agregó espera explícita de montaje para la carga asíncrona. Se añadió una ruta de carga documental sin consultas innecesarias de invitación/intenciones/simulación para el integrante activo. La ronda responsive detectó además cierre nativo del diálogo al repetir Escape tras resize: el visor ahora resuelve el retorno en keydown antes del cierre nativo. Las assertions se reforzaron para exigir diálogo y overview visibles; no se usaron endpoints sin autenticación para sustituir la consulta UI. Los registros/admisiones sintéticos se conservaron entre intentos; no se reseteó el fixture para ocultar fallos. Los 19 resultados funcionales previos se preservan en core-observed.json, transcritos del stdout real del runner; después del cambio exclusivamente de Escape se reejecutan las consultas actual/histórica y ocho tamaños en ambos motores, en vez de repetir el alta y todas sus decisiones. La comparación de hashes protege las fuentes funcionales conservadas.

## Guía de revisión local

URL nueva: `https://127.0.0.1:65339/internal/media-review/index.php?section=equipo`.
Director sintético: `qa_director@example.test`. Selecciona Martha Figueroa Rosado → DOCUMENTACIÓN → Consultar documentos actuales o históricos. Cada consulta exige contraseña y MFA vigentes. El visor conserva el conjunto seleccionado; cerrar no confirma revisión. La persona titular actualiza su propia documentación desde `/staff-admission.php`, con su cuenta independiente.

Expiración de infraestructura: **2026-10-22 10:58:44 -06:00 (America/Mexico_City)**. Máximo seguro existente: 24 horas desde lanzamiento. No se prolongan sesiones Identity, pruebas MFA ni invitaciones.

```sh
cd /Users/circulodigital/Documents/GitHub/mxmed-staff-doc-lifecycle03
python3 scripts/staff-doc-lifecycle03-review.py /tmp/mxmed-ase-adm02imp01-_yny_0h7 credentials-director
python3 scripts/staff-doc-lifecycle03-review.py /tmp/mxmed-ase-adm02imp01-_yny_0h7 mfa-director
python3 scripts/staff-doc-lifecycle03-review.py /tmp/mxmed-ase-adm02imp01-_yny_0h7 open-director
python3 scripts/staff-doc-lifecycle03-review.py /tmp/mxmed-ase-adm02imp01-_yny_0h7 stop
```

Contraseña y código se copian al portapapeles sin imprimir secretos. Cada TOTP debe ser actual y no consumido; si acabas de usar uno, espera el siguiente intervalo de 30 segundos. Si vence Identity, inicia sesión mediante el formulario normal o cierra la ventana y ejecuta `open-director`; enfocar una ventana existente no renueva su sesión. `open-advisor` abre una ventana independiente para Irene; no le concede permisos nuevos. `stop` sólo avisa al runner propietario, responsable de detener y retirar exclusivamente sus recursos desechables. El preview VIS02 anterior no fue alterado.

## CTX-SYNC01

- CONTEXT_BASELINE: VIS02 `e88a6dd387a3e9491cb99e6738cac55ba86c42c1`.
- AFFECTED_CAPABILITY_IDS: ID-02, ID-03, ID-07, COM-10.
- AFFECTED_JOURNEY_IDS: J09.
- AFFECTED_DATA_OBJECTS: document sets, media assets, review intents, identity reviews y acknowledgements existentes; completions/staff/grants preservados.
- AFFECTED_CONTEXT_FILES: estado, router, inventario, interacciones, datos, registro visual, sucesor VIS02 y este contrato.
- CONTEXT_CHANGES_IMPLEMENTED: consulta independiente, histórico exacto y actualización propia post-admisión.
- CONTEXT_VERIFICATION: QA dirigida, revisión del diff completo, evidencia/guía y enlaces locales válidos; fuentes y runtimes protegidos sin cambios.
- CONTEXT_SYNC_STATUS: PASS_UPDATED.
- DOCUMENTATION_COMMIT: mismo commit de implementación/evidencia.
- REMAINING_CONTEXT_GAPS: ninguno dentro del alcance sintético; aceptación visual PENDING y gates productivos intactos.
