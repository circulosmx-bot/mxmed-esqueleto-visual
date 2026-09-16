<?php
declare(strict_types=1);
namespace Billing\Services;

interface CsdSecretPort
{
    public function store(string $doctorId,string $credentialId,string $kind,string $plaintext):string;
    public function read(string $key):string;
    public function delete(string $key):void;
}
