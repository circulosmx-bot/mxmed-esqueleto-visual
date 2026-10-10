/* INF-HELP01: one fictional case distinguishes the five Informe narratives. */
(function () {
  const modal = document.getElementById('modalInformeMedico');
  if (!modal || !window.mxmedExampleFieldHelp) return;

  const sections = [
    {
      key: 'reason', label: 'Motivo del informe',
      explanation: 'Indica de forma breve para qué se emite el informe o cuál es su finalidad principal.',
      text: 'Informe médico emitido para documentar la valoración y seguimiento de episodios recurrentes de palpitaciones y disnea de esfuerzo.'
    },
    {
      key: 'summary', label: 'Resumen clínico',
      explanation: 'Resume el contexto clínico, la evolución y los datos relevantes que permiten comprender el caso.',
      text: 'Paciente con episodios intermitentes de palpitaciones de varias semanas de evolución, acompañados ocasionalmente de falta de aire al esfuerzo. Refiere que los episodios son autolimitados y no se acompañan de pérdida del estado de alerta.'
    },
    {
      key: 'findings', label: 'Hallazgos / valoración médica',
      explanation: 'Describe los hallazgos relevantes de la valoración médica actual, evitando repetir la historia clínica.',
      text: 'Durante la valoración actual el paciente se encuentra consciente, orientado y clínicamente estable, sin datos de dificultad respiratoria en reposo.'
    },
    {
      key: 'impression', label: 'Impresión diagnóstica / diagnóstico',
      explanation: 'Registra el diagnóstico establecido, prediagnóstico o impresión clínica derivada de la información disponible.',
      text: 'Palpitaciones recurrentes en estudio. Se requiere ampliar valoración para determinar su origen.'
    },
    {
      key: 'plan', label: 'Plan / manejo / recomendaciones',
      explanation: 'Describe el manejo indicado, estudios, seguimiento o recomendaciones derivadas de la valoración.',
      text: 'Se recomienda completar valoración cardiovascular, considerar estudios complementarios según evolución y acudir a atención médica inmediata si se presentan síntomas de alarma.'
    }
  ];

  window.mxmedExampleFieldHelp.mount({
    modal,
    id: 'im-example',
    fieldMap: {
      im_reason: 'reason',
      im_clinical_summary: 'summary',
      im_findings: 'findings',
      im_diagnostic_impression: 'impression',
      im_plan: 'plan'
    },
    sections,
    contextLabel: 'Caso ficticio',
    contextText: 'Persona adulta con palpitaciones intermitentes y disnea de esfuerzo de varias semanas; clínicamente estable en la valoración actual, sin diagnóstico definitivo.',
    note: 'Referencia educativa. Adapta cada campo a la información clínica real antes de usarlo.',
    onUseExample: ({section, destination}) => {
      destination.value = section.text;
      destination.dispatchEvent(new Event('input', {bubbles: true}));
    }
  });
})();
