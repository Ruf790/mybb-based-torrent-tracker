<?php

declare(strict_types=1);

/**
 * reports.php - Report Management System
 */



if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed2222222.</font>');
}

require_once(INC_PATH . '/class_parser.php');

$parser = new postParser();
$parser_options = [
    'allow_html'       => 1,
    'allow_mycode'     => 1,
    'allow_smilies'    => 1,
    'allow_imgcode'    => 1,
    'allow_videocode'  => 1,
    'filter_badwords'  => 1,
];

$action    = $_GET['action'] ?? 'list';
$report_id = (int)($_GET['id'] ?? 0);

// ==================== КОНСТАНТЫ ====================

const REPORT_TYPES = ['torrent', 'user', 'comment', 'forumpost'];

const RULES_MAP = [
    'rule_1' => ['text' => 'Rule 1: No spamming or advertising',    'color' => 'bg-danger',  'icon' => 'fa-megaphone'],
    'rule_2' => ['text' => 'Rule 2: No offensive language',         'color' => 'bg-danger',  'icon' => 'fa-comment-slash'],
    'rule_3' => ['text' => 'Rule 3: No harassment or bullying',     'color' => 'bg-danger',  'icon' => 'fa-user-group-slash'],
    'rule_4' => ['text' => 'Rule 4: Stay on topic',                 'color' => 'bg-warning', 'icon' => 'fa-signs-post'],
    'rule_5' => ['text' => 'Rule 5: No warez or illegal content',   'color' => 'bg-danger',  'icon' => 'fa-ban'],
    'rule_6' => ['text' => 'Rule 6: Respect other members',         'color' => 'bg-warning', 'icon' => 'fa-handshake'],
    'rule_7' => ['text' => 'Rule 7: No double posting',             'color' => 'bg-info',    'icon' => 'fa-copy'],
    'rule_8' => ['text' => 'Rule 8: Use appropriate language',      'color' => 'bg-warning', 'icon' => 'fa-language'],
];

const REASON_RECOMMENDATIONS = [
    'comment' => [
        'spam'          => 'Consider deleting the comment and warning the user about spam policies.',
        'offensive'     => 'Review the language used and consider deletion with a user warning.',
        'harassment'    => 'Immediate action recommended. Delete comment and consider user ban.',
        'hate_speech'   => 'Zero tolerance policy. Delete immediately and consider permanent ban.',
        'inappropriate' => 'Review content against community guidelines. Edit or delete as needed.',
        'spoiler'       => 'Consider adding spoiler tags or moving to appropriate section.',
        'misinformation'=> 'Verify information and add correction notice if false.',
        'off_topic'     => 'Move to appropriate thread or delete if completely irrelevant.',
        'personal_info' => 'Delete immediately. Do not share personal information.',
        'other'         => 'Review based on description provided.',
    ],
    'torrent' => [
        'copyright'     => 'Verify copyright claim. Remove torrent if infringement is confirmed.',
        'malware'       => 'Scan files for malware. Remove immediately if infected.',
        'fake'          => 'Verify content authenticity. Remove if fake or mislabeled.',
        'broken'        => 'Check tracker and seed status. Mark as broken if dead.',
        'inappropriate' => 'Review against content policies. Remove if violates guidelines.',
        'other'         => 'Review based on description provided.',
    ],
];

// ==================== МАППИНГИ ПРИЧИН ====================

function get_report_reasons_map(?string $type = null): array
{
    static $maps = null;

    if ($maps === null) {
        $maps = [
            'comment' => [
                'spam'          => ['text' => 'Spam / Advertising',           'color' => 'bg-danger',    'icon' => 'fa-bullhorn',            'severity' => 'high',    'category' => 'Content Issues'],
                'offensive'     => ['text' => 'Offensive / Abusive Language', 'color' => 'bg-danger',    'icon' => 'fa-comment-slash',       'severity' => 'high',    'category' => 'Content Issues'],
                'harassment'    => ['text' => 'Harassment / Bullying',        'color' => 'bg-danger',    'icon' => 'fa-user-slash',          'severity' => 'high',    'category' => 'Content Issues'],
                'hate_speech'   => ['text' => 'Hate Speech / Discrimination', 'color' => 'bg-danger',    'icon' => 'fa-triangle-exclamation','severity' => 'high',    'category' => 'Content Issues'],
                'inappropriate' => ['text' => 'Inappropriate Content',        'color' => 'bg-warning',   'icon' => 'fa-eye-slash',           'severity' => 'medium',  'category' => 'Content Issues'],
                'spoiler'       => ['text' => 'Spoiler / Leaked Content',     'color' => 'bg-info',      'icon' => 'fa-mask',                'severity' => 'low',     'category' => 'Other Issues'],
                'misinformation'=> ['text' => 'Misinformation / Fake News',   'color' => 'bg-warning',   'icon' => 'fa-circle-exclamation',  'severity' => 'medium',  'category' => 'Other Issues'],
                'off_topic'     => ['text' => 'Off Topic / Irrelevant',       'color' => 'bg-secondary', 'icon' => 'fa-signs-post',          'severity' => 'low',     'category' => 'Other Issues'],
                'personal_info' => ['text' => 'Personal Information',         'color' => 'bg-danger',    'icon' => 'fa-id-card',             'severity' => 'high',    'category' => 'Other Issues'],
                'other'         => ['text' => 'Other Reason',                 'color' => 'bg-dark',      'icon' => 'fa-ellipsis',            'severity' => 'unknown', 'category' => 'Other Issues'],
            ],
            'torrent' => [
                'copyright'     => ['text' => 'Copyright Infringement',  'color' => 'bg-danger',  'icon' => 'fa-copyright',  'severity' => 'high',    'category' => 'Legal Issues'],
                'malware'       => ['text' => 'Malware/Virus',           'color' => 'bg-danger',  'icon' => 'fa-bug',        'severity' => 'high',    'category' => 'Security Issues'],
                'fake'          => ['text' => 'Fake/Incorrect Content',  'color' => 'bg-warning', 'icon' => 'fa-ban',        'severity' => 'medium',  'category' => 'Content Issues'],
                'broken'        => ['text' => 'Broken/Dead Torrent',     'color' => 'bg-info',    'icon' => 'fa-link-slash', 'severity' => 'low',     'category' => 'Technical Issues'],
                'inappropriate' => ['text' => 'Inappropriate Content',   'color' => 'bg-warning', 'icon' => 'fa-eye-slash',  'severity' => 'medium',  'category' => 'Content Issues'],
                'other'         => ['text' => 'Other Reason',            'color' => 'bg-dark',    'icon' => 'fa-ellipsis',   'severity' => 'unknown', 'category' => 'Other Issues'],
            ],
            'forumpost' => [
                'spam'           => ['text' => 'Spam / Advertising',           'color' => 'bg-danger',    'icon' => 'fa-bullhorn',            'severity' => 'high',    'category' => 'Content Violations'],
                'offensive'      => ['text' => 'Offensive / Abusive Language', 'color' => 'bg-danger',    'icon' => 'fa-comment-slash',       'severity' => 'high',    'category' => 'Content Violations'],
                'harassment'     => ['text' => 'Harassment / Bullying',        'color' => 'bg-danger',    'icon' => 'fa-user-slash',          'severity' => 'high',    'category' => 'Content Violations'],
                'hate_speech'    => ['text' => 'Hate Speech / Discrimination', 'color' => 'bg-danger',    'icon' => 'fa-triangle-exclamation','severity' => 'high',    'category' => 'Content Violations'],
                'explicit'       => ['text' => 'Explicit / Adult Content',     'color' => 'bg-danger',    'icon' => 'fa-eye-slash',           'severity' => 'high',    'category' => 'Content Violations'],
                'illegal'        => ['text' => 'Illegal Content / Warez',      'color' => 'bg-danger',    'icon' => 'fa-ban',                 'severity' => 'high',    'category' => 'Content Violations'],
                'off_topic'      => ['text' => 'Off Topic / Wrong Forum',      'color' => 'bg-warning',   'icon' => 'fa-signs-post',          'severity' => 'medium',  'category' => 'Forum Rules'],
                'double_post'    => ['text' => 'Double Post / Cross-Posting',  'color' => 'bg-info',      'icon' => 'fa-copy',                'severity' => 'low',     'category' => 'Forum Rules'],
                'flame'          => ['text' => 'Flaming / Trolling',           'color' => 'bg-warning',   'icon' => 'fa-fire',                'severity' => 'medium',  'category' => 'Forum Rules'],
                'personal_attack'=> ['text' => 'Personal Attack',              'color' => 'bg-danger',    'icon' => 'fa-user-slash',          'severity' => 'high',    'category' => 'Forum Rules'],
                'spoiler'        => ['text' => 'Unmarked Spoilers',            'color' => 'bg-warning',   'icon' => 'fa-mask',                'severity' => 'medium',  'category' => 'Forum Rules'],
                'copyright'      => ['text' => 'Copyright Infringement',       'color' => 'bg-danger',    'icon' => 'fa-copyright',           'severity' => 'high',    'category' => 'Other Issues'],
                'personal_info'  => ['text' => 'Personal Information',         'color' => 'bg-danger',    'icon' => 'fa-id-card',             'severity' => 'high',    'category' => 'Other Issues'],
                'malware'        => ['text' => 'Malware Link',                 'color' => 'bg-danger',    'icon' => 'fa-bug',                 'severity' => 'high',    'category' => 'Other Issues'],
                'scam'           => ['text' => 'Scam / Fraud',                 'color' => 'bg-danger',    'icon' => 'fa-skull-crossbones',    'severity' => 'high',    'category' => 'Other Issues'],
                'other'          => ['text' => 'Other Reason',                 'color' => 'bg-dark',      'icon' => 'fa-ellipsis',            'severity' => 'unknown', 'category' => 'Other Issues'],
                // Forum rules
                'rule_1' => array_merge(RULES_MAP['rule_1'], ['severity' => 'high',   'category' => 'Forum Rules']),
                'rule_2' => array_merge(RULES_MAP['rule_2'], ['severity' => 'high',   'category' => 'Forum Rules']),
                'rule_3' => array_merge(RULES_MAP['rule_3'], ['severity' => 'high',   'category' => 'Forum Rules']),
                'rule_4' => array_merge(RULES_MAP['rule_4'], ['severity' => 'medium', 'category' => 'Forum Rules']),
                'rule_5' => array_merge(RULES_MAP['rule_5'], ['severity' => 'high',   'category' => 'Forum Rules']),
                'rule_6' => array_merge(RULES_MAP['rule_6'], ['severity' => 'medium', 'category' => 'Forum Rules']),
                'rule_7' => array_merge(RULES_MAP['rule_7'], ['severity' => 'low',    'category' => 'Forum Rules']),
                'rule_8' => array_merge(RULES_MAP['rule_8'], ['severity' => 'medium', 'category' => 'Forum Rules']),
            ],
            'user' => [
                'spam'          => ['text' => 'Spam Account',            'color' => 'bg-danger',  'icon' => 'fa-user-slash',       'severity' => 'high',    'category' => 'Account Issues',  'description' => 'User is posting spam content',                       'recommended_action' => 'Review user posts and consider temporary suspension'],
                'harassment'    => ['text' => 'Harassment/Bullying',     'color' => 'bg-danger',  'icon' => 'fa-ban',              'severity' => 'high',    'category' => 'Behavior Issues', 'description' => 'User is harassing or bullying others',               'recommended_action' => 'Immediate warning or temporary ban'],
                'fake'          => ['text' => 'Fake Account',            'color' => 'bg-warning', 'icon' => 'fa-mask',             'severity' => 'medium',  'category' => 'Account Issues',  'description' => 'User is pretending to be someone else',              'recommended_action' => 'Verify identity and take appropriate action'],
                'impersonation' => ['text' => 'Impersonation',           'color' => 'bg-danger',  'icon' => 'fa-id-badge',         'severity' => 'high',    'category' => 'Account Issues',  'description' => 'User is impersonating another user',                 'recommended_action' => 'Immediate account suspension'],
                'inappropriate' => ['text' => 'Inappropriate Profile',   'color' => 'bg-warning', 'icon' => 'fa-eye-slash',        'severity' => 'medium',  'category' => 'Content Issues',  'description' => 'User has inappropriate profile content',             'recommended_action' => 'Request profile cleanup or temporary restriction'],
                'scam'          => ['text' => 'Scam/Fraud',              'color' => 'bg-danger',  'icon' => 'fa-skull-crossbones', 'severity' => 'high',    'category' => 'Legal Issues',    'description' => 'User is involved in scams or fraud',                 'recommended_action' => 'Immediate ban and report if necessary'],
                'copyright'     => ['text' => 'Copyright Infringement',  'color' => 'bg-danger',  'icon' => 'fa-copyright',        'severity' => 'high',    'category' => 'Legal Issues',    'description' => 'User is sharing copyrighted content',                'recommended_action' => 'Remove infringing content and issue warning'],
                'malware'       => ['text' => 'Malware Distribution',    'color' => 'bg-danger',  'icon' => 'fa-bug',              'severity' => 'high',    'category' => 'Security Issues', 'description' => 'User is distributing malware/viruses',               'recommended_action' => 'Immediate ban and content removal'],
                'racism'        => ['text' => 'Racism/Hate Speech',      'color' => 'bg-danger',  'icon' => 'fa-comment-slash',    'severity' => 'high',    'category' => 'Behavior Issues', 'description' => 'User is posting racist or hateful content',          'recommended_action' => 'Immediate suspension or ban'],
                'threats'       => ['text' => 'Threats/Violence',        'color' => 'bg-danger',  'icon' => 'fa-exclamation-triangle', 'severity' => 'high', 'category' => 'Behavior Issues', 'description' => 'User is making threats or promoting violence',      'recommended_action' => 'Immediate permanent ban'],
                'underage'      => ['text' => 'Underage User',           'color' => 'bg-warning', 'icon' => 'fa-child',            'severity' => 'medium',  'category' => 'Account Issues',  'description' => 'User appears to be underage',                       'recommended_action' => 'Suspend until age verification'],
                'cheating'      => ['text' => 'Cheating/Gaming System',  'color' => 'bg-warning', 'icon' => 'fa-gamepad',          'severity' => 'medium',  'category' => 'Behavior Issues', 'description' => 'User is cheating or exploiting the system',          'recommended_action' => 'Reset stats and issue warning'],
                'other'         => ['text' => 'Other Reason',            'color' => 'bg-dark',    'icon' => 'fa-ellipsis',         'severity' => 'unknown', 'category' => 'Other Issues',    'description' => 'Select for other reasons',                           'recommended_action' => 'Review report description carefully'],
            ],
        ];
    }

    if ($type === null) {
        return $maps;
    }

    // Алиасы
    $type = ($type === 'forum_post') ? 'forumpost' : $type;

    return $maps[$type] ?? [];
}

// ==================== ХЕЛПЕРЫ ====================

function truncateString(string $string, int $length = 30): string
{
    return mb_strlen($string) > $length
        ? mb_substr($string, 0, $length) . '...'
        : $string;
}

function getTypeColor(string $type): string
{
    return match ($type) {
        'torrent'   => 'primary',
        'comment'   => 'info',
        'user'      => 'warning',
        'forumpost' => 'success',
        default     => 'secondary',
    };
}

function getTypeIcon(string $type): string
{
    return match ($type) {
        'torrent'   => 'fa-download',
        'comment'   => 'fa-comment',
        'user'      => 'fa-user',
        'forumpost' => 'fa-comments',
        default     => 'fa-file',
    };
}

function isAjaxRequest(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function sendResponse(string $message, bool $success = true): never
{
    global $_this_script_;

    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message], JSON_THROW_ON_ERROR);
        exit;
    }

    $action = $_GET['action'] ?? 'list';
    $id     = (int)($_GET['id'] ?? 0);

    $params = ['action' => $action === 'takeaction' ? 'list' : $action];

    $params[$success ? 'success' : 'error'] = match ($message) {
        'Report resolved'                         => 'resolved',
        'Report deleted'                          => 'deleted',
        'Comment deleted and report resolved'     => 'comment_deleted',
        'Report ignored'                          => 'ignored',
        'Invalid report ID'                       => 'invalid_id',
        'Report not found'                        => 'not_found',
        'Invalid action'                          => 'invalid_action',
        'No user to warn'                         => 'no_user',
        default                                   => $success ? 'success' : 'error',
    };

    if ($id > 0 && $action !== 'takeaction') {
        $params['id'] = $id;
    }

    header('Location: ' . $_this_script_ . '&' . http_build_query($params));
    exit;
}

function getReportFromDb(int $report_id): array|false
{
    global $db;
    $stmt = $db->sql_query_prepared("SELECT * FROM reports WHERE id = ?", [$report_id]);
    return $stmt ? $db->fetch_array($stmt) : false;
}

function markReportResolved(int $report_id, string $notes = ''): void
{
    global $db, $CURUSER;

    $suffix = $notes ? "\n\n--- ADMIN NOTES ---\n" . $notes : '';

    $db->sql_query_prepared(
        "UPDATE reports SET dealtwith = 1, dealtby = ?, updated_at = ?,
         description = CONCAT(COALESCE(description, ''), ?)
         WHERE id = ?",
        [$CURUSER['id'], time(), $suffix, $report_id]
    );
}

// ==================== ОБРАБОТЧИКИ ДЕЙСТВИЙ ====================

function handleAction(): never
{
    $do        = $_GET['do'] ?? $_POST['do'] ?? '';
    $report_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

    if ($report_id <= 0) {
        sendResponse('Invalid report ID', false);
    }

    $report = getReportFromDb($report_id);

    if (!$report) {
        sendResponse('Report not found', false);
    }

    match ($do) {
        'resolve'         => handleResolve($report_id),
        'delete'          => handleDelete($report_id),
        'deletecomment'   => handleDeleteComment($report_id, $report),
        'deleteforumpost' => handleDeleteForumPost($report_id, $report),
        default           => sendResponse('Invalid action', false),
    };
}

function handleResolve(int $report_id): never
{
    markReportResolved($report_id, trim($_POST['notes'] ?? ''));
    sendResponse('Report resolved');
}

function handleDelete(int $report_id): never
{
    global $db;
    $db->sql_query_prepared("DELETE FROM reports WHERE id = ?", [$report_id]);
    sendResponse('Report deleted');
}

function handleDeleteComment(int $report_id, array $report): never
{
    global $db, $CURUSER, $kpscomment;

    if ($report['type'] !== 'comment') {
        sendResponse('Invalid report type for comment deletion', false);
    }

    $comment_id = (int)$report['reported_id'];

    $res          = $db->sql_query('SELECT torrent, user FROM comments WHERE id = ' . $db->escape_string($comment_id));
    $comment_data = $db->fetch_array($res);

    if (!$comment_data) {
        sendResponse('Comment not found', false);
    }

    $torrent_id = (int)$comment_data['torrent'];
    $user_id    = (int)$comment_data['user'];

    // Удаляем вложенные файлы
    $files = $db->simple_select('comment_files', '*', 'comment_id = ' . $comment_id);
    while ($file = $db->fetch_array($files)) {
        if (!empty($file['file_path']) && is_file($file['file_path'])) {
            @unlink($file['file_path']);
        }
    }
    $db->delete_query('comment_files', 'comment_id = ' . $comment_id);
    $db->delete_query('comments', 'id = ' . $comment_id);

    if ($torrent_id > 0 && $db->affected_rows() > 0) {
        $db->sql_query('UPDATE torrents SET comments = IF(comments > 0, comments - 1, 0) WHERE id = ' . $db->escape_string($torrent_id));
        if ($user_id > 0) {
            $db->sql_query('UPDATE users SET comms = IF(comms > 0, comms - 1, 0) WHERE id = ' . $db->escape_string($user_id));
        }
    }

    if (isset($kpscomment) && $user_id > 0) {
        kps('-', $kpscomment, $user_id);
    }

    markReportResolved($report_id);
    sendResponse('Comment deleted and report resolved');
}

function handleDeleteForumPost(int $report_id, array $report): never
{
    global $db, $CURUSER;

    if ($report['type'] !== 'forumpost') {
        sendResponse('Invalid report type for forum post deletion', false);
    }

    $post_id    = (int)$report['reported_id'];
    $post_check = $db->sql_query_prepared("SELECT pid FROM posts WHERE pid = ?", [$post_id]);

    if (!$post_check || $db->num_rows($post_check) === 0) {
        markReportResolved($report_id);
        sendResponse('Forum post already deleted, report marked as resolved');
    }

    if (!class_exists('Moderation')) {
        require_once INC_PATH . '/class_moderation.php';
    }

    try {
        $moderation = new Moderation();
        $moderation->delete_post($post_id);
    } catch (Exception $e) {
        error_log("Error deleting forum post #$post_id: " . $e->getMessage());
    }

    markReportResolved($report_id);
    sendResponse('Forum post deleted and report resolved');
}

// ==================== ДАННЫЕ ====================

function getForumPostData(int $post_id, array $report): ?array
{
    global $db;

    $result = $db->sql_query_prepared(
        "SELECT p.*, t.subject AS thread_subject, t.tid AS thread_id,
                f.name AS forum_name, f.fid AS forum_id,
                u.username AS author_name, u.id AS author_id
         FROM posts p
         LEFT JOIN threads t ON p.tid = t.tid
         LEFT JOIN forums  f ON p.fid = f.fid
         LEFT JOIN users       u ON p.uid = u.id
         WHERE p.pid = ?",
        [$post_id]
    );

    if (!$result || $db->num_rows($result) === 0) {
        return null;
    }

    $data = $db->fetch_array($result);
    $db->free_result($result);

    $data['report_forum_id']  = $report['forum_id']      ?? 0;
    $data['report_thread_id'] = $report['thread_id']     ?? 0;
    $data['rule_violation']   = $report['rule_violation'] ?? '';

    return $data;
}

function parseUserReportDescription(string $description): array
{
    $result = ['formatted_description' => $description, 'additional_info' => '', 'evidence_links' => ''];

    if (!str_contains($description, '===== USER REPORT =====')) {
        return $result;
    }

    foreach (explode('=====', $description) as $section) {
        $section = trim($section);
        if (str_contains($section, 'DESCRIPTION'))           $result['formatted_description'] = trim(str_replace('DESCRIPTION =====', '', $section));
        if (str_contains($section, 'ADDITIONAL INFORMATION')) $result['additional_info']       = trim(str_replace('ADDITIONAL INFORMATION =====', '', $section));
        if (str_contains($section, 'EVIDENCE LINKS'))         $result['evidence_links']        = trim(str_replace('EVIDENCE LINKS =====', '', $section));
    }

    return $result;
}

// ==================== ТОЧКА ВХОДА ====================

if ($action === 'takeaction' && $report_id) {
    handleAction();
}

stdhead("Report Management - Admin Panel");
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/toast.js"></script>';

?>

<div class="container mt-3">
    <h1 class="h3 mb-4">
        <i class="fa-solid fa-flag text-danger me-2"></i>Report Management
        <small class="text-muted fs-6">Admin Panel</small>
    </h1>

    <div class="btn-group mb-4" role="group">
        <?php foreach (['list' => ['All Reports', 'fa-list'], 'pending' => ['Pending', 'fa-clock'], 'resolved' => ['Resolved', 'fa-check-circle'], 'stats' => ['Statistics', 'fa-chart-bar']] as $act => [$label, $icon]): ?>
        <a href="<?= $_this_script_ ?>&action=<?= $act ?>"
           class="btn btn-outline-primary <?= $action === $act ? 'active' : '' ?>">
            <i class="fa-solid <?= $icon ?> me-1"></i><?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php
    match ($action) {
        'view'     => showReportDetails($report_id),
        'pending'  => showPendingReports(),
        'resolved' => showResolvedReports(),
        'stats'    => showStatistics(),
        default    => showAllReports(),
    };
    ?>
</div>

<?php stdfoot(); ?>

<?php
// ==================== ОТОБРАЖЕНИЕ ====================

function showAllReports(): void
{
    global $db, $_this_script_;

    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perpage = 20;
    $offset  = ($page - 1) * $perpage;

    $type   = $_GET['type']   ?? '';
    $status = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');

    [$where_sql, $params] = buildReportWhereClause($type, $status, $search);

    $total_result = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total FROM reports r
         LEFT JOIN users u1 ON r.addedby = u1.id
         LEFT JOIN users u2 ON r.reported_user_id = u2.id
         $where_sql",
        $params
    );
    $total = $total_result ? (int)($db->fetch_array($total_result)['total'] ?? 0) : 0;

    $result = $db->sql_query_prepared(
        "SELECT r.*, u1.username AS reporter_name, u2.username AS reported_user_name,
                u3.username AS dealtby_name, r.reason, r.rule_violation
         FROM reports r
         LEFT JOIN users u1 ON r.addedby = u1.id
         LEFT JOIN users u2 ON r.reported_user_id = u2.id
         LEFT JOIN users u3 ON r.dealtby = u3.id
         $where_sql
         ORDER BY r.added DESC LIMIT ?, ?",
        [...$params, $offset, $perpage]
    );

    ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="<?= $_this_script_ ?>" class="row g-3">
                <input type="hidden" name="act" value="reports">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach (['torrent' => 'Torrent', 'comment' => 'Comment', 'user' => 'User', 'forumpost' => 'Forum Post'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $type === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending"  <?= $status === 'pending'  ? 'selected' : '' ?>>Pending</option>
                        <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search reason, description, usernames..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Reports (<?= number_format($total) ?>)</h5>
            <a href="<?= $_this_script_ ?>&action=list&export=csv" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-file-export me-1"></i> Export CSV
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th><th>Type</th><th>Reason</th><th>Reporter</th>
                        <th>Reported User</th><th>Date</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($total > 0 && $result):
                    while ($row = $db->fetch_array($result)):
                        $reasons_map = get_report_reasons_map($row['type']);
                        $reason_data = $reasons_map[$row['reason']] ?? null;
                        $ads         = my_datee('relative', $row['added']);
                ?>
                <tr class="<?= $row['dealtwith'] ? 'table2-success' : 'table2-warning' ?>">
                    <td>#<?= $row['id'] ?></td>
                    <td><span class="badge bg-<?= getTypeColor($row['type']) ?>"><?= ucfirst($row['type']) ?></span></td>
                    <td>
                        <?php if ($reason_data): ?>
                        <span class="badge <?= $reason_data['color'] ?>" title="<?= htmlspecialchars($reason_data['text']) ?>">
                            <i class="fa-solid <?= $reason_data['icon'] ?> me-1"></i>
                            <?= htmlspecialchars(truncateString($reason_data['text'], 25)) ?>
                        </span>
                        <?php if ($reason_data['severity'] === 'high' && !$row['dealtwith']): ?>
                        <span class="badge bg-danger blink ms-1" title="High Priority"><i class="fa-solid fa-exclamation"></i></span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?= htmlspecialchars(truncateString($row['reason'], 30)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['addedby']): ?>
                        <a href="user-<?= $row['addedby'] ?>.html" target="_blank" class="text-decoration-none">
                            <?= htmlspecialchars($row['reporter_name'] ?? 'User #' . $row['addedby']) ?>
                        </a>
                        <?php else: ?><span class="text-muted">Guest</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['reported_user_id']): ?>
                        <a href="user-<?= $row['reported_user_id'] ?>.html" target="_blank" class="text-decoration-none">
                            <?= htmlspecialchars($row['reported_user_name'] ?? 'User #' . $row['reported_user_id']) ?>
                        </a>
                        <?php else: ?><span class="text-muted">N/A</span><?php endif; ?>
                    </td>
                    <td><?= $ads ?></td>
                    <td>
                        <?php if ($row['dealtwith']): ?>
                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Resolved</span>
                        <?php if ($row['dealtby_name']): ?>
                        <div class="small text-muted">by <?= htmlspecialchars($row['dealtby_name']) ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= htmlspecialchars($_this_script_) ?>&action=view&id=<?= $row['id'] ?>"
                               class="btn btn-outline-primary" title="View Details">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <?php if (!$row['dealtwith']): ?>
                            <a href="<?= $_this_script_ ?>&action=takeaction&do=resolve&id=<?= $row['id'] ?>"
                               class="btn btn-outline-success btn-sm resolve-report" data-id="<?= $row['id'] ?>">
                                <i class="fa-solid fa-check"></i>
                            </a>
                            <?php endif; ?>
                            <a href="<?= $_this_script_ ?>&action=takeaction&do=delete&id=<?= $row['id'] ?>"
                               class="btn btn-outline-danger btn-sm delete-report" data-id="<?= $row['id'] ?>">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox fa-3x mb-3"></i>
                            <h5>No reports found</h5>
                            <p>Try adjusting your filters or check back later</p>
                            <a href="<?= $_this_script_ ?>&action=list" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-sync-alt me-1"></i> Clear Filters
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > $perpage): ?>
        <div class="card-footer">
            <?php renderPagination($page, $total, $perpage, ['action' => 'list', 'type' => $type, 'status' => $status, 'search' => $search]); ?>
        </div>
        <?php endif; ?>
    </div>

    <?= getReportListStyles() ?>
    <?= getDeleteModalScript() ?>

    <?php
    if ($result) $db->free_result($result);
}

function buildReportWhereClause(string $type, string $status, string $search): array
{
    $where_parts = [];
    $params      = [];

    if ($type && in_array($type, REPORT_TYPES, true)) {
        $where_parts[] = "r.type = ?";
        $params[]      = $type;
    }

    if ($status === 'pending') {
        $where_parts[] = "r.dealtwith = 0";
    } elseif ($status === 'resolved') {
        $where_parts[] = "r.dealtwith = 1";
    }

    if ($search) {
        $like            = "%$search%";
        $where_parts[]   = "(r.reason LIKE ? OR r.description LIKE ? OR u1.username LIKE ? OR u2.username LIKE ?)";
        array_push($params, $like, $like, $like, $like);
    }

    $where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    return [$where_sql, $params];
}

function renderPagination(int $page, int $total, int $perpage, array $extra_params): void
{
    global $_this_script_;

    $totalPages = (int)ceil($total / $perpage);
    $query      = http_build_query($extra_params);

    echo '<nav><ul class="pagination justify-content-center mb-0">';

    if ($page > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . $_this_script_ . '&' . $query . '&page=' . ($page - 1) . '"><i class="fa-solid fa-chevron-left"></i></a></li>';
    }

    for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        $active = $i === $page ? ' active' : '';
        echo "<li class=\"page-item$active\"><a class=\"page-link\" href=\"{$_this_script_}&{$query}&page={$i}\">{$i}</a></li>";
    }

    if ($page < $totalPages) {
        echo '<li class="page-item"><a class="page-link" href="' . $_this_script_ . '&' . $query . '&page=' . ($page + 1) . '"><i class="fa-solid fa-chevron-right"></i></a></li>';
    }

    echo '</ul></nav>';
}

function showPendingReports(): void
{
    global $db, $_this_script_;

    $result = $db->sql_query_prepared(
        "SELECT r.*, u1.username AS reporter_name, u2.username AS reported_user_name
         FROM reports r
         LEFT JOIN users u1 ON r.addedby = u1.id
         LEFT JOIN users u2 ON r.reported_user_id = u2.id
         WHERE r.dealtwith = 0
         ORDER BY r.added DESC LIMIT 100"
    );

    ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Pending Reports (Require Attention)</h5></div>
        <?php if ($result && $db->num_rows($result) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-warning">
                    <tr><th>ID</th><th>Type</th><th>Reason</th><th>Reporter</th><th>Reported User</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php while ($row = $db->fetch_array($result)): ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td><span class="badge bg-<?= getTypeColor($row['type']) ?>"><?= ucfirst($row['type']) ?></span></td>
                    <td><?= htmlspecialchars(truncateString($row['reason'], 30)) ?></td>
                    <td><?= htmlspecialchars($row['reporter_name'] ?? 'User #' . $row['addedby']) ?></td>
                    <td><?= htmlspecialchars($row['reported_user_name'] ?? 'User #' . $row['reported_user_id']) ?></td>
                    <td><?= date('Y-m-d H:i', (int)$row['added']) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= $_this_script_ ?>&action=view&id=<?= $row['id'] ?>" class="btn btn-outline-primary"><i class="fa-solid fa-eye"></i></a>
                            <a href="<?= $_this_script_ ?>&action=takeaction&do=resolve&id=<?= $row['id'] ?>" class="btn btn-outline-success"><i class="fa-solid fa-check"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-success py-5">
            <i class="fa-solid fa-check-circle fa-3x mb-3"></i>
            <h5>No pending reports!</h5>
            <p class="mb-0">All reports have been resolved.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php
    if ($result) $db->free_result($result);
}

function showResolvedReports(): void
{
    global $db, $_this_script_;

    $result = $db->sql_query_prepared(
        "SELECT r.*, u1.username AS reporter_name, u2.username AS reported_user_name, u3.username AS dealtby_name
         FROM reports r
         LEFT JOIN users u1 ON r.addedby = u1.id
         LEFT JOIN users u2 ON r.reported_user_id = u2.id
         LEFT JOIN users u3 ON r.dealtby = u3.id
         WHERE r.dealtwith = 1
         ORDER BY r.updated_at DESC LIMIT 50"
    );

    ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Resolved Reports</h5></div>
        <?php if ($result && $db->num_rows($result) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-success">
                    <tr><th>ID</th><th>Type</th><th>Reason</th><th>Reporter</th><th>Reported User</th><th>Resolved By</th><th>Date Resolved</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php while ($row = $db->fetch_array($result)): ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td><span class="badge bg-<?= getTypeColor($row['type']) ?>"><?= ucfirst($row['type']) ?></span></td>
                    <td><?= htmlspecialchars(truncateString($row['reason'], 25)) ?></td>
                    <td><?= htmlspecialchars($row['reporter_name'] ?? 'User #' . $row['addedby']) ?></td>
                    <td><?= htmlspecialchars($row['reported_user_name'] ?? 'User #' . $row['reported_user_id']) ?></td>
                    <td><?= htmlspecialchars($row['dealtby_name'] ?? '') ?></td>
                    <td><?= date('Y-m-d H:i', (int)$row['updated_at']) ?></td>
                    <td>
                        <a href="<?= $_this_script_ ?>&action=view&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-muted py-5">
            <i class="fa-solid fa-inbox fa-3x mb-3"></i>
            <h5>No resolved reports found</h5>
        </div>
        <?php endif; ?>
    </div>
    <?php
    if ($result) $db->free_result($result);
}

function showStatistics(): void
{
    global $db, $_this_script_;

    $thirty_days_ago = time() - (30 * 24 * 60 * 60);

    $stats_result = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN dealtwith = 1 THEN 1 ELSE 0 END) AS resolved,
                SUM(CASE WHEN dealtwith = 0 THEN 1 ELSE 0 END) AS pending,
                COUNT(DISTINCT addedby) AS unique_reporters,
                COUNT(DISTINCT reported_user_id) AS unique_reported_users
         FROM reports WHERE added > ?",
        [$thirty_days_ago]
    );

    $stats = $stats_result
        ? $db->fetch_array($stats_result)
        : ['total' => 0, 'resolved' => 0, 'pending' => 0, 'unique_reporters' => 0, 'unique_reported_users' => 0];

    $type_stats_result = $db->sql_query_prepared(
        "SELECT type, COUNT(*) AS count, SUM(CASE WHEN dealtwith = 1 THEN 1 ELSE 0 END) AS resolved
         FROM reports WHERE added > ? GROUP BY type ORDER BY count DESC",
        [$thirty_days_ago]
    );

    $top_reported_result = $db->sql_query_prepared(
        "SELECT reported_user_id, u.username, COUNT(*) AS report_count
         FROM reports r LEFT JOIN users u ON r.reported_user_id = u.id
         WHERE reported_user_id > 0 GROUP BY reported_user_id ORDER BY report_count DESC LIMIT 10"
    );

    $top_reporters_result = $db->sql_query_prepared(
        "SELECT addedby, u.username, COUNT(*) AS report_count
         FROM reports r LEFT JOIN users u ON r.addedby = u.id
         WHERE addedby > 0 GROUP BY addedby ORDER BY report_count DESC LIMIT 10"
    );

    ?>
    <div class="row">
        <?php foreach ([
            ['primary', 'Total Reports (30 days)', $stats['total']            ?? 0],
            ['success', 'Resolved',                $stats['resolved']         ?? 0],
            ['warning', 'Pending',                 $stats['pending']          ?? 0],
            ['info',    'Unique Reporters',        $stats['unique_reporters'] ?? 0],
        ] as [$color, $label, $value]): ?>
        <div class="col-md-3 mb-4">
            <div class="card bg-<?= $color ?> text-<?= $color === 'warning' ? 'dark' : 'white' ?>">
                <div class="card-body text-center py-4">
                    <div class="display-5 fw-bold"><?= $value ?></div>
                    <div><?= $label ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="fa-solid fa-chart-pie me-2"></i>Reports by Type (30 days)</h6></div>
                <div class="card-body">
                    <?php if ($type_stats_result && $db->num_rows($type_stats_result) > 0): ?>
                    <table class="table table-sm">
                        <thead><tr><th>Type</th><th>Total</th><th>Resolved</th><th>Pending</th><th>% Resolved</th></tr></thead>
                        <tbody>
                        <?php while ($row = $db->fetch_array($type_stats_result)):
                            $pending = (int)$row['count'] - (int)$row['resolved'];
                            $percent = $row['count'] > 0 ? round(((int)$row['resolved'] / (int)$row['count']) * 100) : 0;
                        ?>
                        <tr>
                            <td><span class="badge bg-<?= getTypeColor($row['type']) ?>"><?= ucfirst($row['type']) ?></span></td>
                            <td><?= $row['count'] ?></td>
                            <td><span class="text-success"><?= $row['resolved'] ?></span></td>
                            <td><span class="text-warning"><?= $pending ?></span></td>
                            <td>
                                <div class="progress" style="height:20px">
                                    <div class="progress-bar bg-success" style="width:<?= $percent ?>%"><?= $percent ?>%</div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?><p class="text-muted text-center">No type statistics available</p><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <?= renderTopUsersTable($top_reported_result, 'Most Reported Users', 'reported_user_id', 'fa-user-slash', 'danger') ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <?= renderTopUsersTable($top_reporters_result, 'Top Reporters', 'addedby', 'fa-user-check', 'info') ?>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h6></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= $_this_script_ ?>&action=pending" class="btn btn-warning">
                            <i class="fa-solid fa-clock me-2"></i>View Pending Reports
                        </a>
                        <a href="<?= $_this_script_ ?>&action=list&export=csv" class="btn btn-secondary">
                            <i class="fa-solid fa-file-export me-2"></i>Export All Reports (CSV)
                        </a>
                        <button class="btn btn-danger"
                                onclick="if(confirm('Clear all resolved reports?')) location.href='<?= htmlspecialchars($_this_script_) ?>&action=takeaction&do=clearold'">
                            <i class="fa-solid fa-broom me-2"></i>Clear Old Resolved Reports
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    foreach ([$stats_result, $type_stats_result, $top_reported_result, $top_reporters_result] as $r) {
        if ($r) $db->free_result($r);
    }
}

function renderTopUsersTable($result, string $title, string $id_field, string $icon, string $badge_color): string
{
    global $db, $_this_script_;

    ob_start(); ?>
    <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="fa-solid <?= $icon ?> me-2"></i><?= $title ?></h6></div>
        <div class="card-body">
            <?php if ($result && $db->num_rows($result) > 0): ?>
            <table class="table table-sm">
                <thead><tr><th>User</th><th>Count</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while ($row = $db->fetch_array($result)): ?>
                <tr>
                    <td><a href="user-<?= $row[$id_field] ?>.html" target="_blank"><?= htmlspecialchars($row['username'] ?? 'User #' . $row[$id_field]) ?></a></td>
                    <td><span class="badge bg-<?= $badge_color ?>"><?= $row['report_count'] ?></span></td>
                    <td><a href="<?= $_this_script_ ?>&action=list&search=<?= urlencode($row['username'] ?? '') ?>" class="btn btn-sm btn-outline-primary">View Reports</a></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?><p class="text-muted text-center">No data available</p><?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

function showReportDetails(int $report_id): void
{
    global $db, $_this_script_;

    if ($report_id <= 0) {
        echo '<div class="alert alert-danger">Invalid report ID</div>';
        return;
    }

    $result = $db->sql_query_prepared(
        "SELECT r.*,
                u1.username AS reporter_name, u1.email AS reporter_email,
                u2.username AS reported_user_name, u2.email AS reported_user_email,
                u3.username AS dealtby_name,
                t.name AS torrent_name,
                c.text AS comment_text, c.torrent AS comment_torrent_id,
                f.name AS forum_name, f.fid AS forum_db_id,
                th.subject AS thread_subject, th.tid AS thread_db_id
         FROM reports r
         LEFT JOIN users u1     ON r.addedby = u1.id
         LEFT JOIN users u2     ON r.reported_user_id = u2.id
         LEFT JOIN users u3     ON r.dealtby = u3.id
         LEFT JOIN torrents t   ON r.type = 'torrent'   AND r.reported_id = t.id
         LEFT JOIN comments c   ON r.type = 'comment'   AND r.reported_id = c.id
         LEFT JOIN forums f ON r.type = 'forumpost' AND r.forum_id = f.fid
         LEFT JOIN threads th ON r.type = 'forumpost' AND r.thread_id = th.tid
         WHERE r.id = ?",
        [$report_id]
    );

    if (!$result) { echo '<div class="alert alert-danger">Error loading report</div>'; return; }

    $report = $db->fetch_array($result);

    if (!$report) { echo '<div class="alert alert-danger">Report not found</div>'; return; }

    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Report Details #<?= $report['id'] ?></h5>
                    <div class="btn-group">
                        <?php if (!$report['dealtwith']): ?>
                        <a href="<?= $_this_script_ ?>&action=takeaction&do=resolve&id=<?= $report['id'] ?>" class="btn btn-success btn-sm">
                            <i class="fa-solid fa-check me-1"></i> Mark as Resolved
                        </a>
                        <?php endif; ?>
                        <a href="<?= $_this_script_ ?>&action=list" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to List
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?= renderReportDetails($report) ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0"><i class="fa-solid fa-cogs me-2"></i>Actions</h6></div>
                <div class="card-body"><?= renderActionForm($report['id'], $report['type']) ?></div>
            </div>
            <?php if ($report['reported_user_id']): ?>
            <?= renderUserReportStats($report['reported_user_id'], $report['reported_user_name']) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    if ($report['type'] === 'forumpost') echo renderForumPostDetails($report);
    if ($report['type'] === 'user')      echo renderUserReportDetails($report);

    $db->free_result($result);
}

function renderReportDetails(array $report): string
{
    global $BASEURL, $parser, $parser_options, $_this_script_;

    $reasons_map = get_report_reasons_map($report['type']);
    $reason_data = $reasons_map[$report['reason']] ?? null;

    $commentlink = $BASEURL . '/' . get_comment_link($report['reported_id'], $report['comment_torrent_id']) . '#pid' . $report['reported_id'];
    $torrentLink = $BASEURL . '/' . get_torrent_link($report['reported_id']);
    $adss        = my_datee('relative', $report['added']);
    $resolveds   = my_datee('relative', $report['updated_at']);

    ob_start(); ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <h6>Basic Information</h6>
            <table class="table table-sm">
                <tr><th width="40%">Report ID:</th><td>#<?= $report['id'] ?></td></tr>
                <tr>
                    <th>Type:</th>
                    <td>
                        <span class="badge bg-<?= getTypeColor($report['type']) ?>">
                            <i class="fa-solid <?= getTypeIcon($report['type']) ?> me-1"></i>
                            <?= ucfirst($report['type']) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Reported Item ID:</th>
                    <td>
                        <?= $report['reported_id'] ?>
                        <?php if ($report['type'] === 'torrent' && !empty($report['torrent_name'])): ?>
                        <a href="<?= $torrentLink ?>" class="btn btn-sm btn-outline-primary ms-2">
                            <i class="fa-solid fa-external-link-alt"></i> View Torrent
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr><th>Reason:</th><td><?= renderReasonBadge($reason_data, $report['reason']) ?></td></tr>
                <tr><th>Status:</th><td><?= renderStatusBadge((bool)$report['dealtwith']) ?></td></tr>
            </table>
        </div>

        <div class="col-md-6">
            <h6>Timestamps</h6>
            <table class="table table-sm">
                <tr><th width="40%">Reported:</th><td><?= $adss ?></td></tr>
                <tr>
                    <th>IP Address:</th>
                    <td>
                        <?= htmlspecialchars($report['ip_address'] ?? '') ?>
                        <?php if (!empty($report['ip_address'])): ?>
                        <a href="<?= $_this_script_ ?>&action=iplookup&ip=<?= urlencode($report['ip_address']) ?>" class="btn btn-sm btn-outline-info ms-1">
                            <i class="fa-solid fa-search"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($report['dealtwith']): ?>
                <tr><th>Resolved:</th><td><?= $resolveds ?></td></tr>
                <?php if ($report['dealtby_name']): ?>
                <tr><th>Resolved by:</th><td><?= htmlspecialchars($report['dealtby_name']) ?></td></tr>
                <?php endif; ?>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <?php if ($reason_data): ?>
    <?= renderPriorityAlert($reason_data, $report) ?>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-6">
            <h6>Reporter Information</h6>
            <table class="table table-sm">
                <tr>
                    <th width="40%">Username:</th>
                    <td>
                        <?php if ($report['addedby']): ?>
                        <a href="user-<?= $report['addedby'] ?>.html" target="_blank">
                            <?= htmlspecialchars($report['reporter_name'] ?? 'User #' . $report['addedby']) ?>
                        </a>
                        <?php else: ?><span class="text-muted">Guest</span><?php endif; ?>
                    </td>
                </tr>
                <tr><th>User ID:</th><td><?= $report['addedby'] ?: 'N/A' ?></td></tr>
                <?php if (!empty($report['reporter_email'])): ?>
                <tr><th>Email:</th><td><a href="mailto:<?= htmlspecialchars($report['reporter_email']) ?>"><?= htmlspecialchars($report['reporter_email']) ?></a></td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="col-md-6">
            <h6>Reported User</h6>
            <table class="table table-sm">
                <?php if ($report['reported_user_id']): ?>
                <tr>
                    <th width="40%">Username:</th>
                    <td>
                        <a href="user-<?= $report['reported_user_id'] ?>.html" target="_blank">
                            <?= htmlspecialchars($report['reported_user_name'] ?? 'User #' . $report['reported_user_id']) ?>
                        </a>
                    </td>
                </tr>
                <tr><th>User ID:</th><td><?= $report['reported_user_id'] ?></td></tr>
                <?php if (!empty($report['reported_user_email'])): ?>
                <tr><th>Email:</th><td><a href="mailto:<?= htmlspecialchars($report['reported_user_email']) ?>"><?= htmlspecialchars($report['reported_user_email']) ?></a></td></tr>
                <?php endif; ?>
                <?php else: ?>
                <tr><td colspan="2" class="text-muted text-center">No user information available</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <h6>Report Description</h6>
    <div class="card bg-light mb-3">
        <div class="card-body">
            <?php if (!empty($report['description'])): ?>
            <?= $parser->parse_message($report['description'], $parser_options) ?>
            <?php else: ?><span class="text-muted">No additional details provided</span><?php endif; ?>
        </div>
    </div>

    <?php if ($report['type'] === 'comment' && !empty($report['comment_text'])): ?>
    <?= renderCommentContent($report, $reason_data, $commentlink) ?>
    <?php endif; ?>

    <?php return ob_get_clean();
}

function renderReasonBadge(?array $reason_data, string $raw_reason): string
{
    if (!$reason_data) {
        return '<span class="badge bg-secondary"><i class="fa-solid fa-question me-1"></i>' . htmlspecialchars($raw_reason) . '</span>';
    }

    $sev_color = match ($reason_data['severity']) {
        'high'   => 'danger',
        'medium' => 'warning',
        'low'    => 'info',
        default  => 'secondary',
    };

    ob_start(); ?>
    <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="badge <?= $reason_data['color'] ?> text-white px-3 py-2">
            <i class="fa-solid <?= $reason_data['icon'] ?> me-2"></i>
            <span class="fw-medium"><?= htmlspecialchars($reason_data['text']) ?></span>
        </span>
        <?php if ($reason_data['severity'] !== 'unknown'): ?>
        <span class="badge bg-<?= $sev_color ?>-subtle text-<?= $sev_color ?> border border-<?= $sev_color ?>">
            <?= ucfirst($reason_data['severity']) ?> Priority
        </span>
        <?php endif; ?>
        <span class="badge bg-light text-dark border">
            <i class="fa-solid fa-tag me-1"></i><?= htmlspecialchars($reason_data['category']) ?>
        </span>
    </div>
    <?php return ob_get_clean();
}

function renderStatusBadge(bool $resolved): string
{
    return $resolved
        ? '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Resolved</span>'
        : '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending</span>';
}

function renderPriorityAlert(array $reason_data, array $report): string
{
    $sev      = $reason_data['severity'];
    $color    = match ($sev) { 'high' => 'danger', 'medium' => 'warning', default => 'info' };
    $icon     = match ($sev) { 'high' => 'fire',   'medium' => 'clock',   default => 'info-circle' };
    $headline = match ($sev) { 'high' => '🚨 High Priority Action Required', 'medium' => '⚠️ Medium Priority Review', default => 'ℹ️ Standard Review' };
    $urgency  = match ($sev) { 'high' => 'Requires immediate attention.', 'medium' => 'Review within 24 hours.', default => 'Review when available.' };

    $recommendation = REASON_RECOMMENDATIONS[$report['type']][$report['reason']]
        ?? 'Review based on provided information.';

    ob_start(); ?>
    <div class="alert alert-<?= $color ?> mt-3 mb-3">
        <div class="d-flex align-items-start">
            <i class="fa-solid fa-<?= $icon ?> fa-2x me-3 mt-1"></i>
            <div>
                <h6 class="alert-heading mb-2"><?= $headline ?></h6>
                <p class="mb-1"><strong>Recommended Action:</strong> <?= $recommendation ?></p>
                <p class="mb-0"><strong>Urgency:</strong> <?= $urgency ?></p>
                <hr class="my-2">
                <p class="mb-0 small">
                    <strong>Category:</strong> <?= htmlspecialchars($reason_data['category']) ?>
                    | <strong>Type:</strong> <?= ucfirst($report['type']) ?>
                    | <strong>Reported:</strong> <?= date('H:i', (int)$report['added']) ?>
                </p>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

function renderCommentContent(array $report, ?array $reason_data, string $commentlink): string
{
    global $parser, $parser_options, $_this_script_;

    $severity      = $reason_data['severity'] ?? 'low';
    $header_color  = $severity === 'high' ? 'danger' : 'warning';
    $delete_class  = in_array($severity, ['high', 'medium'], true) ? 'btn-danger' : 'btn-outline-light';

    ob_start(); ?>
    <h6>Comment Content</h6>
    <div class="card border-<?= $header_color ?> mb-3">
        <div class="card-header bg-<?= $header_color ?> text-white d-flex justify-content-between align-items-center">
            <span>
                <i class="fa-solid <?= $reason_data['icon'] ?? 'fa-comment' ?> me-1"></i>
                Reported Comment<?= $reason_data ? ': ' . $reason_data['text'] : '' ?>
            </span>
            <div class="btn-group btn-group-sm">
                <a href="<?= $commentlink ?>" class="btn btn-outline-light" target="_blank">
                    <i class="fa-solid fa-external-link-alt me-1"></i> View
                </a>
                <a href="<?= $_this_script_ ?>&action=takeaction&do=deletecomment&id=<?= $report['id'] ?>"
                   class="btn <?= $delete_class ?>" onclick="return confirm('Delete this comment?')">
                    <i class="fa-solid fa-trash me-1"></i> Delete
                </a>
                <?php if ($report['reported_user_id'] && $severity === 'high'): ?>
                <a href="warn.php?uid=<?= $report['reported_user_id'] ?>&reason=<?= urlencode($reason_data['text'] ?? '') ?>"
                   class="btn btn-warning" target="_blank">
                    <i class="fa-solid fa-exclamation-triangle me-1"></i> Warn
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?= $parser->parse_message($report['comment_text'], $parser_options) ?>
            <?php if (!empty($report['comment_torrent_id'])): ?>
            <div class="mt-3">
                <a href="<?= $commentlink ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-external-link-alt me-1"></i> View in Context
                </a>
                <a href="<?= $_this_script_ ?>&action=takeaction&do=deletecomment&id=<?= $report['id'] ?>"
                   class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this comment?')">
                    <i class="fa-solid fa-trash me-1"></i> Delete Comment
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

function renderForumPostDetails(array $report): string
{
    global $BASEURL, $parser, $parser_options, $_this_script_;

    $post_id   = (int)$report['reported_id'];
    $post_data = getForumPostData($post_id, $report);

    if (!$post_data) {
        return '<div class="alert alert-warning mt-4">Forum post data not found (post may have been deleted)</div>';
    }

    $postlink = $BASEURL . '/' . get_post_link($post_data['pid'], $post_data['thread_id']) . '#pid' . $post_data['pid'];
    $postdate = my_datee('relative', $post_data['dateline']);

    $rule_code = $post_data['rule_violation'] ?? '';
    $rule_data = isset(RULES_MAP[$rule_code]) ? RULES_MAP[$rule_code] : null;

    $visible_map = [0 => ['Deleted/Hidden', 'danger'], 1 => ['Visible', 'success'], 2 => ['Unapproved', 'warning']];

    ob_start(); ?>
    <div class="card mt-4 border-success">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fa-solid fa-comments me-2"></i> Forum Post Details</h6>
            <span class="badge bg-light text-dark"><i class="fa-solid fa-hashtag me-1"></i> Post ID: <?= $post_data['pid'] ?></span>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Post Information</h6>
                    <table class="table table-sm">
                        <tr><th width="40%">Post ID:</th><td>#<?= $post_data['pid'] ?></td></tr>
                        <tr>
                            <th>Author:</th>
                            <td>
                                <a href="user-<?= $post_data['author_id'] ?>.html" target="_blank" class="text-decoration-none">
                                    <i class="fa-solid fa-user me-1"></i>
                                    <?= htmlspecialchars($post_data['author_name'] ?? 'Unknown') ?>
                                </a>
                            </td>
                        </tr>
                        <tr><th>Post Date:</th><td><?= $postdate ?></td></tr>
                        <?php if (!empty($post_data['subject'])): ?>
                        <tr><th>Subject:</th><td><strong><?= htmlspecialchars($post_data['subject']) ?></strong></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Forum Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Forum:</th>
                            <td>
                                <a href="forumdisplay.php?fid=<?= $post_data['forum_id'] ?>" target="_blank" class="text-decoration-none">
                                    <i class="fa-solid fa-comments me-1"></i>
                                    <?= htmlspecialchars($post_data['forum_name'] ?? 'Unknown Forum') ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Thread:</th>
                            <td>
                                <a href="showthread.php?tid=<?= $post_data['thread_id'] ?>" target="_blank" class="text-decoration-none">
                                    <?= htmlspecialchars($post_data['thread_subject'] ?? 'Unknown Thread') ?>
                                </a>
                            </td>
                        </tr>
                        <tr><th>Thread ID:</th><td><?= $post_data['thread_id'] ?></td></tr>
                        <?php if ($rule_data): ?>
                        <tr>
                            <th>Rule Violation:</th>
                            <td>
                                <span class="badge <?= $rule_data['color'] ?> text-white">
                                    <i class="fa-solid <?= $rule_data['icon'] ?> me-1"></i>
                                    <?= htmlspecialchars($rule_data['text']) ?>
                                </span>
                                <?php if ($rule_code === 'rule_7'): ?>
                                <small class="text-muted ms-2"><i class="fa-solid fa-circle-info"></i> Posting multiple times in a row</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <h6>Post Content</h6>
            <div class="card border-warning mb-3">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-comment-dots me-1"></i> Reported Post Content</span>
                    <span class="badge bg-dark"><i class="fa-solid fa-eye me-1"></i> Views: <?= (int)$post_data['views'] ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($post_data['message'])): ?>
                    <div class="forum-post-content">
                        <?= $parser->parse_message($post_data['message'], $parser_options) ?>
                    </div>
                    <?php else: ?><div class="text-muted">Post content is empty</div><?php endif; ?>
                </div>
            </div>

            <h6>Post Actions</h6>
            <div class="row g-2">
                <div class="col-md-6">
                    <a href="<?= htmlspecialchars($postlink) ?>" target="_blank" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-external-link-alt me-1"></i> View in Forum
                    </a>
                </div>
                <div class="col-md-6">
                    <div class="dropdown">
                        <button class="btn btn-outline-danger w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-trash me-1"></i> Moderate Post
                        </button>
                        <ul class="dropdown-menu w-100">
                            <li>
                                <a class="dropdown-item text-danger" href="#"
                                   onclick="if(confirm('Delete this forum post permanently?')) window.location.href='<?= htmlspecialchars($_this_script_) ?>&action=takeaction&do=deleteforumpost&id=<?= $report['id'] ?>'">
                                    <i class="fa-solid fa-trash me-2"></i> Delete Post
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-warning" href="#"
                                   onclick="if(confirm('Edit this forum post?')) window.open('editpost.php?pid=<?= $post_data['pid'] ?>', '_blank')">
                                    <i class="fa-solid fa-edit me-2"></i> Edit Post
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-info" href="#"
                                   onclick="if(confirm('Warn the author of this post?')) window.open('warn.php?uid=<?= $post_data['author_id'] ?>', '_blank')">
                                    <i class="fa-solid fa-exclamation-triangle me-2"></i> Warn Author
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if (isset($post_data['visible'])): ?>
            <?php [$vis_text, $vis_color] = $visible_map[(int)$post_data['visible']] ?? ['Unknown', 'secondary']; ?>
            <div class="alert alert-info mt-3 small">
                <i class="fa-solid fa-info-circle me-1"></i>
                <strong>Post Status:</strong> <span class="badge bg-<?= $vis_color ?>"><?= $vis_text ?></span>
                <?php if (!empty($post_data['moderated'])): ?>
                <br><strong>Moderated:</strong> <?= htmlspecialchars($post_data['moderated']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <style>
    .forum-post-content { max-height: 400px; overflow-y: auto; padding: 15px; background: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6; }
    .forum-post-content img { max-width: 100%; height: auto; }
    .forum-post-content pre { background: #2b2b2b; color: #f8f8f2; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
    <?php return ob_get_clean();
}

function renderActionForm(int $report_id, string $report_type): string
{
    global $_this_script_;

    ob_start(); ?>
    <form method="POST" action="<?= $_this_script_ ?>&action=takeaction&id=<?= $report_id ?>">
        <input type="hidden" name="id" value="<?= $report_id ?>">
        <div class="mb-3">
            <label class="form-label">Action</label>
            <select name="do" class="form-select" required>
                <option value="">Select action...</option>
                <option value="resolve">Mark as Resolved</option>
                <?php if ($report_type === 'forumpost'): ?>
                <option value="deleteforumpost">Delete Forum Post</option>
                <?php elseif ($report_type === 'comment'): ?>
                <option value="deletecomment">Delete Comment</option>
                <?php endif; ?>
                <option value="warn_user">Warn Reported User</option>
                <option value="ban_user">Ban Reported User</option>
                <option value="ignore">Ignore Report</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Notes (Optional)</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Add notes about how this report was handled..."></textarea>
        </div>
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check me-1"></i> Apply Action
            </button>
            <a href="<?= $_this_script_ ?>&action=takeaction&do=delete&id=<?= $report_id ?>"
               class="btn btn-outline-danger" onclick="return confirm('Delete this report permanently?')">
                <i class="fa-solid fa-trash me-1"></i> Delete Report
            </a>
        </div>
    </form>
    <?php return ob_get_clean();
}

function renderUserReportStats(int $user_id, ?string $username): string
{
    global $db, $_this_script_;

    $r = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total_reports,
                SUM(CASE WHEN dealtwith = 1 THEN 1 ELSE 0 END) AS resolved,
                SUM(CASE WHEN dealtwith = 0 THEN 1 ELSE 0 END) AS pending
         FROM reports WHERE reported_user_id = ?",
        [$user_id]
    );

    $s = $r ? $db->fetch_array($r) : ['total_reports' => 0, 'resolved' => 0, 'pending' => 0];
    if ($r) $db->free_result($r);

    ob_start(); ?>
    <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="fa-solid fa-chart-bar me-2"></i>User Report History</h6></div>
        <div class="card-body">
            <div class="text-center">
                <div class="display-6 text-primary"><?= $s['total_reports'] ?></div>
                <div class="text-muted">Total Reports</div>
            </div>
            <div class="row mt-3">
                <div class="col-6 text-center">
                    <div class="text-success fw-bold"><?= $s['resolved'] ?></div>
                    <small class="text-muted">Resolved</small>
                </div>
                <div class="col-6 text-center">
                    <div class="text-warning fw-bold"><?= $s['pending'] ?></div>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
            <div class="mt-3">
                <a href="<?= $_this_script_ ?>&action=list&search=<?= urlencode($username ?? '') ?>"
                   class="btn btn-sm btn-outline-primary w-100">
                    <i class="fa-solid fa-list me-1"></i> View All Reports for this User
                </a>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

function renderUserReportDetails(array $report): string
{
    global $BASEURL, $parser, $parser_options, $_this_script_, $db;

    $user_id = (int)$report['reported_user_id'];

    $user_result = $db->sql_query_prepared(
        "SELECT u.*,
                COUNT(r2.id)                                          AS total_reports,
                COUNT(CASE WHEN r2.dealtwith = 1 THEN 1 END)         AS resolved_reports,
                COUNT(CASE WHEN r2.dealtwith = 0 THEN 1 END)         AS pending_reports
         FROM users u LEFT JOIN reports r2 ON u.id = r2.reported_user_id
         WHERE u.id = ? GROUP BY u.id",
        [$user_id]
    );

    $user_info = $user_result ? $db->fetch_array($user_result) : null;

    $recent_result = $db->sql_query_prepared(
        "SELECT r.*, u.username AS reporter_name FROM reports r
         LEFT JOIN users u ON r.addedby = u.id
         WHERE r.reported_user_id = ? AND r.id != ?
         ORDER BY r.added DESC LIMIT 5",
        [$user_id, $report['id']]
    );

    $parsed_data = parseUserReportDescription($report['description'] ?? '');

    ob_start(); ?>
    <?php if ($user_info): ?>
    <div class="card border-primary mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fa-solid fa-user-circle me-2"></i> User Information</h6>
            <a href="user-<?= $user_id ?>.html" target="_blank" class="btn btn-sm btn-light">
                <i class="fa-solid fa-external-link-alt me-1"></i> View Profile
            </a>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>User Details</h6>
                    <table class="table table-sm">
                        <tr><th width="40%">Username:</th><td><a href="user-<?= $user_id ?>.html" target="_blank" class="fw-bold"><?= htmlspecialchars($user_info['username'] ?? 'Unknown') ?></a></td></tr>
                        <tr><th>User ID:</th><td><?= $user_id ?></td></tr>
                        <tr>
                            <th>Email:</th>
                            <td>
                                <?php if (!empty($user_info['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($user_info['email']) ?>"><?= htmlspecialchars($user_info['email']) ?></a>
                                <?php else: ?><span class="text-muted">Not available</span><?php endif; ?>
                            </td>
                        </tr>
                        <tr><th>Registered:</th><td><?= my_datee('relative', $user_info['added']) ?></td></tr>
                        <tr><th>Status:</th><td><?= $user_info['enabled'] === 'yes' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Disabled</span>' ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Report Statistics</h6>
                    <table class="table table-sm">
                        <tr><th width="40%">Total Reports:</th><td><span class="badge bg-primary"><?= $user_info['total_reports'] ?? 0 ?></span></td></tr>
                        <tr><th>Resolved:</th><td><span class="badge bg-success"><?= $user_info['resolved_reports'] ?? 0 ?></span></td></tr>
                        <tr><th>Pending:</th><td><span class="badge bg-warning"><?= $user_info['pending_reports'] ?? 0 ?></span></td></tr>
                        <tr>
                            <th>Report Rate:</th>
                            <td><?php
                                $days = max(1, floor((time() - $user_info['added']) / 86400));
                                echo number_format(($user_info['total_reports'] ?? 0) / $days, 2);
                            ?> reports/day</td>
                        </tr>
                    </table>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= $_this_script_ ?>&action=list&search=<?= urlencode($user_info['username'] ?? '') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-list me-1"></i> View All Reports
                        </a>
                        <a href="warn.php?uid=<?= $user_id ?>&reason=<?= urlencode($report['reason']) ?>" class="btn btn-sm btn-outline-warning" target="_blank">
                            <i class="fa-solid fa-exclamation-triangle me-1"></i> Warn User
                        </a>
                        <button class="btn btn-sm btn-outline-danger"
                                onclick="if(confirm('Ban user <?= htmlspecialchars($user_info['username'] ?? '') ?>?')) window.open('bans.php?action=add&uid=<?= $user_id ?>', '_blank')">
                            <i class="fa-solid fa-ban me-1"></i> Ban User
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fa-solid fa-flag me-2"></i> Report Details</h6></div>
        <div class="card-body">
            <h6>Report Description</h6>
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <?= $parser->parse_message($parsed_data['formatted_description'] ?: ($report['description'] ?? ''), $parser_options) ?>
                </div>
            </div>

            <?php if (!empty($parsed_data['additional_info']) || !empty($parsed_data['evidence_links'])): ?>
            <h6>Additional Information</h6>
            <div class="row">
                <?php if (!empty($parsed_data['additional_info'])): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header bg-light"><h6 class="mb-0"><i class="fa-solid fa-info-circle me-2"></i>Additional Info</h6></div>
                        <div class="card-body"><?= nl2br(htmlspecialchars($parsed_data['additional_info'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($parsed_data['evidence_links'])): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header bg-light"><h6 class="mb-0"><i class="fa-solid fa-link me-2"></i>Evidence Links</h6></div>
                        <div class="card-body">
                            <?php foreach (array_filter(array_map('trim', explode("\n", $parsed_data['evidence_links']))) as $link): ?>
                            <div class="mb-2">
                                <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="text-decoration-none">
                                    <i class="fa-solid fa-external-link-alt me-1"></i>
                                    <?= htmlspecialchars(truncateString($link, 50)) ?>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($user_id && $recent_result && $db->num_rows($recent_result) > 0): ?>
            <h6>Recent Reports for This User</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Type</th><th>Reason</th><th>Reporter</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php while ($r = $db->fetch_array($recent_result)): ?>
                    <tr>
                        <td><?= date('Y-m-d', (int)$r['added']) ?></td>
                        <td><span class="badge bg-<?= getTypeColor($r['type']) ?>"><?= ucfirst($r['type']) ?></span></td>
                        <td><?= htmlspecialchars(truncateString($r['reason'], 20)) ?></td>
                        <td><?= htmlspecialchars($r['reporter_name'] ?? 'User #' . $r['addedby']) ?></td>
                        <td><?= renderStatusBadge((bool)$r['dealtwith']) ?></td>
                        <td><a href="<?= $_this_script_ ?>&action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($user_id): ?>
            <div class="alert alert-info"><i class="fa-solid fa-info-circle me-2"></i>This is the only report for this user.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-danger">
        <div class="card-header bg-danger text-white"><h6 class="mb-0"><i class="fa-solid fa-shield-alt me-2"></i>Moderation Actions</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <a href="warn.php?uid=<?= $user_id ?>&reason=Report%20#<?= $report['id'] ?>:<?= urlencode($report['reason']) ?>" class="btn btn-warning w-100" target="_blank">
                        <i class="fa-solid fa-exclamation-triangle me-1"></i> Issue Warning
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="edituser.php?action=edituser&userid=<?= $user_id ?>" class="btn btn-info w-100" target="_blank">
                        <i class="fa-solid fa-user-edit me-1"></i> Edit User
                    </a>
                </div>
                <div class="col-md-6">
                    <button class="btn btn-outline-danger w-100"
                            onclick="if(confirm('Temporarily suspend this user?')) window.open('staff.php?act=users&do=suspend&uid=<?= $user_id ?>', '_blank')">
                        <i class="fa-solid fa-clock me-1"></i> Suspend User
                    </button>
                </div>
                <div class="col-md-6">
                    <button class="btn btn-danger w-100"
                            onclick="if(confirm('Permanently ban this user?')) window.open('bans.php?action=add&uid=<?= $user_id ?>', '_blank')">
                        <i class="fa-solid fa-ban me-1"></i> Ban User
                    </button>
                </div>
            </div>

            <hr>

            <form method="POST" action="<?= $_this_script_ ?>&action=takeaction" class="mt-3">
                <input type="hidden" name="do" value="resolve">
                <input type="hidden" name="id" value="<?= $report['id'] ?>">
                <div class="mb-3">
                    <label class="form-label">Resolution Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Add notes about how this user report was handled..."></textarea>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-check me-1"></i> Mark as Resolved
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php
    if (isset($user_result))   $db->free_result($user_result);
    if (isset($recent_result)) $db->free_result($recent_result);

    return ob_get_clean();
}

// ==================== СТИЛИ И СКРИПТЫ ====================

function getReportListStyles(): string
{
    return '
<style>
.empty-state { padding: 2rem 0; text-align: center; }
.empty-state i { opacity: 0.5; transition: opacity 0.3s ease; margin-bottom: 1rem; }
.empty-state:hover i { opacity: 0.8; }
.empty-state h5 { font-weight: 500; color: #495057; }
.empty-state p { font-size: 0.95rem; max-width: 300px; margin: 0 auto 1rem; color: #6c757d; }
.table-hover tbody tr:hover { transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,.05); transition: all .2s ease; }
tr { transition: opacity .3s ease, transform .3s ease; }
.blink { animation: blink-animation 1s infinite; }
@keyframes blink-animation { 0%,50% { opacity:1; } 51%,100% { opacity:.5; } }
</style>';
}

function getDeleteModalScript(): string
{
    return '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const deleteModal = new bootstrap.Modal(document.getElementById("deleteModal"));
    let currentBtn = null, currentUrl = null;

    document.querySelectorAll(".delete-report").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            currentBtn  = this;
            currentUrl  = this.href;
            document.getElementById("reportIdText").textContent = "#" + this.dataset.id;
            deleteModal.show();
        });
    });

    document.getElementById("confirmDelete").addEventListener("click", function() {
        const spinner = this.querySelector(".spinner-border");
        spinner.classList.remove("d-none");
        this.disabled = true;

        fetch(currentUrl, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then(r => r.json())
            .then(data => {
                deleteModal.hide();
                if (data.success) {
                    const row = currentBtn.closest("tr");
                    if (row) {
                        row.style.transition = "all .3s ease";
                        row.style.opacity    = "0";
                        setTimeout(() => row.remove(), 300);
                    }
                    showToast(data.message || "Report deleted", "success");
                } else {
                    showToast(data.error || "Failed to delete report", "danger");
                }
            })
            .catch(() => showToast("Connection error, please try again", "warning"))
            .finally(() => { spinner.classList.add("d-none"); this.disabled = false; });
    });

    document.getElementById("deleteModal").addEventListener("hidden.bs.modal", () => { currentBtn = null; });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const msgs = { resolved:"Report marked as resolved", deleted:"Report deleted successfully", comment_deleted:"Comment deleted and report resolved" };
    const errs = { invalid_id:"Invalid report ID", not_found:"Report not found", invalid_action:"Invalid action" };
    if (urlParams.has("success") && msgs[urlParams.get("success")]) showToast(msgs[urlParams.get("success")], "success");
    if (urlParams.has("error")   && errs[urlParams.get("error")])   showToast(errs[urlParams.get("error")],   "danger");
});
</script>

<script type="text/javascript" src="<?= htmlspecialchars($BASEURL ?? "") ?>/scripts/toast.js"></script>
<script type="text/javascript" src="<?= htmlspecialchars($BASEURL ?? "") ?>/scripts/popover.js"></script>

<!-- Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete report <strong id="reportIdText"></strong>?</p>
                <p class="text-danger small"><i class="bi bi-info-circle me-1"></i>This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    <i class="bi bi-trash"></i> Delete Report
                </button>
            </div>
        </div>
    </div>
</div>';
}