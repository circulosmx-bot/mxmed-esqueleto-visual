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
    public static function implementationRehearsal(): bool { return getenv('MXMED_OP_IMP07_REHEARSAL')==='1'; }
    public static function rehearsal(): bool { return getenv('MXMED_OP_UI03_REHEARSAL')==='1'; }
    public static function database(): string { return self::implementationRehearsal()?'mxmed_gate4d_preview_opimp07':(self::rehearsal()?'mxmed_gate4d_preview_opui03':self::DATABASE); }
    public static function user(): string { return self::implementationRehearsal()?'mxmed_opimp07':(self::rehearsal()?'mxmed_opui03':self::USER); }
    public static function origin(): string { return self::implementationRehearsal()?'https://127.0.0.1:8447':(self::rehearsal()?'https://127.0.0.1:8446':self::ORIGIN); }
    public static function port(): int { return self::implementationRehearsal()?6389:(self::rehearsal()?6388:6387); }
    public static function supportName(): string { return self::implementationRehearsal()?'opimp07-rehearsal':(self::rehearsal()?'opui03-rehearsal':'op02-admin'); }
    public static function requested(): bool { return getenv('MXMED_OP02_ISOLATED')==='1'; }
    public static function assertEnabled(?PDO $pdo=null): void
    {
        foreach (['MXMED_OP02_ISOLATED'=>'1','APP_ENV'=>'local','MXMED_ENVIRONMENT'=>'local',
            'MXMED_PREVIEW_EXPLICIT'=>'1','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'=>'0',
            'MXMED_DB_HOST'=>'127.0.0.1','MXMED_DB_PORT'=>'3306','MXMED_DB_NAME'=>self::database(),
            'MXMED_DB_USER'=>self::user(),'MXMED_PREVIEW_ORIGIN'=>self::origin(),'MXMED_SESSION_STORE_PORT'=>(string)self::port(),
            'MXMED_EMAIL_PROVIDER'=>'disabled'] as $k=>$v)
            if(getenv($k)!==$v)throw new \RuntimeException('op02_isolation_required');
        $support=getenv('HOME').'/Library/Application Support/MXMED/'.self::supportName();
        foreach(['MXMED_PRIVATE_MEDIA_ROOT','MXMED_PUBLIC_MEDIA_ROOT','TMPDIR'] as $key)
            if(!str_starts_with((string)getenv($key),$support.'/'))throw new \RuntimeException('op02_private_storage_required');
        if($pdo!==null && ($pdo->query('SELECT DATABASE()')->fetchColumn()!==self::database()
            ||$pdo->query('SELECT CURRENT_USER()')->fetchColumn()!==self::user().'@127.0.0.1'))throw new \RuntimeException('op02_database_identity_required');
    }
}
