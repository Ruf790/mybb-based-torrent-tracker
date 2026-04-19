<?php


declare(strict_types=1);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error! Direct initialization of this file is not allowed.</div>');
}

define('FNC_VERSION', '1.0 by xam');

// Get action with null coalescing
$do = (string)($_GET['do'] ?? $_POST['do'] ?? '');
$lang->load('findnotconnectable');

$PMSEND = false;
$errors = [];

// Delete log entry
if ($do === 'delete' && is_valid_id($_GET['id'] ?? 0)) {
    $DelID = (int)$_GET['id'];
    $db->sql_query('DELETE FROM notconnectablepmlog WHERE id = ' . $DelID) or sqlerr(__FILE__, 29);
    $do = '';
}

// Send PM to selected users
if ($do === 'pm2' && is_array($_POST['userids'] ?? null)) {
    $msg = trim($lang->findnotconnectable['msg'] ?? '');
    
    if ($msg !== '') {
        require_once INC_PATH . '/functions_pm.php';
        
        foreach ((array)$_POST['userids'] as $userid) {
            if (is_valid_id((int)$userid)) {
                send_pm((int)$userid, $msg, $lang->findnotconnectable['subject']);
            }
        }
        
        $db->sql_query('INSERT INTO notconnectablepmlog VALUES (NULL, ' . (int)$CURUSER['id'] . ', NOW())') or sqlerr(__FILE__, 48);
        $PMSEND = true;
        $do = '';
    } else {
        $errors[] = $lang->findnotconnectable['error1'];
        $do = 'showlist';
    }
} elseif ($do === 'pm2') {
    $errors[] = $lang->findnotconnectable['error3'];
    $do = 'showlist';
}

// Show list of unconnectable peers
if ($do === 'showlist') {
    $query = $db->sql_query('SELECT DISTINCT id FROM peers WHERE connectable = "no"');
    $count = (int)$db->num_rows($query);
    
    list($pagertop, $pagerbottom, $limit) = pager(25, $count, $_this_script_ . '&amp;do=showlist&amp;');
    
    $query = $db->sql_query('
        SELECT DISTINCT p.torrent, p.userid, p.ip, p.port, p.seeder, p.agent, t.name, u.username, g.namestyle 
        FROM peers p 
        LEFT JOIN torrents t ON (p.torrent = t.id) 
        LEFT JOIN users u ON (p.userid = u.id) 
        LEFT JOIN usergroups g ON (u.usergroup = g.gid) 
        WHERE p.connectable = "no" 
        ORDER BY u.username ' . $limit
    );
    
    if ($db->num_rows($query) > 0) {
        $str = $pagertop . '
        <div class="table-responsive">
            <form method="POST" action="' . $_this_script_ . '&amp;do=pm2" name="userlist" id="userlistForm">
                <table class="table table-hover table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th><i class="fas fa-user me-2"></i>' . $lang->findnotconnectable['username'] . '</th>
                            <th><i class="fas fa-torrent me-2"></i>' . $lang->findnotconnectable['torrent'] . '</th>
                            <th><i class="fas fa-network-wired me-2"></i>' . $lang->findnotconnectable['ip'] . '</th>
                            <th><i class="fas fa-laptop-code me-2"></i>' . $lang->findnotconnectable['client'] . '</th>
                            <th class="text-center"><i class="fas fa-cloud-upload-alt me-2"></i>' . $lang->findnotconnectable['seeder'] . '</th>
                            <th class="text-center"><input type="checkbox" id="selectAll" onclick="toggleAll(this)" class="form-check-input"></th>
                        </tr>
                    </thead>
                    <tbody>';
        
        while ($Users = mysqli_fetch_assoc($query)) {
            $isSeeder = $Users['seeder'] == 'yes';
            $str .= '
                        <tr>
                            <td><i class="fas fa-user-circle text-primary me-2"></i><a href="' . ts_seo($Users['userid'], $Users['username']) . '">' . get_user_color($Users['username'], $Users['namestyle']) . '</a></td>
                            <td><i class="fas fa-file-alt text-info me-2"></i><a href="' . $BASEURL . '/details.php?id=' . $Users['torrent'] . '">' . cutename($Users['name'], 60) . '</a></td>
                            <td><code><i class="fas fa-globe me-2"></i>' . htmlspecialchars_uni($Users['ip']) . ':' . htmlspecialchars_uni($Users['port']) . '</code></td>
                            <td><i class="fas fa-desktop me-2"></i>' . htmlspecialchars_uni($Users['agent']) . '</td>
                            <td class="text-center">' . ($isSeeder ? '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Yes</span>' : '<span class="badge bg-secondary"><i class="fas fa-times me-1"></i>No</span>') . '</td>
                            <td class="text-center"><input type="checkbox" name="userids[]" value="' . $Users['userid'] . '" class="userCheckbox form-check-input"></td>
                        </tr>';
        }
        
        $str .= '
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>' . $lang->findnotconnectable['pm3'] . '
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </form>
        </div>
        ' . $pagerbottom;
    } else {
        $errors[] = $lang->findnotconnectable['error2'];
        $do = '';
    }
}

// Send PM to all unconnectable users
if ($do === 'pm') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $msg = trim($_POST['msg'] ?? '');
        
        if ($msg !== '') {
            $query = $db->sql_query('SELECT DISTINCT userid FROM peers WHERE connectable = "no"');
            
            if ($db->num_rows($query) > 0) {
                require_once INC_PATH . '/functions_pm.php';
                
                while ($PM = mysqli_fetch_assoc($query)) {
                    send_pm((int)$PM['userid'], $msg, $lang->findnotconnectable['subject']);
                }
                
                $db->sql_query('INSERT INTO notconnectablepmlog VALUES (NULL, ' . (int)$CURUSER['id'] . ', NOW())');
                $PMSEND = true;
            } else {
                $errors[] = $lang->findnotconnectable['error2'];
            }
        } else {
            $errors[] = $lang->findnotconnectable['error1'];
        }
    }
    
    if (!$PMSEND) {
        $str = '
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" action="' . $_this_script_ . '&amp;do=pm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-envelope me-2 text-primary"></i>' . $lang->findnotconnectable['pm2'] . '
                        </label>
                        <textarea name="msg" class="form-control" rows="12" placeholder="' . htmlspecialchars($lang->findnotconnectable['msg']) . '">' . htmlspecialchars($lang->findnotconnectable['msg']) . '</textarea>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-paper-plane me-2"></i>' . $lang->findnotconnectable['pm'] . '
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo me-2"></i>' . $lang->findnotconnectable['reset'] . '
                        </button>
                    </div>
                </form>
            </div>
        </div>';
    } else {
        $do = '';
    }
}

// Main page with logs
if ($do === '') {
    $logs = '';
    $Query = $db->sql_query('
        SELECT n.id, n.user, n.date, u.username, g.namestyle 
        FROM notconnectablepmlog n 
        LEFT JOIN users u ON (n.user = u.id) 
        LEFT JOIN usergroups g ON (u.usergroup = g.gid) 
        ORDER BY date DESC
    ');
    
    if ($db->num_rows($Query) > 0) {
        while ($Log = mysqli_fetch_assoc($Query)) {
            $logs .= '
                <tr>
                    <td><i class="fas fa-user-circle text-primary me-2"></i><a href="' . ts_seo($Log['user'], $Log['username']) . '">' . get_user_color($Log['username'], $Log['namestyle']) . '</a></td>
                    <td><i class="far fa-calendar-alt me-2"></i>' . my_datee($dateformat, $Log['date']) . ' ' . my_datee($timeformat, $Log['date']) . '</td>
                    <td><a href="' . $_this_script_ . '&amp;do=delete&amp;id=' . $Log['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure?\')"><i class="fas fa-trash-alt me-1"></i>' . $lang->findnotconnectable['delete'] . '</a></td>
                </tr>';
        }
    } else {
        $logs = '
                <tr>
                    <td colspan="3" class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                        ' . $lang->findnotconnectable['nolog'] . '
                    </td>
                </tr>';
    }
    
    $str = '
    <div class="card shadow-sm">
        <div class="card-header bg-gradient-primary text-white">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>' . $lang->findnotconnectable['log'] . '</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fas fa-user me-2"></i>' . $lang->findnotconnectable['sender'] . '</th>
                            <th><i class="far fa-clock me-2"></i>' . $lang->findnotconnectable['date'] . '</th>
                            <th><i class="fas fa-cogs me-2"></i>' . $lang->findnotconnectable['action'] . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $logs . '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';
}

// Navigation buttons
$navButtons = '
<div class="d-flex gap-2 mb-4 flex-wrap">
    ' . ($do !== '' ? '
    <a href="' . $_this_script_ . '" class="btn btn-outline-primary">
        <i class="fas fa-home me-2"></i>' . $lang->findnotconnectable['home'] . '
    </a>' : '') . '
    <a href="' . $_this_script_ . '&amp;do=pm" class="btn btn-outline-success">
        <i class="fas fa-envelope me-2"></i>' . $lang->findnotconnectable['pm'] . '
    </a>
    <a href="' . $_this_script_ . '&amp;do=showlist" class="btn btn-outline-info">
        <i class="fas fa-list me-2"></i>' . $lang->findnotconnectable['showlist'] . '
    </a>
</div>';

// Error display function
function show_fn_errors(): void {
    global $errors, $lang;
    
    if (!empty($errors)) {
        echo '
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>' . $lang->global['error'] . ':</strong><br>
            ' . implode('<br>', array_map('htmlspecialchars', $errors)) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
}

// JavaScript for select all
echo '
<script>
function toggleAll(checkbox) {
    document.querySelectorAll(\'.userCheckbox\').forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
}
</script>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .table-hover tbody tr:hover {
        background-color: rgba(102, 126, 234, 0.05);
        transition: all 0.2s ease;
    }
    .btn-outline-primary:hover, .btn-outline-success:hover, .btn-outline-info:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
    }
    .table th, .table td {
        vertical-align: middle;
    }
    code {
        background: #f8f9fa;
        padding: 2px 6px;
        border-radius: 4px;
    }
    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.85rem;
        }
        .btn {
            font-size: 0.85rem;
        }
    }
</style>';

// Display page
stdhead($lang->findnotconnectable['head']);
show_fn_errors();
echo $navButtons . $str;
stdfoot();
?>