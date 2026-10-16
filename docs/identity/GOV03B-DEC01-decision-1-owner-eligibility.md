# GOV03B-DEC01 — DECISION_1: elegibilidad del primer owner médico

```text
STATUS=SUPERSEDED_IN_PART_BY_GOV_SIM02
DATE=2026-10-16
DECISION=MINIMUM_PHYSICIAN_ONLY_AND_SINGLE_STAFF_APPROVAL_APPROVED_BY_GOV_SIM02
REAL_BENEFICIARY_SELECTED=false
```

**Opción recomendada:** reanudar el único proceso C4/ADM02 para emitir admisión humana vigente por perfil y una vinculación explícita de Identity: cuenta exacta → persona VID01 → acreditación aprobada → perfil/claim exactos. Referenciar CRD02 y evidencia del control de cuenta comprobado de forma atendida; no crear otra revisión médica en GOV03B. Un revisor normal y segundo en excepción/conflicto/transferencia, independientes del beneficiario y con capacidad específica, MFA y reauth.

**Detalle aún pendiente, subordinado a GOV-SIM02:** método atendido de comprobación, fuentes/evidencia privada y autoridad emisora; estados/vigencia/versiones/revocación de admisión y binding; plazo obligatorio de doce meses `DEFERRED_NOT_APPROVED` (la vigencia/revocación de fuentes sigue exigida); clasificación de legado y emisión sin backfill. La [propuesta completa, §3](GOV03B-DEC01-policy-reconciliation.md#3-propuesta-de-elegibilidad-pendiente-de-decision_1) define la proyección sólo de lectura y revalidación en el commit.

**Alternativas:** mantener C4/ADM02 diferido, con owner denegado; o autorizar posteriormente una ceremonia excepcional equivalente, con contrato propio y sin omitir acreditación. GOV-SIM02 rechaza ownership de no médicos; no es alternativa aprobada. VID01/cédula/email/rol coincidentes no son alternativas aceptables de vinculación.

**Consecuencias de seguridad:** no hay autoacreditación ni autoownership; cambian versiones/estado al revocar y se deniegan casos pendientes. GOV-SIM02 exige una aprobación rutinaria de staff explícitamente autorizado y aceptación independiente del médico; no exige dos aprobadores o root para esa rutina. Owner no obtiene firma clínica; CLIN-AUTH02A continúa vigente. Fuentes ausentes, vencidas, revocadas, ambiguas o caídas deniegan.

**Contratos reutilizados:** guía C4 §§5–8/13, ADM01/ADM02, VID01/CRD02, propuesta de binding de CLIN-AUTH02B1 D01–D03/D10, R3 y puerto `InitialOwnerEligibilityPort`, escritor de membresía/auditoría existentes. Se conserva la separación owner/staff/plan/profesión.

**Mínimo faltante:** autoridad efectiva de admisión + relación de binding, capacidad/expediente emisor y revocación, pruebas y migración futura; implementar el adapter de lectura y revalidación en el PDO final. No construir un nuevo maestro de profesionales, revisar documentos por duplicado ni tocar Recetas.

**Autorización humana exacta requerida:** el Director debe ratificar expresamente los detalles anteriores y autorizar separadamente reanudar/implementar C4/ADM02. Después deben aprobarse DDL y target y nombrarse revisores legítimos, fuentes y beneficiario/cuenta/perfil/membresía específicos con evidencia real. Ratificar esta política no efectúa esos nombramientos ni la asignación de owner.

**Decisión posterior registrada:** GOV-SIM02 aprueba el mínimo anterior y sustituye sus incompatibilidades; [implementación y pendientes](GOV-SIM02-physician-ownership-session.md). La denegación runtime por falta de emisor/vinculación y normal-local se conservan; no se aprobaron el plazo obligatorio ni una operación real.
