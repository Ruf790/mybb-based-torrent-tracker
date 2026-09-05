<?php
declare(strict_types=1);


// ---------------------------------------------------------------------------
// Upload limit
// ---------------------------------------------------------------------------
function get_php_upload_limit(): int
{
    $sizes = array_filter([
        return_bytes(ini_get('upload_max_filesize')),
        return_bytes(ini_get('post_max_size')),
    ]);

    return empty($sizes) ? 0 : (int)min($sizes);
}


// ---------------------------------------------------------------------------
// Аватары
// ---------------------------------------------------------------------------

function remove_avatars(int $uid, string $exclude = ''): void
{
    global $avataruploadpath, $plugins;

    $avatarpath = defined('IN_ADMINCP') ? '../' . $avataruploadpath : $avataruploadpath;

    $dir = @opendir($avatarpath);
    if ($dir === false) {
        return;
    }

    while (($file = @readdir($dir)) !== false) {
        $plugins->run_hooks('remove_avatars_do_delete', $file);

        if (
            preg_match('#avatar_' . $uid . '\.#', $file)
            && is_file($avatarpath . '/' . $file)
            && $file !== $exclude
        ) {
            delete_uploaded_file($avatarpath . '/' . $file);
        }
    }

    @closedir($dir);
}

function upload_avatar(array $avatar = [], int $uid = 0): array
{
    global $db, $CURUSER, $lang, $plugins, $cache, $avataruploadpath, $avatarsize, $maxavatardims;

    if (!$uid) {
        $uid = (int)$CURUSER['id'];
    }

    if (empty($avatar['name']) || empty($avatar['tmp_name'])) {
        $avatar = $_FILES['avatarupload'] ?? [];
    }

    if (empty($avatar['tmp_name']) || !is_uploaded_file($avatar['tmp_name'])) {
        return ['error' => 'The file upload failed. Please choose a valid file and try again.'];
    }

    $ext = get_extension(my_strtolower($avatar['name']));
    if (!preg_match('#^(gif|jpg|jpeg|jpe|bmp|png|webp)$#i', $ext)) {
        return ['error' => 'Invalid file type. An uploaded avatar must be in GIF, JPEG, BMP, PNG or WebP format'];
    }

    
    $avatarpath = mk_path_abs($avataruploadpath);
    $filename   = 'avatar_' . $uid . '.' . $ext;
    $file       = upload_file($avatar, $avatarpath, $filename);

    if (!empty($file['error'])) {
        delete_uploaded_file($avatarpath . '/' . $filename);
        return ['error' => 'The file upload failed. Please choose a valid file and try again'];
    }

    if (!file_exists($avatarpath . '/' . $filename)) {
        delete_uploaded_file($avatarpath . '/' . $filename);
        return ['error' => 'The file upload failed. Please choose a valid file and try again'];
    }

    $img_dimensions = @getimagesize($avatarpath . '/' . $filename);
    if (!is_array($img_dimensions)) {
        delete_uploaded_file($avatarpath . '/' . $filename);
        return ['error' => 'The file upload failed. Please choose a valid file and try again'];
    }

    // Разрешённые MIME-типы из кэша
    $attachtypes       = (array)$cache->read('attachtypes');
    $allowed_mime_types = [];
    foreach ($attachtypes as $attachtype) {
        if ($attachtype['avatarfile']) {
            $allowed_mime_types[$attachtype['mimetype']] = $attachtype['maxsize'];
        }
    }

   
    $img_type  = (int)$img_dimensions[2];
    $mime_type = image_type_to_mime_type($img_type);

    if ($img_type === 0 || empty($allowed_mime_types[$mime_type])) {
        delete_uploaded_file($avatarpath . '/' . $filename);
        return ['error' => 'The file upload failed. Please choose a valid file and try again'];
    }

    
    $avatarFullPath = $avatarpath . '/' . $filename;

    [$maxW, $maxH] = array_pad(
        array_map('intval', preg_split('/[|x]/i', (string)$maxavatardims)),
        2,
        200 // запасное значение, если настройка вдруг пустая/некорректная
    );

    if ($mime_type === 'image/gif' && is_animated_gif($avatarFullPath)) {
      
        if (!resize_animated_gif($avatarFullPath, $avatarFullPath, $maxW, $maxH)) {
            delete_uploaded_file($avatarFullPath);
            return ['error' => 'The uploaded file is corrupted or is not a valid image.'];
        }
    } elseif (in_array($mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        if (!create_thumbnail($avatarFullPath, $avatarFullPath, $maxW, $maxH, $mime_type)) {
            delete_uploaded_file($avatarFullPath);
            return ['error' => 'The uploaded file is corrupted or is not a valid image.'];
        }
    }
    
    $final_dimensions = @getimagesize($avatarFullPath) ?: $img_dimensions;
    clearstatcache(true, $avatarFullPath);
    $final_filesize = @filesize($avatarFullPath) ?: (int)$avatar['size'];

    $max_size = min(
        $avatarsize > 0 ? $avatarsize * 1024 : PHP_INT_MAX,
        ($allowed_mime_types[$mime_type] ?? 0) * 1024
    );

    if ($final_filesize > $max_size) {
        delete_uploaded_file($avatarpath . '/' . $filename);
        return ['error' => 'The size of the uploaded file is too large'];
    }

    remove_avatars($uid, $filename);

    $ret = [
        'avatar' => $avataruploadpath . '/' . $filename,
        'width'  => (int)$final_dimensions[0],
        'height' => (int)$final_dimensions[1],
    ];

    return $plugins->run_hooks('upload_avatar_end', $ret);
}


// ---------------------------------------------------------------------------
// Вспомогательные функции
// ---------------------------------------------------------------------------


function check_parse_php_upload_err(array $FILE): string
{
    global $lang;

    $error = $FILE['error'] ?? 0;

    if ($error === 0 || ($error === UPLOAD_ERR_NO_FILE && empty($FILE['name']))) {
        return '';
    }

    $detail = $lang->error_uploadfailed . $lang->error_uploadfailed_detail;

    return $detail . match($error) {
        UPLOAD_ERR_INI_SIZE   => 'error_uploadfailed_php1',
        UPLOAD_ERR_FORM_SIZE  => 'error_uploadfailed_php2',
        UPLOAD_ERR_PARTIAL    => 'error_uploadfailed_php3',
        UPLOAD_ERR_NO_FILE    => 'error_uploadfailed_php4',
        UPLOAD_ERR_NO_TMP_DIR => 'error_uploadfailed_php6',
        UPLOAD_ERR_CANT_WRITE => 'error_uploadfailed_php7',
        default               => sprintf('error_uploadfailed_phpx', $error),
    };
}


function create_attachment_index(string $path): void
{
    $index = @fopen(rtrim($path, '/') . '/index.html', 'w');
    if ($index !== false) {
        @fwrite($index, "<html>\n<head>\n<title></title>\n</head>\n<body>\n&nbsp;\n</body>\n</html>");
        @fclose($index);
    }
}


require_once __DIR__ . '/functions_image_recode.php';


function mk_path_abs(string $path, string $base = TSDIR): string
{
    $isWin = str_starts_with(strtoupper(PHP_OS), 'WIN');
    $char1 = $path[0] ?? '';

    if ($char1 !== '/' && !($isWin && ($char1 === '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path)))) {
        $base = rtrim($base, ".\\/") . DIRECTORY_SEPARATOR;
        $path = $base . ltrim($path, '/\\');
    }

    return $path;
}


function mk_path_abs2(string $path, string $base = TSDIR): string
{
    $isWin = str_starts_with(strtoupper(PHP_OS), 'WIN');
    $char1 = my_substr($path, 0, 1);

    if ($char1 !== '/' && !($isWin && ($char1 === '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path)))) {
        $path = $base . $path;
    }

    return $path;
}


function my_chmod(string $file, string $mode): bool
{
    if ($mode[0] !== '0' || strlen($mode) !== 4) {
        return false;
    }
    $old = umask(0);
    $result = chmod($file, octdec($mode));
    umask($old);
    return $result;
}


function delete_uploaded_file(string $path = ''): bool
{
    global $plugins;

    $deleted = @unlink($path);

    $hook_params = ['path' => &$path, 'deleted' => &$deleted];
    $plugins->run_hooks('delete_uploaded_file', $hook_params);

    return $deleted;
}


function delete_upload_directory(string $path = ''): bool
{
    global $plugins;

    $deleted_index = @unlink(rtrim($path, '/') . '/index.html');
    $deleted       = @rmdir($path);

    $hook_params = ['path' => &$path, 'deleted' => &$deleted];
    $plugins->run_hooks('delete_upload_directory', $hook_params);

    if (!$deleted && $deleted_index) {
        create_attachment_index($path);
    }

    return $deleted;
}


function upload_file(array $file, string $path, string $filename = ''): array
{
    global $plugins;

    if (empty($file['name']) || $file['name'] === 'none' || $file['size'] < 1) {
        return ['error' => 1];
    }

    if (!$filename) {
        $filename = $file['name'];
    }

    // Безопасное имя файла — убираем trailing slash
    $original = preg_replace('#/$#', '', $file['name']);
    $filename  = preg_replace('#/$#', '', $filename);

    if (!@move_uploaded_file($file['tmp_name'], $path . '/' . $filename)) {
        return ['error' => 2];
    }

    @my_chmod($path . '/' . $filename, '0644');

    $upload = [
        'original_filename' => $original,
        'filename'          => $filename,
        'path'              => $path,
        'type'              => $file['type'],
        'size'              => $file['size'],
    ];

    $upload = $plugins->run_hooks('upload_file_end', $upload);

    return $upload;
}


// ---------------------------------------------------------------------------
// Аттачменты
// ---------------------------------------------------------------------------


function upload_attachment(array $attachment, bool $update_attachment = false): array
{
    global $mybb, $db, $lang, $plugins, $cache, $usergroups,
           $uploadspath, $attachthumbh, $attachthumbw,
           $posthash, $pid, $tid, $forum, $maxattachments, $CURUSER;

    $posthash = $mybb->get_input('posthash');
    $pid      = (int)$pid;

    if (!is_uploaded_file($attachment['tmp_name']) || empty($attachment['tmp_name'])) {
        return ['error' => $lang->error_uploadfailed . $lang->error_uploadfailed_php4];
    }

    $attachtypes = (array)$cache->read('attachtypes');
    $attachment  = $plugins->run_hooks('upload_attachment_start', $attachment);

    $ext = get_extension($attachment['name']);
    if (!isset($attachtypes[$ext]) || empty($attachtypes[$ext]['enabled'])) {
        return ['error' => 'The type of file that you attached is not allowed. Please remove the attachment or choose a different type'];
    }
    $attachtype = $attachtypes[$ext];

   
    if (function_exists('finfo_open')) {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $attachment['tmp_name']) ?: '';

        $dangerousRealTypes = [
            'application/x-executable', 'application/x-dosexec', 'application/x-msdownload',
            'application/x-sh', 'application/x-bat', 'text/x-php', 'application/x-httpd-php',
            'application/x-elf', 'application/x-mach-binary',
        ];

        if (in_array($realMime, $dangerousRealTypes, true)) {
            return ['error' => 'The uploaded file was rejected: its real content does not match an allowed file type.'];
        }
    }

    $imageExts = ['gif', 'png', 'jpg', 'jpeg', 'jpe', 'webp', 'bmp'];
    if (in_array($ext, $imageExts, true)) {
        if (@getimagesize($attachment['tmp_name']) === false) {
            return ['error' => 'The uploaded file is not a valid image.'];
        }

        if ($ext !== 'gif') {
            $newSize = recode_image_file($attachment['tmp_name'], $realMime);
            if ($newSize === false) {
                return ['error' => 'The uploaded file is corrupted or is not a valid image.'];
            }
            $attachment['size'] = $newSize;
        }
    }

    // Длина имени файла
    if (my_strlen($attachment['name']) > 255) {
        return ['error' => 'The file name ' . htmlspecialchars_uni($attachment['name']) . ' exceeds the maximum file name length 255.'];
    }

    // Размер файла
    if ($attachtype['maxsize'] !== '' && $attachment['size'] > (int)$attachtype['maxsize'] * 1024) {
        return ['error' => 'The file ' . htmlspecialchars_uni($attachment['name']) . ' is too large. The maximum size for that type of file is ' . $attachtype['maxsize'] . ' kilobytes'];
    }

    // Квота пользователя
    if ($usergroups['attachquota'] > 0) {
        $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [$CURUSER['id']]);
        $usage = ($query ? (int)$db->fetch_array($query)['ausage'] : 0) + $attachment['size'];
        if ($usage > $usergroups['attachquota'] * 1024) {
            return ['error' => 'Sorry, but you cannot attach this file because you have reached your attachment quota of ' . mksize($usergroups['attachquota'] * 1024)];
        }
    }

    // Уже существующий аттачмент с таким именем
    if ($pid !== 0) {
        $uploaded_query  = "pid = ?";
        $uploaded_params = [$pid];
    } else {
        $uploaded_query  = "posthash = ?";
        $uploaded_params = [$posthash];
    }
    $query      = $db->sql_query_prepared(
        "SELECT * FROM attachments WHERE filename = ? AND {$uploaded_query}",
        [$attachment['name'], ...$uploaded_params]
    );
    $prevattach = $query ? $db->fetch_array($query) : null;

    if ($prevattach && !$update_attachment) {
        return ['error' => sprintf('error_alreadyuploaded', htmlspecialchars_uni($attachment['name']))];
    }

    // Максимум аттачментов на пост
  
    if ($maxattachments > 0 && !$update_attachment) {
        $query       = $db->sql_query_prepared("SELECT COUNT(aid) AS numattachs FROM attachments WHERE {$uploaded_query}", $uploaded_params);
        $attachcount = $query ? (int)$db->fetch_field($query, 'numattachs') : 0;
        if ($attachcount >= $maxattachments) {
            return ['error' => 'Sorry but you cannot attach this file because you have reached the maximum number of attachments allowed per post of ' . $maxattachments];
        }
    }

    // Подготовка директории по месяцам
    $uploadspath_abs = mk_path_abs($uploadspath);
    $month_dir       = gmdate('Ym');

    if (!@is_dir($uploadspath_abs . '/' . $month_dir)) {
        @mkdir($uploadspath_abs . '/' . $month_dir);
        if (!@is_dir($uploadspath_abs . '/' . $month_dir)) {
            $month_dir = '';
        } else {
            create_attachment_index($uploadspath_abs . '/' . $month_dir);
        }
    }

    $filename = 'post_' . $CURUSER['id'] . '_' . TIMENOW . '_' . md5(random_str()) . '.attach';
    $file     = upload_file($attachment, $uploadspath_abs . '/' . $month_dir, $filename);

    // Если не получилось в месячную директорию — в корень
    if (!empty($file['error']) && $month_dir) {
        $file = upload_file($attachment, $uploadspath_abs . '/', $filename);
    } elseif ($month_dir) {
        $filename = $month_dir . '/' . $filename;
    }

    if (!empty($file['error'])) {
        $err = $lang->error_uploadfailed . $lang->error_uploadfailed_detail;
        $err .= match($file['error']) {
            1       => 'error_uploadfailed_nothingtomove',
            2       => 'error_uploadfailed_movefailed',
            default => '',
        };
        return ['error' => $err];
    }

    if (!file_exists($uploadspath_abs . '/' . $filename)) {
        return ['error' => 'error_uploadfailed' . 'error_uploadfailed_detail' . 'error_uploadfailed_lost'];
    }

    $attacharray = [
        'pid'         => $pid,
        'posthash'    => $posthash,
        'uid'         => $CURUSER['id'],
        'filename'    => $file['original_filename'],
        'filetype'    => $file['type'],
        'filesize'    => (int)$file['size'],
        'attachname'  => $filename,
        'downloads'   => 0,
        'dateuploaded'=> TIMENOW,
    ];

    // Генерация миниатюры для изображений
    if (in_array($ext, ['gif', 'png', 'jpg', 'jpeg', 'jpe', 'webp'], true)) {
        $attacharray = _process_image_attachment(
            $attacharray, $file, $ext, $filename,
            $uploadspath_abs, $attachtypes,
            $attachthumbh, $attachthumbw,
            $plugins, $lang
        );

        // Если _process вернул ошибку
        if (isset($attacharray['error'])) {
            return $attacharray;
        }
    }

    $attacharray['visible'] = 1;
    $attacharray = $plugins->run_hooks('upload_attachment_do_insert', $attacharray);

    if ($prevattach && $update_attachment) {
        unset($attacharray['downloads']);
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($attacharray)));
        $params = array_values($attacharray);
        $params[] = $prevattach['aid'];
        $db->sql_query_prepared("UPDATE attachments SET {$set} WHERE aid = ?", $params);
        _cleanup_old_attachment($prevattach, $uploadspath_abs, $db);
        $aid = $prevattach['aid'];
    } else {
        $columns      = array_keys($attacharray);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $db->sql_query_prepared(
            "INSERT INTO attachments (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
            array_values($attacharray)
        );
        $aid = $db->insert_id();
        if ($pid) {
            update_thread_counters((int)$tid, ['attachmentcount' => '+1']);
        }
    }

    return ['aid' => $aid];
}


function remove_attachment(int $pid, string $posthash, int $aid): void
{
    global $db, $plugins, $uploadspath;

    $aid      = (int)$aid;

    if (!empty($posthash)) {
        $where_sql    = "aid = ? AND posthash = ?";
        $where_params = [$aid, $posthash];
    } else {
        $where_sql    = "aid = ? AND pid = ?";
        $where_params = [$aid, $pid];
    }
    $query      = $db->sql_query_prepared("SELECT aid, attachname, thumbnail, visible FROM attachments WHERE {$where_sql}", $where_params);
    $attachment = $query ? $db->fetch_array($query) : null;

    if ($attachment === false || $attachment === null) {
        return;
    }

    $plugins->run_hooks('remove_attachment_do_delete', $attachment);
    $db->sql_query_prepared("DELETE FROM attachments WHERE aid = ?", [$attachment['aid']]);

    $uploadspath_abs = mk_path_abs($uploadspath);
    _delete_attachment_files($attachment, $uploadspath_abs, $db);

    if ($attachment['visible'] == 1 && $pid) {
        $post = get_post($pid);
        update_thread_counters((int)$post['tid'], ['attachmentcount' => '-1']);
    }
}


function remove_attachments(int $pid, string $posthash = ''): void
{
    global $db, $plugins, $uploadspath;

    $post = $pid ? get_post($pid) : [];

    if ($posthash !== '' && !$pid) {
        $where_sql    = "posthash = ?";
        $where_params = [$posthash];
    } else {
        $where_sql    = "pid = ?";
        $where_params = [$pid];
    }
    $query = $db->sql_query_prepared("SELECT * FROM attachments WHERE {$where_sql}", $where_params);

    $uploadspath_abs  = mk_path_abs($uploadspath);
    $num_attachments  = 0;

    while ($query && ($attachment = $db->fetch_array($query))) {
        if ($attachment['visible'] == 1) {
            $num_attachments++;
        }

        $plugins->run_hooks('remove_attachments_do_delete', $attachment);
        $db->sql_query_prepared("DELETE FROM attachments WHERE aid = ?", [$attachment['aid']]);
        _delete_attachment_files($attachment, $uploadspath_abs, $db);
    }

    if (!empty($post['tid'])) {
        update_thread_counters((int)$post['tid'], ['attachmentcount' => "-{$num_attachments}"]);
    }
}


function add_attachments(int $pid, mixed $forumpermissions, string $attachwhere, string|false $action = false, array $attachwhere_params = []): array
{
    global $db, $mybb, $lang;

    $ret   = [];
    $total = isset($_FILES['attachments']['name']) ? count($_FILES['attachments']['name']) : 0;

    if ($total === 0) {
        return $ret;
    }

    $fields      = ['name', 'type', 'tmp_name', 'error', 'size'];
    $attachments = [];
    $filename_list = [];

    for ($i = 0; $i < $total; $i++) {
        $attachments[$i] = [];
        foreach ($fields as $field) {
            $attachments[$i][$field] = $_FILES['attachments'][$field][$i];
        }

        $FILE = $attachments[$i];
        if (!empty($FILE['name']) && !empty($FILE['type']) && $FILE['size'] > 0) {
            $filename_list[] = $FILE['name'];
        }
    }

    // Предзагрузка уже существующих имён
    $existing = [];
    if (!empty($filename_list)) {
        $placeholders = implode(',', array_fill(0, count($filename_list), '?'));
        $query = $db->sql_query_prepared(
            "SELECT filename FROM attachments WHERE {$attachwhere} AND filename IN ({$placeholders})",
            [...$attachwhere_params, ...$filename_list]
        );
        while ($query && ($row = $db->fetch_array($query))) {
            $existing[$row['filename']] = true;
        }
    }

    foreach ($attachments as $FILE) {
        $err = check_parse_php_upload_err($FILE);
        if ($err !== '') {
            $ret['errors'][]      = $err;
            $mybb->input['action'] = $action;
            continue;
        }

        if (empty($FILE['name']) || empty($FILE['type'])) {
            continue;
        }

        if ($FILE['size'] <= 0) {
            $ret['errors'][]      = sprintf('error_uploadempty', htmlspecialchars_uni($FILE['name']));
            $mybb->input['action'] = $action;
            continue;
        }

        $filename         = $FILE['name'];
        $exists           = !empty($existing[$filename]);
        $update_attachment = $exists && (bool)$mybb->get_input('updateattachment');

        if (!$exists && $mybb->get_input('updateattachment') && $mybb->get_input('updateconfirmed', MyBB::INPUT_INT) != 1) {
            $ret['errors'][] = sprintf('error_updatefailed', $filename);
            continue;
        }

        $attachedfile = upload_attachment($FILE, $update_attachment);

        if (!empty($attachedfile['error'])) {
            $ret['errors'][]      = $attachedfile['error'];
            $mybb->input['action'] = $action;
        } elseif (isset($attachedfile['aid']) && $mybb->get_input('ajax', MyBB::INPUT_INT) === 1) {
            $ret['success'][] = [
                $attachedfile['aid'],
                get_attachment_icon(get_extension($filename)),
                htmlspecialchars_uni($filename),
                mksize($FILE['size']),
            ];
        }
    }

    return $ret;
}


// ---------------------------------------------------------------------------
// Внутренние хелперы
// ---------------------------------------------------------------------------


function _mime_to_imagetype(string $mime): int
{
    return match($mime) {
        'image/gif'                                                   => IMAGETYPE_GIF,
        'image/jpeg', 'image/x-jpg', 'image/x-jpeg',
        'image/pjpeg', 'image/jpg'                                    => IMAGETYPE_JPEG,
        'image/png', 'image/x-png'                                    => IMAGETYPE_PNG,
        'image/webp'                                                   => IMAGETYPE_WEBP,
        'image/bmp', 'image/x-bmp', 'image/x-windows-bmp'            => IMAGETYPE_BMP,
        default                                                        => 0,
    };
}


function _detect_mime(string $file_path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME);
        [$mime] = explode(';', finfo_file($finfo, $file_path), 2);
        // finfo_close не нужен в PHP 8.1+ — объект освобождается автоматически
        return trim($mime);
    }

    if (function_exists('mime_content_type')) {
        return (string)mime_content_type($file_path);
    }

    return '';
}


function _process_image_attachment(
    array  $attacharray,
    array  $file,
    string $ext,
    string $filename,
    string $uploadspath_abs,
    array  $attachtypes,
    int|string $attachthumbh,
    int|string $attachthumbw,
    object $plugins,
    object $lang,
): array {
    $img_type = _mime_to_imagetype(my_strtolower($file['type']));

    $supported_mimes = array_filter(array_column($attachtypes, 'mimetype'));

    $file_path      = $uploadspath_abs . '/' . $filename;
    $img_dimensions = @getimagesize($file_path);
    $mime           = _detect_mime($file_path);

    if (!is_array($img_dimensions) || ($img_dimensions[2] !== $img_type && !in_array($mime, $supported_mimes, true))) {
        delete_uploaded_file($file_path);
        return ['error' => $lang->error_uploadfailed];
    }

    $thumbname   = str_replace('.attach', "_thumb.{$ext}", $filename);
    $attacharray = $plugins->run_hooks('upload_attachment_thumb_start', $attacharray);
    $attacharray['thumbnail'] = '';

   
    $created = create_thumbnail($file_path, $uploadspath_abs . '/' . $thumbname, (int)$attachthumbw, (int)$attachthumbh, $mime);

    if ($created) {
        $attacharray['thumbnail'] = $thumbname;
    }
    

    return $attacharray;
}


function _cleanup_old_attachment(array $prevattach, string $uploadspath_abs, object $db): void
{
    $query = $db->sql_query_prepared("SELECT COUNT(aid) as numreferences FROM attachments WHERE attachname = ?", [$prevattach['attachname']]);
    if ($query && $db->fetch_field($query, 'numreferences') > 0) {
        return;
    }

    delete_uploaded_file($uploadspath_abs . '/' . $prevattach['attachname']);

    if (!empty($prevattach['thumbnail'])) {
        delete_uploaded_file($uploadspath_abs . '/' . $prevattach['thumbnail']);
    }

    _maybe_delete_month_dir($prevattach['attachname'], $uploadspath_abs, $db);
}


function _delete_attachment_files(array $attachment, string $uploadspath_abs, object $db): void
{
    $query = $db->sql_query_prepared("SELECT COUNT(aid) as numreferences FROM attachments WHERE attachname = ?", [$attachment['attachname']]);
    if ($query && $db->fetch_field($query, 'numreferences') > 0) {
        return;
    }

    delete_uploaded_file($uploadspath_abs . '/' . $attachment['attachname']);

    if (!empty($attachment['thumbnail']) && $attachment['thumbnail'] !== 'SMALL') {
        delete_uploaded_file($uploadspath_abs . '/' . $attachment['thumbnail']);
    }

    _maybe_delete_month_dir($attachment['attachname'], $uploadspath_abs, $db);
}


function _maybe_delete_month_dir(string $attachname, string $uploadspath_abs, object $db): void
{
    $parts = explode('/', $attachname);
    if (count($parts) < 2) {
        return;
    }

    $month_dir   = $parts[0];
    $like_pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $month_dir) . '/%';
    $query_indir = $db->sql_query_prepared("SELECT COUNT(aid) as indir FROM attachments WHERE attachname LIKE ?", [$like_pattern]);

    if ($query_indir && $db->fetch_field($query_indir, 'indir') == 0 && @is_dir($uploadspath_abs . '/' . $month_dir)) {
        delete_upload_directory($uploadspath_abs . '/' . $month_dir);
    }
}