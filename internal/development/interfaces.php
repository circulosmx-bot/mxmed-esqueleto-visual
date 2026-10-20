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
?>
<details class="development-index" id="development-interface-index">
 <summary>Índice de interfaces · México Médico <small>Revisión sintética</small></summary>
 <p>Sesión actual: <?= htmlspecialchars($role?:'Sin rol staff activo',ENT_QUOTES,'UTF-8') ?>. Navegar no cambia tu identidad ni concede permisos.</p>
 <p id="development-physician-context">Selecciona un perfil en el panel Asesor para identificar el contexto autorizado. El índice no transfiere permisos entre sesiones.</p>
 <ul><?php foreach($items as [$name,$route,$required,$allowed,$status]): ?>
 <li><strong><?= htmlspecialchars($name) ?></strong><p><?= htmlspecialchars($status) ?> · Requiere: <?= htmlspecialchars($required) ?>.</p><code><?= htmlspecialchars($route) ?></code>
 <?php if($allowed): ?><a href="<?= htmlspecialchars($route,ENT_QUOTES) ?>">Abrir con esta sesión</a><?php else: ?><span>Sin acceso con este contexto de revisión.</span><?php endif; ?></li>
 <?php endforeach; ?></ul>
 <p>Para comparar Director y Asesor, utiliza las dos ventanas independientes del comando de reapertura. No se importan cookies entre roles.</p>
 <a href="/development/interfaces.php">Ver índice completo</a>
</details>
