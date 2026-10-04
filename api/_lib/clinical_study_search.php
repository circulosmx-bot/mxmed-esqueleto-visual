<?php
declare(strict_types=1);

/** STUDY-SEARCH02: read-only discovery over active canonical study rows. */
function clinical_study_search_authority(): array
{
    static $authority = null;
    if ($authority !== null) return $authority;
    $path = __DIR__.'/../../modules/clinical/catalog/study_search_authority_v1.json';
    $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (($decoded['version'] ?? null) !== 1 || count($decoded['studies'] ?? []) !== 252) {
        throw new RuntimeException('STUDY_SEARCH_AUTHORITY_INVALID');
    }
    $byKey = [];
    foreach ($decoded['studies'] as $study) {
        $key = $study['study_key'] ?? null;
        if (!is_string($key) || $key === '' || isset($byKey[$key])) {
            throw new RuntimeException('STUDY_SEARCH_AUTHORITY_INVALID');
        }
        $byKey[$key] = $study;
    }
    $approvedExactTerms = [];
    foreach ($byKey as $study) {
        $terms = array_merge([$study['canonical_display_name']], $study['abbreviations'], $study['equivalent_aliases']);
        if ($study['common_display_name'] !== null) $terms[] = $study['common_display_name'];
        foreach ($terms as $term) $approvedExactTerms[clinical_study_search_normalize($term)] = true;
    }
    return $authority = ['config' => $decoded, 'by_key' => $byKey, 'approved_exact_terms' => $approvedExactTerms];
}

function clinical_study_search_normalize(string $value): string
{
    $value = Normalizer::normalize($value, Normalizer::FORM_KC);
    if ($value === false) return '';
    $value = mb_convert_case($value, MB_CASE_FOLD, 'UTF-8');
    $value = str_replace('β', 'beta', $value);
    $value = Normalizer::normalize($value, Normalizer::FORM_D);
    if ($value === false) return '';
    $value = preg_replace('/\p{Mn}/u', '', $value);
    return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $value));
}

function clinical_study_search_query_tokens(string $normalized): array
{
    if ($normalized === '') return [];
    $connectors = ['de', 'del', 'en', 'y'];
    return array_values(array_filter(explode(' ', $normalized),
        static fn(string $token): bool => !in_array($token, $connectors, true)));
}

function clinical_study_search_allowed(array $tokens, string $normalized, array $authority): bool
{
    if ($tokens === []) return false;
    $minimum = $authority['config']['prefix_min_length'];
    if (array_reduce($tokens, static fn(bool $valid, string $token): bool => $valid && strlen($token) >= $minimum, true)) return true;
    if (count($tokens) > 1 && isset($authority['config']['short_modality_heads'][$tokens[0]])
        && array_reduce(array_slice($tokens, 1), static fn(bool $valid, string $token): bool => $valid && strlen($token) >= $minimum, true)) return true;
    return in_array($normalized, $authority['config']['short_query_exceptions'], true)
        || isset($authority['approved_exact_terms'][$normalized]);
}

function clinical_study_search_phrases(array $row, array $authority): array
{
    $key = (string)$row['study_type_key'];
    $entry = $authority['by_key'][$key] ?? [];
    $phrases = [['CANONICAL_NAME', (string)$row['display_name_es']]];
    foreach ($entry['abbreviations'] ?? [] as $term) $phrases[] = ['ABBREVIATION', $term];
    foreach ($entry['equivalent_aliases'] ?? [] as $term) $phrases[] = ['ALIAS_EQUIVALENT', $term];
    $known = [];
    foreach ($phrases as [, $term]) $known[clinical_study_search_normalize($term)] = true;
    // The live catalog remains the identity authority if an alias is added later.
    foreach (json_decode((string)$row['aliases_json'], true, 512, JSON_THROW_ON_ERROR) as $term) {
        $normalized = clinical_study_search_normalize((string)$term);
        if ($normalized !== '' && !isset($known[$normalized])) {
            $phrases[] = ['ALIAS_EQUIVALENT', (string)$term];
            $known[$normalized] = true;
        }
    }
    if (!empty($entry['common_display_name'])) $phrases[] = ['COMMON_NAME', $entry['common_display_name']];
    foreach ($entry['discovery_terms'] ?? [] as $term) $phrases[] = ['DISCOVERY_TERM', $term];
    $phrases[] = ['STABLE_KEY', str_replace('_', ' ', $key)];
    return $phrases;
}

function clinical_study_search_token_prefixes(array $queryTokens, array $phraseTokens): bool
{
    $used = [];
    foreach ($queryTokens as $queryToken) {
        $found = false;
        foreach ($phraseTokens as $index => $phraseToken) {
            if (!isset($used[$index]) && str_starts_with($phraseToken, $queryToken)) {
                $used[$index] = true;
                $found = true;
                break;
            }
        }
        if (!$found) return false;
    }
    return true;
}

/** Returns the SEARCH01 tier, unmatched token count and phrase length. */
function clinical_study_search_score(array $row, string $query, array $authority): ?array
{
    $normalized = clinical_study_search_normalize($query);
    $queryTokens = clinical_study_search_query_tokens($normalized);
    if (!clinical_study_search_allowed($queryTokens, $normalized, $authority)) return null;
    $best = null;
    $modalityHeads = $authority['config']['short_modality_heads'][$normalized] ?? null;
    $exactShortTerm = $modalityHeads === null
        && !in_array($normalized, $authority['config']['short_query_exceptions'], true)
        && isset($authority['approved_exact_terms'][$normalized])
        && count(array_filter($queryTokens, static fn(string $token): bool => strlen($token) < $authority['config']['prefix_min_length'])) > 0;
    foreach (clinical_study_search_phrases($row, $authority) as [$kind, $phrase]) {
        $text = clinical_study_search_normalize($phrase);
        if ($text === '') continue;
        $phraseTokens = explode(' ', $text);
        if ($modalityHeads !== null && ($kind !== 'ABBREVIATION' || !in_array($phraseTokens[0], $modalityHeads, true))) continue;
        if ($exactShortTerm && ($text !== $normalized || in_array($kind, ['DISCOVERY_TERM', 'STABLE_KEY'], true))) continue;
        if ($kind === 'CANONICAL_NAME' && $normalized === $text) $tier = 1;
        elseif ($kind === 'ABBREVIATION' && $normalized === $text) $tier = 2;
        elseif (in_array($kind, ['ALIAS_EQUIVALENT', 'STABLE_KEY'], true) && $normalized === $text) $tier = 3;
        elseif ($kind === 'CANONICAL_NAME' && count($queryTokens) === 1 && str_starts_with($phraseTokens[0], $normalized)) $tier = 4;
        elseif ($kind === 'ALIAS_EQUIVALENT' && count($queryTokens) === 1 && str_starts_with($phraseTokens[0], $normalized)) $tier = 5;
        elseif ($kind === 'COMMON_NAME' && $normalized === $text) $tier = 7;
        elseif ($kind === 'DISCOVERY_TERM' && $normalized === $text) $tier = 8;
        elseif (clinical_study_search_token_prefixes($queryTokens, $phraseTokens)) {
            $tier = match ($kind) {
                'CANONICAL_NAME', 'ABBREVIATION', 'ALIAS_EQUIVALENT' => 6,
                'COMMON_NAME' => 7,
                'DISCOVERY_TERM' => 8,
                default => 9,
            };
        } else continue;
        $candidate = [$tier, count($phraseTokens) - count($queryTokens), strlen($text)];
        if ($best === null || $candidate < $best) $best = $candidate;
    }
    return $best;
}

function clinical_study_search_ranked_rows(array $rows, string $query, array $authority): array
{
    $ranked = [];
    foreach ($rows as $row) {
        $score = clinical_study_search_score($row, $query, $authority);
        if ($score !== null) $ranked[] = ['row' => $row, 'score' => $score];
    }
    usort($ranked, static function (array $left, array $right): int {
        foreach ([0, 1, 2] as $index) {
            $comparison = $left['score'][$index] <=> $right['score'][$index];
            if ($comparison !== 0) return $comparison;
        }
        return strcmp((string)$left['row']['study_type_key'], (string)$right['row']['study_type_key']);
    });
    return array_column($ranked, 'row');
}
