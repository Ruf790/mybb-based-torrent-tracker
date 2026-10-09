<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['cleartable'] = array (

    // ── Page titles (stdhead, plain text) ──
    'title_page' => 'TRUNCATE MySQL Tables',
    'title_results' => 'TRUNCATE MySQL Tables - Results',

    // ── Pane titles ──
    'pane_form' => 'Truncate Database Tables',
    'pane_confirm' => 'Confirm Truncation',
    'pane_success' => 'Success',
    'pane_failed' => 'Failed',

    // ── Tags ──
    'tag_irreversible' => 'IRREVERSIBLE',
    'tag_step2' => 'STEP 2 OF 2',
    'tag_protected' => 'protected',

    // ── Hints / notices  (*_html keys are printed as-is; {1} there is HTML built by PHP) ──
    'hint_form_warn_html' => '<strong>TRUNCATE deletes every row in the tables you select. There is no undo.</strong> Take a database backup before continuing.',
    'hint_confirm_warn_html' => '<strong>You are about to permanently delete all data in {1} table(s).</strong> This cannot be undone — make sure you have a backup before continuing.',
    'hint_locked' => '{1} table(s) are locked and cannot be truncated from here:',

    // ── Labels ──
    'lbl_selected_html' => '{1} table(s) selected',
    'lbl_filter_placeholder' => 'Filter tables…',
    'lbl_all' => 'All',
    'lbl_clear' => 'Clear',
    'lbl_invert' => 'Invert',
    'lbl_success_lead' => 'Operation completed successfully!',
    'lbl_success_sub' => 'The following tables have been truncated:',
    'lbl_table_truncated' => '{1} - successfully truncated!',
    'lbl_total_html' => 'Total truncated: {1} table(s)',
    'lbl_failed_intro' => 'Failed to truncate the following table(s):',
    'lbl_optimizing' => 'Optimizing…',
    'lbl_opt_done' => 'All tables optimized.',
    'lbl_opt_reminder' => 'Don\'t forget to optimize your tables!',

    // ── Buttons ──
    'btn_truncate_selected' => 'Truncate selected tables',
    'btn_confirm_yes' => 'Yes, truncate these tables',
    'btn_optimize' => 'Optimize Tables',
    'btn_cancel' => 'Cancel',
    'btn_go_back' => 'Go back',
    'btn_back' => 'Back',

    // ── Flash / error messages ──
    'flash_none_selected' => 'No tables selected for truncation.',
    'flash_bad_token_page' => 'Invalid or missing security token. Please use the confirmation button below instead of a direct link.',
    'flash_bad_token' => 'Invalid security token',
    'flash_bad_table' => 'Invalid table name',

    // ── JS strings (js_ prefix is stripped by PHP → AGS_LANG; **bold** and `code` are rendered by JS as DOM nodes) ──
    'js_none_title' => 'No tables selected',
    'js_none_text' => 'Tick at least one table to truncate.',
    'js_confirm_title' => 'Truncate {1} table(s)?',
    'js_confirm_warn' => 'Every row in these tables will be deleted. **There is no undo** — make sure you have a backup.',
    'js_confirm_type' => 'Type `TRUNCATE` to confirm',
    'js_confirm_btn' => 'Truncate',
    'js_confirm_tail' => 'This cannot be undone.',
    'js_cancel' => 'Cancel',
    'js_type_exact' => 'Type TRUNCATE exactly',
    'js_truncating' => 'Truncating…',
    'js_trunc_done' => '{1} table(s) truncated',
    'js_optimizing' => 'Optimizing {1} ({2}/{3})…',
    'js_opt_ok' => 'Optimized',
    'js_opt_err' => 'Error',
    'js_unknown_error' => 'unknown error',
    'js_opt_errors_title' => 'Optimization finished with errors',
    'js_opt_all_done' => 'All tables optimized',
    'js_fail_title' => 'Failed to truncate {1} table(s)',
    'js_fail_partial' => 'The tables that did succeed are listed on the page.',
    'js_fail_log' => 'Check the error log.',
    'js_error_title' => 'Truncate Database Tables',
    'js_go_back' => 'Go back',
);
?>
