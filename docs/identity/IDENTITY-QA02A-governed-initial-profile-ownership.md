# IDENTITY-QA02A — preparación de propiedad inicial de perfil

Estado: preparación fail-closed. No existe una composición ejecutable, endpoint ni comando de promoción.

## Límite implementado

`ProfileMembershipRoleChangeService::assignInitialOwner` representa únicamente la asignación inicial de `owner` sobre una membresía de perfil existente. Acepta `collaborator` o `administrator` como rol esperado, conserva `membership_id`, cuenta, perfil, alcance y estado, y rechaza cualquier destino distinto de `owner`.

El servicio exige una sesión canónica activa del beneficiario, cuenta activa y verificada, versión de credencial vigente, exactamente una membresía médica activa, coincidencia exacta de perfil/alcance/rol/estado/`updated_at` y ausencia de otro propietario activo. La fila del perfil serializa asignaciones concurrentes. La actualización usa comparación optimista y se ejecuta en la misma transacción que el consumo de evidencias y la auditoría.

Tres puertos participan obligatoriamente en esa transacción:

- `ProfileOwnershipApprovalPort`: consume una decisión emitida por una autoridad independiente y ligada a operación, ejecutor, beneficiario, membresía, perfil y roles.
- `ProfileOwnershipStepUpPort`: consume prueba reciente de autenticación, ligada a operación, sesión, versión de credencial, membresía, perfil y rol.
- `ProfileMembershipRoleChangeAuditPort`: debe agregar `ROLE_REVOKED`, `ROLE_ASSIGNED` y `PROFILE_OWNERSHIP_ASSIGNED`, con toda la evidencia no secreta indicada por `ProfileMembershipRoleChangeAuditRecord`.

Los únicos adaptadores runtime entregados por esta preparación rechazan siempre. Los adaptadores sintéticos de la prueba existen exclusivamente dentro de una base desechable y no constituyen autoridad real.

## Dependencias antes de habilitar una transición real

1. Ratificar quién puede aprobar propiedad inicial y cómo se verifica esa autoridad sin datos controlados por el solicitante.
2. Implementar persistencia versionada para decisiones de aprobación y pruebas step-up consumibles. Debe incluir referencias opacas, vínculo exacto con operación y objetivo, expiración, estado y consumo único. No debe guardar contraseñas ni tokens en claro.
3. Implementar el flujo de step-up reutilizando `CredentialAuthenticationService`, sus límites de intentos, la versión de credencial y la seguridad de sesión. La autenticación exitosa debe emitir una prueba corta y específica para esta operación; la sesión existente por sí sola no es prueba fresca.
4. Extender el contrato de metadatos de auditoría canónica para conservar cuenta objetivo, membresía, perfil, roles, aprobación/caso, decisión, aprobador, step-up y operación; después conectar un adaptador basado en `JoinedPdoCanonicalAuditTransactionAdapter`.
5. Crear una composición backend gobernada. La entrada debe provenir de la sesión y de registros confiables; no debe aceptar `approved=true`, autoridad declarada por el navegador ni identificadores como prueba.
6. Obtener autorización separada para aplicar las migraciones a normal-local y para ejecutar la transición real.

## Propuesta de persistencia, todavía no implementada

Una migración futura puede agregar dos tablas `auth_*`:

- una decisión de propiedad inicial, con `approval_reference`, `case_reference`, autoridad aprobadora, ejecutor permitido, cuenta/membresía/perfil/roles exactos, decisión, emisión, expiración, operación única y consumo;
- una prueba step-up, con referencia opaca o digest, cuenta, sesión, versión de credencial, operación/membresía/perfil/rol exactos, verificación, expiración y consumo.

Ambas deben usar InnoDB, claves foráneas a las autoridades canónicas, unicidad de operación/consumo y bloqueo `FOR UPDATE`. Su forward, rollback y recuperación deben ensayarse antes de aplicarse a `mxmed`.

## Sesión y frontend

La membresía no está persistida dentro del token de sesión: `PrivateApplicationAccessResolver` vuelve a leer las membresías. `GET /api/identity/index.php/current-session` reconstruye el contexto privado, y el gestor del navegador lo vuelve a consultar en bootstrap, foco, visibilidad y `pageshow`. Después de una transición futura, una respuesta controlada que fuerce recarga y una validación inmediata son suficientes; no se requiere revocar la sesión si la versión de credencial continúa vigente. Ningún cierre o revocación de sesión forma parte de esta preparación.

## Agenda, Perfil, Media y planes

La matriz `PrivateAgendaRoutePolicy` expresa la diferencia correcta entre roles, pero `api/agenda/index.php` mantiene `canonical_actor_authority=false` y sólo prepara la raíz canónica. Su activación requiere una migración separada de rutas privadas con regresión completa; las rutas públicas deben permanecer fuera de esa matriz.

Perfil valida sesión canónica y alcance exacto, pero varias rutas no distinguen `owner` de `administrator`. Media e información profesional todavía consumen `GallerySessionScope` de PHP. Estas superficies requieren alineación separada antes de afirmar cobertura administrativa completa.

El widget normal-local permanece intacto: exige Identity, fija `doctor_id=1`, guarda el escenario fuera de suscripciones y sólo admite REAL, STANDARD, OPTIMUM y PROFESSIONAL. Un rol de propiedad no concede capacidades comerciales.

## Recuperación

Antes del commit, cualquier fallo revierte actualización, consumo de aprobación, consumo de step-up y auditoría. Después de un commit real, la recuperación deberá ser otra transición gobernada y auditada; nunca una corrección SQL manual ni la creación de otra membresía.
