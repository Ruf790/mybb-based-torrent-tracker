<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['cheat_attempts'] = array (
	// ── Page ──────────────────────────────────────────────
	'page_title'          => 'Cheat Attempts',
	'pane_title'          => 'Cheat Attempts',

	// ── Stats tiles / filters ─────────────────────────────
	'lbl_total_records'   => 'Total Records',
	'lbl_high_severity'   => 'High Severity',
	'lbl_medium_severity' => 'Medium Severity',
	'btn_filter_high'     => 'High only',
	'btn_filter_all'      => 'All',

	// ── Table columns ─────────────────────────────────────
	'th_user'             => 'User',
	'th_date'             => 'Date',
	'th_torrent'          => 'Torrent',
	'th_reason'           => 'Reason',
	'th_detail'           => 'Detail',
	'th_severity'         => 'Severity',
	'th_ip'               => 'IP',
	'th_ban'              => 'Ban',
	'th_warn'             => 'Warn',
	'th_delete'           => 'Del',

	// ── Severity options ──────────────────────────────────
	'opt_severity_high'   => 'High',
	'opt_severity_medium' => 'Medium',
	'opt_severity_low'    => 'Low',

	// ── Buttons ───────────────────────────────────────────
	'btn_all_ban'         => 'All Ban',
	'btn_all_warn'        => 'All Warn',
	'btn_all_delete'      => 'All Delete',
	'btn_apply'           => 'Apply',
	'btn_autoban'         => 'Auto-Ban (5+/h)',

	// ── Flash messages ────────────────────────────────────
	'flash_banned'        => 'Users have been banned',
	'flash_warned'        => 'Users have been warned',
	'flash_deleted'       => 'Records deleted',
	'flash_autoban'       => 'Auto-ban complete: {1} user(s) banned',

	// ── Warning PM ────────────────────────────────────────
	'pm_warn_subject'     => 'Warning: Suspicious Activity Detected',
	'pm_warn_message'     => 'Your account has been flagged for suspicious upload activity. Please contact staff if you believe this is an error.',

	// ── Reason labels ─────────────────────────────────────
	'reason_fake_completed_event'         => '🎭 Fake Complete',
	'reason_completed_without_download'   => '📥 Complete Without Download',
	'reason_fake_seeding'                 => '🌱 Fake Seeding',
	'reason_peer_id_changed'              => '🔄 Client Changed',
	'reason_suspicious_peer_id'           => '🕵️ Suspicious Client',
	'reason_negative_values'              => '➖ Negative Values',
	'reason_completed_while_seeding'      => '⚡ Already Seeding',
	'reason_speed_anomaly'                => '🚀 Impossible Speed',
	'reason_port_changed'                 => '🔌 Port Changed',
	'reason_announce_spam'                => '📢 Announce Spam',
	'reason_banned_cheat_client'          => '🚫 Banned Client',
	'reason_instant_stop_after_complete'  => '⏱️ Instant Stop',
	'reason_impossible_ratio_new_torrent' => '📊 Impossible Ratio',
	'reason_extreme_ratio'                => '📈 Extreme Ratio',
	'reason_empty_user_agent'             => '👻 No User Agent',
	'reason_seed_with_left'               => '🌱 Seeding Incomplete',
	'reason_fake_completed_no_data'       => '🎭 Fake Complete (No Data)',
	'reason_multi_ip_same_peer_id'        => '🌐 Multiple IPs',
	'reason_too_many_torrents_single_ip'  => '🌊 IP Flood',

	// ── Detail descriptions (output as-is) ────────────────
	'detail_announce_spam'                => 'Announced again after only {1} seconds (minimum: 30s)',
	'detail_speed_anomaly'                => 'Average upload speed: {1} MB/s — physically impossible',
	'detail_negative_values'              => 'Sent negative upload/download values — possible exploit attempt',
	'detail_peer_id_changed'              => 'Client changed from {1} to {2}',
	'detail_fake_completed_event'         => 'Claimed download complete but still has {1} remaining',
	'detail_fake_completed_no_data'       => 'Sent &quot;completed&quot; event but downloaded nothing this session',
	'detail_fake_seeding'                 => 'Reporting as seeder but file is incomplete ({1} remaining)',
	'detail_multi_ip_same_peer_id'        => 'Same client ID seen from {1} different IP addresses',
	'detail_extreme_ratio'                => 'Suspiciously high ratio: {1}:1',
	'detail_instant_stop_after_complete'  => 'Stopped seeding {1} seconds after completing download',
	'detail_banned_cheat_client'          => 'Using a known ratio-cheating client',
	'unit_mb'                             => 'MB',

	// ── JS strings ────────────────────────────────────────
	'js_autoban_confirm'  => 'Auto-ban users with 5+ high violations in last hour?',
);
?>
