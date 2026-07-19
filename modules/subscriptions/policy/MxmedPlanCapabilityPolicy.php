<?php
declare(strict_types=1);

namespace Subscriptions\Policy;

final class MxmedPlanCapabilityPolicy
{
    public const VERSION = 'MXMED_PLAN_CAPABILITY_POLICY_V1';

    private const PLAN_CODES = ['free', 'basic', 'standard', 'optimum', 'professional'];

    private const PLAN_ALIASES = [
        'free' => 'free',
        'gratis' => 'free',
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
        'pro' => 'professional',
    ];

    private const TECHNICAL_FALLBACK_ALIASES = [
        'free_default' => 'free',
    ];

    private const PLAN_LABELS = [
        'free' => 'Gratuito',
        'basic' => 'Básico',
        'standard' => 'Estándar',
        'optimum' => 'Óptimo',
        'professional' => 'Profesional',
    ];

    private const PLAN_HIERARCHY = [
        'free' => 0,
        'basic' => 1,
        'standard' => 2,
        'optimum' => 3,
        'professional' => 4,
    ];

    private const PLAN_CAPABILITIES = [
        'free' => [
            'profile_publication',
            'profile_management',
            'profile_photo',
            'public_reviews',
            'clickable_map',
            'gps_directions',
        ],
        'basic' => [
            'public_contact',
            'public_gallery',
            'public_profile_extended',
            'internal_inbox',
            'ai_content_writing',
            'ai_image_generation',
        ],
        'standard' => [
            'agenda',
            'patient_contact',
            'notifications',
            'promotional_packages',
            'insurance_affiliations',
            'consultation_details',
            'review_replies',
        ],
        'optimum' => [
            'clinical_record',
            'prescriptions',
            'clinical_files',
        ],
        'professional' => [
            'ai_agenda_agent',
        ],
    ];

    private const FUTURE_DISABLED = [
        'notifications',
        'ai_content_writing',
        'ai_image_generation',
        'ai_agenda_agent',
        'ai_medication_interaction',
        'call_center_human_service',
        'call_center_ai_fallback',
        'call_center_voice_channel',
        'call_center_whatsapp_channel',
    ];

    private const CAPABILITY_LABELS = [
        'profile_publication' => 'Perfil en directorio',
        'profile_management' => 'Administración del perfil',
        'profile_photo' => 'Fotografía principal',
        'public_reviews' => 'Reseñas públicas',
        'clickable_map' => 'Mapa externo',
        'gps_directions' => 'Indicaciones de ubicación',
        'public_contact' => 'Contacto público',
        'public_gallery' => 'Galería pública',
        'public_profile_extended' => 'Perfil ampliado',
        'internal_inbox' => 'Buzón interno',
        'agenda' => 'Agenda en línea',
        'patient_contact' => 'Contacto operativo del paciente',
        'notifications' => 'Notificaciones operativas',
        'promotional_packages' => 'Promociones y paquetes',
        'insurance_affiliations' => 'Aseguradoras aceptadas',
        'consultation_details' => 'Detalles comerciales de consulta',
        'review_replies' => 'Respuesta a reseñas',
        'clinical_record' => 'Expediente clínico',
        'prescriptions' => 'Recetas digitales',
        'clinical_files' => 'Archivos clínicos',
        'ai_content_writing' => 'Redacción asistida por IA',
        'ai_image_generation' => 'Imágenes promocionales por IA',
        'ai_agenda_agent' => 'Agente de Agenda con IA',
        'ai_medication_interaction' => 'Interacciones medicamentosas',
        'call_center_human_service' => 'Call Center humano',
        'call_center_ai_fallback' => 'Respaldo IA de Call Center',
        'call_center_voice_channel' => 'Canal de voz Call Center',
        'call_center_whatsapp_channel' => 'Canal WhatsApp Call Center',
    ];

    private const LEGACY_CAPABILITY_CROSSWALK = [
        'allow_review_replies' => 'review_replies',
        'can_allow_review_replies' => 'review_replies',
        'can_show_contact_buttons' => 'public_contact',
        'can_show_promotional_packages' => 'promotional_packages',
        'can_show_public_agenda' => 'agenda',
        'has_ai_agent' => 'ai_agenda_agent',
        'has_ai_prescription_safety' => 'ai_medication_interaction',
        'has_ai_profile_writer' => 'ai_content_writing',
        'has_commercial_profile_data' => 'consultation_details',
        'has_ecosystem_links' => 'public_profile_extended',
        'has_insurance_affiliations' => 'insurance_affiliations',
        'has_promotions' => 'promotional_packages',
        'has_public_agenda' => 'agenda',
        'has_public_contact' => 'public_contact',
        'has_public_profile' => 'profile_publication',
        'has_reviews' => 'public_reviews',
        'has_video_consultation' => 'public_profile_extended',
        'show_accepted_insurances' => 'insurance_affiliations',
        'show_ai_claims' => 'ai_content_writing',
        'show_claim_button' => 'profile_management',
        'show_claim_profile' => 'profile_management',
        'show_clickable_map' => 'clickable_map',
        'show_consultation_details' => 'consultation_details',
        'show_consultation_fee' => 'consultation_details',
        'show_contact_buttons' => 'public_contact',
        'show_gallery' => 'public_gallery',
        'show_gps_directions' => 'gps_directions',
        'show_insurances' => 'insurance_affiliations',
        'show_internal_inbox' => 'internal_inbox',
        'show_internal_message' => 'internal_inbox',
        'show_logo' => 'public_profile_extended',
        'show_map_gps' => 'gps_directions',
        'show_phone' => 'public_contact',
        'show_photo' => 'profile_photo',
        'show_professional_review' => 'public_profile_extended',
        'show_promotional_packages' => 'promotional_packages',
        'show_promotions' => 'promotional_packages',
        'show_public_agenda' => 'agenda',
        'show_reviews' => 'public_reviews',
        'show_video_consultation' => 'public_profile_extended',
        'show_whatsapp' => 'public_contact',
    ];

    private const COMMERCIAL_STATES = [
        'free',
        'draft',
        'pending_payment',
        'pending_activation',
        'active',
        'past_due',
        'grace',
        'restricted',
        'expired',
        'cancelled',
        'superseded',
        'failed',
    ];

    private const CAPABILITY_STATES = [
        'enabled',
        'read_only',
        'locked_upsell',
        'blocked_dependency',
        'pending_activation',
        'grace_limited',
        'suspended_policy',
        'hidden_security',
        'not_applicable',
    ];

    private const DENIAL_REASONS = [
        'profile_not_approved',
        'ownership_required',
        'ownership_disputed',
        'ownership_suspended',
        'capability_not_in_plan',
        'addon_required',
        'addon_not_eligible',
        'implementation_not_available',
        'capability_pending_activation',
        'dependency_missing',
        'quota_exhausted',
        'actor_role_not_allowed',
        'actor_scope_not_allowed',
        'subscription_pending_payment',
        'subscription_in_grace',
        'capability_grace_limited',
        'capability_read_only',
        'capability_suspended',
        'profile_type_not_supported',
    ];

    public static function version(): string
    {
        return self::VERSION;
    }

    public static function planCodes(): array
    {
        return self::PLAN_CODES;
    }

    public static function planAliases(bool $includeTechnicalFallbacks = false): array
    {
        return $includeTechnicalFallbacks
            ? array_merge(self::PLAN_ALIASES, self::TECHNICAL_FALLBACK_ALIASES)
            : self::PLAN_ALIASES;
    }

    public static function normalizePlanCode($value, bool $allowTechnicalFallback = false): ?string
    {
        $raw = self::lower(trim((string)($value ?? '')));
        if ($raw === '') {
            return null;
        }

        if (array_key_exists($raw, self::PLAN_ALIASES)) {
            return self::PLAN_ALIASES[$raw];
        }
        if ($allowTechnicalFallback && array_key_exists($raw, self::TECHNICAL_FALLBACK_ALIASES)) {
            return self::TECHNICAL_FALLBACK_ALIASES[$raw];
        }

        return null;
    }

    public static function requirePlanCode($value, bool $allowTechnicalFallback = false): string
    {
        $normalized = self::normalizePlanCode($value, $allowTechnicalFallback);
        if ($normalized === null) {
            throw new \InvalidArgumentException('unknown_plan_code');
        }
        return $normalized;
    }

    public static function planRank($value): ?int
    {
        $code = self::normalizePlanCode($value, true);
        return $code !== null ? self::PLAN_HIERARCHY[$code] : null;
    }

    public static function planMeetsMinimum($value, $minimum): bool
    {
        $rank = self::planRank($value);
        $minimumRank = self::planRank($minimum);
        return $rank !== null && $minimumRank !== null && $rank >= $minimumRank;
    }

    public static function planCapabilities($value): array
    {
        $code = self::requirePlanCode($value, true);
        $capabilities = [];
        foreach (self::PLAN_CODES as $planCode) {
            if (self::PLAN_HIERARCHY[$planCode] > self::PLAN_HIERARCHY[$code]) {
                break;
            }
            $capabilities = array_merge($capabilities, self::PLAN_CAPABILITIES[$planCode]);
        }
        return array_values(array_unique($capabilities));
    }

    public static function capabilityRegistry(): array
    {
        $registry = [];
        foreach (self::CAPABILITY_LABELS as $code => $label) {
            $eligiblePlans = [];
            foreach (self::PLAN_CODES as $planCode) {
                if (in_array($code, self::planCapabilities($planCode), true)) {
                    $eligiblePlans[] = $planCode;
                }
            }
            $future = in_array($code, self::FUTURE_DISABLED, true);
            $addonOnly = str_starts_with($code, 'call_center_');
            $clinical = in_array($code, ['clinical_record', 'prescriptions', 'clinical_files'], true);
            $registry[$code] = [
                'code' => $code,
                'label' => $label,
                'sourceTypes' => $addonOnly
                    ? ['addon', 'temporary_grant', 'contractual_override', 'security_policy']
                    : ['plan', 'temporary_grant', 'contractual_override', 'security_policy'],
                'eligiblePlans' => $addonOnly ? ['standard', 'optimum', 'professional'] : $eligiblePlans,
                'applicableProfileTypes' => ['doctor'],
                'implementationState' => $future ? 'documented_disabled' : 'implemented_core',
                'documented' => true,
                'marketable' => !$future,
                'purchasable' => !$future && !$addonOnly,
                'operational' => !$future,
                'approvalRequired' => !in_array($code, ['profile_publication', 'public_reviews'], true),
                'ownershipRequired' => !in_array($code, ['profile_publication', 'public_reviews'], true),
                'clinicalAuthorizationRequired' => $clinical,
                'publicPresentationEligible' => !$clinical,
                'quotaPolicy' => self::quotaKeyForCapability($code),
                'dependencies' => $future ? ['implementation_available'] : [],
            ];
        }
        return $registry;
    }

    public static function capability(string $code): ?array
    {
        $registry = self::capabilityRegistry();
        return $registry[$code] ?? null;
    }

    public static function legacyCapabilityCrosswalk(): array
    {
        return self::LEGACY_CAPABILITY_CROSSWALK;
    }

    public static function quotas(): array
    {
        return [
            'profile_photo' => [
                'category' => 'commercial_quota',
                'unit' => 'active_slot',
                'period' => 'concurrent',
                'plans' => array_fill_keys(self::PLAN_CODES, 1),
            ],
            'public_gallery' => [
                'category' => 'commercial_quota',
                'unit' => 'active_slot',
                'period' => 'concurrent',
                'plans' => ['free' => 0, 'basic' => 21, 'standard' => 21, 'optimum' => 21, 'professional' => 21],
            ],
            'public_image_size' => [
                'category' => 'technical_limit',
                'unit' => 'byte',
                'period' => 'per_stored_public_image',
                'maximumExclusive' => 300000,
            ],
            'ai_image_generation' => [
                'category' => 'commercial_quota',
                'unit' => 'successful_generation',
                'period' => 'calendar_month',
                'provisional' => true,
                'plans' => ['free' => 0, 'basic' => 3, 'standard' => 10, 'optimum' => 20, 'professional' => 30],
            ],
            'ai_content_writing' => [
                'category' => 'commercial_quota',
                'unit' => 'successful_draft',
                'period' => 'calendar_month',
                'provisional' => true,
                'plans' => ['free' => 0, 'basic' => 15, 'standard' => 30, 'optimum' => 60, 'professional' => 100],
            ],
            'agenda' => [
                'category' => 'commercial_quota',
                'unit' => 'appointment_action',
                'period' => 'contract',
                'plans' => ['free' => 0, 'basic' => 0, 'standard' => 'unlimited', 'optimum' => 'unlimited', 'professional' => 'unlimited'],
            ],
            'call_center' => [
                'category' => 'commercial_quota',
                'unit' => 'agenda_interaction',
                'period' => 'addon_contract',
                'value' => 'unlimited',
                'fairUse' => true,
                'overageBilling' => false,
            ],
        ];
    }

    public static function quotaFor(string $quotaKey, string $planCode): ?array
    {
        $quota = self::quotas()[$quotaKey] ?? null;
        if ($quota === null) {
            return null;
        }
        $plan = self::normalizePlanCode($planCode, true);
        $value = $plan !== null && isset($quota['plans']) ? ($quota['plans'][$plan] ?? null) : ($quota['value'] ?? null);
        return $quota + ['key' => $quotaKey, 'value' => $value];
    }

    public static function addOns(): array
    {
        $base = [
            'profileTypes' => ['doctor'],
            'eligiblePlans' => ['standard', 'optimum', 'professional'],
            'commercialUnit' => 'doctor_profile',
            'agendaUnits' => 1,
            'priceStatus' => 'tentative_prelaunch',
            'currency' => 'MXN',
            'billingPeriod' => 'annual',
            'renewal' => 'automatic_annual_modeled',
            'proration' => 'daily_remaining_term_modeled',
            'monthlyInitialAdvanceCycles' => 3,
            'purchasable' => false,
            'operational' => false,
            'implementationState' => 'documented_disabled',
            'dependencyReason' => 'implementation_not_available',
            'states' => ['available', 'selected', 'pending_payment', 'paid_pending_configuration', 'active', 'paused', 'cancel_at_period_end', 'expired', 'ineligible'],
            'quotaPolicy' => 'call_center',
            'mutualExclusionGroup' => 'call_center_mode',
        ];
        return [
            'call_center_complementary' => $base + [
                'code' => 'call_center_complementary',
                'label' => 'Call Center Complementario',
                'tentativePriceCents' => 199900,
                'capabilities' => ['call_center_human_service', 'call_center_ai_fallback', 'call_center_voice_channel', 'call_center_whatsapp_channel'],
            ],
            'call_center_integral' => $base + [
                'code' => 'call_center_integral',
                'label' => 'Call Center Integral',
                'tentativePriceCents' => 299900,
                'capabilities' => ['call_center_human_service', 'call_center_ai_fallback', 'call_center_voice_channel', 'call_center_whatsapp_channel'],
            ],
        ];
    }

    public static function addOnEligibility(string $addOnCode, string $planCode, string $profileType = 'doctor'): array
    {
        $addOn = self::addOns()[$addOnCode] ?? null;
        $plan = self::normalizePlanCode($planCode, true);
        if ($addOn === null || $plan === null) {
            return ['eligible' => false, 'reason' => 'addon_not_eligible'];
        }
        if (!in_array($profileType, $addOn['profileTypes'], true)) {
            return ['eligible' => false, 'reason' => 'profile_type_not_supported'];
        }
        if (!in_array($plan, $addOn['eligiblePlans'], true)) {
            return ['eligible' => false, 'reason' => 'addon_not_eligible'];
        }
        return [
            'eligible' => true,
            'reason' => null,
            'purchasable' => false,
            'operational' => false,
            'dependencyReason' => 'implementation_not_available',
        ];
    }

    public static function commercialStates(): array
    {
        return self::COMMERCIAL_STATES;
    }

    public static function capabilityStates(): array
    {
        return self::CAPABILITY_STATES;
    }

    public static function denialReasons(): array
    {
        return self::DENIAL_REASONS;
    }

    public static function planCatalog(): array
    {
        $catalog = [];
        foreach (self::PLAN_CODES as $code) {
            $capabilities = self::planCapabilities($code);
            $catalog[] = [
                'code' => $code,
                'label' => self::PLAN_LABELS[$code],
                'rank' => self::PLAN_HIERARCHY[$code],
                'profileTypes' => ['doctor'],
                'capabilities' => array_map(static function (string $capabilityCode): array {
                    $capability = self::capability($capabilityCode) ?? [];
                    return [
                        'code' => $capabilityCode,
                        'label' => $capability['label'] ?? $capabilityCode,
                        'implementationState' => $capability['implementationState'] ?? 'documented_disabled',
                        'operational' => (bool)($capability['operational'] ?? false),
                        'marketable' => (bool)($capability['marketable'] ?? false),
                        'quotaPolicy' => $capability['quotaPolicy'] ?? null,
                    ];
                }, $capabilities),
                'quotas' => self::planQuotaSummary($code),
                'futureDisabledCapabilities' => array_values(array_filter(
                    $capabilities,
                    static fn(string $capabilityCode): bool => in_array($capabilityCode, self::FUTURE_DISABLED, true)
                )),
                'priceAuthority' => 'subscription_plan_prices_backend',
                'prices' => [],
            ];
        }
        return $catalog;
    }

    public static function export(): array
    {
        return [
            'policyVersion' => self::VERSION,
            'planCodes' => self::PLAN_CODES,
            'planAliases' => self::PLAN_ALIASES,
            'technicalFallbackAliases' => self::TECHNICAL_FALLBACK_ALIASES,
            'planHierarchy' => self::PLAN_HIERARCHY,
            'planCatalog' => self::planCatalog(),
            'capabilities' => self::capabilityRegistry(),
            'legacyCapabilityCrosswalk' => self::LEGACY_CAPABILITY_CROSSWALK,
            'quotas' => self::quotas(),
            'addOns' => self::addOns(),
            'commercialStates' => self::COMMERCIAL_STATES,
            'capabilityStates' => self::CAPABILITY_STATES,
            'denialReasons' => self::DENIAL_REASONS,
            'profileTypes' => ['doctor'],
            'futureImplementationDefault' => 'fail_closed',
        ];
    }

    private static function planQuotaSummary(string $planCode): array
    {
        $summary = [];
        foreach (['profile_photo', 'public_gallery', 'ai_image_generation', 'ai_content_writing', 'agenda'] as $quotaKey) {
            $summary[$quotaKey] = self::quotaFor($quotaKey, $planCode);
        }
        return $summary;
    }

    private static function quotaKeyForCapability(string $capabilityCode): ?string
    {
        $map = [
            'profile_photo' => 'profile_photo',
            'public_gallery' => 'public_gallery',
            'ai_image_generation' => 'ai_image_generation',
            'ai_content_writing' => 'ai_content_writing',
            'agenda' => 'agenda',
            'call_center_human_service' => 'call_center',
            'call_center_ai_fallback' => 'call_center',
            'call_center_voice_channel' => 'call_center',
            'call_center_whatsapp_channel' => 'call_center',
        ];
        return $map[$capabilityCode] ?? null;
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
