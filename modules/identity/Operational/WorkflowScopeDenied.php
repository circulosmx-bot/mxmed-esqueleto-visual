<?php
declare(strict_types=1);
namespace Identity\Operational;

/** A verified deployment can still deny an actor/resource/action in its pilot. */
final class WorkflowScopeDenied extends \RuntimeException
{
    public readonly int $status;
    public function __construct(string $reason){$this->status=403;parent::__construct($reason);}
}
