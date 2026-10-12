<?php
declare(strict_types=1);

define('L_VERSION', '0.2');
require_once 'global.php';
gzip();


$lang->load('links');

// ── Данные ────────────────────────────────────────────────

const STATS = [
    ['bi-windows', 'primary', '24+', 'stat_windows'],
    ['bi-ubuntu',  'success', '18+', 'stat_linux'],
    ['bi-apple',   'info',    '12+', 'stat_mac'],
    ['bi-phone',   'warning', '15+', 'stat_mobile'],
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
                'subtitle' => 'sub_open_source',
                'platform' => ['bi-windows', 'bi-ubuntu', 'bi-apple'],
                'desc'     => 'desc_qbittorrent',
                'status'   => ['st_active', 'success'],
                'btn'      => ['btn_download', 'primary', 'https://www.qbittorrent.org/'],
                'highlight'=> true,
            ],
            [
                'star'     => 'bi-star-fill text-warning',
                'name'     => 'Deluge',
                'subtitle' => 'sub_thin_client',
                'platform' => ['bi-ubuntu', 'bi-windows', 'bi-apple'],
                'desc'     => 'desc_deluge',
                'status'   => ['st_active', 'success'],
                'btn'      => ['btn_download', 'success', 'https://deluge-torrent.org/'],
            ],
            [
                'star'     => 'bi-star-fill text-warning',
                'name'     => 'Transmission',
                'subtitle' => 'sub_minimalistic',
                'platform' => ['bi-apple', 'bi-ubuntu'],
                'desc'     => 'desc_transmission',
                'status'   => ['st_active', 'success'],
                'btn'      => ['btn_download', 'info', 'https://transmissionbt.com/'],
            ],
        ],
    ],

    'windows' => [
        'label'   => ['bi-windows', 'grp_windows'],
        'clients' => [
            [
                'star'  => 'bi-lightning text-warning',
                'name'  => 'µTorrent',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_utorrent',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'warning', 'https://www.utorrent.com/'],
            ],
            [
                'star'  => 'bi-bell text-info',
                'name'  => 'BitComet',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_bitcomet',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'info', 'https://www.bitcomet.com/'],
            ],
            [
                'star'  => 'bi-flower1 text-success',
                'name'  => 'Vuze (Azureus)',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_vuze',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'success', 'https://www.vuze.com/'],
            ],
            [
                'star'  => 'bi-lightning-charge text-danger',
                'name'  => 'Tixati',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_tixati',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'danger', 'https://www.tixati.com/'],
            ],
            [
                'star'  => 'bi-hurricane text-primary',
                'name'  => 'BitTorrent Classic',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_bittorrent',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'primary', 'https://www.bittorrent.com/'],
            ],
            [
                'star'  => 'bi-bucket text-warning',
                'name'  => 'FrostWire',
                'platform' => ['bi-windows', 'bi-apple', 'bi-ubuntu'],
                'desc'  => 'desc_frostwire',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'warning', 'https://www.frostwire.com/'],
            ],
            [
                'star'  => 'bi-box text-info',
                'name'  => 'Halite',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_halite',
                'status'=> ['st_legacy', 'secondary'],
                'btn'   => ['btn_archive', 'secondary', 'https://sourceforge.net/projects/halite/'],
            ],
        ],
    ],

    'linux' => [
        'label'   => ['bi-ubuntu', 'grp_linux'],
        'clients' => [
            [
                'star'  => 'bi-terminal text-success',
                'name'  => 'rTorrent',
                'platform' => ['bi-ubuntu'],
                'desc'  => 'desc_rtorrent',
                'status'=> ['st_active', 'success'],
                'btn'   => ['GitHub', 'success', 'https://github.com/rakshasa/rtorrent'],
            ],
            [
                'star'  => 'bi-boxes text-info',
                'name'  => 'KTorrent',
                'platform' => ['bi-ubuntu'],
                'desc'  => 'desc_ktorrent',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'info', 'https://apps.kde.org/ktorrent/'],
            ],
            [
                'star'  => 'bi-gear text-warning',
                'name'  => 'Transmission-Qt',
                'platform' => ['bi-ubuntu'],
                'desc'  => 'desc_transmission_qt',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'warning', 'https://transmissionbt.com/'],
            ],
            [
                'star'  => 'bi-braces text-primary',
                'name'  => 'aria2',
                'platform' => ['bi-ubuntu'],
                'desc'  => 'desc_aria2',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'primary', 'https://aria2.github.io/'],
            ],
            [
                'star'  => 'bi-fan text-danger',
                'name'  => 'Tribler',
                'platform' => ['bi-ubuntu', 'bi-windows'],
                'desc'  => 'desc_tribler',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'danger', 'https://www.tribler.org/'],
            ],
        ],
    ],

    'macos' => [
        'label'   => ['bi-apple', 'grp_macos'],
        'clients' => [
            [
                'star'  => 'bi-app text-info',
                'name'  => 'Folx',
                'platform' => ['bi-apple'],
                'desc'  => 'desc_folx',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'info', 'https://mac.eltima.com/folx.html'],
            ],
            [
                'star'  => 'bi-rocket-takeoff text-warning',
                'name'  => 'BitRocket',
                'platform' => ['bi-apple'],
                'desc'  => 'desc_bitrocket',
                'status'=> ['st_limited', 'warning'],
                'btn'   => ['btn_download', 'warning', 'https://bitrocket.org/'],
            ],
            [
                'star'  => 'bi-tomato text-danger',
                'name'  => 'Tomato Torrent',
                'platform' => ['bi-apple'],
                'desc'  => 'desc_tomato',
                'status'=> ['st_legacy', 'secondary'],
                'btn'   => ['GitHub', 'secondary', 'https://github.com/grahamgilbert/tomato'],
            ],
            [
                'star'  => 'bi-cloud-arrow-down text-primary',
                'name'  => 'WebTorrent Desktop',
                'platform' => ['bi-apple', 'bi-windows', 'bi-ubuntu'],
                'desc'  => 'desc_webtorrent',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'primary', 'https://webtorrent.io/desktop/'],
            ],
        ],
    ],

    'mobile' => [
        'label'   => ['bi-phone', 'grp_mobile'],
        'clients' => [
            [
                'star'  => 'bi-android2 text-success',
                'name'  => 'Flud',
                'platform' => ['bi-android2'],
                'desc'  => 'desc_flud',
                'status'=> ['st_active', 'success'],
                'btn'   => ['Play Store', 'success', 'https://play.google.com/store/apps/details?id=com.delphicoder.flud'],
            ],
            [
                'star'  => 'bi-apple text-dark',
                'name'  => 'iTransmission',
                'platform' => ['bi-apple'],
                'desc'  => 'desc_itransmission',
                'status'=> ['st_jailbreak', 'warning'],
                'btn'   => ['Cydia', 'dark', 'https://cydia.saurik.com/package/itransmission/'],
            ],
            [
                'star'  => 'bi-android2 text-success',
                'name'  => 'LibreTorrent',
                'platform' => ['bi-android2'],
                'desc'  => 'desc_libretorrent',
                'status'=> ['st_active', 'success'],
                'btn'   => ['F-Droid', 'success', 'https://f-droid.org/packages/org.proninyaroslav.libretorrent/'],
            ],
            [
                'star'  => 'bi-android2 text-success',
                'name'  => 'FrostWire Mobile',
                'platform' => ['bi-android2', 'bi-apple'],
                'desc'  => 'desc_frostwire_mobile',
                'status'=> ['st_active', 'success'],
                'btn'   => ['btn_download', 'warning', 'https://www.frostwire.com/mobile/'],
            ],
        ],
    ],

    'legacy' => [
        'label'   => ['bi-archive', 'grp_legacy'],
        'clients' => [
            [
                'star'  => 'bi-hdd text-secondary',
                'name'  => 'BitTornado',
                'platform' => ['bi-windows', 'bi-ubuntu'],
                'desc'  => 'desc_bittornado',
                'status'=> ['st_legacy', 'secondary'],
                'btn'   => ['btn_archive', 'secondary', 'http://bittornado.com/'],
            ],
            [
                'star'  => 'bi-lightbulb text-warning',
                'name'  => 'ABC (Yet Another BitTorrent Client)',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_abc',
                'status'=> ['st_legacy', 'secondary'],
                'btn'   => ['btn_archive', 'secondary', 'http://pingpong-abc.sourceforge.net/'],
            ],
            [
                'star'  => 'bi-browser-chrome text-info',
                'name'  => 'Shareaza',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_shareaza',
                'status'=> ['st_legacy', 'secondary'],
                'btn'   => ['btn_archive', 'secondary', 'http://www.shareaza.com/'],
            ],
            [
                'star'  => 'bi-tools text-primary',
                'name'  => 'MakeTorrent',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_maketorrent',
                'status'=> ['st_legacy', 'secondary'],
                'btn'   => ['btn_archive', 'secondary', 'http://krypt.dyndns.org:81/torrent/maketorrent/'],
            ],
            [
                'star'  => 'bi-lightning-charge text-danger',
                'name'  => 'Burst!',
                'platform' => ['bi-windows'],
                'desc'  => 'desc_burst',
                'status'=> ['st_legacy', 'secondary'],
                'btn'   => ['btn_archive', 'secondary', 'http://krypt.dyndns.org:81/torrent/'],
            ],
            [
                'star'  => 'bi-python text-warning',
                'name'  => 'G3 Torrent',
                'platform' => ['bi-windows', 'bi-ubuntu'],
                'desc'  => 'desc_g3torrent',
                'status'=> ['st_legacy', 'secondary'],
                'btn'   => ['btn_archive', 'secondary', 'http://g3torrent.sourceforge.net/'],
            ],
        ],
    ],
];

const RECOMMENDATIONS = [
    [
        'border' => 'primary',
        'name'   => 'qBittorrent',
        'badge'  => ['rec_badge_first', 'primary'],
        'desc'   => 'rec_desc_qbittorrent',
        'checks' => ['chk_no_ads', 'chk_open_source', 'chk_all_platforms'],
    ],
    [
        'border' => 'success',
        'name'   => 'Deluge',
        'badge'  => ['rec_badge_light', 'success'],
        'desc'   => 'rec_desc_deluge',
    ],
    [
        'border' => 'warning',
        'name'   => 'Transmission',
        'badge'  => ['rec_badge_simple', 'warning'],
        'desc'   => 'rec_desc_transmission',
    ],
];

const GUIDE_ITEMS = [
    ['bi-windows text-primary', 'guide_windows_title', 'guide_windows'],
    ['bi-apple text-dark',      'guide_macos_title',   'guide_macos'],
    ['bi-ubuntu text-success',  'guide_linux_title',   'guide_linux'],
    ['bi-shield-check text-info','guide_privacy_title', 'guide_privacy'],
];

// ── Рендер-функции ────────────────────────────────────────

/**
 * Text from the links lang, escaped for HTML. Values in the constants above are
 * lang keys; anything that is not a key (client names, "GitHub", "Play Store",
 * "F-Droid", "Cydia") is printed as it is.
 */
function links_t(string $key): string
{
    global $lang;
    return htmlspecialchars((string)($lang->links[$key] ?? $key), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

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
        ? '<div class="small text-muted">' . links_t($c['subtitle']) . '</div>'
        : '';
    $name        = htmlspecialchars($c['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $desc        = links_t($c['desc']);
    $statusLabel = links_t($statusLabel);
    $btnLabel    = links_t($btnLabel);

    return <<<HTML
<tr class="{$highlight}">
    <td class="text-center"><i class="bi {$c['star']}"></i></td>
    <td>
        <strong>{$name}</strong>
        {$subtitle}
    </td>
    <td class="text-center">{$platforms}</td>
    <td>{$desc}</td>
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
    $text = links_t($text);
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
        $badgeLabel = links_t($badgeLabel);
        $rName      = htmlspecialchars($r['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rDesc      = links_t($r['desc']);
        $checks = '';
        if (!empty($r['checks'])) {
            $checks = '<div class="d-flex flex-wrap gap-3 mt-2">'
                . implode('', array_map(
                    fn($c) => '<span><i class="bi bi-check-circle text-success me-1"></i>' . links_t($c) . '</span>',
                    $r['checks']
                ))
                . '</div>';
        }
        $html .= <<<HTML
<div class="recommendation-item mb-3 p-3 border-start border-4 border-{$r['border']}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="fw-bold mb-0">{$rName}</h5>
        <span class="badge bg-{$badgeColor}">{$badgeLabel}</span>
    </div>
    <p class="text-muted small mb-0">{$rDesc}</p>
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
        $title = links_t($title);
        $text  = links_t($text);
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
        $label = links_t($label);
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
            <i class="bi bi-collection-play-fill me-2"></i><?= links_t('pane_title') ?>
        </h1>
        <p class="text-muted lead"><?= links_t('pane_subtitle') ?></p>
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
                    <h3 class="mb-0 fw-bold"><?= links_t('sec_clients') ?></h3>
                </div>
                <span class="badge bg-light text-dark fs-6"><?= links_t('lbl_clients_badge') ?></span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50"  class="text-center"><i class="bi bi-star"></i></th>
                            <th><?= links_t('lbl_col_name') ?></th>
                            <th width="100" class="text-center"><?= links_t('lbl_col_platform') ?></th>
                            <th><?= links_t('lbl_col_desc') ?></th>
                            <th width="120" class="text-center"><?= links_t('lbl_col_status') ?></th>
                            <th width="100" class="text-center"><?= links_t('lbl_col_action') ?></th>
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
                        <i class="bi bi-bar-chart me-2"></i><?= links_t('sec_recommendations') ?>
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
                        <i class="bi bi-info-circle me-2"></i><?= links_t('sec_guide') ?>
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
            <?= links_t('foot_warning') ?>
        </p>
        <p class="text-muted small">
            <?= htmlspecialchars(sprintf($lang->links['foot_updated'], date($lang->links['fmt_month'])), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
        </p>
    </div>

</div>

<?php stdfoot(); ?>