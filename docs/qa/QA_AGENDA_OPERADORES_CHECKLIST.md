# QA Checklist · Agenda + Operadores (estabilización)

Fecha base: 2026-05-18  
Objetivo: no regresión de flujos estabilizados antes de nuevas funciones.

## A) Agenda

### Semana
- [ ] Semana inicia en día actual.
- [ ] Navegación de semana mantiene posición estable de flechas.
- [ ] Modo `Todos los consultorios` renderiza cards por consultorio sin colisiones visuales.
- [ ] Modo consultorio específico filtra correctamente.

### Día
- [ ] Vista Día muestra mini calendario + reloj/contexto + KPI + columnas Mañana/Tarde.
- [ ] Domingo sin horario muestra: `No hay horarios disponibles para este día.`
- [ ] Domingo/feriado se puede seleccionar (sin inventar disponibilidad).
- [ ] Scroll interno de Mañana y Tarde funcional y consistente.

### Bloqueos
- [ ] Bloqueo parcial desde slot disponible.
- [ ] Bloqueo de día completo usa ventanas reales del día (no 08:00–20:00 ficticio).
- [ ] Si hay cita real en ventana, bloqueo total respeta restricción.
- [ ] Desbloqueo actualiza Día inmediatamente.
- [ ] Semana y Día permanecen sincronizadas tras bloquear/desbloquear.

### Citas
- [ ] Nueva cita abre desde slot disponible con fecha/hora/consultorio correctos.
- [ ] Reprogramar funciona y refresca render.
- [ ] Cancelar funciona y refresca render.
- [ ] No show funciona y refresca render.
- [ ] Modal `Siguiente cita disponible` lista cards, permite elegir y navegar siguientes/anteriores.

### Reglas de UX
- [ ] Vista Mes no visible en selector principal.
- [ ] Leyenda origen de cita visible en ubicación actual.

## B) Operadores

### Alta
- [ ] Alta completa en wizard progresivo (Datos generales -> Acceso -> Permisos -> Enviar credenciales).
- [ ] Botón principal muestra `Siguiente` fuera del último paso.
- [ ] `Guardar operador` solo aparece en paso Permisos.
- [ ] Cancelar alta limpia formulario y contrae banda.
- [ ] Reingreso a Operadores no rehidrata drafts inconclusos.

### Alias / credenciales
- [ ] Alias obligatorio.
- [ ] Alias normaliza a MAYÚSCULAS, sin acentos, sin espacios.
- [ ] Alias valida longitud (3–15) y unicidad.
- [ ] Login automático sugerido (editable) y deduplicado.
- [ ] Contraseña temporal generada y regenerable.
- [ ] Validación mínima de login/contraseña antes de avanzar.

### Estados / cupo
- [ ] Máximo absoluto: 3 operadores contables (`active`, `paused`, `pending`).
- [ ] Archivados no cuentan para cupo.
- [ ] Si cupo = 0, alta adicional bloqueada.

### Seguridad y archivado
- [ ] Pausar acceso requiere código 6 dígitos.
- [ ] Reactivar acceso requiere código 6 dígitos.
- [ ] Eliminar operador requiere código 6 dígitos.
- [ ] Eliminar = archivar (no borrar).
- [ ] Operador archivado desaparece de registrados y aparece en `Operadores eliminados`.

### Historial
- [ ] Historial de acciones abre desde operador activo.
- [ ] Historial abre desde operador archivado.
- [ ] En archivados no mostrar etiqueta `Operador 01`; usar `Nombre completo (Alias)`.

## C) Persistencia y recarga
- [ ] Recargar conserva operadores registrados/archivados.
- [ ] Recargar conserva cupo calculado correctamente.
- [ ] Recargar no restaura drafts incompletos de alta.

## D) Recomendación de ejecución
- [ ] Ejecutar checklist en escritorio (Safari/Chrome).
- [ ] Repetir smoke básico en responsive.
- [ ] Registrar evidencia (capturas + hora + usuario QA) por bloque A/B/C.
