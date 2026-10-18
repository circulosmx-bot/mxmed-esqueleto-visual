<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
final class Input
{
    public static function validate(string $action,array $b):array
    {
        $keys=match($action){'write'=>['operation_id','case_id','expected_case_version','expected_asset_version','purpose','declared_mime'],
            'read'=>['case_id','expected_case_version','purpose','asset_id','asset_version'],
            'result'=>['case_id','operation_id','purpose'],default=>throw new \InvalidArgumentException('staff_media_invalid')};
        $actual=array_keys($b);sort($keys);sort($actual);if($actual!==$keys)throw new \InvalidArgumentException('staff_media_invalid');
        foreach($b as $k=>$v){$ok=match($k){
            'case_id'=>is_string($v)&&preg_match('/^adm_[a-f0-9]{32}$/D',$v),
            'asset_id'=>is_string($v)&&preg_match('/^sma_[a-f0-9]{32}$/D',$v),
            'operation_id'=>is_string($v)&&preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$v),
            'expected_case_version','asset_version'=>is_int($v)&&$v>=1&&$v<2147483647,
            'expected_asset_version'=>is_int($v)&&$v>=0&&$v<2147483647,
            'purpose'=>is_string($v)&&in_array($v,[Policy::PORTRAIT,Policy::EVIDENCE],true),
            'declared_mime'=>is_string($v)&&in_array($v,['image/png','image/jpeg'],true),default=>false};
            if(!$ok)throw new \InvalidArgumentException('staff_media_invalid');}
        return $b;
    }
}
