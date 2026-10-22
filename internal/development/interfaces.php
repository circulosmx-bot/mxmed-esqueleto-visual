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
if(preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',(string)getenv('SUPPORT_FIXTURE_DB')))$control=getenv('MXMED_LINK03_REVIEW')==='1'?'cross-surface-link03':'advisor-operator-support01';
if(getenv('MXMED_UX04_REVIEW')==='1')$control='cross-surface-ux04';
if(getenv('MXMED_UX05_REVIEW')==='1')$control='cross-surface-ux05';
$surfaceDetails=[
 ['Ámbito: gobierno del staff sintético; grants del Asesor. Comparación: permisos, no ficha médica.','Acceso: login Director en su ventana independiente.'],
 ['Ámbito: origen y médicos asignados por capacidad; selección exacta visible arriba. Comparación: nombre, especialidad, localidad, sedes y relaciones autorizadas.','Acceso: login Asesor en su ventana independiente.'],
 ['Comparación bloqueada: falta sesión médica legítima y composición de su fuente original.','No se dispone de credenciales autorizadas de prueba; ser staff no concede este contexto.'],
 ['Comparación bloqueada: relación canónica observada no sustituye sesión propia de operador.','Falta una sesión legítima del operador y composición del recurso original; no se importan cuentas ni cookies.'],
 ['Comparación bloqueada: la proyección administrativa no acredita publicación ni ruta pública canónica.','Falta lector de elegibilidad/ruta autorizada; active no equivale a publicado. No se abre una ruta supuesta.'],
 ['Comparación bloqueada: no hay relación organizacional acreditada para el médico seleccionado en esta composición.','Falta fuente/relación proveedor verificada y sesión propia; no se fabrica una organización.'],
];
$launcher=realpath(__DIR__.'/../../scripts/'.$control.'-review.py');
$windows=[];
foreach(['director','advisor'] as $actor){
 $path=$owned.'/'.$control.'-'.$actor.'-browser.lock';$open=false;
 if(is_file($path)&&!is_link($path)){$lock=fopen($path,'r');if($lock){$open=!flock($lock,LOCK_EX|LOCK_NB);if(!$open)flock($lock,LOCK_UN);fclose($lock);}}
 $windows[$actor]=$open;
}
?>
<details class="development-index" id="development-interface-index">
 <summary>Índice de interfaces · México Médico <small><?= getenv('MXMED_LINK03_REVIEW')==='1'?'Staff sintético · fuente local de sólo lectura':'Revisión sintética' ?></small></summary>
 <p>Sesión actual: <?= htmlspecialchars($role?:'Sin rol staff activo',ENT_QUOTES,'UTF-8') ?>. Navegar no cambia tu identidad ni concede permisos.</p>
 <p id="development-physician-context">Selecciona un perfil en el panel Asesor para identificar el contexto autorizado. El índice no transfiere permisos entre sesiones.</p>
 <ul><?php foreach($items as $index=>[$name,$route,$required,$allowed,$status]): ?>
 <li><strong><?= htmlspecialchars($name) ?></strong><p><?= $index<2?'Disponible en esta prueba':'Interfaz existente; contexto de prueba todavía no habilitado.' ?> · Requiere: <?= htmlspecialchars($required) ?>.</p><code><?= htmlspecialchars($route) ?></code>
 <p><?= htmlspecialchars($surfaceDetails[$index][0]) ?></p><p><?= htmlspecialchars($surfaceDetails[$index][1]) ?></p>
 <?php if($index<2): $actor=$index===0?'director':'advisor'; ?>
 <p><?= $windows[$actor]?'Ventana independiente abierta; el acceso se valida con su propia sesión.':'Ventana independiente todavía no abierta.' ?></p>
 <p>Esta interfaz usa una ventana y un login propios. Copia y ejecuta este comando local para abrirla o recuperarla:</p>
 <code id="review-command-<?= $actor ?>"><?= htmlspecialchars('python3 '.$launcher.' '.$owned.' open-'.$actor,ENT_QUOTES,'UTF-8') ?></code>
 <button type="button" data-copy-review="review-command-<?= $actor ?>">Copiar comando para <?= $index===0?'Director':'Asesor' ?></button>
 <?php else: ?><p><?= htmlspecialchars($status) ?></p><?php endif; ?>
 <?php if($allowed): ?><a href="<?= htmlspecialchars($route,ENT_QUOTES) ?>">Abrir con esta sesión</a><?php else: ?><span>Esta sesión no abre esta interfaz.</span><?php endif; ?></li>
 <?php endforeach; ?></ul>
 <p>Para comparar Director y Asesor, utiliza las dos ventanas independientes del comando de reapertura. No se importan cookies entre roles.</p>
 <a href="/development/interfaces.php">Ver índice completo</a>
<p id="review-command-feedback" role="status"></p>
</details>
<script src="/assets/js/development-review-index.js" defer></script>
