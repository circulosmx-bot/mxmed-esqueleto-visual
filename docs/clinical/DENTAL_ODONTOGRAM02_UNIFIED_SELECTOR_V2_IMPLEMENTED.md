# DENTAL-ODONTOGRAM02 — selector de ubicación dental V2

**IMPLEMENTED** desde `bd0a52661306305618a782490286b69a4e0ef68f`, con la referencia visual del Director como guía de interacción y geometría. La imagen no forma parte del producto. Los códigos y nombres provienen de la autoridad FDI existente, no de la numeración dibujada en la imagen.

## Autoridad y snapshot

`assets/data/clinical/dental-fdi-iso3950-v1.json` continúa siendo la lista única de 32 posiciones permanentes y 20 temporales. `assets/data/clinical/dental-location-authority-v2.json` añade `contract_version:2`, claves tipadas de ubicación y políticas para los siete estudios dentales actuales. Los payloads V2 usan `contract_version`, `location_type` y `selection_mode`, más los campos propios del tipo. `numbering_system: FDI_ISO_3950` y `dentition_mode` son obligatorios para piezas; el modo temporal visible `PRIMARY` corresponde a los códigos FDI 51–85. El lector V1 sigue aceptando `DECIDUOUS` sin transformar snapshots históricos.

Los tipos son `TOOTH_LOCATION` (una o varias piezas), `QUADRANT_LOCATION`, `ARCH_LOCATION`, `REGION_LOCATION` (incluida región bilateral) y `TMJ_LOCATION`. Cuadrante, arcada y región se guardan como intención estructurada, sin expandirlos a una lista de dientes. La ATM usa lateralidad propia y nunca un código FDI. El CBCT conserva `coverage` y `fov_cm` como parámetros del estudio; la radiografía de ATM conserva `projection`.

`api/_lib/clinical_dental_location_v2.php` valida versión, discriminador, campos permitidos, cardinalidad, pertenencia FDI/dentición, política del consumidor, región/lado/arco y las restricciones adicionales de CBCT/ATM. `clinical_dental_location.php` envía solo los snapshots V2 al validador nuevo; los V1 siguen su ruta original. El resumen de lectura se produce por versión. La escritura sigue dentro del `dental_location` de cada `order_item`; no se añadió tabla, migración, presencia dentaria, superficie, ni estado clínico odontológico.

## Componente e interacción

`assets/js/clinical/dental-odontogram-v2.js` expone `window.mxmedDentalOdontogramV2.mount(host, options)`, que devuelve `{ready, value, valid, issue, destroy}`, y `authority()`. Las opciones incluyen `authorityVersion`, `dentitionMode`, `allowedDentitionModes`, `allowedLocationModes`, `allowedRegions`, `minSelection`, `maxSelection`, `required`, `disabledFdiCodes`, `value` y `onChange`. El componente no conoce estudios ni procedimientos; un futuro adaptador de procedimientos puede aportar su política sin duplicar numeración o geometría.

Permanente muestra 32 posiciones; Temporal, 20. Mixta muestra cuatro carriles separados: maxilar permanente, maxilar temporal, mandíbula temporal y mandíbula permanente. Las piezas se dibujan como siluetas SVG ligeras con FDI visible y orientación derecha/izquierda del paciente. Los controles tienen área de 44×54 CSS px, estado de foco visible, seleccionado con relleno y borde turquesa, y estado deshabilitado con trazo discontinuo además de `disabled`. Tab entra al grupo; las flechas recorren posiciones; Enter/Espacio seleccionan. Un mensaje breve en `aria-live` anuncia cada cambio sin leer de nuevo todos los dientes. Los chips permiten retirar piezas y la limpieza aparece solo si existe una selección.

El cambio de dentición no descarta piezas silenciosamente: si la nueva dentición no contiene una pieza seleccionada, conserva Mixta y explica la incompatibilidad. La selección única sustituye la pieza anterior; la múltiple alterna cada pieza. En móvil el gráfico mantiene tamaño táctil y se desplaza dentro de su contenedor, con indicación visible de desplazamiento; no genera ancho extra en la página. El diálogo de configuración permite observar ambas arcadas a tamaño legible en escritorio. La edad no bloquea ni cambia la dentición: no existe un mecanismo seguro de sugerencia de edad en este flujo.

## Estudios actuales y compatibilidad

`assets/js/clinical/dental-location-v2-adapter.js` conecta la política versionada con el compositor de estudios. CBCT acepta piezas, cuadrante, arcada o región según la matriz; panorámica y cefalometría no piden ubicación; ATM usa lado y conserva vista; escaneo usa arco; fotografía conserva su control V1 de alcance; modelo conserva arco opcional. La proyección visual de un valor V1 no reescribe la orden hasta que el profesional haga una selección V2. Un V1 de ATM solo conoce vista: la interfaz no infiere lateralidad. Cuando un V1 contiene piezas y región libre a la vez, se informa de esa combinación histórica antes de editar.

La selección V2 usa el mismo `order_item` y los mismos escritores de órdenes. El catálogo activo permanece en 280 estudios; no se agregaron periapical ni bitewing. La navegación, enrutamiento y procedimientos clínicos permanecen iguales. DENTAL-CAT03A podrá reutilizar este componente cuando sus identidades y políticas se aprueben por separado. La matriz de consumidores DENTAL-ODONTOGRAM01 marca `IMPLEMENTED` solo para los siete estudios actuales; los consumidores hipotéticos y escenarios propuestos no activan productos nuevos.

## QA ejecutada

- `php modules/clinical/qa/dental_odontogram02_contract_gate.php`: 32/20 FDI, selección mixta, cinco tipos de ubicación, lectura V1 y rechazos de código, dentición, cantidad, duplicado, versión, campo desconocido y consumidor incompatible.
- `python3 modules/clinical/qa/dental_odontogram02_browser.py`: permanente, temporal y mixta en 1440×900, 1366×768 y 390×844; teclado, blancos táctiles, orientación, cambio no destructivo, cuadrante, arco, región, estado deshabilitado, adaptadores CBCT/ATM/escaneo/modelo, compositor con reapertura, proyección V1, sin error JavaScript ni ancho de documento extra. La misma prueba abre el selector en la página real de revisión médica de `127.0.0.1:18148` con solicitudes de escritura bloqueadas y guarda capturas en `/tmp/dental_odontogram02_live_*`.
- `bash modules/clinical/qa/dental_odontogram02_disposable_http.sh`: servidor HTTP autenticado y base desechable; escritura CBCT V2, impresión HTML, rechazos 422 con cero documentos y cero solicitudes de idempotencia adicionales, V1 compatible, ATM/escaneo/modelo, panorámica/cefalometría sin selector, fotografía V1 y lote separado de laboratorio/patología/imagen/funcional/procedimiento diagnóstico.
- `bash modules/clinical/qa/trt04_disposable_gate.sh`: procedimientos existentes, 60 comprobaciones de servicio y 7 HTTP. No se añadió flujo dental de procedimientos.

Los gates históricos `path_cat02b_disposable_gate.sh` e `img_cat02b_disposable_gate.sh` conservan contadores fijos de 263 y 278 estudios y fallan ahora en su primera aserción porque el catálogo aprobado tiene 280. El lote mixto desechable anterior valida sus rutas vigentes sin modificar esos scripts históricos.
