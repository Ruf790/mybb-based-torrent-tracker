<?php
declare(strict_types=1);

// ── Конфигурация ──────────────────────────────────────────
const BASE_DIR     = TSDIR . '/include/templates/default/style/';
const MAX_BACKUPS  = 10;
const HISTORY_DAYS = 30;

function scanCssFiles(string $dir): array
{
    $files  = glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.{css,scss,less}', GLOB_BRACE) ?: [];
    $result = [];
    foreach ($files as $file) {
        if (str_contains($file, DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $result[] = basename($file);
    }
    sort($result);
    return $result;
}

define('ALLOWED_FILES', scanCssFiles(BASE_DIR));

// ── Класс редактора ───────────────────────────────────────
class CssEditor
{
    private string $baseDir;
    private string $backupDir;
    private array  $allowedFiles;

    public function __construct(string $baseDir, array $allowedFiles)
    {
        $this->baseDir      = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->backupDir    = $this->baseDir . 'backup' . DIRECTORY_SEPARATOR;
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
        $pattern = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . $filename . '.*.bak';
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
        // Защита от path traversal
        $safe = basename($backupFilename);
        if ($safe !== $backupFilename || str_contains($backupFilename, '..')) {
            throw new RuntimeException("Недопустимое имя файла");
        }
        $belongsToAllowed = false;
        foreach ($this->allowedFiles as $allowed) {
            if (str_starts_with($safe, $allowed . '.')) {
                $belongsToAllowed = true;
                break;
            }
        }
        if (!$belongsToAllowed) {
            throw new RuntimeException("Доступ запрещён");
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

    private function createBackup(string $filename): void
    {
        $source     = $this->baseDir . $filename;
        $backupFile = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR
                    . $filename . '.' . date('Y-m-d_His') . '.bak';
        if (!copy($source, $backupFile)) {
            throw new RuntimeException("Не удалось создать бэкап");
        }
        $this->cleanupOldBackups();
    }

    private function cleanupOldBackups(): void
    {
        $pattern = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . '*.bak';
        $files   = glob($pattern) ?: [];
        if (count($files) <= MAX_BACKUPS) return;
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
        $toDelete = count($files) - MAX_BACKUPS;
        for ($i = 0; $i < $toDelete; $i++) {
            unlink($files[$i]);
        }
    }

    private function logHistory(string $filename, string $content): void
    {
        $historyFile = $this->backupDir . 'history' . DIRECTORY_SEPARATOR . $filename . '.log';
        $entry = json_encode([
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
        $cutoff   = strtotime('-' . HISTORY_DAYS . ' days');
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
        if (!in_array($filename, $this->allowedFiles, true)) {
            throw new RuntimeException("Доступ запрещён: {$filename}");
        }
    }
}

function formatSize(int $bytes): string
{
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i     = (int)floor(log($bytes) / log(1024));
    return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
}

// ── Инициализация ─────────────────────────────────────────
$editor      = new CssEditor(BASE_DIR, ALLOWED_FILES);
$allowedList = ALLOWED_FILES;
$currentFile = $_GET['file'] ?? ($allowedList[0] ?? '');
if (!in_array($currentFile, $allowedList, true)) {
    $currentFile = $allowedList[0] ?? '';
}

// ── AJAX ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $action   = $_POST['action']   ?? '';
        $filename = basename($_POST['file'] ?? '');
        switch ($action) {
            case 'save':
                $content    = $_POST['content'] ?? '';
                $makeBackup = ($_POST['backup'] ?? '0') === '1';
                $editor->saveFile($filename, $content, $makeBackup);
                echo json_encode([
                    'status'  => 'success',
                    'message' => $makeBackup ? 'Файл сохранён с бэкапом!' : 'Файл сохранён!',
                ]);
                break;
            case 'get_backups':
                $backups = $editor->getBackups($filename);
                foreach ($backups as &$b) {
                    $b['size_fmt'] = formatSize($b['size']);
                }
                echo json_encode(['status' => 'success', 'backups' => $backups]);
                break;
            case 'get_backup_content':
                $content = $editor->getBackupContent($_POST['backup_file'] ?? '');
                echo json_encode(['status' => 'success', 'content' => $content]);
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
    $currentContent = $editor->getFileContent($currentFile);
    $backups        = $editor->getBackups($currentFile);
} catch (Exception $e) {
    die('Ошибка: ' . htmlspecialchars($e->getMessage()));
}

stdhead('CSS Editor');
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
#previewContainer { border: 1px solid var(--border-color); padding: 15px; margin-top: 20px; background-color: var(--card-bg); display: none; border-radius: 4px; }
.preview-box { min-height: 300px; padding: 15px; background: white; border-radius: 4px; overflow: auto; }
.preview-section { margin-bottom: 20px; padding-bottom: 15px; }
.preview-section:not(:last-child) { border-bottom: 1px solid #eee; }
.preview-box .btn { margin-right: 8px; margin-bottom: 8px; }
.preview-box .alert { margin-bottom: 15px; }
.preview-box .card { margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.backup-item { cursor: pointer; transition: background-color 0.2s; }
.backup-item:hover { background-color: var(--hover-bg); }
.template-btn { margin-right: 5px; margin-bottom: 5px; border: 1px solid var(--border-color); }
.tab-content { background-color: var(--card-bg); border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); padding: 15px; }
.backup-item.active-file { background-color: #e7f3ff !important; font-weight: bold; }
.backup-item .badge { margin-right: 5px; font-size: 0.75rem; }
</style>

<div class="container mt-3">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-code me-2"></i>Advanced CSS Editor</h5>
            <div class="d-flex align-items-center">
                <form method="get" action="index.php" class="me-3">
                    <input type="hidden" name="act" value="csseditor">
                    <select name="file" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach (ALLOWED_FILES as $file): ?>
                            <option value="<?= htmlspecialchars($file) ?>" <?= $file === $currentFile ? 'selected' : '' ?>>
                                <?= htmlspecialchars($file) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="livePreviewToggle">
                    <label class="form-check-label" for="livePreviewToggle">Preview</label>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="mb-3">
                <?php
                $snippetBtns = [
                    'reset'       => ['icon' => 'fa-eraser',        'label' => 'Reset'],
                    'flex-center' => ['icon' => 'fa-align-center',  'label' => 'Flex Center'],
                    'grid-layout' => ['icon' => 'fa-th',            'label' => 'Grid Layout'],
                    'media-query' => ['icon' => 'fa-mobile-screen', 'label' => 'Media Query'],
                    'animation'   => ['icon' => 'fa-film',          'label' => 'Animation'],
                    'transition'  => ['icon' => 'fa-exchange-alt',  'label' => 'Transition'],
                    'transform'   => ['icon' => 'fa-arrows-alt',    'label' => 'Transform'],
                    'shadow'      => ['icon' => 'fa-box',           'label' => 'Shadow'],
                ];
                foreach ($snippetBtns as $key => $btn):
                ?>
                <button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="<?= $key ?>">
                    <i class="fas <?= $btn['icon'] ?> me-1"></i><?= $btn['label'] ?>
                </button>
                <?php endforeach; ?>
            </div>

            <textarea id="codeEditor"><?= htmlspecialchars($currentContent) ?></textarea>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="d-flex">
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" id="backupCheck" checked>
                        <label class="form-check-label" for="backupCheck">
                            <i class="fas fa-copy me-1"></i>BackUp
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="minifyCheck">
                        <label class="form-check-label" for="minifyCheck">
                            <i class="fas fa-compress-alt me-1"></i>Minify
                        </label>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="findBtn"   class="btn btn-warning me-2"><i class="fas fa-search me-1"></i>Search</button>
                    <button id="formatBtn" class="btn btn-info me-2"><i class="fas fa-indent me-1"></i>Format</button>
                    <button id="saveBtn"   class="btn btn-success me-2"><i class="fas fa-save me-1"></i>Save</button>
                    <button id="reloadBtn" class="btn btn-primary"><i class="fas fa-sync-alt me-1"></i>Reload</button>
                </div>
            </div>

            <div id="previewContainer" class="mt-4">
                <h5><i class="fas fa-eye me-2"></i>Preview Styles</h5>
                <div class="border p-3 mb-3 bg-white rounded">
                    <div class="preview-box">
                        <div class="preview-section mb-4">
                            <h6 class="border-bottom pb-2">Basic Elements</h6>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button class="btn btn-primary">Primary</button>
                                <button class="btn btn-secondary">Secondary</button>
                                <button class="btn btn-success">Success</button>
                            </div>
                            <div class="alert alert-warning mb-3">Warning message example</div>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">Card Example</h5>
                                    <p class="card-text">Sample text inside card.</p>
                                    <a href="#" class="btn btn-primary">Card Button</a>
                                </div>
                            </div>
                        </div>
                        <div class="preview-section">
                            <h6 class="border-bottom pb-2">Typography</h6>
                            <h1>Heading h1</h1>
                            <h2>Heading h2</h2>
                            <p>Regular paragraph with <strong>bold</strong> and <em>italic</em>.</p>
                            <ul class="mb-3"><li>List item 1</li><li>List item 2</li></ul>
                        </div>
                    </div>
                </div>
                <button id="refreshPreview" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt me-1"></i>Refresh Preview
                </button>
            </div>
        </div>

        <div class="card-footer">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#backups">
                        <i class="fas fa-history me-1"></i>BackUps
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
                <div class="tab-pane fade show active" id="backups">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">BackUps:</h6>
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
                            ?>
                            <tr class="backup-item <?= $origName === $currentFile ? 'active-file' : '' ?>"
                                data-file="<?= htmlspecialchars($backup['file']) ?>">
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($origName) ?></span>
                                    <?= htmlspecialchars($backup['date']) ?>
                                </td>
                                <td><?= formatSize($backup['size']) ?></td>
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

                <div class="tab-pane fade" id="stats">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>CSS Statistics:</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Selectors <span class="badge bg-primary rounded-pill" id="statsSelectors">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Properties <span class="badge bg-primary rounded-pill" id="statsProperties">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Media Queries <span class="badge bg-primary rounded-pill" id="statsMediaQueries">0</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>File Size:</h6>
                            <div class="progress mb-3" style="height:20px">
                                <div class="progress-bar" id="sizeBar" style="width:0%"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small>Minified: <span id="minSize">0 KB</span></small>
                                <small>Original: <span id="normalSize">0 KB</span></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="help">
                    <h6>Keyboard Shortcuts:</h6>
                    <ul class="list-unstyled">
                        <li><kbd>Ctrl+F</kbd> — Search</li>
                        <li><kbd>Ctrl+H</kbd> — Replace</li>
                        <li><kbd>Ctrl+S</kbd> — Save</li>
                        <li><kbd>Ctrl+Space</kbd> — Autocomplete</li>
                        <li><kbd>Alt+F</kbd> — Format</li>
                        <li><kbd>Ctrl+/</kbd> — Toggle comment</li>
                    </ul>
                    <h6 class="mt-3">Templates:</h6>
                    <p>Use the buttons above to insert common CSS patterns.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно просмотра бэкапа -->
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/css-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/brace-fold.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/comment-fold.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.7/beautify-css.min.js"></script>

<script>
const AJAX_URL   = (() => {
    const p = new URLSearchParams(window.location.search);
    return window.location.pathname + '?act=' + (p.get('act') || 'csseditor');
})();
const CURRENT_FILE = <?= json_encode($currentFile) ?>;

// ── Редактор ──────────────────────────────────────────────
const editor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
    lineNumbers: true, mode: 'text/css', theme: 'eclipse',
    indentUnit: 4, tabSize: 4, lineWrapping: true,
    autoCloseBrackets: true, matchBrackets: true,
    foldGutter: true, gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
    extraKeys: {
        'Ctrl-S': saveFile, 'Ctrl-F': 'findPersistent', 'Ctrl-H': 'replace',
        'Ctrl-G': 'findNext', 'Shift-Ctrl-G': 'findPrev',
        'Ctrl-Space': 'autocomplete', 'Alt-F': formatCode, 'Ctrl-/': 'toggleComment',
    },
    hintOptions: { completeSingle: false, hint: CodeMirror.hint.css },
});

// ── Просмотрщик бэкапов — ленивая инициализация ──────────
let backupViewerCM = null;
function getBackupViewer() {
    if (backupViewerCM) return backupViewerCM;
    backupViewerCM = CodeMirror(document.getElementById('backupViewerContainer'), {
        lineNumbers: true, mode: 'text/css', theme: 'eclipse', readOnly: true, value: '',
    });
    backupViewerCM.setSize('100%', '60vh');
    return backupViewerCM;
}

const backupModal = new bootstrap.Modal(document.getElementById('backupModal'));

// ── Toast ─────────────────────────────────────────────────
function showAlert(type, message, timer = 3000) {
    Swal.fire({ icon: type, title: message, timer, showConfirmButton: false, toast: true, position: 'top-end' });
}

// ── AJAX helper ───────────────────────────────────────────
function ajaxPost(data) {
    return fetch(AJAX_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ ajax: '1', file: CURRENT_FILE, ...data }),
    }).then(r => r.json());
}

// ── Сохранение ────────────────────────────────────────────
function saveFile() {
    let content  = editor.getValue();
    const backup = document.getElementById('backupCheck').checked;
    const minify = document.getElementById('minifyCheck').checked;
    if (minify) {
        content = content
            .replace(/\/\*[\s\S]*?\*\//g, '')
            .replace(/\s+/g, ' ')
            .replace(/\s?([\{\}:;,])\s?/g, '$1')
            .replace(/;\}/g, '}').trim();
    }
    ajaxPost({ action: 'save', content, backup: backup ? '1' : '0' })
        .then(d => { d.status === 'success' ? showAlert('success', d.message) : showAlert('error', d.message); updateStats(); })
        .catch(e => showAlert('error', e.message));
}

// ── Форматирование ────────────────────────────────────────
function formatCode() {
    editor.setValue(css_beautify(editor.getValue(), {
        indent_size: 4, selector_separator_newline: true,
        end_with_newline: true, newline_between_rules: true,
    }));
    showAlert('success', 'Код отформатирован');
}

// ── Статистика ────────────────────────────────────────────
function updateStats() {
    const c = editor.getValue();
    document.getElementById('statsSelectors').textContent    = (c.match(/[^{]+\{/g)      || []).length;
    document.getElementById('statsProperties').textContent   = (c.match(/[^:]+:[^;]+;/g)  || []).length;
    document.getElementById('statsMediaQueries').textContent = (c.match(/@media[^{]+\{/g) || []).length;
    const normal  = c.length;
    const mini    = c.replace(/\/\*[\s\S]*?\*\//g,'').replace(/\s+/g,' ').replace(/\s?([\{\}:;,])\s?/g,'$1').replace(/;\}/g,'}').length;
    document.getElementById('normalSize').textContent = formatFileSize(normal);
    document.getElementById('minSize').textContent    = formatFileSize(mini);
    const pct = Math.round((mini / Math.max(normal, 1)) * 100);
    document.getElementById('sizeBar').style.width    = pct + '%';
    document.getElementById('sizeBar').textContent    = pct + '%';
}

function formatFileSize(b) {
    if (!b) return '0 B';
    const u = ['B','KB','MB'], i = Math.floor(Math.log(b) / Math.log(1024));
    return (b / 1024**i).toFixed(2) + ' ' + u[i];
}

// ── Бэкапы — делегирование событий ───────────────────────
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
            if (intoEditor) { editor.setValue(d.content); showAlert('success', 'Бэкап загружен в редактор'); return; }
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
                    <td><span class="badge bg-secondary">${esc(b.file.replace(/\.\d{4}-\d{2}-\d{2}.*\.bak$/, ''))}</span> ${esc(b.date)}</td>
                    <td>${esc(b.size_fmt)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-backup"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm btn-outline-success restore-backup"><i class="fas fa-undo"></i></button>
                    </td>
                </tr>`).join('');
            showAlert('success', 'Список обновлён');
        });
});

// ── Preview ───────────────────────────────────────────────
document.getElementById('livePreviewToggle').addEventListener('change', function () {
    const wrap = document.getElementById('previewContainer');
    wrap.style.display = this.checked ? 'block' : 'none';
    if (this.checked) applyPreview();
    else document.getElementById('livePreviewStyle')?.remove();
});
document.getElementById('refreshPreview').addEventListener('click', applyPreview);

function applyPreview() {
    document.getElementById('livePreviewStyle')?.remove();
    const css = editor.getValue().trim();
    if (!css) { showAlert('info', 'Редактор пуст'); return; }
    const s = document.createElement('style');
    s.id = 'livePreviewStyle';
    s.textContent = css;
    document.head.appendChild(s);
    showAlert('success', 'Стили применены', 1500);
}

editor.on('change', () => {
    if (document.getElementById('livePreviewToggle').checked) {
        clearTimeout(window._previewTimer);
        window._previewTimer = setTimeout(applyPreview, 500);
    }
    clearTimeout(window._statsTimer);
    window._statsTimer = setTimeout(updateStats, 800);
});

// ── Сниппеты ─────────────────────────────────────────────
const SNIPPETS = {
    'reset':       `* {\n    margin: 0;\n    padding: 0;\n    box-sizing: border-box;\n}`,
    'flex-center': `.centered {\n    display: flex;\n    justify-content: center;\n    align-items: center;\n}`,
    'grid-layout': `.grid-container {\n    display: grid;\n    grid-template-columns: repeat(3, 1fr);\n    gap: 20px;\n}`,
    'media-query': `@media (min-width: 768px) {\n    \n}`,
    'animation':   `@keyframes fadeIn {\n    from { opacity: 0; }\n    to { opacity: 1; }\n}\n\n.animated {\n    animation: fadeIn 0.5s ease-in-out;\n}`,
    'transition':  `.element {\n    transition: all 0.3s ease;\n    will-change: transform, opacity;\n}`,
    'transform':   `.element {\n    transform: translateX(10px) rotate(5deg);\n    transform-origin: center center;\n}`,
    'shadow':      `.shadow-soft {\n    box-shadow: 0 2px 8px rgba(0,0,0,0.1);\n}\n\n.shadow-medium {\n    box-shadow: 0 4px 12px rgba(0,0,0,0.15);\n}`,
};

document.querySelectorAll('.template-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const snippet = SNIPPETS[btn.dataset.snippet];
        if (!snippet) return;
        const cursor = editor.getCursor();
        const prefix = (cursor.ch > 0 || cursor.line > 0) ? '\n\n' : '';
        editor.replaceRange(prefix + snippet, cursor);
        editor.scrollIntoView(cursor);
        showAlert('success', 'Сниппет добавлен', 1500);
    });
});

// ── Кнопки ───────────────────────────────────────────────
document.getElementById('saveBtn').addEventListener('click',   saveFile);
document.getElementById('formatBtn').addEventListener('click', formatCode);
document.getElementById('findBtn').addEventListener('click',   () => editor.execCommand('findPersistent'));
document.getElementById('reloadBtn').addEventListener('click', () => location.reload());

// ── Инициализация ─────────────────────────────────────────
updateStats();
</script>

<?php stdfoot(); ?>