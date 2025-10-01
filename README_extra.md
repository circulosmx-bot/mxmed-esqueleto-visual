# 📑 Texto guía completo — Proyecto MXMed 2025

Este documento encapsula TODO el contexto necesario para continuar el desarrollo en una nueva conversación de ChatGPT.

## ⚠️ Reglas principales
- `index.html` **no debe modificarse** salvo petición expresa del cliente.
- Cambios de estilos → `assets/css/style.css`.
- Cambios de lógica/JS → `assets/js/app.js`.
- Mantener **pixel-perfect** idéntico al archivo base.
- Entregar en dos formatos cuando corresponda:
  1. `index.html` integrado (para pruebas rápidas en Wamp).
  2. Paquete separado (`index.html` + `assets/`) en `.zip` (para desarrolladores).

## 🎯 Contexto del proyecto
Plataforma digital MXMed 2025 con perfiles profesionales del área médica (médicos, hospitales, clínicas, laboratorios, aseguradoras, farmacéuticas).

### Perfiles contemplados
- Médico
- Hospital
- Clínica
- Laboratorio de análisis clínicos
- Gabinetes de imagenología
- Aseguradoras
- Laboratorios farmacéuticos

### Planes de perfiles médicos
1. **Gratuito**
   - Nombre completo, especialidad, cédula profesional, domicilio completo.
   - Mapa sin GPS.
   - Sin teléfono ni medios de contacto.
   - Puede recibir reseñas pero no responderlas hasta reclamar el perfil.
   - Proceso de reclamo → acceso a la interfaz.

2. **Básico / Estándar / Óptimo / Profesional**
   - Cada plan agrega más funciones (agenda, operadores, expediente médico, facturación, contacto).
   - Mapas con GPS (planes de pago).
   - Solo **Profesional** habilita **Paquetes y Promociones** con IA para generar campañas.

### Secciones del menú lateral (perfil médico)
- **Mi Perfil**
  - Información Mi Perfil → pestañas: *Servicios Principales*, *Enfermedades y Tratamientos*, *Mi Formación Profesional*.
  - Información Consultorio.
  - Supervisión de Opiniones.
  - Seguridad.
  - Mi Suscripción.

- **Mi Agenda**
  - Configuración de Agenda.
  - Administrar Operadores.
  - Administración de Citas.

- **Mis Pacientes**
  - Archivo de Pacientes.
  - Expediente Médico → pestañas: *Historia Clínica*, *Antecedentes Ginecoobstétricos*, *Exploración Física*, *Estudios Diagnóstico*, *Tratamiento/Recetas*, *Notas de Evolución*, *Manejo Hospitalario*, *Consentimiento Informado*, *Archivo*.

- **Facturación**
  - Crear factura / Listar facturas / Pacientes / Facturas canceladas.

- **Paquetes y Promociones**
  - Solo plan Profesional.

- **Resumen**
  - Estado general, barras de completitud, donut/gauge radial.

### Colores y tipografía
- Barra de estado perfil: `#5C7B91`
- Barra vigencia: `#004465`
- Fondo base central/interactivo: `#00B0C5`
- Botones principales contraídos: `#003152`
- Botón activo: `#00738F`
- Separadores: blanco con transparencia ~25–50%
- Tipografía: IBM Plex Sans (Google Font).

### Seguridad
- Aplica a todos los perfiles reclamados/generados con acceso admin.
- Incluye login, 2FA, control de sesiones.

## 🚀 Flujo de trabajo en la nueva conversación
1. Revisión botón por botón de cada sección.
2. Migración progresiva: mover estilos a `style.css` y scripts a `app.js`.
3. Entregables iterativos: validar en Wamp (`index.html` integrado) y entregar ZIP Dev.

---
Este `README_extra.md` garantiza que cualquier nueva conversación con ChatGPT tenga toda la información sin depender de chats previos.
