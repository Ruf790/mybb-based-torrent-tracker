<?php
declare(strict_types=1);

define('L_VERSION', '0.2');
require_once 'global.php';
gzip();

include_once INC_PATH . '/functions_security.php';
$lang->load('links');

// ── Данные ────────────────────────────────────────────────

const STATS = [
    ['bi-windows', 'primary', '24+', 'Windows Clients'],
    ['bi-ubuntu',  'success', '18+', 'Linux Clients'],
    ['bi-apple',   'info',    '12+', 'Mac Clients'],
    ['bi-phone',   'warning', '15+', 'Mobile Apps'],
];

const CLIENTS = [
    // Популярные
    '_group' => null,

    'popular' => [
        'label'   => null,
        'clients' => [
            [
                'star'     => 'bi-star-fill text-warning',
                'name'     => 'qBittorrent',
                'subtitle' => 'Open Source',
                'platform' => ['bi-windows', 'bi-ubuntu', 'bi-apple'],
                'desc'     => 'µTorrent alternative, no ads, feature-rich',
                'status'   => ['Active', 'success'],
                'btn'      => ['Download', 'primary', 'https://www.qbittorrent.org/'],
                'highlight'=> true,
            ],
            [
                'star'     => 'bi-star-fill text-warning',
                'name'     => 'Deluge',
                'subtitle' => 'Thin Client',
                'platform' => ['bi-ubuntu', 'bi-windows', 'bi-apple'],
                'desc'     => 'Lightweight, modular, cross-platform',
                'status'   => ['Active', 'success'],
                'btn'      => ['Download', 'success', 'https://deluge-torrent.org/'],
            ],
            [
                'star'     => 'bi-star-fill text-warning',
                'name'     => 'Transmission',
                'subtitle' => 'Minimalistic',
                'platform' => ['bi-apple', 'bi-ubuntu'],
                'desc'     => 'Fast, lightweight, native macOS & Linux',
                'status'   => ['Active', 'success'],
                'btn'      => ['Download', 'info', 'https://transmissionbt.com/'],
            ],
        ],
    ],

    'windows' => [
        'label'   => ['bi-windows', 'Windows Clients'],
        'clients' => [
            [
                'star'  => 'bi-lightning text-warning',
                'name'  => 'µTorrent',
                'platform' => ['bi-windows'],
                'desc'  => 'Most popular, lightweight, efficient',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'warning', 'https://www.utorrent.com/'],
            ],
            [
                'star'  => 'bi-bell text-info',
                'name'  => 'BitComet',
                'platform' => ['bi-windows'],
                'desc'  => 'Long-established, many features',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'info', 'https://www.bitcomet.com/'],
            ],
            [
                'star'  => 'bi-flower1 text-success',
                'name'  => 'Vuze (Azureus)',
                'platform' => ['bi-windows'],
                'desc'  => 'Java-based, advanced features',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'success', 'https://www.vuze.com/'],
            ],
            [
                'star'  => 'bi-lightning-charge text-danger',
                'name'  => 'Tixati',
                'platform' => ['bi-windows'],
                'desc'  => 'No ads, privacy focused',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'danger', 'https://www.tixati.com/'],
            ],
            [
                'star'  => 'bi-hurricane text-primary',
                'name'  => 'BitTorrent Classic',
                'platform' => ['bi-windows'],
                'desc'  => 'Original client from creators',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'primary', 'https://www.bittorrent.com/'],
            ],
            [
                'star'  => 'bi-bucket text-warning',
                'name'  => 'FrostWire',
                'platform' => ['bi-windows', 'bi-apple', 'bi-ubuntu'],
                'desc'  => 'Gnutella + BitTorrent, open source',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'warning', 'https://www.frostwire.com/'],
            ],
            [
                'star'  => 'bi-box text-info',
                'name'  => 'Halite',
                'platform' => ['bi-windows'],
                'desc'  => 'Open source, minimalist design',
                'status'=> ['Legacy', 'secondary'],
                'btn'   => ['Archive', 'secondary', 'https://sourceforge.net/projects/halite/'],
            ],
        ],
    ],

    'linux' => [
        'label'   => ['bi-ubuntu', 'Linux Clients'],
        'clients' => [
            [
                'star'  => 'bi-terminal text-success',
                'name'  => 'rTorrent',
                'platform' => ['bi-ubuntu'],
                'desc'  => 'Command line, powerful, lightweight',
                'status'=> ['Active', 'success'],
                'btn'   => ['GitHub', 'success', 'https://github.com/rakshasa/rtorrent'],
            ],
            [
                'star'  => 'bi-boxes text-info',
                'name'  => 'KTorrent',
                'platform' => ['bi-ubuntu'],
                'desc'  => 'KDE integration, feature-rich',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'info', 'https://apps.kde.org/ktorrent/'],
            ],
            [
                'star'  => 'bi-gear text-warning',
                'name'  => 'Transmission-Qt',
                'platform' => ['bi-ubuntu'],
                'desc'  => 'Qt interface for Transmission',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'warning', 'https://transmissionbt.com/'],
            ],
            [
                'star'  => 'bi-braces text-primary',
                'name'  => 'aria2',
                'platform' => ['bi-ubuntu'],
                'desc'  => 'Command line, multi-protocol',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'primary', 'https://aria2.github.io/'],
            ],
            [
                'star'  => 'bi-fan text-danger',
                'name'  => 'Tribler',
                'platform' => ['bi-ubuntu', 'bi-windows'],
                'desc'  => 'Anonymous, decentralized, built-in VPN',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'danger', 'https://www.tribler.org/'],
            ],
        ],
    ],

    'macos' => [
        'label'   => ['bi-apple', 'macOS Clients'],
        'clients' => [
            [
                'star'  => 'bi-app text-info',
                'name'  => 'Folx',
                'platform' => ['bi-apple'],
                'desc'  => 'Mac-style, download manager + torrent',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'info', 'https://mac.eltima.com/folx.html'],
            ],
            [
                'star'  => 'bi-rocket-takeoff text-warning',
                'name'  => 'BitRocket',
                'platform' => ['bi-apple'],
                'desc'  => 'Native macOS, selective downloading',
                'status'=> ['Limited', 'warning'],
                'btn'   => ['Download', 'warning', 'https://bitrocket.org/'],
            ],
            [
                'star'  => 'bi-tomato text-danger',
                'name'  => 'Tomato Torrent',
                'platform' => ['bi-apple'],
                'desc'  => 'Simple, clean macOS interface',
                'status'=> ['Legacy', 'secondary'],
                'btn'   => ['GitHub', 'secondary', 'https://github.com/grahamgilbert/tomato'],
            ],
            [
                'star'  => 'bi-cloud-arrow-down text-primary',
                'name'  => 'WebTorrent Desktop',
                'platform' => ['bi-apple', 'bi-windows', 'bi-ubuntu'],
                'desc'  => 'Stream torrents while downloading',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'primary', 'https://webtorrent.io/desktop/'],
            ],
        ],
    ],

    'mobile' => [
        'label'   => ['bi-phone', 'Mobile Applications'],
        'clients' => [
            [
                'star'  => 'bi-android2 text-success',
                'name'  => 'Flud',
                'platform' => ['bi-android2'],
                'desc'  => 'Material Design, powerful Android client',
                'status'=> ['Active', 'success'],
                'btn'   => ['Play Store', 'success', 'https://play.google.com/store/apps/details?id=com.delphicoder.flud'],
            ],
            [
                'star'  => 'bi-apple text-dark',
                'name'  => 'iTransmission',
                'platform' => ['bi-apple'],
                'desc'  => 'iOS client, requires jailbreak',
                'status'=> ['Jailbreak', 'warning'],
                'btn'   => ['Cydia', 'dark', 'https://cydia.saurik.com/package/itransmission/'],
            ],
            [
                'star'  => 'bi-android2 text-success',
                'name'  => 'LibreTorrent',
                'platform' => ['bi-android2'],
                'desc'  => 'Open source, no ads, Android',
                'status'=> ['Active', 'success'],
                'btn'   => ['F-Droid', 'success', 'https://f-droid.org/packages/org.proninyaroslav.libretorrent/'],
            ],
            [
                'star'  => 'bi-android2 text-success',
                'name'  => 'FrostWire Mobile',
                'platform' => ['bi-android2', 'bi-apple'],
                'desc'  => 'Mobile version of FrostWire',
                'status'=> ['Active', 'success'],
                'btn'   => ['Download', 'warning', 'https://www.frostwire.com/mobile/'],
            ],
        ],
    ],

    'legacy' => [
        'label'   => ['bi-archive', 'Specialized & Legacy Clients'],
        'clients' => [
            [
                'star'  => 'bi-hdd text-secondary',
                'name'  => 'BitTornado',
                'platform' => ['bi-windows', 'bi-ubuntu'],
                'desc'  => 'Experimental client by TheSHAD0W',
                'status'=> ['Legacy', 'secondary'],
                'btn'   => ['Archive', 'secondary', 'http://bittornado.com/'],
            ],
            [
                'star'  => 'bi-lightbulb text-warning',
                'name'  => 'ABC (Yet Another BitTorrent Client)',
                'platform' => ['bi-windows'],
                'desc'  => 'Improved BitTorrent client, stable',
                'status'=> ['Legacy', 'secondary'],
                'btn'   => ['Archive', 'secondary', 'http://pingpong-abc.sourceforge.net/'],
            ],
            [
                'star'  => 'bi-browser-chrome text-info',
                'name'  => 'Shareaza',
                'platform' => ['bi-windows'],
                'desc'  => 'Multi-network: Gnutella, eDonkey, BitTorrent',
                'status'=> ['Legacy', 'secondary'],
                'btn'   => ['Archive', 'secondary', 'http://www.shareaza.com/'],
            ],
            [
                'star'  => 'bi-tools text-primary',
                'name'  => 'MakeTorrent',
                'platform' => ['bi-windows'],
                'desc'  => 'Torrent creation tool',
                'status'=> ['Legacy', 'secondary'],
                'btn'   => ['Archive', 'secondary', 'http://krypt.dyndns.org:81/torrent/maketorrent/'],
            ],
            [
                'star'  => 'bi-lightning-charge text-danger',
                'name'  => 'Burst!',
                'platform' => ['bi-windows'],
                'desc'  => 'Alternative Win32 client',
                'status'=> ['Legacy', 'secondary'],
                'btn'   => ['Archive', 'secondary', 'http://krypt.dyndns.org:81/torrent/'],
            ],
            [
                'star'  => 'bi-python text-warning',
                'name'  => 'G3 Torrent',
                'platform' => ['bi-windows', 'bi-ubuntu'],
                'desc'  => 'Python-based graphical client',
                'status'=> ['Legacy', 'secondary'],
                'btn'   => ['Archive', 'secondary', 'http://g3torrent.sourceforge.net/'],
            ],
        ],
    ],
];

const RECOMMENDATIONS = [
    [
        'border' => 'primary',
        'name'   => 'qBittorrent',
        'badge'  => ['#1 Choice', 'primary'],
        'desc'   => 'Open source, no ads, feature-rich, cross-platform',
        'checks' => ['No Ads', 'Open Source', 'All Platforms'],
    ],
    [
        'border' => 'success',
        'name'   => 'Deluge',
        'badge'  => ['Lightweight', 'success'],
        'desc'   => 'Thin client, modular plugins, great for seedboxes',
    ],
    [
        'border' => 'warning',
        'name'   => 'Transmission',
        'badge'  => ['Simple & Fast', 'warning'],
        'desc'   => 'Minimalistic, native macOS/Linux, low resource usage',
    ],
];

const GUIDE_ITEMS = [
    ['bi-windows text-primary', 'Windows Users',    'qBittorrent or Deluge for no ads. µTorrent 2.2.1 for legacy systems.'],
    ['bi-apple text-dark',      'macOS Users',      'Transmission for native experience. qBittorrent for feature parity with Windows.'],
    ['bi-ubuntu text-success',  'Linux Users',      'Deluge for GUI, rTorrent for CLI. Transmission for minimalism.'],
    ['bi-shield-check text-info','Privacy Focused', 'qBittorrent, Deluge, or Tixati. Avoid µTorrent due to ads/tracking.'],
];

// ── Рендер-функции ────────────────────────────────────────

function renderPlatformIcons(array $platforms): string
{
    $icons = implode(' ', array_map(
        fn($p) => '<i class="bi ' . htmlspecialchars($p) . '"></i>',
        $platforms
    ));
    return '<span class="platform-badge">' . $icons . '</span>';
}

function renderClientRow(array $c): string
{
    $highlight = !empty($c['highlight']) ? 'table-primary bg-opacity-10' : 'hover-lift';
    $platforms = renderPlatformIcons($c['platform']);
    [$statusLabel, $statusColor] = $c['status'];
    [$btnLabel,    $btnColor, $btnUrl] = $c['btn'];
    $subtitle = !empty($c['subtitle'])
        ? '<div class="small text-muted">' . htmlspecialchars($c['subtitle']) . '</div>'
        : '';

    return <<<HTML
<tr class="{$highlight}">
    <td class="text-center"><i class="bi {$c['star']}"></i></td>
    <td>
        <strong>{$c['name']}</strong>
        {$subtitle}
    </td>
    <td class="text-center">{$platforms}</td>
    <td>{$c['desc']}</td>
    <td class="text-center">
        <span class="badge bg-{$statusColor}">{$statusLabel}</span>
    </td>
    <td class="text-center">
        <a href="{$btnUrl}" class="btn btn-sm btn-{$btnColor}" target="_blank" rel="noopener">
            {$btnLabel}
        </a>
    </td>
</tr>
HTML;
}

function renderGroupHeader(array $label): string
{
    [$icon, $text] = $label;
    return <<<HTML
<tr class="table-secondary">
    <td colspan="6" class="bg-light fw-bold py-2">
        <i class="bi {$icon} me-2"></i>{$text}
    </td>
</tr>
HTML;
}

function renderAllRows(): string
{
    $html = '';
    foreach (CLIENTS as $key => $group) {
        if ($key === '_group') continue;
        if (!empty($group['label'])) {
            $html .= renderGroupHeader($group['label']);
        }
        foreach ($group['clients'] as $client) {
            $html .= renderClientRow($client);
        }
    }
    return $html;
}

function renderRecommendations(): string
{
    $html = '';
    foreach (RECOMMENDATIONS as $r) {
        [$badgeLabel, $badgeColor] = $r['badge'];
        $checks = '';
        if (!empty($r['checks'])) {
            $checks = '<div class="d-flex flex-wrap gap-3 mt-2">'
                . implode('', array_map(
                    fn($c) => '<span><i class="bi bi-check-circle text-success me-1"></i>' . htmlspecialchars($c) . '</span>',
                    $r['checks']
                ))
                . '</div>';
        }
        $html .= <<<HTML
<div class="recommendation-item mb-3 p-3 border-start border-4 border-{$r['border']}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="fw-bold mb-0">{$r['name']}</h5>
        <span class="badge bg-{$badgeColor}">{$badgeLabel}</span>
    </div>
    <p class="text-muted small mb-0">{$r['desc']}</p>
    {$checks}
</div>
HTML;
    }
    return $html;
}

function renderGuide(): string
{
    $html = '';
    foreach (GUIDE_ITEMS as [$icon, $title, $text]) {
        $html .= <<<HTML
<div class="guide-item mb-3">
    <h6 class="fw-bold"><i class="bi {$icon} me-2"></i>{$title}</h6>
    <p class="text-muted small mb-0">{$text}</p>
</div>
HTML;
    }
    return $html;
}

function renderStats(): string
{
    $html = '';
    foreach (STATS as [$icon, $color, $count, $label]) {
        $html .= <<<HTML
<div class="col-md-3 col-6 mb-3">
    <div class="stat-card text-center p-3 rounded-3 bg-{$color} bg-opacity-10">
        <i class="bi {$icon} fs-1 text-{$color} mb-2"></i>
        <h3 class="fw-bold mb-1">{$count}</h3>
        <p class="text-muted small mb-0">{$label}</p>
    </div>
</div>
HTML;
    }
    return $html;
}

// ── Вывод ─────────────────────────────────────────────────
stdhead($lang->links['head']);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/links.css">

<div class="container py-4" style="font-family: 'Inter', sans-serif;">

    <!-- Header -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold gradient-text mb-3">
            <i class="bi bi-collection-play-fill me-2"></i>Torrent Clients Collection
        </h1>
        <p class="text-muted lead">Comprehensive directory of BitTorrent clients for every platform and need</p>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <?= renderStats() ?>
    </div>

    <!-- Main Table -->
    <div class="card glass-card border-0 mb-4">
        <div class="card-header gradient-bg text-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-list-columns-reverse fs-4 me-3"></i>
                    <h3 class="mb-0 fw-bold">Complete Clients List</h3>
                </div>
                <span class="badge bg-light text-dark fs-6">70+ Clients</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50"  class="text-center"><i class="bi bi-star"></i></th>
                            <th>Client Name</th>
                            <th width="100" class="text-center">Platform</th>
                            <th>Description</th>
                            <th width="120" class="text-center">Status</th>
                            <th width="100" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?= renderAllRows() ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recommendations & Guide -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card glass-card border-0 h-100">
                <div class="card-header gradient-bg-2 text-white py-3">
                    <h4 class="mb-0 fw-bold">
                        <i class="bi bi-bar-chart me-2"></i>Top Recommendations
                    </h4>
                </div>
                <div class="card-body">
                    <?= renderRecommendations() ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card glass-card border-0 h-100">
                <div class="card-header gradient-bg-3 text-white py-3">
                    <h4 class="mb-0 fw-bold">
                        <i class="bi bi-info-circle me-2"></i>Choosing Guide
                    </h4>
                </div>
                <div class="card-body">
                    <?= renderGuide() ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center mt-5 pt-4 border-top">
        <p class="text-muted small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Always download from official sources. Avoid modified clients from untrusted sites.
        </p>
        <p class="text-muted small">
            Last Updated: <?= date('F Y') ?> | Total Clients Listed: 70+
        </p>
    </div>

</div>

<?php stdfoot(); ?>