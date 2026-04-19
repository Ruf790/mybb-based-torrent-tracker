<?php
declare(strict_types=1);

define('IN_MYBB',                  1);
define('IGNORE_CLEAN_VARS',        'sid');
define('THIS_SCRIPT',              'search.php');
define('SCRIPTNAME',               'search.php');
define('TSF_FORUMS_TSSEv56',       true);
define('TSF_FORUMS_GLOBAL_TSSEv56',true);

$templatelist  = 'search,forumdisplay_thread_gotounread,search_results_threads_thread,search_results_threads';
$templatelist .= ',search_results_posts,search_results_posts_post,search_results_icon';
$templatelist .= ',search_forumlist_forum,search_forumlist';
$templatelist .= ',multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage';
$templatelist .= ',multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start';
$templatelist .= ',search_results_posts_inlinecheck,search_results_posts_nocheck';
$templatelist .= ',search_results_threads_inlinecheck,search_results_threads_nocheck';
$templatelist .= ',search_results_inlinemodcol,search_results_inlinemodcol_empty';
$templatelist .= ',search_results_posts_inlinemoderation_custom_tool,search_results_posts_inlinemoderation_custom';
$templatelist .= ',search_results_posts_inlinemoderation,search_results_threads_inlinemoderation_custom_tool';
$templatelist .= ',search_results_threads_inlinemoderation_custom,search_results_threads_inlinemoderation';
$templatelist .= ',forumdisplay_thread_attachment_count,search_threads_inlinemoderation_selectall';
$templatelist .= ',search_posts_inlinemoderation_selectall,post_prefixselect_prefix,post_prefixselect_multiple';
$templatelist .= ',search_orderarrow,search_results_posts_forumlink,search_results_threads_forumlink';
$templatelist .= ',forumdisplay_thread_multipage_more,forumdisplay_thread_multipage_page,forumdisplay_thread_multipage';
$templatelist .= ',search_moderator_options';

define('IN_FORUM', true);
require_once 'global.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_search.php';


require_once(INC_PATH.'/class_parser.php');
$parser = new postParser;
  
  
$parser_options = array(
	"allow_html" => 1,
	"allow_mycode" => 1,
	"allow_smilies" => 1,
	"allow_imgcode" => 1,
	"allow_videocode" => 1,
	"filter_badwords" => 1
);


$lang->load('search');

// ─────────────────────────────────────────────────────────────────────────────
// CSS / HTML HELPERS
// ─────────────────────────────────────────────────────────────────────────────
function search_css(): string
{
    return <<<'CSS'
<style>
/* ============================================
   PREMIUM MODERN SEARCH DESIGN v2.0
   ============================================ */
:root {
    --gradient-1: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    --gradient-2: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
    --gradient-3: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%);
    --gradient-4: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    --gradient-gold: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
    --gradient-dark: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --primary-light: #60a5fa;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #0f172a;
    --light: #f8fafc;
    
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    --shadow-2xl: 0 25px 50px -12px rgba(0,0,0,0.25);
    --shadow-glow: 0 0 20px rgba(59,130,246,0.4);
    
    --radius-sm: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
    --radius-2xl: 2rem;
}

/* Global Background */
body.search-page {
    background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%);
    min-height: 100vh;
    position: relative;
}

body.search-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(59,130,246,0.03)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') repeat-x bottom;
    pointer-events: none;
    opacity: 0.5;
}

/* Hero Section Premium */
.sr-hero {
    text-align: center;
    margin-bottom: 2rem;
    padding: 2rem 1rem;
    background: var(--gradient-1);
    border-radius: var(--radius-2xl);
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-xl);
}

.sr-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: shimmer 8s infinite;
}

@keyframes shimmer {
    0% { transform: translate(-30%, -30%) rotate(0deg); }
    100% { transform: translate(30%, 30%) rotate(360deg); }
}

.sr-hero h1 {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #fff, #bfdbfe);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: relative;
    z-index: 1;
}

.sr-hero p {
    color: rgba(255,255,255,0.95);
    font-size: 1rem;
    font-weight: 500;
    position: relative;
    z-index: 1;
}

/* Quick Links Premium */
.sr-quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.sr-quick-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.25rem;
    background: white;
    border-radius: 2rem;
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
    border: 1px solid transparent;
}

.sr-quick-link:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: var(--gradient-1);
    color: white;
    border-color: transparent;
}

.sr-quick-link i {
    transition: transform 0.2s;
    font-size: 0.875rem;
}

.sr-quick-link:hover i {
    transform: scale(1.1);
}

/* Search Card Premium */
.sr-card {
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(10px);
    border-radius: var(--radius-2xl);
    box-shadow: var(--shadow-2xl);
    overflow: hidden;
    margin-bottom: 2rem;
    border: 1px solid rgba(255,255,255,0.3);
    transition: transform 0.3s, box-shadow 0.3s;
}

.sr-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-2xl);
}

.sr-card-body {
    padding: 2rem;
}

/* Main Search Row Premium */
.sr-main-row {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    position: relative;
}

.sr-main-row input {
    flex: 1;
    padding: 0.875rem 1.25rem;
    font-size: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: var(--radius-xl);
    transition: all 0.3s;
    background: #fefefe;
    font-weight: 500;
}

.sr-main-row input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: var(--shadow-glow);
    background: white;
}

.sr-btn {
    padding: 0.875rem 1.5rem;
    font-weight: 700;
    border-radius: var(--radius-xl);
    font-size: 0.9375rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.sr-btn-primary {
    background: var(--gradient-1);
    color: white;
    position: relative;
    overflow: hidden;
}

.sr-btn-primary::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.sr-btn-primary:hover::before {
    width: 300px;
    height: 300px;
}

.sr-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
}

.sr-btn-ghost {
    background: #f1f5f9;
    color: var(--secondary);
    border: 1px solid #e2e8f0;
}

.sr-btn-ghost:hover {
    background: #fee2e2;
    border-color: #fecaca;
    color: var(--danger);
    transform: translateY(-2px);
}

/* Advanced Toggle Premium */
.sr-adv-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    background: var(--gradient-1);
    border-radius: 2rem;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-sm);
}

.sr-adv-toggle:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.sr-adv-toggle i {
    transition: transform 0.3s;
    font-size: 0.875rem;
}

.sr-adv-toggle.active i {
    transform: rotate(180deg);
}

/* Advanced Panel Premium */
.sr-adv-panel {
    background: linear-gradient(135deg, #f9fafb, #ffffff);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    margin-top: 1rem;
    border: 1px solid #e2e8f0;
    box-shadow: var(--shadow-md);
}

.sr-field-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: var(--gradient-1);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 0.5rem;
}

.sr-field-ctrl {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: var(--radius-md);
    font-size: 0.9375rem;
    transition: all 0.3s;
    background: white;
}

.sr-field-ctrl:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: var(--shadow-glow);
}

/* Results Page Premium */
.sr-res-wrap {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.sr-res-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.sr-res-title {
    font-size: 1.5rem;
    font-weight: 800;
    background: var(--gradient-1);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.sr-res-count {
    color: var(--secondary);
    font-size: 0.875rem;
    font-weight: 500;
}

/* Sort Bar Premium */
.sr-sort-bar {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.sr-sort-btn {
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
    background: white;
    border: 1px solid #e2e8f0;
    color: var(--secondary);
    transition: all 0.3s;
}

.sr-sort-btn:hover,
.sr-sort-btn.active {
    background: var(--gradient-1);
    border-color: transparent;
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Thread Card Premium */
.sr-thread-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #e2e8f0;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.sr-thread-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-1);
    transform: scaleX(0);
    transition: transform 0.4s;
    transform-origin: left;
}

.sr-thread-card:hover::before {
    transform: scaleX(1);
}

.sr-thread-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary-light);
}

.sr-thread-inner {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

/* Avatar Premium */
.sr-avatar img,
.sr-avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    transition: transform 0.3s;
}

.sr-avatar:hover img,
.sr-avatar:hover .sr-avatar-placeholder {
    transform: scale(1.1);
}

.sr-avatar-placeholder {
    background: var(--gradient-1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
}

/* Thread Subject Premium */
.sr-thread-subject {
    font-size: 1.0625rem;
    font-weight: 700;
    color: var(--dark);
    text-decoration: none;
    transition: all 0.3s;
}

.sr-thread-subject:hover {
    background: var(--gradient-1);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

/* Badges Premium */
.sr-new-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.625rem;
    border-radius: 2rem;
    font-size: 0.6875rem;
    font-weight: 700;
    background: var(--gradient-4);
    color: #064e3b;
}

.sr-hot-badge {
    background: var(--gradient-gold);
    color: #78350f;
}

.sr-closed-badge {
    background: var(--gradient-dark);
    color: #94a3b8;
}

/* Meta Info Premium */
.sr-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.8125rem;
    color: var(--secondary);
    align-items: center;
    margin-top: 0.5rem;
}

.sr-meta a {
    color: var(--secondary);
    text-decoration: none;
    transition: color 0.3s;
}

.sr-meta a:hover {
    color: var(--primary);
}

/* Forum Badge Premium */
.sr-forum-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.3125rem 0.75rem;
    background: var(--gradient-3);
    color: white;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s;
}

.sr-forum-badge:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
    color: white;
}

/* Action Buttons Premium */
.sr-thread-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex-shrink: 0;
    align-self: center;
}

.sr-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    white-space: nowrap;
}

.sr-action-view {
    border: 2px solid var(--primary);
    color: var(--primary);
    background: transparent;
}

.sr-action-view:hover {
    background: var(--gradient-1);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Post Card Premium */
.sr-post-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.4s;
    border: 1px solid #e2e8f0;
    position: relative;
    overflow: hidden;
}

.sr-post-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-4);
    transform: scaleX(0);
    transition: transform 0.4s;
    transform-origin: left;
}

.sr-post-card:hover::before {
    transform: scaleX(1);
}

.sr-post-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
}

/* Snippet Premium */
.sr-snippet {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-left: 3px solid var(--primary);
    border-radius: var(--radius-md);
    padding: 0.75rem 1rem;
    margin-top: 0.75rem;
    font-size: 0.875rem;
    color: var(--secondary);
    line-height: 1.6;
}

/* Pagination Premium */
.sr-pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.sr-page-btn {
    min-width: 2.5rem;
    height: 2.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md);
    color: var(--secondary);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.3s;
}

.sr-page-btn:hover,
.sr-page-btn.active {
    background: var(--gradient-1);
    border-color: transparent;
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Empty State Premium */
.sr-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: var(--radius-xl);
    border: 1px solid #e2e8f0;
}

.sr-empty i {
    font-size: 4rem;
    background: var(--gradient-1);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 1rem;
    display: block;
}

.sr-empty h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.sr-empty p {
    font-size: 0.9375rem;
    color: var(--secondary);
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.sr-thread-card,
.sr-post-card {
    animation: fadeInUp 0.4s ease-out forwards;
}

/* Responsive */
@media (max-width: 768px) {
    .sr-hero h1 {
        font-size: 1.75rem;
    }
    
    .sr-card-body {
        padding: 1rem;
    }
    
    .sr-main-row {
        flex-direction: column;
    }
    
    .sr-thread-inner {
        flex-direction: column;
    }
    
    .sr-thread-actions {
        flex-direction: row;
        width: 100%;
    }
    
    .sr-action-btn {
        flex: 1;
        justify-content: center;
    }
    
    .sr-res-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: var(--gradient-1);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--gradient-2);
}
</style>
CSS;
}












function sr_avatar(string $avatar, string $dims, string $username): string
{
    $ua = format_avatar($avatar, $dims, '50|50');
    if (str_starts_with($ua['image'], '<')) return $ua['image'];
    if (!empty($ua['image']) && $ua['image'] !== 'default') {
        return '<img src="' . htmlspecialchars($ua['image']) . '" class="rounded-circle" style="width:50px;height:50px;object-fit:cover;" alt="">';
    }
    $letter = mb_strtoupper(mb_substr($username, 0, 1));
    return '<div class="sr-avatar-placeholder rounded-circle" style="width:50px;height:50px;">' . $letter . '</div>';
}

// ─────────────────────────────────────────────────────────────────────────────
// PREFIX SELECT
// ─────────────────────────────────────────────────────────────────────────────
function build_prefix_select(mixed $fid, mixed $selected_pid = 0, int $multiple = 0, int $previous_pid = 0): string
{
    global $cache, $db, $lang, $mybb, $templates;

    if ($fid !== 'all') $fid = (int)$fid;
    if (empty($prefix_cache)) return '';

    $prefixes = [];
    foreach ($prefix_cache as $prefix) {
        if ($fid !== 'all' && $prefix['forums'] !== '-1') {
            $forums = explode(',', $prefix['forums']);
            if (!in_array($fid, $forums) && $prefix['pid'] != $previous_pid) continue;
        }
        if (is_member($prefix['groups']) || $prefix['pid'] == $previous_pid) {
            $prefixes[$prefix['pid']] = $prefix;
        }
    }
    if (empty($prefixes)) return '';

    $prefixselect = $prefixselect_prefix = '';
    $any_selected     = ($multiple == 1 && $selected_pid === 'any') ? ' selected="selected"' : '';
    $default_selected = ((int)$selected_pid === 0 && $selected_pid !== 'any') ? ' selected="selected"' : '';

    foreach ($prefixes as $prefix) {
        $selected = ($prefix['pid'] == $selected_pid) ? ' selected="selected"' : '';
        $prefix['prefix'] = htmlspecialchars_uni($prefix['prefix']);
        eval("\$prefixselect_prefix .= \"".$templates->get('post_prefixselect_prefix')."\";");
    }

    if ($multiple !== 0) {
        eval("\$prefixselect = \"".$templates->get('post_prefixselect_multiple')."\";");
    } else {
        eval("\$prefixselect = \"".$templates->get('post_prefixselect_single')."\";");
    }
    return $prefixselect;
}

// ─────────────────────────────────────────────────────────────────────────────
// INIT
// ─────────────────────────────────────────────────────────────────────────────
$is_mod = is_mod($mybb->usergroup);
add_breadcrumb($lang->search['nav_search'], 'search.php');

$mybb->input['action'] = $mybb->get_input('action');
if ($mybb->input['action'] === 'results') add_breadcrumb($lang->search['nav_results']);
if ($usergroups['cansearch'] == 0) print_no_permission();

$now             = TIMENOW;
$mybb->input['keywords'] = trim($mybb->get_input('keywords'));
$searchhardlimit = 0;
$limitsql        = $searchhardlimit > 0 ? "LIMIT {$searchhardlimit}" : '';

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: results
// ─────────────────────────────────────────────────────────────────────────────
if ($mybb->input['action'] === 'results') {

    $sid    = $db->escape_string($mybb->get_input('sid'));
    $query  = $db->simple_select('searchlog', '*', "sid='$sid'");
    $search = $db->fetch_array($query);
	
	
	

	
	
    if (!$search) stderr($lang->search['error_invalidsearch']);

    $plugins->run_hooks('search_results_start');

    $order  = my_strtolower(htmlspecialchars_uni($mybb->get_input('order')));
    $sortby = my_strtolower(htmlspecialchars_uni($mybb->get_input('sortby')));

    $is_threads = isset($search['resulttype']) && $search['resulttype'] === 'threads';
	


    $sortfield = match ($sortby) {
        'replies' => 't.replies',
        'views'   => 't.views',
        'subject' => $is_threads ? 't.subject' : 'p.subject',
        'forum'   => 'f.name',
        'starter' => $is_threads ? 't.username' : 'p.username',
        default   => (function() use (&$sortby, $is_threads): string {
            if ($is_threads) { $sortby = 'lastpost'; return 't.lastpost'; }
            $sortby = 'dateline'; return 'p.dateline';
        })(),
    };

    if ($order !== 'asc') { $order = 'desc'; $oppsort = 'asc'; }
    else                  { $oppsort = 'desc'; }

    $perpage = 20;
    $page    = max(1, (int)$mybb->get_input('page'));
    $start   = ($page - 1) * $perpage;
    $upper   = $start + $perpage;

    $highlight = '';
    if (!empty($search['keywords'])) {
        $highlight = $mybb->seo_support
            ? '?highlight=' . urlencode($search['keywords'])
            : '&amp;highlight=' . urlencode($search['keywords']);
    }

    $sorturl    = "search.php?action=results&amp;sid={$sid}";
    $forumcache = $cache->read('forums');
    $forumsread = [];

    if ($CURUSER['id'] == 0) {
        $q = $db->sql_query("SELECT fid FROM tsf_forums WHERE active != 0 ORDER BY pid, disporder");
        $forumsread = isset($mybb->cookies['mybb']['forumread'])
            ? my_unserialize($mybb->cookies['mybb']['forumread'], false) : [];
    } else {
        $q = $db->sql_query("SELECT f.fid, fr.dateline AS lastread FROM tsf_forums f LEFT JOIN tsf_forumsread fr ON (fr.fid=f.fid AND fr.uid='{$CURUSER['id']}') WHERE f.active!=0 ORDER BY pid, disporder");
    }
    $readforums = [];
    while ($f = $db->fetch_array($q)) {
        if ($CURUSER['id'] == 0 && !empty($forumsread[$f['fid']])) $f['lastread'] = $forumsread[$f['fid']];
        $readforums[$f['fid']] = $f['lastread'] ?? '';
    }
    $fpermissions = forum_permissions();

    $inlinemodcol = $inlinecookie = '';
    $is_supermod  = $show_inline_moderation = false;
    $inlinecount  = 0;
    if ($usergroups['issupermod']) $is_supermod = true;
    if ($is_supermod || $is_mod) {
        $inlinecookie = 'inlinemod_search' . $sid;
        $is_mod       = true;
        $return_url   = 'search.php?' . htmlspecialchars_uni($_SERVER['QUERY_STRING']);
    }

    // ── Threads ────────────────────────────────────────────────────────────
    if ($is_threads) {

        $unapproved_where_t = get_visible_where('t');
        $threadcount        = 0;
        $threads            = [];

        if ($search['querycache'] !== '') {
            $q = $db->simple_select('tsf_threads t', 't.tid', $search['querycache'] . " AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' ORDER BY t.lastpost DESC {$limitsql}");
            while ($t = $db->fetch_array($q)) { $threads[$t['tid']] = $t['tid']; $threadcount++; }
            if (!$threadcount) stderr($lang->search['error_nosearchresults']);
            $search['threads'] = implode(',', $threads);
            $where_conditions  = 't.tid IN (' . $search['threads'] . ')';
        } else {
            $where_conditions = 't.tid IN (' . $search['threads'] . ')';
            $q     = $db->simple_select('tsf_threads t', 'COUNT(t.tid) AS resultcount', $where_conditions . " AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' {$limitsql}");
            $cnt   = $db->fetch_array($q);
            if (!$cnt['resultcount']) stderr($lang->search['error_nosearchresults']);
            $threadcount = $cnt['resultcount'];
        }

        $permsql = ''; $onlyusfids = [];
        $gp = forum_permissions();
        foreach ($gp as $fid => $fp) {
            if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) $onlyusfids[] = $fid;
        }
        if (!empty($onlyusfids)) $permsql .= "AND ((t.fid IN(" . implode(',', $onlyusfids) . ") AND t.uid='{$CURUSER['id']}') OR t.fid NOT IN(" . implode(',', $onlyusfids) . "))";
        $uf = get_unsearchable_forums(); if ($uf) $permsql .= " AND t.fid NOT IN ($uf)";
        $ia = get_inactive_forums();     if ($ia) $permsql .= " AND t.fid NOT IN ($ia)";

        $pages = max(1, (int)ceil($threadcount / $perpage));
        if ($page > $pages) { $start = 0; $page = 1; }

        $q = $db->sql_query("
            SELECT t.*, u.username AS userusername, u.avatar, u.avatardimensions, u.usergroup AS u_usergroup
            FROM tsf_threads t
            LEFT JOIN users u ON (u.id = t.uid)
            LEFT JOIN tsf_forums f ON (t.fid = f.fid)
            WHERE $where_conditions AND ({$unapproved_where_t}) {$permsql} AND t.closed NOT LIKE 'moved|%'
            ORDER BY $sortfield $order
            LIMIT $start, $perpage
        ");

        $thread_cache = [];
        while ($t = $db->fetch_array($q)) {
            $t['threadprefix'] = '';
            $thread_cache[$t['tid']] = $t;
        }
        $thread_ids = implode(',', array_keys($thread_cache));
        if (empty($thread_ids)) stderr($lang->search['error_nosearchresults']);

        // dot icons
        if ($CURUSER['id'] && $thread_cache) {
            $uwp = str_replace('t.', '', $unapproved_where_t);
            $q2  = $db->simple_select('tsf_posts', 'DISTINCT tid,uid', "uid='{$CURUSER['id']}' AND tid IN({$thread_ids}) AND ({$uwp})");
            while ($t = $db->fetch_array($q2)) $thread_cache[$t['tid']]['dot_icon'] = 1;
        }
        // read threads
        $threadreadcut = 7;
        if ($CURUSER['id'] && $threadreadcut > 0) {
            $q2 = $db->simple_select('tsf_threadsread', 'tid,dateline', "uid='{$CURUSER['id']}' AND tid IN({$thread_ids})");
            while ($rt = $db->fetch_array($q2)) $thread_cache[$rt['tid']]['lastread'] = $rt['dateline'];
        }

        // inline mod prep
        $show_inline_moderation = false;
        $inline_mod_items       = [];
        foreach ($thread_cache as $tid => $_) {
            $ck = '';
            if ($is_supermod || $is_mod) {
                $ck = (isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], "|{$tid}|") !== false) ? 'checked' : '';
                if ($ck) $inlinecount++;
                $show_inline_moderation = true;
            }
            $inline_mod_items[$tid] = $ck;
        }

        // ── OUTPUT ────────────────────────────────────────────────────────
        stdhead('Search Results');
        build_breadcrumb();
        echo search_css();
        echo '<div class="sr-res-wrap">';

        // Header
        echo '<div class="sr-res-header">';
        echo '<div><div class="sr-res-title"><i class="fas fa-search me-2" style="color:var(--sr-primary)"></i>Thread Results</div>';
        echo '<div class="sr-res-count">Found <strong>' . ts_nf($threadcount) . '</strong> thread' . ($threadcount !== 1 ? 's' : '') . '</div></div>';
        echo '<div class="sr-sort-bar">';
        $sort_opts = ['lastpost'=>'Date','replies'=>'Replies','views'=>'Views','subject'=>'Subject','starter'=>'Author','forum'=>'Forum'];
        foreach ($sort_opts as $key => $label) {
            $active = $sortby === $key ? ' active' : '';
            $no     = ($sortby === $key && $order === 'asc') ? 'desc' : 'asc';
            echo '<a href="' . $sorturl . '&amp;sortby=' . $key . '&amp;order=' . $no . '" class="sr-sort-btn' . $active . '">' . $label . '</a>';
        }
        echo '</div></div>';

        // Cards
        $f_postsperpage  = 10;
        $maxmultilinks   = 5;

        foreach ($thread_cache as $thread) {
            if ($thread['userusername']) $thread['username'] = $thread['userusername'];
            $thread['username']    = htmlspecialchars_uni($thread['username']);
            $thread['subject']     = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

            $thread_link  = get_thread_link($thread['tid']);
            $lastpostdate = my_datee('relative', $thread['lastpost']);
            $lp_uid       = $thread['lastposteruid'];
            $lp_name      = $lp_uid ? htmlspecialchars_uni($thread['lastposter']) : htmlspecialchars_uni($lang->guest);
            $lp_link      = $lp_uid ? build_profile_link($lp_name, $lp_uid) : $lp_name;

            // new/hot/closed badges
            $badges = '';
            $read_cutoff = TIMENOW - $threadreadcut * 86400;
            $forum_read  = $readforums[$thread['fid']] ?? 0;
            if ($forum_read == 0 || $forum_read < $read_cutoff) $forum_read = $read_cutoff;
            if ($threadreadcut > 0 && $CURUSER['id'] && $thread['lastpost'] > $forum_read) {
                $last_read = $thread['lastread'] ?? $read_cutoff;
            } else {
                $last_read = my_get_array_cookie('threadread', $thread['tid']);
            }
            if ($forum_read > $last_read) $last_read = $forum_read;
            if ($thread['lastpost'] > $last_read && $last_read) {
                $badges .= '<span class="sr-new-badge"><i class="fas fa-circle" style="font-size:6px"></i> New</span>';
            }
            if ($thread['replies'] >= 20 || $thread['views'] >= 150) {
                $badges .= '<span class="sr-new-badge sr-hot-badge"><i class="fas fa-fire"></i> Hot</span>';
            }
            if ($thread['closed'] == 1) {
                $badges .= '<span class="sr-new-badge sr-closed-badge"><i class="fas fa-lock"></i></span>';
            }

            // multipage
            $multipages_html = '';
            $thread['posts'] = $thread['replies'] + 1;
            if ($is_mod) $thread['posts'] += $thread['unapprovedposts'];
            if ($thread['posts'] > $f_postsperpage) {
                $total_pages = (int)ceil($thread['posts'] / $f_postsperpage);
                $stop        = min($total_pages, $maxmultilinks);
                $multipages_html = '<div class="sr-multipage">';
                for ($i = 1; $i <= $stop; $i++) {
                    $multipages_html .= '<a href="' . get_thread_link($thread['tid'], $i) . $highlight . '">' . $i . '</a>';
                }
                if ($total_pages > $maxmultilinks) {
                    $multipages_html .= '<a href="' . get_thread_link($thread['tid'], $total_pages) . $highlight . '">…' . $total_pages . '</a>';
                }
                $multipages_html .= '</div>';
            }

            // forum badge
            $forum_name   = htmlspecialchars($forumcache[$thread['fid']]['name'] ?? '');
            $forum_link   = get_forum_link($thread['fid']);
            $profile_link = get_profile_link((int)$thread['uid']);
            $avatar_html  = sr_avatar($thread['avatar'] ?? '', $thread['avatardimensions'] ?? '', $thread['username']);

            // inline mod checkbox
            $chk_html = '';
            if ($is_supermod || $is_mod) {
                $ck = $inline_mod_items[$thread['tid']] ?? '';
                $chk_html = '<input type="checkbox" name="inlinemod[' . $thread['tid'] . ']" value="' . $thread['tid'] . '" ' . $ck . ' style="margin-right:6px;">';
            }

            echo '<div class="sr-thread-card" onclick="window.location.href=\'' . $thread_link . '\'">';
            echo '<div class="sr-thread-inner">';
            echo '<div class="sr-avatar"><a href="' . $profile_link . '" onclick="event.stopPropagation()">' . $avatar_html . '</a></div>';
            echo '<div class="sr-thread-body">';

            // Subject row
            echo '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:6px;">';
            echo $chk_html;
            echo '<a href="' . $thread_link . '" class="sr-thread-subject" onclick="event.stopPropagation()">';
            echo htmlspecialchars_uni($thread['threadprefix']) . $thread['subject'];
            echo '</a>';
            echo $badges;
            echo '</div>';

            // Meta
            echo '<div class="sr-meta">';
            echo '<a href="' . $forum_link . '" class="sr-sort-btn active" onclick="event.stopPropagation()"><i class="fas fa-folder-open"></i>' . $forum_name . '</a>';
            echo '<span><i class="fas fa-user"></i><a href="' . $profile_link . '" onclick="event.stopPropagation()">' . $thread['username'] . '</a></span>';
            echo '<span><i class="fas fa-comments"></i>' . ts_nf($thread['replies']) . ' replies</span>';
            echo '<span><i class="fas fa-eye"></i>' . ts_nf($thread['views']) . ' views</span>';
            echo '<span><i class="fas fa-clock"></i>' . $lastpostdate . ' by ' . $lp_link . '</span>';
            echo '</div>';

            if ($multipages_html) echo $multipages_html;
            echo '</div>'; // body

            // Action button
            echo '<div class="sr-thread-actions">';
            echo '<a href="' . $thread_link . '" class="sr-action-btn sr-action-view" onclick="event.stopPropagation()"><i class="fas fa-arrow-right"></i> View</a>';
            echo '</div>';

            echo '</div>'; // inner
            echo '</div>'; // card
        }

        // Pagination
        $mp_url = "search.php?action=results&amp;sid=$sid&amp;sortby=$sortby&amp;order=$order&amp;uid=" . $mybb->get_input('uid', MyBB::INPUT_INT);
        echo '<div class="sr-pagination">';
        if ($page > 1) echo '<a href="' . $mp_url . '&amp;page=' . ($page - 1) . '" class="sr-page-btn"><i class="fas fa-chevron-left"></i></a>';
        $ps = max(1, $page - 2); $pe = min($pages, $page + 2);
        if ($ps > 1) { echo '<a href="' . $mp_url . '&amp;page=1" class="sr-page-btn">1</a>'; if ($ps > 2) echo '<span class="sr-page-btn disabled">…</span>'; }
        for ($i = $ps; $i <= $pe; $i++) echo '<a href="' . $mp_url . '&amp;page=' . $i . '" class="sr-page-btn' . ($i === $page ? ' active' : '') . '">' . $i . '</a>';
        if ($pe < $pages) { if ($pe < $pages - 1) echo '<span class="sr-page-btn disabled">…</span>'; echo '<a href="' . $mp_url . '&amp;page=' . $pages . '" class="sr-page-btn">' . $pages . '</a>'; }
        if ($page < $pages) echo '<a href="' . $mp_url . '&amp;page=' . ($page + 1) . '" class="sr-page-btn"><i class="fas fa-chevron-right"></i></a>';
        echo '</div>';

        // Inline mod
        if ($show_inline_moderation) {
            $selectall = ''; $inlinemod = '';
            eval("\$inlinemodcol = \"".$templates->get('search_results_inlinemodcol')."\";");
            $page_selected = sprintf($lang->search['page_selected'], count($thread_cache));
            $all_selected  = sprintf($lang->search['all_selected'], (int)$threadcount);
            $select_all    = sprintf($lang->search['select_all'], (int)$threadcount);
            eval("\$selectall = \"".$templates->get('search_threads_inlinemoderation_selectall')."\";");
            $customthreadtools = '';
            $q3 = $db->simple_select('modtools', 'tid, name', "type='t' AND (CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='')");
            while ($tool = $db->fetch_array($q3)) {
                $tool['name'] = htmlspecialchars_uni($tool['name']);
                eval("\$customthreadtools .= \"".$templates->get('search_results_threads_inlinemoderation_custom_tool')."\";");
            }
            if (!empty($customthreadtools)) eval("\$customthreadtools = \"".$templates->get('search_results_threads_inlinemoderation_custom')."\";");
            eval("\$inlinemod = \"".$templates->get('search_results_threads_inlinemoderation')."\";");
            echo $inlinemod;
        }

        echo '</div>';
        $plugins->run_hooks('search_results_end');
        stdfoot();

    } else {
        // ── Posts ──────────────────────────────────────────────────────────
		
		
        if (empty($search['posts'])) stderr($lang->search['error_nosearchresults']);

        $unapproved_where   = get_visible_where();
        $post_cache_options = [];
        if ($searchhardlimit > 0) $post_cache_options['limit'] = $searchhardlimit;
        if (str_contains($sortfield, 'p.')) {
            $post_cache_options['order_by']  = str_replace('p.', '', $sortfield);
            $post_cache_options['order_dir'] = $order;
        }

        $tids = []; $pids = [];
        $q = $db->simple_select('tsf_posts', 'pid, tid', "pid IN(" . $db->escape_string($search['posts']) . ") AND ({$unapproved_where})", $post_cache_options);
		


		
		
        while ($p = $db->fetch_array($q)) 
		{ 
	
	     $pids[$p['pid']] = $p['tid']; $tids[$p['tid']][$p['pid']] = $p['pid']; 
		 
		
	
	  
	    }
		
	
		
		
		

        if (!empty($pids)) {
            $gp = forum_permissions(); $permsql = ''; $onlyusfids = [];
            foreach ($gp as $fid => $fp) { if (!empty($fp['canonlyviewownthreads'])) $onlyusfids[] = $fid; }
            if ($onlyusfids) $permsql .= ' OR (fid IN(' . implode(',', $onlyusfids) . ") AND uid!={$CURUSER['id']})";
            $uf = get_unsearchable_forums(); if ($uf) $permsql .= " OR fid IN ($uf)";
            $ia = get_inactive_forums();     if ($ia) $permsql .= " OR fid IN ($ia)";
            $q = $db->simple_select('tsf_threads', 'tid', "tid IN(" . $db->escape_string(implode(',', array_keys($tids))) . ") AND (NOT ({$unapproved_where}){$permsql} OR closed LIKE 'moved|%')");
            while ($t = $db->fetch_array($q)) {
                foreach ($tids[$t['tid']] as $pid) unset($pids[$pid]);
                unset($tids[$t['tid']]);
            }
        }
		
		
		

        $postcount = count($pids);
		
		
		
		
		
        if (!$postcount) stderr($lang->search['error_nosearchresults']);
        $search['posts'] = implode(',', array_keys($pids));
        $tids_str        = implode(',', array_keys($tids));

        $readthreads = [];
        $threadreadcut = 7;
        if ($CURUSER['id'] && $threadreadcut > 0) {
            $q = $db->simple_select('tsf_threadsread', 'tid, dateline', "uid='{$CURUSER['id']}' AND tid IN(" . $db->escape_string($tids_str) . ")");
            while ($rt = $db->fetch_array($q)) $readthreads[$rt['tid']] = $rt['dateline'];
        }

        $pages = max(1, (int)ceil($postcount / $perpage));
        if ($page > $pages) { $start = 0; $page = 1; }

        $q = $db->sql_query("
            SELECT p.*, u.username AS userusername, u.avatar, u.avatardimensions, u.usergroup AS u_usergroup,
                   t.subject AS thread_subject, t.replies AS thread_replies, t.views AS thread_views,
                   t.lastpost AS thread_lastpost, t.closed AS thread_closed, t.uid AS thread_uid
            FROM tsf_posts p
            LEFT JOIN tsf_threads t ON (t.tid = p.tid)
            LEFT JOIN users u ON (u.id = p.uid)
            LEFT JOIN tsf_forums f ON (t.fid = f.fid)
            WHERE p.pid IN (" . $db->escape_string($search['posts']) . ")
            ORDER BY $sortfield $order
            LIMIT $start, $perpage
        ");
		

        stdhead('Search Results — Posts');
        build_breadcrumb();
        echo search_css();
        echo '<div class="sr-res-wrap">';

        // Header
        echo '<div class="sr-res-header">';
        echo '<div><div class="sr-res-title"><i class="fas fa-comment-dots me-2" style="color:#10b981"></i>Post Results</div>';
        echo '<div class="sr-res-count">Found <strong>' . ts_nf($postcount) . '</strong> post' . ($postcount !== 1 ? 's' : '') . '</div></div>';
        echo '<div class="sr-sort-bar">';
        $sort_opts2 = ['dateline'=>'Date','subject'=>'Subject','starter'=>'Author','forum'=>'Forum'];
        foreach ($sort_opts2 as $key => $label) {
            $active = $sortby === $key ? ' active' : '';
            $no     = ($sortby === $key && $order === 'asc') ? 'desc' : 'asc';
            echo '<a href="' . $sorturl . '&amp;sortby=' . $key . '&amp;order=' . $no . '" class="sr-sort-btn' . $active . '">' . $label . '</a>';
        }
        echo '</div></div>';

        

        while ($post = $db->fetch_array($q)) {
            if ($post['userusername']) $post['username'] = $post['userusername'];
            $post['username']       = htmlspecialchars_uni($post['username']);
            $post['thread_subject'] = htmlspecialchars_uni($parser->parse_badwords($post['thread_subject']));

            //$parser_opts['me_username'] = $post['username'];
            $clean_msg = $parser->parse_message($post['message'], $parser_options);
			
			$preview = $clean_msg;

            $posted    = my_datee('relative', $post['dateline']);

            $thread_url  = get_thread_link($post['tid']);
            $post_url    = get_post_link($post['pid'], $post['tid']);
            $forum_name  = htmlspecialchars($forumcache[$post['fid']]['name'] ?? '');
            $forum_link  = get_forum_link($post['fid']);
            $profile_url = get_profile_link((int)$post['uid']);
            $avatar_html = sr_avatar($post['avatar'] ?? '', $post['avatardimensions'] ?? '', $post['username']);

            // inline mod
            $chk_html = '';
            if ($is_supermod || $is_mod) {
                $ck = (isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], "|{$post['pid']}|") !== false) ? 'checked' : '';
                if ($ck) $inlinecount++;
                $show_inline_moderation = true;
                $chk_html = '<input type="checkbox" name="inlinemod[' . $post['pid'] . ']" value="' . $post['pid'] . '" ' . $ck . ' style="margin-right:6px;">';
            }

            echo '<div class="sr-post-card">';
            echo '<div class="sr-thread-inner">';
            echo '<div class="sr-avatar"><a href="' . $profile_url . '">' . $avatar_html . '</a></div>';
            echo '<div class="sr-thread-body">';

            echo '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:6px;">';
            echo $chk_html;
            echo '<a href="' . $thread_url . '" class="sr-thread-subject">' . $post['thread_subject'] . '</a>';
            echo '<span class="badge bg-success-subtle text-success-emphasis" style="font-size:.7rem;font-weight:700;">Post</span>';
            echo '</div>';

            if ($preview) {
                echo '<div class="sr-snippet"><i class="fas fa-quote-left me-1 opacity-50"></i>' . $preview . '</div>';
            }

            echo '<div class="sr-meta" style="margin-top:10px;">';
            echo '<a href="' . $forum_link . '" class="sr-sort-btn active"><i class="fas fa-folder-open"></i>' . $forum_name . '</a>';
            echo '<span><i class="fas fa-user"></i><a href="' . $profile_url . '">' . $post['username'] . '</a></span>';
            echo '<span><i class="fas fa-clock"></i>' . $posted . '</span>';
            echo '</div>';

            echo '</div>'; // body

            echo '<div class="sr-thread-actions">';
            echo '<a href="' . $post_url . '#pid' . $post['pid'] . '" class="sr-action-btn sr-action-view"><i class="fas fa-comment"></i> View post</a>';
            echo '<a href="' . $thread_url . '" class="sr-action-btn" style="border:1px solid var(--sr-border);color:var(--sr-secondary);" onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'\'"><i class="fas fa-list"></i> Thread</a>';
            echo '</div>';

            echo '</div></div>';
        }

        // Pagination
        $mp_url2 = "search.php?action=results&amp;sid=" . htmlspecialchars_uni($mybb->get_input('sid')) . "&amp;sortby=$sortby&amp;order=$order&amp;uid=" . $mybb->get_input('uid', MyBB::INPUT_INT);
        echo '<div class="sr-pagination">';
        if ($page > 1) echo '<a href="' . $mp_url2 . '&amp;page=' . ($page - 1) . '" class="sr-page-btn"><i class="fas fa-chevron-left"></i></a>';
        $ps2 = max(1, $page - 2); $pe2 = min($pages, $page + 2);
        if ($ps2 > 1) { echo '<a href="' . $mp_url2 . '&amp;page=1" class="sr-page-btn">1</a>'; if ($ps2 > 2) echo '<span class="sr-page-btn disabled">…</span>'; }
        for ($i = $ps2; $i <= $pe2; $i++) echo '<a href="' . $mp_url2 . '&amp;page=' . $i . '" class="sr-page-btn' . ($i === $page ? ' active' : '') . '">' . $i . '</a>';
        if ($pe2 < $pages) { if ($pe2 < $pages - 1) echo '<span class="sr-page-btn disabled">…</span>'; echo '<a href="' . $mp_url2 . '&amp;page=' . $pages . '" class="sr-page-btn">' . $pages . '</a>'; }
        if ($page < $pages) echo '<a href="' . $mp_url2 . '&amp;page=' . ($page + 1) . '" class="sr-page-btn"><i class="fas fa-chevron-right"></i></a>';
        echo '</div>';

        if ($show_inline_moderation) {
            eval("\$inlinemodcol = \"".$templates->get('search_results_inlinemodcol')."\";");
            $num_results   = $db->num_rows($q);
            $page_selected = sprintf('page_selected', (int)$num_results);
            $select_all    = sprintf('select_all', (int)$postcount);
            $all_selected  = sprintf('all_selected', (int)$postcount);
            eval("\$selectall = \"".$templates->get('search_posts_inlinemoderation_selectall')."\";");
            $customposttools = '';
            $q4 = $db->simple_select('modtools', 'tid, name, type', "type='p' AND (CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='')");
            while ($tool = $db->fetch_array($q4)) eval("\$customposttools .= \"".$templates->get('search_results_posts_inlinemoderation_custom_tool')."\";");
            if (!empty($customposttools)) eval("\$customposttools = \"".$templates->get('search_results_posts_inlinemoderation_custom')."\";");
            $inlinemod = '';
            eval("\$inlinemod = \"".$templates->get('search_results_posts_inlinemoderation')."\";");
            echo $inlinemod;
        }

        echo '</div>';
        $plugins->run_hooks('search_results_end');
        stdfoot();
    }

// ─────────────────────────────────────────────────────────────────────────────
// findguest / finduser / finduserthreads / getnew / getdaily
// ─────────────────────────────────────────────────────────────────────────────
} elseif (in_array($mybb->input['action'], ['findguest','finduser','finduserthreads','getnew','getdaily'], true)) {

    $action = $mybb->input['action'];

    $where_sql = match ($action) {
        'findguest'       => "uid='0'",
        'finduser',
        'finduserthreads' => "uid='" . $mybb->get_input('uid', MyBB::INPUT_INT) . "'",
        'getnew'          => "lastpost >= '" . (int)$CURUSER['lastvisit'] . "'",
        'getdaily'        => (function() use ($mybb): string {
            $days = max(1, $mybb->get_input('days', MyBB::INPUT_INT));
            return "lastpost >= '" . (TIMENOW - 86400 * $days) . "'";
        })(),
    };

    // optional fid filter for getnew/getdaily
    if (in_array($action, ['getnew','getdaily'], true)) {
        if ($mybb->get_input('fid', MyBB::INPUT_INT)) {
            $where_sql .= " AND fid='" . $mybb->get_input('fid', MyBB::INPUT_INT) . "'";
        } elseif ($mybb->get_input('fids')) {
            $fids = array_map('intval', explode(',', $mybb->get_input('fids')));
            if ($fids) $where_sql .= ' AND fid IN (' . implode(',', $fids) . ')';
        }
    }

    $uf = get_unsearchable_forums(); if ($uf) $where_sql .= " AND fid NOT IN ($uf)";
    $ia = get_inactive_forums();     if ($ia) $where_sql .= " AND fid NOT IN ($ia)";
    $where_sql .= ' AND (' . get_visible_where() . ')';

    $gp = forum_permissions(); $onlyusfids = [];
    foreach ($gp as $fid => $fp) {
        if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) $onlyusfids[] = $fid;
    }
    if (!empty($onlyusfids)) {
        if ($action === 'findguest') {
            $where_sql .= ' AND fid NOT IN(' . implode(',', $onlyusfids) . ')';
        } else {
            $where_sql .= " AND ((fid IN(" . implode(',', $onlyusfids) . ") AND uid='{$CURUSER['id']}') OR fid NOT IN(" . implode(',', $onlyusfids) . "))";
        }
    }

    $resulttype = 'threads'; $querycache = ''; $pids = ''; $tids = ''; $comma = '';

    if (in_array($action, ['finduserthreads','getnew','getdaily'], true)) {
        $q = $db->simple_select('tsf_threads', 'tid', $where_sql);
        while ($tid = $db->fetch_field($q, 'tid')) { $tids .= $comma . $tid; $comma = ','; }
        $querycache = $where_sql;
    } else {
        $resulttype = 'posts';
        $opts = ['order_by' => 'dateline DESC, pid DESC'];
        if ($searchhardlimit > 0) $opts['limit'] = $searchhardlimit;
        $q = $db->simple_select('tsf_posts', 'pid', $where_sql, $opts);
        while ($pid = $db->fetch_field($q, 'pid')) { $pids .= $comma . $pid; $comma = ','; }
        $comma = '';
        $q = $db->simple_select('tsf_threads', 'tid', $where_sql);
        while ($tid = $db->fetch_field($q, 'tid')) { $tids .= $comma . $tid; $comma = ','; }
    }

    $sid = md5(uniqid(microtime(), true));
    $plugins->run_hooks('search_do_search_process');
    $db->insert_query('searchlog', [
        'sid'        => $db->escape_string($sid),
        'uid'        => $CURUSER['id'],
        'dateline'   => TIMENOW,
        'ipaddress'  => $db->escape_binary($session->packedip),
        'threads'    => $db->escape_string($tids),
        'posts'      => $db->escape_string($pids),
        'resulttype' => $resulttype,
        'querycache' => $db->escape_string($querycache),
        'keywords'   => '',
    ]);
    redirect("search.php?action=results&sid=$sid", $lang->search['redirect_searchresults']);

// ─────────────────────────────────────────────────────────────────────────────
// do_search
// ─────────────────────────────────────────────────────────────────────────────
} elseif ($mybb->input['action'] === 'do_search') {
	
	
	 

    $plugins->run_hooks('search_do_search_start');

    $searchfloodtime = 30;
    if ($searchfloodtime > 0 && $usergroups['cansearch'] != 1) {
        $cond    = $CURUSER['id'] ? "uid='{$CURUSER['id']}'" : "uid='0' AND ipaddress=" . $CURUSER['ip'];
        $timecut = TIMENOW - $searchfloodtime;
        $q       = $db->simple_select('searchlog', '*', "$cond AND dateline > '$timecut'", ['order_by'=>'dateline','order_dir'=>'DESC']);
        $ls      = $db->fetch_array($q);
        if (!empty($ls['sid'])) {
            $rt  = $searchfloodtime - (TIMENOW - $ls['dateline']);
            stderr($rt === 1
                ? sprintf($lang->search['error_searchflooding_1'], $searchfloodtime)
                : sprintf($lang->search['error_searchflooding'], $searchfloodtime, $rt)
            );
        }
    }

    $resulttype = $mybb->get_input('showresults') === 'threads' ? 'threads' : 'posts';
    $forums     = isset($mybb->input['forums']) && is_array($mybb->input['forums'])
        ? $mybb->get_input('forums', MyBB::INPUT_ARRAY)
        : [$mybb->get_input('forums')];

    $search_data = [
        'keywords'      => $mybb->input['keywords'],
        'author'        => $mybb->get_input('author'),
        'postthread'    => $mybb->get_input('postthread',    MyBB::INPUT_INT),
        'matchusername' => $mybb->get_input('matchusername', MyBB::INPUT_INT),
        'postdate'      => $mybb->get_input('postdate',      MyBB::INPUT_INT),
        'pddir'         => $mybb->get_input('pddir',         MyBB::INPUT_INT),
        'forums'        => $forums,
        'findthreadst'  => $mybb->get_input('findthreadst',  MyBB::INPUT_INT),
        'numreplies'    => $mybb->get_input('numreplies',    MyBB::INPUT_INT),
        'threadprefix'  => $mybb->get_input('threadprefix',  MyBB::INPUT_ARRAY),
    ];
	
	
	
	
	
	
	
    if ($is_mod && !empty($mybb->input['visible'])) {
        $search_data['visible'] = $mybb->get_input('visible', MyBB::INPUT_INT);
    }

    if (!$db->can_search) stderr('error_no_search_support');

    $search_results = ($db->supports_fulltext_boolean('posts') && $db->is_fulltext('posts'))
        ? perform_search_mysql_ft($search_data)
        : perform_search_mysql($search_data);

    $sid = md5(uniqid(microtime(), true));
    $plugins->run_hooks('search_do_search_process');
    
	
	
	$db->insert_query('searchlog', [
        'sid'        => $db->escape_string($sid),
        'uid'        => $CURUSER['id'],
        'dateline'   => $now,
        'ipaddress'  => $db->escape_binary($session->packedip),
        'threads'    => $search_results['threads'],
        'posts'      => $search_results['posts'],
        'resulttype' => $resulttype,
        'querycache' => $search_results['querycache'],
        'keywords'   => $db->escape_string($mybb->input['keywords']),
    ]);

    $so = my_strtolower($mybb->get_input('sortordr'));
    $sortorder = in_array($so, ['asc','desc']) ? $so : 'desc';
    $sortby    = htmlspecialchars_uni($mybb->get_input('sortby'));
    $plugins->run_hooks('search_do_search_end');
    redirect("search.php?action=results&sid={$sid}&sortby={$sortby}&order={$sortorder}", $lang->search['redirect_searchresults']);

// ─────────────────────────────────────────────────────────────────────────────
// thread search
// ─────────────────────────────────────────────────────────────────────────────
} elseif ($mybb->input['action'] === 'thread') {

    $thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
    $forum  = get_forum($thread['fid']);
    if (!$forum) stderr($lang->search['error_invalidforum']);
    if ($forum['open'] == 0 || $forum['type'] !== 'f') stderr($lang->search['error_closedinvalidforum']);
    if (!$db->can_search) stderr($lang->search['error_no_search_support']);

    $plugins->run_hooks('search_thread_start');

    $search_results = ($db->supports_fulltext_boolean('posts') && $db->is_fulltext('posts'))
        ? perform_search_mysql_ft(['keywords'=>$mybb->input['keywords'],'postthread'=>1,'tid'=>$mybb->get_input('tid',MyBB::INPUT_INT)])
        : perform_search_mysql(['keywords'=>$mybb->input['keywords'],'postthread'=>1,'tid'=>$mybb->get_input('tid',MyBB::INPUT_INT)]);

    $sid = md5(uniqid(microtime(), true));
    $plugins->run_hooks('search_thread_process');
    $db->insert_query('searchlog', [
        'sid'        => $db->escape_string($sid),
        'uid'        => $CURUSER['id'],
        'dateline'   => $now,
        'ipaddress'  => $db->escape_binary($session->packedip),
        'threads'    => $search_results['threads'],
        'posts'      => $search_results['posts'],
        'resulttype' => 'posts',
        'querycache' => $search_results['querycache'],
        'keywords'   => $db->escape_string($mybb->input['keywords']),
    ]);
    $plugins->run_hooks('search_do_search_end');
    redirect("search.php?action=results&sid=$sid", $lang->search['redirect_searchresults']);

// ─────────────────────────────────────────────────────────────────────────────
// Search form (default)
// ─────────────────────────────────────────────────────────────────────────────
} else {

    $plugins->run_hooks('search_start');
    $srchlist      = make_searchable_forums();
    $prefixselect  = build_prefix_select('all', 'any', 1);
    $maxnamelength = 30;
    $rowspan       = 5;
    $moderator_options = '';
    if ($is_mod) {
        $rowspan += 2;
        eval("\$moderator_options = \"".$templates->get('search_moderator_options')."\";");
    }
    $plugins->run_hooks('search_end');

    $kw_val     = htmlspecialchars_uni($mybb->get_input('keywords'));
    $author_val = htmlspecialchars_uni($mybb->get_input('author'));

    stdhead('Forum Search');
    build_breadcrumb();
    echo search_css();
    ?>
    <div class="container mt-3">

        <div class="sr-hero">
            <h1><i class="fas fa-search" style="color:var(--sr-primary)"></i> Forum Search</h1>
            <p>Find threads, posts and discussions across the forum</p>
        </div>

        <!-- Quick links -->
        <div class="sr-quick-links">
            <a href="search.php?action=getnew" class="sr-quick-link"><i class="fas fa-bolt"></i> New posts</a>
            <a href="search.php?action=getdaily&days=1" class="sr-quick-link"><i class="fas fa-calendar-day"></i> Today</a>
            <a href="search.php?action=getdaily&days=7" class="sr-quick-link"><i class="fas fa-calendar-week"></i> This week</a>
            <?php if ($CURUSER['id']): ?>
            <a href="search.php?action=finduserthreads&uid=<?= (int)$CURUSER['id'] ?>" class="sr-quick-link"><i class="fas fa-user"></i> My threads</a>
            <a href="search.php?action=finduser&uid=<?= (int)$CURUSER['id'] ?>" class="sr-quick-link"><i class="fas fa-comment"></i> My posts</a>
            <?php endif; ?>
        </div>

        <div class="sr-card">
            <form action="search.php" method="post" id="srForm">
                <input type="hidden" name="action" value="do_search">
                <input type="hidden" name="postcode" value="<?= generate_post_check() ?>">

                <!-- Main input -->
                <div class="sr-main-row">
                    <input type="text" name="keywords" id="srKeywords"
                           placeholder="Search keywords… (Ctrl+K)"
                           value="<?= $kw_val ?>" autocomplete="off" maxlength="200">
                    <button type="submit" class="sr-btn sr-btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <button type="button" class="sr-btn sr-btn-ghost" id="srClear">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                </div>

                <!-- Show results as -->
                <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                    <span class="sr-field-label mb-0">Show as:</span>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="showresults" id="srT" value="threads" checked>
                        <label class="form-check-label small fw-semibold" for="srT">Threads</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="showresults" id="srP" value="posts">
                        <label class="form-check-label small fw-semibold" for="srP">Posts</label>
                    </div>
                </div>

                <hr class="sr-divider">

                <!-- Advanced toggle -->
                <span class="sr-adv-toggle" data-bs-toggle="collapse" data-bs-target="#srAdv">
                    <i class="fas fa-sliders-h"></i> Advanced options
                    <i class="fas fa-chevron-down" style="font-size:10px;transition:transform .2s"></i>
                </span>

                <div class="collapse" id="srAdv">
                    <div class="sr-adv-panel">
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="sr-field-label"><i class="fas fa-user me-1"></i>Author</label>
                                <input type="text" name="author" class="sr-field-ctrl"
                                       value="<?= $author_val ?>" placeholder="Username…">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="matchusername" value="1" id="srExact">
                                    <label class="form-check-label small text-muted" for="srExact">Exact match only</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="sr-field-label"><i class="fas fa-search-plus me-1"></i>Search in</label>
                                 
								 
								 
								 <select name="postthread" class="sr-field-ctrl">
    <option value="1">Subject &amp; message</option>
    <option value="0">Subject only</option>
</select>
								 
							  
                            </div>

                            <div class="col-md-4">
                                <label class="sr-field-label"><i class="fas fa-sort me-1"></i>Sort by</label>
                                <select name="sortby" class="sr-field-ctrl">
                                    <option value="lastpost">Last post date</option>
                                    <option value="dateline">Post date</option>
                                    <option value="subject">Subject</option>
                                    <option value="replies">Replies</option>
                                    <option value="views">Views</option>
                                    <option value="starter">Author</option>
                                    <option value="forum">Forum</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="sr-field-label"><i class="fas fa-sort-amount-down me-1"></i>Order</label>
                                <select name="sortordr" class="sr-field-ctrl">
                                    <option value="desc">Newest first</option>
                                    <option value="asc">Oldest first</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="sr-field-label"><i class="fas fa-calendar me-1"></i>Posted within</label>
                                <select name="postdate" class="sr-field-ctrl">
                                    <option value="0">Any time</option>
                                    <option value="1">Yesterday</option>
                                    <option value="7">Last week</option>
                                    <option value="30">Last month</option>
                                    <option value="90">Last 3 months</option>
                                    <option value="365">Last year</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="sr-field-label"><i class="fas fa-reply me-1"></i>Minimum replies</label>
                                <input type="number" name="numreplies" class="sr-field-ctrl" value="0" min="0">
                            </div>

                            <div class="col-md-6">
                               
                                    <?= $srchlist ?>
                               
                                <small class="text-muted">Hold Ctrl to select multiple</small>
                            </div>

                            <?php if ($prefixselect): ?>
                            <div class="col-md-6">
                                <label class="sr-field-label"><i class="fas fa-tag me-1"></i>Thread prefix</label>
                                <?= $prefixselect ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($moderator_options): ?>
                            <div class="col-12">
                                <label class="sr-field-label"><i class="fas fa-shield-alt me-1"></i>Moderator options</label>
                                <?= $moderator_options ?>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <script>
    // Ctrl+K
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('srKeywords')?.focus();
        }
    });
    // Clear button
    document.getElementById('srClear')?.addEventListener('click', () => {
        document.getElementById('srKeywords').value = '';
        document.getElementById('srKeywords').focus();
    });
    // Chevron rotate on collapse
    document.getElementById('srAdv')?.addEventListener('show.bs.collapse', () => {
        document.querySelector('.sr-adv-toggle .fa-chevron-down').style.transform = 'rotate(180deg)';
    });
    document.getElementById('srAdv')?.addEventListener('hide.bs.collapse', () => {
        document.querySelector('.sr-adv-toggle .fa-chevron-down').style.transform = 'rotate(0deg)';
    });
    </script>
    <?php
    stdfoot();
}