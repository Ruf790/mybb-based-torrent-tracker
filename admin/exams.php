<?php
declare(strict_types=1);

/**
 * Staff panel: exams and tasks (index.php?act=exams).
 * Port of the NexusPHP admin: ExamResource + ExamUserResource.
 */

if (!defined('STAFF_PANEL')) {
    die('Direct access denied.');
}

require_once TSDIR . '/include/functions_exam.php';

global $lang;
$lang->load('exams');

/** Language string with {1}, {2} (or %1$s, %2$s if the loader converted them) filled in. */
function ex_t(string $key, ...$args): string
{
    global $lang;
    $text = (string)($lang->exams[$key] ?? $key);
    foreach ($args as $i => $v) {
        $n    = $i + 1;
        $text = str_replace(['{' . $n . '}', '%' . $n . '$s'], (string)$v, $text);
    }
    return $text;
}

const EX_PAGE_SIZE = 50;

/** Short duration for stats: "2d 5h", "3h 12m", "8m" */
function ex_duration(?int $sec): string
{
    if ($sec === null) return '-';
    $d = ex_t('unit_d');
    $h = ex_t('unit_h');
    $m = ex_t('unit_m');
    if ($sec >= 86400) return intdiv($sec, 86400) . $d . ' ' . intdiv($sec % 86400, 3600) . $h;
    if ($sec >= 3600)  return intdiv($sec, 3600) . $h . ' ' . intdiv($sec % 3600, 60) . $m;
    return max(1, intdiv($sec, 60)) . $m;
}

function ex_rate(?float $rate): string
{
    return $rate === null ? '-' : rtrim(rtrim(number_format($rate, 1, '.', ''), '0'), '.') . '%';
}

// ── Helpers ──────────────────────────────────────────────

function ex_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function ex_self(array $params = []): string
{
    global $_this_script_;
    $base = html_entity_decode((string)($_this_script_ ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($base === '') {
        $base = (string)($_SERVER['SCRIPT_NAME'] ?? 'index.php') . '?act=exams';
    }
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null);
    return $params ? $base . '&' . http_build_query($params) : $base;
}

function ex_post_key(): string
{
    global $mybb;
    return function_exists('generate_post_check') ? (string)generate_post_check() : (string)($mybb->post_code ?? '');
}

function ex_redirect(array $params, string $type, string $msg): never
{
    setcookie('ex_flash', json_encode(['t' => $type, 'm' => $msg], JSON_UNESCAPED_UNICODE), [
        'expires' => TIMENOW + 60, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure'  => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    header('Location: ' . ex_self($params), true, 303);
    exit;
}

function ex_flash_html(): string
{
    if (empty($_COOKIE['ex_flash'])) return '';
    $f = json_decode((string)$_COOKIE['ex_flash'], true);
    setcookie('ex_flash', '', ['expires' => 1, 'path' => '/']);
    if (!is_array($f) || !isset($f['t'], $f['m'])) return '';
    $tone = in_array($f['t'], ['success', 'danger', 'warning'], true) ? $f['t'] : 'primary';
    $icon = ['success' => 'fa-circle-check', 'danger' => 'fa-triangle-exclamation', 'warning' => 'fa-circle-exclamation'][$tone] ?? 'fa-circle-info';
    return '<div class="ex-alert ex-tone-' . $tone . '" role="alert"><i class="fa-solid ' . $icon . '"></i><div>' . ex_e((string)$f['m']) . '</div></div>';
}

function ex_log(string $msg): void
{
    if (function_exists('write_log')) write_log('[Exams] ' . $msg);
}

function ex_groups(): array
{
    global $db;
    static $g = null;
    if ($g === null) {
        $g = [];
        $q = $db->sql_query_prepared('SELECT gid, title FROM usergroups ORDER BY gid', []);
        while ($r = $db->fetch_array($q)) $g[(int)$r['gid']] = (string)$r['title'];
    }
    return $g;
}

function ex_hidden(string $do, array $extra = []): string
{
    $h = '<input type="hidden" name="my_post_key" value="' . ex_e(ex_post_key()) . '">'
       . '<input type="hidden" name="do" value="' . ex_e($do) . '">';
    foreach ($extra as $k => $v) {
        $h .= '<input type="hidden" name="' . ex_e((string)$k) . '" value="' . ex_e((string)$v) . '">';
    }
    return $h;
}

function ex_user_link(array $r): string
{
    global $BASEURL;
    $uid  = (int)$r['uid'];
    $name = ex_e((string)$r['username']);
    if (function_exists('format_name')) {
        $name = format_name($name, (int)$r['usergroup'], (int)($r['displaygroup'] ?? 0));
    }
    $url = function_exists('get_profile_link') ? get_profile_link($uid) : $BASEURL . '/member.php?action=profile&uid=' . $uid;
    return '<a href="' . ex_e((string)$url) . '">' . $name . '</a>';
}

function ex_dt_input(string $name): ?string
{
    $v = trim((string)($_POST[$name] ?? ''));
    if ($v === '') return null;
    $ts = strtotime($v);
    if ($ts === false) throw new ExamException(ex_t('invalid_date', $v));
    return exam_dt($ts);
}

function ex_dt_value(?string $dt): string
{
    $ts = exam_ts($dt);
    return $ts ? date('Y-m-d H:i', $ts) : ''; // flatpickr dateFormat 'Y-m-d H:i'
}

/** Date/time field for flatpickr with a clear button (as in usersearch.php). */
function ex_date_input(string $id, string $name, string $value = '', string $from = '', bool $required = false): string
{
    return '<div class="ex-date-wrap">'
        . '<input id="' . $id . '" type="text" class="ex-date" autocomplete="off" name="' . $name . '" value="' . ex_e($value) . '"'
        . ($from !== '' ? ' data-ex-from="#' . $from . '"' : '') . ($required ? ' required' : '') . '>'
        . '<button type="button" class="ex-icon-btn" data-ex-clear="#' . $id . '" title="' . ex_e(ex_t('clear')) . '" aria-label="' . ex_e(ex_t('clear')) . '"><i class="fa-solid fa-xmark"></i></button>'
        . '</div>';
}

// ── Text for indexes / filters / time ────────────────────

function ex_indexes_html(array $exam): string
{
    $out = '';
    foreach (exam_checked_indexes($exam) as $i) {
        $meta = EXAM_INDEXES[(int)$i['index']];
        $out .= '<div class="ex-req">' . ex_e($meta['name']) . ': <strong>' . (int)$i['require_value'] . ' ' . ex_e($meta['unit']) . '</strong></div>';
    }
    return $out;
}

function ex_filters_html(array $exam): string
{
    $f   = $exam['filters'];
    $out = [];
    if (!empty($f[EXAM_FILTER_USER_CLASS])) {
        $names = array_map(static fn($g) => ex_groups()[(int)$g] ?? "#{$g}", $f[EXAM_FILTER_USER_CLASS]);
        $out[] = ex_e(ex_t('groups')) . ': ' . ex_e(implode(', ', $names));
    }
    $r = $f[EXAM_FILTER_USER_REGISTER_TIME_RANGE] ?? [];
    if (!empty($r[0]) || !empty($r[1])) {
        $out[] = ex_e(ex_t('registered')) . ': ' . ex_e(($r[0] ?: '--') . ' ~ ' . ($r[1] ?: '--'));
    }
    $r = $f[EXAM_FILTER_USER_REGISTER_DAYS_RANGE] ?? [];
    if ((isset($r[0]) && $r[0] !== '' && $r[0] !== null) || (isset($r[1]) && $r[1] !== '' && $r[1] !== null)) {
        $out[] = ex_e(ex_t('days_since_reg')) . ': ' . ex_e(($r[0] ?? '--') . ' ~ ' . ($r[1] ?? '--'));
    }
    if (!empty($f[EXAM_FILTER_USER_DONATE])) {
        $out[] = ex_e(ex_t('donor')) . ': ' . ex_e(implode(', ', array_map(static fn($v) => ex_t($v === 'yes' ? 'yes_l' : 'no_l'), $f[EXAM_FILTER_USER_DONATE])));
    }
    return implode('<br>', $out);
}

function ex_time_html(array $exam): string
{
    if (!empty($exam['begin']) && !empty($exam['end'])) {
        return ex_e(date('d.m.Y H:i', exam_ts($exam['begin'])) . ' ~ ' . date('d.m.Y H:i', exam_ts($exam['end'])));
    }
    if ((int)$exam['duration'] > 0) return (int)$exam['duration'] . ' ' . ex_e(ex_t('days_from_assignment'));
    if (!empty($exam['recurring'])) return ex_e(EXAM_RECURRING[(string)$exam['recurring']] ?? (string)$exam['recurring']);
    return '-';
}

// ── Validation (checkIndexes / checkBeginEnd / checkFilters) ─

/** Returns the duration in hours. */
function ex_check_begin_end(?string $begin, ?string $end, int $duration, ?string $recurring): float
{
    if ($begin && $end && $duration === 0 && !$recurring) {
        if (exam_ts($end) <= exam_ts($begin)) throw new ExamException(ex_t('err_end_before'));
        return round((exam_ts($end) - exam_ts($begin)) / 3600);
    }
    if (!$begin && !$end && $duration > 0 && !$recurring) {
        return (float)($duration * 24);
    }
    if (!$begin && !$end && $duration === 0 && $recurring) {
        [$b, $e] = exam_recurring_range($recurring, TIMENOW);
        return round(($e - $b) / 3600);
    }
    throw new ExamException(ex_t('err_time_cond'));
}

function ex_check_indexes(array $indexes, float $hours): void
{
    $valid = 0;
    foreach ($indexes as $i) {
        if (empty($i['checked'])) continue;
        if (!ctype_digit((string)$i['require_value'])) {
            throw new ExamException(ex_t('err_index_whole', EXAM_INDEXES[$i['index']]['name']));
        }
        if ($i['index'] === EXAM_INDEX_SEED_TIME_AVERAGE && (int)$i['require_value'] > $hours) {
            throw new ExamException(ex_t('err_seed_avg_long', $i['require_value'], $hours));
        }
        $valid++;
    }
    if ($valid === 0) throw new ExamException(ex_t('err_no_index'));
}

function ex_check_filters(array $f): void
{
    $has = false;
    if (!empty($f[EXAM_FILTER_USER_CLASS])) {
        if (array_diff($f[EXAM_FILTER_USER_CLASS], array_keys(ex_groups()))) throw new ExamException(ex_t('err_unknown_group'));
        $has = true;
    }
    if (!empty($f[EXAM_FILTER_USER_DONATE])) $has = true;

    [$b, $e] = $f[EXAM_FILTER_USER_REGISTER_TIME_RANGE];
    if ($b || $e) $has = true;
    if ($b && $e && exam_ts($b) > exam_ts($e)) throw new ExamException(ex_t('err_reg_range'));

    [$b, $e] = $f[EXAM_FILTER_USER_REGISTER_DAYS_RANGE];
    if ($b !== null || $e !== null) $has = true;
    if ($b !== null && $e !== null && $b > $e) throw new ExamException(ex_t('err_days_range'));

    if (!$has) throw new ExamException(ex_t('err_no_filter'));
}

/**
 * What changed in the requirements, as lines for the confirm / log / PM:
 * "Downloaded: 200 GB -> 500 GB", "Seed points: removed", "Uploaded: added, 50 GB".
 */
function ex_indexes_diff(array $old, array $new): array
{
    $map = static function (array $list): array {
        $m = [];
        foreach ($list as $i) {
            if (!empty($i['checked'])) $m[(int)$i['index']] = (int)$i['require_value'];
        }
        return $m;
    };
    $o = $map($old);
    $n = $map($new);
    $fmt = static fn(int $k, int $v): string => trim(exam_num($v) . ' ' . EXAM_INDEXES[$k]['unit']);

    $lines = [];
    foreach (EXAM_INDEXES as $k => $meta) {
        $had = array_key_exists($k, $o);
        $has = array_key_exists($k, $n);
        if ($had && $has && $o[$k] !== $n[$k]) $lines[] = "{$meta['name']}: " . $fmt($k, $o[$k]) . ' -> ' . $fmt($k, $n[$k]);
        elseif ($had && !$has)                   $lines[] = "{$meta['name']}: " . ex_t('diff_removed', $fmt($k, $o[$k]));
        elseif (!$had && $has)                   $lines[] = "{$meta['name']}: " . ex_t('diff_added', $fmt($k, $n[$k]));
    }
    return $lines;
}

/** Target filters from the exam form, validated (shared by save and the coverage check). */
function ex_filters_from_post(): array
{
    $days = static fn(string $k): ?int => trim((string)($_POST[$k] ?? '')) === '' ? null : max(0, (int)$_POST[$k]);
    $filters = [
        EXAM_FILTER_USER_CLASS               => array_values(array_map('intval', (array)($_POST['classes'] ?? []))),
        EXAM_FILTER_USER_REGISTER_TIME_RANGE => [ex_dt_input('reg_from'), ex_dt_input('reg_to')],
        EXAM_FILTER_USER_REGISTER_DAYS_RANGE => [$days('days_from'), $days('days_to')],
        EXAM_FILTER_USER_DONATE              => array_values(array_intersect((array)($_POST['donate'] ?? []), ['yes', 'no'])),
    ];
    ex_check_filters($filters);
    return $filters;
}

/**
 * "Check who gets it" (AJAX from the exam form): coverage for the filters as they
 * are in the form right now, nothing is saved or assigned. Answers JSON {ok, html}.
 */
function ex_simulate_ajax(): never
{
    header('Content-Type: application/json; charset=utf-8');
    $reply = static function (bool $ok, string $html): never {
        echo json_encode(['ok' => $ok, 'html' => $html], JSON_UNESCAPED_UNICODE);
        exit;
    };
    $err = static fn(string $m): string => '<div class="ex-alert ex-tone-danger"><i class="fa-solid fa-triangle-exclamation"></i><div>' . ex_e($m) . '</div></div>';

    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        $reply(false, $err(ex_t('err_token')));
    }
    try {
        $filters = ex_filters_from_post();
    } catch (ExamException $e) {
        $reply(false, $err($e->getMessage()));
    }

    $id      = (int)($_POST['id'] ?? 0);
    $isTask  = (int)($_POST['type'] ?? EXAM_TYPE_EXAM) === EXAM_TYPE_TASK;
    $auto    = !$isTask && !empty($_POST['is_discovered']);
    $enabled = !empty($_POST['enabled']);
    $s       = exam_simulate_coverage($filters, $id);

    $row = static fn(string $label, int $n, string $note = '', string $tone = ''): string =>
        '<tr' . ($tone !== '' ? ' class="ex-sim__' . $tone . '"' : '') . '><td>' . $label . ($note !== '' ? '<div class="ex-sub">' . ex_e($note) . '</div>' : '') . '</td>'
        . '<td class="ex-num">' . exam_num($n) . '</td></tr>';

    $h = '<table class="ex-sim__table"><tbody>'
       . $row(ex_e(ex_t('sim_matched')), $s['matched'])
       . ($s['staff']       ? $row('&minus; ' . ex_e(ex_t('sim_staff')), $s['staff'], ex_t('sim_staff_note'), 'muted') : '')
       . ($s['disabled']    ? $row('&minus; ' . ex_e(ex_t('sim_disabled')), $s['disabled'], '', 'muted') : '')
       . ($s['unconfirmed'] ? $row('&minus; ' . ex_e(ex_t('sim_unconfirmed')), $s['unconfirmed'], '', 'muted') : '')
       . ($s['took']        ? $row('&minus; ' . ex_e(ex_t($isTask ? 'sim_took_task' : 'sim_took_exam')), $s['took'], ex_t('sim_took_note'), 'muted') : '')
       . ($s['busy']        ? $row('&minus; ' . ex_e(ex_t('sim_busy')), $s['busy'], ex_t('sim_busy_note'), 'muted') : '')
       . $row('<strong>' . ex_e(ex_t($isTask ? 'sim_now_task' : ($auto ? 'sim_now_auto' : 'sim_now_manual'))) . '</strong>', $s['will_get'], '', 'total')
       . '</tbody></table>';

    if ($s['sample']) {
        $names = array_map(static fn(array $u): string => ex_user_link(['uid' => (int)$u['id']] + $u), $s['sample']);
        $more  = $s['will_get'] - count($s['sample']);
        $h .= '<div class="ex-sim__names"><span class="ex-sub">' . ex_e($more > 0 ? ex_t('sim_first', count($names)) : ex_t('sim_who')) . '</span> '
            . implode(', ', $names) . ($more > 0 ? ' <span class="ex-sub">' . ex_e(ex_t('sim_and_more', exam_num($more))) . '</span>' : '') . '</div>';
    }

    // What actually happens on save
    if ($isTask) {
        $note = ['info', 'fa-hand', ex_t('sim_note_task')];
    } elseif (!$enabled) {
        $note = ['secondary', 'fa-power-off', ex_t('sim_note_disabled')];
    } elseif (!$auto) {
        $note = ['info', 'fa-user-plus', ex_t('sim_note_manual')];
    } elseif ($s['will_get'] > 0) {
        $note = ['warning', 'fa-robot', ex_t('sim_note_auto', exam_num($s['will_get']))];
    } else {
        $note = ['success', 'fa-circle-check', ex_t('sim_note_none')];
    }
    $h .= '<div class="ex-alert ex-tone-' . $note[0] . '"><i class="fa-solid ' . $note[1] . '"></i><div>' . ex_e($note[2]) . '</div></div>';

    $reply(true, $h);
}

// ── POST ─────────────────────────────────────────────────

function ex_handle_post(): void
{
    global $db, $CURUSER;

    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        ex_redirect([], 'danger', ex_t('err_token'));
    }

    $do   = (string)($_POST['do'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);
    $back = json_decode((string)($_POST['back'] ?? ''), true);
    $back = is_array($back) ? $back : [];

    try {
        switch ($do) {
            case 'save':
                $cloneOf = (int)($_POST['clone_of'] ?? 0);
                $back = $id ? ['do' => 'edit', 'id' => $id] : ($cloneOf ? ['do' => 'clone', 'id' => $cloneOf] : ['do' => 'new']);
                ex_save($id);

            case 'toggle':
                $exam = exam_get($id) ?? throw new ExamException(ex_t('not_found'));
                $st   = (int)$exam['status'] === EXAM_STATUS_ENABLED ? EXAM_STATUS_DISABLED : EXAM_STATUS_ENABLED;
                $db->sql_query_prepared('UPDATE exams SET status = ?, updated_at = ? WHERE id = ?', [$st, exam_now(), $id]);
                ex_log("#{$id} status -> {$st}");
                ex_redirect([], 'success', ex_t($st === EXAM_STATUS_ENABLED ? 'msg_enabled' : 'msg_disabled', $exam['name']));

            case 'delete':
                $exam = exam_get($id) ?? throw new ExamException(ex_t('not_found'));
                $db->sql_query_prepared('DELETE FROM exam_progress WHERE exam_id = ?', [$id]);
                $db->sql_query_prepared('DELETE FROM exam_users WHERE exam_id = ?', [$id]);
                $db->sql_query_prepared('DELETE FROM exams WHERE id = ?', [$id]);
                ex_log("#{$id} '{$exam['name']}' deleted");
                ex_redirect([], 'success', ex_t('msg_deleted', $exam['name']));

            case 'assign':
                $back     = ['do' => 'users'];
                $username = trim((string)($_POST['username'] ?? ''));
                $u = $db->fetch_array($db->sql_query_prepared('SELECT id, username FROM users WHERE username = ? LIMIT 1', [$username]))
                    ?: throw new ExamException(ex_t('user_not_found'));
                $begin = ex_dt_input('begin');
                $end   = ex_dt_input('end');
                exam_assign_to_user((int)$u['id'], $id, ['id' => (int)$CURUSER['id'], 'staff' => true],
                    $begin ? exam_ts($begin) : null, $end ? exam_ts($end) : null);
                ex_log("#{$id} assigned to {$u['username']} ({$u['id']})");
                ex_redirect(['do' => 'users', 'exam_id' => $id], 'success', ex_t('msg_assigned', $u['username']));

            case 'avoid':
            case 'recover':
            case 'remove':
            case 'update_end':
            case 'finish':
                $eu   = exam_user_get((int)($_POST['euid'] ?? 0)) ?? throw new ExamException(ex_t('attempt_not_found'));
                $opts = ex_attempt_opts($do);
                ex_attempt_action($do, $eu, $opts);
                if ($do === 'finish') {
                    exam_cron_checkout();
                }
                ex_redirect($back, 'success', ex_t('done_' . $do));

            case 'bulk':
                ex_bulk($back);
        }
    } catch (ExamException $e) {
        ex_redirect($back, 'danger', $e->getMessage());
    }

    ex_redirect([], 'danger', ex_t('unknown_action'));
}

// ── Actions on attempts (one or many) ────────────────────

const EX_BULK_MAX = 500;

/** Parameters for an action, read from POST once (shared by single and bulk). */
function ex_attempt_opts(string $do): array
{
    if ($do === 'update_end') {
        return [
            'end'    => ex_dt_input('end') ?? throw new ExamException(ex_t('err_enter_end')),
            'reason' => mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 500),
        ];
    }
    if ($do === 'pm') {
        $subject = mb_substr(trim((string)($_POST['subject'] ?? '')), 0, 120);
        $message = trim((string)($_POST['message'] ?? ''));
        if ($subject === '' || $message === '') throw new ExamException(ex_t('err_subject_message'));
        return ['subject' => $subject, 'message' => $message];
    }
    return [];
}

/**
 * One action on one attempt. Throws ExamException when it does not apply
 * (bulk counts that as "skipped" and goes on).
 * finish: end is moved to the past; the caller runs exam_cron_checkout() once
 * afterwards, so the result is counted by the usual rules (passed / failed / disabled).
 */
function ex_attempt_action(string $do, array $eu, array $opts): void
{
    global $db, $CURUSER;

    $euId = (int)$eu['id'];
    $st   = (int)$eu['status'];

    switch ($do) {
        case 'avoid':
            if ($st !== EXAM_USER_STATUS_NORMAL) throw new ExamException(ex_t('err_only_avoid'));
            $db->sql_query_prepared('UPDATE exam_users SET status = ?, updated_at = ? WHERE id = ? AND status = ?',
                [EXAM_USER_STATUS_AVOIDED, exam_now(), $euId, EXAM_USER_STATUS_NORMAL]);
            ex_log("exam_user #{$euId} avoided");
            return;

        case 'recover':
            if ($st !== EXAM_USER_STATUS_AVOIDED) throw new ExamException(ex_t('err_only_recover'));
            if (exam_has_ongoing((int)$eu['uid'])) throw new ExamException(ex_t('err_has_ongoing'));
            $db->sql_query_prepared('UPDATE exam_users SET status = ?, updated_at = ? WHERE id = ? AND status = ?',
                [EXAM_USER_STATUS_NORMAL, exam_now(), $euId, EXAM_USER_STATUS_AVOIDED]);
            ex_log("exam_user #{$euId} recovered");
            return;

        case 'remove':
            $db->sql_query_prepared('DELETE FROM exam_progress WHERE exam_user_id = ?', [$euId]);
            $db->sql_query_prepared('DELETE FROM exam_users WHERE id = ?', [$euId]);
            ex_log("exam_user #{$euId} removed");
            return;

        case 'update_end':
            if ($st !== EXAM_USER_STATUS_NORMAL) throw new ExamException(ex_t('err_end_ongoing_only'));
            $exam   = exam_get((int)$eu['exam_id']) ?? throw new ExamException(ex_t('err_exam_not_found'));
            $newEnd = (string)$opts['end'];
            if (exam_ts($newEnd) < exam_user_begin($eu, $exam)) throw new ExamException(ex_t('err_end_earlier'));
            $oldEnd = exam_user_end($eu, $exam);
            $db->sql_query_prepared('UPDATE exam_users SET `end` = ?, updated_at = ? WHERE id = ?', [$newEnd, exam_now(), $euId]);
            exam_notify((int)$eu['uid'], "End time changed: {$exam['name']}",
                "The end time of [b]{$exam['name']}[/b] was changed from " . date('d.m.Y H:i', $oldEnd)
                . ' to ' . date('d.m.Y H:i', exam_ts($newEnd)) . '.'
                . "\nChanged by: " . (string)$CURUSER['username'] . ($opts['reason'] !== '' ? "\nReason: {$opts['reason']}" : ''));
            ex_log("exam_user #{$euId} end -> {$newEnd}");
            return;

        case 'finish':
            if ($st !== EXAM_USER_STATUS_NORMAL) throw new ExamException(ex_t('err_only_finish'));
            // A second in the past: exam_cron_checkout() picks it up right away
            $db->sql_query_prepared('UPDATE exam_users SET `end` = ?, updated_at = ? WHERE id = ? AND status = ?',
                [exam_dt(TIMENOW - 1), exam_now(), $euId, EXAM_USER_STATUS_NORMAL]);
            ex_log("exam_user #{$euId} finished now by staff");
            return;
    }
    throw new ExamException(ex_t('unknown_action'));
}

/** PM from the staff member (not from the system), one per user. */
function ex_send_staff_pm(int $uid, string $subject, string $message): void
{
    global $CURUSER;
    if (!function_exists('send_pm')) {
        require_once INC_PATH . '/functions_pm.php';
    }
    send_pm(['subject' => $subject, 'message' => $message, 'touid' => $uid], (int)$CURUSER['id'], true);
}

/** Bulk action on the selected attempts; one summary flash and one log line. */
function ex_bulk(array $back): never
{
    global $db;

    $do  = (string)($_POST['bulk_action'] ?? '');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['euids'] ?? [])))));
    if (!in_array($do, ['avoid', 'recover', 'remove', 'update_end', 'finish', 'pm'], true)) {
        throw new ExamException(ex_t('err_choose_action'));
    }
    if (!$ids) throw new ExamException(ex_t('err_select_one'));
    if (count($ids) > EX_BULK_MAX) throw new ExamException(ex_t('err_bulk_max', EX_BULK_MAX));

    $opts = ex_attempt_opts($do);

    $rows = [];
    $q = $db->sql_query_prepared(
        'SELECT * FROM exam_users WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
    while ($r = $db->fetch_array($q)) $rows[] = $r;

    $ok = 0;
    $skipped = [];
    if ($do === 'pm') {
        $sent = [];
        foreach ($rows as $eu) {
            $uid = (int)$eu['uid'];
            if (isset($sent[$uid])) continue;
            ex_send_staff_pm($uid, $opts['subject'], $opts['message']);
            $sent[$uid] = true;
            $ok++;
        }
    } else {
        foreach ($rows as $eu) {
            try {
                ex_attempt_action($do, $eu, $opts);
                $ok++;
            } catch (ExamException $e) {
                $skipped[$e->getMessage()] = ($skipped[$e->getMessage()] ?? 0) + 1;
            }
        }
        if ($do === 'finish' && $ok > 0) {
            exam_cron_checkout();
        }
    }
    $missing = count($ids) - count($rows);
    if ($missing > 0) $skipped[ex_t('attempt_not_found')] = ($skipped[ex_t('attempt_not_found')] ?? 0) + $missing;

    ex_log("bulk {$do}: {$ok} ok, " . array_sum($skipped) . ' skipped');

    $msg = ex_t('bulk_done_' . $do, exam_num($ok));
    foreach ($skipped as $why => $n) {
        $msg .= ' ' . ex_t('bulk_skipped', exam_num($n), rtrim($why, '.'));
    }
    if ($do === 'finish' && $ok > 0) $msg .= ' ' . ex_t('bulk_finish_note');
    ex_redirect($back, $ok > 0 ? 'success' : 'danger', $msg);
}

/** PM to everyone doing the exam now: what changed in the requirements. */
function ex_notify_requirements(int $examId, string $name, array $diff): int
{
    global $db, $CURUSER;

    $uids = [];
    $q = $db->sql_query_prepared('SELECT uid FROM exam_users WHERE exam_id = ? AND status = ?', [$examId, EXAM_USER_STATUS_NORMAL]);
    while ($r = $db->fetch_array($q)) $uids[(int)$r['uid']] = true;

    $list = implode("\n", array_map(static fn(string $l): string => '[*]' . str_replace(' -> ', ' → ', $l), $diff));
    foreach (array_keys($uids) as $uid) {
        exam_notify($uid, "Requirements changed: {$name}",
            "The requirements of [b]{$name}[/b] were changed:\n[list]\n{$list}\n[/list]\n"
            . 'Your progress since the assignment is kept and is now checked against the new requirements.'
            . "\nChanged by: " . (string)$CURUSER['username']);
    }
    return count($uids);
}

/**
 * Puts the exam's new time into attempts that are already in progress
 * (NexusPHP leaves them untouched). Returns the number of changed attempts.
 */
function ex_apply_time_to_ongoing(int $examId, string $name, ?string $begin, ?string $end, int $duration, ?string $recurring): int
{
    global $db, $CURUSER;

    $rows = [];
    $q = $db->sql_query_prepared(
        'SELECT id, uid, `begin`, `end`, created_at FROM exam_users WHERE exam_id = ? AND status = ?',
        [$examId, EXAM_USER_STATUS_NORMAL]
    );
    while ($r = $db->fetch_array($q)) $rows[] = $r;

    $changed = 0;
    foreach ($rows as $eu) {
        $oldBegin = exam_ts($eu['begin']) ?: exam_ts((string)$eu['created_at']);
        $oldEnd   = exam_ts($eu['end']);

        if ($begin && $end) {                       // fixed window
            [$newBegin, $newEnd] = [exam_ts($begin), exam_ts($end)];
        } elseif ($duration > 0) {                  // N days from the user's own start
            [$newBegin, $newEnd] = [$oldBegin, exam_add_days($oldBegin, $duration)];
        } elseif ($recurring) {                     // the current cycle
            [$newBegin, $newEnd] = exam_recurring_range($recurring, TIMENOW);
        } else {
            continue;
        }
        if ($newEnd <= $newBegin || ($newBegin === $oldBegin && $newEnd === $oldEnd)) continue;

        $db->sql_query_prepared(
            'UPDATE exam_users SET `begin` = ?, `end` = ?, updated_at = ? WHERE id = ? AND status = ?',
            [exam_dt($newBegin), exam_dt($newEnd), exam_now(), (int)$eu['id'], EXAM_USER_STATUS_NORMAL]
        );
        if ((int)$db->affected_rows() !== 1) continue;

        exam_notify((int)$eu['uid'], "Time changed: {$name}",
            "The time of [b]{$name}[/b] was changed from "
            . date('d.m.Y H:i', $oldBegin) . ' - ' . date('d.m.Y H:i', $oldEnd) . ' to '
            . date('d.m.Y H:i', $newBegin) . ' - ' . date('d.m.Y H:i', $newEnd) . '.'
            . "\nChanged by: " . (string)$CURUSER['username']);
        $changed++;
    }

    if ($changed > 0) ex_log("#{$examId} new time applied to {$changed} ongoing attempt(s)");
    return $changed;
}

function ex_save(int $id): never
{
    global $db;

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 255) throw new ExamException(ex_t('err_name'));
    $type = (int)($_POST['type'] ?? EXAM_TYPE_EXAM) === EXAM_TYPE_TASK ? EXAM_TYPE_TASK : EXAM_TYPE_EXAM;

    // Time: the selected mode, the other fields are cleared
    $mode      = (string)($_POST['time_mode'] ?? 'duration');
    $begin     = $mode === 'range' ? ex_dt_input('begin') : null;
    $end       = $mode === 'range' ? ex_dt_input('end') : null;
    $duration  = $mode === 'duration' ? max(0, (int)($_POST['duration'] ?? 0)) : 0;
    $recurring = $mode === 'recurring' ? (string)($_POST['recurring'] ?? '') : null;
    if ($recurring !== null && !isset(EXAM_RECURRING[$recurring])) $recurring = null;
    $hours = ex_check_begin_end($begin, $end, $duration, $recurring);

    $indexes = [];
    foreach (array_keys(EXAM_INDEXES) as $k) {
        $indexes[] = [
            'index'         => $k,
            'require_value' => trim((string)($_POST['idx_value'][$k] ?? '0')),
            'checked'       => !empty($_POST['idx_checked'][$k]),
        ];
    }
    ex_check_indexes($indexes, $hours);
    foreach ($indexes as &$i) { $i['require_value'] = (int)$i['require_value']; }
    unset($i);

    $filters = ex_filters_from_post();

    $color = (string)($_POST['background_color'] ?? 'blue');
    $data = [
        'name'                 => $name,
        'description'          => trim((string)($_POST['description'] ?? '')),
        'begin'                => $begin,
        'end'                  => $end,
        'duration'             => $duration,
        'recurring'            => $recurring,
        'filters'              => json_encode($filters, JSON_UNESCAPED_UNICODE),
        'indexes'              => json_encode($indexes),
        'status'               => !empty($_POST['enabled']) ? EXAM_STATUS_ENABLED : EXAM_STATUS_DISABLED,
        'is_discovered'        => $type === EXAM_TYPE_EXAM && !empty($_POST['is_discovered']) ? 1 : 0,
        'priority'             => (int)($_POST['priority'] ?? 0),
        'type'                 => $type,
        // Reward: tasks (NexusPHP) and, our addition, exams too
        'success_reward_bonus' => max(0, (int)($_POST['success_reward_bonus'] ?? 0)),
        'fail_deduct_bonus'    => $type === EXAM_TYPE_TASK ? max(0, (int)($_POST['fail_deduct_bonus'] ?? 0)) : 0,
        'max_user_count'       => $type === EXAM_TYPE_TASK ? max(0, (int)($_POST['max_user_count'] ?? 0)) : 0,
        'background_color'     => isset(EXAM_BG[$color]) ? $color : 'blue',
        'updated_at'           => exam_now(),
    ];

    if ($id > 0) {
        $old = exam_get($id) ?? throw new ExamException(ex_t('not_found'));

        // Ongoing attempts always use the current requirements: a change hits them at once
        $diff    = ex_indexes_diff($old['indexes'], $indexes);
        $ongoing = $diff ? exam_ongoing_count($id) : 0;
        if ($ongoing > 0 && empty($_POST['confirm_requirements'])) {
            throw new ExamException(ex_t('err_req_changed', implode('; ', $diff), $ongoing));
        }

        $set = implode(', ', array_map(static fn($k) => "`{$k}` = ?", array_keys($data)));
        $db->sql_query_prepared("UPDATE exams SET {$set} WHERE id = ?", [...array_values($data), $id]);
        ex_log("#{$id} '{$name}' updated" . ($diff ? ': ' . implode('; ', $diff) . ($ongoing ? " ({$ongoing} ongoing)" : '') : ''));

        $notified = 0;
        if ($ongoing > 0 && !empty($_POST['notify_requirements'])) {
            $notified = ex_notify_requirements($id, $name, $diff);
        }

        $applied = !empty($_POST['apply_ongoing'])
            ? ex_apply_time_to_ongoing($id, $name, $begin, $end, $duration, $recurring)
            : 0;
        ex_redirect([], 'success', ex_t('msg_saved', $name)
            . ($diff && $ongoing > 0 ? ' ' . ex_t($notified > 0 ? 'msg_req_applied_pm' : 'msg_req_applied', exam_num($ongoing)) : '')
            . ($applied > 0 ? ' ' . ex_t('msg_time_applied', $applied) : ''));
    }

    $data['created_at'] = exam_now();
    $cols = implode(', ', array_map(static fn($k) => "`{$k}`", array_keys($data)));
    $ph   = implode(', ', array_fill(0, count($data), '?'));
    $db->sql_query_prepared("INSERT INTO exams ({$cols}) VALUES ({$ph})", array_values($data));
    ex_log("'{$name}' created");
    ex_redirect([], 'success', ex_t('msg_created', $name));
}

// ── Render: common ───────────────────────────────────────

function ex_header(string $icon, string $title, string $sub, string $tone, string $right = ''): string
{
    return '<div class="ex-card ex-head"><div class="ex-head__icon ex-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></div>'
        . '<div class="ex-head__text"><h1>' . ex_e($title) . '</h1><p>' . ex_e($sub) . '</p></div>'
        . ($right !== '' ? '<div class="ex-head__right">' . $right . '</div>' : '') . '</div>';
}

function ex_kpis(array $items): string
{
    $h = '<div class="ex-kpis">';
    foreach ($items as [$icon, $tone, $value, $label]) {
        $h .= '<div class="ex-card ex-kpi"><div class="ex-kpi__icon ex-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></div>'
            . '<div><div class="ex-kpi__value">' . ex_e((string)$value) . '</div><div class="ex-kpi__label">' . ex_e($label) . '</div></div></div>';
    }
    return $h . '</div>';
}

function ex_nav(string $active): string
{
    $items = ['' => ['fa-list', ex_t('list_title')], 'users' => ['fa-users', ex_t('nav_users')]];
    $h = '<div class="ex-tabs">';
    foreach ($items as $k => [$icon, $label]) {
        $h .= '<a class="ex-tab' . ($k === $active ? ' is-active' : '') . '" href="' . ex_e(ex_self(['do' => $k])) . '"><i class="fa-solid ' . $icon . '"></i>' . $label . '</a>';
    }
    return $h . '</div>';
}

// ── Render: exam list ────────────────────────────────────

function ex_render_list(): string
{
    global $db;

    $ongoing = [];
    $q = $db->sql_query_prepared('SELECT exam_id, COUNT(*) AS n FROM exam_users WHERE status = ? GROUP BY exam_id', [EXAM_USER_STATUS_NORMAL]);
    while ($r = $db->fetch_array($q)) $ongoing[(int)$r['exam_id']] = (int)$r['n'];

    $stats = exam_stats();

    $exams = [];
    $q = $db->sql_query_prepared('SELECT * FROM exams ORDER BY priority DESC, id DESC', []);
    while ($r = $db->fetch_array($q)) $exams[] = exam_decode($r);

    $cnt = ['exam' => 0, 'task' => 0, 'on' => 0];
    foreach ($exams as $x) {
        (int)$x['type'] === EXAM_TYPE_TASK ? $cnt['task']++ : $cnt['exam']++;
        if ((int)$x['status'] === EXAM_STATUS_ENABLED) $cnt['on']++;
    }

    $h  = ex_header('fa-graduation-cap', ex_t('list_title'), ex_t('list_sub'), 'primary',
        '<a class="ex-btn ex-btn--primary" href="' . ex_e(ex_self(['do' => 'new'])) . '"><i class="fa-solid fa-plus"></i>' . ex_e(ex_t('create')) . '</a>');
    $h .= ex_flash_html() . ex_nav('');
    $h .= ex_kpis([
        ['fa-graduation-cap',  'primary', $cnt['exam'],        ex_t('kpi_exams')],
        ['fa-list-check',      'info',    $cnt['task'],        ex_t('kpi_tasks')],
        ['fa-toggle-on',       'success', $cnt['on'],          ex_t('kpi_enabled')],
        ['fa-hourglass-half',  'warning', array_sum($ongoing), ex_t('kpi_in_progress')],
    ]);

    if (!$exams) {
        return $h . '<div class="ex-card ex-empty"><i class="fa-solid fa-graduation-cap"></i><p>' . ex_e(ex_t('list_empty')) . '</p>'
            . '<a class="ex-btn ex-btn--primary" href="' . ex_e(ex_self(['do' => 'new'])) . '"><i class="fa-solid fa-plus"></i>' . ex_e(ex_t('create')) . '</a></div>';
    }

    $h .= '<div class="ex-card ex-table-wrap"><table class="ex-table"><thead><tr>'
        . '<th>' . ex_e(ex_t('th_name')) . '</th><th>' . ex_e(ex_t('th_indexes')) . '</th><th>' . ex_e(ex_t('th_time')) . '</th><th>' . ex_e(ex_t('th_target')) . '</th><th class="ex-num">' . ex_e(ex_t('th_ongoing')) . '</th><th class="ex-num">' . ex_e(ex_t('th_results')) . '</th><th></th></tr></thead><tbody>';

    foreach ($exams as $x) {
        $id     = (int)$x['id'];
        $on     = (int)$x['status'] === EXAM_STATUS_ENABLED;
        $isTask = (int)$x['type'] === EXAM_TYPE_TASK;
        $tags   = '<span class="ex-badge ex-tone-' . ($isTask ? 'info' : 'primary') . '">' . exam_type_text($x) . '</span>'
                . (!$on ? '<span class="ex-badge ex-tone-secondary">' . ex_e(ex_t('disabled')) . '</span>' : '')
                . ((int)$x['is_discovered'] === 1 ? '<span class="ex-badge ex-tone-warning"><i class="fa-solid fa-robot"></i>' . ex_e(ex_t('auto_assign_badge')) . '</span>' : '')
                . ((int)$x['priority'] !== 0 ? '<span class="ex-badge ex-tone-secondary">' . ex_e(ex_t('priority_badge', (int)$x['priority'])) . '</span>' : '');
        $extra  = $isTask
            ? '<div class="ex-sub">' . ex_e(ex_t('reward_penalty', exam_num((int)$x['success_reward_bonus']), exam_num((int)$x['fail_deduct_bonus']))) . '</div>'
            : '<div class="ex-sub">' . ex_e((int)$x['success_reward_bonus'] > 0 ? ex_t('reward_fail_disabled', exam_num((int)$x['success_reward_bonus'])) : ex_t('fail_disabled')) . '</div>';
        $count  = ($ongoing[$id] ?? 0) . ($isTask ? ' / ' . ((int)$x['max_user_count'] ?: '∞') : '');
        $st     = $stats[$id] ?? null;
        $anom   = exam_anomaly($st);
        $result = $st
            ? ($anom !== null
                ? '<span class="ex-badge ex-tone-' . ($anom === 'low' ? 'danger' : 'warning') . '" title="' . ex_e(exam_anomaly_hint($anom, $isTask)) . '">'
                  . '<i class="fa-solid fa-triangle-exclamation"></i>' . ex_rate($st['pass_rate']) . '</span> ' . ex_e(ex_t('passed'))
                : '<strong>' . ex_rate($st['pass_rate']) . '</strong> ' . ex_e(ex_t('passed')))
              . '<div class="ex-sub">' . exam_num($st['passed']) . ' / ' . exam_num($st['finished'])
              . ($st['disabled'] > 0 ? ' &middot; ' . exam_num($st['disabled']) . ' ' . ex_e(ex_t('disabled')) : '')
              . ($st['abandoned'] > 0 ? ' &middot; ' . exam_num($st['abandoned']) . ' ' . ex_e(ex_t('abandoned')) : '')
              . ($st['avg_seconds'] !== null ? ' &middot; ' . ex_e(ex_t('avg')) . ' ' . ex_duration($st['avg_seconds']) : '') . '</div>'
            : '<span class="ex-sub">' . ex_e(ex_t('no_results')) . '</span>';

        $h .= '<tr class="' . ($on ? '' : 'ex-row-off') . '">'
            . '<td><a class="ex-name" href="' . ex_e(ex_self(['do' => 'edit', 'id' => $id])) . '">' . ex_e((string)$x['name']) . '</a><div>' . $tags . '</div>' . $extra . '</td>'
            . '<td>' . ex_indexes_html($x) . '</td>'
            . '<td>' . ex_time_html($x) . '</td>'
            . '<td class="ex-small">' . ex_filters_html($x) . '</td>'
            . '<td class="ex-num"><a href="' . ex_e(ex_self(['do' => 'users', 'exam_id' => $id])) . '">' . $count . '</a></td>'
            . '<td class="ex-num ex-small">' . $result . '</td>'
            . '<td class="ex-actions">'
            . '<a class="ex-icon-btn" title="' . ex_e(ex_t('title_participants')) . '" href="' . ex_e(ex_self(['do' => 'users', 'exam_id' => $id])) . '"><i class="fa-solid fa-users"></i></a>'
            . '<a class="ex-icon-btn" title="' . ex_e(ex_t('title_edit')) . '" href="' . ex_e(ex_self(['do' => 'edit', 'id' => $id])) . '"><i class="fa-solid fa-pen"></i></a>'
            . '<a class="ex-icon-btn" title="' . ex_e(ex_t('clone')) . '" href="' . ex_e(ex_self(['do' => 'clone', 'id' => $id])) . '"><i class="fa-solid fa-clone"></i></a>'
            . '<form method="post" action="' . ex_e(ex_self()) . '">' . ex_hidden('toggle', ['id' => $id])
            . '<button class="ex-icon-btn" title="' . ex_e($on ? ex_t('title_disable') : ex_t('title_enable')) . '"><i class="fa-solid ' . ($on ? 'fa-toggle-on' : 'fa-toggle-off') . '"></i></button></form>'
            . '<form method="post" action="' . ex_e(ex_self()) . '" data-ex-confirm="' . ex_e(ex_t('confirm_delete', (string)$x['name'])) . '" data-ex-tone="danger">'
            . ex_hidden('delete', ['id' => $id]) . '<button class="ex-icon-btn ex-icon-btn--danger" title="' . ex_e(ex_t('title_delete')) . '"><i class="fa-solid fa-trash"></i></button></form>'
            . '</td></tr>';
    }
    return $h . '</tbody></table></div>';
}

// ── Render: form ─────────────────────────────────────────

function ex_render_form(?array $x, bool $clone = false): string
{
    $new     = $x === null || $clone;
    $cloneOf = 0;
    if ($clone && $x !== null) {
        // Copy of an existing exam: everything kept, saved as a new one.
        // Disabled by default so an auto-assign exam isn't handed out before the time is fixed.
        $cloneOf          = (int)$x['id'];
        $srcName          = (string)$x['name'];
        $x['id']          = 0;
        $x['name']        = mb_substr($srcName . ' ' . ex_t('copy'), 0, 255);
        $x['status']      = EXAM_STATUS_DISABLED;
    }
    $x ??= exam_decode([
        'id' => 0, 'name' => '', 'description' => '', 'begin' => null, 'end' => null, 'duration' => 30, 'recurring' => null,
        'filters' => '', 'indexes' => json_encode([
            ['index' => EXAM_INDEX_DOWNLOADED,  'require_value' => 200,  'checked' => true],
            ['index' => EXAM_INDEX_UPLOADED,    'require_value' => 200,  'checked' => true],
            ['index' => EXAM_INDEX_SEED_POINTS, 'require_value' => 3000, 'checked' => true],
        ]),
        'status' => EXAM_STATUS_ENABLED, 'is_discovered' => 0, 'priority' => 0, 'type' => EXAM_TYPE_EXAM,
        'success_reward_bonus' => 0, 'fail_deduct_bonus' => 0, 'max_user_count' => 0, 'background_color' => 'blue',
    ]);
    $id    = (int)$x['id'];
    $f     = $x['filters'];
    $byIdx = [];
    foreach ($x['indexes'] as $i) $byIdx[(int)$i['index']] = $i;

    $mode = !empty($x['begin']) ? 'range' : (!empty($x['recurring']) ? 'recurring' : 'duration');
    $sel  = static fn(bool $b): string => $b ? ' selected' : '';
    $chk  = static fn(bool $b): string => $b ? ' checked' : '';
    $isTask = (int)$x['type'] === EXAM_TYPE_TASK;

    // Indexes
    $idxRows = '';
    $units   = [EXAM_INDEX_UPLOADED => 'GB', EXAM_INDEX_DOWNLOADED => 'GB', EXAM_INDEX_SEED_TIME_AVERAGE => ex_t('hours')];
    $hints   = [
        EXAM_INDEX_SEED_BONUS  => ex_t('hint_seed_bonus'),
        EXAM_INDEX_SEED_POINTS => ex_t('hint_seed_points'),
        EXAM_INDEX_SEED_TIME_AVERAGE => ex_t('hint_seed_time_avg'),
        EXAM_INDEX_UPLOAD_TORRENT_COUNT => ex_t('hint_upload_count'),
    ];
    foreach (EXAM_INDEXES as $k => $meta) {
        $cur = $byIdx[$k] ?? ['require_value' => 0, 'checked' => false];
        $idxRows .= '<label class="ex-idx' . (!empty($cur['checked']) ? ' is-on' : '') . '">'
            . '<input type="checkbox" name="idx_checked[' . $k . ']" value="1"' . $chk(!empty($cur['checked'])) . '>'
            . '<span class="ex-idx__name">' . ex_e($meta['name']) . ($hints[$k] ?? '' ? '<small>' . ex_e($hints[$k]) . '</small>' : '') . '</span>'
            . '<input type="number" min="0" step="1" name="idx_value[' . $k . ']" value="' . (int)$cur['require_value'] . '">'
            . '<span class="ex-idx__unit">' . ex_e($units[$k] ?? '') . '</span></label>';
    }

    // Groups
    $groups = '';
    $selG   = array_map('intval', $f[EXAM_FILTER_USER_CLASS] ?? []);
    $staff  = exam_staff_groups();
    foreach (ex_groups() as $gid => $title) {
        if (isset($staff[$gid])) continue; // staff never gets exams, no point in offering it
        $groups .= '<label class="ex-chip"><input type="checkbox" name="classes[]" value="' . $gid . '"' . $chk(in_array($gid, $selG, true)) . '><span>' . ex_e($title) . '</span></label>';
    }
    $donate = $f[EXAM_FILTER_USER_DONATE] ?? [];
    $reg    = $f[EXAM_FILTER_USER_REGISTER_TIME_RANGE] ?? [null, null];
    $days   = $f[EXAM_FILTER_USER_REGISTER_DAYS_RANGE] ?? [null, null];

    $recOpts = '';
    foreach (EXAM_RECURRING as $k => $label) $recOpts .= '<option value="' . $k . '"' . $sel((string)$x['recurring'] === $k) . '>' . $label . '</option>';
    $colorOpts = '';
    $colorNames = ['blue' => ex_t('color_blue'), 'green' => ex_t('color_green'), 'red' => ex_t('color_red'), 'orange' => ex_t('color_orange'), 'grey' => ex_t('color_grey'), 'cyan' => ex_t('color_cyan')];
    foreach ($colorNames as $k => $label) $colorOpts .= '<option value="' . $k . '"' . $sel((string)$x['background_color'] === $k) . '>' . $label . '</option>';

    $ongoing  = $new ? 0 : exam_ongoing_count($id);
    $applyBox = $ongoing > 0
        ? '<div class="ex-switches"><label class="ex-switch"><input type="checkbox" name="apply_ongoing" value="1" checked>'
          . '<span><strong>' . ex_e(ex_t('apply_ongoing_title')) . '</strong><small>' . ex_e(ex_t('apply_ongoing_hint', $ongoing)) . '</small></span></label></div>'
        : '';

    $h  = ex_header(
        $cloneOf ? 'fa-clone' : ($new ? 'fa-plus' : 'fa-pen'),
        $cloneOf ? ex_t('form_clone_title', $srcName) : ($new ? ex_t('form_new_title') : ex_t('form_edit_title', $x['name'])),
        $cloneOf
            ? ex_t($isTask ? 'form_clone_sub_task' : 'form_clone_sub_exam')
            : ex_t('form_sub'),
        $new ? 'success' : 'primary',
        (!$new ? '<a class="ex-btn" href="' . ex_e(ex_self(['do' => 'clone', 'id' => $id])) . '"><i class="fa-solid fa-clone"></i>' . ex_e(ex_t('clone')) . '</a> ' : '')
        . '<a class="ex-btn" href="' . ex_e(ex_self()) . '"><i class="fa-solid fa-arrow-left"></i>' . ex_e(ex_t('back')) . '</a>');
    $h .= ex_flash_html();

    // Requirements as saved, for the "requirements changed" confirm (exams.js)
    $origIdx = [];
    foreach ($x['indexes'] as $i) {
        if (!empty($i['checked'])) $origIdx[(int)$i['index']] = (int)$i['require_value'];
    }
    $idxMeta = [];
    foreach (EXAM_INDEXES as $k => $m) $idxMeta[$k] = [$m['name'], $m['unit']];

    $h .= '<form method="post" action="' . ex_e(ex_self()) . '" id="ex-form"'
        . ($ongoing > 0 ? ' data-ex-ongoing="' . $ongoing . '" data-ex-orig-idx="' . ex_e(json_encode((object)$origIdx)) . '"'
                        . ' data-ex-idx-meta="' . ex_e(json_encode((object)$idxMeta)) . '"' : '') . '>'
        . ex_hidden('save', ['id' => $id] + ($cloneOf ? ['clone_of' => $cloneOf] : []))
        . '<input type="hidden" name="confirm_requirements" value=""><input type="hidden" name="notify_requirements" value="">'

        // General
        . '<div class="ex-card ex-section"><h2><i class="fa-solid fa-circle-info"></i>' . ex_e(ex_t('sec_general')) . '</h2><div class="ex-grid">'
        . '<div class="ex-field"><label>' . ex_e(ex_t('type')) . '</label><div class="ex-seg">'
        . '<label><input type="radio" name="type" value="' . EXAM_TYPE_EXAM . '"' . $chk(!$isTask) . '><span><i class="fa-solid fa-graduation-cap"></i>' . ex_e(ex_t('type_exam')) . '</span></label>'
        . '<label><input type="radio" name="type" value="' . EXAM_TYPE_TASK . '"' . $chk($isTask) . '><span><i class="fa-solid fa-list-check"></i>' . ex_e(ex_t('type_task')) . '</span></label>'
        . '</div><small class="ex-help" data-for-type="1">' . ex_e(ex_t('help_exam')) . '</small>'
        . '<small class="ex-help" data-for-type="2">' . ex_e(ex_t('help_task')) . '</small></div>'
        . '<div class="ex-field ex-span-2"><label for="ex-name">' . ex_e(ex_t('name')) . '</label><input id="ex-name" name="name" maxlength="255" required value="' . ex_e((string)$x['name']) . '"></div>'
        . '<div class="ex-field ex-span-3"><label for="ex-desc">' . ex_e(ex_t('description')) . ' <small>' . ex_e(ex_t('description_hint')) . '</small></label>'
        . '<textarea id="ex-desc" name="description" rows="3">' . ex_e((string)$x['description']) . '</textarea></div>'
        . '</div></div>'

        // Indexes
        . '<div class="ex-card ex-section"><h2><i class="fa-solid fa-bullseye"></i>' . ex_e(ex_t('sec_indexes')) . ' <small>' . ex_e(ex_t('sec_indexes_hint')) . '</small></h2>'
        . '<div class="ex-idxs">' . $idxRows . '</div></div>'

        // Time
        . '<div class="ex-card ex-section"><h2><i class="fa-solid fa-clock"></i>' . ex_e(ex_t('sec_time')) . ' <small>' . ex_e(ex_t('sec_time_hint')) . '</small></h2>'
        . '<div class="ex-seg ex-seg--wide">'
        . '<label><input type="radio" name="time_mode" value="duration"' . $chk($mode === 'duration') . '><span>' . ex_e(ex_t('mode_duration')) . '</span></label>'
        . '<label><input type="radio" name="time_mode" value="range"' . $chk($mode === 'range') . '><span>' . ex_e(ex_t('mode_range')) . '</span></label>'
        . '<label><input type="radio" name="time_mode" value="recurring"' . $chk($mode === 'recurring') . '><span>' . ex_e(ex_t('mode_recurring')) . '</span></label></div>'
        . '<div class="ex-grid">'
        . '<div class="ex-field" data-mode="duration"><label for="ex-dur">' . ex_e(ex_t('duration_days')) . '</label><input id="ex-dur" type="number" min="1" name="duration" value="' . max(1, (int)$x['duration']) . '"></div>'
        . '<div class="ex-field" data-mode="range"><label for="ex-begin">' . ex_e(ex_t('begin')) . '</label>' . ex_date_input('ex-begin', 'begin', ex_dt_value($x['begin'])) . '</div>'
        . '<div class="ex-field" data-mode="range"><label for="ex-end">' . ex_e(ex_t('end')) . '</label>' . ex_date_input('ex-end', 'end', ex_dt_value($x['end']), 'ex-begin') . '</div>'
        . '<div class="ex-field" data-mode="recurring"><label for="ex-rec">' . ex_e(ex_t('period')) . '</label><select id="ex-rec" name="recurring">' . $recOpts . '</select>'
        . '<small class="ex-help">' . ex_e(ex_t('help_recurring')) . '</small></div>'
        . '</div>' . $applyBox . '</div>'

        // Target users
        . '<div class="ex-card ex-section"><h2><i class="fa-solid fa-filter"></i>' . ex_e(ex_t('sec_target')) . ' <small>' . ex_e(ex_t('sec_target_hint')) . '</small></h2>'
        . '<div class="ex-field"><label>' . ex_e(ex_t('groups')) . '</label><div class="ex-chips">' . $groups . '</div></div>'
        . '<div class="ex-grid">'
        . '<div class="ex-field"><label for="ex-rf">' . ex_e(ex_t('registered_from')) . '</label>' . ex_date_input('ex-rf', 'reg_from', ex_dt_value($reg[0] ?? null)) . '</div>'
        . '<div class="ex-field"><label for="ex-rt">' . ex_e(ex_t('registered_until')) . '</label>' . ex_date_input('ex-rt', 'reg_to', ex_dt_value($reg[1] ?? null), 'ex-rf') . '</div>'
        . '<div class="ex-field"><label>' . ex_e(ex_t('donor')) . '</label><div class="ex-chips">'
        . '<label class="ex-chip"><input type="checkbox" name="donate[]" value="yes"' . $chk(in_array('yes', $donate, true)) . '><span>' . ex_e(ex_t('yes')) . '</span></label>'
        . '<label class="ex-chip"><input type="checkbox" name="donate[]" value="no"' . $chk(in_array('no', $donate, true)) . '><span>' . ex_e(ex_t('no')) . '</span></label></div></div>'
        . '<div class="ex-field"><label for="ex-df">' . ex_e(ex_t('days_min')) . '</label><input id="ex-df" type="number" min="0" name="days_from" value="' . ex_e((string)($days[0] ?? '')) . '"></div>'
        . '<div class="ex-field"><label for="ex-dt">' . ex_e(ex_t('days_max')) . '</label><input id="ex-dt" type="number" min="0" name="days_to" value="' . ex_e((string)($days[1] ?? '')) . '"></div>'
        . '</div>'
        . '<div class="ex-sim">'
        . '<button type="button" class="ex-btn" data-ex-simulate><i class="fa-solid fa-users-viewfinder"></i>' . ex_e(ex_t('sim_button')) . '</button>'
        . '<span class="ex-sub">' . ex_e(ex_t('sim_hint')) . '</span>'
        . '<div class="ex-sim__out" data-ex-sim-out aria-live="polite"></div></div>'
        . '</div>'

        // Reward (both types), penalty and participant limit (tasks only)
        . '<div class="ex-card ex-section"><h2><i class="fa-solid fa-coins"></i><span data-for-type="1">' . ex_e(ex_t('sec_reward')) . '</span><span data-for-type="2">' . ex_e(ex_t('sec_reward_penalty')) . '</span></h2><div class="ex-grid">'
        . '<div class="ex-field"><label for="ex-rw"><span data-for-type="1">' . ex_e(ex_t('reward_passing')) . '</span><span data-for-type="2">' . ex_e(ex_t('reward_completing')) . '</span> <small>' . ex_e(ex_t('reward_hint')) . '</small></label><input id="ex-rw" type="number" min="0" name="success_reward_bonus" value="' . (int)$x['success_reward_bonus'] . '"></div>'
        . '<div class="ex-field" data-for-type="2"><label for="ex-fd">' . ex_e(ex_t('penalty_failing')) . '</label><input id="ex-fd" type="number" min="0" name="fail_deduct_bonus" value="' . (int)$x['fail_deduct_bonus'] . '"></div>'
        . '<div class="ex-field" data-for-type="2"><label for="ex-max">' . ex_e(ex_t('max_participants')) . ' <small>' . ex_e(ex_t('zero_unlimited')) . '</small></label><input id="ex-max" type="number" min="0" name="max_user_count" value="' . (int)$x['max_user_count'] . '"></div>'
        . '</div></div>'

        // Other
        . '<div class="ex-card ex-section"><h2><i class="fa-solid fa-sliders"></i>' . ex_e(ex_t('sec_other')) . '</h2><div class="ex-grid">'
        . '<div class="ex-field"><label for="ex-pr">' . ex_e(ex_t('priority')) . ' <small>' . ex_e(ex_t('priority_hint')) . '</small></label><input id="ex-pr" type="number" name="priority" value="' . (int)$x['priority'] . '"></div>'
        . '<div class="ex-field"><label for="ex-bg">' . ex_e(ex_t('progress_color')) . '</label><select id="ex-bg" name="background_color">' . $colorOpts . '</select></div>'
        . '</div><div class="ex-switches">'
        . '<label class="ex-switch" data-for-type="1"><input type="checkbox" name="is_discovered" value="1"' . $chk((int)$x['is_discovered'] === 1) . '>'
        . '<span><strong>' . ex_e(ex_t('auto_assign')) . '</strong><small>' . ex_e(ex_t('auto_assign_hint')) . '</small></span></label>'
        . '<label class="ex-switch"><input type="checkbox" name="enabled" value="1"' . $chk((int)$x['status'] === EXAM_STATUS_ENABLED) . '>'
        . '<span><strong>' . ex_e(ex_t('enabled')) . '</strong><small>' . ex_e(ex_t('enabled_hint')) . '</small></span></label>'
        . '</div></div>'

        . '<div class="ex-bar"><a class="ex-btn" href="' . ex_e(ex_self()) . '">' . ex_e(ex_t('cancel')) . '</a>'
        . '<button class="ex-btn ex-btn--primary"><i class="fa-solid fa-floppy-disk"></i>' . ex_e($new ? ex_t('create') : ex_t('save')) . '</button></div>'
        . '</form>';

    return $h;
}

// ── Render: participants ─────────────────────────────────

function ex_render_users(): string
{
    global $db;

    $examId = (int)($_GET['exam_id'] ?? 0);
    $uname  = trim((string)($_GET['username'] ?? ''));
    $status = (string)($_GET['status'] ?? (string)EXAM_USER_STATUS_NORMAL);
    $isDone = (string)($_GET['is_done'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $filter = ['do' => 'users', 'exam_id' => $examId ?: '', 'username' => $uname, 'status' => $status, 'is_done' => $isDone];
    $back   = json_encode(array_merge($filter, ['page' => $page]));

    $where = ['1 = 1'];
    $p     = [];
    if ($examId > 0)                              { $where[] = 'eu.exam_id = ?'; $p[] = $examId; }
    if ($uname !== '') {
        // "#123" or "123" also finds by user id
        if (preg_match('/^#?(\d+)$/', $uname, $m)) { $where[] = '(u.username = ? OR u.id = ?)'; array_push($p, $uname, (int)$m[1]); }
        else                                        { $where[] = 'u.username = ?'; $p[] = $uname; }
    }
    if ($status !== '' && is_numeric($status))    { $where[] = 'eu.status = ?';  $p[] = (int)$status; }
    if ($isDone !== '' && is_numeric($isDone))    { $where[] = 'eu.is_done = ?'; $p[] = (int)$isDone; }
    $w = implode(' AND ', $where);

    $total = (int)($db->fetch_array($db->sql_query_prepared(
        "SELECT COUNT(*) AS n FROM exam_users eu JOIN users u ON u.id = eu.uid WHERE {$w}", $p))['n'] ?? 0);

    $stat = [];
    $q = $db->sql_query_prepared('SELECT status, COUNT(*) AS n FROM exam_users' . ($examId ? ' WHERE exam_id = ?' : '') . ' GROUP BY status', $examId ? [$examId] : []);
    while ($r = $db->fetch_array($q)) $stat[(int)$r['status']] = (int)$r['n'];
    $done = (int)($db->fetch_array($db->sql_query_prepared(
        'SELECT COUNT(*) AS n FROM exam_users WHERE status = ? AND is_done = 1' . ($examId ? ' AND exam_id = ?' : ''),
        $examId ? [EXAM_USER_STATUS_NORMAL, $examId] : [EXAM_USER_STATUS_NORMAL]))['n'] ?? 0);

    $exams = [];
    $q = $db->sql_query_prepared('SELECT * FROM exams ORDER BY id DESC', []);
    while ($r = $db->fetch_array($q)) $exams[(int)$r['id']] = exam_decode($r);

    $h  = ex_header('fa-users', ex_t('nav_users'), $examId && isset($exams[$examId]) ? exam_type_text($exams[$examId]) . ': ' . $exams[$examId]['name'] : ex_t('all_exams_tasks'), 'primary');
    $h .= ex_flash_html() . ex_nav('users');
    $h .= ex_kpis([
        ['fa-hourglass-half', 'warning',   $stat[EXAM_USER_STATUS_NORMAL] ?? 0,   ex_t('kpi_ongoing')],
        ['fa-circle-check',   'success',   $done,                                 ex_t('kpi_met')],
        ['fa-flag-checkered', 'primary',   $stat[EXAM_USER_STATUS_FINISHED] ?? 0, ex_t('kpi_finished')],
        ['fa-user-shield',    'secondary', $stat[EXAM_USER_STATUS_AVOIDED] ?? 0,  ex_t('kpi_avoided')],
    ]);

    // Results of finished attempts
    $rs     = exam_stats($examId ?: null)[$examId ?: 0] ?? null;
    $selX   = $examId && isset($exams[$examId]) ? $exams[$examId] : null;
    $isTaskSel = $selX !== null && (int)$selX['type'] === EXAM_TYPE_TASK;
    if ($rs && $rs['finished'] > 0) {
        $h .= ex_kpis([
            ['fa-chart-pie',  'info',    ex_rate($rs['pass_rate']),     ex_t('kpi_pass_rate', $rs['passed'], $rs['finished'])],
            ['fa-stopwatch',  'primary', ex_duration($rs['avg_seconds']), ex_t('kpi_avg_time')],
            ['fa-circle-xmark', 'warning', $rs['failed'],
                $selX === null ? ex_t('kpi_failed_nodisable') : ($isTaskSel ? ex_t('kpi_failed_penalty') : ex_t('kpi_failed_staff'))],
            $isTaskSel
                ? ['fa-person-walking-arrow-right', 'secondary', $rs['abandoned'], ex_t('kpi_abandoned')]
                : ['fa-user-slash', 'danger',  $rs['disabled'], ex_t('kpi_disabled_accounts')],
        ]);
    }

    // Filter + assign
    $examOpts = '<option value="">' . ex_e(ex_t('all')) . '</option>';
    $assignOpts = '';
    foreach ($exams as $id => $x) {
        $examOpts .= '<option value="' . $id . '"' . ($id === $examId ? ' selected' : '') . '>' . ex_e(exam_type_text($x) . ': ' . $x['name']) . '</option>';
        if ((int)$x['type'] === EXAM_TYPE_EXAM && (int)$x['status'] === EXAM_STATUS_ENABLED) {
            $assignOpts .= '<option value="' . $id . '"' . ($id === $examId ? ' selected' : '') . '>' . ex_e($x['name']) . '</option>';
        }
    }
    $stOpts = '<option value="">' . ex_e(ex_t('all')) . '</option>';
    foreach (EXAM_USER_STATUS as $k => $label) $stOpts .= '<option value="' . $k . '"' . ($status !== '' && (int)$status === $k ? ' selected' : '') . '>' . $label . '</option>';
    $doneOpts = '<option value="">' . ex_e(ex_t('all')) . '</option><option value="1"' . ($isDone === '1' ? ' selected' : '') . '>' . ex_e(ex_t('yes')) . '</option><option value="0"' . ($isDone === '0' ? ' selected' : '') . '>' . ex_e(ex_t('no')) . '</option>';

    $h .= '<div class="ex-card ex-toolbar"><form method="get" action="' . ex_e(ex_self()) . '" class="ex-filter">'
        . '<input type="hidden" name="act" value="exams"><input type="hidden" name="do" value="users">'
        . '<div class="ex-field"><label>' . ex_e(ex_t('filter_exam')) . '</label><select name="exam_id">' . $examOpts . '</select></div>'
        . '<div class="ex-field"><label>' . ex_e(ex_t('filter_user')) . ' <small>' . ex_e(ex_t('filter_user_hint')) . '</small></label><input name="username" value="' . ex_e($uname) . '"></div>'
        . '<div class="ex-field"><label>' . ex_e(ex_t('filter_status')) . '</label><select name="status">' . $stOpts . '</select></div>'
        . '<div class="ex-field"><label>' . ex_e(ex_t('filter_met')) . '</label><select name="is_done">' . $doneOpts . '</select></div>'
        . '<button class="ex-btn"><i class="fa-solid fa-magnifying-glass"></i>' . ex_e(ex_t('show')) . '</button></form>';

    if ($assignOpts !== '') {
        $h .= '<details class="ex-assign"><summary><i class="fa-solid fa-user-plus"></i>' . ex_e(ex_t('assign_summary')) . '</summary>'
            . '<form method="post" action="' . ex_e(ex_self()) . '" class="ex-filter">' . ex_hidden('assign')
            . '<div class="ex-field"><label>' . ex_e(ex_t('filter_exam')) . '</label><select name="id">' . $assignOpts . '</select></div>'
            . '<div class="ex-field"><label>' . ex_e(ex_t('username')) . '</label><input name="username" required></div>'
            . '<div class="ex-field"><label>' . ex_e(ex_t('begin')) . ' <small>' . ex_e(ex_t('begin_empty')) . '</small></label>' . ex_date_input('ex-as-begin', 'begin') . '</div>'
            . '<div class="ex-field"><label>' . ex_e(ex_t('end')) . ' <small>' . ex_e(ex_t('begin_empty')) . '</small></label>' . ex_date_input('ex-as-end', 'end', '', 'ex-as-begin') . '</div>'
            . '<button class="ex-btn ex-btn--primary"><i class="fa-solid fa-paper-plane"></i>' . ex_e(ex_t('assign')) . '</button></form></details>';
    }
    $h .= '</div>';

    $q = $db->sql_query_prepared(
        "SELECT eu.*, u.username, u.usergroup, u.displaygroup FROM exam_users eu JOIN users u ON u.id = eu.uid
         WHERE {$w} ORDER BY eu.id DESC LIMIT ? OFFSET ?",
        [...$p, EX_PAGE_SIZE, ($page - 1) * EX_PAGE_SIZE]
    );

    $rows = '';
    while ($r = $db->fetch_array($q)) {
        $x    = $exams[(int)$r['exam_id']] ?? null;
        $euId = (int)$r['id'];
        $st   = (int)$r['status'];
        if (!$x) continue;

        // Ongoing attempts: recalculate on view (same 5-minute throttle as the user's card),
        // so staff see current numbers instead of the last hourly cron run
        $formatted = null;
        if ($st === EXAM_USER_STATUS_NORMAL && TIMENOW - exam_ts((string)$r['updated_at']) >= EXAM_PROGRESS_VIEW_THROTTLE) {
            $res = exam_update_progress($r, $x);
            if ($res !== null) {
                $formatted       = $res['formatted'];
                $r['updated_at'] = exam_now();
                $r['is_done']    = $res['is_done'] ? 1 : 0;
            }
        }
        $formatted ??= exam_progress_formatted($x, json_decode((string)$r['progress'], true) ?: []);

        $bars = '';
        foreach ($formatted as $f) {
            $bars .= '<div class="ex-prog">'
                . '<span class="ex-prog__name">' . ex_e($f['name']) . '</span>'
                . '<div class="ex-prog__track"><div class="ex-prog__fill ex-fill-' . ($f['passed'] ? 'success' : 'primary') . '" style="width:' . $f['pct'] . '%"></div></div>'
                . '<span class="ex-prog__val">' . ex_e($f['current_text'] . ' / ' . $f['require_text']) . '</span></div>';
        }
        if ($bars !== '' && $st === EXAM_USER_STATUS_NORMAL) {
            $ago   = max(0, TIMENOW - exam_ts((string)$r['updated_at']));
            $bars .= '<div class="ex-sub">' . ex_e($ago < 60 ? ex_t('updated_now') : ex_t('updated_min', intdiv($ago, 60))) . '</div>';
        }

        $stTone = [EXAM_USER_STATUS_NORMAL => 'warning', EXAM_USER_STATUS_FINISHED => 'primary', EXAM_USER_STATUS_AVOIDED => 'secondary'][$st] ?? 'secondary';
        $acts = '<a class="ex-icon-btn" title="' . ex_e(ex_t('send_pm')) . '" href="' . ex_e($GLOBALS['BASEURL'] . '/private.php?action=send&uid=' . (int)$r['uid']) . '" target="_blank" rel="noopener"><i class="fa-solid fa-envelope"></i></a>';
        if ($st === EXAM_USER_STATUS_NORMAL) {
            $acts .= '<form method="post" action="' . ex_e(ex_self()) . '" data-ex-confirm="' . ex_e(ex_t((int)$x['type'] === EXAM_TYPE_EXAM ? 'confirm_finish_exam' : 'confirm_finish_task', (string)$r['username'])) . '" data-ex-tone="danger">'
                . ex_hidden('finish', ['euid' => $euId, 'back' => $back]) . '<button class="ex-icon-btn ex-icon-btn--danger" title="' . ex_e(ex_t('finish_now')) . '"><i class="fa-solid fa-flag-checkered"></i></button></form>';
            $acts .= '<button type="button" class="ex-icon-btn" title="' . ex_e(ex_t('change_end')) . '" data-ex-end="' . $euId . '" data-ex-end-value="' . ex_e(ex_dt_value($r['end'])) . '"><i class="fa-solid fa-calendar-days"></i></button>'
                . '<form method="post" action="' . ex_e(ex_self()) . '" data-ex-confirm="' . ex_e(ex_t('confirm_avoid', (string)$r['username'])) . '" data-ex-tone="warning">'
                . ex_hidden('avoid', ['euid' => $euId, 'back' => $back]) . '<button class="ex-icon-btn" title="' . ex_e(ex_t('avoid')) . '"><i class="fa-solid fa-user-shield"></i></button></form>';
        } elseif ($st === EXAM_USER_STATUS_AVOIDED) {
            $acts .= '<form method="post" action="' . ex_e(ex_self()) . '">' . ex_hidden('recover', ['euid' => $euId, 'back' => $back])
                . '<button class="ex-icon-btn" title="' . ex_e(ex_t('recover')) . '"><i class="fa-solid fa-rotate-left"></i></button></form>';
        }
        $acts .= '<form method="post" action="' . ex_e(ex_self()) . '" data-ex-confirm="' . ex_e(ex_t('confirm_remove', (string)$r['username'])) . '" data-ex-tone="danger">'
            . ex_hidden('remove', ['euid' => $euId, 'back' => $back]) . '<button class="ex-icon-btn ex-icon-btn--danger" title="' . ex_e(ex_t('title_delete')) . '"><i class="fa-solid fa-trash"></i></button></form>';

        $rows .= '<tr><td class="ex-check"><input type="checkbox" name="euids[]" value="' . $euId . '" form="ex-bulk-form" aria-label="' . ex_e(ex_t('select_user', (string)$r['username'])) . '"></td>'
            . '<td>' . ex_user_link($r) . '<div class="ex-sub">' . ex_e(exam_type_text($x) . ': ' . $x['name']) . '</div></td>'
            . '<td class="ex-small">' . ex_e(date('d.m.Y H:i', exam_user_begin($r, $x))) . '<br>' . ex_e(date('d.m.Y H:i', exam_user_end($r, $x))) . '</td>'
            . '<td class="ex-bars">' . ($bars ?: '<span class="ex-sub">' . ex_e(ex_t('not_calculated')) . '</span>') . '</td>'
            . '<td><span class="ex-badge ex-tone-' . $stTone . '">' . EXAM_USER_STATUS[$st] . '</span>'
            . ($st === EXAM_USER_STATUS_FINISHED
                ? match ((string)($r['result'] ?? '')) {
                    EXAM_RESULT_PASSED   => '<span class="ex-badge ex-tone-success"><i class="fa-solid fa-check"></i>' . ex_e(ex_t('badge_passed')) . '</span>',
                    EXAM_RESULT_DISABLED => '<span class="ex-badge ex-tone-danger"><i class="fa-solid fa-user-slash"></i>' . ex_e(ex_t('badge_disabled')) . '</span>',
                    EXAM_RESULT_FAILED   => '<span class="ex-badge ex-tone-warning"><i class="fa-solid fa-xmark"></i>' . ex_e(ex_t('badge_failed')) . '</span>',
                    EXAM_RESULT_ABANDONED => '<span class="ex-badge ex-tone-secondary"><i class="fa-solid fa-person-walking-arrow-right"></i>' . ex_e(ex_t('badge_abandoned')) . '</span>',
                    default              => '',
                  }
                : ((int)$r['is_done'] === 1 ? '<span class="ex-badge ex-tone-success"><i class="fa-solid fa-check"></i>' . ex_e(ex_t('badge_met')) . '</span>' : ''))
            . '</td>'
            . '<td class="ex-actions">' . $acts . '</td></tr>';
    }

    if ($rows === '') {
        return $h . '<div class="ex-card ex-empty"><i class="fa-solid fa-user-graduate"></i><p>' . ex_e(ex_t('no_match')) . '</p></div>';
    }

    $h .= '<div class="ex-card ex-table-wrap"><table class="ex-table"><thead><tr><th class="ex-check"><input type="checkbox" data-ex-check-all aria-label="' . ex_e(ex_t('select_all')) . '"></th><th>' . ex_e(ex_t('th_user')) . '</th><th>' . ex_e(ex_t('th_time')) . '</th><th>' . ex_e(ex_t('th_progress')) . '</th><th>' . ex_e(ex_t('th_status')) . '</th><th></th></tr></thead><tbody>'
        . $rows . '</tbody></table></div>';

    // Bulk bar: appears when something is selected (exams.js). Checkboxes point here via form="ex-bulk-form".
    $h .= '<form method="post" action="' . ex_e(ex_self()) . '" id="ex-bulk-form" class="ex-bulk" hidden>' . ex_hidden('bulk', ['back' => $back])
        . '<input type="hidden" name="end"><input type="hidden" name="reason"><input type="hidden" name="subject"><input type="hidden" name="message">'
        . '<span class="ex-bulk__count"><span class="ex-bulk__icon ex-tone-primary"><i class="fa-solid fa-list-check"></i></span>'
        . '<span><strong data-ex-bulk-count>0</strong> <span class="ex-sub">' . ex_e(ex_t('bulk_selected')) . '</span></span></span>'
        . '<select name="bulk_action" class="ex-bulk__select" aria-label="' . ex_e(ex_t('bulk_action')) . '">'
        . '<option value="">' . ex_e(ex_t('bulk_choose')) . '</option>'
        . '<option value="update_end">' . ex_e(ex_t('bulk_opt_update_end')) . '</option>'
        . '<option value="pm">' . ex_e(ex_t('bulk_opt_pm')) . '</option>'
        . '<option value="avoid">' . ex_e(ex_t('bulk_opt_avoid')) . '</option>'
        . '<option value="recover">' . ex_e(ex_t('bulk_opt_recover')) . '</option>'
        . '<option value="finish">' . ex_e(ex_t('bulk_opt_finish')) . '</option>'
        . '<option value="remove">' . ex_e(ex_t('bulk_opt_remove')) . '</option>'
        . '</select>'
        . '<span class="ex-bulk__btns">'
        . '<button class="ex-btn ex-btn--primary" data-ex-bulk-apply disabled><i class="fa-solid fa-bolt"></i>' . ex_e(ex_t('apply')) . '</button>'
        . '<button type="button" class="ex-btn" data-ex-bulk-clear><i class="fa-solid fa-xmark"></i>' . ex_e(ex_t('cancel')) . '</button></span></form>';

    $pages = (int)ceil($total / EX_PAGE_SIZE);
    if ($pages > 1) {
        $h .= '<div class="ex-tabs">';
        for ($i = max(1, $page - 5); $i <= min($pages, $page + 5); $i++) {
            $h .= '<a class="ex-tab' . ($i === $page ? ' is-active' : '') . '" href="' . ex_e(ex_self(array_merge($filter, ['page' => $i]))) . '">' . $i . '</a>';
        }
        $h .= '</div>';
    }

    // End time change form (filled in by JS)
    $h .= '<form method="post" action="' . ex_e(ex_self()) . '" id="ex-end-form" hidden>' . ex_hidden('update_end', ['back' => $back])
        . '<input type="hidden" name="euid"><input type="hidden" name="end"><input type="hidden" name="reason"></form>';

    return $h;
}

// ── Main ─────────────────────────────────────────────────

function main(): void
{
    global $BASEURL;

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (($_POST['do'] ?? '') === 'simulate') {
            ex_simulate_ajax();
        }
        ex_handle_post();
    }

    $do   = (string)($_GET['do'] ?? '');
    $exam = null;
    if ($do === 'edit' || $do === 'clone') {
        $exam = exam_get((int)($_GET['id'] ?? 0));
        if (!$exam) ex_redirect([], 'danger', ex_t('not_found'));
    }

    stdhead(ex_t('list_title'));
    ?>
    <link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/exams.css?ver=163">
    <script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
    <link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/flatpickr.min.css">
    <script src="<?= $BASEURL ?>/admin/scripts/flatpickr.js"></script>

    <div class="ex">
    <?php
    try {
        echo match ($do) {
            'new'   => ex_render_form(null),
            'edit'  => ex_render_form($exam),
            'clone' => ex_render_form($exam, true),
            'users' => ex_render_users(),
            default => ex_render_list(),
        };
    } catch (Throwable $e) {
        echo '<div class="ex-alert ex-tone-danger" role="alert"><i class="fa-solid fa-triangle-exclamation"></i><div><strong>' . ex_e(ex_t('error')) . '</strong><div>'
            . ex_e($e->getMessage()) . '</div></div></div>';
    }
    ?>
    </div>

    <script src="<?= $BASEURL ?>/admin/scripts/exams.js?ver=164"></script>
    <?php
    stdfoot();
}

main();