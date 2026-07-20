# MXMed Identity — Gate 4D: integración HTTP con UI aprobada

## Estado

APPROVED_IDENTITY_UI_HTTP_INTEGRATION_GATE_4D_V2_READY_FOR_DIRECTOR_REVIEW

Actividad 4/22. Este cierre conecta, en un preview local explícito y aislado,
los servicios aprobados en Gates 4A–4C con la UI-3 aprobada. El prototipo
checkpoint/mxmed-identity-access-ui3-approved-v2 permanece como autoridad
visual inmutable; no se modificó ni se versionó prototypes/.

## Topología y aislamiento

- Producto oficial 8091: sólo lectura, intacto.
- Candidato HTTPS: https://127.0.0.1:8140/.
- Backend interno: http://127.0.0.1:8141/.
- Preview Valkey local: 127.0.0.1:6384, prefijo
  mxmed:gate4d:preview:session:.
- Base sintética aislada con prefijo mxmed_gate4d_preview_.
- La composición exige MXMED_ENVIRONMENT=local,
  MXMED_PREVIEW_EXPLICIT=1, pepper explícito y base con el prefijo anterior.
- No hay conexión AWS, escritura en 8091, datos reales, correo productivo ni
  secretos versionados.

## Rutas y contrato HTTP

| Ruta de producto | Operación |
|---|---|
| /acceso | login |
| /crear-cuenta | registro pendiente de verificación |
| /verificar-correo | consumo POST de token |
| /recuperar-acceso | solicitud genérica anti-enumeración |
| /restablecer-acceso | reset POST de un solo uso |
| /api/identity/index.php/current-session | lectura mínima de sesión |
| /api/identity/index.php/logout | POST CSRF, idempotente |

Todas las escrituras requieren POST, application/json, same-origin y CSRF.
Las respuestas usan no-store, nosniff, CSP de no-embed y no exponen tokens,
IDs de cuenta, membresías, planes, capacidades ni reason codes internos.

La única cookie es __Host-mxmed_session: Secure, HttpOnly, SameSite=Lax,
Path=/, sin Domain. El logout emite la misma cookie con Max-Age=0.

## Flujos comprobados

El preview ejecutó registro → verificación → login → sesión actual →
recuperación → reset → revocación de sesiones → logout. También se comprobó:

- credenciales inválidas genéricas (INVALID_CREDENTIALS);
- anti-enumeración de recuperación;
- CSRF ausente/inválido;
- Origin externo (403);
- GET sobre escritura (405);
- reset con confirmación de contraseña;
- autorización fail-closed por membresía, perfil, scope, plan y
  transitional_open.

## Preservación visual

La comparación Playwright/Chromium contra el prototipo aprobado cubrió las
cinco rutas a 1440×1100: DOM visible, texto, estilos computados, geometría y
RGBA de screenshot tuvieron diferencia cero. Los únicos cambios son
atributos técnicos invisibles: acción/método de formulario, CSRF, cabeceras,
IDs/ARIA dinámicos y el controlador JS de estados no sensibles.

El smoke móvil es intermedio y no constituye FINAL_MOBILE_APPROVED.

## Evidencia y rollback

La evidencia no versionada vive en
/tmp/mxmed-activity04-gate4d-http-ui-integration-v2/, incluyendo auditorías
de baseline/hash, diffs visuales, flujos HTTP, seguridad, fixtures, rollback y
estado Git. El rollback es dry-run controlado sobre los puertos y la base de
preview; 8091 permaneció en HTTP 200. El servidor de referencia 8142 se
detiene antes del reporte final; 8140 permanece activo.

## Exclusiones

No se implementaron reclamación, MFA, passwordless, social login, soporte
asistido, panel de dispositivos, Stripe, AWS, correo real, nuevas capacidades
ni cambios visuales fuera de la UI-3 aprobada.
