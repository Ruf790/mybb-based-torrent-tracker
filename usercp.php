<?php
declare(strict_types=1);


define('IN_MYBB', 1);
define('THIS_SCRIPT', 'usercp.php');
define('ALLOWABLE_PAGE', 'removesubscription,removesubscriptions');
define('SCRIPTNAME', 'usercp.php');



require_once 'global.php';

define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);

require_once INC_PATH . '/functions_forum.php';
require_once INC_PATH . '/functions_category2.php';

if (!isset($CURUSER) || $CURUSER['id'] == 0) {
    print_no_permission();
}

require_once INC_PATH . '/editor.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_timezone.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_remote_connect.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_additions.php';

$parser = new postParser();

$lang->load('usercp');
$lang->load('member');

gzip();
maxsysop();

$userid  = (int) $CURUSER['id'];
$IsStaff = is_mod($usergroups);
$errors  = '';

$mybb->input['action'] ??= '';

usercp_menu();

// SweetAlert2 + ags-dialog.js (agsAlert / agsConfirm / data-ags-confirm) — выводится
// на тех страницах UCP, где есть диалоги; кнопки ОК/Да/Отмена — из global.lang.php
$swal_assets = '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">'
    . '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>'
    . ags_js_lang('global', ['dlg_ok' => 'js_dlg_ok', 'dlg_yes' => 'js_dlg_yes', 'dlg_cancel' => 'js_dlg_cancel'])
    . '<script src="' . $BASEURL . '/scripts/ags-dialog.js?ver=1"></script>';

// ── HTTP Referer sanitisation ──────────────────────────────────────────────
$server_http_referer = '';
if (isset($_SERVER['HTTP_REFERER'])) {
    $server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);
    if (!str_starts_with($server_http_referer, $BASEURL . '/')) {
        if (str_starts_with($server_http_referer, '/')) {
            $server_http_referer = substr($server_http_referer, 1);
        }
        $url_segments        = explode('/', $server_http_referer);
        $server_http_referer = $BASEURL . '/' . end($url_segments);
    }
}

$plugins->run_hooks('usercp_start');

// ── Pre-validate signature edit ────────────────────────────────────────────
if ($mybb->input['action'] === 'do_editsig' && $mybb->request_method === 'post') {
    require_once INC_PATH . '/datahandlers/user.php';
    $userhandler = new UserDataHandler();
    $userhandler->set_data([
        'uid'       => $CURUSER['id'],
        'signature' => $mybb->get_input('signature'),
    ]);

    if (!$userhandler->verify_signature()) {
        $error = inline_error($userhandler->get_friendly_errors());
    }

    if (isset($error) || !empty($mybb->input['preview'])) {
        $mybb->input['action'] = 'editsig';
    }
}

// ── Breadcrumb ─────────────────────────────────────────────────────────────
add_breadcrumb($lang->usercp['nav_usercp'], 'usercp.php');

$breadcrumb_map = [
    'profile'           => $lang->usercp['ucp_nav_profile'],
    'do_profile'        => $lang->usercp['ucp_nav_profile'],
    'options'           => $lang->usercp['nav_options'],
    'do_options'        => $lang->usercp['nav_options'],
    'email'             => $lang->usercp['nav_email'],
    'do_email'          => $lang->usercp['nav_email'],
    'password'          => $lang->usercp['nav_password'],
    'do_password'       => $lang->usercp['nav_password'],
    'subscriptions'     => $lang->usercpnav['ucp_nav_subscribed_threads'],
    'forumsubscriptions'=> $lang->usercpnav['ucp_nav_forum_subscriptions'],
    'editsig'           => $lang->usercp['nav_editsig'],
    'do_editsig'        => $lang->usercp['nav_editsig'],
    'avatar'            => $lang->usercp['nav_avatar'],
    'do_avatar'         => $lang->usercp['nav_avatar'],
    'editlists'         => $lang->usercpnav['ucp_nav_editlists'],
    'do_editlists'      => $lang->usercpnav['ucp_nav_editlists'],
    'drafts'            => $lang->usercpnav['ucp_nav_drafts'],
    'usergroups'        => $lang->usercpnav['ucp_nav_usergroups'],
    'attachments'       => $lang->usercpnav['ucp_nav_attachments'],
    'bookmarks'         => $lang->usercpnav['ucp_nav_book'],
	'2fa'               => $lang->usercp['nav_2fa'],
    'do_2fa'            => $lang->usercp['nav_2fa'],
];

if (isset($breadcrumb_map[$mybb->input['action']])) {
    add_breadcrumb($breadcrumb_map[$mybb->input['action']]);
}

// ── Avatar helper ──────────────────────────────────────────────────────────
$useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
$avatarssss = str_starts_with($useravatar['image'], '<')
    ? $useravatar['image']
    : '<img class="rounded img-fluid" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . ' />';

// ── Helper: render current-avatar HTML ────────────────────────────────────
function build_avatar_html(array $useravatar): string
{
    global $avatarsize;
    $maxMb = round(($avatarsize ?? 0) / 1024);


    $avatarClass = !empty($useravatar['is_placeholder']) ? 'avatar-ring img-fluid' : 'rounded img-fluid';

    return '<div style="position:relative;display:inline-block;">'
         . '<img id="avatarImage" class="' . $avatarClass . '" src="' . $useravatar['image'] . '" alt="" '
         . 'object-fit:cover;cursor:pointer;" data-original-avatar="' . $useravatar['image'] . '" data-max-mb="' . $maxMb . '">'
         . '<input type="file" id="avatarInput" name="avatarupload" style="display:none;" accept="image/*">'
         . '</div>';
}




// ── Helper: lang strings for page JS → window.AGS_LANG ────────────────────
// $map: js key (без префикса) => ключ из $lang->usercp. JS берёт их через t(key, fallback).
function ucp_js_lang(array $map): string
{
    global $lang;
    $out = [];
    foreach ($map as $jsKey => $langKey) {
        $out[$jsKey] = (string)($lang->usercp[$langKey] ?? '');
    }
    return '<script>window.AGS_LANG = Object.assign(window.AGS_LANG || {}, '
         . json_encode($out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
         . ');</script>';
}




// ── Helper: file type icon ─────────────────────────────────────────────────
function get_file_type_icon(string $file_type): string
{
    $icons = [
        'image/'           => 'fa-image',
        'video/'           => 'fa-video',
        'audio/'           => 'fa-music',
        'text/'            => 'fa-file-lines',
        'application/pdf'  => 'fa-file-pdf',
        'application/zip'  => 'fa-file-zipper',
        'application/'     => 'fa-file',
    ];
    foreach ($icons as $prefix => $icon) {
        if (str_starts_with($file_type, $prefix)) {
            return '<i class="fa-solid ' . $icon . ' me-1"></i>';
        }
    }
    return '<i class="fa-solid fa-file me-1"></i>';
}

// ── Helper: file preview ───────────────────────────────────────────────────
function get_file_preview(array $file): string
{
    if (str_starts_with($file['file_type'], 'image/')) {
        return '<img src="' . htmlspecialchars_uni($file['file_url']) . '"'
             . ' class="file-preview"'
             . ' alt="' . htmlspecialchars_uni($file['file_name']) . '"'
             . ' style="max-width:160px;max-height:160px;"'
             . ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">'
             . '<div class="file-icon-fallback" style="display:none;height:100%;align-items:center;justify-content:center;">'
             . '<i class="fa-solid fa-image fa-3x text-muted"></i></div>';
    }
    return '<div class="file-icon" style="height:100%;display:flex;align-items:center;justify-content:center;">'
         . '<i class="fa-solid fa-file fa-3x text-muted"></i></div>';
}



// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_options
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_options' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $plugins->run_hooks('usercp_do_options_start');

    // Build notification string
    $notifs  = $mybb->get_input('pmnotif') === 'yes' ? '[pm]' : '';
    $notifs .= $mybb->get_input('emailnotif') === 'yes' ? '[email]' : '';

    // Новые cat теги из hidden input
    $raw_cats = $mybb->get_input('cat_subscriptions');
    preg_match_all('/\[cat\d+\]/', $raw_cats, $m);
    $notifs .= implode('', $m[0]);
	

    require_once INC_PATH . '/datahandlers/user.php';
    $userhandler = new UserDataHandler('update');
    $userhandler->set_data([
        'uid'               => $CURUSER['id'],
        'dateformat'        => $mybb->get_input('dateformat', MyBB::INPUT_INT),
        'timeformat'        => $mybb->get_input('timeformat', MyBB::INPUT_INT),
        'timezone'          => $mybb->get_input('timezoneoffset'),
        'usergroup'         => $CURUSER['usergroup'],
        'options'           => [
            
			'allownotices' => $mybb->get_input('allownotices', MyBB::INPUT_INT),
		    'hideemail' => $mybb->get_input('hideemail', MyBB::INPUT_INT),
			'subscriptionmethod' => $mybb->get_input('subscriptionmethod', MyBB::INPUT_INT),
            'invisible'          => $mybb->get_input('invisible', MyBB::INPUT_INT),
            'dstcorrection'      => $mybb->get_input('dstcorrection', MyBB::INPUT_INT),
            'threadmode'         => $mybb->get_input('threadmode'),                       
            'commentpm'          => $mybb->get_input('commentpm', MyBB::INPUT_INT),
            'torrentsperpage'    => $mybb->get_input('tp', MyBB::INPUT_INT),
            'daysprune'          => $mybb->get_input('daysprune', MyBB::INPUT_INT),
            'buddyrequestsauto'  => $mybb->get_input('buddyrequestsauto', MyBB::INPUT_INT),
            'buddyrequestspm'    => $mybb->get_input('buddyrequestspm', MyBB::INPUT_INT),
            'pmnotice'           => $mybb->get_input('pmnotice', MyBB::INPUT_INT),
            'pmnotify'           => $mybb->get_input('pmnotify', MyBB::INPUT_INT),
            'receivepms'         => $mybb->get_input('receivepms', MyBB::INPUT_INT),
            'notifs'             => $notifs,
        ],
    ]);

    if ($usertppoptions) {
        $userhandler->data['options']['threadsperpages'] = $mybb->get_input('tpp', MyBB::INPUT_INT);
    }
    if ($userpppoptions) {
        $userhandler->data['options']['postsperpage'] = $mybb->get_input('ppp', MyBB::INPUT_INT);
    }

    if (!$userhandler->validate_user()) {
        $errors = inline_error($userhandler->get_friendly_errors());
        $mybb->input['action'] = 'options';
    } else {
        $userhandler->update_user();
        $plugins->run_hooks('usercp_do_options_end');
        redirect('usercp.php?action=options', $lang->usercp['redirect_optionsupdated']);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: options
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'options') {
    $user = $errors !== '' ? $mybb->input : $CURUSER;
    $plugins->run_hooks('usercp_options_start');

    // Boolean option helpers
    $opt = static fn(mixed $val, int $match = 1): string
        => isset($val) && (int) $val === $match ? 'checked="checked"' : '';

    $allowcommentpm      = $opt($CURUSER['commentpm'] ?? null);
    $invisiblecheck      = $opt($user['invisible'] ?? null);
    $hideemailcheck      = $opt($user['hideemail'] ?? null);
    $receivepmscheck     = $opt($user['receivepms'] ?? null);
    $receivefrombuddycheck = $opt($user['receivefrombuddy'] ?? null);
	$allownoticescheck = isset($user['allownotices']) && $user['allownotices'] >= 1 ? ' checked="checked"' : '';
    $pmnoticecheck       = isset($user['pmnotice']) && $user['pmnotice'] >= 1 ? ' checked="checked"' : '';
    $pmnotifycheck       = $opt($user['pmnotify'] ?? null, 0) === '' ? 'checked="checked"' : '';
    $buddyrequestspmcheck   = $opt($user['buddyrequestspm'] ?? null, 0) === '' ? 'checked="checked"' : '';
    $buddyrequestsautocheck = $opt($user['buddyrequestsauto'] ?? null, 0) === '' ? 'checked="checked"' : '';
    $showcodebuttonscheck   = $opt($user['showcodebuttons'] ?? null);
    $sourcemodecheck        = $opt($user['sourceeditor'] ?? null);
    $classicpostbitcheck    = $opt($user['classicpostbit'] ?? null);

    // pmnotify / buddyrequests need proper logic (re-check original):
    $pmnotifycheck          = (isset($user['pmnotify']) && $user['pmnotify'] != 0) ? 'checked="checked"' : '';
    $buddyrequestspmcheck   = (isset($user['buddyrequestspm']) && $user['buddyrequestspm'] != 0) ? 'checked="checked"' : '';
    $buddyrequestsautocheck = (isset($user['buddyrequestsauto']) && $user['buddyrequestsauto'] != 0) ? 'checked="checked"' : '';

    // Subscription method
    [$no_auto_subscribe_selected, $instant_email_subscribe_selected, $instant_pm_subscribe_selected, $no_subscribe_selected]
        = ['', '', '', ''];
    match ($user['subscriptionmethod'] ?? 0) {
        1       => $no_subscribe_selected              = 'selected="selected"',
        2       => $instant_email_subscribe_selected   = 'selected="selected"',
        3       => $instant_pm_subscribe_selected      = 'selected="selected"',
        default => $no_auto_subscribe_selected         = 'selected="selected"',
    };

    // DST
    [$dst_auto_selected, $dst_enabled_selected, $dst_disabled_selected] = ['', '', ''];
    match ($user['dstcorrection'] ?? 0) {
        2       => $dst_auto_selected     = 'selected="selected"',
        1       => $dst_enabled_selected  = 'selected="selected"',
        default => $dst_disabled_selected = 'selected="selected"',
    };

    $user['threadmode'] ??= '';
    if (!in_array($user['threadmode'], ['threaded', 'linear'], true)) {
        $user['threadmode'] = '';
    }

    // Date/time format options
    $date_format_options = '';
    foreach ($date_formats as $key => $format) {
        $selected = (isset($CURUSER['dateformat']) && $CURUSER['dateformat'] == $key) ? ' selected="selected"' : '';
        $dateformat = my_datee($format, TIMENOW, '', 0);
        $date_format_options .= '<option value="' . $key . '"' . $selected . '>' . $dateformat . '</option>';
    }

    $time_format_options = '';
    foreach ($time_formats as $key => $format) {
        $selected = (isset($CURUSER['timeformat']) && $CURUSER['timeformat'] == $key) ? ' selected="selected"' : '';
        $timeformat = my_datee($format, TIMENOW, '', 0);
        $time_format_options .= '<option value="' . $key . '"' . $selected . '>' . $timeformat . '</option>';
    }

    $tzselect = build_timezone_select('timezoneoffset', $CURUSER['timezone'], true);

    $pms_from_buddys = '';
    if ((int) ($allowbuddyonly ?? 0) === 1) {
        $pms_from_buddys = ucp_check('receivefrombuddy', $receivefrombuddycheck, $lang->usercp['receive_from_buddy']);
    }

    if ((int) ($enablepms ?? 1) !== 0) {
        $pms = ucp_check('receivepms', $receivepmscheck, $lang->usercp['receive_pms'])
            . $pms_from_buddys
            . ucp_check('pmnotice', $pmnoticecheck, $lang->usercp['pm_notice'])
            . ucp_check('pmnotify', $pmnotifycheck, $lang->usercp['pm_notify'])
            . ucp_check('buddyrequestspm', $buddyrequestspmcheck, $lang->usercp['buddyrequests_pm']);
    } else {
        $pms = '';
    }


    $threadview = ['linear' => '', 'threaded' => ''];
    if (is_scalar($user['threadmode'] ?? null)) {
        $threadview[$user['threadmode']] = 'selected="selected"';
    }

    $daysprunesel = array_fill_keys([1, 5, 10, 20, 50, 75, 100, 365, 9999], '');
    if (isset($user['daysprune']) && is_numeric($user['daysprune'])) {
        $daysprunesel[$user['daysprune']] = 'selected="selected"';
    }

    $user['style'] ??= '';

    // TPP / PPP selects
    $default_opt = '<option value="">' . $lang->usercp['use_default'] . '</option>';
    $tppselect = $pppselect = '';
    if ($usertppoptions) {
        $tppoptions = '';
        foreach (array_map('trim', explode(',', $usertppoptions)) as $val) {
            $selected = (isset($user['threadsperpages']) && $user['threadsperpages'] == $val) ? ' selected="selected"' : '';
            $tpp_option = sprintf($lang->usercp['tpp_option'], $val);
            $tppoptions .= '<option value="' . $val . '"' . $selected . '>' . $tpp_option . '</option>';
        }

        $tppselect = ucp_select('tpp', $lang->usercp['tpp'], $default_opt . $tppoptions, 'fa-list-ol', 'primary');
    }

    if ($userpppoptions) {
        $pppoptions = '';
        foreach (array_map('trim', explode(',', $userpppoptions)) as $val) {
            $selected = (isset($user['postsperpage']) && $user['postsperpage'] == $val) ? ' selected="selected"' : '';
            $ppp_option = sprintf($lang->usercp['ppp_option'], $val);
            $pppoptions .= '<option value="' . $val . '"' . $selected . '>' . $ppp_option . '</option>';
        }

        $pppselect = ucp_select('ppp', $lang->usercp['post_per_page'], $default_opt . $pppoptions, 'fa-comments', 'success');
    }

    // Torrents-per-page select
    $pppoptions2 = '';
    foreach (array_map('trim', explode(',', '5,10,15,20,25,30,40,50')) as $val) {
        $selected = (isset($CURUSER['torrentsperpage']) && $CURUSER['torrentsperpage'] == $val) ? ' selected="selected"' : '';
        $ppp_option2 = sprintf($lang->usercp['tp_option'], $val);
        $pppoptions2 .= '<option value="' . $val . '"' . $selected . '>' . $ppp_option2 . '</option>';
    }

    $pppselect2 = ucp_select('tp', $lang->usercp['tppp'], $default_opt . $pppoptions2, 'fa-magnet', 'danger');
$pmnotif    = str_contains($CURUSER['notifs'], '[pm]')    ? 'checked' : '';
$emailnotif = str_contains($CURUSER['notifs'], '[email]') ? 'checked' : '';

if (!isset($_categoriesC) || !is_array($_categoriesC)) {
    require_once TSDIR . '/cache/categories.php';
}

$notifs = $CURUSER['notifs'] ?? '';

$category_subscriptions = '';
foreach ($_categoriesC as $cat) {
    $cat_id = (int)$cat['id'];
    $active = str_contains($notifs, '[cat' . $cat_id . ']') ? 'active' : '';
    $icon   = htmlspecialchars_uni($cat['icon'] ?? 'fas fa-folder');
    $name   = htmlspecialchars_uni($cat['name']);
    $category_subscriptions .= '<button type="button" class="cat-pick-btn ' . $active . '" '
        . 'data-id="' . $cat_id . '" title="' . $name . '" onclick="toggleCatSub(this)">'
        . '<i class="' . $icon . '"></i><span>' . $name . '</span></button>';
}

preg_match_all('/\[cat\d+\]/', $notifs, $cat_matches);
$current_cats = implode('', $cat_matches[0]);


    // Invisible option
    $canbeinvisible = ucp_check('invisible', $invisiblecheck, $lang->usercp['invisible_mode'], 'fa-user-secret', 'secondary');

    $plugins->run_hooks('usercp_options_end');
    stdhead($lang->usercp['edit_options']);
    build_breadcrumb();

    $dflt = '<option value="0">' . $lang->usercp['use_default'] . '</option>';

    echo ucp_open('fa-sliders', 'primary', $lang->usercp['edit_options'])
        . $errors
        . '<form action="usercp.php" method="post">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'

        // ── Приватность ──
        . ucp_sec_open('fa-user-secret', 'muted', $lang->usercp['login_cookies_privacy'])
        .   $canbeinvisible
        . ucp_sec_close()

        // ── Сообщения и уведомления ──
        . ucp_sec_open('fa-envelope-open-text', 'info', $lang->usercp['messaging_notification'])
        .   ucp_check('commentpm', $allowcommentpm, $lang->usercp['pm10'], 'fa-paper-plane', 'primary')
        .   ucp_check('allownotices', $allownoticescheck, $lang->usercp['allow_notices'], 'fa-bell', 'warning')
        .   ucp_check('hideemail', $hideemailcheck, $lang->usercp['allow_emails'], 'fa-at', 'success')
        .   $pms
        .   ucp_check('buddyrequestsauto', $buddyrequestsautocheck, $lang->usercp['buddyrequests_auto'], 'fa-user-plus', 'secondary')
        .   '<div class="mt-3">'
        .   ucp_select('subscriptionmethod', $lang->usercp['subscription_method'],
                '<option value="0" ' . $no_auto_subscribe_selected . '>' . $lang->usercp['no_auto_subscribe'] . '</option>'
              . '<option value="1" ' . $no_subscribe_selected . '>' . $lang->usercp['no_subscribe'] . '</option>'
              . '<option value="2" ' . $instant_email_subscribe_selected . '>' . $lang->usercp['instant_email_subscribe'] . '</option>'
              . '<option value="3" ' . $instant_pm_subscribe_selected . '>' . $lang->usercp['instant_pm_subscribe'] . '</option>',
                'fa-bell-concierge', 'info')
        .   '</div>'
        . ucp_sec_close()

        // ── Уведомления по категориям торрентов ──
        . ucp_sec_open('fa-bell', 'warning', $lang->usercp['sec_cat_notifications_short'])
        .   '<p class="st-hint mt-0 mb-3"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_cat_notifications'] . '</p>'
        .   '<div class="d-flex flex-wrap gap-2 mb-3">'
        .     ucp_check('pmnotif', $pmnotif, '<strong>' . $lang->usercp['lbl_pm_notification_cat'] . '</strong>', 'fa-envelope', 'primary', 'yes')
        .     ucp_check('emailnotif', $emailnotif, '<strong>' . $lang->usercp['lbl_email_notification_cat'] . '</strong>', 'fa-at', 'success', 'yes')
        .   '</div>'
        .   '<input type="hidden" name="cat_subscriptions" id="catSubsSelected" value="' . htmlspecialchars_uni($current_cats) . '">'
        .   '<div class="category-icon-picker" id="catSubsPicker">' . $category_subscriptions . '</div>'
        .   '<div class="st-hint mt-3"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_cat_toggle'] . '</div>'
        . ucp_sec_close()

        // ── Дата и время ──
        . ucp_sec_open('fa-calendar-days', 'primary', $lang->usercp['date_time_options'])
        .   ucp_select('dateformat', $lang->usercp['date_format'], $dflt . $date_format_options, 'fa-calendar-day', 'primary')
        .   ucp_select('timeformat', $lang->usercp['time_format'], $dflt . $time_format_options, 'fa-clock', 'success')
        .   '<div class="st-field"><label class="st-label"><i class="fa-solid fa-earth-europe text-info"></i>' . $lang->usercp['time_offset'] . '</label>' . $tzselect . '</div>'
        .   ucp_select('dstcorrection', $lang->usercp['dst_correction'],
                '<option value="2" ' . $dst_auto_selected . '>' . $lang->usercp['dst_correction_auto'] . '</option>'
              . '<option value="1" ' . $dst_enabled_selected . '>' . $lang->usercp['dst_correction_enabled'] . '</option>'
              . '<option value="0" ' . $dst_disabled_selected . '>' . $lang->usercp['dst_correction_disabled'] . '</option>',
                'fa-sun', 'warning')
        . ucp_sec_close()

        // ── Отображение форума ──
        . ucp_sec_open('fa-chalkboard-user', 'info', $lang->usercp['forum_display_options'])
        .   ucp_select('daysprune', $lang->usercp['thread_view'],
                '<option value="">' . $lang->usercp['use_default'] . '</option>'
              . '<option value="1" '    . $daysprunesel['1']    . '>' . $lang->usercp['thread_view_lastday'] . '</option>'
              . '<option value="5" '    . $daysprunesel['5']    . '>' . $lang->usercp['thread_view_5days'] . '</option>'
              . '<option value="10" '   . $daysprunesel['10']   . '>' . $lang->usercp['thread_view_10days'] . '</option>'
              . '<option value="20" '   . $daysprunesel['20']   . '>' . $lang->usercp['thread_view_20days'] . '</option>'
              . '<option value="50" '   . $daysprunesel['50']   . '>' . $lang->usercp['thread_view_50days'] . '</option>'
              . '<option value="75" '   . $daysprunesel['75']   . '>' . $lang->usercp['thread_view_75days'] . '</option>'
              . '<option value="100" '  . $daysprunesel['100']  . '>' . $lang->usercp['thread_view_100days'] . '</option>'
              . '<option value="365" '  . $daysprunesel['365']  . '>' . $lang->usercp['thread_view_year'] . '</option>'
              . '<option value="9999" ' . $daysprunesel['9999'] . '>' . $lang->usercp['thread_view_all'] . '</option>',
                'fa-hourglass-half', 'secondary')
        .   $tppselect
        . ucp_sec_close()

        // ── Просмотр тем ──
        . ucp_sec_open('fa-eye', 'success', $lang->usercp['thread_view_options'])
        .   $pppselect
        .   $pppselect2
        . ucp_sec_close()

        . '<div class="st-submitbar justify-content-end">'
        .   '<input type="hidden" name="action" value="do_options" />'
        .   '<button type="submit" class="btn btn-primary st-pill" name="regsubmit" value="' . $lang->usercp['update_options'] . '">'
        .     '<i class="fa-solid fa-floppy-disk me-2"></i>' . $lang->usercp['update_options']
        .   '</button>'
        . '</div>'
        . '</form>'
        . ucp_close();

    echo '<script src="' . $BASEURL . '/scripts/usercp.js?ver=1903"></script>';
    stdfoot();
    exit;
	
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_email
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_email' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $errors = [];
    $plugins->run_hooks('usercp_do_email_start');

    if (!validate_password_from_uid($CURUSER['id'], $mybb->get_input('password'))) {
        $errors[] = $lang->usercp['error_invalidpassword'];
    } else {
        require_once INC_PATH . '/datahandlers/user.php';
        $userhandler = new UserDataHandler('update');
        $userhandler->set_data([
            'uid'    => $CURUSER['id'],
            'email'  => $mybb->get_input('email'),
            'email2' => $mybb->get_input('email2'),
        ]);

        if (!$userhandler->validate_user()) {
            $errors = $userhandler->get_friendly_errors();
        } else {
            $activation = false;
            if ($CURUSER['usergroup'] == 5 && in_array($regtype, ['verify', 'both'], true)) {
                $query      = $db->sql_query_prepared("SELECT * FROM awaitingactivation WHERE uid = ? AND (type='r' OR type='b')", [(int)$CURUSER['id']]);
                $activation = $query ? $db->fetch_array($query) : null;
            }

            if ($activation) {
                $userhandler->update_user();
                $db->sql_query_prepared("DELETE FROM awaitingactivation WHERE uid = ?", [(int)$CURUSER['id']]);

                $activationcode = random_str();
                $db->sql_query_prepared(
                    "INSERT INTO awaitingactivation (`uid`,`dateline`,`code`,`type`) VALUES (?,?,?,?)",
                    [(int)$CURUSER['id'], TIMENOW, $activationcode, $activation['type']]
                );

                $emailsubject = sprintf($lang->usercp['subj_account_activation'], $SITENAME);
                
				$emailmessage = sprintf(
                    $lang->member['email_activateaccount'],
                    $CURUSER['username'], $SITENAME, $BASEURL, $CURUSER['id'], $activationcode
                );
                my_mail($CURUSER['email'], $emailsubject, $emailmessage);
                $plugins->run_hooks('usercp_do_email_changed');
                redirect('usercp.php?action=email', $lang->member['redirect_emailupdated']);

            } elseif ($mybb->usergroup['cancp'] != 1 && in_array($regtype, ['verify', 'both'], true)) {
                $activationcode = random_str();
                $db->sql_query_prepared("DELETE FROM awaitingactivation WHERE uid = ?", [(int)$CURUSER['id']]);
                $db->sql_query_prepared(
                    "INSERT INTO awaitingactivation (`uid`,`dateline`,`code`,`type`,`misc`) VALUES (?,?,?,?,?)",
                    [(int)$CURUSER['id'], TIMENOW, $activationcode, 'e', $mybb->get_input('email')]
                );
                my_mail(
                    $mybb->get_input('email'),
                    sprintf($lang->usercp['subj_change_email'], $SITENAME),
                    sprintf($lang->usercp['email_changeemail'], $CURUSER['username'], $SITENAME, $CURUSER['email'],
                        $mybb->get_input('email'), $BASEURL, $activationcode, $CURUSER['username'], $CURUSER['id'])
                );
                $plugins->run_hooks('usercp_do_email_verify');
                
				stdok(
                    $lang->usercp['redirect_changeemail_activation'],
                    $lang->usercp['lbl_email_change'],
                    $lang->usercp['flash_activation_sent']
                );
				
				
				

            } else {
                $userhandler->update_user();
                my_mail(
                    $mybb->get_input('email'),
                    sprintf($lang->member['emailsubject_changeemail'], $SITENAME),
                    sprintf($lang->member['email_changeemail_noactivation'], $CURUSER['username'], $SITENAME,
                        $CURUSER['email'], $mybb->get_input('email'), $BASEURL)
                );
                $plugins->run_hooks('usercp_do_email_changed');
                redirect('usercp.php?action=email', $lang->member['redirect_emailupdated']);
            }
        }
    }

    if ($errors) {
        $mybb->input['action'] = 'email';
        $errors = inline_error($errors);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: email
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'email') {
    [$email, $email2] = $errors
        ? [htmlspecialchars_uni($mybb->get_input('email')), htmlspecialchars_uni($mybb->get_input('email2'))]
        : ['', ''];

    $plugins->run_hooks('usercp_email');
    stdhead($lang->usercp['change_email']);
    build_breadcrumb();

    echo ucp_open('fa-at', 'primary', $lang->usercp['change_email'], $lang->usercp['hint_email_sub'])
        . $errors
        . '<div class="st-notice st-soft-info mb-3">'
        .   '<i class="fa-solid fa-shield-halved"></i>'
        .   '<div><strong>' . $lang->usercp['lbl_security_notice'] . '</strong> ' . $lang->usercp['hint_email_notice'] . '</div>'
        . '</div>'
        . '<form action="usercp.php" method="post">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . ucp_sec_open('fa-envelope', 'primary', $lang->usercp['change_email'])

        // Текущий пароль
        . '<div class="st-field st-field--split">'
        .   '<label class="st-label" for="password"><i class="fa-solid fa-lock text-warning"></i>' . $lang->usercp['current_password'] . '</label>'
        .   '<div class="st-pw">'
        .     '<input type="password" class="form-control" name="password" id="password" placeholder="' . $lang->usercp['ph_current_password'] . '" />'
        .     '<button type="button" class="toggle-password"><i class="fa-solid fa-eye"></i></button>'
        .   '</div>'
        .   '<div class="st-hint"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_password_verify_identity'] . '</div>'
        . '</div>'

        // Новый e-mail
        . '<div class="st-field st-field--split">'
        .   '<label class="st-label" for="email"><i class="fa-solid fa-envelope text-primary"></i>' . $lang->usercp['new_email'] . '</label>'
        .   '<input type="email" class="form-control" name="email" id="email" maxlength="150" value="' . $email . '" placeholder="newemail@example.com" />'
        .   '<div id="emailFeedback" class="email-feedback"></div>'
        .   '<div class="st-hint"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_new_email'] . '</div>'
        . '</div>'

        // Подтверждение
        . '<div class="st-field">'
        .   '<label class="st-label" for="email2"><i class="fa-solid fa-circle-check text-success"></i>' . $lang->usercp['confirm_email'] . '</label>'
        .   '<input type="email" class="form-control" name="email2" id="email2" maxlength="150" value="' . $email2 . '" placeholder="' . $lang->usercp['ph_confirm_email'] . '" />'
        .   '<div id="emailMatchFeedback" class="email-feedback"></div>'
        . '</div>'

        . ucp_sec_close()
        . '<div class="st-submitbar justify-content-end">'
        .   '<input type="hidden" name="action" value="do_email" />'
        .   '<button type="submit" class="btn btn-primary st-pill" name="submit" value="' . $lang->usercp['update_email'] . '" id="submitBtn">'
        .     '<i class="fa-solid fa-floppy-disk me-2"></i>' . $lang->usercp['update_email']
        .   '</button>'
        . '</div>'
        . '</form>'
        . ucp_close();

    echo '<script src="' . $BASEURL . '/scripts/password-toggle.js" defer></script>';
    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_password
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_password' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $errors = [];
    $plugins->run_hooks('usercp_do_password_start');

    if (!validate_password_from_uid($CURUSER['id'], $mybb->get_input('oldpassword'))) {
        $errors[] = $lang->usercp['error_invalidpassword'];
    } else {
        require_once INC_PATH . '/datahandlers/user.php';
        $userhandler = new UserDataHandler('update');
        $userhandler->set_data([
            'uid'       => $CURUSER['id'],
            'password'  => $mybb->get_input('password'),
            'password2' => $mybb->get_input('password2'),
        ]);

        if (!$userhandler->validate_user()) {
            $errors = $userhandler->get_friendly_errors();
        } else {
            $userhandler->update_user();
            my_setcookie('mybbuser', $CURUSER['id'] . '_' . $userhandler->data['loginkey'], null, true, 'lax');
            $plugins->run_hooks('usercp_do_password_end');
            redirect('usercp.php?action=password', $lang->usercp['redirect_passwordupdated']);
        }
    }

    if ($errors) {
        $mybb->input['action'] = 'password';
        $errors = inline_error($errors);
    }
}






// ══════════════════════════════════════════════════════════════════════════
// ACTION: password
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'password') {
    $plugins->run_hooks('usercp_password');

    // ── Подготовка данных ─────────────────────────────────────────────────
    $BASE = htmlspecialchars($BASEURL ?? '', ENT_QUOTES, 'UTF-8');

    $minLen         = (int)($minpasswordlength       ?? 6);
    $maxLen         = (int)($maxpasswordlength       ?? 30);
    $requireComplex = (int)($requirecomplexpasswords ?? 0);
    $postCode       = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');

    $lang_change_password  = htmlspecialchars($lang->usercp['change_password']  ?? '', ENT_QUOTES, 'UTF-8');
    $lang_current_password = htmlspecialchars($lang->usercp['current_password'] ?? '', ENT_QUOTES, 'UTF-8');
    $lang_new_password     = htmlspecialchars($lang->usercp['new_password']     ?? '', ENT_QUOTES, 'UTF-8');
    $lang_confirm_password = htmlspecialchars($lang->usercp['confirm_password'] ?? '', ENT_QUOTES, 'UTF-8');
    $lang_update_password  = htmlspecialchars($lang->usercp['update_password']  ?? '', ENT_QUOTES, 'UTF-8');

    // ── Вывод страницы ────────────────────────────────────────────────────
    stdhead($lang->usercp['change_password'] ?? '');
    build_breadcrumb();

    $pwfield = static fn(string $id, string $ph, string $auto, string $max = ''): string =>
        '<div class="st-pw">'
        . '<input type="password" class="form-control" name="' . $id . '" id="' . $id . '" placeholder="' . $ph . '" autocomplete="' . $auto . '"' . $max . ' />'
        . '<button type="button" class="toggle-password" data-target="' . $id . '" aria-label="' . $lang->usercp['tip_toggle_password'] . '">'
        . '<i class="fa-solid fa-eye"></i></button></div>';

     echo ucp_open('fa-key', 'primary', $lang_change_password, $lang->usercp['hint_password_sub'])
   . $errors
        . '<div class="st-notice st-soft-info mb-3">'
        .   '<i class="fa-solid fa-shield-halved"></i>'
        .   '<div><strong>' . $lang->usercp['lbl_security_tip'] . '</strong> ' . sprintf($lang->usercp['hint_password_tip'], $minLen)
        .     '<div class="mt-1 small"><i class="fa-solid fa-circle-info me-1"></i>'
        .       sprintf($lang->usercp['hint_password_length'], $minLen, $maxLen) . ' ' . $requirecomplexpasswords
        .     '</div>'
        .   '</div>'
        . '</div>'
        . '<form action="usercp.php" method="post">'
        . '<input type="hidden" name="my_post_key" value="' . $postCode . '" />'
        . ucp_sec_open('fa-lock', 'primary', $lang_change_password)

        // Текущий пароль
        . '<div class="st-field st-field--split">'
        .   '<label class="st-label" for="oldpassword"><i class="fa-solid fa-lock-open text-warning"></i>' . $lang_current_password . '</label>'
        .   $pwfield('oldpassword', '••••••••', 'current-password')
        .   '<div class="st-hint"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_password_verification'] . '</div>'
        . '</div>'

        // Новый пароль
        . '<div class="st-field st-field--split">'
        .   '<label class="st-label" for="password"><i class="fa-solid fa-key text-primary"></i>' . $lang_new_password . '</label>'
        .   $pwfield('password', $lang->usercp['ph_new_password'], 'new-password', ' maxlength="' . $maxLen . '"')
        .   '<div class="st-charcount mt-1" id="passwordCharCounter">'
        .     '<i class="fa-solid fa-text-height me-1"></i>'
        .     '<span id="passwordLength">0</span> / ' . $maxLen . ' ' . $lang->usercp['lbl_characters']
        .     '<span id="minLengthHint" class="ms-2"><i class="fa-solid fa-circle-info me-1"></i>' . sprintf($lang->usercp['lbl_min_chars'], $minLen) . '</span>'
        .   '</div>'
        .   '<div class="mt-2" id="complexitySection">'
        .     '<div class="st-label mb-1"><i class="fa-solid fa-list-check"></i>' . $lang->usercp['lbl_password_requirements'] . '</div>'
        .     '<ul class="st-complexity" id="complexityList">'
        .       '<li id="complexityUppercase" class="invalid"><i class="fa-solid fa-circle-xmark"></i> ' . $lang->usercp['lbl_req_upper'] . '</li>'
        .       '<li id="complexityLowercase" class="invalid"><i class="fa-solid fa-circle-xmark"></i> ' . $lang->usercp['lbl_req_lower'] . '</li>'
        .       '<li id="complexityNumber" class="invalid"><i class="fa-solid fa-circle-xmark"></i> ' . $lang->usercp['lbl_req_number'] . '</li>'
        .       '<li id="complexitySpecial" class="invalid"><i class="fa-solid fa-circle-xmark"></i> ' . $lang->usercp['lbl_req_special'] . '</li>'
        .     '</ul>'
        .   '</div>'
        .   '<div class="st-strength mt-2">'
        .     '<div class="st-strength__bar"><div class="st-strength__fill" id="strengthFill"></div></div>'
        .     '<span id="strengthText" class="st-meta"></span>'
        .   '</div>'
        . '</div>'

        // Подтверждение
        . '<div class="st-field">'
        .   '<label class="st-label" for="password2"><i class="fa-solid fa-circle-check text-success"></i>' . $lang_confirm_password . '</label>'
        .   $pwfield('password2', $lang->usercp['ph_confirm_password'], 'new-password')
        .   '<div id="passwordMatch" class="small mt-1"></div>'
        . '</div>'

        . ucp_sec_close()
        . '<div class="st-submitbar justify-content-end">'
        .   '<input type="hidden" name="action" value="do_password" />'
        .   '<button type="submit" class="btn btn-primary st-pill" name="submit" value="' . $lang_update_password . '" id="submitBtn">'
        .     '<i class="fa-solid fa-floppy-disk me-2"></i>' . $lang_update_password
        .   '</button>'
        . '</div>'
        . '</form>'
        . ucp_close()
        . '<script>window.passwordConfig = { minLength: ' . $minLen . ', maxLength: ' . $maxLen . ', requireComplex: ' . $requireComplex . ' };</script>'
        . $swal_assets
        . ucp_js_lang([
            'pwd_min' => 'js_pwd_min',
            'pwd_max' => 'js_pwd_max',
            'pwd_match' => 'js_pwd_match',
            'pwd_mismatch' => 'js_pwd_mismatch',
            'pwd_weak' => 'js_pwd_weak',
            'enter_current_password' => 'js_enter_current_password',
            'enter_new_password' => 'js_enter_new_password',
            'updating' => 'js_updating',
        ])
        . '<script src="' . $BASEURL . '/scripts/password-validation.js?ver=7"></script>';

    stdfoot();
    exit;
}















// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_avatar
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_avatar' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $avatar_error = '';
    $plugins->run_hooks('usercp_do_avatar_start');

    require_once INC_PATH . '/functions_upload.php';

    if (!empty($mybb->input['remove'])) {
        $db->sql_query_prepared("UPDATE users SET avatar = '', avatardimensions = '', avatartype = '' WHERE id = ?", [(int)$CURUSER['id']]);
        remove_avatars($CURUSER['id']);

    } elseif ($_FILES['avatarupload']['name'] ?? '') {
        $avatar = upload_avatar();
        if (!empty($avatar['error'])) {
            $avatar_error = $avatar['error'];
        } else {
            $avatar_dimensions = ($avatar['width'] > 0 && $avatar['height'] > 0)
                ? $avatar['width'] . '|' . $avatar['height']
                : '';
            $db->sql_query_prepared(
                "UPDATE users SET avatar = ?, avatardimensions = ?, avatartype = ? WHERE id = ?",
                [$avatar['avatar'], $avatar_dimensions, 'upload', (int)$CURUSER['id']]
            );
        }

    } elseif (!$allowremoteavatars && !($_FILES['avatarupload']['name'] ?? '')) {
        $avatar_error = $lang->usercp['error_avatarimagemissing'];

    } elseif ($allowremoteavatars) {
        $avatarurl = preg_replace('#script:#i', '', $mybb->input['avatarurl']);
        $ext       = !empty(trim($avatarurl)) ? get_extension($avatarurl) : '';
        $file      = fetch_remote_file($avatarurl);

        if (!$file) {
            $avatar_error = $lang->usercp['error_invalidavatarurl'];
        } else {
            $tmp_name = $avataruploadpath . '/remote_' . md5((string) time() . mt_rand());
            $fp       = @fopen($tmp_name, 'wb');
            if (!$fp) {
                $avatar_error = $lang->usercp['error_invalidavatarurl'];
            } else {
                fwrite($fp, $file);
                fclose($fp);
                [, , $type] = @getimagesize($tmp_name);
                [$width, $height] = @getimagesize($tmp_name);
                @unlink($tmp_name);
                if (!$type) {
                    $avatar_error = $lang->usercp['error_invalidavatarurl'];
                }
            }
        }

        if (empty($avatar_error)) {
            $maxavatardims = '2000x2000';
            if ($width && $height && $maxavatardims !== '') {
                [$maxwidth, $maxheight] = preg_split('/[|x]/', strtolower($maxavatardims));
                if (($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight)) {
                    $avatar_error = sprintf($lang->usercp['error_avatartoobig'], $maxwidth, $maxheight);
                }
            }
            if (strlen($avatarurl) > 200) {
                $avatar_error = $lang->usercp['error_avatarurltoolong'];
            }
        }

        if (empty($avatar_error)) {
            $avatar_dimensions = ($width > 0 && $height > 0) ? (int) $width . '|' . (int) $height : '';
            $db->sql_query_prepared(
                "UPDATE users SET avatar = ?, avatardimensions = ?, avatartype = ? WHERE id = ?",
                [$avatarurl, $avatar_dimensions, 'remote', (int)$CURUSER['id']]
            );
            remove_avatars($CURUSER['id']);
        }
    } else {
        $avatar_error = $lang->usercp['error_remote_avatar_not_allowed'];
    }

    $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

    if (empty($avatar_error)) {
        $plugins->run_hooks('usercp_do_avatar_end');
        if ($isAjax) {
            echo json_encode(['success' => true]);
            exit;
        }
        redirect('usercp.php?action=avatar', $lang->usercp['redirect_avatarupdated']);
    } else {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => $avatar_error]);
            exit;
        }
        $mybb->input['action'] = 'avatar';
        $avatar_error = '<div class="container mt-3"><div class="red_alert mb-3" role="alert">' . $avatar_error . '</div></div>';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: avatar
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'avatar') {
    $plugins->run_hooks('usercp_avatar_start');

    $avatarmsg = $avatarurl = '';
    if ($CURUSER['avatartype'] === 'upload' || str_contains($CURUSER['avatar'], $avataruploadpath)) {
        $avatarmsg = '<br /><strong>' . $lang->usercp['already_uploaded_avatar'] . '</strong>';
    } elseif ($CURUSER['avatartype'] === 'remote' || my_validate_url($CURUSER['avatar'])) {
        $avatarmsg = '<br /><strong>' . $lang->usercp['using_remote_avatar'] . '</strong>';
        $avatarurl = htmlspecialchars_uni($CURUSER['avatar']);
    }


    $useravatar    = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
    $currentavatar = build_avatar_html($useravatar);

    $avatar_note = '';
    if (!empty($maxavatardims)) {
        [$maxwidth, $maxheight] = preg_split('/[|x]/', strtolower($maxavatardims));
        $avatar_note .= '<br />' . sprintf($lang->usercp['avatar_note_dimensions'], $maxwidth, $maxheight);
    }
    if (!empty($avatarsize)) {
        $avatar_note .= '<br />' . sprintf($lang->usercp['avatar_note_size'], mksize($avatarsize * 1024));
    }

    $plugins->run_hooks('usercp_avatar_intermediate');

    $avatarresizing = 'auto';
    $auto_resize = match ($avatarresizing) {
        'auto'  => '<div class="mt-1"><i class="bi bi-info-circle"></i>&nbsp;<span class="text-muted" style="font-size:14px">'
                 . $lang->usercp['avatar_auto_resize_note'] . '</span></div>',
        'user'  => '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="auto_resize" value="1" checked id="auto_resize" />'
                 . '<label class="form-check-label" for="auto_resize">' . $lang->usercp['avatar_auto_resize_option'] . '</label></div>',
        default => '',
    };

    $avatar_remote = '<div class="input-group">'
        . '<span class="input-group-text"><i class="fa-solid fa-globe"></i></span>'
        . '<input type="text" class="form-control" name="avatarurl" id="avatarurl" value="' . $avatarurl . '" placeholder="https://" />'
        . '</div>'
        . '<div class="st-hint"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['avatar_url_note'] . ' ' . $lang->usercp['avatar_url_gravatar'] . '</div>';

    $removeavatar = !empty($CURUSER['avatar'])
        ? '<button type="submit" class="btn btn-outline-secondary st-pill" name="remove" value="Remove Avatar"><i class="fa-solid fa-xmark"></i> &nbsp;' . $lang->usercp['remove_avatar'] . '</button>'
        : '';

    $plugins->run_hooks('usercp_avatar_end');
    $avatar_error ??= '';

    stdhead($lang->usercp['change_avatar']);
    build_breadcrumb();
    
    $avatar_note_clean = preg_replace('~^(<br\s*/?>)+~i', '', $avatar_note);

    echo ucp_open('fa-image', 'primary', $lang->usercp['change_avatar'], $lang->usercp['hint_avatar_sub'])
        . $avatar_error
        . '<form enctype="multipart/form-data" action="usercp.php" method="post" id="avatarForm">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'

        . ucp_sec_open('fa-user-circle', 'primary', $lang->usercp['lbl_current_avatar'])
        .   '<div class="st-hint mt-0 mb-3"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_current_avatar']
        .     ($avatar_note_clean !== '' ? '<br>' . $avatar_note_clean : '') . '</div>'
        .   '<div class="row align-items-center g-4">'
        .     '<div class="col-md-5 text-center">'
        .       '<div class="avatar-container">' . $currentavatar . '</div>'
        .       '<div class="st-meta mt-2"><i class="fa-solid fa-camera me-1"></i>' . $lang->usercp['lbl_current_avatar_caption'] . '</div>'
        .     '</div>'
        .     '<div class="col-md-7">'
        .       '<label class="st-label" for="avatarupload"><i class="fa-solid fa-cloud-arrow-up text-primary"></i>' . $lang->usercp['lbl_upload_new_avatar'] . '</label>'
        .       '<div class="file-input-wrapper">' . $avatarupload . '</div>'
        .       '<div class="st-hint"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_avatar_formats'] . '</div>'
        .     '</div>'
        .   '</div>'
        . ucp_sec_close()

        . ucp_sec_open('fa-link', 'info', $lang->usercp['avatar_url'])
        .   $avatar_remote
        .   '<div class="st-hint"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_avatar_url'] . '</div>'
        . ucp_sec_close()

        . '<div class="st-submitbar">'
        .   '<div>' . $removeavatar . '</div>'
        .   '<input type="hidden" name="action" value="do_avatar" />'
        .   '<button type="submit" class="btn btn-primary st-pill" name="submit" value="' . $lang->usercp['change_avatar'] . '">'
        .     '<i class="fa-solid fa-floppy-disk me-2"></i>' . $lang->usercp['change_avatar']
        .   '</button>'
        . '</div>'
        . '</form>'
        . ucp_close();

    // Скрипты — после разметки страницы
    echo $swal_assets;
    echo avatar_js_lang();
    echo '<script src="' . $BASEURL . '/scripts/toast.js?ver=2"></script>';
    echo '<script src="' . $BASEURL . '/scripts/avatar_upload.js?ver=5"></script>';
    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_addsubscription (thread only)
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_addsubscription' && $mybb->get_input('type') !== 'forum') {
    verify_post_check($mybb->get_input('my_post_key'));

    $thread = get_thread($mybb->get_input('tid'));
    if (!$thread || $thread['visible'] == -1) {
        error('error_invalidthread');
    }

    $forumpermissions = forum_permissions($thread['fid']);
    if ($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0
        || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0
            && $thread['uid'] != $CURUSER['id'])) {
        error_no_permission();
    }

    check_forum_password($thread['fid']);
    $plugins->run_hooks('usercp2_do_addsubscription');
    add_subscribed_thread($thread['tid'], $mybb->get_input('notification', MyBB::INPUT_INT));

    $referrer = $mybb->get_input('referrer');
    if ($referrer) {
        if (!str_starts_with($referrer, $BASEURL . '/')) {
            $referrer = str_starts_with($referrer, '/')
                ? substr($referrer, 1)
                : end(explode('/', $referrer));
            $referrer = $BASEURL . '/' . $referrer;
        }
        $url = htmlspecialchars_uni($referrer);
    } else {
        $url = get_thread_link($thread['tid']);
    }
    redirect($url, $lang->usercp['redirect_subscriptionadded']);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: addsubscription
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'addsubscription') {
    verify_post_check($mybb->get_input('my_post_key'));

    if ($mybb->get_input('type') === 'forum') {
        $forum = get_forum($mybb->get_input('fid', MyBB::INPUT_INT));
        if (!$forum) { error('error_invalidforum'); }

        $forumpermissions = forum_permissions($forum['fid']);
        if ($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0) {
            error_no_permission();
        }

        check_forum_password($forum['fid']);
        $plugins->run_hooks('usercp2_addsubscription_forum');
        add_subscribed_forum($forum['fid']);

        $url = ($server_http_referer && $mybb->request_method !== 'post') ? $server_http_referer : 'index.php';
        redirect($url, $lang->usercp['redirect_forumsubscriptionadded']);
    } else {
        $thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
        if (!$thread || $thread['visible'] == -1) { error('error_invalidthread'); }

        add_breadcrumb('nav_subthreads', 'usercp.php?action=subscriptions');
        add_breadcrumb('nav_addsubscription');

        $forumpermissions = forum_permissions($thread['fid']);
        if ($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0
            || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0
                && $thread['uid'] != $mybb->user['uid'])) {
            error_no_permission();
        }

        check_forum_password($thread['fid']);

        $referrer = $server_http_referer ?: '';
        $thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
        $subscribe_to_thread = sprintf($lang->usercp['subscribe_to_thread'], $thread['subject']);

        $notification_none_checked = $notification_email_checked = $notification_pm_checked = '';
        match ($CURUSER['subscriptionmethod']) {
            1, 0    => $notification_none_checked  = 'checked="checked"',
            2       => $notification_email_checked = 'checked="checked"',
            3       => $notification_pm_checked    = 'checked="checked"',
            default => null,
        };

        $plugins->run_hooks('usercp2_addsubscription_thread');
        stdhead($lang->usercp['subscribe_to_thread']);
        build_breadcrumb();
        
        $notif_radio = static fn(string $id, string $value, string $checked, string $icon, string $tone, string $label, string $desc): string =>
            '<label class="st-check" for="' . $id . '">'
            . '<input type="radio" class="form-check-input" name="notification" id="' . $id . '" value="' . $value . '" ' . $checked . ' />'
            . '<span><i class="fa-solid ' . $icon . ' text-' . $tone . ' me-1"></i><strong>' . $label . '</strong>'
            . '<span class="d-block st-meta fw-normal">' . $desc . '</span></span></label>';

        echo ucp_open('fa-bell', 'info', $lang->usercp['subscribe_to_thread'], $thread['subject'])
            . '<form action="usercp.php" method="post" name="input">'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
            . '<input type="hidden" name="action" value="do_addsubscription" />'
            . '<input type="hidden" name="tid" value="' . $thread['tid'] . '" />'
            . ucp_sec_open('fa-bell-concierge', 'info', $lang->usercp['notification_method'])
            .   $notif_radio('notification_none', '0', $notification_none_checked, 'fa-bell-slash', 'secondary',
                    $lang->usercp['no_notification'], $lang->usercp['no_notification_desc'])
            .   $notif_radio('notification_email', '1', $notification_email_checked, 'fa-envelope', 'primary',
                    $lang->usercp['email_notification'], $lang->usercp['email_notification_desc'])
            .   $notif_radio('notification_pm', '2', $notification_pm_checked, 'fa-comment-dots', 'success',
                    $lang->usercp['pm_notification'], $lang->usercp['pm_notification_desc'])
            . ucp_sec_close()
            . '<div class="st-submitbar justify-content-end">'
            .   '<button type="submit" class="btn btn-primary st-pill" name="submit" value="' . $lang->usercp['do_subscribe'] . '" accesskey="s">'
            .     '<i class="fa-solid fa-bell me-2"></i>' . $lang->usercp['do_subscribe']
            .   '</button>'
            . '</div>'
            . '</form>'
            . ucp_close();

        stdfoot();
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_editsig  (save)
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_editsig' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('usercp_do_editsig_start');

    $db->sql_query_prepared("UPDATE users SET signature = ? WHERE id = ?", [$mybb->input['signature'], (int)$CURUSER['id']]);

    $plugins->run_hooks('usercp_do_editsig_end');
    redirect('usercp.php?action=editsig', $lang->usercp['redirect_sigupdated']);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: editsig
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'editsig') {
    $plugins->run_hooks('usercp_editsig_start');

    $error ??= '';

    if (!empty($mybb->input['preview']) && empty($error)) {
    $sig     = $mybb->get_input('signature');
    $heading = $lang->usercp['sig_preview'];
} elseif (empty($error)) {
    $sig     = $CURUSER['signature'];
    $heading = $lang->usercp['current_sig'];
} else {
    $sig     = $mybb->get_input('signature');
    $heading = false;
}

$signature = '';
if ($sig && $heading) {
    $sig_parser = [
        'allow_html'     => 0, 'allow_mycode'    => 1,
        'allow_smilies'  => 1, 'allow_imgcode'   => 1,
        'me_username'    => 1, 'filter_badwords' => 1,
    ];
    $sigpreview = $parser->parse_message($sig, $sig_parser); // сначала парсим

    $signature = '<div class="st-card st-form-card">'
        . '<div class="st-card__head"><span class="st-ico st-soft-info"><i class="fa-solid fa-eye"></i></span><span>' . $heading . '</span></div>'
        . '<div class="st-card__body">' . $sigpreview . '</div></div>';
}

    if ($mybb->user['suspendsignature'] && $mybb->user['suspendsigtime'] > TIMENOW) {
        $plugins->run_hooks('usercp_editsig_end');
        
        $editsig = ucp_open('fa-signature', 'warning', $lang->usercp['edit_sig'])
            . $signature
            . '<div class="st-notice st-soft-danger"><i class="fa-solid fa-ban"></i>'
            .   '<div><strong>' . $lang->usercp['edit_sig_error_title'] . '</strong><br>' . $lang->usercp['edit_sig_no_permission'] . '</div></div>'
            . ucp_close();
	   	
		
		
    } else {
        $smilieinserter = '';
        $sigsmilies  = '1';
        $sigmycode   = '1';
        $sightml     = '0';
        $sigimgcode  = '1';
        $siglength   = '255';

        $sigsmilies  = ($sigsmilies  == 1) ? $lang->usercp['on']  : $lang->usercp['off'];
        $sigmycode   = ($sigmycode   == 1) ? $lang->usercp['on']  : $lang->usercp['off'];
        $sightml     = ($sightml     == 1) ? $lang->usercp['on']  : $lang->usercp['off'];
        $sigimgcode  = ($sigimgcode  == 1) ? $lang->usercp['on']  : $lang->usercp['off'];
        $siglength   = ($siglength   == 0) ? $lang->usercp['unlimited'] : $siglength;

        $sig = htmlspecialchars_uni($sig);

        require_once INC_PATH . '/editor.php';
        require_once 'cache/smilies.php';
        $editor = insert_bbcode_editor($smilies, $BASEURL, 'signature');

        $plugins->run_hooks('usercp_editsig_end');
        
        $sig_radio = static fn(string $id, string $value, string $checked, string $icon, string $tone, string $label, string $hint): string =>
            '<label class="st-check" for="' . $id . '">'
            . '<input type="radio" class="form-check-input" name="updateposts" value="' . $value . '" id="' . $id . '" ' . $checked . ' />'
            . '<span><i class="fa-solid ' . $icon . ' text-' . $tone . ' me-1"></i>' . $label
            . '<span class="d-block st-meta fw-normal">' . $hint . '</span></span></label>';

        $editsig = ucp_open('fa-signature', 'primary', $lang->usercp['edit_sig'], $lang->usercp['hint_sig_sub'])
            . $error
            . $signature
            . '<form action="usercp.php" method="post">'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'

            . '<div class="st-card st-form-card">'
            .   '<div class="p-2 border-bottom">' . $editor['toolbar'] . '</div>'
            .   '<div class="st-card__body">'
            .     '<textarea class="form-control st-editor-text" id="signature" name="signature" placeholder="' . $lang->usercp['ph_signature'] . '">' . $sig . '</textarea>'
            .     '<div class="char-counter st-meta text-end mt-2" id="charCounter"><i class="fa-solid fa-text-height me-1"></i>'
            .       '<span id="charCount">0</span> ' . $lang->usercp['lbl_characters'] . '</div>'
            .   '</div>'
            . '</div>'

            . ucp_sec_open('fa-pen-fancy', 'info', $lang->usercp['lbl_sig_guidelines'])
            .   '<p class="st-hint mt-0 mb-3"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_sig_guidelines'] . '</p>'
            .   $sig_radio('enableSig', 'enable', '', 'fa-circle-check', 'success', $lang->usercp['enable_sig_posts'], $lang->usercp['hint_sig_enable_all'])
            .   $sig_radio('disableSig', 'disable', '', 'fa-circle-xmark', 'danger', $lang->usercp['disable_sig_posts'], $lang->usercp['hint_sig_disable_all'])
            .   $sig_radio('leaveSig', '0', 'checked="checked"', 'fa-clock', 'secondary', $lang->usercp['leave_sig_settings'], $lang->usercp['hint_sig_future'])
            . ucp_sec_close()

            . '<div class="st-submitbar">'
            .   '<input type="hidden" name="action" value="do_editsig" />'
            .   '<button type="submit" class="btn btn-outline-secondary st-pill" name="preview" value="' . $lang->usercp['preview'] . '">'
            .     '<i class="fa-solid fa-eye me-2"></i>' . $lang->usercp['preview']
            .   '</button>'
            .   '<button type="submit" class="btn btn-primary st-pill" name="submit" value="' . $lang->usercp['update_sig'] . '">'
            .     '<i class="fa-solid fa-floppy-disk me-2"></i>' . $lang->usercp['update_sig']
            .   '</button>'
            . '</div>'
            . '</form>'
            . ucp_close()
            . $editor['modal']
            . '<script src="' . $BASEURL . '/scripts/char-counter.js"></script>';
		
		
		
		
    }

    stdhead($lang->usercp['edit_sig']);
    build_breadcrumb();
    echo $editsig;
    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_profile
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_profile' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $plugins->run_hooks('usercp_do_profile_start');

    $bday = [
        'day'   => $mybb->get_input('bday1', MyBB::INPUT_INT),
        'month' => $mybb->get_input('bday2', MyBB::INPUT_INT),
        'year'  => $mybb->get_input('bday3', MyBB::INPUT_INT),
    ];

    $passhint    = $mybb->get_input('passhint', MyBB::INPUT_INT);
    $hintanswer  = trim($mybb->get_input('hintanswer'));
    $secret_errors = [];

    if ($passhint > 0 && $hintanswer === '') {
        $secret_errors[] = $lang->usercp['err_secret_answer'];
    } elseif ($passhint === 0 && $hintanswer !== '') {
        $secret_errors[] = $lang->usercp['err_secret_question'];
    }

    require_once INC_PATH . '/datahandlers/user.php';
    $userhandler = new UserDataHandler('update');
    $userhandler->set_data([
        'uid'               => $CURUSER['id'],
        'postnum'           => $CURUSER['postnum'],
        'usergroup'         => $CURUSER['usergroup'],
        'birthday'          => $bday,
        'birthdayprivacy'   => $mybb->get_input('birthdayprivacy'),
    ]);

    $userhandler_valid  = $userhandler->validate_user();
    $has_secret_errors  = !empty($secret_errors);

    if (!$userhandler_valid || $has_secret_errors) {
        $errors = [];

        if (!$userhandler_valid) {
            $user_errors = $userhandler->get_friendly_errors();
            $errors      = array_merge($errors, is_array($user_errors) ? $user_errors : [$user_errors]);

            $raw_errors = $userhandler->get_errors();
            if (array_key_exists('invalid_birthday_privacy', $raw_errors)
                || array_key_exists('conflicted_birthday_privacy', $raw_errors)) {
                $mybb->input['birthdayprivacy'] = $CURUSER['birthdayprivacy'];
                $bday = explode('-', $CURUSER['birthday']);
                if (isset($bday[2])) { $mybb->input['bday3'] = $bday[2]; }
            }
        }

        $errors = array_merge($errors, $secret_errors);
        $errors = inline_error($errors);
        $mybb->input['action']      = 'profile';
        $mybb->input['passhint']    = $passhint;
        $mybb->input['hintanswer']  = $hintanswer;
    } else {
        $userhandler->update_user();

        if ($passhint > 0 && $hintanswer !== '') {
            $hashed_answer = md5($hintanswer);
            $query = $db->sql_query_prepared("SELECT userid FROM ts_secret_questions WHERE userid = ?", [(int)$CURUSER['id']]);
            if ($query && $db->num_rows($query) > 0) {
                $db->sql_query_prepared(
                    "UPDATE ts_secret_questions SET passhint = ?, hintanswer = ? WHERE userid = ?",
                    [$passhint, $hashed_answer, (int)$CURUSER['id']]
                );
            } else {
                $db->sql_query_prepared(
                    "INSERT INTO ts_secret_questions (`userid`,`passhint`,`hintanswer`) VALUES (?,?,?)",
                    [(int)$CURUSER['id'], $passhint, $hashed_answer]
                );
            }
        }

        $plugins->run_hooks('usercp_do_profile_end');
        redirect('usercp.php?action=profile', $lang->usercp['redirect_profileupdated']);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: removesubscriptions
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'removesubscriptions') {
    verify_post_check($mybb->get_input('my_post_key'));

    if ($mybb->get_input('type') === 'forum') {
        $plugins->run_hooks('usercp2_removesubscriptions_forum');
        $db->sql_query_prepared("DELETE FROM forumsubscriptions WHERE uid = ?", [(int)$CURUSER['id']]);
        $url = $server_http_referer ?: 'usercp.php?action=forumsubscriptions';
        redirect($url, $lang->usercp['redirect_forumsubscriptionsremoved']);
    } else {
        $plugins->run_hooks('usercp2_removesubscriptions_thread');
        $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE uid = ?", [(int)$CURUSER['id']]);
        $url = $server_http_referer ?: 'usercp.php?action=subscriptions';
        redirect($url, $lang->usercp['redirect_subscriptionsremoved']);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_subscriptions
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_subscriptions') {
    verify_post_check($mybb->get_input('my_post_key'));

    if (!isset($mybb->input['check']) || !is_array($mybb->input['check'])) {
        error($lang->usercp['no_subscriptions_selected']);
    }

    $plugins->run_hooks('usercp_do_subscriptions_start');
    $mybb->input['check'] = array_map('intval', $mybb->get_input('check', MyBB::INPUT_ARRAY));
    $tids = implode(',', $mybb->input['check']);

    if ($mybb->get_input('do') === 'delete') {
        $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE tid IN ($tids) AND uid = ?", [(int)$CURUSER['id']]);
    } else {
        $new_notification = match ($mybb->get_input('do')) {
            'email_notification' => 1,
            'pm_notification'    => 2,
            default              => 0,
        };
        $db->sql_query_prepared("UPDATE threadsubscriptions SET notification = ? WHERE tid IN ($tids) AND uid = ?", [$new_notification, (int)$CURUSER['id']]);
    }

    redirect('usercp.php?action=subscriptions', $lang->usercp['redirect_subscriptions_updated']);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: subscriptions
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'subscriptions') {
    $plugins->run_hooks('usercp_subscriptions_start');

    $where_parts = ["s.uid=?"];
    $where_params = [(int)$CURUSER['id']];
    if ($unviewable_forums = get_unviewable_forums(true)) {
        $where_parts[] = "t.fid NOT IN ({$unviewable_forums})";
    }
    if ($inactive_forums = get_inactive_forums()) {
        $where_parts[] = "t.fid NOT IN ({$inactive_forums})";
    }
    $where = implode(' AND ', $where_parts);

    $query        = $db->sql_query_prepared("SELECT COUNT(s.tid) as threads FROM threadsubscriptions s LEFT JOIN threads t ON (t.tid=s.tid) WHERE {$where}", $where_params);
    $threadcount  = $query ? (int) $db->fetch_field($query, 'threads') : 0;
    $perpage      = ($f_threadsperpage && (int) $f_threadsperpage >= 1) ? (int) $f_threadsperpage : 20;

    $page  = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start = ($page - 1) * $perpage;
    $pages = (int) ceil($threadcount / $perpage);
    if ($page > $pages) { $start = 0; $page = 1; }

    $multipage    = multipage($threadcount, $perpage, $page, 'usercp.php?action=subscriptions');
    $fpermissions = forum_permissions();
    $del_subs     = $subscriptions = [];

    $query = $db->sql_query_prepared("
        SELECT s.*, s.dateline AS subscription_dateline, t.*, t.username AS threadusername,
               u.username, u.username AS author,
               f.name AS forumname
        FROM threadsubscriptions s
        LEFT JOIN threads t ON (s.tid=t.tid)
        LEFT JOIN users u ON (u.id=t.uid)
        LEFT JOIN forums f ON (f.fid=t.fid)
        WHERE {$where}
        ORDER BY t.lastpost DESC
        LIMIT {$start}, {$perpage}
    ", $where_params);

    while ($query && ($subscription = $db->fetch_array($query))) {
        $fp = $fpermissions[$subscription['fid']];
        if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] != 0 && $subscription['uid'] != $mybb->user['id']) {
            $del_subs[] = $subscription['sid'];
        } elseif ($subscription['tid']) {
            $subscriptions[$subscription['tid']] = $subscription;
        }
    }

    if ($del_subs) {
        $sids = implode(',', array_map('intval', $del_subs));
        $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE sid IN ({$sids}) AND uid = ?", [(int)$CURUSER['id']]);
        $threadcount = max(0, $threadcount - count($del_subs));
    }

    $threads = '';
    if (!empty($subscriptions)) {
        $tids = implode(',', array_keys($subscriptions));

        // Forum read times
        $readforums = [];
        $q = $db->sql_query_prepared("
            SELECT f.fid, fr.dateline AS lastread
            FROM forums f
            LEFT JOIN forumsread fr ON (fr.fid=f.fid AND fr.uid=?)
            WHERE f.active != 0 ORDER BY pid, disporder
        ", [(int)$CURUSER['id']]);
        while ($q && ($forum = $db->fetch_array($q))) {
            $readforums[$forum['fid']] = $forum['lastread'];
        }

        // Dot icons
        if ((int) ($dotfolders ?? 1) !== 0) {
            $q = $db->sql_query_prepared("SELECT tid,uid FROM posts WHERE uid=? AND tid IN ({$tids})", [(int)$CURUSER['id']]);
            while ($q && ($post = $db->fetch_array($q))) {
                $subscriptions[$post['tid']]['doticon'] = 1;
            }
        }

        // Thread read times
        $threadreadcut = (int) ($threadreadcut ?? 7);
        if ($threadreadcut > 0) {
            $q = $db->sql_query_prepared("SELECT * FROM threadsread WHERE uid=? AND tid IN ({$tids})", [(int)$CURUSER['id']]);
            while ($q && ($rt = $db->fetch_array($q))) {
                $subscriptions[$rt['tid']]['lastread'] = $rt['dateline'];
            }
        }

        $icon_cache = $cache->read('posticons');

        foreach ($subscriptions as $thread) {
            $subscription_date = !empty($thread['subscription_dateline'])
                ? date('d.m.Y', (int)$thread['subscription_dateline'])
                : '—';
           
            $prefix   = '';
            $thread['threadprefix'] = '';

            $thread['subject']    = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
            $thread['threadlink'] = get_thread_link($thread['tid']);
            $thread['lastpostlink'] = get_thread_link($thread['tid'], 0, 'lastpost');

            $icon = (($thread['icon'] ?? 0) > 0 && ($icon_cache[$thread['icon'] ?? 0] ?? false))
                ? '<img src="' . htmlspecialchars_uni(str_replace('{theme}', $theme['imgdir'], $icon_cache[$thread['icon']]['path'])) . '" alt="">'
                : '&nbsp;';

            $folder = $folder_label = '';
            if (isset($thread['doticon'])) { $folder = 'dot_'; $folder_label = 'icon_dot'; }

            $read_cutoff  = TIMENOW - $threadreadcut * 86400;
            $forum_read   = (empty($readforums[$thread['fid']]) || $readforums[$thread['fid']] < $read_cutoff)
                ? $read_cutoff : $readforums[$thread['fid']];
            $cutoff = ($threadreadcut > 0 && $thread['lastpost'] > $forum_read) ? TIMENOW - $threadreadcut * 86400 : 0;

            $lastread = 0;
            if ($thread['lastpost'] > $cutoff) {
                $lastread = !empty($thread['lastread']) ? $thread['lastread'] : 1;
            }
            if (!$lastread) {
                $readcookie = my_get_array_cookie('threadread', $thread['tid']);
                $lastread   = ($readcookie > $forum_read) ? $readcookie : $forum_read;
            }

            $gotounread = '';
            if ($lastread && $lastread < $thread['lastpost']) {
                $folder      .= 'new';
                $folder_label .= $lang->usercp['icon_new'];
                $new_class    = 'subject_new';
                $thread['newpostlink'] = get_thread_link($thread['tid'], 0, 'newpost');
                $gotounread   = '<a href="' . $thread['newpostlink'] . '"><i class="fas fa-chevron-right text-primary" title="' . $lang->usercp['goto_first_unread'] . '"></i></a>';
            } else {
                $folder_label .= $lang->usercp['icon_no_new'];
                $new_class    = 'subject_old';
            }

            $hottopic      = 20;
            $hottopicviews = 150;
            if ($thread['replies'] >= $hottopic || $thread['views'] >= $hottopicviews) { $folder .= 'hot'; $folder_label .= 'icon_hot'; }
            if ($thread['closed'] == 1) { $folder .= 'close'; $folder_label .= 'icon_close'; }
            $folder .= 'folder';

            //if ($thread['visible'] == 0) { $bgcolor = 'trow_shaded'; }

            $lastpostdate = my_datee($dateformat, $thread['lastpost']) . ', ' . my_datee($timeformat, $thread['lastpost']);

            $lastposteruid  = $thread['lastposteruid'];
            $lastposter     = htmlspecialchars_uni($thread['lastposter'] ?: 'guest');
            $lastposterlink = ($lastposteruid == 0) ? $lastposter : build_profile_link($lastposter, $lastposteruid);

            $thread['replies'] = ts_nf($thread['replies']);
            $thread['views']   = ts_nf($thread['views']);

            $notification_type = match ((string) $thread['notification']) {
                '2'     => $lang->usercp['pm_notification'],
                '1'     => $lang->usercp['email_notification'],
                default => $lang->usercp['no_notification'],
            };

           
			
            $threads .= '<div class="subscription-card st-sub">'
                . '<input type="checkbox" class="form-check-input subscription-checkbox" name="check[' . $thread['tid'] . ']" value="' . $thread['tid'] . '" id="thread_' . $thread['tid'] . '">'
                . '<div class="min-w-0">'
                .   '<div class="st-frow__top">' . $gotounread
                .     '<a href="' . $thread['threadlink'] . '" class="st-frow__title">' . $thread['subject'] . '</a>' . $icon . '</div>'
                .   '<div class="st-frow__top mt-1">'
                .     '<span class="st-badge st-soft-info" title="' . htmlspecialchars_uni($lang->usercp['notification_method']) . '"><i class="fa-solid fa-bell"></i>' . $notification_type . '</span>'
                .     '<span class="st-meta"><i class="fa-solid fa-folder me-1"></i>' . htmlspecialchars_uni($thread['forumname'] ?? '—')
                .       ' &middot; <i class="fa-solid fa-user me-1"></i>' . htmlspecialchars_uni($thread['author'] ?? '—')
                .       ' &middot; <i class="fa-solid fa-calendar-plus me-1"></i>' . $subscription_date . '</span>'
                .   '</div>'
                . '</div>'
                . '<div class="st-sub__side">'
                .   '<div class="st-sub__stats">'
                .     '<div class="st-fstat"><span class="st-fstat__val">' . $thread['replies'] . '</span><span class="st-fstat__lbl">' . $lang->usercp['replies'] . '</span></div>'
                .     '<div class="st-fstat"><span class="st-fstat__val">' . $thread['views'] . '</span><span class="st-fstat__lbl">' . $lang->usercp['views'] . '</span></div>'
                .   '</div>'
                .   '<div class="st-frow__last-date">'
                .     '<div><i class="fa-solid fa-circle-user me-1"></i>' . $lastposterlink . '</div>'
                .     '<div>' . $lastpostdate . '</div>'
                .   '</div>'
                . '</div>'
                . '</div>';
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
        }

        $gobutton = '<button type="submit" class="btn btn-sm btn-primary st-pill" value="Go"><i class="fa-solid fa-shuffle me-1"></i>' . $lang->usercp['btn_go'] . '</button>';

        $remove_options = '<div class="d-flex flex-wrap align-items-center gap-2 ms-auto">'
            . '<a href="usercp.php?action=removesubscriptions&amp;my_post_key=' . $mybb->post_code . '" class="btn btn-sm btn-outline-danger st-pill">'
            .   '<i class="fa-solid fa-xmark me-1"></i>' . $lang->usercp['remove_all_subscriptions'] . '</a>'
            . '<select name="do" class="form-select form-select-sm w-auto">'
            .   '<option value="delete">' . $lang->usercp['delete_subscriptions'] . '</option>'
            .   '<option value="no_notification">' . $lang->usercp['update_no_notification'] . '</option>'
            .   '<option value="email_notification">' . $lang->usercp['update_email_notification'] . '</option>'
            .   '<option value="pm_notification">' . $lang->usercp['update_pm_notification'] . '</option>'
            . '</select>'
            . $gobutton
            . '</div>';
    } else {
        $remove_options = '';
        $threads = '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-solid fa-bell-slash"></i></div>'
            . $lang->usercp['no_thread_subscriptions'] . '</div>';
    }

    $plugins->run_hooks('usercp_subscriptions_end');
    stdhead($lang->usercp['subscriptions']);
    build_breadcrumb();
    
	
	
    echo ucp_open('fa-bell', 'success', $lang->usercp['subscriptions'])
        . '<form action="usercp.php" method="post" name="input" id="subscriptionsForm">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '<input type="hidden" name="action" value="do_subscriptions" />'
        . '<div class="st-card mb-3 overflow-hidden">'
        .   '<div class="st-card__head">'
        .     '<span class="st-ico st-soft-success"><i class="fa-solid fa-bell"></i></span>'
        .     '<span>' . $lang->usercp['subscriptions'] . '</span>'
        .     '<span class="st-badge st-soft-success">' . $threadcount . '</span>'
        .     '<label class="ms-auto d-flex align-items-center gap-2 mb-0 st-meta" for="subs_allbox">' . $lang->usercp['lbl_select_all']
        .       '<input name="allbox" id="subs_allbox" type="checkbox" class="form-check-input checkall" value="1"></label>'
        .   '</div>'
        .   '<div id="subscriptionsList">' . $threads . '</div>'
        .   '<div id="noSubscriptions" class="st-empty d-none">'
        .     '<div class="st-ico st-soft-muted"><i class="fa-solid fa-bell-slash"></i></div>'
        .     '<strong>' . $lang->usercp['lbl_no_subs_title'] . '</strong><div>' . $lang->usercp['hint_no_subs'] . '</div>'
        .   '</div>'
        .   ($remove_options !== ''
                ? '<div class="st-optbar justify-content-between">'
                .   '<div class="selected-count st-meta d-none">' . $lang->usercp['lbl_selected'] . ' <span id="selectedCount">0</span></div>'
                .   $remove_options
                . '</div>'
                : '')
        . '</div>'
        . '</form>'
        . '<div class="st-pager d-flex justify-content-center">' . $multipage . '</div>'
        . ucp_close()
        . $swal_assets
        . ucp_js_lang([
            'select_subscription' => 'no_subscriptions_selected',
            'confirm_unsubscribe' => 'js_confirm_unsubscribe',
            'close' => 'js_close',
        ])
        . '<script type="text/javascript" src="' . $BASEURL . '/scripts/subscription.js?ver=31"></script>'
        . '<script type="text/javascript" src="' . $BASEURL . '/scripts/subscriptions.js?ver=31"></script>';

    stdfoot();
    exit;
	
	
	
	
	
	
	
	
	
	
	
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: removesubscription
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'removesubscription'
    && ($mybb->request_method === 'post' || verify_post_check($mybb->get_input('my_post_key'), true))) {

    verify_post_check($mybb->get_input('my_post_key'));

    if ($mybb->get_input('type') === 'forum') {
        $forum = get_forum($mybb->get_input('fid', MyBB::INPUT_INT));
        if (!$forum) { error('error_invalidforum'); }
        $plugins->run_hooks('usercp2_removesubscription_forum');
        remove_subscribed_forum($forum['fid']);
        $url = ($server_http_referer && $mybb->request_method !== 'post') ? $server_http_referer : 'usercp.php?action=forumsubscriptions';
        redirect($url, $lang->usercp['redirect_forumsubscriptionremoved']);
    } else {
        $thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
        if (!$thread) { error('error_invalidthread'); }
        $plugins->run_hooks('usercp2_removesubscription_thread');
        remove_subscribed_thread($thread['tid']);
        $url = ($server_http_referer && $mybb->request_method !== 'post') ? $server_http_referer : 'usercp.php?action=subscriptions';
        redirect($url, $lang->usercp['redirect_subscriptionremoved']);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: forumsubscriptions
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'forumsubscriptions') {
    $plugins->run_hooks('usercp_forumsubscriptions_start');

    $readforums = [];
    $q = $db->sql_query_prepared("
        SELECT f.fid, fr.dateline AS lastread
        FROM forums f
        LEFT JOIN forumsread fr ON (fr.fid=f.fid AND fr.uid=?)
        WHERE f.active != 0 ORDER BY pid, disporder
    ", [(int)$CURUSER['id']]);
    while ($q && ($forum = $db->fetch_array($q))) {
        $readforums[$forum['fid']] = $forum['lastread'];
    }

    $fpermissions = forum_permissions();
    require_once INC_PATH . '/functions_forumlist.php';

    $uid   = (int) $CURUSER['id'];
    $query = $db->sql_query_prepared("
        SELECT fs.*, f.*, t.subject AS lastpostsubject, fr.dateline AS lastread
        FROM forumsubscriptions fs
        LEFT JOIN forums f ON (f.fid=fs.fid)
        LEFT JOIN threads t ON (t.tid=f.lastposttid)
        LEFT JOIN forumsread fr ON (fr.fid=f.fid AND fr.uid=?)
        WHERE f.type='f' AND fs.uid=?
        ORDER BY f.name ASC", [$uid, $uid]);

    $forums = '';
    while ($forum = $db->fetch_array($query)) {
        $fp = $fpermissions[$forum['fid']];
        if ($fp['canview'] == 0 || $fp['canviewthreads'] == 0) { continue; }

        $lightbulb = get_forum_lightbulb(['open' => $forum['open'], 'lastread' => $forum['lastread']], ['lastpost' => $forum['lastpost']]);
        $folder    = $lightbulb['folder'];

        [$posts, $threads] = (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] != 0)
            ? ['-', '-']
            : [ts_nf($forum['posts']), ts_nf($forum['threads'])];

        $last_cls = '';
        if ($forum['lastpost'] == 0) {
            $last_cls = ' st-frow__last--muted';
            $lastpost = '<span class="st-ico st-soft-muted"><i class="fa-solid fa-user"></i></span>'
                . '<div class="st-frow__last-text">' . $lang->usercp['lastpost_never'] . '</div>';

        } elseif (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] != 0 && $forum['lastposteruid'] != $CURUSER['id']) {
            $last_cls = ' st-frow__last--muted';
            $lastpost = '<span class="st-ico st-soft-muted"><i class="fa-solid fa-lock"></i></span>'
                . '<div class="st-frow__last-text">' . $lang->global['lastvisit_hidden'] . '</div>';

        } else {
            $forum['lastpostsubject'] = htmlspecialchars_uni($parser->parse_badwords($forum['lastpostsubject']));
            $lastpost_date       = my_datee('relative', $forum['lastpost']);
            $lastposter          = htmlspecialchars_uni($forum['lastposter'] ?: $lang->guest);
            $lastpost_profilelink = ($forum['lastposteruid'] == 0)
                ? $lastposter
                : build_profile_link($lastposter, $forum['lastposteruid']);
            $full_lastpost_subject = $lastpost_subject = htmlspecialchars_uni($forum['lastpostsubject']);
            if (mb_strlen($lastpost_subject) > 25) {
                $lastpost_subject = mb_substr($lastpost_subject, 0, 25) . '...';
            }
            $lastpost_link = get_thread_link($forum['lastposttid'], 0, 'lastpost');

            $lastpost = '<avatarep_uid_[' . $forum['lastposteruid'] . ']>'
                . '<div class="st-frow__last-text">'
                .   '<a class="st-frow__last-subj" href="' . $lastpost_link . '" title="' . $full_lastpost_subject . '">' . $lastpost_subject . '</a>'
                .   '<div class="st-frow__last-date">' . $lastpost_date . ' &middot; ' . $lang->global['by'] . ' ' . $lastpost_profilelink . '</div>'
                . '</div>';
        }

        $showdescriptions = '1';
        if ($showdescriptions == 0) { $forum['description'] = ''; }
		
		$forum_url = get_forum_link($forum['fid']);

        $forums .= '<div class="st-frow">'
            . '<span class="st-ico st-frow__ico st-soft-primary"><i class="fa-solid fa-comments"></i></span>'
            . '<div class="st-frow__body">'
            .   '<div class="st-frow__top"><a class="st-frow__title" href="' . $forum_url . '">' . $forum['name'] . '</a></div>'
            .   '<div class="st-frow__desc"><a class="text-danger text-decoration-none" href="usercp.php?action=removesubscription&amp;type=forum&amp;fid=' . $forum['fid'] . '&amp;my_post_key=' . $mybb->post_code . '">'
            .     '<i class="fa-solid fa-bell-slash me-1"></i>' . $lang->usercp['unsubscribe'] . '</a></div>'
            .   '<div class="st-frow__inline"><span><i class="fa-solid fa-layer-group"></i>' . $threads . '</span><span><i class="fa-solid fa-message"></i>' . $posts . '</span></div>'
            . '</div>'
            . '<div class="st-frow__stats">'
            .   '<div class="st-fstat"><span class="st-fstat__val">' . $threads . '</span><span class="st-fstat__lbl">' . $lang->usercp['threads'] . '</span></div>'
            .   '<div class="st-fstat"><span class="st-fstat__val">' . $posts . '</span><span class="st-fstat__lbl">' . $lang->usercp['posts'] . '</span></div>'
            . '</div>'
            . '<div class="st-frow__last' . $last_cls . '">' . $lastpost . '</div>'
            . '</div>';

    }

    if (!$forums) {
        
		
		
        $forums = '<div class="st-empty">'
            . '<div class="st-ico st-soft-muted"><i class="fa-regular fa-folder-open"></i></div>'
            . '<strong>' . $lang->usercp['no_forum_subscriptions'] . '</strong>'
            . '<div class="st-meta mt-1"><i class="fa-regular fa-bell-slash me-1"></i>' . $lang->usercp['hint_no_forum_subs'] . '</div>'
            . '<a href="' . $BASEURL . '/index2.php" class="btn btn-sm btn-outline-primary st-pill mt-3">'
            .   '<i class="fa-regular fa-compass me-1"></i>' . $lang->usercp['btn_browse_forums'] . '</a>'
            . '</div>';
		
		
    }

    $plugins->run_hooks('usercp_forumsubscriptions_end');
    stdhead($lang->usercp['forum_subscriptions']);
    build_breadcrumb();
    
	
	
    echo ucp_open('fa-eye', 'info', $lang->usercp['forum_subscriptions'])
        . '<div class="st-card st-fcat mb-3"><div class="st-fcat__body">' . $forums . '</div></div>'
        . ucp_close();

    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: Bookmarks (AJAX removes)
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_removebookmarks') {
    verify_post_check($mybb->get_input('my_post_key'));

    header('Content-Type: application/json');

    if (!isset($mybb->input['check']) || !is_array($mybb->input['check'])) {
        echo json_encode(['status' => 'error', 'message' => $lang->usercp['no_books_selected']]);
        exit;
    }

    $mybb->input['check'] = array_map('intval', $mybb->get_input('check', MyBB::INPUT_ARRAY));
    $tids = implode(',', $mybb->input['check']);

    if ($mybb->get_input('do') === 'delete') {
        $db->sql_query_prepared("DELETE FROM bookmarks WHERE torrentid IN ($tids) AND userid = ?", [(int)$CURUSER['id']]);
    }

    echo json_encode(['status' => 'success', 'message' => $lang->usercp['redirect_bookmark_updated']]);
    exit;
}

if ($mybb->input['action'] === 'removebookmarks') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('usercp2_removesubscriptions_thread');
    $db->sql_query_prepared("DELETE FROM bookmarks WHERE userid = ?", [(int)$CURUSER['id']]);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => $lang->usercp['redirect_bookmarkremoved']]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: bookmarks
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'bookmarks') {
    $plugins->run_hooks('usercp_bookmarks_start');

    $uid        = (int) $CURUSER['id'];
    $count_q    = $db->sql_query_prepared('SELECT COUNT(*) as total FROM bookmarks WHERE userid=?', [$uid]);
    $total      = (int) $db->fetch_field($count_q, 'total');
    $perpage    = 12;
    $page       = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start      = ($page - 1) * $perpage;
    $multipage  = multipage($total, $perpage, $page, 'usercp.php?action=bookmarks');

    $query = $db->sql_query_prepared(
        'SELECT b.*, t.name, t.added, t.seeders, t.leechers, t.t_image FROM bookmarks b LEFT JOIN torrents t ON t.id=b.torrentid WHERE b.userid=? LIMIT ?,?',
        [$uid, $start, $perpage]
    );



    $bookmark_cards = '';
    $counter = 0;

    if ($db->num_rows($query) > 0) {
        while ($bm = $db->fetch_array($query)) {
            $torrent_link = get_torrent_link($bm['torrentid']);
            $image        = !empty($bm['t_image']) ? htmlspecialchars_uni($bm['t_image']) : 'default_torrent.png';
            $name         = cutename($bm['name'], 60);
            $delay        = $counter * 0.05;

            $bookmark_cards .= '<div class="col-xl-4 col-lg-6 col-md-6 st-bm-col">'
                . '<div class="st-card st-bm h-100" style="animation-delay:' . $delay . 's;">'
                .   '<a href="' . $torrent_link . '" class="st-bm__thumb">'
                .     '<img src="' . $image . '" class="st-bm__img" alt="' . $name . '" loading="lazy">'
                .     '<span class="st-bm__title">' . $name . '</span>'
                .   '</a>'
                .   '<div class="st-bm__foot">'
                .     '<div class="d-flex align-items-center gap-2">'
                .       '<span class="st-badge st-soft-success"><i class="fa-solid fa-arrow-up"></i>' . $bm['seeders'] . '</span>'
                .       '<span class="st-badge st-soft-danger"><i class="fa-solid fa-arrow-down"></i>' . $bm['leechers'] . '</span>'
                .       '<input type="checkbox" class="form-check-input ms-auto" name="check[' . $bm['torrentid'] . ']" value="' . $bm['torrentid'] . '">'
                .     '</div>'
                .     '<div class="st-meta mt-2"><i class="fa-regular fa-calendar me-1"></i>' . my_datee($dateformat, $bm['added'])
                .       ' <i class="fa-regular fa-clock ms-2 me-1"></i>' . my_datee($timeformat, $bm['added']) . '</div>'
                .   '</div>'
                . '</div>'
                . '</div>';
            $counter++;
        }

        $remove_options = '<div class="st-submitbar">'
            . '<button type="button" class="btn btn-outline-danger st-pill" data-bs-toggle="modal" data-bs-target="#confirmDeleteSelectedModal">'
            .   '<i class="fa-solid fa-trash me-1"></i>' . $lang->usercp['btn_delete_selected'] . '</button>'
            . '<button type="button" class="btn btn-outline-danger st-pill" data-bs-toggle="modal" data-bs-target="#confirmDeleteAllModal">'
            .   '<i class="fa-solid fa-xmark me-1"></i>' . $lang->usercp['btn_remove_all'] . '</button>'
            . '</div>';
    } else {
        $bookmark_cards = '<div class="col-12"><div class="st-empty">'
            . '<div class="st-ico st-soft-muted"><i class="fa-regular fa-bookmark"></i></div>'
            . '<strong>' . $lang->usercp['lbl_bookmarks_empty'] . '</strong>'
            . '<div class="st-meta mt-1">' . $lang->usercp['hint_bookmarks_empty'] . '</div>'
            . '<a href="browse.php" class="btn btn-sm btn-primary st-pill mt-3"><i class="fa-solid fa-magnifying-glass me-1"></i>' . $lang->usercp['btn_browse_torrents'] . '</a>'
            . '</div></div>';
        $remove_options = '';
    }

    $post_code   = $mybb->post_code;
    $multipage_h = $multipage ? '<div class="st-pager d-flex justify-content-center mt-3">' . $multipage . '</div>' : '';

    $bm_subtitle    = sprintf($lang->usercp['hint_bookmarks_sub'], $total);
    $bm_confirm_sel = sprintf($lang->usercp['hint_confirm_delete_bm'], '<span id="selectedCount">0</span>');

    $ucp_head = ucp_open('fa-bookmark', 'danger', $lang->usercp['lbl_my_bookmarks'], $bm_subtitle);
    $ucp_tail = ucp_close();

    $bookmarks_html = <<<HTML
{$ucp_head}
            <form method="post" action="usercp.php" id="bookmarksForm">
                <input type="hidden" name="my_post_key" value="{$post_code}">
                <input type="hidden" name="action" value="do_removebookmarks">
                <div class="row g-4">{$bookmark_cards}</div>
                {$remove_options}
                {$multipage_h}
            </form>
{$ucp_tail}

<div class="modal fade" id="confirmDeleteSelectedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">{$lang->usercp['lbl_confirm_deletion']}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">{$bm_confirm_sel}</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{$lang->usercp['cancel']}</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteSelectedBtn">{$lang->usercp['btn_yes_delete']}</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmDeleteAllModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">{$lang->usercp['lbl_confirm_deletion']}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">{$lang->usercp['hint_confirm_remove_all_bm']}</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{$lang->usercp['cancel']}</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteAllBtn">{$lang->usercp['btn_yes_remove_all']}</button>
      </div>
    </div>
  </div>
</div>
HTML;

    ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form           = document.getElementById('bookmarksForm');
    const cardsContainer = form.querySelector('.row.g-4');
    const postCode       = <?= json_encode($mybb->post_code) ?>;

    function showToast(message, ok = true) {
        const el  = document.getElementById('toastNotification');
        const msg = document.getElementById('toastMessage');
        if (!el || !msg) return;
        el.className = `toast align-items-center text-bg-${ok ? 'success' : 'danger'} border-0`;
        msg.textContent = message;
        new bootstrap.Toast(el, { delay: 3000 }).show();
    }

    function showEmptyState() {
        cardsContainer.innerHTML = `
        <div class="col-12"><div class="st-empty">
            <div class="st-ico st-soft-muted"><i class="fa-regular fa-bookmark"></i></div>
            <strong><?= htmlspecialchars_uni($lang->usercp['lbl_bookmarks_empty']) ?></strong>
            <div class="st-meta mt-1"><?= htmlspecialchars_uni($lang->usercp['hint_bookmarks_empty']) ?></div>
            <a href="browse.php" class="btn btn-sm btn-primary st-pill mt-3">
                <i class="fa-solid fa-magnifying-glass me-1"></i> <?= htmlspecialchars_uni($lang->usercp['btn_browse_torrents']) ?>
            </a>
        </div></div>`;
        document.querySelector('.st-submitbar')?.remove();
    }

    document.querySelector('[data-bs-target="#confirmDeleteSelectedModal"]')?.addEventListener('click', function () {
        const count = form.querySelectorAll('input[type="checkbox"]:checked').length;
        if (!count) { showToast(<?= json_encode($lang->usercp['js_select_bookmark'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, false); return; }
        document.getElementById('selectedCount').textContent = count;
        new bootstrap.Modal(document.getElementById('confirmDeleteSelectedModal')).show();
    });

    document.getElementById('confirmDeleteSelectedBtn')?.addEventListener('click', function () {
        const fd = new FormData(form);
        fd.set('do', 'delete');
        fetch('usercp.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    form.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                        const card = cb.closest('.st-bm-col');
                        card.style.transition = 'all .5s ease';
                        card.style.opacity    = '0';
                        card.style.transform  = 'scale(.95)';
                        setTimeout(() => card.remove(), 500);
                    });
                    bootstrap.Modal.getInstance(document.getElementById('confirmDeleteSelectedModal'))?.hide();
                    showToast(d.message, true);
                    setTimeout(() => { if (!cardsContainer.children.length) showEmptyState(); }, 600);
                } else { showToast(d.message, false); }
            });
    });

    document.getElementById('confirmDeleteAllBtn')?.addEventListener('click', function () {
        const fd = new FormData();
        fd.append('action', 'removebookmarks');
        fd.append('my_post_key', postCode);
        fetch('usercp.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    document.querySelectorAll('.st-bm-col').forEach(c => {
                        c.style.transition = 'all .5s ease';
                        c.style.opacity    = '0';
                        setTimeout(() => c.remove(), 500);
                    });
                    bootstrap.Modal.getInstance(document.getElementById('confirmDeleteAllModal'))?.hide();
                    showToast(d.message, true);
                    setTimeout(showEmptyState, 600);
                } else { showToast(d.message, false); }
            });
    });
});
</script>
    <?php

    stdhead($lang->usercp['lbl_my_bookmarks'] . ' - ' . $SITENAME);
    build_breadcrumb();
    echo $bookmarks_html;
    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: manage_files
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'manage_files') {
    $plugins->run_hooks('usercp_manage_files_start');

    $uid       = (int) $CURUSER['id'];
    $count_q   = $db->sql_query_prepared("SELECT COUNT(*) as files_count FROM comment_files WHERE user_id=?", [$uid]);
    $filecount = $count_q ? (int) $db->fetch_field($count_q, 'files_count') : 0;
    $perpage   = max(1, (int) ($ts_perpage ?? 15));
    $page      = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start     = ($page - 1) * $perpage;
    $multipage = multipage($filecount, $perpage, $page, 'usercp.php?action=manage_files');

    $query = $db->sql_query_prepared("
        SELECT cf.*, c.torrent AS torrentid, p.pid AS postid, p.tid AS thread_id, p.subject AS post_subject
        FROM comment_files AS cf
        LEFT JOIN comments AS c ON c.id=cf.comment_id
        LEFT JOIN posts AS p ON p.pid=cf.post_id
        WHERE cf.user_id=?
        ORDER BY cf.uploaded_at DESC LIMIT ?,?", [$uid, $start, $perpage]);

    if ($db->num_rows($query) > 0) {
        $filelist = '<div class="row g-4">';
        while ($file = $db->fetch_array($query)) {
            $file_size      = mksize($file['file_size']);
            $file_type_icon = get_file_type_icon($file['file_type']);
            $postlink       = get_post_link((int)$file['postid'], (int)$file['thread_id']);

            // Build badge links inline
            $badge_comment = $file['comment_id']
                ? '<a href="' . get_comment_link($file['comment_id'], $file['torrentid']) . '#pid' . $file['comment_id'] . '" class="st-badge st-soft-success"><i class="fa-solid fa-comment me-1"></i>' . sprintf($lang->usercp['lbl_badge_comment'], $file['comment_id']) . '</a>'
                : '';
            $badge_news = $file['news_id']
                ? '<a href="news.php?id=' . $file['news_id'] . '" class="st-badge st-soft-warning"><i class="fa-solid fa-newspaper me-1"></i>' . sprintf($lang->usercp['lbl_badge_news'], $file['news_id']) . '</a>'
                : '';
            $badge_torrent = $file['torrent_id']
                ? '<a href="' . get_torrent_link($file['torrent_id']) . '" class="st-badge st-soft-info"><i class="fa-solid fa-download me-1"></i>' . sprintf($lang->usercp['lbl_badge_torrent'], $file['torrent_id']) . '</a>'
                : '';
            $badge_post = $file['postid']
                ? '<a href="' . $postlink . '#pid' . $file['postid'] . '" class="st-badge st-soft-primary"><i class="fa-solid fa-file-lines me-1"></i>' . sprintf($lang->usercp['lbl_badge_post'], $file['postid']) . '</a>'
                : '';
            $badge_msg = $file['messages_id']
                ? '<a href="messages.php?id=' . $file['messages_id'] . '" class="st-badge st-soft-secondary"><i class="fa-solid fa-envelope-open-text me-1"></i>' . sprintf($lang->usercp['lbl_badge_message'], $file['messages_id']) . '</a>'
                : '';

            $fn_safe   = htmlspecialchars_uni($file['file_name']);
            $fn_cut    = htmlspecialchars_uni(cutename($file['file_name'], 40));
            $file_url  = htmlspecialchars_uni($file['file_url']);
            $preview   = get_file_preview($file);
            $fid_val   = $file['id'];

            $filelist .= <<<HTML
            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6">
                <div class="st-card file-card h-100">
                    <div class="st-card__body">
                        <div class="file-checkbox">
                            <input type="checkbox" class="form-check-input file-checkbox-input" name="file_ids[]" value="{$fid_val}" id="file_{$fid_val}">
                            <label for="file_{$fid_val}" class="file-checkbox-label"></label>
                        </div>
                        <div class="file-preview-wrapper text-center mb-3">{$preview}</div>
                        <div class="file-info text-center mb-3 position-relative">
                            <h6 class="file-name text-truncate mb-2 fw-semibold" title="{$fn_safe}">{$fn_cut}</h6>
                            <div class="file-meta d-flex justify-content-center align-items-center gap-3 text-muted small mb-2">
                                <span class="file-size"><i class="fa-solid fa-weight-hanging me-1"></i>{$file_size}</span>
                                <span class="file-type">{$file_type_icon}</span>
                            </div>
                            <div class="file-date small text-muted"><i class="fa-regular fa-clock me-1"></i>{$file['uploaded_at']}</div>
                            <div class="file-badges mt-2">{$badge_comment}{$badge_news}{$badge_torrent}{$badge_post}{$badge_msg}</div>
                        </div>
                        <div class="file-actions d-grid">
                            <a href="{$file_url}" target="_blank" class="btn btn-outline-primary st-pill"><i class="fa-solid fa-eye me-2"></i>{$lang->usercp['btn_view_file']}</a>
                        </div>
                    </div>
                </div>
            </div>
            HTML;
        }
        $filelist .= '</div>';

        $remove_options = '<div class="d-flex flex-wrap gap-2">'
            . '<button type="button" class="btn btn-danger st-pill" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"><i class="fa-solid fa-trash me-2"></i>' . $lang->usercp['btn_delete_selected'] . '</button>'
            . '<a href="#" class="btn btn-outline-danger st-pill" data-bs-toggle="modal" data-bs-target="#confirmDeleteAllModal"><i class="fa-solid fa-broom me-2"></i>' . $lang->usercp['btn_delete_all'] . '</a>'
            . '</div>'
            . '<div class="d-flex align-items-center gap-3">'
            .   '<span class="st-meta">' . $lang->usercp['lbl_selected'] . ' <span id="selected-count" class="fw-semibold">0</span></span>'
            .   '<label class="st-check mb-0" for="select-all"><input type="checkbox" class="form-check-input" id="select-all"><span>' . $lang->usercp['lbl_select_all'] . '</span></label>'
            . '</div>';
    } else {
        $filelist       = '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-regular fa-folder-open"></i></div>'
            . '<strong>' . $lang->usercp['lbl_no_files'] . '</strong><div class="st-meta mt-1">' . $lang->usercp['hint_no_files'] . '</div></div>';
        $remove_options = '';
    }

    $post_code  = $mybb->post_code;
    $mp_html    = $multipage ? '<div class="st-pager d-flex justify-content-center mt-3"><nav><ul class="pagination justify-content-center">' . $multipage . '</ul></nav></div>' : '';
    $ro_html    = $remove_options ? '<div class="st-optbar justify-content-between">' . $remove_options . '</div>' : '';

    $files_subtitle = sprintf($lang->usercp['hint_files_sub'], $filecount);
    $files_badge    = sprintf($lang->usercp['lbl_files_count'], $filecount);

    $ucp_head = ucp_open('fa-images', 'info', $lang->usercp['lbl_my_files'], $files_subtitle);
    $ucp_tail = ucp_close();

    $files_html = <<<HTML
{$ucp_head}
            <form id="fileDeleteForm" action="usercp.php" method="post">
                <input type="hidden" name="my_post_key" value="{$post_code}">
                <input type="hidden" name="action" value="do_remove_files">
                <div class="st-card mb-3 overflow-hidden">
                    <div class="st-card__head">
                        <span class="st-ico st-soft-info"><i class="fa-solid fa-list"></i></span>
                        <span>{$lang->usercp['lbl_file_manager']}</span>
                        <span class="st-badge st-soft-primary ms-auto">{$files_badge}</span>
                    </div>
                    <div class="st-card__body">{$filelist}</div>
                    {$ro_html}
                </div>
            </form>

            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header"><h5 class="modal-title">{$lang->usercp['lbl_confirm_deletion']}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">{$lang->usercp['hint_confirm_delete_files']}</div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{$lang->usercp['cancel']}</button>
                    <button type="submit" class="btn btn-danger" form="fileDeleteForm">{$lang->usercp['btn_yes_delete']}</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal fade" id="confirmDeleteAllModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fa-solid fa-triangle-exclamation me-2"></i>{$lang->usercp['lbl_confirm_deletion']}</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">{$lang->usercp['hint_confirm_delete_all_files']}</div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{$lang->usercp['cancel']}</button>
                    <a href="usercp.php?action=remove_all_files&amp;my_post_key={$post_code}" class="btn btn-danger">{$lang->usercp['btn_yes_delete_all']}</a>
                  </div>
                </div>
              </div>
            </div>
            {$mp_html}
{$ucp_tail}
HTML;

    stdhead($lang->usercp['lbl_my_files'] . ' - ' . $SITENAME);


    echo '<script>
    document.addEventListener("DOMContentLoaded",function(){
        const selectAll=document.getElementById("select-all");
        const cbs=document.querySelectorAll(".file-checkbox-input");
        const cnt=document.getElementById("selected-count");
        selectAll?.addEventListener("change",function(){cbs.forEach(c=>c.checked=this.checked);update()});
        cbs.forEach(c=>c.addEventListener("change",update));
        function update(){const n=document.querySelectorAll(".file-checkbox-input:checked").length;if(cnt)cnt.textContent=n;if(selectAll)selectAll.checked=n===cbs.length&&cbs.length>0}
        document.querySelectorAll(".file-card").forEach((c,i)=>{c.style.opacity="0";c.style.transform="translateY(20px)";setTimeout(()=>{c.style.transition="opacity .6s ease,transform .6s ease";c.style.opacity="1";c.style.transform="translateY(0)"},100+i*100)});
    });
    </script>';

    build_breadcrumb();
    echo $files_html;
    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_remove_files
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_remove_files') {
    verify_post_check($mybb->get_input('my_post_key'));

    $deleted = 0;

    if (isset($mybb->input['file_ids']) && is_array($mybb->input['file_ids'])) {
        foreach ($mybb->input['file_ids'] as $raw_id) {
            $file_id = (int) $raw_id;
            $query   = $db->sql_query_prepared("
                SELECT cf.id, cf.file_path, cf.comment_id, cf.news_id, cf.torrent_id, cf.post_id, cf.messages_id,
                       c.text AS comment_text, n.body AS news_text, t.descr AS torrent_description,
                       p.message AS post_message, pm.message AS pm_message
                FROM comment_files cf
                LEFT JOIN comments c ON c.id=cf.comment_id
                LEFT JOIN news n ON n.id=cf.news_id
                LEFT JOIN torrents t ON t.id=cf.torrent_id
                LEFT JOIN posts p ON p.pid=cf.post_id
                LEFT JOIN privatemessages pm ON pm.pmid=cf.messages_id
                WHERE cf.id=? AND cf.user_id=?", [$file_id, (int) $CURUSER['id']]);

            if ($query && $db->num_rows($query) > 0) {
                $file = $db->fetch_array($query);
                if (!empty($file['file_path']) && file_exists($file['file_path'])) {
                    @unlink($file['file_path']);
                }

                $filename      = basename($file['file_path']);
                $image_pattern = '/\[img\][^\[]*' . preg_quote($filename, '/') . '[^\[]*\[\/img\]/i';

                foreach ([
                    ['text',    'comments',        'id',  $file['comment_id'],  $file['comment_text']],
                    ['body',    'news',             'id',  $file['news_id'],     $file['news_text']],
                    ['descr',   'torrents',         'id',  $file['torrent_id'],  $file['torrent_description']],
                    ['message', 'posts',        'pid', $file['post_id'],     $file['post_message']],
                    ['message', 'privatemessages',  'pmid',$file['messages_id'],$file['pm_message']],
                ] as [$field, $table, $id_field, $id, $content]) {
                    if ($id) {
                        $new_text = preg_replace($image_pattern, '[Image Deleted]', (string) $content);
                        if ($new_text !== $content) {
                            $db->sql_query_prepared("UPDATE {$table} SET {$field}=? WHERE {$id_field}=?", [$new_text, (int) $id]);
                        }
                    }
                }

                $db->sql_query_prepared("DELETE FROM comment_files WHERE id=? AND user_id=?", [$file_id, (int)$CURUSER['id']]);
                $deleted++;
            }
        }
        header('Location: usercp.php?action=manage_files&msg=' . urlencode(sprintf($lang->usercp['flash_files_deleted'], $deleted)));
        exit;
    }

    header('Location: usercp.php?action=manage_files&msg=' . urlencode($lang->usercp['flash_no_files_selected']));
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: remove_all_files
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'remove_all_files') {
    verify_post_check($mybb->get_input('my_post_key'));

    $uid   = (int) $CURUSER['id'];
    $query = $db->sql_query_prepared("
        SELECT cf.id, cf.file_path, cf.comment_id, cf.news_id, cf.torrent_id, cf.post_id, cf.messages_id,
               c.text AS comment_text, n.body AS news_text, t.descr AS torrent_description,
               p.message AS post_message, pm.message AS pm_message
        FROM comment_files cf
        LEFT JOIN comments c ON c.id=cf.comment_id
        LEFT JOIN news n ON n.id=cf.news_id
        LEFT JOIN torrents t ON t.id=cf.torrent_id
        LEFT JOIN posts p ON p.pid=cf.post_id
        LEFT JOIN privatemessages pm ON pm.pmid=cf.messages_id
        WHERE cf.user_id=?", [$uid]);

    $deleted = 0;
    while ($query && ($file = $db->fetch_array($query))) {
        if (!empty($file['file_path']) && file_exists($file['file_path'])) {
            @unlink($file['file_path']);
        }
        $filename      = basename($file['file_path']);
        $image_pattern = '/\[img\][^\[]*' . preg_quote($filename, '/') . '[^\[]*\[\/img\]/i';

        foreach ([
            ['text',    'comments',       'id',   $file['comment_id'],  $file['comment_text']],
            ['body',    'news',           'id',   $file['news_id'],     $file['news_text']],
            ['descr',   'torrents',       'id',   $file['torrent_id'],  $file['torrent_description']],
            ['message', 'posts',      'pid',  $file['post_id'],     $file['post_message']],
            ['message', 'privatemessages','pmid',  $file['messages_id'],$file['pm_message']],
        ] as [$field, $table, $id_field, $id, $content]) {
            if ($id) {
                $new_text = preg_replace($image_pattern, '[Image Deleted]', (string) $content);
                if ($new_text !== $content) {
                    $db->sql_query_prepared("UPDATE {$table} SET {$field}=? WHERE {$id_field}=?", [$new_text, (int) $id]);
                }
            }
        }

        $db->sql_query_prepared("DELETE FROM comment_files WHERE id=? AND user_id=?", [$file['id'], $uid]);
        $deleted++;
    }

    header('Location: usercp.php?action=manage_files&msg=' . urlencode(sprintf($lang->usercp['flash_all_files_deleted'], $deleted)));
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTIONs: acceptrequest / declinerequest / cancelrequest
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'acceptrequest') {
    verify_post_check($mybb->get_input('my_post_key'));

    $query   = $db->sql_query_prepared("SELECT * FROM buddyrequests WHERE id=? AND touid=?", [$mybb->get_input('id', MyBB::INPUT_INT), (int) $CURUSER['id']]);
    $request = $query ? $db->fetch_array($query) : null;
    if (empty($request)) { error('invalid_request'); }

    $plugins->run_hooks('usercp_acceptrequest_start');

    $user = get_user((int)$request['uid']);
    if (empty($user)) { error('user_doesnt_exist'); }

    // Add current user to the requestor's buddy list
    $requester_buddylist   = array_filter(explode(',', $user['buddylist']));
    $requester_buddylist[] = (int) $CURUSER['id'];
    $new_list = preg_replace(['#,{2,}#', '#[^0-9,]#'], [',', ''], implode(',', $requester_buddylist));
    $new_list = trim($new_list, ',');
    $db->sql_query_prepared("UPDATE users SET buddylist = ? WHERE id = ?", [$new_list, (int) $user['id']]);

    // Add requestor to current user's buddy list
    $my_buddylist   = array_filter(explode(',', $CURUSER['buddylist']));
    $my_buddylist[] = (int) $request['uid'];
    $new_list = preg_replace(['#,{2,}#', '#[^0-9,]#'], [',', ''], implode(',', $my_buddylist));
    $new_list = trim($new_list, ',');
    $db->sql_query_prepared("UPDATE users SET buddylist = ? WHERE id = ?", [$new_list, (int) $CURUSER['id']]);

    require_once INC_PATH . '/functions_pm.php';
    send_pm([
        'subject' => $lang->usercp['buddyrequest_accepted_request_message'],
        'message' => $lang->usercp['buddyrequest_accepted_request'],
        'touid'   => $user['id'],
    ], $CURUSER['id'], true);

    $db->sql_query_prepared("DELETE FROM buddyrequests WHERE id = ?", [(int) $request['id']]);
    $plugins->run_hooks('usercp_acceptrequest_end');
    redirect('usercp.php?action=editlists', $lang->usercp['buddyrequest_accepted']);
}

if ($mybb->input['action'] === 'declinerequest') {
    verify_post_check($mybb->get_input('my_post_key'));

    $query   = $db->sql_query_prepared("SELECT * FROM buddyrequests WHERE id=? AND touid=?", [$mybb->get_input('id', MyBB::INPUT_INT), (int) $CURUSER['id']]);
    $request = $query ? $db->fetch_array($query) : null;
    if (empty($request)) { error('invalid_request'); }

    $plugins->run_hooks('usercp_declinerequest_start');
    $user = get_user($request['uid']);
    if (empty($user)) { error('user_doesnt_exist'); }

    $db->sql_query_prepared("DELETE FROM buddyrequests WHERE id = ?", [(int) $request['id']]);
    $plugins->run_hooks('usercp_declinerequest_end');
    redirect('usercp.php?action=editlists', $lang->usercp['buddyrequest_declined']);
}

if ($mybb->input['action'] === 'cancelrequest') {
    verify_post_check($mybb->get_input('my_post_key'));

    $query   = $db->sql_query_prepared("SELECT * FROM buddyrequests WHERE id=? AND uid=?", [$mybb->get_input('id', MyBB::INPUT_INT), (int) $CURUSER['id']]);
    $request = $query ? $db->fetch_array($query) : null;
    if (empty($request)) { error('invalid_request'); }

    $plugins->run_hooks('usercp_cancelrequest_start');
    $db->sql_query_prepared("DELETE FROM buddyrequests WHERE id = ?", [(int) $request['id']]);
    $plugins->run_hooks('usercp_cancelrequest_end');
    redirect('usercp.php?action=editlists', $lang->usercp['buddyrequest_cancelled']);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_editlists
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_editlists') {
    verify_post_check($mybb->get_input('my_post_key'));

    $plugins->run_hooks('usercp_do_editlists_start');

    $isIgnored    = $mybb->get_input('manage') === 'ignored';
    $existing_str = $isIgnored ? ($CURUSER['ignorelist'] ?? '') : ($CURUSER['buddylist'] ?? '');
    $selected_str = $isIgnored ? ($CURUSER['buddylist'] ?? '')  : ($CURUSER['ignorelist'] ?? '');

    $existing_users = $existing_str ? explode(',', $existing_str) : [];
    $selected_list  = $selected_str ? explode(',', $selected_str) : [];

    $error_message = $message = '';

    if ($mybb->get_input('add_username')) {
        $found_users = 0;
        $adding_self = false;
        $users       = array_unique(array_filter(array_map('trim', explode(',', $mybb->get_input('add_username')))));

        foreach ($users as $key => $username) {
            if (strtoupper($CURUSER['username']) === strtoupper($username)) {
                $adding_self = true;
                unset($users[$key]);
            }
        }

        // Pending requests
        $q        = $db->sql_query_prepared("SELECT touid FROM buddyrequests WHERE uid=?", [(int) $CURUSER['id']]);
        $requests = [];
        while ($q && ($r = $db->fetch_array($q))) { $requests[$r['touid']] = true; }

        $q = $db->sql_query_prepared("SELECT uid FROM buddyrequests WHERE touid=?", [(int) $CURUSER['id']]);
        $requests_rec = [];
        while ($q && ($r = $db->fetch_array($q))) { $requests_rec[$r['uid']] = true; }

        $sent = false;

        if (count($users) > 0) {
            $field = in_array($db->type, ['mysql', 'mysqli'], true) ? 'username' : 'LOWER(username)';
            $lowered_users = array_map('strtolower', $users);
            $placeholders  = implode(',', array_fill(0, count($lowered_users), '?'));
            $q     = $db->sql_query_prepared("SELECT id,buddyrequestsauto,buddyrequestspm FROM users WHERE {$field} IN ({$placeholders})", $lowered_users);

            while ($q && ($user = $db->fetch_array($q))) {
                $found_users++;

                if (in_array($user['id'], $existing_users) || in_array($user['id'], $selected_list)) {
                    $string        = 'users_already_on_' . ($isIgnored ? 'ignored' : 'buddy') . '_list';
                    if (in_array($user['id'], $selected_list)) { $string .= '_alt'; }
                    $error_message = $lang->$string;
                    array_pop($users);
                    continue;
                }

                if (isset($requests[$user['id']])) {
                    $error_message = $isIgnored ? 'users_already_sent_request_alt' : 'users_already_sent_request';
                    array_pop($users);
                    continue;
                }

                if (isset($requests_rec[$user['id']])) {
                    $error_message = $isIgnored ? 'users_already_rec_request_alt' : 'users_already_rec_request';
                    array_pop($users);
                    continue;
                }

                if ($user['buddyrequestsauto'] == 1 && !$isIgnored) {
                    $existing_users[] = $user['id'];
                    require_once INC_PATH . '/functions_pm.php';
                    send_pm($user['id'], $lang->usercp['buddyrequest_new_buddy_message'], $lang->usercp['buddyrequest_new_buddy'], $CURUSER['id']);
                } elseif ($user['buddyrequestsauto'] != 1 && !$isIgnored) {
                    $db->sql_query_prepared(
                        "INSERT INTO buddyrequests (`uid`,`touid`,`date`) VALUES (?,?,?)",
                        [(int) $CURUSER['id'], (int) $user['id'], TIMENOW]
                    );
                    require_once INC_PATH . '/functions_pm.php';
                    send_pm([
                        'subject'       => $lang->usercp['buddyrequest_received'],
                        'message'       => sprintf($lang->usercp['buddyrequest_received_message'], $CURUSER['username']),
                        'touid'         => (int) $user['id'],
                        'fromid'        => (int) $CURUSER['id'],
                        'receivepms'    => (int) $user['buddyrequestspm'],
                        'language'      => $user['language'],
                        'language_file' => 'usercp',
                    ]);
                    $sent = true;
                } elseif ($isIgnored) {
                    $existing_users[] = $user['id'];
                }
            }
        }

        if ($found_users < count($users)) {
            if ($error_message) { $error_message .= '<br />'; }
            $error_message .= $lang->usercp['invalid_user_selected'];
        }

        if ((!$adding_self || ($adding_self && count($users) > 0)) && ($error_message === '' || count($users) > 1)) {
            $message = $isIgnored ? $lang->usercp['users_added_to_ignore_list'] : $lang->usercp['users_added_to_buddy_list'];
        }

        if ($adding_self) {
            $error_message = $isIgnored ? $lang->usercp['cant_add_self_to_ignore_list'] : $lang->usercp['cant_add_self_to_buddy_list'];
        }

        if (count($existing_users) === 0) {
            $message = '';
            if ($sent === true) { $message = $lang->usercp['buddyrequests_sent_success']; }
        }

    } elseif ($mybb->get_input('delete', MyBB::INPUT_INT)) {
        $del_id = $mybb->get_input('delete', MyBB::INPUT_INT);
        $key    = array_search($del_id, $existing_users);
        if ($key !== false) {
            unset($existing_users[$key]);
            $del_user = get_user($del_id);
            if (!empty($del_user) && !$isIgnored) {
                $bl  = $del_user['buddylist'] ? explode(',', $del_user['buddylist']) : [];
                $k2  = array_search($del_id, $bl);
                if ($k2 !== false) { unset($bl[$k2]); }
                $nl  = preg_replace(['#,{2,}#', '#[^0-9,]#'], [',', ''], implode(',', $bl));
                $nl  = trim($nl, ',');
                $db->sql_query_prepared("UPDATE users SET buddylist = ? WHERE id = ?", [$nl, (int) $del_user['uid']]);
            }
            $tmpl = $isIgnored ? $lang->usercp['removed_from_ignore_list'] : $lang->usercp['removed_from_buddy_list'];
            $message = sprintf($tmpl, htmlspecialchars_uni($del_user['username'] ?? ''));
        }
    }

    $new_list = trim(preg_replace(['#,{2,}#', '#[^0-9,]#'], [',', ''], implode(',', $existing_users)), ',');

    if ($isIgnored) {
        $CURUSER['ignorelist'] = $new_list;
        $db->sql_query_prepared("UPDATE users SET ignorelist = ? WHERE id = ?", [$CURUSER['ignorelist'], (int)$CURUSER['id']]);
    } else {
        $CURUSER['buddylist'] = $new_list;
        $db->sql_query_prepared("UPDATE users SET buddylist = ? WHERE id = ?", [$CURUSER['buddylist'], (int)$CURUSER['id']]);
    }

    $plugins->run_hooks('usercp_do_editlists_end');

    if (!empty($mybb->input['ajax'])) {
        $list       = $isIgnored ? 'ignore' : 'buddy';
        $message_js = '';
        if ($message)       { $message_js  = "$.jGrowl('{$message}', {theme:'jgrowl_success'});"; }
        if ($error_message) { $message_js .= " $.jGrowl('{$error_message}', {theme:'jgrowl_error'});"; }

        if ($mybb->get_input('delete', MyBB::INPUT_INT)) {
            // JSON вместо jQuery-кода ($("#…").remove(); $.jGrowl(…)): jQuery на сайте нет,
            // а значение manage попадало в ответ без экранирования.
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $error_message === '',
                'deleted' => $mybb->get_input('delete', MyBB::INPUT_INT),
                'count'   => $new_list === '' ? 0 : count(explode(',', $new_list)),
                'message' => $error_message !== '' ? $error_message : $message,
            ]);
            exit;
        }
        $mybb->input['action'] = 'editlists';
    } else {
        if ($error_message) { $message .= '<br />' . $error_message; }
        redirect('usercp.php?action=editlists#' . $mybb->get_input('manage'), $message);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: editlists
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'editlists') {
    $plugins->run_hooks('usercp_editlists_start');

    $timecut    = TIMENOW - ($wolcutoffmins ?? 900);
    $buddy_list = '';
    $buddy_count = 0;

    if ($CURUSER['buddylist']) {
        $type  = 'buddy';
        $buddy_ids = array_filter(array_map('intval', explode(',', $CURUSER['buddylist'])));
        if (!empty($buddy_ids)) {
            $buddy_placeholders = implode(',', array_fill(0, count($buddy_ids), '?'));
            $query = $db->sql_query_prepared("SELECT * FROM users WHERE id IN ({$buddy_placeholders}) ORDER BY username", $buddy_ids);
        } else {
            $query = false;
        }
        while ($query && ($user = $db->fetch_array($query))) {
            $user['username'] = htmlspecialchars_uni($user['username']);
            $profile_link     = build_profile_link(format_name($user['username'], (int)($user['usergroup'] ?? 1)), $user['id']);
            $status           = ($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $mybb->usergroup['canviewwolinvis'] == 1) && $user['lastvisit'] != $user['lastactive'])
                ? 'online' : 'offline';
            
			
			
			
$buddy_list .= '<div class="ucp-list-row" id="'.$type.'_'.(int)$user['id'].'">
    <span class="ucp-status ucp-status-'.$status.'" title="'.ucfirst($status).'"></span>
    <div class="ucp-list-name">'.$profile_link.'</div>
    <a href="usercp.php?action=do_editlists&amp;my_post_key='.$mybb->post_code.'&amp;manage='.$type.'&amp;delete='.(int)$user['id'].'" onclick="return UserCP.removeBuddy(\'' . $type . '\', ' . (int)$user['id'] . ');" class="ucp-list-remove" title="'.$lang->usercp['remove_from_list'].'" aria-label="'.$lang->usercp['remove_from_list'].'"><i class="fa-solid fa-xmark"></i></a>
</div>';
			
			
			
			
			
			
			
            $buddy_count++;
        }
    }

    $current_buddies = sprintf($lang->usercp['current_buddies'], $buddy_count);
    if (!$buddy_list) 
	{ 
	
	   $buddy_list = '<div class="ucp-list-empty"><i class="fa-regular fa-face-smile"></i><span>'.$lang->usercp['buddy_list_empty'].'</span></div>'; 


	
	
	}

    $ignore_list  = '';
    $ignore_count = 0;
    if ($CURUSER['ignorelist']) {
        $type  = 'ignored';
        $ignore_ids = array_filter(array_map('intval', explode(',', $CURUSER['ignorelist'])));
        if (!empty($ignore_ids)) {
            $ignore_placeholders = implode(',', array_fill(0, count($ignore_ids), '?'));
            $query = $db->sql_query_prepared("SELECT * FROM users WHERE id IN ({$ignore_placeholders}) ORDER BY username", $ignore_ids);
        } else {
            $query = false;
        }
        while ($query && ($user = $db->fetch_array($query))) {
            $user['username'] = htmlspecialchars_uni($user['username']);
            $profile_link     = build_profile_link(format_name($user['username'], (int)($user['usergroup'] ?? 1)), $user['id']);
            $status           = ($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $usergroups['issupermod'] === 'yes') && $user['lastvisit'] != $user['lastactive'])
                ? 'online' : 'offline';
            
			
			$ignore_list .= '<div class="ucp-list-row" id="'.$type.'_'.(int)$user['id'].'">
    <span class="ucp-ignored-mark"><i class="fa-solid fa-eye-slash"></i></span>
    <div class="ucp-list-name">'.$profile_link.'</div>
    <a href="usercp.php?action=do_editlists&amp;my_post_key='.$mybb->post_code.'&amp;manage='.$type.'&amp;delete='.(int)$user['id'].'" onclick="return UserCP.removeBuddy(\'' . $type . '\', ' . (int)$user['id'] . ');" class="ucp-list-remove" title="'.$lang->usercp['remove_from_list'].'" aria-label="'.$lang->usercp['remove_from_list'].'"><i class="fa-solid fa-xmark"></i></a>
</div>';
			
			
            $ignore_count++;
        }
    }

    $current_ignored_users = sprintf($lang->usercp['current_ignored_users'], $ignore_count);
    if (!$ignore_list) 
	{ 
        $ignore_list = '<div class="ucp-list-empty"><i class="fa-regular fa-circle-check"></i><span>'.$lang->usercp['ignore_list_empty'].'</span></div>'; 
   
    }

    // AJAX branch
    if ($mybb->request_method === 'post' && ($mybb->input['ajax'] ?? 0) == 1) {
        if ($mybb->input['manage'] === 'ignored') {
            echo $ignore_list;
            echo '<script>$("#ignored_count").html("' . $ignore_count . '"); ' . ($message_js ?? '') . '</script>';
        } else {
            if (isset($sent) && $sent === true) {
                $sent_rows = '';
                $q = $db->sql_query_prepared("SELECT r.*, u.username FROM buddyrequests r LEFT JOIN users u ON (u.id=r.touid) WHERE r.uid=?", [(int) $CURUSER['id']]);
                while ($q && ($request = $db->fetch_array($q))) {
                    $request['username'] = build_profile_link(htmlspecialchars_uni($request['username']), (int) $request['touid']);
                    $request['date']     = my_datee('relative', $request['date']);
                    
					$sent_rows .= '<div class="mb-2 pb-3 border-bottom">
<div class="row">
	<div class="col-auto">
		'.$request['username'].'
	</div>
	<div class="col-auto text-desc">
		'.$request['date'].'
	</div>
	<div class="col text-end">
		<a href="'.$BASEURL.'/usercp.php?action=cancelrequest&amp;id='.$request['id'].'&amp;my_post_key='.$mybb->post_code.'" class="links"><i class="fa-solid fa-xmark"></i> '.$lang->usercp['cancel'].'</a>
	</div>
	</div>
</div>';


                }
                if (!$sent_rows) { 
				
				$sent_rows = ''.$lang->usercp['no_requests'].''; 
				
				}
                
				$_tpl_out = '<div class="st-card">
	<div class="st-card__head">
	    '.$lang->usercp['buddyrequests_sent'].'
	</div>
	<div class="st-card__body">
		
		'.$sent_rows.'
		
	</div>
</div>'; 
				
				echo $_tpl_out;
				
                echo '<script>' . ($message_js ?? '') . '</script>';
            } else {
                echo $buddy_list;
                echo '<script>$("#buddy_count").html("' . $buddy_count . '"); ' . ($message_js ?? '') . '</script>';
            }
        }
        exit;
    }

    // Received requests
    $received_rows = '';
    $q = $db->sql_query_prepared("SELECT r.*, u.username FROM buddyrequests r LEFT JOIN users u ON (u.id=r.uid) WHERE r.touid=?", [(int) $CURUSER['id']]);
    while ($q && ($request = $db->fetch_array($q))) {
        // (int)$request['uid'] — автор заявки. Раньше здесь был $request['id'] (id самой
        // заявки), и ссылка вела на профиль случайного пользователя с таким id.
        $request['username'] = build_profile_link(htmlspecialchars_uni($request['username']), (int) $request['uid']);
        $request['date']     = my_datee('relative', $request['date']);
        
		$received_rows .= '<div class="ucp-list-row">
    <span class="ucp-req-mark ucp-req-in"><i class="fa-solid fa-arrow-down"></i></span>
    <div class="ucp-list-name">'.$request['username'].'<span class="ucp-list-date">'.$request['date'].'</span></div>
    <div class="ucp-req-actions">
        <a href="'.$BASEURL.'/usercp.php?action=acceptrequest&amp;id='.(int)$request['id'].'&amp;my_post_key='.$mybb->post_code.'" class="btn btn-sm btn-success rounded-pill px-3"><i class="fa-solid fa-check me-1"></i>'.$lang->usercp['accept'].'</a>
        <a href="'.$BASEURL.'/usercp.php?action=declinerequest&amp;id='.(int)$request['id'].'&amp;my_post_key='.$mybb->post_code.'" class="ucp-list-remove" title="'.$lang->usercp['decline'].'" aria-label="'.$lang->usercp['decline'].'"><i class="fa-solid fa-xmark"></i></a>
    </div>
</div>';
		
		
    }
    if (!$received_rows) 
	{ 
	    $received_rows = '<div class="ucp-list-empty"><i class="fa-regular fa-envelope-open"></i><span>'.$lang->usercp['no_requests'].'</span></div>'; 
		
	}
    
	$received_count    = substr_count($received_rows, 'class="ucp-list-row"');
	$received_requests = '<div class="st-card ucp-card">
    <div class="st-card__head">
        <span class="ucp-icon ucp-icon-received"><i class="fa-solid fa-inbox"></i></span>
        <span>'.$lang->usercp['buddyrequests_received'].'</span>
        <span class="ucp-count'.($received_count > 0 ? ' ucp-count-active' : '').'">'.$received_count.'</span>
    </div>
    <div class="st-card__body">
        <div class="ucp-list">'.$received_rows.'</div>
    </div>
</div>';


    // Sent requests
    $sent_rows = '';
    $q = $db->sql_query_prepared("SELECT r.*, u.username FROM buddyrequests r LEFT JOIN users u ON (u.id=r.touid) WHERE r.uid=?", [(int) $CURUSER['id']]);
    while ($q && ($request = $db->fetch_array($q))) {
        $request['username'] = build_profile_link(htmlspecialchars_uni($request['username']), (int) $request['touid']);
        $request['date']     = my_datee('relative', $request['date']);
        
		$sent_rows .= '<div class="ucp-list-row">
    <span class="ucp-req-mark ucp-req-out"><i class="fa-solid fa-arrow-up"></i></span>
    <div class="ucp-list-name">'.$request['username'].'<span class="ucp-list-date">'.$request['date'].'</span></div>
    <div class="ucp-req-actions">
        <span class="ucp-req-pending"><i class="fa-regular fa-clock me-1"></i>' . $lang->usercp['lbl_pending'] . '</span>
        <a href="'.$BASEURL.'/usercp.php?action=cancelrequest&amp;id='.(int)$request['id'].'&amp;my_post_key='.$mybb->post_code.'" class="ucp-list-remove" title="'.$lang->usercp['cancel'].'" aria-label="'.$lang->usercp['cancel'].'"><i class="fa-solid fa-xmark"></i></a>
    </div>
</div>';
		
		
    }
    if (!$sent_rows) 
	{ 

         $sent_rows = '<div class="ucp-list-empty"><i class="fa-regular fa-paper-plane"></i><span>'.$lang->usercp['no_requests'].'</span></div>'; 
		 
	}
    
	$sent_count    = substr_count($sent_rows, 'class="ucp-list-row"');
	$sent_requests = '<div class="st-card ucp-card">
    <div class="st-card__head">
        <span class="ucp-icon ucp-icon-sent"><i class="fa-solid fa-paper-plane"></i></span>
        <span>'.$lang->usercp['buddyrequests_sent'].'</span>
        <span class="ucp-count">'.$sent_count.'</span>
    </div>
    <div class="st-card__body">
        <div class="ucp-list">'.$sent_rows.'</div>
    </div>
</div>';


    $plugins->run_hooks('usercp_editlists_end');

    stdhead($lang->usercp['edit_lists']);
    build_breadcrumb();
    
	
	$ucp_lang_js = json_encode([
		'remove_buddy'    => $lang->usercp['confirm_remove_buddy'],
		'remove_ignored'  => $lang->usercp['confirm_remove_ignored'],
		'adding_buddy'    => $lang->usercp['adding_buddy'],
		'adding_ignored'  => $lang->usercp['adding_ignored'],
		'buddylist_error' => $lang->usercp['buddylist_error'],
	], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

	$_tpl_out = '


' . $swal_assets . '
' . ucp_js_lang([
    'search_user' => 'search_user',
    'max_users' => 'js_max_users',
    'remove' => 'remove_from_list',
    'remove_from_list_confirm' => 'js_confirm_remove_user',
    'list_update_failed' => 'js_list_update_failed',
]) . '
<script type="text/javascript" src="' . $BASEURL . '/scripts/usercp.js?ver=1903"></script>
<script type="text/javascript">
    window.lang = Object.assign(window.lang || {}, ' . $ucp_lang_js . ');
</script>

' . ucp_open('fa-user-group', 'success', $lang->usercp['edit_lists']) . '<div class="ucp-lists">
            <div class="row g-4">

                <!-- Buddy list -->
                <div class="col-xl-6">
                    <form action="usercp.php" method="post" id="buddy" onsubmit="return UserCP.addBuddy(\'buddy\');" class="h-100">
                        <input type="hidden" name="action" value="do_editlists" />
                        <input type="hidden" name="manage" value="buddy" />
                        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />

                        <div class="st-card ucp-card">
                            <div class="st-card__head">
                                <span class="ucp-icon ucp-icon-buddy"><i class="fas fa-user-friends"></i></span>
                                <span>' . $lang->usercp['edit_buddy_list'] . '</span>
                                <span class="ucp-count" title="' . htmlspecialchars_uni(strip_tags($current_buddies)) . '">' . (int)$buddy_count . '</span>
                            </div>
                            <div class="st-card__body">
                                <label class="st-label" for="buddy_add_username">' . $lang->usercp['add_buddies'] . '</label>
                                <div class="ucp-help">' . $lang->usercp['add_buddies_desc'] . '</div>

                                <div class="user-select" id="buddy_add_username"></div>
                                <input type="hidden" name="add_username" id="buddy_add_username_input">

                                <div class="d-flex justify-content-between align-items-center gap-2 mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-secondary st-pill" id="buddy_search_btn">
                                        <i class="fas fa-search me-1"></i> ' . $lang->usercp['search_user'] . '
                                    </button>
                                    <button type="submit" id="buddy_submit" class="btn btn-sm btn-success st-pill">
                                        <i class="fas fa-user-plus me-1"></i> ' . $lang->usercp['add_to_buddies'] . '
                                    </button>
                                </div>

                                <div class="ucp-list-title">' . $current_buddies . '</div>
                                <div class="ucp-list">' . $buddy_list . '</div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Ignore list -->
                <div class="col-xl-6">
                    <form action="usercp.php" method="post" id="ignored" onsubmit="return UserCP.addBuddy(\'ignored\');" class="h-100">
                        <input type="hidden" name="action" value="do_editlists" />
                        <input type="hidden" name="manage" value="ignored" />
                        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />

                        <div class="st-card ucp-card">
                            <div class="st-card__head">
                                <span class="ucp-icon ucp-icon-ignored"><i class="fas fa-user-slash"></i></span>
                                <span>' . $lang->usercp['edit_ignore_list'] . '</span>
                                <span class="ucp-count" title="' . htmlspecialchars_uni(strip_tags($current_ignored_users)) . '">' . (int)$ignore_count . '</span>
                            </div>
                            <div class="st-card__body">
                                <label class="st-label" for="ignored_add_username">' . $lang->usercp['add_ignored_users'] . '</label>
                                <div class="ucp-help">' . $lang->usercp['add_ignored_users_desc'] . '</div>

                                <div class="user-select" id="ignored_add_username"></div>
                                <input type="hidden" name="add_username" id="ignored_add_username_input">

                                <div class="d-flex justify-content-between align-items-center gap-2 mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-secondary st-pill" id="ignored_search_btn">
                                        <i class="fas fa-search me-1"></i> ' . $lang->usercp['search_user'] . '
                                    </button>
                                    <button type="submit" id="ignored_submit" class="btn btn-sm btn-danger st-pill">
                                        <i class="fas fa-ban me-1"></i> ' . $lang->usercp['ignore_users'] . '
                                    </button>
                                </div>

                                <div class="ucp-list-title">' . $current_ignored_users . '</div>
                                <div class="ucp-list">' . $ignore_list . '</div>
                            </div>
                        </div>
                    </form>
                </div>

            </div>

            <div class="row g-4 mt-0">
                <div class="col-xl-6">' . $received_requests . '</div>
                <div class="col-xl-6">' . $sent_requests . '</div>
            </div>
        </div>' . ucp_close();
    echo $_tpl_out;
    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_drafts
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_drafts' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $mybb->input['deletedraft'] = $mybb->get_input('deletedraft', MyBB::INPUT_ARRAY);
    if (empty($mybb->input['deletedraft'])) { error($lang->usercp['no_drafts_selected']); }

    $plugins->run_hooks('usercp_do_drafts_start');

    $pidin = $tidin_arr = [];
    foreach ($mybb->input['deletedraft'] as $id => $val) {
        if ($val === 'post')   { $pidin[]      = "'" . (int) $id . "'"; }
        elseif ($val === 'thread') { $tidin_arr[] = "'" . (int) $id . "'"; }
    }

    $tidinp = '';
    if ($tidin_arr) {
        $tidin  = implode(',', $tidin_arr);
        $db->sql_query_prepared("DELETE FROM threads WHERE tid IN ({$tidin}) AND visible='-2' AND uid = ?", [(int)$CURUSER['id']]);
        $tidinp = "OR tid IN ({$tidin})";
    }

    if ($pidin || $tidinp) {
        $pidinq = $pidin ? 'pid IN (' . implode(',', $pidin) . ')' : '1=0';
        $db->sql_query_prepared("DELETE FROM posts WHERE ({$pidinq} {$tidinp}) AND visible='-2' AND uid = ?", [(int)$CURUSER['id']]);
    }

    $plugins->run_hooks('usercp_do_drafts_end');
    redirect('usercp.php?action=drafts', $lang->usercp['selected_drafts_deleted']);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: drafts
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'drafts') {
    $plugins->run_hooks('usercp_drafts_start');

    $query      = $db->sql_query_prepared("SELECT COUNT(pid) AS draftcount FROM posts WHERE visible='-2' AND uid = ?", [(int)$CURUSER['id']]);
    $draftcount = $query ? (int) $db->fetch_field($query, 'draftcount') : 0;
    $drafts_count       = sprintf($lang->usercp['drafts_count'], ts_nf($draftcount));
    $drafts             = '';
    $disable_delete_drafts = '';

    if ($draftcount) {
        $query = $db->sql_query_prepared("
            SELECT p.subject, p.pid, t.tid, t.subject AS threadsubject, t.fid, f.name AS forumname,
                   p.dateline, t.visible AS threadvisible, p.visible AS postvisible
            FROM posts p
            LEFT JOIN threads t ON (t.tid=p.tid)
            LEFT JOIN forums f ON (f.fid=t.fid)
            WHERE p.uid=? AND p.visible='-2'
            ORDER BY p.dateline DESC, p.pid DESC", [(int) $CURUSER['id']]);

        while ($draft = $db->fetch_array($query)) {
           
            $detail = '';

            if ($draft['threadvisible'] == 1) {
                $draft['threadlink']    = get_thread_link($draft['tid']);
                $draft['threadsubject'] = htmlspecialchars_uni($draft['threadsubject']);
                $detail   = $lang->usercp['thread'] . ': <a href="' . $draft['threadlink'] . '">' . $draft['threadsubject'] . '</a>';
                $editurl  = 'newreply.php?action=editdraft&amp;pid=' . $draft['pid'];
                $id       = $draft['pid'];
                $type     = 'post';
            } elseif ($draft['threadvisible'] == -2) {
                $draft['forumlink'] = get_forum_link($draft['fid']);
                $draft['forumname'] = htmlspecialchars_uni($draft['forumname']);
                $detail   = $lang->usercp['forum'] . ': <a href="' . $draft['forumlink'] . '">' . $draft['forumname'] . '</a>';
                $editurl  = 'newthread.php?action=editdraft&amp;tid=' . $draft['tid'];
                $id       = $draft['tid'];
                $type     = 'thread';
            }

            $draft['subject'] = htmlspecialchars_uni($draft['subject']);
            $savedate = my_datee('relative', $draft['dateline']);
            
            $drafts .= '<div class="draft-card st-sub">'
                . '<input type="checkbox" class="form-check-input draft-checkbox" name="deletedraft[' . $id . ']" value="' . $type . '" id="draft_' . $id . '">'
                . '<div class="min-w-0">'
                .   '<div class="st-frow__top"><i class="fa-solid fa-file-lines text-primary"></i>'
                .     '<a href="' . $editurl . '" class="st-frow__title">' . $draft['subject'] . '</a></div>'
                .   '<div class="st-frow__desc">' . $detail . '</div>'
                .   '<div class="st-frow__top mt-1">'
                .     '<span class="st-badge st-soft-muted"><i class="fa-solid fa-comments"></i>' . $lang->usercp['lbl_draft'] . '</span>'
                .     '<span class="st-badge st-soft-info"><i class="fa-solid fa-floppy-disk"></i>' . $lang->usercp['lbl_autosave'] . '</span>'
                .   '</div>'
                . '</div>'
                . '<div class="st-sub__side">'
                .   '<div class="st-frow__last-date">'
                .     '<div><i class="fa-regular fa-calendar me-1"></i>' . $lang->usercp['lbl_save_date'] . '</div>'
                .     '<div><i class="fa-regular fa-clock me-1"></i>' . $savedate . '</div>'
                .   '</div>'
                .   '<div class="d-flex gap-2">'
                .     '<a href="' . $editurl . '" class="btn btn-sm btn-primary st-pill" title="' . $lang->usercp['edit_draft'] . '"><i class="fa-solid fa-pen me-1"></i>' . $lang->usercp['btn_edit'] . '</a>'
                .     '<button type="button" class="btn btn-sm btn-outline-danger st-pill delete-single-draft" data-draft-id="' . $id . '" title="' . $lang->usercp['tip_delete_draft'] . '"><i class="fa-solid fa-trash-can"></i></button>'
                .   '</div>'
                . '</div>'
                . '</div>';
			
			
			
			
        }
    } else {
        $disable_delete_drafts = 'disabled="disabled"';
        
        $drafts = '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-solid fa-file-lines"></i></div>'
            . '<strong>' . $lang->usercp['no_drafts'] . '</strong><div class="st-meta mt-1">' . $lang->usercp['hint_no_drafts'] . '</div>'
            . '<a href="' . $BASEURL . '/newthread.php" class="btn btn-primary st-pill mt-3"><i class="fa-solid fa-circle-plus me-2"></i>' . $lang->usercp['btn_create_thread'] . '</a>'
            . '</div>';
		
		
		
		
		
		
		
    }

    $plugins->run_hooks('usercp_drafts_end');
    stdhead($lang->usercp['drafts']);
    build_breadcrumb();
    
	
    echo ucp_open('fa-pen-ruler', 'warning', $lang->usercp['drafts'])
        . '<form action="usercp.php" method="post" id="draftsForm">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">'
        . '<input type="hidden" name="action" value="do_drafts">'
        . '<div class="st-card mb-3 overflow-hidden">'
        .   '<div class="st-card__head">'
        .     '<span class="st-ico st-soft-warning"><i class="fa-solid fa-pen-ruler"></i></span>'
        .     '<span>' . $lang->usercp['drafts'] . '</span>'
        .     '<span class="st-badge st-soft-warning">' . $drafts_count . '</span>'
        .     '<label class="ms-auto d-flex align-items-center gap-2 mb-0 st-meta" for="checkAll">' . $lang->usercp['lbl_select_all']
        .       '<input type="checkbox" id="checkAll" class="form-check-input"></label>'
        .   '</div>'
        .   '<div id="draftsList">' . $drafts . '</div>'
        .   '<div class="st-optbar justify-content-between">'
        .     '<div class="selected-count st-meta d-none">' . $lang->usercp['lbl_selected'] . ' <span id="selectedCount">0</span></div>'
        .     '<button type="submit" class="btn btn-danger st-pill ms-auto" id="deleteButton" name="draftman" value="' . $lang->usercp['delete_drafts'] . '" disabled>'
        .       '<i class="fa-solid fa-trash me-2"></i>' . $lang->usercp['delete_drafts']
        .     '</button>'
        .   '</div>'
        . '</div>'
        . '</form>'
        . ucp_close()
        . $swal_assets
        . ucp_js_lang([
            'select_draft' => 'no_drafts_selected',
            'confirm_delete_draft' => 'js_confirm_delete_draft',
            'confirm_delete_drafts' => 'js_confirm_delete_drafts',
        ])
        . '<script type="text/javascript" src="' . $BASEURL . '/scripts/drafts.js?ver=3"></script>';

    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: profile
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'profile') {
    if ($errors) {
        $user = $mybb->input;
        $bday = [$mybb->input['bday1'], $mybb->input['bday2'], (int) $mybb->input['bday3']];
    } else {
        $user = $CURUSER['id'];
        $bday = explode('-', $CURUSER['birthday']);
        $bday[1] ??= 0;
    }
    $bday[2] = (isset($bday[2]) && $bday[2] != 0) ? $bday[2] : '';

    $plugins->run_hooks('usercp_profile_start');

    $bdaydaysel = '';
    for ($day = 1; $day <= 31; $day++) {
        $selected    = ($bday[0] == $day) ? 'selected="selected"' : '';
        $bdaydaysel .= '<option value="' . $day . '"' . $selected . '>' . $day . '</option>';
    }

    $bdaymonthsel = array_fill(1, 12, '');
    $bdaymonthsel[$bday[1]] = 'selected="selected"';

    $allselected = $noneselected = $ageselected = '';
    match ($CURUSER['birthdayprivacy'] ?? 'all') {
        'none'  => $noneselected = ' selected="selected"',
        'age'   => $ageselected  = ' selected="selected"',
        default => $allselected  = ' selected="selected"',
    };

    $QArray = [
        1 => 'What is your name of first school?',
        2 => "What is your pet's name?",
        3 => "What is your mothers maiden name?",
    ];

    $current_passhint   = $errors ? (int) ($mybb->input['passhint'] ?? 0) : 0;
    $hintanswer_value   = $errors ? htmlspecialchars_uni($mybb->input['hintanswer'] ?? '') : '';

    $secret_questions_options = '<option value="0">-- Select Question --</option>';
    foreach ($QArray as $id => $question) {
        $selected = ($current_passhint === $id) ? 'selected="selected"' : '';
        $secret_questions_options .= '<option value="' . $id . '" ' . $selected . '>' . htmlspecialchars_uni($question) . '</option>';
    }

    $plugins->run_hooks('usercp_profile_end');
    stdhead($lang->usercp['edit_profile']);
    build_breadcrumb();

    $bd_opt = '<option value="">&nbsp;</option>';

    echo ucp_open('fa-user-pen', 'primary', $lang->usercp['edit_profile'])
        . $errors
        . '<form action="usercp.php" method="post" name="input">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . ucp_sec_open('fa-cake-candles', 'warning', $lang->usercp['birthday'])
        .   '<p class="st-hint mt-0 mb-3"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_birthday_sub'] . '</p>'
        .   '<div class="row g-2 st-field st-field--split">'
        .     '<div class="col-4 col-md-3"><label class="st-label" for="bday1"><i class="fa-solid fa-calendar-day"></i>' . $lang->usercp['day'] . '</label>'
        .       '<select name="bday1" id="bday1" class="form-select">' . $bd_opt . $bdaydaysel . '</select></div>'
        .     '<div class="col-8 col-md-5"><label class="st-label" for="bday2"><i class="fa-solid fa-calendar-days"></i>' . $lang->usercp['month'] . '</label>'
        .       '<select name="bday2" id="bday2" class="form-select">' . $bd_opt;
    for ($m = 1; $m <= 12; $m++) {
        echo '<option value="' . $m . '" ' . $bdaymonthsel[$m] . '>' . $lang->usercp['month_' . $m] . '</option>';
    }
    echo        '</select></div>'
        .     '<div class="col-12 col-md-4"><label class="st-label" for="bday3"><i class="fa-solid fa-calendar-week"></i>' . $lang->usercp['year'] . '</label>'
        .       '<input type="text" class="form-control" maxlength="4" name="bday3" id="bday3" value="' . $bday['2'] . '" placeholder="YYYY" /></div>'
        .   '</div>'
        .   ucp_select('birthdayprivacy', $lang->usercp['birthdayprivacy'],
                '<option value="all"' . $allselected . '>' . $lang->usercp['birthdayprivacyall'] . '</option>'
              . '<option value="none"' . $noneselected . '>' . $lang->usercp['birthdayprivacynone'] . '</option>'
              . '<option value="age"' . $ageselected . '>' . $lang->usercp['birthdayprivacyage'] . '</option>',
                'fa-shield-halved', 'info')
        .   '<div class="st-hint"><i class="fa-solid fa-circle-info"></i>' . $lang->usercp['hint_birthday_privacy'] . '</div>'
        . ucp_sec_close()
        . '<div class="st-submitbar justify-content-end">'
        .   '<input type="hidden" name="action" value="do_profile" />'
        .   '<button type="submit" class="btn btn-primary st-pill" name="regsubmit" value="' . $lang->usercp['update_profile'] . '">'
        .     '<i class="fa-solid fa-floppy-disk me-2"></i>' . $lang->usercp['update_profile']
        .   '</button>'
        . '</div>'
        . '</form>'
        . ucp_close();

    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_attachments
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_attachments' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    require_once INC_PATH . '/functions_upload.php';
    if (!isset($mybb->input['attachments']) || !is_array($mybb->input['attachments'])) {
        error($lang->usercp['no_attachments_selected']);
    }

    $plugins->run_hooks('usercp_do_attachments_start');

    $f_perm_sql       = '';
    $unviewable_forums = get_unviewable_forums(true);
    $inactiveforums   = get_inactive_forums();
    if ($unviewable_forums) { $f_perm_sql  = " AND (p.fid IS NULL OR p.fid NOT IN ({$unviewable_forums}))"; }
    if ($inactiveforums)    { $f_perm_sql .= " AND (p.fid IS NULL OR p.fid NOT IN ({$inactiveforums}))"; }

    $aids  = implode(',', array_map('intval', $mybb->input['attachments']));
    $query = $db->sql_query_prepared("SELECT a.*, p.fid FROM attachments a LEFT JOIN posts p ON (a.pid=p.pid) WHERE aid IN ({$aids}) AND a.uid=? {$f_perm_sql}", [(int)$CURUSER['id']]);

    while ($query && ($attachment = $db->fetch_array($query))) {
        if ((int)$attachment['pid'] > 0) {
            remove_attachment((int)$attachment['pid'], '', (int)$attachment['aid']);
        } else {
            // Comment-based attachment — delete directly (no post to update)
            require_once INC_PATH . '/functions_upload.php';
            $uploadDir = TSDIR . '/uploads/attachments/';

            delete_uploaded_file($uploadDir . $attachment['attachname']);
            if (!empty($attachment['thumbnail']) && $attachment['thumbnail'] !== 'SMALL') {
                delete_uploaded_file($uploadDir . $attachment['thumbnail']);
            }

            $db->sql_query_prepared("DELETE FROM attachments WHERE aid = ?", [(int)$attachment['aid']]);
        }
    }

    $plugins->run_hooks('usercp_do_attachments_end');
    redirect('usercp.php?action=attachments', $lang->usercp['attachments_deleted']);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: attachments
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'attachments') {
    require_once INC_PATH . '/functions_upload.php';

    if ((int) ($enableattachments ?? 1) === 0) { error($lang->usercp['err_attachments_disabled']); }

    $plugins->run_hooks('usercp_attachments_start');

    $f_perm_sql       = '';
    $unviewable_forums = get_unviewable_forums(true);
    $inactiveforums   = get_inactive_forums();
    if ($unviewable_forums) { $f_perm_sql  = " AND (t.fid IS NULL OR t.fid NOT IN ({$unviewable_forums}))"; }
    if ($inactiveforums)    { $f_perm_sql .= " AND (t.fid IS NULL OR t.fid NOT IN ({$inactiveforums}))"; }

    $perpage = ($f_threadsperpage && (int) $f_threadsperpage >= 1) ? (int) $f_threadsperpage : 20;
    $page    = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start   = ($page - 1) * $perpage;

    $query = $db->sql_query_prepared("
        SELECT a.*, p.subject, p.dateline, t.tid, t.subject AS threadsubject, u.username AS username,
               c.id AS cmt_id, c.torrent AS cmt_torrentid
        FROM attachments a
        LEFT JOIN posts p ON (a.pid=p.pid)
        LEFT JOIN threads t ON (t.tid=p.tid)
		LEFT JOIN users u ON (u.id=p.uid)
        LEFT JOIN comments c ON (c.id=a.comment_id)
        WHERE a.uid=? {$f_perm_sql}
        ORDER BY a.dateuploaded DESC, a.aid DESC LIMIT ?,?", [(int) $CURUSER['id'], $start, $perpage]);

    $attachments  = '';
    $bandwidth = $totaldownloads = $totalusage = $totalattachments = $processedattachments = 0;

    while ($attachment = $db->fetch_array($query)) {
        $is_post_attachment    = (int)$attachment['pid'] > 0 && $attachment['dateline'] && $attachment['tid'];
        $is_comment_attachment = (int)$attachment['comment_id'] > 0 && $attachment['cmt_id'];

        if ($is_post_attachment || $is_comment_attachment) {

            if ($is_post_attachment) {
                $attachment['subject']       = htmlspecialchars_uni($parser->parse_badwords($attachment['subject']));
                $attachment['postlink']      = get_post_link($attachment['pid'], $attachment['tid']);
                $attachment['threadlink']    = get_thread_link($attachment['tid']);
                $attachment['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($attachment['threadsubject']));

                $location_badge = '<span class="st-badge st-soft-primary">
                    <i class="fas fa-comment me-1"></i>
                    ' . $lang->usercp['attachments_post'] . '
                </span>
                <a href="' . $attachment['postlink'] . '#pid' . $attachment['pid'] . '"
                   class="st-meta text-truncate text-decoration-none">
                    ' . $attachment['subject'] . '
                </a>';
            } else {
                $comment_link = get_comment_link((int)$attachment['comment_id'], (int)$attachment['cmt_torrentid']);
                $location_badge = '<span class="st-badge st-soft-success">
                    <i class="fas fa-comments me-1"></i>
                    ' . $lang->usercp['lbl_badge_comment_type'] . '
                </span>
                <a href="' . $comment_link . '#pid' . $attachment['comment_id'] . '"
                   class="st-meta text-truncate text-decoration-none">
                    ' . sprintf($lang->usercp['lbl_badge_comment'], (int)$attachment['comment_id']) . '
                </a>';
            }

            $size           = mksize($attachment['filesize']);
            $icon           = get_attachment_icon(get_extension($attachment['filename']));
            $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
            $sizedownloads  = sprintf($lang->usercp['attachment_size_downloads'], $size, $attachment['downloads']);
            $attachdate     = my_datee('relative', (int)$attachment['dateuploaded']);
           

            if ($is_comment_attachment) {
                $view_url     = $BASEURL . '/uploads/attachments/' . rawurlencode($attachment['attachname']);
                $download_url = $view_url;
            } else {
                $view_url     = 'attachment.php?aid=' . (int)$attachment['aid'];
                $download_url = 'attachment.php?aid=' . (int)$attachment['aid'] . '&action=download';
            }

            // Image preview thumbnail
            $is_image = str_starts_with((string)($attachment['filetype'] ?? ''), 'image/');
            if ($is_image) {
                if ($is_comment_attachment) {
                    $thumb_url = (!empty($attachment['thumbnail']) && $attachment['thumbnail'] !== 'SMALL')
                        ? $BASEURL . '/uploads/attachments/' . rawurlencode($attachment['thumbnail'])
                        : $view_url;
                } else {
                    // Post attachments: use attachment.php?thumbnail=AID
                    $thumb_url = 'attachment.php?thumbnail=' . (int)$attachment['aid'];
                }
                $icon_html = '<a href="' . $view_url . '" target="_blank" class="attachment-thumb-link">
                    <img src="' . $thumb_url . '" class="attachment-thumb" alt="' . $attachment['filename'] . '" loading="lazy"
                         onerror="this.closest(\'.attachment-icon\').innerHTML=\'' . addslashes($icon) . '\'">
                </a>';
            } else {
                $icon_html = $icon;
            }
            
			
            $attachments .= '<div class="attachment-item st-sub">'
                . '<input type="checkbox" class="form-check-input attachment-checkbox" name="attachments[' . $attachment['aid'] . ']" value="' . $attachment['aid'] . '" id="attachment_' . $attachment['aid'] . '">'
                . '<div class="d-flex align-items-center gap-3 min-w-0">'
                .   '<div class="attachment-icon">' . $icon_html . '</div>'
                .   '<div class="min-w-0">'
                .     '<a href="' . $view_url . '" target="_blank" class="st-frow__title d-block text-truncate" title="' . $attachment['filename'] . '">' . $attachment['filename'] . '</a>'
                .     '<div class="st-meta mt-1">' . $sizedownloads . '</div>'
                .     '<div class="st-frow__top mt-1">' . $location_badge . '</div>'
                .   '</div>'
                . '</div>'
                . '<div class="st-sub__side">'
                .   '<div class="st-frow__last-date"><i class="fa-regular fa-calendar me-1"></i>' . $attachdate . '</div>'
                .   '<div class="d-flex gap-2">'
                .     '<a href="' . $view_url . '" target="_blank" class="btn btn-sm btn-outline-primary st-pill" title="' . $lang->usercp['tip_preview'] . '"><i class="fa-solid fa-eye"></i></a>'
                .     '<a href="' . $download_url . '" class="btn btn-sm btn-outline-success st-pill" title="' . $lang->usercp['tip_download'] . '"><i class="fa-solid fa-download"></i></a>'
                .   '</div>'
                . '</div>'
                . '</div>';



            $bandwidth      += $attachment['filesize'] * $attachment['downloads'];
            $totaldownloads += $attachment['downloads'];
            $totalusage     += $attachment['filesize'];
            $totalattachments++;
        } else {
            remove_attachment($attachment['pid'], $attachment['posthash'], $attachment['aid']);
        }
        $processedattachments++;
    }

    $multipage = '';
    if ($processedattachments >= $perpage || $page > 1) {
        $q = $db->sql_query_prepared("
            SELECT SUM(a.filesize) AS ausage, COUNT(a.aid) AS acount
            FROM attachments a
            LEFT JOIN posts p ON (a.pid=p.pid)
            LEFT JOIN threads t ON (t.tid=p.tid)
            WHERE a.uid=? {$f_perm_sql}", [(int) $CURUSER['id']]);
        $usage         = $db->fetch_array($q);
        $totalusage    = $usage['ausage'];
        $totalattachments = $usage['acount'];
        $multipage     = multipage($totalattachments, $perpage, $page, 'usercp.php?action=attachments');
    }

    $friendlyusage = mksize((int) $totalusage);
    $attachquota   = $lang->usercp['unlimited'];
    if ($usergroups['attachquota']) {
        $percent       = round(($totalusage / ($usergroups['attachquota'] * 1024)) * 100);
        $friendlyusage .= sprintf($lang->usercp['attachments_usage_percent'], $percent);
        $attachquota   = mksize($usergroups['attachquota'] * 1024);
        $usagenote     = sprintf($lang->usercp['attachments_usage_quota'], $friendlyusage, $attachquota, $totalattachments);
    } else {
        $usagenote = sprintf($lang->usercp['attachments_usage'], $friendlyusage, $totalattachments);
    }

    $bandwidth     = mksize($bandwidth);
    
	$attachments_present = !empty($attachments);
	$delete_button = '<input type="hidden" name="action" value="do_attachments" />';

    if (!$attachments) {
        
        $attachments = '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-solid fa-paperclip"></i></div>'
            . '<strong>' . $lang->usercp['no_attachments'] . '</strong></div>';
		
        $usagenote     = '';
        $delete_button = '';
    }

    $plugins->run_hooks('usercp_attachments_end');
    stdhead($lang->usercp['attachments_manager']);
    
    build_breadcrumb();

    $att_kpi = static fn(string $icon, string $tone, $val, string $lbl): string =>
        '<div class="col-6 col-md-4"><div class="st-kpi"><span class="st-ico st-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<div class="min-w-0"><div class="st-kpi__val">' . $val . '</div><div class="st-kpi__lbl">' . $lbl . '</div></div></div></div>';

    echo ucp_open('fa-paperclip', 'primary', $lang->usercp['attachments_manager'],
            sprintf($lang->usercp['lbl_attach_header'], $totalattachments, $friendlyusage))
        . '<div class="row g-3 mb-3">'
        .   $att_kpi('fa-file', 'primary', $totalattachments, $lang->usercp['attachstats_attachs'])
        .   $att_kpi('fa-hard-drive', 'success', $friendlyusage, $lang->usercp['attachstats_spaceused'])
        .   $att_kpi('fa-gauge-high', 'info', $attachquota, $lang->usercp['attachstats_quota'])
        .   $att_kpi('fa-download', 'warning', $totaldownloads, $lang->usercp['attachstats_totaldl'])
        .   $att_kpi('fa-network-wired', 'danger', $bandwidth, $lang->usercp['attachstats_bandwidth'])
        . '</div>'
        . '<form action="usercp.php" method="post" name="attachmentsmanager">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '<div class="st-card mb-3 overflow-hidden">'
        .   '<div class="st-card__head">'
        .     '<span class="st-ico st-soft-primary"><i class="fa-solid fa-list"></i></span>'
        .     '<span>' . $lang->usercp['attachments_manager'] . '</span>'
        .     '<label class="ms-auto d-flex align-items-center gap-2 mb-0 st-meta" for="att_allbox">' . $lang->usercp['lbl_select_all']
        .       '<input type="checkbox" id="att_allbox" name="allbox" class="form-check-input checkall"></label>'
        .   '</div>'
        .   ($usagenote ? '<div class="st-notice st-soft-info m-3 mb-0"><i class="fa-solid fa-circle-info"></i><div>' . $usagenote . '</div></div>' : '')
        .   '<div class="attachments-container">' . $attachments . '</div>'
        .   $delete_button
        .   (!empty($attachments_present)
                ? '<div class="st-optbar justify-content-end">'
                .   '<button type="submit" class="btn btn-danger st-pill" name="delete" data-ags-confirm="' . htmlspecialchars_uni($lang->usercp['confirm_deletion']) . '" data-ags-danger="1">'
                .     '<i class="fa-solid fa-trash-can me-2"></i>' . $lang->usercp['delete_attachments']
                .   '</button>'
                . '</div>'
                : '')
        . '</div>'
        . '</form>'
        . ($multipage ? '<div class="st-pager d-flex justify-content-center">' . $multipage . '</div>' : '')
        . ucp_close()
        . $swal_assets
        . '<script type="text/javascript" src="' . $BASEURL . '/scripts/usercp.js?ver=1903"></script>'
        . '<script type="text/javascript" src="' . $BASEURL . '/scripts/attachments.js"></script>';

    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// DEFAULT: User CP home
// ══════════════════════════════════════════════════════════════════════════
if (!$mybb->input['action']) {
    $daysreg = max(1, (TIMENOW - $CURUSER['added']) / 86400);
    $perday  = round(min($CURUSER['postnum'], $CURUSER['postnum'] / $daysreg), 2);

    $stats = $cache->read("stats");
	$posts = $stats['numposts'];
    $percent = ($posts > 0) ? round($CURUSER['postnum'] * 100 / $posts, 2) : 0;

    $colspan = 2;
    $ss      = sprintf($lang->usercp['posts_day'], ts_nf($perday), $percent);
    $regdate = my_datee('relative', $CURUSER['added']);
    $bonus   = $CURUSER['seedbonus'];
    $com     = $CURUSER['comms'];


    $useravatar    = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
    $currentavatar = build_avatar_html($useravatar);

    $mybb->user['email'] = htmlspecialchars_uni($CURUSER['email']);
    $usergroup           = htmlspecialchars_uni($groupscache[$CURUSER['usergroup']]['title']);
    $username            = build_profile_link(
        format_name(htmlspecialchars_uni($CURUSER['username']), (int)$CURUSER['usergroup']),
        $CURUSER['id']
    );
    $mybb->user['posts'] = ts_nf($CURUSER['postnum']);

    $plugins->run_hooks('usercp_end');
    stdhead($lang->usercp['user_cp']);
    
    build_breadcrumb();

    $home_row = static fn(string $icon, string $tone, string $lbl, string $val): string =>
        '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid ' . $icon . ' text-' . $tone . '"></i>' . $lbl . '</span>'
        . '<span class="st-row__val">' . $val . '</span></div>';

    echo ucp_open('fa-house', 'primary', $lang->usercp['user_cp'])
        . '<div class="st-card mb-3"><div class="st-card__body"><div class="row align-items-center g-4">'
        .   '<div class="col-md-auto text-center">'
        .     '<div class="d-inline-block">' . $currentavatar . '</div>'
        .     '<div class="mt-2"><span class="st-badge st-soft-success"><i class="fa-solid fa-circle"></i>' . $lang->usercp['online'] . '</span></div>'
        .   '</div>'
        .   '<div class="col-md min-w-0">'
        .     '<div class="st-title mb-3"><i class="fa-solid fa-user text-primary me-2"></i>' . $username . '</div>'
        .     '<div class="row g-3">'
        .       '<div class="col-md-6"><div class="st-rows">'
        .         $home_row('fa-comment', 'primary', $lang->usercp['postnum'],
                      '<a href="search.php?action=finduser&amp;uid=' . $CURUSER['id'] . '">' . $mybb->user['posts'] . ' ' . $ss . '</a>')
        .         $home_row('fa-comments', 'info', $lang->usercp['comentsss'],
                      '<a href="userhistory.php?action=viewcomments&amp;id=' . $CURUSER['id'] . '">' . $com . '</a>')
        .         $home_row('fa-star', 'warning', $lang->usercp['bpoints'], '<a href="mybonus.php">' . $bonus . '</a>')
        .       '</div></div>'
        .       '<div class="col-md-6"><div class="st-rows">'
        .         $home_row('fa-envelope', 'danger', $lang->usercp['email'], $CURUSER['email'])
        .         $home_row('fa-calendar-days', 'success', $lang->usercp['registration_date'], $regdate)
        .         $home_row('fa-users', 'secondary', $lang->usercp['primary_usergroup'], $usergroup)
        .       '</div></div>'
        .     '</div>'
        .   '</div>'
        . '</div></div></div>'
        . ucp_close();

    // Скрипты — после разметки страницы (раньше выводились до stdhead())
    echo $swal_assets;
    echo avatar_js_lang();
    echo '<script src="' . $BASEURL . '/scripts/toast.js?ver=2"></script>';
    echo '<script src="' . $BASEURL . '/scripts/avatar_upload.js?ver=5"></script>';
    stdfoot();
    exit;
}







// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_2fa — enable / disable / confirm 2FA
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_2fa' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    require_once INC_PATH . '/functions_2fa.php';
    require_once INC_PATH . '/function_loginattemptcheck.php';

    $uid  = (int)$CURUSER['id'];
    $mode = $mybb->get_input('mode'); // 'enable' | 'disable' | 'confirm'

    // ── Notify the account owner of a 2FA status change ─────────────────────
    $notify_2fa_change = function (int $uid, bool $enabled) use ($db, $SITENAME, $lang): void {
        $query = $db->sql_query_prepared("SELECT username, email FROM users WHERE id = ? LIMIT 1", [$uid]);
        $user  = $query ? $db->fetch_array($query) : null;

        if (empty($user['email'])) {
            return;
        }

        $ip   = get_ip();
        $geo  = geo_by_ip($ip);
        $time = date('Y-m-d H:i:s');

        $action  = $enabled ? $lang->usercp['email_2fa_word_enabled'] : $lang->usercp['email_2fa_word_disabled'];
        $subject = sprintf($lang->usercp['email_2fa_subject'], $SITENAME, $action);

        $message = sprintf(
            $lang->usercp['email_2fa_body'],
            $action,
            $ip,
            $geo['country'] . ' / ' . $geo['city'],
            $time,
            $enabled ? $lang->usercp['email_2fa_note_enabled'] : $lang->usercp['email_2fa_note_disabled']
        );

        my_mail($user['email'], $subject, $message);
    };

    if ($mode === 'disable') {
        if (!validate_password_from_uid($uid, $mybb->get_input('password'))) {
            $mybb->input['action']    = '2fa';
            $mybb->input['2fa_error'] = $lang->usercp['err_2fa_bad_password'];
        } else {
            totp_disable($uid);
            $notify_2fa_change($uid, false);
            redirect('usercp.php?action=2fa', $lang->usercp['flash_2fa_disabled']);
        }

    } elseif ($mode === 'confirm') {
        $secret = $mybb->get_input('secret');
        $code   = preg_replace('/\D/', '', $mybb->get_input('totp_code'));

        if (empty($secret)) {
            redirect('usercp.php?action=2fa', $lang->usercp['err_2fa_bad_secret']);
        }

        if (!totp_verify($secret, $code)) {
            $mybb->input['action']    = '2fa';
            $mybb->input['2fa_error'] = $lang->usercp['err_2fa_bad_code'];
            $mybb->input['2fa_secret'] = $secret;
        } else {
            totp_enable($uid, $secret);
            $notify_2fa_change($uid, true);
            redirect('usercp.php?action=2fa', $lang->usercp['flash_2fa_enabled']);
        }

    } elseif ($mode === 'enable') {
        $mybb->input['action'] = '2fa';
        $mybb->input['2fa_new'] = '1';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: 2fa — show 2FA settings page
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === '2fa') {
    require_once INC_PATH . '/functions_2fa.php';

    $uid      = (int)$CURUSER['id'];
    $BASE     = htmlspecialchars($BASEURL, ENT_QUOTES, 'UTF-8');
    $postCode = htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8');
    $enabled  = totp_is_enabled($uid);

    // Generate new secret if entering setup flow
    if (!empty($mybb->input['2fa_new']) || !empty($mybb->input['2fa_secret'])) {
        $secret   = !empty($mybb->input['2fa_secret'])
            ? htmlspecialchars($mybb->input['2fa_secret'], ENT_QUOTES, 'UTF-8')
            : totp_generate_secret();
            $otpauth_uri = totp_get_otpauth_uri($secret, htmlspecialchars($CURUSER['username'], ENT_QUOTES), $SITENAME);
		
        $error    = htmlspecialchars($mybb->input['2fa_error'] ?? '', ENT_QUOTES, 'UTF-8');
        $setup_mode = true;
    } else {
        $setup_mode = false;
        $secret = $otpauth_uri = $error = '';
    }

    stdhead($lang->usercp['nav_2fa']);
    build_breadcrumb();

    $tfa_err = !empty($mybb->input['2fa_error'])
        ? '<div class="st-notice st-soft-danger mb-3"><i class="fa-solid fa-triangle-exclamation"></i><div>' . htmlspecialchars($mybb->input['2fa_error']) . '</div></div>'
        : '';

    // ── Enabled state ─────────────────────────────────────────────────────
    if ($enabled && !$setup_mode) {
        $tfa_body = ucp_open('fa-shield-halved', 'success', $lang->usercp['lbl_2fa_short'], $lang->usercp['hint_2fa_protected'])
            . '<div class="st-notice st-soft-success mb-3"><i class="fa-solid fa-circle-check fa-lg"></i>'
            .   '<div><strong>' . $lang->usercp['lbl_2fa_enabled'] . '</strong><br>' . $lang->usercp['hint_2fa_enabled'] . '</div></div>'
            . '<form method="post" action="usercp.php">'
            . '<input type="hidden" name="action" value="do_2fa" />'
            . '<input type="hidden" name="mode" value="disable" />'
            . '<input type="hidden" name="my_post_key" value="' . $postCode . '" />'
            . $tfa_err
            . ucp_sec_open('fa-shield-halved', 'success', $lang->usercp['lbl_2fa_short'])
            .   '<p class="text-secondary">' . $lang->usercp['hint_2fa_login_code'] . '</p>'
            .   '<div class="st-field" style="max-width:20rem;">'
            .     '<label class="st-label" for="tfa_pwd"><i class="fa-solid fa-lock text-warning"></i>' . $lang->usercp['lbl_2fa_confirm_pwd'] . '</label>'
            .     '<input type="password" id="tfa_pwd" name="password" class="form-control" required autocomplete="current-password">'
            .   '</div>'
            . ucp_sec_close()
            . '<div class="st-submitbar justify-content-end">'
            .   '<button type="submit" class="btn btn-danger st-pill"'
            .     ' data-ags-confirm="' . htmlspecialchars_uni($lang->usercp['js_confirm_disable_2fa']) . '" data-ags-danger="1">'
            .     '<i class="fa-solid fa-shield-xmark me-2"></i>' . $lang->usercp['btn_2fa_disable']
            .   '</button>'
            . '</div>'
            . '</form>'
            . ucp_close();

    // ── Setup / confirm flow ──────────────────────────────────────────────
    } elseif ($setup_mode) {
        $tfa_body = ucp_open('fa-qrcode', 'primary', $lang->usercp['lbl_2fa_setup'], $lang->usercp['hint_2fa_scan'])
            . (!empty($error)
                ? '<div class="st-notice st-soft-danger mb-3"><i class="fa-solid fa-triangle-exclamation"></i><div>' . $error . '</div></div>'
                : '')
            . '<div class="st-notice st-soft-info mb-3"><i class="fa-solid fa-circle-info"></i><div>'
            .   '<strong>' . sprintf($lang->usercp['lbl_2fa_step'], 1) . '</strong> '
            .   sprintf($lang->usercp['hint_2fa_step1'],
                    '<a href="https://googleauthenticator.net/" target="_blank" rel="noopener">Google Authenticator</a>',
                    '<a href="https://authy.com/" target="_blank" rel="noopener">Authy</a>') . '<br>'
            .   '<strong>' . sprintf($lang->usercp['lbl_2fa_step'], 2) . '</strong> ' . $lang->usercp['hint_2fa_step2'] . '<br>'
            .   '<strong>' . sprintf($lang->usercp['lbl_2fa_step'], 3) . '</strong> ' . $lang->usercp['hint_2fa_step3']
            . '</div></div>'
            . ucp_sec_open('fa-qrcode', 'primary', $lang->usercp['lbl_2fa_setup'])
            .   '<div class="text-center mb-4">'
            .     '<div id="totp-qr" class="d-inline-block border rounded p-2 bg-white"></div>'
            .     '<div class="mt-2"><div class="st-meta">' . $lang->usercp['lbl_2fa_manual_key'] . '</div>'
            .       '<code class="user-select-all fs-6 fw-bold">' . $secret . '</code></div>'
            .   '</div>'
            .   '<script src="' . $BASEURL . '/scripts/qrcode.min.js"></script>'
            .   '<script>new QRCode(document.getElementById("totp-qr"), {'
            .     'text: ' . json_encode($otpauth_uri, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ', width: 200, height: 200});</script>'
            .   '<form method="post" action="usercp.php" id="tfaConfirmForm">'
            .     '<input type="hidden" name="action" value="do_2fa" />'
            .     '<input type="hidden" name="mode" value="confirm" />'
            .     '<input type="hidden" name="secret" value="' . $secret . '" />'
            .     '<input type="hidden" name="my_post_key" value="' . $postCode . '" />'
            .     '<div class="st-field mx-auto" style="max-width:18rem;">'
            .       '<label class="st-label justify-content-center" for="totp_code"><i class="fa-solid fa-mobile-screen"></i>' . $lang->usercp['lbl_2fa_enter_code'] . '</label>'
            .       '<input type="text" id="totp_code" name="totp_code" class="form-control form-control-lg text-center fw-bold"'
            .         ' placeholder="000000" maxlength="6" autocomplete="one-time-code" autofocus inputmode="numeric" pattern="[0-9]{6}">'
            .     '</div>'
            .     '<div class="d-flex justify-content-center gap-2 mt-3">'
            .       '<button type="submit" class="btn btn-success st-pill"><i class="fa-solid fa-check me-2"></i>' . $lang->usercp['btn_2fa_confirm'] . '</button>'
            .       '<a href="usercp.php?action=2fa" class="btn btn-outline-secondary st-pill">' . $lang->usercp['cancel'] . '</a>'
            .     '</div>'
            .   '</form>'
            . ucp_sec_close()
            . ucp_close();

    // ── Disabled state ────────────────────────────────────────────────────
    } else {
        $tfa_body = ucp_open('fa-shield-halved', 'warning', $lang->usercp['lbl_2fa_short'], $lang->usercp['hint_2fa_not_enabled'])
            . '<div class="st-notice st-soft-warning mb-3"><i class="fa-solid fa-triangle-exclamation fa-lg"></i>'
            .   '<div><strong>' . $lang->usercp['lbl_2fa_not_enabled'] . '</strong><br>' . $lang->usercp['hint_2fa_extra'] . '</div></div>'
            . '<form method="post" action="usercp.php">'
            . '<input type="hidden" name="action" value="do_2fa" />'
            . '<input type="hidden" name="mode" value="enable" />'
            . '<input type="hidden" name="my_post_key" value="' . $postCode . '" />'
            . ucp_sec_open('fa-circle-info', 'info', $lang->usercp['lbl_2fa_short'])
            .   '<p class="text-secondary mb-0">' . $lang->usercp['hint_2fa_about'] . '</p>'
            . ucp_sec_close()
            . '<div class="st-submitbar justify-content-end">'
            .   '<button type="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-shield-halved me-2"></i>' . $lang->usercp['btn_2fa_enable'] . '</button>'
            . '</div>'
            . '</form>'
            . ucp_close();
    }

    echo $tfa_body;

    echo $swal_assets;
    stdfoot();
    exit;
}



// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_sessions — log out everywhere (reset current session)
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_sessions' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $uid = (int)($CURUSER['id'] ?? 0);
    if ($uid <= 0) {
        print_no_permission();
    }

    $mode = $mybb->get_input('mode');

    // ── Remove a specific known device ────────────────────────────────────
    if ($mode === 'remove_device') {
        $device_id = (int)$mybb->get_input('device_id');
        if ($device_id > 0) {
            $db->sql_query_prepared("DELETE FROM user_devices WHERE id = ? AND uid = ?", [$device_id, $uid]);
        }
        redirect('usercp.php?action=sessions', $lang->usercp['flash_device_removed']);
    }

    // ── Log out everywhere ────────────────────────────────────────────────
    // Новый loginkey делает недействительными cookie mybbuser на всех устройствах
    update_loginkey($uid);
    $session->destroy_user_sessions($uid);
    $session->destroy_session();
    my_unsetcookie('mybbuser');

    // Флаг 2FA админки в ЭТОМ браузере. На других устройствах доступ
    // закрывается тем, что их cookie mybbuser больше не действительна.
    unset($_SESSION['admin_2fa_ok_' . $uid], $_SESSION['admin_2fa_fail_count']);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    redirect('member.php?action=login', $lang->usercp['flash_logged_out_all']);
}


// ══════════════════════════════════════════════════════════════════════════
// ACTION: sessions — show active session info
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'sessions') {

    $uid      = (int)$CURUSER['id'];
    $postCode = htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8');

    $session_query = $db->sql_query_prepared("SELECT * FROM sessions WHERE uid = ? LIMIT 1", [$uid]);
    $session_row   = $session_query ? $db->fetch_array($session_query) : null;

    // ── Parse user agent into a friendly browser / OS label ────────────────
    $ua = $session_row['useragent'] ?? '';

    $browser = $lang->usercp['lbl_unknown_browser'];
    if (preg_match('/Edg\/([\d.]+)/i', $ua, $m))        { $browser = 'Microsoft Edge ' . $m[1]; }
    elseif (preg_match('/OPR\/([\d.]+)/i', $ua, $m))    { $browser = 'Opera ' . $m[1]; }
    elseif (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)){ $browser = 'Firefox ' . $m[1]; }
    elseif (preg_match('/Chrome\/([\d.]+)/i', $ua, $m)) { $browser = 'Chrome ' . $m[1]; }
    elseif (preg_match('/Version\/([\d.]+).*Safari/i', $ua, $m)) { $browser = 'Safari ' . $m[1]; }
    elseif (preg_match('/Safari\/([\d.]+)/i', $ua, $m)) { $browser = 'Safari ' . $m[1]; }

    $os = $lang->usercp['lbl_unknown_os'];
    if (str_contains($ua, 'Windows NT 10.0'))      { $os = 'Windows 10/11'; }
    elseif (str_contains($ua, 'Windows NT 6.3'))   { $os = 'Windows 8.1'; }
    elseif (str_contains($ua, 'Windows NT 6.1'))   { $os = 'Windows 7'; }
    elseif (str_contains($ua, 'Windows'))          { $os = 'Windows'; }
    elseif (str_contains($ua, 'Android'))          { $os = 'Android'; }
    elseif (preg_match('/iPhone|iPad|iPod/', $ua)) { $os = 'iOS'; }
    elseif (str_contains($ua, 'Mac OS X'))         { $os = 'macOS'; }
    elseif (str_contains($ua, 'Linux'))            { $os = 'Linux'; }

    $device_icon = match (true) {
        $os === 'Android' || $os === 'iOS' => 'fa-mobile-screen',
        default => 'fa-desktop',
    };

    // ── IP + geolocation ────────────────────────────────────────────────────
    require_once INC_PATH . '/functions_loginattemptcheck.php';

    $ip = '';
    if (!empty($session_row['ip'])) {
        // ip column is varbinary — convert back to readable string
        $ip = inet_ntop($session_row['ip']) ?: '';
    }

    $geo      = $ip ? geo_by_ip($ip) : ['country' => '', 'city' => ''];
    $location = trim(($geo['city'] ?? '') . (($geo['city'] && $geo['country']) ? ', ' : '') . ($geo['country'] ?? ''));
    if ($location === '') {
        $location = $lang->usercp['lbl_unknown_location'];
    }

    $last_active = !empty($session_row['time'])
        ? my_datee('relative', (int)$session_row['time'])
        : $lang->usercp['lbl_unknown'];

    // Current cookie sid vs stored sid — should always match for the active session
    $current_sid = $mybb->cookies['sid'] ?? '';
    $is_current   = !empty($session_row['sid']) && $session_row['sid'] === $current_sid;

    stdhead($lang->usercp['lbl_active_session']);
    build_breadcrumb();

    // UA → [браузер, ОС, иконка] для списка известных устройств
    $parse_ua = static function (string $ua) use ($lang): array {
        $b = $lang->usercp['lbl_unknown_browser'];
        if (preg_match('/Edg\/([\d.]+)/i', $ua, $m))         { $b = 'Microsoft Edge ' . $m[1]; }
        elseif (preg_match('/OPR\/([\d.]+)/i', $ua, $m))     { $b = 'Opera ' . $m[1]; }
        elseif (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)) { $b = 'Firefox ' . $m[1]; }
        elseif (preg_match('/Chrome\/([\d.]+)/i', $ua, $m))  { $b = 'Chrome ' . $m[1]; }
        elseif (preg_match('/Version\/([\d.]+).*Safari/i', $ua, $m)) { $b = 'Safari ' . $m[1]; }
        elseif (preg_match('/Safari\/([\d.]+)/i', $ua, $m))  { $b = 'Safari ' . $m[1]; }

        $o = $lang->usercp['lbl_unknown_os'];
        if (str_contains($ua, 'Windows NT 10.0'))      { $o = 'Windows 10/11'; }
        elseif (str_contains($ua, 'Windows NT 6.3'))   { $o = 'Windows 8.1'; }
        elseif (str_contains($ua, 'Windows NT 6.1'))   { $o = 'Windows 7'; }
        elseif (str_contains($ua, 'Windows'))          { $o = 'Windows'; }
        elseif (str_contains($ua, 'Android'))          { $o = 'Android'; }
        elseif (preg_match('/iPhone|iPad|iPod/', $ua)) { $o = 'iOS'; }
        elseif (str_contains($ua, 'Mac OS X'))         { $o = 'macOS'; }
        elseif (str_contains($ua, 'Linux'))            { $o = 'Linux'; }

        $i = ($o === 'Android' || $o === 'iOS') ? 'fa-mobile-screen' : 'fa-desktop';

        return [$b, $o, $i];
    };

    $devices_html = '';
    $devices_query = $db->sql_query_prepared("SELECT * FROM user_devices WHERE uid = ? ORDER BY last_seen DESC", [$uid]);
    while ($devices_query && ($dev = $db->fetch_array($devices_query))) {
        [$dev_browser, $dev_os, $dev_icon] = $parse_ua((string)$dev['user_agent']);

        $first_seen = my_datee('relative', (int)$dev['first_seen']);
        $last_seen  = my_datee('relative', (int)$dev['last_seen']);

        $devices_html .= '<div class="st-dev">'
            . '<span class="st-ico st-soft-muted"><i class="fa-solid ' . $dev_icon . '"></i></span>'
            . '<div class="st-dev__body">'
            .   '<div class="st-dev__name">' . htmlspecialchars(sprintf($lang->usercp['lbl_browser_on_os'], $dev_browser, $dev_os)) . '</div>'
            .   '<div class="st-meta">' . $lang->usercp['lbl_first_seen'] . ': ' . $first_seen
            .     ' &bull; ' . $lang->usercp['lbl_last_used'] . ': ' . $last_seen . '</div>'
            . '</div>'
            . '<form method="post" action="usercp.php" class="mb-0">'
            .   '<input type="hidden" name="action" value="do_sessions" />'
            .   '<input type="hidden" name="mode" value="remove_device" />'
            .   '<input type="hidden" name="device_id" value="' . (int)$dev['id'] . '" />'
            .   '<input type="hidden" name="my_post_key" value="' . $postCode . '" />'
            .   '<button type="submit" class="st-icon-btn border-0"'
            .     ' data-ags-confirm="' . htmlspecialchars_uni($lang->usercp['js_confirm_remove_device']) . '" data-ags-danger="1">'
            .     '<i class="fa-solid fa-xmark"></i></button>'
            . '</form>'
            . '</div>';
    }

    if ($devices_html === '') {
        $devices_html = '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-solid fa-display"></i></div>'
            . $lang->usercp['hint_no_devices'] . '</div>';
    }

    $this_badge = $is_current
        ? '<span class="st-badge st-soft-success ms-2"><i class="fa-solid fa-circle-check"></i>' . $lang->usercp['lbl_this_device'] . '</span>'
        : '';

    echo ucp_open('fa-shield-halved', 'primary', $lang->usercp['lbl_session_security'], $lang->usercp['hint_session_sub'])

        . '<div class="st-notice st-soft-info mb-3"><i class="fa-solid fa-circle-info"></i>'
        .   '<div><strong>' . $lang->usercp['lbl_one_session'] . '</strong><br>' . $lang->usercp['hint_one_session'] . '</div></div>'

        // Текущая сессия
        . '<div class="st-card st-form-card"><div class="st-card__body d-flex align-items-start gap-3">'
        .   '<div class="st-head__icon st-soft-primary"><i class="fa-solid ' . $device_icon . '"></i></div>'
        .   '<div class="min-w-0">'
        .     '<div class="st-title fs-5">' . htmlspecialchars(sprintf($lang->usercp['lbl_browser_on_os'], $browser, $os)) . $this_badge . '</div>'
        .     '<div class="st-head__meta">'
        .       '<span><i class="fa-solid fa-location-dot"></i>' . htmlspecialchars($location) . '</span>'
        .       '<span><i class="fa-solid fa-network-wired"></i>' . htmlspecialchars($ip ?: $lang->usercp['lbl_unknown_ip']) . '</span>'
        .       '<span><i class="fa-solid fa-clock"></i>' . $lang->usercp['lbl_last_active'] . ': ' . $last_active . '</span>'
        .     '</div>'
        .   '</div>'
        . '</div></div>'

        // Известные устройства
        . '<div class="st-card st-form-card">'
        .   '<div class="st-card__head"><span class="st-ico st-soft-muted"><i class="fa-solid fa-list"></i></span>'
        .   '<span>' . $lang->usercp['lbl_known_devices'] . '</span></div>'
        .   $devices_html
        . '</div>'

        // Выйти везде
        . '<form method="post" action="usercp.php" class="st-card st-danger-box st-form-card">'
        .   '<input type="hidden" name="action" value="do_sessions" />'
        .   '<input type="hidden" name="my_post_key" value="' . $postCode . '" />'
        .   '<div class="flex-grow-1 min-w-0 text-secondary">' . $lang->usercp['hint_logout_everywhere'] . '</div>'
        .   '<button type="submit" class="btn btn-danger st-pill"'
        .     ' data-ags-confirm="' . htmlspecialchars_uni($lang->usercp['js_confirm_logout_all']) . '" data-ags-danger="1">'
        .     '<i class="fa-solid fa-right-from-bracket me-2"></i>' . $lang->usercp['btn_logout_everywhere']
        .   '</button>'
        . '</form>'
        . ucp_close();

    echo $swal_assets;
    stdfoot();
    exit;
}









stdfoot();