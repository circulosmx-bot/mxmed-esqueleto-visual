<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/profiles/repositories/PublicProfileRepository.php';
require_once __DIR__ . '/../../modules/profiles/controllers/PublicProfileController.php';

use Profiles\Repositories\PublicProfileRepository;
use Profiles\Controllers\PublicProfileController;

header('Content-Type: application/json; charset=UTF-8');

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

try {
    $method = strtoupper(trim((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $segments = profileRelativeSegments();

    if ($method !== 'GET') {
        profileRespond([
            'ok' => false,
            'error' => 'not_found',
            'message' => 'route not found',
            'data' => null,
            'meta' => [
                'contract' => 'profile_public_mvp',
                'version' => 'PP-4B',
                'generated_at' => gmdate('c'),
            ],
        ], 404);
        return;
    }

    if (count($segments) === 3 && $segments[0] === 'public' && $segments[1] === 'doctor') {
        $doctorId = trim((string)$segments[2]);
        if (profileInvalidDoctorId($doctorId)) {
            profileRespond([
                'ok' => false,
                'error' => 'invalid_doctor_id',
                'message' => 'doctor_id invalid',
                'data' => null,
                'meta' => [
                    'contract' => 'profile_public_mvp',
                    'version' => 'PP-4B',
                    'generated_at' => gmdate('c'),
                ],
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

    profileRespond([
        'ok' => false,
        'error' => 'not_found',
        'message' => 'route not found',
        'data' => null,
        'meta' => [
            'contract' => 'profile_public_mvp',
            'version' => 'PP-4B',
            'generated_at' => gmdate('c'),
        ],
    ], 404);
} catch (\RuntimeException $e) {
    profileRespond([
        'ok' => false,
        'error' => 'profile_public_unavailable',
        'message' => 'internal error',
        'data' => null,
        'meta' => [
            'contract' => 'profile_public_mvp',
            'version' => 'PP-4B',
            'generated_at' => gmdate('c'),
        ],
    ], 500);
} catch (\Throwable $e) {
    profileRespond([
        'ok' => false,
        'error' => 'profile_public_unavailable',
        'message' => 'internal error',
        'data' => null,
        'meta' => [
            'contract' => 'profile_public_mvp',
            'version' => 'PP-4B',
            'generated_at' => gmdate('c'),
        ],
    ], 500);
}
