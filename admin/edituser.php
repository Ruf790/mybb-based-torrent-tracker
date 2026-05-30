<?php

declare(strict_types=1);


// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

flash_message();






function fetch_ban_times()
{
	global $plugins, $lang;
	// Days-Months-Years
	$ban_times = array(
		"1-0-0" => "1 Day",
		"2-0-0" => "2 Days",
		"3-0-0" => "3 Days",
		"4-0-0" => "4 Days",
		"5-0-0" => "5 Days",
		"6-0-0" => "6 Days",
		"7-0-0" => "1 Week",
		"14-0-0" => "2 Weeks",
		"21-0-0" => "3 Weeks",
		"0-1-0" => "1 Month",
		"0-2-0" => "2 Months",
		"0-3-0" => "3 Months",
		"0-4-0" => "4 Months",
		"0-5-0" => "5 Months",
		"0-6-0" => "6 Months",
		"0-0-1" => "1 Year",
		"0-0-2" => "2 Years"
	);
	$ban_times = $plugins->run_hooks("functions_fetch_ban_times", $ban_times);
	$ban_times['---'] = 'Permanent';
	return $ban_times;
}
  
  
function ban_date2timestamp($date, $stamp = 0)
{
    if ($stamp == 0) {
        $stamp = TIMENOW;
    }
    
    $d = explode('-', $date);
    $nowdate = date("H-j-n-Y", $stamp);
    $n = explode('-', $nowdate);
    
    // Явное преобразование в int
    $n[1] = (int)$n[1] + (int)$d[0];  // дни
    $n[2] = (int)$n[2] + (int)$d[1];  // месяцы
    $n[3] = (int)$n[3] + (int)$d[2];  // годы
    
    return mktime(
        (int)date("G", $stamp),  // час
        (int)date("i", $stamp),  // минуты
        0,                        // секунды
        (int)$n[2],              // месяц
        (int)$n[1],              // день
        (int)$n[3]               // год
    );
}




/**
 * Calculate user ratio
 */
function get_ratio(array $userdata): string
{
    $uploaded = (float)$userdata['uploaded'];
    $downloaded = (float)$userdata['downloaded'];
    
    if ($downloaded == 0) {
        return $uploaded > 0 ? '∞' : '---';
    }
    
    $ratio = $uploaded / $downloaded;
    
    return ts_nf($ratio, 2);
}

  function get_user_class_name ($class = '')
  {
    //global $cache;
    if ($class == 'all')
    {
      return 'ALL Usergroups';
    }

    require TSDIR . '/cache/usergroups.php';
    foreach ($usergroups as $arr)
    {
      if ($arr['gid'] == $class)
      {
        return $arr['title'];
      }
    }

    return 'ALL Usergroups';
  }
  
  
  





function modcomment(string $what = 'Unknown Action taken'): string
{
    global $modcomment, $CURUSER;

    $action     = str_replace("'", '`', $what);
    $username   = $CURUSER['username'] ?? 'System';
    $modcomment = gmdate('Y-m-d H:i') . " - {$action} by {$username}\n" . ($modcomment ?? '');
    return $modcomment;
}
  
  

/**
 * Enhanced flash message system with animations
 */
function flash_message(?string $message = null, string $type = 'info'): void
{
    // Добавляем новое сообщение в сессию
    if ($message !== null) {
        $_SESSION['flash'][] = [
            'message' => $message,
            'type'    => $type
        ];
        return;
    }

    // Если есть сообщения — выводим
    if (!empty($_SESSION['flash'])) {
        echo '
        <div aria-live="polite" aria-atomic="true" class="position-relative">
            <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">';

        foreach ($_SESSION['flash'] as $flash) {
            $typeClass = match ($flash['type']) {
                'success' => 'bg-success text-white',
                'error', 'danger' => 'bg-danger text-white',
                'warning' => 'bg-warning text-dark',
                default => 'bg-info text-white',
            };

            $msg = htmlspecialchars($flash['message']);
            echo "
            <div class='toast border-0 mb-2' role='alert' aria-live='assertive' aria-atomic='true'>
                <div class='toast-header {$typeClass}'>
                    <strong class='me-auto'>Message</strong>
                    <small>Now</small>
                    <button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='Close'></button>
                </div>
                <div class='toast-body'>
                    {$msg}
                </div>
            </div>";
        }

        echo '
          </div>
            </div> 
       <script>
          document.addEventListener("DOMContentLoaded", function() {
          document.querySelectorAll(".toast").forEach(function(toastEl) {
          let toast = new bootstrap.Toast(toastEl, { delay: 5000 });
          toast.show();
            });
        });
        </script>';

        unset($_SESSION['flash']); // очищаем после показа
    }
}







/**
 * Enhanced admin redirect with smooth transitions
 */
function admin_redirect(string $url, ?string $message = null): void
{
    if ($message) {
        flash_message($message, 'success');
    }
    
    if (!headers_sent()) {
        $url = str_replace("&amp;", "&", $url);
        header("Location: {$url}");
    } else {
        echo "
        <div class='redirect-overlay'>
            <div class='redirect-spinner'></div>
            <p>Redirecting...</p>
        </div>
        <script>
        setTimeout(() => {
            window.location.href = '{$url}';
        }, 500);
        </script>";
    }
    exit;
}

/**
 * Generate enhanced months dropdown with icons
 */
function months(): string
{
    $months = [
        '0' => ['text' => '--- Select Duration ---', 'icon' => 'fa-clock'],
        '1' => ['text' => '1 Week', 'icon' => 'fa-calendar-week'],
        '2' => ['text' => '2 Weeks', 'icon' => 'fa-calendar-days'], 
        '3' => ['text' => '3 Weeks', 'icon' => 'fa-calendar-plus'],
        '4' => ['text' => '1 Month', 'icon' => 'fa-calendar'],
        '5' => ['text' => '5 Weeks', 'icon' => 'fa-calendar-check'],
        '6' => ['text' => '6 Weeks', 'icon' => 'fa-calendar-day'],
        '7' => ['text' => '7 Weeks', 'icon' => 'fa-calendar-alt'], 
        '8' => ['text' => '2 Months', 'icon' => 'fa-calendar-minus'],
        '12' => ['text' => '3 Months', 'icon' => 'fa-calendar-xmark'],
        '16' => ['text' => '4 Months', 'icon' => 'fa-calendar'],
        '20' => ['text' => '5 Months', 'icon' => 'fa-calendar'],
        '24' => ['text' => '6 Months', 'icon' => 'fa-calendar'],
        '28' => ['text' => '7 Months', 'icon' => 'fa-calendar'],
        '32' => ['text' => '8 Months', 'icon' => 'fa-calendar'], 
        '36' => ['text' => '9 Months', 'icon' => 'fa-calendar'],
        '40' => ['text' => '10 Months', 'icon' => 'fa-calendar'],
        '44' => ['text' => '11 Months', 'icon' => 'fa-calendar'],
        '48' => ['text' => '12 Months', 'icon' => 'fa-calendar-star'],
        '255' => ['text' => 'Unlimited', 'icon' => 'fa-infinity']
    ];
    
    return implode('', array_map(
        fn($v, $data) => "<option value='{$v}' data-icon='{$data['icon']}'>{$data['text']}</option>",
        array_keys($months),
        $months
    ));
}

/**
 * Generate enhanced weeks dropdown with icons
 */
function weeks(): string
{
    $weeks = [
        '0' => ['text' => '--- Select Duration ---', 'icon' => 'fa-clock'],
        '1' => ['text' => '1 Week', 'icon' => 'fa-calendar-week'],
        '2' => ['text' => '2 Weeks', 'icon' => 'fa-calendar-days'],
        '3' => ['text' => '3 Weeks', 'icon' => 'fa-calendar-plus'],
        '4' => ['text' => '4 Weeks', 'icon' => 'fa-calendar'],
        '5' => ['text' => '5 Weeks', 'icon' => 'fa-calendar-check'],
        '6' => ['text' => '6 Weeks', 'icon' => 'fa-calendar-day'],
        '7' => ['text' => '7 Weeks', 'icon' => 'fa-calendar-alt'],
        '8' => ['text' => '8 Weeks', 'icon' => 'fa-calendar-minus'],
        '9' => ['text' => '9 Weeks', 'icon' => 'fa-calendar-week'],
        '10' => ['text' => '10 Weeks', 'icon' => 'fa-calendar-days'],
        '11' => ['text' => '11 Weeks', 'icon' => 'fa-calendar-plus'],
        '12' => ['text' => '12 Weeks', 'icon' => 'fa-calendar-star'],
        '255' => ['text' => 'Unlimited', 'icon' => 'fa-infinity']
    ];
    
    return implode('', array_map(
        fn($v, $data) => "<option value='{$v}' data-icon='{$data['icon']}'>{$data['text']}</option>", 
        array_keys($weeks),
        $weeks
    ));
}

/**
 * Enhanced permission check with visual feedback
 */
function permission_check(): void
{
    global $userdata, $usergroups, $CURUSER;
    
    if 
	(
        (($userdata['cansettingspanel'] == '1' && $usergroups['cansettingspanel'] != '1') ||
        ($userdata['issupermod'] == '1' && $usergroups['issupermod'] != '1') ||
        ($userdata['canstaffpanel'] == '1' && $usergroups['canstaffpanel'] != '1')) ||
        $CURUSER['id'] == $userdata['id']
    ) 
	{
        print_no_permission (false, true, 'Permission Denied: Protected usergroup!');
    }
}








function inputbox(string $title, string $name, string $value = '', string $class = 'form-control', string $size = '35', string $extra = '', string $maxlength = '', bool $autocomplete = true, string $extra2 = '', bool $required = false): string
{
   
    $value = htmlspecialchars_uni($value);
    $autocompleteAttr = $autocomplete ? '' : 'autocomplete="off"';
    $maxlengthAttr = $maxlength ? "maxlength='{$maxlength}'" : '';
    $sizeAttr = $size ? "size='{$size}'" : '';
    $inputId = uniqid('input_');
    
    $icon = match($name) {
        'username' => 'fa-user',
        'password' => 'fa-lock',
        'email' => 'fa-envelope',
        'usertitle' => 'fa-award',
        'avatar' => 'fa-image',
        'seedbonus' => 'fa-star',
        'invites' => 'fa-envelope-open-text',
        'uploaded' => 'fa-upload',
        'downloaded' => 'fa-download',
        default => 'fa-pen'
    };
   
    $requiredAttr = $required ? 'required' : '';
    $requiredStar = $required ? ' <span class="text-danger">*</span>' : '';
    
    return "
    <div class='form-floating mb-4'>
        <input type='text' class='{$class}' id='{$inputId}' name='{$name}' 
               {$sizeAttr} {$maxlengthAttr} {$autocompleteAttr} value='{$value}' 
               {$requiredAttr} {$extra2}
               placeholder='{$title}'>
        <label for='{$inputId}' class='form-label'>
            <i class='fas {$icon} me-2 text-primary'></i>{$title}{$requiredStar}
        </label>
        {$extra}
    </div>";
}












/**
 * Enhanced select box that returns HTML string
 */
function selectbox(string $title, string $name, string $type, string $class = 'form-select'): string
{
    global $userdata, $usergroups, $db;
    
    $selectId = uniqid('select_');
    
    $options = "<option value=''>Select usergroup...</option>";
    
    if ($type === 'trackergroups') {
        $query = $db->sql_query('SELECT gid, title, cansettingspanel, issupermod, canstaffpanel FROM usergroups ORDER BY title');
        while ($tclass = $db->fetch_array($query)) {
            if (
                (($tclass['cansettingspanel'] == '1' && $usergroups['cansettingspanel'] != '1') ||
                ($tclass['issupermod'] == '1' && $usergroups['issupermod'] != '1') ||
                ($tclass['canstaffpanel'] == '1' && $usergroups['canstaffpanel'] != '1') ||
                (($tclass['cansettingspanel'] == '1' || $tclass['issupermod'] == '1') && $usergroups['cansettingspanel'] != '1') ||
                (($tclass['gid'] == UC_ADMINISTRATOR || $tclass['gid'] == UC_SYSOP) && $usergroups['cansettingspanel'] != '1'))
            ) {
                continue;
            }
            
            $selected = $userdata['usergroup'] == $tclass['gid'] ? 'selected' : '';
            $icon = $tclass['gid'] == UC_ADMINISTRATOR ? 'fa-crown text-warning' : 
                   ($tclass['gid'] == UC_SYSOP ? 'fa-shield-alt text-primary' : 'fa-user text-secondary');
            
            $options .= "<option value='{$tclass['gid']}' {$selected} data-icon='{$icon}'>{$tclass['title']}</option>";
        }
    }
    
    return "
    <div class='mb-4'>
        <label for='{$selectId}' class='form-label fw-semibold mb-3'>
            <i class='fas fa-users me-2 text-primary'></i>{$title}
        </label>
        <select name='{$name}' id='{$selectId}' class='{$class} select2-enable' data-search='true'>
            {$options}
        </select>
    </div>";
}



/**
 * Enhanced yes/no radio buttons
 */


function yesno(string $title, string $name, string $value = '1'): string
{
    // Определяем какие поля используют числовые значения
    $numericFields = [
        'moderateposts', 'allownotices', 'hideemail', 'receivepms', 'receivefrombuddy',
        'pmnotice', 'pmnotify', 'buddyrequestspm', 'buddyrequestsauto', 'invisible',
        'showavatars', 'showsigs', 'commentpm', 'showredirect', 'dst', 'dstcorrection'
    ];
    
    if (in_array($name, $numericFields)) {
        // Для числовых полей
        $yesChecked = ($value === '1' || $value === 1) ? 'checked' : '';
        $noChecked = ($value === '0' || $value === 0) ? 'checked' : '';
        $yesValue = '1';
        $noValue = '0';
    } else {
        // Для старых текстовых полей (donor, warned, enabled и т.д.)
        $yesChecked = $value === 'yes' ? 'checked' : '';
        $noChecked = $value === 'no' ? 'checked' : '';
        $yesValue = 'yes';
        $noValue = 'no';
    }
    
    $fieldId = uniqid('field_');
    
    $icon = match($name) {
        'donor' => 'fa-heart',
        'warned' => 'fa-exclamation-triangle',
        'enabled' => 'fa-user-check',
        'cancomment' => 'fa-comment',
        'canupload' => 'fa-upload',
        'candownload' => 'fa-download',
        'moderateposts' => 'fa-user-cog',
        'allownotices' => 'fa-envelope',
        'hideemail' => 'fa-eye-slash',
        'receivepms' => 'fa-comments',
        'receivefrombuddy' => 'fa-user-friends',
        'pmnotice' => 'fa-bell',
        'pmnotify' => 'fa-envelope',
        'buddyrequestspm' => 'fa-user-plus',
        'buddyrequestsauto' => 'fa-check-circle',
        'invisible' => 'fa-eye-slash',
        'showavatars' => 'fa-image',
        'showsigs' => 'fa-signature',
        'commentpm' => 'fa-comment-medical',
        'showredirect' => 'fa-external-link-alt',
        'dst' => 'fa-clock',
        default => 'fa-toggle-on'
    };
    
    return "
    <div class='mb-4'>
        <label class='form-label fw-semibold mb-3'>
            <i class='fas {$icon} me-2 text-primary'></i>{$title}
        </label>
        <div class='btn-group w-100' role='group'>
            <input type='radio' class='btn-check' name='{$name}' id='{$fieldId}_yes' value='{$yesValue}' {$yesChecked}>
            <label class='btn btn-outline-success' for='{$fieldId}_yes'>
                <i class='fas fa-check me-2'></i>Yes
            </label>
            
            <input type='radio' class='btn-check' name='{$name}' id='{$fieldId}_no' value='{$noValue}' {$noChecked}>
            <label class='btn btn-outline-danger' for='{$fieldId}_no'>
                <i class='fas fa-times me-2'></i>No
            </label>
        </div>
    </div>";
}









/**
 * Enhanced user data fetching with caching
 */
function get_user_data(): void
{
    global $userid, $db;

    // ► Кэш: если данные уже загружены для того же userid — ничего не делаем
    if (!empty($GLOBALS['userdata']['id']) && (int)$GLOBALS['userdata']['id'] === (int)$userid) {
        return;
    }

    // LEFT JOIN с агрегатами вместо двух коррелированных subquery
    $res = $db->sql_query('
    SELECT u.*,
           g.cansettingspanel,
           g.canstaffpanel,
           g.issupermod,
           COALESCE(pc.cnt, 0)  AS post_count,
           COALESCE(tc.cnt, 0)  AS thread_count,
           COALESCE(sn.total, 0)      AS snatch_total,
           COALESCE(sn.done, 0)       AS snatch_done,
           COALESCE(sn.seedtime, 0)   AS snatch_seedtime,
           COALESCE(sn.leechtime, 0)  AS snatch_leechtime,
           COALESCE(pr.peers_total, 0)   AS peers_total,
           COALESCE(pr.peers_seeding, 0) AS peers_seeding,
		   COALESCE(rc.cnt, 0)           AS report_count
    FROM users u
    LEFT JOIN usergroups g  ON g.gid = u.usergroup
    LEFT JOIN (SELECT uid, COUNT(*) AS cnt FROM posts   GROUP BY uid) pc ON pc.uid = u.id
    LEFT JOIN (SELECT uid, COUNT(*) AS cnt FROM threads GROUP BY uid) tc ON tc.uid = u.id
    
	LEFT JOIN (
    SELECT addedby, COUNT(*) AS cnt
    FROM reports GROUP BY addedby
    ) rc ON rc.addedby = u.id
	
	LEFT JOIN (
        SELECT userid,
               COUNT(*)             AS total,
               SUM(finished="yes")  AS done,
               SUM(seedtime)        AS seedtime,
               SUM(leechtime)       AS leechtime
        FROM snatched GROUP BY userid
    ) sn ON sn.userid = u.id
    LEFT JOIN (
        SELECT userid,
               COUNT(*)              AS peers_total,
               SUM(seeder = "yes")   AS peers_seeding
        FROM peers GROUP BY userid
    ) pr ON pr.userid = u.id
    WHERE u.id = ' . $db->sqlesc($userid)
    );

    $arr = $db->fetch_array($res);
    if (!$arr) {
        echo '
        <div class="user-not-found">
            <div class="error-container text-center py-5">
                <i class="fas fa-user-slash fa-5x text-muted mb-4"></i>
                <h3 class="text-danger mb-3">User Not Found</h3>
                <p class="text-muted mb-4">No user exists with the specified ID.</p>
                <a href="javascript:history.back()" class="btn btn-primary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Go Back
                </a>
            </div>
        </div>';
        exit;
    }

    $GLOBALS['userdata'] = $arr;
}

/**
 * Check if username exists
 */
function username_exists222(string $username): bool
{
    global $db;
    
    $tracker_query = $db->sql_query('SELECT username FROM users WHERE username = ' . $db->sqlesc($username) . ' LIMIT 1');
    return $db->num_rows($tracker_query) <= 0;
}

/**
 * Validate username format
 */
function validusername(string $username): bool
{
    return !preg_match('/[^a-zA-Z0-9]/', $username);
}

/**
 * Check if email exists
 */
function email_exists(string $email): bool
{
    global $db;

    $q = $db->sql_query(
        'SELECT 1 FROM users WHERE email = ' . $db->sqlesc($email) . ' LIMIT 1'
    );

    return $db->num_rows($q) > 0;
}

/**
 * Add moderation comment
 */


/**
 * Send private message to user
 */
function insert_message(int $userid, string $message, string $subject): void
{
    require_once INC_PATH . '/functions_pm.php';
    
    $pm = [
        'subject' => $subject,
        'message' => $message,
        'touid' => $userid
    ];
                    
    $pm['sender']['uid'] = -1;
    send_pm($pm, -1, true);
}

// Initialize application
$rootpath = './../';
include $rootpath . '/global.php';

define("IN_MYBB", 1);
define("IN_ADMINCP", 1);
define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);

require_once INC_PATH . '/tsf_functions.php';



// Include required files
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_upload.php';



gzip();
maxsysop();






// ---- avatar upload action ----
if (isset($_GET['action']) && $_GET['action'] === 'upload_avatar') 
{
    global $CURUSER, $userdata, $db, $mybb, $rootpath;

    // JSON helper
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $json = function($arr, $code = 200){
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    };

    // Авторизация
    $user_uid = (int)($CURUSER['id'] ?? 0);
    if ($user_uid <= 0) {
        $is_ajax ? $json(['success'=>false,'error'=>'Not authorized'], 401) : exit('Error: Not authorized.');
    }

    // Целевой профиль
    $uid = (int)($_POST['id'] ?? $_GET['id'] ?? ($userdata['id'] ?? 0));
    if ($uid <= 0) {
        $is_ajax ? $json(['success'=>false,'error'=>'Profile ID not found'], 400) : exit('Error: Profile ID not found.');
    }

    // Права: владелец или модератор
    $is_own_profile = ($user_uid === $uid);
    $is_mod = is_mod($usergroups ?? []);
    if (!$is_own_profile && !$is_mod) {
        $is_ajax ? $json(['success'=>false,'error'=>'No permission'], 403) : exit('Error: No permission to change this avatar.');
    }

    // CSRF
    if (empty($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        $is_ajax ? $json(['success'=>false,'error'=>'CSRF check failed'], 403) : exit('Error: CSRF check failed.');
    }

    // Файл
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $is_ajax ? $json(['success'=>false,'error'=>'File not uploaded'], 400) : exit('Error: File not uploaded.');
    }

    $file_name = $_FILES['avatar']['name'];
    $file_tmp  = $_FILES['avatar']['tmp_name'];
    $file_size = $_FILES['avatar']['size'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Разрешённые форматы и лимит
    $allowed_ext = ['jpg','jpeg','png','gif','webp'];
    $max_size = 22 * 1024 * 1024;
    if (!in_array($file_ext, $allowed_ext, true)) {
        $is_ajax ? $json(['success'=>false,'error'=>'Allowed formats: JPG, JPEG, PNG, GIF, WebP'], 415) : exit('Error: Allowed formats: JPG, JPEG, PNG, GIF, WebP.');
    }
    if ($file_size > $max_size) {
        $is_ajax ? $json(['success'=>false,'error'=>'File too big (max 22 MB)'], 413) : exit('Error: File too big.');
    }

    // MIME check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true)) {
        $is_ajax ? $json(['success'=>false,'error'=>'File is not a valid image'], 415) : exit('Error: File is not a valid image.');
    }

    // Папка и имя
    $upload_dir = $rootpath . '/uploads/avatars/';
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

    $new_name  = "avatar_{$uid}." . $file_ext;
    $dest_path = $upload_dir . $new_name;

    // Удаляем старые аватары
    foreach ($allowed_ext as $e) {
        $p = $upload_dir . "avatar_{$uid}." . $e;
        if ($e !== $file_ext && is_file($p)) @unlink($p);
    }

    if (!move_uploaded_file($file_tmp, $dest_path)) {
        $is_ajax ? $json(['success'=>false,'error'=>'Could not save file'], 500) : exit('Error: Could not save file.');
    }

    // Получаем размеры
    $size = @getimagesize($dest_path);
    if (!$size) {
        @unlink($dest_path);
        $is_ajax ? $json(['success'=>false,'error'=>'File corrupted or not an image'], 415) : exit('Error: File corrupted or not an image.');
    }
    [$width, $height] = $size;
    $avatar_dimensions = "{$width}|{$height}";

    // Относительный путь для вывода
    $avatar_url = "/uploads/avatars/" . $new_name;

    // Обновляем базу
    $updated_avatar = [
        "avatar"           => $avatar_url,
        "avatardimensions" => $avatar_dimensions,
        "avatartype"       => "upload",
    ];
  
  $current_modcomment = $userdata['modcomment'] 
    ?? $db->fetch_field($db->sql_query("SELECT modcomment FROM users WHERE id='{$uid}' LIMIT 1"), 'modcomment')
    ?? '';

$updated_avatar['modcomment'] = gmdate('Y-m-d H:i') . " - Avatar updated by {$CURUSER['username']}\n" . rtrim($current_modcomment);
$db->update_query("users", $updated_avatar, "id='{$uid}'");
  

    if ($is_ajax) {
        $json([
            'success' => true,
            'url' => $avatar_url,
            'width' => $width,
            'height' => $height,
            'message' => 'Avatar successfully updated'
        ]);
    } else {
        header("Location: edituser.php?action=edituser&userid={$uid}");
        exit;
    }
}



























/**
 * Проверяет, является ли URL корректным изображением
 */
function is_valid_avatar_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $ext = get_extension($url);
    $allowed_extensions = ['gif','jpg','jpeg','png','bmp','webp','svg','avif'];

    if (in_array(strtolower($ext), $allowed_extensions, true)) {
        return true;
    }

    $image_indicators = [
        '/avatar', '/avatars/', '/image/', '/images/', '/img/', '/imgs/',
        '/profile/', '/user/', '/users/', '/photo/', '/picture/', '/pic/',
        'gravatar.com/avatar/', 'i.imgur.com/'
    ];

    foreach ($image_indicators as $ind) {
        if (stripos($url, $ind) !== false) return true;
    }

    return false;
}

/**
 * Обновление аватара по URL
 */
function process_avatar_url(string $avatar_url, int $user_id): string
{
    global $db, $userdata;

    if ($user_id <= 0) return "Invalid user ID";

    // Проверяем URL
    if (!is_valid_avatar_url($avatar_url)) return "URL does not point to a valid image file";
    if (strlen($avatar_url) > 200) return "Avatar URL too long";

    // Удаляем старые аватары
    remove_avatars($user_id);

    // Получаем размеры
    $dimensions = '';
    $temp_file  = tempnam(sys_get_temp_dir(), 'avatar_');
    require_once INC_PATH . '/functions_ts_remote_connect.php';

    try {
        $file_content = TS_Fetch_Data($avatar_url);
        if ($file_content) {
            file_put_contents($temp_file, $file_content);
            $img_info = getimagesize($temp_file);
            if ($img_info !== false) {
                $dimensions = $img_info[0] . '|' . $img_info[1];
            }
        }
    } catch (Exception $e) {
        // Игнорируем ошибки скачивания
    }

    @unlink($temp_file);

    $db->update_query("users", [
        'avatar'           => $db->escape_string($avatar_url),
        'avatardimensions' => $dimensions,
        'avatartype'       => 'remote',
    ], "id='{$user_id}'");

    $userdata['avatar'] = $avatar_url;

    return ''; // успех
}

/**
 * Проверка локального аватара
 */
function is_valid_local_avatar(string $avatar): bool
{
    $avatar = trim($avatar);
    if ($avatar === '') return false;
    // FIX: нормализуем путь
    $normalized = ltrim(str_replace('\\', '/', $avatar), './');
    if (str_starts_with($normalized, 'uploads/avatars/')) {
        return (bool)preg_match('#\.(gif|jpg|jpeg|png|bmp|webp|avif)$#i', $normalized);
    }
    return false;
}


/**
 * Главная обработка аватара
 */
function handle_avatar_update(): void
{
    global $userdata, $db, $modcomment; // ← добавить $modcomment

    if (!isset($_POST['avatar'])) return;
    $new_avatar = trim($_POST['avatar'] ?? '');
	
	// Если аватар не менялся — пропускаем
    if (($_POST['avatar_changed'] ?? '0') !== '1') return;
	

    $current_user_id = (int)($userdata['id'] ?? $_POST['userid'] ?? 0);
    if ($current_user_id <= 0) {
        flash_message("Error: User ID not found", "error");
        return;
    }

    $normalize = fn(string $s): string =>
        preg_replace('/\?dateline=\d+$/', '', strtolower(ltrim(str_replace('\\', '/', trim($s)), './')));

    $old_avatar = $userdata['avatar'] ?? '';
    $new_clean  = $normalize($new_avatar);
    $old_clean  = $normalize($old_avatar);

    if ($new_clean === $old_clean) return;


    if ($new_avatar === '') {
        remove_avatars($current_user_id);
        $db->update_query("users", ['avatar' => '', 'avatardimensions' => '', 'avatartype' => ''], "id='{$current_user_id}'");
        $userdata['avatar'] = '';
        modcomment("Avatar removed");
        flash_message("Avatar successfully removed!", "success");
        return;
    }

    if (filter_var($new_avatar, FILTER_VALIDATE_URL)) {
        $error = process_avatar_url($new_avatar, $current_user_id);
        if ($error === "") {
            modcomment("Avatar updated via URL");
            flash_message("Avatar successfully updated!", "success");
        } elseif ($error !== "Avatar unchanged") {
            modcomment("Avatar update error: $error");
            flash_message("Error: $error", "error");
        }
        return;
    }

    if (is_valid_local_avatar($new_avatar)) {
        modcomment("Avatar updated (local)");
        flash_message("Avatar successfully updated!", "success");
        return;
    }

    modcomment("Attempt to update avatar: invalid format");
    flash_message("Invalid avatar format", "error");
}














// Get request parameters
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$do = $_POST['do'] ?? $_GET['do'] ?? '';
$userid = (int)($_POST['userid'] ?? $_GET['userid'] ?? 0);

// Permission check
if ($usergroups['canuserdetails'] != 1) 
{
    print_no_permission(true);
}

if (empty($action) || empty($userid) || !is_valid_id($userid))
	{
    print_no_permission(true);
}

int_check($userid, true);
$lang->load('modtask');
require_once INC_PATH . '/functions_mkprettytime.php';




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     handle_avatar_update();
}



// Route actions
match ($action) {
    'edituser' => handleEditUser(),
    'updateuser' => handleUpdateUser(),
    'deleteaccount' => handleDeleteAccount(),
    'resetpasskey' => handleResetPasskey(),
    default => print_no_permission(true)
};



/**
 * Get user status badges
 */
function get_user_status_badges(array $userdata): string
{
    $badges = [];
    
    if ($userdata['donor'] == 'yes') {
        $badges[] = '<span class="user-badge badge-success"><i class="fas fa-heart me-1"></i>Donor</span>';
    }
    
    if ($userdata['warned'] == 'yes') {
        $badges[] = '<span class="user-badge badge-warning"><i class="fas fa-exclamation-triangle me-1"></i>Warned</span>';
    }
    
    if ($userdata['enabled'] == 'no') {
        $badges[] = '<span class="user-badge badge-danger"><i class="fas fa-ban me-1"></i>Banned</span>';
    }
    
    if ($userdata['moderateposts'] == 1) {
        $badges[] = '<span class="user-badge badge-info"><i class="fas fa-eye me-1"></i>Moderated</span>';
    }
    
    return implode('', $badges);
}

/**
 * Get user statistics cards
 */
function get_user_stats(array $userdata): string
{
    $ratio = get_ratio($userdata);
    $ratioColor = $ratio == '∞' ? 'success' : ($ratio >= 1 ? 'success' : ($ratio >= 0.5 ? 'warning' : 'danger'));
    
    $stats = [
        [
            'icon' => 'fa-calendar',
            'label' => 'Joined',
            'value' => my_datee('relative', $userdata['added']),
            'color' => 'primary'
        ],
        [
            'icon' => 'fa-chart-line',
            'label' => 'Ratio',
            'value' => '<span class="text-' . $ratioColor . '">' . $ratio . '</span>',
            'color' => $ratioColor
        ],
        [
            'icon' => 'fa-upload',
            'label' => 'Uploaded',
            'value' => mksize($userdata['uploaded']),
            'color' => 'info'
        ],
        [
            'icon' => 'fa-download',
            'label' => 'Downloaded',
            'value' => mksize($userdata['downloaded']),
            'color' => 'warning'
        ],
        [
            'icon' => 'fa-star',
            'label' => 'Bonus Points',
            'value' => ts_nf($userdata['seedbonus']),
            'color' => 'success'
        ],
        [
            'icon' => 'fa-envelope-open',
            'label' => 'Invites',
            'value' => $userdata['invites'],
            'color' => 'secondary'
        ]
    ];
    
    return implode('', array_map(fn($stat) => "
        <div class='col-md-4'>
            <div class='stat-card'>
                <div class='d-flex align-items-center'>
                    <i class='fas {$stat['icon']} fa-2x text-{$stat['color']} me-3'></i>
                    <div>
                        <div class='fw-bold fs-5'>{$stat['value']}</div>
                        <small class='text-muted'>{$stat['label']}</small>
                    </div>
                </div>
            </div>
        </div>
    ", $stats));
}





/**
 * Render basic info tab
 */
function renderBasicInfoTab(): string
{
    global $userdata;
    
	
   $useravatar = format_avatar($userdata['avatar'], $userdata['avatardimensions']);

   // Если аватар — это HTML-заглушка (начинается с '<'), выводим её как есть
   if (strpos($useravatar['image'], '<') === 0) 
   {
       $avatarHtml = $useravatar['image']; // <div class="avatar-ring2">No Avatar</div>
   } 
   // Иначе выводим как <img> (стандартный аватар)
   else 
   {
       $avatarHtml = '<img class="rounded img-fluid" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . ' />';
   }	
		
		
		
		
    
    return '
    <div class="row">
        <div class="col-lg-8">
            <div class="row">
                <div class="col-md-6">
                   
				   ' . inputbox('Username', 'username', $userdata['username'], 'form-control', '35', '', '255', true, '', true) . '
				   
                </div>
                <div class="col-md-6">
                    ' . inputbox('Email Address', 'email', $userdata['email'], 'form-control', '35', '', '255', true, '', true) . '
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    ' . inputbox('New Password', 'password', '', 'form-control', '35', '<small class="text-muted">Leave blank to keep current</small>', '255', false) . '
                </div>
                <div class="col-md-6">
                    ' . inputbox('User Title', 'usertitle', htmlspecialchars_uni($userdata['usertitle']), 'form-control', '35', '', '255', true) . '
                </div>
            </div>
            
             ' . selectbox('Tracker Usergroup', 'usergroup', 'trackergroups', 'form-select') .'
            
            ' . inputbox('Avatar URL', 'avatar', htmlspecialchars_uni($userdata['avatar']), 'form-control', '35', '<small class="text-muted">Full URL to avatar image</small>', '500', true) . '
			
			
			<input type="hidden" name="avatar_changed" id="avatar_changed" value="0">
<script>
document.querySelector(\'input[name="avatar"]\').addEventListener(\'change\', function(){
    document.getElementById(\'avatar_changed\').value = \'1\';
});
</script>
			
			
			
			
        </div>
        
        
				
				
<div class="col-lg-4">
            <div class="text-center">	
				
			<div class="col-auto">
  <div class="avatar-ring position-relative hov-soft"
       id="avatar-container"
       title="Avatar">
    <div>
      ' . $avatarHtml . '
    </div>

  </div>
</div>


				
				
				
				
				
				
				
				
				
				
                <div class="avatar-actions">

<button type="submit" class="btn btn-outline-danger btn-sm me-2" name="remove_avatar" value="1">
    <i class="fas fa-trash me-1"></i>Remove
</button>
		
					
					
					
					
					
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="testAvatar()">
                        <i class="fas fa-sync me-1"></i>Test
                    </button>
                </div>
            </div>
            
            <div class="mt-4">
                <label class="form-label fw-semibold">Signature Preview</label>
                <div class="signature-preview p-3 bg-light rounded">
                    ' . ($userdata['signature'] ? htmlspecialchars_uni($userdata['signature']) : '<em class="text-muted">No signature</em>') . '
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <label class="form-label fw-semibold">User Signature</label>
            <textarea name="signature" class="form-control" rows="4" placeholder="Enter user signature...">' . htmlspecialchars_uni($userdata['signature']) . '</textarea>
        </div>
    </div>';
}

/**
 * Render permissions tab
 */
function renderPermissionsTab(): string
{
    global $userdata, $db, $userid;
    
    // Get current permissions
   
$cancomment  = ($userdata['cancomment']  ?? 1) == 1 ? 'yes' : 'no';
$canupload   = ($userdata['canupload']   ?? 1) == 1 ? 'yes' : 'no';
$candownload = ($userdata['candownload'] ?? 1) == 1 ? 'yes' : 'no';

    $banIpSelect = '
    <div class="mb-4">
        <label class="form-label fw-semibold mb-3">
            <i class="fas fa-ban me-2 text-danger"></i>IP Ban Action
        </label>
        <select name="banip" class="form-select">
            <option value="no">Do Not Ban IP</option>
            <option value="yes">Ban Associated IP Address</option>
        </select>
        <small class="text-muted">Applies when disabling account</small>
    </div>';

    $disabledAlert = $userdata['enabled'] == 'no' ? '
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Account Disabled</h6>
        <p class="mb-0">' . htmlspecialchars_uni($userdata['notifs'] ?? 'No reason provided') . '</p>
    </div>' : '';

    // Для moderateposts преобразуем числовое значение в строковое для отображения
    $moderatepostsValue = $userdata['moderateposts'] ? '1' : '0';

    return '
    <div class="row">
        <div class="col-md-6">
            <div class="permission-section mb-5">
                <h5 class="mb-4"><i class="fas fa-shield-alt me-2 text-primary"></i>Account Permissions</h5>
                
                ' . yesno('Account Enabled', 'enabled', $userdata['enabled']) . '
                
                ' . yesno('Can Post Comments', 'cancomment', $cancomment) . '
                
                ' . yesno('Can Upload Torrents', 'canupload', $canupload) . '
                
                ' . yesno('Can Download Torrents', 'candownload', $candownload) . '
                
                ' . yesno('Moderate User Posts', 'moderateposts', $moderatepostsValue) . '
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="permission-section mb-5">
                <h5 class="mb-4"><i class="fas fa-gem me-2 text-warning"></i>Special Status</h5>
                
                ' . yesno('Is Donor', 'donor', $userdata['donor']) . '
                
                ' . $banIpSelect . '
            </div>
            
            ' . $disabledAlert . '
        </div>
    </div>';
}

/**
 * Render warnings tab
 */
function renderWarningsTab(): string
{
    global $userdata, $db, $BASEURL, $ban_user_limit, $dateformat;
    
    $warned = $userdata['warned'] === 'yes';
    $leechwarn = $userdata['leechwarn'] === 'yes';
    
    $warningInfo = '';
    if ($warned) {
        $warneduntil = mkprettytime($userdata['warneduntil'] - TIMENOW);
        $warneduntilDate = my_datee($dateformat, $userdata['warneduntil']);
        
        $warningInfo = '
        <div class="alert alert-warning">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h6 class="alert-heading mb-1">User is Currently Warned</h6>
                    <p class="mb-1">Expires: ' . ($warneduntil === '0' ? '<strong>Never</strong>' : $warneduntilDate) . '</p>
                    <small class="text-muted">' . $warneduntil . ' remaining</small>
                </div>
            </div>
        </div>';
    }
    
    $leechWarningInfo = '';
    if ($leechwarn) {
        $leechuntil = mkprettytime($userdata['leechwarnuntil'] - TIMENOW);
        $leechuntilDate = my_datee($dateformat, $userdata['leechwarnuntil']);
        
        $leechWarningInfo = '
        <div class="alert alert-danger">
            <div class="d-flex align-items-center">
                <i class="fas fa-skull-crossbones fa-2x me-3"></i>
                <div>
                    <h6 class="alert-heading mb-1">Auto Leech Warning Active</h6>
                    <p class="mb-1">Low ratio detected - expires: ' . ($leechuntil === '0' ? '<strong>Never</strong>' : $leechuntilDate) . '</p>
                    <small class="text-muted">' . $leechuntil . ' remaining</small>
                </div>
            </div>
        </div>';
    }

    return '
    <div class="row">
        <div class="col-md-6">
            ' . $warningInfo . '
            ' . $leechWarningInfo . '
            
            <div class="warning-controls">
                <h5 class="mb-4"><i class="fas fa-exclamation-circle me-2 text-warning"></i>Warning Controls</h5>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Warning Status</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="warned" id="warned_no" value="no" ' . (!$warned ? 'checked' : '') . '>
                        <label class="btn btn-outline-success" for="warned_no">
                            <i class="fas fa-check me-2"></i>Not Warned
                        </label>
                        
                        <input type="radio" class="btn-check" name="warned" id="warned_yes" value="yes" ' . ($warned ? 'checked' : '') . '>
                        <label class="btn btn-outline-warning" for="warned_yes">
                            <i class="fas fa-exclamation-triangle me-2"></i>Warned
                        </label>
                    </div>
                </div>
                
                ' . (!$warned ? '
                <div class="mb-4">
                    <label class="form-label fw-semibold">Warn Duration</label>
                    <select name="warnlength" class="form-select">' . weeks() . '</select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Warning Reason</label>
                    <input type="text" class="form-control" name="warnpm" placeholder="Enter reason for warning...">
                </div>' : '') . '
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="warning-stats">
                <h5 class="mb-4"><i class="fas fa-chart-bar me-2 text-info"></i>Warning Statistics</h5>
                
                <div class="stat-card mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Times Warned:</span>
                        <span class="badge bg-' . ($userdata['timeswarned'] >= $ban_user_limit ? 'danger' : 'warning') . ' fs-6">
                            ' . $userdata['timeswarned'] . '
                        </span>
                    </div>
                    <small class="text-muted">Max: ' . $ban_user_limit . ' before automatic ban</small>
                </div>
                
                ' . ($userdata['timeswarned'] > 1 ? '
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="reset_timeswarned" value="yes" id="resetWarned">
                    <label class="form-check-label" for="resetWarned">Reset warning counter</label>
                </div>' : '') . '
                
                ' . get_last_warning_info() . '
            </div>
        </div>
    </div>';
}

/**
 * Get last warning information
 */
function get_last_warning_info(): string
{
    global $userdata, $db, $BASEURL, $dateformat;
    
    if ($userdata['timeswarned'] == 0 || empty($userdata['warnedby'])) {
        return '
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            This user hasn\'t been warned yet.
        </div>';
    }
    
    $elapsedlw = mkprettytime(TIMENOW - $userdata['lastwarned']);
    
    if ($userdata['warnedby'] !== 'System' && !empty($userdata['warnedby'])) {
        $res = $db->sql_query('SELECT id, username FROM users WHERE id = ' . $userdata['warnedby']);
        $arr = $db->fetch_array($res);
        $warnedby = ' by <a href="' . $BASEURL . '/userdetails.php?id=' . $arr['id'] . '" class="text-decoration-none">' . htmlspecialchars_uni($arr['username']) . '</a>';
    } else {
        $warnedby = ' automatically by System';
    }
    
    return '
    <div class="alert alert-secondary">
        <h6><i class="fas fa-history me-2"></i>Last Warning</h6>
        <p class="mb-1">' . $elapsedlw . ' ago' . $warnedby . '</p>
        <small class="text-muted">Last warned: ' . my_datee($dateformat, $userdata['lastwarned']) . '</small>
    </div>';
}

/**
 * Render statistics tab
 */
function renderStatisticsTab(): string
{
    global $userdata, $usergroups;
    
    $othervalue = ($usergroups['cansettingspanel'] == '1' || $usergroups['issupermod'] == '1') ? '' : 'disabled';
    $disabledAttr = $othervalue ? 'disabled' : '';
    $disabledClass = $othervalue ? 'opacity-50' : '';
    
    return '
    <div class="row">
        <div class="col-md-6">
            <div class="statistics-section">
                <h5 class="mb-4"><i class="fas fa-chart-line me-2 text-success"></i>Traffic Statistics</h5>
                
                <div class="mb-4 ' . $disabledClass . '">
                    <label class="form-label fw-semibold">Uploaded Amount</label>
                    <input type="text" class="form-control" name="uploaded" value="' . htmlspecialchars_uni($userdata['uploaded']) . '" ' . $disabledAttr . '>
                    <small class="text-muted">Current: ' . mksize($userdata['uploaded']) . '</small>
                </div>
                
                <div class="mb-4 ' . $disabledClass . '">
                    <label class="form-label fw-semibold">Downloaded Amount</label>
                    <input type="text" class="form-control" name="downloaded" value="' . htmlspecialchars_uni($userdata['downloaded']) . '" ' . $disabledAttr . '>
                    <small class="text-muted">Current: ' . mksize($userdata['downloaded']) . '</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="bonus-section">
                <h5 class="mb-4"><i class="fas fa-gift me-2 text-warning"></i>Bonus & Invites</h5>
                
                <div class="mb-4 ' . $disabledClass . '">
                    <label class="form-label fw-semibold">Bonus Points</label>
                    <input type="text" class="form-control" name="seedbonus" value="' . htmlspecialchars_uni($userdata['seedbonus']) . '" ' . $disabledAttr . '>
                    <small class="text-muted">Current: ' . ts_nf($userdata['seedbonus']) . ' points</small>
                </div>
                
                <div class="mb-4 ' . $disabledClass . '">
                    <label class="form-label fw-semibold">Invite Count</label>
                    <input type="text" class="form-control" name="invites" value="' . (int)$userdata['invites'] . '" ' . $disabledAttr . '>
                    <small class="text-muted">Available invitations</small>
                </div>
            </div>
        </div>
    </div>
    
    ' . ($othervalue ? '
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Permission Notice:</strong> You need higher privileges to modify statistics and bonus points.
    </div>' : '') . '

    <!-- Charts -->
    <div class="row mt-5">
        <div class="col-12 mb-3">
            <h5 class="fw-bold"><i class="fas fa-chart-area me-2 text-primary"></i>Upload / Download by Month <small class="text-muted fs-6">(last 12 months, GB)</small></h5>
            <canvas id="trafficChart" height="80"></canvas>
        </div>
        <div class="col-12 mt-4">
            <h5 class="fw-bold"><i class="fas fa-chart-bar me-2 text-success"></i>Site Activity <small class="text-muted fs-6">(last 30 days)</small></h5>
            <canvas id="activityChart" height="60"></canvas>
        </div>
    </div>';
}

/**
 * Render advanced tab
 */
function renderAdvancedTab(): string
{
    global $userdata, $usergroups;
    
    $modcomment = htmlspecialchars_uni($userdata['modcomment']);
    $bonuscomment = htmlspecialchars_uni($userdata['bonuscomment']);
    $readonly = $usergroups['cansettingspanel'] != '1' ? 'readonly' : '';
    $readonlyAttr = $readonly ? 'readonly' : '';
    
    return '
    <div class="row">
        <div class="col-12">
            <div class="advanced-section">
                <h5 class="mb-4"><i class="fas fa-cogs me-2 text-primary"></i>Moderator Comments</h5>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Current Moderation Comments</label>
                    <textarea name="modcomment" class="form-control" rows="20" ' . $readonlyAttr . ' placeholder="Moderator comments and notes...">' . $modcomment . '</textarea>
                    ' . ($readonly ? '<small class="text-muted">Read-only - Higher privileges required to edit</small>' : '') . '
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Add New Comment</label>
                    <textarea name="addcomment" class="form-control" rows="3" placeholder="Add new moderator comment..."></textarea>
                    <small class="text-muted">This will be prepended to existing comments</small>
                </div>
            </div>
            
            <div class="system-section mt-5">
                <h5 class="mb-4"><i class="fas fa-database me-2 text-secondary"></i>System Information</h5>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Seeding Karma Log</label>
                    <textarea class="form-control" rows="4" readonly>' . $bonuscomment . '</textarea>
                    <small class="text-muted">System-generated karma log - read only</small>
                </div>
                
                <div class="system-info">
                    
				<div class="info-grid">
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                            </div>
                            <div class="info-label">Registered</div>
                            <div class="info-value">' . my_datee('relative', $userdata['added']) . '</div>
                            <small class="text-muted">' . my_datee('d M Y', $userdata['added']) . '</small>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-clock fa-2x text-info"></i>
                            </div>
                            <div class="info-label">Last Active</div>
                            <div class="info-value">' . my_datee('relative', $userdata['lastactive']) . '</div>
                            <small class="text-muted">' . ($userdata['lastactive'] ? my_datee('d M Y H:i', $userdata['lastactive']) : 'Never') . '</small>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-history fa-2x text-warning"></i>
                            </div>
                            <div class="info-label">Last Visit</div>
                            <div class="info-value">' . my_datee('relative', $userdata['lastvisit']) . '</div>
                            <small class="text-muted">' . ($userdata['lastvisit'] ? my_datee('d M Y H:i', $userdata['lastvisit']) : 'Never') . '</small>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-comments fa-2x text-success"></i>
                            </div>
                            <div class="info-label">Forum Posts</div>
                            <div class="info-value">' . ts_nf($userdata['post_count'] ?? 0) . '</div>
                            <small class="text-muted">total messages</small>
                        </div>
                    </div>		
					
					
					
                </div>
            </div>
        </div>
    </div>';
}







function renderAccountSettingsTab(): string
{
    global $userdata;
    
    return '
    <div class="row">
        <div class="col-md-6">
            
			
			
			<h5 class="mb-4"><i class="fas fa-user-shield me-2 text-success"></i>Login, Cookies & Privacy</h5>
            
            ' . yesno('Hide from the Who\'s Online list', 'invisible', $userdata['invisible'] ? '1' : '0') . '
			
			
			
			
			
			<h5 class="mb-4"><i class="fas fa-envelope me-2 text-primary"></i>Email & Privacy Settings</h5>
            
            ' . yesno('Receive emails from administrators', 'allownotices', $userdata['allownotices'] ? '1' : '0') . '
            
            ' . yesno('Hide email address from other members', 'hideemail', $userdata['hideemail'] ? '1' : '0') . '
            
            ' . yesno('Receive private messages from other users', 'receivepms', $userdata['receivepms'] ? '1' : '0') . '
            
            ' . yesno('Only receive private messages from buddy list (this setting has no effect unless there is at least one buddy on the list)', 'receivefrombuddy', $userdata['receivefrombuddy'] ? '1' : '0') . '
        </div>
        
        <div class="col-md-6">
            <h5 class="mb-4"><i class="fas fa-bell me-2 text-warning"></i>Notification Settings</h5>
            
            ' . yesno('Alert with notice when new private message is received', 'pmnotice', $userdata['pmnotice'] ? '1' : '0') . '
            
            ' . yesno('Notify by email when new private message is received', 'pmnotify', $userdata['pmnotify'] ? '1' : '0') . '
            
            ' . yesno('Receive PM notifications for new buddy requests', 'buddyrequestspm', $userdata['buddyrequestspm'] ? '1' : '0') . '
            
            ' . yesno('Automatically accept buddy requests (if the above checkbox is ticked, a PM is sent informing of the new buddy connection)', 'buddyrequestsauto', $userdata['buddyrequestsauto'] ? '1' : '0') . '
            
            <div class="mb-4">
                <label class="form-label fw-semibold">Default thread subscription mode</label>
                <select name="subscriptionmethod" class="form-select">
                    <option value="0" ' . ($userdata['subscriptionmethod'] == 0 ? 'selected' : '') . '>Do not subscribe</option>
                    <option value="1" ' . ($userdata['subscriptionmethod'] == 1 ? 'selected' : '') . '>No notification</option>
                    <option value="2" ' . ($userdata['subscriptionmethod'] == 2 ? 'selected' : '') . '>Instant email notification</option>
					<option value="3" ' . ($userdata['subscriptionmethod'] == 3 ? 'selected' : '') . '>Instant PM notification</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> "Only receive private messages from buddy list" setting will only work if the user has at least one buddy in their buddy list.
            </div>
        </div>
    </div>';
}






/**
 * Handle user edit form with premium UI
 */


/**
 * Render Activity / History tab
 */
function renderActivityTab(): string
{
    global $userdata, $db, $BASEURL, $dateformat;

    $uid = (int)$userdata['id'];

    // Последние загруженные торренты
    $q = $db->sql_query("SELECT id, name, added, seeders, leechers FROM torrents WHERE owner = {$uid} ORDER BY added DESC LIMIT 10");
    $uploads_html = '';
    while ($row = $db->fetch_array($q)) {
        $badge = $row['seeders'] > 0
            ? '<span class="badge bg-success">' . (int)$row['seeders'] . 'S</span>'
            : '<span class="badge bg-danger">Dead</span>';
        $uploads_html .= '<li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div>
                <a href="' . $BASEURL . '/details.php?id=' . (int)$row['id'] . '" class="text-decoration-none small fw-semibold">' . htmlspecialchars_uni($row['name']) . '</a>
                <div class="text-muted" style="font-size:.72rem">' . my_datee($dateformat, (int)$row['added']) . '</div>
            </div>
            ' . $badge . '
        </li>';
    }
    if (!$uploads_html) $uploads_html = '<li class="list-group-item text-muted text-center py-3">No uploads</li>';

    // Последние скачивания
    $q = $db->sql_query("SELECT s.torrentid, t.name, s.completedat, s.finished FROM snatched s LEFT JOIN torrents t ON (s.torrentid = t.id) WHERE s.userid = {$uid} ORDER BY s.completedat DESC LIMIT 10");
    $downloads_html = '';
    while ($row = $db->fetch_array($q)) {
        $fin  = $row['finished'] === 'yes'
            ? '<span class="badge bg-success">Finished</span>'
            : '<span class="badge bg-warning text-dark">Incomplete</span>';
        $name = $row['name'] ? htmlspecialchars_uni($row['name']) : '<em class="text-muted">Deleted torrent</em>';
        $downloads_html .= '<li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div>
                <span class="small fw-semibold">' . $name . '</span>
                <div class="text-muted" style="font-size:.72rem">' . ($row['completedat'] ? my_datee($dateformat, (int)$row['completedat']) : '—') . '</div>
            </div>
            ' . $fin . '
        </li>';
    }
    if (!$downloads_html) $downloads_html = '<li class="list-group-item text-muted text-center py-3">No downloads</li>';

    // Последние комментарии
    $q = $db->sql_query("SELECT c.id, c.torrent, c.dateline, c.text, t.name FROM comments c LEFT JOIN torrents t ON (c.torrent = t.id) WHERE c.user = {$uid} ORDER BY c.dateline DESC LIMIT 5");
    $comments_html = '';
    while ($row = $db->fetch_array($q)) {
        $torr = $row['name'] ? htmlspecialchars_uni($row['name']) : '#' . (int)$row['torrent'];
        $txt  = mb_substr(strip_tags($row['text'] ?? ''), 0, 80);
        $comments_html .= '<li class="list-group-item py-2">
            <div class="d-flex justify-content-between">
                <small class="fw-semibold text-primary">' . $torr . '</small>
                <small class="text-muted">' . my_datee($dateformat, (int)$row['dateline']) . '</small>
            </div>
            <div class="small text-muted mt-1">' . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . (mb_strlen($row['text'] ?? '') > 80 ? '…' : '') . '</div>
        </li>';
    }
    if (!$comments_html) $comments_html = '<li class="list-group-item text-muted text-center py-3">No comments</li>';

    // ► Статистика из $userdata — запросов нет
    $seedtime_fmt  = $userdata['snatch_seedtime']  ? mkprettytime((int)$userdata['snatch_seedtime'])  : '—';
    $leechtime_fmt = $userdata['snatch_leechtime'] ? mkprettytime((int)$userdata['snatch_leechtime']) : '—';

    return '
    <div class="row g-4">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-3">
                <div class="border rounded-3 px-3 py-2 text-center" style="min-width:110px">
                    <div class="fw-bold fs-5 text-primary">' . (int)($userdata['snatch_total'] ?? 0) . '</div>
                    <small class="text-muted">Snatches</small>
                </div>
                <div class="border rounded-3 px-3 py-2 text-center" style="min-width:110px">
                    <div class="fw-bold fs-5 text-success">' . (int)($userdata['snatch_done'] ?? 0) . '</div>
                    <small class="text-muted">Completed</small>
                </div>
                <div class="border rounded-3 px-3 py-2 text-center" style="min-width:110px">
                    <div class="fw-bold fs-5 text-info">' . $seedtime_fmt . '</div>
                    <small class="text-muted">Seed Time</small>
                </div>
                <div class="border rounded-3 px-3 py-2 text-center" style="min-width:110px">
                    <div class="fw-bold fs-5 text-warning">' . $leechtime_fmt . '</div>
                    <small class="text-muted">Leech Time</small>
                </div>
                <div class="border rounded-3 px-3 py-2 text-center" style="min-width:110px">
                    <div class="fw-bold fs-5 text-success">' . (int)($userdata['peers_seeding'] ?? 0) . ' / ' . (int)($userdata['peers_total'] ?? 0) . '</div>
                    <small class="text-muted">Active Peers</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <h6 class="fw-bold mb-3"><i class="fas fa-upload me-2 text-primary"></i>Last Uploads</h6>
            <ul class="list-group list-group-flush border rounded-3" style="max-height:280px;overflow-y:auto">
                ' . $uploads_html . '
            </ul>
        </div>
        <div class="col-md-6">
            <h6 class="fw-bold mb-3"><i class="fas fa-download me-2 text-success"></i>Last Downloads</h6>
            <ul class="list-group list-group-flush border rounded-3" style="max-height:280px;overflow-y:auto">
                ' . $downloads_html . '
            </ul>
        </div>
        <div class="col-12">
            <h6 class="fw-bold mb-3"><i class="fas fa-comment me-2 text-warning"></i>Last Comments</h6>
            <ul class="list-group list-group-flush border rounded-3">
                ' . $comments_html . '
            </ul>
        </div>
    </div>';
}









/**
 * Render Security tab
 */
function renderSecurityTab(): string
{
    global $userdata, $db, $BASEURL, $dateformat, $timeformat, $mybb;

    $uid      = (int)$userdata['id'];
    $h_script = htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8');
    $postCode = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');

    // Активные сессии
    $q = $db->sql_query("SELECT sid, ip, time, location, useragent FROM sessions WHERE uid = {$uid} ORDER BY time DESC LIMIT 10");
    $sessions_html = '';
    while ($row = $db->fetch_array($q)) {
        $ip       = htmlspecialchars(my_inet_ntop($db->unescape_binary($row['ip'])), ENT_QUOTES, 'UTF-8');
        $ua_short = mb_substr($row['useragent'], 0, 60);
        $time_ago = my_datee('relative', (int)$row['time']);
        $sessions_html .= '<li class="list-group-item py-2">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <code class="small">' . $ip . '</code>
                    <div class="text-muted" style="font-size:.72rem">' . htmlspecialchars($ua_short, ENT_QUOTES, 'UTF-8') . '</div>
                </div>
                <small class="text-muted text-nowrap ms-2">' . $time_ago . '</small>
            </div>
        </li>';
    }
    if (!$sessions_html) $sessions_html = '<li class="list-group-item text-muted text-center py-3">No active sessions</li>';

    // IP история
    $regip  = $userdata['regip']  ? htmlspecialchars(my_inet_ntop($db->unescape_binary($userdata['regip'])),  ENT_QUOTES, 'UTF-8') : '—';
    $lastip = $userdata['lastip'] ? htmlspecialchars(my_inet_ntop($db->unescape_binary($userdata['lastip'])), ENT_QUOTES, 'UTF-8') : '—';
    $ip_warn = ($regip !== $lastip && $regip !== '—' && $lastip !== '—')
        ? '<div class="alert alert-warning py-2 mt-2 small"><i class="fas fa-exclamation-triangle me-1"></i>Registration IP differs from last IP — possible account sharing.</div>'
        : '';

    // Login attempts
    $attempts = (int)$userdata['loginattempts'];
    $lockout  = (int)$userdata['loginlockoutexpiry'];
    $lockout_html = ($lockout > time())
        ? '<span class="badge bg-danger">Locked until ' . my_datee($dateformat, $lockout) . '</span>'
        : ($attempts > 0 ? '<span class="badge bg-warning text-dark">' . $attempts . ' failed attempt(s)</span>' : '<span class="badge bg-success">OK</span>');

    // Passkey
    $passkey = htmlspecialchars($userdata['passkey'], ENT_QUOTES, 'UTF-8');

    return '
    <div class="row g-4">

        <!-- IP Info -->
        <div class="col-md-6">
            <h6 class="fw-bold mb-3"><i class="fas fa-network-wired me-2 text-primary"></i>IP Information</h6>
            <table class="table table-sm table-bordered">
                <tr><th class="text-muted" style="width:40%">Registration IP</th><td><code>' . $regip . '</code></td></tr>
                <tr><th class="text-muted">Last IP</th><td><code>' . $lastip . '</code></td></tr>
            </table>
            ' . $ip_warn . '
        </div>

        <!-- Login Security -->
        <div class="col-md-6">
            <h6 class="fw-bold mb-3"><i class="fas fa-lock me-2 text-warning"></i>Login Security</h6>
            <table class="table table-sm table-bordered">
                <tr><th class="text-muted" style="width:40%">Login Status</th><td>' . $lockout_html . '</td></tr>
                <tr><th class="text-muted">Last Login</th><td>' . ($userdata['last_login'] ? my_datee($dateformat, (int)$userdata['last_login']) : '—') . '</td></tr>
                <tr><th class="text-muted">Registered</th><td>' . my_datee($dateformat, (int)$userdata['added']) . '</td></tr>
            </table>
            <!-- Reset lockout -->
            <button type="button" class="btn btn-sm btn-outline-warning mt-2"
        onclick="securityAction(\'reset_lockout\', ' . $uid . ', \'' . $postCode . '\', \'' . $h_script . '\')">
    <i class="fas fa-unlock me-1"></i>Reset Login Lockout
</button>
        </div>

        <!-- Passkey -->
        <div class="col-12">
            <h6 class="fw-bold mb-3"><i class="fas fa-key me-2 text-success"></i>Passkey</h6>
            <div class="d-flex align-items-center gap-2">
                <code class="passkey-text border rounded px-3 py-2 bg-body-secondary"
                      id="passkeyText" style="filter:blur(4px);user-select:none;letter-spacing:.1em">
                    ' . $passkey . '
                </code>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="passkeyToggle">
                    <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="passkeyToggle" onclick="navigator.clipboard.writeText(\'' . $passkey . '\')">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        </div>

        <!-- Active Sessions -->
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-desktop me-2 text-info"></i>Active Sessions</h6>
                
				<button type="button" class="btn btn-sm btn-outline-danger"
        onclick="securityAction(\'kill_sessions\', ' . $uid . ', \'' . $postCode . '\', \'' . $h_script . '\')">
    <i class="fas fa-sign-out-alt me-1"></i>Kill All Sessions
</button>
				
            </div>
            <ul class="list-group list-group-flush border rounded-3" style="max-height:240px;overflow-y:auto">
                ' . $sessions_html . '
            </ul>
        </div>

    </div>

    <script>
    (function(){
        var el  = document.getElementById("passkeyText");
        var btn = document.getElementById("passkeyToggle");
        if (!el || !btn) return;
        btn.addEventListener("click", function(){
            var hidden = el.style.filter !== "none";
            el.style.filter      = hidden ? "none" : "blur(4px)";
            el.style.userSelect  = hidden ? "text" : "none";
            btn.querySelector("i").className = hidden ? "fas fa-eye-slash" : "fas fa-eye";
        });
    }());
	
	
	
	function quickBan(uid, bannedGid, postKey, script) {
        var reason = document.getElementById("banReason").value.trim();
        var errEl  = document.getElementById("banReasonError");
        if (!reason) {
            errEl.classList.remove("d-none");
            return;
        }
        errEl.classList.add("d-none");

        var duration = document.getElementById("banDuration").value;
        var sendPm   = document.getElementById("banSendPM").checked ? "1" : "";
        var btn      = document.getElementById("quickBanSubmit");
        btn.disabled = true;
        btn.innerHTML = "<i class=\"fas fa-spinner fa-spin me-1\"></i>Banning…";

        var data = new FormData();
        data.append("action",       "updateuser");
        data.append("userid",       uid);
        data.append("my_post_key",  postKey);
        data.append("quick_ban",    "1");
        data.append("ban_reason",   reason);
        data.append("ban_duration", duration);
        data.append("banned_gid",   bannedGid);
        data.append("ban_send_pm",  sendPm);

        fetch(script, { method: "POST", body: data })
            .then(function() { location.reload(); })
            .catch(function(e) {
                btn.disabled = false;
                btn.innerHTML = "<i class=\"fas fa-ban me-2\"></i>Ban User";
                alert("Error: " + e);
            });
    }
	
	
	
	
    </script>';
}

/**
 * Render Send PM modal trigger tab-section
 */
function renderSendPMModal(): string
{
    global $userdata, $mybb, $BASEURL;
    $uid      = (int)$userdata['id'];
    $username = htmlspecialchars_uni($userdata['username']);
    $postCode = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');
    $h_script = htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8');

    return '
<script src="' . $BASEURL . '/scripts/edit_delete_comment.js"></script>
<div class="modal fade" id="sendPMModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Send PM to ' . $username . '</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
	  <form method="post" action="' . $h_script . '">
        <input type="hidden" name="action"      value="updateuser">
        <input type="hidden" name="userid"      value="' . $uid . '">
        <input type="hidden" name="my_post_key" value="' . $postCode . '">
        <input type="hidden" name="send_pm"     value="1">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Subject</label>
            <input type="text" name="pm_subject" class="form-control" required placeholder="PM subject…">
          </div>
          <div class="mb-2">
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[b]\',\'[/b]\')"><b>B</b></button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[i]\',\'[/i]\')"><i>I</i></button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[u]\',\'[/u]\')"><u>U</u></button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[s]\',\'[/s]\')"><s>S</s></button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[left]\',\'[/left]\')">Left</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[center]\',\'[/center]\')">Center</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[right]\',\'[/right]\')">Right</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[color=red]\',\'[/color]\')">Red</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[size=18]\',\'[/size]\')">Size</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[url]\',\'[/url]\')">URL</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[img]\',\'[/img]\')">IMG</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[video]\',\'[/video]\')">Video</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[youtube]\',\'[/youtube]\')">YouTube</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[quote]\',\'[/quote]\')">Quote</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[code]\',\'[/code]\')">Code</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[list]\n[*]\',\'\n[/list]\')">List</button>
            <button class="btn btn-sm btn-light" type="button" onclick="wrapBBCodeNear(this,\'[list=1]\n[*]\',\'\n[/list]\')">#List</button>
          </div>
          <textarea name="pm_message" class="form-control mb-3" rows="6" required placeholder="Your message…"></textarea>
          <h6>Live Preview</h6>
          <div data-bb-preview class="border p-2 bg-light rounded" style="min-height:100px;"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane me-1"></i>Send PM
          </button>
        </div>
      </form>
    </div>
  </div>
</div>';
}




/**
 * Render Hit & Run tab
 */
function renderHitRunTab(): string
{
    global $userdata, $db, $BASEURL, $dateformat;

    $uid       = (int)$userdata['id'];
    $min_hours = 24; // H&R если сидировал меньше N часов после завершения

    // Статистика
    $total    = (int)($userdata['snatch_total'] ?? 0);
    $finished = (int)($userdata['snatch_done']  ?? 0);

    $q   = $db->sql_query("SELECT COUNT(*) AS hnr_count FROM snatched WHERE userid = {$uid} AND finished = 'yes' AND seedtime < {$min_hours} * 3600");
    $hnr = (int)($db->fetch_array($q)['hnr_count'] ?? 0);
    
    $hnr_ratio = $finished > 0 ? round($hnr / $finished * 100, 1) : 0;
    $hnr_color = $hnr_ratio < 10 ? 'success' : ($hnr_ratio < 30 ? 'warning' : 'danger');
    $hnr_status = $hnr_ratio < 10 ? 'Good' : ($hnr_ratio < 30 ? 'Warning' : 'Critical');

    // Список H&R торрентов
    $q = $db->sql_query("
        SELECT s.torrentid, t.name, s.downloaded, s.seedtime, s.leechtime,
               s.completedat, s.last_action
        FROM snatched s
        LEFT JOIN torrents t ON (s.torrentid = t.id)
        WHERE s.userid = {$uid}
          AND s.finished = 'yes'
          AND s.seedtime < {$min_hours} * 3600
        ORDER BY s.completedat DESC
        LIMIT 50
    ");

    $rows = '';
    while ($row = $db->fetch_array($q)) {
        $name     = $row['name'] ? htmlspecialchars_uni($row['name']) : '<em class="text-muted">Deleted torrent</em>';
        $seed_hours = $row['seedtime'] ? round($row['seedtime'] / 3600, 1) : 0;
        $required_hours = $min_hours;
        $missing_hours = round($required_hours - $seed_hours, 1);
        
        $seedh = $seed_hours . 'h';
        $dl    = mksize((int)$row['downloaded']);
        $date  = $row['completedat'] ? my_datee($dateformat, (int)$row['completedat']) : '—';
        
        // Определяем цвет и прогресс
        $badge_color = $seed_hours == 0 ? 'danger' : ($missing_hours > 12 ? 'warning' : 'info');
        $progress_width = min(100, round($seed_hours / $required_hours * 100));
        
        $rows .= '
        <tr class="hnr-row">
            <td class="align-middle">
                <div class="d-flex flex-column">
                    <a href="' . $BASEURL . '/details.php?id=' . (int)$row['torrentid'] . '"
                       class="text-decoration-none fw-semibold torrent-link">
                        <i class="fas fa-skull-crossbones text-danger me-1"></i>
                        ' . $name . '
                    </a>
                    <div class="text-muted small mt-1">
                        <i class="fas fa-clock me-1"></i>Missing: ' . $missing_hours . 'h
                    </div>
                </div>
            </td>
            <td class="align-middle text-center" style="min-width: 120px">
                <div class="d-flex flex-column">
                    <div class="progress hnr-progress mb-1" style="height: 6px;">
                        <div class="progress-bar bg-' . $badge_color . '" 
                             style="width: ' . $progress_width . '%"
                             role="progressbar"></div>
                    </div>
                    <div>
                        <span class="badge bg-' . $badge_color . '">' . $seedh . ' / ' . $required_hours . 'h</span>
                    </div>
                </div>
            </td>
            <td class="align-middle text-center">
                <span class="badge bg-secondary-subtle text-secondary border">
                    <i class="fas fa-database me-1"></i>' . $dl . '
                </span>
            </td>
            <td class="align-middle text-center">
                <small class="text-muted">
                    <i class="far fa-calendar-alt me-1"></i>' . $date . '
                </small>
            </td>
        </tr>';
    }
    
    if (!$rows) {
        $rows = '<tr><td colspan="4" class="text-center py-5">
            <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
            <p class="text-muted mb-0">No Hit & Runs found! Great seeding habits! 🎉</p>
        </td></tr>';
    }

    return '
    
	<style>
    .hnr-stats-card {
        background: white;
        border: none;
        transition: all 0.3s ease;
        cursor: default;
        border-radius: 16px;
        overflow: hidden;
    }
	
    
    .hnr-stats-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }
	
	 .stat-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
    }
	
	.risk-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.85rem;
    }
	
	 .table-hnr {
        border-radius: 12px;
        overflow: hidden;
    }
	
	</style>

    <!-- H&R Summary Cards -->
    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="hnr-stats-card card shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="stat-circle bg-primary bg-opacity-10 mx-auto">
                        <i class="fas fa-chart-line fa-2x text-primary"></i>
                    </div>
                    <div class="fw-bold fs-2 text-primary">' . number_format($total) . '</div>
                    <small class="text-muted">Total Snatches</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="hnr-stats-card card shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="stat-circle bg-success bg-opacity-10 mx-auto">
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                    <div class="fw-bold fs-2 text-success">' . number_format($finished) . '</div>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="hnr-stats-card card shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="stat-circle bg-danger bg-opacity-10 mx-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                    </div>
                    <div class="fw-bold fs-2 text-danger">' . number_format($hnr) . '</div>
                    <small class="text-muted">Hit & Runs</small>
                    <div class="text-muted small mt-1">
                        <i class="fas fa-hourglass-half me-1"></i>&lt;' . $min_hours . 'h seed
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="hnr-stats-card card shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="stat-circle bg-' . $hnr_color . ' bg-opacity-10 mx-auto">
                        <i class="fas fa-percent fa-2x text-' . $hnr_color . '"></i>
                    </div>
                    <div class="fw-bold fs-2 text-' . $hnr_color . '">' . $hnr_ratio . '%</div>
                    <small class="text-muted">H&R Ratio</small>
                    <div class="text-muted small mt-1">
                        <span class="risk-badge bg-' . $hnr_color . ' bg-opacity-10 text-' . $hnr_color . '">
                            <i class="fas fa-chart-simple me-1"></i>' . $hnr_status . '
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Warning Alert -->
    ' . ($hnr_ratio >= 10 ? '
    <div class="alert alert-' . $hnr_color . ' border-0 shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <i class="fas fa-exclamation-triangle fa-2x"></i>
            <div>
                <strong class="d-block">' . ($hnr_ratio >= 30 ? '⚠️ Critical H&R Rate Detected!' : '⚠️ Moderate H&R Rate Detected') . '</strong>
                <small>' . ($hnr_ratio >= 30 ? 'This user has a high number of incomplete downloads.' : 'User has some incomplete downloads. Monitor activity.') . '</small>
            </div>
        </div>
        ' . ($hnr_ratio >= 30 ? '
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-warning" onclick="showToast(\'Leech warning issued\', \'warning\')">
                <i class="fas fa-exclamation-triangle me-1"></i>Issue Warning
            </button>
            <button class="btn btn-sm btn-danger" onclick="showToast(\'Download rights restricted\', \'error\')">
                <i class="fas fa-ban me-1"></i>Restrict Downloads
            </button>
        </div>' : '') . '
    </div>' : '
    <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-center gap-3">
        <i class="fas fa-thumbs-up fa-2x"></i>
        <div>
            <strong>Good standing!</strong>
            <small class="d-block">User maintains good seeding habits with &lt;10% H&R ratio.</small>
        </div>
    </div>') . '

    <!-- H&R List Table -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">
                    <i class="fas fa-list me-2 text-danger"></i>Hit & Run Details
                </h6>
                <span class="badge bg-secondary">' . $hnr . ' records</span>
            </div>
        </div>
        <div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><i class="fas fa-torrent me-2"></i>Torrent Name</th>
                    <th class="text-center" style="width: 180px"><i class="fas fa-clock me-2"></i>Seed Time / Required</th>
                    <th class="text-center" style="width: 120px"><i class="fas fa-database me-2"></i>Downloaded</th>
                    <th class="text-center" style="width: 140px"><i class="far fa-calendar-alt me-2"></i>Completed</th>
                </tr>
            </thead>
            <tbody>' . $rows . '</tbody>
        </table>
    </div>
</div>
    </div>';
}











/**
 * Render Forum Activity tab
 */
function renderForumTab(): string
{
    global $userdata, $db, $BASEURL, $dateformat;

    $uid = (int)$userdata['id'];

    // Counts
    $post_count   = (int)($userdata['post_count']   ?? 0);
    $thread_count = (int)($userdata['thread_count'] ?? 0);

    $report_count = (int)($userdata['report_count'] ?? 0);
	
    // Последние 10 постов
    $q = $db->sql_query("
        SELECT p.pid, p.tid, p.fid, p.subject, p.dateline, p.message,
               t.subject AS thread_subject, f.name AS forum_name
        FROM posts p
        LEFT JOIN threads t ON (p.tid = t.tid)
        LEFT JOIN forums  f ON (p.fid = f.fid)
        WHERE p.uid = {$uid}
        ORDER BY p.dateline DESC
        LIMIT 10
    ");
    $posts_html = '';
    while ($row = $db->fetch_array($q)) {
        $thread = htmlspecialchars_uni($row['thread_subject'] ?: $row['subject']);
        $forum  = htmlspecialchars_uni($row['forum_name'] ?? '');
        $text   = mb_substr(strip_tags($row['message'] ?? ''), 0, 100);
        $date   = my_datee($dateformat, (int)$row['dateline']);
        $posts_html .= '
        <li class="list-group-item py-2">
            <div class="d-flex justify-content-between align-items-start">
                <div class="me-2">
                    <a href="' . $BASEURL . '/' . get_post_link((int)$row['pid']) . '#pid' . (int)$row['pid'] . '"
                       class="text-decoration-none small fw-semibold">' . $thread . '</a>
                    <div class="text-muted" style="font-size:.72rem">
                        <i class="fas fa-folder me-1"></i>' . $forum . '
                    </div>
                    <div class="text-muted small mt-1">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . ($text ? '…' : '') . '</div>
                </div>
                <small class="text-muted text-nowrap">' . $date . '</small>
            </div>
        </li>';
    }
    if (!$posts_html) $posts_html = '<li class="list-group-item text-muted text-center py-3">No posts</li>';

    // Последние 10 тредов
    $q = $db->sql_query("
        SELECT t.tid, t.fid, t.subject, t.dateline, t.replies, t.views,
               f.name AS forum_name
        FROM threads t
        LEFT JOIN forums f ON (t.fid = f.fid)
        WHERE t.uid = {$uid}
        ORDER BY t.dateline DESC
        LIMIT 10
    ");
    $threads_html = '';
    while ($row = $db->fetch_array($q)) {
        $subj  = htmlspecialchars_uni($row['subject']);
        $forum = htmlspecialchars_uni($row['forum_name'] ?? '');
        $date  = my_datee($dateformat, (int)$row['dateline']);
        $threads_html .= '
        <li class="list-group-item py-2">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <a href="' . $BASEURL . '/' . get_thread_link((int)$row['tid']) . '"
                       class="text-decoration-none small fw-semibold">' . $subj . '</a>
                    <div class="text-muted" style="font-size:.72rem">
                        <i class="fas fa-folder me-1"></i>' . $forum . '
                        <span class="ms-2"><i class="fas fa-reply me-1"></i>' . (int)$row['replies'] . ' replies</span>
                        <span class="ms-2"><i class="fas fa-eye me-1"></i>' . (int)$row['views'] . '</span>
                    </div>
                </div>
                <small class="text-muted text-nowrap">' . $date . '</small>
            </div>
        </li>';
    }
    if (!$threads_html) $threads_html = '<li class="list-group-item text-muted text-center py-3">No threads</li>';

    return '
    <!-- Counts -->
    <div class="d-flex flex-wrap gap-3 mb-4">
        <div class="border rounded-3 px-4 py-3 text-center" style="min-width:120px">
            <div class="fw-bold fs-4 text-primary">' . number_format($post_count) . '</div>
            <small class="text-muted">Posts</small>
        </div>
        <div class="border rounded-3 px-4 py-3 text-center" style="min-width:120px">
            <div class="fw-bold fs-4 text-success">' . number_format($thread_count) . '</div>
            <small class="text-muted">Threads</small>
        </div>
        <div class="border rounded-3 px-4 py-3 text-center" style="min-width:120px">
            <div class="fw-bold fs-4 text-warning">' . number_format($report_count) . '</div>
            <small class="text-muted">Reports Filed</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <h6 class="fw-bold mb-3"><i class="fas fa-comment me-2 text-primary"></i>Last Posts</h6>
            <ul class="list-group list-group-flush border rounded-3" style="max-height:320px;overflow-y:auto">
                ' . $posts_html . '
            </ul>
        </div>
        <div class="col-md-6">
            <h6 class="fw-bold mb-3"><i class="fas fa-file-alt me-2 text-success"></i>Last Threads</h6>
            <ul class="list-group list-group-flush border rounded-3" style="max-height:320px;overflow-y:auto">
                ' . $threads_html . '
            </ul>
        </div>
    </div>
	
	
	  <div class="text-center mt-4">
        <a href="' . $BASEURL . '/search.php?action=finduser&uid=' . $uid . '" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-search me-1"></i>View All Posts
        </a>
        <a href="' . $BASEURL . '/search.php?action=finduserthreads&uid=' . $uid . '" class="btn btn-outline-success btn-sm ms-2">
            <i class="fas fa-list me-1"></i>View All Threads
        </a>
    </div>
	
	
	
	';
}






/**
 * Build chart data for Statistics tab
 */
function buildChartData(int $uid, $db): array
{
    global $db;
	
	// Upload/Download по месяцам из snatched (последние 12 мес)
    $q = $db->sql_query("
        SELECT
            DATE_FORMAT(FROM_UNIXTIME(completedat), '%Y-%m') AS month,
            SUM(downloaded) AS dl,
            SUM(uploaded)   AS ul
        FROM snatched
        WHERE userid = {$uid}
          AND completedat >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 12 MONTH))
          AND finished = 'yes'
        GROUP BY month
        ORDER BY month ASC
    ");

    $months = $dl_data = $ul_data = [];
    while ($row = $db->fetch_array($q)) {
        $months[]  = $row['month'];
        $dl_data[] = round((int)$row['dl'] / 1073741824, 2); // GB
        $ul_data[] = round((int)$row['ul'] / 1073741824, 2);
    }

    // Активность по дням (последние 30 дней из sitelog)
    $q2 = $db->sql_query("
    SELECT
        DATE(FROM_UNIXTIME(completedat)) AS day,
        COUNT(*) AS events
    FROM snatched
    WHERE userid = {$uid}
      AND completedat >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
      AND completedat > 0
    GROUP BY day
    ORDER BY day ASC
");

    $days = $day_data = [];
    while ($row = $db->fetch_array($q2)) {
        $days[]     = $row['day'];
        $day_data[] = (int)$row['events'];
    }

    return compact('months', 'dl_data', 'ul_data', 'days', 'day_data');
}






/**
 * Quick Ban modal
 */
function renderQuickBanModal(bool $is_banned = false): string
{
    global $userdata, $mybb, $db, $memperms;
    $uid       = (int)$userdata['id'];
    $username  = htmlspecialchars_uni($userdata['username']);
    $postCode  = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');
    $h_script  = htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8');


    // Загружаем текущий бан если забанен
    $current_ban = null;
    if ($is_banned) {
        $q = $db->simple_select('banned', '*', "uid='{$uid}'", ['limit' => 1]);
        $current_ban = $db->fetch_array($q) ?: null;
    }

    $ban_times = fetch_ban_times();
    $options   = '';
    foreach ($ban_times as $val => $label) {
        $sel = ($current_ban && $current_ban['bantime'] === $val) || (!$current_ban && $val === '---')
            ? ' selected' : '';
        $options .= "<option value='" . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . "'{$sel}>"
                  . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
    }

    $q = $db->sql_query("SELECT gid, title FROM usergroups WHERE title LIKE '%banned%' OR title LIKE '%Banned%' LIMIT 1");
    $banned_group = $db->fetch_array($q);
    $banned_gid   = $banned_group ? (int)$banned_group['gid'] : 0;

    $modal_title  = $is_banned ? 'Edit Ban: ' : 'Ban User: ';
    $header_class = $is_banned ? 'bg-warning text-dark' : 'bg-danger text-white';
    $btn_class    = $is_banned ? 'btn-warning' : 'btn-danger';
    $btn_label    = $is_banned ? 'Update Ban' : 'Ban User';
    $btn_icon     = $is_banned ? 'fa-edit' : 'fa-ban';
    $current_reason = htmlspecialchars_uni($current_ban['reason'] ?? '');

    return "
    <div class='modal fade' id='quickBanModal' tabindex='-1' aria-hidden='true'>
        <div class='modal-dialog modal-dialog-centered'>
            <div class='modal-content border-0 shadow'>
                <div class='modal-header {$header_class}'>
                    <h5 class='modal-title'>
                        <i class='fas {$btn_icon} me-2'></i>{$modal_title}{$username}
                    </h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                </div>
                <div class='modal-body'>
                    <div class='mb-3'>
                        <label class='form-label fw-semibold'>Ban Duration</label>
                        <select id='banDuration' class='form-select'>
                            {$options}
                        </select>
                    </div>
                    <div class='mb-3'>
                        <label class='form-label fw-semibold'>Reason <span class='text-danger'>*</span></label>
                        <textarea id='banReason' class='form-control' rows='3'
                                  placeholder='Reason for ban...' required>{$current_reason}</textarea>
                        <div id='banReasonError' class='text-danger small mt-1 d-none'>
                            Please provide a reason.
                        </div>
                    </div>
                    <div class='mb-3'>
                        <div class='form-check'>
                            <input class='form-check-input' type='checkbox' id='banSendPM' " . (!$is_banned ? 'checked' : '') . ">
                            <label class='form-check-label' for='banSendPM'>
                                Send ban notification PM to user
                            </label>
                        </div>
                    </div>
                </div>
                <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                        <i class='fas fa-times me-1'></i>Cancel
                    </button>
                    <button type='button' class='btn {$btn_class}' id='quickBanSubmit'
                            onclick=\"quickBan({$uid}, {$banned_gid}, '{$postCode}', '{$h_script}')\">
                        <i class='fas {$btn_icon} me-2'></i>{$btn_label}
                    </button>
                </div>
            </div>
        </div>
    </div>";
}








/**
 * Render Audit Log tab — timeline из modcomment
 */
function renderAuditTab(): string
{
    global $userdata, $usergroups, $mybb;

    $raw = (string)($userdata['modcomment'] ?? '');
    //$isSuperMod = ($usergroups['cansettingspanel'] == '1' || $usergroups['issupermod'] == '1');
    $postCode  = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');
    $h_script  = htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8');
    $uid       = (int)$userdata['id'];

    // ── Парсим строки modcomment ──────────────────────────────────────────────
    // Формат: "2026-05-08 19:52 - Action text by Username"
    //      или "2026-05-08 - Action text by Username" (без времени)
    $lines = array_filter(array_map('trim', explode("\n", (string)($raw ?? ''))));
    $events = [];

    foreach ($lines as $line) {
        // Пробуем с временем: 2026-05-08 19:52 - ...
       if (preg_match('/^(\d{4}-\d{2}-\d{2}(?:\s+\d{2}:\d{2})?)\s+-\s+(.+?)\s+by\s+(.+)$/u', $line, $m)) {
    $events[] = [
        'date'   => $m[1],
        'action' => trim((string)($m[2] ?? '')),
        'by'     => trim((string)($m[3] ?? '')),
    'raw'    => $line,
];
        } else {
            // Нераспознанная строка — показываем как есть
            $events[] = [
    'date'   => '',
    'action' => (string)($line ?? ''),
    'by'     => '',
    'raw'    => (string)($line ?? ''),
];
        }
    }

    // ── Определяем тип события → цвет и иконка ───────────────────────────────
    $classify = function(string $action): array {
        $a = mb_strtolower($action);
        if (preg_match('/warn|ban|restrict|leechwarn|demot/u', $a))
            return ['danger',  'fa-exclamation-triangle', 'Warn / Ban'];
        if (preg_match('/unlock|unbann|lift|unwarn|removed|reset/u', $a))
            return ['success', 'fa-unlock',               'Resolved'];
        if (preg_match('/promot|upgrad/u', $a))
            return ['success', 'fa-level-up-alt',         'Promotion'];
        if (preg_match('/usergroup|group/u', $a))
            return ['primary', 'fa-users',                'Group'];
        if (preg_match('/avatar/u', $a))
            return ['info',    'fa-image',                'Avatar'];
        if (preg_match('/password/u', $a))
            return ['warning', 'fa-key',                  'Password'];
        if (preg_match('/session|login|lockout/u', $a))
            return ['warning', 'fa-desktop',              'Session'];
        if (preg_match('/pm sent|message/u', $a))
            return ['info',    'fa-envelope',             'PM'];
        if (preg_match('/can(upload|download|comment)/u', $a))
            return ['secondary','fa-toggle-off',          'Permission'];
        if (preg_match('/email|username/u', $a))
            return ['primary', 'fa-edit',                 'Profile'];
        return ['secondary', 'fa-circle',                 'Change'];
    };

    // ── Рендер timeline ───────────────────────────────────────────────────────
    if (empty($events)) {
        $timeline = '
        <div class="text-center text-muted py-5">
            <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
            No audit history found.
        </div>';
    } else {
        $items = '';
        foreach ($events as $ev) {
            [$color, $icon, $label] = $classify($ev['action']);
            $date = htmlspecialchars($ev['date'], ENT_QUOTES, 'UTF-8');
            $act  = htmlspecialchars($ev['action'], ENT_QUOTES, 'UTF-8');
            $by   = htmlspecialchars($ev['by'],     ENT_QUOTES, 'UTF-8');

            $items .= "
            <div class='audit-item d-flex gap-3 mb-3'>
                <div class='audit-icon flex-shrink-0 bg-{$color} bg-opacity-10 border border-{$color} rounded-circle d-flex align-items-center justify-content-center' style='width:36px;height:36px'>
                    <i class='fas {$icon} text-{$color} small'></i>
                </div>
                <div class='flex-grow-1 border-bottom pb-3'>
                    <div class='d-flex justify-content-between align-items-start flex-wrap gap-1'>
                        <div>
                            <span class='badge bg-{$color}-subtle text-{$color} border border-{$color} me-2'>{$label}</span>
                            <span class='fw-semibold'>{$act}</span>
                        </div>
                        " . ($date ? "<small class='text-muted text-nowrap'><i class='fas fa-clock me-1'></i>{$date}</small>" : '') . "
                    </div>
                    " . ($by ? "<div class='text-muted small mt-1'><i class='fas fa-user me-1'></i>by <strong>{$by}</strong></div>" : '') . "
                </div>
            </div>";
        }

        $timeline = "
        <div class='audit-timeline'>
            {$items}
        </div>";
    }

    // ── Clear History кнопка (только суперадмин) ──────────────────────────────
    $clearBtn = '';
    
        $clearBtn = "
        <form method='post' action='{$h_script}' class='d-inline'
              onsubmit=\"return confirm('Clear entire audit history? This cannot be undone.')\">
            <input type='hidden' name='action'       value='updateuser'>
            <input type='hidden' name='userid'       value='{$uid}'>
            <input type='hidden' name='my_post_key'  value='{$postCode}'>
            <input type='hidden' name='clear_modcomment' value='1'>
            <button type='submit' class='btn btn-sm btn-outline-danger'>
                <i class='fas fa-trash me-1'></i>Clear History
            </button>
        </form>";
    

    $count = count($events);

    return "
    <div class='d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2'>
        <div>
            <h5 class='fw-bold mb-0'>
                <i class='fas fa-history me-2 text-primary'></i>Audit Log
            </h5>
            <small class='text-muted'>{$count} " . ($count === 1 ? 'entry' : 'entries') . "</small>
        </div>
        <div class='d-flex gap-2 align-items-center flex-wrap'>
            <!-- Filter -->
            <select id='auditFilter' class='form-select form-select-sm' style='width:auto'
                    onchange='filterAudit(this.value)'>
                <option value='all'>All events</option>
                <option value='danger'>Warn / Ban</option>
                <option value='success'>Resolved</option>
                <option value='primary'>Profile / Group</option>
                <option value='warning'>Password / Session</option>
                <option value='info'>Avatar / PM</option>
                <option value='secondary'>Permissions</option>
            </select>
            {$clearBtn}
        </div>
    </div>

    {$timeline}

    <script>
    function filterAudit(type) {
        document.querySelectorAll('.audit-item').forEach(function(item) {
            if (type === 'all') {
                item.style.display = '';
                return;
            }
            var icon = item.querySelector('.audit-icon');
            item.style.display = icon && icon.classList.contains('bg-' + type) ? '' : 'none';
        });
    }
    </script>";
	
	


}









/**
 * Render Torrent Activity tab
 * Sections: Active Peers | Snatch History | Uploaded Torrents
 */
function renderTorrentActivityTab(): string
{
    global $userdata, $db, $BASEURL;

    $uid = (int)$userdata['id'];

    // ── 1. Active peers ───────────────────────────────────────────────────────
    $q = $db->sql_query("
        SELECT p.torrent, p.uploaded, p.downloaded, p.to_go,
               p.seeder, p.last_action, p.agent, p.ip, p.connectable,
               t.name, t.size, t.id AS tid
        FROM peers p
        LEFT JOIN torrents t ON t.id = p.torrent
        WHERE p.userid = {$uid}
        ORDER BY p.last_action DESC
        LIMIT 50
    ");

    $peer_rows = '';
    $peer_count = 0;
    while ($row = $db->fetch_array($q)) {
        $peer_count++;
        $name      = htmlspecialchars($row['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $tid       = (int)$row['tid'];
        $seeder    = $row['seeder'] === 'yes';
        $statusBadge = $seeder
            ? "<span class='badge bg-success'><i class='fas fa-arrow-up me-1'></i>Seeding</span>"
            : "<span class='badge bg-warning text-dark'><i class='fas fa-arrow-down me-1'></i>Leeching</span>";

        $ul        = mksize($row['uploaded']);
        $dl        = mksize($row['downloaded']);
        $to_go     = mksize($row['to_go']);
        $progress  = $row['size'] > 0
            ? round((1 - $row['to_go'] / max($row['size'], 1)) * 100)
            : ($seeder ? 100 : 0);
        $progress  = max(0, min(100, $progress));

        $last      = $row['last_action'] ? my_datee('relative', $row['last_action']) : '—';
        $agent     = htmlspecialchars($row['agent'] ?? '', ENT_QUOTES, 'UTF-8');
        $conn      = $row['connectable'] === 'yes'
            ? "<i class='fas fa-plug text-success' title='Connectable'></i>"
            : "<i class='fas fa-plug text-danger' title='Not connectable'></i>";

        $progressColor = $progress >= 100 ? 'success' : ($progress >= 50 ? 'info' : 'warning');

        $peer_rows .= "
        <tr class='peer-row'>
            <td class='align-middle'>
                <div class='d-flex flex-column'>
                    <a href='{$BASEURL}/torrents.php?id={$tid}' class='text-decoration-none fw-semibold torrent-link' title='{$name}'>
                        <i class='fas fa-torrent text-primary me-1 fs-10'></i>
                        <span class='text-truncate d-inline-block' style='max-width: 280px'>{$name}</span>
                    </a>
                    <small class='text-muted mt-1'>
                        <i class='fas fa-microchip me-1'></i>{$agent}
                    </small>
                </div>
            </td>
            <td class='align-middle text-center'>{$statusBadge}</td>
            <td class='align-middle' style='min-width: 120px'>
                <div class='d-flex flex-column'>
                    <div class='progress' style='height: 6px;'>
                        <div class='progress-bar bg-{$progressColor}' style='width: {$progress}%'></div>
                    </div>
                    <small class='text-muted text-center mt-1'>{$progress}%</small>
                </div>
            </td>
            <td class='align-middle text-end'><span class='text-success fw-semibold'>{$ul}</span></td>
            <td class='align-middle text-end'><span class='text-danger fw-semibold'>{$dl}</span></td>
            <td class='align-middle text-center'><span class='badge bg-secondary-subtle text-secondary'>{$to_go}</span></td>
            <td class='align-middle text-center'><small class='text-muted'><i class='far fa-clock me-1'></i>{$last}</small></td>
            <td class='align-middle text-center'>{$conn}</td>
        </tr>";
    }

    if ($peer_count === 0) {
        $peers_html = "
        <div class='empty-state text-center py-5'>
            <div class='empty-state-icon mb-3'>
                <i class='fas fa-satellite-dish fa-4x text-muted opacity-25'></i>
            </div>
            <h6 class='text-muted'>No Active Connections</h6>
            <p class='text-muted small'>No active torrents found for this user.</p>
        </div>";
    } else {
        $peers_html = "
        <div class='table-responsive'>
            <table class='table table-hover'>
                <thead class='table-light'>
                    <tr>
                        <th><i class='fas fa-torrent me-2'></i>Torrent</th>
                        <th class='text-center'><i class='fas fa-chart-simple me-2'></i>Status</th>
                        <th><i class='fas fa-chart-line me-2'></i>Progress</th>
                        <th class='text-end'><i class='fas fa-arrow-up me-2'></i>Uploaded</th>
                        <th class='text-end'><i class='fas fa-arrow-down me-2'></i>Downloaded</th>
                        <th class='text-center'><i class='fas fa-hourglass-half me-2'></i>Remaining</th>
                        <th class='text-center'><i class='far fa-clock me-2'></i>Last Seen</th>
                        <th class='text-center'><i class='fas fa-plug me-2'></i>Conn</th>
                    </tr>
                </thead>
                <tbody>{$peer_rows}</tbody>
            </table>
        </div>";
    }

    // ── 2. Snatch history ─────────────────────────────────────────────────────
    $q2 = $db->sql_query("
        SELECT s.torrentid, s.uploaded, s.downloaded, s.seeder,
               s.last_action, s.startdat, s.completedat,
               s.seedtime, s.leechtime, s.finished, s.agent,
               s.downspeed, s.upspeed,
               t.name, t.size, t.id AS tid
        FROM snatched s
        LEFT JOIN torrents t ON t.id = s.torrentid
        WHERE s.userid = {$uid}
        ORDER BY s.last_action DESC
        LIMIT 100
    ");

    $snatch_rows = '';
    $snatch_count = 0;
    $total_ul = 0;
    $total_dl = 0;

    while ($row = $db->fetch_array($q2)) {
        $snatch_count++;
        $total_ul += (int)$row['uploaded'];
        $total_dl += (int)$row['downloaded'];

        $name     = htmlspecialchars($row['name'] ?? 'Deleted torrent', ENT_QUOTES, 'UTF-8');
        $tid      = (int)$row['tid'];
        $finished = $row['finished'] === 'yes';

        $finishedBadge = $finished
            ? "<span class='badge bg-success-subtle text-success border border-success'><i class='fas fa-check-circle me-1'></i>Complete</span>"
            : "<span class='badge bg-secondary-subtle text-secondary border border-secondary'><i class='fas fa-hourglass-half me-1'></i>Incomplete</span>";

        $seederBadge = $row['seeder'] === 'yes'
            ? "<span class='badge bg-primary-subtle text-primary border border-primary ms-1'><i class='fas fa-arrow-up me-1'></i>Seeding</span>"
            : '';

        $ul       = mksize($row['uploaded']);
        $dl       = mksize($row['downloaded']);
        $seedtime = $row['seedtime'] ? mkprettytime((int)$row['seedtime']) : '—';
        $started  = $row['startdat']    ? my_datee('relative', $row['startdat'])    : '—';
        $completed = $row['completedat'] ? my_datee('relative', $row['completedat']) : '—';
        $last     = $row['last_action'] ? my_datee('relative', $row['last_action']) : '—';
        $agent    = htmlspecialchars($row['agent'] ?? '', ENT_QUOTES, 'UTF-8');
        
        $ratio = $row['downloaded'] > 0 
            ? round($row['uploaded'] / $row['downloaded'], 2) 
            : ($row['uploaded'] > 0 ? '∞' : '—');

        $snatch_rows .= "
        <tr class='snatch-row'>
            <td class='align-middle'>
                <div class='d-flex flex-column'>
                    <a href='{$BASEURL}/torrents.php?id={$tid}' class='text-decoration-none fw-semibold torrent-link' title='{$name}'>
                        <i class='fas fa-download text-success me-1 fs-10'></i>
                        <span class='text-truncate d-inline-block' style='max-width: 260px'>{$name}</span>
                    </a>
                    <small class='text-muted mt-1'>
                        <i class='fas fa-microchip me-1'></i>{$agent}
                    </small>
                </div>
            </td>
            <td class='align-middle'>{$finishedBadge}{$seederBadge}</td>
            <td class='align-middle text-end fw-semibold text-success'>{$ul}</td>
            <td class='align-middle text-end fw-semibold text-danger'>{$dl}</td>
            <td class='align-middle text-center'><span class='badge bg-info-subtle text-info border border-info'><i class='fas fa-chart-line me-1'></i>{$ratio}</span></td>
            <td class='align-middle text-center'><small class='text-muted'><i class='fas fa-clock me-1'></i>{$seedtime}</small></td>
            <td class='align-middle text-center'><small class='text-muted'>{$started}</small></td>
            <td class='align-middle text-center'><small class='text-muted'>{$completed}</small></td>
            <td class='align-middle text-center'><small class='text-muted'><i class='far fa-clock me-1'></i>{$last}</small></td>
        </tr>";
    }

    if ($snatch_count === 0) {
        $snatch_html = "
        <div class='empty-state text-center py-5'>
            <div class='empty-state-icon mb-3'>
                <i class='fas fa-history fa-4x text-muted opacity-25'></i>
            </div>
            <h6 class='text-muted'>No Snatch History</h6>
            <p class='text-muted small'>This user hasn't downloaded any torrents yet.</p>
        </div>";
    } else {
        $summary = "
        <div class='stats-summary d-flex flex-wrap gap-3 mb-4 p-3 bg-light rounded-4'>
            <div class='stat-item'>
                <i class='fas fa-list text-primary me-2'></i>
                <strong>{$snatch_count}</strong> <span class='text-muted'>snatches</span>
            </div>
            <div class='stat-item'>
                <i class='fas fa-arrow-up text-success me-2'></i>
                <strong>" . mksize($total_ul) . "</strong> <span class='text-muted'>uploaded</span>
            </div>
            <div class='stat-item'>
                <i class='fas fa-arrow-down text-danger me-2'></i>
                <strong>" . mksize($total_dl) . "</strong> <span class='text-muted'>downloaded</span>
            </div>
            <div class='stat-item'>
                <i class='fas fa-chart-line text-info me-2'></i>
                <strong>" . ($total_dl > 0 ? round($total_ul / $total_dl, 2) : '∞') . "</strong> <span class='text-muted'>total ratio</span>
            </div>
        </div>";

        $snatch_html = $summary . "
        <div class='table-responsive'>
            <table class='table table-hover'>
                <thead class='table-light'>
                    <tr>
                        <th><i class='fas fa-torrent me-2'></i>Torrent</th>
                        <th><i class='fas fa-flag-checkered me-2'></i>Status</th>
                        <th class='text-end'><i class='fas fa-arrow-up me-2'></i>Uploaded</th>
                        <th class='text-end'><i class='fas fa-arrow-down me-2'></i>Downloaded</th>
                        <th class='text-center'><i class='fas fa-chart-simple me-2'></i>Ratio</th>
                        <th class='text-center'><i class='fas fa-clock me-2'></i>Seed Time</th>
                        <th class='text-center'><i class='fas fa-play me-2'></i>Started</th>
                        <th class='text-center'><i class='fas fa-check me-2'></i>Completed</th>
                        <th class='text-center'><i class='far fa-clock me-2'></i>Last Active</th>
                    </tr>
                </thead>
                <tbody>{$snatch_rows}</tbody>
            </table>
        </div>";
    }

    // ── 3. Uploaded torrents ──────────────────────────────────────────────────
    $q3 = $db->sql_query("
        SELECT id, name, size, added, seeders, leechers,
               times_completed, visible, banned, free, isnuked
        FROM torrents
        WHERE owner = {$uid}
        ORDER BY added DESC
        LIMIT 100
    ");

    $upload_rows = '';
    $upload_count = 0;

    while ($row = $db->fetch_array($q3)) {
        $upload_count++;
        $name    = htmlspecialchars($row['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $tid     = (int)$row['id'];
        $size    = mksize($row['size']);
        $added   = $row['added'] ? my_datee('relative', $row['added']) : '—';
        $seeders  = (int)$row['seeders'];
        $leechers = (int)$row['leechers'];
        $grabs    = (int)$row['times_completed'];

        $visibleBadge = $row['visible'] === 'yes'
            ? "<span class='badge bg-success-subtle text-success border border-success'><i class='fas fa-eye me-1'></i>Visible</span>"
            : "<span class='badge bg-secondary-subtle text-secondary border border-secondary'><i class='fas fa-eye-slash me-1'></i>Hidden</span>";

        $nukedBadge = $row['isnuked'] === 'yes'
            ? "<span class='badge bg-danger-subtle text-danger border border-danger ms-1'><i class='fas fa-skull me-1'></i>Nuked</span>"
            : '';

        $freeBadge = $row['free'] === 'yes'
            ? "<span class='badge bg-info-subtle text-info border border-info ms-1'><i class='fas fa-gift me-1'></i>Free</span>"
            : '';

        $bannedBadge = $row['banned'] === 'yes'
            ? "<span class='badge bg-dark-subtle text-dark border border-dark ms-1'><i class='fas fa-ban me-1'></i>Banned</span>"
            : '';

        $upload_rows .= "
        <tr class='upload-row'>
            <td class='align-middle'>
                <a href='{$BASEURL}/torrents.php?id={$tid}' class='text-decoration-none fw-semibold torrent-link' title='{$name}'>
                    <i class='fas fa-upload text-primary me-1 fs-10'></i>
                    <span class='text-truncate d-inline-block' style='max-width: 280px'>{$name}</span>
                </a>
            </td>
            <td class='align-middle text-center'><span class='badge bg-secondary-subtle text-secondary border border-secondary'>{$size}</span></td>
            <td class='align-middle'>{$visibleBadge}{$nukedBadge}{$freeBadge}{$bannedBadge}</td>
            <td class='align-middle text-center'>
                <span class='text-success me-2'><i class='fas fa-arrow-up'></i> {$seeders}</span>
                <span class='text-danger'><i class='fas fa-arrow-down'></i> {$leechers}</span>
            </td>
            <td class='align-middle text-center'><span class='badge bg-primary-subtle text-primary border border-primary'><i class='fas fa-download me-1'></i>{$grabs}</span></td>
            <td class='align-middle text-center'><small class='text-muted'><i class='far fa-calendar-alt me-1'></i>{$added}</small></td>
        </tr>";
    }

    if ($upload_count === 0) {
        $uploads_html = "
        <div class='empty-state text-center py-5'>
            <div class='empty-state-icon mb-3'>
                <i class='fas fa-upload fa-4x text-muted opacity-25'></i>
            </div>
            <h6 class='text-muted'>No Uploads</h6>
            <p class='text-muted small'>This user hasn't uploaded any torrents yet.</p>
        </div>";
    } else {
        $uploads_html = "
        <div class='table-responsive'>
            <table class='table'>
                <thead class='table-light'>
                    <tr>
                        <th><i class='fas fa-torrent me-2'></i>Torrent</th>
                        <th class='text-center'><i class='fas fa-database me-2'></i>Size</th>
                        <th><i class='fas fa-flag me-2'></i>Status</th>
                        <th class='text-center'><i class='fas fa-users me-2'></i>Peers</th>
                        <th class='text-center'><i class='fas fa-download me-2'></i>Grabs</th>
                        <th class='text-center'><i class='far fa-calendar-alt me-2'></i>Added</th>
                    </tr>
                </thead>
                <tbody>{$upload_rows}</tbody>
            </table>
        </div>";
    }

    // ── Styles ────────────────────────────────────────────────────────────────
    return "

    <div class='torrent-tabs'>
        <ul class='nav nav-pills mb-4 gap-2' id='torrentActivityTabs' role='tablist'>
            <li class='nav-item' role='presentation'>
                <button class='nav-link active' data-bs-toggle='pill' data-bs-target='#ta-peers' type='button'>
                    <i class='fas fa-satellite-dish me-2'></i>Active
                    <span class='badge bg-white text-primary ms-2 rounded-pill'>{$peer_count}</span>
                </button>
            </li>
            <li class='nav-item' role='presentation'>
                <button class='nav-link' data-bs-toggle='pill' data-bs-target='#ta-snatched' type='button'>
                    <i class='fas fa-history me-2'></i>Snatched
                    <span class='badge bg-white text-secondary ms-2 rounded-pill'>{$snatch_count}</span>
                </button>
            </li>
            <li class='nav-item' role='presentation'>
                <button class='nav-link' data-bs-toggle='pill' data-bs-target='#ta-uploads' type='button'>
                    <i class='fas fa-upload me-2'></i>Uploads
                    <span class='badge bg-white text-secondary ms-2 rounded-pill'>{$upload_count}</span>
                </button>
            </li>
        </ul>

        <div class='tab-content'>
            <div class='tab-pane fade show active' id='ta-peers' role='tabpanel'>
                {$peers_html}
            </div>
            <div class='tab-pane fade' id='ta-snatched' role='tabpanel'>
                {$snatch_html}
            </div>
            <div class='tab-pane fade' id='ta-uploads' role='tabpanel'>
                {$uploads_html}
            </div>
        </div>
    </div>";
}










/**
 * Render Reports tab
 * Shows: reports filed AGAINST this user | reports filed BY this user
 */
function renderReportsTab(): string
{
    global $userdata, $db, $BASEURL;

    $uid = (int)$userdata['id'];

    // ── Единый JOIN-запрос для отчётов ПРОТИВ пользователя ───────────────────
    $q1 = $db->sql_query("
        SELECT r.*,
               u_by.username    AS reporter_username,
               u_rep.username   AS reported_username,
               u_dealt.username AS dealt_username,
               t.name           AS torrent_name
        FROM reports r
        LEFT JOIN users    u_by    ON u_by.id    = r.addedby
        LEFT JOIN users    u_rep   ON u_rep.id   = r.reported_user_id
        LEFT JOIN users    u_dealt ON u_dealt.id = r.dealtby
        LEFT JOIN torrents t       ON t.id        = r.reported_id AND r.type = 'torrent'
        WHERE r.reported_user_id = {$uid}
        ORDER BY r.added DESC
        LIMIT 200
    ");

    // ── Единый JOIN-запрос для отчётов ОТ пользователя ────────────────────────
    $q2 = $db->sql_query("
        SELECT r.*,
               u_by.username    AS reporter_username,
               u_rep.username   AS reported_username,
               u_dealt.username AS dealt_username,
               t.name           AS torrent_name
        FROM reports r
        LEFT JOIN users    u_by    ON u_by.id    = r.addedby
        LEFT JOIN users    u_rep   ON u_rep.id   = r.reported_user_id
        LEFT JOIN users    u_dealt ON u_dealt.id = r.dealtby
        LEFT JOIN torrents t       ON t.id        = r.reported_id AND r.type = 'torrent'
        WHERE r.addedby = {$uid}
        ORDER BY r.added DESC
        LIMIT 200
    ");

    // ── Построение одной строки таблицы (все данные уже в $row из JOIN) ───────
    $buildRow = function(array $row, bool $isIncoming) use ($BASEURL): string {

        [$typeColor, $typeIcon] = match($row['type']) {
            'torrent'   => ['primary',   'fa-magnet'],
            'user'      => ['warning',   'fa-user'],
            'comment'   => ['info',      'fa-comment'],
            'forumpost' => ['secondary', 'fa-comments'],
            default     => ['secondary', 'fa-flag'],
        };
        $typeBadge = "<span class='badge bg-{$typeColor}-subtle text-{$typeColor} border border-{$typeColor}'>
            <i class='fas {$typeIcon} me-1'></i>" . ucfirst($row['type']) . "</span>";

        $statusBadge = $row['dealtwith']
            ? "<span class='badge bg-success-subtle text-success border border-success'><i class='fas fa-check me-1'></i>Resolved</span>"
            : "<span class='badge bg-danger-subtle text-danger border border-danger'><i class='fas fa-clock me-1'></i>Open</span>";

        // Subject — данные уже в JOIN, дополнительных запросов не нужно
        $subject = '—';
        if ($row['type'] === 'torrent' && $row['reported_id']) {
            $tid    = (int)$row['reported_id'];
            $tname  = htmlspecialchars($row['torrent_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $subject = $tname
                ? "<a href='{$BASEURL}/torrents.php?id={$tid}' class='text-decoration-none'>{$tname}</a>"
                : "Torrent #{$tid}";
        } elseif (in_array($row['type'], ['user', 'comment']) && $row['reported_user_id']) {
            $ruid  = (int)$row['reported_user_id'];
            $rname = htmlspecialchars($row['reported_username'] ?? '', ENT_QUOTES, 'UTF-8');
            if ($rname) {
                $subject = "<a href='{$BASEURL}/profile.php?id={$ruid}' class='text-decoration-none'>{$rname}</a>";
            }
        } elseif ($row['type'] === 'forumpost' && $row['thread_id']) {
            $tid2    = (int)$row['thread_id'];
            $subject = "<a href='{$BASEURL}/showthread.php?tid={$tid2}' class='text-decoration-none'>Thread #{$tid2}</a>";
        }

        // Who column
        if ($isIncoming) {
            $byUid  = (int)$row['addedby'];
            $byName = htmlspecialchars($row['reporter_username'] ?? '', ENT_QUOTES, 'UTF-8');
            $whoCol = $byUid && $byName
                ? "<a href='{$BASEURL}/profile.php?id={$byUid}' class='text-decoration-none'>{$byName}</a>"
                : '—';
        } else {
            $targetUid  = (int)$row['reported_user_id'];
            $targetName = htmlspecialchars($row['reported_username'] ?? '', ENT_QUOTES, 'UTF-8');
            $whoCol = $targetUid && $targetName
                ? "<a href='{$BASEURL}/profile.php?id={$targetUid}' class='text-decoration-none'>{$targetName}</a>"
                : '—';
        }

        $dealtBy = $row['dealtwith'] && !empty($row['dealt_username'])
            ? htmlspecialchars($row['dealt_username'], ENT_QUOTES, 'UTF-8')
            : '—';

        $reason      = htmlspecialchars($row['reason']        ?? '', ENT_QUOTES, 'UTF-8');
        $rule        = htmlspecialchars($row['rule_violation'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(mb_strimwidth((string)($row['description'] ?? ''), 0, 120, '…'), ENT_QUOTES, 'UTF-8');
        $date        = $row['added']      ? my_datee('relative', $row['added'])      : '—';
        $updated     = $row['updated_at'] ? my_datee('relative', $row['updated_at']) : '—';

        $ruleBadge = $rule
            ? "<span class='badge bg-light text-dark border ms-1' title='Rule violation'>{$rule}</span>"
            : '';

        return "
        <tr>
            <td>{$typeBadge}{$ruleBadge}</td>
            <td>{$subject}</td>
            <td>
                <div class='fw-semibold'>{$reason}</div>
                " . ($description ? "<small class='text-muted'>{$description}</small>" : '') . "
            </td>
            <td>{$whoCol}</td>
            <td>{$statusBadge}</td>
            <td><small class='text-muted'>{$dealtBy}</small></td>
            <td><small class='text-muted'>{$date}</small></td>
            <td><small class='text-muted'>{$updated}</small></td>
        </tr>";
    };

    $buildTable = function(string $rows, bool $isIncoming): string {
        $whoHeader = $isIncoming ? 'Reported By' : 'Reported User';
        if (!$rows) {
            $icon = $isIncoming ? 'fa-shield-alt' : 'fa-flag';
            $msg  = $isIncoming ? 'No reports filed against this user.' : 'This user has not filed any reports.';
            return "
            <div class='text-center text-muted py-5'>
                <i class='fas {$icon} fa-2x mb-2 d-block opacity-25'></i>
                {$msg}
            </div>";
        }
        return "
        <div class='table-responsive'>
            <table class='table table-hover table-sm align-middle mb-0'>
                <thead class='table-light'>
                    <tr>
                        <th>Type</th><th>Subject</th><th>Reason / Description</th>
                        <th>{$whoHeader}</th><th>Status</th><th>Dealt By</th>
                        <th>Reported</th><th>Updated</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>";
    };

    // ── Обход результатов ─────────────────────────────────────────────────────
    $incoming_rows  = '';
    $incoming_open  = 0;
    $incoming_total = 0;
    while ($row = $db->fetch_array($q1)) {
        $incoming_total++;
        if (!$row['dealtwith']) $incoming_open++;
        $incoming_rows .= $buildRow($row, true);
    }

    $outgoing_rows  = '';
    $outgoing_open  = 0;
    $outgoing_total = 0;
    while ($row = $db->fetch_array($q2)) {
        $outgoing_total++;
        if (!$row['dealtwith']) $outgoing_open++;
        $outgoing_rows .= $buildRow($row, false);
    }

    $incomingOpenBadge = $incoming_open
        ? "<span class='badge bg-danger ms-1'>{$incoming_open} open</span>" : '';
    $outgoingOpenBadge = $outgoing_open
        ? "<span class='badge bg-warning text-dark ms-1'>{$outgoing_open} open</span>" : '';

    $incomingTab = $buildTable($incoming_rows, true);
    $outgoingTab = $buildTable($outgoing_rows, false);

    return "
    <div class='d-flex align-items-center mb-4'>
        <i class='fas fa-flag fa-lg text-danger me-2'></i>
        <h5 class='fw-bold mb-0'>Reports</h5>
    </div>

    <ul class='nav nav-pills mb-4 gap-2' id='reportsSubTabs' role='tablist'>
        <li class='nav-item' role='presentation'>
            <button class='nav-link active' data-bs-toggle='pill' data-bs-target='#rep-incoming' type='button'>
                <i class='fas fa-shield-alt me-1'></i>Against This User
                <span class='badge bg-secondary ms-1'>{$incoming_total}</span>
                {$incomingOpenBadge}
            </button>
        </li>
        <li class='nav-item' role='presentation'>
            <button class='nav-link' data-bs-toggle='pill' data-bs-target='#rep-outgoing' type='button'>
                <i class='fas fa-flag me-1'></i>Filed By This User
                <span class='badge bg-secondary ms-1'>{$outgoing_total}</span>
                {$outgoingOpenBadge}
            </button>
        </li>
    </ul>

    <div class='tab-content'>
        <div class='tab-pane fade show active' id='rep-incoming' role='tabpanel'>{$incomingTab}</div>
        <div class='tab-pane fade'            id='rep-outgoing' role='tabpanel'>{$outgoingTab}</div>
    </div>";
}







function buildInviteTree(int $rootUid, $db, string $BASEURL): string
{
    // Максимальная глубина дерева
    $MAX_DEPTH  = 3;
    // Максимум детей на уровень (защита от взрыва)
    $LIMIT_EACH = 50;

    // Стартуем с корневого пользователя
    $currentLevelIds = [$rootUid];

    // Сохраняем строки по parent_id → [children html]
    // и все данные по uid → row  для рекурсивной сборки
    $childrenOf = []; // [parent_uid => [row, ...]]

    for ($depth = 0; $depth < $MAX_DEPTH; $depth++) {
        if (empty($currentLevelIds)) break;

        $ids_sql = implode(',', array_map('intval', $currentLevelIds));

        $q = $db->sql_query("
            SELECT u.id, u.username, u.enabled, u.warned, u.donor,
                   u.uploaded, u.downloaded, u.added,
                   i.inviter_id,
                   i.status AS invite_status
            FROM invites i
            JOIN users u ON u.id = i.invitee_id
            WHERE i.inviter_id IN ({$ids_sql})
              AND i.status = 'used'
            ORDER BY i.inviter_id, u.added ASC
            LIMIT " . ($LIMIT_EACH * count($currentLevelIds)) . "
        ");

        $nextLevelIds = [];
        while ($row = $db->fetch_array($q)) {
            $pid = (int)$row['inviter_id'];
            $childrenOf[$pid][] = $row;
            $nextLevelIds[] = (int)$row['id'];
        }

        $currentLevelIds = array_unique($nextLevelIds);
    }

    // Рекурсивная сборка HTML из уже загруженных данных (без запросов)
    $renderNode = null;
    $renderNode = function(int $parentUid, int $depth) use (&$renderNode, &$childrenOf, $BASEURL): string {
        if ($depth > 3 || empty($childrenOf[$parentUid])) return '';

        $html = '';
        foreach ($childrenOf[$parentUid] as $row) {
            $iid   = (int)$row['id'];
            $iname = htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8');

            $badges = '';
            if ($row['enabled'] === 'no')  $badges .= "<span class='badge bg-danger ms-1'    style='font-size:0.7em'>Banned</span>";
            if ($row['warned']  === 'yes') $badges .= "<span class='badge bg-warning text-dark ms-1' style='font-size:0.7em'>Warned</span>";
            if ($row['donor']   === 'yes') $badges .= "<span class='badge bg-info ms-1'       style='font-size:0.7em'>Donor</span>";

            $ul     = mksize($row['uploaded']  ?? 0);
            $dl     = mksize($row['downloaded'] ?? 0);
            $joined = $row['added'] ? my_datee('relative', $row['added']) : '—';

            $children    = $renderNode($iid, $depth + 1);
            $hasChildren = $children !== '';
            $toggleId    = "tree-node-{$iid}";

            $indent   = $depth * 24;
            $connLine = $depth > 0
                ? "<span style='display:inline-block;width:{$indent}px;border-left:2px solid #dee2e6;border-bottom:2px solid #dee2e6;height:16px;vertical-align:bottom;margin-right:4px;'></span>"
                : '';

            $toggle = $hasChildren
                ? "<button class='btn btn-link btn-sm p-0 me-1 text-muted' onclick=\"var el=document.getElementById('{$toggleId}');el.style.display=el.style.display==='none'?'block':'none'\">
                       <i class='fas fa-chevron-down' style='font-size:0.75em'></i>
                   </button>"
                : "<span style='display:inline-block;width:22px'></span>";

            $html .= "
            <div class='d-flex align-items-center py-1 border-bottom'>
                <div style='padding-left:{$indent}px' class='d-flex align-items-center flex-grow-1'>
                    {$toggle}
                    <i class='fas fa-user-circle text-secondary me-2'></i>
                    <a href='{$BASEURL}/profile.php?id={$iid}' class='text-decoration-none fw-semibold me-1'>{$iname}</a>
                    {$badges}
                    <small class='text-muted ms-3'>Joined: {$joined}</small>
                    <small class='text-success ms-3'><i class='fas fa-upload'></i> {$ul}</small>
                    <small class='text-danger ms-2'><i class='fas fa-download'></i> {$dl}</small>
                </div>
            </div>
            " . ($hasChildren ? "<div id='{$toggleId}'>{$children}</div>" : '');
        }

        return $html;
    };

    return $renderNode($rootUid, 0);
}



/**
 * Render Invites tab
 * Sections: Summary stats | Sent invites | Invite tree | Who invited this user
 */
function renderInvitesTab(): string
{
    global $userdata, $db, $BASEURL;

    $uid = (int)$userdata['id'];

    // ── Stats ─────────────────────────────────────────────────────────────────
    $q_stats = $db->sql_query("
        SELECT
            COUNT(*)                                          AS total,
            SUM(status = 'used')                              AS used,
            SUM(status = 'pending')                           AS pending,
            SUM(status = 'expired')                           AS expired,
            SUM(status = 'revoked')                           AS revoked
        FROM invites
        WHERE inviter_id = {$uid}
    ");
    $stats = $db->fetch_array($q_stats) ?: [];
    $total   = (int)($stats['total']   ?? 0);
    $used    = (int)($stats['used']    ?? 0);
    $pending = (int)($stats['pending'] ?? 0);
    $expired = (int)($stats['expired'] ?? 0);
    $revoked = (int)($stats['revoked'] ?? 0);

    $statsBar = "
    <div class='row g-3 mb-4'>
        <div class='col-6 col-md-3'>
            <div class='rounded-3 bg-light border text-center py-3 px-2'>
                <div class='fs-4 fw-bold text-primary'>{$total}</div>
                <small class='text-muted'>Total Sent</small>
            </div>
        </div>
        <div class='col-6 col-md-3'>
            <div class='rounded-3 bg-success-subtle border border-success text-center py-3 px-2'>
                <div class='fs-4 fw-bold text-success'>{$used}</div>
                <small class='text-muted'>Used</small>
            </div>
        </div>
        <div class='col-6 col-md-3'>
            <div class='rounded-3 bg-warning-subtle border border-warning text-center py-3 px-2'>
                <div class='fs-4 fw-bold text-warning'>{$pending}</div>
                <small class='text-muted'>Pending</small>
            </div>
        </div>
        <div class='col-6 col-md-3'>
            <div class='rounded-3 bg-secondary-subtle border text-center py-3 px-2'>
                <div class='fs-4 fw-bold text-secondary'>" . ($expired + $revoked) . "</div>
                <small class='text-muted'>Expired / Revoked</small>
            </div>
        </div>
    </div>
    <div class='mb-1 text-muted small'>
        <i class='fas fa-ticket-alt me-1'></i>Available invites in balance:
        <strong class='text-dark'>" . (int)($userdata['invites'] ?? 0) . "</strong>
    </div>";

    // ── Sent invites table ────────────────────────────────────────────────────
    $q_sent = $db->sql_query("
        SELECT i.*,
               u.username   AS invitee_name,
               u.enabled    AS invitee_enabled,
               u.usergroup  AS invitee_group,
               u.added      AS invitee_joined,
               u.uploaded   AS invitee_ul,
               u.downloaded AS invitee_dl,
               u.warned     AS invitee_warned,
               u.donor      AS invitee_donor
        FROM invites i
        LEFT JOIN users u ON u.id = i.invitee_id
        WHERE i.inviter_id = {$uid}
        ORDER BY i.created_at DESC
        LIMIT 200
    ");

    $sent_rows = '';
    $sent_count = 0;
    while ($row = $db->fetch_array($q_sent)) {
        $sent_count++;

        // Status badge
        [$sc, $si, $sl] = match($row['status']) {
            'used'    => ['success', 'fa-check',      'Used'],
            'pending' => ['warning', 'fa-clock',      'Pending'],
            'expired' => ['secondary','fa-hourglass-end','Expired'],
            'revoked' => ['danger',  'fa-ban',        'Revoked'],
            default   => ['secondary','fa-question',  $row['status']],
        };
        $statusBadge = "<span class='badge bg-{$sc}-subtle text-{$sc} border border-{$sc}'>
            <i class='fas {$si} me-1'></i>{$sl}</span>";

        // Invitee info
        if ($row['invitee_id'] && $row['invitee_name']) {
            $iname  = htmlspecialchars($row['invitee_name'], ENT_QUOTES, 'UTF-8');
            $iid    = (int)$row['invitee_id'];
            $ilink  = "<a href='{$BASEURL}/profile.php?id={$iid}' class='text-decoration-none fw-semibold'>{$iname}</a>";

            $iBadges = '';
            if ($row['invitee_warned']  === 'yes') $iBadges .= "<span class='badge bg-warning text-dark ms-1'>Warned</span>";
            if ($row['invitee_donor']   === 'yes') $iBadges .= "<span class='badge bg-info ms-1'>Donor</span>";
            if ($row['invitee_enabled'] === 'no')  $iBadges .= "<span class='badge bg-danger ms-1'>Banned</span>";

            $iJoined = $row['invitee_joined'] ? my_datee('relative', $row['invitee_joined']) : '—';
            $iUl     = mksize($row['invitee_ul']  ?? 0);
            $iDl     = mksize($row['invitee_dl']  ?? 0);

            $inviteeCol = "
                {$ilink}{$iBadges}
                <div class='text-muted small mt-1'>
                    Joined: {$iJoined} &nbsp;·&nbsp;
                    <span class='text-success'><i class='fas fa-upload'></i> {$iUl}</span> &nbsp;
                    <span class='text-danger'><i class='fas fa-download'></i> {$iDl}</span>
                </div>";
        } elseif ($row['email']) {
            $email = htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8');
            $inviteeCol = "<span class='text-muted'><i class='fas fa-envelope me-1'></i>{$email}</span>
                <div class='text-muted small'>Not registered yet</div>";
        } else {
            $inviteeCol = "<span class='text-muted'>—</span>";
        }

        $note       = htmlspecialchars($row['note'] ?? '', ENT_QUOTES, 'UTF-8');
        $created    = $row['created_at'] ? my_datee('relative', $row['created_at']) : '—';
        $used_at    = $row['used_at']    ? my_datee('relative', $row['used_at'])    : '—';
        $expires_at = $row['expires_at'] ? my_datee('relative', $row['expires_at']) : '—';
        $ip_created = htmlspecialchars($row['ip_created'] ?? '', ENT_QUOTES, 'UTF-8');
        $ip_used    = htmlspecialchars($row['ip_used']    ?? '', ENT_QUOTES, 'UTF-8');

        $ipCol = '';
        if ($ip_created) $ipCol .= "<small class='text-muted d-block'><i class='fas fa-paper-plane me-1'></i>{$ip_created}</small>";
        if ($ip_used)    $ipCol .= "<small class='text-muted d-block'><i class='fas fa-sign-in-alt me-1'></i>{$ip_used}</small>";
        if (!$ipCol)     $ipCol  = '<small class="text-muted">—</small>';

        $sent_rows .= "
        <tr>
            <td>{$inviteeCol}</td>
            <td>{$statusBadge}</td>
            <td><small class='text-muted'>{$created}</small></td>
            <td><small class='text-muted'>{$used_at}</small></td>
            <td><small class='text-muted'>{$expires_at}</small></td>
            <td>{$ipCol}</td>
            " . ($note ? "<td><small class='text-muted'>{$note}</small></td>" : "<td>—</td>") . "
        </tr>";
    }

    if (!$sent_rows) {
        $sentTable = "
        <div class='text-center text-muted py-5'>
            <i class='fas fa-ticket-alt fa-2x mb-2 d-block opacity-25'></i>
            No invites sent yet.
        </div>";
    } else {
        $sentTable = "
        <div class='table-responsive'>
            <table class='table table-hover table-sm align-middle mb-0'>
                <thead class='table-light'>
                    <tr>
                        <th>Invitee</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Used At</th>
                        <th>Expires</th>
                        <th>IP</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>{$sent_rows}</tbody>
            </table>
        </div>";
    }

    // ── Invite tree (recursive, max depth 3) ──────────────────────────────────
    $treeHtml = buildInviteTree($uid, $db, $BASEURL);

    
    if (!$treeHtml) {
        $treeSection = "
        <div class='text-center text-muted py-5'>
            <i class='fas fa-sitemap fa-2x mb-2 d-block opacity-25'></i>
            No invite tree — nobody registered via this user's invites yet.
        </div>";
    } else {
        $treeSection = "
        <div class='border rounded-3 p-3 bg-light'>
            {$treeHtml}
        </div>";
    }

    // ── Who invited THIS user ─────────────────────────────────────────────────
    $inviterSection = '';
    $invitedBy = (int)($userdata['invited_by'] ?? 0);
    if ($invitedBy) {
        $iq = $db->sql_query("SELECT id, username, enabled, donor FROM users WHERE id = {$invitedBy} LIMIT 1");
        if ($ir = $db->fetch_array($iq)) {
            $iname   = htmlspecialchars($ir['username'], ENT_QUOTES, 'UTF-8');
            $ibanned = $ir['enabled'] === 'no' ? "<span class='badge bg-danger ms-1'>Banned</span>" : '';
            $idonor  = $ir['donor']  === 'yes' ? "<span class='badge bg-info ms-1'>Donor</span>"   : '';
            $inviterSection = "
            <div class='alert alert-light border d-flex align-items-center gap-3 mb-0'>
                <i class='fas fa-user-plus fa-lg text-primary'></i>
                <div>
                    <div class='fw-semibold'>Invited by</div>
                    <a href='{$BASEURL}/profile.php?id={$invitedBy}' class='text-decoration-none'>{$iname}</a>
                    {$ibanned}{$idonor}
                </div>
            </div>";
        }
    } else {
        $inviterSection = "
        <div class='alert alert-light border text-muted mb-0'>
            <i class='fas fa-user-plus me-2'></i>No inviter — registered without an invite.
        </div>";
    }

    // ── Assemble ──────────────────────────────────────────────────────────────
    return "
    <div class='d-flex align-items-center mb-4'>
        <i class='fas fa-ticket-alt fa-lg text-primary me-2'></i>
        <h5 class='fw-bold mb-0'>Invites</h5>
    </div>

    {$statsBar}

    <div class='mb-4'>{$inviterSection}</div>

    <ul class='nav nav-pills mb-4 gap-2' id='inviteSubTabs' role='tablist'>
        <li class='nav-item' role='presentation'>
            <button class='nav-link active' data-bs-toggle='pill' data-bs-target='#inv-sent' type='button'>
                <i class='fas fa-paper-plane me-1'></i>Sent Invites
                <span class='badge bg-secondary ms-1'>{$sent_count}</span>
            </button>
        </li>
        <li class='nav-item' role='presentation'>
            <button class='nav-link' data-bs-toggle='pill' data-bs-target='#inv-tree' type='button'>
                <i class='fas fa-sitemap me-1'></i>Invite Tree
            </button>
        </li>
    </ul>

    <div class='tab-content'>
        <div class='tab-pane fade show active' id='inv-sent' role='tabpanel'>
            {$sentTable}
        </div>
        <div class='tab-pane fade' id='inv-tree' role='tabpanel'>
            {$treeSection}
        </div>
    </div>";
}




function handleEditUser(): void
{
    global $userdata, $BASEURL, $CURUSER, $usergroups, $db;
    
    get_user_data();
    permission_check();
    stdhead('Edit User: ' . $userdata['username'] . ' (UID: ' . $userdata['id'] . ')');
	
	$user_avatar = format_avatar($userdata['avatar'], $userdata['avatardimensions']);
	
	
	
	$is_own_profile = ((int)$CURUSER['id'] === (int)$userdata['id']);
    $is_mod = is_mod($usergroups); // твоя функция
    $can_change_avatar = ($is_own_profile || $is_mod) ? 1 : 0;
	
	
    echo '<script src="'.$BASEURL.'/admin/scripts/avatar.js"></script>';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/edituser.css">';

    
   
   
   

    // Enhanced action buttons
    echo '
    <div class="container mt-4">
        <div class="d-flex flex-wrap gap-3 mb-4">
            <a href="' . $BASEURL . '/' . get_profile_link($userdata['id']) . '" class="btn btn-premium">
                <i class="fas fa-arrow-left me-2"></i>Back to Profile
            </a>
            <a href="' . $BASEURL . '/search.php?action=finduserthreads&uid=' . $userdata['id'] . '" class="btn btn-outline-primary">
                <i class="fas fa-file-alt me-2"></i>User Threads
            </a>
            <a href="' . $BASEURL . '/search.php?action=finduser&uid=' . $userdata['id'] . '" class="btn btn-outline-primary">
                <i class="fas fa-comments me-2"></i>User Posts
            </a>
            <a href="' . $BASEURL . '/admin/index.php?act=ip_info&userid=' . $userdata['id'] . '" class="btn btn-outline-warning">
                <i class="fas fa-network-wired me-2"></i>IP Info
            </a>
            <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=resetpasskey&userid=' . $userdata['id'] . '" class="btn btn-outline-info">
                <i class="fas fa-key me-2"></i>Reset Passkey
            </a>
            <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=deleteaccount&userid=' . $userdata['id'] . '" class="btn btn-outline-danger">
                <i class="fas fa-trash-alt me-2"></i>Delete Account
            </a>
			
			<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#quickBanModal">
                <i class="fas fa-ban me-2"></i>Quick Ban
            </button>
			
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sendPMModal">
                <i class="fas fa-paper-plane me-2"></i>Send PM
            </button>
            <a href="' . $BASEURL . '/admin/index.php?act=usersearch&uploader=' . $userdata['id'] . '" class="btn btn-outline-secondary">
                <i class="fas fa-upload me-2"></i>Uploads
            </a>
            <a href="' . $BASEURL . '/userhistory.php?id=' . $userdata['id'] . '" class="btn btn-outline-secondary">
                <i class="fas fa-comments me-2"></i>Comments
            </a>
        </div>
    </div>
';
    
	echo renderSendPMModal();
	
	$uid_check = (int)$userdata['id'];
    $q = $db->simple_select('banned', 'uid', "uid='{$uid_check}'", ['limit' => 1]);
    $is_user_banned = $db->num_rows($q) > 0;
    echo renderQuickBanModal($is_user_banned);
	


    // User header with stats
    echo '
    <div class="container mb-5">
        <div class="glass-card p-4 mb-4">
            <div class="row align-items-center">
                
				
			
				
				
				
				<div class="col-auto">
  <div class="avatar-ring position-relative hov-soft"
       id="avatar-container"
       data-uid="'.$userdata['id'].'"
       data-can-change="'.$can_change_avatar.'"
       title="Avatar">
    <div>
     ' . $user_avatar['html'] . '
      <span class="avatar-overlay">Change</span>
    </div>

    <div id="avatar-progress"><div id="avatar-progress-bar"></div></div>
  </div>
</div>
<input type="file" id="avatar-input" accept="image/*" style="display:none;">				
				
				
				
				
				
				
				
				
				
				
				
				
                <div class="col">
                    <div class="d-flex align-items-center mb-2">
                        <h2 class="mb-0 me-3">' . htmlspecialchars_uni($userdata['username']) . '</h2>
                        <span class="user-badge badge-premium">
                            <i class="fas fa-id-card me-1"></i>UID: ' . $userdata['id'] . '
                        </span>
                        ' . get_user_status_badges($userdata) . '
                    </div>
                    <p class="text-muted mb-3">
                        <i class="fas fa-envelope me-2"></i>' . htmlspecialchars_uni($userdata['email']) . '
                        <span class="mx-3">•</span>
                        <i class="fas fa-user-group me-2"></i>' . get_user_class_name($userdata['usergroup']) . '
                    </p>
                    <div class="row">
                        ' . get_user_stats($userdata) . '
                    </div>
                </div>
            </div>
        </div>
    </div>';






   
// Main form with tabs
echo '
<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '" name="updateuser" id="userEditForm">
    <input type="hidden" name="userid" value="' . $userdata['id'] . '">
    <input type="hidden" name="action" value="updateuser">
    
    <div class="container">
        <div class="glass-card">
            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs-premium nav-fill p-3" id="userTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                        <i class="fas fa-user me-2"></i>Basic Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Permissions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="warning-tab" data-bs-toggle="tab" data-bs-target="#warning" type="button" role="tab">
                        <i class="fas fa-exclamation-triangle me-2"></i>Warnings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
                        <i class="fas fa-chart-bar me-2"></i>Statistics
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                        <i class="fas fa-cog me-2"></i>Account Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced" type="button" role="tab">
                        <i class="fas fa-cogs me-2"></i>Advanced
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                        <i class="fas fa-history me-2"></i>Activity
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                        <i class="fas fa-shield-halved me-2"></i>Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="hnr-tab" data-bs-toggle="tab" data-bs-target="#hnr" type="button" role="tab">
                        <i class="fas fa-exclamation-circle me-2"></i>Hit &amp; Run
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="forum-tab" data-bs-toggle="tab" data-bs-target="#forum" type="button" role="tab">
                        <i class="fas fa-comments me-2"></i>Forum
                    </button>
                </li>
                <!-- ДОБАВЛЕНО: Audit Log Tab Button -->
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit" type="button" role="tab">
                        <i class="fas fa-clipboard-list me-2"></i>Audit Log
                    </button>
                </li>
				
				<li class="nav-item" role="presentation">
                   <button class="nav-link" id="torrents-tab" data-bs-toggle="tab" data-bs-target="#torrents" type="button" role="tab">
                        <i class="fas fa-exchange-alt me-2"></i>Torrent Activity
                   </button>
                </li>
				
				<li class="nav-item" role="presentation">
                   <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                        <i class="fas fa-flag me-2"></i>Reports
                   </button>
                </li>
				
				
				<li class="nav-item" role="presentation">
                   <button class="nav-link" id="invites-tab" data-bs-toggle="tab" data-bs-target="#invites" type="button" role="tab">
                         <i class="fas fa-ticket-alt me-2"></i>Invites
                   </button>
                </li>
				
				
				
            </ul>
            
            <div class="tab-content p-4" id="userTabsContent">
                <!-- Basic Info Tab -->
                <div class="tab-pane fade show active" id="basic" role="tabpanel">
                    ' . renderBasicInfoTab() . '
                </div>
                
                <!-- Permissions Tab -->
                <div class="tab-pane fade" id="permissions" role="tabpanel">
                    ' . renderPermissionsTab() . '
                </div>
                
                <!-- Warnings Tab -->
                <div class="tab-pane fade" id="warning" role="tabpanel">
                    ' . renderWarningsTab() . '
                </div>
                
                <!-- Statistics Tab -->
                <div class="tab-pane fade" id="stats" role="tabpanel">
                    ' . renderStatisticsTab() . '
                </div>
                
                <!-- Account Settings Tab -->
                <div class="tab-pane fade" id="account" role="tabpanel">
                    ' . renderAccountSettingsTab() . '
                </div>
                
                <!-- Advanced Tab -->
                <div class="tab-pane fade" id="advanced" role="tabpanel">
                    ' . renderAdvancedTab() . '
                </div>

                <!-- Activity Tab -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    ' . renderActivityTab() . '
                </div>

                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    ' . renderSecurityTab() . '
                </div>

                <!-- Hit & Run Tab -->
                <div class="tab-pane fade" id="hnr" role="tabpanel">
                    ' . renderHitRunTab() . '
                </div>

                <!-- Forum Tab -->
                <div class="tab-pane fade" id="forum" role="tabpanel">
                    ' . renderForumTab() . '
                </div>
                
                <!-- Audit Log Tab Content -->
                <div class="tab-pane fade" id="audit" role="tabpanel">
                    ' . renderAuditTab() . '
                </div>
				
				<div class="tab-pane fade" id="torrents" role="tabpanel">
                   '.renderTorrentActivityTab().'
                </div>
				
				<div class="tab-pane fade" id="reports" role="tabpanel">
                   '.renderReportsTab().'
                </div>
				
				<div class="tab-pane fade" id="invites" role="tabpanel">
                   '.renderInvitesTab().'
                </div>
				
				
				
				
            </div>
            
            <!-- Form Footer -->
            <div class="card-footer bg-transparent border-top-0 py-4">
                <div class="text-center">
                    <button type="submit" class="btn btn-success btn-lg px-5 me-3" name="updateuser">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                    <button type="reset" class="btn btn-outline-secondary btn-lg px-5">
                        <i class="fas fa-undo me-2"></i>Reset
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>';






	
	
   
	    // Enhanced JavaScript
	echo '
	
	<style>
	@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
	
<script src="'.$BASEURL.'/admin/scripts/edituser.js"></script>';
	



$chartData   = buildChartData((int)$userdata['id'], $db);
$js_months   = json_encode($chartData['months'],   JSON_UNESCAPED_UNICODE);
$js_dl       = json_encode($chartData['dl_data'],  JSON_UNESCAPED_UNICODE);
$js_ul       = json_encode($chartData['ul_data'],  JSON_UNESCAPED_UNICODE);
$js_days     = json_encode($chartData['days'],     JSON_UNESCAPED_UNICODE);
$js_day_data = json_encode($chartData['day_data'], JSON_UNESCAPED_UNICODE);

echo '<script src="' . $BASEURL . '/admin/scripts/chart.umd.min.js"></script>';
echo '<script>
window.CHART_MONTHS   = ' . ($js_months   ?? '[]') . ';
window.CHART_DL       = ' . ($js_dl       ?? '[]') . ';
window.CHART_UL       = ' . ($js_ul       ?? '[]') . ';
window.CHART_DAYS     = ' . ($js_days     ?? '[]') . ';
window.CHART_DAY_DATA = ' . ($js_day_data ?? '[]') . ';
</script>';
echo '<script src="' . $BASEURL . '/admin/scripts/charts.js"></script>';
    

    stdfoot();
}

/**
 * Handle user update
 */
/**
 * Handle user update
 */
function handleUpdateUser(): void
{
    global $userdata, $db, $CURUSER, $lang, $update_array, $modcomment;
    
    get_user_data();
    permission_check();
	
	$modcomment = rtrim($userdata['modcomment'] ?? '');

    // Initialize update array
    $updateData = [];
    
    // Process avatar separately with validation
    processAvatarUpdate();
    
    // Process other form data
    $updateData = processUserUpdateData();
    
    // Merge avatar updates if any (from local avatars)
    if (!empty($update_array)) {
        $updateData = array_merge($updateData, $update_array);
    }
    
    if (!empty($updateData)) {
        $db->update_query("users", $updateData, "id = " . (int)$userdata['id']);
        write_log("User {$userdata['username']} ({$userdata['id']}) has been edited by {$CURUSER['username']}");
    }

    // Update permissions
    updateUserPermissions();
	
	// Сохраняем финальный modcomment отдельным запросом
    if (!empty($modcomment)) {
        $db->update_query("users", ['modcomment' => $modcomment], "id = " . (int)$userdata['id']);
    }
	

    flash_message("The account ({$userdata['id']}) has been updated successfully!", "success");
    admin_redirect("edituser.php?action=edituser&userid={$userdata['id']}");
}

/**
 * Process avatar update with validation
 */
function processAvatarUpdate(): void
{
    global $userdata, $update_array, $mybb;
    
    // Process remove avatar
    if($mybb->get_input('remove_avatar'))
    {
        $update_array['avatar'] = "";
        $update_array['avatardimensions'] = "";
        $update_array['avatartype'] = "";
        remove_avatars((int)$userdata['id']);
        $modcomment = modcomment("Avatar removed");
        return;
    }
    
    // Avatar processing is already handled in the main avatar block above
    // This function just ensures remove_avatar works correctly
}

/**
 * Process user update data from form
 */
function processUserUpdateData(): array
{
    global $userdata, $CURUSER, $db, $mybb, $modcomment;
	
	$updateData = [];

    $fields = [
        'username', 'email', 'usergroup', 'usertitle', 'signature',
        'donor', 'moderateposts', 'enabled', 'seedbonus', 'invites',
        'uploaded', 'downloaded', 'allownotices', 'hideemail', 'receivepms',
        'receivefrombuddy', 'pmnotice', 'pmnotify', 'buddyrequestspm',
        'buddyrequestsauto', 'subscriptionmethod', 'invisible'
    ];

    

    
    foreach ($fields as $field) {
        if (isset($_POST[$field]) && $_POST[$field] != $userdata[$field]) {
            $updateData[$field] = $_POST[$field];
            $modcomment = modcomment(ucfirst($field) . " changed from '{$userdata[$field]}' to '{$_POST[$field]}'");
        }
    }
	


    // Process password
    if (!empty($_POST['password'])) {
        $user['loginkey'] = generate_loginkey();
        $password_fields = create_password($_POST['password'], false, $user);
        $updateData = array_merge($updateData, $password_fields);
        $modcomment = modcomment("Password updated");
    }

    // Process warnings
    if (($_POST['warned'] ?? '') == 'no' && $userdata['warned'] == 'yes') {
        $updateData['warned'] = 'no';
        $updateData['warneduntil'] = '0';
        $modcomment = modcomment("Warning removed");
    } elseif (isset($_POST['warnlength']) && is_valid_id($_POST['warnlength']) && $userdata['warned'] == 'no') {
        $warnpm = $_POST['warnpm'] ?: 'No Reason Given.';
        if ($_POST['warnlength'] == 255) {
            $updateData['warneduntil'] = '0000-00-00 00:00:00';
        } else {
            $updateData['warneduntil'] = TIMENOW + $_POST['warnlength'] * 604800;
        }
        $updateData['warned'] = 'yes';
        $updateData['timeswarned'] = $userdata['timeswarned'] + 1;
        $updateData['lastwarned'] = TIMENOW;
        $updateData['warnedby'] = $CURUSER['id'];
        $modcomment = modcomment("User warned for {$_POST['warnlength']} weeks. Reason: {$warnpm}");
    }

    // Process additional comment
    if (!empty($_POST['addcomment'])) {
        $modcomment = gmdate('Y-m-d') . ' - ' . $_POST['addcomment'] . ' - ' . $CURUSER['username'] . "\n" . $modcomment;
    }

    // Process reset times warned
    if (!empty($_POST['reset_timeswarned'])) {
        $updateData['timeswarned'] = 0;
        $modcomment = modcomment('Warning count reset');
    }

    // Update modcomment if changed
    if ($modcomment != $userdata['modcomment']) {
        $updateData['modcomment'] = $modcomment;
    }
	
	
// ── Quick Ban ─────────────────────────────────────────────────────────────
if (!empty($_POST['quick_ban'])) {
    $ban_reason   = trim($_POST['ban_reason']   ?? 'No reason given.');
    $ban_duration = trim($_POST['ban_duration'] ?? '');
    $banned_gid   = (int)($_POST['banned_gid']  ?? 0);
    $send_pm_ban  = !empty($_POST['ban_send_pm']);

    if ($banned_gid > 0) {
        $lifted = ($ban_duration !== '---' && $ban_duration !== '')
            ? ban_date2timestamp($ban_duration)
            : 0;

        $ban_times_map = fetch_ban_times();
        $dur_label = ($ban_duration === '---' || $ban_duration === '')
            ? 'Permanently'
            : ($ban_times_map[$ban_duration] ?? $ban_duration);

        // Проверяем существующий бан
        $existing = $db->simple_select('banned', 'uid', "uid='" . (int)$userdata['id'] . "'", ['limit' => 1]);

        if ($db->num_rows($existing) > 0) {
            // UPDATE — обновляем бан
            $db->update_query('banned', [
                'bantime'  => $db->escape_string($ban_duration === '---' ? '---' : $ban_duration),
                'lifted'   => (string)$lifted,
                'reason'   => $db->escape_string($ban_reason),
                'admin'    => (int)$CURUSER['id'],
                'dateline' => TIMENOW,
            ], "uid='" . (int)$userdata['id'] . "'");
            modcomment("Ban updated ({$dur_label}). Reason: {$ban_reason}");
        } else {
            // INSERT — новый бан
            $db->insert_query('banned', [
                'uid'                 => (int)$userdata['id'],
                'gid'                 => $banned_gid,
                'oldgroup'            => (int)$userdata['usergroup'],
                'oldadditionalgroups' => $db->escape_string($userdata['additionalgroups'] ?? ''),
                'olddisplaygroup'     => (int)$userdata['displaygroup'],
                'admin'               => (int)$CURUSER['id'],
                'dateline'            => TIMENOW,
                'bantime'             => $db->escape_string($ban_duration === '---' ? '---' : $ban_duration),
                'lifted'              => (string)$lifted,
                'reason'              => $db->escape_string($ban_reason),
            ]);
            $updateData['usergroup'] = $banned_gid;
            modcomment("Banned ({$dur_label}). Reason: {$ban_reason}");
        }

        $updateData['modcomment'] = $modcomment;
    }
}
	
	
	

    // FIX: Kill all sessions
    if (!empty($_POST['kill_sessions'])) {
        $db->delete_query('sessions', 'uid = ' . (int)$userdata['id']);
        $modcomment = modcomment('All sessions killed');
        $updateData['modcomment'] = $modcomment;
        write_log("All sessions for user {$userdata['username']} ({$userdata['id']}) killed by {$CURUSER['username']}");
    }

    // FIX: Reset login lockout
    if (!empty($_POST['reset_loginattempts'])) {
        $updateData['loginattempts']       = 0;
        $updateData['loginlockoutexpiry']  = 0;
        $modcomment = modcomment('Login lockout reset');
        $updateData['modcomment'] = $modcomment;
    }

    // FIX: Send PM from edit form
    if (!empty($_POST['send_pm']) && !empty($_POST['pm_subject']) && !empty($_POST['pm_message'])) {
        require_once INC_PATH . '/functions_pm.php';
        send_pm(
            ['touid' => (int)$userdata['id'], 'message' => trim($_POST['pm_message']), 'subject' => trim($_POST['pm_subject'])],
            (int)$CURUSER['id'],
            true
        );
        $modcomment = modcomment('PM sent: ' . substr(trim($_POST['pm_subject']), 0, 60));
        $updateData['modcomment'] = $modcomment;
    }

    return $updateData;
}

/**
 * Update user permissions
 */

function updateUserPermissions(): void
{
    global $db, $userid, $userdata, $modcomment, $CURUSER;

    // Не обновляем если поля не переданы (например при отправке ЛС)
    if (!isset($_POST['cancomment']) && !isset($_POST['canupload']) && !isset($_POST['candownload'])) {
        return;
    }

    $fields = [
        'cancomment'  => (int)(($_POST['cancomment']  ?? '') === 'yes'),
        'canupload'   => (int)(($_POST['canupload']   ?? '') === 'yes'),
        'candownload' => (int)(($_POST['candownload'] ?? '') === 'yes'),
    ];

    foreach ($fields as $field => $newVal) {
        $oldVal = (int)($userdata[$field] ?? 1);
        if ($newVal !== $oldVal) {
            modcomment(ucfirst($field) . " changed from '{$oldVal}' to '{$newVal}'");
        }
    }
    $db->update_query('users', $fields, 'id = ' . (int)$userid);
}





/**
 * Handle account deletion
 */
function handleDeleteAccount(): void
{
    global $userdata, $usergroups, $mybb, $db, $CURUSER;
    
    if (($usergroups['issupermod'] != '1' && $usergroups['cansettingspanel'] != '1')) {
        print_no_permission();
    }

    get_user_data();
    permission_check();

    $userid = (int)$userdata['id'];
    $username = htmlspecialchars_uni($userdata['username']);

    if (!isset($_GET['sure'])) {
        stdhead();
        echo '
        <div class="container mt-5">
            <div class="glass-card">
                <div class="card-header bg-danger text-white text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                    <h3 class="mb-0">Confirm Account Deletion</h3>
                </div>
                <div class="card-body text-center py-5">
                    <h5 class="text-danger mb-3">Are you sure you want to delete this account?</h5>
                    <div class="user-info bg-light rounded p-4 mb-4">
                        <h4 class="text-danger">' . $username . '</h4>
                        <p class="text-muted mb-0">UID: ' . $userid . '</p>
                    </div>
                    <p class="text-muted mb-4">This action cannot be undone. All user data will be permanently removed.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=deleteaccount&userid=' . $userid . '&sure=1&my_post_key=' . $mybb->post_code . '" class="btn btn-danger btn-lg">
                            <i class="fas fa-trash me-2"></i>Yes, Delete Account
                        </a>
                        <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=edituser&userid=' . $userid . '" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>';
        stdfoot();
        return;
    }

    // Get user data
    $user = get_user((int)$userdata['id']);

    require_once INC_PATH . "/datahandlers/user.php";
    $userhandler = new UserDataHandler('delete');

    if (!$userhandler->delete_user([$user['id']])) 
    {
       stderr('Error', 'Cannot delete user!');
       redirect($_SERVER['SCRIPT_NAME']);
    }

    write_log('Account: ' . $userdata['username'] . ' (' . $userdata['id'] . ') has been deleted by ' . $CURUSER['username']);
    
	
	stdok(
    message:  'The account <strong>' . htmlspecialchars_uni($userdata['username']) . '</strong> has been successfully deleted.',
    title:    'Account Deleted',
    subtitle: 'The user has been removed from the system.'
);
	
	
	
	
	
	
}

/**
 * Handle passkey reset
 */
function handleResetPasskey(): void
{
    global $userdata, $db, $CURUSER, $lang, $userid, $mybb;
    
    get_user_data();
    permission_check();

    if (isset($_POST['sure']) && $_POST['sure'] == 1) 
	{
        $modcomment = $userdata['modcomment'];
        $old_passkey = $userdata['passkey'];
        $passkey = generate_passkey($userdata['username'], $userdata['loginkey']);

        $modcomment = gmdate('Y-m-d') . ' - Passkey reset by ' . $CURUSER['username'] . 
              '. Old Passkey: ' . $old_passkey . 
              ' | New Passkey: ' . $passkey . "\n" . $modcomment;

        $msg = sprintf($lang->modtask['passkeymsg'], $CURUSER['username']);
        $subject = $lang->modtask['passkeysubject'];

        $db->sql_query('UPDATE users SET passkey = ' . $db->sqlesc($passkey) . ', modcomment = ' . $db->sqlesc($modcomment) . ' WHERE id = ' . $db->sqlesc($userdata['id']));
        insert_message($userid, $msg, $subject);

        write_log('Passkey for user: ' . $userdata['username'] . ' (' . $userdata['id'] . ') has been reset by ' . $CURUSER['username']);

        flash_message("Passkey has been reset successfully!", "success");
        admin_redirect('edituser.php?action=edituser&userid=' . $userdata['id']);
        return;
    }

    stdhead('Reset Passkey for: ' . $userdata['username']);

    echo '
    <div class="container mt-5">
        <div class="glass-card">
            <div class="card-header bg-warning text-dark text-center py-4">
                <i class="fas fa-key fa-3x mb-3"></i>
                <h3 class="mb-0">Reset Passkey</h3>
            </div>
            <div class="card-body text-center py-5">
                <h5 class="mb-4">Are you sure you want to reset passkey for user?</h5>
                <div class="user-info bg-light rounded p-4 mb-4">
                    <h4>' . htmlspecialchars_uni($userdata['username']) . '</h4>
                    <p class="text-muted mb-0">UID: ' . $userdata['id'] . '</p>
                </div>
                <p class="text-muted mb-4">User will receive a notification and will need to update their clients.</p>
                <form method="post" action="' . $_SERVER['PHP_SELF'] . '?action=resetpasskey&userid=' . $userdata['id'] . '">
                    <input type="hidden" name="userid" value="' . $userdata['id'] . '" />
                    <input type="hidden" name="sure" value="1" />
                    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
                    <div class="d-flex justify-content-center gap-3">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fas fa-key me-2"></i>Yes, Reset Passkey
                        </button>
                        <a href="edituser.php?action=edituser&userid=' . $userdata['id'] . '" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>';

	
	

    stdfoot();
}

?>