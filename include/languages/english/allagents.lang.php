<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['allagents'] = array (
	// ── Page ──
	'page_title'          => 'Allowed Clients',
	'sec_title'           => 'Allowed clients',
	'sec_subtitle'        => 'Choose which BitTorrent clients the tracker accepts. Each row groups every peer that announces with the same peer ID prefix.',

	// ── Flash messages ──
	'flash_saved'         => 'Client list saved.',
	'flash_nochange'      => 'Nothing changed, so there was nothing to save.',
	'flash_csrf'          => 'The security token expired. Reload the page and save again.',
	'flash_dberror'       => 'The database rejected the update. The client list was not saved.',

	// ── KPI tiles ──
	'kpi_clients'         => 'Clients',
	'kpi_allowed'         => 'Allowed',
	'kpi_blocked'         => 'Blocked',
	'kpi_peers'           => 'Active peers',

	// ── Toolbar ──
	'ph_search'           => 'Search by client, version or prefix',
	'aria_filters'        => 'Show',
	'opt_all'             => 'All',
	'opt_allowed'         => 'Allowed',
	'opt_blocked'         => 'Blocked',
	'btn_allow_shown'     => 'Allow shown',
	'tip_allow_shown'     => 'Allow every client shown below',
	'btn_block_shown'     => 'Block shown',
	'tip_block_shown'     => 'Block every client shown below',

	// ── Table ──
	'col_client'          => 'Client',
	'col_prefix'          => 'Peer ID prefix',
	'col_peers'           => 'Peers',
	'col_access'          => 'Access',
	'lbl_unknown_client'  => 'Unknown client',
	'lbl_more'            => '+{1} more',
	'lbl_no_peers'        => 'No peers',
	'tip_no_peers'        => 'Whitelisted, but no peers are connected with this client right now',
	'lbl_state_allowed'   => 'Allowed',
	'lbl_state_blocked'   => 'Blocked',

	// ── Empty states ──
	'empty_no_match'      => 'No clients match this search. Clear the search or pick another filter.',
	'empty_no_clients'    => 'No clients yet. They appear here as soon as peers announce to the tracker.',

	// ── Save bar ──
	'lbl_no_unsaved'      => 'No unsaved changes',
	'btn_reset'           => 'Reset',
	'btn_save'            => 'Save changes',

	// ── JS strings ──
	'js_state_allowed'    => 'Allowed',
	'js_state_blocked'    => 'Blocked',
	'js_dirty_none'       => 'No unsaved changes',
	'js_dirty_count'      => 'Unsaved changes: {1}',
	'js_btn_cancel'       => 'Cancel',
	'js_reset_title'      => 'Discard changes?',
	'js_reset_text'       => 'Every switch goes back to its saved position.',
	'js_reset_confirm'    => 'Discard changes',
	'js_blockall_title'   => 'Block every client?',
	'js_blockall_text'    => 'No client will be on the allowed list after saving.',
	'js_blockall_confirm' => 'Save anyway',
);
?>
