<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicContactReference
{
    public function __construct(private string $channel, private string $contactReference, private string $maskedDestination)
    {
        if (!PublicAgendaPolicy::isChannel($channel)) throw new PublicAgendaDomainException('invalid_channel');
        if (preg_match('/\A[0-9a-f]{64}\z/D', $contactReference) !== 1) {
            throw new PublicAgendaDomainException('invalid_contact_reference');
        }
        if ($maskedDestination === '' || strlen($maskedDestination) > 128 || preg_match('/[\x00-\x1F\x7F]/', $maskedDestination) === 1) {
            throw new PublicAgendaDomainException('invalid_masked_destination');
        }
        if (preg_match('/\A\+?[0-9 ()-]{8,}\z/D', $maskedDestination) === 1
            || preg_match('/\A[^*@\s]+@[^@\s]+\.[^@\s]+\z/D', $maskedDestination) === 1) {
            throw new PublicAgendaDomainException('invalid_masked_destination');
        }
    }

    public function channel(): string { return $this->channel; }
    public function contactReference(): string { return $this->contactReference; }
    public function maskedDestination(): string { return $this->maskedDestination; }
    public function toArray(): array
    {
        return ['channel' => $this->channel, 'contact_reference' => $this->contactReference, 'masked_destination' => $this->maskedDestination];
    }
}
