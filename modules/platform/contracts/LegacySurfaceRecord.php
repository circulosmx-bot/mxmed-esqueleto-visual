<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class LegacySurfaceRecord
{
    private string $surface;
    private string $status;
    private string $risk;
    private string $owningDomain;
    private string $remediationState;
    private string $deploymentEffect;
    private string $evidence;
    private string $notes;
    private array $extra;

    /** @param array<string,mixed> $extra */
    public function __construct(
        string $surface,
        string $status,
        string $risk,
        string $owningDomain,
        string $remediationState,
        string $deploymentEffect,
        string $evidence,
        string $notes = '',
        array $extra = []
    ) {
        $surface = trim($surface);
        $owningDomain = trim($owningDomain);
        $remediationState = trim($remediationState);
        $deploymentEffect = trim($deploymentEffect);
        $evidence = trim($evidence);
        if ($surface === '' || $owningDomain === '' || $remediationState === '' || $deploymentEffect === '' || $evidence === '') {
            throw new \InvalidArgumentException('legacy_surface_fields_required');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $surface . $owningDomain . $remediationState . $deploymentEffect . $evidence) === 1) {
            throw new \InvalidArgumentException('legacy_surface_control_character');
        }
        $status = LegacyContainmentStatus::assertValid($status);
        if (!in_array($risk, RiskLevel::all(), true)) {
            throw new \InvalidArgumentException('invalid_legacy_surface_risk');
        }
        $this->surface = $surface;
        $this->status = $status;
        $this->risk = $risk;
        $this->owningDomain = $owningDomain;
        $this->remediationState = $remediationState;
        $this->deploymentEffect = $deploymentEffect;
        $this->evidence = $evidence;
        $this->notes = trim($notes);
        $this->extra = $extra;
    }

    /** @param array<string,mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self(
            (string)($value['surface'] ?? ''),
            (string)($value['status'] ?? ''),
            (string)($value['risk'] ?? ''),
            (string)($value['owning_domain'] ?? ''),
            (string)($value['remediation_state'] ?? ''),
            (string)($value['deployment_effect'] ?? ''),
            (string)($value['evidence'] ?? ''),
            (string)($value['notes'] ?? ''),
            array_diff_key($value, array_flip(['surface', 'status', 'risk', 'owning_domain', 'remediation_state', 'deployment_effect', 'evidence', 'notes']))
        );
    }

    public function surface(): string { return $this->surface; }
    public function status(): string { return $this->status; }
    public function risk(): string { return $this->risk; }
    public function owningDomain(): string { return $this->owningDomain; }
    public function remediationState(): string { return $this->remediationState; }
    public function deploymentEffect(): string { return $this->deploymentEffect; }
    public function evidence(): string { return $this->evidence; }
    public function notes(): string { return $this->notes; }
    /** @return array<string,mixed> */
    public function extra(): array { return $this->extra; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return array_merge([
            'surface' => $this->surface,
            'status' => $this->status,
            'risk' => $this->risk,
            'owning_domain' => $this->owningDomain,
            'remediation_state' => $this->remediationState,
            'deployment_effect' => $this->deploymentEffect,
            'evidence' => $this->evidence,
            'notes' => $this->notes,
        ], $this->extra);
    }
}

