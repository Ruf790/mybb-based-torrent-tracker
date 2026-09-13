<?php
declare(strict_types=1);


if (!function_exists('is_animated_gif')) {
function is_animated_gif(string $path): bool
{
    if (!extension_loaded('imagick')) {
        return false;
    }
    try {
        $img = new \Imagick(_resolve_absolute_image_path($path));
        $count = $img->getNumberImages();
        $img->clear();
        return $count > 1;
    } catch (\Throwable $e) {
        return false;
    }
}
}


if (!function_exists('resize_animated_gif')) {
function resize_animated_gif(string $src, string $dst, int $maxW, int $maxH): bool
{
    if (!extension_loaded('imagick')) {
        return false;
    }
    try {
        $img = new \Imagick(_resolve_absolute_image_path($src));
        $img = $img->coalesceImages();

        foreach ($img as $frame) {
            $frame->thumbnailImage($maxW, $maxH, true, false);
        }

   
        $result = $img->writeImages($dst, true);
        $img->clear();
        return (bool)$result;
    } catch (\Throwable $e) {
        return false;
    }
}
}


if (!function_exists('_resolve_absolute_image_path')) {
function _resolve_absolute_image_path(string $path): string
{

    $real = realpath($path);
    return $real !== false ? $real : $path;
}
}


if (!function_exists('recode_image_via_imagick')) {
function recode_image_via_imagick(string $path): int|false
{
    if (!extension_loaded('imagick')) {
        error_log('recode_image_via_imagick: extension "imagick" not loaded');
        return false;
    }
    $absPath = _resolve_absolute_image_path($path);
    try {
        $img = new \Imagick($absPath);
        $frames = $img->getNumberImages();
        if ($frames > 1) {
            $img = $img->coalesceImages();
            $img = $img->optimizeImageLayers();
        }
        $result = $img->writeImages($absPath, true);
        $img->clear();
        if (!$result) {
            error_log('recode_image_via_imagick: writeImages() returned false for ' . $absPath);
            return false;
        }
        clearstatcache(true, $path);
        return @filesize($path);
    } catch (\Throwable $e) {
        error_log('recode_image_via_imagick: exception for ' . $absPath . ' - ' . get_class($e) . ': ' . $e->getMessage());
        return false;
    }
}
}


if (!function_exists('recode_image_file')) {
function recode_image_file(string $path, string $mime): int|false
{
    
    if ($mime === 'image/gif' && is_animated_gif($path)) {
        return recode_image_via_imagick($path);
    }

    if ($mime === 'image/webp' && extension_loaded('imagick')) {
        $result = recode_image_via_imagick($path);
        if ($result !== false) {
            return $result;
        }
        error_log('recode_image_file(webp): Imagick path failed for ' . $path . ', falling back to GD attempt');
    }

    $recodeMap = [
        'image/jpeg' => true,
        'image/png'  => true,
        'image/webp' => true,
        'image/gif'  => true,
    ];

    if (isset($recodeMap[$mime])) {
        $gdPath = _resolve_absolute_image_path($path);
        $info = @getimagesize($gdPath);
        if ($info) {
            _ensure_thumbnail_memory($info[0], $info[1], $info['bits'] ?? 8, $info['channels'] ?? 3);
        }

        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($gdPath),
            'image/png'  => @imagecreatefrompng($gdPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($gdPath) : false,
            'image/gif'  => @imagecreatefromgif($gdPath),
        };
        if ($img === false) {
            if ($mime === 'image/webp') {
                error_log('recode_image_file(webp): GD imagecreatefromwebp() returned false for ' . $gdPath);
            }
            return false;
        }
        if ($mime === 'image/png') {
      
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
        match ($mime) {
            'image/jpeg' => imagejpeg($img, $gdPath, 90),
            'image/png'  => imagepng($img, $gdPath, 9),
            'image/webp' => imagewebp($img, $gdPath, 90),
            'image/gif'  => imagegif($img, $gdPath),
        };
    }

    clearstatcache(true, $path);
    return @filesize($path);
}
}


if (!function_exists('create_thumbnail_via_imagick')) {
function create_thumbnail_via_imagick(string $src, string $dst, int $maxW, int $maxH): bool
{
    if (!extension_loaded('imagick')) {
        return false;
    }
    try {
        $absSrc = _resolve_absolute_image_path($src);
        $img = new \Imagick($absSrc);
        
        if ($img->getNumberImages() > 1) {
            $img->setIteratorIndex(0);
        }
        $img->thumbnailImage($maxW, $maxH, true, false);
        $result = $img->writeImage($dst);
        $img->clear();
        return (bool)$result;
    } catch (\Throwable $e) {
        error_log('create_thumbnail_via_imagick: exception for ' . $src . ' - ' . get_class($e) . ': ' . $e->getMessage());
        return false;
    }
}
}


if (!function_exists('create_thumbnail')) {
function create_thumbnail(string $src, string $dst, int $maxW, int $maxH, string $mime): bool
{
    
    if ($mime === 'image/webp' && extension_loaded('imagick')) {
        if (create_thumbnail_via_imagick($src, $dst, $maxW, $maxH)) {
            return true;
        }
        error_log('create_thumbnail(webp): Imagick path failed for ' . $src . ', falling back to GD attempt');
    }

    if (!extension_loaded('gd')) return false;

    $info = @getimagesize($src);
    if (!$info || !$info[0] || !$info[1]) return false;
    [$w, $h] = $info;

   
    _ensure_thumbnail_memory($w, $h, $info['bits'] ?? 8, $info['channels'] ?? 3);

    // Вычисляем новые размеры
    $ratio  = min($maxW / $w, $maxH / $h, 1);
    $newW   = (int)round($w * $ratio);
    $newH   = (int)round($h * $ratio);

    $src_img = match($mime) {
        'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($src),
        'image/png'               => @imagecreatefrompng($src),
        'image/gif'               => @imagecreatefromgif($src),
        'image/webp'              => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default                   => false,
    };

    if (!$src_img) {
        if ($mime === 'image/webp') {
            error_log('create_thumbnail(webp): GD imagecreatefromwebp() also returned false for ' . $src);
        }
        return false;
    }

    $dst_img = imagecreatetruecolor($newW, $newH);

 
    if (in_array($mime, ['image/png', 'image/webp'], true)) {
        imagealphablending($dst_img, false);
        imagesavealpha($dst_img, true);
        $transparent = imagecolorallocatealpha($dst_img, 0, 0, 0, 127);
        imagefilledrectangle($dst_img, 0, 0, $newW, $newH, $transparent);
    } elseif ($mime === 'image/gif') {
        $transIdx = imagecolortransparent($src_img);
        if ($transIdx >= 0 && $transIdx < imagecolorstotal($src_img)) {
            $c = imagecolorsforindex($src_img, $transIdx);
            $newIdx = imagecolorallocate($dst_img, $c['red'], $c['green'], $c['blue']);
            imagefill($dst_img, 0, 0, $newIdx);
            imagecolortransparent($dst_img, $newIdx);
        }
    }

    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $result = match($mime) {
        'image/jpeg', 'image/jpg' => imagejpeg($dst_img, $dst, 85),
        'image/png'               => imagepng($dst_img, $dst, 6),
        'image/gif'                => imagegif($dst_img, $dst),
        'image/webp'               => imagewebp($dst_img, $dst, 85),
        default                    => false,
    };

    return (bool)$result;
}
}


if (!function_exists('_ensure_thumbnail_memory')) {
function _ensure_thumbnail_memory(int $width, int $height, int $bitdepth = 8, int $channels = 3): void
{
    if (!function_exists('memory_get_usage')) {
        return;
    }

    $limitRaw = @ini_get('memory_limit');
    if (!$limitRaw || $limitRaw === '-1') {
        return;
    }

    $limitBytes = _parse_memory_limit_for_thumbnail($limitRaw);
    if ($limitBytes === null) {
        return;
    }

    $needed = (int)round(($width * $height * $bitdepth * $channels / 8) * 5) + 2_097_152;
    $free   = $limitBytes - memory_get_usage();

    if ($needed > $free) {
        @ini_set('memory_limit', _format_thumbnail_memory_limit($limitBytes + $needed, $limitRaw));
    }
}
}

if (!function_exists('_parse_memory_limit_for_thumbnail')) {
function _parse_memory_limit_for_thumbnail(string $raw): ?int
{
    if (!preg_match('#^(\d+)\s*([kmg])b?$#i', trim($raw), $m)) {
        return (int)$raw ?: null;
    }

    return (int)$m[1] * match(strtolower($m[2])) {
        'k' => 1_024,
        'm' => 1_048_576,
        'g' => 1_073_741_824,
        default => 1,
    };
}
}

if (!function_exists('_format_thumbnail_memory_limit')) {
function _format_thumbnail_memory_limit(int $bytes, string $originalRaw): string
{
    if (!preg_match('#^(\d+)\s*([kmg])b?$#i', trim($originalRaw), $m)) {
        return (string)$bytes;
    }

    return match(strtolower($m[2])) {
        'k' => ceil($bytes / 1_024)         . 'K',
        'm' => ceil($bytes / 1_048_576)     . 'M',
        'g' => ceil($bytes / 1_073_741_824) . 'G',
        default => (string)$bytes,
    };
}
}