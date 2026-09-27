<?php

declare(strict_types=1);
/**
 * Forum Management — optimized
 * Originally ~286 KB, refactored to ~110 KB
 * PHP 8.1+
 */


if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}


if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization is not allowed.</div>');
}



// style.php больше не нужен - он определял только Page/Table/Form/
// FormContainer, которые здесь больше нигде не используются.

$lang->load('forum_management');

// ── Breadcrumb (замена DefaultPage) ─────────────────────────
// Раньше mgmt_add_breadcrumb()/mgmt_render_breadcrumb() -
// логика воспроизведена один в один (порядок "first"/"last" через
// &raquo; между элементами, последний элемент - просто активный текст
// без ссылки).
$mgmt_breadcrumb_trail = [];

function mgmt_add_breadcrumb(string $name, string $url = ''): void
{
    global $mgmt_breadcrumb_trail;
    $mgmt_breadcrumb_trail[] = ['name' => $name, 'url' => $url];
}

function mgmt_render_breadcrumb(): string
{
    global $mgmt_breadcrumb_trail;

    if (empty($mgmt_breadcrumb_trail)) {
        return '';
    }

    $trailParts = [];
    $totalItems = count($mgmt_breadcrumb_trail);

    foreach ($mgmt_breadcrumb_trail as $index => $crumb) {
        $isLastItem = ($index === $totalItems - 1);
        $crumbName  = htmlspecialchars((string)($crumb['name'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (!$isLastItem) {
            $crumbUrl     = htmlspecialchars((string)($crumb['url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $trailParts[] = sprintf('<a href="%s">%s</a>', $crumbUrl, $crumbName);
        } else {
            $trailParts[] = sprintf('<span class="active">%s</span>', $crumbName);
        }
    }

    // Подряд идущие одинаковые пункты («Forum Management » Forum Management»)
    // схлопываем: корневой пункт добавлялся и глобально, и внутри действия
    $trailParts = array_values(array_filter($trailParts, static function ($p) {
        static $prev = null;
        $name = strip_tags($p);
        $dup  = $name === $prev;
        $prev = $name;
        return !$dup;
    }));

    return implode(' <i class="fa-solid fa-chevron-right mx-1" style="font-size:.65rem;opacity:.5"></i> ', $trailParts);
}

// ── Форма (замена DefaultForm) ──────────────────────────────
// generate_hidden_field/text_box/numeric_field/check_box/select_box/
// forum_select уже существуют как самостоятельные глобальные функции
// в adminfunctions.php - используются напрямую ниже, без обёртки.

function mgmt_form_open(string $action, string $method = 'post', string $id = ''): void
{
    global $mybb;
    echo '<form action="' . $action . '" method="' . $method . '"' . ($id !== '' ? ' id="' . $id . '"' : '') . '>' . "\n";
    echo generate_hidden_field('my_post_key', $mybb->post_code) . "\n";
}

function mgmt_form_close(): void
{
    echo '</form>';
}

function mgmt_submit_button(string $value, array $options = []): string
{
    $cls = isset($options['class']) ? ' ' . $options['class'] : '';
    return '<input type="submit" value="' . htmlspecialchars_uni($value) . '" class="submit_button' . $cls . '" />';
}

function mgmt_reset_button(string $value, array $options = []): string
{
    $cls = isset($options['class']) ? ' ' . $options['class'] : '';
    return '<input type="reset" value="' . htmlspecialchars_uni($value) . '" class="submit_button' . $cls . '" />';
}

function mgmt_output_submit_wrapper(array $buttons): void
{
    echo '<div class="form_button_wrapper">' . "\n";
    foreach ($buttons as $b) {
        echo $b . " \n";
    }
    echo "</div>\n";
}




// ── generate_hidden_field ─────────────────────────────────────────────────────
/** $value может быть int (get_input INT, ID из БД) — приводим к строке, иначе TypeError под strict_types */
function generate_hidden_field(string $name, int|string $value, array $options = []): string
{
    $id = isset($options['id']) ? ' id="' . htmlspecialchars_uni((string)$options['id']) . '"' : '';
    return '<input type="hidden" name="' . htmlspecialchars_uni($name) . '" value="' . htmlspecialchars_uni((string)$value) . '"' . $id . ' />';
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

    $field_str = implode(',', array_keys($permission_fields));
    $ug_str    = implode(',', array_keys($ug_fields));

    $q = $db->sql_query_prepared("SELECT gid FROM usergroups");
    while ($q && ($ug = $db->fetch_array($q))) {
        $gid = (int)$ug['gid'];

        $q2   = $db->sql_query_prepared("SELECT {$field_str} FROM forumpermissions WHERE fid = ? AND gid = ? LIMIT 1", [$fid, $gid]);
        $perms = $q2 ? $db->fetch_array($q2) : null;

        if (!$perms) {
            $q2   = $db->sql_query_prepared("SELECT {$ug_str} FROM usergroups WHERE gid = ? LIMIT 1", [$gid]);
            $perms = $q2 ? $db->fetch_array($q2) : null;
        }

        $db->sql_query_prepared("DELETE FROM forumpermissions WHERE fid = ? AND gid = ?", [$fid, $gid]);

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
                    $insert[$field] = isset($perms[$field]) ? (int)$perms[$field] : 0;
                }
            }

            $columns      = array_keys($insert);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $db->sql_query_prepared(
                "INSERT INTO forumpermissions (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                array_values($insert)
            );
        }
    }

    $cache->update_forumpermissions();
}


// ── join_usergroup ────────────────────────────────────────────────────────────
function join_usergroup(int $uid, int $joingroup): bool
{
    global $db, $mybb, $CURUSER;

    $user = $uid === (int)$CURUSER['id']
        ? $mybb->user
        : (function() use ($db, $uid) {
            $q = $db->sql_query_prepared("SELECT additionalgroups, usergroup FROM users WHERE id = ?", [$uid]);
            return $q ? $db->fetch_array($q) : null;
        })();

    $groups = array_filter(array_map('intval', explode(',', $user['additionalgroups'] ?? '')));

    if (in_array($joingroup, $groups, true)) {
        return false;
    }

    $groups[] = $joingroup;
    $groups   = array_values(array_unique(array_diff($groups, [(int)$user['usergroup']])));

    $db->sql_query_prepared("UPDATE users SET additionalgroups = ? WHERE id = ?", [implode(',', $groups), $uid]);
    return true;
}


// ── generate_check_box ────────────────────────────────────────────────────────
/** $value может быть int (например 1 для прав) — приводим к строке, иначе TypeError под strict_types */
function generate_check_box(string $name, int|string $value = '', string $label = '', array $options = []): string
{
    $e = static fn($v): string => htmlspecialchars_uni((string)$v);

    $cls     = isset($options['class'])   ? ' ' . $e($options['class']) : '';
    $id      = isset($options['id'])      ? ' id="' . $e($options['id']) . '"' : '';
    $forid   = isset($options['id'])      ? ' for="' . $e($options['id']) . '"' : '';
    $lbl_c   = isset($options['class'])   ? ' class="label_' . $e($options['class']) . '"' : '';
    $chk     = !empty($options['checked']) ? ' checked="checked"' : '';
    $onclick = isset($options['onclick']) ? ' onclick="' . $e($options['onclick']) . '"' : '';

    return "<label{$forid}{$lbl_c}>"
        . '<input type="checkbox" name="' . $e($name) . '" value="' . $e($value) . '"'
        . " class=\"form-check-input{$cls}\"{$id}{$chk}{$onclick} /> "
        . $label   // $label — готовый HTML, как и раньше
        . '</label>';
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


// ── get_post ──────────────────────────────────────────────────────────────────
function get_post(int $pid): array|false
{
    global $db;
    static $post_cache;

    if (isset($post_cache[$pid])) {
        return $post_cache[$pid];
    }

    $query = $db->sql_query_prepared("SELECT * FROM posts WHERE pid = ?", [$pid]);
    $post  = $query ? $db->fetch_array($query) : null;
    $post_cache[$pid] = $post ?: false;

    return $post_cache[$pid];
}


// ── update_thread_counters ────────────────────────────────────────────────────
function update_thread_counters(int $tid, array $changes = []): void
{
    global $db;

    $counters = ['replies', 'unapprovedposts', 'attachmentcount'];
    $query    = $db->sql_query_prepared(
        "SELECT " . implode(',', $counters) . " FROM threads WHERE tid = ?",
        [$tid]
    );
    $thread = $query ? $db->fetch_array($query) : null;
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
            ? $thread[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
        $params   = array_values($update);
        $params[] = $tid;

        $db->sql_query_prepared("UPDATE threads SET {$set} WHERE tid = ?", $params);
    }
}


// ── update_user_counters ──────────────────────────────────────────────────────
function update_user_counters(int|string $uid, array $changes = []): void
{
    global $db;

    $uid = (int)$uid;
    
	$counters = ['postnum', 'threadnum'];
    $query    = $db->sql_query_prepared(
        "SELECT " . implode(',', $counters) . " FROM users WHERE id = ?",
        [$uid]
    );
    $user = $query ? $db->fetch_array($query) : null;

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
        // Имена колонок берутся только из фиксированного списка $counters выше — не из ввода
        $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
        $params   = array_values($update);
        $params[] = $uid;

        $db->sql_query_prepared("UPDATE users SET {$set} WHERE id = ?", $params);
    }
}


// ── update_forum_counters ─────────────────────────────────────────────────────
function update_forum_counters(int|string $fid, array $changes = []): void
{
    global $db;

    $fid = (int)$fid;
	
	$counters = ['threads', 'unapprovedthreads', 'posts', 'unapprovedposts'];
    $query    = $db->sql_query_prepared(
        "SELECT " . implode(',', $counters) . " FROM forums WHERE fid = ?",
        [$fid]
    );
    $forum  = $query ? $db->fetch_array($query) : null;
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
            ? $forum[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
        $params   = array_values($update);
        $params[] = $fid;

        $db->sql_query_prepared("UPDATE forums SET {$set} WHERE fid = ?", $params);
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

    $query = $db->sql_query_prepared("
        SELECT tid, lastpost, lastposter, lastposteruid, subject
        FROM threads
        WHERE fid = ? AND visible = '1' AND closed NOT LIKE 'moved|%'
        ORDER BY lastpost DESC LIMIT 1
    ", [$fid]);

    if ($query && $db->num_rows($query) > 0) {
        $last = $db->fetch_array($query);
        $updated = [
            'lastpost'        => (int)$last['lastpost'],
            'lastposter'      => $last['lastposter'],
            'lastposteruid'   => (int)$last['lastposteruid'],
            'lastposttid'     => (int)$last['tid'],
            'lastpostsubject' => $last['subject'],
        ];
    } else {
        $updated = [
            'lastpost' => 0, 'lastposter' => '', 'lastposteruid' => 0,
            'lastposttid' => 0, 'lastpostsubject' => '',
        ];
    }

    $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($updated)));
    $params = array_values($updated);
    $params[] = $fid;

    $db->sql_query_prepared("UPDATE forums SET {$set} WHERE fid = ?", $params);
}








mgmt_add_breadcrumb('Forum Management', 'index.php?act=management');

// ═══════════════════════════════════════════════════════════
// SHARED HELPERS
// ═══════════════════════════════════════════════════════════

/** Общие CSS + JS assets (вызывается один раз в начале страницы) */
function fm_head_assets(): void
{
    echo '<link rel="stylesheet" href="templates/forum_management.css">',
         '<link rel="stylesheet" href="templates/forum_management2.css?ver=1">',
         '<link rel="stylesheet" href="templates/main.css?ver=1813">',
         '<link rel="stylesheet" href="templates/modal.css?ver=1813">',
         '<script src="scripts/admincp.js?ver=1821"></script>',
         '<script src="scripts/tabs.js"></script>',
         '<script src="scripts/popup.js"></script>',
         '<script src="scripts/quick_perm_editor.js?ver=2"></script>',
         '<script src="scripts/forum_management.js?ver=4"></script>';
}

/** Иконка группы: в usergroups.image здесь хранится HTML (<i …>) или путь к картинке */
function fm_group_icon(array $ug): string
{
    $img = trim((string)($ug['image'] ?? ''));
    if ($img === '') {
        return '<span class="fm2-gicon"><i class="fa-solid fa-users"></i></span>';
    }
    if (str_starts_with($img, '<')) {
        return '<span class="fm2-gicon">' . $img . '</span>';
    }
    return '<span class="fm2-gicon"><img src="' . htmlspecialchars_uni(str_replace('{lang}', 'english', $img)) . '" alt=""></span>';
}

/** Зоны Allowed / Denied для QuickPermEditor (id/классы прежние — их использует JS) */
function fm_perm_zones(int $gid, string $enabled, string $disabled): string
{
    return '<div class="fm2-zones">'
         . '<div><div class="fm2-zone-label is-on"><i class="fa-solid fa-circle-check"></i>Allowed</div>'
         . '<div class="enabled-permissions" id="enabled-' . $gid . '">' . $enabled . '</div></div>'
         . '<div><div class="fm2-zone-label is-off"><i class="fa-solid fa-circle-xmark"></i>Denied</div>'
         . '<div class="disabled-permissions" id="disabled-' . $gid . '">' . $disabled . '</div></div>'
         . '</div>';
}

function fm_perm_card_head(string $title, string $sub): string
{
    return '<div class="fm2-hdr"><span class="fm2-hdr-icon ic-amber"><i class="fas fa-shield-halved"></i></span>'
         . '<div style="min-width:0"><h1>' . $title . '</h1><p>' . $sub . '</p></div>'
         . '<div class="ms-auto d-flex flex-wrap gap-2 fm2-legend">'
         . '<span class="fm2-tag t-on"><i class="fa-solid fa-hand-pointer"></i>Drag a permission to move it</span>'
         . '</div></div>';
}

/** Заголовок карточки страницы (мягкий стиль вместо сплошной цветной полосы) */
function fm_card_header(string $title, string $subtitle, string $icon, string $color = 'primary'): void
{
    $cls = match ($color) {
        'info'    => 'ic-info',
        'success' => 'ic-green',
        'warning' => 'ic-amber',
        'danger'  => 'ic-red',
        default   => 'ic-blue',
    };
    echo <<<HTML
    <div class="card-header fm2-hdr fm2">
        <span class="fm2-hdr-icon {$cls}"><i class="fas fa-{$icon}"></i></span>
        <div style="min-width:0"><h1>{$title}</h1><p>{$subtitle}</p></div>
    </div>
    HTML;
}

/** Пронумерованный шаг формы */
function fm_step(int $num, string $color, string $icon, string $title): void
{
    echo <<<HTML
    <div class="d-flex align-items-center mb-4">
        <div class="step-number bg-{$color} text-white rounded-circle me-3" style="width:40px;height:40px;">
            <span class="fw-bold">{$num}</span>
        </div>
        <h5 class="mb-0 fw-bold"><i class="fas fa-{$icon} me-2 text-{$color}"></i>{$title}</h5>
    </div>
    HTML;
}

/** Секция с иконкой-кружком */

function fm_section_header(string $icon, string $color, string $title, string $desc = ''): void
{
    $descHtml = $desc ? "<p class=\"text-muted mb-0\" style=\"font-size:13px\">{$desc}</p>" : '';
    
    echo '<div class="d-flex align-items-center mb-4">
        <div class="icon-circle bg-' . $color . ' bg-opacity-10 text-' . $color . ' p-3 me-3" style="width:50px;height:50px;">
            <i class="fas fa-' . $icon . '"></i>
        </div>
        <div>
            <h5 class="mb-1 fw-bold text-dark">' . $title . '</h5>
            ' . $descHtml . '
        </div>
    </div>';
}

/** Ошибки формы — через showToast() из scripts/toast.js (подгружается, если его нет).
 *  Сама логика — в scripts/forum_management.js, здесь только данные. */
function fm_errors(array $errors): void
{
    global $BASEURL;

    $errors = array_values(array_filter(array_map('strval', $errors), 'strlen'));
    if (!$errors) return;

    // showToast() вставляет текст через innerHTML — передаём уже экранированный HTML
    $messages = json_encode(
        array_map(static fn(string $e): string => htmlspecialchars_uni(trim(strip_tags(html_entity_decode($e, ENT_QUOTES, 'UTF-8')))), $errors),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    $toast_js = rtrim((string)$BASEURL, '/') . '/scripts/toast.js';

    echo '<span hidden class="fm2-errors" data-messages="' . htmlspecialchars((string)$messages, ENT_QUOTES, 'UTF-8')
       . '" data-toast-src="' . htmlspecialchars($toast_js, ENT_QUOTES, 'UTF-8') . '"></span>';
}

/** Блок переключателя "Additional Options" */
function fm_toggle_advanced_open(): void
{
    echo <<<HTML
    <div id="additional_options_link" class="text-center py-5">
        <button onclick="return toggleAdditionalOptions();" class="toggle-options-btn">
            <i class="fas fa-cogs fa-lg"></i>
            <span>Show Additional Forum Options</span>
            <i class="fas fa-chevron-down"></i>
        </button>
        <p class="mt-3 text-muted" style="font-size:13px">Advanced settings for forum configuration</p>
    </div>
    <div id="additional_options" style="display:none">
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-sliders-h fa-lg me-2"></i>Additional Forum Options</span>
        <button onclick="return toggleAdditionalOptions();" class="toggle-options-btn" style="font-size:14px;padding:10px 20px">
            <i class="fas fa-times"></i> Hide Options <i class="fas fa-chevron-up"></i>
        </button>
    </div><div class="card-body">
    HTML;
}

function fm_toggle_advanced_close(): void
{
    echo '</div></div></div>';
}


/** Строки выбора типа форума (Forum / Category) */
function fm_type_cards(string $current): void
{
    $f_active  = $current === 'f' ? 'active' : '';
    $c_active  = $current !== 'f' ? 'active' : '';
    $f_checked = $current === 'f' ? 'checked' : '';
    $c_checked = $current !== 'f' ? 'checked' : '';
    $f_icon    = $current === 'f' ? 'fas fa-check-circle' : 'far fa-circle';
    $c_icon    = $current !== 'f' ? 'fas fa-check-circle' : 'far fa-circle';
    echo <<<HTML
    <div class="row g-4">
        <div class="col-md-6">
            <div class="type-card {$f_active}" onclick="selectType('forum')" data-type="forum">
                <input type="radio" name="type" value="f" class="d-none" id="type_forum" {$f_checked}>
                <div class="type-card-body text-center">
                    <div class="type-icon"><i class="fas fa-comments fa-2x"></i></div>
                    <h6 class="fw-bold mt-3 mb-2">Standard Forum</h6>
                    <p class="text-muted small mb-0">A regular forum where users can post threads and replies</p>
                </div>
                <div class="type-check"><i class="{$f_icon}"></i></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="type-card {$c_active}" onclick="selectType('category')" data-type="category">
                <input type="radio" name="type" value="c" class="d-none" id="type_category" {$c_checked}>
                <div class="type-card-body text-center">
                    <div class="type-icon"><i class="fas fa-folder fa-2x"></i></div>
                    <h6 class="fw-bold mt-3 mb-2">Category</h6>
                    <p class="text-muted small mb-0">A container for organizing multiple forums together</p>
                </div>
                <div class="type-check"><i class="{$c_icon}"></i></div>
            </div>
        </div>
    </div>
    HTML;
}


/** Поля базовой информации (title, disporder, description) */
function fm_basic_fields(array $data): void
{
    $title   = htmlspecialchars_uni($data['title'] ?? '');
    $order   = (int)($data['disporder'] ?? 1);
    $desc    = htmlspecialchars_uni($data['description'] ?? '');
    $dcLen   = mb_strlen($data['description'] ?? '');
    echo <<<HTML
    <div class="row g-4">
        <div class="col-lg-6">
            <label class="form-label fw-bold">
                <i class="fas fa-heading me-2 text-primary"></i>Forum Title
                <span class="required-badge ms-2">Required</span>
            </label>
            <input type="text" name="title" class="form-control form-control-lg"
                   value="{$title}" placeholder="e.g. General Discussion" required>
        </div>
        <div class="col-lg-6">
            <label class="form-label fw-bold">
                <i class="fas fa-sort-numeric-up me-2 text-primary"></i>Display Order
            </label>
            <input type="number" name="disporder" class="form-control form-control-lg"
                   value="{$order}" min="0">
        </div>
        <div class="col-12">
            <label class="form-label fw-bold">
                <i class="fas fa-align-left me-2 text-primary"></i>Description
            </label>
            <textarea name="description" id="description" class="form-control form-control-lg"
                      rows="4" style="resize:vertical">{$desc}</textarea>
            <div class="d-flex justify-content-end mt-1">
                <small class="text-muted"><span id="charCount">{$dcLen}</span>/500 characters</small>
            </div>
        </div>
    </div>
    HTML;
}

/** Поля дополнительных опций (linkto, password, active, open, datecut, sortby, sortorder, counts) */
function fm_extra_fields(array $d): void
{
    $linkto  = htmlspecialchars_uni($d['linkto']  ?? '');
    $pass    = htmlspecialchars_uni($d['password'] ?? '');
    $active  = !empty($d['active'])         ? 'checked' : '';
    $open    = !empty($d['open'])           ? 'checked' : '';
    $posts   = !empty($d['usepostcounts'])  ? 'checked' : '';
    $threads = !empty($d['usethreadcounts'])? 'checked' : '';

    $datecuts = [0=>'Board Default',1=>'Last 24h',5=>'Last 5 days',10=>'Last 10 days',
                 20=>'Last 20 days',50=>'Last 50 days',75=>'Last 75 days',
                 100=>'Last 100 days',365=>'Last year',9999=>'All time'];
    $sortbys  = [''=> 'Board Default','subject'=>'Subject','lastpost'=>'Last post',
                 'starter'=>'Starter','started'=>'Thread time','rating'=>'Rating',
                 'replies'=>'Replies','views'=>'Views'];
    $sortords = [''=> 'Board Default','asc'=>'Ascending ↑','desc'=>'Descending ↓'];

    $sel = fn($arr, $cur) => implode('', array_map(
        fn($v, $l) => '<option value="' . $v . '" ' . ($cur == $v ? 'selected' : '') . '>' . $l . '</option>',
        array_keys($arr), $arr
    ));

    echo <<<HTML
    <div class="form-row">
        <label class="form-label"><i class="fas fa-external-link-alt me-2"></i>Forum Link (Redirect)</label>
        <p class="text-muted small">Leave empty for a normal forum. Entering a URL disables posting.</p>
        <input type="text" name="linkto" class="form-control" value="{$linkto}" placeholder="https://example.com" style="max-width:450px">
    </div>
    <div class="form-row">
        <label class="form-label"><i class="fas fa-lock me-2"></i>Password Protection</label>
        <p class="text-muted small">Optional. Users still need group permissions on top of the password.</p>
        <input type="text" name="password" class="form-control" value="{$pass}" placeholder="Leave empty for no password" style="max-width:450px">
    </div>
    <div class="form-row">
        <label class="form-label"><i class="fas fa-shield-alt me-2"></i>Access Control</label>
        <div class="settings-grid">
            <label class="form-check settings-group mb-0">
                <input type="checkbox" name="active" value="1" class="form-check-input" {$active}>
                <span class="form-check-label fw-semibold">
                    <i class="fas fa-toggle-on me-1 text-success"></i>Forum is Active
                    <small class="d-block text-muted fw-normal">Hidden from users when unchecked</small>
                </span>
            </label>
            <label class="form-check settings-group mb-0">
                <input type="checkbox" name="open" value="1" class="form-check-input" {$open}>
                <span class="form-check-label fw-semibold">
                    <i class="fas fa-door-open me-1 text-info"></i>Forum is Open
                    <small class="d-block text-muted fw-normal">No posting when unchecked, regardless of permissions</small>
                </span>
            </label>
        </div>
    </div>
    <div class="form-row">
        <label class="form-label"><i class="fas fa-eye me-2"></i>Default View Options</label>
        <div class="settings-grid">
            <div class="settings-group">
                <div class="settings-group-title"><i class="fas fa-calendar-alt"></i>Date Range</div>
                <select name="defaultdatecut" class="form-select">{$sel($datecuts, $d['defaultdatecut'] ?? 0)}</select>
            </div>
            <div class="settings-group">
                <div class="settings-group-title"><i class="fas fa-sort-amount-down"></i>Sort By</div>
                <select name="defaultsortby" class="form-select">{$sel($sortbys, $d['defaultsortby'] ?? '')}</select>
            </div>
            <div class="settings-group">
                <div class="settings-group-title"><i class="fas fa-sort-alpha-down"></i>Sort Order</div>
                <select name="defaultsortorder" class="form-select">{$sel($sortords, $d['defaultsortorder'] ?? '')}</select>
            </div>
        </div>
    </div>
    <div class="form-row">
        <label class="form-label"><i class="fas fa-chart-bar me-2"></i>Statistics Counting</label>
        <div class="settings-grid">
            <label class="form-check settings-group mb-0">
                <input type="checkbox" name="usepostcounts" value="1" class="form-check-input" {$posts}>
                <span class="form-check-label fw-semibold">
                    <i class="fas fa-comment-alt me-1 text-primary"></i>Count user posts
                    <small class="d-block text-muted fw-normal">Posts here count toward user totals</small>
                </span>
            </label>
            <label class="form-check settings-group mb-0">
                <input type="checkbox" name="usethreadcounts" value="1" class="form-check-input" {$threads}>
                <span class="form-check-label fw-semibold">
                    <i class="fas fa-file-alt me-1 text-primary"></i>Count user threads
                    <small class="d-block text-muted fw-normal">Threads here count toward user totals</small>
                </span>
            </label>
        </div>
    </div>
    HTML;
}

/** Кнопки submit / cancel */
function fm_submit_row(string $cancel_url, string $submit_label = 'Save Changes', bool $show_advanced = true): void
{
    $adv = $show_advanced
        ? '<button type="button" class="btn btn-outline-secondary px-4" onclick="toggleAdditionalOptions()"><i class="fas fa-cogs me-2"></i>Advanced</button>'
        : '';
    echo <<<HTML
    <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top">
        <a href="{$cancel_url}" class="btn btn-outline-secondary px-4">
            <i class="fas fa-arrow-left me-2"></i>Cancel
        </a>
        <div class="d-flex gap-2">
            {$adv}
            <button type="submit" class="btn btn-primary px-5">
                <i class="fas fa-save me-2"></i>{$submit_label}
            </button>
        </div>
    </div>
    HTML;
}

/** Модалка подтверждения очистки разрешений */
function fm_clear_permission_modal(): void
{
    echo <<<'HTML'
    <div class="modal fade" id="clearPermissionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-trash-can fa-2x text-danger mb-3"></i>
                    <h5 class="fw-bold mb-2">Clear Custom Permissions</h5>
                    <p class="text-muted mb-4">
                        Clear permissions for <span class="fw-semibold text-primary" id="modalGroupName"></span>?
                        <small class="d-block mt-1">This cannot be undone.</small>
                    </p>
                    <div class="d-flex gap-3 justify-content-center">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" id="confirmClearBtn">Clear</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    HTML;
}

/**
 * Какие права действуют для группы в форуме: свои (forumpermissions) → кэш форума → права группы.
 * @return array{0: array, 1: bool} [$perms, $default_checked]
 */
function fm_resolve_group_perms(array $usergroup, int $fid, array $existing_permissions, array $cached_forum_perms): array
{
    $gid = (int)$usergroup['gid'];
    if (!empty($existing_permissions[$gid])) {
        return [$existing_permissions[$gid], false];
    }
    if (!empty($cached_forum_perms[$fid][$gid])) {
        return [$cached_forum_perms[$fid][$gid], true];
    }
    return [$usergroup, true];
}

/**
 * Строка таблицы прав (вкладка Permissions). Одна разметка и для страницы,
 * и для AJAX-ответа после сохранения в модалке — строка заменяется целиком.
 */
function fm_perm_row(array $usergroup, int $fid, array $perms, bool $default_checked): string
{
    global $mybb;

    $field_list = ['canview' => 'View', 'canpostthreads' => 'Post Threads', 'canpostreplys' => 'Post Replies', 'canpostpolls' => 'Post Polls'];
    $gid    = (int)$usergroup['gid'];
    $utitle = htmlspecialchars_uni((string)$usergroup['title']);

    $perms_checked = [];
    foreach ($field_list as $fp => $_) {
        $perms_checked[$fp] = ($perms[$fp] ?? 0) == 1 ? 1 : 0;
    }

    // Пустые зоны без текста «No permissions / No restrictions» — он мешал перетаскиванию
    $enabled_html = $disabled_html = '';
    foreach ($field_list as $perm => $label) {
        $on = $perms_checked[$perm];
        $badge = '<span class="badge ' . ($on ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger')
               . ' me-1 mb-1 permission-badge" data-perm="' . $perm . '">' . $label . '</span>';
        if ($on) $enabled_html .= $badge; else $disabled_html .= $badge;
    }

    $fields_val = implode(',', array_keys(array_filter($perms_checked)));
    $source = $default_checked
        ? '<span class="fm2-tag t-sub"><i class="fa-solid fa-arrow-turn-down"></i>Inherited</span>'
        : '<span class="fm2-tag t-cat"><i class="fa-solid fa-sliders"></i>Custom</span>';

    $actions = '<a href="index.php?act=management&amp;action=permissions&amp;gid=' . $gid . '&amp;fid=' . $fid . '" class="fm2-act" title="Advanced permissions" onclick="popupWindow(this.href + \'&ajax=1\', null, true);return false;"><i class="fas fa-sliders"></i></a>';
    if (!$default_checked) {
        $actions .= '<a href="javascript:void(0);" class="fm2-act text-danger clear-permission-btn" title="Reset to inherited"'
                  . ' data-pid="' . (int)$perms['pid'] . '" data-fid="' . $fid . '" data-gid="' . $gid . '"'
                  . ' data-group-name="' . $utitle . '" data-post-key="' . $mybb->post_code . '"><i class="fas fa-rotate-left"></i></a>';
    }

    return '
                            <tr data-group-id="' . $gid . '">
                                <td><div class="d-flex align-items-center gap-3">' . fm_group_icon($usergroup)
        . '<div><div class="fw-bold">' . $utitle . '</div><div class="fm2-gid">GID ' . $gid . '</div></div></div></td>
                                <td>
                                    <div class="permission-fields" id="permission-fields-' . $gid . '">
                                        ' . fm_perm_zones($gid, $enabled_html, $disabled_html) . '
                                        <input type="hidden" name="fields_' . $gid . '" id="fields_' . $gid . '" value="' . $fields_val . '">
                                        <input type="hidden" name="fields_inherit_' . $gid . '" id="fields_inherit_' . $gid . '" value="' . (int)$default_checked . '">
                                        <input type="hidden" name="fields_default_' . $gid . '" id="fields_default_' . $gid . '" value="' . $fields_val . '">
                                    </div>
                                </td>
                                <td class="text-center">' . $source . '</td>
                                <td class="text-end text-nowrap">' . $actions . '</td>
                            </tr>';
}

/** JSON-ответ для AJAX-запросов и выход */
function fm_json_response(array $data, int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Модалка подтверждения удаления модератора */
function fm_delete_mod_modal(): void
{
    echo <<<'HTML'
    <div class="modal fade" id="deleteModeratorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <i class="fas fa-user-slash fa-2x text-danger mb-3"></i>
                    <h5 class="mb-3">Remove this moderator?</h5>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger" id="confirmDeleteModeratorBtn">Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    HTML;
}









mgmt_add_breadcrumb('Forum Management', "index.php?act=management");

$action = $mybb->get_input('action');

if($action == "add" || $action == "edit" || $action == "copy" || $action == "permissions" || !$action)
{
	if(!empty($mybb->input['fid']) && ($action == "management" || $action == "edit" || $action == "copy" || !$action))
	{
		$nav_fid = $mybb->get_input('fid', MyBB::INPUT_INT);

		$sub_tabs['view_forum'] = array(
			'title' =>'View Forum',
			'link' => "index.php?act=management&fid=".$nav_fid,
			'description' => 'Here you can view sub forums, quickly edit permissions and add moderators to your forum'
		);

		$sub_tabs['add_child_forum'] = array(
			'title' => 'Add Child Forum',
			'link' => "index.php?act=management&action=add&pid=".$nav_fid,
			'description' => 'Here you can view sub forums, quickly edit permissions and add moderators to your forum'
		);

		$sub_tabs['edit_forum_settings'] = array(
			'title' => 'Edit Forum Settings',
			'link' => "index.php?act=management&action=edit&fid=".$nav_fid,
			'description' => 'Here you can edit an existing forums settings and its permissions'
		);

		$sub_tabs['copy_forum'] = array(
			'title' => 'Copy Forum',
			'link' => "index.php?act=management&action=copy&fid=".$nav_fid,
			'description' => 'Here you can copy forum settings or permissions from an existing forum to another or to a new forum'
		);
	}
	else
	{
		$sub_tabs['forum_management'] = array(
			'title' => 'Forum Management',
			'link' => "index.php?act=management",
			'description' => 'This section allows you to manage the categories and forums on your board. You can manage forum permissions and forum-specific moderators as well. If you change the display order for one or more forums or categories, make sure you submit the form at the bottom of the page'
		);

		$sub_tabs['add_forum'] = array(
			'title' => 'Add New Forum',
			'link' => "index.php?act=management&action=add",
			'description' => 'Here you can add a new forum or category to your board. You may also set initial permissions for this forum'
		);
	}
}

$plugins->run_hooks("admin_forum_management_begin");




// ═══════════════════════════════════════════════════════════
// ACTION: COPY
// ═══════════════════════════════════════════════════════════
if ($action === 'copy') {
    $plugins->run_hooks('admin_forum_management_copy');

    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $errors = [];
        $from = $mybb->get_input('from', MyBB::INPUT_INT);
        $to   = $mybb->get_input('to',   MyBB::INPUT_INT);

        $query      = $db->sql_query_prepared("SELECT * FROM forums WHERE fid = ?", [$from]);
        $from_forum = $query ? $db->fetch_array($query) : null;
        if (!$query || !$db->num_rows($query)) $errors[] = 'error_invalid_source_forum';

        if ($to === -1) {
            if (empty($mybb->input['title']))                               $errors[] = 'You need to give your new forum a name';
            if ($mybb->input['pid'] == -1 && $mybb->input['type'] === 'f') $errors[] = 'You must select a parent forum';

            if (!$errors) {
                $pid = max(0, $mybb->get_input('pid', MyBB::INPUT_INT));
                $new_forum = array_diff_key($from_forum, array_flip([
                    'fid','threads','posts','lastpost','lastposter','lastposteruid',
                    'lastposttid','lastpostsubject','unapprovedthreads','unapprovedposts'
                ]));
                $new_forum['name']        = $mybb->input['title'];
                $new_forum['description'] = $mybb->input['description'];
                $new_forum['type']        = $mybb->input['type'];
                $new_forum['pid']         = $pid;
                $new_forum['parentlist']  = '';

                $columns      = array_keys($new_forum);
                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $db->sql_query_prepared(
                    "INSERT INTO forums (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                    array_values($new_forum)
                );
                $to = $db->insert_id();
                $db->sql_query_prepared("UPDATE forums SET parentlist = ? WHERE fid = ?", [make_parent_list($to), $to]);
            }
        } elseif ($mybb->input['copyforumsettings'] == 1) {
            $query    = $db->sql_query_prepared("SELECT * FROM forums WHERE fid = ?", [$to]);
            $to_forum = $query ? $db->fetch_array($query) : null;
            if (!$query || !$db->num_rows($query)) $errors[] = 'Invalid destination forum';

            if (!$errors) {
                $new_forum = array_diff_key($from_forum, array_flip([
                    'fid','threads','posts','lastpost','lastposter','lastposteruid',
                    'lastposttid','lastpostsubject','unapprovedthreads','unapprovedposts'
                ]));
                $new_forum['name']        = $to_forum['name'];
                $new_forum['description'] = $to_forum['description'];
                $new_forum['pid']         = $to_forum['pid'];
                $new_forum['parentlist']  = $to_forum['parentlist'];

                $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($new_forum)));
                $params = array_values($new_forum);
                $params[] = $to;
                $db->sql_query_prepared("UPDATE forums SET {$set} WHERE fid = ?", $params);
            }
        } else {
            $new_forum['name'] = null;
        }

        if (!$errors) {
            if (!empty($mybb->input['copygroups']) && is_array($mybb->input['copygroups'])) {
                $group_ids = array_map('intval', $mybb->input['copygroups']);
                $ph = implode(',', array_fill(0, count($group_ids), '?'));
                $query  = $db->sql_query_prepared("SELECT * FROM forumpermissions WHERE fid = ? AND gid IN ({$ph})", [$from, ...$group_ids]);
                $db->sql_query_prepared("DELETE FROM forumpermissions WHERE fid = ? AND gid IN ({$ph})", [$to, ...$group_ids]);
                while ($query && ($p = $db->fetch_array($query))) {
                    unset($p['pid']); $p['fid'] = $to;
                    $columns      = array_keys($p);
                    $placeholders = implode(',', array_fill(0, count($columns), '?'));
                    $db->sql_query_prepared(
                        "INSERT INTO forumpermissions (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                        array_values($p)
                    );
                }
                log_admin_action($from, $from_forum['name'], $to, $new_forum['name'], implode(',', $group_ids));
            } else {
                log_admin_action($from, $from_forum['name'], $to, $new_forum['name']);
            }

            $plugins->run_hooks('admin_forum_management_copy_commit');
            $cache->update_forums();
            $cache->update_forumpermissions();

            flash_message($lang->forum_management['success_forum_copied'], 'success');
            admin_redirect("index.php?act=management&action=edit&fid={$to}");
        }
    }

    // ── Sub-tabs ──
    if (!empty($mybb->input['fid'])) {
        $nav_fid = $mybb->get_input('fid', MyBB::INPUT_INT);
        $sub_tabs = [
            'view_forum'         => ['title'=>'View Forum',         'link'=>"index.php?act=management&fid={$nav_fid}",                    'description'=>''],
            'add_child_forum'    => ['title'=>'Add Child Forum',    'link'=>"index.php?act=management&action=add&pid={$nav_fid}",          'description'=>''],
            'edit_forum_settings'=> ['title'=>'Edit Forum Settings','link'=>"index.php?act=management&action=edit&fid={$nav_fid}",         'description'=>''],
            'copy_forum'         => ['title'=>'Copy Forum',         'link'=>"index.php?act=management&action=copy&fid={$nav_fid}",         'description'=>''],
        ];
    }

    // ── Defaults ──
    $copy_data = [
        'type' => 'f', 'title' => '', 'description' => '',
        'pid'  => max(0, $mybb->get_input('pid', MyBB::INPUT_INT)),
        'disporder' => 1, 'from' => $mybb->get_input('fid'),
        'to' => -1, 'copyforumsettings' => 0, 'copygroups' => [],
    ];
    if ($errors) {
        foreach ($copy_data as $k => $_) {
            if (isset($mybb->input[$k])) $copy_data[$k] = $mybb->input[$k];
        }
    }

    $usergroupsZZ = [];
    $q = $db->sql_query_prepared("SELECT gid, title FROM usergroups WHERE gid != '1' ORDER BY title");
    while ($q && ($ug = $db->fetch_array($q))) {
        $usergroupsZZ[$ug['gid']] = htmlspecialchars_uni($ug['title']);
    }

    stdhead('Copy Forum');
    fm_head_assets();
    output_nav_tabs($sub_tabs ?? [], 'copy_forum');
    ?>

    <div class="admin-container">
    <div class="container mt-3">
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <?php fm_card_header('Copy Forum Settings', 'Duplicate forum settings and permissions to another forum', 'clone', 'info'); ?>

            <div class="card-body px-5 py-4">
                <?php fm_errors($errors ?? []); ?>

                <div class="row">
                <div class="col-lg-8">
                <form method="post" action="index.php?act=management&action=copy" id="copyForumForm">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">

                    <?php fm_step(1, 'info', 'exchange-alt', 'Select Forums'); ?>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-info bg-opacity-10 py-3">
                                    <h6 class="mb-0 fw-bold"><i class="fas fa-download me-2 text-info"></i>Copy FROM <span class="text-danger">*</span></h6>
                                </div>
                                <div class="card-body">
                                    <?= generate_forum_select('from', $copy_data['from'], ['id'=>'from','class'=>'form-select']) ?>
                                    <small class="text-muted">Forum to copy settings from</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-success bg-opacity-10 py-3">
                                    <h6 class="mb-0 fw-bold"><i class="fas fa-upload me-2 text-success"></i>Copy TO <span class="text-danger">*</span></h6>
                                </div>
                                <div class="card-body">
                                    <?= generate_forum_select('to', $copy_data['to'], ['id'=>'to','class'=>'form-select','main_option'=>'Create New Forum']) ?>
                                    <small class="text-muted">Forum to copy settings to</small>

                                    <!-- New forum fields -->
                                    <div id="newForumSettings" style="display:none" class="mt-3">
                                        <?php fm_type_cards($copy_data['type']); ?>
                                        <div class="mt-3">
                                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars_uni($copy_data['title']) ?>">
                                        </div>
                                        <div class="mt-3">
                                            <label class="form-label fw-semibold">Description</label>
                                            <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars_uni($copy_data['description']) ?></textarea>
                                        </div>
                                        <div class="mt-3" id="parentForumField">
                                            <label class="form-label fw-semibold">Parent Forum <span class="text-danger">*</span></label>
                                            <?= generate_forum_select('pid', $copy_data['pid'], ['id'=>'pid','class'=>'form-select','main_option'=>'None']) ?>
                                        </div>
                                    </div>

                                    <!-- Copy settings toggle (existing forum) -->
                                    <div id="copySettings" style="display:none" class="mt-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="copyforumsettings" id="copyforumsettings" value="1" <?= $copy_data['copyforumsettings'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="copyforumsettings">Copy Forum Settings</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php fm_step(2, 'warning', 'users', 'Copy Permissions'); ?>
                    <div class="card mb-5">
                        <div class="card-header bg-warning bg-opacity-10 py-3">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-shield-alt me-2 text-warning"></i>User Group Permissions</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <label class="form-label fw-semibold">Select User Groups</label>
                                    <?= generate_select_box('copygroups[]', $usergroupsZZ, $copy_data['copygroups'], ['id'=>'copygroups','multiple'=>true,'size'=>8,'class'=>'form-select']) ?>
                                    <small class="text-muted">Hold CTRL for multiple</small>
                                </div>
                                <div class="col-lg-6">
                                    <div class="bg-light rounded p-3 h-100">
                                        <h6 class="fw-bold mb-3">Selected Groups</h6>
                                        <div id="selectedGroupsList"><p class="text-muted small mb-2">No groups selected</p></div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllGroups">Select All</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="deselectAllGroups">Deselect All</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php fm_submit_row('index.php?act=management', 'Copy Forum Settings', false); ?>
                </form>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="sticky-top" style="top:20px">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-info text-white py-3">
                                <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Tips</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info small mb-2"><strong>New Forum:</strong> Select "Create New Forum" as destination</div>
                                <div class="alert alert-warning small mb-2"><strong>Existing Forum:</strong> Enable "Copy Forum Settings" to overwrite</div>
                                <div class="alert alert-success small mb-0"><strong>Permissions:</strong> Hold CTRL to multi-select groups</div>
                            </div>
                        </div>
                    </div>
                </div>
                </div><!-- /row -->
            </div>
        </div>
    </div>
    </div>

    <?php
    stdfoot();
	exit;
}


if($action == "editmod")
{
	$query = $db->sql_query_prepared("SELECT * FROM moderators WHERE mid = ?", [$mybb->get_input('mid', MyBB::INPUT_INT)]);
	$mod_data = $query ? $db->fetch_array($query) : null;

	if(!$mod_data['id'])
	{
		flash_message($lang->forum_management['error_incorrect_moderator'], 'error');
		admin_redirect("index.php?act=management");
	}

	$plugins->run_hooks("admin_forum_management_editmod");

	if($mod_data['isgroup'])
	{
		$fieldname = "title";
	}
	else
	{
		$fieldname = "username";
	}

	if($mybb->request_method == "post")
	{
		verify_post_check($mybb->get_input('my_post_key'));

		$mid = $mybb->get_input('mid', MyBB::INPUT_INT);
		if(!$mid)
		{
			flash_message($lang->forum_management['error_incorrect_moderator'], 'error');
			admin_redirect("index.php?act=management");
		}

		$errors = [];
		if(!$errors)
		{
			$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
			$forum = get_forum($fid, true);
			if($mod_data['isgroup'])
			{
				$mod = $groupscache[$mod_data['id']];
			}
			else
			{
				$mod = get_user($mod_data['id']);
			}
			$update_array = array(
				'fid' => (int)$fid,
				'caneditposts' => $mybb->get_input('caneditposts', MyBB::INPUT_INT),
				'cansoftdeleteposts' => $mybb->get_input('cansoftdeleteposts', MyBB::INPUT_INT),
				'canrestoreposts' => $mybb->get_input('canrestoreposts', MyBB::INPUT_INT),
				'candeleteposts' => $mybb->get_input('candeleteposts', MyBB::INPUT_INT),
				'cansoftdeletethreads' => $mybb->get_input('cansoftdeletethreads', MyBB::INPUT_INT),
				'canrestorethreads' => $mybb->get_input('canrestorethreads', MyBB::INPUT_INT),
				'candeletethreads' => $mybb->get_input('candeletethreads', MyBB::INPUT_INT),
				'canviewips' => $mybb->get_input('canviewips', MyBB::INPUT_INT),
				'canviewunapprove' => $mybb->get_input('canviewunapprove', MyBB::INPUT_INT),
				'canviewdeleted' => $mybb->get_input('canviewdeleted', MyBB::INPUT_INT),
				'canopenclosethreads' => $mybb->get_input('canopenclosethreads', MyBB::INPUT_INT),
				'canstickunstickthreads' => $mybb->get_input('canstickunstickthreads', MyBB::INPUT_INT),
				'canapproveunapprovethreads' => $mybb->get_input('canapproveunapprovethreads', MyBB::INPUT_INT),
				'canapproveunapproveposts' => $mybb->get_input('canapproveunapproveposts', MyBB::INPUT_INT),
				'canapproveunapproveattachs' => $mybb->get_input('canapproveunapproveattachs', MyBB::INPUT_INT),
				'canmanagethreads' => $mybb->get_input('canmanagethreads', MyBB::INPUT_INT),
				'canmanagepolls' => $mybb->get_input('canmanagepolls', MyBB::INPUT_INT),
				'canpostclosedthreads' => $mybb->get_input('canpostclosedthreads', MyBB::INPUT_INT),
				'canmovetononmodforum' => $mybb->get_input('canmovetononmodforum', MyBB::INPUT_INT),
				'canusecustomtools' => $mybb->get_input('canusecustomtools', MyBB::INPUT_INT),
				'canmanageannouncements' => $mybb->get_input('canmanageannouncements', MyBB::INPUT_INT),
				'canmanagereportedposts' => $mybb->get_input('canmanagereportedposts', MyBB::INPUT_INT),
				'canviewmodlog' => $mybb->get_input('canviewmodlog', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_forum_management_editmod_commit");

			$mid_input = $mybb->get_input('mid', MyBB::INPUT_INT);
			$set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update_array)));
			$params = array_values($update_array);
			$params[] = $mid_input;
			$db->sql_query_prepared("UPDATE moderators SET {$set} WHERE mid = ?", $params);

			$cache->update_moderators();

			// Log admin action
			log_admin_action($fid, $forum['name'], $mid, $mod[$fieldname]);

			flash_message($lang->forum_management['success_moderator_updated'], 'success');
			admin_redirect("index.php?act=management&fid=".$mybb->get_input('fid', MyBB::INPUT_INT)."#tab_moderators");
		}
	}

	if($mod_data['isgroup'])
	{
		$query = $db->sql_query_prepared("SELECT title FROM usergroups WHERE gid = ?", [$mod_data['id']]);
		$mod_data[$fieldname] = $query ? $db->fetch_field($query, 'title') : null;
	}
	else
	{
		$query = $db->sql_query_prepared("SELECT username FROM users WHERE id = ?", [$mod_data['id']]);
		$mod_data[$fieldname] = $query ? $db->fetch_field($query, 'username') : null;
	}

	$sub_tabs = array();

	$sub_tabs['edit_mod'] = array(
		'title' => $lang->forum_management['edit_mod'],
		'link' => "index.php?act=management&action=editmod&mid=".$mybb->get_input('mid', MyBB::INPUT_INT),
		'description' => $lang->forum_management['edit_mod_desc']
	);

	mgmt_add_breadcrumb('forum_moderators', "index.php?act=management&amp;fid={$mod_data['fid']}#tab_moderators");
	mgmt_add_breadcrumb('edit_forum');
	
	

	
	stdhead('Edit Moderator');
	
	fm_head_assets();
	
	
	

	
echo '<div class="container mt-3 admin-container fm2">';
	
output_nav_tabs($sub_tabs, 'edit_mod');
fm_errors($errors ?? []);

mgmt_form_open("index.php?act=management&action=editmod", "post", "editModForm");
echo generate_hidden_field("mid", (string)$mod_data['mid']);

if($errors)
{
    // output_inline_error() убран — ошибки показывает fm_errors() выше, после вкладок
    $mod_data = $mybb->input;
}

// ── Оформление как в groups.php: строки-переключатели с иконкой ──────────
$fm_sw = static function (string $name, string $label, string $icon, bool $danger = false) use ($mod_data): string {
    $checked = !empty($mod_data[$name]) ? ' checked' : '';
    return '<label class="fm2-sw' . ($danger ? ' is-danger' : '') . '" for="' . $name . '">'
         . '<span class="fm2-sw-icon"><i class="fas ' . $icon . '"></i></span>'
         . '<span class="fm2-sw-text">' . htmlspecialchars_uni($label) . '</span>'
         . '<input type="checkbox" class="form-check-input" role="switch" name="' . $name . '" id="' . $name . '" value="1"' . $checked . '>'
         . '</label>';
};
$L = $lang->forum_management;
$fm_section = static function (string $icon, string $cls, string $title, array $items, string $desc = '') use ($fm_sw): string {
    $html = '<div class="fm2-msec"><div class="fm2-msec-head">'
          . '<span class="fm2-msec-icon ' . $cls . '"><i class="fas ' . $icon . '"></i></span><span class="fw-bold">' . $title . '</span>'
          . '<span class="ms-auto d-flex gap-1">'
          . '<button type="button" class="fm2-mini" data-sw-all="1" title="Enable all"><i class="fa-solid fa-check-double"></i></button>'
          . '<button type="button" class="fm2-mini" data-sw-all="0" title="Disable all"><i class="fa-solid fa-xmark"></i></button>'
          . '</span></div>'
          . ($desc !== '' ? '<div class="fm2-muted mb-2">' . $desc . '</div>' : '')
          . '<div class="fm2-sw-grid">';
    foreach ($items as $it) {
        $html .= $fm_sw($it[0], $it[1], $it[2], $it[3] ?? false);
    }
    return $html . '</div></div>';
};

$mod_title = htmlspecialchars_uni($mod_data[$fieldname] ?? '');

echo '<div class="card fm2-perm">';
echo '<div class="fm2-hdr">'
   . '<span class="fm2-hdr-icon ic-blue"><i class="fas fa-user-pen"></i></span>'
   . '<div style="min-width:0"><h1>' . sprintf($L['edit_mod_for'], $mod_title) . '</h1>'
   . '<p>What this moderator can do in the selected forum</p></div>'
   . '<div class="ms-auto fm2-legend"><span class="fm2-tag t-on" id="fm2SwCount"><i class="fa-solid fa-toggle-on"></i>0 enabled</span></div>'
   . '</div>';

echo '<div class="p-3 p-md-4">';

// Форум
echo '<div class="fm2-msec"><div class="fm2-msec-head"><span class="fm2-msec-icon ic-amber"><i class="fas fa-comments"></i></span><span class="fw-bold">' . $L['forum'] . '</span></div>';
if ($L['forum_desc'] !== '') echo '<div class="fm2-muted mb-2">' . $L['forum_desc'] . '</div>';
echo '<div style="max-width:420px">' . generate_forum_select('fid', $mod_data['fid'], ['id' => 'fid', 'class' => 'form-select']) . '</div></div>';

echo '<div class="row g-3">';
echo '<div class="col-lg-6">';
echo $fm_section('fa-file-lines', 'ic-blue', 'Posts', [
    ['caneditposts',       $L['can_edit_posts'],        'fa-pen'],
    ['cansoftdeleteposts', $L['can_soft_delete_posts'], 'fa-trash-can'],
    ['canrestoreposts',    $L['can_restore_posts'],     'fa-rotate-left'],
    ['candeleteposts',     $L['can_delete_posts'],      'fa-trash', true],
    ['canpostclosedthreads', $L['can_post_closed_threads'], 'fa-comment'],
]);
echo $fm_section('fa-list', 'ic-green', 'Threads', [
    ['cansoftdeletethreads',   $L['can_soft_delete_threads'],   'fa-trash-can'],
    ['canrestorethreads',      $L['can_restore_threads'],       'fa-rotate-left'],
    ['candeletethreads',       $L['can_delete_threads'],        'fa-trash', true],
    ['canopenclosethreads',    $L['can_open_close_threads'],    'fa-lock-open'],
    ['canstickunstickthreads', $L['can_stick_unstick_threads'], 'fa-thumbtack'],
]);
echo '</div><div class="col-lg-6">';
echo $fm_section('fa-eye', 'ic-teal', 'Visibility & approval', [
    ['canviewunapprove',           $L['can_view_unapprove'],                'fa-eye-slash'],
    ['canviewdeleted',             $L['can_view_deleted'],                  'fa-eye'],
    ['canapproveunapprovethreads', $L['can_approve_unapprove_threads'],     'fa-circle-check'],
    ['canapproveunapproveposts',   $L['can_approve_unapprove_posts'],       'fa-circle-check'],
    ['canapproveunapproveattachs', $L['can_approve_unapprove_attachments'], 'fa-paperclip'],
    ['canviewips',                 $L['can_view_ips'],                      'fa-network-wired', true],
]);
echo $fm_section('fa-screwdriver-wrench', 'ic-purple', 'Management', [
    ['canmanagethreads',     $L['can_manage_threads'],       'fa-code-merge'],
    ['canmanagepolls',       $L['can_manage_polls'],         'fa-chart-simple'],
    ['canmovetononmodforum', $L['can_move_to_other_forums'], 'fa-right-left', true],
    ['canusecustomtools',    $L['can_use_custom_tools'],     'fa-toolbox'],
]);
echo '</div></div>';

echo $fm_section('fa-gauge-high', 'ic-red', $L['moderator_cp_permissions'], [
    ['canmanageannouncements', $L['can_manage_announcements'],  'fa-bullhorn'],
    ['canmanagereportedposts', $L['can_manage_reported_posts'], 'fa-flag'],
    ['canviewmodlog',          $L['can_view_mod_log'],          'fa-clock-rotate-left'],
], $L['moderator_cp_permissions_desc']);

echo '</div>'; // p-3

echo '<div class="fm2-savebar">'
   . '<span class="fm2-muted"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Red switches grant powerful rights</span>'
   . '<div class="d-flex gap-2">'
   . '<a href="index.php?act=management&amp;fid=' . (int)$mod_data['fid'] . '#tab_moderators" class="btn btn-outline-secondary rounded-pill px-3"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>'
   . '<button type="reset" class="btn btn-outline-secondary rounded-pill px-3"><i class="fa-solid fa-rotate-left me-1"></i>' . htmlspecialchars_uni($lang->reset ?? 'Reset') . '</button>'
   . '<button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-floppy-disk me-1"></i>' . htmlspecialchars_uni($L['save_mod']) . '</button>'
   . '</div></div>';

echo '</div>'; // .card

// Стили переключателей — templates/forum_management2.css, счётчик — scripts/forum_management.js

mgmt_form_close();



echo '</div>'; // .container mt-3
	
	

	stdfoot();
	exit;
}

if($action == "clear_permission")
{
	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);

	// User clicked no
	if(!empty($mybb->input['no']))
	{
		admin_redirect("index.php?act=management&fid={$fid}");
	}

	$plugins->run_hooks("admin_forum_management_clear_permission");

	if($mybb->request_method == "post")
	{
		verify_post_check($mybb->get_input('my_post_key'));

		if((!$fid || !$gid) && $pid)
		{
			$query = $db->sql_query_prepared("SELECT fid, gid FROM forumpermissions WHERE pid = ?", [$pid]);
			$result = $query ? $db->fetch_array($query) : null;
			$fid = $result['fid'];
			$gid = $result['gid'];
		}

		if($pid)
		{
			$db->sql_query_prepared("DELETE FROM forumpermissions WHERE pid = ?", [$pid]);
		}
		else
		{
			$db->sql_query_prepared("DELETE FROM forumpermissions WHERE gid = ? AND fid = ?", [$gid, $fid]);
		}

		$plugins->run_hooks('admin_forum_management_clear_permission_commit');

		$cache->update_forumpermissions();

		flash_message($lang->forum_management['success_custom_permission_cleared'], 'success');
		admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");
	}
}












// ============================================================
// ACTION: PERMISSIONS
// ============================================================
if ($action === 'permissions') {
    $plugins->run_hooks('admin_forum_management_permissions');

    // ── POST ─────────────────────────────────────────────────
    if ($mybb->request_method === 'post') {
        $is_ajax_post = (int)($mybb->input['ajax'] ?? 0) === 1;
        if ($is_ajax_post) {
            if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
                fm_json_response(['ok' => false, 'error' => 'Invalid security token. Reload the page and try again.'], 403);
            }
        } else {
            verify_post_check($mybb->get_input('my_post_key'));
        }

        $pid   = $mybb->get_input('pid', MyBB::INPUT_INT);
        $fid   = $mybb->get_input('fid', MyBB::INPUT_INT);
        $gid   = $mybb->get_input('gid', MyBB::INPUT_INT);
        $forum = get_forum($fid, 1);

        if ((!$fid || !$gid) && $pid) {
            $query  = $db->sql_query_prepared("SELECT fid, gid FROM forumpermissions WHERE pid = ?", [$pid]);
            $result = $query ? $db->fetch_array($query) : null;
            $fid    = (int)$result['fid'];
            $gid    = (int)$result['gid'];
            $forum  = get_forum($fid, 1);
        }

        $update_array = [];
        $fields_array = $db->show_fields_from('forumpermissions');
        $input_perms  = $mybb->input['permissions'] ?? null;

        foreach ($fields_array as $field) {
            $fname = $field['Field'];
            if (!str_contains($fname, 'can') && !str_contains($fname, 'mod')) {
                continue;
            }
            $update_array[$fname] = $input_perms !== null
                ? (int)($input_perms[$fname] ?? 0)
                : 0;
        }

        if ($fid && !$pid) {
            $update_array['fid'] = $fid;
            $update_array['gid'] = $gid;
            $columns      = array_keys($update_array);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $db->sql_query_prepared(
                "INSERT INTO forumpermissions (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                array_values($update_array)
            );
        }

        $plugins->run_hooks('admin_forum_management_permissions_commit');

        if (!($fid && !$pid)) {
            $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update_array)));
            $params = array_values($update_array);
            $params[] = $pid;
            $db->sql_query_prepared("UPDATE forumpermissions SET {$set} WHERE pid = ?", $params);
        }

        $cache->update_forumpermissions();
        log_admin_action($fid, $forum['name'] ?? '');

        if ($is_ajax_post) {
            // popup.js заменяет tr[data-group-id=gid] на html и вызывает QuickPermEditor.init(gid)
            fm_json_response([
                'ok'   => true,
                'gid'  => $gid,
                'fid'  => $fid,
                'html' => retrieve_single_permissions_row($gid, $fid),
            ]);
        }

        flash_message($lang->forum_management['success_forum_permissions_saved'], 'success');
        admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");
    }

    $is_ajax = (int)($mybb->input['ajax'] ?? 0) === 1;

    // ── Non-AJAX: page setup ──────────────────────────────────
    if (!$is_ajax) {
        $sub_tabs  = [];
        $fid_in    = $mybb->get_input('fid', MyBB::INPUT_INT);
        $gid_in    = $mybb->get_input('gid', MyBB::INPUT_INT);

        if ($fid_in && $gid_in) {
            $sub_tabs['edit_permissions'] = [
                'title'       => $lang->forum_management['forum_permissions2'],
                'link'        => "index.php?act=management&action=permissions&fid={$fid_in}&amp;gid={$gid_in}",
                'description' => $lang->forum_management['forum_permissions_desc'],
            ];
            mgmt_add_breadcrumb(
                $lang->forum_management['forum_permissions2'],
                "index.php?act=management&fid={$fid_in}#tab_permissions"
            );
        } else {
            $pid_in = $mybb->get_input('pid', MyBB::INPUT_INT);
            $query  = $db->sql_query_prepared("SELECT fid FROM forumpermissions WHERE pid = ?", [$pid_in]);
            $mybb->input['fid'] = $query ? $db->fetch_field($query, 'fid') : null;

            $sub_tabs['edit_permissions'] = [
                'title'       => $lang->forum_management['forum_permissions'],
                'link'        => "index.php?act=management&action=permissions&pid={$pid_in}",
                'description' => $lang->forum_management['forum_permissions_desc'],
            ];
            mgmt_add_breadcrumb(
                $lang->forum_management['forum_permissions2'],
                "index.php?act=management&fid={$mybb->input['fid']}#tab_permissions"
            );
        }

        mgmt_add_breadcrumb($lang->forum_management['forum_permissions']);

        stdhead('Forum Permissions');
        fm_head_assets();

        output_nav_tabs($sub_tabs, 'edit_permissions');

    }
    // AJAX mode: разметка вставляется через insertAdjacentHTML, <script> в ней не выполняются.
    // Вкладки и сохранение #modal_form обрабатывает scripts/popup.js на родительской странице.

    // ── Modal with permission form ────────────────────────────
    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
    $gid = $mybb->get_input('gid', MyBB::INPUT_INT);
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);

    if (!empty($pid) || (!empty($gid) && !empty($fid))) {
        echo '
<div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-shield-alt me-2"></i>Forum Permissions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-4">
                    <div style="overflow-y:auto;max-height:400px">';

        mgmt_form_open(
            "index.php?act=management&action=permissions" . ($is_ajax ? '&ajax=1' : '') . "&pid={$pid}&gid={$gid}&fid={$fid}",
            'post',
            'modal_form'
        );

        echo generate_hidden_field('usecustom', '1');

        if (!empty($errors)) {
            fm_errors($errors);
            $permission_data = $mybb->input;
            $ugq = $db->sql_query_prepared("SELECT * FROM usergroups WHERE gid = ?", [$permission_data['gid']]);
            $usergroup = $ugq ? $db->fetch_array($ugq) : null;
            $fq = $db->sql_query_prepared("SELECT * FROM forums WHERE fid = ?", [$permission_data['fid']]);
            $forum = $fq ? $db->fetch_array($fq) : null;
        } else {
            $query = $pid
                ? $db->sql_query_prepared("SELECT * FROM forumpermissions WHERE pid = ?", [$pid])
                : $db->sql_query_prepared("SELECT * FROM forumpermissions WHERE fid = ? AND gid = ? LIMIT 1", [$fid, $gid]);

            $permission_data = $query ? $db->fetch_array($query) : null;

            if (is_array($permission_data)) {
                $fid = $fid ?: (int)$permission_data['fid'];
                $gid = $gid ?: (int)$permission_data['gid'];
                $pid = $pid ?: (int)$permission_data['pid'];
            }

            $ugq2 = $db->sql_query_prepared("SELECT * FROM usergroups WHERE gid = ?", [$gid]);
            $usergroup   = $ugq2 ? $db->fetch_array($ugq2) : null;
            $fq2 = $db->sql_query_prepared("SELECT * FROM forums WHERE fid = ?", [$fid]);
            $forum       = $fq2 ? $db->fetch_array($fq2) : null;
            $cpq = $db->sql_query_prepared(
                "SELECT * FROM forumpermissions WHERE " . build_parent_list($fid) . " AND gid = ?",
                [$gid]
            );
            $customperms = $cpq ? $db->fetch_array($cpq) : null;

            if (!empty($permission_data['pid'])) {
                $permission_data['usecustom'] = 1;
                echo generate_hidden_field('pid', $pid);
            } else {
                echo generate_hidden_field('fid', $fid);
                echo generate_hidden_field('gid', $gid);
                $permission_data = empty($customperms['pid'])
                    ? usergroup_permissions($gid)
                    : forum_permissions($fid, 0, $gid);
            }
        }

        // Permission group map
        $groups = [
            'canviewthreads'         => 'viewing',
            'canview'                => 'viewing',
            'canonlyviewownthreads'  => 'viewing',
            'candlattachments'       => 'viewing',
            'canpostthreads'         => 'posting_rating',
            'canpostreplys'          => 'posting_rating',
            'canonlyreplyownthreads' => 'posting_rating',
            'canpostattachments'     => 'posting_rating',
            'caneditposts'           => 'editing',
            'candeleteposts'         => 'editing',
            'candeletethreads'       => 'editing',
            'caneditattachments'     => 'editing',
            'modposts'               => 'moderate',
            'modthreads'             => 'moderate',
            'modattachments'         => 'moderate',
            'mod_edit_posts'         => 'moderate',
            'canpostpolls'           => 'polls',
            'canvotepolls'           => 'polls',
            'cansearch'              => 'misc',
        ];

        $hidefields = ($usergroup['gid'] == 222)
            ? ['canonlyviewownthreads','canonlyreplyownthreads','caneditposts',
               'candeleteposts','candeletethreads','caneditattachments']
            : [];

        $groups = $plugins->run_hooks('admin_forum_management_permission_groups', $groups);
        foreach ($hidefields as $hf) { unset($groups[$hf]); }

        $tab_colors  = ['viewing'=>'bg-primary','posting_rating'=>'bg-success','editing'=>'bg-info','moderate'=>'bg-warning','polls'=>'bg-purple','misc'=>'bg-secondary'];
        $tab_icons   = ['viewing'=>'fa-eye','posting_rating'=>'fa-comment','editing'=>'fa-edit','moderate'=>'fa-gavel','polls'=>'fa-chart-bar','misc'=>'fa-cog'];
        $tab_titles  = ['viewing'=>'Viewing','posting_rating'=>'Posting & Rating','editing'=>'Editing','moderate'=>'Moderation','polls'=>'Polls','misc'=>'Misc'];

        $l = [
            'viewing_field_canview'                       => 'Can view forum?',
            'viewing_field_canviewthreads'                => 'Can view threads within forum?',
            'viewing_field_canonlyviewownthreads'         => 'Can only view own threads?',
            'viewing_field_candlattachments'              => 'Can download attachments?',
            'posting_rating_field_canpostthreads'         => 'Can post threads?',
            'posting_rating_field_canpostreplys'          => 'Can post replies?',
            'posting_rating_field_canonlyreplyownthreads' => 'Can only reply to own threads?',
            'posting_rating_field_canpostattachments'     => 'Can post attachments?',
            'editing_field_caneditposts'                  => 'Can edit own posts?',
            'editing_field_candeleteposts'                => 'Can delete own posts?',
            'editing_field_candeletethreads'              => 'Can delete own threads?',
            'editing_field_caneditattachments'            => 'Can update own attachments?',
            'moderate_field_modposts'                     => 'Moderate new posts?',
            'moderate_field_modthreads'                   => 'Moderate new threads?',
            'moderate_field_modattachments'               => 'Moderate new attachments?',
            'moderate_field_mod_edit_posts'               => "Moderate posts after they've been edited?",
            'polls_field_canpostpolls'                    => 'Can post polls?',
            'polls_field_canvotepolls'                    => 'Can vote in polls?',
            'misc_field_cansearch'                        => 'Can search forum?',
        ];

        // Tabs nav
        echo '<div class="container-fluid px-0">
                <ul class="nav nav-tabs nav-justified mb-4" id="permissionTabs" role="tablist">';

        $first = true;
        foreach (array_unique(array_values($groups)) as $group) {
            $active = $first ? ' active' : '';
            $sel    = $first ? 'true' : 'false';
            echo '<li class="nav-item" role="presentation">
                    <button class="nav-link' . $active . '" id="' . $group . '-tab"
                            data-bs-toggle="tab" data-bs-target="#tab_' . $group . '"
                            type="button" role="tab" aria-selected="' . $sel . '">
                        <i class="fas ' . $tab_icons[$group] . ' me-1"></i>' . $tab_titles[$group] . '
                    </button>
                  </li>';
            $first = false;
        }
        echo '</ul><div class="tab-content">';

        // Tab content
        $first = true;
        foreach (array_unique(array_values($groups)) as $group) {
            $show = $first ? ' show active' : '';
            echo '<div class="tab-pane fade' . $show . '" id="tab_' . $group . '" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header ' . $tab_colors[$group] . ' text-white py-3">
                            <h6 class="mb-0">
                                <i class="fas fa-user me-2"></i>"'
                                . htmlspecialchars_uni($usergroup['title'])
                                . '" Custom Permissions for "'
                                . htmlspecialchars_uni($forum['name']) . '"
                            </h6>
                        </div>
                        <div class="card-body"><div class="row">';

            foreach ($db->show_fields_from('forumpermissions') as $field) {
                $fname = $field['Field'];
                if (in_array($fname, $hidefields, true)) { continue; }
                if (!str_starts_with($fname, 'can') && !str_starts_with($fname, 'mod')) { continue; }
                if (!isset($groups[$fname]) || $groups[$fname] !== $group) { continue; }

                $label_key = $group . '_field_' . $fname;
                $checkbox  = generate_check_box(
                    "permissions[{$fname}]", 1, '',
                    ['checked' => !empty($permission_data[$fname]), 'id' => $fname, 'class' => 'form-check-input']
                );

                echo '<div class="col-md-6">
                        <div class="form-check form-switch mb-3">
                            ' . $checkbox . '
                            <label class="form-check-label" for="' . htmlspecialchars_uni($fname) . '">'
                            . ($l[$label_key] ?? $fname) . '
                            </label>
                        </div>
                      </div>';
            }

            echo '</div></div></div></div>';
            $first = false;
        }

        echo '</div></div>';

        echo '<div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="savePermissions">
                    <i class="fas fa-save me-2"></i>Save Permissions
                </button>
              </div>';

        mgmt_form_close();

        echo '</div></div></div></div></div>';
    }

    if (!$is_ajax) {
        stdfoot();
    }
    // AJAX: только разметка модалки — дальше ничего не выводим
    exit;
}










// ═══════════════════════════════════════════════════════════
// ACTION: ADD
// ═══════════════════════════════════════════════════════════
if ($action === 'add') {
    $plugins->run_hooks('admin_forum_management_add');

    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $errors = [];
        if (!trim($mybb->input['title'])) $errors[] = 'You must enter a title';
        $pid  = $mybb->get_input('pid', MyBB::INPUT_INT);
        $type = $mybb->input['type'];
        if ($pid <= 0 && $type === 'f') $errors[] = 'You must select a parent forum';

        if (!$errors) {
            $pid = max(0, $pid);
            $insert = [
                'name'             => $mybb->input['title'],
                'description'      => $mybb->input['description'],
                'linkto'           => $mybb->input['linkto'],
                'type'             => $type,
                'pid'              => $pid,
                'parentlist'       => '',
                'disporder'        => $mybb->get_input('disporder',        MyBB::INPUT_INT),
                'active'           => $mybb->get_input('active',           MyBB::INPUT_INT),
                'open'             => $mybb->get_input('open',             MyBB::INPUT_INT),
                'usepostcounts'    => $mybb->get_input('usepostcounts',    MyBB::INPUT_INT),
                'usethreadcounts'  => $mybb->get_input('usethreadcounts',  MyBB::INPUT_INT),
                'password'         => $mybb->input['password'],
                'defaultdatecut'   => $mybb->get_input('defaultdatecut',   MyBB::INPUT_INT),
                'defaultsortby'    => $mybb->input['defaultsortby'],
                'defaultsortorder' => $mybb->input['defaultsortorder'],
            ];

            $plugins->run_hooks('admin_forum_management_add_start');
            $columns      = array_keys($insert);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $db->sql_query_prepared(
                "INSERT INTO forums (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                array_values($insert)
            );
            $fid = $db->insert_id();
            $db->sql_query_prepared("UPDATE forums SET parentlist = ? WHERE fid = ?", [make_parent_list($fid), $fid]);
            $cache->update_forums();

            $inherit = $mybb->input['default_permissions'] ?? [];
            foreach ($mybb->input as $id => $permission) {
                if (strpos($id, 'fields_') === false
                    || strpos($id, 'fields_default_') !== false
                    || strpos($id, 'fields_inherit_') !== false
                ) continue;
                [, $gid] = explode('fields_', $id, 2);
                $gid = (int)$gid;
                if ($gid <= 0) continue;
                if (!is_array($permission)) {
                    $permission = array_fill_keys(explode(',', $permission), 1);
                }
                foreach (['canview','canpostthreads','canpostreplys','canpostpolls','canpostattachments'] as $n) {
                    $permissions[$n][$gid] = !empty($permission[$n]) ? 1 : 0;
                }
            }
            $canview            = $permissions['canview']            ?? [];
            $canpostthreads     = $permissions['canpostthreads']     ?? [];
            $canpostpolls       = $permissions['canpostpolls']       ?? [];
            $canpostattachments = $permissions['canpostattachments'] ?? [];
            $canpostreplies     = $permissions['canpostreplys']      ?? [];
            save_quick_perms($fid);

            $plugins->run_hooks('admin_forum_management_add_commit');
            log_admin_action($fid, $insert['name']);
            flash_message($lang->forum_management['success_forum_added'], 'success');
            admin_redirect('index.php?act=management');
        }
    }

    mgmt_add_breadcrumb('Add New Forum');

    $forum_data = [
        'type'           => 'f',
        'title'          => '',
        'description'    => '',
        'pid'            => empty($mybb->input['pid']) ? -1 : $mybb->get_input('pid', MyBB::INPUT_INT),
        'disporder'      => 1,
        'linkto'         => '',
        'password'       => '',
        'active'         => 1,
        'open'           => 1,
        'overridestyle'  => '',
        'style'          => '',
        'rulestype'      => '',
        'rulestitle'     => '',
        'rules'          => '',
        'defaultdatecut' => '',
        'defaultsortby'  => '',
        'defaultsortorder' => '',
        'allowhtml'      => '',
        'allowmycode'    => 1,
        'allowsmilies'   => 1,
        'allowimgcode'   => 1,
        'allowvideocode' => 1,
        'allowpicons'    => 1,
        'allowtratings'  => 1,
        'showinjump'     => 1,
        'usepostcounts'  => 1,
        'usethreadcounts'=> 1,
    ];

    if ($errors ?? false) {
        // output_inline_error() убран: он печатал красный блок ДО stdhead(), в самом верху
        // страницы. Ошибки показывает fm_errors() тостом (вызывается после вкладок).
        foreach ($forum_data as $k => $_) {
            if (isset($mybb->input[$k])) $forum_data[$k] = $mybb->input[$k];
        }
    }

    stdhead('Add New Forum');
	
    // admin-container: без него стили forum_management.css (они ограничены этим классом) сюда не применялись
    echo '<div class="container mt-3 admin-container fm2">';
    // Крошки убраны: дублировали вкладки ниже (View / Add Child / Edit / Copy)
    fm_head_assets();
	
	
    echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css">';
	
    echo '<link rel="stylesheet" href="templates/forum.css?ver=1813">';
    output_nav_tabs($sub_tabs ?? [], 'add_forum');
    // Раньше $errors собирались, но нигде не выводились — форма молча перезагружалась
    fm_errors($errors ?? []);

    mgmt_form_open('index.php?act=management&action=add', 'post');
    ?>

    <div class="container mt-4">
    <div class="card border-0 shadow-lg">
        <?php fm_card_header('Create New Forum', 'Configure your new forum with the settings below', 'plus-circle'); ?>
        <div class="card-body p-4">

            <div class="form-section">
                <?php fm_section_header('layer-group', 'primary', 'Forum Type', 'Select the type of forum you are creating'); ?>
                <?php fm_type_cards($forum_data['type']); ?>
            </div>

            <div class="form-section">
                <?php fm_section_header('info-circle', 'info', 'Basic Information', 'Essential details for your new forum'); ?>
                <?php fm_basic_fields($forum_data); ?>
            </div>

            <div class="form-section">
                <?php fm_section_header('sitemap', 'success', 'Forum Hierarchy', 'Organize your forum within the site structure'); ?>
                <label class="form-label fw-bold">Parent Forum <span class="required-badge">Required</span></label>
                <?= generate_forum_select('pid', $forum_data['pid'], ['id'=>'pid','class'=>'form-select form-select-lg','main_option'=>'None (Top Level)']) ?>
            </div>

            <?php fm_submit_row('index.php?act=management', 'Create Forum'); ?>
        </div>
    </div>
    </div>

    <?php
    fm_toggle_advanced_open();
    fm_extra_fields(array_merge(['active'=>1,'open'=>1,'usepostcounts'=>1,'usethreadcounts'=>1], $forum_data));
    fm_toggle_advanced_close();

    // ── Permissions table ─────────────────────────────────────
    $field_list2 = [
        'canview'       => 'View',
        'canpostthreads'=> 'Post Threads',
        'canpostreplys' => 'Post Replies',
        'canpostpolls'  => 'Post Polls',
    ];

    $q = $db->sql_query_prepared("SELECT * FROM usergroups");
    while ($q && ($ug = $db->fetch_array($q))) $ugList[$ug['gid']] = $ug;
    ?>

    <div class="card fm2-perm mt-4">
        <?= fm_perm_card_head('Initial Permissions', 'What each group can do in the new forum — you can change this later') ?>
        <div class="table-responsive">
        <table class="table fm2-table fm2-perm-table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:28%"><i class="fa-solid fa-users"></i>User group</th>
                    <th><i class="fa-solid fa-key"></i>Permissions</th>
                </tr>
            </thead>
            <tbody>
    <?php
    foreach ($ugList ?? [] as $ug) {
        $perms         = $ug;
        $perms_checked = [];
        foreach (array_keys($field_list2) as $fp) {
            $perms_checked[$fp] = ($perms[$fp] ?? 0) == 1 ? 1 : 0;
        }
        $gid      = $ug['gid'];
        $title    = htmlspecialchars_uni($ug['title']);
        $hiddenVal = implode(',', array_keys(array_filter($perms_checked)));

        $enabled_html  = implode('', array_map(
            fn($p) => $perms_checked[$p]
                ? '<span class="badge bg-success bg-opacity-10 text-success me-1 mb-1 permission-badge" data-perm="' . $p . '">' . $field_list2[$p] . '</span>'
                : '',
            array_keys($field_list2)
        ));
        $disabled_html = implode('', array_map(
            fn($p) => !$perms_checked[$p]
                ? '<span class="badge bg-danger bg-opacity-10 text-danger me-1 mb-1 permission-badge" data-perm="' . $p . '">' . $field_list2[$p] . '</span>'
                : '',
            array_keys($field_list2)
        ));


        $group_icon = fm_group_icon($ug);
        $zones      = fm_perm_zones((int)$gid, $enabled_html, $disabled_html);

echo <<<HTML
        <tr data-group-id="{$gid}">
            <td>
                <div class="d-flex align-items-center gap-3">
                    {$group_icon}
                    <div><div class="fw-bold">{$title}</div><div class="fm2-gid">GID {$gid}</div></div>
                </div>
            </td>
            <td>
                <div class="permission-fields" id="permission-fields-{$gid}">
                    {$zones}
                    <input type="hidden" name="fields_{$gid}" id="fields_{$gid}" value="{$hiddenVal}">
                </div>
            </td>
        </tr>
        HTML;
    }
    ?>
            </tbody>
        </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
            <span class="fm2-muted"><i class="fa-solid fa-circle-info me-1"></i>Permissions are saved together with the forum</span>
            <button type="submit" name="save" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-floppy-disk me-2"></i>Create Forum
            </button>
        </div>
    </div>

    <?php
    // QuickPermEditor инициализирует строки tr[data-group-id] сам (scripts/quick_perm_editor.js)

    mgmt_form_close();
    echo '</div>'; // container
    stdfoot();
    exit;
}






// ═══════════════════════════════════════════════════════════
// ACTION: EDIT
// ═══════════════════════════════════════════════════════════
if ($action === 'edit') {
    if (!$mybb->input['fid']) {
        flash_message($lang->forum_management['error_invalid_fid'], 'error');
        admin_redirect('index.php?act=management');
    }
    $fid   = $mybb->get_input('fid', MyBB::INPUT_INT);
    $query = $db->sql_query_prepared("SELECT * FROM forums WHERE fid = ?", [$fid]);
    $forum_data = $query ? $db->fetch_array($query) : null;
    if (!$forum_data) {
        flash_message($lang->forum_management['error_invalid_fid'], 'error');
        admin_redirect('index.php?act=management');
    }

    $plugins->run_hooks('admin_forum_management_edit');

    if ($mybb->request_method === 'post') {	
		verify_post_check($mybb->get_input('my_post_key'));

        $errors = [];
        if (!trim($mybb->input['title']))    $errors[] = 'You must enter a title';
        $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
        if ($pid === $fid)                   $errors[] = 'The forum parent cannot be the forum itself';
        else {
            $plq = $db->sql_query_prepared("SELECT parentlist FROM forums WHERE fid = ?", [$pid]);
            $parents = explode(',', $plq ? (string)$db->fetch_field($plq, 'parentlist') : '');
            if (in_array($fid, $parents))    $errors[] = 'Cannot set parent to a child forum';
        }
        $type = $mybb->input['type'];
        if ($pid <= 0 && $type === 'f')      $errors[] = 'You must select a parent forum';
        if ($type === 'c' && $forum_data['type'] === 'f') {
            $ctq = $db->sql_query_prepared("SELECT COUNT(tid) as n FROM threads WHERE fid = ?", [$fid]);
            if (($ctq ? $db->fetch_field($ctq, 'n') : 0) > 0)
                $errors[] = 'Forums with threads cannot be converted to categories';
        }
        if (!empty($mybb->input['linkto']) && empty($forum_data['linkto'])) {
            $ctq2 = $db->sql_query_prepared("SELECT COUNT(tid) as n FROM threads WHERE fid = ?", [$fid]);
            if (($ctq2 ? $db->fetch_field($ctq2, 'n') : 0) > 0)
                $errors[] = 'Forums with threads cannot be redirected';
        }

        if (!$errors) {
            $pid = max(0, $pid);
            $update = [
                'name'             => $mybb->input['title'],
                'description'      => $mybb->input['description'],
                'linkto'           => $mybb->input['linkto'],
                'type'             => $type,
                'pid'              => $pid,
                'disporder'        => $mybb->get_input('disporder',        MyBB::INPUT_INT),
                'active'           => $mybb->get_input('active',           MyBB::INPUT_INT),
                'open'             => $mybb->get_input('open',             MyBB::INPUT_INT),
                'usepostcounts'    => $mybb->get_input('usepostcounts',    MyBB::INPUT_INT),
                'usethreadcounts'  => $mybb->get_input('usethreadcounts',  MyBB::INPUT_INT),
                'password'         => $mybb->input['password'],
                'defaultdatecut'   => $mybb->get_input('defaultdatecut',   MyBB::INPUT_INT),
                'defaultsortby'    => $mybb->input['defaultsortby'],
                'defaultsortorder' => $mybb->input['defaultsortorder'],
            ];
            $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
            $params = array_values($update);
            $params[] = $fid;
            $db->sql_query_prepared("UPDATE forums SET {$set} WHERE fid = ?", $params);

            if ($pid !== (int)$forum_data['pid']) {
                $db->sql_query_prepared("UPDATE forums SET parentlist = ? WHERE fid = ?", [make_parent_list($fid), $fid]);
                $col = $db->type === 'pgsql' || $db->type === 'sqlite'
                    ? "','||parentlist||',' LIKE ?"
                    : "CONCAT(',',parentlist,',') LIKE ?";
                $q2 = $db->sql_query_prepared("SELECT fid FROM forums WHERE {$col}", ["%,{$fid},%"]);
                while ($q2 && ($ch = $db->fetch_array($q2))) {
                    $db->sql_query_prepared("UPDATE forums SET parentlist = ? WHERE fid = ?", [make_parent_list($ch['fid']), $ch['fid']]);
                }
            }

            // Quick perms
            $inherit = $mybb->input['default_permissions'] ?? [];
            foreach ($mybb->input as $id => $permission) {
                if (strpos($id, 'fields_') === false || strpos($id, 'fields_default_') !== false || strpos($id, 'fields_inherit_') !== false) continue;
                [, $gid] = explode('fields_', $id, 2);
                $gid = (int)$gid;
                if ($gid <= 0) continue;
                if ($mybb->input['fields_default_'.$gid] == $permission && $mybb->input['fields_inherit_'.$gid] == 1) { $inherit[$gid]=1; continue; }
                $inherit[$gid] = 0;
                if (!is_array($permission)) $permission = array_fill_keys(explode(',', $permission), 1);
                foreach (['canview','canpostthreads','canpostreplys','canpostpolls'] as $n) {
                    $permissions[$n][$gid] = !empty($permission[$n]) ? 1 : 0;
                }
            }
            $canview = $permissions['canview'] ?? [];
            $canpostthreads = $permissions['canpostthreads'] ?? [];
            $canpostpolls   = $permissions['canpostpolls']   ?? [];
            $canpostattachments = $permissions['canpostattachments'] ?? [];
            $canpostreplies = $permissions['canpostreplys']  ?? [];
            save_quick_perms($fid);
            $cache->update_forums();

            $plugins->run_hooks('admin_forum_management_edit_commit');
            log_admin_action($fid, $mybb->input['title']);
            flash_message('The forum settings have been updated successfully', 'success');
            admin_redirect("index.php?act=management&fid={$fid}");
        }
    }

    if ($errors ?? false) {
        // output_inline_error() убран: он печатал красный блок ДО stdhead(), в самом верху
        // страницы. Ошибки показывает fm_errors() тостом (вызывается после вкладок).
        $forum_data = array_merge($forum_data, $mybb->input);
    } else {
        $forum_data['title'] = $forum_data['name'];
    }

    //$extra_header = "<script src=\"scripts/quick_perm_editor.js\"></script>\n";
    mgmt_add_breadcrumb('Edit Forum');

    stdhead('Edit Forum');
    // admin-container: без него стили forum_management.css (они ограничены этим классом) сюда не применялись
    echo '<div class="container mt-3 admin-container fm2">';
    // Крошки убраны: дублировали вкладки ниже (View / Add Child / Edit / Copy)
  
    fm_head_assets();
	
	

echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css">';
	
	
    echo '<link rel="stylesheet" href="templates/forum.css?ver=1813">';
    output_nav_tabs($sub_tabs ?? [], 'edit_forum_settings');
    // Раньше $errors собирались, но нигде не выводились — форма молча перезагружалась
    fm_errors($errors ?? []);

    mgmt_form_open('index.php?act=management&action=edit', 'post');
    echo generate_hidden_field('fid', (string)$fid);
    ?>

    <div class="container mt-4">
    <div class="card border-0 shadow-sm">
        <?php fm_card_header('Edit Forum: ' . htmlspecialchars_uni($forum_data['title']), 'Update and configure your forum settings', 'edit'); ?>
        <div class="card-body p-4">

            <div class="form-section">
                <?php fm_section_header('layer-group','primary','Forum Type','Select the type of forum'); ?>
                <?php fm_type_cards($forum_data['type']); ?>
            </div>

            <div class="form-section">
                <?php fm_section_header('info-circle','info','Basic Information'); ?>
                <?php fm_basic_fields($forum_data); ?>
            </div>

            <div class="form-section">
                <?php fm_section_header('sitemap','success','Forum Hierarchy'); ?>
                <label class="form-label fw-bold">Parent Forum <span class="required-badge">Required</span></label>
                <?= generate_forum_select('pid', $forum_data['pid'], ['id'=>'pid','class'=>'form-select form-select-lg','main_option'=>'None (Top Level)']) ?>
            </div>

            <?php fm_submit_row('index.php?act=management', 'Save Changes'); ?>
        </div>
    </div>
    </div>

    <?php
    fm_toggle_advanced_open();
    fm_extra_fields($forum_data);
    fm_toggle_advanced_close();

    //echo '</form>';

    // ── Permissions table (same as add, but with existing data) ──
    $cached_forum_perms = $cache->read('forumpermissions');
    $field_list2 = ['canview'=>'View','canpostthreads'=>'Post Threads','canpostreplys'=>'Post Replies','canpostpolls'=>'Post Polls'];
    $existing_permissions = [];

    $q = $db->sql_query_prepared("SELECT * FROM forumpermissions WHERE fid = ?", [$fid]);
    while ($q && ($ex = $db->fetch_array($q))) $existing_permissions[$ex['gid']] = $ex;

    $q = $db->sql_query_prepared("SELECT * FROM usergroups");
    while ($q && ($ug = $db->fetch_array($q))) $ugList2[$ug['gid']] = $ug;
    ?>

    <div class="card fm2-perm mt-4">
        <?= fm_perm_card_head('Forum Permissions', htmlspecialchars_uni($forum_data['name']) . ' — inherited from the group unless customised') ?>
        <div class="table-responsive">
        <table class="table fm2-table fm2-perm-table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:24%"><i class="fa-solid fa-users"></i>User group</th>
                    <th><i class="fa-solid fa-key"></i>Permissions</th>
                    <th class="text-center" style="width:12%"><i class="fa-solid fa-code-branch"></i>Source</th>
                    <th class="text-end" style="width:10%"><i class="fa-solid fa-bolt"></i>Actions</th>
                </tr>
            </thead>
            <tbody>
    <?php foreach ($ugList2 ?? [] as $ug):
        $gid   = $ug['gid'];
        $title = htmlspecialchars_uni($ug['title']);
        if (!empty($existing_permissions[$gid])) {
            $perms = $existing_permissions[$gid]; $default_checked = false;
        } elseif (!empty($cached_forum_perms[$fid][$gid])) {
            $perms = $cached_forum_perms[$fid][$gid]; $default_checked = true;
        } else {
            $perms = $ug; $default_checked = true;
        }
        $pc = [];
        foreach (array_keys($field_list2) as $fp) $pc[$fp] = ($perms[$fp] ?? 0) == 1 ? 1 : 0;
        //$enabled  = implode('', array_map(fn($p) => $pc[$p] ? '<span class="badge bg-success bg-opacity-10 text-success me-1 mb-1 permission-badge">' . $field_list2[$p] . '</span>' : '', array_keys($field_list2)));
        //$disabled = implode('', array_map(fn($p) => !$pc[$p] ? '<span class="badge bg-danger bg-opacity-10 text-danger me-1 mb-1 permission-badge">' . $field_list2[$p] . '</span>' : '', array_keys($field_list2)));
		
		$enabled  = implode('', array_map(fn($p) => $pc[$p]
    ? '<span class="badge bg-success bg-opacity-10 text-success me-1 mb-1 permission-badge" data-perm="'.$p.'">' . $field_list2[$p] . '</span>'
    : '', array_keys($field_list2)));
$disabled = implode('', array_map(fn($p) => !$pc[$p]
    ? '<span class="badge bg-danger bg-opacity-10 text-danger me-1 mb-1 permission-badge" data-perm="'.$p.'">' . $field_list2[$p] . '</span>'
    : '', array_keys($field_list2)));
		
		
		
		
		
		
        $hiddenVal = implode(',', array_keys(array_filter($pc)));
        $status    = $default_checked
            ? '<span class="fm2-tag t-sub"><i class="fa-solid fa-arrow-turn-down"></i>Inherited</span>'
            : '<span class="fm2-tag t-cat"><i class="fa-solid fa-sliders"></i>Custom</span>';
    ?>
            <tr data-group-id="<?= $gid ?>">
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <?= fm_group_icon($ug) ?>
                        <div><div class="fw-bold"><?= $title ?></div><div class="fm2-gid">GID <?= $gid ?></div></div>
                    </div>
                </td>
                <td>
                    <?= fm_perm_zones((int)$gid, $enabled, $disabled) ?>
                    <input type="hidden" name="fields_<?= $gid ?>" id="fields_<?= $gid ?>" value="<?= $hiddenVal ?>">
                    <input type="hidden" name="fields_inherit_<?= $gid ?>" value="<?= (int)$default_checked ?>">
                    <input type="hidden" name="fields_default_<?= $gid ?>" value="<?= $hiddenVal ?>">
                </td>
                <td class="text-center"><?= $status ?></td>
                <td class="text-end text-nowrap">
                    <?php if (!$default_checked): ?>
                    <a href="index.php?act=management&action=permissions&pid=<?= $perms['pid'] ?>"
                       class="fm2-act" title="Edit permissions"
                       onclick="popupWindow(this.href + '&ajax=1', null, true);return false;">
                        <i class="fas fa-pen"></i>
                    </a>
                    <a href="javascript:void(0)" class="fm2-act text-danger clear-permission-btn" title="Reset to inherited"
                       data-pid="<?= $perms['pid'] ?>" data-fid="<?= $fid ?>"
                       data-gid="<?= $gid ?>" data-group-name="<?= addslashes($title) ?>"
                       data-post-key="<?= $mybb->post_code ?>">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php else: ?>
                    <a href="index.php?act=management&action=permissions&gid=<?= $gid ?>&fid=<?= $fid ?>"
                       class="fm2-act" title="Customise"
                       onclick="popupWindow(this.href + '&ajax=1', null, true);return false;">
                        <i class="fas fa-sliders"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
    <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
            <span class="fm2-muted"><i class="fa-solid fa-circle-info me-1"></i>Saved together with the forum settings above</span>
            <button type="submit" name="save_forum" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-floppy-disk me-2"></i>Save Changes
            </button>
        </div>
    </div>

    <?php
    // QuickPermEditor инициализирует строки tr[data-group-id] сам (scripts/quick_perm_editor.js)

    fm_clear_permission_modal();
    mgmt_form_close();
    echo '</div>';
    stdfoot();
	exit; // ← добавь это
}









if($action == "deletemod")
{
	$modid = $mybb->get_input('id', MyBB::INPUT_INT);
	$isgroup = $mybb->get_input('isgroup', MyBB::INPUT_INT);
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	$query = $db->sql_query_prepared("SELECT * FROM moderators WHERE id = ? AND isgroup = ? AND fid = ?", [$modid, $isgroup, $fid]);
	$mod = $query ? $db->fetch_array($query) : null;

	// Does the forum not exist?
	if(!$mod)
	{
		flash_message($lang->forum_management['error_invalid_moderator'], 'error');
		admin_redirect("index.php?act=management&fid={$fid}");
	}

	// User clicked no
	if(!empty($mybb->input['no']))
	{
		admin_redirect("index.php?act=management&fid={$fid}");
	}

	$plugins->run_hooks("admin_forum_management_deletemod");

	if($mybb->request_method == "post")
	{
		verify_post_check($mybb->get_input('my_post_key'));

		$mid = $mod['mid'];
		if($mybb->input['isgroup'])
		{
			$query = $db->sql_query_prepared("
				SELECT m.*, g.title
				FROM moderators m
				LEFT JOIN usergroups g ON (g.gid=m.id)
				WHERE m.mid = ?
			", [$mid]);
		}
		else
		{
			$query = $db->sql_query_prepared("
				SELECT m.*, u.username, u.usergroup
				FROM moderators m
				LEFT JOIN users u ON (u.id=m.id)
				WHERE m.mid = ?
			", [$mid]);
		}
		$mod = $query ? $db->fetch_array($query) : null;

		$db->sql_query_prepared("DELETE FROM moderators WHERE mid = ?", [$mid]);

		$plugins->run_hooks("admin_forum_management_deletemod_commit");

		$cache->update_moderators();

		$forum = get_forum($fid, 1);

		// Log admin action
		if($isgroup)
		{
			log_admin_action($mid, $mod['title'], $forum['fid'], $forum['name']);
		}
		else
		{
			log_admin_action($mid, $mod['username'], $forum['fid'], $forum['name']);
		}

		flash_message($lang->forum_management['success_moderator_deleted'], 'success');
		admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
	}
}







if ($action === 'delete')
{
    // Разрешаем ТОЛЬКО POST
    if ($mybb->request_method !== 'post')
    {
        http_response_code(405);
        echo 'Method not allowed';
        exit;
    }

    // CSRF (если используешь my_post_key)
    if (!verify_post_check($mybb->get_input('my_post_key')))
    {
        http_response_code(403);
        echo 'Invalid security token';
        exit;
    }

    // Получаем fid
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
    if (!$fid)
    {
        http_response_code(400);
        echo 'Invalid forum id';
        exit;
    }

    // Проверяем существование форума
    $query = $db->sql_query_prepared("SELECT * FROM forums WHERE fid = ?", [$fid]);
    $forum = $query ? $db->fetch_array($query) : null;

    if (!$forum)
    {
        http_response_code(404);
        echo 'Forum not found';
        exit;
    }

    $plugins->run_hooks('admin_forum_management_delete');

    // -------------------------------------------------
    // Ищем подфорумы
    // -------------------------------------------------
    $fids = [$fid];

    switch ($db->type)
    {
        case 'pgsql':
        case 'sqlite':
            $query = $db->sql_query_prepared(
                "SELECT * FROM forums WHERE ','||parentlist||',' LIKE ?",
                ["%,{$fid},%"]
            );
            break;

        default:
            $query = $db->sql_query_prepared(
                "SELECT * FROM forums WHERE CONCAT(',', parentlist, ',') LIKE ?",
                ["%,{$fid},%"]
            );
    }

    while ($query && ($subforum = $db->fetch_array($query)))
    {
        $fids[] = (int)$subforum['fid'];
    }

    // -------------------------------------------------
    // Удаляем темы
    // -------------------------------------------------
    require_once INC_PATH . '/class_moderation.php';
    $moderation = new Moderation();

    // Удаляем ВСЕ темы сразу (без HTML-пагинации)
    $fids_ph = implode(',', array_fill(0, count($fids), '?'));
    $query = $db->sql_query_prepared(
        "SELECT tid FROM threads WHERE fid IN ({$fids_ph})",
        $fids
    );

    while ($query && ($tid = $db->fetch_field($query, 'tid')))
    {
        $moderation->delete_thread((int)$tid);
    }

    // -------------------------------------------------
    // Удаляем форум и подфорумы
    // -------------------------------------------------
    $db->sql_query_prepared("DELETE FROM forums WHERE fid IN ({$fids_ph})", $fids);

    // -------------------------------------------------
    // Чистим связанные таблицы
    // -------------------------------------------------
    $db->sql_query_prepared("DELETE FROM moderators WHERE fid IN ({$fids_ph})", $fids);
    $db->sql_query_prepared("DELETE FROM forumsubscriptions WHERE fid IN ({$fids_ph})", $fids);
    $db->sql_query_prepared("DELETE FROM forumpermissions WHERE fid IN ({$fids_ph})", $fids);
    $db->sql_query_prepared("DELETE FROM announcements WHERE type IN ('forum', 'global') AND fid IN ({$fids_ph})", $fids);
    $db->sql_query_prepared("DELETE FROM forumsread WHERE fid IN ({$fids_ph})", $fids);

    // -------------------------------------------------
    // Хуки, кеши, лог
    // -------------------------------------------------
    $plugins->run_hooks('admin_forum_management_delete_commit');

    $cache->update_forums();
    $cache->update_moderators();
    $cache->update_forumpermissions();
    $cache->update_forumsdisplay();

    log_admin_action($fid, $forum['name']);

    // -------------------------------------------------
    // AJAX-ответ
    // -------------------------------------------------
    echo 'Forum deleted successfully';
    exit;
}









// ============================================================
// DEFAULT ACTION: MAIN VIEW
// ============================================================
if (!$action) {
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
    if ($fid) {
        $forum = get_forum($fid, true);
    }

    $plugins->run_hooks('admin_forum_management_start');

    // ── POST ─────────────────────────────────────────────────
    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        if ($mybb->get_input('update') === 'permissions') {
            $inherit     = [];
            $permissions = [];

            foreach ($mybb->input as $id => $permission) {
                if (!str_contains($id, 'fields_')
                    || str_contains($id, 'fields_default_')
                    || str_contains($id, 'fields_inherit_')
                ) { continue; }

                [, $gid] = explode('fields_', $id, 2);
                $gid = (int)$gid;
                if ($gid <= 0) continue;

                if (($mybb->input['fields_default_' . $gid] ?? null) == $permission
                    && (int)($mybb->input['fields_inherit_' . $gid] ?? 0) === 1
                ) {
                    $inherit[$gid] = 1;
                    continue;
                }
                $inherit[$gid] = 0;

                if (!is_array($permission)) {
                    $permission = array_fill_keys(explode(',', $permission), 1);
                }

                foreach (['canview', 'canpostthreads', 'canpostreplys', 'canpostpolls'] as $name) {
                    $permissions[$name][$gid] = !empty($permission[$name]) ? 1 : 0;
                }
            }

            $canview            = $permissions['canview']            ?? [];
            $canpostthreads     = $permissions['canpostthreads']     ?? [];
            $canpostpolls       = $permissions['canpostpolls']       ?? [];
            $canpostattachments = $permissions['canpostattachments'] ?? [];
            $canpostreplies     = $permissions['canpostreplys']      ?? [];

            save_quick_perms($fid);
            $plugins->run_hooks('admin_forum_management_start_permissions_commit');
            $cache->update_forums();
            log_admin_action('quickpermissions', $fid, $forum['name'] ?? '');

            flash_message($lang->forum_management['success_forum_permissions_updated'], 'success');
            admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");

        } elseif ($mybb->get_input('add') === 'moderators') {
            $forum = get_forum($fid, 1);
            if (!$forum) {
                flash_message($lang->forum_management['error_invalid_forum'], 'error');
                admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
            }

            if (!empty($mybb->input['usergroup'])) {
                $isgroup = 1;
                $gid     = $mybb->get_input('usergroup', MyBB::INPUT_INT);
                if (empty($groupscache[$gid])) {
                    flash_message($lang->forum_management['error_moderator_not_found'], 'error');
                    admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
                }
                $newmod = ['id' => $gid, 'name' => $groupscache[$gid]['title']];
            } else {
                $options    = ['fields' => ['id AS id', 'username AS name', 'usergroup', 'additionalgroups']];
                $newmod     = $newmoduser = get_user_by_username($mybb->input['username'] ?? '', $options);
                $isgroup    = 0;
                if (empty($newmod['id'])) {
                    flash_message($lang->forum_management['error_moderator_not_found'], 'error');
                    admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
                }
            }

            if (!empty($newmod['id'])) {
                $query = $db->sql_query_prepared(
                    "SELECT id FROM moderators WHERE id = ? AND fid = ? AND isgroup = ? LIMIT 1",
                    [$newmod['id'], $fid, $isgroup]
                );

                if (!$query || !$db->num_rows($query)) {
                    $new_mod = [
                        'fid' => $fid, 'id' => $newmod['id'], 'isgroup' => $isgroup,
                        'caneditposts' => 1, 'cansoftdeleteposts' => 1, 'canrestoreposts' => 1,
                        'candeleteposts' => 1, 'cansoftdeletethreads' => 1, 'canrestorethreads' => 1,
                        'candeletethreads' => 1, 'canviewips' => 1, 'canviewunapprove' => 1,
                        'canviewdeleted' => 1, 'canopenclosethreads' => 1, 'canstickunstickthreads' => 1,
                        'canapproveunapprovethreads' => 1, 'canapproveunapproveposts' => 1,
                        'canapproveunapproveattachs' => 1, 'canmanagethreads' => 1, 'canmanagepolls' => 1,
                        'canpostclosedthreads' => 1, 'canmovetononmodforum' => 1, 'canusecustomtools' => 1,
                        'canmanageannouncements' => 1, 'canmanagereportedposts' => 1, 'canviewmodlog' => 1,
                    ];
                    $columns      = array_keys($new_mod);
                    $placeholders = implode(',', array_fill(0, count($columns), '?'));
                    $db->sql_query_prepared(
                        "INSERT INTO moderators (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                        array_values($new_mod)
                    );
                    $mid = $db->insert_id();

                    if (!$isgroup) {
                        $newmodgroups = $newmoduser['usergroup'] ?? '';
                        if (!empty($newmoduser['additionalgroups'])) {
                            $newmodgroups .= ',' . $newmoduser['additionalgroups'];
                        }
                        $groupperms = usergroup_permissions($newmodgroups);
                        if (($groupperms['canmodcp'] ?? 0) != 1) {
                            $uid = $newmoduser['id'];
                            if (in_array((int)($newmoduser['usergroup'] ?? 0), [2, 5], true)) {
                                $db->sql_query_prepared("UPDATE users SET usergroup = 6 WHERE id = ?", [$uid]);
                            } else {
                                join_usergroup($uid, 6);
                            }
                        }
                    }

                    $plugins->run_hooks('admin_forum_management_start_moderators_commit');
                    $cache->update_moderators();
                    log_admin_action('addmod', $mid, $newmod['name'], $fid, $forum['name'] ?? '');

                    flash_message($lang->forum_management['success_moderator_added'], 'success');
                    admin_redirect("index.php?act=management&action=editmod&mid={$mid}");
                } else {
                    flash_message($lang->forum_management['error_moderator_already_added'], 'error');
                    admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
                }
            } else {
                flash_message($lang->forum_management['error_moderator_not_found'], 'error');
                admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
            }

        } else {
            // Save display order
            if (isset($mybb->input['save_forum_orders']) || isset($mybb->input['save_order'])) {
                $disporders = $mybb->input['disporder'] ?? [];
                if (!empty($disporders) && is_array($disporders)) {
                    foreach ($disporders as $update_fid => $order) {
                        $db->sql_query_prepared("UPDATE forums SET disporder = ? WHERE fid = ?", [(int)$order, (int)$update_fid]);
                    }
                    $plugins->run_hooks('admin_forum_management_start_disporder_commit');
                    $cache->update_forums();

                    if (!empty($forum)) {
                        log_admin_action('orders', $forum['fid'], $forum['name'] ?? '');
                    } else {
                        log_admin_action('orders', 0);
                    }

                    flash_message($lang->forum_management['success_forum_disporder_updated'], 'success');
                    admin_redirect('index.php?act=management&fid=' . $mybb->get_input('fid', MyBB::INPUT_INT));
                }
            }
        }
    }

    // ── Page setup ────────────────────────────────────────────
    //$extra_header .= "<script src=\"scripts/quick_perm_editor.js\"></script>\n";

    if ($fid) {
        mgmt_add_breadcrumb('View Forum', 'index.php?act=management');
    }

    if (!isset($forum_cache) || !is_array($forum_cache)) {
        cache_forums();
    }

    // Раньше здесь было new FormContainer(...) с заголовком - но заголовок
    // нигде реально не рендерился (настоящая таблица строится вручную
    // ниже), объект использовался только как счётчик строк через
    // construct_row()/num_rows(). Простой int делает то же самое.
    $mgmt_row_count = 0;

    $form = null; // Form больше не нужна - hidden-поля теперь вызываются напрямую,
    // а сам <form>-тег для этого раздела прописан вручную ниже в HTML.

    stdhead('Forum Management');
    fm_head_assets();
    echo '<link rel="stylesheet" href="templates/forum.css?ver=1813">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/userclass.css">';

    output_nav_tabs($sub_tabs, $fid ? 'view_forum' : 'forum_management');

    $cur = ($fid && isset($forum_cache[$fid])) ? $forum_cache[$fid] : null;
    $forum_name_esc = $cur ? htmlspecialchars_uni($cur['name']) : '';

    // Сводка по всем форумам (для плиток)
    $n_cat = $n_forum = $n_inactive = $n_threads = $n_posts = 0;
    foreach ($forum_cache as $f) {
        if (($f['type'] ?? '') === 'c') $n_cat++; else $n_forum++;
        if ((int)($f['active'] ?? 1) === 0) $n_inactive++;
    }
    foreach (fm_forum_counts() as $c) {
        $n_threads += (int)($c['threads'] ?? 0);
        $n_posts   += (int)($c['posts'] ?? 0);
    }

    // Крошки: раньше выводились ДВЕ цепочки (mgmt_render_breadcrumb + nav.breadcrumb)
    $crumbs = '<a href="index.php?act=management"><i class="fa-solid fa-sitemap me-1"></i>Forums</a>';
    if ($cur) {
        foreach (array_filter(array_map('intval', explode(',', (string)($cur['parentlist'] ?? '')))) as $pfid) {
            if ($pfid === $fid || !isset($forum_cache[$pfid])) continue;
            $crumbs .= '<i class="fa-solid fa-chevron-right fm2-sep"></i><a href="index.php?act=management&amp;fid=' . $pfid . '">' . htmlspecialchars_uni($forum_cache[$pfid]['name']) . '</a>';
        }
        $crumbs .= '<i class="fa-solid fa-chevron-right fm2-sep"></i><span class="fm2-cur">' . $forum_name_esc . '</span>';
    }

    $head_actions = $cur
        ? '<a href="index.php?act=management&amp;action=edit&amp;fid=' . $fid . '" class="btn btn-sm btn-primary rounded-pill px-3"><i class="fa-solid fa-pen me-1"></i>Edit</a>'
          . '<a href="index.php?act=management&amp;action=add&amp;pid=' . $fid . '" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="fa-solid fa-plus me-1"></i>Add child</a>'
        : '<a href="index.php?act=management&amp;action=add" class="btn btn-sm btn-primary rounded-pill px-3"><i class="fa-solid fa-plus me-1"></i>Add Forum</a>';

    $is_cat = $cur && ($cur['type'] ?? '') === 'c';

    echo '
<div class="admin-container fm2">
<div class="container mt-3 mb-4">

    <nav class="fm2-crumbs" aria-label="breadcrumb">' . $crumbs . '</nav>

    <div class="fm2-card mb-3"><div class="fm2-head">
        <span class="fm2-head-icon ' . ($cur ? ($is_cat ? 'ic-amber' : 'ic-blue') : 'ic-purple') . '"><i class="fa-solid ' . ($cur ? ($is_cat ? 'fa-folder-open' : 'fa-comments') : 'fa-sitemap') . '"></i></span>
        <div style="min-width:0">
            <h1 class="fm2-title">' . ($cur ? $forum_name_esc : 'Forum Management') . '</h1>
            <div class="fm2-sub">' . ($cur
                ? '<span class="fm2-gid">FID ' . $fid . '</span> · ' . ($is_cat ? 'Category' : 'Forum') . ' — sub-forums, permissions and moderators'
                : 'Categories, forums and their display order') . '</div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">' . $head_actions . '</div>
    </div></div>';

    if (!$cur) {
        echo '<div class="row g-3 mb-3">';
        foreach ([
            ['fa-folder',        'ic-amber',  'Categories', ts_nf($n_cat)],
            ['fa-comments',      'ic-blue',   'Forums',     ts_nf($n_forum)],
            ['fa-file-lines',    'ic-green',  'Threads',    ts_nf($n_threads)],
            ['fa-eye-slash',     'ic-slate',  'Inactive',   ts_nf($n_inactive)],
        ] as [$ic, $cls, $label, $val]) {
            echo '<div class="col-6 col-md-3"><div class="fm2-card fm2-stat"><span class="fm2-stat-icon ' . $cls . '"><i class="fa-solid ' . $ic . '"></i></span>'
               . '<div><div class="fm2-stat-label">' . $label . '</div><div class="fm2-stat-value">' . $val . '</div></div></div></div>';
        }
        echo '</div>';
    }

    // Вкладки. id панелей оставлены прежними (subforums / permissions / moderators) —
    // на них ссылается существующий код и JS.
    echo '
    <ul class="nav fm2-tabs" id="forumTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="subforums-tab" data-bs-toggle="tab" data-bs-target="#subforums" type="button" role="tab"><i class="fa-solid fa-folder-tree"></i>' . ($fid ? 'Sub-forums' : 'All forums') . '</button></li>'
      . ($fid ? '
        <li class="nav-item" role="presentation"><button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab"><i class="fa-solid fa-shield-halved"></i>Permissions</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="moderators-tab" data-bs-toggle="tab" data-bs-target="#moderators" type="button" role="tab"><i class="fa-solid fa-user-shield"></i>Moderators</button></li>' : '') . '
    </ul>

    <div class="card border-0 bg-transparent"><div class="card-body p-0">
    <div class="tab-content" id="forumTabsContent">

        <div class="tab-pane fade show active" id="subforums" role="tabpanel">
            <form method="post" action="index.php?act=management">
                ' . generate_hidden_field('fid', (string)$fid) . '
                ' . generate_hidden_field('my_post_key', $mybb->post_code) . '

                <div class="fm2-card overflow-hidden">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">
                        <span class="fw-bold"><i class="fa-solid fa-list-ol me-2 text-body-secondary"></i>Structure</span>
                        <div class="position-relative fm2-search"><i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" class="form-control form-control-sm" id="fm2Filter" placeholder="Filter forums…"></div>
                    </div>
                    <div class="table-responsive">
                    <table class="table fm2-table">
                        <thead><tr>
                            <th><i class="fa-solid fa-comments"></i>Forum</th>
                            <th class="text-center"><i class="fa-solid fa-chart-simple"></i>Content</th>
                            <th class="text-center"><i class="fa-solid fa-arrow-down-1-9"></i>Order</th>
                            <th class="text-end"><i class="fa-solid fa-bolt"></i>Actions</th>
                        </tr></thead>
                        <tbody>';

    build_admincp_forums_list($mgmt_row_count, $form, $fid);

    if ($mgmt_row_count === 0) {
        echo '<tr><td colspan="4"><div class="fm2-empty"><i class="fa-solid fa-inbox"></i>
                <div class="fw-semibold">' . ($fid ? 'No sub-forums yet' : 'No forums yet') . '</div>
                <a href="index.php?act=management&amp;action=add' . ($fid ? '&amp;pid=' . $fid : '') . '" class="btn btn-sm btn-primary rounded-pill px-3 mt-2"><i class="fa-solid fa-plus me-1"></i>Add ' . ($fid ? 'child forum' : 'forum') . '</a>
              </div></td></tr>';
    }

    echo '<tr id="fm2NoMatch" hidden><td colspan="4"><div class="fm2-empty"><i class="fa-solid fa-magnifying-glass"></i><div class="fw-semibold">No matches</div></div></td></tr>';
    echo '      </tbody>
                    </table>
                    </div>';

    if ($mgmt_row_count > 0) {
        echo '
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
                        <span class="fm2-muted"><i class="fa-solid fa-circle-info me-1"></i>' . $mgmt_row_count . ' item(s) · change the numbers and save to reorder</span>
                        <div class="d-flex gap-2">
                            <button type="reset" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="fa-solid fa-rotate-left me-1"></i>Reset</button>
                            <button type="submit" name="save_forum_orders" class="btn btn-sm btn-primary rounded-pill px-3"><i class="fa-solid fa-floppy-disk me-1"></i>Save order</button>
                        </div>
                    </div>';
    }

    echo '
                </div>
            </form>
        </div>';


    // ── Permissions tab ───────────────────────────────────────
    if ($fid && isset($forum_cache[$fid])) {
        $query = $db->sql_query_prepared("SELECT * FROM usergroups");
        $usergroups22 = [];
        while ($query && ($ug = $db->fetch_array($query))) { $usergroups22[$ug['gid']] = $ug; }

        $query = $db->sql_query_prepared("SELECT * FROM forumpermissions WHERE fid = ?", [$fid]);
        $existing_permissions = [];
        while ($query && ($ep = $db->fetch_array($query))) { $existing_permissions[$ep['gid']] = $ep; }

        $cached_forum_perms = $cache->read('forumpermissions');
        $n_custom = count(array_intersect_key($existing_permissions, $usergroups22));

        echo '
            <div class="tab-pane fade" id="permissions" role="tabpanel">
                <form method="post" action="index.php?act=management" id="permissionsForm">
                    <input type="hidden" name="fid" value="' . $fid . '">
                    <input type="hidden" name="update" value="permissions">
                    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">

                    <div class="card fm2-perm">
                        <div class="fm2-hdr">
                            <span class="fm2-hdr-icon ic-amber"><i class="fas fa-shield-halved"></i></span>
                            <div style="min-width:0"><h1>Forum Permissions</h1><p>Access rights in <strong>' . $forum_name_esc . '</strong> · drag a permission between the lists</p></div>
                            <div class="ms-auto d-flex flex-wrap gap-2 fm2-legend">
                                <span class="fm2-tag t-on"><i class="fa-solid fa-users"></i>' . count($usergroups22) . ' groups</span>
                                <span class="fm2-tag t-cat"><i class="fa-solid fa-sliders"></i>' . $n_custom . ' custom</span>
                            </div>
                        </div>
                        <div class="table-responsive">
                        <table class="table fm2-table fm2-perm-table align-middle mb-0">
                            <thead><tr>
                                <th style="width:24%"><i class="fa-solid fa-users"></i>User group</th>
                                <th><i class="fa-solid fa-key"></i>Permissions</th>
                                <th class="text-center" style="width:12%"><i class="fa-solid fa-code-branch"></i>Source</th>
                                <th class="text-end" style="width:10%"><i class="fa-solid fa-bolt"></i>Actions</th>
                            </tr></thead>
                            <tbody>';

        $perm_ids = [];
        foreach ($usergroups22 as $usergroup) {
            $gid = (int)$usergroup['gid'];
            [$perms, $default_checked] = fm_resolve_group_perms($usergroup, $fid, $existing_permissions, is_array($cached_forum_perms) ? $cached_forum_perms : []);
            echo fm_perm_row($usergroup, $fid, $perms, $default_checked);
            $perm_ids[] = $gid;
        }

        echo '
                            </tbody>
                        </table>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
                            <span class="fm2-muted"><i class="fa-solid fa-circle-info me-1"></i>Changed rows become “Custom” after saving; <i class="fa-solid fa-rotate-left mx-1"></i>returns a group to inherited</span>
                            <div class="d-flex gap-2">
                                <button type="reset" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="fas fa-rotate-left me-1"></i>Reset</button>
                                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="fas fa-floppy-disk me-1"></i>Save permissions</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>';

        // ── Moderators tab ────────────────────────────────────
        $query = $db->sql_query_prepared("
            SELECT m.mid, m.id, m.isgroup, u.username, u.usergroup, g.title
            FROM moderators m
            LEFT JOIN users u ON (m.isgroup='0' AND m.id=u.id)
            LEFT JOIN usergroups g ON (m.isgroup='1' AND m.id=g.gid)
            WHERE m.fid = ?
            ORDER BY m.isgroup DESC, u.username ASC, g.title ASC
        ", [$fid]);
        $current_moderators = [];
        while ($query && ($mod = $db->fetch_array($query))) { $current_moderators[] = $mod; }

        $query = $db->sql_query_prepared("SELECT * FROM usergroups ORDER BY title");
        $user_groups = [];
        while ($query && ($group = $db->fetch_array($query))) { $user_groups[$group['gid']] = $group; }

        echo '
            <div class="tab-pane fade" id="moderators" role="tabpanel">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="card fm2-perm">
                            <div class="fm2-hdr">
                                <span class="fm2-hdr-icon ic-blue"><i class="fas fa-user-shield"></i></span>
                                <div style="min-width:0"><h1>Moderators</h1><p>Users and groups that moderate <strong>' . $forum_name_esc . '</strong></p></div>
                                <div class="ms-auto fm2-legend"><span class="fm2-tag t-on"><i class="fa-solid fa-user-shield"></i>' . count($current_moderators) . '</span></div>
                            </div>
                            <div class="table-responsive">
                            <table class="table fm2-table align-middle mb-0">
                                <thead><tr>
                                    <th><i class="fa-solid fa-user"></i>Moderator</th>
                                    <th class="text-center"><i class="fa-solid fa-tag"></i>Type</th>
                                    <th class="text-end"><i class="fa-solid fa-bolt"></i>Actions</th>
                                </tr></thead>
                                <tbody>';

        if (empty($current_moderators)) {
            echo '<tr><td colspan="3"><div class="fm2-empty"><i class="fa-solid fa-user-slash"></i><div class="fw-semibold">No moderators yet</div><div class="small">Add a user or a whole group on the right</div></div></td></tr>';
        } else {
            foreach ($current_moderators as $moderator) {
                $is_group = (int)$moderator['isgroup'] === 1;
                $mod_name = htmlspecialchars_uni(($is_group ? $moderator['title'] : $moderator['username']) ?? '');
                $display  = ($is_group || !function_exists('format_name')) ? $mod_name : format_name($mod_name, (int)($moderator['usergroup'] ?? 0));
                $type     = $is_group
                    ? '<span class="fm2-tag t-sub"><i class="fa-solid fa-users"></i>Group</span>'
                    : '<span class="fm2-tag t-forum"><i class="fa-solid fa-user"></i>User</span>';

                echo '
                                <tr>
                                    <td><div class="d-flex align-items-center gap-3">
                                        <span class="fm2-gicon ' . ($is_group ? 'ic-teal' : 'ic-blue') . '"><i class="fas ' . ($is_group ? 'fa-users' : 'fa-user') . '"></i></span>
                                        <div><div class="fw-bold">' . ($mod_name !== '' ? $display : '<em class="text-body-secondary">deleted</em>') . '</div><div class="fm2-gid">' . ($is_group ? 'GID ' : 'UID ') . (int)$moderator['id'] . '</div></div>
                                    </div></td>
                                    <td class="text-center">' . $type . '</td>
                                    <td class="text-end text-nowrap">
                                        <a href="index.php?act=management&amp;action=editmod&amp;mid=' . (int)$moderator['mid'] . '" class="fm2-act" title="Edit moderator permissions"><i class="fas fa-pen"></i></a>
                                        <a href="#" class="fm2-act text-danger delete-moderator-btn" title="Remove moderator"
                                           data-mid="' . (int)$moderator['id'] . '" data-fid="' . $fid . '" data-isgroup="' . (int)$moderator['isgroup'] . '"
                                           data-post-key="' . $mybb->post_code . '"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>';
            }
        }

        $group_options = '';
        foreach ($user_groups as $group) {
            $group_options .= '<option value="' . (int)$group['gid'] . '">' . htmlspecialchars_uni($group['title']) . ' (GID ' . (int)$group['gid'] . ')</option>';
        }

        echo '
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="fm2-sticky">
                            <div class="card fm2-perm mb-3">
                                <div class="fm2-hdr py-3"><span class="fm2-hdr-icon ic-blue" style="width:38px;height:38px;font-size:1rem"><i class="fas fa-user-plus"></i></span>
                                    <div><h1 style="font-size:1.05rem">Add user</h1><p style="font-size:.85rem">A single member moderates this forum</p></div></div>
                                <form method="post" action="index.php?act=management" class="p-3">
                                    <input type="hidden" name="fid" value="' . $fid . '">
                                    <input type="hidden" name="add" value="moderators">
                                    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
                                    <label class="form-label" for="fm2ModUser"><i class="fa-solid fa-user me-1 text-body-secondary"></i>Username</label>
                                    <input type="text" id="fm2ModUser" name="username" class="form-control mb-3" placeholder="Start typing…" required autocomplete="off"
                                           data-autocomplete-url="../xmlhttp.php?action=get_users">
                                    <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="fas fa-user-plus me-1"></i>Add user</button>
                                </form>
                            </div>
                            <div class="card fm2-perm">
                                <div class="fm2-hdr py-3"><span class="fm2-hdr-icon ic-teal" style="width:38px;height:38px;font-size:1rem"><i class="fas fa-users"></i></span>
                                    <div><h1 style="font-size:1.05rem">Add group</h1><p style="font-size:.85rem">Every member of the group moderates</p></div></div>
                                <form method="post" action="index.php?act=management" class="p-3">
                                    <input type="hidden" name="fid" value="' . $fid . '">
                                    <input type="hidden" name="add" value="moderators">
                                    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
                                    <label class="form-label" for="fm2ModGroup"><i class="fa-solid fa-users me-1 text-body-secondary"></i>User group</label>
                                    <select id="fm2ModGroup" name="usergroup" class="form-select mb-3" required>
                                        <option value="">— Select group —</option>' . $group_options . '
                                    </select>
                                    <button type="submit" class="btn btn-outline-primary rounded-pill w-100"><i class="fas fa-plus me-1"></i>Add group</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';

        fm_clear_permission_modal();
        fm_delete_mod_modal();
    }

    echo '
        </div>
        </div>
    </div>
</div>
</div>';

    $plugins->run_hooks('admin_forum_management_start_graph');
    echo '<script src="scripts/deleteForum.js"></script>';
    // Поповеры, открытие вкладки по #tab_… и фильтр списка — в scripts/forum_management.js
	stdfoot();
	exit;
}














/**
 * @param int $mgmt_row_count Счётчик строк (по ссылке) - раньше здесь был объект DefaultFormContainer,
 *                             использовавшийся только для подсчёта, реальный HTML строится вручную ниже.
 * @param DefaultForm $form
 * @param int $pid
 * @param int $depth
 */
 
 





// popover.js: инициализация подсказок теперь внутри главной страницы (см. выше)

/**
 * Живые счётчики тем/постов по форумам: [fid => ['threads' => n, 'posts' => n]].
 * Два GROUP BY-запроса на всю страницу. Кэш форумов этих полей не содержит
 * (или счётчики в таблице forums не обновляются) — колонка Content была пустой.
 */
function fm_forum_counts(): array
{
    global $db;
    static $counts = null;
    if ($counts !== null) return $counts;

    $counts = [];
    $q = $db->sql_query_prepared("SELECT fid, COUNT(*) AS n FROM threads WHERE visible = 1 GROUP BY fid");
    while ($q && ($r = $db->fetch_array($q))) {
        $counts[(int)$r['fid']]['threads'] = (int)$r['n'];
    }
    $q = $db->sql_query_prepared("SELECT fid, COUNT(*) AS n FROM posts WHERE visible = 1 GROUP BY fid");
    while ($q && ($r = $db->fetch_array($q))) {
        $counts[(int)$r['fid']]['posts'] = (int)$r['n'];
    }
    return $counts;
}

function build_admincp_forums_list(&$mgmt_row_count, &$form, $pid = 0, $depth = 1)
{
    global $mybb, $lang, $db, $sub_forums;
    static $forums_by_parent;

    if (!is_array($forums_by_parent)) {
        $forum_cache = cache_forums();
        foreach ($forum_cache as $forum) {
            $forums_by_parent[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
        }
    }

    if (!isset($forums_by_parent[$pid]) || !is_array($forums_by_parent[$pid])) {
        return;
    }

    $subforumsindex = 2;
    $donecount = 0;
    $comma = '';

    foreach ($forums_by_parent[$pid] as $children) {
        foreach ($children as $forum) {
            $fid       = (int)$forum['fid'];
            $raw_name  = (string)$forum['name'];
            $forum['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $raw_name);

            if ($depth == 3) {
                if ($donecount < $subforumsindex) {
                    $sub_forums .= "{$comma}<a href=\"index.php?act=management&amp;fid={$fid}\" class=\"fm2-subchip\"><i class=\"fa-solid fa-turn-up fa-rotate-90\"></i>{$forum['name']}</a>";
                    $comma = ' ';
                }
                ++$donecount;
                if ($donecount == $subforumsindex && subforums_count2($forums_by_parent[$pid]) > $donecount) {
                    $sub_forums .= ' <span class="fm2-subchip is-more">+' . (subforums_count2($forums_by_parent[$pid]) - $donecount) . '</span>';
                    return;
                }
                continue;
            }

            if (!($depth == 1 || $depth == 2)) {
                continue;
            }

            $is_cat   = $forum['type'] == 'c';
            $inactive = (int)$forum['active'] === 0;
            $closed   = isset($forum['open']) && (int)$forum['open'] === 0;

            // Подфорумы третьего уровня собираются в $sub_forums рекурсивным вызовом
            $sub_forums = '';
            if (isset($forums_by_parent[$fid]) && $depth == 2) {
                build_admincp_forums_list($mgmt_row_count, $form, $fid, $depth + 1);
            }
            $subs_html = $sub_forums ? '<div class="fm2-subs">' . $sub_forums . '</div>' : '';

            $description = '';
            if (!$is_cat && !empty($forum['description'])) {
                $description = '<div class="fm2-muted fm2-desc">' . preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['description']) . '</div>';
            }

            $children_n = isset($forums_by_parent[$fid]) ? subforums_count2($forums_by_parent[$fid]) : 0;

            $tags = $is_cat
                ? '<span class="fm2-tag t-cat"><i class="fa-solid fa-folder"></i>Category</span>'
                : '<span class="fm2-tag t-forum"><i class="fa-solid fa-comments"></i>Forum</span>';
            if ($inactive) $tags .= ' <span class="fm2-tag t-off"><i class="fa-solid fa-eye-slash"></i>Inactive</span>';
            if ($closed)   $tags .= ' <span class="fm2-tag t-closed"><i class="fa-solid fa-lock"></i>Closed</span>';
            if ($children_n) $tags .= ' <span class="fm2-tag t-sub"><i class="fa-solid fa-sitemap"></i>' . $children_n . '</span>';

            $cnt     = fm_forum_counts()[$fid] ?? [];
            $content = $is_cat
                ? '<span class="fm2-muted">—</span>'
                : '<div class="fm2-counts"><span title="Threads"><i class="fa-solid fa-file-lines"></i>' . number_format((int)($cnt['threads'] ?? 0)) . '</span>'
                  . '<span title="Posts"><i class="fa-solid fa-comment"></i>' . number_format((int)($cnt['posts'] ?? 0)) . '</span></div>';

            $icon = $is_cat
                ? '<span class="fm2-ficon ic-amber"><i class="fa-solid fa-folder' . ($inactive ? '' : '-open') . '"></i></span>'
                : '<span class="fm2-ficon ic-blue"><i class="fa-solid ' . ($closed ? 'fa-lock' : 'fa-comments') . '"></i></span>';

            // Счётчик — один раз на строку (раньше у категорий увеличивался дважды,
            // и «N forum(s) found» показывал завышенное число)
            $mgmt_row_count++;

            echo '
            <tr class="forum-row' . ($is_cat ? ' is-cat' : '') . ($inactive ? ' is-off' : '') . '" data-search="' . htmlspecialchars_uni(my_strtolower(strip_tags(html_entity_decode($raw_name)))) . '">
                <td>
                    <div class="fm2-forum" style="--depth:' . ($depth - 1) . '">
                        ' . ($depth > 1 ? '<span class="fm2-branch"></span>' : '') . $icon . '
                        <div style="min-width:0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <a href="index.php?act=management&amp;fid=' . $fid . '" class="fm2-fname">' . ($inactive ? '<em>' . $forum['name'] . '</em>' : $forum['name']) . '</a>
                                <span class="fm2-gid">#' . $fid . '</span>
                            </div>
                            <div class="fm2-tags">' . $tags . '</div>
                            ' . $description . $subs_html . '
                        </div>
                    </div>
                </td>
                <td class="text-center">' . $content . '</td>
                <td class="text-center">
                    <input type="number" name="disporder[' . $fid . ']" value="' . (int)$forum['disporder'] . '" min="0" class="form-control form-control-sm fm2-order" aria-label="Display order">
                </td>
                <td class="text-end text-nowrap">' . generate_forum_actions($forum) . '</td>
            </tr>';

            if (!empty($forums_by_parent[$fid]) && ($is_cat || $depth == 1)) {
                build_admincp_forums_list($mgmt_row_count, $form, $fid, $depth + 1);
            }
        }
    }
}

/**
 * Кнопки действий для форума
 */
function generate_forum_actions($forum)
{
    $fid  = (int)$forum['fid'];
    $name = htmlspecialchars_uni((string)$forum['name']);

    return '
    <div class="fm2-actions">
        <a href="index.php?act=management&amp;action=edit&amp;fid=' . $fid . '" class="fm2-act" data-bs-toggle="popover" data-bs-content="Edit"><i class="fa-solid fa-pen"></i></a>
        <a href="index.php?act=management&amp;fid=' . $fid . '#tab_permissions" class="fm2-act" data-bs-toggle="popover" data-bs-content="Permissions"><i class="fa-solid fa-shield-halved"></i></a>
        <a href="index.php?act=management&amp;fid=' . $fid . '#tab_moderators" class="fm2-act" data-bs-toggle="popover" data-bs-content="Moderators"><i class="fa-solid fa-user-shield"></i></a>
        <div class="dropdown d-inline-block">
            <button class="fm2-act" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More"><i class="fa-solid fa-ellipsis-vertical"></i></button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                <li><a class="dropdown-item" href="index.php?act=management&amp;fid=' . $fid . '"><i class="fa-solid fa-sitemap fa-fw me-2"></i>Open / sub-forums</a></li>
                <li><a class="dropdown-item" href="index.php?act=management&amp;action=add&amp;pid=' . $fid . '"><i class="fa-solid fa-circle-plus fa-fw me-2"></i>Add child forum</a></li>
                <li><a class="dropdown-item" href="index.php?act=management&amp;action=copy&amp;fid=' . $fid . '"><i class="fa-solid fa-copy fa-fw me-2"></i>Copy forum</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger delete_employee" href="javascript:void(0)" data-emp-id="' . $fid . '" data-forum-name="' . $name . '"><i class="fa-solid fa-trash fa-fw me-2"></i>Delete forum</a></li>
            </ul>
        </div>
    </div>';
}


/**
 * Подсчитывает количество подфорумов
 */
function subforums_count2($forums)
{
    $count = 0;
    foreach($forums as $children)
    {
        $count += count($children);
    }
    return $count;
}






// (инициализация popover перенесена в главную страницу)







/**
 * @param int $gid
 * @param int $fid
 *
 * @return string
 */
/** Готовая строка <tr> группы для AJAX-ответа (та же разметка, что на вкладке Permissions) */
function retrieve_single_permissions_row(int $gid, int $fid): string
{
    global $cache, $db;

    $ugq = $db->sql_query_prepared("SELECT * FROM usergroups WHERE gid = ?", [$gid]);
    $usergroup = $ugq ? $db->fetch_array($ugq) : null;
    if (!$usergroup) {
        return '';
    }

    $existing_permissions = [];
    $q = $db->sql_query_prepared("SELECT * FROM forumpermissions WHERE fid = ? AND gid = ?", [$fid, $gid]);
    if ($q && ($row = $db->fetch_array($q))) {
        $existing_permissions[$gid] = $row;
    }

    $cached_forum_perms = $cache->read('forumpermissions');
    [$perms, $default_checked] = fm_resolve_group_perms($usergroup, $fid, $existing_permissions, is_array($cached_forum_perms) ? $cached_forum_perms : []);

    return fm_perm_row($usergroup, $fid, $perms, $default_checked);
}