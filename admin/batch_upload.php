<?php
declare(strict_types=1);


ini_set('memory_limit', '512M');
set_time_limit(600);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization not allowed.</div>');
}

require_once INC_PATH . '/functions_category.php';
require_once INC_PATH . '/editor.php';

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
        $query      = $db->sql_query_prepared("SELECT id, name, added FROM torrents WHERE info_hash = ? LIMIT 1", [$infoHash]);
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

    foreach ([$uploadDir, $torrentDirPath, $imageDir] as $dir) {
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
                $torrentDirPath, $imageDir,
                $posterFiles, $csvData
            );

            if (isset($result['error'])) {
                $errors[] = "'{$name}': " . $result['error'];
            } else {
                $results[] = $result;
                $successCount++;
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
            'total_torrents' => $fileCount,
            'with_posters'   => count(array_filter($results, fn($r) => $r['has_poster'])),
            'csv_imported'   => count($csvData),
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
    array  $posterFiles,
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
        [$infoHash]
    );
    if ($db->num_rows($existing) > 0) {
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

    // IMDb
    $t_link   = trim($_POST['imdb_urls'][$index] ?? '');
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
        'anonymous'       => isset($_POST['batch_anonymous']) ? 'yes' : 'no',
        't_link'          => $t_link,
        'visible'         => 'yes',
    ];

    // Вставка в БД
    $columns      = array_keys($dbData);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO torrents (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
        array_values($dbData)
    );
    $newId = $db->insert_id();
    if (!$newId) {
        @unlink($torrentPath);
        throw new Exception('Failed to insert torrent into database');
    }

    // Копируем файл торрента
    $finalPath = $torrentDir . $newId . '.torrent';
    if (!copy($torrentPath, $finalPath)) {
        $db->sql_query_prepared("DELETE FROM torrents WHERE id = ?", [$newId]);
        @unlink($torrentPath);
        throw new Exception('Failed to copy torrent file');
    }

    $db->sql_query_prepared("UPDATE torrents SET filename = ? WHERE id = ?", [$newId . '.torrent', $newId]);
    @unlink($torrentPath);

    // Постер
    $imageProcessed = false;
    if (isset($posterFiles[$index])) {
        $imageProcessed = processImage($posterFiles[$index], $newId, $imageDir);
    }

    // Лог
    write_log(sprintf(
        $lang->upload['newtorrent'],
        '[URL=' . $BASEURL . '/' . get_torrent_link($newId) . ']' . $customName . '[/URL]',
        '[URL=' . $BASEURL . '/' . get_profile_link($CURUSER['id']) . ']'
            . format_name($CURUSER['username'], $CURUSER['usergroup']) . '[/URL]'
    ));

    return [
        'id'         => $newId,
        'name'       => htmlspecialchars($customName),
        'size'       => mksize($size),
        'files'      => $numFiles,
        'link'       => get_torrent_link($newId),
        'has_poster' => $imageProcessed,
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

    $ext        = IMAGE_EXTENSIONS[$imageFile['type']] ?? 'jpg';
    $targetPath = rtrim($imageDir, '/') . '/' . $torrentId . '.' . $ext;

    if (!copy($imageFile['tmp_name'], $targetPath)) return false;

    $rootDir      = dirname(__DIR__);
    $relativePath = ltrim(str_replace($rootDir, '', $targetPath), '/\\');
    $imageUrl     = $BASEURL . '/' . str_replace('\\', '/', $relativePath);

    $db->sql_query_prepared("UPDATE torrents SET t_image = ? WHERE id = ?", [$imageUrl, $torrentId]);

    return true;
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
    $q = $db->sql_query_prepared("SELECT id, name FROM categories ORDER BY id");
    while ($q && ($cat = $db->fetch_array($q))) {
        $categories[] = ['id' => (int)$cat['id'], 'name' => $cat['name']];
    }
    $categoriesJson = json_encode($categories, JSON_UNESCAPED_UNICODE);
    $postKey = htmlspecialchars($mybb->post_code);
    $scriptUrl = htmlspecialchars($mybb->input['_this_script_'] ?? $_this_script_ ?? '');
    ?>

<div class="container mt-4">
  <h2 class="mb-4">
    <i class="fa-solid fa-layer-group me-2 text-primary"></i>Batch Torrent Upload
  </h2>

  <div class="row">
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="fa-solid fa-upload me-2"></i>Upload Multiple Torrents</h5>
        </div>
        <div class="card-body">

          <!-- Announce URL -->
          <?php
          $batchAnnounceURL = trim($announce_urls[0] . "?passkey=" . $CURUSER["passkey"]);
          ?>
          <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div>
              <i class="fa-solid fa-satellite-dish me-2"></i>
              <strong>Your Announce URL:</strong>
              <code id="batchAnnounceUrl" class="ms-2"><?= $batchAnnounceURL ?></code>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary"
                    onclick="navigator.clipboard.writeText(document.getElementById('batchAnnounceUrl').textContent).then(()=>this.textContent='Copied!').catch(()=>{})">
              <i class="fa-solid fa-copy me-1"></i>Copy
            </button>
          </div>

          <form id="batchUploadForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="my_post_key" value="<?= $postKey ?>">

            <!-- Drag & Drop -->
            <div class="drop-zone mb-4 p-5 border rounded text-center" style="border-color:#6c757d;background:#f8f9fa;cursor:pointer">
              <i class="fa-solid fa-cloud-upload fa-3x text-muted mb-3"></i>
              <h5>Drag & Drop Torrent Files Here</h5>
              <p class="text-muted mb-0">or click to browse</p>
              <input type="file" id="dragDropFiles" style="display:none" multiple accept=".torrent">
            </div>

            <!-- Торренты -->
            <div id="torrentContainer">
              <div class="torrent-item mb-3 border p-3 rounded">
                <?= torrentItemHtml(0) ?>
              </div>
            </div>

            <div class="mb-3">
              <button type="button" id="addMore" class="btn btn-outline-primary">
                <i class="fa-solid fa-plus"></i> Add Another Torrent
              </button>
            </div>

            <!-- CSV -->
            <div class="mb-3">
              <label class="form-label fw-bold">
                <i class="fa-solid fa-file-csv me-1"></i>Import Metadata from CSV (Optional)
              </label>
              <input class="form-control" type="file" name="csvImport" accept=".csv">
              <div class="form-text">CSV format: torrent_filename, name, category, description</div>
            </div>

            <!-- Глобальные настройки -->
            <div class="card mb-3">
              <div class="card-header bg-light">
                <h6 class="mb-0">Global Settings</h6>
              </div>
              <div class="card-body">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="batch_anonymous" name="batch_anonymous" value="yes">
                  <label class="form-check-label" for="batch_anonymous">Anonymous Upload</label>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-secondary" onclick="history.back()">
                <i class="fa-solid fa-arrow-left me-1"></i>Back
              </button>
              <button type="submit" class="btn btn-primary" id="batchUploadBtn">
                <i class="fa-solid fa-cloud-upload me-1"></i>Upload
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-info text-white"><h6 class="mb-0">Instructions</h6></div>
        <div class="card-body">
          <ol class="mb-0 small">
            <li class="mb-2">Drag & drop or select .torrent files</li>
            <li class="mb-2">Add poster images for each torrent</li>
            <li class="mb-2">Set categories and descriptions</li>
            <li class="mb-2">Optional: Import metadata from CSV</li>
            <li>Click "Upload"</li>
          </ol>
        </div>
      </div>

      <div class="card shadow-sm mb-3">
        <div class="card-header bg-success text-white"><h6 class="mb-0">System Status</h6></div>
        <div class="card-body">
          <ul class="list-unstyled mb-0 small">
            <li class="mb-2"><i class="fa-solid fa-check-circle text-success me-2"></i>Torrent Parser: Available</li>
            <li class="mb-2"><i class="fa-solid fa-hdd me-2 text-primary"></i>Maximum Files: <?= MAX_BATCH_SIZE ?></li>
            <li><i class="fa-solid fa-image me-2 text-info"></i>Max Image Size: 5MB</li>
          </ul>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-warning text-white"><h6 class="mb-0">CSV Template</h6></div>
        <div class="card-body">
          <a href="data:text/csv;charset=utf-8,torrent_filename,name,category,description%0Amovie.torrent,My%20Movie,1,Description"
             download="torrent_template.csv" class="btn btn-sm btn-outline-warning">
            <i class="fa-solid fa-download me-1"></i>Download Template
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Progress Modal -->
<div class="modal fade" id="batchProgressModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fa-solid fa-spinner fa-spin me-2"></i>Processing Upload</h5>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <div class="d-flex justify-content-between mb-2">
            <span>Overall Progress</span>
            <span id="overallProgressPercent" class="fw-bold">0%</span>
          </div>
          <div class="progress" style="height:10px">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="overallProgressBar" style="width:0%"></div>
          </div>
        </div>
        <div id="fileProgressContainer" class="mb-3"></div>
        <div id="resultsContainer" style="display:none">
          <h6 class="border-bottom pb-2 mb-3">Results</h6>
          <div id="resultsList"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="closeModalBtn" style="display:none">Close</button>
        <button type="button" class="btn btn-primary" id="viewTorrentsBtn" style="display:none">
          <i class="fa-solid fa-eye me-1"></i>View Torrents
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.drop-zone:hover { border-color:#0d6efd!important; background:#e7f1ff!important; }
.torrent-item { transition: border-color .2s; }
.torrent-item:hover { border-color:#0d6efd; background:#f8f9fa; }
</style>

<script>
// Конфигурация передаётся из PHP в JS через единый объект
const BATCH_CONFIG = {
    categories:    <?= $categoriesJson ?>,
    maxTorrents:   <?= MAX_BATCH_SIZE ?>,
    maxImageBytes: <?= MAX_IMAGE_SIZE ?>,
    scriptUrl:     <?= json_encode($scriptUrl) ?>,
};
</script>
<script src="<?= $BASEURL ?>/admin/scripts/batch_upload.js"></script>

<!-- Floating BBCode Toolbar -->
<div id="bbToolbar" class="card shadow border-0 d-none"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:9990;min-width:560px;">
  <div class="card-body p-2 d-flex flex-wrap gap-1 align-items-center">
    <small class="text-muted me-1"><i class="fas fa-edit"></i></small>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[b]','[/b]')"><strong>B</strong></button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[i]','[/i]')"><em>I</em></button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[u]','[/u]')"><u>U</u></button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[s]','[/s]')">S</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[url]','[/url]')">URL</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[img]','[/img]')"><i class="fas fa-image"></i></button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[center]','[/center]')">Center</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[left]','[/left]')">Left</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[right]','[/right]')">Right</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[quote]','[/quote]')">Quote</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[code]','[/code]')">Code</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[spoiler]','[/spoiler]')">Spoiler</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="batchBB('[video=youtube]','[/video]')">YouTube</button>
    <button type="button" class="btn btn-sm btn-outline-warning" onclick="batchPreview()"><i class="fas fa-eye"></i> Preview</button>
    <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="hideBBToolbar()"><i class="fas fa-times"></i></button>
  </div>
</div>

<!-- BBCode Preview Modal -->
<div class="modal fade" id="batchPreviewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Description Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="batchPreviewBody">Loading...</div>
    </div>
  </div>
</div>

<script>
let _batchActiveTextarea = null;

document.addEventListener('focusin', function(e) {
    if (!e.target.matches('textarea[name="descriptions[]"]')) return;
    _batchActiveTextarea = e.target;
    document.getElementById('bbToolbar').classList.remove('d-none');
});

document.addEventListener('focusout', function(e) {
    if (!e.target.matches('textarea[name="descriptions[]"]')) return;
    setTimeout(() => {
        if (!document.activeElement?.closest('#bbToolbar') &&
            !document.activeElement?.matches('textarea[name="descriptions[]"]')) {
            document.getElementById('bbToolbar').classList.add('d-none');
        }
    }, 200);
});

function hideBBToolbar() {
    document.getElementById('bbToolbar').classList.add('d-none');
    _batchActiveTextarea = null;
}

function batchBB(open, close) {
    const ta = _batchActiveTextarea;
    if (!ta) return;
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    const sel   = ta.value.substring(start, end);
    ta.value    = ta.value.substring(0, start) + open + sel + close + ta.value.substring(end);
    ta.selectionStart = start + open.length;
    ta.selectionEnd   = start + open.length + sel.length;
    ta.focus();
}

function batchPreview() {
    const ta = _batchActiveTextarea;
    if (!ta) return;
    const modal = new bootstrap.Modal(document.getElementById('batchPreviewModal'));
    document.getElementById('batchPreviewBody').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i></div>';
    modal.show();

    fetch('<?= htmlspecialchars($scriptUrl ?? '') ?>&action=bbcode_preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'text=' + encodeURIComponent(ta.value) + '&my_post_key=<?= $mybb->post_code ?>'
    })
    .then(r => r.text())
    .then(text => {
        const match = text.match(/\{[\s\S]*\}/);
        if (match) {
            const data = JSON.parse(match[0]);
            document.getElementById('batchPreviewBody').innerHTML = data.html || ta.value;
        } else {
            document.getElementById('batchPreviewBody').innerHTML = '<pre>' + escapeHtml(ta.value) + '</pre>';
        }
    })
    .catch(() => {
        document.getElementById('batchPreviewBody').innerHTML = '<pre>' + escapeHtml(ta.value) + '</pre>';
    });
}
</script>

<?php
    stdfoot();
}

// ── PHP helper для первого элемента формы ─────────────────

function torrentItemHtml(int $idx): string
{
    ob_start();
    ?>
    <div class="row">
      <div class="col-md-6">
        <label class="form-label fw-bold"><i class="fa-solid fa-file-archive me-1"></i>Torrent File *</label>
        <input class="form-control" type="file" name="torrentFiles[]" accept=".torrent" required>
        <div class="torrent-name mt-1 small text-muted"></div>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold"><i class="fa-solid fa-image me-1"></i>Poster Image (Optional)</label>
        <input class="form-control" type="file" name="posters[]" accept="image/*">
        <div class="image-preview mt-2" style="max-width:150px;display:none">
          <img src="" class="img-thumbnail" style="max-height:100px">
        </div>
      </div>
    </div>
    <div class="row mt-2">
      <div class="col-md-6">
        <label class="form-label">Torrent Name <span class="text-muted fw-normal small">(Optional — uses filename if empty)</span></label>
        <input type="text" class="form-control torrent-name-input" name="torrent_names[]" placeholder="Leave empty to use filename...">
      </div>
      <div class="col-md-6">
        <label class="form-label">Category</label>
        <?= ts_category_list('batch_categories[]', 0) ?>
      </div>
    </div>
    <div class="row mt-2">
      <div class="col-md-12">
        <label class="form-label">Description</label>
        <textarea class="form-control batch-desc" name="descriptions[]" rows="5" placeholder="Description..."></textarea>
      </div>
    </div>
    <div class="row mt-2">
      <div class="col-md-12">
        <label class="form-label fw-bold">
          <i class="fab fa-imdb text-warning me-1"></i>IMDb URL
          <span class="text-muted fw-normal small">(Optional)</span>
        </label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="fas fa-link"></i></span>
          <input type="url" class="form-control imdb-url-input" name="imdb_urls[]"
                 placeholder="https://www.imdb.com/title/tt0000000/">
          <button type="button" class="btn btn-warning btn-fetch-imdb">
            <i class="fab fa-imdb me-1"></i>Fetch Info
          </button>
        </div>
        <div class="imdb-preview mt-2" style="display:none;">
          <div class="card border-0 bg-light">
            <div class="card-body p-2">
              <div class="d-flex gap-2 align-items-start">
                <img class="imdb-poster" src="" alt="Poster"
                     style="width:50px;height:75px;object-fit:cover;border-radius:4px;display:none;">
                <div class="flex-grow-1">
                  <div class="fw-bold imdb-title small">—</div>
                  <div class="d-flex gap-1 mt-1 flex-wrap">
                    <span class="badge bg-warning text-dark imdb-year" style="display:none;"></span>
                    <span class="badge bg-secondary imdb-genre" style="display:none;"></span>
                    <span class="badge bg-success imdb-rating" style="display:none;"></span>
                  </div>
                  <p class="small text-muted mt-1 mb-1 imdb-plot"></p>
                  <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 btn-imdb-apply-desc">
                    <i class="fas fa-paste me-1"></i>Add to Description
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}