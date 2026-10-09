<?php

if (!defined('IN_TRACKER'))
    die('Hacking attempt!');

// takereport.php — English. Keep english and russian key sets identical.

$language['takereport'] = array
(

// ── Request errors (JSON "error") ──
'err_method'          => 'Method not allowed',
'err_csrf'            => 'Invalid security token. Please refresh the page and try again.',
'err_type'            => 'Invalid report type',
'err_captcha'         => 'Invalid or expired security code. Please try again.',
'err_rate_limit'      => 'Too many reports submitted recently. Please wait before submitting another.',
'err_submit_failed'   => 'Sorry, there was an error submitting your report. Please try again.',

// ── Validation errors (JSON "errors") ──
'err_reason_required' => 'Reason is required',
'err_item_id'         => 'Invalid item ID',
'err_login'           => 'You must be logged in to submit a report',
'err_user_id'         => 'Invalid user ID',
'err_desc_long'       => 'Description is too long (max {1} characters)',

// ── Success ──
'msg_success'         => 'Report submitted successfully',

// ── Moderator notification PM ──
'pm_mod_subject'      => '📢 New Report #{1}',
'pm_mod_body'         => 'A new report has been submitted:

🔹 Report ID: #{1}
🔹 Type: {2}
🔹 Reported ID: {3}
🔹 Reason: {4}
🔗 Link: {5}

Please review it as soon as possible.',

// ── Report types ──
'opt_type_torrent'    => 'Torrent',
'opt_type_comment'    => 'Comment',
'opt_type_user'       => 'User',
'opt_type_forumpost'  => 'Forum post',
'opt_type_post'       => 'Post',

);
