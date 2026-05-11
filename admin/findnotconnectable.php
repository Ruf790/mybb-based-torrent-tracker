<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Direct initialization not allowed.</div>');
}

define('FNC_VERSION', '1.0');

require_once INC_PATH . '/functions_multipage.php';

require_once INC_PATH.'/datahandler.php';

$lang->load('findnotconnectable');

$do     = (string)($_GET['do'] ?? $_POST['do'] ?? '');
$errors = [];
$str    = '';
$PMSEND = false;

// ── Удаление записи лога ──────────────────────────────────────────────────────
// FIX: было без CSRF — любой мог удалить лог через GET-ссылку
if ($do === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post_check($_POST['my_post_key'] ?? '');
    $DelID = (int)($_POST['id'] ?? 0);
    if ($DelID > 0) {
        $db->sql_query('DELETE FROM notconnectablepmlog WHERE id = ' . $DelID);
    }
    $do = '';
}

// ── PM выбранным пользователям ────────────────────────────────────────────────
if ($do === 'pm2') {
    if (!is_array($_POST['userids'] ?? null)) {
        $errors[] = $lang->findnotconnectable['error3'] ?? 'No users selected.';
        $do = 'showlist';
    } else {
        $msg = trim($lang->findnotconnectable['msg'] ?? '');
        if ($msg === '') {
            $errors[] = $lang->findnotconnectable['error1'] ?? 'Message is empty.';
            $do = 'showlist';
        } else {
            require_once INC_PATH . '/functions_pm.php';
            foreach ($_POST['userids'] as $userid) {
                $uid = (int)$userid;
                if ($uid > 0) {
                    send_pm(['touid' => $uid, 'message' => $msg, 'subject' => $lang->findnotconnectable['subject'] ?? ''], 0, true);
                }
            }
            $db->sql_query(
                'INSERT INTO notconnectablepmlog (user, date) VALUES (' . (int)$CURUSER['id'] . ', NOW())'
            );
            $PMSEND = true;
            $do = '';
        }
    }
}

// ── Список не подключаемых пиров ──────────────────────────────────────────────
if ($do === 'showlist') {
    // FIX: два запроса — сначала COUNT, потом с LIMIT
    $countQ = $db->sql_query('SELECT COUNT(DISTINCT userid) AS cnt FROM peers WHERE connectable = "no"');
    $count  = (int)($db->fetch_array($countQ)['cnt'] ?? 0);

    $perpage  = 25;
    $page     = max(1, (int)($mybb->input['page'] ?? 1));
    $start    = ($page - 1) * $perpage;
    $multipage = multipage($count, $perpage, $page, $_this_script_ . '&do=showlist&');

    $query = $db->sql_query('
        SELECT DISTINCT p.torrent, p.userid, p.ip, p.port, p.seeder, p.agent,
               t.name, u.username, g.namestyle
        FROM peers p
        LEFT JOIN torrents t     ON (p.torrent   = t.id)
        LEFT JOIN users u        ON (p.userid    = u.id)
        LEFT JOIN usergroups g   ON (u.usergroup = g.gid)
        WHERE p.connectable = "no"
        ORDER BY u.username
        LIMIT ' . $start . ', ' . $perpage
    );

    if ($db->num_rows($query) > 0) {
        $rows = '';
        // FIX: было mysqli_fetch_assoc() напрямую → $db->fetch_array()
        while ($row = $db->fetch_array($query)) {
            $isSeeder = $row['seeder'] === 'yes';
            $rows .= '
            <tr>
                <td><a href="' . ts_seo($row['userid'], $row['username']) . '">'
                    . get_user_color($row['username'], $row['namestyle']) . '</a></td>
                <td><a href="' . $BASEURL . '/details.php?id=' . (int)$row['torrent'] . '">'
                    . cutename($row['name'], 60) . '</a></td>
                <td><code>' . htmlspecialchars($row['ip'], ENT_QUOTES, 'UTF-8') . ':'
                    . (int)$row['port'] . '</code></td>
                <td>' . htmlspecialchars($row['agent'], ENT_QUOTES, 'UTF-8') . '</td>
                <td class="text-center">'
                    . ($isSeeder
                        ? '<span class="badge bg-success">Yes</span>'
                        : '<span class="badge bg-secondary">No</span>') . '
                </td>
                <td class="text-center">
                    <input type="checkbox" name="userids[]" value="' . (int)$row['userid'] . '"
                           class="userCheckbox form-check-input">
                </td>
            </tr>';
        }

        $str = ($multipage ? '<div class="mb-3">' . $multipage . '</div>' : '') . '
        <div class="table-responsive">
            <form method="post" action="' . $_this_script_ . '&amp;do=pm2" id="userlistForm">
                <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8') . '">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>' . ($lang->findnotconnectable['username'] ?? 'User') . '</th>
                            <th>' . ($lang->findnotconnectable['torrent']  ?? 'Torrent') . '</th>
                            <th>' . ($lang->findnotconnectable['ip']       ?? 'IP') . '</th>
                            <th>' . ($lang->findnotconnectable['client']   ?? 'Client') . '</th>
                            <th class="text-center">' . ($lang->findnotconnectable['seeder'] ?? 'Seeder') . '</th>
                            <th class="text-center">
                                <input type="checkbox" id="selectAll" class="form-check-input"
                                       onchange="document.querySelectorAll(\'.userCheckbox\').forEach(cb => cb.checked = this.checked)">
                            </th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>'
                                    . ($lang->findnotconnectable['pm3'] ?? 'Send PM') . '
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </form>
        </div>' . ($multipage ? '<div class="mt-3">' . $multipage . '</div>' : '');
    } else {
        $errors[] = $lang->findnotconnectable['error2'] ?? 'No unconnectable peers found.';
        $do = '';
    }
}

// ── PM всем не подключаемым ───────────────────────────────────────────────────
if ($do === 'pm') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_post_check($_POST['my_post_key'] ?? '');
        $msg = trim($_POST['msg'] ?? '');

        if ($msg === '') {
            $errors[] = $lang->findnotconnectable['error1'] ?? 'Message is empty.';
        } else {
            $query = $db->sql_query('SELECT DISTINCT userid FROM peers WHERE connectable = "no"');
            if ($db->num_rows($query) > 0) {
                require_once INC_PATH . '/functions_pm.php';
                // FIX: было mysqli_fetch_assoc() → $db->fetch_array()
                while ($row = $db->fetch_array($query)) {
                    send_pm(['touid' => (int)$row['userid'], 'message' => $msg, 'subject' => $lang->findnotconnectable['subject'] ?? ''], 0, true);
                }
                $db->sql_query(
                    'INSERT INTO notconnectablepmlog (user, date) VALUES (' . (int)$CURUSER['id'] . ', NOW())'
                );
                $PMSEND = true;
            } else {
                $errors[] = $lang->findnotconnectable['error2'] ?? 'No unconnectable peers.';
            }
        }
    }

    if (!$PMSEND) {
        $postCode = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');
        $msgText  = htmlspecialchars($lang->findnotconnectable['msg'] ?? '', ENT_QUOTES, 'UTF-8');
        $str = '
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" action="' . $_this_script_ . '&amp;do=pm">
                    <input type="hidden" name="my_post_key" value="' . $postCode . '">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-envelope me-2 text-primary"></i>'
                            . ($lang->findnotconnectable['pm2'] ?? 'Message') . '
                        </label>
                        <textarea name="msg" class="form-control" rows="10"
                                  placeholder="' . $msgText . '">' . $msgText . '</textarea>
                    </div>
                    <div class="text-end d-flex gap-2 justify-content-end">
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i>'
                            . ($lang->findnotconnectable['reset'] ?? 'Reset') . '
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>'
                            . ($lang->findnotconnectable['pm'] ?? 'Send') . '
                        </button>
                    </div>
                </form>
            </div>
        </div>';
    } else {
        $do = '';
    }
}

// ── Главная страница — лог ────────────────────────────────────────────────────
if ($do === '') {
    $postCode = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');
    $rows     = '';

    $Query = $db->sql_query('
        SELECT n.id, n.user, n.date, u.username, g.namestyle
        FROM notconnectablepmlog n
        LEFT JOIN users u      ON (n.user      = u.id)
        LEFT JOIN usergroups g ON (u.usergroup = g.gid)
        ORDER BY n.date DESC
    ');

    if ($db->num_rows($Query) > 0) {
        // FIX: было mysqli_fetch_assoc() → $db->fetch_array()
        while ($log = $db->fetch_array($Query)) {
            $logDate = my_datee($dateformat ?? '', $log['date'])
                     . ' ' . my_datee($timeformat ?? '', $log['date']);
            // FIX: удаление через POST-форму вместо GET-ссылки
            $rows .= '
            <tr>
                <td><a href="' . ts_seo($log['user'], $log['username']) . '">'
                    . get_user_color($log['username'], $log['namestyle']) . '</a></td>
                <td>' . $logDate . '</td>
                <td>
                    <form method="post" action="' . $_this_script_ . '&amp;do=delete"
                          onsubmit="return confirm(\'Delete this entry?\')">
                        <input type="hidden" name="my_post_key" value="' . $postCode . '">
                        <input type="hidden" name="id" value="' . (int)$log['id'] . '">
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash-alt me-1"></i>'
                            . ($lang->findnotconnectable['delete'] ?? 'Delete') . '
                        </button>
                    </form>
                </td>
            </tr>';
        }
    } else {
        $rows = '
        <tr>
            <td colspan="3" class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>'
                . ($lang->findnotconnectable['nolog'] ?? 'No log entries.') . '
            </td>
        </tr>';
    }

    $str = '
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            <i class="fas fa-history me-2"></i>'
            . ($lang->findnotconnectable['log'] ?? 'PM Log') . '
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>' . ($lang->findnotconnectable['sender'] ?? 'Sent by') . '</th>
                            <th>' . ($lang->findnotconnectable['date']   ?? 'Date') . '</th>
                            <th>' . ($lang->findnotconnectable['action'] ?? 'Action') . '</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </div>
    </div>';
}

// ── Навигация ─────────────────────────────────────────────────────────────────
$navButtons = '
<div class="d-flex gap-2 mb-4 flex-wrap">
    ' . ($do !== '' ? '
    <a href="' . $_this_script_ . '" class="btn btn-outline-secondary">
        <i class="fas fa-home me-1"></i>' . ($lang->findnotconnectable['home'] ?? 'Home') . '
    </a>' : '') . '
    <a href="' . $_this_script_ . '&amp;do=pm" class="btn btn-outline-success">
        <i class="fas fa-envelope me-1"></i>' . ($lang->findnotconnectable['pm'] ?? 'Send PM') . '
    </a>
    <a href="' . $_this_script_ . '&amp;do=showlist" class="btn btn-outline-info">
        <i class="fas fa-list me-1"></i>' . ($lang->findnotconnectable['showlist'] ?? 'Show List') . '
    </a>
</div>';

// ── Вывод ─────────────────────────────────────────────────────────────────────
// FIX: stdhead() вызывался после echo — теперь первым
stdhead($lang->findnotconnectable['head'] ?? 'Not Connectable');


// Errors
if (!empty($errors)) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>'
        . implode('<br>', array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors))
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

// Success
if ($PMSEND) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>'
        . htmlspecialchars($lang->findnotconnectable['done'] ?? 'PM sent successfully.', ENT_QUOTES, 'UTF-8')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

echo $navButtons . $str;

stdfoot();