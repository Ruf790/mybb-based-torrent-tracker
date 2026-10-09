<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

// Admin panel → Bonus Points (admin/bonuspoints.php)
// Strings marked [raw] are printed as-is and may contain HTML; all others are plain text.
$language['bonuspoints'] = array (
	// ── Page / header ──
	'page_title'           => 'Bonus Points {1} — {2}',
	'head_title'           => 'Bonus Points',
	'head_sub'             => 'Bonus shop items, user balances and the bonus log',

	// ── Tabs ──
	'tab_items'            => 'Shop items',
	'tab_add'              => 'Add item',
	'tab_users'            => 'Users',
	'tab_log'              => 'Log',
	'tab_reset'            => 'Reset points',

	// ── KPI tiles ──
	'kpi_items'            => 'Shop items',
	'kpi_users'            => 'Users with points',
	'kpi_total'            => 'Points in total',
	'kpi_top'              => 'Top balance',

	// ── Section headings ──
	'sec_items'            => 'Shop items',
	'sec_new_item'         => 'New shop item',
	'sec_edit_item'        => 'Edit item: {1}',
	'sec_users'            => 'Users with points',
	'sec_edit_balance'     => 'Edit balance',
	'sec_log'              => 'Bonus log',
	'sec_reset'            => 'Reset bonus points',
	'sub_items'            => '{1} item(s), cheapest first',
	'sub_users'            => '{1} user(s), highest balance first',
	'sub_reset'            => 'Sets the balance of active, confirmed users to 0',

	// ── Table headers ──
	'th_item'              => 'Item',
	'th_type'              => 'Type',
	'th_amount'            => 'Amount',
	'th_price'             => 'Price',
	'th_user'              => 'User',
	'th_points'            => 'Points',
	'th_uploaded'          => 'Uploaded',
	'th_date'              => 'Date',
	'th_reason'            => 'What for',
	'th_change'            => 'Change',
	'th_balance'           => 'Balance',

	// ── Field labels ──
	'lbl_name'             => 'Name',
	'lbl_price'            => 'Price',
	'lbl_points_unit'      => 'points',
	'lbl_description'      => 'Description',
	'lbl_traffic'          => 'Traffic amount',
	'lbl_delete_item'      => 'Delete this item',
	'lbl_new_balance'      => 'New balance',
	'lbl_balance_now'      => '{1} now',
	'lbl_id'               => 'ID {1}',
	'lbl_user'             => 'User',
	'lbl_type'             => 'Type',
	'lbl_period'           => 'Period',
	'lbl_which_group'      => 'Which group',
	'lbl_usergroup'        => 'Usergroup',
	'lbl_all_groups'       => 'ALL user groups',
	'lbl_group_fallback'   => 'Group {1}',
	'lbl_deleted_user'     => '#{1} (deleted)',
	'lbl_whole_day'        => '(whole day)',
	'lbl_by_actor'         => 'by {1}',

	// ── Hints / placeholders / titles ──
	'hint_delete_item'     => 'You will be asked to confirm',
	'hint_bytes'           => '= {1} bytes · raw bytes before: 1 GB = 1,073,741,824', // [raw] {1} = byte counter span
	'hint_user'            => 'name or #id',
	'ph_name'              => 'e.g. 5 GB Upload',
	'ph_description'       => 'What the user gets',
	'ph_find_user'         => 'Find user…',
	'ph_everyone'          => 'Everyone',
	'title_edit'           => 'Edit',
	'title_edit_item'      => 'Edit item',
	'title_history'        => 'Bonus history',
	'title_edit_balance'   => 'Edit balance',
	'title_only_user'      => 'Only this user',
	'alert_reset'          => '<strong>Can\'t be undone.</strong> Balances are wiped; you will be asked to confirm on the next screen.', // [raw]

	// ── Buttons ──
	'btn_items'            => 'Shop items',
	'btn_users'            => 'Users',
	'btn_cancel'           => 'Cancel',
	'btn_add_item'         => 'Add item',
	'btn_save_item'        => 'Save item',
	'btn_save_balance'     => 'Save balance',
	'btn_set_zero'         => 'Set 0',
	'btn_show'             => 'Show',
	'btn_reset_filters'    => 'Reset',
	'btn_continue'         => 'Continue',
	'btn_yes_delete'       => 'Yes, delete',
	'btn_yes_reset'        => 'Yes, reset',

	// ── Select options ──
	'opt_all_types'        => 'All types',
	'opt_period_today'     => 'Today',
	'opt_period_7'         => '7 days',
	'opt_period_30'        => '30 days',
	'opt_period_90'        => '90 days',
	'opt_period_all'       => 'All time',

	// ── Bonus log summary ──
	'log_entries_one'      => '{1} entry',
	'log_entries'          => '{1} entries',
	'log_net'              => 'net <strong>{1}</strong>', // [raw]

	// ── Empty states ──
	'empty_items'          => 'No shop items yet',
	'empty_users'          => 'Nobody has bonus points yet',
	'empty_users_search'   => 'No user matches “{1}”', // [raw] {1} is escaped
	'empty_log'            => 'No entries for these filters',

	// ── Confirmations ──
	'confirm_delete_title' => 'Delete shop item?',
	'confirm_delete_text'  => '<strong>{1}</strong> will be removed from the bonus shop permanently.', // [raw]
	'confirm_reset_title'  => 'Reset bonus points?',
	'confirm_reset_text'   => '{1} — {2} user(s) lose {3} points in total.', // [raw] {1} = <strong>group</strong>

	// ── Results (flash) ──
	'res_not_found'        => 'Not found',
	'res_item_missing'     => 'This shop item does not exist.',
	'res_user_missing'     => 'User not found.',
	'res_not_saved'        => 'Not saved',
	'res_need_name'        => 'The item needs a name.',
	'res_db_error'         => 'Database error.',
	'res_added'            => 'Item added',
	'res_add_failed'       => 'Could not add item',
	'res_added_text'       => '“{1}” is now in the bonus shop.', // [raw]
	'res_deleted'          => 'Item deleted',
	'res_delete_failed'    => 'Could not delete',
	'res_deleted_text'     => '“{1}” was removed.', // [raw]
	'res_saved'            => 'Item saved',
	'res_save_failed'      => 'Could not save',
	'res_saved_text'       => 'Changes to “{1}” are live.', // [raw]
	'res_balance_updated'  => 'Balance updated',
	'res_update_failed'    => 'Could not update',
	'res_balance_text'     => '{1}: {2} → <strong>{3}</strong> points', // [raw]
	'res_reset'            => 'Points reset',
	'res_reset_failed'     => 'Could not reset',
	'res_reset_text'       => '{1} now have 0 points.', // [raw] {1} = <strong>group</strong>

	// ── Errors ──
	'err_security_title'   => 'Security Error',
	'err_security_text'    => 'Invalid security token. Please refresh the page and try again.',
	'err_title'            => 'Error',
	'err_method'           => 'Invalid request method',

	// ── JS (edit balance) ──
	'js_no_change'         => 'No change',
	'js_diff_points'       => '{1} points',
	'js_diff_vs_now'       => '{1} compared to now',
);
?>
