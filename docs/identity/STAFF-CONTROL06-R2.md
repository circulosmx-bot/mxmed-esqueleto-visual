# STAFF-CONTROL06-R2 — controles superiores y revisión clara

Baseline limpio `cbc92d964240a595e2804b4134fbe290cf02e226`, [STAFF-CONTROL06](STAFF-CONTROL06.md). Rama `feature/mxmed-staff-control06-r2-director-layout`; worktree `mxmed-staff-control06-r2-director-layout`.

## Ficha de cambio aprobado

Feedback expreso del Director: reducir espacio vertical, retirar «Staff» y la explicación redundante, usar «Integrantes» para una lista de estados mixtos, colocar Agregar integrante junto a Estado del equipo y explicar contraseña/MFA. Ajuste UI-2 con cambios visuales dirigidos autorizados; no rediseño general. La aceptación visual del resultado corresponde al Director y permanece PENDING.

Antes: subtítulo y bloque introductorio repetían contexto; el encabezado de pendientes podía interpretarse como título de toda la lista; alta al final; el revisor no sabía dónde obtener MFA sintético. Después: sólo marca/título y sesión compacta; encabezado neutral, badges individuales, misma acción de alta en fila superior y helper explícito con comandos locales desplegables. Riesgos considerados: espacio móvil, conservación de selección, focus modal y separación de acciones protegidas. Rollback: detener únicamente este preview owned y usar la rama anterior; no migración ni cambio al runtime original.

## Fuente, conexión y límites

- `PRODUCT_POLICY`: no se modifica autoridad, MFA ni la separación entre consultar evidencia, decidir revisión, admitir y conceder permisos.
- `SOURCE_IMPLEMENTATION`: partial y CSS de Team compactan la cabecera y mueven el botón conservando su ID/handler. `internal-team.js` retira el subencabezado permanente de pendientes y mantiene badges y selección. `staff-workflow.js` añade ayuda junto a la acción principal; el diálogo muestra también ayuda local.
- `CONNECTIVITY`: mismos readers, intents, reautenticación, challenge, verify, pruebas de un solo uso y writers. Ningún cambio en PHP de servicios Identity, Media, Geography, API, schema, consumidores Advisor o fuentes de verdad. No impacto en el mapa de datos/migraciones: sólo texto, disposición y controles del ensayo.
- Los comandos se renderizan sólo para Director con `MXMED_STAFF_CONTROL06_R2=1`. Las rutas se resuelven en servidor y se escapan como argumentos shell y HTML. No contienen contraseñas, códigos, semillas o tokens. El launcher valida propiedad, fuente, branch, HEAD, expiración y listener loopback antes de copiar credenciales/MFA sintéticos al portapapeles; nunca importa cookies ni salta autenticación.
- `OPERATIONAL_STATUS=OWNED_SYNTHETIC_ONLY`: composición nueva y desechable; no modifica el preview anterior, producción ni 8091. Los TTL de Identity/MFA no se amplían. Martha conserva revisión pendiente; Irene queda admitida mediante el flujo legítimo con dos grants Media explícitos.

## Verificación

Evidencia dirigida en [qa.json](../evidence/staff-control06-r2/qa.json), [mediciones](../evidence/staff-control06-r2/layout.json) y [manifiesto final](../evidence/staff-control06-r2/final-verification.json). **35 comprobaciones dirigidas PASS** en el recorrido final: seis de preparación aislada y 29 del navegador. Chromium nativo (1366×783), 1366×768 y 390×844. Cabecera de 67,38 px en escritorio y 111,71 px en móvil; filtro y alta comparten la misma coordenada vertical y permanecen visibles. Sin errores JavaScript. No se ejecuta la suite histórica completa ni se heredan sus PASS.

La creación inicial de Irene recorrió preparación, invitación, simulación legítima, READ/ACCEPT/ADMIT con contraseña/MFA y concesión explícita de dos grants. Al ajustar el margen del filtro se reanudó el ensayo conservando estos registros; no se repitió la admisión ni se cuentan dos veces las comprobaciones. Martha conserva revisión pendiente. El recorrido final verifica selección activa/pendiente, teclado estado/ciudad, contraseña incorrecta, MFA inválido, READ válido sin decisión/admisión y retirada de imágenes al cerrar. Los servicios canónicos, API y selector geográfico permanecen idénticos al baseline.

El primer intento detectó el margen heredado del filtro, corregido en CSS. Se corrigieron también dos esperas del ensayo: aceptar el descarte explícito del borrador del asistente y esperar el evento de cierre que retira imágenes. No se modificó el comportamiento de esos flujos. El proxy registró desconexiones de transporte al cerrar páginas; los recorridos finales no muestran errores JS ni acciones incompletas. La comparación de 30 worktrees, listeners protegidos y puntero previo permanece igual. Safari/WebKit no se repitieron en esta tarea; aceptación visual del Director PENDING.

## Revisión local

Preview: https://127.0.0.1:55716/internal/media-review/index.php?section=equipo

Vence **2026-10-21T19:11:04-06:00**, America/Mexico_City. Cuenta `qa_director@example.test`; los datos son ficticios. Desde este worktree:

```sh
python3 scripts/staff-control06-r2-review.py /tmp/mxmed-ase-adm02imp01-f5djbn68 credentials-director
python3 scripts/staff-control06-r2-review.py /tmp/mxmed-ase-adm02imp01-f5djbn68 mfa-director
python3 scripts/staff-control06-r2-review.py /tmp/mxmed-ase-adm02imp01-f5djbn68 open-director
python3 scripts/staff-control06-r2-review.py /tmp/mxmed-ase-adm02imp01-f5djbn68 stop
```

Los dos primeros comandos copian el valor al portapapeles; pegarlo en el campo correspondiente. Cada acción requiere código vigente no consumido; esperar al siguiente intervalo si acaba de usarse. `open-director` abre/enfoca Chromium aislado con login canónico. `stop` señala sólo al runner propietario; no mata listeners ajenos. No reutilizar infraestructura vencida.

## CTX-SYNC01

```ini
CONTEXT_BASELINE=STAFF-CONTROL06 cbc92d964240a595e2804b4134fbe290cf02e226
AFFECTED_CAPABILITY_IDS=ID-02,ID-03,ID-07,COM-10; sin nuevas capacidades
AFFECTED_JOURNEY_IDS=J09; J04 sólo re-verificación
AFFECTED_DATA_OBJECTS=ninguno modificado; mismos casos/cuentas/invitaciones/intenciones
AFFECTED_CONTEXT_FILES=ESTADO_ACTUAL_MXMED; README; MXMED_REGISTRO_CONTRATOS_VISUALES; INVENTARIO_FUNCIONAL_MXMED; INTERACCIONES_MXMED; STAFF-CONTROL06; STAFF-CONTROL06-R2
CONTEXT_CHANGES_IMPLEMENTED=disposición compacta, lista neutral, alta superior, guía MFA sintética
CONTEXT_VERIFICATION=ensayo dirigido, diff completo, hashes, sintaxis, enlaces y preservación
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit aislado; hash en entrega
REMAINING_CONTEXT_GAPS=aceptación visual del Director pendiente; Safari físico no ejecutado
```
