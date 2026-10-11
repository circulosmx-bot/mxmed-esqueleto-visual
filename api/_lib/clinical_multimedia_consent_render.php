<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_legal_document_presentation.php';

function clinical_multimedia_consent_normalize(array $input): array
{
    $modelVersion = (int)($input['model_version'] ?? 1);
    if (!in_array($modelVersion, [1, 2], true)) throw new InvalidArgumentException('MULTIMEDIA_MODEL_VERSION_INVALID');
    $choices = static function (mixed $value, array $allowed): array {
        if (!is_array($value)) throw new InvalidArgumentException('MULTIMEDIA_SELECTION_INVALID');
        $result = [];
        foreach ($value as $entry) {
            if (!is_string($entry) || !in_array($entry, $allowed, true))
                throw new InvalidArgumentException('MULTIMEDIA_SELECTION_INVALID');
            if (!in_array($entry, $result, true)) $result[] = $entry;
        }
        sort($result, SORT_STRING);
        return $result;
    };
    $media = $choices($input['media_types'] ?? [], ['photo', 'video', 'audio']);
    $additional = ['teaching', 'clinical_research', 'clinical_trial', 'scientific_publication',
        'institutional_communication', 'public_dissemination', 'other_specific_use'];
    $purposes = $choices($input['use_purposes'] ?? [], $modelVersion === 2
        ? array_merge(['clinical_record', 'clinical_followup'], $additional)
        : ['clinical_record', 'clinical_followup']);
    if (!$media || !$purposes) throw new InvalidArgumentException('MULTIMEDIA_SELECTION_REQUIRED');
    if (($input['capture_allowed'] ?? null) !== true)
        throw new InvalidArgumentException('MULTIMEDIA_CAPTURE_AUTHORIZATION_REQUIRED');
    $identification = (string)($input['identification_scope'] ?? '');
    if (!in_array($identification, ['identifiable', 'reduced_identification'], true))
        throw new InvalidArgumentException('MULTIMEDIA_IDENTIFICATION_INVALID');
    $scope = (string)($input['authorization_scope'] ?? '');
    if (!in_array($scope, ['specific_event', 'current_episode', 'current_condition_followup', 'custom_limited_scope'], true))
        throw new InvalidArgumentException('MULTIMEDIA_SCOPE_INVALID');
    $custom = $scope === 'custom_limited_scope' ? trim((string)($input['custom_scope_description'] ?? '')) : '';
    if ($scope === 'custom_limited_scope' && $custom === '')
        throw new InvalidArgumentException('MULTIMEDIA_CUSTOM_SCOPE_REQUIRED');
    $validity = is_array($input['validity'] ?? null) ? $input['validity'] : [];
    $mode = (string)($validity['mode'] ?? '');
    if (!in_array($mode, $modelVersion === 2
        ? ['single_event', 'explicit_date_range', 'until_revoked'] : ['single_event', 'explicit_date_range'], true))
        throw new InvalidArgumentException('MULTIMEDIA_VALIDITY_INVALID');
    $from = $mode === 'explicit_date_range' ? (string)($validity['valid_from'] ?? '') : '';
    $to = $mode === 'explicit_date_range' ? (string)($validity['valid_to'] ?? '') : '';
    $validDate = static fn(string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) === 1
        && checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4));
    if ($mode === 'explicit_date_range' && (!$validDate($from) || !$validDate($to) || $to < $from))
        throw new InvalidArgumentException('MULTIMEDIA_DATE_RANGE_INVALID');
    $context = is_array($input['context'] ?? null) ? $input['context'] : [];
    $eventDate = trim((string)($context['event_date'] ?? ''));
    if ($eventDate !== '' && !$validDate($eventDate))
        throw new InvalidArgumentException('MULTIMEDIA_EVENT_DATE_INVALID');
    $safeContext = [];
    foreach (['clinical_event_or_procedure', 'anatomical_area', 'clinical_purpose_note'] as $key)
        $safeContext[$key] = trim((string)($context[$key] ?? ''));
    $safeContext['event_date'] = $eventDate;
    foreach ([$custom, ...array_values($safeContext)] as $value)
        if (mb_strlen($value, 'UTF-8') > 2000) throw new InvalidArgumentException('MULTIMEDIA_TEXT_TOO_LONG');
    $result = [
        'media_types' => $media, 'capture_allowed' => true, 'use_purposes' => $purposes,
        'identification_scope' => $identification, 'authorization_scope' => $scope,
        'custom_scope_description' => $custom,
        'validity' => ['mode' => $mode, 'valid_from' => $from, 'valid_to' => $to],
        'context' => $safeContext,
    ];
    if ($modelVersion === 2) {
        $sensitive = array_intersect($purposes, $additional) !== [];
        $confirmed = ($input['sensitive_purposes_confirmed'] ?? null) === true;
        $other = in_array('other_specific_use', $purposes, true)
            ? trim((string)($input['other_specific_use_description'] ?? '')) : '';
        if (in_array('other_specific_use', $purposes, true) && $other === '')
            throw new InvalidArgumentException('MULTIMEDIA_OTHER_USE_DESCRIPTION_REQUIRED');
        if (mb_strlen($other, 'UTF-8') > 2000) throw new InvalidArgumentException('MULTIMEDIA_TEXT_TOO_LONG');
        $result = ['model_version' => 2] + $result + [
            'sensitive_purposes_confirmed' => $sensitive && $confirmed,
            'other_specific_use_description' => $other,
        ];
    }
    return $result;
}

function clinical_multimedia_consent_require_sensitive_confirmation(array $consent): void
{
    if (($consent['model_version'] ?? 1) === 2
        && array_intersect($consent['use_purposes'], ['teaching', 'clinical_research', 'clinical_trial',
            'scientific_publication', 'institutional_communication', 'public_dissemination', 'other_specific_use'])
        && !$consent['sensitive_purposes_confirmed'])
        throw new InvalidArgumentException('MULTIMEDIA_SENSITIVE_PURPOSE_CONFIRMATION_REQUIRED');
}

function clinical_multimedia_consent_declaration(array $consent): string
{
    if (($consent['model_version'] ?? 1) === 2) return clinical_multimedia_consent_declaration_v2($consent);
    $media = ['photo' => 'fotografías', 'video' => 'video', 'audio' => 'audio'];
    $purpose = ['clinical_record' => 'integración al expediente clínico',
        'clinical_followup' => 'seguimiento de la evolución clínica'];
    $scope = ['specific_event' => 'este evento o atención', 'current_episode' => 'este episodio de atención',
        'current_condition_followup' => 'el seguimiento del padecimiento actual',
        'custom_limited_scope' => $consent['custom_scope_description']];
    $validity = $consent['validity']['mode'] === 'single_event' ? 'este evento únicamente'
        : 'el periodo del ' . $consent['validity']['valid_from'] . ' al ' . $consent['validity']['valid_to'];
    $identity = $consent['identification_scope'] === 'identifiable' ? 'identificable'
        : 'con identificación reducida, sin garantía de anonimato';
    $statement = 'De manera voluntaria autorizo la captura de ' . implode(', ', array_map(
        static fn(string $key): string => $media[$key], $consent['media_types']))
        . ' en relación con mi atención clínica. Autorizo su uso únicamente para '
        . implode(' y ', array_map(static fn(string $key): string => $purpose[$key], $consent['use_purposes']))
        . '. El material se utilizará de forma ' . $identity . ', en el ámbito de '
        . $scope[$consent['authorization_scope']] . ', durante ' . $validity . '.';
    $context = $consent['context'];
    $parts = [];
    foreach (['clinical_event_or_procedure' => 'Atención o procedimiento', 'anatomical_area' => 'Área anatómica',
        'clinical_purpose_note' => 'Nota de contexto clínico', 'event_date' => 'Fecha del evento'] as $key => $label)
        if ($context[$key] !== '') $parts[] = $label . ': ' . $context[$key];
    if ($parts) $statement .= ' Contexto: ' . implode('; ', $parts) . '.';
    return $statement . ' Recibí información sobre este alcance y tuve oportunidad de hacer preguntas antes de decidir.';
}

function clinical_multimedia_consent_declaration_v2(array $consent): string
{
    $media = ['photo' => 'fotografías', 'video' => 'registros de video', 'audio' => 'registros de audio'];
    $purposes = ['clinical_record' => 'integrar el expediente clínico',
        'clinical_followup' => 'documentar la evolución clínica', 'teaching' => 'docencia médica',
        'clinical_research' => 'investigación clínica', 'clinical_trial' => 'ensayo clínico',
        'scientific_publication' => 'presentaciones o publicaciones científicas',
        'institutional_communication' => 'comunicación institucional',
        'public_dissemination' => 'difusión pública',
        'other_specific_use' => 'otro uso específico: ' . $consent['other_specific_use_description']];
    $scope = ['specific_event' => 'solo este evento o atención', 'current_episode' => 'este episodio de atención',
        'current_condition_followup' => 'el seguimiento del padecimiento actual',
        'custom_limited_scope' => $consent['custom_scope_description']];
    $validity = match ($consent['validity']['mode']) {
        'single_event' => 'solo este evento o atención',
        'explicit_date_range' => 'el periodo del ' . $consent['validity']['valid_from'] . ' al ' . $consent['validity']['valid_to'],
        'until_revoked' => 'hasta que el paciente o su representante solicite su revocación para usos futuros',
    };
    $statement = 'Autorizo de manera libre y expresa la captura de '
        . implode(' y ', array_map(static fn(string $key): string => $media[$key], $consent['media_types']))
        . ' relacionados con mi atención médica, así como su uso únicamente para '
        . implode(', ', array_map(static fn(string $key): string => $purposes[$key], $consent['use_purposes'])) . '.';
    if ($consent['sensitive_purposes_confirmed'])
        $statement .= ' Confirmo que autoricé expresamente los usos adicionales seleccionados.';
    $statement .= $consent['identification_scope'] === 'identifiable'
        ? ' Comprendo que el rostro, la voz, características físicas u otros elementos pueden permitir reconocerme.'
        : ' Se procurará limitar los elementos que permitan identificarme cuando sea posible, sin garantía de que no pueda ser reconocido.';
    $statement .= ' Esta autorización cubre ' . $scope[$consent['authorization_scope']]
        . ' y permanecerá vigente ' . $validity . '.';
    $parts = [];
    foreach (['clinical_event_or_procedure' => 'Atención o procedimiento', 'anatomical_area' => 'Área anatómica',
        'clinical_purpose_note' => 'Nota de contexto clínico', 'event_date' => 'Fecha del evento'] as $key => $label)
        if ($consent['context'][$key] !== '') $parts[] = $label . ': ' . $consent['context'][$key];
    if ($parts) $statement .= ' Contexto: ' . implode('; ', $parts) . '.';
    return $statement . ' He recibido información sobre el propósito de la captura y uso del material, tuve oportunidad de realizar preguntas y manifiesto mi conformidad con las opciones seleccionadas.';
}

function clinical_multimedia_consent_render_html(array $payload): string
{
    $h = static fn(mixed $value): string => htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $consent = clinical_multimedia_consent_normalize((array)($payload['consent'] ?? []));
    $declaration = clinical_multimedia_consent_declaration($consent);
    $patient = (array)($payload['patient_snapshot'] ?? []);
    $actor = (array)($payload['actor_snapshot'] ?? []);
    $signer = (array)($payload['signer'] ?? []);
    $html = '<article class="informe-doc-sheet document-sheet doc-base-sheet doc-base-sheet--letter doc-base-print-safe"><div class="doc-base-sheet-inner doc-base-body">';
    if (clinical_legal_document_professional_header_mode($payload) === 'shown') {
        $html .= '<header class="clinical-doc-head doc-base-medical-header doc-base-header-block"><div class="clinical-doc-head-main"><div class="clinical-doc-doctor-name">'
            . $h($actor['full_name'] ?? '') . '</div>';
        foreach (['specialty', 'license', 'facility', 'place'] as $field)
            if (trim((string)($actor[$field] ?? '')) !== '') $html .= '<div class="clinical-doc-doctor-site">' . $h($actor[$field]) . '</div>';
        $html .= '</div></header>';
    }
    $html .= '<div class="doc-base-title-block-wrap"><section class="doc-base-title-block"><div class="doc-base-title">CONSENTIMIENTO MULTIMEDIA</div></section></div>'
        . '<div class="doc-base-patient-block-wrap"><section class="doc-base-patient-meta"><div><strong>Paciente:</strong> '
        . $h($patient['full_name'] ?? '') . '</div><div><strong>Fecha:</strong> ' . $h($payload['document_date'] ?? '')
        . '</div></section></div><div class="doc-base-body-block">';
    $sections = [
        'Material autorizado' => implode(', ', array_map(static fn(string $x): string => ['photo'=>'Fotografías','video'=>'Video','audio'=>'Audio'][$x], $consent['media_types'])),
        'Autorización de captura' => 'Sí, autorizo la captura del material seleccionado.',
        'Usos clínicos autorizados' => implode('; ', array_map(static fn(string $x): string => [
            'clinical_record'=>'Integración al expediente clínico','clinical_followup'=>'Seguimiento de la evolución clínica',
            'teaching'=>'Docencia médica','clinical_research'=>'Investigación clínica','clinical_trial'=>'Ensayo clínico',
            'scientific_publication'=>'Presentaciones o publicaciones científicas',
            'institutional_communication'=>'Comunicación institucional','public_dissemination'=>'Difusión pública',
            'other_specific_use'=>'Otro uso específico'][$x], $consent['use_purposes'])),
        'Identificación del material' => $consent['identification_scope'] === 'identifiable' ? 'Identificable' : 'Identificación reducida (no garantiza anonimato)',
        'Alcance' => ['specific_event'=>'Este evento o atención','current_episode'=>'Este episodio de atención',
            'current_condition_followup'=>'Seguimiento del padecimiento actual','custom_limited_scope'=>$consent['custom_scope_description']][$consent['authorization_scope']],
        'Vigencia' => $consent['validity']['mode'] === 'single_event' ? 'Este evento únicamente'
            : $consent['validity']['valid_from'] . ' al ' . $consent['validity']['valid_to'],
    ];
    if (($consent['model_version'] ?? 1) === 2) {
        $purposeLabels = ['clinical_record'=>'Expediente clínico',
            'clinical_followup'=>'Seguimiento de la evolución clínica', 'teaching'=>'Docencia médica',
            'clinical_research'=>'Investigación clínica', 'clinical_trial'=>'Ensayo clínico',
            'scientific_publication'=>'Presentaciones o publicaciones científicas',
            'institutional_communication'=>'Comunicación institucional', 'public_dissemination'=>'Difusión pública',
            'other_specific_use'=>'Otro uso específico: ' . $consent['other_specific_use_description']];
        $sections['¿Para qué se autoriza el uso del material?'] = implode('; ', array_map(
            static fn(string $x): string => $purposeLabels[$x], $consent['use_purposes']));
        unset($sections['Usos clínicos autorizados']);
        if ($consent['sensitive_purposes_confirmed'])
            $sections['Confirmación de usos adicionales'] = 'El paciente autorizó expresamente los usos adicionales seleccionados.';
        $sections['¿La persona puede ser reconocida en el material?'] = $consent['identification_scope'] === 'identifiable'
            ? 'Sí, puede ser identificada. El rostro, la voz, características físicas u otros elementos pueden permitir reconocer al paciente.'
            : 'Se reducirán los elementos que permitan identificarla. Se procurará limitar datos o elementos identificadores cuando sea posible, aunque esto no garantiza que la persona no pueda ser reconocida.';
        unset($sections['Identificación del material']);
        $sections['¿Qué atención cubre esta autorización?'] = $sections['Alcance'];
        unset($sections['Alcance']);
        $sections['¿Hasta cuándo aplica esta autorización?'] = match ($consent['validity']['mode']) {
            'single_event' => 'Sólo para este evento o atención',
            'explicit_date_range' => $consent['validity']['valid_from'] . ' al ' . $consent['validity']['valid_to'],
            'until_revoked' => 'Hasta que solicite su revocación. Esta autorización permanecerá vigente para los usos seleccionados hasta que el paciente o su representante solicite su revocación para usos futuros.',
        };
        unset($sections['Vigencia']);
    }
    foreach (['clinical_event_or_procedure'=>'Atención o procedimiento','anatomical_area'=>'Área anatómica',
        'clinical_purpose_note'=>'Nota de contexto clínico','event_date'=>'Fecha del evento'] as $key=>$label)
        if ($consent['context'][$key] !== '') $sections[$label] = $consent['context'][$key];
    if (($signer['mode'] ?? '') === 'representative') {
        $sections['Firmante previsto'] = 'Representante: ' . (string)($signer['name'] ?? '');
        $sections['Carácter y relación'] = trim((string)($signer['character'] ?? '')) . '; '
            . trim((string)($signer['relationship'] ?? ''));
        $sections['Autoridad del representante'] = (string)($signer['authority'] ?? '');
    } else $sections['Firmante previsto'] = 'Paciente';
    $sections['Declaración'] = $declaration;
    foreach ($sections as $label=>$value)
        $html .= '<section class="informe-doc-body-section doc-base-section"><div class="informe-doc-section-title doc-base-section-title">'
            . $h($label) . '</div><div class="informe-doc-text doc-base-text">' . nl2br($h($value)) . '</div></section>';
    foreach (['signer' => 'Firmante', 'doctor' => 'Médico responsable'] as $role => $label) {
        $entry = (array)($payload['signatures'][$role] ?? []);
        $image = (string)($entry['image_data'] ?? '');
        $name = $role === 'doctor' ? ($actor['full_name'] ?? '')
            : (($signer['mode'] ?? '') === 'patient' ? ($patient['full_name'] ?? '') : ($signer['name'] ?? ''));
        $html .= '<section class="informe-doc-sign doc-base-signature"><div class="informe-doc-section-title doc-base-section-title">'
            . $label . '</div>';
        if (preg_match('#^data:image/png;base64,[A-Za-z0-9+/]+={0,2}$#D', $image) === 1)
            $html .= '<img class="informe-doc-sign-image" src="' . $h($image) . '" alt="Firma de ' . $h($name) . '">';
        else $html .= '<div class="informe-doc-sign-line"></div>';
        $html .= '<div class="informe-doc-sign-meta doc-base-signature-meta">' . $h($name)
            . '</div></section>';
    }
    $html .= '</div></div></article>';
    return $html;
}
