<?php
declare(strict_types=1);

define('IN_FORUM', true);

require_once 'global.php';

$lang->load('moderation');

require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_upload.php';
require_once INC_PATH . '/functions_parent_list.php';
require_once INC_PATH . '/class_moderation.php';
require_once INC_PATH . '/functions_forum_jump.php';

$moderation = new Moderation();

// ── Helper: {1}, {2}… placeholders for lang strings ─────────────────────────
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        return preg_replace_callback(
            '/\{(\d+)\}|%(\d+)\$s/',
            static function (array $m) use ($args): string {
                $i = (int)($m[1] !== '' ? $m[1] : $m[2]) - 1;
                return array_key_exists($i, $args) ? (string)$args[$i] : $m[0];
            },
            $str
        ) ?? $str;
    }
}

$plugins->run_hooks('moderation_start');

$tid  = $mybb->get_input('tid',  MyBB::INPUT_INT);
$pid  = $mybb->get_input('pid',  MyBB::INPUT_INT);
$fid  = $mybb->get_input('fid',  MyBB::INPUT_INT);
$pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);
$modal = $mybb->get_input('modal', MyBB::INPUT_INT);

if ($CURUSER['id'] === 0) {
    print_no_permission();
}

if ($pid) {
    $post = get_post($pid);
    if (!$post) {
        error($lang->moderation['error_invalidpost'], $lang->moderation['error']);
    }
    $tid = $post['tid'];
}

if ($tid) {
    $thread = get_thread($tid);
    if (!$thread) {
        error($lang->moderation['error_invalidthread'], $lang->moderation['error']);
    }
    $fid = $thread['fid'];
}

if ($fid) {
    $modlogdata['fid'] = $fid;
    $forum = get_forum($fid);
    build_forum_breadcrumb($fid);
}

if ($pmid > 0) {
    $query = $db->sql_query_prepared("SELECT uid, subject, ipaddress, fromid FROM privatemessages WHERE pmid = ?", [$pmid]);
    $pm = $query ? $db->fetch_array($query) : null;
    if (!$pm) {
        error($lang->moderation['error_invalidpm'], $lang->moderation['error']);
    }
}

$action = $mybb->get_input('action');
$mybb->input['action'] = $action;

match ($action) {
    'reports'    => add_breadcrumb($lang->moderation['reported_posts']),
    'allreports' => add_breadcrumb($lang->moderation['all_reported_posts']),
    default      => null,
};

if (isset($thread)) {
    $thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
    add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
    $modlogdata['tid'] = $thread['tid'];
}

if (isset($forum)) {
    check_forum_password($forum['fid']);
}

$log_multithreads_actions = [
    'do_multideletethreads', 'multiclosethreads', 'multiopenthreads',
    'multiapprovethreads', 'multiunapprovethreads',
    'multistickthreads', 'multiunstickthreads',
    'do_multimovethreads',
];

if (in_array($action, $log_multithreads_actions)) {
    $tids = !empty($mybb->input['searchid'])
        ? getids($mybb->get_input('searchid'), 'search')
        : getids($fid, 'forum');
    $modlogdata['tids'] = (array)$tids;
    unset($tids);
}

$CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);

$loginbox = '<div class="md-actor">
    <span class="st-ico st-soft-muted"><i class="fa-solid fa-user-shield"></i></span>
    <span class="md-actor__name">' . $CURUSER['username'] . '</span>
    <a href="member.php?action=logout&amp;logoutkey=' . $mybb->user['logoutkey'] . '" class="md-actor__switch"><i class="fa-solid fa-right-left me-1"></i>'
    . $lang->global['change_user'] . '</a>
</div>';

$allowable_moderation_actions = [
    'cancel_delayedmoderation', 'delayedmoderation',
];

if ($mybb->request_method !== 'post' && !in_array($action, $allowable_moderation_actions)) {
    print_no_permission();
}

$parser_options_default = [
    'allow_html'      => 0,
    'allow_mycode'    => 1,
    'allow_smilies'   => 1,
    'allow_imgcode'   => 1,
    'allow_videocode' => 1,
    'filter_badwords' => 1,
];

// ── Helper: build month <select> options ────────────────────────────────────
function build_month_options(int $selected, array $lang_months): string {
    $months = ['01','02','03','04','05','06','07','08','09','10','11','12'];
    $names  = array_values($lang_months);
    $html   = '';
    foreach ($months as $i => $m) {
        $sel  = ((int)$m === $selected) ? ' selected="selected"' : '';
        $html .= "<option value=\"{$m}\"{$sel}>{$names[$i]}</option>\n";
    }
    return $html;
}

// ── Page UI helpers («новый стиль»: st-ui.css + moderation-page.css, всё под .md-page) ──
function md_assets(): void
{
    global $BASEURL;
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=4">' . PHP_EOL
       . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/moderation-page.css?ver=1">' . PHP_EOL;
}

/** Шапка страницы: иконка в мягком квадрате + заголовок + подзаголовок + мета + блок справа */
function md_head(string $icon, string $soft, string $title, string $sub = '', string $meta = '', string $aside = ''): string
{
    $sub   = $sub   !== '' ? '<p class="md-sub">' . $sub . '</p>' : '';
    $meta  = $meta  !== '' ? '<div class="st-head__meta">' . $meta . '</div>' : '';
    $aside = $aside !== '' ? '<div class="md-head__aside">' . $aside . '</div>' : '';
    return '<div class="st-card st-head md-head">
    <span class="st-head__icon st-soft-' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>
    <div class="md-head__main"><h1 class="st-title">' . $title . '</h1>' . $sub . $meta . '</div>' . $aside . '
</div>';
}

/** Мета-элемент шапки: <span><i></i>текст</span> */
function md_meta(string $icon, string $html): string
{
    return '<span><i class="fa-solid ' . $icon . '"></i>' . $html . '</span>';
}

/** Карточка-секция с заголовком */
function md_card(string $icon, string $soft, string $title, string $body, string $sub = '', string $extra = ''): string
{
    $sub = $sub !== '' ? '<span class="md-card__sub">' . $sub . '</span>' : '';
    return '<div class="st-card md-card">
    <div class="st-card__head md-card__head">
        <span class="st-ico st-soft-' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>
        <span class="md-card__ttl"><span class="md-card__title">' . $title . '</span>' . $sub . '</span>' . $extra . '
    </div>
    <div class="st-card__body">' . $body . '</div>
</div>';
}

/** Подсказка/предупреждение: $kind = info|warning|danger */
function md_note(string $kind, string $icon, string $html, string $title = ''): string
{
    $title = $title !== '' ? '<strong class="md-note__title">' . $title . '</strong>' : '';
    return '<div class="md-note md-note--' . $kind . '"><i class="fa-solid ' . $icon . '"></i><div class="min-w-0">' . $title . $html . '</div></div>';
}

/** Галочка/радио плиткой (.st-check) */
function md_check(string $type, string $name, string $value, string $label, bool $checked = false,
                  string $id = '', string $icon = '', string $desc = ''): string
{
    $idattr = $id !== '' ? ' id="' . $id . '"' : '';
    $icon   = $icon !== '' ? '<i class="fa-solid ' . $icon . ' md-check__ico"></i>' : '';
    $desc   = $desc !== '' ? '<small class="md-check__desc">' . $desc . '</small>' : '';
    return '<label class="st-check md-check"><input type="' . $type . '" class="form-check-input" name="' . $name . '" value="' . $value . '"'
         . $idattr . ($checked ? ' checked="checked"' : '') . ' /><span class="md-check__txt">' . $icon
         . '<span class="md-check__lbl">' . $label . $desc . '</span></span></label>';
}

/** KPI-плитка */
function md_kpi(string $val, string $lbl, string $soft = 'primary'): string
{
    return '<div class="st-kpi md-kpi md-kpi--' . $soft . '"><div class="st-kpi__val">' . $val . '</div><div class="st-kpi__lbl">' . $lbl . '</div></div>';
}

/** Строка «название — значение» */
function md_row(string $lbl, string $val): string
{
    return '<div class="st-row"><span class="st-row__lbl">' . $lbl . '</span><span class="st-row__val">' . $val . '</span></div>';
}

/** Пост в списке (split / merge) с галочкой выбора */
function md_post(string $username, string $date, string $message, string $pick): string
{
    return '<div class="md-post">
    <div class="md-post__head">
        <span class="md-post__who"><i class="fa-solid fa-user"></i>' . $username . '</span>
        <span class="md-post__date"><i class="fa-regular fa-clock"></i>' . $date . '</span>
        <span class="md-post__pick">' . $pick . '</span>
    </div>
    <div class="md-post__body">' . $message . '</div>
</div>';
}

/** Номер шага в заголовке карточки */
function md_step(int $n, string $title): string
{
    return '<span class="md-step">' . $n . '</span>' . $title;
}

/** Липкая панель снизу: «назад» слева, кнопки справа */
function md_submitbar(string $cancel_url, string $cancel_label, string $buttons): string
{
    return '<div class="st-submitbar">
    <a href="' . $cancel_url . '" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-arrow-left me-1"></i>' . $cancel_label . '</a>
    <div class="md-submitbar__btns">' . $buttons . '</div>
</div>';
}

/** Куда вести «Отмена»: ?url=… или запасной адрес (раньше при пустом url ссылка была href="") */
function md_cancel_url(string $fallback): string
{
    global $mybb;
    $u = htmlspecialchars_uni($mybb->get_input('url'));
    return $u !== '' ? $u : $fallback;
}

/** Обёртка страницы */
function md_page(string $html, string $mod = ''): string
{
    return '<div class="container-md st-page md-page' . ($mod !== '' ? ' ' . $mod : '') . '">' . PHP_EOL . $html . PHP_EOL . '</div>';
}

/** Карточка «кто выполняет действие» */
function md_actor_card(): string
{
    global $lang, $loginbox;
    return md_card('fa-shield-halved', 'muted', $lang->moderation['sec_security'], $loginbox);
}



// ── Moderation assets (SweetAlert2, toast, moderation JS) ───────────────────
$_mod_assets = '<script>const AGS_LANG = ' . mod_js_lang() . ';</script>' . PHP_EOL
    . '<link rel="stylesheet" href="' . htmlspecialchars($BASEURL) . '/include/templates/default/style/sweetalert2.min.css">' . PHP_EOL
    . '<script src="' . htmlspecialchars($BASEURL) . '/scripts/sweetalert2.min.js"></script>' . PHP_EOL
    . '<script src="' . htmlspecialchars($BASEURL) . '/scripts/toast.js"></script>' . PHP_EOL
    . '<script src="' . htmlspecialchars($BASEURL) . '/scripts/moderation.js?ver=22"></script>';

// ── Begin switch ─────────────────────────────────────────────────────────────
switch ($action) {

    // ── Cancel delayed moderation ────────────────────────────────────────────
    case 'cancel_delayedmoderation':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        add_breadcrumb($lang->moderation['delayed_moderation']);
        $plugins->run_hooks('moderation_cancel_delayedmoderation');
        $db->sql_query_prepared("DELETE FROM delayedmoderation WHERE did = ?", [$mybb->get_input('did', MyBB::INPUT_INT)]);

        if ($tid === 0) {
            moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_delayed_moderation_cancelled']);
        } else {
            moderation_redirect(
                "moderation.php?action=delayedmoderation&amp;tid={$tid}&amp;my_post_key={$mybb->post_code}",
                $lang->moderation['redirect_delayed_moderation_cancelled']
            );
        }
        break;

    // ── Delayed moderation (show/process form) ───────────────────────────────
    case 'do_delayedmoderation':
    case 'delayedmoderation':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $localized_time_offset = ($CURUSER['timezone'] + $CURUSER['dst']) * 3600;

        if (!$mybb->get_input('date_day', MyBB::INPUT_INT)) {
            $mybb->input['date_day'] = gmdate('d', TIMENOW + $localized_time_offset);
        }
        if (!$mybb->get_input('date_month', MyBB::INPUT_INT)) {
            $mybb->input['date_month'] = gmdate('m', TIMENOW + $localized_time_offset);
        }

        // Inline or single-thread mode
        if (!empty($mybb->input['tid'])) {
            $mybb->input['tids'] = $tid;
        } else {
            $tids = $mybb->get_input('inlinetype') === 'search'
                ? getids($mybb->get_input('searchid'), 'search')
                : getids($mybb->get_input('fid', MyBB::INPUT_INT), 'forum');

            if (count($tids) < 1) {
                stderr($lang->moderation['error_inline_nothreadsselected']);
            }
            $mybb->input['tids'] = $tids;
        }

        add_breadcrumb($lang->moderation['delayed_moderation']);

        $errors = [];

        $allowed_types = [
            'move', 'merge', 'removeredirects', 'removesubscriptions',
            'openclosethread', 'deletethread', 'stick', 'approveunapprovethread',
        ];

        $mybb->input['delayedmoderation'] = $mybb->get_input('delayedmoderation', MyBB::INPUT_ARRAY);
        $mybb->input['type'] = $mybb->get_input('type');

        // ── Process form submission ──────────────────────────────────────────
        if ($action === 'do_delayedmoderation' && $mybb->request_method === 'post') {

            if (!in_array($mybb->input['type'], $allowed_types)) {
                $mybb->input['type'] = '';
                $errors[] = $lang->moderation['error_delayedmoderation_unsupported_type'];
            }

            if ($mybb->input['type'] === 'move') {
                $delmod = $mybb->input['delayedmoderation'];
                if (!isset($delmod['method']) || !in_array($delmod['method'], ['move', 'redirect', 'copy'])) {
                    $mybb->input['delayedmoderation']['method'] = '';
                    $errors[] = $lang->moderation['error_delayedmoderation_unsupported_method'];
                }

                $newfid   = (int)($delmod['new_forum'] ?? 0);
                $newforum = get_forum($newfid);
                if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') {
                    $errors[] = $lang->moderation['error_invalidforum'];
                }
                if (($delmod['method'] ?? '') !== 'copy' && $fid === $newfid) {
                    $errors[] = $lang->moderation['error_movetosameforum'];
                }
            }

            $dateDay   = $mybb->get_input('date_day',   MyBB::INPUT_INT);
            $dateMonth = $mybb->get_input('date_month', MyBB::INPUT_INT);
            $dateYear  = $mybb->get_input('date_year',  MyBB::INPUT_INT);

            if ($dateDay < 1 || $dateDay > 31)     $errors[] = $lang->moderation['error_delayedmoderation_invalid_date_day'];
            if ($dateMonth < 1 || $dateMonth > 12) $errors[] = $lang->moderation['error_delayedmoderation_invalid_date_month'];
            if ($dateYear < (int)gmdate('Y', TIMENOW + $localized_time_offset)) {
                $errors[] = $lang->moderation['error_delayedmoderation_invalid_date_year'];
            }

            $date_time_raw   = $mybb->get_input('date_time');
            $date_time_parts = explode(' ', trim($date_time_raw));
            $time_parts      = explode(':', (string)($date_time_parts[0] ?? ''));
            $hour = (int)($time_parts[0] ?? 0);
            $min  = (int)($time_parts[1] ?? 0);

            // 12-часовой формат: 12pm = 12, 12am = 0 (раньше 12pm превращалось в 0:xx)
            if (stripos($date_time_raw, 'pm') !== false && $hour < 12) {
                $hour += 12;
            } elseif (stripos($date_time_raw, 'am') !== false && $hour === 12) {
                $hour = 0;
            }

            $rundate = gmmktime($hour, $min, (int)date('s', TIMENOW), $dateMonth, $dateDay, $dateYear)
                       - $localized_time_offset;

            if (!$errors) {
                if (is_array($mybb->input['tids'])) {
                    $mybb->input['tids'] = implode(',', $mybb->input['tids']);
                }

                $db->sql_query_prepared(
                    "INSERT INTO delayedmoderation (`type`,`delaydateline`,`uid`,`tids`,`fid`,`dateline`,`inputs`) VALUES (?,?,?,?,?,?,?)",
                    [
                        $mybb->input['type'],
                        (int)$rundate,
                        $CURUSER['id'],
                        $mybb->input['tids'],
                        $fid,
                        TIMENOW,
                        my_serialize($mybb->input['delayedmoderation']),
                    ]
                );

                $plugins->run_hooks('moderation_do_delayedmoderation');

                $rundate_format = my_datee('relative', $rundate, '', 2);

                if (!empty($mybb->input['tid'])) {
                    moderation_redirect(
                        get_thread_link($thread['tid']),
                        ags_fmt($lang->moderation['redirect_delayed_moderation_thread'], $rundate_format)
                    );
                } elseif ($mybb->get_input('inlinetype') === 'search') {
                    moderation_redirect(
                        get_forum_link($fid),
                        ags_fmt($lang->moderation['redirect_delayed_moderation_search'], $rundate_format)
                    );
                } else {
                    moderation_redirect(
                        get_forum_link($fid),
                        ags_fmt($lang->moderation['redirect_delayed_moderation_forum'], $rundate_format)
                    );
                }
            }

            // Rebuild selected state after errors
            $type_selected   = array_fill_keys($allowed_types, '');
            $type_selected[$mybb->get_input('type')] = 'checked="checked"';
            $method_selected = ['move' => '', 'redirect' => '', 'copy' => ''];
            if (isset($mybb->input['delayedmoderation']['method'])) {
                $method_selected[$mybb->input['delayedmoderation']['method']] = 'checked="checked"';
            }
            foreach (['redirect_expire', 'new_forum', 'subject', 'threadurl'] as $v) {
                $mybb->input['delayedmoderation'][$v] ??= '';
            }
            $mybb->input['delayedmoderation']['redirect_expire'] = (int)$mybb->input['delayedmoderation']['redirect_expire'];
            $mybb->input['delayedmoderation']['new_forum']       = (int)$mybb->input['delayedmoderation']['new_forum'];
            $mybb->input['delayedmoderation']['subject']         = htmlspecialchars_uni($mybb->input['delayedmoderation']['subject']);
            $mybb->input['delayedmoderation']['threadurl']       = htmlspecialchars_uni($mybb->input['delayedmoderation']['threadurl']);
            $forumselect = build_forum_jump('', $mybb->input['delayedmoderation']['new_forum'], 1, '', 0, true, '', 'delayedmoderation[new_forum]');

        } else {
            // Fresh form defaults
            $type_selected   = array_fill_keys($allowed_types, '');
            $type_selected['openclosethread'] = 'checked="checked"';
            $method_selected = ['move' => 'checked="checked"', 'redirect' => '', 'copy' => ''];
            $mybb->input['delayedmoderation']['redirect_expire'] = '';
            $mybb->input['delayedmoderation']['subject']         = $thread['subject'] ?? '';
            $mybb->input['delayedmoderation']['threadurl']       = '';
            $forumselect = build_forum_jump('', $fid, 1, '', 0, true, '', 'delayedmoderation[new_forum]');
        }

        $display_errors = !empty($errors) ? inline_error($errors) : '';
        $forum_cache    = $cache->read('forums');

        $actions = [
            'openclosethread'      => $lang->moderation['open_close_thread'],
            'deletethread'         => $lang->moderation['delete_thread'],
            'move'                 => $lang->moderation['move_copy_thread'],
            'stick'                => $lang->moderation['stick_unstick_thread'],
            'merge'                => $lang->moderation['merge_threads'],
            'removeredirects'      => $lang->moderation['remove_redirects'],
            'removesubscriptions'  => $lang->moderation['remove_subscriptions'],
            'approveunapprovethread' => $lang->moderation['approve_unapprove_thread'],
        ];

        // ── Delayed mods list ────────────────────────────────────────────────
        $delayedmods = '';

        if ($tid === 0) {
            $tids = $mybb->get_input('inlinetype') === 'search'
                ? getids($mybb->get_input('searchid'), 'search')
                : getids($fid, 'forum');

            $where_array  = [];
            $where_params = [];
            foreach ($tids as $like) {
                $where_array[] = match ($db->type) {
                    'pgsql', 'sqlite' => "','||d.tids||',' LIKE ?",
                    default           => "CONCAT(',',d.tids,',') LIKE ?",
                };
                $where_params[] = '%,' . $like . ',%';
            }
            $where_statement = implode(' OR ', $where_array);
        } else {
            $where_statement = match ($db->type) {
                'pgsql', 'sqlite' => "','||d.tids||',' LIKE ?",
                default           => "CONCAT(',',d.tids,',') LIKE ?",
            };
            $where_params = ['%,' . $tid . ',%'];
        }

        // Пустой список тем давал «WHERE » без условия → ошибка SQL; тогда просто не запрашиваем
        $query = $where_statement === '' ? false : $db->sql_query_prepared("
            SELECT d.*, u.username, f.name AS fname
            FROM delayedmoderation d
            LEFT JOIN users u ON (u.id = d.uid)
            LEFT JOIN forums f ON (f.fid = d.fid)
            WHERE {$where_statement}
            ORDER BY d.dateline DESC
            LIMIT 0, 20
        ", $where_params);

        $md_info = static fn(string $lbl, string $val): string =>
            '<div class="md-info"><span class="md-info__lbl">' . $lbl . '</span> ' . $val . '</div>';

        while ($query && ($delayedmod = $db->fetch_array($query))) {
            $delayedmod['dateline']    = my_datee('normal', $delayedmod['delaydateline'], '', 2);
            $delayedmod['username']    = htmlspecialchars_uni((string)$delayedmod['username']);
            $delayedmod['profilelink'] = build_profile_link($delayedmod['username'], $delayedmod['uid']);
            $delayedmod['action']      = $actions[$delayedmod['type']] ?? htmlspecialchars_uni((string)$delayedmod['type']);
            $info = '';

            if (strpos((string)$delayedmod['tids'], ',') === false) {
                // Тема могла быть удалена — раньше это давало warning'и и пустую ссылку
                $delayed_thread = get_thread((int)$delayedmod['tids']);
                if ($delayed_thread) {
                    $info .= $md_info($lang->moderation['thread'], '<a href="' . get_thread_link($delayed_thread['tid']) . '">'
                        . htmlspecialchars_uni($parser->parse_badwords($delayed_thread['subject'])) . '</a>');
                } else {
                    $info .= $md_info($lang->moderation['thread'], '#' . (int)$delayedmod['tids']);
                }
            } else {
                $info .= $md_info($lang->moderation['thread'], $lang->moderation['multiple_threads']);
            }

            if ($delayedmod['fname']) {
                $info .= $md_info($lang->moderation['forum'], '<a href="' . get_forum_link($delayedmod['fid']) . '">'
                    . htmlspecialchars_uni($delayedmod['fname']) . '</a>');
            }

            $delayedmod['inputs'] = my_unserialize($delayedmod['inputs']);
            if (!is_array($delayedmod['inputs'])) $delayedmod['inputs'] = [];

            if ($delayedmod['type'] === 'move') {
                $nf = (int)($delayedmod['inputs']['new_forum'] ?? 0);
                $info .= $md_info($lang->moderation['new_forum'], '<a href="' . get_forum_link($nf) . '">'
                    . htmlspecialchars_uni((string)($forum_cache[$nf]['name'] ?? ('#' . $nf))) . '</a>');
            } elseif ($delayedmod['type'] === 'merge') {
                // Раньше тема/URL слияния вычислялись, но не выводились
                $m_subject = htmlspecialchars_uni((string)($delayedmod['inputs']['subject'] ?? ''));
                $m_url     = htmlspecialchars_uni((string)($delayedmod['inputs']['threadurl'] ?? ''));
                if ($m_subject !== '') $info .= $md_info($lang->moderation['new_subject'], $m_subject);
                if ($m_url !== '')     $info .= $md_info($lang->moderation['thread_to_merge_with'], '<span class="md-break">' . $m_url . '</span>');
            }

            $cancel_href = 'moderation.php?action=cancel_delayedmoderation&amp;tid=' . $tid . '&amp;fid=' . $fid
                         . '&amp;did=' . (int)$delayedmod['did'] . '&amp;my_post_key=' . $mybb->post_code;

            $delayedmods .= '<div class="md-qrow">
    <div class="md-qrow__who"><i class="fa-solid fa-user-shield"></i>' . $delayedmod['profilelink'] . '</div>
    <div class="md-qrow__when"><i class="fa-regular fa-clock"></i>' . $delayedmod['dateline'] . '</div>
    <div class="md-qrow__what"><span class="st-badge st-soft-primary">' . $delayedmod['action'] . '</span></div>
    <div class="md-qrow__info">' . $info . '</div>
    <div class="md-qrow__act"><a href="' . $cancel_href . '" class="btn btn-sm btn-outline-danger st-pill"><i class="fa-solid fa-xmark me-1"></i>' . $lang->moderation['cancel'] . '</a></div>
</div>';
        }

        if ($delayedmods !== '') {
            $delayedmods = '<div class="md-queue">
    <div class="md-qrow md-qrow--head">
        <div>' . $lang->moderation['mod_username'] . '</div>
        <div>' . $lang->moderation['time_to_perform_action'] . '</div>
        <div>' . $lang->moderation['mod_actions'] . '</div>
        <div>' . $lang->moderation['mod_information'] . '</div>
        <div>' . $lang->moderation['actions'] . '</div>
    </div>' . $delayedmods . '
</div>';
        } else {
            $delayedmods = '<div class="st-empty"><i class="fa-regular fa-calendar-xmark"></i><div>' . $lang->moderation['no_delayed_mods'] . '</div></div>';
        }

        // ── Build thread/inline vars ─────────────────────────────────────────
        $url = '';
        $sel = static fn(string $s): bool => $s !== '';

        if ($mybb->get_input('tid', MyBB::INPUT_INT)) {
            $thread['link'] = get_thread_link($tid);
            $threads = md_meta('fa-comments', '<a href="' . $thread['link'] . '">' . $thread['subject'] . '</a>');
            $cancel_url = $thread['link'];
            // Раньше <dd> здесь не закрывался и ломал разметку формы
            $merge_option = md_check('radio', 'type', 'merge', $lang->moderation['merge_threads'], $sel($type_selected['merge']), 'type_merge', 'fa-code-merge');
            $merge_panel  = '<div id="type_merge_expanded" class="md-expand"><div class="md-panel">
    <label class="md-label" for="dm_subject">' . $lang->moderation['new_subject'] . '</label>
    <input type="text" class="form-control" id="dm_subject" name="delayedmoderation[subject]" value="' . ($mybb->input['delayedmoderation']['subject'] ?? '') . '" />
    <label class="md-label mt-3" for="dm_threadurl">' . $lang->moderation['thread_to_merge_with'] . '</label>
    <input type="text" class="form-control" id="dm_threadurl" name="delayedmoderation[threadurl]" value="' . ($mybb->input['delayedmoderation']['threadurl'] ?? '') . '" />
    <div class="md-hint mt-2">' . $lang->moderation['merge_with_note'] . '</div>
</div></div>';
        } else {
            $tids = $mybb->get_input('inlinetype') === 'search'
                ? getids($mybb->get_input('searchid'), 'search')
                : getids($fid, 'forum');

            if ($mybb->get_input('inlinetype') === 'search') {
                $url = htmlspecialchars_uni($mybb->get_input('url'));
            }
            if (count($tids) < 1) {
                stderr($lang->moderation['error_inline_nothreadsselected']);
            }
            $threads      = md_meta('fa-layer-group', ags_fmt($lang->moderation['threads_selected'], count($tids)));
            $cancel_url   = md_cancel_url(get_forum_link($fid));
            $merge_option = '';
            $merge_panel  = '';
        }

        // Раньше брался несуществующий $_POST['redirect_expire'] — введённое значение терялось после ошибки
        $redirect_expire = (int)($mybb->input['delayedmoderation']['redirect_expire'] ?? 0);
        $redirect_expire = $redirect_expire > 0 ? (string)$redirect_expire : '';

        // Date day options
        $dateday = '';
        $sel_day = $mybb->get_input('date_day', MyBB::INPUT_INT);
        for ($day = 1; $day <= 31; ++$day) {
            $s = ($sel_day === $day) ? ' selected="selected"' : '';
            $dateday .= "<option value=\"{$day}\"{$s}>{$day}</option>";
        }

        $sel_month = $mybb->get_input('date_month', MyBB::INPUT_INT);
        $month_names = [
            $lang->moderation['january'], $lang->moderation['february'], $lang->moderation['march'],
            $lang->moderation['april'],   $lang->moderation['may'],       $lang->moderation['june'],
            $lang->moderation['july'],    $lang->moderation['august'],    $lang->moderation['september'],
            $lang->moderation['october'], $lang->moderation['november'],  $lang->moderation['december'],
        ];
        $datemonth = '';
        foreach (['01','02','03','04','05','06','07','08','09','10','11','12'] as $i => $m) {
            $s = ((int)$m === $sel_month) ? ' selected="selected"' : '';
            $datemonth .= "<option value=\"{$m}\"{$s}>{$month_names[$i]}</option>\n";
        }

        // Год/время: после ошибки оставляем введённое; время по умолчанию в 24-часовом H:i —
        // form-validation2.js принимает только HH:MM, а $timeformat мог быть с AM/PM и блокировал отправку
        $dateyear = $mybb->get_input('date_year', MyBB::INPUT_INT) ?: (int)gmdate('Y', TIMENOW + $localized_time_offset);
        $datetime = htmlspecialchars_uni($mybb->get_input('date_time')) ?: gmdate('H:i', TIMENOW + $localized_time_offset);

        $type_grid = md_check('radio', 'type', 'openclosethread', $lang->moderation['open_close_thread'], $sel($type_selected['openclosethread']), 'type_openclosethread', 'fa-lock-open')
            . md_check('radio', 'type', 'deletethread', $lang->moderation['delete_thread'], $sel($type_selected['deletethread']), 'type_deletethread', 'fa-trash-can')
            . md_check('radio', 'type', 'stick', $lang->moderation['stick_unstick_thread'], $sel($type_selected['stick']), 'type_stick_unstick_thread', 'fa-thumbtack')
            . md_check('radio', 'type', 'move', $lang->moderation['move_copy_thread'], $sel($type_selected['move']), 'type_movecopythread', 'fa-right-left')
            . $merge_option
            . md_check('radio', 'type', 'removeredirects', $lang->moderation['remove_redirects'], $sel($type_selected['removeredirects']), 'type_removeredirects', 'fa-link-slash')
            . md_check('radio', 'type', 'removesubscriptions', $lang->moderation['remove_subscriptions'], $sel($type_selected['removesubscriptions']), 'type_removesubscriptions', 'fa-bell-slash')
            . md_check('radio', 'type', 'approveunapprovethread', $lang->moderation['approve_unapprove_thread'], $sel($type_selected['approveunapprovethread']), 'type_approveunapprovethread', 'fa-circle-check');

        $move_panel = '<div id="type_movecopythread_expanded" class="md-expand"><div class="md-panel">
    <div class="md-label">' . $lang->moderation['new_forum'] . '</div>
    <div class="md-forum">' . $forumselect . '</div>
    <div class="md-label mt-3">' . $lang->moderation['method'] . '</div>
    <div class="md-grid">'
        . md_check('radio', 'delayedmoderation[method]', 'move', $lang->moderation['method_move'], $sel($method_selected['move']), '', 'fa-arrow-right')
        . md_check('radio', 'delayedmoderation[method]', 'redirect', $lang->moderation['method_move_redirect'], $sel($method_selected['redirect']), '', 'fa-link')
        . md_check('radio', 'delayedmoderation[method]', 'copy', $lang->moderation['method_copy'], $sel($method_selected['copy']), '', 'fa-copy') . '
    </div>
    <div class="md-inline mt-2">
        <input type="number" min="0" class="form-control md-days" name="delayedmoderation[redirect_expire]" value="' . $redirect_expire . '" placeholder="' . $lang->moderation['ph_days'] . '" />
        <span class="md-hint">' . $lang->moderation['redirect_expire_note'] . '</span>
    </div>
</div></div>';

        $plugins->run_hooks('moderation_delayedmoderation');

        $forum_meta = isset($forum['name']) ? md_meta('fa-folder', htmlspecialchars_uni($forum['name'])) : '';

        $when = '<div class="md-label"><i class="fa-solid fa-stopwatch me-1"></i>' . $lang->moderation['run_moderation_time'] . '</div>
<div class="md-when">
    <select name="date_day" class="form-select" aria-label="' . $lang->moderation['run_moderation_time'] . '">' . $dateday . '</select>
    <select name="date_month" class="form-select" aria-label="' . $lang->moderation['run_moderation_time'] . '">' . $datemonth . '</select>
    <input type="text" name="date_year" value="' . $dateyear . '" size="4" maxlength="4" inputmode="numeric" class="form-control" />
    <div class="input-group"><span class="input-group-text"><i class="fa-regular fa-clock"></i></span><input type="text" name="date_time" value="' . $datetime . '" inputmode="numeric" class="form-control" /></div>
</div>';

        $delayedmoderation = md_page(
            $display_errors
            . md_head('fa-clock', 'primary', $lang->moderation['delayed_moderation'], $lang->moderation['sub_delayed_form'], $threads . $forum_meta)
            . md_card('fa-calendar-week', 'info', $lang->moderation['delayed_mod_queue'], $delayedmods, $lang->moderation['sub_delayed_queue'])
            . '<form action="moderation.php" method="post" id="delayedModForm" class="md-form">
    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    <input type="hidden" name="action" value="do_delayedmoderation" />
    <input type="hidden" name="url" value="' . $url . '" />
    <input type="hidden" name="tid" value="' . $tid . '" />
    <input type="hidden" name="fid" value="' . $fid . '" />'
            . md_card('fa-calendar-days', 'primary', $lang->moderation['delayed_moderation'],
                md_note('info', 'fa-circle-info', $lang->moderation['delayed_moderation_desc']) . $loginbox . $when)
            . md_card('fa-gavel', 'warning', $lang->moderation['moderation_action'],
                '<div class="md-grid">' . $type_grid . '</div>' . $move_panel . $merge_panel)
            . md_submitbar($cancel_url, $lang->moderation['cancel'],
                '<button type="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-calendar-check me-1"></i>' . $lang->moderation['save_delayed_moderation'] . '</button>')
            . '</form>
<script src="' . $BASEURL . '/scripts/toggle-type.js"></script>
<script src="' . $BASEURL . '/scripts/form-validation2.js?ver=22"></script>'
        );

        stdhead($lang->moderation['delayed_moderation']);
        md_assets();
        echo $delayedmoderation;
        echo $_mod_assets;
        stdfoot();
        break;

    // ── Open / close thread ──────────────────────────────────────────────────
    case 'openclosethread':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        if ($thread['closed'] == 1) {
            $openclose = 'opened';
            $redirect  = $lang->moderation['redirect_openthread'];
            $moderation->open_threads($tid);
        } else {
            $openclose = 'closed';
            $redirect  = $lang->moderation['redirect_closethread'];
            $moderation->close_threads($tid);
        }

        log_moderator_action($modlogdata, 'Thread ' . $openclose);
        redirect(get_thread_link($thread['tid']), $redirect);
        break;

    // ── Stick / unstick thread ───────────────────────────────────────────────
    case 'stick':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $plugins->run_hooks('moderation_stick');

        if ($thread['sticky'] == 1) {
            $stuckunstuck = 'unstuck';
            $redirect     = $lang->moderation['redirect_unstickthread'];
            $moderation->unstick_threads($tid);
        } else {
            $stuckunstuck = 'stuck';
            $redirect     = $lang->moderation['redirect_stickthread'];
            $moderation->stick_threads($tid);
        }

        log_moderator_action($modlogdata, 'Thread ' . $stuckunstuck);
        redirect(get_thread_link($thread['tid']), $redirect);
        break;

    // ── Remove redirects ─────────────────────────────────────────────────────
    case 'removeredirects':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $plugins->run_hooks('moderation_removeredirects');
        $moderation->remove_redirects($tid);
        log_moderator_action($modlogdata, 'Thread Redirects Removed');
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_redirectsremoved']);
        break;

    // ── Delete thread ────────────────────────────────────────────────────────
    case 'do_deletethread':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        $plugins->run_hooks('moderation_do_deletethread');

        $modlogdata['thread_subject'] = $thread['subject'];
        log_moderator_action($modlogdata, 'Thread Deleted Permanently: ' . $thread['subject']);
        $moderation->delete_thread($tid);
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_threaddeleted']);
        break;

    // ── Delete poll (confirmation) ───────────────────────────────────────────
    case 'deletepoll':
        if (!is_mod($usergroups)) error_no_permission();
        add_breadcrumb($lang->moderation['nav_deletepoll']);
        $plugins->run_hooks('moderation_deletepoll');

        $q = $db->sql_query_prepared("SELECT pid FROM polls WHERE tid = ?", [$tid]);
        $poll = $q ? $db->fetch_array($q) : null;
        if (!$poll) stderr($lang->moderation['error_invalidpoll']);

        $thread_link = get_thread_link($tid);

        $deletepoll = md_page(
            md_head('fa-square-poll-vertical', 'danger', $lang->moderation['delete_poll'], $lang->moderation['tip_cannot_undo'],
                md_meta('fa-comments', '<a href="' . $thread_link . '">' . $thread['subject'] . '</a>'))
            . '<form action="moderation.php" method="post" class="md-form">
    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    <input type="hidden" name="action" value="do_deletepoll" />
    <input type="hidden" name="tid" value="' . $tid . '" />
    <input type="hidden" name="delete" value="1" />'
            . md_note('danger', 'fa-triangle-exclamation', $lang->moderation['warn_poll_delete'], $lang->moderation['lbl_warning'])
            . md_actor_card()
            . md_submitbar($thread_link, $lang->moderation['cancel'],
                '<button type="submit" class="btn btn-danger st-pill" name="submit" value="' . $lang->moderation['delete_poll'] . '"><i class="fa-solid fa-trash-can me-1"></i>' . $lang->moderation['delete_poll'] . '</button>')
            . '</form>',
            'md-narrow'
        );

        stdhead($lang->moderation['delete_poll']);
        md_assets();
        echo $deletepoll;
        echo $_mod_assets;
        stdfoot();
        break;

    // ── Do delete poll ───────────────────────────────────────────────────────
    case 'do_deletepoll':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        if (!isset($mybb->input['delete'])) stderr($lang->moderation['redirect_pollnotdeleted']);

        $q = $db->sql_query_prepared("SELECT pid FROM polls WHERE tid = ?", [$tid]);
        $poll = $q ? $db->fetch_array($q) : null;
        if (!$poll) stderr($lang->moderation['error_invalidpoll']);

        $plugins->run_hooks('moderation_do_deletepoll');
        log_moderator_action($modlogdata, 'Poll Deleted: ' . $thread['subject']);
        $moderation->delete_poll($poll['pid']);
        redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_polldeleted']);
        break;

    // ── Approve thread ───────────────────────────────────────────────────────
    case 'approvethread':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $thread = get_thread($tid);
        $plugins->run_hooks('moderation_approvethread');
        log_moderator_action($modlogdata, 'Thread Approved: ' . $thread['subject']);
        $moderation->approve_threads($tid, $fid);
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_threadapproved']);
        break;

    // ── Unapprove thread ─────────────────────────────────────────────────────
    case 'unapprovethread':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $thread = get_thread($tid);
        $plugins->run_hooks('moderation_unapprovethread');
        log_moderator_action($modlogdata, 'Thread Unapproved: ' . $thread['subject']);
        $moderation->unapprove_threads($tid);
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_threadunapproved']);
        break;

    // ── Move thread ──────────────────────────────────────────────────────────
    case 'do_move':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $moveto = $mybb->get_input('moveto', MyBB::INPUT_INT);
        $method = $mybb->get_input('method');


        $newforum = get_forum($moveto);
        if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') {
            stderr($lang->moderation['error_invalidforum']);
        }
        if ($method !== 'copy' && $thread['fid'] === $moveto) {
            stderr($lang->moderation['error_movetosameforum']);
        }

        $plugins->run_hooks('moderation_do_move');

        $expire = 0;
        if ($mybb->get_input('redirect_expire', MyBB::INPUT_INT) > 0) {
            $expire = TIMENOW + ($mybb->get_input('redirect_expire', MyBB::INPUT_INT) * 86400);
        }

        $newtid = $moderation->move_thread($tid, $moveto, $method, $expire);

        $log_msg = match ($method) {
            'copy'  => 'Thread Copied',
            default => 'Thread Moved',
        };
        log_moderator_action($modlogdata, $log_msg);
        redirect(get_thread_link($newtid), $lang->moderation['redirect_threadmoved']);
        break;

    // ── Merge threads ────────────────────────────────────────────────────────
    case 'do_merge':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $plugins->run_hooks('moderation_do_merge');

        $realurl = explode('#', $mybb->get_input('threadurl'));
        $mybb->input['threadurl'] = $realurl[0];
        $parameters = [];

        if (str_ends_with($mybb->input['threadurl'], 'html')) {
            preg_match('#thread-([0-9]+)?#i', $mybb->input['threadurl'], $threadmatch);
            preg_match('#post-([0-9]+)?#i',   $mybb->input['threadurl'], $postmatch);
            if (!empty($threadmatch[1])) $parameters['tid'] = $threadmatch[1];
            if (!empty($postmatch[1]))   $parameters['pid'] = $postmatch[1];
        } else {
            $splitloc = explode('.php', $mybb->input['threadurl']);
            $temp = explode('&', my_substr($splitloc[1], 1));
            foreach ($temp as $pair) {
                [$k, $v] = explode('=', $pair, 2) + [1 => ''];
                $parameters[$k] = $v;
            }
        }

        if (!empty($parameters['pid']) && empty($parameters['tid'])) {
            $mergetid = (int)get_post($parameters['pid'])['tid'];
        } else {
            $mergetid = (int)($parameters['tid'] ?? 0);
        }

        $mergethread = get_thread($mergetid);
        if (!$mergethread)        stderr($lang->moderation['error_badmergeurl']);
        if ($mergetid === $tid)   stderr($lang->moderation['error_mergewithself']);

        // $thread['subject'] выше уже экранирован для вывода — берём исходную тему из кеша
        $subject = trim($mybb->get_input('subject')) ?: (string)(get_thread($tid)['subject'] ?? '');
        $moderation->merge_threads($mergetid, $tid, $subject);
        log_moderator_action($modlogdata, 'Threads Merged');
        redirect(get_thread_link($tid), $lang->moderation['redirect_threadsmerged']);
        break;

    // ── Split thread ─────────────────────────────────────────────────────────
    case 'split':
        add_breadcrumb($lang->moderation['nav_split']);
        if (!is_mod($usergroups)) error_no_permission();

        $query = $db->sql_query_prepared("
            SELECT p.*, u.*
            FROM posts p
            LEFT JOIN users u ON (p.uid = u.id)
            WHERE tid = ?
            ORDER BY dateline ASC, pid ASC
        ", [$tid]);

        if ($db->num_rows($query) <= 1) stderr($lang->moderation['error_cantsplitonepost']);

        $posts = '';
        while ($post = $db->fetch_array($query)) {
            $postdate         = my_datee('relative', $post['dateline']);
            $post['username'] = htmlspecialchars_uni((string)$post['username']);
            $message          = $parser->parse_message($post['message'], $parser_options_default);
            $posts .= md_post($post['username'], $postdate, $message,
                md_check('checkbox', 'splitpost[' . (int)$post['pid'] . ']', '1', '#' . (int)$post['pid']));
        }

        clearinline($tid, 'thread');
        $forumselect = build_forum_jump('', $fid, 1, '', 0, true, '', 'moveto');
        $plugins->run_hooks('moderation_split');

        $thread_link = get_thread_link($tid);

        $split_page = md_page(
            md_head('fa-arrows-split-up-and-left', 'info', $lang->moderation['split_thread'], '',
                md_meta('fa-comments', '<a href="' . $thread_link . '">' . $thread['subject'] . '</a>'))
            . '<form action="moderation.php" method="post" class="md-form">
    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    <input type="hidden" name="action" value="do_split" />
    <input type="hidden" name="tid" value="' . $tid . '" />'
            . md_actor_card()
            . md_card('fa-circle-plus', 'primary', $lang->moderation['new_thread_info'],
                '<label class="md-label" for="md_newsubject">' . $lang->moderation['new_subject'] . '</label>
<div class="input-group st-subject mb-3">
    <span class="input-group-text"><i class="fa-solid fa-heading"></i></span>
    <input type="text" class="form-control" id="md_newsubject" name="newsubject" value="' . $lang->moderation['split_thread_subject'] . ' ' . $thread['subject'] . '" />
</div>
<div class="md-label">' . $lang->moderation['new_forum'] . '</div>
<div class="md-forum">' . $forumselect . '</div>')
            . md_card('fa-list-check', 'warning', $lang->moderation['posts_to_split'], '<div class="md-posts">' . $posts . '</div>')
            . md_submitbar($thread_link, $lang->moderation['cancel'],
                '<button type="submit" class="btn btn-primary st-pill" name="submit" value="' . $lang->moderation['split_thread'] . '"><i class="fa-solid fa-arrows-split-up-and-left me-1"></i>' . $lang->moderation['split_thread'] . '</button>')
            . '</form>'
        );

        stdhead($lang->moderation['split_thread']);
        md_assets();
        echo $split_page;
        echo $_mod_assets;
        stdfoot();
        break;

    // ── Do split ─────────────────────────────────────────────────────────────
    case 'do_split':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $plugins->run_hooks('moderation_do_split');

        $splitpost = $mybb->get_input('splitpost', MyBB::INPUT_ARRAY);
        if (empty($splitpost)) stderr($lang->moderation['error_nosplitposts']);

        $count_q = $db->sql_query_prepared("SELECT COUNT(*) AS totalposts FROM posts WHERE tid = ?", [$tid]);
        $count = $count_q ? $db->fetch_array($count_q) : null;
        if ($count['totalposts'] == 1)                           stderr($lang->moderation['error_cantsplitonepost']);
        if ($count['totalposts'] == count($splitpost))           stderr($lang->moderation['error_cantsplitall']);

        $moveto   = !empty($mybb->input['moveto']) ? $mybb->get_input('moveto', MyBB::INPUT_INT) : $fid;
        $newforum = get_forum($moveto);
        if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') stderr($lang->moderation['error_invalidforum']);

        $pids  = [];
        $query = $db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ?", [$tid]);
        while ($query && ($post = $db->fetch_array($query))) {
            if (isset($splitpost[$post['pid']]) && $splitpost[$post['pid']] == 1) {
                $pids[] = $post['pid'];
            }
        }

        $newtid = $moderation->split_posts($pids, $tid, $moveto, $mybb->get_input('newsubject'));
        moderation_redirect(get_thread_link($newtid), $lang->moderation['redirect_threadsplit']);
        break;

    // ── Remove subscriptions ─────────────────────────────────────────────────
    case 'removesubscriptions':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $plugins->run_hooks('moderation_removesubscriptions');
        $moderation->remove_thread_subscriptions($tid, true);
        log_moderator_action($modlogdata, 'Removed All Subscriptions');
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_removed_subscriptions']);
        break;

    // ── Helper: get inline threads ───────────────────────────────────────────

    // ── Delete threads (inline) ──────────────────────────────────────────────
    case 'do_multideletethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads_input = $mybb->get_input('threads');
        $threadlist    = str_contains($threads_input, ',')
            ? explode(',', $threads_input)
            : explode('|', $threads_input);
        $threadlist    = array_map('intval', $threadlist);

        if (!is_mod($usergroups)) error_no_permission();

        foreach ($threadlist as $t) {
            $moderation->delete_thread($t);
        }

        log_moderator_action($modlogdata, 'Threads Deleted Permanently');
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');

        redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsdeleted']);
        break;

    // ── Helper closure for inline thread operations ──────────────────────────
    // ── Open threads (inline) ────────────────────────────────────────────────
    case 'multiopenthreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error($lang->moderation['error_inline_nothreadsselected'], $lang->moderation['error']);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->open_threads($threads);
        log_moderator_action($modlogdata, 'Threads Opened');
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsopened']);
        break;

    // ── Close threads (inline) ───────────────────────────────────────────────
    case 'multiclosethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error($lang->moderation['error_inline_nothreadsselected'], $lang->moderation['error']);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->close_threads($threads);
        log_moderator_action($modlogdata, 'Threads Closed');
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsclosed']);
        break;

    // ── Approve threads (inline) ─────────────────────────────────────────────
    case 'multiapprovethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error($lang->moderation['error_inline_nothreadsselected'], $lang->moderation['error']);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->approve_threads($threads, $fid);
        log_moderator_action($modlogdata, 'Threads Approved');
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        $cache->update_stats();
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsapproved']);
        break;

    // ── Unapprove threads (inline) ───────────────────────────────────────────
    case 'multiunapprovethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error($lang->moderation['error_inline_nothreadsselected'], $lang->moderation['error']);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->unapprove_threads($threads, $fid);
        log_moderator_action($modlogdata, 'Threads Unapproved');
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        $cache->update_stats();
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsunapproved']);
        break;

    // ── Stick threads (inline) ───────────────────────────────────────────────
    case 'multistickthreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) stderr($lang->moderation['error_inline_nothreadsselected'], $lang->moderation['error']);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->stick_threads($threads);
        log_moderator_action($modlogdata, 'Threads Stuck');
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsstuck']);
        break;

    // ── Unstick threads (inline) ─────────────────────────────────────────────
    case 'multiunstickthreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) stderr($lang->moderation['error_inline_nothreadsselected'], $lang->moderation['error']);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->unstick_threads($threads);
        log_moderator_action($modlogdata, 'Threads Unstuck');
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsunstuck']);
        break;

    // ── Move threads (inline) — show form ────────────────────────────────────
    case 'multimovethreads':
        if (!is_mod($usergroups)) error_no_permission();
        add_breadcrumb($lang->moderation['nav_multi_movethreads']);

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) stderr($lang->moderation['error_inline_nothreadsselected']);

        $inlineids    = implode('|', $threads);
        $thread_count = count($threads);

        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');

        $forumselect = build_forum_jump('', '', 1, '', 0, true, '', 'moveto');
        $return_url  = htmlspecialchars_uni($mybb->get_input('url'));
        $cancel_url  = md_cancel_url(get_forum_link($fid));

        $bulk_threads_hint = ags_fmt($lang->moderation['hint_bulk_threads'], $thread_count);
        $forum_meta = isset($forum['name']) ? md_meta('fa-folder', htmlspecialchars_uni($forum['name'])) : '';

        $movethreads = md_page(
            md_head('fa-right-left', 'primary', $lang->moderation['move_threads'], $lang->moderation['sub_move_threads'], $forum_meta,
                md_kpi((string)$thread_count, $lang->moderation['lbl_threads_selected']))
            . '<form action="moderation.php" method="post" id="multiMoveForm" class="md-form">
    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    <input type="hidden" name="action" value="do_multimovethreads" />
    <input type="hidden" name="fid" value="' . $fid . '" />
    <input type="hidden" name="threads" value="' . $inlineids . '" />
    <input type="hidden" name="url" value="' . $return_url . '" />'
            . md_actor_card()
            . md_card('fa-folder-open', 'primary', $lang->moderation['new_forum'],
                md_note('info', 'fa-circle-info', $bulk_threads_hint)
                . '<div class="md-forum">' . $forumselect . '</div>
<div class="md-label mt-3">' . $lang->moderation['method'] . '</div>
<div class="md-grid">'
                . md_check('radio', 'method', 'move', $lang->moderation['method_move'], false, '', 'fa-arrow-right')
                . md_check('radio', 'method', 'redirect', $lang->moderation['method_move_redirect'], true, '', 'fa-link')
                . md_check('radio', 'method', 'copy', $lang->moderation['method_copy'], false, '', 'fa-copy') . '
</div>
<div class="md-inline mt-2">
    <input type="number" name="redirect_expire" class="form-control md-days" placeholder="' . $lang->moderation['ph_days'] . '" min="1" max="365" />
    <span class="md-hint">' . $lang->moderation['redirect_expire_note'] . '</span>
</div>')
            . md_submitbar($cancel_url, $lang->moderation['cancel'],
                '<button type="submit" name="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-right-left me-1"></i>' . $lang->moderation['move_threads'] . '</button>')
            . '</form>'
        );

        stdhead($lang->moderation['move_threads']);
        md_assets();
        echo $movethreads;
        echo $_mod_assets;
        stdfoot();
        break;

    // ── Do move threads (inline) ─────────────────────────────────────────────
    case 'do_multimovethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $moveto     = $mybb->get_input('moveto', MyBB::INPUT_INT);
        $method     = $mybb->get_input('method');
        $threadlist = explode('|', $mybb->get_input('threads'));

        if (!is_mod($usergroups)) error_no_permission();

        $tids = array_map('intval', $threadlist);

        $newforum = get_forum($moveto);
        if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') stderr($lang->moderation['error_invalidforum']);

        $plugins->run_hooks('moderation_do_multimovethreads');
        log_moderator_action($modlogdata, 'Threads Moved / Copied');

        $expire = $mybb->get_input('redirect_expire', MyBB::INPUT_INT) > 0
            ? TIMENOW + ($mybb->get_input('redirect_expire', MyBB::INPUT_INT) * 86400)
            : 0;

        foreach ($tids as $t) {
            $moderation->move_thread($t, $moveto, $method, $expire);
        }

        moderation_redirect(get_forum_link($moveto), $lang->moderation['redirect_inline_threadsmoved']);
        break;

    // ── Delete posts (inline) ────────────────────────────────────────────────
    case 'do_multideleteposts':
        verify_post_check($mybb->get_input('my_post_key'));

        $postlist = array_map('intval', explode(',', $mybb->get_input('posts')));
        if (!is_mod($usergroups)) error_no_permission();

        $tids = [];

        if ($postlist) {
            $ph = implode(',', array_fill(0, count($postlist), '?'));
            $query = $db->sql_query_prepared("SELECT tid FROM threads WHERE firstpost IN ({$ph})", $postlist);
            while ($query && ($threadid = $db->fetch_field($query, 'tid'))) {
                $tids[] = $threadid;
            }
        }

        $deletecount = 0;
        foreach ($postlist as $p) {
            $moderation->delete_post($p);
            $deletecount++;
        }

        if (!empty($tids)) {
            foreach ($tids as $t) {
                $moderation->delete_thread((int)$t);
            }
            $url = get_forum_link($fid);
        } else {
            $numposts = $db->num_rows($db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ?", [$tid]));
            if (!$numposts) {
                $moderation->delete_thread($tid);
                $url = get_forum_link($fid);
            } else {
                $url = get_thread_link($thread['tid']);
            }
        }

        log_moderator_action($modlogdata, 'Deleted Selective Posts (' . $deletecount . ')');
        redirect($url, $lang->moderation['redirect_postsdeleted']);
        break;

    // ── Merge posts (inline) — show form ─────────────────────────────────────
    case 'multimergeposts':
        if (!is_mod($usergroups)) error_no_permission();
        add_breadcrumb($lang->moderation['nav_multi_mergeposts']);

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        // Collect posts from other threads via cookies
        foreach ($mybb->cookies as $key => $value) {
            if (str_contains($key, 'inlinemod_thread') && $key !== "inlinemod_thread{$tid}") {
                foreach (explode('|', $value) as $p) {
                    $p = (int)$p;
                    if ($p) $posts[] = $p;
                }
                my_unsetcookie($key);
            }
        }

        if (empty($posts)) {
            stderr($lang->moderation['error_inline_nopostsselected']);
        }

        $postlist = '';
        $placeholders = implode(',', array_fill(0, count($posts), '?'));
        $query = $db->sql_query_prepared("
            SELECT p.*, u.*
            FROM posts p
            LEFT JOIN users u ON (p.uid = u.id)
            WHERE pid IN ({$placeholders})
            ORDER BY dateline ASC, pid ASC
        ", $posts);

        while ($post = $db->fetch_array($query)) {
            $postdate = my_datee('relative', $post['dateline']);
            $message  = $parser->parse_message($post['message'], $parser_options_default);
            // Имя раньше выводилось без экранирования (XSS)
            $postlist .= md_post(htmlspecialchars_uni((string)$post['username']), $postdate, $message,
                md_check('checkbox', 'mergepost[' . (int)$post['pid'] . ']', '1', '#' . (int)$post['pid'], true));
        }

        $inlineids  = implode('|', $posts);
        $post_count = count($posts);

        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');

        $return_url = htmlspecialchars_uni($mybb->get_input('url'));
        $cancel_url = md_cancel_url(get_thread_link($tid));

        $posts_selected_text = ags_fmt($lang->moderation['lbl_posts_selected'], $post_count);

        $thread_meta = isset($thread['subject']) ? md_meta('fa-comments', '<a href="' . get_thread_link($tid) . '">' . $thread['subject'] . '</a>') : '';

        $merge_preview = '<div class="md-mpreview">
    <div class="md-label">' . $lang->moderation['lbl_preview'] . '</div>
    <div class="md-bubble"><span class="md-avatar"></span><div class="md-bubble__txt">' . $lang->moderation['lbl_preview_first'] . '</div></div>
    <div class="md-sep-hr" id="hrPreview"></div>
    <div class="md-sep-nl d-none" id="newlinePreview"><i class="fa-solid fa-arrow-down me-1"></i>' . $lang->moderation['lbl_preview_newparagraph'] . '</div>
    <div class="md-bubble"><span class="md-avatar"></span><div class="md-bubble__txt">' . $lang->moderation['lbl_preview_second'] . '</div></div>
</div>';

        $merge_page = md_page(
            md_head('fa-shuffle', 'primary', $lang->moderation['merge_posts'], $lang->moderation['sub_merge_posts'], $thread_meta,
                md_kpi((string)$post_count, $lang->moderation['step_posts_to_merge']))
            . '<form action="moderation.php" method="post" id="mergePostsForm" class="md-form">
    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    <input type="hidden" name="action" value="do_multimergeposts" />
    <input type="hidden" name="tid" value="' . $tid . '" />
    <input type="hidden" name="url" value="' . $return_url . '" />'
            . md_actor_card()
            . md_card('fa-grip-lines', 'info', md_step(1, $lang->moderation['step_select_separator']),
                '<div class="md-grid md-grid--2">'
                . md_check('radio', 'sep', 'hr', $lang->moderation['horizontal_rule'], true, '', 'fa-minus', $lang->moderation['tip_sep_hr'])
                . md_check('radio', 'sep', 'new_line', $lang->moderation['new_line'], false, '', 'fa-arrow-down', $lang->moderation['tip_sep_newline'])
                . '</div>' . $merge_preview)
            . md_card('fa-list-check', 'warning', md_step(2, $lang->moderation['step_posts_to_merge']),
                '<div class="md-posts">' . $postlist . '</div>' . md_note('info', 'fa-circle-info', $lang->moderation['tip_merge_chrono']),
                '', '<span class="st-badge st-soft-primary ms-auto">' . $posts_selected_text . '</span>')
            . md_submitbar($cancel_url, $lang->moderation['btn_cancel_return'],
                '<button type="submit" class="btn btn-primary st-pill" name="submit" value="' . $lang->moderation['merge_posts'] . '"><i class="fa-solid fa-shuffle me-1"></i>' . $lang->moderation['merge_posts'] . '</button>')
            . '</form>
<script src="' . $BASEURL . '/scripts/moderation-page.js?ver=1"></script>'
        );

        // Раньше тут не было stdfoot() и ассетов — страница обрывалась без подвала
        stdhead($lang->moderation['merge_posts']);
        md_assets();
        echo $merge_page;
        echo $_mod_assets;
        stdfoot();
        break;

    // ── Do merge posts (inline) ──────────────────────────────────────────────
    case 'do_multimergeposts':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $mergepost = $mybb->get_input('mergepost', MyBB::INPUT_ARRAY);
        if (count($mergepost) <= 1) stderr($lang->moderation['error_nomergeposts']);

        $postlist = array_map('intval', array_keys($mergepost));

        $masterpid = $moderation->merge_posts($postlist, (int)$tid, $mybb->input['sep'] ?? 'hr');
        log_moderator_action($modlogdata, 'Merged Selective Posts');
        redirect(get_post_link($masterpid) . "#pid{$masterpid}", $lang->moderation['redirect_inline_postsmerged']);
        break;

    // ── Split posts (inline) — show form ─────────────────────────────────────
    case 'multisplitposts':
        add_breadcrumb($lang->moderation['nav_multi_splitposts']);

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        if (count($posts) < 1) stderr($lang->moderation['error_inline_nopostsselected']);
        if (!is_mod($usergroups)) error_no_permission();

        $posts  = array_map('intval', $posts);
        $placeholders = implode(',', array_fill(0, count($posts), '?'));

        // Validate: no single-post threads
        $query = $db->sql_query_prepared("
            SELECT DISTINCT p.tid, COUNT(q.pid) as count
            FROM posts p LEFT JOIN posts q ON (p.tid=q.tid)
            WHERE p.pid IN ({$placeholders}) GROUP BY p.tid, p.pid
        ", $posts);
        $pcheck = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ((int)$tcheck['count'] <= 1) stderr($lang->moderation['error_cantsplitonepost']);
            $pcheck[] = $tcheck['tid'];
        }

        // Validate: not splitting all posts
        $query = $db->sql_query_prepared("
            SELECT DISTINCT p.tid, COUNT(q.pid) as count
            FROM posts p LEFT JOIN posts q ON (p.tid=q.tid)
            WHERE p.pid IN ({$placeholders}) AND q.pid NOT IN ({$placeholders}) GROUP BY p.tid, p.pid
        ", [...$posts, ...$posts]);
        $pcheck2 = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ($tcheck['count'] > 0) $pcheck2[] = $tcheck['tid'];
        }
        if (count($pcheck2) !== count($pcheck)) stderr($lang->moderation['error_cantsplitall']);

        $inlineids = implode('|', $posts);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');

        $forumselect = build_forum_jump('', $fid, 1, '', 0, true, '', 'moveto');
        $return_url  = htmlspecialchars_uni($mybb->get_input('url'));

        $post_count  = count($posts);
        $split_warning = ags_fmt($lang->moderation['warn_split_posts'], $post_count);

        $cancel_url  = md_cancel_url(get_thread_link($tid));
        $thread_link = get_thread_link($tid);

        $splitposts_page = md_page(
            md_head('fa-arrows-split-up-and-left', 'info', $lang->moderation['split_thread'], $lang->moderation['sub_split_posts'],
                md_meta('fa-comments', '<a href="' . $thread_link . '">' . $thread['subject'] . '</a>'),
                md_kpi('<span id="postsCount">' . $post_count . '</span>', $lang->moderation['lbl_posts_selected_split']))
            . '<form action="moderation.php" method="post" id="splitThreadForm" class="md-form">
    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    <input type="hidden" name="action" value="do_multisplitposts" />
    <input type="hidden" name="tid" value="' . $tid . '" />
    <input type="hidden" name="posts" value="' . $inlineids . '" />
    <input type="hidden" name="url" value="' . $return_url . '" />'
            . md_actor_card()
            . md_note('info', 'fa-circle-info', $lang->moderation['tip_split_info'], $lang->moderation['sec_split_info'])
            . md_card('fa-file-lines', 'muted', $lang->moderation['sec_original_thread'],
                '<div class="st-rows">' . md_row($lang->moderation['lbl_thread_id'], '<a href="' . $thread_link . '">#' . $tid . '</a>') . '</div>')
            . md_card('fa-circle-plus', 'primary', $lang->moderation['new_thread_info'],
                '<label class="md-label" for="md_newsubject">' . $lang->moderation['new_subject'] . '</label>
<div class="input-group st-subject mb-3">
    <span class="input-group-text"><i class="fa-solid fa-heading"></i></span>
    <input type="text" class="form-control" id="md_newsubject" name="newsubject" value="' . $lang->moderation['split_thread_subject'] . ' ' . $thread['subject'] . '" required />
</div>
<div class="md-label">' . $lang->moderation['new_forum'] . '</div>
<div class="md-forum">' . $forumselect . '</div>')
            . md_note('warning', 'fa-triangle-exclamation', $split_warning)
            . md_submitbar($cancel_url, $lang->moderation['btn_cancel_return'],
                '<button type="submit" class="btn btn-primary st-pill" name="submit" value="' . $lang->moderation['split_thread'] . '"><i class="fa-solid fa-arrows-split-up-and-left me-1"></i>' . $lang->moderation['split_thread'] . '</button>')
            . '</form>
<script src="' . $BASEURL . '/scripts/split-thread.js?ver=23"></script>'
        );

        stdhead($lang->moderation['split_thread']);
        md_assets();
        echo $splitposts_page;
        echo $_mod_assets;
        stdfoot();
        break;

    // ── Do split posts (inline) ──────────────────────────────────────────────
    case 'do_multisplitposts':
        verify_post_check($mybb->get_input('my_post_key'));

        $plist = array_map('intval', explode('|', $mybb->get_input('posts')));
        if (!is_mod($usergroups)) error_no_permission();

        $posts = [];
        if (!empty($plist)) {
            $ph = implode(',', array_fill(0, count($plist), '?'));
            $query = $db->sql_query_prepared("SELECT pid FROM posts WHERE pid IN ({$ph})", $plist);
            while ($query && ($p = $db->fetch_field($query, 'pid'))) $posts[] = $p;
        }
        if (empty($posts)) error($lang->moderation['error_inline_nopostsselected'], $lang->moderation['error']);

        $placeholders = implode(',', array_fill(0, count($posts), '?'));

        $query  = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) GROUP BY p.tid, p.pid", $posts);
        $pcheck = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ((int)$tcheck['count'] <= 1) error($lang->moderation['error_cantsplitonepost'], $lang->moderation['error']);
            $pcheck[] = $tcheck['tid'];
        }

        $query   = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) AND q.pid NOT IN ({$placeholders}) GROUP BY p.tid, p.pid", [...$posts, ...$posts]);
        $pcheck2 = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ($tcheck['count'] > 0) $pcheck2[] = $tcheck['tid'];
        }
        if (count($pcheck2) !== count($pcheck)) error($lang->moderation['error_cantsplitall'], $lang->moderation['error']);

        $moveto   = isset($mybb->input['moveto']) ? $mybb->get_input('moveto', MyBB::INPUT_INT) : $fid;
        $newforum = get_forum($moveto);
        if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') error($lang->moderation['error_invalidforum'], $lang->moderation['error']);

        $newtid = $moderation->split_posts($posts, $tid, $moveto, $mybb->get_input('newsubject'));
        log_moderator_action($modlogdata, 'Split posts (PIDs: ' . implode(', ', $posts) . ') to thread (TID: ' . $newtid . ')');
        moderation_redirect(get_thread_link($newtid), $lang->moderation['redirect_threadsplit']);
        break;

    // ── Move posts (inline) — show form ──────────────────────────────────────
    case 'multimoveposts':
        add_breadcrumb($lang->moderation['nav_multi_moveposts']);

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        if (count($posts) < 1) stderr($lang->moderation['error_inline_nopostsselected']);
        if (!is_mod($usergroups)) error_no_permission();

        $posts  = array_map('intval', $posts);
        $placeholders = implode(',', array_fill(0, count($posts), '?'));

        $query  = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) GROUP BY p.tid, p.pid", $posts);
        $pcheck = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ((int)$tcheck['count'] <= 1) error($lang->moderation['error_cantsplitonepost'], $lang->moderation['error']);
            $pcheck[] = $tcheck['tid'];
        }

        $query   = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) AND q.pid NOT IN ({$placeholders}) GROUP BY p.tid, p.pid", [...$posts, ...$posts]);
        $pcheck2 = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ($tcheck['count'] > 0) $pcheck2[] = $tcheck['tid'];
        }
        if (count($pcheck2) !== count($pcheck)) error($lang->moderation['error_cantmoveall'], $lang->moderation['error']);

        $inlineids  = implode('|', $posts);
        $post_count = count($posts);

        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');

        $return_url = htmlspecialchars_uni($mybb->get_input('url'));

        $posts_selected_text = ags_fmt($lang->moderation['lbl_posts_selected'], $post_count);
        $move_posts_count    = ags_fmt($lang->moderation['lbl_posts_count'], $post_count);

        $cancel_url  = md_cancel_url(get_thread_link($tid));
        $thread_link = get_thread_link($tid);
        $example_url = htmlspecialchars_uni($BASEURL) . '/showthread.php?tid=456';

        $notes = '<ul class="md-list">'
            . '<li>' . $lang->moderation['hint_move_note_1'] . '</li>'
            . '<li>' . $lang->moderation['hint_move_note_2'] . '</li>'
            . '<li>' . $lang->moderation['hint_move_note_3'] . '</li>'
            . '<li>' . $lang->moderation['hint_move_note_4'] . '</li></ul>';

        $moveposts_page = md_page(
            md_head('fa-arrow-right-to-bracket', 'primary', $lang->moderation['move_posts'], $lang->moderation['sub_move_posts'],
                md_meta('fa-comments', '<a href="' . $thread_link . '">' . $thread['subject'] . '</a>'))
            . '<div class="md-kpis">'
                . md_kpi((string)$post_count, $lang->moderation['lbl_posts_to_move'])
                . md_kpi('#' . $tid, $lang->moderation['lbl_current_tid'], 'muted')
            . '</div>'
            . '<form action="moderation.php" method="post" id="moveForm" class="md-form">
    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    <input type="hidden" name="action" value="do_multimoveposts" />
    <input type="hidden" name="tid" value="' . $tid . '" />
    <input type="hidden" name="posts" value="' . $inlineids . '" />
    <input type="hidden" name="url" value="' . $return_url . '" />'
            . md_actor_card()
            . md_note('info', 'fa-circle-info', $lang->moderation['tip_move_posts'], $lang->moderation['sec_how_to_move'])
            . md_card('fa-link', 'primary', $lang->moderation['lbl_dest_url'],
                '<div class="input-group st-subject">
    <span class="input-group-text"><i class="fa-solid fa-link"></i></span>
    <input type="text" class="form-control" name="threadurl" id="threadUrl" placeholder="' . $lang->moderation['ph_thread_url'] . '" autocomplete="off" required />
    <button type="button" class="btn btn-outline-secondary" id="clearUrl" aria-label="' . $lang->moderation['cancel'] . '"><i class="fa-solid fa-xmark"></i></button>
</div>
<div class="md-hint md-example"><i class="fa-solid fa-lightbulb me-1"></i><strong>' . $lang->moderation['lbl_example'] . '</strong> <span class="md-break">' . $example_url . '</span></div>
<div class="md-preview" id="threadPreview">
    <div class="md-label"><i class="fa-solid fa-eye me-1"></i>' . $lang->moderation['lbl_thread_preview'] . '</div>
    <div class="md-preview__body" id="previewContent">' . $lang->moderation['lbl_preview_empty'] . '</div>
</div>', '', '<span class="st-badge st-soft-primary ms-auto">' . $posts_selected_text . '</span>')
            . md_note('warning', 'fa-triangle-exclamation', $notes, $lang->moderation['sec_important_notes'])
            . md_submitbar($cancel_url, $lang->moderation['btn_cancel_return'],
                '<button type="button" class="btn btn-outline-primary st-pill" id="validateBtn"><i class="fa-solid fa-circle-check me-1"></i>' . $lang->moderation['btn_validate_url'] . '</button>
        <button type="submit" class="btn btn-primary st-pill btn-move" name="submit" value="' . $lang->moderation['move_posts'] . '"><i class="fa-solid fa-arrow-right me-1"></i>' . $lang->moderation['move_posts'] . '</button>')
            . '</form>'
        );

        stdhead($lang->moderation['move_posts']);
        md_assets();
        build_breadcrumb();
        echo $moveposts_page;
        echo $_mod_assets;
        stdfoot();
        break;

    // ── Do move posts (inline) ───────────────────────────────────────────────
    case 'do_multimoveposts':
        verify_post_check($mybb->get_input('my_post_key'));
        $plugins->run_hooks('moderation_do_multimoveposts');

        $realurl = explode('#', $mybb->get_input('threadurl'));
        $mybb->input['threadurl'] = $realurl[0];
        $parameters = [];

        if (str_ends_with($mybb->input['threadurl'], 'html')) {
            preg_match('#thread-([0-9]+)?#i', $mybb->input['threadurl'], $threadmatch);
            preg_match('#post-([0-9]+)?#i',   $mybb->input['threadurl'], $postmatch);
            if (!empty($threadmatch[1])) $parameters['tid'] = $threadmatch[1];
            if (!empty($postmatch[1]))   $parameters['pid'] = $postmatch[1];
        } else {
            $splitloc = explode('.php', $mybb->input['threadurl']);
            foreach (explode('&', my_substr($splitloc[1], 1)) as $pair) {
                [$k, $v] = explode('=', $pair, 2) + [1 => ''];
                $parameters[$k] = $v;
            }
        }

        if (!empty($parameters['pid']) && empty($parameters['tid'])) {
            $newtid = (int)$db->fetch_field($db->sql_query_prepared("SELECT tid FROM posts WHERE pid = ?", [(int)$parameters['pid']]), 'tid');
        } else {
            $newtid = (int)($parameters['tid'] ?? 0);
        }

        $newthread = get_thread($newtid);
        if (!$newthread)    stderr($lang->moderation['error_badmovepostsurl']);
        if ($newtid === $tid) stderr($lang->moderation['error_movetoself']);

        $plist = array_map('intval', explode('|', $mybb->get_input('posts')));
        if (!is_mod($usergroups)) error_no_permission();

        $posts = [];
        if (!empty($plist)) {
            $ph = implode(',', array_fill(0, count($plist), '?'));
            $query = $db->sql_query_prepared("SELECT pid FROM posts WHERE pid IN ({$ph})", $plist);
            while ($query && ($p = $db->fetch_field($query, 'pid'))) $posts[] = $p;
        }
        if (empty($posts)) stderr($lang->moderation['error_inline_nopostsselected']);

        $placeholders = implode(',', array_fill(0, count($posts), '?'));
        $query  = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) GROUP BY p.tid, p.pid", $posts);
        $pcheck = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ((int)$tcheck['count'] <= 1) stderr($lang->moderation['error_cantsplitonepost']);
            $pcheck[] = $tcheck['tid'];
        }

        $query   = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) AND q.pid NOT IN ({$placeholders}) GROUP BY p.tid, p.pid", [...$posts, ...$posts]);
        $pcheck2 = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ($tcheck['count'] > 0) $pcheck2[] = $tcheck['tid'];
        }
        if (count($pcheck2) !== count($pcheck)) stderr($lang->moderation['error_cantmoveall']);

       $newtid = $moderation->split_posts($posts, $tid, (int)$newthread['fid'], $newthread['subject'], $newtid);
        log_moderator_action($modlogdata, 'Moved posts (PIDs: ' . implode(', ', $posts) . ') to thread (TID: ' . $newtid . ')');
        moderation_redirect(get_thread_link($newtid), $lang->moderation['redirect_moveposts']);
        break;

    // ── Approve posts (inline) ───────────────────────────────────────────────
    case 'multiapproveposts':
        verify_post_check($mybb->get_input('my_post_key'));

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        if (count($posts) < 1) stderr($lang->moderation['error_inline_nopostsselected']);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->approve_posts(array_map('intval', $posts));
        log_moderator_action($modlogdata, 'Selected Posts Approved');
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_inline_postsapproved']);
        break;

    // ── Unapprove posts (inline) ─────────────────────────────────────────────
    case 'multiunapproveposts':
        verify_post_check($mybb->get_input('my_post_key'));

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        if (count($posts) < 1) error($lang->moderation['error_inline_nopostsselected'], $lang->moderation['error']);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->unapprove_posts(array_map('intval', $posts));
        log_moderator_action($modlogdata, 'Selected Posts Unapproved');
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_inline_postsunapproved']);
        break;

}

// ── Helper functions ──────────────────────────────────────────────────────────

function getids(string|int $id, string $type): array
{
    global $mybb;

    $newids = [];
    $cookie = "inlinemod_{$type}{$id}";

    if (!isset($mybb->cookies[$cookie])) {
        return $newids;
    }

    foreach (explode('|', $mybb->cookies[$cookie]) as $cookie_id) {
        if ($cookie_id === '') continue;
        if ($cookie_id === 'ALL') {
            $newids += getallids($id, $type);
        } else {
            $newids[] = (int)$cookie_id;
        }
    }

    return $newids;
}

function getallids(string|int $id, string $type): array
{
    global $db, $mybb, $CURUSER;

    $ids         = [];
    $removed_ids = [];
    $cookie      = "inlinemod_{$type}{$id}_removed";

    if (isset($mybb->cookies[$cookie])) {
        $removed_ids = explode('|', $mybb->cookies[$cookie]);
        if (!is_array($removed_ids)) $removed_ids = [];
    }

    if ($type === 'forum') {
        $query = $db->sql_query_prepared("SELECT tid FROM threads WHERE fid = ?", [(int)$id]);
        while ($query && ($tid = $db->fetch_field($query, 'tid'))) {
            if (!in_array($tid, $removed_ids)) $ids[] = $tid;
        }
    } elseif ($type === 'search') {
        $query     = $db->sql_query_prepared("SELECT resulttype, posts, threads FROM searchlog WHERE sid = ? AND uid = ?", [$id, $CURUSER['id']]);
        $searchlog = $query ? $db->fetch_array($query) : null;
        $ids       = explode(',', $searchlog['resulttype'] === 'posts' ? $searchlog['posts'] : $searchlog['threads']);

        if (is_array($ids)) {
            foreach ($ids as $key => $tid) {
                if (in_array($tid, $removed_ids)) unset($ids[$key]);
            }
        }
    }

    return $ids;
}

function clearinline(string|int $id, string $type): void
{
    my_unsetcookie("inlinemod_{$type}{$id}");
    my_unsetcookie("inlinemod_{$type}{$id}_removed");
}

function extendinline(string|int $id, string $type): void
{
    my_setcookie("inlinemod_{$type}{$id}",         '', TIMENOW + 3600);
    my_setcookie("inlinemod_{$type}{$id}_removed", '', TIMENOW + 3600);
}

/**
 * Collect lang keys with the js_ prefix (prefix stripped) and return them as JSON
 * for `const AGS_LANG = ...;` printed before the page script.
 */
function mod_js_lang(): string
{
    global $lang;

    $arr = [];
    foreach ($lang->moderation as $key => $value) {
        if (str_starts_with((string)$key, 'js_')) {
            $arr[substr((string)$key, 3)] = $value;
        }
    }

    return json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
}

function moderation_redirect(string $url, string $message = '', string $title = ''): void
{
    global $mybb, $BASEURL;

    if (!empty($mybb->input['url'])) {
        $url = htmlentities($mybb->input['url']);
    }

    if (!str_starts_with($url, $BASEURL . '/')) {
    if (str_starts_with($url, '/')) $url = my_substr($url, 1);
    $parts = explode('/', $url);
    $url = $BASEURL . '/' . end($parts);
}

    redirect($url, $message, $title);
}