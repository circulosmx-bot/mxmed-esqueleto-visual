<?php
declare(strict_types=1);
require_once __DIR__ . '/../controllers/ScheduleController.php';
use Agenda\Controllers\ScheduleController;
use Agenda\Repositories\ScheduleRepository;
final class ConflictMemorySchedules extends ScheduleRepository {
    public array $rows=[]; public int $writes=0;
    public function __construct() {}
    public function listByDoctor(string $id): array { return array_values(array_filter($this->rows,fn($r)=>$r['doctor_id']===$id)); }
    public function listByDoctorConsultorio(string $d,string $c): array { return []; }
    public function replaceWeeklySchedule(string $d,string $c,array $s): void { $this->writes++; }
}
function check($ok,$label){if(!$ok)throw new RuntimeException($label);}
$repo=new ConflictMemorySchedules();
$controller=(new ReflectionClass(ScheduleController::class))->newInstanceWithoutConstructor();
(new ReflectionProperty($controller,'repository'))->setValue($controller,$repo);
$controller->setActorContext(['doctor_id'=>'1','strict'=>true]);
$row=fn($office,$start,$end,$day=1,$active=true,$doctor='1')=>['doctor_id'=>$doctor,'consultorio_id'=>$office,'weekday'=>$day,'start_time'=>$start,'end_time'=>$end,'is_active'=>$active];
$save=fn($start,$end,$day=1)=>$controller->update(['doctor_id'=>'1','consultorio_id'=>'3','days'=>[['weekday'=>$day,'active'=>true,'windows'=>[['start_time'=>$start,'end_time'=>$end]]]]]);
foreach([['exact','09:00','12:00','09:00:00','12:00:00'],['partial','11:00','14:00','11:00:00','12:00:00'],['contained','10:00','11:00','10:00:00','11:00:00'],['contains','08:00','14:00','09:00:00','12:00:00']] as [$name,$s,$e,$os,$oe]){
 $repo->rows=[$row('2','09:00:00','12:00:00')];$r=$save($s,$e);
 check(!$r['ok'] && $r['error']==='conflict',$name);check($r['meta']->conflicts[0]['overlap_window']===['start_time'=>$os,'end_time'=>$oe],$name.' exact intersection');
}
check($repo->writes===0,'bypassing JS never persists invalid saves');
$repo->rows=[$row('2','09:00:00','10:00:00')];check($save('10:00','11:00')['ok'],'adjacent');check($save('09:00','10:00',2)['ok'],'weekday');
$repo->rows=[$row('2','09:00:00','12:00:00'),$row('1','10:00:00','13:00:00'),$row('4','09:00:00','14:00:00',1,false),$row('5','09:00:00','14:00:00',1,true,'9')];
$r=$save('11:00','14:00');check(count($r['meta']->conflicts)===2,'all active same-doctor conflicts');check($repo->writes===2,'multiple conflicts not saved');
$r=$controller->update(['doctor_id'=>'9','consultorio_id'=>'3','days'=>[]]);check($r['error']==='forbidden','doctor scope preserved');
echo "PASS: authoritative update rejects exact/partial/containment, reports intersections and all active same-doctor conflicts, permits adjacency/different weekday, zero invalid writes\n";
