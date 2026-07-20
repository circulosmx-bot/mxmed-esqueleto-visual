# MXMed — Prototipo UI-3 de identidad y acceso V2

## Propósito

Gate 4D quedó bloqueado porque el producto no tenía superficies visibles para registro, login, recuperación ni reset. Esta etapa auxiliar propone esas superficies sin reanudar el Gate 4D funcional.

## Alcance

Se incluyen cinco páginas estáticas: iniciar sesión, crear cuenta, verificar correo, recuperar acceso y establecer una nueva contraseña. El índice es sólo un navegador del prototipo.

La composición propuesta usa una página dedicada, encabezado compacto, logotipo existente, tarjeta central de una columna, CTA principal y enlaces secundarios. No se agregan planes, navegación privada, modal ni sidebar.

## Copy provisional

- Accede a México Médico — Ingresa a tu cuenta para administrar tus perfiles y servicios.
- Crea tu cuenta — Comienza a administrar tu presencia en México Médico.
- Verifica tu correo electrónico — Confirma tu correo para activar tu cuenta de forma segura.
- Recupera tu acceso — Ingresa tu correo y te enviaremos instrucciones si existe una cuenta asociada.
- Establece una nueva contraseña — Crea una contraseña segura para recuperar el acceso.

Campos y acciones: login (Correo electrónico, Contraseña, Iniciar sesión, ¿Olvidaste tu contraseña?, Crear una cuenta); crear cuenta (Correo electrónico, Contraseña, Confirmar contraseña, consentimientos, Crear cuenta); verificar correo (Verificar correo); recuperar acceso (Correo electrónico, Enviar instrucciones); nueva contraseña (Nueva contraseña, Confirmar contraseña, Guardar nueva contraseña, Usa al menos 12 caracteres.). Todas las superficies incluyen el enlace de retorno indicado por el contrato.

Todo el copy queda `PROVISIONAL_PENDING_DIRECTOR_APPROVAL`.

## Estados y accesibilidad

Los estados se activan sólo mediante query string. Se incluyen estados default, loading, error, success y expired según corresponda; hay labels reales, foco visible, `aria-live`, `autocomplete`, tipos de input correctos y controles semánticos. No se declara auditoría WCAG completa.

## Responsive

La propuesta se adapta a 320 px, 360 px, 390 px, 768 px y escritorio sin overflow horizontal. Las capturas de revisión se guardan fuera del repositorio.

## Límites

Backend/API/DB/sesiones/cookies: 0. Reclamación de perfiles: deshabilitada. 8091 permanece intacto. Gate 4D funcional: no reanudado. Contador oficial: 3/22.

## Aprobación pendiente

La propuesta no constituye aprobación visual. El siguiente paso es la revisión del director en el worktree y servidor de prototipo.

## URLs de revisión

- `http://127.0.0.1:8140/prototypes/identity-access-ui3-v2/`
- `http://127.0.0.1:8140/prototypes/identity-access-ui3-v2/login.html`
- `http://127.0.0.1:8140/prototypes/identity-access-ui3-v2/create-account.html`
- `http://127.0.0.1:8140/prototypes/identity-access-ui3-v2/verify-email.html`
- `http://127.0.0.1:8140/prototypes/identity-access-ui3-v2/recover-access.html`
- `http://127.0.0.1:8140/prototypes/identity-access-ui3-v2/reset-password.html`
