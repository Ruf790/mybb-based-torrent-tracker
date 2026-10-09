<?php

declare(strict_types=1);

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

$lang->load('changeusername');

define('CU_VERSION', '0.6');

/**
 * Подстановка {1}, {2}… в строку из ланга.
 * $lang->load() превращает {1} в %1$s, поэтому заменяем оба формата.
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

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
        echo htmlspecialchars($lang->changeusername['err_token']);
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
        $message = $lang->changeusername['err_required'];
    } elseif (!$targetUser) {
        $message = $lang->changeusername['err_not_found'];
    } elseif (!cu_can_edit((int)$targetUser['id'])) {
        $message = $lang->changeusername['err_super'];
    } elseif (mb_strlen($newUsername) < 3 || mb_strlen($newUsername) > 25) {
        $message = $lang->changeusername['err_length'];
    } elseif ($newUsername === $targetUser['username']) {
        $message = $lang->changeusername['err_same'];
    } else {
        // Раньше: проверка «ник занят» находила самого пользователя — поменять
        // только регистр («bob» → «Bob») было нельзя. Себя из проверки исключаем.
        $taken = $db->sql_query_prepared('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1', [$newUsername, (int)$targetUser['id']]);
        if ($taken && $db->num_rows($taken) > 0) {
            $message = $lang->changeusername['err_taken'];
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
                    $message = $lang->changeusername['err_validation'];
                } elseif ($userhandler->update_user()) {
                    $success = true;
                    write_log(sprintf(
                        "%s's account name has been changed to %s by %s (Change Username Tool)",
                        $oldUsername, $newUsername, $CURUSER['username'] ?? 'System'
                    ));
                    $message = $lang->changeusername['flash_success'];
                } else {
                    $message = $lang->changeusername['err_update_failed'];
                }
            } catch (Throwable $e) {
                $message = ags_fmt($lang->changeusername['err_system'], $e->getMessage());
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

stdhead($lang->changeusername['page_title']);

echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/changeusername.css?ver=2">';

$self = htmlspecialchars($_this_script_ ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
$key  = htmlspecialchars((string)$mybb->post_code);

echo '<div class="container mt-3 mb-4 cu">';
echo '<div class="cu-card mb-3"><div class="cu-head">'
   . '<span class="cu-head-icon ic-purple"><i class="fa-solid fa-user-pen"></i></span>'
   . '<div><h1 class="cu-title">' . htmlspecialchars($lang->changeusername['head_title']) . '</h1><div class="cu-sub">' . htmlspecialchars($lang->changeusername['head_sub']) . '</div></div>'
   . '<span class="cu-ver ms-auto"><i class="fa-solid fa-code-branch me-1"></i>v' . CU_VERSION . '</span>'
   . '</div></div>';

if ($success) {
    $profileLink = $BASEURL . '/' . get_profile_link((int)$userId);
    ?>
    <div class="cu-card cu-result is-success">
        <span class="cu-result-icon"><i class="fa-solid fa-circle-check"></i></span>
        <h2 class="cu-result-title"><?= htmlspecialchars($lang->changeusername['sec_success']) ?></h2>
        <div class="cu-swap">
            <span class="cu-name old"><?= htmlspecialchars($oldUsername) ?></span>
            <i class="fa-solid fa-arrow-right-long"></i>
            <span class="cu-name new"><?= htmlspecialchars($newUsername) ?></span>
        </div>
        <div class="cu-muted mb-3"><i class="fa-solid fa-id-card me-1"></i><?= htmlspecialchars(ags_fmt($lang->changeusername['lbl_user_id'], (int)$userId)) ?> · <i class="fa-solid fa-clipboard-list ms-1 me-1"></i><?= htmlspecialchars($lang->changeusername['res_logged']) ?></div>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="<?= $self ?>" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i><?= htmlspecialchars($lang->changeusername['btn_change_another']) ?></a>
            <a href="<?= htmlspecialchars($profileLink) ?>" class="btn btn-primary px-3" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= htmlspecialchars($lang->changeusername['btn_view_profile']) ?></a>
        </div>
    </div>
    <?php
} elseif ($confirmationNeeded) {
    $isSuper = is_super_admin((int)$userId);
    ?>
    <div class="cu-card cu-result is-warn">
        <span class="cu-result-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <h2 class="cu-result-title"><?= htmlspecialchars($lang->changeusername['sec_confirm']) ?></h2>
        <div class="cu-swap">
            <span class="cu-name old"><?= htmlspecialchars($currentUsername) ?></span>
            <i class="fa-solid fa-arrow-right-long"></i>
            <span class="cu-name new"><?= htmlspecialchars($newUsername) ?></span>
        </div>
        <div class="cu-muted mb-3"><i class="fa-solid fa-id-card me-1"></i><?= htmlspecialchars(ags_fmt($lang->changeusername['lbl_user_id'], (int)$userId)) ?></div>

        <?php if ($isSuper): ?>
        <div class="cu-note is-danger mb-3"><i class="fa-solid fa-crown"></i>
            <div><strong><?= htmlspecialchars($lang->changeusername['conf_super_title']) ?></strong> <?= htmlspecialchars($lang->changeusername['conf_super_text']) ?></div></div>
        <?php endif; ?>

        <div class="cu-points mb-3">
            <div><i class="fa-solid fa-right-to-bracket"></i><?= htmlspecialchars($lang->changeusername['conf_pt_login']) ?></div>
            <div><i class="fa-solid fa-link"></i><?= htmlspecialchars($lang->changeusername['conf_pt_posts']) ?></div>
            <div><i class="fa-solid fa-clipboard-list"></i><?= htmlspecialchars($lang->changeusername['conf_pt_log']) ?></div>
            <div><i class="fa-solid fa-rotate-left"></i><?= htmlspecialchars($lang->changeusername['conf_pt_undo']) ?></div>
        </div>

        <form method="post" action="<?= $self ?>" class="text-start">
            <input type="hidden" name="act" value="changeusername">
            <input type="hidden" name="my_post_key" value="<?= $key ?>">
            <input type="hidden" name="id" value="<?= (int)$userId ?>">
            <input type="hidden" name="username" value="<?= htmlspecialchars($newUsername) ?>">
            <input type="hidden" name="sure" value="yes">
            <label class="cu-confirm mb-3">
                <input type="checkbox" class="form-check-input m-0" name="confirm" value="1" required id="cuConfirm">
                <span><?= htmlspecialchars($lang->changeusername['conf_checkbox']) ?></span>
            </label>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="<?= $self ?>" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-xmark me-1"></i><?= htmlspecialchars($lang->changeusername['btn_cancel']) ?></a>
                <button type="submit" class="btn btn-warning px-4" id="cuConfirmBtn" disabled><i class="fa-solid fa-check me-1"></i><?= htmlspecialchars($lang->changeusername['btn_confirm']) ?></button>
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

echo '<div class="cu-muted text-center mt-3"><i class="fa-solid fa-shield-halved me-1"></i>' . htmlspecialchars($lang->changeusername['foot_note']) . '</div>';
echo '</div>';

// Строки для JS: ключи js_* без префикса
$cuJsLang = [];
foreach ($lang->changeusername as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $cuJsLang[substr((string)$k, 3)] = (string)$v;
    }
}
echo '<script>const AGS_LANG = ' . json_encode($cuJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
echo '<script src="' . $BASEURL . '/admin/scripts/changeusername.js?ver=2"></script>';

stdfoot();

// ═══════════════════════════════════════════════════════════════════════
// VIEW HELPERS
// ═══════════════════════════════════════════════════════════════════════

function cu_form(string $self, string $key, string $target, string $username): void
{
    global $lang;
    ?>
    <form method="post" action="<?= $self ?>" class="cu-card p-3 p-md-4" id="username-change-form" data-self="<?= $self ?>" novalidate>
        <input type="hidden" name="act" value="changeusername">
        <input type="hidden" name="my_post_key" value="<?= $key ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="user-id" class="form-label"><i class="fa-solid fa-magnifying-glass"></i><?= htmlspecialchars($lang->changeusername['lbl_user']) ?> <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                    <input type="text" id="user-id" name="id" value="<?= htmlspecialchars($target) ?>" class="form-control"
                           placeholder="<?= htmlspecialchars($lang->changeusername['ph_user']) ?>" required autocomplete="off" autofocus>
                </div>
                <div class="form-text"><?= htmlspecialchars($lang->changeusername['hint_user']) ?></div>
            </div>
            <div class="col-md-6">
                <label for="username" class="form-label"><i class="fa-solid fa-signature"></i><?= htmlspecialchars($lang->changeusername['lbl_new']) ?> <span class="text-danger">*</span></label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" class="form-control"
                           placeholder="<?= htmlspecialchars($lang->changeusername['ph_new']) ?>" required minlength="3" maxlength="25" autocomplete="off">
                </div>
                <div class="form-text" id="cuNameHint"><?= htmlspecialchars($lang->changeusername['hint_new']) ?></div>
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
            <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-eraser me-1"></i><?= htmlspecialchars($lang->changeusername['btn_clear']) ?></button>
            <button type="submit" class="btn btn-primary px-4 submit-button"><i class="fa-solid fa-arrow-right me-1"></i><?= htmlspecialchars($lang->changeusername['btn_continue']) ?></button>
        </div>
    </form>

    <?php
}