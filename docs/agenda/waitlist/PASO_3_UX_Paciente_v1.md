# Lista de Espera (Waitlist) — PASO 3: UX del Paciente v1

1. Propósito desde el punto de vista del paciente
   - Que quien no encuentra cita directa pueda dejar sus datos y aguardar posibilidad real sin creer que ya tiene fecha.
   - Asegurar claridad en el estado “en espera”: el paciente sabe que está en cola, que el equipo validará huecos y que cualquier cita debe confirmarse después.
   - Reforzar que el objetivo es ofrecer un hueco liberado, no entregar cita instantánea.

2. Qué ve el paciente (pantallas/mensajes) — v1
   - Mensaje corto obligatorio: “Estás en lista de espera por 7 días; te avisaremos si surge un hueco. No es una cita confirmada.”
   - Versión un poco más larga (para políticas o términos): “Ingresaste a la lista de espera porque por ahora no había turno disponible. Permanecerás 7 días en la cola; te contactaremos sólo si aparece un hueco real. Mientras tanto, la reserva no está confirmada y puedes seguir buscando otros días o médicos.”
   - Se puede mostrar un panel simple con fecha de ingreso, tiempo restante (sin dar posición exacta) y canales de contacto, tal como aparece en PASO 2 donde se aclara que no se debe prometer cita.

3. Qué NO ve (guardrails UX)
   - No se indica una fecha ni hora tentativa hasta que un operario confirme la asignación.
   - No se muestra la posición exacta en la cola ni se repite el mensaje de “cita confirmada”.
   - No hay botones de “reservar ahora” desde esta pantalla: la única acción disponible es abandonar o esperar comunicación.

4. Regla de bloqueo de reserva directa (explicada en lenguaje de paciente)
   - Mientras estés en la lista, no puedes reservar directamente otro turno desde la misma pantalla porque primero debemos validar huecos reales. Recomendamos seguir buscando fechas abiertas si necesitas urgencia y, si aparece un hueco, nosotros te llamamos.

5. Vigencia / expiración (7 días) y recomendación de acción
   - El registro en la lista caduca a los 7 días sin respuesta para evitar expectativa infinita.
   - Recomendación: si no hay contacto en ese lapso, vuelve a revisar el portal o llama al consultorio; puedes solicitar reapertura de la lista si aún necesitas el turno.

6. Canales posibles (teléfono, portal, WhatsApp) — v1 vs FUTURO
   - v1: el paciente es contactado por teléfono/WhatsApp o desde el portal cuando la operadora identifica un hueco; la comunicación es manual.
   - FUTURO: se puede automatizar la oferta y un canal puede ser notificación en la app (o SMS) con botones para aceptar, rechazar o posponer directamente desde el mensaje.

7. FUTURO (no implementar): automatización oferta 60 min con aceptar/rechazar/no respuesta
   - Cuando aparezca un hueco, se oferta automáticamente al paciente más antiguo y se abre una ventana de 60 minutos para responder.
   - Si acepta, la cita se confirma y el plazo que queda se ajusta; si rechaza o no responde, se pasa al siguiente en FIFO.
   - Siempre debe quedar claro en esa automatización que se trata de una oferta provisional, no de una cita definitiva hasta que se confirme.

8. Checklist v1 vs FUTURO (tabla)
   | Elemento | v1 (manual) | FUTURO (automatización opcional) |
   | --- | --- | --- |
   | Comunicación principal | Operadora llama / WhatsApp / portal | Mensaje automático con opciones (aceptar/rechazar/noresponde) |
   | Mensaje sobre vigencia | Texto claro de 7 días y sin garantía | Mismo mensaje con opción de ver políticas completas |
   | Acción disponible | Esperar contacto; seguir buscando otras fechas | Respuesta directa al mensaje de oferta |
   | Expiración visible | Registro en cola se limpia después de 7 días (sin aviso) | Notificación de expiración y posibilidad de reingresar |
   | Override / excepciones | Documentado manualmente en PASO 2 (override justificado) | Logica de prioridad específica con trazabilidad |
   | Supervisión humana | Operadora confirma hueco real antes de asignar | IA/call center supervisado que gestiona ofertas en tiempo real |

Este documento se alinea con PASO 2 en el tono y en la insistencia de no prometer cita, pero se enfoca en lo que el paciente experimenta en v1 y en cómo podrían evolucionar las señales cuando llegue la automatización.
