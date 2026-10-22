# CROSS-SURFACE-LINK02 — preflight de conexión

Fecha: 2026-10-21. Baseline `b775f21ad7d5bdb1685d9e7547c2c6e9c96a724d`.
Resultado **BLOCKED_SOURCE_CONNECTION_PREREQUISITE**. Sólo documentación; sin puente implementado.

## PRODUCT_POLICY

LINK02 autoriza implementar descubrimiento y resumen administrativo con grant efectivo y recurso de origen explícito, separados de soporte de operadores. Sucede la falta de autorización funcional de LINK01. Mantiene prohibidos cambios a usuarios/grants MySQL originales, DDL/DML original y atajos privilegiados. La credencial SQL dedicada es una preferencia del encargo, no una obligación textual absoluta; el bloqueo aquí es la falta de una conexión restringida disponible en la composición inspeccionada.

## SOURCE_IMPLEMENTATION / CONNECTIVITY

Se revalidó el perfil de referencia por doctor_id exacto, sin coincidencias por nombre, en `mxmed.profiles_doctors` de MySQL local en loopback. Configuración `APP_ENV=local`, base `mxmed`. No se consultaron pacientes, citas, documentos ni hashes de credenciales. Configuración secreta leída únicamente para la inspección local; no copiada al preview, Git ni navegador.

Evidencia de `CURRENT_USER()` y `SHOW GRANTS`, más inventario User/Host sin columnas de autenticación:

- La conexión configurada usa `root@localhost`, con privilegios globales y GRANT OPTION.
- `mxmed@localhost` y `mxmed@127.0.0.1` tienen ALL PRIVILEGES sobre `mxmed.*`.
- `mxmed_clinical_m6_migration@localhost` tiene SELECT de toda la base y permisos DDL/rutinas; no es lector administrativo restringido.
- `mxmed_audit_reader_local@127.0.0.1` tiene únicamente USAGE: su nombre no acredita acceso SELECT.
- Los restantes principales de auditoría están limitados a tablas/procedimientos de auditoría, con escrituras en algunos casos; los principales QA sólo acceden a sus respectivas bases preview. Ninguno aporta lectura restringida de perfiles/consultorios.

Las consultas de inspección usaron `SET SESSION TRANSACTION READ ONLY`, `START TRANSACTION READ ONLY` y rollback final. Esto limita esa transacción, pero no reduce los privilegios de la credencial para un servidor de larga duración. No se entregó root ni una cuenta equivalente al preview ni a un proxy nuevo.

`workspace02OwnedServices` compone Identity y EvidenceAuthority sobre el PDO desechable; `AdvisorSupportReader` consulta la misma base y exige contexto Requests/Media. No hay en esos contratos un transporte federado restringido que pueda reutilizarse. No se alteraron para simular conexión.

## Prerrequisito concreto

Provisionar fuera de esta tarea una conexión restringida o un servicio de lectura equivalente. La opción SQL mínima requiere únicamente SELECT de las columnas administrativas necesarias de `profiles_doctors` y `consultorios`; excluir contactos privados, licencias, documentos, clínica y finanzas. El adapter deberá imponer además el recurso exacto y referencia calificada por origen: un grant SQL por columnas no limita filas. Operadores requiere permisos de lectura separados y sólo columnas necesarias de membresías, estado de cuenta, invitaciones y delegación, sin correos, tokens ni credenciales. No se ejecutó ni preparó una concesión global.

Después: implementar grant staff delegable, alcance explícito, adapter de consultas fijas, referencia por origen, auditoría aislada, revalidación de sesión/revocación, fallo cerrado y búsqueda independiente de pendientes. No se afirma atomicidad entre bases. La autorización funcional del Director ya existe; falta el acceso técnico restringido. Crear usuarios o cambiar grants para resolverlo excedería el apartado 3.

## VERIFICATION / OPERATIONAL_STATUS

- Baseline limpio y nueva rama aislada `feature/mxmed-cross-surface-link02`.
- Fuente original permanece limpia en `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`; mismo listener PHP PID90106 en 8091.
- Identidad del perfil revalidada; no se copió ningún registro. Correspondencia entre colaborador y Director sigue sin acreditarse; no se deduce de un email preferido.
- Preview previo saludable según launcher, sin cambios ni extensión de expiración; no contiene Leticia conectada.
- No implementación, grants staff nuevos, sesiones nuevas ni cambios de UI. No pruebas E2E de revocación/colisiones/fallos de fuente, Chromium o responsive: no hay puente que probar. No se reejecutó QA histórica.
- Sólo diagnóstico de conexión PASS; objetivo funcional BLOCKED, no COMPLETE.

Relacionado: [LINK01](CROSS-SURFACE-LINK01.md), [soporte existente](ADVISOR-OPERATOR-SUPPORT01.md). OP-01/02/03, J07 y `physician_operator_support_read` no cambian de contrato efectivo.
