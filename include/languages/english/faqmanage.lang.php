<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

//  admin/faqmanage.php
$language['faqmanage'] = array (
	// ── Meta ── (plural rules: en / ru)
	'lang_code'               => 'en',

	// ── Main page ──
	'pane_title'              => 'FAQ manager',
	'pane_subtitle'           => 'Categories and questions shown on the public FAQ page.',
	'btn_new_question'        => 'New question',
	'btn_new_category'        => 'New category',

	// ── KPI tiles ──
	'kpi_categories'          => 'Categories',
	'kpi_categories_hint'     => 'Groups on the FAQ page',
	'kpi_questions'           => 'Questions',
	'kpi_questions_hint'      => 'Inside categories',
	'kpi_empty'               => 'Empty categories',
	'kpi_empty_hint'          => 'No questions yet',
	'kpi_unlinked'            => 'Unlinked questions',
	'kpi_unlinked_hint'       => 'Not in any category',

	// ── No categories ──
	'sec_no_categories'       => 'No categories yet',
	'msg_no_categories'       => 'Create a category first, then add questions to it.',
	'btn_first_category'      => 'Create the first category',

	// ── Categories table ──
	'sec_categories'          => 'Categories',
	'lbl_total'               => '{1} total',
	'col_order'               => 'Order',
	'col_category'            => 'Category',
	'col_questions'           => 'Questions',
	'col_actions'             => 'Actions',
	'aria_order_for'          => 'Display order for {1}',
	'tip_open_questions'      => 'Open questions',
	'tip_add_question'        => 'Add question',
	'tip_edit_category'       => 'Edit category',
	'tip_edit_question'       => 'Edit question',
	'tip_delete'              => 'Delete',
	'aria_delete'             => 'Delete {1}',
	'hint_order_categories'   => 'Lower numbers appear first on the FAQ page.',
	'btn_save_order'          => 'Save order',

	// ── Unlinked questions ──
	'sec_unlinked'            => 'Unlinked questions',
	'hint_unlinked'           => 'Open one and pick a category to show it again',

	// ── Category view ──
	'lnk_all_categories'      => 'All categories',
	'lbl_in_category_one'     => '{1} question in this category.',
	'lbl_in_category_few'     => '{1} questions in this category.',
	'lbl_in_category_many'    => '{1} questions in this category.',
	'btn_edit_category'       => 'Edit category',
	'btn_add_question'        => 'Add question',
	'sec_cat_empty'           => 'This category has no questions',
	'msg_cat_empty'           => 'Add a question and its answer to show the category on the FAQ page.',
	'btn_first_question'      => 'Add the first question',
	'ph_filter'               => 'Filter questions',
	'btn_expand_all'          => 'Expand all',
	'btn_collapse_all'        => 'Collapse all',
	'msg_no_match'            => 'No questions match the filter.',
	'hint_order_questions'    => 'Lower numbers appear first inside the category.',

	// ── Russian version ──
	'sec_ru_version'          => 'Russian version',
	'hint_ru_fallback'        => 'Optional. Leave empty and the English version is shown.',
	'lbl_question_ru'         => 'Question (Russian)',
	'lbl_category_name_ru'    => 'Category name (Russian)',
	'lbl_answer_ru'           => 'Answer (Russian)',
	'lbl_description_ru'      => 'Description (Russian)',
	'tip_ru_ok'               => 'Russian version is filled in',
	'tip_ru_missing'          => 'No Russian version — the English one is shown',

	// ── Form ──
	'form_new_category'       => 'New category',
	'form_new_category_sub'   => 'Categories group questions on the FAQ page.',
	'btn_create_category'     => 'Create category',
	'form_new_question'       => 'New question',
	'form_new_question_sub'   => 'Add a question and its answer to a category.',
	'form_edit_question'      => 'Edit question',
	'form_edit_question_sub'  => 'Question #{1}',
	'form_edit_category'      => 'Edit category',
	'form_edit_category_sub'  => 'Category #{1}',
	'btn_save_changes'        => 'Save changes',
	'opt_choose_category'     => 'Choose a category',
	'lbl_category'            => 'Category',
	'lbl_question'            => 'Question',
	'lbl_category_name'       => 'Category name',
	'lbl_answer'              => 'Answer',
	'lbl_description'         => 'Description',
	'lbl_optional'            => 'optional',
	'hint_html'               => 'HTML is allowed and is shown on the FAQ page exactly as written.',
	'sec_placement'           => 'Placement',
	'lbl_disporder'           => 'Display order',
	'hint_disporder'          => 'Lower numbers appear first.',
	'hint_shortcut'           => '{1} saves the form',

	// ── Save bar ──
	'status_no_changes'       => 'No unsaved changes.',
	'status_required'         => 'Fields marked * are required.',
	'btn_cancel'              => 'Cancel',
	'btn_undo'                => 'Undo',

	// ── Flash messages ──
	'flash_order'             => 'Display order saved.',
	'flash_cat_created'       => 'Category created.',
	'flash_q_created'         => 'Question added.',
	'flash_updated'           => 'Changes saved.',
	'flash_deleted'           => 'Deleted.',
	'aria_close'              => 'Close',

	// ── Errors ──
	'lbl_error'               => 'Error',
	'sec_error_page'          => 'Something went wrong',
	'msg_error_page'          => 'The request could not be completed.',
	'btn_back_to_faq'         => 'Back to FAQ',
	'err_not_found'           => 'The FAQ entry was not found.',
	'err_csrf'                => 'Security check failed. Reload the page and try again.',
	'err_no_order'            => 'No display order values were sent.',
	'err_category_first'      => 'Create a category before adding questions.',
	'err_category_name'       => 'Enter a category name.',
	'err_question'            => 'Enter the question.',
	'err_answer'              => 'Enter the answer.',
	'err_choose_category'     => 'Choose an existing category.',
	// ── JS ──
	'js_btn_cancel'           => 'Cancel',
	'js_btn_delete'           => 'Delete',
	'js_del_cat_title'        => 'Delete this category?',
	'js_del_q_title'          => 'Delete this question?',
	'js_del_text'             => '“{1}” will be deleted permanently.',
	'js_del_cat_text_one'     => '“{1}” and its {2} question will be deleted permanently.',
	'js_del_cat_text_few'     => '“{1}” and its {2} questions will be deleted permanently.',
	'js_del_cat_text_many'    => '“{1}” and its {2} questions will be deleted permanently.',
	'js_unsaved_one'          => '{1} unsaved change',
	'js_unsaved_few'          => '{1} unsaved changes',
	'js_unsaved_many'         => '{1} unsaved changes',
	'js_chars_one'            => '{1} character',
	'js_chars_few'            => '{1} characters',
	'js_chars_many'           => '{1} characters',
);
?>
