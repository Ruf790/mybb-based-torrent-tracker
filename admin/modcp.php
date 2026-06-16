<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}


define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);
//require_once INC_PATH . '/tsf_functions.php';

//require_once INC_PATH . "/functions_modcp.php";
require_once INC_PATH . '/editor.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/class_parser.php';

$parser = new postParser;

if($CURUSER['id'] == 0 || $usergroups['canstaffpanel'] != 1) {
    print_no_permission();
}

if(!$f_threadsperpage || (int)$f_threadsperpage < 1) {
    $f_threadsperpage = 20;
}

$lang->load("modcp");

/* ═══════════════════════════════════════════════════════════════════
 *  ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
 * ═══════════════════════════════════════════════════════════════════ */

function render_header(string $title): void {
    global $SITENAME, $mybb;
    
    
	stdhead('ffffffff');
	
	echo '<!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($SITENAME) . ' - ' . htmlspecialchars($title) . '</title>
        
        <style>
            .radio-toolbar {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                flex-wrap: wrap;
            }
            .radio-toolbar input[type="radio"] {
                display: none;
            }
            .radio-toolbar label {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                cursor: pointer;
                transition: all 0.2s ease;
                font-size: 13px;
            }
            .radio-toolbar .radio_ignore + label {
                background: #6c757d;
                color: white;
            }
            .radio-toolbar .radio_delete + label {
                background: #dc3545;
                color: white;
            }
            .radio-toolbar .radio_approve + label {
                background: #198754;
                color: white;
            }
            .radio-toolbar input[type="radio"]:checked + label {
                transform: scale(1.05);
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            .text-desc {
                color: #6c757d;
                font-size: 0.9em;
            }
            .card-header.bg-primary {
                background-color: #0d6efd !important;
            }
            .btn-primary {
                background-color: #0d6efd;
                border-color: #0d6efd;
            }
            .mass-controls {
                margin-bottom: 15px;
            }
            .mass-controls .btn {
                margin-right: 8px;
            }
        </style>
    </head>
    <body>
        <div class="container mt-3">';
}

function render_footer(): void {
    echo '</div>
    </body>
    </html>
	'.stdfoot().'
	';
}

function render_mass_controls(): string {
    $lang = $GLOBALS['lang'];
    return '
    <div class="mass-controls">
        <a href="#" class="mass_ignore btn btn-sm btn-secondary">
            <i class="fas fa-eye-slash me-1"></i> ' . ($lang->modcp['ignore_all'] ?? 'Mark all ignored') . '
        </a>
        <a href="#" class="mass_delete btn btn-sm btn-danger">
            <i class="fas fa-trash-alt me-1"></i> ' . ($lang->modcp['delete_all'] ?? 'Mark all delete') . '
        </a>
        <a href="#" class="mass_approve btn btn-sm btn-success">
            <i class="fas fa-check me-1"></i> ' . ($lang->modcp['approve_all'] ?? 'Mark all approve') . '
        </a>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var massIgnore = document.querySelectorAll(".mass_ignore");
        var massDelete = document.querySelectorAll(".mass_delete");
        var massApprove = document.querySelectorAll(".mass_approve");
        
        for(var i = 0; i < massIgnore.length; i++) {
            massIgnore[i].addEventListener("click", function(e) {
                e.preventDefault();
                var radios = document.querySelectorAll("input.radio_ignore");
                for(var j = 0; j < radios.length; j++) {
                    radios[j].checked = true;
                }
            });
        }
        
        for(var i = 0; i < massDelete.length; i++) {
            massDelete[i].addEventListener("click", function(e) {
                e.preventDefault();
                var radios = document.querySelectorAll("input.radio_delete");
                for(var j = 0; j < radios.length; j++) {
                    radios[j].checked = true;
                }
            });
        }
        
        for(var i = 0; i < massApprove.length; i++) {
            massApprove[i].addEventListener("click", function(e) {
                e.preventDefault();
                var radios = document.querySelectorAll("input.radio_approve");
                for(var j = 0; j < radios.length; j++) {
                    radios[j].checked = true;
                }
            });
        }
    });
    </script>';
}

/* ═══════════════════════════════════════════════════════════════════
 *  ОБРАБОТЧИКИ ДЕЙСТВИЙ
 * ═══════════════════════════════════════════════════════════════════ */

function handle_do_modqueue(): void {
    global $db, $mybb, $lang, $plugins, $_this_script;
    global $flist_queue_threads, $flist_queue_posts, $tflist_queue_attach;

    require_once INC_PATH . '/class_moderation.php';
    $moderation = new Moderation();

    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks("modcp_do_modqueue_start");

    $threads = $mybb->get_input('threads', MyBB::INPUT_ARRAY);
    $posts = $mybb->get_input('posts', MyBB::INPUT_ARRAY);
    $attachments = $mybb->get_input('attachments', MyBB::INPUT_ARRAY);
    
    if (!empty($threads)) {
        $tids = array_map("intval", array_keys($threads));
        $threads_to_approve = $threads_to_delete = [];
        
        $query = $db->simple_select("threads", "tid", "tid IN (" . implode(",", $tids) . "){$flist_queue_threads}");
        while($thread = $db->fetch_array($query)) {
            if(!isset($threads[$thread['tid']])) continue;
            $action = $threads[$thread['tid']];
            if($action == "approve") $threads_to_approve[] = $thread['tid'];
            elseif($action == "delete") $threads_to_delete[] = $thread['tid'];
        }
        
        if(!empty($threads_to_approve)) {
            $moderation->approve_threads($threads_to_approve);
            log_moderator_action(['tids' => $threads_to_approve], $lang->modcp['multi_approve_threads']);
        }
        
        if(!empty($threads_to_delete)) {
            if($mybb->settings['soft_delete'] == 1) {
                $moderation->soft_delete_threads($threads_to_delete);
                log_moderator_action(['tids' => $threads_to_delete], $lang->multi_soft_delete_threads);
            } else {
                foreach($threads_to_delete as $tid) $moderation->delete_thread($tid);
                log_moderator_action(['tids' => $threads_to_delete], $lang->multi_delete_threads);
            }
        }
        $plugins->run_hooks("modcp_do_modqueue_end");
		redirect('admin/index.php?act=modcp', $lang->modcp['redirect_threadsmoderated']);
    }
    
    if (!empty($posts)) {
        $pids = array_map("intval", array_keys($posts));
        $posts_to_approve = $posts_to_delete = [];
        
        $query = $db->simple_select("posts", "pid", "pid IN (" . implode(",", $pids) . "){$flist_queue_posts}");
        while($post = $db->fetch_array($query)) {
            if(!isset($posts[$post['pid']])) continue;
            $action = $posts[$post['pid']];
            if($action == "approve") $posts_to_approve[] = $post['pid'];
            elseif($action == "delete" && $mybb->settings['soft_delete'] != 1) $moderation->delete_post($post['pid']);
            elseif($action == "delete") $posts_to_delete[] = $post['pid'];
        }
        
        if(!empty($posts_to_approve)) {
            $moderation->approve_posts($posts_to_approve);
            log_moderator_action(['pids' => $posts_to_approve], $lang->modcp['multi_approve_posts']);
        }
        if(!empty($posts_to_delete) && $mybb->settings['soft_delete'] == 1) {
            $moderation->soft_delete_posts($posts_to_delete);
            log_moderator_action(['pids' => $posts_to_delete], $lang->multi_soft_delete_posts);
        }
        $plugins->run_hooks("modcp_do_modqueue_end");
        redirect('admin/index.php?act=modcp&type=posts', $lang->modcp['redirect_postsmoderated']);
    }
    
    if (!empty($attachments)) {
        $aids = array_map("intval", array_keys($attachments));
        $query = $db->sql_query("
            SELECT a.pid, a.aid, t.tid
            FROM attachments a
            LEFT JOIN posts p ON (a.pid = p.pid)
            LEFT JOIN threads t ON (t.tid = p.tid)
            WHERE aid IN (" . implode(",", $aids) . "){$tflist_queue_attach}
        ");
        while($attachment = $db->fetch_array($query)) {
            if(!isset($attachments[$attachment['aid']])) continue;
            $action = $attachments[$attachment['aid']];
            if($action == "approve") {
                $db->update_query("attachments", ["visible" => 1], "aid='{$attachment['aid']}'");
                if(isset($attachment['tid'])) update_thread_counters((int)$attachment['tid'], ["attachmentcount" => "+1"]);
            } elseif($action == "delete") {
                remove_attachment($attachment['pid'], '', $attachment['aid']);
                if(isset($attachment['tid'])) update_thread_counters((int)$attachment['tid'], ["attachmentcount" => "-1"]);
            }
        }
        $plugins->run_hooks("modcp_do_modqueue_end");
        redirect('admin/index.php?act=modcp&type=attachments', $lang->modcp['redirect_attachmentsmoderated']);
    }
}

function handle_modqueue(): void {
    global $db, $_this_script_, $mybb, $lang, $plugins, $templates, $parser, $cache, $usergroups;
    global $flist_queue_threads, $tflist_queue_threads, $flist_queue_posts, $tflist_queue_posts;
    global $tflist_queue_attach, $f_threadsperpage, $f_postsperpage;
    global $nummodqueuethreads, $nummodqueueposts, $nummodqueueattach, $enableattachments;

    $type = $mybb->get_input('type');
    $forum_cache = $cache->read("forums");
    
    // Threads queue
    if($type == "threads" || (!$type && ($nummodqueuethreads > 0 || $usergroups['issupermod'] == "1"))) {
        $query = $db->simple_select("threads", "COUNT(tid) AS cnt", "visible='0' {$flist_queue_threads}");
        $total = (int)$db->fetch_field($query, "cnt");
        
        $page = max(1, (int)$mybb->get_input('page', MyBB::INPUT_INT));
        $perpage = (int)$f_threadsperpage;
        $start = ($page - 1) * $perpage;
        $multipage = multipage($total, $perpage, $page, "modcp.php?type=threads");
        
        $query = $db->sql_query("
            SELECT t.tid, t.dateline, t.fid, t.subject, t.username AS threadusername, 
                   p.message AS postmessage, u.username, t.uid
            FROM threads t
            LEFT JOIN posts p ON (p.pid = t.firstpost)
            LEFT JOIN users u ON (u.id = t.uid)
            WHERE t.visible='0' {$tflist_queue_threads}
            ORDER BY t.lastpost DESC
            LIMIT {$start}, {$perpage}
        ");
        
        $threads_html = '';
        while($thread = $db->fetch_array($query)) {
            $forum_name = $forum_cache[$thread['fid']]['name'] ?? '';
            $thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
            $thread['postmessage'] = nl2br(htmlspecialchars_uni($thread['postmessage']));
            $profile_link = $thread['username'] ? build_profile_link(htmlspecialchars_uni($thread['username']), $thread['uid']) : 
                           ($thread['threadusername'] ? htmlspecialchars_uni($thread['threadusername']) : 'guest');
            
            $threads_html .= '
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <a href="' . get_thread_link($thread['tid']) . '" class="fw-bold">' . $thread['subject'] . '</a>
                            <div class="text-desc small">' . $lang->modcp['by'] . ' ' . $profile_link . ', ' . my_datee('relative', $thread['dateline']) . '</div>
                        </div>
                        <div class="col-md-4">
                            <strong>' . $lang->modcp['forum'] . ':</strong> <a href="' . get_forum_link($thread['fid']) . '">' . $forum_name . '</a>
                        </div>
                        <div class="col-md-3 text-md-end">
                            <div class="radio-toolbar">
                                <input type="radio" class="radio_ignore" name="threads[' . $thread['tid'] . ']" id="ignore_t' . $thread['tid'] . '" value="ignore" checked>
                                <label for="ignore_t' . $thread['tid'] . '">' . $lang->modcp['ignore'] . '</label>
                                <input type="radio" class="radio_delete" name="threads[' . $thread['tid'] . ']" id="delete_t' . $thread['tid'] . '" value="delete">
                                <label for="delete_t' . $thread['tid'] . '">' . $lang->modcp['delete'] . '</label>
                                <input type="radio" class="radio_approve" name="threads[' . $thread['tid'] . ']" id="approve_t' . $thread['tid'] . '" value="approve">
                                <label for="approve_t' . $thread['tid'] . '">' . $lang->modcp['approve'] . '</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top" style="max-height: 200px; overflow-y: auto;">
                        ' . $thread['postmessage'] . '
                    </div>
                </div>
            </div>';
        }
        
        if(!$threads_html) $threads_html = '<div class="text-center py-5">
    <i class="bi bi-inbox fs-1 text-muted mb-3 d-block"></i>
    <h5 class="text-muted">' . $lang->modcp['mod_queue_threads_empty'] . '</h5>
</div>';
        
        render_header($lang->modcp['threads_awaiting_moderation']);
        echo '
<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">' . $lang->modcp['threads_awaiting_moderation'] . '</h4>
    </div>
    <form action="'.$_this_script_.'" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
        <input type="hidden" name="action" value="do_modqueue">
        <div class="card-body">
            <div class="mb-3">
                <a href="'.$_this_script_.'&type=threads" class="btn btn-primary btn-sm">' . $lang->modcp['threads'] . '</a>
                <a href="'.$_this_script_.'&type=posts" class="btn btn-outline-secondary btn-sm">' . $lang->modcp['posts'] . '</a>
                <a href="'.$_this_script_.'&type=attachments" class="btn btn-outline-secondary btn-sm">' . $lang->modcp['attachments'] . '</a>
            </div>
            ' . $threads_html . '
        </div>
        <div class="card-footer text-center bg-white">
            ' . render_mass_controls() . '
            <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> ' . $lang->modcp['perform_actions'] . '</button>
        </div>
    </form>
</div>
' . $multipage;
        render_footer();
        return;
    }
    
    // Posts queue
    if($type == "posts" || (!$type && ($nummodqueueposts > 0 || $usergroups['issupermod'] == "1"))) {
        $query = $db->sql_query("
            SELECT COUNT(pid) AS cnt
            FROM posts p
            LEFT JOIN threads t ON (t.tid = p.tid)
            WHERE p.visible='0' {$tflist_queue_posts} AND t.firstpost != p.pid
        ");
        $total = (int)$db->fetch_field($query, "cnt");
        
        $page = max(1, (int)$mybb->get_input('page', MyBB::INPUT_INT));
        $perpage = (int)$f_postsperpage;
        $start = ($page - 1) * $perpage;
        $multipage = multipage($total, $perpage, $page, "modcp.php?type=posts");
        
        $query = $db->sql_query("
            SELECT p.pid, p.subject, p.message, p.username AS postusername, 
                   t.subject AS threadsubject, t.tid, u.username, p.uid, t.fid, p.dateline
            FROM posts p
            LEFT JOIN threads t ON (t.tid = p.tid)
            LEFT JOIN users u ON (u.id = p.uid)
            WHERE p.visible='0' {$tflist_queue_posts} AND t.firstpost != p.pid
            ORDER BY p.dateline DESC
            LIMIT {$start}, {$perpage}
        ");
        
        $posts_html = '';
        while($post = $db->fetch_array($query)) {
            $forum_name = $forum_cache[$post['fid']]['name'] ?? '';
            $post['subject'] = htmlspecialchars_uni($parser->parse_badwords($post['subject']));
            $post['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($post['threadsubject']));
            $post['message'] = nl2br(htmlspecialchars_uni($post['message']));
            $profile_link = $post['username'] ? build_profile_link(htmlspecialchars_uni($post['username']), $post['uid']) : 
                           ($post['postusername'] ? htmlspecialchars_uni($post['postusername']) : $lang->guest);
            
            $posts_html .= '
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <a href="' . get_post_link($post['pid'], $post['tid']) . '#pid' . $post['pid'] . '" class="fw-bold">' . $post['subject'] . '</a>
                            <div class="text-desc small">' . $lang->modcp['by'] . ' ' . $profile_link . ', ' . my_datee('relative', $post['dateline']) . '</div>
                        </div>
                        <div class="col-md-4">
                            <strong>' . $lang->modcp['thread'] . ':</strong> <a href="' . get_thread_link($post['tid']) . '">' . $post['threadsubject'] . '</a><br>
                            <strong>' . $lang->modcp['forum'] . ':</strong> <a href="' . get_forum_link($post['fid']) . '">' . $forum_name . '</a>
                        </div>
                        <div class="col-md-3 text-md-end">
                            <div class="radio-toolbar">
                                <input type="radio" class="radio_ignore" name="posts[' . $post['pid'] . ']" id="ignore_p' . $post['pid'] . '" value="ignore" checked>
                                <label for="ignore_p' . $post['pid'] . '">' . $lang->modcp['ignore'] . '</label>
                                <input type="radio" class="radio_delete" name="posts[' . $post['pid'] . ']" id="delete_p' . $post['pid'] . '" value="delete">
                                <label for="delete_p' . $post['pid'] . '">' . $lang->modcp['delete'] . '</label>
                                <input type="radio" class="radio_approve" name="posts[' . $post['pid'] . ']" id="approve_p' . $post['pid'] . '" value="approve">
                                <label for="approve_p' . $post['pid'] . '">' . $lang->modcp['approve'] . '</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top" style="max-height: 200px; overflow-y: auto;">
                        ' . $post['message'] . '
                    </div>
                </div>
            </div>';
        }
        
        if(!$posts_html) $posts_html = '<div class="text-center py-5">
    <i class="bi bi-inbox fs-1 text-muted mb-3 d-block"></i>
    <h5 class="text-muted">' . $lang->modcp['mod_queue_posts_empty'] . '</h5>
</div>';
        
        render_header($lang->modcp['posts_awaiting_moderation']);
        echo '
<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">' . $lang->modcp['posts_awaiting_moderation'] . '</h4>
    </div>
    <form action="'.$_this_script_.'" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
        <input type="hidden" name="action" value="do_modqueue">
        <div class="card-body">
            <div class="mb-3">
                <a href="'.$_this_script_.'&type=threads" class="btn btn-outline-secondary btn-sm">' . $lang->modcp['threads'] . '</a>
                <a href="'.$_this_script_.'&type=posts" class="btn btn-primary btn-sm">' . $lang->modcp['posts'] . '</a>
                <a href="'.$_this_script_.'&type=attachments" class="btn btn-outline-secondary btn-sm">' . $lang->modcp['attachments'] . '</a>
            </div>
            ' . $posts_html . '
        </div>
        <div class="card-footer text-center bg-white">
            ' . render_mass_controls() . '
            <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> ' . $lang->modcp['perform_actions'] . '</button>
        </div>
    </form>
</div>
' . $multipage;
        render_footer();
        return;
    }
    
    // Attachments queue
    if(($type == "attachments" || !$type) && $enableattachments == 1) {
        $query = $db->sql_query("
            SELECT COUNT(aid) AS cnt
            FROM attachments a
            LEFT JOIN posts p ON (p.pid = a.pid)
            LEFT JOIN threads t ON (t.tid = p.tid)
            WHERE a.visible='0'{$tflist_queue_attach}
        ");
        $total = (int)$db->fetch_field($query, "cnt");
        
        $page = max(1, (int)$mybb->get_input('page', MyBB::INPUT_INT));
        $perpage = (int)$f_postsperpage;
        $start = ($page - 1) * $perpage;
        $multipage = multipage($total, $perpage, $page, "'.$_this_script_.'&type=attachments");
        
        $query = $db->sql_query("
            SELECT a.*, p.subject AS postsubject, p.dateline, p.uid, u.username, t.tid, t.subject AS threadsubject
            FROM attachments a
            LEFT JOIN posts p ON (p.pid = a.pid)
            LEFT JOIN threads t ON (t.tid = p.tid)
            LEFT JOIN users u ON (u.id = p.uid)
            WHERE a.visible='0'{$tflist_queue_attach}
            ORDER BY a.dateuploaded DESC
            LIMIT {$start}, {$perpage}
        ");
        
        $attachments_html = '';
        while($att = $db->fetch_array($query)) {
            $att['filename'] = htmlspecialchars_uni($att['filename']);
            $att['postsubject'] = htmlspecialchars_uni($parser->parse_badwords($att['postsubject']));
            $attachments_html .= '
            <div class="card mb-2">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <i class="fas fa-paperclip me-1"></i> <a href="attachment.php?aid=' . $att['aid'] . '" target="_blank">' . $att['filename'] . '</a>
                            <span class="text-desc">(' . mksize($att['filesize']) . ')</span>
                        </div>
                        <div class="col-md-5">
                            <a href="' . get_post_link($att['pid'], $att['tid']) . '#pid' . $att['pid'] . '">' . $att['postsubject'] . '</a>
                            <div class="text-desc small">' . my_datee('relative', $att['dateuploaded'] ?: $att['dateline']) . '</div>
                        </div>
                        <div class="col-md-3 text-md-end">
                            <div class="radio-toolbar">
                                <input type="radio" class="radio_ignore" name="attachments[' . $att['aid'] . ']" id="ignore_a' . $att['aid'] . '" value="ignore" checked>
                                <label for="ignore_a' . $att['aid'] . '">' . $lang->modcp['ignore'] . '</label>
                                <input type="radio" class="radio_delete" name="attachments[' . $att['aid'] . ']" id="delete_a' . $att['aid'] . '" value="delete">
                                <label for="delete_a' . $att['aid'] . '">' . $lang->modcp['delete'] . '</label>
                                <input type="radio" class="radio_approve" name="attachments[' . $att['aid'] . ']" id="approve_a' . $att['aid'] . '" value="approve">
                                <label for="approve_a' . $att['aid'] . '">' . $lang->modcp['approve'] . '</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }
        
        if(!$attachments_html) $attachments_html = '<div class="text-center py-5">
    <i class="bi bi-paperclip fs-1 text-muted mb-3 d-block"></i>
    <h5 class="text-muted">' . $lang->modcp['mod_queue_attachments_empty'] . '</h5>
</div>';
        
        render_header($lang->modcp['attachments_awaiting_moderation']);
        echo '
<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">' . $lang->modcp['attachments_awaiting_moderation'] . '</h4>
    </div>
    <form action="'.$_this_script_.'" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
        <input type="hidden" name="action" value="do_modqueue">
        <div class="card-body">
            <div class="mb-3">
                <a href="'.$_this_script_.'&type=threads" class="btn btn-outline-secondary btn-sm">' . $lang->modcp['threads'] . '</a>
                <a href="'.$_this_script_.'&type=posts" class="btn btn-outline-secondary btn-sm">' . $lang->modcp['posts'] . '</a>
                <a href="'.$_this_script_.'&type=attachments" class="btn btn-primary btn-sm">' . $lang->modcp['attachments'] . '</a>
            </div>
            ' . $attachments_html . '
        </div>
        <div class="card-footer text-center bg-white">
            ' . render_mass_controls() . '
            <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> ' . $lang->modcp['perform_actions'] . '</button>
        </div>
    </form>
</div>
' . $multipage;
        render_footer();
        return;
    }
    
    // Empty queue
    render_header($lang->modcp['mod_queue']);
    echo '
<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">' . $lang->modcp['mod_queue'] . '</h4>
    </div>
    <div class="card-body text-center py-5">
        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
        <h5>' . $lang->modcp['mod_queue_empty'] . '</h5>
        <p class="text-desc">' . ($lang->modcp['mod_queue_empty_desc'] ?? 'No items awaiting moderation.') . '</p>
    </div>
</div>';
    render_footer();
}

/* ═══════════════════════════════════════════════════════════════════
 *  ОСНОВНАЯ ЛОГИКА
 * ═══════════════════════════════════════════════════════════════════ */

$action = $mybb->get_input('action', MyBB::INPUT_STRING);
$plugins->run_hooks('modcp_start');

if($mybb->request_method == 'post' && $action == "do_modqueue") {
    handle_do_modqueue();
} elseif(empty($action) || $action == "modqueue") {
    handle_modqueue();
} else {
    add_breadcrumb($lang->modcp['mcp_nav_home'], "modcp.php");
    $plugins->run_hooks('modcp_home');
    
    render_header($lang->modcp['modcp']);
    
    if($templates->get("modcp")) {
        eval("\$modcp = \"" . $templates->get("modcp") . "\";");
        //echo $modcp;
    } else {
        echo '
<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">' . $lang->modcp['modcp'] . '</h4>
    </div>
    <div class="card-body">
        <p>' . ($lang->modcp['welcome_modcp'] ?? 'Welcome to Moderator Control Panel') . '</p>
        <hr>
        <div class="row">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-tasks fa-2x text-primary mb-2"></i>
                        <h5>' . $lang->modcp['mod_queue'] . '</h5>
                        <a href="modcp.php" class="btn btn-sm btn-primary">' . ($lang->modcp['view'] ?? 'View') . '</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';
    }
    
    render_footer();
}