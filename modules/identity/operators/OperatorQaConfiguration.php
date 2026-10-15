<?php
declare(strict_types=1);
namespace Identity\Operators;
use PDO;

/** Fixed, loopback-only QA02 composition. No client flag, fallback or real DB override. */
final class OperatorQaConfiguration
{
    public const DATABASE='mxmed_gate4d_preview_op02';
    public const USER='mxmed_op02';
    public const ORIGIN='https://127.0.0.1:8445';
    public static function requested(): bool { return getenv('MXMED_OP02_ISOLATED')==='1'; }
    public static function assertEnabled(?PDO $pdo=null): void
    {
        foreach (['MXMED_OP02_ISOLATED'=>'1','APP_ENV'=>'local','MXMED_ENVIRONMENT'=>'local',
            'MXMED_PREVIEW_EXPLICIT'=>'1','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'=>'0',
            'MXMED_DB_HOST'=>'127.0.0.1','MXMED_DB_PORT'=>'3306','MXMED_DB_NAME'=>self::DATABASE,
            'MXMED_DB_USER'=>self::USER,'MXMED_PREVIEW_ORIGIN'=>self::ORIGIN,'MXMED_SESSION_STORE_PORT'=>'6387',
            'MXMED_EMAIL_PROVIDER'=>'disabled'] as $k=>$v)
            if(getenv($k)!==$v)throw new \RuntimeException('op02_isolation_required');
        $support=getenv('HOME').'/Library/Application Support/MXMED/op02-admin';
        foreach(['MXMED_PRIVATE_MEDIA_ROOT','MXMED_PUBLIC_MEDIA_ROOT','TMPDIR'] as $key)
            if(!str_starts_with((string)getenv($key),$support.'/'))throw new \RuntimeException('op02_private_storage_required');
        if($pdo!==null && ($pdo->query('SELECT DATABASE()')->fetchColumn()!==self::DATABASE
            ||$pdo->query('SELECT CURRENT_USER()')->fetchColumn()!==self::USER.'@127.0.0.1'))throw new \RuntimeException('op02_database_identity_required');
    }
}
