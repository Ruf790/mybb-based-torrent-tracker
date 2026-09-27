<?php

declare(strict_types=1);

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_upload.php';


if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

define('AU_VERSION', '2.0');



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

        if (mb_strlen($username) < $minnamelength) {
            $this->errors[] = "Username must be at least {$minnamelength} characters long";
            return false;
        }

        if (mb_strlen($username) > $maxnamelength) {
            $this->errors[] = "Username cannot be longer than {$maxnamelength} characters";
            return false;
        }

        // Раньше — только [a-zA-Z0-9]: ники с «_», «-», «.», кириллицей и т.п.
        // отклонялись, хотя обычная регистрация их принимает
        if (preg_match('/[<>&"\'\\\\\x00-\x1F\x7F]/u', $username)) {
            $this->errors[] = "Username contains characters that are not allowed";
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
            "SELECT email FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1",
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

        // Быстрая ранняя отбраковка очевидно небезопасных URL до похода в
        // сеть. Настоящая гарантия безопасности — ниже, в fetch_remote_file(),
        // которая резолвит хост и "прикалывает" соединение к уже
        // провалидированному IP (защита от DNS rebinding между проверкой
        // и реальным запросом — getimagesize($url) на сырой ссылке этой
        // защиты не даёт, PHP резолвит DNS заново в момент запроса).
        if (!$this->isUrlSafeForFetch($avatar_url)) {
            $this->errors[] = "Invalid avatar URL or image not accessible";
            return $avatar_data;
        }

        require_once INC_PATH . '/functions_remote_connect.php';
        $data = fetch_remote_file($avatar_url);
        if ($data === false) {
            $this->errors[] = "Invalid avatar URL or image not accessible";
            return $avatar_data;
        }

        $image_info = @getimagesizefromstring($data);
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

    // Вся валидация (расширение, реальный MIME через getimagesize(),
    // размер по настройке avatarsize), сохранение и перекодирование через
    // GD (защита от "полиглот"-файлов) теперь делает upload_avatar() —
    // та же функция, что использует usercp.php/member.php/usersearch.php.
    // Раньше здесь была ещё одна отдельная копия той же логики без
    // перекодирования и с захардкоженным лимитом 500KB.
    $result = upload_avatar($_FILES['avatar_file'], $user_id);

    if (!empty($result['error'])) {
        $this->errors[] = $result['error'];
        return $avatar_data;
    }

    $ext      = pathinfo($result['avatar'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user_id . '.' . $ext;

    return [
        'url'        => $BASEURL . '/uploads/avatars/' . $filename,
        'dimensions' => (int)$result['width'] . '|' . (int)$result['height'],
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

        // Единицы измерения трафика (раньше — только байты)
        $unitMul = static fn(string $u): int => match (strtoupper($u)) {
            'TB' => 1024 ** 4, 'GB' => 1024 ** 3, 'MB' => 1024 ** 2, default => 1,
        };
        $uploaded_raw = trim((string)($post_data['uploaded'] ?? ''));
        $uploaded = $uploaded_raw !== ''
            ? (int)round(max(0.0, (float)$uploaded_raw) * $unitMul((string)($post_data['uploaded_unit'] ?? 'B')))
            : ($autogigsignup > 0 ? (int)$autogigsignup * 1024 * 1024 * 1024 : 0);

        $downloaded = (int)round(max(0.0, (float)($post_data['downloaded'] ?? 0)) * $unitMul((string)($post_data['downloaded_unit'] ?? 'B')));
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

// ── AJAX: занят ли ник / email (живая проверка в форме) ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['check_name']) || isset($_GET['check_email']))) {
    header('Content-Type: application/json; charset=utf-8');
    if (isset($_GET['check_name'])) {
        $v = trim((string)$_GET['check_name']);
        $q = $db->sql_query_prepared('SELECT id FROM users WHERE username = ? LIMIT 1', [$v]);
        $taken = $q && $db->num_rows($q) > 0;
        echo json_encode(['taken' => $taken, 'banned' => !$taken && $v !== '' && is_banned_username($v, true)]);
    } else {
        $v = trim((string)$_GET['check_email']);
        $valid = (bool)filter_var($v, FILTER_VALIDATE_EMAIL);
        $q = $valid ? $db->sql_query_prepared('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1', [$v]) : null;
        echo json_encode(['valid' => $valid, 'taken' => $q && $db->num_rows($q) > 0, 'banned' => $valid && is_banned_email($v, true)]);
    }
    exit;
}

// Обработка формы
$registration_handler = new UserRegistrationHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post_check($_POST['my_post_key'] ?? '');

    if ($registration_handler->processRegistration($_POST)) {
        $user_data       = $registration_handler->getUserData();
        $post_create_err = $registration_handler->getErrors();
        $profile_url     = $BASEURL . '/' . get_profile_link($user_data['id']);

        // Аккаунт создан; если были некритичные ошибки (аватар, письмо) — показываем их здесь
        if (empty($post_create_err)) {
            redirect($profile_url);
            exit();
        }

        stdhead($lang->adduser['title']);
        au_styles();
        echo '<div class="container mt-3 mb-4 au" style="max-width:720px"><div class="au-card au-done">'
           . '<span class="au-done-icon"><i class="fa-solid fa-user-check"></i></span>'
           . '<h2 class="h4 fw-bold mb-1">Account created</h2>'
           . '<div class="text-body-secondary mb-3"><strong>' . htmlspecialchars_uni($user_data['username']) . '</strong> can log in now, but something needs your attention:</div>'
           . '<div class="alert alert-warning text-start rounded-4"><ul class="mb-0 ps-3">';
        foreach ($post_create_err as $e) echo '<li>' . htmlspecialchars_uni((string)$e) . '</li>';
        echo '</ul></div>'
           . '<div class="d-flex flex-wrap justify-content-center gap-2">'
           . '<a href="' . htmlspecialchars($_SERVER['REQUEST_URI']) . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-user-plus me-1"></i>Add another</a>'
           . '<a href="' . $profile_url . '" class="btn btn-primary px-3"><i class="fa-solid fa-arrow-right me-1"></i>Go to profile</a>'
           . '</div></div></div>';
        stdfoot();
        exit();
    }
}

// ═══════════════════════════════════════════════════════════
// ФОРМА
// ═══════════════════════════════════════════════════════════

// Раньше стили задавали .card, .card-header, .btn-primary (с transform), .form-label
// и .text-danger для ВСЕЙ страницы, включая шапку сайта
function au_styles(): void
{
    global $BASEURL;
	echo '<link rel="stylesheet" href="' . htmlspecialchars($BASEURL, ENT_QUOTES, 'UTF-8')
       . '/admin/templates/add_user.css?ver=336">';
}




stdhead($lang->adduser['title']);
au_styles();

$errors         = $registration_handler->getErrors();
$allowed_groups = $registration_handler->getAllowedUsergroups();
$p              = static fn(string $k, string $d = ''): string => htmlspecialchars_uni((string)($_POST[$k] ?? $d));
$defaultGroup   = (int)($_d_usergroup ?: 2);
$defaultUpGb    = (int)($autogigsignup ?? 0);
$defaultBonus   = (int)($autosbsignup ?? 0);
$self           = htmlspecialchars($_SERVER['REQUEST_URI']);
$unit           = static fn(string $name, string $sel) => '<select class="form-select" name="' . $name . '" style="max-width:90px">'
    . implode('', array_map(fn($u) => '<option value="' . $u . '"' . ($sel === $u ? ' selected' : '') . '>' . $u . '</option>', ['GB', 'MB', 'TB', 'B']))
    . '</select>';
?>
<div class="container mt-3 mb-4 au">

    <div class="au-card mb-3"><div class="au-head">
        <span class="au-head-icon"><i class="fa-solid fa-user-plus"></i></span>
        <div style="min-width:0">
            <h1 class="au-title"><?= htmlspecialchars_uni($lang->adduser['title']) ?></h1>
            <div class="au-sub">Create an account by hand — it is confirmed immediately unless you ask for email activation</div>
        </div>
        <span class="ms-auto au-muted"><i class="fa-solid fa-code-branch me-1"></i>v<?= htmlspecialchars(AU_VERSION) ?></span>
    </div></div>

    <?php if ($errors): ?>
    <div class="alert alert-danger d-flex gap-2 rounded-4"><i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <div><div class="fw-semibold mb-1">The account was not created:</div><ul class="mb-0 ps-3">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars_uni((string)$e) ?></li><?php endforeach; ?>
        </ul></div></div>
    <?php endif; ?>

    <form method="POST" action="<?= $self ?>" class="needs-validation" novalidate enctype="multipart/form-data" id="auForm">
        <input type="hidden" name="act" value="adduser">
        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="au-card">

                    <!-- Аккаунт -->
                    <div class="au-sec" style="border-top:0">
                        <div class="au-sec-head"><span class="au-sec-icon ic-blue"><i class="fa-solid fa-id-card"></i></span>Account</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="input_username"><i class="fa-solid fa-user"></i>Username <span class="au-req">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                    <input type="text" class="form-control" id="input_username" name="username" value="<?= $p('username') ?>"
                                           required minlength="<?= (int)$minnamelength ?>" maxlength="<?= (int)$maxnamelength ?>" autocomplete="off" placeholder="New username">
                                </div>
                                <div class="au-hint" id="hint_username"><?= (int)$minnamelength ?>–<?= (int)$maxnamelength ?> characters</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_email"><i class="fa-solid fa-envelope"></i>Email <span class="au-req">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                    <input type="email" class="form-control" id="input_email" name="email" value="<?= $p('email') ?>" required autocomplete="off" placeholder="user@example.com">
                                </div>
                                <div class="au-hint" id="hint_email">Used for login, notifications and activation</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_usergroup"><i class="fa-solid fa-users"></i>User group <span class="au-opt">(optional)</span></label>
                                <select class="form-select" id="input_usergroup" name="usergroup">
                                    <option value="">Default — <?= htmlspecialchars_uni($allowed_groups[$defaultGroup] ?? 'registration group') ?></option>
                                    <?php foreach ($allowed_groups as $gid => $title): ?>
                                    <option value="<?= (int)$gid ?>" <?= (string)($_POST['usergroup'] ?? '') === (string)$gid ? 'selected' : '' ?>><?= htmlspecialchars_uni($title) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="au-hint">Staff groups can't be assigned here</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_modcomment"><i class="fa-solid fa-comment-dots"></i>Moderator note <span class="au-opt">(optional)</span></label>
                                <input type="text" class="form-control" id="input_modcomment" name="modcomment" value="<?= $p('modcomment') ?>" maxlength="255" placeholder="e.g. Invited by forum post #123">
                                <div class="au-hint">Stored in the user's mod comment with today's date</div>
                            </div>
                        </div>
                    </div>

                    <!-- Пароль -->
                    <div class="au-sec">
                        <div class="au-sec-head"><span class="au-sec-icon ic-purple"><i class="fa-solid fa-key"></i></span>Password
                            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" onclick="generatePassword()"><i class="fa-solid fa-dice me-1"></i>Generate</button></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="input_password"><i class="fa-solid fa-lock"></i>Password <span class="au-req">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="input_password" name="password" required
                                           minlength="<?= (int)$minpasswordlength ?>" maxlength="<?= (int)$maxpasswordlength ?>" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" data-toggle-pw="input_password" aria-label="Show password" style="border-radius:0 .7rem .7rem 0"><i class="fa-solid fa-eye"></i></button>
                                </div>
                                <div class="au-strength"><span id="au_strength"></span></div>
                                <div class="au-hint" id="hint_password"><?= (int)$minpasswordlength ?>–<?= (int)$maxpasswordlength ?> characters<?= $requirecomplexpasswords ? ', letters and numbers' : '' ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_password2"><i class="fa-solid fa-lock"></i>Confirm <span class="au-req">*</span></label>
                                <input type="password" class="form-control" id="input_password2" name="password2" required
                                       minlength="<?= (int)$minpasswordlength ?>" maxlength="<?= (int)$maxpasswordlength ?>" autocomplete="new-password">
                                <div class="au-hint" id="hint_password2"></div>
                            </div>
                        </div>
                        <div class="au-genbox" id="generated_password_box" style="display:none">
                            <span><i class="fa-solid fa-circle-check text-success me-2"></i>Generated: <code id="generated_password_text"></code></span>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="copyGeneratedPassword()"><i class="fa-regular fa-copy me-1"></i>Copy</button>
                        </div>
                    </div>

                    <!-- Трафик и бонусы -->
                    <div class="au-sec">
                        <div class="au-sec-head"><span class="au-sec-icon ic-teal"><i class="fa-solid fa-arrow-right-arrow-left"></i></span>Traffic &amp; bonus</div>
                        <div class="row g-3">
                            <!-- Раньше трафик вводился только в БАЙТАХ: 10 GB = 10737418240 -->
                            <div class="col-md-6">
                                <label class="form-label" for="input_uploaded"><i class="fa-solid fa-upload"></i>Uploaded</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="input_uploaded" name="uploaded" min="0" step="any" value="<?= $p('uploaded') ?>" placeholder="<?= $defaultUpGb > 0 ? 'Default: ' . $defaultUpGb . ' GB' : '0' ?>">
                                    <?= $unit('uploaded_unit', (string)($_POST['uploaded_unit'] ?? 'GB')) ?>
                                </div>
                                <div class="au-hint">Empty = site signup bonus<?= $defaultUpGb > 0 ? ' (' . $defaultUpGb . ' GB)' : '' ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_downloaded"><i class="fa-solid fa-download"></i>Downloaded</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="input_downloaded" name="downloaded" min="0" step="any" value="<?= $p('downloaded', '0') ?>">
                                    <?= $unit('downloaded_unit', (string)($_POST['downloaded_unit'] ?? 'GB')) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_seedbonus"><i class="fa-solid fa-coins"></i>Seed bonus</label>
                                <input type="number" class="form-control" id="input_seedbonus" name="seedbonus" min="0" value="<?= $p('seedbonus') ?>" placeholder="<?= $defaultBonus > 0 ? 'Default: ' . $defaultBonus : '0' ?>">
                                <div class="au-hint">Empty = site signup bonus</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_invites"><i class="fa-solid fa-ticket"></i>Invites</label>
                                <input type="number" class="form-control" id="input_invites" name="invites" min="0" value="<?= $p('invites', '0') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Аватар -->
                    <div class="au-sec">
                        <div class="au-sec-head"><span class="au-sec-icon ic-amber"><i class="fa-solid fa-image"></i></span>Avatar <span class="au-opt ms-1">(optional)</span></div>
                        <ul class="nav au-seg" id="avatarTabs">
                            <li class="nav-item"><button class="nav-link active" id="tab-url" type="button" onclick="switchAvatarTab('url')"><i class="fa-solid fa-link me-1"></i>URL</button></li>
                            <li class="nav-item"><button class="nav-link" id="tab-file" type="button" onclick="switchAvatarTab('file')"><i class="fa-solid fa-upload me-1"></i>Upload</button></li>
                        </ul>
                        <div id="avatar-panel-url">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-link"></i></span>
                                <input type="url" class="form-control" id="input_avatar" name="avatar_url" value="<?= $p('avatar_url') ?>" placeholder="https://example.com/avatar.jpg">
                            </div>
                            <div class="au-hint">Direct link to a JPG, PNG, GIF or WEBP image</div>
                        </div>
                        <div id="avatar-panel-file" style="display:none">
                            <div id="avatar-dropzone"
                                 ondragover="event.preventDefault();this.classList.add('border-primary')"
                                 ondragleave="this.classList.remove('border-primary')"
                                 ondrop="handleAvatarDrop(event)">
                                <i class="fa-solid fa-cloud-arrow-up fa-2x text-body-secondary mb-2"></i>
                                <p class="mb-2 text-body-secondary">Drag &amp; drop an image here or</p>
                                <label class="btn btn-outline-primary btn-sm mb-0" for="input_avatar_file"><i class="fa-solid fa-folder-open me-1"></i>Choose file</label>
                                <input type="file" class="d-none" id="input_avatar_file" name="avatar_file" accept="image/jpeg,image/png,image/gif,image/webp">
                                <!-- Раньше было «max 500KB», хотя лимит берётся из настроек аватаров -->
                                <p class="au-muted mt-2 mb-0">JPG, PNG, GIF, WEBP — size limit from the avatar settings</p>
                                <p id="avatar-filename" class="text-success mt-1 mb-0 fw-semibold small" style="display:none"></p>
                            </div>
                        </div>
                        <div id="avatar_preview" hidden>
                            <img id="avatar_preview_img" src="" alt="" hidden>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="clearAvatar()"><i class="fa-solid fa-xmark me-1"></i>Remove avatar</button>
                        </div>
                    </div>

                    <!-- Опции -->
                    <div class="au-sec">
                        <div class="au-sec-head"><span class="au-sec-icon ic-green"><i class="fa-solid fa-sliders"></i></span>Options</div>
                        <label class="au-switch" for="sendcredentials">
                            <span class="au-sec-icon ic-purple"><i class="fa-solid fa-paper-plane"></i></span>
                            <span><span class="fw-semibold d-block"><?= htmlspecialchars_uni($lang->adduser['sendcredentials']) ?></span><span class="au-muted">The username and password are emailed to the user</span></span>
                            <input type="checkbox" class="form-check-input" role="switch" name="sendcredentials" id="sendcredentials" value="yes" <?= (($_POST['sendcredentials'] ?? 'yes') === 'yes') ? 'checked' : '' ?>>
                        </label>
                        <label class="au-switch" for="confirm">
                            <span class="au-sec-icon ic-amber"><i class="fa-solid fa-envelope-circle-check"></i></span>
                            <span><span class="fw-semibold d-block"><?= htmlspecialchars_uni($lang->adduser['o1']) ?></span><span class="au-muted">Account stays “pending” until the user clicks the activation link</span></span>
                            <input type="checkbox" class="form-check-input" role="switch" name="confirm" id="confirm" value="yes" <?= (($_POST['confirm'] ?? '') === 'yes') ? 'checked' : '' ?>>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Превью будущего аккаунта -->
            <div class="col-lg-4">
                <div class="au-card au-preview">
                    <div class="au-pv-top">
                        <div class="au-pv-avatar" id="pvAvatar">?</div>
                        <div class="au-pv-name" id="pvName">New user</div>
                        <div class="au-muted" id="pvEmail">no email yet</div>
                    </div>
                    <div class="au-pv-rows">
                        <div class="au-pv-row"><span><i class="fa-solid fa-users"></i>Group</span><b id="pvGroup"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-upload"></i>Uploaded</span><b id="pvUp"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-download"></i>Downloaded</span><b id="pvDown"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-scale-balanced"></i>Ratio</span><b id="pvRatio"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-coins"></i>Bonus</span><b id="pvBonus"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-ticket"></i>Invites</span><b id="pvInv"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-circle-check"></i>Status</span><b id="pvStatus"></b></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="au-card au-savebar">
            <span class="au-muted"><i class="fa-solid fa-circle-info me-1"></i>A welcome PM is sent automatically</span>
            <button type="submit" class="btn btn-success px-4" id="auSubmit"><i class="fa-solid fa-user-plus me-1"></i><?= htmlspecialchars_uni($lang->adduser['title']) ?></button>
        </div>
    </form>
</div>

<script>
(function () {
    'use strict';
    const cfg = {
        minName: <?= (int)$minnamelength ?>, maxName: <?= (int)$maxnamelength ?>,
        minPw: <?= (int)$minpasswordlength ?>, maxPw: <?= (int)$maxpasswordlength ?>,
        complex: <?= $requirecomplexpasswords ? 'true' : 'false' ?>,
        defUpGb: <?= $defaultUpGb ?>, defBonus: <?= $defaultBonus ?>,
        self: <?= json_encode(html_entity_decode($self)) ?>,
    };
    const $ = id => document.getElementById(id);
    const sep = cfg.self.includes('?') ? '&' : '?';
    const units = { B: 1, MB: 1024 ** 2, GB: 1024 ** 3, TB: 1024 ** 4 };
    const fmt = b => { const u = ['B','KB','MB','GB','TB','PB']; let i = 0; while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; } return (i ? b.toFixed(2) : b) + ' ' + u[i]; };
    const hint = (id, text, cls) => { const h = $(id); if (h) { h.className = 'au-hint' + (cls ? ' ' + cls : ''); h.innerHTML = text; } };

    // ── Bootstrap-валидация ─────────────────────────────────
    const form = $('auForm');
    form.addEventListener('submit', e => {
        if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); form.classList.add('was-validated'); return; }
        const b = $('auSubmit'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating…';
    });

    // ── Ник: длина + живая проверка занятости ───────────────
    // Раньше разрешались только a-z и цифры (и в браузере, и на сервере)
    let tName, tMail;
    $('input_username').addEventListener('input', function () {
        const v = this.value.trim();
        const len = [...v].length;
        if (!v) { this.setCustomValidity(''); hint('hint_username', cfg.minName + '–' + cfg.maxName + ' characters'); update(); return; }
        if (len < cfg.minName || len > cfg.maxName) {
            this.setCustomValidity('length'); hint('hint_username', '<i class="fa-solid fa-circle-xmark me-1"></i>' + cfg.minName + '–' + cfg.maxName + ' characters', 'bad'); update(); return;
        }
        if (/[<>&"'\\\x00-\x1f]/.test(v)) {
            this.setCustomValidity('chars'); hint('hint_username', '<i class="fa-solid fa-circle-xmark me-1"></i>Characters &lt; &gt; &amp; " \' \\ are not allowed', 'bad'); update(); return;
        }
        this.setCustomValidity('');
        clearTimeout(tName);
        tName = setTimeout(() => fetch(cfg.self + sep + 'check_name=' + encodeURIComponent(v), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if ($('input_username').value.trim() !== v) return;
                if (d.taken)       { $('input_username').setCustomValidity('taken');  hint('hint_username', '<i class="fa-solid fa-circle-xmark me-1"></i>Already taken', 'bad'); }
                else if (d.banned) { $('input_username').setCustomValidity('banned'); hint('hint_username', '<i class="fa-solid fa-ban me-1"></i>Disallowed by a ban filter', 'bad'); }
                else hint('hint_username', '<i class="fa-solid fa-circle-check me-1"></i>Available', 'ok');
            }).catch(() => {}), 300);
        update();
    });

    // ── Email: формат + занятость + бан ─────────────────────
    $('input_email').addEventListener('input', function () {
        const v = this.value.trim();
        update();
        if (!v) { hint('hint_email', 'Used for login, notifications and activation'); return; }
        clearTimeout(tMail);
        tMail = setTimeout(() => fetch(cfg.self + sep + 'check_email=' + encodeURIComponent(v), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if ($('input_email').value.trim() !== v) return;
                const bad = !d.valid || d.taken || d.banned;
                $('input_email').setCustomValidity(bad ? 'bad' : '');
                hint('hint_email', !d.valid ? '<i class="fa-solid fa-circle-xmark me-1"></i>Not a valid address'
                    : d.taken  ? '<i class="fa-solid fa-circle-xmark me-1"></i>Used by another account'
                    : d.banned ? '<i class="fa-solid fa-ban me-1"></i>Disallowed by a ban filter'
                    : '<i class="fa-solid fa-circle-check me-1"></i>Available', bad ? 'bad' : 'ok');
            }).catch(() => {}), 350);
    });

    // ── Пароль: правила + индикатор надёжности ──────────────
    function checkPw() {
        const p = $('input_password'), p2 = $('input_password2'), v = p.value;
        let err = '';
        if (v.length < cfg.minPw) err = 'At least ' + cfg.minPw + ' characters';
        else if (v.length > cfg.maxPw) err = 'At most ' + cfg.maxPw + ' characters';
        else if (cfg.complex && (!/[a-zA-Z]/.test(v) || !/[0-9]/.test(v))) err = 'Needs letters and numbers';
        else if (v && v === $('input_username').value.trim()) err = 'Must differ from the username';
        p.setCustomValidity(err);

        let score = 0;
        if (v.length >= 8) score++; if (v.length >= 12) score++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++; if (/\d/.test(v)) score++; if (/[^A-Za-z0-9]/.test(v)) score++;
        const bar = $('au_strength'), colors = ['#ef4444', '#ef4444', '#f59e0b', '#eab308', '#22c55e', '#16a34a'];
        bar.style.width = v ? (Math.max(1, score) / 5 * 100) + '%' : '0';
        bar.style.backgroundColor = colors[score];
        hint('hint_password', v ? (err ? '<i class="fa-solid fa-circle-xmark me-1"></i>' + err : ['Very weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very strong'][score]) : cfg.minPw + '–' + cfg.maxPw + ' characters' + (cfg.complex ? ', letters and numbers' : ''), v ? (err ? 'bad' : (score >= 3 ? 'ok' : '')) : '');

        const match = p2.value === v;
        p2.setCustomValidity(match ? '' : 'mismatch');
        hint('hint_password2', p2.value ? (match ? '<i class="fa-solid fa-circle-check me-1"></i>Passwords match' : '<i class="fa-solid fa-circle-xmark me-1"></i>Passwords do not match') : '', p2.value ? (match ? 'ok' : 'bad') : '');
    }
    $('input_password').addEventListener('input', checkPw);
    $('input_password2').addEventListener('input', checkPw);
    document.querySelectorAll('[data-toggle-pw]').forEach(b => b.addEventListener('click', () => {
        const f = $(b.dataset.togglePw), show = f.type === 'password';
        f.type = show ? 'text' : 'password'; $('input_password2').type = f.type;
        b.innerHTML = '<i class="fa-solid ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
    }));

    // Генерация пароля — криптостойкий генератор вместо Math.random()
    window.generatePassword = function () {
        const letters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ', digits = '23456789', symbols = '!@#$%^&*';
        const all = letters + digits + symbols;
        const len = Math.min(cfg.maxPw, Math.max(cfg.minPw, 14));
        const rnd = n => { const a = new Uint32Array(1); crypto.getRandomValues(a); return a[0] % n; };
        const chars = [letters[rnd(letters.length)], digits[rnd(digits.length)], symbols[rnd(symbols.length)]];
        while (chars.length < len) chars.push(all[rnd(all.length)]);
        for (let i = chars.length - 1; i > 0; i--) { const j = rnd(i + 1); [chars[i], chars[j]] = [chars[j], chars[i]]; }
        const pw = chars.join('');
        $('input_password').value = pw; $('input_password2').value = pw; checkPw();
        $('generated_password_text').textContent = pw;
        $('generated_password_box').style.display = '';
    };
    window.copyGeneratedPassword = function () {
        const t = $('generated_password_text').textContent;
        if (t) navigator.clipboard?.writeText(t).then(() => { if (typeof showToast === 'function') showToast('Password copied to clipboard', 'success'); });
    };

    // ── Аватар ──────────────────────────────────────────────
    window.switchAvatarTab = function (tab) {
        $('avatar-panel-url').style.display  = tab === 'url'  ? '' : 'none';
        $('avatar-panel-file').style.display = tab === 'file' ? '' : 'none';
        $('tab-url').classList.toggle('active',  tab === 'url');
        $('tab-file').classList.toggle('active', tab === 'file');
    };
    function showAvatar(src) {
        const box = $('pvAvatar');
        box.replaceChildren();
        if (src) {
            const img = document.createElement('img'); img.alt = ''; img.src = src;
            img.onerror = () => { box.textContent = initial(); };
            box.appendChild(img);
            $('avatar_preview').hidden = false;
        } else {
            box.textContent = initial();
            $('avatar_preview').hidden = true;
        }
    }
    const initial = () => ([...$('input_username').value.trim()][0] || '?').toUpperCase();
    window.clearAvatar = function () {
        $('input_avatar').value = ''; $('input_avatar_file').value = '';
        $('avatar-filename').style.display = 'none';
        showAvatar('');
    };
    window.handleAvatarDrop = function (e) {
        e.preventDefault();
        $('avatar-dropzone').classList.remove('border-primary');
        if (e.dataTransfer.files[0]) setAvatarFile(e.dataTransfer.files[0]);
    };
    function setAvatarFile(file) {
        const dt = new DataTransfer(); dt.items.add(file);
        $('input_avatar_file').files = dt.files;
        $('avatar-filename').textContent = file.name; $('avatar-filename').style.display = 'block';
        const r = new FileReader(); r.onload = e => showAvatar(e.target.result); r.readAsDataURL(file);
    }
    $('input_avatar_file').addEventListener('change', function () { if (this.files[0]) setAvatarFile(this.files[0]); });
    $('input_avatar').addEventListener('input', function () { showAvatar(/^https?:\/\//i.test(this.value) ? this.value : ''); });

    // ── Карточка-превью ─────────────────────────────────────
    function bytes(field, unitName, def) {
        const raw = $(field).value.trim();
        const u = document.querySelector('[name="' + unitName + '"]').value;
        return raw === '' ? def : Math.max(0, parseFloat(raw) || 0) * units[u];
    }
    function update() {
        const name = $('input_username').value.trim();
        $('pvName').textContent = name || 'New user';
        $('pvEmail').textContent = $('input_email').value.trim() || 'no email yet';
        if (!$('pvAvatar').querySelector('img')) $('pvAvatar').textContent = initial();
        const g = $('input_usergroup');
        $('pvGroup').textContent = g.options[g.selectedIndex].text.replace(/^Default — /, '');
        const up = bytes('input_uploaded', 'uploaded_unit', cfg.defUpGb * units.GB);
        const down = bytes('input_downloaded', 'downloaded_unit', 0);
        $('pvUp').textContent = fmt(up); $('pvDown').textContent = fmt(down);
        $('pvRatio').textContent = down > 0 ? (up / down).toFixed(2) : '∞';
        const sb = $('input_seedbonus').value.trim();
        $('pvBonus').textContent = (sb === '' ? cfg.defBonus : parseInt(sb, 10) || 0).toLocaleString();
        $('pvInv').textContent = (parseInt($('input_invites').value, 10) || 0).toLocaleString();
        $('pvStatus').innerHTML = $('confirm').checked
            ? '<span class="text-warning"><i class="fa-solid fa-hourglass-half me-1"></i>Pending activation</span>'
            : '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Confirmed</span>';
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);

    // Начальное состояние (после ошибки поля уже заполнены)
    if ($('input_avatar').value) showAvatar($('input_avatar').value);
    if ($('input_username').value) $('input_username').dispatchEvent(new Event('input'));
    if ($('input_email').value) $('input_email').dispatchEvent(new Event('input'));
    update();
})();
</script>
<?php

stdfoot();