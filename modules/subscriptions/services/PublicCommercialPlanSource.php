<?php
declare(strict_types=1);
namespace Subscriptions\Services;
use PDO;
use Subscriptions\Repositories\CurrentSubscriptionRepository;
if(!class_exists(CurrentSubscriptionRepository::class))require_once __DIR__.'/../repositories/CurrentSubscriptionRepository.php';
if(!class_exists(CurrentSubscriptionReadModelService::class))require_once __DIR__.'/CurrentSubscriptionReadModelService.php';

/** Public eligibility needs an explicit source, never the commercial FREE default.
 * Paid entitlements still come from the existing commercial-only read model.
 */
final class PublicCommercialPlanSource
{
    public function __construct(private PDO $pdo){}
    public function resolve(array $profile):array
    {return ['authority_model'=>'ACT02.1']+$this->current($profile);}
    private function current(array $profile):array
    {
        $unknown=['plan_code'=>null,'source'=>'commercial_unavailable','source_field'=>null];
        try {
            // Distinguish a missing/unreadable source from the repository's null fallback.
            $this->pdo->query('SELECT subscription_id FROM profile_subscriptions LIMIT 0');
            if($this->pdo->inTransaction()){
                $lock=$this->pdo->prepare("SELECT subscription_id FROM profile_subscriptions WHERE entity_type='doctor' AND entity_id=? AND deleted_at IS NULL FOR SHARE");
                $lock->execute([(string)$profile['doctor_id']]);$lock->fetchAll();
            }
            $repo=new CurrentSubscriptionRepository($this->pdo);
            $row=$repo->findCurrentCandidateForEntity('doctor',(string)$profile['doctor_id'],true);
            if($row!==null){
                $plan=$row['contracted_plan_code']??$row['plan_code']??$row['effective_plan_code']??null;
                if(!in_array($plan,['free','basic','standard','optimum','professional'],true))return $unknown;
                $model=(new CurrentSubscriptionReadModelService($repo,commercialOnly:true))->resolveForEntity('doctor',(string)$profile['doctor_id']);
                if(($model['is_active']??false)!==true||($model['is_free_fallback']??true)===true
                    ||($model['source']??null)!=='profile_subscriptions.current_candidate'||($model['effective_plan_code']??null)!==$plan
                    ||$repo->findCurrentCandidateForEntity('doctor',(string)$profile['doctor_id'],true)!==$row)return $unknown;
                return ['plan_code'=>$model['effective_plan_code'],'source'=>'profile_subscriptions','source_field'=>'effective_plan_code'];
            }
            if(($profile['preloaded_free_plan']??null)==='free'
                &&is_string($profile['preloaded_free_reference']??null)&&strlen($profile['preloaded_free_reference'])>=8
                &&($profile['preloaded_free_recorded_at']??null)!==null)
                return ['plan_code'=>'free','source'=>'profiles_doctors','source_field'=>'preloaded_free_plan'];
            // Preserve explicit historical fields where they actually exist; never invent FREE.
            foreach(['plan_code','profile_plan','plan_name','subscription_plan','commercial_plan'] as $field){
                $value=$profile[$field]??null;
                if(is_string($value)&&trim($value)!=='')return ['plan_code'=>$value,'source'=>'profiles_doctors','source_field'=>$field];
            }
            return ['plan_code'=>null,'source'=>'commercial_unknown','source_field'=>null];
        }catch(\Throwable){return $unknown;}
    }
}
