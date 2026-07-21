<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\LegacySurfaceRecord;
use Platform\Contracts\LegacyContainmentStatus;

final class LegacyContainmentRegistry
{
    /** @var array<string,mixed> */
    private array $manifest;
    /** @var array<string,LegacySurfaceRecord> */
    private array $records = [];

    /** @param array<string,mixed> $manifest */
    public function __construct(array $manifest)
    {
        $this->manifest = $manifest;
        $this->validateAndLoad();
    }

    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('legacy_containment_manifest_invalid_json', 0, $exception);
        }
        if (!is_array($decoded)) throw new \InvalidArgumentException('legacy_containment_manifest_invalid_shape');
        return new self($decoded);
    }

    public function schemaVersion(): string { return (string)$this->manifest['schema_version']; }
    public function authoritativeGate(): string { return (string)$this->manifest['authoritative_gate']; }
    public function manifestStatus(): string { return (string)$this->manifest['status']; }
    /** @return list<LegacySurfaceRecord> */
    public function records(): array { return array_values($this->records); }
    public function find(string $surface): ?LegacySurfaceRecord { return $this->records[$surface] ?? null; }
    /** @return array<string,mixed> */
    public function manifest(): array { return $this->manifest; }

    private function validateAndLoad(): void
    {
        if ((string)($this->manifest['schema_version'] ?? '') !== '1') throw new \InvalidArgumentException('legacy_containment_schema_version_invalid');
        if (trim((string)($this->manifest['authoritative_gate'] ?? '')) === '') throw new \InvalidArgumentException('legacy_containment_authoritative_gate_required');
        if (!in_array((string)($this->manifest['status'] ?? ''), [PlatformFoundationStatus::NO_GO_LEGACY_BLOCKERS_PRESENT], true)) {
            throw new \InvalidArgumentException('legacy_containment_manifest_status_invalid');
        }
        $surfaces = $this->manifest['surfaces'] ?? null;
        if (!is_array($surfaces) || $surfaces === []) throw new \InvalidArgumentException('legacy_containment_surfaces_required');
        foreach ($surfaces as $surfaceValue) {
            if (!is_array($surfaceValue)) throw new \InvalidArgumentException('legacy_surface_record_invalid');
            $record = LegacySurfaceRecord::fromArray($surfaceValue);
            if (isset($this->records[$record->surface()])) throw new \InvalidArgumentException('legacy_surface_duplicate');
            $this->records[$record->surface()] = $record;
        }
        if (!in_array($this->manifest['risk'] ?? null, \Platform\Contracts\RiskLevel::all(), true)) {
            throw new \InvalidArgumentException('legacy_containment_manifest_risk_invalid');
        }
        if (!isset($this->manifest['evidence'], $this->manifest['notes'])) throw new \InvalidArgumentException('legacy_containment_manifest_metadata_required');
        foreach ($this->records as $record) LegacyContainmentStatus::assertValid($record->status());
    }
}

final class PlatformFoundationStatus
{
    public const NO_GO_LEGACY_BLOCKERS_PRESENT = 'NO_GO_LEGACY_BLOCKERS_PRESENT';
}

