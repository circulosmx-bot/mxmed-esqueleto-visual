<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;

/** Adapter over Consultorio's catalog. Its natural geographic keys are labels;
 * catalog row IDs identify postal colonies, not states or cities. */
final class Geography
{
    public function __construct(private \PDO $pdo) {}
    public function locations(): array
    {
        return $this->pdo->query("SELECT DISTINCT estado AS state_key, municipio AS city_key FROM catalog_cp_colonias WHERE is_active=1 AND estado<>'' AND municipio<>'' ORDER BY estado,municipio")->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function validate(array $profile): void
    {
        $state=$profile['operation_state'];$city=$profile['operation_city'];
        if($state===''&&$city==='')return;
        $q=$this->pdo->prepare('SELECT 1 FROM catalog_cp_colonias WHERE is_active=1 AND BINARY estado=BINARY ? AND BINARY municipio=BINARY ? LIMIT 1');
        $q->execute([$state,$city]);
        if(!$q->fetchColumn())throw new \InvalidArgumentException('admission_geography_invalid');
    }
}
