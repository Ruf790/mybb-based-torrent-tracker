<?php
declare(strict_types=1);

define('SCRIPTNAME', 'upload_image.php');
require_once 'global.php';
require_once INC_PATH . '/functions_image_recode.php';

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

header('Content-Type: application/json');

// Max editor image size from the admin settings (Settings → Tracker → Attachments), MB; default 20
$maxSizeBytes = max(1, (int)($upload_editor_img_mb ?? 0) ?: 20) * 1048576;

/* ── Constants ───────────────────────────────────────────────────── */

const ALLOWED_MIME   = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
const ALLOWED_EXT    = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
const UPLOAD_SUBDIR  = '/uploads/';

/* ── Helpers ─────────────────────────────────────────────────────── */

function json_out(bool $ok, array $data = []): never
{
    echo json_encode(['success' => $ok] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function upload_error_msg(int $code): string
{
    global $lang;
    $UL = $lang->uploads;

    return match ($code) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => $UL['err_php_size'],
        UPLOAD_ERR_PARTIAL   => $UL['err_php_partial'],
        UPLOAD_ERR_NO_FILE   => $UL['err_php_no_file'],
        UPLOAD_ERR_NO_TMP_DIR=> $UL['err_php_no_tmp'],
        UPLOAD_ERR_CANT_WRITE=> $UL['err_php_cant_write'],
        UPLOAD_ERR_EXTENSION => $UL['err_php_extension'],
        default              => $UL['err_php_unknown'],
    };
}

/**
 * Human-readable size limit in the current language ("20 MB" / "20 МБ")
 */
function upload_size_human(int $bytes): string
{
    global $lang;
    $UL = $lang->uploads;

    return $bytes >= 1073741824
        ? ags_fmt($UL['lbl_size_gb'], round($bytes / 1073741824, 1))
        : ags_fmt($UL['lbl_size_mb'], round($bytes / 1048576, 0));
}

/* ── Route guard ─────────────────────────────────────────────────── */

if (($_POST['upload_type'] ?? '') !== 'editor_image') {
    json_out(false, ['error' => $UL['err_unknown_type']]);
}

if (empty($CURUSER['id'])) {
    json_out(false, ['error' => $UL['err_login_required']]);
}

// CSRF check - previously missing entirely. Without it a third-party page
if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
    json_out(false, ['error' => $UL['err_csrf']]);
}

/* ── Main ────────────────────────────────────────────────────────── */

try {
    if (empty($_FILES['image'])) {
        throw new RuntimeException($UL['err_no_file']);
    }

    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_msg($file['error']));
    }

    if ($file['size'] > $maxSizeBytes) {
        // The message uses the same value, so it always matches the real limit
        throw new RuntimeException(ags_fmt($UL['err_file_too_large'], upload_size_human($maxSizeBytes)));
    }

    // MIME by content — never trust the client-supplied header
    $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($realMime, ALLOWED_MIME, true)) {
        throw new RuntimeException(ags_fmt($UL['err_img_type'], (string)$realMime));
    }

    // Extra check on top of finfo - catches rare "polyglot" files whose
    // magic bytes look like a valid image but the rest of the file isn't.
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false) {
        throw new RuntimeException($UL['err_not_image']);
    }

    // Two independent detectors (finfo content-sniffing vs getimagesize()'s
    // own header parsing) must agree on the type. A mismatch is a strong
    // signal of a malformed or deliberately crafted file.
    if (($imgInfo['mime'] ?? null) !== $realMime) {
        throw new RuntimeException($UL['err_img_mismatch']);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        throw new RuntimeException($UL['err_img_ext']);
    }

    // ── Target (comment_id / news_id / torrent_id) ──────────────────────
    // comment_files is a polymorphic table - comment_id, torrent_id and
   
    $user_id    = (int) $CURUSER['id'];
    $comment_id = isset($_POST['comment_id']) && $_POST['comment_id'] !== '' ? (int) $_POST['comment_id'] : null;
    $news_id    = isset($_POST['news_id'])    && $_POST['news_id']    !== '' ? (int) $_POST['news_id']    : null;
    $torrent_id = isset($_POST['torrent_id']) && $_POST['torrent_id'] !== '' ? (int) $_POST['torrent_id'] : null;

    $providedTargets = array_filter([
        'comment_id' => $comment_id,
        'news_id'    => $news_id,
        'torrent_id' => $torrent_id,
    ], static fn($v) => $v !== null);

    if (count($providedTargets) > 1) {
        throw new RuntimeException($UL['err_multi_target']);
    }

    if ($comment_id !== null) {
        $check = $db->sql_query_prepared('SELECT user FROM comments WHERE id = ?', [$comment_id]);
        $row   = $check ? $db->fetch_array($check) : null;

        if (!$row) {
            throw new RuntimeException($UL['err_comment_not_found']);
        }
        if ((int)$row['user'] !== $user_id && !is_mod($usergroups)) {
            throw new RuntimeException($UL['err_comment_perm']);
        }
    }

    if ($torrent_id !== null) {
        $check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
        if (!$check || $db->num_rows($check) === 0) {
            throw new RuntimeException($UL['err_torrent_not_found']);
        }
    }

    if ($news_id !== null) {
        $check = $db->sql_query_prepared('SELECT id FROM news WHERE id = ?', [$news_id]);
        if (!$check || $db->num_rows($check) === 0) {
            throw new RuntimeException($UL['err_news_not_found']);
        }
    }

    // Upload dir
    $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . UPLOAD_SUBDIR;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException($UL['err_mkdir']);
    }

    // Save
    $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException($UL['err_save_failed']);
    }

   
   
    // Перекодирование — защита от "полиглот"-файлов. recode_image_file()
    // сама отличает анимированные GIF (обрабатывает через Imagick, сохраняя
    // анимацию) от статичных (обычный путь через GD) - отдельная ветка
    // здесь больше не нужна.
    $fileSize = recode_image_file($destination, $realMime);
    if ($fileSize === false) {
        @unlink($destination);
        throw new RuntimeException($UL['err_not_image']);
    }

    // URL
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url    = $scheme . '://' . $_SERVER['HTTP_HOST'] . UPLOAD_SUBDIR . $filename;

    // DB insert via sql_query_prepared
    $result = $db->sql_query_prepared(
        'INSERT INTO comment_files
             (comment_id, news_id, torrent_id, user_id, file_name, file_path, file_url, file_type, file_size, uploaded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        [
            $comment_id,     // int|null → bound as 'i'/'s'; null is passed through as SQL NULL
            $news_id,
            $torrent_id,
            $user_id,
            $file['name'],
            $destination,
            $url,
            $realMime,       // use the verified MIME type, not the client-supplied one
            $fileSize,
        ]
    );

    if (!$result) {
        @unlink($destination);
        throw new RuntimeException($UL['err_db']);
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