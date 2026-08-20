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
    'changename'        => $lang->usercp['nav_changename'],
    'do_changename'     => $lang->usercp['nav_changename'],
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
	'2fa'               => 'Two-Factor Authentication',
    'do_2fa'            => 'Two-Factor Authentication',
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
    $isSvg = str_starts_with($useravatar['image'], '<svg');
    if ($isSvg) {
        return '<div style="position:relative;display:inline-block;">'
             . '<div id="avatarImage" class="rounded img-fluid" style="cursor:pointer;">'
             . $useravatar['image']
             . '</div>'
             . '<input type="file" id="avatarInput" name="avatarupload" style="display:none;" accept="image/*">'
             . '</div>';
    }
    return '<div style="position:relative;display:inline-block;">'
         . '<img id="avatarImage" class="rounded img-fluid" src="' . $useravatar['image'] . '" alt="" '
         . $useravatar['width_height'] . ' style="cursor:pointer;" data-original-avatar="' . $useravatar['image'] . '">'
         . '<input type="file" id="avatarInput" name="avatarupload" style="display:none;" accept="image/*">'
         . '</div>';
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
// ACTION: do_changename
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_changename' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $errors = [];
    $plugins->run_hooks('usercp_do_changename_start');

    if (!validate_password_from_uid($CURUSER['id'], $mybb->get_input('password'))) {
        $errors[] = $lang->usercp['error_invalidpassword'];
    } else {
        require_once INC_PATH . '/datahandlers/user.php';
        $userhandler = new UserDataHandler('update');
        $userhandler->set_data([
            'uid'      => $CURUSER['id'],
            'username' => $mybb->get_input('username'),
        ]);

        if (!$userhandler->validate_user()) {
            $errors = $userhandler->get_friendly_errors();
        } else {
            $userhandler->update_user();
            $plugins->run_hooks('usercp_do_changename_end');
            redirect('usercp.php?action=changename', $lang->usercp['redirect_namechanged']);
        }
    }

    if ($errors) {
        $errors = inline_error($errors);
        $mybb->input['action'] = 'changename';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: changename
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'changename') {
    $plugins->run_hooks('usercp_changename_start');
    $username = $errors ? htmlspecialchars_uni($mybb->get_input('username')) : '';
    $plugins->run_hooks('usercp_changename_end');

    stdhead($lang->usercp['change_username']);
    build_breadcrumb();
    
	
	
$postCode = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');
$minLen   = (int)($minnamelength ?? 3);
$maxLen   = (int)($maxnamelength ?? 25);

 
$lang_change_username  = htmlspecialchars($lang->usercp['change_username']      ?? '', ENT_QUOTES, 'UTF-8');
$lang_password_confirm = htmlspecialchars($lang->usercp['password_confirmation'] ?? '', ENT_QUOTES, 'UTF-8');
$lang_new_username     = htmlspecialchars($lang->usercp['new_username']          ?? '', ENT_QUOTES, 'UTF-8');
$lang_update_username  = htmlspecialchars($lang->usercp['update_username']       ?? '', ENT_QUOTES, 'UTF-8');
 

 
$_tpl_out =
'<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/usercp_profile.css">
<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/change_username.css">
 
<form action="usercp.php" method="post">
<input type="hidden" name="my_post_key" value="' . $postCode . '" />
 
<div class="container-md py-4">
<div class="row g-4">
 
    <div class="col-lg-3">
        ' . $usercpnav . '
    </div>
 
    <div class="col-lg-9">
 
        ' . $errors . '
 
        <div class="card">
            <div class="card-body">
 
                <!-- Заголовок (мобильный) -->
                <div class="bg-nav p-2 rounded text-16 d-lg-none mb-3">
                    <i class="fas fa-user-pen me-2 text-primary"></i> ' . $lang_change_username . '
                </div>
 
                <div class="row g-4">
 
                    <!-- Иконка-заголовок (десктоп) -->
                    <div class="col-lg-3 d-none d-lg-flex">
                        <div class="section-title-left text-center w-100">
                            <i class="fas fa-user-edit section-icon-large"></i>
                            <div class="fw-bold mt-2">' . $lang_change_username . '</div>
                            <small class="text-muted">Change your display name</small>
                        </div>
                    </div>
 
                    <div class="col-lg-9">
 
                        <!-- Уведомление -->
                        <div class="info-hint mb-4">
                            <i class="fas fa-shield-alt me-2 text-info"></i>
                            <strong>Important Notice:</strong>
                            Changing your username will affect how you appear across the entire site.
                            Some restrictions may apply.
                        </div>
 
                        <!-- Подтверждение паролем -->
                        <div class="form-group mb-4 pb-3 border-bottom">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock text-warning me-1"></i>
                                ' . $lang_password_confirm . '
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control pe-5"
                                       name="password" id="password"
                                       placeholder="Enter your current password"
                                       autocomplete="current-password" />
                                <button type="button"
                                        class="btn btn-link position-absolute end-0 top-50 translate-middle-y me-1 text-muted toggle-password"
                                        data-target="password"
                                        aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i> Enter your current password to confirm identity
                            </small>
                        </div>
 
                        <!-- Новый никнейм -->
                        <div class="form-group mb-4">
                            <label for="username" class="form-label">
                                <i class="fas fa-user-circle text-primary me-1"></i>
                                ' . $lang_new_username . '
                            </label>
                            <input type="text" class="form-control"
                                   name="username" id="username"
                                   maxlength="' . $maxLen . '"
                                   value="' . $username . '"
                                   placeholder="New username"
                                   autocomplete="username" />
                            <div id="usernameFeedback" class="username-feedback"></div>
                            <div class="char-counter mt-1" id="charCounter">
                                <i class="fas fa-text-height me-1"></i>
                                <span id="usernameLength">0</span> / ' . $maxLen . ' characters
                                <span id="minLengthHint" class="ms-2">
                                    <i class="fas fa-info-circle me-1"></i> Minimum: ' . $minLen . ' characters
                                </span>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i> Username must be unique and follow community guidelines
                            </small>
                        </div>
 
                    </div>
                </div>
 
            </div><!-- card-body -->
 
            <div class="card-footer text-center">
                <input type="hidden" name="action" value="do_changename" />
                <!-- FIX: было две иконки (fa-save + fa-user-pen) — оставлена одна -->
                <button type="submit" class="btn btn-primary"
                        name="submit"
                        value="' . $lang_update_username . '"
                        id="submitBtn">
                    <i class="fas fa-user-pen me-2"></i> ' . $lang_update_username . '
                </button>
            </div>
 
        </div><!-- card -->
    </div><!-- col-lg-9 -->
</div><!-- row -->
</div><!-- container -->
</form>
 
<script>
window.MIN_USERNAME_LENGTH = ' . $minLen . ';
window.MAX_USERNAME_LENGTH = ' . $maxLen . ';
</script>
<script src="' . $BASEURL . '/scripts/username-validation.js"></script>';
	
	
	
	
	
	
	
	
	
	echo $_tpl_out;
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
        'additionalgroups'  => $CURUSER['additionalgroups'],
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
        
		$pms_from_buddys = '<div class="form-check">
  <input type="checkbox" class="form-check-input" name="receivefrombuddy" id="receivefrombuddy" value="1" '.$receivefrombuddycheck.' />
  <label class="form-check-label" for="flexSwitchCheckDefault">'.$lang->usercp['receive_from_buddy'].'</label>
</div>';

    }

    if ((int) ($enablepms ?? 1) !== 0) 
	{ 
	
	$pms = '<div class="form-check">
  <input type="checkbox" class="form-check-input" name="receivepms" id="receivepms" value="1" '.$receivepmscheck.' />
  <label class="form-check-label" for="flexSwitchCheckDefault">'.$lang->usercp['receive_pms'].'</label>
</div>
'.$pms_from_buddys.'
<div class="form-check">
  <input type="checkbox" class="form-check-input" name="pmnotice" id="pmnotice" value="1"'.$pmnoticecheck.' />
  <label class="form-check-label" for="flexSwitchCheckDefault">'.$lang->usercp['pm_notice'].'</label>
</div>
<div class="form-check">
  <input type="checkbox" class="form-check-input" name="pmnotify" id="pmnotify" value="1" '.$pmnotifycheck.' />
  <label class="form-check-label" for="flexSwitchCheckDefault">'.$lang->usercp['pm_notify'].'</label>
</div>
<div class="form-check">
  <input type="checkbox" class="form-check-input" name="buddyrequestspm" id="buddyrequestspm" value="1" '.$buddyrequestspmcheck.' />
  <label class="form-check-label" for="flexSwitchCheckDefault">'.$lang->usercp['buddyrequests_pm'].'</label>
</div>'; 
	
	} 
	else 
	{ 
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
    $tppselect = $pppselect = '';
    if ($usertppoptions) {
        $tppoptions = '';
        foreach (array_map('trim', explode(',', $usertppoptions)) as $val) {
            $selected = (isset($user['threadsperpages']) && $user['threadsperpages'] == $val) ? ' selected="selected"' : '';
            $tpp_option = sprintf($lang->usercp['tpp_option'], $val);
            $tppoptions .= '<option value="' . $val . '"' . $selected . '>' . $tpp_option . '</option>';
        }
        
		$tppselect = '<div class="mb-2 pb-3">
	<label for="tpp">'.$lang->usercp['tpp'].'</label>
<select name="tpp" class="form-select form-select-sm border pe-5 w-auto">
<option value="">'.$lang->usercp['use_default'].'</option>
'.$tppoptions.'
</select>
</div>';
		
    }

    if ($userpppoptions) {
        $pppoptions = '';
        foreach (array_map('trim', explode(',', $userpppoptions)) as $val) {
            $selected = (isset($user['postsperpage']) && $user['postsperpage'] == $val) ? ' selected="selected"' : '';
            $ppp_option = sprintf($lang->usercp['ppp_option'], $val);
            $pppoptions .= '<option value="' . $val . '"' . $selected . '>' . $ppp_option . '</option>';
        }
        
		$pppselect = '<div class="mb-2 pb-3">
	<label for="ppp">'.$lang->usercp['post_per_page'].'</label>
<select name="ppp" class="form-select form-select-sm border pe-5 w-auto">
<option value="">'.$lang->usercp['use_default'].'</option>
'.$pppoptions.'
</select>
</div>';
		
		
    }

    // Torrents-per-page select
    $pppselect2 = '';
    $pppoptions2 = '';
    foreach (array_map('trim', explode(',', '5,10,15,20,25,30,40,50')) as $val) {
        $selected = (isset($CURUSER['torrentsperpage']) && $CURUSER['torrentsperpage'] == $val) ? ' selected="selected"' : '';
        $ppp_option2 = sprintf($lang->usercp['tp_option'], $val);
        $pppoptions2 .= '<option value="' . $val . '"' . $selected . '>' . $ppp_option2 . '</option>';
    }
    
	
	$pppselect2 = '<div class="mb-2 pb-3">
	<label for="tp">'.$lang->usercp['tppp'].'</label>
<select name="tp" class="form-select form-select-sm border pe-5 w-auto">
<option value="">'.$lang->usercp['use_default'].'</option>
'.$pppoptions2.'
</select>
</div>';
	
	

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
    
	$canbeinvisible = '<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->usercp['login_cookies_privacy'].'</div>
	<div class="row g-3 m-auto border-bottom pb-4 pt-0 mb-2">
		<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
			'.$lang->usercp['login_cookies_privacy'].'
			
		</div>
		<div class="col">
			
<div class="form-check">
  <input type="checkbox" class="form-check-input" name="invisible" id="invisible" value="1" '.$invisiblecheck.' />
  <label class="form-check-label fw-normal" for="flexSwitchCheckDefault">'.$lang->usercp['invisible_mode'].'</label>
	</div>	
		</div>
		</div>';
		




    $plugins->run_hooks('usercp_options_end');
    stdhead($lang->usercp['edit_options']);
    build_breadcrumb();
    
	$_tpl_out = '
	
	<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - '.$lang->usercp['edit_options'].' | Настройки профиля</title>
    
	<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/usercp_options.css">

<style>	
	.category-icon-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.cat-pick-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 18px;
    min-width: 90px;
    border: 1.5px solid #dee2e6;
    border-radius: 14px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #6c757d;
    font-size: 0.78rem;
    font-weight: 500;
    line-height: 1.2;
    text-align: center;
}

.cat-pick-btn i {
    font-size: 1.8rem;
    transition: transform 0.2s ease;
}

.cat-pick-btn:hover {
    border-color: #0d6efd;
    color: #0d6efd;
    background: #f0f5ff;
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(13,110,253,0.15);
}

.cat-pick-btn:hover i {
    transform: scale(1.2);
}

.cat-pick-btn.active {
    border-color: #0d6efd;
    border-width: 2px;
    background: #e7f1ff;
    color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13,110,253,0.12);
    transform: translateY(-2px);
}

.cat-pick-btn.active span {
    font-weight: 700;
}


</style>



    
</head>
<body>

<form action="usercp.php" method="post">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="container-md py-4">
<div class="row g-4">
<div class="col-lg-3">
    '.$usercpnav.'
</div>
<div class="col-lg-9">

    '.$errors.'				
    <div class="card">
        <div class="card-body">
            
            '.$canbeinvisible.'
            
            <!-- ========= MESSAGING & NOTIFICATION SECTION ========= -->
            <div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">
                <i class="fas fa-comment-dots me-2 text-primary"></i> '.$lang->usercp['messaging_notification'].'
            </div>
            <div class="row g-3 m-auto border-bottom pb-4 pt-0 mb-2">
                <div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
                    <i class="fas fa-envelope-open-text me-2 text-info"></i> '.$lang->usercp['messaging_notification'].'
                </div>
                <div class="col">
                    <!-- Comment PM -->
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="commentpm" id="commentpm" value="1" '.$allowcommentpm.' />
                        <label class="form-check-label" for="commentpm">
                            <i class="fas fa-paper-plane text-primary"></i> '.$lang->usercp['pm10'].'
                        </label>
                    </div>
                    <!-- Allow Notices -->
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="allownotices" id="allownotices" value="1" '.$allownoticescheck.' />
                        <label class="form-check-label" for="allownotices">
                            <i class="fas fa-bell text-warning"></i> '.$lang->usercp['allow_notices'].'
                        </label>
                    </div>
                    <!-- Hide email / allow emails -->
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="hideemail" id="hideemail" value="1" '.$hideemailcheck.' />
                        <label class="form-check-label" for="hideemail">
                            <i class="fas fa-at text-success"></i> '.$lang->usercp['allow_emails'].'
                        </label>
                    </div>
                    '.$pms.'
                    <!-- Buddy requests auto -->
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="buddyrequestsauto" id="buddyrequestsauto" value="1" '.$buddyrequestsautocheck.' />
                        <label class="form-check-label" for="buddyrequestsauto">
                            <i class="fas fa-user-plus text-secondary"></i> '.$lang->usercp['buddyrequests_auto'].'
                        </label>
                    </div>
                    
                    <!-- Subscription method with icon -->
                    <div class="form-group mt-4">
                        <label class="fw-semibold mb-2"><i class="fas fa-bell-ring me-2 text-info"></i> '.$lang->usercp['subscription_method'].'</label>
                        <select name="subscriptionmethod" id="subscriptionmethod" class="form-select form-select-sm border w-auto pe-5">
                            <option value="0" '.$no_auto_subscribe_selected.'><i class="fas fa-ban"></i> '.$lang->usercp['no_auto_subscribe'].'</option>
                            <option value="1" '.$no_subscribe_selected.'><i class="fas fa-eye-slash"></i> '.$lang->usercp['no_subscribe'].'</option>
                            <option value="2" '.$instant_email_subscribe_selected.'><i class="fas fa-envelope"></i> '.$lang->usercp['instant_email_subscribe'].'</option>
                            <option value="3" '.$instant_pm_subscribe_selected.'><i class="fas fa-comment-dots"></i> '.$lang->usercp['instant_pm_subscribe'].'</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- ========= TORRENT CATEGORY NOTIFICATIONS ========= -->
            <div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3 mt-3">
                <i class="fas fa-bell me-1"></i> Torrent Category Notifications
            </div>
            <div class="row g-3 m-auto border-bottom pb-4 pt-0 mb-2">
                <div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
                    <i class="fas fa-bell me-1 fa-fw text-warning"></i> Category Notifications
                </div>
                <div class="col">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle me-1 text-info"></i>
                        Select categories you want to be notified about when a new torrent is uploaded.
                    </p>
                    <!-- PM / Email toggles with beautiful icons -->
                    <div class="d-flex gap-4 mb-3 flex-wrap">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="pmnotif" id="pmnotif" value="yes" '.$pmnotif.' />
                            <label class="form-check-label" for="pmnotif">
                                <i class="fas fa-envelope me-1 text-primary"></i> <strong>PM Notification</strong>
                            </label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="emailnotif" id="emailnotif" value="yes" '.$emailnotif.' />
                            <label class="form-check-label" for="emailnotif">
                                <i class="fas fa-at me-1 text-success"></i> <strong>Email Notification</strong>
                            </label>
                        </div>
                    </div>
                  
<!-- Category Subscriptions -->
<input type="hidden" name="cat_subscriptions" id="catSubsSelected" value="'.htmlspecialchars_uni($current_cats).'">
<div class="category-icon-picker" id="catSubsPicker">
    '.$category_subscriptions.'
</div>
<div class="mt-2 small text-muted">
    <i class="fas fa-info-circle me-1"></i> Click categories to toggle subscription
</div>

                </div>
            </div>	
            
            <!-- ========= DATE & TIME OPTIONS ========= -->
            <div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3 mt-3">
                <i class="fas fa-calendar-alt me-2"></i> '.$lang->usercp['date_time_options'].'
            </div>
            <div class="row g-3 m-auto border-bottom pb-4 pt-0 mb-2">
                <div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
                    <i class="fas fa-calendar-week me-2 text-secondary"></i> '.$lang->usercp['date_time_options'].'
                </div>
                <div class="col">
                    <!-- Date format -->
                    <div class="mb-3 pb-2">
                        <label class="fw-semibold mb-2"><i class="fas fa-calendar-day me-2 text-primary"></i> '.$lang->usercp['date_format'].'</label>
                        <select name="dateformat" class="form-select form-select-sm border pe-5 w-auto">
                            <option value="0"><i class="fas fa-globe"></i> '.$lang->usercp['use_default'].'</option>
                            '.$date_format_options.'
                        </select>
                    </div>
                    <!-- Time format -->
                    <div class="mb-3 pb-2">
                        <label class="fw-semibold mb-2"><i class="fas fa-clock me-2 text-success"></i> '.$lang->usercp['time_format'].'</label>
                        <select name="timeformat" class="form-select form-select-sm border pe-5 w-auto">
                            <option value="0"><i class="fas fa-globe"></i> '.$lang->usercp['use_default'].'</option>
                            '.$time_format_options.'
                        </select>
                    </div>
                    <!-- Time offset -->
                    <div class="mb-2">
                        <label class="fw-semibold"><i class="fas fa-globe-americas me-2 text-info"></i> '.$lang->usercp['time_offset'].'</label>
                        '.$tzselect.'
                    </div>
                    <!-- DST correction -->
                    <div class="mt-3">
                        <label class="fw-semibold mb-2"><i class="fas fa-sun me-2 text-warning"></i> '.$lang->usercp['dst_correction'].'</label>
                        <select name="dstcorrection" class="form-select form-select-sm border w-auto pe-5">
                            <option value="2" '.$dst_auto_selected.'><i class="fas fa-robot"></i> '.$lang->usercp['dst_correction_auto'].'</option>
                            <option value="1" '.$dst_enabled_selected.'><i class="fas fa-check-circle text-success"></i> '.$lang->usercp['dst_correction_enabled'].'</option>
                            <option value="0" '.$dst_disabled_selected.'><i class="fas fa-ban text-danger"></i> '.$lang->usercp['dst_correction_disabled'].'</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- ========= FORUM DISPLAY OPTIONS ========= -->
            <div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3 mt-3">
                <i class="fas fa-chalkboard-user me-2"></i> '.$lang->usercp['forum_display_options'].'
            </div>
            <div class="row g-3 m-auto border-bottom pb-4 pt-0 mb-2">
                <div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
                    <i class="fas fa-desktop me-2 text-info"></i> '.$lang->usercp['forum_display_options'].'
                </div>
                <div class="col">
                    <!-- Thread view cutoff (daysprune) -->
                    <div class="mb-3 pb-2">
                        <label class="fw-semibold mb-2"><i class="fas fa-hourglass-half me-2 text-secondary"></i> '.$lang->usercp['thread_view'].'</label>
                        <select name="daysprune" class="form-select form-select-sm border pe-5 w-auto">
                            <option value=""><i class="fas fa-star-of-life"></i> '.$lang->usercp['use_default'].'</option>
                            <option value="1" '.$daysprunesel['1'].'><i class="fas fa-calendar-day"></i> '.$lang->usercp['thread_view_lastday'].'</option>
                            <option value="5" '.$daysprunesel['5'].'><i class="fas fa-calendar-week"></i> '.$lang->usercp['thread_view_5days'].'</option>
                            <option value="10" '.$daysprunesel['10'].'><i class="fas fa-calendar-week"></i> '.$lang->usercp['thread_view_10days'].'</option>
                            <option value="20" '.$daysprunesel['20'].'><i class="fas fa-calendar-alt"></i> '.$lang->usercp['thread_view_20days'].'</option>
                            <option value="50" '.$daysprunesel['50'].'><i class="fas fa-calendar-alt"></i> '.$lang->usercp['thread_view_50days'].'</option>
                            <option value="75" '.$daysprunesel['75'].'><i class="fas fa-calendar-alt"></i> '.$lang->usercp['thread_view_75days'].'</option>
                            <option value="100" '.$daysprunesel['100'].'><i class="fas fa-calendar-alt"></i> '.$lang->usercp['thread_view_100days'].'</option>
                            <option value="365" '.$daysprunesel['365'].'><i class="fas fa-calendar-check"></i> '.$lang->usercp['thread_view_year'].'</option>
                            <option value="9999" '.$daysprunesel['9999'].'><i class="fas fa-infinity"></i> '.$lang->usercp['thread_view_all'].'</option>
                        </select>
                    </div>
                    <!-- Topics per page (tpp) dynamic -->
                    '.$tppselect.'
                </div>
            </div>
            
            <!-- ========= THREAD VIEW OPTIONS ========= -->
            <div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3 mt-3">
                <i class="fas fa-comment-dots me-2"></i> '.$lang->usercp['thread_view_options'].'
            </div>
            <div class="row g-3 m-auto border-bottom pb-4 pt-0 mb-2">
                <div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
                    <i class="fas fa-eye me-2 text-primary"></i> '.$lang->usercp['thread_view_options'].'
                </div>
                <div class="col">
                    
                    <!-- Posts per page (ppp) -->
                    '.$pppselect.'
                    '.$pppselect2.'
                </div>
            </div>
            
            
        
        </div> <!-- card-body -->
        
        <div class="card-footer text-center">
            <input type="hidden" name="action" value="do_options" />
            <button type="submit" class="btn btn-primary" name="regsubmit" value="'.$lang->usercp['update_options'].'">
                <i class="fas fa-sliders-h me-2"></i> '.$lang->usercp['update_options'].'
            </button>
        </div>
    </div> <!-- card -->
</div> <!-- col -->
</div> <!-- row -->
</div> <!-- container -->
</form>


<script src="'.$BASEURL.'/scripts/usercp-options.js"></script>

<script src="'.$BASEURL.'/scripts/theme-switcher.js"></script>

</body>
</html>';
	

	echo $_tpl_out;
	
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

                $emailsubject = 'Account Activation at ' . $SITENAME;
                
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
                    sprintf('Change of Email at ' . $SITENAME),
                    sprintf($lang->usercp['email_changeemail'], $CURUSER['username'], $SITENAME, $CURUSER['email'],
                        $mybb->get_input('email'), $BASEURL, $activationcode, $CURUSER['username'], $CURUSER['id'])
                );
                $plugins->run_hooks('usercp_do_email_verify');
                
				stdok(
                    $lang->usercp['redirect_changeemail_activation'],
                    "Email Change",
                    "Activation link has been sent"
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
   
    $_tpl_out = '
	
	
	<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - '.$lang->usercp['change_email'].'</title>
   
    <link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/usercp_profile.css">
    

      <style>

        * {
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.2s ease;
        }
        
        @media (max-width: 768px) {
            .card-body {
                padding: 1.2rem;
            }
            
            .btn-primary {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
        
        /* Email validation feedback */
        .email-feedback {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        .email-feedback.valid {
            color: var(--icon-success);
        }
        
        .email-feedback.invalid {
            color: var(--icon-danger);
        }
    </style>
   
</head>
<body>


<form action="usercp.php" method="post">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="container-md py-4">
<div class="row g-4">
    <div class="col-lg-3">
        '.$usercpnav.'
    </div>
    <div class="col-lg-9">
        
        '.$errors.'
        
        <div class="card">
            <div class="card-body">
                
                <!-- ========= CHANGE EMAIL SECTION ========= -->
                <div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">
                    <i class="fas fa-envelope me-2 text-primary"></i> '.$lang->usercp['change_email'].'
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block">
                        <div class="section-title-left text-center">
                            <i class="fas fa-at section-icon-large"></i>
                            <div class="fw-bold mt-2">'.$lang->usercp['change_email'].'</div>
                            <small class="text-muted">Update your email address</small>
                        </div>
                    </div>
                    
                    <div class="col-lg-9">
                        
                        <!-- Security Notice -->
                        <div class="info-hint mb-4">
                            <i class="fas fa-shield-alt me-2 text-info"></i>
                            <strong>Security Notice:</strong> Your email address is used for account recovery and important notifications. Please ensure its correct.
                        </div>
                        
                        <!-- Current Password -->
                        <div class="form-group mb-4 pb-3 border-bottom">
                            <label for="password">
                                <i class="fas fa-lock text-warning"></i>
                                '.$lang->usercp['current_password'].'
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control" name="password" id="password" placeholder="Enter your current password" />
                                <button type="button" class="btn btn-link position-absolute end-0 top-0 mt-2 me-2 text-muted toggle-password" style="text-decoration: none;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i> Enter your current password to verify your identity
                            </small>
                        </div>
                        
                        <!-- New Email -->
                        <div class="form-group mb-4 pb-3 border-bottom">
                            <label for="email">
                                <i class="fas fa-envelope text-primary"></i>
                                '.$lang->usercp['new_email'].'
                            </label>
                            <input type="email" class="form-control" name="email" id="email" maxlength="150" value="'.$email.'" placeholder="newemail@example.com" />
                            <div id="emailFeedback" class="email-feedback"></div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i> Your new email address will be used for all communications
                            </small>
                        </div>
                        
                        <!-- Confirm Email -->
                        <div class="form-group mb-4">
                            <label for="email2">
                                <i class="fas fa-check-circle text-success"></i>
                                '.$lang->usercp['confirm_email'].'
                            </label>
                            <input type="email" class="form-control" name="email2" id="email2" maxlength="150" value="'.$email2.'" placeholder="Confirm your new email" />
                            <div id="emailMatchFeedback" class="email-feedback"></div>
                        </div>
                        
                    </div>
                </div>
                
            </div> <!-- card-body -->
            
            <div class="card-footer text-center">
                <input type="hidden" name="action" value="do_email" />
                <button type="submit" class="btn btn-primary" name="submit" value="'.$lang->usercp['update_email'].'" id="submitBtn">
                    <i class="fas fa-save me-2"></i>
                    <i class="fas fa-envelope me-1"></i>
                    '.$lang->usercp['update_email'].'
                </button>
            </div>
        </div> <!-- card -->
        
    </div> <!-- col -->
</div> <!-- row -->
</div> <!-- container -->
</form>


<script src="'.$BASEURL.'/scripts/theme-switcher.js"></script>

<script src="'.$BASEURL.'/scripts/password-toggle.js" defer></script>


</body>
</html>';
	
	
	
	
	echo $_tpl_out;
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
    // stdhead/build_breadcrumb вызываются ПОСЛЕ подготовки данных,
    // чтобы заголовок страницы уже имел правильную lang-строку
    stdhead($lang->usercp['change_password'] ?? '');
    build_breadcrumb();

    echo
'<link rel="stylesheet" href="' . $BASE . '/include/templates/default/style/usercp_profile.css">
<link rel="stylesheet" href="' . $BASE . '/include/templates/default/style/password.css">

<form action="usercp.php" method="post">
<input type="hidden" name="my_post_key" value="' . $postCode . '" />

<div class="container-md py-4">
<div class="row g-4">

    <div class="col-lg-3">
        ' . $usercpnav . '
    </div>

    <div class="col-lg-9">

        ' . $errors . '

        <div class="card">
            <div class="card-body">

                <div class="bg-nav p-2 rounded text-16 d-lg-none mb-3">
                    <i class="fas fa-key me-2 text-primary"></i> ' . $lang_change_password . '
                </div>

                <div class="row g-4">

                    <div class="col-lg-3 d-none d-lg-flex">
                        <div class="text-center p-3 border-end w-100">
                            <i class="fas fa-lock fa-3x mb-3 text-primary"></i>
                            <div class="fw-bold mt-2">' . $lang_change_password . '</div>
                            <small class="text-muted">Update your password for security</small>
                        </div>
                    </div>

                    <div class="col-lg-9">

                        <div class="info-hint mb-4">
                            <i class="fas fa-shield-alt me-2 text-info"></i>
                            <strong>Security Tip:</strong>
                            Use a strong password with at least ' . $minLen . ' characters,
                            including uppercase and lowercase letters, numbers, and special symbols.
                            <br>
                            <small class="mt-1 d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                Password length: ' . $minLen . ' – ' . $maxLen . ' characters
                                ' . $requirecomplexpasswords . '
                            </small>
                        </div>

                        <div class="form-group mb-4 pb-3 border-bottom">
                            <label for="oldpassword" class="form-label">
                                <i class="fas fa-lock-open text-warning me-1"></i>
                                ' . $lang_current_password . '
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control pe-5"
                                       name="oldpassword" id="oldpassword"
                                       placeholder="••••••••" autocomplete="current-password" />
                                <button type="button"
                                        class="btn btn-link position-absolute end-0 top-50 translate-middle-y me-1 text-muted toggle-password"
                                        data-target="oldpassword" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <i class="fas fa-info-circle me-1"></i> Enter your current password for verification
                            </small>
                        </div>

                        <div class="form-group mb-4 pb-3 border-bottom">
                            <label for="password" class="form-label">
                                <i class="fas fa-key text-primary me-1"></i>
                                ' . $lang_new_password . '
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control pe-5"
                                       name="password" id="password"
                                       placeholder="New password"
                                       autocomplete="new-password"
                                       maxlength="' . $maxLen . '" />
                                <button type="button"
                                        class="btn btn-link position-absolute end-0 top-50 translate-middle-y me-1 text-muted toggle-password"
                                        data-target="password" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>

                            <div class="char-counter mt-1" id="passwordCharCounter">
                                <i class="fas fa-text-height me-1"></i>
                                <span id="passwordLength">0</span> / ' . $maxLen . ' characters
                                <span id="minLengthHint" class="ms-2">
                                    <i class="fas fa-info-circle me-1"></i> Minimum: ' . $minLen . ' characters
                                </span>
                            </div>

                            <div class="mt-2" id="complexitySection">
                                <div class="small fw-semibold mb-1">
                                    <i class="fas fa-list-check me-1"></i> Password requirements:
                                </div>
                                <ul class="complexity-list" id="complexityList">
                                    <li id="complexityUppercase">
                                        <i class="fas fa-times-circle"></i> At least one uppercase letter (A-Z)
                                    </li>
                                    <li id="complexityLowercase">
                                        <i class="fas fa-times-circle"></i> At least one lowercase letter (a-z)
                                    </li>
                                    <li id="complexityNumber">
                                        <i class="fas fa-times-circle"></i> At least one number (0-9)
                                    </li>
                                    <li id="complexitySpecial">
                                        <i class="fas fa-times-circle"></i> At least one special character (!@#$%^&amp;* etc.)
                                    </li>
                                </ul>
                            </div>

                            <div class="password-strength mt-2">
                                <div class="strength-bar">
                                    <div class="strength-bar-fill" id="strengthFill"></div>
                                </div>
                                <span id="strengthText" class="text-muted small"></span>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label for="password2" class="form-label">
                                <i class="fas fa-check-circle text-success me-1"></i>
                                ' . $lang_confirm_password . '
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control pe-5"
                                       name="password2" id="password2"
                                       placeholder="Confirm new password"
                                       autocomplete="new-password" />
                                <button type="button"
                                        class="btn btn-link position-absolute end-0 top-50 translate-middle-y me-1 text-muted toggle-password"
                                        data-target="password2" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="small mt-1"></div>
                        </div>

                    </div>
                </div>

            </div>

            <div class="card-footer text-center">
                <input type="hidden" name="action" value="do_password" />
                <button type="submit" class="btn btn-primary"
                        name="submit"
                        value="' . $lang_update_password . '"
                        id="submitBtn">
                    <i class="fas fa-key me-2"></i> ' . $lang_update_password . '
                </button>
            </div>

        </div>
    </div>
</div>
</div>
</form>

<script>
window.passwordConfig = {
    minLength:      ' . $minLen . ',
    maxLength:      ' . $maxLen . ',
    requireComplex: ' . $requireComplex . '
};
</script>
<script src="' . $BASE . '/scripts/password-validation.js"></script>';

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
        $avatar_error = 'error_avatarimagemissing';

    } elseif ($allowremoteavatars) {
        $avatarurl = preg_replace('#script:#i', '', $mybb->input['avatarurl']);
        $ext       = !empty(trim($avatarurl)) ? get_extension($avatarurl) : '';
        $file      = fetch_remote_file($avatarurl);

        if (!$file) {
            $avatar_error = 'The URL you entered for your avatar does not appear to be valid. Please ensure you enter a valid URL';
        } else {
            $tmp_name = $avataruploadpath . '/remote_' . md5((string) time() . mt_rand());
            $fp       = @fopen($tmp_name, 'wb');
            if (!$fp) {
                $avatar_error = 'The URL you entered for your avatar does not appear to be valid. Please ensure you enter a valid URL';
            } else {
                fwrite($fp, $file);
                fclose($fp);
                [, , $type] = @getimagesize($tmp_name);
                [$width, $height] = @getimagesize($tmp_name);
                @unlink($tmp_name);
                if (!$type) {
                    $avatar_error = 'The URL you entered for your avatar does not appear to be valid. Please ensure you enter a valid URL';
                }
            }
        }

        if (empty($avatar_error)) {
            $maxavatardims = '2000x2000';
            if ($width && $height && $maxavatardims !== '') {
                [$maxwidth, $maxheight] = preg_split('/[|x]/', strtolower($maxavatardims));
                if (($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight)) {
                    $avatar_error = 'error_avatartoobig2';
                }
            }
            if (strlen($avatarurl) > 200) {
                $avatar_error = 'error_avatarurltoolong';
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

    echo '<script src="' . $BASEURL . '/scripts/toast.js"></script>';
    echo '<script src="' . $BASEURL . '/scripts/upload_avatar_usercp.js"></script>';

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

    $avatar_remote = '<hr />

<div class="bg-nav p-2 rounded text-19 d-nlock d-sm-block d-md-block d-lg-none mb-3">'.$lang->usercp['avatar_url'].'</div>
	<div class="row g-3 gx-5 mb-2">
		<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
			'.$lang->usercp['avatar_url'].'
			
		</div>
		<div class="col">
	'.$lang->usercp['avatar_url_note'].'
	<input type="text" class="form-control form-control-sm border" name="avatarurl" value="'.$avatarurl.'" />
			'.$lang->usercp['avatar_url_gravatar'].'
		</div>
		</div>
		';

    $removeavatar = !empty($CURUSER['avatar'])
        ? '<button type="submit" class="btn btn-secondary" name="remove" value="Remove Avatar"><i class="fa-solid fa-xmark"></i> &nbsp;Remove Avatar</button>'
        : '';

    $plugins->run_hooks('usercp_avatar_end');
    $avatar_error ??= '';

    stdhead('title');
    build_breadcrumb();
    
	$_tpl_out = '<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - '.$lang->usercp['change_avatar'].'</title>
  
    <link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/usercp_profile.css">
    <style>
        * {
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.2s ease;
        }
        
        @media (max-width: 768px) {
            .card-body {
                padding: 1.2rem;
            }
            
            .btn-primary, .btn-danger {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
        
        /* File input styling */
        .file-input-wrapper {
            margin-top: 0.5rem;
        }
        
        .file-input-wrapper input[type="file"] {
            display: block;
            width: 100%;
            padding: 0.5rem;
            border-radius: 0.75rem;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-primary);
            cursor: pointer;
        }
        
        .file-input-wrapper input[type="file"]::-webkit-file-upload-button {
            background: var(--btn-primary);
            border: none;
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 0.5rem;
            margin-right: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .file-input-wrapper input[type="file"]::-webkit-file-upload-button:hover {
            background: var(--btn-primary-hover);
        }
    </style>
    
</head>
<body>


<form enctype="multipart/form-data" action="usercp.php" method="post" id="avatarForm">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="container-md py-4">
<div class="row g-4">
    <div class="col-lg-3">
        '.$usercpnav.'
    </div>
    <div class="col-lg-9">
        
        '.$avatar_error.'
        
        <div class="card">
            <div class="card-body">
                
                <!-- ========= CHANGE AVATAR SECTION ========= -->
                <div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">
                    <i class="fas fa-image me-2 text-primary"></i> '.$lang->usercp['change_avatar'].'
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block">
                        <div class="section-title-left text-center">
                            <i class="fas fa-user-circle section-icon-large"></i>
                            <div class="fw-bold mt-2">'.$lang->usercp['change_avatar'].'</div>
                            <small class="text-muted">Customize your profile picture</small>
                        </div>
                    </div>
                    
                    <div class="col-lg-9">
                        
                        <!-- Avatar Note / Info -->
                        '.$avatar_note.'
                        
                        <!-- Current Avatar Display -->
                        <div class="info-hint mb-3">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            <strong>Current Avatar:</strong> Your current profile picture is displayed below.
                        </div>
                        
                        <div class="row align-items-center g-4">
                            <div class="col-md-6">
                                <div class="avatar-container">
                                    '.$currentavatar.'
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-camera me-1"></i> Current avatar
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="avatarupload">
                                        <i class="fas fa-cloud-upload-alt text-primary me-2"></i>
                                        Upload New Avatar
                                    </label>
                                    <div class="file-input-wrapper">
                                        '.$avatarupload.'
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i> Supported formats: JPG, PNG, GIF (Max 2MB)
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <hr />
                        
                        <!-- Remote Avatar URL -->
                        <div class="form-group mt-3">
                            <label for="avatarurl">
                                <i class="fas fa-link text-info me-2"></i>
                                '.$lang->usercp['avatar_url'].'
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-nav border-0">
                                    <i class="fas fa-globe"></i>
                                </span>
                                '.$avatar_remote.'
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i> Enter a direct URL to an image (e.g., https://example.com/avatar.jpg)
                            </small>
                        </div>
                        
                    </div>
                </div>
                
            </div> <!-- card-body -->
            
            <div class="card-footer text-center">
                <input type="hidden" name="action" value="do_avatar" />
                <button type="submit" class="btn btn-primary" name="submit" value="'.$lang->usercp['change_avatar'].'">
                    <i class="fas fa-save me-2"></i>
                    <i class="fas fa-image me-1"></i>
                    '.$lang->usercp['change_avatar'].'
                </button>
                '.$removeavatar.'
            </div>
        </div> <!-- card -->
        
    </div> <!-- col -->
</div> <!-- row -->
</div> <!-- container -->
</form>

<script src="'.$BASEURL.'/scripts/theme-switcher.js"></script>


</body>
</html>';


	echo $_tpl_out;
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
    redirect($url, 'The selected thread has been added to your subscriptions list.<br />You will be now returned to the location you came from');
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
        redirect($url, 'redirect_forumsubscriptionadded');
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
        
		$_tpl_out = '
		
		<html>
<head>
<title>'.$lang->usercp['subscribe_to_thread'].'</title>

</head>
<body>

<form action="usercp.php" method="post" name="input">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<input type="hidden" name="action" value="do_addsubscription" />
<input type="hidden" name="tid" value="'.$thread['tid'].'" />
<div class="container-md">
<div class="row">
<div class="col-3 d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">

'.$usercpnav.'
				
</div>
<div class="col">	
	<!-- offcanvas -->
				<div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none">
				<button class="btn btn-primary mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasExample" aria-controls="offcanvasExample">
  <i class="fa-solid fa-circle-arrow-left"></i> &nbsp;'.$lang->ucpmenu.'
</button>
				
				<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasExampleLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasExampleLabel">'.$lang->usercp['ucp_nav_menu'].'</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div><ul class="list-group list-group-flush">
		'.$usercpnav.'</ul>
    </div>
  </div>
</div>
				</div>
				<!-- /offcanvas -->
<div class="card">
<div class="card-body">
	
	<div class="legend mb-4">'.$lang->usercp['subscribe_to_thread'].'</div>

	<div class="ps-3 pe-3"><strong>'.$lang->usercp['notification_method'].'</strong></div>

	<div class="row ps-3 pe-3 mt-4">
		<div class="col-auto align-self-top text-center">
			
			<input type="radio" name="notification" id="notification_none" class="form-check-input" value="0" '.$notification_none_checked.' />
			
		</div>
		<div class="col align-self-center">
		<strong>'.$lang->usercp['no_notification'].'</strong><br /><span class="text-muted">'.$lang->usercp['no_notification_desc'].'</span>
		</div>
	</div>
	
	<div class="row ps-3 pe-3 mt-2">
		<div class="col-auto align-self-top text-center">
			
			<input type="radio" class="form-check-input" name="notification" id="notification_email" value="1" '.$notification_email_checked.' />
			
		</div>
		<div class="col align-self-center">
		<strong>'.$lang->usercp['email_notification'].'</strong><br /><span class="text-muted">'.$lang->usercp['email_notification_desc'].'</span>
		</div>
	</div>
	
	<div class="row ps-3 pe-3 mt-2">
		<div class="col-auto align-self-top text-center">
			
			<input type="radio" class="form-check-input" name="notification" id="notification_pm" value="2" '.$notification_pm_checked.' />
			
		</div>
		<div class="col align-self-center">
		<strong>'.$lang->usercp['pm_notification'].'</strong><br /><span class="text-muted">'.$lang->usercp['pm_notification_desc'].'</span>
		</div>
	</div>

<div class="mt-4 text-end ps-3 pe-3">
	<div class="d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">
<input type="submit" class="btn btn-primary" name="submit" value="'.$lang->usercp['do_subscribe'].'" tabindex="3" accesskey="s" />
	</div>
<div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none">
	<input type="submit" class="btn btn-primary" style="width: 100%" name="submit" value="'.$lang->usercp['do_subscribe'].'" tabindex="3" accesskey="s" />
</div>
	</div>
	</div>
	</div>
	</div>
	</div>
	</div>
</form>

</body>
</html>';
		
		
		
		echo $_tpl_out;
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
    redirect('usercp.php?action=editsig', 'Your signature has been successfully updated.<br />You will be now returned to the signature settings');
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

    $signature = '<div class="card border-0 mb-4">
    <div class="card-header rounded-bottom text-19 fw-bold">
        ' . $heading . '               
    </div>
    <div class="card-body">
        ' . $sigpreview . '            
    </div>
</div>';
}

    if ($mybb->user['suspendsignature'] && $mybb->user['suspendsigtime'] > TIMENOW) {
        $plugins->run_hooks('usercp_editsig_end');
        
	   $editsig = '
	   
	   
	   <html>
<head>
<title>'.$SITENAME.' - '.$lang->userc['edit_sig'].'</title>

</head>
<body>

<div class="container">
<div class="row">
<div class="col-lg-3">

'.$usercpnav.'
				
</div>
<div class="col">
'.$signature.'				
<div class="card">
<div class="card-body">
	<div class="card-header bg-white text-dark border-bottom-0 text-19 fw-bold mt-2 pb-0">
	'.$lang->usercp['edit_sig_error_title'].'</div>
	<ul>
		<li>'.$lang->usercp['edit_sig_no_permission'].'</li>
	</ul>
	</div>
	</div></div>
	</div></div>

</body>
</html>';
	   	
		
		
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
        
		$editsig = '
		
		<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - '.$lang->usercp['edit_sig'].'</title>
     
	<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/usercp_profile.css">
    
 <style>
       
        
        /* Character counter */
        .char-counter {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
            text-align: right;
        }
        
        .char-counter.warning {
            color: var(--icon-warning);
        }
        
        .char-counter.danger {
            color: var(--icon-danger);
        }
        
        /* Editor toolbar styling */
        .editor-toolbar {
            background: var(--bg-nav);
            padding: 0.5rem;
            border-radius: 0.75rem 0.75rem 0 0;
            border-bottom: 1px solid var(--border-color);
        }
    </style>
    
   
</head>
<body>


<form action="usercp.php" method="post">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="container-md py-4">
<div class="row g-4">
    <div class="col-lg-3">
        '.$usercpnav.'
    </div>
    <div class="col-lg-9">
        
        '.$error.'
		
		'.$signature.'
        
        <div class="card">
            
            <!-- Editor Toolbar -->
            <div class="editor-toolbar">
                '.$editor['toolbar'].'
            </div>
            
            <div class="card-body">
                
                <!-- Signature Textarea -->
                <div class="form-group">
                    <textarea style="height: 300px; width: 100%" class="form-control" id="signature" name="signature" placeholder="Write your signature here...">'.$sig.'</textarea>
                    <div class="char-counter" id="charCounter">
                        <i class="fas fa-text-height me-1"></i> <span id="charCount">0</span> characters
                    </div>
                </div>
                
                <div class="mt-3"></div>
                
                <!-- ========= EDIT SIGNATURE SECTION ========= -->
                <div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">
                    <i class="fas fa-signature me-2 text-primary"></i> '.$lang->usercp['edit_sig'].'
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block">
                        <div class="section-title-left text-center">
                            <i class="fas fa-pen-fancy section-icon-large"></i>
                            <div class="fw-bold mt-2">'.$lang->usercp['edit_sig'].'</div>
                            <small class="text-muted">Signature settings</small>
                        </div>
                    </div>
                    
                    <div class="col-lg-9">
                        
                        <!-- Info Notice -->
                        <div class="info-hint mb-3">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            <strong>Signature Guidelines:</strong> Your signature appears below all your posts. Keep it clean and follow community rules.
                        </div>
                        
                        <!-- Radio Options -->
                        <div class="form-check">
                            <input type="radio" class="form-check-input" name="updateposts" value="enable" id="enableSig" />
                            <label class="form-check-label" for="enableSig">
                                <i class="fas fa-check-circle text-success"></i>
                                '.$lang->usercp['enable_sig_posts'].'
                            </label>
                            <div class="text-muted small ms-4 mt-1">
                                <i class="fas fa-info-circle me-1"></i> Update signature in all existing posts
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input type="radio" class="form-check-input" name="updateposts" value="disable" id="disableSig" />
                            <label class="form-check-label" for="disableSig">
                                <i class="fas fa-times-circle text-danger"></i>
                                '.$lang->usercp['disable_sig_posts'].'
                            </label>
                            <div class="text-muted small ms-4 mt-1">
                                <i class="fas fa-info-circle me-1"></i> Remove signature from all existing posts
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input type="radio" class="form-check-input" name="updateposts" value="0" checked="checked" id="leaveSig" />
                            <label class="form-check-label" for="leaveSig">
                                <i class="fas fa-clock text-secondary"></i>
                                '.$lang->usercp['leave_sig_settings'].'
                            </label>
                            <div class="text-muted small ms-4 mt-1">
                                <i class="fas fa-info-circle me-1"></i> Only affect future posts
                            </div>
                        </div>
                        
                    </div>
                </div>
                
            </div> <!-- card-body -->
            
            <div class="card-footer">
                <input type="hidden" name="action" value="do_editsig" />
                <button type="submit" class="btn btn-secondary" name="preview" value="'.$lang->usercp['preview'].'">
                    <i class="fas fa-eye me-2"></i>
                    '.$lang->usercp['preview'].'
                </button>
                <button type="submit" class="btn btn-primary" name="submit" value="'.$lang->usercp['update_sig'].'">
                    <i class="fas fa-save me-2"></i>
                    <i class="fas fa-signature me-1"></i>
                    '.$lang->usercp['update_sig'].'
                </button>
            </div>
        </div> <!-- card -->
        
    </div> <!-- col -->
</div> <!-- row -->
</div> <!-- container -->
</form>

'.$editor['modal'].'


<script src="'.$BASEURL.'/scripts/theme-switcher.js"></script>

<script src="'.$BASEURL.'/scripts/char-counter.js"></script>

</body>
</html>';
		
		
		
		
    }

    stdhead($lang->usercp['edit_sig']);
    build_breadcrumb();
    echo $editsig;
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
        $secret_errors[] = 'Please provide an answer for the secret question';
    } elseif ($passhint === 0 && $hintanswer !== '') {
        $secret_errors[] = 'Please select a secret question';
    }

    require_once INC_PATH . '/datahandlers/user.php';
    $userhandler = new UserDataHandler('update');
    $userhandler->set_data([
        'uid'               => $CURUSER['id'],
        'postnum'           => $CURUSER['postnum'],
        'usergroup'         => $CURUSER['usergroup'],
        'additionalgroups'  => $CURUSER['additionalgroups'],
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
        redirect('usercp.php?action=profile', 'redirect_profileupdated');
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
        redirect($url, $lang->redirect_forumsubscriptionsremoved);
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
            $bgcolor  = alt_trow();
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
                $gotounread   = '<a href="' . $thread['newpostlink'] . '"><i class="fas fa-chevron-right text-primary" title="Go to first unread post"></i></a>';
            } else {
                $folder_label .= $lang->usercp['icon_no_new'];
                $new_class    = 'subject_old';
            }

            $hottopic      = 20;
            $hottopicviews = 150;
            if ($thread['replies'] >= $hottopic || $thread['views'] >= $hottopicviews) { $folder .= 'hot'; $folder_label .= 'icon_hot'; }
            if ($thread['closed'] == 1) { $folder .= 'close'; $folder_label .= 'icon_close'; }
            $folder .= 'folder';

            if ($thread['visible'] == 0) { $bgcolor = 'trow_shaded'; }

            $lastpostdate = '<div><span class="badge bg-light text-dark border">' . my_datee($dateformat, $thread['lastpost']) . '</span><br>'
                          . '<span class="badge bg-light text-dark border">' . my_datee($timeformat, $thread['lastpost']) . '</span></div>';

            $lastposteruid  = $thread['lastposteruid'];
            $lastposter     = htmlspecialchars_uni($thread['lastposter'] ?: 'guest');
            $lastposterlink = ($lastposteruid == 0) ? $lastposter : build_profile_link($lastposter, $lastposteruid);

            $thread['replies'] = ts_nf($thread['replies']);
            $thread['views']   = ts_nf($thread['views']);

            $notification_type = match ((string) $thread['notification']) {
                '2'     => 'Instant PM Notification',
                '1'     => 'Change to email notification',
                default => 'No Notification',
            };

           
			
			$threads .= '<div class="subscription-card p-3 mb-3 rounded-3 border hover-lift transition-all">
    <div class="row align-items-center g-3">
        <!-- Информация о теме -->
        <div class="col-lg-7 col-md-8">
            <div class="d-flex align-items-start gap-3">
                <!-- Переключатель подписки -->
                <div class="form-check form-switch mt-1">
                    <input type="checkbox" 
                           class="form-check-input subscription-checkbox" 
                           name="check['.$thread['tid'].']" 
                           value="'.$thread['tid'].'" 
                           id="thread_'.$thread['tid'].'">
                    <label class="form-check-label" for="thread_'.$thread['tid'].'"></label>
                </div>
                
                <!-- Заголовок темы и информация -->
                <div class="thread-info">
                    <h6 class="mb-1">
                        <a href="'.$thread['threadlink'].'" 
                           class="thread-title text-decoration-none d-block mb-1">
                            '.$gotounread.'
                            <strong>'.$thread['subject'].'</strong>
                            '.$icon.'
                        </a>
                    </h6>
                    
                    <!-- Метод уведомлений -->
                    <div class="notification-method mt-2">
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                            <i class="fas fa-bell me-1"></i>
                            '.$lang->usercp['notification_method'].': 
                            <span class="fw-medium">'.$notification_type.'</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Статистика темы -->
        <div class="col-lg-2 col-md-4">
            <div class="d-flex flex-column gap-2">
                <div class="stat-item d-flex align-items-center">
                    <span class="stat-icon text-primary me-2">
                        <i class="fas fa-comment"></i>
                    </span>
                    <div>
                        <div class="text-muted small">'.$lang->usercp['replies'].'</div>
                        <div class="fw-bold">'.$thread['replies'].'</div>
                    </div>
                </div>
                
                <div class="stat-item d-flex align-items-center">
                    <span class="stat-icon text-warning me-2">
                        <i class="fas fa-eye"></i>
                    </span>
                    <div>
                        <div class="text-muted small">'.$lang->usercp['views'].'</div>
                        <div class="fw-bold">'.$thread['views'].'</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Последнее сообщение и дата -->
        <div class="col-lg-3 col-md-12">
            <div class="last-post-info border-start ps-3">
                <div class="mb-2">
                    <div class="text-muted small mb-1">
                        <i class="far fa-clock me-1"></i>
                        '.$lang->usercp['lastpost'].'
                    </div>
                    <div class="last-poster fw-medium">
                        <i class="fas fa-user-circle me-1 text-secondary"></i>
                        '.$lastposterlink.'
                    </div>
                </div>
                
               
                   
                       
                            '.$lastpostdate.'
                       
                    
                    
					
                    <!-- Быстрые действия -->
                    <div class="d-flex gap-1">
                        <a href="'.$thread['threadlink'].'" 
                           class="btn btn-sm btn-outline-primary" 
                           title="Go to Thread">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                
            </div>
        </div>
    </div>
    
    <!-- Дополнительная информация (по клику) -->
    <div class="row mt-3 d-none additional-info">
        <div class="col-12">
            <div class="card bg-light border-0">
                <div class="card-body py-2">
                    <div class="row small">
                        <div class="col-md-4">
                            <i class="fas fa-calendar-plus me-1 text-muted"></i>
                            <span class="text-muted">Подписана:</span>
                            <span class="ms-1">'.$subscription_date.'</span>
                        </div>
                        <div class="col-md-4">
                            <i class="fas fa-user me-1 text-muted"></i>
                            <span class="text-muted">Автор:</span>
                            <span class="ms-1">'.htmlspecialchars_uni($thread['author'] ?? '—').'</span>
                        </div>
                        <div class="col-md-4">
                            <i class="fas fa-tag me-1 text-muted"></i>
                            <span class="text-muted">Форум:</span>
                            <span class="ms-1">'.htmlspecialchars_uni($thread['forumname'] ?? '—').'</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
    /* Стили для карточки подписки */
    .subscription-card {
        background: white;
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .subscription-card:hover {
        border-color: #28a745;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
    }
    
    .subscription-card.selected {
        border-color: #28a745;
        background-color: rgba(40, 167, 69, 0.05);
    }
    
    .hover-lift:hover {
        transform: translateY(-2px);
    }
    
    .transition-all {
        transition: all 0.3s ease;
    }
    
    /* Стили для переключателя */
    .form-switch .form-check-input {
        width: 3em;
        height: 1.5em;
        cursor: pointer;
        border-color: #adb5bd;
    }
    
    .form-switch .form-check-input:checked {
        background-color: #28a745;
        border-color: #28a745;
    }
    
    .form-switch .form-check-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
    }
    
    /* Стили для статистики */
    .stat-item {
        padding: 6px 10px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    
    .stat-icon {
        font-size: 1.2rem;
    }
    
    /* Стили для заголовка темы */
    .thread-title {
        color: #212529;
        line-height: 1.4;
    }
    
    .thread-title:hover {
        color: #28a745;
    }
    
    .last-post-info {
        border-left: 3px solid #e9ecef !important;
        transition: border-color 0.3s ease;
    }
    
    .subscription-card.selected .last-post-info {
        border-left: 3px solid #28a745 !important;
    }
    
    /* Анимации */
    .additional-info {
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Адаптивность */
    @media (max-width: 992px) {
        .subscription-card .row > div {
            margin-bottom: 15px;
        }
        
        .last-post-info {
            border-left: none !important;
            border-top: 3px solid #e9ecef !important;
            padding-left: 0 !important;
            padding-top: 15px;
        }
        
        .subscription-card.selected .last-post-info {
            border-top: 3px solid #28a745 !important;
        }
    }
    
    @media (max-width: 768px) {
        .subscription-card {
            padding: 15px !important;
        }
    }
</style>


<script type="text/javascript" src="' . $BASEURL . '/scripts/subscription.js"></script>
';
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
        }

        $gobutton = '<button type="submit" class="btn btn-sm btn-primary rounded" value="Go"><i class="fa-solid fa-shuffle"></i> &nbsp;Go</button>';
        
		$remove_options = '<div class="row g-3">
	<div class="col-lg text-center text-sm-center text-md-center text-lg-start text-xl-start text-xxl-start">
		<a href="usercp.php?action=removesubscriptions&amp;my_post_key='.$mybb->post_code.'" class="btn btn-primary btn-sm"><i class="fa-solid fa-xmark"></i> &nbsp;'.$lang->usercp['remove_all_subscriptions'].'</a>
	</div>
	<div class="col-lg-auto text-lg-end">
		<div class="input-group">
            <select name="do" class="form-select form-select-sm border pe-5 w-auto rounded me-2">
			<option value="delete">'.$lang->usercp['delete_subscriptions'].'</option>
			<option value="no_notification">'.$lang->usercp['update_no_notification'].'</option>
			<option value="email_notification">'.$lang->usercp['update_email_notification'].'</option>
			<option value="pm_notification">'.$lang->usercp['update_pm_notification'].'</option>
		</select> 
			'.$gobutton.'
		</div>
	</div>
</div>';
		
		
    } else {
        $remove_options = '';
        $threads = 'You are currently not subscribed to any threads.<p>To subscribe to a thread:</p><ol><li>Navigate to the thread you wish to subscribe to.</li><li>Click the Subscribe to this thread link towards the bottom of the page.</li></ol>';
    }

    $plugins->run_hooks('usercp_subscriptions_end');
    stdhead('title');
    build_breadcrumb();
    
	
	
	$_tpl_out = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - '.$lang->usercp['subscriptions'].'</title>
    <style>
        /* Стили для переключателей подписок */
        .subscription-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
            flex-shrink: 0;
        }
        
        .subscription-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 34px;
        }
        
        .switch-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .subscription-switch input:checked + .switch-slider {
            background-color: #28a745;
        }
        
        .subscription-switch input:checked + .switch-slider:before {
            transform: translateX(24px);
        }
        
        /* Стили для карточек подписок */
        .subscription-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .subscription-card:hover {
            border-color: #28a745;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.1);
            transform: translateY(-1px);
        }
        
        .subscription-card.selected {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.05);
        }
        
        .thread-info {
            flex: 1;
            min-width: 0;
        }
        
        .thread-title {
            color: #212529;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .thread-title:hover {
            color: #28a745;
        }
        
        .subscription-date {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .last-post-info {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
            .subscription-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .subscription-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .last-post-info {
                margin-top: 10px;
            }
        }
    </style>
   
</head>
<body>
  
    
    <div class="container-md py-4">
        <div class="row">
            <!-- Навигация -->
            <div class="col-lg-3 mb-4 mb-lg-0">
                '.$usercpnav.'
            </div>
            
            <!-- Основной контент -->
            <div class="col-lg-9">
                <!-- Форма подписок -->
                <form action="usercp.php" method="post" name="input" id="subscriptionsForm">
                    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                    <input type="hidden" name="action" value="do_subscriptions" />
                    
                    <!-- Карточка подписок -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0 fw-bold text-success">
                                    <i class="fas fa-bell me-2"></i>
                                    '.$lang->usercp['subscriptions'].'
                                    <span class="badge bg-success ms-2">'.$threadcount.'</span>
                                </h5>
                                
                                <!-- Переключатель "Select All" -->
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted small me-2">Select All</span>
                                    <label class="subscription-switch">
                                        <input name="allbox" type="checkbox" class="checkall" value="1">
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <!-- Список подписок -->
                            <div id="subscriptionsList">
                                '.$threads.'
                            </div>
                            
                            <!-- Сообщение если нет подписок -->
                            <div id="noSubscriptions" class="text-center py-5 d-none">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Нет активных подписок</h5>
                                <p class="text-muted">Вы не подписаны ни на одну тему</p>
                            </div>
                        </div>
                        
                        <!-- Опции удаления -->
                        <div class="card-footer bg-light py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="selected-count text-muted small d-none">
                                    Selected: <span id="selectedCount">0</span>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    '.$remove_options.'
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Пагинация -->
                <div class="d-flex justify-content-center">
                    '.$multipage.'
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript" src="' . $BASEURL . '/scripts/subscriptions.js"></script>
	
	
</body>
</html>';

echo $_tpl_out;
	
	
	
	
	
	
	
	
	
	
	
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
        redirect($url, 'The selected forum has been removed from your forum subscriptions list.<br />You will be now returned to where you came from.');
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

        if ($forum['lastpost'] == 0) {
            
			
			$lastpost = '<div class="d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">

<div class="row">
	<div class="col-auto align-self-center">
		<i class="fa-solid fa-user icon"></i>
			</div>
		<div class="col align-self-center">


<p class="fs-6 mb-0">Never</p>
			
		</div>
	</div>
	
	</div>
	
	<div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none">

<div class="row py-3 bg-light mt-2 rounded">
	
		<div class="col align-self-center">


<p class="fs-6 mb-0">Never</p>
			
		</div>
		<div class="col-auto align-self-center">
		<i class="fa-solid fa-user icon"></i>
			</div>
	</div>
	
	</div>';
			
			
        } elseif (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] != 0 && $forum['lastposteruid'] != $CURUSER['id']) {
            
		    $lastpost = '<div class="d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">

<div class="row">
	<div class="col-auto align-self-center">
		<i class="fa-solid fa-lock icon"></i>
			</div>
		<div class="col align-self-center">


<p class="fs-6 mb-0">'.$lang->global['lastvisit_hidden'].'</p>
			
		</div>
	</div>
	
	</div>
	
	<div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none">

<div class="row py-3 bg-light mt-2 rounded">
	
		<div class="col align-self-center">


<p class="fs-6 mb-0">'.$lang->global['lastvisit_hidden'].'</p>
			
		</div>
		<div class="col-auto align-self-center">
		<i class="fa-solid fa-lock icon"></i>
			</div>
	</div>
	
	</div>';
			
			
        } else {
            $forum['lastpostsubject'] = htmlspecialchars_uni($parser->parse_badwords($forum['lastpostsubject']));
            $lastpost_date       = my_datee('relative', $forum['lastpost']);
            $lastposttid         = $forum['lastposttid'];
            $lastposter          = htmlspecialchars_uni($forum['lastposter'] ?: $lang->guest);
            $lastpost_profilelink = ($forum['lastposteruid'] == 0)
                ? $lastposter
                : build_profile_link($lastposter, $forum['lastposteruid']);
            $full_lastpost_subject = $lastpost_subject = htmlspecialchars_uni($forum['lastpostsubject']);
            if (mb_strlen($lastpost_subject) > 25) {
                $lastpost_subject = mb_substr($lastpost_subject, 0, 25) . '...';
            }
            $lastpost_link = get_thread_link($forum['lastposttid'], 0, 'lastpost');
            
			$lastpost = '<div class="d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">

<div class="row">
	<div class="col-auto align-self-center">
		<avatarep_uid_['.$forum['lastposteruid'].']>
			</div>
		<div class="col align-self-center">


<p class="fs-7 mb-0"><a href="'.$lastpost_link.'">'.$lastpost_subject.'</a></p>
			<p class="small text-muted mb-0 text-uppercase">'.$lastpost_date.'</p>
			<p class="small text-muted mb-0">'.$lang->global['by'].' <span class="links">'.$lastpost_profilelink.'</span></p>
			
		</div>
	</div>
	
	</div>
	
	<div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none">

<div class="row py-3 bg-light mt-2 rounded">
	
		<div class="col align-self-center">


<p class="fs-7 mb-0"><a href="'.$lastpost_link.'">'.$lastpost_subject.'</a></p>
			<p class="small text-muted mb-0 text-uppercase">'.$lastpost_date.'</p>
			<p class="small text-muted mb-0">'.$lang->global['by'].' <span class="links">'.$lastpost_profilelink.'</span></p>
			
		</div>
		<div class="col-auto align-self-center">
		<avatarep_uid_['.$forum['lastposteruid'].']>
			</div>
	</div>
	
	</div>';
	
        }

        $showdescriptions = '1';
        if ($showdescriptions == 0) { $forum['description'] = ''; }
		
		$forum_url = get_forum_link($forum['fid']);

        $forums .= '<div class="row py-2 border-bottom">
	<div class="col-lg-7 align-self-center">
		<h6 class="mb-0"><a href="'.$forum_url.'">'.$forum['name'].'</a></h6>
	<a class="text-danger" href="usercp.php?action=removesubscription&amp;type=forum&amp;fid='.$forum['fid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->usercp['unsubscribe'].'</a>
	</div>
	<div class="col-lg-2 align-self-center small">
		'.$lang->usercp['threads'].' '.$threads.'<br />
		'.$lang->usercp['posts'].' '.$posts.'
	</div>
	<div class="col-lg-3">
		'.$lastpost.'
	</div>
</div>';

    }

    if (!$forums) {
        
		
		
		$forums = '
<div class="empty-state text-center py-5">
    <div class="mb-4">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center p-4" style="width: 80px; height: 80px;">
            <i class="fa-regular fa-folder-open fa-3x text-muted"></i>
        </div>
    </div>
    <h5 class="text-muted mb-3">' . $lang->usercp['no_forum_subscriptions'] . '</h5>
    <p class="text-muted small mb-0">
        <i class="fa-regular fa-bell-slash me-1"></i>
        You are not subscribed to any forums
    </p>
    <a href="' . $BASEURL . '/index2.php" class="btn btn-sm btn-outline-primary mt-3">
        <i class="fa-regular fa-compass me-1"></i>
        Browse Forums
    </a>
</div>';
		
		
    }

    $plugins->run_hooks('usercp_forumsubscriptions_end');
    stdhead($lang->usercp['forum_subscriptions']);
    build_breadcrumb();
    
	
	
$_tpl_out = '
    
	<html>
<head>
<title>'.$SITENAME.' - '.$lang->usercp['forum_subscriptions'].'</title>
</head>
<body>
	<div class="container-md">
<div class="row">
<div class="col-lg-3">
'.$usercpnav.'
				
</div>
<div class="col">
			
<div class="card mb-4">
	<div class="card-header bg-white text-dark border-bottom-0 text-19 fw-bold mt-2 pb-0">
		'.$lang->usercp['forum_subscriptions'].'
	</div>
	<div class="card-body">
		'.$forums.'
	</div>
	</div>
	</div>
		</div>
	</div>
</body>
</html>';
 
    
 
	
	echo $_tpl_out;
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


    echo '<style>
    .bookmark-card{transition:transform .3s ease,box-shadow .3s ease,opacity .3s ease;border-radius:12px;opacity:0;transform:translateY(20px);animation:fadeInUp .5s forwards}
    .bookmark-card:hover{transform:translateY(-5px);box-shadow:0 12px 30px rgba(0,0,0,.2)}
    .bookmark-img{width:100%;height:180px;object-fit:cover;transition:transform .3s ease}
    .bookmark-card:hover .bookmark-img{transform:scale(1.05)}
    .bookmark-overlay{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.5);padding:.3rem}
    .bookmark-overlay h6{color:#fff;margin:0;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    @keyframes fadeInUp{to{opacity:1;transform:translateY(0)}}
    @keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
    .animate-bounce{animation:bounce 1.5s infinite}
    </style>';

    $bookmark_cards = '';
    $counter = 0;

    if ($db->num_rows($query) > 0) {
        while ($bm = $db->fetch_array($query)) {
            $torrent_link = get_torrent_link($bm['torrentid']);
            $image        = !empty($bm['t_image']) ? htmlspecialchars_uni($bm['t_image']) : 'default_torrent.png';
            $name         = cutename($bm['name'], 60);
            $delay        = $counter * 0.05;

            $bookmark_cards .= <<<HTML
            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                <div class="card shadow-sm h-100 bookmark-card" style="animation-delay:{$delay}s;">
                    <a href="{$torrent_link}" class="position-relative d-block">
                        <img src="{$image}" class="card-img-top bookmark-img" alt="{$name}">
                        <div class="bookmark-overlay"><h6>{$name}</h6></div>
                    </a>
                    <div class="card-body d-flex flex-column">
                        <div class="mb-2 small text-muted">
                            <span class="me-2"><i class="fa-solid fa-arrow-up me-1"></i>{$bm['seeders']}</span>
                            <span><i class="fa-solid fa-arrow-down me-1"></i>{$bm['leechers']}</span>
                        </div>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <span class="small text-muted">
                                <i class="bi bi-calendar me-1"></i>
            HTML;
            $bookmark_cards .= my_datee($dateformat, $bm['added']);
            $bookmark_cards .= ' <i class="bi bi-clock me-1"></i>' . my_datee($timeformat, $bm['added']);
            $bookmark_cards .= <<<HTML
                            </span>
                            <input type="checkbox" class="form-check-input" name="check[{$bm['torrentid']}]" value="{$bm['torrentid']}">
                        </div>
                    </div>
                </div>
            </div>
            HTML;
            $counter++;
        }

        $remove_options = '
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
            <button type="button" class="btn btn-danger btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#confirmDeleteSelectedModal">
                <i class="fa-solid fa-trash me-1"></i> Delete Selected
            </button>
            <button type="button" class="btn btn-danger btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#confirmDeleteAllModal">
                <i class="fa-solid fa-xmark me-1"></i> Remove All
            </button>
        </div>';
    } else {
        $bookmark_cards = '
        <div class="col-12 text-center py-5 text-muted">
            <div class="mb-3 animate-bounce" style="font-size:4rem;color:rgba(108,117,125,.3);"><i class="fa-regular fa-bookmark"></i></div>
            <h5 class="mb-2 fw-semibold">Your bookmark list is empty</h5>
            <p class="mb-4">Start building your collection by bookmarking your favourite torrents.</p>
            <a href="browse.php" class="btn btn-primary btn-sm shadow-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> Browse Torrents</a>
        </div>';
        $remove_options = '';
    }

    $post_code   = $mybb->post_code;
    $multipage_h = $multipage ? '<div class="mt-3">' . $multipage . '</div>' : '';

    $bookmarks_html = <<<HTML
<div class="container">
    <div class="row">
        <div class="col-lg-3 mb-4">{$usercpnav}</div>
        <div class="col-lg-9">
            <div class="page-header mb-4">
                <h2 class="h3 mb-2 text-dark"><i class="fa-solid fa-bookmark me-2"></i>My Bookmarks</h2>
                <p class="text-muted mb-0">Manage your bookmarked torrents &bull; {$total} total</p>
            </div>
            <form method="post" action="usercp.php" id="bookmarksForm">
                <input type="hidden" name="my_post_key" value="{$post_code}">
                <input type="hidden" name="action" value="do_removebookmarks">
                <div class="row g-4">{$bookmark_cards}</div>
                {$remove_options}
                {$multipage_h}
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteSelectedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">Are you sure you want to delete <span id="selectedCount">0</span> selected bookmark(s)?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteSelectedBtn">Yes, Delete</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmDeleteAllModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">Are you sure you want to remove <strong>all</strong> bookmarks? This cannot be undone.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteAllBtn">Yes, Remove All</button>
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
        <div class="col-12 text-center py-5 text-muted">
            <div class="mb-3 animate-bounce" style="font-size:4rem;color:rgba(108,117,125,.3);">
                <i class="fa-regular fa-bookmark"></i>
            </div>
            <h5 class="mb-2 fw-semibold">Your bookmark list is empty</h5>
            <p class="mb-4">Start building your collection by bookmarking your favourite torrents.</p>
            <a href="browse.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Browse Torrents
            </a>
        </div>`;
        document.querySelector('[data-bs-target="#confirmDeleteSelectedModal"]')?.remove();
        document.querySelector('[data-bs-target="#confirmDeleteAllModal"]')?.remove();
    }

    document.querySelector('[data-bs-target="#confirmDeleteSelectedModal"]')?.addEventListener('click', function () {
        const count = form.querySelectorAll('input[type="checkbox"]:checked').length;
        if (!count) { showToast('Please select at least one bookmark.', false); return; }
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
                        const card = cb.closest('.col-xl-3, .col-lg-4, .col-md-6');
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
                    document.querySelectorAll('.col-xl-3, .col-lg-4, .col-md-6').forEach(c => {
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

    stdhead('My Bookmarks - ' . $SITENAME);
    build_breadcrumb();
    echo $bookmarks_html;
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
                ? '<a href="' . get_comment_link($file['comment_id'], $file['torrentid']) . '#pid' . $file['comment_id'] . '" class="badge text-success bg-success bg-opacity-10 me-1"><i class="fa-solid fa-comment me-1"></i>Comment #' . $file['comment_id'] . '</a>'
                : '';
            $badge_news = $file['news_id']
                ? '<a href="news.php?id=' . $file['news_id'] . '" class="badge text-warning bg-warning bg-opacity-10 me-1"><i class="fa-solid fa-newspaper me-1"></i>News #' . $file['news_id'] . '</a>'
                : '';
            $badge_torrent = $file['torrent_id']
                ? '<a href="' . get_torrent_link($file['torrent_id']) . '" class="badge text-info bg-info bg-opacity-10 me-1"><i class="fa-solid fa-download me-1"></i>Torrent #' . $file['torrent_id'] . '</a>'
                : '';
            $badge_post = $file['postid']
                ? '<a href="' . $postlink . '#pid' . $file['postid'] . '" class="badge text-primary bg-primary bg-opacity-10 me-1"><i class="fa-solid fa-file-lines me-1"></i>Post #' . $file['postid'] . '</a>'
                : '';
            $badge_msg = $file['messages_id']
                ? '<a href="messages.php?id=' . $file['messages_id'] . '" class="badge text-secondary bg-secondary bg-opacity-10 me-1"><i class="fa-solid fa-envelope-open-text me-1"></i>Message #' . $file['messages_id'] . '</a>'
                : '';

            $fn_safe   = htmlspecialchars_uni($file['file_name']);
            $fn_cut    = htmlspecialchars_uni(cutename($file['file_name'], 40));
            $file_url  = htmlspecialchars_uni($file['file_url']);
            $preview   = get_file_preview($file);
            $fid_val   = $file['id'];

            $filelist .= <<<HTML
            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6">
                <div class="card file-card h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
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
                            <a href="{$file_url}" target="_blank" class="btn btn-outline-primary"><i class="fa-solid fa-eye me-2"></i>View File</a>
                        </div>
                    </div>
                </div>
            </div>
            HTML;
        }
        $filelist .= '</div>';

        $remove_options = '
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-danger px-4" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"><i class="fa-solid fa-trash me-2"></i>Delete Selected</button>
                <a href="#" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteAllModal"><i class="fa-solid fa-broom me-2"></i>Delete All</a>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted">Selected: <span id="selected-count" class="fw-semibold">0</span></span>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="select-all">
                    <label class="form-check-label text-muted" for="select-all">Select all</label>
                </div>
            </div>
        </div>';
    } else {
        $filelist       = '<div class="text-center py-5"><div class="empty-state"><i class="fa-regular fa-folder-open fa-4x text-muted mb-4"></i><h4 class="text-muted mb-3">No files uploaded yet</h4><p class="text-muted mb-4">You haven\'t uploaded any files yet.</p></div></div>';
        $remove_options = '';
    }

    $post_code  = $mybb->post_code;
    $mp_html    = $multipage ? '<div class="mt-4"><nav><ul class="pagination justify-content-center">' . $multipage . '</ul></nav></div>' : '';
    $ro_html    = $remove_options ? '<div class="card-footer bg-light py-3">' . $remove_options . '</div>' : '';

    $files_html = <<<HTML
<div class="container">
    <div class="row">
        <div class="col-lg-3 mb-4">{$usercpnav}</div>
        <div class="col-lg-9">
            <div class="page-header mb-4">
                <h2 class="h3 mb-2 text-dark"><i class="fa-solid fa-images me-2"></i>My Files</h2>
                <p class="text-muted mb-0">Manage your uploaded files &bull; {$filecount} total</p>
            </div>
            <form id="fileDeleteForm" action="usercp.php" method="post">
                <input type="hidden" name="my_post_key" value="{$post_code}">
                <input type="hidden" name="action" value="do_remove_files">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fa-solid fa-list me-2"></i>File Manager</h5>
                            <span class="badge bg-primary">{$filecount} files</span>
                        </div>
                    </div>
                    <div class="card-body p-4">{$filelist}</div>
                    {$ro_html}
                </div>
            </form>

            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">Are you sure you want to delete the selected files? This action cannot be undone.</div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" form="fileDeleteForm">Yes, Delete</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal fade" id="confirmDeleteAllModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fa-solid fa-triangle-exclamation me-2"></i>Confirm Deletion</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">Are you sure you want to delete <strong>ALL your files</strong>? This action <strong>cannot</strong> be undone!</div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="usercp.php?action=remove_all_files&amp;my_post_key={$post_code}" class="btn btn-danger">Yes, Delete All</a>
                  </div>
                </div>
              </div>
            </div>
            {$mp_html}
        </div>
    </div>
</div>
HTML;

    stdhead('My Files - ' . $SITENAME);

    echo '<style>
    .file-card{transition:all .3s ease;border-radius:12px;overflow:hidden}
    .file-card:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(0,0,0,.15)!important}
    .file-preview-wrapper{position:relative;height:180px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f8f9fa,#e9ecef);border-radius:10px;overflow:hidden;margin-bottom:1rem}
    .file-checkbox{position:absolute;top:12px;right:12px;z-index:2}
    .file-checkbox-input{opacity:0;position:absolute}
    .file-checkbox-label{width:22px;height:22px;border:2px solid #dee2e6;border-radius:5px;background:#fff;cursor:pointer;transition:all .2s ease;display:block}
    .file-checkbox-input:checked+.file-checkbox-label{background:#0d6efd;border-color:#0d6efd}
    .file-checkbox-input:checked+.file-checkbox-label::after{content:"✓";color:#fff;font-size:.9rem;font-weight:700;display:block;text-align:center;line-height:18px}
    </style>';

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
        header('Location: usercp.php?action=manage_files&msg=' . urlencode("Successfully deleted {$deleted} files"));
        exit;
    }

    header('Location: usercp.php?action=manage_files&msg=' . urlencode('No files selected for deletion'));
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

    header('Location: usercp.php?action=manage_files&msg=' . urlencode("Successfully deleted all {$deleted} files"));
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
            header('Content-type: text/javascript');
            echo '$("#' . $mybb->get_input('manage') . '_' . $mybb->get_input('delete', MyBB::INPUT_INT) . '").remove();';
            if ($new_list === '') {
                echo '$("#' . $mybb->get_input('manage') . '_count").html("0");';
                echo '$("#buddylink").remove();';
                $empty_msg = $isIgnored ? $lang->usercp['ignore_list_empty'] : $lang->usercp['buddy_list_empty'];
                echo '$("#' . $mybb->get_input('manage') . '_list").html("<li>' . $empty_msg . '</li>");';
            } else {
                echo '$("#' . $mybb->get_input('manage') . '_count").html("' . count(explode(',', $new_list)) . '");';
            }
            echo $message_js;
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
            $profile_link     = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['id']);
            $status           = ($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $mybb->usergroup['canviewwolinvis'] == 1) && $user['lastvisit'] != $user['lastactive'])
                ? 'online' : 'offline';
            
			
			
			
$buddy_list .= '<div class="row border-bottom pb-2 mb-2">
    <div class="col">
        '.$profile_link.'
    </div>
    <div class="col text-end">
        <a href="usercp.php?action=do_editlists&amp;my_post_key='.$mybb->post_code.'&amp;manage='.$type.'&amp;delete='.$user['id'].'" onclick="return UserCP.removeBuddy(\'' . $type . '\', ' . $user['id'] . ');" title="'.$lang->usercp['remove_from_list'].'"><i class="fa-solid fa-user-xmark text-danger" title="'.$lang->usercp['remove_from_list'].'"></i></a>
    </div>
</div>';
			
			
			
			
			
			
			
            $buddy_count++;
        }
    }

    $current_buddies = sprintf($lang->usercp['current_buddies'], $buddy_count);
    if (!$buddy_list) 
	{ 
	
	   $buddy_list = ''.$lang->usercp['buddy_list_empty'].''; 


	
	
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
            $profile_link     = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['id']);
            $status           = ($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $usergroups['issupermod'] === 'yes') && $user['lastvisit'] != $user['lastactive'])
                ? 'online' : 'offline';
            
			
			$ignore_list .= '<div class="row border-bottom pb-2 mb-2">
	<div class="col">
		'.$profile_link.'
	</div>
	<div class="col text-end">
		<a href="usercp.php?action=do_editlists&amp;my_post_key='.$mybb->post_code.'&amp;manage='.$type.'&amp;delete='.$user['id'].'" onclick="return UserCP.removeBuddy(\'' . $type . '\', ' . $user['id'] . ');" title="'.$lang->usercp['remove_from_list'].'"><i class="fa-solid fa-user-xmark text-danger" title="'.$lang->usercp['remove_from_list'].'"></i></a>
	</div>
</div>';
			
			
            $ignore_count++;
        }
    }

    $current_ignored_users = sprintf($lang->usercp['current_ignored_users'], $ignore_count);
    if (!$ignore_list) 
	{ 
        $ignore_list = ''.$lang->usercp['ignore_list_empty'].''; 
   
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
                
				$_tpl_out = '<div class="card">
	<div class="card-header bg-white text-dark border-bottom-0 text-19 fw-bold mt-2 pb-0">
	    '.$lang->usercp['buddyrequests_sent'].'
	</div>
	<div class="card-body">
		
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
        $request['username'] = build_profile_link(htmlspecialchars_uni($request['username']), (int) $request['id']);
        $request['date']     = my_datee('relative', $request['date']);
        
		$received_rows .= '<div class="mb-2 pb-3 border-bottom">
<div class="row">
	<div class="col-auto">
		'.$request['username'].'
	</div>
	<div class="col-auto text-desc">
		'.$request['date'].'
	</div>
	<div class="col text-end">
		<a href="'.$BASEURL.'/usercp.php?action=acceptrequest&amp;id='.$request['id'].'&amp;my_post_key='.$mybb->post_code.'" class="links"><i class="fa-solid fa-check"></i> '.$lang->usercp['accept'].'</a> &nbsp;&nbsp;&nbsp; <a href="'.$BASEURL.'/usercp.php?action=declinerequest&amp;id='.$request['id'].'&amp;my_post_key='.$mybb->post_code.'" class="links"><i class="fa-solid fa-xmark"></i> '.$lang->usercp['decline'].'</a>
	</div>
	</div>
</div>';
		
		
    }
    if (!$received_rows) 
	{ 
	    $received_rows = ''.$lang->usercp['no_requests'].''; 
		
	}
    
	$received_requests = '<div class="card mb-4">
	<div class="card-header bg-white text-dark border-bottom-0 text-19 fw-bold mt-2 pb-0">
		'.$lang->usercp['buddyrequests_received'].'
	</div>
	<div class="card-body">
		
		'.$received_rows.'
		
	</div>
</div>';


    // Sent requests
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
    if (!$sent_rows) 
	{ 

         $sent_rows = ''.$lang->usercp['no_requests'].''; 
		 
	}
    
	$sent_requests = '<div class="card">
	<div class="card-header bg-white text-dark border-bottom-0 text-19 fw-bold mt-2 pb-0">
	    '.$lang->usercp['buddyrequests_sent'].'
	</div>
	<div class="card-body">
		
		'.$sent_rows.'
		
	</div>
</div>';


    $plugins->run_hooks('usercp_editlists_end');

    stdhead('title');
    build_breadcrumb();
    
	
	$_tpl_out = '
	<!DOCTYPE html>
<html lang="en">
<head>
    <title>'.$SITENAME.' - '.$lang->usercp['edit_lists'].'</title>
	
	
	<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/buddy.css">
    
    <script type="text/javascript" src="'.$BASEURL.'/scripts/usercp.js?ver=1827"></script>
    <script type="text/javascript">
       lang.remove_buddy = "' . addslashes($lang->usercp['confirm_remove_buddy']) . '";
       lang.remove_ignored = "' . addslashes($lang->usercp['confirm_remove_ignored']) . '";
       lang.adding_buddy = "' . addslashes($lang->usercp['adding_buddy']) . '";
       lang.adding_ignored = "' . addslashes($lang->usercp['adding_ignored']) . '";
       lang.buddylist_error = "' . addslashes($lang->usercp['buddylist_error']) . '";
    </script>

  
 
    
    
</head>
<body>
    
    <form action="usercp.php" method="post" id="buddy" onsubmit="return UserCP.addBuddy(\'buddy\');">
        <input type="hidden" name="action" value="do_editlists" />
        <input type="hidden" name="manage" value="buddy" />
        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
        
        <div class="container-md">
            <div class="row">
                <div class="col-lg-3">
                    '.$usercpnav.'
                </div>
                <div class="col">    
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-user-friends me-2"></i> '.$lang->usercp['edit_buddy_list'].'
                        </div>
                        <div class="card-body">
                            
                            <div class="mb-4">
                                <div class="section-title">'.$lang->usercp['add_buddies'].'</div>
                                <div class="mb-3">
                                    <label class="form-label">'.$lang->usercp['username_or_usernames'].'</label>
                                    <div class="help-text">'.$lang->usercp['add_buddies_desc'].'</div>
                                    <div class="user-select" id="buddy_add_username"></div>
<input type="hidden" name="add_username" id="buddy_add_username_input">

                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-primary" id="buddy_search_btn">
                                        <i class="fas fa-search me-1"></i> '.$lang->usercp['search_user'].'
                                    </button>
                                    <button type="submit" id="buddy_submit" class="btn btn-success">
                                        <i class="fas fa-user-plus me-1"></i> '.$lang->usercp['add_to_buddies'].'
                                    </button>
                                </div>
                            </div>
                            
                            <div class="user-list-container">
                                <div class="section-title">'.$current_buddies.'</div>
                                '.$buddy_list.'
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <form action="usercp.php" method="post" id="ignored" onsubmit="return UserCP.addBuddy(\'ignored\');">
        <input type="hidden" name="action" value="do_editlists" />
        <input type="hidden" name="manage" value="ignored" />
        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
        
        
		<div class="container mt-3">
		<div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-user-slash me-2"></i> '.$lang->usercp['edit_ignore_list'].'
            </div>
            <div class="card-body">
                
                <div class="mb-4">
                    <div class="section-title">'.$lang->usercp['add_ignored_users'].'</div>
                    <div class="mb-3">
                        <label class="form-label">'.$lang->usercp['username_or_usernames'].'</label>
                        <div class="help-text">'.$lang->usercp['add_ignored_users_desc'].'</div>
                        <div class="user-select" id="ignored_add_username"></div>
<input type="hidden" name="add_username" id="ignored_add_username_input">

                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-primary" id="ignored_search_btn">
                            <i class="fas fa-search me-1"></i> '.$lang->usercp['search_user'].'
                        </button>
                        <button type="submit" id="ignored_submit" class="btn btn-danger">
                            <i class="fas fa-ban me-1"></i> '.$lang->usercp['ignore_users'].'
                        </button>
                    </div>
                </div>
                
                <div class="user-list-container">
                    <div class="section-title">'.$current_ignored_users.'</div>
                    '.$ignore_list.'
                </div>
                
            </div>
        </div>
		 </div>
    </form>
    
    <div class="container mt-3">
	'.$received_requests.'
    '.$sent_requests.'
	</div>
</body>
</html>';
	
	
	echo $_tpl_out;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_drafts
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_drafts' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $mybb->input['deletedraft'] = $mybb->get_input('deletedraft', MyBB::INPUT_ARRAY);
    if (empty($mybb->input['deletedraft'])) { error($lang->no_drafts_selected); }

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
    $drafts_count       = 'Saved Drafts (' . ts_nf($draftcount) . ')';
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
            $trow = alt_trow();
            $detail = '';

            if ($draft['threadvisible'] == 1) {
                $draft['threadlink']    = get_thread_link($draft['tid']);
                $draft['threadsubject'] = htmlspecialchars_uni($draft['threadsubject']);
                $detail   = 'Thread: <a href="' . $draft['threadlink'] . '">' . $draft['threadsubject'] . '</a>';
                $editurl  = 'newreply.php?action=editdraft&amp;pid=' . $draft['pid'];
                $id       = $draft['pid'];
                $type     = 'post';
            } elseif ($draft['threadvisible'] == -2) {
                $draft['forumlink'] = get_forum_link($draft['fid']);
                $draft['forumname'] = htmlspecialchars_uni($draft['forumname']);
                $detail   = 'Forum: <a href="' . $draft['forumlink'] . '">' . $draft['forumname'] . '</a>';
                $editurl  = 'newthread.php?action=editdraft&amp;tid=' . $draft['tid'];
                $id       = $draft['tid'];
                $type     = 'thread';
            }

            $draft['subject'] = htmlspecialchars_uni($draft['subject']);
            $savedate = my_datee('relative', $draft['dateline']);
            
			$drafts .= ' <div class="draft-card border rounded-3 p-3 mb-3 bg-white hover-lift transition-all">
    <div class="row align-items-center g-3">
        <!-- Draft Information -->
        <div class="col-lg-7 col-md-6">
            <div class="d-flex align-items-start gap-3">
                <!-- Delete Toggle -->
                <div class="form-check form-switch mt-1">
                    <input type="checkbox" 
                           class="form-check-input draft-checkbox" 
                           name="deletedraft['.$id.']" 
                           value="'.$type.'" 
                           id="draft_'.$id.'">
                    <label class="form-check-label" for="draft_'.$id.'"></label>
                </div>
                
                <!-- Draft Content -->
                <div class="draft-content flex-grow-1">
                    <h6 class="mb-2">
                        <a href="'.$editurl.'" class="text-decoration-none text-dark hover-primary fw-semibold">
                            <i class="fas fa-file-alt me-2 text-primary"></i>
                            '.$draft['subject'].'
                        </a>
                    </h6>
                    
                    <div class="draft-preview mb-2 text-muted small">
                        <i class="fas fa-align-left me-1"></i>
                        '.$detail.'
                    </div>
                    
                    <!-- Quick Labels -->
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-dark border">
                            <i class="fas fa-comments me-1"></i>
                            Draft
                        </span>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info">
                            <i class="fas fa-save me-1"></i>
                            Auto-save
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Date -->
        <div class="col-lg-3 col-md-3">
            <div class="draft-date text-center text-md-start">
                <div class="text-muted small mb-1">
                    <i class="far fa-calendar me-1"></i>
                    Save Date
                </div>
                <div class="fw-medium">
                    <i class="far fa-clock me-1 text-secondary"></i>
                    '.$savedate.'
                </div>
            </div>
        </div>

        <!-- Draft Actions -->
        <div class="col-lg-2 col-md-3">
            <div class="d-flex flex-column flex-md-row align-items-center justify-content-end gap-2">
                <!-- Edit Button -->
                <a href="'.$editurl.'" 
                   class="btn btn-sm btn-primary w-100 w-md-auto" 
                   title="'.$lang->usercp['edit_draft'].'">
                    <i class="fas fa-edit me-1"></i>
                    Edit
                </a>
                
                <!-- Quick Delete Button -->
                <button type="button" 
                        class="btn btn-sm btn-outline-danger delete-single-draft" 
                        data-draft-id="'.$id.'"
                        title="Delete Draft">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Draft Element Styles */
    .draft-card {
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .draft-card:hover {
        border-color: #dc3545;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.1);
    }
    
    .draft-card.selected {
        border-color: #dc3545;
        background-color: rgba(220, 53, 69, 0.05);
    }
    
    .hover-lift:hover {
        transform: translateY(-2px);
    }
    
    .transition-all {
        transition: all 0.3s ease;
    }
    
    .hover-primary:hover {
        color: #0d6efd !important;
    }
    
    /* Toggle Switch Styles */
    .form-switch .form-check-input {
        width: 3em;
        height: 1.5em;
        cursor: pointer;
        border-color: #adb5bd;
    }
    
    .form-switch .form-check-input:checked {
        background-color: #dc3545;
        border-color: #dc3545;
    }
    
    .form-switch .form-check-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }
    
    /* Text Preview Styles */
    .draft-preview {
        max-height: 48px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        line-height: 1.4;
    }
    
    /* Animations */
    .additional-info {
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>';
			
			
			
			
        }
    } else {
        $disable_delete_drafts = 'disabled="disabled"';
        
		//$drafts = ''.$lang->usercp['no_drafts'].'';
		
		$drafts = '
<div class="text-center py-5">
    <div class="mb-4">
        <div class="empty-state-icon mx-auto">
            <i class="fas fa-file-alt fa-4x text-muted"></i>
        </div>
    </div>
    <h5 class="text-muted mb-2">' . $lang->usercp['no_drafts'] . '</h5>
    <p class="text-muted small">You don\'t have any saved drafts. Start creating a new thread or post to save drafts automatically.</p>
    <a href="' . $BASEURL . '/newthread.php" class="btn btn-primary mt-3">
        <i class="fas fa-plus-circle me-2"></i>Create New Thread
    </a>
</div>';
		
		
		
		
		
		
		
    }

    $plugins->run_hooks('usercp_drafts_end');
    stdhead('title');
    build_breadcrumb();
    
	
	$_tpl_out = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>'.$SITENAME.' - '.$lang->usercp['drafts'].'</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
.draft-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 30px;
}
.draft-switch input { display:none; }
.draft-slider {
    position:absolute;
    inset:0;
    background:#ccc;
    border-radius:30px;
    cursor:pointer;
    transition:.3s;
}
.draft-slider:before {
    content:"";
    position:absolute;
    width:22px;
    height:22px;
    left:4px;
    bottom:4px;
    background:#fff;
    border-radius:50%;
    transition:.3s;
}
.draft-switch input:checked + .draft-slider {
    background:#dc3545;
}
.draft-switch input:checked + .draft-slider:before {
    transform:translateX(28px);
}

.draft-card {
    border:1px solid #e9ecef;
    border-radius:10px;
    padding:16px;
    margin-bottom:12px;
    transition:.3s;
}
.draft-card.selected {
    border-color:#dc3545;
    background:rgba(220,53,69,.05);
}
</style>


</head>
<body>

<form action="usercp.php" method="post" id="draftsForm">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">
<input type="hidden" name="action" value="do_drafts">

<div class="container-md py-4">
<div class="row">
<div class="col-lg-3">'.$usercpnav.'</div>

<div class="col-lg-9">
<div class="card shadow-sm">
<div class="card-header d-flex justify-content-between align-items-center">
<h5 class="mb-0">
'.$lang->usercp['drafts'].' <span class="badge bg-secondary">'.$drafts_count.'</span>
</h5>

<div class="d-flex align-items-center gap-2">
<span class="small text-muted">Select All</span>
<label class="draft-switch">
<input type="checkbox" id="checkAll">
<span class="draft-slider"></span>
</label>
</div>
</div>

<div class="card-body" id="draftsList">
'.$drafts.'
</div>

<div class="card-footer d-flex justify-content-between align-items-center">
<div class="selected-count d-none">
Selected: <span id="selectedCount">0</span>
</div>

<button type="submit"
        class="btn btn-danger"
        id="deleteButton"
        name="draftman"
        value="'.$lang->usercp['delete_drafts'].'"
        disabled>
'.$lang->usercp['delete_drafts'].'
</button>
</div>
</div>
</div>
</div>
</div>
</form>

<script type="text/javascript" src="' . $BASEURL . '/scripts/drafts.js"></script>

</body>
</html>'; 
	
	
	echo $_tpl_out;
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
    stdhead('title');
    
	
	$_tpl_out = '<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - '.$lang->usercp['edit_profile'].'</title>
    <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/usercp_profile.css">

    
</head>
<body>
'.$header.'

<form action="usercp.php" method="post" name="input">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="container-md py-4">
    <div class="row g-4">
        <div class="col-lg-3">
            '.$usercpnav.'
        </div>
        <div class="col-lg-9">
            '.$errors.'
            
            <div class="card">
                <div class="card-body">
                    
                    <!-- ========= BIRTHDAY SECTION ========= -->
                    <div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">
                        <i class="fas fa-cake-candles me-2 text-primary"></i> '.$lang->usercp['birthday'].'
                    </div>
                    <div class="row g-3 m-auto border-bottom pb-4 pt-0 mb-2">
                        <div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block">
                            <div class="section-title-left text-center">
                                <i class="fas fa-cake-candles section-icon-large"></i>
                                <div class="fw-bold mt-2">'.$lang->usercp['birthday'].'</div>
                                <small class="text-muted">Your date of birth</small>
                            </div>
                        </div>
                        <div class="col-lg-9">
                            <div class="alert bg-nav mb-3">
                                <div class="row g-2 align-items-end">
                                    <div class="col-auto">
                                        <span class="text-desc"><i class="fas fa-calendar-day me-1"></i> '.$lang->usercp['day'].'</span>
                                        <select name="bday1" class="form-select form-select-sm border">
                                            <option value="">&nbsp;</option>
                                            '.$bdaydaysel.'
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <span class="text-desc"><i class="fas fa-calendar-alt me-1"></i> '.$lang->usercp['month'].'</span>
                                        <select name="bday2" class="form-select form-select-sm border">
                                            <option value="">&nbsp;</option>
                                            <option value="1" '.$bdaymonthsel['1'].'>'.$lang->usercp['month_1'].'</option>
                                            <option value="2" '.$bdaymonthsel['2'].'>'.$lang->usercp['month_2'].'</option>
                                            <option value="3" '.$bdaymonthsel['3'].'>'.$lang->usercp['month_3'].'</option>
                                            <option value="4" '.$bdaymonthsel['4'].'>'.$lang->usercp['month_4'].'</option>
                                            <option value="5" '.$bdaymonthsel['5'].'>'.$lang->usercp['month_5'].'</option>
                                            <option value="6" '.$bdaymonthsel['6'].'>'.$lang->usercp['month_6'].'</option>
                                            <option value="7" '.$bdaymonthsel['7'].'>'.$lang->usercp['month_7'].'</option>
                                            <option value="8" '.$bdaymonthsel['8'].'>'.$lang->usercp['month_8'].'</option>
                                            <option value="9" '.$bdaymonthsel['9'].'>'.$lang->usercp['month_9'].'</option>
                                            <option value="10" '.$bdaymonthsel['10'].'>'.$lang->usercp['month_10'].'</option>
                                            <option value="11" '.$bdaymonthsel['11'].'>'.$lang->usercp['month_11'].'</option>
                                            <option value="12" '.$bdaymonthsel['12'].'>'.$lang->usercp['month_12'].'</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <span class="text-desc"><i class="fas fa-calendar-week me-1"></i> '.$lang->usercp['year'].'</span>
                                        <input type="text" class="form-control form-control-sm border" size="5" maxlength="4" name="bday3" value="'.$bday['2'].'" placeholder="YYYY" />
                                    </div>
                                </div>
                            </div>
                            
                            <hr />
                            
                            <div class="form-group">
                                <label for="birthdayprivacy">
                                    <i class="fas fa-shield-alt text-info"></i>
                                    '.$lang->usercp['birthdayprivacy'].'
                                </label>
                                <select name="birthdayprivacy" class="form-select form-select-sm w-auto">
                                    <option value="all"'.$allselected.'><i class="fas fa-globe me-1"></i> '.$lang->usercp['birthdayprivacyall'].'</option>
                                    <option value="none"'.$noneselected.'><i class="fas fa-lock me-1"></i> '.$lang->usercp['birthdayprivacynone'].'</option>
                                    <option value="age"'.$ageselected.'><i class="fas fa-chart-simple me-1"></i> '.$lang->usercp['birthdayprivacyage'].'</option>
                                </select>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-info-circle me-1"></i> Control who can see your birthday information
                                </small>
                            </div>
                        </div>
                    </div>
  
                </div> <!-- card-body -->
                
                <div class="card-footer text-center">
                    <input type="hidden" name="action" value="do_profile" />
                    <button type="submit" class="btn btn-primary" name="regsubmit" value="'.$lang->usercp['update_profile'].'">
                        <i class="fas fa-save me-2"></i>
                        <i class="fas fa-user me-1"></i>
                        '.$lang->usercp['update_profile'].'
                    </button>
                </div>
            </div> <!-- card -->
        </div> <!-- col -->
    </div> <!-- row -->
</div> <!-- container -->
</form>

<script src="' . $BASEURL . '/scripts/theme-switcher.js"></script>

</body>
</html>'; 
	
	
	echo $_tpl_out;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_attachments
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_attachments' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    require_once INC_PATH . '/functions_upload.php';
    if (!isset($mybb->input['attachments']) || !is_array($mybb->input['attachments'])) {
        error('no_attachments_selected');
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

    if ((int) ($enableattachments ?? 1) === 0) { error('attachments_disabled'); }

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

                $location_badge = '<span class="badge text-primary bg-primary bg-opacity-10 border border-primary border-opacity-25">
                    <i class="fas fa-comment me-1"></i>
                    ' . $lang->usercp['attachments_post'] . '
                </span>
                <a href="' . $attachment['postlink'] . '#pid' . $attachment['pid'] . '"
                   class="text-truncate text-decoration-none text-secondary hover-primary">
                    ' . $attachment['subject'] . '
                </a>';
            } else {
                $comment_link = get_comment_link((int)$attachment['comment_id'], (int)$attachment['cmt_torrentid']);
                $location_badge = '<span class="badge text-success bg-success bg-opacity-10 border border-success border-opacity-25">
                    <i class="fas fa-comments me-1"></i>
                    Comment
                </span>
                <a href="' . $comment_link . '#pid' . $attachment['comment_id'] . '"
                   class="text-truncate text-decoration-none text-secondary hover-primary">
                    Comment #' . (int)$attachment['comment_id'] . '
                </a>';
            }

            $size           = mksize($attachment['filesize']);
            $icon           = get_attachment_icon(get_extension($attachment['filename']));
            $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
            $sizedownloads  = '(' . $size . ', ' . $attachment['downloads'] . ' Downloads)';
            $attachdate     = my_datee('relative', (int)$attachment['dateuploaded']);
            $altbg          = alt_trow();

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
            
			
			$attachments .= '<div class="attachment-item border rounded-3 mb-3 p-3 bg-white hover-shadow transition-all">
    <div class="row align-items-center g-3">
        <!-- Иконка и информация о файле -->
        <div class="col-lg-4 col-md-6">
            <div class="d-flex align-items-center gap-3">
                <!-- Переключатель для выбора -->
                <div class="form-check form-switch">
                    <input type="checkbox" 
                           class="form-check-input attachment-checkbox" 
                           name="attachments['.$attachment['aid'].']" 
                           value="'.$attachment['aid'].'" 
                           id="attachment_'.$attachment['aid'].'">
                    <label class="form-check-label" for="attachment_'.$attachment['aid'].'"></label>
                </div>
                
                <!-- Информация о файле -->
                <div class="d-flex align-items-center gap-2 flex-grow-1" style="min-width:0;">
                    <div class="attachment-icon">
                        '.$icon_html.'
                    </div>
                    <div class="attachment-details" style="min-width:0;overflow:hidden;">
                        <a href="'.$view_url.'" 
                           target="_blank" 
                           class="fw-semibold text-decoration-none text-dark hover-primary d-block text-truncate"
                           title="'.$attachment['filename'].'">
                            '.$attachment['filename'].'
                        </a>
                        <div class="text-muted small mt-1">
                            '.$sizedownloads.'
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Информация о посте / комментарии -->
        <div class="col-lg-4 col-md-6">
            <div class="d-flex align-items-center gap-2">
                '.$location_badge.'
            </div>
        </div>

        <!-- Дата и дополнительные действия -->
        <div class="col-lg-4 col-md-12">
            <div class="d-flex justify-content-between justify-content-lg-end align-items-center gap-3">
                <!-- Дата загрузки -->
                <div class="text-muted small">
                    <i class="far fa-calendar me-1"></i>
                    '.$attachdate.'
                </div>
                
                <!-- Дополнительные кнопки действий -->
                <div class="d-flex gap-2">
                    <!-- Кнопка предпросмотра -->
                    <a href="'.$view_url.'" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-primary" 
                       title="Предпросмотр">
                        <i class="fas fa-eye"></i>
                    </a>
                    
                    <!-- Кнопка скачивания -->
                    <a href="'.$download_url.'" 
                       class="btn btn-sm btn-outline-success" 
                       title="Скачать">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Дополнительная информация (скрытая по умолчанию) -->
    <div class="row mt-3 d-none additional-info">
        <div class="col-12">
            <div class="card bg-light border-0">
                <div class="card-body py-2">
                    <div class="row small text-muted">
                        <div class="col-md-3">
                            <i class="fas fa-file me-1"></i>
                            Тип: <span class="text-dark">'.$attachment['filetype'].'</span>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-expand-alt me-1"></i>
                            Размер: <span class="text-dark">'.$attachment['filesize'].'</span>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-chart-line me-1"></i>
                            Загрузок: <span class="text-dark">'.$attachment['downloads'].'</span>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-user me-1"></i>
                            Загрузил: <span class="text-dark">'.$attachment['username'].'</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .attachment-item {
        transition: all 0.3s ease;
        border: 1px solid #e9ecef;
    }
    
    .attachment-item:hover {
        border-color: #0d6efd;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }
    
    .hover-shadow:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }
    
    .hover-primary:hover {
        color: #0d6efd !important;
    }
    
    .transition-all {
        transition: all 0.3s ease;
    }
    
    /* Кастомный стиль для переключателя */
    .form-switch .form-check-input {
        width: 3em;
        height: 1.5em;
        cursor: pointer;
    }
    
    .form-switch .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    
    .attachment-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        overflow: hidden;
        flex-shrink: 0;
    }

    .attachment-thumb-link {
        display: block;
        width: 100%;
        height: 100%;
    }

    .attachment-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .2s ease;
    }

    .attachment-thumb-link:hover .attachment-thumb {
        transform: scale(1.08);
    }
    
    .additional-info {
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @media (max-width: 768px) {
        .attachment-item .row > div {
            margin-bottom: 10px;
        }
        
        .d-flex.justify-content-end {
            justify-content: flex-start !important;
        }
    }
</style>



<script type="text/javascript" src="' . $BASEURL . '/scripts/usercp_attachments.js"></script>';



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
        $usagenote     = sprintf('- Using ' . $friendlyusage . ' of ' . $attachquota . ' in ' . $totalattachments . ' Attachments');
    } else {
        $usagenote = sprintf($lang->usercp['attachments_usage'], $friendlyusage, $totalattachments);
    }

    $bandwidth     = mksize($bandwidth);
    
	$attachments_present = !empty($attachments);
	$delete_button = '<input type="hidden" name="action" value="do_attachments" />';

    if (!$attachments) {
        
		$attachments = '
        <div class="col-12 text-center py-5 text-muted">
            <div class="mb-3 animate-bounce" style="font-size:4rem;color:rgba(108,117,125,.3);">
                <i class="fa-regular fa-paperclip"></i>
            </div>
            <h5 class="mb-2 fw-semibold">You currently do not have any files attached to your posts</h5>
        </div>';
		
        $usagenote     = '';
        $delete_button = '';
    }

    $plugins->run_hooks('usercp_attachments_end');
    stdhead('title');
    
	$_tpl_out = '
	
	<!DOCTYPE html>
<html lang="eng">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - '.$lang->usercp['attachments_manager'].'</title>

    <style>
        .att-page-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a4fc4 100%);
            border-radius: 16px;
            padding: 1.75rem 2rem;
            color: #fff;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 24px rgba(13,110,253,.25);
        }
        .att-page-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.5rem;
        }
        .att-page-header h2 i {
            margin-right: .5rem;
            opacity: .9;
        }
        .att-page-header p {
            margin: .35rem 0 0;
            opacity: .85;
            font-size: .9rem;
        }

        .att-stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 1.5rem;
        }
        .att-stat-card {
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 14px;
            padding: 1.1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .att-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0,0,0,.07);
        }
        .att-stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .att-stat-icon.is-primary { background: rgba(13,110,253,.1); color: #0d6efd; }
        .att-stat-icon.is-success { background: rgba(25,135,84,.1);  color: #198754; }
        .att-stat-icon.is-info    { background: rgba(13,202,240,.12); color: #0aa2c0; }
        .att-stat-icon.is-warning { background: rgba(255,193,7,.12);  color: #cc9a06; }
        .att-stat-icon.is-danger  { background: rgba(220,53,69,.1);   color: #dc3545; }
        .att-stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1.1;
            color: #212529;
        }
        .att-stat-label {
            font-size: .78rem;
            color: #8a8f98;
            margin-top: 2px;
        }

        .att-card {
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 16px;
            overflow: hidden;
        }
        .att-card-header {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid #f1f2f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .att-card-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.05rem;
            color: #212529;
        }
        .att-card-header h5 i {
            color: #0d6efd;
            margin-right: .5rem;
        }
        .att-card-body { padding: 1.25rem 1.5rem; }

        .att-usage-note {
            background: #f0f6ff;
            border: 1px solid #d6e7ff;
            border-radius: 10px;
            padding: .75rem 1.1rem;
            font-size: .88rem;
            color: #0a4fc4;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        /* Toggle switch */
        .form-switch-custom {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }
        .form-switch-custom input { opacity: 0; width: 0; height: 0; }
        .switch-slider {
            position: absolute; cursor: pointer; inset: 0;
            background-color: #dadfe4;
            transition: .25s;
            border-radius: 28px;
        }
        .switch-slider:before {
            position: absolute; content: "";
            height: 21px; width: 21px; left: 3.5px; bottom: 3.5px;
            background-color: #fff;
            transition: .25s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }
        .form-switch-custom input:checked + .switch-slider { background-color: #0d6efd; }
        .form-switch-custom input:checked + .switch-slider:before { transform: translateX(22px); }

        .att-footer {
            padding: 1.1rem 1.5rem;
            border-top: 1px solid #f1f2f4;
            text-align: center;
            background: #fafbfc;
        }
        .att-delete-btn {
            border: none;
            border-radius: 10px;
            padding: .65rem 1.5rem;
            font-weight: 600;
            background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
            color: #fff;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .att-delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(220,53,69,.35);
        }

        @media (max-width: 768px) {
            .att-page-header { padding: 1.25rem 1.5rem; }
            .att-card-body, .att-card-header { padding: 1rem; }
        }
    </style>

</head>
<body>
    <div class="container-md py-4">
        <div class="row">
            <!-- Навигация -->
            <div class="col-lg-3 mb-4 mb-lg-0">
                '.$usercpnav.'
            </div>

            <!-- Основной контент -->
            <div class="col-lg-9">

                <div class="att-page-header">
                    <h2><i class="fas fa-paperclip"></i>'.$lang->usercp['attachments_manager'].'</h2>
                    <p>'.$totalattachments.' attachments &bull; '.$friendlyusage.' used</p>
                </div>

                <div class="att-stats-row">
                    <div class="att-stat-card">
                        <div class="att-stat-icon is-primary"><i class="fas fa-file"></i></div>
                        <div>
                            <div class="att-stat-value">'.$totalattachments.'</div>
                            <div class="att-stat-label">'.$lang->usercp['attachstats_attachs'].'</div>
                        </div>
                    </div>
                    <div class="att-stat-card">
                        <div class="att-stat-icon is-success"><i class="fas fa-hdd"></i></div>
                        <div>
                            <div class="att-stat-value">'.$friendlyusage.'</div>
                            <div class="att-stat-label">'.$lang->usercp['attachstats_spaceused'].'</div>
                        </div>
                    </div>
                    <div class="att-stat-card">
                        <div class="att-stat-icon is-info"><i class="fas fa-tachometer-alt"></i></div>
                        <div>
                            <div class="att-stat-value">'.$attachquota.'</div>
                            <div class="att-stat-label">'.$lang->usercp['attachstats_quota'].'</div>
                        </div>
                    </div>
                    <div class="att-stat-card">
                        <div class="att-stat-icon is-warning"><i class="fas fa-download"></i></div>
                        <div>
                            <div class="att-stat-value">'.$totaldownloads.'</div>
                            <div class="att-stat-label">'.$lang->usercp['attachstats_totaldl'].'</div>
                        </div>
                    </div>
                    <div class="att-stat-card">
                        <div class="att-stat-icon is-danger"><i class="fas fa-network-wired"></i></div>
                        <div>
                            <div class="att-stat-value">'.$bandwidth.'</div>
                            <div class="att-stat-label">'.$lang->usercp['attachstats_bandwidth'].'</div>
                        </div>
                    </div>
                </div>

                <!-- Форма управления вложениями -->
                <form action="usercp.php" method="post" name="attachmentsmanager">
                    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />

                    <div class="att-card">
                        <div class="att-card-header">
                            <h5><i class="fas fa-list"></i>'.$lang->usercp['attachments_manager'].'</h5>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small">Select All</span>
                                <label class="form-switch-custom">
                                    <input type="checkbox" name="allbox" class="checkall">
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="att-card-body">
                            '.($usagenote ? '<div class="att-usage-note"><i class="fas fa-info-circle"></i>'.$usagenote.'</div>' : '').'

                            <div class="attachments-container">
                                '.$attachments.'
                            </div>
                        </div>

                        '.$delete_button.'
                        '.(!empty($attachments_present) ? '<div class="att-footer">
                            <button type="submit" class="att-delete-btn" name="delete" onclick="return confirm('.$lang->usercp['confirm_deletion'].')">
                                <i class="fas fa-trash-alt me-2"></i>'.$lang->usercp['delete_attachments'].'
                            </button>
                        </div>' : '').'
                    </div>
                </form>

                '.($multipage ? '<div class="mt-4 d-flex justify-content-center">'.$multipage.'</div>' : '').'

            </div>
        </div>
    </div>
<script type="text/javascript" src="' . $BASEURL . '/scripts/attachments.js"></script>
</body>
</html>';
	
	
	
	
	echo $_tpl_out;
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

    echo '<script src="' . $BASEURL . '/scripts/toast.js"></script>';
    echo '<script src="' . $BASEURL . '/scripts/upload_avatar_usercp.js"></script>';

    $useravatar    = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
    $currentavatar = build_avatar_html($useravatar);

    $mybb->user['email'] = htmlspecialchars_uni($CURUSER['email']);
    $usergroup           = htmlspecialchars_uni($groupscache[$CURUSER['usergroup']]['title']);
    $username            = build_profile_link(
        format_name(htmlspecialchars_uni($CURUSER['username']), $CURUSER['usergroup'], $CURUSER['displaygroup']),
        $CURUSER['id']
    );
    $mybb->user['posts'] = ts_nf($CURUSER['postnum']);

    $plugins->run_hooks('usercp_end');
    stdhead('title');
    
	$_tpl_out = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - '.$lang->usercp['user_cp'].'</title>
    <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/usercp.css">
	

</head>

<div class="container-md py-4">
    <div class="row g-4">
        <!-- Навигационная панель -->
        <div class="col-12 col-lg-3">
            <div class="sticky-top" style="top: 20px;">
                '.$usercpnav.'
            </div>
        </div>
        
        <!-- Основной контент -->
        <div class="col">
            <div class="card user-stats-card mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <!-- Аватар -->
                        <div class="col-auto text-center">
                            
                                '.$currentavatar.'
                                <div class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-white" 
                                     style="width: 20px; height: 20px;"></div>
                           
                            <div class="mt-2">
                                <span class="badge-status">Online</span>
                            </div>
                        </div>
                        
                        <!-- Статистика пользователя -->
                        <div class="col">
                            <!-- Имя пользователя -->
                            <div class="stat-item border-bottom border-custom mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user icon-user me-3"></i>
                                    <div class="username-display">'.$username.'</div>
                                </div>
                            </div>
                            
                            <!-- Статистика в две колонки -->
                            <div class="row">
                                <div class="col-md-6">
                                    <!-- Количество сообщений -->
                                    <div class="stat-item border-bottom border-custom d-flex justify-content-between align-items-center">
                                        <span class="stat-label">
                                            <i class="fas fa-comment icon-post"></i>
                                            '.$lang->usercp['postnum'].'
                                        </span>
                                        <a href="search.php?action=finduser&amp;uid='.$CURUSER['id'].'" class="stat-value text-decoration-none">
                                            '.$mybb->user['posts'].' '.$ss.'
                                        </a>
                                    </div>
                                    
                                    <!-- Комментарии -->
                                    <div class="stat-item border-bottom border-custom d-flex justify-content-between align-items-center">
                                        <span class="stat-label">
                                            <i class="fas fa-comments icon-comment"></i>
                                            '.$lang->usercp['comentsss'].'
                                        </span>
                                        <a href="userhistory.php?action=viewcomments&id='.$CURUSER['id'].'" class="stat-value text-decoration-none">
                                            '.$com.'
                                        </a>
                                    </div>
                                    
                                    <!-- Бонусные баллы -->
                                    <div class="stat-item border-bottom border-custom d-flex justify-content-between align-items-center">
                                        <span class="stat-label">
                                            <i class="fas fa-star icon-bonus"></i>
                                            '.$lang->usercp['bpoints'].'
                                        </span>
                                        <a href="mybonus.php" class="stat-value text-decoration-none">
                                            '.$bonus.'
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <!-- Email -->
                                    <div class="stat-item border-bottom border-custom d-flex justify-content-between align-items-center">
                                        <span class="stat-label">
                                            <i class="fas fa-envelope icon-email"></i>
                                            '.$lang->usercp['email'].'
                                        </span>
                                        <span class="stat-value">'.$CURUSER['email'].'</span>
                                    </div>
                                    
                                    <!-- Дата регистрации -->
                                    <div class="stat-item border-bottom border-custom d-flex justify-content-between align-items-center">
                                        <span class="stat-label">
                                            <i class="fas fa-calendar-alt icon-date"></i>
                                            '.$lang->usercp['registration_date'].'
                                        </span>
                                        <span class="stat-value">'.$regdate.'</span>
                                    </div>
                                    
                                    <!-- Основная группа -->
                                    <div class="stat-item d-flex justify-content-between align-items-center">
                                        <span class="stat-label">
                                            <i class="fas fa-users icon-group"></i>
                                            '.$lang->usercp['primary_usergroup'].'
                                        </span>
                                        <span class="stat-value">'.$usergroup.'</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</html>'; 
	
	echo $_tpl_out;
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
    $notify_2fa_change = function (int $uid, bool $enabled) use ($db, $SITENAME): void {
        $query = $db->sql_query_prepared("SELECT username, email FROM users WHERE id = ? LIMIT 1", [$uid]);
        $user  = $query ? $db->fetch_array($query) : null;

        if (empty($user['email'])) {
            return;
        }

        $ip   = get_ip();
        $geo  = geo_by_ip($ip);
        $time = date('Y-m-d H:i:s');

        $action  = $enabled ? 'enabled' : 'disabled';
        $subject = '[' . $SITENAME . '] Two-factor authentication ' . $action;

        $message = "Two-factor authentication on your account has just been {$action}.\n\n"
                 . "IP       : {$ip}\n"
                 . "Location : " . $geo['country'] . ' / ' . $geo['city'] . "\n"
                 . "Time     : {$time}\n\n"
                 . ($enabled
                    ? "Your account now requires a 6-digit code from your authenticator app at login."
                    : "Your account no longer requires a 2FA code at login. ")
                 . "\n\nIf you didn't make this change, your account may be compromised. "
                 . "Please change your password immediately and review your active session.";

        my_mail($user['email'], $subject, $message);
    };

    if ($mode === 'disable') {
        if (!validate_password_from_uid($uid, $mybb->get_input('password'))) {
            $mybb->input['action']    = '2fa';
            $mybb->input['2fa_error'] = 'Incorrect password. Please enter your current password to confirm disabling two-factor authentication.';
        } else {
            totp_disable($uid);
            $notify_2fa_change($uid, false);
            redirect('usercp.php?action=2fa', 'Two-factor authentication has been disabled.');
        }

    } elseif ($mode === 'confirm') {
        $secret = $mybb->get_input('secret');
        $code   = preg_replace('/\D/', '', $mybb->get_input('totp_code'));

        if (empty($secret)) {
            redirect('usercp.php?action=2fa', 'Invalid secret. Please try again.');
        }

        if (!totp_verify($secret, $code)) {
            $mybb->input['action']    = '2fa';
            $mybb->input['2fa_error'] = 'Invalid code. Please scan the QR code again and try once more.';
            $mybb->input['2fa_secret'] = $secret;
        } else {
            totp_enable($uid, $secret);
            $notify_2fa_change($uid, true);
            redirect('usercp.php?action=2fa', 'Two-factor authentication has been enabled successfully!');
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
        $qr_url   = totp_qr_url($secret, htmlspecialchars($CURUSER['username'], ENT_QUOTES), $SITENAME);
        $error    = htmlspecialchars($mybb->input['2fa_error'] ?? '', ENT_QUOTES, 'UTF-8');
        $setup_mode = true;
    } else {
        $setup_mode = false;
        $secret = $qr_url = $error = '';
    }

    stdhead('Two-Factor Authentication');
    build_breadcrumb();

    echo '<link rel="stylesheet" href="' . $BASE . '/include/templates/default/style/usercp_profile.css">';

    echo '
<div class="container-md py-4">
<div class="row g-4">

    <div class="col-lg-3">
        ' . $usercpnav . '
    </div>

    <div class="col-lg-9">';

    // ── Enabled state ─────────────────────────────────────────────────────
    if ($enabled && !$setup_mode) {
        echo '
        <div class="card">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-3 d-none d-lg-flex">
                        <div class="text-center p-3 border-end w-100">
                            <i class="fas fa-shield-halved fa-3x mb-3 text-success"></i>
                            <div class="fw-bold mt-2">Two-Factor Auth</div>
                            <small class="text-muted">Account protected</small>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div class="alert alert-success d-flex align-items-center gap-3 mb-4">
                            <i class="fas fa-check-circle fa-2x"></i>
                            <div>
                                <strong>2FA is enabled!</strong><br>
                                <small>Your account is protected with two-factor authentication.</small>
                            </div>
                        </div>
                        <p class="text-muted">
                            When you log in, you will be asked for a 6-digit code from your authenticator app
                            (Google Authenticator, Authy, or similar).
                        </p>
                        <hr>
                        <form method="post" action="usercp.php">
                            <input type="hidden" name="action" value="do_2fa" />
                            <input type="hidden" name="mode" value="disable" />
                            <input type="hidden" name="my_post_key" value="' . $postCode . '" />
                            ' . (!empty($mybb->input['2fa_error']) ? '<div class="alert alert-danger py-2 mb-3">' . htmlspecialchars($mybb->input['2fa_error']) . '</div>' : '') . '
                            <div class="mb-3" style="max-width:320px;">
                                <label class="form-label small text-muted">Confirm your current password to disable 2FA</label>
                                <input type="password" name="password" class="form-control" required autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn btn-danger"
                                onclick="return confirm(\'Disable two-factor authentication? Your account will be less secure.\')">
                                <i class="fas fa-shield-xmark me-2"></i>Disable 2FA
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>';

    // ── Setup / confirm flow ──────────────────────────────────────────────
    } elseif ($setup_mode) {
        echo '
        <div class="card">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-3 d-none d-lg-flex">
                        <div class="text-center p-3 border-end w-100">
                            <i class="fas fa-qrcode fa-3x mb-3 text-primary"></i>
                            <div class="fw-bold mt-2">Setup 2FA</div>
                            <small class="text-muted">Scan & confirm</small>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        ' . (!empty($error) ? '<div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-2"></i>' . $error . '</div>' : '') . '

                        <div class="info-hint mb-4">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            <strong>Step 1:</strong> Install an authenticator app —
                            <a href="https://googleauthenticator.net/" target="_blank">Google Authenticator</a>,
                            <a href="https://authy.com/" target="_blank">Authy</a>, or similar.<br>
                            <strong>Step 2:</strong> Scan the QR code below.<br>
                            <strong>Step 3:</strong> Enter the 6-digit code to confirm.
                        </div>

                        <div class="text-center mb-4">
                            <img src="' . $qr_url . '" alt="2FA QR Code"
                                 class="border rounded p-2" width="200" height="200">
                            <div class="mt-2">
                                <small class="text-muted">Manual entry key:</small><br>
                                <code class="user-select-all fs-6 fw-bold">' . $secret . '</code>
                            </div>
                        </div>

                        <form method="post" action="usercp.php">
                            <input type="hidden" name="action" value="do_2fa" />
                            <input type="hidden" name="mode" value="confirm" />
                            <input type="hidden" name="secret" value="' . $secret . '" />
                            <input type="hidden" name="my_post_key" value="' . $postCode . '" />
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-mobile-screen me-1"></i>
                                    Enter the 6-digit code from your app
                                </label>
                                <input type="text" name="totp_code"
                                       class="form-control form-control-lg text-center fw-bold"
                                       placeholder="000000" maxlength="6"
                                       autocomplete="one-time-code" autofocus
                                       inputmode="numeric" pattern="[0-9]{6}">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check me-2"></i>Confirm & Enable 2FA
                                </button>
                                <a href="usercp.php?action=2fa" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>';

    // ── Disabled state ────────────────────────────────────────────────────
    } else {
        echo '
        <div class="card">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-3 d-none d-lg-flex">
                        <div class="text-center p-3 border-end w-100">
                            <i class="fas fa-shield-halved fa-3x mb-3 text-muted"></i>
                            <div class="fw-bold mt-2">Two-Factor Auth</div>
                            <small class="text-muted">Not enabled</small>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
                            <i class="fas fa-triangle-exclamation fa-2x"></i>
                            <div>
                                <strong>2FA is not enabled.</strong><br>
                                <small>Add an extra layer of security to your account.</small>
                            </div>
                        </div>
                        <p class="text-muted">
                            Two-factor authentication adds a second verification step when you log in.
                            Even if someone gets your password, they cannot access your account
                            without the code from your phone.
                        </p>
                        <hr>
                        <form method="post" action="usercp.php">
                            <input type="hidden" name="action" value="do_2fa" />
                            <input type="hidden" name="mode" value="enable" />
                            <input type="hidden" name="my_post_key" value="' . $postCode . '" />
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-shield-halved me-2"></i>Enable Two-Factor Authentication
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>';
    }

    echo '
    </div><!-- col-lg-9 -->
</div><!-- row -->
</div><!-- container -->';

    stdfoot();
    exit;
}



// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_sessions — log out everywhere (reset current session)
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_sessions' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $uid  = (int)$CURUSER['id'];
    $mode = $mybb->get_input('mode');

    // ── Remove a specific known device ────────────────────────────────────
    if ($mode === 'remove_device') {
        $device_id = (int)$mybb->get_input('device_id');
        if ($device_id > 0) {
            $db->sql_query_prepared("DELETE FROM user_devices WHERE id = ? AND uid = ?", [$device_id, $uid]);
        }
        redirect('usercp.php?action=sessions', 'Device removed from your known devices list.');
    }

    // ── Log out everywhere ────────────────────────────────────────────────
    $db->sql_query_prepared("DELETE FROM sessions WHERE uid = ?", [$uid]);
    update_loginkey($uid);

    my_unsetcookie('mybbuser');
    my_unsetcookie('sid');
	
	
	// This is a full "log out everywhere" — the admin-panel 2FA gate must not
    // outlive it, otherwise the user could still be logged into /admin/ for
    // up to 8 hours after believing every session was terminated.
    unset($_SESSION['admin_2fa_ok_' . $uid]);
    unset($_SESSION['admin_2fa_fail_count']);
	

    redirect('member.php?action=login', 'You have been logged out of all sessions. Please log in again.');
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

    $browser = 'Unknown browser';
    if (preg_match('/Edg\/([\d.]+)/i', $ua, $m))        { $browser = 'Microsoft Edge ' . $m[1]; }
    elseif (preg_match('/OPR\/([\d.]+)/i', $ua, $m))    { $browser = 'Opera ' . $m[1]; }
    elseif (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)){ $browser = 'Firefox ' . $m[1]; }
    elseif (preg_match('/Chrome\/([\d.]+)/i', $ua, $m)) { $browser = 'Chrome ' . $m[1]; }
    elseif (preg_match('/Version\/([\d.]+).*Safari/i', $ua, $m)) { $browser = 'Safari ' . $m[1]; }
    elseif (preg_match('/Safari\/([\d.]+)/i', $ua, $m)) { $browser = 'Safari ' . $m[1]; }

    $os = 'Unknown OS';
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
    require_once INC_PATH . '/function_loginattemptcheck.php';

    $ip = '';
    if (!empty($session_row['ip'])) {
        // ip column is varbinary — convert back to readable string
        $ip = inet_ntop($session_row['ip']) ?: '';
    }

    $geo      = $ip ? geo_by_ip($ip) : ['country' => '', 'city' => ''];
    $location = trim(($geo['city'] ?? '') . (($geo['city'] && $geo['country']) ? ', ' : '') . ($geo['country'] ?? ''));
    if ($location === '') {
        $location = 'Unknown location';
    }

    $last_active = !empty($session_row['time'])
        ? my_datee('relative', (int)$session_row['time'])
        : 'Unknown';

    // Current cookie sid vs stored sid — should always match for the active session
    $current_sid = $mybb->cookies['sid'] ?? '';
    $is_current   = !empty($session_row['sid']) && $session_row['sid'] === $current_sid;

    stdhead('Active Session');
    build_breadcrumb();

    echo '
<div class="container-md py-4">
<div class="row g-4">

    <div class="col-lg-3">
        ' . $usercpnav . '
    </div>

    <div class="col-lg-9">
        <div class="card">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-3 d-none d-lg-flex">
                        <div class="text-center p-3 border-end w-100">
                            <i class="fas fa-shield-halved fa-3x mb-3 text-primary"></i>
                            <div class="fw-bold mt-2">Session Security</div>
                            <small class="text-muted">Your current login</small>
                        </div>
                    </div>
                    <div class="col-lg-9">

                        <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
                            <i class="fas fa-circle-info fa-2x"></i>
                            <div>
                                <strong>One active session per account.</strong><br>
                                <small>Logging in on a new device automatically replaces this session.
                                If you don\'t recognize the details below, log out everywhere and change your password immediately.</small>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="text-primary" style="font-size:2.25rem;">
                                        <i class="fas ' . $device_icon . '"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold fs-5">
                                            ' . htmlspecialchars($browser) . ' on ' . htmlspecialchars($os) . '
                                            ' . ($is_current ? '<span class="badge bg-success ms-2"><i class="fas fa-check-circle me-1"></i>This device</span>' : '') . '
                                        </div>
                                        <div class="text-muted small mt-1">
                                            <i class="fas fa-location-dot me-1"></i>' . htmlspecialchars($location) . '
                                            &nbsp;&bull;&nbsp;
                                            <i class="fas fa-network-wired me-1"></i>' . htmlspecialchars($ip ?: 'Unknown IP') . '
                                        </div>
                                        <div class="text-muted small mt-1">
                                            <i class="fas fa-clock me-1"></i>Last active: ' . $last_active . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Known Devices -->
                        <h6 class="fw-bold mb-3"><i class="fas fa-list me-2 text-secondary"></i>Known Devices</h6>';

    // Load known devices
    $devices_query = $db->sql_query_prepared("SELECT * FROM user_devices WHERE uid = ? ORDER BY last_seen DESC", [$uid]);

    $devices_html = '';
    while ($devices_query && ($dev = $db->fetch_array($devices_query))) {
        $dev_ua = $dev['user_agent'];

        $dev_browser = 'Unknown browser';
        if (preg_match('/Edg\/([\d.]+)/i', $dev_ua, $m))        { $dev_browser = 'Microsoft Edge ' . $m[1]; }
        elseif (preg_match('/OPR\/([\d.]+)/i', $dev_ua, $m))    { $dev_browser = 'Opera ' . $m[1]; }
        elseif (preg_match('/Firefox\/([\d.]+)/i', $dev_ua, $m)){ $dev_browser = 'Firefox ' . $m[1]; }
        elseif (preg_match('/Chrome\/([\d.]+)/i', $dev_ua, $m)) { $dev_browser = 'Chrome ' . $m[1]; }
        elseif (preg_match('/Version\/([\d.]+).*Safari/i', $dev_ua, $m)) { $dev_browser = 'Safari ' . $m[1]; }
        elseif (preg_match('/Safari\/([\d.]+)/i', $dev_ua, $m)) { $dev_browser = 'Safari ' . $m[1]; }

        $dev_os = 'Unknown OS';
        if (str_contains($dev_ua, 'Windows NT 10.0'))      { $dev_os = 'Windows 10/11'; }
        elseif (str_contains($dev_ua, 'Windows NT 6.3'))   { $dev_os = 'Windows 8.1'; }
        elseif (str_contains($dev_ua, 'Windows NT 6.1'))   { $dev_os = 'Windows 7'; }
        elseif (str_contains($dev_ua, 'Windows'))          { $dev_os = 'Windows'; }
        elseif (str_contains($dev_ua, 'Android'))          { $dev_os = 'Android'; }
        elseif (preg_match('/iPhone|iPad|iPod/', $dev_ua)) { $dev_os = 'iOS'; }
        elseif (str_contains($dev_ua, 'Mac OS X'))         { $dev_os = 'macOS'; }
        elseif (str_contains($dev_ua, 'Linux'))            { $dev_os = 'Linux'; }

        $dev_icon = match(true) {
            $dev_os === 'Android' || $dev_os === 'iOS' => 'fa-mobile-screen',
            default => 'fa-desktop',
        };

        $first_seen = my_datee('relative', (int)$dev['first_seen']);
        $last_seen  = my_datee('relative', (int)$dev['last_seen']);

        $devices_html .= '
        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
            <div class="text-secondary" style="font-size:1.4rem;width:28px;text-align:center;">
                <i class="fas ' . $dev_icon . '"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-semibold small">' . htmlspecialchars($dev_browser) . ' on ' . htmlspecialchars($dev_os) . '</div>
                <div class="text-muted" style="font-size:.78rem;">
                    First seen: ' . $first_seen . '
                    &nbsp;&bull;&nbsp;
                    Last used: ' . $last_seen . '
                </div>
            </div>
            <form method="post" action="usercp.php" class="mb-0">
                <input type="hidden" name="action" value="do_sessions" />
                <input type="hidden" name="mode" value="remove_device" />
                <input type="hidden" name="device_id" value="' . (int)$dev['id'] . '" />
                <input type="hidden" name="my_post_key" value="' . $postCode . '" />
                <button type="submit" class="btn btn-sm btn-outline-danger"
                    onclick="return confirm(\'Remove this device from your known devices list?\')">
                    <i class="fas fa-times"></i>
                </button>
            </form>
        </div>';
    }

    echo ($devices_html ?: '<p class="text-muted small">No known devices yet. They will appear here after your next login.</p>');

    echo '
                        <hr class="mt-3">

                        <p class="text-muted">
                            If you suspect someone else has access to your account, you can immediately
                            log out of this session everywhere. This will also invalidate any
                            "remember me" cookies, requiring a fresh login (and 2FA code, if enabled).
                        </p>

                        <form method="post" action="usercp.php">
                            <input type="hidden" name="action" value="do_sessions" />
                            <input type="hidden" name="my_post_key" value="' . $postCode . '" />
                            <button type="submit" class="btn btn-danger"
                                onclick="return confirm(\'This will log you out everywhere and require you to sign in again. Continue?\')">
                                <i class="fas fa-right-from-bracket me-2"></i>Log Out Everywhere
                            </button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div><!-- col-lg-9 -->
</div><!-- row -->
</div><!-- container -->';

    stdfoot();
    exit;
}









stdfoot();