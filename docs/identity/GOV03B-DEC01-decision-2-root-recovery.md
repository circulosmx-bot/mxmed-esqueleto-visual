# GOV03B-DEC01 — DECISION_2: recuperación extraordinaria del Director interno

> GOV-SIM02 difiere expresamente esta propuesta: **no aprobada ni obligatoria para ownership médico rutinario**. No se implementa V3 ni se debilitan los controles existentes de las autoridades raíz.

```text
STATUS=DEFERRED_NOT_APPROVED
DATE=2026-10-16
DECISION=NOT_TAKEN
CUSTODIANS_OR_TARGET_SELECTED=false
```

**Opción recomendada:** una operación backend raíz separada del bootstrap inicial, disponible sólo para un incidente aprobado cuando no se reúna autoridad interna normal suficiente. Tres custodios institucionales independientes previamente nombrados; **dos firmas Ed25519 propias de dos custodios vigentes**, distintos de beneficiario/autoridad comprometida/iniciador/ejecutor. Un solo custodio o la llave agregadora v2 no pueden autorizar recovery.

**Detalle nuevo por ratificar:** padrón/custodia/autoridad organizacional previa, quorum 2/3, fuentes de identidad fuerte y MFA/reauth externos independientes; envelope v3 exacto, consumible una vez. Ventanas propuestas: reauth cinco minutos, evidencia quince, ceremonia treinta; revisión independiente en 24 horas. Recuperar al titular original es preferible; sucesor distinto requiere aprobación nominativa expresa, sin promoción automática. La [propuesta completa, §4](GOV03B-DEC01-policy-reconciliation.md#4-propuesta-de-recuperación-raíz--deferred_not_approved) detalla compromiso, contención y atomicidad.

**Alternativas:** 3 de 5 para tolerar dos indisponibles con mayor coste; 2 de 2 aceptando bloqueo si falta uno; o mantener recuperación extraordinaria deshabilitada hasta definir custodia. Mantener una sola llave emisora no satisface resistencia ante compromiso de esa llave.

**Consecuencias de seguridad:** dos sanos pueden revocar/rotar una llave perdida o comprometida; no permitir voto de llave afectada ni de sustituto en su propio nombramiento. Sin quorum, detener y exigir reconstitución institucional separada; nunca bajar umbral, repetir bootstrap, usar SQL, password por defecto o token maestro. Dos custodios coludidos siguen siendo riesgo residual; control total de host/DB exige custodia y evidencia externas independientes.

**Contratos reutilizados:** DEC-011A–E y DEC-012E/F (R3, caso, identidad real, separación, última raíz protegida, expiración/revisión), IW01 y slot de Director único, sesiones/recovery canónicos, MFA, patrones Ed25519 y audit del mismo PDO. El envelope actual v2 y la ceremonia MFA ordinaria no implementan el quorum/enrollment excepcional propuestos.

**Mínimo faltante:** registro/versiones/revocación de custodios y llaves; verificador de dos firmas v3; incidente/epoch/consumo; contención y enrollment excepcional con factor nuevo sin grants; writer atómico de restauración/sucesión y auditoría. La contención auditada persiste si falla después la restauración. No se concede clínica, ownership, operadores ni grants heredados.

**Autorización humana exacta requerida:** ratificar estos controles, incluida la excepción acotada a protección del último Director y su democión atómica si se aprueba sucesor; aprobar autoridad organizacional de continuidad sin quorum. Posteriormente nominar custodios/ejecutor/target y llaves con evidencia, aprobar implementación/migración y cada incidente/operación/alcance exactos por separado. La autorización de emergencia expira; el nombramiento restaurado sólo persiste según su aprobación institucional explícita.

**Decisión registrada hoy:** ninguna. No se crea raíz, secreto, MFA o grant; los runtimes permanecen deshabilitados.
