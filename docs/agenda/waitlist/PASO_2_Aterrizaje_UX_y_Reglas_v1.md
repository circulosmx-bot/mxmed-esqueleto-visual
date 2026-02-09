# Lista de Espera (Waitlist) — PASO 2: Aterrizaje UX y Reglas v1

Resumen ejecutivo: la lista de espera de Agenda Médica v1 mantiene la operación manual: la operadora captura pacientes que no encontraron cita directa y los coloca en una cola FIFO; cuando aparece un hueco real (cancelación) el equipo puede asignar la cita al paciente más antiguo y registrar la trazabilidad. Este documento aclara el propósito, actores, flujos (hoy y futuro), reglas, estados y límites para evitar confusiones. Se destaca que la asignación automática todavía no existe, que la permanencia máxima es limitada y que la interfaz debe comunicar claramente que no hay cita garantizada. También se describe qué eventos de bitácora registrar y qué no debe inducir el diseño.

1. Propósito
   - Mantener ocupados los huecos que deja una agenda saturada sin tener que abrir el libro de citas al público.
   - Capturar pacientes que aceptan esperar y asignarlos cuando surge un hueco real producto de una cancelación o no-show con suficiente anticipación.
   - Evitar que la lista se convierta en una promesa de cita: el propósito es gestionar la cola, no garantizar reserva inmediata.

2. Actores y roles
   - Paciente (futuro): entra a la lista desde la cara externa (portal, app o atención telefónica) y recibe mensajes cuando se le ofrece slot.
   - Operadora / consultorio (hoy): gestiona manualmente las capturas de pacientes, mantiene la cola y dispara la asignación cuando identifica un hueco.
   - Médico (hoy): informa sobre cancelaciones y valida la disponibilidad de su agenda; recibe el aviso de la cita creada por la cola.
   - Call center de la plataforma (futuro): automatiza la captura y seguimiento del paciente en grande escala.
   - Agente IA como operadora (permitido, futuro): puede ejecutar tareas repetitivas de captura o seguimiento bajo supervisión humana.

3. Flujo v1 (manual)
   - El paciente no puede reservar directamente si entra a waitlist: se le informa que está en una cola sin promesa de fecha.
   - Alta manual realizada por consultorio/operadora desde la UI de lista de espera (doctor/consultorio específico).
   - Cada entrada conserva notes/estado y aparece en la tabla de activos.
   - Cuando se detecta un hueco real (cancelación confirmada), el equipo accede a la entrada, usa “Asignar” y selecciona un slot disponible; el sistema crea la cita y registra el evento de asignación.
   - Existe opción de override o anotación (linked_cancelled_appointment) para documentar excepciones; la navegación guía a la ficha de cita creada para confirmación final.

4. Flujo futuro (automatización opcional)
   - Al detectarse una cancelación, se toma el paciente más antiguo (FIFO) y se le ofrece la opción mediante canal configurado (SMS/WhatsApp/AI) durante una ventana de 60 minutos.
   - Si acepta: se confirma la cita y se cierra el evento; si rechaza o no responde, se ofrece al siguiente en lista.
   - Un fallback mantiene el registro en la cola durante el proceso.
   - Nota: este flujo todavía no existe en v1; debe implementarse en PASOS posteriores.

5. Vigencia de la entrada
   - Cada entrada expira a los 7 días sin acción para evitar permanencia indefinida.
   - Mensaje recomendado al paciente (versión corta): “Estás en lista de espera por 7 días; recibirás un aviso si surge un hueco. No es una cita confirmada.”
   - La expiración es silenciosa: en v1 no se notifica automáticamente al paciente, pero sí se refleja en la bitácora y ocurre una limpieza periódica.

6. Estados de una entrada
   | Estado | Descripción |
   | --- | --- |
   | `activa` | Paciente en cola listo para el siguiente hueco. |
   | `ofertada` (futuro) | Ya se intentó notificar al paciente (pendiente de respuesta). |
   | `confirmada` | Se creó cita y se vinculó con el entry. |
   | `expirada` | Superó los 7 días sin acción. |
   | `rechazada` | Paciente declinó oferta. |
   | `removida` | Eliminado manualmente por operadora (duplicado/irrelevante). |

7. Reglas duras
   - FIFO es la regla general para asignaciones; solo un override documentado rompe ese orden.
   - No hay permanencia indefinida: el entry expira o se remueve manualmente.
   - Si en un momento no hay lista o está vacía, el slot liberado se maneja como una cita estándar abierta al público.

8. Bitácora / trazabilidad (PASO 7)
   - Eventos mínimos:
     * `waitlist_entry_created` (nuevo entry)
     * `waitlist_entry_updated` (status/notes)
     * `appointment_reassigned_from_waitlist` (asignación manual)
     * `waitlist_entry_override` (si aplica override manual)
     * `waitlist_entry_expired` (silencioso)
   - Campos clave en cada evento: timestamp (America/Mexico_City), actor_type (operadora/IA), actor_id, metadata JSON (contiene entry_id, status anterior/nuevo, motivo, start_at/end_at cuando aplica, linked_cancelled_appointment)

9. “Qué NO debe inducir el diseño”
   - No prometer una cita al paciente: siempre debe entender que es una lista de espera.
   - No mostrar su posición exacta para evitar frustración.
   - No mezclar la captura de datos con la asignación como si fuera la misma acción; la UI debe separar claramente “Agregar a la cola” de “Asignar cuando hay hueco”.

10. Checklist de implementación
   - v1 (manual): captura individual, tabla de activos, botón discreto de asignación, confirmación en ficha, eventos logueados.
   - FUTURO: automatización de ofertas, seguimiento de respuestas, expiraciones automáticas, vistas para agentes IA y call center, métricas de tiempo en cola. Notar en la documentación futura cuando cada pieza quede implementada. 
