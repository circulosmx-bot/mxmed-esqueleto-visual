<?php
declare(strict_types=1);

namespace Media\Services;

use RuntimeException;

final class GdPublicLogoProcessor
{
    public const MAX_UPLOAD_BYTES = 2097152;
    public const MAX_WIDTH = 4096;
    public const MAX_HEIGHT = 4096;
    public const MAX_PIXEL_COUNT = 4000000;
    public const OUTPUT_MAX_EDGE = 800;
    public const TARGET_MAX_BYTES = 153600;
    public const OUTPUT_MIME = 'image/webp';

    private const INPUT_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return array{path:string,mime_type:string,format:string,width:int,height:int,byte_size:int,checksum_sha256:string,source_width:int,source_height:int,source_mime_type:string}
     */
    public function process(array $upload, bool $deleteSource = true): array
    {
        $sourcePath = trim((string)($upload['tmp_name'] ?? ''));
        $outputPath = null;
        $source = null;
        $working = null;

        try {
            $error = (int)($upload['error'] ?? UPLOAD_ERR_OK);
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('logo_upload_error_' . $error);
            }
            if ($sourcePath === '' || !is_file($sourcePath) || !is_readable($sourcePath)) {
                throw new RuntimeException('logo_upload_temporary_file_invalid');
            }

            $actualBytes = (int)(filesize($sourcePath) ?: 0);
            if ($actualBytes <= 0) {
                throw new RuntimeException('logo_upload_empty');
            }
            if ($actualBytes > self::MAX_UPLOAD_BYTES) {
                throw new RuntimeException('logo_upload_bytes_exceeded');
            }

            if (!extension_loaded('gd') || !function_exists('imagewebp')) {
                throw new RuntimeException('safe_logo_processor_unavailable');
            }

            $detectedMime = strtolower(trim((string)(new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath)));
            if (!in_array($detectedMime, self::INPUT_MIMES, true)) {
                throw new RuntimeException('logo_upload_unsupported_media_type');
            }
            $clientMime = strtolower(trim((string)($upload['type'] ?? '')));
            if ($clientMime !== '' && $clientMime !== $detectedMime) {
                throw new RuntimeException('logo_upload_media_type_mismatch');
            }

            $imageInfo = @getimagesize($sourcePath);
            if (!is_array($imageInfo)) {
                throw new RuntimeException('logo_upload_malformed_image');
            }
            $sourceWidth = (int)($imageInfo[0] ?? 0);
            $sourceHeight = (int)($imageInfo[1] ?? 0);
            $decodedMime = strtolower(trim((string)($imageInfo['mime'] ?? '')));
            if ($decodedMime !== $detectedMime) {
                throw new RuntimeException('logo_upload_decoded_media_type_mismatch');
            }
            if ($sourceWidth <= 0 || $sourceHeight <= 0) {
                throw new RuntimeException('logo_upload_dimensions_invalid');
            }
            if ($sourceWidth > self::MAX_WIDTH || $sourceHeight > self::MAX_HEIGHT) {
                throw new RuntimeException('logo_upload_dimensions_exceeded');
            }
            if (($sourceWidth * $sourceHeight) > self::MAX_PIXEL_COUNT) {
                throw new RuntimeException('logo_upload_pixel_count_exceeded');
            }

            $source = $this->decode($sourcePath, $detectedMime);
            if (!$this->isImage($source)) {
                throw new RuntimeException('logo_upload_safe_decode_failed');
            }
            $oriented = $this->applyJpegOrientation($source, $sourcePath, $detectedMime);
            if (!$this->isImage($oriented)) {
                throw new RuntimeException('logo_upload_orientation_failed');
            }
            if ($oriented !== $source) {
                $this->releaseImage($source);
                $source = $oriented;
            }

            $working = $this->resizeWithin($source, self::OUTPUT_MAX_EDGE);
            if (!$this->isImage($working)) {
                throw new RuntimeException('logo_derivative_resize_failed');
            }

            $outputPath = tempnam(sys_get_temp_dir(), 'mxmed-public-logo-');
            if (!is_string($outputPath) || $outputPath === '') {
                throw new RuntimeException('logo_derivative_temp_create_failed');
            }

            $qualityCandidates = [90, 84, 78, 72, 66, 60, 54];
            $encoded = false;
            foreach ($qualityCandidates as $quality) {
                if (!@imagewebp($working, $outputPath, $quality)) {
                    continue;
                }
                clearstatcache(true, $outputPath);
                if ((int)(filesize($outputPath) ?: 0) <= self::TARGET_MAX_BYTES) {
                    $encoded = true;
                    break;
                }
            }

            while (!$encoded && max(imagesx($working), imagesy($working)) > 160) {
                $nextEdge = max(160, (int)floor(max(imagesx($working), imagesy($working)) * 0.82));
                $smaller = $this->resizeWithin($working, $nextEdge);
                if (!$this->isImage($smaller) || $smaller === $working) {
                    break;
                }
                if ($working !== $source) {
                    $this->releaseImage($working);
                }
                $working = $smaller;
                foreach ([72, 64, 56] as $quality) {
                    if (@imagewebp($working, $outputPath, $quality)) {
                        clearstatcache(true, $outputPath);
                        if ((int)(filesize($outputPath) ?: 0) <= self::TARGET_MAX_BYTES) {
                            $encoded = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$encoded) {
                throw new RuntimeException('logo_derivative_byte_budget_unmet');
            }

            $outputInfo = @getimagesize($outputPath);
            $outputBytes = (int)(filesize($outputPath) ?: 0);
            if (!is_array($outputInfo)
                || strtolower((string)($outputInfo['mime'] ?? '')) !== self::OUTPUT_MIME
                || $outputBytes <= 0
                || $outputBytes > self::TARGET_MAX_BYTES) {
                throw new RuntimeException('logo_derivative_validation_failed');
            }

            $result = [
                'path' => $outputPath,
                'mime_type' => self::OUTPUT_MIME,
                'format' => 'webp',
                'width' => (int)$outputInfo[0],
                'height' => (int)$outputInfo[1],
                'byte_size' => $outputBytes,
                'checksum_sha256' => hash_file('sha256', $outputPath),
                'source_width' => $sourceWidth,
                'source_height' => $sourceHeight,
                'source_mime_type' => $detectedMime,
            ];
            $outputPath = null;
            return $result;
        } finally {
            if ($this->isImage($working) && $working !== $source) {
                $this->releaseImage($working);
            }
            if ($this->isImage($source)) {
                $this->releaseImage($source);
            }
            if (is_string($outputPath) && is_file($outputPath)) {
                unlink($outputPath);
            }
            if ($deleteSource && $sourcePath !== '' && is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    private function decode(string $path, string $mime)
    {
        if ($mime === 'image/jpeg') {
            return @imagecreatefromjpeg($path);
        }
        if ($mime === 'image/png') {
            return @imagecreatefrompng($path);
        }
        if ($mime === 'image/webp') {
            return @imagecreatefromwebp($path);
        }
        return false;
    }

    private function applyJpegOrientation($image, string $path, string $mime)
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = (int)($exif['Orientation'] ?? 1);
        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return $image;
            case 3:
                return imagerotate($image, 180, 0);
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                return $image;
            case 5:
                $rotated = imagerotate($image, -90, 0);
                if ($this->isImage($rotated)) {
                    imageflip($rotated, IMG_FLIP_HORIZONTAL);
                }
                return $rotated;
            case 6:
                return imagerotate($image, -90, 0);
            case 7:
                $rotated = imagerotate($image, 90, 0);
                if ($this->isImage($rotated)) {
                    imageflip($rotated, IMG_FLIP_HORIZONTAL);
                }
                return $rotated;
            case 8:
                return imagerotate($image, 90, 0);
            default:
                return $image;
        }
    }

    private function resizeWithin($source, int $maxEdge)
    {
        $width = imagesx($source);
        $height = imagesy($source);
        if (max($width, $height) <= $maxEdge) {
            return $source;
        }
        $scale = $maxEdge / max($width, $height);
        $nextWidth = max(1, (int)round($width * $scale));
        $nextHeight = max(1, (int)round($height * $scale));
        $target = imagecreatetruecolor($nextWidth, $nextHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $nextWidth, $nextHeight, $transparent);
        if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $nextWidth, $nextHeight, $width, $height)) {
            $this->releaseImage($target);
            return false;
        }
        return $target;
    }

    private function isImage($value): bool
    {
        return is_resource($value) || $value instanceof \GdImage;
    }

    private function releaseImage(&$image): void
    {
        if (PHP_VERSION_ID < 80500 && $this->isImage($image)) {
            imagedestroy($image);
        }
        $image = null;
    }
}
