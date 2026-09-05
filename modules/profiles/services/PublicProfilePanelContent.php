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
        $about = [];
        $consultation = [];
        $add = static function (array &$groups, string $title, array $items): void {
            if ($items !== []) {
                $groups[] = ['title' => $title, 'items' => $items];
            }
        };
        $bio = self::text($professional['bio_long'] ?? null) ?? self::text($professional['bio_short'] ?? null);
        $add($about, 'Perfil profesional', $bio !== null ? [$bio] : []);
        $specialties = self::items($data['specialties'] ?? []);
        if ($specialties === []) {
            $specialty = self::text($professional['specialty_primary'] ?? null);
            $specialties = $specialty !== null ? [$specialty] : [];
        }
        $add($about, 'Especialidades', $specialties);
        $licenses = [];
        foreach (['professional_license' => 'Cédula profesional', 'specialty_license' => 'Cédula de especialidad'] as $key => $label) {
            $value = self::text($professional[$key] ?? null);
            if ($value !== null) {
                $licenses[] = $label . ': ' . $value;
            }
        }
        $add($about, 'Cédulas', $licenses);
        foreach (['education' => 'Formación profesional', 'certifications' => 'Certificaciones', 'professional_associations' => 'Asociaciones', 'conditions_treated' => 'Padecimientos y tratamientos', 'languages' => 'Idiomas'] as $key => $label) {
            $add($about, $label, self::items($professional[$key] ?? []));
        }
        $years = $professional['years_experience'] ?? null;
        if (is_numeric($years) && (int)$years > 0) {
            $add($about, 'Experiencia', [(int)$years . ' años de experiencia profesional']);
        }

        $schedules = [];
        foreach ((array)($data['consultorios'] ?? []) as $office) {
            if (!is_array($office)) {
                continue;
            }
            $summary = self::text($office['schedule_summary'] ?? null);
            if ($summary === null) {
                $items = self::items($office['schedule_summary'] ?? []);
                $summary = $items !== [] ? implode(' · ', $items) : null;
            }
            if ($summary !== null) {
                $name = self::text($office['public_name'] ?? null);
                $schedules[] = ($name !== null ? $name . ': ' : '') . $summary;
            }
        }
        $add($consultation, 'Horarios por consultorio', $schedules);
        $add($consultation, 'Servicios de consulta', self::items($professional['services'] ?? []));
        $commercial = (array)($data['commercial_visibility'] ?? []);
        if (($visibility['show_consultation_fee'] ?? false) === true) {
            $fee = self::text($commercial['consultation_fee'] ?? null);
            $add($consultation, 'Costo de consulta', $fee !== null ? [$fee] : []);
            $add($consultation, 'Medios de pago', self::items($commercial['payment_methods'] ?? []));
        }
        if (($visibility['show_accepted_insurances'] ?? false) === true) {
            $add($consultation, 'Aseguradoras aceptadas', self::items($commercial['accepted_insurances'] ?? []));
        }

        $views = [];
        foreach ([
            'about' => ['show_about_action', 'Sobre mí', $about, 'Este perfil aún no cuenta con información profesional adicional publicada.'],
            'consultation' => ['show_consulta_action', 'Detalles sobre la consulta', $consultation, 'Este perfil aún no cuenta con detalles de consulta publicados.'],
        ] as $key => [$capability, $title, $groups, $emptyMessage]) {
            if (($visibility[$capability] ?? false) === true) {
                $views[$key] = ['title' => $title, 'groups' => $groups, 'empty_message' => $emptyMessage];
            }
        }
        return $views;
    }

    private static function text($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $text = trim((string)$value);
        return $text !== '' ? $text : null;
    }

    private static function items($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        foreach ($value as $item) {
            $text = is_array($item)
                ? (self::text($item['name_es'] ?? null) ?? self::text($item['name'] ?? null) ?? self::text($item['title'] ?? null))
                : self::text($item);
            if ($text !== null) {
                $items[] = $text;
            }
        }
        return array_values(array_unique($items));
    }
}
