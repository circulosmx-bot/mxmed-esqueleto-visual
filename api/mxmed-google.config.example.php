<?php
/**
 * Config local opcional para geocoding Google.
 * NO versionar con clave real.
 *
 * Copia este archivo a:
 *   api/mxmed-google.config.php
 *
 * y coloca la clave real.
 */
return [
    // Backend proxy para Geocoding (no se expone al frontend)
    'google_geocode_api_key' => 'REPLACE_WITH_GOOGLE_GEOCODE_KEY',

    // Frontend Google Maps JavaScript API (sí se expone en navegador).
    // Requiere restricciones por HTTP referrer.
    'google_maps_js_api_key' => 'REPLACE_WITH_GOOGLE_MAPS_JS_KEY',
];
