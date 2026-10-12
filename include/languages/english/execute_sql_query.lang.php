<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['execute_sql_query'] = array (
    // ── Page / header ─────────────────────────────────────────────────────────
    'page_title'            => 'SQL Query Editor',
    'sub_header'            => 'Run SQL against the live database. Every executed query is written to the staff log.',
    'conn_ok'               => 'Connected',
    'conn_off'              => 'Offline',

    // ── KPI tiles ─────────────────────────────────────────────────────────────
    'lbl_database'          => 'Database',
    'lbl_mysql_version'     => 'MySQL version',
    'lbl_tables'            => 'Tables',
    'sub_rows'              => '~{1} rows',
    'lbl_size'              => 'Size on disk',
    'sub_size'              => 'data + indexes',

    // ── Size units ────────────────────────────────────────────────────────────
    'unit_b'                => 'B',
    'unit_kb'               => 'KB',
    'unit_mb'               => 'MB',
    'unit_gb'               => 'GB',
    'unit_tb'               => 'TB',

    // ── Editor ────────────────────────────────────────────────────────────────
    'ph_query'              => 'Enter your SQL query here',
    'btn_format'            => 'Format',
    'btn_history'           => 'History',
    'btn_clear'             => 'Clear',
    'btn_execute'           => 'Execute',

    // ── Quick examples ────────────────────────────────────────────────────────
    'sec_examples'          => 'Quick examples',
    'opt_ex_all_users'      => 'All users',
    'opt_ex_active_users'   => 'Active users',
    'opt_ex_show_tables'    => 'Show tables',
    'opt_ex_thread_status'  => 'Thread status',
    'opt_ex_table_sizes'    => 'Table sizes',
    'opt_ex_process_list'   => 'Process list',

    // ── Result panel ──────────────────────────────────────────────────────────
    'sec_result'            => 'Query result',
    'lbl_exec_ms'           => '{1} ms',
    'lbl_error'             => 'Error',
    'btn_copy_csv'          => 'Copy as CSV',

    // ── Messages ──────────────────────────────────────────────────────────────
    'flash_csrf_failed'     => 'Security check failed. Please refresh the page and try again.',
    'flash_db_failed'       => 'Database connection failed. Check the server log for details.',
    'flash_destructive'     => 'This query looks destructive (DROP/DELETE/TRUNCATE/UPDATE/ALTER). Confirm the dialog to run it.',
    'flash_rows_returned'   => '{1} row(s) returned',
    'flash_no_rows'         => 'Query returned no rows.',
    'flash_rows_affected'   => '{1} row(s) affected',
    'flash_empty_query'     => 'Empty query — nothing to execute.',

    // ── History modal ─────────────────────────────────────────────────────────
    'sec_history'           => 'Query history',
    'tip_load_query'        => 'Load into editor',
    'hint_history_empty'    => 'No queries yet. Executed queries from this session will appear here.',
    'btn_clear_history'     => 'Clear history',
    'btn_close'             => 'Close',

    // ── JS strings ────────────────────────────────────────────────────────────
    'js_cancel'             => 'Cancel',
    'js_clear_hist_title'   => 'Clear query history?',
    'js_clear_hist_text'    => 'All saved queries from this session will be removed.',
    'js_clear_hist_confirm' => 'Clear history',
    'js_run_title'          => 'Run {1} query?',
    'js_run_text'           => 'This query changes or deletes data and runs immediately. There is no undo.',
    'js_run_confirm'        => 'Run {1}',
    'js_run_fallback_head'  => 'This looks like a destructive query ({1}).',
    'js_run_fallback_body'  => 'It will run immediately with no undo. Continue?',
    'js_running'            => 'Running…',
    'js_copied'             => 'Copied',
    'js_copy_failed'        => 'Copy failed',
);
?>
