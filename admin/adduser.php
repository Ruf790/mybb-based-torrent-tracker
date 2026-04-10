<?php

declare(strict_types=1);

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_user.php';


if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

define('AU_VERSION', '1.1 by xam');
define("IN_MYBB", 1);

$lang->load('adduser');
$lang->load("signup");
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
        
        $query = $db->sql_query(
            "SELECT gid, title FROM usergroups 
             WHERE isbannedgroup = '0' AND issupermod = '0' 
             AND cansettingspanel = '0' AND canstaffpanel = '0' 
             AND canuserdetails = '0' ORDER BY gid"
        );
        
        while ($ug = $db->fetch_array($query)) {
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

        // Проверка запрещенных имен
        $illegal_names = preg_split('/\s*,\s*/', $illegalusernames, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($illegal_names as $val) {
            $val = trim($val);
            if (!empty($val) && stripos($username, $val) !== false) {
                $this->errors[] = "Username contains forbidden word: " . htmlspecialchars_uni($val);
                return false;
            }
        }

        return true;
    }

    private function validateEmail(string $email): bool
    {
        global $lang, $db;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = $lang->signup['invalidemail'];
            return false;
        }

        // Проверка бана email с подготовленным запросом
        if (is_banned_email($email, true)) {
            $this->errors[] = $lang->signup['banned_email'];
            return false;
        }

        // Проверка существования email с подготовленным запросом
        $query = $db->sql_query_prepared(
            "SELECT email FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
        
        if ($db->num_rows($query) > 0) {
            $this->errors[] = $lang->signup['invalidemail3'];
            return false;
        }

        return true;
    }

    private function validatePassword(string $password, string $confirm_password, string $username): bool
    {
        global $lang, $minpasswordlength, $maxpasswordlength, $requirecomplexpasswords;

        if ($password !== $confirm_password) {
            $this->errors[] = $lang->signup['passe1'];
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
            $this->errors[] = $lang->signup['passe4'];
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

    public function processRegistration(array $post_data): bool
    {
        global $db, $lang, $BASEURL, $SITENAME, $CURUSER, $cache;

        // Очистка и валидация данных
        $username = trim($post_data['username'] ?? '');
        $email = trim($post_data['email'] ?? '');
        $password = $post_data['password'] ?? '';
        $password2 = $post_data['password2'] ?? '';
        $usergroup = (int)($post_data['usergroup'] ?? 0);
        $modcomment = htmlspecialchars_uni($post_data['modcomment'] ?? '');
        $seedbonus = (int)($post_data['seedbonus'] ?? 0);
        $invites = (int)($post_data['invites'] ?? 0);
        $uploaded = (int)($post_data['uploaded'] ?? 0);
        $downloaded = (int)($post_data['downloaded'] ?? 0);
        $confirm = trim($post_data['confirm'] ?? '');
        $avatar_url = trim($post_data['avatar_url'] ?? '');
       

        // Валидации
        $validations = [
            $this->validateUsername($username),
            $this->validateEmail($email),
            $this->validatePassword($password, $password2, $username),
            $this->validateUsergroup($usergroup)
        ];

        $avatar_data = $this->validateAvatar($avatar_url);

        if (in_array(false, $validations, true) || !empty($this->errors)) {
            return false;
        }

        // Подготовка данных пользователя
        $user = [];
        $user['loginkey'] = generate_loginkey();
        $password_fields = create_password($password, false, $user);
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
            $db->escape_string($username),
            $user['password'],
            $user['salt'],
            $user['loginkey'],
            TIMENOW,
            'confirmed',
            $db->escape_string($email),
            $usergroup,
            $additionalgroups,
            $db->escape_string(gmdate('Y-m-d') . ' - ' . $modcomment),
            $seedbonus,
            $invites,
            $uploaded,
            $downloaded,
            '2',
            $avatar_data['url'],
            $avatar_data['dimensions'],
            "remote",
            '1',
            '1',
            '1',
            '1',
            '',
            '',
            "0**$%%$1**$%%$2**$%%$3**$%%$4**"
        ];

        $placeholders = str_repeat('?,', count($user_insert_data) - 1) . '?';
        $sql = "INSERT INTO users (username, password, salt, loginkey, added, ustatus, email, usergroup, 
                additionalgroups, modcomment, seedbonus, invites, uploaded, downloaded, timezone, avatar, 
                avatardimensions, avatartype, invisible, showsigs, showavatars, showredirect, ignorelist, 
                buddylist, pmfolders) VALUES ({$placeholders})";

        $result = $db->sql_query_prepared($sql, $user_insert_data);

        if (!$result || $db->affected_rows() === 0) {
            $this->errors[] = $lang->global['error'];
            return false;
        }

        $user_id = $db->insert_id();


        // User fields
        $pfcache = $cache->read('profilefields');
        $user_fields = ['ufid' => $user_id];

        if (is_array($pfcache)) {
            foreach ($pfcache as $profile_field) {
                $user_fields["fid{$profile_field['fid']}"] = '';
            }
        }

        $field_placeholders = '?' . str_repeat(',?', count($user_fields) - 1);
        $field_values = array_values($user_fields);
        
        $db->sql_query_prepared(
            "INSERT INTO userfields VALUES ({$field_placeholders})",
            $field_values
        );

        // Обновление статистики
        update_stats(['numusers' => '+1']);

        // Приветственное сообщение
        require_once INC_PATH . '/functions_pm.php';
        
        $pm = [
            'subject' => sprintf($lang->signup['welcomepmsubject'], $SITENAME),
            'message' => sprintf($lang->signup['welcomepmbody'], htmlspecialchars_uni($username), $SITENAME, $BASEURL),
            'touid' => $user_id
        ];
        
        $pm['sender']['uid'] = -1;
        send_pm($pm, -1, true);

        // Подтверждение email
        if ($confirm === 'yes') {
            $editsecret = mksecret();
            $db->sql_query_prepared(
                "REPLACE INTO ts_user_validation (editsecret, userid) VALUES (?, ?)",
                [$editsecret, $user_id]
            );
            
            $psecret = md5($editsecret);
            $body = sprintf($lang->signup['verifiyemailbody'], $username, $BASEURL, $user_id, $psecret, $SITENAME);
            sent_mail($email, sprintf($lang->signup['verifiyemailsubject'], $SITENAME), $body, 'signup', false);
        }

        // Логирование
        write_log('New Account Created by ' . $CURUSER['username'] . '. Account Name: ' . htmlspecialchars_uni($username));

        $this->user_data = [
            'id' => $user_id,
            'username' => $username,
            'confirm' => $confirm
        ];

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
    if ($registration_handler->processRegistration($_POST)) {
        $user_data = $registration_handler->getUserData();
        $redirect_url = $BASEURL . '/' . ($user_data['confirm'] === 'yes' ? 'checkuser' : 'member') . 
                       '.php?action=profile&id=' . $user_data['id'];
        redirect($redirect_url);
        exit();
    }
}

// Отображение формы
stdhead($lang->adduser['title']);

$errors = $registration_handler->getErrors();
if (!empty($errors)) {
   
    echo '
   
   <link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
   <link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">


   <div class="container mt-3">
   <div class="card error-card">
      <div class="card-header22">
        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
        <div>
          <h2 class="mb-0">Detect Errors</h2>
          <p class="mb-0 opacity-75"></p>
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-danger" role="alert">
          ' . implode ('<br />', $errors) . '
        </div>
      </div>
    </div>
	</div>
	
	<br>';
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
        
        <form method="POST" action="' . htmlspecialchars($_SERVER['REQUEST_URI']) . '" class="needs-validation" novalidate>
            <input type="hidden" name="act" value="adduser">
            
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
                            <select class="form-select" id="input_usergroup" name="usergroup" required>
                                <option value="">Choose usergroup...</option>';
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
                <div class="row g-3 mb-4">
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
                            <div class="form-text text-muted">
                                <small>' . $minpasswordlength . '-' . $maxpasswordlength . ' characters' . 
                                ($requirecomplexpasswords ? ', must contain letters and numbers' : '') . '</small>
                            </div>
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
                                   value="' . htmlspecialchars_uni($_POST['seedbonus'] ?? '0') . '"
                                   placeholder="Seed Bonus">
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
                                   value="' . htmlspecialchars_uni($_POST['uploaded'] ?? '0') . '"
                                   placeholder="Uploaded">
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
                    <div class="form-floating">
                        <input type="url" 
                               class="form-control" 
                               id="input_avatar" 
                               name="avatar_url" 
                               value="' . htmlspecialchars_uni($_POST['avatar_url'] ?? '') . '" 
                               placeholder="Avatar URL">
                        <label for="input_avatar" class="form-label">
                            <i class="fas fa-image me-2 text-primary"></i>Avatar URL
                        </label>
                        <div class="form-text">Optional: URL to user avatar image</div>
                    </div>
                </div>

                <!-- Options -->
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

stdfoot();
?>