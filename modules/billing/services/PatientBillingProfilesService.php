<?php
declare(strict_types=1);
namespace Billing\Services;

use Billing\Repositories\PatientBillingProfilesRepository;

require_once __DIR__.'/SatCfdiCatalog.php';
require_once __DIR__.'/../repositories/PatientBillingProfilesRepository.php';

final class PatientBillingProfilesService
{
    public function __construct(private PatientBillingProfilesRepository $repo, private SatCfdiCatalog $catalog) {}

    public function catalog(): array { return $this->catalog->publicData(); }

    public function list(string $doctorId, string $patientId): array
    {
        self::identifier($patientId);
        $this->repo->requireActiveLink($doctorId, $patientId);
        return $this->repo->listActive($doctorId, $patientId);
    }

    public function create(string $doctorId, string $patientId, array $draft): array
    {
        self::identifier($patientId);
        $fields = $this->normalize($draft);
        return $this->repo->transaction(function () use ($doctorId, $patientId, $draft, $fields): array {
            $this->repo->requireActiveLink($doctorId, $patientId, true);
            $existing = $this->repo->listActive($doctorId, $patientId);
            $default = ($draft['is_default'] ?? false) === true || $existing === [];
            if ($default) $this->repo->clearDefault($doctorId, $patientId);
            $id = self::uuid();
            $this->repo->insert($id, $doctorId, $patientId, $fields, $default);
            return $this->repo->findActive($doctorId, $patientId, $id);
        });
    }

    public function update(string $doctorId, string $patientId, string $profileId, array $draft): array
    {
        self::identifier($patientId);
        self::identifier($profileId);
        $fields = $this->normalize($draft);
        return $this->repo->transaction(function () use ($doctorId, $patientId, $profileId, $draft, $fields): array {
            $this->repo->requireActiveLink($doctorId, $patientId, true);
            $this->repo->findActive($doctorId, $patientId, $profileId);
            $this->repo->update($doctorId, $patientId, $profileId, $fields);
            if (array_key_exists('is_default', $draft)) {
                if (!is_bool($draft['is_default'])) throw new \InvalidArgumentException('invalid_is_default');
                if ($draft['is_default']) $this->repo->clearDefault($doctorId, $patientId);
                $this->repo->setDefault($doctorId, $patientId, $profileId, $draft['is_default']);
            }
            return $this->repo->findActive($doctorId, $patientId, $profileId);
        });
    }

    public function makeDefault(string $doctorId, string $patientId, string $profileId): array
    {
        self::identifier($patientId);
        self::identifier($profileId);
        return $this->repo->transaction(function () use ($doctorId, $patientId, $profileId): array {
            $this->repo->requireActiveLink($doctorId, $patientId, true);
            $this->repo->findActive($doctorId, $patientId, $profileId);
            $this->repo->clearDefault($doctorId, $patientId);
            $this->repo->setDefault($doctorId, $patientId, $profileId, true);
            return $this->repo->findActive($doctorId, $patientId, $profileId);
        });
    }

    public function archive(string $doctorId, string $patientId, string $profileId): void
    {
        self::identifier($patientId);
        self::identifier($profileId);
        $this->repo->transaction(function () use ($doctorId, $patientId, $profileId): void {
            $this->repo->requireActiveLink($doctorId, $patientId, true);
            $this->repo->findActive($doctorId, $patientId, $profileId);
            $this->repo->archive($doctorId, $patientId, $profileId);
        });
    }

    private function normalize(array $draft): array
    {
        $allowed = ['alias','receiver_legal_name','rfc','fiscal_zip_code','fiscal_regime_code','default_cfdi_use_code','billing_email','is_default'];
        if (array_diff(array_keys($draft), $allowed)) throw new \InvalidArgumentException('invalid_billing_profile_fields');
        foreach ($draft as $key=>$value) {
            if ($key === 'is_default') continue;
            if ($value !== null && !is_string($value)) throw new \InvalidArgumentException('invalid_billing_profile_fields');
        }
        $alias = trim((string)($draft['alias'] ?? ''));
        $name = mb_strtoupper(trim((string)($draft['receiver_legal_name'] ?? '')), 'UTF-8');
        $rfc = mb_strtoupper(trim((string)($draft['rfc'] ?? '')), 'UTF-8');
        $zip = trim((string)($draft['fiscal_zip_code'] ?? ''));
        $regime = trim((string)($draft['fiscal_regime_code'] ?? ''));
        $use = strtoupper(trim((string)($draft['default_cfdi_use_code'] ?? '')));
        $email = trim((string)($draft['billing_email'] ?? ''));
        if (preg_match('/[\x00-\x1F\x7F]/', $alias.$name.$email)) throw new \InvalidArgumentException('invalid_billing_profile_fields');
        if ($alias === '' || mb_strlen($alias) > 80) throw new \InvalidArgumentException('invalid_alias');
        if ($name === '' || mb_strlen($name) > 254) throw new \InvalidArgumentException('invalid_receiver_legal_name');
        if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc) || !in_array(mb_strlen($rfc), [12,13], true)) throw new \InvalidArgumentException('invalid_rfc');
        if (!preg_match('/^[0-9]{5}$/D', $zip)) throw new \InvalidArgumentException('invalid_fiscal_zip_code');
        if ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) throw new \InvalidArgumentException('invalid_billing_email');
        $this->catalog->validateCombination($rfc, $regime, $use);
        if (array_key_exists('is_default', $draft) && !is_bool($draft['is_default'])) throw new \InvalidArgumentException('invalid_is_default');
        return ['alias'=>$alias, 'receiver_legal_name'=>$name, 'rfc'=>$rfc, 'fiscal_zip_code'=>$zip, 'fiscal_regime_code'=>$regime, 'default_cfdi_use_code'=>$use, 'billing_email'=>$email === '' ? null : $email];
    }

    private static function identifier(string $value): void
    {
        if (!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D', $value)) throw new \InvalidArgumentException('invalid_identifier');
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}
