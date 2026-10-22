# ADVISOR-CAPABILITY-CLOSURE02 — cierre de consumidores y límites

Baseline: `2ef08171641ea86c3069ab9e9c3eab0ff229fe7e`, derivado de
`feature/mxmed-advisor-full-grants-qa01`; entrega en
`feature/mxmed-advisor-capability-closure02`. Verificado 2026-10-22, México.

**RESULT: COMPLETE_SUPPORTED_CAPABILITIES_ISOLATED.** Sucede sólo las brechas de
conexión y la interpretación del resumen de [QA01](ADVISOR-FULL-GRANTS-QA01.md).
La evidencia histórica de sus 14 capacidades no se repitió ni se sustituyó.

- **PRODUCT_POLICY:** la instrucción CLOSURE02 autoriza conectar únicamente
  consumidores cuyo contrato vigente admite delegación Advisor. No modifica
  catálogo, gobierno, ownership médico, operador, clínica ni permisos de Irene.
- **SOURCE_IMPLEMENTATION:** reutiliza pantalla/JS/API de acreditación y fuentes,
  `PhysicianEvidenceService`, `SourceProvisioningService`, VID01/CRD02 y el writer
  `HealthcareOrganizationClaimService`. Añade sólo composición aislada, entrada
  de navegación, adaptador de claims de organizaciones y comprobación de ámbito.
- **CONNECTIVITY:** 17 capacidades dentro de alcance conectadas: 14 previas y
  tres nuevas. Las entradas superiores de revisiones son independientes de la
  selección del médico. La cola profesional conserva su selección propia; no se
  presume que corresponde al médico abierto. Los claims pertenecen a organizaciones.
- **VERIFICATION:** [evidencia dirigida](../evidence/advisor-capability-closure02/README.md),
  33 aserciones con nombres distintos, incluyendo tres denegaciones nativas,
  más reconciliación de auditorías, hashes originales, navegación y sintaxis.
  Ensayo reanudado: no se presenta como una ejecución ininterrumpida.
- **OPERATIONAL_STATUS:** sólo servidor desechable propio, HTTPS loopback, bases,
  claves y documentos artificiales. Leticia se consulta mediante el lector
  original restringido existente; por ello el entorno combinado no es enteramente
  sintético. Sin activación normal-local/producción, merge ni cambios en 8091.

## Inventario sin doble conteo

[Matriz de los 26 IDs canónicos](../evidence/advisor-capability-closure02/capability-matrix.json).
Las cuatro clases exclusivas suman **17 + 4 + 4 + 1 = 26**:

| Clase exclusiva | Cantidad | Significado |
|---|---:|---|
| Operativas delegables dentro del alcance | 17 | 14 conectadas antes + tres con backend existente, ahora conectadas |
| Reservadas para este actor | 4 | `internal_advisors_manage`, `profile_initial_ownership_approve`, `public_profile_correction_review`, `public_profile_correction_apply` |
| Sin consumidor Advisor compatible | 4 | `profile_create`, `profile_curate`, `profile_publication_manage`, `profile_support_assist` |
| Comercial fuera del alcance | 1 | `provider_subscription_activate`: implementada, no obsoleta ni técnicamente incompatible |

El catálogo declara24 entradas operational/delegable; dos correcciones limitadas por rol y cuatro genéricas sin consumidor no acreditan delegación efectiva al Advisor. Hay18 capacidades con backend compatible si se incluye la comercial excluida. “14 conectadas”
era un subconjunto de las 17, no una clase adicional. Las cuatro genéricas sin
consumidor no son las tres A desconectadas. “Una incompatible” significaba
inaplicable al ensayo comercialmente restringido, no ausencia de implementación.
**Cero capacidades demostradas obsoletas.** Las correcciones públicas aparecen
como `delegable=true` en el catálogo, pero sus consumidores exigen Director/Master;
ese campo por sí solo no constituye autorización efectiva para un Advisor.

No se conceden capacidades reservadas, genéricas incompletas ni la comercial.
Completar las genéricas exigiría un contrato/consumidor autorizado específico;
no se equiparan a resolver una solicitud canónica. Delegar correcciones públicas
exigiría una decisión explícita de producto que cambie su autoridad: no se hace.
Ninguna de esas decisiones es necesaria para las tres integraciones presentes.

## Contratos comprobados

| Capacidad | Servicio y autoridad existente | Evidencia, estado, MFA y auditoría | Conexión realizada |
|---|---|---|---|
| `professional_source_provision` | `SourceProvisioningService` / `EvidenceAuthority::sourceReviewer`: Director, Master o Advisor ACTIVE, credencial vigente y grant exacto; cohorte cuenta/perfil; sin autorrevisión | Intake SUBMITTED y perfil FREE elegible sin dueño/binding incompatible. COLLECT → dos documentos cifrados → lectura efectiva → AUTHORIZE con hechos humanos tipados → EXECUTE. Reauth y MFA por caso/operación, manifiesto/versiones exactas; journal, `EvidenceAudit`, VID01/CRD02 originales. No consulta real de registro oficial | Entrada Advisor a la pantalla original, composición owned con scope explícito. COLLECT por UI; autorización/ejecución por el mismo HTTP canónico. Sin nuevo writer |
| `physician_accreditation_review` | `PhysicianEvidenceService` / `EvidenceAuthority::reviewer`; staff ACTIVE y grant exacto, Advisor permitido. Enrollment usa `ReviewedEnrollmentService` en la misma pantalla | Expediente enviado, teléfono/fuentes/versiones válidos, cuenta/perfil exactos, no autorrevisión. Lectura de ambos documentos, reauth/MFA y decisión humana. `EvidenceAudit`/caso/proof; confirmación del solicitante e issuer siguen independientes | Pantalla y formularios originales, decisión APPROVED por UI del Advisor; no emite autoridad profesional ni ownership automáticamente |
| `provider_claim_review` | `HealthcareOrganizationClaimService`: cuenta activa, staff ACTIVE y grant exacto; contrato existente permite Advisor. Primer claim de organización proveedora, nunca médico | Claim PENDING, tipo de organización admitido, no autorrevisión ni propietario activo incompatible. El contrato almacena `evidence_type` y razón enumerada; no incluye visor ni certificación documental, ni exige MFA adicional. No se fabrica ese requisito. Decisión y owner de **organización** en transacción; journal `healthcare_organization_claim_events`, distinto de plataforma | Panel mínimo separado; `ProviderClaimReview` exige sesión actual, grant y allowlist exacta de claims. Reutiliza `readForReview/decide`. APPROVED/REJECTED reales en fixtures; conserva un claim pendiente |

La autorización de fuentes no concede revisión C4, ni a la inversa. Se verificó
retirando cada grant con la misma sesión Advisor y conservando Media. Los tres
consumidores continúan denegando tras revocación. La concesión/revocación usa
`InternalGovernanceService` desde la UI Director y su auditoría original.
No se insertaron grants/admisiones de los nuevos asesores con SQL de fixture.

### Corrección de ámbito detectada

`SourceProvisioningService::statusInTransaction` devolvía `NOT_REQUESTED` antes de
comprobar el perfil cuando aún no existía colección. Una cuenta dentro de la
cohorte podía obtener ese estado de un perfil excluido. `origin()` ahora comprueba
`original_profile_doctor_id` antes de cualquier proyección; el perfil resuelto de
solicitudes sin perfil inicial sigue comprobándose en `context()`. El caso
reproducido pasa de permitir ese estado a **403**, y el expediente permitido sigue
consultable. No se alteran los writers ni las capacidades.

## Resumen: revocación por campos

La prueba usa un Advisor nuevo con **Media read + summary**, sin Request read.
Se conserva exactamente la misma cookie de sesión al revocar summary por UI.
No fue necesario cambiar `PendingSummary`: su filtrado existente era correcto.

| Campo | Antes | Después |
|---|---|---|
| `summary_authorized` | true | false |
| `context.sections.requests` | `{visible_count:1, actionable_count:0, summary_only:true}` | Ausente |
| `context.sections.images` | `{visible_count:4, actionable_count:0, summary_only:false}` | Idéntico |
| `context.visible_count` | 5 | 4 |
| `visible_unresolved_count` | 9 | 8 |
| `context.permissions.requests` | false | false |
| Datos básicos del médico autorizado | Disponibles | Iguales; HTTP200 válido |

El filtrado también se refleja en `groups`/`awareness_groups` y sus agregados.
No aparecen datos del solicitante ni documentos por el grant de resumen.
Requests directo y pending por application ID responden403; un médico fuera del
ámbito responde403. Media continúa autorizado. Con ambos permisos de lectura
básica legítimos, sus conteos no tienen por qué desaparecer: son autoridades
independientes. `UNAUTHORIZED_FIELD_DISCLOSURE=false` para los campos del resumen
probados; la brecha de estado de fuentes anterior se documenta y corrige aparte.

## Revisión y preservación

Instancia: `/tmp/mxmed-ase-adm02imp01-83vg864p`.
Director: `https://127.0.0.1:54400/internal/media-review/index.php?section=equipo`.
Advisor: `https://127.0.0.1:54400/internal/staff-workspace/index.php`.
Caduca **2026-10-22 23:56:19 -06:00, America/Mexico_City**. Sesiones y MFA
conservan sus vencimientos normales; no se renuevan pruebas para aparentar login permanente.
Chromium abrió dos contextos independientes con login Identity legítimo.

Cuentas: `qa_director@example.test` y `full_grants_closure@example.test`.
El nuevo demo conserva17; el actor del resumen conserva únicamente Media read.
La instancia QA01 anterior conserva demo14 y actor revocado0, sin cambios.

Desde cualquier directorio:

```sh
REVIEW='/Users/circulodigital/Documents/GitHub/mxmed-advisor-capability-closure02/scripts/advisor-capability-closure02-review.py'
INSTANCE='/tmp/mxmed-ase-adm02imp01-83vg864p'
python3 "$REVIEW" "$INSTANCE" open
python3 "$REVIEW" "$INSTANCE" credentials-director
python3 "$REVIEW" "$INSTANCE" mfa-director
python3 "$REVIEW" "$INSTANCE" credentials-advisor
python3 "$REVIEW" "$INSTANCE" mfa-advisor
python3 "$REVIEW" "$INSTANCE" stop
```

Contraseñas y códigos se copian al portapapeles, no al reporte. `stop` sólo señala
al runner propietario. El launcher reutilizado conserva verificación de dueño,
branch/HEAD, hashes, expiración y listener loopback; su parametrización mantiene
los defaults QA01 y un namespace distinto para esta revisión.

Los listeners y worktrees protegidos, incluido8091, coinciden con el inicio.
Los cuatro hashes acotados del médico original coinciden con la evidencia previa
UX05. No se exportan filas personales. **Diferencia observada:** el puntero antiguo
`/tmp/mxmed-ase-int01-owned.json` pasó de hash a ausente durante la tarea. No fue
modificado por estas operaciones; causa no atribuida. No se declara igualdad de
ese puntero ni se lo reconstruye. Esta diferencia no afecta la igualdad comprobada
de listeners/worktrees originales. Aceptación visual Director: **PENDING**.

## CTX-SYNC01

- CONTEXT_BASELINE: `2ef08171641ea86c3069ab9e9c3eab0ff229fe7e`.
- AFFECTED_CAPABILITY_IDS: las tres integradas y `physician_pending_summary_read`; matriz de inventario reconciliada, catálogo sin cambios.
- AFFECTED_JOURNEY_IDS: J01/J02; ID-04/05/07, ORG-01 y COM-10.
- AFFECTED_DATA_OBJECTS: expedientes/fuentes/evidencia C4, grants/proofs/audit, claims/journal/membership organizacional y proyección PendingSummary. Sin nuevo esquema de producto.
- AFFECTED_CONTEXT_FILES: este contrato, estado actual, router, inventario funcional, trazabilidad de asesores, interacciones, ACT03 y marcador sucesor QA01.
- CONTEXT_CHANGES_IMPLEMENTED: separa política, implementación, conexión, QA y estado; matriz exclusiva y semántica200 de resumen; preserva evidencia anterior.
- CONTEXT_VERIFICATION: enlaces/paths, catálogo26, diff, evidencia dirigida, hashes y revisión de autoridad.
- CONTEXT_SYNC_STATUS: PASS_UPDATED.
- DOCUMENTATION_COMMIT: commit de esta entrega (`git log -1 --format=%H -- docs/identity/ADVISOR-CAPABILITY-CLOSURE02.md`).
- REMAINING_CONTEXT_GAPS: aceptación visual pendiente; cuatro genéricas sin consumidor; facultades reservadas/comerciales fuera de alcance; puntero antiguo sin causa atribuida.
