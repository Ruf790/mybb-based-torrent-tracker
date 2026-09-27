<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/datahandler.php';

$lang->load('user_awaiting_activation');

// Initialize input parameters
foreach (['action', 'do', 'module'] as $input) {
    $mybb->input[$input] ??= '';
}

$plugins->run_hooks('admin_user_awaiting_activation_begin');

if ($mybb->input['action'] === 'activate' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message('Security check failed. Please try again.', 'error');
        admin_redirect($_this_script_);
    }

    $plugins->run_hooks('admin_user_awaiting_activation_activate');

    process_user_activation();

    // Post/Redirect/Get — F5 never repeats the action
    admin_redirect($_this_script_);
}

display_awaiting_activation_page();

/* =====================================================================
 *  ACTIONS
 * ===================================================================== */

/**
 * Process user activation or deletion
 */
function process_user_activation(): void
{
    global $mybb, $lang;

    $raw = $mybb->input['user'] ?? [];
    $user_ids = array_values(array_unique(array_filter(
        array_map('intval', (array)$raw),
        static fn(int $id): bool => $id > 0
    )));

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
 * Process user deletion (only accounts that are still pending)
 */
function process_user_deletion(array $user_ids): void
{
    global $db, $lang, $cache, $plugins;

    $users_to_delete = [];
    $names = [];

    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $query = $db->sql_query_prepared(
        "SELECT id, username FROM users WHERE ustatus = 'pending' AND id IN ({$placeholders})",
        $user_ids
    );

    if ($query !== false) {
        while ($user = $db->fetch_array($query)) {
            $users_to_delete[] = (int)$user['id'];
            $names[] = htmlspecialchars_uni((string)$user['username']);
        }
    }

    if (empty($users_to_delete)) {
        flash_message('None of the selected accounts are pending anymore.', 'error');
        return;
    }

    require_once INC_PATH . '/datahandlers/user.php';
    $userhandler = new UserDataHandler('delete');
    $userhandler->delete_user($users_to_delete, true);

    $cache->update_awaitingactivation();
    $plugins->run_hooks('admin_user_awaiting_activation_activate_delete_commit');

    $num_deleted = count($users_to_delete);
    log_admin_action('deleted', $num_deleted);
    write_log('Awaiting activation: deleted ' . $num_deleted . ' pending account(s): ' . implode(', ', $names));

    flash_message($lang->user_awaiting_activation['success_users_deleted'], 'success');
}

/**
 * Process user activation (only accounts that are still pending)
 */
function process_user_activation_flow(array $user_ids): void
{
    global $db, $lang, $cache, $plugins;

    $activated = [];

    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $query = $db->sql_query_prepared(
        "SELECT id, username, email FROM users WHERE ustatus = 'pending' AND id IN ({$placeholders})",
        $user_ids
    );

    if ($query !== false) {
        while ($user = $db->fetch_array($query)) {
            activate_single_user($user);
            $activated[] = htmlspecialchars_uni((string)$user['username']);
        }
    }

    if (empty($activated)) {
        flash_message('None of the selected accounts are pending anymore.', 'error');
        return;
    }

    $cache->update_awaitingactivation();
    $plugins->run_hooks('admin_user_awaiting_activation_activate_commit');

    $num_activated = count($activated);
    log_admin_action('activated', $num_activated);
    write_log('Awaiting activation: activated ' . $num_activated . ' account(s): ' . implode(', ', $activated));

    flash_message($lang->user_awaiting_activation['success_users_activated'], 'success');
}

/**
 * Activate a single user and notify them by e-mail
 */
function activate_single_user(array $user): void
{
    global $db, $lang, $SITENAME, $BASEURL;

    $uid = (int)$user['id'];

    // The coppauser column is gone — just drop the queue entry.
    $db->sql_query_prepared('DELETE FROM awaitingactivation WHERE uid = ?', [$uid]);
    $db->sql_query_prepared(
        "UPDATE users SET ustatus = 'confirmed' WHERE id = ? AND ustatus = 'pending'",
        [$uid]
    );

    $message = sprintf(
        $lang->user_awaiting_activation['email_adminactivateaccount'],
        htmlspecialchars_uni((string)$user['username']),
        $SITENAME,
        $BASEURL
    );
    $subject = sprintf($lang->user_awaiting_activation['emailsubject_activateaccount'], $SITENAME);

    my_mail((string)$user['email'], $subject, $message);
}

/* =====================================================================
 *  PAGE
 * ===================================================================== */

/**
 * Display the awaiting activation page
 */
function display_awaiting_activation_page(): void
{
    global $db, $mybb, $plugins, $threadsperpage2, $_this_script_;

    $plugins->run_hooks('admin_user_awaiting_activation_start');

    $stats = get_awaiting_stats();
    $user_count = $stats['total'];

    // Pagination
    $perpage = max(20, (int)($threadsperpage2 ?? 20));
    $page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $pages = max(1, (int)ceil($user_count / $perpage));
    if ($page > $pages) {
        $page = 1;
    }
    $start = ($page - 1) * $perpage;

    $multipage = (string)multipage($user_count, $perpage, $page, "{$_this_script_}&amp;page={page}");

    stdhead();
    render_page_assets();

    echo '<div class="container my-3 ag-awaiting">';
    render_page_header($user_count);
    render_kpi_tiles($stats);
    render_user_table($start, $perpage, $user_count, $multipage);
    echo '</div>';

    render_page_script();
    stdfoot();
}

/**
 * Collect KPI numbers in one query
 */
function get_awaiting_stats(): array
{
    global $db;

    $stats = ['total' => 0, 'email' => 0, 'admin' => 0, 'oldest' => null];

    $query = $db->sql_query_prepared(
        "SELECT COUNT(DISTINCT u.id) AS total,
                COUNT(DISTINCT CASE WHEN a.type IN ('r','b') AND a.validated = 0 THEN u.id END) AS email_pending,
                MIN(u.added) AS oldest
         FROM users u
         LEFT JOIN awaitingactivation a ON (a.uid = u.id)
         WHERE u.ustatus = 'pending'"
    );

    if ($query !== false && ($row = $db->fetch_array($query))) {
        $stats['total']  = (int)($row['total'] ?? 0);
        $stats['email']  = (int)($row['email_pending'] ?? 0);
        $stats['admin']  = max(0, $stats['total'] - $stats['email']);
        $stats['oldest'] = $row['oldest'] ?? null;
    }

    return $stats;
}

/**
 * Render page header
 */
function render_page_header(int $user_count): void
{
    global $lang;

    $manage = $lang->user_awaiting_activation['manage_awaiting_activation'];

    echo <<<HTML
    <div class="ag-card ag-head mb-3">
        <div class="ag-icon-sq ag-soft-warning"><i class="fa-solid fa-user-clock"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="ag-title">Unconfirmed User Accounts</h1>
            <p class="ag-sub">
                <i class="fa-solid fa-circle-info me-1"></i>
                Manage pending registrations. Unconfirmed accounts are cleaned up automatically according to system settings.
            </p>
        </div>
        <span class="ag-chip ag-soft-primary d-none d-md-inline-flex">
            <i class="fa-solid fa-sliders"></i>{$manage}
        </span>
    </div>
HTML;
}

/**
 * Render the 4 KPI tiles
 */
function render_kpi_tiles(array $stats): void
{
    $oldest = $stats['oldest'] !== null && $stats['oldest'] !== ''
        ? my_datee('relative', $stats['oldest'])
        : '—';

    $tiles = [
        ['fa-hourglass-half',     'ag-soft-primary', number_format($stats['total']), 'Pending accounts'],
        ['fa-envelope-open-text', 'ag-soft-warning', number_format($stats['email']), 'Awaiting e-mail confirmation'],
        ['fa-user-shield',        'ag-soft-info',    number_format($stats['admin']), 'Awaiting staff activation'],
        ['fa-clock-rotate-left',  'ag-soft-danger',  $oldest,                        'Oldest request'],
    ];

    echo '<div class="ag-kpis mb-3">';
    foreach ($tiles as [$icon, $tone, $value, $label]) {
        echo <<<HTML
        <div class="ag-card ag-kpi">
            <div class="ag-icon-sq ag-icon-sm {$tone}"><i class="fa-solid {$icon}"></i></div>
            <div class="min-w-0">
                <div class="ag-kpi-val">{$value}</div>
                <div class="ag-kpi-label">{$label}</div>
            </div>
        </div>
HTML;
    }
    echo '</div>';
}

/**
 * Render user table (inside the activation form)
 */
function render_user_table(int $start, int $perpage, int $user_count, string $multipage): void
{
    global $db, $_this_script_, $mybb;

    $query = $db->sql_query_prepared(
        "SELECT u.id, u.username, u.added, u.regip, u.lastactive, u.email,
                a.type AS reg_type, a.validated
         FROM users u
         LEFT JOIN awaitingactivation a ON (a.uid = u.id)
         WHERE u.ustatus = 'pending'
         ORDER BY u.added DESC
         LIMIT ? OFFSET ?",
        [$perpage, $start]
    );

    $has_rows = $query !== false && $db->num_rows($query) > 0;
    $post_key = htmlspecialchars_uni((string)$mybb->post_code);

    echo <<<HTML
    <form action="{$_this_script_}&amp;action=activate" method="post" id="userActivationForm">
        <input type="hidden" name="my_post_key" value="{$post_key}" />
        <div class="ag-card overflow-hidden">
            <div class="ag-toolbar">
                <h2 class="ag-section-title">
                    <i class="fa-solid fa-list-check me-2"></i>Pending users
                    <span class="ag-chip ag-soft-secondary ms-1">{$user_count}</span>
                </h2>
HTML;

    if ($has_rows) {
        echo <<<HTML
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <label class="ag-search mb-0">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="agFilter" class="form-control form-control-sm" placeholder="Filter by name, e-mail or IP…" autocomplete="off">
                    </label>
                    <div class="form-check mb-0">
                        <input type="checkbox" id="selectAll" class="form-check-input">
                        <label for="selectAll" class="form-check-label small">Select all</label>
                    </div>
                </div>
HTML;
    }

    echo '</div>';

    if ($multipage !== '') {
        echo '<div class="ag-pages ag-pages-top">' . $multipage . '</div>';
    }

    if ($has_rows) {
        render_users_table_content($query);
    } else {
        render_empty_state();
    }

    if ($multipage !== '') {
        echo '<div class="ag-pages ag-pages-bottom">' . $multipage . '</div>';
    }

    echo '</div>';

    if ($has_rows) {
        render_action_buttons();
    }

    echo '</form>';
}

/**
 * Render users table content
 */
function render_users_table_content(object $query): void
{
    global $db;

    echo <<<HTML
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 ag-table">
            <thead>
                <tr>
                    <th class="text-center ag-col-check">
                        <input type="checkbox" id="mainCheckbox" class="form-check-input" aria-label="Select all">
                    </th>
                    <th><i class="fa-solid fa-user me-1"></i>User</th>
                    <th><i class="fa-solid fa-calendar-plus me-1"></i>Registered</th>
                    <th class="d-none d-lg-table-cell"><i class="fa-solid fa-clock me-1"></i>Last active</th>
                    <th class="d-none d-md-table-cell"><i class="fa-solid fa-network-wired me-1"></i>IP address</th>
                    <th><i class="fa-solid fa-signal me-1"></i>Status</th>
                    <th class="text-end"><i class="fa-solid fa-bolt me-1"></i>Actions</th>
                </tr>
            </thead>
            <tbody>
HTML;

    while ($user = $db->fetch_array($query)) {
        render_user_row($user);
    }

    echo <<<HTML
            </tbody>
        </table>
        <div id="agNoMatch" class="ag-nomatch" hidden>
            <i class="fa-solid fa-filter-circle-xmark me-2"></i>No users match the filter.
        </div>
    </div>
HTML;
}

/**
 * Render single user row
 */
function render_user_row(array $user): void
{
    $uid        = (int)$user['id'];
    $raw_name   = (string)$user['username'];
    $username   = htmlspecialchars_uni($raw_name);
    $profile    = build_profile_link($username, $uid, '_blank');
    $email      = htmlspecialchars_uni((string)$user['email']);
    $added      = my_datee('relative', $user['added']);
    $lastactive = my_datee('relative', $user['lastactive']);
    $ip_raw     = format_ip_address($user['regip'] ?? null);
    $ip_html    = $ip_raw === ''
        ? '<span class="text-body-secondary">N/A</span>'
        : '<span class="ag-ip">' . htmlspecialchars_uni($ip_raw) . '</span>';

    $initial = htmlspecialchars_uni(mb_strtoupper(mb_substr($raw_name, 0, 1)) ?: '?');
    $hue     = crc32($raw_name) % 360;
    $search  = htmlspecialchars_uni(mb_strtolower($raw_name . ' ' . $user['email'] . ' ' . $ip_raw));
    $status  = render_status_badge(is_email_pending($user));

    echo <<<HTML
                <tr data-search="{$search}">
                    <td class="text-center">
                        <input type="checkbox" name="user[{$uid}]" value="{$uid}" class="form-check-input user-checkbox" data-username="{$username}" aria-label="Select {$username}">
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <span class="ag-ava" style="--ag-hue: {$hue}">{$initial}</span>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{$profile}</div>
                                <a href="mailto:{$email}" class="ag-mail text-truncate">
                                    <i class="fa-solid fa-envelope me-1"></i>{$email}
                                </a>
                            </div>
                        </div>
                    </td>
                    <td class="ag-muted">{$added}</td>
                    <td class="ag-muted d-none d-lg-table-cell">{$lastactive}</td>
                    <td class="d-none d-md-table-cell">{$ip_html}</td>
                    <td>{$status}</td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="ag-row-btn ag-row-ok" data-row-action="activate" data-uid="{$uid}" title="Activate {$username}">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button type="button" class="ag-row-btn ag-row-del" data-row-action="delete" data-uid="{$uid}" title="Delete {$username}">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </td>
                </tr>
HTML;
}

/**
 * Is the user still waiting for their own e-mail confirmation?
 */
function is_email_pending(array $user): bool
{
    return in_array($user['reg_type'] ?? null, ['r', 'b'], true)
        && (int)($user['validated'] ?? 0) === 0;
}

/**
 * Format IP address (plain text, escaped by caller)
 */
function format_ip_address(?string $ip): string
{
    global $db;

    if ($ip === null || $ip === '') {
        return '';
    }

    return (string)my_inet_ntop($db->unescape_binary($ip));
}

/**
 * Render status badge
 */
function render_status_badge(bool $email_pending): string
{
    global $lang;

    if ($email_pending) {
        return '<span class="ag-chip ag-soft-warning"><i class="fa-solid fa-envelope-open-text"></i>Awaiting e-mail</span>';
    }

    return '<span class="ag-chip ag-soft-info"><i class="fa-solid fa-user-shield"></i>'
        . $lang->user_awaiting_activation['administrator_activation'] . '</span>';
}

/**
 * Render sticky action bar
 */
function render_action_buttons(): void
{
    global $lang;

    $activate = $lang->user_awaiting_activation['activate_users'];
    $delete   = $lang->user_awaiting_activation['delete_users'];

    echo <<<HTML
    <div class="ag-bar" id="agActionBar">
        <div class="d-flex align-items-center gap-2">
            <span class="ag-icon-sq ag-icon-xs ag-soft-secondary"><i class="fa-solid fa-check-double"></i></span>
            <span class="ag-muted">Selected:</span>
            <span id="selectedCount" class="selected-count ag-count">0</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-success ag-pill" data-ag-action="activate" disabled>
                <i class="fa-solid fa-circle-check me-2"></i>{$activate}
            </button>
            <button type="button" class="btn btn-outline-danger ag-pill" data-ag-action="delete" disabled>
                <i class="fa-solid fa-trash-can me-2"></i>{$delete}
            </button>
        </div>
    </div>
HTML;
}

/**
 * Render empty state
 */
function render_empty_state(): void
{
    echo <<<HTML
    <div class="ag-empty">
        <div class="ag-icon-sq ag-icon-lg ag-soft-success mx-auto mb-3"><i class="fa-solid fa-user-check"></i></div>
        <h3 class="ag-empty-title">No pending users</h3>
        <p class="ag-muted mb-0">Every account has been confirmed — nothing waiting in the queue.</p>
    </div>
HTML;
}

/* =====================================================================
 *  ASSETS
 * ===================================================================== */

/**
 * Scoped styles + SweetAlert2 stylesheet
 */
function render_page_assets(): void
{
   global $BASEURL;
   
   ?>

<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/awaiting_activation.css?ver=339">

<?php
}

/**
 * Page behaviour: selection, filter, SweetAlert2 confirmations
 */
function render_page_script(): void
{
    global $BASEURL;
    ?>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/awaiting_activation.js?ver=339"></script>
<?php
}