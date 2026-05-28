<?php
declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function toText($value): ?string
{
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }
    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

function toBool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return $value !== 0;
    }
    if (is_string($value)) {
        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
    return false;
}

function safeArray($value): array
{
    return is_array($value) ? $value : [];
}

function parseHttpStatusCode(array $headers): ?int
{
    foreach ($headers as $line) {
        if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})\b/', (string)$line, $m) === 1) {
            return (int)$m[1];
        }
    }
    return null;
}

function currentOrigin(): string
{
    $https = $_SERVER['HTTPS'] ?? '';
    $isHttps = (!empty($https) && strtolower((string)$https) !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1'));
    if ($host === '') {
        $host = '127.0.0.1';
    }
    return $scheme . '://' . $host;
}

function resolveProfilesApiBase(): string
{
    $candidates = [
        trim((string)(getenv('MXMED_PROFILES_API_BASE') ?: '')),
        trim((string)(getenv('MXMED_API_BASE') ?: '')),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        return rtrim($candidate, '/');
    }

    return rtrim(currentOrigin(), '/');
}

function readLastHttpHeaders(): array
{
    if (function_exists('http_get_last_response_headers')) {
        $headers = http_get_last_response_headers();
        return is_array($headers) ? $headers : [];
    }

    return [];
}

function shouldUseLocalProfileFallback(string $apiBase): bool
{
    if (PHP_SAPI !== 'cli-server') {
        return false;
    }

    $originHost = (string)parse_url(currentOrigin(), PHP_URL_HOST);
    $apiHost = (string)parse_url($apiBase, PHP_URL_HOST);
    $originPort = (string)parse_url(currentOrigin(), PHP_URL_PORT);
    $apiPort = (string)parse_url($apiBase, PHP_URL_PORT);

    if ($originHost === '' || $apiHost === '' || $originHost !== $apiHost) {
        return false;
    }

    if ($originPort !== $apiPort) {
        return false;
    }

    $workers = (int)(getenv('PHP_CLI_SERVER_WORKERS') ?: '1');
    return $workers <= 1;
}

function fetchProfileViaControllerFallback(string $doctorId): ?array
{
    try {
        require_once __DIR__ . '/../api/_lib/db.php';
        require_once __DIR__ . '/../modules/profiles/repositories/PublicProfileRepository.php';
        require_once __DIR__ . '/../modules/profiles/controllers/PublicProfileController.php';

        $repo = new \Profiles\Repositories\PublicProfileRepository(mxmed_pdo());
        $controller = new \Profiles\Controllers\PublicProfileController($repo);
        $response = $controller->showByDoctorId($doctorId);
        if (!is_array($response)) {
            return null;
        }

        $ok = toBool($response['ok'] ?? false);
        $error = toText($response['error'] ?? null);
        if ($ok) {
            $status = 200;
        } elseif ($error === 'invalid_doctor_id') {
            $status = 400;
        } elseif ($error === 'profile_not_found') {
            $status = 404;
        } else {
            $status = 500;
        }

        return [
            'status' => $status,
            'payload' => $response,
        ];
    } catch (\Throwable $e) {
        return null;
    }
}

function buildScheduleRows(array $schedule, array $consultorioNames): array
{
    $byDay = safeArray($schedule['by_day'] ?? []);
    $weekdayLabels = [
        '1' => 'Lunes',
        '2' => 'Martes',
        '3' => 'Miercoles',
        '4' => 'Jueves',
        '5' => 'Viernes',
        '6' => 'Sabado',
        '7' => 'Domingo',
    ];

    $rows = [];
    foreach ($weekdayLabels as $weekday => $label) {
        $items = safeArray($byDay[$weekday] ?? []);
        if (empty($items)) {
            continue;
        }
        $windows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $start = toText($item['start_time'] ?? null);
            $end = toText($item['end_time'] ?? null);
            $consultorioId = toText($item['consultorio_id'] ?? null);
            if ($start === null || $end === null) {
                continue;
            }
            $title = null;
            if ($consultorioId !== null && isset($consultorioNames[$consultorioId])) {
                $title = $consultorioNames[$consultorioId];
            }
            $windows[] = [
                'start' => $start,
                'end' => $end,
                'consultorio' => $title,
            ];
        }
        if (!empty($windows)) {
            $rows[] = [
                'label' => $label,
                'windows' => $windows,
            ];
        }
    }

    return $rows;
}

$doctorId = trim((string)($_GET['doctor_id'] ?? ''));
$inputError = null;
if ($doctorId === '') {
    $inputError = 'Falta el parámetro doctor_id para mostrar el perfil público.';
} elseif (!preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $doctorId)) {
    $inputError = 'El identificador del médico no es válido.';
}

$endpointStatus = null;
$endpointError = null;
$dto = null;

if ($inputError === null) {
    $apiBase = resolveProfilesApiBase();
    $endpointUrl = $apiBase . '/api/profiles/public/doctor/' . rawurlencode($doctorId);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 6,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($endpointUrl, false, $context);
    $headers = readLastHttpHeaders();
    $endpointStatus = parseHttpStatusCode($headers);

    // QA/local-only fallback for php -S with a single worker:
    // avoids deadlock from HTTP self-calls to the same process.
    // It is gated by PHP_SAPI === 'cli-server' and same host/port,
    // so it should not activate in production.
    // Reuses PublicProfileController/PublicProfileRepository to keep
    // the same sanitized public DTO contract.
    if ($raw === false && shouldUseLocalProfileFallback($apiBase)) {
        $fallback = fetchProfileViaControllerFallback($doctorId);
        if (is_array($fallback)) {
            $endpointStatus = (int)($fallback['status'] ?? 500);
            $raw = json_encode($fallback['payload'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    if ($raw === false) {
        $endpointError = 'No fue posible cargar el perfil en este momento.';
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $endpointError = 'No fue posible interpretar la respuesta del perfil.';
        } else {
            $ok = toBool($decoded['ok'] ?? false);
            if ($ok && is_array($decoded['data'] ?? null)) {
                $dto = $decoded;
            } else {
                $apiError = toText($decoded['error'] ?? null) ?? 'profile_public_unavailable';
                if ($apiError === 'profile_not_found') {
                    $endpointError = 'Perfil médico no encontrado.';
                    $endpointStatus = 404;
                } elseif ($apiError === 'invalid_doctor_id') {
                    $endpointError = 'El identificador del médico no es válido.';
                    $endpointStatus = 400;
                } else {
                    $endpointError = 'Perfil no disponible temporalmente.';
                    if ($endpointStatus === null || $endpointStatus < 400) {
                        $endpointStatus = 500;
                    }
                }
            }
        }
    }
}

if ($inputError !== null) {
    http_response_code(400);
} elseif ($dto === null) {
    http_response_code($endpointStatus !== null ? $endpointStatus : 500);
} else {
    http_response_code(200);
}

$data = $dto !== null ? safeArray($dto['data'] ?? []) : [];
$profile = safeArray($data['profile'] ?? []);
$identity = safeArray($data['identity'] ?? []);
$professional = safeArray($data['professional'] ?? []);
$specialties = safeArray($data['specialties'] ?? []);
$consultorios = safeArray($data['consultorios'] ?? []);
$schedule = safeArray($data['schedule'] ?? []);
$contact = safeArray($data['contact'] ?? []);
$agendaPublic = safeArray($data['agenda_public'] ?? []);
$publicVisibility = safeArray($data['public_visibility'] ?? []);
$commercialVisibility = safeArray($data['commercial_visibility'] ?? []);
$reviews = safeArray($data['reviews'] ?? []);
$claim = safeArray($data['claim'] ?? []);
$seo = safeArray($data['seo'] ?? []);
$jsonLd = $data['json_ld'] ?? null;
$ecosystemLinks = safeArray($data['ecosystem_links'] ?? []);
$featureFlags = safeArray($data['feature_flags'] ?? []);
$plan = safeArray($data['plan'] ?? []);

$displayName = toText($identity['display_name'] ?? null);
$profileStatus = toText($profile['status'] ?? null) ?? 'hidden';
$isPublic = toBool($profile['is_public'] ?? false);
$hasPublicProfile = toBool($featureFlags['has_public_profile'] ?? false);
$showLimitedNotice = (!$isPublic || !$hasPublicProfile);

$pageTitle = toText($seo['title'] ?? null) ?? 'Perfil Médico | México Médico';
$pageDescription = toText($seo['description'] ?? null) ?? 'Ficha pública informativa del perfil médico en México Médico.';
$pageRobots = toText($seo['robots'] ?? null) ?? 'noindex,nofollow';
$canonicalUrl = toText($seo['canonical_url'] ?? null);

$primaryConsultorio = isset($consultorios[0]) && is_array($consultorios[0]) ? $consultorios[0] : [];
$primaryName = toText($primaryConsultorio['public_name'] ?? null) ?? 'Consultorio principal';
$primaryAddress = toText($primaryConsultorio['address'] ?? null);
$primaryMapUrl = toText($primaryConsultorio['map_embed_url'] ?? null);

$primarySpecialty = null;
if (!empty($specialties) && is_array($specialties[0])) {
    $primarySpecialty = toText($specialties[0]['name_es'] ?? null);
}

$professionalLicense = toText($professional['professional_license'] ?? null);
$specialtyLicense = toText($professional['specialty_license'] ?? null);
$bioShort = toText($professional['bio_short'] ?? null);

$reviewCount = (int)($reviews['review_count'] ?? 0);
$ratingAvg = $reviews['rating_avg'] ?? null;
$reviewsVisible = toBool($reviews['visible'] ?? false);

$showContactButtons = toBool($publicVisibility['show_contact_buttons'] ?? false);
$showPhone = ($showContactButtons && toBool($publicVisibility['show_phone'] ?? false));
$showWhatsapp = ($showContactButtons && toBool($publicVisibility['show_whatsapp'] ?? false));
$showPublicAgenda = toBool($publicVisibility['show_public_agenda'] ?? false);
$showFee = toBool($publicVisibility['show_consultation_fee'] ?? false);
$showInsurances = toBool($publicVisibility['show_accepted_insurances'] ?? false);

$contactPhone = $showPhone ? toText($contact['phone'] ?? null) : null;
$contactWhatsapp = $showWhatsapp ? toText($contact['whatsapp'] ?? null) : null;

$consultationFee = $showFee ? ($commercialVisibility['consultation_fee'] ?? null) : null;
$paymentMethods = $showFee ? safeArray($commercialVisibility['payment_methods'] ?? []) : [];
$acceptedInsurances = $showInsurances ? safeArray($commercialVisibility['accepted_insurances'] ?? []) : [];

$consultorioNames = [];
foreach ($consultorios as $row) {
    if (!is_array($row)) {
        continue;
    }
    $id = toText($row['consultorio_id'] ?? null);
    $name = toText($row['public_name'] ?? null);
    if ($id !== null && $name !== null) {
        $consultorioNames[$id] = $name;
    }
}
$scheduleRows = buildScheduleRows($schedule, $consultorioNames);

$renderJsonLd = is_array($jsonLd) && !empty($jsonLd);
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h($pageTitle) ?></title>
  <meta name="description" content="<?= h($pageDescription) ?>" />
  <meta name="robots" content="<?= h($pageRobots) ?>" />
  <?php if ($canonicalUrl !== null): ?>
    <link rel="canonical" href="<?= h($canonicalUrl) ?>" />
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/public-profile.css" />
  <?php if ($renderJsonLd): ?>
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>
</head>
<body>
  <header class="mxpp-header">
    <div class="mxpp-wrap mxpp-header__inner">
      <div class="mxpp-brand">México Médico</div>
      <form class="mxpp-search" action="#" method="get" onsubmit="return false;">
        <input type="search" placeholder="Buscar médico o especialidad (próximamente)" aria-label="Buscar" disabled />
      </form>
    </div>
  </header>

  <main class="mxpp-wrap mxpp-main">
    <?php if ($inputError !== null || $dto === null): ?>
      <section class="mxpp-alert mxpp-alert--error">
        <h1>Perfil no disponible</h1>
        <p>
          <?= h($inputError ?? $endpointError ?? 'No fue posible cargar el perfil en este momento.') ?>
        </p>
      </section>
    <?php else: ?>
      <?php if ($showLimitedNotice): ?>
        <section class="mxpp-alert mxpp-alert--info">
          <strong>Información pública limitada.</strong>
          <span>Este perfil está en validación y algunos datos pueden no estar disponibles.</span>
        </section>
      <?php endif; ?>

      <section class="mxpp-hero">
        <div class="mxpp-col mxpp-col--left">
          <div class="mxpp-card mxpp-avatar-card">
            <?php if (toText($identity['photo_url'] ?? null) !== null): ?>
              <img src="<?= h((string)$identity['photo_url']) ?>" alt="Foto del médico" class="mxpp-avatar" />
            <?php else: ?>
              <div class="mxpp-avatar mxpp-avatar--placeholder" aria-hidden="true">👨‍⚕️</div>
            <?php endif; ?>

            <h3><?= h($primaryName) ?></h3>
            <?php if ($primaryAddress !== null): ?>
              <p class="mxpp-muted"><?= h($primaryAddress) ?></p>
            <?php endif; ?>

            <?php if ($primaryMapUrl !== null): ?>
              <div class="mxpp-map">
                <iframe src="<?= h($primaryMapUrl) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Ubicación del consultorio"></iframe>
              </div>
            <?php endif; ?>

            <a class="mxpp-link" href="#" aria-disabled="true">Sugerir corrección</a>
            <a class="mxpp-link mxpp-link--strong" href="#" aria-disabled="true">Yo soy este médico y quiero administrar mi perfil</a>
          </div>
        </div>

        <div class="mxpp-col mxpp-col--right">
          <div class="mxpp-card">
            <div class="mxpp-heading-row">
              <h1>
                <?= h($displayName ?? 'Perfil médico en validación') ?>
              </h1>
              <?php if ($isPublic): ?>
                <span class="mxpp-badge">Verificado</span>
              <?php endif; ?>
            </div>

            <?php if ($reviewsVisible): ?>
              <p class="mxpp-muted">Opiniones: <?= h((string)$reviewCount) ?><?= $ratingAvg !== null ? ' · Calificación ' . h((string)$ratingAvg) : '' ?></p>
            <?php else: ?>
              <p class="mxpp-muted">Opiniones públicas no disponibles por ahora.</p>
            <?php endif; ?>

            <?php if ($primarySpecialty !== null): ?>
              <p><strong>Especialidad:</strong> <?= h($primarySpecialty) ?></p>
            <?php endif; ?>
            <?php if ($professionalLicense !== null): ?>
              <p><strong>Cédula profesional:</strong> <?= h($professionalLicense) ?></p>
            <?php endif; ?>
            <?php if ($specialtyLicense !== null): ?>
              <p><strong>Cédula de especialidad:</strong> <?= h($specialtyLicense) ?></p>
            <?php endif; ?>
            <?php if ($bioShort !== null): ?>
              <p><?= h($bioShort) ?></p>
            <?php endif; ?>
            <?php if (toText($plan['plan_label'] ?? null) !== null): ?>
              <p class="mxpp-muted">Plan informativo: <?= h((string)$plan['plan_label']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="mxpp-grid">
        <article class="mxpp-card">
          <h2>Consultorios</h2>
          <?php if (!empty($consultorios)): ?>
            <ul class="mxpp-list">
              <?php foreach ($consultorios as $row): ?>
                <?php if (!is_array($row)): continue; endif; ?>
                <li>
                  <strong><?= h(toText($row['public_name'] ?? null) ?? 'Consultorio') ?></strong>
                  <?php if (toText($row['address'] ?? null) !== null): ?>
                    <div class="mxpp-muted"><?= h((string)$row['address']) ?></div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="mxpp-muted">No hay consultorios públicos disponibles.</p>
          <?php endif; ?>
        </article>

        <article class="mxpp-card">
          <h2>Horarios</h2>
          <?php if (!empty($scheduleRows)): ?>
            <ul class="mxpp-list">
              <?php foreach ($scheduleRows as $day): ?>
                <li>
                  <strong><?= h((string)$day['label']) ?></strong>
                  <ul class="mxpp-sublist">
                    <?php foreach ($day['windows'] as $window): ?>
                      <li>
                        <?= h((string)$window['start']) ?> - <?= h((string)$window['end']) ?>
                        <?php if (!empty($window['consultorio'])): ?>
                          <span class="mxpp-muted"> · <?= h((string)$window['consultorio']) ?></span>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="mxpp-muted">Horarios no disponibles públicamente.</p>
          <?php endif; ?>
        </article>

        <?php if ($showContactButtons): ?>
          <article class="mxpp-card">
            <h2>Contacto</h2>
            <?php if ($showPhone && $contactPhone !== null): ?>
              <p><strong>Teléfono:</strong> <?= h($contactPhone) ?></p>
            <?php endif; ?>
            <?php if ($showWhatsapp && $contactWhatsapp !== null): ?>
              <p><strong>WhatsApp:</strong> <?= h($contactWhatsapp) ?></p>
            <?php endif; ?>
            <?php if ((!$showPhone || $contactPhone === null) && (!$showWhatsapp || $contactWhatsapp === null)): ?>
              <p class="mxpp-muted">Contacto público no disponible por ahora.</p>
            <?php endif; ?>
          </article>
        <?php endif; ?>

        <?php if ($showPublicAgenda): ?>
          <article class="mxpp-card">
            <h2>Agenda una cita</h2>
            <p class="mxpp-muted">Horarios disponibles para reserva pública.</p>
            <?php if (toText($agendaPublic['availability_endpoint'] ?? null) !== null): ?>
              <p class="mxpp-muted">Fuente: <?= h((string)$agendaPublic['availability_endpoint']) ?></p>
            <?php endif; ?>
          </article>
        <?php endif; ?>

        <?php if ($showFee): ?>
          <article class="mxpp-card">
            <h2>Costo y medios de pago</h2>
            <?php if ($consultationFee !== null): ?>
              <p><strong>Costo de consulta:</strong> <?= h(is_scalar($consultationFee) ? (string)$consultationFee : json_encode($consultationFee, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></p>
            <?php else: ?>
              <p class="mxpp-muted">Costo de consulta no disponible.</p>
            <?php endif; ?>
            <?php if (!empty($paymentMethods)): ?>
              <ul class="mxpp-list">
                <?php foreach ($paymentMethods as $method): ?>
                  <li><?= h(is_scalar($method) ? (string)$method : json_encode($method, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        <?php endif; ?>

        <?php if ($showInsurances): ?>
          <article class="mxpp-card">
            <h2>Aseguradoras aceptadas</h2>
            <?php if (!empty($acceptedInsurances)): ?>
              <ul class="mxpp-list">
                <?php foreach ($acceptedInsurances as $insurer): ?>
                  <?php if (!is_array($insurer)): continue; endif; ?>
                  <li><?= h(toText($insurer['name'] ?? null) ?? 'Aseguradora') ?></li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="mxpp-muted">Aseguradoras no disponibles por ahora.</p>
            <?php endif; ?>
          </article>
        <?php endif; ?>

        <?php if ($reviewsVisible): ?>
          <article class="mxpp-card">
            <h2>Opiniones</h2>
            <p class="mxpp-muted">Calificación promedio: <?= h((string)($ratingAvg ?? 'N/D')) ?> · <?= h((string)$reviewCount) ?> reseñas</p>
          </article>
        <?php endif; ?>
      </section>

      <?php if (!empty($ecosystemLinks)): ?>
        <section class="mxpp-card mxpp-card--subtle">
          <h2>Red profesional</h2>
          <p class="mxpp-muted">Información de ecosistema disponible de forma limitada.</p>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <footer class="mxpp-footer">
    <div class="mxpp-wrap">
      <p>© <?= h((string)date('Y')) ?> México Médico · Perfil público transicional</p>
    </div>
  </footer>
</body>
</html>
