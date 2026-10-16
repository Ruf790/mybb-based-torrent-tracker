<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['langcheck'] = array (
	// ── Page ──
	'page_title'          => 'Lang Checker',
	'page_subtitle'       => 'Compares keys used in code with every language file — missing, unused and mismatched',
	'lbl_reference'       => 'Reference: {1}',

	'lbl_lang_dir'        => 'Folder: {1}',
	'err_no_lang_dir'     => 'Language folder not found — nothing was compared.',
	'err_no_lang_dir_hint'=> 'Looked in $lang->path, then languages/, include/languages/ and inc/languages/ under {1}.',
	'issue_no_lang_dir'    => 'Language folder not found — keys were not checked',

	// ── KPI tiles ──
	'kpi_pages'           => 'Pages checked',
	'kpi_clean'           => 'Clean',
	'kpi_warn'            => 'With warnings',
	'kpi_error'           => 'With errors',

	// ── Files summary ──
	'sec_files'           => 'Files per language',
	'msg_files_ok'        => 'Every language has the same set of files.',
	'lbl_files_missing'   => 'Files missing here',
	'tip_orphan'          => 'No PHP file in the project loads this page',
	'hint_orphan'         => '⚠ — no PHP file in the project loads this page: probably left over from a removed page and can be deleted instead of translated.',

	// ── Page list ──
	'sec_pages'           => 'Pages',
	'badge_ok'            => 'OK',
	'badge_warn'          => 'Warnings',
	'badge_error'         => 'Errors',
	'lbl_used'            => 'in code',
	'lbl_files'           => 'Loaded by',
	'lbl_js'              => 'Scripts',
	'msg_page_ok'         => 'All keys match in every language.',
	'empty_pages'         => 'No language files found in {1}',
	'empty_problems'      => 'No problems found — every page is clean.',

	// ── Issues ──
	'issue_no_file'        => 'Language file is missing',
	'issue_bad_file'       => 'File could not be loaded',
	'issue_missing'        => 'Used in code but missing — TypeError under strict_types',
	'issue_missing_ref'    => 'Present in {1}, missing here',
	'issue_extra_ref'      => 'Not present in {1}',
	'issue_placeholders'   => 'Placeholders differ from {1}',
	'issue_unused'         => 'Not used in code',
	'issue_unused_dynamic' => 'Not found in code, but the page builds keys dynamically — check by hand',
	'issue_non_string'     => 'Value is not a string',
	'issue_dups'           => 'Duplicate keys — the later value wins',
	'issue_not_loaded'     => 'No PHP file in the project loads this page — key usage not checked',
	'issue_missing_opt'   => 'Missing, but the code has a fallback (?? / isset) — no TypeError, the fallback text is shown',
	'issue_other_name'    => 'Array is named differently from the page ({1}) — make sure $lang->load() picks it up',
	'issue_missing_js'    => 'Used in JS but missing — the English fallback from t() is shown',

	// ── Bottom bar ──
	'hint_scan'           => '{1} PHP files scanned in {2} ms',
	'hint_skipped'        => '{1} folders without code skipped (hover for the list)',
	'aria_filter'         => 'Filter',
	'opt_show_all'        => 'All pages',
	'opt_show_problems'   => 'Problems only',
	'btn_rescan'          => 'Rescan',
	'btn_expand_all'      => 'Expand all',
	'btn_collapse_all'    => 'Collapse all',

	// ── Report ──
	'btn_copy_report'     => 'Copy report',
	'rep_more'            => '+{1} more',
	'js_copied'           => 'Copied — paste it into the chat',
	'js_copy_failed'      => 'Copy blocked — the report is shown below, press Ctrl+C',
	
	
	
	// ── Unused files ──
    'opt_show_unused'      => 'Unused files',
    'sec_unused'           => 'Unused lang files',
    'hint_unused_scope'    => 'A file counts as used if it is loaded with $lang->load(\'page\') or get_section(), passed to send_pm() as language_file, or its strings are read. Commented-out code is ignored.',
    'hint_unused_dynamic'  => 'Before deleting anything, check the dynamic load() calls: a file loaded through a variable is invisible to the scan.',
    'msg_unused_ok'        => 'All lang files are in use.',
    'issue_unused_file'    => 'Not loaded or referenced anywhere — candidates for deletion',
    'issue_loaded_only'    => 'Loaded, but none of its strings are used',
    'issue_missing_lang'   => 'Loaded, but the lang file does not exist',
    'issue_refs_only'      => 'Strings are used, but the file is never loaded by name',
    'issue_dynamic_load'   => 'Dynamic load() calls — check by hand',
    'issue_loaded_dynamic' => 'Loaded; strings are read through a variable — most likely in use',
	
	// ── Hard-coded text tab ──
	'opt_show_hardcoded' => 'Hard-coded text',
	'sec_hardcoded'      => 'Hard-coded text in PHP',
	'hint_hardcoded'     => 'Text written straight into PHP/HTML instead of a lang file — users see it in this language only. Lang fallbacks (?? \'…\'), logs, SQL and comments are skipped. This is a heuristic: some lines may be technical.',
	'msg_hardcoded_ok'   => 'No hard-coded text found.',
	'lbl_hc_files'       => 'Files',
	'lbl_hc_strings'     => 'Strings',
	'lbl_hc_line'        => 'line {1}',
	
	

	
);
?>
