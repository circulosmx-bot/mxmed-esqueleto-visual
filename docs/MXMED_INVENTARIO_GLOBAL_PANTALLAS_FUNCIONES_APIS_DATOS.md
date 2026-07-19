# Inventario Global de Pantallas, Funciones, APIs y Datos — México Médico

**Contrato:** `MXMED_SYSTEM_WIDE_PRODUCT_INVENTORY_V1`
**Versión:** `1.3.0`
**Fecha:** 2026-07-18
**Método:** auditoría estática completa en cobertura
**Runtime:** no ejecutado

## 1. Portada y versión

Este documento materializa `MXMED_SYSTEM_WIDE_PRODUCT_INVENTORY_V1` y es la fuente canónica del inventario transversal estático. Los inventarios completos y validables residen en la evidencia JSON; este Markdown conserva totales, mapas, hallazgos y decisiones de alcance.

- [Registro maestro de deuda](./MXMED_REGISTRO_MAESTRO_DE_DEUDA_PRODUCTO.md)
- [Contrato maestro y PP-Decisiones](./PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md)
- [Requisitos del plano de control de operadores](./MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md)
- [Aprobación directoral](./MXMED_APROBACION_DECISIONES_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md)

Estado vigente: PP278 aprueba 30 requisitos atómicos y deja Activity 2
`UNBLOCKED_READY_NOT_STARTED`; los totales implementados no cambian.

## 2. Propósito

Responder qué superficies existen en el repositorio, cómo se conectan estáticamente, qué queda documentado o parcial y qué auditorías especializadas deben seguir, sin implementar ni ejecutar flujos.

## 3. Alcance

Se inventariaron 695 rutas versionadas y se leyó contenido seguro de 531 archivos de texto. Configuraciones efectivas, storage, uploads, backups, mocks y seeds se conservaron únicamente como nombres de inventario.

## 4. Metodología

- Inventario de rutas con Git y clasificación por extensión/capa.
- Extracción estática de entry points, paneles, diálogos, navegación, rutas, clases y schemas.
- Referencias directas `archivo:línea`; sin snippets extensos ni valores de datos.
- Separación estricta de conexión confirmada, inferencia estructural, documentación y verificación runtime.
- Crosswalk completo contra las 92 deudas de PP273.

## 5. Limitaciones estáticas

- No se ejecutaron PHP, navegador, HTTP, SQL, Stripe, correo, Maps, IA, AWS, CDK, Docker ni tests.
- Un `fetch`, route dispatch o invocation confirma una referencia estática, no el resultado runtime.
- Rutas dinámicas, auth efectivo, datos persistidos y side effects requieren auditoría especializada o runtime posterior.
- `candidate_orphan` no equivale a código muerto ni vulnerabilidad.

## 6. Taxonomía de IDs

| Namespace | Uso |
|---|---|
| INV-SCR | SCR |
| INV-NAV | NAV |
| INV-FUN | FUN |
| INV-ACT | ACT |
| INV-STA | STA |
| INV-API | API |
| INV-CTL | CTL |
| INV-SVC | SVC |
| INV-REP | REP |
| INV-DAT | DAT |
| INV-EVT | EVT |
| INV-NOT | NOT |
| INV-CAP | CAP |
| INV-ROL | ROL |
| INV-OWN | OWN |
| INV-EXT | EXT |
| INV-DOC | DOC |
| INV-TST | TST |
| INV-FLW | FLW |
| INV-GAP | GAP |

## 7. Niveles de evidencia

| Nivel | Entradas |
|---|---|
| code_only | 120 |
| confirmed_code_and_documentation | 9 |
| confirmed_direct_reference | 647 |
| documented_only | 148 |
| not_found_in_scanned_scope | 0 |
| probable_by_structure | 29 |
| requires_runtime_verification | 0 |

## 8. Estados de implementación

| Estado | Entradas |
|---|---|
| backend_only | 119 |
| candidate_orphan | 15 |
| data_only | 19 |
| deferred_refactor | 0 |
| documented_only | 148 |
| frontend_only | 109 |
| implemented_connected_static | 172 |
| implemented_partial | 300 |
| mock_or_fixture_only | 34 |
| not_found_in_scanned_scope | 0 |
| placeholder_or_shell | 21 |
| protected_closed | 1 |
| requires_runtime_verification | 14 |
| runtime_gate | 1 |

## 9. Resumen ejecutivo

| Métrica | Total |
|---|---|
| Archivos versionados | 695 |
| Archivos de contenido seguro revisados | 531 |
| Entradas INV | 953 |
| Pantallas/superficies | 143 |
| Navegación | 34 |
| Funciones | 41 |
| APIs | 166 |
| Controladores | 27 |
| Servicios | 28 |
| Repositorios | 33 |
| Tablas/vistas | 47 |
| Flujos | 15 |
| Candidatos a desconexión | 15 |
| Deudas cruzadas | 92/92 |

Hallazgo rector: existen superficies amplias en frontend y backend, pero la equivalencia de auth, plan, ownership, eventos y delivery no puede generalizarse desde presencia de nombres. El siguiente paso correcto sigue siendo una auditoría especializada, no implementación.

## 10. Arquitectura funcional observada

```text
Páginas públicas + shell privado + UI PHP
        ↓ fetch/form/include
Dispatchers api/**
        ↓ controller/service/repository (no uniforme)
Schemas SQL versionados
        ↓ eventos/notificaciones parciales
Integraciones externas y gates runtime
```

La arquitectura observada combina un shell HTML/JS de gran tamaño, páginas públicas independientes, UI PHP para Agenda/Clínico, dispatchers por dominio y módulos con capas explícitas. Clinical conserva lógica considerable dentro del dispatcher; Agenda, Patients, Profiles y Subscriptions exhiben capas más visibles.

## 11. Inventario de pantallas

| Tipo | Total |
|---|---|
| modal | 63 |
| qa_debug | 3 |
| screen | 31 |
| subscreen | 46 |

| ID | Nombre | Dominio | Estado | Fuente |
|---|---|---|---|---|
| INV-SCR-001 | Panel principal de mi perfil | profiles | implemented_partial | index.html:514 |
| INV-SCR-002 | Datos Personales | profiles | implemented_partial | index.html:722 |
| INV-SCR-003 | Consultorio | profiles | implemented_partial | index.html:1622 |
| INV-SCR-004 | Opiniones recibidas en mi perfil | profiles | implemented_partial | index.html:2243 |
| INV-SCR-005 | Seguridad | identity-security | implemented_partial | index.html:2313 |
| INV-SCR-006 | Suscripción y planes | cross-cutting | implemented_partial | index.html:2889 |
| INV-SCR-007 | p-ag-ajustes | agenda | implemented_partial | index.html:3178 |
| INV-SCR-008 | p-ag-operadores | administration | implemented_partial | index.html:3505 |
| INV-SCR-009 | p-ag-admin | agenda | implemented_partial | index.html:3818 |
| INV-SCR-010 | Buscar paciente en mi archivo | patients | implemented_partial | index.html:4445 |
| INV-SCR-011 | Receta rápida | clinical | implemented_partial | index.html:4494 |
| INV-SCR-012 | Expediente paciente | clinical | implemented_partial | index.html:4605 |
| INV-SCR-013 | Facturación | subscriptions-payments | implemented_partial | index.html:10144 |
| INV-SCR-014 | Paquetes y Promociones | subscriptions-payments | implemented_partial | index.html:10235 |
| INV-SCR-015 | Notificaciones | notifications | implemented_partial | index.html:10354 |
| INV-SCR-016 | Perfil público del médico | profiles | requires_runtime_verification | profiles/doctor.php:610 |
| INV-SCR-017 | Agenda pública | agenda | implemented_partial | public-agenda.html:2 |
| INV-SCR-018 | Reserva pública de cita | agenda | implemented_partial | public-book.html:2 |
| INV-SCR-019 | Cancelación pública de cita | agenda | implemented_partial | public-cancel.html:2 |
| INV-SCR-020 | Captura pública de nota/firma | clinical | implemented_partial | public/note-capture.html:2 |
| INV-SCR-021 | Agenda PHP - índice | agenda | requires_runtime_verification | api/agenda/ui/index.php:1 |
| INV-SCR-022 | Agenda PHP - día | agenda | requires_runtime_verification | api/agenda/ui/day.php:1 |
| INV-SCR-023 | Agenda PHP - cita | agenda | requires_runtime_verification | api/agenda/ui/appointment.php:1 |
| INV-SCR-024 | Agenda PHP - lista de espera | agenda | requires_runtime_verification | api/agenda/ui/waitlist.php:1 |
| INV-SCR-025 | Agenda PHP - asignar día | agenda | requires_runtime_verification | api/agenda/ui/waitlist_assign_pick_day.php:1 |
| INV-SCR-026 | Agenda PHP - asignar horario | agenda | requires_runtime_verification | api/agenda/ui/waitlist_assign_pick_slot.php:1 |
| INV-SCR-027 | Expediente - historial | clinical | requires_runtime_verification | modules/clinical/ui/historial.php:1 |
| INV-SCR-028 | Expediente - encuentro | clinical | requires_runtime_verification | modules/clinical/ui/encounter.php:1 |
| INV-SCR-029 | Expediente - timeline | clinical | requires_runtime_verification | modules/clinical/ui/timeline.php:70 |
| INV-SCR-030 | Documento clínico | clinical | requires_runtime_verification | modules/clinical/ui/document.php:1 |
| INV-SCR-031 | Visor de documento clínico | clinical | requires_runtime_verification | modules/clinical/ui/viewer.php:1 |
| INV-SCR-032 | Índice documental estático | documentation | mock_or_fixture_only | docs/index.html:3 |
| INV-SCR-033 | Selector QA de plan público | profiles | mock_or_fixture_only | profiles/doctor.php:1112 |
| INV-SCR-034 | Estado debug de activación de suscripción | subscriptions-payments | mock_or_fixture_only | index.html:3088 |

El detalle de roles, planes, capabilities, estados y renderer de cada superficie está en `screen-inventory.json`.

## 12. Inventario de navegación

Se identificaron 34 entradas únicas por origen/label/destino saneado. 17 son placeholders/shells.

| Estado | Total |
|---|---|
| implemented_connected_static | 17 |
| placeholder_or_shell | 17 |

## 13. Inventario de funciones

| ID | Función | Dominio | Actor | Estado |
|---|---|---|---|---|
| INV-FUN-001 | Ver dashboard | cross-cutting | doctor | implemented_partial |
| INV-FUN-002 | Editar perfil privado | profiles | doctor | implemented_partial |
| INV-FUN-003 | Ver perfil público | profiles | public | implemented_partial |
| INV-FUN-004 | Gestionar puntos de contacto | profiles | doctor | implemented_partial |
| INV-FUN-005 | Reclamar perfil | ownership | public | placeholder_or_shell |
| INV-FUN-006 | Administrar consultorios | profiles | doctor | implemented_partial |
| INV-FUN-007 | Ver agenda pública | agenda | public | implemented_partial |
| INV-FUN-008 | Solicitar cita pública | agenda | public | implemented_partial |
| INV-FUN-009 | Cancelar cita pública | agenda | public | implemented_partial |
| INV-FUN-010 | Administrar citas | agenda | doctor | implemented_partial |
| INV-FUN-011 | Configurar disponibilidad | agenda | doctor | implemented_partial |
| INV-FUN-012 | Gestionar lista de espera | agenda | doctor/operator | implemented_partial |
| INV-FUN-013 | Gestionar operadores de agenda | administration | doctor | implemented_partial |
| INV-FUN-014 | Buscar pacientes | patients | doctor | implemented_partial |
| INV-FUN-015 | Crear contacto de paciente | patients | doctor | implemented_partial |
| INV-FUN-016 | Editar contacto de paciente | patients | doctor | implemented_partial |
| INV-FUN-017 | Abrir expediente | clinical | doctor | implemented_partial |
| INV-FUN-018 | Gestionar casos clínicos | clinical | doctor | implemented_partial |
| INV-FUN-019 | Gestionar encuentros | clinical | doctor | implemented_partial |
| INV-FUN-020 | Registrar examen físico | clinical | doctor | implemented_partial |
| INV-FUN-021 | Gestionar consentimientos | clinical | doctor | implemented_partial |
| INV-FUN-022 | Crear documento clínico | clinical | doctor | implemented_partial |
| INV-FUN-023 | Ver documento clínico | clinical | doctor | implemented_partial |
| INV-FUN-024 | Capturar adjunto/firma mediante enlace | clinical | patient-link | implemented_partial |
| INV-FUN-025 | Gestionar estancia hospitalaria | clinical | doctor | implemented_partial |
| INV-FUN-026 | Crear receta | clinical | doctor | implemented_partial |
| INV-FUN-027 | Listar reseñas | reviews | public | implemented_partial |
| INV-FUN-028 | Moderar reseña | reviews | operator | documented_only |
| INV-FUN-029 | Ver buzón de notificaciones | notifications | doctor | implemented_partial |
| INV-FUN-030 | Marcar notificación leída | notifications | doctor | implemented_partial |
| INV-FUN-031 | Configurar preferencias de notificación | notifications | doctor | documented_only |
| INV-FUN-032 | Ver plan actual | subscriptions-payments | doctor | implemented_partial |
| INV-FUN-033 | Crear checkout | subscriptions-payments | doctor | implemented_partial |
| INV-FUN-034 | Crear PaymentIntent | subscriptions-payments | doctor | implemented_partial |
| INV-FUN-035 | Procesar webhook Stripe | subscriptions-payments | system | implemented_partial |
| INV-FUN-036 | Activar suscripción post-pago | subscriptions-payments | system | implemented_partial |
| INV-FUN-037 | Resolver capacidades por plan | plans-capabilities | system | implemented_partial |
| INV-FUN-038 | Consultar catálogo postal | catalog | public/doctor | implemented_partial |
| INV-FUN-039 | Verificar contraseña | identity-security | doctor | placeholder_or_shell |
| INV-FUN-040 | Verificar SMS/segundo factor | identity-security | doctor | placeholder_or_shell |
| INV-FUN-041 | Usar IA profesional | artificial-intelligence | doctor | documented_only |

## 14. Inventario de acciones y estados

Acciones transversales: 29. Estados localizados/documentados: 26.

| Acción | Estado |
|---|---|
| Abrir panel | implemented_partial |
| Crear | implemented_partial |
| Leer | implemented_partial |
| Actualizar | implemented_partial |
| Eliminar | implemented_partial |
| Enviar formulario | implemented_partial |
| Confirmar | implemented_partial |
| Cancelar | implemented_partial |
| Archivar | implemented_partial |
| Restaurar | implemented_partial |
| Reclamar | implemented_partial |
| Aprobar | implemented_partial |
| Rechazar | implemented_partial |
| Notificar | documented_only |
| Exportar | documented_only |
| Subir archivo | implemented_partial |
| Descargar | implemented_partial |
| Activar | implemented_partial |
| Renovar | implemented_partial |
| Cambiar a plan inferior | implemented_partial |
| Reprogramar cita | implemented_partial |
| Bloquear horario | implemented_partial |
| Buscar | implemented_partial |
| Filtrar | implemented_partial |
| Marcar leída | implemented_partial |
| Generar documento | implemented_partial |
| Firmar | implemented_partial |
| Replicar documento | implemented_partial |
| Finalizar encuentro | implemented_partial |

Los estados técnicos no se consolidan automáticamente con estados de producto o suscripción.

## 15. Inventario de APIs

| Dispatcher | Rutas agrupadas |
|---|---|
| api/agenda/index.php | 65 |
| api/catalog/index.php | 2 |
| api/clinical-documents.php | 2 |
| api/clinical/index.php | 52 |
| api/evolution-note-generate.php | 1 |
| api/hospital-stays.php | 2 |
| api/patients/index.php | 9 |
| api/profiles/index.php | 8 |
| api/subscriptions/index.php | 22 |
| api/verify-password.php | 1 |
| api/verify-sms.php | 1 |
| infra/aws/runtime/app/health/readyz.php | 1 |

| Método | Total |
|---|---|
| DELETE | 1 |
| GET | 64 |
| PATCH | 14 |
| POST | 77 |
| PUT | 10 |

Lecturas: 64; escrituras: 101; webhooks: 1; fixtures/dev: 10; health/version: 3.
Las rutas parametrizadas se agrupan y no contienen IDs concretos. Auth/scope/plan se preservan como `requires_specialized_audit` cuando el dispatcher no permite concluir por ruta sin ejecución.

## 16. Inventario de controladores/servicios/repositorios

| Capa | Total | Candidatos sin caller simple |
|---|---|---|
| Controladores | 27 | 0 |
| Servicios | 28 | 0 |
| Repositorios | 33 | 0 |

La ausencia de caller por búsqueda simple sólo genera candidato; no se declara código muerto.

## 17. Inventario de datos

Se localizaron 47 tablas y 0 vistas en 37 fuentes SQL seguras/versionadas.

| Clasificación | Total |
|---|---|
| account | 11 |
| audit | 4 |
| clinical | 8 |
| operational | 11 |
| payment | 9 |
| public | 2 |
| temporary | 2 |

| ID | Entidad | Clase | Fuente |
|---|---|---|---|
| INV-DAT-001 | agenda_appointment_events | audit | modules/agenda/db/ready_schema.sql:32 |
| INV-DAT-002 | agenda_appointments | operational | modules/agenda/db/ready_schema.sql:6 |
| INV-DAT-003 | agenda_availability_overrides | operational | modules/agenda/db/availability_overrides_min.sql:6 |
| INV-DAT-004 | agenda_operator_audit_events | audit | modules/agenda/db/operators_phase1.sql:60 |
| INV-DAT-005 | agenda_operator_permissions | operational | modules/agenda/db/operators_phase1.sql:48 |
| INV-DAT-006 | agenda_operators | operational | modules/agenda/db/operators_phase1.sql:7 |
| INV-DAT-007 | agenda_patient_flags | account | modules/agenda/db/ready_schema.sql:56 |
| INV-DAT-008 | agenda_patient_incidents | account | modules/agenda/db/ready_schema.sql:72 |
| INV-DAT-009 | agenda_public_appointment_flows | temporary | modules/agenda/db/public_booking_p3.sql:65 |
| INV-DAT-010 | agenda_public_otp_requests | temporary | modules/agenda/db/public_appointments_otp_requests.sql:5 |
| INV-DAT-011 | agenda_settings | operational | modules/agenda/db/agenda_settings_schema.sql:4 |
| INV-DAT-012 | agenda_waitlist_entries | operational | modules/agenda/db/ready_schema.sql:87 |
| INV-DAT-013 | appointment_events | audit | database/agenda/02_create_appointment_events.sql:2 |
| INV-DAT-014 | appointments | operational | database/agenda/01_create_appointments.sql:2 |
| INV-DAT-015 | catalog_cp_colonias | public | modules/catalog/db/catalog_cp_colonias_schema.sql:1 |
| INV-DAT-016 | clinical_case_items | clinical | modules/clinical/db/schema_v2.sql:123 |
| INV-DAT-017 | clinical_cases | clinical | modules/clinical/db/schema_v2.sql:104 |
| INV-DAT-018 | clinical_consents | clinical | modules/clinical/db/schema_v1.sql:73 |
| INV-DAT-019 | clinical_encounters | clinical | modules/clinical/db/schema_v2.sql:140 |
| INV-DAT-020 | clinical_patients | clinical | modules/clinical/db/schema_v1.sql:21 |
| INV-DAT-021 | clinical_record_entries | clinical | modules/clinical/db/schema_v1.sql:45 |
| INV-DAT-022 | consultorio_schedule | operational | modules/agenda/db/availability_bootstrap_min.sql:4 |
| INV-DAT-023 | consultorios | operational | modules/agenda/db/consultorios_schema.sql:4 |
| INV-DAT-024 | doctor_contact_points | account | modules/profiles/db/doctor_contact_points_schema.sql:14 |
| INV-DAT-025 | doctor_identity_map | account | modules/agenda/db/doctor_identity_map_schema.sql:4 |
| INV-DAT-026 | hospital_stays | clinical | modules/clinical/db/hospital_stays_minimal_schema.sql:22 |
| INV-DAT-027 | medical_group_memberships | operational | modules/agenda/db/medical_groups_schema.sql:33 |
| INV-DAT-028 | medical_group_review_log | audit | modules/agenda/db/medical_groups_schema.sql:53 |
| INV-DAT-029 | medical_groups | operational | modules/agenda/db/medical_groups_schema.sql:8 |
| INV-DAT-030 | patient_flags | account | database/agenda/03_create_patient_flags.sql:2 |
| INV-DAT-031 | patients_addresses | account | modules/patients/db/ready_schema.sql:45 |
| INV-DAT-032 | patients_consents | clinical | modules/patients/db/ready_schema.sql:70 |
| INV-DAT-033 | patients_contacts | account | modules/patients/db/ready_schema.sql:32 |
| INV-DAT-034 | patients_doctor_links | account | modules/patients/db/ready_schema.sql:84 |
| INV-DAT-035 | patients_patients | account | modules/patients/db/ready_schema.sql:4 |
| INV-DAT-036 | patients_profiles | account | modules/patients/db/ready_schema.sql:17 |
| INV-DAT-037 | profile_subscriptions | payment | modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql:83 |
| INV-DAT-038 | profiles_doctors | account | modules/profiles/db/profiles_doctors_schema.sql:5 |
| INV-DAT-039 | public_profile_seo_routes | public | modules/profiles/db/2026_06_19_create_public_profile_seo_routes.sql:4 |
| INV-DAT-040 | subscription_checkout_intents | payment | modules/profiles/db/2026_06_22_create_subscription_checkout_intents.sql:15 |
| INV-DAT-041 | subscription_contract_acceptances | payment | modules/profiles/db/2026_06_20_create_subscription_contract_acceptances.sql:51 |
| INV-DAT-042 | subscription_payment_events | payment | modules/profiles/db/2026_06_22_create_subscription_checkout_intents.sql:123 |
| INV-DAT-043 | subscription_payment_intents | payment | modules/profiles/db/2026_06_22_create_subscription_checkout_intents.sql:81 |
| INV-DAT-044 | subscription_payment_routes | payment | modules/profiles/db/2026_07_08_create_subscription_payment_routes.sql:9 |
| INV-DAT-045 | subscription_plan_prices | payment | modules/profiles/db/2026_06_22_create_subscription_plan_prices.sql:18 |
| INV-DAT-046 | subscription_plans | payment | modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql:57 |
| INV-DAT-047 | subscription_write_idempotency_keys | payment | modules/profiles/db/2026_06_22_create_subscription_write_idempotency_keys.sql:53 |

No se ejecutó `DESCRIBE` ni consulta alguna.

## 18. Roles y ownership

| ID | Rol | Estado |
|---|---|---|
| INV-ROL-001 | public | implemented_partial |
| INV-ROL-002 | doctor | implemented_partial |
| INV-ROL-003 | agenda_operator | implemented_partial |
| INV-ROL-004 | medical_group_member | implemented_partial |
| INV-ROL-005 | administrator | implemented_partial |
| INV-ROL-006 | moderator | documented_only |
| INV-ROL-007 | patient_secure_link | implemented_partial |
| INV-ROL-008 | qa_local | implemented_partial |
| INV-ROL-009 | system_webhook | implemented_partial |

| ID | Ownership | Estado |
|---|---|---|
| INV-OWN-001 | unclaimed | documented_only |
| INV-OWN-002 | claimed | implemented_partial |
| INV-OWN-003 | disputed | documented_only |
| INV-OWN-004 | suspended | documented_only |
| INV-OWN-005 | transferred | documented_only |
| INV-OWN-006 | revoked | documented_only |

La matriz rol→dominio→entidad permanece pendiente de PG-02/PG-08.

## 19. Planes/capacidades/cuotas

Fuentes de plan localizadas: 14; capabilities explícitas del read-model público: 41; términos de cuota/límite con evidencia: 2.

Se localizaron `free`, `basic`, `standard`, `optimum`, `professional` y aliases en fuentes múltiples. El inventario no selecciona una matriz canónica ni decide beneficios.

## 20. Eventos

| Tipo | Total |
|---|---|
| explicit | 7 |
| implicit_state_change | 4 |
| dom | 3 |
| proposed_trigger | 10 |

| ID | Evento | Estado | Madurez |
|---|---|---|---|
| INV-EVT-001 | appointment_state_changed | implemented_partial | implemented_or_implicit |
| INV-EVT-002 | appointment_created | implemented_partial | implemented_or_implicit |
| INV-EVT-003 | appointment_cancelled | implemented_partial | implemented_or_implicit |
| INV-EVT-004 | waitlist_assigned | implemented_partial | implemented_or_implicit |
| INV-EVT-005 | operator_audit_event | implemented_partial | implemented_or_implicit |
| INV-EVT-006 | payment_provider_event | implemented_partial | implemented_or_implicit |
| INV-EVT-007 | stripe_webhook_received | implemented_partial | implemented_or_implicit |
| INV-EVT-008 | subscription_activated | implemented_partial | implemented_or_implicit |
| INV-EVT-009 | clinical_case_updated | implemented_partial | implemented_or_implicit |
| INV-EVT-010 | clinical_encounter_finalized | implemented_partial | implemented_or_implicit |
| INV-EVT-011 | clinical_document_created | implemented_partial | implemented_or_implicit |
| INV-EVT-012 | notification_dom_click | implemented_partial | implemented_or_implicit |
| INV-EVT-013 | navigation_popstate | implemented_partial | implemented_or_implicit |
| INV-EVT-014 | embed_message | implemented_partial | implemented_or_implicit |
| INV-EVT-015 | claim_submitted | documented_only | documented_required_future |
| INV-EVT-016 | claim_approved | documented_only | documented_required_future |
| INV-EVT-017 | review_reported | documented_only | documented_required_future |
| INV-EVT-018 | profile_incomplete | documented_only | documented_required_future |
| INV-EVT-019 | renewal_30_15_7_days | documented_only | documented_required_future |
| INV-EVT-020 | grace_ending | documented_only | documented_required_future |
| INV-EVT-021 | payment_rejected | documented_only | documented_required_future |
| INV-EVT-022 | suspicious_session | documented_only | documented_required_future |
| INV-EVT-023 | clinical_upload_failed | documented_only | documented_required_future |
| INV-EVT-024 | ai_provider_failed | documented_only | documented_required_future |

## 21. Notificaciones

| ID | Superficie | Persistencia | Estado |
|---|---|---|---|
| INV-NOT-001 | Campana/contador visual | not_found_in_static_scope | implemented_partial |
| INV-NOT-002 | Buzón interno local | localStorage | implemented_partial |
| INV-NOT-003 | Estado leída/no leída local | localStorage | implemented_partial |
| INV-NOT-004 | Mensajes de suscripción | not_found_in_static_scope | implemented_partial |
| INV-NOT-005 | Vista previa de recordatorios Agenda | not_found_in_static_scope | implemented_partial |
| INV-NOT-006 | Envío OTP Agenda | requires_specialized_audit | implemented_partial |
| INV-NOT-007 | Correo SES runtime | requires_specialized_audit | documented_only |
| INV-NOT-008 | Preferencias obligatorias/configurables | requires_specialized_audit | documented_only |

La campana/buzón visual y el estado local no prueban modelo persistente, trigger, entrega, retry ni preferencias.

## 22. Integraciones externas

| ID | Proveedor | Estado | Gate |
|---|---|---|---|
| INV-EXT-001 | Stripe | protected_closed | static |
| INV-EXT-002 | Google Maps / geocoding | implemented_partial | static |
| INV-EXT-003 | WhatsApp link | implemented_partial | static |
| INV-EXT-004 | Telephone link | implemented_partial | static |
| INV-EXT-005 | SES/email | requires_runtime_verification | runtime |
| INV-EXT-006 | OpenAI/future AI | documented_only | static |
| INV-EXT-007 | AWS runtime | runtime_gate | runtime |
| INV-EXT-008 | SEPOMEX catalog | implemented_partial | static |
| INV-EXT-009 | Frontend CDN libraries | requires_runtime_verification | runtime |
| INV-EXT-010 | FullCalendar | frontend_only | static |
| INV-EXT-011 | Leaflet | frontend_only | static |

Stripe permanece protegido; ninguna integración fue contactada.

## 23. Documentación

Documentos Markdown y contratos JSON inventariados: 117.

| Estado inferido | Total |
|---|---|
| contrato | 20 |
| deuda | 2 |
| histórico | 4 |
| vigente | 72 |
| índice | 19 |

La clasificación es preliminar por ruta/título; no modifica documentos ni declara superseded sin auditoría.

## 24. Tests

Superficies de test/QA/mock inventariadas: 119; ejecutadas: 0.

| Tipo | Total |
|---|---|
| fixture | 21 |
| integration | 11 |
| static | 21 |
| unit | 66 |

La presencia de una suite no implica cobertura semántica.

## 25. Flujos transversales

### INV-FLW-001 — Navegación pública → perfil

`screen → action → endpoint → controller → repository → data`

Aristas: `confirmed, confirmed, confirmed, confirmed, confirmed`. Confianza: `high`. Faltantes: ninguno estático.

### INV-FLW-002 — Perfil → reclamo

`screen → action → endpoint`

Aristas: `confirmed, missing`. Confianza: `low`. Faltantes: action→endpoint.

### INV-FLW-003 — Registro/login/recuperación

`screen → action → endpoint → service → data`

Aristas: `confirmed, probable, missing, missing`. Confianza: `low`. Faltantes: endpoint→service, service→data.

### INV-FLW-004 — Edición de perfil

`screen → action → endpoint → controller → repository → data`

Aristas: `confirmed, confirmed, confirmed, confirmed, runtime_verification_required`. Confianza: `medium`. Faltantes: ninguno estático.

### INV-FLW-005 — Agenda pública → cita

`screen → action → endpoint → controller → repository → event → notification`

Aristas: `confirmed, confirmed, confirmed, probable, probable, documented_only`. Confianza: `medium`. Faltantes: event→notification.

### INV-FLW-006 — Agenda privada → paciente

`screen → action → endpoint → service → data`

Aristas: `confirmed, confirmed, probable, runtime_verification_required`. Confianza: `medium`. Faltantes: ninguno estático.

### INV-FLW-007 — Paciente → expediente

`screen → action → endpoint → data`

Aristas: `confirmed, confirmed, runtime_verification_required`. Confianza: `medium`. Faltantes: ninguno estático.

### INV-FLW-008 — Expediente → receta

`screen → action → endpoint → data → document`

Aristas: `confirmed, probable, probable, confirmed`. Confianza: `medium`. Faltantes: ninguno estático.

### INV-FLW-009 — Upload → documento

`screen → action → endpoint → data → audit`

Aristas: `confirmed, confirmed, runtime_verification_required, missing`. Confianza: `low`. Faltantes: data→audit.

### INV-FLW-010 — Comentario → moderación

`screen → action → endpoint → service → notification`

Aristas: `confirmed, documented_only, missing, missing`. Confianza: `low`. Faltantes: action→endpoint, endpoint→service, service→notification.

### INV-FLW-011 — Suscripción → pago → activación

`screen → action → endpoint → service → repository → data → event → provider`

Aristas: `confirmed, confirmed, confirmed, confirmed, confirmed, confirmed, runtime_verification_required`. Confianza: `medium`. Faltantes: ninguno estático.

### INV-FLW-012 — Evento → notificación

`event → notification → delivery`

Aristas: `probable, missing`. Confianza: `low`. Faltantes: notification→delivery.

### INV-FLW-013 — Cambio de plan → capabilities

`endpoint → service → capability → screen`

Aristas: `confirmed, confirmed, probable`. Confianza: `medium`. Faltantes: ninguno estático.

### INV-FLW-014 — Downgrade → datos existentes

`service → capability → data → screen`

Aristas: `confirmed, documented_only, missing`. Confianza: `low`. Faltantes: capability→data, data→screen.

### INV-FLW-015 — Professional → IA

`capability → screen → endpoint → provider`

Aristas: `confirmed, documented_only, missing`. Confianza: `low`. Faltantes: screen→endpoint, endpoint→provider.

## 26. Candidatos a desconexión

| ID | Categoría | Candidato | Grupo |
|---|---|---|---|
| INV-GAP-001 | navigation_without_screen | CTA de reclamo sin destino | G2 |
| INV-GAP-002 | navigation_without_screen | Sugerir corrección sin destino | G6 |
| INV-GAP-003 | documentation_without_code | Login productivo no localizado en dispatch principal | G2 |
| INV-GAP-004 | documentation_without_code | Recuperación de cuenta sin endpoint productivo confirmado | G2 |
| INV-GAP-005 | screen_without_endpoint | Buzón visual sin persistencia backend confirmada | G4 |
| INV-GAP-006 | screen_without_endpoint | Reseñas públicas sin repositorio localizado | G6 |
| INV-GAP-007 | documentation_without_code | Preferencias de notificación sin API localizada | G4 |
| INV-GAP-008 | documentation_without_code | IA profesional sin proveedor/endpoint localizado | G8 |
| INV-GAP-009 | conflicting_plan_source | Gating de plan distribuido entre fuentes | G1 |
| INV-GAP-010 | backend_gate_without_ux_explanation | Auth privado transicional | G2 |
| INV-GAP-011 | endpoint_without_service | Clinical gateway monolítico con capas internas no uniformes | G7 |
| INV-GAP-012 | runtime_only_unknown | Readiness productivo pendiente | G7 |
| INV-GAP-013 | navigation_without_screen | Retorno Stripe productivo ausente | G5 |
| INV-GAP-014 | runtime_only_unknown | Maps/CSP legacy pendiente | G6 |
| INV-GAP-015 | data_entity_without_known_repository | Entidades SQL duplicadas legacy/canónicas | G7 |

Todos permanecen `candidate_orphan`; requieren resolución especializada y no se presentan como hechos definitivos.

## 27. Crosswalk de deuda

Cobertura: **92/92**. Sin superficie implementada confirmable: 12. Mapeos múltiples: 80. Registro maestro modificado: **no**.

| Prefijo de deuda | Entradas |
|---|---|
| ADM | 2 |
| AGD | 5 |
| AI | 2 |
| AUTH | 4 |
| CAP | 10 |
| CLN | 6 |
| DATA | 4 |
| DOC | 5 |
| NOT | 5 |
| OWN | 3 |
| PAT | 3 |
| PRIV | 2 |
| PUB | 5 |
| QA | 4 |
| REV | 3 |
| RUNTIME | 13 |
| RX | 2 |
| SUB | 4 |
| TECH | 3 |
| UX | 7 |

El detalle deuda→INV→evidencia→grupo está en `debt-to-inventory-crosswalk.json`.

## 28. Contradicciones y preguntas

| ID | Tema | Clasificación |
|---|---|---|
| DIV-001 | Claim CTA documented but destination disabled | partial |
| DIV-002 | Notifications visual/local model versus required persistent model | partial |
| DIV-003 | Reviews visible with no persistence layer localized | candidate |
| DIV-004 | Plan sources distributed across profile and subscription layers | candidate |
| DIV-005 | AWS offline closure does not imply deployment | protected |

Preguntas rectoras: ¿qué fuente de plan es canónica?, ¿dónde se obliga auth/ownership por entidad?, ¿qué eventos tienen consumidor/delivery?, ¿qué rutas clinical requieren capa canónica?, ¿qué documentos históricos siguen operativos?

## 29. Grupos oficiales del ciclo

| ID | Nombre | Tamaño | Actividades probables | Dependencias |
|---|---|---|---|---|
| PG-01 | Planes, capabilities, ownership, ciclo de acceso y separación de permisos internos | muy grande | 2 | ninguna |
| PG-02 | Identidad, reclamo, sesiones, seguridad y lifecycle de operadores internos | muy grande | 2 | PG-01 |
| PG-03 | Agenda y contactos de pacientes | muy grande | 2 | PG-02 |
| PG-04 | Expediente, recetas, consentimiento y archivos | muy grande | 3 | PG-02, PG-03, PG-08 |
| PG-05 | Buzón, eventos, preferencias, delivery y colas internas | grande | 2 | PG-02, PG-08 |
| PG-06 | Suscripciones, pago, activación, bloqueo y operación controlada | grande | 2 | PG-01, PG-02 |
| PG-07 | Perfil público, reseñas, media, Maps y SEO | grande | 2 | PG-01, PG-02 |
| PG-08 | APIs, datos, RBAC/scopes, permisos, logs, auditoría y privacidad | muy grande | 3 | PG-02 |
| PG-09 | Visual, responsive, accesibilidad, ayuda y UX de consola | muy grande | 2 | PG-01, PG-02, PG-03, PG-04 |
| PG-10 | Consola operativa, administración, soporte, moderación y gobierno de plataforma | mediano | 1 | PG-02, PG-08 |
| PG-11 | IA, voz y degradación de proveedor | mediano | 1 | PG-01, PG-08 |

Cambios respecto de G1–G8: G3 se divide en Agenda/Pacientes y Clínico; G8 se divide en UX, Administración e IA. G7 se mantiene transversal y se adelanta después de identidad. No se fusionan grupos. El amendment del plano de control agrega a PG-01 separación plan/rol/ownership/operator permissions, jerarquía y riesgo; a PG-02 invitación/MFA/login/sesiones/suspensión/revocación; a PG-05 colas/tareas/aprobaciones; a PG-06 operación y override R3; a PG-08 RBAC/scopes/caso/masking/audit/access reviews; a PG-09 UX accesible de consola; y a PG-10 los 12 módulos, personal, soporte, reclamos, moderación, privacidad, acciones críticas y dirección.

## 30. Contador oficial

Contador principal aprobado: **1/22 actividades completadas**, distribuidas en 11 grupos.

La Actividad 1 de 22 concluyó en PP276. Esta reconciliación histórica auxiliar no incrementa el contador, no pertenece a AWS y no crea Microfase 25. La Actividad 2 permanece bloqueada hasta aprobar el paquete directoral revisado.

## 31. Orden recomendado

| Orden | Grupo | Nombre |
|---|---|---|
| 1 | PG-01 | Planes, capabilities, ownership, ciclo de acceso y separación de permisos internos |
| 2 | PG-02 | Identidad, reclamo, sesiones, seguridad y lifecycle de operadores internos |
| 3 | PG-08 | APIs, datos, RBAC/scopes, permisos, logs, auditoría y privacidad |
| 4 | PG-03 | Agenda y contactos de pacientes |
| 5 | PG-04 | Expediente, recetas, consentimiento y archivos |
| 6 | PG-06 | Suscripciones, pago, activación, bloqueo y operación controlada |
| 7 | PG-05 | Buzón, eventos, preferencias, delivery y colas internas |
| 8 | PG-07 | Perfil público, reseñas, media, Maps y SEO |
| 9 | PG-09 | Visual, responsive, accesibilidad, ayuda y UX de consola |
| 10 | PG-10 | Consola operativa, administración, soporte, moderación y gobierno de plataforma |
| 11 | PG-11 | IA, voz y degradación de proveedor |

El orden coloca planes/capabilities/ownership primero por dependencia transversal; identidad después; y autorización/datos antes de clínica, notificaciones y UX.

## 32. Estado de la siguiente actividad

`PRODUCT-AUDIT/MXMed-Plans-Capabilities-Ownership-Lifecycle-Audit-01` concluyó como Actividad 1 de 22. La siguiente actividad principal es `PRODUCT-IMPLEMENTATION/MXMed-Plans-Capabilities-Ownership-Lifecycle-Implementation-01`, Actividad 2 de 22, pero permanece bloqueada. Antes de iniciarla, dirección debe aprobar el paquete revisado derivado de la reconciliación histórica. Stripe/AWS continúan protegidos.

## 33. OPERATOR CONTROL PLANE REQUIREMENT AMENDMENT

**Contrato:** `MXMED_OPERATOR_CONTROL_PLANE_REQUIREMENTS_V1`
**Fecha:** 2026-07-17
**Requerimiento:** la dirección necesita un plano interno gobernado para administrar personal, soporte, reclamos, moderación, facturación operativa, privacidad, auditoría, incidentes y recuperación.
**Impacto:** transversal en PG-01/02/05/06/08/09/10; contador `0/22` sin cambio.

### implemented_inventory_totals

Los totales de secciones 7–24 permanecen iguales: 953 entradas, 143 superficies, 166 APIs y 47 entidades versionadas. El amendment no cuenta módulos futuros como pantallas, endpoints o tablas implementados.

Evidencia actual relacionada:

- `operator` y `assistant` son roles funcionales de Agenda en el plano customer/professional;
- `administrator` y `moderator` son candidatos o referencias que requieren auditoría;
- `use_for_platform_admin` es un propósito de contacto, no un rol;
- los roles AWS de break-glass/audit pertenecen a infraestructura, no al producto;
- `p-ag-operadores`, ocho rutas `/operators`, siete rutas `medical-groups` y seis entidades relacionadas son parciales y específicas de dominio;
- no se confirmó consola integral, login interno, MFA administrativo, API administrativa transversal, case management de soporte, doble aprobación, sesiones asistidas, colas internas ni auditoría administrativa inmutable.

### required_future_control_plane_surfaces

Se agregan como requisitos futuros, no como inventario implementado, 12 módulos: Operations home; Users and accounts; Profile claims and ownership; Subscriptions and billing; Support cases; Content moderation; Notification operations; Internal staff; Privacy and security; Audit; Technical operations; y Platform configuration.

Roles preliminares requeridos: `platform_director`, `break_glass_superadmin`, `platform_admin`, `operations_manager`, `support_advisor`, `profile_claim_reviewer`, `billing_subscription_operator`, `content_moderator`, `privacy_security_officer`, `technical_operations_viewer` y `audit_read_only`. Sus nombres no son definitivos.

Deudas relacionadas: `ADM-001` a `ADM-008`, `AUTH-004`, `CAP-008`, `DATA-002`, `DOC-006`, `NOT-001` a `NOT-005`, `PRIV-001/002`, `REV-002`, `SUB-002` y `UX-005`. Estado: `documented_required_future`; implementación integral confirmada: no.

Documento canónico: [Requisitos del plano de control de operadores](./MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md).

## 34. HISTORICAL FUNCTIONAL SOURCES RECONCILIATION AMENDMENT

**Contrato:** `MXMED_HISTORICAL_FUNCTIONAL_DOCUMENTS_RECONCILIATION_V1`
**Fecha:** 2026-07-18
**Contador:** `1/22`; Actividad 2 bloqueada.

Ocho PDF y 95 requisitos se registran como `historical_noncanonical`. No se
agregan a las superficies implementadas. Permanecen exactamente iguales:

- entradas inventariadas: 953;
- superficies: 143;
- endpoints/API inventariados: 166;
- tablas/entidades inventariadas: 47;
- pantallas implementadas: 31.

### implemented_inventory

No fue alterado. La reconciliación no confirma endpoints, tablas, eventos,
roles, pantallas, providers IA, canales o flujos runtime por su mención en PDF.
Los 22 triggers son requisitos históricos para PG-05, no eventos actuales
adicionales; esta auditoría confirma 0/22 implementaciones end-to-end. Los
roles históricos son candidatos de crosswalk, no roles implementados.

### historical_documented_requirements

- Agenda puede crear/vincular contacto operativo; no expediente automático.
- Claim requiere request, ownership y publicación como máquinas separadas.
- No delegabilidad, integridad de Recetas y reimpresión auditada pasan a PG-04.
- Los 22 triggers, preferencias y conflicto de email pasan a PG-05.
- Grace D+8 frente a 15 días pasa como conflicto a PG-06.
- Publicación/moderación y backoffice pasan a PG-07/PG-10.
- Seis capabilities IA separadas pasan a PG-11/PG-04/PG-08.

### required_future_surfaces

Se documentan como futuras, sin incrementar totales: claim review documental,
publication moderation queue, manual payment reconciliation, marketing/global
booking scopes, notification delivery operations, attendance risk review y
governed AI tools/dry-run.

### rejected_or_superseded_requirements

- acceso universal cotidiano del superadmin: `superseded`;
- IA con todos los campos: `reject_for_safety`;
- lista negra literal por inasistencia: `reject_for_safety`;
- autoenrollment de padecimientos: `reject_for_safety`;
- lista gris publicada: `requires_specialized_audit`, no inventario.

Documento rector: [Reconciliación histórica funcional](./MXMED_RECONCILIACION_DOCUMENTACION_HISTORICA_FUNCIONAL.md).

## 35. Historial

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0.0 | 2026-07-17 | Inventario estático inicial, crosswalk 92/92 y propuesta no oficial de 11 grupos/22 actividades |
| 1.1.0 | 2026-07-17 | Amendment del plano de control; ciclo oficial 0/22, PG-10 renombrado y requisitos futuros separados de totales implementados |
| 1.2.0 | 2026-07-18 | Reconciliación histórica; requisitos futuros/rechazados separados, totales implementados sin cambio y contador 1/22 |
| 1.3.0 | 2026-07-18 | PP278 registra approved requirements sin inflar pantallas, endpoints, tablas, roles o eventos |

## 36. DIRECTOR DECISION APPROVAL AMENDMENT

**Contrato:** `MXMED_PLANS_CAPABILITIES_OWNERSHIP_LIFECYCLE_DIRECTOR_DECISION_APPROVAL_V1`

Las 30 decisiones aprobadas se registran como `approved_requirement` e
`implementation_ready_contract`; no como inventario implementado.

Totales preservados:

- entradas: 953;
- superficies: 143;
- endpoints/API: 166;
- tablas/entidades: 47;
- pantallas implementadas: 31.

| Requisito aprobado | Clasificación de inventario | Implementado |
|---|---|---:|
| policy, códigos, matriz, estados, cuotas, denials y add-ons | `implementation_ready_contract` | no |
| Call Center, telefonía y WhatsApp operativo | `required_future_surface` | no |
| IA Agenda/fallback/imágenes/redacción | `deferred_specialized_implementation` | no |
| claim/ownership/publicación completos | `approved_requirement` | no |
| doce roles, sessions, lifecycle y platform cases | `required_future_surface` | no |
| retención por clase e interacción medicamentosa | `deferred_specialized_implementation` | no |

No se agregan pantallas, endpoints, tablas, roles, eventos, add-ons, cases,
proveedores o agentes a los totales actuales. Actividad 2 queda
`UNBLOCKED_READY_NOT_STARTED`, contador `1/22`.

Documento rector:
[Aprobación directoral](./MXMED_APROBACION_DECISIONES_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md).

## 37. Delta implementado por PG-01 / Actividad 2

Contrato: `MXMED_PLANS_CAPABILITIES_OWNERSHIP_LIFECYCLE_IMPLEMENTATION_V1`.

| Tipo | Superficie/artefacto | Estado real | Notas |
|---|---|---|---|
| policy | `modules/subscriptions/policy/MxmedPlanCapabilityPolicy.php` | `implemented_core` | cinco planes, 28 capabilities canónicas, crosswalk 41, cuotas, add-ons, estados y denials |
| backend | adapter approval/ownership | `implemented_core` | esquema legacy compatible y fail-closed |
| backend | lifecycle/resolver/read-model builder | `implemented_core` | clock inyectable, once gates, archive/downgrade y compatibilidad |
| API | `GET /api/subscriptions/index.php/entities/{type}/{id}/current` | `extended_existing` | policy, gates, catálogo, estados, cuotas, denials, archive y futuras disabled |
| API | `DELETE /api/subscriptions/index.php/entities/{type}/{id}/scheduled-plan` | `implemented_core` | cancela sólo cambio futuro programado; requiere write context |
| UI | panel `#p-suscripcion` | `extended_existing` | encabezado, comparación, locks, grace, scheduled change, archive y funciones futuras |
| UI adapter | `assets/js/subscription-policy-ui.js` | `implemented_core` | transforma exclusivamente el read-model; 19 mensajes de denial |
| persistencia | migración `2026_07_18_add_plan_capability_policy_v1_fields.sql` | `versioned_not_executed` | aditiva, nullable, sin datos reales |
| QA | dos suites PHP + test frontend | `implemented_core` | 251 aserciones PHP; parser/semántica JS; snapshots sin datos reales |

No se agrega pantalla nueva: se amplía el panel existente. El endpoint current
se cuenta como extendido, no duplicado. Se agrega un endpoint de cancelación
acotado. Las tablas reales no cambian durante esta actividad porque la migración
no se ejecuta. Call Center, IA, consola y notificaciones no se inventarían como
superficies implementadas.

Estado backend↔frontend: `BACKEND_AND_FRONTEND_COMPLETED`. Siguiente grupo:
PG-02, sin iniciarlo desde esta actividad.
