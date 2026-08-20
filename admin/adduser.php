<?php

declare(strict_types=1);

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_user.php';


if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

define('AU_VERSION', '1.1 by xam');



$lang->load('adduser');
$lang->load("member");



class UserRegistrationHandler
{
    private array $allowed_usergroups = [];
    private array $errors = [];
    private array $user_data = [];

    public function __construct()
    {
        $this->loadAllowedUsergroups();
    }

    private function loadAllowedUsergroups(): void
    {
        global $db;
        
        $query = $db->sql_query_prepared(
            "SELECT gid, title FROM usergroups 
             WHERE isbannedgroup = '0' AND issupermod = '0' 
             AND cansettingspanel = '0' AND canstaffpanel = '0' 
             AND canstaffpanel = '0' ORDER BY gid"
        );
        
        while ($query && ($ug = $db->fetch_array($query))) {
            $this->allowed_usergroups[$ug['gid']] = $ug['title'];
        }
    }

    private function validateUsername(string $username): bool
    {
        global $lang, $db, $minnamelength, $maxnamelength, $illegalusernames;

        if (strlen($username) < $minnamelength) {
            $this->errors[] = "Username must be at least {$minnamelength} characters long";
            return false;
        }

        if (strlen($username) > $maxnamelength) {
            $this->errors[] = "Username cannot be longer than {$maxnamelength} characters";
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            $this->errors[] = "Username can only contain letters and numbers";
            return false;
        }

        // Проверка существования username с подготовленным запросом
        $query = $db->sql_query_prepared(
            "SELECT username FROM users WHERE username = ? LIMIT 1",
            [$username]
        );
        
        if ($db->num_rows($query) > 0) {
            $this->errors[] = "Username already exists";
            return false;
        }

        // Проверка запрещённых имён (те же wildcard-фильтры banfilters, что и при обычной регистрации)
        if (is_banned_username($username, true)) {
            $this->errors[] = "Username is not allowed";
            return false;
        }
        

        return true;
    }

    private function validateEmail(string $email): bool
    {
        global $lang, $db;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = $lang->adduser['invalidemail'];
            return false;
        }

        // Проверка бана email с подготовленным запросом
        if (is_banned_email($email, true)) {
            $this->errors[] = $lang->adduser['banned_email'];
            return false;
        }

        // Проверка существования email с подготовленным запросом
        $query = $db->sql_query_prepared(
            "SELECT email FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
        
        if ($db->num_rows($query) > 0) {
            $this->errors[] = $lang->adduser['invalidemail3'];
            return false;
        }

        return true;
    }

    private function validatePassword(string $password, string $confirm_password, string $username): bool
    {
        global $lang, $minpasswordlength, $maxpasswordlength, $requirecomplexpasswords;

        if ($password !== $confirm_password) {
            $this->errors[] = $lang->adduser['passe1'];
            return false;
        }

        if (strlen($password) < $minpasswordlength) {
            $this->errors[] = "Password must be at least {$minpasswordlength} characters long";
            return false;
        }

        if (strlen($password) > $maxpasswordlength) {
            $this->errors[] = "Password cannot be longer than {$maxpasswordlength} characters";
            return false;
        }

        if ($password === $username) {
            $this->errors[] = $lang->adduser['passe4'];
            return false;
        }

        // Проверка сложности пароля если требуется
        if ($requirecomplexpasswords && !$this->checkPasswordStrength($password)) {
            $this->errors[] = "Password must contain both letters and numbers";
            return false;
        }

        return true;
    }

    private function checkPasswordStrength(string $password): bool
    {
        // Проверяем что пароль содержит и буквы и цифры
        $has_letter = preg_match('/[a-zA-Z]/', $password);
        $has_digit = preg_match('/[0-9]/', $password);

        return $has_letter && $has_digit;
    }

    private function validateUsergroup(int $usergroup): bool
    {
        global $lang;

        if (!array_key_exists($usergroup, $this->allowed_usergroups)) {
            $this->errors[] = $lang->adduser['invalidug'];
            return false;
        }

        return true;
    }

    private function validateAvatar(string $avatar_url): array
    {
        $avatar_data = ['url' => '', 'dimensions' => '0|0'];

        if (empty($avatar_url)) {
            return $avatar_data;
        }

        if (!$this->isUrlSafeForFetch($avatar_url)) {
            $this->errors[] = "Invalid avatar URL or image not accessible";
            return $avatar_data;
        }

        $image_info = @getimagesize($avatar_url);
        if (!$image_info) {
            $this->errors[] = "Invalid avatar URL or image not accessible";
            return $avatar_data;
        }

        $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!in_array($image_info[2], $allowed_types, true)) {
            $this->errors[] = "Unsupported avatar image type";
            return $avatar_data;
        }

        $avatar_data = [
            'url' => $avatar_url,
            'dimensions' => $image_info[0] . "|" . $image_info[1]
        ];

        return $avatar_data;
    }

    /**
     * Guards against SSRF: only plain http/https URLs pointing at a public,
     * non-internal IP address are allowed to be fetched server-side.
     */
    private function isUrlSafeForFetch(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];

        // Resolve the hostname to an IP so we can check the *actual*
        // destination, not just the literal string (defends against
        // "localhost", DNS rebinding to loopback/private ranges, etc.)
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        return true;
    }
	
	
	
	private function handleAvatarUpload(int $user_id = 0): array
{
    global $BASEURL;
    
    $avatar_data = ['url' => '', 'dimensions' => '0|0'];
    
    if (empty($_FILES['avatar_file']['tmp_name'])) {
        return $avatar_data;
    }
    
    $file = $_FILES['avatar_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $this->errors[] = 'Avatar upload failed';
        return $avatar_data;
    }
    
    $allowed_types = ['image/jpeg','image/png','image/gif','image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime, $allowed_types, true)) {
        $this->errors[] = 'Invalid avatar file type';
        return $avatar_data;
    }
    
    if ($file['size'] > 512000) {
        $this->errors[] = 'Avatar file too large (max 500KB)';
        return $avatar_data;
    }
    
    $image_info = getimagesize($file['tmp_name']);
    if (!$image_info) {
        $this->errors[] = 'Invalid image file';
        return $avatar_data;
    }
    
    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    };
    
    $filename    = 'avatar_' . $user_id . '.' . $ext;
    $upload_path = TSDIR . '/uploads/avatars/' . $filename;
    
    
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        $this->errors[] = 'Failed to save avatar';
        return $avatar_data;
    }
    
    return [
        'url'        => $BASEURL . '/uploads/avatars/' . $filename,
        'dimensions' => $image_info[0] . '|' . $image_info[1],
    ];
}
	
	
	
	
	
	
	
	
	
	
	
	

    public function processRegistration(array $post_data): bool
    {
        global $db, $lang, $BASEURL, $SITENAME, $CURUSER, $cache,
               $autogigsignup, $autosbsignup, $_d_usergroup;

        // Очистка и валидация данных
        $username = trim($post_data['username'] ?? '');
        $email = trim($post_data['email'] ?? '');
        $password = $post_data['password'] ?? '';
        $password2 = $post_data['password2'] ?? '';

        // Если поле не заполнено (реально пусто) - подставляем дефолт с сайта,
        // как при обычной саморегистрации. Если введено явно (в т.ч. "0") - уважаем ввод.
        $usergroup_raw = trim((string)($post_data['usergroup'] ?? ''));
        $usergroup = $usergroup_raw !== ''
            ? (int)$usergroup_raw
            : (int)($_d_usergroup ?: 2);

        $modcomment = htmlspecialchars_uni($post_data['modcomment'] ?? '');

        $seedbonus_raw = trim((string)($post_data['seedbonus'] ?? ''));
        $seedbonus = $seedbonus_raw !== ''
            ? (int)$seedbonus_raw
            : (int)($autosbsignup > 0 ? $autosbsignup : 0);

        $invites = (int)($post_data['invites'] ?? 0);

        $uploaded_raw = trim((string)($post_data['uploaded'] ?? ''));
        $uploaded = $uploaded_raw !== ''
            ? (int)$uploaded_raw
            : ($autogigsignup > 0 ? (int)$autogigsignup * 1024 * 1024 * 1024 : 0);

        $downloaded = (int)($post_data['downloaded'] ?? 0);
        $confirm = trim($post_data['confirm'] ?? '');
        $send_credentials = trim($post_data['sendcredentials'] ?? '') === 'yes';
        $avatar_url = trim($post_data['avatar_url'] ?? '');
       

        // Валидации
        $validations = [
            $this->validateUsername($username),
            $this->validateEmail($email),
            $this->validatePassword($password, $password2, $username),
            $this->validateUsergroup($usergroup)
        ];

        

        if (in_array(false, $validations, true) || !empty($this->errors)) {
            return false;
        }

        // Подготовка данных пользователя
        $user = [];
        $user['loginkey'] = generate_loginkey();
        $password_fields = create_password($password, $user);
        $user = array_merge($user, $password_fields);

        // Дополнительные группы
        $additionalgroups = '';
        if (!empty($post_data['additionalgroups']) && is_array($post_data['additionalgroups'])) {
            $additional_groups = array_map('intval', $post_data['additionalgroups']);
            $additional_groups = array_diff($additional_groups, [$usergroup]);
            $additionalgroups = implode(",", $additional_groups);
        }

        // Вставка пользователя с подготовленным запросом
        $user_insert_data = [
            $username,
            $user['password'],
            $user['loginkey'],
            TIMENOW,
            'confirmed',
            $email,
            $usergroup,
            $additionalgroups,
            gmdate('Y-m-d') . ' - ' . $modcomment,
            $seedbonus,
            $invites,
            $uploaded,
            $downloaded,
            '2',
            '',      
            '0|0',   
            "upload",
            '1',
            '',
            '',
            "0**$%%$1**$%%$2**$%%$3**$%%$4**"
        ];

        $placeholders = str_repeat('?,', count($user_insert_data) - 1) . '?';

        $sql = "INSERT INTO users (username, password, loginkey, added, ustatus, email, usergroup, 
                additionalgroups, modcomment, seedbonus, invites, uploaded, downloaded, timezone, avatar, 
                avatardimensions, avatartype, invisible, ignorelist, 
                buddylist, pmfolders) VALUES ({$placeholders})";

        $result = $db->sql_query_prepared($sql, $user_insert_data);

        if (!$result || $db->affected_rows() === 0) {
            $this->errors[] = $lang->global['error'];
            return false;
        }

        $user_id = $db->insert_id();
		
		
// Аватар — только здесь с реальным $user_id
if (!empty($_FILES['avatar_file']['tmp_name'])) {
    $avatar_data = $this->handleAvatarUpload($user_id);
    if (!empty($avatar_data['url'])) {
        $db->sql_query_prepared(
            "UPDATE users SET avatar = ?, avatardimensions = ?, avatartype = 'upload' WHERE id = ?",
            [$avatar_data['url'], $avatar_data['dimensions'], $user_id]
        );
    }
} elseif (!empty($avatar_url)) {
    $avatar_data = $this->validateAvatar($avatar_url);
    if (!empty($avatar_data['url'])) {
        $db->sql_query_prepared(
            "UPDATE users SET avatar = ?, avatardimensions = ?, avatartype = 'remote' WHERE id = ?",
            [$avatar_data['url'], $avatar_data['dimensions'], $user_id]
        );
    }
}


      
        
   

        // Обновление статистики
        update_stats(['numusers' => '+1']);

        // Приветственное сообщение
        require_once INC_PATH . '/functions_pm.php';
        
        $pm = [
            'subject' => sprintf($lang->adduser['welcomepmsubject'], $SITENAME),
            'message' => sprintf($lang->adduser['welcomepmbody'], htmlspecialchars_uni($username), $SITENAME, $BASEURL),
            'touid' => $user_id
        ];
        
        $pm['sender']['uid'] = -1;
        send_pm($pm, -1, true);

        // Отправка логина/пароля на email (по желанию админа)
        if ($send_credentials) {
            $credentialssubject = sprintf($lang->adduser['credentialsemailsubject'], $SITENAME);
            $credentialsbody = sprintf(
                $lang->adduser['credentialsemailbody'],
                $username,
                $SITENAME,
                $password,
                $BASEURL
            );
            $credentials_sent = my_mail($email, $credentialssubject, $credentialsbody);

            if (!$credentials_sent) {
                // Не блокируем создание аккаунта - он уже создан к этому моменту,
                // но админ должен явно узнать, что данные не были доставлены,
                // и передать пароль пользователю каким-то другим способом.
                $this->errors[] = 'Failed to send login credentials email - please deliver the password to the user manually. Password: ' . $password;
            }
        }

        // Подтверждение email
        if ($confirm === 'yes') {
           
		   $db->sql_query_prepared(
    "UPDATE users SET ustatus = 'pending' WHERE id = ?",
    [$user_id]
);
		   
		   
           $activationcode = random_str();
           $db->sql_query_prepared(
               "INSERT INTO awaitingactivation (`uid`,`dateline`,`code`,`type`) VALUES (?,?,?,?)",
               [$user_id, TIMENOW, $activationcode, 'r']
           );
		   
		   $cache->update_awaitingactivation();
		   
           $emailsubject = sprintf($lang->member['emailsubject_activateaccount'], $SITENAME);
           
		   $emailmessage = sprintf($lang->member['email_activateaccount'], $username, $SITENAME, $BASEURL, $user_id, $activationcode);
		   
           my_mail($email, $emailsubject, $emailmessage); 

		   
		   
        }

        // Логирование
        write_log('New Account Created by ' . $CURUSER['username'] . '. Account Name: ' . htmlspecialchars_uni($username));

        $this->user_data = [
            'id' => $user_id,
            'username' => $username,
            'confirm' => $confirm
        ];

        // Аккаунт уже создан к этому моменту - ошибки аватара (если есть)
        // не должны блокировать регистрацию, но админ должен их увидеть.
        if (!empty($this->errors)) {
            flash_message('Account created, but: ' . implode('; ', $this->errors), 'warning');
        }

        return true;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getUserData(): array
    {
        return $this->user_data;
    }

    public function getAllowedUsergroups(): array
    {
        return $this->allowed_usergroups;
    }
}

// Обработка формы
$registration_handler = new UserRegistrationHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post_check($_POST['my_post_key'] ?? '');

    if ($registration_handler->processRegistration($_POST)) {
        $user_data      = $registration_handler->getUserData();
        $post_create_err = $registration_handler->getErrors();
        $profile_url    = $BASEURL . '/' . get_profile_link($user_data['id']);

        // Аккаунт уже создан к этому моменту - ошибки на этом этапе (например,
        // не удалось привязать аватар) не должны выглядеть как провал
        // регистрации, но админ обязательно должен их увидеть. Редирект на
        // member.php/checkuser.php уводит со страниц стафф-панели, где
        // flash_message() гарантированно рендерится, поэтому в этом случае
        // остаёмся на adduser.php и показываем предупреждение здесь.
        if (empty($post_create_err)) {
            redirect($profile_url);
            exit();
        }

        stdhead($lang->adduser['title']);
        echo '<link href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';
        echo '<div class="container mt-3">';
        echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Account "'
            . htmlspecialchars_uni($user_data['username']) . '" was created successfully.</div>';
        echo inline_error($post_create_err);
        echo '<a href="' . $profile_url . '" class="btn btn-primary">'
            . '<i class="fas fa-arrow-right me-2"></i>Go to profile</a>';
        echo '</div>';
        stdfoot();
        exit();
    }
}



// Отображение формы
stdhead($lang->adduser['title']);


$errors = $registration_handler->getErrors();
if (!empty($errors)) {
   
   $send_errors = inline_error($errors);

   echo '<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';
   echo '<div class="container mt-3">'.$send_errors.'</div>';
	
	
}




$allowed_groups = $registration_handler->getAllowedUsergroups();

echo '
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white rounded-top">
            <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>' . $lang->adduser['title'] . '</h4>
            <small class="opacity-75">Username: ' . $minnamelength . '-' . $maxnamelength . ' chars • Password: ' . $minpasswordlength . '-' . $maxpasswordlength . ' chars' . 
            ($requirecomplexpasswords ? ' (letters & numbers required)' : '') . '</small>
        </div>
        
        <form method="POST" action="' . htmlspecialchars($_SERVER['REQUEST_URI']) . '" class="needs-validation" novalidate enctype="multipart/form-data">
            <input type="hidden" name="act" value="adduser">
            <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
            
            <div class="card-body">
                <!-- Basic Information -->
                <div class="row g-3 mb-4">
                    <!-- Username (обязательный) -->
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control" 
                                   id="input_username" 
                                   name="username" 
                                   value="' . htmlspecialchars_uni($_POST['username'] ?? '') . '" 
                                   required
                                   minlength="' . $minnamelength . '"
                                   maxlength="' . $maxnamelength . '"
                                   pattern="[a-zA-Z0-9]+"
                                   placeholder="Username">
                            <label for="input_username" class="form-label">
                                <i class="fas fa-user me-2 text-primary"></i>Username <span class="text-danger">*</span>
                            </label>
                            <div class="invalid-feedback">Username must be ' . $minnamelength . '-' . $maxnamelength . ' characters, letters and numbers only.</div>
                            <div class="form-text text-muted">
                                <small>' . $minnamelength . '-' . $maxnamelength . ' characters, letters and numbers only</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Usergroup (обязательный) -->
                    <div class="col-md-6">
                        <div class="form-floating">
                            <select class="form-select" id="input_usergroup" name="usergroup">
                                <option value="">Choose usergroup... (default: registration group)</option>';
                                foreach ($allowed_groups as $gid => $title) {
                                    $selected = ($_POST['usergroup'] ?? '') == $gid ? 'selected' : '';
                                    echo '<option value="' . $gid . '" ' . $selected . '>' . htmlspecialchars_uni($title) . '</option>';
                                }
echo '                      </select>
                            <label for="input_usergroup" class="form-label">
                                <i class="fas fa-users me-2 text-primary"></i>Usergroup <span class="text-danger">*</span>
                            </label>
                            <div class="invalid-feedback">Please select a usergroup.</div>
                        </div>
                    </div>
                </div>

                <!-- Password Section -->
                <div class="row g-3 mb-2">
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <span class="form-text text-muted mb-0">
                            ' . $minpasswordlength . '-' . $maxpasswordlength . ' characters' . 
                            ($requirecomplexpasswords ? ', must contain letters and numbers' : '') . '
                        </span>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="generatePassword()">
                            <i class="fas fa-dice me-1"></i>Generate Password
                        </button>
                    </div>
                </div>
                <div class="row g-3 mb-2">
                    <!-- Password (обязательный) -->
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="password" 
                                   class="form-control" 
                                   id="input_password" 
                                   name="password" 
                                   required
                                   minlength="' . $minpasswordlength . '"
                                   maxlength="' . $maxpasswordlength . '"
                                   placeholder="Password">
                            <label for="input_password" class="form-label">
                                <i class="fas fa-lock me-2 text-primary"></i>Password <span class="text-danger">*</span>
                            </label>
                            <div class="invalid-feedback">Password must be ' . $minpasswordlength . '-' . $maxpasswordlength . ' characters long.</div>
                        </div>
                    </div>
                    
                    <!-- Confirm Password (обязательный) -->
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="password" 
                                   class="form-control" 
                                   id="input_password2" 
                                   name="password2" 
                                   required
                                   minlength="' . $minpasswordlength . '"
                                   maxlength="' . $maxpasswordlength . '"
                                   placeholder="Confirm Password">
                            <label for="input_password2" class="form-label">
                                <i class="fas fa-lock me-2 text-primary"></i>Confirm Password <span class="text-danger">*</span>
                            </label>
                            <div class="invalid-feedback">Please confirm your password.</div>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-12" id="generated_password_box" style="display:none">
                        <div class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 mb-0">
                            <span><i class="fas fa-check-circle me-2"></i>Generated password: <code id="generated_password_text" class="fw-bold"></code></span>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="copyGeneratedPassword()">
                                <i class="fas fa-copy me-1"></i>Copy
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="row g-3 mb-4">
                    <!-- Email (обязательный) -->
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="email" 
                                   class="form-control" 
                                   id="input_email" 
                                   name="email" 
                                   value="' . htmlspecialchars_uni($_POST['email'] ?? '') . '" 
                                   required
                                   pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                                   placeholder="Email Address">
                            <label for="input_email" class="form-label">
                                <i class="fas fa-envelope me-2 text-primary"></i>Email Address <span class="text-danger">*</span>
                            </label>
                            <div class="invalid-feedback">Please provide a valid email address.</div>
                        </div>
                    </div>
                    
                    <!-- Seed Bonus (необязательный) -->
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="number" 
                                   class="form-control" 
                                   id="input_seedbonus" 
                                   name="seedbonus" 
                                   value="' . htmlspecialchars_uni($_POST['seedbonus'] ?? '') . '"
                                   placeholder="Seed Bonus (default: site signup bonus)">
                            <label for="input_seedbonus" class="form-label">
                                <i class="fas fa-coins me-2 text-primary"></i>Seed Bonus
                            </label>
                        </div>
                    </div>
                </div>

                

                <!-- Traffic Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="number" 
                                   class="form-control" 
                                   id="input_uploaded" 
                                   name="uploaded" 
                                   value="' . htmlspecialchars_uni($_POST['uploaded'] ?? '') . '"
                                   placeholder="Uploaded (default: site signup bonus)">
                            <label for="input_uploaded" class="form-label">
                                <i class="fas fa-upload me-2 text-primary"></i>Uploaded (bytes)
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="number" 
                                   class="form-control" 
                                   id="input_downloaded" 
                                   name="downloaded" 
                                   value="' . htmlspecialchars_uni($_POST['downloaded'] ?? '0') . '"
                                   placeholder="Downloaded">
                            <label for="input_downloaded" class="form-label">
                                <i class="fas fa-download me-2 text-primary"></i>Downloaded (bytes)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control" 
                                   id="input_modcomment" 
                                   name="modcomment" 
                                   value="' . htmlspecialchars_uni($_POST['modcomment'] ?? '') . '"
                                   placeholder="Moderator Comment">
                            <label for="input_modcomment" class="form-label">
                                <i class="fas fa-comment me-2 text-primary"></i>Moderator Comment
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="number" 
                                   class="form-control" 
                                   id="input_invites" 
                                   name="invites" 
                                   value="' . htmlspecialchars_uni($_POST['invites'] ?? '0') . '"
                                   placeholder="Invites">
                            <label for="input_invites" class="form-label">
                                <i class="fas fa-ticket-alt me-2 text-primary"></i>Invites
                            </label>
                        </div>
                    </div>
                </div>

               
				
				
				
				
				<!-- Avatar -->
<div class="mb-4">
    <label class="form-label fw-semibold">
        <i class="fas fa-image me-1 text-primary"></i>Avatar
    </label>

    <!-- Tabs -->
    <ul class="nav nav-pills mb-3" id="avatarTabs">
        <li class="nav-item">
            <button class="nav-link active" id="tab-url" type="button" onclick="switchAvatarTab(\'url\')">
                <i class="fas fa-link me-1"></i>URL
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="tab-file" type="button" onclick="switchAvatarTab(\'file\')">
                <i class="fas fa-upload me-1"></i>Upload file
            </button>
        </li>
    </ul>

    <!-- URL panel -->
    <div id="avatar-panel-url">
        <div class="form-floating">
            <input type="url"
                   class="form-control"
                   id="input_avatar"
                   name="avatar_url"
                   value="' . htmlspecialchars_uni($_POST['avatar_url'] ?? '') . '"
                   placeholder="https://example.com/avatar.jpg">
            <label for="input_avatar"><i class="fas fa-link me-1"></i>Image URL</label>
        </div>
        <div class="form-text">Direct link to JPG, PNG, GIF or WEBP image</div>
    </div>

    <!-- File panel -->
    <div id="avatar-panel-file" style="display:none">
        <div class="border rounded-3 p-4 text-center bg-light" id="avatar-dropzone"
             ondragover="event.preventDefault();this.classList.add(\'border-primary\')"
             ondragleave="this.classList.remove(\'border-primary\')"
             ondrop="handleAvatarDrop(event)">
            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
            <p class="mb-2 text-muted">Drag & drop image here or</p>
            <label class="btn btn-outline-primary btn-sm mb-0" for="input_avatar_file">
                <i class="fas fa-folder-open me-1"></i>Choose file
            </label>
            <input type="file"
                   class="d-none"
                   id="input_avatar_file"
                   name="avatar_file"
                   accept="image/jpeg,image/png,image/gif,image/webp">
            <p class="text-muted mt-2 mb-0" style="font-size:.8rem">JPG, PNG, GIF, WEBP — max 500KB</p>
            <p id="avatar-filename" class="text-success mt-1 mb-0 fw-semibold" style="font-size:.85rem;display:none"></p>
        </div>
    </div>

    <!-- Preview -->
    <div id="avatar_preview" class="mt-3 d-flex align-items-center gap-3" style="display:none!important">
        <img id="avatar_preview_img" src="" class="rounded-circle border shadow-sm"
             style="width:150px;height:150px;object-fit:cover;">
        <div>
            <div class="fw-semibold small">Preview</div>
            <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="clearAvatar()">
                <i class="fas fa-times me-1"></i>Remove
            </button>
        </div>
    </div>
</div>
				
				
				
				
				
				
				
				
				
				
				
				
				

                <!-- Options -->
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" name="sendcredentials" id="sendcredentials" value="yes" 
                           ' . (($_POST['sendcredentials'] ?? 'yes') === 'yes' ? 'checked' : '') . '>
                    <label for="sendcredentials" class="form-check-label">
                        <i class="fas fa-key me-2 text-primary"></i>' . $lang->adduser['sendcredentials'] . '
                    </label>
                </div>
                <div class="form-check mb-4">
                    <input type="checkbox" class="form-check-input" name="confirm" id="confirm" value="yes" 
                           ' . (($_POST['confirm'] ?? '') === 'yes' ? 'checked' : '') . '>
                    <label for="confirm" class="form-check-label">
                        <i class="fas fa-envelope me-2 text-primary"></i>' . $lang->adduser['o1'] . '
                    </label>
                </div>
            </div>
            
            <div class="card-footer bg-light text-center py-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-user-plus me-2"></i>' . $lang->adduser['title'] . '
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Bootstrap валидация формы
(function () {
    \'use strict\'
    
    const forms = document.querySelectorAll(\'.needs-validation\')
    
    Array.from(forms).forEach(form => {
        form.addEventListener(\'submit\', event => {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            
            form.classList.add(\'was-validated\')
        }, false)
    })
})();

// Username валидация в реальном времени
document.getElementById(\'input_username\').addEventListener(\'input\', function() {
    const minLength = ' . $minnamelength . ';
    const maxLength = ' . $maxnamelength . ';
    const value = this.value;
    
    if (!/^[a-zA-Z0-9]+$/.test(value)) {
        this.setCustomValidity(\'Only letters and numbers allowed\');
    } else if (value.length < minLength) {
        this.setCustomValidity(`Username must be at least ${minLength} characters`);
    } else if (value.length > maxLength) {
        this.setCustomValidity(`Username cannot exceed ${maxLength} characters`);
    } else {
        this.setCustomValidity(\'\');
    }
});

// Password валидация в реальном времени
document.getElementById(\'input_password\').addEventListener(\'input\', function() {
    const minLength = ' . $minpasswordlength . ';
    const maxLength = ' . $maxpasswordlength . ';
    const requireComplex = ' . ($requirecomplexpasswords ? 'true' : 'false') . ';
    const value = this.value;
    
    if (value.length < minLength) {
        this.setCustomValidity(`Password must be at least ${minLength} characters`);
    } else if (value.length > maxLength) {
        this.setCustomValidity(`Password cannot exceed ${maxLength} characters`);
    } else if (requireComplex && (!/[a-zA-Z]/.test(value) || !/[0-9]/.test(value))) {
        this.setCustomValidity(\'Password must contain both letters and numbers\');
    } else {
        this.setCustomValidity(\'\');
    }
});

// Пароль подтверждения
document.getElementById(\'input_password2\').addEventListener(\'input\', function() {
    const password = document.getElementById(\'input_password\').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity(\'Passwords do not match\');
    } else {
        this.setCustomValidity(\'\');
    }
});

// Генерация случайного пароля
function generatePassword() {
    const minLength = ' . $minpasswordlength . ';
    const length = Math.max(minLength, 12);
    const letters = \'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ\';
    const digits = \'0123456789\';
    const symbols = \'!@#$%^&*\';
    const charset = letters + digits + symbols;

    let password = letters[Math.floor(Math.random() * letters.length)]
                  + digits[Math.floor(Math.random() * digits.length)];

    for (let i = password.length; i < length; i++) {
        password += charset[Math.floor(Math.random() * charset.length)];
    }

    password = password.split(\'\').sort(() => Math.random() - 0.5).join(\'\');

    const passField = document.getElementById(\'input_password\');
    const pass2Field = document.getElementById(\'input_password2\');
    passField.value = password;
    pass2Field.value = password;
    passField.dispatchEvent(new Event(\'input\'));
    pass2Field.dispatchEvent(new Event(\'input\'));

    document.getElementById(\'generated_password_text\').textContent = password;
    document.getElementById(\'generated_password_box\').style.display = \'\';
}

function copyGeneratedPassword() {
    const text = document.getElementById(\'generated_password_text\').textContent;
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        if (typeof showToast === \'function\') {
            showToast(\'Password copied to clipboard\', \'success\');
        }
    });
}
</script>

<style>
.card { 
    border: none; 
    border-radius: 16px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.card-header { 
    border-radius: 16px 16px 0 0 !important; 
    padding: 1.5rem;
}
.form-label { 
    color: #495057; 
    font-weight: 500;
}
.form-floating {
    margin-bottom: 1rem;
}
.btn-primary {
    border: none;
    border-radius: 12px;
    font-weight: 600;
    padding: 12px 40px;
    transition: all 0.3s ease;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}
.was-validated .form-control:valid {
    border-color: #198754;
    background-image: url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 8 8\'%3e%3cpath fill=\'%23198754\' d=\'M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z\'/%3e%3c/svg%3e");
}
.was-validated .form-control:invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 12 12\' width=\'12\' height=\'12\' fill=\'none\' stroke=\'%23dc3545\'%3e%3ccircle cx=\'6\' cy=\'6\' r=\'4.5\'/%3e%3cpath d=\'m5.8 3.6.4.4.4-.4\'/%3e%3cpath d=\'M6 7v1\'/%3e%3c/svg%3e");
}
.text-danger {
    color: #dc3545 !important;
}
</style>';






?>
<script>
function switchAvatarTab(tab) {
    document.getElementById("avatar-panel-url").style.display  = tab === "url"  ? "" : "none";
    document.getElementById("avatar-panel-file").style.display = tab === "file" ? "" : "none";
    document.getElementById("tab-url").classList.toggle("active",  tab === "url");
    document.getElementById("tab-file").classList.toggle("active", tab === "file");
}

function showAvatarPreview(src) {
    const preview = document.getElementById("avatar_preview");
    document.getElementById("avatar_preview_img").src = src;
    preview.style.display = "flex";
}

function clearAvatar() {
    document.getElementById("avatar_preview").style.display = "none";
    document.getElementById("input_avatar").value = "";
    document.getElementById("input_avatar_file").value = "";
    document.getElementById("avatar-filename").style.display = "none";
}

function handleAvatarDrop(e) {
    e.preventDefault();
    document.getElementById("avatar-dropzone").classList.remove("border-primary");
    const file = e.dataTransfer.files[0];
    if (file) setAvatarFile(file);
}

function setAvatarFile(file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById("input_avatar_file").files = dt.files;
    const name = document.getElementById("avatar-filename");
    name.textContent = file.name;
    name.style.display = "block";
    const reader = new FileReader();
    reader.onload = e => showAvatarPreview(e.target.result);
    reader.readAsDataURL(file);
}

document.getElementById("input_avatar_file").addEventListener("change", function() {
    if (this.files[0]) setAvatarFile(this.files[0]);
});

document.getElementById("input_avatar").addEventListener("input", function() {
    if (this.value) showAvatarPreview(this.value);
    else document.getElementById("avatar_preview").style.display = "none";
});
</script>
<?



stdfoot();
?>