<?php
declare(strict_types=1);

namespace Subscriptions\Services;

final class StripeWebhookSignatureVerifier
{
    private const PROVIDER = 'stripe';
    private const SIGNATURE_VERSION = 'v1';
    private const DEFAULT_TOLERANCE_SECONDS = 300;

    public function verify(array $input): array
    {
        $rawBody = (string)($input['raw_body'] ?? '');
        $signatureHeader = trim((string)($input['signature_header'] ?? ''));
        $webhookSecret = trim((string)($input['webhook_secret'] ?? ''));
        $toleranceSeconds = (int)($input['tolerance_seconds'] ?? self::DEFAULT_TOLERANCE_SECONDS);
        if ($toleranceSeconds < 1) {
            $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS;
        }

        if ($webhookSecret === '') {
            return $this->error(
                'stripe_webhook_secret_missing',
                'stripe webhook secret is not configured',
                500,
                null,
                $toleranceSeconds,
                $signatureHeader
            );
        }

        if ($signatureHeader === '') {
            return $this->error(
                'stripe_webhook_signature_missing',
                'stripe webhook signature is required',
                401,
                null,
                $toleranceSeconds,
                $signatureHeader
            );
        }

        $parsed = $this->parseSignatureHeader($signatureHeader);
        $timestamp = $parsed['timestamp'];
        $signatures = $parsed['signatures'];
        if ($timestamp === null || !ctype_digit($timestamp) || $signatures === []) {
            return $this->invalid($timestamp, $toleranceSeconds, $signatureHeader);
        }

        if (abs(time() - (int)$timestamp) > $toleranceSeconds) {
            return $this->invalid($timestamp, $toleranceSeconds, $signatureHeader);
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $webhookSecret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return [
                    'ok' => true,
                    'provider' => self::PROVIDER,
                    'verified' => true,
                    'timestamp' => (int)$timestamp,
                    'signature_version' => self::SIGNATURE_VERSION,
                    'log_context' => [
                        'provider' => self::PROVIDER,
                        'timestamp' => (int)$timestamp,
                        'tolerance_seconds' => $toleranceSeconds,
                        'signature_header_length' => strlen($signatureHeader),
                    ],
                ];
            }
        }

        return $this->invalid($timestamp, $toleranceSeconds, $signatureHeader);
    }

    private function parseSignatureHeader(string $signatureHeader): array
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pieces = explode('=', trim($part), 2);
            if (count($pieces) !== 2) {
                continue;
            }

            $key = trim($pieces[0]);
            $value = trim($pieces[1]);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === self::SIGNATURE_VERSION && $value !== '') {
                $signatures[] = $value;
            }
        }

        return [
            'timestamp' => $timestamp,
            'signatures' => $signatures,
        ];
    }

    private function invalid(?string $timestamp, int $toleranceSeconds, string $signatureHeader): array
    {
        return $this->error(
            'stripe_webhook_signature_invalid',
            'stripe webhook signature is invalid',
            401,
            $timestamp,
            $toleranceSeconds,
            $signatureHeader
        );
    }

    private function error(
        string $code,
        string $message,
        int $httpStatus,
        ?string $timestamp,
        int $toleranceSeconds,
        string $signatureHeader
    ): array {
        $logContext = [
            'provider' => self::PROVIDER,
            'error_code' => $code,
            'tolerance_seconds' => $toleranceSeconds,
            'signature_header_length' => strlen($signatureHeader),
        ];

        if ($timestamp !== null && ctype_digit($timestamp)) {
            $logContext['timestamp'] = (int)$timestamp;
        }

        return [
            'ok' => false,
            'provider' => self::PROVIDER,
            'verified' => false,
            'error_code' => $code,
            'code' => $code,
            'http_status' => $httpStatus,
            'safe_message' => $message,
            'message' => $message,
            'log_context' => $logContext,
        ];
    }
}
