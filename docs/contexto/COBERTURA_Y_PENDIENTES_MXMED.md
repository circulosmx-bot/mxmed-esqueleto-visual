# Cobertura, recuperación y pendientes — CTX-MAP02

Ancla de fuente: `1b8dfccb68a7613b2182507a840454631dbf012a`, rama `ux/consultation-step2-vitals-r1`, 2026-10-17. Worktree exclusivo `mxmed-step2-vitals-r1`; checkout hermano preservado. [Estado compacto](../ESTADO_ACTUAL_MXMED.md), [router](../README.md), [funciones](INVENTARIO_FUNCIONAL_MXMED.md), [interacciones](INTERACCIONES_MXMED.md).

## Qué significa la cobertura

El censo parte de los **2.967 paths versionados en el baseline**, con clasificación individual en [INVENTARIO_FUENTES_MXMED.csv](INVENTARIO_FUENTES_MXMED.csv). Esto no afirma que cada línea, variante, autorización o proceso esté probado. Se inspeccionaron los doce módulos propios y las superficies públicas, privadas, staff, provider, workers/CLI e infraestructura. Las 26 etiquetas del CSV incluyen dominios y agrupaciones de soporte; no son 26 productos independientes.

Se documentan **119 capacidades funcionales**: 44 del [inventario general](INVENTARIO_FUNCIONAL_MXMED.md), 59 [clínicas](FUNCIONES_CLINICAS_MXMED.md), 16 [financieras](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md), además de 18 IDs de componentes [AWS](AWS_Y_ENTORNOS_MXMED.md). Una capacidad puede agrupar CRUD con la misma autoridad; no equivale a una ruta ni a un test. Once journeys conectan los dominios, con tres trazas clínicas y dos financieras dedicadas, sin duplicar su definición.

Los cinco ejes son independientes: `PRODUCT_POLICY`, `SOURCE`, `CONNECTIVITY`, `VERIFICATION`, `OPERATION`. Un contrato citado no prueba ratificación de toda la UI. Fuente inspeccionada, QA aislada histórica, aceptación física anterior y actividad actual se distinguen. No se ejecutaron tests de producto en esta tarea. La observación nueva de datos es exclusivamente metadata SQL local; AWS/Stripe/proveedores y disponibilidad runtime no fueron consultados.

| Medida | Denominador / resultado observado | Límite |
|---|---|---|
| Archivos baseline | 2.967/2.967 clasificados; 19 raíces, doce módulos | Censo de paths; no ejecución exhaustiva |
| Etiquetas de inventario | 26/26 explicadas por las familias de abajo | Varias son assets, pruebas o soporte, no dominio de negocio |
| Entradas candidatas | 183: 60 API, 21 UI/legacy, siete UI clínicas, 91 CLI/QA/worker, cuatro IaC/runtime | [CSV](PUNTOS_ENTRADA_MXMED.csv) inspeccionado por tipo; aliases, includes y despacho dinámico no son URLs live verificadas |
| Rutas literales | 49 registros en [RUTAS_DECLARADAS](RUTAS_DECLARADAS_MXMED.csv) | Declaraciones literales/políticas, **no** total de pares método/ruta; gateways ensamblan rutas dinámicamente |
| Capacidades y journeys | 119 capacidades funcionales + 18 componentes AWS; once journeys | Agrupación de contratos y conexiones, no porcentaje de sistema comprendido |
| Integraciones | 18 fronteras/integraciones en mapa externo | Incluye placeholders y recursos públicos; no 18 proveedores activos |
| Tablas/vistas | 204 nombres fuente o físicos, todos con dominio | 195 fuente, 128 físicos, 119 comunes; nueve físicos sin CREATE localizado |
| SQL | 210 artefactos clasificados | 155 DDL/migraciones, 27 rollback, doce históricos, tres propuestas, tres rutinas y diez artefactos de datos/test excluidos |
| Metadata física | 128 tablas, cero vistas, 1.734 columnas, 138 FK, 563 índices, 35 triggers y once rutinas | MySQL local 9.6.0; no lectura de filas, defaults, cuerpos/definers privados o secretos |
| Refs/worktrees | 448 refs, 64 fuera de ancestry; 47 worktrees registrados, 25 paths existentes | Metadata Git; no checkout, merge, prune ni inventario de servicios externos |

## Raíces y exclusiones

Los números son del baseline, no incluyen estos mapas nuevos. Ver [resumen JSON](RESUMEN_INVENTARIO_MXMED.json) para clasificaciones exactas y [refs](REFERENCIAS_GIT_MXMED.csv)/[worktrees](WORKTREES_MXMED.csv) para historia. Ancestry no demuestra que una implementación no haya sido sustituida después.

| Raíz / paths | Tratamiento y mapa responsable |
|---|---|
| root / 27 | Shell original, router/rewrite, booking/cancel, herramientas locales; [SYS/PUB/AG/CAT/AUX](INVENTARIO_FUNCIONAL_MXMED.md). `index.bak*.html` son copias históricas; samples config no son pantallas |
| `api` / 171 | Gateways/adapters/helper clínico y endpoints legacy retirados; mapas general, clínico, financiero y entradas CSV. `_layout`/client includes no cuentan como HTTP independiente |
| `assets` / 196 | UI compartida, Agenda, Consultorio, público, pacientes, clínica, dental, CSS/QR y datos geográficos. Bibliotecas externas/lock y recursos binarios excluidos de lectura detallada |
| `modules` / 1.662 | Doce raíces detalladas abajo; tests inspeccionados como evidencia, no ejecutados |
| `public` / 13 | Identity/onboarding/handoff y flujo público; [ID/PUB](INVENTARIO_FUNCIONAL_MXMED.md), [SIG](FUNCIONES_CLINICAS_MXMED.md) |
| `profiles` / 2 | Directorio/perfil original; PUB-01..06, DTO y decisiones de publicación |
| `internal` / 1 | Shell staff original; Media/C4/fuentes/enrollment/correcciones, no un portal nuevo |
| `provider` / 8 | UI organizaciones y APIs en Agenda; ORG/STUDY/SUBS-10. Fixtures de QA son CLI/test, no producto vivo |
| `partials` / 9 | Layout y panel operadores compartidos, no páginas autónomas |
| `infra` / 210 | Inventario AWS completo, stacks/config/aspects/constructs/runtime/tests; [mapa dedicado](AWS_Y_ENTORNOS_MXMED.md) |
| `scripts` / 88 | Herramientas QA, gobierno, packaging/deploy/backup; clasificación por path. No ejecutadas; algunas mutan o envían y requieren alcance específico |
| `tools` / 4 | Importación/encoding catálogos y mantenimiento; no ejecución autorizada |
| `tests` / 3 | Pruebas/fixture clínicas; sólo evidencia de fuente |
| `database` / 4 | Schema/semillas legacy; schema por DDL, filas seed excluidas de lectura/copia |
| `db_backups` / 1 | Artefacto histórico de datos, sólo path; no dump leído/restaurado |
| `tmp` / 87 | Evidencia/copias/QA históricas, sólo clasificación o referencia contractual dirigida; no resucitar scripts ni copiar payloads |
| `uploads` / 1 | Placeholder de filesystem, no censo de archivos privados reales |
| `docs` / 479 | Mapas/contratos/decisiones/cierres/runbooks: retrieval dirigido, no lectura indiscriminada de toda la colección |
| `.vscode` / 1 | Metadata de editor, sin función de producto |

| Módulo / paths | Dominio, autoridad y estado de cobertura |
|---|---|
| `agenda` / 235 | AG/PRF/ORG: citas, schedules/overrides, booking/OTP, grupos, provider y puente clínico; implementado con gates por ruta |
| `billing` / 33 | BILL: fiscal/archivo/borrador/CSD/PAC seleccionado; adapter de timbrado operativo pendiente |
| `catalog` / 3 | CAT: apoyo geográfico; no sustituye catálogos clínicos/fiscales de otros módulos |
| `clinical` / 446 | PAT/CLIN/STUDY/DENT/DOC: UI/QA/contratos y gateway/helpers; restricciones de emisión profesional explícitas |
| `identity` / 323 | ID/OP/PUB: registro/sesión/roles/gobierno/C4/orígenes/provisioning; composición opcional no activa por presencia de fuente |
| `media` / 165 | MED: archivos/candidatos/aprobación/logos/galería/worker; candidatos privados distintos de proyección pública |
| `patients` / 53 | PAT: maestro, datos administrativos, link médico-paciente y reconciliación; historia pertenece a clínica |
| `platform` / 206 | PLT: audit/retención/autorización/correlación y QA; no portal universal ni purga automática |
| `profiles` / 133 | PRF/PUB: DTO/contactos/profesional/VID/CRD/correcciones; lifecycle y gates separados |
| `signatures` / 15 | SIG: PNG privado, handoff y auditoría; no autoridad de prescribir |
| `subscriptions` / 46 | SUBS: catálogo/aceptación/checkout/pago/activación/entitlements/provider; retorno/recurrencia no completos |
| `_partials` / 4 | Layout/embed reutilizable, explícitamente sin writer autónomo |

Todas las etiquetas del censo quedan en estas familias: `clinical-shared` y `clinical-ui-and-signatures` son helpers/UI clínicos; `media-staff` es UI/API de revisión; `subscriptions-qa`, `clinical-test-tooling`, `tooling`, `maintenance-tools`, `shared-ui-assets`, `shared-ui-partials`, `legacy-api`, `legacy-schema` y `root-shell-and-local-tools` son soportes o historia de las mismas funciones. No se encontró una raíz first-party disponible pendiente de examinar. Opiniones, promociones/IA general, dashboards e inbox visual se clasifican parciales en AUX; aseguradoras/farmacéuticas no se convierten en módulos operativos por aparecer en un modelo.

## Decisiones preservadas y afirmaciones sucedidas

| Referencia anterior | Evidencia/sucesor y clasificación actual |
|---|---|
| [Mapa total](../MAPA_TOTAL_SISTEMA_MXMED.md), [datos conceptual](../db/MAPA_DOMINIOS_DATOS.md), [interacciones clínicas](../MAPA_INTERCONEXIONES_CLINICAS_MXMED.md), [Agenda](../MAPEO_AGENDA_MXMED.md) | Conservados como historia/contratos de su etapa; mapas actuales cruzan fuente/metadata y no reescriben decisiones |
| README clínico/API_V1: scaffolding, sin SQL/endpoints/UI | Gateway actual, maestro patients y módulos clínicos contradicen esa descripción temporal; [reconciliación clínica](FUNCIONES_CLINICAS_MXMED.md#reconciliación-y-siguientes-lecturas-mínimas) |
| [GOV-SIM02](../identity/GOV-SIM02-physician-ownership-session.md): issuer todavía ausente | [C4-ADM02-IMP02](../identity/C4-ADM02-IMP02-physician-authority-issuer.md) implementa issuer; consumidor clínico profesional continúa denegando. No traducirlo a prohibición de producto para médico owner cualificado |
| ACT02: fuente profesional gobernada faltante | [ACT03](../identity/ACT03-governed-professional-sources.md#cierre-verificado) implementa provisioning y QA aislada; no activo en normal-local |
| ACT02/ACT03: tres tests históricos fallidos | ACT04-RECON02 publicado en baseline corrige expectativas/dobles/pin, sin cambiar producto; no son tres fallos vigentes |
| Teléfonos FREE/selectores globales ausentes | Campos administrativos y teléfonos de sede tienen writers funcionales; falta de otros nodos no demuestra necesidad aprobada de nuevo editor. Propósitos y [PRF](INVENTARIO_FUNCIONAL_MXMED.md) se separan |
| [README AWS](../../infra/aws/README.md): Email/Jobs vacíos y ausencia de despliegues | Stacks actuales + cierres C3/SES documentan implementaciones y evidencia física histórica; remoto actual desconocido, no deployment inferido |
| Health readiness descrita como siempre 503 | Runtime actual realiza AUTH/PING TLS a Valkey; no chequea MySQL. [AWS runtime](AWS_Y_ENTORNOS_MXMED.md) |
| Packaging histórico de 653 paths | Manifiesto actual 688 entradas; excluye shell privado/workflows actuales. No asumir imagen funcionalmente equivalente a HEAD |
| Stripe como futuro / rama return como implementación | Confirmación/activación y sandbox histórico existen; rama return contiene readiness BLOCKED, browser confirm/return no completo. [SUBS-06..08](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md) |
| [FISC02B](../billing/FISC02B_ISSUANCE_CORE.md): selección PAC pendiente | Decisión actual: SW primario, Finkok secundario. Integración/operación pendientes no reabren elección. [BILL-05](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md) |

Preservar [catálogo de planes](../MXMED_DECISIONES_V2_CATALOGO_PRECIOS_MODALIDADES.md), [autoridad/permisos](../MXMED_DECISIONES_V2_CAPACIDADES_AUTORIDAD_PERMISOS.md), [GOV-SIM02](../identity/GOV-SIM02-physician-ownership-session.md), [OP-POL02](../identity/OP-POL02_NONMEDICAL_OPERATOR_IMPLEMENTATION_READINESS.md) si se consulta su alcance, y [contrato de prescripción](FUNCIONES_CLINICAS_MXMED.md). Un médico owner, hasta dos operadores no médicos con grants individuales; éstos no prescriben. Médico cualificado firma por su cuenta propia verificada cuando estén implementados/activados sus gates. FREE privado ≠ público; CLAIM/JOIN/reporte tercero son distintos; separación de estructuras internas no exige doble revisión humana.

## Áreas no verificadas y brechas concretas

| Área | Qué falta conocer o conectar; razón y recuperación mínima |
|---|---|
| Runtime normal-local, Safari y filas reales | No son observación autorizada de esta tarea; no se consultaron sesiones, membresías, pacientes ni procesos. Revalidar sólo el flujo autorizado futuro, sin reciclar evidencia histórica como estado live |
| AWS/Stripe/proveedores actuales | Sin operaciones externas autorizadas. Source/IaC y evidencia fechada sí mapeados; remoto `NOT_VERIFIED_REMOTE` / `NOT_VERIFIED` |
| Binding profesional → permiso clínico | Issuer/binding existe; `clinical_professional_authority.php` aún deniega incondicionalmente. Brecha de consumidor, no ausencia C4 ni nueva regla de producto |
| Alta paciente | Diferencia fuente entre POST creación y lecturas/save con resolver canónico; alcance del dispatcher externo no demostrado. `unresolved`, sin probes de acceso ni conclusión de explotación |
| Stripe canónico | Writes privados bloqueados por runtime canónico y configuración `session_scope` incompatible; confirm/return final y snapshot legal también pendientes. No reabrir piloto cerrado |
| Renewal/refund/dispute/Connect | Renewal parcial; no soporte completo localizado para refund/dispute/Connect/recurrencia. No inferir por conceptos comerciales o mockup |
| Deployment actual | Packaging omite entradas actuales; `DB_*` vs `MXMED_DB_*` y OIDC no instanciado son brechas de fuente, no inventario AWS. No hacer build/deploy para probar el mapa |
| 36 gaps léxicos de writer revisados | Búsqueda dirigida recuperó SQL dinámico/constantes, triggers, catálogos y VIEW; cinco candidatos fixture adicionales corregidos. Cada caso tiene adjudicación y notas en CSV/resumen. Restan writers no localizados de schemas históricos/consents/SEO, provisioning de grants/códigos externos y copias físicas; adapter de identidad paciente explícitamente rechazante, no writer implementado |
| Nueve objetos físicos sin CREATE | Metadata existente y nombres exactos conservados; lineage incompleto/dinámico o copias históricas. No borrar ni restaurar para investigarlo |
| 76 objetos sólo fuente | No observados homónimos localmente; comparación literal no demuestra cada ALTER ni receipt. Aplicación de migraciones sólo por paquete/guard/versiones autorizados |
| Dispatch dinámico/aliases | 49 registros literales no expanden todas las ramas. Los mapas nombran gateway, acción/patrón y autoridad; equivalencia de todas las URLs y runtime es `unresolved` |
| UI parciales y entidades futuras | Dashboard, opiniones/promociones/IA e inbox general no tienen cadena completa acreditada; provider sí tiene servicios propios, sin equivalencia a todo tipo de entidad |
| Teléfono físico/QR/Maps | Constructor Rx y dependencia externa mapeados; QR realmente escaneado, ruta de red/TLS móvil y fallo privado Maps no revalidados. No alterar exposición/configuración para cartografiar |

Estas incertidumbres son resultados explícitos del mapa. No quedan secciones de dominio disponibles sin poblar; la falta de evidencia física/externa y la resolución exhaustiva de SQL/rutas dinámicas tienen límites expresos. Los candidatos originales por regex se conservan como apoyo de búsqueda; 41 filas tienen revisión dirigida. Esto no constituye auditoría de cada ruta dinámica ni autoridad operacional completa.

## Preguntas de recuperación

Cada respuesta dirige a un mapa y símbolos concretos; no requiere volver a abrir todo el repositorio.

1. **¿Dónde persiste cada teléfono y quién lo edita?** [PRF-01/03 y PUB-03](INVENTARIO_FUNCIONAL_MXMED.md): `persistAdministrativeContact` → DCP `platform_admin` privado; `collectPanePayload` → `ConsultoriosRepository` → teléfonos/WhatsApp de sede, owner/administrator; emergency compartido → `PhysicianEmergencyContactsService`; global publicado → DCP/adapter corrección; OTP → `PhoneVerificationService`, no copia de consulta. [Datos](DATOS_Y_MIGRACIONES_MXMED.md#propiedad-escritores-y-consumidores).
2. **¿Cómo llega FREE al DTO público?** [PUB-03](INVENTARIO_FUNCIONAL_MXMED.md#pub-03--visibilidad-de-contacto-free-y-información-de-consulta), [SUBS-01](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md#subs-01--consultar-plan-capacidades-y-publicación): `PublicCommercialPlanSource` → `PublicProfilePlanCapabilities` → `PublicProfileController`/Repository; flags + plan, privado no publicado.
3. **¿Cómo afecta ubicación de sede al público?** [PRF-04/J04](INTERACCIONES_MXMED.md#j04-consultorio-y-reserva): multisede confirmación → PUT consultorio → coords/source/time persistidos → helper/DTO público; mover mapa sin guardar no cambia origen público.
4. **¿Quién confirma Stripe y activa?** [SUBS-07/08 y J08](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md#j08--plan--pago--evento--activación--entitlements): `ProcessStripeSubscriptionWebhookService` confirma pago; `ActivateSubscriptionAfterPaymentService` activa explícitamente en otra transacción. Retorno/browser no concede capacidades.
5. **¿Qué AWS existe y qué se desplegó?** [Stacks, entorno y C3/SES](AWS_Y_ENTORNOS_MXMED.md): 22 archivos stacks, cinco stages; evidencia offline y física histórica fechada separada de teardown y remoto desconocido. Consultar tabla por stack antes de CLI.
6. **¿Qué permite firma clínica al médico?** [Gates clínicos](FUNCIONES_CLINICAS_MXMED.md), [F06](INTERACCIONES_CLINICAS_MXMED.md#f06): sesión + scope/capability + vínculo profesional/credencial real requeridos; issuer implementado, consumidor aún deniega. Owner o PNG no basta, operador no hereda.
7. **¿Qué puede hacer un operador hoy?** [OP-01..03](INVENTARIO_FUNCIONAL_MXMED.md), [J07](INTERACCIONES_MXMED.md#j07-owner-operador-y-agenda-original): cinco grants exactos, revisión vigente y servicio habilitado; dos plazas, owner gestiona, sin clínica. Historial legacy no demuestra membership actual; normal-local activación no acreditada.
8. **¿Cómo llega CLAIM a C4?** [J01](INTERACCIONES_MXMED.md#j01-claim-existente): origen server → intake → review/MFA/decisión → `EnrollmentResolutionAuthority` → handoff/evidencia → issuer; ownership separado.
9. **¿Qué pasa con JOIN sin coincidencia?** [J02](INTERACCIONES_MXMED.md#j02-formar-parte): conserva solicitud y target original null; procedimiento nuevo perfil pendiente, sin creación automática ni fuentes inventadas.
10. **¿Qué modifica una corrección aceptada?** [J03](INTERACCIONES_MXMED.md#j03-correcciones): aceptación no aplica; `ApplicationService` + `PublicPhoneCorrectionAdapter` sólo teléfono global ya publicado elegible, con CAS/journal/MFA, no sedes/WhatsApp/FREE oculto/identidad.
11. **¿Quién posee identidad paciente e historia?** [PAT y CLIN](FUNCIONES_CLINICAS_MXMED.md#pacientes-e-identidad-administrativa), [F05](INTERACCIONES_CLINICAS_MXMED.md#f05): PatientsRepository/`patients_*` maestro; gateway/helpers clínicos encounter/document/longitudinal; link médico-paciente limita ambos.
12. **¿Qué migraciones corresponden a workflows inactivos?** [Cadena de once etapas](DATOS_Y_MIGRACIONES_MXMED.md#cadena-preparada-y-diferencias-físicas), `DeploymentPackage::migrations/professionalSourceMigrations`, [comparador](COMPARACION_MIGRACIONES_MXMED.csv): Identity 01–06, correcciones 01/02, FREE, metadata reader y fuentes ACT03. No confundir objetos OP previos con aprobación/activación actual.

## Progreso conservado y regla de mantenimiento

La [validación documental](VALIDACION_CTX_MAP02.md) registra cotejos de paths, enlaces, objetos/IDs, formatos y privacidad; no agrega evidencia de tests de producto.

Etapas de esta tarea: A censo fuente/ref/worktrees; B capacidades/datos/metadata; C once recorridos; D AWS/Stripe/18 integraciones; E reconciliación histórica; F router/estado/validación documental. Los hallazgos están en archivos modulares, no en una transcripción ni agentes automáticos de fondo. Baseline y denominadores en JSON/CSV permiten continuar sin rehacer el censo.

Tras una **operación futura autorizada**, actualizar los IDs/journeys/objetos afectados, su evidencia y los cinco ejes; sustituir el resumen compacto y conservar historia en Git/contratos. Recontar sólo si cambia el universo fuente o físico pertinente; no regenerar todo ni ejecutar pruebas/migraciones/proveedores automáticamente. ChatGPT/Codex empiezan por `AGENTS.md` → estado → router → mapa y símbolos del dominio, con acceso a los archivos publicados; no se promete memoria automática.

Próxima acción de producto/piloto: **NONE_PENDING_DIRECTOR_REVIEW**. Corresponde al Director priorizar una brecha y autorizar su alcance; despliegue, DDL, SMS/llaves/custodias/cohortes/staff/MFA, gobierno/ownership o clínica conservan requisitos propios. Finalizar estos mapas no concede autorización operacional.

<a id="ctx-map03"></a>

## CTX-MAP03 — Recuperación por caso de negocio

Revisión dirigida, 2026-10-17, sobre `0bea8195ce75c309a54cba35acb4605ba290ee00`. Se conservan censo, IDs funcionales e inventarios CTX-MAP02; no se regeneraron ni se consultó SQL. Los dos suplementos son [Agenda por canal/actor](AGENDA_CANALES_Y_PERMISOS_MXMED.md) y [captación, asesores, descuentos y workspace](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md); la [extensión fiscal](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md#estado-por-caso-y-límites-de-evidencia-ctx-map03) mantiene BILL-01..06.

Se añaden 35 casos de negocio estables: diez AGENDA, quince UC-BILL y diez COM; no son 35 funcionalidades nuevas implementadas ni un recuento del repositorio. Las doce filas de acciones agrupadas de Agenda se comparan entre cinco canales; readiness del workspace conserva trece piezas con sus prerrequisitos.

Las clases `IMPLEMENTED_AND_WIRED`, `IMPLEMENTED_BUT_DISCONNECTED`, `PREPARED_BUT_INACTIVE`, `POLICY_ONLY`, `UI_ONLY`, `HISTORICAL`, `NOT_FOUND_IN_INSPECTED_SCOPE` y `UNVERIFIED` describen conexiones concretas. No sustituyen los cinco ejes política/fuente/conexión/verificación/operación. Fuente conectada no implica actividad actual; una búsqueda negativa indica su alcance y términos alternativos, sin demostrar ausencia universal.

### Preguntas adicionales y ejemplos hipotéticos

Son nueve comprobaciones de recuperación documental, **sin fixtures insertadas ni tests funcionales ejecutados**. Cada respuesta identifica evidencia existente y el primer límite material. Para método/ruta, writer, campos, permisos y auditoría abrir el caso enlazado, no todos los mapas.

| ID / pregunta del Director | Evidencia y respuesta recuperable | Primer límite no demostrado o conexión faltante |
|---|---|---|
| BR-01. Un paciente reserva desde el perfil público | [AGENDA-PUB01](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-pub01): `profiles/doctor.php` selecciona médico/sede/slot, separa solicitante y paciente, y conecta reserve → OTP request → confirm. Páginas legacy son otras entradas | Disponibilidad/entitlement/OTP/delivery y éxito real no se observaron; conexión de fuente no acredita reserva física. Flujo no es una transacción única |
| BR-02. El médico crea una cita | [AGENDA-MED01](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-med01): Agenda original → gateway → writer/collision/version/event history | Política por rol de configuración sigue preparada y no constituye gate específico del consumidor actual. Sesión/scope del médico y `actor_id/created_by_*` son dimensiones diferentes: el writer ordinario aún acepta atributos del payload; identidad confiable del creador no queda demostrada por color/canal |
| BR-03. Operador crea y luego cambia de alias | [AGENDA-OP01](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-op01): adapter canónico deriva cuenta/nombramiento/grant; creator snapshot inmutable y presentación actual son registros separados | Requiere servicio/entitlement/nombramiento vigentes, no verificados aquí. Cambiar alias no reescribe creador histórico; no implica permiso para todas las subacciones |
| BR-04. Call Center intenta reservar/asignar espera | [AGENDA-CC01](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-cc01) y [waitlist](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-esp01): IW01 define staff activo + capability CC + servicio del médico, con V1 acotado | No se localizó consumidor que reúna esos tres prerrequisitos; labels `call_center` y roles legacy no lo implementan. No se ejecutó una denegación HTTP; los cinco grants del operador tampoco habilitan waitlist |
| BR-05. El médico factura a un paciente | [UC-BILL](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md#autoridad-compartida-y-actores-por-operación): scope/selección → receptor/default → emisor/CSD → conceptos/impuestos/borrador/preview; importación y descarga privadas son otras operaciones | Confirmación visual no es snapshot final fiscal; certify sigue en frontera PAC409. SW primario/Finkok alternativo ya están decididos. Delegar requiere autoridad/atribución fiscal propia, no Agenda |
| BR-06. Llega por su cuenta y compra con ayuda de asesor | [COM-01..08](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#casos-com-01-a-com-08) separa origen técnico CLAIM/JOIN, captación y asesor de una venta | Falta de asesor no prueba adquisición orgánica; no se localizó traza canónica que registre ambos hechos comerciales con ese significado. Aceptante/creador no equivale a vendedor |
| BR-07. Vendedor distinto del revisor acreditador | [Identidades y procedencias](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#separacion-de-identidades-y-procedencias): C4 conserva revisor/caso; aceptación y actor técnico tienen productores propios | No adjudicar ventas al revisor; el vínculo canónico de venta/asesor no localizado es la conexión faltante, no la revisión C4 |
| BR-08. Contrato con código de descuento | [COM-09](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#descuentos-com-09): control de cupón oculto/deshabilitado; snapshots de precio/contrato y pago existentes pertenecen al flujo servidor | No se localizó validación/catálogo/regla canónica ni persistencia de cupón→efecto→aceptación/pago. No es Stripe Coupon por el nombre del input; descuento fiscal de concepto es distinto |
| BR-09. Cambia asesor responsable sin alterar venta original | [COM-10/readiness](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#readiness): cuenta interna, grants/delegaciones y workspace de colas existen | Gestión de clases/grants no es asignación de cartera. Relación temporal de soporte y atribución inmutable de venta no localizadas; requieren política y conexión autorizadas antes de una pantalla |

### Reconciliación y próximos límites

- **IW01 y sucesores:** conservar su gobierno backend y contrato futuro Call Center. El catálogo creció después; no repetir su conteo histórico ni generalizar «sin asignación asesor-médico» a ventas/carteras. [Workspace](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#workspace-com-10) identifica las colas actuales y proyecciones preparadas; no inventa un CRM.
- **Permisos:** restricciones owner/administrator/owner-only de configuración son política preparada, sin enforcement ordinario por operación acreditado; no se convierten en permisos aprobados para collaborator. Matriz legacy de operadora y boolean `operatorAllowed` no conceden las acciones ausentes del adapter/catálogo canónico. Billing conserva actor/scopes por operación; plataforma, médico y nombramiento no médico no son equivalentes.
- **Comercial:** los campos de aceptación y auditoría sí existen con su significado propio; no bastan para captación orgánica, vendedor, soporte o campaña. Cupones y atribución/retención por renovación/upgrade quedan clasificados, sin backfill ni comisiones inferidas.
- **Política:** las preguntas no aprueban reglas nuevas. Se requiere decisión del Director sobre semántica y alcance comercial/delegado cuando quiera completar una conexión, seguida de autorización técnica/operacional específica. No se reabre PAC-DEC01 ni el piloto Stripe cerrado.
- **Validación:** enlaces/anchors, símbolos, campos documentados, alcance Markdown y correspondencia de los nueve ejemplos se comprueban sobre fuente y metadata publicada. Ningún PASS documental representa pruebas de negocio, operación local o permisos concedidos.

**Validación documental CTX-MAP03:** PASS de 218 enlaces/anchors introducidos, incluidos 34 enlaces a líneas fuente; dieciséis paths de scripts HTTP citados inline existen. Revisiones dirigidas cotejaron 112 campos de diez tablas Billing y 33 métodos, además de 46 referencias comerciales (33 físicas en snapshot y trece en DDL preparado); los nombres/rutas/acciones Agenda se contrastaron con sus consumers. Los once inventarios JSON/CSV CTX-MAP02 conservan su blob original. Se comprobaron nueve ejemplos BR, cinco etiquetas de readiness, diff/whitespace y patrones sensibles; sólo ocho Markdown, dos nuevos. No se abrió conexión de datos ni se ejecutó test de producto.

Progreso CTX-MAP03 se conserva en estos casos/suplementos y en el estado compacto. Mantener sólo los IDs/casos afectados tras cambios futuros autorizados; no repetir inventario amplio ni crear agentes automáticos. Próxima acción de producto/piloto permanece `NONE_PENDING_DIRECTOR_REVIEW`.
