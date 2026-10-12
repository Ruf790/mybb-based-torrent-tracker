<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['category'] = array (
	// ── Page titles (<title>) ──
	'title_list'          => 'Manage Tracker Categories',
	'title_form'          => 'Manage Categories - {1}',
	'title_add_sub'       => 'Add Subcategory',

	// ── Headers / panes ──
	'pane_list'           => 'Tracker Categories',
	'sub_list'            => 'Main categories and their subcategories as shown on Browse',
	'pane_add_category'   => 'Add Category',
	'pane_edit_category'  => 'Edit Category',
	'sub_new_form'        => 'Create a main category or a subcategory',
	'sub_edit_form'       => 'ID {1} · {2}',
	'pane_add_sub'        => 'Add subcategory',
	'sub_inside'          => 'Inside {1}',
	'pane_delete'         => 'Delete category',
	'modal_add_category'  => 'Add category',
	'modal_edit_category' => 'Edit category',

	// ── Field labels ──
	'lbl_name'            => 'Name',
	'lbl_parent'          => 'Parent category',
	'lbl_icon'            => 'Icon',
	'lbl_loading'         => 'Loading…',

	// ── KPI tiles ──
	'stat_main'           => 'Main categories',
	'stat_subs'           => 'Subcategories',
	'stat_torrents'       => 'Torrents',
	'stat_empty'          => 'Empty',

	// ── Hints (hint_icon is printed as-is: HTML allowed) ──
	'hint_parent_none'    => 'Leave “None” for a main category',
	'hint_icon'           => 'Font Awesome classes, e.g. <code>fa-solid fa-film</code>',
	'lnk_browse_icons'    => 'browse icons',
	'hint_delete_meta'    => 'ID {1} · no torrents, no subcategories',
	'hint_cannot_undo'    => 'This action cannot be undone.',

	// ── Select options ──
	'opt_all'             => '— All categories —',
	'opt_none'            => '— None (main category) —',

	// ── Buttons ──
	'btn_pick'            => 'Pick',
	'btn_back'            => 'Back',
	'btn_reset'           => 'Reset',
	'btn_save'            => 'Save',
	'btn_save_changes'    => 'Save changes',
	'btn_cancel'          => 'Cancel',
	'btn_delete'          => 'Delete',
	'btn_close'           => 'Close',
	'btn_add_category'    => 'Add category',
	'btn_add_sub'         => 'Add subcategory',

	// ── Tooltips (title=) ──
	'tip_total_torrents'  => 'Torrents in this category and its subcategories',
	'tip_view_browse'     => 'View on Browse',
	'tip_view'            => 'View',
	'tip_edit'            => 'Edit',
	'tip_delete'          => 'Delete',
	'tip_locked_torrents' => 'Has torrents — reassign them first',
	'tip_locked_subs'     => 'Has subcategories — remove them first',

	// ── Empty states / filter ──
	'empty_title'         => 'No categories yet',
	'empty_text'          => 'Create the first one to start organising torrents.',
	'empty_no_subs'       => 'No subcategories',
	'empty_no_match'      => 'No matches',
	'ph_filter'           => 'Filter categories…',

	// ── Flash messages ──
	'flash_added'         => 'New category has been successfully added!',
	'flash_updated'       => 'Category has been updated!',
	'flash_deleted'       => 'Category has been successfully deleted!',
	'flash_sub_added'     => 'New subcategory has been successfully added!',

	// ── Errors ──
	'err_title'           => 'Error',
	'err_token'           => 'Invalid security token',
	'err_not_found'       => 'Category with this ID was not found!',
	'err_main_not_found'  => 'Main category with this ID was not found!',
	'err_ajax_not_found'  => 'Category not found',
	'err_has_torrents'    => 'This category still has {1} torrent(s) assigned to it. Please reassign or remove them before deleting the category.',
	'err_has_subs'        => 'This category still has {1} subcategory(ies). Delete or move them first.',
	'err_name_empty'      => 'Category name cannot be empty',
	'err_parent_missing'  => 'Selected parent category does not exist',
	'err_own_parent'      => 'A category cannot be its own parent',
	'err_has_subs_demote' => 'This category has subcategories — move or delete them before making it a subcategory',
	'err_db_save'         => 'Database error while saving category',
	'err_cache_write'     => 'Failed to write cache file',

	// ── JS (admin_category.js → AGS_LANG, without the js_ prefix) ──
	'js_lbl_name'         => 'Name',
	'js_lbl_parent'       => 'Parent category',
	'js_lbl_icon'         => 'Icon',
	'js_hint_has_subs'    => 'Has subcategories — must stay a main category',
	'js_edit_title'       => 'Edit “{1}”',
	'js_err_load'         => 'Error loading category data',
);
?>
