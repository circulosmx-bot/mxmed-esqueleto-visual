<?php
declare(strict_types=1);
namespace Profiles\Corrections;
use PDO;
require_once __DIR__.'/../controllers/DoctorContactPointsController.php';
require_once __DIR__.'/../controllers/PublicProfileController.php';

/** Narrow staff service adapter. Existing controller/repository remain the ONLY value writer.
 * Called by ApplicationService after exact application authority/case/MFA validation.
 * It never supplies a physician/owner actor or changes contact publication/association.
 */
final class PublicPhoneCorrectionAdapter
{
    private \Profiles\Repositories\DoctorContactPointsRepository $repository;
    private \Profiles\Controllers\DoctorContactPointsController $controller;
    public function __construct(private PDO $pdo)
    {$this->repository=new \Profiles\Repositories\DoctorContactPointsRepository($pdo);$this->controller=new \Profiles\Controllers\DoctorContactPointsController($this->repository);}
    public function normalize(mixed $value):string
    {
        $value=ReportCatalog::text($value,7,40);
        if(!preg_match('/^\+?[0-9 ()-]+$/D',$value))throw new ReportDenied(400);
        $normal=$this->repository->normalizeValue('phone',$value);
        if(!preg_match('/^\+?[0-9]{7,16}$/D',$normal))throw new ReportDenied(400);
        return $normal;
    }
    public function targets(string $profile):array
    {
        $q=$this->pdo->prepare("SELECT contact_point_id FROM doctor_contact_points WHERE doctor_id=? AND type='phone' AND deleted_at IS NULL ORDER BY contact_point_id");$q->execute([$profile]);$targets=[];
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){try{$targets[]=$this->snapshot($profile,(string)$id);}catch(ReportDenied){}}
        return $targets;
    }
    public function snapshot(string $profile,string $id):array
    {
        if(!$this->pdo->inTransaction())throw new ReportDenied(503);
        $parent=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$parent->execute([$profile]);
        if($parent->fetchColumn()!==$profile)throw new ReportDenied(403);
        $q=$this->pdo->prepare('SELECT * FROM doctor_contact_points WHERE doctor_id=? AND contact_point_id=? FOR UPDATE');$q->execute([$profile,$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['doctor_id']!==$profile||(string)$r['contact_point_id']!==$id||$r['consultorio_id']!==null
            ||$r['type']!=='phone'||$r['scope']!=='public_profile'||$r['status']!=='active'||$r['deleted_at']!==null
            ||(int)$r['is_public']!==1||(int)$r['use_for_public_profile']!==1||(int)$r['use_for_security']!==0
            ||(int)$r['use_for_platform_admin']!==0||(int)$r['use_for_appointments']!==0
            ||(int)$r['is_verified']!==0||$r['verification_status']!=='unverified'
            ||!in_array($r['visibility_plan_min'],['basic','standard','optimum','professional'],true))throw new ReportDenied(403);
        $current=$this->normalize($r['value']);
        if($current!==$r['normalized_value'])throw new ReportDenied(403);
        $ownership=(new \Profiles\Repositories\PublicProfileRepository($this->pdo))->resolveOwnershipSource($profile);
        if(($ownership['source_ready']??false)!==true||($ownership['is_administered']??true)!==false)throw new ReportDenied(403);
        $dto=$this->publicProfile($profile);
        if(($dto['profile']['is_public']??false)!==true||($dto['contact']['phone']??null)!==$current
            ||($dto['public_visibility']['show_phone']??false)!==true)throw new ReportDenied(403);
        $q=$this->pdo->prepare('SELECT version FROM profiles_contact_correction_versions WHERE contact_point_id=? FOR UPDATE');$q->execute([$id]);$version=(int)($q->fetchColumn()?:0);
        $visibility=['plan'=>$dto['plan']['plan_code'],'public'=>$dto['profile']['is_public'],'show_phone'=>$dto['public_visibility']['show_phone']];
        return ['contact_point_id'=>$id,'consultorio_id'=>null,'label'=>'Teléfono público del perfil (sin vincular consultorio)',
            'current_value'=>$current,'field_version'=>$version,'expected_version'=>hash('sha256',json_encode([$r,$version,$visibility],JSON_THROW_ON_ERROR))];
    }
    private function publicProfile(string $profile):array
    {
        $dto=(new \Profiles\Controllers\PublicProfileController(new \Profiles\Repositories\PublicProfileRepository($this->pdo)))->showByDoctorId($profile);
        if(($dto['ok']??false)!==true)throw new ReportDenied(503);return $dto['data'];
    }
    public function apply(array $command,array $before):array
    {
        if(!$this->pdo->inTransaction())throw new ReportDenied(503);
        $profile=$command['profile_id'];$id=$command['target']['contact_point_id'];
        $q=$this->pdo->prepare('SELECT * FROM doctor_contact_points WHERE doctor_id=? AND contact_point_id=? FOR UPDATE');$q->execute([$profile,$id]);$raw=$q->fetch(PDO::FETCH_ASSOC);
        if($this->snapshot($profile,$id)['expected_version']!==$command['expected_version'])throw new ReportDenied(409);
        $result=$this->controller->update($profile,$id,['value'=>$command['proposed_value']],'canonical_staff_correction_application');
        if(($result['ok']??false)!==true)throw new ReportDenied(($result['error']??'')==='duplicate_active_contact'?409:422);
        if(!$this->pdo->inTransaction())throw new ReportDenied(503);
        $q->execute([$profile,$id]);$afterRaw=$q->fetch(PDO::FETCH_ASSOC);
        // The controller may normalize flags. Only already canonical public records qualify;
        // a surprising side effect fails the enclosing transaction instead of widening scope.
        foreach($raw as $key=>$value)if(!in_array($key,['value','normalized_value','updated_at','active_normalized_value'],true)&&$afterRaw[$key]!==$value)throw new ReportDenied(503);
        $after=$this->snapshot($profile,$id);
        if($after['current_value']!==$command['proposed_value']||$after['field_version']!==$before['field_version']+1)throw new ReportDenied(503);
        return $after;
    }
}
