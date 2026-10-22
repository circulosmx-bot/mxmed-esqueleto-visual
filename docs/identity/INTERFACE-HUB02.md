# INTERFACE-HUB02 — hub HTML independiente de Chromium

Baseline funcional `3ebd8af4f21dc323451796ba7f1e2acf597b97b4`, más recuperación documental/local `e85d0bc2ceca0b43b146c5a92f89fa26bd6b3d29`. Rama aislada `feature/mxmed-interface-hub02`.

## PRODUCT_POLICY

Recuperación/navegación de desarrollo autorizada. Conservar la sesión médica Safari legítima, los seis contextos y todas las autoridades. No reiniciar Identity/original, recrear cuentas ni modificar grants o registros originales. Safari Profiles es el flujo soportado para separar Director/Asesor; no se afirma que pestañas o ventanas del mismo perfil separen cookies.

## Diagnóstico verificado

Al inspeccionar,57452 pertenecía al proceso hub01 PID92727; el root exacto respondía200, no había otro servicio ni expiración del preview staff54400. Las ventanas Chromium estaban cerradas. **El404 histórico exacto no se reprodujo y no se atribuye una causa no demostrada.** La implementación comparaba `self.path` literalmente con `/`, por lo que variantes con query no se registraban; además entregaba sólo instrucciones fuera de Chromium: los seis controles se inyectaban mediante Playwright. Ambos límites reales se corrigen.

## SOURCE_IMPLEMENTATION / CONNECTIVITY

[Hub02](../../scripts/interface-hub02.py) sustituye exclusivamente al servicio hub01 en el mismo puerto57452. Reutiliza catálogo de seis entradas, verificación de ownership/source/expiry del preview y probe médico de hub01. El propietario anterior se detuvo mediante su marcador, después de verificar que sus ventanas estaban cerradas; no se terminaron procesos originales ni Safari.

Servidor HTTPS de HTML normal, sólo127.0.0.1, independiente de cualquier navegador. Reutiliza la configuración TLS local existente sin copiar/modificar claves ni trust store. `/`, root con query, `/index.html` y `/development/interfaces.php` sirven el mismo índice. No scripts, popups, bindings, shell HTTP, endpoints de comandos ni cookies propias; ignora las cookies recibidas, no las registra/reenvía. POST501, ruta desconocida404, CSP sin scripts/forms/frames, no-store y no-referrer. No se instala en el router normal ni producción.

Tres entradas disponibles: Director/Asesor enlazan al login Identity original del **preview sintético**, con continuación exacta; Médico enlaza directamente a `https://localhost:8443/private-app.php`. El gate staff rechaza correctamente la navegación directa desde otro origen (`same_origin_required`); se preserva usando la entrada pública canónica, no relajando ese gate. No se autentica automáticamente ninguna cuenta desde Safari. Operador/Proveedor sin contexto preparado y publicación deshabilitada siguen sin acciones de apertura.

Safari: conservar el perfil médico actual. Crear perfiles distintos «Director» y «Asesor» en Configuración → Perfiles, abrir el hub dentro del perfil correspondiente y usar su enlace. Dos pestañas del mismo perfil **no** son aislamiento. [Apple documenta cookies/datos separados por perfil](https://support.apple.com/en-ie/105100). El hub no puede elegir un perfil Safari por el usuario. Los enlaces abren en la misma pestaña; Atrás/marcador regresa al hub. **Aislamiento simultáneo en perfiles Safari: PENDING_MANUAL_VERIFICATION**; no se confunde WebKit automatizado con Safari físico.

## VERIFICATION

- Safari real: lectura dirigida únicamente a la pestaña del hub confirma HTML y seis entradas; sin404 ni scripts. Se confirmó por lectura del bootstrap ya cargado una pestaña Safari autenticada para doctor1; no se extrajo la sesión, contraseña, token o datos clínicos, ni se navegó/cerró esa pestaña.
- SafariDriver rechazó la sesión porque «Allow remote automation» está desactivado; no se cambió esa preferencia. La observación de Safari se hizo mediante Apple Events limitados a las URLs de esta tarea, no una prueba automática de perfiles.
- [15 checks dirigidos](../evidence/interface-hub02/checks.json) con [script](../../tools/interface-hub02-qa.py): rutas200, POST denegado, seis entradas sinJS, enlace médico sin popup, login D/A canónico con WebKit separado, cookies staff distintas y ausencia de cookies médicas, reapertura de navegador y móvil sin overflow.
- Primer test directo a staff403: se corrigieron los enlaces hacia login sin tocar autorización. Dos ensayos posteriores agotaron la espera de navegación; el test final espera `load` de la navegación antes del envío. No se amplió TTL ni se deshabilitó MFA/CSRF/rate-limit.
- [Reinicio del servidor](../evidence/interface-hub02/restart.json): mismo57452, root200 y seis entradas; cerrar/reabrir navegador no controla el proceso. Si el puerto pertenece a otro proceso, bind falla; no mata ni elige otro puerto silenciosamente.
- [Preservación](../evidence/interface-hub02/preservation.json): cuatro conjuntos de registros originales coinciden con UX05 y comparación antes/después; listeners90106/93582 y HEAD original iguales. No se declara un hash de toda la base; no escrituras de negocio originales, cambios de grants o credenciales.

## OPERATIONAL_STATUS y guía

Hub: **https://127.0.0.1:57452/**. Se mantiene disponible independientemente de Chromium. Su configuración privada contiene sólo el path de la instancia staff, en `~/Library/Application Support/MXMED/interface-hub02`, modo0700/archivos0600, fuera de Git.

```sh
python3 /Users/circulodigital/Documents/GitHub/mxmed-interface-hub02/scripts/interface-hub02.py open
python3 /Users/circulodigital/Documents/GitHub/mxmed-interface-hub02/scripts/interface-hub02.py status
python3 /Users/circulodigital/Documents/GitHub/mxmed-interface-hub02/scripts/interface-hub02.py stop
```

`open` inicia si hace falta y abre Safari; `stop` detiene sólo el hub. No cierra Safari ni el runtime médico/staff. El puerto fijo evita enlaces obsoletos tras reinicio; no se promete autostart después de reiniciar macOS. Si expira/desaparece staff, el hub conserva Médico y muestra staff no disponible; no conserva enlaces activos a un preview vencido ni inventa privilegios.

Preview staff existente sano, no recreado: `https://127.0.0.1:54400`, expira2026-10-22T23:56:19-06:00. Entradas:
- Director: `/public/identity/acceso.php?next=%2Finternal%2Fmedia-review%2Findex.php%3Fsection%3Dequipo`.
- Asesor: `/public/identity/acceso.php?next=%2Finternal%2Fstaff-workspace%2Findex.php`.
- Médico: origen original localhost8443/private-app.php; sesión existente preservada.

Contraseñas/MFA sintéticos: launcher CLOSURE02 ya documentado, `credentials-director`, `credentials-advisor`, `mfa-director`, `mfa-advisor`. Nada se imprime en el hub. Para conectar una futura instancia recuperada legítimamente, usar `start --owned <directorio-verificado>`; el verificador existente exige source/owner/expiry válidos. No se recrea una instancia ni grants de forma silenciosa.

## CTX-SYNC01

- CONTEXT_BASELINE:3ebd8af4 + e85d0bc2; UX05/CLOSURE02 conservados.
- AFFECTED_CAPABILITY_IDS:ID-02/03,COM-10; sin nuevas facultades.
- AFFECTED_JOURNEY_IDS:J09 navegación; frontera J07 preservada.
- AFFECTED_DATA_OBJECTS:configuración efímera hub; cero writers de negocio.
- AFFECTED_CONTEXT_FILES:estado,router,HUB01 sucesor,este contrato/evidencia.
- CONTEXT_CHANGES_IMPLEMENTED:HTML normal,puerto estable,guía Safari y límites verificados.
- CONTEXT_VERIFICATION:Safari dirigido,WebKit15,reinicio,preservación y diff.
- CONTEXT_SYNC_STATUS:PASS_UPDATED.
- DOCUMENTATION_COMMIT:incluido en commit INTERFACE-HUB02; consultar Git de este archivo.
- REMAINING_CONTEXT_GAPS:aislamiento simultáneo de perfiles Safari y aceptación visual manual pendientes;404 histórico exacto no reproducido.
