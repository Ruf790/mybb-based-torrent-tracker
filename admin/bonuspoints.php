<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_bonuslog.php';

$lang->load('bonuspoints');

if (!function_exists('ags_fmt')) {
    /** Подстановка {1}, {2}… ($lang->load() превращает их в %1$s, %2$s — поддерживаем оба вида) */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $a) {
            $n = $i + 1;
            $map['{' . $n . '}']    = (string)$a;
            $map['%' . $n . '$s'] = (string)$a;
        }
        return $map ? strtr($str, $map) : $str;
    }
}

final class BonusPointsManager
{
    private const BS_VERSION      = 'v0.8';
    private const ALLOWED_ACTIONS = [
        'showlist', 'edituser', 'updateuser', 'updatebonussystem',
        'updatebonussystemsave', 'adminpanel', 'add', 'add_save', 'resetall', 'reset', 'log'
    ];
    private const LOG_PER_PAGE = 50;
    /** Период => ключ ланга */
    private const LOG_PERIODS  = ['1' => 'opt_period_today', '7' => 'opt_period_7', '30' => 'opt_period_30', '90' => 'opt_period_90', 'all' => 'opt_period_all'];
    private const UNITS = ['GB' => 1073741824, 'MB' => 1048576, 'TB' => 1099511627776, 'B' => 1];

    private string $script;
    private static bool $assets = false;

    public function __construct(
        private object $db,
        private string $baseUrl,
        private int    $perPage = 20
    ) {
        $this->script = $_SERVER['SCRIPT_NAME'];
    }

    // ── Router ───────────────────────────────────────────────

    public function handleRequest(): void
    {
        global $mybb;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
                http_response_code(403);
                stderr($this->t('err_security_title'), $this->t('err_security_text'));
            }
        }

        match ($this->getValidatedAction()) {
            'showlist'              => $this->showUserList(),
            'edituser'              => $this->editUser(),
            'updateuser'            => $this->updateUser(),
            'updatebonussystem'     => $this->updateBonusSystem(),
            'updatebonussystemsave' => $this->updateBonusSystemSave(),
            'add'                   => $this->addBonus(),
            'add_save'              => $this->addBonusSave(),
            'resetall'              => $this->resetAllPoints(),
            'reset'                 => $this->resetPointsForm(),
            'log'                   => $this->showLog(),
            default                 => $this->showAdminPanel(),
        };
    }

    private function getValidatedAction(): string
    {
        $action = (string)($_POST['action'] ?? $_GET['action'] ?? 'adminpanel');
        return in_array($action, self::ALLOWED_ACTIONS, true) ? $action : 'adminpanel';
    }

    // ── Helpers ──────────────────────────────────────────────

    private function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

    /** Строка из ланга с подстановкой {1}, {2}… */
    private function t(string $key, string|int|float ...$args): string
    {
        global $lang;
        return ags_fmt($lang->bonuspoints[$key], ...$args);
    }
    private function url(string $action = '', array $q = []): string
    {
        $u = $this->script . '?act=bonuspoints' . ($action !== '' ? '&amp;action=' . $action : '');
        foreach ($q as $k => $v) $u .= '&amp;' . $k . '=' . rawurlencode((string)$v);
        return $u;
    }
    private function pts(mixed $v): string
    {
        $f = (float)$v;
        return number_format($f, fmod($f, 1.0) === 0.0 ? 0 : 1);
    }

    private function getValidatedUserId(): int
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        return is_valid_id($id) ? $id : 0;
    }
    private function getValidatedBonusId(): int  { return max(0, (int)($_POST['id'] ?? $_GET['id'] ?? 0)); }
    private function getValidatedUserGroup(): int { return max(0, (int)($_POST['usergroup'] ?? $_GET['usergroup'] ?? 0)); }

    private function getUserById(int $id): ?array
    {
        $res = $this->db->sql_query_prepared("SELECT id, username, usergroup, seedbonus, uploaded, downloaded, avatar, avatardimensions FROM users WHERE id = ?", [$id]);
        return ($res && $this->db->num_rows($res) > 0) ? $this->db->fetch_array($res) : null;
    }
    private function getBonusById(int $id): ?array
    {
        $res = $this->db->sql_query_prepared("SELECT * FROM bonus WHERE id = ?", [$id]);
        return ($res && $this->db->num_rows($res) > 0) ? $this->db->fetch_array($res) : null;
    }

    /** Название группы. (string): в strict_types get_user_class_name(string) с int давала TypeError.
     *  $forLog: запасное «Group N» для логов остаётся на английском */
    private function groupName(int $gid, bool $forLog = false): string
    {
        $n = function_exists('get_user_class_name') ? (string)get_user_class_name((string)$gid) : '';
        return $n !== '' ? $n : ($forLog ? 'Group ' . $gid : $this->t('lbl_group_fallback', $gid));
    }

    /** Трафик из «число + единица». Раньше количество вводилось только в байтах */
    private function mengeFromPost(): int
    {
        if (isset($_POST['menge_value'])) {
            $mul = self::UNITS[strtoupper((string)($_POST['menge_unit'] ?? 'GB'))] ?? 1;
            return (int)round(max(0.0, (float)$_POST['menge_value']) * $mul);
        }
        return max(0, (int)($_POST['menge'] ?? 0));
    }

    private function stats(): array
    {
        $r = $this->db->sql_query_prepared("SELECT COUNT(*) AS users, COALESCE(SUM(seedbonus),0) AS total, COALESCE(MAX(seedbonus),0) AS top FROM users WHERE seedbonus > 0");
        $a = $r ? $this->db->fetch_array($r) : [];
        $r = $this->db->sql_query_prepared("SELECT COUNT(*) AS n FROM bonus");
        $b = $r ? $this->db->fetch_array($r) : [];
        return ['users' => (int)($a['users'] ?? 0), 'total' => (float)($a['total'] ?? 0), 'top' => (float)($a['top'] ?? 0), 'items' => (int)($b['n'] ?? 0)];
    }

    // ── Layout ───────────────────────────────────────────────

    private function page(string $title, string $active, string $body): void
    {
        stdhead($this->t('page_title', self::BS_VERSION, $title));
        $this->assets();
        $st = $this->stats();

        $tabs = [
            'adminpanel' => ['fa-store',            $this->t('tab_items')],
            'add'        => ['fa-circle-plus',      $this->t('tab_add')],
            'showlist'   => ['fa-users',            $this->t('tab_users')],
            'log'        => ['fa-clock-rotate-left', $this->t('tab_log')],
            'reset'      => ['fa-rotate-left',      $this->t('tab_reset')],
        ];
        $nav = '<nav class="bp-tabs">';
        foreach ($tabs as $k => [$ic, $label]) {
            $nav .= '<a href="' . ($k === 'adminpanel' ? $this->url() : $this->url($k)) . '" class="' . ($k === $active ? 'active' : '') . '"><i class="fa-solid ' . $ic . '"></i>' . $label . '</a>';
        }
        $nav .= '</nav>';

        echo '<div class="container mt-3 mb-4 bp">'
           . '<div class="bp-card mb-3"><div class="bp-head">'
           . '<span class="bp-head-icon"><i class="fa-solid fa-coins"></i></span>'
           . '<div class="bp-minw0"><h1 class="bp-title">' . $this->t('head_title') . '</h1><div class="bp-sub">' . $this->t('head_sub') . '</div></div>'
           . '<span class="bp-ver ms-auto"><i class="fa-solid fa-code-branch me-1"></i>' . self::BS_VERSION . '</span>'
           . '</div></div>'
           . '<div class="row g-3 mb-3">';
        foreach ([
            ['fa-store',   'ic-purple', $this->t('kpi_items'),  number_format($st['items'])],
            ['fa-users',   'ic-blue',   $this->t('kpi_users'),  number_format($st['users'])],
            ['fa-coins',   'ic-amber',  $this->t('kpi_total'),  $this->pts($st['total'])],
            ['fa-trophy',  'ic-green',  $this->t('kpi_top'),    $this->pts($st['top'])],
        ] as [$ic, $cls, $label, $val]) {
            echo '<div class="col-6 col-lg-3"><div class="bp-card bp-kpi"><span class="bp-kpi-icon ' . $cls . '"><i class="fa-solid ' . $ic . '"></i></span>'
               . '<div class="bp-minw0"><div class="bp-kpi-label">' . $label . '</div><div class="bp-kpi-value">' . $val . '</div></div></div></div>';
        }
        echo '</div>' . $nav . $body . '</div>';
        stdfoot();
    }

    /** Результат действия — в общем стиле, со ссылками дальше */
    private function result(bool $ok, string $title, string $text, string $active = 'adminpanel'): void
    {
        $this->page($title, $active,
            '<div class="bp-card bp-result ' . ($ok ? 'is-ok' : 'is-bad') . '">'
            . '<span class="bp-result-icon"><i class="fa-solid ' . ($ok ? 'fa-circle-check' : 'fa-circle-xmark') . '"></i></span>'
            . '<h2 class="h4 fw-bold mb-1">' . $this->e($title) . '</h2><div class="text-body-secondary mb-3">' . $text . '</div>'
            . '<div class="d-flex flex-wrap justify-content-center gap-2">'
            . '<a href="' . $this->url() . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-store me-1"></i>' . $this->t('btn_items') . '</a>'
            . '<a href="' . $this->url('showlist') . '" class="btn btn-primary px-3"><i class="fa-solid fa-users me-1"></i>' . $this->t('btn_users') . '</a>'
            . '</div></div>');
    }

    private function confirmPage(string $title, string $text, string $actionUrl, array $hidden, string $button, string $active): void
    {
        global $mybb;
        $h = '<input type="hidden" name="my_post_key" value="' . $this->e($mybb->post_code) . '">';
        foreach ($hidden as $k => $v) $h .= '<input type="hidden" name="' . $this->e($k) . '" value="' . $this->e($v) . '">';
        $this->page($title, $active,
            '<div class="bp-card bp-result is-bad">'
            . '<span class="bp-result-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>'
            . '<h2 class="h4 fw-bold mb-1">' . $this->e($title) . '</h2><div class="text-body-secondary mb-3">' . $text . '</div>'
            . '<form method="post" action="' . $actionUrl . '" class="d-flex flex-wrap justify-content-center gap-2">' . $h
            . '<a href="' . $this->url() . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-xmark me-1"></i>' . $this->t('btn_cancel') . '</a>'
            . '<button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-trash me-1"></i>' . $button . '</button></form></div>');
    }

    private function assets(): void
    {
        if (self::$assets) return;
        self::$assets = true;
        $rel = '/admin/templates/bonuspoints.css';
        $ver = @filemtime(TSDIR . $rel) ?: self::BS_VERSION;
        echo '<link rel="stylesheet" href="' . $this->baseUrl . $rel . '?v=' . $this->e($ver) . '">';
    }

    /** js_* ключи ланга → массив без префикса для const AGS_LANG */
    private function jsLang(): string
    {
        global $lang;
        $arr = [];
        foreach ($lang->bonuspoints as $k => $v) {
            if (str_starts_with((string)$k, 'js_')) $arr[substr((string)$k, 3)] = $v;
        }
        return (string)json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    // ── Shop items ───────────────────────────────────────────

    private function showAdminPanel(): void
    {
        $res   = $this->db->sql_query_prepared('SELECT * FROM bonus ORDER BY points ASC, id ASC');
        $rows  = '';
        $count = 0;
        while ($res && ($b = $this->db->fetch_array($res))) {
            $count++;
            $traffic = (string)($b['art'] ?? '') === 'traffic';
            $menge   = (int)($b['menge'] ?? 0);
            $rows .= '<tr>'
                . '<td><div class="d-flex align-items-center gap-3"><span class="bp-ico ' . ($traffic ? 'ic-teal' : 'ic-purple') . '"><i class="fa-solid ' . ($traffic ? 'fa-cloud-arrow-up' : 'fa-gift') . '"></i></span>'
                . '<div class="bp-minw0"><div class="fw-bold">' . $this->e($b['bonusname']) . '</div>'
                . '<div class="bp-comment">' . $this->e($b['description'] ?? '') . '</div></div></div></td>'
                . '<td class="text-nowrap"><span class="bp-tag ' . ($traffic ? 't-traffic' : 't-other') . '">' . $this->e($b['art'] ?? '—') . '</span></td>'
                // раньше количество не показывалось вовсе (или было бы голым числом байт)
                . '<td class="text-nowrap fw-semibold">' . ($menge > 0 ? mksize($menge) : '<span class="bp-muted">—</span>') . '</td>'
                . '<td class="text-nowrap"><span class="bp-pts"><i class="fa-solid fa-coins"></i>' . $this->pts($b['points']) . '</span></td>'
                . '<td class="text-end"><a href="' . $this->url('updatebonussystem', ['id' => (int)$b['id']]) . '" class="bp-act" title="' . $this->e($this->t('title_edit')) . '" aria-label="' . $this->e($this->t('title_edit')) . '"><i class="fa-solid fa-pen"></i></a></td>'
                . '</tr>';
        }

        $body = '<div class="bp-card overflow-hidden"><div class="bp-sec-head">'
              . '<span class="bp-sec-icon ic-purple"><i class="fa-solid fa-store"></i></span>'
              . '<div><h2 class="bp-sec-title">' . $this->t('sec_items') . '</h2><div class="bp-muted">' . $this->t('sub_items', $count) . '</div></div>'
              . '<a href="' . $this->url('add') . '" class="btn btn-sm btn-primary px-3 ms-auto"><i class="fa-solid fa-plus me-1"></i>' . $this->t('btn_add_item') . '</a></div>';
        $body .= $count
            ? '<div class="table-responsive"><table class="table bp-table"><thead><tr>'
              . '<th><i class="fa-solid fa-tag"></i>' . $this->t('th_item') . '</th><th><i class="fa-solid fa-shapes"></i>' . $this->t('th_type') . '</th>'
              . '<th><i class="fa-solid fa-hard-drive"></i>' . $this->t('th_amount') . '</th><th><i class="fa-solid fa-coins"></i>' . $this->t('th_price') . '</th><th></th>'
              . '</tr></thead><tbody>' . $rows . '</tbody></table></div>'
            : '<div class="bp-empty"><i class="fa-solid fa-store-slash"></i><div class="fw-semibold">' . $this->t('empty_items') . '</div></div>';
        $body .= '</div>';

        $this->page($this->t('tab_items'), 'adminpanel', $body);
    }

    private function renderBonusForm(array $data = [], bool $isEdit = false): string
    {
        global $mybb;

        $menge = (int)($data['menge'] ?? 0);
        // Показываем количество в самой крупной «ровной» единице
        [$val, $unit] = [0, 'GB'];
        if ($menge > 0) {
            foreach (['TB', 'GB', 'MB', 'B'] as $u) {
                if ($menge % self::UNITS[$u] === 0 || $u === 'B') { $val = $menge / self::UNITS[$u]; $unit = $u; break; }
            }
            if ($unit === 'B' && $menge >= self::UNITS['MB']) { $val = round($menge / self::UNITS['GB'], 3); $unit = 'GB'; }
        }
        $unitSel = '<select class="form-select bp-unit-select" name="menge_unit">';
        foreach (array_keys(self::UNITS) as $u) $unitSel .= '<option value="' . $u . '"' . ($u === $unit ? ' selected' : '') . '>' . $u . '</option>';
        $unitSel .= '</select>';

        $hidden = '<input type="hidden" name="act" value="bonuspoints">'
                . '<input type="hidden" name="my_post_key" value="' . $this->e($mybb->post_code) . '">'
                . '<input type="hidden" name="action" value="' . ($isEdit ? 'updatebonussystemsave' : 'add_save') . '">'
                . ($isEdit ? '<input type="hidden" name="id" value="' . (int)$data['id'] . '">' : '');

        $chips = '';
        foreach ([1, 2.5, 5, 10, 25, 50] as $gb) $chips .= '<button type="button" class="bp-chip" data-gb="' . $gb . '">' . $gb . ' GB</button>';

        $delete = $isEdit ? '
            <label class="d-flex align-items-center gap-3 p-3 rounded-4 mt-3 bp-danger-zone" for="deleteCheck">
                <span class="bp-sec-icon ic-red"><i class="fa-solid fa-trash"></i></span>
                <span class="flex-grow-1"><b class="d-block text-danger">' . $this->t('lbl_delete_item') . '</b><small class="bp-muted">' . $this->t('hint_delete_item') . '</small></span>
                <input class="form-check-input m-0" type="checkbox" name="delete" value="1" id="deleteCheck">
            </label>' : '';

        return '<form method="post" action="' . $this->script . '" class="bp-card">' . $hidden . '
            <div class="bp-sec-head"><span class="bp-sec-icon ' . ($isEdit ? 'ic-blue' : 'ic-green') . '"><i class="fa-solid ' . ($isEdit ? 'fa-pen-to-square' : 'fa-circle-plus') . '"></i></span>
                <h2 class="bp-sec-title">' . ($isEdit ? $this->t('sec_edit_item', $this->e($data['bonusname'] ?? '')) : $this->t('sec_new_item')) . '</h2></div>
            <div class="p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="bonusname"><i class="fa-solid fa-tag"></i>' . $this->t('lbl_name') . ' <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="bonusname" name="bonusname" value="' . $this->e($data['bonusname'] ?? '') . '" placeholder="' . $this->e($this->t('ph_name')) . '" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="points"><i class="fa-solid fa-coins"></i>' . $this->t('lbl_price') . ' <span class="text-danger">*</span></label>
                        <div class="input-group"><input type="number" class="form-control" id="points" name="points" value="' . $this->e($data['points'] ?? '') . '" min="0" step="0.1" required><span class="input-group-text">' . $this->t('lbl_points_unit') . '</span></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description"><i class="fa-solid fa-align-left"></i>' . $this->t('lbl_description') . ' <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3" required placeholder="' . $this->e($this->t('ph_description')) . '">' . $this->e($data['description'] ?? '') . '</textarea>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="menge_value"><i class="fa-solid fa-hard-drive"></i>' . $this->t('lbl_traffic') . '</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="menge_value" name="menge_value" value="' . ($val ?: '') . '" min="0" step="any" placeholder="0">
                            ' . $unitSel . '
                        </div>
                        <div class="bp-chips">' . $chips . '</div>
                        <div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>' . $this->t('hint_bytes', '<span id="bpBytes">0</span>') . '</div>
                    </div>
                </div>
                ' . $delete . '
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="' . $this->url() . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-xmark me-1"></i>' . $this->t('btn_cancel') . '</a>
                    <button type="submit" class="btn ' . ($isEdit ? 'btn-primary' : 'btn-success') . ' px-4"><i class="fa-solid ' . ($isEdit ? 'fa-floppy-disk' : 'fa-plus') . ' me-1"></i>' . ($isEdit ? $this->t('btn_save_item') : $this->t('btn_add_item')) . '</button>
                </div>
            </div>
        </form>
        <script>
        (function () {
            const v = document.getElementById("menge_value"), u = document.querySelector(".bp [name=menge_unit]"), out = document.getElementById("bpBytes");
            const mul = { B: 1, MB: 1048576, GB: 1073741824, TB: 1099511627776 };
            const calc = () => { out.textContent = Math.round((parseFloat(v.value) || 0) * mul[u.value]).toLocaleString("en-US"); };
            v.addEventListener("input", calc); u.addEventListener("change", calc);
            document.querySelectorAll(".bp .bp-chip[data-gb]").forEach(b => b.addEventListener("click", () => { v.value = b.dataset.gb; u.value = "GB"; calc(); }));
            calc();
        })();
        </script>';
    }

    private function addBonus(): void
    {
        $this->page($this->t('tab_add'), 'add', $this->renderBonusForm());
    }

    private function addBonusSave(): void
    {
        global $CURUSER;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); stderr($this->t('err_title'), $this->t('err_method')); }

        $name = trim((string)($_POST['bonusname'] ?? ''));
        if ($name === '') { $this->result(false, $this->t('res_not_saved'), $this->t('res_need_name'), 'add'); return; }

        $ok = $this->db->sql_query_prepared(
            "INSERT INTO bonus (bonusname, points, description, menge, art) VALUES (?, ?, ?, ?, 'traffic')",
            [$name, (float)($_POST['points'] ?? 0), (string)($_POST['description'] ?? ''), $this->mengeFromPost()]
        );
        if ($ok && function_exists('write_log')) write_log("Bonus shop item \"{$name}\" added by " . ($CURUSER['username'] ?? 'System'));
        $this->result((bool)$ok, $ok ? $this->t('res_added') : $this->t('res_add_failed'), $ok ? $this->t('res_added_text', $this->e($name)) : $this->t('res_db_error'), 'add');
    }

    private function updateBonusSystem(): void
    {
        $bonus = $this->getBonusById($this->getValidatedBonusId());
        if (!$bonus) { $this->result(false, $this->t('res_not_found'), $this->t('res_item_missing')); return; }
        $this->page($this->t('title_edit_item'), 'adminpanel', $this->renderBonusForm($bonus, true));
    }

    private function updateBonusSystemSave(): void
    {
        global $CURUSER;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); stderr($this->t('err_title'), $this->t('err_method')); }

        $id     = $this->getValidatedBonusId();
        $delete = ($_POST['delete'] ?? '') === '1';
        $sure   = (int)($_POST['sure'] ?? 0);
        $bonus  = $this->getBonusById($id);
        if (!$bonus) { $this->result(false, $this->t('res_not_found'), $this->t('res_item_missing')); return; }

        if ($delete && !$sure) {
            $this->confirmPage($this->t('confirm_delete_title'),
                $this->t('confirm_delete_text', $this->e($bonus['bonusname'])),
                $this->url('updatebonussystemsave', ['id' => $id]), ['delete' => '1', 'sure' => '1', 'id' => $id], $this->t('btn_yes_delete'), 'adminpanel');
            return;
        }
        if ($delete && $sure) {
            $ok = $this->db->sql_query_prepared("DELETE FROM bonus WHERE id = ?", [$id]);
            if ($ok && function_exists('write_log')) write_log("Bonus shop item \"{$bonus['bonusname']}\" deleted by " . ($CURUSER['username'] ?? 'System'));
            $this->result((bool)$ok, $ok ? $this->t('res_deleted') : $this->t('res_delete_failed'), $ok ? $this->t('res_deleted_text', $this->e($bonus['bonusname'])) : $this->t('res_db_error'));
            return;
        }

        $ok = $this->db->sql_query_prepared(
            "UPDATE bonus SET bonusname = ?, points = ?, description = ?, menge = ? WHERE id = ?",
            [trim((string)($_POST['bonusname'] ?? '')), (float)($_POST['points'] ?? 0), (string)($_POST['description'] ?? ''), $this->mengeFromPost(), $id]
        );
        $this->result((bool)$ok, $ok ? $this->t('res_saved') : $this->t('res_save_failed'), $ok ? $this->t('res_saved_text', $this->e($_POST['bonusname'] ?? '')) : $this->t('res_db_error'));
    }

    // ── Users ────────────────────────────────────────────────

    private function showUserList(): void
    {
        $q     = trim((string)($_GET['q'] ?? ''));
        $where = 'seedbonus > 0';
        $par   = [];
        if ($q !== '') { $where .= ' AND username LIKE ?'; $par[] = '%' . addcslashes($q, '%_\\') . '%'; }

        $cnt   = $this->db->sql_query_prepared("SELECT COUNT(*) AS total FROM users WHERE {$where}", $par);
        $total = $cnt ? (int)($this->db->fetch_array($cnt)['total'] ?? 0) : 0;
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $start = ($page - 1) * $this->perPage;

        $res = $this->db->sql_query_prepared(
            "SELECT id, username, usergroup, seedbonus, uploaded, avatar, avatardimensions
             FROM users WHERE {$where} ORDER BY seedbonus DESC LIMIT ?, ?",
            [...$par, $start, $this->perPage]
        );

        $rows = '';
        $rank = $start;
        while ($res && ($u = $this->db->fetch_array($res))) {
            $rank++;
            $name = (string)$u['username'];
            $av   = function_exists('format_avatar') ? format_avatar($u['avatar'] ?? '', $u['avatardimensions'] ?? '') : [];
            $pic  = (!empty($av['image']) && empty($av['is_placeholder']))
                ? '<img class="bp-avatar" src="' . $av['image'] . '" alt="" loading="lazy">'
                : '<span class="bp-initial">' . $this->e(mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1))) . '</span>';
            $rows .= '<tr>'
                . '<td class="text-center bp-muted fw-bold">' . ($rank <= 3 ? ['', '🥇', '🥈', '🥉'][$rank] : '#' . $rank) . '</td>'
                . '<td><div class="d-flex align-items-center gap-2">' . $pic . '<div class="bp-minw0">'
                . '<a href="' . $this->baseUrl . '/' . get_profile_link((int)$u['id']) . '" class="fw-semibold text-decoration-none">' . (function_exists('format_name') ? format_name($this->e($name), (int)$u['usergroup']) : $this->e($name)) . '</a>'
                . '<div class="bp-muted">' . $this->t('lbl_id', (int)$u['id']) . '</div></div></div></td>'
                . '<td class="text-nowrap"><span class="bp-pts"><i class="fa-solid fa-coins"></i>' . $this->pts($u['seedbonus']) . '</span></td>'
                . '<td class="text-nowrap"><i class="fa-solid fa-upload text-body-secondary me-1"></i>' . mksize((float)$u['uploaded']) . '</td>'
                . '<td class="text-end text-nowrap">'
                . '<a href="' . $this->url('log', ['user' => '#' . (int)$u['id'], 'period' => 'all']) . '" class="bp-act" title="' . $this->e($this->t('title_history')) . '" aria-label="' . $this->e($this->t('title_history')) . '"><i class="fa-solid fa-clock-rotate-left"></i></a> '
                . '<a href="' . $this->url('edituser', ['id' => (int)$u['id']]) . '" class="bp-act" title="' . $this->e($this->t('title_edit_balance')) . '" aria-label="' . $this->e($this->t('title_edit_balance')) . '"><i class="fa-solid fa-pen"></i></a></td>'
                . '</tr>';
        }

        $pageUrl = $this->script . '?act=bonuspoints&action=showlist' . ($q !== '' ? '&q=' . rawurlencode($q) : '') . '&';
        $pager   = $total > $this->perPage ? '<div class="d-flex justify-content-center py-2 border-top">' . multipage($total, $this->perPage, $page, $pageUrl) . '</div>' : '';

        $body = '<div class="bp-card overflow-hidden"><div class="bp-sec-head">'
              . '<span class="bp-sec-icon ic-blue"><i class="fa-solid fa-ranking-star"></i></span>'
              . '<div><h2 class="bp-sec-title">' . $this->t('sec_users') . '</h2><div class="bp-muted">' . $this->t('sub_users', number_format($total)) . '</div></div>'
              . '<form method="get" action="' . $this->script . '" class="ms-auto position-relative bp-search">'
              . '<input type="hidden" name="act" value="bonuspoints"><input type="hidden" name="action" value="showlist">'
              . '<i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="' . $this->e($q) . '" class="form-control form-control-sm" placeholder="' . $this->e($this->t('ph_find_user')) . '" aria-label="' . $this->e($this->t('ph_find_user')) . '"></form></div>';
        $body .= $rows
            ? '<div class="table-responsive"><table class="table bp-table"><thead><tr>'
              . '<th class="text-center">#</th><th><i class="fa-solid fa-user"></i>' . $this->t('th_user') . '</th><th><i class="fa-solid fa-coins"></i>' . $this->t('th_points') . '</th>'
              . '<th><i class="fa-solid fa-upload"></i>' . $this->t('th_uploaded') . '</th><th></th>'
              . '</tr></thead><tbody>' . $rows . '</tbody></table></div>' . $pager
            : '<div class="bp-empty"><i class="fa-solid fa-user-slash"></i><div class="fw-semibold">' . ($q !== '' ? $this->t('empty_users_search', $this->e($q)) : $this->t('empty_users')) . '</div></div>';
        $body .= '</div>';

        $this->page($this->t('tab_users'), 'showlist', $body);
    }

    private function editUser(): void
    {
        global $mybb;
        $user = $this->getUserById($this->getValidatedUserId());
        if (!$user) { $this->result(false, $this->t('res_not_found'), $this->t('res_user_missing'), 'showlist'); return; }

        $name = (string)$user['username'];
        $cur  = (float)$user['seedbonus'];
        // Раньше ссылка вела на старый userdetails.php
        $link = $this->baseUrl . '/' . get_profile_link((int)$user['id']);

        $body = '<form method="post" action="' . $this->script . '" class="bp-card bp-narrow">
            <input type="hidden" name="act" value="bonuspoints">
            <input type="hidden" name="my_post_key" value="' . $this->e($mybb->post_code) . '">
            <input type="hidden" name="action" value="updateuser">
            <input type="hidden" name="id" value="' . (int)$user['id'] . '">
            <div class="bp-sec-head"><span class="bp-sec-icon ic-blue"><i class="fa-solid fa-user-pen"></i></span>
                <div><h2 class="bp-sec-title">' . $this->t('sec_edit_balance') . '</h2><div class="bp-muted"><a href="' . $link . '" target="_blank">' . $this->e($name) . '</a> · ' . $this->t('lbl_id', (int)$user['id']) . '</div></div>
                <a href="' . $this->url('log', ['user' => '#' . (int)$user['id'], 'period' => 'all']) . '" class="ms-auto bp-act" title="' . $this->e($this->t('title_history')) . '" aria-label="' . $this->e($this->t('title_history')) . '"><i class="fa-solid fa-clock-rotate-left"></i></a>
                <span class="bp-pts"><i class="fa-solid fa-coins"></i>' . $this->t('lbl_balance_now', $this->pts($cur)) . '</span></div>
            <div class="p-3 p-md-4">
                <label class="form-label" for="seedbonus"><i class="fa-solid fa-coins"></i>' . $this->t('lbl_new_balance') . '</label>
                <div class="input-group"><input type="number" class="form-control form-control-lg" id="seedbonus" name="seedbonus" value="' . $this->e($cur) . '" min="0" step="0.1" required><span class="input-group-text">' . $this->t('lbl_points_unit') . '</span></div>
                <div class="bp-chips">
                    <button type="button" class="bp-chip" data-d="100">+100</button><button type="button" class="bp-chip" data-d="1000">+1 000</button>
                    <button type="button" class="bp-chip" data-d="-100">−100</button><button type="button" class="bp-chip" data-d="-1000">−1 000</button>
                    <button type="button" class="bp-chip" data-set="0">' . $this->t('btn_set_zero') . '</button>
                </div>
                <div class="form-text" id="bpDiff"></div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="' . $this->url('showlist') . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-xmark me-1"></i>' . $this->t('btn_cancel') . '</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i>' . $this->t('btn_save_balance') . '</button>
                </div>
            </div>
        </form>
        <script>
        const AGS_LANG = ' . $this->jsLang() . ';
        (function () {
            /** Перевод с английским fallback; {1} и %1$s (так их отдаёт $lang->load()) */
            const t = (k, fb, ...a) => {
                let s = (typeof AGS_LANG === "object" && AGS_LANG !== null && typeof AGS_LANG[k] === "string") ? AGS_LANG[k] : fb;
                s = s.replace(/%(\d+)\$s/g, "{$1}");
                a.forEach((v, n) => { s = s.split("{" + (n + 1) + "}").join(String(v)); });
                return s;
            };
            const i = document.getElementById("seedbonus"), d = document.getElementById("bpDiff"), cur = ' . json_encode($cur) . ';
            const show = () => { const v = parseFloat(i.value) || 0, diff = v - cur;
                d.textContent = "";
                if (diff === 0) { d.textContent = t("no_change", "No change"); return; }
                const sp = document.createElement("span");
                sp.className = diff > 0 ? "text-success" : "text-danger";
                sp.textContent = t("diff_points", "{1} points", (diff > 0 ? "+" : "") + diff.toLocaleString());
                const parts = t("diff_vs_now", "{1} compared to now").replace(/%1\$s/g, "{1}").split("{1}");
                d.append(document.createTextNode(parts[0]), sp, document.createTextNode(parts.slice(1).join(""))); };
            document.querySelectorAll(".bp .bp-chip").forEach(b => b.addEventListener("click", () => {
                i.value = b.dataset.set !== undefined ? b.dataset.set : Math.max(0, (parseFloat(i.value) || 0) + parseFloat(b.dataset.d)); show();
            }));
            i.addEventListener("input", show); show();
        })();
        </script>';

        $this->page($this->t('sec_edit_balance'), 'showlist', $body);
    }

    private function updateUser(): void
    {
        global $CURUSER;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); stderr($this->t('err_title'), $this->t('err_method')); }

        $id    = $this->getValidatedUserId();
        $user  = $this->getUserById($id);
        if (!$user) { $this->result(false, $this->t('res_not_found'), $this->t('res_user_missing'), 'showlist'); return; }
        $bonus = max(0.0, (float)($_POST['seedbonus'] ?? 0));
        $ok    = $this->db->sql_query_prepared("UPDATE users SET seedbonus = ? WHERE id = ?", [$bonus, $id]);
        $old   = (float)$user['seedbonus'];
        if ($ok && abs($bonus - $old) >= 0.01) {
            bonus_log($id, $bonus - $old, 'staff',
                'Balance set by staff: ' . $this->pts($old) . ' → ' . $this->pts($bonus), null, (int)($CURUSER['id'] ?? 0) ?: null);
        }
        // Раньше изменение баланса нигде не фиксировалось
        if ($ok && function_exists('write_log')) {
            write_log("Bonus balance of {$user['username']} changed from {$this->pts($user['seedbonus'])} to {$this->pts($bonus)} by " . ($CURUSER['username'] ?? 'System'));
        }
        $this->result((bool)$ok, $ok ? $this->t('res_balance_updated') : $this->t('res_update_failed'),
            $ok ? $this->t('res_balance_text', $this->e($user['username']), $this->pts($user['seedbonus']), $this->pts($bonus)) : $this->t('res_db_error'), 'showlist');
    }

    // ── Bonus log ────────────────────────────────────────────

    private function showLog(): void
    {
        $userIn = trim((string)($_GET['user'] ?? ''));
        $type   = (string)($_GET['type'] ?? '');
        $type   = isset(BONUS_LOG_TYPES[$type]) ? $type : '';
        $period = (string)($_GET['period'] ?? '30');
        $period = isset(self::LOG_PERIODS[$period]) ? $period : '30';
        $since  = $period === 'all' ? null : ($period === '1' ? (int)strtotime('today', TIMENOW) : TIMENOW - (int)$period * 86400);
        $page   = max(1, (int)($_GET['page'] ?? 1));

        $uid = null;
        $err = '';
        if ($userIn !== '') {
            $r = preg_match('/^#?(\d+)$/', $userIn, $m)
                ? $this->db->sql_query_prepared('SELECT id FROM users WHERE id = ? OR username = ? LIMIT 1', [(int)$m[1], $userIn])
                : $this->db->sql_query_prepared('SELECT id FROM users WHERE username = ? LIMIT 1', [$userIn]);
            $row = $r ? $this->db->fetch_array($r) : null;
            if ($row) $uid = (int)$row['id']; else $err = $this->t('res_user_missing');
        }

        $tot  = $err === '' ? bonus_log_totals($uid, $type ?: null, $since) : ['count' => 0, 'plus' => 0.0, 'minus' => 0.0];
        $rows = $err === '' ? bonus_log_fetch($uid, $type ?: null, self::LOG_PER_PAGE, ($page - 1) * self::LOG_PER_PAGE, $since) : [];
        $net  = $tot['plus'] - $tot['minus'];
        $amt  = static fn(float $v): string => abs($v) >= 0.01 ? bonus_log_amount($v) : '0';

        // Filters
        $typeOpts = '<option value="">' . $this->e($this->t('opt_all_types')) . '</option>';
        foreach (BONUS_LOG_TYPES as $k => [$label]) {
            $typeOpts .= '<option value="' . $k . '"' . ($type === $k ? ' selected' : '') . '>' . $this->e($label) . '</option>';
        }
        $perOpts = '';
        foreach (self::LOG_PERIODS as $k => $labelKey) {
            $perOpts .= '<option value="' . $k . '"' . ($period === (string)$k ? ' selected' : '') . '>' . $this->e($this->t($labelKey)) . '</option>';
        }

        $body = '<div class="bp-card overflow-hidden"><div class="bp-sec-head">'
              . '<span class="bp-sec-icon ic-amber"><i class="fa-solid fa-clock-rotate-left"></i></span>'
              . '<div><h2 class="bp-sec-title">' . $this->t('sec_log') . '</h2><div class="bp-muted">'
              . $this->t($tot['count'] === 1 ? 'log_entries_one' : 'log_entries', number_format($tot['count']))
              . ' &middot; <span class="text-success">' . $amt($tot['plus']) . '</span>'
              . ' &middot; <span class="text-danger">' . ($tot['minus'] > 0 ? $amt(-$tot['minus']) : '0') . '</span>'
              . ' &middot; ' . $this->t('log_net', $amt($net)) . '</div></div></div>'
              . '<form method="get" action="' . $this->script . '" class="d-flex flex-wrap gap-2 align-items-end px-3 pb-3">'
              . '<input type="hidden" name="act" value="bonuspoints"><input type="hidden" name="action" value="log">'
              . '<div class="flex-grow-1" style="min-width:11rem"><label class="form-label small mb-1">' . $this->t('lbl_user') . ' <span class="bp-muted">' . $this->t('hint_user') . '</span></label>'
              . '<input name="user" value="' . $this->e($userIn) . '" class="form-control form-control-sm" placeholder="' . $this->e($this->t('ph_everyone')) . '"></div>'
              . '<div><label class="form-label small mb-1">' . $this->t('lbl_type') . '</label><select name="type" class="form-select form-select-sm">' . $typeOpts . '</select></div>'
              . '<div><label class="form-label small mb-1">' . $this->t('lbl_period') . '</label><select name="period" class="form-select form-select-sm">' . $perOpts . '</select></div>'
              . '<button class="btn btn-primary btn-sm px-3"><i class="fa-solid fa-magnifying-glass me-1"></i>' . $this->t('btn_show') . '</button>'
              . ($userIn !== '' || $type !== '' || $period !== '30'
                    ? '<a href="' . $this->url('log') . '" class="btn btn-outline-secondary btn-sm px-3"><i class="fa-solid fa-xmark me-1"></i>' . $this->t('btn_reset_filters') . '</a>' : '')
              . '</form>';

        if ($err !== '') {
            $body .= '<div class="alert alert-danger mx-3"><i class="fa-solid fa-triangle-exclamation me-1"></i>' . $this->e($err) . '</div>';
        }

        if ($rows) {
            $tr = '';
            foreach ($rows as $r) {
                $a = (float)$r['amount'];
                [$label, $icon] = BONUS_LOG_TYPES[$r['type']] ?? BONUS_LOG_TYPES['other'];
                $name = $r['username'] !== null
                    ? '<a href="' . $this->baseUrl . '/' . get_profile_link((int)$r['uid']) . '" class="fw-semibold text-decoration-none">'
                      . (function_exists('format_name') ? format_name($this->e($r['username']), (int)$r['usergroup'], (int)$r['displaygroup']) : $this->e($r['username'])) . '</a>'
                      . ($uid === null ? ' <a href="' . $this->url('log', ['user' => '#' . (int)$r['uid'], 'type' => $type, 'period' => $period]) . '" class="bp-muted" title="' . $this->e($this->t('title_only_user')) . '" aria-label="' . $this->e($this->t('title_only_user')) . '"><i class="fa-solid fa-filter"></i></a>' : '')
                    : '<span class="bp-muted">' . $this->e($this->t('lbl_deleted_user', (int)$r['uid'])) . '</span>';
                $tr .= '<tr>'
                    . '<td class="text-nowrap">' . date('d.m.Y', (int)$r['added']) . '<div class="bp-muted">' . date('H:i', (int)$r['added']) . '</div></td>'
                    . '<td>' . $name . '</td>'
                    . '<td><div class="bp-muted small text-uppercase"><i class="fa-solid ' . $icon . ' me-1"></i>' . $this->e($label) . '</div>'
                    . $this->e($r['reason']) . ($r['type'] === 'seeding' ? ' <span class="bp-muted">' . $this->t('lbl_whole_day') . '</span>' : '')
                    . (!empty($r['actor_name']) ? ' <span class="bp-muted">&middot; ' . $this->t('lbl_by_actor', $this->e($r['actor_name'])) . '</span>' : '') . '</td>'
                    . '<td class="text-end text-nowrap fw-bold ' . ($a >= 0 ? 'text-success' : 'text-danger') . '">' . $this->e(bonus_log_amount($a)) . '</td>'
                    . '<td class="text-end text-nowrap bp-muted">' . ($r['balance'] !== null ? $this->pts($r['balance']) : '-') . '</td>'
                    . '</tr>';
            }
            $pageUrl = $this->script . '?act=bonuspoints&action=log'
                     . ($userIn !== '' ? '&user=' . rawurlencode($userIn) : '')
                     . ($type !== '' ? '&type=' . $type : '')
                     . ($period !== '30' ? '&period=' . $period : '') . '&';
            $pager = $tot['count'] > self::LOG_PER_PAGE
                ? '<div class="d-flex justify-content-center py-2 border-top">' . multipage($tot['count'], self::LOG_PER_PAGE, $page, $pageUrl) . '</div>' : '';

            $body .= '<div class="table-responsive"><table class="table bp-table"><thead><tr>'
                   . '<th><i class="fa-solid fa-calendar"></i>' . $this->t('th_date') . '</th><th><i class="fa-solid fa-user"></i>' . $this->t('th_user') . '</th><th><i class="fa-solid fa-receipt"></i>' . $this->t('th_reason') . '</th>'
                   . '<th class="text-end">' . $this->t('th_change') . '</th><th class="text-end">' . $this->t('th_balance') . '</th>'
                   . '</tr></thead><tbody>' . $tr . '</tbody></table></div>' . $pager;
        } else {
            $body .= '<div class="bp-empty"><i class="fa-solid fa-receipt"></i><div class="fw-semibold">' . $this->t('empty_log') . '</div></div>';
        }
        $body .= '</div>';

        $this->page($this->t('sec_log'), 'log', $body);
    }

    // ── Reset ────────────────────────────────────────────────

    private function resetPointsForm(): void
    {
        global $mybb;
        $groups = _selectbox_($this->t('lbl_usergroup'), 'usergroup');
        $body = '<form method="post" action="' . $this->script . '" class="bp-card bp-narrow">
            <input type="hidden" name="act" value="bonuspoints">
            <input type="hidden" name="my_post_key" value="' . $this->e($mybb->post_code) . '">
            <input type="hidden" name="action" value="resetall">
            <div class="bp-sec-head"><span class="bp-sec-icon ic-red"><i class="fa-solid fa-rotate-left"></i></span>
                <div><h2 class="bp-sec-title">' . $this->t('sec_reset') . '</h2><div class="bp-muted">' . $this->t('sub_reset') . '</div></div></div>
            <div class="p-3 p-md-4">
                <div class="alert alert-danger d-flex gap-2 rounded-4"><i class="fa-solid fa-radiation mt-1"></i><div>' . $this->t('alert_reset') . '</div></div>
                <label class="form-label"><i class="fa-solid fa-users"></i>' . $this->t('lbl_which_group') . '</label>
                <div class="bp-group-select">' . $groups . '</div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="' . $this->url() . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-xmark me-1"></i>' . $this->t('btn_cancel') . '</a>
                    <button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-arrow-right me-1"></i>' . $this->t('btn_continue') . '</button>
                </div>
            </div>
        </form>
        <script>document.querySelector(".bp .bp-group-select select")?.classList.add("form-select");</script>';
        // Раньше перед страницей подтверждения был ещё и confirm() — подтверждать приходилось дважды
        $this->page($this->t('tab_reset'), 'reset', $body);
    }

    private function resetAllPoints(): void
    {
        global $CURUSER;
        $group = $this->getValidatedUserGroup();
        $sure  = (int)($_POST['sure'] ?? 0);

        $cq = $this->db->sql_query_prepared(
            "SELECT COUNT(*) AS n, COALESCE(SUM(seedbonus),0) AS s FROM users WHERE enabled = 'yes' AND ustatus = 'confirmed' AND seedbonus > 0" . ($group ? ' AND usergroup = ?' : ''),
            $group ? [$group] : []
        );
        $c = $cq ? $this->db->fetch_array($cq) : [];
        $who = '<strong>' . $this->e($group ? $this->groupName($group) : $this->t('lbl_all_groups')) . '</strong>';

        if (!$sure) {
            $this->confirmPage($this->t('confirm_reset_title'),
                $this->t('confirm_reset_text', $who, number_format((int)($c['n'] ?? 0)), $this->pts($c['s'] ?? 0)),
                $this->url('resetall'), ['sure' => '1', 'usergroup' => $group, 'action' => 'resetall'], $this->t('btn_yes_reset'), 'reset');
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); stderr($this->t('err_title'), $this->t('err_method')); }

        $where  = "enabled = 'yes' AND ustatus = 'confirmed'";
        $params = [];
        if ($group) { $where .= " AND usergroup = ?"; $params[] = $group; }

        // Bonus log first: after the UPDATE the old balances are gone
        bonus_log_reset($where, $params,
            'Balance reset by staff (' . ($group ? $this->groupName($group, true) : 'all groups') . ')', (int)($CURUSER['id'] ?? 0) ?: null);

        $ok = $this->db->sql_query_prepared("UPDATE users SET seedbonus = 0.0 WHERE {$where}", $params);
        if ($ok && function_exists('write_log')) {
            write_log('Bonus points reset for ' . ($group ? $this->groupName($group, true) : 'all groups') . ' by ' . ($CURUSER['username'] ?? 'System'));
        }
        $this->result((bool)$ok, $ok ? $this->t('res_reset') : $this->t('res_reset_failed'), $ok ? $this->t('res_reset_text', $who) : $this->t('res_db_error'), 'reset');
    }
}

$bonusManager = new BonusPointsManager($db, $BASEURL);
$bonusManager->handleRequest();