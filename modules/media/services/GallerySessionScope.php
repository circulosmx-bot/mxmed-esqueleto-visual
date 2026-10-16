<?php
declare(strict_types=1);

namespace Media\Services;
require_once __DIR__ . '/../../identity/http/IdentityHttpComposition.php';

/** Consumes the established server session_scope contract; never request identity. */
final class GallerySessionScope
{
    public static function resolve(array $session, bool $allowDevFixture = false): ?array
    {
        // PHP session aliases never outlive canonical Identity in normal-local.
        // Keep legacy fixture contracts isolated from the normal application.
        if (\Identity\Http\IdentityHttpComposition::canonicalRuntimeRequested()) {
            require_once __DIR__ . '/../../identity/http/PrivateApplicationAccessResolver.php';
            try {
                $access = \Identity\Http\PrivateApplicationAccessResolver::fromProcessEnvironment()->resolve($_COOKIE);
                if (!$access->allowed) return null;
                return ['user_id'=>(string)$access->context['account']['id'],
                    'doctor_id'=>(string)$access->context['professional']['doctor_id'],
                    'session_id'=>(string)$access->session->sessionId()];
            } catch (\Throwable) { return null; }
        }
        $value = static function (array $keys) use ($session): string {
            foreach ($keys as $key) {
                $v = trim((string)($session[$key] ?? ''));
                if ($v !== '') return $v;
            }
            return '';
        };
        if (!empty($session['subscriptions_dev_session_fixture']) && !$allowDevFixture) return null;
        $user = $value(['user_id', 'mxmed_user_id', 'auth_user_id']);
        $doctor = $value(['doctor_id', 'active_doctor_id', 'mxmed_doctor_id']);
        $type = strtolower($value(['entity_type', 'active_entity_type']));
        $entity = $value(['entity_id', 'active_entity_id']);
        if ($doctor === '' && $type === 'doctor') $doctor = $entity;
        if ($user === '' || $doctor === '') return null;
        if ($type !== '' && $type !== 'doctor') return null;
        if ($entity !== '' && $entity !== $doctor) return null;
        // Conflicting aliases must not silently select a different physician.
        foreach (['doctor_id', 'active_doctor_id', 'mxmed_doctor_id'] as $key) {
            $candidate = trim((string)($session[$key] ?? ''));
            if ($candidate !== '' && $candidate !== $doctor) return null;
        }
        $role = strtolower($value(['actor_role', 'user_role', 'role', 'mxmed_user_role']));
        if ($value(['operator_id']) !== '' || ($role !== '' && !in_array($role, ['doctor', 'medico', 'principal', 'owner'], true))) return null;
        return ['user_id' => $user, 'doctor_id' => $doctor];
    }
}
