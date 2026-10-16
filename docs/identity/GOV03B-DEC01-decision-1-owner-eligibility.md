# GOV03B-DEC01 — DECISION_1: elegibilidad del primer owner médico

```text
STATUS=PROPOSED_NOT_RATIFIED
DATE=2026-10-16
DECISION=NOT_TAKEN
REAL_BENEFICIARY_SELECTED=false
```

**Opción recomendada:** reanudar el único proceso C4/ADM02 para emitir admisión humana vigente por perfil y una vinculación explícita de Identity: cuenta exacta → persona VID01 → acreditación aprobada → perfil/claim exactos. Referenciar CRD02 y evidencia del control de cuenta comprobado de forma atendida; no crear otra revisión médica en GOV03B. Un revisor normal y segundo en excepción/conflicto/transferencia, independientes del beneficiario y con capacidad específica, MFA y reauth.

**Detalle nuevo por ratificar:** método atendido de comprobación, fuentes/evidencia privada y autoridad emisora; estados/vigencia/versiones/revocación de admisión y binding; revisión máxima propuesta de doce meses o vencimiento anterior de fuente, sin gracia; clasificación de legado y emisión sin backfill. La [propuesta completa, §3](GOV03B-DEC01-policy-reconciliation.md#3-propuesta-de-elegibilidad-pendiente-de-decision_1) define la proyección sólo de lectura y revalidación en el commit.

**Alternativas:** mantener C4/ADM02 diferido, con owner denegado; o autorizar posteriormente una ceremonia excepcional equivalente, con contrato propio y sin omitir acreditación. Ampliar owner a no médicos sería otra decisión de producto, no un atajo recomendado. VID01/cédula/email/rol coincidentes no son alternativas aceptables de vinculación.

**Consecuencias de seguridad:** no hay autoacreditación ni autoownership; cambian versiones/estado al revocar y se deniegan casos pendientes. Ownership conserva solicitante y dos aprobadores R3 más aceptación exacta: acreditar no sustituye esas aprobaciones. Owner no obtiene firma clínica; CLIN-AUTH02A continúa vigente. Fuentes ausentes, vencidas, revocadas, ambiguas o caídas deniegan.

**Contratos reutilizados:** guía C4 §§5–8/13, ADM01/ADM02, VID01/CRD02, propuesta de binding de CLIN-AUTH02B1 D01–D03/D10, R3 y puerto `InitialOwnerEligibilityPort`, escritor de membresía/auditoría existentes. Se conserva la separación owner/staff/plan/profesión.

**Mínimo faltante:** autoridad efectiva de admisión + relación de binding, capacidad/expediente emisor y revocación, pruebas y migración futura; implementar el adapter de lectura y revalidación en el PDO final. No construir un nuevo maestro de profesionales, revisar documentos por duplicado ni tocar Recetas.

**Autorización humana exacta requerida:** el Director debe ratificar expresamente los detalles anteriores y autorizar separadamente reanudar/implementar C4/ADM02. Después deben aprobarse DDL y target y nombrarse revisores legítimos, fuentes y beneficiario/cuenta/perfil/membresía específicos con evidencia real. Ratificar esta política no efectúa esos nombramientos ni la asignación de owner.

**Decisión registrada hoy:** ninguna. GOV03B mantiene su denegación y normal-local no cambia.
