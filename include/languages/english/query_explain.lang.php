<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['query_explain'] = array (
	// ── Page ──
	'page_title'           => 'DEBUG MODE',
	'no_queries'           => 'There is no query to show.',
	'err_security'         => 'Invalid security token. Please refresh the page and try again.',

	// ── Query speed badges ──
	'speed_excellent'      => 'Excellent',
	'speed_good'           => 'Good',
	'speed_regular'        => 'Regular',
	'speed_bad'            => 'Bad',

	// ── Query cards ──
	'hdr_select'           => '#{1} - Select Query',
	'hdr_read'             => '#{1} - Read Query',
	'hdr_write'            => '#{1} - {2} Query',
	'hdr_deep'             => '#{1} - Deep Plan (EXPLAIN ANALYZE)',
	'badge_read_only'      => 'Read Only',
	'badge_write'          => 'Write Operation',
	'badge_deep'           => 'Deep Analysis',
	'explain_rows'         => '{1} row(s) in explain',
	'query_num'            => 'Query #{1}',
	'measured_time'        => 'Measured time:',
	'executed_analyze'     => 'Executed with ANALYZE',
	'affected_rows'        => 'Affected rows: {1}',
	'insert_id'            => 'Insert ID: {1}',
	'warn_level'           => 'Level',
	'warn_code'            => 'Code',
	'warn_message'         => 'Message',

	// ── Query errors ──
	'err_no_db'            => 'No database connection',
	'err_empty_query'      => 'Empty query.',
	'err_multi_statements' => 'Multiple statements are not allowed.',
	'err_explain_failed'   => 'EXPLAIN failed:',

	// ── Performance statistics ──
	'stats_title'          => 'Performance Statistics',
	'time_distribution'    => 'Time Distribution',
	'total_queries'        => 'Total Queries:',
	'displayed'            => 'Displayed:',
	'hidden'               => 'Hidden:',
	'total_time'           => 'Total Time:',
	'system_info'          => 'System Information',
	'php_version'          => 'PHP Version:',
	'mysql_version'        => 'MySQL Version:',
	'server_load'          => 'Server Load:',
	'memory_usage'         => 'Memory Usage:',
	'memory_peak'          => 'Peak Memory:',
	'gzip'                 => 'GZip Compression:',
	'enabled'              => 'Enabled',
	'disabled'             => 'Disabled',
	'unknown'              => 'Unknown',
	'php_extensions'       => 'PHP Extensions',
	'ext_loaded'           => 'Loaded',
	'ext_missing'          => 'Missing',

	// ── Performance summary ──
	'summary_title'        => 'Performance Summary',
	'fast_queries'         => 'Fast Queries (<10ms)',
	'medium_queries'       => 'Medium Queries (10-100ms)',
	'slow_queries'         => 'Slow Queries (>100ms)',
	'hidden_non_select'    => 'Hidden non-SELECT queries: {1}',

	// ── Cache calls ──
	'cache_calls_title'    => 'Cache Calls ({1} Total, {2})',
	'hdr_cache_call'       => '#{1} - {2} Call',
	'call_time'            => 'Call Time:',
	'call_num'             => 'Call #{1}',

	// ── Included files ──
	'included_files'       => 'Included Files',
	'files_count'          => '{1} files',
	'badge_core'           => 'Core',
);
