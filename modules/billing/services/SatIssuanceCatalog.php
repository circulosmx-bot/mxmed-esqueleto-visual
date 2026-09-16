<?php
declare(strict_types=1);
namespace Billing\Services;

/** Enumerations extracted unchanged from the official SAT catCFDI.xsd. */
final class SatIssuanceCatalog
{
    private array $data;
    private array $sets = [];

    public function __construct(?string $path = null)
    {
        $bytes = file_get_contents($path ?? __DIR__.'/../catalog/sat-cfdi-xsd-codes-20260916.json.gz');
        $raw = $bytes === false ? false : gzdecode($bytes);
        $data = $raw === false ? null : json_decode($raw, true);
        if (!is_array($data) || !is_array($data['groups'] ?? null)) throw new \RuntimeException('sat_issuance_catalog_unavailable');
        $this->data = $data;
    }

    public function requireCode(string $group, string $code): void
    {
        if (!isset($this->data['groups'][$group])) throw new \InvalidArgumentException('invalid_sat_catalog_group');
        $this->sets[$group] ??= array_fill_keys($this->data['groups'][$group], true);
        if (!isset($this->sets[$group][$code])) throw new \InvalidArgumentException('invalid_'.$group);
    }

    public function publicData(): array
    {
        return ['source'=>$this->data['source'], 'source_url'=>$this->data['source_url'], 'verified_on'=>$this->data['verified_on'],
            'payment_methods'=>$this->data['groups']['c_MetodoPago'], 'payment_forms'=>$this->data['groups']['c_FormaPago'],
            'currencies'=>$this->data['groups']['c_Moneda'], 'tax_objects'=>$this->data['groups']['c_ObjetoImp']];
    }
}
