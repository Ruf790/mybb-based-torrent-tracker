<?php
declare(strict_types=1);
// Requires PHP 8.2+

/**
 * Результат генерации миниатюры.
 */
readonly class ThumbnailResult
{
    public function __construct(
        public int     $code,
        public ?string $filename = null,
    ) {}

    public function isSuccess(): bool  { return $this->code === 1; }
    public function isNoResize(): bool { return $this->code === 4; }
}

/**
 * Поддерживаемые типы изображений.
 */
enum ImageType: int
{
    case Jpeg = IMAGETYPE_JPEG;
    case Png  = IMAGETYPE_PNG;
    case Gif  = IMAGETYPE_GIF;
    case Webp = IMAGETYPE_WEBP;

    public static function tryFromConstant(int $value): ?self
    {
        return match($value) {
            IMAGETYPE_JPEG => self::Jpeg,
            IMAGETYPE_PNG  => self::Png,
            IMAGETYPE_GIF  => self::Gif,
            IMAGETYPE_WEBP => self::Webp,
            default        => null,
        };
    }

    /** Загрузить GD-ресурс из файла. */
    public function load(string $file): \GdImage|false
    {
        return match($this) {
            self::Jpeg => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file) : false,
            self::Png  => function_exists('imagecreatefrompng')  ? @imagecreatefrompng($file)  : false,
            self::Gif  => function_exists('imagecreatefromgif')  ? @imagecreatefromgif($file)  : false,
            self::Webp => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
        };
    }

    /** Сохранить GD-ресурс в файл. */
    public function save(\GdImage $image, string $dest): void
    {
        match($this) {
            self::Jpeg => @imagejpeg($image, $dest, 90),
            self::Png  => @imagepng($image, $dest, 6),
            self::Gif  => function_exists('imagegif')
                            ? @imagegif($image, $dest)
                            : @imagejpeg($image, $dest),
            self::Webp => function_exists('imagewebp')
                            ? @imagewebp($image, $dest, 80)
                            : @imagejpeg($image, $dest),
        };
    }
}

/**
 * Размеры изображения.
 */
readonly class ImageDimensions
{
    public function __construct(
        public int $width,
        public int $height,
    ) {}

    public function isValid(): bool { return $this->width > 0 && $this->height > 0; }

    public function exceedsLimit(int $maxWidth, int $maxHeight): bool
    {
        return $this->width >= $maxWidth || $this->height >= $maxHeight;
    }

    /** Вписать в maxWidth × maxHeight с сохранением пропорций. */
    public function scaleTo(int $maxWidth, int $maxHeight): self
    {
        $w = $this->width  ?: $maxWidth;
        $h = $this->height ?: $maxHeight;

        if ($w > $maxWidth) {
            $h = (int)ceil($h * $maxWidth / $w);
            $w = $maxWidth;
        }
        if ($h > $maxHeight) {
            $w = (int)ceil($w * $maxHeight / $h);
            $h = $maxHeight;
        }

        return new self($w, $h);
    }
}

// ---------------------------------------------------------------------------
// Публичное API
// ---------------------------------------------------------------------------

/**
 * Генерирует миниатюру изображения.
 *
 * @param string $file      Полный путь к исходному файлу.
 * @param string $path      Директория для сохранения миниатюры.
 * @param string $filename  Имя файла миниатюры.
 * @param int    $maxHeight Максимальная высота.
 * @param int    $maxWidth  Максимальная ширина.
 */
function generate_thumbnail(
    string $file,
    string $path,
    string $filename,
    int|string $maxHeight,
    int|string $maxWidth,
): array {
    $maxHeight = (int)$maxHeight;
    $maxWidth  = (int)$maxWidth;
    if (!function_exists('imagecreate')) {
        return ['code' => 3];
    }

    $meta = _read_image_meta($file);
    if ($meta === null) {
        return ['code' => 3];
    }

    [$dims, $type, $bits, $channels] = $meta;

    if (!$dims->isValid()) {
        return ['code' => 3];
    }

    if (!$dims->exceedsLimit($maxWidth, $maxHeight)) {
        return ['code' => 4]; // миниатюра не нужна
    }

    check_thumbnail_memory(
        width:    $dims->width,
        height:   $dims->height,
        type:     $type->value,
        bitdepth: $bits,
        channels: $channels,
    );

    $source = $type->load($file);
    if (!$source instanceof \GdImage) {
        return ['code' => 3];
    }

    $scaled       = $dims->scaleTo($maxWidth, $maxHeight);
    $usedFallback = false;
    $thumb        = _create_canvas($scaled->width, $scaled->height, $usedFallback);

    if (!$thumb instanceof \GdImage) {
        imagedestroy($source);
        return ['code' => 3];
    }

    _apply_transparency($thumb, $source, $type);

    if ($usedFallback) {
        @imagecopyresized($thumb, $source, 0, 0, 0, 0, $scaled->width, $scaled->height, $dims->width, $dims->height);
    } else {
        @imagecopyresampled($thumb, $source, 0, 0, 0, 0, $scaled->width, $scaled->height, $dims->width, $dims->height);
    }

    imagedestroy($source);

    $dest = $path . '/' . $filename;
    $type->save($thumb, $dest);
    @my_chmod($dest, '0644');
    imagedestroy($thumb);

    return ['code' => 1, 'filename' => $filename];
}

/**
 * Пытается выделить достаточно памяти для генерации миниатюры.
 */
function check_thumbnail_memory(
    int $width,
    int $height,
    int $type,
    ?int $bitdepth,
    ?int $channels,
): bool {
    if (!function_exists('memory_get_usage')) {
        return false;
    }

    $limitRaw = @ini_get('memory_limit');
    if (!$limitRaw || $limitRaw === '-1') {
        return false;
    }

    $limitBytes = _parse_memory_limit($limitRaw);
    if ($limitBytes === null) {
        return false;
    }

    $needed = (int)round(($width * $height * ($bitdepth ?? 8) * ($channels ?? 3) / 8) * 5) + 2_097_152;
    $free   = $limitBytes - memory_get_usage();

    if ($needed > $free) {
        @ini_set('memory_limit', _format_memory_limit($limitBytes + $needed, $limitRaw));
    }

    return true;
}

/**
 * Вычисляет новые размеры, вписывающие изображение в maxWidth × maxHeight.
 *
 * @return array{width: int, height: int}
 */
function scale_image(int|string $width, int|string $height, int|string $maxWidth, int|string $maxHeight): array
{
    $dims = (new ImageDimensions((int)$width, (int)$height))->scaleTo((int)$maxWidth, (int)$maxHeight);
    return ['width' => $dims->width, 'height' => $dims->height];
}

// ---------------------------------------------------------------------------
// Внутренние хелперы (префикс _ — не публичное API)
// ---------------------------------------------------------------------------

/**
 * Читает метаданные изображения.
 *
 * @return array{ImageDimensions, ImageType, int, int}|null
 */
function _read_image_meta(string $file): ?array
{
    $info = @getimagesize($file);
    if (!$info || !isset($info[0], $info[1], $info[2])) {
        return null;
    }

    $type = ImageType::tryFromConstant($info[2]);
    if ($type === null) {
        return null;
    }

    return [
        new ImageDimensions($info[0], $info[1]),
        $type,
        $info['bits']     ?? 8,
        $info['channels'] ?? 3,
    ];
}

/**
 * Создаёт холст нужного размера.
 * Пробует imagecreatetruecolor, при неудаче — imagecreate.
 *
 * @param bool $usedFallback  Устанавливается в true если использован imagecreate.
 */
function _create_canvas(int $width, int $height, bool &$usedFallback = false): \GdImage|false
{
    $usedFallback = false;
    $canvas = @imagecreatetruecolor($width, $height);

    if (!$canvas instanceof \GdImage) {
        $usedFallback = true;
        $canvas = @imagecreate($width, $height);
    }

    return $canvas;
}

/**
 * Настраивает прозрачность холста в зависимости от типа изображения.
 */
function _apply_transparency(\GdImage $thumb, \GdImage $source, ImageType $type): void
{
    match($type) {
        ImageType::Png, ImageType::Webp => (static function () use ($thumb): void {
            imagealphablending($thumb, false);
            imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            imagesavealpha($thumb, true);
        })(),

        ImageType::Gif => (static function () use ($thumb, $source): void {
            $transIdx = imagecolortransparent($source);
            if ($transIdx >= 0 && $transIdx < imagecolorstotal($source)) {
                $c = imagecolorsforindex($source, $transIdx);
                $newIdx = imagecolorallocate($thumb, $c['red'], $c['green'], $c['blue']);
                imagefill($thumb, 0, 0, $newIdx);
                imagecolortransparent($thumb, $newIdx);
            }
        })(),

        default => null,
    };
}

/**
 * Разбирает строку лимита памяти PHP в байты.
 */
function _parse_memory_limit(string $raw): ?int
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

/**
 * Форматирует новый лимит памяти в той же единице, что и исходный.
 */
function _format_memory_limit(int $bytes, string $originalRaw): string
{
    if (!preg_match('#^(\d+)\s*([kmg])b?$#i', trim($originalRaw), $m)) {
        return (string)$bytes;
    }

    return match(strtolower($m[2])) {
        'k' => ceil($bytes / 1_024)      . 'K',
        'm' => ceil($bytes / 1_048_576)  . 'M',
        'g' => ceil($bytes / 1_073_741_824) . 'G',
        default => (string)$bytes,
    };
}