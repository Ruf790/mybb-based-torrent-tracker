<?php
declare(strict_types=1);

define('IN_MYBB', 1);
define('IGNORE_CLEAN_VARS', 'sid');
define('SCRIPTNAME', 'private.php');
define('IN_FORUM', true);

require_once 'global.php';
require_once 'cache/smilies.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/datahandler.php';

$parser = new postParser();

$lang->load('private');

if ($enablepms == 0) {
    error($lang->private['pms_disabled']);
}

if ($CURUSER['id'] == '/' || $CURUSER['id'] == 0 || $usergroups['canusepms'] == 0) {
    print_no_permission();
}

// ── Папки ────────────────────────────────────────────────────────────────────
$mybb->input['fid'] = $mybb->get_input('fid', MyBB::INPUT_INT);

$folder_id = $folder_name = $folderjump_folder = $folderoplist_folder = $foldersearch_folder = '';
$foldernames = [];

foreach (explode('$%%$', $CURUSER['pmfolders']) as $folders) {
    $folderinfo = explode('**', $folders, 2);
    $sel        = $mybb->input['fid'] == $folderinfo[0] ? ' selected="selected"' : '';
    $folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
    $foldernames[$folderinfo[0]] = $folderinfo[1];

    $folder_id   = $folderinfo[0];
    $folder_name = $folderinfo[1];

    $folderjump_folder .= '<option value="' . $folder_id . '"' . $sel . '>' . $folder_name . '</option>';

    if ($folder_id != 1) {
        if ($folder_id == 0) $folder_id = 1;
        $folderoplist_folder  .= '<option value="' . $folder_id . '"' . $sel . '>' . $folder_name . '</option>';
        $foldersearch_folder  .= '<option value="' . $folder_id . '"' . $sel . '>' . $folder_name . '</option>';
    }
}

$from_fid = $mybb->input['fid'];

$folderjump  = '<select name="jumpto" class="form-select form-select-sm border">' . $folderjump_folder . '</select>';
$folderoplist = '<input type="hidden" value="' . $from_fid . '" name="fromfid" />
<select name="fid" class="form-select form-select-sm border w-auto pe-5">' . $folderoplist_folder . '</select>';
$foldersearch = '<select name="folder[]" id="folder" class="form-select form-select-sm border w-auto pe-5">
<option selected="selected">' . $lang->private['all_folders'] . '</option>' . $foldersearch_folder . '</select>';

usercp_menu();
$plugins->run_hooks('private_start');
add_breadcrumb($lang->private['nav_pms'], 'private.php');

$action = $mybb->get_input('action');
$mybb->input['action'] = $action;

match ($action) {
    'send'     => add_breadcrumb('nav_send'),
    'tracking' => add_breadcrumb('nav_tracking'),
    'empty'    => add_breadcrumb('nav_empty'),
    'results'  => add_breadcrumb('nav_results'),
    default    => null,
};

if (!empty($mybb->input['preview'])) {
    $mybb->input['action'] = 'send';
}

// ── Dismiss PM notice ────────────────────────────────────────────────────────
if ($action === 'dismiss_notice') {
    if ($CURUSER['pmnotice'] != 2) exit;

    verify_post_check($mybb->get_input('my_post_key'));
    $db->update_query('users', ['pmnotice' => 1], "id='{$CURUSER['id']}'");

    if (!empty($mybb->input['ajax'])) {
        echo 1;
        exit;
    }
    header('Location: index.php');
    exit;
}

// ── Do send ──────────────────────────────────────────────────────────────────
$send_errors = '';

if ($action === 'do_send' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('private_send_do_send');

    $to         = array_unique(array_map('trim', explode(',', $mybb->get_input('to'))));
    $to_escaped = implode("','", array_map([$db, 'escape_string'], array_map('my_strtolower', $to)));
    $time_cutoff = TIMENOW - (5 * 60 * 60);

    $query = $db->sql_query("
        SELECT pm.pmid FROM privatemessages pm
        LEFT JOIN users u ON (u.id = pm.toid)
        WHERE LOWER(u.username) IN ('{$to_escaped}')
          AND pm.dateline > {$time_cutoff}
          AND pm.fromid = '{$CURUSER['id']}'
          AND pm.subject = '" . $db->escape_string($mybb->get_input('subject')) . "'
          AND pm.message = '" . $db->escape_string($mybb->get_input('message')) . "'
          AND pm.folder != '3'
        LIMIT 0, 1
    ");
    if ($db->num_rows($query) > 0) {
        stderr($lang->private['error_pm_already_submitted']);
    }

    require_once INC_PATH . '/datahandlers/pm.php';
    $pmhandler = new PMDataHandler();

    $pm = [
        'subject'   => $mybb->get_input('subject'),
        'message'   => $mybb->get_input('message'),
        'icon'      => $mybb->get_input('icon', MyBB::INPUT_INT),
        'fromid'    => $CURUSER['id'],
        'do'        => $mybb->get_input('do'),
        'pmid'      => $mybb->get_input('pmid', MyBB::INPUT_INT),
        'ipaddress' => $session->packedip,
        'to'        => $to,
    ];

    if (!empty($mybb->input['bcc'])) {
        $pm['bcc'] = array_map('trim', explode(',', $mybb->get_input('bcc')));
    }

    $mybb->input['options'] = $mybb->get_input('options', MyBB::INPUT_ARRAY);
    if (!$usergroups['cantrackpms']) {
        $mybb->input['options']['readreceipt'] = false;
    }

    $pm['options'] = [
        'savecopy'    => (isset($mybb->input['options']['savecopy']) && $mybb->input['options']['savecopy'] == 1) ? 1 : 0,
        'readreceipt' => $mybb->input['options']['readreceipt'] ?? 0,
    ];

    if (!empty($mybb->input['saveasdraft'])) {
        $pm['saveasdraft'] = 1;
    }

    $pmhandler->set_data($pm);

    if (!$pmhandler->validate_pm()) {
        $send_errors = inline_error($pmhandler->get_friendly_errors());
        $mybb->input['action'] = 'send';
    } else {
        $pminfo = $pmhandler->insert_pm();
        $plugins->run_hooks('private_do_send_end');
        redirect('private.php', isset($pminfo['draftsaved'])
            ? $lang->private['redirect_pmsaved']
            : $lang->private['redirect_pmsent']
        );
    }
}

// ── Send form ────────────────────────────────────────────────────────────────
if ($mybb->input['action'] === 'send') {
    $plugins->run_hooks('private_send_start');

    require_once INC_PATH . '/editor.php';
    $editor      = insert_bbcode_editor($smilies, $BASEURL, 'message');
    $codebuttons = $editor['toolbar'] . $editor['modal'];

    $message = htmlspecialchars_uni($parser->parse_badwords($mybb->get_input('message')));
    $subject = htmlspecialchars_uni($parser->parse_badwords($mybb->get_input('subject')));

    $optionschecked = ['savecopy' => '', 'readreceipt' => ''];
    $to = $bcc = '';

    if (!empty($mybb->input['preview']) || $send_errors) {
        $options = $mybb->get_input('options', MyBB::INPUT_ARRAY);
        if (isset($options['savecopy']) && $options['savecopy'] != 0)      $optionschecked['savecopy']    = 'checked="checked"';
        if (isset($options['readreceipt']) && $options['readreceipt'] != 0) $optionschecked['readreceipt'] = 'checked="checked"';
        $to  = htmlspecialchars_uni(implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('to'))))));
        $bcc = htmlspecialchars_uni(implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('bcc'))))));
    }

    $preview = '';
    if (!empty($mybb->input['preview'])) {
        $query = $db->sql_query_prepared(
            'SELECT u.username AS userusername, u.* FROM users u WHERE u.id = ?',
            [(int)$CURUSER['id']]
        );
        $post = $db->fetch_array($query);
        $post['userusername'] = $post['postusername'] = $CURUSER['username'];
        $post['message']  = $mybb->get_input('message');
        $post['subject']  = htmlspecialchars_uni($mybb->get_input('subject'));
        $post['icon']     = $mybb->get_input('icon', MyBB::INPUT_INT);
        $post['dateline'] = TIMENOW;

        foreach (['title' => 'grouptitle', 'usertitle' => 'groupusertitle', 'stars' => 'groupstars',
                  'starimage' => 'groupstarimage', 'image' => 'groupimage',
                  'namestyle' => 'namestyle', 'usereputationsystem' => 'usereputationsystem'] as $field => $key) {
            $post[$key] = $groupscache[$post['usergroup']][$field];
        }
        $preview = build_postbit($post, 2);

    } elseif (!$send_errors) {
        $optionschecked['readreceipt'] = 'checked="checked"';
        $optionschecked['savecopy']    = 'checked="checked"';
    }

    // Draft / reply / forward
    if ($mybb->get_input('pmid') && empty($mybb->input['preview']) && !$send_errors) {
        $query = $db->sql_query("
            SELECT pm.*, u.username AS quotename
            FROM privatemessages pm
            LEFT JOIN users u ON (u.id = pm.fromid)
            WHERE pm.pmid = '" . $mybb->get_input('pmid', MyBB::INPUT_INT) . "'
              AND pm.uid = '{$CURUSER['id']}'
        ");
        $pm      = $db->fetch_array($query);
        $message = htmlspecialchars_uni($parser->parse_badwords($pm['message']));
        $subject = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));

        if ($pm['folder'] === '3') {
            $mybb->input['uid'] = $pm['toid'];
            if ($pm['receipt']) $optionschecked['readreceipt'] = 'checked="checked"';

            $recipients   = my_unserialize($pm['recipients']);
            $recipientids = '';
            $comma        = '';
            $recipient_list = [];

            foreach (['to', 'bcc'] as $type) {
                if (isset($recipients[$type]) && is_array($recipients[$type])) {
                    foreach ($recipients[$type] as $recipient) {
                        $recipient_list[$type][] = $recipient;
                        $recipientids .= $comma . $recipient;
                        $comma = ',';
                    }
                }
            }

            if (!empty($recipientids)) {
                $query = $db->simple_select('users', 'id, username', "id IN ({$recipientids})");
                while ($user = $db->fetch_array($query)) {
                    if (isset($recipient_list['bcc']) && in_array($user['id'], $recipient_list['bcc'])) {
                        $bcc .= htmlspecialchars_uni($user['username']) . ', ';
                    } else {
                        $to  .= htmlspecialchars_uni($user['username']) . ', ';
                    }
                }
            }
        } else {
            $subject = preg_replace('#(FW|RE):( *)#is', '', $subject);
            $message = "[quote='{$pm['quotename']}']\n{$message}\n[/quote]";
            $message = preg_replace('#^/me (.*)$#im', '* ' . $pm['quotename'] . ' \\1', $message);

            require_once INC_PATH . '/functions_posting.php';
            $maxpmquotedepth = 5;
if ($maxpmquotedepth !== 0) {
    $message = remove_message_quotes($message, $maxpmquotedepth);
}

            $do = $mybb->input['do'];
            if ($do === 'forward') {
                $subject = "Fw: {$subject}";
            } elseif ($do === 'reply') {
                $subject = "Re: {$subject}";
                $uid = $pm['fromid'];
                $to  = $CURUSER['id'] === $uid
                    ? $CURUSER['username']
                    : $db->fetch_field($db->simple_select('users', 'username', "id='{$uid}'"), 'username');
                $to  = htmlspecialchars_uni($to);
            } elseif ($do === 'replyall') {
                $subject      = "Re: {$subject}";
                $recipients   = my_unserialize($pm['recipients']);
                $recipientids = (string)$pm['fromid'];

                if (isset($recipients['to']) && is_array($recipients['to'])) {
                    foreach ($recipients['to'] as $recipient) {
                        if ($recipient === $CURUSER['id']) continue;
                        $recipientids .= ',' . $recipient;
                    }
                }
                $comma = '';
                $query = $db->simple_select('users', 'id, username', "id IN ({$recipientids})");
                while ($user = $db->fetch_array($query)) {
                    $to    .= $comma . htmlspecialchars_uni($user['username']);
                    $comma  = $lang->private['comma'];
                }
            }
        }
    }

    // New PM with preset recipient
    if ($mybb->get_input('uid', MyBB::INPUT_INT) && empty($mybb->input['preview'])) {
        $to = htmlspecialchars_uni($db->fetch_field(
            $db->simple_select('users', 'username', "id='" . $mybb->get_input('uid', MyBB::INPUT_INT) . "'"),
            'username'
        )) . ', ';
    }

    $max_recipients = $usergroups['maxpmrecipients'] > 0
        ? sprintf($lang->private['max_recipients'], $usergroups['maxpmrecipients'])
        : '';

    if ($send_errors) {
        $to  = htmlspecialchars_uni(implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('to'))))));
        $bcc = htmlspecialchars_uni(implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('bcc'))))));
    }

    $autocompletejs = '<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    var container = document.getElementById(\'to-container\');
    var toInput = document.getElementById(\'to\');
    if (!container || !toInput) return;
    var maxRecipients = parseInt(toInput.getAttribute(\'data-max-recipients\')) || 5;
    var input = container.querySelector(\'.select2-tags-input\');
    if (!input) return;
    var recipients = [];
    var debounceTimer = null;
    if (toInput.value) {
        toInput.value.split(\',\').map(v => v.trim()).filter(v => v !== \'\').forEach(v => addRecipient(v, true));
    }
    var dropdown = document.createElement(\'div\');
    dropdown.className = \'select2-dropdown\';
    dropdown.style.cssText = \'position:absolute;background:white;border:1px solid #ddd;border-radius:4px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 2px 8px rgba(0,0,0,.1);display:none;\';
    document.body.appendChild(dropdown);
    var errorDisplay = document.createElement(\'div\');
    errorDisplay.style.cssText = \'color:#dc3545;font-size:12px;margin-top:5px;display:none;\';
    container.parentNode.insertBefore(errorDisplay, container.nextSibling);
    function addRecipient(username, skipDuplicateCheck) {
        if (recipients.length >= maxRecipients) { showLimitMessage(); return; }
        if (!skipDuplicateCheck && recipients.includes(username)) return;
        recipients.push(username);
        var tag = document.createElement(\'div\');
        tag.style.cssText = \'display:inline-flex;align-items:center;background:#e9ecef;border-radius:16px;padding:4px 8px;font-size:14px;color:#495057;margin:2px;\';
        var textSpan = document.createElement(\'span\');
        textSpan.textContent = username;
        textSpan.style.marginRight = \'6px\';
        tag.appendChild(textSpan);
        var removeBtn = document.createElement(\'button\');
        removeBtn.textContent = \'×\';
        removeBtn.style.cssText = \'background:none;border:none;color:#6c757d;font-size:16px;cursor:pointer;padding:0;\';
        removeBtn.onclick = e => { e.preventDefault(); e.stopPropagation(); removeRecipient(username); };
        tag.appendChild(removeBtn);
        container.insertBefore(tag, input);
        toInput.value = recipients.join(\', \');
        input.value = \'\';
        enableInput();
        dropdown.style.display = \'none\';
        if (recipients.length >= maxRecipients) showLimitMessage();
        updateCounter();
    }
    function removeRecipient(username) {
        var idx = recipients.indexOf(username);
        if (idx > -1) { recipients.splice(idx, 1); toInput.value = recipients.join(\', \'); redrawTags(); enableInput(); updateCounter(); }
    }
    function redrawTags() {
        container.querySelectorAll(\'div:not(.select2-tags-input)\').forEach(t => container.removeChild(t));
        recipients.forEach(u => {
            var tag = document.createElement(\'div\');
            tag.style.cssText = \'display:inline-flex;align-items:center;background:#e9ecef;border-radius:16px;padding:4px 8px;font-size:14px;color:#495057;margin:2px;\';
            var s = document.createElement(\'span\'); s.textContent = u; s.style.marginRight = \'6px\'; tag.appendChild(s);
            var b = document.createElement(\'button\'); b.textContent = \'×\'; b.style.cssText = \'background:none;border:none;color:#6c757d;font-size:16px;cursor:pointer;padding:0;\';
            b.onclick = e => { e.preventDefault(); e.stopPropagation(); removeRecipient(u); }; tag.appendChild(b);
            container.insertBefore(tag, input);
        });
    }
    function showLimitMessage() {
        var msg = \'You are only allowed to send messages to \' + maxRecipients + \' users at a time\';
        showMessage(msg); disableInput();
        errorDisplay.textContent = msg; errorDisplay.style.display = \'block\';
    }
    function enableInput()  { input.disabled = false; input.placeholder = \'Search for users\'; container.style.opacity = \'1\'; container.style.borderColor = \'#ddd\'; errorDisplay.style.display = \'none\'; }
    function disableInput() { input.disabled = true; input.placeholder = \'Maximum recipients reached (\' + maxRecipients + \')\'; container.style.opacity = \'0.7\'; container.style.borderColor = \'#dc3545\'; }
    function searchUsers(q) {
        if (q.length < 2) { dropdown.style.display = \'none\'; return; }
        fetch(\'xmlhttp.php?action=get_users&query=\' + encodeURIComponent(q))
            .then(r => r.json()).then(displayResults).catch(() => dropdown.style.display = \'none\');
    }
    function displayResults(users) {
        dropdown.innerHTML = \'\';
        if (!users || !users.length) { showMessage(\'No matches found\'); return; }
        users.forEach(u => {
            var name = u.username || u.text || u.name || u.label || \'\';
            var item = document.createElement(\'div\');
            item.textContent = name;
            item.style.cssText = \'padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:14px;background:white;color:black;\';
            item.onmouseover = () => { item.style.background = \'#007bff\'; item.style.color = \'white\'; };
            item.onmouseout  = () => { item.style.background = \'white\';   item.style.color = \'black\'; };
            item.onclick     = () => addRecipient(name);
            dropdown.appendChild(item);
        });
        dropdown.style.display = \'block\'; updateDropdownPosition();
    }
    function showMessage(msg) { dropdown.innerHTML = \'<div style="padding:8px 12px;color:#666;font-style:italic;font-size:14px;">\' + msg + \'</div>\'; dropdown.style.display = \'block\'; updateDropdownPosition(); }
    function updateDropdownPosition() { var r = container.getBoundingClientRect(); dropdown.style.top = (r.bottom + window.scrollY) + \'px\'; dropdown.style.left = (r.left + window.scrollX) + \'px\'; dropdown.style.width = r.width + \'px\'; }
    function updateCounter() {
        var c = document.getElementById(\'recipientCounter\');
        if (!c) return;
        c.innerHTML = \'<i class="fas fa-user-plus me-1"></i> Recipients: \' + recipients.length + \'/\' + maxRecipients;
        c.style.color = recipients.length >= maxRecipients ? \'#dc3545\' : \'\';
    }
    input.addEventListener(\'focus\', () => { container.style.borderColor = recipients.length >= maxRecipients ? \'#dc3545\' : \'#007bff\'; container.style.boxShadow = \'0 0 0 2px rgba(0,123,255,.25)\'; if (input.value.trim().length >= 2) searchUsers(input.value.trim()); });
    input.addEventListener(\'blur\',  () => setTimeout(() => { container.style.borderColor = recipients.length >= maxRecipients ? \'#dc3545\' : \'#ddd\'; container.style.boxShadow = \'none\'; dropdown.style.display = \'none\'; }, 200));
    input.addEventListener(\'input\', e => { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => searchUsers(e.target.value.trim()), 300); });
    input.addEventListener(\'keydown\', e => {
        if (e.key === \'Enter\' && input.value.trim().length >= 2) { e.preventDefault(); addRecipient(input.value.trim()); }
        else if (e.key === \'Backspace\' && input.value === \'\' && recipients.length > 0) { e.preventDefault(); removeRecipient(recipients[recipients.length - 1]); }
    });
    window.addEventListener(\'resize\', updateDropdownPosition);
    document.addEventListener(\'click\', e => { if (!container.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = \'none\'; });
    updateCounter();
});
</script>';

    $pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);
    $do   = $mybb->get_input('do');
    if (!in_array($do, ['forward', 'reply', 'replyall'])) $do = '';

    $private_send_tracking = '';
    if ($mybb->usergroup['cantrackpms']) {
        $private_send_tracking = '<input type="checkbox" class="form-check-input" name="options[readreceipt]" value="1" tabindex="8" '
            . $optionschecked['readreceipt'] . ' /> ' . $lang->private['quickreply_read_receipt'];
    }

    $plugins->run_hooks('private_send_end');

    $send = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $lang->private['compose_pm'] . '</title>
    <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/private.css">
    <script src="' . $BASEURL . '/scripts/usercp.js?ver=1827"></script>
</head>
<body>
<form action="private.php" method="post" name="input">
<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
<div id="fileIdsContainer"></div>
<div class="container-md py-4">
<div class="row g-4">
    <div class="col-lg-3">' . $usercpnav . '</div>
    <div class="col-lg-9">
        ' . $preview . '
        ' . $send_errors . '
        <div class="card">
            <div class="card-body">
                <div class="mb-4 pb-3 border-bottom">
                    <label class="fw-semibold mb-2"><i class="fas fa-users text-primary me-2"></i> Recipients</label>
                    <input name="to" id="to" value="' . $to . '" tabindex="1" class="form-control border" placeholder="Search for users" style="display:none;">
                    <div id="to-container"></div>
                    <div class="select2-counter" id="recipientCounter"><i class="fas fa-user-plus me-1"></i> Recipients: 0/' . (int)$usergroups['maxpmrecipients'] . '</div>
                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Click to search and select users (minimum 2 characters)</small>
                </div>
                <div class="mb-4 pb-3 border-bottom">
                    <label class="fw-semibold mb-2"><i class="fas fa-heading text-primary me-2"></i> Subject</label>
                    <input type="text" class="form-control" name="subject" maxlength="85" value="' . $subject . '" tabindex="3" placeholder="Enter message subject..." />
                </div>
                <div class="mb-3">
                    <label class="fw-semibold mb-2"><i class="fas fa-pen-fancy text-primary me-2"></i> Message</label>
                    ' . $codebuttons . '
                    <textarea name="message" id="message" class="form-control" style="width:100%;height:350px" tabindex="4" placeholder="Write your message here...">' . $message . '</textarea>
                </div>
                <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
                    <a class="links" data-bs-toggle="collapse" aria-expanded="false" href="#collapse-pmop" role="button">
                        <i class="fas fa-cog"></i> Options
                    </a>
                    <button type="submit" class="btn-thread" name="preview" value="Preview" tabindex="11">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                </div>
                <div id="collapse-pmop" class="collapse">
                    <div class="mt-3 pt-2">
                        <div class="row g-3">
                            <div class="col-lg-9">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" name="options[savecopy]" value="1" tabindex="7" ' . $optionschecked['savecopy'] . ' id="savecopy" />
                                    <label class="form-check-label" for="savecopy"><i class="fas fa-save text-success me-1"></i> Save a copy in my Sent Items folder</label>
                                </div>
                                ' . $private_send_tracking . '
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="action" value="do_send" />
                <input type="hidden" name="pmid" value="' . $pmid . '" />
                <input type="hidden" name="do" value="' . $do . '" />
            </div>
            <div class="card-footer text-center">
                <button type="submit" class="btn btn-primary" name="submit" value="Send Message" tabindex="9">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div>
    </div>
</div>
</div>
</form>
<script>window.maxRecipients = ' . (int)$usergroups['maxpmrecipients'] . ';</script>
<script src="' . $BASEURL . '/scripts/select2-field.js"></script>
</body>
</html>';

    stdhead('title');
    echo $send;
    stdfoot();
}

// ── Read PM ───────────────────────────────────────────────────────────────────
if ($action === 'read') {
    $plugins->run_hooks('private_read');

    $pmid  = $mybb->get_input('pmid', MyBB::INPUT_INT);
    $query = $db->sql_query("
        SELECT pm.*, u.*
        FROM privatemessages pm
        LEFT JOIN users u ON (u.id = pm.fromid)
        WHERE pm.pmid = '{$pmid}' AND pm.uid = '" . $CURUSER['id'] . "'
    ");
    $pm = $db->fetch_array($query);

    if (!$pm) stderr($lang->private['error_invalidpm']);

    if ($pm['folder'] == 3) {
        header("Location: private.php?action=send&pmid={$pm['pmid']}");
        exit;
    }

    //if (isset($groupscache[$pm['usergroup']])) {
    //    foreach (['title' => 'grouptitle', 'usertitle' => 'groupusertitle', 'image' => 'groupimage', 'namestyle' => 'namestyle'] as $field => $key) {
    //        $pm[$key] = $groupscache[$pm['usergroup']][$field];
    //    }
    //}
	
	if (!empty($pm['usergroup']) && isset($groupscache[$pm['usergroup']])) 
	{
       foreach (['title' => 'grouptitle', 'usertitle' => 'groupusertitle', 'image' => 'groupimage', 'namestyle' => 'namestyle'] as $field => $key) 
	   {
          $pm[$key] = $groupscache[$pm['usergroup']][$field];
       }
    }
	

    $receiptadd = null;
    if ($pm['receipt'] == 1) {
        $receiptadd = ($mybb->usergroup['candenypmreceipts'] == 1 && $mybb->get_input('denyreceipt', MyBB::INPUT_INT) == 1) ? 0 : 2;
    }

    $action_time = '';
    if ($pm['status'] == 0) {
        $updatearray = ['status' => 1, 'readtime' => TIMENOW];
        if ($receiptadd !== null) $updatearray['receipt'] = $receiptadd;
        $db->update_query('privatemessages', $updatearray, "pmid='{$pmid}'");
        update_pm_count($CURUSER['id'], 6);
        if ($CURUSER['unreadpms'] - 1 <= 0 && $CURUSER['pmnotice'] == 2) {
            $db->update_query('users', ['pmnotice' => 1], "id='{$CURUSER['id']}'");
        }
    } elseif ($pm['status'] == 3 && $pm['statustime']) {
        $reply_date   = my_datee('relative', $pm['statustime']);
        $reply_string = (TIMENOW - $pm['statustime']) < 3600 ? $lang->you_replied : $lang->private['you_replied_on'];
        $action_time  = '<div class="mb-4 alert bg-success text-white border-0 little"><i class="fa-solid fa-check"></i> &nbsp;' . sprintf($reply_string, $reply_date) . '</div>';
    } elseif ($pm['status'] == 4 && $pm['statustime']) {
        $forward_date   = my_datee('relative', $pm['statustime']);
        $forward_string = (TIMENOW - $pm['statustime']) < 3600 ? $lang->private['you_forwarded'] : $lang->private['you_forwarded_on'];
        $action_time    = '<div class="mb-4 alert bg-success text-white border-0 little"><i class="fa-solid fa-check"></i> &nbsp;' . sprintf($forward_string, $forward_date) . '</div>';
    }

    $pm['userusername'] = $pm['username'];
    $pm['subject']      = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));
    if ($pm['fromid'] == 0) $pm['username'] = 'Ruff Tracker Engine';
    if (!$pm['username'])   $pm['username'] = 'na';

    $pm['recipients'] = my_unserialize($pm['recipients']);
    $uid_sql = isset($pm['recipients']['to']) && is_array($pm['recipients']['to'])
        ? implode(',', $pm['recipients']['to'])
        : (string)$pm['toid'];

    if (!isset($pm['recipients']['to'])) $pm['recipients']['to'] = [$pm['toid']];

    $show_bcc = 0;
    if (isset($pm['recipients']['bcc']) && count($pm['recipients']['bcc']) > 0) {
        $show_bcc  = 1;
        $uid_sql  .= ',' . implode(',', $pm['recipients']['bcc']);
    }

    $bcc_recipients = $to_recipients = $bcc_form_val = [];
    $query = $db->simple_select('users', 'id, username', "id IN ({$uid_sql})");
    while ($recipient = $db->fetch_array($query)) {
        $recipient['username'] = htmlspecialchars_uni($recipient['username']);
        if ($show_bcc && in_array($recipient['id'], $pm['recipients']['bcc'])) {
            $bcc_recipients[] = build_profile_link($recipient['username'], $recipient['id']);
            $bcc_form_val[]   = $recipient['username'];
        } elseif (in_array($recipient['id'], $pm['recipients']['to'])) {
            $to_recipients[]  = build_profile_link($recipient['username'], $recipient['id']);
        }
    }

    $bcc = '';
    if (count($bcc_recipients) > 0) {
        $bcc_form_val = implode(',', $bcc_form_val);
        $bcc = '<br />' . $lang->private['bcc'] . ' ' . implode(', ', $bcc_recipients);
    } else {
        $bcc_form_val = '';
    }

    $replyall    = count($to_recipients) > 1;
    $to_recipients = count($to_recipients) > 0
        ? implode($lang->private['comma'], $to_recipients)
        : $lang->private['nobody'];

    $pm['subject_extra'] = '<br />' . $lang->private['to'] . ' ' . $to_recipients . $bcc;

    add_breadcrumb($pm['subject']);
    $message = build_postbit($pm, 2);

    $quickreply = '';
    if ($usergroups['cansendpms'] != 0 && $pm['fromid'] != 0 && $pm['folder'] != 3) {
        $optionschecked = ['savecopy' => 'checked="checked"', 'readreceipt' => 'checked="checked"'];

        require_once INC_PATH . '/functions_posting.php';
       
	   
	  $quoted_data = [
    'message'     => htmlspecialchars_uni($parser->parse_badwords($pm['message'])),
    'username'    => $pm['username'],
    'quote_is_pm' => true,
];
$quoted_message = parse_quoted_message($quoted_data);

$maxpmquotedepth = 5;
if ($maxpmquotedepth !== 0) {
    $quoted_message = remove_message_quotes($quoted_message, $maxpmquotedepth);
}

        $subject = preg_replace('#(FW|RE):( *)#is', '', $pm['subject']);
        $to = $CURUSER['id'] === $pm['fromid']
            ? htmlspecialchars_uni($CURUSER['username'])
            : htmlspecialchars_uni($db->fetch_field($db->simple_select('users', 'username', "id='{$pm['fromid']}'"), 'username'));

        $private_send_tracking = $mybb->usergroup['cantrackpms']
            ? '<input type="checkbox" class="form-check-input" name="options[readreceipt]" value="1" tabindex="8" ' . $optionschecked['readreceipt'] . ' /> ' . $lang->private['quickreply_read_receipt']
            : '';

        $quickreply = '<form action="private.php" method="post" name="input">
    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    <input type="hidden" name="to" value="' . $to . '" />
    <input type="hidden" name="bcc" value="' . $bcc_form_val . '" />
    <input type="hidden" name="subject" value="Re: ' . $subject . '" />
    <input type="hidden" name="action" value="do_send" />
    <input type="hidden" name="pmid" value="' . $pmid . '" />
    <input type="hidden" name="do" value="reply" />
    <div class="row d-flex g-2 mb-4 mt-5">
        <div class="col-auto d-none d-lg-block"><img src="' . $CURUSER['avatar'] . '" class="rounded img-fluid" style="width:100px;"></div>
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2">' . $CURUSER['username'] . '</h6>
                    <textarea class="form-control form-control-sm border-0 p-0" style="resize:none;height:150px;" name="message" id="message" tabindex="1">' . $quoted_message . '</textarea>
                    <div id="collapse-reply" class="collapse bg-nav mt-3 p-2">
                        <input type="checkbox" class="form-check-input" name="options[savecopy]" value="1" ' . $optionschecked['savecopy'] . ' /> ' . $lang->private['quickreply_save_copy'] . '<br />
                        ' . $private_send_tracking . '
                    </div>
                </div>
                <div class="card-footer border-top-0">
                    <input type="submit" class="btn btn-primary" value="' . $lang->private['send_message'] . '" tabindex="2" accesskey="s" />
                    <a class="btn btn-thread ms-3 me-3" data-bs-toggle="collapse" href="#collapse-reply" role="button"><i class="fa-solid fa-gear"></i></a>
                    <input type="submit" class="btn btn-thread" name="preview" value="' . $lang->private['preview'] . '" tabindex="3" />
                </div>
            </div>
        </div>
    </div>
</form>';
    }

    $plugins->run_hooks('private_read_end');
    stdhead('title');
    echo '<div class="container-md"><div class="row">
        <div class="col-lg-3">' . $usercpnav . '</div>
        <div class="col">' . $action_time . '<div id="posts">' . $message . '</div>' . $quickreply . '</div>
    </div></div>';
    stdfoot();
}

// ── Tracking ─────────────────────────────────────────────────────────────────
if ($action === 'tracking') {
    $plugins->run_hooks('private_tracking_start');

    $perpage = max(1, (int)($f_postsperpage ?: 20));

    // Read messages
    $postcount = (int)$db->fetch_field($db->simple_select('privatemessages', 'COUNT(pmid) as readpms', "receipt='2' AND folder!='3' AND status!='0' AND fromid='" . $CURUSER['id'] . "'"), 'readpms');
    $page      = $mybb->get_input('read_page', MyBB::INPUT_INT) ?: 1;
    $pages     = max(1, (int)ceil($postcount / $perpage));
    $page      = max(1, min($page, $pages));
    $start     = ($page - 1) * $perpage;
    $read_multipage = multipage($postcount, $perpage, $page, 'private.php?action=tracking&amp;read_page={page}');

    $readmessages = '';
    $query = $db->sql_query("
        SELECT pm.pmid, pm.subject, pm.toid, pm.readtime, u.username as tousername
        FROM privatemessages pm
        LEFT JOIN users u ON (u.id = pm.toid)
        WHERE pm.receipt = '2' AND pm.folder != '3' AND pm.status != '0' AND pm.fromid = '" . $CURUSER['id'] . "'
        ORDER BY pm.readtime DESC
        LIMIT {$start}, {$perpage}
    ");
    while ($rm = $db->fetch_array($query)) {
        $rm['subject']     = htmlspecialchars_uni($parser->parse_badwords($rm['subject']));
        $rm['tousername']  = htmlspecialchars_uni($rm['tousername']);
        $rm['profilelink'] = build_profile_link($rm['tousername'], $rm['toid']);
        $readdate          = my_datee('relative', $rm['readtime']);
        $readmessages .= '<div class="card mb-0 border-0"><div class="card-body pt-0 inline_row">
            <div class="row g-2 pb-3 border-bottom mb-0">
                <div class="col-auto col-lg-1 align-self-center"><avatarep_uid_[' . $rm['toid'] . ']></div>
                <div class="col align-self-center">
                    <h6 class="mb-0 text-forum">' . $rm['subject'] . '</h6>
                    <span class="links small">' . $rm['profilelink'] . '</span>
                </div>
                <div class="col-auto d-none d-lg-block align-self-center"><i class="fa-solid fa-envelope-open text-muted"></i></div>
                <div class="col-lg-3 text-muted align-self-center">' . $readdate . '
                    <span class="float-end"><input type="checkbox" class="form-check-input" name="readcheck[' . $rm['pmid'] . ']" value="1" /></span>
                </div>
            </div>
        </div></div>';
    }

    $stoptrackingread = !empty($readmessages)
        ? '<div class="text-center"><button type="submit" class="btn btn-primary btn-sm" name="stoptracking" value="' . $lang->private['stop_tracking'] . '"><i class="fa-solid fa-xmark"></i> &nbsp;' . $lang->private['stop_tracking'] . '</button> &nbsp; <a href="private.php?action=stopalltracking&amp;my_post_key=' . $mybb->post_code . '" class="btn btn-primary btn-sm" style="color:#fff!important"><i class="fa-solid fa-xmark"></i> &nbsp;' . $lang->private['stop_tracking_all'] . '</a></div>'
        : '';

    if (!$readmessages) $readmessages = '<div class="ps-3 pe-3 pb-3">' . $lang->private['no_unreadmessages'] . '</div>';

    // Unread messages
    $postcount = (int)$db->fetch_field($db->simple_select('privatemessages', 'COUNT(pmid) as unreadpms', "receipt='1' AND folder!='3' AND status='0' AND fromid='" . $CURUSER['id'] . "'"), 'unreadpms');
    $page      = $mybb->get_input('unread_page', MyBB::INPUT_INT) ?: 1;
    $pages     = max(1, (int)ceil($postcount / $perpage));
    $page      = max(1, min($page, $pages));
    $start     = ($page - 1) * $perpage;
    $unread_multipage = multipage($postcount, $perpage, $page, 'private.php?action=tracking&amp;unread_page={page}');

    $unreadmessages = '';
    $query = $db->sql_query_prepared(
        'SELECT pm.pmid, pm.subject, pm.toid, pm.dateline, u.username AS tousername
         FROM privatemessages pm LEFT JOIN users u ON u.id = pm.toid
         WHERE pm.receipt = ? AND pm.folder != ? AND pm.status = ? AND pm.fromid = ?
         ORDER BY pm.dateline DESC LIMIT ?, ?',
        ['1', '3', '0', (int)$CURUSER['id'], (int)$start, (int)$perpage]
    );
    while ($um = $db->fetch_array($query)) {
        $um['subject']     = htmlspecialchars_uni($parser->parse_badwords($um['subject']));
        $um['tousername']  = htmlspecialchars_uni($um['tousername']);
        $um['profilelink'] = build_profile_link($um['tousername'], $um['toid']);
        $senddate          = my_datee('relative', $um['dateline']);
        $unreadmessages .= '<div class="card mb-0 border-0"><div class="card-body pt-0 inline_row">
            <div class="row g-2 pb-3 border-bottom mb-0">
                <div class="col-auto col-lg-1 align-self-center"><avatarep_uid_[' . $um['toid'] . ']></div>
                <div class="col align-self-center">
                    <h6 class="mb-0 text-forum">' . $um['subject'] . '</h6>
                    <span class="links small">' . $um['profilelink'] . '</span>
                </div>
                <div class="col-auto d-none d-lg-block align-self-center"><i class="fa-solid fa-envelope text-danger"></i></div>
                <div class="col-lg-3 text-muted align-self-center">' . $senddate . '
                    <span class="float-end"><input type="checkbox" class="form-check-input" name="unreadcheck[' . $um['pmid'] . ']" value="1" /></span>
                </div>
            </div>
        </div></div>';
    }

    $stoptrackingunread = !empty($unreadmessages)
        ? '<div class="text-center">
            <button type="submit" class="btn btn-primary btn-sm" name="stoptrackingunread" value="' . $lang->private['stop_tracking'] . '"><i class="fa-solid fa-xmark"></i> &nbsp;' . $lang->private['stop_tracking'] . '</button> &nbsp;
            <button type="submit" class="btn btn-primary btn-sm" name="cancel" value="' . $lang->private['delete'] . '"><i class="fa-solid fa-xmark"></i> &nbsp;' . $lang->private['delete'] . '</button>
           </div>'
        : '';

    if (!$unreadmessages) $unreadmessages = '<div class="ps-3 pe-3 pb-3">' . $lang->private['no_unreadmessages'] . '</div>';

    $plugins->run_hooks('private_tracking_end');
    stdhead($lang->private['pm_tracking']);
    echo '<div class="container-md"><div class="row">
        <div class="col-lg-3">' . $usercpnav . '</div>
        <div class="col">
            <form action="private.php" method="post">
            <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
            <input type="hidden" name="action" value="do_tracking" />
            <div class="card border-0 mb-4"><div class="card-header py-3 bg-nav rounded border-bottom-0">
                <div class="row g-2 text-forum">
                    <div class="col-1">&nbsp;</div>
                    <div class="col align-self-center">' . $lang->private['message_title'] . ' &mdash; ' . $lang->private['sentto'] . '</div>
                    <div class="col-3 align-self-center"><span class="d-none d-lg-inline-block">' . $lang->private['dateread'] . '</span>
                        <span class="float-end"><input type="checkbox" class="form-check-input checkall" name="allbox" /></span>
                    </div>
                </div>
            </div></div>
            ' . $readmessages . '
            <div class="card border-0 mb-4"><div class="card-header py-3 bg-nav rounded border-bottom-0">' . $stoptrackingread . '</div></div>
            </form>
            ' . $unread_multipage . '
            <form action="private.php" method="post">
            <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
            <input type="hidden" name="action" value="do_tracking" />
            <div class="card border-0 mb-4"><div class="card-header py-3 bg-nav rounded border-bottom-0">
                <div class="row g-2 text-forum">
                    <div class="col-1">&nbsp;</div>
                    <div class="col align-self-center">' . $lang->private['message_title'] . ' &mdash; ' . $lang->private['sentto'] . '</div>
                    <div class="col-3 align-self-center"><span class="d-none d-lg-inline-block">' . $lang->private['datesent'] . '</span>
                        <span class="float-end"><input type="checkbox" class="form-check-input checkall" name="allbox" /></span>
                    </div>
                </div>
            </div></div>
            ' . $unreadmessages . '
            <div class="card border-0 mb-4"><div class="card-header py-3 bg-nav rounded border-bottom-0">' . $stoptrackingunread . '</div></div>
            </form>
        </div>
    </div></div>';
    stdfoot();
}

// ── Do tracking ──────────────────────────────────────────────────────────────
if ($action === 'do_tracking' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('private_do_tracking_start');

    if (!empty($mybb->input['stoptracking'])) {
        foreach ($mybb->get_input('readcheck', MyBB::INPUT_ARRAY) as $key => $val) {
            $db->update_query('privatemessages', ['receipt' => 0], 'pmid=' . (int)$key . ' AND fromid=' . $CURUSER['id']);
        }
        $plugins->run_hooks('private_do_tracking_end');
        redirect('private.php?action=tracking', $lang->private['redirect_pmstrackingstopped']);

    } elseif (!empty($mybb->input['stoptrackingunread'])) {
        foreach ($mybb->get_input('unreadcheck', MyBB::INPUT_ARRAY) as $key => $val) {
            $db->update_query('privatemessages', ['receipt' => 0], 'pmid=' . (int)$key . ' AND fromid=' . $CURUSER['id']);
        }
        $plugins->run_hooks('private_do_tracking_end');
        redirect('private.php?action=tracking', $lang->private['redirect_pmstrackingstopped']);

    } elseif (!empty($mybb->input['cancel'])) {
        $unreadcheck = $mybb->get_input('unreadcheck', MyBB::INPUT_ARRAY);
        if (!empty($unreadcheck)) {
            $pmids   = implode(',', array_map('intval', array_keys($unreadcheck)));
            $pmuids  = [];
            $query   = $db->simple_select('privatemessages', 'uid', "pmid IN ($pmids) AND fromid='" . $CURUSER['id'] . "'");
            while ($pm = $db->fetch_array($query)) $pmuids[$pm['uid']] = $pm['uid'];
            $db->delete_query('privatemessages', "pmid IN ($pmids) AND receipt='1' AND status='0' AND fromid='" . $CURUSER['id'] . "'");
            foreach ($pmuids as $uid) update_pm_count($uid);
        }
        $plugins->run_hooks('private_do_tracking_end');
        redirect('private.php?action=tracking', $lang->private['redirect_pmstrackingcanceled']);
    }
}

// ── Stop all tracking ────────────────────────────────────────────────────────
if ($action === 'stopalltracking') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('private_stopalltracking_start');
    $db->update_query('privatemessages', ['receipt' => 0], "receipt='2' AND folder!='3' AND status!='0' AND fromid=" . $CURUSER['id']);
    $plugins->run_hooks('private_stopalltracking_end');
    redirect('private.php?action=tracking', $lang->private['redirect_allpmstrackingstopped']);
}

// ── Do stuff (move/delete) ───────────────────────────────────────────────────
if ($action === 'do_stuff' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('private_do_stuff');

    if (!empty($mybb->input['hop'])) {
        header('Location: private.php?fid=' . $mybb->get_input('jumpto'));

    } elseif (!empty($mybb->input['moveto'])) {
        $pms = array_map('intval', array_keys($mybb->get_input('check', MyBB::INPUT_ARRAY)));
        if (!empty($pms)) {
            $fid_move = $mybb->input['fid'] ?: 1;
            if (array_key_exists($fid_move, $foldernames)) {
                $db->update_query('privatemessages', ['folder' => $fid_move], 'pmid IN (' . implode(',', $pms) . ") AND uid='" . $CURUSER['id'] . "'");
                update_pm_count();
            } else {
                error($lang->error_invalidmovefid);
            }
        }
        redirect(!empty($mybb->input['fromfid'])
            ? 'private.php?fid=' . $mybb->get_input('fromfid', MyBB::INPUT_INT)
            : 'private.php', $lang->private['redirect_pmsmoved']);

    } elseif (!empty($mybb->input['delete'])) {
        $check = $mybb->get_input('check', MyBB::INPUT_ARRAY);
        if (!empty($check)) {
            $pmssql   = implode(',', array_map(fn($k) => "'" . (int)$k . "'", array_keys($check)));
            $deletepms = [];
            $query     = $db->simple_select('privatemessages', 'pmid, folder', "pmid IN ($pmssql) AND uid='" . $CURUSER['id'] . "' AND folder='4'");
            while ($dp = $db->fetch_array($query)) $deletepms[$dp['pmid']] = 1;

            foreach ($check as $key => $val) {
                $key = (int)$key;
                if (!empty($deletepms[$key])) {
                    $db->delete_query('privatemessages', "pmid='$key' AND uid='" . $CURUSER['id'] . "'");
                } else {
                    $db->update_query('privatemessages', ['folder' => 4, 'deletetime' => TIMENOW], "pmid='{$key}' AND uid='" . $CURUSER['id'] . "'");
                }
            }
        }
        update_pm_count();
        redirect(!empty($mybb->input['fromfid'])
            ? 'private.php?fid=' . $mybb->get_input('fromfid', MyBB::INPUT_INT)
            : 'private.php', $lang->private['redirect_pmsdeleted']);
    }
}

// ── Delete single PM ─────────────────────────────────────────────────────────
if ($action === 'delete') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('private_delete_start');

    $pmid_del = $mybb->get_input('pmid', MyBB::INPUT_INT);
    $query    = $db->simple_select('privatemessages', '*', "pmid='{$pmid_del}' AND uid='" . $CURUSER['id'] . "' AND folder='4'");

    if ($db->num_rows($query) == 1) {
        $db->delete_query('privatemessages', "pmid='{$pmid_del}'");
    } else {
        $db->update_query('privatemessages', ['folder' => 4, 'deletetime' => TIMENOW], "pmid='{$pmid_del}' AND uid='" . $CURUSER['id'] . "'");
    }

    update_pm_count();
    $plugins->run_hooks('private_delete_end');
    redirect('private.php', $lang->private['redirect_pmsdeleted']);
}

// ── Inbox ─────────────────────────────────────────────────────────────────────
if (!$mybb->input['action']) {
    $plugins->run_hooks('private_inbox');

    if (!$mybb->input['fid'] || !array_key_exists($mybb->input['fid'], $foldernames)) {
        $mybb->input['fid'] = 0;
    }

    $fid        = (int)$mybb->input['fid'];
    $folder     = !$fid ? 1 : $fid;
    $foldername = $foldernames[$fid];
    $sender     = ($folder == 2 || $folder == 3) ? $lang->private['sentto'] : $lang->private['sender'];

    $ordersel     = ['asc' => '', 'desc' => ''];
    $sortordernow = 'desc';
    switch (my_strtolower($mybb->get_input('order'))) {
        case 'asc':
            $sortordernow = 'asc';
            $ordersel['asc'] = 'selected="selected"';
            $oppsort = $lang->private['desc'];
            $oppsortnext = 'desc';
            break;
        default:
            $ordersel['desc'] = 'selected="selected"';
            $oppsort = $lang->private['asc'];
            $oppsortnext = 'asc';
            break;
    }

    $sortby = htmlspecialchars_uni($mybb->get_input('sortby'));
    $sortfield = match ($mybb->get_input('sortby')) {
        'subject'  => 'subject',
        'username' => 'username',
        default    => 'dateline',
    };
    if ($sortfield === 'dateline') { $sortby = 'dateline'; $mybb->input['sortby'] = 'dateline'; }

    $sortsel = $orderarrow = ['subject' => '', 'username' => '', 'dateline' => ''];
    $sortsel[$sortby] = 'selected="selected"';

    $selective = ($fid == 1) ? " AND status='0'" : '';
    $pmscount  = (int)$db->fetch_field($db->simple_select('privatemessages', 'COUNT(*) AS total', "uid='" . $CURUSER['id'] . "' AND folder='$folder'{$selective}"), 'total');

    $perpage = max(1, (int)($f_threadsperpage ?: 20));
    $page    = $mybb->get_input('page', MyBB::INPUT_INT);
    $page    = $page > 0 ? $page : 1;
    $pages   = (int)ceil($pmscount / $perpage);
    if ($page > $pages) { $page = 1; }
    $start   = ($page - 1) * $perpage;
    $end     = $start + $perpage;
    $upper   = min($end, $pmscount);

   $page_url = (!empty($mybb->input['order']) || ($sortby && $sortby !== 'dateline'))
    ? "private.php?fid={$fid}&sortby={$sortby}&order={$sortordernow}"
    : "private.php?fid={$fid}";
    $multipage = multipage($pmscount, $perpage, $page, $page_url);

    $selective  = '';
    $messagelist = '';
    $cached_users = [];

    // Cache recipients for sent/drafts folders
    if ($folder == 2 || $folder == 3) {
        $u = ($sortfield === 'username') ? 'u.' : 'pm.';
        $get_users = [];
        $users_query = $db->sql_query("
            SELECT pm.recipients FROM privatemessages pm
            LEFT JOIN users u ON (u.id = pm.toid)
            WHERE pm.folder = '{$folder}' AND pm.uid = '" . $CURUSER['id'] . "'
            ORDER BY {$u}{$sortfield} {$sortordernow}
            LIMIT {$start}, {$perpage}
        ");
        while ($row = $db->fetch_array($users_query)) {
            $recipients = my_unserialize($row['recipients']);
            foreach (['to', 'bcc'] as $type) {
                if (isset($recipients[$type]) && is_array($recipients[$type]) && count($recipients[$type])) {
                    $get_users = array_merge($get_users, $recipients[$type]);
                }
            }
        }
        if ($get_users = implode(',', array_unique($get_users))) {
            $users_query = $db->simple_select('users', 'id, username, usergroup, displaygroup', "id IN ({$get_users})");
            while ($user = $db->fetch_array($users_query)) $cached_users[$user['id']] = $user;
        }
    }

    if ($folder == 2 || $folder == 3) {
        $pm_prefix = ($sortfield === 'username') ? 'tu.' : 'pm.';
    } else {
        if ($fid == 1) $selective = " AND pm.status='0'";
        $pm_prefix = ($sortfield === 'username') ? 'fu.' : 'pm.';
    }

    $query = $db->sql_query("
        SELECT pm.*, fu.username AS fromusername, tu.username as tousername, fu.avatar, fu.avatardimensions
        FROM privatemessages pm
        LEFT JOIN users fu ON (fu.id = pm.fromid)
        LEFT JOIN users tu ON (tu.id = pm.toid)
        WHERE pm.folder = '$folder' AND pm.uid = '" . $CURUSER['id'] . "'{$selective}
        ORDER BY {$pm_prefix}{$sortfield} {$sortordernow}
        LIMIT $start, $perpage
    ");

    if ($db->num_rows($query) > 0) {
        $bgcolor = alt_trow(true);
        while ($message = $db->fetch_array($query)) {
            $msgstatus = $msgalt = $fa_icon_html = $badge_class = $popover_title = $popover_content = '';

            match ((int)$message['status']) {
                0 => [
                    $msgstatus     = 'new_pm',
                    $msgalt        = $lang->private['new_pm'],
                    $fa_icon_html  = '<i class="fa-solid fa-envelope fa-fw"></i>',
                    $badge_class   = 'status-new',
                    $popover_title = 'New Message',
                    $popover_content = 'This message has not been read yet',
                ],
                1 => [
                    $msgstatus     = 'old_pm',
                    $msgalt        = $lang->private['old_pm'],
                    $fa_icon_html  = '<i class="fa-regular fa-envelope-open fa-fw"></i>',
                    $badge_class   = 'status-read',
                    $popover_title = 'Read Message',
                    $popover_content = 'This message has been read',
                ],
                3 => [
                    $msgstatus     = 're_pm',
                    $msgalt        = $lang->private['reply_pm'],
                    $fa_icon_html  = '<i class="fa-solid fa-reply fa-fw"></i>',
                    $badge_class   = 'status-reply',
                    $popover_title = 'Reply to Message',
                    $popover_content = 'This is a reply to a previous message',
                ],
                4 => [
                    $msgstatus     = 'fw_pm',
                    $msgalt        = $lang->private['fwd_pm'],
                    $fa_icon_html  = '<i class="fa-solid fa-share fa-fw"></i>',
                    $badge_class   = 'status-forward',
                    $popover_title = 'Forwarded Message',
                    $popover_content = 'This message has been forwarded',
                ],
                default => null,
            };

            $tofromuid = 0;
            if ($folder == 2 || $folder == 3) {
                $recipients = my_unserialize($message['recipients']);
                $to_users = $bcc_users = '';
                $has_multi = isset($recipients['to']) && (count($recipients['to']) > 1 ||
                    (count($recipients['to']) == 1 && isset($recipients['bcc']) && count($recipients['bcc']) > 0));

                if ($has_multi) {
                    foreach ($recipients['to'] as $uid) {
                        if (!isset($cached_users[$uid])) continue;
                        $user = $cached_users[$uid];
                        $user['username'] = htmlspecialchars_uni($user['username']);
                        $uname = format_name($user['username'], $user['usergroup'], $user['displaygroup']) ?: $lang->na;
                        $to_users .= '<div class="popup_item_container"><a href="' . get_profile_link($uid) . '" class="popup_item">' . $uname . '</a></div>';
                    }
                    if (isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc'])) {
                        $bcc_users = '<div class="tcat"><strong>' . $lang->private['bcc'] . '</strong></div>';
                        foreach ($recipients['bcc'] as $uid) {
                            if (!isset($cached_users[$uid])) continue;
                            $user = $cached_users[$uid];
                            $user['username'] = htmlspecialchars_uni($user['username']);
                            $uname = format_name($user['username'], $user['usergroup'], $user['displaygroup']) ?: $lang->na;
                            $bcc_users .= '<div class="popup_item_container"><a href="' . get_profile_link($uid) . '" class="popup_item">' . $uname . '</a></div>';
                        }
                    }
                    $tofromusername = '<a href="private.php?action=read&amp;pmid=' . $message['pmid'] . '">' . $lang->private['multiple_recipients'] . '</a>';
                } elseif ($message['toid']) {
                    $tofromusername = htmlspecialchars_uni($message['tousername']);
                    $tofromuid      = $message['toid'];
                } else {
                    $tofromusername = 'not_sent';
                }
            } else {
                $tofromusername = htmlspecialchars_uni($message['fromusername']);
                $tofromuid      = $message['fromid'];
                if ($tofromuid == 0) $tofromusername = $SITENAME . ' Engine';
                if (!$tofromusername) { $tofromuid = 0; $tofromusername = $lang->na; }
            }

            $tofromusername = build_profile_link($tofromusername, $tofromuid);

            $useravatar = format_avatar($message['avatar'], $message['avatardimensions']);
            $ava_img = str_starts_with($useravatar['image'], '<')
                ? '<svg class="nav-avatar rounded border" width="50" height="50" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/><text x="50" y="55" text-anchor="middle" font-size="12" fill="#666">No Avatar</text></svg>'
                : '<img class="user-avatar" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . ' />';

            $denyreceipt = ($usergroups['candenypmreceipts'] == 1 && $message['receipt'] == '1' && $message['folder'] != '3' && $message['folder'] != 2)
                ? '<span class="smalltext"><a href="private.php?action=read&amp;pmid=' . $message['pmid'] . '&amp;denyreceipt=1">' . $lang->private['deny_receipt'] . '</a></span>'
                : '';

            if (!trim($message['subject'])) $message['subject'] = $lang->private['pm_no_subject'];
            $message['subject'] = htmlspecialchars_uni($parser->parse_badwords($message['subject']));
            $senddate = $message['folder'] != '3' ? my_datee('relative', $message['dateline']) : $lang->not_sent;

            $plugins->run_hooks('private_message');

            $messagelist .= '<div class="card message-card shadow-sm border-0 mb-3">
    <div class="card-body p-4">
        <div class="row align-items-center g-3">
            <div class="col-auto">' . $ava_img . '</div>
            <div class="col">
                <div class="d-flex flex-column">
                    <h6 class="mb-1">
                        <a href="private.php?action=read&amp;pmid=' . $message['pmid'] . '" class="message-title ' . $msgstatus . ' text-decoration-none fw-semibold">' . $message['subject'] . '</a>
                        ' . $denyreceipt . '
                    </h6>
                    <span class="text-muted small"><i class="fas fa-user me-1"></i>' . $tofromusername . '</span>
                </div>
            </div>
            <div class="col-auto d-none d-lg-block">
                <span class="status-badge ' . $badge_class . '" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-title="' . $popover_title . '" data-bs-content="' . $popover_content . '" data-bs-container="body" title="' . $msgalt . '">' . $fa_icon_html . '</span>
            </div>
            <div class="col-lg-3">
                <div class="d-flex align-items-center justify-content-end gap-3">
                    <span class="text-muted small text-nowrap"><i class="far fa-clock me-1"></i>' . $senddate . '</span>
                    <div class="form-check form-switch message-select-switch">
                        <input type="checkbox" class="form-check-input message-select-toggle" name="check[' . $message['pmid'] . ']" value="1" id="select-' . $message['pmid'] . '" data-pmid="' . $message['pmid'] . '">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';
            $bgcolor = alt_trow();
        }
    } else {
        $messagelist .= '<div class="text-center py-5">
            <div style="font-size:64px;color:#cbd5e0;margin-bottom:20px;"><i class="fas fa-inbox"></i></div>
            <div style="font-size:18px;color:#4a5568;margin-bottom:10px;"><strong>' . $lang->private['nomessages'] . '</strong></div>
            <div style="font-size:14px;color:#718096;"><i class="fas fa-envelope-open-text"></i> Your mailbox is empty</div>
        </div>';
    }

    // PM quota bar
    $pmspacebar = '';
    if ($usergroups['pmquota'] != 0) {
        $pmscount_arr = $db->fetch_array($db->simple_select('privatemessages', 'COUNT(*) AS total', "uid='" . $CURUSER['id'] . "'"));
        $spaceused    = $pmscount_arr['total'] == 0 ? 0 : min(100, $pmscount_arr['total'] / $usergroups['pmquota'] * 100);
        $pmspacebar   = '<div class="progress mt-3" style="height:40px"><div class="progress-bar" role="progressbar" style="width:' . $spaceused . '%"></div></div>
            <div class="mt-1">' . round($spaceused, 0) . '% ' . $lang->private['pmspaceused'] . '</div>';
        $limitwarning = $pmscount_arr['total'] >= $usergroups['pmquota']
            ? '<div class="progress mt-3"><div class="progress-bar" role="progressbar">' . $lang->private['reached_warning'] . '</div></div>'
            : '';
    } else {
        $limitwarning = '';
    }

    $plugins->run_hooks('private_end');

    stdhead('title');
    echo '<script src="' . $BASEURL . '/scripts/popover.js"></script>';
    echo '<div class="container-md"><div class="row">
    <div class="col-lg-3">' . $usercpnav . '</div>
    <div class="col">
        ' . $limitwarning . '
        <form action="private.php" method="post" name="pmForm">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
        <div class="card border-0 mb-3">
            <div class="card-header py-3 bg-nav rounded border-bottom-0">
                <div class="row g-2 text-forum">
                    <div class="col-1">&nbsp;</div>
                    <div class="col align-self-center">
                        <a href="private.php?fid=' . $fid . '&amp;sortby=subject&amp;order=asc">' . $lang->private['message_title'] . '</a> &mdash;
                        <a href="private.php?fid=' . $fid . '&amp;sortby=username&amp;order=asc">' . $sender . '</a>
                    </div>
                    <div class="col-3 align-self-center">
                        <div class="d-flex align-items-center justify-content-end gap-2">
                            <a href="private.php?fid=' . $fid . '&amp;sortby=dateline&amp;order=desc" class="d-none d-lg-inline-block text-decoration-none">' . $lang->private['date_sent'] . '</a>
                            <div class="form-check form-switch ms-auto">
                                <input class="form-check-input select-all-switch" type="checkbox" role="switch" id="selectAllSwitch" title="' . $lang->private['check_all'] . '">
                                <label class="form-check-label small ms-2" for="selectAllSwitch">' . $lang->private['check_all'] . '</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        ' . $messagelist . '
        ' . $multipage . '
        <div class="card border-0 mt-3">
            <div class="card-header py-3 bg-nav rounded border-bottom-0">
                <div class="row g-1">
                    <div class="col">&nbsp;</div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm" name="moveto" value="' . $lang->private['move_to'] . '">' . $lang->private['move_to'] . ' &nbsp;<i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                    <div class="col-auto">' . $folderoplist . '</div>
                    <div class="col-auto">&nbsp;' . $lang->private['or'] . '&nbsp;
                        <button type="submit" class="btn btn-primary btn-sm" name="delete" value="' . $lang->private['delete'] . '"><i class="fa-solid fa-xmark"></i> &nbsp;' . $lang->private['delete'] . '</button>
                    </div>
                    <div class="col">&nbsp;</div>
                </div>
            </div>
        </div>
        ' . $pmspacebar . '
        <input type="hidden" name="action" value="do_stuff" />
        </form>
    </div>
</div></div>
<style>
.status-badge { display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;font-size:16px;transition:all .3s;cursor:default;border:1px solid transparent;box-shadow:0 3px 8px rgba(0,0,0,.08); }
.status-badge:hover { transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,.12); }
.status-badge.status-new { background:linear-gradient(135deg,rgba(231,76,60,.15),rgba(231,76,60,.08));color:#e74c3c;border-color:rgba(231,76,60,.2);animation:pulse-glow 2s infinite; }
.status-badge.status-read { background:linear-gradient(135deg,rgba(149,165,166,.15),rgba(149,165,166,.08));color:#95a5a6;border-color:rgba(149,165,166,.2); }
.status-badge.status-reply { background:linear-gradient(135deg,rgba(52,152,219,.15),rgba(52,152,219,.08));color:#3498db;border-color:rgba(52,152,219,.2); }
.status-badge.status-forward { background:linear-gradient(135deg,rgba(39,174,96,.15),rgba(39,174,96,.08));color:#27ae60;border-color:rgba(39,174,96,.2); }
@keyframes pulse-glow { 0%{box-shadow:0 0 0 0 rgba(231,76,60,.4)} 70%{box-shadow:0 0 0 8px rgba(231,76,60,0)} 100%{box-shadow:0 0 0 0 rgba(231,76,60,0)} }
.message-card { border-radius:12px;transition:all .3s;background:linear-gradient(135deg,#fff,#f8f9fa); }
.message-card:hover { transform:translateY(-2px);box-shadow:0 8px 25px rgba(0,0,0,.1)!important; }
.user-avatar { width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid #e9ecef;transition:border-color .3s; }
.user-avatar:hover { border-color:#007bff; }
.nav-avatar { width:44px;height:44px;border-radius:50%;object-fit:cover;border:1px solid #ccc; }
.message-title { color:#2c3e50;transition:color .2s; }
.message-title:hover { color:#3498db; }
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const toggles = document.querySelectorAll(".message-select-toggle");
    const selectAll = document.getElementById("selectAllSwitch");
    function updateSelectAll() {
        if (!selectAll) return;
        const checked = document.querySelectorAll(".message-select-toggle:checked").length;
        selectAll.checked = checked === toggles.length;
        selectAll.indeterminate = checked > 0 && checked < toggles.length;
    }
    toggles.forEach(t => {
        t.addEventListener("change", function() {
            this.closest(".message-card")?.classList.toggle("selected", this.checked);
            updateSelectAll();
        });
    });
    if (selectAll) {
        selectAll.addEventListener("change", function() {
            toggles.forEach(t => { t.checked = this.checked; t.closest(".message-card")?.classList.toggle("selected", this.checked); });
        });
    }
});
</script>';
    stdfoot();
}