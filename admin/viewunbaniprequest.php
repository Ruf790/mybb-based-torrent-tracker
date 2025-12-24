<?php

declare(strict_types=1);



if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct access to this file is not allowed.</div>');
}

define('UL_VERSION', 'by xam v.0.8');

/**
 * Generate back link
 */
function generate_back_link(string $script): string
{
    return '<a href="' . htmlspecialchars($script) . '" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>';
}

/**
 * Display error message with back link
 */
function display_error(string $title, string $message, bool $show_back = true): void
{
    global $_this_script_;
    
    $back_link = $show_back ? generate_back_link($_this_script_) : '';
    
    echo '
    <div class="container mt-4">
        <div class="card border-danger shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>' . htmlspecialchars($title) . '</h5>
            </div>
            <div class="card-body">
                <p class="card-text">' . htmlspecialchars($message) . '</p>
                ' . $back_link . '
            </div>
        </div>
    </div>';
}

// Process action
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$action = htmlspecialchars($action);

// Handle delete action
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    $sure = (int)($_GET['sure'] ?? 0);
    
    if ($id <= 0) {
        display_error('Error', 'Invalid request ID');
        exit;
    }
    
    if ($sure !== 1) {
        $confirm_url = $_this_script_ . '&action=delete&id=' . $id . '&sure=1';
        
        echo '
        <div class="container mt-4">
            <div class="card border-warning shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>Delete Confirmation</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h6 class="alert-heading"><i class="bi bi-exclamation-circle me-2"></i>Warning!</h6>
                        <p class="mb-0">You are about to delete an unban request. This action cannot be undone.</p>
                    </div>
                    
                    <div class="d-flex flex-column flex-md-row gap-3 mt-4">
                        <a href="' . $confirm_url . '" 
                           class="btn btn-danger btn-lg flex-grow-1"
                           onclick="return confirm(\'Are you absolutely sure you want to delete this request?\')">
                            <i class="bi bi-trash3 me-2"></i>Yes, Delete Request
                        </a>
                        ' . generate_back_link($_this_script_) . '
                    </div>
                </div>
            </div>
        </div>';
        
        exit;
    } else {
        try {
            $result = $db->sql_query('DELETE FROM unbanrequests WHERE id = ' . $db->sqlesc($id));
            
            if ($result) {
                echo '
                <div class="container mt-4">
                    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>Success!</strong> Unban request has been deleted.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    ' . generate_back_link($_this_script_) . '
                </div>';
            } else {
                display_error('Delete Failed', 'Unable to delete unban request #' . $id);
            }
        } catch (Exception $e) {
            display_error('Database Error', 'Failed to delete request: ' . $e->getMessage());
        }
        exit;
    }
}

// Display main page
stdhead('Unban Requests Manager');

// Get pagination settings
$perpage = $ts_perpage ?? 20;
$current_page = (int)($_GET['page'] ?? 1);
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $perpage;

// Get total count
$count_query = $db->sql_query('SELECT COUNT(*) as total FROM unbanrequests');
if ($count_query && $count_query->result) {
    $count_row = $db->fetch_array($count_query->result);
    $total_count = (int)($count_row['total'] ?? 0);
    $db->free_result($count_query->result);
} else {
    $total_count = 0;
}

// Calculate pagination
$total_pages = (int)ceil($total_count / $perpage);
$current_page = min($current_page, $total_pages);

// Get records
$sql = "SELECT u.*, l.id as loginaid 
        FROM unbanrequests u 
        LEFT JOIN loginattempts l ON (u.ip = l.ip OR u.realip = l.ip) 
        ORDER BY u.added DESC 
        LIMIT " . $db->sqlesc($offset) . ", " . $db->sqlesc($perpage);

$result = $db->sql_query($sql);

?>
<!-- Page Header -->
<div class="container-fluid py-4 bg-gradient bg-primary bg-opacity-10">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php">Staff Panel</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Unban Requests</li>
                    </ol>
                </nav>
                <h1 class="h2 mb-0 mt-2">
                    <i class="bi bi-shield-lock me-2"></i>Unban Requests Manager
                </h1>
                <p class="text-muted mb-0">
                    Manage user requests for IP unbanning
                    <span class="badge bg-primary ms-2"><?= UL_VERSION ?></span>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="d-flex flex-column flex-md-row gap-2">
                    <span class="badge bg-secondary fs-6">
                        <i class="bi bi-list-ul me-1"></i><?= number_format($total_count) ?> Requests
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container mt-4">
    <?php if ($db->num_rows($result) === 0): ?>
        <!-- Empty State -->
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="empty-state-icon mb-4">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                </div>
                <h3 class="text-muted mb-3">No Unban Requests</h3>
                <p class="text-muted mb-4">There are currently no pending unban requests.</p>
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Users can submit requests when their IP is blocked from logging in.
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Requests Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">
                            <i class="bi bi-table me-2"></i>Unban Requests
                        </h5>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i>Print
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="exportToCSV()">
                                <i class="bi bi-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 80px;">
                                <i class="bi bi-hash"></i> ID
                            </th>
                            <th>
                                <i class="bi bi-globe me-1"></i> IP Address
                            </th>
                            <th>
                                <i class="bi bi-globe-americas me-1"></i> Real IP
                            </th>
                            <th>
                                <i class="bi bi-envelope me-1"></i> Email
                            </th>
                            <th>
                                <i class="bi bi-chat-text me-1"></i> Comment
                            </th>
                            <th>
                                <i class="bi bi-calendar-plus me-1"></i> Submitted
                            </th>
                            <th class="text-center" style="width: 150px;">
                                <i class="bi bi-gear me-1"></i> Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = $db->fetch_array($result)): ?>
                            <tr id="request-<?= $request['id'] ?>">
                                <!-- ID -->
                                <td class="text-center">
                                    <span class="badge bg-dark rounded-pill">
                                        #<?= $request['id'] ?>
                                    </span>
                                </td>
                                
                                <!-- IP Address -->
                                <td>
                                    <div class="font-monospace small">
                                        <i class="bi bi-dot text-primary"></i>
                                        <?= htmlspecialchars($request['ip'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                
                                <!-- Real IP -->
                                <td>
                                    <div class="font-monospace small">
                                        <i class="bi bi-dot text-success"></i>
                                        <?= htmlspecialchars($request['realip'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                
                                <!-- Email -->
                                <td>
                                    <?php if (!empty($request['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($request['email']) ?>" 
                                           class="text-decoration-none">
                                            <i class="bi bi-envelope me-1 text-primary"></i>
                                            <?= htmlspecialchars($request['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="bi bi-envelope me-1"></i>Not provided
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Comment -->
                                <td>
                                    <div class="comment-preview text-truncate" style="max-width: 250px;" 
                                         data-bs-toggle="tooltip" 
                                         data-bs-placement="top"
                                         title="<?= htmlspecialchars($request['comment']) ?>">
                                        <?= htmlspecialchars($request['comment'] ?? 'No comment') ?>
                                    </div>
                                </td>
                                
                                <!-- Submitted Date -->
                                <td>
                                    <div class="small">
                                        <div class="text-muted">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= my_datee($dateformat, $request['added']) ?>
                                        </div>
                                        <div class="text-muted">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= my_datee($timeformat, $request['added']) ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Actions -->
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if ($request['loginaid']): ?>
                                            <!-- Edit Failed Login Attempt -->
                                            <a href="<?= $_this_script_no_act ?>?act=maxlogin&action=edit&id=<?= $request['loginaid'] ?>&return=yes"
                                               class="btn btn-outline-primary"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               title="Edit Failed Login Attempt">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            
                                            <!-- Delete Failed Login Attempt -->
                                            <a href="<?= $_this_script_no_act ?>?act=maxlogin&action=delete&id=<?= $request['loginaid'] ?>&return=yes"
                                               class="btn btn-outline-warning"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               title="Delete Failed Login Attempt"
                                               onclick="return confirm('Delete this failed login attempt?')">
                                                <i class="bi bi-shield-x"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Delete Request -->
                                        <a href="<?= $_this_script_ ?>&action=delete&id=<?= $request['id'] ?>"
                                           class="btn btn-outline-danger"
                                           data-bs-toggle="tooltip"
                                           data-bs-placement="top"
                                           title="Delete Unban Request"
                                           onclick="return confirm('Delete this unban request?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-light border-0">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <!-- Previous Page -->
                            <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" 
                                   href="<?= $_this_script_ ?>&page=<?= max(1, $current_page - 1) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $_this_script_ ?>&page=<?= $i ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Next Page -->
                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" 
                                   href="<?= $_this_script_ ?>&page=<?= min($total_pages, $current_page + 1) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Legend -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-light border-0">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Action Legend</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary me-2">
                                <i class="bi bi-pencil"></i>
                            </span>
                            <span class="small">Edit Failed Login Attempt</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-warning text-dark me-2">
                                <i class="bi bi-shield-x"></i>
                            </span>
                            <span class="small">Delete Failed Login Attempt</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-danger me-2">
                                <i class="bi bi-trash"></i>
                            </span>
                            <span class="small">Delete Unban Request</span>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>Note:</strong> If no edit button is shown, the IP address from the unban request 
                    could not be found in the failed login attempts database.
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Export to CSV function
function exportToCSV() {
    let csv = [];
    let rows = document.querySelectorAll('table tr');
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Remove icons and HTML from cell content
            let text = cols[j].innerText.replace(/\n/g, ' ').trim();
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    let csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
    let encodedUri = encodeURI(csvContent);
    let link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', 'unban_requests_' + new Date().toISOString().split('T')[0] + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Quick delete confirmation
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-outline-danger')) {
        if (!confirm('Are you sure you want to delete this unban request?')) {
            e.preventDefault();
        }
    }
});
</script>

<style>
/* Custom Styles */
.empty-state-icon {
    opacity: 0.5;
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 0.5; transform: translateY(0); }
}

.comment-preview {
    cursor: help;
    transition: all 0.2s ease;
}

.comment-preview:hover {
    color: #0d6efd;
}

.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
}

.table td, .table th {
    vertical-align: middle;
}

.breadcrumb {
    background: transparent;
    padding: 0;
}

.bg-gradient {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

@media (max-width: 768px) {
    .btn-group-sm {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
}
</style>

<?php
stdfoot();