<?php
declare(strict_types=1);

function mxmed_build_hosp_evolution_note_payload(array $payload): array {
    $ambito = 'hospitalizacion';

    $contractRaw = $payload['contract_version'] ?? 1;
    $contractVersion = is_numeric($contractRaw) ? (int)$contractRaw : 1;
    if (array_key_exists('contract_version', $payload) && $contractVersion !== 1) {
        try { error_log('mxmed: nota_evolucion_hosp contract_version=' . $contractVersion); } catch (Throwable $e) { }
    }

    $citas = is_array($payload['citas_clinicas'] ?? null) ? $payload['citas_clinicas'] : [];
    $sv = is_array($payload['signos_vitales'] ?? null) ? $payload['signos_vitales'] : [];
    $expl = is_array($payload['exploracion_relevante'] ?? null) ? $payload['exploracion_relevante'] : [];

    $dx = $payload['diagnosticos'] ?? [];
    if (!is_array($dx)) $dx = [];
    $dxNorm = [];
    foreach ($dx as $d) {
        if (!is_array($d)) continue;
        $label = trim((string)($d['label'] ?? ''));
        if ($label === '') continue;
        $dxNorm[] = ['code' => isset($d['code']) ? (string)$d['code'] : null, 'label' => $label];
    }

    $events = $payload['eventos'] ?? [];
    if (!is_array($events)) $events = [];

    $rx = is_array($payload['receta'] ?? null) ? $payload['receta'] : [];
    $balance = is_array($payload['balance_hidrico'] ?? null) ? $payload['balance_hidrico'] : [];
    $estado = is_array($payload['estado_actual'] ?? null) ? $payload['estado_actual'] : [];

    return [
        'section_id' => 'nota_evolucion_hosp',
        'standard' => 'NOM-004-SSA3-2012',
        'contract_version' => $contractVersion,
        'ambito' => $ambito,
        'citas_clinicas' => [
            'motivo_consulta' => (string)($citas['motivo_consulta'] ?? ''),
            'padecimiento_actual' => (string)($citas['padecimiento_actual'] ?? ''),
        ],
        'estado_actual' => [
            'estado_general' => (string)($estado['estado_general'] ?? ''),
            'conciencia' => (string)($estado['conciencia'] ?? ''),
            'dolor_eva' => $estado['dolor_eva'] ?? null,
            'soporte' => is_array($estado['soporte'] ?? null) ? $estado['soporte'] : [],
        ],
        'signos_vitales' => [
            'ta_sistolica' => $sv['ta_sistolica'] ?? null,
            'ta_diastolica' => $sv['ta_diastolica'] ?? null,
            'fc' => $sv['fc'] ?? null,
            'fr' => $sv['fr'] ?? null,
            'temperatura' => $sv['temperatura'] ?? null,
            'spo2' => $sv['spo2'] ?? null,
            'dolor_eva' => $sv['dolor_eva'] ?? ($estado['dolor_eva'] ?? null),
        ],
        'balance_hidrico' => $balance,
        'exploracion_relevante' => [
            'resumen_sistemas' => (string)($expl['resumen_sistemas'] ?? ''),
            'hallazgos_relevantes' => (string)($expl['hallazgos_relevantes'] ?? ''),
        ],
        'estudios_relevantes' => is_array($payload['estudios_relevantes'] ?? null) ? $payload['estudios_relevantes'] : [],
        'interpretacion_resultados' => isset($payload['interpretacion_resultados']) ? (string)$payload['interpretacion_resultados'] : null,
        'evolucion_diaria' => [
            'nota' => (string)($payload['evolucion_diaria']['nota'] ?? $payload['evolucion_cuadro_clinico'] ?? ''),
            'hallazgos' => (string)($payload['evolucion_diaria']['hallazgos'] ?? $payload['hallazgos_relevantes'] ?? ''),
        ],
        'diagnosticos' => $dxNorm,
        'pronostico' => mxmed_parse_pronostico($payload['pronostico'] ?? null),
        'plan_indicaciones' => (string)($payload['plan_indicaciones'] ?? $payload['plan'] ?? ''),
        'receta' => [
            'has_prescription' => (bool)($rx['has_prescription'] ?? false),
            'prescription_id' => $rx['prescription_id'] ?? null,
            'medicamentos' => is_array($rx['medicamentos'] ?? null) ? $rx['medicamentos'] : [],
        ],
        'eventos' => $events,
        'snapshot' => is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : null,
    ];
}

function mxmed_hosp_evolution_note_validate_to_generate(array $payload): array {
    $errors = [];
    $nota = trim((string)($payload['evolucion_diaria']['nota'] ?? ''));
    if ($nota === '') $errors[] = 'Evolución/nota diaria es obligatoria.';
    $dx = $payload['diagnosticos'] ?? [];
    if (!is_array($dx) || count($dx) === 0) $errors[] = 'Diagnóstico(s) activos es obligatorio.';
    $pron = $payload['pronostico'] ?? [];
    $pronText = mxmed_pronostico_to_text(is_array($pron) ? $pron : []);
    if (trim($pronText) === '') $errors[] = 'Pronóstico es obligatorio.';
    if (trim((string)($payload['plan_indicaciones'] ?? '')) === '') $errors[] = 'Plan/indicaciones es obligatorio.';
    return $errors;
}

function mxmed_build_hoja_indicaciones_payload(array $payload): array {
    $rx = is_array($payload['receta'] ?? null) ? $payload['receta'] : [];
    $contractRaw = $payload['contract_version'] ?? 1;
    $contractVersion = is_numeric($contractRaw) ? (int)$contractRaw : 1;
    if (array_key_exists('contract_version', $payload) && $contractVersion !== 1) {
        try { error_log('mxmed: hoja_indicaciones contract_version=' . $contractVersion); } catch (Throwable $e) { }
    }
    return [
        'section_id' => 'hoja_indicaciones',
        'standard' => 'interno',
        'contract_version' => $contractVersion,
        'ambito' => 'hospitalizacion',
        'dieta' => is_array($payload['dieta'] ?? null) ? $payload['dieta'] : ['preset' => (string)($payload['dieta'] ?? ''), 'texto' => null],
        'soluciones_iv' => (string)($payload['soluciones_iv'] ?? ''),
        'cuidados_enfermeria' => (string)($payload['cuidados_enfermeria'] ?? ''),
        'interconsultas' => (string)($payload['interconsultas'] ?? ''),
        'ordenes_estudios' => (string)($payload['ordenes_estudios'] ?? ''),
        'receta' => [
            'has_prescription' => (bool)($rx['has_prescription'] ?? false),
            'prescription_id' => $rx['prescription_id'] ?? null,
            'medicamentos' => is_array($rx['medicamentos'] ?? null) ? $rx['medicamentos'] : [],
        ],
        'snapshot' => is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : null,
    ];
}

function mxmed_build_hoja_indicaciones_rendered_text(array $payload, array $context, array $actor): string {
    $dt = new DateTimeImmutable('now');
    $dtStr = $dt->format('d/m/Y H:i');

    $snap = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : [];
    $pac = is_array($snap['paciente'] ?? null) ? $snap['paciente'] : [];
    $med = is_array($snap['medico'] ?? null) ? $snap['medico'] : [];

    $safe = function ($v, string $fb = 'No registrado'): string {
        $t = trim((string)($v ?? ''));
        return $t !== '' ? $t : $fb;
    };

    $rx = is_array($payload['receta'] ?? null) ? $payload['receta'] : ['medicamentos' => []];
    $meds = is_array($rx['medicamentos'] ?? null) ? $rx['medicamentos'] : [];
    $dieta = is_array($payload['dieta'] ?? null) ? $payload['dieta'] : ['preset' => (string)($payload['dieta'] ?? ''), 'texto' => null];
    $dietaText = trim((string)($dieta['texto'] ?? ''));
    if ($dietaText === '') $dietaText = trim((string)($dieta['preset'] ?? ''));

    $lines = [];
    $lines[] = 'HOJA DE INDICACIONES / ÓRDENES MÉDICAS';
    $lines[] = "Fecha/Hora: {$dtStr}";
    $lines[] = '';
    $lines[] = 'Médico tratante: ' . $safe($med['nombre_completo'] ?? ($actor['nombre_completo'] ?? ''));
    $lines[] = '';
    $lines[] = 'Paciente: ' . $safe($pac['nombre_completo'] ?? '') . ' · Edad: ' . ($pac['edad'] ?? '--') . ' · Sexo: ' . ($pac['sexo'] ?? '--');
    $lines[] = '';
    $lines[] = 'DIETA';
    $lines[] = $safe($dietaText, 'No registrado');
    $lines[] = '';
    $lines[] = 'SOLUCIONES IV';
    $lines[] = $safe($payload['soluciones_iv'] ?? null);
    $lines[] = '';
    $lines[] = 'MEDICACIÓN';
    if (count($meds) === 0) {
        $lines[] = 'No registrado';
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
    $lines[] = 'LABORATORIO / IMAGEN';
    $lines[] = $safe($payload['ordenes_estudios'] ?? null);
    $lines[] = '';
    $lines[] = 'CUIDADOS DE ENFERMERÍA';
    $lines[] = $safe($payload['cuidados_enfermeria'] ?? null);
    $lines[] = '';
    $lines[] = 'INTERCONSULTAS';
    $lines[] = $safe($payload['interconsultas'] ?? null);
    $lines[] = '';
    $lines[] = "Documento generado: {$dtStr}";
    return implode("\n", $lines);
}

function mxmed_build_hoja_indicaciones_summary(array $payload, array $actor): string {
    return 'Indicaciones del día';
}

function mxmed_build_hosp_evolution_note_rendered_text(array $payload, array $context, array $actor): string {
    $dt = new DateTimeImmutable('now');
    $dtStr = $dt->format('d/m/Y H:i');

    $safe = function ($v, string $fb = 'No registrado'): string {
        $t = trim((string)($v ?? ''));
        return $t !== '' ? $t : $fb;
    };

    $snap = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : [];
    $pac = is_array($snap['paciente'] ?? null) ? $snap['paciente'] : [];
    $med = is_array($snap['medico'] ?? null) ? $snap['medico'] : [];
    $stay = is_array($snap['hospital_stay'] ?? null) ? $snap['hospital_stay'] : [];

    $sv = is_array($payload['signos_vitales'] ?? null) ? $payload['signos_vitales'] : [];
    $expl = is_array($payload['exploracion_relevante'] ?? null) ? $payload['exploracion_relevante'] : [];
    $dx = is_array($payload['diagnosticos'] ?? null) ? $payload['diagnosticos'] : [];
    $events = is_array($payload['eventos'] ?? null) ? $payload['eventos'] : [];
    $balance = is_array($payload['balance_hidrico'] ?? null) ? $payload['balance_hidrico'] : [];

    $rx = is_array($payload['receta'] ?? null) ? $payload['receta'] : ['medicamentos' => []];
    $meds = is_array($rx['medicamentos'] ?? null) ? $rx['medicamentos'] : [];

    $estado = is_array($payload['estado_actual'] ?? null) ? $payload['estado_actual'] : [];

    $lines = [];
    $lines[] = 'NOTA DE EVOLUCIÓN INTRAHOSPITALARIA (NOM-004-SSA3-2012)';
    $lines[] = "Fecha/Hora: {$dtStr}";
    $lines[] = '';

    $lines[] = 'Episodio: ' . $safe($stay['service'] ?? null, 'Hospitalización') .
        (($stay['room'] ?? '') !== '' ? " · Hab: {$stay['room']}" : '') .
        (($stay['bed'] ?? '') !== '' ? " · Cama: {$stay['bed']}" : '');
    $lines[] = 'Médico tratante: ' . $safe($med['nombre_completo'] ?? ($actor['nombre_completo'] ?? ''));
    $lines[] = 'Cédula: ' . $safe($med['cedula_profesional'] ?? ($actor['cedula_profesional'] ?? '')) .
        ' · Especialidad: ' . $safe($med['especialidad'] ?? ($actor['especialidad'] ?? ''));
    $lines[] = '';
    $lines[] = 'Paciente: ' . $safe($pac['nombre_completo'] ?? '') . ' · Edad: ' . ($pac['edad'] ?? '--') . ' · Sexo: ' . ($pac['sexo'] ?? '--');
    $lines[] = '';

    $lines[] = 'ESTADO ACTUAL';
    $lines[] = 'Estado general: ' . $safe($estado['estado_general'] ?? null);
    $lines[] = 'Conciencia: ' . $safe($estado['conciencia'] ?? null);
    $dolor = $payload['signos_vitales']['dolor_eva'] ?? ($estado['dolor_eva'] ?? null);
    $lines[] = 'Dolor EVA: ' . ($dolor === null || $dolor === '' ? 'No registrado' : (string)$dolor);
    $lines[] = '';

    $lines[] = 'SIGNOS VITALES';
    $lines[] = mxmed_format_vitals_line($sv);
    $lines[] = '';

    $lines[] = 'BALANCE HÍDRICO';
    $inTotal = (float)($balance['ingresos_total_ml'] ?? 0);
    $outTotal = (float)($balance['egresos_total_ml'] ?? 0);
    $bal = $inTotal - $outTotal;
    $lines[] = "Ingresos total: {$inTotal} ml";
    $lines[] = "Egresos total: {$outTotal} ml";
    $lines[] = "Balance: {$bal} ml";
    if (isset($balance['notas']) && trim((string)$balance['notas']) !== '') {
        $lines[] = "Notas: " . trim((string)$balance['notas']);
    }
    $lines[] = '';

    $lines[] = 'EXPLORACIÓN RELEVANTE';
    $lines[] = 'Resumen por sistemas: ' . $safe($expl['resumen_sistemas'] ?? null);
    $lines[] = 'Hallazgos relevantes: ' . $safe($expl['hallazgos_relevantes'] ?? null);
    $lines[] = '';

    $lines[] = 'EVOLUCIÓN / NOTA DIARIA';
    $lines[] = $safe($payload['evolucion_diaria']['nota'] ?? null);
    $hall = trim((string)($payload['evolucion_diaria']['hallazgos'] ?? ''));
    if ($hall !== '') {
        $lines[] = '';
        $lines[] = 'HALLAZGOS RELEVANTES';
        $lines[] = $hall;
    }
    $lines[] = '';

    $lines[] = 'EVENTOS Y CAMBIOS';
    if (count($events) === 0) {
        $lines[] = 'No registrado';
    } else {
        foreach ($events as $ev) {
            if (!is_array($ev)) continue;
            $lbl = trim((string)($ev['label'] ?? $ev['type'] ?? 'Evento'));
            $desc = trim((string)($ev['descripcion'] ?? ''));
            $lines[] = '- ' . ($lbl !== '' ? $lbl : 'Evento') . ($desc !== '' ? ": {$desc}" : '');
        }
    }
    $lines[] = '';

    $lines[] = 'DIAGNÓSTICO(S) ACTIVOS';
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
    $lines[] = $safe($pronText);
    $lines[] = '';

    $lines[] = 'PLAN / INDICACIONES';
    $lines[] = $safe($payload['plan_indicaciones'] ?? null);
    $lines[] = '';

    $lines[] = 'MEDICAMENTOS (RECETA / TERAPÉUTICA)';
    if (count($meds) === 0) {
        $lines[] = 'No registrado';
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

function mxmed_build_hosp_evolution_note_summary(array $payload, array $actor): string {
    $evo = trim((string)($payload['evolucion_diaria']['nota'] ?? $payload['evolucion_cuadro_clinico'] ?? ''));
    $plan = trim((string)($payload['plan_indicaciones'] ?? ''));
    $expl = is_array($payload['exploracion_relevante'] ?? null) ? $payload['exploracion_relevante'] : [];
    $explText = trim((string)($expl['resumen_sistemas'] ?? '')) . "\n" . trim((string)($expl['hallazgos_relevantes'] ?? ''));
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
    $resp = $spo2Low || ($respAbn && !($negRespCore || $negO2));

    $hayAjustes = preg_match('/\\b(ajust|cambi|modific|increment|aument|disminu|suspende|inicia|iniciar|titul|escalar|rota|cambia)\\w*/iu', $plan) === 1;
    $sinAlt = preg_match('/\\b(sin\\s+alter|sin\\s+camb|sin\\s+noved|establ|evoluci[oó]n\\s+favorable)\\w*/iu', $evo) === 1;

    if ($resp) return 'Evolución con hallazgos respiratorios';
    if ($hayAjustes) return 'Evolución con ajustes terapéuticos';
    if ($sinAlt) return 'Evolución sin alteraciones relevantes';
    return 'Evolución intrahospitalaria registrada';
}
