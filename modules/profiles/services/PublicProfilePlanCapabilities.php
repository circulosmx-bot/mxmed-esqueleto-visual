<?php
declare(strict_types=1);

namespace Profiles\Services;

require_once __DIR__ . '/../../subscriptions/policy/MxmedPlanCapabilityPolicy.php';

use Subscriptions\Policy\MxmedPlanCapabilityPolicy;

final class PublicProfilePlanCapabilities
{
    private const DEFAULT_PLAN = 'free';

    public static function build($planCode, array $context = []): array
    {
        $code = self::normalizePlanCode($planCode);
        $capabilities = self::capabilitiesFor($code);

        $hasPublicProfile = (bool)($context['has_public_profile'] ?? false);
        $isClaimed = (bool)($context['is_claimed'] ?? false);
        $publicContactReady = (bool)($context['public_contact_source_ready'] ?? false);
        $claimSourceReady = (bool)($context['claim_source_ready'] ?? false);
        $commercialSourceReady = (bool)($context['commercial_source_ready'] ?? false);

        $showContactButtons = $capabilities['show_contact_buttons'] && $publicContactReady;
        $showPhone = $capabilities['show_phone'] && $publicContactReady;
        $showWhatsapp = $capabilities['show_whatsapp'] && $publicContactReady;
        $showInternalInbox = $capabilities['show_internal_inbox'] && $publicContactReady;
        $showPublicAgenda = $capabilities['show_public_agenda'] && $hasPublicProfile;
        $showReviews = $capabilities['show_reviews'] && $hasPublicProfile;
        $showPromotions = $capabilities['show_promotional_packages'] && $commercialSourceReady;
        $showInsurances = $capabilities['show_insurances'] && $commercialSourceReady;
        $showConsultationDetails = $capabilities['show_consultation_details'] && $commercialSourceReady;
        $showClaimProfile = $capabilities['show_claim_profile'] && !$isClaimed && $claimSourceReady;

        return [
            'plan' => [
                'plan_code' => $code,
                'code' => $code,
                'plan_label' => self::planLabel($code),
                'label' => self::planLabel($code),
                'tier' => MxmedPlanCapabilityPolicy::planRank($code),
                'is_paid' => $code !== 'free',
                'is_active' => true,
                'expires_at' => null,
                'grace_status' => null,
                'source' => $context['plan_source'] ?? 'default_free',
                'features' => [
                    'contact' => $capabilities['show_contact_buttons'],
                    'public_agenda' => $capabilities['show_public_agenda'],
                    'reviews' => $capabilities['show_reviews'],
                    'promotions' => $capabilities['show_promotional_packages'],
                    'clickable_map' => $capabilities['show_clickable_map'],
                    'gps_directions' => $capabilities['show_gps_directions'],
                    'review_replies' => $capabilities['allow_review_replies'],
                    'claim_profile' => $capabilities['show_claim_profile'],
                ],
                'capabilities' => $capabilities,
            ],
            'public_visibility' => [
                'show_photo' => $capabilities['show_photo'],
                'show_logo' => $capabilities['show_logo'],
                'show_professional_review' => $capabilities['show_professional_review'],
                'show_contact_buttons' => $showContactButtons,
                'show_phone' => $showPhone,
                'show_whatsapp' => $showWhatsapp,
                'show_internal_message' => $showInternalInbox,
                'show_internal_inbox' => $showInternalInbox,
                'show_public_agenda' => $showPublicAgenda,
                'show_clickable_map' => $capabilities['show_clickable_map'],
                'show_map_gps' => $capabilities['show_gps_directions'],
                'show_gps_directions' => $capabilities['show_gps_directions'],
                'show_reviews' => $showReviews,
                'show_promotions' => $showPromotions,
                'show_promotional_packages' => $showPromotions,
                'show_claim_button' => $showClaimProfile,
                'show_video_consultation' => false,
                'show_ai_claims' => false,
                'show_consultation_fee' => $showConsultationDetails,
                'show_accepted_insurances' => $showInsurances,
                'show_gallery' => $capabilities['show_gallery'],
                'show_insurances' => $showInsurances,
                'show_consultation_details' => $showConsultationDetails,
            ],
            'contact' => [
                'phone' => null,
                'whatsapp' => null,
                'internal_message_enabled' => $showInternalInbox,
                'contact_cta_label' => $showContactButtons ? 'Contactar' : null,
                'contact_restriction_reason' => self::restrictionReason(
                    $capabilities['show_contact_buttons'],
                    $publicContactReady
                ),
            ],
            'agenda_public' => [
                'enabled' => $showPublicAgenda,
                'availability_endpoint' => '/api/agenda/index.php/public/availability',
                'booking_flow' => 'public_agenda_existing',
                'requires_otp' => true,
                'allowed_consultorios' => [],
                'allowed_modalities' => [],
                'blocked_by_plan_reason' => self::agendaRestrictionReason(
                    $capabilities['show_public_agenda'],
                    $hasPublicProfile
                ),
            ],
            'commercial_visibility' => [
                'status' => $showPromotions || $showInsurances || $showConsultationDetails
                    ? 'enabled'
                    : self::commercialRestrictionReason($capabilities, $commercialSourceReady),
                'consultation_fee' => null,
                'payment_methods' => [],
                'accepted_insurances' => [],
                'commercial_restriction_reason' => self::commercialRestrictionReason(
                    $capabilities,
                    $commercialSourceReady
                ),
            ],
            'reviews' => [
                'enabled' => $showReviews,
                'visible' => $showReviews,
                'rating_avg' => null,
                'review_count' => 0,
                'reviews_preview' => [],
                'doctor_can_reply' => $capabilities['allow_review_replies'] && $hasPublicProfile,
                'doctor_can_archive' => $capabilities['allow_review_replies'] && $hasPublicProfile,
            ],
            'claim' => [
                'show_claim_button' => $showClaimProfile,
                'claim_url' => null,
                'claim_status' => $isClaimed ? 'claimed' : null,
                'claim_allowed' => $showClaimProfile,
                'claim_blocked_reason' => self::claimRestrictionReason(
                    $capabilities['show_claim_profile'],
                    $claimSourceReady,
                    $isClaimed
                ),
            ],
            'feature_flags' => [
                'has_public_profile' => $hasPublicProfile,
                'has_public_contact' => $showContactButtons,
                'has_public_agenda' => $showPublicAgenda,
                'has_reviews' => $showReviews,
                'has_promotions' => $showPromotions,
                'has_video_consultation' => false,
                'has_ai_agent' => false,
                'has_ai_profile_writer' => false,
                'has_ai_prescription_safety' => false,
                'has_commercial_profile_data' => $commercialSourceReady,
                'has_insurance_affiliations' => $showInsurances,
                'has_ecosystem_links' => false,
                'show_contact_buttons' => $showContactButtons,
                'show_public_agenda' => $showPublicAgenda,
                'show_clickable_map' => $capabilities['show_clickable_map'],
                'show_reviews' => $showReviews,
                'can_show_contact_buttons' => $capabilities['show_contact_buttons'],
                'can_show_public_agenda' => $capabilities['show_public_agenda'],
                'can_show_promotional_packages' => $capabilities['show_promotional_packages'],
                'can_allow_review_replies' => $capabilities['allow_review_replies'],
            ],
        ];
    }

    public static function normalizePlanCode($planCode): string
    {
        return MxmedPlanCapabilityPolicy::normalizePlanCode($planCode, true) ?? self::DEFAULT_PLAN;
    }

    public static function planMeetsMinimum($planCode, $minimumPlanCode): bool
    {
        $code = self::normalizePlanCode($planCode);
        $minimum = self::normalizePlanCode($minimumPlanCode);
        return MxmedPlanCapabilityPolicy::planMeetsMinimum($code, $minimum);
    }

    private static function capabilitiesFor(string $planCode): array
    {
        $included = MxmedPlanCapabilityPolicy::planCapabilities($planCode);
        $registry = MxmedPlanCapabilityPolicy::capabilityRegistry();
        $legacy = [];
        foreach (MxmedPlanCapabilityPolicy::legacyCapabilityCrosswalk() as $legacyCode => $canonicalCode) {
            $definition = $registry[$canonicalCode] ?? [];
            $legacy[$legacyCode] = in_array($canonicalCode, $included, true)
                && (bool)($definition['operational'] ?? false);
        }
        return $legacy;
    }

    private static function planLabel(string $planCode): string
    {
        foreach (MxmedPlanCapabilityPolicy::planCatalog() as $plan) {
            if (($plan['code'] ?? null) === $planCode) {
                return (string)$plan['label'];
            }
        }
        return $planCode;
    }

    private static function restrictionReason(bool $capabilityIncluded, bool $sourceReady): ?string
    {
        if (!$capabilityIncluded) {
            return 'plan_not_included';
        }
        if (!$sourceReady) {
            return 'source_not_ready';
        }
        return null;
    }

    private static function agendaRestrictionReason(bool $capabilityIncluded, bool $hasPublicProfile): ?string
    {
        if (!$capabilityIncluded) {
            return 'plan_not_included';
        }
        if (!$hasPublicProfile) {
            return 'profile_not_public';
        }
        return null;
    }

    private static function commercialRestrictionReason(array $capabilities, bool $sourceReady): ?string
    {
        $hasCommercialCapability = $capabilities['show_promotional_packages']
            || $capabilities['show_insurances']
            || $capabilities['show_consultation_details'];
        if (!$hasCommercialCapability) {
            return 'plan_not_included';
        }
        if (!$sourceReady) {
            return 'source_not_ready';
        }
        return null;
    }

    private static function claimRestrictionReason(bool $capabilityIncluded, bool $sourceReady, bool $isClaimed): ?string
    {
        if (!$capabilityIncluded) {
            return 'plan_not_included';
        }
        if ($isClaimed) {
            return 'claimed';
        }
        if (!$sourceReady) {
            return 'source_not_ready';
        }
        return null;
    }
}
