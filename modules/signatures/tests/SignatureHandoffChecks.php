<?php
require_once __DIR__.'/../SignatureHandoffService.php';
sigSql($pdo,file_get_contents(__DIR__.'/../db/2026_09_14_signature_handoffs.sql'));sigSql($pdo,file_get_contents(__DIR__.'/../db/2026_09_14_signature_handoffs.sql'));
$handoff=new \Signatures\SignatureHandoffService($pdo,$service);
$service->save('synthetic-a',$data,$context);$original=$service->current('synthetic-a');
$firstHandoff=$handoff->create('synthetic-a',$context);sigCheck($firstHandoff['ttl_seconds']===300,'five minute TTL');sigCheck($handoff->validate($firstHandoff['token'])['status']==='PENDING','valid token opens');
$rows=$pdo->query('SELECT * FROM physician_signature_handoffs')->fetchAll(PDO::FETCH_ASSOC);sigCheck(!str_contains(json_encode($rows),$firstHandoff['token']),'only token hash persisted');sigCheck($service->current('synthetic-a')===$original,'starting handoff preserves signature');
sigDeny(fn()=>$handoff->validate(str_repeat('Z',43)),'random token denied');sigDeny(fn()=>$handoff->create('synthetic-b',$context),'cross owner creation denied');sigDeny(fn()=>$handoff->status($firstHandoff['id'],'synthetic-b',$context),'cross owner status denied');
$newHandoff=$handoff->create('synthetic-a',$context);sigDeny(fn()=>$handoff->validate($firstHandoff['token']),'new session invalidates old token');sigCheck($handoff->status($firstHandoff['id'],'synthetic-a',$context)['status']==='EXPIRED','invalidated status');
$pdo->exec("UPDATE physician_signature_handoffs SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=".$pdo->quote($newHandoff['id']));sigDeny(fn()=>$handoff->validate($newHandoff['token']),'expired token denied');sigCheck($service->current('synthetic-a')===$original,'expired handoff preserves signature');
$pending=$handoff->create('synthetic-a',$context);sigDeny(fn()=>$handoff->complete($pending['token'],'broken'),'failed signature leaves pending');sigCheck($handoff->status($pending['id'],'synthetic-a',$context)['status']==='PENDING','failure does not consume');
$pdo->exec("CREATE TRIGGER synthetic_handoff_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW BEGIN IF NEW.action='PHYSICIAN_SIGNATURE_HANDOFF_COMPLETED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'; END IF; END");
sigDeny(fn()=>$handoff->complete($pending['token'],$data),'completion audit failure rolls back signature');sigCheck($service->current('synthetic-a')===$original,'completion audit failure keeps old signature');sigCheck($handoff->status($pending['id'],'synthetic-a',$context)['status']==='PENDING','completion audit failure keeps pending');$pdo->exec('DROP TRIGGER synthetic_handoff_audit_failure');
$beforeAudit=(int)$pdo->query("SELECT COUNT(*) FROM platform_audit_events WHERE action='PHYSICIAN_SIGNATURE_HANDOFF_COMPLETED'")->fetchColumn();
$children=[];
for($i=0;$i<2;$i++){
 $child=proc_open([PHP_BINARY,__DIR__.'/SignatureHandoffRaceWorker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$cpipes,$root,array_merge(getenv(),['MXMED_SIG03A_DISPOSABLE_PORT'=>$port,'MXMED_SIG03A_PRIVATE_TEST_ROOT'=>$private]));
 fwrite($cpipes[0],json_encode(['token'=>$pending['token'],'image'=>$data])."\n");$children[]=[$child,$cpipes];
}
foreach($children as[$child,$cpipes])sigCheck(trim(fgets($cpipes[1]))==='READY','concurrent worker ready');
foreach($children as[$child,$cpipes]){fwrite($cpipes[0],"GO\n");fclose($cpipes[0]);}
$wins=0;foreach($children as[$child,$cpipes]){$result=trim(stream_get_contents($cpipes[1]));$error=stream_get_contents($cpipes[2]);fclose($cpipes[1]);fclose($cpipes[2]);$exit=proc_close($child);sigCheck($exit===0,'race worker completed');if($result==='SUCCESS')$wins++;else sigCheck($result==='DENIED','race loser denied');}
sigCheck($wins===1,'exactly one concurrent completion succeeds');sigCheck((int)$pdo->query("SELECT COUNT(*) FROM platform_audit_events WHERE action='PHYSICIAN_SIGNATURE_HANDOFF_COMPLETED'")->fetchColumn()===$beforeAudit+1,'exactly one completion audit');
sigCheck($handoff->status($pending['id'],'synthetic-a',$context)['status']==='COMPLETED','desktop completion status');sigDeny(fn()=>$handoff->complete($pending['token'],$data),'completed token replay denied');sigDeny(fn()=>$handoff->validate($pending['token']),'completed token cannot reopen');sigCheck($service->current('synthetic-b')===null,'token never writes other physician');
$cancel=$handoff->create('synthetic-a',$context);$beforeCancel=$service->current('synthetic-a');$handoff->cancel($cancel['id'],'synthetic-a',$context);sigDeny(fn()=>$handoff->validate($cancel['token']),'desktop cancellation invalidates');sigCheck($service->current('synthetic-a')===$beforeCancel,'cancel preserves signature');
$pdo->exec("UPDATE physician_signature_handoffs SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 2 DAY)");$handoff->create('synthetic-a',$context);sigCheck((int)$pdo->query('SELECT COUNT(*) FROM physician_signature_handoffs')->fetchColumn()===1,'opportunistic old-session cleanup');
$allAudit=json_encode($pdo->query('SELECT action,metadata_json FROM platform_audit_events')->fetchAll(PDO::FETCH_ASSOC));sigCheck(!str_contains($allAudit,$pending['token'])&&!str_contains($allAudit,$firstHandoff['token'])&&!str_contains($allAudit,'data:image'),'no tokens or image in audit');
require __DIR__.'/SignatureHandoffHttpChecks.php';
