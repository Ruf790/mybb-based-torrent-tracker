<?php
declare(strict_types=1);

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'memberlist.php');
define('SCRIPTNAME', 'memberlist.php');
define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);

require_once 'global.php';



require_once INC_PATH . '/functions_multipage.php';

$lang->load('memberlist');
$plugins->run_hooks('memberlist_start');

/**
 * Экранирует только LIKE-wildcard'ы (%, _, \) — для bind-параметров.
 * Кавычки экранировать не нужно, это делает сам биндинг (в отличие
 * от $db->escape_string_like(), который экранирует и их тоже).
 */
function ml_escape_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}


/* ═══════════════════════════════════════════════════════════════════
 *  HELPER FUNCTIONS
 * ═══════════════════════════════════════════════════════════════════ */

function render_header(string $title): void {
    global $SITENAME;
    
    stdhead('5555555555');
    
    echo '<!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($SITENAME) . ' - ' . htmlspecialchars($title) . '</title>
       
        <style>
            :root {
                --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                --ring: #dfe7ff;
            }

            .avatar-ring{position:relative; display:inline-block; padding:6px; border-radius:50%; background:
              conic-gradient(from 140deg,#fff 0 20%, var(--ring) 20% 70%, #fff 70% 100%)}
            .avatar-ring>*{display:block; border-radius:50%}
            
            .user-card {
                border: none;
                border-radius: 16px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                background: white;
                border: 1px solid #e9ecef;
            }
            
            .user-card:hover {
                transform: translateY(-5px);
                border-color: #667eea;
            }
            
            .alphabet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(44px, 1fr));
    gap: 8px;
    max-width: 800px;
    margin: 0 auto;
}

.alphabet-letter {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 44px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1.1rem;
    color: white;
    text-decoration: none;
    background: var(--bs-primary);
    transition: all 0.2s ease;
    text-transform: uppercase;
}

.alphabet-letter:hover {
    transform: translateY(-2px);
    background: var(--bs-primary-dark, #0b5ed7);
    color: white;
}
            
            .stat-badge {
                background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.85rem;
            }
            
            .card {
                border: 1px solid #e9ecef;
                border-radius: 16px;
            }
            
            .card-header {
                background: transparent;
            }
            
            @media (max-width: 768px) {
                .alphabet-grid { grid-template-columns: repeat(7, 1fr); gap: 5px; }
                .alphabet-letter { height: 38px; font-size: 0.9rem; }
            }
        </style>
    </head>
    <body>
        <div class="container py-4">';
}

function render_footer(): void {
    echo '</div>
    </body>
    </html>
    ' . stdfoot() . '
    ';
}

/* ═══════════════════════════════════════════════════════════════════
 *  ACTION: search page
 * ═══════════════════════════════════════════════════════════════════ */

if ($mybb->get_input('action') === 'search') {
    $plugins->run_hooks('memberlist_search');
    
    render_header($lang->memberlist['member_list']);
    
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border rounded-4">
                <div class="card-header bg-transparent border-0 pt-4">
                    <h3 class="text-center mb-0">
                        <i class="fas fa-search text-primary me-2"></i>
                        <?= $lang->memberlist['search_members'] ?>
                    </h3>
                </div>
                <div class="card-body p-4">
                    <form method="post" action="memberlist.php">
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= $lang->memberlist['username'] ?></label>
                            <input type="text" class="form-control form-control-lg rounded-3" 
                                   name="username" placeholder="Enter username...">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Match type</label>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="username_match" 
                                           id="m_exact" value="exact" checked>
                                    <label class="form-check-label" for="m_exact">
                                        <i class="fas fa-equals me-1"></i>Exact match
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="username_match" 
                                           id="m_begins" value="begins">
                                    <label class="form-check-label" for="m_begins">
                                        <i class="fas fa-arrow-right me-1"></i>Begins with
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="username_match" 
                                           id="m_contains" value="contains">
                                    <label class="form-check-label" for="m_contains">
                                        <i class="fas fa-asterisk me-1"></i>Contains
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= $lang->memberlist['website'] ?></label>
                            <input type="text" class="form-control form-control-lg rounded-3" 
                                   name="website" placeholder="Enter website...">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-3">
                            <i class="fas fa-search me-2"></i><?= $lang->memberlist['search'] ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    render_footer();
    exit;
}

/* ═══════════════════════════════════════════════════════════════════
 *  MAIN LIST
 * ═══════════════════════════════════════════════════════════════════ */

// Sort handling
$sort_options = [
    'username' => 'u.username',
    'added' => 'u.added',
    'lastvisit' => 'u.lastactive',
    'postnum' => 'u.postnum',
    'threadnum' => 'u.threadnum'
];

$sort_input = strtolower($mybb->get_input('sort') ?: 'username');
$sort_field = $sort_options[$sort_input] ?? 'u.username';
$sort_display = $sort_input === 'added' ? 'regdate' : $sort_input;

$order_input = strtolower($mybb->get_input('order') ?: 'ascending');
$sort_order = $order_input === 'descending' ? 'DESC' : 'ASC';

$per_page = $mybb->get_input('perpage', MyBB::INPUT_INT);
if ($per_page <= 0 || $per_page > 500) $per_page = 20;

// Search query building
$search_conditions = ['1=1'];
$search_params = [];
$search_url_params = [];

// Letter filter
if (isset($mybb->input['letter'])) {
    $letter = $mybb->get_input('letter');
    if ($letter == -1) {
        $search_conditions[] = "u.username NOT REGEXP('[a-zA-Z]')";
        $search_url_params['letter'] = -1;
    } elseif (strlen($letter) == 1) {
        $search_conditions[] = "u.username LIKE ?";
        $search_params[] = ml_escape_like($letter) . '%';
        $search_url_params['letter'] = $letter;
    }
}

// Username filter
$search_username = htmlspecialchars_uni(trim($mybb->get_input('username')));
if ($search_username !== '') {
    $match = $mybb->get_input('username_match');
    $search_url_params['username'] = $search_username;
    
    if ($match === 'begins') {
        $search_conditions[] = "u.username LIKE ?";
        $search_params[] = ml_escape_like($search_username) . '%';
        $search_url_params['username_match'] = 'begins';
    } elseif ($match === 'contains') {
        $search_conditions[] = "u.username LIKE ?";
        $search_params[] = '%' . ml_escape_like($search_username) . '%';
        $search_url_params['username_match'] = 'contains';
    } else {
        $search_conditions[] = "LOWER(u.username)=?";
        $search_params[] = my_strtolower($search_username);
    }
}

// Website filter
$search_website = htmlspecialchars_uni(trim($mybb->get_input('website')));
if ($search_website !== '') {
    $search_conditions[] = "u.website LIKE ?";
    $search_params[] = '%' . ml_escape_like($search_website) . '%';
    $search_url_params['website'] = $search_website;
}

$search_query = implode(' AND ', $search_conditions);

// Pagination
$count_query = $db->sql_query_prepared("SELECT COUNT(*) AS users FROM users u WHERE {$search_query}", $search_params);
$num_users = $count_query ? (int)$db->fetch_field($count_query, 'users') : 0;

$page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
$pages = (int)ceil($num_users / $per_page);
if ($page > $pages) $page = 1;
$start = ($page - 1) * $per_page;

$base_url = "memberlist.php?sort={$sort_input}&order={$order_input}&perpage={$per_page}";
foreach ($search_url_params as $key => $value) {
    $base_url .= "&{$key}=" . urlencode($value);
}
$multipage = multipage($num_users, $per_page, $page, htmlspecialchars_uni($base_url));



$users_html = '';
$query = $db->sql_query_prepared("
    SELECT u.*
    FROM users u
    WHERE {$search_query}
    ORDER BY {$sort_field} {$sort_order}
    LIMIT ?, ?
", [...$search_params, $start, $per_page]);

while ($query && ($user = $db->fetch_array($query))) {
    $user = $plugins->run_hooks('memberlist_user', $user);
    
    // Format user data
    $username_plain = htmlspecialchars_uni($user['username']);
    $username_formatted = format_name($username_plain, $user['usergroup'], $user['displaygroup']);
    $profile_link = build_profile_link($username_formatted, $user['id']);
    
    // Usergroup and usertitle
    $usergroup = usergroup_permissions($user['usergroup'] ?: 1);
    if (!$user['displaygroup']) $user['displaygroup'] = $user['usergroup'];
    $display_group = usergroup_displaygroup((int)$user['displaygroup']);
    if (is_array($display_group)) $usergroup = array_merge($usergroup, $display_group);
    
    $usertitle = htmlspecialchars_uni($usergroup['title'] ?? $lang->memberlist['member']);
    
    // Avatar
    $useravatar = format_avatar($user['avatar'], $user['avatardimensions']);
    $avatarClass = !empty($useravatar['is_placeholder']) ? 'avatar-ring img-fluid' : 'rounded img-fluid';
    $avatar_profile_url = get_profile_link($user['id']);

    $avatar_html = '
        <div class="d-none d-sm-none d-md-none d-lg-block d-xxl-block d-xxl-block">
            <div class="author_avatar"><a href="'.$avatar_profile_url.'"><img class="'.$avatarClass.'" style="width: 80px; height: 80px; object-fit: cover;" src="'.$useravatar['image'].'" alt="'.$username_plain.'" /></a></div>
        </div>
        <div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none">
            <div class="author_avatar"><a href="'.$avatar_profile_url.'"><img class="'.$avatarClass.'" style="width: 30px; height: 30px; object-fit: cover;" src="'.$useravatar['image'].'" alt="'.$username_plain.'" /></a></div>
        </div>';
    
    // Last visit
    $last_seen = max($user['lastactive'], $user['lastvisit']);
    if (!$last_seen) {
        $lastvisit = '<span class="text-muted"><i class="far fa-clock me-1"></i>' . $lang->memberlist['lastvisit_never'] . '</span>';
    } elseif ($user['invisible'] == 1 && $usergroups['canviewwolinvis'] != 1 && $user['id'] != $CURUSER['id']) {
        $lastvisit = '<span class="text-muted"><i class="fas fa-eye-slash me-1"></i>' . $lang->memberlist['lastvisit_hidden'] . '</span>';
    } else {
        $lastvisit = '<span><i class="fas fa-circle text-success me-1" style="font-size: 0.7rem;"></i>' . my_datee('relative', $last_seen) . '</span>';
    }
    
    $added = my_datee('relative', $user['added']);
    $postnum = ts_nf($user['postnum']);
    $threadnum = ts_nf($user['threadnum']);
    
    $users_html .= <<<HTML
    <div class="col-lg-6 col-md-6 col-12">
        <div class="user-card p-3">
            <div class="d-flex gap-3">
                <div class="flex-shrink-0">
                    {$avatar_html}
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-1">{$profile_link}</h5>
                    <div class="text-muted small mb-2">{$usertitle}</div>
                    <div class="d-flex gap-2 mb-2 flex-wrap">
                        <span class="stat-badge">
                            <i class="fas fa-comment me-1"></i>{$postnum} {$lang->memberlist['posts']}
                        </span>
                        <span class="stat-badge">
                            <i class="fas fa-file-alt me-1"></i>{$threadnum} {$lang->memberlist['threads']}
                        </span>
                    </div>
                    <div class="small text-muted">
                        <div><i class="fas fa-calendar-alt me-1"></i>{$lang->memberlist['joined']}: {$added}</div>
                        <div class="mt-1">{$lastvisit}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
HTML;
}

if (!$users_html) {
    $users_html = '<div class="col-12">
		<div class="text-center py-5">
                <i class="fa-solid fa-users fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">' . ($lang->memberlist['no_members'] ?? 'No members found.') . '</h5>
              </div>
    </div>';
}

$plugins->run_hooks('memberlist_end');

/* ═══════════════════════════════════════════════════════════════════
 *  OUTPUT
 * ═══════════════════════════════════════════════════════════════════ */

render_header($lang->memberlist['member_list']);

?>

<div class="row">
    <div class="col-12">
        
        <!-- Stats summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border rounded-4 text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h3 class="mb-0"><?= ts_nf($num_users) ?></h3>
                        <small class="text-muted">Total Members</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border rounded-4 text-center">
                    <div class="card-body">
                        <i class="fas fa-calendar-week fa-2x text-success mb-2"></i>
                        <h3 class="mb-0"><?= ts_nf($pages) ?></h3>
                        <small class="text-muted">Total Pages</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border rounded-4 text-center">
                    <div class="card-body">
                        <i class="fas fa-eye fa-2x text-info mb-2"></i>
                        <h3 class="mb-0"><?= $per_page ?></h3>
                        <small class="text-muted">Per Page</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border rounded-4 text-center">
                    <div class="card-body">
                        <i class="fas fa-sort-amount-down fa-2x text-warning mb-2"></i>
                        <h6 class="mb-0 mt-2"><?= ucfirst($sort_input) ?></h6>
                        <small class="text-muted">Sorted by</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pagination top -->
        <?php if ($multipage): ?>
            <div class="mb-4"><?= $multipage ?></div>
        <?php endif; ?>
        
        <!-- Alphabet navigation -->
        <div class="card border rounded-4 mb-4 overflow-hidden">
            <div class="card-header bg-transparent border-0 pt-3">
                <h6 class="mb-0 text-center">
                    <i class="fas fa-filter me-2"></i>Browse by letter
                </h6>
            </div>
            <div class="card-body pt-0 pb-3">
                <div class="alphabet-grid">
                    <a href="memberlist.php?letter=-1" class="alphabet-letter" title="Special characters">
                        <i class="fas fa-hashtag"></i>
                    </a>
                    <?php foreach (range('a', 'z') as $letter): ?>
                        <a href="memberlist.php?username_match=begins&username=<?= $letter ?>" 
                           class="alphabet-letter"><?= strtoupper($letter) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Users grid -->
        <div class="row g-3 mb-4">
            <?= $users_html ?>
        </div>
        
        <!-- Pagination bottom -->
        <?php if ($multipage): ?>
            <div class="mb-4"><?= $multipage ?></div>
        <?php endif; ?>
        
        <!-- Search/Sort panel -->
        <div class="card border rounded-4">
            <div class="card-header bg-transparent border-0 pt-3">
                <h6 class="mb-0">
                    <i class="fas fa-search me-2 text-primary"></i>
                    <?= $lang->memberlist['search_members'] ?>
                </h6>
            </div>
            <div class="card-body">
                <form method="post" action="memberlist.php" class="needs-validation">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">
                                <i class="fas fa-user me-1"></i><?= $lang->memberlist['username'] ?>
                            </label>
                            <input type="text" class="form-control rounded-3" name="username" 
                                   value="<?= htmlspecialchars($search_username) ?>"
                                   placeholder="Enter username...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">
                                <i class="fas fa-filter me-1"></i>Match
                            </label>
                            <select name="username_match" class="form-select rounded-3">
                                <option value="exact">Exact match</option>
                                <option value="begins">Begins with</option>
                                <option value="contains">Contains</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">
                                <i class="fas fa-globe me-1"></i><?= $lang->memberlist['website'] ?>
                            </label>
                            <input type="text" class="form-control rounded-3" name="website"
                                   value="<?= htmlspecialchars($search_website) ?>"
                                   placeholder="Website...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">
                                <i class="fas fa-eye me-1"></i>Per page
                            </label>
                            <input type="number" class="form-control rounded-3" name="perpage"
                                   value="<?= (int)$per_page ?>" min="5" max="500">
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">
                                <i class="fas fa-sort me-1"></i><?= $lang->memberlist['sort_by'] ?>
                            </label>
                            <select name="sort" class="form-select rounded-3">
                                <option value="username"<?= $sort_display === 'username' ? ' selected' : '' ?>><?= $lang->memberlist['sort_by_username'] ?></option>
                                <option value="added"<?= $sort_display === 'regdate' ? ' selected' : '' ?>><?= $lang->memberlist['sort_by_regdate'] ?></option>
                                <option value="lastvisit"<?= $sort_display === 'lastvisit' ? ' selected' : '' ?>><?= $lang->memberlist['sort_by_lastvisit'] ?></option>
                                <option value="postnum"<?= $sort_display === 'postnum' ? ' selected' : '' ?>><?= $lang->memberlist['sort_by_posts'] ?></option>
                                <option value="threadnum"<?= $sort_display === 'threadnum' ? ' selected' : '' ?>><?= $lang->memberlist['sort_by_threads'] ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">
                                <i class="fas fa-arrow-up me-1"></i>Order
                            </label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="order" 
                                           id="o_asc" value="ascending"<?= $order_input === 'ascending' ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="o_asc">
                                        <i class="fas fa-arrow-up me-1"></i>Ascending
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="order" 
                                           id="o_desc" value="descending"<?= $order_input === 'descending' ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="o_desc">
                                        <i class="fas fa-arrow-down me-1"></i>Descending
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 rounded-3">
                                <i class="fas fa-search me-2"></i><?= $lang->memberlist['search'] ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-end mt-3">
                        <a href="memberlist.php?action=search" class="text-decoration-none small">
                            <i class="fas fa-sliders-h me-1"></i><?= $lang->memberlist['advanced_search'] ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>

<?php
render_footer();
?>