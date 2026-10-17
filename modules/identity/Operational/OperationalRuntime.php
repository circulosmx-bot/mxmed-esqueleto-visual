<?php
declare(strict_types=1);
namespace Identity\Operational;
use Identity\Accreditation\PublicEnrollmentRuntime;
use Profiles\Corrections\ReportRuntime;

/** Fixed private installation path, deliberately absent in normal-local.
 * No query, env flag or fixture selects an operational approval or trust key.
 */
final class OperationalRuntime
{
    private static bool $attempted=false;
    private static ?array $bundle=null;
    public static function boot():void
    {
        if(self::$attempted)return;self::$attempted=true;
        $user=posix_getpwuid(posix_geteuid());$dir=($user['dir']??'').'/Library/Application Support/MXMED/public-workflows';
        if(!is_file($dir.'/installation.json'))return;
        try{
            $config=ActivationGuard::json($dir.'/installation.json');
            $secrets=ActivationGuard::json($config['secrets_file']??'');
            $secrets['_source_path']=$config['secrets_file'];
            foreach(['evidence_key','report_key','proof_key','mfa_key','transport_key','audit_key'] as $key){$value=base64_decode($secrets[$key]??'',true);if(!is_string($value))throw new \RuntimeException('act02_secret_encoding');$secrets[$key]=$value;}
            $connection=ActivationGuard::json($config['connection_file']??'');
            if(($connection['MXMED_DB_HOST']??null)!=='127.0.0.1'||(string)($connection['MXMED_DB_PORT']??'')!=='3306'||($connection['MXMED_DB_NAME']??null)!=='mxmed')throw new \RuntimeException('act02_connection_target_denied');
            $pdo=new \PDO('mysql:host=127.0.0.1;port=3306;dbname=mxmed;charset=utf8mb4',$connection['MXMED_DB_USER'],$connection['MXMED_DB_PASS'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false]);
            $identity=\Identity\Http\IdentityHttpComposition::operationalNormalLocal($pdo);
            $secrets['_connection_path']=$config['connection_file'];
            self::$bundle=WorkflowFactory::create($identity,$dir.'/authorization.json',$dir.'/trust.json',$secrets,SmsIntegration::configured());
            require_once DeploymentPackage::root().'/modules/profiles/Corrections/bootstrap.php';
            PublicEnrollmentRuntime::install(self::$bundle['enrollment']);ReportRuntime::install(self::$bundle['reports']);
        }catch(\Throwable){self::$bundle=null;/* No operational writer, receipt or fallback provider. */}
    }
    public static function onboarding():?\Identity\Accreditation\PhysicianOnboardingHttp
    {self::boot();return self::$bundle['onboarding']??null;}
}
