<?php
declare(strict_types=1);


require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_multipage.php';

// Access check
if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <strong>Error!</strong> Direct initialization of this file is not allowed.
    </div>');
}

// Initialize parser
$parser = new postParser();
$parser_options = [
    "allow_html" => 0,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

// Page title
stdhead('Email Logs');

// Handle actions
handleMailLogActions();

// Search block
renderSearchForm();

// Get data
$perpage = (int)($torrentsperpage ?? 20) ?: 20;
$page = max(1, (int)($mybb->input['page'] ?? 1));
$start = ($page - 1) * $perpage;

// Get total count
$total_count = getTotalMailCount();
$mail_logs = getMailLogs($start, $perpage);

// Pagination - FIXED LINE
$page_url = $_this_script_ ?? '/admin/index.php';
$multipage = multipage($total_count, $perpage, $page, $page_url);

// Render page
renderMailLogsTable($mail_logs, $total_count, $multipage, $parser, $parser_options);

stdfoot();

/**
 * Handle mail log actions
 */
function handleMailLogActions(): void
{
    global $db, $usergroups, $_this_script_;
    
    if (!($usergroups['cansettingspanel'] ?? false)) {
        return;
    }
    
    if (($_POST['clear'] ?? '') === 'yes') {
        $db->sql_query_prepared('TRUNCATE TABLE maillogs');
        showAlert('success', '<i class="fas fa-trash"></i> Log table has been completely cleared!');
        return;
    }
    
    if (($_POST['action'] ?? '') === 'delete' && !empty($_POST['logid'])) {
        $log_ids = array_filter($_POST['logid'], 'is_numeric');
        if (!empty($log_ids)) {
            $ids = implode(', ', array_map('intval', $log_ids));
            $db->sql_query_prepared("DELETE FROM maillogs WHERE mid IN ($ids)");
            $deleted = $db->affected_rows();
            showAlert('success', 
                '<i class="fas fa-check-circle"></i> Successfully deleted ' . $deleted . ' ' . 
                pluralize($deleted, ['entry', 'entries', 'entries']) . '!'
            );
        }
    }
}

/**
 * Show notification
 */
function showAlert(string $type, string $message): void
{
    $icons = [
        'success' => 'fas fa-check-circle',
        'danger' => 'fas fa-exclamation-circle',
        'warning' => 'fas fa-exclamation-triangle',
        'info' => 'fas fa-info-circle'
    ];
    
    echo '<div class="container mt-3">
        <div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">
            <i class="' . ($icons[$type] ?? 'fas fa-info') . '"></i> ' . $message . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>';
}

/**
 * Render search form
 */
function renderSearchForm(): void
{
    global $_this_script_no_act;
    $searchstr = htmlspecialchars($_GET['query'] ?? '');
    
    echo '<div class="container mt-4">
        <div class="card border-0">
            <div class="card-body">
                <h4 class="card-title mb-4">
                    <i class="fas fa-search me-2 text-primary"></i>Search Email Logs
                </h4>
                <form method="get" action="' . htmlspecialchars(($_this_script_no_act ?? '') . '?act=searchlog') . '" class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="text" 
                                   name="query" 
                                   class="form-control border-start-0" 
                                   placeholder="Enter email, subject or message text..."
                                   value="' . $searchstr . '">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                    </div>
                </form>
                <div class="mt-3 d-flex gap-2">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="clear" value="yes">
                        <button type="submit" class="btn btn-outline-danger btn-sm" 
                                onclick="return confirm(\'Are you sure you want to completely clear all logs?\')">
                            <i class="fas fa-trash-alt me-1"></i> Clear All Logs
                        </button>
                    </form>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" 
                            data-bs-target="#helpSection">
                        <i class="fas fa-question-circle me-1"></i> Help
                    </button>
                </div>
                <div class="collapse mt-3" id="helpSection">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Search works by:</strong> sender address, recipient address, and message content.
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Get total mail count
 */
function getTotalMailCount(): int
{
    global $db;
    
    $res = $db->sql_query_prepared('SELECT COUNT(*) as total FROM maillogs');
    $row = $res ? $db->fetch_array($res) : null;
    
    return (int)($row['total'] ?? 0);
}

/**
 * Get mail logs
 */
function getMailLogs(int $start, int $perpage): array
{
    global $db;
    
    $query = "SELECT mid, dateline, message, fromemail, toemail 
              FROM maillogs 
              ORDER BY dateline DESC 
              LIMIT ?, ?";
    
    $res = $db->sql_query_prepared($query, [$start, $perpage]);
    $logs = [];
    
    while ($res && ($row = $db->fetch_array($res))) {
        $logs[] = $row;
    }
    
    return $logs;
}

/**
 * Render mail logs table
 */
function renderMailLogsTable(array $logs, int $total_count, string $multipage, $parser, array $parser_options): void
{
    global $_this_script_, $usergroups;
    
    echo '<div class="container mt-4">
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-envelope-open-text fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0">Total Entries</h5>
                                <h2 class="mb-0">' . number_format($total_count) . '</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3 text-warning">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0">Last Update</h5>
                                <h6 class="mb-0 text-muted">' . date('m/d/Y H:i:s') . '</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    
    // Top pagination
    if (!empty($multipage)) {
        echo '<div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-list me-1"></i>
                        Showing ' . (count($logs) > 0 ? (($page - 1) * $perpage + 1) : 0) . '-' . 
                        min(($page - 1) * $perpage + count($logs), $total_count) . ' of ' . $total_count . ' entries
                    </small>
                    <div class="pagination pagination-sm mb-0">
                        ' . $multipage . '
                    </div>
                </div>
            </div>
        </div>';
    }
    
    echo '<!-- Main table -->
        <div class="card border-0">
            <div class="card-header bg-white border-0 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2 text-primary"></i>Email History
                    </h5>';
    
    if (!empty($logs) && ($usergroups['cansettingspanel'] ?? false)) {
        echo '<button type="button" class="btn btn-outline-primary btn-sm" id="selectAllBtn">
                <i class="fas fa-check-square me-1"></i> Select All
            </button>';
    }
    
    echo '</div>
            </div>
            
            <form method="post" action="' . htmlspecialchars($_this_script_ ?? '') . '" id="mailLogsForm">
                <input type="hidden" name="action" value="delete">
                
                <div class="table-responsive">';
    
    if (empty($logs)) {
        echo '<div class="text-center py-5">
                <div class="py-4">
                    <i class="fas fa-inbox fa-4x text-muted opacity-50 mb-3"></i>
                    <h4 class="text-muted">Email logs are empty</h4>
                    <p class="text-muted mb-0">All system emails will be displayed here.</p>
                </div>
            </div>';
    } else {
        echo '<table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th style="width: 180px;">
                            <i class="fas fa-calendar-alt me-1 text-muted"></i>Date
                        </th>
                        <th>
                            <i class="fas fa-envelope me-1 text-muted"></i>Message
                        </th>
                        <th style="width: 100px;" class="text-center">
                            <i class="fas fa-cog me-1 text-muted"></i>Actions
                        </th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($logs as $log) {
            $date = my_datee('relative', $log['dateline']);
            $message = $parser->parse_message($log['message'], $parser_options);
            $message_preview = mb_substr(strip_tags($message), 0, 100) . (mb_strlen(strip_tags($message)) > 100 ? '...' : '');
            
           
            
            echo '<tr class="mail-log-row">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input mail-checkbox" 
                               name="logid[]" value="' . (int)$log['mid'] . '">
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="me-2 text-primary">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div class="fw-medium">' . $date . '</div>
                                <small class="text-muted">ID: ' . (int)$log['mid'] . '</small>
                            </div>
                        </div>
                    </td>
					
					
					
                    <td>
                        <div class="mail-preview">
                            <div class="mb-2">
                                <span class="badge bg-light text-dark me-2">
                                    <i class="fas fa-paper-plane me-1"></i>From: ' . htmlspecialchars($log['fromemail']) . '
                                </span>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-inbox me-1"></i>To: ' . htmlspecialchars($log['toemail']) . '
                                </span>
                            </div>
                            <div class="mail-content" style="max-height: 100px; overflow: hidden;">
                                ' . $message . '
                            </div>
                            <button type="button" class="btn btn-link btn-sm p-0 mt-1 show-more-btn">
                                <i class="fas fa-chevron-down me-1"></i>Show More
                            </button>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-info view-mail-btn" 
                                    data-mid="' . (int)$log['mid'] . '"
                                    data-from="' . htmlspecialchars($log['fromemail']) . '"
                                    data-to="' . htmlspecialchars($log['toemail']) . '"
                                    data-date="' . htmlspecialchars($date) . '"
                                    data-message="' . htmlspecialchars($message) . '"
                                    title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-single" 
                                    data-id="' . (int)$log['mid'] . '"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>';
        }
        
        echo '</tbody>
            </table>';
        
        // Control buttons
        if ($usergroups['cansettingspanel'] ?? false) {
            echo '<div class="card-footer bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small" id="selectedCount">Selected: 0</span>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm(\'Delete selected entries?\')"
                                    disabled id="deleteSelectedBtn">
                                <i class="fas fa-trash me-1"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                </div>';
        }
    }
    
    echo '</div>
            </form>
        </div>';
    
    // Bottom pagination
    if (!empty($multipage)) {
        echo '<div class="mt-4">
                ' . $multipage . '
            </div>';
    }
    
    echo '</div>
    
    <!-- View modal -->
    <div class="modal fade" id="mailDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-envelope-open-text me-2"></i>Email Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="mailDetailsContent">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-3">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Select all checkboxes
        const selectAll = document.getElementById("selectAll");
        const selectAllBtn = document.getElementById("selectAllBtn");
        
        function toggleAllCheckboxes(checked) {
            document.querySelectorAll(".mail-checkbox").forEach(cb => {
                cb.checked = checked;
            });
            updateSelectedCount();
        }
        
        if (selectAll) {
            selectAll.addEventListener("change", function() {
                toggleAllCheckboxes(this.checked);
            });
        }
        
        if (selectAllBtn) {
            selectAllBtn.addEventListener("click", function() {
                const allChecked = Array.from(document.querySelectorAll(".mail-checkbox"))
                    .every(cb => cb.checked);
                toggleAllCheckboxes(!allChecked);
                if (selectAll) {
                    selectAll.checked = !allChecked;
                }
            });
        }
        
        // Count selected
        function updateSelectedCount() {
            const selected = document.querySelectorAll(".mail-checkbox:checked").length;
            const selectedCount = document.getElementById("selectedCount");
            const deleteBtn = document.getElementById("deleteSelectedBtn");
            
            if (selectedCount) {
                selectedCount.textContent = "Selected: " + selected;
            }
            
            if (deleteBtn) {
                deleteBtn.disabled = selected === 0;
            }
        }
        
        document.querySelectorAll(".mail-checkbox").forEach(cb => {
            cb.addEventListener("change", updateSelectedCount);
        });
        
        // Expand/collapse messages
        document.querySelectorAll(".show-more-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                const content = this.closest(".mail-preview").querySelector(".mail-content");
                if (content.style.maxHeight) {
                    content.style.maxHeight = null;
                    this.innerHTML = \'<i class="fas fa-chevron-up me-1"></i>Collapse\';
                } else {
                    content.style.maxHeight = "100px";
                    this.innerHTML = \'<i class="fas fa-chevron-down me-1"></i>Show More\';
                }
            });
        });
        
        // Delete single entry
        document.querySelectorAll(".delete-single").forEach(btn => {
            btn.addEventListener("click", function() {
                if (confirm("Delete this entry?")) {
                    const form = document.getElementById("mailLogsForm");
                    const checkbox = document.createElement("input");
                    checkbox.type = "hidden";
                    checkbox.name = "logid[]";
                    checkbox.value = this.dataset.id;
                    form.appendChild(checkbox);
                    form.submit();
                }
            });
        });
        
        // View email details
        document.querySelectorAll(".view-mail-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                const modal = new bootstrap.Modal(document.getElementById("mailDetailsModal"));
                const content = document.getElementById("mailDetailsContent");
                
                content.innerHTML = `
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-paper-plane me-2"></i>Sender</h6>
                                    <p class="card-text">${this.dataset.from}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-inbox me-2"></i>Recipient</h6>
                                    <p class="card-text">${this.dataset.to}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-calendar-alt me-2"></i>Send Date</h6>
                                    <p class="card-text">${this.dataset.date}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-envelope me-2"></i>Message Content</h6>
                                    <div class="mail-content-full p-3 bg-light rounded">
                                        ${this.dataset.message}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
                
                modal.show();
            });
        });
        
        updateSelectedCount();
    });
    </script>';
}

/**
 * Pluralize numbers
 */
function pluralize(int $number, array $forms): string
{
    $cases = [2, 0, 1, 1, 1, 2];
    return $forms[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
}
?>