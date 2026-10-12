<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');
$language['task'] = array (
    // ── Page ──
    'pg_title'              => 'Tasks',
    'pane_tasks'            => 'Tasks',
    'hint_intro_full'       => "Meet the requirements before the time runs out to earn bonus points. If you don't make it, the penalty is deducted. Changed your mind? Abandon the task for the full penalty and claim another one. You can only have one task or exam in progress at a time.",
    'hint_intro_pct'        => "Meet the requirements before the time runs out to earn bonus points. If you don't make it, the penalty is deducted. Changed your mind? Abandon the task for {1}% of the penalty and claim another one. You can only have one task or exam in progress at a time.",

    // ── Labels ──
    'lbl_reg_min'           => 'on the tracker for at least {1} days',
    'lbl_reg_max'           => 'on the tracker for at most {1} days',
    'lbl_claimed_count'     => 'Claimed: {1} / {2}',
    'lbl_abandon_penalty'   => 'Abandon penalty: −{1}',
    'lbl_abandon_free'      => 'No penalty to abandon',

    // ── Buttons ──
    'btn_claim'             => 'Claim task',
    'btn_claimed'           => 'Claimed',
    'btn_abandon'           => 'Abandon',
    'btn_full'              => 'Full',

    // ── Empty states ──
    'msg_no_tasks'          => 'There are no tasks right now. Check back later.',
    'msg_lb_empty_last'     => 'No completed tasks yet',
    'msg_lb_empty_this'     => 'No completed tasks yet this month. Be the first!',
    'msg_lb_you_none'       => "You haven't completed any tasks this month yet.",

    // ── Leaderboard ──
    'sec_leaderboard'       => 'Leaderboard',
    'tab_this_month'        => 'This month',
    'tab_last_month'        => 'Last month',
    'lbl_lb_sub'            => 'Most tasks completed in {1} {2}.',
    'th_rank'               => '#',
    'th_user'               => 'User',
    'th_tasks'              => 'Tasks',
    'th_bonus'              => 'Bonus',

    // ── Month names ──
    'mon_1'                 => 'January',
    'mon_2'                 => 'February',
    'mon_3'                 => 'March',
    'mon_4'                 => 'April',
    'mon_5'                 => 'May',
    'mon_6'                 => 'June',
    'mon_7'                 => 'July',
    'mon_8'                 => 'August',
    'mon_9'                 => 'September',
    'mon_10'                => 'October',
    'mon_11'                => 'November',
    'mon_12'                => 'December',

    // ── Flash messages ──
    'flash_token_expired'   => 'Security token expired. Reload the page and try again.',
    'flash_abandoned'       => 'Task abandoned. You can claim another task now.',
    'flash_abandoned_penalty' => 'Task abandoned, {1} bonus points deducted. You can claim another task now.',
    'flash_claimed'         => 'Task claimed. Your progress is shown at the top of every page.',

    // ── JS strings ──
    'js_claim_q'            => 'Claim the task "{1}"?',
    'js_claim_penalty'      => 'If you do not complete it in time, {1} bonus points will be deducted.',
    'js_claim_btn'          => 'Claim',
    'js_cancel'             => 'Cancel',
    'js_abandon_q'          => 'Abandon the task "{1}"?',
    'js_abandon_pen'        => '{1} bonus points will be deducted.',
    'js_abandon_nopen'      => 'No bonus points will be deducted.',
    'js_abandon_done'       => 'All requirements are already met - you will lose the reward.',
    'js_abandon_after'      => 'You can claim another task right after.',
    'js_abandon_btn'        => 'Abandon',
);
?>
