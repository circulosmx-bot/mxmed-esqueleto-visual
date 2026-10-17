# UX-ALIGN01 — experiencia original y contratos pendientes

2026-10-16. Worktree `mxmed-step2-vitals-r1`, rama `ux/consultation-step2-vitals-r1`, inicio local/upstream/remoto `e927dcfa86e3bf1fb7cf4e972b4155051d2e51c0`, limpio. Se conserva la aplicación original `https://localhost:8443/private-app.php`, el runtime y las autoridades existentes. No se dispone de los mockups históricos en filesystem y no se inventan rutas para ellos; se aplican las descripciones funcionales del Director con el sistema visual actual.

## A — identidad del encabezado

`PrivateApplicationAccessResolver` añade exclusivamente `profiles_doctors.professional_designation` al SELECT del perfil resuelto desde la membresía canónica y al DTO `professional`. La selección de membresía, `professional.role`, scope, capabilities y sesión conservan su autoridad anterior. No se cambia ningún escritor, rol, grant, propietario o suscripción.

El renderer original `assets/js/core/header-account.js` muestra esa denominación exacta (trim de espacios), con fallback “Perfil profesional”. No usa el código de membresía, especialidad inferida, género, localStorage ni un control de edición como título. Reacciona al evento canónico `mxmed:session-authenticated`; los cambios de `fs-esp` dejan de sobreescribir la identidad. Nombre, geometría, colores, avatar y acciones conservan sus componentes.

El contexto acotado de Operadores se reconoce por `workspace=agenda_operators` o el atributo canónico del shell, y conserva su renderer personal de alias/identidad. La denominación del médico no sustituye al operador ni se transforma en permiso clínico. En lectura normal-local el perfil 1 conserva la denominación almacenada “Endocrinóloga”; no es un literal global de la UI.

La QA distingue el resolver/sesión nativos con datos sintéticos, la presentación de componentes originales en Chromium/WebKit y la observación DOM read-only de una pestaña Safari ya autenticada. Ninguno equivale a aprobación física del Director. Una pestaña abierta antes del cambio conserva sus scripts/DTO antiguos hasta una actualización normal; no se alteran sus cookies, credenciales o sesión para fabricar una prueba.

Resultado A: 42 comprobaciones del resolver nativo en SQLite en memoria y 48 casos de componentes originales en Chromium/WebKit (1440×900, 1366×768, 820×1180). Cubren denominaciones específicas/neutras/vacías/null, otra membresía/perfil, denegaciones de anónimo/revocado/ambiguo/deshabilitado/operador, ausencia de escrituras y owner, refresh canónico y conservación del alias de operador. La prueba de presentación no se declara E2E de toda la aplicación privada. Se conservan fuentes/manifest y capturas sintéticas en `~/Library/Application Support/MXMED/ux-align01/header/`.

La pestaña Safari normal-local ya autenticada se observó sin reload, navegación o acceso a cookies. El DOM antiguo mostraba “collaborator”. Un GET nontouch al `current-session` existente devuelve la denominación nueva del servidor y conserva rol `collaborator`; no se fuerza la carga de scripts nuevos en esa pestaña ni se cambia su sesión. Los tests de sesión privada y GOV-SIM02 también pasan.
