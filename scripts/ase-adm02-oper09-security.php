<?php
declare(strict_types=1);
// Actual canonical services, only a previously authenticated synthetic account.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/ase-adm02-workflow-runtime.php';
[$identity]=staffWorkflowOwnedServices();$owned=realpath(getenv('ASE_INT02A_OWNED'));$pdo=$identity->pdo();
$tokens=json_decode(file_get_contents($owned.'/sessions.json'),true,512,JSON_THROW_ON_ERROR);
$record=json_decode(file_get_contents($owned.'/oper09-advisor.json'),true,512,JSON_THROW_ON_ERROR);
$cookies=['__Host-mxmed_session'=>$tokens['oper09']];$resolver=new Identity\Http\CanonicalHttpSessionResolver($identity->sessions());$actor=$resolver->resolve($cookies,true);
if(!$actor||$actor->accountId()!==$record['account'])throw new RuntimeException('exact_synthetic_advisor_required');
require_once __DIR__.'/../modules/subscriptions/services/ProviderOrganizationSubscriptionService.php';
$out=[];
try{(new Subscriptions\Services\ProviderOrganizationSubscriptionService($pdo))->activateInternal($actor,'oper09_nonexistent_organization','synthetic','MONTHLY');throw new RuntimeException('unexpected_financial_authorization');}
catch(Subscriptions\Services\ProviderOrganizationSubscriptionException $e){if($e->reason!=='internal_governance_required')throw $e;$out['provider_subscription_without_grant']='DENIED_BY_CANONICAL_AUTHORITY';}
try{(new Identity\Services\InternalGovernanceService($pdo,$resolver))->execute($cookies,'delegate','qa_master','media_review_read');throw new RuntimeException('unexpected_master_authorization');}
catch(RuntimeException $e){if($e->getMessage()!=='governance_denied')throw $e;$out['master_delegation']='DENIED_BY_CANONICAL_AUTHORITY';}
$q=$pdo->prepare('SELECT capability FROM internal_operator_grants WHERE account_id=? AND status=\'ACTIVE\' AND revoked_at IS NULL ORDER BY capability');$q->execute([$record['account']]);
if($q->fetchAll(PDO::FETCH_COLUMN)!==['media_review_read'])throw new RuntimeException('unexpected_grants');
$out['only_explicit_media_read_grant']=true;
echo json_encode($out,JSON_THROW_ON_ERROR);
