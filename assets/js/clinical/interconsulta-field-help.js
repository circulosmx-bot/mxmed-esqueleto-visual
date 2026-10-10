/* IX-HELP01: one fictional referral illustrates the boundaries between Interconsulta fields. */
(function () {
  const modal = document.getElementById('modalInterconsulta');
  if (!modal || !window.mxmedExampleFieldHelp) return;

  const sections = [
    {
      key: 'reason', label: 'Motivo de interconsulta',
      explanation: 'Resume en una frase o párrafo breve la razón principal por la que solicitas valoración por otro médico o servicio. No incluyas aquí toda la historia clínica.',
      text: 'Valoración por episodios recurrentes de palpitaciones asociados a disnea de esfuerzo, para descartar un posible origen cardiovascular.'
    },
    {
      key: 'summary', label: 'Resumen clínico de referencia',
      explanation: 'Describe el contexto clínico relevante que ayudará al especialista a comprender el caso: síntomas, evolución y hallazgos principales. Deja la petición concreta para Solicitud clínica puntual.',
      text: 'Paciente con episodios intermitentes de palpitaciones de varias semanas de evolución, acompañados en ocasiones de falta de aire al esfuerzo. En la valoración actual se encuentra clínicamente estable.'
    },
    {
      key: 'background', label: 'Antecedentes relevantes',
      explanation: 'Registra sólo antecedentes, comorbilidades, tratamientos o factores de contexto importantes para esta valoración.',
      text: 'Antecedente de hipertensión arterial en tratamiento. Sin antecedente conocido de arritmia diagnosticada.'
    },
    {
      key: 'request', label: 'Solicitud clínica puntual',
      explanation: 'Especifica qué valoración, opinión o intervención solicitas al médico o servicio receptor. Esto es distinto de explicar por qué se solicita la interconsulta.',
      text: 'Se solicita valoración por Cardiología para orientar el estudio de las palpitaciones y definir si requiere estudios o seguimiento especializado.'
    },
    {
      key: 'studies', label: 'Estudios relevantes',
      explanation: 'Incluye únicamente estudios o resultados que ya existan y sean relevantes para la interconsulta. Si no hay estudios disponibles, deja el campo vacío.',
      text: 'En este caso ficticio no se especifican estudios previos verificados. Registra aquí sólo resultados existentes y confirmados en el expediente.'
    },
    {
      key: 'comments', label: 'Comentarios al colega',
      explanation: 'Usa este espacio opcional sólo para una aclaración breve dirigida al colega que no esté contenida en el motivo, resumen o solicitud.',
      text: 'Agradezco valoración y recomendaciones para continuidad del seguimiento.'
    }
  ];

  window.mxmedExampleFieldHelp.mount({
    modal,
    id: 'ix-example',
    fieldMap: {
      ix_reason: 'reason',
      ix_summary: 'summary',
      ix_background: 'background',
      ix_request: 'request',
      ix_studies: 'studies',
      ix_comments: 'comments'
    },
    sections,
    contextLabel: 'Caso ficticio de ejemplo',
    contextText: 'Interconsulta a Cardiología.',
    note: 'Referencia educativa. Adapta cada campo a la información clínica real antes de usarlo.',
    canUseExample: ({section}) => section.key !== 'studies',
    onUseExample: ({section, destination}) => {
      destination.value = section.text;
      destination.dispatchEvent(new Event('input', {bubbles: true}));
    }
  });
})();
