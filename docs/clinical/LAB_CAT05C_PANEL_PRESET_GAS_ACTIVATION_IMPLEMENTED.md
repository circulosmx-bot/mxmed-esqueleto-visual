# LAB-CAT05C — paneles reales y presets de laboratorio

## Autoridad y alcance

La migración `2026_10_04_25_lab_cat05c_panels.sql` activa exactamente dos tipos canónicos: `panel_quimica_6` y `arterial_blood_gas`. El catálogo pasa de 278 a 280 estudios activos y Laboratorio de 140 a 142. Ambos se enrutan por `CLINICAL_LAB`, sin crear un grupo ni modificar el esquema. Las definiciones de panel y presets conservan versión 1. Tiroides, T4 total, TB IGRA y AMH sérica continúan diferidos.

QS6 es **un solo `order_item`** con una definición inmutable de seis componentes: glucosa, urea, creatinina, ácido úrico, colesterol total y triglicéridos. Su muestra es suero. La gasometría arterial también es **un solo `order_item`**, con seis observaciones y muestra arterial. El compositor ofrece contexto de oxígeno desconocido, aire ambiente u oxígeno suplementario. Para el último acepta FiO₂ válida o dispositivo de administración; el contrato rechaza combinaciones inválidas antes de escribir.

Los tres presets activos (`preset_qs3_renal_v1`, `preset_qs3_lipids_v1`, `preset_hepatic_basic_v1`) son atajos de composición. Crean estudios canónicos individuales, deduplicados por identidad de estudio. La orden guarda `lab_preset_provenance` con versión, componentes y `order_item_id`; el preset nunca se convierte en estudio canónico ni en línea clínica del PDF. El proveedor se coteja por los estudios resultantes. QS6 requiere identidad de panel u oferta compatible verificada; seis analitos aislados no prueban capacidad para QS6.

## Navegación y búsqueda

QS6 y gasometría aparecen en Laboratorio → Perfiles y paneles y en búsqueda familiar; QS6, Química 6, GSA, ABG y nombres clínicos buscan sus identidades. Los presets aparecen en Química clínica y en la búsqueda de Laboratorio, identificados como atajos que agregan estudios individuales. La selección mantiene composición y procedencia al cambiar de pantalla.

## Validación

La base desechable recibió la migración y permitió emisión HTTP autenticada, rechazo sin escrituras, repetición idempotente, snapshots, resultado vinculado al `order_item_id` exacto y PDF real para QS6, gasometría y preset. Los tres PDF muestran solo líneas clínicas y el de gasometría presenta el contexto de oxígeno sin enum interno. La prueba de contrato LAB-CAT05B validó paneles históricos y reglas negativas. La interfaz se probó en 1440×900, 1366×768 con menú compacto y expandido, y 390×844. En el puerto 18148 se aplicaron solo las dos filas de catálogo; la inspección posterior fue de lectura y pasó en los mismos tamaños. No se alteraron órdenes ni resultados de la base de revisión.
