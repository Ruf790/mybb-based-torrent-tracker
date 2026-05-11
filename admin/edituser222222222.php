<?php

declare(strict_types=1);



// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

flash_message();



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
    
    $eol = PHP_EOL;
    
    // Заменяем проблемные кавычки на безопасные символы
    $action = str_replace("'", "`", $what);
    $username = $CURUSER['username'];
    
    return gmdate('Y-m-d') . " - {$action} by {$username}{$eol}{$modcomment}";
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
    
    $res = $db->sql_query('
        SELECT u.*, g.cansettingspanel, g.canstaffpanel, g.issupermod,
               (SELECT COUNT(*) FROM tsf_posts WHERE uid = u.id) as post_count,
               (SELECT COUNT(*) FROM tsf_threads WHERE uid = u.id) as thread_count
        FROM users u 
        LEFT JOIN usergroups g ON (u.usergroup = g.gid) 
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
    
    $tracker_query = $db->sql_query('SELECT email FROM users WHERE email = ' . $db->sqlesc($email) . ' LIMIT 1');
    return $db->num_rows($tracker_query) <= 0;
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
    $db->update_query("users", $updated_avatar, "id='{$uid}'");

    // Модкоммент
    $modcomment = gmdate('Y-m-d') . " - Avatar updated by {$CURUSER['username']}\n" . ($userdata['modcomment'] ?? '');
    $db->update_query("users", ["modcomment" => $modcomment], "id='{$uid}'");

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

    // Сравниваем с текущим аватаром
    $old_avatar = trim(strtolower($userdata['avatar'] ?? ''));
    $new_avatar = trim(strtolower($avatar_url));
    $old_clean = preg_replace('/\?dateline=\d+$/', '', $old_avatar);
    $new_clean = preg_replace('/\?dateline=\d+$/', '', $new_avatar);

    if ($old_clean === $new_clean) return "Avatar unchanged";

    // Проверяем URL
    if (!is_valid_avatar_url($avatar_url)) return "URL does not point to a valid image file";
    if (strlen($avatar_url) > 200) return "Avatar URL too long";

    // Удаляем старые аватары
    remove_avatars($user_id);

    // Получаем размеры удалённого аватара
    $dimensions = '';
    $temp_file = tempnam(sys_get_temp_dir(), 'avatar_');
    require_once INC_PATH . '/functions_ts_remote_connect.php';

    try {
        $file_content = TS_Fetch_Data($avatar_url);
        if ($file_content) {
            file_put_contents($temp_file, $file_content);
            $img_info = getimagesize($temp_file);
            if ($img_info !== false) $dimensions = $img_info[0] . '|' . $img_info[1];
        }
    } catch (Exception $e) {
        // Игнорируем ошибки скачивания
    }
    @unlink($temp_file);

    // Обновляем базу
    $updated = [
        "avatar" => $db->escape_string($avatar_url),
        "avatardimensions" => $dimensions,
        "avatartype" => "remote"
    ];
    $db->update_query("users", $updated, "id='{$user_id}'");

    // Обновляем локально userdata
    $userdata['avatar'] = $avatar_url;

    return ""; // успех
}

/**
 * Проверка локального аватара
 */
function is_valid_local_avatar(string $avatar): bool
{
    $avatar = trim($avatar);
    if ($avatar === '') return false;
    if (str_starts_with($avatar, '/uploads/avatars/')) {
        return (bool)preg_match('#\.(gif|jpg|jpeg|png|bmp|webp|avif)$#i', $avatar);
    }
    return false;
}


/**
 * Главная обработка аватара
 */
function handle_avatar_update(): void
{
    global $userdata, $db;

    if (!isset($_POST['avatar'])) return;

    $new_avatar = trim($_POST['avatar'] ?? '');
    $current_user_id = (int)($userdata['id'] ?? $_POST['userid'] ?? 0);
    if ($current_user_id <= 0) {
        flash_message("Error: User ID not found", "error");
        return;
    }

    $old_avatar = trim($userdata['avatar'] ?? '');
    $new_clean = preg_replace('/\?dateline=\d+$/', '', strtolower($new_avatar));
    $old_clean = preg_replace('/\?dateline=\d+$/', '', strtolower($old_avatar));

    // Проверяем, изменился ли аватар
    if ($new_clean === $old_clean) return;

    // Флеши и модкомменты
    $modcomment = '';

    // Удаление аватара
    if ($new_avatar === '') {
        remove_avatars($current_user_id);
        $update_array = [
            'avatar' => '',
            'avatardimensions' => '',
            'avatartype' => ''
        ];
        $db->update_query("users", $update_array, "id='{$current_user_id}'");
        $userdata['avatar'] = '';
        $modcomment = modcomment("Avatar removed");
        flash_message("Avatar successfully removed!", "success");
        return;
    }

    // URL-аватар
    if (filter_var($new_avatar, FILTER_VALIDATE_URL)) 
	{
        $error = process_avatar_url($new_avatar, $current_user_id);
        if ($error === "") {
            $modcomment = modcomment("Avatar updated via URL");
            flash_message("Avatar successfully updated!", "success");
        } elseif ($error !== "Avatar unchanged") {
            $modcomment = modcomment("Avatar update error: $error");
            flash_message("Error: $error", "error");
        }
        return;
    }

   
    // Локальный аватар
    if (is_valid_local_avatar($new_avatar)) 
	{
        flash_message("Avatar successfully updated!", "success");
        return;
    }   
   

    // Невалидный формат
    $modcomment = modcomment("Attempt to update avatar: invalid format");
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




handle_avatar_update();



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
            
            ' . selectbox('Tracker Usergroup', 'usergroup', 'trackergroups', 'form-select') . '
            
            ' . inputbox('Avatar URL', 'avatar', htmlspecialchars_uni($userdata['avatar']), 'form-control', '35', '<small class="text-muted">Full URL to avatar image</small>', '500', true) . '
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
   
$query = $db->sql_query('SELECT canupload, candownload, cancomment FROM users WHERE id = ' . $db->sqlesc($userid) . ' LIMIT 1');
$permresults = $db->fetch_array($query);

$cancomment  = ($permresults['cancomment']  ?? 1) == 1 ? 'yes' : 'no';
$canupload   = ($permresults['canupload']   ?? 1) == 1 ? 'yes' : 'no';
$candownload = ($permresults['candownload'] ?? 1) == 1 ? 'yes' : 'no';

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
    </div>' : '');
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item mb-2">
                                <strong>Registered:</strong> ' . my_datee('relative', $userdata['added']) . '
                            </div>
                            <div class="info-item mb-2">
                                <strong>Last Active:</strong> ' . my_datee('relative', $userdata['lastactive']) . '
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item mb-2">
                                <strong>Last Visit:</strong> ' . my_datee('relative', $userdata['lastvisit']) . '
                            </div>
                            <div class="info-item mb-2">
                                <strong>Posts:</strong> ' . ($userdata['post_count'] ?? 0) . '
                            </div>
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
function handleEditUser(): void
{
    global $userdata, $BASEURL, $CURUSER, $usergroups;
    
    get_user_data();
    permission_check();
    stdhead('Edit User: ' . $userdata['username'] . ' (UID: ' . $userdata['id'] . ')');
	
	$user_avatar = format_avatar($userdata['avatar'], $userdata['avatardimensions']);
	
	
	
	$is_own_profile = ((int)$CURUSER['id'] === (int)$userdata['id']);
    $is_mod = is_mod($usergroups); // твоя функция
    $can_change_avatar = ($is_own_profile || $is_mod) ? 1 : 0;
	
	
    echo '<script src="'.$BASEURL.'/admin/scripts/avatar.js"></script>';
    
    echo '
   
    
    <style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
		--ring:#dfe7ff; --accent:#3b82f6; --shadow:0 8px 20px rgba(16,24,40,.06), 0 2px 8px rgba(16,24,40,.04);
		
    }
	
	
    
   
    
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 20px;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); /* очень легкая тень */
    }
    
    .section-header {
        background: var(--primary-gradient);
        color: white;
        padding: 20px 30px;
        border-radius: 15px;
        margin: 30px 0 20px 0;
        font-weight: 600;
        position: relative;
        overflow: hidden;
    }
    
    .section-header::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
        animation: shimmer 3s infinite;
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
	
	
	
	
	
	
	
	
	
	

	
	
	
	/* ---------- AVATAR RING + STATUS DOT ---------- */
    .avatar-ring{position:relative; display:inline-block; padding:6px; border-radius:50%; background:
      conic-gradient(from 140deg,#fff 0 20%, var(--ring) 20% 70%, #fff 70% 100%)}
    .avatar-ring>*{display:block; border-radius:50%}
    .status-dot{ position:absolute; right:4px; bottom:4px; width:14px; height:14px; border-radius:50%; border:2px solid #fff; background:var(--success); box-shadow:0 0 0 2px #fff }
    .status-away{ background:var(--warning) }
    .status-off{ background:var(--muted) }
	
	
	/* Hover-подсказка */
.avatar-overlay{
  position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
  background:rgba(0,0,0,.35); color:#fff; font-weight:700; opacity:0; transition:.2s;
}
#avatar-container:hover .avatar-overlay{ opacity:1; }
/* Прогресс */
#avatar-progress{ position:absolute; left:50%; transform:translateX(-50%); bottom:-10px;
  width:140px; height:6px; background:#eef2f7; border-radius:999px; overflow:hidden; display:none; }
#avatar-progress-bar{ width:0%; height:100%; background:linear-gradient(90deg,#60a5fa,#3b82f6); }

	
	
	
#avatar-container[data-can-change="0"] { cursor: default !important; }
#avatar-container[data-can-change="0"] .avatar-overlay { display: none !important; }
#avatar-container[data-can-change="1"] { cursor: pointer; }
	
	
	
	
	
	
	
	
    
    .avatar-preview {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        border: 4px solid;
        border-image: var(--primary-gradient) 1;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
        background: white;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        margin: 0 auto;
    }
    
    .avatar-preview::before {
        content: "";
        position: absolute;
        inset: -4px;
        background: var(--primary-gradient);
        border-radius: 50%;
        z-index: -1;
        opacity: 0;
        transition: opacity 0.4s;
    }
    
    .avatar-preview:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
    }
    
    .avatar-preview:hover::before {
        opacity: 1;
    }
    
    .avatar-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s;
    }
    
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        border-left: 5px solid;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .stat-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-gradient);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }
    
    
	
	.btn-premium {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    border-radius: 12px;
    padding: 12px 30px;
    font-weight: 600;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    color: white;
}

.btn-premium::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s;
}

.btn-premium:hover::before {
    left: 100%;
}

.btn-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
    color: white;
}
	
	
    
    .form-control, .form-select {
        border-radius: 12px;
        border: 2px solid #eef2f7;
        padding: 12px 20px;
        transition: all 0.3s;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        transform: translateY(-2px);
    }
    
    .nav-tabs-premium {
        border-bottom: 2px solid #eef2f7;
    }
    
    .nav-tabs-premium .nav-link {
        border: none;
        border-radius: 12px 12px 0 0;
        padding: 15px 30px;
        font-weight: 600;
        color: #6c757d;
        transition: all 0.3s;
        position: relative;
    }
    
    .nav-tabs-premium .nav-link.active {
        color: #667eea;
        background: rgba(102, 126, 234, 0.1);
    }
    
    .nav-tabs-premium .nav-link.active::after {
        content: "";
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: 3px 3px 0 0;
    }
    
    .floating-action-buttons {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
    }
    
    .fab-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: none;
        background: var(--primary-gradient);
        color: white;
        font-size: 20px;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        transition: all 0.3s;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .fab-btn:hover {
        transform: scale(1.1) rotate(15deg);
    }
    
    .user-badge {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.85em;
        margin: 2px;
    }
    
    .badge-premium {
        background: var(--primary-gradient);
        color: white;
    }
    
    .badge-success {
        background: var(--success-gradient);
        color: white;
    }
    
    .badge-warning {
        background: var(--warning-gradient);
        color: white;
    }
    
    .badge-danger {
        background: var(--danger-gradient);
        color: white;
    }
    
    .badge-info {
        background: var(--success-gradient);
        color: white;
    }
    
    .permission-denied-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    
    .permission-denied-modal {
        background: white;
        padding: 40px;
        border-radius: 20px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    .user-not-found {
        min-height: 60vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .error-container {
        max-width: 500px;
    }
    
    .redirect-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.9);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    
    .redirect-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 20px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 12px;
        border: 2px solid #eef2f7;
        padding: 8px;
    }
    
    .avatar-placeholder {
        color: #6c757d;
    }
    
    .signature-preview {
        border: 1px solid #eef2f7;
        min-height: 100px;
    }
    
    .opacity-50 {
        opacity: 0.5;
    }
    </style>';

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
        </div>
    </div>';

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
	

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
	<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Initialize Select2
        $(".select2-enable").each(function() {
            const isSearchable = $(this).data("search") === true;
            $(this).select2({
                theme: "bootstrap-5",
                width: "100%",
                templateResult: formatOption,
                templateSelection: formatOption,
                minimumResultsForSearch: isSearchable ? 0 : -1
            });
        });
        
        function formatOption(option) {
            if (!option.id) return option.text;
            const icon = $(option.element).data("icon");
            if (icon) {
                return $("<span><i class=\'fas " + icon + " me-2\'></i>" + option.text + "</span>");
            }
            return option.text;
        }
        
        // Animate form elements on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = "fadeInUp 0.6s ease-out forwards";
                }
            });
        }, { threshold: 0.1 });
        
        document.querySelectorAll(".form-floating, .stat-card").forEach(el => {
            el.style.opacity = "0";
            el.style.transform = "translateY(30px)";
            observer.observe(el);
        });
        
        // Real-time form validation
        const form = document.getElementById("userEditForm");
        form.addEventListener("input", function(e) {
            const input = e.target;
            if (input.checkValidity()) {
                input.classList.add("is-valid");
                input.classList.remove("is-invalid");
            } else {
                input.classList.add("is-invalid");
                input.classList.remove("is-valid");
            }
        });
        
        // Tab persistence
        const activeTab = localStorage.getItem("activeUserTab");
        if (activeTab) {
            const tab = document.querySelector(`[data-bs-target="${activeTab}"]`);
            if (tab) {
                new bootstrap.Tab(tab).show();
            }
        }
        
        document.querySelectorAll(\'[data-bs-toggle="tab"]\').forEach(tab => {
            tab.addEventListener("shown.bs.tab", function(e) {
                localStorage.setItem("activeUserTab", e.target.getAttribute("data-bs-target"));
            });
        });
    });
    
    
	
	
	
	
	
	
	
	
	
	
	
	 function testAvatar() 
	 {
        const avatarInputs = document.querySelectorAll(\'input[name="avatar"]\');
        if (avatarInputs.length === 0) {
            alert("Avatar field not found!");
            return;
        }
        
        const avatarUrl = avatarInputs[0].value.trim();
        
        if (!avatarUrl) {
            alert("Please enter an avatar URL first.");
            return;
        }
        
        const button = document.querySelector(\'button[onclick="testAvatar()"]\');
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = \'<i class="fas fa-spinner fa-spin me-1"></i>Testing...\';
        
        const img = new Image();
        const timeout = setTimeout(function() {
            img.onload = img.onerror = null;
            button.disabled = false;
            button.innerHTML = originalHtml;
            alert("⏰ Avatar test timed out. The image might be too large or the server is slow to respond.");
        }, 10000);
        
        img.onload = function() {
            clearTimeout(timeout);
            button.disabled = false;
            button.innerHTML = originalHtml;
            alert("✓ Avatar URL is valid and image loads successfully!\\\\n\\\\nDimensions: " + img.naturalWidth + "×" + img.naturalHeight + "px");
        };
        
        img.onerror = function() {
            clearTimeout(timeout);
            button.disabled = false;
            button.innerHTML = originalHtml;
            alert("✗ Avatar URL is invalid or image cannot be loaded!\\\\n\\\\nPlease check:\\\\n• URL is correct\\\\n• Image is accessible\\\\n• Supported format (JPG, PNG, GIF, WebP)");
        };
        
        const separator = avatarUrl.includes(\'?\') ? \'&\' : \'?\';
        img.src = avatarUrl + separator + \'t=\' + new Date().getTime();
    }















	
	
	
	
	
	
    </script>';

    


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
    global $userdata, $db, $CURUSER, $lang, $update_array;
    
    get_user_data();
    permission_check();

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
        remove_avatars($userdata['id']);
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
    global $userdata, $CURUSER, $db, $mybb;
    
    $updateData = [];
    $modcomment = $userdata['modcomment'];
	
	
	
	
	
	
	
	
	
	

    // Process basic fields
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
    if ($_POST['warned'] == 'no' && $userdata['warned'] == 'yes') {
        $updateData['warned'] = 'no';
        $updateData['warneduntil'] = '0';
        $modcomment = modcomment("Warning removed");
    } elseif (is_valid_id($_POST['warnlength']) && $userdata['warned'] == 'no') {
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

    return $updateData;
}

/**
 * Update user permissions
 */
function updateUserPermissions(): void
{
    global $db, $userid;

    $db->update_query('users', [
        'cancomment'  => (int)($_POST['cancomment']  === 'yes'),
        'canupload'   => (int)($_POST['canupload']   === 'yes'),
        'candownload' => (int)($_POST['candownload'] === 'yes'),
    ], 'id = ' . (int)$userid);
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