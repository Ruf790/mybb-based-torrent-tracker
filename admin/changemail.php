<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

$lang->load('changemail');

define('CE_VERSION', '0.7');

if (!function_exists('ags_fmt')) {
    /** Подстановка {1}, {2}… (и %1$s — в него $lang->load() превращает {1}) */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $a) {
            $n = $i + 1;
            $v = (string)$a;
            $map['{' . $n . '}']   = $v;
            $map['%' . $n . '$s'] = $v;
            $map['%' . $n . '$d'] = $v;
        }
        return strtr($str, $map);
    }
}

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
        stderr($lang->changemail['err_security_title'], $lang->changemail['err_security_token']);
    }

    $formSubmitted = true;
    $target = trim((string)($_POST['username'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $user   = ce_find_user($target);

    if ($target === '' || $email === '') {
        $message = $lang->changemail['err_required'];
    } elseif (!$user) {
        // Раньше ник проверялся шаблоном ^[a-zA-Z0-9]+$ — пользователей с «_», «-»,
        // кириллицей и т.п. найти было невозможно
        $message = $lang->changemail['err_user_not_found'];
    } elseif (!ce_can_edit((int)$user['id'])) {
        $message = $lang->changemail['err_super_admin'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[<>"\'\\\\\s]/', $email)) {
        // Раньше недопустимые символы молча вырезались str_replace() — сохранялся
        // не тот адрес, что ввели. Теперь такой адрес просто отклоняется.
        $message = $lang->changemail['err_invalid_email'];
    } elseif (strcasecmp($email, (string)$user['email']) === 0) {
        $message = $lang->changemail['err_same_email'];
    } elseif (ce_email_taken($email, (int)$user['id'])) {
        $message = $lang->changemail['err_email_taken'];
    } else {
        $oldEmail = (string)$user['email'];
        // По id, а не по нику
        if ($db->sql_query_prepared('UPDATE users SET email = ? WHERE id = ?', [$email, (int)$user['id']])) {
            $success  = true;
            $newEmail = $email;
            // Лог — всегда на английском
            write_log(sprintf(
                "%s's email has been changed from %s to %s by %s (Change Email Tool)",
                $user['username'], $oldEmail, $email, $CURUSER['username'] ?? 'System'
            ));
            $message = $lang->changemail['flash_success'];
        } else {
            $message = $lang->changemail['err_db'];
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════════════════════

stdhead($lang->changemail['page_title']);

// Строки для JS: js_* → без префикса
$ceJsLang = [];
foreach ($lang->changemail as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $ceJsLang[substr((string)$k, 3)] = (string)$v;
    }
}
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/changemail.css?ver=2">
<script>const AGS_LANG = <?= json_encode($ceJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js" defer></script>
<script src="<?= $BASEURL ?>/admin/scripts/changemail.js?ver=3" defer></script>
<?php

$self = htmlspecialchars($_this_script_ ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
$key  = htmlspecialchars((string)$mybb->post_code);

echo '<div class="container mt-3 mb-4 ce">';
echo '<div class="ce-card mb-3"><div class="ce-head">'
   . '<span class="ce-head-icon ic-green"><i class="fa-solid fa-envelope-circle-check"></i></span>'
   . '<div><h1 class="ce-title">' . htmlspecialchars($lang->changemail['pane_title']) . '</h1>'
   . '<div class="ce-sub">' . htmlspecialchars($lang->changemail['pane_sub']) . '</div></div>'
   . '<span class="ce-ver ms-auto"><i class="fa-solid fa-code-branch me-1"></i>v' . CE_VERSION . '</span>'
   . '</div></div>';

if ($success) {
    $profileLink = $BASEURL . '/' . get_profile_link((int)$user['id']);
    ?>
    <div class="ce-card ce-result">
        <span class="ce-result-icon"><i class="fa-solid fa-circle-check"></i></span>
        <h2 class="ce-result-title"><?= htmlspecialchars($lang->changemail['res_title']) ?></h2>
        <div class="fw-semibold mb-2"><i class="fa-solid fa-user me-1 text-body-secondary"></i><?= htmlspecialchars((string)$user['username']) ?> <span class="ce-muted">· <?= htmlspecialchars(ags_fmt($lang->changemail['res_id'], (int)$user['id'])) ?></span></div>
        <div class="ce-swap">
            <span class="ce-mail old"><?= htmlspecialchars($oldEmail) ?></span>
            <i class="fa-solid fa-arrow-right-long"></i>
            <span class="ce-mail new"><?= htmlspecialchars($newEmail) ?></span>
        </div>
        <div class="ce-muted mb-3"><i class="fa-solid fa-clipboard-list me-1"></i><?= htmlspecialchars($lang->changemail['res_logged']) ?></div>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="<?= $self ?>" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i><?= htmlspecialchars($lang->changemail['btn_another']) ?></a>
            <a href="<?= htmlspecialchars($profileLink) ?>" class="btn btn-primary px-3" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= htmlspecialchars($lang->changemail['btn_profile']) ?></a>
        </div>
    </div>
    <?php
} else {
    if ($formSubmitted) {
        echo '<div class="ce-note mb-3"><i class="fa-solid fa-circle-exclamation"></i><div><strong>' . htmlspecialchars($message) . '</strong></div></div>';
    }
    ce_form($self, $key, $target !== '' ? $target : (string)($_GET['id'] ?? ''), $email);
}

echo '<div class="ce-muted text-center mt-3"><i class="fa-solid fa-shield-halved me-1"></i>' . htmlspecialchars($lang->changemail['footer_note']) . '</div>';
echo '</div>';

stdfoot();

// ═══════════════════════════════════════════════════════════════════════
// VIEW
// ═══════════════════════════════════════════════════════════════════════

function ce_form(string $self, string $key, string $target, string $email): void
{
    global $lang;
    ?>
    <form method="post" action="<?= $self ?>" class="ce-card p-3 p-md-4" id="email-change-form"
          data-self="<?= $self ?>" novalidate>
        <input type="hidden" name="act" value="changemail">
        <input type="hidden" name="my_post_key" value="<?= $key ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="username" class="form-label"><i class="fa-solid fa-magnifying-glass"></i><?= htmlspecialchars($lang->changemail['lbl_user']) ?> <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($target) ?>" class="form-control"
                           placeholder="<?= htmlspecialchars($lang->changemail['ph_user']) ?>" required autocomplete="off" autofocus>
                </div>
                <div class="form-text"><?= htmlspecialchars($lang->changemail['hint_user']) ?></div>
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label"><i class="fa-solid fa-at"></i><?= htmlspecialchars($lang->changemail['lbl_email']) ?> <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" class="form-control"
                           placeholder="<?= htmlspecialchars($lang->changemail['ph_email']) ?>" required autocomplete="off">
                </div>
                <div class="form-text" id="ceMailHint"><?= htmlspecialchars($lang->changemail['hint_email']) ?></div>
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
            <span class="ce-flag" id="ceFlag" hidden><?= htmlspecialchars($lang->changemail['flag_protected']) ?></span>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-eraser me-1"></i><?= htmlspecialchars($lang->changemail['btn_clear']) ?></button>
            <button type="submit" class="btn btn-success px-4" id="ceSubmit"><i class="fa-solid fa-paper-plane me-1"></i><?= htmlspecialchars($lang->changemail['btn_submit']) ?></button>
        </div>
    </form>

    <?php
}
