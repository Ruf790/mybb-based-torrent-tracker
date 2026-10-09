<?php
declare(strict_types=1);


require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_multipage.php';

// Access check
// (stays hardcoded: on direct access the language system is not initialised yet)
if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <strong>Error!</strong> Direct initialization of this file is not allowed.
    </div>');
}

$lang->load('logmails');

/**
 * Substitute {1}, {2}… (and %1$s, %2$s… produced by $lang->load()) placeholders
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

/**
 * Encode a string as a JS literal safe for use inside an HTML attribute
 */
if (!function_exists('ags_js_attr')) {
    function ags_js_attr(string $str): string
    {
        return htmlspecialchars(
            json_encode($str, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            ENT_QUOTES
        );
    }
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
stdhead($lang->logmails['page_title']);

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
renderMailLogsTable($mail_logs, $total_count, $multipage, $parser, $parser_options, $page, $perpage);

stdfoot();

/**
 * Handle mail log actions
 */
function handleMailLogActions(): void
{
    global $db, $usergroups, $_this_script_, $lang;
    
    if (!($usergroups['cansettingspanel'] ?? false)) {
        return;
    }
    
    if (($_POST['clear'] ?? '') === 'yes') {
        $db->sql_query_prepared('TRUNCATE TABLE maillogs');
        showAlert('success', '<i class="fas fa-trash"></i> ' . htmlspecialchars($lang->logmails['flash_cleared']));
        return;
    }
    
    if (($_POST['action'] ?? '') === 'delete' && !empty($_POST['logid'])) {
        $log_ids = array_filter($_POST['logid'], 'is_numeric');
        if (!empty($log_ids)) {
            $ids = implode(', ', array_map('intval', $log_ids));
            $db->sql_query_prepared("DELETE FROM maillogs WHERE mid IN ($ids)");
            $deleted = $db->affected_rows();
            showAlert('success', 
                '<i class="fas fa-check-circle"></i> ' . htmlspecialchars(ags_fmt(
                    $lang->logmails['flash_deleted'],
                    $deleted,
                    pluralize($deleted, explode('|', $lang->logmails['plural_entries']))
                ))
            );
        }
    }
}

/**
 * Show notification
 */
function showAlert(string $type, string $message): void
{
    global $lang;

    $icons = [
        'success' => 'fas fa-check-circle',
        'danger' => 'fas fa-exclamation-circle',
        'warning' => 'fas fa-exclamation-triangle',
        'info' => 'fas fa-info-circle'
    ];
    
    echo '<div class="container mt-3">
        <div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">
            <i class="' . ($icons[$type] ?? 'fas fa-info') . '"></i> ' . $message . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="' . htmlspecialchars($lang->logmails['aria_close']) . '"></button>
        </div>
    </div>';
}

/**
 * Render search form
 */
function renderSearchForm(): void
{
    global $_this_script_no_act, $lang;
    $L = $lang->logmails;
    $searchstr = htmlspecialchars($_GET['query'] ?? '');
    
    echo '<div class="container mt-4">
        <div class="card border-0">
            <div class="card-body">
                <h4 class="card-title mb-4">
                    <i class="fas fa-search me-2 text-primary"></i>' . htmlspecialchars($L['sec_search']) . '
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
                                   placeholder="' . htmlspecialchars($L['ph_search']) . '"
                                   aria-label="' . htmlspecialchars($L['aria_search']) . '"
                                   value="' . $searchstr . '">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> ' . htmlspecialchars($L['btn_search']) . '
                        </button>
                    </div>
                </form>
                <div class="mt-3 d-flex gap-2">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="clear" value="yes">
                        <button type="submit" class="btn btn-outline-danger btn-sm" 
                                onclick="return confirm(' . ags_js_attr($L['js_confirm_clear']) . ')">
                            <i class="fas fa-trash-alt me-1"></i> ' . htmlspecialchars($L['btn_clear_all']) . '
                        </button>
                    </form>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" 
                            data-bs-target="#helpSection">
                        <i class="fas fa-question-circle me-1"></i> ' . htmlspecialchars($L['btn_help']) . '
                    </button>
                </div>
                <div class="collapse mt-3" id="helpSection">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        ' . $L['hint_search'] . '
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
function renderMailLogsTable(array $logs, int $total_count, string $multipage, $parser, array $parser_options, int $page, int $perpage): void
{
    global $_this_script_, $usergroups, $lang, $BASEURL;
    $L = $lang->logmails;
    
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
                                <h5 class="card-title mb-0">' . htmlspecialchars($L['lbl_total']) . '</h5>
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
                                <h5 class="card-title mb-0">' . htmlspecialchars($L['lbl_last_update']) . '</h5>
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
                        ' . htmlspecialchars(ags_fmt(
                            $L['lbl_showing'],
                            count($logs) > 0 ? (($page - 1) * $perpage + 1) : 0,
                            min(($page - 1) * $perpage + count($logs), $total_count),
                            $total_count
                        )) . '
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
                        <i class="fas fa-history me-2 text-primary"></i>' . htmlspecialchars($L['sec_history']) . '
                    </h5>';
    
    if (!empty($logs) && ($usergroups['cansettingspanel'] ?? false)) {
        echo '<button type="button" class="btn btn-outline-primary btn-sm" id="selectAllBtn">
                <i class="fas fa-check-square me-1"></i> ' . htmlspecialchars($L['btn_select_all']) . '
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
                    <h4 class="text-muted">' . htmlspecialchars($L['empty_title']) . '</h4>
                    <p class="text-muted mb-0">' . htmlspecialchars($L['empty_text']) . '</p>
                </div>
            </div>';
    } else {
        echo '<table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAll" aria-label="' . htmlspecialchars($L['aria_select_all']) . '">
                        </th>
                        <th style="width: 180px;">
                            <i class="fas fa-calendar-alt me-1 text-muted"></i>' . htmlspecialchars($L['th_date']) . '
                        </th>
                        <th>
                            <i class="fas fa-envelope me-1 text-muted"></i>' . htmlspecialchars($L['th_message']) . '
                        </th>
                        <th style="width: 100px;" class="text-center">
                            <i class="fas fa-cog me-1 text-muted"></i>' . htmlspecialchars($L['th_actions']) . '
                        </th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($logs as $log) {
            $mid = (int)$log['mid'];
            $date = my_datee('relative', $log['dateline']);
            $message = $parser->parse_message($log['message'], $parser_options);
            $message_preview = mb_substr(strip_tags($message), 0, 100) . (mb_strlen(strip_tags($message)) > 100 ? '...' : '');
            
           
            
            echo '<tr class="mail-log-row">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input mail-checkbox" 
                               name="logid[]" value="' . $mid . '"
                               aria-label="' . htmlspecialchars(ags_fmt($L['aria_select_row'], $mid)) . '">
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="me-2 text-primary">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div class="fw-medium">' . $date . '</div>
                                <small class="text-muted">' . htmlspecialchars(ags_fmt($L['lbl_id'], $mid)) . '</small>
                            </div>
                        </div>
                    </td>
					
					
					
                    <td>
                        <div class="mail-preview">
                            <div class="mb-2">
                                <span class="badge bg-light text-dark me-2">
                                    <i class="fas fa-paper-plane me-1"></i>' . htmlspecialchars(ags_fmt($L['lbl_from'], (string)$log['fromemail'])) . '
                                </span>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-inbox me-1"></i>' . htmlspecialchars(ags_fmt($L['lbl_to'], (string)$log['toemail'])) . '
                                </span>
                            </div>
                            <div class="mail-content" style="max-height: 100px; overflow: hidden;">
                                ' . $message . '
                            </div>
                            <button type="button" class="btn btn-link btn-sm p-0 mt-1 show-more-btn">
                                <i class="fas fa-chevron-down me-1"></i>' . htmlspecialchars($L['js_show_more']) . '
                            </button>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-info view-mail-btn" 
                                    data-mid="' . $mid . '"
                                    data-from="' . htmlspecialchars($log['fromemail']) . '"
                                    data-to="' . htmlspecialchars($log['toemail']) . '"
                                    data-date="' . htmlspecialchars($date) . '"
                                    data-message="' . htmlspecialchars($message) . '"
                                    title="' . htmlspecialchars($L['title_view']) . '"
                                    aria-label="' . htmlspecialchars($L['title_view']) . '">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-single" 
                                    data-id="' . $mid . '"
                                    title="' . htmlspecialchars($L['title_delete']) . '"
                                    aria-label="' . htmlspecialchars($L['title_delete']) . '">
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
                            <span class="text-muted small" id="selectedCount">' . htmlspecialchars(ags_fmt($L['js_selected'], 0)) . '</span>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm(' . ags_js_attr($L['js_confirm_delete_selected']) . ')"
                                    disabled id="deleteSelectedBtn">
                                <i class="fas fa-trash me-1"></i> ' . htmlspecialchars($L['btn_delete_selected']) . '
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
                        <i class="fas fa-envelope-open-text me-2"></i>' . htmlspecialchars($L['modal_title']) . '
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . htmlspecialchars($L['aria_close']) . '"></button>
                </div>
                <div class="modal-body" id="mailDetailsContent">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-3">' . htmlspecialchars($L['lbl_loading']) . '</p>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    // JS strings: js_* keys → AGS_LANG without the prefix
    $js_lang = [];
    foreach ($L as $key => $value) {
        if (str_starts_with((string)$key, 'js_')) {
            $js_lang[substr((string)$key, 3)] = $value;
        }
    }

    echo '
    <script>
    const AGS_LANG = ' . json_encode($js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
    </script>
    <script src="' . htmlspecialchars((string)($BASEURL ?? '')) . '/admin/scripts/logmails.js?ver=1"></script>';
}

/**
 * Pluralize numbers
 * 2 forms (one|other) → English rule; 3 forms (one|few|many) → Slavic rule
 */
function pluralize(int $number, array $forms): string
{
    if (count($forms) === 2) {
        return $forms[$number === 1 ? 0 : 1];
    }
    $cases = [2, 0, 1, 1, 1, 2];
    return $forms[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
}
?>
