<?php
// ============================================================
//  POLL MANAGER
// ============================================================
// Берём реальную настройку сайта, если она задана - иначе безопасный дефолт.
$per_page = (int)($ts_perpage ?? 25);
if ($per_page <= 0) $per_page = 25;

if (!defined('STAFF_PANEL')) {
    die('Direct initialization of this file is not allowed.');
}

/**
 * Экранирует только LIKE-wildcard'ы (%, _, \) — для bind-параметров.
 */
function mp_escape_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    die('You do not have permission to access this page.');
}

// global.php не стартует нативную PHP-сессию сама (та же история, что и в
// member.php/report_captcha.php) — без этого $_SESSION['csrf'] не переживёт
// между запросом формы и её отправкой, и все POST-действия ниже будут
// постоянно валиться с "CSRF error" даже у легитимных запросов.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lang->load("polls");



function h(string $s): string { 
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); 
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die('CSRF error');
    }
}

function paginate(int $total, int $page, int $per): array {
    $pages = max(1, (int)ceil($total / $per));
    $page  = max(1, min($page, $pages));
    return [
        'total' => $total, 
        'page' => $page, 
        'pages' => $pages,
        'offset' => ($page - 1) * $per, 
        'limit' => $per
    ];
}

function self_url(array $merge = []): string {
    return '?' . http_build_query(array_merge($_GET, $merge));
}

function opt_label(string $options, int $voteoption): string {
    $opts = explode('||~|~||', $options);
    return trim($opts[$voteoption - 1] ?? ('Option ' . $voteoption));
}

$page = $_GET['p'] ?? 'polls';

// ============================================================
//  ACTIONS (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    csrf_check();

    // --- Poll: create ---
    if ($action === 'poll_create') {
        $errors = [];
        $q          = trim($_POST['question'] ?? '');
        $opts       = array_values(array_filter(array_map('trim', $_POST['options'] ?? []), fn($v) => $v !== ''));
        $to         = max(0, (int)($_POST['timeout'] ?? 0));
        $mo         = max(0, (int)($_POST['maxoptions'] ?? 0));
        $cl         = !empty($_POST['closed'])   ? 1 : 0;
        $mu         = !empty($_POST['multiple']) ? 1 : 0;
        $pu         = !empty($_POST['public'])   ? 1 : 0;
        $fid        = (int)($_POST['fid'] ?? 0);
        $thread_title = trim($_POST['thread_title'] ?? '');

        if ($q === '') $errors[] = 'Enter a question.';
        if (my_strlen($q) > 200) $errors[] = 'Question cannot exceed 200 characters.';
        if (count($opts) < 2) $errors[] = 'Minimum 2 options required.';
        if (count($opts) > 20) $errors[] = 'Maximum 20 options allowed.';
        if ($thread_title === '') $errors[] = 'Enter a thread title.';

        $forum = $fid > 0 ? get_forum($fid) : null;
        if (!$forum) $errors[] = 'Select a valid forum.';

        if (empty($errors)) {
            $options_str  = implode('||~|~||', $opts);
            $votes_str    = implode('||~|~||', array_fill(0, count($opts), '0'));
            $uid          = (int)$CURUSER['id'];

            // 1) Опрос (tid проставим после создания треда)
            $db->sql_query_prepared(
                "INSERT INTO polls (tid, question, dateline, options, votes, numoptions, numvotes, timeout, closed, multiple, public, maxoptions)
                VALUES (0, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)",
                [$q, TIMENOW, $options_str, $votes_str, count($opts), $to, $cl, $mu, $pu, $mo]
            );
            $pollid = (int)$db->insert_id();

            // 2) Тред (поля — точно как в post.php::insert_thread(), poll проставим отдельным UPDATE ниже)
            $db->sql_query_prepared(
                "INSERT INTO threads (fid, subject, uid, username, dateline, lastpost, lastposter, lastposteruid, views, replies, visible, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, '')",
                [$fid, $thread_title, $uid, $CURUSER['username'], TIMENOW, TIMENOW, $CURUSER['username'], $uid]
            );
            $newtid = (int)$db->insert_id();

            // 3) Первый пост треда (те же поля, что и post.php::insert_thread() для первого поста)
            $message = '[b]' . $q . '[/b]' . "\n\nPlease vote in the poll above.";
            $ip_bin = my_inet_pton(get_ip());
            $db->sql_query_prepared(
                "INSERT INTO posts (tid, fid, subject, uid, username, dateline, message, ipaddress, visible)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [$newtid, $fid, $thread_title, $uid, $CURUSER['username'], TIMENOW, $message, $ip_bin]
            );
            $newpid = (int)$db->insert_id();

            // 4) Замыкаем связи и пересчитываем счётчики
            $db->sql_query_prepared("UPDATE threads SET firstpost = ?, poll = ? WHERE tid = ?", [$newpid, $pollid, $newtid]);
            $db->sql_query_prepared("UPDATE polls SET tid = ? WHERE pid = ?", [$newtid, $pollid]);

            update_forum_counters($fid, ['threads' => '+1', 'posts' => '+1']);
            update_forum_lastpost($fid);
            if (($forum['usepostcounts'] ?? 0) != 0) {
                update_user_counters($uid, ['postnum' => '+1']);
            }
            if (($forum['usethreadcounts'] ?? 0) != 0) {
                update_user_counters($uid, ['threadnum' => '+1']);
            }

            write_log("Poll created: #{$pollid} \"{$q}\" — new thread #{$newtid} in forum #{$fid} by {$CURUSER['username']}");
            flash_message('Poll and thread created successfully.', 'success');
            header('Location: '.$_this_script_.'&p=polls'); 
            exit;
        }
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data']   = $_POST;
        header('Location: '.$_this_script_.'&p=poll_create'); 
        exit;
    }

    // --- Poll: edit save ---
    if ($action === 'poll_edit') {
        $errors = [];
        $pid    = (int)($_POST['pid'] ?? 0);
        $q      = trim($_POST['question'] ?? '');
        $opts   = array_values(array_filter(array_map('trim', $_POST['options'] ?? []), fn($v) => $v !== ''));
        $vts_in = $_POST['votes'] ?? [];
        $votes  = [];
        foreach (($_POST['options'] ?? []) as $i => $opt) {
            if (trim($opt) !== '') $votes[] = max(0, (int)($vts_in[$i] ?? 0));
        }
        $to = max(0, (int)($_POST['timeout'] ?? 0));
        $mo = max(0, (int)($_POST['maxoptions'] ?? 0));
        $cl = !empty($_POST['closed'])   ? 1 : 0;
        $mu = !empty($_POST['multiple']) ? 1 : 0;
        $pu = !empty($_POST['public'])   ? 1 : 0;

        if ($q === '') $errors[] = 'Enter a question.';
        if (my_strlen($q) > 200) $errors[] = 'Question cannot exceed 200 characters.';
        if (count($opts) < 2) $errors[] = 'Minimum 2 options required.';

        if (empty($errors)) {
            $options_str = implode('||~|~||', $opts);
            $votes_str = implode('||~|~||', $votes);
            $total_votes = array_sum($votes);
            
            $db->sql_query_prepared(
                "UPDATE polls SET 
                    question = ?,
                    options = ?,
                    votes = ?,
                    numoptions = ?,
                    numvotes = ?,
                    timeout = ?,
                    closed = ?,
                    multiple = ?,
                    public = ?,
                    maxoptions = ?
                WHERE pid = ?",
                [$q, $options_str, $votes_str, count($opts), $total_votes, $to, $cl, $mu, $pu, $mo, $pid]
            );
            flash_message('Changes saved.', 'success');
            header('Location: '.$_this_script_.'&p=poll_edit&pid=' . $pid); 
            exit;
        }
        $_SESSION['form_errors'] = $errors;
        header('Location: '.$_this_script_.'&p=poll_edit&pid=' . $pid);
        exit;
    }

    // --- Poll: toggle ---
    if ($action === 'poll_toggle') {
        $pid = (int)($_POST['pid'] ?? 0);
        $query = $db->sql_query_prepared("SELECT closed FROM polls WHERE pid = ?", [$pid]);
        $cur = $query ? (int)($db->fetch_field($query, 'closed')) : 0;
        $new_status = $cur ? 0 : 1;
        $db->sql_query_prepared("UPDATE polls SET closed = ? WHERE pid = ?", [$new_status, $pid]);
        flash_message($cur ? 'Poll opened.' : 'Poll closed.', 'success');
        header('Location: '.$_this_script_.'&p=polls'); 
        exit;
    }

    // --- Poll: delete ---
    if ($action === 'poll_delete') {
        $pid = (int)($_POST['pid'] ?? 0);
        
        // Обновляем темы, где был этот полл
        $db->sql_query_prepared("UPDATE threads SET poll = 0 WHERE poll = ?", [$pid]);
        // Удаляем голоса
        $db->sql_query_prepared("DELETE FROM pollvotes WHERE pid = ?", [$pid]);
        // Удаляем сам полл
        $db->sql_query_prepared("DELETE FROM polls WHERE pid = ?", [$pid]);
        
        flash_message('Poll #' . $pid . ' and all its votes deleted.', 'success');
        header('Location: '.$_this_script_.'&p=polls'); 
        exit;
    }

    // --- Poll: bulk close ---
    if ($action === 'poll_bulk_close') {
        $pids = array_filter(array_map('intval', $_POST['pids'] ?? []));
        if ($pids) {
            $ph = implode(',', array_fill(0, count($pids), '?'));
            $db->sql_query_prepared("UPDATE polls SET closed = 1 WHERE pid IN ({$ph})", $pids);
            flash_message('Closed ' . count($pids) . ' poll(s).', 'success');
        }
        header('Location: '.$_this_script_.'&p=polls');
        exit;
    }

    // --- Poll: bulk delete ---
    if ($action === 'poll_bulk_delete') {
        $pids = array_filter(array_map('intval', $_POST['pids'] ?? []));
        if ($pids) {
            $ph = implode(',', array_fill(0, count($pids), '?'));
            // Та же последовательность, что и в одиночном poll_delete —
            // отвязать треды, удалить голоса, удалить сами опросы
            $db->sql_query_prepared("UPDATE threads SET poll = 0 WHERE poll IN ({$ph})", $pids);
            $db->sql_query_prepared("DELETE FROM pollvotes WHERE pid IN ({$ph})", $pids);
            $db->sql_query_prepared("DELETE FROM polls WHERE pid IN ({$ph})", $pids);
            flash_message('Deleted ' . count($pids) . ' poll(s) and all their votes.', 'success');
        }
        header('Location: '.$_this_script_.'&p=polls');
        exit;
    }

    // --- Vote: delete single ---
    if ($action === 'vote_delete') {
        $vid = (int)($_POST['vid'] ?? 0);
        $db->sql_query_prepared("DELETE FROM pollvotes WHERE vid = ?", [$vid]);
        flash_message('Vote #' . $vid . ' deleted.', 'success');
        $back = $_POST['back'] ?? '?p=votes';
        header('Location: ' . $back); 
        exit;
    }

    // --- Vote: bulk delete ---
    if ($action === 'vote_bulk_delete') {
        $vids = array_filter(array_map('intval', $_POST['vids'] ?? []));
        if ($vids) {
            $ph = implode(',', array_fill(0, count($vids), '?'));
            $db->sql_query_prepared("DELETE FROM pollvotes WHERE vid IN ({$ph})", $vids);
            flash_message('Deleted votes: ' . count($vids) . '.', 'success');
        }
        $back = $_POST['back'] ?? '?p=votes';
        header('Location: ' . $back); 
        exit;
    }

    // --- Vote: delete all votes for a user ---
    if ($action === 'vote_delete_user') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid > 0) {
            $count_query = $db->sql_query_prepared("SELECT COUNT(*) FROM pollvotes WHERE uid = ?", [$uid]);
            $count = $count_query ? (int)$db->fetch_field($count_query, 'COUNT(*)') : 0;
            $db->sql_query_prepared("DELETE FROM pollvotes WHERE uid = ?", [$uid]);
            flash_message('Deleted ' . $count . ' vote(s) for user #' . $uid . '.', 'success');
        }
        $back = $_POST['back'] ?? '?p=votes_by_user';
        header('Location: ' . $back);
        exit;
    }
}

// ============================================================
//  DATA for current page
// ============================================================

// ---- POLLS LIST ----
if ($page === 'polls') {
    $stats_query = $db->sql_query_prepared("SELECT COUNT(*) AS total, SUM(closed=0) AS open_c, SUM(closed=1) AS closed_c, SUM(numvotes) AS tvotes FROM polls");
    $stats = $stats_query ? $db->fetch_array($stats_query) : null;

    $where = '1';
    $where_params = [];
    $search = trim($_GET['q'] ?? '');
    if ($search !== '') { 
        $where .= " AND p.question LIKE ?"; 
        $where_params[] = '%' . mp_escape_like($search) . '%';
    }
    $fs = $_GET['status'] ?? '';
    if ($fs === 'open') $where .= ' AND p.closed=0';
    if ($fs === 'closed') $where .= ' AND p.closed=1';
    $ft = $_GET['type'] ?? '';
    if ($ft === 'multi') $where .= ' AND p.multiple=1';
    if ($ft === 'public') $where .= ' AND p.public=1';
    $linked = $_GET['linked'] ?? '';
    if ($linked === 'yes') {
        $where .= ' AND t.tid IS NOT NULL';
    } elseif ($linked === 'no') {
        $where .= ' AND t.tid IS NULL';
    }

    $total_query = $db->sql_query_prepared("
        SELECT COUNT(*) FROM polls p 
        LEFT JOIN threads t ON (p.pid = t.poll)
        WHERE {$where}
    ", $where_params);
    $total = $total_query ? (int)$db->fetch_field($total_query, 'COUNT(*)') : 0;
    $pg = paginate($total, (int)($_GET['page'] ?? 1), $per_page);

    $polls_query = $db->sql_query_prepared("
        SELECT p.*, 
               (SELECT COUNT(*) FROM pollvotes v WHERE v.pid = p.pid) AS rv,
               t.subject AS thread_subject,
               t.tid AS thread_tid
        FROM polls p 
        LEFT JOIN threads t ON (p.pid = t.poll)
        WHERE {$where} 
        ORDER BY p.dateline DESC 
        LIMIT ?, ?
    ", [...$where_params, $pg['offset'], $pg['limit']]);
    $polls = [];
    while ($polls_query && ($row = $db->fetch_array($polls_query))) {
        $polls[] = $row;
    }
}

// ---- POLL CREATE ----
if ($page === 'poll_create') {
    $form_errors = $_SESSION['form_errors'] ?? [];
    $form_data   = $_SESSION['form_data']   ?? [];
    unset($_SESSION['form_errors'], $_SESSION['form_data']);
    $opts_val = array_filter(array_map('trim', $form_data['options'] ?? ['', '']));
    if (empty($opts_val)) $opts_val = ['', ''];

    // Список форумов для выбора, куда создать тред опроса (только реальные
    // форумы, не категории — type='f', как и везде в остальном коде проекта)
    $forums_list = [];
    $forums_query = $db->sql_query_prepared("SELECT fid, name FROM forums WHERE type='f' AND active!=0 ORDER BY name ASC");
    while ($forums_query && ($frow = $db->fetch_array($forums_query))) {
        $forums_list[] = $frow;
    }
}

// ---- POLL EDIT ----
if ($page === 'poll_edit') {
    $pid = (int)($_GET['pid'] ?? 0);
    $poll_query = $db->sql_query_prepared("SELECT * FROM polls WHERE pid = ?", [$pid]);
    $poll_row = $poll_query ? $db->fetch_array($poll_query) : null;
    if (!$poll_row) { 
        flash_message('Poll not found.', 'error'); 
        header('Location: ?p=polls'); 
        exit; 
    }
    $form_errors = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_errors']);
    $edit_opts = explode('||~|~||', $poll_row['options']);
    $edit_vts  = explode('||~|~||', $poll_row['votes']);
}

// ---- VOTES ----
if ($page === 'votes') {
    $vwhere = '1';
    $vwhere_params = [];
    $vsearch = trim($_GET['q'] ?? '');
    if ($vsearch !== '') { 
        $search_like = '%' . mp_escape_like($vsearch) . '%';
        $vwhere .= " AND (v.uid LIKE ? OR v.ipaddress LIKE ? OR p.question LIKE ?)"; 
        array_push($vwhere_params, $search_like, $search_like, $search_like);
    }
    $vpid = (int)($_GET['pid'] ?? 0);
    if ($vpid) { $vwhere .= " AND v.pid = ?"; $vwhere_params[] = $vpid; }
    $vuid = (int)($_GET['uid'] ?? 0);
    if ($vuid) { $vwhere .= " AND v.uid = ?"; $vwhere_params[] = $vuid; }

    $total_query = $db->sql_query_prepared("
        SELECT COUNT(*) FROM pollvotes v 
        LEFT JOIN polls p ON p.pid = v.pid 
        WHERE {$vwhere}
    ", $vwhere_params);
    $vtotal = $total_query ? (int)$db->fetch_field($total_query, 'COUNT(*)') : 0;
    $vpg = paginate($vtotal, (int)($_GET['page'] ?? 1), $per_page);

    $votes_query = $db->sql_query_prepared("
        SELECT v.*, p.question, p.options 
        FROM pollvotes v 
        LEFT JOIN polls p ON p.pid = v.pid 
        WHERE {$vwhere} 
        ORDER BY v.dateline DESC 
        LIMIT ?, ?
    ", [...$vwhere_params, $vpg['offset'], $vpg['limit']]);
    $votes = [];
    while ($votes_query && ($row = $db->fetch_array($votes_query))) {
        $votes[] = $row;
    }

    $all_polls = [];
    $all_polls_query = $db->sql_query_prepared("SELECT pid, question FROM polls ORDER BY dateline DESC");
    while ($all_polls_query && ($row = $db->fetch_array($all_polls_query))) {
        $all_polls[] = $row;
    }

    // Bar chart if filtered by poll
    $poll_stats = null;
    $pd = null;
    if ($vpid) {
        $pd_query = $db->sql_query_prepared("SELECT * FROM polls WHERE pid = ?", [$vpid]);
        $pd = $pd_query ? $db->fetch_array($pd_query) : null;
        if ($pd) {
            $poll_stats = [];
            $options_arr = explode('||~|~||', $pd['options']);
            $ptot = 0;
            foreach ($options_arr as $i => $opt) {
                $count_query = $db->sql_query_prepared("SELECT COUNT(*) FROM pollvotes WHERE pid = ? AND voteoption = ?", [$vpid, $i + 1]);
                $cnt = $count_query ? (int)$db->fetch_field($count_query, 'COUNT(*)') : 0;
                $poll_stats[] = ['label' => trim($opt), 'count' => $cnt];
                $ptot += $cnt;
            }
        }
    }
}

// ---- VOTES BY USER ----
if ($page === 'votes_by_user') {
    $bwhere = '1';
    $bwhere_params = [];
    $bsearch = trim($_GET['q'] ?? '');
    if ($bsearch !== '') { 
        $search_like = '%' . mp_escape_like($bsearch) . '%';
        $bwhere .= " AND (v.uid LIKE ? OR v.ipaddress LIKE ?)"; 
        array_push($bwhere_params, $search_like, $search_like);
    }
    $buid = (int)($_GET['uid'] ?? 0);
    if ($buid) { $bwhere .= " AND v.uid = ?"; $bwhere_params[] = $buid; }

    $by_users_query = $db->sql_query_prepared("
    SELECT v.uid, 
           COUNT(*) AS vc, 
           COUNT(DISTINCT v.pid) AS pc,
           MAX(v.dateline) AS lv, 
           SUBSTRING_INDEX(GROUP_CONCAT(v.ipaddress ORDER BY v.dateline DESC SEPARATOR '|'), '|', 1) AS lip,
           GROUP_CONCAT(DISTINCT v.pid ORDER BY v.pid SEPARATOR ',') AS pids
    FROM pollvotes v 
    WHERE {$bwhere} 
    GROUP BY v.uid 
    ORDER BY lv DESC
", $bwhere_params);
	
	
    $by_users = [];
    while ($by_users_query && ($row = $db->fetch_array($by_users_query))) {
        $by_users[] = $row;
    }

    $user_detail = null;
    if ($buid && count($by_users) === 1) {
        $detail_query = $db->sql_query_prepared("
            SELECT v.*, p.question, p.options 
            FROM pollvotes v 
            LEFT JOIN polls p ON p.pid = v.pid 
            WHERE v.uid = ? 
            ORDER BY v.dateline DESC
        ", [$buid]);
        $user_detail = [];
        while ($row = $db->fetch_array($detail_query)) {
            $user_detail[] = $row;
        }
    }
}

// ============================================================
//  LAYOUT
// ============================================================
$titles = [
    'polls' => 'Poll Manager',
    'poll_create' => 'Create Poll',
    'poll_edit' => 'Edit Poll',
    'votes' => 'Votes Manager',
    'votes_by_user' => 'Votes by User'
];
$title = $titles[$page] ?? 'Poll Admin';

stdhead($title);



?>

<div class="container mt-3">

<?php flash_message(); // Выводим flash сообщения ?>

<?php if ($page === 'polls'): ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['primary','Total Polls',$stats['total'],'clipboard-list'],
        ['success','Open',$stats['open_c'],'unlock'],
        ['danger','Closed',$stats['closed_c'],'lock'],
        ['warning','Total Votes',number_format((int)$stats['tvotes'],0,'.',' '),'thumbtack'],
    ] as [$color,$label,$val,$icon]): ?>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small text-uppercase"><?= $label ?></span>
                        <h2 class="mb-0 mt-1 fw-bold"><?= h((string)$val) ?></h2>
                    </div>
                    <div class="rounded-circle p-3" style="background: <?= $color === 'primary' ? '#0d6efd20' : ($color === 'success' ? '#19875420' : ($color === 'danger' ? '#dc354520' : '#ffc10720')) ?>">
                        <i class="fas fa-<?= $icon ?> fa-2x text-<?= $color ?>"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Search Form -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="p" value="polls">
            <div class="col-md-4">
                <label class="form-label small text-muted fw-semibold">🔍 Search</label>
                <input type="search" name="q" class="form-control" placeholder="Question text..." value="<?= h($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted fw-semibold">📊 Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="open" <?= $fs === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="closed" <?= $fs === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted fw-semibold">🏷️ Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="multi" <?= $ft === 'multi' ? 'selected' : '' ?>>Multiple choice</option>
                    <option value="public" <?= $ft === 'public' ? 'selected' : '' ?>>Public</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted fw-semibold">🔗 Linked</label>
                <select name="linked" class="form-select">
                    <option value="">All</option>
                    <option value="yes" <?= ($_GET['linked'] ?? '') === 'yes' ? 'selected' : '' ?>>Linked to thread</option>
                    <option value="no" <?= ($_GET['linked'] ?? '') === 'no' ? 'selected' : '' ?>>Orphaned (no thread)</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
                <a href="<?php echo $_this_script_; ?>&p=polls" class="btn btn-outline-secondary ms-1"><i class="fas fa-undo me-1"></i>Reset</a>
            </div>
            <div class="col-md-auto ms-auto">
                <a href="<?php echo $_this_script_; ?>&p=poll_create" class="btn btn-success"><i class="fas fa-plus me-1"></i>Create Poll</a>
            </div>
        </form>
    </div>
</div>

<!-- Polls Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 34px">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="pollsSelectAll" onclick="togglePollsAll(this)">
                        </div>
                    </th>
                    <th style="width: 50px">#</th>
                    <th>Question</th>
                    <th style="width: 200px">Thread</th>
                    <th style="width: 80px">Votes</th>
                    <th style="width: 70px">Opts</th>
                    <th style="width: 90px">Status</th>
                    <th style="width: 90px">Expires</th>
                    <th style="width: 120px">Date</th>
                    <th style="width: 130px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$polls): ?>
            <tr>
                <td colspan="10" class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-2 d-block opacity-25"></i>
                    No polls found
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($polls as $p):
                $exp_str = '∞';
                if ((int)$p['timeout'] > 0) {
                    $diff = ((int)$p['dateline'] + (int)$p['timeout'] * 86400) - TIMENOW;
                    if ($diff < 0) $exp_str = '<span class="badge bg-danger">Expired</span>';
                    elseif ($diff < 86400) $exp_str = '<span class="badge bg-warning">&lt;1d</span>';
                    else $exp_str = '<span class="badge bg-secondary">' . round($diff/86400) . 'd</span>';
                }
            ?>
            <tr>
                <td>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input pollCheckbox" type="checkbox" role="switch" name="pids[]" value="<?= (int)$p['pid'] ?>" form="bulkPollsForm">
                    </div>
                </td>
                <td class="text-muted"><?= (int)$p['pid'] ?></td>
                <td>
                    <div class="fw-semibold" style="max-width: 300px; overflow-wrap: break-word;"><?= h($p['question']) ?></div>
                    <div class="mt-1">
                        <?php 
                        $opts_arr = explode('||~|~||', $p['options']);
                        foreach ($opts_arr as $opt): ?>
                        <span class="badge bg-light text-dark me-1"><?= h(my_substr(trim($opt), 0, 20)) ?><?= my_strlen(trim($opt)) > 20 ? '…' : '' ?></span>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td>
                    <?php if (!empty($p['thread_tid']) && !empty($p['thread_subject'])): ?>
                        <a href="../showthread.php?tid=<?= (int)$p['thread_tid'] ?>" target="_blank" class="text-decoration-none">
                            <i class="fas fa-comment me-1"></i>
                            <?= h(my_substr($p['thread_subject'], 0, 35)) ?>
                            <?= my_strlen($p['thread_subject']) > 35 ? '…' : '' ?>
                        </a>
                        <br>
                        <small class="text-muted">TID: <?= (int)$p['thread_tid'] ?></small>
                    <?php else: ?>
                        <span class="text-muted">
                            <i class="fas fa-chain-broken me-1"></i>
                            Orphaned (no thread)
                        </span>
                    <?php endif; ?>
                </td>
                <td class="fw-semibold"><?= number_format((int)$p['rv'], 0, '.', ' ') ?></td>
                <td><?= (int)$p['numoptions'] ?></td>
                <td>
                    <?php if ($p['closed']): ?>
                        <span class="badge bg-danger">Closed</span>
                    <?php else: ?>
                        <span class="badge bg-success">Open</span>
                    <?php endif; ?>
                    <?php if ($p['multiple']): ?>
                        <span class="badge bg-info">multi</span>
                    <?php endif; ?>
                </td>
                <td><?= $exp_str ?></td>
                <td class="text-muted small"><?= date('d.m.Y H:i', (int)$p['dateline']) ?></td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="<?php echo $_this_script_; ?>&p=votes&pid=<?= (int)$p['pid'] ?>" class="btn btn-outline-secondary" title="View votes">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?php echo $_this_script_; ?>&p=poll_edit&pid=<?= (int)$p['pid'] ?>" class="btn btn-outline-primary" title="Edit">
                            <i class="fas fa-pencil-alt"></i>
                        </a>
                        <form method="post" class="d-inline" onsubmit="return confirm('<?= $p['closed'] ? 'Open this poll?' : 'Close this poll?' ?>')">
                            <input type="hidden" name="csrf" value="<?= csrf() ?>">
                            <input type="hidden" name="action" value="poll_toggle">
                            <input type="hidden" name="pid" value="<?= (int)$p['pid'] ?>">
                            <button class="btn btn-outline-<?= $p['closed'] ? 'success' : 'warning' ?>" title="<?= $p['closed'] ? 'Open' : 'Close' ?>">
                                <i class="fas fa-<?= $p['closed'] ? 'unlock' : 'lock' ?>"></i>
                            </button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete poll #<?= (int)$p['pid'] ?> and all votes?')">
                            <input type="hidden" name="csrf" value="<?= csrf() ?>">
                            <input type="hidden" name="action" value="poll_delete">
                            <input type="hidden" name="pid" value="<?= (int)$p['pid'] ?>">
                            <button class="btn btn-outline-danger" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="submitBulkPolls('poll_bulk_close')">
                <i class="fas fa-lock me-1"></i>Close Selected
            </button>
            <button type="button" class="btn btn-danger btn-sm" onclick="submitBulkPolls('poll_bulk_delete')">
                <i class="fas fa-trash-alt me-1"></i>Delete Selected
            </button>
        </div>
    <?php if ($pg['pages'] > 1): ?>
        <div class="d-flex align-items-center gap-3">
        <small class="text-muted"><?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['limit'], $total) ?> of <?= $total ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
                <li class="page-item <?= $i === $pg['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="<?= self_url(['page' => $i]) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<form method="post" id="bulkPollsForm">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="action" id="bulkPollsAction" value="">
</form>

<script>
function togglePollsAll(source) {
    document.querySelectorAll('.pollCheckbox').forEach(cb => cb.checked = source.checked);
}
function submitBulkPolls(action) {
    const checked = document.querySelectorAll('.pollCheckbox:checked');
    if (checked.length === 0) {
        alert('Select at least one poll first.');
        return;
    }
    const label = action === 'poll_bulk_delete' ? 'delete' : 'close';
    if (!confirm('Are you sure you want to ' + label + ' ' + checked.length + ' selected poll(s)?')) return;
    document.getElementById('bulkPollsAction').value = action;
    document.getElementById('bulkPollsForm').submit();
}
</script>

<?php elseif ($page === 'poll_create'): ?>

<div class="card border-0 shadow-sm rounded-3 mx-auto" style="max-width: 1140px;">
    <div class="card-header bg-transparent border-0 pt-4">
        <h3 class="mb-0"><i class="fas fa-plus-circle me-2 text-success"></i>Create New Poll</h3>
        <small class="text-muted">Fill in the details below to create a new poll</small>
    </div>
    <div class="card-body">
        <?php if (!empty($form_errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($form_errors as $e): ?>
                <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <form method="post" id="pollForm">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="poll_create">
            
            <div class="mb-4">
                <label class="form-label fw-semibold">Question <span class="text-danger">*</span></label>
                <input type="text" name="question" id="pollQuestion" class="form-control form-control-lg" maxlength="200" required value="<?= h(trim($form_data['question'] ?? '')) ?>" placeholder="e.g., What is your favorite color?">
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Forum <span class="text-danger">*</span></label>
                    <select name="fid" class="form-select" required>
                        <option value="">Select a forum…</option>
                        <?php foreach ($forums_list as $f): ?>
                        <option value="<?= (int)$f['fid'] ?>" <?= (int)($form_data['fid'] ?? 0) === (int)$f['fid'] ? 'selected' : '' ?>><?= h($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">A new thread hosting this poll will be created here.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Thread Title <span class="text-danger">*</span></label>
                    <input type="text" name="thread_title" id="threadTitle" class="form-control" maxlength="120" required value="<?= h(trim($form_data['thread_title'] ?? '')) ?>" placeholder="Auto-filled from question, editable">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-semibold">Options <span class="text-danger">*</span></label>
                <div id="optionsContainer">
                    <?php $oi = 0; foreach (array_pad(array_values($opts_val), 2, '') as $ov): $oi++; ?>
                    <div class="input-group mb-2 option-row">
                        <span class="input-group-text bg-light"><?= $oi ?></span>
                        <input type="text" name="options[]" class="form-control" placeholder="Option <?= $oi ?>" value="<?= h($ov) ?>" required>
                        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addOption()">
                    <i class="fas fa-plus me-1"></i>Add Option
                </button>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Timeout (days)</label>
                    <input type="number" name="timeout" class="form-control" min="0" value="<?= (int)($form_data['timeout'] ?? 0) ?>">
                    <small class="text-muted">0 = never expires</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Max options per user</label>
                    <input type="number" name="maxoptions" class="form-control" min="0" value="<?= (int)($form_data['maxoptions'] ?? 0) ?>">
                    <small class="text-muted">0 = unlimited</small>
                </div>
            </div>
            
            <div class="d-flex flex-wrap gap-4 mb-4">
                <div class="form-check">
                    <input type="checkbox" name="closed" class="form-check-input" id="closedChk" value="1" <?= !empty($form_data['closed']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="closedChk"><i class="fas fa-lock me-1"></i>Closed (no more voting)</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="multiple" class="form-check-input" id="multipleChk" value="1" <?= !empty($form_data['multiple']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="multipleChk"><i class="fas fa-check-double me-1"></i>Allow multiple answers</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="public" class="form-check-input" id="publicChk" value="1" <?= !empty($form_data['public']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="publicChk"><i class="fas fa-globe me-1"></i>Public (show who voted)</label>
                </div>
            </div>
            
            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i>Create Poll
                </button>
                <a href="<?php echo $_this_script_; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
let optionCount = document.querySelectorAll('.option-row').length;

// Автозаполнение заголовка треда текстом вопроса — прекращается, как только
// пользователь сам что-то введёт в поле заголовка вручную
(function () {
    const q = document.getElementById('pollQuestion');
    const t = document.getElementById('threadTitle');
    if (!q || !t) return;
    let titleTouched = t.value !== '';
    t.addEventListener('input', () => { titleTouched = true; });
    q.addEventListener('input', () => {
        if (!titleTouched) t.value = q.value.slice(0, 120);
    });
})();

function addOption() {
    optionCount++;
    const div = document.createElement('div');
    div.className = 'input-group mb-2 option-row';
    div.innerHTML = `
        <span class="input-group-text bg-light">${optionCount}</span>
        <input type="text" name="options[]" class="form-control" placeholder="Option ${optionCount}" required>
        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
    `;
    document.getElementById('optionsContainer').appendChild(div);
}
function removeOption(btn) {
    if (document.querySelectorAll('.option-row').length <= 2) {
        alert('Minimum 2 options required');
        return;
    }
    btn.closest('.option-row').remove();
    reindexOptions();
}
function reindexOptions() {
    document.querySelectorAll('.option-row').forEach((row, idx) => {
        row.querySelector('.input-group-text').textContent = idx + 1;
        const input = row.querySelector('input[name="options[]"]');
        if (input) input.placeholder = 'Option ' + (idx + 1);
    });
    optionCount = document.querySelectorAll('.option-row').length;
}
</script>

<?php elseif ($page === 'poll_edit'): ?>

<div class="card border-0 shadow-sm rounded-3 mx-auto" style="max-width: 1140px;">
    <div class="card-header bg-transparent border-0 pt-4">
        <h3 class="mb-0"><i class="fas fa-edit me-2 text-primary"></i>Edit Poll</h3>
        <small class="text-muted">Modify poll details and vote counts</small>
    </div>
    <div class="card-body">
        <?php if (!empty($form_errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($form_errors as $e): ?>
                <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <form method="post" id="pollForm">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="poll_edit">
            <input type="hidden" name="pid" value="<?= (int)$poll_row['pid'] ?>">
            
            <div class="mb-4">
                <label class="form-label fw-semibold">Question</label>
                <input type="text" name="question" class="form-control form-control-lg" maxlength="200" required value="<?= h($poll_row['question']) ?>">
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-semibold">Options & Votes</label>
                <div id="optionsContainer">
                    <?php foreach ($edit_opts as $i => $opt): ?>
                    <div class="input-group mb-2 option-row">
                        <span class="input-group-text bg-light"><?= $i+1 ?></span>
                        <input type="text" name="options[]" class="form-control" value="<?= h(trim($opt)) ?>" required>
                        <input type="number" name="votes[]" class="form-control" style="max-width: 100px" min="0" value="<?= (int)($edit_vts[$i] ?? 0) ?>">
                        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addOption(true)">
                    <i class="fas fa-plus me-1"></i>Add Option
                </button>
                <small class="text-muted d-block mt-2">You can adjust vote counts manually</small>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Timeout (days)</label>
                    <input type="number" name="timeout" class="form-control" min="0" value="<?= (int)$poll_row['timeout'] ?>">
                    <small class="text-muted">0 = never expires</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Max options per user</label>
                    <input type="number" name="maxoptions" class="form-control" min="0" value="<?= (int)$poll_row['maxoptions'] ?>">
                    <small class="text-muted">0 = unlimited</small>
                </div>
            </div>
            
            <div class="d-flex flex-wrap gap-4 mb-4">
                <div class="form-check">
                    <input type="checkbox" name="closed" class="form-check-input" id="closedChk" value="1" <?= $poll_row['closed'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="closedChk">Closed (no more voting)</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="multiple" class="form-check-input" id="multipleChk" value="1" <?= $poll_row['multiple'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="multipleChk">Allow multiple answers</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="public" class="form-check-input" id="publicChk" value="1" <?= $poll_row['public'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="publicChk">Public (show who voted)</label>
                </div>
            </div>
            
            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
                <a href="<?php echo $_this_script_; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
let optionCount = document.querySelectorAll('.option-row').length;
function addOption(hasVotes = false) {
    optionCount++;
    const div = document.createElement('div');
    div.className = 'input-group mb-2 option-row';
    div.innerHTML = `
        <span class="input-group-text bg-light">${optionCount}</span>
        <input type="text" name="options[]" class="form-control" placeholder="Option ${optionCount}" required>
        ${hasVotes ? '<input type="number" name="votes[]" class="form-control" style="max-width:100px" min="0" value="0">' : ''}
        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
    `;
    document.getElementById('optionsContainer').appendChild(div);
}
function removeOption(btn) {
    if (document.querySelectorAll('.option-row').length <= 2) {
        alert('Minimum 2 options required');
        return;
    }
    btn.closest('.option-row').remove();
    reindexOptions();
}
function reindexOptions() {
    document.querySelectorAll('.option-row').forEach((row, idx) => {
        row.querySelector('.input-group-text').textContent = idx + 1;
    });
    optionCount = document.querySelectorAll('.option-row').length;
}
</script>

<?php elseif ($page === 'votes'): ?>




<?php if ($poll_stats !== null && $pd): ?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-transparent">
        <h5 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Results: <?= h($pd['question']) ?></h5>
    </div>
    <div class="card-body">
        <?php 
        $ptot = array_sum(array_column($poll_stats, 'count'));
        $counts = array_column($poll_stats, 'count');
        $pmax = !empty($counts) ? max($counts) : 1;
        if ($pmax == 0) $pmax = 1;
        $colors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#0dcaf0', '#fd7e14', '#20c997']; 
        ?>
        <?php foreach ($poll_stats as $si => $s):
            $pct = $ptot > 0 ? round($s['count'] / $ptot * 100) : 0; ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between small mb-1">
                <span class="fw-semibold"><?= h($s['label']) ?></span>
                <span class="text-muted"><?= $s['count'] ?> votes (<?= $pct ?>%)</span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar" style="width: <?= $pmax > 0 ? round($s['count'] / $pmax * 100) : 0 ?>%; background: <?= $colors[$si % count($colors)] ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="mt-3 pt-2 border-top">
            <small class="text-muted">Total votes: <?= $ptot ?></small>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="p" value="votes">
            <div class="col-md-3">
                <label class="form-label small text-muted">Search</label>
                <input type="search" name="q" class="form-control" placeholder="UID / IP / Question..." value="<?= h($vsearch) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Filter by Poll</label>
                <select name="pid" class="form-select">
                    <option value="">All polls</option>
                    <?php foreach ($all_polls as $ap): ?>
                    <option value="<?= (int)$ap['pid'] ?>" <?= $vpid === (int)$ap['pid'] ? 'selected' : '' ?>>
                        #<?= (int)$ap['pid'] ?> <?= h(my_substr($ap['question'], 0, 40)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">User ID</label>
                <input type="number" name="uid" class="form-control" min="0" value="<?= $vuid ?: '' ?>">
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
                <a href="?p=votes" class="btn btn-outline-secondary ms-1"><i class="fas fa-undo me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <form method="post" id="bulkForm">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="vote_bulk_delete">
            <input type="hidden" name="back" value="?p=votes">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                        <th style="width: 60px">VID</th>
                        <th style="width: 70px">Poll</th>
                        <th style="width: 80px">UID</th>
                        <th>Option</th>
                        <th style="width: 130px">IP</th>
                        <th style="width: 150px">Date</th>
                        <th style="width: 100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$votes): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-3x mb-2 d-block opacity-25"></i>
                        No votes found
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($votes as $v): ?>
				
				<?
				$ipaddress = my_inet_ntop($db->unescape_binary((string)$v['ipaddress']));
				?>
				
				
                <tr>
                    <td><input type="checkbox" name="vids[]" value="<?= (int)$v['vid'] ?>" class="voteCheckbox"></td>
                    <td class="text-muted"><?= (int)$v['vid'] ?></td>
                    <td><a href="<?php echo $_this_script_; ?>&p=votes&pid=<?= (int)$v['pid'] ?>" class="text-decoration-none">#<?= (int)$v['pid'] ?></a></td>
                    <td><a href="<?php echo $_this_script_; ?>&p=votes_by_user&uid=<?= (int)$v['uid'] ?>" class="badge bg-primary text-decoration-none"><?= (int)$v['uid'] ?></a></td>
                    <td><span class="badge bg-light text-dark">#<?= (int)$v['voteoption'] ?></span> <?= h(my_substr(opt_label($v['options'] ?? '', (int)$v['voteoption']), 0, 50)) ?></td>
                    <td><code class="small"><?= h($ipaddress) ?></code></td>
                    <td class="text-muted small"><?= date('d.m.Y H:i:s', (int)$v['dateline']) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="<?php echo $_this_script_; ?>&p=votes&q=<?= urlencode($ipaddress) ?>" class="btn btn-outline-secondary" title="Search IP">
                                <i class="fas fa-search"></i>
                            </a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete vote #<?= (int)$v['vid'] ?>?')">
                                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                                <input type="hidden" name="action" value="vote_delete">
                                <input type="hidden" name="vid" value="<?= (int)$v['vid'] ?>">
                                <input type="hidden" name="back" value="<?php echo $_this_script_; ?>&p=votes">
                                <button class="btn btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
    <?php if ($votes): ?>
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <button type="submit" form="bulkForm" class="btn btn-danger btn-sm" onclick="return confirm('Delete selected votes?')">
                <i class="fas fa-trash-alt me-1"></i>Delete Selected
            </button>
        </div>
        <div class="d-flex align-items-center gap-3">
            <small class="text-muted"><?= $vpg['offset']+1 ?>–<?= min($vpg['offset']+$vpg['limit'], $vtotal) ?> of <?= $vtotal ?></small>
            <?php if ($vpg['pages'] > 1): ?>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $vpg['pages']; $i++): ?>
                    <li class="page-item <?= $i === $vpg['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= self_url(['page' => $i]) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleAll(source) {
    document.querySelectorAll('.voteCheckbox').forEach(cb => cb.checked = source.checked);
}
</script>

<?php elseif ($page === 'votes_by_user'): ?>

<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="p" value="votes_by_user">
            <div class="col-md-4">
                <label class="form-label small text-muted">Search (UID / IP)</label>
                <input type="search" name="q" class="form-control" placeholder="UID or IP..." value="<?= h($bsearch) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">User ID</label>
                <input type="number" name="uid" class="form-control" min="0" value="<?= $buid ?: '' ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
                <a href="<?php echo $_this_script_; ?>&p=votes_by_user" class="btn btn-outline-secondary ms-1"><i class="fas fa-undo me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 100px">User ID</th>
                    <th style="width: 100px">Votes</th>
                    <th style="width: 100px">Polls</th>
                    <th>Last IP</th>
                    <th style="width: 150px">Last Vote</th>
                    <th style="width: 180px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$by_users): ?>
            <tr>
                <td colspan="6" class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-2 d-block opacity-25"></i>
                    No votes found
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($by_users as $u): ?>
            <tr>
                <td><span class="badge bg-primary fs-6"><?= (int)$u['uid'] ?></span></td>
                <td class="fw-semibold"><?= (int)$u['vc'] ?></td>
                <td><?= (int)$u['pc'] ?></td>
                <td><code class="small"><?= h($u['lip']) ?></code></td>
                <td class="text-muted small"><?= date('d.m.Y H:i', (int)$u['lv']) ?></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="<?php echo $_this_script_; ?>&p=votes&uid=<?= (int)$u['uid'] ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye"></i> Votes
                        </a>
                        <a href="<?php echo $_this_script_; ?>&p=votes&q=<?= urlencode($u['lip']) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-search"></i> IP
                        </a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete all <?= (int)$u['vc'] ?> votes for user <?= (int)$u['uid'] ?>?')">
                            <input type="hidden" name="csrf" value="<?= csrf() ?>">
                            <input type="hidden" name="action" value="vote_delete_user">
                            <input type="hidden" name="uid" value="<?= (int)$u['uid'] ?>">
                            <button class="btn btn-outline-danger" title="Delete all votes for this user">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($user_detail !== null): ?>
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-transparent">
        <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i>All votes for user #<?= $buid ?></h5>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px">VID</th>
                    <th>Poll</th>
                    <th>Option</th>
                    <th style="width: 150px">Date</th>
                    <th style="width: 80px">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($user_detail as $v):
                $opt_text = opt_label($v['options'] ?? '', (int)$v['voteoption']); ?>
            <tr>
                <td class="text-muted"><?= (int)$v['vid'] ?></td>
                <td><a href="?p=votes&pid=<?= (int)$v['pid'] ?>">#<?= (int)$v['pid'] ?></a> <?= h(my_substr($v['question'] ?? '', 0, 50)) ?></td>
                <td><span class="badge bg-light text-dark">#<?= (int)$v['voteoption'] ?></span> <?= h(my_substr($opt_text, 0, 40)) ?></td>
                <td class="text-muted small"><?= date('d.m.Y H:i:s', (int)$v['dateline']) ?></td>
                <td>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete vote #<?= (int)$v['vid'] ?>?')">
                        <input type="hidden" name="csrf" value="<?= csrf() ?>">
                        <input type="hidden" name="action" value="vote_delete">
                        <input type="hidden" name="vid" value="<?= (int)$v['vid'] ?>">
                        <input type="hidden" name="back" value="<?php echo $_this_script_; ?>&p=votes_by_user&uid=<?= $buid ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

</div>

<style>
.hover-bg:hover {
    background: rgba(255,255,255,0.1) !important;
}
.sidebar .active {
    background: #0d6efd !important;
    color: white !important;
}
</style>

<?php stdfoot(); ?>