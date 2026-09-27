<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

$lang->load('settings');
$L = static fn(string $k, string $d): string => (string)($lang->settings[$k] ?? $d);

/** Типы промо: id => [ключ, подпись, иконка, цвет] */
$PROMO = [
    1 => ['normal',              $L('text_normal', 'Normal'),                  'fa-circle',           't-slate'],
    2 => ['free',                $L('text_free', 'Free Leech'),                'fa-gift',             't-green'],
    3 => ['twoup',               $L('text_two_times_up', '2X Upload'),         'fa-angles-up',        't-blue'],
    4 => ['twoupfree',           $L('text_free_two_times_up', 'Free + 2X'),    'fa-star',             't-amber'],
    5 => ['halfleech',           $L('text_half_down', '50% Leech'),            'fa-star-half-stroke', 't-teal'],
    6 => ['twouphalfleech',      $L('text_half_down_two_up', '50% + 2X'),      'fa-bolt',             't-purple'],
    7 => ['thirtypercentleech',  $L('text_thirty_percent_down', '30% Leech'),  'fa-percent',          't-indigo'],
];

// Значения по умолчанию — одни для чтения и сохранения.
// Раньше они расходились: например largesize 12 при чтении и 20 при сохранении,
// expirehalfleech 70 и 150, largepro 5 и 2
$DEF = [
    'prorules' => 'yes', 'uploaderdouble' => 'no', 'deldeadtorrent' => 'no',
    'randomhalfleech' => 5, 'randomfree' => 2, 'randomtwoup' => 2, 'randomtwoupfree' => 1, 'randomtwouphalfdown' => 0, 'randomthirtypercentdown' => 0,
    'largesize' => 20, 'largepro' => 2,
    'expirehalfleech' => 150, 'expirefree' => 60, 'expiretwoup' => 60, 'expiretwoupfree' => 30, 'expiretwouphalfleech' => 30, 'expirethirtypercentleech' => 30, 'expirenormal' => 0,
    'halfleechbecome' => 1, 'freebecome' => 1, 'twoupbecome' => 1, 'twoupfreebecome' => 1, 'twouphalfleechbecome' => 1, 'thirtypercentleechbecome' => 1, 'normalbecome' => 1,
    'hotdays' => 7, 'hotseeder' => 5,
];
$YESNO = ['prorules', 'uploaderdouble', 'deldeadtorrent'];

// ── Сохранение ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_torrent_settings') {
    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        stderr('Security Error', 'Invalid security token. Please refresh the page and try again.');
    }
    foreach ($DEF as $name => $def) {
        if (in_array($name, $YESNO, true)) {
            $v = ($_POST[$name] ?? 'no') === 'yes' ? 'yes' : 'no';
        } elseif ($name === 'largesize') {
            $v = (string)max(0.0, round((float)($_POST[$name] ?? $def), 2));
        } elseif (str_starts_with($name, 'random')) {
            $v = (string)max(0, min(100, (int)($_POST[$name] ?? $def)));   // шанс в %
        } elseif (str_ends_with($name, 'become') || $name === 'largepro') {
            $v = (string)(isset($PROMO[(int)($_POST[$name] ?? 0)]) ? (int)$_POST[$name] : $def);
        } else {
            $v = (string)max(0, (int)($_POST[$name] ?? $def));             // раньше отрицательные проходили
        }
        $db->sql_query_prepared('UPDATE settings SET value = ? WHERE name = ?', [$v, $name]);
    }
    rebuild_settings();
    write_log('Torrent promotion settings changed by ' . ($CURUSER['username'] ?? 'staff'), 'settings');
    flash_message($L('settings_saved', 'Settings saved successfully!'), 'success');
    function_exists('admin_redirect') ? admin_redirect($_this_script_) : header('Location: ' . $_this_script_);
    exit;
}

// ── Текущие значения ─────────────────────────────────────────────────
$S = $DEF;
$ph = implode(',', array_fill(0, count($DEF), '?'));
$q = $db->sql_query_prepared("SELECT name, value FROM settings WHERE name IN ({$ph})", array_keys($DEF));
while ($q && ($r = $db->fetch_array($q))) $S[$r['name']] = $r['value'];

// Сейчас на промо (для плиток)
$cnt = [];
$cq = $db->sql_query_prepared("SELECT COALESCE(SUM(free = 'yes'),0) AS free, COALESCE(SUM(doubleupload = 'yes'),0) AS twoup, COALESCE(SUM(silver = 'yes'),0) AS half, COALESCE(SUM(thirtypercent = 'yes'),0) AS thirty FROM torrents");
$cnt = $cq ? $db->fetch_array($cq) : [];

$e = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$badge = static function (int $id) use ($PROMO, $e): string {
    [, $label, $icon, $cls] = $PROMO[$id] ?? $PROMO[1];
    return '<span class="tp-pill ' . $cls . '"><i class="fa-solid ' . $icon . '"></i>' . $e($label) . '</span>';
};
$select = static function (string $name, int $sel, int $hide = 0) use ($PROMO, $e): string {
    $h = '<select class="form-select form-select-sm tp-promo-select" name="' . $e($name) . '">';
    foreach ($PROMO as $id => [, $label]) {
        if ($id === $hide) continue;
        $h .= '<option value="' . $id . '"' . ($id === $sel ? ' selected' : '') . '>' . $e($label) . '</option>';
    }
    return $h . '</select>';
};
$sw = static function (string $name, string $icon, string $cls, string $title, string $desc) use ($S, $e): string {
    return '<label class="tp-switch" for="' . $name . '"><span class="tp-sec-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<span class="flex-grow-1"><b class="d-block">' . $e($title) . '</b><small class="tp-muted">' . $e($desc) . '</small></span>'
         . '<input type="hidden" name="' . $name . '" value="no">'
         . '<input class="form-check-input" type="checkbox" role="switch" id="' . $name . '" name="' . $name . '" value="yes"' . ($S[$name] === 'yes' ? ' checked' : '') . '></label>';
};

stdhead('Torrent Promotions');
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/torrents_promo.css?v=20260926">

<div class="container mt-3 mb-4 tp">

    <div class="tp-card mb-3"><div class="tp-head">
        <span class="tp-head-icon"><i class="fa-solid fa-gift"></i></span>
        <div style="min-width:0">
            <h1 class="tp-title"><?= $e($L('head_torrent_settings', 'Torrent Promotions')) ?></h1>
            <div class="tp-sub">Automatic freeleech and bonus rules — on upload, for big torrents, and when promotions expire</div>
        </div>
    </div></div>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-gift',             'ic-green',  'Free now',    (int)($cnt['free'] ?? 0)],
            ['fa-angles-up',        'ic-blue',   '2X upload',   (int)($cnt['twoup'] ?? 0)],
            ['fa-star-half-stroke', 'ic-teal',   '50% leech',   (int)($cnt['half'] ?? 0)],
            ['fa-percent',          'ic-purple', '30% leech',   (int)($cnt['thirty'] ?? 0)],
        ] as [$ic, $cls, $label, $n]): ?>
        <div class="col-6 col-lg-3"><div class="tp-card tp-kpi"><span class="tp-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="tp-kpi-label"><?= $label ?></div><div class="tp-kpi-value"><?= number_format($n) ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= $e($_this_script_) ?>" id="tpForm">
        <input type="hidden" name="action" value="save_torrent_settings">
        <input type="hidden" name="my_post_key" value="<?= $e($mybb->post_code) ?>">

        <!-- Общие переключатели -->
        <div class="row g-3 mb-3">
            <div class="col-md-4"><?= $sw('prorules', 'fa-wand-magic-sparkles', 'ic-amber', $L('row_promotion_rules', 'Promotion rules'), $L('text_promotion_rules_note', 'Enable the automatic promotion rules below')) ?></div>
            <div class="col-md-4"><?= $sw('uploaderdouble', 'fa-user-pen', 'ic-blue', 'Uploader double upload', 'Uploaders get 2× upload on their own torrents') ?></div>
            <div class="col-md-4"><?= $sw('deldeadtorrent', 'fa-skull', 'ic-red', 'Delete dead torrents', 'Remove torrents with no seeders automatically') ?></div>
        </div>

        <div id="tpRules">
        <div class="row g-3">
            <!-- Случайное промо -->
            <div class="col-lg-6">
                <div class="tp-card h-100">
                    <div class="tp-sec-head"><span class="tp-sec-icon ic-purple"><i class="fa-solid fa-dice"></i></span>
                        <div><h2 class="tp-sec-title"><?= $e($L('row_random_promotion', 'Random promotion')) ?></h2>
                        <div class="tp-muted"><?= $e($L('text_random_promotion_note_one', 'Torrents promoted randomly by system upon uploading.')) ?></div></div></div>
                    <div class="tp-body">
                        <?php foreach ([
                            'randomhalfleech'         => [5, 'text_halfleech_chance_becoming'],
                            'randomfree'              => [2, 'text_free_chance_becoming'],
                            'randomtwoup'             => [3, 'text_twoup_chance_becoming'],
                            'randomtwoupfree'         => [4, 'text_freetwoup_chance_becoming'],
                            'randomtwouphalfdown'     => [6, 'text_twouphalfleech_chance_becoming'],
                            'randomthirtypercentdown' => [7, 'text_thirtypercentleech_chance_becoming'],
                        ] as $name => [$pid, $chanceKey]): $v = (int)$S[$name]; ?>
                        <div class="tp-chance" data-cls="<?= $PROMO[$pid][3] ?>">
                            <div class="d-flex flex-wrap align-items-center gap-2"><span class="tp-muted"><?= $e($L($chanceKey, '% chance becoming')) ?></span><?= $badge($pid) ?></div>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control text-center tp-chance-num" name="<?= $name ?>" value="<?= $v ?>" min="0" max="100" step="1">
                                <span class="input-group-text">%</span>
                            </div>
                            <input type="range" class="form-range tp-chance-range" min="0" max="100" step="1" value="<?= $v ?>" aria-label="Chance">
                        </div>
                        <?php endforeach; ?>
                        <div class="tp-split" id="tpSplit"></div>
                        <div class="tp-sum" id="tpSum"></div>
                        <div class="tp-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i><?= $e($L('text_random_promotion_note_two', "Set values to '0' to disable the rules.")) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- Крупные торренты -->
                <div class="tp-card mb-3">
                    <div class="tp-sec-head"><span class="tp-sec-icon ic-teal"><i class="fa-solid fa-hard-drive"></i></span>
                        <div><h2 class="tp-sec-title"><?= $e($L('row_large_torrent_promotion', 'Large torrents')) ?></h2>
                        <div class="tp-muted"><?= $e($L('text_by_system_upon_uploading', 'by system upon uploading.')) ?></div></div></div>
                    <div class="tp-body">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span><?= $e($L('text_torrent_larger_than', 'Torrents larger than')) ?></span>
                            <input type="number" class="form-control form-control-sm text-center" style="width:100px" name="largesize" id="tpLarge" value="<?= $e((float)$S['largesize']) ?>" min="0" step="0.5">
                            <span><?= $e($L('text_gb_promoted_to', 'GB will be automatically promoted to')) ?></span>
                            <div style="min-width:170px"><?= $select('largepro', (int)$S['largepro'], 1) ?></div>
                            <span><?= $e($L('text_by_system_upon_uploading', 'by system upon uploading.')) ?></span>
                        </div>
                        <div class="tp-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i><?= $e($L('text_large_torrent_promotion_note', "Default '20', 'free'. Set torrent size to '0' to disable the rule.")) ?></div>
                    </div>
                </div>
                <!-- «Горячие» торренты -->
                <div class="tp-card">
                    <div class="tp-sec-head"><span class="tp-sec-icon ic-red"><i class="fa-solid fa-fire"></i></span>
                        <div><h2 class="tp-sec-title">Hot torrents</h2><div class="tp-muted">When a torrent counts as “hot”</div></div></div>
                    <div class="tp-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small"><i class="fa-solid fa-calendar me-1 text-body-secondary"></i>Uploaded within</label>
                                <div class="input-group input-group-sm"><input type="number" class="form-control" name="hotdays" value="<?= (int)$S['hotdays'] ?>" min="0"><span class="input-group-text">days</span></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small"><i class="fa-solid fa-arrow-up me-1 text-body-secondary"></i>At least</label>
                                <div class="input-group input-group-sm"><input type="number" class="form-control" name="hotseeder" value="<?= (int)$S['hotseeder'] ?>" min="0"><span class="input-group-text">seeders</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Истечение промо -->
            <div class="col-12">
                <div class="tp-card">
                    <div class="tp-sec-head"><span class="tp-sec-icon ic-amber"><i class="fa-solid fa-hourglass-half"></i></span>
                        <div><h2 class="tp-sec-title"><?= $e($L('row_promotion_timeout', 'Promotion timeout')) ?></h2>
                        <div class="tp-muted"><?= $e($L('text_promotion_timeout_note_one', 'Promotion for torrents will expire after some time.')) ?></div></div></div>
                    <div class="tp-body">
                        <?php foreach ([
                            [5, 'expirehalfleech',          'halfleechbecome',          150],
                            [2, 'expirefree',               'freebecome',               60],
                            [3, 'expiretwoup',              'twoupbecome',              60],
                            [4, 'expiretwoupfree',          'twoupfreebecome',          30],
                            [6, 'expiretwouphalfleech',     'twouphalfleechbecome',     30],
                            [7, 'expirethirtypercentleech', 'thirtypercentleechbecome', 30],
                            [1, 'expirenormal',             'normalbecome',             0],
                        ] as [$pid, $days, $become, $defDays]): $d = (int)$S[$days]; ?>
                        <div class="tp-flow<?= $d === 0 ? ' tp-off' : '' ?>">
                            <div><?= $badge($pid) ?></div>
                            <i class="fa-solid fa-arrow-right-long arr"></i>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">after</span>
                                <input type="number" class="form-control text-center tp-days" name="<?= $days ?>" value="<?= $d ?>" min="0" step="1">
                                <span class="input-group-text">d</span>
                            </div>
                            <i class="fa-solid fa-arrow-right-long arr a2"></i>
                            <div><?= $select($become, (int)$S[$become], $pid) ?></div>
                            <div class="def">Default: <?= $defDays ?> days → <?= $e($PROMO[1][1]) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <div class="tp-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i><?= $e($L('text_promotion_timeout_note_two', 'Promotion for torrents will expire after some time.')) ?></div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="tp-card tp-savebar">
            <span class="tp-muted">
                <span class="badge rounded-pill text-bg-warning me-2" id="tpDirty" hidden><i class="fa-solid fa-pen me-1"></i>Unsaved changes</span>
                <i class="fa-solid fa-circle-info me-1"></i>Rules apply to new uploads and the next cleanup run
            </span>
            <div class="d-flex gap-2">
                <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i>Reset</button>
                <button type="submit" class="btn btn-primary px-4" id="tpSave"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
            </div>
        </div>
    </form>
</div>

<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/torrents_promo.js?v=20260926"></script>
<?php
stdfoot();