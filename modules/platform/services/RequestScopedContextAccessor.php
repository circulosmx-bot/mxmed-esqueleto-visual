<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\TrustedRequestContext;
final class RequestScopedContextAccessor
{
    private ?TrustedRequestContext $context = null;
    public function setOnce(TrustedRequestContext $context): void
    {
        if ($this->context !== null) throw new \LogicException('request_context_immutable');
        $this->context = $context;
    }
    public function get(): TrustedRequestContext
    {
        if ($this->context === null) throw new \LogicException('request_context_not_initialized');
        return $this->context;
    }
}
