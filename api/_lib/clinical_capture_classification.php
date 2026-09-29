<?php
declare(strict_types=1);

/** Existing binary document types only; generated notes/orders/results keep their own authority. */
function clinical_capture_classifications(): array
{
    return [
        'clinical_image' => ['id' => 'clinical_image', 'label' => 'Imagen clínica', 'document_type' => 'image',
            'icon' => 'image', 'mime_types' => ['image/jpeg', 'image/png', 'image/webp']],
        'clinical_pdf' => ['id' => 'clinical_pdf', 'label' => 'Documento clínico (PDF)', 'document_type' => 'pdf',
            'icon' => 'picture_as_pdf', 'mime_types' => ['application/pdf']],
    ];
}

function clinical_capture_classification_context(string $id): string
{
    if (!isset(clinical_capture_classifications()[$id])) throw new InvalidArgumentException('CAPTURE_CLASSIFICATION_INVALID');
    return 'step6_capture_r4:' . $id;
}

function clinical_capture_classification_from_context(string $context): ?array
{
    if (!str_starts_with(strtolower(trim($context)), 'step6_capture_r4:')) return null;
    $id = substr(trim($context), strlen('step6_capture_r4:'));
    $item = clinical_capture_classifications()[$id] ?? null;
    if ($item === null || $context !== clinical_capture_classification_context($id)) {
        throw new InvalidArgumentException('CAPTURE_CLASSIFICATION_INVALID');
    }
    return $item;
}

/** The returned URL is the complete QR authority, never reconstructed by the phone. */
function clinical_capture_mobile_url(string $path): string
{
    $origin = trim((string)(getenv('MXMED_CAPTURE_PUBLIC_ORIGIN') ?: ''));
    if ($origin === '') {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if (!preg_match('/^(?:[a-zA-Z0-9.-]+|\[[a-fA-F0-9:]+\])(?::[0-9]{1,5})?$/D', $host)) {
            throw new RuntimeException('CAPTURE_ORIGIN_NOT_CONFIGURED');
        }
        $origin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $host;
    }
    $parts = parse_url($origin);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)) {
        throw new RuntimeException('CAPTURE_ORIGIN_NOT_CONFIGURED');
    }
    return rtrim($origin, '/') . $path;
}
