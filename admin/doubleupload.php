<?php

declare(strict_types=1);

/**
 * Torrent Double Upload Manager
 * PHP 8.1+ Enhanced Version
 * 
 * @package StaffPanel
 * @version 2.0
 * @author TSSpecial Edition v5.6
 */

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger" role="alert">
            <i class="fas fa-ban me-2"></i>
            <strong>Access Denied!</strong> Direct initialization is not allowed.
          </div>');
}

global $lang;
$lang->load('doubleupload');

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… (и %1$s — в него $lang->load() превращает {1})
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $v) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$v;
            $map['%' . $n . '$s'] = (string)$v;
        }
        return strtr($str, $map);
    }
}

class TorrentDoubleUploadManager
{
    private const ALLOWED_ACTIONS = ['main', 'setalldouble', 'setallnormal'];
    
    private array $currentUser;
    private object $database;
    private string $scriptUrl;
    
    public function __construct()
    {
        global $db;
        $this->database = $db;
        $this->currentUser = $GLOBALS['CURUSER'] ?? [];
        $this->scriptUrl = 'index.php?act=doubleupload';
    }
    
    /**
     * Main execution method
     */
    public function execute(): void
    {
        try {
            $action = $this->getAction();
            
            match ($action) {
                'setalldouble' => $this->setAllDoubleUpload(),
                'setallnormal' => $this->setAllNormal(),
                default => $this->showMainInterface()
            };
            
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }
	
	
	
	private function jsonSuccess(string $title, string $message): void
{
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'title'  => $title,
        'message'=> $message
    ]);
    exit;
}

private function jsonError(string $message): void
{
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'status' => 'error',
        'message'=> $message
    ]);
    exit;
}

	
	
	
    
    /**
     * Get and validate action
     */
    private function getAction(): string
    {
        $action = $_SERVER['REQUEST_METHOD'] === 'POST'
            ? ($_POST['action'] ?? 'main')
            : ($_GET['action'] ?? 'main');

        // Мутирующие действия принимаем только через POST — GET может дойти
        // через простую ссылку/img-тег, что превращает это в one-click CSRF.
        if (in_array($action, ['setalldouble', 'setallnormal'], true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $action = 'main';
        }

        $action = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');

        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            throw new InvalidArgumentException('Invalid action specified');
        }

        return $action;
    }
    
    /**
     * Set all torrents to double upload
     */
   private function setAllDoubleUpload(): void
{
    global $lang;
    $this->validateStaffAccess();
    $this->validateCsrf();

    $query = "UPDATE torrents 
              SET doubleupload = 'yes' 
              WHERE doubleupload = 'no'";

    if (!$this->database->sql_query_prepared($query)) {
        $this->jsonError($lang->doubleupload['err_db']);
    }

    $affectedRows = $this->database->affected_rows();
    $this->logAction('double', $affectedRows);

    $this->jsonSuccess(
        $lang->doubleupload['msg_double_title'],
        ags_fmt($lang->doubleupload['msg_updated'], number_format($affectedRows))
    );
}


private function setAllNormal(): void
{
    global $lang;
    $this->validateStaffAccess();
    $this->validateCsrf();

    $query = "UPDATE torrents 
              SET doubleupload = 'no' 
              WHERE doubleupload = 'yes'";

    if (!$this->database->sql_query_prepared($query)) {
        $this->jsonError($lang->doubleupload['err_db']);
    }

    $affectedRows = $this->database->affected_rows();
    $this->logAction('normal', $affectedRows);

    $this->jsonSuccess(
        $lang->doubleupload['msg_normal_title'],
        ags_fmt($lang->doubleupload['msg_updated'], number_format($affectedRows))
    );
}



    
    /**
     * Show main interface
     *
     * Раньше: внутри страницы печатался второй <!DOCTYPE html><html data-bs-theme="dark">
     * <head><body>, карточки «прыгали» через transform, а блоки «Before/After» с
     * bg-light в тёмной теме были белыми пятнами.
     */
    private function showMainInterface(): void
    {
        global $mybb, $lang;

        $L        = $lang->doubleupload;
        $e        = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $stats    = $this->getTorrentStats();
        $canAct   = $this->hasStaffAccess();
        $recent   = $this->getRecentActions();
        $key      = htmlspecialchars((string)$mybb->post_code, ENT_QUOTES);
        $url      = htmlspecialchars($this->scriptUrl, ENT_QUOTES);
        $dPct     = round($stats['double_percent'], 1);
        $nPct     = round($stats['normal_percent'], 1);
        $mode     = $stats['total'] === 0 ? 'empty' : ($stats['double_count'] === $stats['total'] ? 'double' : ($stats['double_count'] === 0 ? 'normal' : 'mixed'));
        $modeInfo = [
            'double' => ['fa-rocket',       'du-mode-double', $L['mode_double']],
            'normal' => ['fa-circle-check', 'du-mode-normal', $L['mode_normal']],
            'mixed'  => ['fa-code-branch',  'du-mode-mixed',  $L['mode_mixed']],
            'empty'  => ['fa-inbox',        'du-mode-normal', $L['mode_empty']],
        ][$mode];

        $nNormal  = '<strong>' . number_format($stats['normal_count']) . '</strong>';
        $nDouble  = '<strong>' . number_format($stats['double_count']) . '</strong>';

        $jsLang = [];
        foreach ($L as $k => $v) {
            if (str_starts_with((string)$k, 'js_')) {
                $jsLang[substr((string)$k, 3)] = (string)$v;
            }
        }

        stdhead($L['page_title']);
        ?>
<style>
.du .du-card, .du-modal .modal-content { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color-translucent); border-radius: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.du .du-head { display: flex; flex-wrap: wrap; align-items: center; gap: .9rem; padding: 1.1rem 1.25rem; }
.du .du-head-icon, .du .du-stat-icon, .du .du-act-icon, .du-modal .du-mh-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.du .du-head-icon { width: 48px; height: 48px; font-size: 1.35rem; border-radius: .85rem; color: #d97706; background: rgba(245,158,11,.14); }
.du .du-title { font-size: 1.4rem; font-weight: 700; margin: 0; }
.du .du-sub { color: var(--bs-secondary-color); font-size: .95rem; }
.du .du-muted { font-size: .86rem; color: var(--bs-secondary-color); }
.du .du-ver { font-size: .78rem; font-weight: 700; padding: .25rem .65rem; border-radius: 50rem; background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); border: 1px solid var(--bs-border-color-translucent); }
.du .btn, .du-modal .btn { border-radius: 50rem; }
.du .ic-blue  { color: var(--bs-primary); background: rgba(var(--bs-primary-rgb),.12); }
.du .ic-amber { color: #d97706; background: rgba(245,158,11,.14); }
.du .ic-slate { color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); }

.du .du-mode { display: inline-flex; align-items: center; gap: .45rem; padding: .35rem .85rem; border-radius: 50rem; font-weight: 700; font-size: .88rem; }
.du .du-mode-double { color: #b45309; background: rgba(245,158,11,.14); border: 1px solid rgba(245,158,11,.4); }
.du .du-mode-normal { color: var(--bs-primary); background: rgba(var(--bs-primary-rgb),.1); border: 1px solid rgba(var(--bs-primary-rgb),.3); }
.du .du-mode-mixed  { color: #7c3aed; background: rgba(124,58,237,.1); border: 1px solid rgba(124,58,237,.3); }

.du .du-stat { display: flex; align-items: center; gap: .85rem; padding: 1rem 1.15rem; height: 100%; }
.du .du-stat-icon { width: 44px; height: 44px; font-size: 1.15rem; border-radius: .85rem; }
.du .du-stat-label { font-size: .8rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--bs-secondary-color); }
.du .du-stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }

.du .du-split { height: 14px; border-radius: 50rem; overflow: hidden; display: flex; background: var(--bs-secondary-bg); }
.du .du-split .n { background: linear-gradient(90deg, #60a5fa, #3b82f6); }
.du .du-split .d { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.du .du-legend { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: .5rem; font-size: .88rem; color: var(--bs-secondary-color); }
.du .du-legend i { font-size: .7rem; margin-right: .3rem; }

/* Карточки действий — без transform (подсветка рамкой и тенью) */
.du .du-action { display: flex; flex-direction: column; height: 100%; padding: 1.4rem; border-radius: 1rem; border: 2px solid var(--bs-border-color-translucent); background: var(--bs-body-bg); text-align: left; width: 100%; color: inherit; transition: border-color .15s ease, box-shadow .15s ease; }
.du .du-action:not(:disabled):hover { box-shadow: 0 .6rem 1.4rem rgba(0,0,0,.08); }
.du .du-action.is-double:not(:disabled):hover { border-color: rgba(245,158,11,.6); }
.du .du-action.is-normal:not(:disabled):hover { border-color: rgba(var(--bs-primary-rgb),.5); }
.du .du-action:disabled { opacity: .55; cursor: not-allowed; }
.du .du-act-icon { width: 56px; height: 56px; font-size: 1.5rem; border-radius: 1rem; margin-bottom: 1rem; }
.du .du-action h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: .35rem; }
.du .du-action .du-cta { margin-top: auto; padding-top: 1rem; font-weight: 700; display: inline-flex; align-items: center; gap: .4rem; }
.du .du-action.is-double .du-cta { color: #d97706; }
.du .du-action.is-normal .du-cta { color: var(--bs-primary); }

.du .du-guide { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: .4rem 1rem; padding: 1rem 1.25rem; }
.du .du-guide div { display: flex; align-items: flex-start; gap: .55rem; font-size: .93rem; }
.du .du-guide i { margin-top: .2rem; width: 1.1rem; }
.du .du-log { list-style: none; margin: 0; padding: 0; }
.du .du-log li { display: flex; align-items: center; gap: .6rem; padding: .55rem 1.25rem; border-top: 1px solid var(--bs-border-color-translucent); font-size: .92rem; }

.du-modal .modal-content { border: 0; overflow: hidden; }
.du-modal .modal-header { border-bottom: 1px solid var(--bs-border-color-translucent); }
.du-modal .du-mh-icon { width: 40px; height: 40px; border-radius: .75rem; margin-right: .75rem; }
.du-modal .du-ba { display: grid; grid-template-columns: 1fr auto 1fr; gap: .75rem; align-items: center; text-align: center; }
.du-modal .du-ba > div { padding: .9rem; border-radius: .9rem; background: var(--bs-tertiary-bg); }
.du-modal .du-ba .v { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
.du-modal .du-note { display: flex; gap: .6rem; padding: .75rem .9rem; border-radius: .85rem; font-size: .92rem; margin-top: 1rem; }
.du-modal .du-note.warn { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.35); }
.du-modal .du-note.info { background: rgba(var(--bs-primary-rgb),.06); border: 1px solid rgba(var(--bs-primary-rgb),.25); }
</style>

<div class="container mt-3 mb-4 du">

    <!-- Шапка -->
    <div class="du-card mb-3"><div class="du-head">
        <span class="du-head-icon"><i class="fas fa-bolt"></i></span>
        <div style="min-width:0">
            <h1 class="du-title"><?= $e($L['pane_title']) ?></h1>
            <div class="du-sub"><?= $e($L['pane_subtitle']) ?></div>
        </div>
        <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
            <span class="du-mode <?= $modeInfo[1] ?>"><i class="fas <?= $modeInfo[0] ?>"></i><?= $e($modeInfo[2]) ?></span>
            <span class="du-ver"><i class="fas fa-code-branch me-1"></i>v2.1</span>
        </div>
    </div></div>

    <!-- Статистика -->
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="du-card du-stat"><span class="du-stat-icon ic-slate"><i class="fas fa-magnet"></i></span>
            <div><div class="du-stat-label"><?= $e($L['lbl_total']) ?></div><div class="du-stat-value"><?= number_format($stats['total']) ?></div></div></div></div>
        <div class="col-md-4"><div class="du-card du-stat"><span class="du-stat-icon ic-blue"><i class="fas fa-upload"></i></span>
            <div><div class="du-stat-label"><?= $e($L['lbl_normal']) ?></div><div class="du-stat-value"><?= number_format($stats['normal_count']) ?></div><div class="du-muted"><?= $nPct ?>%</div></div></div></div>
        <div class="col-md-4"><div class="du-card du-stat"><span class="du-stat-icon ic-amber"><i class="fas fa-angles-up"></i></span>
            <div><div class="du-stat-label"><?= $e($L['lbl_double']) ?></div><div class="du-stat-value"><?= number_format($stats['double_count']) ?></div><div class="du-muted"><?= $dPct ?>%</div></div></div></div>
    </div>

    <div class="du-card p-3 mb-3">
        <div class="du-split" role="img" aria-label="<?= $e(ags_fmt($L['aria_split'], $nPct, $dPct)) ?>">
            <span class="n" style="width:<?= $nPct ?>%"></span><span class="d" style="width:<?= $dPct ?>%"></span>
        </div>
        <div class="du-legend">
            <span><i class="fas fa-circle text-primary"></i><?= $e($L['lbl_legend_normal']) ?> · <?= number_format($stats['normal_count']) ?></span>
            <span><i class="fas fa-circle text-warning"></i><?= $e($L['lbl_legend_double']) ?> · <?= number_format($stats['double_count']) ?></span>
        </div>
    </div>

    <?php if (!$canAct): ?>
    <div class="alert alert-warning d-flex gap-2 rounded-4"><i class="fas fa-lock mt-1"></i>
        <div><?= $L['hint_no_access'] ?></div></div>
    <?php endif; ?>

    <!-- Действия -->
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <button type="button" class="du-action is-double" data-bs-toggle="modal" data-bs-target="#confirmDoubleModal"
                    <?= (!$canAct || $stats['normal_count'] === 0) ? 'disabled' : '' ?>>
                <span class="du-act-icon ic-amber"><i class="fas fa-rocket"></i></span>
                <h3><?= $e($L['sec_enable']) ?></h3>
                <div class="du-muted"><?= ags_fmt($L['hint_enable'], $nNormal) ?></div>
                <span class="du-cta"><?= $stats['normal_count'] === 0 ? '<i class="fas fa-check"></i>' . $e($L['btn_already_on']) : '<i class="fas fa-bolt"></i>' . $e($L['btn_activate']) ?></span>
            </button>
        </div>
        <div class="col-lg-6">
            <button type="button" class="du-action is-normal" data-bs-toggle="modal" data-bs-target="#confirmNormalModal"
                    <?= (!$canAct || $stats['double_count'] === 0) ? 'disabled' : '' ?>>
                <span class="du-act-icon ic-blue"><i class="fas fa-rotate-left"></i></span>
                <h3><?= $e($L['sec_revert']) ?></h3>
                <div class="du-muted"><?= ags_fmt($L['hint_revert'], $nDouble) ?></div>
                <span class="du-cta"><?= $stats['double_count'] === 0 ? '<i class="fas fa-check"></i>' . $e($L['btn_nothing']) : '<i class="fas fa-rotate-left"></i>' . $e($L['btn_revert']) ?></span>
            </button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="du-card h-100">
                <div class="du-head pb-0"><span class="du-stat-icon ic-slate" style="width:38px;height:38px;font-size:1rem"><i class="fas fa-circle-info"></i></span><h2 class="h6 fw-bold mb-0"><?= $e($L['sec_how']) ?></h2></div>
                <div class="du-guide">
                    <div><i class="fas fa-angles-up text-warning"></i><?= $e($L['guide_double']) ?></div>
                    <div><i class="fas fa-upload text-primary"></i><?= $e($L['guide_normal']) ?></div>
                    <div><i class="fas fa-globe text-body-secondary"></i><?= $e($L['guide_all']) ?></div>
                    <div><i class="fas fa-clipboard-list text-body-secondary"></i><?= $e($L['guide_log']) ?></div>
                    <div><i class="fas fa-triangle-exclamation text-warning"></i><?= $e($L['guide_keep']) ?></div>
                    <div><i class="fas fa-user-lock text-danger"></i><?= $e($L['guide_access']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="du-card h-100 overflow-hidden">
                <div class="du-head pb-2"><span class="du-stat-icon ic-slate" style="width:38px;height:38px;font-size:1rem"><i class="fas fa-clock-rotate-left"></i></span><h2 class="h6 fw-bold mb-0"><?= $e($L['sec_recent']) ?></h2></div>
                <?php if ($recent): ?>
                <ul class="du-log">
                    <?php foreach ($recent as $r):
                        $on = str_contains((string)$r['txt'], 'enabled'); ?>
                    <li><i class="fas <?= $on ? 'fa-rocket text-warning' : 'fa-rotate-left text-primary' ?>"></i>
                        <span class="flex-grow-1"><?= htmlspecialchars((string)$r['txt']) ?></span>
                        <span class="du-muted text-nowrap"><?= my_datee('relative', (int)$r['added']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="du-muted px-4 pb-4"><?= $e($L['hint_no_log']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($canAct): ?>
<!-- Подтверждение: включить -->
<div class="modal fade du-modal" id="confirmDoubleModal" tabindex="-1" aria-labelledby="duDblTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header">
            <span class="du-mh-icon ic-amber" style="color:#d97706;background:rgba(245,158,11,.14)"><i class="fas fa-rocket"></i></span>
            <h5 class="modal-title fw-bold" id="duDblTitle"><?= $e($L['modal_enable_title']) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $e($L['aria_close']) ?>"></button>
        </div>
        <div class="modal-body">
            <div class="du-ba">
                <div><div class="du-muted"><?= $e($L['lbl_double_now']) ?></div><div class="v"><?= number_format($stats['double_count']) ?></div></div>
                <i class="fas fa-arrow-right-long text-body-secondary"></i>
                <div><div class="du-muted"><?= $e($L['lbl_double_after']) ?></div><div class="v text-warning"><?= number_format($stats['total']) ?></div></div>
            </div>
            <div class="du-note warn"><i class="fas fa-triangle-exclamation text-warning mt-1"></i>
                <div><?= ags_fmt($L['note_enable'], $nNormal) ?></div></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal"><i class="fas fa-xmark me-1"></i><?= $e($L['btn_cancel']) ?></button>
            <form method="post" action="<?= $url ?>" class="d-inline" data-action="setalldouble">
                <input type="hidden" name="action" value="setalldouble">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <button type="submit" class="btn btn-warning px-4"><i class="fas fa-bolt me-1"></i><?= $e($L['btn_enable_x2']) ?></button>
            </form>
        </div>
    </div></div>
</div>

<!-- Подтверждение: вернуть -->
<div class="modal fade du-modal" id="confirmNormalModal" tabindex="-1" aria-labelledby="duNrmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header">
            <span class="du-mh-icon" style="color:var(--bs-primary);background:rgba(var(--bs-primary-rgb),.12)"><i class="fas fa-rotate-left"></i></span>
            <h5 class="modal-title fw-bold" id="duNrmTitle"><?= $e($L['modal_revert_title']) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $e($L['aria_close']) ?>"></button>
        </div>
        <div class="modal-body">
            <div class="du-ba">
                <div><div class="du-muted"><?= $e($L['lbl_double_now']) ?></div><div class="v text-warning"><?= number_format($stats['double_count']) ?></div></div>
                <i class="fas fa-arrow-right-long text-body-secondary"></i>
                <div><div class="du-muted"><?= $e($L['lbl_double_after']) ?></div><div class="v">0</div></div>
            </div>
            <div class="du-note info"><i class="fas fa-circle-info text-primary mt-1"></i>
                <div><?= $e($L['note_revert']) ?></div></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal"><i class="fas fa-xmark me-1"></i><?= $e($L['btn_cancel']) ?></button>
            <form method="post" action="<?= $url ?>" class="d-inline" data-action="setallnormal">
                <input type="hidden" name="action" value="setallnormal">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-rotate-left me-1"></i><?= $e($L['btn_revert']) ?></button>
            </form>
        </div>
    </div></div>
</div>

<!-- Результат -->
<div class="modal fade du-modal" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content text-center p-4">
        <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle" style="width:64px;height:64px;font-size:1.8rem;color:#16a34a;background:rgba(34,197,94,.12)"><i class="fas fa-circle-check"></i></div>
        <h5 class="fw-bold mb-1" id="successTitle"><?= $e($L['js_done']) ?></h5>
        <p class="mb-2" id="successMessage"></p>
        <small class="text-body-secondary"><i class="fas fa-rotate me-1"></i><?= $e($L['lbl_refreshing']) ?></small>
    </div></div>
</div>

<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function t(key, fallback, ...args) {
    let s = (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
    args.forEach((v, i) => { s = s.split('{' + (i + 1) + '}').join(String(v)).split('%' + (i + 1) + '$s').join(String(v)); });
    return s;
}
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.du-modal form[data-action]').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const html = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            btn.appendChild(document.createTextNode(t('working', 'Working…')));

            fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                // Раньше при ответе 500 текст ошибки сервера терялся («HTTP error! status: 500»)
                .then(r => r.json().catch(() => ({ status: 'error', message: t('bad_response', 'Unexpected server response ({1})', r.status) })))
                .then(data => {
                    if (data.status !== 'success') throw new Error(data.message || t('unknown_error', 'Unknown error'));
                    bootstrap.Modal.getInstance(form.closest('.modal'))?.hide();
                    document.getElementById('successTitle').textContent = data.title || t('done', 'Done');
                    document.getElementById('successMessage').textContent = data.message || '';
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('successModal')).show();
                    setTimeout(() => location.reload(), 1800);
                })
                .catch(err => {
                    btn.disabled = false; btn.innerHTML = html;
                    if (typeof showToast === 'function') showToast(String(err.message).replace(/[<>&]/g, ''), 'error');
                    else alert(err.message);
                });
        });
    });
});
</script>
<?php endif; ?>
        <?php
        stdfoot();
    }

    /** Может ли текущий пользователь переключать режим (Administrator 7, Sysop 8) */
    private function hasStaffAccess(): bool
    {
        return in_array((int)($this->currentUser['usergroup'] ?? 0), [7, 8], true);
    }

    /** Последние переключения из журнала сайта */
    private function getRecentActions(): array
    {
        $rows = [];
        $q = $this->database->sql_query_prepared(
            "SELECT txt, added FROM sitelog WHERE txt LIKE ? ORDER BY added DESC LIMIT 5",
            ['% double upload for % torrents']
        );
        while ($q && ($r = $this->database->fetch_array($q))) {
            $rows[] = $r;
        }
        return $rows;
    }

    /**
     * Get torrent statistics (один запрос вместо двух)
     */
    private function getTorrentStats(): array
    {
        $res  = $this->database->sql_query_prepared(
            "SELECT COUNT(*) AS total, COALESCE(SUM(doubleupload = 'yes'), 0) AS double_count FROM torrents"
        );
        $row  = $res ? $this->database->fetch_array($res) : null;
        $total  = (int)($row['total'] ?? 0);
        $double = (int)($row['double_count'] ?? 0);
        $normal = $total - $double;

        return [
            'total'          => $total,
            'double_count'   => $double,
            'normal_count'   => $normal,
            'double_percent' => $total > 0 ? $double / $total * 100 : 0,
            'normal_percent' => $total > 0 ? $normal / $total * 100 : 0,
        ];
    }

    /**
     * Validate staff access
     */
    private function validateStaffAccess(): void
    {
        // Moderator(6) сюда сознательно не входит — действие затрагивает весь трекер
        if (!$this->hasStaffAccess()) {
            global $lang;
            $this->jsonError($lang->doubleupload['err_no_access']);
        }
    }


    /**
     * Validate CSRF token for state-changing actions
     */
    private function validateCsrf(): void
    {
        $token = $_POST['my_post_key'] ?? '';
        // $silent=true — иначе при провале функция может сама вывести HTML
        // (в зависимости от состояния IN_ADMINCP) вместо простого false.
        // Этот эндпоинт всегда отвечает JSON — примешавшийся HTML сломает
        // res.json() на фронте.
        if (!verify_post_check($token, true)) {
            global $lang;
            $this->jsonError($lang->doubleupload['err_csrf']);
        }
    }
    
    /**
     * Log action to system log
     */
    private function logAction(string $type, int $affectedRows): void
    {
        $username = $this->currentUser['username'] ?? 'System';
        $action = ($type === 'double') ? 'enabled double upload' : 'disabled double upload';
        
        $message = sprintf(
            '%s %s for %d torrents',
            $username,
            $action,
            $affectedRows
        );
        
        // write_log() уже сам пишет в sitelog — отдельный прямой INSERT
        // ниже дублировал одну и ту же запись дважды.
        if (function_exists('write_log')) {
            write_log($message);
        }
    }
    
    /**
     * Handle errors gracefully
     */
    private function handleError(Throwable $e): void
    {
        $username = $this->currentUser['username'] ?? 'System';
        $logMessage = sprintf(
            'TorrentDoubleUploadManager error for %s: %s in %s:%d',
            $username,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        if (function_exists('write_log')) {
            write_log($logMessage);
        }
        
        // Раньше HTML ошибки печатался без stdhead() и даже в ответ на AJAX-запрос
        global $lang;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->jsonError($lang->doubleupload['err_unexpected_ajax']);
        }
        stderr($lang->doubleupload['err_system_title'], $lang->doubleupload['err_unexpected']);
        exit;
    }
}

// Initialize and execute
try {
    $manager = new TorrentDoubleUploadManager();
    $manager->execute();
} catch (Throwable $e) {
    // Fallback error display (ланг мог не загрузиться — оставляем английский запасной текст)
    http_response_code(500);
    $fatalLang = $GLOBALS['lang']->doubleupload ?? [];
    echo '<div class="alert alert-danger m-3" role="alert">
            <h4><i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars((string)($fatalLang['err_fatal_title'] ?? 'Fatal Error')) . '</h4>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <small>' . htmlspecialchars((string)($fatalLang['err_fatal_contact'] ?? 'Please contact system administrator.')) . '</small>
          </div>';
}