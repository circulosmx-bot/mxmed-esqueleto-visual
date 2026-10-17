# México Médico — instrucciones de proyecto

## Propósito

México Médico es un sistema existente y sustancialmente desarrollado.
El trabajo actual conecta componentes, corrige inconsistencias y completa integraciones.
La UI original y los servicios canónicos son el punto de partida; no reconstruir módulos.

## Inicio de cada tarea

1. Verificar `pwd`, Git root, rama, HEAD, upstream y worktree antes de editar.
2. No confundir este worktree con un checkout hermano ni cambiar de rama para resolverlo.
3. Leer [estado actual](docs/ESTADO_ACTUAL_MXMED.md), comprobando fecha y baseline.
4. Usar el [router de módulos](docs/README.md) para recuperar sólo el contrato pertinente.
   Para localizar trabajo existente, seguir sus mapas CTX-MAP02 por función/datos/flujo; consultar AWS o pagos sólo si aplican y respetar sus límites de evidencia.
5. Identificar la decisión aprobada y el alcance autorizado por el Director.
6. Buscar primero pantalla original, consumidor, servicio, writer, read model y QA previa.

## Implementación

- Reutilizar componentes y writers existentes; preferir el cambio correcto más pequeño.
- Buscar con `rg` y leer fragmentos pertinentes antes de abrir archivos extensos completos.
- Clasificar la brecha antes de proponer código: ausente, funcional, desconectada,
  preparada e inactiva, parcial, bloqueada por autorización, histórica o no verificada.
- Una función deshabilitada no está necesariamente ausente.
- Un mockup o una prueba aislada no acredita integración ni activación normal-local.
- Mantener el español del producto y las decisiones funcionales/visuales aprobadas.
- Preservar shell, navegación y UX original; cambios de diseño requieren su alcance aprobado.
- Consultar el protocolo UI/UX enlazado en el router cuando se afecte una superficie.

## Autoridad y protección

- El Director decide producto; las notas del asistente no constituyen aprobación.
- Separar cuenta Identity, médico acreditado, owner, operador y staff interno.
- Una membresía no es una denominación profesional; acreditación no asigna ownership.
- Ser owner no concede automáticamente permiso de firma o emisión clínica.
- Revalidar en servidor sesión, ámbito, capacidades, fuentes y autoridad aplicables.
- Preservar CSRF, auditoría canónica, procedencia y versiones de documentos médicos.
- Proteger datos de pacientes y médicos; no exponer documentos privados ni credenciales.
- No copiar secretos, tokens, cookies, claves o datos reales a documentación, Git o fixtures.
- Contactos almacenados FREE no se convierten automáticamente en contactos públicos.
- Preservar sesiones, bases, historia, fixtures persistentes y servidores existentes.
- DDL real requiere autorización específica; código o SQL preparado no la sustituye.
- No activar runtimes, entregar invitaciones, elevar roles ni actuar en producción sin autorización.

## Verificación y continuidad

- Elegir pruebas por impacto; inspeccionar antes sus targets, escrituras y limpieza.
- No ejecutar por defecto QA que resetee datos persistentes o toque servicios originales.
- Distinguir prueba sintética, QA normal-local, aceptación física y activación operacional.
- Reportar cambios, entorno/commit probado, cobertura, fallos conocidos y límites exactos.
- Cerrar con baseline/post HEAD, resultado, enlaces de evidencia y autorizaciones pendientes.
- La revisión y sincronización de contexto forman parte de cada cambio autorizado, según la regla siguiente.
- Mantenerlo acotado; conservar historia en Git y contratos, sin transcripciones acumulativas.
- Estas referencias se comparten mediante Git; no implican memoria automática ChatGPT/Codex.

## Sincronización obligatoria de contexto — CTX-SYNC01

- Orden de consulta: este archivo → estado actual → router → mapa CTX-MAP02/03 pertinente → fuente, decisiones y evidencia. Mantener el inicio acotado y recuperar detalle bajo demanda.
- **Antes de implementar:** leer el estado vigente, localizar IDs de capacidades/journeys y objetos existentes, identificar mapas/decisiones afectados y contrastar la implementación actual antes de añadir código.
- **Durante:** preservar IDs estables y dueños de las fuentes de verdad; identificar cambios de UI, API, writers, datos, autoridades, servicios externos e interacciones, con dependencias nuevas y limitaciones reales.
- **Antes del commit:** revisar el diff completo, incluidos archivos nuevos; identificar y actualizar los documentos de contexto impactados con cambios verificados, pruebas, entorno y estado operacional. Actualizar el estado compacto ante cambios materiales y el router sólo si cambia el enrutamiento documental. Validar enlaces, anchors y paths pertinentes.
- Conservar contratos/evidencia históricos; marcar afirmaciones sucedidas y enlazar su sucesor verificado. Separar `PRODUCT_POLICY`, `SOURCE_IMPLEMENTATION`, `CONNECTIVITY`, `VERIFICATION` y `OPERATIONAL_STATUS`; QA aislada no acredita activación ni verificación externa. Actualizar sólo IDs/journeys/objetos afectados, sin regenerar inventarios completos.
- Toda entrega Codex debe completar los diez [campos obligatorios de contexto y su gate](docs/MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md#context-sync). Verificar coherencia entre documentación, implementación entregada, alcance del commit y evidencia; editar Markdown por sí solo no acredita sincronización.
- Sin impacto real: registrar `CONTEXT_CHANGE=NO_IMPACT` y una razón precisa, sin inventar cambios documentales. `CONTEXT_SYNC_STATUS` debe ser `PASS_UPDATED`, `PASS_NO_IMPACT_WITH_REASON` o `BLOCKED_INCOMPLETE`.
- Una función cambiada sin revisión de contexto, o una entrega con `CONTEXT_SYNC_STATUS=BLOCKED_INCOMPLETE`, no puede declararse COMPLETE.
- La revisión se realiza por tarea autorizada; esta política no instala agentes de fondo, daemons ni hooks globales y no amplía la autorización operacional.
