<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');


$language['user_awaiting_activation'] = array
(

// ── Page header ──
'pane_title'              => "Unconfirmed User Accounts",
'pane_subtitle'           => "Manage pending registrations. Unconfirmed accounts are cleaned up automatically according to system settings.",
'lbl_manage'              => "Manage Awaiting Activation",

// ── KPI tiles ──
'lbl_kpi_total'           => "Pending accounts",
'lbl_kpi_email'           => "Awaiting e-mail confirmation",
'lbl_kpi_admin'           => "Awaiting staff activation",
'lbl_kpi_oldest'          => "Oldest request",

// ── Toolbar ──
'sec_pending_users'       => "Pending users",
'hint_filter'             => "Filter by name, e-mail or IP…",
'lbl_select_all'          => "Select all",

// ── Table ──
'lbl_col_user'            => "User",
'lbl_col_registered'      => "Registered",
'lbl_col_last_active'     => "Last active",
'lbl_col_ip'              => "IP address",
'lbl_col_status'          => "Status",
'lbl_col_actions'         => "Actions",
'lbl_na'                  => "N/A",
'lbl_no_match'            => "No users match the filter.",
'tip_select_user'         => "Select {1}",
'tip_activate_user'       => "Activate {1}",
'tip_delete_user'         => "Delete {1}",

// ── Status badges ──
'opt_status_email'        => "Awaiting e-mail",
'opt_status_admin'        => "Awaiting administrator activation",

// ── Action bar ──
'lbl_selected'            => "Selected:",
'lbl_activate_users'      => "Activate Users",
'lbl_delete_users'        => "Delete Users",

// ── Empty state ──
'sec_empty_title'         => "No pending users",
'hint_empty'              => "Every account has been confirmed — nothing waiting in the queue.",

// ── Flash messages ──
'flash_csrf_failed'       => "Security check failed. Please try again.",
'flash_no_users_selected' => "You did not select any users.",
'flash_none_pending'      => "None of the selected accounts are pending anymore.",
'flash_users_deleted'     => "The selected user(s) have been deleted.",
'flash_users_activated'   => "The selected user(s) have been activated.",

// ── E-mail to activated user ──
'email_subject'           => "Account Activation at {1}",
'email_body'              => "{1},

The administrator has activated your account on {2}.

To proceed, please go to

{3}

You will be able to log in with the credentials you registered with.

Thank you,
{2} Staff",

// ── PM to activated user ──
'pm_activated_subject' => "Your account has been activated",
'pm_activated_body'    => "Hello!

The staff of {1} have activated your account. All features of the tracker are now available to you: downloading, uploading, comments and the forum.

Start here: [url={2}]{1}[/url]",

// ── JS (SweetAlert2 confirmations) ──
'js_none_title'            => "No users selected",
'js_none_text'             => "Select at least one user first.",
'js_activate_title'        => "Activate {1} user(s)?",
'js_activate_text'         => "They will receive an e-mail and a PM and can log in right away.",
'js_delete_title'          => "Delete {1} user(s)?",
'js_delete_text'           => "Accounts will be removed permanently.",
'js_delete_irreversible'   => "This cannot be undone.",
'js_delete_confirm_plain'  => "Permanently delete {1} user(s)? This cannot be undone.",
'js_delete_checkbox'       => "I understand this is permanent",
'js_delete_validator'      => "Please tick the box to confirm.",
'js_more'                  => "+{1} more",
'js_btn_activate'          => "Activate",
'js_btn_delete'            => "Delete",
'js_btn_cancel'            => "Cancel",

);
