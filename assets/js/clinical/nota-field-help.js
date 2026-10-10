/* NM-HELP01: one fictional case explains the seven Nota médica narratives. */
(function () {
  const modal = document.getElementById('modalNotaMedica');
  if (!modal || !window.mxmedExampleFieldHelp) return;

  const sections = [
    {
      key: 'reason', label: 'MOTIVO',
      explanation: 'Describe en forma breve la razón principal de la atención. Evita convertir este campo en toda la historia del padecimiento.',
      text: 'Dolor abdominal de 48 horas de evolución.'
    },
    {
      key: 'illness', label: 'PADECIMIENTO ACTUAL',
      explanation: 'Explica cómo inició el cuadro, cómo ha evolucionado y qué síntomas relevantes lo acompañan.',
      text: 'Inicia hace aproximadamente 48 horas con dolor abdominal difuso, que durante las últimas horas se ha localizado con mayor intensidad en el cuadrante inferior derecho. Refiere náusea, sin vómito persistente.'
    },
    {
      key: 'exam', label: 'EXPLORACIÓN FÍSICA',
      explanation: 'Registra los hallazgos relevantes obtenidos durante la exploración física, sin repetir la historia clínica.',
      text: 'Paciente consciente y orientado. Abdomen blando, con dolor a la palpación en cuadrante inferior derecho y sin datos evidentes de irritación peritoneal en esta valoración.'
    },
    {
      key: 'impression', label: 'IMPRESIÓN DIAGNÓSTICA',
      explanation: 'Registra el diagnóstico establecido, prediagnóstico o impresión clínica derivada de la información disponible.',
      text: 'Dolor abdominal en estudio, con sospecha clínica de proceso inflamatorio intraabdominal. Requiere vigilancia y valoración complementaria.'
    },
    {
      key: 'plan', label: 'TRATAMIENTO E INDICACIONES',
      explanation: 'Describe el manejo indicado, tratamiento, recomendaciones y conducta clínica derivada de la valoración.',
      text: 'Se indican medidas generales, vigilancia de evolución y revaloración médica. Se explican signos de alarma y la necesidad de acudir a atención urgente si presenta empeoramiento.'
    },
    {
      key: 'analysis', label: 'ANÁLISIS CLÍNICO',
      explanation: 'Utiliza este campo para explicar brevemente cómo integras los datos clínicos y por qué orientan tu impresión o conducta.',
      text: 'La localización progresiva del dolor y los hallazgos de la exploración hacen necesario mantener vigilancia clínica y considerar estudios complementarios para aclarar la causa.'
    },
    {
      key: 'studies', label: 'ESTUDIOS SUGERIDOS',
      explanation: 'Registra los estudios que consideras pertinentes para continuar la valoración. Este texto no crea una orden de estudios.',
      text: 'Considerar biometría hemática y estudio de imagen abdominal según evolución y criterio clínico.'
    }
  ];
  const typeLabels = {
    consulta_inicial: 'Consulta inicial',
    nota_evolucion: 'Nota de evolución',
    nota_subsecuente: 'Nota subsecuente'
  };

  window.mxmedExampleFieldHelp.mount({
    modal,
    id: 'nm-example',
    fieldMap: {
      nm_motivo_consulta: 'reason',
      nm_padecimiento_actual: 'illness',
      nm_exploracion_fisica: 'exam',
      nm_impresion_diagnostica: 'impression',
      nm_tratamiento_indicaciones: 'plan',
      nm_analisis_clinico: 'analysis',
      nm_estudios_sugeridos: 'studies'
    },
    sections,
    contextLabel: 'Caso ficticio',
    contextText: 'Persona adulta con dolor abdominal de 48 horas, inicialmente difuso y después más localizado, con náusea y estabilidad clínica en esta valoración.',
    note: 'Referencia educativa. Adapta cada campo a la valoración real. El texto de Estudios sugeridos no crea una orden.',
    resolveScenario: () => ({
      categoryLabel: `Ejemplo de redacción para: ${typeLabels[modal.querySelector('#nm_note_type')?.value] || typeLabels.consulta_inicial}`
    }),
    onUseExample: ({section, destination}) => {
      destination.value = section.text;
      destination.dispatchEvent(new Event('input', {bubbles: true}));
    }
  });
})();
