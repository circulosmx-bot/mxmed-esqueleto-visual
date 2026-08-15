<?php
declare(strict_types=1);

namespace Platform\Audit;

/** Safe target reference resolved from a committed backend outcome. */
final readonly class AuthoritativeAuditTarget
{
    private function __construct(
        public string $type,
        public string $id,
        public string $authority,
    ) {}

    public static function fromCommittedBackendOutcome(string $type, string $id): self
    {
        if (preg_match('/^[A-Z][A-Z0-9_]{1,63}$/D', $type) !== 1) {
            throw new \InvalidArgumentException('invalid_authoritative_target_type');
        }
        if ($id !== trim($id) || $id === '' || strlen($id) > 128 || preg_match('/[\x00-\x20\x7f]/', $id) === 1) {
            throw new \InvalidArgumentException('invalid_authoritative_target_id');
        }
        if (preg_match('/(?:password|credential|secret|bearer|otp|one[_-]?time|magic[_-]?link|raw[_-]?token|token:)/i', $id) === 1) {
            throw new \InvalidArgumentException('sensitive_material_forbidden_as_target');
        }
        return new self($type, $id, 'committed_backend_outcome');
    }
}
