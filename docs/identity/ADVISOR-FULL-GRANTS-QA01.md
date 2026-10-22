# ADVISOR-FULL-GRANTS-QA01 — facultades y revocación acumulativa

**Sucesor:** [CLOSURE02](ADVISOR-CAPABILITY-CLOSURE02.md) resuelve las tres conexiones y la verificación de resumen por campos; esta evidencia y resultado históricos se conservan.

## Baseline y alcance

Baseline `42d1e9e97e01ba29ad213c2732aa5a0d81ed637b`, rama de trabajo
`feature/mxmed-advisor-full-grants-qa01`. Sucede la verificación UX05,
no su implementación. No cambia componentes de producto, SQL original,
roles, política MFA, consumidores ni autoridad. Sólo harness, launcher y evidencia.

**PRODUCT_POLICY:** probar permisos operativos delegables mediante Director real,
conservar Irene, no clínica/comercial/owner ni activación de consumidores desconectados.
**SOURCE_IMPLEMENTATION:** reutiliza Identity, admisión, writer interno,
Requests, Media y readers administrativos.
**CONNECTIVITY:** hay 17 capacidades A canónicas; 14 están conectadas a esta
composición. Las otras tres conservan consumidores backend compatibles,
pero no están conectadas al workspace/router actual. No se reclasifican como
inexistentes ni se conceden para simular cobertura.
**VERIFICATION:** resultados dirigidos en los artefactos enlazados abajo.
**OPERATIONAL_STATUS:** exclusivamente instancia owned con staff/expedientes
sintéticos y consulta administrativa restringida de la fuente original.
No es un entorno íntegramente sintético: Leticia y sus sedes se leen desde
las vistas originales autorizadas. Aceptación visual pendiente.

## Inventario previo a grants

[Matriz completa de 26 capacidades](../evidence/advisor-full-grants-qa01/capability-matrix.json).
Incluye ID, nombre, grupo, consumidor, lectura/escritura/decisión, delegabilidad
Director/Master, compatibilidad Advisor, ámbito, confirmación, efecto de
concesión/revocación y cobertura de auditoría. Un writer que acepta un grant
operacional no sustituye las restricciones del consumidor ni la elegibilidad UI.

- A: 17 operativas canónicas. 14 conectadas y tres sin conexión en este workspace:
  `professional_source_provision`, `physician_accreditation_review`, `provider_claim_review`.
- B: cuatro reservadas: gobierno de asesores, ownership inicial y dos correcciones
  públicas cuyo consumidor limita la autoridad a Director/Master.
- C: cuatro entradas de administración genérica sin consumidor Advisor compatible
  conectado (`profile_create`, `profile_curate`, `profile_publication_manage`, `profile_support_assist`).
- D: una inaplicable **al contrato de este ensayo**: `provider_subscription_activate`.
  Tiene implementación operativa; no se declara legacy ni obsoleta. El Director
  excluyó explícitamente acciones comerciales/de facturación.

Master no recibe facultades por clase: requiere gestión vigente y delegación
exacta actual para cada grant operacional. No se otorgan permisos B/C/D.

## Dos identidades independientes

`full_grants_test@example.test` consume la secuencia acumulativa.
`full_grants_demo@example.test` conserva la demostración conectada completa.
Ambas atraviesan registro/verificación/aceptación de invitación inerte,
confirmación personal, evidencia geométrica, revisión y admisión canónicas.
La simulación del solicitante no revisa, admite ni concede grants.
Director realiza autorización/revisión/admisión con la política vigente AUTH-UX08;
los grants se seleccionan y guardan en ACCESO Y FUNCIONES mediante Chromium.
MFA Advisor se enrola por la ceremonia existente con registrador distinto,
después de habilitar las facultades que hacen elegible al sujeto.

No hay SQL directo para staff, admisión ni grants de los dos asesores dedicados. SQL del harness de navegador
admite exclusivamente SELECT/SHOW para comprobar resultados. El setup reutiliza
fixtures de perfiles/Media sintéticos y schemas en un datadir nuevo.

## Contrato de revocación y límites

Cada paso retira una sola capacidad, guarda por UI, recarga y comprueba SQL,
conserva la sesión Advisor, prueba el endpoint y comprueba lectores ajenos.
Las decisiones/mutaciones se retiran antes que sus permisos base.
No se restaura ningún grant del actor de revocación.

`physician_pending_summary_read` **no es una puerta exclusiva al endpoint**
`mode=pending`. Extiende conteos sobre ámbito ya autorizado. Sin ella, otros
permisos pueden mantener HTTP 200 con `summary_authorized=false`. Exigir 403
al lector completo inutilizaría permisos ajenos y cambiaría el contrato canónico.
Se registra esa divergencia, no un PASS de denegación HTTP.
Los readers agregados de Requests/Media también pueden devolver una proyección
vacía; la revocación se prueba contra el recurso protegido exacto para obtener 403.

El ámbito original usa `mxmed-local-development:1`, allowlist por actor/capacidad,
lectores SQL sólo SELECT y referencias que no caen a un ID sintético. Consultorios
requiere discovery; soporte requiere su permiso independiente. La composición
sintética preexistente usa el conjunto de trabajo elegible, no grants por médico
inventados. No se atribuye a ella una asignación por médico que no implementa.

## Evidencia y revisión

Los JSON sanitizados se guardan en [evidencia QA01](../evidence/advisor-full-grants-qa01/).
Capturas, registros de sesión y credenciales quedan exclusivamente en el directorio
privado owned, nunca en Git. El resultado no se declara COMPLETE mientras falte
cobertura de consumidores o haya divergencias con la exigencia HTTP literal.

Launcher: `scripts/advisor-full-grants-qa01-review.py <owned> <acción>`.
Acciones: `status`, `open`, `open-director`, `open-advisor`,
`credentials-director`, `credentials-advisor`, `mfa-director`, `mfa-advisor`, `stop`.
Credenciales y MFA se copian al portapapeles; no se imprimen secretos.
`stop` sólo señala al runner propietario. La infraestructura dura 24 horas;
Identity, invitaciones y códigos no se prolongan.

## CTX-SYNC01

IDs preservados: ID-01/02/03/07, PUB-04/05, MED-01/02/03, PRF-03/04,
OP-01/02 (sólo lectura de relaciones, sin modificar sus writers).
Journeys: J01/J02/J07/J09; J08 sólo frontera comercial excluida.
Objetos: Identity account/session, staff admission case/evidence/completion,
internal staff/grant, solicitudes/decisiones y Media sintéticos, auditoría,
referencia administrativa calificada y proyecciones originales read-only.
No se regeneran mapas ni se modifica el censo de capacidades.

## Resultado dirigido

**PARTIAL_ISOLATED_CANONICAL_QA**, no COMPLETE. [Resultado](../evidence/advisor-full-grants-qa01/result.json),
[por capacidad](../evidence/advisor-full-grants-qa01/per-capability-results.json),
[secuencia](../evidence/advisor-full-grants-qa01/revocation.json),
[auditoría de grants](../evidence/advisor-full-grants-qa01/canonical-grant-audit.json),
[consumidores/MFA](../evidence/advisor-full-grants-qa01/consumer-audit.json) y
[ámbitos negativos](../evidence/advisor-full-grants-qa01/boundaries.json).

14 consumidores conectados ejercitados, 13 denegaciones403 y una proyección
compartida200 sin summary_authorized. 28 grants y 14 retiros distintos conciliados
con actor Director y capability exactos; ninguna reconcesión del actor test.
Las ejecuciones del harness se reanudaron entre segmentos por selectores antiguos,
espera de carga y consulta inicial de auditoría sin prefijo `account:`.
Cada revocación se probó sin cambiar la sesión Advisor dentro del paso; no se
presenta como una única sesión ininterrumpida de principio a fin.
La pestaña de operadores permanece navegable y muestra la sección restringida;
no debe confundirse navegación con autorización. WebKit es smoke de lectura/contexto,
no repetición de las 14 mutaciones/revocaciones Chromium.

Catorce comprobaciones negativas suplementarias: vínculo Media incorrecto,
archivo de otro expediente, autorrevisión de reclamo/perfil nuevo, MFA inválido,
decisión sin proof y logout/relogin legítimo. Requests exige dos proofs consumidos
para sus dos aprobaciones. Los cuatro hashes originales coinciden con la referencia
anterior a UX05; listeners/worktrees protegidos intactos.

Instancia: `/tmp/mxmed-ase-adm02imp01-0xiv9irr`.
Director: `https://127.0.0.1:52105/internal/media-review/index.php?section=equipo`.
Advisor: `https://127.0.0.1:52105/internal/staff-workspace/index.php`.
Expira **2026-10-22 23:33:57 -06:00 (America/Mexico_City)**.
La demostración mantiene 14/17 A; las tres desconectadas nunca se concedieron.
Para completar el criterio literal faltan su conectividad autorizada y resolver
la incompatibilidad entre exigir403 global y preservar el contrato del resumen.
No se habilitaron consumidores nuevos para eludir esas restricciones.
