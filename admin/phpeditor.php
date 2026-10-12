<?php
declare(strict_types=1);


if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

$lang->load('phpeditor');

// {1}, {2}… (и %1$s, в которые их превращает $lang->load()) → аргументы
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
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
        global $lang;
        $path = $this->baseDir . $filename;
        if (!file_exists($path)) {
            throw new RuntimeException(ags_fmt($lang->phpeditor['err_file_not_found'], $filename));
        }
        return file_get_contents($path) ?: '';
    }

    public function saveFile(string $filename, string $content, bool $makeBackup = true): void
    {
        global $lang;
        $this->assertAllowed($filename);
        $path = $this->baseDir . $filename;

        if (!is_writable($path)) {
            throw new RuntimeException(ags_fmt($lang->phpeditor['err_not_writable'], $filename));
        }
        if (trim($content) === '') {
            throw new RuntimeException($lang->phpeditor['err_empty_content']);
        }

        // Проверка синтаксиса PHP
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'phpcheck_');
            file_put_contents($tmpFile, $content);
            exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $code);
            unlink($tmpFile);
            if ($code !== 0) {
                throw new RuntimeException(ags_fmt($lang->phpeditor['err_php_syntax'], implode(' ', $output)));
            }
        }

        if ($makeBackup) {
            $this->createBackup($filename);
        }
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException($lang->phpeditor['err_save_failed']);
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
        global $lang;
        $safe = basename($backupFilename);
        if ($safe !== $backupFilename || str_contains($backupFilename, '..')) {
            throw new RuntimeException($lang->phpeditor['err_invalid_filename']);
        }
        $path = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . $safe;
        if (!file_exists($path)) {
            throw new RuntimeException($lang->phpeditor['err_backup_not_found']);
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException($lang->phpeditor['err_backup_read_failed']);
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
        global $lang;
        $source  = $this->baseDir . $filename;
        $safe    = str_replace(['/', '\\'], '__', $filename);
        $bakFile = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR
                 . $safe . '.' . date('Y-m-d_His') . '.bak';
        if (!copy($source, $bakFile)) {
            throw new RuntimeException($lang->phpeditor['err_backup_failed']);
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
        global $lang;
        // Защита от path traversal
        if (str_contains($filename, '..') || str_contains($filename, "\0")) {
            throw new RuntimeException($lang->phpeditor['err_invalid_filename']);
        }
        if (!in_array($filename, $this->allowedFiles, true)) {
            throw new RuntimeException(ags_fmt($lang->phpeditor['err_access_denied'], $filename));
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

    if (!verify_post_check($mybb->get_input('my_post_key'))) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => $lang->phpeditor['err_invalid_token']], JSON_UNESCAPED_UNICODE);
        exit;
    }

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
                    'message' => $makeBackup ? $lang->phpeditor['flash_saved_backup'] : $lang->phpeditor['flash_saved'],
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
                throw new RuntimeException(ags_fmt($lang->phpeditor['err_unknown_action'], $action));
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
    die(htmlspecialchars(ags_fmt($lang->phpeditor['err_page'], $e->getMessage())));
}

$cmMode = getCodemirrorMode($currentFile);

// js_* → массив без префикса для AGS_LANG
$jsLang = [];
foreach ($lang->phpeditor as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = $v;
    }
}

global $BASEURL;
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

stdhead($lang->phpeditor['page_title']);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<link rel="stylesheet" href="<?= $h((string)$BASEURL) ?>/admin/templates/phpeditor.css?ver=22">

<div class="phpeditor-page">
<div class="container mt-3">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-file-code me-2"></i><?= $h($lang->phpeditor['hdr_title']) ?></h5>
            <div class="d-flex align-items-center gap-2">
                <form method="get" action="index.php" class="me-2 mb-0">
                    <input type="hidden" name="act" value="phpeditor">
                    <select name="file" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width:300px" aria-label="<?= $h($lang->phpeditor['aria_select_file']) ?>">
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
                <span><i class="fas fa-list-ol me-1 text-muted"></i><?= $h(ags_fmt($lang->phpeditor['lbl_info_lines'], (int)($fileInfo['lines'] ?? 0))) ?></span>
                <span><i class="fas fa-clock me-1 text-muted"></i><?= htmlspecialchars($fileInfo['modified'] ?? '') ?></span>
                <span id="syntaxStatus"></span>
                <?php if (!($fileInfo['writable'] ?? false)): ?>
                <span class="text-danger"><i class="fas fa-lock me-1"></i><?= $h($lang->phpeditor['lbl_read_only']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <input type="hidden" id="myPostKey" value="<?= htmlspecialchars($mybb->post_code) ?>">
            <textarea id="codeEditor" data-file="<?= htmlspecialchars($currentFile) ?>" data-cm-mode="<?= $h($cmMode) ?>"><?= htmlspecialchars($currentContent) ?></textarea>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="backupCheck" checked>
                        <label class="form-check-label" for="backupCheck">
                            <i class="fas fa-copy me-1"></i><?= $h($lang->phpeditor['lbl_backup']) ?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="syntaxCheckToggle" checked>
                        <label class="form-check-label" for="syntaxCheckToggle">
                            <i class="fas fa-check-circle me-1"></i><?= $h($lang->phpeditor['lbl_syntax_check']) ?>
                        </label>
                    </div>
                </div>
                <div class="btn-group btn-group-sm">
                    <button id="findBtn"   class="btn btn-warning"><i class="fas fa-search me-1"></i><?= $h($lang->phpeditor['btn_search']) ?></button>
                    <button id="formatBtn" class="btn btn-info"><i class="fas fa-indent me-1"></i><?= $h($lang->phpeditor['btn_format']) ?></button>
                    <button id="saveBtn"   class="btn btn-success"><i class="fas fa-save me-1"></i><?= $h($lang->phpeditor['btn_save']) ?></button>
                    <button id="reloadBtn" class="btn btn-outline-secondary"><i class="fas fa-sync-alt me-1"></i><?= $h($lang->phpeditor['btn_reload']) ?></button>
                </div>
            </div>
        </div>

        <!-- Вкладки -->
        <div class="card-footer">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#backups">
                        <i class="fas fa-history me-1"></i><?= $h($lang->phpeditor['tab_backups']) ?>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stats">
                        <i class="fas fa-chart-bar me-1"></i><?= $h($lang->phpeditor['tab_stats']) ?>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#help">
                        <i class="fas fa-question-circle me-1"></i><?= $h($lang->phpeditor['tab_help']) ?>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Backups -->
                <div class="tab-pane fade show active" id="backups">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><?= ags_fmt($h($lang->phpeditor['sec_backups_for']), '<code>' . $h($currentFile) . '</code>') ?></h6>
                        <button id="refreshBackups" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i><?= $h($lang->phpeditor['btn_refresh_backups']) ?>
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr><th><?= $h($lang->phpeditor['th_date']) ?></th><th><?= $h($lang->phpeditor['th_size']) ?></th><th><?= $h($lang->phpeditor['th_action']) ?></th></tr>
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
                                    <button class="btn btn-sm btn-outline-primary view-backup" title="<?= $h($lang->phpeditor['title_view']) ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success restore-backup" title="<?= $h($lang->phpeditor['title_restore']) ?>">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($backups)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3"><?= $h($lang->phpeditor['lbl_no_backups']) ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Stats -->
                <div class="tab-pane fade" id="stats">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?= $h($lang->phpeditor['sec_file_stats']) ?></h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <?= $h($lang->phpeditor['lbl_lines']) ?> <span class="badge bg-primary rounded-pill" id="statsLines">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <?= $h($lang->phpeditor['lbl_functions']) ?> <span class="badge bg-primary rounded-pill" id="statsFunctions">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <?= $h($lang->phpeditor['lbl_classes']) ?> <span class="badge bg-primary rounded-pill" id="statsClasses">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <?= $h($lang->phpeditor['lbl_comments']) ?> <span class="badge bg-secondary rounded-pill" id="statsComments">0</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><?= $h($lang->phpeditor['sec_file_size']) ?></h6>
                            <div class="progress mb-2" style="height:20px">
                                <div class="progress-bar bg-dark" id="sizeBar" style="width:0%"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small><?= $h($lang->phpeditor['lbl_current_size']) ?> <strong id="currentSize">0 B</strong></small>
                                <small><?= $h($lang->phpeditor['lbl_original_size']) ?> <strong id="originalSize"><?= phpFormatSize($fileInfo['size'] ?? 0) ?></strong></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help -->
                <div class="tab-pane fade" id="help">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?= $h($lang->phpeditor['sec_shortcuts']) ?></h6>
                            <ul class="list-unstyled small">
                                <li><kbd>Ctrl+S</kbd> — <?= $h($lang->phpeditor['hint_key_save']) ?></li>
                                <li><kbd>Ctrl+F</kbd> — <?= $h($lang->phpeditor['hint_key_search']) ?></li>
                                <li><kbd>Ctrl+H</kbd> — <?= $h($lang->phpeditor['hint_key_replace']) ?></li>
                                <li><kbd>Ctrl+G</kbd> — <?= $h($lang->phpeditor['hint_key_goto']) ?></li>
                                <li><kbd>Ctrl+Space</kbd> — <?= $h($lang->phpeditor['hint_key_autocomplete']) ?></li>
                                <li><kbd>Alt+F</kbd> — <?= $h($lang->phpeditor['hint_key_format']) ?></li>
                                <li><kbd>Ctrl+/</kbd> — <?= $h($lang->phpeditor['hint_key_comment']) ?></li>
                                <li><kbd>Ctrl+Z</kbd> — <?= $h($lang->phpeditor['hint_key_undo']) ?></li>
                                <li><kbd>Ctrl+Y</kbd> — <?= $h($lang->phpeditor['hint_key_redo']) ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><?= $h($lang->phpeditor['sec_file_types']) ?></h6>
                            <ul class="list-unstyled small">
                                <?php foreach (PHP_ALLOWED_EXTENSIONS as $ext): ?>
                                <li><span class="badge bg-dark">.<?= $ext ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                            <h6 class="mt-3"><?= $h($lang->phpeditor['sec_notes']) ?></h6>
                            <p class="small text-muted">
                                <?= $h($lang->phpeditor['hint_notes']) ?>
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
                <h5 class="modal-title"><?= $h($lang->phpeditor['modal_backup_title']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $h($lang->phpeditor['btn_close']) ?>"></button>
            </div>
            <div class="modal-body p-0" style="overflow:hidden">
                <div id="backupViewerContainer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $h($lang->phpeditor['btn_close']) ?></button>
                <button type="button" class="btn btn-primary" id="restoreFromModal"><?= $h($lang->phpeditor['btn_load_into_editor']) ?></button>
            </div>
        </div>
    </div>
</div>
</div><!-- /.phpeditor-page -->

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
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $h((string)$BASEURL) ?>/admin/scripts/phpeditor.js?ver=1"></script>

<?php stdfoot(); ?>