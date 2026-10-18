<?php
declare(strict_types=1);
namespace Identity\MasterGovernance;
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver,GovernanceDeploymentAuthorization};
use Identity\Services\{GovernanceRuntime,GovernanceSecurityContext,StaffMfaService,InternalGovernanceService};
use Identity\Adapters\UnavailableStaffIdentityReview;
use PDO;
final readonly class Composition
{
    private function __construct(public Cases $cases,public InternalGovernanceService $writer){}
    public static function fromIdentity(IdentityHttpComposition $identity):self
    {
        if(getenv('MXMED_MASTER_GOVERNANCE_ENABLED')!=='1'||!IdentityHttpComposition::canonicalRuntimeRequested())throw new \RuntimeException('master_disabled');
        $p=$identity->pdo();self::schema($p);
        $deployment=GovernanceDeploymentAuthorization::require($p,'master_governance',environment:$identity->environment());
        if(($deployment['master_source_sha256']??null)!==self::sourceHash())throw new \RuntimeException('master_deployment_drift');
        (new \Platform\Services\EnvironmentAuditSecretProvider())->currentAuditIpKey();
        $security=new GovernanceSecurityContext($identity->sessions(),$identity->authentication());
        $mfa=new StaffMfaService($p,(string)getenv('MXMED_STAFF_MFA_ENCRYPTION_KEY'),(string)getenv('MXMED_STAFF_MFA_KEY_ID'),security:$security);
        // Deliberately unconditional. Test eligibility adapters cannot be selected through HTTP/environment.
        $cases=GovernanceRuntime::staff($p,$security,$mfa,new UnavailableStaffIdentityReview());
        return new self($cases,new InternalGovernanceService($p,new CanonicalHttpSessionResolver($identity->sessions()),$cases));
    }
    public static function schema(PDO $p):void
    {
        if((int)$p->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn()!==1)throw new \RuntimeException('master_schema_required');
        $q=$p->query("SELECT c.CHECK_CLAUSE,t.ENFORCED FROM information_schema.CHECK_CONSTRAINTS c JOIN information_schema.TABLE_CONSTRAINTS t USING(CONSTRAINT_SCHEMA,CONSTRAINT_NAME) WHERE c.CONSTRAINT_SCHEMA=DATABASE() AND c.CONSTRAINT_NAME='ck_auth_governance_case_operation'");$row=$q->fetch(PDO::FETCH_ASSOC);
        $expected=array_keys(Policy::OPERATIONS);
        $expected=[...$expected,'INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY','PHYSICIAN_EVIDENCE_REVIEW','PHYSICIAN_AUTHORITY_CHANGE','PHYSICIAN_ENROLLMENT_REVIEW','PROFESSIONAL_SOURCE_PROVISION','PUBLIC_PROFILE_CORRECTION_REVIEW','PUBLIC_PROFILE_CORRECTION_APPLY','STAFF_ADMISSION_REVIEW'];sort($expected);
        $clause=str_replace(chr(92),'',$row['CHECK_CLAUSE']??'');
        $shape=preg_replace('/\s+|_(?:utf8mb4|ascii)/','',$clause);
        if(!preg_match("~^\(`operation`in\('[A-Z_]+'(?:,'[A-Z_]+')*\)\)$~D",$shape))throw new \RuntimeException('master_schema_required');
        preg_match_all("/'([A-Z_]+)'/",$clause,$matches);$actual=$matches[1];sort($actual);
        if(($row['ENFORCED']??null)!=='YES'||$actual!==$expected)throw new \RuntimeException('master_schema_required');
        foreach(['auth_governance_cases'=>'security_context','auth_staff_mfa_factors'=>'enrollment_context','auth_staff_mfa_challenges'=>'reauthentication_context','auth_staff_mfa_proofs'=>'consumed_by_reference','internal_staff'=>'governance_class','internal_operator_grants'=>'grant_id','internal_capability_delegations'=>'delegation_id','platform_audit_events'=>'event_id','platform_audit_stream_heads'=>'stream_key'] as $table=>$column)$p->query("SELECT `$column` FROM `$table` LIMIT 0");
    }
    public static function sourceHash():string
    {
        $root=dirname(__DIR__,3);$paths=glob(__DIR__.'/*.php');
        foreach(['contracts/StaffIdentityReviewPort.php','contracts/StaffIdentityReviewSnapshot.php','adapters/UnavailableStaffIdentityReview.php','services/InternalGovernanceService.php','services/StaffMfaService.php','services/GovernanceRuntime.php','services/GovernanceCaseSecurity.php','db/migrations/2026_10_18_01_internal_master_governance_operations.sql'] as $f)$paths[]=$root.'/modules/identity/'.$f;
        foreach(glob($root.'/api/internal/team/master/*.php') as $f)$paths[]=$f;
        sort($paths);$hash=[];foreach($paths as $path)$hash[substr($path,strlen($root)+1)]=hash_file('sha256',$path);
        return hash('sha256',json_encode($hash,JSON_THROW_ON_ERROR));
    }
}
