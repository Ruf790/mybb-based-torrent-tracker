<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');


$language['invites'] = array
(

// ── Page header ──
'page_title'          => "My Invites",
'pane_subtitle'       => "Invite your friends to join {1}",
'lbl_invites_left'    => "invites left",

// ── KPI ──
'lbl_kpi_total'       => "Total Sent",
'lbl_kpi_pending'     => "Pending",
'lbl_kpi_used'        => "Accepted",

// ── Create form ──
'sec_create'          => "Create New Invite",
'lbl_email'           => "Email (optional)",
'ph_email'            => "friend@example.com",
'hint_email'          => "If provided, the invite will be sent by e-mail.",
'lbl_note'            => "Note (optional)",
'ph_note'             => "e.g. for John from work",
'btn_generate'        => "Generate Invite",
'hint_no_invites'     => "You have no invites left. Invites can be earned through activity or bought with bonus points.",

// ── History ──
'sec_history'         => "Invite History",
'hint_empty'          => "No invites yet. Create your first invite above!",
'lbl_col_code'        => "Code",
'lbl_col_email'       => "Email",
'lbl_col_note'        => "Note",
'lbl_col_invitee'     => "Invited User",
'lbl_col_status'      => "Status",
'lbl_col_expires'     => "Expires",
'lbl_col_created'     => "Created",
'tip_copy'            => "Click to copy the invite link",
'tip_revoke'          => "Revoke invite",
'fmt_date'            => "M j, Y",

// ── Status badges ──
'opt_status_pending'  => "Pending",
'opt_status_used'     => "Used",
'opt_status_expired'  => "Expired",
'opt_status_revoked'  => "Revoked",

// ── Flash / errors ──
'flash_created'       => "Invite created successfully!",
'flash_revoked'       => "Invite revoked. Your invite has been returned.",
'err_email'           => "Invalid email address.",
'err_no_invites'      => "You have no invites left.",
'err_create_failed'   => "Failed to create the invite. You may have no invites remaining.",
'err_revoke_failed'   => "Failed to revoke the invite.",

// ── E-mail to the invited person ──
'email_subject'       => "You've been invited to {1}",
'email_body'          => "Hello!

{1} has invited you to join {2}.

Register here:
{3}

This invite expires in {4} days.

— {2} Team",

// ── JS ──
'js_revoke_confirm'   => "Revoke this invite?",
'js_copied'           => "Copied!",
'js_copied_toast'     => "Invite link copied to clipboard!",

);
