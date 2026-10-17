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
