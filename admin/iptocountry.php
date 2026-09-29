<?php
declare(strict_types=1);

if (!defined('IN_ADMIN_PANEL')) {
    exit('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

const ITC_VERSION   = '1.0';
const ITC_ASSET_VER = 1;

/**
 * Real client IP behind the Nginx reverse proxy.
 * Takes the first public address from X-Real-IP / X-Forwarded-For, otherwise REMOTE_ADDR.
 */
function itc_client_ip(): string
{
    $candidates = [];
    if (!empty($_SERVER['HTTP_X_REAL_IP']) && is_string($_SERVER['HTTP_X_REAL_IP'])) {
        $candidates[] = trim($_SERVER['HTTP_X_REAL_IP']);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
            $candidates[] = trim($part);
        }
    }
    foreach ($candidates as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $candidate;
        }
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Geo lookup via ipwho.is (HTTPS, JSON, no key).
 * The old ip-to-country.webhosting.info service has been offline for years.
 *
 * @return array{ok: bool, data?: array, error?: string}
 */
function itc_lookup(string $ip): array
{
    $url  = 'https://ipwho.is/' . rawurlencode($ip) . '?lang=en';
    $ua   = 'ArtCore-Gangsta-Admin/' . ITC_VERSION;
    $body = false;
    $err  = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => $ua,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        // curl_close() is a no-op since PHP 8.0 and deprecated in 8.5 — the handle is freed automatically.
    } else {
        $ctx  = stream_context_create(['http' => ['timeout' => 6, 'user_agent' => $ua, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
    }

    if (!is_string($body) || $body === '') {
        return ['ok' => false, 'error' => 'The lookup service did not respond' . ($err !== '' ? " ({$err})" : '') . '. Try again in a minute.'];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'The lookup service returned an unreadable response. Try again in a minute.'];
    }
    if (empty($data['success'])) {
        return ['ok' => false, 'error' => 'Lookup failed: ' . (string)($data['message'] ?? 'unknown reason') . '.'];
    }

    return ['ok' => true, 'data' => $data];
}

/* ---------- input ---------- */

$in = static function (string $key): string {
    foreach ([$_GET, $_POST] as $src) {
        if (isset($src[$key]) && is_string($src[$key])) {
            return trim($src[$key]);
        }
    }
    return '';
};
$e = static fn(mixed $v): string => htmlspecialchars_uni((string)$v);

$do    = (int)($in('do') ?: 1);
$myIp  = itc_client_ip();
$rawIp = $in('ip_address');
$ip    = $rawIp !== '' ? $rawIp : $myIp;

$result = null;
$error  = '';
$notice = '';

if ($do === 2) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $error = 'That is not a valid IPv4 or IPv6 address. Check it for typos and extra characters.';
    } elseif (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $notice = 'This is a private or reserved address (local network, loopback and so on), so it has no country.';
    } else {
        $lookup = itc_lookup($ip);
        if ($lookup['ok']) {
            $result = $lookup['data'];
        } else {
            $error = $lookup['error'];
        }
    }
}

/* ---------- prepare result ---------- */

if ($result !== null) {
    $country   = (string)($result['country'] ?? 'Unknown country');
    $cc        = strtoupper((string)($result['country_code'] ?? ''));
    $continent = (string)($result['continent'] ?? '');
    $ipType    = (string)($result['type'] ?? '');
    $isEu      = !empty($result['is_eu']);

    $flag = (string)($result['flag']['img'] ?? '');
    if (!preg_match('~^https://[a-z0-9.-]+/[\w./-]+\.(?:svg|png)$~i', $flag)) {
        $flag = '';
    }

    $city   = (string)($result['city'] ?? '');
    $region = (string)($result['region'] ?? '');
    $postal = (string)($result['postal'] ?? '');

    $conn   = is_array($result['connection'] ?? null) ? $result['connection'] : [];
    $isp    = (string)($conn['isp'] ?? '');
    $org    = (string)($conn['org'] ?? '');
    $asn    = (int)($conn['asn'] ?? 0);
    $domain = (string)($conn['domain'] ?? '');
    $providerMeta = implode(' · ', array_filter([$asn > 0 ? 'AS' . $asn : '', $domain ?: ($org !== $isp ? $org : '')]));

    $tz        = is_array($result['timezone'] ?? null) ? $result['timezone'] : [];
    $tzId      = (string)($tz['id'] ?? '');
    $tzUtc     = (string)($tz['utc'] ?? '');
    $localTime = '';
    if (!empty($tz['current_time']) && is_string($tz['current_time'])) {
        try {
            $localTime = (new DateTimeImmutable($tz['current_time']))->format('H:i, j M');
        } catch (Exception) {
            $localTime = '';
        }
    }

    $lat = isset($result['latitude'])  ? (float)$result['latitude']  : null;
    $lon = isset($result['longitude']) ? (float)$result['longitude'] : null;

    $ipUrl    = rawurlencode($ip);
    $mapUrl   = ($lat !== null && $lon !== null)
        ? "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lon}#map=10/{$lat}/{$lon}"
        : '';
    $whoisUrl = "https://bgp.he.net/ip/{$ipUrl}";
    $abuseUrl = "https://www.abuseipdb.com/check/{$ipUrl}";
}

/* ---------- output ---------- */

stdhead('IP to Country');
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/iptocountry.css?ver=<?= ITC_ASSET_VER ?>">

<div class="itc-page container-md"
    <?php if ($result !== null): ?>
     data-looked-up="<?= $e($ip) ?>" data-country="<?= $e($country) ?>" data-cc="<?= $e($cc) ?>"
    <?php endif; ?>>

    <!-- Header -->
    <div class="itc-head">
        <div class="itc-head-icon"><i class="fa-solid fa-earth-europe"></i></div>
        <div>
            <h1 class="itc-title">IP to Country</h1>
            <p class="itc-sub">Find where an IP address is registered: country, city, provider and timezone.</p>
        </div>
    </div>

    <!-- Search -->
    <form id="itc-form" class="itc-search" method="get" action="<?= $e($_SERVER['SCRIPT_NAME']) ?>" autocomplete="off">
        <input type="hidden" name="act" value="iptocountry">
        <input type="hidden" name="do" value="2">

        <label for="itc-ip" class="itc-label">IP address</label>
        <div class="itc-search-row">
            <div class="itc-field">
                <i class="fa-solid fa-network-wired" aria-hidden="true"></i>
                <input id="itc-ip" name="ip_address" type="text" inputmode="text" spellcheck="false"
                       placeholder="e.g. 85.14.0.1 or 2a01:4f8::1" value="<?= $e($ip) ?>" required>
            </div>
            <button type="submit" id="itc-submit" class="itc-btn itc-btn-primary">
                <i class="fa-solid fa-magnifying-glass"></i><span>Find country</span>
            </button>
            <button type="button" id="itc-myip" class="itc-btn itc-btn-ghost" data-ip="<?= $e($myIp) ?>"
                    title="Fill in your own IP: <?= $e($myIp) ?>">
                <i class="fa-solid fa-location-crosshairs"></i><span>My IP</span>
            </button>
        </div>

        <div id="itc-recent" class="itc-recent" hidden>
            <span class="itc-recent-label"><i class="fa-solid fa-clock-rotate-left"></i> Recent</span>
            <div class="itc-recent-list"></div>
            <button type="button" class="itc-recent-clear" title="Clear recent lookups">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </form>

    <?php if ($error !== ''): ?>
        <div class="itc-msg itc-msg-danger" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i><span><?= $e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($notice !== ''): ?>
        <div class="itc-msg itc-msg-warning" role="status">
            <i class="fa-solid fa-house-lock"></i>
            <span><b><?= $e($ip) ?></b> — <?= $e($notice) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <!-- Result -->
        <section class="itc-result"<?= $flag !== '' ? ' style="--itc-flag:url(\'' . $e($flag) . '\')"' : '' ?>>
            <div class="itc-hero">
                <?php if ($flag !== ''): ?>
                    <img class="itc-flag" src="<?= $e($flag) ?>" alt="Flag of <?= $e($country) ?>" width="96" height="64">
                <?php else: ?>
                    <div class="itc-flag itc-flag-empty"><i class="fa-solid fa-flag"></i></div>
                <?php endif; ?>

                <div class="itc-hero-text">
                    <h2 class="itc-country"><?= $e($country) ?></h2>
                    <div class="itc-ip-line">
                        <code class="itc-ip"><?= $e($ip) ?></code>
                        <button type="button" class="itc-copy" data-copy="<?= $e($ip) ?>" title="Copy IP">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                    <div class="itc-badges">
                        <?php if ($cc !== ''): ?><span class="itc-badge"><?= $e($cc) ?></span><?php endif; ?>
                        <?php if ($ipType !== ''): ?><span class="itc-badge"><?= $e($ipType) ?></span><?php endif; ?>
                        <?php if ($continent !== ''): ?>
                            <span class="itc-badge"><i class="fa-solid fa-globe"></i> <?= $e($continent) ?></span>
                        <?php endif; ?>
                        <?php if ($isEu): ?>
                            <span class="itc-badge itc-badge-eu"><i class="fa-solid fa-star"></i> EU</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="itc-tiles">
                <div class="itc-tile">
                    <div class="itc-tile-icon itc-c-blue"><i class="fa-solid fa-city"></i></div>
                    <div class="itc-tile-body">
                        <div class="itc-tile-label">City</div>
                        <div class="itc-tile-value"><?= $city !== '' ? $e($city) : '—' ?></div>
                        <div class="itc-tile-meta">
                            <?= $e(implode(', ', array_filter([$region, $postal]))) ?: '&nbsp;' ?>
                        </div>
                    </div>
                </div>

                <div class="itc-tile">
                    <div class="itc-tile-icon itc-c-purple"><i class="fa-solid fa-server"></i></div>
                    <div class="itc-tile-body">
                        <div class="itc-tile-label">Provider</div>
                        <div class="itc-tile-value" title="<?= $e($isp) ?>"><?= $isp !== '' ? $e($isp) : '—' ?></div>
                        <div class="itc-tile-meta"><?= $providerMeta !== '' ? $e($providerMeta) : '&nbsp;' ?></div>
                    </div>
                </div>

                <div class="itc-tile">
                    <div class="itc-tile-icon itc-c-amber"><i class="fa-solid fa-clock"></i></div>
                    <div class="itc-tile-body">
                        <div class="itc-tile-label">Local time</div>
                        <div class="itc-tile-value"><?= $localTime !== '' ? $e($localTime) : '—' ?></div>
                        <div class="itc-tile-meta">
                            <?= $e($tzId) ?><?= $tzUtc !== '' ? ' (UTC' . $e($tzUtc) . ')' : '' ?>
                        </div>
                    </div>
                </div>

                <div class="itc-tile">
                    <div class="itc-tile-icon itc-c-green"><i class="fa-solid fa-map-location-dot"></i></div>
                    <div class="itc-tile-body">
                        <div class="itc-tile-label">Coordinates</div>
                        <div class="itc-tile-value">
                            <?= ($lat !== null && $lon !== null) ? number_format($lat, 4) . ', ' . number_format($lon, 4) : '—' ?>
                        </div>
                        <div class="itc-tile-meta">Approximate</div>
                    </div>
                </div>
            </div>

            <p class="itc-hint">
                <i class="fa-solid fa-circle-info"></i>
                This is where the address is registered, not necessarily where the user is.
                VPNs, proxies and mobile carriers often show a different city or country.
            </p>

            <div class="itc-actions">
                <?php if ($mapUrl !== ''): ?>
                    <a class="itc-btn itc-btn-soft" href="<?= $e($mapUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fa-solid fa-map"></i><span>Open map</span>
                    </a>
                <?php endif; ?>
                <a class="itc-btn itc-btn-soft" href="<?= $e($whoisUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-diagram-project"></i><span>ASN / WHOIS</span>
                </a>
                <a class="itc-btn itc-btn-soft" href="<?= $e($abuseUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-shield-halved"></i><span>Abuse check</span>
                </a>
            </div>
        </section>
    <?php elseif ($do !== 2): ?>
        <div class="itc-empty">
            <i class="fa-solid fa-satellite-dish"></i>
            <p>Enter an IP address from a user profile, log or peer list, then press <b>Find country</b>.</p>
        </div>
    <?php endif; ?>
</div>

<script src="<?= $BASEURL ?>/admin/scripts/iptocountry.js?ver=<?= ITC_ASSET_VER ?>" defer></script>
<?php
stdfoot();