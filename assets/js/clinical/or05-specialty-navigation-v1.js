// OR05-NAVMAP02: V1 physician-facing navigation configuration. Clinical study identity remains server-owned.
// Source: docs/clinical/OR05_NAVMAP01_SPECIALTY_NAVIGATION_MATRIX_V1_PROPOSED.md.
(function () {
  'use strict';
  const config = {
  "version": 1,
  "groups": {
    "primary": [
      {
        "id": "lab",
        "label": "LABORATORIO",
        "description": "Análisis clínicos y pruebas de laboratorio.",
        "icon": "science",
        "parts": [
          {
            "category": "LABORATORIO"
          },
          {
            "category": "GENETICA"
          },
          {
            "category": "PATOLOGIA"
          }
        ]
      },
      {
        "id": "imaging",
        "label": "IMAGENOLOGÍA",
        "description": "Estudios de imagenología y radiología.",
        "icon": "image",
        "parts": [
          {
            "category": "IMAGEN"
          },
          {
            "category": "CARDIOVASCULAR",
            "keys": [
              "echo_tte",
              "echo_tes",
              "stress_echo",
              "carotid_doppler",
              "lower_ext_art_doppler",
              "lower_ext_venous_doppler"
            ]
          }
        ]
      },
      {
        "id": "functional",
        "label": "ESTUDIOS FUNCIONALES",
        "description": "Pruebas cardiovasculares, respiratorias y neurofisiológicas.",
        "icon": "monitor_heart",
        "parts": [
          {
            "category": "CARDIOVASCULAR",
            "keys": [
              "ecg_12lead",
              "ecg_rhythm_strip",
              "holter",
              "abpm_mapa",
              "stress_test",
              "tilt_table",
              "ankle_brachial_index"
            ]
          },
          {
            "category": "NEUROFISIOLOGIA"
          },
          {
            "category": "FUNCION_PULMONAR"
          },
          {
            "category": "SUENO"
          },
          {
            "category": "AUDIOLOGIA"
          }
        ]
      },
      {
        "id": "diagnostic",
        "label": "PROCEDIMIENTOS DIAGNÓSTICOS",
        "description": "Estudios endoscópicos del catálogo.",
        "icon": "biotech",
        "parts": [
          {
            "category": "ENDOSCOPIA"
          }
        ]
      }
    ],
    "quick": {
      "C": {
        "id": "cardiology",
        "label": "Cardiología",
        "icon": "cardiology",
        "parts": [
          {
            "category": "CARDIOVASCULAR"
          }
        ]
      },
      "N": {
        "id": "neurophysiology",
        "label": "Neurofisiología",
        "icon": "neurology",
        "parts": [
          {
            "category": "NEUROFISIOLOGIA"
          }
        ]
      },
      "F": {
        "id": "pulmonary",
        "label": "Función pulmonar",
        "icon": "pulmonology",
        "parts": [
          {
            "category": "FUNCION_PULMONAR"
          }
        ]
      },
      "P": {
        "id": "cytology",
        "label": "Citología",
        "icon": "biotech",
        "parts": [
          {
            "category": "PATOLOGIA"
          }
        ]
      },
      "E": {
        "id": "endoscopy",
        "label": "Endoscopía",
        "icon": "gastroenterology",
        "parts": [
          {
            "category": "ENDOSCOPIA"
          }
        ]
      },
      "S": {
        "id": "sleep",
        "label": "Sueño",
        "icon": "bedtime",
        "parts": [
          {
            "category": "SUENO"
          }
        ]
      },
      "A": {
        "id": "audiology",
        "label": "Audiología",
        "icon": "hearing",
        "parts": [
          {
            "category": "AUDIOLOGIA"
          }
        ]
      },
      "X": {
        "id": "genetics",
        "label": "Genética",
        "icon": "genetics",
        "parts": [
          {
            "category": "GENETICA"
          }
        ]
      },
      "D2": {"id":"dental-radiology","label":"Radiología dental 2D","icon":"radiology","parts":[
        {"category":"IMAGEN","keys":["dental_panoramic_xray","dental_cephalometric_xray","tmj_comparative_xray"]}]},
      "CB": {"id":"dental-cbct","label":"Cone Beam / CBCT","icon":"view_in_ar","parts":[
        {"category":"IMAGEN","keys":["dental_cbct"]}]},
      "RO": {"id":"dental-records","label":"Registros ortodóncicos","icon":"dentistry","parts":[
        {"category":"IMAGEN","keys":["dental_panoramic_xray","dental_cephalometric_xray"]},
        {"category":"DENTAL","keys":["dental_clinical_photographs","dental_intraoral_scan","dental_study_model"]}]},
      "EM": {"id":"dental-scan-models","label":"Escaneo y modelos","icon":"dentistry","parts":[
        {"category":"DENTAL","keys":["dental_intraoral_scan","dental_study_model"]}]}
    },
    "lower": [
      {
        "id": "cardiac-ultrasound",
        "label": "Ultrasonido cardiaco",
        "icon": "cardiology",
        "parts": [{"category": "CARDIOVASCULAR", "keys": ["echo_tte", "echo_tes", "stress_echo"]}]
      },
      {
        "id": "vascular-ultrasound",
        "label": "Ultrasonido vascular / Doppler",
        "icon": "cardiology",
        "parts": [{"category": "CARDIOVASCULAR", "keys": ["carotid_doppler", "lower_ext_art_doppler", "lower_ext_venous_doppler"]}]
      },
      {
        "id": "ophthalmology",
        "label": "Oftalmología",
        "icon": "visibility",
        "parts": [
          {
            "category": "OFTALMOLOGIA"
          }
        ]
      },
      {
        "id": "other",
        "label": "Otros estudios",
        "icon": "science",
        "parts": [
          {
            "category": "OTROS"
          }
        ]
      }
    ]
  },
  "profiles": {
    "allergy": {
      "quick": [
        "F"
      ],
      "promoted": [
        "spirometry"
      ]
    },
    "anesthesia": {
      "quick": [
        "F",
        "C"
      ],
      "promoted": [
        "capnography",
        "ecg_12lead"
      ]
    },
    "audiology": {
      "quick": [
        "A",
        "N"
      ],
      "promoted": [
        "audiometry_tonal",
        "tympanometry",
        "vng"
      ]
    },
    "cardio": {
      "quick": [
        "C",
        "F",
        "S"
      ],
      "promoted": [
        "ecg_12lead",
        "echo_tte",
        "holter",
        "abpm_mapa"
      ]
    },
    "clinical_lab": {
      "quick": [],
      "promoted": []
    },
    "colposcopy": {
      "quick": [
        "P"
      ],
      "promoted": [
        "cyto_pap",
        "cyto_liquid_based"
      ]
    },
    "critical": {
      "quick": [
        "F",
        "C"
      ],
      "promoted": [
        "capnography",
        "blood_culture",
        "ecg_12lead"
      ]
    },
    "dental": {
      "quick": ["D2","CB","EM"],
      "promoted": ["dental_panoramic_xray","dental_intraoral_scan","dental_cbct"]
    },
    "dental_ortho": {"quick":["RO","D2","EM","CB"],"promoted":["dental_panoramic_xray","dental_cephalometric_xray","dental_clinical_photographs","dental_intraoral_scan","dental_study_model"]},
    "dental_implant": {"quick":["CB","D2","EM"],"promoted":["dental_cbct","dental_panoramic_xray","dental_intraoral_scan","dental_study_model"]},
    "dental_endo": {"quick":["D2","CB"],"promoted":["dental_panoramic_xray"]},
    "dental_perio": {"quick":["D2","CB"],"promoted":["dental_panoramic_xray","dental_clinical_photographs"]},
    "dental_maxillofacial": {"quick":["CB","D2"],"promoted":["dental_cbct","dental_panoramic_xray","tmj_comparative_xray"]},
    "dental_prosthetic": {"quick":["EM","D2"],"promoted":["dental_intraoral_scan","dental_study_model","dental_clinical_photographs"]},
    "dental_aesthetic": {"quick":["EM","RO","D2"],"promoted":["dental_intraoral_scan","dental_clinical_photographs","dental_study_model"]},
    "dental_pathology": {"quick":["D2","CB"],"promoted":[]},
    "derm": {
      "quick": [],
      "promoted": []
    },
    "endocrine": {
      "quick": [],
      "promoted": [
        "hba1c",
        "tsh",
        "ft4"
      ]
    },
    "ent": {
      "quick": [
        "A",
        "E",
        "S"
      ],
      "promoted": [
        "audiometry_tonal",
        "tympanometry",
        "laryngoscopy_base"
      ]
    },
    "general_med": {
      "quick": [
        "C",
        "N",
        "F",
        "P",
        "E",
        "S",
        "A",
        "X"
      ],
      "promoted": []
    },
    "geriatric": {
      "quick": [],
      "promoted": [
        "dexa",
        "cbc",
        "creatinine"
      ]
    },
    "gi": {
      "quick": [
        "E"
      ],
      "promoted": [
        "egd_eda_base",
        "colonoscopy_base",
        "us_abdomen"
      ]
    },
    "head_neck_surgery": {
      "quick": [
        "E"
      ],
      "promoted": [
        "laryngoscopy_base"
      ]
    },
    "heme": {
      "quick": [],
      "promoted": [
        "cbc",
        "ferritin",
        "aptt"
      ]
    },
    "imaging": {
      "quick": [],
      "promoted": []
    },
    "infect": {
      "quick": [],
      "promoted": [
        "blood_culture",
        "urine_culture",
        "hiv_ag_ac"
      ]
    },
    "nephro": {
      "quick": [],
      "promoted": [
        "creatinine",
        "microalbumin",
        "us_renal"
      ]
    },
    "neuro": {
      "quick": [
        "N",
        "S"
      ],
      "promoted": [
        "eeg_routine",
        "emg_ncs",
        "mr_brain"
      ]
    },
    "nuclear": {
      "quick": [],
      "promoted": [
        "nm_bone_scan",
        "nm_thyroid_uptake",
        "pet_ct"
      ]
    },
    "nursing": {
      "quick": [],
      "promoted": []
    },
    "nutrition": {
      "quick": [],
      "promoted": [
        "hba1c",
        "chol_total",
        "triglycerides"
      ]
    },
    "obgyn": {
      "quick": [
        "P",
        "X"
      ],
      "promoted": [
        "bhcg",
        "us_obstetric_study",
        "nipt",
        "cyto_pap"
      ]
    },
    "oncology": {
      "quick": [
        "X"
      ],
      "promoted": [
        "hereditary_cancer_germline",
        "pet_ct",
        "somatic_tumor_ngs"
      ]
    },
    "ophthal": {
      "quick": [],
      "promoted": [
        "evoked_visual"
      ]
    },
    "ortho": {
      "quick": [],
      "promoted": [
        "dexa"
      ]
    },
    "other": {
      "quick": [],
      "promoted": []
    },
    "palliative": {
      "quick": [],
      "promoted": []
    },
    "pathology": {
      "quick": [
        "P",
        "X"
      ],
      "promoted": [
        "cyto_pap",
        "cyto_liquid_based"
      ]
    },
    "peds": {
      "quick": [
        "A",
        "F",
        "N"
      ],
      "promoted": [
        "otoacoustic_emissions",
        "cbc",
        "us_abdomen"
      ]
    },
    "peds_dental": {
      "quick": ["D2","RO","EM"],
      "promoted": ["dental_panoramic_xray","dental_clinical_photographs"]
    },
    "peds_nephro": {
      "quick": [],
      "promoted": [
        "us_renal",
        "creatinine",
        "urinalysis"
      ]
    },
    "peds_neuro": {
      "quick": [
        "N",
        "S"
      ],
      "promoted": [
        "eeg_routine",
        "video_eeg",
        "mr_brain"
      ]
    },
    "peds_onc": {
      "quick": [
        "X"
      ],
      "promoted": [
        "cbc",
        "somatic_tumor_ngs"
      ]
    },
    "peds_pulm": {
      "quick": [
        "F",
        "S"
      ],
      "promoted": [
        "spirometry",
        "full_pft",
        "overnight_oximetry"
      ]
    },
    "peds_surgery": {
      "quick": [],
      "promoted": []
    },
    "physio": {
      "quick": [
        "N"
      ],
      "promoted": [
        "emg_ncs",
        "dexa"
      ]
    },
    "podiatry": {
      "quick": [],
      "promoted": []
    },
    "psychiatry": {
      "quick": [
        "S"
      ],
      "promoted": [
        "psg_diagnostic"
      ]
    },
    "psychology": {
      "quick": [],
      "promoted": []
    },
    "publichealth": {
      "quick": [],
      "promoted": []
    },
    "pulm": {
      "quick": [
        "F",
        "S",
        "E"
      ],
      "promoted": [
        "spirometry",
        "full_pft",
        "ct_chest",
        "bronchoscopy_base"
      ]
    },
    "qfb": {
      "quick": [],
      "promoted": []
    },
    "rehab": {
      "quick": [
        "N"
      ],
      "promoted": [
        "emg_ncs",
        "dexa"
      ]
    },
    "rheum": {
      "quick": [],
      "promoted": [
        "ana",
        "anti_ccp",
        "rf"
      ]
    },
    "surgery": {
      "quick": [
        "E"
      ],
      "promoted": [
        "ct_abdomen_pelvis"
      ]
    },
    "surgery_narrow": {
      "quick": [],
      "promoted": []
    },
    "urology": {
      "quick": [],
      "promoted": [
        "urinalysis",
        "us_renal",
        "ct_uro"
      ]
    },
    "vascular": {
      "quick": [
        "C"
      ],
      "promoted": [
        "carotid_doppler",
        "lower_ext_art_doppler",
        "lower_ext_venous_doppler",
        "ankle_brachial_index"
      ]
    }
  },
  "specialties": {
    "Alergología": "allergy",
    "Anatomía Patológica": "pathology",
    "Anestesiología": "anesthesia",
    "Angiología y Cirugía Vascular": "vascular",
    "Análisis Clínicos": "clinical_lab",
    "Audiología": "audiology",
    "Banco de Sangre": "qfb",
    "Cardiología": "cardio",
    "Cirugía Bariátrica": "gi",
    "Cirugía Cabeza y Cuello": "head_neck_surgery",
    "Cirugía Cardiovascular": "cardio",
    "Cirugía Gastrointestinal": "gi",
    "Cirugía General": "surgery",
    "Cirugía Laparoscópica": "surgery",
    "Cirugía Maxilofacial": "dental_maxillofacial",
    "Cirugía Oncológica Pediátrica": "peds_onc",
    "Cirugía Oral y Maxilofacial": "dental_maxillofacial",
    "Cirugía Pediátrica": "peds_surgery",
    "Cirugía Plástica": "surgery_narrow",
    "Cirugía Torácica": "pulm",
    "Cirugía de Columna": "surgery_narrow",
    "Cirugía de Mano": "surgery_narrow",
    "Cirugía de Pie": "surgery_narrow",
    "Coloproctología": "gi",
    "Colposcopía": "colposcopy",
    "Cuidados Paliativos": "palliative",
    "Dentista": "dental",
    "Dermatología": "derm",
    "Diabetología": "endocrine",
    "Endocrinología": "endocrine",
    "Endodoncia": "dental_endo",
    "Enfermería Comunitaria": "nursing",
    "Enfermería General": "nursing",
    "Enfermería Geriátrica": "nursing",
    "Enfermería Obstétrica": "nursing",
    "Enfermería Pediátrica": "nursing",
    "Enfermería Quirúrgica": "nursing",
    "Enfermería en Cuidados Intensivos": "nursing",
    "Estudios de Diagnóstico": "imaging",
    "Farmacia Clínica": "qfb",
    "Fisioterapia Deportiva": "physio",
    "Fisioterapia Geriátrica": "physio",
    "Fisioterapia Neurológica": "physio",
    "Fisioterapia Ortopédica": "physio",
    "Fisioterapia Pediátrica": "physio",
    "Gastroenterología": "gi",
    "Geriatría": "geriatric",
    "Ginecología y Obstetricia": "obgyn",
    "Hematología": "heme",
    "Hematología de Laboratorio": "qfb",
    "Implantología": "dental_implant",
    "Implantología Dental": "dental_implant",
    "Infectología": "infect",
    "Inmunología": "qfb",
    "Kinesiología": "physio",
    "Medicina Crítica": "critical",
    "Medicina Estética": "derm",
    "Medicina Familiar": "general_med",
    "Medicina Física y Rehabilitación": "rehab",
    "Medicina General": "general_med",
    "Medicina Integrada": "general_med",
    "Medicina Interna": "general_med",
    "Medicina Nuclear": "nuclear",
    "Medicina de Rehabilitación": "rehab",
    "Medicina del Deporte": "rehab",
    "Medicina del Trabajo": "publichealth",
    "Microbiología": "qfb",
    "Nefrología": "nephro",
    "Nefrología Pediátrica": "peds_nephro",
    "Neumología": "pulm",
    "Neumología Pediátrica": "peds_pulm",
    "Neurocirugía": "neuro",
    "Neurología": "neuro",
    "Neurología Pediátrica": "peds_neuro",
    "Neuropsicología": "psychology",
    "Nutrición Bariátrica": "nutrition",
    "Nutrición Clínica": "nutrition",
    "Nutrición Deportiva": "nutrition",
    "Nutrición Geriátrica": "nutrition",
    "Nutrición Oncológica": "nutrition",
    "Nutrición Pediátrica": "nutrition",
    "Nutrición Renal": "nutrition",
    "Nutrición en Diabetes": "nutrition",
    "Nutriología": "nutrition",
    "Odontología": "dental",
    "Odontología Estética": "dental_aesthetic",
    "Odontopediatría": "peds_dental",
    "Oftalmología": "ophthal",
    "Oncología": "oncology",
    "Optometría": "ophthal",
    "Ortodoncia": "dental_ortho",
    "Ortopedia Dental": "dental_ortho",
    "Ortopedia y Traumatología": "ortho",
    "Otorrinolaringología": "ent",
    "Otra (especificar)": "other",
    "Patología": "pathology",
    "Patología Bucal": "dental_pathology",
    "Patología Clínica": "clinical_lab",
    "Pediatría": "peds",
    "Periodoncia": "dental_perio",
    "Podología": "podiatry",
    "Proctología": "gi",
    "Prótesis Bucal": "dental_prosthetic",
    "Psicología": "psychology",
    "Psicología Clínica": "psychology",
    "Psicología Educativa": "psychology",
    "Psicología Infantil": "psychology",
    "Psicología Organizacional": "psychology",
    "Psicoterapia": "psychology",
    "Psiquiatría": "psychiatry",
    "Química Clínica": "qfb",
    "Radiología e Imagen": "imaging",
    "Rehabilitación Física": "physio",
    "Rehabilitación Oral": "dental_prosthetic",
    "Rehabilitación Postquirúrgica": "physio",
    "Reumatología": "rheum",
    "Salud Pública": "publichealth",
    "Terapia Familiar": "psychology",
    "Terapia Manual": "physio",
    "Terapia de Pareja": "psychology",
    "Toxicología": "qfb",
    "Traumatología y Ortopedia": "ortho",
    "Urgencias Médico Quirúrgicas": "general_med",
    "Urología": "urology"
  },
  "professionalTitles": {
    "Médico Cirujano": "general_med",
    "medico_cirujano": "general_med",
    "Médico General": "general_med",
    "medico_general": "general_med",
    "Médico Cirujano y Partero": "general_med",
    "medico_cirujano_partero": "general_med",
    "Cirujano Dentista": "dental",
    "cirujano_dentista": "dental",
    "Licenciado en Nutrición": "nutrition",
    "lic_nutricion": "nutrition",
    "Licenciado en Psicología": "psychology",
    "lic_psicologia": "psychology",
    "Licenciado en Fisioterapia": "physio",
    "lic_fisioterapia": "physio",
    "Licenciado en Rehabilitación": "physio",
    "lic_rehabilitacion": "physio",
    "Químico Farmacobiólogo": "qfb",
    "qfb": "qfb",
    "Enfermería": "nursing",
    "enfermeria": "nursing"
  },
  "aliases": {},
  "parentProfiles": {},
  "generalProfile": "general_med",
  "families": {
    "dental": ["dental","dental_ortho","dental_implant","dental_endo","dental_perio","dental_maxillofacial","dental_prosthetic","dental_aesthetic","dental_pathology","peds_dental"],
    "other": ["clinical_lab","nursing","nutrition","psychology","physio","rehab","qfb","podiatry","other"]
  }
};
  const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('es');
  const specialtyMap = new Map(Object.entries(config.specialties).map(([label, profile]) => [normalize(label), profile]));
  const titleMap = new Map(Object.entries(config.professionalTitles).map(([label, profile]) => [normalize(label), profile]));
  const profileFor = label => specialtyMap.get(normalize(label)) || config.aliases[normalize(label)] || null;
  const eligible = row => row && row.credential_type === 'SPECIALTY' && row.verification_status === 'VERIFIED' && row.lifecycle_status === 'ACTIVE';
  function resolve(data = {}) {
    const identity = data.identity_public || {};
    const credentials = (data.verified_credentials?.specialties || []).filter(eligible);
    const primaryId = data.primary_specialty_credential_id == null ? '' : String(data.primary_specialty_credential_id);
    const verifiedPrimary = credentials.find(row => String(row.credential_id) === primaryId);
    const verifiedProfile = verifiedPrimary && profileFor(verifiedPrimary.professional_area_label);
    const textProfile = profileFor(identity.specialty_primary);
    const titleProfile = titleMap.get(normalize(identity.professional_designation)) ||
      titleMap.get(normalize(data.verified_credentials?.professional?.professional_area_label));
    const primary = verifiedProfile || textProfile || titleProfile || config.generalProfile;
    const used = new Set();
    const quick = [];
    const add = key => {
      for (const group of config.profiles[key]?.quick || []) {
        if (!used.has(group) && quick.length < 8) { used.add(group); quick.push(group); }
      }
    };
    add(primary);
    const secondary = [
      ...credentials.filter(row => row !== verifiedPrimary).map(row => row.professional_area_label),
      ...(Array.isArray(identity.specialty_secondary) ? identity.specialty_secondary : [])
    ];
    for (const label of secondary) { const profile = profileFor(label); if (profile) add(profile); }
    const source=verifiedProfile ? 'verified_primary' : textProfile ? 'primary_text' : titleProfile ? 'professional_family' : 'general';
    const family=config.families.dental.includes(primary)?'DENTAL':
      config.families.other.includes(primary)||source==='general'?'OTHER':'MEDICAL';
    return {profile: primary, family, quick, promoted: config.profiles[primary]?.promoted || [], source};
  }
  const active = (group, counts, activeStudyKeys=null) =>
    activeStudyKeys instanceof Set && group.id.startsWith('dental-')
      ? group.parts.some(part=>(part.keys||[]).some(key=>activeStudyKeys.has(key)))
      : (!group.id.startsWith('dental-') || Number(counts.DENTAL || 0) > 0) &&
        group.parts.some(part => Number(counts[part.category] || 0) > 0);
  const dentalParts=Object.values(config.groups.quick).filter(group=>group.id.startsWith('dental-'))
    .flatMap(group=>group.parts);
  const dentalScope=()=>{
    const byCategory=new Map();
    dentalParts.forEach(part=>{
      if(!byCategory.has(part.category))byCategory.set(part.category,new Set());
      (part.keys||[]).forEach(key=>byCategory.get(part.category).add(key));
    });
    return [...byCategory].map(([category,keys])=>({category,keys:[...keys]}));
  };
  window.mxmedSpecialtyNavigationV1 = Object.freeze({config, normalize, profileFor, resolve, active, dentalScope});
})();
