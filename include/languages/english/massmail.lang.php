<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['massmail'] = array (

	// ── Page titles (browser tab) ──
	'title_start'           => 'START',
	'title_send'            => 'SEND',

	// ── Page headers ──
	'head_compose'          => 'Mass mail',
	'head_compose_sub'      => 'Email every confirmed, enabled member of the groups you pick. Mail goes out in batches with a pause between them.',
	'head_sending'          => 'Sending mass mail',
	'head_finished'         => 'Mass mail finished',
	'head_subject'          => '“{1}”',

	// ── KPI tiles ──
	'kpi_eligible'          => 'Eligible members',
	'kpi_selected'          => 'Selected recipients',
	'kpi_batches'           => 'Batches',
	'kpi_eta'               => 'Estimated time',
	'kpi_recipients'        => 'Recipients',
	'kpi_batch'             => 'Batch',
	'kpi_delivered'         => 'Delivered',
	'kpi_failed'            => 'Failed',

	// ── Section titles ──
	'sec_message'           => 'Message',
	'sec_recipients'        => 'Recipients',
	'sec_pace'              => 'Delivery pace',
	'sec_last'              => 'Last mailing',
	'sec_progress'          => 'Progress',
	'sec_batch'             => 'Batch {1} of {2}',

	// ── Field labels ──
	'lbl_subject'           => 'Subject',
	'lbl_body'              => 'Body',
	'lbl_all_groups'        => 'All groups',
	'lbl_batch_size'        => 'Batch size',
	'lbl_pause'             => 'Pause',
	'lbl_started'           => 'Started',
	'lbl_delivered'         => 'Delivered',
	'lbl_failed'            => 'Failed',

	// ── Hints / tooltips ──
	'hint_subject_count'    => '{1} characters',
	'hint_batch_size'       => 'Emails per batch',
	'hint_pause'            => 'Seconds between batches',
	'hint_html'             => 'HTML is allowed. The standard header and footer are added to every email automatically.',
	'hint_processed'        => '{1} of {2} processed',
	'tip_group_count'       => 'Confirmed, enabled members',

	// ── Placeholders ──
	'ph_subject'            => 'What is this email about?',
	'ph_message'            => 'Write your message here. HTML is allowed.',

	// ── Accessibility (aria-label / title) ──
	'aria_view_tabs'        => 'Message view',
	'aria_preview_frame'    => 'Email preview',
	'aria_sent'             => 'Sent',
	'aria_failed'           => 'Failed',

	// ── Tabs ──
	'tab_write'             => 'Write',
	'tab_preview'           => 'Preview',

	// ── Buttons ──
	'btn_clear'             => 'Clear',
	'btn_send'              => 'Send mail',
	'btn_reuse'             => 'Reuse this message',
	'btn_new'               => 'New mailing',
	'btn_pause'             => 'Pause',
	'btn_stop'              => 'Stop mailing',

	// ── Status badges ──
	'status_stopped'        => 'Stopped',
	'status_completed'      => 'Completed',
	'status_unfinished'     => 'Not finished',
	'status_batch_sent'     => '{1} sent',
	'status_batch_failed'   => '{1} failed',

	// ── Bottom bar ──
	'bar_pick_group'        => 'Pick at least one group to see who will get this email.',
	'bar_finished'          => 'All batches processed. {1} delivered, {2} failed.',
	'bar_countdown'         => 'Batch {1} starts in {2} s',

	// ── Empty states / alerts ──
	'empty_groups'          => 'No usergroups found.',
	'empty_batch'           => 'No addresses in this batch.',
	'alert_skipped'         => 'This batch was already sent, so it was skipped. Refreshing the page never sends the same batch twice.',

	// ── Errors ──
	'err_title'             => 'Error',
	'err_csrf'              => 'Security check failed. Please refresh the page and try again.',
	'err_not_writable'      => '{1} doesn\'t exist or isn\'t writable.',
	'err_write'             => 'Cannot write to {1}. Check permissions.',
	'err_empty'             => 'Fill in both the subject and the message.',
	'err_no_groups'         => 'Pick at least one usergroup.',
	'err_no_members'        => 'No confirmed, enabled members in the selected groups.',
	'err_no_mailing'        => 'There is no mailing to continue. Start a new one below.',

	// ── Notices ──
	'notice_was_stopped'    => 'This mailing was stopped. Nothing more will be sent.',
	'notice_finished'       => 'This mailing has already finished.',
	'notice_stopped'        => 'Mailing stopped. Nothing more will be sent.',

	// ── Durations ──
	'dur_h_m'               => '{1} h {2} min',
	'dur_m_s'               => '{1} min {2} s',
	'dur_m'                 => '{1} min',
	'dur_s'                 => '{1} s',

	// ── JS: general ──
	'js_locale'             => 'en',
	'js_cancel'             => 'Cancel',
	'js_dur_h_m'            => '{1} h {2} min',
	'js_dur_m_s'            => '{1} min {2} s',
	'js_dur_m'              => '{1} min',
	'js_dur_s'              => '{1} s',

	// ── JS: plural forms of "batch" (Intl.PluralRules categories) ──
	'js_batches_one'        => 'batch',
	'js_batches_few'        => 'batches',
	'js_batches_many'       => 'batches',
	'js_batches_other'      => 'batches',

	// ── JS: compose form ──
	'js_summary'            => 'Emailing {1} members in {2} {3}, about {4}.',
	'js_summary_no_members' => 'The selected groups have no confirmed, enabled members.',
	'js_summary_pick'       => 'Pick at least one group to see who will get this email.',
	'js_err_subject_title'  => 'Subject is empty',
	'js_err_subject'        => 'Add a subject before sending.',
	'js_err_message_title'  => 'Message is empty',
	'js_err_message'        => 'Write a message before sending.',
	'js_err_recipients_title' => 'No recipients',
	'js_err_no_groups'      => 'Pick at least one usergroup.',
	'js_err_no_members'     => 'The selected groups have no confirmed, enabled members.',

	// ── JS: dialogs ──
	'js_clear_title'        => 'Clear the form?',
	'js_clear_text'         => 'Clear the subject, message and selected groups?',
	'js_clear_body'         => 'The subject, message and selected groups will be cleared.',
	'js_clear_confirm'      => 'Clear',
	'js_send_title'         => 'Send this email?',
	'js_send_text'          => 'Send "{1}" to {2} members?',
	'js_send_body'          => 'Goes to {1} members in {2} {3} (about {4}). Keep this tab open while it runs.',
	'js_send_confirm'       => 'Send mail',
	'js_starting'           => 'Starting…',
	'js_stop_title'         => 'Stop this mailing?',
	'js_stop_text'          => 'Stop the mailing? Batches that were already sent stay sent.',
	'js_stop_body'          => 'Remaining batches will not be sent. Batches that already went out stay sent.',
	'js_stop_confirm'       => 'Stop mailing',

	// ── JS: sending page ──
	'js_pause'              => 'Pause',
	'js_resume'             => 'Resume',
	
	 // ── Mail header / footer (plain text; {1} = site name, {2} = date in GMT; in mail_footer_team {1} = link to the site) ──
	'mail_header'          => 'Message received from {1} on {2} GMT.',
	'mail_footer_greeting' => 'Yours,',
	'mail_footer_team'     => 'The {1} Team.',
	
);
?>
