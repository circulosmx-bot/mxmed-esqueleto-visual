<?php
declare(strict_types=1);

use Media\Services\GdPublicLogoProcessor;

require_once __DIR__ . '/../services/GdPublicLogoProcessor.php';

function media01Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function media01Image(string $format, int $width, int $height, bool $noise = false): string
{
    $path = tempnam(sys_get_temp_dir(), 'media01-input-');
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    if (!$noise) {
        $background = imagecolorallocatealpha($image, 18, 112, 190, 0);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $background);
    } else {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $value = ($x * 73 + $y * 151 + ($x ^ $y)) & 255;
                $color = ($value << 16) | ((($value * 3) & 255) << 8) | (255 - $value);
                imagesetpixel($image, $x, $y, $color);
            }
        }
    }
    $ok = match ($format) {
        'jpeg' => imagejpeg($image, $path, 92),
        'png' => imagepng($image, $path, $noise ? 0 : 6),
        'webp' => imagewebp($image, $path, 90),
        default => false,
    };
    $image = null;
    media01Assert($ok && is_file($path), 'synthetic image created: ' . $format);
    return $path;
}

function media01Upload(string $path, string $mime): array
{
    return ['tmp_name' => $path, 'type' => $mime, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'name' => 'logo.bin'];
}

function media01ExpectReject(GdPublicLogoProcessor $processor, string $path, string $mime, string $expected): void
{
    try {
        $processor->process(media01Upload($path, $mime), true);
        throw new RuntimeException('expected rejection: ' . $expected);
    } catch (RuntimeException $e) {
        media01Assert($e->getMessage() === $expected, 'exact rejection: ' . $expected . ', got ' . $e->getMessage());
        media01Assert(!is_file($path), 'failed upload temporary source removed: ' . $expected);
    }
}

$processor = new GdPublicLogoProcessor();
media01Assert(extension_loaded('gd') && function_exists('imagewebp'), 'GD WebP processor available');

foreach (['jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $format => $mime) {
    $input = media01Image($format, 320, 160);
    $result = $processor->process(media01Upload($input, $mime), true);
    media01Assert(!is_file($input), strtoupper($format) . ' temporary original removed');
    media01Assert($result['mime_type'] === 'image/webp' && $result['format'] === 'webp', strtoupper($format) . ' accepted and normalized');
    media01Assert($result['width'] === 320 && $result['height'] === 160, strtoupper($format) . ' not upscaled');
    media01Assert(abs(($result['width'] / $result['height']) - 2.0) < 0.001, strtoupper($format) . ' aspect ratio preserved');
    media01Assert($result['byte_size'] <= GdPublicLogoProcessor::TARGET_MAX_BYTES, strtoupper($format) . ' byte budget');
    media01Assert(preg_match('/^[a-f0-9]{64}$/', $result['checksum_sha256']) === 1, strtoupper($format) . ' checksum generated');
    unlink($result['path']);
}

$transparentPath = tempnam(sys_get_temp_dir(), 'media01-alpha-');
$transparentImage = imagecreatetruecolor(80, 40);
imagealphablending($transparentImage, false);
imagesavealpha($transparentImage, true);
$fullyTransparent = imagecolorallocatealpha($transparentImage, 0, 0, 0, 127);
imagefilledrectangle($transparentImage, 0, 0, 79, 39, $fullyTransparent);
$opaque = imagecolorallocatealpha($transparentImage, 0, 120, 180, 0);
imagefilledrectangle($transparentImage, 40, 0, 79, 39, $opaque);
imagepng($transparentImage, $transparentPath, 6);
$transparentImage = null;
$alphaResult = $processor->process(media01Upload($transparentPath, 'image/png'), true);
$alphaImage = imagecreatefromwebp($alphaResult['path']);
$alphaPixel = imagecolorsforindex($alphaImage, imagecolorat($alphaImage, 5, 5));
media01Assert((int)$alphaPixel['alpha'] > 100, 'PNG transparency preserved in WebP derivative');
$alphaImage = null;
unlink($alphaResult['path']);

$large = media01Image('png', 1000, 500, true);
$largeBytes = filesize($large);
$optimized = $processor->process(media01Upload($large, 'image/png'), true);
media01Assert($optimized['width'] <= 800 && $optimized['height'] <= 800, 'bounded output dimensions');
media01Assert(abs(($optimized['width'] / $optimized['height']) - 2.0) < 0.01, 'resized aspect ratio preserved');
media01Assert($optimized['byte_size'] < $largeBytes, 'output is smaller than synthetic source');
media01Assert($optimized['byte_size'] <= 153600, 'optimized output is at most 150 KiB');
unlink($optimized['path']);

$metadata = media01Image('jpeg', 240, 120);
file_put_contents($metadata, 'GPSLatitude=19.4326;Device=MEDIA01_TEST', FILE_APPEND);
$metadataResult = $processor->process(media01Upload($metadata, 'image/jpeg'), true);
$derivativeBytes = file_get_contents($metadataResult['path']);
media01Assert(!str_contains((string)$derivativeBytes, 'GPSLatitude'), 'source GPS metadata absent');
media01Assert(!str_contains((string)$derivativeBytes, 'MEDIA01_TEST'), 'source device metadata absent');
unlink($metadataResult['path']);

$svg = tempnam(sys_get_temp_dir(), 'media01-svg-');
file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>');
media01ExpectReject($processor, $svg, 'image/svg+xml', 'logo_upload_unsupported_media_type');

$spoofed = media01Image('png', 20, 20);
media01ExpectReject($processor, $spoofed, 'image/jpeg', 'logo_upload_media_type_mismatch');

$malformed = tempnam(sys_get_temp_dir(), 'media01-malformed-');
file_put_contents($malformed, "\x89PNG\r\n\x1a\nnot-an-image");
media01ExpectReject($processor, $malformed, 'image/png', 'logo_upload_unsupported_media_type');

$oversized = tempnam(sys_get_temp_dir(), 'media01-bytes-');
$handle = fopen($oversized, 'wb');
fseek($handle, GdPublicLogoProcessor::MAX_UPLOAD_BYTES);
fwrite($handle, 'x');
fclose($handle);
media01ExpectReject($processor, $oversized, 'image/png', 'logo_upload_bytes_exceeded');

$wide = media01Image('png', GdPublicLogoProcessor::MAX_WIDTH + 1, 1);
media01ExpectReject($processor, $wide, 'image/png', 'logo_upload_dimensions_exceeded');

$pixelBomb = media01Image('png', 2500, 2000);
media01ExpectReject($processor, $pixelBomb, 'image/png', 'logo_upload_pixel_count_exceeded');

echo "PublicLogoProcessorTest PASS\n";
