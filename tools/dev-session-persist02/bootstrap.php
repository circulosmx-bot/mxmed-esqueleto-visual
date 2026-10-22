<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,2);require_once $root.'/scripts/ase-adm02-workflow-runtime.php';
$owned=realpath((string)getenv('ASE_INT02A_OWNED'));
if(!$owned||!preg_match('~^/private/tmp/mxmed-ase-adm02imp01-[a-zA-Z0-9_]+$~D',$owned)||in_array((int)getenv('MXMED_DB_PORT'),[3306,3309],true)||getenv('MXMED_DEV_PERSISTENT_COOKIE')!=='1')throw new RuntimeException('owned_persist02_required');
[$identity]=staffWorkflowOwnedServices();$p=$identity->pdo();
if(realpath((string)$p->query('SELECT @@datadir')->fetchColumn())!==$owned.'/mysql')throw new RuntimeException('owned_datadir_required');
$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
