<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

// Own lang file (was the 820-key NexusPHP settings.lang.php, of which only ~20 keys were used)
$lang->load('torrents_promo');

/** Типы промо: id => [ключ, подпись, иконка, цвет] */
$PROMO = [
    1 => ['normal',             $lang->torrents_promo['promo_normal'] ?? 'Normal',        'fa-circle',           't-slate'],
    2 => ['free',               $lang->torrents_promo['promo_free'] ?? 'Free Leech',      'fa-gift',             't-green'],
    3 => ['twoup',              $lang->torrents_promo['promo_twoup'] ?? '2X Upload',      'fa-angles-up',        't-blue'],
    4 => ['twoupfree',          $lang->torrents_promo['promo_twoupfree'] ?? 'Free + 2X',  'fa-star',             't-amber'],
    5 => ['halfleech',          $lang->torrents_promo['promo_half'] ?? '50% Leech',       'fa-star-half-stroke', 't-teal'],
    6 => ['twouphalfleech',     $lang->torrents_promo['promo_twouphalf'] ?? '50% + 2X',   'fa-bolt',             't-purple'],
    7 => ['thirtypercentleech', $lang->torrents_promo['promo_thirty'] ?? '30% Leech',     'fa-percent',          't-indigo'],
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
        stderr(
            $lang->torrents_promo['err_security_title'] ?? 'Security Error',
            $lang->torrents_promo['err_security'] ?? 'Invalid security token. Please refresh the page and try again.'
        );
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
    flash_message($lang->torrents_promo['flash_saved'] ?? 'Settings saved successfully!', 'success');
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

stdhead($lang->torrents_promo['page_title'] ?? 'Torrent Promotions');
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/torrents_promo.css?v=20260926">

<div class="container mt-3 mb-4 tp">

    <div class="tp-card mb-3"><div class="tp-head">
        <span class="tp-head-icon"><i class="fa-solid fa-gift"></i></span>
        <div style="min-width:0">
            <h1 class="tp-title"><?= $e($lang->torrents_promo['page_title'] ?? 'Torrent Promotions') ?></h1>
            <div class="tp-sub"><?= $e($lang->torrents_promo['page_subtitle'] ?? 'Automatic freeleech and bonus rules — on upload, for big torrents, and when promotions expire') ?></div>
        </div>
    </div></div>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-gift',             'ic-green',  $lang->torrents_promo['kpi_free'] ?? 'Free now',     (int)($cnt['free'] ?? 0)],
            ['fa-angles-up',        'ic-blue',   $lang->torrents_promo['kpi_twoup'] ?? '2X upload',   (int)($cnt['twoup'] ?? 0)],
            ['fa-star-half-stroke', 'ic-teal',   $lang->torrents_promo['kpi_half'] ?? '50% leech',    (int)($cnt['half'] ?? 0)],
            ['fa-percent',          'ic-purple', $lang->torrents_promo['kpi_thirty'] ?? '30% leech',  (int)($cnt['thirty'] ?? 0)],
        ] as [$ic, $cls, $label, $n]): ?>
        <div class="col-6 col-lg-3"><div class="tp-card tp-kpi"><span class="tp-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="tp-kpi-label"><?= $e($label) ?></div><div class="tp-kpi-value"><?= number_format($n) ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= $e($_this_script_) ?>" id="tpForm">
        <input type="hidden" name="action" value="save_torrent_settings">
        <input type="hidden" name="my_post_key" value="<?= $e($mybb->post_code) ?>">

        <!-- Общие переключатели -->
        <div class="row g-3 mb-3">
            <div class="col-md-4"><?= $sw('prorules', 'fa-wand-magic-sparkles', 'ic-amber',
                $lang->torrents_promo['sw_rules'] ?? 'Promotion rules',
                $lang->torrents_promo['sw_rules_desc'] ?? 'Enable the automatic promotion rules below') ?></div>
            <div class="col-md-4"><?= $sw('uploaderdouble', 'fa-user-pen', 'ic-blue',
                $lang->torrents_promo['sw_uploader_double'] ?? 'Uploader double upload',
                $lang->torrents_promo['sw_uploader_double_desc'] ?? 'Uploaders get 2× upload on their own torrents') ?></div>
            <div class="col-md-4"><?= $sw('deldeadtorrent', 'fa-skull', 'ic-red',
                $lang->torrents_promo['sw_delete_dead'] ?? 'Delete dead torrents',
                $lang->torrents_promo['sw_delete_dead_desc'] ?? 'Remove torrents with no seeders automatically') ?></div>
        </div>

        <div id="tpRules">
        <div class="row g-3">
            <!-- Случайное промо -->
            <div class="col-lg-6">
                <div class="tp-card h-100">
                    <div class="tp-sec-head"><span class="tp-sec-icon ic-purple"><i class="fa-solid fa-dice"></i></span>
                        <div><h2 class="tp-sec-title"><?= $e($lang->torrents_promo['sec_random'] ?? 'Random promotion') ?></h2>
                        <div class="tp-muted"><?= $e($lang->torrents_promo['sec_random_note'] ?? 'Torrents promoted randomly by the system on upload.') ?></div></div></div>
                    <div class="tp-body">
                        <?php foreach ([
                            'randomhalfleech'         => 5,
                            'randomfree'              => 2,
                            'randomtwoup'             => 3,
                            'randomtwoupfree'         => 4,
                            'randomtwouphalfdown'     => 6,
                            'randomthirtypercentdown' => 7,
                        ] as $name => $pid): $v = (int)$S[$name]; ?>
                        <div class="tp-chance" data-cls="<?= $PROMO[$pid][3] ?>">
                            <div class="d-flex flex-wrap align-items-center gap-2"><span class="tp-muted"><?= $e($lang->torrents_promo['chance_label'] ?? 'Chance of becoming') ?></span><?= $badge($pid) ?></div>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control text-center tp-chance-num" name="<?= $name ?>" value="<?= $v ?>" min="0" max="100" step="1">
                                <span class="input-group-text">%</span>
                            </div>
                            <input type="range" class="form-range tp-chance-range" min="0" max="100" step="1" value="<?= $v ?>" aria-label="<?= $e($lang->torrents_promo['aria_chance'] ?? 'Chance') ?>">
                        </div>
                        <?php endforeach; ?>
                        <div class="tp-split" id="tpSplit"></div>
                        <div class="tp-sum" id="tpSum"></div>
                        <div class="tp-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i><?= $e($lang->torrents_promo['random_note_off'] ?? 'Set a value to 0 to disable that rule.') ?></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- Крупные торренты -->
                <div class="tp-card mb-3">
                    <div class="tp-sec-head"><span class="tp-sec-icon ic-teal"><i class="fa-solid fa-hard-drive"></i></span>
                        <div><h2 class="tp-sec-title"><?= $e($lang->torrents_promo['sec_large'] ?? 'Large torrents') ?></h2>
                        <div class="tp-muted"><?= $e($lang->torrents_promo['sec_large_sub'] ?? 'Promoted by the system on upload') ?></div></div></div>
                    <div class="tp-body">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span><?= $e($lang->torrents_promo['large_larger_than'] ?? 'Torrents larger than') ?></span>
                            <input type="number" class="form-control form-control-sm text-center" style="width:100px" name="largesize" id="tpLarge" value="<?= $e((float)$S['largesize']) ?>" min="0" step="0.5">
                            <span><?= $e($lang->torrents_promo['large_gb_promoted'] ?? 'GB are promoted to') ?></span>
                            <div style="min-width:170px"><?= $select('largepro', (int)$S['largepro'], 1) ?></div>
                            <span><?= $e($lang->torrents_promo['large_on_upload'] ?? 'automatically on upload.') ?></span>
                        </div>
                        <div class="tp-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i><?= $e($lang->torrents_promo['large_note'] ?? 'Default: 20 GB → Free Leech. Set the size to 0 to disable the rule.') ?></div>
                    </div>
                </div>
                <!-- «Горячие» торренты -->
                <div class="tp-card">
                    <div class="tp-sec-head"><span class="tp-sec-icon ic-red"><i class="fa-solid fa-fire"></i></span>
                        <div><h2 class="tp-sec-title"><?= $e($lang->torrents_promo['sec_hot'] ?? 'Hot torrents') ?></h2>
                        <div class="tp-muted"><?= $e($lang->torrents_promo['sec_hot_sub'] ?? 'When a torrent counts as “hot”') ?></div></div></div>
                    <div class="tp-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small"><i class="fa-solid fa-calendar me-1 text-body-secondary"></i><?= $e($lang->torrents_promo['hot_uploaded_within'] ?? 'Uploaded within') ?></label>
                                <div class="input-group input-group-sm"><input type="number" class="form-control" name="hotdays" value="<?= (int)$S['hotdays'] ?>" min="0"><span class="input-group-text"><?= $e($lang->torrents_promo['unit_days'] ?? 'days') ?></span></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small"><i class="fa-solid fa-arrow-up me-1 text-body-secondary"></i><?= $e($lang->torrents_promo['hot_at_least'] ?? 'At least') ?></label>
                                <div class="input-group input-group-sm"><input type="number" class="form-control" name="hotseeder" value="<?= (int)$S['hotseeder'] ?>" min="0"><span class="input-group-text"><?= $e($lang->torrents_promo['unit_seeders'] ?? 'seeders') ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Истечение промо -->
            <div class="col-12">
                <div class="tp-card">
                    <div class="tp-sec-head"><span class="tp-sec-icon ic-amber"><i class="fa-solid fa-hourglass-half"></i></span>
                        <div><h2 class="tp-sec-title"><?= $e($lang->torrents_promo['sec_timeout'] ?? 'Promotion timeout') ?></h2>
                        <div class="tp-muted"><?= $e($lang->torrents_promo['sec_timeout_note'] ?? 'Promotions expire after some time and switch to another type.') ?></div></div></div>
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
                                <span class="input-group-text"><?= $e($lang->torrents_promo['timeout_after'] ?? 'after') ?></span>
                                <input type="number" class="form-control text-center tp-days" name="<?= $days ?>" value="<?= $d ?>" min="0" step="1">
                                <span class="input-group-text"><?= $e($lang->torrents_promo['unit_d'] ?? 'd') ?></span>
                            </div>
                            <i class="fa-solid fa-arrow-right-long arr a2"></i>
                            <div><?= $select($become, (int)$S[$become], $pid) ?></div>
                            <div class="def"><?= $e(sprintf($lang->torrents_promo['timeout_default'] ?? 'Default: %1$s days → %2$s', $defDays, $PROMO[1][1])) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <div class="tp-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i><?= $e($lang->torrents_promo['timeout_note_off'] ?? 'Set the days to 0 to keep a promotion forever.') ?></div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="tp-card tp-savebar">
            <span class="tp-muted">
                <span class="badge rounded-pill text-bg-warning me-2" id="tpDirty" hidden><i class="fa-solid fa-pen me-1"></i><?= $e($lang->torrents_promo['save_unsaved'] ?? 'Unsaved changes') ?></span>
                <i class="fa-solid fa-circle-info me-1"></i><?= $e($lang->torrents_promo['save_hint'] ?? 'Rules apply to new uploads and the next cleanup run') ?>
            </span>
            <div class="d-flex gap-2">
                <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i><?= $e($lang->torrents_promo['btn_reset'] ?? 'Reset') ?></button>
                <button type="submit" class="btn btn-primary px-4" id="tpSave"><i class="fa-solid fa-floppy-disk me-1"></i><?= $e($lang->torrents_promo['btn_save'] ?? 'Save') ?></button>
            </div>
        </div>
    </form>
</div>

<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/torrents_promo.js?v=20260926"></script>
<?php
stdfoot();
