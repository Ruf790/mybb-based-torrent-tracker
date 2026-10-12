<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['docleanup'] = array
(
    // ── Page ──
    'page_title'            => 'CleanUp',

    // ── Confirmation screen ──
    'sec_sanity_check'      => 'Sanity Check!',
    'txt_confirm_warning'   => 'This will force ALL scheduled cron jobs to run immediately (regardless of their normal schedule), including any that delete or modify data.',
    'btn_run_all'           => 'Force Run All Cron Jobs Now',
    'btn_cancel'            => 'Cancel',

    // ── Result message ──
    'sec_system_message'    => 'System Message',
    'lbl_close'             => 'Close',
    'lbl_done'              => 'Done!',
    'msg_cleanup_finished'  => 'System Message: {1}, Cleanup operation has been finished.',
    'msg_go_back'           => 'Click {1} to go back.',
    'lnk_here'              => 'here',

    // ── Errors ──
    'err_no_permission'     => 'Error! You do not have permission to access this page.',
    'err_invalid_token'     => 'Invalid security token. Please try again from the page.',
    'err_cron_reset_failed' => 'Error: Failed to reset cron schedule (nextrun update failed). The database may have safe-update mode enabled, or another error occurred — check the server error log.',
);
?>
