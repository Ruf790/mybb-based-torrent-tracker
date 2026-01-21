<?php
define("SCRIPTNAME", "batch_upload.php");

error_reporting(E_ALL);
ini_set('display_errors', 1); // Изменено на 1 для отладки
ini_set('memory_limit', '512M');
set_time_limit(600);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

require_once INC_PATH . '/functions_category.php';
require_once INC_PATH . '/editor.php';


$rootDir = dirname(__DIR__); 

// Подключаем автозагрузку
require_once $rootDir . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;
use Arokettu\Bencode\Bencode;

$lang->load('upload');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest();
} else {
    showForm();
}
exit;

// ===================== FUNCTIONS =====================

function json_response($success, $data = [], $code = 200) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR])) {
        json_response(false, [
            'error' => 'Внутренняя ошибка сервера',
            'debug' => [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]
        ], 500);
    }
});

function handlePostRequest() {
    global $db, $CURUSER, $mybb, $torrent_dir, $BASEURL, $lang, $cache, $announce_urls;

    // ------------------- Проверка прав -------------------
    $query = $db->simple_select("users_perm", "userid", 
        "userid='".$db->escape_string($CURUSER['id'])."' AND canupload = '0'");
    if ($db->num_rows($query)) {
        json_response(false, ['error' => 'У вас нет прав на загрузку торрентов'], 403);
    }

    if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        json_response(false, ['error' => 'Неверный CSRF токен'], 403);
    }

    if (!isset($_FILES['torrentFiles']) || empty($_FILES['torrentFiles']['name'][0])) {
        json_response(false, ['error' => 'Выберите хотя бы один торрент файл']);
    }

    $maxBatchSize = 10;
    $fileCount = count($_FILES['torrentFiles']['name']);
    if ($fileCount > $maxBatchSize) {
        json_response(false, [
            'error' => "Максимальное количество файлов: {$maxBatchSize}. Вы выбрали: {$fileCount}"
        ]);
    }

    // ------------------- Директории -------------------
    // Определяем корневую директорию
    $rootDir = dirname(__DIR__); // Из D:\web\admin поднимаемся в D:\web
    
    // Пути относительно корня сайта
    $uploadDir = $rootDir . '/uploads/batch/'; // D:\web\uploads\batch\
    $torrentDir = $rootDir . '/' . $torrent_dir . '/'; // D:\web\torrents\ (или что там в $torrent_dir)
    $imageDir = $rootDir . '/torrents/images/'; // D:\web\torrents\images\
   
    
    foreach ([$uploadDir, $torrentDir, $imageDir] as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                json_response(false, ['error' => "Не удалось создать директорию: {$dir}"]);
            }
        }
        if (!is_writable($dir)) {
            json_response(false, ['error' => "Нет прав на запись в директорию: {$dir}"]);
        }
    }

    // ------------------- Подготовка изображений -------------------
    $availableImages = [];
    $posterFiles = [];
    
    // Обработка персональных постеров (новый формат)
    if (isset($_FILES['posters'])) {
        foreach ($_FILES['posters']['name'] as $idx => $name) {
            if ($_FILES['posters']['error'][$idx] === UPLOAD_ERR_OK && 
                !empty($_FILES['posters']['tmp_name'][$idx])) {
                $posterFiles[$idx] = [
                    'name' => $name,
                    'tmp_name' => $_FILES['posters']['tmp_name'][$idx],
                    'type' => $_FILES['posters']['type'][$idx],
                    'error' => $_FILES['posters']['error'][$idx],
                    'size' => $_FILES['posters']['size'][$idx]
                ];
            }
        }
    }
    
    // Обработка старых изображений (для обратной совместимости)
    if (isset($_FILES['imagesUpload'])) {
        foreach ($_FILES['imagesUpload']['name'] as $idx => $name) {
            if ($_FILES['imagesUpload']['error'][$idx] === UPLOAD_ERR_OK && 
                !empty($_FILES['imagesUpload']['tmp_name'][$idx])) {
                $availableImages[] = [
                    'name' => $name,
                    'tmp_name' => $_FILES['imagesUpload']['tmp_name'][$idx],
                    'type' => $_FILES['imagesUpload']['type'][$idx],
                    'error' => $_FILES['imagesUpload']['error'][$idx],
                    'size' => $_FILES['imagesUpload']['size'][$idx]
                ];
            }
        }
    }

    // ------------------- Обработка CSV импорта -------------------
    $csvData = [];
    if (isset($_FILES['csvImport']) && $_FILES['csvImport']['error'] === UPLOAD_ERR_OK) {
        $csvData = parseCSV($_FILES['csvImport']['tmp_name']);
    }

    $results = [];
    $errors = [];
    $successCount = 0;

    // ------------------- Обработка каждого файла -------------------
    for ($i = 0; $i < $fileCount; $i++) {
        $name = $_FILES['torrentFiles']['name'][$i];
        $torrentFile = [
            'name' => $name,
            'type' => $_FILES['torrentFiles']['type'][$i],
            'tmp_name' => $_FILES['torrentFiles']['tmp_name'][$i],
            'error' => $_FILES['torrentFiles']['error'][$i],
            'size' => $_FILES['torrentFiles']['size'][$i]
        ];

        if ($torrentFile['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Ошибка файла '{$name}': код ошибки " . $torrentFile['error'];
            continue;
        }

        // ------------------- Сохраняем временно -------------------
        $savedFile = saveBatchFile($torrentFile, $uploadDir, ['application/x-bittorrent']);
        if (isset($savedFile['error'])) {
            $errors[] = "'{$name}': " . $savedFile['error'];
            continue;
        }

        // ------------------- Работа с торрентом -------------------
        try {
            $torrentPath = $savedFile['path'];
            $torrentObj = TorrentFile::load($torrentPath);

            $info_hash = (string) $torrentObj->v1()->getInfoHash();
            $filesList = $torrentObj->v1()->getFiles();
            $numfiles = count($filesList);
            $size = 0;
            foreach ($filesList as $f) $size += $f->length;

            // ------------------- Получение данных из CSV -------------------
            $csvMetadata = null;
            if (!empty($csvData)) {
                $csvMetadata = findCSVData($csvData, $name);
            }

            // ------------------- Подготовка данных для БД -------------------
            $torrentName = pathinfo($name, PATHINFO_FILENAME);
            $torrentName = substr($torrentName, 0, 255);
            $torrentId = time() + $i;
            
            // Используем данные из CSV или из формы
            if ($csvMetadata) {
                $category = (int)($csvMetadata['category'] ?? $_POST['batch_category'] ?? 1);
                $description = $db->escape_string($csvMetadata['description'] ?? $_POST['batch_description'] ?? '');
                $customName = $db->escape_string($csvMetadata['name'] ?? $torrentName);
            } else {
                $category = (int)($_POST['batch_categories'][$i] ?? $_POST['batch_category'] ?? 1);
                $description = $db->escape_string($_POST['descriptions'][$i] ?? $_POST['batch_description'] ?? '');
                $customName = $db->escape_string($torrentName);
            }

            $dbData = [
                'name' => $customName,
                'filename' => $db->escape_string($torrentId . '.torrent'),
                'info_hash' => $db->escape_string($info_hash),
                'size' => (int)$size,
                'numfiles' => (int)$numfiles,
                'owner' => $db->escape_string($CURUSER['id']),
                'added' => TIMENOW,
                'category' => $category,
                'descr' => $description,
                'anonymous' => isset($_POST['batch_anonymous']) ? 'yes' : 'no',
                't_link' => '',
                'visible' => 'yes',
                'ts_external_url' => $db->escape_string($torrentObj->getAnnounce() ?? ''),
                'ts_external' => 'yes'
            ];

            // ------------------- Вставка с rollback -------------------
            $db->sql_query("START TRANSACTION");
            try {
                $db->insert_query("torrents", $dbData);
                $newId = $db->insert_id();
                if (!$newId) throw new Exception("Не удалось добавить торрент в базу данных");

                $finalPath = $torrentDir . $newId . '.torrent';
                
                error_log("Copying torrent from $torrentPath to $finalPath");
                if (!copy($torrentPath, $finalPath)) {
                    error_log("Copy failed. Source exists: " . (file_exists($torrentPath) ? 'yes' : 'no'));
                    error_log("Destination writable: " . (is_writable(dirname($finalPath)) ? 'yes' : 'no'));
                    throw new Exception("Не удалось скопировать файл торрента");
                }

                $db->update_query("torrents", ['filename' => $newId . '.torrent'], "id='{$newId}'");

                // ------------------- Обработка изображений -------------------
                $imageProcessed = false;
                
                // Пробуем использовать персональный постер
                if (isset($posterFiles[$i])) {
                    $imageProcessed = processImage($posterFiles[$i], $newId, $imageDir, $db, $BASEURL);
                }
                
                // Если персонального постера нет, используем первый из старых изображений
                if (!$imageProcessed && !empty($availableImages)) {
                    $imageFile = $availableImages[0];
                    $imageProcessed = processImage($imageFile, $newId, $imageDir, $db, $BASEURL);
                }

                $db->sql_query("COMMIT");
                @unlink($torrentPath);

                // Логирование
                write_log(sprintf($lang->upload['newtorrent'],
                    '[URL=' . $BASEURL . '/' . get_torrent_link($newId) . ']<font color=red>' . $dbData['name'] . '</font>[/URL]',
                    '[URL=' . $BASEURL . '/' . get_profile_link($CURUSER['id']) . ']' . format_name($CURUSER['username'], $CURUSER['usergroup']) . '[/URL]'));

                $results[] = [
                    'id' => $newId,
                    'name' => htmlspecialchars($dbData['name']),
                    'size' => format_size_custom($size),
                    'files' => $numfiles,
                    'link' => get_torrent_link($newId),
                    'has_poster' => $imageProcessed
                ];
                $successCount++;

            } catch (\Exception $e) {
                $db->sql_query("ROLLBACK");
                @unlink($torrentPath);
                $errors[] = "Ошибка '{$name}': " . $e->getMessage();
                error_log("Transaction error for torrent $i: " . $e->getMessage());
            }

        } catch (\Exception $e) {
            $errors[] = "Ошибка обработки '{$name}': " . $e->getMessage();
            error_log("Torrent processing error for $i: " . $e->getMessage());
        }
    }

    if (isset($cache) && method_exists($cache, 'update_torrents')) {
        $cache->update_torrents();
    }

    // ------------------- Формирование ответа -------------------
    $response = [
        'processed' => $fileCount,
        'successful' => $successCount,
        'failed' => count($errors),
        'results' => $results,
        'errors' => $errors,
        'stats' => [
            'total_torrents' => $fileCount,
            'with_posters' => count(array_filter($results, function($r) { return $r['has_poster']; })),
            'csv_imported' => !empty($csvData) ? count($csvData) : 0
        ]
    ];

    json_response(true, $response);
}

function processImage($imageFile, $torrentId, $imageDir, $db, $BASEURL) {
    $allowedMimeTypes = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    
    if (!in_array($imageFile['type'], $allowedMimeTypes, true)) {
        error_log("Invalid image type: " . $imageFile['type']);
        return false;
    }
    
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];
    
    $ext = $mimeToExt[$imageFile['type']] ?? 'jpg';
    $imageFilename = $torrentId . '.' . $ext;
    $targetPath = rtrim($imageDir, '/') . '/' . $imageFilename;
    
    error_log("Processing image for torrent $torrentId: " . $targetPath);
    
    // Копируем изображение
    if (copy($imageFile['tmp_name'], $targetPath)) {
        // Формируем относительный путь для URL
        $rootDir = dirname(__DIR__);
        $relativePath = str_replace($rootDir, '', $targetPath);
        $relativePath = ltrim($relativePath, '/\\');
        
        $NewImageURL = $BASEURL . '/' . $relativePath;
        $db->update_query("torrents", [
            't_image' => $db->escape_string($NewImageURL)
        ], "id='{$torrentId}'");
        
        error_log("Successfully uploaded poster for torrent $torrentId: $targetPath");
        return true;
    }
    
    error_log("Failed to copy image from {$imageFile['tmp_name']} to {$targetPath}");
    return false;
}

function parseCSV($filePath) {
    $data = [];
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($row) >= 4) {
                $data[] = [
                    'torrent_filename' => $row[0] ?? '',
                    'name' => $row[1] ?? '',
                    'category' => $row[2] ?? '',
                    'description' => $row[3] ?? ''
                ];
            }
        }
        fclose($handle);
    }
    return $data;
}

function findCSVData($csvData, $filename) {
    foreach ($csvData as $row) {
        if ($row['torrent_filename'] === $filename) {
            return $row;
        }
    }
    return null;
}

function saveBatchFile($file, $targetDir, $allowedTypes) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return ['error' => 'Ошибка загрузки файла'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedTypes)) return ['error' => 'Неверный формат файла'];

    $filename = uniqid('batch_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $targetPath = rtrim($targetDir, '/') . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath, 'original_name' => $file['name']];
    }
    return ['error' => 'Не удалось сохранить файл'];
}

function format_size_custom($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $pow = floor(log($bytes)/log(1024));
    $pow = min($pow, count($units)-1);
    $bytes /= pow(1024,$pow);
    return round($bytes,2).' '.$units[$pow];
}

function getCategoryOptions($selected = 0) {
    global $db;
    $options = '';
    $query = $db->simple_select("categories", "*", "", array("order_by" => "sort", "order_dir" => "ASC"));
    while ($category = $db->fetch_array($query)) {
        $sel = ($selected == $category['cid']) ? ' selected="selected"' : '';
        $options .= '<option value="' . (int)$category['cid'] . '"' . $sel . '>' . htmlspecialchars($category['name']) . '</option>';
    }
    return $options;
}

function showForm() {
    global $mybb;
    
    stdhead('Пакетная загрузка торрентов');
    ?>

<div class="container mt-4">
    <h2 class="mb-4">
        <i class="fa-solid fa-layer-group me-2 text-primary"></i>
        Batch Torrent Upload
    </h2>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-upload me-2"></i>
                        Upload Multiple Torrents
                    </h5>
                </div>
                <div class="card-body">
                    <form id="batchUploadForm" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="my_post_key" value="<?php echo htmlspecialchars($mybb->post_code); ?>" />

                        <!-- Drag & Drop Zone -->
                        <div class="drop-zone mb-4 p-5 border border-dashed rounded text-center" 
                             style="border-color: #6c757d; background: #f8f9fa;">
                            <i class="fa-solid fa-cloud-upload fa-3x text-muted mb-3"></i>
                            <h5>Drag & Drop Torrent Files Here</h5>
                            <p class="text-muted">or click to browse</p>
                            <input type="file" id="dragDropFiles" style="display: none;" multiple accept=".torrent">
                        </div>

                        <!-- Торренты и постеры -->
                        <div id="torrentContainer">
                            <div class="torrent-item mb-3 border p-3 rounded">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fa-solid fa-file-archive me-1"></i>
                                            Torrent File *
                                        </label>
                                        <input class="form-control" type="file" name="torrentFiles[]" accept=".torrent" required>
                                        <div class="torrent-name mt-1 small text-muted"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fa-solid fa-image me-1"></i>
                                            Poster Image (Optional)
                                        </label>
                                        <input class="form-control poster-file" type="file" name="posters[]" accept="image/*">
                                        <div class="image-preview mt-2" style="max-width: 150px; display: none;">
                                            <img src="" class="img-thumbnail" style="max-height: 100px;">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Category</label>
                                        <?php echo ts_category_list('batch_categories[]', 0); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="descriptions[]" rows="2" placeholder="Description for this torrent"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="button" id="addMore" class="btn btn-outline-primary">
                                <i class="fa-solid fa-plus"></i> Add Another Torrent
                            </button>
                        </div>

                        <!-- CSV Import -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fa-solid fa-file-csv me-1"></i>
                                Import Metadata from CSV (Optional)
                            </label>
                            <input class="form-control" type="file" id="csvImport" name="csvImport" accept=".csv">
                            <div class="form-text">CSV format: torrent_filename,name,category,description</div>
                        </div>

                        <!-- Global Settings -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Global Settings (applies to all torrents)</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="batch_anonymous" name="batch_anonymous" value="yes">
                                    <label class="form-check-label" for="batch_anonymous">Anonymous Upload</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                <i class="fa-solid fa-arrow-left me-1"></i> Back
                            </button>
                            <button type="submit" class="btn btn-primary" id="batchUploadBtn">
                                <i class="fa-solid fa-cloud-upload me-1"></i> Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">Instructions</h6>
                </div>
                <div class="card-body">
                    <ol class="mb-0 small">
                        <li class="mb-2">Drag & drop or select .torrent files</li>
                        <li class="mb-2">Add poster images for each torrent</li>
                        <li class="mb-2">Set categories and descriptions</li>
                        <li class="mb-2">Optional: Import metadata from CSV</li>
                        <li class="mb-2">Configure global settings</li>
                        <li>Click "Upload"</li>
                    </ol>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">System Status</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fa-solid fa-check-circle text-success me-2"></i>
                            Torrent Parser: Available
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-hdd me-2 text-primary"></i>
                            Maximum Files: 10
                        </li>
                        <li>
                            <i class="fa-solid fa-image me-2 text-info"></i>
                            Max Image Size: 5MB
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-white">
                    <h6 class="mb-0">CSV Template</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2">Download example CSV:</p>
                    <a href="data:text/csv;charset=utf-8,torrent_filename,name,category,description%0Amovie.torrent,My%20Movie%20Title,1,Great%20movie%20description%0Aseries.torrent,TV%20Series,2,Complete%20season" 
                       download="torrent_metadata_template.csv" class="btn btn-sm btn-outline-warning">
                        <i class="fa-solid fa-download me-1"></i> Download Template
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
                <h5 class="modal-title">
                    <i class="fa-solid fa-spinner fa-spin me-2"></i>Processing Upload
                </h5>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Overall Progress</span>
                        <span id="overallProgressPercent" class="fw-bold">0%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             id="overallProgressBar" style="width: 0%"></div>
                    </div>
                </div>
                
                <div id="fileProgressContainer" class="mb-3"></div>
                
                <div id="resultsContainer" style="display: none;">
                    <h6 class="border-bottom pb-2 mb-3">Results</h6>
                    <div id="resultsList"></div>
                    <div id="bulkActions" class="mt-3" style="display: none;">
                        <h6>Bulk Actions</h6>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-success" onclick="bulkEditSelected()">
                                <i class="fas fa-edit"></i> Edit Selected
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="bulkFreeSelected()">
                                <i class="fas fa-tag"></i> Make Free
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="bulkSilverSelected()">
                                <i class="fas fa-star"></i> Make Silver
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="closeModalBtn" style="display: none;">Close</button>
                <button type="button" class="btn btn-primary" id="viewTorrentsBtn" style="display: none;">
                    <i class="fa-solid fa-eye"></i> View Torrents
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let torrentCount = 1;

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('batchUploadForm');
    const progressModal = new bootstrap.Modal(document.getElementById('batchProgressModal'));
    const overallProgressBar = document.getElementById('overallProgressBar');
    const overallProgressPercent = document.getElementById('overallProgressPercent');
    const fileProgressContainer = document.getElementById('fileProgressContainer');
    const resultsContainer = document.getElementById('resultsContainer');
    const resultsList = document.getElementById('resultsList');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const viewTorrentsBtn = document.getElementById('viewTorrentsBtn');
    const bulkActions = document.getElementById('bulkActions');
    const dropZone = document.querySelector('.drop-zone');
    const dragDropInput = document.getElementById('dragDropFiles');
    const addMoreBtn = document.getElementById('addMore');
    
    // Инициализация первого элемента торрента
    updateTorrentCount();
    
    // Drag & Drop
    if (dropZone) {
        dropZone.addEventListener('click', () => dragDropInput.click());
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#0d6efd';
            dropZone.style.backgroundColor = '#e7f1ff';
        });
        dropZone.addEventListener('dragleave', () => {
            dropZone.style.borderColor = '#6c757d';
            dropZone.style.backgroundColor = '#f8f9fa';
        });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#6c757d';
            dropZone.style.backgroundColor = '#f8f9fa';
            
            const files = e.dataTransfer.files;
            handleDroppedFiles(files);
        });
    }
    
    if (dragDropInput) {
        dragDropInput.addEventListener('change', function() {
            handleDroppedFiles(this.files);
        });
    }
    
    // Добавление новых торрентов
    if (addMoreBtn) {
        addMoreBtn.addEventListener('click', function() {
            if (torrentCount >= 10) {
                alert('Maximum 10 torrents allowed');
                return;
            }
            addTorrentItem(null, torrentCount);
            torrentCount++;
            updateUploadButton();
            updateTorrentCount();
        });
    }
    
    // Предпросмотр изображений
    document.addEventListener('change', function(e) {
        if (e.target.matches('input[name="posters[]"]')) {
            const file = e.target.files[0];
            if (file) {
                // Валидация изображения
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                const maxSize = 5 * 1024 * 1024;
                
                if (!validTypes.includes(file.type)) {
                    alert('Please upload a valid image file (JPEG, PNG, GIF, WebP)');
                    e.target.value = '';
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('Image file is too large. Maximum size is 5MB');
                    e.target.value = '';
                    return;
                }
                
                const parentDiv = e.target.closest('.col-md-6');
                if (parentDiv) {
                    const previewContainer = parentDiv.querySelector('.image-preview');
                    if (previewContainer) {
                        const previewImg = previewContainer.querySelector('img');
                        if (previewImg) {
                            const reader = new FileReader();
                            
                            reader.onload = function(e) {
                                previewImg.src = e.target.result;
                                previewContainer.style.display = 'block';
                            }
                            
                            reader.readAsDataURL(file);
                        }
                    }
                }
            }
        }
    });
    
    // Обработка формы
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const torrentInputs = document.querySelectorAll('input[name="torrentFiles[]"]');
            const hasFiles = Array.from(torrentInputs).some(input => input.files.length > 0);
            
            if (!hasFiles) {
                alert('Please select at least one torrent file');
                return;
            }
            
            // Валидация изображений
            if (!validateImageFiles()) {
                return;
            }
            
            // Сброс
            resetUI();
            
            // Показ модального окна
            if (progressModal) {
                progressModal.show();
            }
            
            // Создаем прогресс-бары для файлов
            createFileProgressItems();
            
            // Отправка формы
            uploadFiles();
        });
    }
    
    // Закрытие модального окна
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            if (progressModal) {
                progressModal.hide();
            }
        });
    }
    
    if (viewTorrentsBtn) {
        viewTorrentsBtn.addEventListener('click', function() {
            window.open('/browse.php', '_blank');
        });
    }
    
    function handleDroppedFiles(files) {
        const torrentContainer = document.getElementById('torrentContainer');
        if (!torrentContainer) return;
        
        // Очищаем существующие элементы (кроме первого)
        const existingItems = torrentContainer.querySelectorAll('.torrent-item');
        if (existingItems.length > 0) {
            // Оставляем первый элемент, остальные удаляем
            for (let i = 1; i < existingItems.length; i++) {
                existingItems[i].remove();
            }
            // Очищаем первый элемент
            const firstItem = existingItems[0];
            firstItem.querySelector('input[name="torrentFiles[]"]').value = '';
            firstItem.querySelector('.torrent-name').textContent = '';
            firstItem.querySelector('input[name="posters[]"]').value = '';
            const firstPreview = firstItem.querySelector('.image-preview');
            if (firstPreview) firstPreview.style.display = 'none';
            firstItem.querySelector('textarea[name="descriptions[]"]').value = '';
        }
        
        torrentCount = 0;
        
        // Создаем элементы для каждого файла
        const torrentFiles = Array.from(files).filter(file => file.name.endsWith('.torrent')).slice(0, 10);
        torrentFiles.forEach((file, index) => {
            if (index === 0 && existingItems.length > 0) {
                // Используем существующий первый элемент
                const firstItem = existingItems[0];
                setFileToInput(firstItem.querySelector('input[name="torrentFiles[]"]'), file);
                firstItem.querySelector('.torrent-name').textContent = file.name;
                torrentCount++;
            } else {
                addTorrentItem(file, index);
                torrentCount++;
            }
        });
        
        updateUploadButton();
        updateTorrentCount();
    }
    
    function setFileToInput(inputElement, file) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        inputElement.files = dataTransfer.files;
        
        // Триггерим событие change
        const event = new Event('change', { bubbles: true });
        inputElement.dispatchEvent(event);
    }
    
    function addTorrentItem(file, index) {
        const torrentContainer = document.getElementById('torrentContainer');
        if (!torrentContainer) return;
        
        // Создаем HTML элемент с нуля вместо клонирования
        const newItem = document.createElement('div');
        newItem.className = 'torrent-item mb-3 border p-3 rounded';
        newItem.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label fw-bold">
                        <i class="fa-solid fa-file-archive me-1"></i>
                        Torrent File *
                    </label>
                    <input class="form-control" type="file" name="torrentFiles[]" accept=".torrent" ${file ? '' : 'required'}>
                    <div class="torrent-name mt-1 small text-muted">${file ? escapeHtml(file.name) : ''}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">
                        <i class="fa-solid fa-image me-1"></i>
                        Poster Image (Optional)
                    </label>
                    <input class="form-control poster-file" type="file" name="posters[]" accept="image/*">
                    <div class="image-preview mt-2" style="max-width: 150px; display: none;">
                        <img src="" class="img-thumbnail" style="max-height: 100px;">
                    </div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select class="form-control" name="batch_categories[${index}]">
                        ${getCategoryOptions()}
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="descriptions[]" rows="2" placeholder="Description for this torrent"></textarea>
                </div>
            </div>
        `;
        
        torrentContainer.appendChild(newItem);
        
        // Если есть файл, добавляем его в input
        if (file) {
            const fileInput = newItem.querySelector('input[name="torrentFiles[]"]');
            setFileToInput(fileInput, file);
        }
        
        // Добавляем обработчик для предпросмотра изображений
        const posterInput = newItem.querySelector('input[name="posters[]"]');
        if (posterInput) {
            posterInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const parentDiv = e.target.closest('.col-md-6');
                    if (parentDiv) {
                        const previewContainer = parentDiv.querySelector('.image-preview');
                        if (previewContainer) {
                            const previewImg = previewContainer.querySelector('img');
                            if (previewImg) {
                                const reader = new FileReader();
                                
                                reader.onload = function(e) {
                                    previewImg.src = e.target.result;
                                    previewContainer.style.display = 'block';
                                }
                                
                                reader.readAsDataURL(file);
                            }
                        }
                    }
                }
            });
        }
    }
    
    function getCategoryOptions() {
        // Эта функция должна возвращать HTML опций категорий
        // В реальном коде это должно быть динамически сгенерировано
        return `
            <option value="1">Category 1</option>
            <option value="2">Category 2</option>
            <option value="3">Category 3</option>
            <option value="4">Category 4</option>
        `;
    }
    
    function updateUploadButton() {
        const uploadBtn = document.getElementById('batchUploadBtn');
        if (uploadBtn) {
            uploadBtn.innerHTML = `<i class="fa-solid fa-cloud-upload me-1"></i> Upload ${torrentCount} Torrent${torrentCount !== 1 ? 's' : ''}`;
        }
    }
    
    function updateTorrentCount() {
        const torrentItems = document.querySelectorAll('.torrent-item');
        torrentCount = torrentItems.length;
    }
    
    function validateImageFiles() {
        const imageInputs = document.querySelectorAll('input[name="posters[]"]');
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 5 * 1024 * 1024;
        
        for (let input of imageInputs) {
            if (input.files.length > 0) {
                const file = input.files[0];
                if (!validTypes.includes(file.type)) {
                    alert(`File ${file.name} is not a valid image type`);
                    return false;
                }
                if (file.size > maxSize) {
                    alert(`File ${file.name} is too large. Maximum size is 5MB`);
                    return false;
                }
            }
        }
        return true;
    }
    
    function resetUI() {
        if (resultsContainer) resultsContainer.style.display = 'none';
        if (resultsList) resultsList.innerHTML = '';
        if (fileProgressContainer) fileProgressContainer.innerHTML = '';
        if (overallProgressBar) {
            overallProgressBar.style.width = '0%';
            overallProgressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        }
        if (overallProgressPercent) overallProgressPercent.textContent = '0%';
        if (closeModalBtn) closeModalBtn.style.display = 'none';
        if (viewTorrentsBtn) viewTorrentsBtn.style.display = 'none';
        if (bulkActions) bulkActions.style.display = 'none';
    }
    
    function createFileProgressItems() {
        const torrentInputs = document.querySelectorAll('input[name="torrentFiles[]"]');
        torrentInputs.forEach((input, index) => {
            if (input.files.length > 0) {
                const fileName = input.files[0].name;
                const progressItem = document.createElement('div');
                progressItem.className = 'mb-2';
                progressItem.id = `fileProgress_${index}`;
                progressItem.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-truncate" style="max-width: 70%">
                            <i class="fas fa-file me-1"></i>${escapeHtml(fileName)}
                        </span>
                        <span class="badge bg-secondary">Waiting</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" style="width: 0%"></div>
                    </div>
                `;
                if (fileProgressContainer) {
                    fileProgressContainer.appendChild(progressItem);
                }
            }
        });
    }
    
    function uploadFiles() {
        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                updateOverallProgress(percent);
                
                // Обновляем прогресс для каждого файла
                const torrentInputs = document.querySelectorAll('input[name="torrentFiles[]"]');
                torrentInputs.forEach((input, index) => {
                    if (input.files.length > 0) {
                        const filePercent = Math.round((index / torrentInputs.length) * 100 + (percent / torrentInputs.length));
                        updateFileProgress(index, 'uploading', filePercent);
                    }
                });
            }
        });
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                try {
                    if (!xhr.getResponseHeader('Content-Type')?.includes('application/json')) {
                        throw new Error('Server returned non-JSON response');
                    }
                    
                    const data = JSON.parse(xhr.responseText);
                    
                    if (data.success) {
                        showResults(data);
                    } else {
                        showError(data.error || 'Server error');
                    }
                } catch(e) {
                    console.error('Error:', e);
                    showError('Response processing error: ' + e.message);
                }
            }
        };
        
        xhr.onerror = function() {
            showError('Network error');
        };
        
        xhr.timeout = 300000;
        xhr.ontimeout = function() {
            showError('Timeout');
        };
        
        xhr.open('POST', '<?php $_this_script_ ?>');
        xhr.send(formData);
    }
    
    function updateOverallProgress(percent) {
        if (overallProgressBar) {
            overallProgressBar.style.width = percent + '%';
        }
        if (overallProgressPercent) {
            overallProgressPercent.textContent = percent + '%';
        }
    }
    
    function updateFileProgress(index, status, percent = 0) {
        const progressItem = document.getElementById(`fileProgress_${index}`);
        if (!progressItem) return;
        
        const statusElement = progressItem.querySelector('.badge');
        const progressBar = progressItem.querySelector('.progress-bar');
        
        if (statusElement && progressBar) {
            progressBar.style.width = percent + '%';
            
            switch(status) {
                case 'uploading':
                    statusElement.className = 'badge bg-info';
                    statusElement.textContent = 'Uploading...';
                    progressBar.className = 'progress-bar bg-info progress-bar-striped progress-bar-animated';
                    break;
                case 'processing':
                    statusElement.className = 'badge bg-warning';
                    statusElement.textContent = 'Processing...';
                    progressBar.className = 'progress-bar bg-warning progress-bar-striped progress-bar-animated';
                    break;
                case 'success':
                    statusElement.className = 'badge bg-success';
                    statusElement.textContent = 'Success';
                    progressBar.className = 'progress-bar bg-success';
                    break;
                case 'error':
                    statusElement.className = 'badge bg-danger';
                    statusElement.textContent = 'Error';
                    progressBar.className = 'progress-bar bg-danger';
                    break;
            }
        }
    }
    
    function showResults(data) {
        if (resultsContainer) resultsContainer.style.display = 'block';
        if (overallProgressBar) {
            overallProgressBar.className = 'progress-bar bg-success';
            overallProgressBar.style.width = '100%';
        }
        if (overallProgressPercent) overallProgressPercent.textContent = '100%';
        
        // Обновляем статусы файлов
        if (data.results && data.results.length > 0) {
            data.results.forEach((result, index) => {
                updateFileProgress(index, 'success', 100);
            });
        }
        
        // Статистика
        const stats = document.createElement('div');
        stats.className = 'alert alert-success mb-3';
        let statsHTML = `
            <i class="fas fa-check-circle me-2"></i>
            <strong>Success!</strong> Uploaded ${data.successful} of ${data.processed} torrents<br>
            <small class="text-muted">
                ${data.stats?.with_posters || 0} with posters | 
                ${data.stats?.csv_imported || 0} CSV records imported
            </small>
        `;
        stats.innerHTML = statsHTML;
        if (resultsList) resultsList.appendChild(stats);
        
        // Результаты с чекбоксами для bulk actions
        if (data.results && data.results.length > 0) {
            const resultsDiv = document.createElement('div');
            resultsDiv.className = 'mb-3';
            
            data.results.forEach(result => {
                const item = document.createElement('div');
                item.className = 'alert alert-light border mb-2 d-flex align-items-center';
                item.innerHTML = `
                    <div class="form-check me-3">
                        <input class="form-check-input torrent-checkbox" type="checkbox" value="${result.id}" id="torrent_${result.id}">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${escapeHtml(result.name)}</strong><br>
                                <small class="text-muted">ID: ${result.id} | Files: ${result.files} | Size: ${result.size}</small>
                            </div>
                            <div>
                                ${result.has_poster ? '<span class="badge bg-info me-2"><i class="fas fa-image"></i></span>' : ''}
                                <a href="${result.link}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                            </div>
                        </div>
                    </div>
                `;
                resultsDiv.appendChild(item);
            });
            
            if (resultsList) resultsList.appendChild(resultsDiv);
            if (bulkActions) bulkActions.style.display = 'block';
        }
        
        // Ошибки
        if (data.errors && data.errors.length > 0) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger mt-3';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Errors (${data.errors.length}):</strong><br>
                <ul class="mb-0">
                    ${data.errors.map(error => `<li>${escapeHtml(error)}</li>`).join('')}
                </ul>
            `;
            if (resultsList) resultsList.appendChild(errorDiv);
        }
        
        if (closeModalBtn) closeModalBtn.style.display = 'block';
        if (viewTorrentsBtn) viewTorrentsBtn.style.display = 'block';
    }
    
    function showError(message) {
        if (resultsContainer) resultsContainer.style.display = 'block';
        if (overallProgressBar) overallProgressBar.className = 'progress-bar bg-danger';
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Error:</strong> ${escapeHtml(message)}
        `;
        if (resultsList) resultsList.appendChild(errorDiv);
        
        if (closeModalBtn) closeModalBtn.style.display = 'block';
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});

// Bulk Actions Functions
function bulkEditSelected() {
    const selected = getSelectedTorrents();
    if (selected.length === 0) {
        alert('Please select at least one torrent');
        return;
    }
    // Реализуйте редактирование выбранных торрентов
    alert(`Editing ${selected.length} torrents`);
}

function bulkFreeSelected() {
    const selected = getSelectedTorrents();
    if (selected.length === 0) {
        alert('Please select at least one torrent');
        return;
    }
    if (confirm(`Make ${selected.length} torrent(s) free?`)) {
        // Отправьте AJAX запрос для изменения статуса
        fetch('/api/torrents/bulk_free.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({torrents: selected})
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  alert('Torrents updated successfully');
                  location.reload();
              } else {
                  alert('Error: ' + data.error);
              }
          });
    }
}

function bulkSilverSelected() {
    const selected = getSelectedTorrents();
    if (selected.length === 0) {
        alert('Please select at least one torrent');
        return;
    }
    if (confirm(`Make ${selected.length} torrent(s) silver?`)) {
        // Отправьте AJAX запрос для изменения статуса
        fetch('/api/torrents/bulk_silver.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({torrents: selected})
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  alert('Torrents updated successfully');
                  location.reload();
              } else {
                  alert('Error: ' + data.error);
              }
          });
    }
}

function getSelectedTorrents() {
    const checkboxes = document.querySelectorAll('.torrent-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}
</script>
<style>
.drop-zone:hover {
    border-color: #0d6efd !important;
    background-color: #e7f1ff !important;
    cursor: pointer;
}
.torrent-item:hover {
    border-color: #0d6efd;
    background-color: #f8f9fa;
}
</style>
<?php
    stdfoot();
}