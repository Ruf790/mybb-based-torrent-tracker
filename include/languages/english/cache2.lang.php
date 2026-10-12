<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['cache2'] = array (
	// ── Page titles & headers ──
	'title_manager'            => 'Cache Manager',
	'title_view'               => 'Cache: {1}',
	'sub_manager'              => 'Stored data caches — view contents or rebuild them from the database',
	'sub_lines'                => '{1} lines',
	'sec_all_caches'           => 'All caches',

	// ── KPI tiles ──
	'kpi_caches'               => 'Caches',
	'kpi_total_size'           => 'Total size',
	'kpi_rebuildable'          => 'Rebuildable',
	'kpi_largest'              => 'Largest',

	// ── Table headers ──
	'th_cache'                 => 'Cache',
	'th_size'                  => 'Size',
	'th_rebuild'               => 'Rebuild',
	'th_actions'               => 'Actions',

	// ── Buttons ──
	'btn_rebuild'              => 'Rebuild',
	'btn_reload'               => 'Reload',
	'btn_rebuild_all'          => 'Rebuild all',
	'btn_back'                 => 'Back',
	'btn_wrap'                 => 'Wrap',
	'btn_copy'                 => 'Copy',

	// ── Tags, tooltips, placeholders ──
	'tag_static'               => 'static',
	'tag_rebuild'              => 'rebuild',
	'tag_reload'               => 'reload',
	'size_live'                => 'live',
	'tip_view'                 => 'View contents',
	'ph_filter'                => 'Filter caches…',
	'ph_highlight'             => 'Highlight text…',

	// ── Empty states & confirmations ──
	'empty_cache'              => 'This cache is empty',
	'no_matches'               => 'No matches',
	'confirm_rebuild_all'      => 'Rebuild all {1} caches now?',

	// ── Flash messages ──
	'flash_no_cache_specified' => 'No cache specified',
	'flash_cache_not_found'    => 'Cache not found',
	'flash_invalid_token'      => 'Invalid security token',
	'flash_no_such_cache'      => 'No such cache',
	'flash_settings_reloaded'  => 'The settings cache has been reloaded',
	'flash_cannot_rebuild'     => 'This cache cannot be rebuilt',
	'flash_cache_rebuilt'      => 'Cache “{1}” has been rebuilt',
	'flash_cache_reloaded'     => 'Cache “{1}” has been reloaded',
	'flash_all_rebuilt'        => '{1} caches have been rebuilt',

	// ── Cache descriptions ──
	'desc_settings'            => 'Board configuration settings',
	'desc_usergroups'          => 'User groups and their permissions',
	'desc_forums'              => 'Forum list and structure',
	'desc_forumpermissions'    => 'Per-forum group permissions',
	'desc_moderators'          => 'Forum moderators',
	'desc_attachtypes'         => 'Allowed attachment types',
	'desc_smilies'             => 'Smilies list',
	'desc_badwords'            => 'Word filters',
	'desc_bannedips'           => 'Banned IP addresses',
	'desc_bannedemails'        => 'Banned email addresses',
	'desc_birthdays'           => 'Upcoming birthdays',
	'desc_stats'               => 'Board statistics',
	'desc_statistics'          => 'Extended statistics',
	'desc_plugins'             => 'Active plugins',
	'desc_mycode'              => 'Custom MyCodes',
	'desc_posticons'           => 'Post icons',
	'desc_profilefields'       => 'Custom profile fields',
	'desc_reportedcontent'     => 'Reported content counters',
	'desc_awaitingactivation'  => 'Accounts awaiting activation',
	'desc_mostonline'          => 'Most users online record',
	'desc_spiders'             => 'Search engine spiders',
	'desc_tasks'               => 'Scheduled tasks',
	'desc_update_check'        => 'Version check result',
	'desc_version'             => 'Installed version',
	'desc_internal_settings'   => 'Internal settings',
	'desc_threadprefixes'      => 'Thread prefixes',
	'desc_forumsdisplay'       => 'Forum display options',
	'desc_groupleaders'        => 'Group leaders',
	'desc_default_theme'       => 'Default theme',
	'desc_kps'                 => 'Karma / bonus points settings',

	// ── JS strings ──
	'js_hits'                  => '{1} match(es)',
	'js_copy'                  => 'Copy',
	'js_copied'                => 'Copied',
);
?>
