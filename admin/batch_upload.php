<?php
declare(strict_types=1);


ini_set('memory_limit', '512M');
set_time_limit(600);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization not allowed.</div>');
}

require_once INC_PATH . '/functions_category.php';
require_once INC_PATH . '/editor.php';
require_once INC_PATH . '/functions_image_recode.php';

$rootDir = dirname(__DIR__);
require_once $rootDir . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;
use Arokettu\Bencode\Bencode;

$lang->load('upload');

const MAX_BATCH_SIZE  = 10;
const MAX_IMAGE_SIZE  = 5 * 1024 * 1024; // 5 MB
const ALLOWED_IMAGES  = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
const IMAGE_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

// ── Проверка файла торрента на дубликат ─────────────────
if (isset($_GET['action']) && $_GET['action'] === 'check_torrent_file' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    if (($_POST['my_post_key'] ?? '') !== $mybb->post_code) {
        echo json_encode(['exists' => false, 'error' => 'Invalid token']);
        exit;
    }

    $file = $_FILES['torrentFile'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['exists' => false, 'error' => 'No file']);
        exit;
    }

    try {
        $torrentObj = TorrentFile::load($file['tmp_name']);
        $infoHash   = (string) $torrentObj->v1()->getInfoHash();
        $query      = $db->sql_query_prepared("SELECT id, name, added FROM torrents WHERE info_hash = ? LIMIT 1", [$infoHash], 1);
        $torrent    = $query ? $db->fetch_array($query) : null;

        if ($torrent) {
            echo json_encode([
                'exists' => true,
                'id'     => (int) $torrent['id'],
                'name'   => htmlspecialchars_uni($torrent['name']),
                'link'   => $BASEURL . '/' . get_torrent_link($torrent['id']),
                'added'  => strip_tags(my_datee('relative', $torrent['added'])),
            ]);
        } else {
            echo json_encode(['exists' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── BBCode Preview ────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'bbcode_preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    if (($_POST['my_post_key'] ?? '') !== $mybb->post_code) {
        echo json_encode(['html' => '']);
        exit;
    }

    $text = $_POST['text'] ?? '';
    require_once INC_PATH . '/class_parser.php';
    $parser  = new postParser();
    $options = [
        'allow_html'      => 0,
        'allow_mycode'    => 1,
        'allow_smilies'   => 1,
        'allow_imgcode'   => 1,
        'allow_videocode' => 1,
        'filter_badwords' => 1,
    ];
    echo json_encode(['html' => $parser->parse_message($text, $options)]);
    exit;
}

// ── IMDb данные ───────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_imdb_data' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $imdb_url = trim($_GET['imdb_url'] ?? '');
    if (empty($imdb_url)) {
        echo json_encode(['success' => false, 'error' => 'No URL provided']);
        exit;
    }
    if (!preg_match('@^https?://www\.imdb\.com/title/tt\d+/@i', $imdb_url)) {
        echo json_encode(['success' => false, 'error' => 'Invalid IMDb URL']);
        exit;
    }
    if (!str_ends_with($imdb_url, '/')) $imdb_url .= '/';

    try {
        include_once INC_PATH . '/IMDB.php';
        $imdbObj = new IMDB($imdb_url);
        $data    = $imdbObj->parse();
        $poster  = $data['poster'] ?? '';
        if ($poster) {
            $poster = preg_replace('#\._V1_.*?\.(jpg|png|jpeg)$#i', '.$1', $poster);
        }
        while (ob_get_level()) ob_end_clean();
        echo json_encode([
            'success' => true,
            'poster'  => $poster,
            'title'   => $data['title']   ?? '',
            'year'    => $data['year']    ?? '',
            'plot'    => $data['plot']    ?? '',
            'rating'  => $data['rating']  ?? '',
            'genre'   => !empty($data['genres'])    ? implode(', ', $data['genres'])    : '',
            'country' => !empty($data['countries']) ? implode(', ', $data['countries']) : '',
        ]);
    } catch (Exception $e) {
        while (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest();
} else {
    showForm();
}
exit;

// ── Вспомогательные функции ───────────────────────────────

function json_exit(bool $success, array $data = [], int $code = 200): never
{
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR], true)) {
        json_exit(false, ['error' => 'Internal server error'], 500);
    }
});

function ensureDir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        json_exit(false, ['error' => "Cannot create directory: {$dir}"]);
    }
    if (!is_writable($dir)) {
        json_exit(false, ['error' => "Directory not writable: {$dir}"]);
    }
}

function extractFileArray(string $field, int $idx): ?array
{
    if (!isset($_FILES[$field]['name'][$idx])) return null;
    if ($_FILES[$field]['error'][$idx] !== UPLOAD_ERR_OK) return null;
    if (empty($_FILES[$field]['tmp_name'][$idx])) return null;

    return [
        'name'     => $_FILES[$field]['name'][$idx],
        'tmp_name' => $_FILES[$field]['tmp_name'][$idx],
        'type'     => $_FILES[$field]['type'][$idx],
        'error'    => $_FILES[$field]['error'][$idx],
        'size'     => $_FILES[$field]['size'][$idx],
    ];
}

// ── POST обработчик ───────────────────────────────────────

function handlePostRequest(): void
{
    global $db, $CURUSER, $mybb, $torrent_dir, $BASEURL, $lang, $cache;

    // Проверка прав
    //$q = $db->simple_select('users_perm', 'userid',
    //    "userid='" . $db->escape_string($CURUSER['id']) . "' AND canupload='0'"
    //);
    //if ($db->num_rows($q)) {
    //    json_exit(false, ['error' => 'Upload permission denied'], 403);
    //}

    // CSRF
    if (($_POST['my_post_key'] ?? '') !== $mybb->post_code) {
        json_exit(false, ['error' => 'Invalid CSRF token'], 403);
    }

    // Файлы
    if (empty($_FILES['torrentFiles']['name'][0])) {
        json_exit(false, ['error' => 'Please select at least one torrent file']);
    }

    $fileCount = count($_FILES['torrentFiles']['name']);
    if ($fileCount > MAX_BATCH_SIZE) {
        json_exit(false, ['error' => "Maximum " . MAX_BATCH_SIZE . " files allowed, got {$fileCount}"]);
    }

    // Директории
    $rootDir   = dirname(__DIR__);
    $uploadDir = $rootDir . '/uploads/batch/';
    $torrentDirPath = $rootDir . '/' . $torrent_dir . '/';
    $imageDir  = $rootDir . '/torrents/images/';
    $screenDir = $rootDir . '/torrents/screens/';

    foreach ([$uploadDir, $torrentDirPath, $imageDir, $screenDir] as $dir) {
        ensureDir($dir);
    }

    // Постеры
    $posterFiles = [];
    if (isset($_FILES['posters'])) {
        foreach (array_keys($_FILES['posters']['name']) as $idx) {
            $f = extractFileArray('posters', $idx);
            if ($f) $posterFiles[$idx] = $f;
        }
    }

    // Скриншоты - отдельное поле screenshots_{index}[] на каждую раздачу
    // в пачке (несколько файлов на одну раздачу, не один флэт-массив,
    // как у постеров).
    $screenshotFiles = [];
    for ($i = 0; $i < $fileCount; $i++) {
        $field = "screenshots_{$i}";
        if (!isset($_FILES[$field])) continue;

        $files = [];
        foreach (array_keys($_FILES[$field]['name']) as $j) {
            $f = extractFileArray($field, $j);
            if ($f) $files[] = $f;
        }
        if ($files) $screenshotFiles[$i] = $files;
    }

    // CSV
    $csvData = [];
    if (isset($_FILES['csvImport']) && $_FILES['csvImport']['error'] === UPLOAD_ERR_OK) {
        $csvData = parseCSV($_FILES['csvImport']['tmp_name']);
    }

    $results      = [];
    $errors       = [];
    $successCount = 0;

    for ($i = 0; $i < $fileCount; $i++) {
        $name = $_FILES['torrentFiles']['name'][$i];

        if ($_FILES['torrentFiles']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "File '{$name}': upload error code " . $_FILES['torrentFiles']['error'][$i];
            continue;
        }

        $torrentFile = [
            'name'     => $name,
            'type'     => $_FILES['torrentFiles']['type'][$i],
            'tmp_name' => $_FILES['torrentFiles']['tmp_name'][$i],
            'error'    => $_FILES['torrentFiles']['error'][$i],
            'size'     => $_FILES['torrentFiles']['size'][$i],
        ];

        $saved = saveTorrentFile($torrentFile, $uploadDir);
        if (isset($saved['error'])) {
            $errors[] = "'{$name}': " . $saved['error'];
            continue;
        }

        try {
            $result = processTorrent(
                $saved['path'], $name, $i,
                $torrentDirPath, $imageDir, $screenDir,
                $posterFiles, $screenshotFiles, $csvData
            );

            if (isset($result['error'])) {
                $errors[] = "'{$name}': " . $result['error'];
            } else {
                $results[] = $result;
                $successCount++;

                // Ошибки по конкретным скриншотам не блокируют сам торрент
                // (он уже успешно создан) - но пользователь должен видеть,
                // что часть скриншотов не прошла, а не просто недосчитаться
                // их молча.
                foreach ($result['screenshot_errors'] ?? [] as $shotErr) {
                    $errors[] = "'{$name}' screenshot {$shotErr}";
                }
            }
        } catch (Exception $e) {
            @unlink($saved['path']);
            $errors[] = "'{$name}': " . $e->getMessage();
        }
    }



    json_exit(true, [
        'processed'  => $fileCount,
        'successful' => $successCount,
        'failed'     => count($errors),
        'results'    => $results,
        'errors'     => $errors,
        'stats'      => [
            'total_torrents'      => $fileCount,
            'with_posters'        => count(array_filter($results, fn($r) => $r['has_poster'])),
            'total_screenshots'   => array_sum(array_column($results, 'screenshots_added')),
            'csv_imported'        => count($csvData),
        ],
    ]);
}

// ── Обработка одного торрента ─────────────────────────────

function processTorrent(
    string $torrentPath,
    string $originalName,
    int    $index,
    string $torrentDir,
    string $imageDir,
    string $screenDir,
    array  $posterFiles,
    array  $screenshotFiles,
    array  $csvData
): array {
    global $db, $CURUSER, $BASEURL, $lang, $privatetrackerpatch, $SITENAME, $announce_urls;

    $torrentObj = TorrentFile::load($torrentPath);

    // ── Private-tracker patch ────────────────────────────────────────────────
    // If the tracker runs in private mode and the uploaded torrent does not
    // already carry the private flag, set it and re-encode the file so the
    // info_hash is consistent with what clients will see.
    if (isset($privatetrackerpatch) && $privatetrackerpatch === 'yes') {
        $rawContent = file_get_contents($torrentPath);
        $bencode    = Bencode::decode($rawContent);

        if (!isset($bencode['info']['private']) || $bencode['info']['private'] != 1) {
            $announceUrl = trim(($announce_urls[0] ?? '') . '?passkey=' . $CURUSER['passkey']);

            $bencode['info']['private']  = 1;
            $bencode['announce']         = $announceUrl;
            $bencode['comment']          = $lang->upload['DefaultTorrentComment'] ?? '';
            $bencode['created by']       = sprintf($lang->upload['CreatedBy'] ?? 'Uploaded by %s', $CURUSER['username']) . ' [' . $SITENAME . ']';
            $bencode['source']           = $BASEURL;
            $bencode['creation date']    = TIMENOW;

            file_put_contents($torrentPath, Bencode::encode($bencode));
            $torrentObj = TorrentFile::load($torrentPath);
        }
    }
    // ── End private-tracker patch ────────────────────────────────────────────

    $infoHash   = (string)$torrentObj->v1()->getInfoHash();
    $filesList  = $torrentObj->v1()->getFiles();
    $numFiles   = count($filesList);
    $size       = array_sum(array_map(fn($f) => $f->length, iterator_to_array($filesList)));

    // Проверка дубликата
    $existing = $db->sql_query_prepared(
        'SELECT id FROM torrents WHERE info_hash = ? LIMIT 1',
        [$infoHash],
        1
    );
    if ($existing && $db->num_rows($existing) > 0) {
        @unlink($torrentPath);
        return ['error' => 'Torrent already exists on the tracker'];
    }

    // Metadata from CSV or form
    $csvMeta    = !empty($csvData) ? findCSVData($csvData, $originalName) : null;
    $baseName   = substr(pathinfo($originalName, PATHINFO_FILENAME), 0, 255);

    if ($csvMeta) {
        $category    = (int)($csvMeta['category'] ?? $_POST['batch_category'] ?? 1);
        $description = $csvMeta['description'] ?? '';
        $customName  = $csvMeta['name'] ?? $baseName;
    } else {
        $category    = (int)($_POST['batch_categories'][$index] ?? $_POST['batch_category'] ?? 1);
        $description = $_POST['descriptions'][$index] ?? $_POST['batch_description'] ?? '';
        $inputName   = trim($_POST['torrent_names'][$index] ?? '');
        $customName  = !empty($inputName) ? substr($inputName, 0, 255) : $baseName;
    }

    // Теги - приоритет: CSV-колонка "tags", иначе ручное поле формы.
    // Если ни то ни другое не задано, а ниже подключится imdb_parser.php
    // (при наличии IMDb-ссылки) - используем его результат ($Genre,
    // не $tags - именно так называется переменная в одиночной загрузке,
    // upload.php: 'tags' => trim($_POST['tags'] ?? $Genre)).
    $manualTags = !empty($csvMeta['tags'])
        ? trim((string)$csvMeta['tags'])
        : trim((string)($_POST['tags_manual'][$index] ?? ''));

    // IMDb
    $t_link   = trim($_POST['imdb_urls'][$index] ?? '');
    $Genre    = '';
    if (!empty($t_link)) {
        if (!str_ends_with($t_link, '/')) $t_link .= '/';
        if (preg_match('@^https?://www\.imdb\.com/title/tt\d+/@i', $t_link)) {
            try {
                include INC_PATH . '/imdb_parser.php';
            } catch (Throwable) {}
        } else {
            $t_link = '';
        }
    }

    $tags = !empty($manualTags) ? $manualTags : trim((string)$Genre);

    $dbData = [
        'name'            => $customName,
        'filename'        => '',
        'info_hash'       => $infoHash,
        'size'            => (int)$size,
        'numfiles'        => $numFiles,
        'owner'           => (int)$CURUSER['id'],
        'added'           => TIMENOW,
        'category'        => $category,
        'descr'           => $description,
        'tags'            => $tags,
        'anonymous'       => isset($_POST['batch_anonymous']) ? 'yes' : 'no',
        't_link'          => $t_link,
        'visible'         => 'yes',
    ];

    // Вставка в БД
    $columns      = array_keys($dbData);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    
    $insertOk = $db->sql_query_prepared(
        "INSERT INTO torrents (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
        array_values($dbData),
        1
    );
    if (!$insertOk) {
        @unlink($torrentPath);
        throw new Exception('Database error while inserting torrent: ' . $db->error_string());
    }
    $newId = $db->insert_id();
    if (!$newId) {
        @unlink($torrentPath);
        throw new Exception('Failed to insert torrent into database');
    }

    // Копируем файл торрента
    $finalPath = $torrentDir . $newId . '.torrent';
    if (!copy($torrentPath, $finalPath)) {
        $db->sql_query_prepared("DELETE FROM torrents WHERE id = ?", [$newId], 1);
        @unlink($torrentPath);
        throw new Exception('Failed to copy torrent file');
    }

    if (!$db->sql_query_prepared("UPDATE torrents SET filename = ? WHERE id = ?", [$newId . '.torrent', $newId], 1)) {
        write_log("[BATCH_UPLOAD] Failed to set filename for torrent #{$newId}: " . $db->error_string());
    }
    @unlink($torrentPath);

    // Постер
    $imageProcessed = false;
    if (isset($posterFiles[$index])) {
        $imageProcessed = processImage($posterFiles[$index], $newId, $imageDir);
    }

    // Скриншоты (несколько на раздачу)
    $screenshotsProcessed = 0;
    $screenshotErrors     = [];
    if (!empty($screenshotFiles[$index])) {
        $shotResult            = processScreenshots($screenshotFiles[$index], $newId, $screenDir);
        $screenshotsProcessed  = $shotResult['added'];
        $screenshotErrors      = $shotResult['errors'];
    }

    // Лог
    write_log(sprintf(
        $lang->upload['newtorrent'],
        '[URL=' . $BASEURL . '/' . get_torrent_link($newId) . ']' . $customName . '[/URL]',
        '[URL=' . $BASEURL . '/' . get_profile_link($CURUSER['id']) . ']'
            . format_name($CURUSER['username'], $CURUSER['usergroup']) . '[/URL]'
    ));

    return [
        'id'                 => $newId,
        'name'               => htmlspecialchars($customName),
        'size'               => mksize($size),
        'files'              => $numFiles,
        'link'               => get_torrent_link($newId),
        'has_poster'         => $imageProcessed,
        'screenshots_added'  => $screenshotsProcessed,
        'screenshot_errors'  => $screenshotErrors,
    ];
}

// ── Работа с файлами ──────────────────────────────────────

function saveTorrentFile(array $file, string $targetDir): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) return ['error' => 'Upload error'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    //finfo_close($finfo);

    if ($mime !== 'application/x-bittorrent') {
        return ['error' => 'Invalid file type: ' . $mime];
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $path     = rtrim($targetDir, '/') . '/batch_' . uniqid('', true) . '_' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return ['error' => 'Failed to save file'];
    }

    return ['path' => $path];
}

function processImage(array $imageFile, int $torrentId, string $imageDir): bool
{
    global $db, $BASEURL;

    if (!in_array($imageFile['type'], ALLOWED_IMAGES, true)) return false;
    if ($imageFile['size'] > MAX_IMAGE_SIZE) return false;

    // Реальный MIME по содержимому файла - $imageFile['type'] это просто
    // Content-Type заголовок ОТ КЛИЕНТА, тривиально подделывается.
    $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($imageFile['tmp_name']);
    if (!in_array($realMime, ALLOWED_IMAGES, true)) return false;

    $ext        = IMAGE_EXTENSIONS[$realMime] ?? 'jpg';
    $targetPath = rtrim($imageDir, '/') . '/' . $torrentId . '.' . $ext;

    if (!copy($imageFile['tmp_name'], $targetPath)) return false;

    // Перекодирование — защита от "полиглот"-файлов. recode_image_file()
    // сама отличает анимированные GIF (Imagick, с сохранением анимации)
    // от статичных (обычный путь через GD).
    if (recode_image_file($targetPath, $realMime) === false) {
        @unlink($targetPath);
        return false;
    }

    $rootDir      = dirname(__DIR__);
    $relativePath = ltrim(str_replace($rootDir, '', $targetPath), '/\\');
    $imageUrl     = $BASEURL . '/' . str_replace('\\', '/', $relativePath);

    if (!$db->sql_query_prepared("UPDATE torrents SET t_image = ? WHERE id = ?", [$imageUrl, $torrentId], 1)) {
        write_log("[BATCH_UPLOAD] Failed to set poster for torrent #{$torrentId}: " . $db->error_string());
        @unlink($targetPath);
        return false;
    }

    return true;
}

function processScreenshots(array $screenshotFileList, int $torrentId, string $screenDir): array
{
    global $db, $usergroups, $mybb;

    $added  = 0;
    $errors = [];
    // Лимит по группе пользователя (как в upload.php), а не жёстко
    // зашитое число - у разных групп может быть разный максимум.
    $max_screenshots = (int)($mybb->usergroup['max_screenshots'] ?? 3);

    foreach ($screenshotFileList as $shotFile) {
        $label = $shotFile['name'] ?? 'screenshot';

        if ($added >= $max_screenshots) {
            $errors[] = "{$label}: skipped, max {$max_screenshots} screenshots per torrent reached";
            continue;
        }
        if (!in_array($shotFile['type'], ALLOWED_IMAGES, true)) {
            $errors[] = "{$label}: unsupported file type";
            continue;
        }
        if ($shotFile['size'] > MAX_IMAGE_SIZE) {
            $errors[] = "{$label}: file too large (max " . (MAX_IMAGE_SIZE / 1024 / 1024) . "MB)";
            continue;
        }

        // Реальный MIME по содержимому файла - $shotFile['type'] это
        // Content-Type заголовок ОТ КЛИЕНТА, тривиально подделывается.
        $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($shotFile['tmp_name']);
        if (!in_array($realMime, ALLOWED_IMAGES, true)) {
            $errors[] = "{$label}: content does not match an allowed image type";
            continue;
        }

        $ext        = IMAGE_EXTENSIONS[$realMime] ?? 'jpg';
        $filename   = $torrentId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetPath = rtrim($screenDir, '/') . '/' . $filename;

        if (!copy($shotFile['tmp_name'], $targetPath)) {
            $errors[] = "{$label}: failed to save file";
            continue;
        }

        // Перекодирование — защита от "полиглот"-файлов, та же
        // recode_image_file(), что и для постера.
        if (recode_image_file($targetPath, $realMime) === false) {
            @unlink($targetPath);
            $errors[] = "{$label}: corrupted or unsupported image (recode failed)";
            continue;
        }

        $inserted = $db->sql_query_prepared(
            "INSERT INTO screenshots (torrent_id, filename, uploaded_at) VALUES (?, ?, ?)",
            [$torrentId, $filename, TIMENOW],
            1
        );

        if ($inserted) {
            $added++;
        } else {
            @unlink($targetPath);
            $errors[] = "{$label}: database insert failed (" . $db->error_string() . ")";
        }
    }

    return ['added' => $added, 'errors' => $errors];
}

// ── CSV ───────────────────────────────────────────────────

function parseCSV(string $filePath): array
{
    $data   = [];
    $handle = fopen($filePath, 'r');
    if (!$handle) return [];

    fgetcsv($handle, 1000, ','); // header
    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
        if (count($row) >= 4) {
            $data[] = [
                'torrent_filename' => $row[0],
                'name'             => $row[1],
                'category'         => $row[2],
                'description'      => $row[3],
                // Необязательная 5-я колонка - через запятую внутри самого
                // поля, например "Action, Comedy, Drama". Запасной вариант
                // на случай, если у раздачи нет точного совпадения в IMDB.
                'tags'             => $row[4] ?? '',
            ];
        }
    }
    fclose($handle);
    return $data;
}

function findCSVData(array $csvData, string $filename): ?array
{
    foreach ($csvData as $row) {
        if ($row['torrent_filename'] === $filename) return $row;
    }
    return null;
}

// ── Form ─────────────────────────────────────────────────

function showForm(): void
{
    global $BASEURL, $mybb, $db, $announce_urls, $CURUSER, $_this_script_;

    stdhead('Batch Torrent Upload');

    // Категории для JS
    $categories = [];
    $q = $db->sql_query_prepared("SELECT id, name FROM categories ORDER BY id", [], 1);
    while ($q && ($cat = $db->fetch_array($q))) {
        $categories[] = ['id' => (int)$cat['id'], 'name' => $cat['name']];
    }
    $categoriesJson = json_encode($categories, JSON_UNESCAPED_UNICODE);
    $postKey        = htmlspecialchars($mybb->post_code);
    $scriptUrl      = htmlspecialchars($mybb->input['_this_script_'] ?? $_this_script_ ?? '');
    $maxScreens     = (int)($mybb->usergroup['max_screenshots'] ?? 3);
    $maxImageMb     = (int)(MAX_IMAGE_SIZE / 1024 / 1024);

    $batchAnnounceURL = trim(($announce_urls[0] ?? '') . '?passkey=' . $CURUSER['passkey']);

    // Шаблон CSV - с необязательной 5-й колонкой tags (её понимает parseCSV())
    $csvTemplate = 'data:text/csv;charset=utf-8,' . rawurlencode(
        "torrent_filename,name,category,description,tags\n"
        . "movie.torrent,My Movie,1,Description,\"Action, Drama\"\n"
    );
    ?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/batch_upload.css?ver=422">

<div class="bu-page container mt-4">

  <!-- ── Header ─────────────────────────────────────────── -->
  <div class="bu-card bu-header mb-3">
    <div class="bu-square bu-square-lg bu-soft-primary"><i class="fa-solid fa-layer-group"></i></div>
    <div class="bu-header-text">
      <h1 class="bu-title">Batch Torrent Upload</h1>
      <div class="bu-subtitle">Upload up to <?= MAX_BATCH_SIZE ?> torrents at once with posters, screenshots, tags and IMDb info</div>
    </div>
    <span class="bu-chip bu-soft-success"><i class="fa-solid fa-circle-check me-1"></i>Torrent parser ready</span>
  </div>

  <!-- ── KPI ────────────────────────────────────────────── -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <div class="bu-card bu-kpi">
        <div class="bu-square bu-soft-primary"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div>
          <div class="bu-kpi-value"><?= MAX_BATCH_SIZE ?></div>
          <div class="bu-kpi-label">Torrents per batch</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="bu-card bu-kpi">
        <div class="bu-square bu-soft-info"><i class="fa-solid fa-image"></i></div>
        <div>
          <div class="bu-kpi-value"><?= $maxImageMb ?> MB</div>
          <div class="bu-kpi-label">Max image size</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="bu-card bu-kpi">
        <div class="bu-square bu-soft-warning"><i class="fa-solid fa-images"></i></div>
        <div>
          <div class="bu-kpi-value"><?= $maxScreens ?></div>
          <div class="bu-kpi-label">Screenshots per torrent</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="bu-card bu-kpi">
        <div class="bu-square bu-soft-success"><i class="fa-solid fa-folder-tree"></i></div>
        <div>
          <div class="bu-kpi-value"><?= count($categories) ?></div>
          <div class="bu-kpi-label">Categories</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Announce URL ───────────────────────────────────── -->
  <div class="bu-card bu-announce mb-3">
    <div class="bu-square bu-soft-info"><i class="fa-solid fa-satellite-dish"></i></div>
    <div class="bu-announce-body">
      <div class="bu-announce-label">Your announce URL</div>
      <code id="batchAnnounceUrl" class="bu-announce-url"><?= htmlspecialchars_uni($batchAnnounceURL) ?></code>
    </div>
    <button type="button" class="btn btn-sm rounded-pill bu-btn-soft-primary" id="buCopyAnnounce">
      <i class="fa-solid fa-copy me-1"></i><span>Copy</span>
    </button>
  </div>

  <form id="batchUploadForm" method="post" enctype="multipart/form-data">
    <input type="hidden" name="my_post_key" value="<?= $postKey ?>">

    <!-- ── Info & settings (top row) ─────────────────────── -->
    <div class="row g-3 mb-3">
      <div class="col-md-6 col-lg-4">
        <div class="bu-card h-100">
          <div class="bu-card-head">
            <div class="bu-square bu-soft-info"><i class="fa-solid fa-list-check"></i></div>
            <div><h2 class="bu-card-title">How it works</h2></div>
          </div>
          <div class="bu-card-body">
            <ol class="bu-steps">
              <li><i class="fa-solid fa-file-arrow-up"></i><span>Drop or select .torrent files</span></li>
              <li><i class="fa-solid fa-image"></i><span>Add a poster and screenshots to each</span></li>
              <li><i class="fa-solid fa-folder-tree"></i><span>Set category, description and tags</span></li>
              <li><i class="fa-brands fa-imdb"></i><span>Optionally fetch info from IMDb</span></li>
              <li><i class="fa-solid fa-cloud-arrow-up"></i><span>Press <b>Upload all</b></span></li>
            </ol>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="bu-card h-100">
          <div class="bu-card-head">
            <div class="bu-square bu-soft-secondary"><i class="fa-solid fa-sliders"></i></div>
            <div><h2 class="bu-card-title">Global settings</h2></div>
          </div>
          <div class="bu-card-body">
            <div class="form-check form-switch bu-switch">
              <input class="form-check-input" type="checkbox" id="batch_anonymous" name="batch_anonymous" value="yes">
              <label class="form-check-label" for="batch_anonymous">
                <i class="fa-solid fa-user-secret me-1"></i>Anonymous upload
              </label>
            </div>
            <div class="bu-hint mt-1">Applies to every torrent in this batch</div>
          </div>
        </div>
      </div>
      <div class="col-md-12 col-lg-4">
        <div class="bu-card h-100">
          <div class="bu-card-head">
            <div class="bu-square bu-soft-warning"><i class="fa-solid fa-file-csv"></i></div>
            <div>
              <h2 class="bu-card-title">CSV metadata</h2>
              <div class="bu-card-sub">Optional, overrides the form fields</div>
            </div>
          </div>
          <div class="bu-card-body">
            <input class="form-control" type="file" name="csvImport" accept=".csv">
            <div class="bu-hint mt-2">
              Columns: <code>torrent_filename, name, category, description, tags</code> (tags optional)
            </div>
            <a href="<?= $csvTemplate ?>" download="torrent_template.csv"
               class="btn btn-sm rounded-pill bu-btn-soft-warning mt-3">
              <i class="fa-solid fa-download me-1"></i>Download template
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Torrents (full width) ──────────────────────────── -->
    <div class="bu-card">
      <div class="bu-card-head">
        <div class="bu-square bu-soft-primary"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <div>
          <h2 class="bu-card-title">Torrents</h2>
          <div class="bu-card-sub">Drop several files at once or add them one by one</div>
        </div>
      </div>
      <div class="bu-card-body">

        <!-- Drag & Drop -->
        <div class="drop-zone mb-3" tabindex="0" role="button" aria-label="Select torrent files">
          <div class="bu-drop-icon"><i class="fa-solid fa-file-arrow-up"></i></div>
          <div class="bu-drop-title">Drag &amp; drop .torrent files here</div>
          <div class="bu-drop-sub">or <span class="bu-drop-link">click to browse</span>, up to <?= MAX_BATCH_SIZE ?> files</div>
          <input type="file" id="dragDropFiles" style="display:none" multiple accept=".torrent">
        </div>

        <!-- Торренты -->
        <div id="torrentContainer">
          <div class="torrent-item mb-3">
            <?= torrentItemHtml(0) ?>
          </div>
        </div>

        <button type="button" id="addMore" class="btn rounded-pill bu-btn-dashed w-100">
          <i class="fa-solid fa-plus me-1"></i>Add another torrent
        </button>
      </div>
    </div>

    <!-- ── Sticky action bar ────────────────────────────── -->
    <div class="bu-actionbar">
      <div class="bu-actionbar-info">
        <i class="fa-solid fa-boxes-stacked me-2"></i>In batch: <b class="bu-count"></b> / <?= MAX_BATCH_SIZE ?>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn rounded-pill bu-btn-soft-secondary" onclick="history.back()">
          <i class="fa-solid fa-arrow-left me-1"></i>Back
        </button>
        <button type="submit" class="btn btn-primary rounded-pill px-4" id="batchUploadBtn">
          <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload all
        </button>
      </div>
    </div>
  </form>

  <!-- ── Progress Modal ─────────────────────────────────── -->
  <div class="modal fade bu-modal" id="batchProgressModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <div class="bu-square bu-soft-primary me-2"><i class="fa-solid fa-spinner fa-spin"></i></div>
          <h5 class="modal-title">Processing upload</h5>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <div class="d-flex justify-content-between mb-2">
              <span><i class="fa-solid fa-gauge-high me-1 text-primary"></i>Overall progress</span>
              <span id="overallProgressPercent" class="fw-bold">0%</span>
            </div>
            <div class="progress bu-progress">
              <div class="progress-bar progress-bar-striped progress-bar-animated" id="overallProgressBar" style="width:0%"></div>
            </div>
          </div>
          <div id="fileProgressContainer" class="mb-3"></div>
          <div id="resultsContainer" style="display:none">
            <h6 class="bu-results-title"><i class="fa-solid fa-clipboard-check me-2"></i>Results</h6>
            <div id="resultsList"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn rounded-pill bu-btn-soft-secondary" id="closeModalBtn" style="display:none">
            <i class="fa-solid fa-xmark me-1"></i>Close
          </button>
          <button type="button" class="btn btn-primary rounded-pill" id="viewTorrentsBtn" style="display:none">
            <i class="fa-solid fa-eye me-1"></i>View torrents
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Floating BBCode Toolbar ────────────────────────── -->
  <div id="bbToolbar" class="bu-bbbar d-none" role="toolbar" aria-label="BBCode">
    <span class="bu-bbbar-label"><i class="fa-solid fa-pen-to-square"></i></span>
    <button type="button" class="bu-bb" title="Bold" onclick="batchBB('[b]','[/b]')"><i class="fa-solid fa-bold"></i></button>
    <button type="button" class="bu-bb" title="Italic" onclick="batchBB('[i]','[/i]')"><i class="fa-solid fa-italic"></i></button>
    <button type="button" class="bu-bb" title="Underline" onclick="batchBB('[u]','[/u]')"><i class="fa-solid fa-underline"></i></button>
    <button type="button" class="bu-bb" title="Strikethrough" onclick="batchBB('[s]','[/s]')"><i class="fa-solid fa-strikethrough"></i></button>
    <span class="bu-bb-sep"></span>
    <button type="button" class="bu-bb" title="Link" onclick="batchBB('[url]','[/url]')"><i class="fa-solid fa-link"></i></button>
    <button type="button" class="bu-bb" title="Image" onclick="batchBB('[img]','[/img]')"><i class="fa-solid fa-image"></i></button>
    <button type="button" class="bu-bb" title="YouTube" onclick="batchBB('[video=youtube]','[/video]')"><i class="fa-brands fa-youtube"></i></button>
    <span class="bu-bb-sep"></span>
    <button type="button" class="bu-bb" title="Align left" onclick="batchBB('[left]','[/left]')"><i class="fa-solid fa-align-left"></i></button>
    <button type="button" class="bu-bb" title="Center" onclick="batchBB('[center]','[/center]')"><i class="fa-solid fa-align-center"></i></button>
    <button type="button" class="bu-bb" title="Align right" onclick="batchBB('[right]','[/right]')"><i class="fa-solid fa-align-right"></i></button>
    <span class="bu-bb-sep"></span>
    <button type="button" class="bu-bb" title="Quote" onclick="batchBB('[quote]','[/quote]')"><i class="fa-solid fa-quote-right"></i></button>
    <button type="button" class="bu-bb" title="Code" onclick="batchBB('[code]','[/code]')"><i class="fa-solid fa-code"></i></button>
    <button type="button" class="bu-bb" title="Spoiler" onclick="batchBB('[spoiler]','[/spoiler]')"><i class="fa-solid fa-eye-slash"></i></button>
    <span class="bu-bb-sep"></span>
    <button type="button" class="btn btn-sm rounded-pill bu-btn-soft-warning" onclick="batchPreview()">
      <i class="fa-solid fa-eye me-1"></i>Preview
    </button>
    <button type="button" class="bu-bb bu-bb-close" title="Hide toolbar" onclick="hideBBToolbar()"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <!-- ── BBCode Preview Modal ───────────────────────────── -->
  <div class="modal fade bu-modal" id="batchPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <div class="bu-square bu-soft-warning me-2"><i class="fa-solid fa-eye"></i></div>
          <h5 class="modal-title">Description preview</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="batchPreviewBody">Loading...</div>
      </div>
    </div>
  </div>

</div>

<script>
// Конфигурация передаётся из PHP в JS через единый объект
const BATCH_CONFIG = {
    categories:     <?= $categoriesJson ?>,
    maxTorrents:    <?= MAX_BATCH_SIZE ?>,
    maxImageBytes:  <?= MAX_IMAGE_SIZE ?>,
    maxScreenshots: <?= $maxScreens ?>,
    scriptUrl:      <?= json_encode($scriptUrl) ?>,
    postKey:        <?= json_encode($mybb->post_code) ?>,
};
</script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/batch_upload.js?ver=3"></script>
<script src="<?= $BASEURL ?>/admin/scripts/batch_upload_ui.js?ver=2"></script>

<?php
    stdfoot();
}

// ── PHP helper для первого элемента формы ─────────────────

function torrentItemHtml(int $idx): string
{
    global $mybb;
    $max_screenshots = (int)($mybb->usergroup['max_screenshots'] ?? 3);

    $batchGenres = [
        ['Action',      'fa-solid fa-bolt',              '#ff4757'],
        ['Adventure',   'fa-solid fa-compass',           '#ffa502'],
        ['Animation',   'fa-solid fa-film',              '#2ed573'],
        ['Biography',   'fa-solid fa-user-graduate',     '#70a1ff'],
        ['Comedy',      'fa-solid fa-face-laugh-squint', '#ff6b81'],
        ['Crime',       'fa-solid fa-gavel',             '#8395a7'],
        ['Documentary', 'fa-solid fa-video',             '#a4b0be'],
        ['Drama',       'fa-solid fa-masks-theater',     '#9b8ea9'],
        ['Family',      'fa-solid fa-people-roof',       '#ff7f50'],
        ['Fantasy',     'fa-solid fa-dragon',            '#a29bfe'],
        ['History',     'fa-solid fa-landmark',          '#cd84f1'],
        ['Horror',      'fa-solid fa-ghost',             '#ff4d4d'],
        ['Music',       'fa-solid fa-music',             '#1e90ff'],
        ['Mystery',     'fa-solid fa-magnifying-glass',  '#8e44ad'],
        ['Romance',     'fa-solid fa-heart',             '#ff6b6b'],
        ['Sci-Fi',      'fa-solid fa-rocket',            '#00cec9'],
        ['Sport',       'fa-solid fa-trophy',            '#e1b12c'],
        ['Thriller',    'fa-solid fa-skull',             '#e17055'],
        ['War',         'fa-solid fa-person-rifle',      '#7f8c8d'],
        ['Western',     'fa-solid fa-hat-cowboy',        '#f39c12'],
    ];

    ob_start();
    ?>
    <button type="button" class="bu-remove-item" title="Remove this torrent" aria-label="Remove this torrent">
      <i class="fa-solid fa-trash-can"></i>
    </button>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-file-zipper bu-ic bu-ic-primary"></i>Torrent file <span class="bu-req">*</span></label>
        <input class="form-control" type="file" name="torrentFiles[]" accept=".torrent" required>
        <div class="torrent-name mt-1 small text-muted"></div>
      </div>
      <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-image bu-ic bu-ic-info"></i>Poster <span class="bu-opt">optional</span></label>
        <input class="form-control" type="file" name="posters[]" accept="image/*">
        <div class="image-preview mt-2" style="max-width:150px;display:none">
          <img src="" class="img-thumbnail" style="max-height:100px" alt="">
        </div>
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-12">
        <label class="form-label"><i class="fa-solid fa-images bu-ic bu-ic-warning"></i>Screenshots <span class="bu-opt">optional, up to <?= $max_screenshots ?></span></label>
        <input class="form-control" type="file" name="screenshots_<?= $idx ?>[]" accept="image/*" multiple>
        <div class="screenshots-preview mt-2 d-flex flex-wrap gap-2"></div>
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-heading bu-ic bu-ic-primary"></i>Torrent name <span class="bu-opt">filename if empty</span></label>
        <input type="text" class="form-control torrent-name-input" name="torrent_names[]" placeholder="Leave empty to use the filename">
      </div>
      <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-folder-tree bu-ic bu-ic-success"></i>Category</label>
        <?= ts_category_list('batch_categories[]', 0) ?>
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-12">
        <label class="form-label"><i class="fa-solid fa-align-left bu-ic bu-ic-secondary"></i>Description <span class="bu-opt">BBCode supported</span></label>
        <textarea class="form-control batch-desc" name="descriptions[]" rows="5" placeholder="Description..."></textarea>
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-12">
        <label class="form-label"><i class="fa-solid fa-tags bu-ic bu-ic-danger"></i>Tags <span class="bu-opt">overridden by the CSV tags column</span></label>
        <div class="input-group mb-2">
          <span class="input-group-text bu-addon"><i class="fa-solid fa-tag"></i></span>
          <input type="text" class="form-control batch-tags-input" name="tags_manual[]" placeholder="Action, Comedy, Drama...">
          <button type="button" class="btn bu-btn-soft-secondary" onclick="clearBatchTags(this)">
            <i class="fa-solid fa-eraser me-1"></i>Clear
          </button>
        </div>
        <div class="d-flex flex-wrap gap-2 batch-genre-buttons">
          <?php foreach ($batchGenres as [$label, $icon, $color]): ?>
          <button type="button"
                  class="btn btn-sm batch-genre-tag-btn"
                  data-genre="<?= $label ?>"
                  data-color="<?= $color ?>"
                  onclick="toggleBatchGenreTag(this)"
                  style="border-color: <?= $color ?>80; color: <?= $color ?>;">
              <i class="<?= $icon ?> me-1"></i><?= $label ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-12">
        <label class="form-label"><i class="fa-brands fa-imdb bu-ic bu-ic-imdb"></i>IMDb URL <span class="bu-opt">optional</span></label>
        <div class="input-group">
          <span class="input-group-text bu-addon"><i class="fa-solid fa-link"></i></span>
          <input type="url" class="form-control imdb-url-input" name="imdb_urls[]"
                 placeholder="https://www.imdb.com/title/tt0000000/">
          <button type="button" class="btn bu-btn-imdb btn-fetch-imdb">
            <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Fetch info
          </button>
        </div>
        <div class="imdb-preview mt-2" style="display:none;">
          <div class="bu-imdb-card">
            <div class="d-flex gap-3 align-items-start">
              <img class="imdb-poster" src="" alt="Poster"
                   style="width:56px;height:84px;object-fit:cover;border-radius:6px;display:none;">
              <div class="flex-grow-1 min-w-0">
                <div class="fw-bold imdb-title">—</div>
                <div class="d-flex gap-1 mt-1 flex-wrap">
                  <span class="badge bu-badge bu-soft-warning imdb-year" style="display:none;"></span>
                  <span class="badge bu-badge bu-soft-secondary imdb-genre" style="display:none;"></span>
                  <span class="badge bu-badge bu-soft-success imdb-rating" style="display:none;"></span>
                </div>
                <p class="small text-muted mt-2 mb-2 imdb-plot"></p>
                <button type="button" class="btn btn-sm rounded-pill bu-btn-soft-primary btn-imdb-apply-desc">
                  <i class="fa-solid fa-paste me-1"></i>Add to description
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}