<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver,GovernanceDeploymentAuthorization};
final class Composition
{
    public static function fromIdentity(IdentityHttpComposition $identity):Service
    {
        if(getenv('MXMED_STAFF_ADMISSION_FOUNDATION_ENABLED')!=='1'||!IdentityHttpComposition::canonicalRuntimeRequested())throw new \RuntimeException('admission_disabled');
        $p=$identity->pdo();
        $authorization=GovernanceDeploymentAuthorization::require($p,'staff_admission_foundation',environment:$identity->environment());
        if(($authorization['staff_admission_source_sha256']??null)!==self::sourceHash())throw new \RuntimeException('admission_source_drift');
        // References must be ratified by deployment authority; this phase issues only disposable QA approvals.
        foreach(['staff_privacy_reference','staff_retention_reference','staff_custody_reference'] as $key)
            if(!is_string($authorization[$key]??null)||!preg_match('/^[A-Za-z0-9_.:-]{8,128}$/D',$authorization[$key]))throw new \RuntimeException('admission_privacy_policy_required');
        if((int)$p->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn()!==1)throw new \RuntimeException('admission_schema_required');
        foreach(['auth_staff_admission_cases'=>'binding_reference','auth_staff_admission_versions'=>'provenance','auth_staff_admission_operations'=>'audit_event_id'] as $table=>$column)$p->query("SELECT `$column` FROM `$table` LIMIT 0");
        return new Service($p,new CanonicalHttpSessionResolver($identity->sessions()));
    }
    public static function sourceHash():string
    {
        $root=dirname(__DIR__,3);$files=glob(__DIR__.'/*.php');
        foreach(glob($root.'/api/internal/staff-admission/*.php') as $f)$files[]=$f;
        $files[]=$root.'/modules/identity/db/migrations/2026_10_18_04_staff_admission_foundation.sql';
        $files[]=$root.'/modules/identity/db/migrations/2026_10_18_05_staff_admission_invitations.sql';
        sort($files);$hash=[];foreach($files as $f)$hash[substr($f,strlen($root)+1)]=hash_file('sha256',$f);
        return hash('sha256',json_encode($hash,JSON_THROW_ON_ERROR));
    }
}
