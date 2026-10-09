<?php
declare(strict_types=1);

/** Shared legal-document presentation contract; missing values retain historical output. */
function clinical_legal_document_professional_header_mode(array $payload): string
{
    $presentation = is_array($payload['presentation'] ?? null) ? $payload['presentation'] : [];
    return ($presentation['professional_header'] ?? 'shown') === 'hidden' ? 'hidden' : 'shown';
}

function clinical_legal_document_presentation_valid(array $payload): bool
{
    if (!array_key_exists('presentation', $payload)) return true;
    $presentation = $payload['presentation'];
    return is_array($presentation)
        && ($presentation['version'] ?? null) === 1
        && in_array($presentation['professional_header'] ?? null, ['shown', 'hidden'], true);
}
