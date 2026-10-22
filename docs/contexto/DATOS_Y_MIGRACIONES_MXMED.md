# Datos, fuentes físicas y migraciones — CTX-MAP02

Baseline `1b8dfccb68a7613b2182507a840454631dbf012a`, rama `ux/consultation-step2-vitals-r1`, 2026-10-17. [Inventario funcional](INVENTARIO_FUNCIONAL_MXMED.md) · [Interacciones](INTERACCIONES_MXMED.md) · [Cobertura](COBERTURA_Y_PENDIENTES_MXMED.md).

## Método y alcance verificable

Se inventariaron los paths versionados mediante `git ls-files`, no sólo archivos visibles a `rg`. Se inspeccionaron DDL y referencias SQL literales del código propio; los dumps/backups, filas seed y artefactos de datos se clasificaron por path sin copiarlos. No se ejecutó código de producto para inferir schema.

La conexión privada local ya existente se usó sólo para `SELECT VERSION()` e `information_schema`, dentro de una transacción READ ONLY terminada con rollback. No se consultaron pacientes, médicos, cuentas, membresías, sesiones, pagos, credenciales ni documentos; no se ejecutaron stored procedures, DDL, DML, restores o fixtures. La conexión y sus valores no forman parte de la entrega.

| Denominador | Observación de esta tarea |
|---|---|
| Servidor inspeccionado | MySQL `9.6.0`, schema `mxmed`, local; no servidor AWS |
| Tablas / vistas instaladas | 128 BASE TABLES y cero vistas; tipos individuales en snapshot |
| Columnas | 1.734; tipos, nullability, ordinal, claves y collations; sin valores/defaults privados |
| Constraints | 570: inventario de nombres/tipos/enforcement; 178 CHECKs |
| FKs | 138 restricciones, 160 posiciones de columnas; reglas UPDATE/DELETE registradas |
| Índices | 563 índices distintos y 921 posiciones de columnas de índice |
| Triggers / rutinas | 35 / 11; 14 posiciones de parámetros; sin cuerpos ni definers privados copiados |
| Tablas/vistas fuente o físicas | 204 nombres distintos: 195 declarados en DDL inspeccionado, 128 físicos; intersección 119 |
| Declarados sin objeto físico homónimo | 76; ausencia del nombre no prueba el estado de una migración completa |
| Físicos sin CREATE localizado | 9; lineage fuente incompleta, no objeto inexistente |
| Artefactos SQL | 210 paths: 155 clasificados como migración/DDL de módulo, 27 rollback, 12 schema histórico, 3 propuesta, 3 rutina y 10 excluidos de lectura de datos/test |
| Nombres de rutinas/triggers fuente o físicos | 156: 116 trigger y 40 procedure; incluye variantes históricas/preparadas, **no** objetos todos instalados |

La clasificación de artefactos es lexical por path/contenido, no ledger de ejecución. Los 36 objetos inicialmente sin writer literal fueron revisados por búsqueda dirigida: se recuperaron escritores dinámicos/constantes, triggers, catálogos SQL y una VIEW derivada; los restantes se clasifican como esquema preparado, adaptador rechazante, historia o metadata de backup sin writer actual localizado. Además se corrigieron cinco candidatos que sólo señalaban fixtures. El CSV conserva candidatos originales y `writer_notes`; una extracción léxica no demuestra autoridad canónica ni ausencia de writer.

## Inventarios consultables bajo demanda

- [METADATOS_LOCALES_MXMED.json](METADATOS_LOCALES_MXMED.json): todas las tablas, columnas, constraints, FKs/reglas, índices, CHECKs, triggers y rutinas observadas; fecha UTC exacta en cabecera. Metadata no equivale a datos ni activación.
- [OBJETOS_SQL_MXMED.csv](OBJETOS_SQL_MXMED.csv): los 204 nombres con dominio, privacidad, estado, referencias writer/read, DDL path/línea, FKs físicas, claves y columnas de lifecycle. `literal_writer_candidates` conserva la extracción inicial; 41 filas tienen adjudicación dirigida en `authoritative_writer_resolution`/`writer_notes`. `source-candidates` requiere confirmar el contrato de dominio; no adjudica autoridad por regex.
- [ARTEFACTOS_SQL_MXMED.csv](ARTEFACTOS_SQL_MXMED.csv): los 210 artefactos, incluidos rollback/propuestas/exclusiones explícitas.
- [COMPARACION_MIGRACIONES_MXMED.csv](COMPARACION_MIGRACIONES_MXMED.csv): nombres CREATE TABLE y ADD COLUMN literales presentes/ausentes por artefacto: 194 tablas declaradas. La VIEW fuente adicional `healthcare_provider_commercial_active_capabilities` figura en OBJETOS, no en este comparador TABLE-only. No interpreta cuerpos dinámicos, CHECKs/índices completos ni equivale a un receipt.
- [RUTINAS_Y_TRIGGERS_MXMED.csv](RUTINAS_Y_TRIGGERS_MXMED.csv): nombres, fuente, dominio, tabla de trigger cuando observable y presencia física. Las 46 rutinas/triggers instaladas están contabilizadas.
- [RESUMEN_INVENTARIO_MXMED.json](RESUMEN_INVENTARIO_MXMED.json): denominadores, clasificaciones y archivos con SQL dinámico que requieren retrieval dirigido.

Estados separados: `SOURCE_DEFINED`, `INSTALLED_METADATA_VERIFIED`, `PREPARED_NOT_APPLIED` sólo con contrato/check específico, `HISTORICAL`, `DRIFT_OR_UNKNOWN`. Tener tabla homónima no prueba igualdad de columnas/triggers/collations ni autorización operacional.

## Propiedad, escritores y consumidores

Los prefijos sirven para agrupar, no para conceder permisos. El CSV detalla cada objeto y sus referencias; esta matriz identifica autoridades de familia y asociaciones lógicas. Las relaciones físicas se consultan exclusivamente en `foreign_keys`/`foreign_key_rules` del snapshot.

| Familia / número de nombres inventariados | Writer y lectura autoritativos / ámbito | Datos, lifecycle y consumidores |
|---|---|---|
| Identity/gobierno: 42 | [repositorios Identity](../../modules/identity/repositories/), [RegistrationService](../../modules/identity/services/RegistrationService.php), [SessionService](../../modules/identity/services/SessionService.php), [R3ProfileGovernanceService](../../modules/identity/services/R3ProfileGovernanceService.php), [fuentes ACT03](../../modules/identity/ProfessionalSources/SourceProvisioningService.php) | Cuenta ≠ médico ≠ staff ≠ owner. `auth_accounts`, credencial/versiones, consents, tokens, grants, casos/MFA, admisión y binding. PII/security; lifecycle por estado/vigencia/revocación y auditoría. C4/enrollment/correcciones sólo composición opcional. |
| Perfiles: 18 | [PrivateProfileRepository](../../modules/profiles/repositories/PrivateProfileRepository.php), [DoctorContactPointsRepository](../../modules/profiles/repositories/DoctorContactPointsRepository.php), [VID](../../modules/profiles/services/VerifiedPhysicianIdentityService.php), [CRD](../../modules/profiles/services/VerifiedDoctorCredentialService.php); [DTO público](../../modules/profiles/repositories/PublicProfileRepository.php) | `profiles_doctors`, identidades verificadas, credenciales, información profesional, contactos y SEO. Es privado con proyección pública filtrada; no publicar filas completas ni inferir identidad por email/nombre. |
| Agenda/Consultorio: 22 | [repositorios Agenda](../../modules/agenda/repositories/), [AppointmentWriteController](../../modules/agenda/controllers/AppointmentWriteController.php), [ConsultoriosRepository](../../modules/agenda/repositories/ConsultoriosRepository.php) | `agenda_appointments/events`, reservas/OTP/overrides/waitlist, `consultorios`, `consultorio_schedule`, grupos y creator snapshots. Ámbito doctor/consultorio; estados/versiones/colisiones e idempotencia. `appointments`, `appointment_events`, `patient_flags` sin prefijo son schema legacy, no sustituyen writers actuales. |
| Pacientes: 10 | [PatientsRepository](../../modules/patients/repositories/PatientsRepository.php), [PatientDetailsRepository](../../modules/patients/repositories/PatientDetailsRepository.php); [resolución clínica](../../modules/identity/http/CanonicalClinicalIdentityResolver.php) | `patients_patients` identidad global; profiles/contacts/addresses/consents; `patients_doctor_links` autoridad del expediente por médico. `patient_identity_*` de reconciliación preparados/históricos se distinguen del maestro vivo. |
| Clínica: 59 | [servicios canónicos](../../api/_lib/), [gateway](../../api/clinical/index.php); writers por familia en el [mapa clínico](FUNCIONES_CLINICAS_MXMED.md) | Encounter/document/observations/longitudinal/tasks/orders/results y multimedia privados. No usar `clinical_patients` de scaffolding como maestro. Documento/revisión/fingerprint/participante y relación doctor-paciente determinan alcance; filas históricas no se regeneran por cambios visuales. |
| Firma: 2 | [PhysicianSignatureService](../../modules/signatures/PhysicianSignatureService.php), [handoff](../../modules/signatures/SignatureHandoffService.php) | `physician_signatures/handoffs`: PNG privado inmutable y tokens/hash/lifetime; mantener separados de QR clínico y autorización de prescribir. Ver [mapa clínico](FUNCIONES_CLINICAS_MXMED.md). |
| Media: 5 | [MediaAssetsRepository](../../modules/media/repositories/MediaAssetsRepository.php), [Photo](../../modules/media/services/DoctorProfilePhotoService.php), [review](../../modules/media/services/MediaCandidateSubmissionService.php) | Assets, lotes, submissions, archivos y ready events. Original/candidato/versión pública aprobada separados; storage privado y público bajo contratos distintos. Consumidores perfil/documentos. |
| Suscripciones: 11 | [PaymentIntentRepository](../../modules/subscriptions/repositories/SubscriptionPaymentIntentRepository.php), [webhook](../../modules/subscriptions/services/ProcessStripeSubscriptionWebhookService.php), [activación](../../modules/subscriptions/services/ActivateSubscriptionAfterPaymentService.php), [read model](../../modules/subscriptions/services/CurrentSubscriptionReadModelService.php) | `profile_subscriptions`, catálogo/precios, aceptación, checkout, rutas, intent/event/idempotency y contratos provider. Pago confirmado no es activación; conservar ambas transacciones y la autoridad que las llama. [Mapa financiero](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md). |
| Billing: 10 | [PatientBillingProfilesService](../../modules/billing/services/PatientBillingProfilesService.php), [InvoiceDraftService](../../modules/billing/services/InvoiceDraftService.php), [InvoiceArchiveService](../../modules/billing/services/InvoiceArchiveService.php) | Datos fiscales por relación médico-paciente, emisor/CSD privados, borrador/items/taxes, archivo XML/PDF y eventos/intentos. Ingreso Stripe por plan no es CFDI al paciente ni contrato PAC. |
| Organizaciones/provider: 20 | [HealthcareOrganizationDirectoryService](../../modules/agenda/services/HealthcareOrganizationDirectoryService.php), [MasterService](../../modules/agenda/services/HealthcareOrganizationMasterService.php), [Team](../../modules/agenda/services/HealthcareOrganizationTeamService.php), [Coverage](../../modules/agenda/services/HealthcareProviderCoverageService.php) | Maestro, sedes, servicios ofertados, cobertura, roles/invitaciones/claims y gobernanza. Identidad del servicio clínico no es oferta comercial; ámbito organization/location más grants y entitlement; [funciones ORG](INVENTARIO_FUNCIONAL_MXMED.md). |
| Platform audit: 3 | [CanonicalAuditWriter](../../modules/platform/services/CanonicalAuditWriter.php), [PdoCanonicalAuditTransactionAdapter](../../modules/platform/repositories/PdoCanonicalAuditTransactionAdapter.php), [Joined adapter](../../modules/platform/repositories/JoinedPdoCanonicalAuditTransactionAdapter.php); [verificador](../../modules/platform/services/AuditIntegrityVerifier.php) | Eventos, cabezas de stream y shadow histórico. Hash encadenado, actor/caso/request/correlation, metadata cerrada. Auditoría conserva historia; retención por [Registry](../../modules/platform/services/RetentionPolicyRegistry.php), sin inventar plazo numérico global. |
| Catálogos geográficos: 2 | [API catalog](../../api/catalog/index.php), [local SEPOMEX](../../sepomex-local.php), [importador administrativo](../../tools/import_catalog_cp_colonias_from_dump.php) | `catalog_cp_colonias` y `sepomex` legacy. Datos geográficos públicos; herramientas importación/encoding no son migraciones autorizadas para ejecutarse durante esta tarea. |

Los writers de familias compartidas también figuran como consumidores en otras familias; no se crea otra copia de identidad/paciente/precio/audit. Un writer fuente puede estar bloqueado antes de SQL por autoridad o readiness.

## Relaciones físicas frente a lógicas

- **Físicas:** cada FK exacta y regla se conserva en el snapshot y en el CSV por tabla; contar restricciones y columnas por separado. Para cualquier cambio, recuperar tipos/collations compuestos, UNIQUEs, CHECKs y triggers, no sólo el nombre del padre.
- **Lógicas:** cookie/session Valkey → cuenta/membresía SQL; `doctor_id` del contexto → recurso; referencia de cita → encounter; storage key → bytes privados; hash/fingerprint → snapshot; event Stripe → intent → aceptación/suscripción. Son contratos de aplicación salvo FK explícita observada.
- Timeline consolida lecturas; no es escritor de identidad, encounter o documento.
- IDs globales del paciente no eliminan `patients_doctor_links`. Mismo nombre, alias, licencia declarada o email no crea vinculación profesional ni permiso.
- Cambiar perfil, contactos, publicación de Consultorio, entitlements o CSS puede afectar el DTO/documento sin cambiar el maestro de paciente; [impacto transversal](INTERACCIONES_MXMED.md#dependencias-compartidas).

## Cadena preparada y diferencias físicas

La autoridad de orden es [DeploymentPackage::migrations/professionalSourceMigrations](../../modules/identity/Operational/DeploymentPackage.php), no ordenar lexicográficamente todos los SQL del repositorio.

| Etapa | Artefacto y propósito | Clasificación normal-local |
|---|---|---|
| 1 | [01 governance bindings](../../modules/identity/db/migrations/2026_10_16_01_governance_security_bindings.sql) | Preparado; requiere comprobación de columnas/constraints, no inferir aplicado por tablas de gobierno previas. |
| 2 | [02 physician binding](../../modules/identity/db/migrations/2026_10_16_02_physician_account_binding.sql) | Nuevas admisiones/bindings no observadas físicamente. |
| 3 | [03 evidencia](../../modules/identity/db/migrations/2026_10_16_03_physician_evidence_foundation.sql) | Evidencia/phone verification preparada; nombres ausentes según comparación. |
| 4 | [04 issuer](../../modules/identity/db/migrations/2026_10_16_04_physician_authority_issuer.sql) | Issuer/commands preparados; guardas prohíben replay ciego sobre autoridades pobladas. |
| 5 | [05 enrollment](../../modules/identity/db/migrations/2026_10_16_05_public_physician_enrollment.sql) | Origen/intake preparados, sin tablas físicas homónimas observadas. |
| 6 | [06 resolución](../../modules/identity/db/migrations/2026_10_16_06_reviewed_physician_enrollment.sql) | Resolución/handoff preparados; no asignan owner. |
| 7–8 | [PUB-COR01](../../modules/profiles/db/2026_10_16_01_public_correction_reports.sql), [PUB-COR02](../../modules/profiles/db/2026_10_16_02_correction_applications.sql) | Intake/revisión/aplicación preparados; nombres no instalados en esta lectura. |
| 9 | [FREE explícito](../../modules/subscriptions/db/2026_10_16_01_preloaded_public_free_source.sql) | Campos de elegibilidad preparados; no backfill ni clasificación FREE por falta de suscripción. |
| 10 | [metadata reader](../../modules/identity/deployment/act02/metadata.sql) | Procedimiento operacional de lectura preparado; no ejecutar/instalar por disponer de este mapa. |
| 11 | [ACT03 fuentes](../../modules/identity/db/migrations/2026_10_17_07_professional_source_provisioning.sql) | Cinco tablas de fuentes ausentes; siete triggers preparados; continuación C4 sin duplicar ciphertext. |

Los paths y presencia literal se validan contra los inventarios. Los paquetes [ACT02](../identity/ACT02-integrated-public-workflows.md) y [ACT03](../identity/ACT03-governed-professional-sources.md) conservan evidencia de ensayos desechables de octubre, no una autorización local actual. Las tablas OP-ACT02A **sí** existen, pero ello no activa invites, owner ni MFA real. La preparación general clínica/provider tiene más artefactos fuera de esta cadena: seguir su contrato específico; nunca aplicar los 155 DDL como una secuencia.

Nueve objetos físicos sin CREATE localizado: `agenda_public_otps`, `clinical_document_participants`, tres copias `clinical_documents*/clinical_document_participants*_backup*`, `clinical_documents`, `clinical_note_capture_tokens`, `clinical_patient_identity_bridge`, `sepomex`. Ver nombres exactos en CSV. Parte del schema se originó en writers legacy con DDL dinámico; parte es copia histórica. Su lineage y posible retiro permanecen `DRIFT_OR_UNKNOWN`; no leer filas ni borrarlos para resolver el mapa.

## Almacenamiento no SQL, cifrado y colas

| Superficie | Productor / consumidor / autoridad | Estado verificable |
|---|---|---|
| Sesiones Valkey | [SessionStoreFactory](../../modules/identity/services/SessionStoreFactory.php) y adaptadores Identity; cookie opaca, TTL/rotación/versiones | Namespace local y TLS productivo son composiciones distintas. No se enumeraron valores o sesiones. [Entornos](AWS_Y_ENTORNOS_MXMED.md). |
| Browser storage | Historial legacy de Operadores, mensajes de muestra, caches/UX en JS | No es cuenta, membresía, firma, suscripción ni writer canónico. No se leyó/borró storage real. |
| Evidencia C4/ACT03 | [EvidenceCipher](../../modules/identity/Accreditation/EvidenceCipher.php), [SourceProvisioningService](../../modules/identity/ProfessionalSources/SourceProvisioningService.php), storage privado | Cifrado verificado en writer, AAD por recurso/propósito; refs C4 conservan objeto original. Llaves fuera de Git; retención/custodia requieren decisión real. |
| Correcciones | [ReportCipher](../../modules/profiles/Corrections/ReportCipher.php) y journal/application | Payload/propuesta/proof cifrados; no inferir cifrado de contactos genéricos por su scope privado. |
| Media/firma/documentos | [LocalPersistentPrivateMediaStorage](../../modules/media/storage/LocalPersistentPrivateMediaStorage.php), módulos de firma/clinical private binary | Privado no equivale automáticamente a cifrado por columna. Validación MIME/hash/scope antes de bytes; proyección pública sólo aprobada. |
| Billing XML/PDF/CSD | [storage Billing](../../modules/billing/) y servicios/contrato fiscal | Archivo inmutable privado; CSD/llaves y datos fiscales no se publican ni se reutilizan en Stripe. |
| Email, jobs, outboxes | Adapters Identity/Agenda; invitation delivery; media ready events y task AWS | [Integraciones](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md), [AWS](AWS_Y_ENTORNOS_MXMED.md). Outbox QA no es delivery normal; fuente de cola/task no prueba worker ejecutándose. |

## Consulta segura y mantenimiento

Para una tarea futura: buscar primero el objeto en CSV; abrir writer/read/DDL enlazados y su contrato; comprobar relaciones físicas en snapshot; revalidar sólo metadata necesaria si hay autorización. Este snapshot no sustituye backup, revisión de drift ni firma del despliegue.

Una modificación autorizada debe actualizar las filas de objetos/artefactos afectadas y la fecha/baseline del estado. No regenerar inventarios completos ni ejecutar migraciones automáticamente. No interpretar los candidatos léxicos como una auditoría exhaustiva de cada query dinámica.

## ASE-INT01 R2 — proyección mínima, sin nuevo objeto

[Contrato/read model](../identity/ASE-INT01-R2-internal-team-readonly.md): SELECT de auth_accounts, internal_staff, internal_operator_grants e internal_capability_delegations; credencial únicamente mediante validador Identity actual. Sin perfil personal/evidencia staff/comercial nuevo, DDL real, backfill o copia de datos. QA usa instancia/datadir MySQL nuevos, cinco DDL fuente y principal HTTP SELECT-only; no consulta metadata/filas de instancias existentes. Metadata/censos físicos históricos anteriores no se revalidan ni alteran.

## ASE-INT02A — writer reutilizado, sin nuevo esquema

[Contrato](../identity/ASE-INT02A-governed-advisor-grants.md): mismo `internal_operator_grants`, auditoría `platform_audit_events`/`platform_audit_stream_heads` y lecturas cuenta/credencial/staff/delegaciones, con target ADVISOR/ACTIVE bajo locks y una transacción por comando. No DDL de producto ni nuevo store; fuente/privilegios/fixtures sólo en MySQL nuevo de QA, sin consultar instancias existentes. Metadatos y censos históricos no se revalidan ni regeneran.

## ASE-INT02B — proyección y transporte, sin migración

[Contrato](../identity/ASE-INT02B-team-permission-ui.md): read añade disponibilidad y claves editables por target; writer, grants y audit existentes sin cambios. Borrador/token sólo memoria del navegador. No store persistente, schema ni writer alternativo. QA crea exclusivamente datadir/schema y Valkey propios, con SQL/audit reales de fixture; no consulta instancias reales ni acredita DDL operacional.

## ASE-INT03A — UPDATE staff existente

[Contrato](../identity/ASE-INT03A-advisor-lifecycle.md): estado de `internal_staff` y append audit en transacción canónica única; cuenta/credencial/grants sólo consultados por lifecycle. No DDL de producto, borrado de historia, recreación de grants ni habilitación de cuenta. MySQL/Valkey nuevos exclusivos de QA; permisos del principal sintético no son migración operacional.

## ASE-INT03B-PREP01 — inspección de esquema, sin ejecución

[Contrato](../identity/ASE-INT03B-PREP01-master-privileged-authorization.md) documenta tablas staff/grants/delegaciones y casos/challenges/proofs existentes; propone ampliar sólo CHECK de operaciones y contexto tipado, sin archivo SQL nuevo. El paquete ACT03 reemplaza el CHECK y omite dos operaciones de correcciones antes admitidas: hallazgo de fuente que exige preflight/rehearsal posterior, no constatación de DB real. Sin consultas SQL/DDL/DML, nuevos objetos físicos ni regeneración del censo; auditoría y sesión conservan sus dueños.

## ASE-INT03B-IMP01 — CHECK acumulativo y rehearsal

[Contrato/evidencia](../identity/ASE-INT03B-IMP01-master-security-foundation.md): nueva migración `modules/identity/db/migrations/2026_10_18_01_internal_master_governance_operations.sql`, unión exacta de once operaciones históricas y seis Master. Reusa auth_governance_cases.security_context y tablas MFA/staff/grants/delegaciones/audit, sin tablas producto nuevas, seeds, backfill ni enums. A/B/C/E ensayan el nuevo CHECK; D confirma que ACT03 rechaza filas de corrección y se detiene preservando datos/constraints. No acredita schema instalado real ni todas las rutas de upgrade. Tabla de review sólo en fixture descartable.

## ASE-INT03B-IMP02 — conexión UI aislada

[IMP02](../identity/ASE-INT03B-IMP02-master-ui-integration.md) no añade SQL/tablas/enums. ReadService proyecta operaciones exactas disponibles y csrf proyecta factor listo; UI consume casos/proofs/audit existentes. Appointment reader indisponible. La ruta D permanece BLOCKED_HISTORICAL_ACT03_CHECK; no reparación, borrado histórico ni DDL persistente.

## ACT03-CHECK-COMPAT01 — sucesor de compatibilidad SQL

[Ruta versionada COMPAT01](../identity/ACT03-CHECK-COMPAT01-historical-case-check.md): diez etapas ACT02 intactas → CHECK compatible 18_02 → objetos ACT03 18_03, cuyo cuerpo coincide con el histórico 17_07. CHECK 11 antes de Master y 17 después de su migración explícita. D conserva correcciones; H exige recuperación de preestado verificado en target nuevo, nunca rollback DDL supuesto. Metadata/receipts reales no inspeccionados.

## ASE-ADM02-POL01 — contrato lógico staff, sin schema

[Política vigente](../identity/ASE-ADM02-POL01-staff-admission-policy.md): principios aprobados; caso/perfil/confirmación, invitación, retrato/evidencia, revisión/admisión y resultado de handoff son **objetos lógicos propuestos**, no tablas/enums/DDL autorizados. Reutiliza auth_accounts/credentials/consents/one_time_tokens e internal_staff/grants/delegaciones/audit; consents actuales sólo terms/privacy_notice y tokens sin propósito staff. No filas consultadas, datos físicos ni censo modificados.

El snapshot Master real sólo contiene account_id, credential_version, review_version; respaldo futuro debe ligar revisión aprobada vigente, versiones documentales y materialidad bajo la misma transacción. No usar tablas C4 como autoridad staff. Register sigue creando ADVISOR ACTIVE sin revisión; futura guarda dentro del mismo writer, sin alterar callers IW01. Retención/custodia/legal pendientes impiden intake real. COMPAT01 y migraciones históricas se conservan intactos.

## ASE-ADM02-IMP01 — tres objetos staff aislados

[Contrato/schema](../identity/ASE-ADM02-IMP01-staff-case-foundation.md) añade auth_staff_admission_cases, auth_staff_admission_versions y auth_staff_admission_operations: FK/CHECK/UNIQUE, puntero/versión anterior del mismo caso, snapshots/recibos inmutables con cuatro triggers. Migración18_04 sólo ensayada en MySQL nuevo; sin aplicación real ni cambio COMPAT01/DeploymentPackage. Dos eventos audit canónicos nuevos, sin datos personales en metadata; tablas Identity/staff/grants existentes intactas. Retención y eliminación gobernada pendientes antes de live.

## ASE-ADM02-IMP02 — invitación y recibo

[Contrato y evidencia](../identity/ASE-ADM02-IMP02-staff-invitation-binding.md): Migración18_05 posterior a18_04 añade auth_staff_admission_invitations y auth_staff_admission_invitation_operations, tres triggers, FKs y una intención ACTIVE por caso. HMAC de destinatario por caso, digest de token aleatorio, expiración/estados terminales y recibos inmutables; binding en el mismo caso IMP01. Audit59 intacto. Sin email/token raw en SQL, cambios históricos/COMPAT01, backfill, staff ni DDL real.

## ASE-ADM02-IMP03 — versiones de media privadas

[Contrato y evidencia](../identity/ASE-ADM02-IMP03-private-staff-media.md): Migración18_06 añade auth_staff_media_assets/operations, cuatro triggers, FK a perfil capturado/cuenta/versión anterior y unique caso+purpose+asset_version. Current derivado, sin sobrescribir historia. Cipher staff/key-id/hash ciphertext, refs custodia/retención, actor/tiempo y recibo/audit; sin plain bytes en SQL. Catálogo audit61 conserva políticas59 previas. IMP01/02, schema COMPAT01 y DeploymentPackage intactos; ningún DDL real.

## Consolidación staff aislada

Migración18_07: conjuntos documentales, intenciones, revisiones y recibos del mismo caso. CHECK gobierno conserva17 propósitos y añade STAFF_ADMISSION_REVIEW; factores/challenges/proofs canónicos. INSERT staff sigue exclusivamente en InternalGovernanceService; sin instalación real. Véase [contrato funcional](../identity/ASE-ADM02-FUNCTIONAL-CONSOLIDATION.md).

## UXFUNC01 — baja definitiva staff, SQL preparado

[Contrato](../identity/ASE-ADM02-UXFUNC01.md): migración2026_10_18_08 amplía internal_staff.status con REMOVED, limitado a ADVISOR y terminal por trigger. La baja revoca grants/delegaciones con auditoría en la misma transacción. Conserva cuenta, casos e historia. Sólo instalada en infraestructura nueva sintética; no DDL operacional.

## Proyección UXFUNC02

No añade migraciones: filtra internal_staff.status antes de paginar y distingue solicitudes admitidas mediante auth_staff_admission_completions. No DELETE ni reactivación REMOVED. Esquema UXFUNC01 intacto. Véase [UXFUNC02](../identity/ASE-ADM02-UXFUNC02.md).

## UXFLOW03 — sin migración

Sin objetos nuevos. Reutiliza versiones/procedencia personal, invitaciones y casos; proyección de acciones elegibles y caso vinculado. Challenge deriva email de auth_accounts por actor autenticado; no acepta email como campo del navegador. Véase [UXFLOW03](../identity/ASE-ADM02-UXFLOW03.md).

## PANEL04 — proyección sin migración

[Contrato](../identity/ASE-ADM02-PANEL04.md): manifest documental y audit existente minimizados, sin tabla/ledger/versionado paralelo. History máximo100 con orden UTC+event_id, sólo target/caso exacto y ámbito delegado; sin metadata privada. No nuevas atribuciones, porcentajes, pagos o comisiones.

## PANEL05 — búsquedas y recuperación sin migración

[PANEL05](../identity/ASE-ADM02-PANEL05.md) filtra nombres actuales vía completions/casos/versiones sólo en reader Director con fuente conectada, antes de LIMIT. Pendientes conserva creator/binding. Lee recibos actor+operation bajo locks canónicos y estado mínimo de invitación Director-only; nunca retorna token ni destinatario. Sin nuevas tablas, writer ni cambios de auditoría/grants.

## PANEL06 — extensión mínima de la misma versión personal

[PANEL06](../identity/ASE-ADM02-PANEL06.md) y migración [2026_10_19_01](../../modules/identity/db/migrations/2026_10_19_01_staff_personal_presentation.sql): gender/contact_phone/avatar/presentation en `auth_staff_admission_versions`, defaults neutros, versiones/procedencia/auditoría y triggers conservados. Teléfono de contacto no verificado; correo sigue `auth_accounts`, sin nueva credencial/identidad. Retrato conserva assets/operaciones/cifrado StaffMedia; sujeto y actor editor separados, propósito independiente de evidencia MFA. Sólo datadir nuevo sintético. El usuario SQL del ensayo recibe SELECT sobre memberships ya existentes para current-session; no se crean memberships ni roles. Los recibos de revisión exponen sólo la intención propia y su consumo, sin pruebas reutilizables ni bytes privados.

## PANEL08 — retrato privado previo al binding

[PANEL08](../identity/ASE-ADM02-PANEL08.md) y [migración2026_10_19_02](../../modules/identity/db/migrations/2026_10_19_02_staff_preparation_portrait.sql): auth_staff_media_assets.account_id nullable sólo para INTERNAL_PRESENTATION_PORTRAIT, CHECK impide evidencia sin cuenta. Mantiene FK a caso/versión, fuente actor, cifrado/AAD, recibos y triggers inmutables. NULL es procedencia de captura; no se actualiza al binding. Lectura requiere autorización sobre el mismo caso y propósito. Sin tablas nuevas ni backfill; DDL instalado sólo en datadir nuevo sintético.

## OPER09 — conexión Media sólo en datadir nuevo

[OPER09](../identity/ASE-ADM02-OPER09.md) reutiliza migrations existentes de `media_review_submissions/files/batches/ready_events` y `profiles_doctors` únicamente en infraestructura desechable propia. HTTP recibe SELECT sobre esos objetos; raíces públicas/privadas apuntan al mismo directorio temporal propietario. No SQL nuevo productivo, mutación de bases oficiales ni datos reales. Advisor/caso/invitación/documentos/admisión/grant/lifecycle usan sus writers canónicos; ninguna actualización directa para saltar gates. La nueva versión personal no reescribe la revisión histórica: nuevos envíos tras admisión permanecen bloqueados por `StaffWorkflow\Service::submit`. Tokens, contraseñas y TOTP quedan fuera de Git, en archivos locales privados; TTL Identity/MFA sin cambios.


## WORKSPACE02 — objetos aditivos de solicitud en ensayo owned

[Contrato](../identity/ASE-ADVISOR-WORKSPACE02.md#datos-y-migración) y [SQL preparado](../../modules/identity/db/migrations/2026_10_20_01_physician_profile_requests.sql), baseline `0dbccc94`. No modifica los denominadores/snapshot físico históricos ni afirma instalación oficial. SQL aplicado y recorrido en instancia owned nueva: [principal 51 PASS](../evidence/ase-advisor-workspace02/qa.json) y [ownership 23 PASS](../evidence/ase-advisor-workspace02/ownership-browser.json); seguridad final 20 PASS.

`auth_physician_profile_request_evidence`, `decisions`, `profiles`, `reads`, `responses`, `ownership` y `locks` agregan linkage exacto a `auth_physician_enrollment_origins`/intake y evidencia C4 existente, decisión/recibo append-only, razón/respuesta cifradas, perfil resultante único, recibos por sesión y casos owner históricos. `profiles_doctors` continúa siendo el único maestro. Ninguna aplicación médica se almacena como `auth_staff_admission_cases`.

Writer RequestService consume proof, audita y materializa mediante PrivateProfileRepository en un mismo PDO. Primero adquiere y valida el singleton de materialización, antes de una lectura consistente del callback; sin ese registro falla cerrado. La prueba final de dos aplicaciones/cuentas con igual cédula confirma un solo perfil y un perdedor en conflicto, sin evento de aprobación duplicado. `auth_physician_profile_request_ownership` usa PK `(origin_id, link_version)` y case_id único con enlaces inmutables; la recuperación preserva intentos anteriores. R3/AccountMembershipRepository mantienen el writer owner y la aceptación del solicitante después de C4; se añade únicamente autoridad exacta por aplicación. CHECK de operaciones acumulativo mantiene 18 y añade 2; endorsements nuevos requieren el adapter específico, no amplían el consumidor global. `platform_audit_events` conserva procedencia sellada; evento nuevo `PHYSICIAN_PROFILE_REQUEST_CHANGED` y lecturas History/Team no duplican eventos. Producción/DDL original no autorizados.

## WORKSPACE03 — agregación sin migración

[PendingSummary](../../modules/identity/PhysicianRequests/PendingSummary.php) reutiliza origins, intakes, decisiones, respuestas, enlace de materialización y submissions/batches/REVIEW Media. Una clave physician:doctor_id o application:origin_id identifica el grupo; nombres no unen registros. No tablas, índices ni DDL nuevos. El catálogo canónico incorpora physician_pending_summary_read; su persistencia/auditoría usa los grants existentes. [Contrato](../identity/ASE-ADVISOR-WORKSPACE03.md).

## STAFF-CONTROL04 — intenciones pendientes aditivas y catálogo compartido

[Contrato](../identity/STAFF-CONTROL04.md), [migración](../../modules/identity/db/migrations/2026_10_19_01_staff_capability_intentions.sql). `auth_staff_capability_intentions` guarda versiones, caso/versión personal, destinatario, binding/cuenta exactos, capabilities, actor, operación/digest y audit. `auth_staff_intention_activations` enlaza confirmación individual con versión y evento del grant canónico. Writer HTTP con SELECT/INSERT sin UPDATE sobre ambos ledgers; compare-and-swap bajo lock del caso, recibos evitan reactivar un grant revocado por replay. No reemplaza `internal_operator_grants`. `catalog_cp_colonias` se reutiliza mediante estado/municipio como claves naturales; id de colonia no es ciudad. DDL y dump exclusivamente en DB owned nueva; cero migración real. Perfiles/versiones, invitaciones, completions, documentos, Media y decisiones Requests conservan sus fuentes.

## STAFF-CONTROL05 — corrección contextual aislada

[Contrato e inventario](../identity/STAFF-CONTROL05.md). Sin tablas ni migraciones nuevas. InvitationService proyecta versión y usabilidad efectiva desde caso/estado/expiración; conserva la fila histórica emitida al cancelar el caso. Service rechaza revoke si la cuenta vinculada ya tiene relación staff. Intentions mantiene ledgers/versiones; catálogo de preparación reducido a consumidores conectados. Hitos de simulación son metadata privada desechable, no nueva autoridad ni estado de negocio.

## STAFF-CONTROL06 — proyección contextual sin DDL

[Contrato](../identity/STAFF-CONTROL06.md). Bootstrap proyecta referencia de correo propio desde `auth_accounts` según el ID autenticado y categoría vigente desde `internal_staff`; no acepta cuenta/rol suministrados por el cliente. Casos, versiones, invitaciones, intenciones y geografía conservan sus fuentes y writers. Ninguna tabla, migración, catálogo municipal ni relación duplicada.

## STAFF-DOC-VIS02 — acknowledgement y fecha de transporte

[Contrato](../identity/STAFF-DOC-VIS02.md), [migración aditiva](../../modules/identity/db/migrations/2026_10_21_01_staff_document_ack.sql). Extiende CHECK de intent con ACK; ledger inmutable `auth_staff_document_acknowledgements` ligado a caso/versión/set/intent, actor/credencial/rol DIRECTOR, timestamp y audit canónico. No sustituye identity_reviews ni completions. `delivered_at` nullable registra ACK del puerto de transporte; sin backfill ni inferencia de correo externo. La composición actual sólo prepara sobre local. DDL exclusivamente ensayado en DB owned nueva, HTTP SELECT/INSERT para ledger; no migración real.

## STAFF-DOC-LIFECYCLE03 — mismos objetos, sin nueva migración

[Contrato](../identity/STAFF-DOC-LIFECYCLE03.md). Resolución histórica por case_id+set_id, revisión/ack por conjunto y recibo READ exacto. Nuevas entregas conservan assets/manifests anteriores; no modifica completions, cuentas ni grants. Misma metadata de retención/custodia y cifrado; no plazos nuevos ni borrado físico.

## STAFF-AUTH-UX08 — snapshot de intención de sesión

[Contrato](../identity/STAFF-AUTH-UX08.md) y [migración aditiva](../../modules/identity/db/migrations/2026_10_21_02_staff_session_review.sql). Dos columnas nullable session_case_version/session_review_id en auth_staff_review_intents; trigger conserva inmutabilidad. Historia MFA preservada; filas sin snapshot no admiten consumo bajo nueva política. Sin escrituras nuevas de grants/media/Identity ni instalación real.

## ADVISOR-OPERATOR-SUPPORT01 — proyección sin schema nuevo

[Contrato y fuentes](../identity/ADVISOR-OPERATOR-SUPPORT01.md). Reutiliza membresías, cuentas, invitaciones de operador, revisiones/grants, presentación y auditoría; `internal_operator_grants` almacena la nueva clave explícita de consulta. Sin nueva tabla, migración de producto ni modificación de ownership. Lectura no expira ni cancela invitaciones; accepted histórico conserva su estado, pending vencida se deriva por fecha. Setup aplica esquema existente exclusivamente a datadir nuevo verificado; factory OP01 genera escenarios y eventos canónicos, transferidos exactamente dentro de ese servidor sintético. No DDL persistente ni datos reales; el censo previo permanece histórico.

## CROSS-SURFACE-LINK03 — proyecciones locales v1

[Contrato y revocación](../identity/CROSS-SURFACE-LINK03.md). Dos principales loopback/ tres vistas administrativas no actualizables sobre perfiles, consultorios y relaciones: configuración de entorno versionada, separada de migraciones de aplicación. Sin tablas nuevas en origen ni copias materializadas persistentes; sin DML médico. Recibo privado permite recuperación/repetición. Los tests de paginación crean únicamente tablas QA sintéticas en la base desechable. El censo histórico de este mapa no se regenera.
