<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="error-message">❌ Error! Direct initialization of this file is not allowed.</div>');
}

define('IPS_VERSION', 'v0.3');

/**
 * IP Search Manager (users.regip = varbinary(16), login_log.ip = varchar(45))
 */
final class IPSearchManager
{
    private $db;
    private string $baseUrl;
    private string $selfUrl;     // ссылка на эту страницу (?act=ipsearch), НЕ html-escaped
    private string $scriptName;  // SCRIPT_NAME - action для GET-формы поиска
    private ?string $postKey = null;

    public function __construct($database, string $baseUrl, string $selfUrl, string $scriptName)
    {
        $this->db         = $database;
        $this->baseUrl    = rtrim($baseUrl, '/');
        $this->selfUrl    = $selfUrl;
        $this->scriptName = $scriptName;
    }

    /* ------------------------------------------------------------------ */
    /*  Input / IP helpers                                                */
    /* ------------------------------------------------------------------ */

    public function getIpAddress(): string
    {
        $ip = $_POST['ip'] ?? $_GET['ip'] ?? '';
        return is_string($ip) ? trim($ip) : '';
    }

    public function validateIp(string $ip): bool
    {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Каноническая форма IP (2001:0DB8:0:0::1 -> 2001:db8::1).
     * login_log.ip сравнивается как текст, поэтому без нормализации
     * развёрнутый/заглавный IPv6 из формы ничего бы не нашёл.
     */
    public function normalizeIp(string $ip): string
    {
        if (!$this->validateIp($ip)) {
            return $ip;
        }
        $bin = inet_pton($ip);
        if ($bin === false) {
            return $ip;
        }
        $text = inet_ntop($bin);
        return $text !== false ? $text : $ip;
    }

    private function ipType(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'IPv4';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'IPv6';
        }
        return '?';
    }

    private function ipScope(string $ip): string
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ? 'Private / reserved'
            : 'Public';
    }

    /**
     * users.ip может быть как текстом, так и бинарным (4/16 байт) - приводим к тексту.
     * Возвращает НЕэкранированную строку, экранирование при выводе.
     */
    private function userIpToText(array $user): string
    {
        $raw = (string)($user['ip'] ?? '');
        if ($raw === '') {
            return '';
        }
        if (filter_var($raw, FILTER_VALIDATE_IP) !== false) {
            return $raw;
        }
        if (in_array(strlen($raw), [4, 16], true)) {
            $text = inet_ntop($raw);
            if ($text !== false) {
                return $text;
            }
        }
        return bin2hex($raw);
    }

    private function formatDateTime(int|string|null $dateTime, string $format = 'Y-m-d H:i:s'): string
    {
        if ($dateTime === null || $dateTime === '' || $dateTime === 0 || $dateTime === '0' || $dateTime === '0000-00-00 00:00:00') {
            return '—';
        }

        try {
            // users.added/lastactive - unix-timestamp: DateTime понимает его
            // только с префиксом '@', и всегда в UTC -> переводим в таймзону приложения.
            $date = is_numeric($dateTime)
                ? new DateTime('@' . $dateTime)
                : new DateTime((string)$dateTime);
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            return $date->format($format);
        } catch (Exception) {
            return 'Invalid date';
        }
    }

    private static function url(string $base, array $params): string
    {
        $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
        if ($params === []) {
            return $base;
        }
        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params);
    }

    private static function e(string $s): string
    {
        return htmlspecialchars_uni($s);
    }

    private function postKey(): string
    {
        if ($this->postKey === null) {
            global $mybb;
            $this->postKey = function_exists('generate_post_check')
                ? (string)generate_post_check()
                : (string)($mybb->post_code ?? '');
        }
        return $this->postKey;
    }

    /* ------------------------------------------------------------------ */
    /*  Data                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{registered: array<int, array>, logins: array<int, array>}
     */
    public function searchIp(string $ip, string $rawIp): array
    {
        $out = ['registered' => [], 'logins' => []];

        $ipBinary = inet_pton($ip);
        if ($ipBinary === false) {
            return $out;
        }

        // Регистрация (users.regip - varbinary(16))
        $q1 = $this->db->sql_query_prepared(
            "SELECT u.*, g.namestyle
               FROM users u
          LEFT JOIN usergroups g ON (u.usergroup = g.gid)
              WHERE u.regip = ?
           ORDER BY u.added DESC",
            [$ipBinary]
        );
        if ($q1) {
            while ($row = $this->db->fetch_array($q1)) {
                $out['registered'][] = $row;
            }
        }

        // История входов (login_log.ip - varchar(45), текст).
        // Ищем и по канонической форме, и по введённой - на случай если
        // в логе IP записан не в канонической форме.
        // INNER JOIN: записи без реального аккаунта (uid не найден) не нужны.
        $q2 = $this->db->sql_query_prepared(
            "SELECT DISTINCT u.*, g.namestyle
               FROM login_log i
         INNER JOIN users u ON (i.uid = u.id)
          LEFT JOIN usergroups g ON (u.usergroup = g.gid)
              WHERE i.ip IN (?, ?)
           ORDER BY u.lastactive DESC",
            [$ip, $rawIp]
        );
        if ($q2) {
            while ($row = $this->db->fetch_array($q2)) {
                if ($row['username'] !== null) {
                    $out['logins'][] = $row;
                }
            }
        }

        return $out;
    }

    /**
     * POST: сброс passkey. CSRF + write_log + PRG, всегда заканчивается редиректом.
     */
    public function handlePasskeyReset(): never
    {
        $uid    = (int)($_POST['uid'] ?? 0);
        $backIp = $this->normalizeIp($this->getIpAddress());
        $status = 'fail';

        if ($uid > 0 && verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
            $q   = $this->db->sql_query_prepared('SELECT id, username FROM users WHERE id = ? LIMIT 1', [$uid]);
            $row = $q ? $this->db->fetch_array($q) : null;

            if ($row) {
                $newKey = bin2hex(random_bytes(16));
                $this->db->sql_query_prepared('UPDATE users SET passkey = ? WHERE id = ?', [$newKey, $uid]);

                $staff = (string)($GLOBALS['CURUSER']['username'] ?? $GLOBALS['mybb']->user['username'] ?? 'unknown');
                write_log(sprintf(
                    'Passkey reset for %s (ID %d) via IP Search by %s',
                    (string)$row['username'],
                    $uid,
                    $staff
                ));
                $status = 'ok';
            }
        }

        header('Location: ' . self::url($this->selfUrl, ['ip' => $backIp, 'reset' => $status]));
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  Rendering                                                         */
    /* ------------------------------------------------------------------ */

    public function renderPage(): string
    {
        $ip   = $this->getIpAddress();
        $html = $this->renderHeader();
        $html .= $this->renderFlash();

        if ($ip === '') {
            return $html . $this->renderSearchCard('', true);
        }

        if (!$this->validateIp($ip)) {
            $html .= $this->renderAlert('danger', 'fa-triangle-exclamation', 'Invalid IP address',
                'Please enter a valid IPv4 or IPv6 address.');
            return $html . $this->renderSearchCard($ip, true);
        }

        $norm    = $this->normalizeIp($ip);
        $results = $this->searchIp($norm, $ip);

        $html .= $this->renderSearchCard($norm, false);
        $html .= $this->renderKpis($results, $norm);

        if ($results['registered'] === [] && $results['logins'] === []) {
            $html .= $this->renderEmptyState($norm);
        } else {
            $html .= $this->renderUserTable(
                $results['registered'], 'Registered from this IP', 'fa-user-plus', 'primary', $norm,
                'Nobody registered from this IP.'
            );
            $html .= $this->renderUserTable(
                $results['logins'], 'Logged in from this IP', 'fa-right-to-bracket', 'success', $norm,
                'No logins from this IP in the log.'
            );
        }

        return $html . $this->renderActionBar($norm);
    }

    private function renderHeader(): string
    {
        $version = self::e(IPS_VERSION);
        return <<<HTML
            <div class="ips-card ips-header">
                <div class="ips-header__icon"><i class="fa-solid fa-magnifying-glass-location"></i></div>
                <div class="ips-header__text">
                    <h1 class="ips-header__title">IP Address Search</h1>
                    <div class="ips-header__sub">Find accounts by registration IP and login history &middot; IPv4 &amp; IPv6</div>
                </div>
                <span class="ips-pill ips-tone-secondary"><i class="fa-solid fa-code-branch"></i> {$version}</span>
            </div>
        HTML;
    }

    private function renderFlash(): string
    {
        return match ($_GET['reset'] ?? '') {
            'ok'   => $this->renderAlert('success', 'fa-circle-check', 'Passkey reset',
                        'A new passkey was generated. The user has to re-download their .torrent files.'),
            'fail' => $this->renderAlert('danger', 'fa-triangle-exclamation', 'Passkey was not reset',
                        'Security token expired or the user no longer exists. Please try again.'),
            default => '',
        };
    }

    private function renderAlert(string $tone, string $icon, string $title, string $text): string
    {
        $title = self::e($title);
        $text  = self::e($text);
        return <<<HTML
            <div class="ips-alert ips-tone-{$tone}" role="alert">
                <i class="fa-solid {$icon} ips-alert__icon"></i>
                <div><strong>{$title}</strong><div>{$text}</div></div>
            </div>
        HTML;
    }

    private function renderSearchCard(string $currentIp, bool $withExamples): string
    {
        $action   = self::e($this->scriptName);
        $value    = self::e($currentIp);
        $examples = '';

        if ($withExamples) {
            $examples = <<<HTML
                <div class="ips-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    Registration IP (users) and login history (login_log) are searched. Try:
                    <button type="button" class="ips-chip ips-tone-primary" data-ip="192.168.1.1"><i class="fa-solid fa-4"></i> 192.168.1.1</button>
                    <button type="button" class="ips-chip ips-tone-info" data-ip="2001:db8::1"><i class="fa-solid fa-6"></i> 2001:db8::1</button>
                </div>
            HTML;
        }

        return <<<HTML
            <div class="ips-card ips-search-card">
                <form method="get" action="{$action}" class="ips-search" id="ip-search-form" novalidate>
                    <input type="hidden" name="act" value="ipsearch">
                    <label class="ips-search__field" for="ip-address">
                        <i class="fa-solid fa-network-wired"></i>
                        <input type="text" id="ip-address" name="ip" value="{$value}"
                               placeholder="Enter IPv4 or IPv6 address" autocomplete="off" spellcheck="false" required>
                    </label>
                    <button type="submit" class="ips-btn ips-btn--primary">
                        <i class="fa-solid fa-magnifying-glass"></i><span>Search</span>
                    </button>
                </form>
                {$examples}
            </div>
        HTML;
    }

    private function renderKpis(array $results, string $ip): string
    {
        $reg    = count($results['registered']);
        $logins = count($results['logins']);
        $ids    = array_map(static fn($u) => (int)$u['id'], array_merge($results['registered'], $results['logins']));
        $unique = count(array_unique($ids));
        $type   = self::e($this->ipType($ip));
        $scope  = self::e($this->ipScope($ip));

        return <<<HTML
            <div class="ips-kpis">
                <div class="ips-card ips-kpi">
                    <div class="ips-kpi__icon ips-tone-primary"><i class="fa-solid fa-user-plus"></i></div>
                    <div><div class="ips-kpi__value">{$reg}</div><div class="ips-kpi__label">Registered from IP</div></div>
                </div>
                <div class="ips-card ips-kpi">
                    <div class="ips-kpi__icon ips-tone-success"><i class="fa-solid fa-right-to-bracket"></i></div>
                    <div><div class="ips-kpi__value">{$logins}</div><div class="ips-kpi__label">Logged in from IP</div></div>
                </div>
                <div class="ips-card ips-kpi">
                    <div class="ips-kpi__icon ips-tone-warning"><i class="fa-solid fa-users"></i></div>
                    <div><div class="ips-kpi__value">{$unique}</div><div class="ips-kpi__label">Unique accounts</div></div>
                </div>
                <div class="ips-card ips-kpi">
                    <div class="ips-kpi__icon ips-tone-info"><i class="fa-solid fa-globe"></i></div>
                    <div><div class="ips-kpi__value">{$type}</div><div class="ips-kpi__label">{$scope}</div></div>
                </div>
            </div>
        HTML;
    }

    private function renderEmptyState(string $ip): string
    {
        $ip = self::e($ip);
        return <<<HTML
            <div class="ips-card ips-empty">
                <div class="ips-empty__icon"><i class="fa-solid fa-user-slash"></i></div>
                <h3>No accounts found</h3>
                <p>Nobody registered or logged in from <code>{$ip}</code>.</p>
            </div>
        HTML;
    }

    private function renderUserTable(array $rows, string $title, string $icon, string $tone, string $searchIp, string $emptyText): string
    {
        global $dateformat, $timeformat;

        $count     = count($rows);
        $title     = self::e($title);
        $emptyText = self::e($emptyText);
        $selfAttr  = self::e($this->selfUrl);
        $ipAttr    = self::e($searchIp);
        $postKey   = self::e($this->postKey());
        $dtFormat  = trim("{$dateformat} {$timeformat}") ?: 'Y-m-d H:i';
        $body      = '';

        if ($rows === []) {
            $body = <<<HTML
                <tr><td colspan="9" class="ips-table__empty"><i class="fa-solid fa-circle-minus"></i> {$emptyText}</td></tr>
            HTML;
        } else {
            require_once INC_PATH . '/functions_ratio.php';

            foreach ($rows as $user) {
                $id           = (int)$user['id'];
                $safeName     = self::e((string)$user['username']);
                $usernameHtml = format_name($safeName, (string)($user['usergroup'] ?? ''));
                $profileUrl   = self::e("{$this->baseUrl}/userdetails.php?id={$id}");

                $emailRaw = (string)($user['email'] ?? '');
                $email    = self::e($emailRaw);
                $emailCell = $emailRaw !== ''
                    ? "<a class=\"ips-muted-link\" href=\"mailto:{$email}\" title=\"{$email}\">{$email}</a>"
                    : '<span class="ips-dim">—</span>';

                // Последний известный IP пользователя + быстрый поиск по нему
                $lastIpRaw = $this->userIpToText($user);
                if ($lastIpRaw === '') {
                    $ipCell = '<span class="ips-dim">—</span>';
                } else {
                    $lastIp   = self::e($lastIpRaw);
                    $typeTone = $this->ipType($lastIpRaw) === 'IPv6' ? 'info' : 'primary';
                    $typeText = self::e($this->ipType($lastIpRaw));
                    $isMatch  = $this->normalizeIp($lastIpRaw) === $searchIp;
                    $match    = $isMatch
                        ? '<span class="ips-badge ips-tone-success" title="Same as searched IP"><i class="fa-solid fa-equals"></i></span>'
                        : '';
                    $ipLink   = $this->validateIp($lastIpRaw) && !$isMatch
                        ? '<a class="ips-icon-btn" href="' . self::e(self::url($this->selfUrl, ['ip' => $lastIpRaw])) . '" title="Search this IP"><i class="fa-solid fa-magnifying-glass"></i></a>'
                        : '';
                    $ipCell = <<<HTML
                        <div class="ips-inline">
                            <span class="ips-mono ips-ellipsis" title="{$lastIp}">{$lastIp}</span>
                            <span class="ips-badge ips-tone-{$typeTone}">{$typeText}</span>
                            {$match}{$ipLink}
                        </div>
                    HTML;
                }

                $passkeyRaw   = (string)($user['passkey'] ?? '');
                $passkey      = self::e($passkeyRaw);
                $passkeyShort = $passkeyRaw !== '' ? self::e(substr($passkeyRaw, 0, 8)) . '…' : '—';
                $copyBtn      = $passkeyRaw !== ''
                    ? "<button type=\"button\" class=\"ips-icon-btn\" data-copy=\"{$passkey}\" title=\"Copy passkey\"><i class=\"fa-solid fa-copy\"></i></button>"
                    : '';

                $lastSeen   = self::e($this->formatDateTime($user['lastactive'] ?? null, $dtFormat));
                $joinDate   = self::e($this->formatDateTime($user['added'] ?? null, $dtFormat));
                $uploaded   = mksize($user['uploaded'] ?? 0);
                $downloaded = mksize($user['downloaded'] ?? 0);
                $ratio      = get_user_ratio($user['uploaded'] ?? 0, $user['downloaded'] ?? 0);
                $upVal      = self::e((string)($user['uploaded'] ?? 0));
                $downVal    = self::e((string)($user['downloaded'] ?? 0));

                $body .= <<<HTML
                    <tr class="user-row">
                        <td>
                            <div class="ips-inline">
                                <a href="{$profileUrl}" class="ips-user-link">{$usernameHtml}</a>
                                <span class="ips-dim ips-small">#{$id}</span>
                            </div>
                        </td>
                        <td class="ips-ellipsis-cell">{$emailCell}</td>
                        <td>{$ipCell}</td>
                        <td>
                            <div class="ips-inline">
                                <span class="ips-mono ips-dim" title="{$passkey}">{$passkeyShort}</span>
                                {$copyBtn}
                                <form method="post" action="{$selfAttr}" class="ips-reset-form" data-username="{$safeName}">
                                    <input type="hidden" name="act" value="ipsearch">
                                    <input type="hidden" name="do" value="resetpasskey">
                                    <input type="hidden" name="uid" value="{$id}">
                                    <input type="hidden" name="ip" value="{$ipAttr}">
                                    <input type="hidden" name="my_post_key" value="{$postKey}">
                                    <button type="submit" class="ips-icon-btn ips-icon-btn--danger" title="Reset passkey"><i class="fa-solid fa-rotate"></i></button>
                                </form>
                            </div>
                        </td>
                        <td class="ips-nowrap"><i class="fa-regular fa-clock ips-dim"></i> {$lastSeen}</td>
                        <td class="ips-nowrap"><i class="fa-regular fa-calendar ips-dim"></i> {$joinDate}</td>
                        <td class="ips-nowrap ips-up" data-value="{$upVal}"><i class="fa-solid fa-arrow-up"></i> {$uploaded}</td>
                        <td class="ips-nowrap ips-down" data-value="{$downVal}"><i class="fa-solid fa-arrow-down"></i> {$downloaded}</td>
                        <td class="ips-nowrap ips-ratio">{$ratio}</td>
                    </tr>
                HTML;
            }
        }

        return <<<HTML
            <div class="ips-card ips-section">
                <div class="ips-section__head">
                    <div class="ips-section__icon ips-tone-{$tone}"><i class="fa-solid {$icon}"></i></div>
                    <h3 class="ips-section__title">{$title}</h3>
                    <span class="ips-pill ips-tone-{$tone}">{$count}</span>
                </div>
                <div class="ips-table-wrap">
                    <table class="ips-table">
                        <thead>
                            <tr>
                                <th><i class="fa-solid fa-user"></i> Username</th>
                                <th><i class="fa-solid fa-envelope"></i> Email</th>
                                <th><i class="fa-solid fa-location-dot"></i> Last IP</th>
                                <th><i class="fa-solid fa-key"></i> Passkey</th>
                                <th><i class="fa-solid fa-clock"></i> Last seen</th>
                                <th><i class="fa-solid fa-calendar-plus"></i> Registered</th>
                                <th><i class="fa-solid fa-cloud-arrow-up"></i> Uploaded</th>
                                <th><i class="fa-solid fa-cloud-arrow-down"></i> Downloaded</th>
                                <th><i class="fa-solid fa-scale-balanced"></i> Ratio</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$body}
                        </tbody>
                    </table>
                </div>
            </div>
        HTML;
    }

    private function renderActionBar(string $ip): string
    {
        $newUrl  = self::e($this->selfUrl);
        $ipAttr  = self::e($ip);
        $infoUrl = self::e('https://ipinfo.io/' . rawurlencode($ip));

        return <<<HTML
            <div class="ips-actionbar">
                <div class="ips-actionbar__ip">
                    <i class="fa-solid fa-crosshairs"></i>
                    <span class="ips-mono">{$ipAttr}</span>
                </div>
                <div class="ips-actionbar__buttons">
                    <button type="button" class="ips-btn ips-btn--soft" data-copy="{$ipAttr}">
                        <i class="fa-solid fa-copy"></i><span>Copy IP</span>
                    </button>
                    <a href="{$infoUrl}" target="_blank" rel="noopener noreferrer" class="ips-btn ips-btn--soft">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i><span>ipinfo.io</span>
                    </a>
                    <a href="{$newUrl}" class="ips-btn ips-btn--primary">
                        <i class="fa-solid fa-magnifying-glass-plus"></i><span>New search</span>
                    </a>
                </div>
            </div>
        HTML;
    }
}

// Main execution
function main(): void
{
    global $db, $BASEURL, $_this_script_;

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $selfUrl    = html_entity_decode((string)($_this_script_ ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($selfUrl === '') {
        $selfUrl = $scriptName . '?act=ipsearch';
    }

    $manager = new IPSearchManager($db, (string)$BASEURL, $selfUrl, $scriptName);

    // Изменения - только POST, до stdhead(), всегда PRG-редирект
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['do'] ?? '') === 'resetpasskey') {
        $manager->handlePasskeyReset();
    }

    stdhead('IP Search');
    ?>
    <link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/ipsearch.css?ver=1">
    <script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>

    <div class="ips-page">
    <?php
    try {
        echo $manager->renderPage();
    } catch (Throwable $e) {
        echo '<div class="ips-alert ips-tone-danger" role="alert">'
            . '<i class="fa-solid fa-triangle-exclamation ips-alert__icon"></i>'
            . '<div><strong>Error</strong><div>' . htmlspecialchars_uni($e->getMessage()) . '</div></div>'
            . '</div>';
    }
    ?>
    </div>

    <script src="<?= $BASEURL ?>/admin/scripts/ipsearch.js?ver=1"></script>
    <?php
    stdfoot();
}

main();