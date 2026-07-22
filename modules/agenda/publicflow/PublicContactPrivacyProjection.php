<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicContactPrivacyProjection
{
    public function beforeConfirmation(PublicBookingIntent $intent, PublicOtpChallenge $challenge, int $expiresIn, string $nextAction, string $genericResultCode): array
    {
        if ($expiresIn < 0 || $nextAction === '' || $genericResultCode === '') throw new PublicAgendaDomainException('invalid_booking_intent');
        return ['intent_id' => $intent->intentId(), 'challenge_id' => $challenge->challengeId(), 'masked_destination' => $intent->contact()->maskedDestination(), 'expires_in' => $expiresIn, 'next_action' => $nextAction, 'result_code' => $genericResultCode];
    }

    public function afterConfirmation(string $appointmentId, string $status, string $nextAction, string $genericResultCode): array
    {
        return ['appointment_id' => PublicAgendaPolicy::identifier($appointmentId, 'invalid_booking_intent'), 'status' => PublicAgendaPolicy::identifier($status, 'invalid_booking_intent'), 'next_action' => PublicAgendaPolicy::identifier($nextAction, 'invalid_booking_intent'), 'result_code' => PublicAgendaPolicy::identifier($genericResultCode, 'invalid_booking_intent')];
    }

    public function genericError(string $resultCode): array
    {
        return ['result_code' => PublicAgendaPolicy::identifier($resultCode, 'invalid_booking_intent'), 'next_action' => 'retry'];
    }
}
