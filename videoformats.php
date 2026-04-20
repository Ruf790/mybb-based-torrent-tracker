<?php
declare(strict_types=1);

require_once __DIR__ . '/global.php';
gzip();

// ── Данные ────────────────────────────────────────────────

const FORMATS = [
    'theater' => [
        [
            'id' => 'cam', 'title' => 'CAM',
            'icon' => 'fas fa-video-camera', 'color' => 'danger',
            'header_bg' => 'bg-danger bg-opacity-10',
            'badges' => [['Lowest','danger'],['Legacy','dark']],
            'desc' => 'Theater recording with digital camera. Poor quality.',
            'specs' => ['Bitrate'=>'500-1000 kbps','Audio'=>'Camera mic','Resolution'=>'480p-720p'],
            'footer' => 'Avoid unless absolutely necessary',
        ],
        [
            'id' => 'ts', 'title' => 'TELESYNC (TS)',
            'icon' => 'fas fa-headphones', 'color' => 'warning',
            'header_bg' => 'bg-warning bg-opacity-10',
            'badges' => [['Low','warning']],
            'desc' => 'CAM with external audio source. Quality varies.',
            'specs' => ['Bitrate'=>'800-1500 kbps','Audio'=>'Theater audio jack','Resolution'=>'576p-720p'],
        ],
        [
            'id' => 'tc', 'title' => 'TELECINE (TC)',
            'icon' => 'fas fa-film', 'color' => 'info',
            'header_bg' => 'bg-info bg-opacity-10',
            'badges' => [['Medium','info']],
            'desc' => 'Professional film reel transfer. Uncommon but good quality.',
            'specs' => ['Bitrate'=>'2000-4000 kbps','Audio'=>'Excellent','Resolution'=>'Up to 1080p'],
        ],
        [
            'id' => 'dcp', 'title' => 'DCP Rip',
            'icon' => 'fas fa-projector', 'color' => 'success',
            'header_bg' => 'bg-success bg-opacity-10',
            'badges' => [['High','success'],['New','primary']],
            'desc' => 'Rip from Digital Cinema Package. Near-perfect theater quality.',
            'specs' => ['Bitrate'=>'50-250 Mbps','Audio'=>'5.1/7.1 Lossless','Resolution'=>'2K/4K'],
            'footer' => 'Professional theater source',
            'footer_icon' => 'fas fa-star',
            'footer_class' => 'text-success',
        ],
    ],
    'digital' => [
        [
            'id' => 'web-dl', 'title' => 'WEB-DL',
            'icon' => 'fas fa-download', 'color' => 'primary',
            'header_bg' => 'bg-primary bg-opacity-10',
            'badges' => [['Excellent','primary'],['Standard','info']],
            'desc' => 'Downloaded from streaming services (iTunes, Amazon, etc.).',
            'specs' => ['Sources'=>'iTunes, Amazon, Netflix, Hulu','Quality'=>'Identical to streaming','DRM'=>'Removed'],
            'variants' => ['WEB-DL 1080p','WEB-DL 2160p','HDR'],
        ],
        [
            'id' => 'webrip', 'title' => 'WEBRip',
            'icon' => 'fas fa-cloud-download-alt', 'color' => 'secondary',
            'header_bg' => 'bg-secondary bg-opacity-10',
            'badges' => [['Good','secondary']],
            'desc' => 'Captured/re-encoded from streaming. Slightly lower quality.',
            'specs' => ['Method'=>'Screen capture/re-encode','Quality'=>'Slight loss','Common'=>'Netflix, Disney+, HBO Max'],
        ],
        [
            'id' => 'amzn', 'title' => 'AMZN WEB-DL',
            'icon' => 'fab fa-amazon', 'color' => 'success',
            'header_bg' => 'bg-success bg-opacity-10',
            'badges' => [['Excellent','success'],['New','primary']],
            'desc' => 'Amazon Prime Video download. Often highest bitrate.',
            'specs' => ['Bitrate'=>'10-20 Mbps (1080p)','Audio'=>'DD+ 5.1, sometimes Atmos','Features'=>'HDR10, Dolby Vision'],
        ],
        [
            'id' => 'netflix', 'title' => 'NF WEB-DL',
            'icon' => 'fab fa-netflix', 'color' => 'danger',
            'header_bg' => 'bg-danger bg-opacity-10',
            'badges' => [['Excellent','success'],['New','primary']],
            'desc' => 'Netflix download. Known for excellent encoding.',
            'specs' => ['Bitrate'=>'8-16 Mbps (1080p)','Codec'=>'x264/x265','Features'=>'4K, HDR, Atmos'],
        ],
        [
            'id' => 'disney', 'title' => 'DSNP WEB-DL',
            'icon' => 'fab fa-disney', 'color' => 'info',
            'header_bg' => 'bg-info bg-opacity-10',
            'badges' => [['Excellent','success'],['New','primary']],
            'desc' => 'Disney+ download. High quality with IMAX Enhanced.',
            'specs' => ['Features'=>'IMAX Enhanced ratio','Audio'=>'Atmos common','HDR'=>'HDR10, Dolby Vision'],
        ],
        [
            'id' => 'hbo', 'title' => 'HMAX WEB-DL',
            'icon' => 'fas fa-crown', 'color' => 'purple',
            'header_bg' => 'bg-purple bg-opacity-10',
            'badges' => [['Excellent','success'],['New','primary']],
            'desc' => 'HBO Max download. High bitrate 4K releases.',
            'specs' => ['Bitrate'=>'15-25 Mbps (4K)','Quality'=>'Reference grade','Features'=>'4K HDR'],
        ],
    ],
    'disc' => [
        [
            'id' => 'dvdrip', 'title' => 'DVDRip',
            'icon' => 'fas fa-compact-disc', 'color' => 'warning',
            'header_bg' => 'bg-warning bg-opacity-10',
            'badges' => [['Standard','warning']],
            'desc' => 'Rip from retail DVD. Standard definition.',
            'specs' => ['Resolution'=>'480p/576p','Bitrate'=>'1500-3000 kbps','Aspect'=>'4:3 or 16:9'],
        ],
        [
            'id' => 'bdrip', 'title' => 'BDRip / BRRip',
            'icon' => 'fas fa-compact-disc', 'color' => 'primary',
            'header_bg' => 'bg-primary bg-opacity-10',
            'badges' => [['High','primary']],
            'desc' => 'Rip from Blu-ray disc. High definition.',
            'specs' => ['Resolution'=>'720p/1080p','Bitrate'=>'4000-10000 kbps','Codecs'=>'x264, x265'],
        ],
        [
            'id' => 'remux', 'title' => 'REMUX',
            'icon' => 'fas fa-database', 'color' => 'success',
            'header_bg' => 'bg-success bg-opacity-10',
            'badges' => [['Perfect','success'],['Lossless','info']],
            'desc' => 'Direct stream copy from Blu-ray. No re-encoding.',
            'specs' => ['Quality'=>'1:1 with source','Size'=>'20-80GB (1080p)','Audio'=>'Lossless (TrueHD, DTS-HD MA)'],
            'alert' => 'Best possible quality',
        ],
        [
            'id' => 'uhd', 'title' => 'UHD REMUX',
            'icon' => 'fas fa-tv', 'color' => 'purple',
            'header_bg' => 'bg-purple bg-opacity-10',
            'badges' => [['Ultimate','purple'],['New','primary']],
            'desc' => '4K Ultra HD Blu-ray remux. Maximum quality.',
            'specs' => ['Resolution'=>'2160p (4K)','Size'=>'50-100GB','Features'=>'HDR10, Dolby Vision, Atmos'],
        ],
        [
            'id' => 'bd100', 'title' => 'BD66 / BD100',
            'icon' => 'fas fa-layer-group', 'color' => 'dark',
            'header_bg' => 'bg-dark bg-opacity-10',
            'badges' => [['Full Disc','dark'],['New','primary']],
            'desc' => 'Complete Blu-ray disc image with menus and extras.',
            'specs' => ['Format'=>'ISO or BDMV folder','Size'=>'66GB or 100GB','Features'=>'All bonus content'],
        ],
    ],
    'codecs' => [
        [
            'id' => 'x264', 'title' => 'x264 / AVC',
            'icon' => 'fas fa-file-video', 'color' => 'primary',
            'header_bg' => 'bg-primary bg-opacity-10',
            'badges' => [['Standard','primary']],
            'desc' => 'H.264 video codec. Most common for 1080p.',
            'specs' => ['Efficiency'=>'Good','Compatibility'=>'Excellent','Bitrate'=>'Higher than x265'],
        ],
        [
            'id' => 'x265', 'title' => 'x265 / HEVC',
            'icon' => 'fas fa-file-video', 'color' => 'success',
            'header_bg' => 'bg-success bg-opacity-10',
            'badges' => [['Efficient','success'],['New','primary']],
            'desc' => 'H.265 video codec. 50% better compression.',
            'specs' => ['Efficiency'=>'Excellent','4K/HDR'=>'Required','Hardware'=>'Modern devices needed'],
        ],
        [
            'id' => 'av1', 'title' => 'AV1',
            'icon' => 'fas fa-file-video', 'color' => 'info',
            'header_bg' => 'bg-info bg-opacity-10',
            'badges' => [['Future','info'],['New','primary']],
            'desc' => 'Royalty-free codec. 30% better than HEVC.',
            'specs' => ['Royalties'=>'Free','Adoption'=>'YouTube, Netflix','Hardware'=>'Limited support'],
        ],
        [
            'id' => 'vvc', 'title' => 'VVC / H.266',
            'icon' => 'fas fa-file-video', 'color' => 'purple',
            'header_bg' => 'bg-purple bg-opacity-10',
            'badges' => [['Next-gen','purple'],['New','primary']],
            'desc' => 'H.266 codec. 50% better than HEVC.',
            'specs' => ['Efficiency'=>'Best','8K'=>'Designed for','Release'=>'2020+'],
        ],
    ],
];

const HDR_FORMATS = [
    ['id'=>'hdr10',  'css_bg'=>'bg-hdr10',    'label'=>'HDR10',        'title'=>'Standard HDR',        'desc'=>'10-bit color, static metadata'],
    ['id'=>'hdr10+', 'css_bg'=>'bg-hdr10plus','label'=>'HDR10+',       'title'=>'Dynamic HDR',         'desc'=>'Dynamic metadata, Samsung/Amazon'],
    ['id'=>'dolby',  'css_bg'=>'bg-dolby',    'label'=>'Dolby Vision', 'title'=>'Premium HDR',         'desc'=>'12-bit color, frame-by-frame'],
];

const STREAMING_FORMATS = [
    [
        'id' => 'atmos', 'title' => 'Dolby Atmos',
        'icon' => 'fas fa-volume-up', 'color' => 'info',
        'header_bg' => 'bg-info bg-opacity-10',
        'badges' => [['Object-based','info']],
        'desc' => '3D object-based audio with height channels.',
        'specs' => ['Channels'=>'7.1.4+','Format'=>'TrueHD or DD+ with Atmos','Devices'=>'Requires Atmos speakers'],
    ],
    [
        'id' => 'dtsx', 'title' => 'DTS:X',
        'icon' => 'fas fa-wave-square', 'color' => 'warning',
        'header_bg' => 'bg-warning bg-opacity-10',
        'badges' => [['Competitor','warning']],
        'desc' => "DTS's object-based audio format.",
        'specs' => ['Format'=>'DTS-HD MA with X','Features'=>'Backward compatible','Common'=>'Blu-ray discs'],
    ],
    [
        'id' => 'imax', 'title' => 'IMAX Enhanced',
        'icon' => 'fas fa-expand-alt', 'color' => 'danger',
        'header_bg' => 'bg-danger bg-opacity-10',
        'badges' => [['IMAX','danger'],['New','primary']],
        'desc' => 'IMAX theater ratio with enhanced audio.',
        'specs' => ['Aspect'=>'1.90:1 or 1.43:1','Audio'=>'DTS:X optimized','Sources'=>'Disney+, Sony Bravia Core'],
    ],
];

const CONTAINERS = [
    ['id'=>'mkv',  'color'=>'primary', 'label'=>'MKV',  'desc'=>'Matroska - Most common'],
    ['id'=>'mp4',  'color'=>'success', 'label'=>'MP4',  'desc'=>'Universal compatibility'],
    ['id'=>'avi',  'color'=>'warning', 'label'=>'AVI',  'desc'=>'Legacy format'],
    ['id'=>'m2ts', 'color'=>'danger',  'label'=>'M2TS', 'desc'=>'Blu-ray container'],
];

const SCENE_TAGS = [
    'Release Types' => [
        ['PROPER','Fixes errors in a previous release'],
        ['REPACK','Replaces a bad release from same group'],
        ['REAL','Distinguishes from a fake PROPER'],
        ['READNFO','Read the NFO file for notes'],
        ['LIMITED','Limited theatrical release'],
        ['INTERNAL','Group internal release, not for trading'],
        ['RETAIL','Retail copy (vs screener/promo)'],
        ['EXTENDED','Extended cut with additional scenes'],
        ['UNRATED','Unrated version, not for general release'],
        ['DC',"Director's Cut"],
        ['THEATRICAL','Standard theatrical version'],
        ['REMASTERED','Remastered from original source'],
    ],
    'Source Tags' => [
        ['SCREENER','Early promotional copy, watermarks possible'],
        ['DVDSCR','DVD screener copy'],
        ['HDTV','Captured from HD television broadcast'],
        ['PDTV','Pure digital TV capture'],
        ['VODRip','Video On Demand source'],
        ['iTUNES','iTunes digital download'],
    ],
    'Quality Tags' => [
        ['2160p','4K resolution'],
        ['1080p','Full HD'],
        ['1080i','Interlaced Full HD'],
        ['720p','HD Ready'],
        ['576p','PAL DVD resolution'],
        ['480p','NTSC DVD resolution'],
    ],
    'Encoding Tags' => [
        ['10bit','10-bit color depth (better gradients)'],
        ['12bit','12-bit for HDR content'],
        ['SDR','Standard Dynamic Range'],
        ['HDR','High Dynamic Range'],
        ['HDR10','HDR with static metadata'],
        ['DV','Dolby Vision HDR'],
        ['HYBRID','Mixed source (e.g. WEB video + BD audio)'],
    ],
    'Audio Tags' => [
        ['AAC','Advanced Audio Coding'],
        ['AC3','Dolby Digital 5.1'],
        ['DD+','Dolby Digital Plus'],
        ['DTS','DTS 5.1'],
        ['DTS-HD','DTS-HD Master Audio (lossless)'],
        ['TrueHD','Dolby TrueHD lossless'],
        ['FLAC','Free Lossless Audio Codec'],
        ['Atmos','Dolby Atmos object audio'],
        ['DTS:X','DTS object-based audio'],
        ['MP3','MPEG-1 Audio Layer III'],
    ],
];

const COMPARISON_ROWS = [
    ['CAM',       '1/10',   'danger', '1-2 GB',   '500-1000 kbps', 'Mono',        'Theater day',      'text-danger',  'Avoid'],
    ['TS',        '3/10',   'warning','1-3 GB',   '800-1500 kbps', 'Stereo',      'Theater',          'text-warning', 'Low quality'],
    ['WEB-DL',    '9/10',   'success','3-8 GB',   '5000-15000 kbps','5.1 DD+',    'Digital release',  'text-success', 'Excellent'],
    ['BDRip',     '8/10',   'primary','8-15 GB',  '8000-12000 kbps','5.1/7.1',    'Blu-ray release',  'text-primary', 'Very Good'],
    ['REMUX',     '10/10',  'purple', '20-40 GB', '20-35 Mbps',    'Lossless',    'Blu-ray release',  'text-purple',  'Perfect'],
    ['4K WEB-DL', '9.5/10', 'info',   '15-30 GB', '15-25 Mbps',    'Atmos/TrueHD','Digital 4K release','text-info',   'Best Value'],
    ['UHD REMUX', '10/10',  'dark',   '50-80 GB', '50-100 Mbps',   'Atmos/DTS:X', 'UHD Blu-ray',      'text-dark',    'Ultimate'],
];

// ── Рендер-функции ────────────────────────────────────────

function renderBadges(array $badges): string
{
    return implode(' ', array_map(
        fn($b) => sprintf('<span class="badge bg-%s">%s</span>', htmlspecialchars($b[1]), htmlspecialchars($b[0])),
        $badges
    ));
}

function renderSpecs(array $specs): string
{
    $rows = '';
    foreach ($specs as $k => $v) {
        $rows .= sprintf('<small><strong>%s:</strong> %s</small><br>', htmlspecialchars($k), htmlspecialchars($v));
    }
    return '<div class="tech-specs">' . $rows . '</div>';
}

function renderFormatCard(array $f): string
{
    $badges   = renderBadges($f['badges'] ?? []);
    $specs    = renderSpecs($f['specs']   ?? []);

    // footer
    $footer = '';
    if (!empty($f['footer'])) {
        $fi     = isset($f['footer_icon']) ? '<i class="' . $f['footer_icon'] . '"></i> ' : '';
        $fc     = $f['footer_class'] ?? 'text-muted';
        $footer = '<div class="card-footer bg-transparent"><small class="' . $fc . '">' . $fi . htmlspecialchars($f['footer']) . '</small></div>';
    }

    // alert
    $alert = !empty($f['alert'])
        ? '<div class="alert alert-info mt-2 p-2"><small><i class="fas fa-info-circle"></i> '
          . htmlspecialchars($f['alert']) . '</small></div>'
        : '';

    // variants
    $variants = '';
    if (!empty($f['variants'])) {
        $pills = implode(' ', array_map(
            fn($v) => '<span class="badge bg-light text-dark">' . htmlspecialchars($v) . '</span>',
            $f['variants']
        ));
        $variants = '<div class="variants mt-2"><small><strong>Variants:</strong> ' . $pills . '</small></div>';
    }

    $hBg    = htmlspecialchars($f['header_bg']);
    $icon   = htmlspecialchars($f['icon']);
    $color  = htmlspecialchars($f['color']);
    $id     = htmlspecialchars($f['id']);
    $title  = htmlspecialchars($f['title']);
    $desc   = htmlspecialchars($f['desc']);

    return <<<HTML
<div class="col-12 col-lg-6" data-format="{$id}">
    <div class="format-card card border-0 shadow-sm h-100">
        <div class="card-header {$hBg}">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="{$icon} text-{$color} me-2"></i>{$title}
                </h4>
                <div>{$badges}</div>
            </div>
        </div>
        <div class="card-body">
            <p class="card-text">{$desc}</p>
            {$specs}{$variants}{$alert}
        </div>
        {$footer}
    </div>
</div>
HTML;
}

function renderTabCards(array $formats): string
{
    return implode('', array_map('renderFormatCard', $formats));
}

stdhead("Video Formats Guide 2024");
?>


<style>
.video-formats-container .format-card {
    transition: transform .15s ease, box-shadow .15s ease;
}
.video-formats-container .format-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.12) !important;
}

.hdr-card {
    transition: transform .15s ease;
    background: var(--bs-body-bg);
}
.hdr-card:hover { transform: translateY(-2px); }

.tech-specs { font-size: .85rem; color: #555; }

/* Скролл в мобильных табах */
@media (max-width: 768px) {
    .nav-tabs { flex-wrap: nowrap; overflow-x: auto; }
    .nav-tabs .nav-link { white-space: nowrap; }
}
</style>


<!-- Main Content -->
<main class="video-formats-container">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">
                    <i class="fas fa-high-definition me-3"></i>
                    Video Formats Encyclopedia 2024
                </h1>
                <p class="hero-subtitle">
                    Ultimate guide to video formats, codecs, containers, and scene terminology.
                    From CAM to 4K HDR WEB-DL and everything in between.
                </p>
                <div class="hero-badge">
                    <span class="badge bg-primary">2024 Edition</span>
                    <span class="badge bg-success">140+ Formats</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Navigation Tabs -->
    <section class="navigation-section">
        <div class="container">
            <div class="formats-nav">
                <nav>
                    <div class="nav nav-tabs" id="formatsTab" role="tablist">
                        <button class="nav-link active" id="theater-tab" data-bs-toggle="tab"
                                data-bs-target="#theater" type="button" role="tab">
                            <i class="fas fa-film me-2"></i>Theater Rips
                        </button>
                        <button class="nav-link" id="digital-tab" data-bs-toggle="tab"
                                data-bs-target="#digital" type="button" role="tab">
                            <i class="fas fa-globe me-2"></i>Digital Sources
                        </button>
                        <button class="nav-link" id="disc-tab" data-bs-toggle="tab"
                                data-bs-target="#disc" type="button" role="tab">
                            <i class="fas fa-compact-disc me-2"></i>Disc Rips
                        </button>
                        <button class="nav-link" id="streaming-tab" data-bs-toggle="tab"
                                data-bs-target="#streaming" type="button" role="tab">
                            <i class="fas fa-stream me-2"></i>Streaming
                        </button>
                        <button class="nav-link" id="codecs-tab" data-bs-toggle="tab"
                                data-bs-target="#codecs" type="button" role="tab">
                            <i class="fas fa-file-video me-2"></i>Codecs &amp; Containers
                        </button>
                        <button class="nav-link" id="scene-tab" data-bs-toggle="tab"
                                data-bs-target="#scene" type="button" role="tab">
                            <i class="fas fa-tags me-2"></i>Scene Tags
                        </button>
                    </div>
                </nav>
            </div>
        </div>
    </section>

    <!-- Search Box -->
    <section class="search-section">
        <div class="container">
            <div class="search-box">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-primary text-white">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control" id="formatSearch"
                           placeholder="Search 140+ formats (CAM, WEB-DL, x265, HDR, etc.)">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-tags mt-3">
                    <small class="text-muted">Trending: </small>
                    <?php
                    $trendTags = [
                        'web-dl' => 'digital',  'hdr'    => 'streaming',
                        'dolby'  => 'streaming', '4k'    => 'disc',
                        'remux'  => 'disc',      'x265'  => 'codecs',
                        'atmos'  => 'streaming',
                    ];
                    foreach ($trendTags as $tag => $tabTarget):
                    ?>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link"
                          data-tag="<?= htmlspecialchars($tag) ?>"
                          data-tab="<?= htmlspecialchars($tabTarget) ?>">
                        <?= htmlspecialchars(strtoupper($tag)) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Content Tabs -->
    <section class="content-section">
        <div class="container">
            <div class="tab-content" id="formatsTabContent">

                <!-- Theater Rips Tab -->
                <div class="tab-pane fade show active" id="theater" role="tabpanel">
                    <div class="row g-4">
                        <?= renderTabCards(FORMATS['theater']) ?>
                    </div>
                </div>

                <!-- Digital Sources Tab -->
                <div class="tab-pane fade" id="digital" role="tabpanel">
                    <div class="row g-4">
                        <?= renderTabCards(FORMATS['digital']) ?>
                    </div>
                </div>

                <!-- Disc Rips Tab -->
                <div class="tab-pane fade" id="disc" role="tabpanel">
                    <div class="row g-4">
                        <?= renderTabCards(FORMATS['disc']) ?>
                    </div>
                </div>

                <!-- Streaming Tab -->
                <div class="tab-pane fade" id="streaming" role="tabpanel">
                    <div class="row g-4">

                        <!-- HDR Formats -->
                        <div class="col-12">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-gradient-hdr">
                                    <h4 class="card-title text-white mb-0">
                                        <i class="fas fa-sun text-warning me-2"></i>HDR Formats
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-4">
                                        <?php foreach (HDR_FORMATS as $h): ?>
                                        <div class="col-md-4" data-format="<?= htmlspecialchars($h['id']) ?>">
                                            <div class="hdr-card">
                                                <div class="hdr-badge <?= htmlspecialchars($h['css_bg']) ?>">
                                                    <?= htmlspecialchars($h['label']) ?>
                                                </div>
                                                <p class="mb-1"><strong><?= htmlspecialchars($h['title']) ?></strong></p>
                                                <small><?= htmlspecialchars($h['desc']) ?></small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?= renderTabCards(STREAMING_FORMATS) ?>

                    </div>
                </div>

                <!-- Codecs Tab -->
                <div class="tab-pane fade" id="codecs" role="tabpanel">
                    <div class="row g-4">

                        <?= renderTabCards(FORMATS['codecs']) ?>

                        <!-- Containers -->
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-dark text-white">
                                    <h4 class="card-title mb-0">Containers</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <?php foreach (CONTAINERS as $c): ?>
                                        <div class="col-md-3" data-format="<?= htmlspecialchars($c['id']) ?>">
                                            <div class="container-card text-center p-3 border rounded">
                                                <div class="container-icon mb-2">
                                                    <i class="fas fa-cube fa-2x text-<?= htmlspecialchars($c['color']) ?>"></i>
                                                </div>
                                                <strong><?= htmlspecialchars($c['label']) ?></strong><br>
                                                <small><?= htmlspecialchars($c['desc']) ?></small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Scene Tags Tab -->
                <div class="tab-pane fade" id="scene" role="tabpanel">
                    <div class="row g-4">
                        <?php foreach (SCENE_TAGS as $group => $tags): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-dark text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-tag me-2"></i><?= htmlspecialchars($group) ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($tags as [$tag, $desc]): ?>
                                    <div class="d-flex align-items-start mb-2">
                                        <span class="badge bg-dark me-2 mt-1"
                                              style="min-width:80px;font-size:.75em">
                                            <?= htmlspecialchars($tag) ?>
                                        </span>
                                        <small class="text-muted"><?= htmlspecialchars($desc) ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Quick Comparison Table -->
    <section class="comparison-section">
        <div class="container">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-compare">
                    <h3 class="card-title text-white mb-0">
                        <i class="fas fa-balance-scale me-2"></i>Format Comparison 2024
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Format</th>
                                    <th>Quality</th>
                                    <th>Size (1080p)</th>
                                    <th>Bitrate</th>
                                    <th>Audio</th>
                                    <th>Release Time</th>
                                    <th>Recommendation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (COMPARISON_ROWS as [$fmt, $q, $qc, $size, $bitrate, $audio, $time, $rc, $rec]): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($fmt) ?></strong></td>
                                    <td><span class="badge bg-<?= htmlspecialchars($qc) ?>"><?= htmlspecialchars($q) ?></span></td>
                                    <td><?= htmlspecialchars($size) ?></td>
                                    <td><?= htmlspecialchars($bitrate) ?></td>
                                    <td><?= htmlspecialchars($audio) ?></td>
                                    <td><?= htmlspecialchars($time) ?></td>
                                    <td class="<?= htmlspecialchars($rc) ?>"><?= htmlspecialchars($rec) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Info -->
    <section class="info-section">
        <div class="container">
            <div class="row g-4">
                <?php
                $infoCards = [
                    ['fa-bolt fa-3x text-warning',      'Release Timeline',       'CAM → WEB-DL → BDRip → REMUX → UHD REMUX'],
                    ['fa-chart-line fa-3x text-success', 'Quality vs Size',        'WEB-DL offers best quality/size ratio for most users'],
                    ['fa-eye fa-3x text-info',           'Viewing Recommendations','For 4K HDR: OLED TV with Atmos sound system'],
                ];
                foreach ($infoCards as [$icon, $title, $text]):
                ?>
                <div class="col-md-4">
                    <div class="info-card card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="info-icon mb-3">
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            <h5><?= htmlspecialchars($title) ?></h5>
                            <p class="small"><?= htmlspecialchars($text) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</main>

<script src="<?= $BASEURL ?>/scripts/videoformats.js"></script>

<?php stdfoot(); ?>