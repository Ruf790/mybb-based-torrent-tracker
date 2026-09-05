<?php


declare(strict_types=1);


// Disallow direct access to this file for security reasons
if (!defined("STAFF_PANEL")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure STAFF_PANEL is defined.");
}

require_once INC_PATH . '/functions_image_recode.php';


// Initialize missing inputs
foreach (['action', 'do', 'module'] as $input) {
    $mybb->input[$input] ??= '';
}

function my_chmod(string $file, string $mode): bool
{
    // Passing $mode as an octal number causes strlen and substr to return incorrect values
    if (!str_starts_with($mode, '0') || strlen($mode) !== 4) {
        return false;
    }
    
    $old_umask = umask(0);
    $result = chmod($file, octdec($mode));
    umask($old_umask);
    
    return $result;
}

function mk_path_abs(string $path, string $base = TSDIR): string
{
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $firstChar = substr($path, 0, 1);
    
    if ($firstChar !== '/' && !($isWin && ($firstChar === '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path)))) {
        $path = $base . $path;
    }

    return $path;
}

function output_auto_redirect(string $form, string $prompt): void
{
    global $lang;
    echo <<<HTML
<div class="container mt-3">
    <p>{$prompt}</p>
    <br />
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const button = document.getElementById('proceed_button');
            if (button) {
                button.value = 'Automatically Redirecting\u2026';
                button.disabled = true;
                button.style.color = '#aaa';
                button.style.borderColor = '#aaa';
                const form = button.closest('form');
                if (form) form.submit();
            }
        });
    </script>
    <button class="btn btn-primary" type="button" id="proceed_button" disabled>
        Automatically Redirecting&hellip;
    </button>
</div>
HTML;
}

$plugins->run_hooks("admin_tools_recount_rebuild");

/**
 * Rebuild forum counters
 */
function acp_rebuild_forum_counters(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_forum_counters");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_forums FROM forums");
    $num_forums = $query ? (int)$db->fetch_field($query, 'num_forums') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('forumcounters', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT fid FROM forums ORDER BY fid ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($forum = $db->fetch_array($query))) {
        $update = ['parentlist' => make_parent_list((int)$forum['fid'])];
        $db->sql_query_prepared("UPDATE forums SET parentlist = ? WHERE fid = ?", [$update['parentlist'], (int)$forum['fid']]);
        rebuild_forum_counters((int)$forum['fid']);
    }

    $message = $lang->success_rebuilt_forum_counters ?? 'The forum counters have been rebuilt successfully';
    
    check_proceed(
        $num_forums, 
        $end, 
        ++$page, 
        $per_page, 
        "forumcounters", 
        "do_rebuildforumcounters",
        $message
    );
}

/**
 * Rebuild thread counters
 */
function acp_rebuild_thread_counters(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_thread_counters");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_threads FROM threads");
    $num_threads = $query ? (int)$db->fetch_field($query, 'num_threads') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('threadcounters', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT tid FROM threads ORDER BY tid ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($thread = $db->fetch_array($query))) {
        rebuild_thread_counters((int)$thread['tid']);
    }

    $message = $lang->success_rebuilt_thread_counters ?? 'The thread counters have been rebuilt successfully';
    
    check_proceed(
        $num_threads, 
        $end, 
        ++$page, 
        $per_page, 
        "threadcounters", 
        "do_rebuildthreadcounters", 
        $message
    );
}

/**
 * Rebuild poll counters
 */
function acp_rebuild_poll_counters(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_poll_counters");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_polls FROM polls");
    $num_polls = $query ? (int)$db->fetch_field($query, 'num_polls') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('pollcounters', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT pid FROM polls ORDER BY pid ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($poll = $db->fetch_array($query))) {
        rebuild_poll_counters((int)$poll['pid']);
    }

    $message = $lang->success_rebuilt_poll_counters ?? 'The poll counters have been rebuilt successfully';
    
    check_proceed(
        $num_polls, 
        $end, 
        ++$page, 
        $per_page, 
        "pollcounters", 
        "do_rebuildpollcounters", 
        $message
    );
}

/**
 * Recount thread ratings (numratings/totalratings cache columns)
 */
function acp_recount_thread_ratings(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_thread_ratings");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_threads FROM threads");
    $num_threads = $query ? (int)$db->fetch_field($query, 'num_threads') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('threadratings', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT tid FROM threads ORDER BY tid ASC LIMIT ?, ?",
        [$start, $per_page]
    );

    while ($query && ($thread = $db->fetch_array($query))) {
        $tid = (int)$thread['tid'];

        $r = $db->sql_query_prepared("SELECT ROUND(AVG(rating),1) AS avg, COUNT(id) AS cnt FROM threadratings WHERE tid = ?", [$tid]);
        $row = $r ? $db->fetch_array($r) : null;
        $avg   = (float)($row['avg'] ?? 0);
        $count = (int)($row['cnt'] ?? 0);

        $db->sql_query_prepared(
            "UPDATE threads SET numratings = ?, totalratings = ? WHERE tid = ?",
            [$count, (int)round($avg * $count), $tid]
        );
    }

    $message = $lang->success_rebuilt_thread_ratings ?? 'Thread ratings have been recounted successfully';

    check_proceed(
        $num_threads,
        $end,
        ++$page,
        $per_page,
        "threadratings",
        "do_recountthreadratings",
        $message
    );
}

/**
 * Recount torrent comment counts (torrents.comments cache column)
 */
function acp_recount_torrent_comments(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_torrent_comments");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_torrents FROM torrents");
    $num_torrents = $query ? (int)$db->fetch_field($query, 'num_torrents') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('torrentcomments', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT id FROM torrents ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );

    while ($query && ($torrent = $db->fetch_array($query))) {
        $tid = (int)$torrent['id'];

        $r = $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM comments WHERE torrent = ?", [$tid]);
        $row = $r ? $db->fetch_array($r) : null;
        $count = (int)($row['cnt'] ?? 0);

        $db->sql_query_prepared("UPDATE torrents SET comments = ? WHERE id = ?", [$count, $tid]);
    }

    $message = $lang->success_rebuilt_torrent_comments ?? 'Torrent comment counts have been recounted successfully';

    check_proceed(
        $num_torrents,
        $end,
        ++$page,
        $per_page,
        "torrentcomments",
        "do_recounttorrentcomments",
        $message
    );
}

/**
 * Recount user posts
 */
function acp_recount_user_posts(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_user_posts");

    $query = $db->sql_query_prepared("SELECT COUNT(id) as num_users FROM users");
    $num_users = $query ? (int)$db->fetch_field($query, 'num_users') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('userposts', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $fids = [];
    $query = $db->sql_query_prepared("SELECT fid FROM forums WHERE usepostcounts = 0");
    
    while ($query && ($forum = $db->fetch_array($query))) {
        $fids[] = (int)$forum['fid'];
    }
    
    $fidsCondition = '';
    if (!empty($fids)) {
        $fidsList = implode(',', $fids);
        $fidsCondition = " AND p.fid NOT IN({$fidsList})";
    }

    $query = $db->sql_query_prepared(
        "SELECT id FROM users ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($user = $db->fetch_array($query))) {
        $query2 = $db->sql_query_prepared("
            SELECT COUNT(p.pid) AS post_count
            FROM posts p
            LEFT JOIN threads t ON (t.tid = p.tid)
            WHERE p.uid = ? 
            AND t.visible > 0 
            AND p.visible > 0
            {$fidsCondition}
        ", [(int)$user['id']]);
        
        $num_posts = $query2 ? (int)$db->fetch_field($query2, "post_count") : 0;
        $db->sql_query_prepared("UPDATE users SET postnum = ? WHERE id = ?", [$num_posts, (int)$user['id']]);
    }

    $message = $lang->success_rebuilt_user_post_counters ?? 'The user posts count have been recounted successfully';
    
    check_proceed(
        $num_users, 
        $end, 
        ++$page, 
        $per_page, 
        "userposts", 
        "do_recountuserposts", 
        $message
    );
}

/**
 * Recount user threads
 */
function acp_recount_user_threads(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_user_threads");

    $query = $db->sql_query_prepared("SELECT COUNT(id) as num_users FROM users");
    $num_users = $query ? (int)$db->fetch_field($query, 'num_users') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('userthreads', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $fids = [];
    $query = $db->sql_query_prepared("SELECT fid FROM forums WHERE usethreadcounts = 0");
    
    while ($query && ($forum = $db->fetch_array($query))) {
        $fids[] = (int)$forum['fid'];
    }
    
    $fidsCondition = '';
    if (!empty($fids)) {
        $fidsList = implode(',', $fids);
        $fidsCondition = " AND t.fid NOT IN({$fidsList})";
    }

    $query = $db->sql_query_prepared(
        "SELECT id FROM users ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($user = $db->fetch_array($query))) {
        $query2 = $db->sql_query_prepared("
            SELECT COUNT(t.tid) AS thread_count
            FROM threads t
            WHERE t.uid = ? 
            AND t.visible > 0 
            AND t.closed NOT LIKE 'moved|%'
            {$fidsCondition}
        ", [(int)$user['id']]);
        
        $num_threads = $query2 ? (int)$db->fetch_field($query2, "thread_count") : 0;
        $db->sql_query_prepared("UPDATE users SET threadnum = ? WHERE id = ?", [$num_threads, (int)$user['id']]);
    }

    $message = $lang->success_rebuilt_user_thread_counters ?? 'The user threads count have been recounted successfully';
    
    check_proceed(
        $num_users, 
        $end, 
        ++$page, 
        $per_page, 
        "userthreads", 
        "do_recountuserthreads", 
        $message
    );
}

/**
 * Recount private messages (total and unread) for users
 */
function acp_recount_private_messages(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_recount_private_messages");

    $query = $db->sql_query_prepared("SELECT COUNT(id) as num_users FROM users");
    $num_users = $query ? (int)$db->fetch_field($query, 'num_users') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('privatemessages', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    require_once INC_PATH . "/functions_user.php";

    $query = $db->sql_query_prepared(
        "SELECT id FROM users ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($user = $db->fetch_array($query))) {
        update_pm_count((int)$user['id']);
    }

    $message = $lang->success_rebuilt_private_messages ?? 'The user private message count has been recounted successfully';
    
    check_proceed(
        $num_users, 
        $end, 
        ++$page, 
        $per_page, 
        "privatemessages", 
        "do_recountprivatemessages", 
        $message
    );
}

function acp_recount_user_comments(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_recount_comments");

    $query = $db->sql_query_prepared("SELECT COUNT(id) as num_users FROM users");
    $num_users = $query ? (int)$db->fetch_field($query, 'num_users') : 0;
    
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('comments', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;
    
    $query = $db->sql_query_prepared(
        "SELECT id FROM users ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($user = $db->fetch_array($query))) {
        $query2 = $db->sql_query_prepared("
            SELECT COUNT(c.id) AS post_count
            FROM comments c
            LEFT JOIN torrents t ON (t.id = c.torrent)
            WHERE c.user = ?
        ", [(int)$user['id']]);
        
        $num_posts = $query2 ? (int)$db->fetch_field($query2, "post_count") : 0;
        $db->sql_query_prepared("UPDATE users SET comms = ? WHERE id = ?", [$num_posts, (int)$user['id']]);
    }
    
    $message = $lang->success_rebuilt_private_messages ?? 'The user private message count has been recounted successfully';
    
    check_proceed(
        $num_users, 
        $end, 
        ++$page, 
        $per_page, 
        "comments", 
        "do_comments", 
        $message
    );
}

/**
 * Rebuild thumbnails for attachments
 */
function acp_rebuild_attachment_thumbnails(): void
{
    global $db, $mybb, $lang, $plugins, $uploadspath, $attachthumbh, $attachthumbw;

    $plugins->run_hooks("admin_tools_recount_rebuild_attachment_thumbs");

    $query = $db->sql_query_prepared("SELECT COUNT(aid) as num_attachments FROM attachments");
    $num_attachments = $query ? (int)$db->fetch_field($query, 'num_attachments') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('attachmentthumbs', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;
    
	
	$uploadspath_abs = TSDIR . '/uploads';
	
  

    $query = $db->sql_query_prepared(
        "SELECT * FROM attachments ORDER BY aid ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    $imageExtensions = ['gif', 'png', 'jpg', 'jpeg', 'webp'];
    $extToMime = ['gif' => 'image/gif', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
    
    while ($query && ($attachment = $db->fetch_array($query))) {
        $ext = strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $imageExtensions, true)) {
            $thumbname = str_replace(".attach", "_thumb.{$ext}", $attachment['attachname']);

            // Реальная сигнатура create_thumbnail(): (src, dst-ФАЙЛ, maxW,
            // maxH, mime) — не совпадает со старой generate_thumbnail(file,
            // path-ПАПКА, filename, maxHeight, maxWidth). Раньше здесь был
            // вызов с аргументами от старой функции, просто с заменённым
            // именем — $thumbname передавался туда, где ждут число (ширину),
            // а $attachthumbw — туда, где ждут MIME-строку.
            $created = create_thumbnail(
                $uploadspath_abs . "/" . $attachment['attachname'],
                $uploadspath_abs . "/" . $thumbname,
                (int)$attachthumbw,
                (int)$attachthumbh,
                $extToMime[$ext] ?? ''
            );

            // create_thumbnail() возвращает bool, а не массив с 'code'/
            // 'filename' (это был контракт старой generate_thumbnail()) —
            // обращение к $thumbnail['code'] на булевом значении молча не
            // работало бы как задумано.
            $thumbnailFilename = $created ? $thumbname : '';

            $db->sql_query_prepared(
                "UPDATE attachments SET thumbnail = ? WHERE aid = ?",
                [$thumbnailFilename, (int)$attachment['aid']]
            );
        }
    }

    $message = $lang->success_rebuilt_attachment_thumbnails ?? 'The attachment thumbnails have been rebuilt successfully';
    
    check_proceed(
        $num_attachments, 
        $end, 
        ++$page, 
        $per_page, 
        "attachmentthumbs", 
        "do_rebuildattachmentthumbs", 
        $message
    );
}








function acp_rebuild_comment_attachment_thumbnails(): void
{
    global $db, $mybb, $lang, $plugins, $attachthumbh, $attachthumbw;

    $plugins->run_hooks('admin_tools_recount_rebuild_comment_attachment_thumbs');

    $query = $db->sql_query_prepared("SELECT COUNT(aid) as num_attachments FROM attachments WHERE comment_id > 0");
    $num_attachments = $query ? (int)$db->fetch_field($query, 'num_attachments') : 0;

    $page     = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('commentattachmentthumbs', MyBB::INPUT_INT);
    $start    = ($page - 1) * $per_page;
    $end      = $start + $per_page;

    $uploadspath_abs = TSDIR . '/uploads/attachments';

   

    $query = $db->sql_query_prepared(
        "SELECT * FROM attachments WHERE comment_id > 0 ORDER BY aid ASC LIMIT ?, ?",
        [$start, $per_page]
    );

    $imageExtensions = ['gif', 'png', 'jpg', 'jpeg', 'webp'];
    $extToMime = ['gif' => 'image/gif', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    while ($query && ($attachment = $db->fetch_array($query))) {
        $ext = strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION));

        if (in_array($ext, $imageExtensions, true)) {
            $thumbname = 'thumb_' . $attachment['attachname'];

            // Та же проблема, что и в форумной функции выше — аргументы
            // соответствовали старой generate_thumbnail(), не реальной
            // сигнатуре create_thumbnail() (src, dst-ФАЙЛ, maxW, maxH, mime).
            $created = create_thumbnail(
                $uploadspath_abs . '/' . $attachment['attachname'],
                $uploadspath_abs . '/' . $thumbname,
                (int)$attachthumbw,
                (int)$attachthumbh,
                $extToMime[$ext] ?? ''
            );

            // create_thumbnail() возвращает bool, не массив.
            $thumbnailFilename = $created ? $thumbname : '';

            $db->sql_query_prepared(
                "UPDATE attachments SET thumbnail = ? WHERE aid = ?",
                [$thumbnailFilename, (int)$attachment['aid']]
            );
        }
    }

    $message = $lang->success_rebuilt_comment_attachment_thumbnails ?? 'The comment attachment thumbnails have been rebuilt successfully';

    check_proceed(
        $num_attachments,
        $end,
        ++$page,
        $per_page,
        'commentattachmentthumbs',
        'do_rebuildcommentattachmentthumbs',
        $message
    );
}













function check_proceed(
    int $current, 
    int $finish, 
    int $next_page, 
    int $per_page, 
    string $name, 
    string $name2, 
    ?string $message = null
): void {
    global $page, $lang, $mybb, $_this_script_;

    // Устанавливаем значение по умолчанию если message is null
    $message = $message ?? 'success_rebuilt';


    if ($finish >= $current) {
        flash_message($message, 'success');
        admin_redirect("index.php?act=recount_rebuild");
    } else {
       stdhead();

        $form = <<<HTML
       <form action="{$_this_script_}" method="post">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
            <input type="hidden" name="page" value="{$next_page}" />
            <input type="hidden" name="{$name}" value="{$per_page}" />
            <input type="hidden" name="{$name2}" value="Go" />
HTML;
        
        echo $form;
        output_auto_redirect($form, 'Click "Proceed" to continue the recount and rebuild process');
        echo '</form>';

        stdfoot();
        exit;
    }
}





if (!$mybb->input['action']) {
    $plugins->run_hooks("admin_tools_recount_rebuild_start");

    if ($mybb->request_method == "post") {
        require_once INC_PATH . "/functions_rebuild.php";

        // CSRF-проверка - токен уже выводился в формах (my_post_key), но
        // нигде не проверялся. Одна проверка тут закрывает сразу все
        // 12 мутирующих действий этого файла (foreach ниже + do_recountstats).
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            die("Invalid security token. Please refresh the page and try again.");
        }

        $mybb->input['page'] = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
        $plugins->run_hooks("admin_tools_do_recount_rebuild");

        $actions = [
            'do_rebuildforumcounters' => [
                'hook' => "admin_tools_recount_rebuild_forum_counters",
                'input' => 'forumcounters',
                'default' => 50,
                'function' => 'acp_rebuild_forum_counters'
            ],
            'do_rebuildthreadcounters' => [
                'hook' => "admin_tools_recount_rebuild_thread_counters",
                'input' => 'threadcounters',
                'default' => 500,
                'function' => 'acp_rebuild_thread_counters'
            ],
            'do_recountuserposts' => [
                'hook' => "admin_tools_recount_rebuild_user_posts",
                'input' => 'userposts',
                'default' => 500,
                'function' => 'acp_recount_user_posts'
            ],
            'do_recountuserthreads' => [
                'hook' => "admin_tools_recount_rebuild_user_threads",
                'input' => 'userthreads',
                'default' => 500,
                'function' => 'acp_recount_user_threads'
            ],
            'do_rebuildattachmentthumbs' => [
                'hook' => "admin_tools_recount_rebuild_attachment_thumbs",
                'input' => 'attachmentthumbs',
                'default' => 20,
                'function' => 'acp_rebuild_attachment_thumbnails'
            ],
			
			'do_rebuildcommentattachmentthumbs' => [
    'hook'     => 'admin_tools_recount_rebuild_comment_attachment_thumbs',
    'input'    => 'commentattachmentthumbs',
    'default'  => 20,
    'function' => 'acp_rebuild_comment_attachment_thumbnails'
],
			
            'do_recountprivatemessages' => [
                'hook' => "admin_tools_recount_recount_private_messages",
                'input' => 'privatemessages',
                'default' => 500,
                'function' => 'acp_recount_private_messages'
            ],
            'do_comments' => [
                'hook' => "admin_tools_recount_recount_comments",
                'input' => 'comments',
                'default' => 500,
                'function' => 'acp_recount_user_comments'
            ],
            'do_rebuildpollcounters' => [
                'hook' => "admin_tools_recount_rebuild_poll_counters",
                'input' => 'pollcounters',
                'default' => 500,
                'function' => 'acp_rebuild_poll_counters'
            ],
            'do_recountthreadratings' => [
                'hook' => "admin_tools_recount_rebuild_thread_ratings",
                'input' => 'threadratings',
                'default' => 500,
                'function' => 'acp_recount_thread_ratings'
            ],
            'do_recounttorrentcomments' => [
                'hook' => "admin_tools_recount_rebuild_torrent_comments",
                'input' => 'torrentcomments',
                'default' => 500,
                'function' => 'acp_recount_torrent_comments'
            ]
        ];

        foreach ($actions as $action => $config) {
            if (isset($mybb->input[$action])) {
                $plugins->run_hooks($config['hook']);

                if ($mybb->input['page'] == 1) {
                    // Log admin action if needed
                }

                $per_page = $mybb->get_input($config['input'], MyBB::INPUT_INT);
                if (!$per_page || $per_page <= 0) {
                    $mybb->input[$config['input']] = $config['default'];
                }

                $config['function']();
                break;
            }
        }

        // Handle stats recount
        if (isset($mybb->input['do_recountstats'])) {
            $plugins->run_hooks("admin_tools_recount_rebuild_stats");
            $cache->update_stats();
            
            // Log admin action
            write_log('User ' . $CURUSER['username'] . ' Recounted and rebuilt statistics');
            
            flash_message('The forum statistics have been rebuilt successfully', 'success');
            admin_redirect("index.php?act=recount_rebuild");
        }
    }

    stdhead();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recount & Rebuild</title>
   
   
 <style>
       
        .card {
            border-radius: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1) !important;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .card-text {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            border-radius: 6px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #3a56c4;
            border-color: #3a56c4;
        }
        .form-control {
            border-radius: 6px;
            border: 1px solid #d1d3e2;
        }
        .page-header {
            color: #4e73df;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        .description {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
    </style>
   
   
</head>
<body>
<div class="container mt-3">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Recount & Rebuild</h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-4 border-bottom bg-light">
                        <p class="text-muted mb-0">Use these tools to recount and rebuild various aspects of your forum. For large forums, this may take some time.</p>
                    </div>
                    
                    <form action="<?php echo $_this_script_; ?>" method="post">
                        <input type="hidden" name="my_post_key" value="<?php echo $mybb->post_code; ?>" />
                        
                        <div class="row p-4">
                            <!-- Rebuild Forum Counters -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-folder-tree me-2"></i>Rebuild Forum Counters
                                        </h5>
                                        <p class="card-text text-muted small">When this is run, the post/thread counters and last post of each forum will be updated to reflect the correct values.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="forumcounters" value="50" min="1">
                                            <button type="submit" name="do_rebuildforumcounters" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Rebuild Thread Counters -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-comments me-2"></i>Rebuild Thread Counters
                                        </h5>
                                        <p class="card-text text-muted small">When this is run, the post/view counters and last post of each thread will be updated to reflect the correct values.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="threadcounters" value="500" min="1">
                                            <button type="submit" name="do_rebuildthreadcounters" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Rebuild Poll Counters -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-chart-pie me-2"></i>Rebuild Poll Counters
                                        </h5>
                                        <p class="card-text text-muted small">When this is run, the vote counters and total number of votes of each poll will be updated to reflect the correct values.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="pollcounters" value="500" min="1">
                                            <button type="submit" name="do_rebuildpollcounters" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recount User Post Counts -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-file-alt me-2"></i>Recount User Post Counts
                                        </h5>
                                        <p class="card-text text-muted small">When this is run, the post count for each user will be updated to reflect its current live value based on the posts in the database.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="userposts" value="500" min="1">
                                            <button type="submit" name="do_recountuserposts" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recount User Thread Counts -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-clone me-2"></i>Recount User Thread Counts
                                        </h5>
                                        <p class="card-text text-muted small">When this is run, the thread count for each user will be updated to reflect its current live value based on the threads in the database.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="userthreads" value="500" min="1">
                                            <button type="submit" name="do_recountuserthreads" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Rebuild Attachment Thumbnails -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-image me-2"></i>Rebuild Attachment Thumbnails
                                        </h5>
                                        <p class="card-text text-muted small">This will rebuild attachment thumbnails to ensure they're using the current width and height dimensions.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="attachmentthumbs" value="20" min="1">
                                            <button type="submit" name="do_rebuildattachmentthumbs" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							<!-- Rebuild Comment Attachment Thumbnails -->
<div class="col-md-6 mb-4">
    <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
            <h5 class="card-title text-primary">
                <i class="fas fa-comments me-2"></i>Rebuild Comment Attachment Thumbnails
            </h5>
            <p class="card-text text-muted small">This will rebuild comment attachment thumbnails to ensure they're using the current width and height dimensions.</p>
            <div class="d-flex align-items-center mt-3">
                <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="commentattachmentthumbs" value="20" min="1">
                <button type="submit" name="do_rebuildcommentattachmentthumbs" class="btn btn-primary btn-sm">Run</button>
            </div>
        </div>
    </div>
</div>
							
							
							
							
							
                            
                            <!-- Recount Statistics -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-chart-bar me-2"></i>Recount Statistics
                                        </h5>
                                        <p class="card-text text-muted small">This will recount and update your forum statistics on the forum index and statistics pages.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <span class="text-muted small me-2">N/A</span>
                                            <button type="submit" name="do_recountstats" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recount Private Messages -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-envelope me-2"></i>Recount Private Messages
                                        </h5>
                                        <p class="card-text text-muted small">This will recount the private message count for each user.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="privatemessages" value="500" min="1">
                                            <button type="submit" name="do_recountprivatemessages" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recount User Comments -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-comment me-2"></i>Recount User Comments
                                        </h5>
                                        <p class="card-text text-muted small">This will recount the comments count for each user.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="comments" value="500" min="1">
                                            <button type="submit" name="do_comments" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recount Thread Ratings -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-star me-2"></i>Recount Thread Ratings
                                        </h5>
                                        <p class="card-text text-muted small">This will recalculate the average rating and vote count cached on each thread from the actual threadratings records.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="threadratings" value="500" min="1">
                                            <button type="submit" name="do_recountthreadratings" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recount Torrent Comment Counts -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-magnet me-2"></i>Recount Torrent Comment Counts
                                        </h5>
                                        <p class="card-text text-muted small">This will recalculate the comment count cached on each torrent from the actual comments in the database.</p>
                                        <div class="d-flex align-items-center mt-3">
                                            <input type="number" class="form-control form-control-sm me-2" style="width: 100px;" name="torrentcomments" value="500" min="1">
                                            <button type="submit" name="do_recounttorrentcomments" class="btn btn-primary btn-sm">Run</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


</body>
</html>
<?php
    stdfoot();
}