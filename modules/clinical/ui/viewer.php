<?php
// modules/clinical/ui/viewer.php

function get_api_base(): string
{
    $env = trim((string)getenv('CLINICAL_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $env = trim((string)getenv('MXMED_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $env = trim((string)getenv('MXMED_TIMELINE_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    $proto = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $proto = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = (string)$_SERVER['HTTP_X_FORWARDED_PROTO'];
    }

    $hostOnly = preg_replace('/:\d+$/', '', $host) ?: '127.0.0.1';
    return $proto . '://' . $hostOnly . ':8091';
}

function normalize_clinical_api_base(string $base): string
{
    $normalized = rtrim(trim($base), '/');
    if ($normalized === '') {
        return '';
    }
    $suffix = '/api/clinical/index.php';
    if (substr($normalized, -strlen($suffix)) === $suffix) {
        $normalized = rtrim(substr($normalized, 0, -strlen($suffix)), '/');
    }
    return $normalized;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function validate_return_to(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if ($value[0] === '/') {
        return (strpos($value, '//') === 0) ? null : $value;
    }

    if (!preg_match('/^https?:\/\//i', $value)) {
        return null;
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        return null;
    }

    $urlPath = (string)($parts['path'] ?? '');
    if ($urlPath !== '' && strpos($urlPath, '/modules/clinical/ui/') === 0) {
        return $value;
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $port = isset($parts['port']) ? (int)$parts['port'] : null;

    $currentHostRaw = (string)($_SERVER['HTTP_HOST'] ?? '');
    $currentHostParts = parse_url('http://' . $currentHostRaw);
    $currentHost = strtolower((string)($currentHostParts['host'] ?? ''));
    $currentPort = isset($currentHostParts['port']) ? (int)$currentHostParts['port'] : null;
    $currentScheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';

    if ($host === '' || $currentHost === '') {
        return null;
    }

    if ($host !== $currentHost || $scheme !== $currentScheme) {
        return null;
    }

    if (($port ?? ($scheme === 'https' ? 443 : 80)) !== ($currentPort ?? ($currentScheme === 'https' ? 443 : 80))) {
        return null;
    }

    return $value;
}

function normalize_return_to(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if ($value[0] === '/') {
        $parts = parse_url($value);
        if (!is_array($parts)) {
            return $value;
        }
        $path = (string)($parts['path'] ?? '/');
        $query = [];
        if (isset($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            if (is_array($query)) {
                unset($query['doc_uuid'], $query['uuid']);
            } else {
                $query = [];
            }
        }
        $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return $path . ($qs !== '' ? ('?' . $qs) : '');
    }

    if (!preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }

    $url = parse_url($value);
    if (!is_array($url)) {
        return $value;
    }
    $query = [];
    if (isset($url['query'])) {
        parse_str((string)$url['query'], $query);
        if (is_array($query)) {
            unset($query['doc_uuid'], $query['uuid']);
        } else {
            $query = [];
        }
    }
    $scheme = (string)($url['scheme'] ?? 'http');
    $host = (string)($url['host'] ?? '');
    $port = isset($url['port']) ? (':' . (int)$url['port']) : '';
    $path = (string)($url['path'] ?? '/');
    $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    return $scheme . '://' . $host . $port . $path . ($qs !== '' ? ('?' . $qs) : '');
}

function render_embed_css(bool $embed): void
{
    if (!$embed) {
        return;
    }

    echo '<link rel="stylesheet" href="/assets/css/style.css">' . "\n";
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">' . "\n";
}

function http_get_json(string $url, int $timeoutSeconds = 8): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $line) {
        if (is_string($line) && preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/i', trim($line), $m)) {
            $status = (int)$m[1];
            break;
        }
    }
    if ($raw === false) {
        return ['ok' => false, 'error' => 'fetch_failed', 'message' => 'No se pudo consultar el documento. status=' . $status];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'invalid_json', 'message' => 'Respuesta inválida del endpoint de documentos. status=' . $status];
    }

    if ($status !== 0 && $status !== 200 && (($decoded['ok'] ?? false) !== true)) {
        $msg = trim((string)($decoded['message'] ?? ''));
        return [
            'ok' => false,
            'error' => (string)($decoded['error'] ?? 'http_error'),
            'message' => ($msg !== '' ? $msg : 'Error consultando documento.') . ' status=' . $status,
        ];
    }

    return $decoded;
}

function build_viewer_self_href(array $params): string
{
    $query = [];
    foreach ($params as $key => $value) {
        if ($value === null) {
            continue;
        }
        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            continue;
        }
        $query[$key] = $stringValue;
    }
    return '/modules/clinical/ui/viewer.php' . ($query !== [] ? ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)) : '');
}

function first_non_empty_string(array $sources, array $keys): string
{
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }
        foreach ($keys as $key) {
            $value = trim((string)($source[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
}

function is_allowed_external_url(string $url, array $allowedHosts, string $currentHost): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '') {
        return false;
    }

    if ($currentHost !== '' && $host === $currentHost) {
        return true;
    }

    return in_array($host, $allowedHosts, true);
}

function is_relative_media_url(string $url): bool
{
    $url = trim($url);
    return $url !== '' && $url[0] === '/';
}

function is_same_origin_media_url(string $url, string $currentHost): bool
{
    $url = trim($url);
    if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
        return false;
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return $host !== '' && $currentHost !== '' && $host === strtolower($currentHost);
}

function sort_bundle_documents_for_viewer(array $items): array
{
    $decorated = [];
    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        $item['_idx'] = (int)$idx;
        $decorated[] = $item;
    }

    usort($decorated, static function ($left, $right): int {
        $leftItem = is_array($left) ? $left : [];
        $rightItem = is_array($right) ? $right : [];

        $leftDt = trim((string)($leftItem['event_datetime'] ?? ''));
        $rightDt = trim((string)($rightItem['event_datetime'] ?? ''));
        if ($leftDt !== $rightDt) {
            return strcmp($leftDt, $rightDt);
        }

        $leftId = (int)($leftItem['id'] ?? 0);
        $rightId = (int)($rightItem['id'] ?? 0);
        if ($leftId > 0 || $rightId > 0) {
            if ($leftId !== $rightId) {
                return $leftId <=> $rightId;
            }
        }

        $leftIdx = (int)($leftItem['_idx'] ?? 0);
        $rightIdx = (int)($rightItem['_idx'] ?? 0);
        if ($leftIdx !== $rightIdx) {
            return $leftIdx <=> $rightIdx;
        }

        return strcmp(
            trim((string)($leftItem['document_uuid'] ?? '')),
            trim((string)($rightItem['document_uuid'] ?? ''))
        );
    });

    foreach ($decorated as &$item) {
        unset($item['_idx']);
    }
    unset($item);

    return $decorated;
}

$uuid = trim((string)($_GET['uuid'] ?? ''));
if ($uuid === '') {
    $uuid = trim((string)($_GET['doc_uuid'] ?? ''));
}
$bundleId = trim((string)($_GET['bundle_id'] ?? ''));
$patientId = trim((string)($_GET['patient_id'] ?? ''));
$returnTo = validate_return_to((string)($_GET['return_to'] ?? ''));
$returnToClean = $returnTo !== null ? normalize_return_to($returnTo) : '';
$backHref = $returnToClean !== '' ? $returnToClean : 'javascript:history.back()';
$embedQueryFlag = trim((string)($_GET['embed'] ?? '')) === '1' ? '1' : '';
$debugView = trim((string)($_GET['debug'] ?? '')) === '1';
$errorMessage = '';
$document = null;
$bundleData = null;
$bundleItems = [];
$bundleClinicalBlock = null;
$bundleTitle = '';
$bundleNote = '';
$selectedBundleIndex = 0;
$apiBase = normalize_clinical_api_base((string)getenv('CLINICAL_API_BASE'));
if ($apiBase === '') {
    $apiBase = normalize_clinical_api_base(get_api_base());
}
$apiIndexBase = ($apiBase !== '') ? ($apiBase . '/api/clinical/index.php') : '';

if ($bundleId !== '' && $apiIndexBase !== '') {
    $bundleUrl = $apiIndexBase . '/bundles/' . rawurlencode($bundleId) . '/documents';
    if ($patientId !== '') {
        $bundleUrl .= '?patient_id=' . rawurlencode($patientId);
    }
    $bundleDecoded = http_get_json($bundleUrl, 8);
    if (($bundleDecoded['ok'] ?? false) !== true) {
        $errorMessage = (string)($bundleDecoded['message'] ?? 'Error consultando bundle.');
    } else {
        $bundlePayload = is_array($bundleDecoded['data'] ?? null) ? $bundleDecoded['data'] : [];
        $bundleItemsRaw = is_array($bundlePayload['items'] ?? null) ? $bundlePayload['items'] : [];
        $bundleItems = [];
        foreach ($bundleItemsRaw as $bundleItemRaw) {
            if (!is_array($bundleItemRaw)) {
                continue;
            }
            if (trim((string)($bundleItemRaw['document_type'] ?? '')) === 'bundle_clinical') {
                if ($bundleClinicalBlock === null) {
                    $bundleClinicalCandidate = is_array($bundleItemRaw['bundle_clinical'] ?? null) ? $bundleItemRaw['bundle_clinical'] : [];
                    $bundleClinicalBlock = [
                        'summary' => trim((string)($bundleClinicalCandidate['summary'] ?? ($bundleItemRaw['summary'] ?? ''))),
                        'interpretation' => trim((string)($bundleClinicalCandidate['interpretation'] ?? '')),
                        'observations' => trim((string)($bundleClinicalCandidate['observations'] ?? '')),
                    ];
                }
                continue;
            }
            $bundleItems[] = $bundleItemRaw;
        }
        $bundleItems = sort_bundle_documents_for_viewer($bundleItems);
        $bundleTitle = trim((string)($bundlePayload['bundle_title'] ?? ''));
        $bundleNote = trim((string)($bundlePayload['bundle_note'] ?? ''));
        $bundleData = $bundlePayload;
        if ($bundleItems === [] && $bundleClinicalBlock === null) {
            $errorMessage = 'Bundle sin documentos.';
        } elseif ($bundleItems !== []) {
            $selectedBundleIndex = 0;
            if ($uuid !== '') {
                foreach ($bundleItems as $idx => $bundleItem) {
                    if (!is_array($bundleItem)) {
                        continue;
                    }
                    if (trim((string)($bundleItem['document_uuid'] ?? '')) === $uuid) {
                        $selectedBundleIndex = $idx;
                        break;
                    }
                }
            }
            $selectedItem = is_array($bundleItems[$selectedBundleIndex] ?? null) ? $bundleItems[$selectedBundleIndex] : [];
            $uuid = trim((string)($selectedItem['document_uuid'] ?? $uuid));
            if ($patientId === '') {
                $patientId = trim((string)($bundlePayload['patient_id'] ?? ''));
            }
            if ($bundleTitle === '') {
                $bundleTitle = trim((string)($selectedItem['media_bundle_title'] ?? ''));
            }
            if ($bundleNote === '') {
                $bundleNote = trim((string)($selectedItem['media_bundle_note'] ?? ''));
            }
        }
    }
}

if ($uuid !== '') {
    if ($apiIndexBase === '') {
        $errorMessage = 'CLINICAL_API_BASE no configurado y get_api_base() vacío.';
    } else {
        $url = $apiIndexBase . '/documents/' . rawurlencode($uuid);
        $decoded = http_get_json($url, 8);

        if (($decoded['ok'] ?? false) !== true) {
            $errorMessage = (string)($decoded['message'] ?? 'Error consultando documento.');
        } else {
            $doc = $decoded['data']['document'] ?? null;
            $document = is_array($doc) ? $doc : null;
            if (!$document) {
                $errorMessage = 'Documento no disponible.';
            }
        }
    }
}

$docType = $document ? (string)($document['document_type'] ?? '-') : '-';
$title = $document ? (string)($document['title'] ?? '-') : '-';
$displayTitle = $bundleTitle !== '' ? $bundleTitle : $title;
$date = $document ? (string)($document['ui']['event_datetime'] ?? ($document['timestamps']['created_at'] ?? '-')) : '-';
$summary = $document ? (string)($document['content']['summary'] ?? '-') : '-';
$content = $document && is_array($document['content'] ?? null) ? $document['content'] : [];
$payload = $document && is_array($content['payload'] ?? null) ? $content['payload'] : [];
$mode = strtolower(trim((string)($_GET['mode'] ?? '')));
$isFullscreenMode = ($mode === 'fullscreen');
$payloadJson = $payload ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
$renderedText = $document ? (string)($content['rendered_text'] ?? '') : '';
$docTypeNorm = strtolower(trim($docType));
$isConsentDoc = ($docTypeNorm === 'consentimiento_informado');
$consentBlock = is_array($payload['consent'] ?? null) ? $payload['consent'] : [];
$consentPatientSnapshot = is_array($payload['patient_snapshot'] ?? null) ? $payload['patient_snapshot'] : [];
$consentActorSnapshot = is_array($payload['actor_snapshot'] ?? null) ? $payload['actor_snapshot'] : [];
$consentTemplateSnapshot = is_array($payload['template_snapshot'] ?? null) ? $payload['template_snapshot'] : [];
$consentLegal = is_array($payload['legal'] ?? null) ? $payload['legal'] : [];
$consentSignatures = is_array($payload['signatures'] ?? null) ? $payload['signatures'] : [];
$consentObservations = trim((string)($payload['observations'] ?? ''));
$consentLegalDetails = is_array($payload['consent_legal'] ?? null) ? $payload['consent_legal'] : [];
$consentSigner = is_array($payload['firmante'] ?? null) ? $payload['firmante'] : [];
$consentWitnesses = is_array($payload['testigos'] ?? null) ? $payload['testigos'] : [];
$consentRenderedText = trim((string)($payload['rendered_text'] ?? ($payload['text'] ?? $renderedText)));
$consentStatus = trim((string)($payload['status'] ?? ($consentBlock['status'] ?? 'draft')));
$consentPrintableHref = ($uuid !== '') ? ('/modules/clinical/ui/document.php?uuid=' . rawurlencode($uuid) . ($embedQueryFlag !== '' ? '&embed=1' : '')) : '';
$consentPatientSignature = is_array($consentSignatures['patient'] ?? null) ? $consentSignatures['patient'] : [];
$consentPatientSignatureImage = trim((string)($consentPatientSignature['image_data'] ?? ''));
$consentPatientSignatureSignerName = trim((string)($consentPatientSignature['signer_name'] ?? ''));
$consentPatientSignatureSignedAt = trim((string)($consentPatientSignature['signed_at'] ?? ''));
$consentPatientSignatureSource = trim((string)($consentPatientSignature['source'] ?? ''));
$consentIdentityAttachments = [];
if (is_array($payload['signer_identity_attachments'] ?? null)) {
    $consentIdentityAttachments = $payload['signer_identity_attachments'];
} elseif (is_array($payload['attachments']['signer_identity'] ?? null)) {
    $consentIdentityAttachments = $payload['attachments']['signer_identity'];
}

$consentBoolLabel = static function ($value): string {
    return $value ? 'Sí' : 'No';
};

$fileMeta = is_array($payload['file'] ?? null) ? $payload['file'] : [];
$optimizedMeta = is_array($fileMeta['optimized'] ?? null) ? $fileMeta['optimized'] : [];
$thumbMeta = is_array($fileMeta['thumb'] ?? null) ? $fileMeta['thumb'] : [];
$originalMeta = is_array($fileMeta['original'] ?? null) ? $fileMeta['original'] : [];
$renderMode = strtolower(trim((string)($fileMeta['render_mode'] ?? '')));
$mimeType = strtolower(first_non_empty_string([$optimizedMeta, $fileMeta, $document, $content, $payload], ['mime', 'mime_type', 'content_type', 'type', 'media_type']));
$mediaSrc = first_non_empty_string([$optimizedMeta, $originalMeta, $fileMeta, $payload, $content], ['path', 'url', 'src', 'file_url', 'pdf_url', 'image_url']);
$thumbSrc = first_non_empty_string([$thumbMeta], ['path', 'url', 'src']);
$htmlInline = trim((string)($payload['html'] ?? ''));

$currentHost = strtolower((string)parse_url(get_api_base(), PHP_URL_HOST));
$externalIframeAllowlist = []; // viewer v0.1: dominios explícitos para iframe externo.
$externalAllowed = $mediaSrc !== '' ? is_allowed_external_url($mediaSrc, $externalIframeAllowlist, $currentHost) : false;
$isRelativeMediaSrc = is_relative_media_url($mediaSrc);
$isSameOriginMediaSrc = is_same_origin_media_url($mediaSrc, $currentHost);
$externalBlockedMessage = '';

$detectedMode = 'json';
if ($renderMode === 'image') {
    $detectedMode = 'image';
} elseif ($htmlInline !== '') {
    $detectedMode = 'html_inline';
} elseif ($mediaSrc !== '') {
    $srcLower = strtolower($mediaSrc);
    if (strpos($mimeType, 'image/') === 0 || preg_match('/\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i', $srcLower)) {
        $detectedMode = 'image';
    } elseif ($mimeType === 'application/pdf' || preg_match('/\.pdf(\?.*)?$/i', $srcLower)) {
        $detectedMode = 'pdf';
    } elseif ($mimeType === 'text/html' || preg_match('/\.html?(\?.*)?$/i', $srcLower)) {
        $detectedMode = 'html_external';
    }
}

if (($detectedMode === 'pdf' || $detectedMode === 'html_external') && $mediaSrc !== '' && !$externalAllowed && !$isRelativeMediaSrc && !$isSameOriginMediaSrc) {
    $externalBlockedMessage = 'La URL externa no está permitida por la allowlist del viewer.';
    $detectedMode = 'json';
}

$isPdf = ($renderMode === 'pdf' || $mimeType === 'application/pdf' || $detectedMode === 'pdf');
$openInNewHref = $mediaSrc !== '' && $externalAllowed ? $mediaSrc : ((string)($_SERVER['REQUEST_URI'] ?? '/modules/clinical/ui/viewer.php'));
$showDownloadAction = ($mediaSrc !== '' && ($isRelativeMediaSrc || $isSameOriginMediaSrc || $externalAllowed));
$downloadIsRelative = $isRelativeMediaSrc;
$bundlePrevHref = '';
$bundleNextHref = '';
if ($bundleItems !== []) {
    $prevIndex = ($selectedBundleIndex > 0) ? ($selectedBundleIndex - 1) : -1;
    $nextIndex = ($selectedBundleIndex < (count($bundleItems) - 1)) ? ($selectedBundleIndex + 1) : -1;
    if ($prevIndex >= 0 && is_array($bundleItems[$prevIndex] ?? null)) {
        $bundlePrevHref = build_viewer_self_href([
            'bundle_id' => $bundleId,
            'patient_id' => $patientId,
            'uuid' => (string)($bundleItems[$prevIndex]['document_uuid'] ?? ''),
            'return_to' => $returnToClean,
            'embed' => $embedQueryFlag,
        ]);
    }
    if ($nextIndex >= 0 && is_array($bundleItems[$nextIndex] ?? null)) {
        $bundleNextHref = build_viewer_self_href([
            'bundle_id' => $bundleId,
            'patient_id' => $patientId,
            'uuid' => (string)($bundleItems[$nextIndex]['document_uuid'] ?? ''),
            'return_to' => $returnToClean,
            'embed' => $embedQueryFlag,
        ]);
    }
}

require_once __DIR__ . '/../../_partials/clinical_embed.php';
$embed = is_embed_request();

if (!$embed) {
    $pageTitle = 'Document Viewer';
    require_once __DIR__ . '/../../_partials/mm_shell_top.php';
} else {
    render_embed_css($embed);
    clinical_embed_start();
}
?>
<style>
  html,body{height:100%;}
  .mm-viewer-shell{height:100vh;display:flex;flex-direction:column;}
  .mm-viewer-actions{flex:0 0 auto;}
  .mm-viewer-frame{flex:1 1 auto;min-height:0;}
  .mm-viewer-frame iframe{width:100%;height:100%;border:0;}
  .clinical-viewer .viewer-sticky-head{
    position: sticky;
    top: 0;
    z-index: 3;
    background: #fff;
    border-bottom: 1px solid rgba(0,0,0,.08);
    padding-bottom: .5rem;
    margin-bottom: .75rem;
  }
  .clinical-viewer.is-fullscreen{
    background: #111827;
    min-height: 100vh;
    padding: 0 !important;
  }
  .clinical-viewer.is-fullscreen .viewer-sticky-head{
    display: none;
  }
  .clinical-viewer.is-fullscreen .fullscreen-image-wrap{
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 1rem;
  }
  .clinical-viewer.is-fullscreen .fullscreen-image-wrap img{
    max-width: 100%;
    max-height: 96vh;
    object-fit: contain;
    border: 0;
  }
  .consent-doc-sheet{
    background:#fff;
    border:1px solid #d7eaf2;
    border-radius:14px;
    padding:16px;
    display:grid;
    gap:12px;
  }
  .consent-doc-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    border-bottom:1px solid #edf4f8;
    padding-bottom:8px;
  }
  .consent-doc-title{font-size:1rem;font-weight:800;color:#0a405f;line-height:1.3;}
  .consent-doc-meta{font-size:.84rem;color:#5d6b74;}
  .consent-doc-section-title{
    font-size:.8rem;
    font-weight:800;
    color:#0a405f;
    text-transform:uppercase;
    letter-spacing:.02em;
    margin-bottom:4px;
  }
  .consent-doc-text{font-size:.9rem;color:#273b47;white-space:pre-wrap;line-height:1.45;}
  .consent-doc-sign-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
  }
  .consent-doc-sign-item{
    border:1px solid #e8f0f5;
    border-radius:10px;
    padding:10px;
  }
  .consent-doc-sign-image{
    max-width:220px;
    max-height:90px;
    width:100%;
    object-fit:contain;
    border:1px dashed #d0dde6;
    border-radius:8px;
    background:#fff;
    margin-top:8px;
  }
  .consent-doc-sign-line{margin-top:28px;border-top:1px solid #9fb6c4;}
  @media (max-width: 767.98px){
    .consent-doc-head{flex-direction:column;}
    .consent-doc-sign-grid{grid-template-columns:1fr;}
  }
</style>
<div class="<?php echo $embed ? 'py-1' : 'container py-4'; ?>">
  <div class="clinical-viewer mm-viewer-shell <?php echo $isFullscreenMode ? 'is-fullscreen' : ''; ?>">
  <div class="viewer-sticky-head">
    <div class="d-flex justify-content-between align-items-center mt-2">
      <div>
        <h1 class="h5 mb-0">Document Viewer</h1>
        <?php if ($displayTitle !== '-' && ($uuid !== '' || $bundleId !== '')): ?>
          <div class="text-secondary small"><?php echo h($displayTitle); ?></div>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-2 mm-viewer-actions">
        <?php if ($returnToClean !== ''): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($backHref); ?>">Volver</a>
        <?php endif; ?>
        <?php if ($showDownloadAction): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($mediaSrc); ?>"<?php echo $downloadIsRelative ? ' download' : ''; ?>>Descargar</a>
        <?php endif; ?>
        <?php if ($isPdf || $isConsentDoc): ?>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-role="viewer-print">Imprimir</button>
        <?php endif; ?>
        <?php if ($isConsentDoc && $consentPrintableHref !== ''): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($consentPrintableHref); ?>" target="_blank" rel="noopener">Versión imprimible</a>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($consentPrintableHref); ?>" target="_blank" rel="noopener" download>Descargar</a>
        <?php endif; ?>
        <?php if ($bundlePrevHref !== ''): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($bundlePrevHref); ?>">Anterior</a>
        <?php endif; ?>
        <?php if ($bundleNextHref !== ''): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($bundleNextHref); ?>">Siguiente</a>
        <?php endif; ?>
        <a class="btn btn-outline-primary btn-sm" href="<?php echo h($openInNewHref); ?>" target="_blank" rel="noopener">Abrir en pestaña</a>
      </div>
    </div>
  </div>

  <?php if ($bundleId !== ''): ?>
    <p class="text-secondary mb-3">bundle_id: <code><?php echo h($bundleId); ?></code></p>
  <?php else: ?>
    <p class="text-secondary mb-3">uuid: <code><?php echo h($uuid !== '' ? $uuid : '-'); ?></code></p>
  <?php endif; ?>

  <?php if ($uuid === '' && $bundleId === ''): ?>
    <div class="alert alert-warning">uuid o bundle_id requerido.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php else: ?>
    <?php
    $bundleClinicalSummary = trim((string)($bundleClinicalBlock['summary'] ?? ''));
    $bundleClinicalInterpretation = trim((string)($bundleClinicalBlock['interpretation'] ?? ''));
    $bundleClinicalObservations = trim((string)($bundleClinicalBlock['observations'] ?? ''));
    ?>
    <?php if ($bundleItems !== []): ?>
      <div class="mm-card mb-3">
        <div class="body">
          <div class="fw-semibold"><?php echo h($bundleTitle !== '' ? $bundleTitle : 'Bundle de imágenes'); ?></div>
          <?php if ($bundleNote !== ''): ?>
            <div class="text-secondary small mt-1"><?php echo h($bundleNote); ?></div>
          <?php endif; ?>
          <div class="mt-2 vstack gap-2">
            <?php foreach ($bundleItems as $bundleIndex => $bundleItem): ?>
              <?php
              $bundleItemUuid = trim((string)($bundleItem['document_uuid'] ?? ''));
              $bundleItemCaption = trim((string)($bundleItem['media_caption'] ?? ''));
              $bundleItemTag = trim((string)($bundleItem['media_tag_label'] ?? ''));
              $bundleItemLabel = $bundleItemCaption !== '' ? $bundleItemCaption : ($bundleItemTag !== '' ? $bundleItemTag : 'Imagen');
              $bundleItemHref = build_viewer_self_href([
                  'bundle_id' => $bundleId,
                  'patient_id' => $patientId,
                  'uuid' => $bundleItemUuid,
                  'return_to' => $returnToClean,
                  'embed' => $embed ? '1' : '',
              ]);
              ?>
              <a class="btn btn-sm <?php echo $bundleIndex === $selectedBundleIndex ? 'btn-primary' : 'btn-outline-secondary'; ?> text-start" href="<?php echo h($bundleItemHref); ?>">
                <?php echo h($bundleItemLabel); ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($bundleClinicalSummary !== '' || $bundleClinicalInterpretation !== '' || $bundleClinicalObservations !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Notas clínicas del estudio</h5></div>
        <div class="body">
          <?php if ($bundleClinicalSummary !== ''): ?>
            <div class="mb-3">
              <div class="fw-semibold small text-uppercase text-secondary">Descripción</div>
              <div><?php echo nl2br(h($bundleClinicalSummary)); ?></div>
            </div>
          <?php endif; ?>
          <?php if ($bundleClinicalInterpretation !== ''): ?>
            <div class="mb-3">
              <div class="fw-semibold small text-uppercase text-secondary">Interpretación</div>
              <div><?php echo nl2br(h($bundleClinicalInterpretation)); ?></div>
            </div>
          <?php endif; ?>
          <?php if ($bundleClinicalObservations !== ''): ?>
            <div>
              <div class="fw-semibold small text-uppercase text-secondary">Observaciones</div>
              <div><?php echo nl2br(h($bundleClinicalObservations)); ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <div class="mm-card mb-3">
      <div class="body small">
        <div><strong>Tipo:</strong> <?php echo h($docType); ?></div>
        <div><strong>Fecha:</strong> <?php echo h($date); ?></div>
        <?php if ($summary !== '' && $summary !== '-'): ?>
          <div><strong>Summary:</strong> <?php echo h($summary); ?></div>
        <?php endif; ?>
        <div><strong>Viewer mode:</strong> <?php echo h($detectedMode); ?></div>
      </div>
    </div>

    <?php if ($isConsentDoc): ?>
      <?php
      $consentDocTitle = trim((string)($consentBlock['document_title'] ?? $title));
      $consentPatientName = trim((string)($consentPatientSnapshot['full_name'] ?? 'Paciente'));
      $consentDoctorName = trim((string)($consentActorSnapshot['full_name'] ?? 'Médico tratante'));
      $consentDoctorLicense = trim((string)($consentActorSnapshot['license'] ?? ''));
      $consentProcedure = trim((string)($consentBlock['document_title'] ?? ''));
      $consentBenefits = trim((string)($consentLegalDetails['beneficios_esperados'] ?? ''));
      $consentAlternatives = trim((string)($consentLegalDetails['alternativas'] ?? ''));
      $consentNoAccept = trim((string)($consentLegalDetails['consecuencias_no_aceptar'] ?? ''));
      $consentContingency = !empty($consentLegalDetails['autorizacion_contingencias']);
      $consentSignerType = trim((string)($consentSigner['tipo'] ?? 'paciente'));
      $consentSignerName = trim((string)($consentSigner['nombre'] ?? $consentPatientName));
      $consentSignerRelationRaw = trim((string)($consentSigner['relacion'] ?? ($consentSigner['parentesco'] ?? '')));
      $consentRelationLabels = [
        'self' => 'Paciente',
        'padre' => 'Padre',
        'madre' => 'Madre',
        'conyuge' => 'Cónyuge',
        'hijo' => 'Hijo',
        'hija' => 'Hija',
        'hermano' => 'Hermano',
        'hermana' => 'Hermana',
        'abuelo' => 'Abuelo',
        'abuela' => 'Abuela',
        'otro_familiar' => 'Otro familiar',
        'otro' => 'Otro',
      ];
      $consentSignerRelation = (string)($consentRelationLabels[$consentSignerRelationRaw] ?? $consentSignerRelationRaw);
      $consentSignerTypeLabels = [
        'paciente' => 'Paciente',
        'tutor' => 'Tutor',
        'representante_legal' => 'Representante legal',
        'familiar_mas_cercano' => 'Familiar más cercano en vínculo',
      ];
      $consentSignerTypeLabel = (string)($consentSignerTypeLabels[$consentSignerType] ?? ucfirst(str_replace('_', ' ', $consentSignerType)));
      $consentSignatureSourceLabelMap = [
        'local_canvas' => 'Firma local',
        'remote_qr' => 'Firma remota',
      ];
      $consentSignatureSourceLabel = (string)($consentSignatureSourceLabelMap[$consentPatientSignatureSource] ?? '');
      $consentWitness1 = trim((string)((is_array($consentWitnesses[0] ?? null) ? ($consentWitnesses[0]['nombre'] ?? '') : '')));
      $consentWitness2 = trim((string)((is_array($consentWitnesses[1] ?? null) ? ($consentWitnesses[1]['nombre'] ?? '') : '')));
      $consentBodyText = trim((string)($consentTemplateSnapshot['body_text'] ?? ''));
      $consentStatusBadgeClass = ($consentStatus === 'granted') ? 'text-bg-success' : 'text-bg-secondary';
      ?>
      <article class="consent-doc-sheet mb-3">
        <header class="consent-doc-head">
          <div>
            <div class="consent-doc-title"><?php echo h($consentDocTitle !== '' ? $consentDocTitle : 'Consentimiento informado'); ?></div>
            <div class="consent-doc-meta">Paciente: <?php echo h($consentPatientName); ?></div>
            <div class="consent-doc-meta">Médico: <?php echo h($consentDoctorName); ?><?php echo $consentDoctorLicense !== '' ? h(' · Cédula: ' . $consentDoctorLicense) : ''; ?></div>
          </div>
          <div class="text-end">
            <span class="badge <?php echo h($consentStatusBadgeClass); ?>"><?php echo h($consentStatus !== '' ? $consentStatus : 'draft'); ?></span>
            <div class="consent-doc-meta mt-2">Fecha: <?php echo h((string)($consentBlock['granted_at'] ?? $date)); ?></div>
          </div>
        </header>

        <section>
          <div class="consent-doc-section-title">Acto autorizado</div>
          <div class="consent-doc-text"><?php echo nl2br(h($consentProcedure !== '' ? $consentProcedure : 'Sin descripción de procedimiento.')); ?></div>
        </section>

        <section>
          <div class="consent-doc-section-title">Riesgos y beneficios esperados</div>
          <div class="consent-doc-text">
            <?php echo nl2br(h($consentBodyText !== '' ? $consentBodyText : 'Riesgos explicados conforme a criterio médico.')); ?>
            <?php if ($consentBenefits !== ''): ?><br><br><?php echo nl2br(h('Beneficios esperados: ' . $consentBenefits)); ?><?php endif; ?>
          </div>
        </section>

        <?php if ($consentAlternatives !== ''): ?>
          <section>
            <div class="consent-doc-section-title">Alternativas</div>
            <div class="consent-doc-text"><?php echo nl2br(h($consentAlternatives)); ?></div>
          </section>
        <?php endif; ?>

        <?php if ($consentNoAccept !== ''): ?>
          <section>
            <div class="consent-doc-section-title">Consecuencias de no realizarlo</div>
            <div class="consent-doc-text"><?php echo nl2br(h($consentNoAccept)); ?></div>
          </section>
        <?php endif; ?>

        <section>
          <div class="consent-doc-section-title">Declaraciones</div>
          <div class="consent-doc-text">Autorización de contingencias y urgencias: <?php echo h($consentContingency ? 'Sí' : 'No'); ?></div>
          <div class="consent-doc-text">Se explicó en lenguaje claro y se resolvieron dudas: <?php echo h($consentBoolLabel((bool)($consentLegal['questions_resolved'] ?? false))); ?></div>
        </section>

        <section class="consent-doc-sign-grid">
          <div class="consent-doc-sign-item">
            <div class="consent-doc-meta"><?php echo h($consentSignerTypeLabel); ?></div>
            <div class="consent-doc-text"><?php echo h($consentSignerName !== '' ? $consentSignerName : '________________'); ?><?php echo $consentSignerRelation !== '' ? h(' · ' . $consentSignerRelation) : ''; ?></div>
            <?php if ($consentPatientSignatureImage !== ''): ?>
              <img class="consent-doc-sign-image" src="<?php echo h($consentPatientSignatureImage); ?>" alt="Firma del paciente o representante">
              <?php if ($consentPatientSignatureSignerName !== '' || $consentPatientSignatureSignedAt !== ''): ?>
                <div class="consent-doc-meta mt-1">
                  <?php echo h($consentPatientSignatureSignerName !== '' ? $consentPatientSignatureSignerName : $consentSignerName); ?>
                  <?php if ($consentPatientSignatureSignedAt !== ''): ?>
                    <?php echo h(' · ' . $consentPatientSignatureSignedAt); ?>
                  <?php endif; ?>
                  <?php if ($consentSignatureSourceLabel !== ''): ?>
                    <?php echo h(' · ' . $consentSignatureSourceLabel); ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="consent-doc-sign-line"></div>
            <?php endif; ?>
          </div>
          <div class="consent-doc-sign-item">
            <div class="consent-doc-meta">Médico responsable</div>
            <div class="consent-doc-text"><?php echo h($consentDoctorName); ?><?php echo $consentDoctorLicense !== '' ? h(' · Cédula: ' . $consentDoctorLicense) : ''; ?></div>
            <div class="consent-doc-sign-line"></div>
          </div>
          <div class="consent-doc-sign-item">
            <div class="consent-doc-meta">Testigo 1</div>
            <div class="consent-doc-text"><?php echo h($consentWitness1 !== '' ? $consentWitness1 : '________________'); ?></div>
            <div class="consent-doc-sign-line"></div>
          </div>
          <div class="consent-doc-sign-item">
            <div class="consent-doc-meta">Testigo 2</div>
            <div class="consent-doc-text"><?php echo h($consentWitness2 !== '' ? $consentWitness2 : '________________'); ?></div>
            <div class="consent-doc-sign-line"></div>
          </div>
        </section>

        <?php if ($consentRenderedText !== ''): ?>
          <section>
            <div class="consent-doc-section-title">Redacción legal ensamblada</div>
            <div class="consent-doc-text"><?php echo nl2br(h($consentRenderedText)); ?></div>
          </section>
        <?php endif; ?>

        <?php if (is_array($consentIdentityAttachments) && $consentIdentityAttachments !== []): ?>
          <section>
            <div class="consent-doc-section-title">Anexos de identidad del firmante</div>
            <div class="vstack gap-1">
              <?php foreach ($consentIdentityAttachments as $index => $att): ?>
                <?php
                $attUuid = trim((string)($att['document_uuid'] ?? ''));
                $attTitle = trim((string)($att['title'] ?? 'Anexo de identidad'));
                $attSource = trim((string)($att['source'] ?? ''));
                $attKind = trim((string)($att['identity_doc_label'] ?? ($att['identity_doc_kind'] ?? '')));
                $attSourceLabelMap = [
                  'consentimiento_identidad_qr_v1' => 'Captura por celular',
                  'consentimiento_identidad_local' => 'Carga local',
                ];
                $attSourceLabel = (string)($attSourceLabelMap[$attSource] ?? '');
                $attMetaParts = array_values(array_filter([
                  $attKind !== '' ? $attKind : '',
                  $attSourceLabel !== '' ? $attSourceLabel : '',
                ]));
                $attHref = $attUuid !== '' ? ('/modules/clinical/ui/viewer.php?uuid=' . rawurlencode($attUuid) . ($embed ? '&embed=1' : '')) : '';
                ?>
                <?php if ($attHref !== ''): ?>
                  <a class="btn btn-sm btn-outline-secondary text-start" href="<?php echo h($attHref); ?>" target="_blank" rel="noopener"><?php echo h(($index + 1) . '. ' . $attTitle); ?></a>
                <?php else: ?>
                  <div class="consent-doc-text"><?php echo h(($index + 1) . '. ' . $attTitle); ?></div>
                <?php endif; ?>
                <?php if ($attMetaParts !== []): ?>
                  <div class="consent-doc-meta ps-1"><?php echo h(implode(' · ', $attMetaParts)); ?></div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($consentObservations !== ''): ?>
          <section>
            <div class="consent-doc-section-title">Observaciones</div>
            <div class="consent-doc-text"><?php echo nl2br(h($consentObservations)); ?></div>
          </section>
        <?php endif; ?>
      </article>
    <?php endif; ?>

    <?php if ($externalBlockedMessage !== ''): ?>
      <div class="alert alert-warning"><?php echo h($externalBlockedMessage); ?></div>
    <?php endif; ?>

    <?php if ($detectedMode === 'image' && $mediaSrc !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Vista previa</h5></div>
        <div class="body <?php echo $isFullscreenMode ? 'fullscreen-image-wrap' : ''; ?>">
          <img src="<?php echo h($mediaSrc); ?>" alt="Documento" class="img-fluid border rounded">
          <?php if ($thumbSrc !== ''): ?>
            <div class="small text-secondary mt-2">Miniatura disponible: <?php echo h($thumbSrc); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php elseif ($renderMode === 'image' && $mediaSrc === ''): ?>
      <div class="alert alert-warning">Archivo no disponible.</div>
    <?php elseif (($detectedMode === 'pdf' || $detectedMode === 'html_external') && $mediaSrc !== ''): ?>
      <div class="mm-card mb-3 mm-viewer-frame">
        <div class="head"><h5>Vista previa</h5></div>
        <div class="body">
          <div data-role="viewer-loader" class="small text-secondary mb-2">Cargando…</div>
        </div>
        <div class="body p-0 mm-viewer-frame">
          <iframe data-role="viewer-iframe" sandbox="allow-same-origin allow-scripts allow-forms allow-downloads" src="<?php echo h($mediaSrc); ?>"></iframe>
        </div>
      </div>
    <?php elseif ($detectedMode === 'html_inline' && $htmlInline !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Vista previa</h5></div>
        <div class="body">
          <div data-role="viewer-loader" class="small text-secondary mb-2">Cargando…</div>
        </div>
        <div class="body p-0">
          <iframe data-role="viewer-iframe" sandbox="allow-same-origin allow-scripts allow-forms" srcdoc="<?php echo h($htmlInline); ?>" style="width:100%;height:72vh;border:0;"></iframe>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($renderedText !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Texto renderizado</h5></div>
        <div class="body">
          <pre class="mb-0 small"><?php echo h($renderedText); ?></pre>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($payloadJson !== '' && (!$isConsentDoc || $debugView)): ?>
      <div class="mm-card">
        <div class="head"><h5><?php echo $isConsentDoc ? 'Datos técnicos (debug)' : 'Payload (JSON)'; ?></h5></div>
        <div class="body">
          <pre class="mb-0 small"><?php echo h($payloadJson); ?></pre>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  </div>
</div>
<script>
  (function () {
    var iframe = document.querySelector('[data-role="viewer-iframe"]');
    var loader = document.querySelector('[data-role="viewer-loader"]');
    if (iframe && loader) {
      iframe.addEventListener('load', function () {
        loader.classList.add('d-none');
      });
    }
    document.addEventListener('click', function (event) {
      var printBtn = event.target && event.target.closest ? event.target.closest('[data-role="viewer-print"]') : null;
      if (!printBtn) return;
      event.preventDefault();
      var frame = document.querySelector('[data-role="viewer-iframe"]');
      if (!frame) {
        window.print();
        return;
      }
      try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
      } catch (e) {
        window.open(<?php echo json_encode($openInNewHref, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, '_blank', 'noopener');
      }
    }, true);
  })();
</script>
<?php if ($embed): ?>
<?php clinical_embed_end(); ?>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
