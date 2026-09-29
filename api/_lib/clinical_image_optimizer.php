<?php
declare(strict_types=1);

/** Shared image transformation policy: legacy and private clinical writers. */
function clinical_gd_supports_webp(): bool
{
    if (!function_exists('gd_info')) {
        return false;
    }
    $info = gd_info();
    return (bool)($info['WebP Support'] ?? false);
}

function clinical_is_image_handle($value): bool
{
    return is_resource($value) || is_object($value);
}

function clinical_image_has_alpha($image): bool
{
    if (!clinical_is_image_handle($image)) {
        return false;
    }
    $w = imagesx($image);
    $h = imagesy($image);
    if ($w <= 0 || $h <= 0) {
        return false;
    }
    $stepX = max(1, (int)floor($w / 48));
    $stepY = max(1, (int)floor($h / 48));
    for ($y = 0; $y < $h; $y += $stepY) {
        for ($x = 0; $x < $w; $x += $stepX) {
            $rgba = imagecolorat($image, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;
            if ($alpha > 0) {
                return true;
            }
        }
    }
    return false;
}

function clinical_image_load_resource(string $tmpPath, string $mime)
{
    if ($mime === 'image/jpeg') {
        return @imagecreatefromjpeg($tmpPath);
    }
    if ($mime === 'image/png') {
        return @imagecreatefrompng($tmpPath);
    }
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($tmpPath);
    }
    return false;
}

function clinical_prepare_png_for_webp($image): bool
{
    if (!clinical_is_image_handle($image)) {
        return false;
    }
    if (function_exists('imageistruecolor') && !imageistruecolor($image)) {
        if (!function_exists('imagepalettetotruecolor')) {
            return false;
        }
        $ok = @imagepalettetotruecolor($image);
        if (!$ok) {
            return false;
        }
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);
    return true;
}

function clinical_image_fix_orientation($image, string $tmpPath, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }
    $exif = @exif_read_data($tmpPath);
    $orientation = (int)($exif['Orientation'] ?? 1);
    if ($orientation === 1) {
        return $image;
    }

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
            $rot = imagerotate($image, -90, 0);
            imageflip($rot, IMG_FLIP_HORIZONTAL);
            return $rot;
        case 6:
            return imagerotate($image, -90, 0);
        case 7:
            $rot = imagerotate($image, 90, 0);
            imageflip($rot, IMG_FLIP_HORIZONTAL);
            return $rot;
        case 8:
            return imagerotate($image, 90, 0);
        default:
            return $image;
    }
}

function clinical_image_resize($source, int $maxSide)
{
    $srcW = imagesx($source);
    $srcH = imagesy($source);
    if ($srcW <= 0 || $srcH <= 0) {
        return false;
    }
    $long = max($srcW, $srcH);
    if ($long <= $maxSide) {
        return $source;
    }
    $ratio = $maxSide / $long;
    $dstW = max(1, (int)round($srcW * $ratio));
    $dstH = max(1, (int)round($srcH * $ratio));
    $dst = imagecreatetruecolor($dstW, $dstH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
    imagecopyresampled($dst, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    return $dst;
}

function clinical_save_image_variant($image, string $absPath, string $format, int $quality): bool
{
    if ($format === 'webp' && function_exists('imagewebp')) {
        return (bool)@imagewebp($image, $absPath, $quality);
    }
    if ($format === 'jpeg') {
        $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
        $white = imagecolorallocate($bg, 255, 255, 255);
        imagefilledrectangle($bg, 0, 0, imagesx($bg), imagesy($bg), $white);
        imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        $ok = (bool)@imagejpeg($bg, $absPath, $quality);
        return $ok;
    }
    if ($format === 'png') {
        return (bool)@imagepng($image, $absPath, 6);
    }
    return false;
}

function clinical_optimize_uploaded_image(array $file, string $documentUuid, ?string $privateOutputDir = null): array
{
    $tmpPath = (string)($file['tmp_name'] ?? '');
    $rawBytes = (int)(@filesize($tmpPath) ?: 0);
    if ($tmpPath === '' || !is_file($tmpPath)) {
        throw new RuntimeException('archivo temporal inválido');
    }
    if ($rawBytes <= 0 || $rawBytes > (25 * 1024 * 1024)) {
        throw new RuntimeException('tamaño de imagen inválido (máximo 25MB)');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower(trim((string)$finfo->file($tmpPath)));
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('solo se permiten imágenes jpeg/png/webp');
    }

    $size = @getimagesize($tmpPath);
    if (!is_array($size)) {
        throw new RuntimeException('imagen inválida');
    }
    $origW = (int)($size[0] ?? 0);
    $origH = (int)($size[1] ?? 0);
    if ($origW <= 0 || $origH <= 0 || $origW > 10000 || $origH > 10000) {
        throw new RuntimeException('dimensiones de imagen inválidas');
    }

    $source = clinical_image_load_resource($tmpPath, $mime);
    if ($source === false) {
        throw new RuntimeException('no se pudo decodificar imagen');
    }
    $oriented = clinical_image_fix_orientation($source, $tmpPath, $mime);
    $source = $oriented;

    $maxImage = clinical_image_resize($source, 2048);
    if ($maxImage === false) {
        throw new RuntimeException('no se pudo redimensionar imagen');
    }
    $source = $maxImage;

    $keepPng = ($mime === 'image/png') && clinical_image_has_alpha($source);
    $supportsWebp = clinical_gd_supports_webp();
    if ($mime === 'image/png' && !$keepPng && $supportsWebp) {
        // PNG palettized -> convert to truecolor before WebP encode.
        if (!clinical_prepare_png_for_webp($source)) {
            // Safe fallback: keep optimized PNG when conversion is unavailable/fails.
            $keepPng = true;
        }
    }
    $targetFormat = $keepPng ? 'png' : ($supportsWebp ? 'webp' : 'jpeg');
    $targetMime = $targetFormat === 'png' ? 'image/png' : ($targetFormat === 'webp' ? 'image/webp' : 'image/jpeg');
    $targetExt = $targetFormat === 'png' ? 'png' : ($targetFormat === 'webp' ? 'webp' : 'jpg');

    $thumbSource = clinical_image_resize($source, 480);
    if ($thumbSource === false) {
        throw new RuntimeException('no se pudo generar thumbnail');
    }

    $thumbFormat = $keepPng ? 'png' : ($supportsWebp ? 'webp' : 'jpeg');
    $thumbMime = $thumbFormat === 'png' ? 'image/png' : ($thumbFormat === 'webp' ? 'image/webp' : 'image/jpeg');
    $thumbExt = $thumbFormat === 'png' ? 'png' : ($thumbFormat === 'webp' ? 'webp' : 'jpg');

    $year = gmdate('Y');
    $month = gmdate('m');
    $baseDir = $privateOutputDir !== null ? $privateOutputDir : rtrim(clinical_uploads_root_dir(), '/');
    $relDir = $privateOutputDir !== null ? '' : rtrim(clinical_uploads_relative_dir(), '/');
    $folderAbs = $privateOutputDir ?? ($baseDir . '/' . $year . '/' . $month);
    $folderRel = $relDir . '/' . $year . '/' . $month;
    if (!is_dir($folderAbs) && !@mkdir($folderAbs, 0775, true) && !is_dir($folderAbs)) {
        throw new RuntimeException('no se pudo crear directorio de uploads');
    }

    $optFilename = $documentUuid . '-opt.' . $targetExt;
    $thumbFilename = $documentUuid . '-thumb.' . $thumbExt;
    $optAbs = $folderAbs . '/' . $optFilename;
    $thumbAbs = $folderAbs . '/' . $thumbFilename;
    $optRel = $privateOutputDir !== null ? $optFilename : $folderRel . '/' . $optFilename;
    $thumbRel = $privateOutputDir !== null ? $thumbFilename : $folderRel . '/' . $thumbFilename;

    $savedOpt = clinical_save_image_variant($source, $optAbs, $targetFormat, 80);
    if (!$savedOpt) {
        throw new RuntimeException('no se pudo guardar imagen optimizada');
    }
    $savedThumb = clinical_save_image_variant($thumbSource, $thumbAbs, $thumbFormat, 75);
    if (!$savedThumb) {
        throw new RuntimeException('no se pudo guardar thumbnail');
    }

    $optW = imagesx($source);
    $optH = imagesy($source);
    $thumbW = imagesx($thumbSource);
    $thumbH = imagesy($thumbSource);

    return [
        'render_mode' => 'image',
        'optimized' => [
            'path' => $optRel,
            'mime' => $targetMime,
            'bytes' => (int)(@filesize($optAbs) ?: 0),
            'w' => (int)$optW,
            'h' => (int)$optH,
        ],
        'thumb' => [
            'path' => $thumbRel,
            'mime' => $thumbMime,
            'bytes' => (int)(@filesize($thumbAbs) ?: 0),
            'w' => (int)$thumbW,
            'h' => (int)$thumbH,
        ],
        'original' => [
            'sha256' => hash_file('sha256', $tmpPath),
            'bytes' => (int)$rawBytes,
            'w' => (int)$origW,
            'h' => (int)$origH,
            'mime' => $mime,
        ],
    ];
}


/** Shared optimizer with private transient outputs; caller must clean in finally. */
function clinical_optimize_private_image(array $file): array
{
    $dir = sys_get_temp_dir() . '/mxmed-image-' . bin2hex(random_bytes(16));
    if (!mkdir($dir, 0700)) throw new RuntimeException('IMAGE_TEMP_CREATE_FAILED');
    try {
        $result = clinical_optimize_uploaded_image($file, bin2hex(random_bytes(16)), $dir);
        return ['directory' => $dir, 'manifest' => $result,
            'main' => $dir . '/' . $result['optimized']['path'],
            'thumbnail' => $dir . '/' . $result['thumb']['path']];
    } catch (Throwable $e) {
        foreach (glob($dir . '/*') ?: [] as $path) @unlink($path);
        @rmdir($dir);
        throw $e;
    }
}
function clinical_clean_private_image(?array $image): void
{
    if ($image === null) return;
    foreach (['main', 'thumbnail'] as $key) if (is_file($image[$key])) @unlink($image[$key]);
    @rmdir($image['directory']);
}
