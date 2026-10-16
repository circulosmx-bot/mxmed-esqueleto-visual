<?php
declare(strict_types=1);
namespace Platform\Repositories;
use PDO;
/** Target comes exclusively from the connected, server-composed PDO. No HTTP schema parameter. */
final class CanonicalAuditDatabaseTarget
{
    private function __construct(public readonly string $database){}
    public static function fromConnection(PDO $pdo):self
    {
        $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if(!preg_match('/^mxmed(?:_[A-Za-z0-9_]+)?$/D',$database))throw new \RuntimeException('audit_database_target_denied');
        return new self($database);
    }
    public function routine(PDO $pdo,string $name):string
    {
        if((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$this->database)throw new \RuntimeException('audit_database_target_changed');
        $files=['audit_mp01c_lock_stream_head_v1'=>'audit_mp01c_r2_lock.sql','audit_mp01c_advance_stream_head_cas_v1'=>'audit_mp01c_r2_cas.sql'];
        if(!isset($files[$name]))throw new \RuntimeException('audit_routine_denied');
        $s=$pdo->prepare("SELECT ROUTINE_DEFINITION,SECURITY_TYPE,SQL_DATA_ACCESS FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=? AND ROUTINE_NAME=? AND ROUTINE_TYPE='PROCEDURE'");$s->execute([$this->database,$name]);$routine=$s->fetch(PDO::FETCH_ASSOC);$body=$routine['ROUTINE_DEFINITION']??null;
        if(!$routine||$routine['SECURITY_TYPE']!=='DEFINER'||$routine['SQL_DATA_ACCESS']!==($name==='audit_mp01c_lock_stream_head_v1'?'READS SQL DATA':'MODIFIES SQL DATA'))throw new \RuntimeException('audit_routine_security_drift');
        $source=file_get_contents(dirname(__DIR__).'/db/routines/'.$files[$name]);$start=strpos($source,"\nBEGIN");
        $canonical=substr($source,$start+1);$canonical=preg_replace('/END\s*\$\$.*\z/s','END',$canonical);
        $canonical=str_replace('\\\\','\\',$canonical);
        $normalize=static function(string $sql,string $db):string{
            $sql=str_replace('`'.$db.'`.`platform_audit_stream_heads`','`platform_audit_stream_heads`',$sql);
            return trim(preg_replace('/\s+/',' ',$sql));
        };
        if(!is_string($body)||$normalize($body,$this->database)!==$normalize($canonical,'mxmed'))throw new \RuntimeException('audit_routine_drift');
        $s=$pdo->prepare("SELECT PARAMETER_MODE,PARAMETER_NAME,DTD_IDENTIFIER,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=? AND SPECIFIC_NAME=? ORDER BY ORDINAL_POSITION");$s->execute([$this->database,$name]);
        $parameters=$s->fetchAll(PDO::FETCH_NUM);
        preg_match_all('/IN\s+(p_\w+)\s+(VARCHAR\(\d+\)|CHAR\(\d+\)|BIGINT)(?:\s+CHARACTER SET (\w+) COLLATE (\w+))?/i',$source,$matches,PREG_SET_ORDER);
        $expected=[];
        foreach($matches as $m){
            // Unqualified VARCHAR parameters inherit the schema character set/collation.
            $character=str_starts_with(strtolower($m[2]),'varchar')||str_starts_with(strtolower($m[2]),'char');
            $expected[]=['IN',$m[1],strtolower($m[2]),$character?($m[3]??'utf8mb4'):null,$character?($m[4]??'utf8mb4_unicode_ci'):null];
        }
        if($parameters!==$expected)throw new \RuntimeException('audit_routine_signature_drift');
        return '`'.$this->database.'`.`'.$name.'`';
    }
}
