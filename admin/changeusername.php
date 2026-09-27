<?php

declare(strict_types=1);

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define('CU_VERSION', '0.6');

/** Найти пользователя по ID или по текущему нику */
function cu_find_user(string $idOrName): ?array
{
    global $db;
    $idOrName = trim($idOrName);
    if ($idOrName === '') return null;

    $q = ctype_digit($idOrName)
        ? $db->sql_query_prepared('SELECT id, username, usergroup, avatar, avatardimensions, added, lastactive FROM users WHERE id = ? LIMIT 1', [(int)$idOrName])
        : $db->sql_query_prepared('SELECT id, username, usergroup, avatar, avatardimensions, added, lastactive FROM users WHERE username = ? LIMIT 1', [$idOrName]);
    $row = $q ? $db->fetch_array($q) : null;
    return $row ?: null;
}

function cu_can_edit(int $targetId): bool
{
    global $CURUSER;
    return !(is_super_admin($targetId) && (int)$CURUSER['id'] !== $targetId && !is_super_admin((int)$CURUSER['id']));
}

/** URL ассета с cache-busting по filemtime */
function cu_asset(string $rel): string
{
    global $BASEURL;
    $file = rtrim(TSDIR, '/\\') . $rel;
    $ver  = is_file($file) ? (string)filemtime($file) : CU_VERSION;
    return htmlspecialchars($BASEURL . $rel . '?v=' . $ver);
}

// ── AJAX: предпросмотр пользователя по ID/нику ─────────────────────────
if (isset($_GET['lookup'])) {
    header('Content-Type: application/json; charset=utf-8');
    $u = cu_find_user((string)$_GET['lookup']);
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
        'avatar'    => (!empty($av['image']) && empty($av['is_placeholder'])) ? $av['image'] : '',
        'joined'    => my_datee('relative', (int)$u['added']),
        'protected' => !cu_can_edit((int)$u['id']),
        'super'     => is_super_admin((int)$u['id']),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX: занят ли ник ─────────────────────────────────────────────────
if (isset($_GET['check'])) {
    header('Content-Type: application/json; charset=utf-8');
    $name = trim((string)$_GET['check']);
    $exclude = (int)($_GET['uid'] ?? 0);
    $q = $db->sql_query_prepared('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1', [$name, $exclude]);
    echo json_encode(['taken' => $q && $db->num_rows($q) > 0]);
    exit;
}

$formSubmitted      = false;
$confirmationNeeded = false;
$success            = false;
$message            = '';
$userId             = '';
$oldUsername        = '';
$newUsername        = '';
$currentUsername    = '';
$validationErrors   = [];
$targetUser         = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'changeusername') {
    if (!verify_post_check($_POST['my_post_key'] ?? '')) {
        http_response_code(403);
        echo 'Invalid security token';
        exit;
    }

    $formSubmitted = true;
    $rawTarget     = trim((string)($_POST['id'] ?? ''));
    $newUsername   = trim((string)($_POST['username'] ?? ''));
    $sure          = $_POST['sure'] ?? '';

    // Поле «кого меняем» принимает ID ИЛИ текущий ник
    $targetUser = cu_find_user($rawTarget);
    $userId     = $targetUser ? (string)(int)$targetUser['id'] : $rawTarget;

    if ($rawTarget === '' || $newUsername === '') {
        $message = 'Please fill in all required fields.';
    } elseif (!$targetUser) {
        $message = 'No user found with this ID or username.';
    } elseif (!cu_can_edit((int)$targetUser['id'])) {
        $message = "You do not have permission to change a super administrator's username.";
    } elseif (mb_strlen($newUsername) < 3 || mb_strlen($newUsername) > 25) {
        $message = 'Username must be between 3 and 25 characters.';
    } elseif ($newUsername === $targetUser['username']) {
        $message = 'The new username is the same as the current one.';
    } else {
        // Раньше: проверка «ник занят» находила самого пользователя — поменять
        // только регистр («bob» → «Bob») было нельзя. Себя из проверки исключаем.
        $taken = $db->sql_query_prepared('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1', [$newUsername, (int)$targetUser['id']]);
        if ($taken && $db->num_rows($taken) > 0) {
            $message = 'This username is already taken.';
        } elseif ($sure === 'yes') {
            // Подтверждено — меняем через UserDataHandler (он же проверяет формат по настройкам форума).
            // Раньше до этого шага ещё была проверка ^[a-zA-Z0-9]+$ — ники с «_», «-»,
            // кириллицей и т.п. отклонялись, хотя сам форум их допускает.
            try {
                require_once INC_PATH . '/datahandlers/user.php';
                $userhandler = new UserDataHandler('update');
                $oldUsername = (string)$targetUser['username'];

                $userhandler->set_data(['uid' => (int)$targetUser['id'], 'username' => $newUsername]);

                if (!$userhandler->validate_user()) {
                    $validationErrors = $userhandler->get_friendly_errors();
                    $message = 'Validation failed.';
                } elseif ($userhandler->update_user()) {
                    $success = true;
                    write_log(sprintf(
                        "%s's account name has been changed to %s by %s (Change Username Tool)",
                        $oldUsername, $newUsername, $CURUSER['username'] ?? 'System'
                    ));
                    $message = 'Username successfully updated.';
                } else {
                    $message = 'Failed to update username using UserDataHandler.';
                }
            } catch (Throwable $e) {
                $message = 'System error: ' . $e->getMessage();
            }
        } else {
            $confirmationNeeded = true;
            $currentUsername    = (string)$targetUser['username'];
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════════════════════

stdhead('Change Username');

echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/changeusername.css?ver=2">';

$self = htmlspecialchars($_this_script_ ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
$key  = htmlspecialchars((string)$mybb->post_code);

echo '<div class="container mt-3 mb-4 cu">';
echo '<div class="cu-card mb-3"><div class="cu-head">'
   . '<span class="cu-head-icon ic-purple"><i class="fa-solid fa-user-pen"></i></span>'
   . '<div><h1 class="cu-title">Change Username</h1><div class="cu-sub">Rename an account — validated by the forum\'s own user rules</div></div>'
   . '<span class="cu-ver ms-auto"><i class="fa-solid fa-code-branch me-1"></i>v' . CU_VERSION . '</span>'
   . '</div></div>';

if ($success) {
    $profileLink = $BASEURL . '/' . get_profile_link((int)$userId);
    ?>
    <div class="cu-card cu-result is-success">
        <span class="cu-result-icon"><i class="fa-solid fa-circle-check"></i></span>
        <h2 class="cu-result-title">Username changed</h2>
        <div class="cu-swap">
            <span class="cu-name old"><?= htmlspecialchars($oldUsername) ?></span>
            <i class="fa-solid fa-arrow-right-long"></i>
            <span class="cu-name new"><?= htmlspecialchars($newUsername) ?></span>
        </div>
        <div class="cu-muted mb-3"><i class="fa-solid fa-id-card me-1"></i>User ID <?= (int)$userId ?> · <i class="fa-solid fa-clipboard-list ms-1 me-1"></i>written to the site log</div>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="<?= $self ?>" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i>Change another</a>
            <a href="<?= htmlspecialchars($profileLink) ?>" class="btn btn-primary px-3" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View profile</a>
        </div>
    </div>
    <?php
} elseif ($confirmationNeeded) {
    $isSuper = is_super_admin((int)$userId);
    ?>
    <div class="cu-card cu-result is-warn">
        <span class="cu-result-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <h2 class="cu-result-title">Confirm the change</h2>
        <div class="cu-swap">
            <span class="cu-name old"><?= htmlspecialchars($currentUsername) ?></span>
            <i class="fa-solid fa-arrow-right-long"></i>
            <span class="cu-name new"><?= htmlspecialchars($newUsername) ?></span>
        </div>
        <div class="cu-muted mb-3"><i class="fa-solid fa-id-card me-1"></i>User ID <?= (int)$userId ?></div>

        <?php if ($isSuper): ?>
        <div class="cu-note is-danger mb-3"><i class="fa-solid fa-crown"></i>
            <div><strong>Super Administrator account.</strong> Double-check before renaming.</div></div>
        <?php endif; ?>

        <div class="cu-points mb-3">
            <div><i class="fa-solid fa-right-to-bracket"></i>The user logs in with the new name from now on</div>
            <div><i class="fa-solid fa-link"></i>Posts, comments and profile show the new name</div>
            <div><i class="fa-solid fa-clipboard-list"></i>The change is written to the site log</div>
            <div><i class="fa-solid fa-rotate-left"></i>To undo it, rename the account back manually</div>
        </div>

        <form method="post" action="<?= $self ?>" class="text-start">
            <input type="hidden" name="act" value="changeusername">
            <input type="hidden" name="my_post_key" value="<?= $key ?>">
            <input type="hidden" name="id" value="<?= (int)$userId ?>">
            <input type="hidden" name="username" value="<?= htmlspecialchars($newUsername) ?>">
            <input type="hidden" name="sure" value="yes">
            <label class="cu-confirm mb-3">
                <input type="checkbox" class="form-check-input m-0" name="confirm" value="1" required id="cuConfirm">
                <span>I've checked the new name and want to rename this account</span>
            </label>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="<?= $self ?>" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-xmark me-1"></i>Cancel</a>
                <button type="submit" class="btn btn-warning px-4" id="cuConfirmBtn" disabled><i class="fa-solid fa-check me-1"></i>Yes, rename</button>
            </div>
        </form>
    </div>
    <?php
} else {
    if ($formSubmitted) {
        echo '<div class="cu-note is-danger mb-3"><i class="fa-solid fa-circle-exclamation"></i><div><strong>' . htmlspecialchars($message) . '</strong>';
        if ($validationErrors) {
            echo '<ul class="mb-0 mt-1 ps-3">';
            foreach ($validationErrors as $err) echo '<li>' . htmlspecialchars((string)$err) . '</li>';
            echo '</ul>';
        }
        echo '</div></div>';
    }
    cu_form($self, $key, $formSubmitted ? (string)($_POST['id'] ?? '') : (string)($_GET['id'] ?? ''), $newUsername);
}

echo '<div class="cu-muted text-center mt-3"><i class="fa-solid fa-shield-halved me-1"></i>Super administrators can only be renamed by another super administrator · every change is logged</div>';
echo '</div>';


echo '<script src="' . $BASEURL . '/admin/scripts/changeusername.js"></script>';

stdfoot();

// ═══════════════════════════════════════════════════════════════════════
// VIEW HELPERS
// ═══════════════════════════════════════════════════════════════════════

function cu_form(string $self, string $key, string $target, string $username): void
{
    ?>
    <form method="post" action="<?= $self ?>" class="cu-card p-3 p-md-4" id="username-change-form" data-self="<?= $self ?>" novalidate>
        <input type="hidden" name="act" value="changeusername">
        <input type="hidden" name="my_post_key" value="<?= $key ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="user-id" class="form-label"><i class="fa-solid fa-magnifying-glass"></i>User <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                    <input type="text" id="user-id" name="id" value="<?= htmlspecialchars($target) ?>" class="form-control"
                           placeholder="ID or current username" required autocomplete="off" autofocus>
                </div>
                <div class="form-text">Numeric ID or the exact current name</div>
            </div>
            <div class="col-md-6">
                <label for="username" class="form-label"><i class="fa-solid fa-signature"></i>New username <span class="text-danger">*</span></label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" class="form-control"
                           placeholder="New name" required minlength="3" maxlength="25" autocomplete="off">
                </div>
                <div class="form-text" id="cuNameHint">3–25 characters; the forum's name rules apply</div>
            </div>
        </div>

        <!-- Живой предпросмотр: кого переименовываем и во что -->
        <div class="cu-preview mt-3" id="cuPreview" hidden>
            <span class="cu-avatar" id="cuAvatar"><i class="fa-solid fa-user"></i></span>
            <div class="flex-grow-1" style="min-width:0">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-bold" id="cuCurrent"></span>
                    <i class="fa-solid fa-arrow-right-long text-body-secondary"></i>
                    <span class="cu-name new" id="cuNew">—</span>
                </div>
                <div class="cu-muted" id="cuMeta"></div>
            </div>
            <span class="cu-flag" id="cuFlag" hidden></span>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-eraser me-1"></i>Clear</button>
            <button type="submit" class="btn btn-primary px-4 submit-button"><i class="fa-solid fa-arrow-right me-1"></i>Continue</button>
        </div>
    </form>

    <?php
}