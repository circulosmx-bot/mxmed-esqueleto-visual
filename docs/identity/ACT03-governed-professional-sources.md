# ACT03 — Fuentes profesionales gobernadas antes de C4

ACT03 agrega una composición opcional para establecer las fuentes VID01/CRD02
faltantes de un perfil FREE precargado. No activa el entorno normal-local, no
provisiona fuentes reales y no asigna membresías ni titularidad. La solicitud,
cuenta y perfil originales se conservan; `REQUEST_NEW_PROFILE` no se transforma
en CLAIM cuando la revisión encuentra un perfil existente.

## Autoridad y verificación humana

La operación requiere sesión Identity vigente, cuenta activa y correo verificado,
credencial actual, alcance del piloto firmado y la capacidad específica
`professional_source_provision`. La pantalla original de acreditación también
admite desde **ASE-ALIGN01 V2** un ADVISOR con ese grant exacto sin exigir
`physician_accreditation_review`: sólo recibe la cola/intake mínimo de fuentes
en su alcance. La revisión de enrollment y evidencia C4 sigue exigiendo su grant
independiente `physician_accreditation_review`; esa capacidad por sí sola
no permite provisionar fuentes. No se crean grants, personal interno ni factores
reales. El sujeto no puede revisar sus propias fuentes.

La política individual de asesores y futura matriz gráfica está registrada en
[IW01](../product-completion/iw01-internal-governance-foundation.md).
El [contrato MFA](IDENTITY-GOV03A-governance-runtime.md#mfa-de-staff) distingue
el asesor como sujeto elegible del registrador DIRECTOR/MASTER_ADMIN independiente.
El guard de despliegue acepta el grant rutinario exacto del asesor para los tres
workflows aprobados, conservando firmas, cohortes, fingerprints, schema y principal
SQL restringido. `c4_issuer` mantiene cobertura DIRECTOR/MASTER_ADMIN y los cambios
de autoridad profesional no se amplían al asesor. Los resultados históricos ACT03
de este documento se conservan; ASE-ALIGN01 no activa ese runtime ni sus proveedores.

Un solo revisor ordinario autorizado registra la comprobación. El registrador
independiente de MFA mantiene su función separada y el contrato existente. Cada
COLLECT, AUTHORIZE, RECOVER o REVOKE requiere reautenticación, caso de cinco
minutos y prueba MFA canónica ligada a cuenta, sesión, versión de credencial,
operación, solicitante y perfil exactos. EXECUTE revalida ese caso consumido,
factor vigente, grant y sesión antes de confirmar.

La cédula declarada y el nombre de un perfil anterior sólo permiten encontrar
candidatos. No constituyen verificación. Una carga tampoco la constituye. El
revisor debe abrir ambos documentos privados en la versión exacta del expediente
y registrar nombres estructurados, texto original de la cédula, área,
institución, fuentes, referencias, procedimiento humano, resultado, fecha UTC
real y confirmación de relación con solicitante y perfil. Los procedimientos
admitidos son consulta humana de registro oficial o confirmación directa de la
institución emisora. **No existe ni se afirma una integración automática con un
registro oficial.** Las pruebas usan documentos y comprobaciones explícitamente
sintéticos, nunca validación profesional real.

La decisión y su contexto se cifran con AEAD. El hash de la decisión, la revisión
del intake, la revisión de fuentes y el manifiesto de documentos se congelan.
Las tablas nuevas no son otro registro maestro de identidad o cédulas: conservan
el expediente, autorización, resultado y trazabilidad hacia las fuentes originales.

## Persistencia, checkpoints y recuperación

1. COLLECT confirma el expediente de colección ligado a la solicitud original.
2. El solicitante de esa cuenta carga identificación y documento profesional
   privados con reautenticación y CAS de versión. No puede leer documentos de
   otra cuenta ni elegir un perfil mediante parámetros HTTP.
3. AUTHORIZE confirma durablemente la decisión humana cifrada y un ID de
   operación único. El manifiesto queda congelado antes de ejecutar writers.
4. EXECUTE bloquea origen, perfil, expediente, fuentes y operación; revalida las
   revisiones; llama al writer VID01 original y al writer CRD02 original mediante
   su contrato explícito de transacción administrada. Registra checkpoints
   `VID01_RECORDED`, `CRD02_RECORDED`, auditorías y resultado COMPLETE en **una
   sola transacción InnoDB**.

La ruta pública original `provisionFromTrustedAuthority` de CRD02 sigue siendo
dueña de su transacción y rechazando una transacción externa. El nuevo contrato
`provisionInGovernedTransaction` exige el mismo PDO activo y un validador antes y
después del writer. Reutiliza validaciones, repositorio y auditoría canónica;
no inserta cédulas mediante un writer paralelo. Las transiciones originales
`verify`, `reject`, `inactivate` y `revoke` permanecen sin cambios.

**No hay un commit independiente de VID01 ni uno independiente de CRD02 en
EXECUTE.** Los checkpoints dentro de esa transacción no sobreviven a su rollback.
La autorización humana confirmada antes sí sobrevive. Terminar el worker después
del primer o segundo writer revierte ambos, su journal transaccional y auditorías.
No se borra historia como compensación.

Un deadlock de SQL confirmado como transacción abortada puede reintentarse hasta
cuatro veces, con validación completa y rollback previo. Se reconoce también el
PDO original que VID01 conserva al envolver un error de inserción. No se repiten
errores de red ni un COMMIT incierto por esta vía.

Ante pérdida de respuesta de COMMIT se vuelve a leer el resultado durable:
COMPLETE se devuelve sólo si IDs, FKs y hash de las fuentes actuales coinciden.
El reintento no repite writers ni auditorías, incluso después del handoff y
la finalización C4 de la misma solicitud. Ese replay es una lectura del resultado
confirmado, no una nueva autorización para escribir sobre un perfil administrado. Una operación AUTHORIZED puede
reintentarse mientras su caso siga vigente o reautorizarse con RECOVER por el
mismo revisor y un nuevo caso/reauth/MFA. No se adopta una inserción parcial de
otra operación. Cambio de fuentes o manifiesto produce `NEEDS_INTERVENTION` y
C4 queda cerrado. Conflictos requieren revisión excepcional; no se restituyen
fuentes expiradas, rechazadas, inactivas o revocadas.

Una fuente legítima existente se conserva. Dos fuentes existentes ambiguas,
identidad contradictoria, credencial pendiente o de otra identidad, perfil
administrado y nuevas altas sin perfil no se resuelven automáticamente. La
verificación de una cédula PENDING ya existente sigue siendo un procedimiento
canónico excepcional, fuera de esta ruta de **fuentes faltantes**. No se modifica
su estado para hacer pasar la operación ordinaria.

El journal es append-only; los archivos y la decisión congelada son inmutables.
Los IDs de resultado tienen FKs a VID01 y CRD02 y se contrastan con el resultado
JSON y las filas actuales. La referencia de auditoría usa el UUID canónico; su
ID físico es SHA-256(UUID), conforme a `AuditV1PhysicalMapper`.

Si una carga deja un objeto cifrado sin fila confirmada por fallo/resultado SQL
incierto, se retiene para reconciliación de custodia. No se elimina ni se adopta
silenciosamente. Retención, legal hold y disposición requieren las políticas
firmadas del despliegue; ACT03 no autoriza limpiar almacenamiento real.

## Pantallas originales y continuación

- Personal: `/internal/media-review/index.php?section=acreditacion`.
- Solicitante: `/public/identity/perfil-medico.php?application=…`.

El bloque de fuentes aparece sólo en la composición aprobada. Informa estados,
permite solicitar documentos, registrar comprobación y recuperar/retirar una
autorización. No muestra IDs técnicos al solicitante. Al cambiar de expediente
se descartan previews Blob y campos de la selección anterior; respuestas tardías
no se aplican a otro expediente. La pérdida de sesión bloquea controles.

Tras COMPLETE, la misma solicitud continúa mediante PUB-ENR03. El handoff a C4
conserva las comprobaciones originales de contacto OTP y resolución aprobada.
Los documentos se enlazan mediante `professional_source_file_id`, conservando
el mismo storage key y ciphertext: **no se copia ciphertext y no se inventa una
revisión C4 aprobada**. La lectura valida operación, revisión de fuentes,
solicitante, perfil, handoff y metadatos, y usa el AAD del archivo original.

C4 aún exige envío, acceso efectivo del revisor a los documentos en C4, decisión
humana/MFA, confirmación del solicitante y el issuer C4 original. La verificación
previa de fuentes no satisface automáticamente esos gates. Completar C4 no
asigna owner, no habilita firmas clínicas y no concede autoridad a operadores.
La revocación o cambio de la fuente invalida el uso de la referencia en C4.

## Teléfonos FREE: contratos distintos

Los writers de contactos no se cambiaron. La suscripción FREE no impide guardar
ni consultar un teléfono en la API privada autorizada. Los flags de opt-in y el
plan mínimo conservan el significado original: un número almacenado puede seguir
oculto en DTO público, HTML, JSON-LD, metadatos y API pública. La acreditación,
provisión de fuentes o membresía no publican el número ni cambian esos flags.
Los planes pagados conservan sus entitlements exactos. PUB-COR02 sigue apuntando
exclusivamente al teléfono global **actualmente publicado**; no selecciona un
número oculto por FREE.

La pantalla original contiene controles y un adaptador de edición de teléfonos administrativos privados
(`#mx-admin-phone`, scope `platform_admin`), separada de OTP y seguridad. Ese
contacto es para comunicación con la plataforma, **no es el editor del teléfono
global de consulta**. Hay código JS para un listado/alta de contactos globales
(`mx-dg-public-contact-list`, `mx-dg-contact-create-form`), pero esos nodos no
están presentes en `private-app.php`. Por tanto, el acceso y persistencia por
API y la edición administrativa no prueban que el teléfono global de consulta
oculto se pueda gestionar hoy desde esa pantalla. Esta brecha se reporta como
pendiente de integración de UI, sin publicar teléfonos ni alterar el contrato
de correcciones. ACT03 no crea otro panel para ocultarla.

## Paquete preparado y apagado por defecto

Se conserva el manifiesto aceptado ACT02 de diez etapas. Para ACT03,
`DeploymentPackage::professionalSourceMigrations()` añade al final:

`modules/identity/db/migrations/2026_10_17_07_professional_source_provisioning.sql`.

Esta etapa añade cinco tablas (expediente, archivos, lecturas, operación y journal),
FKs, checks, siete triggers y el enlace nullable de C4. No contiene seeds de
personal, cuentas, grants, membresías, MFA, fuentes ni suscripciones.

`WorkflowFactory` sólo construye el servicio cuando el documento firmado incluye
`professional_source_provisioning`; el guard comprueba esquema, código,
capacidad exacta, cohort y privilegios de SQL. La selección de fuentes requiere
explícitamente `public_enrollment` y `enrollment_review`; una composición
incompleta se rechaza antes de construir los servicios. Sólo esa selección acepta INSERT
sobre las dos tablas maestras; no concede UPDATE/DELETE sobre ellas. La opción
no se añade implícitamente a las composiciones anteriores.

No se preparó una autorización firmada real ni se escribió la instalación fija
normal-local. No se aplicó DDL a `mxmed`. Los servidores originales no se
reiniciaron. Una activación real necesita autorización separada del despliegue,
políticas de custodia/retención/recuperación, cohort y personal real legitimado,
registrador MFA, proveedores aprobados y una migración ensayada sobre respaldo.

## Verificación reproducible

Sólo en recursos nuevos sintéticos, mediante el runner existente:

```sh
python3 scripts/gov03b-disposable-qa.py \
  --connection /ruta/privada/connection.json \
  --evidence /ruta/privada/nueva-evidencia \
  --suite resolution --act03
```

`--native-only` omite navegadores. La conexión de preparación valida servidor
local y baseline; sólo clona **esquema sin filas reales** y crea DB/principal,
cuentas, perfiles, fuentes de ensayo, llaves, MFA, Valkey, storage y TLS propios.
La matriz completa usa además un target nuevo independiente por motor/tamaño,
para respetar los límites de registro por IP sin compartir buckets ni factores.
Cada target conserva los dos orígenes CLAIM/FORMAR PARTE. El proceso destruye
esos recursos tras guardar evidencia privada. No usa ni
reinicia QA05, OP02 o UI03. No habilitar ACT03_QA en procesos normales.

`Act03IntegrationTest` cubre las tres combinaciones de fuentes, evidencia cifrada,
capacidad separada, auto-revisión, cuenta incorrecta, CAS/replay, procedencia,
fecha futura, fuentes/grants cambiados, fallos de writers/auditoría, terminación
de workers, concurrencia, recuperación y revocación. El proxy de ensayo desecha
la respuesta de un COMMIT que el servidor ya confirmó; verifica reconciliación
con un PDO nuevo sin repetir las fuentes.

`Act03FreeContactTest` comprueba persistencia privada, proyección pública FREE,
planes pagados, exclusión de teléfonos ocultos de PUB-COR02 y ausencia de copia
del teléfono OTP a contactos. El recorrido original de navegador verifica
fuentes → PUB-ENR03 → C4 por referencia, sin owner automático, con Chromium y
WebKit en 1440×900, 1366×768 y 820×1180. La prueba de la API privada usa
un owner **sintético preautorizado separado** y conserva los flags de publicación;
no afirma haber ejecutado una nueva aprobación de owner. La inspección DOM
registra la ausencia del editor global y distingue el campo administrativo.
Los solicitantes de fuentes no reciben membresía.

Los resultados finales, limitaciones y huellas de preservación se consignan en
el cierre ACT03. No se presenta la evidencia sintética como validación real ni
se afirma activación normal-local.


Los errores de scripts externos del perfil público se conservan como fallos
separados en `external-script-failures.json`; no se clasifican como éxito de la
aceptación global. El adaptador de navegador exige cero errores propios del
flujo de identidad. Una visita con fallo identificable del loader de Google Maps
puede continuar la comprobación de CLAIM/FORMAR PARTE y C4; el resultado global
permanece PARTIAL, junto con la brecha del editor de contactos. Los tres fallos
históricos de auditoría de cédulas (52 frente a 57 políticas), literal de icono
y AUTH02A_29 se mantienen sin relajar sus tests.


## Cierre verificado

Resultado: **PARTIAL_MXMED_ACT03**. La implementación de fuentes y su continuación
C4 están verificadas y se entregan como código; la aceptación global no se
etiqueta READY ni activación completa.

- ACT03: 127 comprobaciones nativas; contactos FREE: 16.
- PUB-ENR02/03 y C4 previos: 89; guard ACT02: 44, fuente comercial: 36,
  rechazo de esquema parcial: 2.
- Doce recorridos fuente → PUB-ENR03 → C4: CLAIM y FORMAR PARTE en Chromium y
  WebKit, cada uno en 1440×900, 1366×768 y 820×1180. Incluyen replay de resultado
  después de C4, sin nuevas escrituras ni membresía automática.
- VID01 físico original: PASS; CRD02 físico original: 141 comprobaciones PASS
  en un servidor MySQL temporal independiente, retirado al finalizar.
- Correcciones: 264 comprobaciones nativas y 35 de navegador PASS.
- Identity normal-local aislado: 80; contrato de sesión privada: 20;
  GovSim02: 10; operadores: 201 y preparación de panel: 8; Agenda: 14.
- Se conservan los tres fallos históricos citados arriba. Los errores de Maps
  se registraron en los tres recorridos CLAIM de WebKit y no se consideran
  aceptación completa de la página pública.

La API privada permite leer y actualizar el teléfono global FREE conservando
sus flags y sin publicarlo. Su editor no está montado en la pantalla original;
esa brecha sigue abierta. El owner utilizado para este ensayo es un preestado
sintético separado, no una aprobación nueva de ownership ni una consecuencia
de Source/C4.

La evidencia privada y manifiestos están en:
`/Users/circulodigital/Library/Application Support/MXMED/act03/20261017-review/`.
`acceptance-summary.json` vincula los seis resultados de navegador; los fallos
anteriores se conservan en sus iteraciones. `preservation-final.json` confirma
igualdad de esquema, datos y autoridades de mxmed, QA05, OP02, UI03 y de la
configuración/journals inspeccionados. `disposal-final.json` confirma eliminación
de 40 bases propias, sus usuarios SQL, conexiones y llaves de firma de ensayo.
Los cuatro procesos normales originales permanecen activos.

Pendiente: integración del editor privado de teléfono global, incidencias de
Maps y tests históricos. Una ejecución real de este workflow requiere otra
autorización para DDL, políticas, cohort, personal/capacidades, MFA, llaves de
custodia y proveedores. No se solicitó ni se produjo esa activación en ACT03.

## ACT03-CHECK-COMPAT01 — sucesor de compatibilidad SQL

[COMPAT01](ACT03-CHECK-COMPAT01-historical-case-check.md) sucede la ruta del primer CHECK para instalaciones nuevas: 2026_10_18_02 conserva correcciones y 2026_10_18_03 mantiene idéntico el cuerpo de objetos/guards. ACT03 histórico y su resultado PARTIAL se preservan. Instalaciones completas/partiales requieren rutas verificadas distintas; no se habilita operación real.
