# PRODUCT-AUDIT — APIs, datos, permisos, scopes, privacidad y retención

## Estado

ACTIVITY_5_AUDIT_DECISIONS_READY_FOR_FAST_FORWARD_INTEGRATION

Contrato: MXMED_APIS_DATA_PERMISSIONS_SCOPES_PRIVACY_RETENTION_AUDIT_V1  
Grupo: PG-08  
Clasificación: UI-0 — STATIC_AND_READ_ONLY_PRODUCT_AUDIT

La auditoría se ejecutó sobre la rama
audit/mxmed-apis-data-permissions-scopes-privacy-retention-v2, derivada de
bbbab40f3c423bb73e0afa362cd51eea0b504e17. No se modificó código, UI,
configuración, SQL, migraciones, AWS, Stripe, 8091 ni datos reales. El
contador oficial permanece 4/22; la Actividad 5 queda READY_FOR_DIRECTOR_REVIEW
la Actividad 5 queda lista para integración fast-forward; la integración aún no
se ejecutó. La Actividad 6 tiene sus gates directorales resueltos, pero no ha
iniciado.

## Baseline y método

Se revisaron los entrypoints PHP bajo api/, los controladores/repositorios de
modules/, schemas y migraciones versionadas, pruebas existentes, llamadas
frontend y los documentos contractuales disponibles. Se descubrieron 12
entrypoints HTTP PHP y 50 declaraciones route/method explícitas; además se
registraron familias dinámicas de Agenda, Pacientes, Perfiles, Catálogo,
Subscriptions y Clinical. No se reutiliza el total histórico de 166 como
verdad actual.

## Resultado transversal

La separación account → membership → entity/profile → role/scope →
capability → authorization está implementada de forma explícita en la
composición de identidad local de Gate 4D y en la autoridad de capacidades.
Fuera de ese preview local, varios entrypoints históricos permanecen
compatibles/transicionales: Agenda y Perfiles aceptan contextos derivados de
headers/query/body cuando no se activa strict mode; Clinical registra actor e
identity bridge, pero no presenta una composición uniforme de sesión,
membership y capability; los stubs verify-password y verify-sms aceptan GET y
valores no vacíos. Estos puntos son confirmed_gap o partial según la evidencia,
no una autorización para corregirlos en esta actividad.

## Hallazgos principales

| ID | Estado | Riesgo | Hallazgo |
|---|---|---|---|
| API-AUD-001 | confirmed_control | R1 | Identity Gate 4D exige preview local explícito, cookie server-side, CSRF, same-origin y fail-closed. Producción diferida. |
| API-AUD-002 | confirmed_gap | R2 | verify-password.php y verify-sms.php aceptan GET y cualquier valor no vacío; además emiten CORS wildcard. |
| API-AUD-003 | confirmed_gap | R2 | Perfiles privados tienen transitional_open por defecto; X-User-Id/X-Doctor-Id pueden suplir sesión cuando strict no está activado. |
| API-AUD-004 | confirmed_gap | R2 | Agenda conserva compat mode, fallback doctor y resolución de actor desde header/query/body; strict es opt-in por entorno. |
| API-AUD-005 | partial | R2 | Clinical valida vínculos doctor-paciente en rutas concretas y exige actor en algunas escrituras, pero no tiene una cadena uniforme account/membership/capability. |
| API-AUD-006 | confirmed_control | R1 | Subscriptions tiene servicios de idempotencia, lock, firma Stripe y guards de ambiente; la cobertura depende de la ruta y de configuración strict. |
| DATA-AUD-001 | confirmed_gap | R2 | Existen schemas duplicados/draft y tablas legacy paralelas; no existe un registro único versionado de fuente de verdad por entidad. |
| DATA-AUD-002 | unresolved | R2 | No se localizó una política canónica de retención, exportación administrativa, anonimización o eliminación irreversible transversal. |
| AUD-AUD-001 | confirmed_gap | R2 | Agenda operator audit existe; pagos emiten logs seguros; no existe audit trail unificado con request/correlation id, actor, scope, before/after y retención para todas las acciones R2/R3. |
| AUD-AUD-002 | confirmed_control | R1 | Logs de Stripe normalizan contexto y filtran valores; no se copiaron payloads ni secretos a esta evidencia. |
| PRIV-AUD-001 | partial | R2 | Contact points y clinical schemas distinguen campos privados/sensibles y soft delete en algunos dominios, pero export/delete/anonymize no están cerrados transversalmente. |
| GOV-AUD-001 | deferred | R3 | Break-glass, soporte asistido, doble aprobación, MFA privilegiada y consola interna permanecen contractuales/futuros, no implementados. |

## Matrices y decisiones

La matriz de planos separa customer/professional, internal operator y
governance/emergency. La matriz de autorización exige membership, ownership,
role, scope, capability y acción; cualquier ausencia debe denegar. Las
familias de row-level access observadas son doctor-profile, doctor-patient,
medical-group, patient-link, subscription-entity y operator-doctor. Los
headers de identidad no se consideran autoridad canónica.

Las decisiones que requieren al director son:

1. aprobar una frontera única de autorización para Agenda, Clinical, Profiles y
   Subscriptions, o autorizar fases separadas con fecha de retiro de
   transitional_open;
2. aprobar el catálogo de fuentes de verdad y reconciliación de tablas draft,
   legacy y canónicas;
3. definir política de retención por dominio sin inventar periodos legales;
4. definir exportación de usuario, exportación administrativa R2/R3,
   eliminación irreversible y anonimización;
5. aprobar el modelo de audit trail unificado y sus controles de integridad;
6. decidir el diseño futuro de support_assisted_session y break-glass.

La revisión directoral aprobó estas decisiones como DEC-012A a DEC-012F. La
aprobación habilita el cierre documental y la integración fast-forward prevista;
no autoriza cambios funcionales en esta rama ni inicia la Actividad 6.

## Cierre directorial DEC-012A–DEC-012F

Todas las decisiones quedaron en estado `APPROVED_BY_DIRECTOR` y se detallan
en [MXMED_DECISIONES_V2_APIS_DATOS_PERMISOS_PRIVACIDAD_RETENCION.md](MXMED_DECISIONES_V2_APIS_DATOS_PERMISOS_PRIVACIDAD_RETENCION.md).

La transición de contador es prevista, no realizada: `4/22` antes de
integración y `5/22` después de una integración fast-forward posterior. La
Actividad 6 queda `GATES_RESOLVED_NOT_STARTED` y no iniciada.

## No repetición

No se ejecutaron writes HTTP, migraciones, SQL contra bases reales, exportación,
eliminación, AWS, Stripe, claim, MFA, consola interna ni cambios funcionales.
Los tests ejecutados fueron existentes y compatibles con auditoría aislada; no
se usaron datos reales, clínicos, secretos, tokens, cookies o contraseñas en la
evidencia.
