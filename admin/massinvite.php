<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization is not allowed.</div>');
}

global $lang;
$lang->load('massinvite');

if (!function_exists('ags_fmt')) {
    /** {1}, {2}… (и %1$s — в него $lang->load() превращает {N}) → аргументы */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $a) {
            $n = $i + 1;
            $map['{' . $n . '}'] = (string)$a;
            $map['%' . $n . '$s'] = (string)$a;
        }
        return strtr($str, $map);
    }
}

class MassInviteManager
{
    private const ALLOWED_TYPES  = ['+', '-'];
    private const DEFAULT_AMOUNT = 5;
    private const MAX_AMOUNT     = 10000;

    public function __construct(private object $db, private string $currentScript) {}

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            global $mybb, $lang;
            if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => $lang->massinvite['err_csrf']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (($_POST['preview'] ?? '') === 'yes') {
                $this->previewAffectedUsers();
            } elseif (($_POST['doit'] ?? '') === 'yes') {
                $this->processMassInvite();
            }
        }
        $this->renderForm();
    }

    private function sanitizeType(string $type): string
    {
        return in_array($type, self::ALLOWED_TYPES, true) ? $type : '+';
    }

    private function sanitizeUserGroup(string $userGroup): int
    {
        $userGroup = trim($userGroup);
        return ($userGroup === '-' || !ctype_digit($userGroup)) ? 0 : (int)$userGroup;
    }

    private function amountFromPost(): int
    {
        return min(self::MAX_AMOUNT, abs((int)($_POST['amount'] ?? self::DEFAULT_AMOUNT)));
    }

    /**
     * Условие + параметры. Раньше номер группы подставлялся прямо в SQL-строку.
     * В режиме «−» затрагиваются только те, у кого есть хотя бы один инвайт.
     */
    private function where(int $group, string $type): array
    {
        $sql = "enabled = 'yes' AND ustatus = 'confirmed'";
        $par = [];
        if ($group > 0) { $sql .= " AND usergroup = ?"; $par[] = $group; }
        if ($type === '-') { $sql .= " AND invites > 0"; }
        return [$sql, $par];
    }

    private function count(int $group, string $type): array
    {
        [$w, $p] = $this->where($group, $type);
        $res = $this->db->sql_query_prepared("SELECT COUNT(id) AS total, COALESCE(SUM(invites),0) AS inv FROM users WHERE {$w}", $p);
        $row = $res ? $this->db->fetch_array($res) : null;
        return [(int)($row['total'] ?? 0), (int)($row['inv'] ?? 0)];
    }

    private function previewAffectedUsers(): void
    {
        $amount = $this->amountFromPost();
        $type   = $this->sanitizeType((string)($_POST['type'] ?? '+'));
        [$count, $inv] = $this->count($this->sanitizeUserGroup((string)($_POST['usergroup'] ?? '-')), $type);
        header('Content-Type: application/json; charset=utf-8');
        // для «−» реальное изменение не больше, чем инвайтов у людей на руках
        $change = $type === '+' ? $count * $amount : min($inv, $count * $amount);
        echo json_encode(['count' => $count, 'change' => $change]);
        exit;
    }

    private function processMassInvite(): void
    {
        global $CURUSER, $lang;

        $amount = $this->amountFromPost();
        $type   = $this->sanitizeType((string)($_POST['type'] ?? '+'));
        $group  = $this->sanitizeUserGroup((string)($_POST['usergroup'] ?? '-'));

        if ($amount < 1) {
            stderr($lang->massinvite['err_title'], ags_fmt($lang->massinvite['err_amount'], self::MAX_AMOUNT));
        }

        // Считаем ДО изменения. Раньше счётчик брался ПОСЛЕ UPDATE с тем же условием
        // «invites >= N» — при вычитании у кого стало меньше N, те в итог уже не попадали
        [$count] = $this->count($group, $type);
        [$w, $p] = $this->where($group, $type);

        // Раньше при «−» пользователи, у которых инвайтов меньше N, пропускались целиком
        // (у них оставались все инвайты). Теперь у них просто обнуление, без минуса.
        $set = $type === '+' ? 'invites = invites + ?' : 'invites = GREATEST(CAST(invites AS SIGNED) - ?, 0)';
        $this->db->sql_query_prepared("UPDATE users SET {$set} WHERE {$w}", [$amount, ...$p]);

        $groupName = $group > 0 ? $this->groupName($group) : 'all groups';
        write_log(sprintf('[MASS INVITE] %s %s %d invite(s) %s %s — %d user(s)',
            $CURUSER['username'] ?? 'Unknown', $type === '+' ? 'added' : 'removed', $amount,
            $type === '+' ? 'to' : 'from', $groupName, $count));

        $_SESSION['mi_done'] = ['type' => $type, 'amount' => $amount, 'count' => $count, 'group' => $groupName, 'gid' => $group];
        function_exists('admin_redirect') ? admin_redirect($this->currentScript) : header('Location: ' . $this->currentScript);
        exit;
    }

    private function groupName(int $gid): string
    {
        $n = function_exists('get_user_class_name') ? (string)get_user_class_name((string)$gid) : '';
        return $n !== '' ? $n : 'Group ' . $gid;
    }

    /** Имя группы для вывода на странице (в лог идёт groupName() — на английском) */
    private function displayGroupName(int $gid): string
    {
        global $lang;
        if ($gid <= 0) {
            return $lang->massinvite['lbl_all_groups'];
        }
        $n = function_exists('get_user_class_name') ? (string)get_user_class_name((string)$gid) : '';
        return $n !== '' ? $n : ags_fmt($lang->massinvite['lbl_group_n'], $gid);
    }

    private function stats(): array
    {
        $r = $this->db->sql_query_prepared("SELECT COALESCE(SUM(invites),0) AS total, COALESCE(SUM(invites > 0),0) AS holders, COALESCE(MAX(invites),0) AS top, COUNT(*) AS users FROM users WHERE enabled = 'yes' AND ustatus = 'confirmed'");
        $a = $r ? $this->db->fetch_array($r) : [];
        return ['total' => (int)($a['total'] ?? 0), 'holders' => (int)($a['holders'] ?? 0), 'top' => (int)($a['top'] ?? 0), 'users' => (int)($a['users'] ?? 0)];
    }

    private function renderForm(): void
    {
        global $BASEURL, $mybb, $lang;
        $L = $lang->massinvite;

        $done = $_SESSION['mi_done'] ?? null;
        unset($_SESSION['mi_done']);
        $st = $this->stats();

        stdhead($L['title']);
        $selectBox = _selectbox_('', 'usergroup');
        $self = htmlspecialchars($this->currentScript, ENT_QUOTES);
        $h = static fn(string $k): string => htmlspecialchars($L[$k], ENT_QUOTES);

        $jsLang = [];
        foreach ($L as $k => $v) {
            if (str_starts_with((string)$k, 'js_')) {
                $jsLang[substr((string)$k, 3)] = $v;
            }
        }
        ?>
<style>
.mi { font-size: 1.055rem; }
.mi .mi-card { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color-translucent); border-radius: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.mi .mi-head { display: flex; flex-wrap: wrap; align-items: center; gap: .9rem; padding: 1.1rem 1.25rem; }
.mi .mi-head-icon, .mi .mi-kpi-icon, .mi .mi-sec-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mi .mi-head-icon { width: 50px; height: 50px; font-size: 1.4rem; border-radius: .9rem; color: #7c3aed; background: rgba(124,58,237,.12); }
.mi .mi-title { font-size: 1.48rem; font-weight: 700; margin: 0; }
.mi .mi-sub { color: var(--bs-secondary-color); font-size: 1rem; }
.mi .mi-muted { font-size: .9rem; color: var(--bs-secondary-color); }
.mi .ic-blue   { color: var(--bs-primary); background: rgba(var(--bs-primary-rgb),.12); }
.mi .ic-green  { color: #16a34a; background: rgba(34,197,94,.12); }
.mi .ic-amber  { color: #d97706; background: rgba(245,158,11,.14); }
.mi .ic-purple { color: #7c3aed; background: rgba(124,58,237,.12); }
.mi .ic-slate  { color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); }
.mi .btn { border-radius: 50rem; }
.mi .btn:not(.btn-sm) { font-size: 1.055rem; }
.mi .form-control, .mi .form-select, .mi .input-group-text { font-size: 1.055rem; }
.mi .form-control, .mi .form-select { border-radius: .7rem; }
.mi .input-group > :first-child { border-top-left-radius: .7rem !important; border-bottom-left-radius: .7rem !important; }
.mi .input-group > :last-child { border-top-right-radius: .7rem !important; border-bottom-right-radius: .7rem !important; }
.mi .input-group-text { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); }
.mi .form-label { font-weight: 600; }
.mi .form-label > i { width: 1.25rem; color: var(--bs-secondary-color); }

.mi .mi-kpi { display: flex; align-items: center; gap: .85rem; padding: 1rem 1.15rem; height: 100%; }
.mi .mi-kpi-icon { width: 46px; height: 46px; border-radius: .85rem; font-size: 1.2rem; }
.mi .mi-kpi-label { font-size: .84rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--bs-secondary-color); }
.mi .mi-kpi-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }

.mi .mi-sec-head { display: flex; align-items: center; gap: .7rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--bs-border-color-translucent); }
.mi .mi-sec-icon { width: 40px; height: 40px; border-radius: .75rem; font-size: 1.05rem; }
.mi .mi-sec-title { font-weight: 700; font-size: 1.12rem; margin: 0; }

/* Выбор операции — две большие карточки (radio прежние: name="type", id="type-add/remove") */
.mi .mi-op { display: flex; align-items: center; gap: .8rem; padding: .85rem 1rem; border-radius: .9rem; border: 2px solid var(--bs-border-color-translucent); cursor: pointer; height: 100%; transition: border-color .15s ease, background-color .15s ease; }
.mi .mi-op input { position: absolute; opacity: 0; pointer-events: none; }
.mi .mi-op .mi-sec-icon { width: 38px; height: 38px; }
.mi .mi-op.add:has(input:checked) { border-color: #16a34a; background: rgba(34,197,94,.06); }
.mi .mi-op.rem:has(input:checked) { border-color: #dc2626; background: rgba(239,68,68,.05); }
.mi .mi-chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .5rem; }
.mi .mi-chip { padding: .25rem .8rem; border-radius: 50rem; border: 1px solid var(--bs-border-color); background: var(--bs-body-bg); font-size: .9rem; font-weight: 600; color: var(--bs-body-color); }
.mi .mi-chip:hover { border-color: rgba(var(--bs-primary-rgb), .5); color: var(--bs-primary); }

/* Превью (id прежние — их обновляет massinvite.js) */
.mi #previewBox { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.15rem; border-radius: 1rem; border: 1px dashed var(--bs-border-color); background: var(--bs-tertiary-bg); margin: 0; }
.mi #previewBox.d-none { display: none !important; }
.mi #previewCount { font-size: 1.1rem; }
.mi #estimatedChange { font-size: 1.35rem; font-weight: 700; }
.mi .mi-savebar { position: sticky; bottom: .75rem; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; margin-top: 1rem; box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08); }
.mi .mi-done { display: flex; align-items: center; gap: .9rem; padding: .9rem 1.1rem; border-radius: 1rem; border: 1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.07); }
.mi .mi-done > i { font-size: 1.4rem; color: #16a34a; }
</style>

<div class="container mt-3 mb-4 mi">

    <div class="mi-card mb-3"><div class="mi-head">
        <span class="mi-head-icon"><i class="fa-solid fa-ticket"></i></span>
        <div style="min-width:0">
            <h1 class="mi-title"><?= $h('title') ?></h1>
            <div class="mi-sub"><?= $h('subtitle') ?></div>
        </div>
    </div></div>

    <?php if ($done): ?>
    <!-- Раньше: stdok() на отдельной странице; теперь итог прямо здесь -->
    <div class="mi-done mb-3"><i class="fa-solid fa-circle-check"></i>
        <?php $doneGroup = isset($done['gid']) ? $this->displayGroupName((int)$done['gid']) : (string)$done['group']; ?>
        <div><div class="fw-bold"><?= htmlspecialchars(ags_fmt($L[$done['type'] === '+' ? 'flash_added' : 'flash_removed'], (int)$done['amount'], $doneGroup), ENT_QUOTES) ?></div>
        <div class="mi-muted"><?= htmlspecialchars(ags_fmt($L['flash_affected'], number_format((int)$done['count'])), ENT_QUOTES) ?></div></div></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-ticket',       'ic-purple', $L['kpi_total'], number_format($st['total'])],
            ['fa-users',        'ic-blue',   $L['kpi_holders'], number_format($st['holders']) . ' <small class="mi-muted">/ ' . number_format($st['users']) . '</small>'],
            ['fa-scale-balanced','ic-green', $L['kpi_average'], $st['users'] ? number_format($st['total'] / $st['users'], 1) : '0'],
            ['fa-trophy',       'ic-amber',  $L['kpi_top'], number_format($st['top'])],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="mi-card mi-kpi"><span class="mi-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="mi-kpi-label"><?= htmlspecialchars($label, ENT_QUOTES) ?></div><div class="mi-kpi-value"><?= $val ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <form action="<?= $self ?>" method="post" id="massInviteForm">
        <input type="hidden" name="doit" value="yes">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars((string)($mybb->post_code ?? ''), ENT_QUOTES) ?>">

        <div class="mi-card">
            <div class="mi-sec-head"><span class="mi-sec-icon ic-purple"><i class="fa-solid fa-sliders"></i></span>
                <div><h2 class="mi-sec-title"><?= $h('sec_operation') ?></h2><div class="mi-muted"><?= $h('sec_operation_sub') ?></div></div></div>
            <div class="p-3 p-md-4">
                <div class="row g-3 mb-3" id="type-buttons">
                    <div class="col-md-6">
                        <label class="mi-op add" for="type-add">
                            <input type="radio" name="type" id="type-add" value="+" checked>
                            <span class="mi-sec-icon ic-green"><i class="fa-solid fa-plus"></i></span>
                            <span><b class="d-block"><?= $h('opt_add') ?></b><small class="mi-muted"><?= $h('opt_add_hint') ?></small></span>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="mi-op rem" for="type-remove">
                            <input type="radio" name="type" id="type-remove" value="-">
                            <span class="mi-sec-icon" style="color:#dc2626;background:rgba(239,68,68,.12)"><i class="fa-solid fa-minus"></i></span>
                            <span><b class="d-block"><?= $h('opt_remove') ?></b><small class="mi-muted"><?= $h('opt_remove_hint') ?></small></span>
                        </label>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="amount" class="form-label"><i class="fa-solid fa-hashtag"></i><?= $h('lbl_amount') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-ticket"></i></span>
                            <input type="number" id="amount" name="amount" value="<?= self::DEFAULT_AMOUNT ?>" min="1" max="<?= self::MAX_AMOUNT ?>" class="form-control" required>
                            <span class="input-group-text"><?= $h('lbl_amount_unit') ?></span>
                        </div>
                        <div class="mi-chips">
                            <?php foreach ([1, 2, 5, 10, 25] as $v): ?><button type="button" class="mi-chip" data-v="<?= $v ?>"><?= $v ?></button><?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="usergroup" class="form-label"><i class="fa-solid fa-users"></i><?= $h('lbl_group') ?></label>
                        <div class="mi-group"><?= $selectBox ?></div>
                        <div class="mi-muted mt-1"><i class="fa-solid fa-circle-info me-1"></i><?= $h('hint_group') ?></div>
                    </div>
                </div>

                <div id="previewBox" class="d-none mt-4">
                    <span class="mi-sec-icon ic-blue"><i class="fa-solid fa-calculator"></i></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= $h('lbl_impact') ?></div>
                        <div class="mi-muted"><span class="badge rounded-pill text-bg-primary" id="previewCount">0</span> <?= $h('lbl_affected') ?></div>
                    </div>
                    <div class="text-end"><div class="mi-muted"><?= $h('lbl_total_change') ?></div><div id="estimatedChange"><?= $h('lbl_change_initial') ?></div></div>
                </div>
            </div>
        </div>

        <div class="mi-card mi-savebar">
            <span class="mi-muted"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i><?= $h('hint_bulk') ?></span>
            <div class="d-flex gap-2">
                <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i><?= $h('btn_reset') ?></button>
                <button type="button" id="previewBtn" class="btn btn-outline-primary px-3"><i class="fa-solid fa-eye me-1"></i><?= $h('btn_preview') ?></button>
                <button type="submit" class="btn btn-primary px-4" id="miGo"><i class="fa-solid fa-play me-1"></i><?= $h('btn_apply') ?></button>
            </div>
        </div>
    </form>
</div>

<link rel="stylesheet" href="<?= $BASEURL; ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= $BASEURL; ?>/scripts/sweetalert2.min.js"></script>
<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.massInviteMax = <?= self::MAX_AMOUNT ?>;
window.massInviteScript = <?= json_encode($this->currentScript) ?>;
document.querySelector('.mi .mi-group select')?.classList.add('form-select');
document.querySelectorAll('.mi .mi-chip').forEach(b => b.addEventListener('click', () => {
    const i = document.getElementById('amount'); i.value = b.dataset.v;
    i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true }));
}));
</script>
<script src="<?= $BASEURL; ?>/admin/scripts/massinvite.js?ver=3"></script>
        <?php
        stdfoot();
    }
}

$massInviteManager = new MassInviteManager($db, $_this_script_);
$massInviteManager->handleRequest();