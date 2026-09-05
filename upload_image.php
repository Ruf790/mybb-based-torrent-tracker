<?php
declare(strict_types=1);

define('SCRIPTNAME', 'upload_image.php');
require_once 'global.php';
require_once INC_PATH . '/functions_image_recode.php';

header('Content-Type: application/json');

/* ── Constants ───────────────────────────────────────────────────── */

const ALLOWED_MIME   = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
const ALLOWED_EXT    = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB - more than enough for any real screenshot/photo
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
        UPLOAD_ERR_FORM_SIZE => 'File exceeds the allowed size.',
        UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE   => 'No file was selected.',
        UPLOAD_ERR_NO_TMP_DIR=> 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE=> 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by a PHP extension.',
        default              => 'Unknown upload error.',
    };
}

/* ── Route guard ─────────────────────────────────────────────────── */

if (($_POST['upload_type'] ?? '') !== 'editor_image') {
    json_out(false, ['error' => 'Unknown upload type.']);
}

if (empty($CURUSER['id'])) {
    json_out(false, ['error' => 'Authentication required.']);
}

// CSRF check - previously missing entirely. Without it a third-party page
if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
    json_out(false, ['error' => 'Security check failed. Please refresh the page and try again.']);
}

/* ── Main ────────────────────────────────────────────────────────── */

try {
    if (empty($_FILES['image'])) {
        throw new RuntimeException('No file was provided.');
    }

    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_msg($file['error']));
    }

    if ($file['size'] > MAX_SIZE_BYTES) {
        throw new RuntimeException('File is too large (max 20 MB).');
    }

    // MIME by content — never trust the client-supplied header
    $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($realMime, ALLOWED_MIME, true)) {
        throw new RuntimeException('Invalid file type: ' . $realMime);
    }

    // Extra check on top of finfo - catches rare "polyglot" files whose
    // magic bytes look like a valid image but the rest of the file isn't.
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false) {
        throw new RuntimeException('File is corrupted or is not a valid image.');
    }

    // Two independent detectors (finfo content-sniffing vs getimagesize()'s
    // own header parsing) must agree on the type. A mismatch is a strong
    // signal of a malformed or deliberately crafted file.
    if (($imgInfo['mime'] ?? null) !== $realMime) {
        throw new RuntimeException('Image type mismatch — file header is inconsistent.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        throw new RuntimeException('Invalid file extension.');
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
        throw new RuntimeException('More than one attachment target was specified.');
    }

    if ($comment_id !== null) {
        $check = $db->sql_query_prepared('SELECT user FROM comments WHERE id = ?', [$comment_id]);
        $row   = $check ? $db->fetch_array($check) : null;

        if (!$row) {
            throw new RuntimeException('Comment not found.');
        }
        if ((int)$row['user'] !== $user_id && !is_mod($usergroups)) {
            throw new RuntimeException("You don't have permission for this comment.");
        }
    }

    if ($torrent_id !== null) {
        $check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
        if (!$check || $db->num_rows($check) === 0) {
            throw new RuntimeException('Torrent not found.');
        }
    }

    if ($news_id !== null) {
        $check = $db->sql_query_prepared('SELECT id FROM news WHERE id = ?', [$news_id]);
        if (!$check || $db->num_rows($check) === 0) {
            throw new RuntimeException('News post not found.');
        }
    }

    // Upload dir
    $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . UPLOAD_SUBDIR;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Failed to create upload directory.');
    }

    // Save
    $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to save file.');
    }

   
   
    if ($realMime === 'image/gif') {
        clearstatcache(true, $destination);
        $fileSize = @filesize($destination);
        if ($fileSize === false) {
            @unlink($destination);
            throw new RuntimeException('Uploaded file is corrupted or is not a valid image.');
        }
    } else {
        $fileSize = recode_image_file($destination, $realMime);
        if ($fileSize === false) {
            @unlink($destination);
            throw new RuntimeException('Uploaded file is corrupted or is not a valid image.');
        }
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
        throw new RuntimeException('Database write error.');
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