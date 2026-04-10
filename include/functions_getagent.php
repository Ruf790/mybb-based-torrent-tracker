<?php

declare(strict_types=1);

// ============================================================
//  PEER-ID STYLE HELPERS
// ============================================================

function parse_azureus_peer_id(string $peer_id): ?array
{
    if (preg_match('/^-([A-Za-z]{2})(\d{4})-/', $peer_id, $m)) {
        $raw   = $m[2];
        $major = (int)$raw[0];
        $minor = (int)$raw[1];
        $patch = (int)substr($raw, 2);
        return [
            'code'    => $m[1],
            'version' => "{$major}.{$minor}.{$patch}",
        ];
    }
    return null;
}

function azureus_code_to_client(string $code): array
{
    static $map = [
        'qB' => ['name' => 'qBittorrent',          'icon' => '⚡',  'category' => 'modern'],
        'UT' => ['name' => 'µTorrent',              'icon' => '⛏️', 'category' => 'classic'],
        'TR' => ['name' => 'Transmission',          'icon' => '🔄',  'category' => 'lightweight'],
        'DE' => ['name' => 'Deluge',                'icon' => '🌊',  'category' => 'modular'],
        'AZ' => ['name' => 'Vuze',                  'icon' => '💎',  'category' => 'feature-rich'],
        'BC' => ['name' => 'BitComet',              'icon' => '☄️',  'category' => 'windows'],
        'TX' => ['name' => 'Tixati',                'icon' => '🌐',  'category' => 'privacy'],
        'BL' => ['name' => 'BiglyBT',               'icon' => '🦋',  'category' => 'opensource'],
        'BT' => ['name' => 'BitTorrent',            'icon' => '🧲',  'category' => 'original'],
        'RT' => ['name' => 'rTorrent',              'icon' => '🐧',  'category' => 'terminal'],
        'lt' => ['name' => 'libtorrent',            'icon' => '🐧',  'category' => 'terminal'],
        'LT' => ['name' => 'libtorrent',            'icon' => '🐧',  'category' => 'terminal'],
        'A2' => ['name' => 'Aria2',                 'icon' => '🛠️', 'category' => 'lightweight'],
        'KT' => ['name' => 'KTorrent',              'icon' => '🐧',  'category' => 'linux'],
        'WD' => ['name' => 'WebTorrent',            'icon' => '🌐',  'category' => 'webbased'],
        'RM' => ['name' => 'Ratio Master',          'icon' => '⚠️',  'category' => 'cheat'],
        'BS' => ['name' => 'BitSpirit',             'icon' => '👻',  'category' => 'windows'],
        'XL' => ['name' => 'Xunlei',               'icon' => '⚡',  'category' => 'chinese'],
        'FD' => ['name' => 'Free Download Manager', 'icon' => '📥',  'category' => 'download'],
        'FL' => ['name' => 'Flud',                  'icon' => '💧',  'category' => 'android'],
        'TT' => ['name' => 'tTorrent',              'icon' => '📱',  'category' => 'android'],
        'LR' => ['name' => 'LibreTorrent',          'icon' => '📚',  'category' => 'android'],
        'PO' => ['name' => 'Popcorn Time',          'icon' => '🍿',  'category' => 'streaming'],
        'SD' => ['name' => 'Thunder',               'icon' => '⚡',  'category' => 'chinese'],
    ];
    return $map[$code] ?? ['name' => "Unknown ({$code})", 'icon' => '❓', 'category' => 'unknown'];
}

// ============================================================
//  CLIENT DATABASE
// ============================================================

function get_client_db(): array
{
    static $db = null;
    if ($db !== null) return $db;

    $db = [
        'qbittorrent'   => ['patterns' => ['/qBittorrent/i', '/qB\/\d+/'],          'name' => 'qBittorrent',          'icon' => '⚡',  'category' => 'modern'],
        'utorrent'      => ['patterns' => ['/[µuÂµ]Torrent/iu', '/UT\d/'],          'name' => 'µTorrent',             'icon' => '⛏️', 'category' => 'classic'],
        'transmission'  => ['patterns' => ['/Transmission/i'],                       'name' => 'Transmission',         'icon' => '🔄',  'category' => 'lightweight'],
        'deluge'        => ['patterns' => ['/Deluge/i'],                             'name' => 'Deluge',               'icon' => '🌊',  'category' => 'modular'],
        'vuze'          => ['patterns' => ['/Vuze/i', '/Azureus/i'],                 'name' => 'Vuze',                 'icon' => '💎',  'category' => 'feature-rich'],
        'bitcomet'      => ['patterns' => ['/BitComet/i'],                           'name' => 'BitComet',             'icon' => '☄️',  'category' => 'windows'],
        'tixati'        => ['patterns' => ['/Tixati/i'],                             'name' => 'Tixati',               'icon' => '🌐',  'category' => 'privacy'],
        'biglybt'       => ['patterns' => ['/BiglyBT/i'],                            'name' => 'BiglyBT',              'icon' => '🦋',  'category' => 'opensource'],
        'bittorrent'    => ['patterns' => ['/BitTorrent/i'],                         'name' => 'BitTorrent',           'icon' => '🧲',  'category' => 'original'],
        'rtorrent'      => ['patterns' => ['/rTorrent/i', '/libtorrent/i'],          'name' => 'rTorrent',             'icon' => '🐧',  'category' => 'terminal'],
        'aria2'         => ['patterns' => ['/aria2/i'],                              'name' => 'Aria2',                'icon' => '🛠️', 'category' => 'lightweight'],
        'ktorrent'      => ['patterns' => ['/KTorrent/i'],                           'name' => 'KTorrent',             'icon' => '🐧',  'category' => 'linux'],
        'frostwire'     => ['patterns' => ['/FrostWire/i'],                          'name' => 'FrostWire',            'icon' => '❄️',  'category' => 'multimedia'],
        'picotorrent'   => ['patterns' => ['/PicoTorrent/i'],                        'name' => 'PicoTorrent',          'icon' => '🔬',  'category' => 'lightweight'],
        'tribler'       => ['patterns' => ['/Tribler/i'],                            'name' => 'Tribler',              'icon' => '🕸️', 'category' => 'privacy'],
        'shareaza'      => ['patterns' => ['/Shareaza/i', '/Raza/i'],                'name' => 'Shareaza',             'icon' => '🦅',  'category' => 'p2p'],
        'bitspirit'     => ['patterns' => ['/BitSpirit/i'],                          'name' => 'BitSpirit',            'icon' => '👻',  'category' => 'windows'],
        'bitlord'       => ['patterns' => ['/BitLord/i'],                            'name' => 'BitLord',              'icon' => '👑',  'category' => 'windows'],
        'xunlei'        => ['patterns' => ['/Xunlei/i', '/Thunder/i'],               'name' => 'Xunlei',               'icon' => '⚡',  'category' => 'chinese'],
        'fdm'           => ['patterns' => ['/Free Download Manager/i', '/\bFDM\b/'], 'name' => 'Free Download Manager','icon' => '📥',  'category' => 'download'],
        'mldonkey'      => ['patterns' => ['/MLDonkey/i'],                           'name' => 'MLDonkey',             'icon' => '🐴',  'category' => 'network'],
        'bittornado'    => ['patterns' => ['/BitTornado/i'],                         'name' => 'BitTornado',           'icon' => '🌪️', 'category' => 'classic'],
        'bitrocket'     => ['patterns' => ['/BitRocket/i'],                          'name' => 'BitRocket',            'icon' => '🚀',  'category' => 'mac'],
        'xtorrent'      => ['patterns' => ['/XTorrent/i'],                           'name' => 'XTorrent',             'icon' => '🦋',  'category' => 'mac'],
        'tomatotorrent' => ['patterns' => ['/TomatoTorrent/i'],                      'name' => 'TomatoTorrent',        'icon' => '🍅',  'category' => 'mac'],
        // Android
        'flud'          => ['patterns' => ['/Flud/i'],                               'name' => 'Flud',                 'icon' => '💧',  'category' => 'android'],
        'ttorrent'      => ['patterns' => ['/tTorrent/i'],                           'name' => 'tTorrent',             'icon' => '📱',  'category' => 'android'],
        'libretorrent'  => ['patterns' => ['/LibreTorrent/i'],                       'name' => 'LibreTorrent',         'icon' => '📚',  'category' => 'android'],
        'atorrent'      => ['patterns' => ['/aTorrent/i'],                           'name' => 'aTorrent',             'icon' => '📲',  'category' => 'android'],
        // iOS
        'itorrent'      => ['patterns' => ['/iTorrent/i'],                           'name' => 'iTorrent',             'icon' => '🍎',  'category' => 'ios'],
        'foxtorrent'    => ['patterns' => ['/FoxTorrent/i'],                         'name' => 'FoxTorrent',           'icon' => '🦊',  'category' => 'ios'],
        'swiftytorrent' => ['patterns' => ['/SwiftyTorrent/i'],                      'name' => 'SwiftyTorrent',        'icon' => '⚡',  'category' => 'ios'],
        'itransmission' => ['patterns' => ['/iTransmission/i'],                      'name' => 'iTransmission',        'icon' => '🔄',  'category' => 'ios'],
        // Streaming
        'popcorntime'   => ['patterns' => ['/Popcorn.?Time/i'],                      'name' => 'Popcorn Time',         'icon' => '🍿',  'category' => 'streaming'],
        'acestream'     => ['patterns' => ['/AceStream/i'],                          'name' => 'Ace Stream',           'icon' => '🅰️', 'category' => 'streaming'],
        'webtorrent'    => ['patterns' => ['/WebTorrent/i'],                         'name' => 'WebTorrent',           'icon' => '🌐',  'category' => 'webbased'],
        // Cheat — всегда последними
        'ratio_master'  => ['patterns' => ['/RatioMaster/i', '/\bRM\d/'],            'name' => 'Ratio Master',         'icon' => '⚠️',  'category' => 'cheat'],
        'mratio'        => ['patterns' => ['/mRator/i'],                             'name' => 'mRator',               'icon' => '⚠️',  'category' => 'cheat'],
        'shumod'        => ['patterns' => ['/shuMod/i'],                             'name' => 'shuMod',               'icon' => '⚠️',  'category' => 'cheat'],
    ];

    return $db;
}

// ============================================================
//  CATEGORY META
// ============================================================

function get_category_meta(string $category): array
{
    static $meta = [
        'modern'       => ['badge_class' => 'primary',   'badge_label' => '',         'platform' => 'desktop'],
        'classic'      => ['badge_class' => 'secondary', 'badge_label' => '',         'platform' => 'desktop'],
        'lightweight'  => ['badge_class' => 'success',   'badge_label' => '',         'platform' => 'desktop'],
        'modular'      => ['badge_class' => 'info',      'badge_label' => '',         'platform' => 'desktop'],
        'feature-rich' => ['badge_class' => 'warning',   'badge_label' => '',         'platform' => 'desktop'],
        'windows'      => ['badge_class' => 'secondary', 'badge_label' => '🪟',       'platform' => 'windows'],
        'privacy'      => ['badge_class' => 'dark',      'badge_label' => '',         'platform' => 'desktop'],
        'multimedia'   => ['badge_class' => 'danger',    'badge_label' => '',         'platform' => 'desktop'],
        'opensource'   => ['badge_class' => 'success',   'badge_label' => 'OSS',      'platform' => 'desktop'],
        'original'     => ['badge_class' => 'secondary', 'badge_label' => '',         'platform' => 'desktop'],
        'terminal'     => ['badge_class' => 'dark',      'badge_label' => 'CLI',      'platform' => 'linux'],
        'web'          => ['badge_class' => 'warning',   'badge_label' => 'WEB',      'platform' => 'web'],
        'webbased'     => ['badge_class' => 'warning',   'badge_label' => 'WEB',      'platform' => 'web'],
        'android'      => ['badge_class' => 'success',   'badge_label' => 'ANDROID',  'platform' => 'android'],
        'ios'          => ['badge_class' => 'primary',   'badge_label' => 'iOS',      'platform' => 'ios'],
        'mobile'       => ['badge_class' => 'info',      'badge_label' => 'MOBILE',   'platform' => 'mobile'],
        'linux'        => ['badge_class' => 'dark',      'badge_label' => 'LINUX',    'platform' => 'linux'],
        'streaming'    => ['badge_class' => 'secondary', 'badge_label' => 'STREAM',   'platform' => 'desktop'],
        'chinese'      => ['badge_class' => 'secondary', 'badge_label' => 'CN',       'platform' => 'desktop'],
        'cheat'        => ['badge_class' => 'danger',    'badge_label' => '⚠️ CHEAT', 'platform' => 'desktop'],
        'mac'          => ['badge_class' => 'secondary', 'badge_label' => 'MAC',      'platform' => 'mac'],
        'download'     => ['badge_class' => 'info',      'badge_label' => 'DL MGR',   'platform' => 'desktop'],
        'network'      => ['badge_class' => 'secondary', 'badge_label' => 'P2P',      'platform' => 'desktop'],
        'p2p'          => ['badge_class' => 'secondary', 'badge_label' => 'P2P',      'platform' => 'desktop'],
        'unknown'      => ['badge_class' => 'secondary', 'badge_label' => '',         'platform' => 'unknown'],
    ];
    return $meta[$category] ?? $meta['unknown'];
}

// ============================================================
//  MAIN DETECTION
// ============================================================

function getagent(?string $httpagent = '', ?string $peer_id = ''): string
{
    global $lang;

    $httpagent = (string)($httpagent ?? '');
    $peer_id   = (string)($peer_id   ?? '');

    if ($httpagent === '' && $peer_id === '') {
        return $lang->global['unknown'] ?? 'Unknown';
    }

    $agent_source     = $httpagent !== '' ? $httpagent : $peer_id;
    $db               = get_client_db();
    $matched_name     = null;
    $matched_icon     = '❓';
    $matched_category = 'unknown';
    $version          = '';

    foreach ($db as $client) {
        foreach ($client['patterns'] as $pattern) {
            if (preg_match($pattern, $agent_source)) {
                $matched_name     = $client['name'];
                $matched_icon     = $client['icon'];
                $matched_category = $client['category'];
                break 2;
            }
        }
    }

    // Fallback: Azureus peer_id
    if ($matched_name === null && $peer_id !== '') {
        $parsed = parse_azureus_peer_id($peer_id);
        if ($parsed !== null) {
            $info             = azureus_code_to_client($parsed['code']);
            $matched_name     = $info['name'];
            $matched_icon     = $info['icon'];
            $matched_category = $info['category'];
            $version          = $parsed['version'];
        }
    }

    if ($matched_name === null) {
        return clean_agent_string($agent_source);
    }

    if ($version === '' && preg_match('/(\d+\.\d+(?:\.\d+){0,2})/', $agent_source, $vm)) {
        $version = $vm[1];
    }

    $meta  = get_category_meta($matched_category);
    $badge = '';
    if ($meta['badge_label'] !== '') {
        $bc    = $meta['badge_class'];
        $badge = ' <span class="badge bg-' . $bc . ' bg-opacity-20 text-' . $bc
               . ' border border-' . $bc . ' border-opacity-25" style="font-size:.6em">'
               . htmlspecialchars($meta['badge_label']) . '</span>';
    }

    $version_str = $version !== '' ? " {$version}" : '';
    return "{$matched_icon} {$matched_name}{$version_str}{$badge}";
}

// ============================================================
//  HELPERS
// ============================================================

function clean_agent_string(string $s): string
{
    $s = trim(preg_replace('/[^\x20-\x7E]/', '', $s));
    return $s !== '' ? (strlen($s) > 50 ? substr($s, 0, 47) . '...' : $s) : 'Unknown';
}

function get_agent_info(?string $httpagent = '', ?string $peer_id = ''): array
{
    $httpagent = (string)($httpagent ?? '');
    $peer_id   = (string)($peer_id   ?? '');
    $db        = get_client_db();

    $agent_source     = $httpagent !== '' ? $httpagent : $peer_id;
    $matched_category = 'unknown';
    $is_cheat         = false;

    foreach ($db as $client) {
        foreach ($client['patterns'] as $pattern) {
            if (preg_match($pattern, $agent_source)) {
                $matched_category = $client['category'];
                $is_cheat         = ($client['category'] === 'cheat');
                break 2;
            }
        }
    }

    if ($matched_category === 'unknown' && $peer_id !== '') {
        $parsed = parse_azureus_peer_id($peer_id);
        if ($parsed !== null) {
            $info             = azureus_code_to_client($parsed['code']);
            $matched_category = $info['category'];
            $is_cheat         = ($info['category'] === 'cheat');
        }
    }

    $meta = get_category_meta($matched_category);

    return [
        'display_name'  => getagent($httpagent, $peer_id),
        'category'      => $matched_category,
        'platform'      => $meta['platform'],
        'is_mobile'     => in_array($meta['platform'], ['android', 'ios', 'mobile'], true),
        'is_suspicious' => $is_cheat,
        'is_cheat'      => $is_cheat,
    ];
}

function get_agent_html(?string $httpagent = '', ?string $peer_id = '', bool $show_details = false): string
{
    $info = get_agent_info($httpagent, $peer_id);
    $name = $info['display_name'];

    if ($info['is_cheat']) {
        return '<span class="text-danger fw-bold" title="Cheating client detected!">' . $name . '</span>';
    }

    return '<span class="torrent-client">' . $name . '</span>';
}

// ============================================================
//  BEHAVIOURAL CHEAT DETECTION
// ============================================================

function detect_ratio_cheat(array $e, array $torrent): array
{
    $flags          = [];
    $uploaded       = (int)($e['uploaded']   ?? 0);
    $downloaded     = (int)($e['downloaded'] ?? 0);
    $to_go          = (int)($e['to_go']      ?? 0);
    $torrent_size   = max(1, (int)($torrent['size'] ?? 1));
    $progress       = min(100.0, max(0.0, 100.0 * (1.0 - ($to_go / $torrent_size))));
    $now            = TIMENOW;
    $connected_secs = max(1, $now - ($e['st'] ?? $now));
    $is_seeder      = ($e['seeder'] ?? 'no') === 'yes';

    // Аплоад без скачивания — только для личеров
    if (!$is_seeder && $uploaded > 50 * 1024 * 1024 && $downloaded < 1 * 1024 * 1024) {
        $flags[] = [
            'code'     => 'UL_NO_DL',
            'severity' => 'high',
            'label'    => 'Upload without download',
            'detail'   => 'Uploaded ' . mksize($uploaded) . ' but downloaded less than 1 MB',
        ];
    }

    // Нулевой прогресс + большой аплоад — только для личеров
    if (!$is_seeder && $progress < 1.0 && $uploaded > 100 * 1024 * 1024) {
        $flags[] = [
            'code'     => 'ZERO_PROGRESS_UL',
            'severity' => 'high',
            'label'    => 'Zero progress + high upload',
            'detail'   => sprintf('%.1f%% progress but uploaded %s', $progress, mksize($uploaded)),
        ];
    }

    // Физически невозможная средняя скорость (> 1 Гбит/с)
    $avg_ul_speed = $uploaded / $connected_secs;
    if ($avg_ul_speed > 125 * 1024 * 1024) {
        $flags[] = [
            'code'     => 'IMPOSSIBLE_SPEED',
            'severity' => 'high',
            'label'    => 'Impossible upload speed',
            'detail'   => 'Avg ' . mksize((int)$avg_ul_speed) . '/s exceeds 1 Gbps',
        ];
    }

    // Аплоад превышает размер торрента менее чем за час
    if ($uploaded > $torrent_size && $connected_secs < 3600) {
        $flags[] = [
            'code'     => 'UL_EXCEEDS_SIZE',
            'severity' => 'high',
            'label'    => 'Upload exceeds torrent size',
            'detail'   => 'Uploaded ' . mksize($uploaded) . ' of a ' . mksize($torrent_size) . ' torrent in under 1h',
        ];
    }

    // Подозрительно круглый рейтинг
    if ($downloaded > 0) {
        $ratio = $uploaded / $downloaded;
        $frac  = abs($ratio - round($ratio));
        if ($ratio > 1.0 && $frac < 0.001 && $uploaded > 10 * 1024 * 1024) {
            $flags[] = [
                'code'     => 'PERFECT_RATIO',
                'severity' => 'low',
                'label'    => 'Suspiciously round ratio',
                'detail'   => 'Ratio is exactly ' . number_format($ratio, 3),
            ];
        }
    }

    // Призрак: онлайн >24ч, но ничего не передал
    if ($connected_secs > 86400 && $uploaded < 1024 && $downloaded < 1024) {
        $flags[] = [
            'code'     => 'GHOST_PEER',
            'severity' => 'medium',
            'label'    => 'Ghost peer',
            'detail'   => 'Connected for ' . mkprettytime($connected_secs) . ' with no data transfer',
        ];
    }

    // Скачал намного больше, чем должен по прогрессу — только для личеров
    if (!$is_seeder && $progress > 10.0 && $downloaded > 0) {
        $expected_dl = $torrent_size * ($progress / 100.0);
        if ($downloaded > $expected_dl * 3) {
            $flags[] = [
                'code'     => 'DL_PROGRESS_MISMATCH',
                'severity' => 'medium',
                'label'    => 'Download/progress mismatch',
                'detail'   => sprintf(
                    'Downloaded %s but progress only %.1f%% (expected ~%s)',
                    mksize($downloaded), $progress, mksize((int)$expected_dl)
                ),
            ];
        }
    }

    return $flags;
}

function cheat_flags_max_severity(array $flags): string
{
    if (empty($flags)) return 'none';
    $order = ['low' => 1, 'medium' => 2, 'high' => 3];
    $max   = 0;
    $level = 'none';
    foreach ($flags as $f) {
        $s = $order[$f['severity']] ?? 0;
        if ($s > $max) { $max = $s; $level = $f['severity']; }
    }
    return $level;
}

function render_cheat_flags_html(array $flags): string
{
    if (empty($flags)) return '';

    $cfg_map = [
        'high'   => ['bg' => 'danger',   'icon' => 'bi-shield-fill-exclamation'],
        'medium' => ['bg' => 'warning',  'icon' => 'bi-exclamation-triangle-fill'],
        'low'    => ['bg' => 'secondary','icon' => 'bi-info-circle-fill'],
    ];

    $items = '';
    foreach ($flags as $f) {
        $cfg    = $cfg_map[$f['severity']] ?? $cfg_map['low'];
        $items .= '<li class="mb-1">'
                . '<span class="badge bg-' . $cfg['bg'] . ' me-1">'
                . '<i class="bi ' . $cfg['icon'] . ' me-1"></i>'
                . htmlspecialchars($f['severity'])
                . '</span>'
                . '<strong>' . htmlspecialchars($f['label']) . '</strong>'
                . ' <span class="text-muted">— ' . htmlspecialchars($f['detail']) . '</span>'
                . '</li>';
    }

    return '<div class="mt-2 p-2 border border-danger border-opacity-25 rounded bg-danger bg-opacity-10">'
         . '<p class="mb-1 small fw-bold text-danger"><i class="bi bi-shield-exclamation me-1"></i>Behaviour flags</p>'
         . '<ul class="mb-0 ps-3 small">' . $items . '</ul>'
         . '</div>';
}

// ============================================================
//  PEER AGE BADGE
// ============================================================

function get_peer_age_badge(int $connected_at): string
{
    $secs = TIMENOW - $connected_at;

    if ($secs < 60) {
        return '<span class="badge bg-success rounded-pill px-2 py-1 ms-2" title="Connected less than a minute ago">'
             . '<i class="bi bi-lightning-charge-fill me-1"></i>NEW</span>';
    }
    if ($secs < 300) {
        return '<span class="badge bg-info bg-opacity-75 rounded-pill px-2 py-1 ms-2" title="Connected ' . mkprettytime($secs) . ' ago">'
             . '<i class="bi bi-clock me-1"></i>JUST JOINED</span>';
    }
    if ($secs >= 7 * 86400) {
        $days = (int)floor($secs / 86400);
        return '<span class="badge bg-primary bg-opacity-75 rounded-pill px-2 py-1 ms-2" title="Connected for ' . $days . ' days">'
             . '<i class="bi bi-star-fill me-1"></i>VETERAN ' . $days . 'd</span>';
    }

    return '';
}

// ============================================================
//  CSS + JS
// ============================================================

function get_peers_css(): string
{
    return <<<'CSS'
<style>
.peers-grid{max-height:640px;overflow-y:auto;padding-right:6px}
.peers-grid::-webkit-scrollbar{width:5px}
.peers-grid::-webkit-scrollbar-track{background:#f5f5f5;border-radius:8px}
.peers-grid::-webkit-scrollbar-thumb{background:#ccc;border-radius:8px}
.peers-grid::-webkit-scrollbar-thumb:hover{background:#999}
.peer-card{border-left:3px solid transparent!important;border-radius:8px;transition:transform .18s ease,box-shadow .18s ease,border-left-color .18s ease}
.peer-card:hover{transform:translateX(4px) translateY(-2px);border-left-color:#0d6efd!important;box-shadow:0 4px 14px rgba(0,0,0,.08)}
.peer-card.border-danger{border-left-color:#dc3545!important}
.current-user-card{background:linear-gradient(to right,rgba(13,110,253,.03),transparent)}
.avatar-image-custom{width:50px;height:50px;border-radius:10px;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.avatar-placeholder-custom{width:50px;height:50px;border-radius:10px;background:linear-gradient(135deg,#667eea22,#764ba222);display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#667eea;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.anonymous-avatar-wrapper{width:50px;height:50px;border-radius:10px;background:#f8f9fa;display:flex;align-items:center;justify-content:center;border:2px solid #e9ecef;color:#adb5bd;font-size:1.3rem}
.current-user-badge{position:absolute;top:-6px;right:-6px;background:#0d6efd;color:#fff;font-size:8px;font-weight:700;padding:2px 6px;border-radius:10px;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.peers-no-results{display:none;text-align:center;color:#999;padding:2rem}
@media(max-width:600px){
  .peer-card .d-flex{flex-direction:column}
  .peer-card .me-3{margin-right:0!important;margin-bottom:12px}
  .peer-card .ms-3{margin-left:0!important;margin-top:12px}
  .d-flex.gap-3{flex-wrap:wrap;gap:8px!important}
}
</style>
CSS;
}

function get_peers_js(): string
{
    return <<<'JS'
<script>
function filterPeers(input) {
  const q = input.value.toLowerCase().trim();
  const grid = input.closest('.peers-header').nextElementSibling;
  if (!grid) return;
  let visible = 0;
  grid.querySelectorAll('.peer-card').forEach(card => {
    const show = !q || (card.dataset.user||'').includes(q) || (card.dataset.ip||'').includes(q);
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  let noRes = grid.querySelector('.peers-no-results');
  if (!noRes) {
    noRes = document.createElement('p');
    noRes.className = 'peers-no-results';
    noRes.textContent = 'No peers match your filter.';
    grid.appendChild(noRes);
  }
  noRes.style.display = (!visible && q) ? 'block' : 'none';
}
</script>
JS;
}

// ============================================================
//  dltable()
// ============================================================

function dltable(string $name, ?array $arr, array $torrent, bool $is_seeders = false): string
{
    global $CURUSER, $lang, $is_mod, $BASEURL;

    $peers      = is_array($arr) ? $arr : [];
    $totalcount = count($peers);
    $now        = TIMENOW;

    $type_label = $is_seeders ? 'SEEDING' : 'LEECHING';
    $type_class = $is_seeders ? 'success'  : 'danger';

    // ── Aggregate stats ───────────────────────────────────────
    $total_up    = 0;
    $total_down  = 0;
    $cheat_count = 0;

    foreach ($peers as $e) {
        $secs        = max(1, ($now - ($e['st'] ?? $now)) - ($now - ($e['la'] ?? $now)));
        $total_up   += ($e['uploaded']   - ($e['uploadoffset']   ?? 0)) / $secs;
        $total_down += ($e['downloaded'] - ($e['downloadoffset'] ?? 0)) / $secs;

        $ai = get_agent_info($e['agent'] ?? '', $e['peer_id'] ?? '');
        $bf = detect_ratio_cheat($e, $torrent);
        if ($ai['is_cheat'] || cheat_flags_max_severity($bf) === 'high') {
            $cheat_count++;
        }
    }

    // ── Cheat alert (mod only) ────────────────────────────────
    $cheat_alert = '';
    if ($cheat_count > 0 && $is_mod) {
        $cheat_alert = '<div class="alert alert-danger alert-sm d-flex align-items-center gap-2 py-2 mb-3 rounded-0 border-0 border-start border-danger border-3">'
                     . '<i class="bi bi-shield-exclamation fs-5"></i>'
                     . '<span><strong>' . $cheat_count . ' cheat client' . ($cheat_count > 1 ? 's' : '') . '</strong> detected in this peer list.</span>'
                     . '</div>';
    }

    // ── Search box ────────────────────────────────────────────
    $search_box = $totalcount > 4
        ? '<div class="mb-3"><div class="input-group input-group-sm">'
        . '<span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>'
        . '<input type="text" class="form-control border-start-0" placeholder="Filter by username or IP…" oninput="filterPeers(this)">'
        . '</div></div>'
        : '';

    // ── Header ────────────────────────────────────────────────
    $header = '<div class="peers-header mb-4">'
            . '<div class="d-flex align-items-center flex-wrap gap-2">'
            . '<h4 class="fw-light mb-0 me-2">' . htmlspecialchars($name) . '</h4>'
            . '<span class="badge bg-dark rounded-0 px-3 py-2">' . $totalcount . ' peers</span>'
            . '<span class="badge bg-' . $type_class . ' rounded-0 px-3 py-2">' . $type_label . '</span>'
            . '</div>'
            . '<hr class="my-3 opacity-25">'
            . '<div class="d-flex gap-3 small text-muted mb-3">'
            . '<span><i class="bi bi-arrow-up-circle text-success me-1"></i>Total up: <strong>' . mksize((int)$total_up) . '/s</strong></span>'
            . '<span><i class="bi bi-arrow-down-circle text-info me-1"></i>Total down: <strong>' . mksize((int)$total_down) . '/s</strong></span>'
            . '</div>'
            . $cheat_alert
            . $search_box
            . '</div>';

    // ── Empty state ───────────────────────────────────────────
    if ($totalcount === 0) {
        $empty_svg = '<svg width="64" height="64" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">'
            . '<circle cx="36" cy="36" r="36" fill="#f8f9fa"/>'
            . '<path d="M20 44 Q24 30 36 30 Q40 30 43 33" stroke="#dee2e6" stroke-width="2" stroke-linecap="round" fill="none"/>'
            . '<path d="M46 36 Q52 36 52 42 Q52 48 46 48 L26 48 Q20 48 20 42 Q20 38 24 37" stroke="#dee2e6" stroke-width="2" stroke-linecap="round" fill="none"/>'
            . '<line x1="28" y1="44" x2="44" y2="44" stroke="#e9ecef" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="3 3"/>'
            . '<circle cx="52" cy="20" r="8" fill="#fff" stroke="#dee2e6" stroke-width="1.5"/>'
            . '<line x1="52" y1="17" x2="52" y2="23" stroke="#adb5bd" stroke-width="1.5" stroke-linecap="round"/>'
            . '<line x1="49" y1="20" x2="55" y2="20" stroke="#adb5bd" stroke-width="1.5" stroke-linecap="round"/>'
            . '</svg>';

        return $header
             . '<div class="text-center py-5" style="background:#f8f9fa;border-radius:8px">'
             . '<div class="mb-3 opacity-75">' . $empty_svg . '</div>'
             . '<p class="fw-500 mb-1" style="font-size:15px">No active peers</p>'
             . '<p class="text-muted small mb-0">Nobody is connected right now. Check back later.</p>'
             . '</div>';
    }

    include_once(INC_PATH . '/functions_ratio.php');

    $cards = '';
    foreach ($peers as $e) {
        $cards .= render_peer_card($e, $torrent, $is_seeders, $now);
    }

    return $header
         . '<div class="peers-grid" id="peers-grid-' . ($is_seeders ? 'seed' : 'leech') . '">'
         . $cards
         . '</div>'
         . get_peers_css()
         . get_peers_js();
}

// ============================================================
//  SINGLE PEER CARD
// ============================================================

function render_peer_card(array $e, array $torrent, bool $is_seeders, int $now): string
{
    global $CURUSER, $is_mod;

    $progress  = min(100.0, max(0.0, 100.0 * (1.0 - ($e['to_go'] / max(1, $torrent['size'])))));
    $bar_class = $is_seeders ? 'success' : 'info';

    // Invisible peer
    $is_invisible = (($e['invisible'] ?? '0') === '1');
    if ($is_invisible && !$is_mod && ($CURUSER['id'] ?? 0) != ($e['id'] ?? -1)) {
        return render_invisible_peer_card($e, $now, $progress, $bar_class);
    }

    // ── Speeds ────────────────────────────────────────────────
    $secs       = max(1, ($now - $e['st']) - ($now - $e['la']));
    $up_speed   = ($e['uploaded']   - ($e['uploadoffset']   ?? 0)) / $secs;
    $down_speed = ($e['seeder'] === 'no')
        ? ($e['downloaded'] - ($e['downloadoffset'] ?? 0)) / $secs
        : ($e['downloaded'] - ($e['downloadoffset'] ?? 0)) / max(1, ($e['finishedat'] ?? $now) - $e['st']);

    $up_badge   = '<span class="badge bg-success bg-opacity-75 text-white"><i class="bi bi-speedometer me-1"></i>'  . mksize((int)$up_speed)   . '/s</span>';
    $down_badge = '<span class="badge bg-danger  bg-opacity-75 text-white"><i class="bi bi-speedometer2 me-1"></i>' . mksize((int)$down_speed) . '/s</span>';

    // ── Ratio ─────────────────────────────────────────────────
    if ($e['downloaded'] > 0) {
        $ratio       = floor(($e['uploaded'] / $e['downloaded']) * 1000) / 1000;
        $ratio_color = get_ratio_color($ratio);
        $ratio_icon  = $ratio >= 1.0 ? 'bi-arrow-up-right-circle' : ($ratio >= 0.5 ? 'bi-arrow-right-circle' : 'bi-arrow-down-right-circle');
        $ratio_html  = '<span class="d-flex align-items-center"><i class="bi ' . $ratio_icon . ' me-1" style="color:' . $ratio_color . '"></i>'
                     . '<span class="fw-bold" style="color:' . $ratio_color . '">' . number_format($ratio, 2) . '</span></span>';
    } elseif ($e['uploaded'] > 0) {
        $ratio_html = '<span class="badge bg-success"><i class="bi bi-infinity me-1"></i>∞</span>';
    } else {
        $ratio_html = '<span class="text-muted">---</span>';
    }

    // ── Agent ─────────────────────────────────────────────────
    $agent       = (string)($e['agent']   ?? '');
    $peer_id     = (string)($e['peer_id'] ?? '');
    $agent_info  = get_agent_info($agent, $peer_id);
    $client_html = get_agent_html($agent, $peer_id);

    // ── Cheat detection ───────────────────────────────────────
    $cheat_flags      = $is_mod ? detect_ratio_cheat($e, $torrent) : [];
    $cheat_severity   = cheat_flags_max_severity($cheat_flags);
    $cheat_flags_html = render_cheat_flags_html($cheat_flags);
    $is_any_cheat     = $agent_info['is_cheat'] || $cheat_severity === 'high';
    $cheat_border     = $is_any_cheat ? ' border-danger' : '';

    $cheat_badge = $agent_info['is_cheat']
        ? ' <span class="badge bg-danger rounded-0 ms-1"><i class="bi bi-shield-x me-1"></i>CHEAT</span>'
        : '';

    // ── Age badge ─────────────────────────────────────────────
    $age_badge = get_peer_age_badge((int)($e['st'] ?? TIMENOW));

    // ── Avatar ────────────────────────────────────────────────
    $show_identity = !empty($e['username'])
        && ($is_mod || ($torrent['anonymous'] ?? '') !== 'yes' || ($e['id'] ?? 0) != ($torrent['owner'] ?? -1));

    $avatar_html = '';
    if ($show_identity) {
        $ua = format_avatar($e['avatar'] ?? '', $e['avatardimensions'] ?? '', '50x50');
        if (str_starts_with((string)($ua['image'] ?? ''), '<')) {
            $avatar_html = str_replace('avatar-ring2', 'avatar-ring2 avatar-placeholder-custom', $ua['image'] ?? '');
        } else {
            $avatar_html = '<img class="avatar-image-custom rounded" src="' . ($ua['image'] ?? '') . '" alt="" '
                         . ($ua['width_height'] ?? 'width="50" height="50"') . '>';
        }
    }
    $avatar_html = $avatar_html ?: '<div class="avatar-placeholder-custom"><i class="bi bi-person"></i></div>';

    // ── IP block ──────────────────────────────────────────────
    $ip_raw    = htmlspecialchars_uni($e['ip'] ?? '');
    $ip_masked = preg_replace('/\.\d+\.\d+$/', '.***', $ip_raw);
    $port      = (int)($e['port'] ?? 0);
    $ul_sz     = mksize($e['uploaded']   ?? 0);
    $dl_sz     = mksize($e['downloaded'] ?? 0);

    $ip_block = $is_mod
        ? '<div class="mt-1"><small class="badge bg-dark bg-opacity-25 text-dark"><i class="bi bi-globe me-1"></i>' . $ip_raw . ':' . $port . '</small>'
        . ' <span><i class="bi bi-arrow-up text-success"></i> ' . $ul_sz . '</span>'
        . ' <span><i class="bi bi-arrow-down text-info"></i> '  . $dl_sz . '</span></div>'
        : '<div class="mt-1"><small class="badge bg-light text-muted"><i class="bi bi-eye me-1"></i>' . $ip_masked . ':' . $port . '</small>'
        . ' <span><i class="bi bi-arrow-up text-success"></i> ' . $ul_sz . '</span>'
        . ' <span><i class="bi bi-arrow-down text-info"></i> '  . $dl_sz . '</span></div>';

    // ── User block ────────────────────────────────────────────
    if (!$show_identity) {
        $user_html = '<span class="text-muted"><i class="bi bi-incognito me-2"></i>Anonymous</span>';
    } elseif (empty($e['username'])) {
        $user_html = '<span class="text-muted"><i class="bi bi-question-circle me-2"></i>Unknown</span>';
    } else {
        $user_html = '<div class="d-flex align-items-center flex-wrap gap-1">'
                   . '<a href="' . get_profile_link($e['userid'] ?? 0) . '" class="text-decoration-none fw-bold text-dark">'
                   . get_user_color($e['username'], $e['namestyle'] ?? '')
                   . '</a>'
                   . $age_badge
                   . '</div>'
                   . $ip_block
                   . $cheat_flags_html;
    }

    // ── Card classes ──────────────────────────────────────────
    $is_current = (($CURUSER['id'] ?? -1) == ($e['id'] ?? -2));
    $card_class = $is_current
        ? 'border-primary shadow current-user-card'
        : 'border-light' . $cheat_border;
    $you_badge  = $is_current ? '<span class="current-user-badge">You</span>' : '';

    $connectable_badge = '<span class="badge ' . ($e['connectable'] === 'yes' ? 'bg-success' : 'bg-danger')
                       . ' rounded-0 px-3 py-2">'
                       . ($e['connectable'] === 'yes' ? 'CONNECTABLE' : 'FIREWALLED')
                       . '</span>'
                       . $cheat_badge;

    $data_user      = htmlspecialchars(strtolower($e['username'] ?? ''));
    $data_ip        = htmlspecialchars(strtolower($e['ip']       ?? ''));
    $connected_time = mkprettytime($now - $e['st']);
    $idle_time      = mkprettytime($now - $e['la']);
    $progress_fmt   = sprintf('%.1f%%', $progress);

    return <<<HTML
<div class="peer-card mb-3 p-3 bg-white border {$card_class}"
     data-user="{$data_user}" data-ip="{$data_ip}">
  <div class="d-flex align-items-start">
    <div class="me-3 position-relative">
      {$avatar_html}
      {$you_badge}
    </div>
    <div class="flex-grow-1">
      <div class="d-flex justify-content-between align-items-start">
        <div class="w-100">
          {$user_html}
          <div class="d-flex flex-wrap gap-3 small text-secondary mt-2">
            <div>
              <span class="text-secondary d-block"><i class="bi bi-speedometer me-1"></i>Up</span>
              {$up_badge}
            </div>
            <div>
              <span class="text-secondary d-block"><i class="bi bi-speedometer2 me-1"></i>Down</span>
              {$down_badge}
            </div>
            <div>
              <span class="text-secondary d-block"><i class="bi bi-percent me-1"></i>Ratio</span>
              {$ratio_html}
            </div>
            <div>
              <span class="text-secondary d-block"><i class="bi bi-clock-history me-1"></i>Connected</span>
              <span class="fw-bold">{$connected_time}</span>
            </div>
            <div>
              <span class="text-secondary d-block"><i class="bi bi-hourglass-split me-1"></i>Idle</span>
              <span class="fw-bold">{$idle_time}</span>
            </div>
            <div>
              <span class="text-secondary d-block"><i class="bi bi-laptop me-1"></i>Client</span>
              <span class="fw-bold">{$client_html}</span>
            </div>
          </div>
        </div>
        <div class="text-end ms-3">
          {$connectable_badge}
          <div class="mt-2" style="min-width:120px">
            <div class="d-flex justify-content-between small mb-1">
              <span>Progress</span>
              <span class="fw-bold">{$progress_fmt}</span>
            </div>
            <div class="progress" style="height:4px">
              <div class="progress-bar bg-{$bar_class}" style="width:{$progress}%"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
HTML;
}

// ============================================================
//  INVISIBLE PEER CARD
// ============================================================

function render_invisible_peer_card(array $e, int $now, float $progress, string $bar_class): string
{
    $connected = mkprettytime($now - ($e['st'] ?? $now));
    $idle      = mkprettytime($now - ($e['la'] ?? $now));
    $prog_fmt  = sprintf('%.1f%%', $progress);

    return <<<HTML
<div class="peer-card mb-3 p-3 bg-white border border-light" data-user="__invisible__" data-ip="">
  <div class="d-flex align-items-start">
    <div class="me-3">
      <div class="anonymous-avatar-wrapper"><i class="bi bi-incognito fs-2 text-secondary"></i></div>
    </div>
    <div class="flex-grow-1">
      <div class="d-flex justify-content-between align-items-start">
        <div class="w-100">
          <span class="text-muted"><i class="bi bi-incognito me-2"></i>Invisible User</span>
          <div class="d-flex gap-3 small text-secondary mt-2">
            <span class="text-muted">↑ --</span>
            <span class="text-muted">↓ --</span>
            <div>
              <span class="text-secondary d-block">Connected</span>
              <span class="fw-bold">{$connected}</span>
            </div>
            <div>
              <span class="text-secondary d-block">Idle</span>
              <span class="fw-bold">{$idle}</span>
            </div>
          </div>
        </div>
        <div class="text-end ms-3">
          <span class="badge bg-secondary rounded-0 px-3 py-2">
            <i class="bi bi-incognito me-1"></i>INVISIBLE
          </span>
          <div class="mt-2" style="min-width:120px">
            <div class="d-flex justify-content-between small mb-1">
              <span>Progress</span><span class="fw-bold">{$prog_fmt}</span>
            </div>
            <div class="progress" style="height:4px">
              <div class="progress-bar bg-{$bar_class}" style="width:{$progress}%"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
HTML;
}