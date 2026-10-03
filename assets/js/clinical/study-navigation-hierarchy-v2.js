// HIER03: presentation-only study hierarchy. Clinical identities remain server-owned.
(function () {
  'use strict';
  const laboratory=window.mxmedLabNavigationV1?.config;
  if(!laboratory)return;
  const labGroups=new Map([...laboratory.primary,...laboratory.secondary,...laboratory.special].map(group=>[group.key,group]));
  const lab=key=>labGroups.get(key)?.parts||[];
  const group=(id,label,icon,parts,children=[],description='')=>({id,label,icon,parts,children,description});
  const nodes={
    laboratory:group('laboratory','LABORATORIO','science',[{category:'LABORATORIO'},{category:'GENETICA'}],
      ['chemistry','hematology','coagulation','endocrine','immunoserology','microbiology','genetics','urine','stool','tumor','drugs','panels'],
      'Análisis de muestras y pruebas de laboratorio.'),
    imaging:group('imaging','IMAGENOLOGÍA','image',[{category:'IMAGEN'},{category:'CARDIOVASCULAR',keys:['echo_tte','echo_tes','stress_echo','carotid_doppler','lower_ext_art_doppler','lower_ext_venous_doppler']}],
      ['radiography','ultrasound','tomography','magnetic_resonance','nuclear','breast_imaging','densitometry'],
      'Radiografías, ultrasonido, tomografía y resonancia.'),
    pathology:group('pathology','PATOLOGÍA Y BIOPSIAS','biotech',[{category:'PATOLOGIA'}],['cervical_cytology'],
      'Citologías y análisis de tejidos o muestras.'),
    functional:group('functional','ESTUDIOS FUNCIONALES','monitor_heart',[
      {category:'CARDIOVASCULAR',keys:['ecg_12lead','ecg_rhythm_strip','holter','abpm_mapa','stress_test','tilt_table','ankle_brachial_index']},
      {category:'NEUROFISIOLOGIA'},{category:'FUNCION_PULMONAR'},{category:'SUENO'},{category:'AUDIOLOGIA'}],
      ['cardiovascular','neurophysiology','pulmonary','sleep','audiovestibular'],
      'Evaluación de la función de órganos y sistemas.'),
    procedures:group('procedures','PROCEDIMIENTOS DIAGNÓSTICOS','gastroenterology',[{category:'ENDOSCOPIA'}],
      ['digestive_endoscopy','bronchoscopy','laryngoscopy','pleuroscopy'],
      'Endoscopías y procedimientos instrumentales.'),
    chemistry:group('chemistry','Química clínica','science',lab('chemistry')),
    hematology:group('hematology','Hematología','bloodtype',lab('hematology')),
    coagulation:group('coagulation','Coagulación','hematology',lab('coagulation')),
    endocrine:group('endocrine','Endocrinología y hormonas','endocrinology',lab('endocrine')),
    immunoserology:group('immunoserology','Inmunología y serología','vaccines',[],
      ['immunoglobulins','autoimmunity','hepatitis_serology','infectious_serology','inflammation']),
    microbiology:group('microbiology','Microbiología','microbiology',lab('microbiology')),
    genetics:group('genetics','Genética y diagnóstico molecular','genetics',[],
      ['cytogenetics','cytogenomics','genomic_sequencing','prenatal_genetics','germline_genetics','hereditary_oncology','pharmacogenomics','molecular_oncology']),
    urine:group('urine','Orina y otros fluidos','water_drop',[{category:'LABORATORIO',keys:["urinalysis", "microalbumin", "urine_albumin_creatinine_panel", "urine_protein_creatinine_panel", "urine_osmolality", "urine_culture", "csf_cell_count", "synovial_crystals", "semen_analysis"]}]),
    stool:group('stool','Materia fecal','science',lab('stool')),
    tumor:group('tumor','Marcadores tumorales','biotech',lab('tumor')),
    drugs:group('drugs','Monitoreo de fármacos','medication',lab('drugs')),
    panels:group('panels','Perfiles y paneles','view_module',lab('panels')),
    immunoglobulins:group('immunoglobulins','Inmunoglobulinas y complemento','vaccines',
      [{category:'LABORATORIO',keys:['iga','igg','igm','c3','c4']}]),
    autoimmunity:group('autoimmunity','Autoinmunidad','vaccines',[
      ...lab('autoimmunity'),{category:'LABORATORIO',keys:['rf']}]),
    hepatitis_serology:group('hepatitis_serology','Serologías de hepatitis','lab_profile',lab('serology')),
    infectious_serology:group('infectious_serology','Otras serologías infecciosas','lab_profile',lab('infectious_serology')),
    inflammation:group('inflammation','Inflamación y reactantes','science',lab('inflammation')),
    cytogenetics:group('cytogenetics','Citogenética','genetics',lab('cytogenetics')),
    cytogenomics:group('cytogenomics','Citogenómica y microarreglos','genetics',lab('cytogenomics')),
    genomic_sequencing:group('genomic_sequencing','Secuenciación genómica','genetics',lab('genomic_sequencing')),
    prenatal_genetics:group('prenatal_genetics','Tamiz genético prenatal','genetics',lab('prenatal_genetics')),
    germline_genetics:group('germline_genetics','Genética germinal','genetics',lab('germline_genetics')),
    hereditary_oncology:group('hereditary_oncology','Oncología hereditaria','genetics',lab('hereditary_oncology')),
    pharmacogenomics:group('pharmacogenomics','Farmacogenómica','genetics',lab('pharmacogenomics')),
    molecular_oncology:group('molecular_oncology','Oncología molecular','genetics',lab('molecular_oncology')),
    cervical_cytology:group('cervical_cytology','Citología cervical','biotech',[
      {category:'PATOLOGIA',keys:['cyto_pap','cyto_liquid_based']}]),
    radiography:group('radiography','Radiografía y fluoroscopía','radiology',[
      {category:'IMAGEN',keys:['rx_chest','rx_abdomen','rx_pelvis','rx_cspine','rx_lspine','rx_shoulder','rx_knee','rx_ankle','rx_hand',
        'fluoro_hsg','fluoro_vcug','fluoro_ugi','fluoro_barium_enema']}]),
    ultrasound:group('ultrasound','Ultrasonido','ultrasound',[],
      ['ultrasound_general','ultrasound_obgyn','ultrasound_cardiac','ultrasound_vascular']),
    ultrasound_general:group('ultrasound_general','Ultrasonido general','ultrasound',[
      {category:'IMAGEN',keys:['us_abdomen','us_renal','us_thyroid','us_soft_tissue','us_testicular']}]),
    ultrasound_obgyn:group('ultrasound_obgyn','Ultrasonido obstétrico y ginecológico','ultrasound',[
      {category:'IMAGEN',keys:['us_pelvic','us_obstetric_study']}]),
    ultrasound_cardiac:group('ultrasound_cardiac','Ultrasonido cardíaco','cardiology',[
      {category:'CARDIOVASCULAR',keys:['echo_tte','echo_tes','stress_echo']}]),
    ultrasound_vascular:group('ultrasound_vascular','Ultrasonido vascular / Doppler','cardiology',[
      {category:'CARDIOVASCULAR',keys:['carotid_doppler','lower_ext_art_doppler','lower_ext_venous_doppler']}]),
    tomography:group('tomography','Tomografía','clinical_notes',[{category:'IMAGEN',keys:['ct_head','ct_chest','ct_abdomen_pelvis','ct_uro']}]),
    magnetic_resonance:group('magnetic_resonance','Resonancia magnética','image',[
      {category:'IMAGEN',keys:['mr_brain','mr_knee','mr_shoulder','mr_abdomen']}]),
    nuclear:group('nuclear','Medicina nuclear y PET','biotech',[
      {category:'IMAGEN',keys:['nm_bone_scan','nm_thyroid_uptake','pet_ct']}]),
    breast_imaging:group('breast_imaging','Imagen mamaria','image',[
      {category:'IMAGEN',keys:['mammo','breast_us']}]),
    densitometry:group('densitometry','Densitometría','image',[{category:'IMAGEN',keys:['dexa']}]),
    cardiovascular:group('cardiovascular','Cardiovascular','cardiology',[
      {category:'CARDIOVASCULAR',keys:['ecg_12lead','ecg_rhythm_strip','holter','abpm_mapa','stress_test','tilt_table','ankle_brachial_index']}]),
    neurophysiology:group('neurophysiology','Neurofisiología','neurology',[{category:'NEUROFISIOLOGIA'}]),
    pulmonary:group('pulmonary','Función pulmonar','pulmonology',[{category:'FUNCION_PULMONAR'}]),
    sleep:group('sleep','Sueño','bedtime',[{category:'SUENO'}]),
    audiovestibular:group('audiovestibular','Audiología y función vestibular','hearing',[{category:'AUDIOLOGIA'}]),
    digestive_endoscopy:group('digestive_endoscopy','Endoscopia digestiva','gastroenterology',[
      {category:'ENDOSCOPIA',keys:['egd_eda_base','colonoscopy_base','flex_sig_base','anoscopy_base','proctoscopy_base',
        'ercp_cpre_base','eus_use_base','capsule_base','enteroscopy_base']}]),
    bronchoscopy:group('bronchoscopy','Broncoscopía','pulmonology',[
      {category:'ENDOSCOPIA',keys:['bronchoscopy_base','ebus_base']}]),
    laryngoscopy:group('laryngoscopy','Laringoscopía','gastroenterology',[
      {category:'ENDOSCOPIA',keys:['laryngoscopy_base']}]),
    pleuroscopy:group('pleuroscopy','Pleuroscopía','gastroenterology',[
      {category:'ENDOSCOPIA',keys:['pleuroscopy_base']}])
  };
  const root=['laboratory','imaging','pathology','functional','procedures'];
  const secondary={laboratory:['stool','tumor','drugs','panels']};
  const priority={
    laboratory:{general:['chemistry','hematology','urine','endocrine'],cardio:['chemistry','hematology','coagulation'],
      endocrine:['endocrine','chemistry','urine'],heme:['hematology','coagulation','genetics','immunoserology'],
      infect:['microbiology','immunoserology'],gi:['chemistry','stool'],oncology:['hematology','chemistry','genetics']},
    imaging:{cardio:['ultrasound']},
    ultrasound:{cardio:['ultrasound_cardiac','ultrasound_vascular']},
    functional:{cardio:['cardiovascular'],neuro:['neurophysiology','sleep'],peds_neuro:['neurophysiology','sleep'],
      pulm:['pulmonary','sleep'],ent:['audiovestibular','sleep']},
    procedures:{gi:['digestive_endoscopy'],pulm:['bronchoscopy'],ent:['laryngoscopy']}
  };
  function parts(id){
    const entry=nodes[id];if(!entry)return [];
    return entry.parts.length?entry.parts:entry.children.flatMap(parts);
  }
  function count(id,active){
    const found=new Set();
    for(const part of parts(id))for(const row of active.values())
      if(row.category_key===part.category&&(!part.keys||part.keys.includes(row.study_type_key)))found.add(row.study_type_key);
    return found.size;
  }
  function children(id,active,profile='general'){
    const entry=nodes[id];if(!entry)return [];
    const activeChildren=entry.children.filter(key=>count(key,active)>0);
    const favored=priority[id]?.[profile]||[];
    return activeChildren.sort((a,b)=>{
      const x=favored.indexOf(a),y=favored.indexOf(b);
      return (x<0?100:x)-(y<0?100:y)||entry.children.indexOf(a)-entry.children.indexOf(b);
    });
  }
  const allLaboratory={id:'all-laboratory',key:'all-laboratory',label:'Todos los estudios de laboratorio',parts:nodes.laboratory.parts};
  const allImaging={id:'all-imaging',label:'Todos los estudios de imagenología',parts:nodes.imaging.parts};
  window.mxmedStudyNavigationHierarchyV2=Object.freeze({version:2,root,nodes,secondary,priority,parts,count,children,allLaboratory,allImaging});
})();
