<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\RequestIdProvider;
final class RandomRequestIdProvider implements RequestIdProvider
{
    public function __construct(private UuidV4Generator $generator){}
    public function serverGeneratedRequestId():string{return $this->generator->generate();}
}
