<?php
declare(strict_types=1);
namespace Identity\Http;

require_once __DIR__.'/IdentityHttpComposition.php';

/** One composition authority for all Identity UI pre-authentication tokens. */
final class IdentityUiBootstrap
{
    public static function boot(): string
    {
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/html; charset=utf-8');
        try {
            // Registers Identity autoload before any Clock/CSRF construction.
            $composition = IdentityHttpComposition::fromProcessEnvironment();
            return $composition->csrf()->issuePreAuth();
        } catch (\Throwable) {
            http_response_code(503);
            header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
            echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Identidad no disponible</title>'
                .'<main><h1>Identidad temporalmente no disponible</h1><p>Intenta de nuevo más tarde.</p></main></html>';
            exit;
        }
    }
}
