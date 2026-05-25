<?php

declare(strict_types=1);

/**
 * User Search + Latest Users with AJAX Delete/Ban/Unban buttons — Final full version
 */
 


if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}


require_once INC_PATH . '/datahandler.php';


require_once INC_PATH . '/functions_multipage.php';



// ---- avatar upload action (place this BEFORE any output/stdhead) ----
if (isset($_GET['action']) && $_GET['action'] === 'upload_avatar') 
{
    // helpers
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $json = function(array $arr, int $code = 200) use ($is_ajax) {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    };

    // auth
    $user_uid = (int)($CURUSER['id'] ?? 0);
    if ($user_uid <= 0) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Не авторизован'], 401) : exit('Error: вы не авторизованы.');
    }

    // target uid
    $uid = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($uid <= 0) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Не указан uid профиля'], 400) : exit('Error: не указан uid профиля.');
    }

    // permissions: in admin/staff context allow staff; otherwise only owner
    $is_staff_ctx = defined('IN_ADMINCP') || defined('STAFF_PANEL_TSSEv56');
    if (!$is_staff_ctx && $user_uid !== $uid) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Нет прав менять этот аватар'], 403) : exit('Error: нет прав менять этот аватар.');
    }

    // file present
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Файл не загружен'], 400) : exit('Error: file is not uploaded.');
    }

    // size & ext checks
    $max_size    = 22 * 1024 * 1024; // 22 MB
    $allowed_ext = ['jpg','jpeg','png','gif','webp'];

    $file_name = $_FILES['avatar']['name'];
    $file_tmp  = $_FILES['avatar']['tmp_name'];
    $file_size = (int)$_FILES['avatar']['size'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_ext, true)) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Допустимо: JPG/JPEG/PNG/GIF/WebP'], 415) : exit('Error: Allowed JPG/JPEG/PNG/GIF/WebP.');
    }
    if ($file_size <= 0 || $file_size > $max_size) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Файл слишком большой (макс. 22 MB)'], 413) : exit('Error: file is too big (max 22 MB).');
    }

    // MIME + exif type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true)) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Файл не является изображением (MIME)'], 415) : exit('Error: file is not image.');
    }
    $itype = @exif_imagetype($file_tmp);
    if (!in_array($itype, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Файл не является изображением (EXIF)'], 415) : exit('Error: file is not image.');
    }

    // paths
    if (!defined('TSDIR')) {
        define('TSDIR', realpath(__DIR__ . '/..')); // admin/.. -> project root
    }
    $upload_dir     = rtrim(TSDIR, '/\\') . '/uploads/avatars/';
    $public_rel_dir = 'uploads/avatars/';

    if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }

    $new_name  = "avatar_{$uid}." . $file_ext;
    $dest_path = $upload_dir . $new_name;
    $rel_path  = $public_rel_dir . $new_name;

    // cleanup old extensions
    foreach (['jpg','jpeg','png','gif','webp'] as $e) {
        $p = $upload_dir . "avatar_{$uid}." . $e;
        if (is_file($p)) { @unlink($p); }
    }

    // move
    if (!move_uploaded_file($file_tmp, $dest_path)) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Не удалось сохранить файл'], 500) : exit('Ошибка: не удалось сохранить файл.');
    }
    @chmod($dest_path, 0644);

    // get dimensions
    $size = @getimagesize($dest_path);
    if (!$size) {
        @unlink($dest_path);
        $is_ajax ? $json(['ok'=>false,'error'=>'Файл повреждён или не изображение'], 415) : exit('Ошибка: файл повреждён или не изображение.');
    }
    [$width, $height] = $size;
    $avatar_dimensions = $width . '|' . $height;

    // absolute URL using $BASEURL only
    $base    = rtrim($BASEURL, '/');
    $abs_url = $base . '/' . ltrim($rel_path, '/');

    // DB update
    $updated_avatar = [
        "avatar"           => $rel_path,
        "avatardimensions" => $avatar_dimensions,
        "avatartype"       => "upload",
    ];

    if (method_exists($db, 'update_query')) {
        $db->update_query("users", $updated_avatar, "id='{$uid}'");
    } elseif (function_exists('update_query')) {
        $db->update_query("users", $updated_avatar, "id='{$uid}'");
    } else {
        // fallback
        $db->sql_query(
            "UPDATE users SET avatar=?, avatardimensions=?, avatartype='upload' WHERE id=?",
            [$rel_path, $avatar_dimensions, $uid]
        );
    }

    // reply
    if ($is_ajax) {
        $json([
            'ok'      => true,
            'url'     => $rel_path,      // относительный путь
            'href'    => $abs_url,       // абсолютный URL
            'width'   => $width,
            'height'  => $height,
            'message' => 'Аватар обновлён'
        ]);
    }

    header("Location: index.php?act=usersearch");
    exit;
}



























$lang->load('usersearch');









// Bulk actions handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['user_ids'])) {
    header('Content-Type: application/json');

    $action   = $_POST['bulk_action'];
    $user_ids = array_map('intval', $_POST['user_ids']);
    $user_ids = array_filter($user_ids);

    if (empty($user_ids)) {
        echo json_encode(['success' => false, 'error' => 'No users selected']);
        exit;
    }

    $ids_str = implode(',', $user_ids);

    switch ($action) {
       
	   
	   
	case 'ban':
    $ban_reason = $db->escape_string(trim($_POST['ban_reason'] ?? ''));
    $ban_time   = $db->escape_string(trim($_POST['ban_time']   ?? '---'));

    // Вычисляем lifted через ban_date2timestamp
    if ($ban_time === '---' || empty($ban_time)) {
        $lifted  = 0;
        $ban_time = '---';
    } else {
        $lifted = ban_date2timestamp($ban_time, TIMENOW);
    }

    // Находим banned group
    $banned_gid = 2; // дефолт
    $groupscache = $cache->read("usergroups");
    foreach ($groupscache as $group) {
        if (!empty($group['isbannedgroup'])) {
            $banned_gid = (int)$group['gid'];
            break;
        }
    }

    foreach ($user_ids as $uid) {
        $user_row = $db->fetch_array(
            $db->simple_select("users", "username, usergroup, additionalgroups, displaygroup", "id='{$uid}'")
        );
        if (!$user_row) continue;

        $old_group       = (int)$user_row['usergroup'];
        $old_addgroups   = $db->escape_string($user_row['additionalgroups']);
        $old_displaygroup = (int)$user_row['displaygroup'];

        // Пишем в banned
        $db->sql_query("
            INSERT INTO banned 
                (uid, gid, oldgroup, oldadditionalgroups, olddisplaygroup, admin, dateline, bantime, lifted, reason)
            VALUES 
                ('{$uid}', '{$banned_gid}', '{$old_group}', '{$old_addgroups}', '{$old_displaygroup}',
                '".(int)$CURUSER['id']."', '".TIMENOW."', '{$ban_time}', '{$lifted}', '{$ban_reason}')
            ON DUPLICATE KEY UPDATE
                gid              = '{$banned_gid}',
                oldgroup         = '{$old_group}',
                oldadditionalgroups = '{$old_addgroups}',
                olddisplaygroup  = '{$old_displaygroup}',
                admin            = '".(int)$CURUSER['id']."',
                dateline         = '".TIMENOW."',
                bantime          = '{$ban_time}',
                lifted           = '{$lifted}',
                reason           = '{$ban_reason}'
        ");

        // Меняем группу юзера на banned group
        $db->update_query("users", [
            'usergroup' => $banned_gid,
            'enabled'   => 'no'
        ], "id='{$uid}'");
		
		
		 // Логируем
    log_moderator_action(
        ['uid' => $uid, 'username' => $user_row['username']],
        'Banned User (bulk)'
    );
		
		
		
    }

    echo json_encode(['success' => true, 'message' => count($user_ids) . ' users banned']);
    break;
	   
	   
	   
	   
	   
	 case 'unban':
    $unbanned = 0;
    $failed   = 0;

    foreach ($user_ids as $uid) {
        // Берём данные из banned
        $ban_row = $db->fetch_array(
            $db->simple_select("banned", "*", "uid='{$uid}'")
        );

        if (!$ban_row) {
            // Юзер не в бане — просто включаем
            $db->update_query("users", ['enabled' => 'yes'], "id='{$uid}'");
            $unbanned++;
            continue;
        }

        // Восстанавливаем группу как в liftban
        $updated_group = [
            'usergroup'          => (int)$ban_row['oldgroup'],
            'additionalgroups'   => $db->escape_string($ban_row['oldadditionalgroups']),
            'displaygroup'       => (int)$ban_row['olddisplaygroup'],
            'enabled'            => 'yes',
            'notifs'             => ''
        ];

        $db->update_query("users", $updated_group, "id='{$uid}'");
        $db->delete_query("banned", "uid='{$uid}'");

        // Логируем
        $user_row = $db->fetch_array(
            $db->simple_select("users", "username", "id='{$uid}'")
        );
        if ($user_row) {
            log_moderator_action(
                ['uid' => $uid, 'username' => $user_row['username']],
                'Lifted User Ban (bulk)'
            );
        }

        $unbanned++;
    }

    $cache->update_moderators();
    echo json_encode(['success' => true, 'message' => $unbanned . ' users unbanned']);
    break;  
        
			
			
			
        case 'changegroup':
            $gid = (int)($_POST['group_id'] ?? 0);
            if ($gid <= 0) { echo json_encode(['success' => false, 'error' => 'Invalid group']); exit; }
            $db->sql_query("UPDATE users SET usergroup='{$gid}' WHERE id IN ({$ids_str})");
            echo json_encode(['success' => true, 'message' => count($user_ids) . ' users moved to group ' . $gid]);
            break;
        case 'delete':
            $db->sql_query("DELETE FROM users WHERE id IN ({$ids_str})");
            echo json_encode(['success' => true, 'message' => count($user_ids) . ' users deleted']);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit;
}











stdhead('User Search', true, 'collapse');


echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css" type="text/css" media="screen" />';



echo '<link rel="stylesheet" href="'.$BASEURL.'/admin/templates/airbnb.css">';
echo '<script src="'.$BASEURL.'/admin/scripts/flatpickr.js"></script>';
echo '<script src="'.$BASEURL.'/admin/scripts/ru.js"></script>';
echo '<script src="'.$BASEURL.'/scripts/toast.js"></script>';


echo '<link rel="stylesheet" href="'.$BASEURL.'/admin/templates/usersearch.css">';






/* ---------- helpers ---------- */
if (empty($_this_script_)) {
    $_this_script_ = $_SERVER['PHP_SELF'] . (!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');
}

/* ---------- paging ---------- */
$perpage = 20;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$start   = ($page - 1) * $perpage;





/* ---------- build filters ---------- */
$where_clauses = []; // Вместо строк здесь будут части SQL с плейсхолдерами
$params = [];        // Массив значений, которые будут подставлены вместо плейсхолдеров
$param_types = '';   // Строка с типами параметров (опционально, зависит от реализации DB класса)

$to_ts = function (?string $d, bool $end = false): int {
    if (!$d) return 0;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return 0;
    return (int)strtotime($d . ($end ? ' 23:59:59' : ' 00:00:00'));
};

if (!empty($_GET) && !isset($_GET['latest'])) {
    if (!empty($_GET['username'])) {
        $username = trim($_GET['username']);
        if (isset($_GET['exactmatch'])) {
            $where_clauses[] = "username = ?";
            $params[] = $username;
            $param_types .= 's';
        } else {
            $where_clauses[] = "username LIKE ?";
            $params[] = '%' . $username . '%';
            $param_types .= 's';
        }
    }
    if (!empty($_GET['email'])) {
        $where_clauses[] = "email LIKE ?";
        $params[] = '%' . trim($_GET['email']) . '%';
        $param_types .= 's';
    }
    if (!empty($_GET['usergroup']) && $_GET['usergroup'] != '-1') {
        $where_clauses[] = "usergroup = ?";
        $params[] = (int)$_GET['usergroup'];
        $param_types .= 'i';
    }
    if (!empty($_GET['regip'])) {
        $where_clauses[] = "regip = ?";
        $params[] = my_inet_pton(trim($_GET['regip']));
        $param_types .= 's';
    }
    if (!empty($_GET['lastip'])) {
        $where_clauses[] = "lastip = ?";
        $params[] = my_inet_pton(trim($_GET['lastip']));
        $param_types .= 's';
    }

    // Обработка дат
    $added_from = $to_ts($_GET['added'] ?? null, false);
    $added_to = $to_ts($_GET['reg_to'] ?? null, true);
    $act_from = $to_ts($_GET['active_from'] ?? null, false);
    $act_to = $to_ts($_GET['active_to'] ?? null, true);

    if ($added_from) {
        $where_clauses[] = "added >= ?";
        $params[] = $added_from;
        $param_types .= 'i';
    }
    if ($added_to) {
        $where_clauses[] = "added <= ?";
        $params[] = $added_to;
        $param_types .= 'i';
    }
    if ($act_from) {
        $where_clauses[] = "lastactive >= ?";
        $params[] = $act_from;
        $param_types .= 'i';
    }
    if ($act_to) {
        $where_clauses[] = "lastactive <= ?";
        $params[] = $act_to;
        $param_types .= 'i';
    }

    if (isset($_GET['enabled']) && $_GET['enabled'] !== '-1') {
        $where_clauses[] = "enabled = ?";
        $params[] = $_GET['enabled'];
        $param_types .= 's';
    }
    if (!empty($_GET['min_uploaded'])) {
        $where_clauses[] = "uploaded >= ?";
        $params[] = (int)$_GET['min_uploaded'] * 1024 * 1024;
        $param_types .= 'i';
    }
    if (!empty($_GET['max_uploaded'])) {
        $where_clauses[] = "uploaded <= ?";
        $params[] = (int)$_GET['max_uploaded'] * 1024 * 1024;
        $param_types .= 'i';
    }
    if (!empty($_GET['country'])) {
        $where_clauses[] = "country = ?";
        $params[] = trim($_GET['country']);
        $param_types .= 's';
    }

    // Ratio - к сожалению, плейсхолдеры нельзя использовать для выражений, только для значений.
    // Это безопасно, так как мы приводим к float, но это не идеально с точки зрения абстракции.
    
	if (!empty($_GET['min_ratio'] ?? '')) {
    $r = (float)$_GET['min_ratio'];
    $where_clauses[] = "uploaded >= {$r} * GREATEST(downloaded,1)";
}

if (!empty($_GET['max_ratio'] ?? '')) {
    $r = (float)$_GET['max_ratio'];
    $where_clauses[] = "uploaded <= {$r} * GREATEST(downloaded,1)";
}

if (!empty($_GET['warnings'])) {
    $where_clauses[] = "timeswarned = ?";
    $params[] = (int)$_GET['warnings'];
    $param_types .= 'i';
}

	
	
}

// Формируем окончательное условие WHERE
$where = $where_clauses ? implode(' AND ', $where_clauses) : '1=1';




$orderby  = (isset($_GET['orderby1']) && in_array($_GET['orderby1'], ['username','email','id'], true)) ? $_GET['orderby1'] : 'username';
$orderdir = (isset($_GET['orderby2']) && $_GET['orderby2'] === 'DESC') ? 'DESC' : 'ASC';



















echo '<div class="container mt-4">';


echo '
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="m-0">User Search</h1>
    <form method="get" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" class="m-0">
      <input type="hidden" name="act" value="usersearch">
      <input type="hidden" name="latest" value="1">
      <button type="submit" class="btn btn-outline-primary"><i class="bi bi-clock-history me-1"></i> Latest Users</button>
    </form>
  </div>
';







// Статистика юзеров
// FIX: 6 отдельных COUNT запросов → один
$_ts_7d  = TIMENOW - 604800;
$_ts_1d  = TIMENOW - 86400;
$_ts_15m = TIMENOW - 900;
$_stat_q = $db->sql_query("
    SELECT
        COUNT(*)                       AS total,
        SUM(enabled = 'yes')           AS active,
        SUM(enabled = 'no')            AS banned,
        SUM(added >= {$_ts_7d})        AS new7,
        SUM(added >= {$_ts_1d})        AS today,
        SUM(lastactive >= {$_ts_15m})  AS online
    FROM users
");
$_stat      = $db->fetch_array($_stat_q);
$stat_total  = (int)$_stat['total'];
$stat_active = (int)$_stat['active'];
$stat_banned = (int)$_stat['banned'];
$stat_new7   = (int)$_stat['new7'];
$stat_today  = (int)$_stat['today'];
$stat_online = (int)$_stat['online'];

echo '
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-3">
                <div class="fs-2 fw-bold text-primary">'.$stat_total.'</div>
                <div class="small text-muted"><i class="bi bi-people me-1"></i>Total Users</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-3">
                <div class="fs-2 fw-bold text-success">'.$stat_active.'</div>
                <div class="small text-muted"><i class="bi bi-check-circle me-1"></i>Active</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-3">
                <div class="fs-2 fw-bold text-danger">'.$stat_banned.'</div>
                <div class="small text-muted"><i class="bi bi-ban me-1"></i>Banned</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-3">
                <div class="fs-2 fw-bold text-info">'.$stat_new7.'</div>
                <div class="small text-muted"><i class="bi bi-calendar-week me-1"></i>New (7d)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-3">
                <div class="fs-2 fw-bold text-warning">'.$stat_today.'</div>
                <div class="small text-muted"><i class="bi bi-calendar-day me-1"></i>New Today</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-3">
                <div class="fs-2 fw-bold text-success">'.$stat_online.'</div>
                <div class="small text-muted"><i class="bi bi-circle-fill text-success me-1" style="font-size:8px;"></i>Online (15m)</div>
            </div>
        </div>
    </div>
</div>

<!-- Быстрые фильтры -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <span class="text-muted small align-self-center me-1"><i class="bi bi-lightning me-1"></i>Quick filters:</span>
    <a href="?act=usersearch&enabled=no" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-ban me-1"></i>Banned ('.$stat_banned.')
    </a>
    <a href="?act=usersearch&added='.date('Y-m-d', TIMENOW - 86400).'" class="btn btn-sm btn-outline-warning">
        <i class="bi bi-calendar-day me-1"></i>New Today ('.$stat_today.')
    </a>
    <a href="?act=usersearch&added='.date('Y-m-d', TIMENOW - 604800).'" class="btn btn-sm btn-outline-info">
        <i class="bi bi-calendar-week me-1"></i>New 7 Days ('.$stat_new7.')
    </a>
    <a href="?act=usersearch&latest=1" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-clock-history me-1"></i>Latest 10
    </a>
    <a href="?act=usersearch&enabled=yes" class="btn btn-sm btn-outline-success">
        <i class="bi bi-check-circle me-1"></i>Active ('.$stat_active.')
    </a>
</div>
';














/* ---------- SEARCH FORM (ON TOP) ---------- */
echo '
<form method="get" action="' . htmlspecialchars($_this_script_) . '" class="card card-body mb-4">
    <input type="hidden" name="act" value="usersearch">
	
	

    <div class="row g-2">
        <div class="col-md-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" value="'.htmlspecialchars_uni($_GET['username'] ?? '').'" placeholder="Username">
        </div>
        <div class="col-md-3">
            <label class="form-label">Email</label>
            <input type="text" name="email" class="form-control" value="'.htmlspecialchars_uni($_GET['email'] ?? '').'" placeholder="Email">
        </div>
        <div class="col-md-3">
            <label class="form-label">Group</label>
            <select name="usergroup" class="form-select">
                <option value="-1">All groups</option>';
                $q = $db->sql_query("SELECT gid, title FROM usergroups");
                while ($g = $db->fetch_array($q)) 
                {
                    $sel = (isset($_GET['usergroup']) && $_GET['usergroup'] == $g['gid']) ? ' selected' : '';
                    echo '<option value="'.$g['gid'].'"'.$sel.'>'.htmlspecialchars_uni($g['title']).'</option>';
                }
echo '      </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="enabled" class="form-select">
                <option value="-1">All</option>
                <option value="yes"'.(($_GET['enabled'] ?? '') === 'yes' ? ' selected' : '').'>Active</option>
                <option value="no"'.(($_GET['enabled'] ?? '') === 'no' ? ' selected' : '').'>Banned</option>
            </select>
        </div>
    </div>

    <div class="row g-2 mt-3">
        <div class="col-md-3">
            <label class="form-label">Reg IP</label>
            <input type="text" name="regip" class="form-control" value="'.htmlspecialchars_uni($_GET['regip'] ?? '').'" placeholder="Reg IP">
        </div>
        <div class="col-md-3">
            <label class="form-label">Last IP</label>
            <input type="text" name="lastip" class="form-control" value="'.htmlspecialchars_uni($_GET['lastip'] ?? '').'" placeholder="Last IP">
        </div>
        <div class="col-md-3">
            <label class="form-label">Country</label>
            <input type="text" name="country" class="form-control" value="'.htmlspecialchars_uni($_GET['country'] ?? '').'" placeholder="Country">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="exactmatch" value="1" '.(isset($_GET['exactmatch']) ? 'checked' : '').'>
                <label class="form-check-label ms-2">Exact match username</label>
            </div>
        </div>
    </div>

   
   
   
   <div class="row g-2 mt-3">
  <div class="col-md-3">
    <label class="form-label">Reg Date From</label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
      <input type="text" id="added" name="added" class="form-control" placeholder="YYYY-MM-DD">
      <button class="btn btn-outline-secondary" type="button" data-clear="#added" aria-label="Clear"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>

  <div class="col-md-3">
    <label class="form-label">Reg Date To</label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
      <input type="text" id="reg_to" name="reg_to" class="form-control" placeholder="YYYY-MM-DD">
      <button class="btn btn-outline-secondary" type="button" data-clear="#reg_to" aria-label="Clear"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>

  <div class="col-md-3">
    <label class="form-label">Last Active From</label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
      <input type="text" id="active_from" name="active_from" class="form-control" placeholder="YYYY-MM-DD">
      <button class="btn btn-outline-secondary" type="button" data-clear="#active_from" aria-label="Clear"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>

  <div class="col-md-3">
    <label class="form-label">Last Active To</label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
      <input type="text" id="active_to" name="active_to" class="form-control" placeholder="YYYY-MM-DD">
      <button class="btn btn-outline-secondary" type="button" data-clear="#active_to" aria-label="Clear"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>
</div>

   
   
   
   
   

    <div class="row g-2 mt-3">
        <div class="col-md-3">
            <label class="form-label">Min Uploaded (MB)</label>
            <input type="number" name="min_uploaded" class="form-control" value="'.htmlspecialchars_uni($_GET['min_uploaded'] ?? '').'">
        </div>
        <div class="col-md-3">
            <label class="form-label">Max Uploaded (MB)</label>
            <input type="number" name="max_uploaded" class="form-control" value="'.htmlspecialchars_uni($_GET['max_uploaded'] ?? '').'">
        </div>
        <div class="col-md-3">
            <label class="form-label">Min Ratio</label>
            <input type="number" step="0.01" name="min_ratio" class="form-control" value="'.htmlspecialchars_uni($_GET['min_ratio'] ?? '').'">
        </div>
        <div class="col-md-3">
            <label class="form-label">Max Ratio</label>
            <input type="number" step="0.01" name="max_ratio" class="form-control" value="'.htmlspecialchars_uni($_GET['max_ratio'] ?? '').'">
        </div>
    </div>

    <div class="row g-2 mt-3">
        <div class="col-md-3">
            <label class="form-label">Warnings</label>
            <input type="number" name="warnings" class="form-control" value="'.htmlspecialchars_uni($_GET['warnings'] ?? '').'">
        </div>
        <div class="col-md-3">
            <label class="form-label">Order by</label>
            <select name="orderby1" class="form-select">
                <option value="username"'.(($_GET['orderby1'] ?? '') === 'username' ? ' selected' : '').'>Username</option>
                <option value="email"'.(($_GET['orderby1'] ?? '') === 'email' ? ' selected' : '').'>Email</option>
                <option value="id"'.(($_GET['orderby1'] ?? '') === 'id' ? ' selected' : '').'>ID</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Direction</label>
            <select name="orderby2" class="form-select">
                <option value="ASC"'.(($_GET['orderby2'] ?? '') === 'ASC' ? ' selected' : '').'>ASC</option>
                <option value="DESC"'.(($_GET['orderby2'] ?? '') === 'DESC' ? ' selected' : '').'>DESC</option>
            </select>
        </div>
        
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary w-100">Search</button>
            <a href="?act=usersearch" class="btn btn-outline-secondary">Clear</a>
        </div>



    </div>
</form>
';

/* ---------- RESULTS (under the form) ---------- */









if (isset($_GET['latest'])) {
    // Latest Users
    $latest_res = $db->sql_query("SELECT id, username, usergroup, added, avatar, avatardimensions, email FROM users ORDER BY id DESC LIMIT 10");
    echo '<div class="card mb-4"><div class="card-header fw-bold">Latest Users</div>';
    echo '<div class="table-responsive"><table class="table table-striped table-hover align-middle mb-0">';
   echo '<thead><tr>
    <th>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="checkAll">
        </div>
    </th>
    <th>ID</th><th>Avatar</th><th>Username</th><th>Email</th><th>Group</th>
    <th>Reg IP/Last IP</th><th>Upl/Down</th><th>Ratio</th><th>Actions</th>
</tr></thead>';
    while ($user = $db->fetch_array($latest_res)) {
        $profile_url = $BASEURL . '/' . get_profile_link($user['id']);
        $av = format_avatar($user['avatar'], $user['avatardimensions'], '80|80');
        if (!empty($av['is_html'])) 
		{
            $avatar_img = '<span class="avatar-sm me-2 d-inline-flex align-items-center justify-content-center">'.$av['image'].'</span>';
        } 
		
		else 
		{
            $avatar_img = '<img src="'.$av['image'].'" '.($av['width_height'] ?: '').' class="rounded" width="50" alt="avatar">';
        }
        $formattedname = format_name($user['username'], $user['usergroup']);
        $joined = my_datee($dateformat, $user['added']) . ' ' . my_datee($timeformat, $user['added']);
        echo '<tr>';
		
		
		// ДОБАВЬ СРАЗУ ПОСЛЕ:
// СТАЛО:
echo '<td>
    <div class="form-check">
        <input class="form-check-input user-checkbox" type="checkbox" value="'.(int)$user['id'].'">
    </div>
</td>';
		
		
		
		
		
		
        echo '<td>'.(int)$user['id'].'</td>';
        echo '<td>'.$avatar_img.'</td>';
        echo '<td><a href="'.$profile_url.'" class="fw-bold">'.$formattedname.'</a></td>';
        echo '<td>'.htmlspecialchars_uni($user['email']).'</td>';
        echo '<td>'.$joined.'</td>';
        echo '<td><a class="delete_employee" data-emp-id="'.$user['id'].'" href="javascript:void(0)" title="Delete">
                <i class="fa-solid fa-trash-can fa-xl" style="color:#eb0f0f;"></i></a></td>';		
				
				
        echo '</tr>';
    }
    echo '</tbody></table></div></div>';
}


else {
    // Search results
    
    


// 1. Запрос для подсчета общего количества
$sql_count = "SELECT COUNT(*) as total FROM users WHERE $where";

if (!empty($params)) {
    $count_result = $db->sql_query_prepared($sql_count, $params);
} else {
    $count_result = $db->sql_query($sql_count);
}

// Проверка ошибок
if (!$count_result) {
    die('Ошибка запроса подсчета: ' . $db->error());
}

// Теперь передаем объект напрямую в fetch_array!
$count_arr = $db->fetch_array($count_result);
$total = (int)$count_arr['total'];
$total_pages = max(1, (int)ceil($total / $perpage));

// 2. Запрос для получения данных с пагинацией
$sql_data = "SELECT * FROM users WHERE $where ORDER BY $orderby $orderdir LIMIT ?, ?";

// Создаем копию параметров для WHERE условий
$params_for_data = $params;

// Добавляем параметры для LIMIT (start и perpage)
$params_for_data[] = (int)$start;
$params_for_data[] = (int)$perpage;

if (!empty($params_for_data)) {
    $query_result = $db->sql_query_prepared($sql_data, $params_for_data);
} else {
    $query_result = $db->sql_query($sql_data);
}

// Проверка ошибок
if (!$query_result) {
    die('Ошибка запроса данных: ' . $db->error());
}

// Теперь передаем объект напрямую в num_rows!
$num = $db->num_rows($query_result);






    if ($num > 0) 
	{
        
		
		
		
		
		
		
		

		
		
			echo '
<div id="bulkActionBar" class="card card-body mb-3 d-none">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="fw-bold text-primary">
            <i class="bi bi-check2-square me-1"></i>
            Selected: <span id="selectedCount">0</span> users
        </span>
        <div class="vr"></div>
        <button type="button" class="btn btn-sm btn-warning" onclick="bulkAction(\'ban\')">
            <i class="bi bi-ban me-1"></i>Ban
        </button>
        <button type="button" class="btn btn-sm btn-success" onclick="bulkAction(\'unban\')">
            <i class="bi bi-check-circle me-1"></i>Unban
        </button>
        <div class="input-group input-group-sm" style="width:auto;">
            <select class="form-select form-select-sm" id="bulkGroupSelect">
                <option value="">Change group...</option>';
                $q = $db->sql_query("SELECT gid, title FROM usergroups ORDER BY title ASC");
                while ($g = $db->fetch_array($q)) {
                    echo '<option value="'.(int)$g['gid'].'">'.htmlspecialchars_uni($g['title']).'</option>';
                }
echo '      </select>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="bulkAction(\'changegroup\')">
                <i class="bi bi-people me-1"></i>Apply
            </button>
        </div>
        <div class="vr"></div>
        <button type="button" class="btn btn-sm btn-danger" onclick="bulkAction(\'delete\')">
            <i class="bi bi-trash me-1"></i>Delete
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="clearSelection()">
            <i class="bi bi-x me-1"></i>Clear
        </button>
    </div>
</div>
';
	
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		echo '<div class="card mb-4"><div class="card-header fw-bold">Users found: '.$total.'</div>';
        echo '<div class="table-responsive"><table class="table table-hover align-middle mb-0">';
       
	   
	  echo '<thead><tr>
    <th>ID</th>
    <th>Avatar</th>
    <th>Username</th>
    <th>Email</th>
    <th>Group</th>
    <th>Reg IP/Last IP</th>
    <th>Upl/Down</th>

    <th>Info</th>
    <th class="text-center" style="width:80px;">
        Actions
        <div class="mt-1">
            <input class="form-check-input" type="checkbox" id="checkAll" title="Select all">
        </div>
    </th>
</tr></thead><tbody>';
	   

        require_once INC_PATH . '/functions_ratio.php';

        while ($u = $db->fetch_array($query_result))
		{
            
			$profile_url = $BASEURL . '/' . get_profile_link($u['id']);

            $av = format_avatar($u['avatar'], $u['avatardimensions'], '100|100');
            if (!empty($av['is_html'])) 
		    {
                $avatar_img = '<span class="avatar-sm me-2 d-inline-flex align-items-center justify-content-center">'.$av['image'].'</span>';
            } 
		
		    else 
		    {
                $avatar_img = '<img src="'.$av['image'].'" '.($av['width_height'] ?: '').' class="rounded" width="50" alt="avatar">';
            }
		   

            $regip         = my_inet_ntop($u['regip']);
            $lastip        = my_inet_ntop($u['lastip']);
            $formattedname = format_name($u['username'], $u['usergroup']);
            $pic           = get_user_icons($u);

            // display group/title
		   if($u['usergroup'])
		   {  
			   $usergroup = usergroup_permissions($u['usergroup']);
		   }
		   else
		   {
			  $usergroup = usergroup_permissions(1);
		   }

		
		   $displaygroupfields = array("title", "description", "namestyle", "usertitle", "image");

		   if(!$u['displaygroup'])
		   {
			  $u['displaygroup'] = $u['usergroup'];
		   }

		   $display_group = usergroup_displaygroup($u['displaygroup']);
		   if(is_array($display_group))
		   {
			  $usergroup = array_merge($usergroup, $display_group);
		   }

		   // User has group title
           $u['usertitle'] = '';
		   $usertitle = '';
		
		   $usertitle = $usergroup['image'];
		   $usertitle = $usertitle;
		
	
			

            echo '<tr>';
			
			// ДОБАВЬ СРАЗУ ПОСЛЕ:

            echo '<td>'.(int)$u['id'].'</td>';
			
			
			
           
            echo '<td data-avatar-cell="1" data-uid="'.(int)$u['id'].'" title="Click to change avatar">'.$avatar_img.'</td>';
			
			
			
			
            echo '<td><a href="'.$profile_url.'" class="fw-bold">'.$formattedname.'</a></br>'.get_user_ratio($u['uploaded'], $u['downloaded']).''.$pic.'</td>';
			
			
            echo '<td>'.htmlspecialchars_uni($u['email']).'</td>';
            echo '<td>'.$usertitle.'</td>';
            echo '<td><code>'.$regip.'<br>'.$lastip.'</code></td>';
            echo '<td>'.mksize($u['uploaded']).'<br>'.mksize($u['downloaded']).'</td>';
           
			
			
			
			

// ← ДОБАВЬ СЮДА:
echo '<td>
    <div class="d-flex flex-column gap-1">
        <div class="d-flex align-items-center gap-1">
            <span class="font-monospace passkey-text"
                  data-passkey="'.htmlspecialchars_uni($u['passkey']).'"
                  style="filter:blur(4px);user-select:none;transition:filter 0.3s;font-size:0.7rem;">
                '.substr($u['passkey'], 0, 8).'...
            </span>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1"
                    onclick="togglePasskey(this)" title="Show/Hide">
                <i class="bi bi-eye" style="font-size:0.7rem;"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1"
                    onclick="copyPasskey(this)" title="Copy">
                <i class="bi bi-clipboard" style="font-size:0.7rem;"></i>
            </button>
        </div>
        <small class="text-muted">
            <i class="bi bi-chat me-1"></i>'.$u['postnum'].'
            &nbsp;|&nbsp;
            <i class="bi bi-chat-dots me-1"></i>'.$u['comms'].'
            &nbsp;|&nbsp;
            <i class="bi bi-star text-warning me-1"></i>'.number_format((float)$u['seedbonus'], 1).'
        </small>
        '.($u['warned'] === 'yes' ? '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Warned x'.$u['timeswarned'].'</span>' : '').'
        '.($u['donor'] === 'yes'  ? '<span class="badge bg-success ms-1"><i class="bi bi-heart me-1"></i>Donor</span>' : '').'
    </div>
</td>';


        
			// СТАЛО:
echo '<td class="text-center align-middle">
    <input class="form-check-input user-checkbox" type="checkbox" value="'.(int)$u['id'].'">
</td>';	
		
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';

        
		
		
// Собираем search_url из текущих GET параметров без page
$search_params = $_GET;
unset($search_params['page']);
unset($search_params['act']); // ← добавь это
$search_params = array_filter($search_params, fn($v) => $v !== '' && $v !== null);
$search_url = !empty($search_params) ? '&' . http_build_query($search_params) : '';
$page_url   = $_SERVER['PHP_SELF'] . '?act=usersearch' . $search_url;
$multipage  = multipage($total, $perpage, $page, $page_url);
echo $multipage;
		
		
		

    } elseif (!empty($_GET)) {
        echo '<div class="alert alert-danger">No users found.</div>';
    }
}

echo '</div>';


/* ---------- Scripts ---------- */

// FIX: togglePasskey/copyPasskey вынесены в usersearch.js


/* единый скрытый инпут для всей страницы (ставим ДО скрипта) */
echo '<input type="file" id="avatarUploadInput" class="d-none" accept="image/*">';
?>

<script src="<?= $BASEURL ?>/admin/scripts/usersearch.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/usersearch_bulkActions.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/avatarUpload.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/datepicker.js"></script>



<?php



stdfoot();
exit;