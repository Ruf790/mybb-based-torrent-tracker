<?php
declare(strict_types=1);

require_once __DIR__ . '/global.php';
gzip();

$lang->load('videoformats');

// ── Данные ────────────────────────────────────────────────
// Строки вида 'xxx_yyy' (b_, sl_, sv_, desc_, foot_, alert_, hdr_, cont_, grp_, tag_, cmp_, rec_, info_)
// — ключи из videoformats.lang.php. Всё остальное (битрейты, размеры, названия форматов) выводится как есть.

const FORMATS = [
    'theater' => [
        [
            'id' => 'cam', 'title' => 'CAM',
            'icon' => 'fas fa-video-camera', 'color' => 'danger',
            'header_bg' => 'bg-danger bg-opacity-10',
            'badges' => [['b_lowest','danger'],['b_legacy','dark']],
            'desc' => 'desc_cam',
            'specs' => ['sl_bitrate'=>'500-1000 kbps','sl_audio'=>'sv_camera_mic','sl_resolution'=>'480p-720p'],
            'footer' => 'foot_cam',
        ],
        [
            'id' => 'ts', 'title' => 'TELESYNC (TS)',
            'icon' => 'fas fa-headphones', 'color' => 'warning',
            'header_bg' => 'bg-warning bg-opacity-10',
            'badges' => [['b_low','warning']],
            'desc' => 'desc_ts',
            'specs' => ['sl_bitrate'=>'800-1500 kbps','sl_audio'=>'sv_theater_jack','sl_resolution'=>'576p-720p'],
        ],
        [
            'id' => 'tc', 'title' => 'TELECINE (TC)',
            'icon' => 'fas fa-film', 'color' => 'info',
            'header_bg' => 'bg-info bg-opacity-10',
            'badges' => [['b_medium','info']],
            'desc' => 'desc_tc',
            'specs' => ['sl_bitrate'=>'2000-4000 kbps','sl_audio'=>'sv_excellent','sl_resolution'=>'sv_up_to_1080p'],
        ],
        [
            'id' => 'dcp', 'title' => 'DCP Rip',
            'icon' => 'fas fa-projector', 'color' => 'success',
            'header_bg' => 'bg-success bg-opacity-10',
            'badges' => [['b_high','success'],['b_new','primary']],
            'desc' => 'desc_dcp',
            'specs' => ['sl_bitrate'=>'50-250 Mbps','sl_audio'=>'sv_lossless_51_71','sl_resolution'=>'2K/4K'],
            'footer' => 'foot_dcp',
            'footer_icon' => 'fas fa-star',
            'footer_class' => 'text-success',
        ],
    ],
    'digital' => [
        [
            'id' => 'web-dl', 'title' => 'WEB-DL',
            'icon' => 'fas fa-download', 'color' => 'primary',
            'header_bg' => 'bg-primary bg-opacity-10',
            'badges' => [['b_excellent','primary'],['b_standard','info']],
            'desc' => 'desc_webdl',
            'specs' => ['sl_sources'=>'iTunes, Amazon, Netflix, Hulu','sl_quality'=>'sv_identical_streaming','sl_drm'=>'sv_removed'],
            'variants' => ['WEB-DL 1080p','WEB-DL 2160p','HDR'],
        ],
        [
            'id' => 'webrip', 'title' => 'WEBRip',
            'icon' => 'fas fa-cloud-download-alt', 'color' => 'secondary',
            'header_bg' => 'bg-secondary bg-opacity-10',
            'badges' => [['b_good','secondary']],
            'desc' => 'desc_webrip',
            'specs' => ['sl_method'=>'sv_screen_capture','sl_quality'=>'sv_slight_loss','sl_common'=>'Netflix, Disney+, HBO Max'],
        ],
        [
            'id' => 'amzn', 'title' => 'AMZN WEB-DL',
            'icon' => 'fab fa-amazon', 'color' => 'success',
            'header_bg' => 'bg-success bg-opacity-10',
            'badges' => [['b_excellent','success'],['b_new','primary']],
            'desc' => 'desc_amzn',
            'specs' => ['sl_bitrate'=>'10-20 Mbps (1080p)','sl_audio'=>'sv_ddp_atmos','sl_features'=>'HDR10, Dolby Vision'],
        ],
        [
            'id' => 'netflix', 'title' => 'NF WEB-DL',
            'icon' => 'fab fa-netflix', 'color' => 'danger',
            'header_bg' => 'bg-danger bg-opacity-10',
            'badges' => [['b_excellent','success'],['b_new','primary']],
            'desc' => 'desc_netflix',
            'specs' => ['sl_bitrate'=>'8-16 Mbps (1080p)','sl_codec'=>'x264/x265','sl_features'=>'4K, HDR, Atmos'],
        ],
        [
            'id' => 'disney', 'title' => 'DSNP WEB-DL',
            'icon' => 'fab fa-disney', 'color' => 'info',
            'header_bg' => 'bg-info bg-opacity-10',
            'badges' => [['b_excellent','success'],['b_new','primary']],
            'desc' => 'desc_disney',
            'specs' => ['sl_features'=>'sv_imax_ratio','sl_audio'=>'sv_atmos_common','HDR'=>'HDR10, Dolby Vision'],
        ],
        [
            'id' => 'hbo', 'title' => 'HMAX WEB-DL',
            'icon' => 'fas fa-crown', 'color' => 'purple',
            'header_bg' => 'bg-purple bg-opacity-10',
            'badges' => [['b_excellent','success'],['b_new','primary']],
            'desc' => 'desc_hbo',
            'specs' => ['sl_bitrate'=>'15-25 Mbps (4K)','sl_quality'=>'sv_reference','sl_features'=>'4K HDR'],
        ],
    ],
    'disc' => [
        [
            'id' => 'dvdrip', 'title' => 'DVDRip',
            'icon' => 'fas fa-compact-disc', 'color' => 'warning',
            'header_bg' => 'bg-warning bg-opacity-10',
            'badges' => [['b_standard','warning']],
            'desc' => 'desc_dvdrip',
            'specs' => ['sl_resolution'=>'480p/576p','sl_bitrate'=>'1500-3000 kbps','sl_aspect'=>'sv_aspect_dvd'],
        ],
        [
            'id' => 'bdrip', 'title' => 'BDRip / BRRip',
            'icon' => 'fas fa-compact-disc', 'color' => 'primary',
            'header_bg' => 'bg-primary bg-opacity-10',
            'badges' => [['b_high','primary']],
            'desc' => 'desc_bdrip',
            'specs' => ['sl_resolution'=>'720p/1080p','sl_bitrate'=>'4000-10000 kbps','sl_codecs'=>'x264, x265'],
        ],
        [
            'id' => 'remux', 'title' => 'REMUX',
            'icon' => 'fas fa-database', 'color' => 'success',
            'header_bg' => 'bg-success bg-opacity-10',
            'badges' => [['b_perfect','success'],['b_lossless','info']],
            'desc' => 'desc_remux',
            'specs' => ['sl_quality'=>'sv_one_to_one','sl_size'=>'sv_size_remux','sl_audio'=>'sv_lossless_truehd'],
            'alert' => 'alert_remux',
        ],
        [
            'id' => 'uhd', 'title' => 'UHD REMUX',
            'icon' => 'fas fa-tv', 'color' => 'purple',
            'header_bg' => 'bg-purple bg-opacity-10',
            'badges' => [['b_ultimate','purple'],['b_new','primary']],
            'desc' => 'desc_uhd',
            'specs' => ['sl_resolution'=>'2160p (4K)','sl_size'=>'sv_size_uhd','sl_features'=>'HDR10, Dolby Vision, Atmos'],
        ],
        [
            'id' => 'bd100', 'title' => 'BD66 / BD100',
            'icon' => 'fas fa-layer-group', 'color' => 'dark',
            'header_bg' => 'bg-dark bg-opacity-10',
            'badges' => [['b_full_disc','dark'],['b_new','primary']],
            'desc' => 'desc_bd100',
            'specs' => ['sl_format'=>'sv_iso_bdmv','sl_size'=>'sv_size_bd100','sl_features'=>'sv_bonus'],
        ],
    ],
    'codecs' => [
        [
            'id' => 'x264', 'title' => 'x264 / AVC',
            'icon' => 'fas fa-file-video', 'color' => 'primary',
            'header_bg' => 'bg-primary bg-opacity-10',
            'badges' => [['b_standard','primary']],
            'desc' => 'desc_x264',
            'specs' => ['sl_efficiency'=>'sv_good','sl_compatibility'=>'sv_excellent','sl_bitrate'=>'sv_higher_x265'],
        ],
        [
            'id' => 'x265', 'title' => 'x265 / HEVC',
            'icon' => 'fas fa-file-video', 'color' => 'success',
            'header_bg' => 'bg-success bg-opacity-10',
            'badges' => [['b_efficient','success'],['b_new','primary']],
            'desc' => 'desc_x265',
            'specs' => ['sl_efficiency'=>'sv_excellent','4K/HDR'=>'sv_required','sl_hardware'=>'sv_modern_devices'],
        ],
        [
            'id' => 'av1', 'title' => 'AV1',
            'icon' => 'fas fa-file-video', 'color' => 'info',
            'header_bg' => 'bg-info bg-opacity-10',
            'badges' => [['b_future','info'],['b_new','primary']],
            'desc' => 'desc_av1',
            'specs' => ['sl_royalties'=>'sv_free','sl_adoption'=>'YouTube, Netflix','sl_hardware'=>'sv_limited_support'],
        ],
        [
            'id' => 'vvc', 'title' => 'VVC / H.266',
            'icon' => 'fas fa-file-video', 'color' => 'purple',
            'header_bg' => 'bg-purple bg-opacity-10',
            'badges' => [['b_next_gen','purple'],['b_new','primary']],
            'desc' => 'desc_vvc',
            'specs' => ['sl_efficiency'=>'sv_best','8K'=>'sv_designed_for','sl_release'=>'2020+'],
        ],
    ],
];

const HDR_FORMATS = [
    ['id'=>'hdr10',  'css_bg'=>'bg-hdr10',    'label'=>'HDR10',        'title'=>'hdr_hdr10_title', 'desc'=>'hdr_hdr10_desc'],
    ['id'=>'hdr10+', 'css_bg'=>'bg-hdr10plus','label'=>'HDR10+',       'title'=>'hdr_hdr10p_title','desc'=>'hdr_hdr10p_desc'],
    ['id'=>'dolby',  'css_bg'=>'bg-dolby',    'label'=>'Dolby Vision', 'title'=>'hdr_dv_title',    'desc'=>'hdr_dv_desc'],
];

const STREAMING_FORMATS = [
    [
        'id' => 'atmos', 'title' => 'Dolby Atmos',
        'icon' => 'fas fa-volume-up', 'color' => 'info',
        'header_bg' => 'bg-info bg-opacity-10',
        'badges' => [['b_object','info']],
        'desc' => 'desc_atmos',
        'specs' => ['sl_channels'=>'7.1.4+','sl_format'=>'sv_truehd_ddp_atmos','sl_devices'=>'sv_atmos_speakers'],
    ],
    [
        'id' => 'dtsx', 'title' => 'DTS:X',
        'icon' => 'fas fa-wave-square', 'color' => 'warning',
        'header_bg' => 'bg-warning bg-opacity-10',
        'badges' => [['b_competitor','warning']],
        'desc' => 'desc_dtsx',
        'specs' => ['sl_format'=>'sv_dtshd_x','sl_features'=>'sv_backward','sl_common'=>'sv_bluray_discs'],
    ],
    [
        'id' => 'imax', 'title' => 'IMAX Enhanced',
        'icon' => 'fas fa-expand-alt', 'color' => 'danger',
        'header_bg' => 'bg-danger bg-opacity-10',
        'badges' => [['IMAX','danger'],['b_new','primary']],
        'desc' => 'desc_imax',
        'specs' => ['sl_aspect'=>'sv_aspect_imax','sl_audio'=>'sv_dtsx_optimized','sl_sources'=>'Disney+, Sony Bravia Core'],
    ],
];

const CONTAINERS = [
    ['id'=>'mkv',  'color'=>'primary', 'label'=>'MKV',  'desc'=>'cont_mkv'],
    ['id'=>'mp4',  'color'=>'success', 'label'=>'MP4',  'desc'=>'cont_mp4'],
    ['id'=>'avi',  'color'=>'warning', 'label'=>'AVI',  'desc'=>'cont_avi'],
    ['id'=>'m2ts', 'color'=>'danger',  'label'=>'M2TS', 'desc'=>'cont_m2ts'],
];

const SCENE_TAGS = [
    'grp_release' => [
        ['PROPER','tag_proper'],
        ['REPACK','tag_repack'],
        ['REAL','tag_real'],
        ['READNFO','tag_readnfo'],
        ['LIMITED','tag_limited'],
        ['INTERNAL','tag_internal'],
        ['RETAIL','tag_retail'],
        ['EXTENDED','tag_extended'],
        ['UNRATED','tag_unrated'],
        ['DC','tag_dc'],
        ['THEATRICAL','tag_theatrical'],
        ['REMASTERED','tag_remastered'],
    ],
    'grp_source' => [
        ['SCREENER','tag_screener'],
        ['DVDSCR','tag_dvdscr'],
        ['HDTV','tag_hdtv'],
        ['PDTV','tag_pdtv'],
        ['VODRip','tag_vodrip'],
        ['iTUNES','tag_itunes'],
    ],
    'grp_quality' => [
        ['2160p','tag_2160p'],
        ['1080p','tag_1080p'],
        ['1080i','tag_1080i'],
        ['720p','tag_720p'],
        ['576p','tag_576p'],
        ['480p','tag_480p'],
    ],
    'grp_encoding' => [
        ['10bit','tag_10bit'],
        ['12bit','tag_12bit'],
        ['SDR','tag_sdr'],
        ['HDR','tag_hdr'],
        ['HDR10','tag_hdr10'],
        ['DV','tag_dv'],
        ['HYBRID','tag_hybrid'],
    ],
    'grp_audio' => [
        ['AAC','tag_aac'],
        ['AC3','tag_ac3'],
        ['DD+','tag_ddp'],
        ['DTS','tag_dts'],
        ['DTS-HD','tag_dtshd'],
        ['TrueHD','tag_truehd'],
        ['FLAC','tag_flac'],
        ['Atmos','tag_atmos'],
        ['DTS:X','tag_dtsx'],
        ['MP3','tag_mp3'],
    ],
];

const COMPARISON_ROWS = [
    ['CAM',       '1/10',   'danger', '1-2 GB',   '500-1000 kbps',  'cmp_mono',     'cmp_theater_day', 'text-danger',  'rec_avoid'],
    ['TS',        '3/10',   'warning','1-3 GB',   '800-1500 kbps',  'cmp_stereo',   'cmp_theater',     'text-warning', 'rec_low'],
    ['WEB-DL',    '9/10',   'success','3-8 GB',   '5000-15000 kbps','5.1 DD+',      'cmp_digital',     'text-success', 'rec_excellent'],
    ['BDRip',     '8/10',   'primary','8-15 GB',  '8000-12000 kbps','5.1/7.1',      'cmp_bluray',      'text-primary', 'rec_very_good'],
    ['REMUX',     '10/10',  'purple', '20-40 GB', '20-35 Mbps',     'cmp_lossless', 'cmp_bluray',      'text-purple',  'rec_perfect'],
    ['4K WEB-DL', '9.5/10', 'info',   '15-30 GB', '15-25 Mbps',     'Atmos/TrueHD', 'cmp_digital_4k',  'text-info',    'rec_best_value'],
    ['UHD REMUX', '10/10',  'dark',   '50-80 GB', '50-100 Mbps',    'Atmos/DTS:X',  'cmp_uhd_bluray',  'text-dark',    'rec_ultimate'],
];

const INFO_CARDS = [
    ['fa-bolt fa-3x text-warning',       'info_timeline_title', 'info_timeline_text'],
    ['fa-chart-line fa-3x text-success', 'info_ratio_title',    'info_ratio_text'],
    ['fa-eye fa-3x text-info',           'info_viewing_title',  'info_viewing_text'],
];

// ── Перевод ───────────────────────────────────────────────

/** Строка из lang по ключу; если ключа нет — сама строка (литералы: битрейты, названия форматов). Без экранирования. */
function vf_raw(string $key): string
{
    global $lang;
    return (string)($lang->videoformats[$key] ?? $key);
}

/** То же, но экранировано для HTML. */
function vf_t(string $key): string
{
    return htmlspecialchars(vf_raw($key), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Рендер-функции ────────────────────────────────────────

function renderBadges(array $badges): string
{
    return implode(' ', array_map(
        fn($b) => sprintf('<span class="badge bg-%s">%s</span>', htmlspecialchars($b[1]), vf_t($b[0])),
        $badges
    ));
}

function renderSpecs(array $specs): string
{
    $rows = '';
    foreach ($specs as $k => $v) {
        $rows .= sprintf('<small><strong>%s:</strong> %s</small><br>', vf_t((string)$k), vf_t((string)$v));
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
        $footer = '<div class="card-footer bg-transparent"><small class="' . $fc . '">' . $fi . vf_t($f['footer']) . '</small></div>';
    }

    // alert
    $alert = !empty($f['alert'])
        ? '<div class="alert alert-info mt-2 p-2"><small><i class="fas fa-info-circle"></i> '
          . vf_t($f['alert']) . '</small></div>'
        : '';

    // variants
    $variants = '';
    if (!empty($f['variants'])) {
        $pills = implode(' ', array_map(
            fn($v) => '<span class="badge bg-light text-dark">' . htmlspecialchars($v) . '</span>',
            $f['variants']
        ));
        $variants = '<div class="variants mt-2"><small><strong>' . vf_t('lbl_variants') . '</strong> ' . $pills . '</small></div>';
    }

    $hBg    = htmlspecialchars($f['header_bg']);
    $icon   = htmlspecialchars($f['icon']);
    $color  = htmlspecialchars($f['color']);
    $id     = htmlspecialchars($f['id']);
    $title  = htmlspecialchars($f['title']);
    $desc   = vf_t($f['desc']);

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

stdhead(vf_raw('title'));
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
                    <?= vf_t('hero_title') ?>
                </h1>
                <p class="hero-subtitle">
                    <?= vf_t('hero_subtitle') ?>
                </p>
                <div class="hero-badge">
                    <span class="badge bg-primary"><?= vf_t('hero_edition') ?></span>
                    <span class="badge bg-success"><?= vf_t('hero_count') ?></span>
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
                            <i class="fas fa-film me-2"></i><?= vf_t('tab_theater') ?>
                        </button>
                        <button class="nav-link" id="digital-tab" data-bs-toggle="tab"
                                data-bs-target="#digital" type="button" role="tab">
                            <i class="fas fa-globe me-2"></i><?= vf_t('tab_digital') ?>
                        </button>
                        <button class="nav-link" id="disc-tab" data-bs-toggle="tab"
                                data-bs-target="#disc" type="button" role="tab">
                            <i class="fas fa-compact-disc me-2"></i><?= vf_t('tab_disc') ?>
                        </button>
                        <button class="nav-link" id="streaming-tab" data-bs-toggle="tab"
                                data-bs-target="#streaming" type="button" role="tab">
                            <i class="fas fa-stream me-2"></i><?= vf_t('tab_streaming') ?>
                        </button>
                        <button class="nav-link" id="codecs-tab" data-bs-toggle="tab"
                                data-bs-target="#codecs" type="button" role="tab">
                            <i class="fas fa-file-video me-2"></i><?= vf_t('tab_codecs') ?>
                        </button>
                        <button class="nav-link" id="scene-tab" data-bs-toggle="tab"
                                data-bs-target="#scene" type="button" role="tab">
                            <i class="fas fa-tags me-2"></i><?= vf_t('tab_scene') ?>
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
                           placeholder="<?= vf_t('ph_search') ?>"
                           aria-label="<?= vf_t('ph_search') ?>">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch"
                            title="<?= vf_t('tip_clear') ?>" aria-label="<?= vf_t('tip_clear') ?>">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-tags mt-3">
                    <small class="text-muted"><?= vf_t('lbl_trending') ?> </small>
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
                                        <i class="fas fa-sun text-warning me-2"></i><?= vf_t('sec_hdr') ?>
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
                                                <p class="mb-1"><strong><?= vf_t($h['title']) ?></strong></p>
                                                <small><?= vf_t($h['desc']) ?></small>
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
                                    <h4 class="card-title mb-0"><?= vf_t('sec_containers') ?></h4>
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
                                                <small><?= vf_t($c['desc']) ?></small>
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
                                        <i class="fas fa-tag me-2"></i><?= vf_t($group) ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($tags as [$tag, $desc]): ?>
                                    <div class="d-flex align-items-start mb-2">
                                        <span class="badge bg-dark me-2 mt-1"
                                              style="min-width:80px;font-size:.75em">
                                            <?= htmlspecialchars($tag) ?>
                                        </span>
                                        <small class="text-muted"><?= vf_t($desc) ?></small>
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
                        <i class="fas fa-balance-scale me-2"></i><?= vf_t('sec_comparison') ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th><?= vf_t('th_format') ?></th>
                                    <th><?= vf_t('th_quality') ?></th>
                                    <th><?= vf_t('th_size') ?></th>
                                    <th><?= vf_t('th_bitrate') ?></th>
                                    <th><?= vf_t('th_audio') ?></th>
                                    <th><?= vf_t('th_release') ?></th>
                                    <th><?= vf_t('th_recommendation') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (COMPARISON_ROWS as [$fmt, $q, $qc, $size, $bitrate, $audio, $time, $rc, $rec]): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($fmt) ?></strong></td>
                                    <td><span class="badge bg-<?= htmlspecialchars($qc) ?>"><?= htmlspecialchars($q) ?></span></td>
                                    <td><?= htmlspecialchars($size) ?></td>
                                    <td><?= htmlspecialchars($bitrate) ?></td>
                                    <td><?= vf_t($audio) ?></td>
                                    <td><?= vf_t($time) ?></td>
                                    <td class="<?= htmlspecialchars($rc) ?>"><?= vf_t($rec) ?></td>
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
                <?php foreach (INFO_CARDS as [$icon, $title, $text]): ?>
                <div class="col-md-4">
                    <div class="info-card card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="info-icon mb-3">
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            <h5><?= vf_t($title) ?></h5>
                            <p class="small"><?= vf_t($text) ?></p>
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
