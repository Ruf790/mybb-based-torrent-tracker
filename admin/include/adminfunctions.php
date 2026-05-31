<?php

declare(strict_types=1);


// ── join_usergroup ────────────────────────────────────────────────────────────
function join_usergroup(int $uid, int $joingroup): bool
{
    global $db, $mybb, $CURUSER;

    $user = $uid === (int)$CURUSER['id']
        ? $mybb->user
        : $db->fetch_array($db->simple_select('users', 'additionalgroups, usergroup', "id='{$uid}'"));

    $groups = array_filter(array_map('intval', explode(',', $user['additionalgroups'] ?? '')));

    if (in_array($joingroup, $groups, true)) {
        return false;
    }

    $groups[] = $joingroup;
    $groups   = array_values(array_unique(array_diff($groups, [(int)$user['usergroup']])));

    $db->update_query('users', ['additionalgroups' => implode(',', $groups)], "id='{$uid}'");
    return true;
}

// ── save_quick_perms ──────────────────────────────────────────────────────────
function save_quick_perms(int $fid): void
{
    global $db, $inherit, $canview, $canpostthreads, $canpostreplies, $canpostpolls, $cache;

    $permission_fields = [];
    foreach ($db->show_fields_from('forumpermissions') as $field) {
        if (str_contains($field['Field'], 'can') || str_contains($field['Field'], 'mod')) {
            $permission_fields[$field['Field']] = 1;
        }
    }

    $ug_fields = $permission_fields;
    unset($ug_fields['canonlyviewownthreads'], $ug_fields['canonlyreplyownthreads']);

    $field_str = $db->escape_string(implode(',', array_keys($permission_fields)));
    $ug_str    = $db->escape_string(implode(',', array_keys($ug_fields)));

    $q = $db->simple_select('usergroups', 'gid');
    while ($ug = $db->fetch_array($q)) {
        $gid = (int)$ug['gid'];

        $q2   = $db->simple_select('forumpermissions', $field_str, "fid='{$fid}' AND gid='{$gid}'", ['limit' => 1]);
        $perms = $db->fetch_array($q2);

        if (!$perms) {
            $q2   = $db->simple_select('usergroups', $ug_str, "gid='{$gid}'", ['limit' => 1]);
            $perms = $db->fetch_array($q2);
        }

        $db->delete_query('forumpermissions', "fid='{$fid}' AND gid='{$gid}'");

        if (empty($inherit[$gid])) {
            $pview    = !empty($canview[$gid])        ? 1 : 0;
            $pthreads = !empty($canpostthreads[$gid]) ? 1 : 0;
            $preplies = !empty($canpostreplies[$gid]) ? 1 : 0;
            $ppolls   = !empty($canpostpolls[$gid])   ? 1 : 0;

            $insert = [
                'fid'            => $fid,
                'gid'            => $gid,
                'canview'        => $pview,
                'canpostthreads' => $pthreads,
                'canpostreplys'  => $preplies,
                'canpostpolls'   => $ppolls,
            ];

            foreach ($permission_fields as $field => $_) {
                if (!array_key_exists($field, $insert)) {
                    $insert[$db->escape_string($field)] = isset($perms[$field]) ? (int)$perms[$field] : 0;
                }
            }

            $db->insert_query('forumpermissions', $insert);
        }
    }

    $cache->update_forumpermissions();
}

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




// ── mk_path_abs22 ─────────────────────────────────────────────────────────────
function mk_path_abs22(string $path, string $base = TSDIR): string
{
    $iswin = str_starts_with(strtoupper(PHP_OS), 'WIN');
    $char1 = my_substr($path, 0, 1);

    if ($char1 !== '/' && !($iswin && ($char1 === '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path)))) {
        $path = $base . $path;
    }

    return $path;
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

// ── generate_radio_button ─────────────────────────────────────────────────────
function generate_radio_button(string $name, string $value = '', string $label = '', array $options = []): string
{
    $cls   = isset($options['class']) ? ' ' . $options['class'] : '';
    $id    = isset($options['id'])    ? " id=\"{$options['id']}\"" : '';
    $forid = isset($options['id'])    ? " for=\"{$options['id']}\"" : '';
    $chk   = !empty($options['checked']) ? ' checked="checked"' : '';
    $lbl_c = isset($options['class']) ? " class=\"label_{$options['class']}\"" : '';

    return "<label{$forid}{$lbl_c}>"
        . "<input type=\"radio\" name=\"{$name}\" value=\"" . htmlspecialchars_uni($value) . "\""
        . " class=\"form-check-input{$cls}\"{$id}{$chk} />"
        . ($label !== '' ? $label : '')
        . '</label>';
}

// ── generate_numeric_field ────────────────────────────────────────────────────
function generate_numeric_field(string $name, mixed $value = 0, array $options = []): string
{
    $val = is_numeric($value) ? (float)$value : '';
    $cls = isset($options['class']) ? ' ' . $options['class'] : '';
    $id  = isset($options['id'])    ? " id=\"{$options['id']}\"" : '';

    $input = "<input type=\"number\" name=\"{$name}\" value=\"{$val}\""
           . " class=\"form-control{$cls}\"{$id}";

    foreach (['min', 'max', 'step', 'style'] as $attr) {
        if (isset($options[$attr])) $input .= " {$attr}=\"{$options[$attr]}\"";
    }

    return $input . ' />';
}

// ── generate_check_box ────────────────────────────────────────────────────────
function generate_check_box(string $name, string $value = '', string $label = '', array $options = []): string
{
    $cls     = isset($options['class'])   ? ' ' . $options['class'] : '';
    $id      = isset($options['id'])      ? " id=\"{$options['id']}\"" : '';
    $forid   = isset($options['id'])      ? " for=\"{$options['id']}\"" : '';
    $lbl_c   = isset($options['class'])   ? " class=\"label_{$options['class']}\"" : '';
    $chk     = !empty($options['checked']) ? ' checked="checked"' : '';
    $onclick = isset($options['onclick']) ? " onclick=\"{$options['onclick']}\"" : '';

    return "<label{$forid}{$lbl_c}>"
        . "<input type=\"checkbox\" name=\"{$name}\" value=\"" . htmlspecialchars_uni($value) . "\""
        . " class=\"form-check-input{$cls}\"{$id}{$chk}{$onclick} /> "
        . ($label !== '' ? $label : '')
        . '</label>';
}

// ── generate_text_box ─────────────────────────────────────────────────────────
function generate_text_box(string $name, string $value = '', array $options = []): string
{
    $cls = isset($options['class']) ? ' ' . $options['class'] : '';
    $id  = isset($options['id'])    ? " id=\"{$options['id']}\"" : '';

    $input = "<input type=\"text\" name=\"{$name}\" value=\"" . htmlspecialchars_uni($value) . "\""
           . " class=\"form-control{$cls}\"{$id}";

    if (isset($options['style'])) $input .= " style=\"{$options['style']}\"";

    return $input . ' />';
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

// ── generate_hidden_field ─────────────────────────────────────────────────────
function generate_hidden_field(string $name, string $value, array $options = []): string
{
    $id = isset($options['id']) ? " id=\"{$options['id']}\"" : '';
    return "<input type=\"hidden\" name=\"{$name}\" value=\"" . htmlspecialchars_uni($value) . "\"{$id} />";
}

// ── subforums_count ───────────────────────────────────────────────────────────
function subforums_count(array $array = []): int
{
    return array_sum(array_map('count', $array));
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
        $q = $db->simple_select('forums', 'name, fid, pid', '', ['order_by' => 'disporder, pid']);
        while ($forum = $db->fetch_array($q)) {
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

// ── delete_attachments ────────────────────────────────────────────────────────
function delete_attachments(int $pid, int $tid, int $aid = 0): void
{
    global $f_upload_path, $db;

    $q = $db->simple_select('attachments', 'a_name', "a_pid='{$pid}' AND a_tid='{$tid}'");
    while ($row = $db->fetch_array($q)) {
        $file = $f_upload_path . $row['a_name'];
        if (file_exists($file)) {
            unlink($file);
        }
    }

    $where = "a_pid={$pid} AND a_tid={$tid}";
    if ($aid > 0) $where .= " AND a_id={$aid}";
    $db->sql_query("DELETE FROM attachments WHERE {$where}");
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

    $base = [
        'uid'       => (int)$CURUSER['id'],
        'dateline'  => TIMENOW,
        'fid'       => $fid,
        'tid'       => $tid,
        'pid'       => $pid,
        'action'    => $db->escape_string($action),
        'data'      => $db->escape_string($serialized),
        'ipaddress' => $db->escape_binary($session->packedip),
    ];

    if ($tids) {
        $rows = [];
        foreach ($tids as $t) {
            $rows[] = array_merge($base, ['tid' => (int)$t]);
        }
        $db->insert_query_multiple('moderatorlog', $rows);
    } else {
        $db->insert_query('moderatorlog', $base);
    }
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

    $db->insert_query('adminlog', [
        'uid'       => !empty($CURUSER['id']) ? (int)$CURUSER['id'] : (int)($mybb->user['id'] ?? 0),
        'ipaddress' => $db->escape_binary(my_inet_pton(get_ip())),
        'dateline'  => TIMENOW,
        'module'    => $db->escape_string($act_map[$act] ?? $act),
        'action'    => $db->escape_string($action),
        'data'      => $db->escape_string(my_serialize($data)),
    ]);
}









// ── get_attachment_icon ───────────────────────────────────────────────────────
function get_attachment_icon(string $ext): string
{
    global $cache, $attachtypes;

    if (!$attachtypes) {
        $attachtypes = $cache->read('attachtypes');
    }

    $ext  = my_strtolower($ext);
    $name = htmlspecialchars_uni($attachtypes[$ext]['name'] ?? $ext);

    if (!empty($attachtypes[$ext]['icon'])) {
        $icon = trim($attachtypes[$ext]['icon']);

        if (str_starts_with($icon, '<')) {
            if (!str_contains($icon, 'title=')) {
                $pos  = strpos($icon, '>');
                $icon = $pos !== false
                    ? substr($icon, 0, $pos) . " title=\"{$name}\">" . substr($icon, $pos + 1)
                    : $icon;
            }
            if (!str_contains($icon, 'font-size:')) {
                if (str_contains($icon, 'style=')) {
                    $icon = str_replace('style="', 'style="font-size:16px; ', $icon);
                } else {
                    $pos  = strpos($icon, '>');
                    $icon = $pos !== false
                        ? substr($icon, 0, $pos) . ' style="font-size:16px;">' . substr($icon, $pos + 1)
                        : $icon;
                }
            }
            return $icon;
        }
    }

    return "<i class=\"fas fa-file\" title=\"{$name}\" style=\"font-size:16px;color:#ccc;\"></i>";
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


















// ── get_parent_list ───────────────────────────────────────────────────────────
function get_parent_list(int $fid): string
{
    global $forum_cache;
    static $forumarraycache;

    if (!empty($forumarraycache[$fid])) {
        return $forumarraycache[$fid]['parentlist'];
    }

    if (!empty($forum_cache[$fid])) {
        return $forum_cache[$fid]['parentlist'];
    }

    cache_forums();
    return $forum_cache[$fid]['parentlist'] ?? '';
}
  
  

// ── build_parent_list ─────────────────────────────────────────────────────────
function build_parent_list(int $fid, string $column = 'fid', string $joiner = 'OR', string $parentlist = ''): string
{
    if (!$parentlist) {
        $parentlist = get_parent_list($fid);
    }

    $parts = array_map(
        fn($val) => "{$column}='{$val}'",
        explode(',', $parentlist)
    );

    return '(' . implode(" {$joiner} ", $parts) . ')';
}




// ── update_last_post ──────────────────────────────────────────────────────────
function update_last_post(int $tid): bool
{
    global $db;

    $last = $db->fetch_array($db->sql_query("
        SELECT u.id, u.username, p.username AS postusername, p.dateline
        FROM posts p LEFT JOIN users u ON u.id = p.uid
        WHERE p.tid = '{$tid}' AND p.visible = '1'
        ORDER BY p.dateline DESC, p.pid DESC LIMIT 1
    "));

    if (!$last) {
        return false;
    }

    $last['username'] = $last['username'] ?: $last['postusername'];

    if (empty($last['dateline'])) {
        $first = $db->fetch_array($db->sql_query("
            SELECT u.id, u.username, p.username AS postusername, p.dateline
            FROM posts p LEFT JOIN users u ON u.id = p.uid
            WHERE p.tid = '{$tid}'
            ORDER BY p.dateline ASC, p.pid ASC LIMIT 1
        "));
        $last['username'] = $first['username'] ?: $first['postusername'];
        $last['id']       = $first['id'];
        $last['dateline'] = $first['dateline'];
    }

    $db->update_query('threads', [
        'lastpost'     => (int)$last['dateline'],
        'lastposter'   => $db->escape_string($last['username']),
        'lastposteruid'=> (int)$last['id'],
    ], "tid='{$tid}'");

    return true;
}





// ── get_post ──────────────────────────────────────────────────────────────────
function get_post(int $pid): array|false
{
    global $db;
    static $post_cache;

    if (isset($post_cache[$pid])) {
        return $post_cache[$pid];
    }

    $post = $db->fetch_array($db->simple_select('posts', '*', "pid='{$pid}'"));
    $post_cache[$pid] = $post ?: false;

    return $post_cache[$pid];
}







// ── update_thread_counters ────────────────────────────────────────────────────
function update_thread_counters(int $tid, array $changes = []): void
{
    global $db;

    $counters = ['replies', 'unapprovedposts', 'attachmentcount'];
    $query    = $db->simple_select('threads', implode(',', $counters), "tid='{$tid}'");
    $thread   = $db->fetch_array($query);
    $update   = [];

    foreach ($counters as $counter) {
        if (!array_key_exists($counter, $changes)) {
            continue;
        }

        $val = $changes[$counter];

        if (str_starts_with((string)$val, '+-')) {
            $val = substr((string)$val, 1);
        }

        $new = str_starts_with((string)$val, '+') || str_starts_with((string)$val, '-')
            ? $thread[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $db->update_query('threads', $update, "tid='{$tid}'");
    }
}




// ── update_thread_data ────────────────────────────────────────────────────────
function update_thread_data(int $tid): void
{
    global $db;

    $thread = get_thread($tid);

    if ($thread && str_starts_with((string)$thread['closed'], 'moved|')) {
        return;
    }

    $last = $db->fetch_array($db->sql_query("
        SELECT u.id, u.username, p.username AS postusername, p.dateline
        FROM posts p LEFT JOIN users u ON u.id = p.uid
        WHERE p.tid = '{$tid}' AND p.visible = '1'
        ORDER BY p.dateline DESC, p.pid DESC LIMIT 1
    "));

    $first = $db->fetch_array($db->sql_query("
        SELECT u.id, u.username, p.pid, p.username AS postusername, p.dateline
        FROM posts p LEFT JOIN users u ON u.id = p.uid
        WHERE p.tid = '{$tid}'
        ORDER BY p.dateline ASC, p.pid ASC LIMIT 1
    "));

    $first['username'] = $db->escape_string($first['username'] ?: $first['postusername']);
    $last['username']  = $db->escape_string($last['username']  ?: $last['postusername']);

    if (empty($last['dateline'])) {
        $last['username'] = $first['username'];
        $last['id']       = $first['id'];
        $last['dateline'] = $first['dateline'];
    }

    $db->update_query('threads', [
        'firstpost'    => (int)$first['pid'],
        'username'     => $first['username'],
        'uid'          => (int)$first['id'],
        'dateline'     => (int)$first['dateline'],
        'lastpost'     => (int)$last['dateline'],
        'lastposter'   => $last['username'],
        'lastposteruid'=> (int)$last['id'],
    ], "tid='{$tid}'");
}





function forum_permissions(int|string|null $fid = 0, int|string|null $uid = 0, int|string|null $gid = 0): array|bool
{
    global $db, $cache, $groupscache, $forum_cache, $fpermcache, $mybb,
           $cached_forum_permissions_permissions, $cached_forum_permissions, $CURUSER;

    // ----------------------------
    // 🔒 SAFE INIT (CRITICAL FIX)
    // ----------------------------

    $fid = (int)($fid ?? 0);
    $uid = (int)($uid ?? 0);

    if (!is_array($cached_forum_permissions_permissions)) {
        $cached_forum_permissions_permissions = [];
    }

    if (!is_array($cached_forum_permissions)) {
        $cached_forum_permissions = [];
    }

    if (!is_array($CURUSER)) {
        $CURUSER = $mybb->user ?? [];
    }

    // fallback uid
    if ($uid === 0) {
        $uid = (int)($CURUSER['id'] ?? $CURUSER['uid'] ?? 0);
    }

    // ----------------------------
    // 🔒 BUILD GROUP IDS (SAFE)
    // ----------------------------

    $groupperms = [];

    if (empty($gid)) {

        // CASE 1: different user
        if ($uid !== 0 && $uid !== (int)($CURUSER['id'] ?? 0)) {

            $user = get_user($uid);

           

            $gid = trim(
                ($user['usergroup'] ?? '1') .
                ',' .
                ($user['additionalgroups'] ?? '')
            );

            $groupperms = usergroup_permissions($gid);

        } else {

            // CASE 2: current user
            $usergroup = $CURUSER['usergroup'] ?? '1';

            if ($usergroup === '' || $usergroup === null) {
                $usergroup = '1';
            }

            $gid = (string)$usergroup;

            if (!empty($CURUSER['additionalgroups'])) {
                $gid .= ',' . $CURUSER['additionalgroups'];
            }

            $groupperms = (is_array($mybb->usergroup))
                ? $mybb->usergroup
                : usergroup_permissions($gid);
        }

    } else {
        $groupperms = usergroup_permissions($gid);
    }

    // ----------------------------
    // 🔒 FORUM CACHE SAFE LOAD
    // ----------------------------

    if (!is_array($forum_cache)) {
        $forum_cache = cache_forums();
    }


    // ----------------------------
    // 🔒 FORUM PERMISSION CACHE
    // ----------------------------

    if (!is_array($fpermcache)) {
        $fpermcache = $cache->read('forumpermissions');
    }



    // ----------------------------
    // 🔥 RETURN SINGLE FORUM
    // ----------------------------

    if ($fid) {

        if (!isset($cached_forum_permissions_permissions[$gid][$fid])) {

            $cached_forum_permissions_permissions[$gid][$fid] =
                fetch_forum_permissions((int)$fid, $gid, $groupperms);
        }

        return $cached_forum_permissions_permissions[$gid][$fid];
    }

    // ----------------------------
    // 🔥 RETURN ALL FORUMS
    // ----------------------------

    if (empty($cached_forum_permissions[$gid])) {

        foreach ($forum_cache as $forum) {

            if (!isset($forum['fid'])) {
                continue;
            }

            $cached_forum_permissions[$gid][$forum['fid']] =
                fetch_forum_permissions((int)$forum['fid'], $gid, $groupperms);
        }
    }

    return $cached_forum_permissions[$gid] ?? [];
}






// ── fetch_forum_permissions ───────────────────────────────────────────────────
function fetch_forum_permissions(int $fid, string $gid, array $groupperms): array
{
    global $groupscache, $forum_cache, $fpermcache, $mybb;

    $groups                 = array_filter(explode(',', $gid));
    $current_permissions    = [];
    $only_view_own_threads  = 1;
    $only_reply_own_threads = 1;

    if (empty($fpermcache[$fid])) {
        return $groupperms;
    }

    foreach ($groups as $group_id) {
        $group_id = trim($group_id);

        $level_permissions = match(true) {
            !empty($fpermcache[$fid][$group_id])  => $fpermcache[$fid][$group_id],
            !empty($groupscache[$group_id])        => $groupscache[$group_id],
            default                                => null,
        };

        if ($level_permissions === null) {
            continue;
        }

        foreach ($level_permissions as $permission => $access) {
            if (
                empty($current_permissions[$permission]) ||
                $access >= $current_permissions[$permission] ||
                ($access === 'yes' && $current_permissions[$permission] === 'no')
            ) {
                $current_permissions[$permission] = $access;
            }
        }

        if (!empty($level_permissions['canview']) && empty($level_permissions['canonlyviewownthreads'])) {
            $only_view_own_threads = 0;
        }

        if (!empty($level_permissions['canpostreplys']) && empty($level_permissions['canonlyreplyownthreads'])) {
            $only_reply_own_threads = 0;
        }
    }

    if (empty($current_permissions)) {
        $current_permissions = $groupperms;
    }

    $current_permissions['canonlyviewownthreads']  = ($only_view_own_threads  && isset($current_permissions['canonlyviewownthreads']))  ? 1 : 0;
    $current_permissions['canonlyreplyownthreads'] = ($only_reply_own_threads && isset($current_permissions['canonlyreplyownthreads'])) ? 1 : 0;

    return $current_permissions;
}









/////////////////////////////////////////////////////////////////////////////////////////////MyBB Functions



// ── update_user_counters ──────────────────────────────────────────────────────
function update_user_counters(int|string $uid, array $changes = []): void
{
    global $db;

    $uid = (int)$uid;
    
	$counters = ['postnum', 'threadnum'];
    $query    = $db->simple_select('users', implode(',', $counters), "id='{$uid}'");
    $user     = $db->fetch_array($query);

    if (!$user) {
        return;
    }

    $update = [];

    foreach ($counters as $counter) {
        if (!array_key_exists($counter, $changes)) {
            continue;
        }

        $val = $changes[$counter];

        if (str_starts_with((string)$val, '+-')) {
            $val = substr((string)$val, 1);
        }

        $new = str_starts_with((string)$val, '+') || str_starts_with((string)$val, '-')
            ? $user[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $db->update_query('users', $update, "id='{$uid}'");
    }
}






// ── update_forum_counters ─────────────────────────────────────────────────────
function update_forum_counters(int|string $fid, array $changes = []): void
{
    global $db;

    $fid = (int)$fid;
	
	$counters = ['threads', 'unapprovedthreads', 'posts', 'unapprovedposts'];
    $query    = $db->simple_select('forums', implode(',', $counters), "fid='{$fid}'");
    $forum    = $db->fetch_array($query);
    $update   = [];

    foreach ($counters as $counter) {
        if (!array_key_exists($counter, $changes)) {
            continue;
        }

        $val = $changes[$counter];

        if (str_starts_with((string)$val, '+-')) {
            $val = substr((string)$val, 1);
        }

        $new = str_starts_with((string)$val, '+') || str_starts_with((string)$val, '-')
            ? $forum[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $db->update_query('forums', $update, "fid='{$fid}'");
    }

    // Обновляем глобальную статистику
    $stat_map = [
        'threads'           => 'numthreads',
        'unapprovedthreads' => 'numunapprovedthreads',
        'posts'             => 'numposts',
        'unapprovedposts'   => 'numunapprovedposts',
    ];

    $new_stats = [];
    foreach ($stat_map as $counter => $stat) {
        if (!isset($update[$counter])) {
            continue;
        }
        $diff = $update[$counter] - $forum[$counter];
        $new_stats[$stat] = ($diff >= 0 ? '+' : '') . $diff;
    }

    if (!empty($new_stats)) {
        update_stats($new_stats);
    }
}









// ── update_forum_lastpost ─────────────────────────────────────────────────────
function update_forum_lastpost(int $fid): void
{
    global $db;

    $query = $db->sql_query("
        SELECT tid, lastpost, lastposter, lastposteruid, subject
        FROM threads
        WHERE fid = '{$fid}' AND visible = '1' AND closed NOT LIKE 'moved|%'
        ORDER BY lastpost DESC LIMIT 1
    ");

    if ($db->num_rows($query) > 0) {
        $last = $db->fetch_array($query);
        $updated = [
            'lastpost'       => (int)$last['lastpost'],
            'lastposter'     => $db->escape_string($last['lastposter']),
            'lastposteruid'  => (int)$last['lastposteruid'],
            'lastposttid'    => (int)$last['tid'],
            'lastpostsubject'=> $db->escape_string($last['subject']),
        ];
    } else {
        $updated = [
            'lastpost' => 0, 'lastposter' => '', 'lastposteruid' => 0,
            'lastposttid' => 0, 'lastpostsubject' => '',
        ];
    }

    $db->update_query('forums', $updated, "fid='{$fid}'");
}







/////////////////////////////////////////////////////////////////////////////////////////////
 
function _file_access_check_(string $name): void
{
    global $CURUSER, $db;

    $query = $db->simple_select('staffpanel', 'usergroups', "name = '" . $db->escape_string($name) . "'");

    if ($db->num_rows($query) === 0) {
        print_no_permission(true);
        exit;
    }

    $result     = $db->fetch_array($query);
    $usergroups = explode(',', $result['usergroups']);
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

    $q = $db->sql_query('SELECT gid, title FROM usergroups ORDER BY disporder');
    while ($row = $db->fetch_array($q)) {
        $sel  = (string)$selected === (string)$row['gid'] ? ' selected' : '';
        $out .= '<option value="' . (int)$row['gid'] . '"' . $sel . '>' . htmlspecialchars($row['title']) . '</option>' . "\n";
    }

    $out .= '</select></label>';
    return $out;
}

function _get_file_type_(string $file): string
{
    return strtolower(pathinfo($file, PATHINFO_EXTENSION));
}






  if ((!defined ('SETTING_PANEL_TSSEv56') AND !defined ('STAFF_PANEL_TSSEv56')))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

 
  define ('ADMIN_FUNCTIONS_TSSEv56', true);
  define ('AP_VERSION', 'v6.2');
  define ('S_VERSION', 'v7.9');
  define ('T_VERSION', '5.6');
  define ('O_VERSION', '');
  define ('TYPE', 99);
  
  $eol = PHP_EOL;

  

  include_once INC_PATH . '/functions_icons.php';
  if (!function_exists ('file_put_contents'))
  {
    function file_put_contents ($filename, $contents)
    {
      if (is_writable ($filename))
      {
        if ($handle = fopen ($filename, 'w'))
        {
          if (fwrite ($handle, $contents) === FALSE)
          {
            return false;
          }

          fclose ($filename);
          return true;
        }
      }

      return false;
    }
  }

?>
