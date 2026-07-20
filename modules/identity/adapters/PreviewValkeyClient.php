<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\ValkeySessionClientPort;

/**
 * Tiny RESP client used only by the localhost Gate 4D preview composition.
 * It deliberately opens a short-lived connection per command; Valkey remains
 * the stateful session authority between HTTP requests.
 */
final class PreviewValkeyClient implements ValkeySessionClientPort
{
    public function __construct(private string $host, private int $port, private float $timeoutSeconds = 1.0)
    {
        if ($this->host !== '127.0.0.1' || $this->port < 1 || $this->port > 65535) {
            throw new \InvalidArgumentException('preview_valkey_must_bind_localhost');
        }
    }

    public function ping(): bool
    {
        return $this->command(['PING']) === 'PONG';
    }

    public function get(string $key): ?string
    {
        $value = $this->command(['GET', $key]);
        return $value === null ? null : (string)$value;
    }

    public function set(string $key, string $value, int $ttlSeconds): bool
    {
        return $this->command(['SET', $key, $value, 'EX', (string)max(1, $ttlSeconds)]) === 'OK';
    }

    public function delete(string $key): bool
    {
        return (int)$this->command(['DEL', $key]) > 0;
    }

    /** @param list<string> $parts */
    private function command(array $parts): mixed
    {
        $errno = 0;
        $error = '';
        $socket = @fsockopen($this->host, $this->port, $errno, $error, $this->timeoutSeconds);
        if (!is_resource($socket)) throw new SessionStoreUnavailableException('preview_valkey_unavailable');
        stream_set_timeout($socket, (int)$this->timeoutSeconds, (int)(($this->timeoutSeconds - floor($this->timeoutSeconds)) * 1000000));
        $payload = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) $payload .= '$' . strlen($part) . "\r\n" . $part . "\r\n";
        if (fwrite($socket, $payload) !== strlen($payload)) {
            fclose($socket);
            throw new SessionStoreUnavailableException('preview_valkey_write_failed');
        }
        $result = $this->readResponse($socket);
        fclose($socket);
        return $result;
    }

    private function readResponse($socket): mixed
    {
        $prefix = fgetc($socket);
        if ($prefix === false) throw new SessionStoreUnavailableException('preview_valkey_read_failed');
        $line = fgets($socket);
        if ($line === false) throw new SessionStoreUnavailableException('preview_valkey_read_failed');
        $line = rtrim($line, "\r\n");
        return match ($prefix) {
            '+' => $line,
            '-' => throw new SessionStoreUnavailableException('preview_valkey_error'),
            ':' => (int)$line,
            '$' => $line === '-1' ? null : $this->readBulk($socket, (int)$line),
            default => throw new SessionStoreUnavailableException('preview_valkey_protocol_error'),
        };
    }

    private function readBulk($socket, int $length): string
    {
        $value = '';
        while (strlen($value) < $length) {
            $chunk = fread($socket, $length - strlen($value));
            if ($chunk === false || $chunk === '') throw new SessionStoreUnavailableException('preview_valkey_read_failed');
            $value .= $chunk;
        }
        if (fread($socket, 2) !== "\r\n") throw new SessionStoreUnavailableException('preview_valkey_protocol_error');
        return $value;
    }
}
