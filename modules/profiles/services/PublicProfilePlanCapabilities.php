<?php
declare(strict_types=1);

namespace Profiles\Services;

final class PublicProfilePlanCapabilities
{
    private const DEFAULT_PLAN = 'free';

    private const PLAN_ALIASES = [
        'free' => 'free',
        'gratuito' => 'free',
        'basic' => 'basic',
        'basico' => 'basic',
        'básico' => 'basic',
        'standard' => 'standard',
        'estandar' => 'standard',
        'estándar' => 'standard',
        'optimum' => 'optimum',
        'optimo' => 'optimum',
        'óptimo' => 'optimum',
        'professional' => 'professional',
        'profesional' => 'professional',
    ];

    private const PLAN_LABELS = [
        'free' => 'Gratuito',
        'basic' => 'Básico',
        'standard' => 'Estándar',
        'optimum' => 'Óptimo',
        'professional' => 'Profesional',
    ];

    private const PLAN_TIERS = [
        'free' => 0,
        'basic' => 1,
        'standard' => 2,
        'optimum' => 3,
        'professional' => 4,
    ];

    public static function build($planCode, array $context = []): array
    {
        $code = self::normalizePlanCode($planCode);
        $capabilities = self::capabilitiesFor($code);

        $hasPublicProfile = (bool)($context['has_public_profile'] ?? false);
        $isClaimed = (bool)($context['is_claimed'] ?? false);
        $profileIsAdministered = (bool)($context['profile_is_administered'] ?? true);
        $ownershipSourceReady = (bool)($context['ownership_source_ready'] ?? false);
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
        $showSuggestCorrection = $code === 'free' && !$profileIsAdministered && $ownershipSourceReady;
        $showAboutAction = $code !== 'free';
        $showConsultaAction = $code !== 'free';

        return [
            'plan' => [
                'plan_code' => $code,
                'code' => $code,
                'plan_label' => self::PLAN_LABELS[$code],
                'label' => self::PLAN_LABELS[$code],
                'tier' => self::PLAN_TIERS[$code],
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
                'show_suggest_correction' => $showSuggestCorrection,
                'show_about_action' => $showAboutAction,
                'show_consulta_action' => $showConsultaAction,
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
                'has_ai_agent' => $code === 'professional',
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
        $raw = trim((string)($planCode ?? ''));
        if ($raw === '') {
            return self::DEFAULT_PLAN;
        }

        $key = mb_strtolower($raw, 'UTF-8');
        $folded = strtr($key, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ä' => 'a',
            'ë' => 'e',
            'ï' => 'i',
            'ö' => 'o',
            'ü' => 'u',
        ]);

        if (isset(self::PLAN_ALIASES[$key])) {
            return self::PLAN_ALIASES[$key];
        }
        if (isset(self::PLAN_ALIASES[$folded])) {
            return self::PLAN_ALIASES[$folded];
        }
        return self::DEFAULT_PLAN;
    }

    public static function planMeetsMinimum($planCode, $minimumPlanCode): bool
    {
        $code = self::normalizePlanCode($planCode);
        $minimum = self::normalizePlanCode($minimumPlanCode);
        return self::PLAN_TIERS[$code] >= self::PLAN_TIERS[$minimum];
    }

    private static function capabilitiesFor(string $planCode): array
    {
        $base = [
            'show_photo' => false,
            'show_logo' => false,
            'show_professional_review' => false,
            'show_contact_buttons' => false,
            'show_phone' => false,
            'show_whatsapp' => false,
            'show_internal_inbox' => false,
            'show_clickable_map' => false,
            'show_gps_directions' => false,
            'show_public_agenda' => false,
            'show_promotional_packages' => false,
            'show_reviews' => true,
            'allow_review_replies' => false,
            'show_claim_profile' => true,
            'show_gallery' => false,
            'show_insurances' => false,
            'show_consultation_details' => false,
        ];

        if ($planCode === 'free') {
            return $base;
        }

        $basic = array_merge($base, [
            'show_photo' => true,
            'show_logo' => true,
            'show_professional_review' => true,
            'show_contact_buttons' => true,
            'show_phone' => true,
            'show_whatsapp' => true,
            'show_internal_inbox' => true,
            'show_clickable_map' => true,
            'show_gps_directions' => true,
            'show_claim_profile' => false,
        ]);

        if ($planCode === 'basic') {
            return $basic;
        }

        return array_merge($basic, [
            'show_public_agenda' => true,
            'show_promotional_packages' => true,
            'allow_review_replies' => true,
            'show_gallery' => true,
            'show_insurances' => true,
            'show_consultation_details' => true,
        ]);
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
