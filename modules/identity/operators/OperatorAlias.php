<?php
declare(strict_types=1);
namespace Identity\Operators;

/** Presentation only. Never an account locator or authorization identifier. */
final class OperatorAlias
{
    /** @return array{display:?string,key:?string} */
    public static function normalize(mixed $value): array
    {
        if($value===null||$value==='')return ['display'=>null,'key'=>null];
        if(!is_string($value)||!extension_loaded('intl')||!extension_loaded('mbstring')||!mb_check_encoding($value,'UTF-8'))
            throw new \InvalidArgumentException('operator_alias_invalid');
        $value=preg_replace('/\A\s+|\s+\z/u','',$value);
        if($value==='')return ['display'=>null,'key'=>null];
        $display=\Normalizer::normalize($value,\Normalizer::FORM_C);
        if($display===false||strlen($display)>96||mb_strlen($display,'UTF-8')>24||!preg_match('/\A\p{L}[\p{L}\p{M}]*\z/uD',$display))
            throw new \InvalidArgumentException('operator_alias_invalid');
        $key=\Normalizer::normalize(mb_convert_case($display,MB_CASE_FOLD,'UTF-8'),\Normalizer::FORM_C);
        if($key===false||strlen($key)>192)throw new \InvalidArgumentException('operator_alias_invalid');
        return ['display'=>$display,'key'=>$key];
    }
}
