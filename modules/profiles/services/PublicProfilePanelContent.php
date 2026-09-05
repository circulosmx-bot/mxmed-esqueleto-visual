<?php
declare(strict_types=1);

namespace Profiles\Services;

/** Presentation groups built exclusively from the already-public profile DTO. */
final class PublicProfilePanelContent
{
    public static function build(array $data): array
    {
        $professional = (array)($data['professional'] ?? []);
        $visibility = (array)($data['public_visibility'] ?? []);
        $bio = self::text($professional['bio_long'] ?? null) ?? self::text($professional['bio_short'] ?? null);
        $specialties = self::items($data['specialties'] ?? []);
        if ($specialties === []) {
            $specialty = self::text($professional['specialty_primary'] ?? null);
            $specialties = $specialty !== null ? [$specialty] : [];
        }
        $about = [
            self::group('Formación académica', self::items($professional['education'] ?? []), 'school', 'left'),
            self::group('Certificaciones y asociaciones', array_values(array_unique(array_merge(
                self::items($professional['certifications'] ?? []),
                self::items($professional['professional_associations'] ?? [])
            ))), 'workspace_premium', 'left'),
            self::group('Especialista en', $specialties, 'person', 'right'),
            self::group('Principales enfermedades y tratamientos', self::items($professional['conditions_treated'] ?? []), 'monitor_heart', 'right'),
        ];
        $licenses = [];
        foreach (['professional_license' => 'Cédula profesional', 'specialty_license' => 'Cédula de especialidad'] as $key => $label) {
            $value = self::text($professional[$key] ?? null);
            if ($value !== null) $licenses[] = $label . ': ' . $value;
        }
        if ($licenses !== []) $about[] = self::group('Cédulas profesionales', $licenses, 'badge', 'left');
        $languages = self::items($professional['languages'] ?? []);
        if ($languages !== []) $about[] = self::group('Idiomas', $languages, 'translate', 'right');
        $years = $professional['years_experience'] ?? null;
        if (is_numeric($years) && (int)$years > 0) {
            $about[] = self::group('Experiencia', [(int)$years . ((int)$years === 1 ? ' año' : ' años') . ' de experiencia profesional'], 'work_history', 'left');
        }

        $schedules = [];
        $scheduleActions = [];
        $contacts = [];
        $modalities = [];
        $agenda = ($visibility['show_public_agenda'] ?? false) === true
            && self::text($data['agenda_public']['availability_endpoint'] ?? null) !== null;
        foreach (array_values((array)($data['consultorios'] ?? [])) as $index => $office) {
            if (!is_array($office)) continue;
            $name = self::text($office['public_name'] ?? null) ?? 'Consultorio';
            $summary = self::scheduleText($office['schedule_summary'] ?? null);
            if ($summary !== null) {
                $scheduleActions[count($schedules)] = $agenda ? '#proximas-citas' : null;
                $schedules[] = $agenda ? $name : $name . ': ' . $summary;
            }
            // Office destinations have already passed the public-contact visibility gate.
            $phone = self::phoneHref(self::text($office['phone_public'] ?? null));
            $whatsapp = self::phoneHref(self::text($office['whatsapp_public'] ?? null));
            $contacts[] = [
                'panel_id' => 'mxpp-consultorio-panel-' . ($index + 1),
                'phone' => $phone,
                'whatsapp' => $whatsapp !== null ? 'https://wa.me/' . preg_replace('/\D/', '', $whatsapp) : null,
            ];
            foreach (self::items($office['modalities'] ?? []) as $mode) {
                $label = ['in_person' => 'Consulta presencial', 'presencial' => 'Consulta presencial',
                    'online' => 'Consulta en línea', 'video' => 'Consulta en línea',
                    'Consulta presencial' => 'Consulta presencial', 'Consulta en línea' => 'Consulta en línea'][$mode] ?? null;
                if ($label !== null) $modalities[] = $label;
            }
        }
        $commercial = (array)($data['commercial_visibility'] ?? []);
        $showFee = ($visibility['show_consultation_fee'] ?? false) === true;
        $fee = $showFee ? self::text($commercial['consultation_fee'] ?? null) : null;
        $insurers = ($visibility['show_accepted_insurances'] ?? false) === true
            ? self::insurers($commercial['accepted_insurances'] ?? []) : [];
        $hours = self::group('Horarios', $schedules, 'alarm', 'left', 'outlined');
        $hours['schedule_actions'] = $scheduleActions;
        $insurance = self::group('Aseguradoras aceptadas', array_column($insurers, 'name'), 'health_and_safety', 'right');
        $insurance['logos'] = array_column($insurers, 'logo_url');
        $consultation = [
            self::group('Atención a', self::items($professional['target_audience'] ?? []), 'group', 'left', 'outlined'),
            $hours,
            self::group('Costo de la consulta', $fee !== null ? [$fee] : [], 'payments', 'left'),
            self::group('Medios de pago', $showFee ? self::items($commercial['payment_methods'] ?? []) : [], 'payments', 'left'),
            $insurance,
            self::group('Modalidad de consulta', array_values(array_unique($modalities)), 'stethoscope', 'right'),
        ];

        $views = [];
        foreach ([
            'about' => ['show_about_action', 'Mi formación profesional', $about, $bio, 'Este perfil aún no cuenta con información profesional adicional publicada.'],
            'consultation' => ['show_consulta_action', 'Detalles sobre la consulta', $consultation, null, 'Este perfil aún no cuenta con detalles de consulta publicados.'],
        ] as $key => [$capability, $title, $groups, $intro, $emptyMessage]) {
            if (($visibility[$capability] ?? false) !== true) continue;
            $hasContent = $intro !== null || array_filter($groups, static fn(array $group): bool => $group['items'] !== []) !== [];
            // CONSULTA retains its product sections even before their data is published.
            $groups = $hasContent || $key === 'consultation' ? $groups : [];
            $columns = ['left' => [], 'right' => []];
            foreach ($groups as $group) $columns[$group['column']][] = $group;
            $views[$key] = ['title' => $title, 'intro' => $intro, 'groups' => $groups, 'columns' => $columns, 'empty_message' => $emptyMessage];
        }
        if (isset($views['consultation'])) {
            $views['consultation']['contacts'] = $contacts;
            $views['consultation']['agenda'] = $agenda;
        }
        return $views;
    }

    private static function group(string $title, array $items, string $icon, string $column, string $iconStyle = 'rounded'): array
    {
        return ['title' => $title, 'items' => $items, 'icon' => $icon, 'icon_style' => $iconStyle, 'column' => $column, 'empty_message' => 'Información aún no publicada.'];
    }

    private static function scheduleText($value): ?string
    {
        if (!is_array($value)) return self::text($value);
        $lines = self::items($value);
        $days = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        foreach ($value as $row) {
            if (!is_array($row) || !isset($days[$row['weekday'] ?? 0])) continue;
            foreach ((array)($row['windows'] ?? []) as $slot) {
                if (!is_array($slot)) continue;
                $start = self::text($slot['start_time'] ?? null);
                $end = self::text($slot['end_time'] ?? null);
                if ($start !== null && $end !== null) $lines[] = $days[$row['weekday']] . ' ' . $start . '–' . $end;
            }
        }
        return $lines !== [] ? implode(' · ', $lines) : null;
    }

    /** Public insurer contract: name/name_es/title plus optional safe logo_url. */
    private static function insurers($value): array
    {
        if (!is_array($value)) return [];
        $result = [];
        foreach ($value as $item) {
            $names = self::items([$item]);
            if ($names === []) continue;
            $url = is_array($item) ? self::text($item['logo_url'] ?? null) : null;
            $safe = $url !== null && !preg_match('/[\\\\\s]/', $url)
                && ((str_starts_with($url, '/') && !str_starts_with($url, '//'))
                    || (filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'));
            $result[] = ['name' => $names[0], 'logo_url' => $safe ? $url : null];
        }
        return $result;
    }

    private static function phoneHref(?string $value): ?string
    {
        if ($value === null) return null;
        $digits = preg_replace('/\D/', '', $value);
        if (!is_string($digits) || strlen($digits) < 7 || strlen($digits) > 16) return null;
        return 'tel:' . (str_starts_with($value, '+') ? '+' : '') . $digits;
    }

    private static function text($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) return null;
        $text = trim((string)$value);
        return $text !== '' ? $text : null;
    }

    private static function items($value): array
    {
        if (!is_array($value)) return [];
        $items = [];
        foreach ($value as $item) {
            $text = is_array($item)
                ? (self::text($item['name_es'] ?? null) ?? self::text($item['name'] ?? null) ?? self::text($item['title'] ?? null))
                : self::text($item);
            if ($text !== null) $items[] = $text;
        }
        return array_values(array_unique($items));
    }
}
