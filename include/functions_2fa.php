<?php

declare(strict_types=1);


// ── Secret generation ─────────────────────────────────────────────────────────

/**
 * Generates a random base32-encoded secret (160 bits = 32 chars).
 */
function totp_generate_secret(): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = '';
    $bytes    = random_bytes(20);

    for ($i = 0; $i < 20; $i++) {
        $secret .= $alphabet[ord($bytes[$i]) & 31];
    }

    return $secret;
}

// ── Base32 decode ─────────────────────────────────────────────────────────────

function totp_base32_decode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = strtoupper($secret);
    $buffer   = 0;
    $bits     = 0;
    $output   = '';

    for ($i = 0; $i < strlen($secret); $i++) {
        $pos = strpos($alphabet, $secret[$i]);
        if ($pos === false) continue;
        $buffer = ($buffer << 5) | $pos;
        $bits  += 5;
        if ($bits >= 8) {
            $bits  -= 8;
            $output .= chr(($buffer >> $bits) & 0xFF);
        }
    }

    return $output;
}

// ── TOTP code generation ──────────────────────────────────────────────────────

/**
 * Generates a TOTP code for a given secret and timestamp.
 * Uses HMAC-SHA1 per RFC 6238.
 */
function totp_generate_code(string $secret, int $time = 0): string
{
    if ($time === 0) {
        $time = (int)floor(time() / 30);
    }

    $key     = totp_base32_decode($secret);
    $counter = pack('N*', 0) . pack('N*', $time);
    $hash    = hash_hmac('sha1', $counter, $key, true);
    $offset  = ord($hash[19]) & 0xF;
    $code    = (
        ((ord($hash[$offset])     & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
        ( ord($hash[$offset + 3]) & 0xFF)
    ) % 1_000_000;

    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

// ── TOTP verification ─────────────────────────────────────────────────────────

/**
 * Verifies a user-supplied code against the secret.
 * Allows ±1 time step (30s window) to handle clock skew.
 */
function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;

    $time = (int)floor(time() / 30);

    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_generate_code($secret, $time + $i), $code)) {
            return true;
        }
    }

    return false;
}

// ── QR code URL ───────────────────────────────────────────────────────────────

/**
 * Returns a Google Charts QR code URL for use with authenticator apps.
 */
function totp_qr_url(string $secret, string $username, string $issuer): string
{
    $label = rawurlencode($issuer . ':' . $username);
    $params = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits'    => 6,
        'period'    => 30,
    ]);

    $otpauth = rawurlencode('otpauth://totp/' . $label . '?' . $params);

    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . $otpauth;
}

// ── DB helpers ────────────────────────────────────────────────────────────────

/**
 * Returns the 2FA row for a user, or null if not set up.
 */
function totp_get(int $uid): ?array
{
    global $db;

    $row = $db->fetch_array(
        $db->simple_select('2fa', '*', "uid='{$uid}'")
    );

    return $row ?: null;
}

/**
 * Returns true if 2FA is enabled for the user.
 */
function totp_is_enabled(int $uid): bool
{
    $row = totp_get($uid);
    return $row !== null && $row['enabled'] === 'yes';
}

/**
 * Returns the secret for a user (only if enabled).
 */
function totp_get_secret(int $uid): ?string
{
    $row = totp_get($uid);
    if ($row === null || $row['enabled'] !== 'yes') return null;
    return $row['secret'];
}

/**
 * Saves a new secret and marks 2FA as enabled.
 */
function totp_enable(int $uid, string $secret): void
{
    global $db;

    $existing = totp_get($uid);

    if ($existing === null) {
        $db->insert_query('2fa', [
            'uid'        => $uid,
            'secret'     => $db->escape_string($secret),
            'enabled'    => 'yes',
            'created_at' => (int)TIMENOW,
        ]);
    } else {
        $db->update_query('2fa', [
            'secret'  => $db->escape_string($secret),
            'enabled' => 'yes',
        ], "uid='{$uid}'");
    }
}

/**
 * Disables 2FA for a user (keeps the row but marks as disabled).
 */
function totp_disable(int $uid): void
{
    global $db;

    $db->update_query('2fa', ['enabled' => 'no'], "uid='{$uid}'");
}



/**
 * Creates a pending 2FA entry and sets a cookie.
 * Call this after password is verified but before complete_login().
 */
function totp_create_pending(int $uid, string $remember, string $url): string
{
    global $db;

    // Clean up expired pending entries (older than 10 minutes)
    $db->sql_query("DELETE FROM 2fa_pending WHERE created_at < " . ((int)TIMENOW - 600));

    $token = bin2hex(random_bytes(32));

    $db->insert_query('2fa_pending', [
        'token'      => $token,
        'uid'        => $uid,
        'remember'   => $db->escape_string(substr($remember, 0, 8)),
        'url'        => $db->escape_string(substr($url, 0, 512)),
        'created_at' => (int)TIMENOW,
    ]);

    // Cookie expires in 10 minutes
    setcookie('2fa_token', $token, time() + 600, '/', '', false, true);

    return $token;
}

/**
 * Retrieves and validates a pending 2FA entry from cookie.
 * Returns the row array or null if invalid/expired.
 */
function totp_get_pending(): ?array
{
    global $db;

    $token = $_COOKIE['2fa_token'] ?? '';
    if (empty($token) || strlen($token) !== 64) return null;

    $token = $db->escape_string($token);
    $row   = $db->fetch_array(
        $db->sql_query("SELECT * FROM 2fa_pending WHERE token='{$token}' AND created_at > " . ((int)TIMENOW - 600))
    );

    return $row ?: null;
}

/**
 * Deletes a pending 2FA entry and clears the cookie.
 */
function totp_clear_pending(string $token): void
{
    global $db;

    $db->sql_query("DELETE FROM 2fa_pending WHERE token='" . $db->escape_string($token) . "'");
    setcookie('2fa_token', '', time() - 3600, '/');
}

