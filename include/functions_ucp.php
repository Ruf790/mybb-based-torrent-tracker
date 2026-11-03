<?php

declare(strict_types=1);

/**
 * User CP UI: page frame (ucp_open/ucp_close), form helpers and the side menu.
 * Used only by usercp.php and private.php - kept out of functions_user.php,
 * which many other pages include.
 *
 * Menu: usercp_menu() builds $usercpnav; groups are added through the
 * "usercp_menu" plugin hook (messenger 10, profile 20, misc 30).
 */

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

    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $cur    = preg_replace('/^do_/', '', (string)($mybb->input['action'] ?? ''));

    // usercp.php / usercp.php?action=xxx
    if (preg_match('~^usercp\.php(?:\?action=([a-z0-9_]+))?$~i', $href, $m)) {
        return $script === 'usercp.php' && $cur === ($m[1] ?? '');
    }

    // private.php?fid=N (folder list) / private.php?action=send|tracking
    if ($script === 'private.php') {
        if (preg_match('~^private\.php\?fid=(\d+)$~', $href, $m)) {
            return $cur === '' && (int)($mybb->input['fid'] ?? 0) === (int)$m[1];
        }
        if (preg_match('~^private\.php\?action=([a-z0-9_]+)$~i', $href, $m)) {
            return $cur === $m[1];
        }
    }

    return false;
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
    global $plugins, $lang, $usercpnav, $usercpmenu, $enablepms, $usergroups, $BASEURL;

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
    global $CURUSER, $usercpmenu, $lang;

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
    global $usercpmenu, $lang;

    $lang->load("usercpnav");

    $usercpmenu .= ucp_nav_group('ucp-nav-profile', 'fa-solid fa-gears', 'primary', $lang->usercpnav['ucp_nav_profile'], [
        ['usercp.php?action=profile',  'fa-solid fa-user-pen',      $lang->usercpnav['ucp_nav_edit_profile']],
        ['usercp.php?action=password', 'fa-solid fa-key',           $lang->usercpnav['ucp_nav_change_pass']],
        ['usercp.php?action=2fa',      'fa-solid fa-shield-halved', $lang->usercpnav['ucp_nav_2fa'] ?? 'Two-Factor Authentication'],
        ['usercp.php?action=sessions', 'fa-solid fa-display',       $lang->usercpnav['ucp_nav_sessions'] ?? 'Active Sessions'],
        ['usercp.php?action=email',    'fa-solid fa-at',            $lang->usercpnav['ucp_nav_change_email']],
        ['usercp.php?action=avatar',   'fa-solid fa-image',         $lang->usercpnav['ucp_nav_change_avatar']],
        ['usercp.php?action=editsig',  'fa-solid fa-signature',     $lang->usercpnav['ucp_nav_edit_sig']],
        ['usercp.php?action=options',  'fa-solid fa-sliders',       $lang->usercpnav['ucp_nav_edit_options']],
    ]);
}

function usercp_menu_misc(): void
{
    global $db, $CURUSER, $usercpmenu, $lang;

    $lang->load("usercpnav");

    $query = $db->sql_query_prepared("SELECT COUNT(pid) AS draftcount FROM posts WHERE visible = '-2' AND uid = ?", [$CURUSER['id']]);
    $count = $query ? (int)$db->fetch_field($query, 'draftcount') : 0;

    $draftbadge = $count > 0
        ? '<span class="st-badge st-soft-primary ms-auto">' . ts_nf($count) . '</span>'
        : '';

    $profile_link = get_profile_link($CURUSER['id']);

    $usercpmenu .= '<div class="st-unav__sep"></div>'
        . ucp_nav_link('usercp.php?action=editlists',          'fa-solid fa-user-group',  $lang->usercpnav['ucp_nav_editlists'], 'success')
        . ucp_nav_link('usercp.php?action=attachments',        'fa-solid fa-paperclip',   $lang->usercpnav['ucp_nav_attachments'] ?? 'Manage Attachments', 'muted')
        . ucp_nav_link('usercp.php?action=drafts',             'fa-solid fa-pen-ruler',   $lang->usercpnav['ucp_nav_drafts'] ?? 'Saved Drafts', 'warning', $draftbadge)
        . ucp_nav_link('usercp.php?action=subscriptions',      'fa-solid fa-comment',     $lang->usercpnav['ucp_nav_subscribed_threads'], 'info')
        . ucp_nav_link('usercp.php?action=forumsubscriptions', 'fa-solid fa-eye',         $lang->usercpnav['ucp_nav_forum_subscriptions'], 'info')
        . '<div class="st-unav__sep"></div>'
        . ucp_nav_link('usercp.php?action=bookmarks',          'fa-solid fa-bookmark',    $lang->usercpnav['ucp_nav_book'], 'danger')
        . ucp_nav_link('usercp.php?action=manage_files',       'fa-solid fa-folder-open', $lang->usercpnav['ucp_nav_my_files'] ?? 'My Files', 'muted')
        . '<div class="st-unav__sep"></div>'
        . ucp_nav_link($profile_link, 'fa-solid fa-id-card-clip', $lang->usercpnav['ucp_nav_view_profile'], 'primary',
            '<i class="fa-solid fa-arrow-up-right-from-square ms-auto small opacity-50"></i>');
}
