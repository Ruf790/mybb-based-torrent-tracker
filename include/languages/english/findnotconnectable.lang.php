<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// admin/findnotconnectable.php
$language['findnotconnectable'] = array
(
	// ── Page / panes ──
	'pane_head'          => 'Unconnectable Peers',
	'pane_log'           => 'Unconnectable Peers Mass PM Log',
	'pane_list'          => 'List Unconnectable Users',
	'sec_subtitle'       => 'Users whose clients cannot accept incoming connections (firewalled or behind NAT). Find them and send a port-forwarding hint by PM.',

	// ── KPI tiles ──
	'kpi_users'          => 'Unconnectable users',
	'kpi_peers'          => 'Unconnectable peers',
	'kpi_seeders'        => 'Of them seeding',
	'kpi_pms'            => 'Mass PMs sent',
	'kpi_last_pm'        => 'Last: {1}',
	'kpi_never'          => 'Never sent yet',

	// ── Navigation / buttons ──
	'btn_home'           => 'PM Log',
	'btn_pm'             => 'Send PM',
	'btn_showlist'       => 'Show List',
	'btn_pm_selected'    => 'Send Message to Selected Users',
	'btn_send'           => 'Send',
	'btn_reset'          => 'Reset',
	'btn_delete'         => 'Delete',

	// ── Table columns ──
	'col_sender'         => 'Sender',
	'col_date'           => 'Date',
	'col_action'         => 'Action',
	'col_username'       => 'Username',
	'col_torrent'        => 'Torrent Name',
	'col_ip'             => 'IP / Port',
	'col_client'         => 'Client',
	'col_seeder'         => 'Seeder',

	// ── Labels ──
	'lbl_yes'            => 'Yes',
	'lbl_no'             => 'No',
	'lbl_select_all'     => 'Select all',
	'lbl_select_user'    => 'Select user',
	'lbl_pm_form'        => 'Send mass message to all non-connectable users',
	'lbl_message'        => 'Message text',
	'lbl_close'          => 'Close',

	// ── Hints / placeholders ──
	'hint_total'         => 'Total {1} unique users that are not connectable',
	'hint_nolog'         => 'There is no PM log to show!',
	'ph_msg'             => 'Enter the message text…',
	'hint_nolog_text'    => 'Mass PMs you send to unconnectable users will appear here.',
	'hint_recipients'    => 'Recipients: {1}',
	'hint_pm_form'       => 'The PM goes to every user who currently has at least one unconnectable peer.',
	'hint_selected_pm'   => 'Selected users will receive the standard port-forwarding warning.',
	'txt_page_of'        => 'Page {1} of {2}',

	// ── Errors ──
	'err_empty_msg'      => 'Please enter a message to send!',
	'err_no_peers'       => 'There are no unconnectable peers!',
	'err_no_users'       => 'Please select at least one user to send a message to!',

	// ── Flash ──
	'flash_pm_sent'      => 'PM sent successfully.',

	// ── PM sent to users ──
	'pm_subject'         => 'Warning: your client is not connectable',
	'pm_body'            => 'Hi,

Our tracker detected that you are firewalled or behind NAT and cannot accept incoming connections.

This means other peers cannot connect to you directly, which reduces your download/upload speed.
If two firewalled peers are matched together, they cannot connect at all.

To fix this:
- Open the port your torrent client uses in your firewall/router
- Configure port forwarding on your router (see: https://portforward.com)

If you need help, post in our forum or contact staff.

Thank you
',

	// ── JS ──
	'js_confirm_delete'  => 'Delete this log entry?',
	'js_confirm_title'   => 'Are you sure?',
	'js_confirm_selected' => 'Send the warning PM to {1} selected users?',
	'js_confirm_mass'    => 'Send this PM to all {1} unconnectable users?',
	'js_selected'        => 'Selected: {1}',
	'js_btn_yes'         => 'Yes',
	'js_btn_cancel'      => 'Cancel',
);
?>
