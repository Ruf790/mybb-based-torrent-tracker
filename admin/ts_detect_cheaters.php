<?php
declare(strict_types=1);

/******************************************************************/
/*==========[ TS Special Edition v.5.6 - Modernized ]============*/
/*====================[ Special Thanks To ]======================*/
/*        DrNet - wWw.SpecialCoders.CoM                         */
/*        Vinson - wWw.Decode4u.CoM                             */
/*    MrDecoder - wWw.Fearless-Releases.CoM                     */
/*        Fynnon - wWw.BvList.CoM                               */
/*****************************************************************/

require_once INC_PATH . '/functions_multipage.php';

if (!defined('STAFF_PANEL_TSSEv56')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert">
            <h4 class="alert-heading"><i class="fas fa-ban me-2"></i>Access Denied</h4>
            <p class="mb-0">Direct initialization of this file is not allowed.</p>
          </div>');
}

use function htmlspecialchars as e;

const AU_VERSION = '0.6 by xam';
const TORRENTS_PER_PAGE = 20;
const BANNED_GROUP_ID = 9; // UC_BANNED константа

/**
 * Get formatted torrent flags with Bootstrap badges
 */
function getTorrentFlags(array $torrent): string {
    global $BASEURL, $lang;
    
    $lang->load('browse');
    $flags = [];
    
    // Free torrent
    if ($torrent['free'] === 'yes') {
        $flags[] = '<span class="badge bg-success" data-bs-toggle="popover" 
                   data-bs-trigger="hover focus"
                   data-bs-content="' . e($lang->browse['freedownload'] ?? 'Free Download') . '">F</span>';
    }
    
    // Silver torrent
    if ($torrent['silver'] === 'yes') {
        $flags[] = '<span class="badge bg-secondary" data-bs-toggle="popover" 
                   data-bs-trigger="hover focus"
                   data-bs-content="' . e($lang->browse['silverdownload'] ?? 'Silver Download') . '">S</span>';
    }
    
    // Requested torrent
    if ($torrent['isrequest'] === 'yes') {
        $flags[] = '<span class="badge bg-primary" data-bs-toggle="popover" 
                   data-bs-trigger="hover focus"
                   data-bs-content="' . e($lang->browse['requested'] ?? 'Requested Torrent') . '">R</span>';
    }
    
    // Anonymous torrent
    if ($torrent['anonymous'] === 'yes') {
        $flags[] = '<span class="badge bg-danger" data-bs-toggle="popover" 
                   data-bs-trigger="hover focus"
                   data-bs-content="Anonymous torrent">A</span>';
    }
    
    // Visible/Active torrent
    if ($torrent['visible'] === 'yes') {
        $flags[] = '<span class="badge bg-success" data-bs-toggle="popover" 
                   data-bs-trigger="hover focus"
                   data-bs-content="Active Torrent">AC</span>';
    } else {
        $flags[] = '<i class="fas fa-skull-crossbones text-danger" 
                     data-bs-toggle="popover" data-bs-trigger="hover focus"
                     data-bs-content="Dead Torrent"></i>';
    }
    
    // Double upload
    if ($torrent['doubleupload'] === 'yes') {
        $flags[] = '<span class="badge bg-dark" data-bs-toggle="popover" 
                   data-bs-trigger="hover focus"
                   data-bs-content="' . e($lang->browse['dupload'] ?? 'Double Upload') . '">x2</span>';
    }
    
    // Sticky (using icon instead of image)
    if ($torrent['sticky'] === 'yes') {
        $flags[] = '<i class="fas fa-thumbtack text-warning" 
                     data-bs-toggle="popover" data-bs-trigger="hover focus"
                     data-bs-content="' . e($lang->browse['sticky'] ?? 'Sticky Torrent') . '"></i>';
    }
    
    // Nuked (using icon)
    if (!empty($torrent['isnuked']) && $torrent['isnuked'] === 'yes') {
        $flags[] = '<i class="fas fa-radiation text-danger" 
                     data-bs-toggle="popover" data-bs-trigger="hover focus"
                     data-bs-content="' . e(sprintf($lang->browse['nuked'] ?? 'Nuked: %s', $torrent['WhyNuked'] ?? '')) . '"></i>';
    }
    
    return implode(' ', $flags);
}

/**
 * Calculate and format user ratio safely
 */
function calculateUserRatio(int $downloaded, int $uploaded): string {
    if ($downloaded > 0) {
        $ratio = $uploaded / $downloaded;
        return number_format($ratio, 2);
    }
    
    return $uploaded > 0 ? '∞' : '0.00';
}

/**
 * Get user popover information
 */
function getUserPopover(array $user): array {
    global $lang;
    
    $downloaded = mksize((int)($user['user_current_download'] ?? 0));
    $uploaded = mksize((int)($user['user_current_upload'] ?? 0));
    $ratio = calculateUserRatio(
        (int)($user['user_current_download'] ?? 0),
        (int)($user['user_current_upload'] ?? 0)
    );
    
    $lastSeen = my_datee($dateformat, $user['lastactive'] ?? time()) . ' ' . 
                my_datee($timeformat, $user['lastactive'] ?? time());
    
    $addedDate = my_datee($dateformat, $user['added'] ?? time());
    
    return [
        'title' => e($user['username'] ?? 'Unknown User'),
        'content' => sprintf(
            '<strong>%s:</strong> %s<br>
            <strong>%s:</strong> %s<br>
            <strong>Downloaded:</strong> %s<br>
            <strong>Uploaded:</strong> %s<br>
            <strong>Ratio:</strong> %s',
            e($lang->tsf_forums['jdate'] ?? 'Joined'),
            e($addedDate),
            e($lang->tsf_forums['lastseen'] ?? 'Last Seen'),
            e($lastSeen),
            e($downloaded),
            e($uploaded),
            e($ratio)
        )
    ];
}

// Initialize pagination
$page = (int)($_GET['page'] ?? $_POST['page'] ?? 1);
$perPage = TORRENTS_PER_PAGE;
$start = max(0, ($page - 1) * $perPage);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total 
               FROM snatched s
               INNER JOIN users u ON (u.id = s.userid)
               WHERE s.downloaded = 0 
                 AND s.uploaded > 0 
                 AND s.leechtime = 0 
                 AND u.enabled = 'yes' 
                 AND u.usergroup != ?";

$countResult = $db->sql_query_prepared($countQuery, [BANNED_GROUP_ID]);

if ($countResult) {
    $countData = $db->fetch_array($countResult);
    $totalRecords = (int)($countData['total'] ?? 0);
} else {
    $totalRecords = 0;
}

// Calculate pagination
$totalPages = max(1, ceil($totalRecords / $perPage));
$page = min(max(1, $page), $totalPages);
$start = ($page - 1) * $perPage;

// Main query with pagination
$sql = "SELECT 
            s.port, s.ip, s.last_action, s.startdat, s.agent, 
            s.userid, s.uploaded as snatched_uploaded, s.downloaded as snatched_downloaded, 
            s.torrentid,
            t.name as torrent_name, t.free, t.silver, t.isrequest, t.isnuked, 
            t.sticky, t.anonymous, t.banned, t.ts_external, t.visible, 
            t.doubleupload, t.allowcomments, t.seeders, t.leechers, 
            u.downloaded as user_current_download, 
            u.uploaded as user_current_upload, 
            u.username, u.usergroup, u.added, u.avatar, u.lastactive, 
            u.enabled, u.donor, u.leechwarn, u.warned,
            g.namestyle, g.title as group_title
        FROM snatched s
        LEFT JOIN torrents t ON (s.torrentid = t.id)
        INNER JOIN users u ON (u.id = s.userid)
        LEFT JOIN usergroups g ON (u.usergroup = g.gid)
        WHERE s.downloaded = 0 
          AND s.uploaded > 0 
          AND s.leechtime = 0 
          AND u.enabled = 'yes' 
          AND u.usergroup != " . (int)BANNED_GROUP_ID . "
        ORDER BY u.username 
        LIMIT " . (int)$start . ", " . (int)$perPage;

$query = $db->sql_query($sql);

if ($query === false) {
    // Логируем ошибку и показываем пользователю
    error_log("Database query failed in ts_detect_cheaters.php");
    
    stdhead('Cheater Detection System - Error');
    
    echo <<<HTML
<div class="container-fluid py-4">
    <div class="alert alert-danger">
        <i class="fas fa-database me-2"></i>
        <strong>Database Error:</strong> Unable to retrieve cheater data. Please try again later.
    </div>
</div>
HTML;
    
    stdfoot();
    exit();
}

// Generate pagination links
$pageUrl = $_this_script_ . '&page=';
$multipage = multipage($totalRecords, $perPage, (int)$page, $pageUrl);

// Load language files
$lang->load('tsf_forums');
include_once INC_PATH . '/functions_icons.php';
include_once INC_PATH . '/functions_ratio.php';

// Start output
stdhead('Cheater Detection System');



?>

<div class="container mt-3">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white rounded-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-secret fa-lg me-3"></i>
                        <div>
                            <h1 class="h4 mb-0">Cheater Detection System</h1>
                            <p class="small mb-0 opacity-85">Detect potential cheating in torrent statistics</p>
                        </div>
                        <div class="ms-auto">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-shield-alt me-1"></i>v<?= e(AU_VERSION) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Detection Logic:</strong> Rows with <span class="badge bg-warning text-dark">highlighted background</span> 
                        indicate 69% probability of cheating (downloaded = 0 AND torrent not free).
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <?php if ($totalRecords > 0): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                    <h3 class="h2 mb-0"><?= number_format($totalRecords) ?></h3>
                    <p class="text-muted mb-0">Total Suspicious</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-file-alt fa-2x text-success mb-2"></i>
                    <h3 class="h2 mb-0"><?= number_format($perPage) ?></h3>
                    <p class="text-muted mb-0">Per Page</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-layer-group fa-2x text-info mb-2"></i>
                    <h3 class="h2 mb-0"><?= number_format($totalPages) ?></h3>
                    <p class="text-muted mb-0">Total Pages</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-filter fa-2x text-warning mb-2"></i>
                    <h3 class="h2 mb-0">Active</h3>
                    <p class="text-muted mb-0">Real-time Filtering</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
	
	
	<?= $multipage ?>

    <!-- Main Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-table me-2"></i>Suspicious Activity Log
                        </h2>
                        <?php if ($totalPages > 0): ?>
                        <div>
                            <span class="badge bg-info">
                                Page <?= $page ?> of <?= $totalPages ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">
                                        <i class="fas fa-user me-1"></i>User Information
                                    </th>
                                    <th>
                                        <i class="fas fa-file-torrent me-1"></i>Torrent Details
                                    </th>
                                    <th>
                                        <i class="fas fa-upload me-1"></i>Upload Statistics
                                    </th>
                                    <th>
                                        <i class="fas fa-network-wired me-1"></i>Connection Info
                                    </th>
                                    <th>
                                        <i class="fas fa-clock me-1"></i>Time Information
                                    </th>
                                   
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($query && $db->num_rows($query) > 0): ?>
                                    <?php while ($record = $db->fetch_array($query)): ?>
                                        <?php
                                        // Check if suspicious (downloaded = 0 AND torrent not free)
                                        $isSuspicious = (
                                            (int)($record['user_current_download'] ?? 0) === 0 && 
                                            ($record['free'] ?? 'no') === 'no'
                                        );
                                        
                                        $profileLink = $BASEURL . '/' . get_profile_link($record['userid']);
                                        $torrentLink = $BASEURL . '/' . get_torrent_link($record['torrentid']);
                                        $popoverInfo = getUserPopover($record);
                                        ?>
                                        
                                        <tr class="<?= $isSuspicious ? 'table-warning' : '' ?>">
                                            <!-- User Column -->
                                            <td class="ps-4">
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-grow-1">
                                                        <a href="<?= e($profileLink) ?>" 
                                                           class="fw-bold text-decoration-none"
                                                           data-bs-toggle="popover" 
                                                           data-bs-trigger="hover focus"
                                                           data-bs-placement="top"
                                                           data-bs-title="<?= $popoverInfo['title'] ?>"
                                                           data-bs-content="<?= $popoverInfo['content'] ?>"
                                                           data-bs-html="true"
                                                           data-bs-container="body">
                                                            <?= format_name($record['username'] ?? '', $record['usergroup'] ?? 0) ?>
                                                        </a>
                                                        <div class="small text-muted">
                                                            <?= e($record['group_title'] ?? 'No Group') ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($isSuspicious): ?>
                                                        <span class="badge bg-warning ms-2" 
                                                              data-bs-toggle="popover" 
                                                              data-bs-trigger="hover focus"
                                                              data-bs-content="69% Cheat Probability - Downloaded = 0 AND torrent is NOT free">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Torrent Column -->
                                            <td>
                                                <div class="mb-2">
                                                    <a href="<?= e($torrentLink) ?>" 
                                                       target="_blank"
                                                       class="text-decoration-none"
                                                       data-bs-toggle="popover" 
                                                       data-bs-trigger="hover focus"
                                                       data-bs-title="Torrent Details"
                                                       data-bs-content="<?= e($record['torrent_name'] ?? 'Unknown') ?>">
                                                        <i class="fas fa-magnet text-danger me-1"></i>
                                                        <?= e(cutename($record['torrent_name'] ?? 'Unknown', 60)) ?>
                                                    </a>
                                                </div>
                                                <div class="d-flex flex-wrap gap-1 mb-2">
                                                    <?= getTorrentFlags($record) ?>
                                                </div>
                                                <div class="small">
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-seedling me-1"></i>
                                                        <?= ts_nf($record['seeders'] ?? 0) ?>
                                                    </span>
                                                    <span class="badge bg-secondary ms-1">
                                                        <i class="fas fa-leaf me-1"></i>
                                                        <?= ts_nf($record['leechers'] ?? 0) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            
                                            <!-- Upload Stats Column -->
                                            <td>
                                                <div class="mb-2">
                                                    <span class="badge bg-success"
                                                          data-bs-toggle="popover" 
                                                          data-bs-trigger="hover focus"
                                                          data-bs-content="Uploaded: <?= mksize((int)($record['snatched_uploaded'] ?? 0)) ?>">
                                                        <i class="fas fa-arrow-up me-1"></i>
                                                        <?= mksize((int)($record['snatched_uploaded'] ?? 0)) ?>
                                                    </span>
                                                </div>
                                                <div class="small">
                                                    <strong>Port:</strong>
                                                    <code class="ms-1"><?= (int)($record['port'] ?? 0) ?></code>
                                                </div>
                                            </td>
                                            
                                            <!-- Connection Info Column -->
                                            <td>
                                                <div class="font-monospace small mb-2" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"
                                                     data-bs-toggle="popover" 
                                                     data-bs-trigger="hover focus"
                                                     data-bs-title="User Agent"
                                                     data-bs-content="<?= e($record['agent'] ?? 'Unknown') ?>">
                                                    <?= e($record['agent'] ?? 'Unknown') ?>
                                                </div>
                                                <div class="small">
                                                    <strong>IP:</strong>
                                                    <code class="ms-1" 
                                                          data-bs-toggle="popover" 
                                                          data-bs-trigger="hover focus"
                                                          data-bs-title="IP Address"
                                                          data-bs-content="Click to copy"><?= e($record['ip'] ?? '0.0.0.0') ?></code>
                                                </div>
                                            </td>
                                            
                                            <!-- Time Column -->
                                            <td>
                                                <div class="small">
                                                    <div class="mb-2">
                                                        <strong><i class="fas fa-play me-1"></i>Started:</strong><br>
                                                        <span data-bs-toggle="popover" 
                                                              data-bs-trigger="hover focus"
                                                              data-bs-title="Start Date & Time"
                                                              data-bs-content="<?= my_datee($dateformat, $record['startdat'] ?? time()) ?> <?= my_datee($timeformat, $record['startdat'] ?? time()) ?>">
                                                            <?= my_datee($dateformat, $record['startdat'] ?? time()) ?><br>
                                                            <?= my_datee($timeformat, $record['startdat'] ?? time()) ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <strong><i class="fas fa-sync me-1"></i>Last Action:</strong><br>
                                                        <span data-bs-toggle="popover" 
                                                              data-bs-trigger="hover focus"
                                                              data-bs-title="Last Action Date & Time"
                                                              data-bs-content="<?= my_datee($dateformat, $record['last_action'] ?? time()) ?> <?= my_datee($timeformat, $record['last_action'] ?? time()) ?>">
                                                            <?= my_datee($dateformat, $record['last_action'] ?? time()) ?><br>
                                                            <?= my_datee($timeformat, $record['last_action'] ?? time()) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                            <h4 class="text-muted">No Suspicious Activity Found</h4>
                                            <p class="text-muted">All users appear to be following the rules.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Table Footer -->
                <?php if ($totalRecords > 0): ?>
                <div class="card-footer bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="small text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing <?= min($start + 1, $totalRecords) ?> to 
                                <?= min($start + $perPage, $totalRecords) ?> of 
                                <?= number_format($totalRecords) ?> entries
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-md-end">
                                <?= $multipage ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Legend -->
    <?php if ($totalRecords > 0): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-info text-white">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-key me-2"></i>Detection Legend
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">
                                <i class="fas fa-flag me-2"></i>Torrent Flags
                            </h6>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-success" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Free Torrent">F</span>
                                <span class="badge bg-secondary" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Silver Torrent">S</span>
                                <span class="badge bg-primary" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Requested">R</span>
                                <span class="badge bg-danger" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Anonymous">A</span>
                                <span class="badge bg-dark" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Double Upload">x2</span>
                                <span class="badge bg-success" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Active">AC</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>Cheat Indicators
                            </h6>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Highlighted rows (yellow)</strong> indicate high probability of cheating.<br>
                                <small>Criteria: Downloaded = 0 AND torrent is NOT free</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>




<!-- JavaScript for popovers -->

<script type="text/javascript" src="<?= htmlspecialchars($BASEURL) ?>/scripts/popover.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
 
    
    // Add click to copy IP functionality with popover feedback
    document.querySelectorAll('code').forEach(codeElement => {
        if (codeElement.textContent.match(/\d+\.\d+\.\d+\.\d+/)) {
            codeElement.style.cursor = 'pointer';
            
            codeElement.addEventListener('click', function() {
                const ip = this.textContent.trim();
                navigator.clipboard.writeText(ip).then(() => {
                    // Show temporary popover feedback
                    const feedbackPopover = new bootstrap.Popover(this, {
                        content: 'IP Address Copied!',
                        trigger: 'manual',
                        placement: 'top'
                    });
                    
                    feedbackPopover.show();
                    
                    // Hide after 1.5 seconds
                    setTimeout(() => {
                        feedbackPopover.hide();
                        feedbackPopover.dispose();
                    }, 1500);
                });
            });
        }
    });
    
    // Handle delete button confirmation
    document.querySelectorAll('.delete-report').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const userId = this.getAttribute('data-id');
            const confirmDelete = confirm('Are you sure you want to delete this report? This action cannot be undone.');
            
            if (confirmDelete) {
                // Create and show a confirmation popover
                const confirmPopover = new bootstrap.Popover(this, {
                    content: 'Deleting... <div class="spinner-border spinner-border-sm" role="status"></div>',
                    placement: 'left',
                    html: true,
                    trigger: 'manual'
                });
                
                confirmPopover.show();
                
                // Simulate delete action (replace with actual AJAX call)
                setTimeout(() => {
                    confirmPopover.hide();
                    confirmPopover.dispose();
                    alert('Report deleted successfully!');
                    // window.location.href = this.href; // Uncomment for actual deletion
                }, 1000);
            }
        });
    });
});
</script>

<style>
:root {
    --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    --info-gradient: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}

.bg-gradient-danger {
    background: var(--danger-gradient) !important;
}

.bg-gradient-info {
    background: var(--info-gradient) !important;
}

.card {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.08);
}

.card-header {
    border-bottom: none;
    font-weight: 600;
}

.table-dark th {
    background-color: #212529;
    border-color: #32383e;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.03);
    transform: translateX(2px);
    transition: all 0.2s ease;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.15) !important;
}

.table-warning:hover {
    background-color: rgba(255, 193, 7, 0.25) !important;
}

.badge {
    border-radius: 6px;
    padding: 0.35em 0.65em;
    font-weight: 500;
    cursor: pointer;
}

.font-monospace {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 0.875em;
}

code {
    padding: 0.2em 0.4em;
    background-color: rgba(0,0,0,0.05);
    border-radius: 3px;
    font-size: 0.875em;
    transition: all 0.2s ease;
    cursor: pointer;
}

code:hover {
    background-color: rgba(0,0,0,0.1);
}

.popover {
    font-size: 0.875rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    border-color: #dc3545;
}

.btn-outline-warning:hover {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

.btn-outline-info:hover {
    background-color: #0dcaf0;
    border-color: #0dcaf0;
    color: #fff;
}
</style>

<?php
stdfoot();
unset($query, $multipage, $totalRecords, $totalPages);
?>