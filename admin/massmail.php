<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}

define('M_VERSION', 'Mass Mail v.3.1');
const MM_ASSET_VER = 2;

set_time_limit(0);

global $mybb, $lang;

$lang->load('massmail');

$config_file = TSDIR . '/cache/massmail_config.php';

// ─── Helpers ──────────────────────────────────────────────────────────────────

if (!function_exists('ags_fmt')) {
    /** Substitutes {1}, {2}… (and %1$s, %2$s… produced by $lang->load()) */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach (array_values($args) as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return $map === [] ? $str : strtr($str, $map);
    }
}

function mm_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** All page strings, HTML-escaped, for use inside templates. @return array<string,string> */
function mm_lang_esc(): array
{
    global $lang;
    $out = [];
    foreach ((array)$lang->massmail as $k => $v) {
        $out[(string)$k] = mm_esc((string)$v);
    }
    return $out;
}

/** js_* strings without the prefix, as a global const for massmail.js */
function mm_js_lang(): string
{
    global $lang;
    $arr = [];
    foreach ((array)$lang->massmail as $k => $v) {
        $k = (string)$k;
        if (str_starts_with($k, 'js_')) {
            $arr[substr($k, 3)] = (string)$v;
        }
    }
    return '<script>const AGS_LANG = '
         . json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
         . ';</script>';
}

function mm_url(string $query = ''): string
{
    global $_this_script_;
    return (string)$_this_script_ . $query;
}

function mm_redirect(string $query = ''): never
{
    header('Location: ' . mm_url($query), true, 303);
    exit;
}

function mm_post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function mm_post_int(string $key, int $default): int
{
    $v = $_POST[$key] ?? $default;
    return is_scalar($v) ? max(1, (int)$v) : $default;
}

function mm_staff_name(): string
{
    global $mybb, $CURUSER;
    return (string)($mybb->user['username'] ?? $CURUSER['username'] ?? 'unknown');
}

function mm_duration(int $sec): string
{
    global $lang;
    if ($sec <= 0) {
        return ags_fmt($lang->massmail['dur_s'], 0);
    }
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    $s = $sec % 60;
    if ($h > 0) {
        return ags_fmt($lang->massmail['dur_h_m'], $h, $m);
    }
    if ($m > 0) {
        return $s > 0
            ? ags_fmt($lang->massmail['dur_m_s'], $m, $s)
            : ags_fmt($lang->massmail['dur_m'], $m);
    }
    return ags_fmt($lang->massmail['dur_s'], $s);
}

/** @return int[] */
function mm_parse_groups(array|string $raw): array
{
    $list = is_array($raw) ? $raw : explode(',', $raw);
    $ids  = [];
    foreach ($list as $v) {
        if (is_scalar($v) && (int)$v > 0) {
            $ids[(int)$v] = (int)$v;
        }
    }
    return array_values($ids);
}

// ─── Recipients ───────────────────────────────────────────────────────────────

/** @return array{0: string, 1: array} */
function mm_recipient_filter(array $ugids): array
{
    $where  = ['enabled = ?', 'ustatus = ?'];
    $params = ['yes', 'confirmed'];

    if ($ugids !== []) {
        $where[] = 'usergroup IN (0, ' . implode(',', array_fill(0, count($ugids), '?')) . ')';
        $params  = array_merge($params, $ugids);
    }

    return [implode(' AND ', $where), $params];
}

function mm_count_recipients(array $ugids): int
{
    global $db;
    [$where, $params] = mm_recipient_filter($ugids);
    $q = $db->sql_query_prepared("SELECT COUNT(email) AS total FROM users WHERE {$where}", $params);
    return (int)$db->fetch_field($q, 'total');
}

/** @return array<int,int> gid => eligible members */
function mm_group_counts(): array
{
    global $db;
    $q = $db->sql_query_prepared(
        'SELECT usergroup, COUNT(email) AS c FROM users WHERE enabled = ? AND ustatus = ? GROUP BY usergroup',
        ['yes', 'confirmed']
    );
    $out = [];
    while ($r = $db->fetch_array($q)) {
        $out[(int)$r['usergroup']] = (int)$r['c'];
    }
    return $out;
}

function mm_load_groups(): array
{
    global $db;
    $q    = $db->sql_query_prepared('SELECT gid, title, namestyle, image FROM usergroups ORDER BY gid');
    $rows = [];
    while ($r = $db->fetch_array($q)) {
        $rows[] = $r;
    }
    return $rows;
}

// ─── Config file (state of the current mailing) ───────────────────────────────

function mm_config_defaults(): array
{
    return [
        'waitbeforeredirect' => 30,
        'max_results'        => 10,
        'mmusergroups'       => '',
        'subject'            => '',
        'message'            => '',
        'total'              => 0,
        'last_page'          => 0,
        'sent_ok'            => 0,
        'sent_fail'          => 0,
        'started'            => 0,
        'finished'           => 0,
        'stopped'            => false,
    ];
}

function mm_read_config(string $file): array
{
    $raw = is_file($file)
        ? (static function (string $__mm_file): array {
            include $__mm_file;
            unset($__mm_file);
            return get_defined_vars();
        })($file)
        : [];

    $cfg = mm_config_defaults();
    foreach ($cfg as $key => $default) {
        if (!array_key_exists($key, $raw)) {
            continue;
        }
        $cfg[$key] = match (true) {
            is_bool($default)   => (bool)$raw[$key],
            is_int($default)    => (int)$raw[$key],
            default             => (string)$raw[$key],
        };
    }
    $cfg['waitbeforeredirect'] = max(1, $cfg['waitbeforeredirect']);
    $cfg['max_results']        = max(1, $cfg['max_results']);
    return $cfg;
}

function mm_write_config(string $file, array $cfg): bool
{
    $out = "<?php\n"
         . "if (!defined('M_VERSION')) die('Direct initialization not allowed.');\n"
         . '// Generated: ' . gmdate('r') . "\n";

    foreach (mm_config_defaults() as $key => $default) {
        $out .= '$' . $key . ' = ' . var_export($cfg[$key] ?? $default, true) . ";\n";
    }

    $ok = file_put_contents($file, $out, LOCK_EX) !== false;
    if ($ok && function_exists('opcache_invalidate')) {
        opcache_invalidate($file, true);
    }
    return $ok;
}

// ─── View pieces ──────────────────────────────────────────────────────────────

function mm_assets(string $baseurl): string
{
    $b = mm_esc($baseurl);
    return '<link rel="stylesheet" href="' . $b . '/include/templates/default/style/sweetalert2.min.css">'
         . '<link rel="stylesheet" href="' . $b . '/admin/templates/massmail.css?ver=' . MM_ASSET_VER . '">';
}

function mm_scripts(string $baseurl): string
{
    $b = mm_esc($baseurl);
    return mm_js_lang()
         . '<script src="' . $b . '/scripts/sweetalert2.min.js"></script>'
         . '<script src="' . $b . '/admin/scripts/massmail.js?ver=' . MM_ASSET_VER . '"></script>';
}

function mm_head(string $icon, string $tone, string $title, string $subtitle_html): string
{
    return '<div class="mm-card mm-head">'
         . '<span class="mm-head-icon tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<div class="mm-head-text"><h1 class="mm-title">' . mm_esc($title) . '</h1>'
         . '<p class="mm-sub">' . $subtitle_html . '</p></div>'
         . '<span class="mm-version"><i class="fa-solid fa-code-branch"></i>' . mm_esc(M_VERSION) . '</span>'
         . '</div>';
}

function mm_kpi(string $icon, string $tone, string $label, string $value, string $id = ''): string
{
    $id_attr = $id !== '' ? ' id="' . mm_esc($id) . '" aria-live="polite"' : '';
    return '<div class="mm-card mm-kpi">'
         . '<span class="mm-kpi-icon tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<div><div class="mm-kpi-value"' . $id_attr . '>' . $value . '</div>'
         . '<div class="mm-kpi-label">' . mm_esc($label) . '</div></div>'
         . '</div>';
}

function mm_alert(string $tone, string $icon, string $html): string
{
    return '<div class="mm-alert tone-' . $tone . '" role="' . ($tone === 'danger' ? 'alert' : 'status') . '">'
         . '<i class="fa-solid ' . $icon . '"></i><div>' . $html . '</div></div>';
}

function mm_render_form(
    array $v,
    array $groups,
    array $counts,
    array $last,
    string $error,
    string $notice,
    string $token,
    string $preview_json
): string {
    global $lang;
    $T = mm_lang_esc();

    $action     = mm_esc(mm_url());
    $eligible   = array_sum($counts);
    $zero_count = (int)($counts[0] ?? 0);

    // Initial numbers (JS keeps them live afterwards)
    $recipients = 0;
    foreach ($v['usergroups'] as $gid) {
        $recipients += (int)($counts[$gid] ?? 0);
    }
    if ($v['usergroups'] !== []) {
        $recipients += $zero_count;
    }
    $batches = $recipients > 0 ? (int)ceil($recipients / $v['max_results']) : 0;
    $eta     = $batches > 1 ? ($batches - 1) * $v['waitbeforeredirect'] : 0;

    // Usergroup switches
    $items       = '';
    $all_checked = $groups !== [];
    foreach ($groups as $g) {
        $gid     = (int)$g['gid'];
        $n       = (int)($counts[$gid] ?? 0);
        $checked = in_array($gid, $v['usergroups'], true);
        $all_checked = $all_checked && $checked;

        $items .= '<label class="mm-group' . ($n === 0 ? ' is-empty' : '') . '" for="ug_' . $gid . '">'
                . '<span class="form-check form-switch m-0">'
                . '<input class="form-check-input" type="checkbox" role="switch" name="usergroup[]"'
                . ' id="ug_' . $gid . '" value="' . $gid . '" data-count="' . $n . '"' . ($checked ? ' checked' : '') . '>'
                . '</span>'
                . '<span class="mm-group-name">' . (string)($g['image'] ?? '')
                . format_name(mm_esc((string)$g['title']), $gid) . '</span>'
                . '<span class="mm-group-count" title="' . $T['tip_group_count'] . '">'
                . '<i class="fa-solid fa-user"></i>' . number_format($n) . '</span>'
                . '</label>';
    }
    if ($items === '') {
        $items = '<p class="mm-empty"><i class="fa-solid fa-circle-info"></i>' . $T['empty_groups'] . '</p>';
    }

    // Last mailing summary
    $last_html = '';
    if ($last['subject'] !== '') {
        [$status_tone, $status_icon, $status_text] = match (true) {
            $last['stopped']      => ['warning', 'fa-circle-stop', $T['status_stopped']],
            $last['finished'] > 0 => ['success', 'fa-circle-check', $T['status_completed']],
            default               => ['info', 'fa-circle-pause', $T['status_unfinished']],
        };
        $when = $last['started'] > 0 ? date('d.m.Y H:i', $last['started']) : '—';

        $last_html = '<section class="mm-card mm-section">'
            . '<h2 class="mm-section-title"><i class="fa-solid fa-clock-rotate-left"></i>' . $T['sec_last']
            . '<span class="mm-status tone-' . $status_tone . '"><i class="fa-solid ' . $status_icon . '"></i>' . $status_text . '</span></h2>'
            . '<p class="mm-last-subject">' . mm_esc($last['subject']) . '</p>'
            . '<dl class="mm-facts">'
            . '<div><dt><i class="fa-regular fa-calendar"></i>' . $T['lbl_started'] . '</dt><dd>' . $when . '</dd></div>'
            . '<div><dt><i class="fa-solid fa-circle-check"></i>' . $T['lbl_delivered'] . '</dt><dd>' . number_format($last['sent_ok']) . '</dd></div>'
            . '<div><dt><i class="fa-solid fa-triangle-exclamation"></i>' . $T['lbl_failed'] . '</dt><dd>' . number_format($last['sent_fail']) . '</dd></div>'
            . '</dl>'
            . '<a class="btn btn-sm btn-outline-secondary mm-btn" href="' . mm_esc(mm_url('&reuse=1')) . '">'
            . '<i class="fa-solid fa-copy"></i>' . $T['btn_reuse'] . '</a>'
            . '</section>';
    }

    $alerts = '';
    if ($error !== '') {
        $alerts .= mm_alert('danger', 'fa-circle-exclamation', mm_esc($error));
    }
    if ($notice !== '') {
        $alerts .= mm_alert('info', 'fa-circle-info', mm_esc($notice));
    }

    $kpis = mm_kpi('fa-users', 'primary', $lang->massmail['kpi_eligible'], number_format($eligible))
          . mm_kpi('fa-user-check', 'success', $lang->massmail['kpi_selected'], number_format($recipients), 'mm-kpi-recipients')
          . mm_kpi('fa-layer-group', 'info', $lang->massmail['kpi_batches'], number_format($batches), 'mm-kpi-batches')
          . mm_kpi('fa-stopwatch', 'warning', $lang->massmail['kpi_eta'], mm_esc(mm_duration($eta)), 'mm-kpi-time');

    $head = mm_head('fa-paper-plane', 'primary', $lang->massmail['head_compose'], $T['head_compose_sub']);

    $subject     = mm_esc($v['subject']);
    $message     = mm_esc($v['message']);
    $wait        = (int)$v['waitbeforeredirect'];
    $per         = (int)$v['max_results'];
    $all_attr    = $all_checked ? ' checked' : '';
    $subject_len = function_exists('mb_strlen') ? mb_strlen($v['subject']) : strlen($v['subject']);
    $subject_cnt = ags_fmt($T['hint_subject_count'], '<span id="mm-subject-count">' . $subject_len . '</span>');

    return <<<HTML
<div class="mm-page" data-mode="form" data-zero-count="{$zero_count}">
    {$head}
    {$alerts}
    <div class="mm-kpis">{$kpis}</div>

    <form method="post" action="{$action}" name="massmail" id="mm-form" novalidate>
        <input type="hidden" name="action" value="sent">
        <input type="hidden" name="my_post_key" value="{$token}">

        <div class="mm-layout">
            <section class="mm-card mm-section">
                <h2 class="mm-section-title"><i class="fa-solid fa-envelope-open-text"></i>{$T['sec_message']}</h2>

                <div class="mm-field">
                    <label class="mm-label" for="mm-subject">
                        <span><i class="fa-solid fa-heading"></i>{$T['lbl_subject']}</span>
                        <span class="mm-hint">{$subject_cnt}</span>
                    </label>
                    <div class="mm-input">
                        <i class="fa-solid fa-pen"></i>
                        <input type="text" name="subject" id="mm-subject" class="form-control" value="{$subject}" placeholder="{$T['ph_subject']}" required>
                    </div>
                </div>

                <div class="mm-field">
                    <div class="mm-label">
                        <label for="message"><i class="fa-solid fa-align-left"></i>{$T['lbl_body']}</label>
                        <div class="mm-tabs" role="tablist" aria-label="{$T['aria_view_tabs']}">
                            <button type="button" class="mm-tab is-active" role="tab" aria-selected="true" data-mm-tab="write"><i class="fa-solid fa-code"></i>{$T['tab_write']}</button>
                            <button type="button" class="mm-tab" role="tab" aria-selected="false" data-mm-tab="preview"><i class="fa-solid fa-eye"></i>{$T['tab_preview']}</button>
                        </div>
                    </div>
                    <div data-mm-pane="write">
                        <textarea name="message" id="message" class="form-control" rows="14" placeholder="{$T['ph_message']}" required>{$message}</textarea>
                    </div>
                    <div data-mm-pane="preview" hidden>
                        <div class="mm-preview"><iframe id="mm-preview-frame" title="{$T['aria_preview_frame']}" sandbox=""></iframe></div>
                    </div>
                    <p class="mm-note"><i class="fa-solid fa-circle-info"></i>{$T['hint_html']}</p>
                </div>
            </section>

            <div class="mm-stack">
                <section class="mm-card mm-section">
                    <h2 class="mm-section-title"><i class="fa-solid fa-users"></i>{$T['sec_recipients']}
                        <span class="mm-title-tools form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="ug_select_all"{$all_attr}>
                            <label class="form-check-label" for="ug_select_all">{$T['lbl_all_groups']}</label>
                        </span>
                    </h2>
                    <div class="mm-groups">{$items}</div>
                </section>

                <section class="mm-card mm-section">
                    <h2 class="mm-section-title"><i class="fa-solid fa-gauge-high"></i>{$T['sec_pace']}</h2>
                    <div class="mm-pair">
                        <div class="mm-field">
                            <label class="mm-label" for="mm-batch"><span><i class="fa-solid fa-layer-group"></i>{$T['lbl_batch_size']}</span></label>
                            <div class="mm-input">
                                <i class="fa-solid fa-envelope"></i>
                                <input type="number" min="1" name="max_results" id="mm-batch" class="form-control" value="{$per}">
                            </div>
                            <p class="mm-note">{$T['hint_batch_size']}</p>
                        </div>
                        <div class="mm-field">
                            <label class="mm-label" for="mm-wait"><span><i class="fa-solid fa-hourglass-half"></i>{$T['lbl_pause']}</span></label>
                            <div class="mm-input">
                                <i class="fa-solid fa-clock"></i>
                                <input type="number" min="1" name="waitbeforeredirect" id="mm-wait" class="form-control" value="{$wait}">
                            </div>
                            <p class="mm-note">{$T['hint_pause']}</p>
                        </div>
                    </div>
                </section>

                {$last_html}
            </div>
        </div>

        <div class="mm-bar">
            <div class="mm-bar-summary" id="mm-bar-summary" aria-live="polite">
                <i class="fa-solid fa-circle-info"></i><span>{$T['bar_pick_group']}</span>
            </div>
            <div class="mm-bar-actions">
                <button type="reset" class="btn btn-outline-secondary mm-btn" id="mm-reset"><i class="fa-solid fa-rotate-left"></i>{$T['btn_clear']}</button>
                <button type="submit" class="btn btn-primary mm-btn" id="mm-send"><i class="fa-solid fa-paper-plane"></i><span>{$T['btn_send']}</span></button>
            </div>
        </div>
    </form>

    <script type="application/json" id="mm-preview-data">{$preview_json}</script>
</div>
HTML;
}

function mm_render_send(array $s): string
{
    global $lang;
    $T = mm_lang_esc();

    $done_count = min($s['page'] * $s['per'], $s['total']);
    $percent    = $s['total'] > 0 ? (int)floor($done_count / $s['total'] * 100) : 100;
    $finished   = $s['next'] === null;

    $subtitle = ags_fmt($T['head_subject'], mm_esc($s['subject']));
    $head = $finished
        ? mm_head('fa-envelope-circle-check', 'success', $lang->massmail['head_finished'], $subtitle)
        : mm_head('fa-paper-plane', 'primary', $lang->massmail['head_sending'], $subtitle);

    $kpis = mm_kpi('fa-users', 'primary', $lang->massmail['kpi_recipients'], number_format($s['total']))
          . mm_kpi('fa-layer-group', 'info', $lang->massmail['kpi_batch'], $s['page'] . ' / ' . $s['total_pages'])
          . mm_kpi('fa-circle-check', 'success', $lang->massmail['kpi_delivered'], number_format($s['ok_total']))
          . mm_kpi('fa-triangle-exclamation', 'danger', $lang->massmail['kpi_failed'], number_format($s['fail_total']));

    // Batch log
    $rows = '';
    foreach ($s['rows'] as [$email, $ok]) {
        $rows .= '<li class="mm-log-row ' . ($ok ? 'is-ok' : 'is-fail') . '">'
               . '<i class="fa-solid ' . ($ok ? 'fa-check' : 'fa-xmark') . '" aria-label="' . ($ok ? $T['aria_sent'] : $T['aria_failed']) . '"></i>'
               . '<span class="mm-addr" title="' . mm_esc($email) . '">' . mm_esc($email) . '</span></li>';
    }

    $batch_ok   = count(array_filter($s['rows'], static fn(array $r): bool => $r[1]));
    $batch_fail = count($s['rows']) - $batch_ok;

    $batch_body = $s['skipped']
        ? mm_alert('info', 'fa-shield-halved', $T['alert_skipped'])
        : ($rows !== '' ? '<ul class="mm-log">' . $rows . '</ul>'
                        : '<p class="mm-empty"><i class="fa-solid fa-inbox"></i>' . $T['empty_batch'] . '</p>');

    $batch_meta = $s['skipped'] ? '' :
        '<span class="mm-status tone-success"><i class="fa-solid fa-check"></i>' . ags_fmt($T['status_batch_sent'], $batch_ok) . '</span>'
        . ($batch_fail > 0 ? '<span class="mm-status tone-danger"><i class="fa-solid fa-xmark"></i>' . ags_fmt($T['status_batch_failed'], $batch_fail) . '</span>' : '');

    // Bottom bar
    if ($finished) {
        $bar = '<div class="mm-bar-summary"><i class="fa-solid fa-flag-checkered"></i>'
             . '<span>' . ags_fmt(
                    $T['bar_finished'],
                    '<strong>' . number_format($s['ok_total']) . '</strong>',
                    '<strong>' . number_format($s['fail_total']) . '</strong>'
               ) . '</span></div>'
             . '<div class="mm-bar-actions"><a class="btn btn-primary mm-btn" href="' . mm_esc(mm_url()) . '">'
             . '<i class="fa-solid fa-plus"></i>' . $T['btn_new'] . '</a></div>';
    } else {
        $bar = '<div class="mm-bar-summary mm-countdown"><i class="fa-solid fa-hourglass-half" id="mm-countdown-icon"></i>'
             . '<span id="mm-countdown-text">' . ags_fmt(
                    $T['bar_countdown'],
                    $s['page'] + 1,
                    '<strong id="mm-countdown">' . (int)$s['wait'] . '</strong>'
               ) . '</span></div>'
             . '<div class="mm-bar-actions">'
             . '<button type="button" class="btn btn-outline-secondary mm-btn" id="mm-pause"><i class="fa-solid fa-pause"></i><span>' . $T['btn_pause'] . '</span></button>'
             . '<form method="post" action="' . mm_esc(mm_url()) . '" id="mm-stop-form">'
             . '<input type="hidden" name="action" value="stop">'
             . '<input type="hidden" name="my_post_key" value="' . $s['token'] . '">'
             . '<button type="submit" class="btn btn-outline-danger mm-btn"><i class="fa-solid fa-circle-stop"></i>' . $T['btn_stop'] . '</button>'
             . '</form></div>';
    }

    $next_attr     = $finished ? '' : ' data-next="' . mm_esc($s['next']) . '" data-seconds="' . (int)$s['wait'] . '"';
    $processed     = ags_fmt($T['hint_processed'], number_format($done_count), number_format($s['total']));
    $batch_title   = ags_fmt($T['sec_batch'], $s['page'], $s['total_pages']);

    return <<<HTML
<div class="mm-page" data-mode="send"{$next_attr}>
    {$head}
    <div class="mm-kpis">{$kpis}</div>

    <section class="mm-card mm-section">
        <h2 class="mm-section-title"><i class="fa-solid fa-bars-progress"></i>{$T['sec_progress']}
            <span class="mm-title-tools mm-hint">{$processed}</span>
        </h2>
        <div class="mm-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{$percent}">
            <div class="mm-progress-bar" style="width: {$percent}%"></div>
        </div>
        <p class="mm-note mm-progress-label">{$percent}%</p>
    </section>

    <section class="mm-card mm-section">
        <h2 class="mm-section-title"><i class="fa-solid fa-list-check"></i>{$batch_title}
            <span class="mm-title-tools">{$batch_meta}</span>
        </h2>
        {$batch_body}
    </section>

    <div class="mm-bar">{$bar}</div>
</div>
HTML;
}

// ─── Mail template (header / footer) ─────────────────────────────────────────

// Was $adminlang['massmail'] in admin/include/staff_languages.php (English only, <font> tags).
// Lang strings are plain text: escaped here, and the site link is put in as HTML via {1}.
$mail_header = '<p style="color:#c0392b;font-weight:bold;margin:0;">'
             . mm_esc(ags_fmt($lang->massmail['mail_header'], (string)$SITENAME, gmdate('Y-m-d H:i:s')))
             . '</p>';
$mail_footer = '<p style="color:#1f5fbf;font-weight:bold;margin:0;">'
             . mm_esc($lang->massmail['mail_footer_greeting']) . '<br>'
             . ags_fmt(
                   mm_esc($lang->massmail['mail_footer_team']),
                   '<a href="' . mm_esc((string)$BASEURL) . '">' . mm_esc((string)$SITENAME) . '</a>'
               )
             . '</p>';
$token       = mm_esc((string)($mybb->post_code ?? ''));

// ─── Controller ───────────────────────────────────────────────────────────────

$error     = '';
$notice    = '';
$form      = null;
$send_view = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        stderr($lang->massmail['err_title'], $lang->massmail['err_csrf'], false);
    }

    // Stop a running mailing
    if ($mybb->get_input('action') === 'stop') {
        $cfg = mm_read_config($config_file);
        if ($cfg['subject'] !== '' && $cfg['finished'] === 0) {
            $cfg['stopped']  = true;
            $cfg['finished'] = TIMENOW;
            mm_write_config($config_file, $cfg);
            write_log(sprintf(
                'Mass mail "%s" stopped by %s after batch %d (%d delivered, %d failed)',
                $cfg['subject'], mm_staff_name(), $cfg['last_page'], $cfg['sent_ok'], $cfg['sent_fail']
            ));
        }
        mm_redirect('&stopped=1');
    }

    // Start a new mailing
    $form = [
        'waitbeforeredirect' => mm_post_int('waitbeforeredirect', 30),
        'max_results'        => mm_post_int('max_results', 10),
        'usergroups'         => mm_parse_groups(is_array($_POST['usergroup'] ?? null) ? $_POST['usergroup'] : []),
        'subject'            => mm_post_str('subject'),
        'message'            => mm_post_str('message'),
    ];

    $writable = is_file($config_file) ? is_writable($config_file) : is_writable(dirname($config_file));

    if (!$writable) {
        $error = ags_fmt($lang->massmail['err_not_writable'], $config_file);
    } elseif ($form['subject'] === '' || $form['message'] === '' || $form['message'] === '<br />') {
        $error = $lang->massmail['err_empty'];
    } elseif ($form['usergroups'] === []) {
        $error = $lang->massmail['err_no_groups'];
    } else {
        $total = mm_count_recipients($form['usergroups']);

        if ($total === 0) {
            $error = $lang->massmail['err_no_members'];
        } else {
            $cfg = array_merge(mm_config_defaults(), [
                'waitbeforeredirect' => $form['waitbeforeredirect'],
                'max_results'        => $form['max_results'],
                'mmusergroups'       => implode(',', $form['usergroups']),
                'subject'            => $form['subject'],
                'message'            => $form['message'],
                'total'              => $total,
                'started'            => TIMENOW,
            ]);

            if (!mm_write_config($config_file, $cfg)) {
                $error = ags_fmt($lang->massmail['err_write'], $config_file);
            } else {
                write_log(sprintf(
                    'Mass mail "%s" started by %s: %d recipients, groups %s, %d per batch, %ds pause',
                    $cfg['subject'], mm_staff_name(), $total, $cfg['mmusergroups'],
                    $cfg['max_results'], $cfg['waitbeforeredirect']
                ));
                mm_redirect('&action=sent&page=1');
            }
        }
    }

} elseif (($_GET['action'] ?? '') === 'sent' && isset($_GET['page'])) {

    $page  = max(1, (int)$_GET['page']);
    $cfg   = mm_read_config($config_file);
    $ugids = mm_parse_groups($cfg['mmusergroups']);

    if ($cfg['subject'] === '' || $cfg['message'] === '' || $ugids === []) {
        $error = $lang->massmail['err_no_mailing'];
    } elseif ($cfg['stopped']) {
        $notice = $lang->massmail['notice_was_stopped'];
    } else {
        $per         = $cfg['max_results'];
        $total       = mm_count_recipients($ugids);
        $total_pages = max(1, (int)ceil($total / $per));

        if ($total === 0) {
            $error = $lang->massmail['err_no_members'];
        } elseif ($page > $total_pages) {
            $notice = $lang->massmail['notice_finished'];
        } else {
            $rows    = [];
            $skipped = $page <= $cfg['last_page'];

            if (!$skipped) {
                // Mark the batch as taken before sending, so a refresh can't send it twice
                $cfg['last_page'] = $page;
                $cfg['total']     = $total;
                mm_write_config($config_file, $cfg);

                [$where, $params] = mm_recipient_filter($ugids);
                $emails = $db->sql_query_prepared(
                    "SELECT email FROM users WHERE {$where} LIMIT ?, ?",
                    array_merge($params, [($page - 1) * $per, $per])
                );

                $body = $mail_header . '<br><hr><br>' . $cfg['message'] . '<br><hr><br>' . $mail_footer;

                while ($row = $db->fetch_array($emails)) {
                    $to     = (string)$row['email'];
                    $rows[] = [$to, (bool)my_mail($to, $cfg['subject'], $body, '', '', '', false, 'html', '')];
                }

                $ok = count(array_filter($rows, static fn(array $r): bool => $r[1]));
                $cfg['sent_ok']   += $ok;
                $cfg['sent_fail'] += count($rows) - $ok;

                if ($page >= $total_pages) {
                    $cfg['finished'] = TIMENOW;
                    write_log(sprintf(
                        'Mass mail "%s" finished (%s): %d delivered, %d failed',
                        $cfg['subject'], mm_staff_name(), $cfg['sent_ok'], $cfg['sent_fail']
                    ));
                }
                mm_write_config($config_file, $cfg);
            }

            $send_view = [
                'subject'     => $cfg['subject'],
                'total'       => $total,
                'page'        => $page,
                'total_pages' => $total_pages,
                'per'         => $per,
                'wait'        => $cfg['waitbeforeredirect'],
                'ok_total'    => $cfg['sent_ok'],
                'fail_total'  => $cfg['sent_fail'],
                'rows'        => $rows,
                'skipped'     => $skipped,
                'next'        => $page < $total_pages ? mm_url('&action=sent&page=' . ($page + 1)) : null,
                'token'       => $token,
            ];
        }
    }

} elseif (isset($_GET['stopped'])) {
    $notice = $lang->massmail['notice_stopped'];
}

// ─── Render: sending page ─────────────────────────────────────────────────────

if ($send_view !== null) {
    stdhead(VERSION . ' – ' . $lang->massmail['title_send']);
    echo mm_assets((string)$BASEURL);
    echo mm_render_send($send_view);
    echo mm_scripts((string)$BASEURL);
    stdfoot();
    exit;
}

// ─── Render: form ─────────────────────────────────────────────────────────────

$last = mm_read_config($config_file);

if ($form === null) {
    $reuse = isset($_GET['reuse']) && $last['subject'] !== '';
    $form  = [
        'waitbeforeredirect' => $reuse ? $last['waitbeforeredirect'] : 30,
        'max_results'        => $reuse ? $last['max_results'] : 10,
        'usergroups'         => $reuse ? mm_parse_groups($last['mmusergroups']) : [],
        'subject'            => $reuse ? $last['subject'] : '',
        'message'            => $reuse ? $last['message'] : '',
    ];
}

$preview_json = (string)json_encode(
    ['header' => $mail_header, 'footer' => $mail_footer],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

stdhead(VERSION . ' – ' . $lang->massmail['title_start'], true, '', '');
echo '<link rel="stylesheet" href="' . mm_esc((string)$BASEURL) . '/include/templates/default/style/userclass.css" type="text/css" media="screen">';
echo mm_assets((string)$BASEURL);
echo mm_render_form($form, mm_load_groups(), mm_group_counts(), $last, $error, $notice, $token, $preview_json);
echo mm_scripts((string)$BASEURL);
stdfoot();