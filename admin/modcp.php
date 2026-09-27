<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}




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
 
 
// ── update_thread_counters ────────────────────────────────────────────────────
function update_thread_counters(int $tid, array $changes = []): void
{
    global $db;

    $counters = ['replies', 'unapprovedposts', 'attachmentcount'];
    $query    = $db->sql_query_prepared("SELECT " . implode(',', $counters) . " FROM threads WHERE tid = ?", [$tid]);
    $thread   = $query ? $db->fetch_array($query) : null;
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
            ? $thread[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
        $params   = array_values($update);
        $params[] = $tid;
        $db->sql_query_prepared("UPDATE threads SET {$set} WHERE tid = ?", $params);
    }
} 
 
 
 
 

/* ═══════════════════════════════════════════════════════════════════
 *  ОФОРМЛЕНИЕ
 * ═══════════════════════════════════════════════════════════════════ */

// Раньше: stdhead('ffffffff') (вкладка браузера называлась «ffffffff») и второй
// <!DOCTYPE html><html><head><body> внутри уже открытой страницы; стили меняли
// .btn-primary и .card-header.bg-primary на всей странице.
function render_header(string $title): void
{
    stdhead($title);
    echo <<<'HTML'
<style>
.mc .mc-card { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color-translucent); border-radius: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.mc .mc-head { display: flex; flex-wrap: wrap; align-items: center; gap: .9rem; padding: 1.1rem 1.25rem; }
.mc .mc-head-icon, .mc .mc-item-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; border-radius: .85rem; }
.mc .mc-head-icon { width: 48px; height: 48px; font-size: 1.35rem; }
.mc .mc-title { font-size: 1.4rem; font-weight: 700; margin: 0; }
.mc .mc-sub { color: var(--bs-secondary-color); font-size: .95rem; }
.mc .mc-muted { font-size: .88rem; color: var(--bs-secondary-color); }
.mc .ic-blue   { color: var(--bs-primary); background: rgba(var(--bs-primary-rgb),.12); }
.mc .ic-green  { color: #16a34a; background: rgba(34,197,94,.12); }
.mc .ic-amber  { color: #d97706; background: rgba(245,158,11,.14); }
.mc .ic-purple { color: #7c3aed; background: rgba(124,58,237,.12); }
.mc .ic-teal   { color: #0891b2; background: rgba(8,145,178,.12); }

.mc .mc-tabs { display: flex; gap: .3rem; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; padding: .3rem; border-radius: 50rem; background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color-translucent); width: max-content; max-width: 100%; margin-bottom: 1rem; }
.mc .mc-tabs::-webkit-scrollbar { display: none; }
.mc .mc-tabs a { display: inline-flex; align-items: center; gap: .45rem; padding: .5rem 1rem; border-radius: 50rem; font-weight: 600; color: var(--bs-secondary-color); text-decoration: none; white-space: nowrap; }
.mc .mc-tabs a:hover { color: var(--bs-body-color); background: var(--bs-body-bg); }
.mc .mc-tabs a.active { color: #fff; background: var(--bs-primary); box-shadow: 0 .25rem .75rem rgba(var(--bs-primary-rgb), .3); }
.mc .mc-count { font-size: .72rem; font-weight: 700; padding: .05rem .45rem; border-radius: 50rem; background: var(--bs-secondary-bg); color: var(--bs-secondary-color); }
.mc .mc-tabs a.active .mc-count { background: rgba(255,255,255,.25); color: #fff; }
.mc .mc-count.has { background: #ef4444; color: #fff; }

.mc .mc-item { padding: 1rem 1.15rem; border-bottom: 1px solid var(--bs-border-color-translucent); transition: background-color .15s ease; }
.mc .mc-item:last-child { border-bottom: 0; }
.mc .mc-item:has(.radio_approve:checked) { background: rgba(34,197,94,.05); box-shadow: inset 3px 0 0 #16a34a; }
.mc .mc-item:has(.radio_delete:checked)  { background: rgba(239,68,68,.05); box-shadow: inset 3px 0 0 #dc2626; }
.mc .mc-item-top { display: flex; flex-wrap: wrap; align-items: flex-start; gap: .85rem; }
.mc .mc-item-icon { width: 40px; height: 40px; font-size: 1rem; border-radius: .75rem; }
.mc .mc-item-main { flex: 1 1 320px; min-width: 0; }
.mc .mc-item-title { font-weight: 700; font-size: 1.05rem; text-decoration: none; overflow-wrap: anywhere; }
.mc .mc-chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .35rem; }
.mc .mc-chip { display: inline-flex; align-items: center; gap: .3rem; padding: .1rem .55rem; border-radius: 50rem; font-size: .8rem; background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color-translucent); color: var(--bs-body-color); text-decoration: none; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mc a.mc-chip:hover { border-color: rgba(var(--bs-primary-rgb), .45); color: var(--bs-primary); }
.mc .mc-chip i { color: var(--bs-secondary-color); font-size: .85em; }
.mc .mc-body { margin: .75rem 0 0 calc(40px + .85rem); padding: .7rem .9rem; border-radius: .75rem; background: var(--bs-tertiary-bg); max-height: 200px; overflow-y: auto; overflow-wrap: anywhere; font-size: .95rem; }
@media (max-width: 575.98px) { .mc .mc-body { margin-left: 0; } }

/* Выбор действия — сегментированный переключатель (radio остаются radio: их читает обработчик) */
.mc .mc-seg { display: inline-flex; padding: .2rem; border-radius: 50rem; background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color-translucent); flex-shrink: 0; }
.mc .mc-seg input { position: absolute; opacity: 0; pointer-events: none; }
.mc .mc-seg label { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .8rem; border-radius: 50rem; font-size: .85rem; font-weight: 600; color: var(--bs-secondary-color); cursor: pointer; white-space: nowrap; transition: background-color .15s ease, color .15s ease; }
.mc .mc-seg label:hover { color: var(--bs-body-color); }
.mc .mc-seg input:focus-visible + label { outline: 2px solid rgba(var(--bs-primary-rgb), .5); }
.mc .mc-seg .radio_ignore:checked + label  { background: var(--bs-body-bg); color: var(--bs-body-color); box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.mc .mc-seg .radio_delete:checked + label  { background: #dc2626; color: #fff; }
.mc .mc-seg .radio_approve:checked + label { background: #16a34a; color: #fff; }

.mc .mc-bar { position: sticky; bottom: .75rem; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem 1rem; margin-top: 1rem; box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08); }
.mc .mc-bar .btn { border-radius: 50rem; }
.mc .mc-summary { font-size: .9rem; color: var(--bs-secondary-color); }
.mc .mc-summary b { color: var(--bs-body-color); }
.mc .mc-empty { text-align: center; padding: 3.5rem 1rem; color: var(--bs-secondary-color); }
.mc .mc-empty > i { font-size: 2.8rem; display: block; margin-bottom: .75rem; }
</style>
<div class="container mt-3 mb-4 mc">
HTML;
}

// Раньше stdfoot() вызывалась ВНУТРИ конкатенации строки: функция печатает сразу,
// поэтому подвал сайта выводился раньше закрывающих тегов страницы
function render_footer(): void
{
    echo '</div>';
    stdfoot();
}

/** Кнопки «отметить всё» + итог выбранного. Классы mass_* и radio_* прежние. */
function render_mass_controls(): string
{
    $lang = $GLOBALS['lang'];
    return '
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="mc-muted me-1"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Mark all:</span>
        <a href="#" class="mass_ignore btn btn-sm btn-outline-secondary"><i class="fas fa-eye-slash me-1"></i>' . ($lang->modcp['ignore_all'] ?? 'Ignore') . '</a>
        <a href="#" class="mass_delete btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt me-1"></i>' . ($lang->modcp['delete_all'] ?? 'Delete') . '</a>
        <a href="#" class="mass_approve btn btn-sm btn-outline-success"><i class="fas fa-check me-1"></i>' . ($lang->modcp['approve_all'] ?? 'Approve') . '</a>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const form = document.querySelector(".mc form");
        function summary() {
            const el = document.getElementById("mcSummary");
            if (!el || !form) return;
            const a = form.querySelectorAll("input.radio_approve:checked").length;
            const d = form.querySelectorAll("input.radio_delete:checked").length;
            el.innerHTML = "<i class=\"fa-solid fa-circle-check text-success me-1\"></i><b>" + a + "</b> approve · "
                         + "<i class=\"fa-solid fa-trash text-danger ms-1 me-1\"></i><b>" + d + "</b> delete";
        }
        ["ignore", "delete", "approve"].forEach(function (kind) {
            document.querySelectorAll(".mass_" + kind).forEach(function (btn) {
                btn.addEventListener("click", function (e) {
                    e.preventDefault();
                    document.querySelectorAll("input.radio_" + kind).forEach(function (r) { r.checked = true; });
                    summary();
                });
            });
        });
        form && form.addEventListener("change", summary);
        // Подтверждение, если есть удаления
        form && form.addEventListener("submit", function (e) {
            const d = form.querySelectorAll("input.radio_delete:checked").length;
            if (d > 0 && !confirm("Delete " + d + " item(s)? This cannot be undone.")) e.preventDefault();
        });
        summary();
    });
    </script>';
}

/** Сегментированный выбор действия для одного элемента очереди */
function mc_actions(string $group, int $id, string $suffix): string
{
    $lang = $GLOBALS['lang'];
    $n = $group . '[' . $id . ']';
    $opt = static fn(string $v, string $cls, string $ic, string $lbl, bool $chk) =>
        '<input type="radio" class="' . $cls . '" name="' . $n . '" id="' . $v . '_' . $suffix . $id . '" value="' . $v . '"' . ($chk ? ' checked' : '') . '>'
      . '<label for="' . $v . '_' . $suffix . $id . '"><i class="fas ' . $ic . '"></i>' . $lbl . '</label>';
    return '<div class="mc-seg" role="radiogroup">'
         . $opt('ignore',  'radio_ignore',  'fa-eye-slash', $lang->modcp['ignore']  ?? 'Ignore',  true)
         . $opt('delete',  'radio_delete',  'fa-trash',     $lang->modcp['delete']  ?? 'Delete',  false)
         . $opt('approve', 'radio_approve', 'fa-check',     $lang->modcp['approve'] ?? 'Approve', false)
         . '</div>';
}

function mc_item(string $icon, string $cls, string $title_html, string $meta_html, string $chips_html, string $actions, string $body_html = ''): string
{
    return '<div class="mc-item"><div class="mc-item-top">'
         . '<span class="mc-item-icon ' . $cls . '"><i class="fas ' . $icon . '"></i></span>'
         . '<div class="mc-item-main">' . $title_html . '<div class="mc-muted">' . $meta_html . '</div>'
         . ($chips_html !== '' ? '<div class="mc-chips">' . $chips_html . '</div>' : '') . '</div>'
         . $actions . '</div>'
         . ($body_html !== '' ? '<div class="mc-body">' . $body_html . '</div>' : '')
         . '</div>';
}

/** Вкладки очереди с количеством элементов */
function mc_tabs(string $active, array $counts, bool $attach_enabled): string
{
    global $_this_script_, $lang;
    $tabs = [
        'threads'     => ['fa-list',      $lang->modcp['threads'] ?? 'Threads'],
        'posts'       => ['fa-comment',   $lang->modcp['posts'] ?? 'Posts'],
    ];
    if ($attach_enabled) {
        $tabs['attachments'] = ['fa-paperclip', $lang->modcp['attachments'] ?? 'Attachments'];
    }
    $html = '<nav class="mc-tabs">';
    foreach ($tabs as $key => [$ic, $label]) {
        $n = (int)($counts[$key] ?? 0);
        $html .= '<a href="' . $_this_script_ . '&amp;type=' . $key . '" class="' . ($key === $active ? 'active' : '') . '">'
               . '<i class="fas ' . $ic . '"></i>' . $label . '<span class="mc-count' . ($n > 0 ? ' has' : '') . '">' . $n . '</span></a>';
    }
    return $html . '</nav>';
}

function mc_hero(string $sub): string
{
    global $lang;
    return '<div class="mc-card mb-3"><div class="mc-head">'
         . '<span class="mc-head-icon ic-purple"><i class="fas fa-gavel"></i></span>'
         . '<div><h1 class="mc-title">' . ($lang->modcp['mod_queue'] ?? 'Moderation Queue') . '</h1><div class="mc-sub">' . $sub . '</div></div>'
         . '</div></div>';
}

/** Общая обёртка страницы очереди */
function mc_queue_page(string $type, string $heading, string $items_html, bool $has_items, string $multipage, array $counts, bool $attach_enabled): void
{
    global $_this_script_, $mybb, $lang;

    render_header($heading);
    echo mc_hero('Approve or remove content that is waiting for a moderator');
    echo mc_tabs($type, $counts, $attach_enabled);
    echo '<form action="' . $_this_script_ . '" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
        <input type="hidden" name="action" value="do_modqueue">
        <div class="mc-card overflow-hidden">' . $items_html . '</div>';
    if ($has_items) {
        echo '<div class="mc-card mc-bar">'
           . render_mass_controls()
           . '<div class="d-flex align-items-center gap-3"><span class="mc-summary" id="mcSummary"></span>'
           . '<button type="submit" class="btn btn-primary px-4"><i class="fas fa-check-double me-1"></i>' . ($lang->modcp['perform_actions'] ?? 'Perform actions') . '</button></div>'
           . '</div>';
    }
    echo '</form>';
    if ($multipage) {
        echo '<div class="d-flex justify-content-center mt-3">' . $multipage . '</div>';
    }
    render_footer();
}

function mc_empty(string $icon, string $text): string
{
    return '<div class="mc-empty"><i class="fas ' . $icon . ' text-success opacity-50"></i><div class="fw-semibold">' . $text . '</div></div>';
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
        
        $tid_placeholders = implode(',', array_fill(0, count($tids), '?'));
        $query = $db->sql_query_prepared(
            "SELECT tid FROM threads WHERE tid IN ({$tid_placeholders}) {$flist_queue_threads}",
            $tids
        );
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
        
        $pid_placeholders = implode(',', array_fill(0, count($pids), '?'));
        $query = $db->sql_query_prepared(
            "SELECT pid FROM posts WHERE pid IN ({$pid_placeholders}) {$flist_queue_posts}",
            $pids
        );
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
        $aid_placeholders = implode(',', array_fill(0, count($aids), '?'));
        $query = $db->sql_query_prepared("
            SELECT a.pid, a.aid, t.tid
            FROM attachments a
            LEFT JOIN posts p ON (a.pid = p.pid)
            LEFT JOIN threads t ON (t.tid = p.tid)
            WHERE aid IN ({$aid_placeholders}) {$tflist_queue_attach}
        ", $aids);
        while($attachment = $db->fetch_array($query)) {
            if(!isset($attachments[$attachment['aid']])) continue;
            $action = $attachments[$attachment['aid']];
            if($action == "approve") {
                $db->sql_query_prepared(
                    "UPDATE attachments SET visible = ? WHERE aid = ?",
                    [1, (int)$attachment['aid']]
                );
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
    global $db, $_this_script_, $mybb, $lang, $plugins, $templates, $parser, $cache, $usergroups, $BASEURL;
    global $flist_queue_threads, $tflist_queue_threads, $flist_queue_posts, $tflist_queue_posts;
    global $tflist_queue_attach, $f_threadsperpage, $f_postsperpage;
    global $nummodqueuethreads, $nummodqueueposts, $nummodqueueattach, $enableattachments;

    $type = $mybb->get_input('type');
    $forum_cache = $cache->read("forums");
    $site = rtrim((string)$BASEURL, '/') . '/';   // ссылки на форум — от корня сайта, а не от /admin/
    $attach_enabled = (int)$enableattachments === 1;
    $f_postsperpage = (int)$f_postsperpage > 0 ? (int)$f_postsperpage : 20;

    // Счётчики для вкладок (те же условия, что и у списков ниже)
    $counts = [];
    $q = $db->sql_query_prepared("SELECT COUNT(tid) AS cnt FROM threads WHERE visible = ? {$flist_queue_threads}", ['0']);
    $counts['threads'] = (int)$db->fetch_field($q, 'cnt');
    $q = $db->sql_query_prepared("SELECT COUNT(pid) AS cnt FROM posts p LEFT JOIN threads t ON (t.tid = p.tid) WHERE p.visible = ? {$tflist_queue_posts} AND t.firstpost != p.pid", ['0']);
    $counts['posts'] = (int)$db->fetch_field($q, 'cnt');
    if ($attach_enabled) {
        $q = $db->sql_query_prepared("SELECT COUNT(aid) AS cnt FROM attachments a LEFT JOIN posts p ON (p.pid = a.pid) LEFT JOIN threads t ON (t.tid = p.tid) WHERE a.visible = ? {$tflist_queue_attach}", ['0']);
        $counts['attachments'] = (int)$db->fetch_field($q, 'cnt');
    }

    // Без явного type — первая непустая очередь (раньше: всегда threads для супермодов)
    if (!$type) {
        $type = $counts['threads'] > 0 ? 'threads' : ($counts['posts'] > 0 ? 'posts' : (($counts['attachments'] ?? 0) > 0 ? 'attachments' : ''));
    }

    $page = max(1, (int)$mybb->get_input('page', MyBB::INPUT_INT));

    // ── Threads ─────────────────────────────────────────────────
    if ($type === 'threads') {
        $perpage = (int)$f_threadsperpage;
        $start   = ($page - 1) * $perpage;
        // Раньше ссылки пагинации вели на "modcp.php?type=threads" — в админке такой страницы нет
        $multipage = multipage($counts['threads'], $perpage, $page, $_this_script_ . '&type=threads');

        $query = $db->sql_query_prepared("
            SELECT t.tid, t.dateline, t.fid, t.subject, t.username AS threadusername,
                   p.message AS postmessage, u.username, t.uid
            FROM threads t
            LEFT JOIN posts p ON (p.pid = t.firstpost)
            LEFT JOIN users u ON (u.id = t.uid)
            WHERE t.visible = ? {$tflist_queue_threads}
            ORDER BY t.lastpost DESC
            LIMIT ?, ?
        ", ['0', $start, $perpage]);

        $html = '';
        while ($query && ($thread = $db->fetch_array($query))) {
            $tid     = (int)$thread['tid'];
            $subject = htmlspecialchars_uni($parser->parse_badwords((string)$thread['subject']));
            $author  = $thread['username']
                ? build_profile_link(htmlspecialchars_uni($thread['username']), (int)$thread['uid'])
                : ($thread['threadusername'] ? htmlspecialchars_uni($thread['threadusername']) : 'guest');
            $forum   = htmlspecialchars_uni($forum_cache[$thread['fid']]['name'] ?? '');

            $html .= mc_item('fa-list', 'ic-blue',
                '<a href="' . $site . get_thread_link($tid) . '" target="_blank" class="mc-item-title">' . $subject . '</a>',
                '<i class="fa-solid fa-user me-1"></i>' . $author . ' · <i class="fa-regular fa-clock mx-1"></i>' . my_datee('relative', (int)$thread['dateline']),
                '<a class="mc-chip" href="' . $site . get_forum_link((int)$thread['fid']) . '" target="_blank"><i class="fas fa-comments"></i>' . $forum . '</a>',
                mc_actions('threads', $tid, 't'),
                nl2br(htmlspecialchars_uni((string)$thread['postmessage']))
            );
        }
        $has = $html !== '';
        mc_queue_page('threads', $lang->modcp['threads_awaiting_moderation'] ?? 'Threads awaiting moderation',
            $has ? $html : mc_empty('fa-circle-check', $lang->modcp['mod_queue_threads_empty'] ?? 'No threads awaiting moderation'),
            $has, $multipage, $counts, $attach_enabled);
        return;
    }

    // ── Posts ───────────────────────────────────────────────────
    if ($type === 'posts') {
        $perpage   = $f_postsperpage;
        $start     = ($page - 1) * $perpage;
        $multipage = multipage($counts['posts'], $perpage, $page, $_this_script_ . '&type=posts');

        $query = $db->sql_query_prepared("
            SELECT p.pid, p.subject, p.message, p.username AS postusername,
                   t.subject AS threadsubject, t.tid, u.username, p.uid, t.fid, p.dateline
            FROM posts p
            LEFT JOIN threads t ON (t.tid = p.tid)
            LEFT JOIN users u ON (u.id = p.uid)
            WHERE p.visible = ? {$tflist_queue_posts} AND t.firstpost != p.pid
            ORDER BY p.dateline DESC
            LIMIT ?, ?
        ", ['0', $start, $perpage]);

        $html = '';
        while ($query && ($post = $db->fetch_array($query))) {
            $pid     = (int)$post['pid'];
            $tid     = (int)$post['tid'];
            $subject = htmlspecialchars_uni($parser->parse_badwords((string)$post['subject']));
            $tsubj   = htmlspecialchars_uni($parser->parse_badwords((string)$post['threadsubject']));
            $author  = $post['username']
                ? build_profile_link(htmlspecialchars_uni($post['username']), (int)$post['uid'])
                : ($post['postusername'] ? htmlspecialchars_uni($post['postusername']) : ($lang->guest ?? 'guest'));
            $forum   = htmlspecialchars_uni($forum_cache[$post['fid']]['name'] ?? '');

            $html .= mc_item('fa-comment', 'ic-teal',
                '<a href="' . $site . get_post_link($pid, $tid) . '#pid' . $pid . '" target="_blank" class="mc-item-title">' . ($subject !== '' ? $subject : 'RE: ' . $tsubj) . '</a>',
                '<i class="fa-solid fa-user me-1"></i>' . $author . ' · <i class="fa-regular fa-clock mx-1"></i>' . my_datee('relative', (int)$post['dateline']),
                '<a class="mc-chip" href="' . $site . get_thread_link($tid) . '" target="_blank" title="' . $tsubj . '"><i class="fas fa-list"></i>' . $tsubj . '</a>'
                . '<a class="mc-chip" href="' . $site . get_forum_link((int)$post['fid']) . '" target="_blank"><i class="fas fa-comments"></i>' . $forum . '</a>',
                mc_actions('posts', $pid, 'p'),
                nl2br(htmlspecialchars_uni((string)$post['message']))
            );
        }
        $has = $html !== '';
        mc_queue_page('posts', $lang->modcp['posts_awaiting_moderation'] ?? 'Posts awaiting moderation',
            $has ? $html : mc_empty('fa-circle-check', $lang->modcp['mod_queue_posts_empty'] ?? 'No posts awaiting moderation'),
            $has, $multipage, $counts, $attach_enabled);
        return;
    }

    // ── Attachments ─────────────────────────────────────────────
    if ($type === 'attachments' && $attach_enabled) {
        $perpage   = $f_postsperpage;
        $start     = ($page - 1) * $perpage;
        // Раньше строка была "'.$_this_script_.'&type=attachments" в двойных кавычках —
        // в ссылку попадал буквальный текст «'.$_this_script_.'»
        $multipage = multipage($counts['attachments'], $perpage, $page, $_this_script_ . '&type=attachments');

        $query = $db->sql_query_prepared("
            SELECT a.*, p.subject AS postsubject, p.dateline, p.uid, u.username, t.tid, t.subject AS threadsubject
            FROM attachments a
            LEFT JOIN posts p ON (p.pid = a.pid)
            LEFT JOIN threads t ON (t.tid = p.tid)
            LEFT JOIN users u ON (u.id = p.uid)
            WHERE a.visible = ? {$tflist_queue_attach}
            ORDER BY a.dateuploaded DESC
            LIMIT ?, ?
        ", ['0', $start, $perpage]);

        $html = '';
        while ($query && ($att = $db->fetch_array($query))) {
            $aid   = (int)$att['aid'];
            $fname = htmlspecialchars_uni((string)$att['filename']);
            $psubj = htmlspecialchars_uni($parser->parse_badwords((string)($att['postsubject'] ?? '')));
            $user  = $att['username'] ? build_profile_link(htmlspecialchars_uni($att['username']), (int)$att['uid']) : 'guest';

            $html .= mc_item('fa-paperclip', 'ic-amber',
                '<a href="' . $site . 'attachment.php?aid=' . $aid . '" target="_blank" class="mc-item-title">' . $fname . '</a>',
                '<i class="fa-solid fa-user me-1"></i>' . $user . ' · <i class="fa-regular fa-clock mx-1"></i>' . my_datee('relative', (int)($att['dateuploaded'] ?: $att['dateline'])),
                '<span class="mc-chip"><i class="fas fa-hard-drive"></i>' . mksize((float)$att['filesize']) . '</span>'
                . '<span class="mc-chip"><i class="fas fa-code"></i>' . htmlspecialchars_uni((string)($att['filetype'] ?? '')) . '</span>'
                . ($att['pid'] ? '<a class="mc-chip" href="' . $site . get_post_link((int)$att['pid'], (int)$att['tid']) . '#pid' . (int)$att['pid'] . '" target="_blank"><i class="fas fa-comment"></i>' . ($psubj !== '' ? $psubj : 'Post #' . (int)$att['pid']) . '</a>' : ''),
                mc_actions('attachments', $aid, 'a')
            );
        }
        $has = $html !== '';
        mc_queue_page('attachments', $lang->modcp['attachments_awaiting_moderation'] ?? 'Attachments awaiting moderation',
            $has ? $html : mc_empty('fa-circle-check', $lang->modcp['mod_queue_attachments_empty'] ?? 'No attachments awaiting moderation'),
            $has, $multipage, $counts, $attach_enabled);
        return;
    }

    // ── Всё пусто ───────────────────────────────────────────────
    render_header($lang->modcp['mod_queue'] ?? 'Moderation Queue');
    echo mc_hero('Nothing is waiting for a moderator right now');
    echo mc_tabs('', $counts, $attach_enabled);
    echo '<div class="mc-card">' . mc_empty('fa-circle-check', $lang->modcp['mod_queue_empty'] ?? 'The moderation queue is empty')
       . '</div>';
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
    // Раньше: шаблон modcp выполнялся через eval(), но его вывод был закомментирован —
    // страница оставалась пустой. Единственный раздел здесь — очередь модерации.
    $plugins->run_hooks('modcp_home');
    handle_modqueue();
}