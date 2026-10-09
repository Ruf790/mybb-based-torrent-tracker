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

// Подстановка {1}, {2}… в строки ланга. $lang->load() превращает {1} в %1$s,
// поэтому заменяем оба формата. strtr за один проход — подставленное значение
// с «{2}» внутри повторно не раскрывается.
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach (array_values($args) as $i => $a) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$a;
            $map['%' . $n . '$s'] = (string)$a;
        }
        return $map ? strtr($str, $map) : $str;
    }
}




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
            $this->errors[] = ags_fmt($lang->adduser['err_name_short'], (int)$minnamelength);
            return false;
        }

        if (mb_strlen($username) > $maxnamelength) {
            $this->errors[] = ags_fmt($lang->adduser['err_name_long'], (int)$maxnamelength);
            return false;
        }

        // Раньше — только [a-zA-Z0-9]: ники с «_», «-», «.», кириллицей и т.п.
        // отклонялись, хотя обычная регистрация их принимает
        if (preg_match('/[<>&"\'\\\\\x00-\x1F\x7F]/u', $username)) {
            $this->errors[] = $lang->adduser['err_name_chars'];
            return false;
        }

        // Проверка существования username с подготовленным запросом
        $query = $db->sql_query_prepared(
            "SELECT username FROM users WHERE username = ? LIMIT 1",
            [$username]
        );
        
        if ($db->num_rows($query) > 0) {
            $this->errors[] = $lang->adduser['err_name_taken'];
            return false;
        }

        // Проверка запрещённых имён (те же wildcard-фильтры banfilters, что и при обычной регистрации)
        if (is_banned_username($username, true)) {
            $this->errors[] = $lang->adduser['err_name_banned'];
            return false;
        }
        

        return true;
    }

    private function validateEmail(string $email): bool
    {
        global $lang, $db;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = $lang->adduser['err_email_invalid'];
            return false;
        }

        // Проверка бана email с подготовленным запросом
        if (is_banned_email($email, true)) {
            $this->errors[] = $lang->adduser['err_email_banned'];
            return false;
        }

        // Проверка существования email с подготовленным запросом
        $query = $db->sql_query_prepared(
            "SELECT email FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1",
            [$email]
        );
        
        if ($db->num_rows($query) > 0) {
            $this->errors[] = $lang->adduser['err_email_taken'];
            return false;
        }

        return true;
    }

    private function validatePassword(string $password, string $confirm_password, string $username): bool
    {
        global $lang, $minpasswordlength, $maxpasswordlength, $requirecomplexpasswords;

        if ($password !== $confirm_password) {
            $this->errors[] = $lang->adduser['err_pw_mismatch'];
            return false;
        }

        if (strlen($password) < $minpasswordlength) {
            $this->errors[] = ags_fmt($lang->adduser['err_pw_short'], (int)$minpasswordlength);
            return false;
        }

        if (strlen($password) > $maxpasswordlength) {
            $this->errors[] = ags_fmt($lang->adduser['err_pw_long'], (int)$maxpasswordlength);
            return false;
        }

        if ($password === $username) {
            $this->errors[] = $lang->adduser['err_pw_same'];
            return false;
        }

        // Проверка сложности пароля если требуется
        if ($requirecomplexpasswords && !$this->checkPasswordStrength($password)) {
            $this->errors[] = $lang->adduser['err_pw_complex'];
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
            $this->errors[] = $lang->adduser['err_usergroup'];
            return false;
        }

        return true;
    }

    private function validateAvatar(string $avatar_url): array
    {
        global $lang;

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
            $this->errors[] = $lang->adduser['err_avatar_invalid'];
            return $avatar_data;
        }

        require_once INC_PATH . '/functions_remote_connect.php';
        $data = fetch_remote_file($avatar_url);
        if ($data === false) {
            $this->errors[] = $lang->adduser['err_avatar_invalid'];
            return $avatar_data;
        }

        $image_info = @getimagesizefromstring($data);
        if (!$image_info) {
            $this->errors[] = $lang->adduser['err_avatar_invalid'];
            return $avatar_data;
        }

        $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!in_array($image_info[2], $allowed_types, true)) {
            $this->errors[] = $lang->adduser['err_avatar_type'];
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
            'subject' => ags_fmt($lang->adduser['pm_welcome_subject'], $SITENAME),
            'message' => ags_fmt($lang->adduser['pm_welcome_body'], htmlspecialchars_uni($username), $SITENAME, $BASEURL),
            'touid' => $user_id
        ];
        
        $pm['sender']['uid'] = -1;
        send_pm($pm, -1, true);

        // Отправка логина/пароля на email (по желанию админа)
        if ($send_credentials) {
            $credentialssubject = ags_fmt($lang->adduser['mail_credentials_subject'], $SITENAME);
            $credentialsbody = ags_fmt(
                $lang->adduser['mail_credentials_body'],
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
                $this->errors[] = ags_fmt($lang->adduser['err_credentials_mail'], $password);
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
		   
           $emailsubject = ags_fmt($lang->adduser['mail_activate_subject'], $SITENAME);
           
		   $emailmessage = ags_fmt($lang->adduser['mail_activate_body'], $username, $SITENAME, $BASEURL, (int)$user_id, $activationcode);
		   
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
            flash_message(ags_fmt($lang->adduser['flash_created_but'], implode('; ', $this->errors)), 'warning');
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
           . '<h2 class="h4 fw-bold mb-1">' . htmlspecialchars_uni($lang->adduser['done_title']) . '</h2>'
           . '<div class="text-body-secondary mb-3">' . ags_fmt(htmlspecialchars_uni($lang->adduser['done_attention']), '<strong>' . htmlspecialchars_uni($user_data['username']) . '</strong>') . '</div>'
           . '<div class="alert alert-warning text-start rounded-4"><ul class="mb-0 ps-3">';
        foreach ($post_create_err as $e) echo '<li>' . htmlspecialchars_uni((string)$e) . '</li>';
        echo '</ul></div>'
           . '<div class="d-flex flex-wrap justify-content-center gap-2">'
           . '<a href="' . htmlspecialchars($_SERVER['REQUEST_URI']) . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-user-plus me-1"></i>' . htmlspecialchars_uni($lang->adduser['btn_add_another']) . '</a>'
           . '<a href="' . $profile_url . '" class="btn btn-primary px-3"><i class="fa-solid fa-arrow-right me-1"></i>' . htmlspecialchars_uni($lang->adduser['btn_go_profile']) . '</a>'
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

$L              = $lang->adduser;
$errors         = $registration_handler->getErrors();
$allowed_groups = $registration_handler->getAllowedUsergroups();
$p              = static fn(string $k, string $d = ''): string => htmlspecialchars_uni((string)($_POST[$k] ?? $d));
$e              = static fn(string $s): string => htmlspecialchars_uni($s);
$defaultGroup   = (int)($_d_usergroup ?: 2);
$defaultUpGb    = (int)($autogigsignup ?? 0);
$defaultBonus   = (int)($autosbsignup ?? 0);
$self           = htmlspecialchars($_SERVER['REQUEST_URI']);
// value => подпись; value остаётся латиницей — на него опирается сервер и JS (units[u])
$unitLabels     = ['GB' => $L['opt_unit_gb'], 'MB' => $L['opt_unit_mb'], 'TB' => $L['opt_unit_tb'], 'B' => $L['opt_unit_b']];
$unit           = static fn(string $name, string $sel) => '<select class="form-select" name="' . $name . '" style="max-width:90px">'
    . implode('', array_map(fn($u) => '<option value="' . $u . '"' . ($sel === $u ? ' selected' : '') . '>' . htmlspecialchars_uni($unitLabels[$u]) . '</option>', array_keys($unitLabels)))
    . '</select>';
$optional       = '<span class="au-opt">' . $e($L['lbl_optional']) . '</span>';
$defaultName    = $allowed_groups[$defaultGroup] ?? $L['opt_default_group_fallback'];

// Строки для JS: ключи js_* без префикса
$jsLang = [];
foreach ($L as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = $v;
    }
}
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$jsCfg = [
    'minName'  => (int)$minnamelength,     'maxName' => (int)$maxnamelength,
    'minPw'    => (int)$minpasswordlength, 'maxPw'   => (int)$maxpasswordlength,
    'complex'  => (bool)$requirecomplexpasswords,
    'defUpGb'  => $defaultUpGb,            'defBonus' => $defaultBonus,
    'self'     => html_entity_decode($self),
];
?>
<div class="container mt-3 mb-4 au">

    <div class="au-card mb-3"><div class="au-head">
        <span class="au-head-icon"><i class="fa-solid fa-user-plus"></i></span>
        <div style="min-width:0">
            <h1 class="au-title"><?= $e($L['title']) ?></h1>
            <div class="au-sub"><?= $e($L['subtitle']) ?></div>
        </div>
        <span class="ms-auto au-muted"><i class="fa-solid fa-code-branch me-1"></i>v<?= htmlspecialchars(AU_VERSION) ?></span>
    </div></div>

    <?php if ($errors): ?>
    <div class="alert alert-danger d-flex gap-2 rounded-4"><i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <div><div class="fw-semibold mb-1"><?= $e($L['err_box_title']) ?></div><ul class="mb-0 ps-3">
        <?php foreach ($errors as $err): ?><li><?= htmlspecialchars_uni((string)$err) ?></li><?php endforeach; ?>
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
                        <div class="au-sec-head"><span class="au-sec-icon ic-blue"><i class="fa-solid fa-id-card"></i></span><?= $e($L['sec_account']) ?></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="input_username"><i class="fa-solid fa-user"></i><?= $e($L['lbl_username']) ?> <span class="au-req">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                    <input type="text" class="form-control" id="input_username" name="username" value="<?= $p('username') ?>"
                                           required minlength="<?= (int)$minnamelength ?>" maxlength="<?= (int)$maxnamelength ?>" autocomplete="off" placeholder="<?= $e($L['ph_username']) ?>">
                                </div>
                                <div class="au-hint" id="hint_username"><?= $e(ags_fmt($L['hint_name_length'], (int)$minnamelength, (int)$maxnamelength)) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_email"><i class="fa-solid fa-envelope"></i><?= $e($L['lbl_email']) ?> <span class="au-req">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                    <input type="email" class="form-control" id="input_email" name="email" value="<?= $p('email') ?>" required autocomplete="off" placeholder="user@example.com">
                                </div>
                                <div class="au-hint" id="hint_email"><?= $e($L['hint_email']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_usergroup"><i class="fa-solid fa-users"></i><?= $e($L['lbl_usergroup']) ?> <?= $optional ?></label>
                                <select class="form-select" id="input_usergroup" name="usergroup">
                                    <!-- data-name: превью берёт имя группы отсюда, а не вырезает префикс «Default — » из текста (он теперь переводится) -->
                                    <option value="" data-name="<?= $e((string)$defaultName) ?>"><?= $e(ags_fmt($L['opt_default_group'], (string)$defaultName)) ?></option>
                                    <?php foreach ($allowed_groups as $gid => $title): ?>
                                    <option value="<?= (int)$gid ?>" <?= (string)($_POST['usergroup'] ?? '') === (string)$gid ? 'selected' : '' ?>><?= htmlspecialchars_uni($title) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="au-hint"><?= $e($L['hint_usergroup']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_modcomment"><i class="fa-solid fa-comment-dots"></i><?= $e($L['lbl_modcomment']) ?> <?= $optional ?></label>
                                <input type="text" class="form-control" id="input_modcomment" name="modcomment" value="<?= $p('modcomment') ?>" maxlength="255" placeholder="<?= $e($L['ph_modcomment']) ?>">
                                <div class="au-hint"><?= $e($L['hint_modcomment']) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Пароль -->
                    <div class="au-sec">
                        <div class="au-sec-head"><span class="au-sec-icon ic-purple"><i class="fa-solid fa-key"></i></span><?= $e($L['sec_password']) ?>
                            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" onclick="generatePassword()"><i class="fa-solid fa-dice me-1"></i><?= $e($L['btn_generate']) ?></button></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="input_password"><i class="fa-solid fa-lock"></i><?= $e($L['lbl_password']) ?> <span class="au-req">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="input_password" name="password" required
                                           minlength="<?= (int)$minpasswordlength ?>" maxlength="<?= (int)$maxpasswordlength ?>" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" data-toggle-pw="input_password" aria-label="<?= $e($L['aria_show_password']) ?>" title="<?= $e($L['aria_show_password']) ?>" style="border-radius:0 .7rem .7rem 0"><i class="fa-solid fa-eye"></i></button>
                                </div>
                                <div class="au-strength"><span id="au_strength"></span></div>
                                <div class="au-hint" id="hint_password"><?= $e(ags_fmt($L[$requirecomplexpasswords ? 'hint_pw_length_complex' : 'hint_pw_length'], (int)$minpasswordlength, (int)$maxpasswordlength)) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_password2"><i class="fa-solid fa-lock"></i><?= $e($L['lbl_password2']) ?> <span class="au-req">*</span></label>
                                <input type="password" class="form-control" id="input_password2" name="password2" required
                                       minlength="<?= (int)$minpasswordlength ?>" maxlength="<?= (int)$maxpasswordlength ?>" autocomplete="new-password">
                                <div class="au-hint" id="hint_password2"></div>
                            </div>
                        </div>
                        <div class="au-genbox" id="generated_password_box" style="display:none">
                            <span><i class="fa-solid fa-circle-check text-success me-2"></i><?= $e($L['lbl_generated']) ?> <code id="generated_password_text"></code></span>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="copyGeneratedPassword()"><i class="fa-regular fa-copy me-1"></i><?= $e($L['btn_copy']) ?></button>
                        </div>
                    </div>

                    <!-- Трафик и бонусы -->
                    <div class="au-sec">
                        <div class="au-sec-head"><span class="au-sec-icon ic-teal"><i class="fa-solid fa-arrow-right-arrow-left"></i></span><?= $e($L['sec_traffic']) ?></div>
                        <div class="row g-3">
                            <!-- Раньше трафик вводился только в БАЙТАХ: 10 GB = 10737418240 -->
                            <div class="col-md-6">
                                <label class="form-label" for="input_uploaded"><i class="fa-solid fa-upload"></i><?= $e($L['lbl_uploaded']) ?></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="input_uploaded" name="uploaded" min="0" step="any" value="<?= $p('uploaded') ?>" placeholder="<?= $defaultUpGb > 0 ? $e(ags_fmt($L['ph_default_gb'], $defaultUpGb)) : '0' ?>">
                                    <?= $unit('uploaded_unit', (string)($_POST['uploaded_unit'] ?? 'GB')) ?>
                                </div>
                                <div class="au-hint"><?= $defaultUpGb > 0 ? $e(ags_fmt($L['hint_signup_bonus_gb'], $defaultUpGb)) : $e($L['hint_signup_bonus']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_downloaded"><i class="fa-solid fa-download"></i><?= $e($L['lbl_downloaded']) ?></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="input_downloaded" name="downloaded" min="0" step="any" value="<?= $p('downloaded', '0') ?>">
                                    <?= $unit('downloaded_unit', (string)($_POST['downloaded_unit'] ?? 'GB')) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_seedbonus"><i class="fa-solid fa-coins"></i><?= $e($L['lbl_seedbonus']) ?></label>
                                <input type="number" class="form-control" id="input_seedbonus" name="seedbonus" min="0" value="<?= $p('seedbonus') ?>" placeholder="<?= $defaultBonus > 0 ? $e(ags_fmt($L['ph_default_value'], $defaultBonus)) : '0' ?>">
                                <div class="au-hint"><?= $e($L['hint_signup_bonus']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="input_invites"><i class="fa-solid fa-ticket"></i><?= $e($L['lbl_invites']) ?></label>
                                <input type="number" class="form-control" id="input_invites" name="invites" min="0" value="<?= $p('invites', '0') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Аватар -->
                    <div class="au-sec">
                        <div class="au-sec-head"><span class="au-sec-icon ic-amber"><i class="fa-solid fa-image"></i></span><?= $e($L['sec_avatar']) ?> <span class="au-opt ms-1"><?= $e($L['lbl_optional']) ?></span></div>
                        <ul class="nav au-seg" id="avatarTabs">
                            <li class="nav-item"><button class="nav-link active" id="tab-url" type="button" onclick="switchAvatarTab('url')"><i class="fa-solid fa-link me-1"></i><?= $e($L['btn_tab_url']) ?></button></li>
                            <li class="nav-item"><button class="nav-link" id="tab-file" type="button" onclick="switchAvatarTab('file')"><i class="fa-solid fa-upload me-1"></i><?= $e($L['btn_tab_upload']) ?></button></li>
                        </ul>
                        <div id="avatar-panel-url">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-link"></i></span>
                                <input type="url" class="form-control" id="input_avatar" name="avatar_url" value="<?= $p('avatar_url') ?>" placeholder="https://example.com/avatar.jpg">
                            </div>
                            <div class="au-hint"><?= $e($L['hint_avatar_url']) ?></div>
                        </div>
                        <div id="avatar-panel-file" style="display:none">
                            <div id="avatar-dropzone"
                                 ondragover="event.preventDefault();this.classList.add('border-primary')"
                                 ondragleave="this.classList.remove('border-primary')"
                                 ondrop="handleAvatarDrop(event)">
                                <i class="fa-solid fa-cloud-arrow-up fa-2x text-body-secondary mb-2"></i>
                                <p class="mb-2 text-body-secondary"><?= $e($L['hint_avatar_drop']) ?></p>
                                <label class="btn btn-outline-primary btn-sm mb-0" for="input_avatar_file"><i class="fa-solid fa-folder-open me-1"></i><?= $e($L['btn_choose_file']) ?></label>
                                <input type="file" class="d-none" id="input_avatar_file" name="avatar_file" accept="image/jpeg,image/png,image/gif,image/webp">
                                <!-- Раньше было «max 500KB», хотя лимит берётся из настроек аватаров -->
                                <p class="au-muted mt-2 mb-0"><?= $e($L['hint_avatar_limits']) ?></p>
                                <p id="avatar-filename" class="text-success mt-1 mb-0 fw-semibold small" style="display:none"></p>
                            </div>
                        </div>
                        <div id="avatar_preview" hidden>
                            <img id="avatar_preview_img" src="" alt="" hidden>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="clearAvatar()"><i class="fa-solid fa-xmark me-1"></i><?= $e($L['btn_remove_avatar']) ?></button>
                        </div>
                    </div>

                    <!-- Опции -->
                    <div class="au-sec">
                        <div class="au-sec-head"><span class="au-sec-icon ic-green"><i class="fa-solid fa-sliders"></i></span><?= $e($L['sec_options']) ?></div>
                        <label class="au-switch" for="sendcredentials">
                            <span class="au-sec-icon ic-purple"><i class="fa-solid fa-paper-plane"></i></span>
                            <span><span class="fw-semibold d-block"><?= $e($L['opt_sendcredentials']) ?></span><span class="au-muted"><?= $e($L['hint_sendcredentials']) ?></span></span>
                            <input type="checkbox" class="form-check-input" role="switch" name="sendcredentials" id="sendcredentials" value="yes" <?= (($_POST['sendcredentials'] ?? 'yes') === 'yes') ? 'checked' : '' ?>>
                        </label>
                        <label class="au-switch" for="confirm">
                            <span class="au-sec-icon ic-amber"><i class="fa-solid fa-envelope-circle-check"></i></span>
                            <span><span class="fw-semibold d-block"><?= $e($L['opt_confirm']) ?></span><span class="au-muted"><?= $e($L['hint_confirm']) ?></span></span>
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
                        <div class="au-pv-name" id="pvName"><?= $e($L['pv_new_user']) ?></div>
                        <div class="au-muted" id="pvEmail"><?= $e($L['pv_no_email']) ?></div>
                    </div>
                    <div class="au-pv-rows">
                        <div class="au-pv-row"><span><i class="fa-solid fa-users"></i><?= $e($L['pv_group']) ?></span><b id="pvGroup"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-upload"></i><?= $e($L['pv_uploaded']) ?></span><b id="pvUp"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-download"></i><?= $e($L['pv_downloaded']) ?></span><b id="pvDown"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-scale-balanced"></i><?= $e($L['pv_ratio']) ?></span><b id="pvRatio"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-coins"></i><?= $e($L['pv_bonus']) ?></span><b id="pvBonus"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-ticket"></i><?= $e($L['pv_invites']) ?></span><b id="pvInv"></b></div>
                        <div class="au-pv-row"><span><i class="fa-solid fa-circle-check"></i><?= $e($L['pv_status']) ?></span><b id="pvStatus"></b></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="au-card au-savebar">
            <span class="au-muted"><i class="fa-solid fa-circle-info me-1"></i><?= $e($L['hint_welcome_pm']) ?></span>
            <button type="submit" class="btn btn-success px-4" id="auSubmit"><i class="fa-solid fa-user-plus me-1"></i><?= $e($L['title']) ?></button>
        </div>
    </form>
</div>

<script>
const AGS_LANG = <?= json_encode($jsLang, $jsonFlags) ?>;
const AU_CFG = <?= json_encode($jsCfg, $jsonFlags) ?>;
</script>
<script src="<?= htmlspecialchars($BASEURL, ENT_QUOTES, 'UTF-8') ?>/admin/scripts/adduser.js?ver=1"></script>
<?php

stdfoot();
