<?php
declare(strict_types=1);
namespace Media\Services;
final class MediaReplacementReasons
{
    public const LABELS=[
        'WRONG_MEDIA_TYPE'=>'La imagen no corresponde al tipo solicitado.',
        'QUALITY_INSUFFICIENT'=>'La calidad de la imagen no es suficiente.',
        'CONTENT_NOT_APPROPRIATE'=>'La imagen no es adecuada para publicarse.',
        'OTHER'=>'Se requiere otra imagen.',
    ];
    public const MAX_FEEDBACK_CHARS=400;
    public static function validate(mixed $reason,mixed $feedback):array
    {
        if(!is_string($reason)||!isset(self::LABELS[$reason])||!is_string($feedback)||!preg_match('//u',$feedback))throw new \RuntimeException('replacement_invalid_request');
        $feedback=preg_replace('/^\s+|\s+$/u','',$feedback);
        if(preg_match_all('/./us',$feedback)>self::MAX_FEEDBACK_CHARS||str_contains($feedback,"\0"))throw new \RuntimeException('replacement_invalid_request');
        return [$reason,$feedback===''?null:$feedback];
    }
}
