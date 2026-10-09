<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

//  admin/modrules.php
$language['modrules'] = array (
	// ── Header ──
	'title'             => 'Manage Tracker Rules',
	'description'       => 'Manage tracker rules and regulations',
	'new'               => 'Create New Rule',

	// ── KPI tiles ──
	'kpi_total'         => 'Total rules',
	'kpi_all'           => 'For all groups',
	'kpi_limited'       => 'Group-restricted',
	'kpi_last'          => 'Last added',

	// ── Form ──
	'title2'            => 'Rule Title',
	'title3'            => 'Rule Text',
	'title4'            => 'Usergroups',
	'formatting_hint'   => 'BBCode and HTML allowed',
	'select_all'        => 'Select All',
	'deselect_all'      => 'Deselect All',
	'groups_hint'       => 'Nothing selected = rule is shown to all groups',
	'save'              => 'Save Rule',
	'cancel'            => 'Cancel',

	// ── Russian version ──
	'sec_ru_version'    => 'Russian version',
	'hint_ru_fallback'  => 'Optional. Leave empty and the English version is shown.',
	'lbl_title_ru'      => 'Title (Russian)',
	'lbl_text_ru'       => 'Text (Russian)',
	'tip_ru_ok'         => 'Russian version is filled in',
	'tip_ru_missing'    => 'No Russian version — the English one is shown',
	'flash_ru_missing'  => 'Rules without a Russian version: {1}. The English text is shown for them.',

	// ── Rules list ──
	'search'            => 'Search rules…',
	'search_empty'      => 'No rules match your search',
	'all_groups'        => 'All User Groups',
	'edit'              => 'Edit',
	'delete'            => 'Delete',
	'no_rules'          => 'No rules found',
	'create_first'      => 'Click the button above to create your first rule',

	// ── Delete confirmation ──
	'confirm_title'     => 'Delete this rule?',
	'confirm'           => 'This action cannot be undone.',

	// ── Messages ──
	'created_success'   => 'Rule created successfully',
	'updated_success'   => 'Rule updated successfully',
	'deleted_success'   => 'Rule deleted successfully',
	'error'             => 'Title and text can not be empty!',
	'invalid_token'     => 'Invalid security token. Please reload the page and try again.',
);
?>