<?php
declare(strict_types=1);

// Раньше файл сам подключал global.php и не проверял STAFF_PANEL: прямой
// заход на admin/smilies.php давал добавлять и удалять смайлы кому угодно.
if (!defined('STAFF_PANEL')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}

define('SM_VERSION', '2.0');
const SM_ASSET_VER  = 1;
const SM_EXT        = ['gif', 'png', 'jpg', 'jpeg', 'webp'];
const SM_MAX_ORDER  = 65535;          // sorder - smallint unsigned
const SM_IMPORT_MAX = 1048576;        // 1 MB на JSON-импорт
const SM_BIG_BYTES  = 102400;         // предупреждение: файл больше 100 KB
const SM_BIG_PX     = 96;             // предупреждение: сторона больше 96 px

final class SmilieManager
{
    private string $dir;
    private string $url;
    private string $self;

    public function __construct(private $db, private $cache)
    {
        global $BASEURL, $pic_base_url, $_this_script_;

        $this->dir  = rtrim(TSDIR . '/' . $pic_base_url . 'smilies', '/');
        $this->url  = rtrim($BASEURL . '/' . $pic_base_url . 'smilies', '/');
        $this->self = (string)($_this_script_ ?? '') ?: 'index.php?act=smilies';
    }

    // ── Маршрутизация ────────────────────────────────────────

    public function run(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && !verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
            http_response_code(403);
            stderr('Security Error', 'Invalid security token. Please refresh the page and try again.');
        }

        match ((string)($_GET['action'] ?? '')) {
            'add_smilie'           => $this->handleForm(false),
            'edit_smilie'          => $this->handleForm(true),
            'delete_smilie'        => $this->handleDelete(),
            'update_smilies_order' => $this->handleUpdateOrder(),
            'export_json'          => $this->handleExport(),
            'import_json'          => $this->handleImport(),
            default                => $this->handleList(),
        };
    }

    // ── Хелперы ──────────────────────────────────────────────

    private function e(string|int|null $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    private function link(string $action = '', array $params = []): string
    {
        $q = ($action !== '' ? ['action' => $action] : []) + $params;
        if (!$q) return $this->self;
        return $this->self . (str_contains($this->self, '?') ? '&' : '?') . http_build_query($q);
    }

    private function done(string $message, string $type = 'success'): void
    {
        flash_message($message, $type);
        admin_redirect($this->self);
        exit;
    }

    private function log(string $what): void
    {
        global $CURUSER;
        write_log('Smilies: ' . $what . ' by ' . ($CURUSER['username'] ?? 'unknown'));
    }

    private function postKey(): string
    {
        global $mybb;
        return $this->e((string)$mybb->post_code);
    }

    private function size(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    /** Имя файла без путей и с разрешённым расширением - иначе ''. */
    private function cleanFile(string $name): string
    {
        $name = trim($name);
        if ($name === '' || $name !== basename($name) || str_starts_with($name, '.')) return '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return in_array($ext, SM_EXT, true) ? $name : '';
    }

    private function fileExists(string $name): bool
    {
        return $name !== '' && is_file($this->dir . '/' . $name);
    }

    /** Все картинки в папке смайлов: имя => размер. */
    private function scanFiles(): array
    {
        $out = [];
        foreach (@scandir($this->dir) ?: [] as $f) {
            if ($this->cleanFile($f) !== '' && is_file($this->dir . '/' . $f)) {
                $out[$f] = (int)filesize($this->dir . '/' . $f);
            }
        }
        uksort($out, 'strnatcasecmp');
        return $out;
    }

    private function allSmilies(): array
    {
        $rows = [];
        $q = $this->db->sql_query_prepared('SELECT * FROM smilies ORDER BY sorder, stitle');
        while ($q && ($r = $this->db->fetch_array($q))) $rows[] = $r;
        return $rows;
    }

    private function getSmilie(int $sid): ?array
    {
        $q = $this->db->sql_query_prepared('SELECT * FROM smilies WHERE sid = ?', [$sid]);
        $r = $q ? $this->db->fetch_array($q) : null;
        return $r ?: null;
    }

    private function nextOrder(): int
    {
        $q   = $this->db->sql_query_prepared('SELECT MAX(sorder) AS m FROM smilies');
        $row = $q ? $this->db->fetch_array($q) : null;
        return min(SM_MAX_ORDER, (int)($row['m'] ?? 0) + 10);
    }

    private function textTaken(string $text, ?int $exceptSid = null): bool
    {
        $q = $exceptSid
            ? $this->db->sql_query_prepared('SELECT sid FROM smilies WHERE stext = ? AND sid <> ? LIMIT 1', [$text, $exceptSid])
            : $this->db->sql_query_prepared('SELECT sid FROM smilies WHERE stext = ? LIMIT 1', [$text]);
        return $q && $this->db->num_rows($q) > 0;
    }

    private function assets(): void
    {
        global $BASEURL;
        $v = SM_ASSET_VER;
        echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">'
           . '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/smilies.css?ver=' . $v . '">';
    }

    private function scripts(): void
    {
        global $BASEURL;
        $v = SM_ASSET_VER;
        echo '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>'
           . '<script src="' . $BASEURL . '/admin/scripts/smilies.js?ver=' . $v . '"></script>';
    }

    /** Скрытая форма удаления: кнопки удаления ставят sid и отправляют её POST-ом. */
    private function deleteForm(): string
    {
        return '<form method="post" action="' . $this->e($this->link('delete_smilie')) . '" id="smDeleteForm" hidden>'
             . '<input type="hidden" name="my_post_key" value="' . $this->postKey() . '">'
             . '<input type="hidden" name="sid" value="">'
             . '</form>';
    }

    // ── Список ───────────────────────────────────────────────

    private function handleList(): void
    {
        $smilies = $this->allSmilies();
        $files   = $this->scanFiles();

        $used    = array_flip(array_map(fn(array $s) => (string)$s['spath'], $smilies));
        $missing = count(array_filter($smilies, fn(array $s) => !isset($files[(string)$s['spath']])));
        $unused  = count(array_diff_key($files, $used));
        $total   = count($smilies);

        stdhead('Smilies');
        $this->assets();
        ?>
<div class="sm-page container mt-3 mb-5" data-smilie-url="<?= $this->e($this->url) ?>">

    <div class="sm-head">
        <div class="sm-head-icon"><i class="fa-solid fa-face-laugh-beam"></i></div>
        <div class="sm-head-text">
            <h1>Smilies</h1>
            <p>Drag a card by its <i class="fa-solid fa-grip-vertical"></i> handle to reorder, click a code to copy it.</p>
        </div>
        <div class="sm-head-actions">
            <a href="<?= $this->e($this->link('import_json')) ?>" class="sm-btn sm-btn-outline"><i class="fa-solid fa-file-import me-1"></i>Import</a>
            <a href="<?= $this->e($this->link('export_json')) ?>" class="sm-btn sm-btn-outline"><i class="fa-solid fa-file-export me-1"></i>Export</a>
            <a href="<?= $this->e($this->link('add_smilie')) ?>" class="sm-btn sm-btn-primary"><i class="fa-solid fa-plus me-1"></i>Add smilie</a>
        </div>
    </div>

    <div class="sm-kpis">
        <div class="sm-kpi">
            <div class="sm-kpi-icon sm-c-primary"><i class="fa-solid fa-face-smile"></i></div>
            <div><div class="sm-kpi-val"><?= $total ?></div><div class="sm-kpi-lbl">Smilies</div></div>
        </div>
        <div class="sm-kpi">
            <div class="sm-kpi-icon sm-c-info"><i class="fa-solid fa-images"></i></div>
            <div><div class="sm-kpi-val"><?= count($files) ?></div><div class="sm-kpi-lbl">Image files · <?= $this->size(array_sum($files)) ?></div></div>
        </div>
        <div class="sm-kpi">
            <div class="sm-kpi-icon sm-c-warning"><i class="fa-solid fa-file-circle-question"></i></div>
            <div><div class="sm-kpi-val"><?= $unused ?></div><div class="sm-kpi-lbl">Unused files</div></div>
        </div>
        <div class="sm-kpi">
            <div class="sm-kpi-icon <?= $missing ? 'sm-c-danger' : 'sm-c-success' ?>"><i class="fa-solid <?= $missing ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i></div>
            <div><div class="sm-kpi-val"><?= $missing ?></div><div class="sm-kpi-lbl">Missing files</div></div>
        </div>
    </div>

    <form method="post" action="<?= $this->e($this->link('update_smilies_order')) ?>" id="smOrderForm">
        <input type="hidden" name="my_post_key" value="<?= $this->postKey() ?>">

        <div class="sm-card">
            <div class="sm-toolbar">
                <div class="sm-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" id="smFilter" placeholder="Filter by title, code or file" aria-label="Filter smilies">
                </div>
                <span class="sm-count" id="smCount"><?= $total ?> shown</span>
            </div>

            <?php if ($total === 0): ?>
                <div class="sm-empty">
                    <i class="fa-regular fa-face-meh-blank"></i>
                    <h2>No smilies yet</h2>
                    <p>Add one by hand or import a JSON file.</p>
                    <a href="<?= $this->e($this->link('add_smilie')) ?>" class="sm-btn sm-btn-primary"><i class="fa-solid fa-plus me-1"></i>Add smilie</a>
                </div>
            <?php else: ?>
                <div class="sm-grid" id="smGrid">
                    <?php foreach ($smilies as $s):
                        $sid   = (int)$s['sid'];
                        $file  = (string)$s['spath'];
                        $ok    = isset($files[$file]);
                        $title = (string)$s['stitle'];
                        $code  = (string)$s['stext'];
                    ?>
                    <div class="sm-item<?= $ok ? '' : ' is-missing' ?>"
                         data-search="<?= $this->e(mb_strtolower($title . ' ' . $code . ' ' . $file)) ?>">
                        <span class="sm-grip" title="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></span>

                        <div class="sm-img">
                            <?php if ($ok): ?>
                                <img src="<?= $this->e($this->url . '/' . rawurlencode($file)) ?>" alt="<?= $this->e($title) ?>" loading="lazy" draggable="false">
                            <?php else: ?>
                                <i class="fa-solid fa-file-circle-xmark" title="File not found: <?= $this->e($file) ?>"></i>
                            <?php endif; ?>
                        </div>

                        <div class="sm-title" title="<?= $this->e($title) ?>"><?= $this->e($title) ?></div>
                        <button type="button" class="sm-code" data-copy="<?= $this->e($code) ?>" title="Copy code">
                            <?= $this->e($code) ?><i class="fa-regular fa-copy"></i>
                        </button>
                        <?php if (!$ok): ?>
                            <span class="sm-badge-missing"><i class="fa-solid fa-triangle-exclamation me-1"></i>File missing</span>
                        <?php endif; ?>

                        <div class="sm-item-foot">
                            <label class="sm-order" title="Display order">
                                <i class="fa-solid fa-arrow-down-1-9"></i>
                                <input type="number" name="sorder[<?= $sid ?>]" value="<?= (int)$s['sorder'] ?>" min="0" max="<?= SM_MAX_ORDER ?>" step="10" aria-label="Order">
                            </label>
                            <a href="<?= $this->e($this->link('edit_smilie', ['sid' => $sid])) ?>" class="sm-icon-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <button type="button" class="sm-icon-btn sm-icon-danger" data-delete="<?= $sid ?>" data-title="<?= $this->e($title) ?>" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="sm-empty sm-empty-filter" id="smNoMatch" hidden>
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <p>Nothing matches this filter.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total > 0): ?>
        <div class="sm-savebar" id="smSaveBar">
            <span class="sm-savebar-hint" id="smDirty" hidden><i class="fa-solid fa-circle-exclamation me-1"></i>Order changed, not saved yet</span>
            <span class="sm-savebar-hint sm-muted" id="smClean"><i class="fa-solid fa-hand-pointer me-1"></i>Drag cards or edit numbers, then save</span>
            <button type="submit" class="sm-btn sm-btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save order</button>
        </div>
        <?php endif; ?>
    </form>

    <?= $this->deleteForm() ?>
</div>
        <?php
        $this->scripts();
        stdfoot();
    }

    // ── Добавление / редактирование ─────────────────────────

    private function handleForm(bool $edit): void
    {
        $sid    = (int)($_GET['sid'] ?? 0);
        $errors = [];

        if ($edit) {
            $data = $this->getSmilie($sid);
            if (!$data) $this->done('Smilie not found.', 'error');
        } else {
            $data = ['stitle' => '', 'stext' => '', 'spath' => '', 'sorder' => $this->nextOrder()];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'stitle' => trim((string)($_POST['stitle'] ?? '')),
                'stext'  => trim((string)($_POST['stext'] ?? '')),
                'spath'  => trim((string)($_POST['spath'] ?? '')),
                'sorder' => (int)($_POST['sorder'] ?? 0),
            ];
            $errors = $this->validate($data, $edit ? $sid : null);

            if (!$errors) {
                if ($edit) {
                    $this->db->sql_query_prepared(
                        'UPDATE smilies SET stitle = ?, stext = ?, spath = ?, sorder = ? WHERE sid = ?',
                        [$data['stitle'], $data['stext'], $data['spath'], $data['sorder'], $sid]
                    );
                    $this->log("edited {$data['stext']} (#{$sid})");
                } else {
                    $this->db->sql_query_prepared(
                        'INSERT INTO smilies (stitle, stext, spath, sorder) VALUES (?, ?, ?, ?)',
                        [$data['stitle'], $data['stext'], $data['spath'], $data['sorder']]
                    );
                    $this->log("added {$data['stext']}");
                }
                $this->cache->update_smilies();
                $this->done($edit ? 'Smilie updated.' : 'Smilie added.');
            }
        }

        $this->renderForm($data, $edit, $errors, $sid);
    }

    private function validate(array $d, ?int $sid): array
    {
        $err = [];

        if ($d['stitle'] === '')                 $err[] = 'Enter a title.';
        elseif (mb_strlen($d['stitle']) > 100)   $err[] = 'The title can be at most 100 characters.';

        if ($d['stext'] === '')                  $err[] = 'Enter the code users will type.';
        elseif (mb_strlen($d['stext']) > 20)     $err[] = 'The code can be at most 20 characters.';
        elseif ($this->textTaken($d['stext'], $sid)) $err[] = 'Another smilie already uses this code.';

        if ($d['spath'] === '')                  $err[] = 'Choose an image file.';
        elseif ($this->cleanFile($d['spath']) === '') $err[] = 'Use a file name only (gif, png, jpg, jpeg or webp), without folders.';
        elseif (!$this->fileExists($d['spath'])) $err[] = 'This file is not in the smilies folder.';

        if ($d['sorder'] < 0 || $d['sorder'] > SM_MAX_ORDER) $err[] = 'Order must be between 0 and ' . SM_MAX_ORDER . '.';

        return $err;
    }

    private function renderForm(array $d, bool $edit, array $errors, int $sid): void
    {
        $files   = $this->scanFiles();
        $usedBy  = [];
        foreach ($this->allSmilies() as $s) {
            if ((int)$s['sid'] !== $sid) $usedBy[(string)$s['spath']][] = (string)$s['stext'];
        }

        $file    = (string)$d['spath'];
        $hasFile = $this->cleanFile($file) !== '' && isset($files[$file]);
        $dim     = $hasFile ? @getimagesize($this->dir . '/' . $file) : false;
        $action  = $edit ? $this->link('edit_smilie', ['sid' => $sid]) : $this->link('add_smilie');

        stdhead($edit ? 'Edit smilie' : 'Add smilie');
        $this->assets();
        ?>
<div class="sm-page container mt-3 mb-5" data-smilie-url="<?= $this->e($this->url) ?>">

    <div class="sm-head">
        <a href="<?= $this->e($this->self) ?>" class="sm-back" title="Back to smilies"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="sm-head-icon"><i class="fa-solid <?= $edit ? 'fa-pen-to-square' : 'fa-circle-plus' ?>"></i></div>
        <div class="sm-head-text">
            <h1><?= $edit ? 'Edit smilie' : 'Add smilie' ?></h1>
            <p><?= $edit ? 'Code ' . $this->e($d['stext']) . ' · ID ' . $sid : 'Pick an image from the smilies folder and give it a code.' ?></p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="sm-alert sm-alert-danger" role="alert">
            <i class="fa-solid fa-circle-xmark"></i>
            <div><?php foreach ($errors as $er): ?><div><?= $this->e($er) ?></div><?php endforeach; ?></div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= $this->e($action) ?>" id="smForm" class="sm-form-grid">
        <input type="hidden" name="my_post_key" value="<?= $this->postKey() ?>">

        <div class="sm-card">
            <h2 class="sm-card-title"><i class="fa-solid fa-sliders"></i>Details</h2>

            <div class="sm-field">
                <label for="stitle"><i class="fa-solid fa-heading"></i>Title <span class="sm-req">*</span></label>
                <input type="text" id="stitle" name="stitle" value="<?= $this->e($d['stitle']) ?>" maxlength="100" required placeholder="Smile">
                <small>Shown as the tooltip in the smilie picker.</small>
            </div>

            <div class="sm-field">
                <label for="stext"><i class="fa-solid fa-keyboard"></i>Code <span class="sm-req">*</span></label>
                <input type="text" id="stext" name="stext" value="<?= $this->e($d['stext']) ?>" maxlength="20" required placeholder=":)" class="sm-mono">
                <small>What users type in a post or comment. Must be unique.</small>
            </div>

            <div class="sm-field">
                <label for="spath"><i class="fa-solid fa-image"></i>Image file <span class="sm-req">*</span></label>
                <div class="sm-input-group">
                    <input type="text" id="spath" name="spath" value="<?= $this->e($file) ?>" required placeholder="smile.gif" class="sm-mono">
                    <button type="button" class="sm-btn sm-btn-outline" data-bs-toggle="modal" data-bs-target="#smPicker">
                        <i class="fa-solid fa-folder-open me-1"></i>Browse
                    </button>
                </div>
                <small><?= count($files) ?> images in <code><?= $this->e(basename($this->dir)) ?>/</code></small>
            </div>

            <div class="sm-field">
                <label for="sorder"><i class="fa-solid fa-arrow-down-1-9"></i>Display order</label>
                <div class="sm-input-group">
                    <input type="number" id="sorder" name="sorder" value="<?= (int)$d['sorder'] ?>" min="0" max="<?= SM_MAX_ORDER ?>" step="10">
                    <button type="button" class="sm-btn sm-btn-outline" id="smAutoOrder" data-next="<?= $this->nextOrder() ?>">
                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Last
                    </button>
                </div>
                <small>Lower numbers come first.</small>
            </div>
        </div>

        <div class="sm-side">
            <div class="sm-card sm-preview-card">
                <h2 class="sm-card-title"><i class="fa-solid fa-eye"></i>Preview</h2>
                <div class="sm-preview-stage">
                    <img id="smPreviewImg" src="<?= $hasFile ? $this->e($this->url . '/' . rawurlencode($file)) : '' ?>" alt="" <?= $hasFile ? '' : 'hidden' ?>>
                    <i class="fa-regular fa-image sm-preview-empty" id="smPreviewEmpty" <?= $hasFile ? 'hidden' : '' ?>></i>
                </div>
                <div class="sm-preview-title" id="smPreviewTitle"><?= $this->e($d['stitle'] ?: 'No title') ?></div>

                <div class="sm-preview-post">
                    <span class="sm-muted">In a post:</span>
                    <p>Nice upload, thanks
                        <img id="smPreviewInline" src="<?= $hasFile ? $this->e($this->url . '/' . rawurlencode($file)) : '' ?>" alt="" <?= $hasFile ? '' : 'hidden' ?>>
                        <code id="smPreviewCode" <?= $hasFile ? 'hidden' : '' ?>><?= $this->e($d['stext'] ?: ':)') ?></code>
                    </p>
                </div>

                <?php if ($hasFile): ?>
                <dl class="sm-meta">
                    <div><dt><i class="fa-solid fa-weight-hanging"></i>Size</dt><dd><?= $this->size($files[$file]) ?></dd></div>
                    <div><dt><i class="fa-solid fa-expand"></i>Dimensions</dt><dd><?= $dim ? (int)$dim[0] . ' × ' . (int)$dim[1] . ' px' : '—' ?></dd></div>
                </dl>
                <?php if ($files[$file] > SM_BIG_BYTES || ($dim && max($dim[0], $dim[1]) > SM_BIG_PX)): ?>
                    <div class="sm-alert sm-alert-warning sm-alert-sm">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div>This image is large for a smilie and will slow down pages with many of them.</div>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="sm-actions">
                <button type="submit" class="sm-btn sm-btn-primary sm-btn-lg"><i class="fa-solid fa-floppy-disk me-1"></i><?= $edit ? 'Save changes' : 'Add smilie' ?></button>
                <a href="<?= $this->e($this->self) ?>" class="sm-btn sm-btn-outline sm-btn-lg">Cancel</a>
                <?php if ($edit): ?>
                    <button type="button" class="sm-btn sm-btn-danger-soft sm-btn-lg" data-delete="<?= $sid ?>" data-title="<?= $this->e($d['stitle']) ?>">
                        <i class="fa-solid fa-trash-can me-1"></i>Delete
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?= $this->deleteForm() ?>

    <!-- Выбор файла. Раньше кнопка открывала модалку #fileBrowser, которой на странице не было. -->
    <div class="modal fade" id="smPicker" tabindex="-1" aria-labelledby="smPickerTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content sm-modal">
                <div class="modal-header">
                    <h2 class="modal-title" id="smPickerTitle"><i class="fa-solid fa-folder-open me-2"></i>Choose an image</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="sm-search mb-3">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="smPickerFilter" placeholder="Filter files" aria-label="Filter files">
                    </div>
                    <?php if (!$files): ?>
                        <div class="sm-empty"><i class="fa-regular fa-folder-open"></i><p>The smilies folder has no images.</p></div>
                    <?php else: ?>
                    <div class="sm-picker-grid" id="smPickerGrid">
                        <?php foreach ($files as $f => $bytes):
                            $by = $usedBy[$f] ?? [];
                        ?>
                        <button type="button" class="sm-pick<?= $f === $file ? ' is-current' : '' ?><?= $by ? ' is-used' : '' ?>"
                                data-file="<?= $this->e($f) ?>" data-search="<?= $this->e(mb_strtolower($f)) ?>"
                                title="<?= $this->e($f) . ($by ? ' · used by ' . $this->e(implode(', ', $by)) : '') ?>">
                            <span class="sm-pick-img"><img src="<?= $this->e($this->url . '/' . rawurlencode($f)) ?>" alt="" loading="lazy"></span>
                            <span class="sm-pick-name"><?= $this->e($f) ?></span>
                            <span class="sm-pick-meta">
                                <?= $this->size($bytes) ?>
                                <?php if ($by): ?><i class="fa-solid fa-link" title="In use"></i><?php endif; ?>
                            </span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer sm-muted">
                    <i class="fa-solid fa-link me-1"></i>already used by another smilie
                </div>
            </div>
        </div>
    </div>
</div>
        <?php
        $this->scripts();
        stdfoot();
    }

    // ── Удаление ─────────────────────────────────────────────

    private function handleDelete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            admin_redirect($this->self);
            exit;
        }

        $sid = (int)($_POST['sid'] ?? 0);
        $s   = $sid > 0 ? $this->getSmilie($sid) : null;
        if (!$s) $this->done('Smilie not found.', 'error');

        $this->db->sql_query_prepared('DELETE FROM smilies WHERE sid = ?', [$sid]);
        $this->cache->update_smilies();
        $this->log("deleted {$s['stext']} (#{$sid})");
        $this->done('Smilie "' . $s['stitle'] . '" deleted.');
    }

    // ── Порядок ──────────────────────────────────────────────

    private function handleUpdateOrder(): void
    {
        $orders = $_POST['sorder'] ?? null;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($orders) || !$orders) {
            $this->done('Nothing to save.', 'error');
        }

        $changed = 0;
        foreach ($orders as $sid => $order) {
            $sid   = (int)$sid;
            $order = max(0, min(SM_MAX_ORDER, (int)$order));
            if ($sid <= 0) continue;
            $this->db->sql_query_prepared('UPDATE smilies SET sorder = ? WHERE sid = ? AND sorder <> ?', [$order, $sid, $order]);
            $changed += (int)$this->db->affected_rows();
        }

        if ($changed) {
            $this->cache->update_smilies();
            $this->log("reordered {$changed} smilies");
        }
        $this->done($changed ? "Order saved ({$changed} changed)." : 'Order was already up to date.');
    }

    // ── Экспорт / импорт ────────────────────────────────────

    private function handleExport(): void
    {
        $out = array_map(fn(array $s) => [
            'title' => (string)$s['stitle'],
            'text'  => (string)$s['stext'],
            'file'  => (string)$s['spath'],
            'order' => (int)$s['sorder'],
        ], $this->allSmilies());

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="smilies_' . date('Y-m-d') . '.json"');
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function handleImport(): void
    {
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $f = $_FILES['import_file'] ?? null;

            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $error = 'Choose a JSON file to import.';
            } elseif ((int)$f['size'] > SM_IMPORT_MAX) {
                $error = 'The file is larger than 1 MB.';
            } else {
                $data = json_decode((string)file_get_contents($f['tmp_name']), true);
                if (!is_array($data) || !array_is_list($data)) {
                    $error = 'This is not a smilies export: expected a JSON list.';
                } else {
                    [$added, $skipped] = $this->importRows($data);
                    if ($added) {
                        $this->cache->update_smilies();
                        $this->log("imported {$added} smilies");
                    }
                    $msg = "Imported {$added} smilies.";
                    if ($skipped) $msg .= " Skipped {$skipped}: duplicate code, missing file or invalid data.";
                    $this->done($msg, $added ? 'success' : 'error');
                }
            }
        }

        stdhead('Import smilies');
        $this->assets();
        ?>
<div class="sm-page container mt-3 mb-5">
    <div class="sm-head">
        <a href="<?= $this->e($this->self) ?>" class="sm-back" title="Back to smilies"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="sm-head-icon"><i class="fa-solid fa-file-import"></i></div>
        <div class="sm-head-text">
            <h1>Import smilies</h1>
            <p>Adds smilies from a JSON export. Existing smilies are not changed.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="sm-alert sm-alert-danger" role="alert"><i class="fa-solid fa-circle-xmark"></i><div><?= $this->e($error) ?></div></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="<?= $this->e($this->link('import_json')) ?>" class="sm-card sm-import">
        <input type="hidden" name="my_post_key" value="<?= $this->postKey() ?>">

        <label class="sm-drop" for="smImportFile">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <span class="sm-drop-title" id="smImportName">Choose a JSON file</span>
            <span class="sm-muted">or drop it here · up to 1 MB</span>
            <input type="file" id="smImportFile" name="import_file" accept=".json,application/json" required>
        </label>

        <ul class="sm-rules">
            <li><i class="fa-solid fa-circle-check"></i>Image files must already be in <code><?= $this->e(basename($this->dir)) ?>/</code></li>
            <li><i class="fa-solid fa-circle-check"></i>Codes that already exist are skipped</li>
            <li><i class="fa-solid fa-circle-check"></i>Format: <code>[{"title":"Smile","text":":)","file":"smile.gif","order":10}]</code></li>
        </ul>

        <div class="sm-actions sm-actions-row">
            <button type="submit" class="sm-btn sm-btn-primary sm-btn-lg"><i class="fa-solid fa-upload me-1"></i>Import</button>
            <a href="<?= $this->e($this->self) ?>" class="sm-btn sm-btn-outline sm-btn-lg">Cancel</a>
        </div>
    </form>
</div>
        <?php
        $this->scripts();
        stdfoot();
        exit;
    }

    /** @return array{0:int,1:int} [добавлено, пропущено] */
    private function importRows(array $rows): array
    {
        $added   = 0;
        $skipped = 0;
        $seen    = [];

        foreach ($rows as $r) {
            $title = is_array($r) ? trim((string)($r['title'] ?? '')) : '';
            $text  = is_array($r) ? trim((string)($r['text'] ?? '')) : '';
            $file  = is_array($r) ? $this->cleanFile((string)($r['file'] ?? '')) : '';
            $order = is_array($r) ? max(0, min(SM_MAX_ORDER, (int)($r['order'] ?? 0))) : 0;

            if ($title === '' || mb_strlen($title) > 100 || $text === '' || mb_strlen($text) > 20
                || $file === '' || !$this->fileExists($file)
                || isset($seen[$text]) || $this->textTaken($text)) {
                $skipped++;
                continue;
            }

            $this->db->sql_query_prepared(
                'INSERT INTO smilies (stitle, stext, spath, sorder) VALUES (?, ?, ?, ?)',
                [$title, $text, $file, $order]
            );
            $seen[$text] = true;
            $added++;
        }
        return [$added, $skipped];
    }
}

(new SmilieManager($db, $cache))->run();