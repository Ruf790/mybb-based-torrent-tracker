<?php
// report_captcha.php
// Генерирует код для CAPTCHA и рисует его в виде картинки с шумом.
// Код хранится ТОЛЬКО в сессии - клиент никогда не получает открытый ответ.

declare(strict_types=1);

require_once 'global.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($CURUSER['id'])) {
    http_response_code(403);
    exit;
}

if (!extension_loaded('gd')) {
    http_response_code(500);
    exit;
}

// ── Генерируем код ──────────────────────────────────────────
$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // без похожих символов (0/O, 1/I)
$code = '';
for ($i = 0; $i < 6; $i++) {
    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}

$_SESSION['report_captcha'] = [
    'code'    => $code,
    'created' => time(),
];

// ── Рисуем картинку ─────────────────────────────────────────
$width  = 160;
$height = 56;

$im = imagecreatetruecolor($width, $height);

$bg = imagecolorallocate($im, 245, 245, 248);
imagefilledrectangle($im, 0, 0, $width, $height, $bg);

// Шумовые линии для затруднения OCR
for ($i = 0; $i < 8; $i++) {
    $lineColor = imagecolorallocate($im, random_int(150, 210), random_int(150, 210), random_int(150, 210));
    imageline(
        $im,
        random_int(0, $width), random_int(0, $height),
        random_int(0, $width), random_int(0, $height),
        $lineColor
    );
}

// Шумовые точки
for ($i = 0; $i < 250; $i++) {
    $dotColor = imagecolorallocate($im, random_int(160, 220), random_int(160, 220), random_int(160, 220));
    imagesetpixel($im, random_int(0, $width - 1), random_int(0, $height - 1), $dotColor);
}

// Рисуем каждый символ отдельно со случайным смещением/цветом
$charSpacing = (int)($width / strlen($code));
for ($i = 0; $i < strlen($code); $i++) {
    $textColor = imagecolorallocate($im, random_int(30, 90), random_int(30, 90), random_int(30, 90));
    $x = 12 + $i * $charSpacing + random_int(-3, 3);
    $y = (int)($height / 2) + random_int(-8, 8);
    imagestring($im, 5, $x, $y - 8, $code[$i], $textColor);
}

// Волнистая линия поверх текста
$waveColor = imagecolorallocate($im, random_int(100, 160), random_int(100, 160), random_int(100, 160));
$prevY = (int)($height / 2);
for ($x = 0; $x < $width; $x += 4) {
    $y = (int)($height / 2 + sin($x / 8) * 6);
    imageline($im, $x, $prevY, $x + 4, $y, $waveColor);
    $prevY = $y;
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
imagepng($im);
// imagedestroy() убран - устарела с PHP 8.0, GD освобождает память сам.