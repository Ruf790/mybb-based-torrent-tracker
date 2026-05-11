<?php
declare(strict_types=1);

define('SCRIPTNAME', 'upload_image.php');
require_once 'global.php';

header('Content-Type: application/json');

/* ── Constants ───────────────────────────────────────────────────── */

const ALLOWED_MIME   = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
const ALLOWED_EXT    = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
const MAX_SIZE_BYTES = 200 * 1024 * 1024; // 200 MB
const UPLOAD_SUBDIR  = '/uploads/';

/* ── Helpers ─────────────────────────────────────────────────────── */

function json_out(bool $ok, array $data = []): never
{
    echo json_encode(['success' => $ok] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function upload_error_msg(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => 'Файл превышает допустимый размер.',
        UPLOAD_ERR_PARTIAL   => 'Файл загружен частично.',
        UPLOAD_ERR_NO_FILE   => 'Файл не выбран.',
        UPLOAD_ERR_NO_TMP_DIR=> 'Отсутствует временная директория.',
        UPLOAD_ERR_CANT_WRITE=> 'Ошибка записи на диск.',
        UPLOAD_ERR_EXTENSION => 'Загрузка заблокирована расширением PHP.',
        default              => 'Неизвестная ошибка загрузки.',
    };
}

/* ── Route guard ─────────────────────────────────────────────────── */

if (($_POST['upload_type'] ?? '') !== 'editor_image') {
    json_out(false, ['error' => 'Неизвестный тип загрузки.']);
}

if (empty($CURUSER['id'])) {
    json_out(false, ['error' => 'Необходима авторизация.']);
}

/* ── Main ────────────────────────────────────────────────────────── */

try {
    if (empty($_FILES['image'])) {
        throw new RuntimeException('Файл не был передан.');
    }

    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_msg($file['error']));
    }

    if ($file['size'] > MAX_SIZE_BYTES) {
        throw new RuntimeException('Файл слишком большой (макс. 200 МБ).');
    }

    // MIME по содержимому — не доверяем заголовку клиента
    $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($realMime, ALLOWED_MIME, true)) {
        throw new RuntimeException('Недопустимый тип файла: ' . $realMime);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        throw new RuntimeException('Недопустимое расширение файла.');
    }

    // Upload dir
    $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . UPLOAD_SUBDIR;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Не удалось создать директорию загрузки.');
    }

    // Save
    $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Ошибка сохранения файла.');
    }

    // URL
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url    = $scheme . '://' . $_SERVER['HTTP_HOST'] . UPLOAD_SUBDIR . $filename;

    // DB insert через sql_query_prepared
    $user_id    = (int) $CURUSER['id'];
    $comment_id = isset($_POST['comment_id'])  ? (int) $_POST['comment_id']  : null;
    $news_id    = isset($_POST['news_id'])      ? (int) $_POST['news_id']     : null;
    $torrent_id = isset($_POST['torrent_id'])   ? (int) $_POST['torrent_id']  : null;

    $result = $db->sql_query_prepared(
        'INSERT INTO comment_files
             (comment_id, news_id, torrent_id, user_id, file_name, file_path, file_url, file_type, file_size, uploaded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        [
            $comment_id,     // int|null → 'i' в bind (null передаётся как string 's' — ОК для NULL)
            $news_id,
            $torrent_id,
            $user_id,
            $file['name'],
            $destination,
            $url,
            $realMime,       // используем проверенный MIME, не от клиента
            $file['size'],
        ]
    );

    if (!$result) {
        throw new RuntimeException('Ошибка записи в БД.');
    }

    $insert_id = $db->insert_id();

    json_out(true, [
        'url'     => $url,
        'file_id' => $insert_id,
        'type'    => 'editor_image',
    ]);

} catch (RuntimeException $e) {
    json_out(false, [
        'error' => $e->getMessage(),
        'type'  => 'editor_image',
    ]);
}