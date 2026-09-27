<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define('CE_VERSION', '0.6');

/** Найти пользователя по ID или нику */
function ce_find_user(string $idOrName): ?array
{
    global $db;
    $idOrName = trim($idOrName);
    if ($idOrName === '') return null;

    $cols = 'id, username, usergroup, email, avatar, avatardimensions, added';
    $q = ctype_digit($idOrName)
        ? $db->sql_query_prepared("SELECT {$cols} FROM users WHERE id = ? LIMIT 1", [(int)$idOrName])
        : $db->sql_query_prepared("SELECT {$cols} FROM users WHERE username = ? LIMIT 1", [$idOrName]);
    $row = $q ? $db->fetch_array($q) : null;
    return $row ?: null;
}

/** Супер-админа меняет только супер-админ (как в changeusername.php) */
function ce_can_edit(int $targetId): bool
{
    global $CURUSER;
    if (!function_exists('is_super_admin')) return true;
    return !(is_super_admin($targetId) && (int)$CURUSER['id'] !== $targetId && !is_super_admin((int)$CURUSER['id']));
}

/** Email уже у другого аккаунта? (регистр не важен) */
function ce_email_taken(string $email, int $excludeId): bool
{
    global $db;
    $q = $db->sql_query_prepared('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ? LIMIT 1', [$email, $excludeId]);
    return $q && $db->num_rows($q) > 0;
}

// ── AJAX: предпросмотр пользователя ────────────────────────────────────
if (isset($_GET['lookup'])) {
    header('Content-Type: application/json; charset=utf-8');
    $u = ce_find_user((string)$_GET['lookup']);
    if (!$u) {
        echo json_encode(['found' => false]);
        exit;
    }
    $av = function_exists('format_avatar') ? format_avatar($u['avatar'] ?? '', $u['avatardimensions'] ?? '') : [];
    echo json_encode([
        'found'     => true,
        'id'        => (int)$u['id'],
        'username'  => (string)$u['username'],
        'name_html' => format_name(htmlspecialchars_uni($u['username']), (int)$u['usergroup']),
        'email'     => (string)$u['email'],
        'avatar'    => (!empty($av['image']) && empty($av['is_placeholder'])) ? $av['image'] : '',
        'joined'    => my_datee('relative', (int)$u['added']),
        'protected' => !ce_can_edit((int)$u['id']),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX: свободен ли адрес ────────────────────────────────────────────
if (isset($_GET['check'])) {
    header('Content-Type: application/json; charset=utf-8');
    $email = trim((string)$_GET['check']);
    echo json_encode([
        'valid' => (bool)filter_var($email, FILTER_VALIDATE_EMAIL),
        'taken' => filter_var($email, FILTER_VALIDATE_EMAIL) ? ce_email_taken($email, (int)($_GET['uid'] ?? 0)) : false,
    ]);
    exit;
}

$formSubmitted = false;
$success       = false;
$message       = '';
$target        = '';
$email         = '';
$oldEmail      = '';
$newEmail      = '';
$user          = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'changemail') {
    // Раньше здесь НЕ было проверки CSRF-токена: любая сторонняя страница могла
    // заставить браузер админа сменить email любого пользователя — а через
    // «забыли пароль» это означает угон аккаунта.
    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        stderr('Security Error', 'Invalid security token. Please refresh the page and try again.');
    }

    $formSubmitted = true;
    $target = trim((string)($_POST['username'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $user   = ce_find_user($target);

    if ($target === '' || $email === '') {
        $message = 'Please fill in all required fields.';
    } elseif (!$user) {
        // Раньше ник проверялся шаблоном ^[a-zA-Z0-9]+$ — пользователей с «_», «-»,
        // кириллицей и т.п. найти было невозможно
        $message = 'No user found with this ID or username.';
    } elseif (!ce_can_edit((int)$user['id'])) {
        $message = "You do not have permission to change a super administrator's email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[<>"\'\\\\\s]/', $email)) {
        // Раньше недопустимые символы молча вырезались str_replace() — сохранялся
        // не тот адрес, что ввели. Теперь такой адрес просто отклоняется.
        $message = 'Invalid email address format.';
    } elseif (strcasecmp($email, (string)$user['email']) === 0) {
        $message = 'This is already the current email of this user.';
    } elseif (ce_email_taken($email, (int)$user['id'])) {
        $message = 'This email address is already used by another account.';
    } else {
        $oldEmail = (string)$user['email'];
        // По id, а не по нику
        if ($db->sql_query_prepared('UPDATE users SET email = ? WHERE id = ?', [$email, (int)$user['id']])) {
            $success  = true;
            $newEmail = $email;
            write_log(sprintf(
                "%s's email has been changed from %s to %s by %s (Change Email Tool)",
                $user['username'], $oldEmail, $email, $CURUSER['username'] ?? 'System'
            ));
            $message = 'Email successfully updated.';
        } else {
            $message = 'Database error: Unable to update email.';
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════════════════════

stdhead('Change User Email Address');
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/changemail.css?ver=2">
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js" defer></script>
<script src="<?= $BASEURL ?>/admin/scripts/changemail.js?ver=2" defer></script>
<?php

$self = htmlspecialchars($_this_script_ ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
$key  = htmlspecialchars((string)$mybb->post_code);

echo '<div class="container mt-3 mb-4 ce">';
echo '<div class="ce-card mb-3"><div class="ce-head">'
   . '<span class="ce-head-icon ic-green"><i class="fa-solid fa-envelope-circle-check"></i></span>'
   . '<div><h1 class="ce-title">Change User Email</h1><div class="ce-sub">Set a new email address for an account</div></div>'
   . '<span class="ce-ver ms-auto"><i class="fa-solid fa-code-branch me-1"></i>v' . CE_VERSION . '</span>'
   . '</div></div>';

if ($success) {
    $profileLink = $BASEURL . '/' . get_profile_link((int)$user['id']);
    ?>
    <div class="ce-card ce-result">
        <span class="ce-result-icon"><i class="fa-solid fa-circle-check"></i></span>
        <h2 class="ce-result-title">Email changed</h2>
        <div class="fw-semibold mb-2"><i class="fa-solid fa-user me-1 text-body-secondary"></i><?= htmlspecialchars((string)$user['username']) ?> <span class="ce-muted">· ID <?= (int)$user['id'] ?></span></div>
        <div class="ce-swap">
            <span class="ce-mail old"><?= htmlspecialchars($oldEmail) ?></span>
            <i class="fa-solid fa-arrow-right-long"></i>
            <span class="ce-mail new"><?= htmlspecialchars($newEmail) ?></span>
        </div>
        <div class="ce-muted mb-3"><i class="fa-solid fa-clipboard-list me-1"></i>Written to the site log</div>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="<?= $self ?>" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i>Change another</a>
            <a href="<?= htmlspecialchars($profileLink) ?>" class="btn btn-primary px-3" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View profile</a>
        </div>
    </div>
    <?php
} else {
    if ($formSubmitted) {
        echo '<div class="ce-note mb-3"><i class="fa-solid fa-circle-exclamation"></i><div><strong>' . htmlspecialchars($message) . '</strong></div></div>';
    }
    ce_form($self, $key, $target !== '' ? $target : (string)($_GET['id'] ?? ''), $email);
}

echo '<div class="ce-muted text-center mt-3"><i class="fa-solid fa-shield-halved me-1"></i>Super administrators can only be changed by another super administrator · every change is logged</div>';
echo '</div>';

stdfoot();

// ═══════════════════════════════════════════════════════════════════════
// VIEW
// ═══════════════════════════════════════════════════════════════════════

function ce_form(string $self, string $key, string $target, string $email): void
{
    ?>
    <form method="post" action="<?= $self ?>" class="ce-card p-3 p-md-4" id="email-change-form"
          data-self="<?= $self ?>" novalidate>
        <input type="hidden" name="act" value="changemail">
        <input type="hidden" name="my_post_key" value="<?= $key ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="username" class="form-label"><i class="fa-solid fa-magnifying-glass"></i>User <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($target) ?>" class="form-control"
                           placeholder="ID or username" required autocomplete="off" autofocus>
                </div>
                <div class="form-text">Numeric ID or the exact username</div>
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label"><i class="fa-solid fa-at"></i>New email <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" class="form-control"
                           placeholder="user@example.com" required autocomplete="off">
                </div>
                <div class="form-text" id="ceMailHint">The user will receive mail at this address</div>
            </div>
        </div>

        <!-- Живой предпросмотр -->
        <div class="ce-preview mt-3" id="cePreview" hidden>
            <span class="ce-avatar" id="ceAvatar"><i class="fa-solid fa-user"></i></span>
            <div class="flex-grow-1" style="min-width:0">
                <div class="fw-bold" id="ceUser"></div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    <span class="ce-mail old" id="ceOld">—</span>
                    <i class="fa-solid fa-arrow-right-long text-body-secondary"></i>
                    <span class="ce-mail new" id="ceNew">—</span>
                </div>
                <div class="ce-muted mt-1" id="ceMeta"></div>
            </div>
            <span class="ce-flag" id="ceFlag" hidden>Protected</span>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-eraser me-1"></i>Clear</button>
            <button type="submit" class="btn btn-success px-4" id="ceSubmit"><i class="fa-solid fa-paper-plane me-1"></i>Change email</button>
        </div>
    </form>

    <?php
}