# ASE-ADM02-UXFUNC01 — acciones operables del Director

Base `66056af75a70de8e1821ac3064d48d629bc76bfb`, rama/worktree aislados `feature/ase-adm02-uxfunc01` / `mxmed-ase-adm02-uxfunc01`. El Director autoriza corrección funcional UI-2 y aclara expresamente «Baja definitiva del equipo». Sin promoción, datos reales ni modificación de 8091.

## Ficha UI-2 y diagnóstico

Pantalla: Equipo interno / Solicitudes / Acceso y funciones. La inspección reproducida del baseline mostró alta habilitada debajo del directorio, asesor admitido con nombre y diez permisos editables, grupos sin consumidor para asesores, fixtures legacy sin perfil y gobierno Master cerrado. No todos los controles deshabilitados eran fallos de autorización.

| Observación | Clasificación y corrección |
|---|---|
| Agregar no visible | Existía al final del directorio. Ahora se presenta antes de Solicitudes; abre formulario limpio, protege el descarte y distingue «Preparar solicitud» de abrir una incorporación. |
| Baja definitiva ausente | Nueva operación acotada del writer canónico, exclusiva del Director sobre ADVISOR; confirmación explícita y estado terminal REMOVED. Suspensión/reactivación siguen siendo acciones distintas. |
| Grupos y permisos | Se conserva la selección de borrador y comandos individuales existentes. Se comprueba clic/confirmación/persistencia/revocación desde Chromium; las restricciones explican cuenta, suspensión, baja, ámbito o función no habilitada. No se habilitan capacidades sin consumidor aprobado. |
| Nombre no disponible | Perfil autorizado ya conectado para admisiones completas. Se usa también en confirmaciones; una relación sin fuente personal muestra «Perfil personal pendiente» junto a su referencia. No se inventan nombres ni se derivan de correos/datos médicos. |
| Gobierno Master no disponible | Restricción intencional preservada. El mensaje aclara que sólo afecta nombramiento/delegación Master y no bloquea incorporación/permisos de asesores. Baja directa de un Master denegada; exige primero su procedimiento independiente de retiro de designación. |
| Suspensión no persistía en el ensayo | El principal HTTP aislado carecía de UPDATE(status) en internal_staff. Se concede sólo esa columna en la infraestructura desechable; se verifica suspender/reactivar por navegador. |

Razón: que el Director encuentre y complete acciones reales sin convertir límites de autoridad en botones permisivos. Alternativa sin cambio: persistían la baja ausente, la omisión de privilegio SQL del ensayo y mensajes ambiguos. Riesgo: baja terminal y revocación de permisos; controles abajo. Rollback: conservar rama/base y entorno anteriores intactos, detener sólo el nuevo runner. No revertir datos terminales ni aplicar downgrade a una base real. Sin cambios CSS, roles nuevos o rediseño. La revisión visual del Director no constituye despliegue.

## Baja canónica y límites

[SQL preparado](../../modules/identity/db/migrations/2026_10_18_08_staff_definitive_removal.sql) amplía el estado de internal_staff, sin otra tabla de personas. REMOVED sólo admite ADVISOR; el trigger impide regresar a otro estado/clase. No DELETE de cuentas, casos, documentos, relaciones ni audit. No se implementa reingreso de una relación dada de baja.

`remove_advisor` exige precondición servidor ADVISOR_REMOVAL, sesión vigente, Director activo, actor distinto y target ADVISOR ACTIVE/SUSPENDED. Permite terminar staff de una cuenta Identity inactiva sin reactivarla. Master/Advisor no pueden ordenar la baja. Un Master no se remueve por este endpoint: conserva su flujo privilegiado separado.

Bajo los mismos locks ordenados cuenta/staff del writer, cambia estado, revoca grants y delegaciones activas y persiste INTERNAL_GOVERNANCE_CHANGED con before/after. Audit fallido revierte todo. Se revalida sesión antes de commit. El guard terminal protege también los callers directos del writer: ni register, reactivate, grant ni appointment restauran la relación. Los consumidores existentes exigen staff ACTIVE; la cuenta Identity y sus otros ámbitos no se desactivan. D01–D08 no cambian.

HTTP conserva gate explícito, TLS firmado, origen, sesión, CSRF, parser cerrado y errores sin secretos. La baja usa la autoridad Director del writer de equipo; no se sustituye ni omite el MFA de Master o revisión documental. La UI identifica el target, consecuencias y carácter definitivo antes del POST, reconsulta precondiciones y resultado y no reintenta automáticamente respuestas inciertas. El registro permanece visible como «Baja definitiva» para consultar su estado, sin acceso staff.

## Verificación y estado operacional

[Runner dirigido](../../scripts/ase-adm02-uxfunc01-qa.py) reutiliza el launcher MySQL/Valkey/TLS propietario y el recorrido positivo de los servicios canónicos para dejar un asesor admitido y otra solicitud pendiente. [Checks](../../scripts/ase-adm02-uxfunc01-checks.py) ejercitan alta visible, nombre canónico, selección/grant/revoke de grupo, cancelación, suspensión/reactivación, baja terminal, CSRF, targets protegidos y rollback por fallo audit. No batería histórica exhaustiva. El primer intento detectó una espera ambigua en la prueba de revocación (podía leer el resultado anterior); se corrigió para esperar el POST y la finalización del editor. Su evidencia fallida se conserva, y la instancia fallida se eliminó tras verificar propiedad y parada.

La configuración de revisión conserva un máximo de 24 horas; no prolonga tokens ni cambia la política de sesión/MFA. Configuración privada y artefactos permanecen en el directorio propietario; las instancias y listeners previos se preservan. 35 comprobaciones dirigidas PASS, ocho comprobaciones adicionales de seguridad PASS y seis escenarios de viewport Chromium/WebKit PASS (1366/820/390). [QA](../evidence/ase-adm02-uxfunc01/qa.json), [seguridad](../evidence/ase-adm02-uxfunc01/security.json), [UI final](../evidence/ase-adm02-uxfunc01/browser-final.json) y [cierre](../evidence/ase-adm02-uxfunc01/verification.json). No aceptación física Safari ni aprobación móvil final. Producción permanece inactiva y el SQL no se instala fuera del ensayo.

## CTX-SYNC01

| Campo | Valor |
|---|---|
| CONTEXT_BASELINE | 66056af7; consolidación funcional y reapertura sintética, sin promoción. |
| AFFECTED_CAPABILITY_IDS | ID-07 / COM-10; mismas capabilities y actores. |
| AFFECTED_JOURNEY_IDS | J09: alta visible, administración de permisos y baja definitiva. J08/D01–D08 intactos. |
| AFFECTED_DATA_OBJECTS | internal_staff.status REMOVED; grants/delegaciones revocados y audit canónico. Sin nuevo registro de staff. |
| AFFECTED_CONTEXT_FILES | Contrato UXFUNC01, estado/router e inventario/datos/interacciones. |
| CONTEXT_CHANGES_IMPLEMENTED | Diagnóstico separado de autoridad; controles/UI, writer de baja, migración preparada y permisos SQL del ensayo. |
| CONTEXT_VERIFICATION | 35 checks funcionales, ocho de seguridad, seis escenarios UI; hashes, sintaxis y referencias verificados. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED. |
| DOCUMENTATION_COMMIT | Commit de corrección, informado al entregar. |
| REMAINING_CONTEXT_GAPS | Activación/legales/custodia/retención/canal productivos y gobierno Master independiente, sin ampliación. |
