<?php
/***********************************************/
/*============[Recover Password — Email Token]=*/
/***********************************************/
define('IN_MYBB', 1);
define('ALLOWABLE_PAGE', 1);
define('SCRIPTNAME', 'recoverhint.php');
define('RH_VERSION', '2.0.0');

// Session must start before global.php to ensure $_SESSION is available
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token_recover'])) {
        $_SESSION['csrf_token_recover'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token_recover'];
}

function verify_csrf_token($token)
{
    if (empty($_SESSION['csrf_token_recover'])) return false;
    return hash_equals($_SESSION['csrf_token_recover'], (string)$token);
}

function validusername($username)
{
    return !preg_match('|[^a-zA-Z0-9]|', $username);
}

function staffnamecheck($username)
{
    global $db, $lang;
    $username = strtolower($username);

    $query = $db->sql_query_prepared("SELECT id FROM users WHERE username = ?", [$username]);
    if ($db->num_rows($query) > 0) {
        $res    = $db->fetch_array($query);
        $userid = intval($res['id']);
    } else {
        stderr($lang->global['error'], $lang->global['nousername']);
    }

    $filename = CONFIG_DIR . '/STAFFTEAM';
    $results  = @file_get_contents($filename);
    $results  = @explode(',', $results);
    if (in_array($username . ':' . $userid, $results)) {
        stderr($lang->global['error'], $lang->recover['denyaccessforstaff'], false);
        exit();
    }
}

/**
 * Generate a cryptographically secure reset token.
 * Returns [plaintext, hash] — plaintext goes in the email, hash is stored in DB.
 */
function generate_reset_token()
{
    $plain = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $plain);
    return [$plain, $hash];
}

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once 'global.php';
require_once INC_PATH . '/function_loginattemptcheck.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/datahandler.php';

gzip();
failedloginscheck('Recover');
$lang->load('recover');

$act = (int)($_GET['act'] ?? 0);

// ── STEP 1: Username form ─────────────────────────────────────────────────────
if ($act === 0) {
	
	// Reset password_generated so user can request again
    unset($_SESSION['password_generated']);
	
    define('SKIP_RELOAD_CODE', true);
    stdhead($lang->recover['head'], false, 'collapse');

    if (!empty($_GET['error'])) {
        $errorCode = (int)$_GET['error'];
        $messages  = [
            1 => sprintf($lang->recover['errortype3'], remaining()),
            2 => sprintf($lang->global['invalidimagecode'], remaining()),
        ];
        if (isset($messages[$errorCode])) {
            echo '<div class="container mt-3">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> ' . $messages[$errorCode] . '
                    </div>
                  </div>';
        }
    }

    $csrf = generate_csrf_token();
    ?>
    <div class="container mt-5" style="max-width:560px;">
        <div class="card shadow border-0 rounded-4">
            <div class="card-header bg-primary text-white fw-bold rounded-top-4">
                <i class="fas fa-envelope-open-text"></i> <?= $lang->recover['head'] ?>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    <?= sprintf($lang->recover['info2'], $maxloginattempts) ?>
                </p>
                <form method="post" action="recoverhint.php?act=1" autocomplete="off"
                      onsubmit="this.send.disabled=true; this.send.value='<?= $lang->global['pleasewait'] ?>';">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">
                            <?= $lang->recover['fieldusername'] ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" id="username"
                                   class="form-control" autocomplete="off" required>
                        </div>
                    </div>


                    <button type="submit" name="send" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane"></i> <?= $lang->global['buttonrecover'] ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

  
    <?php
    stdfoot();
    exit();
}

// ── STEP 2: Process form → save token → send email ───────────────────────────
if ($act === 1) {
	

   if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    stderr($lang->global['error'], $lang->global['dontleavefieldsblank']);
    exit();
   }



    $username = htmlspecialchars_uni($_POST['username'] ?? '');
    if (empty($username) || !validusername($username)) {
        failedlogins('silent', false, false);
        stderr($lang->global['error'], $lang->global['dontleavefieldsblank']);
        exit();
    }



    $res = $db->sql_query_prepared(
        "SELECT id, username, email FROM users
         WHERE username = ?
           AND ustatus = 'confirmed'
           AND enabled  = 'yes'
         LIMIT 1",
        [strtolower($username)]
    );

    // Anti-enumeration: always show the same message regardless of result
    if ($db->num_rows($res) >= 1) {
        $arr = $db->fetch_array($res);

        [$plain, $hash] = generate_reset_token();
        $expires = TIMENOW + 3600;

        $db->sql_query_prepared(
            "DELETE FROM password_reset_tokens WHERE userid = ?",
            [intval($arr['id'])]
        );

        $insert_token = [
            "userid"     => intval($arr['id']),
            "token_hash" => $hash,
            "expires_at" => $expires,
            "ip"         => $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        $columns      = array_keys($insert_token);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $db->sql_query_prepared(
            "INSERT INTO password_reset_tokens (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
            array_values($insert_token)
        );

        $link    = $BASEURL . '/recoverhint.php?act=3&token=' . urlencode($plain);
        $subject = '[' . $SITENAME . '] ' . $lang->recover['email_subject'];
        $body    = sprintf($lang->recover['email_body'], $arr['username'], $link, 60);

        my_mail($arr['email'], $subject, $body);

    } else {
        failedlogins('silent', false, false);
    }

    // Invalidate CSRF token after use
    unset($_SESSION['csrf_token_recover']);

    stdhead($lang->recover['head']);
    ?>
    <div class="container mt-5" style="max-width:560px;">
        <div class="card shadow border-0 rounded-4">
            <div class="card-header bg-success text-white fw-bold rounded-top-4">
                <i class="fas fa-check-circle"></i> <?= $lang->recover['sent_title'] ?>
            </div>
            <div class="card-body p-4 text-center">
                <p class="fs-6"><?= $lang->recover['sent_body'] ?></p>
                <small class="text-muted"><?= $lang->recover['sent_hint'] ?></small>
            </div>
        </div>
    </div>
    <?php
    stdfoot();
    exit();
}

// ── STEP 3: Token link → new password form ────────────────────────────────────
if ($act === 3) {

    $token_plain = trim($_GET['token'] ?? '');

    if (empty($token_plain)) {
        stderr($lang->global['error'], $lang->recover['invalid_token']);
        exit();
    }

    $token_hash = hash('sha256', $token_plain);

    $res = $db->sql_query_prepared(
        "SELECT t.userid, t.expires_at, u.username, u.email, u.ustatus, u.enabled
         FROM password_reset_tokens t
         JOIN users u ON u.id = t.userid
         WHERE t.token_hash = ?
         LIMIT 1",
        [$token_hash]
    );

    if ($db->num_rows($res) < 1) {
        failedlogins('silent', false, false);
        stderr($lang->global['error'], $lang->recover['invalid_token']);
        exit();
    }

    $row = $db->fetch_array($res);

    if ((int)$row['expires_at'] < TIMENOW) {
        $db->sql_query_prepared(
            "DELETE FROM password_reset_tokens WHERE userid = ?",
            [intval($row['userid'])]
        );
        stderr($lang->global['error'], $lang->recover['token_expired']);
        exit();
    }

    if ($row['ustatus'] !== 'confirmed' || $row['enabled'] !== 'yes') {
        stderr($lang->global['error'], $lang->global['nouserid']);
        exit();
    }

    staffnamecheck($row['username']);

    // ── POST: save new password ───────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            stderr($lang->global['error'], $lang->global['dontleavefieldsblank']);
            exit();
        }

        //if (!empty($_SESSION['password_generated'])) {
        //   print_no_permission();
        //}

        $newpass1 = $_POST['password']  ?? '';
        $newpass2 = $_POST['password2'] ?? '';
		
		

        if (empty($newpass1) || $newpass1 !== $newpass2) {
			stderr($lang->recover['passwords_mismatch']);
            exit();
        }

        if (strlen($newpass1) < $minpasswordlength) {
            stderr($lang->recover['password_tooshort']);
            exit();
        }

        
        require_once INC_PATH . '/datahandlers/user.php';

        $userhandler = new UserDataHandler('update');
        $userhandler->set_data([
            'uid'       => (int)$row['userid'],
            'password'  => $newpass1,
            'password2' => $newpass2,
        ]);

        if (!$userhandler->validate_user()) {
            $errors = $userhandler->get_friendly_errors();
            stderr($lang->global['error'], implode('<br>', $errors));
            exit();
        }

        $userhandler->update_user();

        my_setcookie('mybbuser', $row['userid'] . '_' . $userhandler->data['loginkey'], null, true, 'lax');

        $db->sql_query_prepared(
            "DELETE FROM password_reset_tokens WHERE userid = ?",
            [intval($row['userid'])]
        );

        // ── Log + notify account owner ─────────────────────────────────────
        require_once INC_PATH . '/function_loginattemptcheck.php';
        log_login((int)$row['userid'], 'success', 'recover');

        $reset_ip   = get_ip();
        $reset_time = date('Y-m-d H:i:s');
        $reset_geo  = geo_by_ip($reset_ip);

        my_mail(
            $row['email'],
            '[' . $SITENAME . '] Your password was changed',
            "Your password was just reset using the password recovery link.\n\n"
            . "IP       : {$reset_ip}\n"
            . "Location : " . $reset_geo['country'] . ' / ' . $reset_geo['city'] . "\n"
            . "Time     : {$reset_time}\n\n"
            . "If this wasn't you, please contact support immediately and change "
            . "your password again."
        );

        $_SESSION['password_generated'] = 1;
        unset($_SESSION['csrf_token_recover']);

        stdok(
          $lang->recover['password_changed'],
          $lang->recover['generated1'],
          'Welcome back!'
        );
    }

    // ── GET: show password form ───────────────────────────────────────────────
    $csrf = generate_csrf_token();
	
	
    stdhead($lang->recover['head']);
    ?>
  

<div class="container mt-5" style="max-width:560px;">
    <div class="card shadow border-0 rounded-4">
        <div class="card-header bg-primary text-white fw-bold rounded-top-4">
            <i class="fas fa-lock"></i> <?= $lang->recover['newpassword_title'] ?>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-4">
                <?= sprintf($lang->recover['newpassword_info'], htmlspecialchars($row['username'])) ?>
            </p>

            <?php if ($requirecomplexpasswords == 1): ?>
            <div class="alert alert-info py-2 small mb-3">
                <i class="fas fa-info-circle me-1"></i>
                Password must be at least <strong><?= (int)$minpasswordlength ?></strong> characters
                and contain uppercase, lowercase letters and a number.
            </div>
            <?php else: ?>
            <div class="alert alert-secondary py-2 small mb-3">
                <i class="fas fa-info-circle me-1"></i>
                Password must be between <strong><?= (int)$minpasswordlength ?></strong>
                and <strong><?= (int)$maxpasswordlength ?></strong> characters.
            </div>
            <?php endif; ?>

            <form method="POST"
                  action="recoverhint.php?act=3&token=<?= urlencode($token_plain) ?>"
                  autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">
                        <?= $lang->recover['newpassword'] ?>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="password"
                               class="form-control"
                               minlength="<?= (int)$minpasswordlength ?>"
                               maxlength="<?= (int)$maxpasswordlength ?>"
                               autocomplete="new-password" required>
                        <button type="button" class="btn btn-outline-secondary"
                                onclick="togglePwd('password',this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="progress mt-2" style="height:4px;">
                        <div id="strength-bar" class="progress-bar" style="width:0%;transition:.3s"></div>
                    </div>
                    <small id="strength-label" class="text-muted"></small>
                </div>

                <div class="mb-4">
                    <label for="password2" class="form-label fw-semibold">
                        <?= $lang->recover['confirmpassword'] ?>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                        <input type="password" name="password2" id="password2"
                               class="form-control"
                               minlength="<?= (int)$minpasswordlength ?>"
                               maxlength="<?= (int)$maxpasswordlength ?>"
                               autocomplete="new-password" required>
                        <button type="button" class="btn btn-outline-secondary"
                                onclick="togglePwd('password2',this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> <?= $lang->global['buttonrecover'] ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}

const minLen     = <?= (int)$minpasswordlength ?>;
const complex    = <?= (int)$requirecomplexpasswords ?>;

document.getElementById('password').addEventListener('input', function () {
    const bar   = document.getElementById('strength-bar');
    const label = document.getElementById('strength-label');
    const v     = this.value;
    let score   = 0;

    if (v.length >= minLen) score++;
    if (v.length >= 12)     score++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
    if (/[0-9]/.test(v))    score++;
    if (/[^a-zA-Z0-9]/.test(v)) score++;

    // If complex passwords required, penalise missing criteria
    if (complex) {
        if (!/[A-Z]/.test(v)) score = Math.max(0, score - 1);
        if (!/[0-9]/.test(v)) score = Math.max(0, score - 1);
    }

    const levels = [
        { w: '0%',   cls: '',           txt: '' },
        { w: '25%',  cls: 'bg-danger',  txt: 'Weak' },
        { w: '50%',  cls: 'bg-warning', txt: 'Fair' },
        { w: '75%',  cls: 'bg-info',    txt: 'Good' },
        { w: '100%', cls: 'bg-success', txt: 'Strong' },
    ];
    const lvl = levels[Math.min(score, 4)];
    bar.style.width   = lvl.w;
    bar.className     = 'progress-bar ' + lvl.cls;
    label.textContent = lvl.txt;
});
</script>
    <?php
    stdfoot();
}
?>