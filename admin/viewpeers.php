<?php



declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center">Error! Direct initialization of this file is not allowed.</div>');
}

define('VP_VERSION', '0.2 by xam');

/**
 * Get user agent information
 */
function get_agent_info(?string $http_agent = '', ?string $peer_id = ""): string
{
    global $lang;
    return $http_agent ?: ($peer_id ?: $lang->global['unknown']);
}

/**
 * Safely encode HTML characters
 */
function html_safe_chars(?string $text = ''): string
{
    if (empty($text)) {
        return '';
    }
    
    $text = preg_replace("/&(?!#[0-9]+;)(?:amp;)?/s", '&amp;', $text);
    return str_replace(
        ["<", ">", '"', "'"],
        ["&lt;", "&gt;", "&quot;", '&#039;'],
        $text
    );
}

/**
 * Format peer status badge
 */
function format_peer_status(string $status, string $type): string
{
    $badge_class = match($status) {
        'yes' => 'bg-success',
        'no'  => 'bg-danger',
        default => 'bg-secondary'
    };
    
    $text = match($type) {
        'connectable' => $status,
        'seeder' => ($status === 'yes' ? 'seed' : 'leech'),
        default => $status
    };
    
    return '<span class="badge ' . $badge_class . '">' . $text . '</span>';
}

/**
 * Format data size with color coding
 */
function format_data_size(int $uploaded, int $downloaded, string $type): string
{
    $size = match($type) {
        'uploaded' => $uploaded,
        'downloaded' => $downloaded,
        default => 0
    };
    
    $formatted = mksize($size);
    
    if ($type === 'uploaded') {
        if ($uploaded < $downloaded && $uploaded > 0) {
            return '<span class="text-danger">' . $formatted . '</span>';
        } elseif ($uploaded > 0) {
            return '<span class="text-success">' . $formatted . '</span>';
        }
    }
    
    return $formatted;
}

// Initialize
stdhead('Peer List');
require_once INC_PATH . '/functions_multipage.php';

// Get total peer count
$count_result = $db->sql_query('SELECT COUNT(*) FROM peers');
$total_peers = (int)mysqli_fetch_array($count_result)[0];

// Pagination setup
$per_page = max(20, (int)($ts_perpage ?? 20));
$page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
$start = ($page - 1) * $per_page;
$total_pages = (int)ceil($total_peers / $per_page);

if ($page > $total_pages) {
    $start = 0;
    $page = 1;
}

$multipage = multipage($total_peers, $per_page, $page, $_this_script_ . '&');

// Display header
echo '
<div class="container mt-3">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">
                                <i class="fas fa-network-wired me-2"></i>
                                Peer List
                            </h4>
                            <small class="opacity-75">Live peer connections monitoring</small>
                        </div>
                        <span class="badge bg-light text-primary fs-6">
                            ' . $total_peers . ' Active Peers
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';

// Top pagination
if (!empty($multipage)) {
    echo '
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-center">
                            ' . $multipage . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

// Fetch peers data
$sql = "
    SELECT p.*, t.name, t.added, u.username, u.usergroup, u.displaygroup
    FROM peers p 
    LEFT JOIN torrents t ON (p.torrent = t.id)
    LEFT JOIN users u ON (p.userid = u.id)
    ORDER BY p.started DESC 
    LIMIT {$start}, {$per_page}
";

$result = $db->sql_query($sql);

if ($db->num_rows($result) > 0) {
    echo '
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="120">User</th>
                                        <th width="150">Torrent</th>
                                        <th width="100">IP:Port</th>
                                        <th width="80" class="text-center">Upload</th>
                                        <th width="80" class="text-center">Download</th>
                                        <th width="120">Peer ID</th>
                                        <th width="80" class="text-center">Connect</th>
                                        <th width="80" class="text-center">Status</th>
                                        <th width="120" class="text-center">Started</th>
                                        <th width="100" class="text-center">Last Action</th>
                                        <th width="100" class="text-center">Prev Action</th>
                                        <th width="80" class="text-center">Up Offset</th>
                                        <th width="80" class="text-center">Down Offset</th>
                                        <th width="80" class="text-center">To Go</th>
                                    </tr>
                                </thead>
                                <tbody>';

    while ($row = mysqli_fetch_array($result)) {
        echo render_peer_row($row);
    }

    echo '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
} else {
    echo '
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Active Peers</h4>
                        <p class="text-muted mb-0">There are currently no active peer connections.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

// Bottom pagination
if (!empty($multipage)) {
    echo '
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-center">
                            ' . $multipage . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

stdfoot();

/**
 * Render a single peer row
 */
/**
 * Render a single peer row
 */
function render_peer_row(array $row): string
{
    global $BASEURL, $dateformat, $timeformat;
	
	$username = htmlspecialchars_uni($row['username'] ?? '');
    $formatted_name = format_name($username, $row['usergroup'] ?? 0, $row['displaygroup'] ?? 0);
    $profile_link = $BASEURL . '/' . get_profile_link($row['userid'] ?? 0);
    
    $torrent_name = htmlspecialchars_uni($row['name'] ?? 'Unknown Torrent');
    $short_name = htmlspecialchars_uni(cutename($row['name'] ?? 'Unknown', 5));
    $torrent_link = $BASEURL . '/' . get_torrent_link($row['torrent'] ?? 0);
    
    // Safe date for popover
    $torrent_added = my_datee($dateformat, $row['added']);
    
    $ip_port = htmlspecialchars_uni($row['ip'] ?? 'N/A') . '<br><small class="text-muted">' . htmlspecialchars_uni($row['port'] ?? 'N/A') . '</small>';
    
    $uploaded = format_data_size((int)($row['uploaded'] ?? 0), (int)($row['downloaded'] ?? 0), 'uploaded');
    $downloaded = format_data_size((int)($row['uploaded'] ?? 0), (int)($row['downloaded'] ?? 0), 'downloaded');
    
    $peer_id = html_safe_chars(get_agent_info($row["agent"] ?? '', $row['peer_id'] ?? ''));
    $connectable = format_peer_status($row['connectable'] ?? 'no', 'connectable');
    $status = format_peer_status($row['seeder'] ?? 'no', 'seeder');
    
    // Safe date formatting with null checks
    $started = my_datee($dateformat, $row['started']) . '<br><small class="text-muted">' . my_datee($timeformat, $row['started']) . '</small>';
    $last_action = my_datee($dateformat, $row['last_action']) . '<br><small class="text-muted">' . my_datee($timeformat, $row['last_action']) . '</small>';
    $prev_action = my_datee($dateformat, $row['prev_action']) . '<br><small class="text-muted">' . my_datee($timeformat, $row['prev_action']) . '</small>';
    
    $upload_offset = mksize($row['uploadoffset']);
    $download_offset = mksize($row['downloadoffset']);
    $to_go = mksize($row['to_go']);
	
	
	
	
	
	
	
	

    // Правильно экранируем HTML для popover
    $popover_title = htmlspecialchars('📁 ' . cutename($row['name'] ?? 'Unknown', 20), ENT_QUOTES);
    $popover_content = htmlspecialchars('
        <div class="torrent-popover">
            <div class="mb-2">
                <strong>📂 Full Name:</strong><br>
                <span class="text-break">' . $torrent_name . '</span>
            </div>
            <div class="d-flex justify-content-between align-items-center border-top pt-2 small text-muted">
                <span><i class="fas fa-user me-1"></i>' . $formatted_name . '</span>
                <span><i class="fas fa-clock me-1"></i>' . $torrent_added . '</span>
            </div>
        </div>
    ', ENT_QUOTES);

    return '
    <tr>
        <td>
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-user-circle text-muted"></i>
                </div>
                <div class="flex-grow-1 ms-2">
                    <a href="' . $profile_link . '" class="text-decoration-none fw-semibold">
                        ' . $formatted_name . '
                    </a>
                </div>
            </div>
        </td>
        <td>
            <a href="' . $torrent_link . '" 
               class="text-decoration-none torrent-link" 
               data-bs-toggle="popover" 
               data-bs-placement="top"
               data-bs-title="' . $popover_title . '"
               data-bs-content="' . $popover_content . '"
               data-bs-html="true">
                ' . $short_name . '
            </a>
        </td>
        <td class="small">' . $ip_port . '</td>
        <td class="text-center fw-semibold">' . $uploaded . '</td>
        <td class="text-center">' . $downloaded . '</td>
        <td class="small font-monospace">' . $peer_id . '</td>
        <td class="text-center">' . $connectable . '</td>
        <td class="text-center">' . $status . '</td>
        <td class="text-center small">' . $started . '</td>
        <td class="text-center small">' . $last_action . '</td>
        <td class="text-center small">' . $prev_action . '</td>
        <td class="text-center small text-muted">' . $upload_offset . '</td>
        <td class="text-center small text-muted">' . $download_offset . '</td>
        <td class="text-center small text-muted">' . $to_go . '</td>
    </tr>';
}


echo '


<style>
.torrent-popover {
    max-width: 320px;
    font-size: 0.875rem;
}
.torrent-popover strong {
    color: #495057;
}
.popover {
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.popover-header {
    background: var(--bs-primary);
    color: white;
    border-bottom: none;
    border-radius: 8px 8px 0 0;
    font-weight: 600;
}
.popover-body {
    padding: 12px 16px;
}
.text-break {
    word-break: break-word;
}
.bs-popover-top > .popover-arrow::after {
    border-top-color: var(--bs-primary);
}
</style>';


// JavaScript for tooltips
echo '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="popover"]\'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            trigger: "hover focus",
            placement: "top",
            delay: {"show": 100, "hide": 100},
            html: true,
            container: "body"
        });
    });
    
    // Close popovers when clicking outside
    document.addEventListener("click", function(e) {
        popoverList.forEach(function(popover) {
            if (!popover._element.contains(e.target)) {
                popover.hide();
            }
        });
    });
});
</script>';