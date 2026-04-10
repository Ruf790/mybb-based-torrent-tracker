<?php

declare(strict_types=1);

require_once 'global.php';

define('IN_MYBB', 1);
define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);

require_once INC_PATH . '/tsf_functions.php';

require_once INC_PATH . '/datahandler.php';


gzip();

define('UIR_VERSION', 'v0.6');

$lang->load('unbaniprequest');

// ── Resolve IP ────────────────────────────────────────────────
$userip = trim((string)($_POST['ip'] ?? ''));
if ($userip === '') {
    $userip = getip();
}

// ── Check if IP is actually banned ────────────────────────────
$query = $db->sql_query(
    "SELECT id FROM loginattempts WHERE ip = " . $db->sqlesc($userip) . " AND banned = 'yes' LIMIT 1"
);
if ($db->num_rows($query) < 1) {
    stderr($lang->unbaniprequest['error'] ?? 'Your IP is not banned.');
}

// ── Check if request already submitted ───────────────────────
$query = $db->sql_query(
    "SELECT id FROM unbanrequests
     WHERE ip = " . $db->sqlesc($userip) . "
        OR realip = " . $db->sqlesc($userip) . "
     LIMIT 1"
);
if ($db->num_rows($query) > 0) {
    stderr($lang->unbaniprequest['error2'] ?? 'You have already submitted an unban request.');
}

// ── Handle POST ───────────────────────────────────────────────
$errors  = [];
$email   = '';
$comment = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = trim((string)($_POST['email']   ?? ''));
    $comment = trim((string)($_POST['comment'] ?? ''));

    if (!check_email($email)) {
        $errors[] = $lang->unbaniprequest['error3'] ?? 'Invalid email address.';
    }

    //require_once INC_PATH . '/functions_EmailBanned.php';
   // if (emailbanned($email)) {
   //     $errors[] = $lang->unbaniprequest['error4'] ?? 'This email address is banned.';
   // }
	
	
	
if (is_banned_email($email, true)) {
    $errors[] = $lang->unbaniprequest['error4'] ?? 'This email address is banned.';
}
	
	

    if (strlen($comment) < 10) {
        $errors[] = $lang->unbaniprequest['error5'] ?? 'Comment must be at least 10 characters.';
    }

    if (empty($errors)) {
        $query = $db->sql_query(
            "INSERT INTO unbanrequests (ip, realip, email, comment, added)
             VALUES (
                 " . $db->sqlesc($userip) . ",
                 " . $db->sqlesc(getip()) . ",
                 " . $db->sqlesc($email) . ",
                 " . $db->sqlesc($comment) . ",
                 " . TIMENOW . "
             )"
        );

        $newid = $db->insert_id();

        if ($db->affected_rows() && $newid) {
            // Notify staff via PM
            $query = $db->sql_query(
                "SELECT usergroups FROM staffpanel
                 WHERE name = 'viewunbaniprequest'
                    OR filename = 'viewunbaniprequest.php'
                 LIMIT 1"
            );

            if ($db->num_rows($query) > 0) {
                $permusergroups = $db->fetch_field($query, 'usergroups');
                $permusergroups = trim(str_replace(['[', ']'], '', (string)$permusergroups));

                if ($permusergroups !== '') {
                    $query = $db->sql_query(
                        "SELECT id FROM users WHERE usergroup IN ({$permusergroups})"
                    );

                    if ($db->num_rows($query) > 0) {
                        $subject = $lang->unbaniprequest['subject'] ?? 'Unban IP Request';
                        $msg     = sprintf(
                            $lang->unbaniprequest['message'] ?? 'Unban request from %s. View: %s',
                            htmlspecialchars_uni($userip),
                            $BASEURL . '/admin/index.php?act=viewunbaniprequest#show_id' . $newid,
                        );

                        require_once INC_PATH . '/functions_pm.php';
                        while ($pmstaff = mysqli_fetch_assoc($query)) {
    send_pm([
        'subject' => $subject,
        'message' => $msg,
        'touid'   => (int)$pmstaff['id'],
        'sender'  => ['uid' => -1],
    ], -1, true);
}
						
						
						
						
						
						
						
                    }
                }
            }

            // Success page
            stdhead($lang->unbaniprequest["title"]);
            
			
			
stdok(
    $lang->unbaniprequest['saved']  ?? 'Your request has been saved.',
    $lang->unbaniprequest['title']  ?? 'Unban IP Request',
    $lang->unbaniprequest['head']   ?? 'Unban IP Request',
);
			
			
			
			
			
			
         

        } else {
            stderr($lang->global['dberror'] ?? 'Database error.');
        }
    }
}

// ── Show form ─────────────────────────────────────────────────
stdhead($lang->unbaniprequest["title"]);

if (!empty($errors)) {
    
	
	echo '<div class="container mt-3">'. inline_error($errors).'</div>';
}

echo render_form($userip, $email, $comment, $lang);

stdfoot();

// ── Render helpers ────────────────────────────────────────────


function render_form(string $userip, string $email, string $comment, object $lang): string
{
    $title   = htmlspecialchars_uni($lang->unbaniprequest['title']   ?? 'Unban IP Request');
    $info    = $lang->unbaniprequest['info']    ?? '';
    $field1  = htmlspecialchars_uni($lang->unbaniprequest['field1']  ?? 'IP Address');
    $field2  = $lang->unbaniprequest['field2']  ?? '';
    $field3  = htmlspecialchars_uni($lang->unbaniprequest['field3']  ?? 'Email');
    $field4  = $lang->unbaniprequest['field4']  ?? '';
    $field5  = htmlspecialchars_uni($lang->unbaniprequest['field5']  ?? 'Comment');
    $field6  = $lang->unbaniprequest['field6']  ?? '';
    $field7  = htmlspecialchars_uni($lang->unbaniprequest['field7']  ?? '');
    $field8  = htmlspecialchars_uni($lang->unbaniprequest['field8']  ?? 'Submit');
    $field9  = htmlspecialchars_uni($lang->unbaniprequest['field9']  ?? 'Reset');
    $action  = htmlspecialchars_uni($_SERVER['SCRIPT_NAME']);
    $ip_val  = htmlspecialchars_uni($userip);
    $em_val  = htmlspecialchars_uni($email);
    $co_val  = htmlspecialchars_uni($comment);

    return '
    <div class="container mt-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>' . $title . '</h5>
            </div>
            <div class="card-body p-4">
                ' . ($info !== '' ? '<p class="text-muted mb-4">' . $info . '</p>' : '') . '
                <form method="post" action="' . $action . '" novalidate>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">' . $field1 . '</label>
                        <p class="text-muted small mb-1">' . $field2 . '</p>
                        <input type="text" name="ip" class="form-control"
                               value="' . $ip_val . '">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">' . $field3 . '</label>
                        <p class="text-muted small mb-1">' . $field4 . '</p>
                        <input type="email" name="email" class="form-control"
                               value="' . $em_val . '" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">' . $field5 . '</label>
                        <p class="text-muted small mb-1">' . $field6 . '</p>
                        <textarea name="comment" class="form-control"
                                  rows="4" required>' . $co_val . '</textarea>
                        <div class="form-text">Minimum 10 characters.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit"  class="btn btn-danger px-4">
                            <i class="bi bi-send me-2"></i>' . $field8 . '
                        </button>
                        <button type="reset" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>' . $field9 . '
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>';
}