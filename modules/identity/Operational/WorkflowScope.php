<?php
declare(strict_types=1);
namespace Identity\Operational;

/** A deployment cohort narrows domain authority; it never grants it. */
final class WorkflowScope
{
    public function __construct(private array $approval){}
    public function workflow(string $name):void
    {if(!in_array($name,$this->approval['workflows'],true))throw new WorkflowScopeDenied('act02_workflow_denied');}
    public function account(string $id):void
    {if(!in_array($id,$this->approval['accounts'],true))throw new WorkflowScopeDenied('act02_account_outside_cohort');}
    public function allowsStaff(string $id,string $capability):bool
    {return in_array($id,$this->approval['accounts'],true)&&in_array($capability,$this->approval['staff'][$id]??[],true);}
    public function staff(string $id,string $capability):void
    {$this->account($id);if(!$this->allowsStaff($id,$capability))throw new WorkflowScopeDenied('act02_staff_outside_approved_capability');}
    public function profile(?string $id):void
    {if($id!==null&&!in_array($id,$this->approval['profiles'],true))throw new WorkflowScopeDenied('act02_profile_outside_cohort');}
    public function accepts(?string $profile,?string $account=null):bool
    {return ($profile===null||in_array($profile,$this->approval['profiles'],true))&&($account===null||in_array($account,$this->approval['accounts'],true));}
    public function sqlProfiles(string $column):array
    {return [$column.' IN ('.implode(',',array_fill(0,count($this->approval['profiles']),'?')).')',$this->approval['profiles']];}
    public function sqlCohort(string $profile,string $account):array
    {
        [$where,$args]=$this->sqlProfiles($profile);
        return ['('.$where.' OR '.$profile.' IS NULL) AND ('.$account.' IN ('.implode(',',array_fill(0,count($this->approval['accounts']),'?')).') OR '.$account.' IS NULL)',array_merge($args,$this->approval['accounts'])];
    }
}
