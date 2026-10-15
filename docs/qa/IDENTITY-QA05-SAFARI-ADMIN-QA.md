# IDENTITY-QA05 — revisión administrativa aislada en Safari

Este entorno ejecuta la aplicación real de México Médico con Identity canónico, sesiones propias y una base exclusivamente sintética. No usa la cuenta de la Dirección, no accede a pacientes reales y no cambia la membresía ni los datos de `mxmed`.

## Inicio y estado

Desde la raíz del repositorio:

```bash
./scripts/qa05-admin-env.py start
./scripts/qa05-admin-env.py status
```

Abrir en Safari:

```text
https://127.0.0.1:8444/private-app.php
```

El origen QA usa `127.0.0.1:8444`; normal-local continúa en `https://localhost:8443/`. QA entrega el cookie host-only `__Host-mxmed_qa05_session`, mientras normal-local conserva `__Host-mxmed_session`; el hostname, el nombre del cookie, el pepper y la instancia Valkey separan ambas sesiones.

Si el entorno todavía no se ha preparado en esa Mac, ejecutar una vez:

```bash
./scripts/qa05-admin-env.py prepare
./scripts/qa05-admin-env.py start
```

`prepare` exige el MySQL local y el material TLS normal-local existentes. Crea únicamente la base protegida `mxmed_gate4d_preview_adminqa_qa05`, un usuario limitado a esa base, almacenamiento privado y fixtures sintéticos.

## Credenciales sintéticas

Las tres cuentas están en un archivo local legible sólo por el propietario:

```text
~/Library/Application Support/MXMED/qa05-admin/Safari credentials.txt
```

El archivo identifica las cuentas `OWNER`, `ADMINISTRATOR` y `COLLABORATOR`. No copiar sus contraseñas a Git, documentación, mensajes ni capturas. Estas credenciales sólo existen en la base QA y no funcionan en normal-local.

Para cambiar de rol, cerrar la sesión desde la aplicación y volver a la página de acceso. No compartir pestañas autenticadas ni reutilizar cookies.

## Perfil y pruebas recomendadas

El perfil es `Dra. Aurora Prueba Segura`, con identificador `qa05-doctor`. Sus cédulas están marcadas explícitamente `QA-SIN-VALIDEZ`. Contiene tres consultorios sintéticos, dos teléfonos de urgencias sintéticos, horarios semanales y cinco citas sin paciente.

Como **Owner**:

1. Abrir **Mi Perfil → Consultorio**.
2. Editar estacionamiento y accesibilidad.
3. Arrastrar las pestañas de consultorios y guardar el orden.
4. Confirmar que la primera pestaña se vuelve la sede principal.
5. Revisar publicación documental y teléfonos compartidos.
6. Recargar y comprobar que los cambios persisten.
7. Abrir **Agenda → Configuración** y revisar horarios y ajustes permitidos.

Como **Administrator**, repetir las operaciones permitidas de consultorio y horario. Los ajustes reservados al owner deben responder `403`.

Como **Collaborator**, revisar las superficies disponibles. Los cambios administrativos de consultorio, orden, contactos protegidos y horario deben responder `403` con el mensaje en español. El contrato de contactos profesionales generales permanece sin decisión definitiva: los endpoints de perfil actualmente autorizan al collaborator con scope exacto; QA05 no modifica esa política.

## Detener, reiniciar, restaurar y destruir

Detener sin borrar datos:

```bash
./scripts/qa05-admin-env.py stop
```

Reiniciar después, incluso tras reiniciar la Mac:

```bash
./scripts/qa05-admin-env.py start
```

Restaurar deliberadamente el fixture sintético original:

```bash
./scripts/qa05-admin-env.py reset
./scripts/qa05-admin-env.py start
```

`reset` conserva las credenciales sintéticas, elimina únicamente la base QA con nombre exacto y vuelve a sembrar el baseline. No acepta `mxmed` como destino.

Eliminar todos los recursos QA requiere confirmación literal:

```bash
./scripts/qa05-admin-env.py destroy --confirm mxmed_gate4d_preview_adminqa_qa05
```

`destroy` detiene sólo los procesos cuyos comandos coinciden con el estado QA, elimina únicamente la base y usuario QA exactos y borra exclusivamente `~/Library/Application Support/MXMED/qa05-admin`. Nunca ejecuta `FLUSHALL`, usa comodines de bases o toca servicios normal-local.

## Límites

- Todos los listeners QA se enlazan a `127.0.0.1`; no existe túnel ni publicación LAN.
- Correo, OTP externo, SMS, WhatsApp, pagos, PAC/CFDI, webhooks y AWS productivo están deshabilitados o carecen de proveedor en esta composición.
- Los medios privados, públicos, temporales y notificaciones están separados de normal-local.
- La aplicación incluye `noindex` y el proxy añade `X-Robots-Tag: noindex, nofollow, noarchive`.
- La prueba automatizada WebKit confirma el contrato del navegador; la aceptación final requiere que la Dirección realice la revisión física en Safari.
