<?php
declare(strict_types=1);

define('IN_MYBB',    1);
define('THIS_SCRIPT','attachment.php');
define('SCRIPTNAME', 'attachment.php');
define('IN_FORUM',   true);

require_once 'global.php';

// ── Абсолютный путь ───────────────────────────────────────────────────────────
function mk_path_abs(string $path, string $base = TSDIR): string
{
    $iswin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $char1 = my_substr($path, 0, 1);

    if ($char1 !== '/' && !($iswin && ($char1 === '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path)))) {
        $base = rtrim($base, ".\\/") . DIRECTORY_SEPARATOR;
        $path = $base . ltrim($path, '/\\');
    }

    return $path;
}

// ── AID / PID ─────────────────────────────────────────────────────────────────
$isThumbnail = isset($mybb->input['thumbnail']);
$aid         = $mybb->get_input($isThumbnail ? 'thumbnail' : 'aid', MyBB::INPUT_INT);
$pid         = $mybb->get_input('pid', MyBB::INPUT_INT);

// ── Загрузка вложения ─────────────────────────────────────────────────────────
$query      = $aid
    ? $db->sql_query_prepared("SELECT * FROM attachments WHERE aid = ?", [$aid])
    : $db->sql_query_prepared("SELECT * FROM attachments WHERE pid = ?", [$pid]);
$attachment = $db->fetch_array($query);

$plugins->run_hooks('attachment_start');

if (!$attachment) {
    stderr($lang->misc['error_invalidattachment'] ?? 'Invalid attachment.', '', 404, '404');
}

if ($attachment['thumbnail'] === '' && $isThumbnail) {
    stderr($lang->misc['error_invalidattachment'] ?? 'Invalid attachment.', '', 404, '404');
}

// ── Тип файла ─────────────────────────────────────────────────────────────────
$attachtypes = (array)$cache->read('attachtypes');
$ext         = get_extension($attachment['filename']);

if (empty($attachtypes[$ext])) {
    stderr($lang->misc['error_invalidattachment'] ?? 'Invalid attachment type.', '', 404, '404');
}

$attachtype = $attachtypes[$ext];
$pid        = (int)$attachment['pid'];

// ── Проверка доступа ──────────────────────────────────────────────────────────
if ($pid || $attachment['uid'] != $CURUSER['id']) {
    $post = get_post($pid);
    if (!$post) {
        stderr($lang->misc['error_invalidthread'] ?? 'Invalid post.', '', 404, '404');
    }

    if ((int)$post['visible'] !== -2) {
        $thread = get_thread($post['tid']);
        if (!$thread && !$isThumbnail) {
            stderr($lang->misc['error_invalidthread'] ?? 'Invalid thread.', '', 404, '404');
        }

        $forum = get_forum($thread['fid'] ?? 0);
        // Разрешения можно включить здесь при необходимости
    }
}

// ── Счётчик скачиваний ────────────────────────────────────────────────────────
if (!$isThumbnail) {
    $db->sql_query_prepared(
        "UPDATE attachments SET downloads = ? WHERE aid = ?",
        [(int)$attachment['downloads'] + 1, (int)$attachment['aid']]
    );
}

// basename не UTF-8 safe — workaround
$attachment['filename'] = ltrim(basename(' ' . $attachment['filename']));

$uploadspath     = './uploads';
$uploadspath_abs = mk_path_abs($uploadspath);

$plugins->run_hooks('attachment_end');

// ── Отдача файла ──────────────────────────────────────────────────────────────
if ($isThumbnail) {

    $thumbPath = $uploadspath_abs . '/' . $attachment['thumbnail'];

    if (!file_exists($thumbPath)) {
        stderr($lang->misc['error_invalidattachment'] ?? 'Thumbnail not found.', '', 404, '404');
    }

    $thumbExt = get_extension($attachment['thumbnail']);
    $mimeMap  = [
        'gif'  => 'image/gif',
        'bmp'  => 'image/bmp',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpe'  => 'image/jpeg',
        'webp' => 'image/webp',
    ];
    $type = $mimeMap[$thumbExt] ?? 'image/unknown';

    header('Content-Disposition: filename="' . $attachment['filename'] . '"');
    header('Content-Type: ' . $type);
    header('Content-Length: ' . (int)@filesize($thumbPath));

    $handle = fopen($thumbPath, 'rb');
    while (!feof($handle)) {
        echo fread($handle, 8192);
    }
    fclose($handle);

} else {

    $filePath = $uploadspath_abs . '/' . $attachment['attachname'];

    if (!file_exists($filePath)) {
        stderr($lang->misc['error_invalidattachment'] ?? 'File not found.', '', 404, '404');
    }

    $inlineTypes = [
        'application/pdf', 'image/bmp', 'image/gif',
        'image/jpeg', 'image/pjpeg', 'image/png',
        'image/webp', 'text/plain',
    ];

    $filetype = $attachment['filetype'] ?: 'application/force-download';

    if (in_array($filetype, $inlineTypes, true)) {
        header('Content-Type: ' . $filetype);
        $disposition = !empty($attachtype['forcedownload']) ? 'attachment' : 'inline';
    } else {
        header('Content-Type: ' . $filetype);
        $disposition = 'attachment';
    }

    // IE не поддерживает inline disposition для некоторых типов
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (str_contains($ua, 'msie')) {
        header('Content-Disposition: attachment; filename="' . $attachment['filename'] . '"');
    } else {
        header('Content-Disposition: ' . $disposition . '; filename="' . $attachment['filename'] . '"');
    }

    // IE 6 — отключаем кэширование
    if (str_contains($ua, 'msie 6.0')) {
        header('Expires: -1');
    }

    $filesize = (int)$attachment['filesize'];
    header('Content-Length: ' . $filesize);
    header('Content-Range: bytes=0-' . ($filesize - 1) . '/' . $filesize);

    $handle = fopen($filePath, 'rb');
    while (!feof($handle)) {
        echo fread($handle, 8192);
    }
    fclose($handle);
}