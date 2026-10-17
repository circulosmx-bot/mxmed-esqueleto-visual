<?php
declare(strict_types=1);
namespace Profiles\Corrections;
use PDO;
require_once __DIR__.'/../controllers/PublicProfileController.php';

/** Always uses the real public read model, never a plan override or browser profile facts. */
final class ReportEligibility
{
    public function __construct(private PDO $pdo){}
    public static function route(string $profile):string{return '/profiles/doctor.php?doctor_id='.rawurlencode($profile);}
    public function resolve(string $profile,bool $lock=false):array
    {
        if(!preg_match('/^[A-Za-z0-9._:-]{1,64}$/D',$profile))throw new ReportDenied(403);
        if($lock){
            if(!$this->pdo->inTransaction())throw new \RuntimeException('correction_transaction_required');
            $q=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$q->execute([$profile]);
            if($q->fetchColumn()!==$profile)throw new ReportDenied(403);
            // Lock the profile membership range as well as the profile row; no inferred account identity.
            $q=$this->pdo->prepare('SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? FOR UPDATE');$q->execute([$profile]);$q->fetchAll();
        }
        $repo=new \Profiles\Repositories\PublicProfileRepository($this->pdo);
        $snapshot=$repo->resolvePublicDoctorProfile($profile);
        $dto=(new \Profiles\Controllers\PublicProfileController($repo))->showByDoctorId($profile);
        $source=$snapshot['plan_source']??[];$ownership=$snapshot['ownership_source']??[];
        if(($dto['ok']??false)!==true||($dto['data']['profile']['is_public']??false)!==true
            ||($source['source']??null)!=='profiles_doctors'||!in_array(strtolower((string)($source['plan_code']??'')),['free','gratuito'],true)
            ||($ownership['source_ready']??false)!==true||($ownership['is_administered']??true)!==false
            ||($dto['data']['public_visibility']['show_suggest_correction']??false)!==true)throw new ReportDenied(403);
        return ['profile'=>$profile,'route'=>self::route($profile),'name'=>$snapshot['identity']['display_name']??$profile];
    }
}
