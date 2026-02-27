<?php
// modules/clinical/ui/viewer.php

function get_api_base(): string
{
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

    return $proto . '://' . $host;
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
                unset($query['doc_uuid']);
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
            unset($query['doc_uuid']);
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
    if ($raw === false) {
        return ['ok' => false, 'error' => 'fetch_failed', 'message' => 'No se pudo consultar el documento.'];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'invalid_json', 'message' => 'Respuesta inválida del endpoint de documentos.'];
    }

    return $decoded;
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

$uuid = trim((string)($_GET['uuid'] ?? ''));
$returnTo = validate_return_to((string)($_GET['return_to'] ?? ''));
$returnToClean = $returnTo !== null ? normalize_return_to($returnTo) : '';
$backHref = $returnToClean !== '' ? $returnToClean : 'javascript:history.back()';
$errorMessage = '';
$document = null;

if ($uuid !== '') {
    $url = get_api_base() . '/api/clinical/index.php/documents/' . rawurlencode($uuid);
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

$docType = $document ? (string)($document['document_type'] ?? '-') : '-';
$title = $document ? (string)($document['title'] ?? '-') : '-';
$date = $document ? (string)($document['ui']['event_datetime'] ?? ($document['timestamps']['created_at'] ?? '-')) : '-';
$summary = $document ? (string)($document['content']['summary'] ?? '-') : '-';
$content = $document && is_array($document['content'] ?? null) ? $document['content'] : [];
$payload = $document && is_array($content['payload'] ?? null) ? $content['payload'] : [];
$payloadJson = $payload ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
$renderedText = $document ? (string)($content['rendered_text'] ?? '') : '';

$fileMeta = is_array($payload['file'] ?? null) ? $payload['file'] : [];
$optimizedMeta = is_array($fileMeta['optimized'] ?? null) ? $fileMeta['optimized'] : [];
$thumbMeta = is_array($fileMeta['thumb'] ?? null) ? $fileMeta['thumb'] : [];
$mimeType = strtolower(first_non_empty_string([$optimizedMeta, $fileMeta, $document, $content, $payload], ['mime', 'mime_type', 'content_type', 'type', 'media_type']));
$mediaSrc = first_non_empty_string([$optimizedMeta, $fileMeta, $payload, $content], ['path', 'url', 'src', 'file_url', 'pdf_url', 'image_url']);
$thumbSrc = first_non_empty_string([$thumbMeta], ['path', 'url', 'src']);
$htmlInline = trim((string)($payload['html'] ?? ''));

$currentHost = strtolower((string)parse_url(get_api_base(), PHP_URL_HOST));
$externalIframeAllowlist = []; // viewer v0.1: dominios explícitos para iframe externo.
$externalAllowed = $mediaSrc !== '' ? is_allowed_external_url($mediaSrc, $externalIframeAllowlist, $currentHost) : false;
$externalBlockedMessage = '';

$detectedMode = 'json';
if ($htmlInline !== '') {
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

if (($detectedMode === 'pdf' || $detectedMode === 'html_external') && $mediaSrc !== '' && !$externalAllowed) {
    $externalBlockedMessage = 'La URL externa no está permitida por la allowlist del viewer.';
    $detectedMode = 'json';
}

$openInNewHref = $mediaSrc !== '' && $externalAllowed ? $mediaSrc : ((string)($_SERVER['REQUEST_URI'] ?? '/modules/clinical/ui/viewer.php'));

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
  .clinical-viewer .viewer-sticky-head{
    position: sticky;
    top: 0;
    z-index: 3;
    background: #fff;
    border-bottom: 1px solid rgba(0,0,0,.08);
    padding-bottom: .5rem;
    margin-bottom: .75rem;
  }
</style>
<div class="<?php echo $embed ? 'py-1' : 'container py-4'; ?>">
  <div class="clinical-viewer">
  <div class="viewer-sticky-head">
    <div class="d-flex justify-content-between align-items-center mt-2">
      <div>
        <h1 class="h5 mb-0">Document Viewer</h1>
        <?php if ($title !== '-' && $uuid !== ''): ?>
          <div class="text-secondary small"><?php echo h($title); ?></div>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($backHref); ?>">Volver</a>
        <a class="btn btn-outline-primary btn-sm" href="<?php echo h($openInNewHref); ?>" target="_blank" rel="noopener">Abrir en pestaña</a>
      </div>
    </div>
  </div>

  <p class="text-secondary mb-3">uuid: <code><?php echo h($uuid !== '' ? $uuid : '-'); ?></code></p>

  <?php if ($uuid === ''): ?>
    <div class="alert alert-warning">uuid requerido.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php else: ?>
    <div class="mm-card mb-3">
      <div class="body small">
        <div><strong>Tipo:</strong> <?php echo h($docType); ?></div>
        <div><strong>Fecha:</strong> <?php echo h($date); ?></div>
        <div><strong>Summary:</strong> <?php echo h($summary); ?></div>
        <div><strong>Viewer mode:</strong> <?php echo h($detectedMode); ?></div>
      </div>
    </div>

    <?php if ($externalBlockedMessage !== ''): ?>
      <div class="alert alert-warning"><?php echo h($externalBlockedMessage); ?></div>
    <?php endif; ?>

    <?php if ($detectedMode === 'image' && $mediaSrc !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Vista previa</h5></div>
        <div class="body">
          <img src="<?php echo h($mediaSrc); ?>" alt="Documento" class="img-fluid border rounded">
          <?php if ($thumbSrc !== ''): ?>
            <div class="small text-secondary mt-2">Miniatura disponible: <?php echo h($thumbSrc); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php elseif (($detectedMode === 'pdf' || $detectedMode === 'html_external') && $mediaSrc !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Vista previa</h5></div>
        <div class="body">
          <div data-role="viewer-loader" class="small text-secondary mb-2">Cargando…</div>
        </div>
        <div class="body p-0">
          <iframe data-role="viewer-iframe" sandbox="allow-same-origin allow-scripts allow-forms allow-downloads" src="<?php echo h($mediaSrc); ?>" style="width:100%;height:72vh;border:0;"></iframe>
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

    <?php if ($payloadJson !== ''): ?>
      <div class="mm-card">
        <div class="head"><h5>Payload (JSON)</h5></div>
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
    if (!iframe || !loader) return;
    iframe.addEventListener('load', function () {
      loader.classList.add('d-none');
    });
  })();
</script>
<?php if ($embed): ?>
<?php clinical_embed_end(); ?>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
