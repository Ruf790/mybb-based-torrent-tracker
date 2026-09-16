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
 */
function output_nav_tabs(array $tabs, string $active_tab): void
{
    $has_description = !empty($tabs[$active_tab]['description'] ?? '');
    ?>
    <div class="container mt-3">
        
        <div class="d-flex justify-content-center overflow-auto hide-scrollbar pb-3 mb-4 border-bottom"
             style="scroll-behavior: smooth;">
            <?php foreach ($tabs as $key => $tab):
                $is_active = ($key === $active_tab);
                $icon = $tab['icon'] ?? match($key) {
                    'find_attachments' => 'fas fa-magnifying-glass',
                    'find_orphans'     => 'fas fa-broom',
                    'stats'            => 'fas fa-chart-pie',
                    default            => 'fas fa-cogs'
                };
                ?>
                <a href="<?= $tab['link'] ?>"
                   class="text-center text-decoration-none mx-2 <?= $is_active ? 'text-primary' : 'text-muted' ?>"
                   style="min-width: 110px;">
                    <div class="card tab-card border-0 shadow-sm h-100 <?= $is_active ? 'border-primary border-2' : 'border-light' ?>"
                         style="width: 350px; transition: all 0.25s ease;">
                        <div class="card-body p-3 d-flex flex-column justify-content-center">
                            <i class="<?= $icon ?> fa-2x mb-2"></i>
                            <div class="small fw-bold text-truncate" style="max-width: 100%;"><?= htmlspecialchars($tab['title']) ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

       
        <?php if ($has_description): ?>
            <div class="alert alert-info d-flex align-items-start gap-3 mx-3 mb-4 rounded-3 border-0 shadow-sm">
                <i class="fas fa-circle-info text-primary mt-1 flex-shrink-0"></i>
                <div class="small"><?= htmlspecialchars($tabs[$active_tab]['description']) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }

    /* Анимация ТОЛЬКО для карточек вкладок */
    .tab-card {
        transition: all 0.25s ease;
    }
    .tab-card:hover {
        transform: translateY(-6px) scale(1.03);
        box-shadow: 0 12px 20px rgba(0,0,0,0.15) !important;
    }
    .tab-card:active {
        transform: translateY(-3px) scale(1.01);
    }

    /* Плавная прокрутка на мобильных */
    @media (max-width: 768px) {
        .overflow-auto {
            -webkit-overflow-scrolling: touch;
        }
    }
</style>
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