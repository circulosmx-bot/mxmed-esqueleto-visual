<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class ContactDescriptor
{
    public const IDENTIFICATION = 'identification';
    public const CONTACT = 'contact';
    public const OPERATIONAL = 'operational';
    public function __construct(private string $category, private string $provenance, private string $consent, private string $channel, private string $visibility, private string $masking, private string $effectiveFrom, private ?string $revokedAt = null)
    {
        if (!in_array($category, [self::IDENTIFICATION, self::CONTACT, self::OPERATIONAL], true)) throw new \InvalidArgumentException('contact category invalid');
        foreach ([$provenance, $consent, $channel, $visibility, $masking, $effectiveFrom] as $value) if (trim($value) === '') throw new \InvalidArgumentException('contact descriptor incomplete');
    }
    public function category(): string { return $this->category; }
    public function toArray(): array { return ['category' => $this->category, 'provenance' => $this->provenance, 'consent' => $this->consent, 'channel' => $this->channel, 'visibility' => $this->visibility, 'masking' => $this->masking, 'effective_from' => $this->effectiveFrom, 'revoked_at' => $this->revokedAt]; }
}

final class PatientIdentityMatch
{
    public const EXACT = 'exact';
    public const PROBABLE = 'probable';
    public const NO_MATCH = 'no_match';
    private function __construct(private string $kind, private array $evidence, private bool $warningBeforeCreate, private bool $autoMerge, private bool $curpRequired) {}
    public static function exact(array $evidence): self { return new self(self::EXACT, $evidence, true, false, false); }
    public static function probable(array $evidence): self { return new self(self::PROBABLE, $evidence, true, false, false); }
    public static function noMatch(): self { return new self(self::NO_MATCH, [], false, false, false); }
    public function kind(): string { return $this->kind; }
    public function evidence(): array { return $this->evidence; }
    public function warningBeforeCreate(): bool { return $this->warningBeforeCreate; }
    public function autoMerge(): bool { return $this->autoMerge; }
    public function curpRequired(): bool { return $this->curpRequired; }
}
