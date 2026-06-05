<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/profiles/repositories/PublicProfileRepository.php';
require_once __DIR__ . '/../../modules/profiles/controllers/PublicProfileController.php';
require_once __DIR__ . '/../../modules/profiles/repositories/PrivateProfileRepository.php';
require_once __DIR__ . '/../../modules/profiles/controllers/PrivateProfileController.php';
require_once __DIR__ . '/../../modules/profiles/repositories/DoctorContactPointsRepository.php';
require_once __DIR__ . '/../../modules/profiles/controllers/DoctorContactPointsController.php';

use Profiles\Repositories\PublicProfileRepository;
use Profiles\Controllers\PublicProfileController;
use Profiles\Repositories\PrivateProfileRepository;
use Profiles\Controllers\PrivateProfileController;
use Profiles\Repositories\DoctorContactPointsRepository;
use Profiles\Controllers\DoctorContactPointsController;

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function profileRespond(array $response, int $status = 200): void
{
    if (isset($response['meta']) && is_array($response['meta'])) {
        $response['meta'] = (object)$response['meta'];
    } elseif (!isset($response['meta']) || !is_object($response['meta'])) {
        $response['meta'] = (object)[];
    }
    http_response_code($status);
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'profile_public_unavailable',
            'message' => 'internal error',
            'data' => null,
            'meta' => (object)[
                'contract' => 'profile_public_mvp',
                'version' => 'PP-4B',
                'generated_at' => gmdate('c'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }
    echo $json;
}

function profileRelativeSegments(): array
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    $path = is_string($path) ? $path : '';

    $marker = '/api/profiles/index.php';
    $relative = '';
    $pos = strpos($path, $marker);
    if ($pos !== false) {
        $relative = substr($path, $pos + strlen($marker));
    } elseif (strpos($path, '/api/profiles/') === 0) {
        $relative = substr($path, strlen('/api/profiles'));
    } else {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $relative = (string)substr($path, strlen((string)$script));
    }

    $relative = trim((string)$relative, '/');
    if ($relative === '') {
        return [];
    }

    $segments = explode('/', $relative);
    if (!empty($segments) && $segments[0] === 'index.php') {
        array_shift($segments);
    }
    return array_values(array_filter($segments, static fn($s) => $s !== ''));
}

function profileInvalidDoctorId(string $doctorId): bool
{
    if ($doctorId === '' || strlen($doctorId) > 64) {
        return true;
    }
    return !preg_match('/^[A-Za-z0-9._:-]+$/', $doctorId);
}

function profileReadJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return ['ok' => true, 'data' => []];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'invalid_json'];
    }
    return ['ok' => true, 'data' => $decoded];
}

function profileBoolEnvFlag($value): bool
{
    $raw = strtolower(trim((string)($value ?? '')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function profileResolvePrivateContext(string $doctorId): array
{
    $strict = profileBoolEnvFlag(getenv('MXMED_PROFILES_PRIVATE_AUTH_REQUIRED'));
    $headers = function_exists('getallheaders') ? (array)getallheaders() : [];
    $headerUserId = trim((string)($headers['X-User-Id'] ?? $headers['x-user-id'] ?? ''));
    $sessionUserId = trim((string)(
        $_SESSION['user_id']
        ?? $_SESSION['mxmed_user_id']
        ?? $_SESSION['auth_user_id']
        ?? ''
    ));
    $headerDoctorId = trim((string)($headers['X-Doctor-Id'] ?? $headers['x-doctor-id'] ?? ''));
    $sessionDoctorId = trim((string)(
        $_SESSION['doctor_id']
        ?? $_SESSION['active_doctor_id']
        ?? $_SESSION['mxmed_doctor_id']
        ?? ''
    ));

    $userId = $sessionUserId !== '' ? $sessionUserId : $headerUserId;
    $scopeDoctorId = $sessionDoctorId !== '' ? $sessionDoctorId : $headerDoctorId;
    $authMode = $strict ? 'strict' : 'transitional_open';

    if ($strict && $userId === '') {
        return [
            'ok' => false,
            'status' => 401,
            'response' => [
                'ok' => false,
                'error' => 'unauthorized',
                'message' => 'authentication required',
                'data' => null,
                'meta' => [
                    'contract' => 'profile_private_identity_mvp',
                    'version' => 'PP-7H2-A',
                    'generated_at' => gmdate('c'),
                    'auth_mode' => $authMode,
                ],
            ],
        ];
    }

    if ($strict && $scopeDoctorId !== '' && $scopeDoctorId !== $doctorId) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => [
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'doctor scope mismatch',
                'data' => null,
                'meta' => [
                    'contract' => 'profile_private_identity_mvp',
                    'version' => 'PP-7H2-A',
                    'generated_at' => gmdate('c'),
                    'auth_mode' => $authMode,
                ],
            ],
        ];
    }

    return [
        'ok' => true,
        'auth_mode' => $authMode,
        'actor_user_id' => $userId,
        'actor_doctor_id' => $scopeDoctorId,
    ];
}

function profilePublicMeta(): array
{
    return [
        'contract' => 'profile_public_mvp',
        'version' => 'PP-7D',
        'generated_at' => gmdate('c'),
    ];
}

function profilePrivateMeta(string $authMode = 'transitional_open'): array
{
    return [
        'contract' => 'profile_private_identity_mvp',
        'version' => 'PP-7H2-A',
        'generated_at' => gmdate('c'),
        'auth_mode' => $authMode,
    ];
}

function profileContactPointsPrivateMeta(string $authMode = 'transitional_open'): array
{
    return [
        'contract' => 'doctor_contact_points_private',
        'version' => 'SYS-Data-01P',
        'generated_at' => gmdate('c'),
        'auth_mode' => $authMode,
        'source' => 'doctor_contact_points',
    ];
}

try {
    $method = strtoupper(trim((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $segments = profileRelativeSegments();

    if (count($segments) === 3 && $segments[0] === 'public' && $segments[1] === 'doctor') {
        if ($method !== 'GET') {
            profileRespond([
                'ok' => false,
                'error' => 'method_not_allowed',
                'message' => 'method not allowed',
                'data' => null,
                'meta' => profilePublicMeta(),
            ], 405);
            return;
        }

        $doctorId = trim((string)$segments[2]);
        if (profileInvalidDoctorId($doctorId)) {
            profileRespond([
                'ok' => false,
                'error' => 'invalid_doctor_id',
                'message' => 'doctor_id invalid',
                'data' => null,
                'meta' => profilePublicMeta(),
            ], 400);
            return;
        }

        $repo = new PublicProfileRepository(mxmed_pdo());
        $controller = new PublicProfileController($repo);
        $response = $controller->showByDoctorId($doctorId);
        $error = (string)($response['error'] ?? '');
        if ($error === 'invalid_doctor_id') {
            profileRespond($response, 400);
            return;
        }
        if ($error === 'profile_not_found') {
            profileRespond($response, 404);
            return;
        }
        profileRespond($response, (($response['ok'] ?? false) ? 200 : 500));
        return;
    }

    if (count($segments) === 4 && $segments[0] === 'private' && $segments[1] === 'doctor' && $segments[3] === 'contact-points') {
        $doctorId = trim((string)$segments[2]);
        if (profileInvalidDoctorId($doctorId)) {
            profileRespond([
                'ok' => false,
                'error' => 'invalid_doctor_id',
                'message' => 'doctor_id invalid',
                'data' => null,
                'meta' => profileContactPointsPrivateMeta(),
            ], 400);
            return;
        }
        if (!in_array($method, ['GET', 'POST'], true)) {
            profileRespond([
                'ok' => false,
                'error' => 'method_not_allowed',
                'message' => 'method not allowed',
                'data' => null,
                'meta' => profileContactPointsPrivateMeta(),
            ], 405);
            return;
        }

        $context = profileResolvePrivateContext($doctorId);
        if (!(bool)($context['ok'] ?? false)) {
            profileRespond((array)($context['response'] ?? []), (int)($context['status'] ?? 403));
            return;
        }
        $authMode = (string)($context['auth_mode'] ?? 'transitional_open');

        $repo = new DoctorContactPointsRepository(mxmed_pdo());
        $controller = new DoctorContactPointsController($repo);
        if ($method === 'GET') {
            $response = $controller->index($doctorId, $authMode);
        } else {
            $jsonBody = profileReadJsonBody();
            if (!(bool)($jsonBody['ok'] ?? false)) {
                profileRespond([
                    'ok' => false,
                    'error' => 'invalid_json',
                    'message' => 'invalid json',
                    'data' => null,
                    'meta' => profileContactPointsPrivateMeta($authMode),
                ], 400);
                return;
            }
            $response = $controller->store($doctorId, (array)($jsonBody['data'] ?? []), $authMode);
        }

        $error = (string)($response['error'] ?? '');
        $statusMap = [
            'invalid_doctor_id' => 400,
            'invalid_json' => 400,
            'invalid_payload' => 400,
            'validation_error' => 422,
            'duplicate_active_contact' => 409,
            'db_not_ready' => 503,
            'profile_contact_points_unavailable' => 500,
            'unauthorized' => 401,
            'forbidden' => 403,
        ];
        if ($error !== '' && isset($statusMap[$error])) {
            profileRespond($response, $statusMap[$error]);
            return;
        }
        profileRespond($response, (($response['ok'] ?? false) ? 200 : 500));
        return;
    }

    if (count($segments) === 3 && $segments[0] === 'private' && $segments[1] === 'doctor') {
        $doctorId = trim((string)$segments[2]);
        if (profileInvalidDoctorId($doctorId)) {
            profileRespond([
                'ok' => false,
                'error' => 'invalid_doctor_id',
                'message' => 'doctor_id invalid',
                'data' => null,
                'meta' => profilePrivateMeta(),
            ], 400);
            return;
        }
        if (!in_array($method, ['GET', 'PATCH'], true)) {
            profileRespond([
                'ok' => false,
                'error' => 'method_not_allowed',
                'message' => 'method not allowed',
                'data' => null,
                'meta' => profilePrivateMeta(),
            ], 405);
            return;
        }

        $context = profileResolvePrivateContext($doctorId);
        if (!(bool)($context['ok'] ?? false)) {
            profileRespond((array)($context['response'] ?? []), (int)($context['status'] ?? 403));
            return;
        }
        $authMode = (string)($context['auth_mode'] ?? 'transitional_open');

        $repo = new PrivateProfileRepository(mxmed_pdo());
        $controller = new PrivateProfileController($repo);
        if ($method === 'GET') {
            $response = $controller->showByDoctorId($doctorId, $authMode);
        } else {
            $jsonBody = profileReadJsonBody();
            if (!(bool)($jsonBody['ok'] ?? false)) {
                profileRespond([
                    'ok' => false,
                    'error' => 'invalid_json',
                    'message' => 'invalid json',
                    'data' => null,
                    'meta' => profilePrivateMeta($authMode),
                ], 400);
                return;
            }
            $response = $controller->patchByDoctorId($doctorId, (array)($jsonBody['data'] ?? []), $authMode);
        }

        $error = (string)($response['error'] ?? '');
        $statusMap = [
            'invalid_doctor_id' => 400,
            'invalid_json' => 400,
            'invalid_payload' => 400,
            'profile_identity_not_found' => 404,
            'method_not_allowed' => 405,
            'unauthorized' => 401,
            'forbidden' => 403,
            'profile_private_unavailable' => 500,
        ];
        if ($error !== '' && isset($statusMap[$error])) {
            profileRespond($response, $statusMap[$error]);
            return;
        }
        profileRespond($response, (($response['ok'] ?? false) ? 200 : 500));
        return;
    }

    profileRespond([
        'ok' => false,
        'error' => 'not_found',
        'message' => 'route not found',
        'data' => null,
        'meta' => profilePublicMeta(),
    ], 404);
} catch (\RuntimeException $e) {
    $segments = profileRelativeSegments();
    $isPrivateRoute = (count($segments) >= 1 && $segments[0] === 'private');
    profileRespond([
        'ok' => false,
        'error' => $isPrivateRoute ? 'profile_private_unavailable' : 'profile_public_unavailable',
        'message' => 'internal error',
        'data' => null,
        'meta' => $isPrivateRoute ? profilePrivateMeta() : profilePublicMeta(),
    ], 500);
} catch (\Throwable $e) {
    $segments = profileRelativeSegments();
    $isPrivateRoute = (count($segments) >= 1 && $segments[0] === 'private');
    profileRespond([
        'ok' => false,
        'error' => $isPrivateRoute ? 'profile_private_unavailable' : 'profile_public_unavailable',
        'message' => 'internal error',
        'data' => null,
        'meta' => $isPrivateRoute ? profilePrivateMeta() : profilePublicMeta(),
    ], 500);
}
