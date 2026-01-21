<?php
declare(strict_types=1);

function mxmed_uuidv4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function mxmed_now_mysql(): string {
    return gmdate('Y-m-d H:i:s');
}

function mxmed_parse_pronostico($p): array {
    if (is_array($p)) {
        $preset = isset($p['preset']) ? trim((string)$p['preset']) : '';
        $texto = isset($p['texto']) ? trim((string)$p['texto']) : '';
        return [
            'preset' => $preset ?: null,
            'texto' => $texto ?: null,
        ];
    }
    $s = trim((string)($p ?? ''));
    if ($s === '') return ['preset' => null, 'texto' => null];
    $norm = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    if (in_array($norm, ['bueno', 'reservado', 'malo'], true)) return ['preset' => $norm, 'texto' => null];
    return ['preset' => null, 'texto' => $s];
}

function mxmed_pronostico_to_text(array $pronostico): string {
    $preset = $pronostico['preset'] ?? null;
    $texto = $pronostico['texto'] ?? null;
    if (is_string($texto) && trim($texto) !== '') return trim($texto);
    if ($preset === 'bueno') return 'Bueno';
    if ($preset === 'reservado') return 'Reservado';
    if ($preset === 'malo') return 'Malo';
    return '';
}

function mxmed_build_evolution_note_payload(array $payload): array {
    $ambito = trim((string)($payload['ambito'] ?? 'consulta')) ?: 'consulta';
    if (!in_array($ambito, ['consulta', 'urgencias', 'hospitalizacion'], true)) $ambito = 'consulta';

    $citas = is_array($payload['citas_clinicas'] ?? null) ? $payload['citas_clinicas'] : [];
    $sv = is_array($payload['signos_vitales'] ?? null) ? $payload['signos_vitales'] : [];
    $expl = is_array($payload['exploracion_relevante'] ?? null) ? $payload['exploracion_relevante'] : [];
    $rx = is_array($payload['receta'] ?? null) ? $payload['receta'] : [];

    $dx = $payload['diagnosticos'] ?? [];
    if (!is_array($dx)) $dx = [];
    $dxNorm = [];
    foreach ($dx as $d) {
        if (!is_array($d)) continue;
        $label = trim((string)($d['label'] ?? ''));
        if ($label === '') continue;
        $dxNorm[] = [
            'code' => isset($d['code']) ? (string)$d['code'] : null,
            'label' => $label,
        ];
    }

    $studies = $payload['estudios_relevantes'] ?? [];
    if (!is_array($studies)) $studies = [];

    return [
        'section_id' => 'nota_evolucion',
        'standard' => 'NOM-004-SSA3-2012',
        'ambito' => $ambito,
        'citas_clinicas' => [
            'motivo_consulta' => (string)($citas['motivo_consulta'] ?? ''),
            'padecimiento_actual' => (string)($citas['padecimiento_actual'] ?? ''),
        ],
        'complemento_sintomas' => isset($payload['complemento_sintomas']) ? (string)$payload['complemento_sintomas'] : null,
        'evolucion_cuadro_clinico' => (string)($payload['evolucion_cuadro_clinico'] ?? ''),
        'signos_vitales' => [
            'ta_sistolica' => $sv['ta_sistolica'] ?? null,
            'ta_diastolica' => $sv['ta_diastolica'] ?? null,
            'fc' => $sv['fc'] ?? null,
            'fr' => $sv['fr'] ?? null,
            'temperatura' => $sv['temperatura'] ?? null,
            'spo2' => $sv['spo2'] ?? null,
            'dolor_eva' => $sv['dolor_eva'] ?? null,
        ],
        'exploracion_relevante' => [
            'resumen_sistemas' => (string)($expl['resumen_sistemas'] ?? ''),
            'hallazgos_relevantes' => (string)($expl['hallazgos_relevantes'] ?? ''),
        ],
        'estudios_relevantes' => $studies,
        'interpretacion_resultados' => isset($payload['interpretacion_resultados']) ? (string)$payload['interpretacion_resultados'] : null,
        'diagnosticos' => $dxNorm,
        'pronostico' => mxmed_parse_pronostico($payload['pronostico'] ?? null),
        'plan_indicaciones' => (string)($payload['plan_indicaciones'] ?? ''),
        'receta' => [
            'has_prescription' => (bool)($rx['has_prescription'] ?? false),
            'prescription_id' => $rx['prescription_id'] ?? null,
            'medicamentos' => is_array($rx['medicamentos'] ?? null) ? $rx['medicamentos'] : [],
        ],
        // Snapshot opcional para UI / auditoría en ambientes sin maestro de pacientes/usuarios.
        'snapshot' => is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : null,
    ];
}

function mxmed_evolution_note_validate_to_generate(array $payload): array {
    $errors = [];
    if (empty($payload['ambito'])) $errors[] = 'Ámbito es obligatorio.';
    if (trim((string)($payload['evolucion_cuadro_clinico'] ?? '')) === '') $errors[] = 'Evolución / actualización del cuadro clínico es obligatoria.';
    $dx = $payload['diagnosticos'] ?? [];
    if (!is_array($dx) || count($dx) === 0) $errors[] = 'Diagnóstico(s) es obligatorio.';
    $pron = $payload['pronostico'] ?? [];
    $pronText = mxmed_pronostico_to_text(is_array($pron) ? $pron : []);
    if (trim($pronText) === '') $errors[] = 'Pronóstico es obligatorio.';
    if (trim((string)($payload['plan_indicaciones'] ?? '')) === '') $errors[] = 'Plan/indicaciones (tratamiento) es obligatorio.';
    return $errors;
}

function mxmed_format_vitals_line(array $sv): string {
    $ta = ($sv['ta_sistolica'] ?? null) !== null && ($sv['ta_diastolica'] ?? null) !== null
        ? "{$sv['ta_sistolica']}/{$sv['ta_diastolica']} mmHg"
        : "TA: No registrado";
    $fc = ($sv['fc'] ?? null) !== null ? "FC: {$sv['fc']} lpm" : "FC: No registrado";
    $fr = ($sv['fr'] ?? null) !== null ? "FR: {$sv['fr']} rpm" : "FR: No registrado";
    $t = ($sv['temperatura'] ?? null) !== null ? "Temp: {$sv['temperatura']} °C" : "Temp: No registrado";
    $s = ($sv['spo2'] ?? null) !== null ? "SpO₂: {$sv['spo2']}%" : "SpO₂: No registrado";
    $d = ($sv['dolor_eva'] ?? null) !== null ? "Dolor: {$sv['dolor_eva']}/10" : "Dolor: No registrado";
    return implode(' · ', [$ta, $fc, $fr, $t, $s, $d]);
}

function mxmed_build_evolution_note_rendered_text(array $payload, array $context, array $actor): string {
    try {
        $dt = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
    } catch (Throwable $e) {
        $dt = new DateTimeImmutable('now');
    }
    $dtStr = $dt->format('d/m/Y H:i');

    $snapshot = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : [];
    $paciente = is_array($snapshot['paciente'] ?? null) ? $snapshot['paciente'] : [];
    $medico = is_array($snapshot['medico'] ?? null) ? $snapshot['medico'] : [];

    $ambito = (string)($payload['ambito'] ?? 'consulta');
    $citas = is_array($payload['citas_clinicas'] ?? null) ? $payload['citas_clinicas'] : [];
    $sv = is_array($payload['signos_vitales'] ?? null) ? $payload['signos_vitales'] : [];
    $ex = is_array($payload['exploracion_relevante'] ?? null) ? $payload['exploracion_relevante'] : [];
    $dx = is_array($payload['diagnosticos'] ?? null) ? $payload['diagnosticos'] : [];
    $rx = is_array($payload['receta'] ?? null) ? $payload['receta'] : ['medicamentos' => []];
    $estudios = is_array($payload['estudios_relevantes'] ?? null) ? $payload['estudios_relevantes'] : [];

    $safeText = function ($v, string $fallback = 'No registrado'): string {
        $t = trim((string)($v ?? ''));
        return $t !== '' ? $t : $fallback;
    };

    $lines = [];
    $lines[] = 'NOTA DE EVOLUCIÓN (NOM-004-SSA3-2012)';
    $lines[] = "Fecha/Hora: {$dtStr}";
    $lines[] = 'Ámbito: ' . ($ambito ?: 'consulta');
    $lines[] = '';

    $docName = trim((string)($medico['nombre_completo'] ?? $actor['nombre_completo'] ?? 'Médico'));
    $docCed = trim((string)($medico['cedula_profesional'] ?? $actor['cedula_profesional'] ?? ''));
    $docEsp = trim((string)($medico['especialidad'] ?? $actor['especialidad'] ?? ''));
    $lines[] = 'Médico responsable: ' . ($docName !== '' ? $docName : 'No registrado');
    $lines[] = 'Cédula profesional: ' . ($docCed !== '' ? $docCed : 'No registrado') . ' · Especialidad: ' . ($docEsp !== '' ? $docEsp : 'No registrado');
    $lines[] = '';

    $pacName = trim((string)($paciente['nombre_completo'] ?? ''));
    $pacEdad = trim((string)($paciente['edad'] ?? ''));
    $pacSexo = trim((string)($paciente['sexo'] ?? ''));
    $lines[] = 'Paciente: ' . ($pacName !== '' ? $pacName : 'No registrado') . ' · Edad: ' . ($pacEdad !== '' ? $pacEdad : '--') . ' · Sexo: ' . ($pacSexo !== '' ? $pacSexo : '--');
    $lines[] = '';

    $lines[] = 'SÍNTOMAS ACTUALES RELEVANTES (cita)';
    $lines[] = 'Motivo de consulta: ' . $safeText($citas['motivo_consulta'] ?? null);
    $lines[] = 'Padecimiento actual: ' . $safeText($citas['padecimiento_actual'] ?? null);
    $complemento = trim((string)($payload['complemento_sintomas'] ?? ''));
    if ($complemento !== '') $lines[] = "Complemento breve: {$complemento}";
    $lines[] = '';

    $lines[] = 'EVOLUCIÓN / ACTUALIZACIÓN DEL CUADRO CLÍNICO';
    $lines[] = $safeText($payload['evolucion_cuadro_clinico'] ?? null);
    $lines[] = '';

    $lines[] = 'SIGNOS VITALES';
    $lines[] = mxmed_format_vitals_line($sv);
    $lines[] = '';

    $lines[] = 'EXPLORACIÓN FÍSICA RELEVANTE';
    $lines[] = 'Resumen por sistemas: ' . $safeText($ex['resumen_sistemas'] ?? null);
    $lines[] = 'Hallazgos relevantes: ' . $safeText($ex['hallazgos_relevantes'] ?? null);
    $lines[] = '';

    $lines[] = 'RESULTADOS RELEVANTES DE ESTUDIOS AUXILIARES';
    if (count($estudios) === 0) {
        $lines[] = 'No registrado';
    } else {
        $slice = array_slice($estudios, 0, 12);
        foreach ($slice as $it) {
            if (!is_array($it)) continue;
            $nombre = trim((string)($it['nombre_estudio'] ?? 'Estudio'));
            $fecha = trim((string)($it['fecha'] ?? ''));
            $lines[] = '- ' . ($nombre !== '' ? $nombre : 'Estudio') . ($fecha !== '' ? " ({$fecha})" : '');
        }
    }

    $interp = trim((string)($payload['interpretacion_resultados'] ?? ''));
    if ($interp !== '') {
        $lines[] = '';
        $lines[] = 'INTERPRETACIÓN CLÍNICA DE RESULTADOS';
        $lines[] = $interp;
    }

    $lines[] = '';
    $lines[] = 'DIAGNÓSTICO(S)';
    if (count($dx) === 0) {
        $lines[] = 'No registrado';
    } else {
        foreach ($dx as $d) {
            if (!is_array($d)) continue;
            $label = trim((string)($d['label'] ?? 'Diagnóstico'));
            $lines[] = '- ' . ($label !== '' ? $label : 'Diagnóstico');
        }
    }

    $lines[] = '';
    $lines[] = 'PRONÓSTICO';
    $pronText = mxmed_pronostico_to_text(is_array($payload['pronostico'] ?? null) ? $payload['pronostico'] : []);
    $lines[] = $safeText($pronText);

    $lines[] = '';
    $lines[] = 'TRATAMIENTO E INDICACIONES';
    $lines[] = $safeText($payload['plan_indicaciones'] ?? null);

    $lines[] = '';
    $lines[] = 'MEDICAMENTOS (RECETA)';
    $meds = is_array($rx['medicamentos'] ?? null) ? $rx['medicamentos'] : [];
    if (count($meds) === 0) {
        $lines[] = 'Sin receta registrada';
    } else {
        foreach ($meds as $m) {
            if (!is_array($m)) continue;
            $parts = [];
            foreach (['medicamento', 'dosis', 'via', 'periodicidad', 'duracion'] as $k) {
                $v = trim((string)($m[$k] ?? ''));
                if ($v !== '') $parts[] = $v;
            }
            $base = count($parts) ? implode(' · ', $parts) : 'Medicamento';
            $extra = trim((string)($m['indicaciones'] ?? ''));
            $lines[] = '- ' . $base . ($extra !== '' ? " ({$extra})" : '');
        }
    }

    $lines[] = '';
    $lines[] = "Documento generado: {$dtStr}";
    return implode("\n", $lines);
}

function mxmed_build_evolution_note_summary(array $payload, array $actor): string {
    $evo = trim((string)($payload['evolucion_cuadro_clinico'] ?? ''));
    $plan = trim((string)($payload['plan_indicaciones'] ?? ''));
    $expl = is_array($payload['exploracion_relevante'] ?? null) ? $payload['exploracion_relevante'] : [];
    $explText = trim((string)($expl['resumen_sistemas'] ?? '')) . "\n" . trim((string)($expl['hallazgos_relevantes'] ?? ''));

    $hayAjustes = preg_match('/\\b(ajust|cambi|modific|increment|aument|disminu|suspende|inicia|iniciar|titul|escalar|rota|cambia)\\w*/iu', $plan) === 1;
    $fullText = $explText . "\n" . $evo;

    $sv = is_array($payload['signos_vitales'] ?? null) ? $payload['signos_vitales'] : [];
    $spo2 = $sv['spo2'] ?? null;
    $spo2Low = is_numeric($spo2) && (float)$spo2 <= 93.0;
    if (!$spo2Low) {
        if (preg_match('/\\b(spo2|sp[oó]2|sat|saturaci[oó]n)\\s*[:=]?\\s*(\\d{2,3})\\b/iu', $fullText, $m) === 1) {
            $n = (int)$m[2];
            if ($n > 0 && $n <= 93) $spo2Low = true;
        }
    }

    $respAbn = preg_match('/\\b(disnea|sibilanc\\w*|estertor\\w*|crepitant\\w*|tiraje|cianos\\w*|broncoespasm\\w*|broncoobstru\\w*|hipox\\w*|uso\\s+de\\s+ox[ií]geno|ox[ií]geno\\s+(suplementario|terapia)|c[aá]nula\\s+nasal|mascarilla|nebuliz\\w*|inhalador\\w*|wheez\\w*)\\b/iu', $fullText) === 1;
    $negRespCore = preg_match('/\\b(sin|niega)\\s+(disnea|sibilanc\\w*|estertor\\w*|crepitant\\w*|tiraje|cianos\\w*|broncoespasm\\w*|hipox\\w*)\\b/iu', $fullText) === 1;
    $negO2 = preg_match('/\\b(sin|niega)\\s+(uso\\s+de\\s+ox[ií]geno|requerimiento\\s+de\\s+ox[ií]geno|ox[ií]geno)\\b/iu', $fullText) === 1;
    $negResp = $negRespCore || $negO2;
    $resp = $spo2Low || ($respAbn && !$negResp);
    $sinAlt = preg_match('/\\b(sin\\s+alter|sin\\s+camb|sin\\s+noved|establ|evoluci[oó]n\\s+favorable)\\w*/iu', $evo) === 1;

    if ($resp) return 'Evolución con hallazgos respiratorios';
    if ($hayAjustes) return 'Evolución con ajustes terapéuticos';
    if ($sinAlt) return 'Evolución sin alteraciones relevantes';
    return 'Evolución registrada';
}

function mxmed_build_clinical_document(array $args): array {
    $type = trim((string)($args['type'] ?? ''));
    $context = is_array($args['context'] ?? null) ? $args['context'] : [];
    $payloadInput = is_array($args['payload'] ?? null) ? $args['payload'] : [];
    $actor = is_array($args['actor'] ?? null) ? $args['actor'] : [];

    if ($type === '') throw new InvalidArgumentException('type requerido');

    $care = trim((string)($context['care_setting'] ?? $payloadInput['ambito'] ?? 'consulta')) ?: 'consulta';
    if (!in_array($care, ['consulta', 'urgencias', 'hospitalizacion'], true)) $care = 'consulta';

    $patientId = (string)($context['patient_id'] ?? '');
    $actorId = (string)($actor['user_id'] ?? '');
    if ($patientId === '') throw new InvalidArgumentException('context.patient_id requerido');
    if ($actorId === '') throw new InvalidArgumentException('actor.user_id requerido');

    $now = mxmed_now_mysql();
    $uuid = mxmed_uuidv4();

    $payload = $type === 'nota_evolucion'
        ? mxmed_build_evolution_note_payload($payloadInput)
        : $payloadInput;

    $title = $type === 'nota_evolucion' ? 'Nota de Evolución' : ucfirst(str_replace('_', ' ', $type));
    $renderedText = null;
    $summary = null;

    if ($type === 'nota_evolucion') {
        $renderedText = mxmed_build_evolution_note_rendered_text($payload, $context, $actor);
        $summary = mxmed_build_evolution_note_summary($payload, $actor);
    }

    return [
        'document_id' => $uuid,
        'document_type' => $type,
        'title' => $title,
        'version' => 1,
        'context' => [
            'patient_id' => $patientId,
            'encounter_id' => $context['encounter_id'] ?? null,
            'hospital_stay_id' => $context['hospital_stay_id'] ?? null,
            'care_setting' => $care,
            'service' => $context['service'] ?? null,
        ],
        'status' => 'generated',
        'timestamps' => [
            'created_at' => $now,
            'updated_at' => null,
            'generated_at' => $now,
            'signed_at' => null,
        ],
        'audit' => [
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => null,
        ],
        'participants' => [
            [
                'user_id' => $actorId,
                'role' => 'medico',
                'participation_type' => 'responsable',
                'signed_at' => null,
            ],
        ],
        'content' => [
            'payload' => $payload,
            'rendered_text' => $renderedText,
            'summary' => $summary,
            'edited_flag' => 0,
        ],
        'ui' => [
            'event_datetime' => $now,
            'widget_group' => 'documentos_clinicos',
            'printable' => true,
        ],
    ];
}

function mxmed_ensure_clinical_docs_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clinical_documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            document_uuid CHAR(36) NOT NULL,
            document_type VARCHAR(64) NOT NULL,
            title VARCHAR(128) NOT NULL,
            version INT NOT NULL DEFAULT 1,
            status ENUM('draft','generated','signed','voided') NOT NULL DEFAULT 'generated',
            patient_id VARCHAR(128) NOT NULL,
            encounter_id VARCHAR(128) NULL,
            hospital_stay_id VARCHAR(128) NULL,
            care_setting ENUM('consulta','urgencias','hospitalizacion') NOT NULL DEFAULT 'consulta',
            service VARCHAR(128) NULL,
            payload_json JSON NOT NULL,
            rendered_text LONGTEXT NULL,
            summary VARCHAR(512) NULL,
            edited_flag TINYINT NOT NULL DEFAULT 0,
            event_datetime DATETIME NOT NULL,
            widget_group VARCHAR(64) NOT NULL DEFAULT 'documentos_clinicos',
            printable TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            generated_at DATETIME NULL,
            signed_at DATETIME NULL,
            created_by_user_id VARCHAR(128) NOT NULL,
            updated_by_user_id VARCHAR(128) NULL,
            UNIQUE KEY uq_clinical_documents_uuid (document_uuid),
            KEY idx_clinical_documents_patient_dt (patient_id, event_datetime),
            KEY idx_clinical_documents_type_dt (document_type, event_datetime),
            KEY idx_clinical_documents_created_by (created_by_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clinical_document_participants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            clinical_document_id BIGINT UNSIGNED NOT NULL,
            user_id VARCHAR(128) NOT NULL,
            role VARCHAR(32) NOT NULL,
            participation_type VARCHAR(32) NOT NULL,
            signed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY idx_cdp_doc (clinical_document_id),
            KEY idx_cdp_user (user_id),
            CONSTRAINT fk_cdp_doc FOREIGN KEY (clinical_document_id) REFERENCES clinical_documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}
