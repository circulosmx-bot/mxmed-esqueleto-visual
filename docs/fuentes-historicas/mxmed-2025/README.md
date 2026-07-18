# Fuentes históricas funcionales MXMed 2025

Este directorio preserva ocho documentos recibidos durante la reconciliación
`MXMED_HISTORICAL_FUNCTIONAL_DOCUMENTS_RECONCILIATION_V1`. Cada PDF se copió
byte a byte desde el intake validado. Sus hashes y páginas están fijados en
[`manifest.json`](./manifest.json).

## Estado de autoridad

Todos los PDF son `historical_noncanonical`. Su inclusión en `docs/` conserva
evidencia y contexto; no demuestra vigencia, implementación, aprobación,
cumplimiento legal ni comportamiento runtime. Tampoco prevalece sobre una
instrucción actual del director, un PP/contrato aprobado o una implementación
actual validada.

La precedencia aplicable es:

1. requisito actual y explícito del director;
2. PP o contrato canónico actual aprobado;
3. comportamiento implementado actual;
4. auditoría actual;
5. fuente funcional histórica;
6. fixture o proyección.

## Cómo citar

Una cita debe incluir `sourceId`, archivo, página y requisito `HIST-*`. Ejemplo:
`HIST-CLM-001; HIST-SRC-003; p. 1`. No debe citarse el directorio como si fuera
una especificación vigente ni transcribirse una página completa.

## Cómo promover un requisito

Un requisito sólo adquiere vigencia mediante decisión explícita del director,
PP-Decisiones posterior, implementación validada y actualización del contrato
canónico correspondiente. La reconciliación debe registrar autoridad,
clasificación, conflictos, recomendación, deuda y grupo especializado antes de
proponer su promoción.

## Contradicciones y uso seguro

Las contradicciones se registran con `conflict_requires_director`; nunca se
resuelven silenciosamente por antigüedad. Los elementos inseguros se clasifican
`reject_for_safety` o `superseded`, conservando su procedencia. Está prohibido
implementar directamente estos PDF, crear roles/estados/canales/proveedores por
inferencia o convertir sus fechas, precios, frecuencias y planes en autoridad
actual sin reconciliación y aprobación.

Documento de reconciliación: [MXMED_RECONCILIACION_DOCUMENTACION_HISTORICA_FUNCIONAL.md](../../MXMED_RECONCILIACION_DOCUMENTACION_HISTORICA_FUNCIONAL.md).
