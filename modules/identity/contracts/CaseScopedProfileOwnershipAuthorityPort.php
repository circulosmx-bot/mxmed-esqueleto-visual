<?php
declare(strict_types=1);
namespace Identity\Contracts;

/** Optional explicit case-bound delegation; general ownership keeps its existing port. */
interface CaseScopedProfileOwnershipAuthorityPort extends ProfileOwnershipApproverAuthorityPort
{
    public function binding():array;
    public function bindCase(array $case):void;
    public function assertCase(array $case):void;
    public function approvalCapability():string;
    public function mfaOperation():string;
}
