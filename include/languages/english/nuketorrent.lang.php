<?php

if (!defined('IN_TRACKER')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$language['nuketorrent'] = array(
    // ── Page ──
    'page_title'           => 'Nuke Torrent: {1}',
    'sec_nuke'             => 'Nuke Torrent',
    'sec_nuked'            => 'Torrent is nuked',

    // ── Current state ──
    'lbl_current_reason'   => 'Current reason',
    'lbl_no_reason'        => 'no reason given',
    'hint_replace_reason'  => 'Saving a new reason replaces it.',

    // ── Form ──
    'lbl_quick_reasons'    => 'Quick reasons',
    'lbl_reason'           => 'Reason',
    'ph_reason'            => 'Explain why this torrent is being nuked (min. 6 characters)',
    'hint_min_chars'       => 'Min. 6 characters',

    // ── Quick reasons ──
    'opt_bad_quality'      => 'Bad Quality',
    'opt_bad_rip'          => 'Bad Rip',
    'opt_duplicate'        => 'Duplicate Torrent',
    'opt_fake'             => 'Fake Content',
    'opt_wrong_category'   => 'Wrong Category',
    'opt_broken_files'     => 'Missing / Broken Files',

    // ── Buttons ──
    'btn_back'             => 'Back to torrent',
    'btn_unnuke'           => 'Unnuke',
    'btn_update'           => 'Update reason',
    'btn_nuke'             => 'Nuke torrent',

    // ── Errors / messages ──
    'err_title'            => 'Error',
    'err_invalid_torrent'  => 'Invalid torrent!',
    'err_csrf'             => 'Invalid security token or request method. Please use the form.',
    'err_reason_short'     => 'Reason too short! Min. 6 characters.',
    'flash_nuked'          => 'The torrent has been nuked!',
    'flash_unnuked'        => 'The torrent has been unnuked!',

    // ── JS ──
    'js_unnuke_title'      => 'Remove the nuke?',
    'js_unnuke_text'       => 'The torrent will be shown as normal again and the reason will be cleared.',
    'js_unnuke_confirm'    => 'Yes, unnuke',
    'js_cancel'            => 'Cancel',
);
