<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*============[Recover Hint Modern]============*/
/***********************************************/

define("SCRIPTNAME", "recoverhint.php");

function staffnamecheck($username)
{
    global $db, $lang;
    $username = strtolower($username);
    $query = $db->sql_query("SELECT id FROM users WHERE username = " . $db->sqlesc($username));

    if ($db->num_rows($query) > 0) {
        $res = $db->fetch_array($query);
        $userid = intval($res['id']);
    } else {
        stderr($lang->global['error'], 'nousername');
    }

    $filename = CONFIG_DIR . '/STAFFTEAM';
    $results = @file_get_contents($filename);
    $results = @explode(',', $results);
    if (in_array($username . ':' . $userid, $results)) {
        stderr($lang->global['error'], $lang->recover['denyaccessforstaff'], false);
        exit();
    }
}

function validusername($username)
{
    return !preg_match('|[^a-zA-Z0-9]|', $username);
}

require_once 'global.php';
include_once INC_PATH . '/functions_security.php';
require_once INC_PATH . "/functions_user.php";

gzip();
failedloginscheck('Recover');
$lang->load('recover');
define('RH_VERSION', '1.2.1');
$act = (int)$_GET['act'];

//
// STEP 1
//
if ($act == 0) {
    define('SKIP_RELOAD_CODE', true);
    stdhead($lang->recover['head'], false, 'collapse');

   
    if (!empty($_GET['error'])) {
        if ($_GET['error'] == 1) {
            echo '<div class="container mt-3"><div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> ' .
                sprintf($lang->recover['errortype3'], remaining()) .
                '</div></div>';
        } elseif ($_GET['error'] == 2) {
            echo '<div class="container mt-3"><div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> ' .
                sprintf($lang->global['invalidimagecode'], remaining()) .
                '</div></div>';
        }
    }
    ?>
    <div class="container mt-5" style="max-width:600px;">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-unlock-alt"></i> <?= $lang->recover['head'] ?>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    <?= sprintf($lang->recover['info2'], $maxloginattempts) ?>
                </p>
                <form method="post" action="recoverhint.php?act=1" name="recover"
                      onsubmit="this.send.disabled=true; this.send.value='<?= $lang->global['pleasewait'] ?>';">
                    <div class="mb-3">
                        <label for="username" class="form-label"><?= $lang->recover['fieldusername'] ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                    </div>

                    <?php if ($iv == 'no'): ?>
                        <button type="submit" name="send" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> <?= $lang->global['buttonrecover'] ?>
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script>
        function reload() {
            document.getElementById('regimage').src =
                "<?= $BASEURL ?>/include/class_tscaptcha.php?" + (new Date()).getTime();
        }
        reload();
    </script>
    <?php
    stdfoot();
    exit();
}

//
// STEP 2
//
if ($act == 1) {
    if (($iv == 'yes' OR $iv == 'reCAPTCHA')) {
        check_code($_POST['imagestring'], 'recoverhint.php', true);
    }

    $username = htmlspecialchars_uni($_POST['username']);
    if (empty($username) || !validusername($username)) {
        failedlogins('silent', false, false);
        stderr($lang->global['error'], $lang->global['dontleavefieldsblank']);
        exit();
    }

    staffnamecheck($username);

    $res = $db->sql_query("SELECT id, username FROM users 
                           WHERE username=" . $db->sqlesc($username) . " 
                           AND ustatus='confirmed' AND enabled='yes' LIMIT 1");

    if ($db->num_rows($res) >= 1) {
        $arr = $db->fetch_array($res);
        $securehash = securehash($arr['id'] . $arr['username']);
        setcookie('securehash_recoverhint', $securehash, TIMENOW + 3600);
        redirect("recoverhint.php?act=3&id={$arr['id']}&username=$username", $lang->global['redirect']);
    } else {
        stdhead($lang->recover['head']);
        echo '<div class="container mt-3"><div class="alert alert-danger">
                <i class="fas fa-user-times"></i> ' . $lang->global['nousername'] .
            '</div></div>';
        failedlogins('silent', false, false);
        stdfoot();
    }
    exit();
}

//
// STEP 3
//
if ($act == 3) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SESSION['password_generated'] != 0) {
            print_no_permission();
        }

        $id = (int)$_GET['id'];
        int_check($id, true);
        $answer = htmlspecialchars_uni($_POST['answer']);

        if (!$answer) {
            failedlogins('silent', false, false);
            stderr($lang->global['error'], $lang->global['dontleavefieldsblank']);
        }

        $res = $db->simple_select("users", "id,username,ustatus,enabled", "id='$id'");
        $user = $db->fetch_array($res) or stderr($lang->global['error'], $lang->global['nouserid']);

        staffnamecheck($user['username']);

        $securehash = securehash($user['id'] . $user['username']);
        if ($_COOKIE['securehash_recoverhint'] != $securehash) {
            failedlogins('silent', false, false);
            print_no_permission();
        }

        $query = $db->simple_select("ts_secret_questions", "passhint,hintanswer", "userid='{$user['id']}'");
        $Array = $db->fetch_array($query);

        if (!$Array || md5($answer) != $Array['hintanswer']) {
            failedlogins('silent', false, false);
            stderr($lang->global['error'], $lang->recover['invalidanswer']);
        }

        $password = random_str();
        $md5password = md5($password);

        $salt = generate_salt();
        $saltedpw = salt_password($md5password, $salt);
        $loginkey = generate_loginkey();

        $newpass = [
            "password" => $db->escape_string($saltedpw),
            "salt" => $db->escape_string($salt),
            "loginkey" => $db->escape_string($loginkey)
        ];
        $db->update_query("users", $newpass, "id='" . $id . "'");

        ++$_SESSION['password_generated'];

        stdhead($lang->recover['head']);
        ?>
        <div class="container mt-5" style="max-width:600px;">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="fas fa-shield-alt"></i> <?= $lang->recover['generated1'] ?>
                </div>
                <div class="card-body text-center">
                    <p class="fs-5">
                        <?= $lang->recover['newpassword'] ?>:
                        <span class="fw-bold text-danger"><?= $password ?></span>
                    </p>
                    <a href="member.php?action=login" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            </div>
        </div>
        <?php
        stdfoot();
        exit();
    }

    // Вывод формы с секретным вопросом
    $id = (int)$_GET['id'];
    $username = htmlspecialchars_uni($_GET['username']);
    staffnamecheck($username);

    $res = $db->simple_select("users", "id,username,ustatus,enabled", "id='$id' AND username ='{$username}'");
    $user = $db->fetch_array($res) or stderr($lang->global['error'], $lang->global['nouserid']);

    $securehash = securehash($user['id'] . $user['username']);
    if ($_COOKIE['securehash_recoverhint'] != $securehash) {
        failedlogins('silent', false, false);
        print_no_permission();
    }

    $query = $db->simple_select("ts_secret_questions", "passhint,hintanswer", "userid='{$user['id']}'");
    $Array = $db->fetch_array($query);

    if (!$Array) {
        stderr($lang->global['error'], $lang->global['nouserid']);
    }

    $passhint = $Array['passhint'];

    stdhead($lang->recover['head']);
    ?>
    <div class="container mt-5" style="max-width:600px;">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-question-circle"></i> <?= $lang->recover['head'] ?>
            </div>
            <div class="card-body">
                <p class="text-muted"><i class="fas fa-info-circle"></i> <?= $lang->recover['info3'] ?></p>
                <form method="POST" action="recoverhint.php?act=3&id=<?= $id ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= $lang->recover['sq'] ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="text" class="form-control" value="<?= $passhint ?>" disabled>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="answer" class="form-label"><?= $lang->recover['ha'] ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-edit"></i></span>
                            <input type="text" name="answer" id="answer" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> <?= $lang->global['buttonrecover'] ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
    stdfoot();
}
?>
