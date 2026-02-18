<?php
declare(strict_types=1);

if (!function_exists('is_embed_request')) {
    function is_embed_request(): bool
    {
        return isset($_GET['embed']) && $_GET['embed'] === '1';
    }
}

if (!function_exists('carry_embed_params')) {
    function carry_embed_params(array $extra = []): string
    {
        $params = $extra;
        if (is_embed_request()) {
            $params['embed'] = '1';
        }
        return http_build_query($params);
    }
}

if (!function_exists('carry_embed_hidden_input')) {
    function carry_embed_hidden_input(): string
    {
        if (!is_embed_request()) {
            return '';
        }
        return '<input type="hidden" name="embed" value="1">';
    }
}

if (!function_exists('clinical_embed_start')) {
    function clinical_embed_start(): void
    {
        static $stylesPrinted = false;
        if (!$stylesPrinted) {
            echo '<style>.clinical-embed{background:transparent;}.clinical-embed .clinical-panel{background:#fff;border:1px solid var(--mm-borde-input,#00B0C5);border-radius:.9rem;box-shadow:0 12px 28px rgba(0,0,0,.08);padding:.9rem;}</style>';
            $stylesPrinted = true;
        }
        echo '<div class="clinical-embed p-2"><div class="clinical-panel">';
    }
}

if (!function_exists('clinical_embed_end')) {
    function clinical_embed_end(): void
    {
        echo '</div></div>';
    }
}
