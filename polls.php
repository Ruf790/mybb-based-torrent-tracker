<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'polls.php');
define("SCRIPTNAME", "polls.php");
define('IN_FORUM', true);

require_once 'global.php';
$lang->load("polls");
require_once INC_PATH . "/functions_post.php";

$plugins->run_hooks("polls_start");

// ─── Helpers ──────────────────────────────────────────────────────────────────

if (!function_exists('ags_fmt')) {
    /**
     * Substitute {1}, {2}… (and %1$s, %2$s… produced by $lang->load()) placeholders.
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

/**
 * Lang strings of this page, HTML-escaped for use inside heredoc templates.
 *
 * @return array<string,string>
 */
function poll_lang_esc(): array
{
    global $lang;
    return array_map('htmlspecialchars_uni', $lang->polls);
}

/**
 * js_* keys of this page without the prefix, for const AGS_LANG.
 *
 * @return array<string,string>
 */
function poll_js_lang(): array
{
    global $lang;
    $out = [];
    foreach ($lang->polls as $key => $value) {
        if (str_starts_with($key, 'js_')) {
            $out[substr($key, 3)] = $value;
        }
    }
    return $out;
}

/**
 * Print AGS_LANG + page scripts (call after stdhead()).
 */
function poll_print_assets(): void
{
    global $BASEURL;
    $json = json_encode(
        poll_js_lang(),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">
<script>const AGS_LANG = ' . $json . ';</script>
<script src="' . $BASEURL . '/scripts/Sortable.min.js"></script>
<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>
<script src="' . $BASEURL . '/scripts/polls.js?ver=2"></script>';
}

/**
 * Unified AJAX / redirect responder.
 */
function poll_respond(bool $is_ajax, string $url, string $msg = '', bool $success = true): void
{
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status'   => $success ? 'success' : 'error',
            'message'  => $msg,
            'redirect' => $url,
        ]);
        exit;
    }
    redirect($url, $msg);
}

/**
 * Unified error response.
 */
function poll_error(bool $is_ajax, string $msg): void
{
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit;
    }
    stderr($msg);
}

/**
 * Validate and sanitise poll options array.
 * Returns clean string values or calls poll_error().
 *
 * @return string[]
 */
function poll_validate_options(array $raw, bool $is_ajax, int $max = 10): array
{
    global $lang;

    $min_options = 2;
    $max_length  = 250;

    $clean = [];
    foreach ($raw as $opt) {
        $opt = trim($opt);
        if ($opt === '') {
            continue;
        }
        if (strpos($opt, '||~|~||') !== false) {
            poll_error($is_ajax, $lang->polls['err_option_sequence']);
        }
        if (my_strlen($opt) > $max_length) {
            poll_error($is_ajax, ags_fmt($lang->polls['err_option_too_long'], $max_length));
        }
        $clean[] = $opt;
    }
    if (count($clean) < $min_options) {
        poll_error($is_ajax, ags_fmt($lang->polls['err_min_options'], $min_options));
    }
    if (count($clean) > $max) {
        poll_error($is_ajax, ags_fmt($lang->polls['err_max_options_count'], $max));
    }
    return $clean;
}

/**
 * Build the "votes" string (all zeros) for a new set of options.
 */
function poll_zero_votes(int $count): string
{
    return implode('||~|~||', array_fill(0, $count, 0));
}

/**
 * Parse postoptions checkboxes.
 *
 * @return array{multiple:int, public:int, closed:int}
 */
function poll_parse_postoptions(array $raw): array
{
    return [
        'multiple' => !empty($raw['multiple']) ? 1 : 0,
        'public'   => !empty($raw['public'])   ? 1 : 0,
        'closed'   => !empty($raw['closed'])   ? 1 : 0,
    ];
}

/**
 * Load poll or die.
 */
function poll_get_or_die(int $pid, bool $is_ajax): array
{
    global $db, $lang;
    $query = $db->sql_query_prepared(
        "SELECT * FROM polls WHERE pid = ?",
        [$pid]
    );
    $poll  = $db->fetch_array($query);
    if (!$poll) {
        poll_error($is_ajax, $lang->polls['err_invalid_poll']);
    }
    return $poll;
}

/**
 * Load thread or die.
 */
function poll_get_thread_or_die(int $tid, bool $is_ajax): array
{
    global $lang;
    $thread = get_thread($tid);
    if (!$thread) {
        poll_error($is_ajax, $lang->polls['err_invalid_thread']);
    }
    return $thread;
}

// ─── Router ───────────────────────────────────────────────────────────────────

$mybb->input['action'] = $mybb->get_input('action');

// "updateoptions" button re-uses the same action with a flag
if (!empty($mybb->input['updateoptions'])) {
    $mybb->input['action'] = ($mybb->input['action'] === 'do_editpoll') ? 'editpoll' : 'newpoll';
}

$action   = $mybb->input['action'];
$is_ajax  = ($mybb->get_input('ajax') == 1);
$is_post  = ($mybb->request_method === 'post');
$is_staff = is_mod($usergroups ?? []);

// ─── ACTION: newpoll (show form) ──────────────────────────────────────────────

if ($action === 'newpoll') {

    $plugins->run_hooks('polls_newpoll_start');

    $tid    = $mybb->get_input('tid', MyBB::INPUT_INT);
    $thread = get_thread($tid);
    if (!$thread || $thread['visible'] == -1) {
        stderr($lang->polls['err_invalid_thread']);
    }

    $fid   = $thread['fid'];
    $forum = get_forum($fid);
    if (!$forum) {
        stderr($lang->polls['err_invalid_forum']);
    }
    if ($forum['open'] == 0 && !$is_staff) {
        stderr($lang->polls['err_forum_closed']);
    }
    if ($thread['poll']) {
        stderr($lang->polls['err_poll_exists']);
    }

    $polltimelimit  = 12;
    $maxpolloptions = 10;

    if ($thread['dateline'] < (TIMENOW - $polltimelimit * 3600) && $polltimelimit && !$is_staff) {
        stderr(ags_fmt($lang->polls['err_poll_time_limit'], $polltimelimit));
    }

    build_forum_breadcrumb($fid);
    add_breadcrumb(htmlspecialchars_uni($thread['subject']), get_thread_link($thread['tid']));
    add_breadcrumb($lang->polls['nav_postpoll']);

    $plugins->run_hooks('polls_newpoll_end');

    stdhead($lang->polls['title_newpoll']);
    poll_print_assets();

    build_breadcrumb();
    $polloptions = max(2, min($maxpolloptions, $mybb->get_input('polloptions', MyBB::INPUT_INT) ?: 2));
    echo poll_render_new_form($tid, $mybb->post_code, $polloptions);
    stdfoot();
    exit;
}

// ─── ACTION: do_newpoll (save new poll) ───────────────────────────────────────

if ($action === 'do_newpoll' && $is_post) {

    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('polls_do_newpoll_start');

    $tid    = $mybb->get_input('tid', MyBB::INPUT_INT);
    $thread = poll_get_thread_or_die($tid, $is_ajax);
    $forum  = get_forum($thread['fid']);
    if (!$forum) {
        poll_error($is_ajax, $lang->polls['err_invalid_forum']);
    }
    if ($thread['poll']) {
        poll_error($is_ajax, $lang->polls['err_poll_exists']);
    }

    $question = trim($mybb->get_input('question'));
    if ($question === '') {
        poll_error($is_ajax, $lang->polls['err_question_required']);
    }

    $clean_options = poll_validate_options(
        $mybb->get_input('options', MyBB::INPUT_ARRAY),
        $is_ajax
    );
    $opts = poll_parse_postoptions($mybb->get_input('postoptions', MyBB::INPUT_ARRAY));

    $maxoptions = $mybb->get_input('maxoptions', MyBB::INPUT_INT);
    $timeout    = max(0, $mybb->get_input('timeout', MyBB::INPUT_INT));

    $count = count($clean_options);
    $newpoll = [
        'tid'        => $thread['tid'],
        'question'   => $question,
        'dateline'   => TIMENOW,
        'options'    => implode('||~|~||', $clean_options),
        'votes'      => poll_zero_votes($count),
        'numoptions' => $count,
        'numvotes'   => 0,
        'timeout'    => $timeout,
        'closed'     => 0,
        'multiple'   => $opts['multiple'],
        'public'     => $opts['public'],
        'maxoptions' => ($opts['multiple'] && $maxoptions > 0 && $maxoptions < $count) ? $maxoptions : 0,
    ];

    $plugins->run_hooks('polls_do_newpoll_process');

    $columns      = array_keys($newpoll);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO polls (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
        array_values($newpoll)
    );
    $pid = $db->insert_id();

    $db->sql_query_prepared(
        "UPDATE threads SET poll = ? WHERE tid = ?",
        [$pid, $thread['tid']]
    );

    $plugins->run_hooks('polls_do_newpoll_end');

    poll_respond($is_ajax, get_thread_link($thread['tid']), $lang->polls['flash_poll_created']);
}

// ─── ACTION: editpoll (show edit form) ────────────────────────────────────────

if ($action === 'editpoll') {

    $plugins->run_hooks('polls_editpoll_start');

    $pid    = $mybb->get_input('pid', MyBB::INPUT_INT);
    $poll   = poll_get_or_die($pid, false);

    $query  = $db->sql_query_prepared(
        "SELECT * FROM threads WHERE poll = ?",
        [$pid]
    );
    $thread = $db->fetch_array($query);
    if (!$thread) {
        stderr($lang->polls['err_invalid_thread']);
    }

    $fid   = $thread['fid'];
    $forum = get_forum($fid);
    if (!$forum) {
        stderr($lang->polls['err_invalid_forum']);
    }
    if ($forum['open'] == 0 && !$is_staff) {
        stderr($lang->polls['err_forum_closed']);
    }

    build_forum_breadcrumb($fid);
    add_breadcrumb(htmlspecialchars_uni($thread['subject']), get_thread_link($thread['tid']));
    add_breadcrumb($lang->polls['nav_editpoll']);

    $plugins->run_hooks('polls_editpoll_end');

    stdhead($lang->polls['title_editpoll']);
    poll_print_assets();

    build_breadcrumb();
    echo poll_render_edit_form($poll, $mybb->post_code, $lang);
    stdfoot();
    exit;
}

// ─── ACTION: do_editpoll (save edits) ─────────────────────────────────────────

if ($action === 'do_editpoll' && $is_post) {

    verify_post_check($mybb->get_input('my_post_key'));

    $pid  = $mybb->get_input('pid', MyBB::INPUT_INT);
    $poll = poll_get_or_die($pid, $is_ajax);

    $question = trim($mybb->input['question'] ?? '');
    if ($question === '') {
        poll_error($is_ajax, $lang->polls['err_question_required']);
    }

    $raw_options = $mybb->get_input('options', MyBB::INPUT_ARRAY);
    $raw_votes   = $mybb->get_input('votes',   MyBB::INPUT_ARRAY);

    $clean_options = poll_validate_options($raw_options, $is_ajax);

    // Re-map votes to match filtered options (skip empty slots)
    $clean_votes = [];
    $vote_keys   = array_keys(array_filter(
        array_map('trim', $raw_options),
        fn($v) => $v !== ''
    ));
    foreach ($vote_keys as $k) {
        $clean_votes[] = max(0, (int)($raw_votes[$k] ?? 0));
    }

    $opts       = poll_parse_postoptions($mybb->get_input('postoptions', MyBB::INPUT_ARRAY));
    $timeout    = max(0, $mybb->get_input('timeout',    MyBB::INPUT_INT));
    $maxoptions = max(0, $mybb->get_input('maxoptions', MyBB::INPUT_INT));
    $count      = count($clean_options);

    $db->sql_query_prepared(
        "UPDATE polls
         SET question = ?, options = ?, votes = ?, numoptions = ?, multiple = ?, public = ?, closed = ?, timeout = ?, maxoptions = ?
         WHERE pid = ?",
        [
            $question,
            implode('||~|~||', $clean_options),
            implode('||~|~||', $clean_votes),
            $count,
            $opts['multiple'],
            $opts['public'],
            $opts['closed'],
            $timeout,
            $maxoptions,
            $pid,
        ]
    );

    $thread = poll_get_thread_or_die($poll['tid'], $is_ajax);
    poll_respond($is_ajax, get_thread_link($thread['tid']), $lang->polls['flash_poll_updated']);
}

// ─── ACTION: showresults ──────────────────────────────────────────────────────

if ($action === 'showresults') {

    $pid  = $mybb->get_input('pid', MyBB::INPUT_INT);
    $poll = poll_get_or_die($pid, false);

    $thread = get_thread($poll['tid']);
    if (!$thread || $thread['visible'] != 1) {
        stderr($lang->polls['err_invalid_thread']);
    }

    $fid              = $thread['fid'];
    $forum            = get_forum($fid);
    $forumpermissions = forum_permissions($fid);

    if (!$forum) {
        stderr($lang->polls['err_invalid_forum']);
    }
    if ($forumpermissions['canviewthreads'] == 0 || $forumpermissions['canview'] == 0) {
        print_no_permission();
    }

    $plugins->run_hooks('polls_showresults_start');

    build_forum_breadcrumb($fid);
    add_breadcrumb(htmlspecialchars_uni($thread['subject']), get_thread_link($thread['tid']));
    add_breadcrumb($lang->polls['nav_pollresults']);

    // Gather voter data
    $voters = $votedfor = $guest_voters = [];
    $query  = $db->sql_query_prepared(
        "SELECT v.*, u.username
         FROM pollvotes v
         LEFT JOIN users u ON (u.id = v.uid)
         WHERE v.pid = ?
         ORDER BY u.username",
        [$poll['pid']]
    );
    while ($voter = $db->fetch_array($query)) {
        $cur_uid = (int)($CURUSER['id'] ?? 0);
        if ($cur_uid && $cur_uid == $voter['uid']) {
            $votedfor[$voter['voteoption']] = 1;
        }
        if (empty($voter['uid']) || $voter['username'] === '') {
            $guest_voters[$voter['voteoption']] = ($guest_voters[$voter['voteoption']] ?? 0) + 1;
        } else {
            $voters[$voter['voteoption']][$voter['uid']] = htmlspecialchars_uni($voter['username']);
        }
    }

    $plugins->run_hooks('polls_showresults_end');

    $poll['question'] = htmlspecialchars_uni($poll['question']);

    stdhead($lang->polls['title_results']);

    echo poll_render_results($poll, $voters, $guest_voters, $votedfor, $fid, $lang, $parser);
    stdfoot();
    exit;
}

// ─── ACTION: vote ─────────────────────────────────────────────────────────────

if ($action === 'vote' && $is_post) {

    verify_post_check($mybb->get_input('my_post_key'));

    $pid  = $mybb->get_input('pid');
    $poll = poll_get_or_die((int)$pid, false);

    $plugins->run_hooks('polls_vote_start');

    $thread = get_thread($poll['tid']);
    if (!$thread || $thread['visible'] != 1) {
        stderr($lang->polls['err_invalid_thread']);
    }

    $fid              = $thread['fid'];
    $forumpermissions = forum_permissions($fid);
    $forum            = get_forum($fid);

    if ($forumpermissions['canvotepolls'] == 0) {
        print_no_permission();
    }
    if (!$forum || $forum['open'] == 0) {
        stderr($lang->polls['err_forum_closed']);
    }

    $expire = $poll['dateline'] + $poll['timeout'] * 86400;
    if ($poll['closed'] == 1 || $thread['closed'] == 1 || ($poll['timeout'] && $expire < TIMENOW)) {
        stderr($lang->polls['err_poll_closed']);
    }

    if (!isset($mybb->input['option'])) {
        stderr($lang->polls['err_no_option']);
    }

    // Duplicate-vote check
    $cur_uid = (int)($CURUSER['id'] ?? 0);
    if ($cur_uid) {
        $uid_condition = "uid = ?";
        $uid_params    = [$cur_uid];
    } else {
        $uid_condition = "ipaddress = ?";
        $uid_params    = [$session->packedip];
    }

    $votecheck = $db->fetch_array($db->sql_query_prepared(
        "SELECT * FROM pollvotes WHERE {$uid_condition} AND pid = ?",
        [...$uid_params, $poll['pid']]
    ));
    if ($votecheck) {
        stderr($lang->polls['err_already_voted']);
    }

    $votesarray = explode('||~|~||', $poll['votes']);
    $option     = $mybb->input['option'];
    $numvotes   = (int)$poll['numvotes'];
    $votesql    = [];

    if ($poll['multiple'] == 1) {
        if (!is_array($option)) {
            stderr($lang->polls['err_no_option']);
        }
        $total = 0;
        foreach ($option as $voteoption => $vote) {
            if ($vote == 1 && isset($votesarray[$voteoption - 1])) {
                $votesql[] = [
                    'pid'        => $poll['pid'],
                    'uid'        => $cur_uid,
                    'voteoption' => $voteoption,
                    'dateline'   => TIMENOW,
                    'ipaddress'  => $session->packedip,
                ];
                $votesarray[$voteoption - 1]++;
                $numvotes++;
                $total++;
            }
        }
        if ($poll['maxoptions'] > 0 && $total > $poll['maxoptions']) {
            stderr(ags_fmt($lang->polls['err_max_votes'], (int)$poll['maxoptions']));
        }
    } else {
        if (is_array($option) || !isset($votesarray[$option - 1])) {
            stderr($lang->polls['err_no_option']);
        }
        $votesql = [
            'pid'        => $poll['pid'],
            'uid'        => $cur_uid,
            'voteoption' => $option,
            'dateline'   => TIMENOW,
            'ipaddress'  => $session->packedip,
        ];
        $votesarray[$option - 1]++;
        $numvotes++;
    }

    if (!$votesql) {
        stderr($lang->polls['err_no_option']);
    }

    $plugins->run_hooks('polls_vote_process');

    $vote_rows = $poll['multiple'] == 1 ? $votesql : [$votesql];
    foreach ($vote_rows as $row) {
        $db->sql_query_prepared(
            "INSERT INTO pollvotes (pid, uid, voteoption, dateline, ipaddress) VALUES (?, ?, ?, ?, ?)",
            [$row['pid'], $row['uid'], $row['voteoption'], $row['dateline'], $row['ipaddress']]
        );
    }

    $voteslist = implode('||~|~||', $votesarray);
    $db->sql_query_prepared(
        "UPDATE polls SET votes = ?, numvotes = ? WHERE pid = ?",
        [$voteslist, $numvotes, $poll['pid']]
    );

    $plugins->run_hooks('polls_vote_end');

    redirect(get_thread_link($poll['tid']), $lang->polls['flash_vote_thanks']);
}

// ─── ACTION: do_undovote ──────────────────────────────────────────────────────

if ($action === 'do_undovote') {

    verify_post_check($mybb->get_input('my_post_key'));

    $pid  = $mybb->get_input('pid', MyBB::INPUT_INT);
    $poll = poll_get_or_die($pid, false);

    $plugins->run_hooks('polls_do_undovote_start');

    $thread = get_thread($poll['tid']);
    if (!$thread || $thread['visible'] != 1) {
        stderr($lang->polls['err_invalid_thread']);
    }

    $fid   = $thread['fid'];
    $forum = get_forum($fid);
    if (!$forum || $forum['open'] == 0) {
        stderr($lang->polls['err_forum_closed']);
    }

    $expire = $poll['dateline'] + $poll['timeout'] * 86400;
    if ($poll['closed'] == 1 || $thread['closed'] == 1 || ($poll['timeout'] && $expire < TIMENOW)) {
        stderr($lang->polls['err_poll_closed']);
    }

    $cur_uid = (int)($CURUSER['id'] ?? 0);
    if ($cur_uid) {
        $uid_condition = "uid = ?";
        $uid_params    = [$cur_uid];
    } else {
        $uid_condition = "uid = 0 AND ipaddress = ?";
        $uid_params    = [$session->packedip];
    }

    $vote_options = [];
    $query = $db->sql_query_prepared(
        "SELECT vid, voteoption FROM pollvotes WHERE {$uid_condition} AND pid = ?",
        [...$uid_params, $poll['pid']]
    );
    while ($row = $db->fetch_array($query)) {
        $vote_options[$row['vid']] = $row['voteoption'];
    }
    if (empty($vote_options)) {
        stderr($lang->polls['err_not_voted']);
    }

    $votesarray = array_slice(explode('||~|~||', $poll['votes']), 0, $poll['numoptions']);
    $numvotes   = (int)$poll['numvotes'];

    if ($poll['multiple'] == 1) {
        foreach ($vote_options as $vote) {
            if (isset($votesarray[$vote - 1])) {
                $votesarray[$vote - 1]--;
                $numvotes--;
            }
        }
    } else {
        $vote = reset($vote_options);
        if (isset($votesarray[$vote - 1])) {
            $votesarray[$vote - 1]--;
            $numvotes--;
        }
    }

    // Clamp negatives
    $numvotes   = max(0, $numvotes);
    $votesarray = array_map(fn($v) => max(0, $v), $votesarray);

    $plugins->run_hooks('polls_do_undovote_process');

    $db->sql_query_prepared(
        "DELETE FROM pollvotes WHERE {$uid_condition} AND pid = ?",
        [...$uid_params, $poll['pid']]
    );
    $db->sql_query_prepared(
        "UPDATE polls SET votes = ?, numvotes = ? WHERE pid = ?",
        [implode('||~|~||', $votesarray), $numvotes, $poll['pid']]
    );

    $plugins->run_hooks('polls_do_undovote_end');

    redirect(get_thread_link($poll['tid']), $lang->polls['flash_unvoted']);
}

// ─── Template functions ───────────────────────────────────────────────────────

function poll_render_new_form(int $tid, string $post_code, int $polloptions = 2): string
{
    $L = poll_lang_esc();

    $count_js = '<script>window.__POLL_COUNT__ = ' . $polloptions . ';</script>';
    return $count_js . <<<HTML
<div class="container py-4">
<div class="card shadow border-0 rounded-4">
    <div class="card-header bg-dark text-white fw-bold">
        <i class="fa-solid fa-chart-simple me-2"></i> {$L['sec_create_poll']}
    </div>
    <div class="card-body">
        <form id="pollForm">
            <input type="hidden" name="my_post_key" value="{$post_code}" />
            <input type="hidden" name="action"      value="do_newpoll" />
            <input type="hidden" name="tid"         value="{$tid}" />
            <input type="hidden" name="ajax"        value="1" />

            <div class="mb-4">
                <label class="form-label fw-semibold">{$L['lbl_question']}</label>
                <input type="text" name="question" class="form-control form-control-lg" required
                       placeholder="{$L['ph_question']}" />
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="fw-semibold">{$L['lbl_answer_options']}</label>
                    <button type="button" id="addOption" class="btn btn-sm btn-primary">
                        <i class="fa fa-plus"></i> {$L['btn_add_option']}
                    </button>
                </div>
                <div id="optionsList"></div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="multiVote" name="postoptions[multiple]" value="1">
                    <label class="form-check-label">{$L['lbl_multiple']}</label>
                </div>
                <div id="maxOptionsBlock" class="mt-2" style="display:none;">
                    <input type="number" name="maxoptions" class="form-control w-auto"
                           placeholder="{$L['ph_maxoptions']}" title="{$L['hint_maxoptions']}" aria-label="{$L['hint_maxoptions']}">
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="postoptions[public]" value="1">
                    <label class="form-check-label">{$L['lbl_public']}</label>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">{$L['lbl_timeout']}</label>
                <input type="number" name="timeout" class="form-control w-auto" value="0" title="{$L['hint_timeout']}">
            </div>

            <div class="mb-4">
                <label class="fw-semibold">{$L['lbl_preview']}</label>
                <div id="previewBox" class="border rounded p-3 bg-light small">{$L['txt_no_options']}</div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-success px-4">
                    <i class="fa fa-check"></i> {$L['btn_create_poll']}
                </button>
            </div>
        </form>
    </div>
</div>
</div>
HTML;
}

function poll_render_edit_form(array $poll, string $post_code, object $lang): string
{
    $L = poll_lang_esc();

    $pid      = (int)$poll['pid'];
    $question = htmlspecialchars_uni($poll['question']);
    $timeout  = (int)$poll['timeout'];
    $maxopts  = (int)$poll['maxoptions'];

    $chk_multiple = $poll['multiple'] ? 'checked' : '';
    $chk_public   = $poll['public']   ? 'checked' : '';
    $chk_closed   = $poll['closed']   ? 'checked' : '';

    // Build initial option rows as JSON for JS.
    // Raw text: JS assigns it via input.value, json_encode flags make it safe inside <script>.
    $options_arr = explode('||~|~||', $poll['options']);
    $votes_arr   = explode('||~|~||', $poll['votes']);
    $initial     = [];
    foreach ($options_arr as $i => $opt) {
        $initial[] = [
            'text'  => $opt,
            'votes' => (int)($votes_arr[$i] ?? 0),
        ];
    }
    $initial_json = json_encode(
        $initial,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );

    return <<<HTML
<div class="container py-4">
<div class="card shadow border-0 rounded-4">
    <div class="card-header bg-dark text-white fw-bold">
        <i class="fa-solid fa-pen me-2"></i> {$L['sec_edit_poll']}
    </div>
    <div class="card-body">
        <form id="pollForm">
            <input type="hidden" name="my_post_key" value="{$post_code}" />
            <input type="hidden" name="action"      value="do_editpoll" />
            <input type="hidden" name="pid"         value="{$pid}" />
            <input type="hidden" name="ajax"        value="1" />

            <div class="mb-4">
                <label class="form-label fw-semibold">{$L['lbl_question']}</label>
                <input type="text" name="question" class="form-control form-control-lg" value="{$question}">
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="fw-semibold">{$L['lbl_options']}</label>
                    <button type="button" id="addOption" class="btn btn-sm btn-primary">
                        <i class="fa fa-plus"></i> {$L['btn_add_option']}
                    </button>
                </div>
                <div id="optionsList"></div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="multiVote"
                           name="postoptions[multiple]" value="1" {$chk_multiple}>
                    <label class="form-check-label">{$L['lbl_multiple']}</label>
                </div>
                <div id="maxOptionsBlock" class="mt-2" style="display:none;">
                    <input type="number" name="maxoptions" class="form-control w-auto" value="{$maxopts}"
                           placeholder="{$L['ph_maxoptions']}" title="{$L['hint_maxoptions']}" aria-label="{$L['hint_maxoptions']}">
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="postoptions[public]" value="1" {$chk_public}>
                    <label class="form-check-label">{$L['lbl_public']}</label>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="postoptions[closed]" value="1" {$chk_closed}>
                    <label class="form-check-label">{$L['lbl_closed']}</label>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">{$L['lbl_timeout']}</label>
                <input type="number" name="timeout" class="form-control w-auto" value="{$timeout}" title="{$L['hint_timeout']}">
            </div>

            <div class="mb-4">
                <label class="fw-semibold">{$L['lbl_preview']}</label>
                <div id="previewBox" class="border rounded p-3 bg-light small">{$L['txt_no_options']}</div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-success px-4">
                    <i class="fa fa-check"></i> {$L['btn_update_poll']}
                </button>
            </div>
        </form>
    </div>
</div>
</div>
<script>window.__POLL_INITIAL__ = {$initial_json};</script>
HTML;
}

function poll_render_results(
    array $poll, array $voters, array $guest_voters, array $votedfor,
    int $fid, object $lang, object $parser
): string {
    $L = poll_lang_esc();

    $options_arr = explode('||~|~||', $poll['options']);
    $votes_arr   = explode('||~|~||', $poll['votes']);
    $totvotes    = array_sum($votes_arr);

    $rows = '';
    foreach ($options_arr as $i => $option_raw) {
        $num    = $i + 1;
        $votes  = (int)($votes_arr[$i] ?? 0);
        $option = $parser->parse_message($option_raw, [
            'allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1,
            'allow_imgcode' => 1, 'allow_videocode' => 1, 'filter_badwords' => 1,
        ]);
        $pct    = $totvotes > 0 ? number_format($votes / $totvotes * 100, 2) : 0;
        $star   = !empty($votedfor[$num]) ? ' <span title="' . $L['tip_your_vote'] . '">*</span>' : '';

        // Build voter list
        $userlist = '';
        //if ($poll['public'] == 1 || is_mod($usergroups)) {
            $comma = '';
            if (!empty($voters[$num]) && is_array($voters[$num])) {
                foreach ($voters[$num] as $uid => $uname) {
                    $userlist .= $comma . build_profile_link($uname, $uid);
                    $comma     = $lang->global['comma'];
                }
            }
            if (!empty($guest_voters[$num])) {
                $gc        = (int)$guest_voters[$num];
                $userlist .= ($comma ?: '') . ($gc === 1 ? $L['lbl_guest_one'] : ags_fmt($L['lbl_guest_many'], $gc));
            }
        //}

        $rows .= <<<HTML
<div class="row pt-3 border-top">
    <div class="col">{$option}{$star}</div>
    <div class="col-auto text-end">{$votes} ({$pct}%)</div>
</div>
<div class="row pb-3">
    <div class="col">
        <div class="progress mb-1" style="height:25px">
            <div class="progress-bar" role="progressbar" style="width:{$pct}%"
                 aria-valuenow="{$pct}" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="mt-1">{$userlist}</div>
    </div>
</div>
HTML;
    }

    $totpct = $totvotes ? '100%' : '0%';
    $total  = ags_fmt($L['lbl_total_votes'], (int)$poll['numvotes'], $totpct);
    return <<<HTML
<div class="container-md">
<div class="card mb-4">
    <div class="card-header bg-white fw-bold mt-2 pb-0">{$poll['question']}</div>
    <div class="card-body">{$rows}</div>
    <div class="card-footer text-center">
        {$total}
    </div>
</div>
</div>
HTML;
}