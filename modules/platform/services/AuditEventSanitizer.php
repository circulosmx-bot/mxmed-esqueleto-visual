<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\AuditEventEnvelope;
use Platform\Contracts\AuditEventReference;

/** Pure allow-list sanitizer for audit references and metadata. */
final class AuditEventSanitizer
{
    /** @var list<string> */
    private const SENSITIVE_KEY_PARTS = ['password', 'secret', 'token', 'cookie', 'authorization', 'clinical_text', 'note', 'diagnosis', 'prescription', 'document', 'file', 'payload', 'body', 'headers', 'session', 'account', 'patient_name', 'email', 'phone', 'address'];

    public function sanitize(AuditEventReference $event): AuditEventReference
    {
        return new AuditEventReference($event->eventName(), $event->riskLevel(), $event->realActor(), $event->effectiveActor(), $event->affectedSubject(), $event->correlationId(), $event->requestId(), $event->result(), $this->sanitizeMetadata($event->metadata()));
    }

    /** @param array<string,mixed> $metadata @return array<string,string|int|bool|null> */
    public function sanitizeMetadata(array $metadata): array
    {
        $allowed = array_fill_keys(AuditEventEnvelope::allowedMetadataKeys(), true);
        if (count($metadata) > count($allowed)) throw new \InvalidArgumentException('audit_metadata_limit_exceeded');
        $clean = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) throw new \InvalidArgumentException('audit_metadata_key_not_allowed');
            $normalizedKey = strtolower(trim($key));
            foreach (self::SENSITIVE_KEY_PARTS as $sensitivePart) {
                if (str_contains($normalizedKey, $sensitivePart) && $normalizedKey !== 'authorization_plane') throw new \InvalidArgumentException('sensitive_audit_metadata_key');
            }
            if (!isset($allowed[$normalizedKey])) throw new \InvalidArgumentException('audit_metadata_key_not_allowed');
            if (array_key_exists($normalizedKey, $clean)) throw new \InvalidArgumentException('duplicate_audit_metadata_key');
            if (!is_string($value) && !is_int($value) && !is_bool($value) && $value !== null) throw new \InvalidArgumentException('nested_audit_metadata_rejected');
            if (is_string($value)) {
                if (strlen($value) > 256 || preg_match('/[\r\n\x00-\x1F\x7F]/', $value) === 1) throw new \InvalidArgumentException('unsafe_audit_metadata_value');
                $value = trim($value);
                if ($value === '') throw new \InvalidArgumentException('empty_audit_metadata_value');
            }
            $clean[$normalizedKey] = $value;
        }
        ksort($clean, SORT_STRING);
        return $clean;
    }
}
