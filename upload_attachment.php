<?php

declare(strict_types=1);

/**
 * upload_attachment.php
 * AJAX обработчик загрузки вложений для комментариев
 */
define('THIS_SCRIPT', 'upload_attachment.php');
require './global.php';

require_once INC_PATH . '/functions_upload.php';
require_once INC_PATH . '/functions_comment_attachments.php';

function json_out(bool $ok, array $data = []): never {
    echo json_encode(['success' => $ok] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}




header('Content-Type: application/json');

// Только залогиненные
if (!$CURUSER || !isset($CURUSER['id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// CSRF проверка
if (empty($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
    echo json_encode(['error' => 'Invalid post key']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $aid = (int)($_POST['aid'] ?? 0);
    if (!$aid) { echo json_encode(['error' => 'Invalid ID']); exit; }

    $att = $db->fetch_array($db->sql_query_prepared(
        "SELECT * FROM attachments WHERE aid = ?",
        [$aid]
    ));

    if (!$att) { echo json_encode(['error' => 'Not found']); exit; }

    // Владелец вложения может удалять своё; стафф (модерация) - чужое тоже
    if ((int)$att['uid'] !== (int)$CURUSER['id'] && !is_mod($usergroups)) {
        echo json_encode(['error' => 'No permission']);
        exit;
    }

    // Удаляем файлы
    $uploadDir = TSDIR . '/uploads/attachments/';
    @unlink($uploadDir . $att['attachname']);
    if ($att['thumbnail']) @unlink($uploadDir . $att['thumbnail']);

    $db->sql_query_prepared("DELETE FROM attachments WHERE aid = ?", [$aid]);
    echo json_encode(['success' => true]);
    exit;
}

// ── UPLOAD ────────────────────────────────────────────────────────────────────
if (!isset($_FILES['attachment'])) {
    echo json_encode(['error' => 'No file']);
    exit;
}

$file     = $_FILES['attachment'];
$posthash = trim($_POST['posthash'] ?? '');
if (empty($posthash)) {
    $posthash = bin2hex(random_bytes(16));
}

// ── Лимит на количество вложений ─────────────────────────────────────────────
// Та же семантика, что и у постов на форуме: $maxattachments > 0 && ... — 0 пропускает проверку (без лимита)
$attLimit = (int)($maxattachments ?? 0);
$hasCountLimit = $attLimit > 0;

// При редактировании существующего комментария учитываем и уже прикреплённые к нему файлы
$commentId = (int)($_POST['comment_id'] ?? 0);

// Быстрая предварительная проверка — экономит работу (обработку файла/thumbnail),
// но НЕ атомарна и НЕ является защитой сама по себе: при параллельных запросах
// (fetch без await несколько файлов сразу) несколько запросов могут увидеть
// одно и то же "текущее количество" до того, как хоть один из них запишется в БД.
// Настоящая защита от превышения лимита — атомарный INSERT ... SELECT ниже, у самой вставки.
if ($hasCountLimit) {
    $currentCount = (int)$db->fetch_field(
        $db->sql_query_prepared(
            "SELECT COUNT(aid) as cnt FROM attachments
             WHERE posthash = ?
               AND uid = ?
               AND comment_id = 0 AND pid = 0",
            [$posthash, (int)$CURUSER['id']]
        ),
        'cnt'
    );
    if ($commentId > 0) {
        $currentCount += (int)$db->fetch_field(
            $db->sql_query_prepared("SELECT COUNT(aid) as cnt FROM attachments WHERE comment_id = ?", [$commentId]),
            'cnt'
        );
    }
    if ($currentCount >= $attLimit) {
        json_out(false, ['error' => "Attachment limit reached ({$attLimit} files max)."]);
    }
}

// Разрешённые типы
$allowedMime = [
    // Изображения
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
    // Видео
    'video/mp4', 'video/avi', 'video/x-msvideo', 'video/x-matroska',
    'video/webm', 'video/quicktime', 'video/x-ms-wmv', 'video/mpeg',
    'video/3gpp', 'video/x-flv', 'video/x-m4v',
    // Аудио
    'audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/flac',
    'audio/aac', 'audio/x-m4a', 'audio/webm',
    // Документы
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    // Архивы
    'application/zip', 'application/x-zip-compressed',
    'application/x-rar-compressed', 'application/vnd.rar',
    'application/x-7z-compressed',
    // Текст
    'text/plain',
    // Субтитры
    'text/vtt', 'application/x-subrip',
];

$allowedExt = [
    // Изображения
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
    // Видео
    'mp4', 'avi', 'mkv', 'webm', 'mov', 'wmv', 'mpg', 'mpeg', '3gp', 'flv', 'm4v',
    // Аудио
    'mp3', 'ogg', 'wav', 'flac', 'aac', 'm4a',
    // Документы
    'pdf', 'doc', 'docx', 'xls', 'xlsx',
    // Архивы
    'zip', 'rar', '7z',
    // Текст
    'txt',
    // Субтитры
    'srt', 'vtt', 'sub', 'ass', 'ssa',
];

// Лимиты размера по категории (в байтах)
$mimeType = _detect_mime($file['tmp_name']);
$fileCategory = match(true) {
    str_starts_with($mimeType, 'video/') => 'video',
    str_starts_with($mimeType, 'audio/') => 'audio',
    str_starts_with($mimeType, 'image/') => 'image',
    default                              => 'other',
};
$maxSize = match($fileCategory) {
    'video' => 2  * 1024 * 1024 * 1024, // 2 GB
    'audio' => 200 * 1024 * 1024,        // 200 MB
    'image' => 200 * 1024 * 1024,        // 200 MB ← было 50
    default => 100 * 1024 * 1024,        // 100 MB
};

// Проверки
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErr = check_parse_php_upload_err($file);
    json_out(false, ['error' => $uploadErr ?: 'Upload error: ' . $file['error']]);
}
if ($file['size'] > $maxSize) {
    $maxHuman = $maxSize >= 1073741824
        ? round($maxSize / 1073741824, 1) . ' GB'
        : round($maxSize / 1048576, 0) . ' MB';
    json_out(false, ['error' => 'File too large (max ' . $maxHuman . ' for ' . $fileCategory . ')']);
}

// ── Квота на вложения (общая с форумом — та же таблица attachments) ───────────
// Быстрая предварительная проверка (не атомарна сама по себе, авторитетная — в INSERT ниже)
if (($usergroups['attachquota'] ?? 0) > 0) {
    $quotaBytes = (int)$usergroups['attachquota'] * 1024;
    $usedBytes  = (int)$db->fetch_field(
        $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [(int)$CURUSER['id']]),
        'ausage'
    );
    if ($usedBytes + $file['size'] > $quotaBytes) {
        json_out(false, ['error' => 'Sorry, but you cannot attach this file because you have reached your attachment quota of ' . mksize($quotaBytes)]);
    }
}

if (!in_array($mimeType, $allowedMime, true)) {
    json_out(false, ['error' => 'File type not allowed: ' . $mimeType]);
}

$origName = basename($file['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExt, true)) {
    json_out(false, ['error' => 'Extension not allowed: ' . $ext]);
}

// Папка для загрузок
$uploadDir = TSDIR . '/uploads/attachments/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Уникальное имя файла
$saveName = uniqid('att_', true) . '.' . $ext;

$uploaded = upload_file($file, rtrim($uploadDir, '/'), $saveName);
if (!empty($uploaded['error'])) {
    json_out(false, ['error' => 'Failed to save file (code ' . $uploaded['error'] . ')']);
}

$savePath = $uploadDir . $saveName;

// ── Thumbnail для изображений ──────────────────────────────────────────────
$thumbName = '';
$isImage   = str_starts_with($mimeType, 'image/');
$isVideo   = str_starts_with($mimeType, 'video/');
$isAudio   = str_starts_with($mimeType, 'audio/');

if ($isImage) {
    $thumbName = 'thumb_' . $saveName;
    $thumbPath = $uploadDir . $thumbName;
    create_thumbnail($savePath, $thumbPath, 300, 300, $mimeType);
    if (!file_exists($thumbPath)) {
        $thumbName = '';
    }
}

// ── INSERT в БД (атомарно, с повторной проверкой лимита) ──────────────────
// Одним запросом: вставляем новую строку, только если лимит всё ещё не превышен
// НА МОМЕНТ САМОЙ ВСТАВКИ. Для MyISAM запись в таблицу сериализуется табличной
// блокировкой — то есть подзапрос COUNT(...) и сам INSERT в рамках одного
// оператора не могут "разъехаться" между параллельными запросами, в отличие
// от раздельных SELECT + INSERT.
$quotaBytes = ((int)($usergroups['attachquota'] ?? 0) > 0) ? (int)$usergroups['attachquota'] * 1024 : 0;

$whereParams = [];
$conditions  = [];

if ($hasCountLimit) {
    $condSql    = "(
        SELECT COUNT(*) FROM attachments
        WHERE (posthash = ? AND uid = ? AND comment_id = 0 AND pid = 0)";
    $condParams = [$posthash, (int)$CURUSER['id']];

    if ($commentId > 0) {
        $condSql .= " OR comment_id = ?";
        $condParams[] = $commentId;
    }

    $condSql .= "
    ) < ?";
    $condParams[] = $attLimit;

    $conditions[] = $condSql;
    $whereParams  = array_merge($whereParams, $condParams);
}
$whereClause = $conditions ? implode(' AND ', $conditions) : '1=1';

$quotaParams = [];
$quotaCondition = '';
if ($quotaBytes > 0) {
    $quotaCondition = " AND (SELECT COALESCE(SUM(filesize),0) FROM attachments WHERE uid = ?) + ? <= ?";
    $quotaParams    = [(int)$CURUSER['id'], (int)$file['size'], $quotaBytes];
}

$selectParams = [
    $posthash,
    (int)$CURUSER['id'],
    $origName,
    $mimeType,
    (int)$file['size'],
    $saveName,
    TIMENOW,
    $thumbName,
];

$insertSql = "
    INSERT INTO attachments (pid, posthash, uid, filename, filetype, filesize, attachname, dateuploaded, visible, thumbnail)
    SELECT 0, ?, ?, ?, ?, ?, ?, ?, 1, ?
    FROM DUAL
    WHERE {$whereClause}
    {$quotaCondition}";

$insertParams = array_merge($selectParams, $whereParams, $quotaParams);


$db->sql_query_prepared($insertSql, $insertParams);

$aid = $db->insert_id();

if ($aid <= 0) {
   
    @unlink($savePath);
    if ($thumbName) {
        @unlink($uploadDir . $thumbName);
    }

    if ($quotaBytes > 0) {
        $usedNow = (int)$db->fetch_field(
            $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [(int)$CURUSER['id']]),
            'ausage'
        );
        if ($usedNow + (int)$file['size'] > $quotaBytes) {
            json_out(false, ['error' => 'Sorry, but you cannot attach this file because you have reached your attachment quota of ' . mksize($quotaBytes)]);
        }
    }

    json_out(false, ['error' => "Attachment limit reached ({$attLimit} files max)."]);
}

$thumbUrl = $thumbName ? $BASEURL . '/uploads/attachments/' . $thumbName : null;

echo json_encode([
    'success'  => true,
    'aid'      => $aid,
    'posthash' => $posthash,
    'name'     => $origName,
    'size'     => $file['size'],
    'type'     => $mimeType,
    'is_image' => $isImage,
    'is_video' => $isVideo,
    'is_audio' => $isAudio,
    'category' => $fileCategory,
    'url'      => $BASEURL . '/uploads/attachments/' . $saveName,
    'thumb'    => $thumbUrl,
    'icon'     => (!$isImage && !$isVideo && !$isAudio) ? get_attachment_icon2($ext) : null,
]);

// ── Функция создания thumbnail ─────────────────────────────────────────────
function create_thumbnail(string $src, string $dst, int $maxW, int $maxH, string $mime): bool
{
    if (!extension_loaded('gd')) return false;

    [$w, $h] = @getimagesize($src);
    if (!$w || !$h) return false;

    // Вычисляем новые размеры
    $ratio  = min($maxW / $w, $maxH / $h, 1);
    $newW   = (int)round($w * $ratio);
    $newH   = (int)round($h * $ratio);

    $src_img = match($mime) {
        'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($src),
        'image/png'               => @imagecreatefrompng($src),
        'image/gif'               => @imagecreatefromgif($src),
        'image/webp'              => @imagecreatefromwebp($src),
        default                   => false,
    };

    if (!$src_img) return false;

    $dst_img = imagecreatetruecolor($newW, $newH);

    // Прозрачность для PNG/GIF/WebP
    if (in_array($mime, ['image/png', 'image/gif', 'image/webp'])) {
        imagealphablending($dst_img, false);
        imagesavealpha($dst_img, true);
        $transparent = imagecolorallocatealpha($dst_img, 0, 0, 0, 127);
        imagefilledrectangle($dst_img, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $result = match($mime) {
        'image/jpeg', 'image/jpg' => imagejpeg($dst_img, $dst, 85),
        'image/png'               => imagepng($dst_img, $dst, 6),
        'image/gif'               => imagegif($dst_img, $dst),
        'image/webp'              => imagewebp($dst_img, $dst, 85),
        default                   => false,
    };



    return (bool)$result;
}