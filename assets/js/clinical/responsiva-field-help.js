/* RESP-HELP01: one synthetic scenario, shown only in read-only field help. */
(function () {
  const modal = document.getElementById('modalResponsivaMedica');
  if (!modal || !window.mxmedExampleFieldHelp) return;

  const narrative = [
    {
      key: 'clinical_situation', label: 'Situación clínica',
      explanation: 'Describe los hechos clínicos que dan origen a la Responsiva: síntomas, hallazgos, evolución o circunstancias relevantes. Deja la recomendación y la decisión para sus propios campos.',
      text: 'Paciente consciente y orientado, con dolor abdominal persistente que ha aumentado durante las últimas horas. La causa del cuadro aún requiere valoración.'
    },
    {
      key: 'indicated_conduct', label: 'Indicación médica',
      explanation: 'Registra la atención o conducta recomendada por el médico para la situación descrita, sin repetir todo el contexto ni la decisión del paciente.',
      text: 'Se recomienda traslado a una unidad hospitalaria para valoración por urgencias, estudios complementarios y vigilancia clínica.'
    },
    {
      key: 'relevant_risk', label: 'Riesgo relevante informado',
      explanation: 'Resume los riesgos relevantes que se explicaron en relación con la decisión. No hace falta enumerar toda complicación imaginable.',
      text: 'Se explica que posponer la valoración hospitalaria puede retrasar la identificación de la causa del dolor y la atención de un posible empeoramiento.'
    },
    {
      key: 'declaration_text', label: 'Declaración y argumento',
      explanation: 'Documenta la manifestación principal del firmante: qué decisión toma después de recibir la información médica y cómo se relaciona con esta Responsiva.',
      text: 'Declaro que recibí explicación sobre mi situación actual, el traslado recomendado y los riesgos señalados. Por decisión propia, en este momento no acepto el traslado hospitalario y manifiesto haber comprendido la información proporcionada.'
    },
    {
      key: 'additional_manifestation', label: 'Manifestación adicional',
      explanation: 'Añade una aclaración o circunstancia que no figure en la declaración principal. Este campo puede quedar vacío.',
      text: 'El paciente refiere que un familiar lo acompañará y que buscará atención si presenta empeoramiento.'
    }
  ];
  const signer = [
    {
      key: 'character', label: 'Carácter del representante',
      explanation: 'Carácter es la calidad o capacidad en la que firma la persona. No equivale a su vínculo familiar con el paciente.',
      text: 'Tutor legal, responsable acompañante o representante legal, según corresponda al caso real.'
    },
    {
      key: 'relationship', label: 'Relación / parentesco',
      explanation: 'Relación o parentesco es el vínculo de la persona con el paciente. Se registra en un campo distinto y no necesita otro botón de ejemplo.',
      text: 'Madre, padre, hija o hermano, según corresponda al caso real.'
    }
  ];
  window.mxmedExampleFieldHelp.mount({
    modal, id: 'rm-example',
    fieldMap: {
      rm_clinical_situation: 'clinical_situation',
      rm_indicated_conduct: 'indicated_conduct',
      rm_relevant_risk: 'relevant_risk',
      rm_declaration_text: 'declaration_text',
      rm_additional_manifestation: 'additional_manifestation'
    },
    sections: narrative,
    contextLabel: 'Caso ficticio',
    contextText: 'Se recomienda valoración hospitalaria; el paciente decide no aceptar el traslado en ese momento.',
    note: 'Ejemplo únicamente orientativo. Adapta la redacción a la situación real del paciente.'
  });
  window.mxmedExampleFieldHelp.mount({
    modal, id: 'rm-signer-example',
    fieldMap: {rm_signer_character: 'character'},
    sections: signer,
    contextLabel: 'Distinción de campos',
    contextText: 'Carácter indica en qué calidad firma una persona; relación indica su vínculo con el paciente.',
    note: 'Ejemplos orientativos. Registra únicamente la información que corresponda a la persona firmante.'
  });
})();
