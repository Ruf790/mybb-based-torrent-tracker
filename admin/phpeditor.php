<?php
declare(strict_types=1);

// ── Конфигурация ──────────────────────────────────────────
if (!defined('TSDIR')) {
    die('Direct access not allowed');
}

const PHP_EDITOR_BASE_DIR  = TSDIR . '/';
const PHP_MAX_BACKUPS      = 10;
const PHP_HISTORY_DAYS     = 30;
const PHP_ALLOWED_EXTENSIONS = ['php', 'html', 'tpl', 'js', 'json', 'twig'];

// Белый список директорий — редактировать только их
const PHP_ALLOWED_DIRS = [
    '',           // корень трекера
    'admin/',
    'config/',
    'include/',
    'scripts/',
];

function scanPhpFiles(string $baseDir): array
{
    $result = [];
    $exts   = '{' . implode(',', PHP_ALLOWED_EXTENSIONS) . '}';

    foreach (PHP_ALLOWED_DIRS as $dir) {
        $path  = rtrim($baseDir . $dir, DIRECTORY_SEPARATOR);
        $files = glob($path . DIRECTORY_SEPARATOR . '*.' . $exts, GLOB_BRACE) ?: [];
        foreach ($files as $file) {
            if (str_contains($file, DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR)) continue;
            $relative      = ltrim(str_replace($baseDir, '', $file), DIRECTORY_SEPARATOR);
            $result[$relative] = $relative;
        }
    }

    ksort($result);
    return array_values($result);
}

define('PHP_ALLOWED_FILES', scanPhpFiles(PHP_EDITOR_BASE_DIR));

// ── Класс редактора ───────────────────────────────────────
class PhpEditor
{
    private string $baseDir;
    private string $backupDir;
    private array  $allowedFiles;

    public function __construct(string $baseDir, array $allowedFiles)
    {
        $this->baseDir      = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->backupDir    = $this->baseDir . 'backup_php' . DIRECTORY_SEPARATOR;
        $this->allowedFiles = $allowedFiles;
        $this->initDirs();
    }

    private function initDirs(): void
    {
        foreach (['daily', 'history'] as $sub) {
            $path = $this->backupDir . $sub;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    public function getFileContent(string $filename): string
    {
        $this->assertAllowed($filename);
        $path = $this->baseDir . $filename;
        if (!file_exists($path)) {
            throw new RuntimeException("Файл не найден: {$filename}");
        }
        return file_get_contents($path) ?: '';
    }

    public function saveFile(string $filename, string $content, bool $makeBackup = true): void
    {
        $this->assertAllowed($filename);
        $path = $this->baseDir . $filename;

        if (!is_writable($path)) {
            throw new RuntimeException("Нет прав на запись: {$filename}");
        }
        if (trim($content) === '') {
            throw new RuntimeException("Содержимое не может быть пустым");
        }

        // Проверка синтаксиса PHP
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'phpcheck_');
            file_put_contents($tmpFile, $content);
            exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $code);
            unlink($tmpFile);
            if ($code !== 0) {
                throw new RuntimeException('PHP syntax error: ' . implode(' ', $output));
            }
        }

        if ($makeBackup) {
            $this->createBackup($filename);
        }
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Ошибка сохранения файла");
        }
        $this->logHistory($filename, $content);
    }

    public function getBackups(string $filename): array
    {
        $this->assertAllowed($filename);
        $safe    = str_replace(['/', '\\'], '__', $filename);
        $pattern = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . $safe . '.*.bak';
        $files   = glob($pattern) ?: [];
        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'file' => basename($file),
                'path' => $file,
                'date' => date('Y-m-d H:i:s', (int)filemtime($file)),
                'size' => (int)filesize($file),
            ];
        }
        usort($backups, fn($a, $b) => filemtime($b['path']) - filemtime($a['path']));
        return $backups;
    }

    public function getBackupContent(string $backupFilename): string
    {
        $safe = basename($backupFilename);
        if ($safe !== $backupFilename || str_contains($backupFilename, '..')) {
            throw new RuntimeException("Недопустимое имя файла");
        }
        $path = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . $safe;
        if (!file_exists($path)) {
            throw new RuntimeException("Файл бэкапа не найден");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Не удалось прочитать файл бэкапа");
        }
        return $content;
    }

    public function getFileInfo(string $filename): array
    {
        $this->assertAllowed($filename);
        $path = $this->baseDir . $filename;
        if (!file_exists($path)) return [];
        return [
            'size'     => (int)filesize($path),
            'modified' => date('Y-m-d H:i:s', (int)filemtime($path)),
            'lines'    => substr_count(file_get_contents($path) ?: '', "\n") + 1,
            'writable' => is_writable($path),
        ];
    }

    private function createBackup(string $filename): void
    {
        $source  = $this->baseDir . $filename;
        $safe    = str_replace(['/', '\\'], '__', $filename);
        $bakFile = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR
                 . $safe . '.' . date('Y-m-d_His') . '.bak';
        if (!copy($source, $bakFile)) {
            throw new RuntimeException("Не удалось создать бэкап");
        }
        $this->cleanupOldBackups();
    }

    private function cleanupOldBackups(): void
    {
        $pattern = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . '*.bak';
        $files   = glob($pattern) ?: [];
        if (count($files) <= PHP_MAX_BACKUPS) return;
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
        $toDelete = count($files) - PHP_MAX_BACKUPS;
        for ($i = 0; $i < $toDelete; $i++) unlink($files[$i]);
    }

    private function logHistory(string $filename, string $content): void
    {
        $safe        = str_replace(['/', '\\'], '__', $filename);
        $historyFile = $this->backupDir . 'history' . DIRECTORY_SEPARATOR . $safe . '.log';
        $entry       = json_encode([
            'date' => date('Y-m-d H:i:s'),
            'user' => $_SESSION['username'] ?? 'anonymous',
            'size' => strlen($content),
            'hash' => md5($content),
        ]);
        file_put_contents($historyFile, $entry . PHP_EOL, FILE_APPEND);
        $this->cleanupOldHistory($historyFile);
    }

    private function cleanupOldHistory(string $historyFile): void
    {
        if (!file_exists($historyFile)) return;
        $cutoff   = strtotime('-' . PHP_HISTORY_DAYS . ' days');
        $lines    = file($historyFile) ?: [];
        $filtered = array_filter($lines, function (string $line) use ($cutoff): bool {
            $data = json_decode(trim($line), true);
            if (!is_array($data) || !isset($data['date'])) return false;
            return strtotime($data['date']) >= $cutoff;
        });
        file_put_contents($historyFile, implode('', $filtered));
    }

    private function assertAllowed(string $filename): void
    {
        // Защита от path traversal
        if (str_contains($filename, '..') || str_contains($filename, "\0")) {
            throw new RuntimeException("Недопустимое имя файла");
        }
        if (!in_array($filename, $this->allowedFiles, true)) {
            throw new RuntimeException("Доступ запрещён: {$filename}");
        }
    }
}

function phpFormatSize(int $bytes): string
{
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i     = (int)floor(log($bytes) / log(1024));
    return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
}

function getCodemirrorMode(string $filename): string
{
    return match(strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
        'php'        => 'application/x-httpd-php',
        'html', 'tpl','twig' => 'text/html',
        'js'         => 'text/javascript',
        'json'       => 'application/json',
        'css'        => 'text/css',
        default      => 'text/plain',
    };
}

// ── Инициализация ─────────────────────────────────────────
$editor      = new PhpEditor(PHP_EDITOR_BASE_DIR, PHP_ALLOWED_FILES);
$allowedList = PHP_ALLOWED_FILES;
$currentFile = $_GET['file'] ?? ($allowedList[0] ?? '');
if (!in_array($currentFile, $allowedList, true)) {
    $currentFile = $allowedList[0] ?? '';
}

// ── AJAX ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $action   = $_POST['action']   ?? '';
        $filename = $_POST['file']     ?? '';

        // Нормализуем путь
        $filename = ltrim(str_replace(['..', "\0"], '', $filename), '/\\');

        switch ($action) {
            case 'save':
                $content    = $_POST['content'] ?? '';
                $makeBackup = ($_POST['backup'] ?? '0') === '1';
                $editor->saveFile($filename, $content, $makeBackup);
                echo json_encode([
                    'status'  => 'success',
                    'message' => $makeBackup ? 'Файл сохранён с бэкапом!' : 'Файл сохранён!',
                    'info'    => $editor->getFileInfo($filename),
                ]);
                break;

            case 'get_backups':
                $backups = $editor->getBackups($filename);
                foreach ($backups as &$b) {
                    $b['size_fmt'] = phpFormatSize($b['size']);
                }
                echo json_encode(['status' => 'success', 'backups' => $backups]);
                break;

            case 'get_backup_content':
                $content = $editor->getBackupContent($_POST['backup_file'] ?? '');
                echo json_encode(['status' => 'success', 'content' => $content]);
                break;

            case 'get_file_info':
                echo json_encode(['status' => 'success', 'info' => $editor->getFileInfo($filename)]);
                break;

            default:
                throw new RuntimeException("Неизвестное действие: {$action}");
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Данные для страницы ───────────────────────────────────
try {
    $currentContent  = $editor->getFileContent($currentFile);
    $backups         = $editor->getBackups($currentFile);
    $fileInfo        = $editor->getFileInfo($currentFile);
} catch (Exception $e) {
    die('Ошибка: ' . htmlspecialchars($e->getMessage()));
}

$cmMode = getCodemirrorMode($currentFile);

stdhead('PHP Editor');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
:root {
    --editor-height: 70vh;
    --card-bg: #ffffff;
    --border-color: #dee2e6;
    --text-color: #212529;
    --primary-color: #0d6efd;
    --hover-bg: #f1f1f1;
}
.CodeMirror { height: var(--editor-height); font-size: 14px; border: 1px solid var(--border-color); border-radius: 4px; }
.CodeMirror-dialog { position: absolute; left: 0; right: 0; background: white; z-index: 15; padding: 5px; border-bottom: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
.CodeMirror-dialog input { border: 1px solid var(--border-color); padding: 5px 10px; width: 80%; outline: none; }
.CodeMirror-dialog button { border: 1px solid var(--border-color); background: var(--primary-color); color: white; padding: 5px 10px; margin-left: 5px; cursor: pointer; }
.backup-item { cursor: pointer; transition: background-color 0.2s; }
.backup-item:hover { background-color: var(--hover-bg); }
.backup-item.active-file { background-color: #e7f3ff !important; font-weight: bold; }
.backup-item .badge { margin-right: 5px; font-size: 0.75rem; }
.tab-content { background-color: var(--card-bg); border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); padding: 15px; }
.file-info-bar { font-size: 12px; background: #f8f9fa; border: 1px solid var(--border-color); border-radius: 4px; padding: 4px 10px; }
.syntax-ok  { color: #198754; }
.syntax-err { color: #dc3545; }
</style>

<div class="container mt-3">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-file-code me-2"></i>PHP Editor</h5>
            <div class="d-flex align-items-center gap-2">
                <form method="get" action="index.php" class="me-2 mb-0">
                    <input type="hidden" name="act" value="phpeditor">
                    <select name="file" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width:300px">
                        <?php foreach (PHP_ALLOWED_FILES as $file): ?>
                        <option value="<?= htmlspecialchars($file) ?>" <?= $file === $currentFile ? 'selected' : '' ?>>
                            <?= htmlspecialchars($file) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Инфо о файле -->
        <div class="px-3 pt-2 pb-1">
            <div class="file-info-bar d-flex gap-3 align-items-center" id="fileInfoBar">
                <span><i class="fas fa-file me-1 text-muted"></i><?= htmlspecialchars($currentFile) ?></span>
                <span><i class="fas fa-weight me-1 text-muted"></i><?= phpFormatSize($fileInfo['size'] ?? 0) ?></span>
                <span><i class="fas fa-list-ol me-1 text-muted"></i><?= ($fileInfo['lines'] ?? 0) ?> lines</span>
                <span><i class="fas fa-clock me-1 text-muted"></i><?= htmlspecialchars($fileInfo['modified'] ?? '') ?></span>
                <span id="syntaxStatus"></span>
                <?php if (!($fileInfo['writable'] ?? false)): ?>
                <span class="text-danger"><i class="fas fa-lock me-1"></i>Read only</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <textarea id="codeEditor" data-file="<?= htmlspecialchars($currentFile) ?>"><?= htmlspecialchars($currentContent) ?></textarea>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="backupCheck" checked>
                        <label class="form-check-label" for="backupCheck">
                            <i class="fas fa-copy me-1"></i>Backup
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="syntaxCheckToggle" checked>
                        <label class="form-check-label" for="syntaxCheckToggle">
                            <i class="fas fa-check-circle me-1"></i>Syntax check
                        </label>
                    </div>
                </div>
                <div class="btn-group btn-group-sm">
                    <button id="findBtn"   class="btn btn-warning"><i class="fas fa-search me-1"></i>Search</button>
                    <button id="formatBtn" class="btn btn-info"><i class="fas fa-indent me-1"></i>Format</button>
                    <button id="saveBtn"   class="btn btn-success"><i class="fas fa-save me-1"></i>Save</button>
                    <button id="reloadBtn" class="btn btn-outline-secondary"><i class="fas fa-sync-alt me-1"></i>Reload</button>
                </div>
            </div>
        </div>

        <!-- Вкладки -->
        <div class="card-footer">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#backups">
                        <i class="fas fa-history me-1"></i>Backups
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stats">
                        <i class="fas fa-chart-bar me-1"></i>Statistics
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#help">
                        <i class="fas fa-question-circle me-1"></i>Help
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Backups -->
                <div class="tab-pane fade show active" id="backups">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Backups for <code><?= htmlspecialchars($currentFile) ?></code></h6>
                        <button id="refreshBackups" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i>Reload
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr><th>Date</th><th>Size</th><th>Action</th></tr>
                            </thead>
                            <tbody id="backupsList">
                            <?php foreach ($backups as $backup):
                                $origName = preg_replace('/\.\d{4}-\d{2}-\d{2}.*\.bak$/', '', $backup['file']);
                                $origName = str_replace('__', '/', $origName);
                            ?>
                            <tr class="backup-item <?= $origName === $currentFile ? 'active-file' : '' ?>"
                                data-file="<?= htmlspecialchars($backup['file']) ?>">
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($origName) ?></span>
                                    <?= htmlspecialchars($backup['date']) ?>
                                </td>
                                <td><?= phpFormatSize($backup['size']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary view-backup" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success restore-backup" title="Restore">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($backups)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No backups yet</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Stats -->
                <div class="tab-pane fade" id="stats">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>File Statistics:</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    Lines <span class="badge bg-primary rounded-pill" id="statsLines">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    Functions <span class="badge bg-primary rounded-pill" id="statsFunctions">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    Classes <span class="badge bg-primary rounded-pill" id="statsClasses">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    Comments <span class="badge bg-secondary rounded-pill" id="statsComments">0</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>File Size:</h6>
                            <div class="progress mb-2" style="height:20px">
                                <div class="progress-bar bg-dark" id="sizeBar" style="width:0%"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small>Current: <strong id="currentSize">0 B</strong></small>
                                <small>Original: <strong id="originalSize"><?= phpFormatSize($fileInfo['size'] ?? 0) ?></strong></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help -->
                <div class="tab-pane fade" id="help">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Keyboard Shortcuts:</h6>
                            <ul class="list-unstyled small">
                                <li><kbd>Ctrl+S</kbd> — Save file</li>
                                <li><kbd>Ctrl+F</kbd> — Search</li>
                                <li><kbd>Ctrl+H</kbd> — Find & Replace</li>
                                <li><kbd>Ctrl+G</kbd> — Go to line</li>
                                <li><kbd>Ctrl+Space</kbd> — Autocomplete</li>
                                <li><kbd>Alt+F</kbd> — Format code</li>
                                <li><kbd>Ctrl+/</kbd> — Toggle comment</li>
                                <li><kbd>Ctrl+Z</kbd> — Undo</li>
                                <li><kbd>Ctrl+Y</kbd> — Redo</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Supported file types:</h6>
                            <ul class="list-unstyled small">
                                <?php foreach (PHP_ALLOWED_EXTENSIONS as $ext): ?>
                                <li><span class="badge bg-dark">.<?= $ext ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                            <h6 class="mt-3">Notes:</h6>
                            <p class="small text-muted">
                                PHP files are syntax-checked before save.
                                Always backup before major edits.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно бэкапа -->
<div class="modal fade" id="backupModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Backup preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="overflow:hidden">
                <div id="backupViewerContainer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="restoreFromModal">Load into editor</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/anyword-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/brace-fold.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/xml-fold.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/comment-fold.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchtags.min.js"></script>

<script>
'use strict';

const AJAX_URL    = (() => {
    const p = new URLSearchParams(window.location.search);
    return window.location.pathname + '?act=' + (p.get('act') || 'phpeditor');
})();
const CURRENT_FILE = document.getElementById('codeEditor').dataset.file;
const CM_MODE      = <?= json_encode($cmMode) ?>;

// ── Редактор ──────────────────────────────────────────────
const editor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
    lineNumbers:       true,
    mode:              CM_MODE,
    theme:             'eclipse',
    indentUnit:        4,
    tabSize:           4,
    indentWithTabs:    false,
    lineWrapping:      false,
    autoCloseBrackets: true,
    matchBrackets:     true,
    matchTags:         true,
    foldGutter:        true,
    gutters:           ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
    extraKeys: {
        'Ctrl-S':       () => saveFile(),
        'Ctrl-F':       'findPersistent',
        'Ctrl-H':       'replace',
        'Ctrl-G':       'findNext',
        'Shift-Ctrl-G': 'findPrev',
        'Ctrl-Space':   'autocomplete',
        'Alt-F':        () => formatCode(),
        'Ctrl-/':       'toggleComment',
    },
    hintOptions: { completeSingle: false },
});

// ── Просмотрщик бэкапов ───────────────────────────────────
let backupViewerCM = null;
function getBackupViewer() {
    if (backupViewerCM) return backupViewerCM;
    backupViewerCM = CodeMirror(document.getElementById('backupViewerContainer'), {
        lineNumbers: true,
        mode:        CM_MODE,
        theme:       'eclipse',
        readOnly:    true,
        value:       '',
    });
    backupViewerCM.setSize('100%', '60vh');
    return backupViewerCM;
}

const backupModal = new bootstrap.Modal(document.getElementById('backupModal'));

// ── Toast ─────────────────────────────────────────────────
function showAlert(type, message, timer = 3000) {
    Swal.fire({ icon: type, title: message, timer, showConfirmButton: false, toast: true, position: 'top-end' });
}

// ── AJAX ──────────────────────────────────────────────────
function ajaxPost(data) {
    return fetch(AJAX_URL, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams({ ajax: '1', file: CURRENT_FILE, ...data }),
    }).then(r => r.json());
}

// ── Сохранение ────────────────────────────────────────────
function saveFile() {
    const content = editor.getValue();
    const backup  = document.getElementById('backupCheck').checked;

    ajaxPost({ action: 'save', content, backup: backup ? '1' : '0' })
        .then(d => {
            if (d.status === 'success') {
                showAlert('success', d.message);
                if (d.info) updateFileInfoBar(d.info);
                updateStats();
            } else {
                showAlert('error', d.message);
            }
        })
        .catch(e => showAlert('error', e.message));
}

// ── Форматирование ────────────────────────────────────────
function formatCode() {
    const cur = editor.getCursor();
    const content = editor.getValue();

    // Простое форматирование отступов
    const lines    = content.split('\n');
    let   indent   = 0;
    const formatted = lines.map(line => {
        const trimmed = line.trim();
        if (!trimmed) return '';
        if (trimmed.match(/^[}\])]/) ) indent = Math.max(0, indent - 1);
        const result = '    '.repeat(indent) + trimmed;
        if (trimmed.match(/[{[(]$/)  ) indent++;
        return result;
    });

    editor.setValue(formatted.join('\n'));
    editor.setCursor(cur);
    showAlert('success', 'Отформатировано');
}

// ── Статистика ────────────────────────────────────────────
function updateStats() {
    const c = editor.getValue();
    const lines     = c.split('\n').length;
    const functions = (c.match(/function\s+\w+\s*\(/g)    || []).length;
    const classes   = (c.match(/\bclass\s+\w+/g)           || []).length;
    const comments  = (c.match(/\/\/.*|\/\*[\s\S]*?\*\//g) || []).length;
    const bytes     = new TextEncoder().encode(c).length;

    document.getElementById('statsLines').textContent     = lines;
    document.getElementById('statsFunctions').textContent = functions;
    document.getElementById('statsClasses').textContent   = classes;
    document.getElementById('statsComments').textContent  = comments;
    document.getElementById('currentSize').textContent    = formatBytes(bytes);

    const origBytes = parseInt(document.getElementById('originalSize').textContent) || bytes;
    const pct       = Math.min(Math.round((bytes / Math.max(origBytes, 1)) * 100), 100);
    document.getElementById('sizeBar').style.width   = pct + '%';
    document.getElementById('sizeBar').textContent   = pct + '%';

    // Подсветка синтаксиса в строке статуса
    if (document.getElementById('syntaxCheckToggle').checked && CM_MODE.includes('php')) {
        checkSyntax(c);
    }
}

function formatBytes(b) {
    if (!b) return '0 B';
    const u = ['B','KB','MB'], i = Math.floor(Math.log(b) / Math.log(1024));
    return (b / 1024**i).toFixed(2) + ' ' + u[i];
}

// Простая клиентская проверка синтаксиса (только базовые ошибки)
function checkSyntax(code) {
    const el     = document.getElementById('syntaxStatus');
    const errors = [];

    // Проверяем баланс скобок
    let braces = 0, parens = 0, brackets = 0;
    let inStr = false, strChar = '', inComment = false;

    for (let i = 0; i < code.length; i++) {
        const ch = code[i];
        if (inComment) {
            if (ch === '*' && code[i+1] === '/') { inComment = false; i++; }
            continue;
        }
        if (!inStr && ch === '/' && code[i+1] === '*') { inComment = true; i++; continue; }
        if (!inStr && ch === '/' && code[i+1] === '/') { while (i < code.length && code[i] !== '\n') i++; continue; }
        if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; continue; }
        if (inStr && ch === strChar && code[i-1] !== '\\') { inStr = false; continue; }
        if (inStr) continue;

        if (ch === '{') braces++;   else if (ch === '}') braces--;
        if (ch === '(') parens++;   else if (ch === ')') parens--;
        if (ch === '[') brackets++; else if (ch === ']') brackets--;
    }

    if (braces   !== 0) errors.push('Unbalanced {}');
    if (parens   !== 0) errors.push('Unbalanced ()');
    if (brackets !== 0) errors.push('Unbalanced []');

    if (errors.length) {
        el.innerHTML = '<span class="syntax-err"><i class="fas fa-times-circle me-1"></i>' + errors.join(', ') + '</span>';
    } else {
        el.innerHTML = '<span class="syntax-ok"><i class="fas fa-check-circle me-1"></i>OK</span>';
    }
}

function updateFileInfoBar(info) {
    if (info.lines)    document.querySelector('.file-info-bar span:nth-child(3)').textContent = '📄 ' + info.lines + ' lines';
    if (info.modified) document.querySelector('.file-info-bar span:nth-child(4)').textContent = '🕐 ' + info.modified;
}

// ── Бэкапы ────────────────────────────────────────────────
document.getElementById('backupsList').addEventListener('click', function (e) {
    const row  = e.target.closest('.backup-item');
    if (!row) return;
    const file = row.dataset.file;
    if (e.target.closest('.view-backup'))    loadBackup(file, false);
    if (e.target.closest('.restore-backup')) loadBackup(file, true);
});

function loadBackup(backupFile, intoEditor = false) {
    ajaxPost({ action: 'get_backup_content', backup_file: backupFile })
        .then(d => {
            if (d.status !== 'success') { showAlert('error', d.message); return; }
            if (intoEditor) {
                editor.setValue(d.content);
                showAlert('success', 'Бэкап загружен в редактор');
                return;
            }
            getBackupViewer().setValue(d.content);
            backupModal.show();
            backupModal._element.addEventListener('shown.bs.modal', () => getBackupViewer().refresh(), { once: true });
        })
        .catch(e => showAlert('error', e.message));
}

document.getElementById('restoreFromModal').addEventListener('click', () => {
    editor.setValue(getBackupViewer().getValue());
    backupModal.hide();
    showAlert('success', 'Содержимое загружено в редактор');
});

document.getElementById('refreshBackups').addEventListener('click', () => {
    ajaxPost({ action: 'get_backups' })
        .then(d => {
            if (d.status !== 'success') { showAlert('error', d.message); return; }
            const tbody = document.getElementById('backupsList');
            if (!d.backups.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No backups yet</td></tr>';
                return;
            }
            const esc = s => s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            tbody.innerHTML = d.backups.map(b => `
                <tr class="backup-item" data-file="${esc(b.file)}">
                    <td><span class="badge bg-secondary">${esc(b.file.replace(/\.\d{4}-\d{2}-\d{2}.*\.bak$/, '').replace(/__/g, '/'))}</span> ${esc(b.date)}</td>
                    <td>${esc(b.size_fmt)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-backup"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm btn-outline-success restore-backup"><i class="fas fa-undo"></i></button>
                    </td>
                </tr>`).join('');
            showAlert('success', 'Список обновлён');
        });
});

// ── Кнопки ────────────────────────────────────────────────
document.getElementById('saveBtn').addEventListener('click',   saveFile);
document.getElementById('formatBtn').addEventListener('click', formatCode);
document.getElementById('findBtn').addEventListener('click',   () => editor.execCommand('findPersistent'));
document.getElementById('reloadBtn').addEventListener('click', () => location.reload());

// ── Изменения в редакторе ─────────────────────────────────
editor.on('change', () => {
    clearTimeout(window._statsTimer);
    window._statsTimer = setTimeout(updateStats, 800);
});

// ── Инициализация ─────────────────────────────────────────
updateStats();
</script>

<?php stdfoot(); ?>
