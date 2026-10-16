# C4-UI01 — acreditación profesional en la UI original

2026-10-16. Worktree `mxmed-step2-vitals-r1`, rama `ux/consultation-step2-vitals-r1`, inicio local/upstream/remoto `391bcaa7aeb65f147ba3bc00bf34c5af76175019`, limpio. Implementación de fuente y QA desechable; sin activación, DDL, SMS, documentos, decisiones ni nuevas autoridades reales.

```text
RESULT=SOURCE_UI_INTEGRATION_VERIFIED_OPERATIONAL_ACTIVATION_PENDING
ORIGINAL_UI_INTEGRATION=true
NORMAL_LOCAL_ACTIVATION=false
REAL_DATA_UNCHANGED=true
MIGRATIONS_APPLIED_TO_NORMAL_LOCAL=false
DIRECTOR_SAFARI_QA=NOT_RUN
DIRECTOR_UI_TEST_READINESS=SAFE_PENDING_UI_ONLY_NOT_READY_FOR_REAL_END_TO_END
```

Esta entrega integra la [evidencia C4-EV01](C4-EV01-protected-evidence-foundation.md) y el [emisor C4-ADM02-IMP02](C4-ADM02-IMP02-physician-authority-issuer.md) en las superficies originales. Las descripciones de ausencia de UI en esos documentos son históricas. No convierte la acreditación, la designación de titular ni la autorización clínica en un único permiso.

## Superficies originales

| Ruta en `https://localhost:8443` | Comportamiento |
|---|---|
| `/public/identity/crear-cuenta.php` | Registro canónico existente; después del éxito ofrece la continuación profesional. |
| `/public/identity/verificar-correo.php` | Verificación existente; muestra continuación sólo tras éxito confirmado. |
| `/public/identity/acceso.php?next=%2Fpublic%2Fidentity%2Fperfil-medico.php` | Mismo login Identity. Admite la continuación de cuenta sin exigir una membresía clínica previa. |
| `/public/identity/perfil-medico.php` | Cuenta autenticada y correo verificado; pasos, estado y acciones privadas de su propia solicitud. |
| `/private-app.php` → Información profesional | Una tarjeta enlaza a la acreditación. La aplicación privada conserva su gate clínico. La página de acceso profesional denegado también permite consultar la acreditación. |
| `/internal/media-review/index.php?section=acreditacion` | Sección del workspace interno existente, protegida por `physician_accreditation_review` activo. El permiso de revisión de medios no concede éste ni a la inversa. |
| `/api/identity/physician-onboarding.php` | Adaptador POST JSON protegido compartido; no es un endpoint público de archivos. |

No se crea otro login, aplicación, workspace operativo ni escritor de autoridades. La página profesional reutiliza marca, estilos y componentes Identity; la sección staff se aloja en la ruta interna existente. No se modifican Agenda, Operadores, Recetas ni sus autoridades.

## Recorrido y confirmación de resultados

1. Cuenta y correo por `RegistrationService` / `EmailVerificationService` y sesión Identity existentes.
2. Teléfono: reauth, entrega SMS confirmada por el puerto canónico y OTP de la versión exacta. El cliente no anuncia envío sin aceptación explícita del servidor. No se presenta un adaptador de email como SMS.
3. Claim: consulta de una referencia exacta de perfil existente, fuentes VID01/CRD02 vigentes, sin owner incompatible; presentación de datos y checkbox explícito. Los IDs de fuentes se resuelven en servidor. Nunca se selecciona otro perfil por nombre, email o resultado único.
4. Documentos privados PDF/JPG/PNG, máximo 8 MiB cada uno; clases de identificación y acreditación profesional. El servicio existente cifra y valida. Enviar exige ambos tipos. Tras envío, el formulario no ofrece sustitución de documentos.
5. Un revisor staff independiente con capacidad exacta abre la revisión usando reauth, examina cada archivo de esa solicitud, informa vencimientos reales si existen y confirma la relación cuenta–persona–perfil con MFA canónico. Puede aprobar o rechazar con motivo enumerado. No hay una segunda aprobación rutinaria obligatoria.
6. El médico ve aprobación, vuelve a autenticar y acepta por separado una autorización de un uso ligada a solicitud/revisión/cuenta/perfil/sesión. Nonce de cinco minutos sólo en memoria; no va a URL, DOM, storage o logs del cliente. Vencimiento requiere reemisión.
7. Confirmar registra el workflow existente; completar invoca exclusivamente el emisor canónico. Fallo ambiguo/auditoría no muestra éxito y permite consultar/reintentar el comando idempotente. Sólo la proyección vigente permite mostrar acreditación completada.
8. Designación de titular continúa pendiente como operación independiente. No se escribe owner, membresía, firma, invitación ni grant desde estos formularios.

Se muestran estados preparación, pendiente, aprobada, confirmada, rechazada, vencida, revocada, suspendida y atención requerida. Conflicto/transferencia y perfil nuevo siguen indisponibles con explicación visible; no se simula su resolución. Un cambio de versión devuelve conflicto y obliga a actualizar. El backend conserva la última decisión; el browser nunca deduce aprobación por botones o mensajes locales.

## Seguridad del adaptador

[PhysicianOnboardingHttp](../../modules/identity/Accreditation/PhysicianOnboardingHttp.php) comparte PDO con Identity, evidencia, teléfono, MFA, auditoría y emisor. Reutiliza `EvidenceHttpBoundary` para sesión, CSRF autenticado, origen y transporte HTTPS firmado. Las acciones mutantes no aceptan selección del actor, cuenta beneficiaria, doctor, VID01/CRD02, origen ni revisor desde el body. El scope se resuelve antes de invocar los servicios, que revalidan en sus transacciones propias. No se entrega una autorización persistente al cliente.

La página staff y cada lectura/decisión requieren cuenta/credencial/staff/grant vigentes y reviewer distinto del solicitante. Los archivos sólo se abren desde POST autenticado staff con solicitud/versión/file exactos; la UI del solicitante recibe metadata mínima. Se conservan `no-store, private`, `nosniff`, attachment, CSP sandbox y CORP same-origin; respuesta binaria con longitud explícita. El visor usa blobs temporales y un iframe sandbox para PDF, sin ruta pública ni storage key.

El cliente revoca blobs y limpia datos/formularios al cerrar visor, salir, recibir logout entre pestañas, abandonar la página o expirar/rechazarse la sesión. Un guard de generación impide que una respuesta en vuelo repueble la UI tras logout. La sesión se observa una vez al llegar a su deadline con el endpoint existente que no extiende actividad; no se añade keepalive. Logout sólo se anuncia tras el envelope canónico de éxito. Los formularios quedan deshabilitados por defecto y ante falta de JavaScript/servicio.

`identity-access.js` conserva el allowlist de destinos same-origin y añade únicamente las superficies originales de cuenta/staff. No evita el gate de `private-app.php`; la propia sección staff decide su autorización. Los source pins de deployment incluyen los componentes nuevos, sin crear un receipt de activación.

## Estado normal-local y acciones seguras

Las entradas originales construyen el adaptador **sin composición operativa**. El estado muestra “La acreditación profesional está pendiente de habilitación. Por ahora puedes consultar los pasos.”; los controles mutantes permanecen deshabilitados y el servidor devuelve indisponibilidad. Ese estado no consulta tablas C4 ausentes. No existe flag de producto para seleccionar el ensayo o activar esta composición.

Es seguro consultar los pasos desde una cuenta ya autenticada, seguir los enlaces originales y cerrar sesión. No se solicita al Director iniciar pruebas ahora. **No está listo el recorrido real de extremo a extremo:** faltan aprobación operativa y composición de SMS, custodia/rotación/recuperación de claves, política de retención/acceso/disposición/reconciliación privada, migraciones C4 controladas y staff concreto autorizado. La ausencia de staff/grant real no se subsana elevando al colaborador actual. Perfil nuevo/conflicto/transferencia, owner, firma clínica y Operadores mantienen sus gates separados.

El acceso staff sin sesión redirige al login existente; una cuenta sin capacidad exacta recibe 403. Las rutas se documentan para trazabilidad, no como invitación a cargar documentos o resolver expedientes reales.

## QA ejecutada y límites

El runner existente [gov03b-disposable-qa.py](../../scripts/gov03b-disposable-qa.py) añade `--suite ui`, con [router de ensayo](../../modules/identity/tests/support/C4UiQaRouter.php) guardado por CLI-server, prefijo DB `mxmed_gate4d_preview_c4ui_<10hex>`, usuario correspondiente y normal-local explícitamente deshabilitado. Construye positivamente los mismos servicios/renderer/JS/rutas originales, con entrega SMS sintética sólo a números ficticios; el runtime original no selecciona ese router. SQL principal restringido, Valkey, objetos cifrados, claves, notificaciones y TLS son propios. Se importan cero filas reales. Las migraciones preparadas 01–04 se aplican sólo a su DB. `finally` elimina DB, usuario y recursos secretos propios.

[c4-ui01-browser-qa.py](../../scripts/c4-ui01-browser-qa.py) usa Chromium y WebKit a **1440×900, 1366×768 y 820×1180**. Resultado final: **45 comprobaciones**, seis recorridos completos hasta admisión/vinculación por el emisor nativo y dos rechazos, sin membresías ni owner. Captura 18 pantallas (solicitante, staff, pendiente) y comprueba ausencia de overflow horizontal/errores JS. Manifest SHA-256 al inicio y al final garantiza que se probó el mismo código. La revisión visual incluye escritorio staff y estado pendiente de tablet.

Además del positivo: OTP incorrecto, MIME inválido, referencia/cuenta/archivo incorrectos, CSRF/origen, ausencia de sesión/grant, lectura pública denegada, versión stale, MFA real del ensayo, rechazo, replay/expiry y reemisión de confirmación, fallo nativo de auditoría **BINDING_ACTIVE** con rollback y reintento, proyección vigente, cierre/expiración que retiran el visor, y denegación clínica/Operadores. El cuerpo privado recibido coincide con el PNG sintético y la imagen se decodifica en ambos motores. Se reinicia sólo el bucket rate-limit **de esta DB propia entre casos independientes** para no confundir ocho registros del mismo loopback; no dentro de los asserts de OTP/seguridad y nunca en normal-local.

Regresiones ejecutadas: C4-EV01 **86**; emisor **101 + 2 guards**; gobierno nativo **88**; OP-IMP01 **200** incluyendo cuotas/grants/concurrencia; transporte TLS firmado y tampering; Identity/GOV-SIM02, C3 **47**, sesión privada, roles **28**, HTTP, Agenda, resolver clínico/CLIN-AUTH02A y almacenamiento privado. El worker perdedor de concurrencia de gobierno genera el rechazo esperado; el gate termina PASS. El proxy mantiene su default exacto `localhost:8443`; la QA usa un host/puerto propio explícito y comprueba el transporte, sin cambiar TLS ni exposición normal-local.

**Límite visible:** `PhysicianCredentialAuditPolicyTest.php` histórico sigue fallando por pin de **52** eventos frente a **54** ya presentes en el HEAD inicial. Ni catálogo ni test cambian aquí; no se declara suite global verde. OP02 HTTP persistente no se repite porque mutaría/resetearía su fixture; sus fuentes y checksums permanecen iguales. No se afirma QA física Safari del Director.

Los snapshots read-only antes/después coinciden exactamente para las **128 tablas** de `mxmed`, incluyendo datos/DDL/auditoría/rutinas/triggers; también configuración, payloads de sesiones y checksums QA05/OP02/UI03. Los servidores normales siguen ejecutándose. Comprobaciones anónimas originales: HTTP 8091/root y HTTPS 8443/root 200; login 200; private-app, acreditación y revisión staff 302 al login; current-session 401.

Evidencia privada fuera de Git: `~/Library/Application Support/MXMED/c4-ui01/20261016-review/`, aceptación `ui-accepted/browser-result.json`, screenshots, manifiesto, disposal y `preservation-result.json`. No incluye datos clínicos reales usados como fixtures mutantes. La publicación de fuente no autoriza activar normal-local.
