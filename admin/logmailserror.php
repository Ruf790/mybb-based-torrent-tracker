<?php
declare(strict_types=1);


require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_multipage.php';

// Access check
if (!defined('STAFF_PANEL_TSSEv56')) {
    http_response_code(403);
    exit('<div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <strong>Error!</strong> Direct initialization of this file is not allowed.
    </div>');
}

// Initialize parser
$parser = new postParser();
$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

// Page title
stdhead('System Email Error Logs');

// Handle actions
handleMailErrorActions();

// Search block
renderSearchForm();

// Get data
$perpage = (int)($torrentsperpage ?? 20) ?: 20;
$page = max(1, (int)($mybb->input['page'] ?? 1));
$start = ($page - 1) * $perpage;

// Get total count and logs
$total_count = getTotalMailErrorCount();
$error_logs = getMailErrorLogs($start, $perpage);

// Pagination
$page_url = $_this_script_ ?? '/admin/index.php';
$multipage = multipage($total_count, $perpage, $page, $page_url);

// Render page
renderMailErrorLogsTable($error_logs, $total_count, $multipage, $parser, $parser_options);

stdfoot();

/**
 * Handle mail error actions
 */
function handleMailErrorActions(): void
{
    global $db, $usergroups;
    
    if (!($usergroups['cansettingspanel'] ?? false)) {
        return;
    }
    
    if (($_POST['clear'] ?? '') === 'yes') {
        $db->sql_query('TRUNCATE TABLE mailerrors');
        showAlert('success', '<i class="fas fa-trash"></i> Error log table has been completely cleared!');
        return;
    }
    
    if (($_POST['action'] ?? '') === 'delete' && !empty($_POST['logid'])) {
        $log_ids = array_filter($_POST['logid'], 'is_numeric');
        if (!empty($log_ids)) {
            $ids = implode(', ', array_map('intval', $log_ids));
            $db->sql_query("DELETE FROM mailerrors WHERE eid IN ($ids)");
            $deleted = $db->affected_rows();
            showAlert('success', 
                '<i class="fas fa-check-circle"></i> Successfully deleted ' . $deleted . ' ' . 
                pluralize($deleted, ['error entry', 'error entries', 'error entries']) . '!'
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
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h4 class="card-title mb-4">
                    <i class="fas fa-search me-2 text-danger"></i>Search Email Error Logs
                </h4>
                <form method="get" action="' . htmlspecialchars(($_this_script_no_act ?? '') . '?act=searchlog') . '" class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-exclamation-triangle text-muted"></i>
                            </span>
                            <input type="text" 
                                   name="query" 
                                   class="form-control border-start-0" 
                                   placeholder="Search by email, error message, or content..."
                                   value="' . $searchstr . '">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fas fa-search me-1"></i> Search Errors
                        </button>
                    </div>
                </form>
                <div class="mt-3 d-flex gap-2">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="clear" value="yes">
                        <button type="submit" class="btn btn-outline-danger btn-sm" 
                                onclick="return confirm(\'Are you sure you want to clear all error logs?\')">
                            <i class="fas fa-trash-alt me-1"></i> Clear All Error Logs
                        </button>
                    </form>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" 
                            data-bs-target="#helpSection">
                        <i class="fas fa-question-circle me-1"></i> Help
                    </button>
                </div>
                <div class="collapse mt-3" id="helpSection">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Search capabilities:</strong> Email addresses, error messages, and email content. 
                        Shows only failed email delivery attempts.
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Get total mail error count
 */
function getTotalMailErrorCount(): int
{
    global $db;
    
    $res = $db->sql_query('SELECT COUNT(*) as total FROM mailerrors');
    $row = $db->fetch_array($res);
    
    return (int)($row['total'] ?? 0);
}

/**
 * Get mail error logs
 */
function getMailErrorLogs(int $start, int $perpage): array
{
    global $db;
    
    $query = "SELECT eid, dateline, message, fromaddress, toaddress, error 
              FROM mailerrors 
              ORDER BY dateline DESC 
              LIMIT $start, $perpage";
    
    $res = $db->sql_query($query);
    $logs = [];
    
    while ($row = $db->fetch_array($res)) {
        $logs[] = $row;
    }
    
    return $logs;
}

/**
 * Render mail error logs table
 */
function renderMailErrorLogsTable(array $logs, int $total_count, string $multipage, $parser, array $parser_options): void
{
    global $_this_script_, $usergroups, $page, $perpage;
    
    echo '<div class="container mt-4">
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 bg-gradient-danger text-white shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0">Total Errors</h5>
                                <h2 class="mb-0">' . number_format($total_count) . '</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-gradient-warning text-white shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-envelope fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0">Failed Emails</h5>
                                <h2 class="mb-0">' . number_format($total_count) . '</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-light shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3 text-info">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0">Last Check</h5>
                                <h6 class="mb-0 text-muted">' . date('m/d/Y H:i:s') . '</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    
    // Top pagination
    if (!empty($multipage)) {
        echo '<div class="card mb-3 border-danger">
            <div class="card-body py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-exclamation-circle me-1 text-danger"></i>
                        Showing ' . (count($logs) > 0 ? (($page - 1) * $perpage + 1) : 0) . '-' . 
                        min(($page - 1) * $perpage + count($logs), $total_count) . ' of ' . $total_count . ' errors
                    </small>
                    <div class="pagination pagination-sm mb-0">
                        ' . $multipage . '
                    </div>
                </div>
            </div>
        </div>';
    }
    
    echo '<!-- Main table -->
        <div class="card shadow-lg border-danger">
            <div class="card-header bg-danger text-white border-0 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-fire me-2"></i>Email Delivery Failures
                    </h5>';
    
    if (!empty($logs) && ($usergroups['cansettingspanel'] ?? false)) {
        echo '<button type="button" class="btn btn-outline-light btn-sm" id="selectAllBtn">
                <i class="fas fa-check-square me-1"></i> Select All
            </button>';
    }
    
    echo '</div>
            </div>
            
            <form method="post" action="' . htmlspecialchars($_this_script_ ?? '') . '" id="mailErrorLogsForm">
                <input type="hidden" name="action" value="delete">
                
                <div class="table-responsive">';
    
    if (empty($logs)) {
        echo '<div class="text-center py-5">
                <div class="py-4">
                    <i class="fas fa-check-circle fa-4x text-success opacity-50 mb-3"></i>
                    <h4 class="text-success">No Email Errors Found</h4>
                    <p class="text-muted mb-0">Great! All emails are being delivered successfully.</p>
                </div>
            </div>';
    } else {
        echo '<table class="table table-hover align-middle mb-0">
                <thead class="table-danger">
                    <tr>
                        <th style="width: 50px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th style="width: 180px;">
                            <i class="fas fa-calendar-exclamation me-1"></i>Date & Time
                        </th>
                        <th>
                            <i class="fas fa-envelope-open-text me-1"></i>Error Details
                        </th>
                        <th style="width: 100px;" class="text-center">
                            <i class="fas fa-cog me-1"></i>Actions
                        </th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($logs as $log) {
            $date_formatted = my_datee('relative', $log['dateline']);
            $full_date = my_datee('M d, Y H:i:s', $log['dateline']);
            $message = $parser->parse_message($log['message'], $parser_options);
            $error_message = htmlspecialchars($log['error']);
            
            // Determine error severity
            $error_severity = getErrorSeverity($log['error']);
            $severity_badge = match($error_severity) {
                'critical' => '<span class="badge bg-danger">CRITICAL</span>',
                'high' => '<span class="badge bg-warning text-dark">HIGH</span>',
                'medium' => '<span class="badge bg-info">MEDIUM</span>',
                default => '<span class="badge bg-secondary">LOW</span>'
            };
            
            echo '<tr class="error-log-row" data-severity="' . $error_severity . '">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input error-checkbox" 
                               name="logid[]" value="' . (int)$log['eid'] . '">
                    </td>
                    <td>
                        <div class="error-datetime">
                            <div class="mb-1">
                                <span class="fw-medium text-dark">' . $date_formatted . '</span>
                            </div>
                            <div class="text-muted small">
                                <i class="fas fa-calendar-alt fa-xs me-1"></i>' . 
                                my_datee('M d, Y', $log['dateline']) . '
                            </div>
                            <div class="text-muted small">
                                <i class="fas fa-clock fa-xs me-1"></i>' . 
                                my_datee('H:i:s', $log['dateline']) . '
                            </div>
                            <div class="mt-1">
                                <small class="text-muted">
                                    <i class="fas fa-hashtag fa-xs me-1"></i>#' . (int)$log['eid'] . '
                                </small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="error-details">
                            <!-- Email addresses -->
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-paper-plane me-1"></i>From: ' . 
                                        htmlspecialchars($log['fromaddress']) . '
                                    </span>
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-inbox me-1"></i>To: ' . 
                                        htmlspecialchars($log['toaddress']) . '
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Error message -->
                            <div class="mb-3">
                                <div class="d-flex align-items-start mb-2">
                                    <div class="me-2 text-danger">
                                        <i class="fas fa-exclamation-circle"></i>
                                    </div>
                                    <div>
                                        <div class="fw-medium mb-1">Error Message</div>
                                        <div class="alert alert-danger py-2 px-3 mb-0">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="error-message-text">' . $error_message . '</div>
                                                <div class="ms-2">' . $severity_badge . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Email content -->
                            <div>
                                <div class="d-flex align-items-start">
                                    <div class="me-2 text-primary">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div class="fw-medium mb-1">Email Content</div>
                                        <div class="email-content-preview" style="max-height: 150px; overflow: hidden;">
                                            ' . $message . '
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm p-0 mt-1 show-email-btn">
                                            <i class="fas fa-chevron-down me-1"></i>Show Full Email
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="btn-group-vertical btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-info view-error-btn" 
                                    data-eid="' . (int)$log['eid'] . '"
                                    data-from="' . htmlspecialchars($log['fromaddress']) . '"
                                    data-to="' . htmlspecialchars($log['toaddress']) . '"
                                    data-date="' . htmlspecialchars($full_date) . '"
                                    data-error="' . htmlspecialchars($log['error']) . '"
                                    data-message="' . htmlspecialchars($log['message']) . '"
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-single-error" 
                                    data-id="' . (int)$log['eid'] . '"
                                    title="Delete Entry">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button type="button" class="btn btn-outline-warning retry-email-btn" 
                                    data-from="' . htmlspecialchars($log['fromaddress']) . '"
                                    data-to="' . htmlspecialchars($log['toaddress']) . '"
                                    data-message="' . htmlspecialchars($log['message']) . '"
                                    title="Retry Sending">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>
                    </td>
                </tr>';
        }
        
        echo '</tbody>
            </table>';
        
        // Control buttons
        if ($usergroups['cansettingspanel'] ?? false) {
            echo '<div class="card-footer bg-light border-danger border-top py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small" id="selectedCount">Selected: 0</span>
                            <span class="ms-3 text-danger small" id="errorStats"></span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="filterCriticalBtn">
                                <i class="fas fa-filter me-1"></i> Show Critical Only
                            </button>
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm(\'Delete selected error entries?\')"
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
    
    <!-- Error Details Modal -->
    <div class="modal fade" id="errorDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Email Error Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="errorDetailsContent">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-danger"></i>
                        <p class="mt-3">Loading error details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    
    ?>
	<!-- JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Select all checkboxes
    const selectAll = document.getElementById("selectAll");
    const selectAllBtn = document.getElementById("selectAllBtn");
    
    function toggleAllCheckboxes(checked) {
        document.querySelectorAll(".error-checkbox").forEach(cb => {
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
            const allChecked = Array.from(document.querySelectorAll(".error-checkbox"))
                .every(cb => cb.checked);
            toggleAllCheckboxes(!allChecked);
            if (selectAll) {
                selectAll.checked = !allChecked;
            }
        });
    }
    
    // Count selected and show stats
    function updateSelectedCount() {
        const selected = document.querySelectorAll(".error-checkbox:checked").length;
        const selectedCount = document.getElementById("selectedCount");
        const deleteBtn = document.getElementById("deleteSelectedBtn");
        const errorStats = document.getElementById("errorStats");
        
        if (selectedCount) {
            selectedCount.textContent = "Selected: " + selected;
        }
        
        if (deleteBtn) {
            deleteBtn.disabled = selected === 0;
        }
        
        // Calculate error severity stats
        if (errorStats && selected > 0) {
            const critical = Array.from(document.querySelectorAll(".error-checkbox:checked"))
                .filter(cb => cb.closest("tr").dataset.severity === "critical").length;
            if (critical > 0) {
                errorStats.textContent = "⚠ " + critical + " critical";
            } else {
                errorStats.textContent = "";
            }
        }
    }
    
    document.querySelectorAll(".error-checkbox").forEach(cb => {
        cb.addEventListener("change", updateSelectedCount);
    });
    
    // Expand/collapse email content
    document.querySelectorAll(".show-email-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            const content = this.closest("td").querySelector(".email-content-preview");
            if (content.style.maxHeight) {
                content.style.maxHeight = null;
                this.innerHTML = '<i class="fas fa-chevron-down me-1"></i>Show Full Email';
            } else {
                content.style.maxHeight = "none";
                this.innerHTML = '<i class="fas fa-chevron-up me-1"></i>Collapse Email';
            }
        });
    });
    
    // Delete single entry
    document.querySelectorAll(".delete-single-error").forEach(btn => {
        btn.addEventListener("click", function() {
            if (confirm("Delete this error entry?")) {
                const form = document.getElementById("mailErrorLogsForm");
                const checkbox = document.createElement("input");
                checkbox.type = "hidden";
                checkbox.name = "logid[]";
                checkbox.value = this.dataset.id;
                form.appendChild(checkbox);
                form.submit();
            }
        });
    });
    
    // Retry email sending
    document.querySelectorAll(".retry-email-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            if (confirm("Attempt to resend this email?")) {
                // AJAX call to resend email
                fetch('?act=retryemail', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        from: this.dataset.from,
                        to: this.dataset.to,
                        message: this.dataset.message
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Email resent successfully!");
                    } else {
                        alert("Failed to resend: " + data.error);
                    }
                })
                .catch(error => {
                    alert("Error: " + error);
                });
            }
        });
    });
    
    // View error details - ИСПРАВЛЕННАЯ ВЕРСИЯ
    document.querySelectorAll(".view-error-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            const modal = new bootstrap.Modal(document.getElementById("errorDetailsModal"));
            const content = document.getElementById("errorDetailsContent");
            
            // Создаем HTML контент безопасным способом
            const modalContent = `
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 text-danger">
                <i class="fas fa-paper-plane me-2"></i>Sender
            </div>
            <div class="card-body">
                <code class="text-break">${this.dataset.from}</code>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 text-danger">
                <i class="fas fa-inbox me-2"></i>Recipient
            </div>
            <div class="card-body">
                <code class="text-break">${this.dataset.to}</code>
            </div>
        </div>
    </div>
</div>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 text-danger">
                <i class="fas fa-calendar-alt me-2"></i>Error Time
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error occurred at:</strong> ${this.dataset.date}
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 text-danger">
                <i class="fas fa-exclamation-circle me-2"></i>Error Message
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <pre class="mb-0" style="white-space: pre-wrap;">${this.dataset.error}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 text-danger">
                <i class="fas fa-envelope me-2"></i>Original Email Content
            </div>
            <div class="card-body">
                <div class="bg-light p-3 rounded">
                    <pre style="white-space: pre-wrap;">${this.dataset.message}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row mt-4">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-lightbulb me-2"></i>
            <strong>Troubleshooting tips:</strong><br>
            1. Check email server configuration<br>
            2. Verify recipient email address<br>
            3. Check SMTP authentication settings<br>
            4. Review email content for invalid characters
        </div>
    </div>
</div>`;
            
            content.innerHTML = modalContent;
            modal.show();
        });
    });
    
    // Filter critical errors
    const filterCriticalBtn = document.getElementById("filterCriticalBtn");
    if (filterCriticalBtn) {
        let showOnlyCritical = false;
        filterCriticalBtn.addEventListener("click", function() {
            showOnlyCritical = !showOnlyCritical;
            document.querySelectorAll(".error-log-row").forEach(row => {
                if (showOnlyCritical) {
                    row.style.display = row.dataset.severity === "critical" ? "" : "none";
                    this.innerHTML = '<i class="fas fa-times me-1"></i> Show All';
                    this.classList.remove("btn-outline-secondary");
                    this.classList.add("btn-outline-danger");
                } else {
                    row.style.display = "";
                    this.innerHTML = '<i class="fas fa-filter me-1"></i> Show Critical Only';
                    this.classList.remove("btn-outline-danger");
                    this.classList.add("btn-outline-secondary");
                }
            });
        });
    }
    
    updateSelectedCount();
});
</script>

    <style>
    .error-log-row:hover {
        background-color: rgba(220, 53, 69, 0.05) !important;
    }
    .error-log-row[data-severity="critical"] {
        border-left: 4px solid #dc3545;
    }
    .error-log-row[data-severity="high"] {
        border-left: 4px solid #ffc107;
    }
    .error-log-row[data-severity="medium"] {
        border-left: 4px solid #0dcaf0;
    }
    .error-datetime {
        min-width: 140px;
    }
    .error-details {
        min-width: 500px;
    }
    .error-message-text {
        font-family: monospace;
        white-space: pre-wrap;
        word-break: break-all;
    }
    </style>

<?php

	
    

}

/**
 * Determine error severity
 */
function getErrorSeverity(string $error): string
{
    $error_lower = strtolower($error);
    
    if (str_contains($error_lower, 'connection refused') ||
        str_contains($error_lower, 'smtp error') ||
        str_contains($error_lower, 'timeout') ||
        str_contains($error_lower, 'authentication failed') ||
        str_contains($error_lower, 'server not responding')) {
        return 'critical';
    }
    
    if (str_contains($error_lower, 'invalid address') ||
        str_contains($error_lower, 'recipient rejected') ||
        str_contains($error_lower, 'quota exceeded') ||
        str_contains($error_lower, 'spam')) {
        return 'high';
    }
    
    if (str_contains($error_lower, 'temporary failure') ||
        str_contains($error_lower, 'try again') ||
        str_contains($error_lower, 'queue')) {
        return 'medium';
    }
    
    return 'low';
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