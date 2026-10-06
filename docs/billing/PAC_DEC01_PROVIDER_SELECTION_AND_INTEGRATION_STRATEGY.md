# PAC-DEC01 — Selección de proveedor y estrategia de integración CFDI

**Estado:** `CURRENT_DECISION` · `IMPLEMENTATION_PENDING`

**Fecha de decisión:** 2026-10-05
**Alcance:** documentación de la decisión; no compra, integración, credenciales ni activación productiva.

## Fuentes y grado de certeza

Este registro separa cuatro categorías: **CONFIRMED BY PROVIDER** (información comercial o técnica comunicada durante la evaluación y aportada a esta decisión), **CURRENT MXMED DECISION** (elección interna vigente), **ARCHITECTURAL RECOMMENDATION** (diseño objetivo, aún no implementado) y **MUST REVALIDATE BEFORE PRODUCTION** (contrato, precio o comportamiento que requiere confirmación vigente y prueba). La cotización de SW del 2026-10-01 y las aclaraciones de Edwin se registran como antecedentes comerciales aportados a MXMED; el repositorio no contiene la cotización original. Tampoco conserva el intercambio original de Finkok o su SLA. Ninguna de estas notas sustituye una oferta firmada ni una prueba de API.

Referencias técnicas públicas consultadas al documentar esta decisión: [SW Timbrado V4–CustomID](https://developers.sw.com.mx/knowledge-base/timbradov4-customid/), [SW cuentas hijo](https://developers.sw.com.mx/knowledge-base/funcionamiento-cuentas-hijo-administrador-de-timbres/) y [Finkok alta de clientes](https://wiki.finkok.com/home/alta-de-clientes). Su contenido debe revisarse de nuevo al implementar. La autoridad interna vigente está descrita en [FISC02B](FISC02B_ISSUANCE_CORE.md), [FISC02A/FISC01](../../modules/billing/README.md), `CfdiCertificationProviderPort` y `InvoiceIssuanceState`.

## Necesidad de México Médico

MXMED es un SaaS para múltiples emisores RFC independientes. El PAC elegido debe permitir operación multi-RFC, consumo compartido o consolidado cuando exista, y una estructura comercial sin penalización por RFC que impida escalar. Se requieren API, ambientes de prueba y producción, soporte, cancelación, consulta/reconciliación tras resultados ambiguos, recuperación confiable de timeout e idempotencia. La arquitectura debe permitir un segundo proveedor futuro. PDF y reenvío por correo generados por el PAC no son requisitos de selección.

## SW Smarter Timbrado Corporativo

**CONFIRMED BY PROVIDER — cotización comunicada el 2026-10-01.** Modalidad `PREPAID`; importes MXN:

| Timbres | Precio unitario antes de IVA | Subtotal antes de IVA | Total con IVA |
| ---: | ---: | ---: | ---: |
| 10,000 | $0.95 | $9,500 | $11,020 |
| 20,000 | $0.58 | $11,600 | $13,456 |
| 30,000 | $0.47 | $14,100 | $16,356 |
| 60,000 | $0.38 | $22,800 | $26,448 |

La cotización tenía **30 días de validez comercial**; los timbres adquiridos fueron descritos como **sin vencimiento**. Se comunicaron timbres multi-RFC, ambiente de pruebas gratuito, soporte técnico básico, un año de almacenamiento, cancelación sin consumo de timbre y soporte para varios tipos de CFDI. Puede haber ajustes anuales de precio. Edwin aclaró que no hay cargo adicional por agregar RFC ni límite declarado de cantidad de RFC, que pueden cotizarse volúmenes mayores o personalizados y que las condiciones comerciales son las mismas para cuentas bajo la modalidad multi-RFC/distribuidor. Estas condiciones corresponden a la propuesta evaluada y **deben reconfirmarse antes de contratar**.

**Servicios fuera de la cotización base, según Edwin:** cancelaciones masivas, validaciones adicionales, PDF generado por SW y reenvío de correo por SW; podrían cotizarse aparte según volumen. **Cancelación masiva no equivale a cancelación ordinaria.** La exclusión comercial de la primera no demuestra que la segunda carezca de API.

**Hallazgos técnicos previos y documentación pública:** SW admite un esquema distribuidor/multi-RFC. Una cuenta principal o dealer puede operar con cuentas hijo; éstas pueden tener credenciales o tokens propios. [La documentación de cuentas hijo](https://developers.sw.com.mx/knowledge-base/funcionamiento-cuentas-hijo-administrador-de-timbres/) también describe el uso de un token administrador para varios RFC si los certificados correspondientes están cargados. Credenciales separadas pueden ser convenientes, pero no son una obligación arquitectónica asumida para cada emisor MXMED. El RFC emisor continúa siendo parte de los datos del CFDI. SW recomendó Timbrado V4 con CustomID para el patrón discutido con MXMED.

**MUST REVALIDATE BEFORE PRODUCTION:** formato exacto, carga/custodia de CSD, autenticación, cuenta/token y alcance por RFC, consulta por identificador, cancelación ordinaria y sus estados, errores y condiciones de reintento. La [documentación pública de CustomID](https://developers.sw.com.mx/knowledge-base/timbradov4-customid/) lo describe como filtro contra duplicados con vigencia de **72 horas desde su primer uso**. Por ello, CustomID no puede ser la única defensa permanente contra duplicados: la identidad y el estado canónicos de MXMED deben sobrevivir a esa ventana. No se presume que una petición agotada pueda repetirse sin reconciliar.

### Recomendación comercial actual, no vinculante

Si se contrata SW bajo esta estructura, **20,000 timbres** es el lote inicial preferido para evaluación: cuesta $13,456 con IVA frente a $11,020 de 10,000; la diferencia de **$2,436 con IVA** añade 10,000 timbres. Su aparente ventaja depende de la vigencia sin vencimiento reportada y del consumo esperado. **No se autoriza compra aquí.** Antes de comprar, reconfirmar cotización, condiciones y volumen previsto de lanzamiento.

## Finkok permanece como alternativa viable

**Investigación comercial/técnica previa aportada a MXMED:** se evaluó el modelo Multiempresa y la modalidad On Demand, sin dependencia de una bolsa prepaga fija tradicional en el esquema analizado; facturación mensual consolidada y consumo agregado de RFC gestionados por el administrador. Se proporcionó información de SLA y se discutió una contingencia de buffer/cola para timbrado y cancelación. Finkok se consideró técnicamente apto para el modelo multi-RFC. Su [documentación de alta de clientes](https://wiki.finkok.com/home/alta-de-clientes) distingue OnDemand de Prepago, pero los términos comerciales concretos de MXMED, el SLA y la contingencia discutida requieren nueva confirmación. La referencia histórica de soporte aportada es el **ticket 211676**, cerrado tras no haber más respuesta; ese cierre no constituye rechazo técnico.

**CURRENT MXMED DECISION:** `PRIMARY_PAC_TARGET=SW` para la **primera integración productiva**. Pesan el modelo multi-RFC, ausencia reportada de cargo adicional o límite declarado por RFC, timbres sin vencimiento, consumo corporativo/distribuidor compartido compatible con MXMED, tramos de volumen competitivos, sandbox gratuito, API y la estrategia CustomID discutida. `SECONDARY_PAC_CANDIDATE=FINKOK`: sigue siendo viable para un proveedor secundario o contingencia futura. No se implementará dual PAC en V1 sólo porque ambos proveedores sean viables.

## Arquitectura y recuperación fiscal

**ARCHITECTURAL RECOMMENDATION:** evitar que el dominio Billing dependa de respuestas, identificadores o reintentos exclusivos de SW. El concepto futuro `PacProvider` debería cubrir timbrar, consultar, cancelar, consultar estado de cancelación y reconciliar. Los nombres exactos se decidirán a partir del Billing real. Objetivo inicial: `PacProvider → SwProvider`; posibilidad posterior: añadir `FinkokProvider`. PAC-DEC01 no implementa esa abstracción. El código actual ya reserva un puerto neutral `CfdiCertificationProviderPort` con `certify` y `reconcile`; `UnconfiguredPacAdapter` continúa rechazando ambos.

**Política ante timeout ambiguo:** MXMED crea una identidad interna estable de emisión y una correlación determinista para el PAC (CustomID donde corresponda); registra la tentativa canónica, envía la petición y, si no conoce el resultado, pasa a reconciliación. Si el CFDI ya fue timbrado, recupera **ese mismo** XML/UUID. Sólo si se confirma que no fue timbrado cabe un reintento controlado según el contrato vigente. **Nunca emitir otra factura fiscal a ciegas tras un timeout.** La vigencia declarada de CustomID refuerza la necesidad de idempotencia persistente propia. La futura integración debe respetar las tentativas por borrador/revisión, los estados `RECONCILIATION_REQUIRED`, las transiciones permitidas y el snapshot fiscal inmutable de FISC02B; ningún retry del adaptador puede saltarse esa autoridad.

**PDF y correo:** MXMED debería controlar su representación PDF legible cuando sea práctico; el artefacto fiscal autoritativo es el CFDI/XML timbrado y sus metadatos. El PDF o reenvío de SW no condiciona la selección. MXMED podrá distribuir documentos mediante su propia infraestructura de correo. Esta decisión no implementa PDF ni correo.

**Custodia:** almacenamiento del PAC es complementario, no archivo fiscal principal de MXMED. La arquitectura productiva debe mantener copias privadas bajo control MXMED de XML timbrado, PDF cuando exista, UUID, acuses y estados de cancelación, identidad del proveedor, marcas de tiempo, hashes de integridad y estado de verificación. Debe conservar la relación exacta con factura, borrador/revisión, paciente y médico conforme a FISC02A/FISC02B.

**Cancelación por validar:** API de cancelación ordinaria, consulta de estado, reconciliación de timeout, estados de aceptación/rechazo SAT, aceptación del receptor cuando aplique, diferencia operativa y comercial frente a cancelación masiva. La cotización no resuelve estos detalles.

**Seguridad:** credenciales y tokens PAC productivos, credenciales específicas por RFC, certificados, claves y demás secretos fiscales nunca se versionan en Git. Usar los mecanismos privados aprobados de MXMED. Este documento no contiene secretos.

## Estado actual y puerta de producción

Esta decisión **no cambia** el Billing existente: modelo de facturas, importación histórica XML/PDF, snapshots fiscales, custodia privada, hashes, relación médico-paciente, estados reservados para PAC y validaciones previas. FISC02B mantiene la certificación cerrada; no existe aquí `SwProvider`, timbrado productivo ni activación de cancelación.

Antes de habilitar SW en producción se exige una actividad de implementación y QA separada que verifique documentación API actual, autorización del PAC, sandbox, autenticación, multi-RFC real, CustomID e idempotencia, timeout ambiguo y reconciliación, búsqueda del CFDI, cancelación y estado, mapeo de errores, reintentos, observabilidad, custodia privada, vínculo exacto factura/orden y manejo de credenciales productivas. No se autoriza activación productiva mediante PAC-DEC01.

## WHEN BILLING / CFDI WORK RESUMES

1. Leer PAC-DEC01 y comprobar el HEAD vigente.
2. Inspeccionar la implementación real de Billing y sus estados/snapshots.
3. Revalidar la documentación actual de SW; reconfirmar cotización y consumo previsto si se contempla compra.
4. Usar primero el sandbox de SW y confirmar allí multi-RFC, CustomID, consultas y cancelación.
5. Diseñar o confirmar la abstracción `PacProvider` sobre la autoridad Billing existente.
6. Implementar `SwProvider` sin alterar la semántica fiscal canónica.
7. Ejecutar QA física de éxito, error y timeout/reconciliación con datos desechables.
8. Sólo después preparar una autorización separada de activación productiva.
