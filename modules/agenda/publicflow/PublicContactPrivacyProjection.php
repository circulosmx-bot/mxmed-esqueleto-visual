<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicContactPrivacyProjection
{
    public function beforeConfirmation(PublicBookingIntent $intent, PublicOtpChallenge $challenge, int $expiresIn, string $nextAction, string $genericResultCode): array
    {
        if ($expiresIn < 0) throw new PublicAgendaDomainException('invalid_booking_intent');
        return ['intent_id' => $intent->intentId(), 'challenge_id' => $challenge->challengeId(), 'masked_destination' => $intent->contact()->maskedDestination(), 'expires_in' => $expiresIn, 'next_action' => PublicAgendaPolicy::publicProjectionToken($nextAction), 'result_code' => PublicAgendaPolicy::publicProjectionToken($genericResultCode)];
    }

    public function afterConfirmation(string $appointmentId, string $status, string $nextAction, string $genericResultCode): array
    {
        return ['appointment_id' => PublicAgendaPolicy::identifier($appointmentId, 'invalid_booking_intent'), 'status' => PublicAgendaPolicy::publicProjectionToken($status), 'next_action' => PublicAgendaPolicy::publicProjectionToken($nextAction), 'result_code' => PublicAgendaPolicy::publicProjectionToken($genericResultCode)];
    }

    public function genericError(string $resultCode): array
    {
        return ['result_code' => PublicAgendaPolicy::publicProjectionToken($resultCode), 'next_action' => 'retry'];
    }
}
