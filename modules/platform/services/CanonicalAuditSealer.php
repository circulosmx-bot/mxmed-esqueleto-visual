<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\CanonicalAuditEnvelope;
final class CanonicalAuditSealer { public function __construct(private CanonicalAuditSerializer $serializer){} public function seal(CanonicalAuditEnvelope $event,string $streamKey): CanonicalAuditEnvelope { return $event->withEventHash(hash('sha256',$this->serializer->bytes($event,$streamKey))); } }
