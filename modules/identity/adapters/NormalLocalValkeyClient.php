<?php
declare(strict_types=1);
namespace Identity\Adapters;

require_once __DIR__.'/../contracts/ValkeySessionClientPort.php';
use Identity\Contracts\TransactionalValkeySessionClientPort;

/** Loopback RESP connection retained across WATCH/MULTI/EXEC; state lives in Valkey. */
final class NormalLocalValkeyClient implements TransactionalValkeySessionClientPort
{
    private mixed $socket = null;
    public function __construct(private string $host, private int $port)
    {
        if ($host !== '127.0.0.1' || $port < 1 || $port > 65535 || in_array($port, [6379,6384], true)) {
            throw new \InvalidArgumentException('normal_local_valkey_configuration_unavailable');
        }
    }
    public function __destruct() { $this->disconnect(); }
    public function ping(): bool { return $this->command(['PING']) === 'PONG'; }
    public function get(string $key): ?string { $this->assertKey($key); $v=$this->command(['GET',$key]); return $v===null ? null : (string)$v; }
    public function set(string $key, string $value, int $ttlSeconds): bool
    {
        $this->assertKey($key);
        if ($ttlSeconds<1 || $ttlSeconds>43200) throw new \InvalidArgumentException('invalid_session_ttl');
        return in_array($this->command(['SET',$key,$value,'EX',(string)$ttlSeconds]), ['OK','QUEUED'], true);
    }
    public function delete(string $key): bool { $this->assertKey($key); $v=$this->command(['DEL',$key]); return $v==='QUEUED' || (is_int($v) && $v>0); }
    public function watch(array $keys): bool
    {
        if ($keys===[]) throw new \InvalidArgumentException('session_watch_keys_required');
        foreach ($keys as $key) $this->assertKey($key);
        return $this->command(array_merge(['WATCH'],$keys))==='OK';
    }
    public function unwatch(): void { $this->disconnect(); }
    public function multi(): bool { return $this->command(['MULTI'])==='OK'; }
    public function exec(): array|false
    {
        $v=$this->command(['EXEC']);
        if ($v===null) return false;
        if (!is_array($v)) throw new SessionStoreUnavailableException('normal_local_session_store_unavailable');
        return $v;
    }
    public function ttl(string $key): int { $this->assertKey($key); return (int)$this->command(['TTL',$key]); }
    private function assertKey(string $key): void
    {
        if (preg_match('/^mxmed:local:session:(?:account-index:)?[a-f0-9]{64}$/D',$key)!==1) throw new \InvalidArgumentException('normal_local_session_key_forbidden');
    }
    private function disconnect(): void { if (is_resource($this->socket)) fclose($this->socket); $this->socket=null; }
    private function command(array $parts): mixed
    {
        try {
            if (!is_resource($this->socket)) {
                $this->socket=@stream_socket_client('tcp://'.$this->host.':'.$this->port,$errno,$error,1);
                if (!is_resource($this->socket)) throw new \RuntimeException();
                stream_set_timeout($this->socket,1);
            }
            $payload='*'.count($parts)."\r\n";
            foreach ($parts as $part) $payload.='$'.strlen($part)."\r\n".$part."\r\n";
            while ($payload!=='') {
                $written=fwrite($this->socket,$payload);
                if ($written===false || $written===0) throw new \RuntimeException();
                $payload=substr($payload,$written);
            }
            return $this->read();
        } catch (\Throwable) {
            $this->disconnect();
            throw new SessionStoreUnavailableException('normal_local_session_store_unavailable');
        }
    }
    private function read(int $depth=0): mixed
    {
        if ($depth>8) throw new \RuntimeException();
        $line=fgets($this->socket,1024);
        if ($line===false || !str_ends_with($line,"\r\n")) throw new \RuntimeException();
        $type=$line[0]; $value=substr($line,1,-2);
        if ($type==='+') return $value;
        if ($type===':') return (int)$value;
        if ($type==='-' || !in_array($type,['$','*'],true) || !preg_match('/^-?\d+$/D',$value)) throw new \RuntimeException();
        $length=(int)$value;
        if ($length===-1) return null;
        if ($length<0 || $length>1048576 || ($type==='*' && $length>1024)) throw new \RuntimeException();
        if ($type==='*') { $out=[]; for($i=0;$i<$length;$i++) $out[]=$this->read($depth+1); return $out; }
        $data='';
        while(strlen($data)<$length+2) {
            $chunk=fread($this->socket,$length+2-strlen($data));
            if ($chunk===false || $chunk==='') throw new \RuntimeException();
            $data.=$chunk;
        }
        if (!str_ends_with($data,"\r\n")) throw new \RuntimeException();
        return substr($data,0,-2);
    }
}
