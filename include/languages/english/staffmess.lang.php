<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['staffmess'] = array (
    // ── Page ──
    'page_title'          => 'Mass Message to all Staff members and/or Users',
    'sec_title'           => 'Mass Message',
    'sec_subtitle'        => 'Send a private message to every member of the selected usergroups.',
    'badge_section'       => 'Communication',

    // ── KPI ──
    'kpi_users'           => 'Total users',
    'kpi_staff'           => 'Staff members',
    'kpi_groups'          => 'Usergroups',
    'kpi_recipients'      => 'Selected recipients',

    // ── Sections ──
    'sec_groups'          => 'Recipients',
    'sec_sender'          => 'Sender',
    'sec_message'         => 'Message',

    // ── Labels ──
    'lbl_check_all'       => 'Check All',
    'lbl_subject'         => 'Subject',
    'lbl_message'         => 'Message',
    'lbl_char_count'      => '{1} characters',
    'lbl_close'           => 'Close',
    'lbl_recipients'      => 'Recipients:',

    // ── Placeholders ──
    'ph_subject'          => 'Short and clear subject line',

    // ── Hints / tooltips ──
    'tip_group_users'     => '{1} user(s) in this group',
    'hint_groups'         => 'Every user in the selected groups receives the message as a PM.',
    'hint_sender'         => 'Messages from the system have no sender to reply to.',
    'hint_bbcode'         => 'BBCode and smilies are supported.',

    // ── Options ──
    'opt_sender_system'   => 'Automatic Message By System',

    // ── Buttons ──
    'btn_send'            => 'Send Message',

    // ── Flash / errors ──
    'flash_csrf_failed'   => 'Security check failed. Please refresh the page and try again.',
    'flash_fields_blank'  => 'Don\'t leave any fields blank.',
    'flash_sent'          => 'Total {1} message(s) has been sent.',

    // ── JS ──
    'js_char_count'       => '{1} characters',
    'js_confirm_title'    => 'Send mass message?',
    'js_confirm_text'     => 'The message will be sent as a PM to {1} user(s).',
    'js_confirm_yes'      => 'Send',
    'js_confirm_no'       => 'Cancel',
    'js_no_groups_title'  => 'No groups selected',
    'js_no_groups_text'   => 'Select at least one usergroup to send the message to.',
    'js_ok'               => 'OK',
);
?>
