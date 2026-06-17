<?php
declare(strict_types=1);

namespace Patients\Validators;

class PatientNameValidator
{
    public const INVALID_MESSAGE = 'Captura un nombre de paciente válido.';

    private const GENERIC_NAME_VALUES = [
        'paciente',
        'sin nombre',
        'sin nombre registrado',
        'no registrado',
        'no especificado',
        'prueba',
        'test',
        'xxx',
        'abc',
    ];

    public static function normalizeNameValue($value): string
    {
        if ($value === null || !is_scalar($value)) {
            return '';
        }
        $text = (string)$value;
        $collapsed = preg_replace('/\s+/u', ' ', $text);
        if (!is_string($collapsed)) {
            $collapsed = preg_replace('/\s+/', ' ', $text);
        }
        return trim(is_string($collapsed) ? $collapsed : $text);
    }

    public static function normalizeDisplayName($value): string
    {
        return self::normalizeNameValue($value);
    }

    public static function validateNameValue($value, array $options = []): array
    {
        $required = ($options['required'] ?? false) === true;
        if ($value !== null && !is_scalar($value)) {
            return self::invalid('', 'must_be_scalar');
        }

        $normalized = self::normalizeNameValue($value);
        if ($normalized === '') {
            return $required
                ? self::invalid('', 'required')
                : ['valid' => true, 'value' => '', 'code' => '', 'message' => ''];
        }

        if (self::isGenericNameValue($normalized) || self::hasInvalidCharacters($normalized)) {
            return self::invalid($normalized, 'invalid_name');
        }

        return ['valid' => true, 'value' => $normalized, 'code' => '', 'message' => ''];
    }

    public static function isGenericNameValue($value): bool
    {
        $folded = self::foldNameValue($value);
        if ($folded === '') {
            return false;
        }
        if (in_array($folded, self::GENERIC_NAME_VALUES, true)) {
            return true;
        }
        foreach (self::GENERIC_NAME_VALUES as $generic) {
            if (strpos($folded, $generic . ' ') === 0) {
                return true;
            }
        }
        return preg_match('/^paciente\s*(nuevo|rapido)?$/u', $folded) === 1;
    }

    public static function hasInvalidCharacters($value): bool
    {
        $normalized = self::normalizeNameValue($value);
        if ($normalized === '') {
            return false;
        }
        if (preg_match('/\p{N}/u', $normalized) === 1) {
            return true;
        }
        if (preg_match('/\p{L}/u', $normalized) !== 1) {
            return true;
        }
        return preg_match('/^[\p{L}\p{M}]+(?:[ \'\x{2019}-][\p{L}\p{M}]+)*$/u', $normalized) !== 1;
    }

    private static function invalid(string $value, string $code): array
    {
        return [
            'valid' => false,
            'value' => $value,
            'code' => $code,
            'message' => self::INVALID_MESSAGE,
        ];
    }

    private static function foldNameValue($value): string
    {
        $normalized = self::normalizeNameValue($value);
        $lower = function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);

        return strtr($lower, [
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'Á' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ë' => 'e',
            'ê' => 'e',
            'É' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ï' => 'i',
            'î' => 'i',
            'Í' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ö' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'Ó' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ü' => 'u',
            'û' => 'u',
            'Ú' => 'u',
        ]);
    }
}
