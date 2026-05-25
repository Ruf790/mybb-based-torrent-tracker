<?php


declare(strict_types=1);



// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/datahandler.php';

$lang->load('user_awaiting_activation');

// Initialize input parameters
$input_params = ['action', 'do', 'module'];
foreach ($input_params as $input) {
    $mybb->input[$input] ??= '';
}

$plugins->run_hooks("admin_user_awaiting_activation_begin");

if ($mybb->input['action'] == "activate" && $mybb->request_method == "post") {
    $plugins->run_hooks("admin_user_awaiting_activation_activate");

    $user_ids = process_user_activation();
    admin_redirect($_this_script_);
}

if (!$mybb->input['action']) {
    display_awaiting_activation_page();
}

/**
 * Process user activation or deletion
 */
function process_user_activation(): void
{
    global $mybb, $db, $lang, $cache, $plugins, $SITENAME, $BASEURL;

    $users = (array)$mybb->input['user'];
    $user_ids = implode(", ", array_map('intval', $users));

    if (empty($user_ids)) {
        flash_message($lang->user_awaiting_activation['no_users_selected'], 'error');
        return;
    }

    if (!empty($mybb->input['delete'])) {
        process_user_deletion($user_ids);
    } else {
        process_user_activation_flow($user_ids);
    }
}

/**
 * Process user deletion
 */
function process_user_deletion(string $user_ids): void
{
    global $db, $plugins;

    $num_deleted = 0;
    $users_to_delete = [];

    $query = $db->simple_select("users", "id, ustatus", "id IN ({$user_ids})");
    while ($user = $db->fetch_array($query)) {
        if ($user['ustatus'] == 'pending') {
            ++$num_deleted;
            $users_to_delete[] = (int)$user['id'];
        }
    }

    if (!empty($users_to_delete)) {
        require_once INC_PATH . '/datahandlers/user.php';
        $userhandler = new UserDataHandler('delete');
        $userhandler->delete_user($users_to_delete, true);
    }

    $plugins->run_hooks("admin_user_awaiting_activation_activate_delete_commit");
    log_admin_action('deleted', $num_deleted);
    flash_message($lang->user_awaiting_activation['success_users_deleted'], 'success');
}

/**
 * Process user activation
 */
function process_user_activation_flow(string $user_ids): void
{
    global $db, $lang, $cache, $plugins, $SITENAME, $BASEURL;

    $num_activated = 0;

    $query = $db->simple_select("users", "id, ustatus, username, email, usergroup", "id IN ({$user_ids})");
    while ($user = $db->fetch_array($query)) {
        ++$num_activated;
        activate_single_user($user);
    }

    $cache->update_awaitingactivation();
    $plugins->run_hooks("admin_user_awaiting_activation_activate_commit");
    log_admin_action('activated', $num_activated);
    flash_message($lang->user_awaiting_activation['success_users_activated'], 'success');
}

/**
 * Activate a single user
 */
function activate_single_user(array $user): void
{
    global $db, $lang, $SITENAME, $BASEURL;

    $updated_user = [];

    if ($user['coppauser']) {
        $updated_user["coppauser"] = 0;
    } else {
        $db->delete_query("awaitingactivation", "uid='{$user['id']}'");
    }

    if ($user['ustatus'] == 'pending') {
        $updated_user['ustatus'] = 'confirmed';
    }

    if (!empty($updated_user)) {
        $db->update_query("users", $updated_user, "id='{$user['id']}'");
    }

    $message = sprintf(
        $lang->user_awaiting_activation['email_adminactivateaccount'],
        $user['username'],
        $SITENAME,
        $BASEURL
    );

    $subject = sprintf($lang->user_awaiting_activation['emailsubject_activateaccount'], $SITENAME);
    my_mail($user['email'], $subject, $message);
}

/**
 * Display the awaiting activation page
 */
function display_awaiting_activation_page(): void
{
    global $db, $mybb, $lang, $plugins, $threadsperpage2, $_this_script_;

    $plugins->run_hooks("admin_user_awaiting_activation_start");

    // Get user count
    $query = $db->simple_select("users", "COUNT(*) AS users", "ustatus='pending'");
    $user_count = (int)$db->fetch_field($query, "users");

    // Pagination setup
    $perpage = max(20, (int)($threadsperpage2 ?? 20));
    $page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start = ($page - 1) * $perpage;
    $pages = (int)ceil($user_count / $perpage);

    if ($page > $pages) {
        $start = 0;
        $page = 1;
    }

    $multipage = multipage($user_count, $perpage, $page, "{$_this_script_}&amp;page={page}");

    stdhead();
    render_page_header();
    render_pagination_top($multipage);
    render_user_table($start, $perpage, $user_count);
    render_pagination_bottom($multipage);
    stdfoot();
}

/**
 * Render page header
 */
function render_page_header(): void
{
    global $lang;

    echo '
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-users-cog me-2"></i>
                                Unconfirmed User Accounts
                            </h4>
                            <span class="badge bg-light text-primary fs-6">' . $lang->user_awaiting_activation['manage_awaiting_activation'] . '</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Manage pending user registrations. Accounts awaiting confirmation will be automatically cleaned up based on system settings.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Render top pagination
 */
function render_pagination_top(string $multipage): void
{
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
}

/**
 * Render user table
 */
function render_user_table(int $start, int $perpage, int $user_count): void
{
    global $db, $lang, $_this_script_, $mybb;

    $query = $db->sql_query("
        SELECT u.id, u.username, u.added, u.regip, u.lastactive, u.email,
               a.type AS reg_type, a.validated
        FROM users u
        LEFT JOIN awaitingactivation a ON (a.uid = u.id)
        WHERE u.ustatus = 'pending'
        ORDER BY u.added DESC
        LIMIT {$start}, {$perpage}
    ");

    echo '
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">
                <form action="' . $_this_script_ . '&action=activate" method="post" id="userActivationForm">
                    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
                    
                    <div class="card shadow-sm">
                        <div class="card-header bg-light py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-dark">
                                    <i class="fas fa-list me-2"></i>
                                    Pending Users (' . $user_count . ')
                                </h5>
                                <div class="form-check">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                    <label for="selectAll" class="form-check-label small">Select All</label>
                                </div>
                            </div>
                        </div>';

    if ($db->num_rows($query) > 0) {
        render_users_table_content($query);
        render_action_buttons();
    } else {
        render_empty_state();
    }

    echo '
                    </div>
                </form>
            </div>
        </div>
    </div>';
}

/**
 * Render users table content
 */
function render_users_table_content(mysqli_result $query): void
{
    global $db, $lang;

    echo '
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th width="40" class="text-center">
                        <input type="checkbox" id="mainCheckbox" class="form-check-input">
                    </th>
                    <th>User</th>
                    <th>Registration</th>
                    <th>Last Active</th>
                    <th>Contact</th>
                    <th>IP Address</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

    while ($user = $db->fetch_array($query)) {
        render_user_row($user);
    }

    echo '
            </tbody>
        </table>
    </div>';
}

/**
 * Render single user row
 */
function render_user_row(array $user): void
{
    $user['username'] = htmlspecialchars_uni($user['username']);
    $user['profilelink'] = build_profile_link($user['username'], $user['id'], "_blank");
    $user['email'] = htmlspecialchars_uni($user['email']);
    $user['added'] = my_datee('relative', $user['added']);
    $user['lastactive'] = my_datee('relative', $user['lastactive']);

    $user_type = determine_user_type($user);
    $ip_address = format_ip_address($user['regip']);
    $row_class = alt_trow();

    echo '
                <tr class="' . $row_class . '">
                    <td class="text-center">
                        <input type="checkbox" name="user[' . $user['id'] . ']" value="' . $user['id'] . '" class="form-check-input user-checkbox">
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-circle text-muted fs-5"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <strong>' . $user['profilelink'] . '</strong>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="text-muted small">' . $user['added'] . '</span>
                    </td>
                    <td>
                        <span class="text-muted small">' . $user['lastactive'] . '</span>
                    </td>
                    <td>
                        <a href="mailto:' . $user['email'] . '" class="text-decoration-none">
                            <i class="fas fa-envelope me-1 text-primary"></i>
                            ' . $user['email'] . '
                        </a>
                    </td>
                    <td>
                        <code class="text-muted small">' . $ip_address . '</code>
                    </td>
                    <td>
                        ' . render_status_badge($user_type) . '
                    </td>
                </tr>';
}

/**
 * Determine user type
 */
function determine_user_type(array $user): string
{
    global $lang;

    if (($user['reg_type'] == 'r' || $user['reg_type'] == 'b') && $user['validated'] == 0) {
        return 'Awaiting Email Activation';
    } elseif ($user['coppauser'] == 1) {
        return $lang->user_awaiting_activation['admin_activation_coppa'];
    } else {
        return $lang->user_awaiting_activation['administrator_activation'];
    }
}

/**
 * Format IP address
 */
function format_ip_address(?string $ip): string
{
    global $db;

    if (empty($ip)) {
        return '<span class="text-muted">N/A</span>';
    }

    return my_inet_ntop($db->unescape_binary($ip));
}

/**
 * Render status badge
 */
function render_status_badge(string $type): string
{
    $badge_class = match (true) {
        str_contains($type, 'Email') => 'bg-warning text-dark',
        str_contains($type, 'COPPA') => 'bg-danger text-white',
        default => 'bg-info text-white'
    };

    return '<span class="badge ' . $badge_class . ' small">' . $type . '</span>';
}

/**
 * Render action buttons
 */
function render_action_buttons(): void
{
    global $lang;

    echo '
    <div class="card-footer bg-light">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Selected:</span>
                <span id="selectedCount" class="selected-count" style="display: none;">0</span>
            </div>
            <div class="d-flex gap-3">
                <button type="button" class="btn btn-success px-4 py-2" onclick="confirmAction(\'activate\')">
                    <i class="fas fa-check-circle me-2"></i>
                    ' . $lang->user_awaiting_activation['activate_users'] . '
                </button>
                <button type="button" class="btn btn-danger px-4 py-2" onclick="confirmAction(\'delete\')">
                    <i class="fas fa-trash-alt me-2"></i>
                    ' . $lang->user_awaiting_activation['delete_users'] . '
                </button>
            </div>
        </div>
    </div>';
}

/**
 * Render empty state
 */
function render_empty_state(): void
{
    echo '
    <div class="card-body text-center py-5">
        <div class="empty-state">
            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No Pending Users</h4>
            <p class="text-muted mb-0">All user accounts have been confirmed.</p>
        </div>
    </div>';
}

/**
 * Render bottom pagination
 */
function render_pagination_bottom(string $multipage): void
{
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
}

// JavaScript for enhanced functionality
echo '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Enhanced select all functionality
    const mainCheckbox = document.getElementById("mainCheckbox");
    const selectAll = document.getElementById("selectAll");
    const userCheckboxes = document.querySelectorAll(".user-checkbox");
    
    function updateSelectAllState() {
        const checkedCount = document.querySelectorAll(".user-checkbox:checked").length;
        const totalCount = userCheckboxes.length;
        
        if (selectAll) {
            selectAll.checked = checkedCount === totalCount && totalCount > 0;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < totalCount;
        }
        if (mainCheckbox) {
            mainCheckbox.checked = checkedCount === totalCount && totalCount > 0;
            mainCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
        }
        
        // Update selected count display
        const selectedCountEl = document.getElementById("selectedCount");
        if (selectedCountEl) {
            selectedCountEl.textContent = checkedCount;
            selectedCountEl.style.display = checkedCount > 0 ? "inline" : "none";
        }
    }
    
    if (mainCheckbox) {
        mainCheckbox.addEventListener("change", function() {
            const isChecked = this.checked;
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateSelectAllState();
        });
    }
    
    if (selectAll) {
        selectAll.addEventListener("change", function() {
            const isChecked = this.checked;
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateSelectAllState();
        });
    }
    
    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener("change", updateSelectAllState);
    });
    
    // Initialize selected count
    updateSelectAllState();
});

function confirmAction(action) {
    const selected = document.querySelectorAll(".user-checkbox:checked").length;
    if (selected === 0) {
        showAlertModal("No Users Selected", "Please select at least one user to proceed.");
        return false;
    }
    
    showConfirmationModal(action, selected);
    return false;
}

function showConfirmationModal(action, selectedCount) {
    const isActivate = action === "activate";
    const title = isActivate ? "Activate Users" : "Delete Users";
    const icon = isActivate ? "check-circle" : "exclamation-triangle";
    const iconColor = isActivate ? "text-success" : "text-danger";
    const buttonColor = isActivate ? "btn-success" : "btn-danger";
    const buttonText = isActivate ? "Activate Users" : "Delete Users";
    const buttonIcon = isActivate ? "check" : "trash-alt";
    
    const message = isActivate 
        ? `You are about to activate <strong>${selectedCount}</strong> user(s). This will allow them to access the forum.`
        : `You are about to permanently delete <strong>${selectedCount}</strong> user(s). <span class="text-danger fw-semibold">This action cannot be undone!</span>`;

    const modalHTML = `
        <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-0 pb-0">
                        <div class="d-flex align-items-center w-100">
                            <div class="flex-shrink-0">
                                <i class="fas fa-${icon} ${iconColor} fa-2x"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="modal-title fw-bold text-dark" id="confirmationModalLabel">${title}</h5>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <div class="modal-body pt-0">
                        <div class="alert ${isActivate ? "alert-info" : "alert-warning"} border-0 mb-0">
                            <div class="d-flex">
                                <i class="fas fa-info-circle me-2 mt-1"></i>
                                <div>${message}</div>
                            </div>
                        </div>
                        ${!isActivate ? `
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="confirmDelete">
                            <label class="form-check-label text-danger fw-semibold small" for="confirmDelete">
                                I understand this action is permanent and cannot be undone
                            </label>
                        </div>
                        ` : ""}
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" id="confirmActionBtn" class="btn ${buttonColor} px-4" ${isActivate ? "" : "disabled"}>
                            <i class="fas fa-${buttonIcon} me-2"></i>${buttonText}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal
    const existingModal = document.getElementById("confirmationModal");
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML("beforeend", modalHTML);

    const modalElement = document.getElementById("confirmationModal");
    const modal = new bootstrap.Modal(modalElement);
    const confirmBtn = document.getElementById("confirmActionBtn");
    const confirmDeleteCheckbox = document.getElementById("confirmDelete");

    // Handle delete confirmation checkbox
    if (!isActivate && confirmDeleteCheckbox) {
        confirmDeleteCheckbox.addEventListener("change", function() {
            confirmBtn.disabled = !this.checked;
        });
    }

    // Confirm button handler
    confirmBtn.addEventListener("click", function() {
        modal.hide();
        setTimeout(() => {
            const form = document.getElementById("userActivationForm");
            if (form) {
                if (!isActivate) {
                    // Add delete parameter for deletion
                    const deleteInput = document.createElement("input");
                    deleteInput.type = "hidden";
                    deleteInput.name = "delete";
                    deleteInput.value = "1";
                    form.appendChild(deleteInput);
                }
                form.submit();
            }
        }, 300);
    });

    // Keyboard support
    modalElement.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
            modal.hide();
        }
    });

    // Clean up on hide
    modalElement.addEventListener("hidden.bs.modal", function() {
        this.remove();
    });

    modal.show();
}

function showAlertModal(title, message) {
    const modalHTML = `
        <div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-0 pb-0">
                        <div class="d-flex align-items-center w-100">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-warning fa-2x"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="modal-title fw-bold text-dark" id="alertModalLabel">${title}</h5>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0 text-muted">${message}</p>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">
                            <i class="fas fa-check me-2"></i>OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById("alertModal");
    if (existingModal) {
        existingModal.remove();
    }

    document.body.insertAdjacentHTML("beforeend", modalHTML);

    const modalElement = document.getElementById("alertModal");
    const modal = new bootstrap.Modal(modalElement);

    modalElement.addEventListener("keydown", function(e) {
        if (e.key === "Escape" || e.key === "Enter") {
            modal.hide();
        }
    });

    modalElement.addEventListener("hidden.bs.modal", function() {
        this.remove();
    });

    modal.show();
}

// Add enhanced styles
const modalStyles = `
<style>
.modal-content {
    border-radius: 12px;
    border: none;
}
.modal-header {
    padding: 1.5rem 1.5rem 0.5rem;
}
.modal-body {
    padding: 1rem 1.5rem;
}
.modal-footer {
    padding: 1rem 1.5rem 1.5rem;
}
.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
}
.alert {
    border-radius: 8px;
    border: none;
}
.form-check-input:checked {
    background-color: #dc3545;
    border-color: #dc3545;
}
.selected-count {
    background: #e9ecef;
    border-radius: 12px;
    padding: 4px 8px;
    font-size: 0.875rem;
    font-weight: 500;
}
</style>
`;

if (!document.getElementById("modalStyles")) {
    const styleElement = document.createElement("style");
    styleElement.id = "modalStyles";
    styleElement.textContent = modalStyles;
    document.head.appendChild(styleElement);
}
</script>';