<?php
declare(strict_types=1);
namespace Billing\Services;

final class SatCfdiCatalog
{
    private array $data;

    public function __construct(?string $path = null)
    {
        $raw = file_get_contents($path ?? __DIR__.'/../catalog/sat-cfdi-4-20260903.json');
        $data = $raw === false ? null : json_decode($raw, true);
        if (!is_array($data) || empty($data['regimes']) || empty($data['uses'])) {
            throw new \RuntimeException('sat_catalog_unavailable');
        }
        $this->data = $data;
    }

    public function publicData(): array
    {
        return [
            'source' => $this->data['source'],
            'source_url' => $this->data['source_url'],
            'verified_on' => $this->data['verified_on'],
            'regimes' => $this->activeRows('regimes'),
            'uses' => $this->activeRows('uses'),
        ];
    }

    public function validateCombination(string $rfc, string $regimeCode, string $useCode): void
    {
        $kind = mb_strlen($rfc, 'UTF-8') === 12 ? 'person_legal' : 'person_physical';
        $regime = $this->findActive('regimes', $regimeCode);
        if ($regime === null || !$regime[$kind]) throw new \InvalidArgumentException('invalid_fiscal_regime_code');
        $use = $this->findActive('uses', $useCode);
        if ($use === null || !$use[$kind] || !in_array($regimeCode, $use['allowed_regimes'], true)) {
            throw new \InvalidArgumentException('invalid_cfdi_use_code');
        }
    }

    private function activeRows(string $group): array
    {
        $today = gmdate('Y-m-d');
        return array_values(array_filter($this->data[$group], static fn(array $row): bool =>
            ($row['valid_from'] === null || $row['valid_from'] <= $today)
            && ($row['valid_to'] === null || $row['valid_to'] >= $today)
        ));
    }

    private function findActive(string $group, string $code): ?array
    {
        foreach ($this->activeRows($group) as $row) {
            if ($row['code'] === $code) return $row;
        }
        return null;
    }
}
