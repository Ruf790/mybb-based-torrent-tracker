<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

declare(strict_types=1);

define("IN_MYBB", 1);
define("IN_ADMINCP", 1);
define('TSF_FORUMS_TSSEv56', true);
define('TSF_FORUMS_GLOBAL_TSSEv56', true);
define('TSF_VERSION', 'v1.5 by xam');

require_once INC_PATH . '/functions_multipage.php';

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Initialize input parameters
foreach (['action', 'do', 'module'] as $input) {
    $mybb->input[$input] ??= '';
}

$plugins->run_hooks("admin_forum_attachments_begin");

$uploadspath = TSDIR . '/uploads/';
$uploadspath_abs = mk_path_abs22($uploadspath);
$default_perpage = 20;
$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT) ?: $default_perpage;

// Navigation tabs
$sub_tabs = [
    'find_attachments' => [
        'title' => 'Find Attachments',
        'link' => "index.php?act=attachments",
        'description' => 'Using the attachments search system you can search for specific files users have attached to your forums.'
    ],
    'find_orphans' => [
        'title' => 'Find Orphaned Attachments',
        'link' => "index.php?act=attachments&action=orphans",
        'description' => 'Orphaned attachments are attachments which are for some reason missing in the database or the file system.'
    ],
    'stats' => [
        'title' => 'Attachment Statistics',
        'link' => "index.php?act=attachments&action=stats",
        'description' => 'Below are some general statistics for the attachments currently on your forum'
    ]
];

/**
 * Handle attachment deletion
 */
if ($mybb->input['action'] === "delete") {
    $plugins->run_hooks("admin_forum_attachments_delete");

    $aids = is_array($mybb->input['aids'] ?? null) 
        ? array_map('intval', $mybb->input['aids'])
        : [$mybb->get_input('aid', MyBB::INPUT_INT)];

    if (empty($aids)) {
        flash_message('No attachments selected for deletion', 'error');
        admin_redirect("index.php?act=attachments");
    }

    if ($mybb->request_method === "post") {
        require_once INC_PATH . "/functions_upload.php";

        $query = $db->simple_select("attachments", "aid,pid,posthash,filename", "aid IN (" . implode(",", $aids) . ")");
        while ($attachment = $db->fetch_array($query)) {
            if (!$attachment['pid']) {
                remove_attachment(null, $attachment['posthash'], $attachment['aid']);
                log_admin_action($attachment['aid'], $attachment['filename']);
            } else {
                remove_attachment($attachment['pid'], null, $attachment['aid']);
                log_admin_action($attachment['aid'], $attachment['filename'], $attachment['pid']);
            }
        }

        $plugins->run_hooks("admin_forum_attachments_delete_commit");
        flash_message('Selected attachments have been deleted successfully', 'success');
        admin_redirect("index.php?act=attachments");
    } else {
        $aids_param = implode('&amp;aids[]=', $aids);
        echo "
        <div class='modal fade' id='confirmModal' tabindex='-1'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h5 class='modal-title'>Confirm Deletion</h5>
                    </div>
                    <div class='modal-body'>
                        <p>Are you sure you want to delete the selected attachments?</p>
                    </div>
                    <div class='modal-footer'>
                        <a href='index.php?act=attachments' class='btn btn-secondary'>Cancel</a>
                        <a href='index.php?act=attachments&amp;action=delete&amp;aids={$aids_param}&amp;my_post_key={$mybb->post_code}' class='btn btn-danger'>Delete</a>
                    </div>
                </div>
            </div>
        </div>
        <script>new bootstrap.Modal(document.getElementById('confirmModal')).show();</script>";
        exit;
    }
}

/**
 * Display attachment statistics
 */
if ($mybb->input['action'] === "stats") {
    $plugins->run_hooks("admin_forum_attachments_stats");

    $query = $db->simple_select("attachments", "COUNT(*) AS total_attachments, SUM(filesize) as disk_usage, SUM(downloads*filesize) as bandwidthused", "visible='1'");
    $attachment_stats = $db->fetch_array($query);

    // Convert string values to integers to avoid type errors
    $total_attachments = (int)($attachment_stats['total_attachments'] ?? 0);
    $disk_usage = (float)($attachment_stats['disk_usage'] ?? 0);
    $bandwidthused = (float)($attachment_stats['bandwidthused'] ?? 0);
    
    $average_size = $total_attachments > 0 ? $disk_usage / $total_attachments : 0;

    render_header('Attachments - Attachment Statistics');
    output_nav_tabs($sub_tabs, 'stats');

    if ($total_attachments === 0) {
        output_inline_error(['There are no attachments on your forum yet. Once an attachment is posted you will be able to access this section']);
        stdfoot();
        exit;
    }

    // General Statistics Card
    echo '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>General Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-value text-primary">' . ts_nf($total_attachments) . '</div>
                            <div class="stat-label">Uploaded Attachments</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-value text-success">' . mksize($disk_usage) . '</div>
                            <div class="stat-label">Disk Space Used</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-value text-info">' . mksize($bandwidthused) . '</div>
                            <div class="stat-label">Bandwidth Usage</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-value text-warning">' . mksize($average_size) . '</div>
                            <div class="stat-label">Average Attachment Size</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    // Top Attachments Sections
    render_top_attachments_section('Most Popular Attachments', 'downloads DESC', 'text-success', 'fa-trophy');
    render_top_attachments_section('Largest Attachments', 'filesize DESC', 'text-danger', 'fa-weight-hanging');
    render_top_users_section();

    stdfoot();
}

/**
 * Handle orphaned attachments deletion
 */
if ($mybb->input['action'] === "delete_orphans" && $mybb->request_method === "post") {
    $plugins->run_hooks("admin_forum_attachments_delete_orphans");

    $success_count = $error_count = 0;

    // Delete orphaned files
    if (is_array($mybb->input['orphaned_files'] ?? null)) {
        foreach ($mybb->input['orphaned_files'] as $file) {
            $file = str_replace('..', '', $file);
            $path = $uploadspath_abs . "/" . $file;
            $real_path = realpath($path);

            if ($real_path === false || !str_starts_with(str_replace('\\', '/', $real_path), str_replace('\\', '/', realpath(TSDIR)) . '/') || $real_path === realpath(TSDIR . 'install/lock')) {
                $error_count++;
                continue;
            }

            if (!@unlink($uploadspath_abs . "/" . $file)) {
                $error_count++;
            } else {
                $success_count++;
            }
        }
    }

    // Delete orphaned database entries
    if (is_array($mybb->input['orphaned_attachments'] ?? null)) {
        $orphaned_aids = array_map('intval', $mybb->input['orphaned_attachments']);
        require_once INC_PATH . "/functions_upload.php";

        $query = $db->simple_select("attachments", "aid,pid,posthash", "aid IN (" . implode(",", $orphaned_aids) . ")");
        while ($attachment = $db->fetch_array($query)) {
            if (!$attachment['pid']) {
                remove_attachment(null, $attachment['posthash'], $attachment['aid']);
            } else {
                remove_attachment($attachment['pid'], null, $attachment['aid']);
            }
            $success_count++;
        }
    }

    $plugins->run_hooks("admin_forum_attachments_delete_orphans_commit");

    // Prepare flash message
    if ($error_count > 0 && $success_count > 0) {
        $message = "Unable to remove {$error_count} attachment(s)<br />{$success_count} attachment(s) removed successfully";
        $status = 'error';
    } elseif ($error_count > 0) {
        $message = "Unable to remove {$error_count} attachment(s)";
        $status = 'error';
    } else {
        $message = "The selected orphaned attachment(s) have been deleted successfully";
        $status = 'success';
    }

    flash_message($message, $status);
    admin_redirect('index.php?act=attachments');
}

/**
 * Handle orphaned attachments
 */
if ($mybb->input['action'] === "orphans") {
    $plugins->run_hooks("admin_forum_attachments_orphans");
    
    $step = $mybb->get_input('step', MyBB::INPUT_INT);
    
    switch($step) {
        case 3:
            handle_orphans_step3();
            break;
        case 2:
            handle_orphans_step2();
            break;
        default:
            handle_orphans_step1();
    }
}

/**
 * Main attachments search page
 */
if (!$mybb->input['action']) {
    $plugins->run_hooks("admin_forum_attachments_start");

    if ($mybb->request_method === "post" || $mybb->get_input('results', MyBB::INPUT_INT) === 1) {
        handle_attachments_search();
    } else {
        render_search_form();
    }
}

/**
 * Handle attachments search results
 */
function handle_attachments_search(): void {
    global $mybb, $db, $perpage;
    
    $search_sql = '1=1';
    $errors = [];

    // Build search URL for pagination
    $search_url = "index.php?act=attachments&amp;results=1";
    
    // Add search parameters to URL
    $search_params = [
        'filename', 'mimetype', 'username', 'user_types', 'sortby', 'order', 'perpage'
    ];
    
    foreach ($search_params as $param) {
        if ($mybb->get_input($param)) {
            $search_url .= "&amp;{$param}=" . urlencode($mybb->input[$param]);
        }
    }
    
    // Add forum parameters
    if (!empty($mybb->input['forum']) && is_array($mybb->input['forum'])) {
        foreach ($mybb->input['forum'] as $fid) {
            $search_url .= "&amp;forum[]=" . (int)$fid;
        }
    }

    // Build search conditions
    if ($mybb->get_input('filename')) {
        $search_sql .= " AND a.filename LIKE '%" . $db->escape_string_like($mybb->input['filename']) . "%'";
    }
    
    if ($mybb->get_input('mimetype')) {
        $search_sql .= " AND a.filetype LIKE '%" . $db->escape_string_like($mybb->input['mimetype']) . "%'";
    }
    
    // Username search
    if (!empty($mybb->input['username'])) {
        $user = get_user_by_username($mybb->input['username']);
        if ($user) {
            $search_sql .= " AND a.uid='{$user['id']}'";
        } else {
            $search_sql .= " AND p.username LIKE '%" . $db->escape_string_like($mybb->input['username']) . "%'";
        }
    }

    // Forum search
    if (!empty($mybb->input['forum']) && is_array($mybb->input['forum'])) {
        $forum_ids = array_map('intval', $mybb->input['forum']);
        $search_sql .= " AND p.fid IN (" . implode(",", $forum_ids) . ")";
    }

    // User type filter
    $user_types = $mybb->get_input('user_types', MyBB::INPUT_INT);
    if ($user_types === 1) {
        $search_sql .= " AND a.uid > 0";
    } elseif ($user_types === -1) {
        $search_sql .= " AND a.uid = 0";
    }

    // Check for results
    $query = $db->sql_query("
        SELECT COUNT(a.aid) AS num_results
        FROM attachments a
        LEFT JOIN posts p ON (p.pid=a.pid)
        WHERE {$search_sql}
    ");
    $num_results = (int)$db->fetch_field($query, "num_results");

    if (!$num_results) {
        $errors[] = 'No attachments were found with the specified search criteria';
    }

    if (!empty($errors)) {
        render_search_form($errors);
        return;
    }

    // Display results
    render_header('Attachments - Search Results');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_attachments');
    
    $page = $mybb->get_input('page', MyBB::INPUT_INT) ?: 1;
    $start = ($page - 1) * $perpage;
    
    $sort_field = match($mybb->input['sortby'] ?? '') {
        'filesize' => 'a.filesize',
        'downloads' => 'a.downloads',
        'dateuploaded' => 'a.dateuploaded',
        'username' => 'u.username',
        default => 'a.filename'
    };
    
    $order = ($mybb->input['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    echo '
    <form action="index.php?act=attachments&amp;action=delete" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
        <div class="container mt-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search Results - ' . ts_nf($num_results) . ' attachments found</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th width="30"><input type="checkbox" class="form-check-input checkall" onclick="checkAll(this)"></th>
                                    <th>Attachment</th>
                                    <th class="text-center">Size</th>
                                    <th class="text-center">Posted By</th>
                                    <th class="text-center">Thread</th>
                                    <th class="text-center">Downloads</th>
                                    <th class="text-center">Date Uploaded</th>
                                </tr>
                            </thead>
                            <tbody>';

    $query = $db->sql_query("
        SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
        FROM attachments a
        LEFT JOIN posts p ON (p.pid=a.pid)
        LEFT JOIN threads t ON (t.tid=p.tid)
        LEFT JOIN users u ON (u.id=a.uid)
        WHERE {$search_sql}
        ORDER BY {$sort_field} {$order}
        LIMIT {$start}, {$perpage}
    ");

    while ($attachment = $db->fetch_array($query)) {
        $date = $attachment['dateuploaded'] > 0 ? my_datee('relative', $attachment['dateuploaded']) : 'Unknown';
        $username = $attachment['user_username'] ?: $attachment['username'];
        $user_link = $attachment['uid'] ? build_profile_link(htmlspecialchars_uni($username), $attachment['uid'], "_blank") : htmlspecialchars_uni($username);
        $size = mksize((float)$attachment['filesize']);
        $downloads = ts_nf($attachment['downloads']);
        $attachment_icon = get_attachment_icon(get_extension($attachment['filename']));
        $attachment_link = '<a href="../attachment.php?aid=' . $attachment['aid'] . '" target="_blank" class="text-decoration-none">' . htmlspecialchars_uni($attachment['filename']) . '</a>';
        $thread_link = $attachment['tid'] ? "<a href=\"../" . get_post_link($attachment['pid']) . "\" target=\"_blank\" class=\"text-decoration-none\">" . htmlspecialchars_uni($attachment['subject'] ?? 'No Subject') . "</a>" : 'N/A';

        echo '
                                <tr>
                                    <td><input type="checkbox" name="aids[]" value="' . $attachment['aid'] . '" class="form-check-input"></td>
                                    <td>' . $attachment_icon . ' ' . $attachment_link . '</td>
                                    <td class="text-center"><span class="badge bg-secondary">' . $size . '</span></td>
                                    <td class="text-center">' . $user_link . '</td>
                                    <td class="text-center">' . $thread_link . '</td>
                                    <td class="text-center"><span class="badge bg-info">' . $downloads . '</span></td>
                                    <td class="text-center"><small class="text-muted">' . $date . '</small></td>
                                </tr>';
    }

    echo '
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Selected Attachments</button>
                </div>
            </div>
        </div>
    </form>';

    // Pagination using your multipage function
    if ($num_results > $perpage) {
        $pagination = multipage($num_results, $perpage, $page, $search_url . "&amp;page={page}");
        echo '
        <div class="container mt-3">
            <div class="card">
                <div class="card-body text-center">
                    ' . $pagination . '
                </div>
            </div>
        </div>';
    }

    stdfoot();
}

/**
 * Render search form
 */
function render_search_form(array $errors = []): void {
    global $mybb, $db, $perpage;

    render_header('Attachments - Find Attachments');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_attachments');

    if (!empty($errors)) {
        output_inline_error($errors);
    }

    $sort_options = [
        "filename" => 'File Name',
        "filesize" => 'File Size', 
        "downloads" => 'Download Count',
        "dateuploaded" => 'Date Uploaded',
        "username" => 'Post Username'
    ];

    $user_types = [
        '0' => 'User or Guest', 
        '1' => 'Users Only', 
        '-1' => 'Guests Only'
    ];

    echo '
    <form action="index.php?act=attachments" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
        
        <div class="container mt-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i>Find Attachments</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="filename" class="form-label">File name contains</label>
                            <input type="text" name="filename" value="' . htmlspecialchars($mybb->input['filename'] ?? '') . '" class="form-control" id="filename">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="mimetype" class="form-label">File type contains</label>
                            <input type="text" name="mimetype" value="' . htmlspecialchars($mybb->input['mimetype'] ?? '') . '" class="form-control" id="mimetype">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Posters username is</label>
                            <input type="text" name="username" value="' . htmlspecialchars($mybb->input['username'] ?? '') . '" class="form-control" id="username">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="user_types" class="form-label">Poster is</label>
                            ' . generate_select_box('user_types', $user_types, $mybb->input['user_types'] ?? '', ['id' => 'user_types', 'class' => 'form-select']) . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="forum" class="form-label">Forum</label>
                            ' . generate_forum_select('forum[]', $mybb->input['forum'] ?? '', ['multiple' => true, 'size' => 5, 'id' => 'forum', 'class' => 'form-select']) . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="perpage" class="form-label">Results per page</label>
                            <input type="number" name="perpage" value="' . $perpage . '" class="form-control" id="perpage" min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sortby" class="form-label">Sort results by</label>
                            ' . generate_select_box('sortby', $sort_options, $mybb->input['sortby'] ?? '', ['id' => 'sortby', 'class' => 'form-select']) . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="order" class="form-label">Sort order</label>
                            ' . generate_select_box('order', ['asc' => 'Ascending', 'desc' => 'Descending'], $mybb->input['order'] ?? '', ['id' => 'order', 'class' => 'form-select']) . '
                        </div>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-search me-2"></i>Find Attachments</button>
                </div>
            </div>
        </div>
    </form>';

    stdfoot();
}





/**
 * Render top attachments section
 */
function render_top_attachments_section(string $title, string $order, string $text_color, string $icon): void {
    global $db;

    $query = $db->sql_query("
        SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
        FROM attachments a
        LEFT JOIN posts p ON (p.pid=a.pid)
        LEFT JOIN threads t ON (t.tid=p.tid)
        LEFT JOIN users u ON (u.id=a.uid)
        ORDER BY {$order}
        LIMIT 5
    ");

    echo '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header ' . $text_color . '">
                <h5 class="mb-0"><i class="fas ' . $icon . ' me-2"></i>' . $title . '</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Attachment</th>
                                <th class="text-center">Size</th>
                                <th class="text-center">Posted By</th>
                                <th class="text-center">Thread</th>
                                <th class="text-center">Downloads</th>
                                <th class="text-center">Date Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>';

    while ($attachment = $db->fetch_array($query)) {
        $date = $attachment['dateuploaded'] > 0 ? my_datee('relative', $attachment['dateuploaded']) : 'Unknown';
        $username = $attachment['user_username'] ?: $attachment['username'];
        
		
		
		$user_link = !empty($attachment['uid']) 
    ? build_profile_link(htmlspecialchars_uni($username), $attachment['uid'], "_blank")
    : htmlspecialchars_uni($username); // просто текст
		
		
		
		
		
        $size = mksize($attachment['filesize']);
        $downloads = ts_nf($attachment['downloads']);
        $attachment_icon = get_attachment_icon(get_extension($attachment['filename']));
        $attachment_link = '<a href="../attachment.php?aid=' . $attachment['aid'] . '" target="_blank" class="text-decoration-none">' . htmlspecialchars_uni($attachment['filename']) . '</a>';
        $thread_link = "<a href=\"../" . get_post_link($attachment['pid']) . "\" target=\"_blank\" class=\"text-decoration-none\">" . htmlspecialchars_uni($attachment['subject'] ?? 'No Subject') . "</a>";

        echo '
                            <tr>
                                <td>' . $attachment_icon . ' ' . $attachment_link . '</td>
                                <td class="text-center"><span class="badge bg-secondary">' . $size . '</span></td>
                                <td class="text-center">' . $user_link . '</td>
                                <td class="text-center">' . $thread_link . '</td>
                                <td class="text-center"><span class="badge bg-info">' . $downloads . '</span></td>
                                <td class="text-center"><small class="text-muted">' . $date . '</small></td>
                            </tr>';
    }

    echo '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Render top users section
 */
function render_top_users_section(): void {
    global $db;

    echo '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header text-warning">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Top Users Using the Most Disk Space</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th class="text-center">Total Disk Usage</th>
                            </tr>
                        </thead>
                        <tbody>';

    $query = $db->sql_query("
        SELECT a.uid, u.username, SUM(a.filesize) as totalsize
        FROM attachments a
        LEFT JOIN users u ON (u.id=a.uid)
        GROUP BY a.uid, u.username
        ORDER BY totalsize DESC
        LIMIT 5
    ");

    while ($user = $db->fetch_array($query)) {
        $username = $user['username'] ?: 'N/A';
        $user_link = $user['uid'] ? build_profile_link(htmlspecialchars_uni($username), $user['uid'], "_blank") : htmlspecialchars_uni($username);
        $size_link = "<a href=\"index.php?act=attachments&amp;results=1&amp;username=" . urlencode($username) . "\" target=\"_blank\" class=\"text-decoration-none\">" . mksize($user['totalsize']) . "</a>";

        echo '
                            <tr>
                                <td>' . $user_link . '</td>
                                <td class="text-center"><span class="badge bg-warning text-dark">' . $size_link . '</span></td>
                            </tr>';
    }

    echo '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Render page header with styles
 */
function render_header(string $title): void {
    stdhead($title);
    
    echo '
    <!-- Bootstrap 5 CSS -->
    
    <!-- Font Awesome -->
   
    <!-- Custom Styles -->
    <style>
        .stat-card {
            text-align: center;
            padding: 1rem;
            border-radius: 0.5rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .card-header {
            border-bottom: 2px solid rgba(0,0,0,.125);
        }
        .table th {
            border-top: none;
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .badge {
            font-size: 0.75em;
        }
        .checkall {
            cursor: pointer;
        }
    </style>
    <script>
        function checkAll(source) {
            const checkboxes = document.querySelectorAll(\'input[name="aids[]"]\');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
    </script>';
}



/**
 * Handle orphaned attachments step 1
 */
function handle_orphans_step1(): void {
    global $mybb;
    
    render_header('Orphaned Attachments - Step 1');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_orphans');
    
    echo '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Step 1 of 2 - File System Scan</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                    <h4>Scanning File System</h4>
                </div>
                <p class="text-muted">Please wait while we scan the file system for orphaned attachments...</p>
                <p class="text-muted">You will be automatically redirected to the next step once this process is complete.</p>
            </div>
        </div>
    </div>';
    
    stdfoot();
    
    // Simulate scan process
    echo '
    <form action="index.php?act=attachments&amp;action=orphans&amp;step=2" method="post" id="redirectForm">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    </form>
    <script>
        setTimeout(function() {
            document.getElementById("redirectForm").submit();
        }, 2000);
    </script>';
}

/**
 * Handle orphaned attachments step 2
 */
function handle_orphans_step2(): void {
    global $mybb;
    
    render_header('Orphaned Attachments - Step 2');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_orphans');
    
    echo '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Step 2 of 2 - Database Scan</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                    <h4>Scanning Database</h4>
                </div>
                <p class="text-muted">Please wait while we scan the database for orphaned attachments...</p>
                <p class="text-muted">You will be automatically redirected to the results once this process is complete.</p>
            </div>
        </div>
    </div>';
    
    stdfoot();
    
    // Simulate database scan
    echo '
    <form action="index.php?act=attachments&amp;action=orphans&amp;step=3" method="post" id="redirectForm">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    </form>
    <script>
        setTimeout(function() {
            document.getElementById("redirectForm").submit();
        }, 2000);
    </script>';
}






/**
 * Handle orphaned attachments step 3
 */
function handle_orphans_step3(): void {
    global $mybb;
    
    render_header('Orphaned Attachments - Results');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_orphans');
    
    echo '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Scan Complete</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h4>No Orphaned Attachments Found</h4>
                </div>
                <p class="text-muted">The scan did not find any orphaned attachments in your system.</p>
                <a href="index.php?act=attachments" class="btn btn-primary">Return to Attachments</a>
            </div>
        </div>
    </div>';
    
    stdfoot();
}
