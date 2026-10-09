<?php
/**
 * Staff PM inbox (staffbox) — admin panel.
 *
 * CSS:  admin/templates/staffbox.css
 * JS:   admin/scripts/staffbox.js
 * Lang: languages/{english,russian}/staffbox.lang.php
 */

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

if (!defined('IN_MYBB')) {
    define('IN_MYBB', 1);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

define('SB_VERSION', '0.9');
define('SB_ASSET_VER', 2);

require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/datahandler.php';

$lang->load('staffbox');

$parser = new postParser;

// Staff messages are written by regular members: HTML must NOT be allowed here.
$parser_options = [
    'allow_html'      => 0,
    'allow_mycode'    => 1,
    'allow_smilies'   => 1,
    'allow_imgcode'   => 1,
    'allow_videocode' => 1,
    'filter_badwords' => 1,
];

$action  = (string)($_GET['action'] ?? '');
$sb_base = rtrim((string)$_this_script_, '&');
$url     = $sb_base . '&';

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

if (!function_exists('ags_fmt')) {
    /**
     * Substitute {1}, {2}… (and %1$s, %2$s… — $lang->load() converts {N} into %N$s).
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

/** Escape a lang string for an HTML attribute. */
function sb_e(string $text): string
{
    return htmlspecialchars_uni($text);
}

function sb_url(string $query = ''): string
{
    global $url;
    return $url . $query;
}

function sb_redirect(string $query = ''): never
{
    header('Location: ' . sb_url($query));
    exit;
}

function sb_csrf_ok(): bool
{
    return (bool)verify_post_check((string)($_POST['my_post_key'] ?? ''), true);
}

function sb_csrf_field(): string
{
    global $mybb;
    return '<input type="hidden" name="my_post_key" value="' . htmlspecialchars_uni((string)$mybb->post_code) . '">';
}

/** @return int[] */
function sb_post_ids(): array
{
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $ids = array_values(array_unique(array_filter($ids, static fn(int $v): bool => $v > 0)));
    return array_slice($ids, 0, 500);
}

function sb_user_link(mixed $id, mixed $name, mixed $group): string
{
    global $BASEURL, $lang;

    $id = (int)$id;
    if ($id <= 0) {
        return '<span class="sb-user is-system"><i class="fa-solid fa-robot"></i>' . $lang->staffbox['lbl_system'] . '</span>';
    }
    if ($name === null || $name === '') {
        return '<span class="sb-user is-deleted"><i class="fa-solid fa-user-slash"></i>' . $lang->staffbox['lbl_deleted_user'] . '</span>';
    }

    return '<a class="sb-user" href="' . $BASEURL . '/' . get_profile_link($id) . '">'
        . format_name(htmlspecialchars_uni((string)$name), (int)$group) . '</a>';
}

function sb_status(bool $answered): string
{
    global $lang;

    return $answered
        ? '<span class="sb-pill sb-tone-success"><i class="fa-solid fa-circle-check"></i>' . $lang->staffbox['lbl_status_answered'] . '</span>'
        : '<span class="sb-pill sb-tone-warning"><i class="fa-solid fa-hourglass-half"></i>' . $lang->staffbox['lbl_status_waiting'] . '</span>';
}

function sb_snippet(string $text): string
{
    $text = preg_replace('~\[/?[a-z*]+(?:=[^\]]*)?\]~i', ' ', $text) ?? $text;
    $text = trim(preg_replace('~\s+~u', ' ', $text) ?? $text);
    return htmlspecialchars_uni(mb_strimwidth($text, 0, 120, '…', 'UTF-8'));
}

function sb_time(mixed $ts): string
{
    $ts = (int)$ts;
    return '<span class="sb-time" data-bs-toggle="tooltip" title="' . date('d.m.Y H:i', $ts) . '">'
        . my_datee('relative', $ts) . '</span>';
}

/** JS strings: js_* keys from the lang, passed without the prefix. */
function sb_js_lang(): string
{
    global $lang;

    $arr = [];
    foreach ((array)$lang->staffbox as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) {
            $arr[substr((string)$k, 3)] = (string)$v;
        }
    }

    return '<script>const AGS_LANG = '
        . json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ';</script>';
}

function sb_assets(): void
{
    global $BASEURL;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $v = SB_ASSET_VER;

    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">' . "\n"
        . '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/staffbox.css?ver=' . $v . '">' . "\n"
        . sb_js_lang() . "\n"
        . '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js" defer></script>' . "\n"
        . '<script src="' . $BASEURL . '/admin/scripts/staffbox.js?ver=' . $v . '" defer></script>' . "\n";
}

function sb_flash(): string
{
    global $lang;

    $L   = $lang->staffbox;
    $msg = (string)($_GET['msg'] ?? '');
    $n   = max(0, (int)($_GET['n'] ?? 0));

    [$tone, $icon, $text] = match ($msg) {
        'deleted'  => ['success', 'fa-trash-can',            $n === 1 ? $L['flash_deleted_one'] : ags_fmt($L['flash_deleted_many'], $n)],
        'answered' => ['success', 'fa-circle-check',         $n === 0 ? $L['flash_answered_none'] : ($n === 1 ? $L['flash_answered_one'] : ags_fmt($L['flash_answered_many'], $n))],
        'sent'     => ['success', 'fa-paper-plane',          $L['flash_sent']],
        'none'     => ['info',    'fa-circle-info',          $L['flash_none']],
        'csrf'     => ['danger',  'fa-triangle-exclamation', $L['flash_csrf']],
        default    => [null, null, null],
    };

    if ($tone === null) {
        return '';
    }

    return '<div class="sb-flash sb-tone-' . $tone . '" role="status">'
        . '<i class="fa-solid ' . $icon . '"></i><span>' . $text . '</span>'
        . '<button type="button" class="sb-flash-close" data-sb-dismiss aria-label="' . sb_e($L['aria_close']) . '"><i class="fa-solid fa-xmark"></i></button>'
        . '</div>';
}

function sb_header(string $icon, string $tone, string $title, string $subtitle, string $actions = ''): string
{
    return '<div class="sb-card sb-head">'
        . '<span class="sb-ico sb-ico-lg sb-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<div class="sb-head-text"><h1 class="sb-title">' . $title . '</h1><div class="sb-subtitle">' . $subtitle . '</div></div>'
        . ($actions !== '' ? '<div class="sb-head-actions">' . $actions . '</div>' : '')
        . '</div>';
}

/* ------------------------------------------------------------------ */
/*  Legacy GET links: never change data on GET                         */
/* ------------------------------------------------------------------ */

if ($action === 'deletestaffmessage' || $action === 'setanswered') {
    $id = (int)($_GET['id'] ?? 0);
    sb_redirect($id > 0 ? 'action=viewpm&pmid=' . $id : '');
}
if ($action === 'viewanswer') {
    sb_redirect('action=viewpm&pmid=' . (int)($_GET['pmid'] ?? 0) . '#answer');
}
if ($action === 'takecontactanswered') {
    sb_redirect();
}

/* ------------------------------------------------------------------ */
/*  POST actions (list + view page) — Post/Redirect/Get               */
/* ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'answermessage') {
    if (!sb_csrf_ok()) {
        sb_redirect('msg=csrf');
    }

    $uid   = (int)$CURUSER['id'];
    $uname = (string)$CURUSER['username'];

    // Keep filter/page after bulk actions
    $keepFilter = (string)($_POST['filter'] ?? 'all');
    $keep  = in_array($keepFilter, ['open', 'done'], true) ? 'filter=' . $keepFilter . '&' : '';
    $keepPage = (int)($_POST['page'] ?? 1);
    $keep .= $keepPage > 1 ? 'page=' . $keepPage . '&' : '';

    // Single delete (list row or view page)
    if (!empty($_POST['delete_one'])) {
        $id = (int)$_POST['delete_one'];
        $n  = 0;
        if ($id > 0) {
            $db->sql_query_prepared('DELETE FROM staffmessages WHERE id = ?', [$id]);
            $n = (int)$db->affected_rows();
            if ($n > 0) {
                write_log('Staff PM #' . $id . ' was deleted by ' . $uname);
            }
        }
        sb_redirect($keep . 'msg=deleted&n=' . $n);
    }

    // Single "mark answered" (view page)
    if (!empty($_POST['answer_one'])) {
        $id = (int)$_POST['answer_one'];
        $n  = 0;
        if ($id > 0) {
            $db->sql_query_prepared('UPDATE staffmessages SET answered = 1, answeredby = ? WHERE id = ? AND answered = 0', [$uid, $id]);
            $n = (int)$db->affected_rows();
            if ($n > 0) {
                write_log('Staff PM #' . $id . ' was marked as answered by ' . $uname);
            }
        }
        sb_redirect('action=viewpm&pmid=' . $id . '&msg=answered&n=' . $n);
    }

    // Bulk actions
    $op  = (string)($_POST['op'] ?? '');
    $ids = sb_post_ids();

    if ($ids === [] || !in_array($op, ['answer', 'delete'], true)) {
        sb_redirect($keep . 'msg=none');
    }

    $in = implode(',', array_fill(0, count($ids), '?'));

    if ($op === 'answer') {
        $db->sql_query_prepared(
            'UPDATE staffmessages SET answered = 1, answeredby = ? WHERE answered = 0 AND id IN (' . $in . ')',
            array_merge([$uid], $ids)
        );
        $n = (int)$db->affected_rows();
        if ($n > 0) {
            write_log($n . ' staff PM(s) marked as answered by ' . $uname . ' (IDs: ' . implode(', ', $ids) . ')');
        }
        sb_redirect($keep . 'msg=answered&n=' . $n);
    }

    $db->sql_query_prepared('DELETE FROM staffmessages WHERE id IN (' . $in . ')', $ids);
    $n = (int)$db->affected_rows();
    if ($n > 0) {
        write_log($n . ' staff PM(s) deleted by ' . $uname . ' (IDs: ' . implode(', ', $ids) . ')');
    }
    sb_redirect($keep . 'msg=deleted&n=' . $n);
}

/* ------------------------------------------------------------------ */
/*  Reply to a staff message                                           */
/* ------------------------------------------------------------------ */

if ($action === 'answermessage') {
    $answeringto = (int)($_POST['answeringto'] ?? $_GET['answeringto'] ?? 0);
    int_check($answeringto, true);

    $res = $db->sql_query_prepared(
        'SELECT s.*, u.username, u.usergroup
           FROM staffmessages s
           LEFT JOIN users u ON (u.id = s.sender)
          WHERE s.id = ?',
        [$answeringto]
    );
    $orig = $db->fetch_array($res);

    if (!$orig) {
        stderr($lang->staffbox['err_title'], $lang->staffbox['err_not_found']);
    }

    $receiver = (int)$orig['sender'];
    if ($receiver <= 0 || $orig['username'] === null) {
        stderr($lang->staffbox['err_title'], $lang->staffbox['err_no_user']);
    }

    $defaultSubject = $lang->staffbox['pm_default_subject'];
    $origSubject    = trim((string)$orig['subject']);
    $subject_val    = $origSubject === '' ? $defaultSubject : (stripos($origSubject, 're:') === 0 ? $origSubject : 'Re: ' . $origSubject);
    $message_val    = '[quote=' . $orig['username'] . ']' . $orig['msg'] . "[/quote]\n";
    $error   = '';
    $preview = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject_val = trim((string)($_POST['subject'] ?? ''));
        $message_val = trim((string)($_POST['message'] ?? ''));

        if (!sb_csrf_ok()) {
            $error = $lang->staffbox['err_csrf_reply'];
        } elseif (isset($_POST['submit'])) {
            if ($message_val === '') {
                $error = $lang->staffbox['err_empty_reply'];
            } else {
                require_once INC_PATH . '/functions_pm.php';

                $uid = (int)$CURUSER['id'];
                send_pm([
                    'subject' => $subject_val !== '' ? $subject_val : $defaultSubject,
                    'message' => $message_val,
                    'touid'   => $receiver,
                ], $uid, true);

                $db->sql_query_prepared(
                    'UPDATE staffmessages SET answer = ?, answered = 1, answeredby = ? WHERE id = ?',
                    [$message_val, $uid, $answeringto]
                );
                write_log('Staff PM #' . $answeringto . ' was answered by ' . $CURUSER['username']);

                sb_redirect('action=viewpm&pmid=' . $answeringto . '&msg=sent');
            }
        } elseif (isset($_POST['previewpost']) && $message_val !== '') {
            $preview = $parser->parse_message($message_val, $parser_options);
        }
    }

    stdhead($lang->staffbox['page_title_reply'], false);
    sb_assets();
    include_once INC_PATH . '/editor.php';

    $L        = $lang->staffbox;
    $editor   = insert_bbcode_editor($smilies, $BASEURL, 'staffMessage');
    $toUser   = sb_user_link($receiver, $orig['username'], $orig['usergroup']);
    $origHtml = $parser->parse_message((string)$orig['msg'], $parser_options);
    $origSubj = $origSubject !== '' ? htmlspecialchars_uni($origSubject) : '<em class="sb-muted">' . $L['lbl_no_subject'] . '</em>';
    $viewUrl  = sb_url('action=viewpm&pmid=' . $answeringto);
    $formUrl  = sb_url('action=answermessage&answeringto=' . $answeringto . '&receiver=' . $receiver);

    echo '<div class="sb-wrap">';
    echo sb_header(
        'fa-reply',
        'primary',
        $L['sec_reply_title'],
        ags_fmt($L['lbl_reply_to'], $toUser) . ' <span class="sb-sep"></span> ' . $origSubj
    );

    if ($error !== '') {
        echo '<div class="sb-flash sb-tone-danger" role="alert"><i class="fa-solid fa-triangle-exclamation"></i><span>' . $error . '</span></div>';
    }

    echo '
    <details class="sb-card sb-original">
        <summary>
            <span class="sb-ico sb-ico-sm sb-tone-secondary"><i class="fa-solid fa-envelope-open-text"></i></span>
            <span class="sb-section-title">' . $L['sec_original'] . '</span>
            <span class="sb-muted sb-small">' . sb_time($orig['added']) . '</span>
            <i class="fa-solid fa-chevron-down sb-chevron"></i>
        </summary>
        <div class="sb-msg-body">' . $origHtml . '</div>
    </details>';

    if ($preview !== '') {
        echo '
    <div class="sb-card sb-section sb-preview">
        <div class="sb-section-head">
            <span class="sb-ico sb-ico-sm sb-tone-info"><i class="fa-solid fa-eye"></i></span>
            <span class="sb-section-title">' . $L['sec_preview'] . '</span>
        </div>
        <div class="sb-msg-body">' . $preview . '</div>
    </div>';
    }

    echo '
    <form method="post" name="compose" action="' . $formUrl . '">
        ' . sb_csrf_field() . '
        <input type="hidden" name="receiver" value="' . $receiver . '">
        <input type="hidden" name="answeringto" value="' . $answeringto . '">

        <div class="sb-card sb-section">
            <div class="sb-section-head">
                <span class="sb-ico sb-ico-sm sb-tone-primary"><i class="fa-solid fa-pen-to-square"></i></span>
                <span class="sb-section-title">' . $L['sec_your_reply'] . '</span>
            </div>
            <div class="sb-form">
                <label class="form-label sb-label" for="sbSubject">' . $L['lbl_subject'] . '</label>
                <div class="sb-input-ico">
                    <i class="fa-solid fa-heading"></i>
                    <input type="text" id="sbSubject" name="subject" maxlength="120" value="' . htmlspecialchars_uni($subject_val) . '" class="form-control">
                </div>

                <label class="form-label sb-label" for="staffMessage">' . $L['lbl_message'] . '</label>
                ' . $editor['toolbar'] . '
                <textarea name="message" id="staffMessage" rows="12" class="form-control">' . htmlspecialchars_uni($message_val) . '</textarea>
            </div>
        </div>

        <div class="sb-actionbar">
            <a href="' . $viewUrl . '" class="sb-pill-btn sb-tone-secondary"><i class="fa-solid fa-arrow-left"></i>' . $L['btn_back'] . '</a>
            <div class="sb-actionbar-btns">
                <button type="submit" name="previewpost" value="1" class="sb-pill-btn sb-tone-info"><i class="fa-solid fa-eye"></i>' . $L['btn_preview'] . '</button>
                <button type="submit" name="submit" value="1" class="sb-pill-btn sb-tone-primary is-solid"><i class="fa-solid fa-paper-plane"></i>' . $L['btn_send'] . '</button>
            </div>
        </div>
    </form>
    </div>
    ' . $editor['modal'];

    stdfoot();
    exit;
}

/* ------------------------------------------------------------------ */
/*  View one message                                                   */
/* ------------------------------------------------------------------ */

if ($action === 'viewpm') {
    $pmid = (int)($_GET['pmid'] ?? 0);
    int_check($pmid, true);

    $res = $db->sql_query_prepared(
        'SELECT s.*,
                su.username AS sender_name, su.usergroup AS sender_group,
                au.username AS answer_name, au.usergroup AS answer_group
           FROM staffmessages s
           LEFT JOIN users su ON (su.id = s.sender)
           LEFT JOIN users au ON (au.id = s.answeredby)
          WHERE s.id = ?',
        [$pmid]
    );
    $m = $db->fetch_array($res);

    if (!$m) {
        stderr($lang->staffbox['err_title'], $lang->staffbox['err_not_found']);
    }

    $L         = $lang->staffbox;
    $id        = (int)$m['id'];
    $sender    = (int)$m['sender'];
    $isDone    = (int)$m['answered'] === 1;
    $subject   = trim((string)$m['subject']);
    $subjectH  = $subject !== '' ? htmlspecialchars_uni($subject) : $L['lbl_no_subject'];
    $fromHtml  = sb_user_link($sender, $m['sender_name'], $m['sender_group']);
    $byHtml    = (int)$m['answeredby'] > 0 ? sb_user_link($m['answeredby'], $m['answer_name'], $m['answer_group']) : '<span class="sb-muted">—</span>';
    $answer    = trim((string)($m['answer'] ?? ''));

    $actions  = '<a href="' . sb_url() . '" class="sb-pill-btn sb-tone-secondary"><i class="fa-solid fa-arrow-left"></i>' . $L['btn_inbox'] . '</a>';
    if ($sender > 0) {
        $actions .= '<a href="' . sb_url('action=answermessage&receiver=' . $sender . '&answeringto=' . $id) . '" class="sb-pill-btn sb-tone-primary is-solid"><i class="fa-solid fa-reply"></i>' . $L['btn_reply'] . '</a>';
    }
    $actions .= '<form method="post" action="' . sb_url() . '" class="sb-inline-form">' . sb_csrf_field();
    if (!$isDone) {
        $actions .= '<button type="submit" name="answer_one" value="' . $id . '" class="sb-pill-btn sb-tone-success"><i class="fa-solid fa-check"></i>' . $L['btn_mark_answered'] . '</button>';
    }
    $actions .= '<button type="submit" name="delete_one" value="' . $id . '" class="sb-pill-btn sb-tone-danger"'
        . ' data-sb-confirm="' . sb_e($L['confirm_delete_one']) . '"'
        . ' data-sb-confirm-text="' . sb_e($L['confirm_delete_view']) . '"'
        . ' data-sb-confirm-btn="' . sb_e($L['confirm_btn_delete']) . '">'
        . '<i class="fa-solid fa-trash-can"></i>' . $L['btn_delete'] . '</button>';
    $actions .= '</form>';

    stdhead($L['page_title_inbox']);
    sb_assets();

    echo '<div class="sb-wrap">';
    echo sb_flash();
    echo sb_header(
        $isDone ? 'fa-envelope-open' : 'fa-envelope',
        $isDone ? 'success' : 'warning',
        $subjectH,
        ags_fmt($L['sec_view_sub'], $id),
        $actions
    );

    echo '
    <div class="sb-meta">
        <div class="sb-card sb-meta-item">
            <span class="sb-ico sb-ico-sm sb-tone-primary"><i class="fa-solid fa-user"></i></span>
            <div><div class="sb-meta-lbl">' . $L['lbl_from'] . '</div><div class="sb-meta-val">' . $fromHtml . '</div></div>
        </div>
        <div class="sb-card sb-meta-item">
            <span class="sb-ico sb-ico-sm sb-tone-info"><i class="fa-solid fa-calendar-day"></i></span>
            <div><div class="sb-meta-lbl">' . $L['lbl_sent'] . '</div><div class="sb-meta-val">' . sb_time($m['added']) . '</div></div>
        </div>
        <div class="sb-card sb-meta-item">
            <span class="sb-ico sb-ico-sm ' . ($isDone ? 'sb-tone-success' : 'sb-tone-warning') . '"><i class="fa-solid fa-flag"></i></span>
            <div><div class="sb-meta-lbl">' . $L['lbl_status'] . '</div><div class="sb-meta-val">' . sb_status($isDone) . '</div></div>
        </div>
        <div class="sb-card sb-meta-item">
            <span class="sb-ico sb-ico-sm sb-tone-secondary"><i class="fa-solid fa-user-shield"></i></span>
            <div><div class="sb-meta-lbl">' . $L['lbl_answered_by'] . '</div><div class="sb-meta-val">' . $byHtml . '</div></div>
        </div>
    </div>

    <div class="sb-card sb-section">
        <div class="sb-section-head">
            <span class="sb-ico sb-ico-sm sb-tone-primary"><i class="fa-solid fa-message"></i></span>
            <span class="sb-section-title">' . $L['sec_message'] . '</span>
        </div>
        <div class="sb-msg-body">' . $parser->parse_message((string)$m['msg'], $parser_options) . '</div>
    </div>';

    if ($answer !== '') {
        echo '
    <div class="sb-card sb-section sb-answer" id="answer">
        <div class="sb-section-head">
            <span class="sb-ico sb-ico-sm sb-tone-success"><i class="fa-solid fa-reply"></i></span>
            <span class="sb-section-title">' . $L['sec_answer'] . '</span>
            <span class="sb-muted sb-small">' . ags_fmt($L['lbl_answer_author'], $byHtml) . '</span>
        </div>
        <div class="sb-msg-body">' . $parser->parse_message($answer, $parser_options) . '</div>
    </div>';
    } elseif ($isDone) {
        echo '
    <div class="sb-card sb-section sb-note" id="answer">
        <i class="fa-solid fa-circle-info"></i>
        ' . $L['lbl_note_no_reply'] . '
    </div>';
    }

    echo '</div>';
    stdfoot();
    exit;
}

/* ------------------------------------------------------------------ */
/*  Inbox (default)                                                    */
/* ------------------------------------------------------------------ */

$L = $lang->staffbox;

$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'open', 'done'], true)) {
    $filter = 'all';
}
$where = match ($filter) {
    'open'  => 'WHERE s.answered = 0',
    'done'  => 'WHERE s.answered = 1',
    default => '',
};

$res = $db->sql_query_prepared(
    'SELECT COUNT(*) AS total,
            COALESCE(SUM(answered = 0), 0) AS open_cnt,
            COALESCE(SUM(answered = 1), 0) AS done_cnt,
            COALESCE(SUM(added >= ?), 0)   AS day_cnt
       FROM staffmessages',
    [TIMENOW - DAY_IN_SECONDS]
);
$st    = $db->fetch_array($res) ?: [];
$total = (int)($st['total'] ?? 0);
$open  = (int)($st['open_cnt'] ?? 0);
$done  = (int)($st['done_cnt'] ?? 0);
$day   = (int)($st['day_cnt'] ?? 0);

$count = match ($filter) {
    'open'  => $open,
    'done'  => $done,
    default => $total,
};

$perpage = max(1, (int)($torrentsperpage ?? 20));
$pages   = max(1, (int)ceil($count / $perpage));
$page    = (int)($mybb->input['page'] ?? ($_GET['page'] ?? 1));
$page    = min(max(1, $page), $pages);
$start   = ($page - 1) * $perpage;

$filterQ   = $filter !== 'all' ? 'filter=' . $filter : '';
$multipage = $count > $perpage ? multipage($count, $perpage, $page, sb_url($filterQ)) : '';

// Strings reused in every row
$tipView    = sb_e($L['tip_view']);
$tipReply   = sb_e($L['tip_reply']);
$tipDelete  = sb_e($L['tip_delete']);
$cfDelOne   = sb_e($L['confirm_delete_one']);
$cfIrrev    = sb_e($L['confirm_irreversible']);
$cfBtnDel   = sb_e($L['confirm_btn_delete']);

$rows = '';
if ($count > 0) {
    $res = $db->sql_query_prepared(
        'SELECT s.id, s.subject, s.sender, s.added, s.answered, s.answeredby,
                LEFT(s.msg, 400) AS snippet,
                u.username, u.usergroup,
                uu.username AS username2, uu.usergroup AS usergroup2
           FROM staffmessages s
           LEFT JOIN users u  ON (u.id = s.sender)
           LEFT JOIN users uu ON (uu.id = s.answeredby)
         ' . $where . '
          ORDER BY s.id DESC
          LIMIT ?, ?',
        [$start, $perpage]
    );

    while ($r = $db->fetch_array($res)) {
        $id       = (int)$r['id'];
        $sender   = (int)$r['sender'];
        $isDone   = (int)$r['answered'] === 1;
        $subject  = trim((string)$r['subject']);
        $subjectH = $subject !== '' ? htmlspecialchars_uni($subject) : '<em class="sb-muted">' . $L['lbl_no_subject'] . '</em>';
        $snippet  = sb_snippet((string)$r['snippet']);
        $viewUrl  = sb_url('action=viewpm&pmid=' . $id);

        $by = '';
        if ($isDone && (int)$r['answeredby'] > 0) {
            $by = '<div class="sb-by">' . ags_fmt($L['lbl_by_list'], sb_user_link($r['answeredby'], $r['username2'], $r['usergroup2'])) . '</div>';
        }

        $reply = $sender > 0
            ? '<a href="' . sb_url('action=answermessage&receiver=' . $sender . '&answeringto=' . $id) . '" class="sb-btn-icon sb-tone-success" data-bs-toggle="tooltip" title="' . $tipReply . '"><i class="fa-solid fa-reply"></i></a>'
            : '';

        $rows .= '
            <tr class="sb-row' . ($isDone ? '' : ' is-open') . '">
                <td class="sb-col-check">
                    <input type="checkbox" class="form-check-input sb-select message-checkbox" name="ids[]" value="' . $id . '" aria-label="' . sb_e(ags_fmt($L['aria_select_msg'], $id)) . '">
                </td>
                <td class="sb-col-msg">
                    <div class="sb-subject">
                        <i class="fa-solid ' . ($isDone ? 'fa-envelope-open' : 'fa-envelope') . '"></i>
                        <a href="' . $viewUrl . '">' . $subjectH . '</a>
                    </div>
                    ' . ($snippet !== '' ? '<div class="sb-snippet">' . $snippet . '</div>' : '') . '
                </td>
                <td class="sb-col-from">' . sb_user_link($sender, $r['username'], $r['usergroup']) . '</td>
                <td class="sb-col-time sb-hide-sm">' . sb_time($r['added']) . '</td>
                <td class="sb-col-status">' . sb_status($isDone) . $by . '</td>
                <td class="sb-col-actions">
                    <div class="sb-actions">
                        <a href="' . $viewUrl . '" class="sb-btn-icon sb-tone-primary" data-bs-toggle="tooltip" title="' . $tipView . '"><i class="fa-solid fa-eye"></i></a>
                        ' . $reply . '
                        <button type="submit" name="delete_one" value="' . $id . '" class="sb-btn-icon sb-tone-danger" data-bs-toggle="tooltip" title="' . $tipDelete . '"
                                data-sb-confirm="' . $cfDelOne . '" data-sb-confirm-text="' . $cfIrrev . '" data-sb-confirm-btn="' . $cfBtnDel . '">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>';
    }
}

$tab = static function (string $key, string $icon, string $label, int $n) use ($filter): string {
    $href = sb_url($key === 'all' ? '' : 'filter=' . $key);
    return '<a href="' . $href . '" class="sb-tab' . ($filter === $key ? ' active' : '') . '">'
        . '<i class="fa-solid ' . $icon . '"></i>' . $label . '<span class="sb-count">' . number_format($n) . '</span></a>';
};

stdhead($L['page_title_inbox']);
sb_assets();

echo '<div class="sb-wrap">';
echo sb_flash();
echo sb_header('fa-inbox', 'primary', $L['sec_inbox_title'], $L['sec_inbox_sub']);

echo '
<div class="sb-kpis">
    <div class="sb-card sb-kpi sb-tone-primary">
        <span class="sb-ico"><i class="fa-solid fa-envelopes-bulk"></i></span>
        <div><div class="sb-kpi-val">' . number_format($total) . '</div><div class="sb-kpi-lbl">' . $L['kpi_total'] . '</div></div>
    </div>
    <div class="sb-card sb-kpi sb-tone-warning">
        <span class="sb-ico"><i class="fa-solid fa-hourglass-half"></i></span>
        <div><div class="sb-kpi-val">' . number_format($open) . '</div><div class="sb-kpi-lbl">' . $L['kpi_open'] . '</div></div>
    </div>
    <div class="sb-card sb-kpi sb-tone-success">
        <span class="sb-ico"><i class="fa-solid fa-circle-check"></i></span>
        <div><div class="sb-kpi-val">' . number_format($done) . '</div><div class="sb-kpi-lbl">' . $L['kpi_done'] . '</div></div>
    </div>
    <div class="sb-card sb-kpi sb-tone-info">
        <span class="sb-ico"><i class="fa-solid fa-clock"></i></span>
        <div><div class="sb-kpi-val">' . number_format($day) . '</div><div class="sb-kpi-lbl">' . $L['kpi_day'] . '</div></div>
    </div>
</div>

<nav class="sb-tabs" aria-label="' . sb_e($L['aria_tabs']) . '">'
    . $tab('all', 'fa-layer-group', $L['tab_all'], $total)
    . $tab('open', 'fa-hourglass-half', $L['tab_open'], $open)
    . $tab('done', 'fa-circle-check', $L['tab_done'], $done) . '
</nav>';

if ($count === 0) {
    [$emptyTitle, $emptyText] = match ($filter) {
        'open'  => [$L['empty_open_title'], $L['empty_open_text']],
        'done'  => [$L['empty_done_title'], $L['empty_done_text']],
        default => [$L['empty_all_title'], $L['empty_all_text']],
    };
    echo '
    <div class="sb-card sb-empty">
        <span class="sb-ico sb-ico-xl sb-tone-' . ($filter === 'open' ? 'success' : 'secondary') . '">
            <i class="fa-solid ' . ($filter === 'open' ? 'fa-mug-hot' : 'fa-inbox') . '"></i>
        </span>
        <div class="sb-empty-title">' . $emptyTitle . '</div>
        <div class="sb-muted">' . $emptyText . '</div>
    </div>';
} else {
    $selectAll = sb_e($L['aria_select_all']);

    echo '
    <form method="post" action="' . sb_url() . '" id="sbInboxForm">
        ' . sb_csrf_field() . '
        <input type="hidden" name="filter" value="' . $filter . '">
        <input type="hidden" name="page" value="' . $page . '">

        <div class="sb-card sb-table-card">
            <div class="table-responsive">
                <table class="sb-table">
                    <thead>
                        <tr>
                            <th class="sb-col-check"><input type="checkbox" class="form-check-input sb-select-all" id="selectAll" aria-label="' . $selectAll . '"></th>
                            <th>' . $L['th_message'] . '</th>
                            <th>' . $L['th_from'] . '</th>
                            <th class="sb-hide-sm">' . $L['th_sent'] . '</th>
                            <th>' . $L['th_status'] . '</th>
                            <th class="sb-col-actions"></th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '
                    </tbody>
                </table>
            </div>
            ' . ($multipage !== '' ? '<div class="sb-pager">' . $multipage . '</div>' : '') . '
        </div>

        <div class="sb-actionbar">
            <label class="sb-selinfo">
                <input type="checkbox" class="form-check-input sb-select-all" aria-label="' . $selectAll . '">
                <span>' . ags_fmt($L['lbl_selected'], '<b id="sbSelCount">0</b>') . '</span>
            </label>
            <div class="sb-actionbar-btns">
                <button type="submit" name="op" value="answer" class="sb-pill-btn sb-tone-success" data-sb-bulk disabled>
                    <i class="fa-solid fa-check-double"></i>' . $L['btn_mark_answered'] . '
                </button>
                <button type="submit" name="op" value="delete" class="sb-pill-btn sb-tone-danger" data-sb-bulk disabled
                        data-sb-confirm="' . sb_e($L['confirm_delete_bulk']) . '" data-sb-confirm-text="' . $cfIrrev . '" data-sb-confirm-btn="' . $cfBtnDel . '">
                    <i class="fa-solid fa-trash-can"></i>' . $L['btn_delete'] . '
                </button>
            </div>
        </div>
    </form>';
}

echo '</div>';
stdfoot();
