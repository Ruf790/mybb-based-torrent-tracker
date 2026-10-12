<?php


declare(strict_types=1);

define('R_VERSION', 'v1.9');
require_once 'global.php';

$lang->load('getrss');

if (!isset($CURUSER) || ($CURUSER["id"] ?? 0) === 0) {
    print_no_permission();
}

gzip();

// Asset versions — bump after editing the CSS / JS file
const GETRSS_CSS_VER = 1;
const GETRSS_JS_VER  = 1;

/**
 * Escaped lang string for HTML output (lang values are plain text).
 */
function getrss_e(string $key): string
{
    global $lang;
    return htmlspecialchars($lang->getrss[$key], ENT_QUOTES, 'UTF-8');
}

/**
 * AGS_LANG (js_* keys without prefix) + page script.
 */
function getrss_scripts(): string
{
    global $lang, $BASEURL;

    $js = [];
    foreach ($lang->getrss as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) {
            $js[substr((string)$k, 3)] = (string)$v;
        }
    }

    $json = json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    return '<script>const AGS_LANG = ' . $json . ';</script>' . "\n"
         . '<script src="' . $BASEURL . '/scripts/getrss.js?ver=' . GETRSS_JS_VER . '"></script>' . "\n";
}

$allowed_timezones = [
    '-12', '-11', '-10', '-9', '-8', '-7', '-6', '-5', '-4', '-3.5', '-3',
    '-2', '-1', '0', '1', '2', '3', '3.5', '4', '4.5', '5', '5.5', '6',
    '7', '8', '9', '9.5', '10', '11', '12'
];

$allowed_showrows = ['5', '10', '20', '30', '40', '50'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $queries = [];
    $link = $BASEURL . '/rss.php?secret_key=' . ($CURUSER['passkey'] ?? '') . '&';

    // Feed type handling
    $feedtype = in_array($_POST['feedtype'] ?? '', ['download', 'details'], true)
        ? $_POST['feedtype']
        : 'details';
    $queries[] = 'feedtype=' . $feedtype;

    // Timezone handling
    $timezone = in_array($_POST['timezone'] ?? '', $allowed_timezones, true)
        ? $_POST['timezone']
        : '1';
    $queries[] = 'timezone=' . $timezone;

    // Show rows handling
    $showrows = in_array($_POST['showrows'] ?? '', $allowed_showrows, true)
        ? $_POST['showrows']
        : '20';
    $queries[] = 'showrows=' . $showrows;

    // Categories handling
    if (isset($_POST['showall'])) {
        $queries[] = 'categories=all';
    } else {
        $cats_raw = trim($_POST['categories_selected'] ?? '');
        if (empty($cats_raw)) {
            $queries[] = 'categories=all';
        } else {
            $ids = array_filter(array_map('intval', explode(',', $cats_raw)));
            $queries[] = !empty($ids) ? 'categories=' . implode(',', $ids) : 'categories=all';
        }
    }

    // Build final URL
    $final_queries = implode('&', $queries);
    if ($final_queries) {
        $link .= $final_queries;
    }
    $link_safe = htmlspecialchars($link);

    stdhead($lang->getrss['title']);
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/getrss.css?ver=' . GETRSS_CSS_VER . '">';
    ?>

<div class="container mt-3 py-4 grss-page">

    <!-- Header -->
    <div class="grss-card grss-head mb-3">
        <div class="grss-head__icon grss-soft-success"><i class="fa-solid fa-rss"></i></div>
        <div class="flex-grow-1">
            <h1 class="grss-title"><?= getrss_e('pane_result') ?></h1>
            <p><?= getrss_e('hint_copy_link') ?></p>
        </div>
    </div>

    <!-- Link -->
    <div class="grss-card mb-3">
        <div class="grss-sec">
            <div class="grss-sec__icon grss-soft-primary"><i class="fa-solid fa-link"></i></div>
            <div>
                <h2 class="grss-sec__title"><?= getrss_e('lbl_rss_link') ?></h2>
            </div>
        </div>

        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-rss"></i></span>
            <input type="text" class="form-control grss-link" value="<?= $link_safe ?>" id="rssLink" readonly
                   aria-label="<?= getrss_e('lbl_rss_link') ?>" onfocus="this.select()">
            <button class="btn btn-outline-secondary" type="button" onclick="copyRssLink(this)">
                <i class="fa-solid fa-copy me-1"></i><?= getrss_e('btn_copy') ?>
            </button>
        </div>

        <div class="grss-note grss-soft-warning">
            <i class="fa-solid fa-key mt-1"></i>
            <span><?= getrss_e('hint_passkey') ?></span>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="<?= $link_safe ?>" class="btn btn-success grss-pill" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= getrss_e('btn_test') ?>
            </a>
            <a href="/getrss.php" class="btn btn-outline-primary grss-pill">
                <i class="fa-solid fa-rotate-left me-1"></i><?= getrss_e('btn_generate_another') ?>
            </a>
        </div>
    </div>
</div>

    <?php
    echo getrss_scripts();
    stdfoot();
    exit;
}

// ---------------------------------------------------------------------
// Form
// ---------------------------------------------------------------------
if (!isset($_categoriesC) || !is_array($_categoriesC)) {
    require_once TSDIR . '/cache/categories.php';
}

// Timezone options (value => lang key)
$timezone_options = [
    '-12'  => 'opt_tz_m12',
    '-11'  => 'opt_tz_m11',
    '-10'  => 'opt_tz_m10',
    '-9'   => 'opt_tz_m9',
    '-8'   => 'opt_tz_m8',
    '-7'   => 'opt_tz_m7',
    '-6'   => 'opt_tz_m6',
    '-5'   => 'opt_tz_m5',
    '-4'   => 'opt_tz_m4',
    '-3.5' => 'opt_tz_m3_5',
    '-3'   => 'opt_tz_m3',
    '-2'   => 'opt_tz_m2',
    '-1'   => 'opt_tz_m1',
    '0'    => 'opt_tz_0',
    '1'    => 'opt_tz_1',
    '2'    => 'opt_tz_2',
    '3'    => 'opt_tz_3',
    '3.5'  => 'opt_tz_3_5',
    '4'    => 'opt_tz_4',
    '4.5'  => 'opt_tz_4_5',
    '5'    => 'opt_tz_5',
    '5.5'  => 'opt_tz_5_5',
    '6'    => 'opt_tz_6',
    '7'    => 'opt_tz_7',
    '8'    => 'opt_tz_8',
    '9'    => 'opt_tz_9',
    '9.5'  => 'opt_tz_9_5',
    '10'   => 'opt_tz_10',
    '11'   => 'opt_tz_11',
    '12'   => 'opt_tz_12',
];

stdhead($lang->getrss['title']);
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/getrss.css?ver=' . GETRSS_CSS_VER . '">';
?>

<div class="container mt-3 py-4 grss-page">
<form method="post" action="/getrss.php" name="rss" class="needs-validation" novalidate>

    <!-- Header -->
    <div class="grss-card grss-head mb-3">
        <div class="grss-head__icon grss-soft-warning"><i class="fa-solid fa-rss"></i></div>
        <div class="flex-grow-1">
            <h1 class="grss-title"><?= getrss_e('title') ?></h1>
            <p><?= getrss_e('sec_subtitle') ?></p>
        </div>
    </div>

    <!-- Categories -->
    <div class="grss-card mb-3">
        <div class="grss-sec">
            <div class="grss-sec__icon grss-soft-primary"><i class="fa-solid fa-table-cells-large"></i></div>
            <div>
                <h2 class="grss-sec__title"><?= getrss_e('pane_categories') ?></h2>
                <p class="grss-sec__hint"><?= getrss_e('hint_categories') ?></p>
            </div>
            <div class="grss-sec__actions">
                <button type="button" class="btn btn-sm btn-outline-success grss-pill grss-quick" onclick="selectAllCategories()">
                    <i class="fa-solid fa-square-check me-1"></i><?= getrss_e('btn_select_all') ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary grss-pill grss-quick" onclick="deselectAllCategories()">
                    <i class="fa-solid fa-xmark me-1"></i><?= getrss_e('btn_deselect_all') ?>
                </button>
            </div>
        </div>

        <input type="hidden" name="categories_selected" id="categoriesSelected" value="">
        <div class="category-icon-picker" id="catPicker">
        <?php foreach ($_categoriesC as $cat): ?>
            <button type="button"
                    class="cat-pick-btn"
                    data-id="<?= (int)$cat['id'] ?>"
                    title="<?= htmlspecialchars($cat['name']) ?>"
                    aria-pressed="false"
                    onclick="toggleCategory(this)">
                <i class="<?= htmlspecialchars($cat['icon'] ?? 'fas fa-folder') ?>"></i>
                <span><?= htmlspecialchars($cat['name']) ?></span>
            </button>
        <?php endforeach; ?>
        </div>

        <div class="grss-catlabel" id="catLabel" aria-live="polite">
            <i class="fa-solid fa-hand-pointer me-1"></i><?= getrss_e('hint_cat_click') ?>
        </div>

        <div class="form-check form-switch grss-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="showall" id="showall" value="1" onclick="handleSelectAll(this)">
            <label class="form-check-label" for="showall">
                <i class="fa-solid fa-infinity me-1"></i><?= getrss_e('lbl_showall') ?>
            </label>
            <div class="grss-meta"><?= getrss_e('hint_showall') ?></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <!-- Feed type -->
        <div class="col-lg-6">
            <div class="grss-card h-100">
                <div class="grss-sec">
                    <div class="grss-sec__icon grss-soft-info"><i class="fa-solid fa-link"></i></div>
                    <div>
                        <h2 class="grss-sec__title"><?= getrss_e('pane_feedtype') ?></h2>
                    </div>
                </div>

                <div class="grss-options">
                    <label class="grss-option" for="feedtype_details">
                        <input class="form-check-input" type="radio" name="feedtype" id="feedtype_details" value="details" checked>
                        <span class="grss-option__icon grss-soft-primary"><i class="fa-solid fa-globe"></i></span>
                        <span>
                            <span class="grss-option__title"><?= getrss_e('opt_feed_details') ?></span>
                            <span class="grss-option__hint"><?= getrss_e('hint_feed_details') ?></span>
                        </span>
                    </label>
                    <label class="grss-option" for="feedtype_download">
                        <input class="form-check-input" type="radio" name="feedtype" id="feedtype_download" value="download">
                        <span class="grss-option__icon grss-soft-success"><i class="fa-solid fa-download"></i></span>
                        <span>
                            <span class="grss-option__title"><?= getrss_e('opt_feed_download') ?></span>
                            <span class="grss-option__hint"><?= getrss_e('hint_feed_download') ?></span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Settings -->
        <div class="col-lg-6">
            <div class="grss-card h-100">
                <div class="grss-sec">
                    <div class="grss-sec__icon grss-soft-warning"><i class="fa-solid fa-sliders"></i></div>
                    <div>
                        <h2 class="grss-sec__title"><?= getrss_e('pane_settings') ?></h2>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="timezone" class="form-label">
                        <i class="fa-solid fa-clock me-1"></i><?= getrss_e('lbl_timezone') ?>
                    </label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="fa-solid fa-earth-europe"></i></span>
                        <select class="form-select" name="timezone" id="timezone" required>
                        <?php foreach ($timezone_options as $value => $tz_key):
                            $value = (string)$value; ?>
                            <option value="<?= $value ?>"<?= $value === '1' ? ' selected' : '' ?>><?= getrss_e($tz_key) ?></option>
                        <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= getrss_e('err_timezone') ?></div>
                    </div>
                </div>

                <div>
                    <label for="showrows" class="form-label">
                        <i class="fa-solid fa-list me-1"></i><?= getrss_e('lbl_showrows') ?>
                    </label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="fa-solid fa-list-ol"></i></span>
                        <select class="form-select" name="showrows" id="showrows" required>
                        <?php foreach ($allowed_showrows as $rows): ?>
                            <option value="<?= $rows ?>"<?= $rows === '20' ? ' selected' : '' ?>><?= $rows ?></option>
                        <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= getrss_e('err_showrows') ?></div>
                    </div>
                    <div class="grss-meta mt-1"><?= getrss_e('hint_showrows') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sticky action bar -->
    <div class="grss-card grss-actionbar">
        <div class="grss-actionbar__info" id="grssSummary" aria-hidden="true">
            <i class="fa-solid fa-hand-pointer me-1"></i><?= getrss_e('hint_cat_click') ?>
        </div>
        <button type="submit" class="btn btn-primary grss-pill">
            <i class="fa-solid fa-wand-magic-sparkles me-2"></i><?= getrss_e('btn_submit') ?>
        </button>
    </div>

</form>
</div>

<?php
echo getrss_scripts();
stdfoot();
