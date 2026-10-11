<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['check_update'] = array (
	// ── Page ──
	'page_title'          => 'Update Check',
	'page_subtitle'       => 'Compares this tracker with the GitHub repository — new commits, changed files and database migrations',

	// ── KPI tiles ──
	'kpi_commits'         => 'New commits',
	'kpi_files'           => 'Changed files',
	'kpi_migrations'      => 'Pending migrations',
	'kpi_scan'            => 'Files differ',

	// ── Versions ──
	'sec_version'         => 'Version',
	'lbl_latest'          => 'Latest on GitHub',
	'lbl_installed'       => 'Installed',
	'lbl_unknown'         => 'unknown',
	'src_git'             => 'from local .git',
	'src_setting'         => 'marked manually',
	'hint_git'            => 'The version is taken from .git and changes with git pull.',
	'lbl_custom_sha'      => 'Installed commit (SHA)',
	'btn_save_sha'        => 'Save',
	'hint_custom_sha'     => 'The commit the files on this server were taken from — full or short SHA (7+ characters).',

	// ── State ──
	'state_current'       => 'You have the latest version.',
	'state_behind'        => 'New commits on GitHub: {1}.',
	'state_diverged'      => 'The installed commit also has {1} commit(s) that are not in {2}.',
	'state_ahead_local'   => 'The installed commit is newer than {1} on GitHub (not pushed yet?).',
	'state_base_unknown'  => 'GitHub does not know commit {1} (history rewritten or force-pushed?). Mark the current state again.',
	'state_no_base'       => 'The installed commit is not known yet. If the files on the server match GitHub right now, click “Mark as up to date”; otherwise enter the commit you deployed from, or scan the files to see what differs.',

	// ── Commits and files ──
	'sec_commits'         => 'What is new',
	'sec_files'           => 'Changed files',
	'hint_cmp_limit'      => 'A comparison shows at most 250 commits and 300 files.',
	'lnk_full_diff'       => 'Full diff on GitHub',
	'st_added'            => 'added',
	'st_removed'          => 'removed',
	'st_modified'         => 'modified',
	'st_renamed'          => 'renamed',

	// ── Migrations ──
	'sec_migrations'      => 'Database migrations',
	'lbl_schema'          => 'Schema version: {1}',
	'mig_new'             => 'New on GitHub — download them into sql/migrations/',
	'mig_pending'         => 'Downloaded but not applied — run upgrade.php',
	'mig_ok'              => 'No pending migrations.',

	// ── File scan ──
	'sec_scan'            => 'Compare files with GitHub',
	'hint_scan'           => 'Computes git hashes of the files on the server and compares them with {1}. Skipped: generated configs, cache/, uploads/, torrents/, error_logs/, docs/ and .htaccess files.',
	'scan_none'           => 'Not scanned yet — use “Scan files” in the bar below.',
	'scan_info'           => 'Compared with {1} at {2}: identical {3}, skipped {4}.',
	'scan_truncated'      => 'GitHub truncated the file tree — the result is incomplete.',
	'scan_modified'       => 'Differ from GitHub ({1})',
	'scan_missing'        => 'Missing on the server ({1})',
	'scan_clean'          => 'All checked files match GitHub.',

	// ── Bottom bar ──
	'hint_checked'        => 'Checked at {1}, cached for {2} min',
	'hint_rate'           => 'GitHub API requests left: {1} of {2}',
	'btn_zip'             => 'Download ZIP',
	'btn_scan'            => 'Scan files',
	'btn_scan_clear'      => 'Hide scan',
	'btn_mark'            => 'Mark as up to date',
	'btn_refresh'         => 'Check now',

	// ── Flash ──
	'flash_bad_key'       => 'Invalid or expired form token, please try again.',
	'flash_bad_sha'       => 'Invalid commit SHA.',
	'flash_sha_404'       => 'Commit {1} was not found on GitHub.',
	'flash_saved'         => 'Installed version saved: {1}.',
	'flash_save_fail'     => 'Could not save to the settings table.',

	// ── Errors ──
	'err_no_curl'         => 'The PHP cURL extension is not available.',
	'err_network'         => 'Cannot reach GitHub: {1}',
	'err_rate_limit'      => 'GitHub API limit reached; it resets at {1}. Add $config[\'github_token\'] to include/config.php to raise it.',
	'err_token'           => 'GitHub rejected the token (HTTP 401) — check $config[\'github_token\'] in include/config.php.',
	'err_api'             => 'GitHub API error (HTTP {1}).',
	'err_no_branch'       => 'Branch {1} was not found on GitHub.',

	// ── JS ──
	'js_yes'              => 'Yes',
	'js_cancel'           => 'Cancel',
	'js_working'          => 'Working…',
	'js_confirm_mark_title' => 'Mark as up to date?',
	'js_confirm_mark_text'  => 'The installed version will be set to commit {1}. Do this only if the files on the server really match GitHub.',
	'js_confirm_sha_title'  => 'Save installed commit?',
	'js_confirm_sha_text'   => 'The installed version will be set to commit {1}.',
);
