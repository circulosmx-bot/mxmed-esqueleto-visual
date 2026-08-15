<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
foreach(glob($root.'/modules/platform/contracts/*.php') as $file)require_once $file;
foreach(['CorrelatableOperationCatalog','SourceModuleCatalog','CanonicalAuditPolicyRegistry','AuditWriterContextBridge'] as $name)require_once $root.'/modules/platform/services/'.$name.'.php';
foreach(['AuditProducerFailureSignal','AuditProducerEmissionResult','BoundedBestEffortAuditEmitter'] as $name)require_once $root.'/modules/identity/audit/'.$name.'.php';
require_once $root.'/modules/identity/audit/contracts/AuditProducerFailureSignalPort.php';
require_once $root.'/modules/identity/audit/contracts/CanonicalAuditAppendPort.php';
$candidateFiles=[
    'modules/platform/audit/AuthoritativeAuditOutcome.php',
    'modules/platform/audit/AuthoritativeAuditTarget.php',
    'modules/platform/audit/CanonicalMp01fAuditProducer.php',
    'modules/platform/audit/ChangedFieldNames.php',
    'modules/platform/audit/Mp01fEventScopePolicy.php',
    'modules/platform/audit/SensitiveAdminActionCatalog.php',
    'modules/platform/audit/SensitiveAdminActionKey.php',
    'modules/platform/audit/contracts/Mp01fAuditProducer.php',
    'modules/platform/tests/AuditMp01FSemanticProducerTest.php',
    'modules/platform/tests/AuditMp01FStaticContractsTest.php',
    'docs/product-completion/audit-mp01f-producer-contract.md',
];
foreach(['Mp01fEventScopePolicy','AuthoritativeAuditTarget','ChangedFieldNames','SensitiveAdminActionCatalog','SensitiveAdminActionKey','AuthoritativeAuditOutcome'] as $name)require_once $root.'/modules/platform/audit/'.$name.'.php';
require_once $root.'/modules/platform/audit/contracts/Mp01fAuditProducer.php';
require_once $root.'/modules/platform/audit/CanonicalMp01fAuditProducer.php';

$total=0;$pass=0;
function mp01fStatic(bool $ok,string $name):void{global $total,$pass;$total++;if(!$ok)throw new RuntimeException('static:'.$name);$pass++;}
foreach($candidateFiles as $rel)mp01fStatic(is_file($root.'/'.$rel),'installed_'.$rel);
mp01fStatic(count($candidateFiles)===11,'physical_candidate_count');
mp01fStatic(is_subclass_of(Platform\Audit\Mp01fEventScopePolicy::class,Platform\Contracts\AuditEventScopePolicy::class),'shared_scope_contract');
$constructor=(new ReflectionClass(Platform\Audit\CanonicalMp01fAuditProducer::class))->getConstructor();
$first=$constructor?->getParameters()[0]??null;
mp01fStatic($first?->getType()?->getName()===Identity\Audit\BoundedBestEffortAuditEmitter::class,'shared_emitter_constructor');
$properties=array_map(fn(ReflectionProperty $p)=>$p->getType()?->getName(),(new ReflectionClass(Platform\Audit\CanonicalMp01fAuditProducer::class))->getProperties());
mp01fStatic(!in_array(Identity\Audit\Contracts\CanonicalAuditAppendPort::class,$properties,true),'no_direct_writer_dependency');
mp01fStatic(!in_array(Platform\Services\AuditWriterContextBridge::class,$properties,true),'no_duplicate_context_bridge');
mp01fStatic(!in_array(Identity\Audit\Contracts\AuditProducerFailureSignalPort::class,$properties,true),'no_duplicate_failure_signal_dependency');
$declaredEmitters=0;
foreach(array_slice($candidateFiles,0,8) as $rel){
    $tokens=token_get_all(file_get_contents($root.'/'.$rel));
    for($i=0,$n=count($tokens);$i<$n;$i++)if(is_array($tokens[$i])&&$tokens[$i][0]===T_CLASS){for($j=$i+1;$j<$n;$j++)if(is_array($tokens[$j])&&$tokens[$j][0]===T_STRING){if(str_ends_with($tokens[$j][1],'Emitter'))$declaredEmitters++;break;}}
}
mp01fStatic($declaredEmitters===0,'no_second_emitter_declared');
mp01fStatic((new Platform\Audit\SensitiveAdminActionCatalog())->all()===[],'initial_catalog_empty');
mp01fStatic(Platform\Audit\SensitiveAdminActionCatalog::FREE_FORM_ALLOWED===false&&Platform\Audit\SensitiveAdminActionCatalog::UNKNOWN_KEY_ALLOWED===false&&Platform\Audit\SensitiveAdminActionCatalog::DUPLICATE_EMISSION===false,'finite_catalog_fail_closed');
echo 'STATIC_TESTS_PASS='.$pass.'/'.$total.PHP_EOL;
echo 'MP01F_SCOPE_IMPLEMENTS_SHARED_CONTRACT=true'.PHP_EOL;
echo 'MP01F_USES_SHARED_EMITTER=true'.PHP_EOL;
echo 'MP01F_REQUIRES_SECOND_EMITTER=false'.PHP_EOL;
