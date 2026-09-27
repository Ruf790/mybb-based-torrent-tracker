<?php

declare(strict_types=1);




// ── generate_forum_select ─────────────────────────────────────────────────────
function generate_forum_select(string $name, mixed $selected, array $options = [], bool $is_first = true): string
{
    global $fselectcache, $forum_cache, $selectoptions;

    $selectoptions ??= '';
    $options['depth'] = (int)($options['depth'] ?? 0);
    $pid              = (int)($options['pid']   ?? 0);

    if (!is_array($fselectcache)) {
        if (!is_array($forum_cache)) {
            $forum_cache = cache_forums();
        }
        foreach ($forum_cache as $forum) {
            $fselectcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
        }
    }

    if (isset($options['main_option']) && $is_first) {
        $sel = $selected == -1 ? ' selected="selected"' : '';
        $selectoptions .= "<option value=\"-1\"{$sel}>{$options['main_option']}</option>\n";
    }

    if (isset($fselectcache[$pid])) {
        foreach ($fselectcache[$pid] as $main) {
            foreach ($main as $forum) {
                if ($forum['fid'] == '0' || $forum['linkto'] !== '') continue;

                $sel   = (!empty($selected) && ($forum['fid'] == $selected || (is_array($selected) && in_array($forum['fid'], $selected))))
                    ? ' selected="selected"' : '';
                $sep   = str_repeat('&nbsp;', $options['depth']);
                $style = $forum['active'] == 0 ? ' style="font-style:italic"' : '';

                $selectoptions .= "<option value=\"{$forum['fid']}\"{$style}{$sel}>{$sep}"
                    . htmlspecialchars_uni(strip_tags($forum['name'])) . "</option>\n";

                if (!empty($forum_cache[$forum['fid']])) {
                    $options['depth'] += 5;
                    $options['pid']    = $forum['fid'];
                    generate_forum_select((string)$forum['fid'], $selected, $options, false);
                    $options['depth'] -= 5;
                }
            }
        }
    }

    if (!$is_first) return '';

    $select = isset($options['multiple'])
        ? "<select name=\"{$name}\" multiple=\"multiple\""
        : "<select name=\"{$name}\" class=\"form-select form-select-sm border pe-5 w-auto\"";

    if (isset($options['class'])) $select .= " class=\"{$options['class']}\"";
    if (isset($options['id']))    $select .= " id=\"{$options['id']}\"";
    if (isset($options['size']))  $select .= " size=\"{$options['size']}\"";

    $result        = $select . ">\n" . $selectoptions . "</select>\n";
    $selectoptions = '';
    return $result;
}





// ── get_user_class_name ───────────────────────────────────────────────────────
function get_user_class_name(string $class = ''): string
{
    if ($class === 'all') {
        return 'ALL Usergroups';
    }

    require TSDIR . '/cache/usergroups.php';
    foreach ($usergroups as $arr) {
        if ((string)$arr['gid'] === $class) {
            return $arr['title'];
        }
    }

    return 'ALL Usergroups';
}



// ── output_inline_error ───────────────────────────────────────────────────────
function output_inline_error(array|string $errors): void
{
    if (!is_array($errors)) $errors = [$errors];

    echo '<div class="container mt-3"><div class="red_alert">';
    echo '<p><em>The following errors were encountered:</em></p><ul>';
    foreach ($errors as $error) {
        echo '<li>' . $error . '</li>';
    }
    echo '</ul></div></div>';
}




// ── generate_select_box ───────────────────────────────────────────────────────
function generate_select_box(string $name, array $option_list = [], mixed $selected = [], array $options = []): string
{
    $multiple = isset($options['multiple']);
    $select   = $multiple
        ? "<select name=\"{$name}\" multiple=\"multiple\""
        : "<select class=\"form-select border form-select-sm w-auto pe-5\" name=\"{$name}\"";

    if (isset($options['class'])) $select .= " class=\"{$options['class']}\"";
    if (isset($options['id']))    $select .= " id=\"{$options['id']}\"";

    $size = $options['size'] ?? ($multiple ? count($option_list) : null);
    if ($size !== null) $select .= " size=\"{$size}\"";

    $select .= ">\n";

    foreach ($option_list as $value => $option) {
        $sel = (!is_array($selected) || !empty($selected))
            && ((is_array($selected) && in_array((string)$value, $selected))
            || (!is_array($selected) && (string)$value === (string)$selected))
            ? ' selected="selected"' : '';
        $select .= "<option value=\"{$value}\"{$sel}>{$option}</option>\n";
    }

    return $select . "</select>\n";
}





// ── flash_message ─────────────────────────────────────────────────────────────
function flash_message(?string $message = null, string $type = 'info', bool $raw_html = false): void
{
    if ($message !== null) {
        $_SESSION['flash'][] = ['message' => $message, 'type' => $type, 'raw' => $raw_html];
        return;
    }

    if (empty($_SESSION['flash'])) return;

    echo '<div aria-live="polite" aria-atomic="true" class="position-relative">
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100;">';

    foreach ($_SESSION['flash'] as $flash) {
        $cls = match ($flash['type']) {
            'success'         => 'bg-success text-white',
            'error', 'danger' => 'bg-danger text-white',
            'warning'         => 'bg-warning text-dark',
            default           => 'bg-info text-white',
        };
        $msg = $flash['raw'] ? $flash['message'] : htmlspecialchars($flash['message']);

        echo "<div class='toast border-0 mb-2' role='alert' aria-live='assertive' aria-atomic='true'>
            <div class='toast-header {$cls}'>
                <strong class='me-auto'>Message</strong>
                <small>Now</small>
                <button type='button' class='btn-close' data-bs-dismiss='toast'></button>
            </div>
            <div class='toast-body'>{$msg}</div>
        </div>";
    }

    echo '</div></div>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".toast").forEach(el => new bootstrap.Toast(el, {delay:5000}).show());
    });
    </script>';

    unset($_SESSION['flash']);
}

// ── admin_redirect ────────────────────────────────────────────────────────────
function admin_redirect(string $url): never
{
    $url = str_replace('&amp;', '&', $url);
    if (!headers_sent()) {
        header("Location: {$url}");
    } else {
        echo "<meta http-equiv=\"refresh\" content=\"0; url={$url}\">";
    }
    exit;
}

// ── make_parent_list ──────────────────────────────────────────────────────────
function make_parent_list(int $fid, string $navsep = ','): string
{
    global $pforumcache, $db;

    if (!$pforumcache) {
        $q = $db->sql_query_prepared("SELECT name, fid, pid FROM forums ORDER BY disporder, pid");
        while ($q && ($forum = $db->fetch_array($q))) {
            $pforumcache[$forum['fid']][$forum['pid']] = $forum;
        }
    }

    if (empty($pforumcache[$fid])) return '';

    $navigation = '';
    foreach ($pforumcache[$fid] as $forum) {
        if ($fid !== (int)$forum['fid']) continue;
        if (!empty($pforumcache[$forum['pid']])) {
            $navigation = make_parent_list((int)$forum['pid'], $navsep) . $navigation;
        }
        if ($navigation) $navigation .= $navsep;
        $navigation .= $forum['fid'];
    }

    return $navigation;
}



// ── get_announcement_link ─────────────────────────────────────────────────────
function get_announcement_link(int $aid = 0): string
{
    return htmlspecialchars_uni(str_replace('{aid}', (string)$aid, ANNOUNCEMENT_URL));
}
  
  
  
// ── get_forum_link ────────────────────────────────────────────────────────────
function get_forum_link(int|string $fid, int|string $page = 0): string
{
    $fid  = (int)$fid;
    $page = (int)$page;
	
	
	if ($page > 0) {
        return htmlspecialchars_uni(
            str_replace(['{fid}', '{page}'], [$fid, $page], FORUM_URL_PAGED)
        );
    }
    return htmlspecialchars_uni(str_replace('{fid}', (string)$fid, FORUM_URL));
}
 
 
// ── get_post_link ─────────────────────────────────────────────────────────────
function get_post_link(int|string $pid, int|string $tid = 0): string
{
    $pid = (int)$pid;
    $tid = (int)$tid;
	
	if ($tid > 0) {
        return htmlspecialchars_uni(
            str_replace(['{tid}', '{pid}'], [$tid, $pid], THREAD_URL_POST)
        );
    }
    return htmlspecialchars_uni(str_replace('{pid}', (string)$pid, POST_URL));
}
 
 

// ── get_thread_link ───────────────────────────────────────────────────────────
function get_thread_link(int|string $tid, int|string $page = 0, string $action = ''): string
{
    $tid  = (int)$tid;
    $page = (int)$page;
	
	$template = match(true) {
        $page > 1 && $action !== '' => THREAD_URL_ACTION,
        $page > 1                   => THREAD_URL_PAGED,
        $action !== ''              => THREAD_URL_ACTION,
        default                     => THREAD_URL,
    };

    $link = str_replace(['{tid}', '{page}', '{action}'], [$tid, $page, $action], $template);
    return htmlspecialchars_uni($link);
}




// ── get_forum ─────────────────────────────────────────────────────────────────
function get_forum(int|string $fid, bool $active_override = false): array|false
{
    global $cache;
    static $forum_cache;

    if (!isset($forum_cache) || !is_array($forum_cache)) {
        $forum_cache = $cache->read('forums');
    }

    if (empty($forum_cache[$fid])) {
        return false;
    }

    if (!$active_override) {
        foreach (explode(',', $forum_cache[$fid]['parentlist']) as $parent) {
            if (($forum_cache[(int)$parent]['active'] ?? 1) == 0) {
                return false;
            }
        }
    }

    return $forum_cache[$fid];
}





// ── log_moderator_action ──────────────────────────────────────────────────────
function log_moderator_action(array $data, string $action = ''): void
{
    global $db, $CURUSER, $session;

    $fid  = (int)($data['fid'] ?? 0);  unset($data['fid']);
    $tid  = (int)($data['tid'] ?? 0);  unset($data['tid']);
    $pid  = (int)($data['pid'] ?? 0);  unset($data['pid']);
    $tids = (array)($data['tids'] ?? []); unset($data['tids']);

    $serialized = is_array($data) ? my_serialize($data) : $data;

    $uid       = (int)$CURUSER['id'];
    $dateline  = TIMENOW;
    $ipaddress = $session->packedip;

    $tid_list = $tids ?: [$tid];

    $placeholders = [];
    $params       = [];

    foreach ($tid_list as $t) {
        $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
        array_push($params, $uid, $dateline, $fid, (int)$t, $pid, $action, $serialized, $ipaddress);
    }

    $sql = "INSERT INTO moderatorlog (`uid`,`dateline`,`fid`,`tid`,`pid`,`action`,`data`,`ipaddress`)
            VALUES " . implode(', ', $placeholders);

    $db->sql_query_prepared($sql, $params);
}





function log_admin_action(mixed ...$args): void
{
    global $db, $mybb, $CURUSER;

    $data = count($args) === 1 && is_array($args[0]) ? $args[0] : $args;

    $act    = $mybb->get_input('act',    MyBB::INPUT_STRING);
    $action = $mybb->get_input('action', MyBB::INPUT_STRING);

    $act_map = [
        'management'    => 'forum-management',
        'forums'        => 'forum-management',
        'banning'       => 'config-banning',
        'banning2'      => 'user-banning',
        'liftban'       => 'user-banning',
        'users'         => 'user-users',
        'editprofile'   => 'user-users',
        'deleteuser'    => 'user-users',
        'usergroups'    => 'user-groups',
        'groups'        => 'user-groups',
        'editgroup'     => 'user-groups',
        'settings'      => 'config-settings',
        'editsettings'  => 'config-settings',
        'plugins'       => 'config-plugins',
        'templates'     => 'style-templates',
        'themes'        => 'style-themes',
        'adminlog'      => 'tools-adminlog',
        'modlog'        => 'tools-modlog',
        'backupdb'      => 'tools-backupdb',
        'tasks'         => 'tools-tasks',
        'cache'         => 'tools-cache',
        'announcements' => 'forum-announcements',
        'attachments'   => 'forum-attachments',
    ];

    $db->sql_query_prepared(
        "INSERT INTO adminlog (`uid`,`ipaddress`,`dateline`,`module`,`action`,`data`) VALUES (?,?,?,?,?,?)",
        [
            !empty($CURUSER['id']) ? (int)$CURUSER['id'] : (int)($mybb->user['id'] ?? 0),
            my_inet_pton(get_ip()),
            TIMENOW,
            $act_map[$act] ?? $act,
            $action,
            my_serialize($data),
        ]
    );
}




/**
 * Output navigation tabs
 *
 * $tabs = [
 *     'key' => ['title' => '…', 'link' => '…', 'description' => '…' (опц.), 'icon' => 'fa-solid fa-…' (опц.)],
 * ];
 */
function output_nav_tabs(array $tabs, string $active_tab): void
{
    static $css_printed = false;

    // Иконки по умолчанию для известных вкладок; 'icon' в самой вкладке важнее
    $default_icons = [
        'find_attachments'     => 'fa-solid fa-magnifying-glass',
        'find_orphans'         => 'fa-solid fa-broom',
        'stats'                => 'fa-solid fa-chart-pie',
        'comment_attachments'  => 'fa-solid fa-comment-dots',
        'attachment_types'     => 'fa-solid fa-paperclip',
        'add_attachment_type'  => 'fa-solid fa-circle-plus',
        'edit_attachment_type' => 'fa-solid fa-pen-to-square',
    ];

    $description = trim((string)($tabs[$active_tab]['description'] ?? ''));

    if (!$css_printed) {
        $css_printed = true;
        ?>
<style>
/* Всё под .ag-tabs — раньше правило .overflow-auto менялось для всей страницы */
.ag-tabs { margin-top: 1rem; }
.ag-tabs .ag-tabs-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding: 2px; /* чтобы не обрезалось кольцо фокуса */
}
.ag-tabs .ag-tabs-scroll::-webkit-scrollbar { display: none; }
/* width: max-content + margin auto вместо justify-content: center:
   при центрировании flex-контейнера с прокруткой левые вкладки уходили
   за край и до них нельзя было доскроллить на телефоне */
.ag-tabs .ag-tabs-bar {
    display: flex; gap: .3rem;
    width: max-content; margin: 0 auto;
    padding: .3rem;
    border-radius: 50rem;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color-translucent);
}
.ag-tabs .ag-tab {
    display: inline-flex; align-items: center; gap: .5rem;
    padding: .5rem 1.05rem;
    border-radius: 50rem;
    font-size: .98rem; font-weight: 600;
    color: var(--bs-secondary-color);
    text-decoration: none;
    white-space: nowrap;
    transition: background-color .15s ease, color .15s ease, box-shadow .15s ease;
}
.ag-tabs .ag-tab i { font-size: .95em; opacity: .85; }
.ag-tabs .ag-tab:hover { color: var(--bs-body-color); background: var(--bs-body-bg); }
.ag-tabs .ag-tab:focus-visible { outline: 2px solid rgba(var(--bs-primary-rgb), .6); outline-offset: 1px; }
.ag-tabs .ag-tab.is-active {
    color: #fff;
    background: var(--bs-primary);
    box-shadow: 0 .25rem .75rem rgba(var(--bs-primary-rgb), .3);
}
.ag-tabs .ag-tab.is-active i { opacity: 1; }
.ag-tabs .ag-tabs-desc {
    display: flex; align-items: flex-start; justify-content: center; gap: .5rem;
    margin: .75rem auto 0; max-width: 760px;
    font-size: .93rem; color: var(--bs-secondary-color); text-align: center;
}
.ag-tabs .ag-tabs-desc i { color: var(--bs-primary); margin-top: .2rem; flex-shrink: 0; }
@media (max-width: 575.98px) {
    .ag-tabs .ag-tab { padding: .45rem .85rem; font-size: .92rem; }
}
</style>
<script>
// На узком экране прокручиваем полосу так, чтобы активная вкладка была видна
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ag-tabs .ag-tab.is-active').forEach(function (tab) {
        const box = tab.closest('.ag-tabs-scroll');
        if (box && box.scrollWidth > box.clientWidth) {
            box.scrollLeft = tab.offsetLeft - (box.clientWidth - tab.offsetWidth) / 2;
        }
    });
});
</script>
        <?php
    }
    ?>
    <nav class="container ag-tabs" aria-label="Section navigation">
        <div class="ag-tabs-scroll">
            <div class="ag-tabs-bar" role="tablist">
                <?php foreach ($tabs as $key => $tab):
                    $is_active = ($key === $active_tab);
                    $icon = $tab['icon'] ?? $default_icons[$key] ?? 'fa-solid fa-folder';
                    ?>
                    <a href="<?= htmlspecialchars((string)$tab['link'], ENT_QUOTES) ?>"
                       class="ag-tab<?= $is_active ? ' is-active' : '' ?>"
                       role="tab" aria-selected="<?= $is_active ? 'true' : 'false' ?>"<?= $is_active ? ' aria-current="page"' : '' ?>>
                        <i class="<?= htmlspecialchars((string)$icon, ENT_QUOTES) ?>"></i>
                        <span><?= htmlspecialchars((string)$tab['title']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($description !== ''): ?>
            <div class="ag-tabs-desc">
                <i class="fa-solid fa-circle-info"></i>
                <span><?= htmlspecialchars($description) ?></span>
            </div>
        <?php endif; ?>
    </nav>
    <?php
}





/////////////////////////////////////////////////////////////////////////////////////////////
 
function _file_access_check_(string $name): void
{
    global $CURUSER, $db;

    if (empty($CURUSER['id'])) {
        print_no_permission(true);
        exit;
    }

    $query = $db->sql_query_prepared("SELECT usergroups FROM staffpanel WHERE name = ?", [$name]);

    if (!$query || $db->num_rows($query) === 0) {
        print_no_permission(true);
        exit;
    }

    $result     = $db->fetch_array($query);
    $usergroups = array_map('trim', explode(',', $result['usergroups']));
    $key        = '[' . $CURUSER['usergroup'] . ']';

    if (!in_array($key, $usergroups, true)) {
        print_no_permission(true);
        exit;
    }
}
  




function _selectbox_(
    string $text     = '',
    string $name     = '',
    bool   $any      = true,
    string $anytext  = 'any usergroup (all)',
    mixed  $selected = ''
): string {
    global $db;

    $out = (!empty($text) ? htmlspecialchars($text) . ': ' : '')
         . '<label><select name="' . htmlspecialchars($name) . '" class="form-select form-select-sm border pe-5 w-auto">' . "\n";

    if ($any) {
        $out .= '<option value="-" style="color:gray">' . htmlspecialchars($anytext) . '</option>' . "\n";
    }

    $q = $db->sql_query_prepared('SELECT gid, title FROM usergroups ORDER BY disporder');
    while ($q && ($row = $db->fetch_array($q))) {
        $sel  = (string)$selected === (string)$row['gid'] ? ' selected' : '';
        $out .= '<option value="' . (int)$row['gid'] . '"' . $sel . '>' . htmlspecialchars($row['title']) . '</option>' . "\n";
    }

    $out .= '</select></label>';
    return $out;
}




  if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
  }

  $eol = PHP_EOL;

  //include_once INC_PATH . '/functions_icons.php';

?>