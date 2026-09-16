# FISC02B — núcleo de emisión CFDI 4.0

El compositor guarda **borradores**, no facturas emitidas. El timbrado está cerrado (`409 pac_provider_selection_required`) hasta que el Director seleccione un PAC vigente, se verifique su autorización y se apruebe el contrato técnico de firma. No se eligió proveedor ni se hizo llamada de sandbox o producción.

## Autoridades

- Receptor: `billing_patient_profiles` (FISC01), siempre dentro de la relación médico–paciente activa.
- Emisor: `billing_issuer_profiles`; 0..N por médico, máximo un predeterminado activo, archivado sin borrado. No deriva del nombre público del médico.
- CSD: `billing_csd_credentials`, unido al emisor. El registro comprueba parseo, contraseña, correspondencia de llave, RFC del sujeto cuando está disponible, serie y vigencia. **Un X.509 válido no prueba por sí mismo que sea CSD en vez de e.firma**: queda `UNVERIFIED` y no habilita emisión.
- Borrador mutable: `billing_invoice_drafts`, `billing_invoice_draft_items`, `billing_invoice_draft_item_taxes`. `billing_invoices` sigue siendo archivo final FISC02A; no se usa como borrador. Las nuevas columnas de emisor, pago y procedencia preparan su snapshot inmutable para una certificación futura.
- `billing_invoice_issuance_events` registra sólo identificadores, acción, resultado y fecha; nunca archivos, contraseñas, XML, llaves ni secretos del PAC.

## Catálogos y cálculo

`c_RegimenFiscal` y `c_UsoCFDI` reutilizan el catálogo FISC01 verificado el 2026-09-16 con la entrega SAT 2026-09-03. Las enumeraciones `c_FormaPago`, `c_MetodoPago`, `c_Moneda`, `c_ClaveProdServ`, `c_ClaveUnidad`, `c_ObjetoImp`, `c_Impuesto`, `c_TipoFactor` y `c_Exportacion` provienen del [XSD oficial SAT](https://www.sat.gob.mx/sitio_internet/cfd/catalogos/catCFDI.xsd), consultado el 2026-09-16 (SHA-256 `6c58936cb77576f839a4d7915953ceaf252b9eb9319f9458fe5bb67ae2bb0bb1`; `Last-Modified` 2024-12-13). El script `scripts/billing/build-sat-issuance-catalog.py` reproduce el artefacto comprimido desde el XSD local. La estructura del CFDI se basa en [Anexo 20](https://wwwmat.sat.gob.mx/consultas/35025/formato-de-factura-electronica-%28anexo-20%29); el vínculo PPD/99 y la exclusión PUE/30/99 para ingreso siguen la [guía SAT de forma y método de pago](https://www.sat.gob.mx/minisitio/Factura/documentos/infografias/formaspagometodopagos.pdf).

La autoridad de importes es el servidor con BCMath y columnas `DECIMAL(18,6)`: cantidad y valor unitario hasta 6 decimales; subtotal de línea = cantidad × valor unitario, redondeo decimal mitad hacia arriba a 6; descuento hasta 6 y nunca superior al subtotal; base de tasa = subtotal − descuento; base de cuota = cantidad; impuesto por concepto redondeado a 6; total = subtotal − descuentos + traslados − retenciones. El navegador no calcula importes definitivos. El borrador admite varios conceptos y combinaciones explícitas de impuestos. La validación previa al timbrado recalcula y coteja los importes persistidos.

El empaquetado PHP instala y comprueba `ext-bcmath`; esta extensión es obligatoria para el cálculo de borradores.

El XSD por sí solo **no contiene toda la vigencia temporal de las claves ni la compatibilidad de `c_TasaOCuota`** ni las reglas completas de precisión por moneda. Por ello, todas las claves de concepto exigen cotejo de vigencia antes de timbrar; cualquier tasa/cuota y moneda distinta de MXN agrega otro error. No se inventó una lista parcial de tasas. Los objetos fiscales `03`–`08` también requerirán cotejo de reglas específicas del catálogo vigente antes de activarse en un PAC. El XML generado es una **vista estructural sin sello**, no un CFDI fiscal completo ni validado criptográficamente.

## Custodia y puerta PAC

`CsdSecretPort` separa el dominio del almacenamiento. `EncryptedLocalCsdStore` guarda `.cer`, `.key` y contraseña cifrados AES-256-GCM en archivos 0600 fuera del document root; `MXMED_CSD_ENCRYPTION_KEY` debe ser una llave base64 de 32 bytes provista por secreto de entorno. No se almacena en Git ni en la base. Sin ella, el registro se cierra. El adaptador productivo puede reemplazar este puerto por un gestor de secretos.

`CfdiCertificationProviderPort` recibe una solicitud neutral al proveedor y una llave de idempotencia; su adaptador actual `UnconfiguredPacAdapter` rechaza certificar o reconciliar. El modelo de firma queda **sin decidir** hasta conocer el contrato del PAC seleccionado. No se afirma que éste acepte XML prefirmado ni que custodie la llave. La tabla `billing_invoice_certification_attempts` reserva una única tentativa por `(draft_id, revision)` y llave de idempotencia única, con estados `CERTIFICATION_PENDING`, `CERTIFIED`, `CERTIFICATION_FAILED` y `RECONCILIATION_REQUIRED`. Una respuesta ambigua deberá permanecer en reconciliación y **nunca repetirse automáticamente**. La integración futura deberá validar XML/UUID devueltos, persistir XML/PDF mediante la custodia privada FISC02A y cerrar factura y tentativa en una transacción; si el PAC sólo entrega XML no se inventará un PDF.

El estado del borrador está limitado a `DRAFT`, `VALIDATION_FAILED`, `READY`, `CERTIFICATION_PENDING`, `CERTIFIED`, `CERTIFICATION_FAILED` y `RECONCILIATION_REQUIRED`. En esta fase sólo se crean/actualizan borradores `DRAFT`; no existe transición HTTP a certificación. No se implementó cancelación, CFDI de pagos, ni navegación de Facturación en Expediente.

## Operación y QA

Aplicar `modules/billing/db/2026_09_16_03_create_invoice_issuance_core.sql` una vez tras FISC01/FISC02A. La migración se aplicó en la base local de revisión el 2026-09-16. `modules/billing/tests/PhysicalIssuanceQa.php` exige `MXMED_ALLOW_LOCAL_BILLING_QA=1` y host de DB loopback; crea datos sintéticos, prueba autoridad y cálculo, y los elimina al final. Los tests de CSD usan certificados sintéticos OpenSSL, nunca CSD reales.
