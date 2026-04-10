<?php

declare(strict_types=1);


/**
 * Returns remaining login attempts as a coloured HTML span.
 * Red when 2 or fewer attempts left, green otherwise.
 */
function remaining(string $type = 'login'): string
{
    global $db, $failedlogincount;

    $ip    = $db->sqlesc(USERIPADDRESS);
    $query = $db->sql_query("SELECT attempts FROM loginattempts WHERE ip = {$ip} LIMIT 1");
    $row   = mysqli_fetch_assoc($query);

    $total = ($db->num_rows($query) > 0) ? (int)$row['attempts'] : 0;
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

    $ip  = get_ip();
    $esc = $db->sqlesc($ip);

    $query = $db->sql_query("SELECT attempts FROM loginattempts WHERE ip = {$esc} LIMIT 1");
    $total = ($db->num_rows($query) > 0)
        ? (int)$db->fetch_field($query, 'attempts')
        : 0;

    if ((int)$failedlogincount <= $total) {
        $db->sql_query("UPDATE loginattempts SET banned = 'yes' WHERE ip = {$esc}");

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
 * @param string   $type     'login' | 'silent' | any stderr-compatible error key
 * @param bool     $recover  Mark the attempt as a password-recovery attempt
 * @param bool     $head     Pass $head through to stderr()
 * @param bool     $msg      Send a warning PM to the affected user
 * @param int      $uid      UID of the affected user (required when $msg = true)
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
    $escapedIp = $db->sqlesc($ip);

    [$count] = mysqli_fetch_row(
        $db->sql_query("SELECT COUNT(*) FROM loginattempts WHERE ip = {$escapedIp} LIMIT 1")
    );

    if ((int)$count === 0) {
        $db->sql_query(
            "INSERT INTO loginattempts (ip, added, attempts)
             VALUES ({$escapedIp}, {$added}, 1)"
        );
    } else {
        $db->sql_query(
            "UPDATE loginattempts SET attempts = attempts + 1
             WHERE ip = {$escapedIp}"
        );
    }

    // ── Mark as recovery attempt if requested ─────────────────
    if ($recover) {
        $db->sql_query(
            "UPDATE loginattempts SET type = 'recover'
             WHERE ip = {$escapedIp}"
        );
    }

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
        $query    = $db->simple_select(
            'users',
            'loginattempts, loginlockoutexpiry',
            "id='{$uid}'",
            ['limit' => 1],
        );
        $attempts = $db->fetch_array($query);

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
                $db->update_query(
                    'users',
                    ['loginlockoutexpiry' => $attempts['loginlockoutexpiry']],
                    "id='{$uid}'",
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
            $db->update_query(
                'users',
                ['loginattempts' => 0, 'loginlockoutexpiry' => 0],
                "id='{$uid}'",
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