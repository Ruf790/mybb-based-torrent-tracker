<?php
declare(strict_types=1);

define('SCRIPTNAME', 'batch_upload.php');

ini_set('memory_limit', '512M');
set_time_limit(600);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization not allowed.</div>');
}

require_once INC_PATH . '/functions_category.php';
require_once INC_PATH . '/editor.php';

$rootDir = dirname(__DIR__);
require_once $rootDir . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;

$lang->load('upload');

const MAX_BATCH_SIZE  = 10;
const MAX_IMAGE_SIZE  = 5 * 1024 * 1024; // 5 MB
const ALLOWED_IMAGES  = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
const IMAGE_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

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
    $q = $db->simple_select('users_perm', 'userid',
        "userid='" . $db->escape_string($CURUSER['id']) . "' AND canupload='0'"
    );
    if ($db->num_rows($q)) {
        json_exit(false, ['error' => 'Upload permission denied'], 403);
    }

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

    if (isset($cache) && method_exists($cache, 'update_torrents')) {
        $cache->update_torrents();
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
    global $db, $CURUSER, $BASEURL, $lang;

    $torrentObj = TorrentFile::load($torrentPath);
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

    // Метаданные из CSV или формы
    $csvMeta    = !empty($csvData) ? findCSVData($csvData, $originalName) : null;
    $baseName   = substr(pathinfo($originalName, PATHINFO_FILENAME), 0, 255);

    if ($csvMeta) {
        $category    = (int)($csvMeta['category'] ?? $_POST['batch_category'] ?? 1);
        $description = $db->escape_string($csvMeta['description'] ?? '');
        $customName  = $db->escape_string($csvMeta['name'] ?? $baseName);
    } else {
        $category    = (int)($_POST['batch_categories'][$index] ?? $_POST['batch_category'] ?? 1);
        $description = $db->escape_string($_POST['descriptions'][$index] ?? $_POST['batch_description'] ?? '');
        $customName  = $db->escape_string($baseName);
    }

    $dbData = [
        'name'            => $customName,
        'filename'        => '',
        'info_hash'       => $db->escape_string($infoHash),
        'size'            => (int)$size,
        'numfiles'        => $numFiles,
        'owner'           => (int)$CURUSER['id'],
        'added'           => TIMENOW,
        'category'        => $category,
        'descr'           => $description,
        'anonymous'       => isset($_POST['batch_anonymous']) ? 'yes' : 'no',
        't_link'          => '',
        'visible'         => 'yes',
        'ts_external_url' => $db->escape_string($torrentObj->getAnnounce() ?? ''),
        'ts_external'     => 'yes',
    ];

    // Вставка в БД
    $db->insert_query('torrents', $dbData);
    $newId = $db->insert_id();
    if (!$newId) {
        @unlink($torrentPath);
        throw new Exception('Failed to insert torrent into database');
    }

    // Копируем файл торрента
    $finalPath = $torrentDir . $newId . '.torrent';
    if (!copy($torrentPath, $finalPath)) {
        $db->delete_query('torrents', "id='{$newId}'");
        @unlink($torrentPath);
        throw new Exception('Failed to copy torrent file');
    }

    $db->update_query('torrents', ['filename' => $newId . '.torrent'], "id='{$newId}'");
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
    finfo_close($finfo);

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

    $db->update_query('torrents', ['t_image' => $db->escape_string($imageUrl)], "id='{$torrentId}'");

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

// ── Форма ─────────────────────────────────────────────────

function showForm(): void
{
    global $mybb, $db, $_this_script_;

    stdhead('Batch Torrent Upload');

    // Категории для JS
    $categories = [];
    $q = $db->simple_select('categories', 'id, name', '', ['order_by' => 'id']);
    while ($cat = $db->fetch_array($q)) {
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
        <label class="form-label">Category</label>
        <?= ts_category_list('batch_categories[]', 0) ?>
      </div>
      <div class="col-md-6">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="descriptions[]" rows="2" placeholder="Description..."></textarea>
      </div>
    </div>
    <?php
    return ob_get_clean();
}