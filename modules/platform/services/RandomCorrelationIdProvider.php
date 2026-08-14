<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\CorrelationIdProvider;
final class RandomCorrelationIdProvider implements CorrelationIdProvider
{
    public function __construct(private UuidV4Generator $generator){}
    public function serverGeneratedCorrelationId(string $operationKey):string{return $this->generator->generate();}
}
