<?php
namespace Agenda\Helpers\ConsultorioMap;

function normalizeCoordinate($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $num = (float)$value;
    if (!is_finite($num)) {
        return null;
    }
    return $num;
}

function isCoordinatePairValid(?float $lat, ?float $lng): bool
{
    if ($lat === null || $lng === null) {
        return false;
    }
    if ($lat < -90 || $lat > 90) {
        return false;
    }
    if ($lng < -180 || $lng > 180) {
        return false;
    }
    return true;
}

function buildGoogleMapsIframeUrlByCoordinates(?float $lat, ?float $lng, int $zoom = 17): string
{
    if (!isCoordinatePairValid($lat, $lng)) {
        return '';
    }
    $safeZoom = max(1, min(21, (int)$zoom));
    return sprintf(
        'https://www.google.com/maps?q=%s,%s&z=%d&output=embed',
        rawurlencode(number_format((float)$lat, 7, '.', '')),
        rawurlencode(number_format((float)$lng, 7, '.', '')),
        $safeZoom
    );
}

function buildGoogleMapsIframeUrlByAddress(string $address, int $zoom = 15): string
{
    $safeAddress = trim($address);
    if ($safeAddress === '') {
        return '';
    }
    $safeZoom = max(1, min(21, (int)$zoom));
    return sprintf(
        'https://www.google.com/maps?q=%s&z=%d&output=embed',
        rawurlencode($safeAddress),
        $safeZoom
    );
}

function buildConsultorioAddressCompactFromRow(array $row): string
{
    $street = trim((string)($row['calle'] ?? ''));
    $numExt = trim((string)($row['num_ext'] ?? ''));
    $streetWithNumber = trim($street . ($numExt !== '' ? ' ' . $numExt : ''));
    $parts = [
        $streetWithNumber,
        trim((string)($row['colonia'] ?? '')),
        trim((string)($row['cp'] ?? '')),
        trim((string)($row['municipio'] ?? '')),
        trim((string)($row['estado'] ?? '')),
        'México',
    ];
    $parts = array_values(array_filter(array_map(static function ($part): string {
        return trim((string)$part);
    }, $parts), static function ($part): bool {
        return $part !== '';
    }));
    return implode(', ', $parts);
}

/**
 * Construye payload reusable para mapa público (Google iframe sin API key).
 * - Fuente principal: lat/lng confirmados manualmente.
 * - Fallback visual: dirección textual (sin persistir nada).
 */
function buildConsultorioPublicMapPayload(array $row): array
{
    $lat = normalizeCoordinate($row['lat'] ?? null);
    $lng = normalizeCoordinate($row['lng'] ?? null);
    $geocodeSource = strtolower(trim((string)($row['geocode_source'] ?? '')));
    $addressCompact = buildConsultorioAddressCompactFromRow($row);
    $hasConfirmedCoordinates = (
        isCoordinatePairValid($lat, $lng)
        && in_array($geocodeSource, ['manual_adjusted', 'google_confirmed'], true)
    );

    $iframeUrl = '';
    $mapSource = 'none';
    if ($hasConfirmedCoordinates) {
        $iframeUrl = buildGoogleMapsIframeUrlByCoordinates($lat, $lng, 17);
        $mapSource = 'coordinates_confirmed';
    } elseif ($addressCompact !== '') {
        $iframeUrl = buildGoogleMapsIframeUrlByAddress($addressCompact, 15);
        $mapSource = 'address_fallback';
    }

    return [
        'lat' => $lat,
        'lng' => $lng,
        'geocode_source' => $geocodeSource,
        'address_compact' => $addressCompact,
        'public_map_has_confirmed_coords' => $hasConfirmedCoordinates,
        'public_map_iframe_url' => $iframeUrl,
        'public_map_source' => $mapSource,
    ];
}
