<?php
declare(strict_types=1);

namespace Profiles\Services;

final class PublicDisplayNamePolicy
{
    // Space-delimited Unicode words are selectable components. More than eight
    // tokens or unusual punctuation remains one atomic given_names presentation.
    private const MAX_SAFE_GIVEN_NAME_TOKENS = 8;

    public function validate(?array $verifiedIdentity, ?string $requestedDisplayName): array
    {
        if ($verifiedIdentity === null) {
            return [
                'verified_identity_available' => false,
                'valid' => true,
                'canonical_display_name' => $requestedDisplayName,
            ];
        }

        $requested = $this->normalizeSpacing((string)($requestedDisplayName ?? ''));
        $canonicalNames = $this->allowedCanonicalDisplayNames($verifiedIdentity);
        $comparison = $this->comparisonForm($requested);
        foreach ($canonicalNames as $canonicalName) {
            if ($comparison !== '' && hash_equals($this->comparisonForm($canonicalName), $comparison)) {
                return [
                    'verified_identity_available' => true,
                    'valid' => true,
                    'canonical_display_name' => $canonicalName,
                ];
            }
        }

        return [
            'verified_identity_available' => true,
            'valid' => false,
            'canonical_display_name' => null,
        ];
    }

    public function describe(?array $verifiedIdentity, ?string $currentDisplayName): array
    {
        if ($verifiedIdentity === null) {
            return [
                'verified_identity_available' => false,
                'current_display_name' => $currentDisplayName,
                'allowed_given_name_presentations' => [],
                'first_surname_required' => false,
                'second_surname_optional' => true,
                'current_display_name_policy_status' => 'NOT_APPLICABLE',
            ];
        }

        $decision = $this->validate($verifiedIdentity, $currentDisplayName);
        return [
            'verified_identity_available' => true,
            'current_display_name' => $currentDisplayName,
            'allowed_given_name_presentations' => $this->allowedGivenNamePresentations($verifiedIdentity),
            'first_surname_required' => true,
            'second_surname_optional' => true,
            'current_display_name_policy_status' => $decision['valid'] ? 'VALID' : 'LEGACY_NONCONFORMING',
        ];
    }

    public function allowedGivenNamePresentations(array $verifiedIdentity): array
    {
        $givenNames = $this->normalizeSpacing((string)($verifiedIdentity['given_names'] ?? ''));
        if ($givenNames === '') {
            return [];
        }

        $tokens = preg_split('/ /u', $givenNames, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)
            || $tokens === []
            || count($tokens) > self::MAX_SAFE_GIVEN_NAME_TOKENS
            || !$this->tokensAreSafe($tokens)) {
            // Compound or unusual forms stay atomic; arbitrary free text is never accepted.
            return [$givenNames];
        }

        $presentations = [];
        $combinations = (1 << count($tokens));
        for ($mask = 1; $mask < $combinations; $mask++) {
            $selected = [];
            foreach ($tokens as $index => $token) {
                if (($mask & (1 << $index)) !== 0) {
                    $selected[] = $token;
                }
            }
            $presentations[] = implode(' ', $selected);
        }
        return array_values(array_unique($presentations));
    }

    private function allowedCanonicalDisplayNames(array $verifiedIdentity): array
    {
        $firstSurname = $this->normalizeSpacing((string)($verifiedIdentity['first_surname'] ?? ''));
        $secondSurname = $this->normalizeSpacing((string)($verifiedIdentity['second_surname'] ?? ''));
        if ($firstSurname === '') {
            return [];
        }

        $names = [];
        foreach ($this->allowedGivenNamePresentations($verifiedIdentity) as $givenPresentation) {
            $names[] = $givenPresentation . ' ' . $firstSurname;
            if ($secondSurname !== '') {
                $names[] = $givenPresentation . ' ' . $firstSurname . ' ' . $secondSurname;
            }
        }
        return array_values(array_unique($names));
    }

    private function tokensAreSafe(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (preg_match("~^\\p{L}[\\p{L}\\p{M}\\p{Pd}'’ʼ]*$~u", (string)$token) !== 1) {
                return false;
            }
        }
        return true;
    }

    private function comparisonForm(string $value): string
    {
        return mb_strtolower($this->unicodeNfc($this->normalizeSpacing($value)), 'UTF-8');
    }

    private function normalizeSpacing(string $value): string
    {
        $collapsed = preg_replace('/[\\p{Z}\\s]+/u', ' ', $value);
        return trim(is_string($collapsed) ? $collapsed : '');
    }

    private function unicodeNfc(string $value): string
    {
        if (class_exists('\\Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                return $normalized;
            }
        }
        return $value;
    }
}
