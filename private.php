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

/** Text from private.lang.php (English fallback), escaped for HTML */
$pl = static fn(string $key, string $fallback): string
    => htmlspecialchars((string)($lang->private[$key] ?? $fallback), ENT_QUOTES | ENT_HTML5, 'UTF-8');

/** "No Avatar" placeholder */
$noAvatarSvg = '<svg class="nav-avatar rounded border" width="50" height="50" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/><text x="50" y="55" text-anchor="middle" font-size="12" fill="#666">'
    . htmlspecialchars((string)($lang->header['hdr_no_avatar'] ?? 'No Avatar'), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</text></svg>';

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
$folderoplist = '<input type="hidden" value="' . $from_fid . '" name="fromfid" form="pmForm" />
<select name="fid" form="pmForm" class="form-select form-select-sm border w-auto pe-5">' . $folderoplist_folder . '</select>';
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
    $db->sql_query_prepared("UPDATE users SET pmnotice = 1 WHERE id = ?", [$CURUSER['id']]);

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
    $to_lower   = array_map('my_strtolower', $to);
    $time_cutoff = TIMENOW - (5 * 60 * 60);

    $to_placeholders = implode(',', array_fill(0, count($to_lower), '?'));
    $query = $db->sql_query_prepared("
        SELECT pm.pmid FROM privatemessages pm
        LEFT JOIN users u ON (u.id = pm.toid)
        WHERE LOWER(u.username) IN ({$to_placeholders})
          AND pm.dateline > ?
          AND pm.fromid = ?
          AND pm.subject = ?
          AND pm.message = ?
          AND pm.folder != '3'
        LIMIT 0, 1
    ", [...$to_lower, $time_cutoff, $CURUSER['id'], $mybb->get_input('subject'), $mybb->get_input('message')]);
    if ($query && $db->num_rows($query) > 0) {
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
        $query = $db->sql_query_prepared("
            SELECT pm.*, u.username AS quotename
            FROM privatemessages pm
            LEFT JOIN users u ON (u.id = pm.fromid)
            WHERE pm.pmid = ? AND pm.uid = ?
        ", [$mybb->get_input('pmid', MyBB::INPUT_INT), $CURUSER['id']]);
        $pm      = $query ? $db->fetch_array($query) : null;
        $message = htmlspecialchars_uni($parser->parse_badwords($pm['message']));
        $subject = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));

        if ($pm['folder'] === '3') {
            $mybb->input['uid'] = $pm['toid'];
            if ($pm['receipt']) $optionschecked['readreceipt'] = 'checked="checked"';

            $recipients   = my_unserialize($pm['recipients']);
            $recipientid_list = [];
            $recipient_list = [];

            foreach (['to', 'bcc'] as $type) {
                if (isset($recipients[$type]) && is_array($recipients[$type])) {
                    foreach ($recipients[$type] as $recipient) {
                        $recipient_list[$type][] = $recipient;
                        $recipientid_list[] = (int)$recipient;
                    }
                }
            }

            if (!empty($recipientid_list)) {
                $ph = implode(',', array_fill(0, count($recipientid_list), '?'));
                $query = $db->sql_query_prepared("SELECT id, username FROM users WHERE id IN ({$ph})", $recipientid_list);
                while ($query && ($user = $db->fetch_array($query))) {
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
                    : $db->fetch_field($db->sql_query_prepared("SELECT username FROM users WHERE id = ?", [$uid]), 'username');
                $to  = htmlspecialchars_uni($to);
            } elseif ($do === 'replyall') {
                $subject      = "Re: {$subject}";
                $recipients   = my_unserialize($pm['recipients']);
                $recipientid_list = [(int)$pm['fromid']];

                if (isset($recipients['to']) && is_array($recipients['to'])) {
                    foreach ($recipients['to'] as $recipient) {
                        if ($recipient === $CURUSER['id']) continue;
                        $recipientid_list[] = (int)$recipient;
                    }
                }
                $comma = '';
                $ph = implode(',', array_fill(0, count($recipientid_list), '?'));
                $query = $db->sql_query_prepared("SELECT id, username FROM users WHERE id IN ({$ph})", $recipientid_list);
                while ($query && ($user = $db->fetch_array($query))) {
                    $to    .= $comma . htmlspecialchars_uni($user['username']);
                    $comma  = $lang->private['comma'];
                }
            }
        }
    }

    // New PM with preset recipient
    if ($mybb->get_input('uid', MyBB::INPUT_INT) && empty($mybb->input['preview'])) {
        $to = htmlspecialchars_uni($db->fetch_field(
            $db->sql_query_prepared("SELECT username FROM users WHERE id = ?", [$mybb->get_input('uid', MyBB::INPUT_INT)]),
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

    $pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);
    $do   = $mybb->get_input('do');
    if (!in_array($do, ['forward', 'reply', 'replyall'])) $do = '';

    $private_send_tracking = '';
    if ($mybb->usergroup['cantrackpms']) {
        $private_send_tracking = ucp_check(
            'options[readreceipt]',
            $optionschecked['readreceipt'],
            $lang->private['quickreply_read_receipt'],
            'fa-envelope-open-text',
            'info'
        );
    }

    $plugins->run_hooks('private_send_end');

    $send = ucp_open('fa-pen-fancy', 'primary', $lang->private['compose_pm'], $pl('pm_recipients_hint', ''))
        . $preview
        . $send_errors
        . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-select2.css">'
        . '<form action="private.php" method="post" name="input">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '<div id="fileIdsContainer"></div>'

        . ucp_sec_open('fa-users', 'primary', $pl('compose_to', 'Recipients:'))
        .   '<div class="st-field st-field--split">'
        .     '<label class="st-label" for="to"><i class="fa-solid fa-users text-primary"></i>' . $pl('compose_to', 'Recipients:') . '</label>'
        .     '<input name="to" id="to" value="' . $to . '" tabindex="1" class="form-control" placeholder="' . $pl('pm_search_users', 'Search for users') . '" style="display:none;">'
        .     '<div id="to-container"></div>'
        .     '<div class="select2-counter" id="recipientCounter"><i class="fa-solid fa-user-plus me-1"></i> ' . $pl('compose_to', 'Recipients:') . ' 0/' . (int)$usergroups['maxpmrecipients'] . '</div>'
        .     '<div class="st-hint"><i class="fa-solid fa-circle-info"></i>' . $pl('pm_recipients_hint', 'Click to search and select users (minimum 2 characters)') . '</div>'
        .   '</div>'
        . ucp_sec_close()

        . ucp_sec_open('fa-heading', 'info', $pl('compose_subject', 'Subject:'))
        .   '<div class="st-field">'
        .     '<label class="st-label" for="subject"><i class="fa-solid fa-heading text-info"></i>' . $pl('compose_subject', 'Subject:') . '</label>'
        .     '<input type="text" class="form-control" name="subject" id="subject" maxlength="85" value="' . $subject . '" tabindex="3" placeholder="' . $pl('pm_subject_placeholder', 'Enter message subject...') . '" />'
        .   '</div>'
        . ucp_sec_close()

        . ucp_sec_open('fa-pen-fancy', 'primary', $pl('compose_message', 'Message:'))
        .   $codebuttons
        .   '<textarea name="message" id="message" class="form-control st-editor-text" style="width:100%;height:350px" tabindex="4" placeholder="' . $pl('pm_message_placeholder', 'Write your message here...') . '">' . $message . '</textarea>'
        . ucp_sec_close()

        . '<div class="st-optbar">'
        .   '<a class="st-toggle" data-bs-toggle="collapse" aria-expanded="false" href="#collapse-pmop" role="button">'
        .     '<i class="fa-solid fa-gear"></i> ' . $pl('compose_options', 'Options:')
        .   '</a>'
        .   '<button type="submit" class="btn btn-outline-secondary st-pill" name="preview" value="Preview" tabindex="11">'
        .     '<i class="fa-solid fa-eye me-1"></i>' . $pl('preview', 'Preview')
        .   '</button>'
        . '</div>'

        . '<div id="collapse-pmop" class="collapse mt-2">'
        .   ucp_check('options[savecopy]', $optionschecked['savecopy'], $lang->private['options_save_copy'] ?? 'Save a copy in my Sent Items folder', 'fa-save', 'success')
        .   $private_send_tracking
        . '</div>'

        . '<input type="hidden" name="action" value="do_send" />'
        . '<input type="hidden" name="pmid" value="' . $pmid . '" />'
        . '<input type="hidden" name="do" value="' . $do . '" />'

        . '<div class="st-submitbar justify-content-end">'
        .   '<button type="submit" class="btn btn-primary st-pill" name="submit" value="Send Message" tabindex="9">'
        .     '<i class="fa-solid fa-paper-plane me-2"></i>' . $pl('send_message', 'Send Message')
        .   '</button>'
        . '</div>'
        . '</form>'
        . ucp_close()
        . '<script>window.maxRecipients = ' . (int)$usergroups['maxpmrecipients'] . ';
window.SELECT2_LANG = ' . json_encode([
    'searchUser'      => (string)($lang->private['pm_search_users'] ?? 'Search for users'),
    'noMatches'       => (string)($lang->header['select2_nomatches'] ?? 'No matches found'),
    'inputTooShort'   => (string)($lang->header['select2_inputtooshort_plural'] ?? 'Please enter {1} or more characters'),
    'selectionTooBig' => (string)($lang->header['select2_toobig_plural'] ?? 'You can only select {1} items'),
    'counterLabel'    => (string)($lang->private['compose_to'] ?? 'Recipients:'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>'
        . '<script src="' . $BASEURL . '/scripts/select2-field.js?ver=2"></script>';

    stdhead($lang->private['compose_pm']);
    build_breadcrumb();
    echo $send;
    stdfoot();
    exit;
}

// ── Read PM ───────────────────────────────────────────────────────────────────
if ($action === 'read') {
    $plugins->run_hooks('private_read');

    $pmid  = $mybb->get_input('pmid', MyBB::INPUT_INT);
    $query = $db->sql_query_prepared("
        SELECT pm.*, u.*
        FROM privatemessages pm
        LEFT JOIN users u ON (u.id = pm.fromid)
        WHERE pm.pmid = ? AND pm.uid = ?
    ", [$pmid, $CURUSER['id']]);
    $pm = $query ? $db->fetch_array($query) : null;

    if (!$pm) stderr($lang->private['error_invalidpm']);

    if ($pm['folder'] == 3) {
        header("Location: private.php?action=send&pmid={$pm['pmid']}");
        exit;
    }

    if (!empty($pm['usergroup']) && isset($groupscache[$pm['usergroup']])) {
        foreach (['title' => 'grouptitle', 'usertitle' => 'groupusertitle', 'image' => 'groupimage', 'namestyle' => 'namestyle'] as $field => $key) {
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
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($updatearray)));
        $params = array_values($updatearray);
        $params[] = $pmid;
        $db->sql_query_prepared("UPDATE privatemessages SET {$set} WHERE pmid = ?", $params);
        update_pm_count($CURUSER['id'], 6);
        if ($CURUSER['unreadpms'] - 1 <= 0 && $CURUSER['pmnotice'] == 2) {
            $db->sql_query_prepared("UPDATE users SET pmnotice = 1 WHERE id = ?", [$CURUSER['id']]);
        }
    } elseif ($pm['status'] == 3 && $pm['statustime']) {
        $reply_date   = my_datee('relative', $pm['statustime']);
        $reply_string = (TIMENOW - $pm['statustime']) < 3600 ? $lang->private['you_replied'] : $lang->private['you_replied_on'];
        $action_time  = '<div class="st-notice st-soft-success mb-3"><i class="fa-solid fa-check"></i><div>' . sprintf($reply_string, $reply_date) . '</div></div>';
    } elseif ($pm['status'] == 4 && $pm['statustime']) {
        $forward_date   = my_datee('relative', $pm['statustime']);
        $forward_string = (TIMENOW - $pm['statustime']) < 3600 ? $lang->private['you_forwarded'] : $lang->private['you_forwarded_on'];
        $action_time    = '<div class="st-notice st-soft-success mb-3"><i class="fa-solid fa-check"></i><div>' . sprintf($forward_string, $forward_date) . '</div></div>';
    }

    $pm['userusername'] = $pm['username'];
    $pm['subject']      = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));
    if ($pm['fromid'] == 0) $pm['username'] = '' . $SITENAME . ' Engine';
    if (!$pm['username'])   $pm['username'] = 'na';

    $pm['recipients'] = my_unserialize($pm['recipients']);
    $recipient_id_list = isset($pm['recipients']['to']) && is_array($pm['recipients']['to'])
        ? array_map('intval', $pm['recipients']['to'])
        : [(int)$pm['toid']];

    if (!isset($pm['recipients']['to'])) $pm['recipients']['to'] = [$pm['toid']];

    $show_bcc = 0;
    if (isset($pm['recipients']['bcc']) && count($pm['recipients']['bcc']) > 0) {
        $show_bcc  = 1;
        $recipient_id_list = array_merge($recipient_id_list, array_map('intval', $pm['recipients']['bcc']));
    }

    $bcc_recipients = $to_recipients = $bcc_form_val = [];
    $ph = implode(',', array_fill(0, count($recipient_id_list), '?'));
    $query = $db->sql_query_prepared("SELECT id, username FROM users WHERE id IN ({$ph})", $recipient_id_list);
    while ($query && ($recipient = $db->fetch_array($query))) {
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
            : htmlspecialchars_uni($db->fetch_field($db->sql_query_prepared("SELECT username FROM users WHERE id = ?", [$pm['fromid']]), 'username'));

        $private_send_tracking = $mybb->usergroup['cantrackpms']
            ? ucp_check('options[readreceipt]', $optionschecked['readreceipt'], $lang->private['quickreply_read_receipt'], 'fa-envelope-open-text', 'info')
            : '';

        $quickreply = '<form action="private.php" method="post" name="input" class="mt-4">'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
            . '<input type="hidden" name="to" value="' . $to . '" />'
            . '<input type="hidden" name="bcc" value="' . $bcc_form_val . '" />'
            . '<input type="hidden" name="subject" value="Re: ' . $subject . '" />'
            . '<input type="hidden" name="action" value="do_send" />'
            . '<input type="hidden" name="pmid" value="' . $pmid . '" />'
            . '<input type="hidden" name="do" value="reply" />'
            . ucp_sec_open('fa-reply', 'primary', $lang->private['quickreply_save_copy'] ?? 'Quick Reply')
            .   '<div class="st-field">'
            .     '<label class="st-label" for="message"><i class="fa-solid fa-pen-fancy text-primary"></i>' . ($lang->private['compose_message'] ?? 'Message') . '</label>'
            .     '<textarea class="form-control st-editor-text" style="min-height:150px;" name="message" id="message" tabindex="1">' . $quoted_message . '</textarea>'
            .   '</div>'
            .   '<div id="collapse-reply" class="collapse mt-2">'
            .     ucp_check('options[savecopy]', $optionschecked['savecopy'], $lang->private['quickreply_save_copy'], 'fa-save', 'success')
            .     $private_send_tracking
            .   '</div>'
            . ucp_sec_close()
            . '<div class="st-submitbar justify-content-between">'
            .   '<a class="btn btn-outline-secondary st-pill" data-bs-toggle="collapse" href="#collapse-reply" role="button"><i class="fa-solid fa-gear"></i></a>'
            .   '<div class="d-flex gap-2">'
            .     '<button type="submit" class="btn btn-outline-secondary st-pill" name="preview" value="' . $lang->private['preview'] . '" tabindex="3"><i class="fa-solid fa-eye me-1"></i>' . $lang->private['preview'] . '</button>'
            .     '<button type="submit" class="btn btn-primary st-pill" value="' . $lang->private['send_message'] . '" tabindex="2" accesskey="s"><i class="fa-solid fa-paper-plane me-1"></i>' . $lang->private['send_message'] . '</button>'
            .   '</div>'
            . '</div>'
            . '</form>';
    }

    $plugins->run_hooks('private_read_end');
    stdhead('title');
    build_breadcrumb();
    echo ucp_open('fa-envelope-open-text', 'info', $pm['subject'])
        . $action_time
        . '<div id="posts">' . $message . '</div>'
        . $quickreply
        . ucp_close();
    stdfoot();
    exit;
}

// ── Tracking ─────────────────────────────────────────────────────────────────
if ($action === 'tracking') {
    $plugins->run_hooks('private_tracking_start');

    $perpage = max(1, (int)($f_postsperpage ?: 20));

    // Read messages
    $read_count_query = $db->sql_query_prepared(
        "SELECT COUNT(pmid) as readpms FROM privatemessages WHERE receipt='2' AND folder!='3' AND status!='0' AND fromid=?",
        [$CURUSER['id']]
    );
    $postcount = $read_count_query ? (int)$db->fetch_field($read_count_query, 'readpms') : 0;
    $page      = $mybb->get_input('read_page', MyBB::INPUT_INT) ?: 1;
    $pages     = max(1, (int)ceil($postcount / $perpage));
    $page      = max(1, min($page, $pages));
    $start     = ($page - 1) * $perpage;
    $read_multipage = multipage($postcount, $perpage, $page, 'private.php?action=tracking&amp;read_page={page}');

    $readmessages = '';
    $query = $db->sql_query_prepared("
        SELECT pm.pmid, pm.subject, pm.toid, pm.readtime, u.username as tousername, u.avatar, u.avatardimensions
        FROM privatemessages pm
        LEFT JOIN users u ON (u.id = pm.toid)
        WHERE pm.receipt = '2' AND pm.folder != '3' AND pm.status != '0' AND pm.fromid = ?
        ORDER BY pm.readtime DESC
        LIMIT ?, ?
    ", [$CURUSER['id'], $start, $perpage]);
    while ($query && ($rm = $db->fetch_array($query))) {
        $rm['subject']     = htmlspecialchars_uni($parser->parse_badwords($rm['subject']));
        $rm['tousername']  = htmlspecialchars_uni($rm['tousername']);
        $rm['profilelink'] = build_profile_link($rm['tousername'], $rm['toid']);
        $readdate          = my_datee('relative', $rm['readtime']);
        $rm_useravatar = format_avatar($rm['avatar'], $rm['avatardimensions']);
        $rm_ava_img = str_starts_with($rm_useravatar['image'], '<')
            ? $noAvatarSvg
            : '<img class="user-avatar" src="' . $rm_useravatar['image'] . '" alt="" ' . $rm_useravatar['width_height'] . ' />';
        $readmessages .= '<div class="st-frow">'
            . '<div class="col-auto">' . $rm_ava_img . '</div>'
            . '<div class="st-frow__body">'
            .   '<div class="st-frow__top"><a class="st-frow__title" href="private.php?action=read&amp;pmid=' . $rm['pmid'] . '">' . $rm['subject'] . '</a></div>'
            .   '<div class="st-frow__desc">' . $rm['profilelink'] . '</div>'
            . '</div>'
            . '<div class="st-frow__last">'
            .   '<span class="st-badge st-soft-info"><i class="fa-regular fa-clock"></i>' . $readdate . '</span>'
            .   '<input type="checkbox" class="form-check-input ms-2" name="readcheck[' . $rm['pmid'] . ']" value="1" />'
            . '</div>'
            . '</div>';
    }

    $stoptrackingread = !empty($readmessages)
        ? '<div class="st-submitbar justify-content-center">'
        .   '<button type="submit" class="btn btn-outline-primary st-pill" name="stoptracking" value="' . $lang->private['stop_tracking'] . '"><i class="fa-solid fa-xmark me-1"></i>' . $lang->private['stop_tracking'] . '</button>'
        .   '<a href="private.php?action=stopalltracking&amp;my_post_key=' . $mybb->post_code . '" class="btn btn-outline-danger st-pill"><i class="fa-solid fa-xmark me-1"></i>' . $lang->private['stop_tracking_all'] . '</a>'
        . '</div>'
        : '';

    if (!$readmessages) {
        $readmessages = '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-regular fa-envelope-open"></i></div>' . $lang->private['no_unreadmessages'] . '</div>';
    }

    // Unread messages
    $unread_count_query = $db->sql_query_prepared(
        "SELECT COUNT(pmid) as unreadpms FROM privatemessages WHERE receipt='1' AND folder!='3' AND status='0' AND fromid=?",
        [$CURUSER['id']]
    );
    $postcount = $unread_count_query ? (int)$db->fetch_field($unread_count_query, 'unreadpms') : 0;
    $page      = $mybb->get_input('unread_page', MyBB::INPUT_INT) ?: 1;
    $pages     = max(1, (int)ceil($postcount / $perpage));
    $page      = max(1, min($page, $pages));
    $start     = ($page - 1) * $perpage;
    $unread_multipage = multipage($postcount, $perpage, $page, 'private.php?action=tracking&amp;unread_page={page}');

    $unreadmessages = '';
    $query = $db->sql_query_prepared(
        'SELECT pm.pmid, pm.subject, pm.toid, pm.dateline, u.username AS tousername, u.avatar, u.avatardimensions
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
        $um_useravatar = format_avatar($um['avatar'], $um['avatardimensions']);
        $um_ava_img = str_starts_with($um_useravatar['image'], '<')
            ? $noAvatarSvg
            : '<img class="user-avatar" src="' . $um_useravatar['image'] . '" alt="" ' . $um_useravatar['width_height'] . ' />';
        $unreadmessages .= '<div class="st-frow">'
            . '<div class="col-auto">' . $um_ava_img . '</div>'
            . '<div class="st-frow__body">'
            .   '<div class="st-frow__top"><a class="st-frow__title" href="private.php?action=read&amp;pmid=' . $um['pmid'] . '">' . $um['subject'] . '</a></div>'
            .   '<div class="st-frow__desc">' . $um['profilelink'] . '</div>'
            . '</div>'
            . '<div class="st-frow__last">'
            .   '<span class="st-badge st-soft-warning"><i class="fa-regular fa-clock"></i>' . $senddate . '</span>'
            .   '<input type="checkbox" class="form-check-input ms-2" name="unreadcheck[' . $um['pmid'] . ']" value="1" />'
            . '</div>'
            . '</div>';
    }

    $stoptrackingunread = !empty($unreadmessages)
        ? '<div class="st-submitbar justify-content-center">'
        .   '<button type="submit" class="btn btn-outline-primary st-pill" name="stoptrackingunread" value="' . $lang->private['stop_tracking'] . '"><i class="fa-solid fa-xmark me-1"></i>' . $lang->private['stop_tracking'] . '</button>'
        .   '<button type="submit" class="btn btn-outline-danger st-pill" name="cancel" value="' . $lang->private['delete'] . '"><i class="fa-solid fa-trash me-1"></i>' . $lang->private['delete'] . '</button>'
        . '</div>'
        : '';

    if (!$unreadmessages) {
        $unreadmessages = '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-regular fa-envelope"></i></div>' . $lang->private['no_unreadmessages'] . '</div>';
    }

    $plugins->run_hooks('private_tracking_end');
    stdhead($lang->private['pm_tracking']);
    build_breadcrumb();

    echo ucp_open('fa-envelope-open-text', 'info', $lang->private['pm_tracking'])

        . '<form action="private.php" method="post">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '<input type="hidden" name="action" value="do_tracking" />'
        . '<div class="st-card mb-3 overflow-hidden">'
        .   '<div class="st-card__head">'
        .     '<span class="st-ico st-soft-info"><i class="fa-solid fa-envelope-open"></i></span>'
        .     '<span>' . $lang->private['message_title'] . ' &mdash; ' . $lang->private['sentto'] . '</span>'
        .     '<span class="st-badge st-soft-info ms-auto">' . $lang->private['dateread'] . '</span>'
        .   '</div>'
        .   '<div class="st-card__body">' . $readmessages . '</div>'
        .   $stoptrackingread
        . '</div>'
        . '</form>'

        . $read_multipage

        . '<form action="private.php" method="post" class="mt-3">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '<input type="hidden" name="action" value="do_tracking" />'
        . '<div class="st-card mb-3 overflow-hidden">'
        .   '<div class="st-card__head">'
        .     '<span class="st-ico st-soft-warning"><i class="fa-solid fa-envelope"></i></span>'
        .     '<span>' . $lang->private['message_title'] . ' &mdash; ' . $lang->private['sentto'] . '</span>'
        .     '<span class="st-badge st-soft-warning ms-auto">' . $lang->private['datesent'] . '</span>'
        .   '</div>'
        .   '<div class="st-card__body">' . $unreadmessages . '</div>'
        .   $stoptrackingunread
        . '</div>'
        . '</form>'

        . ucp_close();

    stdfoot();
    exit;
}

// ── Do tracking ──────────────────────────────────────────────────────────────
if ($action === 'do_tracking' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('private_do_tracking_start');

    if (!empty($mybb->input['stoptracking'])) {
        foreach ($mybb->get_input('readcheck', MyBB::INPUT_ARRAY) as $key => $val) {
            $db->sql_query_prepared("UPDATE privatemessages SET receipt = 0 WHERE pmid = ? AND fromid = ?", [(int)$key, $CURUSER['id']]);
        }
        $plugins->run_hooks('private_do_tracking_end');
        redirect('private.php?action=tracking', $lang->private['redirect_pmstrackingstopped']);

    } elseif (!empty($mybb->input['stoptrackingunread'])) {
        foreach ($mybb->get_input('unreadcheck', MyBB::INPUT_ARRAY) as $key => $val) {
            $db->sql_query_prepared("UPDATE privatemessages SET receipt = 0 WHERE pmid = ? AND fromid = ?", [(int)$key, $CURUSER['id']]);
        }
        $plugins->run_hooks('private_do_tracking_end');
        redirect('private.php?action=tracking', $lang->private['redirect_pmstrackingstopped']);

    } elseif (!empty($mybb->input['cancel'])) {
        $unreadcheck = $mybb->get_input('unreadcheck', MyBB::INPUT_ARRAY);
        if (!empty($unreadcheck)) {
            $pmid_list = array_map('intval', array_keys($unreadcheck));
            $ph      = implode(',', array_fill(0, count($pmid_list), '?'));
            $pmuids  = [];
            $query   = $db->sql_query_prepared("SELECT uid FROM privatemessages WHERE pmid IN ({$ph}) AND fromid = ?", [...$pmid_list, $CURUSER['id']]);
            while ($query && ($pm = $db->fetch_array($query))) $pmuids[$pm['uid']] = $pm['uid'];
            $db->sql_query_prepared(
                "DELETE FROM privatemessages WHERE pmid IN ({$ph}) AND receipt='1' AND status='0' AND fromid = ?",
                [...$pmid_list, $CURUSER['id']]
            );
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
    $db->sql_query_prepared("UPDATE privatemessages SET receipt = 0 WHERE receipt='2' AND folder!='3' AND status!='0' AND fromid = ?", [$CURUSER['id']]);
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
                $ph = implode(',', array_fill(0, count($pms), '?'));
                $db->sql_query_prepared("UPDATE privatemessages SET folder = ? WHERE pmid IN ({$ph}) AND uid = ?", [$fid_move, ...$pms, $CURUSER['id']]);
                update_pm_count();
            } else {
                error($lang->private['error_invalidmovefid']);
            }
        }
        redirect(!empty($mybb->input['fromfid'])
            ? 'private.php?fid=' . $mybb->get_input('fromfid', MyBB::INPUT_INT)
            : 'private.php', $lang->private['redirect_pmsmoved']);

    } elseif (!empty($mybb->input['delete'])) {
        $check = $mybb->get_input('check', MyBB::INPUT_ARRAY);
        $uid   = (int)$CURUSER['id'];

        $pmid_list = array_values(array_unique(array_filter(array_map('intval', array_keys($check)))));
        if (empty($pmid_list)) {
            error($lang->private['err_no_pms_selected'] ?? 'You did not select any messages.');
        }

        $ph = implode(',', array_fill(0, count($pmid_list), '?'));

        $found = [];
        $query = $db->sql_query_prepared(
            "SELECT pmid, folder FROM privatemessages WHERE pmid IN ({$ph}) AND uid = ?",
            [...$pmid_list, $uid]
        );
        while ($query && ($dp = $db->fetch_array($query))) {
            $found[(int)$dp['pmid']] = (int)$dp['folder'];
        }

        if (empty($found)) {
            error($lang->private['err_pms_not_found'] ?? 'The selected messages were not found.');
        }

        $in_trash = array_keys(array_filter($found, fn(int $f): bool => $f === 4));
        $to_trash = array_keys(array_filter($found, fn(int $f): bool => $f !== 4));
        $errors   = [];

        if ($in_trash) {
            $ph_t = implode(',', array_fill(0, count($in_trash), '?'));
            if (!$db->sql_query_prepared("DELETE FROM privatemessages WHERE pmid IN ({$ph_t}) AND uid = ?", [...$in_trash, $uid])) {
                $errors[] = 'DELETE: ' . (string)$db->error();
            }
        }
        if ($to_trash) {
            $ph_m = implode(',', array_fill(0, count($to_trash), '?'));
            if (!$db->sql_query_prepared(
                "UPDATE privatemessages SET folder = 4, deletetime = ? WHERE pmid IN ({$ph_m}) AND uid = ?",
                [TIMENOW, ...$to_trash, $uid]
            )) {
                $errors[] = 'UPDATE: ' . (string)$db->error();
            }
        }

        if ($errors) {
            write_log('private.php: PM delete failed for uid ' . $uid . ' — ' . implode(' | ', $errors));
            error(($lang->private['err_pms_delete_failed'] ?? 'Could not delete the selected messages. Database error:')
                . '<br><code>' . htmlspecialchars_uni(implode(' | ', $errors)) . '</code>');
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
    $query    = $db->sql_query_prepared("SELECT * FROM privatemessages WHERE pmid = ? AND uid = ? AND folder='4'", [$pmid_del, $CURUSER['id']]);

    if ($query && $db->num_rows($query) == 1) {
        $db->sql_query_prepared("DELETE FROM privatemessages WHERE pmid = ?", [$pmid_del]);
    } else {
        $db->sql_query_prepared(
            "UPDATE privatemessages SET folder = ?, deletetime = ? WHERE pmid = ? AND uid = ?",
            [4, TIMENOW, $pmid_del, $CURUSER['id']]
        );
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
    $count_query = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total FROM privatemessages WHERE uid = ? AND folder = ?{$selective}",
        [$CURUSER['id'], $folder]
    );
    $pmscount  = $count_query ? (int)$db->fetch_field($count_query, 'total') : 0;

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

    if ($folder == 2 || $folder == 3) {
        $u = ($sortfield === 'username') ? 'u.' : 'pm.';
        $get_users = [];
        $users_query = $db->sql_query_prepared("
            SELECT pm.recipients FROM privatemessages pm
            LEFT JOIN users u ON (u.id = pm.toid)
            WHERE pm.folder = ? AND pm.uid = ?
            ORDER BY {$u}{$sortfield} {$sortordernow}
            LIMIT ?, ?
        ", [$folder, $CURUSER['id'], $start, $perpage]);
        while ($users_query && ($row = $db->fetch_array($users_query))) {
            $recipients = my_unserialize($row['recipients']);
            foreach (['to', 'bcc'] as $type) {
                if (isset($recipients[$type]) && is_array($recipients[$type]) && count($recipients[$type])) {
                    $get_users = array_merge($get_users, $recipients[$type]);
                }
            }
        }
        if ($get_users_list = array_values(array_unique($get_users))) {
            $ph = implode(',', array_fill(0, count($get_users_list), '?'));
            $users_query = $db->sql_query_prepared("SELECT id, username, usergroup FROM users WHERE id IN ({$ph})", $get_users_list);
            while ($users_query && ($user = $db->fetch_array($users_query))) $cached_users[$user['id']] = $user;
        }
    }

    if ($folder == 2 || $folder == 3) {
        $pm_prefix = ($sortfield === 'username') ? 'tu.' : 'pm.';
    } else {
        if ($fid == 1) $selective = " AND pm.status='0'";
        $pm_prefix = ($sortfield === 'username') ? 'fu.' : 'pm.';
    }

    $query = $db->sql_query_prepared("
        SELECT pm.*, fu.username AS fromusername, tu.username as tousername, fu.avatar, fu.avatardimensions
        FROM privatemessages pm
        LEFT JOIN users fu ON (fu.id = pm.fromid)
        LEFT JOIN users tu ON (tu.id = pm.toid)
        WHERE pm.folder = ? AND pm.uid = ?{$selective}
        ORDER BY {$pm_prefix}{$sortfield} {$sortordernow}
        LIMIT ?, ?
    ", [$folder, $CURUSER['id'], $start, $perpage]);

    if ($query && $db->num_rows($query) > 0) {
        while ($message = $db->fetch_array($query)) {
            $msgstatus = $msgalt = $fa_icon_html = $badge_class = $popover_title = $popover_content = '';

            match ((int)$message['status']) {
                0 => [
                    $msgstatus     = 'new_pm',
                    $msgalt        = $lang->private['new_pm'],
                    $fa_icon_html  = '<i class="fa-solid fa-envelope fa-fw"></i>',
                    $badge_class   = 'st-soft-danger',
                    $popover_title = $pl('new_pm', 'New Message'),
                    $popover_content = $pl('pm_status_new_desc', 'This message has not been read yet'),
                ],
                1 => [
                    $msgstatus     = 'old_pm',
                    $msgalt        = $lang->private['old_pm'],
                    $fa_icon_html  = '<i class="fa-regular fa-envelope-open fa-fw"></i>',
                    $badge_class   = 'st-soft-muted',
                    $popover_title = $pl('old_pm', 'Read Message'),
                    $popover_content = $pl('pm_status_read_desc', 'This message has been read'),
                ],
                3 => [
                    $msgstatus     = 're_pm',
                    $msgalt        = $lang->private['reply_pm'],
                    $fa_icon_html  = '<i class="fa-solid fa-reply fa-fw"></i>',
                    $badge_class   = 'st-soft-info',
                    $popover_title = $pl('reply_pm', 'Reply to Message'),
                    $popover_content = $pl('pm_status_reply_desc', 'This is a reply to a previous message'),
                ],
                4 => [
                    $msgstatus     = 'fw_pm',
                    $msgalt        = $lang->private['fwd_pm'],
                    $fa_icon_html  = '<i class="fa-solid fa-share fa-fw"></i>',
                    $badge_class   = 'st-soft-success',
                    $popover_title = $pl('fwd_pm', 'Forwarded Message'),
                    $popover_content = $pl('pm_status_fwd_desc', 'This message has been forwarded'),
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
                        $uname = format_name((string)($user['username'] ?? ''), (int)($user['usergroup'] ?? 1)) ?: ($lang->private['pm_na'] ?? 'N/A');
                        $to_users .= '<div class="popup_item_container"><a href="' . get_profile_link($uid) . '" class="popup_item">' . $uname . '</a></div>';
                    }
                    if (isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc'])) {
                        $bcc_users = '<div class="tcat"><strong>' . $lang->private['bcc'] . '</strong></div>';
                        foreach ($recipients['bcc'] as $uid) {
                            if (!isset($cached_users[$uid])) continue;
                            $user = $cached_users[$uid];
                            $user['username'] = htmlspecialchars_uni($user['username']);
                            $uname = format_name((string)($user['username'] ?? ''), (int)($user['usergroup'] ?? 1)) ?: ($lang->private['pm_na'] ?? 'N/A');
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
                if (!$tofromusername) { $tofromuid = 0; $tofromusername = ($lang->private['pm_na'] ?? 'N/A'); }
            }

            $tofromusername = build_profile_link($tofromusername, $tofromuid);

            $useravatar = format_avatar($message['avatar'], $message['avatardimensions']);
            $ava_img = str_starts_with($useravatar['image'], '<')
                ? $noAvatarSvg
                : '<img class="user-avatar" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . ' />';

            $denyreceipt = ($usergroups['candenypmreceipts'] == 1 && $message['receipt'] == '1' && $message['folder'] != '3' && $message['folder'] != 2)
                ? '<span class="st-meta ms-2"><a href="private.php?action=read&amp;pmid=' . $message['pmid'] . '&amp;denyreceipt=1">' . $lang->private['deny_receipt'] . '</a></span>'
                : '';

            if (!trim($message['subject'])) $message['subject'] = ($lang->private['pm_no_subject'] ?? '[no subject]');
            $message['subject'] = htmlspecialchars_uni($parser->parse_badwords($message['subject']));
            $senddate = $message['folder'] != '3' ? my_datee('relative', $message['dateline']) : ($lang->private['not_sent'] ?? 'N/A (not sent)');

            $plugins->run_hooks('private_message');

            $messagelist .= '<div class="st-frow message-card">'
                . '<input type="checkbox" form="pmForm" class="form-check-input message-select-toggle" name="check[' . $message['pmid'] . ']" value="1" id="select-' . $message['pmid'] . '" data-pmid="' . $message['pmid'] . '">'
                . '<div class="st-frow__body">'
                .   '<div class="st-frow__top">'
                .     '<a href="private.php?action=read&amp;pmid=' . $message['pmid'] . '" class="st-frow__title ' . $msgstatus . '">' . $message['subject'] . '</a>'
                .     $denyreceipt
                .   '</div>'
                .   '<div class="st-frow__desc"><i class="fa-solid fa-user me-1"></i>' . $tofromusername . '</div>'
                . '</div>'
                . '<div class="st-frow__last">'
                .   '<span class="st-badge ' . $badge_class . '" title="' . $msgalt . '" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-title="' . $popover_title . '" data-bs-content="' . $popover_content . '" data-bs-container="body">' . $fa_icon_html . '</span>'
                .   '<span class="st-meta text-nowrap"><i class="fa-regular fa-clock me-1"></i>' . $senddate . '</span>'
                . '</div>'
                . '</div>';
        }
    } else {
        $messagelist .= '<div class="st-empty">'
            . '<div class="st-ico st-soft-muted"><i class="fa-regular fa-envelope"></i></div>'
            . '<strong>' . $lang->private['nomessages'] . '</strong>'
            . '<div class="st-meta mt-1"><i class="fa-regular fa-envelope-open-text me-1"></i> ' . $pl('pm_mailbox_empty', 'Your mailbox is empty') . '</div>'
            . '</div>';
    }

    $pmspacebar = '';
    $limitwarning = '';
    if ($usergroups['pmquota'] != 0) {
        $quota_query = $db->sql_query_prepared("SELECT COUNT(*) AS total FROM privatemessages WHERE uid = ?", [$CURUSER['id']]);
        $pmscount_arr = $quota_query ? $db->fetch_array($quota_query) : null;
        $total = (int)($pmscount_arr['total'] ?? 0);
        $spaceused    = $total == 0 ? 0 : min(100, $total / $usergroups['pmquota'] * 100);
        $pmspacebar   = '<div class="progress mt-3" style="height:24px"><div class="progress-bar" role="progressbar" style="width:' . $spaceused . '%"></div></div>'
            . '<div class="st-meta mt-1">' . round($spaceused, 0) . '% ' . $lang->private['pmspaceused'] . '</div>';
        $limitwarning = $total >= $usergroups['pmquota']
            ? '<div class="st-notice st-soft-danger mb-3"><i class="fa-solid fa-triangle-exclamation"></i><div>' . $lang->private['reached_warning'] . '</div></div>'
            : '';
    }

    $plugins->run_hooks('private_end');

    stdhead('title');
    build_breadcrumb();
    echo '<script src="' . $BASEURL . '/scripts/popover.js"></script>';

    echo ucp_open('fa-envelope', 'primary', $foldernames[$fid] ?: $lang->private['nav_pms'], $foldername)
        . $limitwarning
        . '<form action="private.php" method="post" name="pmForm" id="pmForm">'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '<input type="hidden" name="action" value="do_stuff" />'

        . '<div class="st-card mb-3 overflow-hidden">'
        .   '<div class="st-card__head">'
        .     '<span class="st-ico st-soft-primary"><i class="fa-solid fa-inbox"></i></span>'
        .     '<a href="private.php?fid=' . $fid . '&amp;sortby=subject&amp;order=asc" class="text-decoration-none">' . $lang->private['message_title'] . '</a>'
        .     '<span class="st-sep"></span>'
        .     '<a href="private.php?fid=' . $fid . '&amp;sortby=username&amp;order=asc" class="text-decoration-none">' . $sender . '</a>'
        .     '<a href="private.php?fid=' . $fid . '&amp;sortby=dateline&amp;order=desc" class="st-meta ms-auto text-decoration-none">' . $lang->private['date_sent'] . '</a>'
        .     '<label class="d-flex align-items-center gap-2 mb-0 ms-3 st-meta" for="selectAllSwitch">' . $lang->private['check_all']
        .       '<input class="form-check-input select-all-switch" type="checkbox" id="selectAllSwitch"></label>'
        .   '</div>'
        .   '<div>' . $messagelist . '</div>'
        .   '<div class="st-optbar justify-content-between">'
        .     '<div class="d-flex align-items-center gap-2">'
        .       '<button type="submit" form="pmForm" class="btn btn-primary st-pill" name="moveto" value="' . $lang->private['move_to'] . '">' . $lang->private['move_to'] . ' &nbsp;<i class="fa-solid fa-arrow-right"></i></button>'
        .       $folderoplist
        .     '</div>'
        .     '<button type="submit" form="pmForm" class="btn btn-outline-danger st-pill" name="delete" value="' . $lang->private['delete'] . '"><i class="fa-solid fa-xmark me-1"></i>' . $lang->private['delete'] . '</button>'
        .   '</div>'
        . '</div>'
        . '</form>'
        . ($multipage ? '<div class="st-pager d-flex justify-content-center mt-3">' . $multipage . '</div>' : '')
        . $pmspacebar
        . ucp_close();

    echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    const toggles = document.querySelectorAll(".message-select-toggle");
    const selectAll = document.getElementById("selectAllSwitch");
    function updateSelectAll() {
        if (!selectAll) return;
        const checked = document.querySelectorAll(".message-select-toggle:checked").length;
        selectAll.checked = toggles.length > 0 && checked === toggles.length;
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
            updateSelectAll();
        });
    }
});
</script>';

    stdfoot();
    exit;
}