<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define('W_VERSION', '0.8');

include_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_multipage.php';

$action = match(true) {
    isset($_POST['action']) => htmlspecialchars($_POST['action']),
    isset($_GET['action']) => htmlspecialchars($_GET['action']),
    default => 'showlist'
};

if ($action === 'remove' && isset($_POST['userid'])) {
    global $mybb;
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        stderr('Security check failed. Please refresh the page and try again.');
    }

    $userIds = array_map('intval', $_POST['userid']);
    
    if (!empty($userIds)) {
        // Создаем плейсхолдеры для каждого ID
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $db->sql_query_prepared(
            "UPDATE users 
             SET warned = 'no', leechwarn = 'no', warneduntil = '0', leechwarnuntil = '0' 
             WHERE id IN ($placeholders)",
            $userIds
        );
    }
    
    $action = 'showlist';
}

if ($action === 'showlist') {
    stdhead('Warned Users');
    
    // Get total count of warned users с использованием prepared statement
    $res = $db->sql_query_prepared(
        "SELECT COUNT(id) AS cnt FROM users 
         WHERE enabled = 'yes' 
         AND usergroup != ? 
         AND (warned = 'yes' OR leechwarn = 'yes')",
        [UC_BANNED]
    );
    
    $countrows = 0;
    if ($res !== false) {
        $row = $db->fetch_array($res);
        $countrows = ts_nf((int) ($row['cnt'] ?? 0));
    }

    // Pagination setup
    $ts_perpage = $ts_perpage ?: 20;
    $perpage = max(1, (int)$ts_perpage);
    
    $page = max(1, (int)($mybb->input['page'] ?? 1));
    $start = ($page - 1) * $perpage;
    
    $pages = (int)ceil($countrows / $perpage);
    if ($page > $pages) {
        $page = 1;
        $start = 0;
    }
    
    $lower = $start + 1;
    $upper = min($start + $perpage, $countrows);
    
    $multipage = multipage(
        (int)$countrows, 
        (int)$perpage, 
        (int)$page, 
        $_this_script_ . '&action=showlist'
    );

    // Display header and pagination
    echo '
    <div class="container-md">
        <div class="card border-0 mb-4">
            <div class="card-header bg-primary text-white rounded-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Warned Users
                    </h5>
                    <span class="badge bg-light text-dark">' . $countrows . ' users</span>
                </div>
            </div>
        </div>
    </div>';

    echo '
    <div class="container mt-3">
        ' . $multipage . '
    </div>';

    echo '
    <style>
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }
    
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }
    
    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .slider {
        background-color: #dc3545;
    }
    
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    
    .slider:hover {
        box-shadow: 0 0 5px rgba(0,0,0,0.3);
    }
    
    /* Стиль для главного переключателя "Select All" */
    .master-switch .slider {
        background-color: #6c757d;
    }
    
    .master-switch input:checked + .slider {
        background-color: #28a745;
    }
    </style>';

    echo '
    <script>
    function select_deselectAll(checkbox) {
        const checkboxes = document.querySelectorAll(\'input[name="userid[]"]\');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
            // Обновляем визуальное состояние переключателей
            cb.dispatchEvent(new Event(\'change\'));
        });
    }
    
    // Инициализация переключателей при загрузке страницы
    document.addEventListener(\'DOMContentLoaded\', function() {
        const checkboxes = document.querySelectorAll(\'input[name="userid[]"]\');
        checkboxes.forEach(cb => {
            cb.addEventListener(\'change\', function() {
                // Можно добавить дополнительную логику при изменении состояния
                console.log(\'Checkbox changed:\', this.checked, this.value);
            });
        });
    });
    </script>';

    echo '
    <form method="post" action="' . $_this_script_ . '" name="update" id="warnedForm">
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) . '">
        
        <div class="container mt-3">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-user me-1"></i>User</th>
                                    <th><i class="fas fa-calendar-plus me-1"></i>Registered</th>
                                    <th><i class="fas fa-clock me-1"></i>Last Access</th>
                                    <th><i class="fas fa-download me-1"></i>DL</th>
                                    <th><i class="fas fa-upload me-1"></i>UL</th>
                                    <th><i class="fas fa-percentage me-1"></i>Ratio</th>
                                    <th><i class="fas fa-calendar-times me-1"></i>Until</th>
                                    <th><i class="fas fa-tag me-1"></i>Type</th>
                                    <th class="text-center">
                                        <label class="switch master-switch" title="Select All">
                                            <input type="checkbox" onclick="select_deselectAll(this)">
                                            <span class="slider"></span>
                                        </label>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>';

    // Fetch warned users с использованием prepared statement
    $query = $db->sql_query_prepared(
        "SELECT u.* 
         FROM users u 
         WHERE u.usergroup != ? 
           AND u.enabled = 'yes' 
           AND (u.warned = 'yes' OR u.leechwarn = 'yes') 
         ORDER BY u.added DESC 
         LIMIT ?, ?",
        [UC_BANNED, $start, $perpage]
    );

    if ($query === false || $db->num_rows($query) === 0) {
        echo '
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                                            No Warned Users Found
                                        </div>
                                    </td>
                                </tr>';
    } else {
        require_once INC_PATH . '/functions_mkprettytime.php';
        
        while ($res = $db->fetch_array($query)) {
            $icons = get_user_icons($res);
            $user = '<a href="' . $BASEURL . '/' . get_profile_link($res['id']) . '" class="text-decoration-none">' . 
                    format_name($res['username'], $res['usergroup']) . '</a>' . $icons;
            
            $registered = my_datee($dateformat, $res['added']);
            $lastaccess = my_datee($dateformat, $res['lastactive']) . '<br><small class="text-muted">' . 
                         my_datee($timeformat, $res['lastactive']) . '</small>';
            
            $downloaded = mksize($res['downloaded']);
            $uploaded = mksize($res['uploaded']);
            
            $ratio = ($res['downloaded'] != 0 ? number_format($res['uploaded'] / $res['downloaded'], 3) : '---');
            $ratioColor = get_ratio_color((float)$ratio);
            $ratio = '<span style="color: ' . $ratioColor . '">' . $ratio . '</span>';
            
            // Warning expiration handling
            $warneduntil = '';
            if ($res['warneduntil'] !== '0000-00-00 00:00:00') {
                $timeLeft = $res['warneduntil'] - TIMENOW;
                $warneduntil = my_datee($dateformat, $res['warneduntil']) . 
                              '<br><small class="text-muted">(' . mkprettytime($timeLeft) . ' remaining)</small>';
            } elseif ($res['leechwarnuntil'] !== '0000-00-00 00:00:00') {
                $timeLeft = $res['leechwarnuntil'] - TIMENOW;
                $warneduntil = my_datee($dateformat, $res['leechwarnuntil']) . 
                              '<br><small class="text-muted">(' . mkprettytime($timeLeft) . ' remaining)</small>';
            } else {
                $warneduntil = '<span class="badge bg-warning text-dark">Arbitrary Duration</span>';
            }
            
            // Warning type with badges
            $warntype = match(true) {
                $res['warned'] === 'yes' => '<span class="badge bg-warning"><i class="fas fa-exclamation-circle me-1"></i>Normal</span>',
                $res['leechwarn'] === 'yes' => '<span class="badge bg-danger"><i class="fas fa-skull-crossbones me-1"></i>Leech Warn</span>',
                default => '<span class="badge bg-secondary">Unknown</span>'
            };
            
            $removeCheckbox = '
                                        <label class="switch" title="Remove Warning">
                                            <input type="checkbox" name="userid[]" value="' . $res['id'] . '">
                                            <span class="slider"></span>
                                        </label>';

            echo '
                                <tr>
                                    <td>' . $user . '</td>
                                    <td>' . $registered . '</td>
                                    <td>' . $lastaccess . '</td>
                                    <td><span class="text-danger">' . $downloaded . '</span></td>
                                    <td><span class="text-success">' . $uploaded . '</span></td>
                                    <td><strong>' . $ratio . '</strong></td>
                                    <td>' . $warneduntil . '</td>
                                    <td class="text-center">' . $warntype . '</td>
                                    <td class="text-center">
                                        ' . $removeCheckbox . '
                                    </td>
                                </tr>';
        }

        echo '
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="9" class="text-end py-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            ' . $multipage . '
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-trash-alt me-1"></i>Remove Selected Warnings
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>';
    }

    echo '
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>';
    
    stdfoot();
}
?>