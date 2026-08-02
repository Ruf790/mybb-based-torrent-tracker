<?php

declare(strict_types=1);


/**
 * Returns remaining login attempts as a coloured HTML span.
 * Red when 2 or fewer attempts left, green otherwise.
 */
function remaining(string $type = 'login'): string
{
    global $db, $failedlogincount;

    $query = $db->sql_query_prepared("SELECT attempts FROM loginattempts WHERE ip = ? LIMIT 1", [USERIPADDRESS]);
    $row   = $query ? $db->fetch_array($query) : null;

    $total = ($query && $db->num_rows($query) > 0) ? (int)$row['attempts'] : 0;
    $left  = max(0, (int)$failedlogincount - $total);
    $color = $left <= 2 ? '#f90510' : '#037621';

    return '<span style="color:' . $color . '">[' . $left . ']</span>';
}

/**
 * Checks whether the current IP has exceeded the login attempt limit.
 * If so, marks it as banned and calls stderr().
 */
function failedloginscheck(string $type = 'Login'): void
{
    global $db, $lang, $BASEURL, $failedlogincount;

    $ip = get_ip();

    $query = $db->sql_query_prepared("SELECT attempts FROM loginattempts WHERE ip = ? LIMIT 1", [$ip]);
    $total = ($query && $db->num_rows($query) > 0)
        ? (int)$db->fetch_field($query, 'attempts')
        : 0;

    if ((int)$failedlogincount <= $total) {
        $db->sql_query_prepared("UPDATE loginattempts SET banned = 'yes' WHERE ip = ?", [$ip]);

        stderr(
            sprintf(
                $lang->global['xlocked2'],
                '<a href="' . $BASEURL . '/unbaniprequest.php">',
                '<a href="' . $BASEURL . '/contactus.php">',
            ),
            sprintf($lang->global['xlocked'], $type, $type),
            403,
            '403',
        );
    }
}


/**
 * Records a failed login attempt and optionally notifies the account owner.
 *
 * @param string $type    'login' | 'silent' | any stderr-compatible error key
 * @param bool   $recover Mark the attempt as a password-recovery attempt
 * @param bool   $head    Pass $head through to stderr()
 * @param bool   $msg     Send a warning PM to the affected user
 * @param int    $uid     UID of the affected user (required when $msg = true)
 */
function failedlogins(
    string $type    = 'login',
    bool   $recover = false,
    bool   $head    = true,
    bool   $msg     = false,
    int    $uid     = 0,
): void {
    global $db, $lang;
    global $username, $password, $md5pw, $ipaddress, $iphost;

    // Resolve IP once
    $ip    = get_ip();
    $added = TIMENOW;

    // ── Upsert login attempt ──────────────────────────────────
    $countQuery = $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM loginattempts WHERE ip = ? LIMIT 1", [$ip]);
    $count      = $countQuery ? (int)$db->fetch_field($countQuery, 'cnt') : 0;

    if ($count === 0) {
        $db->sql_query_prepared(
            "INSERT INTO loginattempts (ip, added, attempts) VALUES (?, ?, 1)",
            [$ip, $added]
        );
    } else {
        $db->sql_query_prepared(
            "UPDATE loginattempts SET attempts = attempts + 1 WHERE ip = ?",
            [$ip]
        );
    }

    // ── Mark as recovery attempt if requested ─────────────────
    if ($recover) {
        $db->sql_query_prepared(
            "UPDATE loginattempts SET type = 'recover' WHERE ip = ?",
            [$ip]
        );
    }

    // ── Write to login_log ─────────────────────────────────
    log_login($uid, 'fail', $recover ? 'recover' : 'login');

    // ── Send warning PM to account owner ─────────────────────
    if ($msg && $uid > 0) {
        require_once INC_PATH . '/functions_pm.php';

        $pm = [
            'subject' => $lang->global['warning'],
            'message' => sprintf(
                $lang->global['accountwarn'],
                $username,
                $password,
                $md5pw,
                $ipaddress,
                $iphost,
            ),
            'touid'  => $uid,
            'sender' => ['uid' => -1],
        ];

        send_pm($pm, -1, true);
    }

    // ── Silent / login: caller handles the rest ───────────────
    if ($type === 'silent' || $type === 'login') {
        return;
    }

    stderr($lang->global['error'], $type, false, $head);
}


/**
 * Checks whether a user (or guest) is allowed to attempt a login.
 *
 * Returns:
 *   - int  0              → no prior failed attempts / lockout just expired
 *   - int  $loginattempts → number of failed attempts so far (> 0, below threshold)
 *   - false               → currently locked out ($fatal = false path)
 *
 * When $fatal = true and the account/cookie is locked out, stderr() is called
 * and execution stops inside stderr() — this function never returns in that case.
 *
 * @param int  $uid   User ID to check (0 = guest / cookie-only check)
 * @param bool $fatal Call stderr() and halt when locked out (default true)
 *
 * @return int|false
 */
function login_attempt_check(int|string|null $uid = 0, bool $fatal = true): int|false
{
    global $mybb, $lang, $db, $failedlogincount;
    $uid = (int)$uid;

    $now              = TIMENOW;
    $failedlogintime  = 1;           // lock-out duration in minutes
    $failedlogincount = (int)($failedlogincount ?? 0); // populated via $cache->read("SIGNUP")
    $attempts         = [];

    // ── Per-user check (UID supplied) ─────────────────────────
    if ($uid > 0) {
        $query    = $db->sql_query_prepared(
            "SELECT loginattempts, loginlockoutexpiry FROM users WHERE id = ? LIMIT 1",
            [$uid]
        );
        $attempts = $query ? $db->fetch_array($query) : null;

        // No failed attempts recorded at all → allow immediately
        if ((int)($attempts['loginattempts'] ?? 0) <= 0) {
            return 0;
        }
    }
    // ── Guest cookie lockout ──────────────────────────────────
    elseif (
        !empty($mybb->cookies['lockoutexpiry']) &&
        (int)$mybb->cookies['lockoutexpiry'] > $now
    ) {
        if ($fatal) {
            [$h, $m, $s] = login_hms_from_seconds((int)$mybb->cookies['lockoutexpiry'] - $now);
            stderr(sprintf($lang->member['failed_login_wait'], $h, $m, $s));
        }
        return false;
    }

    // ── Threshold check ───────────────────────────────────────
    $loginAttempts = (int)($attempts['loginattempts'] ?? 0);

    if ($failedlogincount > 0 && $loginAttempts >= $failedlogincount) {

        // Set expiry if not already set
        if (empty($attempts['loginlockoutexpiry'])) {
            $attempts['loginlockoutexpiry'] = $now + ($failedlogintime * 60);

            my_setcookie('lockoutexpiry', (string)$attempts['loginlockoutexpiry']);

            if ($uid > 0) {
                $db->sql_query_prepared(
                    "UPDATE users SET loginlockoutexpiry = ? WHERE id = ?",
                    [$attempts['loginlockoutexpiry'], $uid]
                );
            }
        }

        $lockoutExpiry = empty($mybb->cookies['lockoutexpiry'])
            ? (int)$attempts['loginlockoutexpiry']
            : (int)$mybb->cookies['lockoutexpiry'];

        // Still locked out?
        if ($lockoutExpiry > $now) {
            if ($fatal) {
                [$h, $m, $s] = login_hms_from_seconds($lockoutExpiry - $now);
                stderr(sprintf($lang->member['failed_login_wait'], $h, $m, $s));
            }
            return false;
        }

        // Lockout expired → reset and allow
        if ($uid > 0) {
            $db->sql_query_prepared(
                "UPDATE users SET loginattempts = 0, loginlockoutexpiry = 0 WHERE id = ?",
                [$uid]
            );
        }
        my_unsetcookie('lockoutexpiry');

        return 0;
    }

    // ── Below threshold → return current attempt count ────────
    return $loginAttempts;
}

/**
 * Splits a duration in seconds into [hours, minutes, seconds].
 *
 * @return array{0: int, 1: int, 2: int}
 */
function login_hms_from_seconds(int $totalSeconds): array
{
    $hours   = (int)floor($totalSeconds / 3600);
    $minutes = (int)floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;

    return [$hours, $minutes, $seconds];
}



/**
 * Resolves geolocation for an IP address via ip-api.com (free, no key required).
 * Returns ['country' => '...', 'city' => '...'].
 * Local/private addresses return 'Local' without making an HTTP request.
 */
function geo_by_ip(string $ip): array
{
    if (
        $ip === ''
        || str_starts_with($ip, '127.')
        || str_starts_with($ip, '192.168.')
        || str_starts_with($ip, '10.')
        || $ip === '::1'
    ) {
        return ['country' => 'Local', 'city' => 'Local'];
    }

    $url  = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,city';
    $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
    $json = @file_get_contents($url, false, $ctx);

    if ($json === false) {
        return ['country' => '', 'city' => ''];
    }

    $data = json_decode($json, true);

    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return ['country' => '', 'city' => ''];
    }

    return [
        'country' => (string)($data['country'] ?? ''),
        'city'    => (string)($data['city']    ?? ''),
    ];
}

/**
 * Writes a login event (success or failure) to login_log.
 * For successful logins from a new country, triggers an admin e-mail alert.
 *
 * @param int    $uid    User ID (0 for guests / unknown)
 * @param string $status 'success' | 'fail'
 * @param string $type   'login'   | 'recover'
 */
function log_login(int $uid, string $status = 'fail', string $type = 'login'): void
{
    global $db, $mybb;

    $ip         = get_ip();
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $now        = (int)TIMENOW;

    // Browser/OS fingerprint — hash of User-Agent + Accept-Language
    $accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $fingerprint = substr(hash('sha256', $user_agent . '|' . $accept_lang), 0, 32);

    // Geolocation
    $geo     = geo_by_ip($ip);
    $country = $geo['country'];
    $city    = $geo['city'];

    // Suspicious? — only for successful logins with a known country
    $suspicious  = 'no';
    $fp_mismatch = false;

    if ($status === 'success' && $uid > 0) {
        if ($country !== '' && $country !== 'Local') {
            $prevQuery = $db->sql_query_prepared(
                "SELECT country FROM login_log WHERE uid = ? AND status = 'success' AND suspicious = 'no' AND country != '' ORDER BY datetime DESC LIMIT 1",
                [$uid]
            );
            $prev = $prevQuery ? $db->fetch_field($prevQuery, 'country') : null;

            if ($prev !== null && $prev !== '' && $prev !== $country) {
                $suspicious = 'yes';
            }
        }

        // Fingerprint check — known device list
        $knownQuery = $db->sql_query_prepared(
            "SELECT id FROM user_devices WHERE uid = ? AND fingerprint = ? LIMIT 1",
            [$uid, $fingerprint]
        );
        $known = $knownQuery ? $db->fetch_field($knownQuery, 'id') : null;

        if ($known) {
            // Known device — just update last_seen
            $db->sql_query_prepared("UPDATE user_devices SET last_seen = ? WHERE id = ?", [$now, $known]);
        } else {
            // New device — add to list and alert user
            $db->sql_query_prepared(
                "INSERT INTO user_devices (`uid`,`fingerprint`,`user_agent`,`first_seen`,`last_seen`) VALUES (?,?,?,?,?)",
                [$uid, $fingerprint, $user_agent, $now, $now]
            );
            $fp_mismatch = true;
        }
    }

    // Is the IP currently banned in loginattempts?
    $banned = 'no';
    if ($ip !== '') {
        $banQuery = $db->sql_query_prepared("SELECT banned FROM loginattempts WHERE ip = ? LIMIT 1", [$ip]);
        $ban      = $banQuery ? $db->fetch_field($banQuery, 'banned') : null;
        if ($ban === 'yes') {
            $banned = 'yes';
        }
    }

    // Persist the record
    $db->sql_query_prepared(
        "INSERT INTO login_log (`uid`,`ip`,`country`,`city`,`user_agent`,`fingerprint`,`datetime`,`type`,`status`,`suspicious`,`banned`) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
        [$uid, $ip, $country, $city, $user_agent, $fingerprint, $now, $type, $status, $suspicious, $banned]
    );

    // Clean up records older than 30 days (runs with 1% probability)
    if (random_int(1, 100) === 1) {
        $db->sql_query_prepared("DELETE FROM login_log WHERE datetime < ?", [$now - 2592000]);
    }

    // Alert admin on suspicious successful login (new country)
    if ($suspicious === 'yes') {
        notify_admin_suspicious($uid, $ip, $country, $city, $user_agent, $now);
    }

    // Soft alert on browser/OS fingerprint mismatch — informational, no logout
    if ($fp_mismatch) {
        notify_user_new_device($uid, $ip, $country, $city, $user_agent, $now);
    }
}

/**
 * Sends an e-mail alert to the site admin when a user logs in from a new country.
 */
function notify_admin_suspicious(
    int    $uid,
    string $ip,
    string $country,
    string $city,
    string $ua,
    int    $time
): void {
    global $db, $SITEEMAIL, $BASEURL;

    if ($uid <= 0) {
        return;
    }

    $query = $db->sql_query_prepared("SELECT username, email FROM users WHERE id = ?", [$uid]);
    $user  = $query ? $db->fetch_array($query) : null;

    if (empty($user['username'])) {
        return;
    }

    $site  = defined('SITENAME') ? SITENAME : 'ruff-tracker';
    $base  = $BASEURL ?? '';
    $admin = $SITEEMAIL ?? '';

    if (empty($admin)) {
        return;
    }

    $date    = date('Y-m-d H:i:s', $time);
    $subject = "[{$site}] Suspicious login: {$user['username']}";

    $message = "A login from a new country has been detected.\n\n"
             . "User         : {$user['username']} (uid={$uid})\n"
             . "Email        : {$user['email']}\n"
             . "IP           : {$ip}\n"
             . "Country/City : {$country} / {$city}\n"
             . "User-Agent   : {$ua}\n"
             . "Time         : {$date}\n\n"
             . "Profile      : {$base}/member.php?action=profile&uid={$uid}\n"
             . "Login log    : {$base}/admin/index.php?module=ts-login_log\n";

    my_mail($admin, $subject, $message);
}

/**
 * Sends an informational email to the user when a successful login is
 * detected from a browser/OS fingerprint not seen before for this account.
 * Non-blocking — the login proceeds normally.
 */
function notify_user_new_device(
    int    $uid,
    string $ip,
    string $country,
    string $city,
    string $ua,
    int    $time
): void {
    global $db, $SITENAME;

    if ($uid <= 0) return;

    $query = $db->sql_query_prepared("SELECT username, email FROM users WHERE id = ? LIMIT 1", [$uid]);
    $user  = $query ? $db->fetch_array($query) : null;

    if (empty($user['email'])) return;

    $date    = date('Y-m-d H:i:s', $time);
    $subject = '[' . $SITENAME . '] New device sign-in';

    $message = "A successful login was just made from a browser or device we haven't seen on your account before.\n\n"
             . "IP           : {$ip}\n"
             . "Country/City : {$country} / {$city}\n"
             . "Browser      : {$ua}\n"
             . "Time         : {$date}\n\n"
             . "If this was you, no action is needed.\n"
             . "If you don't recognize this device, please change your password and review your active session.";

    my_mail($user['email'], $subject, $message);
}