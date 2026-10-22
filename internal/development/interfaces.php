<?php
declare(strict_types=1);
// Emitted exclusively by the verified disposable STAFF-CONTROL04 router.
if(!isset($staffControl04Services)||getenv('MXMED_STAFF_CONTROL04')!=='1'){http_response_code(404);return;}
$i=$staffControl04Services[0];
$a=(new \Identity\Http\CanonicalHttpSessionResolver($i->sessions()))->resolve($_COOKIE,true);
$role=null;
if($a){$q=$i->pdo()->prepare("SELECT governance_class FROM internal_staff WHERE account_id=? AND status='ACTIVE'");$q->execute([$a->accountId()]);$role=$q->fetchColumn()?:null;}
$items=[
 ['PANEL DIRECTOR','/internal/media-review/index.php?section=equipo','Director activo',$role==='DIRECTOR','Disponible'],
 ['PANEL ASESOR','/internal/staff-workspace/index.php','Staff activo con grants efectivos',$role==='ADVISOR','Disponible; sesión de Asesor independiente'],
 ['PANEL USUARIO MÉDICO','/private-app.php','Médico con ámbito profesional autorizado',false,'Fuente existente; contexto médico no habilitado en esta instancia'],
 ['PANEL OPERADOR DE USUARIO MÉDICO','/private-app.php','Relación de operador y permisos propios',false,'Shell compartido existente; contexto operador no habilitado en esta instancia'],
 ['PERFIL PÚBLICO USUARIO MÉDICO','/profiles/doctor.php','Perfil publicado conforme a sus gates',false,'Fuente existente; ruta pública completa no habilitada en esta instancia'],
 ['PANEL PROVEEDOR','/provider/index.php','Cuenta y relación organizacional autorizadas',false,'Portal existente; servicio y relación organizacional no habilitados en esta instancia'],
];
$owned=realpath((string)getenv('ASE_INT02A_OWNED'));
$control=getenv('MXMED_STAFF_DOC_VIS01')==='1'?'staff-doc-vis01':(getenv('MXMED_STAFF_CONTROL07')==='1'?'staff-control07':(getenv('MXMED_STAFF_CONTROL06_R2')==='1'?'staff-control06-r2':(getenv('MXMED_STAFF_CONTROL06')==='1'?'staff-control06':(getenv('MXMED_STAFF_CONTROL05')==='1'?'staff-control05':'staff-control04'))));
if(preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',(string)getenv('SUPPORT_FIXTURE_DB')))$control='advisor-operator-support01';
$launcher=realpath(__DIR__.'/../../scripts/'.$control.'-review.py');
$windows=[];
foreach(['director','advisor'] as $actor){
 $path=$owned.'/'.$control.'-'.$actor.'-browser.lock';$open=false;
 if(is_file($path)&&!is_link($path)){$lock=fopen($path,'r');if($lock){$open=!flock($lock,LOCK_EX|LOCK_NB);if(!$open)flock($lock,LOCK_UN);fclose($lock);}}
 $windows[$actor]=$open;
}
?>
<details class="development-index" id="development-interface-index">
 <summary>Índice de interfaces · México Médico <small>Revisión sintética</small></summary>
 <p>Sesión actual: <?= htmlspecialchars($role?:'Sin rol staff activo',ENT_QUOTES,'UTF-8') ?>. Navegar no cambia tu identidad ni concede permisos.</p>
 <p id="development-physician-context">Selecciona un perfil en el panel Asesor para identificar el contexto autorizado. El índice no transfiere permisos entre sesiones.</p>
 <ul><?php foreach($items as $index=>[$name,$route,$required,$allowed,$status]): ?>
 <li><strong><?= htmlspecialchars($name) ?></strong><p><?= $index<2?'Disponible en esta prueba':'Interfaz existente; contexto de prueba todavía no habilitado.' ?> · Requiere: <?= htmlspecialchars($required) ?>.</p><code><?= htmlspecialchars($route) ?></code>
 <?php if($index<2): $actor=$index===0?'director':'advisor'; ?>
 <p><?= $windows[$actor]?'Ventana independiente abierta; el acceso se valida con su propia sesión.':'Ventana independiente todavía no abierta.' ?></p>
 <p>Para abrir o mostrar su ventana correcta, ejecuta en Terminal:</p>
 <code><?= htmlspecialchars('python3 '.$launcher.' '.$owned.' open-'.$actor,ENT_QUOTES,'UTF-8') ?></code>
 <?php else: ?><p><?= htmlspecialchars($status) ?></p><?php endif; ?>
 <?php if($allowed): ?><a href="<?= htmlspecialchars($route,ENT_QUOTES) ?>">Abrir con esta sesión</a><?php else: ?><span>Esta sesión no abre esta interfaz.</span><?php endif; ?></li>
 <?php endforeach; ?></ul>
 <p>Para comparar Director y Asesor, utiliza las dos ventanas independientes del comando de reapertura. No se importan cookies entre roles.</p>
 <a href="/development/interfaces.php">Ver índice completo</a>
</details>
