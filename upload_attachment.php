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

$lang->load('uploads');
$UL = $lang->uploads;

if (!function_exists('ags_fmt')) {
    /**
     * Substitute {1}, {2}… (and %1$s, %2$s… — $lang->load() converts {n} into those)
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

function json_out(bool $ok, array $data = []): never {
    echo json_encode(['success' => $ok] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}




header('Content-Type: application/json');

// Только залогиненные
if (!$CURUSER || !isset($CURUSER['id'])) {
    echo json_encode(['error' => $UL['err_login_required']]);
    exit;
}

// CSRF проверка
if (empty($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
    echo json_encode(['error' => $UL['err_csrf']]);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $aid = (int)($_POST['aid'] ?? 0);
    if (!$aid) { echo json_encode(['error' => $UL['err_invalid_id']]); exit; }

    $att = $db->fetch_array($db->sql_query_prepared(
        "SELECT * FROM attachments WHERE aid = ?",
        [$aid]
    ));

    if (!$att) { echo json_encode(['error' => $UL['err_not_found']]); exit; }

    // Владелец вложения может удалять своё; стафф (модерация) - чужое тоже
    if ((int)$att['uid'] !== (int)$CURUSER['id'] && !is_mod($usergroups)) {
        echo json_encode(['error' => $UL['err_no_permission']]);
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
    echo json_encode(['error' => $UL['err_no_file']]);
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
        json_out(false, ['error' => ags_fmt($UL['err_limit'], $attLimit)]);
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
// Limits come from the admin settings (Settings → Tracker → Attachments)
$maxSize = att_size_limits()[$fileCategory];

// Проверки
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErr = check_parse_php_upload_err($file);
    json_out(false, ['error' => $uploadErr ?: ags_fmt($UL['err_upload_code'], (int)$file['error'])]);
}
if ($file['size'] > $maxSize) {
    json_out(false, ['error' => ags_fmt($UL['err_file_too_large_cat'], att_size_human($maxSize), $UL['opt_cat_' . $fileCategory])]);
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
        json_out(false, ['error' => ags_fmt($UL['err_quota'], mksize($quotaBytes))]);
    }
}

if (!in_array($mimeType, $allowedMime, true)) {
    json_out(false, ['error' => ags_fmt($UL['err_mime'], $mimeType)]);
}

$origName = basename($file['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExt, true)) {
    json_out(false, ['error' => ags_fmt($UL['err_ext'], $ext)]);
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
    json_out(false, ['error' => ags_fmt($UL['err_save_failed_code'], (string)$uploaded['error'])]);
}

$savePath = $uploadDir . $saveName;


$actualFileSize = (int)$file['size'];
if (str_starts_with($mimeType, 'image/')) {
    // Перекодирование — защита от "полиглот"-файлов. recode_image_file()
    // сама отличает анимированные GIF (обрабатывает через Imagick, сохраняя
    // анимацию) от статичных (обычный путь через GD).
    $recodedSize = recode_image_file($savePath, $mimeType);
    if ($recodedSize === false) {
        @unlink($savePath);
        json_out(false, ['error' => $UL['err_not_image']]);
    }
    $actualFileSize = $recodedSize;
}

// ── Thumbnail для изображений ──────────────────────────────────────────────
$thumbName = '';
$isImage   = str_starts_with($mimeType, 'image/');
$isVideo   = str_starts_with($mimeType, 'video/');
$isAudio   = str_starts_with($mimeType, 'audio/');

if ($isImage) {
    $thumbName = 'thumb_' . $saveName;
    $thumbPath = $uploadDir . $thumbName;
    create_thumbnail($savePath, $thumbPath, (int)$attachthumbw, (int)$attachthumbh, $mimeType);
    if (!file_exists($thumbPath)) {
        $thumbName = '';
    }
}


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
    $quotaParams    = [(int)$CURUSER['id'], $actualFileSize, $quotaBytes];
}

$selectParams = [
    $posthash,
    (int)$CURUSER['id'],
    $origName,
    $mimeType,
    $actualFileSize,
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
        if ($usedNow + $actualFileSize > $quotaBytes) {
            json_out(false, ['error' => ags_fmt($UL['err_quota'], mksize($quotaBytes))]);
        }
    }

    json_out(false, ['error' => ags_fmt($UL['err_limit'], $attLimit)]);
}

$thumbUrl = $thumbName ? $BASEURL . '/uploads/attachments/' . $thumbName : null;

echo json_encode([
    'success'  => true,
    'aid'      => $aid,
    'posthash' => $posthash,
    'name'     => $origName,
    'size'     => $actualFileSize,
    'type'     => $mimeType,
    'is_image' => $isImage,
    'is_video' => $isVideo,
    'is_audio' => $isAudio,
    'category' => $fileCategory,
    'url'      => $BASEURL . '/uploads/attachments/' . $saveName,
    'thumb'    => $thumbUrl,
    'icon'     => (!$isImage && !$isVideo && !$isAudio) ? get_attachment_icon2($ext) : null,
]);