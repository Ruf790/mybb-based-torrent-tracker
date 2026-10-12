<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['rules'] = array (
	// ── Meta ── (which DB columns to use: ru => title_ru / text_ru)
	'lang_code'           => 'en',

	// ── Header ──
	'pane_title'          => 'The Book of Rules',
	'pane_subtitle'       => 'Terms every member agreed to on signup. Read it. Know it. Break it and this is exactly what gets cited.',
	'lbl_viewing_as'      => 'Viewing as {1}',

	// ── KPI tiles ──
	'kpi_rules_for_you'   => 'Rules for you',
	'kpi_for_all'         => 'For all users',
	'kpi_group_specific'  => 'Group-specific',
	'kpi_reading_time'    => 'Reading time',
	'kpi_minutes'         => '~{1} min',

	// ── Usergroups ──
	'grp_1'               => 'Guest',
	'grp_2'               => 'User',
	'grp_3'               => 'Power User',
	'grp_4'               => 'VIP',
	'grp_5'               => 'Uploader',
	'grp_6'               => 'Moderator',
	'grp_7'               => 'Administrator',
	'grp_8'               => 'SysOp',
	'grp_9'               => 'Banned',
	'grp_unknown'         => 'Group #{1}',
	'grp_all_users'       => 'All users',

	// ── Search card ──
	'ph_search'           => 'Search rules…  (press / )',
	'aria_search'         => 'Search rules',
	'btn_expand_all'      => 'Expand all',
	'btn_collapse_all'    => 'Collapse all',
	'btn_print'           => 'Print',
	'lbl_showing'         => 'Showing {1} of {2}',
	'lbl_search_chip'     => 'Search: {1}',
	'tip_remove_filter'   => 'Remove filter',

	// ── Contents ──
	'sec_contents'        => 'Contents',
	'aria_contents'       => 'Rules contents',

	// ── Rule card ──
	'lbl_untitled'        => 'Untitled Rule',
	'lbl_rule_num'        => 'Rule {1}',
	'lbl_min_read'        => '~{1} min read',
	'tip_copy_link'       => 'Copy link to this rule',
	'lbl_applies_to'      => 'Applies to:',
	'lbl_applies_to_you'  => 'Applies to you',

	// ── No match ──
	'sec_no_match'        => 'Nothing Found',
	'msg_no_match'        => 'No rules match your search. Try another word.',
	'btn_reset_search'    => 'Reset search',
	'aria_back_to_top'    => 'Back to top',

	// ── Empty state ──
	'sec_empty'           => 'No Rules Found',
	'msg_empty'           => 'There are currently no rules applicable to your account.',
	'btn_return_home'     => 'Return to Home',

	// ── Error state ──
	'sec_error'           => 'Unable to Load Rules',
	'msg_error'           => 'We encountered an error while loading the rules. Please try again later.',
	'lbl_error'           => 'Error:',
	'err_contact_admin'   => 'Please contact the administrator',
	'btn_retry'           => 'Retry',
	'btn_go_home'         => 'Go Home',

	// ── JS ──
	'js_rule_collapse'    => 'Collapse rule',
	'js_rule_expand'      => 'Expand rule',
);
?>