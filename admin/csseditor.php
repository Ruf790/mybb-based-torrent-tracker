<?php
declare(strict_types=1);

// Раньше здесь не было вообще никакой проверки прав - файл позволяет
// редактировать CSS-шаблоны сайта на диске (запись файлов), а без этой
// защиты был бы доступен кому угодно при прямом обращении по URL.
if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

$lang->load('csseditor');

// ── Хелперы ланга ─────────────────────────────────────────
// $lang->load() превращает {1} в %1$s, поэтому подставляем оба формата.
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

function csse_lang(string $key, string|int|float ...$args): string
{
    global $lang;
    $str = $lang->csseditor[$key];
    return $args ? ags_fmt($str, ...$args) : $str;
}

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
            throw new RuntimeException(csse_lang('err_file_not_found', $filename));
        }
        return file_get_contents($path) ?: '';
    }

    public function saveFile(string $filename, string $content, bool $makeBackup = true): void
    {
        $this->assertAllowed($filename);
        $path = $this->baseDir . $filename;

        if (!is_writable($path)) {
            throw new RuntimeException(csse_lang('err_not_writable', $filename));
        }
        if (trim($content) === '') {
            throw new RuntimeException(csse_lang('err_empty_content'));
        }
        if ($makeBackup) {
            $this->createBackup($filename);
        }
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(csse_lang('err_save_failed'));
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
            throw new RuntimeException(csse_lang('err_invalid_filename'));
        }
        $belongsToAllowed = false;
        foreach ($this->allowedFiles as $allowed) {
            if (str_starts_with($safe, $allowed . '.')) {
                $belongsToAllowed = true;
                break;
            }
        }
        if (!$belongsToAllowed) {
            throw new RuntimeException(csse_lang('err_access_denied'));
        }
        $path = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . $safe;
        if (!file_exists($path)) {
            throw new RuntimeException(csse_lang('err_backup_not_found'));
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(csse_lang('err_backup_read_failed'));
        }
        return $content;
    }

    private function createBackup(string $filename): void
    {
        $source     = $this->baseDir . $filename;
        $backupFile = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR
                    . $filename . '.' . date('Y-m-d_His') . '.bak';
        if (!copy($source, $backupFile)) {
            throw new RuntimeException(csse_lang('err_backup_create_failed'));
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

    // Служебный журнал на диске — не переводится
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
            throw new RuntimeException(csse_lang('err_access_denied_file', $filename));
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

    if (!verify_post_check($mybb->get_input('my_post_key'))) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => csse_lang('err_invalid_token')]);
        exit;
    }

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
                    'message' => $makeBackup ? csse_lang('flash_saved_backup') : csse_lang('flash_saved'),
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
                throw new RuntimeException(csse_lang('err_unknown_action', (string)$action));
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
    die(htmlspecialchars(csse_lang('err_page', $e->getMessage())));
}

// ── Строки для JS (js_* без префикса) ─────────────────────
$jsLang = [];
foreach ($lang->csseditor as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = $v;
    }
}

$L = $lang->csseditor;
$h = static fn(string $key): string => htmlspecialchars($L[$key]);

stdhead($L['page_title']);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/csseditor.css?ver=1">

<div class="container mt-3">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-code me-2"></i><?= $h('sec_editor') ?></h5>
            <div class="d-flex align-items-center">
                <form method="get" action="index.php" class="me-3">
                    <input type="hidden" name="act" value="csseditor">
                    <select name="file" class="form-select form-select-sm" onchange="this.form.submit()"
                            aria-label="<?= $h('lbl_file_select') ?>" title="<?= $h('lbl_file_select') ?>">
                        <?php foreach (ALLOWED_FILES as $file): ?>
                            <option value="<?= htmlspecialchars($file) ?>" <?= $file === $currentFile ? 'selected' : '' ?>>
                                <?= htmlspecialchars($file) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="livePreviewToggle">
                    <label class="form-check-label" for="livePreviewToggle"><?= $h('lbl_preview_toggle') ?></label>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="mb-3">
                <?php
                $snippetBtns = [
                    'reset'       => ['icon' => 'fa-eraser',        'label' => $L['opt_snip_reset']],
                    'flex-center' => ['icon' => 'fa-align-center',  'label' => $L['opt_snip_flex_center']],
                    'grid-layout' => ['icon' => 'fa-th',            'label' => $L['opt_snip_grid_layout']],
                    'media-query' => ['icon' => 'fa-mobile-screen', 'label' => $L['opt_snip_media_query']],
                    'animation'   => ['icon' => 'fa-film',          'label' => $L['opt_snip_animation']],
                    'transition'  => ['icon' => 'fa-exchange-alt',  'label' => $L['opt_snip_transition']],
                    'transform'   => ['icon' => 'fa-arrows-alt',    'label' => $L['opt_snip_transform']],
                    'shadow'      => ['icon' => 'fa-box',           'label' => $L['opt_snip_shadow']],
                ];
                foreach ($snippetBtns as $key => $btn):
                ?>
                <button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="<?= $key ?>">
                    <i class="fas <?= $btn['icon'] ?> me-1"></i><?= htmlspecialchars($btn['label']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <input type="hidden" id="myPostKey" value="<?= htmlspecialchars($mybb->post_code) ?>">
            <textarea id="codeEditor"><?= htmlspecialchars($currentContent) ?></textarea>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="d-flex">
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" id="backupCheck" checked>
                        <label class="form-check-label" for="backupCheck">
                            <i class="fas fa-copy me-1"></i><?= $h('lbl_backup') ?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="minifyCheck">
                        <label class="form-check-label" for="minifyCheck">
                            <i class="fas fa-compress-alt me-1"></i><?= $h('lbl_minify') ?>
                        </label>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="findBtn"   class="btn btn-warning me-2"><i class="fas fa-search me-1"></i><?= $h('btn_search') ?></button>
                    <button id="formatBtn" class="btn btn-info me-2"><i class="fas fa-indent me-1"></i><?= $h('btn_format') ?></button>
                    <button id="saveBtn"   class="btn btn-success me-2"><i class="fas fa-save me-1"></i><?= $h('btn_save') ?></button>
                    <button id="reloadBtn" class="btn btn-primary"><i class="fas fa-sync-alt me-1"></i><?= $h('btn_reload') ?></button>
                </div>
            </div>

            <div id="previewContainer" class="mt-4">
                <h5><i class="fas fa-eye me-2"></i><?= $h('sec_preview') ?></h5>
                <div class="border p-3 mb-3 bg-white rounded">
                    <div class="preview-box">
                        <div class="preview-section mb-4">
                            <h6 class="border-bottom pb-2"><?= $h('sec_preview_basic') ?></h6>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button class="btn btn-primary"><?= $h('lbl_demo_primary') ?></button>
                                <button class="btn btn-secondary"><?= $h('lbl_demo_secondary') ?></button>
                                <button class="btn btn-success"><?= $h('lbl_demo_success') ?></button>
                            </div>
                            <div class="alert alert-warning mb-3"><?= $h('lbl_demo_warning') ?></div>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><?= $h('lbl_demo_card_title') ?></h5>
                                    <p class="card-text"><?= $h('lbl_demo_card_text') ?></p>
                                    <a href="#" class="btn btn-primary"><?= $h('lbl_demo_card_btn') ?></a>
                                </div>
                            </div>
                        </div>
                        <div class="preview-section">
                            <h6 class="border-bottom pb-2"><?= $h('sec_preview_typography') ?></h6>
                            <h1><?= $h('lbl_demo_h1') ?></h1>
                            <h2><?= $h('lbl_demo_h2') ?></h2>
                            <p><?= $L['lbl_demo_paragraph'] ?></p>
                            <ul class="mb-3"><li><?= $h('lbl_demo_list_1') ?></li><li><?= $h('lbl_demo_list_2') ?></li></ul>
                        </div>
                    </div>
                </div>
                <button id="refreshPreview" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt me-1"></i><?= $h('btn_refresh_preview') ?>
                </button>
            </div>
        </div>

        <div class="card-footer">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#backups">
                        <i class="fas fa-history me-1"></i><?= $h('pane_backups') ?>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stats">
                        <i class="fas fa-chart-bar me-1"></i><?= $h('pane_stats') ?>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#help">
                        <i class="fas fa-question-circle me-1"></i><?= $h('pane_help') ?>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="backups">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><?= $h('sec_backups') ?></h6>
                        <button id="refreshBackups" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i><?= $h('btn_refresh_backups') ?>
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr><th><?= $h('th_date') ?></th><th><?= $h('th_size') ?></th><th><?= $h('th_action') ?></th></tr>
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
                                    <button class="btn btn-sm btn-outline-primary view-backup" title="<?= $h('tip_view') ?>" aria-label="<?= $h('tip_view') ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success restore-backup" title="<?= $h('tip_restore') ?>" aria-label="<?= $h('tip_restore') ?>">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($backups)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3"><?= $h('lbl_no_backups') ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="stats">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?= $h('sec_css_stats') ?></h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= $h('lbl_selectors') ?> <span class="badge bg-primary rounded-pill" id="statsSelectors">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= $h('lbl_properties') ?> <span class="badge bg-primary rounded-pill" id="statsProperties">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= $h('lbl_media_queries') ?> <span class="badge bg-primary rounded-pill" id="statsMediaQueries">0</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><?= $h('sec_file_size') ?></h6>
                            <div class="progress mb-3" style="height:20px">
                                <div class="progress-bar" id="sizeBar" style="width:0%"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small><?= $h('lbl_minified') ?> <span id="minSize">0 KB</span></small>
                                <small><?= $h('lbl_original') ?> <span id="normalSize">0 KB</span></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="help">
                    <h6><?= $h('sec_shortcuts') ?></h6>
                    <ul class="list-unstyled">
                        <li><kbd>Ctrl+F</kbd> — <?= $h('hint_kb_search') ?></li>
                        <li><kbd>Ctrl+H</kbd> — <?= $h('hint_kb_replace') ?></li>
                        <li><kbd>Ctrl+S</kbd> — <?= $h('hint_kb_save') ?></li>
                        <li><kbd>Ctrl+Space</kbd> — <?= $h('hint_kb_autocomplete') ?></li>
                        <li><kbd>Alt+F</kbd> — <?= $h('hint_kb_format') ?></li>
                        <li><kbd>Ctrl+/</kbd> — <?= $h('hint_kb_comment') ?></li>
                    </ul>
                    <h6 class="mt-3"><?= $h('sec_templates') ?></h6>
                    <p><?= $h('hint_templates') ?></p>
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
                <h5 class="modal-title"><?= $h('sec_backup_modal') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $h('btn_close') ?>"></button>
            </div>
            <div class="modal-body p-0" style="overflow:hidden">
                <div id="backupViewerContainer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $h('btn_close') ?></button>
                <button type="button" class="btn btn-primary" id="restoreFromModal"><?= $h('btn_load_into_editor') ?></button>
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
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.CSSE_CURRENT_FILE = <?= json_encode($currentFile, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/admin/scripts/csseditor.js?ver=1"></script>

<?php stdfoot(); ?>
