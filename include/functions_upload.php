<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Глобальные настройки CDN (переопределяются снаружи если нужно)
// ---------------------------------------------------------------------------
$cdnpath = '';
$usecdn  = '0';


// ---------------------------------------------------------------------------
// Upload limit
// ---------------------------------------------------------------------------

/**
 * Возвращает минимальный из PHP-лимитов на загрузку файлов (в байтах).
 * Возвращает 0 если лимиты не заданы.
 */
function get_php_upload_limit(): int
{
    $sizes = array_filter([
        return_bytes(ini_get('upload_max_filesize')),
        return_bytes(ini_get('post_max_size')),
    ]);

    return empty($sizes) ? 0 : (int)min($sizes);
}


// ---------------------------------------------------------------------------
// CDN
// ---------------------------------------------------------------------------

/**
 * Копирует файл в CDN-директорию если CDN включён.
 *
 * @param string      $file_path     Локальный путь к файлу.
 * @param string|null $uploaded_path Устанавливается в CDN-путь при успехе.
 */
function copy_file_to_cdn(string $file_path = '', ?string &$uploaded_path = null): bool
{
    global $cdnpath, $usecdn, $plugins;

    $success        = false;
    $real_file_path = realpath($file_path);

    if ($real_file_path === false) {
        return false;
    }

    $file_dir_path = str_replace(TSDIR, '', dirname($real_file_path));
    $file_dir_path = ltrim($file_dir_path, './\\');
    $file_name     = basename($real_file_path);

    if (!file_exists($file_path)) {
        return false;
    }

    if (is_object($plugins)) {
        $hook_args = [
            'file_path'      => &$file_path,
            'real_file_path' => &$real_file_path,
            'file_name'      => &$file_name,
            'file_dir_path'  => &$file_dir_path,
        ];
        $plugins->run_hooks('copy_file_to_cdn_start', $hook_args);
    }

    if (!empty($usecdn) && !empty($cdnpath)) {
        $cdn_base       = rtrim($cdnpath, '/\\');
        $cdn_upload_dir = $cdn_base . DIRECTORY_SEPARATOR . $file_dir_path;

        $dir_exists = is_dir($cdn_upload_dir) || @mkdir($cdn_upload_dir, 0777, true);

        if ($dir_exists) {
            $real_cdn_dir = realpath($cdn_upload_dir);
            if ($real_cdn_dir !== false) {
                $success = @copy($file_path, $real_cdn_dir . DIRECTORY_SEPARATOR . $file_name);
                if ($success) {
                    $uploaded_path = $real_cdn_dir;
                }
            }
        }
    }

    if (is_object($plugins)) {
        $hook_args = [
            'file_path'      => &$file_path,
            'real_file_path' => &$real_file_path,
            'file_name'      => &$file_name,
            'uploaded_path'  => &$uploaded_path,
            'success'        => &$success,
        ];
        $plugins->run_hooks('copy_file_to_cdn_end', $hook_args);
    }

    return $success;
}


// ---------------------------------------------------------------------------
// Аватары
// ---------------------------------------------------------------------------

/**
 * Удаляет все аватары пользователя кроме указанного файла.
 */
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

/**
 * Загружает аватар в файловую систему.
 *
 * @param array $avatar Массив из $_FILES, если пустой — берёт $_FILES['avatarupload'].
 * @param int   $uid    ID пользователя (0 = текущий).
 * @return array Массив с ключами 'error' при ошибке, или 'avatar'/'width'/'height' при успехе.
 */
function upload_avatar(array $avatar = [], int $uid = 0): array
{
    global $db, $CURUSER, $lang, $plugins, $cache, $avataruploadpath, $avatarsize;

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

    $avatarpath = $avataruploadpath;
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
        if (defined('IN_ADMINCP') || (is_member($attachtype['groups']) && $attachtype['avatarfile'])) {
            $allowed_mime_types[$attachtype['mimetype']] = $attachtype['maxsize'];
        }
    }

    $mime_type = my_strtolower($avatar['type']);
    $img_type  = _mime_to_imagetype($mime_type);

    if (empty($allowed_mime_types[$mime_type]) || $img_dimensions[2] !== $img_type || $img_type === 0) {
        delete_uploaded_file($avatarpath . '/' . $filename);
        return ['error' => 'The file upload failed. Please choose a valid file and try again'];
    }

    $max_size = min(
        $avatarsize > 0 ? $avatarsize * 1024 : PHP_INT_MAX,
        ($allowed_mime_types[$mime_type] ?? 0) * 1024
    );

    if ($avatar['size'] > $max_size) {
        delete_uploaded_file($avatarpath . '/' . $filename);
        return ['error' => 'The size of the uploaded file is too large'];
    }

    remove_avatars($uid, $filename);

    $ret = [
        'avatar' => $avataruploadpath . '/' . $filename,
        'width'  => (int)$img_dimensions[0],
        'height' => (int)$img_dimensions[1],
    ];

    return $plugins->run_hooks('upload_avatar_end', $ret);
}


// ---------------------------------------------------------------------------
// Вспомогательные функции
// ---------------------------------------------------------------------------

/**
 * Проверяет PHP-ошибки загрузки файла и возвращает строку ошибки или ''.
 */
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

/**
 * Создаёт index.html-заглушку в директории загрузок.
 */
function create_attachment_index(string $path): void
{
    $index = @fopen(rtrim($path, '/') . '/index.html', 'w');
    if ($index !== false) {
        @fwrite($index, "<html>\n<head>\n<title></title>\n</head>\n<body>\n&nbsp;\n</body>\n</html>");
        @fclose($index);
    }
}

/**
 * Приводит относительный путь к абсолютному.
 */
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

/**
 * Альтернативная версия mk_path_abs (оставлена для совместимости).
 */
function mk_path_abs2(string $path, string $base = TSDIR): string
{
    $isWin = str_starts_with(strtoupper(PHP_OS), 'WIN');
    $char1 = my_substr($path, 0, 1);

    if ($char1 !== '/' && !($isWin && ($char1 === '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path)))) {
        $path = $base . $path;
    }

    return $path;
}

/**
 * chmod с проверкой формата строки.
 */
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

/**
 * Удаляет загруженный файл (локально и из CDN если включён).
 */
function delete_uploaded_file(string $path = ''): bool
{
    global $plugins;

    $cdnpath = '';
    $usecdn  = '0';

    $deleted = @unlink($path);

    $cdn_base = rtrim($cdnpath, '/');
    $rel_path = ltrim($path, '/');
    $cdn_full = realpath($cdn_base . '/' . $rel_path);

    if (!empty($usecdn) && !empty($cdn_base) && $cdn_full !== false) {
        $deleted = @unlink($cdn_full) && $deleted;
    }

    $hook_params = ['path' => &$path, 'deleted' => &$deleted];
    $plugins->run_hooks('delete_uploaded_file', $hook_params);

    return $deleted;
}

/**
 * Удаляет директорию загрузок (вместе с index.html-заглушкой).
 */
function delete_upload_directory(string $path = ''): bool
{
    global $plugins;

    $cdnpath = '';
    $usecdn  = '0';

    $deleted_index = @unlink(rtrim($path, '/') . '/index.html');
    $deleted       = @rmdir($path);

    $cdn_base = rtrim($cdnpath, '/');
    $rel_path = ltrim($path, '/');
    $cdn_full = realpath($cdn_base . '/' . $rel_path);

    if (!empty($usecdn) && !empty($cdn_base) && $cdn_full !== false) {
        $deleted = @rmdir(rtrim($cdn_full, '/')) && $deleted;
    }

    $hook_params = ['path' => &$path, 'deleted' => &$deleted];
    $plugins->run_hooks('delete_upload_directory', $hook_params);

    if (!$deleted && $deleted_index) {
        create_attachment_index($path);
    }

    return $deleted;
}

/**
 * Перемещает загруженный файл в нужную директорию.
 *
 * @return array Массив с 'error' при неудаче или данными файла при успехе.
 */
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

    $cdn_path    = '';
    $moved_to_cdn = copy_file_to_cdn($path . '/' . $filename, $cdn_path);

    $upload = [
        'original_filename' => $original,
        'filename'          => $filename,
        'path'              => $path,
        'type'              => $file['type'],
        'size'              => $file['size'],
    ];

    $upload = $plugins->run_hooks('upload_file_end', $upload);

    if ($moved_to_cdn) {
        $upload['cdn_path'] = $cdn_path;
    }

    return $upload;
}


// ---------------------------------------------------------------------------
// Аттачменты
// ---------------------------------------------------------------------------

/**
 * Загружает один аттачмент и сохраняет запись в БД.
 */
function upload_attachment(array $attachment, bool $update_attachment = false): array
{
    global $mybb, $db, $lang, $plugins, $cache, $usergroups,
           $uploadspath, $attachthumbh, $attachthumbw,
           $posthash, $pid, $tid, $forum, $CURUSER;

    $posthash = $db->escape_string($mybb->get_input('posthash'));
    $pid      = (int)$pid;

    if (!is_uploaded_file($attachment['tmp_name']) || empty($attachment['tmp_name'])) {
        return ['error' => $lang->error_uploadfailed . $lang->error_uploadfailed_php4];
    }

    $attachtypes = (array)$cache->read('attachtypes');
    $attachment  = $plugins->run_hooks('upload_attachment_start', $attachment);

    $ext = get_extension($attachment['name']);
    if (!isset($attachtypes[$ext])) {
        return ['error' => 'The type of file that you attached is not allowed. Please remove the attachment or choose a different type'];
    }
    $attachtype = $attachtypes[$ext];

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
        $query = $db->simple_select('attachments', 'SUM(filesize) AS ausage', "uid='" . $CURUSER['id'] . "'");
        $usage = (int)$db->fetch_array($query)['ausage'] + $attachment['size'];
        if ($usage > $usergroups['attachquota'] * 1024) {
            return ['error' => 'Sorry, but you cannot attach this file because you have reached your attachment quota of ' . mksize($usergroups['attachquota'] * 1024)];
        }
    }

    // Уже существующий аттачмент с таким именем
    $uploaded_query = $pid !== 0 ? "pid='{$pid}'" : "posthash='{$posthash}'";
    $query          = $db->simple_select('attachments', '*', "filename='" . $db->escape_string($attachment['name']) . "' AND " . $uploaded_query);
    $prevattach     = $db->fetch_array($query);

    if ($prevattach && !$update_attachment) {
        return ['error' => sprintf('error_alreadyuploaded', htmlspecialchars_uni($attachment['name']))];
    }

    // Максимум аттачментов на пост
    $maxattachments = 5;
    if ($maxattachments > 0 && !$update_attachment) {
        $query       = $db->simple_select('attachments', 'COUNT(aid) AS numattachs', $uploaded_query);
        $attachcount = (int)$db->fetch_field($query, 'numattachs');
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
        'filename'    => $db->escape_string($file['original_filename']),
        'filetype'    => $db->escape_string($file['type']),
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
        $db->update_query('attachments', $attacharray, "aid='" . $db->escape_string($prevattach['aid']) . "'");
        _cleanup_old_attachment($prevattach, $uploadspath_abs, $db);
        $aid = $prevattach['aid'];
    } else {
        $aid = $db->insert_query('attachments', $attacharray);
        if ($pid) {
            update_thread_counters($tid, ['attachmentcount' => '+1']);
        }
    }

    return ['aid' => $aid];
}

/**
 * Удаляет один аттачмент из БД и файловой системы.
 */
function remove_attachment(int $pid, string $posthash, int $aid): void
{
    global $db, $plugins, $uploadspath;

    $aid      = (int)$aid;
    $posthash = $db->escape_string($posthash);

    $where     = !empty($posthash) ? "aid='{$aid}' AND posthash='{$posthash}'" : "aid='{$aid}' AND pid='{$pid}'";
    $query     = $db->simple_select('attachments', 'aid, attachname, thumbnail, visible', $where);
    $attachment = $db->fetch_array($query);

    if ($attachment === false) {
        return;
    }

    $plugins->run_hooks('remove_attachment_do_delete', $attachment);
    $db->delete_query('attachments', "aid='{$attachment['aid']}'");

    $uploadspath_abs = mk_path_abs($uploadspath);
    _delete_attachment_files($attachment, $uploadspath_abs, $db);

    if ($attachment['visible'] == 1 && $pid) {
        $post = get_post($pid);
        update_thread_counters($post['tid'], ['attachmentcount' => '-1']);
    }
}

/**
 * Удаляет все аттачменты поста или posthash.
 */
function remove_attachments(int $pid, string $posthash = ''): void
{
    global $db, $plugins, $uploadspath;

    $post     = $pid ? get_post($pid) : [];
    $posthash = $db->escape_string($posthash);

    $where = ($posthash !== '' && !$pid) ? "posthash='$posthash'" : "pid='$pid'";
    $query = $db->simple_select('attachments', '*', $where);

    $uploadspath_abs  = mk_path_abs($uploadspath);
    $num_attachments  = 0;

    while ($attachment = $db->fetch_array($query)) {
        if ($attachment['visible'] == 1) {
            $num_attachments++;
        }

        $plugins->run_hooks('remove_attachments_do_delete', $attachment);
        $db->delete_query('attachments', "aid='" . $attachment['aid'] . "'");
        _delete_attachment_files($attachment, $uploadspath_abs, $db);
    }

    if (!empty($post['tid'])) {
        update_thread_counters($post['tid'], ['attachmentcount' => "-{$num_attachments}"]);
    }
}

/**
 * Обрабатывает аттачменты при создании/редактировании поста.
 */
function add_attachments(int $pid, mixed $forumpermissions, string $attachwhere, string|false $action = false): array
{
    global $db, $mybb, $lang;

    $ret   = [];
    $total = isset($_FILES['attachments']['name']) ? count($_FILES['attachments']['name']) : 0;

    if ($total === 0) {
        return $ret;
    }

    $fields      = ['name', 'type', 'tmp_name', 'error', 'size'];
    $attachments = [];
    $filenames   = '';
    $delim       = '';

    for ($i = 0; $i < $total; $i++) {
        $attachments[$i] = [];
        foreach ($fields as $field) {
            $attachments[$i][$field] = $_FILES['attachments'][$field][$i];
        }

        $FILE = $attachments[$i];
        if (!empty($FILE['name']) && !empty($FILE['type']) && $FILE['size'] > 0) {
            $filenames .= $delim . "'" . $db->escape_string($FILE['name']) . "'";
            $delim      = ',';
        }
    }

    // Предзагрузка уже существующих имён
    $existing = [];
    if ($filenames !== '') {
        $query = $db->simple_select('attachments', 'filename', "{$attachwhere} AND filename IN ({$filenames})");
        while ($row = $db->fetch_array($query)) {
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

        $filename         = $db->escape_string($FILE['name']);
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

/**
 * Конвертирует MIME-тип в константу IMAGETYPE_*.
 */
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

/**
 * Определяет MIME-тип файла через finfo или mime_content_type.
 */
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

/**
 * Обрабатывает изображение: проверяет MIME и генерирует миниатюру.
 * Возвращает обновлённый $attacharray или массив с 'error'.
 */
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

    require_once INC_PATH . '/functions_image.php';

    $thumbname   = str_replace('.attach', "_thumb.{$ext}", $filename);
    $attacharray = $plugins->run_hooks('upload_attachment_thumb_start', $attacharray);
    $thumbnail   = generate_thumbnail($file_path, $uploadspath_abs, $thumbname, $attachthumbh, $attachthumbw);

    if (!empty($thumbnail['filename'])) {
        $attacharray['thumbnail'] = $thumbnail['filename'];
    } elseif (($thumbnail['code'] ?? 0) === 4) {
        $attacharray['thumbnail'] = 'SMALL';
    }

    return $attacharray;
}

/**
 * Удаляет файлы старого аттачмента при обновлении.
 */
function _cleanup_old_attachment(array $prevattach, string $uploadspath_abs, object $db): void
{
    $query = $db->simple_select('attachments', 'COUNT(aid) as numreferences', "attachname='" . $db->escape_string($prevattach['attachname']) . "'");
    if ($db->fetch_field($query, 'numreferences') > 0) {
        return;
    }

    delete_uploaded_file($uploadspath_abs . '/' . $prevattach['attachname']);

    if (!empty($prevattach['thumbnail'])) {
        delete_uploaded_file($uploadspath_abs . '/' . $prevattach['thumbnail']);
    }

    _maybe_delete_month_dir($prevattach['attachname'], $uploadspath_abs, $db);
}

/**
 * Удаляет файлы аттачмента если на него нет других ссылок.
 */
function _delete_attachment_files(array $attachment, string $uploadspath_abs, object $db): void
{
    $query = $db->simple_select('attachments', 'COUNT(aid) as numreferences', "attachname='" . $db->escape_string($attachment['attachname']) . "'");
    if ($db->fetch_field($query, 'numreferences') > 0) {
        return;
    }

    delete_uploaded_file($uploadspath_abs . '/' . $attachment['attachname']);

    if (!empty($attachment['thumbnail']) && $attachment['thumbnail'] !== 'SMALL') {
        delete_uploaded_file($uploadspath_abs . '/' . $attachment['thumbnail']);
    }

    _maybe_delete_month_dir($attachment['attachname'], $uploadspath_abs, $db);
}

/**
 * Удаляет месячную директорию если она пуста.
 */
function _maybe_delete_month_dir(string $attachname, string $uploadspath_abs, object $db): void
{
    $parts = explode('/', $attachname);
    if (count($parts) < 2) {
        return;
    }

    $month_dir   = $parts[0];
    $query_indir = $db->simple_select('attachments', 'COUNT(aid) as indir', "attachname LIKE '" . $db->escape_string_like($month_dir) . "/%'");

    if ($db->fetch_field($query_indir, 'indir') == 0 && @is_dir($uploadspath_abs . '/' . $month_dir)) {
        delete_upload_directory($uploadspath_abs . '/' . $month_dir);
    }
}