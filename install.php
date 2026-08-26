<?php
/**
 * Installer v2.0
 * 
 */

// ── Безопасный вывод ошибок ────────────────────────────────────────────────
// Ошибки/варнинги PHP не должны печататься на страницу (утечка путей,
// структуры кода и т.п.), особенно пока install.php ещё не удалён с сервера.
// Вместо этого пишем их в install_error.log рядом с инсталлером.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/install_error.log');

/**
 * Логирует техническую деталь ошибки и возвращает безопасное для показа
 * пользователю сообщение общего вида.
 */
function safe_error(string $context, Throwable $e): string
{
    error_log(sprintf('[installer] %s: %s in %s:%d', $context, $e->getMessage(), $e->getFile(), $e->getLine()));
    return $context . '. Подробности сохранены в install_error.log — проверьте лог на сервере.';
}

const IN_TRACKER = true;
const APP_INITIALIZED = true;
define('IN_MYBB', 1);
define('TSDIR', __DIR__);
define('MYBB_ROOT', __DIR__ . '/');
define('INC_PATH', __DIR__ . '/include');
// Флаг "мы внутри инсталлятора" — до require functions.php, чтобы write_log()
// (и всё остальное, что на него смотрит через defined('INSTALL_MODE')) сразу
// знало, что сайта ещё нет, и не пыталось писать в БД-таблицы, которых
// на этом этапе может ещё не существовать.


require_once INC_PATH . '/functions.php';
require_once INC_PATH . '/functions_user.php';

define('INSTALLER_VERSION', '2.0');
define('LOCK_FILE', __DIR__ . '/install.lock');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ── CSRF token ───────────────────────────────────────────────────────────
// Перенесено сюда (было ниже, после блокировки install.lock) - AJAX-запросы
// (test_smtp, self_destruct) должны отрабатывать даже когда install.lock уже
// существует, а без csrf_check()/$_SESSION['csrf_token'] здесь до проверки
// лока они бы падали на неопределённую функцию/пустой токен.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">';
}

function csrf_check(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/**
 * Быстрая проверка SMTP-подключения без полного фреймворка сайта -
 * тот же протокол, что и в include/mailhandlers/smtp.php (connect + EHLO +
 * STARTTLS при необходимости + AUTH LOGIN), но самодостаточно для инсталлятора.
 */
function testSmtpConnection(string $host, int $port, string $user, string $pass): array
{
    if ($host === '') {
        return ['status' => 'skipped', 'message' => 'No SMTP host provided — skipping test.'];
    }

    $useTls = in_array($port, [587, 2587], true);
    $target = in_array($port, [465, 2465], true) ? "ssl://{$host}" : $host;

    $sock = @fsockopen($target, $port, $errno, $errstr, 8);
    if (!$sock) {
        return ['status' => 'error', 'message' => "Could not connect to {$host}:{$port} — {$errstr} ({$errno})"];
    }
    stream_set_timeout($sock, 8);

    $read  = static function ($sock): string {
        $data = '';
        while (($line = fgets($sock, 515)) !== false) {
            $data .= $line;
            if (!isset($line[3]) || $line[3] === ' ') break;
        }
        return $data;
    };
    $write = static function ($sock, string $cmd) use ($read): string {
        fwrite($sock, $cmd . "\r\n");
        return $read($sock);
    };

    $greeting = $read($sock);
    if (!str_starts_with($greeting, '220')) {
        fclose($sock);
        return ['status' => 'error', 'message' => 'Server did not greet with 220: ' . trim($greeting)];
    }

    $ehlo = $write($sock, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!str_starts_with($ehlo, '250')) {
        fclose($sock);
        return ['status' => 'error', 'message' => 'EHLO rejected: ' . trim($ehlo)];
    }

    if ($useTls) {
        if (!str_contains($ehlo, 'STARTTLS')) {
            fclose($sock);
            return ['status' => 'error', 'message' => 'Server does not advertise STARTTLS on this port.'];
        }
        $starttls = $write($sock, 'STARTTLS');
        if (!str_starts_with($starttls, '220')) {
            fclose($sock);
            return ['status' => 'error', 'message' => 'STARTTLS rejected: ' . trim($starttls)];
        }
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT
                      | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                      | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        if (!@stream_socket_enable_crypto($sock, true, $cryptoMethod)) {
            fclose($sock);
            return ['status' => 'error', 'message' => 'Failed to negotiate TLS encryption.'];
        }
        $ehlo = $write($sock, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    }

    if ($user !== '' && $pass !== '') {
        $authStart = $write($sock, 'AUTH LOGIN');
        if (!str_starts_with($authStart, '334')) {
            fclose($sock);
            return ['status' => 'error', 'message' => 'Server did not accept AUTH LOGIN: ' . trim($authStart)];
        }
        $authUser = $write($sock, base64_encode($user));
        if (!str_starts_with($authUser, '334')) {
            fclose($sock);
            return ['status' => 'error', 'message' => 'Username rejected: ' . trim($authUser)];
        }
        $authPass = $write($sock, base64_encode($pass));
        if (!str_starts_with($authPass, '235')) {
            fclose($sock);
            $write($sock, 'QUIT');
            return ['status' => 'error', 'message' => 'Login failed — check SMTP username/password: ' . trim($authPass)];
        }
    }

    $write($sock, 'QUIT');
    fclose($sock);

    $authNote = ($user !== '' && $pass !== '') ? ' Authentication succeeded.' : ' (No credentials provided — connection only, not authenticated.)';
    return ['status' => 'success', 'message' => "Connected to {$host}:{$port} successfully.{$authNote}"];
}

// ── AJAX: проверка SMTP (шаг 3, кнопка "Send Test Email") ─────────────────
// Стоит ДО проверки install.lock ниже: этот запрос должен возвращать чистый
// JSON независимо от того, установлен ли уже трекер.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'test_smtp') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_check()) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token, reload the page and try again.']);
        exit;
    }
    echo json_encode(testSmtpConnection(
        trim((string)($_POST['smtp_host'] ?? '')),
        (int)($_POST['smtp_port'] ?? 587),
        trim((string)($_POST['smtp_user'] ?? '')),
        (string)($_POST['smtp_pass'] ?? '')
    ));
    exit;
}

// ── AJAX: самоудаление install.php (шаг 6, кнопка "Delete install.php") ────
// Тоже стоит ДО проверки install.lock ниже - иначе, раз install.lock к этому
// моменту уже существует (установка завершена), запрос перехватывался бы
// экраном "Already Installed" вместо того, чтобы реально удалить файл.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'self_destruct') {
    header('Content-Type: application/json; charset=utf-8');
    // Разрешаем только когда установка реально завершена (install.lock
    // существует) - деструктивное действие ограничено удалением только
    // самого install.php, ничего больше на сайте оно затронуть не может,
    // так что этой проверки достаточно вместе с CSRF.
    if (!csrf_check() || !file_exists(LOCK_FILE)) {
        echo json_encode(['status' => 'error', 'message' => 'Not allowed.']);
        exit;
    }
    if (@unlink(__FILE__)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Could not delete the file automatically — remove install.php manually via FTP/SSH.']);
    }
    exit;
}

// ── AJAX: план установки (шаг 5, до старта — сколько всего шагов и их имена) ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'get_install_plan') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_check()) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }
    $steps = getInstallSteps();
    echo json_encode(['status' => 'ok', 'total' => count($steps), 'labels' => array_column($steps, 'label')]);
    exit;
}

// ── AJAX: выполнить ровно один шаг установки (дёргается фронтендом в цикле) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'install_step') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_check()) {
        echo json_encode(['status' => 'fail', 'label' => 'Security', 'message' => 'Invalid security token', 'elapsed_ms' => 0]);
        exit;
    }
    $index = (int)($_POST['step_index'] ?? -1);
    echo json_encode(executeInstallStep($index));
    exit;
}

// ── Защита от повторного запуска ────────────────────────────────────────────
// ВАЖНО: раньше здесь была проверка `!isset($_GET['force'])`, позволявшая
// обойти install.lock простым GET-параметром без какой-либо аутентификации -
// то есть кто угодно, зная (или угадав) install.php?force=1, мог заново
// пройти установку, создать нового admin-пользователя и переписать
// $config['super_admins'] в config.php, полностью захватив сайт. Единственный
// способ переустановки теперь - ручное удаление install.lock на сервере
// (доступ есть только у того, у кого есть доступ к файловой системе).
//
// Исключение: сразу после успешной установки шаг 'finalize' создаёт
// install.lock и редиректит на ?step=6, чтобы показать финальную страницу
// с доступами. Это уже НОВЫЙ HTTP-запрос — без явного bypass эта проверка
// перехватывала бы его раньше, чем успевал открыться шаг 6, и админ никогда
// не видел финальную страницу (включая карточку автологина). Флаг
// одноразовый: снимается сразу после того, как шаг 6 отрендерился один раз.
$justInstalled = !empty($_SESSION['just_installed']);
if (file_exists(LOCK_FILE) && !$justInstalled) {
    die('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔒 Already Installed | Mybb Based Torrent Tracker</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body { background: radial-gradient(circle at top left, #eef0fd 0%, #f7f7fb 55%, #ffffff 100%); }
        .lock-card { backdrop-filter:blur(10px); background:rgba(255,255,255,.97); border-radius:30px; box-shadow:0 25px 60px -12px rgba(0,0,0,.2); transition:transform .3s ease; }
        .lock-card:hover { transform:translateY(-5px); }
        .lock-icon { font-size:5rem; animation:pulse 2s infinite; }
        @keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.1);opacity:.8} }
        .lock-card h2 { font-family:Oswald,sans-serif; font-weight:700; text-transform:uppercase; letter-spacing:.01em; }
        code { background:#f8f9fa; padding:4px 8px; border-radius:8px; font-weight:600; color:#dc3545; }
        .warning-alert { border-left:4px solid #ffc107; background:#fff9e6; }
        .btn { border-radius:.7rem; font-weight:600; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;margin:0;padding:20px;">
    <div class="container" style="max-width:550px;">
        <div class="lock-card p-5">
            <div class="text-center mb-4">
                <div class="lock-icon mb-3"><i class="fas fa-lock-circle fa-4x text-danger"></i></div>
                <div class="mb-3"><span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><i class="fas fa-shield-alt me-2"></i>Installation Locked</span></div>
                <h2 class="fw-bold mb-2" style="color:#1a1a2e;">Already Installed</h2>
                <p class="text-muted mb-1">Mybb Based Torrent Tracker is already installed and configured.</p>
                <p class="text-muted">To reinstall, delete <code>install.lock</code> from the server via FTP/SSH/RDP, then reload this page.</p>
            </div>
            <hr class="my-4">
            <div class="warning-alert p-3 rounded mb-4">
                <div class="d-flex">
                    <div class="flex-shrink-0"><i class="fas fa-exclamation-triangle text-warning fa-lg"></i></div>
                    <div class="ms-3"><strong class="d-block mb-1">Warning!</strong><small class="text-muted">Reinstalling will overwrite your current settings and data.</small></div>
                </div>
            </div>
            <div class="d-grid gap-3">
                <a href="/" class="btn btn-secondary btn-lg"><i class="fas fa-home me-2"></i>Go to Homepage</a>
            </div>
        </div>
    </div>
</body>
</html>');
}

// Одноразовый флаг использован — снимаем, чтобы следующая загрузка install.php
// (например, обновление страницы шага 6 или переход куда-то ещё) снова
// показывала обычный экран блокировки, как и задумано.
if ($justInstalled) {
    unset($_SESSION['just_installed']);
}

$steps = [
    1 => 'Requirements',
    2 => 'Database',
    3 => 'Site Settings',
    4 => 'Admin Account',
    5 => 'Install',
    6 => 'Complete',
];

$step   = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step   = max(1, min(6, $step));
$errors = [];

// ── Initialize DB class from session (starting at step 3) ─────────────────
function getDb(): mixed
{
    $dbSess = $_SESSION['db'] ?? [];
    if (empty($dbSess)) return null;

    $config = [
        'database' => [
            'type'         => $dbSess['type']     ?? 'mysqli',
            'hostname'     => $dbSess['hostname']  ?? 'localhost',
            'database'     => $dbSess['database']  ?? '',
            'username'     => $dbSess['username']  ?? '',
            'password'     => $dbSess['password']  ?? '',
            'encoding'     => 'utf8mb4',
        ],
    ];

    require_once INC_PATH . '/db_base.php';
    $driverFile = INC_PATH . "/db_{$config['database']['type']}.php";
    if (!file_exists($driverFile)) {
        throw new RuntimeException("Database driver not found: {$config['database']['type']}");
    }
    require_once $driverFile;

    $db = match($config['database']['type']) {
        'sqlite' => new DB_SQLite(),
        'pgsql'  => new DB_PgSQL(),
        'mysqli' => new DB_MySQLi(),
        default  => new DB_MySQL(),
    };

    $db->connect($config['database']);
    $db->type = $config['database']['type'];

    return $db;
}

// ── POST handling ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
    $errors[] = 'Invalid or expired form token, please try again.';
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Step 2: connection check ────────────────────────────────────────────
    if ($step === 2) {
        $dbType = trim($_POST['db_type'] ?? 'mysqli');

        $_SESSION['db'] = [
            'type'     => $dbType,
            'hostname' => trim($_POST['db_host'] ?? 'localhost'),
            'database' => trim($_POST['db_name'] ?? ''),
            'username' => trim($_POST['db_user'] ?? ''),
            'password' => $_POST['db_pass'] ?? '',
        ];

        try {
            $db = getDb();
            // Simple test: version query
            $db->sql_query_prepared("SELECT 1");
            header('Location: install.php?step=3'); exit;
        } catch (Throwable $e) {
            $errors[] = safe_error('Cannot connect to the database. Please check your credentials', $e);
        }
    }

    // ── Step 3: site settings ────────────────────────────────────────────────
    if ($step === 3) {
        $url = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $_SESSION['site'] = [
            'name'          => trim($_POST['site_name']      ?? ''),
            'url'           => $url,
            'email'         => trim($_POST['site_email']     ?? ''),
            'timezone'      => trim($_POST['timezone']       ?? '2'),
            'cookie_domain' => trim($_POST['cookie_domain']  ?? ''),
            'smtp_host'     => trim($_POST['smtp_host']      ?? 'smtp.gmail.com'),
            'smtp_user'     => trim($_POST['smtp_user']      ?? ''),
            'smtp_pass'     => $_POST['smtp_pass']           ?? '',
            'smtp_port'     => trim($_POST['smtp_port']      ?? '587'),
            'privatepatch'  => isset($_POST['privatepatch']) ? 'yes' : 'no',
        ];
        if (!$_SESSION['site']['name'])  $errors[] = 'Site name is required.';
        if (!$_SESSION['site']['url'])   $errors[] = 'Site URL is required.';
        if (!$_SESSION['site']['email']) $errors[] = 'Site email is required.';
        if (!$errors) { header('Location: install.php?step=4'); exit; }
    }

    // ── Step 4: admin account ────────────────────────────────────────────────
    if ($step === 4) {
        $_SESSION['admin'] = [
            'username' => trim($_POST['admin_user']    ?? ''),
            'email'    => trim($_POST['admin_email']   ?? ''),
            'password' => $_POST['admin_pass']         ?? '',
            'confirm'  => $_POST['admin_confirm']      ?? '',
        ];
        if (!$_SESSION['admin']['username']) $errors[] = 'Admin username is required.';
        if (!$_SESSION['admin']['email'])    $errors[] = 'Admin email is required.';
        if (strlen($_SESSION['admin']['password']) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($_SESSION['admin']['password'] !== $_SESSION['admin']['confirm']) $errors[] = 'Passwords do not match.';
        if (!$errors) { header('Location: install.php?step=5'); exit; }
    }

    // Шаг 5 (сама установка) больше не обрабатывается здесь как обычный
    // form-POST - вся работа идёт через AJAX-эндпоинты get_install_plan/
    // install_step выше (см. живой прогресс-бар на странице шага 5).
}

// ── Main installation function ────────────────────────────────────────────────
function getTableSchemaSql(): string
{
    return <<<SQL
CREATE TABLE IF NOT EXISTS `2fa` (
  `uid`        INT UNSIGNED     NOT NULL,
  `secret`     VARCHAR(64)      NOT NULL DEFAULT '',
  `enabled`    ENUM('yes','no') NOT NULL DEFAULT 'no',
  `created_at` INT UNSIGNED     NOT NULL DEFAULT 0,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `2fa_pending` (
  `token`      VARCHAR(64)  NOT NULL,
  `uid`        INT UNSIGNED NOT NULL,
  `remember`   VARCHAR(8)   NOT NULL DEFAULT '',
  `url`        VARCHAR(512) NOT NULL DEFAULT '',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`token`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
CREATE TABLE IF NOT EXISTS `adminlog` (
  `uid` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `module` varchar(50) NOT NULL DEFAULT '',
  `action` varchar(50) NOT NULL DEFAULT '',
  `data` text NOT NULL,
  KEY `module` (`module`,`action`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(120) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `uid` int UNSIGNED NOT NULL DEFAULT '0',
  `added` int UNSIGNED NOT NULL DEFAULT '0',
  `updated` int UNSIGNED NOT NULL DEFAULT '0',
  `views` int UNSIGNED NOT NULL DEFAULT '0',
  `startdate` int UNSIGNED NOT NULL DEFAULT '0',
  `enddate` int UNSIGNED NOT NULL DEFAULT '0',
  `minclassread` tinyint NOT NULL DEFAULT '1',
  `fid` int NOT NULL DEFAULT '0',
  `type` enum('tracker','forum','global') NOT NULL DEFAULT 'tracker',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE IF NOT EXISTS `attachments` (
  `aid` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` int unsigned NOT NULL DEFAULT '0',
  `comment_id` int unsigned NOT NULL DEFAULT '0',
  `posthash` varchar(50) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `filename` varchar(255) NOT NULL DEFAULT '',
  `filetype` varchar(120) NOT NULL DEFAULT '',
  `filesize` int unsigned NOT NULL DEFAULT '0',
  `attachname` varchar(255) NOT NULL DEFAULT '',
  `downloads` int unsigned NOT NULL DEFAULT '0',
  `dateuploaded` int unsigned NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '0',
  `thumbnail` varchar(120) NOT NULL DEFAULT '',
  PRIMARY KEY (`aid`),
  KEY `pid` (`pid`,`visible`),
  KEY `uid` (`uid`),
  KEY `comment_id` (`comment_id`),
  KEY `posthash` (`posthash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attachtypes` (
  `atid` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `mimetype` varchar(120) NOT NULL DEFAULT '',
  `extension` varchar(10) NOT NULL DEFAULT '',
  `maxsize` int unsigned NOT NULL DEFAULT '0',
  `icon` varchar(255) NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `forcedownload` tinyint(1) NOT NULL DEFAULT '0',
  `groups` text NOT NULL,
  `forums` text NOT NULL,
  `avatarfile` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`atid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `auto_vip` (
  `userid` int unsigned NOT NULL DEFAULT '0',
  `vip_until` int unsigned NOT NULL DEFAULT '0',
  `old_gid` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`userid`),
  KEY `idx_vip_until` (`vip_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `awaitingactivation` (
  `aid` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `code` varchar(100) NOT NULL DEFAULT '',
  `type` char(1) NOT NULL DEFAULT '',
  `validated` tinyint(1) NOT NULL DEFAULT '0',
  `misc` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `banfilters` (
  `fid` int unsigned NOT NULL AUTO_INCREMENT,
  `filter` varchar(200) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `lastuse` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`fid`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `banned` (
  `uid` int unsigned NOT NULL DEFAULT '0',
  `gid` int unsigned NOT NULL DEFAULT '0',
  `oldgroup` int unsigned NOT NULL DEFAULT '0',
  `oldadditionalgroups` text NOT NULL,
  `olddisplaygroup` int unsigned NOT NULL DEFAULT '0',
  `admin` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `bantime` varchar(50) NOT NULL DEFAULT '',
  `lifted` int unsigned NOT NULL DEFAULT '0',
  `reason` varchar(255) NOT NULL DEFAULT '',
  KEY `uid` (`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int unsigned NOT NULL DEFAULT '0',
  `torrentid` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`,`torrentid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` char(100) NOT NULL DEFAULT '',
  `cat_desc` char(30) NOT NULL DEFAULT '',
  `minclassread` tinyint unsigned NOT NULL DEFAULT '0',
  `type` char(1) NOT NULL DEFAULT 'c',
  `pid` smallint unsigned NOT NULL DEFAULT '0',
  `icon` varchar(100) NOT NULL DEFAULT 'fa-solid fa-question',
  PRIMARY KEY (`id`),
  KEY `type` (`type`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cheat_attempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `added` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `torrentid` int unsigned NOT NULL DEFAULT '0',
  `agent` char(32) NOT NULL DEFAULT '',
  `transfer_rate` bigint unsigned NOT NULL DEFAULT '0',
  `beforeup` bigint unsigned NOT NULL DEFAULT '0',
  `upthis` bigint unsigned NOT NULL DEFAULT '0',
  `timediff` int unsigned NOT NULL DEFAULT '0',
  `ip` char(15) NOT NULL DEFAULT '',
  `reason` varchar(64) NOT NULL DEFAULT '',
  `detail` varchar(512) NOT NULL DEFAULT '',
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `comment_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` int unsigned DEFAULT NULL,
  `news_id` int unsigned DEFAULT NULL,
  `torrent_id` int unsigned DEFAULT NULL,
  `post_id` int unsigned DEFAULT NULL,
  `messages_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` bigint unsigned NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comment_id` (`comment_id`),
  KEY `idx_news_id` (`news_id`),
  KEY `idx_torrent_id` (`torrent_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `comments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user` int unsigned NOT NULL DEFAULT '0',
  `torrent` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `text` text NOT NULL,
  `editreason` varchar(150) NOT NULL DEFAULT '',
  `editedby` int unsigned NOT NULL DEFAULT '0',
  `editedat` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
  KEY `torrent` (`torrent`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `countries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `flagpic` char(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cron` (
  `cronid` int unsigned NOT NULL AUTO_INCREMENT,
  `nextrun` int unsigned NOT NULL DEFAULT '0',
  `minutes` int unsigned NOT NULL DEFAULT '0',
  `filename` char(50) NOT NULL DEFAULT '',
  `description` char(200) NOT NULL DEFAULT '',
  `loglevel` tinyint unsigned NOT NULL DEFAULT '1',
  `active` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`cronid`),
  KEY `nextrun` (`nextrun`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cron_log` (
  `filename` char(100) NOT NULL DEFAULT '',
  `querycount` smallint unsigned NOT NULL DEFAULT '0',
  `executetime` char(10) NOT NULL DEFAULT '0',
  `runtime` int unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `datacache` (
  `title` varchar(50) NOT NULL DEFAULT '',
  `cache` mediumtext NOT NULL,
  PRIMARY KEY (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `faq` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('category','item') NOT NULL DEFAULT 'category',
  `name` varchar(120) NOT NULL DEFAULT '',
  `pid` int unsigned NOT NULL DEFAULT '0',
  `disporder` smallint unsigned NOT NULL DEFAULT '0',
  `description` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `views_count` int unsigned DEFAULT '0',
  `icon_class` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forumpermissions` (
  `pid` int unsigned NOT NULL AUTO_INCREMENT,
  `fid` int unsigned NOT NULL DEFAULT '0',
  `gid` int unsigned NOT NULL DEFAULT '0',
  `canview` tinyint(1) NOT NULL DEFAULT '0',
  `canviewthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canonlyviewownthreads` tinyint(1) NOT NULL DEFAULT '0',
  `candlattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canpostthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostreplys` tinyint(1) NOT NULL DEFAULT '0',
  `canonlyreplyownthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostattachments` tinyint(1) NOT NULL DEFAULT '0',
  `caneditposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `caneditattachments` tinyint(1) NOT NULL DEFAULT '0',
  `modposts` tinyint(1) NOT NULL DEFAULT '0',
  `modthreads` tinyint(1) NOT NULL DEFAULT '0',
  `mod_edit_posts` tinyint(1) NOT NULL DEFAULT '0',
  `modattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canpostpolls` tinyint(1) NOT NULL DEFAULT '0',
  `canvotepolls` tinyint(1) NOT NULL DEFAULT '0',
  `cansearch` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`),
  KEY `fid` (`fid`,`gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hit_and_run` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int unsigned NOT NULL DEFAULT '0',
  `torrentid` int unsigned NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inactivity` (
  `userid` int unsigned NOT NULL DEFAULT '0',
  `inactivitytag` int unsigned NOT NULL DEFAULT '0',
  KEY `userid` (`userid`),
  KEY `inactivitytag` (`inactivitytag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `inviter_id` int unsigned NOT NULL DEFAULT '0',
  `invitee_id` int unsigned DEFAULT NULL,
  `email` varchar(64) DEFAULT NULL,
  `status` enum('pending','used','expired','revoked') NOT NULL DEFAULT 'pending',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `expires_at` int unsigned DEFAULT NULL,
  `used_at` int unsigned DEFAULT NULL,
  `ip_created` varchar(45) DEFAULT NULL,
  `ip_used` varchar(45) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `inviter_id` (`inviter_id`),
  KEY `status` (`status`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `loginattempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip` char(15) NOT NULL DEFAULT '',
  `added` int unsigned NOT NULL DEFAULT '0',
  `banned` enum('yes','no') NOT NULL DEFAULT 'no',
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `type` enum('login','recover') NOT NULL DEFAULT 'login',
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `login_log` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `uid`        INT UNSIGNED    NOT NULL DEFAULT 0,
  `ip`         VARCHAR(45)     NOT NULL DEFAULT '',
  `country`    VARCHAR(64)     NOT NULL DEFAULT '',
  `city`       VARCHAR(64)     NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255)    NOT NULL DEFAULT '',
  `fingerprint` VARCHAR(32)    NOT NULL DEFAULT '',
  `datetime`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `type`       ENUM('login','recover') NOT NULL DEFAULT 'login',
  `status`     ENUM('success','fail')  NOT NULL DEFAULT 'fail',
  `suspicious` ENUM('yes','no')        NOT NULL DEFAULT 'no',
  `banned`     ENUM('yes','no')        NOT NULL DEFAULT 'no',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`), KEY `ip` (`ip`),
  KEY `datetime` (`datetime`), KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE IF NOT EXISTS `mailerrors` (
  `eid` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `toaddress` varchar(150) NOT NULL DEFAULT '',
  `fromaddress` varchar(150) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `error` text NOT NULL,
  `smtperror` varchar(200) NOT NULL DEFAULT '',
  `smtpcode` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`eid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `maillogs` (
  `mid` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `fromuid` int unsigned NOT NULL DEFAULT '0',
  `fromemail` varchar(200) NOT NULL DEFAULT '',
  `touid` int unsigned NOT NULL DEFAULT '0',
  `toemail` varchar(200) NOT NULL DEFAULT '',
  `tid` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`mid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mailqueue` (
  `mid` int unsigned NOT NULL AUTO_INCREMENT,
  `mailto` varchar(200) NOT NULL,
  `mailfrom` varchar(200) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `headers` text NOT NULL,
  PRIMARY KEY (`mid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `moderatorlog` (
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `tid` int unsigned NOT NULL DEFAULT '0',
  `pid` int unsigned NOT NULL DEFAULT '0',
  `action` text NOT NULL,
  `data` text NOT NULL,
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  KEY `uid` (`uid`),
  KEY `fid` (`fid`),
  KEY `tid` (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `moderators` (
  `mid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `id` int unsigned NOT NULL DEFAULT '0',
  `isgroup` tinyint unsigned NOT NULL DEFAULT '0',
  `caneditposts` tinyint(1) NOT NULL DEFAULT '0',
  `cansoftdeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `canrestoreposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `cansoftdeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canrestorethreads` tinyint(1) NOT NULL DEFAULT '0',
  `candeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canviewips` tinyint(1) NOT NULL DEFAULT '0',
  `canviewunapprove` tinyint(1) NOT NULL DEFAULT '0',
  `canviewdeleted` tinyint(1) NOT NULL DEFAULT '0',
  `canopenclosethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canstickunstickthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canapproveunapprovethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canapproveunapproveposts` tinyint(1) NOT NULL DEFAULT '0',
  `canapproveunapproveattachs` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagepolls` tinyint(1) NOT NULL DEFAULT '0',
  `canpostclosedthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canmovetononmodforum` tinyint(1) NOT NULL DEFAULT '0',
  `canusecustomtools` tinyint(1) NOT NULL DEFAULT '0',
  `canmanageannouncements` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagereportedposts` tinyint(1) NOT NULL DEFAULT '0',
  `canviewmodlog` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`mid`),
  KEY `uid` (`id`,`fid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `news` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  `body` text NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `added` (`added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` int unsigned NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_userid` (`userid`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `peers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrent` int unsigned NOT NULL DEFAULT '0',
  `peer_id` binary(20) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `port` smallint unsigned NOT NULL DEFAULT '0',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `to_go` bigint unsigned NOT NULL DEFAULT '0',
  `seeder` enum('yes','no') NOT NULL DEFAULT 'no',
  `started` int unsigned NOT NULL DEFAULT '0',
  `last_action` int unsigned NOT NULL DEFAULT '0',
  `prev_action` int unsigned NOT NULL DEFAULT '0',
  `connectable` enum('yes','no') NOT NULL DEFAULT 'yes',
  `userid` int unsigned NOT NULL DEFAULT '0',
  `agent` varchar(80) NOT NULL DEFAULT '',
  `finishedat` int unsigned NOT NULL DEFAULT '0',
  `downloadoffset` bigint unsigned NOT NULL DEFAULT '0',
  `uploadoffset` bigint unsigned NOT NULL DEFAULT '0',
  `passkey` char(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `last_action` (`last_action`),
  KEY `userid` (`userid`),
  KEY `passkey` (`passkey`),
  KEY `torrent` (`torrent`),
  KEY `peer_id` (`peer_id`,`seeder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `privatemessages` (
  `pmid` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `toid` int unsigned NOT NULL DEFAULT '0',
  `fromid` int unsigned NOT NULL DEFAULT '0',
  `recipients` text NOT NULL,
  `folder` smallint unsigned NOT NULL DEFAULT '1',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `icon` smallint unsigned NOT NULL DEFAULT '0',
  `message` text NOT NULL,
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `deletetime` int unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `statustime` int unsigned NOT NULL DEFAULT '0',
  `receipt` tinyint(1) NOT NULL DEFAULT '0',
  `readtime` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`pmid`),
  KEY `uid` (`uid`,`folder`),
  KEY `toid` (`toid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `addedby` int unsigned NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  `reported_id` int unsigned NOT NULL DEFAULT '0',
  `reported_user_id` int unsigned NOT NULL DEFAULT '0',
  `forum_id` int DEFAULT '0',
  `thread_id` int DEFAULT '0',
  `type` enum('torrent','user','comment','forumpost') NOT NULL DEFAULT 'torrent',
  `reason` varchar(255) NOT NULL DEFAULT '',
  `rule_violation` varchar(50) DEFAULT '',
  `description` text,
  `dealtby` int unsigned NOT NULL DEFAULT '0',
  `dealtwith` tinyint unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reported_id` (`reported_id`),
  KEY `idx_dealtwith` (`dealtwith`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `text` text NOT NULL,
  `usergroups` varchar(100) NOT NULL DEFAULT '[0]',
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `priority` tinyint unsigned DEFAULT '50',
  `is_active` tinyint(1) DEFAULT '1',
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `screenshots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `torrent_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` int unsigned NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `searchlog` (
  `sid` varchar(32) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `threads` longtext NOT NULL,
  `posts` longtext NOT NULL,
  `resulttype` varchar(10) NOT NULL DEFAULT '',
  `querycache` text NOT NULL,
  `keywords` text NOT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `seedbonus_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('boolean','integer','float','string','array') DEFAULT 'string',
  `setting_group` varchar(50) DEFAULT 'general',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sessions` (
  `sid` varchar(32) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `ip` varbinary(16) NOT NULL DEFAULT '',
  `time` int unsigned NOT NULL DEFAULT '0',
  `location` varchar(150) NOT NULL DEFAULT '',
  `useragent` varchar(200) NOT NULL DEFAULT '',
  `anonymous` tinyint(1) NOT NULL DEFAULT '0',
  `nopermission` tinyint(1) NOT NULL DEFAULT '0',
  `location1` int unsigned NOT NULL DEFAULT '0',
  `location2` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`),
  KEY `time` (`time`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `sid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `value` text NOT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sitelog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `added` int unsigned NOT NULL DEFAULT '0',
  `txt` text,
  `category` varchar(50) NOT NULL DEFAULT '',
  `level` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `category` (`category`),
  KEY `added` (`added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `smilies` (
  `sid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `stitle` char(100) NOT NULL DEFAULT '',
  `stext` char(20) NOT NULL DEFAULT '',
  `spath` char(100) NOT NULL DEFAULT '',
  `sorder` smallint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `snatched` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrentid` int unsigned DEFAULT '0',
  `userid` int unsigned DEFAULT '0',
  `port` smallint unsigned NOT NULL DEFAULT '0',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `to_go` bigint unsigned NOT NULL DEFAULT '0',
  `seeder` enum('yes','no') NOT NULL DEFAULT 'no',
  `last_action` int unsigned NOT NULL DEFAULT '0',
  `startdat` int unsigned NOT NULL DEFAULT '0',
  `completedat` int unsigned NOT NULL DEFAULT '0',
  `connectable` enum('yes','no') NOT NULL DEFAULT 'yes',
  `agent` char(32) NOT NULL DEFAULT '',
  `finished` enum('yes','no') NOT NULL DEFAULT 'no',
  `downspeed` bigint unsigned NOT NULL DEFAULT '0',
  `upspeed` bigint unsigned NOT NULL DEFAULT '0',
  `seedtime` int unsigned NOT NULL DEFAULT '0',
  `leechtime` int unsigned NOT NULL DEFAULT '0',
  `ip` char(15) NOT NULL DEFAULT '',
  `warned` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `seeder` (`seeder`,`last_action`),
  KEY `torrentid` (`torrentid`),
  KEY `userid` (`userid`),
  KEY `finished` (`finished`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `spiders` (
  `sid` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `theme` smallint unsigned NOT NULL DEFAULT '0',
  `language` varchar(20) NOT NULL DEFAULT '',
  `usergroup` smallint unsigned NOT NULL DEFAULT '0',
  `useragent` varchar(200) NOT NULL DEFAULT '',
  `lastvisit` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `staffmessages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sender` int unsigned NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  `msg` text,
  `subject` varchar(100) NOT NULL DEFAULT '',
  `answeredby` int unsigned NOT NULL DEFAULT '0',
  `answered` tinyint unsigned NOT NULL DEFAULT '0',
  `answer` text,
  PRIMARY KEY (`id`),
  KEY `answered` (`answered`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `staffpanel` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` char(32) NOT NULL DEFAULT '',
  `description` varchar(64) NOT NULL DEFAULT '',
  `filename` char(32) NOT NULL DEFAULT '',
  `usergroups` char(32) NOT NULL DEFAULT '[8]',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stats` (
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `numusers` int unsigned NOT NULL DEFAULT '0',
  `numthreads` int unsigned NOT NULL DEFAULT '0',
  `numposts` int unsigned NOT NULL DEFAULT '0',
  `torrents` int unsigned NOT NULL DEFAULT '0',
  `seeders` int unsigned NOT NULL DEFAULT '0',
  `leechers` int unsigned NOT NULL DEFAULT '0',
  `peers` int unsigned NOT NULL DEFAULT '0',
  `totaldownloaded` bigint unsigned NOT NULL DEFAULT '0',
  `totaluploaded` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `torrents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `info_hash` varchar(40) NOT NULL,
  `name` tinytext,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `descr` longtext,
  `tags` text,
  `category` int unsigned NOT NULL DEFAULT '0',
  `size` bigint unsigned NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  `numfiles` int unsigned NOT NULL DEFAULT '0',
  `comments` int unsigned NOT NULL DEFAULT '0',
  `hits` int unsigned NOT NULL DEFAULT '0',
  `times_completed` int unsigned NOT NULL DEFAULT '0',
  `leechers` int unsigned NOT NULL DEFAULT '0',
  `seeders` int unsigned NOT NULL DEFAULT '0',
  `last_action` int unsigned NOT NULL DEFAULT '0',
  `visible` enum('yes','no') NOT NULL DEFAULT 'yes',
  `banned` enum('yes','no') NOT NULL DEFAULT 'no',
  `owner` int unsigned NOT NULL DEFAULT '0',
  `anonymous` enum('yes','no') NOT NULL DEFAULT 'no',
  `free` enum('yes','no') DEFAULT 'no',
  `silver` enum('yes','no') NOT NULL DEFAULT 'no',
  `doubleupload` enum('yes','no') NOT NULL DEFAULT 'no',
  `thirtypercent` enum('yes','no') NOT NULL DEFAULT 'no',
  `sticky` enum('yes','no') NOT NULL DEFAULT 'no',
  `allowcomments` enum('yes','no') NOT NULL DEFAULT 'yes',
  `t_image` varchar(255) NOT NULL DEFAULT '',
  `t_image2` varchar(255) NOT NULL DEFAULT '',
  `t_link` text NOT NULL,
  `isnuked` enum('yes','no') NOT NULL DEFAULT 'no',
  `WhyNuked` varchar(255) NOT NULL DEFAULT 'Bad quality!',
  `isrequest` enum('yes','no') NOT NULL DEFAULT 'no',
  `mtime` int unsigned NOT NULL DEFAULT '0',
  `ctime` int unsigned NOT NULL DEFAULT '0',
  `promotion_time_type` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `info_hash` (`info_hash`),
  KEY `added` (`added`),
  KEY `category` (`category`),
  KEY `owner` (`owner`),
  KEY `visible` (`visible`,`banned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `torrents_nfo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrent_id` int unsigned NOT NULL,
  `nfo` longtext NOT NULL,
  `uploaded_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `torrent_id` (`torrent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `torrent_ratings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `torrent_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `added` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`torrent_id`, `user_id`)
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS `threadratings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `tid` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `added` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`tid`, `user_id`),
  KEY `tid` (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;






CREATE TABLE IF NOT EXISTS `forums` (
  `fid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `linkto` varchar(180) NOT NULL DEFAULT '',
  `type` char(1) NOT NULL DEFAULT '',
  `pid` smallint unsigned NOT NULL DEFAULT '0',
  `parentlist` text NOT NULL,
  `disporder` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `open` tinyint(1) NOT NULL DEFAULT '1',
  `threads` int unsigned NOT NULL DEFAULT '0',
  `posts` int unsigned NOT NULL DEFAULT '0',
  `lastpost` int unsigned NOT NULL DEFAULT '0',
  `lastposter` varchar(120) NOT NULL DEFAULT '',
  `lastposteruid` int unsigned NOT NULL DEFAULT '0',
  `lastposttid` int NOT NULL DEFAULT '0',
  `lastpostsubject` varchar(120) NOT NULL DEFAULT '',
  `password` varchar(50) NOT NULL DEFAULT '',
  `usepostcounts` tinyint(1) NOT NULL DEFAULT '1',
  `usethreadcounts` tinyint(1) NOT NULL DEFAULT '1',
  `unapprovedthreads` int unsigned NOT NULL DEFAULT '0',
  `unapprovedposts` int unsigned NOT NULL DEFAULT '0',
  `defaultdatecut` smallint unsigned NOT NULL DEFAULT '0',
  `defaultsortby` varchar(10) NOT NULL DEFAULT '',
  `defaultsortorder` varchar(4) NOT NULL DEFAULT '',
  PRIMARY KEY (`fid`),
  KEY `pid` (`pid`),
  KEY `type` (`type`,`pid`,`disporder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forumsread` (
  `fid` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `fid` (`fid`,`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forumsubscriptions` (
  `fsid` int unsigned NOT NULL AUTO_INCREMENT,
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`fsid`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `polls` (
  `pid` int unsigned NOT NULL AUTO_INCREMENT,
  `tid` int unsigned NOT NULL DEFAULT '0',
  `question` varchar(200) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `options` text NOT NULL,
  `votes` text NOT NULL,
  `numoptions` smallint unsigned NOT NULL DEFAULT '0',
  `numvotes` int unsigned NOT NULL DEFAULT '0',
  `timeout` int unsigned NOT NULL DEFAULT '0',
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  `multiple` tinyint(1) NOT NULL DEFAULT '0',
  `public` tinyint(1) NOT NULL DEFAULT '0',
  `maxoptions` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`),
  KEY `tid` (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pollvotes` (
  `vid` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `voteoption` smallint unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`vid`),
  KEY `pid` (`pid`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `posts` (
  `pid` int unsigned NOT NULL AUTO_INCREMENT,
  `tid` int unsigned NOT NULL DEFAULT '0',
  `replyto` int unsigned NOT NULL DEFAULT '0',
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `username` char(32) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `message` text NOT NULL,
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `edituid` int unsigned NOT NULL DEFAULT '0',
  `edittime` int NOT NULL DEFAULT '0',
  `editreason` varchar(150) NOT NULL DEFAULT '',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`pid`),
  KEY `tid` (`tid`,`uid`),
  KEY `uid` (`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `threads` (
  `tid` int unsigned NOT NULL AUTO_INCREMENT,
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `username` char(32) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `firstpost` int unsigned NOT NULL DEFAULT '0',
  `lastpost` int unsigned NOT NULL DEFAULT '0',
  `lastposter` char(32) NOT NULL DEFAULT '',
  `lastposteruid` int unsigned NOT NULL DEFAULT '0',
  `views` int NOT NULL DEFAULT '0',
  `replies` int NOT NULL DEFAULT '0',
  `closed` varchar(30) NOT NULL DEFAULT '',
  `sticky` tinyint(1) NOT NULL DEFAULT '0',
  `votenum` smallint unsigned NOT NULL DEFAULT '0',
  `votetotal` smallint unsigned NOT NULL DEFAULT '0',
  `poll` int unsigned NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `attachmentcount` int unsigned NOT NULL DEFAULT '0',
  `prefix` smallint unsigned NOT NULL DEFAULT '0',
  `numratings` smallint unsigned NOT NULL DEFAULT '0',
  `totalratings` smallint unsigned NOT NULL DEFAULT '0',
  `unapprovedposts` int unsigned NOT NULL DEFAULT '0',
  `deletetime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`tid`),
  KEY `fid` (`fid`,`visible`,`sticky`),
  KEY `lastpost` (`lastpost`,`fid`),
  KEY `uid` (`uid`),
  KEY `deletetime` (`deletetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `threadsread` (
  `tid` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `tid` (`tid`,`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `threadsubscriptions` (
  `sid` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `tid` int unsigned NOT NULL DEFAULT '0',
  `notification` tinyint(1) NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`),
  KEY `uid` (`uid`),
  KEY `tid` (`tid`,`notification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `threadviews` (
  `tid` int unsigned NOT NULL DEFAULT '0',
  KEY `tid` (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `unbanrequests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip` char(15) NOT NULL DEFAULT '',
  `realip` char(15) NOT NULL DEFAULT '',
  `email` char(64) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `added` int unsigned NOT NULL DEFAULT '0',
  `reply` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `usergroups` (
  `gid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `disporder` smallint unsigned NOT NULL DEFAULT '0',
  `type` tinyint unsigned NOT NULL DEFAULT '1',
  `title` char(20) NOT NULL DEFAULT '',
  `usertitle` varchar(120) NOT NULL DEFAULT '',
  `description` tinytext NOT NULL,
  `isbannedgroup` tinyint(1) NOT NULL DEFAULT '0',
  `cansettingspanel` tinyint(1) NOT NULL DEFAULT '0',
  `canstaffpanel` tinyint(1) NOT NULL DEFAULT '0',
  `floodlimit` tinyint unsigned NOT NULL DEFAULT '60',
  `namestyle` char(255) NOT NULL DEFAULT '{username}',
  `issupermod` tinyint(1) NOT NULL DEFAULT '0',
  `isvipgroup` enum('yes','no') NOT NULL DEFAULT 'no',
  `canfreeleech` enum('yes','no') NOT NULL DEFAULT 'no',
  `candeletetorrent` tinyint(1) NOT NULL DEFAULT '0',
  `attachquota` int unsigned NOT NULL DEFAULT '0',
  `maxpmrecipients` int unsigned NOT NULL DEFAULT '5',
  `pmquota` int unsigned NOT NULL DEFAULT '0',
  `showforumteam` tinyint(1) NOT NULL DEFAULT '0',
  `cansendemail` tinyint(1) NOT NULL DEFAULT '0',
  `cansendemailoverride` tinyint(1) NOT NULL DEFAULT '0',
  `canusepms` tinyint(1) NOT NULL DEFAULT '0',
  `cansendpms` tinyint(1) NOT NULL DEFAULT '0',
  `canoverridepm` tinyint(1) NOT NULL DEFAULT '0',
  `cantrackpms` tinyint(1) NOT NULL DEFAULT '0',
  `candenypmreceipts` tinyint(1) NOT NULL DEFAULT '0',
  `canview` tinyint(1) NOT NULL DEFAULT '0',
  `canviewthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostreplys` tinyint(1) NOT NULL DEFAULT '0',
  `caneditposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostpolls` tinyint(1) NOT NULL DEFAULT '0',
  `cansearch` tinyint(1) NOT NULL DEFAULT '0',
  `candlattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canviewboardclosed` tinyint(1) NOT NULL DEFAULT '0',
  `canpostattachments` tinyint(1) NOT NULL DEFAULT '0',
  `max_screenshots` tinyint UNSIGNED NOT NULL DEFAULT '5',
  `caneditattachments` tinyint(1) NOT NULL DEFAULT '0',
  `modposts` tinyint(1) NOT NULL DEFAULT '0',
  `modthreads` tinyint(1) NOT NULL DEFAULT '0',
  `mod_edit_posts` tinyint(1) NOT NULL DEFAULT '0',
  `modattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canvotepolls` tinyint(1) NOT NULL DEFAULT '0',
  `canundovotes` tinyint(1) NOT NULL DEFAULT '0',
  `image` varchar(300) NOT NULL DEFAULT '',
  `canviewwolinvis` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(120) NOT NULL DEFAULT '',
  `password` varchar(120) NOT NULL DEFAULT '',
  `loginkey` varchar(50) NOT NULL DEFAULT '',
  `email` char(64) NOT NULL DEFAULT '',
  `ustatus` enum('pending','confirmed') NOT NULL DEFAULT 'pending',
  `enabled` enum('yes','no') NOT NULL DEFAULT 'yes',
  `added` int unsigned NOT NULL DEFAULT '0',
  `last_login` int unsigned NOT NULL DEFAULT '0',
  `timeonline` int unsigned NOT NULL DEFAULT '0',
  `lastpost` int unsigned NOT NULL DEFAULT '0',
  `lastactive` int unsigned NOT NULL DEFAULT '0',
  `lastvisit` int unsigned NOT NULL DEFAULT '0',
  `stylesheet` char(32) DEFAULT NULL,
  `regip` varbinary(16) NOT NULL DEFAULT '',
  `lastip` varbinary(16) NOT NULL DEFAULT '',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `usertitle` varchar(250) NOT NULL DEFAULT '',
  `country` int unsigned NOT NULL DEFAULT '1',
  `notifs` varchar(300) NOT NULL DEFAULT '',
  `modcomment` text,
  `donor` enum('yes','no') NOT NULL DEFAULT 'no',
  `warned` enum('yes','no') NOT NULL DEFAULT 'no',
  `lastwarned` int unsigned NOT NULL DEFAULT '0',
  `warnedby` int unsigned NOT NULL DEFAULT '0',
  `timeswarned` int unsigned NOT NULL DEFAULT '0',
  `warneduntil` int unsigned NOT NULL DEFAULT '0',
  `leechwarn` enum('yes','no') NOT NULL DEFAULT 'no',
  `leechwarnuntil` int unsigned NOT NULL DEFAULT '0',
  `torrentsperpage` tinyint unsigned NOT NULL DEFAULT '0',
  `postsperpage` tinyint unsigned NOT NULL DEFAULT '0',
  `threadsperpages` smallint unsigned NOT NULL DEFAULT '0',
  `passkey` char(32) NOT NULL DEFAULT '',
  `timezone` varchar(5) NOT NULL DEFAULT '0',
  `timeformat` varchar(4) NOT NULL DEFAULT '',
  `dateformat` varchar(4) NOT NULL DEFAULT '',
  `dst` tinyint(1) NOT NULL DEFAULT '0',
  `dstcorrection` tinyint(1) NOT NULL DEFAULT '0',
  `invites` int unsigned NOT NULL DEFAULT '0',
  `invited_by` int unsigned NOT NULL DEFAULT '0',
  `seedbonus` decimal(9,1) NOT NULL DEFAULT '0.0',
  `bonuscomment` text,
  `lastinvite` int unsigned NOT NULL DEFAULT '0',
  `announce_read` enum('yes','no') NOT NULL DEFAULT 'yes',
  `usergroup` smallint unsigned NOT NULL DEFAULT '0',
  `additionalgroups` varchar(200) NOT NULL DEFAULT '',
  `displaygroup` smallint unsigned NOT NULL DEFAULT '0',
  `oldusergroup` int unsigned NOT NULL DEFAULT '2',
  `avatar` varchar(200) NOT NULL DEFAULT '',
  `avatardimensions` varchar(10) NOT NULL DEFAULT '',
  `avatartype` varchar(10) NOT NULL DEFAULT '0',
  `signature` varchar(255) DEFAULT NULL,
  `birthday` varchar(15) NOT NULL DEFAULT '',
  `birthdayprivacy` varchar(4) NOT NULL DEFAULT 'all',
  `totalpms` int unsigned NOT NULL DEFAULT '0',
  `unreadpms` int unsigned NOT NULL DEFAULT '0',
  `postnum` int unsigned NOT NULL DEFAULT '0',
  `threadnum` int unsigned NOT NULL DEFAULT '0',
  `comms` int unsigned NOT NULL DEFAULT '0',
  `invisible` tinyint(1) NOT NULL DEFAULT '0',
  `commentpm` tinyint(1) NOT NULL DEFAULT '1',
  `showredirect` tinyint(1) NOT NULL DEFAULT '0',
  `subscriptionmethod` tinyint(1) NOT NULL DEFAULT '0',
  `ignorelist` text NOT NULL,
  `loginattempts` smallint unsigned NOT NULL DEFAULT '0',
  `loginlockoutexpiry` int unsigned NOT NULL DEFAULT '0',
  `buddylist` text NOT NULL,
  `buddyrequestsauto` tinyint(1) NOT NULL DEFAULT '0',
  `buddyrequestspm` tinyint(1) NOT NULL DEFAULT '1',
  `daysprune` smallint unsigned NOT NULL DEFAULT '0',
  `pmnotice` tinyint(1) NOT NULL DEFAULT '1',
  `pmnotify` tinyint(1) NOT NULL DEFAULT '0',
  `receivepms` tinyint(1) NOT NULL DEFAULT '1',
  `pmfolders` text NOT NULL,
  `moderateposts` tinyint(1) NOT NULL DEFAULT '0',
  `moderationtime` int unsigned NOT NULL DEFAULT '0',
  `hideemail` tinyint(1) NOT NULL DEFAULT '0',
  `allownotices` tinyint(1) NOT NULL DEFAULT '0',
  `receivefrombuddy` tinyint(1) NOT NULL DEFAULT '0',
  `threadmode` varchar(8) NOT NULL DEFAULT '',
  `canupload` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `candownload` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `cancomment` tinyint UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `passkey` (`passkey`),
  KEY `uploaded` (`uploaded`),
  KEY `added` (`added`),
  KEY `usergroup` (`usergroup`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `user_devices` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid`         INT UNSIGNED NOT NULL,
  `fingerprint` VARCHAR(32)  NOT NULL,
  `user_agent`  VARCHAR(255) NOT NULL DEFAULT '',
  `first_seen`  INT UNSIGNED NOT NULL DEFAULT '0',
  `last_seen`   INT UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_fp` (`uid`, `fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS `bonus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bonusname` char(50) NOT NULL DEFAULT '',
  `points` decimal(5,1) NOT NULL DEFAULT '0.0',
  `description` tinytext NOT NULL,
  `art` char(10) NOT NULL DEFAULT 'traffic',
  `menge` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `buddyrequests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `touid` int unsigned NOT NULL DEFAULT '0',
  `date` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `touid` (`touid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `delayedmoderation` (
  `did` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(30) NOT NULL DEFAULT '',
  `delaydateline` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `tids` text NOT NULL,
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `inputs` text NOT NULL,
  PRIMARY KEY (`did`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notconnectablepmlog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user` int unsigned NOT NULL DEFAULT '0',
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `requests` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `category_id` int UNSIGNED NOT NULL DEFAULT '0',
  `year` smallint UNSIGNED DEFAULT NULL,
  `status` enum('open','filled','cancelled') NOT NULL DEFAULT 'open',
  `filled_by` int UNSIGNED DEFAULT NULL,
  `torrent_id` int UNSIGNED DEFAULT NULL,
  `votes` int UNSIGNED NOT NULL DEFAULT '1',
  `bounty` decimal(9,1) NOT NULL DEFAULT '0.0',
  `created_at` int UNSIGNED NOT NULL,
  `filled_at` int UNSIGNED DEFAULT NULL,
  `updated_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `request_votes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `bounty` decimal(9,1) NOT NULL DEFAULT '0.0',
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_vote` (`request_id`,`user_id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `request_comments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `offers` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `category_id` int UNSIGNED NOT NULL DEFAULT '0',
  `year` smallint UNSIGNED DEFAULT NULL,
  `status` enum('open','uploaded','cancelled') NOT NULL DEFAULT 'open',
  `torrent_id` int UNSIGNED DEFAULT NULL,
  `requests` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` int UNSIGNED NOT NULL,
  `uploaded_at` int UNSIGNED DEFAULT NULL,
  `updated_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `offer_votes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `offer_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_vote` (`offer_id`,`user_id`),
  KEY `idx_offer_id` (`offer_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `offer_comments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `offer_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_offer_id` (`offer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
SQL;
}

/**
 * Разбирает схему на отдельные операторы CREATE TABLE. Нормализует \r\n → \n
 * перед разбивкой (на Windows-сервере файл может физически хранить \r\n, и
 * explode(";\n", ...) тогда не находит границу между запросами - соседние
 * CREATE TABLE склеиваются в один запрос и падают с синтаксической ошибкой).
 */
function getTableStatements(): array
{
    $tables = str_replace("\r\n", "\n", getTableSchemaSql());
    $statements = array_filter(array_map('trim', explode(";\n", $tables)));
    $out = [];
    foreach ($statements as $sql) {
        if (empty($sql)) continue;
        preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $sql, $m);
        $out[] = ['table' => $m[1] ?? 'unknown', 'sql' => $sql];
    }
    return $out;
}

/**
 * Полный упорядоченный план установки: каждая CREATE TABLE - отдельный шаг
 * (для максимально гранулярного живого прогресса), плюс именованные фазы
 * после неё. Индекс в этом массиве = step_index, который дёргает фронтенд.
 */
function getInstallSteps(): array
{
    $steps = [];
    foreach (getTableStatements() as $t) {
        $steps[] = ['type' => 'table', 'label' => "Table `{$t['table']}`"];
    }
    $steps[] = ['type' => 'categories',    'label' => 'Default categories'];
    $steps[] = ['type' => 'country',       'label' => 'Default country'];
    $steps[] = ['type' => 'admin',         'label' => 'Administrator account + auto-login'];
    $steps[] = ['type' => 'staffteam',     'label' => 'config/STAFFTEAM'];
    $steps[] = ['type' => 'news_announce', 'label' => 'Welcome news, announcement & cache'];
    $steps[] = ['type' => 'config_files',  'label' => 'Generating config.php, settings.php, config_announce.php'];
    $steps[] = ['type' => 'install_data',  'label' => 'sql/install_data.sql'];
    $steps[] = ['type' => 'faq',           'label' => 'sql/faq.sql (FAQ + Rules)'];
    $steps[] = ['type' => 'group',         'label' => 'sql/group.sql (default usergroups)'];
    $steps[] = ['type' => 'finalize',      'label' => 'Finalizing installation'];
    return $steps;
}

/**
 * Выполняет РОВНО ОДИН шаг установки по индексу и сразу возвращает результат.
 * Каждый вызов - собственный HTTP-запрос/ответ (AJAX из install.php?step=5),
 * поэтому setcookie() в шаге 'admin' отрабатывает нормально (заголовки этого
 * конкретного ответа ещё не отправлены - в отличие от подхода с flush() во
 * время одного длинного запроса, где кука для автологина отваливалась).
 * Между шагами (например, admin_id из шага 'admin' нужен шагам 'staffteam'/
 * 'news_announce'/'config_files') состояние переносится через $_SESSION.
 */
function executeInstallStep(int $index): array
{
    global $db;

    $dbSess = $_SESSION['db']    ?? [];
    $site   = $_SESSION['site']  ?? [];
    $admin  = $_SESSION['admin'] ?? [];

    if (!isset($db) || !is_object($db)) {
        try {
            $db = getDb();
        } catch (Throwable $e) {
            return ['status' => 'fail', 'label' => 'Database connection', 'message' => $e->getMessage(), 'elapsed_ms' => 0];
        }
    }

    $steps = getInstallSteps();
    if (!isset($steps[$index])) {
        return ['status' => 'fail', 'label' => 'Unknown step', 'message' => 'Invalid step index', 'elapsed_ms' => 0];
    }
    $step = $steps[$index];
    $t0 = microtime(true);
    $elapsed = fn() => (int) round((microtime(true) - $t0) * 1000);

    try {
        switch ($step['type']) {

            case 'table': {
                $t = getTableStatements()[$index];
                $result = $db->sql_query_prepared($t['sql'], [], 1);
                if ($result === false) {
                    $errNo  = $db->error_number();
                    $errMsg = $db->error_string();
                    if ($errNo && str_contains($errMsg, 'already exists')) {
                        return ['status' => 'ok', 'label' => "Table `{$t['table']}`", 'message' => 'Already existed — skipped', 'elapsed_ms' => $elapsed()];
                    }
                    error_log("[installer] Failed to create table `{$t['table']}`: #{$errNo} {$errMsg}");
                    return ['status' => 'fail', 'label' => "Table `{$t['table']}`", 'message' => "#{$errNo}: {$errMsg}", 'sql' => $t['sql'], 'elapsed_ms' => $elapsed()];
                }
                return ['status' => 'ok', 'label' => "Table `{$t['table']}`", 'message' => 'Created', 'elapsed_ms' => $elapsed()];
            }

            case 'categories': {
                $cats = [
                    ['1080p',   'fa-solid fa-film fa-shake'],
                    ['2160p',   'fa-solid fa-clapperboard'],
                    ['720p',    'fa-solid fa-tv'],
                    ['BluRay',  'fa-solid fa-compact-disc fa-spin'],
                    ['UltraHD', 'fa-solid fa-satellite-dish'],
                ];
                foreach ($cats as $cat) {
                    $db->sql_query_prepared(
                        'INSERT IGNORE INTO categories (name, icon, type, minclassread) VALUES (?, ?, ?, ?)',
                        [$cat[0], $cat[1], 'c', 0]
                    );
                }
                return ['status' => 'ok', 'label' => 'Default categories', 'message' => count($cats) . ' seeded', 'elapsed_ms' => $elapsed()];
            }

            case 'country': {
                $db->sql_query_prepared(
                    'INSERT IGNORE INTO countries (id, name, flagpic) VALUES (?, ?, ?)',
                    [1, 'Unknown', 'unknown.gif']
                );
                return ['status' => 'ok', 'label' => 'Default country', 'message' => 'Seeded', 'elapsed_ms' => $elapsed()];
            }

            case 'admin': {
                $now       = time();
                $loginkey  = generate_loginkey();
                $pass_hash = password_hash($admin['password'], PASSWORD_DEFAULT);

                $db->sql_query_prepared(
                    'INSERT INTO users (
                        username, password, loginkey, email, ustatus, enabled,
                        added, lastactive, lastvisit, usergroup, timezone, ignorelist,
                        buddylist, pmfolders, notifs, announce_read
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $admin['username'], $pass_hash, $loginkey, $admin['email'],
                        'confirmed', 'yes', $now, $now, $now, 8, '0', '', '',
                        '0**$%%$1**$%%$2**$%%$3**$%%$4**', '', 'no',
                    ]
                );
                $admin_id = $db->insert_id();

                // Автологин: та же кука, что и на обычном сайте (uid_loginkey).
                // Это отдельный AJAX-ответ (не продолжение застриженного вывода
                // прогресса) - setcookie() тут отрабатывает штатно.
                $cookieHost = parse_url($site['url'] ?? '', PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '');
                setcookie('mybbuser', $admin_id . '_' . $loginkey, [
                    'expires'  => time() + 60 * 60 * 24 * 365,
                    'path'     => '/',
                    'domain'   => $cookieHost,
                    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                $_SESSION['install_ctx'] = ['admin_id' => $admin_id, 'loginkey' => $loginkey, 'now' => $now];

                return ['status' => 'ok', 'label' => 'Administrator account', 'message' => "`{$admin['username']}` created (id {$admin_id}), auto-login cookie set", 'elapsed_ms' => $elapsed()];
            }

            case 'staffteam': {
                $ctx      = $_SESSION['install_ctx'] ?? [];
                $admin_id = $ctx['admin_id'] ?? 0;

                $staffteam_dir  = __DIR__ . '/config';
                $staffteam_file = $staffteam_dir . '/STAFFTEAM';
                if (!is_dir($staffteam_dir)) {
                    @mkdir($staffteam_dir, 0755, true);
                }
                if (file_put_contents($staffteam_file, $admin['username'] . ':' . $admin_id) === false) {
                    return ['status' => 'fail', 'label' => 'config/STAFFTEAM', 'message' => 'Could not write file — check permissions', 'elapsed_ms' => $elapsed()];
                }
                return ['status' => 'ok', 'label' => 'config/STAFFTEAM', 'message' => 'Written', 'elapsed_ms' => $elapsed()];
            }

            case 'news_announce': {
                $ctx      = $_SESSION['install_ctx'] ?? [];
                $admin_id = $ctx['admin_id'] ?? 0;
                $now      = $ctx['now'] ?? time();

                $db->sql_query_prepared(
                    'INSERT INTO news (userid, added, body, title) VALUES (?, ?, ?, ?)',
                    [$admin_id, $now, '<p>Welcome to <strong>' . $site['name'] . '</strong>! The tracker is now online.</p>', 'Welcome!']
                );
                $db->sql_query_prepared(
                    'INSERT INTO announcements (
                        subject, message, uid, added, updated, startdate, enddate, minclassread, type
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    ['Welcome to ' . $site['name'], '<p>The tracker is now online and ready to use!</p>', $admin_id, $now, $now, $now, 0, 0, 'tracker']
                );

                $GLOBALS['db'] = $db;
              

                return ['status' => 'ok', 'label' => 'News, announcement & cache', 'message' => 'Done', 'elapsed_ms' => $elapsed()];
            }

            case 'config_files': {
                $db_cfg   = $dbSess;
                $ctx      = $_SESSION['install_ctx'] ?? [];
                $admin_id = $ctx['admin_id'] ?? 0;

                $cookieDomain = $site['cookie_domain'] ?: ('.' . preg_replace('#^https?://(www\.)?#', '', $site['url']));
                $announce_url = $site['url'] . '/announce.php';

                $config_php  = "<?php\n";
                $config_php .= "  \$config['database']['type'] = '" . addslashes($db_cfg['type'] ?? 'mysqli') . "';\n";
                $config_php .= "  \$config['database']['database'] = '" . addslashes($db_cfg['database']) . "';\n";
                $config_php .= "  \$config['database']['hostname'] = '" . addslashes($db_cfg['hostname']) . "';\n";
                $config_php .= "  \$config['database']['username'] = '" . addslashes($db_cfg['username']) . "';\n";
                $config_php .= "  \$config['database']['password'] = '" . addslashes($db_cfg['password']) . "';\n";
                $config_php .= "  \$config['database']['encoding'] = 'utf8mb4';\n";
                $config_php .= "  \$config['cache_store'] = 'files';\n";
                $config_php .= "  \$config['super_admins'] = '{$admin_id}';\n";
                $config_php .= "  \$config['log_pruning'] = array(\n";
                $config_php .= "    'admin_logs' => 365,\n    'mod_logs' => 365,\n    'task_logs' => 30,\n";
                $config_php .= "    'mail_logs' => 180,\n    'user_mail_logs' => 180,\n    'promotion_logs' => 180\n  );\n";

                $settings_sql_file = __DIR__ . '/sql/default_settings.sql';
                if (!file_exists($settings_sql_file)) {
                    return ['status' => 'fail', 'label' => 'Config files', 'message' => 'Missing file: sql/default_settings.sql', 'elapsed_ms' => $elapsed()];
                }
                $settings_sql = file_get_contents($settings_sql_file);

                $encryptionKey = random_str(32);

                $placeholders = [
                    '{{SITENAME}}'       => $site['name'],
                    '{{BASEURL}}'        => $site['url'],
                    '{{SITEEMAIL}}'      => $site['email'],
                    '{{PRIVATEPATCH}}'   => $site['privatepatch'],
                    '{{TIMEZONE}}'       => $site['timezone'],
                    '{{COOKIEDOMAIN}}'   => $cookieDomain,
                    '{{ENCRYPTION_KEY}}' => $encryptionKey,
                    '{{ANNOUNCE_URL}}'   => $announce_url,
                    '{{SMTP_HOST}}'      => $site['smtp_host'],
                    '{{SMTP_USER}}'      => $site['smtp_user'],
                    '{{SMTP_PASS}}'      => $site['smtp_pass'],
                    '{{SMTP_PORT}}'      => $site['smtp_port'],
                    '{{DB_HOST}}'        => $db_cfg['hostname'],
                    '{{DB_USER}}'        => $db_cfg['username'],
                    '{{DB_PASS}}'        => $db_cfg['password'],
                    '{{DB_NAME}}'        => $db_cfg['database'],
                ];

                $settings_params = [];
                $settings_sql = preg_replace_callback(
                    "/'\{\{(\w+)\}\}'/",
                    function (array $m) use ($placeholders, &$settings_params): string {
                        $key = '{{' . $m[1] . '}}';
                        $settings_params[] = $placeholders[$key] ?? '';
                        return '?';
                    },
                    $settings_sql
                );

                try {
                    $db->sql_query_prepared($settings_sql, $settings_params);
                } catch (Throwable $e) {
                    return ['status' => 'fail', 'label' => 'Config files', 'message' => 'Failed to insert default settings: ' . $e->getMessage(), 'sql' => $settings_sql, 'elapsed_ms' => $elapsed()];
                }

                $settings_out = '';
                $q = $db->sql_query_prepared('SELECT name, value FROM settings');
                while ($row = $db->fetch_array($q)) {
                    $n = $row['name'];
                    $v = addcslashes($row['value'], '\\"$');
                    $settings_out .= "\${$n} = \"{$v}\";\n";
                }
                $s = "<?php\n"
                   . "/*********************************\\\n"
                   . "  DO NOT EDIT THIS FILE, PLEASE USE\n"
                   . "  THE SETTINGS EDITOR\n"
                   . "\\*********************************/\n\n"
                   . $settings_out
                   . "\n";

                $announce_php  = "<?php #DO NOT EDIT THIS FILE, PLEASE USE THE SETTINGS PANEL!!\n";
                $announce_php .= "if(!defined('IN_ANNOUNCE')) die('Hacking attempt!');\n";
                $announce_php .= "\$announce_actions = 'yes';\n\$aggressivecheat = 'yes';\n\$nc = 'no';\n";
                $announce_php .= "\$announce_wait = '0';\n\$announce_interval = '900';\n\$max_rate = '2097152';\n";
                $announce_php .= "\$bannedclientdetect = 'no';\n\$allowed_clients = '-UT1610-,-AZ3034-,-UT1750-';\n";
                $announce_php .= "\$detectbrowsercheats = 'no';\n\$checkconnectable = 'no';\n\$checkip = 'no';\n";
                $announce_php .= "\$mysql_host = '" . addslashes($db_cfg['hostname']) . "';\n";
                $announce_php .= "\$mysql_user = '" . addslashes($db_cfg['username']) . "';\n";
                $announce_php .= "\$mysql_pass = '" . addslashes($db_cfg['password']) . "';\n";
                $announce_php .= "\$mysql_db = '" . addslashes($db_cfg['database']) . "';\n";
                $announce_php .= "\$BASEURL = '" . addslashes($site['url']) . "';\n";
                $announce_php .= "\$SITENAME = '" . addslashes($site['name']) . "';\n";
                $announce_php .= "\$privatetrackerpatch = '" . $site['privatepatch'] . "';\n";
                $announce_php .= "\$gzipcompress = 'no';\n\$charset = 'UTF-8';\n\$aggressivecheckip = 'no';\n";
                $announce_php .= "\$snatchmod = 'yes';\n\$bonus = 'enable';\n\$kpsseed = '5.0';\n";
                $announce_php .= "\$bdayreward = 'yes';\n\$bdayrewardtype = 'freeleech';\n?>\n";

                $writeErrors = [];
                if (@file_put_contents(__DIR__ . '/include/config.php', $config_php) === false) { $writeErrors[] = 'config.php'; }
                if (@file_put_contents(__DIR__ . '/include/settings.php', $s) === false) { $writeErrors[] = 'settings.php'; }
                if (@file_put_contents(__DIR__ . '/include/config_announce.php', $announce_php) === false) { $writeErrors[] = 'config_announce.php'; }
                if (!is_dir(__DIR__ . '/cache')) { @mkdir(__DIR__ . '/cache', 0755, true); }

                if ($writeErrors) {
                    return ['status' => 'fail', 'label' => 'Config files', 'message' => 'Cannot write: ' . implode(', ', $writeErrors) . ' — check permissions', 'elapsed_ms' => $elapsed()];
                }
                return ['status' => 'ok', 'label' => 'Config files', 'message' => 'config.php, settings.php, config_announce.php written', 'elapsed_ms' => $elapsed()];
            }

            case 'install_data': {
                $data_sql = @file_get_contents(__DIR__ . '/sql/install_data.sql');
                if (!$data_sql) {
                    return ['status' => 'fail', 'label' => 'sql/install_data.sql', 'message' => 'Missing or unreadable', 'elapsed_ms' => $elapsed()];
                }
                $data_sql = str_replace("\r\n", "\n", $data_sql);
                foreach (preg_split('/\n\n+/', $data_sql) as $block) {
                    $block = trim($block);
                    if (empty($block)) continue;
                    $lines = array_filter(explode("\n", $block), fn($l) => strpos(trim($l), '--') !== 0);
                    $sql = trim(implode("\n", $lines));
                    if (!preg_match('/^INSERT/i', $sql)) continue;
                    try {
                        $db->sql_query_prepared(rtrim($sql, ';'));
                    } catch (Throwable $e) {
                        return ['status' => 'fail', 'label' => 'sql/install_data.sql', 'message' => $e->getMessage(), 'sql' => $sql, 'elapsed_ms' => $elapsed()];
                    }
                }
                return ['status' => 'ok', 'label' => 'sql/install_data.sql', 'message' => 'Seed data imported', 'elapsed_ms' => $elapsed()];
            }

            case 'faq': {
                $faq_sql = @file_get_contents(__DIR__ . '/sql/faq.sql');
                if (!$faq_sql) {
                    return ['status' => 'fail', 'label' => 'sql/faq.sql', 'message' => 'Missing or unreadable', 'elapsed_ms' => $elapsed()];
                }
                $faq_sql = str_replace("\r\n", "\n", $faq_sql);
                $faq_sql = str_ireplace('INSERT INTO faq', 'INSERT IGNORE INTO `faq`', $faq_sql);
                foreach (array_filter(array_map('trim', explode("\n", $faq_sql))) as $sql) {
                    if (stripos($sql, 'INSERT') !== 0) continue;
                    try {
                        $db->sql_query_prepared(rtrim($sql, ';'));
                    } catch (Throwable $e) {
                        return ['status' => 'fail', 'label' => 'sql/faq.sql', 'message' => $e->getMessage(), 'sql' => $sql, 'elapsed_ms' => $elapsed()];
                    }
                }
                return ['status' => 'ok', 'label' => 'sql/faq.sql (FAQ + Rules)', 'message' => 'Imported', 'elapsed_ms' => $elapsed()];
            }

            case 'group': {
                $usergroups_sql = @file_get_contents(__DIR__ . '/sql/group.sql');
                if (!$usergroups_sql) {
                    return ['status' => 'fail', 'label' => 'sql/group.sql', 'message' => 'Missing — default usergroups will NOT be created', 'elapsed_ms' => $elapsed()];
                }
                $usergroups_sql = str_replace(["\r\n", '\\"'], ["\n", '"'], $usergroups_sql);
                $usergroups_sql = str_ireplace('INSERT IGNORE INTO usergroups', 'INSERT IGNORE INTO `usergroups`', $usergroups_sql);
                foreach (array_filter(array_map('trim', explode("\n", $usergroups_sql))) as $sql) {
                    if (stripos($sql, 'INSERT') !== 0) continue;
                    try {
                        $db->sql_query_prepared(rtrim($sql, ';'));
                    } catch (Throwable $e) {
                        return ['status' => 'fail', 'label' => 'sql/group.sql', 'message' => $e->getMessage(), 'sql' => $sql, 'elapsed_ms' => $elapsed()];
                    }
                }
                return ['status' => 'ok', 'label' => 'sql/group.sql (default usergroups)', 'message' => 'Imported', 'elapsed_ms' => $elapsed()];
            }

            case 'finalize': {
                file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));
                $_SESSION['just_installed'] = true;
                unset($_SESSION['install_ctx']);
                return ['status' => 'ok', 'label' => 'Finalizing', 'message' => 'Installation locked and complete', 'elapsed_ms' => $elapsed()];
            }

            default:
                return ['status' => 'fail', 'label' => 'Unknown step', 'message' => 'No handler for type ' . $step['type'], 'elapsed_ms' => $elapsed()];
        }
    } catch (Throwable $e) {
        return ['status' => 'fail', 'label' => $step['label'], 'message' => $e->getMessage(), 'elapsed_ms' => $elapsed()];
    }
}


// ── Requirements check ───────────────────────────────────────────────────────
function checkRequirements(): array
{
    // Рабочие директории трекера — пути соответствуют дефолтам из
    // include/settings.php ($uploadspath, $torrent_dir, $avataruploadpath).
    // Если их ещё нет — пробуем создать сразу здесь, чтобы проверка
    // отражала реальную готовность, а не просто "директории не существует".
    $workDirs = [
        'uploads'         => __DIR__ . '/uploads',
        'torrents'        => __DIR__ . '/torrents',
        'uploads/avatars' => __DIR__ . '/uploads/avatars',
    ];
    $dirChecks = [];
    foreach ($workDirs as $label => $path) {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        $ok = is_dir($path) && is_writable($path);
        $dirChecks[] = ["{$label}/ writable", $ok, $ok ? 'Writable' : 'Missing or not writable'];
    }

    return array_merge([
        ['PHP Version ≥ 8.0',    version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION],
        ['MySQLi',               extension_loaded('mysqli'),   extension_loaded('mysqli') ? 'OK' : 'Missing'],
        ['GD',                   extension_loaded('gd'),       extension_loaded('gd') ? 'OK' : 'Missing'],
        ['JSON',                 extension_loaded('json'),     'OK'],
        ['MB String',            extension_loaded('mbstring'), extension_loaded('mbstring') ? 'OK' : 'Missing'],
        ['OpenSSL',              extension_loaded('openssl'),  extension_loaded('openssl') ? 'OK' : 'Missing'],
        ['cURL',                 extension_loaded('curl'),     extension_loaded('curl') ? 'OK' : 'Missing'],
        ['DB base driver',       file_exists(INC_PATH . '/db_base.php'), file_exists(INC_PATH . '/db_base.php') ? 'Found' : 'Missing: include/db_base.php'],
        ['config.php writable',  is_writable(__DIR__) || (file_exists(__DIR__.'/include/config.php') && is_writable(__DIR__.'/include/config.php')), is_writable(__DIR__) ? 'Writable' : 'Not writable'],
        ['settings.php writable',is_writable(__DIR__) || (file_exists(__DIR__.'/include/settings.php') && is_writable(__DIR__.'/include/settings.php')), is_writable(__DIR__) ? 'Writable' : 'Not writable'],
    ], $dirChecks);
}

$reqs  = ($step === 1) ? checkRequirements() : [];
$allOk = true;
foreach ($reqs as $r) { if (!$r[1]) { $allOk = false; break; } }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mybb Based Torrent Tracker — Installer v<?= INSTALLER_VERSION ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --accent: #6366f1;
        --accent-dark: #4338ca;
        --accent-soft: rgba(99, 102, 241, .12);
        --success-grad: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
    }

    body {
        font-family: 'Inter', -apple-system, sans-serif;
        background: radial-gradient(circle at top left, #eef0fd 0%, #f7f7fb 55%, #ffffff 100%);
        min-height: 100vh;
        margin: 0;
    }

    .installer-container { max-width: 800px; margin: 0 auto; }

    .main-card {
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 30px 70px -20px rgba(0, 0, 0, .25);
        overflow: hidden;
    }

    .card-header {
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: #fff;
        padding: 1.75rem 1.9rem;
        border-bottom: none;
        position: relative;
    }

    .card-header h2 {
        font-family: 'Oswald', sans-serif;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .01em;
        font-size: 1.5rem;
    }

    .card-header .version-badge {
        font-family: 'Oswald', sans-serif;
        font-weight: 600;
        font-size: .78rem;
        letter-spacing: .05em;
        background: rgba(255, 255, 255, .15);
        border: 1px solid rgba(255, 255, 255, .3);
        backdrop-filter: blur(4px);
    }

    .step-progress { padding: 1.6rem 1.9rem; background: #faf9ff; border-bottom: 1px solid #eee9fb; }
    .step-item { position: relative; flex: 1; text-align: center; }

    .step-circle {
        width: 40px; height: 40px;
        background: #e4e1f5;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 8px;
        font-family: 'Oswald', sans-serif;
        font-weight: 700;
        color: #9691b8;
        transition: all .3s ease;
        position: relative; z-index: 2;
    }
    .step-item.active .step-circle {
        background: var(--accent);
        color: #fff;
        box-shadow: 0 0 0 5px var(--accent-soft);
        transform: scale(1.12);
    }
    .step-item.completed .step-circle { background: #10b981; color: #fff; }

    .step-label {
        font-family: 'Oswald', sans-serif;
        font-size: .68rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #9691b8;
        font-weight: 600;
    }
    .step-item.active .step-label { color: var(--accent-dark); }
    .step-item.completed .step-label { color: #0d9668; }

    .step-connector {
        position: absolute; top: 20px; left: 50%;
        width: 100%; height: 2px;
        background: #e4e1f5; z-index: 1;
    }
    .step-item.completed .step-connector { background: #10b981; }
    .step-item:last-child .step-connector { display: none; }

    .form-label { font-family: 'Oswald', sans-serif; font-weight: 500; font-size: .85rem; letter-spacing: .01em; color: #33304d; }

    .form-control, .form-select {
        border-radius: .7rem;
        border: 2px solid #ece9fa;
        transition: all .2s ease;
        padding: .6rem .9rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 .2rem var(--accent-soft);
    }

    .btn { border-radius: .7rem; padding: .7rem 1.4rem; font-weight: 600; transition: all .2s ease; }
    .btn-primary { background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%); border: none; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -6px var(--accent-soft); }
    .btn-outline-primary { border: 2px solid var(--accent); color: var(--accent-dark); }
    .btn-outline-primary:hover { background: var(--accent); border-color: var(--accent); }
    .btn-success { background: var(--success-grad); border: none; color: #fff; }
    .btn-success:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -6px rgba(16, 185, 129, .35); }

    .requirement-item { display: flex; align-items: center; padding: .8rem 0; border-bottom: 1px solid #f2f0fa; }
    .requirement-item:last-child { border-bottom: none; }
    .requirement-status { width: 30px; text-align: center; margin-right: 1rem; }

    .info-card {
        background: linear-gradient(135deg, #f2f0fd 0%, #e5e0fa 100%);
        border-radius: 1rem;
        padding: 1.25rem;
        margin-bottom: 1.25rem;
        border: 1px solid #e4dffa;
    }
    .info-card h5, .info-card h6 { font-family: 'Oswald', sans-serif; font-weight: 600; }

    .alert-custom { border-radius: 1rem; border-left: 4px solid; }

    .fade-in { animation: fadeIn .5s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>
<div class="installer-container fade-in p-3">
    <div class="main-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-satellite-dish me-2"></i>Mybb Based Torrent Tracker Installer</h2>
                    <p class="mb-0 opacity-75">Professional Torrent Tracker Setup</p>
                </div>
                <span class="badge version-badge px-3 py-2 rounded-pill">
                    <i class="fas fa-code-branch me-1"></i> v<?= INSTALLER_VERSION ?>
                </span>
            </div>
        </div>

        <div class="step-progress">
            <div class="d-flex justify-content-between position-relative">
                <?php foreach ($steps as $num => $label): ?>
                <div class="step-item <?= $num < $step ? 'completed' : ($num == $step ? 'active' : '') ?>">
                    <div class="step-circle">
                        <?php if ($num < $step): ?><i class="fas fa-check"></i><?php else: ?><?= $num ?><?php endif; ?>
                    </div>
                    <div class="step-label"><?= $label ?></div>
                    <?php if ($num < count($steps)): ?><div class="step-connector"></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="p-4">
            <?php if ($errors): ?>
            <div class="alert alert-danger alert-custom mb-4" style="border-left-color:#dc3545">
                <div class="d-flex">
                    <div class="me-3"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                    <div>
                        <strong>Errors:</strong>
                        <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-clipboard-list fa-2x me-3"></i><h5 class="mb-0">System Requirements Check</h5></div><p class="text-muted small mb-0">All checks must pass before proceeding.</p></div>
            <div class="mb-4">
                <?php foreach ($reqs as $req): ?>
                <div class="requirement-item">
                    <div class="requirement-status"><i class="fas fa-<?= $req[1] ? 'check-circle text-success' : 'times-circle text-danger' ?> fa-lg"></i></div>
                    <div class="flex-grow-1"><strong><?= $req[0] ?></strong><div class="small text-muted"><?= htmlspecialchars($req[2]) ?></div></div>
                    <span class="badge bg-<?= $req[1] ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $req[1] ? 'success' : 'danger' ?> px-3 py-1 rounded-pill">
                        <i class="fas fa-<?= $req[1] ? 'check' : 'times' ?> me-1"></i><?= $req[1] ? 'Passed' : 'Failed' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($allOk): ?>
                <a href="install.php?step=2" class="btn btn-primary btn-lg w-100"><i class="fas fa-arrow-right me-2"></i>Continue to Database Setup</a>
            <?php else: ?>
                <div class="alert alert-warning text-center mb-3"><i class="fas fa-tools me-2"></i>Please fix the issues above before continuing</div>
                <a href="install.php?step=1" class="btn btn-outline-secondary w-100"><i class="fas fa-sync-alt me-2"></i>Recheck</a>
            <?php endif; ?>

            <?php elseif ($step === 2): ?>
            <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-database fa-2x me-3"></i><h5 class="mb-0">Database Configuration</h5></div><p class="text-muted small mb-0">Connects via your DB class (db_base.php + driver).</p></div>
            <form method="post" action="install.php?step=2"><?= csrf_field() ?>
                <input type="hidden" name="db_type" value="mysqli">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-server me-1"></i>Database Host</label>
                        <input type="text" class="form-control" name="db_host" value="<?= htmlspecialchars($_SESSION['db']['hostname'] ?? 'localhost') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold"><i class="fas fa-database me-1"></i>Database Name</label>
                        <input type="text" class="form-control" name="db_name" value="<?= htmlspecialchars($_SESSION['db']['database'] ?? '') ?>" placeholder="tracker_db" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-user me-1"></i>Username</label>
                        <input type="text" class="form-control" name="db_user" value="<?= htmlspecialchars($_SESSION['db']['username'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-lock me-1"></i>Password</label>
                        <input type="password" class="form-control" name="db_pass" value="<?= htmlspecialchars($_SESSION['db']['password'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-4"><i class="fas fa-database me-2"></i>Test Connection & Continue</button>
            </form>

            <?php elseif ($step === 3): ?>
            <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-globe fa-2x me-3"></i><h5 class="mb-0">Site Configuration</h5></div><p class="text-muted small mb-0">Basic tracker settings.</p></div>
            <form method="post" action="install.php?step=3"><?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold"><i class="fas fa-tag me-1"></i>Site Name</label>
                        <input type="text" class="form-control" name="site_name" value="<?= htmlspecialchars($_SESSION['site']['name'] ?? '') ?>" placeholder="My Awesome Tracker" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold"><i class="fas fa-link me-1"></i>Site URL</label>
                        <input type="url" class="form-control" name="site_url" value="<?= htmlspecialchars($_SESSION['site']['url'] ?? '') ?>" placeholder="https://tracker.example.com" required>
                        <small class="text-muted">No trailing slash.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-envelope me-1"></i>Site Email</label>
                        <input type="email" class="form-control" name="site_email" value="<?= htmlspecialchars($_SESSION['site']['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-clock me-1"></i>Timezone</label>
                        <select class="form-select" name="timezone">
                            <?php for ($i=-12;$i<=14;$i++): ?>
                            <option value="<?=$i?>" <?=(($_SESSION['site']['timezone']??2)==$i)?'selected':''?>>UTC <?=$i>=0?'+'.$i:$i?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                   
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="privatepatch" id="privatepatch" <?= ($_SESSION['site']['privatepatch'] ?? 'yes') === 'yes' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="privatepatch"><i class="fas fa-lock me-1"></i>Private Tracker Mode</label>
                        </div>
                    </div>
                </div>
                <hr class="my-4">
                <h6 class="mb-3"><i class="fas fa-envelope me-2"></i>SMTP <small class="text-muted">(Optional)</small></h6>
                <div class="row g-3">
                    <div class="col-md-8"><input type="text" id="smtp_host" class="form-control" name="smtp_host" value="<?= htmlspecialchars($_SESSION['site']['smtp_host'] ?? 'smtp.gmail.com') ?>" placeholder="SMTP Host"></div>
                    <div class="col-md-4"><input type="text" id="smtp_port" class="form-control" name="smtp_port" value="<?= htmlspecialchars($_SESSION['site']['smtp_port'] ?? '587') ?>" placeholder="Port"></div>
                    <div class="col-md-6"><input type="text" id="smtp_user" class="form-control" name="smtp_user" value="<?= htmlspecialchars($_SESSION['site']['smtp_user'] ?? '') ?>" placeholder="SMTP Username"></div>
                    <div class="col-md-6"><input type="password" id="smtp_pass" class="form-control" name="smtp_pass" value="<?= htmlspecialchars($_SESSION['site']['smtp_pass'] ?? '') ?>" placeholder="SMTP Password"></div>
                </div>
                <div class="mt-3">
                    <button type="button" id="testSmtpBtn" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-paper-plane me-1"></i>Test SMTP Connection
                    </button>
                    <div id="smtpTestResult" class="mt-2"></div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-4"><i class="fas fa-arrow-right me-2"></i>Continue to Admin Setup</button>
            </form>

            <script>
            document.getElementById('testSmtpBtn')?.addEventListener('click', async function () {
                const btn = this;
                const resultBox = document.getElementById('smtpTestResult');
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
                resultBox.innerHTML = '';

                try {
                    const body = new URLSearchParams({
                        ajax_action: 'test_smtp',
                        csrf_token: <?= json_encode($csrfToken) ?>,
                        smtp_host: document.getElementById('smtp_host').value,
                        smtp_port: document.getElementById('smtp_port').value,
                        smtp_user: document.getElementById('smtp_user').value,
                        smtp_pass: document.getElementById('smtp_pass').value,
                    });
                    const resp = await fetch('install.php', { method: 'POST', body });
                    const data = await resp.json();

                    const cls = data.status === 'success' ? 'alert-success' : (data.status === 'skipped' ? 'alert-secondary' : 'alert-danger');
                    const icon = data.status === 'success' ? 'fa-check-circle' : (data.status === 'skipped' ? 'fa-circle-info' : 'fa-triangle-exclamation');
                    resultBox.innerHTML = `<div class="alert ${cls} alert-custom py-2 px-3 mb-0 small"><i class="fas ${icon} me-2"></i>${data.message}</div>`;
                } catch (err) {
                    resultBox.innerHTML = '<div class="alert alert-danger alert-custom py-2 px-3 mb-0 small"><i class="fas fa-triangle-exclamation me-2"></i>Network error while testing SMTP.</div>';
                }

                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
            </script>


            <?php elseif ($step === 4): ?>
            <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-user-shield fa-2x me-3"></i><h5 class="mb-0">Administrator Account</h5></div><p class="text-muted small mb-0">Master admin for your tracker.</p></div>
            <form method="post" action="install.php?step=4"><?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-user me-1"></i>Username</label>
                        <input type="text" class="form-control" name="admin_user" value="<?= htmlspecialchars($_SESSION['admin']['username'] ?? '') ?>" required minlength="3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-envelope me-1"></i>Email</label>
                        <input type="email" class="form-control" name="admin_email" value="<?= htmlspecialchars($_SESSION['admin']['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-lock me-1"></i>Password</span>
                            <button type="button" id="genPassBtn" class="btn btn-link btn-sm p-0 text-decoration-none"><i class="fas fa-dice me-1"></i>Generate</button>
                        </label>
                        <div class="input-group">
                            <input type="password" id="admin_pass" class="form-control" name="admin_pass" required minlength="6">
                            <button type="button" class="btn btn-outline-secondary toggle-pass" data-target="admin_pass"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-check-double me-1"></i>Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="admin_confirm" class="form-control" name="admin_confirm" required>
                            <button type="button" class="btn btn-outline-secondary toggle-pass" data-target="admin_confirm"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-4"><i class="fas fa-arrow-right me-2"></i>Continue to Installation</button>
            </form>

            <script>
            document.querySelectorAll('.toggle-pass').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const input = document.getElementById(this.dataset.target);
                    const icon  = this.querySelector('i');
                    const show  = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    icon.classList.toggle('fa-eye', !show);
                    icon.classList.toggle('fa-eye-slash', show);
                });
            });

            document.getElementById('genPassBtn')?.addEventListener('click', function () {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
                let pass = '';
                const bytes = new Uint32Array(16);
                crypto.getRandomValues(bytes);
                for (let i = 0; i < 16; i++) pass += chars[bytes[i] % chars.length];

                const passField = document.getElementById('admin_pass');
                const confirmField = document.getElementById('admin_confirm');
                passField.type = 'text';
                confirmField.type = 'text';
                passField.value = pass;
                confirmField.value = pass;
                document.querySelectorAll('.toggle-pass i').forEach(function (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                });
            });
            </script>

            <?php elseif ($step === 5): ?>
            <div id="installSummary">
                <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-rocket fa-2x me-3"></i><h5 class="mb-0">Ready to Install</h5></div></div>
                <div class="bg-light rounded p-3 mb-4">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Summary</h6>
                    <div class="row g-2 small">
                        <div class="col-4 fw-semibold">Driver:</div><div class="col-8"><?= htmlspecialchars(strtoupper($_SESSION['db']['type'] ?? 'mysqli')) ?></div>
                        <div class="col-4 fw-semibold">Database:</div><div class="col-8"><?= htmlspecialchars(($_SESSION['db']['database']??'').'@'.($_SESSION['db']['hostname']??'')) ?></div>
                        <div class="col-4 fw-semibold">Site:</div><div class="col-8"><?= htmlspecialchars($_SESSION['site']['name']??'') ?> — <?= htmlspecialchars($_SESSION['site']['url']??'') ?></div>
                        <div class="col-4 fw-semibold">Admin:</div><div class="col-8"><?= htmlspecialchars($_SESSION['admin']['username']??'') ?> &lt;<?= htmlspecialchars($_SESSION['admin']['email']??'') ?>&gt;</div>
                    </div>
                </div>
                <div class="alert alert-warning alert-custom mb-4" style="border-left-color:#ffc107">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This will create all database tables and config files. Cannot be undone.
                </div>
                <button type="button" id="startInstallBtn" class="btn btn-success w-100 btn-lg"><i class="fas fa-download me-2"></i>Install Mybb Based Torrent Tracker Now!</button>
            </div>

            <div id="installProgressSection" style="display:none">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="installProgressLabel" class="fw-semibold small">Starting…</span>
                    <span id="installProgressCount" class="text-muted small"></span>
                </div>
                <div class="progress mb-3" style="height:1.5rem;border-radius:.7rem;">
                    <div id="installProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%;background:linear-gradient(135deg,var(--accent) 0%,var(--accent-dark) 100%);">0%</div>
                </div>
                <div id="liveLogBox" style="background:#f7f7fb;color:#33304d;border:1px solid #ece9fa;font-family:'SFMono-Regular',Consolas,monospace;font-size:.78rem;padding:1rem;border-radius:.7rem;max-height:320px;overflow-y:auto;"></div>
                <div id="installFailureBox" class="alert alert-danger alert-custom mt-3 text-start" style="display:none"></div>
                <div id="installSuccessBox" class="alert alert-success alert-custom mt-3 text-center" style="display:none">
                    <i class="fas fa-check-circle me-2"></i>All steps completed — redirecting…
                </div>
            </div>

            <style>
                #liveLogBox .log-line { padding:.12rem 0; opacity:0; animation:logFadeIn .2s ease forwards; display:flex; justify-content:space-between; gap:.5rem; }
                #liveLogBox .log-line .log-left { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
                #liveLogBox .log-line .log-time { flex-shrink:0; color:#9691b8; }
                @keyframes logFadeIn { from{opacity:0;transform:translateY(3px)} to{opacity:1;transform:translateY(0)} }
            </style>

            <script>
            (function () {
                const csrfToken = <?= json_encode($csrfToken) ?>;
                const startBtn  = document.getElementById('startInstallBtn');
                if (!startBtn) return;

                function escapeHtml(s) {
                    const d = document.createElement('div');
                    d.textContent = s;
                    return d.innerHTML;
                }

                async function postAjax(action, extra) {
                    const body = new URLSearchParams(Object.assign({ ajax_action: action, csrf_token: csrfToken }, extra || {}));
                    const resp = await fetch('install.php?step=5', { method: 'POST', body });
                    return resp.json();
                }

                function addLogLine(result) {
                    const box = document.getElementById('liveLogBox');
                    const line = document.createElement('div');
                    line.className = 'log-line';
                    const color = result.status === 'ok' ? '#34d399' : '#f87171';
                    const icon  = result.status === 'ok' ? 'fa-check' : 'fa-xmark';
                    line.innerHTML =
                        '<span class="log-left"><i class="fas ' + icon + ' me-2" style="color:' + color + '"></i>' +
                        '<strong>' + escapeHtml(result.label) + '</strong> — ' +
                        '<span style="color:#6b6690">' + escapeHtml(result.message) + '</span></span>' +
                        '<span class="log-time">' + (result.elapsed_ms ?? 0) + 'ms</span>';
                    box.appendChild(line);
                    box.scrollTop = box.scrollHeight;
                }

                function showFailure(result) {
                    const bar = document.getElementById('installProgressBar');
                    bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                    bar.style.background = '#dc3545';
                    document.getElementById('installProgressLabel').textContent = 'Installation failed — stopped';

                    const box = document.getElementById('installFailureBox');
                    box.style.display = 'block';
                    let html = '<strong><i class="fas fa-exclamation-triangle me-2"></i>' + escapeHtml(result.label) + ' failed:</strong>' +
                               '<p class="mb-2 mt-1">' + escapeHtml(result.message) + '</p>';
                    if (result.sql) {
                        html += '<div class="small fw-semibold mb-1">Failed SQL:</div>' +
                                '<pre class="bg-dark text-light p-2 rounded small mb-2" style="white-space:pre-wrap;word-break:break-all;">' + escapeHtml(result.sql) + '</pre>';
                    }
                    html += '<a href="install.php?step=1" class="btn btn-outline-secondary w-100 mt-1"><i class="fas fa-sync-alt me-2"></i>Start Over</a>';
                    box.innerHTML = html;
                }

                startBtn.addEventListener('click', async function () {
                    startBtn.disabled = true;
                    document.getElementById('installSummary').style.display = 'none';
                    document.getElementById('installProgressSection').style.display = 'block';

                    const progressLabel = document.getElementById('installProgressLabel');
                    const progressCount = document.getElementById('installProgressCount');
                    const progressBar   = document.getElementById('installProgressBar');

                    const plan = await postAjax('get_install_plan');
                    if (plan.status !== 'ok') {
                        showFailure({ label: 'Installation plan', message: plan.message || 'Could not load the install plan.' });
                        return;
                    }
                    const total = plan.total;

                    for (let i = 0; i < total; i++) {
                        progressLabel.textContent = plan.labels[i];
                        progressCount.textContent = (i + 1) + ' / ' + total;

                        const result = await postAjax('install_step', { step_index: i });
                        addLogLine(result);

                        const pct = Math.round(((i + 1) / total) * 100);
                        progressBar.style.width = pct + '%';
                        progressBar.textContent = pct + '%';

                        if (result.status === 'fail') {
                            showFailure(result);
                            return;
                        }
                    }

                    progressLabel.textContent = 'Installation complete!';
                    progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                    progressBar.style.background = '#10b981';
                    document.getElementById('installSuccessBox').style.display = 'block';
                    setTimeout(() => { window.location.href = 'install.php?step=6'; }, 700);
                });
            })();
            </script>

            <?php elseif ($step === 6): ?>
            <div class="text-center py-4">
                <i class="fas fa-check-circle text-success mb-4" style="font-size:5rem;display:block"></i>
                <h3 class="text-success fw-bold mb-3">Installation Complete!</h3>
                <p class="text-muted mb-4">Mybb Based Torrent Tracker has been successfully installed and configured.</p>
                <div class="alert alert-success text-start mb-4">
                    <i class="fas fa-right-to-bracket me-2"></i>
                    <strong>You're already logged in!</strong> An auto-login cookie was set for your new admin
                    account — just hit the button below, no need to fill in the login form.
                </div>
                <div class="alert alert-danger text-start mb-4" id="deleteInstallAlert">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Security:</strong> Delete <code>install.php</code> from your server immediately!
                        </div>
                        <button type="button" id="selfDestructBtn" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash-alt me-1"></i>Delete install.php Now
                        </button>
                    </div>
                    <div id="selfDestructResult" class="mt-2 small"></div>
                </div>

                <?php if (!empty($_SESSION['install_log'])): ?>
                <div class="text-start mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small"><i class="fas fa-terminal me-1"></i>Installation Log</span>
                        <span class="text-muted small"><?= count($_SESSION['install_log']) ?> steps</span>
                    </div>
                    <div id="installLogBox" class="rounded" style="background:#f7f7fb;color:#33304d;border:1px solid #ece9fa;font-family:'SFMono-Regular',Consolas,monospace;font-size:.78rem;padding:1rem;max-height:260px;overflow-y:auto;"></div>
                </div>
                <script>
                (function () {
                    const entries = <?= str_replace('</', '<\/', json_encode($_SESSION['install_log'])) ?>;
                    const box = document.getElementById('installLogBox');
                    let i = 0;
                    function next() {
                        if (i >= entries.length) return;
                        const [status, msg] = entries[i++];
                        const line = document.createElement('div');
                        line.style.cssText = 'padding:.1rem 0;opacity:0;transition:opacity .2s ease';
                        const color = status === 'ok' ? '#34d399' : '#f87171';
                        const icon  = status === 'ok' ? 'fa-check' : 'fa-xmark';
                        line.innerHTML = '<i class="fas ' + icon + ' me-2" style="color:' + color + '"></i>' + msg.replace(/</g, '&lt;');
                        box.appendChild(line);
                        box.scrollTop = box.scrollHeight;
                        requestAnimationFrame(() => { line.style.opacity = '1'; });
                        setTimeout(next, 35);
                    }
                    next();
                })();
                </script>
                <?php endif; ?>


                <div class="info-card text-start mb-4">
                    <h6 class="mb-3"><i class="fas fa-key me-2"></i>Access</h6>
                    <div class="row g-2 small">
                        <div class="col-4 fw-semibold">Site:</div>
                        <div class="col-8"><a href="<?= htmlspecialchars($_SESSION['site']['url']??'/') ?>" target="_blank"><?= htmlspecialchars($_SESSION['site']['url']??'/') ?></a></div>
                        <div class="col-4 fw-semibold">Admin Panel:</div>
                        <div class="col-8"><a href="<?= htmlspecialchars(($_SESSION['site']['url']??'').'/admin/index.php') ?>" target="_blank">admin/index.php</a></div>
                        <div class="col-4 fw-semibold">Username:</div>
                        <div class="col-8"><?= htmlspecialchars($_SESSION['admin']['username']??'') ?></div>
                    </div>
                </div>
                <div class="d-grid gap-3">
                    <a href="<?= htmlspecialchars($_SESSION['site']['url']??'/') ?>" class="btn btn-primary btn-lg"><i class="fas fa-right-to-bracket me-2"></i>Login Here &amp; Go to Tracker</a>
                    <a href="<?= htmlspecialchars(($_SESSION['site']['url']??'').'/admin/index.php') ?>" class="btn btn-outline-primary btn-lg"><i class="fas fa-crown me-2"></i>Admin Panel</a>
                </div>
            </div>

            <script>
            document.getElementById('selfDestructBtn')?.addEventListener('click', async function () {
                if (!confirm('Delete install.php from the server right now? This cannot be undone.')) return;

                const btn = this;
                const resultBox = document.getElementById('selfDestructResult');
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';

                try {
                    const body = new URLSearchParams({
                        ajax_action: 'self_destruct',
                        csrf_token: <?= json_encode($csrfToken) ?>,
                    });
                    const resp = await fetch('install.php', { method: 'POST', body });
                    const data = await resp.json();

                    if (data.status === 'success') {
                        document.getElementById('deleteInstallAlert').outerHTML =
                            '<div class="alert alert-success text-start mb-4"><i class="fas fa-check-circle me-2"></i><strong>Done!</strong> install.php has been deleted from the server.</div>';
                    } else {
                        resultBox.innerHTML = '<span class="text-danger"><i class="fas fa-triangle-exclamation me-1"></i>' + data.message + '</span>';
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                } catch (err) {
                    resultBox.innerHTML = '<span class="text-danger"><i class="fas fa-triangle-exclamation me-1"></i>Network error — delete the file manually via FTP/SSH.</span>';
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            });
            </script>

            <?php endif; ?>
        </div>
    </div>
    <div class="text-center mt-3">
        <small class="text-muted">
            <i class="fas fa-code me-1"></i> Mybb Based Torrent Tracker Installer v<?= INSTALLER_VERSION ?> |
            <a href="install.php?step=1" class="text-muted text-decoration-none">Start Over</a>
        </small>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>