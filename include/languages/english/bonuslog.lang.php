<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');
$language['bonuslog'] = array (
    // ── Page ──
    'page_title'      => 'Bonus history',
    'head_title'      => 'Bonus history',
    'head_desc'       => 'Every change of your bonus points: what for, how much and the balance after it.',
    'btn_my_bonuses'  => 'My bonuses',

    // ── KPI cards ──
    'lbl_balance_now' => 'Balance now',
    'lbl_received_30' => 'Received, 30 days',
    'lbl_spent_30'    => 'Spent, 30 days',

    // ── Tabs ──
    'tab_all'         => 'All',

    // ── Table ──
    'th_date'         => 'Date',
    'th_what_for'     => 'What for',
    'th_change'       => 'Change',
    'th_balance'      => 'Balance',
    'lbl_whole_day'   => '(whole day)',
    'empty_nothing'   => 'Nothing here yet.',

    // ── Types (keys = BONUS_LOG_TYPES) ──
    'type_seeding'    => 'Seeding',
    'type_shop'       => 'Shop',
    'type_gift'       => 'Gifts',
    'type_exam'       => 'Exams and tasks',
    'type_request'    => 'Requests',
    'type_claim'      => 'Claims',
    'type_staff'      => 'Staff',
    'type_kps'        => 'Activity',
    'type_other'      => 'Other',

    // ── Reasons written by the system ──
    'reason_seeding'  => 'Seeding',
    'reason_gift_to'   => 'Gift to {1}: {2} + fee {3}',
    'reason_gift_from' => 'Gift from {1}',
    'reason_req_new'             => 'Bounty for new request #{1}',
    'reason_req_added'           => 'Bounty added to request #{1}',
    'reason_req_filled'          => 'Bounty for filling request #{1}',
    'reason_req_refund'          => 'Bounty refund: request #{1} deleted',
    'reason_claim_give_up'       => 'Claim given up: torrent #{1}',
    'reason_claim_reached'       => 'Claims {1}: {2} torrent(s) reached the norm',
    'reason_claim_removed'       => 'Claims {1}: {2} torrent(s) removed, not seeded enough',
    'reason_task_done'           => 'Task completed: {1}',
    'reason_task_failed'         => 'Task failed: {1}',
    'reason_task_abandoned'      => 'Task abandoned: {1}',
    'reason_exam_passed'         => 'Exam passed: {1}',
    'reason_comment'             => 'Comment on “{1}” (#{2}, comment #{3})',
    'reason_comment_deleted'     => 'Comment deleted on torrent #{1} (comment #{2})',
    'reason_comment_deleted_mod' => 'Comment deleted by moderator {1} on torrent #{2} (comment #{3})',
    'reason_comments_removed'    => 'Comments removed with deleted torrent {1} (#{2}, {3} comment(s))',
    'reason_torrent_uploaded'    => 'Torrent uploaded: {1} (#{2})',
    'reason_torrent_deleted'     => 'Torrent deleted: {1} (#{2})',
    'reason_torrent_deleted_mod' => 'Torrent deleted by moderator {1}: {2} (#{3})',
    'reason_forum_thread'        => 'New forum thread: {1} (#{2})',
    'reason_forum_reply'         => 'Forum reply: {1} (thread #{2}, post #{3})',
    'reason_rated_torrent'       => 'Rated torrent #{1} ({2}/10)',
    'reason_rated_thread'        => 'Rated forum thread #{1} ({2}/10)',
    'reason_activity'            => 'Activity bonus',
    'reason_from_staff'          => 'From staff: {1}',
    'reason_from_staff_group'    => 'From staff ({1})',
    'reason_balance_set'         => 'Balance set by staff: {1} → {2}',
    'reason_balance_reset'       => 'Balance reset by staff ({1})',
    'reason_all_groups'          => 'all groups',
);
?>
