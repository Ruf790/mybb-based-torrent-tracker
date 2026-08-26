<?php
declare(strict_types=1);

if (!function_exists('recode_image_file')) {
function recode_image_file(string $path, string $mime): int|false
{
    $recodeMap = [
        'image/jpeg' => true,
        'image/png'  => true,
        'image/webp' => true,
        'image/gif'  => true,
    ];

    if (isset($recodeMap[$mime])) {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif'  => @imagecreatefromgif($path),
        };
        if ($img === false) {
            return false;
        }
        if ($mime === 'image/png') {
            // Без этого PNG с прозрачностью теряет альфа-канал при
            // повторном сохранении — прозрачные области становятся
            // сплошными (проверено эмпирически: imagepng() без явного
            // imagesavealpha() записывает альфа=255 вместо исходного
            // значения, даже если сам объект $img загружен с прозрачностью).
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
        match ($mime) {
            'image/jpeg' => imagejpeg($img, $path, 90),
            'image/png'  => imagepng($img, $path, 9),
            'image/webp' => imagewebp($img, $path, 90),
            'image/gif'  => imagegif($img, $path),
        };
    }

    clearstatcache(true, $path);
    return @filesize($path);
}
}