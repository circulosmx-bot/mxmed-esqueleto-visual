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
        $phones = [];
        $phoneLinks = [];
        foreach ((array)($data['consultorios'] ?? []) as $office) {
            if (!is_array($office)) continue;
            $name = self::text($office['public_name'] ?? null);
            $summary = self::text($office['schedule_summary'] ?? null);
            if ($summary === null) {
                $items = self::items($office['schedule_summary'] ?? []);
                $summary = $items !== [] ? implode(' · ', $items) : null;
            }
            if ($summary !== null) $schedules[] = ($name !== null ? $name . ': ' : '') . $summary;
            // These fields are already visibility-gated by the public office DTO.
            foreach (['phone_public' => 'Tel. consultorio', 'emergency_phone_public' => 'Urgencias'] as $key => $label) {
                $phone = self::text($office[$key] ?? null);
                $href = self::phoneHref($phone);
                if ($href !== null) {
                    $phoneLinks[count($phones)] = $href;
                    $phones[] = ($name !== null ? $name . ' · ' : '') . $label . ': ' . $phone;
                }
            }
        }
        $consultation = [
            // No public patient-audience field exists yet; never infer it from specialty.
            self::group('Atención a', [], 'groups', 'left'),
            self::group('Horarios', $schedules, 'event_available', 'left'),
            self::group('Servicios de consulta', self::items($professional['services'] ?? []), 'stethoscope', 'right'),
        ];
        $commercial = (array)($data['commercial_visibility'] ?? []);
        if (($visibility['show_consultation_fee'] ?? false) === true) {
            $consultation[] = self::group('Medios de pago', self::items($commercial['payment_methods'] ?? []), 'payments', 'left');
            $fee = self::text($commercial['consultation_fee'] ?? null);
            if ($fee !== null) $consultation[] = self::group('Costo de consulta', [$fee], 'payments', 'left');
        }
        if (($visibility['show_accepted_insurances'] ?? false) === true) {
            array_splice($consultation, 2, 0, [self::group('Aseguradoras aceptadas', self::items($commercial['accepted_insurances'] ?? []), 'health_and_safety', 'right')]);
        }
        if ($phones !== []) {
            $contacts = self::group('Teléfonos y urgencias', $phones, 'call', 'right');
            $contacts['links'] = $phoneLinks;
            $consultation[] = $contacts;
        }

        $views = [];
        foreach ([
            'about' => ['show_about_action', 'Mi formación profesional', $about, $bio, 'Este perfil aún no cuenta con información profesional adicional publicada.'],
            'consultation' => ['show_consulta_action', 'Detalles sobre la consulta', $consultation, null, 'Este perfil aún no cuenta con detalles de consulta publicados.'],
        ] as $key => [$capability, $title, $groups, $intro, $emptyMessage]) {
            if (($visibility[$capability] ?? false) !== true) continue;
            $hasContent = $intro !== null || array_filter($groups, static fn(array $group): bool => $group['items'] !== []) !== [];
            // Section-level placeholders only accompany real published information.
            $groups = $hasContent ? $groups : [];
            $columns = ['left' => [], 'right' => []];
            foreach ($groups as $group) $columns[$group['column']][] = $group;
            $views[$key] = ['title' => $title, 'intro' => $intro, 'groups' => $groups, 'columns' => $columns, 'empty_message' => $emptyMessage];
        }
        return $views;
    }

    private static function group(string $title, array $items, string $icon, string $column): array
    {
        return ['title' => $title, 'items' => $items, 'icon' => $icon, 'column' => $column, 'empty_message' => 'Información aún no publicada.'];
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
