# MED-CONSULTORIOS-INIT02 — hidratación de consultorios

## Alcance y decisión

Baseline original `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`. Rama aislada `feature/mxmed-med-consultorios-init02`. UI-2 autorizada por la solicitud del Director: estados, error recuperable y prevención de persistencia durante carga. No promoción a 8091. El disparador histórico de INIT01 continúa **no confirmado**.

Ficha UI-2: pantalla original `p-consultorio`; antes, placeholder aparentemente válido y errores ocultos; ahora, LOADING/READY/EMPTY/ERROR/RETRYING, reintento manual y creación de borrador explícita desde EMPTY. Motivo: separar lectura fallida de ausencia legítima. Alternativa sin cambio conserva la ambigüedad. Sin nuevas APIs, writers ni autoridad. Riesgos: eventos programáticos, callbacks tardíos y campos pendientes; se verifican con fallos aislados y edición real. Rollback: descartar la rama o revertir su commit; original nunca modificado.

## Trazabilidad y frontera

- PRF-03: shell `private-app.php` → `multisede.js` → GET `/api/agenda/index.php/consultorios` → `ConsultoriosController`/`ConsultoriosRepository`.
- PRF-04: mapa conserva coordenadas; carga no inicia geocodificación POST automáticamente. Dirección/búsqueda genuina la habilita en el ámbito actual.
- PRF-05: hidratación sólo aplica presentación; writer de orden bloqueado mientras no haya READY. No cambia primary ni orden persistido.
- Identity/resolución profesional/servidor mantienen autoridad. No se añade un grant ni una autorización de escritura para collaborator. La brecha documentada entre política preparada y dispatch ordinario sigue fuera del alcance.

## Implementación

El shell oculta e inhabilita el editor hasta una respuesta válida. HTTP fallido, timeout de 12 segundos, JSON inválido, `ok` falso, ausencia de lista, ámbito distinto o IDs inválidos/duplicados llevan a ERROR, nunca EMPTY. EMPTY sólo procede de un GET exitoso con lista vacía. READY muestra exactamente los IDs autorizados de los tres slots canónicos existentes; no renumera registros.

El controlador de carga pertenece a la instancia de pantalla. Clave origen + médico; cancelación y generación descartan respuestas antiguas. Navegación repetida no inicia cargas duplicadas ni reemplaza una edición válida. Un cambio de ámbito cancela timers y descarta estados del médico anterior. No se limpian caches globales, sesiones ni almacenamiento del navegador.

Se eliminó la materialización automática de panes y el barrido de guardado de borradores al finalizar GET. No se difieren eventos de hidratación para ejecutarlos como escrituras después. Dirty nace de interacción real, no de eventos sintéticos de montaje. El writer existente sigue disponible para edición genuina después de READY; respuestas de guardado anteriores no reemplazan ediciones más recientes ni otro ámbito. Una huella del payload guardado evita un PUT duplicado al salir de un campo ya guardado. EMPTY permite iniciar explícitamente un borrador, sin escribir hasta una edición significativa.

## Verificación aislada

Runner `scripts/med-consultorios-init02-runtime.py`: datadir/puerto/socket MySQL propios, Valkey propio, PHP/TLS loopback, credenciales sintéticas QA05 reutilizadas mediante su fixture. Sólo lee esquema de tablas del original; no copia registros, triggers, rutinas, vistas ni credenciales originales a la instancia. El principal HTTP sólo tiene grants sobre la base desechable. Cookie distinta y contextos Playwright propios; Safari real no intervenido. Expiración de infraestructura 24 h, sin extender tokens Identity.

`med-consultorios-init02-qa.py`: login Identity canónico de cuentas de prueba, shell completo y controller original. Fallos inyectados exclusivamente en peticiones del contexto sintético. Triggers de conteo de INSERT/UPDATE/DELETE sólo en tabla desechable `consultorios`; hashes completos antes/después de casos de lectura. Peticiones POST/PUT/PATCH/DELETE de la aplicación vigiladas en navegador; telemetría externa del SDK de mapas no se cuenta como writer de consultorios. Las ediciones de prueba se miden por separado: `updated_at` puede cambiar legítimamente, por ello no se exige hash idéntico después de una escritura solicitada.

36 escenarios PASS (18 por motor, Chromium/WebKit). Verificación posterior del ajuste final de mapa: estados visibles LOADING/RETRYING, recuperación con teclado, orden principal preservado y búsqueda explícita con ámbito correcto en ambos motores; [resultados adicionales](../evidence/med-consultorios-init02/states.json), [huellas finales de fuente](../evidence/med-consultorios-init02/manifest.json). Sin búsquedas automáticas ni aviso de fallo por una búsqueda deliberadamente omitida.

Resultados y capturas: [evidencia](../evidence/med-consultorios-init02/results.json). Los fallos simulados prueban recuperación; no reproducen retrospectivamente el disparador de Safari. QA de collaborator acredita lectura y denegación de otro médico en la ruta actual; no acredita enforcement nuevo de la política de escritura preparada QA05, cuyo gate no se activa en este entorno.

Comandos (INSTANCE es el directorio emitido por start):

```sh
python3 scripts/med-consultorios-init02-runtime.py start
python3 scripts/med-consultorios-init02-runtime.py status INSTANCE
python3 scripts/med-consultorios-init02-qa.py INSTANCE
python3 scripts/med-consultorios-init02-states-qa.py INSTANCE
python3 scripts/med-consultorios-init02-runtime.py stop INSTANCE
```

El runner no abre Safari ni sustituye sesiones existentes. `stop` verifica dueño/command/source y solicita cierre sólo de sus hijos. Los secretos sintéticos quedan en el directorio privado de instancia, nunca en evidencia/Git. No merge ni activación de producción.

## CTX-SYNC01

CONTEXT_BASELINE=f92f37f1958af5140a96c04cb52c5c1ce7af8e01
AFFECTED_CAPABILITY_IDS=PRF-03,PRF-04,PRF-05
AFFECTED_JOURNEY_IDS=J04 (carga/edición de sede; reserva y disponibilidad sin cambios)
AFFECTED_DATA_OBJECTS=consultorios (contrato sin cambio); estado local de hidratación/dirty
AFFECTED_CONTEXT_FILES=docs/ESTADO_ACTUAL_MXMED.md;docs/README.md;docs/contexto/INVENTARIO_FUNCIONAL_MXMED.md;docs/contexto/INTERACCIONES_MXMED.md;este contrato
CONTEXT_CHANGES_IMPLEMENTED=loader y frontera de persistencia; evidencia y límites de autoridad
CONTEXT_VERIFICATION=fuente, escenarios y evidencia aislada contrastados; sin activación normal-local
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit de cierre de esta rama
REMAINING_CONTEXT_GAPS=disparador histórico no confirmado; aceptación visual del Director pendiente

## Instancia entregada

- URL: https://127.0.0.1:56121/private-app.php
- Instancia privada: `/tmp/mxmed-init02-36clk43w`. Expira 2026-10-23 09:21:26 -06:00.
- Cuenta sintética: `qa05-owner@example.invalid`; credenciales en `credentials.json` dentro de esa instancia, fuera de Git. Copiar contraseña local: `python3 -c 'import json; print(json.load(open("/tmp/mxmed-init02-36clk43w/credentials.json"))["owner"]["password"])' | pbcopy`.
- Cierre: `python3 scripts/med-consultorios-init02-runtime.py stop /tmp/mxmed-init02-36clk43w`.
- La infraestructura tiene TLS de prueba propio. Ninguna confianza/cookie/sesión de Safari original se modifica.
