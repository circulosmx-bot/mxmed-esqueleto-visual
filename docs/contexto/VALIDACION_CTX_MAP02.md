# Validación documental — CTX-MAP02

Fecha 2026-10-17; baseline fuente `1b8dfccb68a7613b2182507a840454631dbf012a`, rama `ux/consultation-step2-vitals-r1`. Validación de documentación e inventarios; **no** regresión funcional de producto ni certificación de operación actual.

| Comprobación | Resultado y alcance |
|---|---|
| Censo fuente | PASS: 2.967 paths idénticos a `git ls-tree -r` del baseline; sin omisiones/duplicados; doce módulos/19 raíces clasificados |
| Entrypoints | PASS: 183 candidatos únicos, todos en baseline; includes, backups y samples retirados del conteo; worker Media y cuatro entradas IaC/runtime incluidos |
| Capacidades | PASS: 44 generales + 59 clínicas + 16 financieras = 119 IDs de definición únicos por mapa; 18 componentes AWS y 18 integraciones externas |
| Journeys/recuperación | PASS: once J01–J11 trazados; doce preguntas respondidas con rutas/mapas/símbolos dirigidos; no doce tests de negocio |
| Enlaces | PASS: paths y anchors locales nuevos/introducidos comprobados; referencias históricas preexistentes sin cambios quedan fuera del conteo de enlaces nuevos |
| Referencias fuente CSV | PASS: 2.836 referencias path:línea en writers/readers/DDL/rutinas y candidatos originales existen en baseline y dentro del archivo |
| JSON/CSV | PASS: nueve CSV y dos JSON parseados; formatos/denominadores sincronizados con resumen |
| Objetos y writers | PASS de inventario: 204 nombres asignados a dominio; 36 gaps léxicos revisados y cinco filas dinámicas adicionales adjudicadas; límites y writers no localizados explícitos |
| Metadata física | PASS READ ONLY: 128 tablas, cero vistas, 1.734 columnas, 570 constraints, 138 FK/160 posiciones, 563 índices/921 posiciones, 35 triggers y once rutinas; padres FK presentes |
| Privacidad | Revisión de alcance y patrones sin hallazgos de claves Stripe/AWS, llaves privadas, emails reales o IDs de cuenta cloud. Snapshot sin defaults, cuerpos/definers privados, filas de negocio o configuración secreta |
| Contexto compacto | PASS: AGENTS 55 líneas; estado 83 líneas; detalles e inventarios permanecen bajo demanda |
| Historia | Cuatro mapas iniciales y README AWS/clínico conservados con aviso breve; ACT04 mantiene los tres tests como corregidos, PAC SW seleccionado |
| Alcance de archivos | Sólo Markdown y CSV/JSON documentales; producto, tests, configuración, SQL e IaC ejecutable intactos |

Se utilizó análisis temporal fuera del repositorio para parsear, cotejar paths/líneas, contar objetos/IDs y comprobar enlaces; no se agrega un runner ni automatización de fondo. La revisión cruzada de dominios corrigió grants, métodos/rutas, writers dinámicos y supuestos de atomicidad de booking/Media antes de publicar.

No se ejecutaron tests de producto, HTTP/browser suites, DDL/DML, stored procedures, restore/cleanup, proveedores AWS/Stripe/PAC/SMS/email, contenedores ni reinicios. La conexión local preexistente sólo leyó `VERSION()`/`information_schema` en transacción READ ONLY con rollback. Las evidencias QA y aceptación física previas conservan su fecha/commit/entorno en los mapas pertinentes.

Los resultados no verifican estado remoto, sesiones/membresías/cuentas reales, publicación actual o rutas dinámicas exhaustivas. Las incertidumbres se conservan en [cobertura](COBERTURA_Y_PENDIENTES_MXMED.md), el [mapa de datos](DATOS_Y_MIGRACIONES_MXMED.md), [AWS](AWS_Y_ENTORNOS_MXMED.md) y [finanzas/integraciones](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md). Próxima acción de producto/piloto: `NONE_PENDING_DIRECTOR_REVIEW`.
