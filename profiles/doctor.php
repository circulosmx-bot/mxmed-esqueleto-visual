<?php
declare(strict_types=1);

require_once __DIR__ . '/../modules/profiles/services/PublicProfilePlanCapabilities.php';
require_once __DIR__ . '/../modules/profiles/services/ProfileThemeCatalog.php';

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

function scheduleSummaryText($value): ?string
{
    if (is_string($value) || is_numeric($value)) {
        return toText($value);
    }

    if (!is_array($value)) {
        return null;
    }

    $parts = [];
    foreach ($value as $item) {
        $text = toText($item);
        if ($text !== null) {
            $parts[] = $text;
        }
    }

    return $parts !== [] ? implode(' · ', $parts) : null;
}

function platformNavigationSectionsFromTaxonomy(array $taxonomy): array
{
    $sections = safeArray($taxonomy['sections'] ?? []);
    if ($sections === []) {
        return [];
    }

    $sections = array_values(array_filter($sections, static fn($section): bool => is_array($section)));
    usort($sections, static function (array $a, array $b): int {
        return ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
    });

    $out = [];
    foreach ($sections as $section) {
        if (!toBool($section['enabled'] ?? false)) {
            continue;
        }

        $sectionKey = toText($section['key'] ?? null);
        $sectionLabel = toText($section['label'] ?? null);
        if ($sectionKey === null || $sectionLabel === null) {
            continue;
        }

        $items = safeArray($section['items'] ?? []);
        $items = array_values(array_filter($items, static fn($item): bool => is_array($item)));
        usort($items, static function (array $a, array $b): int {
            return ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
        });

        $navItems = [];
        foreach ($items as $item) {
            if (!toBool($item['enabled'] ?? false)) {
                continue;
            }

            $label = toText($item['label'] ?? null);
            if ($label === null) {
                continue;
            }

            $navItems[] = [
                'label' => $label,
                'slug' => toText($item['slug'] ?? null),
                'profile_type' => toText($item['profile_type'] ?? null),
                'route_enabled' => toBool($item['route_enabled'] ?? false),
                'url' => toText($item['url'] ?? null),
            ];
        }

        $out[] = [
            'id' => $sectionKey,
            'label' => $sectionLabel,
            'items' => $navItems,
        ];
    }

    return $out;
}

function fallbackPlatformNavigationSections(): array
{
    return [
        [
            'id' => 'medical_specialists',
            'label' => 'Especialistas Médicos',
            'items' => [['label' => 'Cardiología']],
        ],
        [
            'id' => 'dental_specialists',
            'label' => 'Especialistas Dentales',
            'items' => [['label' => 'Odontología general']],
        ],
        [
            'id' => 'other_services',
            'label' => 'Otros servicios',
            'items' => [['label' => 'Psicología']],
        ],
        [
            'id' => 'hospitals',
            'label' => 'Hospitales',
            'items' => [['label' => 'Hospitales generales']],
        ],
        [
            'id' => 'clinics',
            'label' => 'Clínicas',
            'items' => [['label' => 'Clínicas generales']],
        ],
        [
            'id' => 'laboratories',
            'label' => 'Laboratorios',
            'items' => [['label' => 'Laboratorios clínicos']],
        ],
    ];
}

function telHref(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }
    $startsWithPlus = str_starts_with($trimmed, '+');
    $digits = preg_replace('/\D/', '', $trimmed);
    if (!is_string($digits) || strlen($digits) < 7 || strlen($digits) > 16) {
        return null;
    }
    return 'tel:' . ($startsWithPlus ? '+' : '') . $digits;
}

function whatsappHref(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $digits = preg_replace('/\D/', '', $value);
    if (!is_string($digits) || strlen($digits) < 7 || strlen($digits) > 16) {
        return null;
    }
    return 'https://wa.me/' . $digits;
}

function isLocalDevRequest(): bool
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host === '') {
        return false;
    }
    $host = strtolower((string)preg_replace('/:\d+$/', '', $host));
    $host = trim($host, '[]');
    return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
}

function resolveDevPlanOverride(): ?string
{
    if (!isLocalDevRequest()) {
        return null;
    }

    $raw = toText($_GET['mxmed_plan'] ?? null);
    if ($raw === null) {
        return null;
    }

    $normalized = \Profiles\Services\PublicProfilePlanCapabilities::normalizePlanCode($raw);
    $allowed = ['free', 'basic', 'standard', 'optimum', 'professional'];
    return in_array($normalized, $allowed, true) ? $normalized : null;
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

function fetchProfileViaControllerFallback(string $doctorId, ?string $planOverride = null): ?array
{
    try {
        require_once __DIR__ . '/../api/_lib/db.php';
        require_once __DIR__ . '/../modules/profiles/repositories/PublicProfileRepository.php';
        require_once __DIR__ . '/../modules/profiles/controllers/PublicProfileController.php';

        $repo = new \Profiles\Repositories\PublicProfileRepository(mxmed_pdo());
        $controller = new \Profiles\Controllers\PublicProfileController($repo);
        $response = $controller->showByDoctorId($doctorId, $planOverride);
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
$devPlanOverride = resolveDevPlanOverride();

if ($inputError === null) {
    $apiBase = resolveProfilesApiBase();
    $endpointUrl = $apiBase . '/api/profiles/public/doctor/' . rawurlencode($doctorId);
    $raw = false;

    if ($devPlanOverride !== null) {
        $fallback = fetchProfileViaControllerFallback($doctorId, $devPlanOverride);
        if (is_array($fallback)) {
            $endpointStatus = (int)($fallback['status'] ?? 500);
            $raw = json_encode($fallback['payload'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    if ($raw === false) {
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
    }

    // Fallback solo para QA local con php -S de un solo worker.
    // Evita deadlock por autollamada HTTP al mismo proceso.
    // En producción no debe activarse porque depende de PHP_SAPI === 'cli-server'.
    // Reutiliza PublicProfileController/PublicProfileRepository para mantener
    // el mismo DTO público sanitizado del endpoint.
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
$contact = safeArray($data['contact'] ?? []);
$agendaPublic = safeArray($data['agenda_public'] ?? []);
$publicVisibility = safeArray($data['public_visibility'] ?? []);
$commercialVisibility = safeArray($data['commercial_visibility'] ?? []);
$reviews = safeArray($data['reviews'] ?? []);
$claim = safeArray($data['claim'] ?? []);
$seo = safeArray($data['seo'] ?? []);
$jsonLd = $data['json_ld'] ?? null;
$featureFlags = safeArray($data['feature_flags'] ?? []);
$plan = safeArray($data['plan'] ?? []);
$geoContext = safeArray($data['geo_context'] ?? []);
$publicNavigationTaxonomy = safeArray($data['public_navigation_taxonomy'] ?? []);
$publicBreadcrumbs = safeArray($data['public_breadcrumbs'] ?? []);
$profileTheme = safeArray($data['profile_theme'] ?? []);

$themeKey = \Profiles\Services\ProfileThemeCatalog::DEFAULT_KEY;
$themePreviewKey = null;
$themePublicRolloutEnabled = toBool($profileTheme['public_rollout_enabled'] ?? false);
if (isLocalDevRequest() || $themePublicRolloutEnabled) {
    $themeKey = \Profiles\Services\ProfileThemeCatalog::normalize(toText($profileTheme['effective_key'] ?? null))
        ?? \Profiles\Services\ProfileThemeCatalog::DEFAULT_KEY;
}
if (isLocalDevRequest()) {
    $themePreviewKey = \Profiles\Services\ProfileThemeCatalog::normalize(toText($_GET['mxmed_theme_preview'] ?? null));
    if ($themePreviewKey !== null) {
        $themeKey = $themePreviewKey;
    }
}
$resolvedProfileTheme = \Profiles\Services\ProfileThemeCatalog::resolve($themeKey);
$profileThemeStyle = \Profiles\Services\ProfileThemeCatalog::cssVariables($resolvedProfileTheme);

$displayName = toText($identity['display_name'] ?? null);
$profileStatus = toText($profile['status'] ?? null) ?? 'hidden';
$isPublic = toBool($profile['is_public'] ?? false);
$hasPublicProfile = toBool($featureFlags['has_public_profile'] ?? false);
$showLimitedNotice = (!$isPublic || !$hasPublicProfile);

$pageTitle = toText($seo['title'] ?? null) ?? 'Perfil Médico | México Médico';
$pageDescription = toText($seo['description'] ?? null) ?? 'Ficha pública informativa del perfil médico en México Médico.';
$pageRobots = toText($seo['robots'] ?? null) ?? 'noindex,nofollow';
$canonicalUrl = toText($seo['canonical_url'] ?? null);
$geoStateName = toText($geoContext['state_name'] ?? null);
$geoStateSlug = toText($geoContext['state_slug'] ?? null);
$geoCityName = toText($geoContext['city_name'] ?? null);
$geoCitySlug = toText($geoContext['city_slug'] ?? null);
$geoSource = toText($geoContext['source'] ?? null);
$geoIsNational = toBool($geoContext['is_national'] ?? false);
$platformRegionLabel = $geoStateName;
$platformNavItems = platformNavigationSectionsFromTaxonomy($publicNavigationTaxonomy);
if ($platformNavItems === []) {
    $platformNavItems = fallbackPlatformNavigationSections();
}

$breadcrumbItems = [];
foreach (safeArray($publicBreadcrumbs['items'] ?? []) as $breadcrumbItem) {
    if (!is_array($breadcrumbItem)) {
        continue;
    }

    $breadcrumbLabel = toText($breadcrumbItem['label'] ?? null);
    if ($breadcrumbLabel === null) {
        continue;
    }

    $breadcrumbItems[] = [
        'label' => $breadcrumbLabel,
        'is_current' => toBool($breadcrumbItem['is_current'] ?? false),
    ];
}

$hasCurrentBreadcrumb = false;
foreach ($breadcrumbItems as $breadcrumbItem) {
    if (toBool($breadcrumbItem['is_current'] ?? false)) {
        $hasCurrentBreadcrumb = true;
        break;
    }
}

if (!$hasCurrentBreadcrumb && $breadcrumbItems !== []) {
    $lastBreadcrumbIndex = count($breadcrumbItems) - 1;
    $breadcrumbItems[$lastBreadcrumbIndex]['is_current'] = true;
}

$showPublicBreadcrumb = (toBool($publicBreadcrumbs['render_enabled'] ?? false) && count($breadcrumbItems) >= 2);

$consultorioPanels = [];
foreach ($consultorios as $index => $consultorio) {
    if (!is_array($consultorio)) {
        continue;
    }

    $consultorioPanels[] = [
        'id' => toText($consultorio['consultorio_id'] ?? null) ?? (string)($index + 1),
        'name' => toText($consultorio['public_name'] ?? null) ?? 'Consultorio principal',
        'brand_name' => toText($consultorio['brand_name'] ?? null),
        'brand_logo_url' => toText($consultorio['brand_logo_url'] ?? null),
        'address' => toText($consultorio['address'] ?? null),
        'phone_public' => toText($consultorio['phone_public'] ?? null),
        'whatsapp_public' => toText($consultorio['whatsapp_public'] ?? null),
        'map_url' => toText($consultorio['map_embed_url'] ?? null),
        'map_can_open_gps' => toBool($consultorio['map_can_open_gps'] ?? false),
        'schedule_summary' => scheduleSummaryText($consultorio['schedule_summary'] ?? null),
    ];
}

if ($consultorioPanels === []) {
    $consultorioPanels[] = [
        'id' => 'principal',
        'name' => 'Consultorio principal',
        'brand_name' => null,
        'brand_logo_url' => null,
        'address' => null,
        'phone_public' => null,
        'whatsapp_public' => null,
        'map_url' => null,
        'map_can_open_gps' => false,
        'schedule_summary' => null,
    ];
}

$primaryConsultorio = $consultorioPanels[0];
$primaryName = $primaryConsultorio['name'];
$primaryAddress = $primaryConsultorio['address'];
$primaryMapUrl = $primaryConsultorio['map_url'];
$scheduleSummary = $primaryConsultorio['schedule_summary'];
$showConsultorioSwitcher = count($consultorioPanels) > 1;
$primaryBrandLogoUrl = toText($primaryConsultorio['brand_logo_url'] ?? null);
$primaryBrandName = toText($primaryConsultorio['brand_name'] ?? null) ?? $primaryName;

$photoUrl = toText($identity['photo_url'] ?? null);
$physicianLogoUrl = toBool($publicVisibility['show_logo'] ?? false)
    ? toText($identity['logo_url'] ?? null)
    : null;
$physicianLogoAlt = $displayName !== null
    ? 'Logotipo de ' . $displayName
    : 'Logotipo del médico';
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
$showInternalInbox = ($showContactButtons && (
    toBool($publicVisibility['show_internal_message'] ?? false)
    || toBool($publicVisibility['show_internal_inbox'] ?? false)
    || toBool($contact['internal_message_enabled'] ?? false)
));
$showPublicAgenda = (
    toBool($publicVisibility['show_public_agenda'] ?? false)
    || toBool($agendaPublic['enabled'] ?? false)
    || toBool($featureFlags['has_public_agenda'] ?? false)
);
$showClickableMap = (
    toBool($publicVisibility['show_clickable_map'] ?? false)
    || toBool($publicVisibility['show_map_gps'] ?? false)
    || toBool($publicVisibility['show_gps_directions'] ?? false)
    || toBool($primaryConsultorio['map_can_open_gps'] ?? false)
);
$showClaimProfile = (
    toBool($publicVisibility['show_claim_button'] ?? false)
    || toBool($claim['show_claim_button'] ?? false)
    || toBool($claim['claim_allowed'] ?? false)
);
$showSuggestCorrection = toBool($publicVisibility['show_suggest_correction'] ?? false);
$showAboutAction = toBool($publicVisibility['show_about_action'] ?? false);
$showConsultaAction = toBool($publicVisibility['show_consulta_action'] ?? false);
$showFee = toBool($publicVisibility['show_consultation_fee'] ?? false);
$showInsurances = toBool($publicVisibility['show_accepted_insurances'] ?? false);

$contactPhone = $showPhone ? toText($contact['phone'] ?? null) : null;
$contactWhatsapp = $showWhatsapp ? toText($contact['whatsapp'] ?? null) : null;
$contactEmail = (
    $showContactButtons
    && toBool($contact['has_public_email'] ?? false)
) ? toText($contact['email'] ?? null) : null;
$contactPhoneHref = telHref($contactPhone);
$contactWhatsappHref = whatsappHref($contactWhatsapp);
$contactEmailHref = $contactEmail !== null && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)
    ? 'mailto:' . $contactEmail
    : null;
$canRenderContactActions = (
    $contactPhoneHref !== null
    || $contactWhatsappHref !== null
    || $contactEmailHref !== null
);
$canRenderContactSection = ($showContactButtons && (
    $contactPhone !== null
    || $contactWhatsapp !== null
    || $contactEmail !== null
    || $showInternalInbox
));

$consultationFee = $showFee ? ($commercialVisibility['consultation_fee'] ?? null) : null;
$paymentMethods = $showFee ? safeArray($commercialVisibility['payment_methods'] ?? []) : [];
$acceptedInsurances = $showInsurances ? safeArray($commercialVisibility['accepted_insurances'] ?? []) : [];

$renderJsonLd = is_array($jsonLd) && !empty($jsonLd);
$planLabel = toText($plan['plan_label'] ?? null);
$agendaEndpoint = toText($agendaPublic['availability_endpoint'] ?? null);
$bookAppointmentUrl = '/public-book.html?doctor_id=' . rawurlencode($doctorId);
$effectivePlanCode = \Profiles\Services\PublicProfilePlanCapabilities::normalizePlanCode($plan['plan_code'] ?? ($plan['code'] ?? null));
$showPaidProfileCheck = (
    $isPublic
    && toBool($plan['is_paid'] ?? false)
    && toBool($plan['is_active'] ?? false)
);
$paidProfileNameLead = null;
$paidProfileNameTail = $displayName;
if ($showPaidProfileCheck && $displayName !== null) {
    $displayNameParts = preg_split('/\s+/u', $displayName) ?: [];
    $paidProfileNameTail = array_pop($displayNameParts) ?: $displayName;
    $paidProfileNameLead = $displayNameParts !== [] ? implode(' ', $displayNameParts) : null;
}
$showAgendaSlot = ($showPublicAgenda && $agendaEndpoint !== null);
$consultaTarget = $showAgendaSlot ? '#proximas-citas' : '#consultorios';
$institutionalImageUrl = null;
$showInstitutionalImageSlot = (
    !$showAgendaSlot
    && $effectivePlanCode === 'basic'
    && $institutionalImageUrl !== null
);
$showDevPlanSwitcher = ($dto !== null && isLocalDevRequest());
$agendaMockMode = null;
if (isLocalDevRequest()) {
    $rawAgendaMockMode = toText($_GET['mxmed_agenda_mock'] ?? null);
    if ($rawAgendaMockMode === 'mixed') {
        $agendaMockMode = 'mixed';
    }
}
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
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Asap:wght@400;500;600;700;900&family=Baloo+2:wght@400;500&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&amp;icon_names=event,person_text" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/public-profile.css" />
  <?php if ($renderJsonLd): ?>
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>
</head>
<body data-profile-theme="<?= h($resolvedProfileTheme['key']) ?>" data-profile-theme-preview="<?= $themePreviewKey !== null ? 'true' : 'false' ?>" style="<?= h($profileThemeStyle) ?>">
  <header class="mxpp-platform-header" aria-label="México Médico">
    <div class="mxpp-platform-header__brand">
      <div class="mxpp-wrap mxpp-platform-header__inner">
        <div
          class="mxpp-platform-brand"
          aria-label="<?= h($platformRegionLabel !== null ? 'México Médico ' . $platformRegionLabel : 'México Médico') ?>"
          <?php if ($geoStateSlug !== null): ?>data-state-slug="<?= h($geoStateSlug) ?>"<?php endif; ?>
          <?php if ($geoCitySlug !== null): ?>data-city-slug="<?= h($geoCitySlug) ?>"<?php endif; ?>
          <?php if ($geoSource !== null): ?>data-geo-source="<?= h($geoSource) ?>"<?php endif; ?>
        >
          <img
            class="mxpp-platform-brand__logo"
            src="/assets/mexico-medico.svg"
            alt="México Médico"
            width="250"
            height="48"
            decoding="async"
          />
          <div class="mxpp-platform-brand__copy">
            <?php if ($platformRegionLabel !== null): ?>
              <p class="mxpp-platform-brand__region"><?= h($platformRegionLabel) ?></p>
            <?php endif; ?>
            <p class="mxpp-platform-brand__tagline">encuentra a tu médico fácilmente</p>
          </div>
        </div>
      </div>
    </div>
    <div class="mxpp-platform-nav" aria-label="Secciones de México Médico">
      <div class="mxpp-wrap mxpp-platform-nav__inner">
        <?php foreach ($platformNavItems as $navItem): ?>
          <?php
            $navId = toText($navItem['id'] ?? null) ?? 'item';
            $navLabel = toText($navItem['label'] ?? null) ?? 'Sección';
            $navOptions = safeArray($navItem['items'] ?? []);
            $navButtonId = 'mxpp-platform-nav-button-' . $navId;
            $navPanelId = 'mxpp-platform-dropdown-' . $navId;
          ?>
          <div class="mxpp-platform-nav__menu" data-mxpp-platform-menu>
            <button
              type="button"
              class="mxpp-platform-nav__item"
              id="<?= h($navButtonId) ?>"
              aria-haspopup="true"
              aria-expanded="false"
              aria-controls="<?= h($navPanelId) ?>"
              data-mxpp-platform-menu-button
              data-nav-section-key="<?= h($navId) ?>"
              <?php if ($geoStateSlug !== null): ?>data-nav-context-state="<?= h($geoStateSlug) ?>"<?php endif; ?>
              <?php if ($geoCitySlug !== null): ?>data-nav-context-city="<?= h($geoCitySlug) ?>"<?php endif; ?>
            >
              <span><?= h($navLabel) ?></span>
              <span class="mxpp-platform-nav__chevron" aria-hidden="true">⌄</span>
            </button>
            <div
              class="mxpp-platform-dropdown"
              id="<?= h($navPanelId) ?>"
              role="menu"
              aria-labelledby="<?= h($navButtonId) ?>"
              data-mxpp-platform-dropdown
            >
              <?php foreach ($navOptions as $navOption): ?>
                <?php
                  $navOptionData = safeArray($navOption);
                  $navOptionLabel = toText($navOptionData['label'] ?? null);
                  $navOptionSlug = toText($navOptionData['slug'] ?? null);
                  $navOptionProfileType = toText($navOptionData['profile_type'] ?? null);
                  $navOptionRouteEnabled = toBool($navOptionData['route_enabled'] ?? false);
                  $navOptionUrl = toText($navOptionData['url'] ?? null);
                ?>
                <?php if ($navOptionLabel !== null): ?>
                  <button
                    type="button"
                    class="mxpp-platform-dropdown__item"
                    role="menuitem"
                    <?php if ($navOptionSlug !== null): ?>data-nav-item-slug="<?= h($navOptionSlug) ?>"<?php endif; ?>
                    <?php if ($navOptionProfileType !== null): ?>data-nav-profile-type="<?= h($navOptionProfileType) ?>"<?php endif; ?>
                    data-route-enabled="<?= $navOptionRouteEnabled ? 'true' : 'false' ?>"
                    <?php if ($navOptionUrl !== null): ?>data-nav-url="<?= h($navOptionUrl) ?>"<?php endif; ?>
                  ><?= h($navOptionLabel) ?></button>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </header>

  <main class="mxpp-wrap mxpp-main">
    <?php if ($inputError !== null || $dto === null): ?>
      <section class="mxpp-alert mxpp-alert--error">
        <h1>Perfil no disponible</h1>
        <p><?= h($inputError ?? $endpointError ?? 'No fue posible cargar el perfil en este momento.') ?></p>
      </section>
    <?php else: ?>
      <?php if ($showPublicBreadcrumb): ?>
        <nav class="mxpp-breadcrumb" aria-label="Ruta de navegación">
          <ol class="mxpp-breadcrumb__list">
            <?php foreach ($breadcrumbItems as $breadcrumbItem): ?>
              <?php $isCurrentBreadcrumb = toBool($breadcrumbItem['is_current'] ?? false); ?>
              <li class="mxpp-breadcrumb__item <?= $isCurrentBreadcrumb ? 'mxpp-breadcrumb__item--current' : '' ?>" <?= $isCurrentBreadcrumb ? 'aria-current="page"' : '' ?>>
                <span class="mxpp-breadcrumb__text"><?= h($breadcrumbItem['label']) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </nav>
      <?php endif; ?>

      <section class="mxpp-profile-hero">
        <aside class="mxpp-left-panel">
          <article class="mxpp-card mxpp-card--left-main">
            <div class="mxpp-avatar-wrap">
              <?php if ($photoUrl !== null): ?>
                <img src="<?= h($photoUrl) ?>" alt="Foto del médico" class="mxpp-avatar" />
              <?php else: ?>
                <div class="mxpp-avatar mxpp-avatar--placeholder" aria-hidden="true">
                  <div class="mxpp-avatar-shape"></div>
                </div>
              <?php endif; ?>
            </div>
          </article>
        </aside>

        <div class="mxpp-right-panel">
          <article class="mxpp-card mxpp-card--identity">
            <div class="mxpp-title-row">
              <div class="mxpp-title-primary-line">
                <h1 class="mxpp-profile-title <?= $displayName === null ? 'mxpp-profile-title--pending' : '' ?>">
                  <?php if ($showPaidProfileCheck): ?>
                    <?php if ($paidProfileNameLead !== null): ?><?= h($paidProfileNameLead) ?> <?php endif; ?><span class="mxpp-profile-name-tail"><?= h($paidProfileNameTail ?? '') ?> <span class="mxpp-paid-profile-check" role="img" aria-label="Perfil con plan activo" title="Perfil con plan activo">✓</span></span>
                  <?php else: ?>
                    <?= h($displayName ?? 'Perfil médico en validación') ?>
                  <?php endif; ?>
                </h1>
                <?php if ($professionalLicense !== null): ?>
                  <span class="mxpp-license-inline mxpp-license-inline--professional"><strong>Cédula profesional:</strong> <?= h($professionalLicense) ?></span>
                <?php endif; ?>
              </div>
              <?php if (!$isPublic): ?>
                <span class="mxpp-badge mxpp-badge--soft">En validación</span>
              <?php endif; ?>
            </div>

            <?php if ($showLimitedNotice): ?>
              <p class="mxpp-inline-limited">Información pública limitada: este perfil puede estar pendiente de validación.</p>
            <?php endif; ?>

            <?php if ($reviewsVisible): ?>
              <div class="mxpp-rating-row" aria-label="Calificación y opiniones">
                <p class="mxpp-rating-pill">
                  <span class="mxpp-rating-stars" aria-hidden="true">★★★★★</span>
                  <span class="mxpp-rating-count"><?= h((string)$reviewCount) ?> opiniones<?= $ratingAvg !== null ? ' · ' . h((string)$ratingAvg) : '' ?></span>
                </p>
                <span class="mxpp-rating-write-link">escribir una opinión</span>
              </div>
            <?php else: ?>
              <p class="mxpp-opinions mxpp-opinions--muted">Opiniones públicas no disponibles por ahora.</p>
            <?php endif; ?>

            <div class="mxpp-specialty-line">
              <?php if ($primarySpecialty !== null): ?>
                <p class="mxpp-specialty"><?= h($primarySpecialty) ?></p>
              <?php else: ?>
                <p class="mxpp-specialty mxpp-specialty--pending">Especialidad en validación</p>
              <?php endif; ?>
              <?php if ($specialtyLicense !== null): ?>
                <span class="mxpp-license-inline mxpp-license-inline--specialty"><strong>Cédula especialidad:</strong> <?= h($specialtyLicense) ?></span>
              <?php endif; ?>
            </div>

            <div class="mxpp-about-target"<?= $showAboutAction ? ' id="sobre-mi"' : '' ?>>
              <?php if ($showSuggestCorrection): ?>
                <a class="mxpp-action-link mxpp-action-link--summary" href="#" aria-disabled="true">Sugerir corrección</a>
              <?php elseif ($bioShort !== null): ?>
                <p class="mxpp-bio"><?= h($bioShort) ?></p>
              <?php else: ?>
                <p class="mxpp-bio mxpp-bio--pending">Descripción profesional en actualización.</p>
              <?php endif; ?>
            </div>

            <?php if ($physicianLogoUrl !== null || $showAboutAction || $showConsultaAction): ?>
              <div class="mxpp-hero-brand-actions <?= $physicianLogoUrl !== null ? 'mxpp-hero-brand-actions--with-logo' : 'mxpp-hero-brand-actions--without-logo' ?>">
              <?php if ($physicianLogoUrl !== null): ?>
                <div class="mxpp-physician-logo">
                  <img src="<?= h($physicianLogoUrl) ?>" alt="<?= h($physicianLogoAlt) ?>" loading="lazy" decoding="async" />
                </div>
              <?php endif; ?>
              <?php if ($showAboutAction || $showConsultaAction): ?>
                <nav class="mxpp-hero-actions" aria-label="Navegación del perfil">
                <?php if ($showAboutAction): ?>
                  <a class="mxpp-hero-action" href="#sobre-mi">
                    <span class="material-symbols-outlined mxpp-hero-action__icon" aria-hidden="true">person_text</span>
                    <span>Sobre mí</span>
                  </a>
                <?php endif; ?>
                <?php if ($showConsultaAction): ?>
                  <a class="mxpp-hero-action" href="<?= h($consultaTarget) ?>">
                    <span class="material-symbols-outlined mxpp-hero-action__icon" aria-hidden="true">event</span>
                    <span>Consulta</span>
                  </a>
                <?php endif; ?>
                </nav>
              <?php endif; ?>
              </div>
            <?php endif; ?>
          </article>

        </div>
      </section>

      <section id="consultorios" class="mxpp-card mxpp-consultorio-block" aria-label="Consultorios públicos" <?= $showConsultorioSwitcher ? 'data-mxpp-consultorio-switcher' : '' ?>>
        <div class="mxpp-consultorio-bar">
          <div class="mxpp-consultorio-brand <?= $primaryBrandLogoUrl !== null ? 'mxpp-consultorio-brand--with-logo' : '' ?>" data-mxpp-consultorio-brand>
            <img
              class="mxpp-consultorio-brand__logo"
              data-mxpp-consultorio-brand-logo
              <?php if ($primaryBrandLogoUrl !== null): ?>src="<?= h($primaryBrandLogoUrl) ?>" alt="<?= h('Logotipo de ' . $primaryBrandName) ?>"<?php else: ?>hidden<?php endif; ?>
              loading="lazy"
              decoding="async"
            />
            <h2 class="mxpp-consultorio-name" data-mxpp-consultorio-brand-name <?= $primaryBrandLogoUrl !== null ? 'hidden' : '' ?>><?= h($primaryBrandName) ?></h2>
          </div>
          <?php if ($showConsultorioSwitcher): ?>
            <div class="mxpp-consultorio-tabs" role="tablist" aria-label="Consultorios disponibles">
              <?php foreach ($consultorioPanels as $index => $consultorio): ?>
                <?php
                  $tabId = 'mxpp-consultorio-tab-' . ($index + 1);
                  $panelId = 'mxpp-consultorio-panel-' . ($index + 1);
                  $isActiveConsultorio = $index === 0;
                ?>
                <button
                  class="mxpp-consultorio-tab <?= $isActiveConsultorio ? 'mxpp-consultorio-tab--active' : '' ?>"
                  type="button"
                  id="<?= h($tabId) ?>"
                  role="tab"
                  aria-selected="<?= $isActiveConsultorio ? 'true' : 'false' ?>"
                  aria-controls="<?= h($panelId) ?>"
                  tabindex="<?= $isActiveConsultorio ? '0' : '-1' ?>"
                  data-mxpp-consultorio-tab
                >
                  <span class="mxpp-consultorio-tab__eyebrow">consultorio</span>
                  <span class="mxpp-consultorio-tab__name"><?= h($consultorio['name']) ?></span>
                  <span class="mxpp-consultorio-tab__chevron" aria-hidden="true">›</span>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php foreach ($consultorioPanels as $index => $consultorio): ?>
          <?php
            $tabId = 'mxpp-consultorio-tab-' . ($index + 1);
            $panelId = 'mxpp-consultorio-panel-' . ($index + 1);
            $isActiveConsultorio = $index === 0;
            $consultorioName = toText($consultorio['name'] ?? null) ?? 'Consultorio principal';
            $consultorioAddress = toText($consultorio['address'] ?? null);
            $consultorioPhone = toText($consultorio['phone_public'] ?? null) ?? $contactPhone;
            $consultorioWhatsapp = toText($consultorio['whatsapp_public'] ?? null) ?? $contactWhatsapp;
            $consultorioPhoneHref = telHref($consultorioPhone);
            $consultorioWhatsappHref = whatsappHref($consultorioWhatsapp);
            $canRenderConsultorioContact = (
                $consultorioPhoneHref !== null
                || $consultorioWhatsappHref !== null
                || $contactEmailHref !== null
            );
            $consultorioMapUrl = toText($consultorio['map_url'] ?? null);
            $consultorioSchedule = toText($consultorio['schedule_summary'] ?? null);
            $consultorioBrandName = toText($consultorio['brand_name'] ?? null) ?? $consultorioName;
            $consultorioBrandLogoUrl = toText($consultorio['brand_logo_url'] ?? null);
          ?>
          <div
            class="mxpp-consultorio-panel"
            id="<?= h($panelId) ?>"
            role="tabpanel"
            <?= $showConsultorioSwitcher ? 'aria-labelledby="' . h($tabId) . '"' : '' ?>
            data-mxpp-consultorio-panel
            data-mxpp-consultorio-brand-name="<?= h($consultorioBrandName) ?>"
            data-mxpp-consultorio-brand-logo="<?= h($consultorioBrandLogoUrl ?? '') ?>"
            <?= $isActiveConsultorio ? '' : 'hidden' ?>
          >
            <div class="mxpp-consultorio-body">
              <div class="mxpp-consultorio-address-col">
                <?php if ($showConsultorioSwitcher): ?>
                  <h3 class="mxpp-consultorio-panel-title"><?= h($consultorioName) ?></h3>
                <?php endif; ?>
                <?php if ($consultorioAddress !== null): ?>
                  <p class="mxpp-consultorio-address"><?= h($consultorioAddress) ?></p>
                <?php else: ?>
                  <p class="mxpp-consultorio-address mxpp-muted">Dirección pública no disponible por ahora.</p>
                <?php endif; ?>
                <?php if ($consultorioSchedule !== null): ?>
                  <p class="mxpp-consultorio-schedule"><?= h($consultorioSchedule) ?></p>
                <?php endif; ?>
                <?php if ($canRenderConsultorioContact): ?>
                  <div class="mxpp-consultorio-contact" aria-label="Contacto público">
                    <?php if ($consultorioPhoneHref !== null): ?>
                      <a class="mxpp-contact-line mxpp-contact-line--phone" href="<?= h($consultorioPhoneHref) ?>">
                        <span class="mxpp-contact-line__icon mxpp-contact-line__icon--phone" aria-hidden="true"></span>
                        <span><strong>Tel. Consultorio:</strong> <?= h($consultorioPhone ?? '') ?></span>
                      </a>
                    <?php endif; ?>
                    <?php if ($consultorioWhatsappHref !== null): ?>
                      <a class="mxpp-contact-line mxpp-contact-line--whatsapp" href="<?= h($consultorioWhatsappHref) ?>" target="_blank" rel="noopener">
                        <span class="mxpp-contact-line__icon mxpp-contact-line__icon--whatsapp" aria-hidden="true"></span>
                        <span><strong>WhatsApp:</strong> <?= h($consultorioWhatsapp ?? '') ?></span>
                      </a>
                    <?php endif; ?>
                    <?php if ($contactEmailHref !== null): ?>
                      <a class="mxpp-contact-line mxpp-contact-line--email" href="<?= h($contactEmailHref) ?>">
                        <span class="mxpp-contact-line__icon mxpp-contact-line__icon--email" aria-hidden="true"></span>
                        <span><?= h($contactEmail ?? '') ?></span>
                      </a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if ($consultorioMapUrl !== null && $showClickableMap): ?>
                  <a class="mxpp-map-link" href="<?= h($consultorioMapUrl) ?>" target="_blank" rel="noopener">Ver en Google Maps</a>
                <?php endif; ?>
                <?php if ($canRenderConsultorioContact): ?>
                  <div class="mxpp-consultorio-contact-actions">
                    <?php if ($consultorioPhoneHref !== null): ?>
                      <a class="mxpp-contact-cta mxpp-contact-cta--phone" href="<?= h($consultorioPhoneHref) ?>">
                        <span class="mxpp-contact-line__icon mxpp-contact-line__icon--phone" aria-hidden="true"></span>
                        <span>Llamar</span>
                      </a>
                    <?php endif; ?>
                    <?php if ($consultorioWhatsappHref !== null): ?>
                      <a class="mxpp-contact-cta mxpp-contact-cta--whatsapp" href="<?= h($consultorioWhatsappHref) ?>" target="_blank" rel="noopener">
                        <span class="mxpp-contact-line__icon mxpp-contact-line__icon--whatsapp" aria-hidden="true"></span>
                        <span>WhatsApp</span>
                      </a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="mxpp-consultorio-map-col <?= $showConsultorioSwitcher ? 'mxpp-consultorio-map-col--with-title' : '' ?>">
                <?php if ($consultorioMapUrl !== null): ?>
                  <div class="mxpp-map">
                    <iframe src="<?= h($consultorioMapUrl) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Ubicación de <?= h($consultorioName) ?>"></iframe>
                  </div>
                <?php else: ?>
                  <div class="mxpp-map mxpp-map--placeholder">
                    <span>Mapa no disponible por ahora.</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </section>

      <?php if ($showClaimProfile): ?>
        <div class="mxpp-actions-row">
          <a class="mxpp-action-claim" href="#" aria-disabled="true">Yo soy este médico y quiero administrar mi perfil</a>
        </div>
      <?php endif; ?>

      <?php if ($showAgendaSlot): ?>
        <section
          id="proximas-citas"
          class="mxpp-card mxpp-card--section mxpp-agenda-compact"
          data-mxpp-agenda-compact
          data-doctor-id="<?= h($doctorId) ?>"
          data-doctor-name="<?= h($displayName ?? 'Médico') ?>"
          data-booking-url="<?= h($bookAppointmentUrl) ?>"
          <?php if ($agendaMockMode !== null): ?>
            data-mock-mode="<?= h($agendaMockMode) ?>"
            data-mock-density="16,8,2|4,16,1|8,3,16"
          <?php endif; ?>
        >
          <div class="mxpp-agenda-compact__header">
            <div>
              <h2>Próximas citas disponibles</h2>
              <p>Reserva tu cita aquí</p>
            </div>
            <a class="mxpp-agenda-compact__open" href="<?= h($bookAppointmentUrl) ?>" data-mxpp-booking-trigger>Ver agenda</a>
          </div>
          <?php if ($agendaMockMode !== null): ?>
            <p class="mxpp-agenda-compact__qa-badge">Simulación visual</p>
          <?php endif; ?>
          <p class="mxpp-agenda-compact__status" data-mxpp-agenda-status>Cargando horarios...</p>
          <div class="mxpp-agenda-compact__nav" aria-label="Navegación de horarios disponibles">
            <button class="mxpp-agenda-compact__nav-btn" type="button" data-mxpp-agenda-prev disabled aria-label="Ver fechas anteriores">Anterior</button>
            <button class="mxpp-agenda-compact__nav-btn" type="button" data-mxpp-agenda-next disabled aria-label="Ver siguientes fechas disponibles">Siguiente</button>
          </div>
          <div class="mxpp-agenda-compact__days" data-mxpp-agenda-days hidden></div>
          <div class="mxpp-agenda-compact__selection" data-mxpp-agenda-selection aria-live="polite">
            <span class="mxpp-agenda-compact__selection-text" data-mxpp-agenda-selection-text>Selecciona un horario para continuar.</span>
          </div>
          <p class="mxpp-agenda-compact__alert" data-mxpp-agenda-alert hidden>Antes de continuar, selecciona una cita disponible.</p>
          <div class="mxpp-agenda-compact__footer">
            <a class="mxpp-book-cta" href="<?= h($bookAppointmentUrl) ?>" data-mxpp-booking-trigger>Agendar cita</a>
          </div>
        </section>
        <div class="mxpp-booking-modal" data-mxpp-booking-modal hidden aria-hidden="true">
          <div class="mxpp-booking-modal__backdrop" data-mxpp-booking-close></div>
          <section class="mxpp-booking-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mxpp-booking-modal-title">
            <button class="mxpp-booking-modal__close" type="button" data-mxpp-booking-close aria-label="Cerrar">×</button>
            <div class="mxpp-booking-modal__step mxpp-booking-modal__step--active" data-mxpp-booking-step="confirm">
              <p class="mxpp-booking-modal__eyebrow">Solicitud de cita</p>
              <h2 id="mxpp-booking-modal-title">Confirma tu cita</h2>
              <p>Estás a punto de solicitar una cita en el siguiente horario.</p>
              <div class="mxpp-booking-modal__summary">
                <p><strong>Doctor:</strong> <span data-mxpp-booking-doctor><?= h($displayName ?? 'Médico') ?></span></p>
                <p><strong>Fecha:</strong> <span data-mxpp-booking-date>Por confirmar</span></p>
                <p><strong>Hora:</strong> <span data-mxpp-booking-time>Por confirmar</span></p>
              </div>
              <div class="mxpp-booking-modal__actions">
                <button class="mxpp-booking-modal__secondary" type="button" data-mxpp-booking-close>Cancelar</button>
                <button class="mxpp-booking-modal__primary" type="button" data-mxpp-booking-next>Confirmar y continuar</button>
              </div>
            </div>
            <div class="mxpp-booking-modal__step" data-mxpp-booking-step="patient" hidden>
              <p class="mxpp-booking-modal__eyebrow">Datos del paciente</p>
              <h2>Datos primarios</h2>
              <div class="mxpp-booking-modal__summary">
                <p><strong>Doctor:</strong> <span data-mxpp-booking-doctor><?= h($displayName ?? 'Médico') ?></span></p>
                <p><strong>Fecha:</strong> <span data-mxpp-booking-date>Por confirmar</span></p>
                <p><strong>Hora:</strong> <span data-mxpp-booking-time>Por confirmar</span></p>
              </div>
              <form class="mxpp-booking-modal__form" data-mxpp-booking-form>
                <label>Nombre(s)<input type="text" name="first_name" autocomplete="given-name" required /></label>
                <label>Apellido paterno<input type="text" name="last_name" autocomplete="family-name" required /></label>
                <label>Apellido materno <span>opcional</span><input type="text" name="second_last_name" autocomplete="additional-name" /></label>
                <label>Teléfono móvil<input type="tel" name="mobile_phone" autocomplete="tel" required /></label>
                <label>Correo electrónico<input type="email" name="email" autocomplete="email" required /></label>
                <label>Fecha de nacimiento<input type="date" name="birth_date" required /></label>
                <label>Género
                  <select name="gender" required>
                    <option value="">Selecciona</option>
                    <option value="F">Femenino</option>
                    <option value="M">Masculino</option>
                    <option value="No especifica">No especifica</option>
                  </select>
                </label>
                <label class="mxpp-booking-modal__field--wide">Motivo de consulta <span>opcional</span><textarea name="reason" rows="3" maxlength="1000"></textarea></label>
              </form>
              <p class="mxpp-booking-modal__message" data-mxpp-booking-message hidden></p>
              <div class="mxpp-booking-modal__actions">
                <button class="mxpp-booking-modal__secondary" type="button" data-mxpp-booking-back>Atrás</button>
                <button class="mxpp-booking-modal__secondary" type="button" data-mxpp-booking-close>Cerrar</button>
                <button class="mxpp-booking-modal__primary" type="button" data-mxpp-booking-submit>Solicitar código</button>
              </div>
            </div>
            <div class="mxpp-booking-modal__step" data-mxpp-booking-step="sent" hidden>
              <p class="mxpp-booking-modal__eyebrow">Vista previa</p>
              <h2>Solicitud en preparación</h2>
              <p>Este paso quedará conectado a la confirmación por código en la siguiente fase. No se ha creado ninguna cita todavía.</p>
              <div class="mxpp-booking-modal__summary">
                <p><strong>Doctor:</strong> <span data-mxpp-booking-doctor><?= h($displayName ?? 'Médico') ?></span></p>
                <p><strong>Fecha:</strong> <span data-mxpp-booking-date>Por confirmar</span></p>
                <p><strong>Hora:</strong> <span data-mxpp-booking-time>Por confirmar</span></p>
                <p><strong>Teléfono:</strong> <span data-mxpp-booking-contact>Por confirmar</span></p>
              </div>
              <p class="mxpp-booking-modal__message mxpp-booking-modal__message--success">No se ha creado ninguna cita todavía.</p>
              <div class="mxpp-booking-modal__actions">
                <button class="mxpp-booking-modal__secondary" type="button" data-mxpp-booking-close>Cerrar</button>
                <button class="mxpp-booking-modal__primary" type="button" data-mxpp-booking-close>Entendido</button>
              </div>
            </div>
          </section>
        </div>
      <?php elseif ($showInstitutionalImageSlot): ?>
        <section class="mxpp-institutional" aria-label="Espacio institucional del consultorio">
          <img src="<?= h($institutionalImageUrl) ?>" alt="Imagen institucional del consultorio" loading="lazy" />
        </section>
      <?php endif; ?>

      <?php if ($canRenderContactSection): ?>
        <section class="mxpp-card mxpp-card--section">
          <h2>Contacto</h2>
          <?php if ($showPhone && $contactPhone !== null): ?>
            <p><strong>Teléfono:</strong> <?= h($contactPhone) ?></p>
          <?php endif; ?>
          <?php if ($showWhatsapp && $contactWhatsapp !== null): ?>
            <p><strong>WhatsApp:</strong> <?= h($contactWhatsapp) ?></p>
          <?php endif; ?>
          <?php if ($contactEmail !== null): ?>
            <p><strong>Email:</strong> <?= h($contactEmail) ?></p>
          <?php endif; ?>
          <?php if ($showInternalInbox): ?>
            <p class="mxpp-muted">Buzón interno disponible según configuración pública vigente.</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($showFee): ?>
        <section class="mxpp-card mxpp-card--section">
          <h2>Costo y medios de pago</h2>
          <?php if ($consultationFee !== null): ?>
            <p><strong>Costo de consulta:</strong> <?= h(is_scalar($consultationFee) ? (string)$consultationFee : json_encode($consultationFee, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></p>
          <?php else: ?>
            <p class="mxpp-muted">Costo de consulta no disponible.</p>
          <?php endif; ?>
          <?php if (!empty($paymentMethods)): ?>
            <ul class="mxpp-bullets">
              <?php foreach ($paymentMethods as $method): ?>
                <li><?= h(is_scalar($method) ? (string)$method : json_encode($method, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($showInsurances): ?>
        <section class="mxpp-card mxpp-card--section">
          <h2>Aseguradoras aceptadas</h2>
          <?php if (!empty($acceptedInsurances)): ?>
            <ul class="mxpp-bullets">
              <?php foreach ($acceptedInsurances as $insurer): ?>
                <?php if (!is_array($insurer)): continue; endif; ?>
                <li><?= h(toText($insurer['name'] ?? null) ?? 'Aseguradora') ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="mxpp-muted">Aseguradoras no disponibles por ahora.</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <section class="mxpp-review-strip">
        <?php if ($reviewsVisible): ?>
          <p class="mxpp-review-strip__header">Opiniones de pacientes · <?= h((string)$reviewCount) ?><?= $ratingAvg !== null ? ' · ' . h((string)$ratingAvg) : '' ?></p>
          <p class="mxpp-review-strip__body">Se muestran opiniones públicas disponibles para este perfil.</p>
        <?php else: ?>
          <p class="mxpp-review-strip__header">Opiniones de pacientes</p>
          <p class="mxpp-review-strip__body">Aún no hay opiniones públicas disponibles para este perfil.</p>
        <?php endif; ?>
        <div class="mxpp-review-strip__links">
          <span>+ VER MÁS OPINIONES</span>
          <span>escribir una opinión</span>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <footer class="mxpp-footer">
    <div class="mxpp-wrap mxpp-footer__top">
      <div class="mxpp-footer__brand">México Médico</div>
      <div class="mxpp-footer__participa">
        <p>¿como formar parte?</p>
        <small>médicos · aseguradoras · clínicas</small>
      </div>
    </div>
    <div class="mxpp-wrap mxpp-footer__links-row">
      <div class="mxpp-footer__links">
        <a href="#" aria-disabled="true">Nosotros</a>
        <span>·</span>
        <a href="#" aria-disabled="true">Términos y Condiciones</a>
        <span>·</span>
        <a href="#" aria-disabled="true">Aviso de Privacidad</a>
      </div>
      <div class="mxpp-footer__links">
        <a href="#" aria-disabled="true">Laboratorios</a>
        <span>·</span>
        <a href="#" aria-disabled="true">Especialistas</a>
      </div>
    </div>
    <div class="mxpp-footer__bottom">
      <div class="mxpp-wrap">
        <small>Todos los derechos reservados México Médico</small>
      </div>
    </div>
  </footer>
  <?php if ($showDevPlanSwitcher): ?>
    <form class="mxpp-dev-plan-switcher" method="get" action="/profiles/doctor.php" aria-label="Selector temporal QA de plan">
      <input type="hidden" name="doctor_id" value="<?= h($doctorId) ?>" />
      <label class="mxpp-dev-plan-switcher__label" for="mxpp-dev-plan-select">Plan QA</label>
      <select class="mxpp-dev-plan-switcher__select" id="mxpp-dev-plan-select" name="mxmed_plan" onchange="this.form.submit()">
        <?php foreach ([
            'free' => 'Gratuito',
            'basic' => 'Básico',
            'standard' => 'Estándar',
            'optimum' => 'Óptimo',
            'professional' => 'Profesional',
        ] as $planCode => $label): ?>
          <option value="<?= h($planCode) ?>" <?= ($devPlanOverride ?? toText($plan['plan_code'] ?? null)) === $planCode ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="mxpp-dev-plan-switcher__hint">Sólo DEV</span>
    </form>
  <?php endif; ?>
  <script>
    (function () {
      function initPlatformMenus() {
        var menus = Array.prototype.slice.call(document.querySelectorAll('[data-mxpp-platform-menu]'));

        function closeMenu(menu) {
          var button = menu.querySelector('[data-mxpp-platform-menu-button]');
          menu.classList.remove('mxpp-platform-nav__menu--open');
          if (button) {
            button.setAttribute('aria-expanded', 'false');
          }
        }

        function openMenu(menu) {
          var button = menu.querySelector('[data-mxpp-platform-menu-button]');
          menus.forEach(function (item) {
            if (item !== menu) {
              closeMenu(item);
            }
          });
          menu.classList.add('mxpp-platform-nav__menu--open');
          if (button) {
            button.setAttribute('aria-expanded', 'true');
          }
        }

        if (!menus.length) {
          return;
        }

        menus.forEach(function (menu) {
          var button = menu.querySelector('[data-mxpp-platform-menu-button]');
          var options = Array.prototype.slice.call(menu.querySelectorAll('.mxpp-platform-dropdown__item'));

          if (!button) {
            return;
          }

          button.addEventListener('click', function (event) {
            event.preventDefault();
            if (menu.classList.contains('mxpp-platform-nav__menu--open')) {
              closeMenu(menu);
            } else {
              openMenu(menu);
            }
          });

          menu.addEventListener('mouseenter', function () {
            openMenu(menu);
          });

          menu.addEventListener('mouseleave', function () {
            closeMenu(menu);
          });

          menu.addEventListener('focusin', function () {
            openMenu(menu);
          });

          menu.addEventListener('focusout', function (event) {
            if (!menu.contains(event.relatedTarget)) {
              closeMenu(menu);
            }
          });

          options.forEach(function (option) {
            option.addEventListener('click', function (event) {
              event.preventDefault();
              closeMenu(menu);
              button.focus();
            });
          });
        });

        document.addEventListener('click', function (event) {
          if (!event.target.closest('[data-mxpp-platform-menu]')) {
            menus.forEach(closeMenu);
          }
        });

        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            menus.forEach(closeMenu);
          }
        });
      }

      function initConsultorioSwitcher(switcher) {
        var tabs = Array.prototype.slice.call(switcher.querySelectorAll('[data-mxpp-consultorio-tab]'));
        var panels = Array.prototype.slice.call(switcher.querySelectorAll('[data-mxpp-consultorio-panel]'));
        if (tabs.length < 2 || panels.length < 2) {
          return;
        }

        var brandName = switcher.querySelector('[data-mxpp-consultorio-brand-name]');
        var brandLogo = switcher.querySelector('[data-mxpp-consultorio-brand-logo]');
        var brand = switcher.querySelector('[data-mxpp-consultorio-brand]');

        function syncBranding(panel) {
          if (!panel || !brandName || !brandLogo) {
            return;
          }
          var logoUrl = panel.getAttribute('data-mxpp-consultorio-brand-logo') || '';
          var name = panel.getAttribute('data-mxpp-consultorio-brand-name') || 'Consultorio';
          if (brand) {
            brand.classList.toggle('mxpp-consultorio-brand--with-logo', Boolean(logoUrl));
          }
          if (logoUrl) {
            brandName.textContent = name;
            brandName.hidden = true;
            brandLogo.src = logoUrl;
            brandLogo.alt = 'Logotipo de ' + name;
            brandLogo.hidden = false;
            return;
          }
          brandName.textContent = name;
          brandName.hidden = false;
          brandLogo.hidden = true;
          brandLogo.removeAttribute('src');
          brandLogo.removeAttribute('alt');
        }

        function activateTab(tab) {
          var panelId = tab.getAttribute('aria-controls') || '';
          tabs.forEach(function (item) {
            var isActive = item === tab;
            item.classList.toggle('mxpp-consultorio-tab--active', isActive);
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');
            item.setAttribute('tabindex', isActive ? '0' : '-1');
          });
          panels.forEach(function (panel) {
            panel.hidden = panel.id !== panelId;
          });
          syncBranding(panels.find(function (panel) {
            return panel.id === panelId;
          }));
        }

        tabs.forEach(function (tab, index) {
          tab.addEventListener('click', function () {
            activateTab(tab);
          });
          tab.addEventListener('keydown', function (event) {
            var nextIndex = null;
            if (event.key === 'ArrowRight') {
              nextIndex = (index + 1) % tabs.length;
            } else if (event.key === 'ArrowLeft') {
              nextIndex = (index - 1 + tabs.length) % tabs.length;
            }
            if (nextIndex === null) {
              return;
            }
            event.preventDefault();
            tabs[nextIndex].focus();
            activateTab(tabs[nextIndex]);
          });
        });
      }

      initPlatformMenus();
      document.querySelectorAll('[data-mxpp-consultorio-switcher]').forEach(initConsultorioSwitcher);
    })();
  </script>
  <?php if ($showAgendaSlot): ?>
    <script>
      (function () {
        function escapeHtml(value) {
          return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        }

        function formatDate(dateYmd) {
          var date = new Date(String(dateYmd || '') + 'T00:00:00');
          if (Number.isNaN(date.getTime())) {
            return String(dateYmd || '');
          }
          return new Intl.DateTimeFormat('es-MX', {
            weekday: 'short',
            day: 'numeric',
            month: 'short'
          }).format(date);
        }

        function formatTime(dateTimeValue) {
          var value = String(dateTimeValue || '');
          return value.length >= 16 ? value.slice(11, 16) : value;
        }

        function formatLocalDate(date) {
          var month = String(date.getMonth() + 1).padStart(2, '0');
          var day = String(date.getDate()).padStart(2, '0');
          return String(date.getFullYear()) + '-' + month + '-' + day;
        }

        function addDays(dateYmd, amount) {
          var date = new Date(String(dateYmd || '') + 'T00:00:00');
          if (Number.isNaN(date.getTime())) {
            return '';
          }
          date.setDate(date.getDate() + amount);
          return formatLocalDate(date);
        }

        function addMinutesToTime(timeValue, minutesToAdd) {
          var parts = String(timeValue || '').split(':');
          var hours = parseInt(parts[0] || '0', 10);
          var minutes = parseInt(parts[1] || '0', 10);
          if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return timeValue;
          }
          var date = new Date(2000, 0, 1, hours, minutes + minutesToAdd, 0);
          return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
        }

        function renderStatus(block, message) {
          var status = block.querySelector('[data-mxpp-agenda-status]');
          if (status) {
            status.textContent = message;
            status.hidden = false;
          }
        }

        function clearStatus(block) {
          var status = block.querySelector('[data-mxpp-agenda-status]');
          if (status) {
            status.hidden = true;
            status.textContent = '';
          }
        }

        function updateControls(block, state) {
          var prevButton = block.querySelector('[data-mxpp-agenda-prev]');
          var nextButton = block.querySelector('[data-mxpp-agenda-next]');
          if (prevButton) {
            prevButton.disabled = state.isLoading || state.currentBlockIndex <= 0;
          }
          if (nextButton) {
            nextButton.disabled = state.isLoading || (state.hasMore === false && state.currentBlockIndex >= state.blocks.length - 1);
          }
        }

        function buildUsefulDays(days) {
          return Array.isArray(days)
            ? days.filter(function (day) {
              return day && Array.isArray(day.slots) && day.slots.length > 0;
            }).slice(0, 3)
            : [];
        }

        function showSelectionAlert(block) {
          var alert = block.querySelector('[data-mxpp-agenda-alert]');
          if (alert) {
            alert.hidden = false;
          }
        }

        function hideSelectionAlert(block) {
          var alert = block.querySelector('[data-mxpp-agenda-alert]');
          if (alert) {
            alert.hidden = true;
          }
        }

        function clearBookingModalMessage(modal) {
          if (!modal) {
            return;
          }
          modal.querySelectorAll('[data-mxpp-booking-message]').forEach(function (message) {
            message.hidden = true;
            message.textContent = '';
            message.classList.remove('mxpp-booking-modal__message--error', 'mxpp-booking-modal__message--success');
          });
        }

        function showBookingModalMessage(modal, type, text) {
          if (!modal) {
            return;
          }
          var message = modal.querySelector('[data-mxpp-booking-message]');
          if (!message) {
            return;
          }
          message.textContent = text;
          message.hidden = false;
          message.classList.toggle('mxpp-booking-modal__message--error', type === 'error');
          message.classList.toggle('mxpp-booking-modal__message--success', type === 'success');
        }

        function getBookingModal() {
          return document.querySelector('[data-mxpp-booking-modal]');
        }

        function setBookingModalStep(modal, stepName) {
          if (!modal) {
            return;
          }
          modal.querySelectorAll('[data-mxpp-booking-step]').forEach(function (step) {
            var isActive = step.getAttribute('data-mxpp-booking-step') === stepName;
            step.hidden = !isActive;
            step.classList.toggle('mxpp-booking-modal__step--active', isActive);
          });
        }

        function closeBookingModal() {
          var modal = getBookingModal();
          if (!modal) {
            return;
          }
          modal.hidden = true;
          modal.setAttribute('aria-hidden', 'true');
          setBookingModalStep(modal, 'confirm');
          clearBookingModalMessage(modal);
          var form = modal.querySelector('[data-mxpp-booking-form]');
          if (form) {
            form.reset();
          }
        }

        function fillBookingModal(modal, state) {
          if (!modal || !state.selectedSlot) {
            return;
          }
          modal.querySelectorAll('[data-mxpp-booking-doctor]').forEach(function (node) {
            node.textContent = state.doctorName || 'Médico';
          });
          modal.querySelectorAll('[data-mxpp-booking-date]').forEach(function (node) {
            node.textContent = formatDate(state.selectedSlot.date);
          });
          modal.querySelectorAll('[data-mxpp-booking-time]').forEach(function (node) {
            node.textContent = formatTime(state.selectedSlot.start_at);
          });
        }

        function openBookingModal(block, state) {
          if (!state.selectedSlot) {
            showSelectionAlert(block);
            block.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
          }
          hideSelectionAlert(block);
          var modal = getBookingModal();
          if (!modal) {
            return;
          }
          fillBookingModal(modal, state);
          clearBookingModalMessage(modal);
          setBookingModalStep(modal, 'confirm');
          modal.hidden = false;
          modal.setAttribute('aria-hidden', 'false');
          var nextButton = modal.querySelector('[data-mxpp-booking-next]');
          if (nextButton) {
            nextButton.focus();
          }
        }

        function resetSelectionState(block, state) {
          state.selectedSlot = null;
          state.appointmentId = null;
          state.cancelToken = null;
          state.otpId = null;
          block.querySelectorAll('.mxpp-agenda-compact__slot').forEach(function (button) {
            button.classList.remove('mxpp-agenda-compact__slot--selected');
            button.setAttribute('aria-pressed', 'false');
          });
          hideSelectionAlert(block);
          closeBookingModal();
          var selection = block.querySelector('[data-mxpp-agenda-selection]');
          var selectionText = block.querySelector('[data-mxpp-agenda-selection-text]');
          if (selection) {
            selection.classList.remove('mxpp-agenda-compact__selection--active');
          }
          if (selectionText) {
            selectionText.textContent = 'Selecciona un horario para continuar.';
          }
        }

        function setSelectedSlot(block, state, slotData) {
          state.selectedSlot = slotData;
          state.appointmentId = null;
          state.cancelToken = null;
          state.otpId = null;
          block.querySelectorAll('.mxpp-agenda-compact__slot').forEach(function (button) {
            var isSelected = button.getAttribute('data-slot-date') === slotData.date
              && button.getAttribute('data-slot-start') === slotData.start_at;
            button.classList.toggle('mxpp-agenda-compact__slot--selected', isSelected);
            button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
          });
          var selection = block.querySelector('[data-mxpp-agenda-selection]');
          var selectionText = block.querySelector('[data-mxpp-agenda-selection-text]');
          if (selection) {
            selection.classList.add('mxpp-agenda-compact__selection--active');
          }
          hideSelectionAlert(block);
          if (selectionText) {
            selectionText.textContent = 'Horario seleccionado: ' + formatDate(slotData.date) + ', ' + formatTime(slotData.start_at) + '. Pulsa Agendar cita para continuar.';
          }
        }

        function getConsultorioIdFromBlock(blockData) {
          var meta = blockData && blockData.meta ? blockData.meta : {};
          return String(meta.consultorio_id_used || '').trim();
        }

        function renderCurrentBlock(block, state) {
          var container = block.querySelector('[data-mxpp-agenda-days]');
          if (!container) {
            return;
          }

          var currentBlock = state.blocks[state.currentBlockIndex] || null;
          var usefulDays = currentBlock && Array.isArray(currentBlock.days) ? currentBlock.days : [];
          resetSelectionState(block, state);
          updateControls(block, state);

          if (usefulDays.length === 0) {
            renderStatus(block, 'No hay horarios disponibles por ahora. Puedes revisar más opciones en la agenda.');
            container.hidden = true;
            container.innerHTML = '';
            return;
          }

          clearStatus(block);

          container.innerHTML = usefulDays.map(function (day) {
            var date = String(day.date || '');
            var slots = Array.isArray(day.slots) ? day.slots : [];
            var mockCount = state.isMock ? slots.length : null;
            var slotHtml = slots.map(function (slot) {
              var startAt = String(slot && slot.start_at ? slot.start_at : '');
              var endAt = String(slot && slot.end_at ? slot.end_at : '');
              if (startAt === '') {
                return '';
              }
              return '<button class="mxpp-agenda-compact__slot" type="button" aria-pressed="false"'
                + ' data-slot-date="' + escapeHtml(date) + '"'
                + ' data-slot-start="' + escapeHtml(startAt) + '"'
                + ' data-slot-end="' + escapeHtml(endAt) + '">'
                + escapeHtml(formatTime(startAt))
                + '</button>';
            }).join('');
            var mockCountHtml = mockCount !== null
              ? '<span class="mxpp-agenda-compact__mock-count">' + escapeHtml(String(mockCount)) + ' horarios QA</span>'
              : '';

            return '<article class="mxpp-agenda-compact__day"' + (mockCount !== null ? ' data-mock-slot-count="' + escapeHtml(String(mockCount)) + '"' : '') + '>'
              + '<h3>' + escapeHtml(formatDate(date)) + '</h3>'
              + '<p>' + escapeHtml(date) + '</p>'
              + mockCountHtml
              + '<div class="mxpp-agenda-compact__slots">' + slotHtml + '</div>'
              + '</article>';
          }).join('');
          container.hidden = false;

          container.querySelectorAll('.mxpp-agenda-compact__slot').forEach(function (slotButton) {
            slotButton.addEventListener('click', function () {
              setSelectedSlot(block, state, {
                date: slotButton.getAttribute('data-slot-date') || '',
                start_at: slotButton.getAttribute('data-slot-start') || '',
                end_at: slotButton.getAttribute('data-slot-end') || '',
                consultorio_id: getConsultorioIdFromBlock(currentBlock),
                doctor_id: state.doctorId,
                booking_url: state.bookingUrl
              });
            });
          });
        }

        function getNextStartDate(blockData) {
          var days = blockData && Array.isArray(blockData.days) ? blockData.days : [];
          if (days.length === 0) {
            return '';
          }
          return addDays(String(days[days.length - 1].date || ''), 1);
        }

        function buildMockSlots(dateYmd, slotCount) {
          var timeProfiles = {
            16: [
              '09:00', '09:30', '10:00', '10:30',
              '11:00', '11:30', '12:00', '12:30',
              '16:00', '16:30', '17:00', '17:30',
              '18:00', '18:30', '19:00', '19:30'
            ],
            8: [
              '09:00', '09:30', '10:00', '10:30',
              '16:00', '16:30', '17:00', '17:30'
            ],
            4: ['09:00', '09:30', '16:00', '16:30'],
            3: ['09:00', '16:00', '18:00'],
            2: ['09:00', '16:00'],
            1: ['16:00']
          };
          var times = timeProfiles[slotCount] || timeProfiles[2];

          return times.map(function (timeValue) {
            return {
              start_at: dateYmd + ' ' + timeValue + ':00',
              end_at: dateYmd + ' ' + addMinutesToTime(timeValue, 30) + ':00'
            };
          });
        }

        function buildMockBlocks() {
          var baseDate = formatLocalDate(new Date());
          var densityBlocks = [
            [16, 8, 2],
            [4, 16, 1],
            [8, 3, 16]
          ];

          return densityBlocks.map(function (densities, blockIndex) {
            var days = densities.map(function (slotCount, dayIndex) {
              var dateYmd = addDays(baseDate, (blockIndex * 3) + dayIndex);
              return {
                date: dateYmd,
                weekday: dayIndex + 1,
                slots: buildMockSlots(dateYmd, slotCount)
              };
            });
            var blockData = {
              startDate: days[0] ? days[0].date : null,
              days: days,
              meta: {
                source: 'dev_mock',
                density: densities.join(',')
              },
              nextStartDate: ''
            };
            blockData.nextStartDate = getNextStartDate(blockData);
            return blockData;
          });
        }

        function loadMockAgenda(block, state) {
          state.blocks = buildMockBlocks();
          state.currentBlockIndex = 0;
          state.hasMore = false;
          state.isLoading = false;
          renderCurrentBlock(block, state);
        }

        function fetchAvailability(block, state, startDate) {
          if (state.isLoading) {
            return;
          }
          state.isLoading = true;
          updateControls(block, state);
          renderStatus(block, 'Cargando horarios...');

          var params = new URLSearchParams();
          params.set('doctor_id', state.doctorId);
          params.set('mode', 'next');
          params.set('days', '3');
          params.set('limit_per_day', '0');
          if (startDate) {
            params.set('start_date', startDate);
          }

          fetch('/api/agenda/index.php/public/availability?' + params.toString(), {
            method: 'GET',
            headers: { Accept: 'application/json' }
          })
            .then(function (response) {
              return response.json().then(function (payload) {
                if (!response.ok || !payload || payload.ok !== true) {
                  throw new Error('availability unavailable');
                }
                return payload;
              });
            })
            .then(function (payload) {
              var data = payload && payload.data ? payload.data : {};
              var usefulDays = buildUsefulDays(data.days);
              if (usefulDays.length === 0) {
                state.hasMore = false;
                if (state.blocks.length === 0) {
                  state.blocks = [{
                    startDate: startDate || null,
                    days: [],
                    meta: payload.meta || {},
                    nextStartDate: ''
                  }];
                  state.currentBlockIndex = 0;
                  renderCurrentBlock(block, state);
                } else {
                  renderStatus(block, 'No encontramos más horarios disponibles por ahora.');
                }
                return;
              }

              var blockData = {
                startDate: startDate || null,
                days: usefulDays,
                meta: payload.meta || {},
                nextStartDate: ''
              };
              blockData.nextStartDate = getNextStartDate(blockData);
              state.blocks = state.blocks.slice(0, state.currentBlockIndex + 1);
              state.blocks.push(blockData);
              state.currentBlockIndex = state.blocks.length - 1;
              state.hasMore = true;
              renderCurrentBlock(block, state);
            })
            .catch(function () {
              renderStatus(block, 'No pudimos cargar los horarios en este momento.');
            })
            .finally(function () {
              state.isLoading = false;
              updateControls(block, state);
            });
        }

        function getBookingFormData(modal) {
          var form = modal ? modal.querySelector('[data-mxpp-booking-form]') : null;
          if (!form) {
            return null;
          }
          var data = new FormData(form);
          return {
            first_name: String(data.get('first_name') || '').trim(),
            last_name: String(data.get('last_name') || '').trim(),
            second_last_name: String(data.get('second_last_name') || '').trim(),
            mobile_phone: String(data.get('mobile_phone') || '').trim(),
            email: String(data.get('email') || '').trim(),
            birth_date: String(data.get('birth_date') || '').trim(),
            gender: String(data.get('gender') || '').trim(),
            reason: String(data.get('reason') || '').trim()
          };
        }

        function getPatientFullName(data) {
          return [data.first_name, data.last_name, data.second_last_name]
            .filter(function (part) { return part !== ''; })
            .join(' ');
        }

        function isValidInlineEmail(value) {
          return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }

        function isValidInlineDate(value) {
          if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return false;
          }
          var date = new Date(value + 'T00:00:00');
          return !Number.isNaN(date.getTime());
        }

        function validateInlineBookingData(modal, state) {
          var data = getBookingFormData(modal);
          if (!state.selectedSlot) {
            return { ok: false, message: 'Antes de continuar, selecciona una cita disponible.' };
          }
          if (!data) {
            return { ok: false, message: 'No pudimos leer el formulario. Intenta de nuevo.' };
          }
          if (data.first_name === '' || data.last_name === '') {
            return { ok: false, message: 'Completa nombre(s) y apellido paterno.' };
          }
          var phoneDigits = data.mobile_phone.replace(/\D+/g, '');
          if (phoneDigits.length < 10) {
            return { ok: false, message: 'Ingresa un teléfono móvil válido.' };
          }
          if (data.email === '' || !isValidInlineEmail(data.email)) {
            return { ok: false, message: 'Ingresa un correo electrónico válido.' };
          }
          if (!isValidInlineDate(data.birth_date)) {
            return { ok: false, message: 'Ingresa una fecha de nacimiento válida.' };
          }
          if (data.gender !== 'F' && data.gender !== 'M' && data.gender !== 'No especifica') {
            return { ok: false, message: 'Selecciona un género válido.' };
          }
          if (data.reason.length > 1000) {
            return { ok: false, message: 'El motivo de consulta es demasiado largo.' };
          }
          data.full_name = getPatientFullName(data);
          data.phone_digits = phoneDigits;
          return { ok: true, data: data };
        }

        function setBookingSubmitState(modal, busy, label) {
          var button = modal ? modal.querySelector('[data-mxpp-booking-submit]') : null;
          if (!button) {
            return;
          }
          button.disabled = busy;
          button.textContent = label || 'Solicitar código';
        }

        function fillBookingSentStep(modal, state, patientData) {
          fillBookingModal(modal, state);
          modal.querySelectorAll('[data-mxpp-booking-contact]').forEach(function (node) {
            node.textContent = patientData.mobile_phone;
          });
        }

        function submitInlineBookingPreview(modal, state) {
          clearBookingModalMessage(modal);
          var valid = validateInlineBookingData(modal, state);
          if (!valid.ok) {
            showBookingModalMessage(modal, 'error', valid.message);
            return;
          }

          var patientData = valid.data;
          fillBookingSentStep(modal, state, patientData);
          setBookingModalStep(modal, 'sent');
          setBookingSubmitState(modal, false, 'Solicitar código');
        }

        function bindBookingModalControls(block, state) {
          document.querySelectorAll('[data-mxpp-booking-trigger]').forEach(function (trigger) {
            if (trigger.getAttribute('data-mxpp-booking-bound') === 'true') {
              return;
            }
            trigger.setAttribute('data-mxpp-booking-bound', 'true');
            trigger.addEventListener('click', function (event) {
              event.preventDefault();
              openBookingModal(block, state);
            });
          });

          var modal = getBookingModal();
          if (!modal || modal.getAttribute('data-mxpp-booking-bound') === 'true') {
            return;
          }
          modal.setAttribute('data-mxpp-booking-bound', 'true');
          modal.querySelectorAll('[data-mxpp-booking-close]').forEach(function (button) {
            button.addEventListener('click', closeBookingModal);
          });
          var nextButton = modal.querySelector('[data-mxpp-booking-next]');
          if (nextButton) {
            nextButton.addEventListener('click', function () {
              setBookingModalStep(modal, 'patient');
              var firstInput = modal.querySelector('[data-mxpp-booking-step="patient"] input');
              if (firstInput) {
                firstInput.focus();
              }
            });
          }
          var backButton = modal.querySelector('[data-mxpp-booking-back]');
          if (backButton) {
            backButton.addEventListener('click', function () {
              setBookingModalStep(modal, 'confirm');
            });
          }
          var form = modal.querySelector('[data-mxpp-booking-form]');
          if (form) {
            form.addEventListener('submit', function (event) {
              event.preventDefault();
              submitInlineBookingPreview(modal, state);
            });
          }
          var submitButton = modal.querySelector('[data-mxpp-booking-submit]');
          if (submitButton) {
            submitButton.addEventListener('click', function () {
              submitInlineBookingPreview(modal, state);
            });
          }
          document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
              closeBookingModal();
            }
          });
        }

        function initCompactAgenda(block) {
          var doctorId = String(block.getAttribute('data-doctor-id') || '').trim();
          var doctorName = String(block.getAttribute('data-doctor-name') || 'Médico').trim();
          var bookingUrl = String(block.getAttribute('data-booking-url') || '/public-book.html').trim();
          var mockMode = String(block.getAttribute('data-mock-mode') || '').trim();
          var prevButton = block.querySelector('[data-mxpp-agenda-prev]');
          var nextButton = block.querySelector('[data-mxpp-agenda-next]');
          var state = {
            currentBlockIndex: -1,
            blocks: [],
            selectedSlot: null,
            isLoading: false,
            hasMore: true,
            isMock: mockMode === 'mixed',
            doctorId: doctorId,
            doctorName: doctorName,
            bookingUrl: bookingUrl
          };

          if (doctorId === '') {
            renderStatus(block, 'No pudimos cargar los horarios en este momento.');
            updateControls(block, state);
            return;
          }

          if (prevButton) {
            prevButton.addEventListener('click', function () {
              if (state.currentBlockIndex <= 0 || state.isLoading) {
                return;
              }
              state.currentBlockIndex -= 1;
              renderCurrentBlock(block, state);
            });
          }

          if (nextButton) {
            nextButton.addEventListener('click', function () {
              if (state.isLoading) {
                return;
              }
              if (state.blocks[state.currentBlockIndex + 1]) {
                state.currentBlockIndex += 1;
                renderCurrentBlock(block, state);
                return;
              }
              var currentBlock = state.blocks[state.currentBlockIndex] || null;
              var nextStartDate = currentBlock ? currentBlock.nextStartDate : '';
              if (state.isMock) {
                state.hasMore = false;
                updateControls(block, state);
                renderStatus(block, 'No encontramos más horarios disponibles por ahora.');
                return;
              }
              if (!nextStartDate) {
                state.hasMore = false;
                updateControls(block, state);
                renderStatus(block, 'No encontramos más horarios disponibles por ahora.');
                return;
              }
              fetchAvailability(block, state, nextStartDate);
            });
          }

          updateControls(block, state);
          bindBookingModalControls(block, state);
          if (state.isMock) {
            loadMockAgenda(block, state);
            return;
          }
          fetchAvailability(block, state, '');
        }

        document.querySelectorAll('[data-mxpp-agenda-compact]').forEach(initCompactAgenda);
      })();
    </script>
  <?php endif; ?>
</body>
</html>
