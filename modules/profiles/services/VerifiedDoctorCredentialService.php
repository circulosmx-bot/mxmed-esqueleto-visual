<?php
declare(strict_types=1);
namespace Profiles\Services;
use Profiles\Repositories\DoctorCredentialRepository;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use RuntimeException;
use Throwable;
require_once __DIR__.'/../repositories/DoctorCredentialRepository.php';
foreach (['contracts','repositories','services'] as $directory) {
    foreach (glob(__DIR__.'/../../platform/'.$directory.'/*.php') as $file) require_once $file;
}

/** Internal callable authority. Not exposed through physician mutation routes. */
final class VerifiedDoctorCredentialService
{
    private CanonicalAuditWriter $audit;
    public function __construct(private DoctorCredentialRepository $repository,private bool $allowSyntheticFixtures=false)
    {
        $this->audit=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),
            new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),
            new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),
            new JoinedPdoCanonicalAuditTransactionAdapter($repository->connection()),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
    }
    public function provisionFromTrustedAuthority(array $input,TrustedAuditContext $context): array
    {
        return $this->mutate($context,function() use($input,$context): array {
            $row=[
                'doctor_id'=>$this->text($input['doctor_id']??null,64,true),
                'credential_type'=>$input['credential_type']??'',
                'license_number'=>$this->text($input['license_number']??null,64,true),
                'professional_area_label'=>$this->text($input['professional_area_label']??null,190),
                'institution_name'=>$this->text($input['institution_name']??null,190),
                'verification_status'=>$input['verification_status']??'PENDING_REVIEW',
                'lifecycle_status'=>'ACTIVE','verified_at'=>null,'verified_by_account_id'=>null,
            ]+$this->source($input);
            if (!in_array($row['credential_type'],['PROFESSIONAL','SPECIALTY'],true) ||
                !in_array($row['verification_status'],['PENDING_REVIEW','VERIFIED'],true)) throw new RuntimeException('credential_invalid_initial_state');
            if ($row['verification_status']==='VERIFIED') $row=$this->verified($row,$context);
            $created=$this->repository->create($row);
            return [$created,'PROVISIONED',['credential_type'=>$row['credential_type'],'new_verification_status'=>$row['verification_status'],'new_lifecycle_status'=>'ACTIVE']];
        });
    }
    public function verify(string $id,array $review,TrustedAuditContext $context): array { return $this->transition($id,'VERIFIED',$context,$review); }
    public function reject(string $id,TrustedAuditContext $context): array { return $this->transition($id,'REJECTED',$context); }
    public function inactivate(string $id,TrustedAuditContext $context): array { return $this->transition($id,'INACTIVATED',$context); }
    public function revoke(string $id,TrustedAuditContext $context): array { return $this->transition($id,'REVOKED',$context); }
    private function transition(string $id,string $event,TrustedAuditContext $context,array $review=[]): array
    {
        return $this->mutate($context,function() use($id,$event,$context,$review): array {
            $row=$this->repository->findById($id,true) ?? throw new RuntimeException('credential_not_found');
            $metadata=['credential_type'=>$row['credential_type']];
            if (in_array($event,['VERIFIED','REJECTED'],true)) {
                if ($row['verification_status']!=='PENDING_REVIEW' || $row['lifecycle_status']!=='ACTIVE') throw new RuntimeException('credential_state_conflict');
                $metadata+=['previous_verification_status'=>$row['verification_status'],'new_verification_status'=>$event];
                if ($event==='VERIFIED') {
                    foreach (['professional_area_label','institution_name'] as $field) {
                        if (array_key_exists($field,$review)) $row[$field]=$this->text($review[$field],190);
                    }
                    $row=array_replace($row,$this->source($review));
                    $row=$this->verified($row,$context);
                }
                $row['verification_status']=$event;
            } else {
                if ($row['lifecycle_status']==='REVOKED' || ($event==='INACTIVATED' && $row['lifecycle_status']!=='ACTIVE')) throw new RuntimeException('credential_state_conflict');
                $next=$event==='INACTIVATED'?'INACTIVE':'REVOKED';
                $metadata+=['previous_lifecycle_status'=>$row['lifecycle_status'],'new_lifecycle_status'=>$next];
                $row['lifecycle_status']=$next;
            }
            return [$this->repository->persistTransition($id,$row),$event,$metadata];
        });
    }
    private function mutate(TrustedAuditContext $context,callable $operation): array
    {
        $pdo=$this->repository->connection();
        if ($pdo->inTransaction()) throw new RuntimeException('credential_outer_transaction_not_supported');
        $pdo->beginTransaction();
        try {
            if ($context->actorType!=='account' || trim($context->sessionId??'')==='' ||
                !$this->repository->isActiveInternalApprover($context->actorIdentityId)) throw new RuntimeException('credential_untrusted_authority');
            [$row,$event,$metadata]=$operation();
            $this->audit->append(new CanonicalAuditEventInput('PHYSICIAN_CREDENTIAL_'.$event,'SUCCESS','ADMIN_DECISION',
                'doctor',(string)$row['doctor_id'],'PHYSICIAN_CREDENTIAL',(string)$row['credential_id'],$metadata),$context);
            $pdo->commit();return $row;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
    private function verified(array $row,TrustedAuditContext $context): array
    {
        foreach (['doctor_id','license_number','professional_area_label','institution_name'] as $field) {
            if (!is_string($row[$field]) || trim($row[$field])==='') throw new RuntimeException('credential_verified_incomplete:'.$field);
        }
        $row['verified_at']=gmdate('Y-m-d H:i:s');
        $row['verified_by_account_id']=$context->actorIdentityId;
        return $row;
    }
    private function source(array $input): array
    {
        $type=$input['source_type']??'';
        if (!in_array($type,['admission_approved','internal_provisioning','governed_correction','synthetic_test'],true) ||
            ($type==='synthetic_test' && !$this->allowSyntheticFixtures)) throw new RuntimeException('credential_invalid_source');
        return ['source_type'=>$type,'source_reference'=>$this->text($input['source_reference']??null,190,$type!=='internal_provisioning')];
    }
    private function text(mixed $value,int $max,bool $required=false): ?string
    {
        // Textual licenses are never cast to numbers or rewritten internally.
        if ($value!==null && !is_string($value)) throw new RuntimeException('credential_text_required');
        $value=$value===null?null:trim($value);
        if ($value==='') $value=null;
        if (($required && $value===null) || ($value!==null && mb_strlen($value,'UTF-8')>$max)) throw new RuntimeException('credential_invalid_text');
        return $value;
    }
    public function listForDoctor(string $doctorId): array { return $this->repository->listByDoctorId($doctorId); }
    public function resolveVerifiedProfessional(string $doctorId): ?array { return $this->repository->resolveVerifiedProfessional($doctorId); }
    public function resolveVerifiedSpecialties(string $doctorId): array { return $this->repository->resolveVerifiedSpecialties($doctorId); }
    public function assertEligiblePrimarySpecialty(string $doctorId,string $id): array
    {
        $row=$this->repository->findById($id);
        if ($row===null || $row['doctor_id']!==$doctorId || $row['credential_type']!=='SPECIALTY' ||
            $row['verification_status']!=='VERIFIED' || $row['lifecycle_status']!=='ACTIVE') throw new RuntimeException('credential_ineligible_primary_specialty');
        return $row;
    }
    /** Called only after the private HTTP route resolves session doctor ownership. */
    public function physicianReadModel(string $doctorId): array
    {
        $primary=$this->repository->primaryId($doctorId);
        $fields=array_flip(['credential_id','credential_type','license_number','professional_area_label','institution_name','verification_status','lifecycle_status']);
        $professional=$this->resolveVerifiedProfessional($doctorId);
        $specialties=array_map(static function(array $row) use($fields,$primary): array {
            return array_intersect_key($row,$fields)+['is_primary'=>(string)$row['credential_id']===$primary];
        },$this->resolveVerifiedSpecialties($doctorId));
        return ['verified_credentials'=>['professional'=>$professional===null?null:array_intersect_key($professional,$fields),'specialties'=>$specialties],
            'primary_specialty_credential_id'=>$primary];
    }
}
