<?php
declare(strict_types=1);
namespace Identity\Operators;
use PDO;
/** Explicit CLI deployment port. No HTTP path invokes it; DDL is NOT globally transactional. */
final class OperatorDeploymentPackage
{
    public const FORWARD=['01_foundation.sql','02_presentations.sql','03_creator_snapshots.sql','04_identity_delivery.sql'];
    public function __construct(private PDO $pdo){}
    public function inspect():array{return OperatorDeploymentSchema::identity($this->pdo)+['schema_fingerprint'=>OperatorDeploymentSchema::fingerprint($this->pdo),'package_hash'=>OperatorDeploymentSchema::packageHash()];}
    public function apply(array $approved,string $journal):array
    {
        if(PHP_SAPI!=='cli'||$this->pdo->inTransaction())throw new \RuntimeException('operator_deployment_cli_required');
        $actual=$this->inspect();foreach(['db','server_uuid','server_version','db_user','package_hash'] as $k)if(($approved[$k]??null)!==$actual[$k])throw new \RuntimeException('operator_deployment_target_mismatch');
        if(!preg_match('/^mxmed(?:_operator_deploy_[a-f0-9]{10})?$/D',$actual['db'])||!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$approved['authorization_reference']??''))throw new \RuntimeException('operator_deployment_authorization_required');
        if(file_exists($journal)){
            $prior=json_decode(file_get_contents($journal),true,512,JSON_THROW_ON_ERROR);
            if(($prior['state']??'')==='complete'&&($prior['db']??'')===$actual['db']&&($prior['server_uuid']??'')===$actual['server_uuid']&&($prior['package_hash']??'')===$actual['package_hash']&&($prior['schema_fingerprint']??'')===$actual['schema_fingerprint'])return $prior+['replayed'=>true];
            throw new \RuntimeException('operator_deployment_partial_or_drift_requires_review');
        }
        if(($approved['schema_fingerprint']??'')!==$actual['schema_fingerprint'])throw new \RuntimeException('operator_deployment_preflight_drift');
        foreach(OperatorDeploymentSchema::TABLES as $table){$s=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$table]);if((int)$s->fetchColumn()!==0)throw new \RuntimeException('operator_deployment_existing_objects_require_review');}
        $dir=dirname($journal);if(!is_dir($dir)||is_link($journal)||is_link($dir)||(fileperms($dir)&0077)!==0)throw new \RuntimeException('operator_deployment_private_journal_required');
        $record=$actual+['state'=>'applying','authorization_reference'=>$approved['authorization_reference'],'completed'=>[]];
        $save=function()use(&$record,$journal):void{$record['schema_fingerprint']=OperatorDeploymentSchema::fingerprint($this->pdo);$tmp=tempnam(dirname($journal),'.deploy-');chmod($tmp,0600);file_put_contents($tmp,json_encode($record,JSON_THROW_ON_ERROR));rename($tmp,$journal);};
        $save();$this->authorize($approved);
        try{
            foreach(self::FORWARD as $file){$record['current_artifact']=$file;$save();$this->pdo->exec(file_get_contents(dirname(__DIR__).'/deployment/op-act01/'.$file));$record['completed'][]=$file;$save();}
            OperatorDeploymentSchema::assertRequired($this->pdo);$record['state']='complete';$save();return $record;
        }catch(\Throwable $e){$record['state']='partial_failed';$save();throw new \RuntimeException('operator_deployment_partial_failed:'.$record['current_artifact'],0,$e);}
    }
    public function recoverEmpty(array $approved):void
    {
        if(PHP_SAPI!=='cli')throw new \RuntimeException('operator_deployment_cli_required');
        $actual=$this->inspect();foreach(['db','server_uuid','server_version','db_user','schema_fingerprint','package_hash'] as $k)if(($approved[$k]??null)!==$actual[$k])throw new \RuntimeException('operator_recovery_target_mismatch');
        if(!preg_match('/^mxmed(?:_operator_deploy_[a-f0-9]{10})?$/D',$actual['db'])||!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$approved['authorization_reference']??''))throw new \RuntimeException('operator_recovery_authorization_required');
        $this->authorize($approved);$this->pdo->exec(file_get_contents(dirname(__DIR__).'/deployment/op-act01/recovery_empty_only.sql'));
    }
    private function authorize(array $v):void
    {$s=$this->pdo->prepare('SET @mxmed_operator_deploy_target=?,@mxmed_operator_deploy_server_uuid=?,@mxmed_operator_deploy_version=?,@mxmed_operator_deploy_authorization=?');$s->execute([$v['db'],$v['server_uuid'],$v['server_version'],hash('sha256',$v['authorization_reference'])]);}
}
