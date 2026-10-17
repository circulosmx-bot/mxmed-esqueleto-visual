<?php
declare(strict_types=1);
namespace Profiles\Services;

/** Re-resolved public display context. Query parameters are NEVER request/ownership authority. */
final class PublicEnrollmentPresentation
{
    public static function resolve(array $query, callable $publicProfile): ?array
    {
        if (!array_key_exists('entry', $query)) return null;
        $base = ['mode' => 'unavailable', 'title' => 'Tu incorporación a México Médico',
            'display_name' => null, 'designation' => null, 'return_url' => '/profiles/listing.php',
            'continue_url' => '/public/identity/perfil-medico.php?entry=join',
            'message' => 'No encontramos un perfil disponible para este trámite. Puedes volver al directorio o consultar cómo formar parte de México Médico.',
            'presentation_only' => true, 'origin_saved' => false];
        if (($query['entry'] ?? null) === 'join' && !array_key_exists('profile', $query)) {
            return array_replace($base, ['mode' => 'join', 'title' => 'Forma parte de México Médico',
                'message' => 'La inscripción profesional está pendiente de habilitación. Puedes conocer los pasos; todavía no se reciben solicitudes ni documentos.']);
        }
        $id = $query['profile'] ?? null;
        if (($query['entry'] ?? null) !== 'claim' || !is_string($id)
            || preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/D', $id) !== 1) return $base;
        $response = $publicProfile($id);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $claim = is_array($data['claim'] ?? null) ? $data['claim'] : [];
        $expected = '/public/identity/perfil-medico.php?entry=claim&profile=' . rawurlencode($id);
        if (($response['ok'] ?? false) !== true || ($data['profile']['doctor_id'] ?? null) !== $id
            || ($data['profile']['is_public'] ?? false) !== true || ($claim['claim_allowed'] ?? false) !== true
            || ($claim['claim_url'] ?? null) !== $expected) return $base;
        return array_replace($base, ['mode' => 'claim', 'title' => 'Administra tu perfil profesional',
            'display_name' => (string)($data['identity']['display_name'] ?? ''),
            'designation' => $data['identity']['professional_designation'] ?? null,
            'return_url' => '/profiles/doctor.php?doctor_id=' . rawurlencode($id), 'continue_url' => $expected,
            'message' => 'La solicitud para administrar este perfil está pendiente de habilitación. Por ahora puedes consultar el perfil y conocer los pasos; no se ha guardado ni enviado una solicitud.']);
    }
}
