<?php
declare(strict_types=1);
require_once __DIR__ . '/../services/PublicDailyAvailability.php';
require_once __DIR__ . '/../../profiles/services/PublicProfileRequestContext.php';
use Agenda\Services\PublicDailyAvailability;
use Profiles\Services\PublicProfileRequestContext;
function check($condition, string $label): void { if (!$condition) throw new RuntimeException($label); }
$profile = ['profile'=>['is_public'=>true], 'agenda_public'=>['enabled'=>true], 'consultorios'=>[
    ['consultorio_id'=>'1','public_name'=>'CMQ','is_active'=>true,'is_public'=>true],
    ['consultorio_id'=>'2','public_name'=>'Star Médica','is_active'=>true,'is_public'=>true],
    ['consultorio_id'=>'3','public_name'=>'Private','is_active'=>true,'is_public'=>false],
    ['consultorio_id'=>'4','public_name'=>'Inactive','is_active'=>false,'is_public'=>true],
]];
$now = new DateTimeImmutable('2026-09-07 09:15:00', new DateTimeZone('America/Mexico_City'));
$calls=[];
$calculate = function($doctor,$office,$date) use (&$calls) {
    $calls[] = [$doctor,$office,$date];
    if ($date === '2026-09-13') return ['ok'=>true,'slots'=>[]];
    $slots=[];
    $start=$office==='1'?9:16; $end=$office==='1'?14:20;
    for($m=$start*60;$m<$end*60;$m+=30) $slots[]=['start_at'=>sprintf('%s %02d:%02d:00',$date,intdiv($m,60),$m%60),
        'end_at'=>sprintf('%s %02d:%02d:00',$date,intdiv($m+30,60),($m+30)%60)];
    // Duplicate same-office slot must not inflate totals.
    $slots[]=$slots[0];
    return ['ok'=>true,'slots'=>array_reverse($slots)];
};
$service = new PublicDailyAvailability();
$day=$service->build($profile,'1',['mode'=>'day','date'=>'2026-09-08'], $calculate,$now);
$slots=$day['data']['days'][0]['slots'];
check(count($slots)===18,'complete global day');
check(count($calls)===2,'one exact-date call per eligible office');
check($slots[10]['consultorio_id']==='2' && $slots[10]['start_at']==='2026-09-08 16:00:00','global order and result identity');
check($slots[10]['end_at']==='2026-09-08 16:30:00','internal end');
$empty=$service->build($profile,'1',['mode'=>'day','date'=>'2026-09-13'],$calculate,$now);
check($empty['data']['days'][0]['total']===0,'empty date retained');
$today=$service->build($profile,'1',['mode'=>'day','date'=>'2026-09-07'],$calculate,$now);
check($today['data']['days'][0]['slots'][0]['start_at']==='2026-09-07 09:30:00','elapsed starts excluded');
foreach(['2026-02-30','2026-09-06','2026-12-06','bad'] as $date) check(!$service->build($profile,'1',['mode'=>'day','date'=>$date],$calculate,$now)['ok'],'date rejected '.$date);
check($service->build($profile,'1',['mode'=>'day','date'=>'2026-12-05'],$calculate,$now)['ok'],'day 89 included');
$next=$service->build($profile,'1',['mode'=>'global_days','date'=>'2026-09-12'],$calculate,$now);
check(array_column($next['data']['days'],'date')===['2026-09-12','2026-09-14','2026-09-15'],'three useful global days');
$tied=$service->build($profile,'1',['mode'=>'day','date'=>'2026-09-08'],fn()=>['ok'=>true,'slots'=>[['start_at'=>'2026-09-08 09:00:00','end_at'=>'2026-09-08 09:30:00']]],$now);
check(array_column($tied['data']['days'][0]['slots'],'consultorio_id')===['1','2'],'same-time offices distinct and deterministic');
$free=$profile; $free['agenda_public']['enabled']=false;
check(!$service->build($free,'1',['mode'=>'day','date'=>'2026-09-08'],$calculate,$now)['ok'],'Free denied');
$private=$profile; $private['profile']['is_public']=false;
check(!$service->build($private,'1',['mode'=>'day','date'=>'2026-09-08'],$calculate,$now)['ok'],'private profile denied');
check(PublicProfileRequestContext::devPlanOverride(['mxmed_plan'=>'professional'],['HTTP_HOST'=>'example.com'])===null,'remote QA override ignored');
check(PublicProfileRequestContext::devPlanOverride(['mxmed_plan'=>'professional'],['HTTP_HOST'=>'127.0.0.1:8092'])==='professional','existing local QA override preserved');
echo "PASS: complete global date, eligibility, totals, chronology, dedup, ties, per-slot identity, empty, past times, 90-day bounds, three-day navigation, plan/QA authority\n";
