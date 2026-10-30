<?php

declare(strict_types=1);

// work out which items the user has collapsed
$collapse = $collapsed = $collapsedimg = $collapsedthead = [];
if (!empty($mybb->cookies['collapsed'])) {
    $colcookie = $mybb->cookies['collapsed'];
    // Preserve and don't unset $collapse, will be needed globally throughout many pages
    $collapse = explode("|", $colcookie);
    foreach ($collapse as $val) {
        $collapsed[$val."_e"] = "display: none;";
        $collapsedimg[$val] = "_collapsed";
        $collapsedthead[$val] = " thead_collapsed";
    }
}


function validate_password_from_uid(int|string $uid, string $password, array $user = []): array|false
{
    global $db, $mybb, $CURUSER;

    if (isset($CURUSER['id']) && $CURUSER['id'] == $uid) {
        $user = $mybb->user;
    }

    if (!$user['password']) {
        $user = get_user((int)$uid);
    }

    if (empty($user['loginkey'])) 
	{
        $user['loginkey'] = generate_loginkey();

        if (!empty($user['id'])) 
		{
            $db->sql_query_prepared("UPDATE users SET loginkey = ? WHERE id = ?", [$user['loginkey'], $user['id']]);
        }
    }

    if (verify_user_password($user, $password)) {
        return $user;
    }

    return false;
}


function create_password(string $password, array|false $user = false): array
{
    global $plugins;

    $fields = null;

    $parameters = compact('password', 'user', 'fields');

    if (!defined('IN_INSTALL') && !defined('IN_UPGRADE')) {
        $plugins->run_hooks('create_password', $parameters);
    }

    if ($parameters['fields'] !== null) {
        $fields = $parameters['fields'];
    } 
	
	else 
	{
       
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $fields = [
            'password' => $hash,
        ];
    }

    return $fields;
}

function verify_user_password(array $user, string $password): bool
{
    global $plugins;

    $result = null;

    $parameters = compact('user', 'password', 'result');

    if (!defined('IN_INSTALL') && !defined('IN_UPGRADE')) {
        $plugins->run_hooks('verify_user_password', $parameters);
    }

    if ($parameters['result'] !== null) {
        return $parameters['result'];
    }

 
    return password_verify($password, $user['password'] ?? '');
}


function generate_loginkey(): string
{
    return random_str(50);
}


function update_loginkey(int|string $uid): string
{
    global $db;

    $loginkey = generate_loginkey();
    $db->sql_query_prepared("UPDATE users SET loginkey = ? WHERE id = ?", [$loginkey, $uid]);

    return $loginkey;
}


function add_subscribed_thread(int|string $tid, int|string $notification = 1, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $query = $db->sql_query_prepared("SELECT * FROM threadsubscriptions WHERE tid = ? AND uid = ?", [(int)$tid, (int)$uid]);
    $subscription = $query ? $db->fetch_array($query) : null;

    if (!$subscription) {
        $db->sql_query_prepared(
            "INSERT INTO threadsubscriptions (`uid`,`tid`,`notification`,`dateline`) VALUES (?,?,?,?)",
            [(int)$uid, (int)$tid, (int)$notification, TIMENOW]
        );
    } else {
        // Subscription exists - simply update notification
        $db->sql_query_prepared(
            "UPDATE threadsubscriptions SET notification = ? WHERE uid = ? AND tid = ?",
            [(int)$notification, $uid, $tid]
        );
    }

    return true;
}


function remove_subscribed_thread(int|string $tid, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE tid = ? AND uid = ?", [$tid, $uid]);

    return true;
}


function add_subscribed_forum(int|string $fid, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $fid = (int)$fid;
    $uid = (int)$uid;

    $query = $db->sql_query_prepared("SELECT * FROM forumsubscriptions WHERE fid = ? AND uid = ? LIMIT 1", [$fid, $uid]);
    $fsubscription = $query ? $db->fetch_array($query) : null;

    if (!$fsubscription) {
        $db->sql_query_prepared("INSERT INTO forumsubscriptions (`fid`,`uid`) VALUES (?,?)", [$fid, $uid]);
    }

    return true;
}


function remove_subscribed_forum(int|string $fid, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $db->sql_query_prepared("DELETE FROM forumsubscriptions WHERE fid = ? AND uid = ?", [$fid, $uid]);

    return true;
}


// ── UI helpers (стили — st-ui.css) ─────────────────────────────────────────
// Общие для usercp.php и private.php.
// ucp_open() открывает: .st-page > шапка + row[ col-3 меню | col-9 ← контент ].
// Открытыми остаются 3 div (container, row, col-9) — закрывает ucp_close().
function ucp_open(string $icon, string $tone, string $title, string $sub = ''): string
{
    global $usercpnav, $BASEURL;

    $meta = $sub !== '' ? '<div class="st-head__meta"><span>' . $sub . '</span></div>' : '';

    return '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css">'
        . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ucp.css">'
        . '<div class="container-md st-page">'
        .   '<div class="st-card st-head mb-3">'
        .     '<div class="st-head__icon st-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></div>'
        .     '<div class="min-w-0"><h1 class="st-title">' . $title . '</h1>' . $meta . '</div>'
        .   '</div>'
        .   '<div class="row g-3">'
        .     '<div class="col-lg-3">' . $usercpnav . '</div>'
        .     '<div class="col-lg-9 min-w-0">';
}

function ucp_close(): string
{
    return '</div></div></div>';
}

// Секция-карточка: ucp_sec_open(...) . тело . ucp_sec_close()
function ucp_sec_open(string $icon, string $tone, string $title): string
{
    return '<div class="st-card st-form-card">'
        . '<div class="st-card__head"><span class="st-ico st-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<span>' . $title . '</span></div><div class="st-card__body">';
}

function ucp_sec_close(): string
{
    return '</div></div>';
}

// Чекбокс-«таблетка» (.st-check)
function ucp_check(string $name, string $checked, string $label, string $icon = '', string $tone = '', string $value = '1'): string
{
    $ico = $icon !== ''
        ? '<i class="fa-solid ' . $icon . ' me-1' . ($tone !== '' ? ' text-' . $tone : '') . '"></i>'
        : '';

    return '<label class="st-check" for="' . $name . '">'
        . '<input type="checkbox" class="form-check-input" name="' . $name . '" id="' . $name . '" value="' . $value . '" ' . $checked . ' />'
        . '<span>' . $ico . $label . '</span></label>';
}

// Поле с подписью и <select>
function ucp_select(string $name, string $label, string $options, string $icon = '', string $tone = ''): string
{
    $ico = $icon !== '' ? '<i class="fa-solid ' . $icon . ($tone !== '' ? ' text-' . $tone : '') . '"></i>' : '';

    return '<div class="st-field"><label class="st-label" for="' . $name . '">' . $ico . $label . '</label>'
        . '<select name="' . $name . '" id="' . $name . '" class="form-select w-auto mw-100">' . $options . '</select></div>';
}
/**
 * Ссылка меню UCP. $icon — полный класс иконки ('fa-solid fa-key').
 * Активность определяется по ?action= (do_xxx приравнивается к xxx).
 */
function ucp_nav_is_active(string $href): bool
{
    global $mybb;

    if (!preg_match('~^usercp\.php(?:\?action=([a-z0-9_]+))?$~i', $href, $m)) {
        return false;
    }
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'usercp.php') {
        return false;
    }
    $cur = preg_replace('/^do_/', '', (string)($mybb->input['action'] ?? ''));

    return $cur === ($m[1] ?? '');
}

function ucp_nav_link(string $href, string $icon, string $label, string $tone = '', string $extra = '', bool $sub = false): string
{
    $cls = 'st-unav__link' . (ucp_nav_is_active($href) ? ' active' : '');

    if ($sub) {
        $ico = '<i class="' . $icon . ' fa-fw"></i>';
    } else {
        $ico = '<span class="st-ico st-soft-' . $tone . '"><i class="' . $icon . '"></i></span>';
    }

    return '<a href="' . $href . '" class="' . $cls . '">' . $ico
        . '<span class="min-w-0 text-truncate">' . $label . '</span>' . $extra . '</a>';
}

/** Раскрывающаяся группа. $items: [[href, icon, label], ...] */
function ucp_nav_group(string $id, string $icon, string $tone, string $label, array $items): string
{
    $open = false;
    $subs = '';
    foreach ($items as [$href, $ico, $text]) {
        $open = $open || ucp_nav_is_active($href);
        $subs .= ucp_nav_link($href, $ico, $text, '', '', true);
    }

    return '<div class="st-unav__group">'
        . '<a class="st-unav__link" data-bs-toggle="collapse" href="#' . $id . '" role="button"'
        . ' aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="' . $id . '">'
        . '<span class="st-ico st-soft-' . $tone . '"><i class="' . $icon . '"></i></span>'
        . '<span class="min-w-0 text-truncate">' . $label . '</span>'
        . '<i class="fa-solid fa-chevron-down st-unav__chev ms-auto"></i></a>'
        . '<div id="' . $id . '" class="collapse' . ($open ? ' show' : '') . '">'
        . '<div class="st-unav__sub">' . $subs . '</div></div></div>';
}

function usercp_menu(): void
{
    global $mybb, $theme, $plugins, $lang, $usercpnav, $usercpmenu, $enablepms, $usergroups, $BASEURL;

    $lang->load("usercpnav");
    $usercpmenu = '';

    // Add the default items as plugins with separated priorities of 10
    if ($enablepms != 0 && $usergroups['canusepms'] == 1) {
        $plugins->add_hook("usercp_menu", "usercp_menu_messenger", 10);
    }

    $plugins->add_hook("usercp_menu", "usercp_menu_profile", 20);
    $plugins->add_hook("usercp_menu", "usercp_menu_misc", 30);

    // Run the plugin hooks
    $plugins->run_hooks("usercp_menu");

    $ucp_nav_home = ucp_nav_link('usercp.php', 'fa-solid fa-house', $lang->usercpnav['ucp_nav_home'], 'primary');

    // Меню самодостаточно: подключает стили и сама создаёт .st-page, поэтому
    // работает и в usercp.php, и в private.php (вложенный .st-page получает padding 0).
    $usercpnav = '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css">'
        . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ucp.css">'
        . '<div class="st-page"><nav class="st-card st-unav mb-3">' . $ucp_nav_home . $usercpmenu . '</nav></div>';

    $plugins->run_hooks("usercp_menu_built");
}

function usercp_menu_messenger(): void
{
    global $db, $mybb, $CURUSER, $BASEURL, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

    $lang->load("usercpnav");
		

    $items = [
        ['private.php?action=send', 'fa-solid fa-marker', $lang->usercpnav['ucp_nav_compose']],
    ];

    $foldersexploded = explode("$%%$", $CURUSER['pmfolders']);
    foreach ($foldersexploded as $folders) {
        $folderinfo    = explode("**", $folders, 2);
        $folderinfo[1] = get_pm_folder_name((int)$folderinfo[0], $folderinfo[1] ?? '');
        $items[] = ['private.php?fid=' . $folderinfo[0], 'fa-regular fa-folder-open', $folderinfo[1]];
    }

    $items[] = ['private.php?action=tracking', 'fa-solid fa-flag', $lang->usercpnav['ucp_nav_tracking']];

    $usercpmenu .= ucp_nav_group(
        'ucp-nav-pm', 'fa-solid fa-envelope-open-text', 'info',
        $lang->usercpnav['ucp_nav_messenger'], $items
    );
}

function usercp_menu_profile(): void
{
    global $db, $mybb, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

    $lang->load("usercpnav");

    $usercpmenu .= ucp_nav_group('ucp-nav-profile', 'fa-solid fa-gears', 'primary', $lang->usercpnav['ucp_nav_profile'], [
        ['usercp.php?action=profile',  'fa-solid fa-user-pen',      $lang->usercpnav['ucp_nav_edit_profile']],
        ['usercp.php?action=password', 'fa-solid fa-key',           $lang->usercpnav['ucp_nav_change_pass']],
        ['usercp.php?action=2fa',      'fa-solid fa-shield-halved', 'Two-Factor Authentication'],
        ['usercp.php?action=sessions', 'fa-solid fa-display',       'Active Session'],
        ['usercp.php?action=email',    'fa-solid fa-at',            $lang->usercpnav['ucp_nav_change_email']],
        ['usercp.php?action=avatar',   'fa-solid fa-image',         $lang->usercpnav['ucp_nav_change_avatar']],
        ['usercp.php?action=editsig',  'fa-solid fa-signature',     $lang->usercpnav['ucp_nav_edit_sig']],
        ['usercp.php?action=options',  'fa-solid fa-sliders',       $lang->usercpnav['ucp_nav_edit_options']],
    ]);
}

function usercp_menu_misc(): void
{
    global $db, $mybb, $CURUSER, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

    $lang->load("usercpnav");

    $query = $db->sql_query_prepared("SELECT COUNT(pid) AS draftcount FROM posts WHERE visible = '-2' AND uid = ?", [$CURUSER['id']]);
    $count = $query ? (int)$db->fetch_field($query, 'draftcount') : 0;

    $draftbadge = $count > 0
        ? '<span class="st-badge st-soft-primary ms-auto">' . ts_nf($count) . '</span>'
        : '';

    $profile_link = get_profile_link($CURUSER['id']);

    $usercpmenu .= '<div class="st-unav__sep"></div>'
        . ucp_nav_link('usercp.php?action=editlists',          'fa-solid fa-user-group',  $lang->usercpnav['ucp_nav_editlists'], 'success')
        . ucp_nav_link('usercp.php?action=attachments',        'fa-solid fa-paperclip',   'Manage Attachments', 'muted')
        . ucp_nav_link('usercp.php?action=drafts',             'fa-solid fa-pen-ruler',   $lang->usercpnav['ucp_nav_drafts'] ?? 'Saved Drafts', 'warning', $draftbadge)
        . ucp_nav_link('usercp.php?action=subscriptions',      'fa-solid fa-comment',     $lang->usercpnav['ucp_nav_subscribed_threads'], 'info')
        . ucp_nav_link('usercp.php?action=forumsubscriptions', 'fa-solid fa-eye',         $lang->usercpnav['ucp_nav_forum_subscriptions'], 'info')
        . '<div class="st-unav__sep"></div>'
        . ucp_nav_link('usercp.php?action=bookmarks',          'fa-solid fa-bookmark',    $lang->usercpnav['ucp_nav_book'], 'danger')
        . ucp_nav_link('usercp.php?action=manage_files',       'fa-solid fa-folder-open', 'My Files', 'muted')
        . '<div class="st-unav__sep"></div>'
        . ucp_nav_link($profile_link, 'fa-solid fa-id-card-clip', $lang->usercpnav['ucp_nav_view_profile'], 'primary',
            '<i class="fa-solid fa-arrow-up-right-from-square ms-auto small opacity-50"></i>');
}


function update_pm_count(int|string $uid = 0, int $count_to_update = 7): array
{
    global $db, $mybb, $CURUSER;

    // If no user id, assume that we mean the current logged in user.
    if ((int)$uid == 0) {
        $uid = (int)(is_array($CURUSER) ? ($CURUSER['id'] ?? 0) : 0);
    }

    $uid = (int)$uid;
    $pmcount = [];

    if ($uid == 0) {
        return $pmcount;
    }

    // Update total number of messages.
    if ($count_to_update & 1) {
        $query = $db->sql_query_prepared("SELECT COUNT(pmid) AS pms_total FROM privatemessages WHERE uid = ?", [$uid]);
        $total = $query ? $db->fetch_array($query) : null;
        $pmcount['totalpms'] = $total['pms_total'];
    }

    // Update number of unread messages.
    if ($count_to_update & 2 && $db->field_exists("unreadpms", "users") == true) {
        $query = $db->sql_query_prepared(
            "SELECT COUNT(pmid) AS pms_unread FROM privatemessages WHERE uid = ? AND status = '0' AND folder = '1'",
            [$uid]
        );
        $unread = $query ? $db->fetch_array($query) : null;
        $pmcount['unreadpms'] = $unread['pms_unread'];
    }

    if (!empty($pmcount)) {
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($pmcount)));
        $params = array_values($pmcount);
        $params[] = $uid;

        $db->sql_query_prepared("UPDATE users SET {$set} WHERE id = ?", $params);
    }

    return $pmcount;
}


function get_pm_folder_name(int|string $fid, string $name = ''): string
{
    global $lang;

    if ($name !== '') {
        return $name;
    }

    return match ((int)$fid) {
        0       => 'Inbox',
        1       => 'Unread',
        2       => 'Sent Items',
        3       => 'Drafts',
        4       => 'Trash Can',
        default => 'folder_untitled',
    };
}
