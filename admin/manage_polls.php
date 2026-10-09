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

/**
 * Подстановка {1}, {2}… в строку из ланга. $lang->load() превращает {N} в %N$s,
 * поэтому понимаем оба формата; strtr — один проход, значения повторно не разбираются.
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string)$arg;
            $map['%' . $n . '$s']  = (string)$arg;
            $map['%' . $n . '$d']  = (string)$arg;
        }
        return strtr($str, $map);
    }
}

$lang->load('manage_polls');

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    die(htmlspecialchars($lang->manage_polls['err_no_permission'], ENT_QUOTES, 'UTF-8'));
}

// global.php не стартует нативную PHP-сессию сама (та же история, что и в
// member.php/report_captcha.php) — без этого $_SESSION['csrf'] не переживёт
// между запросом формы и её отправкой, и все POST-действия ниже будут
// постоянно валиться с "CSRF error" даже у легитимных запросов.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lang->load("polls");






// ── update_user_counters ──────────────────────────────────────────────────────
function update_user_counters(int|string $uid, array $changes = []): void
{
    global $db;

    $uid = (int)$uid;
    
	$counters = ['postnum', 'threadnum'];
    $query    = $db->sql_query_prepared("SELECT " . implode(',', $counters) . " FROM users WHERE id = ?", [$uid]);
    $user     = $query ? $db->fetch_array($query) : null;

    if (!$user) {
        return;
    }

    $update = [];

    foreach ($counters as $counter) {
        if (!array_key_exists($counter, $changes)) {
            continue;
        }

        $val = $changes[$counter];

        if (str_starts_with((string)$val, '+-')) {
            $val = substr((string)$val, 1);
        }

        $new = str_starts_with((string)$val, '+') || str_starts_with((string)$val, '-')
            ? $user[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
        $params   = array_values($update);
        $params[] = $uid;
        $db->sql_query_prepared("UPDATE users SET {$set} WHERE id = ?", $params);
    }
}



// ── update_forum_counters ─────────────────────────────────────────────────────
function update_forum_counters(int|string $fid, array $changes = []): void
{
    global $db;

    $fid = (int)$fid;
	
	$counters = ['threads', 'unapprovedthreads', 'posts', 'unapprovedposts'];
    $query    = $db->sql_query_prepared("SELECT " . implode(',', $counters) . " FROM forums WHERE fid = ?", [$fid]);
    $forum    = $query ? $db->fetch_array($query) : null;
    $update   = [];

    foreach ($counters as $counter) {
        if (!array_key_exists($counter, $changes)) {
            continue;
        }

        $val = $changes[$counter];

        if (str_starts_with((string)$val, '+-')) {
            $val = substr((string)$val, 1);
        }

        $new = str_starts_with((string)$val, '+') || str_starts_with((string)$val, '-')
            ? $forum[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
        $params   = array_values($update);
        $params[] = $fid;
        $db->sql_query_prepared("UPDATE forums SET {$set} WHERE fid = ?", $params);
    }

    // Обновляем глобальную статистику
    $stat_map = [
        'threads'           => 'numthreads',
        'unapprovedthreads' => 'numunapprovedthreads',
        'posts'             => 'numposts',
        'unapprovedposts'   => 'numunapprovedposts',
    ];

    $new_stats = [];
    foreach ($stat_map as $counter => $stat) {
        if (!isset($update[$counter])) {
            continue;
        }
        $diff = $update[$counter] - $forum[$counter];
        $new_stats[$stat] = ($diff >= 0 ? '+' : '') . $diff;
    }

    if (!empty($new_stats)) {
        update_stats($new_stats);
    }
}



// ── update_forum_lastpost ─────────────────────────────────────────────────────
function update_forum_lastpost(int $fid): void
{
    global $db;

    $query = $db->sql_query_prepared("
        SELECT tid, lastpost, lastposter, lastposteruid, subject
        FROM threads
        WHERE fid = ? AND visible = '1' AND closed NOT LIKE 'moved|%'
        ORDER BY lastpost DESC LIMIT 1
    ", [$fid]);

    if ($query && $db->num_rows($query) > 0) {
        $last = $db->fetch_array($query);
        $updated = [
            'lastpost'       => (int)$last['lastpost'],
            'lastposter'     => $last['lastposter'],
            'lastposteruid'  => (int)$last['lastposteruid'],
            'lastposttid'    => (int)$last['tid'],
            'lastpostsubject'=> $last['subject'],
        ];
    } else {
        $updated = [
            'lastpost' => 0, 'lastposter' => '', 'lastposteruid' => 0,
            'lastposttid' => 0, 'lastpostsubject' => '',
        ];
    }

    $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($updated)));
    $params = array_values($updated);
    $params[] = $fid;
    $db->sql_query_prepared("UPDATE forums SET {$set} WHERE fid = ?", $params);
}





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
    global $lang;
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die(h($lang->manage_polls['err_csrf']));
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
    global $lang;
    $opts = explode('||~|~||', $options);
    return trim($opts[$voteoption - 1] ?? ags_fmt($lang->manage_polls['opt_option_n'], $voteoption));
}

// ── UI helpers ───────────────────────────────────────────────────────────────

/** Ссылка на страницу менеджера с параметрами (через $_this_script_, чтобы не терять act=...). */
function mp_url(array $params = []): string {
    global $_this_script_;
    return $_this_script_ . ($params ? '&' . http_build_query($params) : '');
}

/** action для GET-форм фильтров: путь из $_this_script_ без query-части. */
function mp_form_action(): string {
    global $_this_script_;
    return explode('?', (string)$_this_script_, 2)[0];
}

/**
 * Hidden-поля с роутинг-параметрами $_this_script_ (act=... и т.п.).
 * GET-форма заменяет весь query string, поэтому без них фильтры уводили со страницы.
 */
function mp_route_fields(): string {
    global $_this_script_;
    $query = explode('?', (string)$_this_script_, 2)[1] ?? '';
    parse_str($query, $route);
    $out = '';
    foreach ($route as $k => $v) {
        if (!is_string($v)) continue;
        $out .= '<input type="hidden" name="' . h((string)$k) . '" value="' . h($v) . '">';
    }
    return $out;
}

/** Разрешаем редирект только на относительный URL этой же страницы. */
function mp_safe_back(string $back, string $default): string {
    global $_this_script_;
    if ($back === '' || preg_match('/[\r\n]/', $back)) return $default;
    if (str_starts_with($back, '?') || str_starts_with($back, (string)$_this_script_)) return $back;
    return $default;
}

/** Мягкий бейдж с иконкой. */
function mp_badge(string $tone, string $icon, string $text, string $title = ''): string {
    return '<span class="mp-badge mp-tone-' . $tone . '"' . ($title !== '' ? ' title="' . h($title) . '"' : '') . '>'
         . '<i class="fa-solid ' . $icon . '" aria-hidden="true"></i>' . h($text) . '</span>';
}

/** Обрезка строки с многоточием + экранирование. */
function mp_cut(string $s, int $len): string {
    $s = trim($s);
    return h(my_substr($s, 0, $len)) . (my_strlen($s) > $len ? '…' : '');
}

/** Бинарный IP из БД → строка. */
function mp_ip(mixed $raw): string {
    global $db;
    if ($raw === null || $raw === '') return '';
    return (string)my_inet_ntop($db->unescape_binary((string)$raw));
}

/** Пагинация с окном ±2 и многоточиями. */
function mp_pagination(array $pg): string {
    global $lang;
    if ($pg['pages'] <= 1) return '';
    $cur  = $pg['page'];
    $last = $pg['pages'];
    $set  = array_unique(array_filter([1, $cur - 2, $cur - 1, $cur, $cur + 1, $cur + 2, $last], fn($n) => $n >= 1 && $n <= $last));
    sort($set);

    $out = '<nav class="mp-pager" aria-label="' . h($lang->manage_polls['aria_pages']) . '">';
    $out .= $cur > 1
        ? '<a href="' . h(self_url(['page' => $cur - 1])) . '" aria-label="' . h($lang->manage_polls['aria_prev']) . '"><i class="fa-solid fa-chevron-left"></i></a>'
        : '<span class="is-disabled"><i class="fa-solid fa-chevron-left"></i></span>';
    $prev = 0;
    foreach ($set as $n) {
        if ($prev && $n - $prev > 1) $out .= '<span class="mp-pager-gap">…</span>';
        $out .= $n === $cur
            ? '<span class="is-current" aria-current="page">' . $n . '</span>'
            : '<a href="' . h(self_url(['page' => $n])) . '">' . $n . '</a>';
        $prev = $n;
    }
    $out .= $cur < $last
        ? '<a href="' . h(self_url(['page' => $cur + 1])) . '" aria-label="' . h($lang->manage_polls['aria_next']) . '"><i class="fa-solid fa-chevron-right"></i></a>'
        : '<span class="is-disabled"><i class="fa-solid fa-chevron-right"></i></span>';
    return $out . '</nav>';
}

/** "1–25 of 340" */
function mp_range(array $pg): string {
    global $lang;
    if ($pg['total'] === 0) return h($lang->manage_polls['lbl_nothing']);
    $from = $pg['offset'] + 1;
    $to   = min($pg['offset'] + $pg['limit'], $pg['total']);
    return h(ags_fmt($lang->manage_polls['lbl_range'], $from, $to, number_format($pg['total'], 0, '.', ' ')));
}

/** Условие поиска по голосам: UID/вопрос через LIKE, IP — точным сравнением бинарного значения. */
function mp_vote_search(string $term, bool $with_question, array &$params): string {
    $like    = '%' . mp_escape_like($term) . '%';
    $conds   = ['v.uid LIKE ?'];
    $params[] = $like;
    if ($with_question) {
        $conds[]  = 'p.question LIKE ?';
        $params[] = $like;
    }
    if (filter_var($term, FILTER_VALIDATE_IP)) {
        $conds[]  = 'v.ipaddress = ?';
        $params[] = my_inet_pton($term);
    }
    return ' AND (' . implode(' OR ', $conds) . ')';
}

$allowed_pages = ['polls', 'poll_create', 'poll_edit', 'votes', 'votes_by_user'];
$page = $_GET['p'] ?? 'polls';
if (!in_array($page, $allowed_pages, true)) $page = 'polls';

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

        if ($q === '') $errors[] = $lang->manage_polls['err_question_empty'];
        if (my_strlen($q) > 200) $errors[] = ags_fmt($lang->manage_polls['err_question_long'], 200);
        if (count($opts) < 2) $errors[] = ags_fmt($lang->manage_polls['err_options_min'], 2);
        if (count($opts) > 20) $errors[] = ags_fmt($lang->manage_polls['err_options_max'], 20);
        if ($thread_title === '') $errors[] = $lang->manage_polls['err_thread_title'];

        $forum = $fid > 0 ? get_forum($fid) : null;
        if (!$forum) $errors[] = $lang->manage_polls['err_forum'];

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
            $message = '[b]' . $q . '[/b]' . "\n\n" . $lang->manage_polls['post_body_vote'];
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
            flash_message($lang->manage_polls['flash_created'], 'success');
            header('Location: ' . mp_url(['p' => 'polls']));
            exit;
        }
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data']   = $_POST;
        header('Location: ' . mp_url(['p' => 'poll_create']));
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

        if ($q === '') $errors[] = $lang->manage_polls['err_question_empty'];
        if (my_strlen($q) > 200) $errors[] = ags_fmt($lang->manage_polls['err_question_long'], 200);
        if (count($opts) < 2) $errors[] = ags_fmt($lang->manage_polls['err_options_min'], 2);

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
            write_log("Poll edited: #{$pid} \"{$q}\" by {$CURUSER['username']}");
            flash_message($lang->manage_polls['flash_saved'], 'success');
            header('Location: ' . mp_url(['p' => 'poll_edit', 'pid' => $pid]));
            exit;
        }
        $_SESSION['form_errors'] = $errors;
        header('Location: ' . mp_url(['p' => 'poll_edit', 'pid' => $pid]));
        exit;
    }

    // --- Poll: toggle ---
    if ($action === 'poll_toggle') {
        $pid = (int)($_POST['pid'] ?? 0);
        $query = $db->sql_query_prepared("SELECT closed FROM polls WHERE pid = ?", [$pid]);
        $cur = $query ? (int)($db->fetch_field($query, 'closed')) : 0;
        $new_status = $cur ? 0 : 1;
        $db->sql_query_prepared("UPDATE polls SET closed = ? WHERE pid = ?", [$new_status, $pid]);
        write_log('Poll ' . ($cur ? 'opened' : 'closed') . ": #{$pid} by {$CURUSER['username']}");
        flash_message($cur ? $lang->manage_polls['flash_opened'] : $lang->manage_polls['flash_closed'], 'success');
        header('Location: ' . mp_safe_back((string)($_POST['back'] ?? ''), mp_url(['p' => 'polls'])));
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

        write_log("Poll deleted: #{$pid} (with all votes) by {$CURUSER['username']}");
        flash_message(ags_fmt($lang->manage_polls['flash_deleted'], $pid), 'success');
        header('Location: ' . mp_safe_back((string)($_POST['back'] ?? ''), mp_url(['p' => 'polls'])));
        exit;
    }

    // --- Poll: bulk close ---
    if ($action === 'poll_bulk_close') {
        $pids = array_values(array_filter(array_map('intval', $_POST['pids'] ?? [])));
        if ($pids) {
            $ph = implode(',', array_fill(0, count($pids), '?'));
            $db->sql_query_prepared("UPDATE polls SET closed = 1 WHERE pid IN ({$ph})", $pids);
            write_log('Polls closed (bulk): #' . implode(', #', $pids) . " by {$CURUSER['username']}");
            flash_message(ags_fmt($lang->manage_polls['flash_bulk_closed'], count($pids)), 'success');
        }
        header('Location: ' . mp_safe_back((string)($_POST['back'] ?? ''), mp_url(['p' => 'polls'])));
        exit;
    }

    // --- Poll: bulk delete ---
    if ($action === 'poll_bulk_delete') {
        $pids = array_values(array_filter(array_map('intval', $_POST['pids'] ?? [])));
        if ($pids) {
            $ph = implode(',', array_fill(0, count($pids), '?'));
            // Та же последовательность, что и в одиночном poll_delete —
            // отвязать треды, удалить голоса, удалить сами опросы
            $db->sql_query_prepared("UPDATE threads SET poll = 0 WHERE poll IN ({$ph})", $pids);
            $db->sql_query_prepared("DELETE FROM pollvotes WHERE pid IN ({$ph})", $pids);
            $db->sql_query_prepared("DELETE FROM polls WHERE pid IN ({$ph})", $pids);
            write_log('Polls deleted (bulk, with votes): #' . implode(', #', $pids) . " by {$CURUSER['username']}");
            flash_message(ags_fmt($lang->manage_polls['flash_bulk_deleted'], count($pids)), 'success');
        }
        header('Location: ' . mp_safe_back((string)($_POST['back'] ?? ''), mp_url(['p' => 'polls'])));
        exit;
    }

    // --- Vote: delete single ---
    if ($action === 'vote_delete') {
        $vid = (int)($_POST['vid'] ?? 0);
        $db->sql_query_prepared("DELETE FROM pollvotes WHERE vid = ?", [$vid]);
        write_log("Poll vote deleted: #{$vid} by {$CURUSER['username']}");
        flash_message(ags_fmt($lang->manage_polls['flash_vote_deleted'], $vid), 'success');
        header('Location: ' . mp_safe_back((string)($_POST['back'] ?? ''), mp_url(['p' => 'votes'])));
        exit;
    }

    // --- Vote: bulk delete ---
    if ($action === 'vote_bulk_delete') {
        $vids = array_values(array_filter(array_map('intval', $_POST['vids'] ?? [])));
        if ($vids) {
            $ph = implode(',', array_fill(0, count($vids), '?'));
            $db->sql_query_prepared("DELETE FROM pollvotes WHERE vid IN ({$ph})", $vids);
            write_log('Poll votes deleted (bulk): #' . implode(', #', $vids) . " by {$CURUSER['username']}");
            flash_message(ags_fmt($lang->manage_polls['flash_votes_deleted'], count($vids)), 'success');
        }
        header('Location: ' . mp_safe_back((string)($_POST['back'] ?? ''), mp_url(['p' => 'votes'])));
        exit;
    }

    // --- Vote: delete all votes for a user ---
    if ($action === 'vote_delete_user') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid > 0) {
            $count_query = $db->sql_query_prepared("SELECT COUNT(*) FROM pollvotes WHERE uid = ?", [$uid]);
            $count = $count_query ? (int)$db->fetch_field($count_query, 'COUNT(*)') : 0;
            $db->sql_query_prepared("DELETE FROM pollvotes WHERE uid = ?", [$uid]);
            write_log("Poll votes deleted for user #{$uid}: {$count} vote(s) by {$CURUSER['username']}");
            flash_message(ags_fmt($lang->manage_polls['flash_user_votes_deleted'], $count, $uid), 'success');
        }
        header('Location: ' . mp_safe_back((string)($_POST['back'] ?? ''), mp_url(['p' => 'votes_by_user'])));
        exit;
    }
}

// ============================================================
//  DATA for current page
// ============================================================

// ---- POLLS LIST ----
if ($page === 'polls') {
    $stats_query = $db->sql_query_prepared("SELECT COUNT(*) AS total, SUM(closed=0) AS open_c, SUM(closed=1) AS closed_c, SUM(numvotes) AS tvotes FROM polls");
    $stats = ($stats_query ? $db->fetch_array($stats_query) : null) ?: [];

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
    $has_filters = $search !== '' || $fs !== '' || $ft !== '' || $linked !== '';

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

// ---- POLL CREATE / EDIT (общая форма) ----
if ($page === 'poll_create') {
    $form_errors = $_SESSION['form_errors'] ?? [];
    $form_data   = $_SESSION['form_data']   ?? [];
    unset($_SESSION['form_errors'], $_SESSION['form_data']);
    $opts_val = array_values(array_filter(array_map('trim', $form_data['options'] ?? ['', ''])));
    $opts_val = array_pad($opts_val, 2, '');

    // Список форумов для выбора, куда создать тред опроса (только реальные
    // форумы, не категории — type='f', как и везде в остальном коде проекта)
    $forums_list = [];
    $forums_query = $db->sql_query_prepared("SELECT fid, name FROM forums WHERE type='f' AND active!=0 ORDER BY name ASC");
    while ($forums_query && ($frow = $db->fetch_array($forums_query))) {
        $forums_list[] = $frow;
    }

    $f = [
        'question'   => trim((string)($form_data['question'] ?? '')),
        'options'    => array_map(fn($o) => ['label' => $o, 'votes' => null], $opts_val),
        'timeout'    => (int)($form_data['timeout'] ?? 0),
        'maxoptions' => (int)($form_data['maxoptions'] ?? 0),
        'closed'     => !empty($form_data['closed']),
        'multiple'   => !empty($form_data['multiple']),
        'public'     => !empty($form_data['public']),
    ];
}

if ($page === 'poll_edit') {
    $pid = (int)($_GET['pid'] ?? 0);
    $poll_query = $db->sql_query_prepared("SELECT * FROM polls WHERE pid = ?", [$pid]);
    $poll_row = $poll_query ? $db->fetch_array($poll_query) : null;
    if (!$poll_row) {
        flash_message($lang->manage_polls['flash_not_found'], 'error');
        header('Location: ' . mp_url(['p' => 'polls']));
        exit;
    }
    $form_errors = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_errors']);
    $edit_opts = explode('||~|~||', (string)$poll_row['options']);
    $edit_vts  = explode('||~|~||', (string)$poll_row['votes']);

    $f = [
        'question'   => (string)$poll_row['question'],
        'options'    => array_map(fn($o, $i) => ['label' => trim($o), 'votes' => (int)($edit_vts[$i] ?? 0)], $edit_opts, array_keys($edit_opts)),
        'timeout'    => (int)$poll_row['timeout'],
        'maxoptions' => (int)$poll_row['maxoptions'],
        'closed'     => (bool)$poll_row['closed'],
        'multiple'   => (bool)$poll_row['multiple'],
        'public'     => (bool)$poll_row['public'],
    ];
}

// ---- VOTES ----
if ($page === 'votes') {
    $vwhere = '1';
    $vwhere_params = [];
    $vsearch = trim($_GET['q'] ?? '');
    if ($vsearch !== '') {
        $vwhere .= mp_vote_search($vsearch, true, $vwhere_params);
    }
    $vpid = (int)($_GET['pid'] ?? 0);
    if ($vpid) { $vwhere .= " AND v.pid = ?"; $vwhere_params[] = $vpid; }
    $vuid = (int)($_GET['uid'] ?? 0);
    if ($vuid) { $vwhere .= " AND v.uid = ?"; $vwhere_params[] = $vuid; }
    $has_filters = $vsearch !== '' || $vpid || $vuid;

    $total_query = $db->sql_query_prepared("
        SELECT COUNT(*) FROM pollvotes v
        LEFT JOIN polls p ON p.pid = v.pid
        WHERE {$vwhere}
    ", $vwhere_params);
    $vtotal = $total_query ? (int)$db->fetch_field($total_query, 'COUNT(*)') : 0;
    $vpg = paginate($vtotal, (int)($_GET['page'] ?? 1), $per_page);

    $votes_query = $db->sql_query_prepared("
        SELECT v.*, p.question, p.options, u.username
        FROM pollvotes v
        LEFT JOIN polls p ON p.pid = v.pid
        LEFT JOIN users u ON u.id = v.uid
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

    // Результаты выбранного опроса — один GROUP BY вместо запроса на каждый вариант
    $poll_stats = null;
    $pd = null;
    if ($vpid) {
        $pd_query = $db->sql_query_prepared("SELECT * FROM polls WHERE pid = ?", [$vpid]);
        $pd = $pd_query ? $db->fetch_array($pd_query) : null;
        if ($pd) {
            $cnt_map = [];
            $cnt_query = $db->sql_query_prepared("SELECT voteoption, COUNT(*) AS c FROM pollvotes WHERE pid = ? GROUP BY voteoption", [$vpid]);
            while ($cnt_query && ($r = $db->fetch_array($cnt_query))) {
                $cnt_map[(int)$r['voteoption']] = (int)$r['c'];
            }
            $poll_stats = [];
            foreach (explode('||~|~||', (string)$pd['options']) as $i => $opt) {
                $poll_stats[] = ['label' => trim($opt), 'count' => $cnt_map[$i + 1] ?? 0];
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
        $bwhere .= mp_vote_search($bsearch, false, $bwhere_params);
    }
    $buid = (int)($_GET['uid'] ?? 0);
    if ($buid) { $bwhere .= " AND v.uid = ?"; $bwhere_params[] = $buid; }
    $has_filters = $bsearch !== '' || $buid;

    // Последний IP берём подзапросом: GROUP_CONCAT по бинарному полю с разделителем '|'
    // ломался, когда байт 0x7C встречался внутри самого адреса.
    $by_users_query = $db->sql_query_prepared("
        SELECT v.uid,
               MAX(u.username) AS username,
               COUNT(*) AS vc,
               COUNT(DISTINCT v.pid) AS pc,
               MAX(v.dateline) AS lv,
               (SELECT v2.ipaddress FROM pollvotes v2 WHERE v2.uid = v.uid ORDER BY v2.dateline DESC LIMIT 1) AS lip
        FROM pollvotes v
        LEFT JOIN users u ON u.id = v.uid
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
        while ($detail_query && ($row = $db->fetch_array($detail_query))) {
            $user_detail[] = $row;
        }
    }
}

// ============================================================
//  LAYOUT
// ============================================================
$page_meta = [
    'polls'         => [$lang->manage_polls['pane_polls'],         $lang->manage_polls['sub_polls'],         'fa-square-poll-vertical', 'primary'],
    'poll_create'   => [$lang->manage_polls['pane_poll_create'],   $lang->manage_polls['sub_poll_create'],   'fa-circle-plus',          'success'],
    'poll_edit'     => [$lang->manage_polls['pane_poll_edit'],     $lang->manage_polls['sub_poll_edit'],     'fa-pen-to-square',        'primary'],
    'votes'         => [$lang->manage_polls['pane_votes'],         $lang->manage_polls['sub_votes'],         'fa-check-to-slot',        'warning'],
    'votes_by_user' => [$lang->manage_polls['pane_votes_by_user'], $lang->manage_polls['sub_votes_by_user'], 'fa-users',                'info'],
];
[$m_title, $m_sub, $m_icon, $m_tone] = $page_meta[$page];

$nav = [
    'polls'         => [$lang->manage_polls['nav_polls'],   'fa-square-poll-vertical'],
    'votes'         => [$lang->manage_polls['nav_votes'],   'fa-check-to-slot'],
    'votes_by_user' => [$lang->manage_polls['nav_by_user'], 'fa-users'],
];
$nav_active = in_array($page, ['poll_create', 'poll_edit'], true) ? 'polls' : $page;

// Палитра вариантов ответа: одна и та же на полосе распределения, чипах и в результатах
$MP_COLORS = 8;
$csrf      = csrf();
$here      = self_url();          // текущая страница с фильтрами — для back после действий
$asset_ver = 2;
$base      = rtrim((string)($BASEURL ?? ''), '/');

// Строки для manage_polls.js: ключи js_* из ланга, без префикса
$js_lang = [];
foreach ($lang->manage_polls as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $js_lang[substr((string)$k, 3)] = (string)$v;
    }
}

stdhead($m_title);
?>
<link rel="stylesheet" href="<?= h($base) ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= h($base) ?>/admin/templates/manage_polls.css?ver=<?= $asset_ver ?>">
<script src="<?= h($base) ?>/scripts/sweetalert2.min.js"></script>
<script>const AGS_LANG = <?= json_encode($js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= h($base) ?>/admin/scripts/manage_polls.js?ver=<?= $asset_ver ?>" defer></script>

<div class="mp-page container mt-3">

<?php flash_message(); ?>

<header class="mp-card mp-head">
    <div class="mp-head-main">
        <span class="mp-head-icon mp-tone-<?= $m_tone ?>"><i class="fa-solid <?= $m_icon ?>" aria-hidden="true"></i></span>
        <div>
            <h1 class="mp-title"><?= h($m_title) ?><?= $page === 'poll_edit' ? ' <span class="mp-title-id">#' . (int)$poll_row['pid'] . '</span>' : '' ?></h1>
            <p class="mp-sub"><?= h($m_sub) ?></p>
        </div>
    </div>
    <div class="mp-head-side">
        <nav class="mp-nav" aria-label="<?= h($lang->manage_polls['aria_nav']) ?>">
            <?php foreach ($nav as $key => [$label, $icon]): ?>
            <a href="<?= h(mp_url(['p' => $key])) ?>" class="<?= $nav_active === $key ? 'is-active' : '' ?>"<?= $nav_active === $key ? ' aria-current="page"' : '' ?>>
                <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i><?= h($label) ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <?php if ($page !== 'poll_create'): ?>
        <a href="<?= h(mp_url(['p' => 'poll_create'])) ?>" class="mp-btn mp-btn-success"><i class="fa-solid fa-plus" aria-hidden="true"></i><?= h($lang->manage_polls['btn_new_poll']) ?></a>
        <?php endif; ?>
    </div>
</header>

<?php if ($page === 'polls'): ?>

<!-- KPI -->
<div class="mp-kpis">
    <?php foreach ([
        ['primary', 'fa-square-poll-vertical', $lang->manage_polls['kpi_polls'],  (int)($stats['total'] ?? 0),    mp_url(['p' => 'polls']),                     $fs === ''],
        ['success', 'fa-lock-open',            $lang->manage_polls['kpi_open'],   (int)($stats['open_c'] ?? 0),   mp_url(['p' => 'polls', 'status' => 'open']),   $fs === 'open'],
        ['danger',  'fa-lock',                 $lang->manage_polls['kpi_closed'], (int)($stats['closed_c'] ?? 0), mp_url(['p' => 'polls', 'status' => 'closed']), $fs === 'closed'],
        ['warning', 'fa-check-to-slot',        $lang->manage_polls['kpi_votes'],  (int)($stats['tvotes'] ?? 0),   mp_url(['p' => 'votes']),                     false],
    ] as [$tone, $icon, $label, $val, $href, $on]): ?>
    <a href="<?= h($href) ?>" class="mp-card mp-kpi mp-tone-<?= $tone ?><?= $on ? ' is-on' : '' ?>">
        <span class="mp-kpi-icon"><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></span>
        <span class="mp-kpi-body">
            <span class="mp-kpi-value"><?= number_format($val, 0, '.', ' ') ?></span>
            <span class="mp-kpi-label"><?= h($label) ?></span>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<form method="get" action="<?= h(mp_form_action()) ?>" class="mp-card mp-filters mp-filters--polls">
    <?= mp_route_fields() ?>
    <input type="hidden" name="p" value="polls">
    <div class="mp-field">
        <label for="mpQ" class="mp-label"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_search']) ?></label>
        <input type="search" id="mpQ" name="q" class="form-control" placeholder="<?= h($lang->manage_polls['ph_search_polls']) ?>" value="<?= h($search) ?>">
    </div>
    <div class="mp-field">
        <label for="mpStatus" class="mp-label"><i class="fa-solid fa-toggle-on" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_status']) ?></label>
        <select id="mpStatus" name="status" class="form-select">
            <option value=""><?= h($lang->manage_polls['opt_status_any']) ?></option>
            <option value="open" <?= $fs === 'open' ? 'selected' : '' ?>><?= h($lang->manage_polls['opt_status_open']) ?></option>
            <option value="closed" <?= $fs === 'closed' ? 'selected' : '' ?>><?= h($lang->manage_polls['opt_status_closed']) ?></option>
        </select>
    </div>
    <div class="mp-field">
        <label for="mpType" class="mp-label"><i class="fa-solid fa-tags" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_type']) ?></label>
        <select id="mpType" name="type" class="form-select">
            <option value=""><?= h($lang->manage_polls['opt_type_any']) ?></option>
            <option value="multi" <?= $ft === 'multi' ? 'selected' : '' ?>><?= h($lang->manage_polls['opt_type_multi']) ?></option>
            <option value="public" <?= $ft === 'public' ? 'selected' : '' ?>><?= h($lang->manage_polls['opt_type_public']) ?></option>
        </select>
    </div>
    <div class="mp-field">
        <label for="mpLinked" class="mp-label"><i class="fa-solid fa-link" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_thread']) ?></label>
        <select id="mpLinked" name="linked" class="form-select">
            <option value=""><?= h($lang->manage_polls['opt_linked_any']) ?></option>
            <option value="yes" <?= $linked === 'yes' ? 'selected' : '' ?>><?= h($lang->manage_polls['opt_linked_yes']) ?></option>
            <option value="no" <?= $linked === 'no' ? 'selected' : '' ?>><?= h($lang->manage_polls['opt_linked_no']) ?></option>
        </select>
    </div>
    <div class="mp-filter-actions">
        <button type="submit" class="mp-btn mp-btn-primary"><i class="fa-solid fa-filter" aria-hidden="true"></i><?= h($lang->manage_polls['btn_apply']) ?></button>
        <?php if ($has_filters): ?>
        <a href="<?= h(mp_url(['p' => 'polls'])) ?>" class="mp-btn mp-btn-ghost"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><?= h($lang->manage_polls['btn_reset']) ?></a>
        <?php endif; ?>
    </div>
</form>

<!-- Polls table -->
<div class="mp-card mp-table-card">
    <div class="table-responsive">
        <table class="table mp-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="mp-col-check">
                        <input class="form-check-input" type="checkbox" id="pollsSelectAll" data-select-all="pollCheckbox" aria-label="<?= h($lang->manage_polls['aria_select_all_polls']) ?>">
                    </th>
                    <th><?= h($lang->manage_polls['th_poll']) ?></th>
                    <th class="mp-col-thread"><i class="fa-solid fa-comments" aria-hidden="true"></i><?= h($lang->manage_polls['th_thread']) ?></th>
                    <th class="mp-col-num"><i class="fa-solid fa-check-to-slot" aria-hidden="true"></i><?= h($lang->manage_polls['th_votes']) ?></th>
                    <th><i class="fa-solid fa-toggle-on" aria-hidden="true"></i><?= h($lang->manage_polls['th_status']) ?></th>
                    <th><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i><?= h($lang->manage_polls['th_expires']) ?></th>
                    <th><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= h($lang->manage_polls['th_created']) ?></th>
                    <th class="mp-col-actions"><span class="visually-hidden"><?= h($lang->manage_polls['th_actions']) ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$polls): ?>
            <tr>
                <td colspan="8">
                    <div class="mp-empty">
                        <span class="mp-empty-icon"><i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i></span>
                        <?php if ($has_filters): ?>
                            <p><?= h($lang->manage_polls['empty_polls_filtered']) ?></p>
                            <a href="<?= h(mp_url(['p' => 'polls'])) ?>" class="mp-btn mp-btn-ghost"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><?= h($lang->manage_polls['btn_reset_filters']) ?></a>
                        <?php else: ?>
                            <p><?= h($lang->manage_polls['empty_polls']) ?></p>
                            <a href="<?= h(mp_url(['p' => 'poll_create'])) ?>" class="mp-btn mp-btn-success"><i class="fa-solid fa-plus" aria-hidden="true"></i><?= h($lang->manage_polls['btn_new_poll']) ?></a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($polls as $p):
                $pid      = (int)$p['pid'];
                $closed   = (bool)$p['closed'];
                $opts_arr = explode('||~|~||', (string)$p['options']);
                $vts_arr  = array_map('intval', explode('||~|~||', (string)$p['votes']));
                $vsum     = array_sum($vts_arr);
                $rv       = (int)$p['rv'];

                if ((int)$p['timeout'] > 0) {
                    $diff = ((int)$p['dateline'] + (int)$p['timeout'] * 86400) - TIMENOW;
                    if ($diff < 0)          $exp_html = mp_badge('danger', 'fa-hourglass-end', $lang->manage_polls['badge_expired']);
                    elseif ($diff < 86400)  $exp_html = mp_badge('warning', 'fa-hourglass-half', $lang->manage_polls['badge_lt_day']);
                    else                    $exp_html = mp_badge('secondary', 'fa-hourglass-start', ags_fmt($lang->manage_polls['badge_days'], (int)round($diff / 86400)));
                } else {
                    $exp_html = mp_badge('secondary', 'fa-infinity', $lang->manage_polls['badge_never']);
                }
            ?>
            <tr>
                <td class="mp-col-check">
                    <input class="form-check-input pollCheckbox" type="checkbox" name="pids[]" value="<?= $pid ?>" form="bulkPollsForm" aria-label="<?= h(ags_fmt($lang->manage_polls['aria_select_poll'], $pid)) ?>">
                </td>
                <td class="mp-col-poll">
                    <a href="<?= h(mp_url(['p' => 'poll_edit', 'pid' => $pid])) ?>" class="mp-q"><?= h((string)$p['question']) ?></a>
                    <div class="mp-meta">
                        <span>#<?= $pid ?></span>
                        <span><i class="fa-solid fa-list-ul" aria-hidden="true"></i><?= h(ags_fmt($lang->manage_polls['lbl_options_count'], (int)$p['numoptions'])) ?></span>
                    </div>
                    <?php if ($vsum > 0): ?>
                    <div class="mp-dist" role="img" aria-label="<?= h($lang->manage_polls['aria_vote_dist']) ?>">
                        <?php foreach ($opts_arr as $i => $opt): if (($vts_arr[$i] ?? 0) <= 0) continue; ?>
                        <span class="mp-c<?= $i % $MP_COLORS ?>" style="flex-grow: <?= (int)$vts_arr[$i] ?>" title="<?= h(trim($opt)) ?>: <?= (int)$vts_arr[$i] ?> (<?= round($vts_arr[$i] / $vsum * 100) ?>%)"></span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="mp-dist is-empty" title="<?= h($lang->manage_polls['tip_no_votes_yet']) ?>"></div>
                    <?php endif; ?>
                    <div class="mp-chips">
                        <?php foreach ($opts_arr as $i => $opt): ?>
                        <span class="mp-chip mp-c<?= $i % $MP_COLORS ?>" title="<?= h(trim($opt)) ?>">
                            <i class="mp-dot" aria-hidden="true"></i><?= mp_cut($opt, 22) ?>
                            <b><?= (int)($vts_arr[$i] ?? 0) ?></b>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td class="mp-col-thread">
                    <?php if (!empty($p['thread_tid']) && !empty($p['thread_subject'])): ?>
                        <a href="../showthread.php?tid=<?= (int)$p['thread_tid'] ?>" target="_blank" rel="noopener" class="mp-thread">
                            <i class="fa-solid fa-comments" aria-hidden="true"></i>
                            <span><?= mp_cut((string)$p['thread_subject'], 40) ?></span>
                        </a>
                        <div class="mp-meta"><span><?= h(ags_fmt($lang->manage_polls['lbl_thread_n'], (int)$p['thread_tid'])) ?></span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></div>
                    <?php else: ?>
                        <?= mp_badge('warning', 'fa-link-slash', $lang->manage_polls['badge_no_thread'], $lang->manage_polls['tip_no_thread']) ?>
                    <?php endif; ?>
                </td>
                <td class="mp-col-num">
                    <a href="<?= h(mp_url(['p' => 'votes', 'pid' => $pid])) ?>" class="mp-num"><?= number_format($rv, 0, '.', ' ') ?></a>
                    <?php if ($rv !== (int)$p['numvotes']): ?>
                    <div class="mp-meta" title="<?= h($lang->manage_polls['tip_stored_mismatch']) ?>">
                        <i class="fa-solid fa-pen-ruler" aria-hidden="true"></i><span><?= h(ags_fmt($lang->manage_polls['lbl_stored'], (int)$p['numvotes'])) ?></span>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="mp-badges">
                        <?= $closed ? mp_badge('danger', 'fa-lock', $lang->manage_polls['badge_closed']) : mp_badge('success', 'fa-lock-open', $lang->manage_polls['badge_open']) ?>
                        <?php if ($p['multiple']): ?><?= mp_badge('info', 'fa-list-check', $lang->manage_polls['badge_multi'], $lang->manage_polls['tip_multi']) ?><?php endif; ?>
                        <?php if ($p['public']): ?><?= mp_badge('secondary', 'fa-eye', $lang->manage_polls['badge_public'], $lang->manage_polls['tip_public']) ?><?php endif; ?>
                    </div>
                </td>
                <td><?= $exp_html ?></td>
                <td class="mp-date">
                    <?= date('d.m.Y', (int)$p['dateline']) ?>
                    <span><?= date('H:i', (int)$p['dateline']) ?></span>
                </td>
                <td class="mp-col-actions">
                    <div class="mp-actions">
                        <a href="<?= h(mp_url(['p' => 'votes', 'pid' => $pid])) ?>" class="mp-icon-btn mp-tone-secondary" title="<?= h($lang->manage_polls['tip_votes_results']) ?>">
                            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i><span class="visually-hidden"><?= h($lang->manage_polls['th_votes']) ?></span>
                        </a>
                        <a href="<?= h(mp_url(['p' => 'poll_edit', 'pid' => $pid])) ?>" class="mp-icon-btn mp-tone-primary" title="<?= h($lang->manage_polls['btn_edit']) ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i><span class="visually-hidden"><?= h($lang->manage_polls['btn_edit']) ?></span>
                        </a>
                        <form method="post"
                              data-confirm="<?= h($closed ? ags_fmt($lang->manage_polls['confirm_open_poll'], $pid) : ags_fmt($lang->manage_polls['confirm_close_poll'], $pid)) ?>"
                              data-confirm-text="<?= h($closed ? $lang->manage_polls['confirm_open_poll_text'] : $lang->manage_polls['confirm_close_poll_text']) ?>"
                              data-confirm-btn="<?= h($closed ? $lang->manage_polls['btn_open_poll'] : $lang->manage_polls['btn_close_poll']) ?>"
                              data-confirm-tone="<?= $closed ? 'success' : 'warning' ?>"
                              data-confirm-icon="question">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="poll_toggle">
                            <input type="hidden" name="pid" value="<?= $pid ?>">
                            <input type="hidden" name="back" value="<?= h($here) ?>">
                            <button type="submit" class="mp-icon-btn mp-tone-<?= $closed ? 'success' : 'warning' ?>" title="<?= h($closed ? $lang->manage_polls['btn_open_poll'] : $lang->manage_polls['btn_close_poll']) ?>">
                                <i class="fa-solid fa-<?= $closed ? 'lock-open' : 'lock' ?>" aria-hidden="true"></i><span class="visually-hidden"><?= h($closed ? $lang->manage_polls['btn_open'] : $lang->manage_polls['btn_close']) ?></span>
                            </button>
                        </form>
                        <form method="post"
                              data-confirm="<?= h(ags_fmt($lang->manage_polls['confirm_delete_poll'], $pid)) ?>"
                              data-confirm-text="<?= h(ags_fmt($lang->manage_polls['confirm_delete_poll_text'], $rv)) ?>"
                              data-confirm-btn="<?= h($lang->manage_polls['btn_delete_poll']) ?>">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="poll_delete">
                            <input type="hidden" name="pid" value="<?= $pid ?>">
                            <input type="hidden" name="back" value="<?= h($here) ?>">
                            <button type="submit" class="mp-icon-btn mp-tone-danger" title="<?= h($lang->manage_polls['btn_delete']) ?>">
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i><span class="visually-hidden"><?= h($lang->manage_polls['btn_delete']) ?></span>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mp-actionbar">
        <div class="mp-actionbar-group">
            <span class="mp-selected"><i class="fa-regular fa-square-check" aria-hidden="true"></i><?= ags_fmt(h($lang->manage_polls['lbl_selected']), '<b data-count-for="pollCheckbox">0</b>') ?></span>
            <button type="button" class="mp-btn mp-btn-ghost mp-btn-sm"
                    data-bulk="pollCheckbox" data-bulk-form="bulkPollsForm" data-bulk-action="poll_bulk_close"
                    data-bulk-verb="Close" data-bulk-noun="poll" data-confirm-tone="warning"
                    data-bulk-title-one="<?= h($lang->manage_polls['bulk_close_polls_one']) ?>" data-bulk-title="<?= h($lang->manage_polls['bulk_close_polls']) ?>"
                    data-bulk-btn="<?= h($lang->manage_polls['bulk_close_polls_btn']) ?>" data-bulk-empty="<?= h($lang->manage_polls['bulk_empty_polls']) ?>"
                    data-confirm-text="<?= h($lang->manage_polls['bulk_close_polls_text']) ?>">
                <i class="fa-solid fa-lock" aria-hidden="true"></i><?= h($lang->manage_polls['btn_close']) ?>
            </button>
            <button type="button" class="mp-btn mp-btn-soft-danger mp-btn-sm"
                    data-bulk="pollCheckbox" data-bulk-form="bulkPollsForm" data-bulk-action="poll_bulk_delete"
                    data-bulk-verb="Delete" data-bulk-noun="poll"
                    data-bulk-title-one="<?= h($lang->manage_polls['bulk_delete_polls_one']) ?>" data-bulk-title="<?= h($lang->manage_polls['bulk_delete_polls']) ?>"
                    data-bulk-btn="<?= h($lang->manage_polls['bulk_delete_polls_btn']) ?>" data-bulk-empty="<?= h($lang->manage_polls['bulk_empty_polls']) ?>"
                    data-confirm-text="<?= h($lang->manage_polls['bulk_delete_polls_text']) ?>">
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i><?= h($lang->manage_polls['btn_delete']) ?>
            </button>
        </div>
        <div class="mp-actionbar-group">
            <span class="mp-range"><?= mp_range($pg) ?></span>
            <?= mp_pagination($pg) ?>
        </div>
    </div>
</div>

<form method="post" id="bulkPollsForm">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" id="bulkPollsAction" value="">
    <input type="hidden" name="back" value="<?= h($here) ?>">
</form>

<?php elseif ($page === 'poll_create' || $page === 'poll_edit'):
    $is_edit = $page === 'poll_edit';
?>

<?php if (!empty($form_errors)): ?>
<div class="mp-alert mp-tone-danger" role="alert">
    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
    <div>
        <strong><?= h($is_edit ? $lang->manage_polls['alert_not_saved'] : $lang->manage_polls['alert_not_created']) ?></strong>
        <ul>
            <?php foreach ($form_errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<form method="post" id="pollForm" class="mp-form">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="<?= $is_edit ? 'poll_edit' : 'poll_create' ?>">
    <?php if ($is_edit): ?><input type="hidden" name="pid" value="<?= (int)$poll_row['pid'] ?>"><?php endif; ?>

    <div class="mp-form-grid">
        <div class="mp-form-main">

            <section class="mp-card mp-section">
                <h2 class="mp-section-title"><i class="fa-solid fa-circle-question" aria-hidden="true"></i><?= h($lang->manage_polls['sec_question']) ?></h2>
                <div class="mp-field">
                    <div class="mp-label-row">
                        <label for="pollQuestion" class="mp-label"><?= h($lang->manage_polls['lbl_question']) ?> <span class="mp-req">*</span></label>
                        <span class="mp-counter" data-counter-for="pollQuestion"></span>
                    </div>
                    <input type="text" name="question" id="pollQuestion" class="form-control form-control-lg" maxlength="200" required
                           value="<?= h($f['question']) ?>" placeholder="<?= h($lang->manage_polls['ph_question']) ?>" data-maxcount="200">
                </div>

                <?php if (!$is_edit): ?>
                <div class="mp-row-2">
                    <div class="mp-field">
                        <label for="pollForum" class="mp-label"><i class="fa-solid fa-folder-open" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_forum']) ?> <span class="mp-req">*</span></label>
                        <select name="fid" id="pollForum" class="form-select" required>
                            <option value=""><?= h($lang->manage_polls['opt_choose_forum']) ?></option>
                            <?php foreach ($forums_list as $fr): ?>
                            <option value="<?= (int)$fr['fid'] ?>" <?= (int)($form_data['fid'] ?? 0) === (int)$fr['fid'] ? 'selected' : '' ?>><?= h((string)$fr['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mp-hint"><?= h($lang->manage_polls['hint_forum']) ?></div>
                    </div>
                    <div class="mp-field">
                        <label for="threadTitle" class="mp-label"><i class="fa-solid fa-heading" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_thread_title']) ?> <span class="mp-req">*</span></label>
                        <input type="text" name="thread_title" id="threadTitle" class="form-control" maxlength="120" required
                               value="<?= h(trim((string)($form_data['thread_title'] ?? ''))) ?>" placeholder="<?= h($lang->manage_polls['ph_thread_title']) ?>">
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <section class="mp-card mp-section">
                <div class="mp-section-head">
                    <h2 class="mp-section-title"><i class="fa-solid fa-list-ol" aria-hidden="true"></i><?= h($lang->manage_polls['sec_answers']) ?></h2>
                    <span class="mp-counter"><b data-option-count><?= count($f['options']) ?></b> / 20</span>
                </div>
                <?php if ($is_edit): ?>
                <div class="mp-option-labels" aria-hidden="true"><span><?= h($lang->manage_polls['lbl_answer']) ?></span><span><?= h($lang->manage_polls['lbl_votes']) ?></span></div>
                <?php endif; ?>
                <div id="optionsContainer" class="mp-options" data-with-votes="<?= $is_edit ? '1' : '0' ?>" data-max="20" data-min="2">
                    <?php foreach ($f['options'] as $i => $o): ?>
                    <div class="mp-option option-row mp-c<?= $i % $MP_COLORS ?>">
                        <span class="mp-option-num"><?= $i + 1 ?></span>
                        <input type="text" name="options[]" class="form-control" placeholder="<?= h(ags_fmt($lang->manage_polls['ph_answer_n'], $i + 1)) ?>" value="<?= h($o['label']) ?>" required aria-label="<?= h(ags_fmt($lang->manage_polls['ph_answer_n'], $i + 1)) ?>">
                        <?php if ($is_edit): ?>
                        <input type="number" name="votes[]" class="form-control mp-option-votes" min="0" value="<?= (int)$o['votes'] ?>" aria-label="<?= h(ags_fmt($lang->manage_polls['aria_votes_for_n'], $i + 1)) ?>">
                        <?php endif; ?>
                        <button type="button" class="mp-icon-btn mp-tone-danger" data-option-remove title="<?= h($lang->manage_polls['btn_remove_answer']) ?>">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="visually-hidden"><?= h($lang->manage_polls['btn_remove']) ?></span>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="mp-add-option" data-option-add>
                    <i class="fa-solid fa-plus" aria-hidden="true"></i><?= h($lang->manage_polls['btn_add_answer']) ?>
                </button>
                <?php if ($is_edit): ?>
                <p class="mp-hint mp-hint-block"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><?= h($lang->manage_polls['hint_votes_edit']) ?></p>
                <?php endif; ?>
            </section>
        </div>

        <aside class="mp-form-side">
            <section class="mp-card mp-section">
                <h2 class="mp-section-title"><i class="fa-solid fa-sliders" aria-hidden="true"></i><?= h($lang->manage_polls['sec_rules']) ?></h2>

                <div class="mp-field">
                    <label for="pollTimeout" class="mp-label"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_timeout']) ?></label>
                    <div class="input-group">
                        <input type="number" name="timeout" id="pollTimeout" class="form-control" min="0" value="<?= $f['timeout'] ?>">
                        <span class="input-group-text"><?= h($lang->manage_polls['lbl_days']) ?></span>
                    </div>
                    <div class="mp-hint"><?= h($lang->manage_polls['hint_timeout']) ?></div>
                </div>
                <div class="mp-field">
                    <label for="pollMax" class="mp-label"><i class="fa-solid fa-list-check" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_maxoptions']) ?></label>
                    <div class="input-group">
                        <input type="number" name="maxoptions" id="pollMax" class="form-control" min="0" value="<?= $f['maxoptions'] ?>">
                        <span class="input-group-text"><?= h($lang->manage_polls['lbl_max']) ?></span>
                    </div>
                    <div class="mp-hint"><?= h($lang->manage_polls['hint_maxoptions']) ?></div>
                </div>

                <div class="mp-switches">
                    <?php foreach ([
                        ['multiple', 'multipleChk', 'fa-list-check', $lang->manage_polls['sw_multiple'], $lang->manage_polls['sw_multiple_desc']],
                        ['public',   'publicChk',   'fa-eye',        $lang->manage_polls['sw_public'],   $lang->manage_polls['sw_public_desc']],
                        ['closed',   'closedChk',   'fa-lock',       $lang->manage_polls['sw_closed'],   $lang->manage_polls['sw_closed_desc']],
                    ] as [$name, $id, $icon, $label, $desc]): ?>
                    <label class="mp-switch" for="<?= $id ?>">
                        <span class="mp-switch-icon"><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></span>
                        <span class="mp-switch-text">
                            <b><?= h($label) ?></b>
                            <small><?= h($desc) ?></small>
                        </span>
                        <span class="form-check form-switch m-0">
                            <input type="checkbox" role="switch" name="<?= $name ?>" id="<?= $id ?>" class="form-check-input" value="1" <?= $f[$name] ? 'checked' : '' ?>>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($is_edit): ?>
            <section class="mp-card mp-section mp-links">
                <a href="<?= h(mp_url(['p' => 'votes', 'pid' => (int)$poll_row['pid']])) ?>" class="mp-link-row">
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i><span><?= h($lang->manage_polls['link_votes_results']) ?></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </a>
                <?php if ((int)$poll_row['tid'] > 0): ?>
                <a href="../showthread.php?tid=<?= (int)$poll_row['tid'] ?>" target="_blank" rel="noopener" class="mp-link-row">
                    <i class="fa-solid fa-comments" aria-hidden="true"></i><span><?= h(ags_fmt($lang->manage_polls['link_open_thread'], (int)$poll_row['tid'])) ?></span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </aside>
    </div>

    <div class="mp-savebar">
        <span class="mp-savebar-note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <?= h($is_edit ? $lang->manage_polls['note_edit'] : $lang->manage_polls['note_create']) ?>
        </span>
        <div class="mp-savebar-actions">
            <a href="<?= h(mp_url(['p' => 'polls'])) ?>" class="mp-btn mp-btn-ghost"><i class="fa-solid fa-xmark" aria-hidden="true"></i><?= h($lang->manage_polls['btn_cancel']) ?></a>
            <button type="submit" class="mp-btn mp-btn-primary">
                <i class="fa-solid <?= $is_edit ? 'fa-floppy-disk' : 'fa-paper-plane' ?>" aria-hidden="true"></i><?= h($is_edit ? $lang->manage_polls['btn_save'] : $lang->manage_polls['btn_create']) ?>
            </button>
        </div>
    </div>
</form>

<?php elseif ($page === 'votes'): ?>

<?php if ($poll_stats !== null && $pd):
    $ptot   = array_sum(array_column($poll_stats, 'count'));
    $pmax   = $poll_stats ? max(array_column($poll_stats, 'count')) : 0;
?>
<section class="mp-card mp-results">
    <div class="mp-results-head">
        <div>
            <h2 class="mp-section-title"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i><?= h((string)$pd['question']) ?></h2>
            <div class="mp-meta">
                <span><?= h(ags_fmt($lang->manage_polls['lbl_poll_n'], (int)$pd['pid'])) ?></span>
                <span><i class="fa-solid fa-check-to-slot" aria-hidden="true"></i><?= h(ags_fmt($lang->manage_polls['lbl_votes_count'], number_format($ptot, 0, '.', ' '))) ?></span>
                <?= $pd['closed'] ? mp_badge('danger', 'fa-lock', $lang->manage_polls['badge_closed']) : mp_badge('success', 'fa-lock-open', $lang->manage_polls['badge_open']) ?>
            </div>
        </div>
        <a href="<?= h(mp_url(['p' => 'poll_edit', 'pid' => (int)$pd['pid']])) ?>" class="mp-btn mp-btn-ghost mp-btn-sm"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= h($lang->manage_polls['btn_edit_poll']) ?></a>
    </div>
    <div class="mp-results-list">
        <?php foreach ($poll_stats as $si => $s):
            $pct  = $ptot > 0 ? round($s['count'] / $ptot * 100, 1) : 0;
            $lead = $pmax > 0 && $s['count'] === $pmax; ?>
        <div class="mp-result mp-c<?= $si % $MP_COLORS ?><?= $lead ? ' is-lead' : '' ?>">
            <div class="mp-result-top">
                <span class="mp-result-label">
                    <?php if ($lead): ?><i class="fa-solid fa-crown" title="<?= h($lang->manage_polls['tip_leading']) ?>" aria-hidden="true"></i><?php endif; ?>
                    <?= h($s['label']) ?>
                </span>
                <span class="mp-result-val"><b><?= $pct ?>%</b> <?= number_format($s['count'], 0, '.', ' ') ?></span>
            </div>
            <div class="mp-result-track"><span style="width: <?= $pct ?>%"></span></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<form method="get" action="<?= h(mp_form_action()) ?>" class="mp-card mp-filters mp-filters--votes">
    <?= mp_route_fields() ?>
    <input type="hidden" name="p" value="votes">
    <div class="mp-field">
        <label for="mpVQ" class="mp-label"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_search']) ?></label>
        <input type="search" id="mpVQ" name="q" class="form-control" placeholder="<?= h($lang->manage_polls['ph_search_votes']) ?>" value="<?= h($vsearch) ?>">
    </div>
    <div class="mp-field">
        <label for="mpVPoll" class="mp-label"><i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_poll']) ?></label>
        <select id="mpVPoll" name="pid" class="form-select">
            <option value=""><?= h($lang->manage_polls['opt_all_polls']) ?></option>
            <?php foreach ($all_polls as $ap): ?>
            <option value="<?= (int)$ap['pid'] ?>" <?= $vpid === (int)$ap['pid'] ? 'selected' : '' ?>>#<?= (int)$ap['pid'] ?> <?= mp_cut((string)$ap['question'], 45) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mp-field">
        <label for="mpVUid" class="mp-label"><i class="fa-solid fa-user" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_user_id']) ?></label>
        <input type="number" id="mpVUid" name="uid" class="form-control" min="0" value="<?= $vuid ?: '' ?>">
    </div>
    <div class="mp-filter-actions">
        <button type="submit" class="mp-btn mp-btn-primary"><i class="fa-solid fa-filter" aria-hidden="true"></i><?= h($lang->manage_polls['btn_apply']) ?></button>
        <?php if ($has_filters): ?>
        <a href="<?= h(mp_url(['p' => 'votes'])) ?>" class="mp-btn mp-btn-ghost"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><?= h($lang->manage_polls['btn_reset']) ?></a>
        <?php endif; ?>
    </div>
</form>

<div class="mp-card mp-table-card">
    <div class="table-responsive">
        <table class="table mp-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="mp-col-check"><input type="checkbox" class="form-check-input" id="selectAll" data-select-all="voteCheckbox" aria-label="<?= h($lang->manage_polls['aria_select_all_votes']) ?>"></th>
                    <th class="mp-col-id">#</th>
                    <th><i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i><?= h($lang->manage_polls['th_poll']) ?></th>
                    <th><i class="fa-solid fa-user" aria-hidden="true"></i><?= h($lang->manage_polls['th_member']) ?></th>
                    <th><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i><?= h($lang->manage_polls['th_answer']) ?></th>
                    <th><i class="fa-solid fa-network-wired" aria-hidden="true"></i><?= h($lang->manage_polls['th_ip']) ?></th>
                    <th><i class="fa-regular fa-clock" aria-hidden="true"></i><?= h($lang->manage_polls['th_when']) ?></th>
                    <th class="mp-col-actions"><span class="visually-hidden"><?= h($lang->manage_polls['th_actions']) ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$votes): ?>
            <tr>
                <td colspan="8">
                    <div class="mp-empty">
                        <span class="mp-empty-icon"><i class="fa-solid fa-check-to-slot" aria-hidden="true"></i></span>
                        <p><?= h($has_filters ? $lang->manage_polls['empty_votes_filtered'] : $lang->manage_polls['empty_no_votes']) ?></p>
                        <?php if ($has_filters): ?>
                        <a href="<?= h(mp_url(['p' => 'votes'])) ?>" class="mp-btn mp-btn-ghost"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><?= h($lang->manage_polls['btn_reset_filters']) ?></a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($votes as $v):
                $ipaddress = mp_ip($v['ipaddress'] ?? '');
                $vo        = (int)$v['voteoption'];
                $uname     = (string)($v['username'] ?? '');
            ?>
            <tr>
                <td class="mp-col-check"><input type="checkbox" class="form-check-input voteCheckbox" name="vids[]" value="<?= (int)$v['vid'] ?>" form="bulkForm" aria-label="<?= h(ags_fmt($lang->manage_polls['aria_select_vote'], (int)$v['vid'])) ?>"></td>
                <td class="mp-col-id"><?= (int)$v['vid'] ?></td>
                <td class="mp-col-pollref">
                    <a href="<?= h(mp_url(['p' => 'votes', 'pid' => (int)$v['pid']])) ?>" class="mp-ref">#<?= (int)$v['pid'] ?></a>
                    <span class="mp-muted"><?= $v['question'] !== null ? mp_cut((string)$v['question'], 40) : h($lang->manage_polls['lbl_deleted_poll']) ?></span>
                </td>
                <td>
                    <a href="<?= h(mp_url(['p' => 'votes_by_user', 'uid' => (int)$v['uid']])) ?>" class="mp-user">
                        <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
                        <span><?= h($uname !== '' ? $uname : $lang->manage_polls['lbl_unknown_user']) ?></span>
                        <small>#<?= (int)$v['uid'] ?></small>
                    </a>
                </td>
                <td>
                    <span class="mp-answer mp-c<?= ($vo - 1 + $MP_COLORS) % $MP_COLORS ?>">
                        <i class="mp-dot" aria-hidden="true"></i><?= mp_cut(opt_label((string)($v['options'] ?? ''), $vo), 45) ?>
                    </span>
                </td>
                <td>
                    <?php if ($ipaddress !== ''): ?>
                    <a href="<?= h(mp_url(['p' => 'votes', 'q' => $ipaddress])) ?>" class="mp-ip" title="<?= h($lang->manage_polls['tip_ip_votes']) ?>"><code><?= h($ipaddress) ?></code></a>
                    <?php else: ?><span class="mp-muted">—</span><?php endif; ?>
                </td>
                <td class="mp-date">
                    <?= date('d.m.Y', (int)$v['dateline']) ?>
                    <span><?= date('H:i:s', (int)$v['dateline']) ?></span>
                </td>
                <td class="mp-col-actions">
                    <div class="mp-actions">
                        <form method="post" data-confirm="<?= h(ags_fmt($lang->manage_polls['confirm_delete_vote'], (int)$v['vid'])) ?>" data-confirm-text="<?= h($lang->manage_polls['confirm_cannot_undo']) ?>" data-confirm-btn="<?= h($lang->manage_polls['btn_delete_vote']) ?>">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="vote_delete">
                            <input type="hidden" name="vid" value="<?= (int)$v['vid'] ?>">
                            <input type="hidden" name="back" value="<?= h($here) ?>">
                            <button type="submit" class="mp-icon-btn mp-tone-danger" title="<?= h($lang->manage_polls['btn_delete_vote']) ?>">
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i><span class="visually-hidden"><?= h($lang->manage_polls['btn_delete']) ?></span>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($votes): ?>
    <div class="mp-actionbar">
        <div class="mp-actionbar-group">
            <span class="mp-selected"><i class="fa-regular fa-square-check" aria-hidden="true"></i><?= ags_fmt(h($lang->manage_polls['lbl_selected']), '<b data-count-for="voteCheckbox">0</b>') ?></span>
            <button type="button" class="mp-btn mp-btn-soft-danger mp-btn-sm"
                    data-bulk="voteCheckbox" data-bulk-form="bulkForm"
                    data-bulk-verb="Delete" data-bulk-noun="vote"
                    data-bulk-title-one="<?= h($lang->manage_polls['bulk_delete_votes_one']) ?>" data-bulk-title="<?= h($lang->manage_polls['bulk_delete_votes']) ?>"
                    data-bulk-btn="<?= h($lang->manage_polls['bulk_delete_votes_btn']) ?>" data-bulk-empty="<?= h($lang->manage_polls['bulk_empty_votes']) ?>"
                    data-confirm-text="<?= h($lang->manage_polls['confirm_cannot_undo']) ?>">
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i><?= h($lang->manage_polls['btn_delete']) ?>
            </button>
        </div>
        <div class="mp-actionbar-group">
            <span class="mp-range"><?= mp_range($vpg) ?></span>
            <?= mp_pagination($vpg) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<form method="post" id="bulkForm">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="vote_bulk_delete">
    <input type="hidden" name="back" value="<?= h($here) ?>">
</form>

<?php elseif ($page === 'votes_by_user'): ?>

<form method="get" action="<?= h(mp_form_action()) ?>" class="mp-card mp-filters mp-filters--users">
    <?= mp_route_fields() ?>
    <input type="hidden" name="p" value="votes_by_user">
    <div class="mp-field">
        <label for="mpBQ" class="mp-label"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_search']) ?></label>
        <input type="search" id="mpBQ" name="q" class="form-control" placeholder="<?= h($lang->manage_polls['ph_search_users']) ?>" value="<?= h($bsearch) ?>">
    </div>
    <div class="mp-field">
        <label for="mpBUid" class="mp-label"><i class="fa-solid fa-user" aria-hidden="true"></i><?= h($lang->manage_polls['lbl_user_id']) ?></label>
        <input type="number" id="mpBUid" name="uid" class="form-control" min="0" value="<?= $buid ?: '' ?>">
    </div>
    <div class="mp-filter-actions">
        <button type="submit" class="mp-btn mp-btn-primary"><i class="fa-solid fa-filter" aria-hidden="true"></i><?= h($lang->manage_polls['btn_apply']) ?></button>
        <?php if ($has_filters): ?>
        <a href="<?= h(mp_url(['p' => 'votes_by_user'])) ?>" class="mp-btn mp-btn-ghost"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><?= h($lang->manage_polls['btn_reset']) ?></a>
        <?php endif; ?>
    </div>
</form>

<div class="mp-card mp-table-card">
    <div class="table-responsive">
        <table class="table mp-table align-middle mb-0">
            <thead>
                <tr>
                    <th><i class="fa-solid fa-user" aria-hidden="true"></i><?= h($lang->manage_polls['th_member']) ?></th>
                    <th class="mp-col-num"><i class="fa-solid fa-check-to-slot" aria-hidden="true"></i><?= h($lang->manage_polls['th_votes']) ?></th>
                    <th class="mp-col-num"><i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i><?= h($lang->manage_polls['th_polls']) ?></th>
                    <th><i class="fa-solid fa-network-wired" aria-hidden="true"></i><?= h($lang->manage_polls['th_last_ip']) ?></th>
                    <th><i class="fa-regular fa-clock" aria-hidden="true"></i><?= h($lang->manage_polls['th_last_vote']) ?></th>
                    <th class="mp-col-actions"><span class="visually-hidden"><?= h($lang->manage_polls['th_actions']) ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$by_users): ?>
            <tr>
                <td colspan="6">
                    <div class="mp-empty">
                        <span class="mp-empty-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                        <p><?= h($has_filters ? $lang->manage_polls['empty_voters_filtered'] : $lang->manage_polls['empty_no_votes']) ?></p>
                        <?php if ($has_filters): ?>
                        <a href="<?= h(mp_url(['p' => 'votes_by_user'])) ?>" class="mp-btn mp-btn-ghost"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><?= h($lang->manage_polls['btn_reset_filters']) ?></a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($by_users as $u):
                $uid   = (int)$u['uid'];
                $lip   = mp_ip($u['lip'] ?? '');
                $uname = (string)($u['username'] ?? '');
            ?>
            <tr>
                <td>
                    <a href="<?= h(mp_url(['p' => 'votes_by_user', 'uid' => $uid])) ?>" class="mp-user">
                        <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
                        <span><?= h($uname !== '' ? $uname : $lang->manage_polls['lbl_unknown_user']) ?></span>
                        <small>#<?= $uid ?></small>
                    </a>
                </td>
                <td class="mp-col-num"><span class="mp-num"><?= (int)$u['vc'] ?></span></td>
                <td class="mp-col-num"><?= (int)$u['pc'] ?></td>
                <td>
                    <?php if ($lip !== ''): ?>
                    <a href="<?= h(mp_url(['p' => 'votes', 'q' => $lip])) ?>" class="mp-ip" title="<?= h($lang->manage_polls['tip_ip_votes']) ?>"><code><?= h($lip) ?></code></a>
                    <?php else: ?><span class="mp-muted">—</span><?php endif; ?>
                </td>
                <td class="mp-date">
                    <?= date('d.m.Y', (int)$u['lv']) ?>
                    <span><?= date('H:i', (int)$u['lv']) ?></span>
                </td>
                <td class="mp-col-actions">
                    <div class="mp-actions">
                        <a href="<?= h(mp_url(['p' => 'votes', 'uid' => $uid])) ?>" class="mp-icon-btn mp-tone-primary" title="<?= h($lang->manage_polls['tip_member_votes']) ?>">
                            <i class="fa-solid fa-list" aria-hidden="true"></i><span class="visually-hidden"><?= h($lang->manage_polls['th_votes']) ?></span>
                        </a>
                        <form method="post"
                              data-confirm="<?= h($uname !== '' ? ags_fmt($lang->manage_polls['confirm_delete_user_votes'], (int)$u['vc'], $uname) : ags_fmt($lang->manage_polls['confirm_delete_user_votes_id'], (int)$u['vc'], $uid)) ?>"
                              data-confirm-text="<?= h($lang->manage_polls['confirm_delete_user_votes_text']) ?>"
                              data-confirm-btn="<?= h($lang->manage_polls['btn_delete_votes']) ?>">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="vote_delete_user">
                            <input type="hidden" name="uid" value="<?= $uid ?>">
                            <input type="hidden" name="back" value="<?= h($here) ?>">
                            <button type="submit" class="mp-icon-btn mp-tone-danger" title="<?= h($lang->manage_polls['tip_delete_user_votes']) ?>">
                                <i class="fa-solid fa-user-xmark" aria-hidden="true"></i><span class="visually-hidden"><?= h($lang->manage_polls['btn_delete_all_votes']) ?></span>
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
<section class="mp-card mp-table-card">
    <div class="mp-table-title">
        <h2 class="mp-section-title"><i class="fa-solid fa-list-ul" aria-hidden="true"></i><?= h(($by_users[0]['username'] ?? '') !== '' ? ags_fmt($lang->manage_polls['sec_user_votes'], (string)$by_users[0]['username']) : ags_fmt($lang->manage_polls['sec_user_votes_id'], $buid)) ?></h2>
    </div>
    <div class="table-responsive">
        <table class="table mp-table mp-table--compact align-middle mb-0">
            <thead>
                <tr>
                    <th class="mp-col-id">#</th>
                    <th><?= h($lang->manage_polls['th_poll']) ?></th>
                    <th><?= h($lang->manage_polls['th_answer']) ?></th>
                    <th><?= h($lang->manage_polls['th_when']) ?></th>
                    <th class="mp-col-actions"><span class="visually-hidden"><?= h($lang->manage_polls['th_actions']) ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($user_detail as $v): $vo = (int)$v['voteoption']; ?>
            <tr>
                <td class="mp-col-id"><?= (int)$v['vid'] ?></td>
                <td class="mp-col-pollref">
                    <a href="<?= h(mp_url(['p' => 'votes', 'pid' => (int)$v['pid']])) ?>" class="mp-ref">#<?= (int)$v['pid'] ?></a>
                    <span class="mp-muted"><?= $v['question'] !== null ? mp_cut((string)$v['question'], 50) : h($lang->manage_polls['lbl_deleted_poll']) ?></span>
                </td>
                <td>
                    <span class="mp-answer mp-c<?= ($vo - 1 + $MP_COLORS) % $MP_COLORS ?>">
                        <i class="mp-dot" aria-hidden="true"></i><?= mp_cut(opt_label((string)($v['options'] ?? ''), $vo), 40) ?>
                    </span>
                </td>
                <td class="mp-date"><?= date('d.m.Y', (int)$v['dateline']) ?> <span><?= date('H:i:s', (int)$v['dateline']) ?></span></td>
                <td class="mp-col-actions">
                    <div class="mp-actions">
                        <form method="post" data-confirm="<?= h(ags_fmt($lang->manage_polls['confirm_delete_vote'], (int)$v['vid'])) ?>" data-confirm-text="<?= h($lang->manage_polls['confirm_cannot_undo']) ?>" data-confirm-btn="<?= h($lang->manage_polls['btn_delete_vote']) ?>">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="vote_delete">
                            <input type="hidden" name="vid" value="<?= (int)$v['vid'] ?>">
                            <input type="hidden" name="back" value="<?= h($here) ?>">
                            <button type="submit" class="mp-icon-btn mp-tone-danger" title="<?= h($lang->manage_polls['btn_delete_vote']) ?>">
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i><span class="visually-hidden"><?= h($lang->manage_polls['btn_delete']) ?></span>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php endif; ?>

</div>

<?php stdfoot(); ?>