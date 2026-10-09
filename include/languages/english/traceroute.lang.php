<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['traceroute'] = array (
	// ── Page ──
	'page_title'       => 'Traceroute',

	// ── Header ──
	'sec_title'        => 'Network Traceroute Tool',
	'sec_subtitle'     => 'Trace the network path to a host hop by hop, with live output.',
	'badge_your_ip'    => 'Your IP: {1}',

	// ── Form ──
	'lbl_host'         => 'IP / Host',
	'lbl_unknown_ip'   => 'Unknown',
	'ph_host'          => 'e.g. 8.8.8.8 or example.com',
	'hint_host'        => 'Domain name or IPv4 address, up to 30 hops.',
	'btn_start'        => 'Start Traceroute',

	// ── Output ──
	'sec_output'       => 'Output',
	'empty_text'       => 'Enter a host and start the traceroute — the output will appear here.',

	// ── Errors (AJAX JSON) ──
	'err_invalid_host' => 'Invalid host',

	// ── JS ──
	'js_status_ready'   => 'Ready',
	'js_status_running' => 'Running…',
	'js_status_done'    => 'Completed',
);
?>
