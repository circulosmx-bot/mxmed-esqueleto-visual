# Catalogo de medicamentos MXMed

## Objetivo
Definir un formato canonico local para medicamentos y una ruta de importacion futura (offline) para que Receta no dependa de consultas en vivo durante la captura clinica.

## Fuentes estrategicas
- COFEPRIS: aporta referencias de registros sanitarios, nombres distintivos/comerciales y principios activos.
- Compendio Nacional de Insumos para la Salud: aporta normalizacion oficial de insumos para uso clinico.
- Catalogo local MXMed (JSON): version utilizable por frontend para busqueda rapida, estable y sin latencia de red.

## Formato canonico MXMed (drug concept)
Cada medicamento se modela como concepto (no fila plana):

```json
{
  "drug_id": "mxm_paracetamol",
  "generic_name": "Paracetamol",
  "brand_names": ["Tempra"],
  "aliases": ["acetaminofen"],
  "search_terms": ["paracetamol", "tempra"],
  "active_ingredients": ["Paracetamol"],
  "source_refs": [
    {
      "source": "cofepris",
      "ref_type": "registro_sanitario",
      "ref_value": "PENDIENTE",
      "notes": "Semilla local"
    }
  ],
  "presentations": [
    {
      "presentation_id": "tab_500",
      "label": "Tableta 500 mg",
      "form": "Tableta",
      "strength": "500 mg",
      "route": "VO",
      "pack": "Caja con 20 tabletas",
      "allowed_routes": ["VO"],
      "default_route": "VO",
      "dose_unit": "tableta",
      "administration_pattern": "tableta_oral",
      "default_dose_suggestions": ["1 tableta", "1/2 tableta"],
      "default_frequency_suggestions": ["Cada 8 horas", "Cada 12 horas"],
      "default_duration_suggestions": ["3 días", "5 días"],
      "instruction_hints": ["Tomar después de alimentos"]
    }
  ]
}
```

## Ubicacion de datos
- Seed actual local: `assets/data/medication_catalog_seed.json`
- Carga en frontend: `assets/js/app.js` con `RX_CATALOG_SEED_URL`

## API interna frontend (estable)
- `loadMedicationCatalog(data)`
- `rebuildMedicationCatalogIndex()`
- `searchMedicationCatalog(term)`

Estas funciones son la frontera de consumo del frontend. La UI de receta no debe acoplarse a detalles internos del archivo seed.

## Estrategia de importacion futura (sin scraping en vivo)
1. Obtener dataset externo (COFEPRIS/Compendio u otra fuente valida).
2. Transformar fuera de runtime al formato canonico MXMed.
3. Validar esquema minimo (`drug_id`, `generic_name`, `presentations[]`).
4. Publicar JSON versionado en `assets/data/`.
5. Cargar con `loadMedicationCatalog(...)` y reconstruir indice con `rebuildMedicationCatalogIndex()`.

## Principios operativos
- Frontend usa catalogo local para autocompletado robusto y rapido.
- "Agregar manualmente" permanece siempre disponible para no bloquear la atencion.
- Memoria del medico sigue como capa complementaria sobre el catalogo local.
- No se integra proveedor IA ni backend nuevo en esta subfase.

## Adaptacion contextual por presentacion (REC-RX-CTX / REC-ADM-2)
- El formulario de Receta aplica un patron de administracion segun `administration_pattern` o `form`.
- Se prioriza contexto guiado sin bloquear captura manual:
  - filtra y prioriza vias con `allowed_routes` + `default_route`
  - adapta chips de `dose`, `frequency`, `duration` e `instructions`
  - ajusta placeholders/hints segun `dose_unit` e `instruction_hints`
  - cuando existe patron contextual, ese patron domina y no mezcla chips genericos incompatibles
  - cuando no hay mapeo confiable a patron, cae en modo manual (fallback seguro)
  - las vias se muestran con etiqueta clinica legible en UI (ej. `VO` -> `Via oral`)
  - cantidad total inteligente:
    - patrones autocálculo (`tableta_oral`, `capsula_oral`, `suspension_oral`, `gotas_orales`, `supositorio`) calculan `quantity` con base en dosis/frecuencia/duracion cuando hay datos suficientes
    - patrones no autocálculo (`topico`, `inhalado`, `nasal`, inyectables variables) usan modo manual asistido por placeholder
    - se conserva compatibilidad de payload guardando `quantity` y metadatos auxiliares `quantity_mode`, `quantity_value`, `quantity_unit`
- Contrato tecnico centralizado de patrones (base):
  - `tableta_oral`
  - `capsula_oral`
  - `suspension_oral`
  - `gotas_orales`
  - `inyectable`
  - `topico`
  - `supositorio`
  - `inhalado`
  - `nasal`
- Alias/compatibilidad runtime:
  - `jarabe_oral`, `gotas_oticas`, `gotas_oftalmicas`
  - `crema_topica`, `unguento_topico`
  - `aerosol_inhalado`, `spray_nasal`, `ampolleta_im`, `ampolleta_iv`

Nota de transicion:
- "Perfil de administracion" se renombra a "patron de administracion" para evitar confusion
  con perfiles de usuario. La nomenclatura de codigo puede migrarse en fases futuras.

## REC-CAT-4 — Fortalecimiento para importacion futura

### Reglas de normalizacion recomendadas (dataset -> canonico MXMed)
1. Normalizar textos para busqueda: lowercase + sin acentos.
2. Deducir `search_terms` unificando:
   - `generic_name`
   - `brand_names[]`
   - `aliases[]`
   - `active_ingredients[]`
3. Eliminar duplicados por termino normalizado.
4. Rechazar conceptos sin:
   - `drug_id`
   - `generic_name`
   - al menos una `presentation.label`
5. Mantener `source_refs[]` para trazabilidad del origen.

### Flujo objetivo de importacion (offline)
1. Obtener dataset fuente (COFEPRIS/Compendio/mixto).
2. Transformar a formato canónico MXMed.
3. Validar esquema y consistencia minima.
4. Publicar seed versionado en `assets/data/medication_catalog_seed.json`.
5. Cargar en frontend con `loadMedicationCatalog(data)` y ejecutar `rebuildMedicationCatalogIndex()`.

### Reglas de busqueda UX que se deben preservar
- coincidencia exacta, por prefijo y por fragmento interno.
- sensibilidad insensible a acentos.
- soporte por alias y marca comercial.
- opcion permanente de "Agregar manualmente".
- memoria del medico como ranking/contexto, sin bloquear el catalogo base.

## REC-CAT-5 — Ampliacion semilla controlada (frecuentes en Mexico)

Se amplio la semilla local para uso intermedio real mientras llega la
importacion masiva offline.

Cobertura actual de seed local:
- 41 conceptos farmacologicos.
- familias reforzadas: analgesicos/antipireticos, AINES, antibioticos,
  antihipertensivos, diureticos, antidiabeticos, gastrointestinales,
  respiratorios, antihistaminicos, corticoides y dermatologicos.

Criterios aplicados:
- formato canónico MXMed por concepto.
- soporte de `brand_names`, `aliases` y `search_terms` enriquecidos.
- presentaciones con `route` y `pack`.
- trazabilidad minima con `source_refs`.
