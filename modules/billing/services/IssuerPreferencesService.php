<?php
declare(strict_types=1);
namespace Billing\Services;

use PDO;

require_once __DIR__.'/ExactInvoiceMath.php';
require_once __DIR__.'/IssuerProfileService.php';

/** Physician-scoped UX defaults. Never a SAT classification or invoice snapshot authority. */
final class IssuerPreferencesService
{
    public function __construct(private PDO $pdo, private IssuerProfileService $issuers) {}

    public function list(string $doctorId): array
    {
        $q=$this->pdo->prepare('SELECT p.issuer_profile_id,p.default_concept_description,p.habitual_unit_price,p.invoice_logo_mode FROM billing_issuer_preferences p JOIN billing_issuer_profiles i ON i.issuer_profile_id=p.issuer_profile_id AND i.doctor_id=p.doctor_id WHERE p.doctor_id=? AND i.archived_at IS NULL');
        $q->execute([$doctorId]);
        $rows=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$rows[$row['issuer_profile_id']]=$row;
        return $rows;
    }

    public function save(string $doctorId,string $issuerId,array $input): array
    {
        $this->issuers->get($doctorId,$issuerId);
        if(array_diff(array_keys($input),['default_concept_description','habitual_unit_price','invoice_logo_mode'])
            || !is_string($input['default_concept_description']??null)
            || !is_string($input['invoice_logo_mode']??null))throw new \InvalidArgumentException('invalid_issuer_preferences');
        $description=trim($input['default_concept_description']);
        if($description===''||mb_strlen($description)>1000||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$description))throw new \InvalidArgumentException('invalid_default_concept_description');
        $price=$input['habitual_unit_price']??null;
        if($price!==null)$price=ExactInvoiceMath::decimal($price);
        $mode=$input['invoice_logo_mode'];
        if(!in_array($mode,['PROFESSIONAL_LOGO','NONE'],true))throw new \InvalidArgumentException('invalid_invoice_logo_mode');
        $q=$this->pdo->prepare('INSERT INTO billing_issuer_preferences (issuer_profile_id,doctor_id,default_concept_description,habitual_unit_price,invoice_logo_mode) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE default_concept_description=VALUES(default_concept_description),habitual_unit_price=VALUES(habitual_unit_price),invoice_logo_mode=VALUES(invoice_logo_mode),updated_at=CURRENT_TIMESTAMP(6)');
        $q->execute([$issuerId,$doctorId,$description,$price,$mode]);
        return $this->list($doctorId)[$issuerId];
    }

    public function suggestion(string $doctorId): string
    {
        // Only governed, verified active credentials can supply profession/specialty.
        $q=$this->pdo->prepare("SELECT c.professional_area_label,p.primary_specialty_credential_id FROM profiles_doctors p LEFT JOIN profiles_doctor_credentials c ON c.doctor_id=p.doctor_id AND c.credential_type='PROFESSIONAL' AND c.verification_status='VERIFIED' AND c.lifecycle_status='ACTIVE' WHERE p.doctor_id=?");
        $q->execute([$doctorId]);$row=$q->fetch(PDO::FETCH_ASSOC)?:[];
        $area=mb_strtolower(trim((string)($row['professional_area_label']??'')),'UTF-8');
        $fixed=[
            'cirujano dentista'=>'Consulta odontológica',
            'licenciado en psicología'=>'Servicios profesionales de psicología',
            'licenciada en psicología'=>'Servicios profesionales de psicología',
            'licenciado en nutrición'=>'Servicios profesionales de nutrición',
            'licenciada en nutrición'=>'Servicios profesionales de nutrición',
            'químico farmacobiólogo'=>'Análisis clínicos',
        ];
        if(isset($fixed[$area]))return $fixed[$area];
        if(!in_array($area,['médico cirujano','médica cirujana','médico general','médica general','médico cirujano y partero','médica cirujana y partera'],true))return 'Servicios profesionales';
        $id=$row['primary_specialty_credential_id']??null;
        if($id!==null){
            $s=$this->pdo->prepare("SELECT professional_area_label FROM profiles_doctor_credentials WHERE doctor_id=? AND credential_id=? AND credential_type='SPECIALTY' AND verification_status='VERIFIED' AND lifecycle_status='ACTIVE'");
            $s->execute([$doctorId,$id]);$specialty=trim((string)($s->fetchColumn()?:''));
            if($specialty!==''&&mb_strlen($specialty)<=190)return 'Consulta médica de '.mb_strtolower($specialty,'UTF-8');
        }
        return 'Honorarios médicos';
    }

    public function professionalLogoAvailable(string $doctorId): bool
    {
        $q=$this->pdo->prepare("SELECT 1 FROM profiles_doctors p JOIN media_assets m ON m.public_url=p.logo_url AND m.owner_type='PHYSICIAN' AND m.owner_id=p.doctor_id AND m.purpose='PHYSICIAN_PERSONAL_LOGO' AND m.classification='PUBLIC' AND m.status='READY' AND m.deleted_at IS NULL WHERE p.doctor_id=? LIMIT 1");
        $q->execute([$doctorId]);return $q->fetchColumn()!==false;
    }
}
