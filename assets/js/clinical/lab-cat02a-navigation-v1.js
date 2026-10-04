// LAB-CAT02A: versioned physician navigation only. Keys remain the server-owned study identity.
(function(){
  'use strict';
  const config={
  "version": 1,
  "primary": [
    {
      "key": "hematology",
      "label": "Hematología",
      "icon": "bloodtype",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "cbc",
            "esr",
            "ferritin",
            "iron",
            "lab_coombs_directo",
            "lab_coombs_indirecto",
            "lab_frotis_sanguineo",
            "lab_reticulocitos",
            "platelets",
            "tibc",
            "transferrin_sat"
          ]
        }
      ],
      "priority": 1
    },
    {
      "key": "chemistry",
      "label": "Química clínica",
      "icon": "science",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "albumin",
            "alp",
            "alt",
            "amylase",
            "ast",
            "bilirubin_direct",
            "bilirubin_indirect",
            "bilirubin_total",
            "bun",
            "calcium",
            "chloride",
            "chol_total",
            "creatinine",
            "folate",
            "fructosamine",
            "ggt",
            "glucose",
            "hdl",
            "lab_amonio",
            "lab_ck_mb",
            "lab_cpk",
            "ldl",
            "lipase",
            "magnesium",
            "non_hdl",
            "phosphorus",
            "potassium",
            "sodium",
            "total_protein",
            "triglycerides",
            "urea",
            "uric_acid",
            "vitamin_b12",
            "vitamin_d"
          ]
        }
      ],
      "priority": 2
    },
    {
      "key": "coagulation",
      "label": "Coagulación",
      "icon": "hematology",
      "parts": [
        {
          "category": "GENETICA",
          "keys": [
            "thrombophilia"
          ]
        },
        {
          "category": "LABORATORIO",
          "keys": [
            "aptt",
            "d_dimer",
            "fibrinogen",
            "lab_tiempo_de_protrombina",
            "lab_tiempo_de_trombina"
          ]
        }
      ],
      "priority": 3
    },
    {
      "key": "endocrine",
      "label": "Endocrinología y hormonas",
      "icon": "endocrinology",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "anti_tg",
            "anti_tpo",
            "bhcg",
            "estradiol",
            "fsh",
            "ft3",
            "ft4",
            "hba1c",
            "lh",
            "ogtt",
            "progesterone",
            "prolactin",
            "testosterone_free",
            "testosterone_total",
            "tsh"
          ]
        }
      ],
      "priority": 4
    },
    {
      "key": "immunology",
      "label": "Inmunología",
      "icon": "vaccines",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "c3",
            "c4",
            "iga",
            "igg",
            "igm",
            "rf"
          ]
        }
      ],
      "priority": 5
    },
    {
      "key": "microbiology",
      "label": "Microbiología",
      "icon": "microbiology",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "blood_culture",
            "stool_culture",
            "throat_swab",
            "urine_culture",
            "vaginal_swab"
          ]
        }
      ],
      "priority": 6
    },
    {
      "key": "molecular",
      "label": "Biología molecular / PCR",
      "icon": "genetics",
      "parts": [],
      "priority": 7
    },
    {
      "key": "urine",
      "label": "Orina y otros fluidos",
      "icon": "water_drop",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "microalbumin",
            "urinalysis",
            "urine_culture",
            "urine_creatinine_spot",
            "urine_sodium_spot",
            "urine_potassium_spot",
            "urine_pregnancy_qualitative",
            "csf_glucose",
            "csf_total_protein",
            "post_vasectomy_semen_check"
          ]
        }
      ],
      "priority": 8
    }
  ],
  "secondary": [
    {
      "key": "stool",
      "label": "Materia fecal",
      "icon": "science",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "fecal_occult_blood",
            "lab_ag_helicobacter_pylori",
            "lab_calprotectina_cuantificada",
            "stool_culture",
            "stool_ova_parasites"
          ]
        }
      ],
      "priority": 1
    },
    {
      "key": "autoimmunity",
      "label": "Autoinmunidad",
      "icon": "vaccines",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "ana",
            "anca",
            "anti_ccp",
            "ena"
          ]
        }
      ],
      "priority": 2
    },
    {
      "key": "inflammation",
      "label": "Inflamación / reactantes",
      "icon": "science",
      "parts": [{"category": "LABORATORIO", "keys": ["crp_hs", "esr"]}],
      "priority": 3
    },
    {
      "key": "tumor",
      "label": "Marcadores tumorales",
      "icon": "biotech",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "bhcg"
          ]
        }
      ],
      "priority": 4
    },
    {
      "key": "serology",
      "label": "Serologías / Hepatitis",
      "icon": "lab_profile",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "anti_hbc",
            "anti_hbs",
            "hbsag",
            "hcv_ab"
          ]
        }
      ],
      "priority": 5
    },
    {
      "key": "infectious_serology",
      "label": "Serologías / infecciones",
      "icon": "lab_profile",
      "parts": [{"category": "LABORATORIO", "keys": ["hiv_ag_ac"]}],
      "priority": 6
    },
    {
      "key": "drugs",
      "label": "Monitoreo de fármacos",
      "icon": "medication",
      "parts": [
        {
          "category": "LABORATORIO",
          "keys": [
            "lab_litio"
          ]
        }
      ],
      "priority": 7
    },
    {
      "key": "transplant",
      "label": "Trasplante",
      "icon": "transplant",
      "parts": [],
      "priority": 8
    },
    {
      "key": "cytopathology",
      "label": "Citopatología cervical",
      "icon": "biotech",
      "parts": [{"category": "PATOLOGIA", "keys": ["cyto_liquid_based", "cyto_pap"]}],
      "priority": 9
    },
    {
      "key": "cytogenetics",
      "label": "Citogenética",
      "icon": "genetics",
      "parts": [{"category": "GENETICA", "keys": ["karyotype"]}],
      "priority": 10
    },
    {
      "key": "cytogenomics",
      "label": "Citogenómica / microarreglos",
      "icon": "genetics",
      "parts": [{"category": "GENETICA", "keys": ["cma_microarray"]}],
      "priority": 11
    },
    {
      "key": "genomic_sequencing",
      "label": "Secuenciación genómica",
      "icon": "genetics",
      "parts": [{"category": "GENETICA", "keys": ["wes", "wgs"]}],
      "priority": 12
    },
    {
      "key": "prenatal_genetics",
      "label": "Tamiz genético prenatal",
      "icon": "genetics",
      "parts": [{"category": "GENETICA", "keys": ["nipt"]}],
      "priority": 13
    },
    {
      "key": "germline_genetics",
      "label": "Genética germinal",
      "icon": "genetics",
      "parts": [{"category": "GENETICA", "keys": ["carrier_screening", "hereditary_cancer_germline", "brca1_2", "lynch", "thrombophilia"]}],
      "priority": 14
    },
    {
      "key": "hereditary_oncology",
      "label": "Oncología hereditaria",
      "icon": "genetics",
      "parts": [{"category": "GENETICA", "keys": ["hereditary_cancer_germline", "brca1_2", "lynch"]}],
      "priority": 15
    },
    {
      "key": "pharmacogenomics",
      "label": "Farmacogenómica",
      "icon": "genetics",
      "parts": [{"category": "GENETICA", "keys": ["pgx"]}],
      "priority": 16
    },
    {
      "key": "molecular_oncology",
      "label": "Oncología molecular",
      "icon": "genetics",
      "parts": [{"category": "GENETICA", "keys": ["somatic_tumor_ngs"]}],
      "priority": 17
    }
  ],
  "special": [
    {
      "key": "panels",
      "label": "Perfiles y paneles",
      "icon": "view_module",
      "parts": [
        {
          "category": "GENETICA",
          "keys": [
            "carrier_screening",
            "hereditary_cancer_germline",
            "pgx",
            "somatic_tumor_ngs",
            "thrombophilia"
          ]
        },
        {
          "category": "LABORATORIO",
          "keys": [
            "cbc",
            "urinalysis"
          ]
        }
      ],
      "priority": 1
    }
  ],
  "allLaboratory": {
    "key": "all-laboratory",
    "label": "Todos los estudios de laboratorio",
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
  "profiles": {
    "general": [
      "chemistry",
      "hematology",
      "urine",
      "panels"
    ],
    "internal": [
      "chemistry",
      "hematology",
      "immunology",
      "microbiology"
    ],
    "endocrine": [
      "endocrine",
      "chemistry",
      "panels",
      "urine"
    ],
    "heme": [
      "hematology",
      "coagulation",
      "molecular",
      "immunology"
    ],
    "infect": [
      "microbiology",
      "molecular",
      "serology",
      "immunology"
    ],
    "nephro": [
      "chemistry",
      "urine",
      "hematology",
      "panels"
    ],
    "oncology": [
      "hematology",
      "chemistry",
      "tumor",
      "molecular"
    ],
    "cardio": [
      "chemistry",
      "hematology",
      "coagulation"
    ],
    "gi": [
      "chemistry",
      "stool",
      "serology",
      "microbiology"
    ],
    "rheum": [
      "autoimmunity",
      "immunology",
      "hematology",
      "chemistry"
    ],
    "obgyn": [
      "endocrine",
      "microbiology",
      "serology",
      "chemistry"
    ],
    "peds": [
      "hematology",
      "chemistry",
      "microbiology",
      "urine"
    ],
    "pulm": [
      "microbiology",
      "molecular",
      "hematology",
      "chemistry"
    ]
  }
};
  const normalize=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().replace(/\s+/g,' ').toLocaleLowerCase('es');
  const labels={
    'medico general':'general','medicina general':'general','medicina interna':'internal',
    'endocrinologia':'endocrine','hematologia':'heme','infectologia':'infect','nefrologia':'nephro',
    'oncologia':'oncology','cardiologia':'cardio','gastroenterologia':'gi','reumatologia':'rheum',
    'ginecologia y obstetricia':'obgyn','pediatria':'peds','neumologia':'pulm'
  };
  const profileKeys={general_med:'general',internal:'internal',endocrine:'endocrine',heme:'heme',infect:'infect',nephro:'nephro',oncology:'oncology',cardio:'cardio',gi:'gi',rheum:'rheum',obgyn:'obgyn',peds:'peds',pulm:'pulm'};
  function profileFor(data={},resolved={},simulation=null){
    const identity=data.identity_public||{};
    const credentials=(data.verified_credentials?.specialties||[]).filter(row=>row.credential_type==='SPECIALTY'&&row.verification_status==='VERIFIED'&&row.lifecycle_status==='ACTIVE');
    const verified=credentials.find(row=>String(row.credential_id)===String(data.primary_specialty_credential_id));
    const label=simulation?.label||verified?.professional_area_label||identity.specialty_primary||identity.professional_designation;
    return labels[normalize(label)]||profileKeys[resolved.profile]||'general';
  }
  const count=(group,activeKeys)=>group.parts.reduce((n,part)=>n+(part.keys||[]).filter(key=>activeKeys.has(key)).length,0);
  function visible(activeKeys,profileKey){
    const byKey=new Map([...config.primary,...config.secondary,...config.special].map(group=>[group.key,group]));
    const priorities=(config.profiles[profileKey]||config.profiles.general).map(key=>byKey.get(key)).filter(group=>group&&count(group,activeKeys)>0);
    const promoted=new Set(priorities.map(group=>group.key));
    const keep=groups=>groups.filter(group=>!promoted.has(group.key)&&count(group,activeKeys)>0);
    return {priorities,primary:keep(config.primary),secondary:keep(config.secondary),special:keep(config.special)};
  }
  window.mxmedLabNavigationV1=Object.freeze({config,profileFor,count,visible});
})();
