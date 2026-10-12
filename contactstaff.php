<?php
declare(strict_types=1);

require_once 'global.php';
$lang->load('contactstaff');

require_once 'cache/smilies.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/editor.php';

$parser         = new postParser;
$parser_options = [
    'allow_html'      => 1, 'allow_mycode'    => 1,
    'allow_smilies'   => 1, 'allow_imgcode'   => 1,
    'allow_videocode' => 1, 'filter_badwords' => 1,
];

gzip();
maxsysop();
define('STF_VERSION', '0.7');

// ── Helpers ──────────────────────────────────────────────────────────────────
if (!function_exists('ags_fmt')) {
    /**
     * Substitutes {1}, {2}… (and %1$s — what $lang->load() turns {1} into).
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n   = $i + 1;
            $val = (string)$arg;
            $map['{' . $n . '}']  = $val;
            $map['%' . $n . '$s'] = $val;
            $map['%' . $n . '$d'] = $val;
        }
        return strtr($str, $map);
    }
}

$L = $lang->contactstaff;

const STF_SUBJECT_MAX = 200;
const STF_MESSAGE_MAX = 1000;

// ── AJAX POST ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // CSRF
    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        echo json_encode(['success' => false, 'message' => $L['flash_invalid_request']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $msgtext = trim($_POST['msgtext'] ?? '');
    $subject = trim($_POST['subject'] ?? '');

    if ($msgtext === '' || $subject === '') {
        echo json_encode(['success' => false, 'message' => $L['flash_fields_blank']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Flood check
    $q = $db->sql_query_prepared(
        'SELECT added FROM staffmessages WHERE sender = ? ORDER BY added DESC LIMIT 1',
        [(int)$CURUSER['id']]
    );
    if ($db->num_rows($q) > 0) {
        $row = $db->fetch_array($q);
        flood_check($L['txt_flood_what'], (string)$row['added']);
    }

    // Prepared statement — no escape_string() needed (would double-escape)
    $db->sql_query_prepared(
        'INSERT INTO staffmessages (sender, added, msg, subject) VALUES (?, ?, ?, ?)',
        [(int)$CURUSER['id'], TIMENOW, $msgtext, $subject]
    );

    echo json_encode(['success' => true, 'message' => $L['flash_sent']], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Subject prefill from GET ─────────────────────────────────────────────────
$subject = '';
if (($_GET['subject'] ?? '') === 'invalid_link') {
    $link = $_GET['link'] ?? '';
    if (is_string($link) && filter_var($link, FILTER_VALIDATE_URL) && str_starts_with($link, 'http')) {
        // Escaped once on output (value="…"), not here — avoids &amp;amp;
        $link    = str_replace('http://referhide.com/?g=', '', $link);
        $subject = ags_fmt($L['txt_invalid_link'], $link);
    }
}

// ── returnto ─────────────────────────────────────────────────────────────────
$returnto = isset($_GET['returnto'])
    ? fix_url($_GET['returnto'])
    : fix_url($_SERVER['HTTP_REFERER'] ?? '');

// ── JS strings ───────────────────────────────────────────────────────────────
$jsLang = [];
foreach ($L as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = $v;
    }
}

// ── Output ───────────────────────────────────────────────────────────────────
stdhead($L['title']);

$h          = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$postCode   = $h((string)($mybb->post_code ?? ''));
$h_subject  = $h($subject);
$h_returnto = $h((string)$returnto);
$h_script   = $h($_SERVER['SCRIPT_NAME']);
$h_base     = $h((string)$BASEURL);
$editor     = insert_bbcode_editor($smilies, $BASEURL, 'staffMessage');
$prefill    = is_string($_POST['msgtext'] ?? null) ? $_POST['msgtext'] : '';
?>

<style>
.ags-contact { font-size: 1.055rem; }
.ags-contact .ags-card {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1rem;
    box-shadow: 0 .25rem 1rem rgba(0,0,0,.05);
}
.ags-contact .ags-head { display: flex; align-items: center; gap: 1rem; padding: 1.15rem 1.35rem; }
.ags-contact .ags-ico {
    flex: 0 0 auto; width: 3.1rem; height: 3.1rem; border-radius: .85rem;
    display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem;
}
.ags-contact .ags-ico-sm { width: 2.35rem; height: 2.35rem; border-radius: .65rem; font-size: 1rem; }
.ags-contact .ags-soft-primary { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
.ags-contact .ags-soft-info    { background: var(--bs-info-bg-subtle);    color: var(--bs-info-text-emphasis); }
.ags-contact .ags-soft-success { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
.ags-contact .ags-soft-warning { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
.ags-contact .ags-soft-danger  { background: var(--bs-danger-bg-subtle);  color: var(--bs-danger-text-emphasis); }
.ags-contact .ags-title { font-family: Oswald, sans-serif; font-size: 1.5rem; margin: 0; line-height: 1.2; }
.ags-contact .ags-sub   { color: var(--bs-secondary-color); margin: 0; }
.ags-contact .ags-notice {
    display: flex; gap: .85rem; align-items: center;
    padding: .9rem 1.15rem; border-radius: .85rem;
    background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis);
    border: 1px solid var(--bs-warning-border-subtle);
}
.ags-contact .ags-sec {
    font-family: Oswald, sans-serif; font-size: 1.1rem; margin: 0;
    display: flex; align-items: center; gap: .55rem;
    padding: .9rem 1.25rem; border-bottom: 1px solid var(--bs-border-color-translucent);
}
.ags-contact .ags-body { padding: 1.25rem; }
.ags-contact .ags-tip { display: flex; gap: .8rem; }
.ags-contact .ags-tip + .ags-tip { margin-top: 1rem; }
.ags-contact .ags-tip-title { font-weight: 600; margin-bottom: .1rem; }
.ags-contact .ags-tip-text  { color: var(--bs-secondary-color); font-size: .93em; margin: 0; }
.ags-contact .ags-pill { border-radius: 50rem; padding-left: 1.15rem; padding-right: 1.15rem; }
.ags-contact .input-group-text { background: var(--bs-tertiary-bg); }
.ags-contact .ags-counter { font-variant-numeric: tabular-nums; }
.ags-contact .ags-actions {
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem;
    padding: .9rem 1.25rem; border-top: 1px solid var(--bs-border-color-translucent);
    background: var(--bs-tertiary-bg); border-radius: 0 0 1rem 1rem;
}
</style>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div id="formToast" class="toast align-items-center text-white border-0" role="alert"
         aria-live="assertive" aria-atomic="true">
        <div class="d-flex align-items-center">
            <i id="formToastIcon" class="fa-solid fa-circle-check ms-3" aria-hidden="true"></i>
            <div class="toast-body" id="formToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="<?= $h($L['aria_close']) ?>"></button>
        </div>
    </div>
</div>

<div class="container mt-3 mb-4 ags-contact">

    <!-- Header -->
    <div class="ags-card ags-head mb-3">
        <span class="ags-ico ags-soft-primary"><i class="fa-solid fa-headset" aria-hidden="true"></i></span>
        <div>
            <h1 class="ags-title"><?= $h($L['title']) ?></h1>
            <p class="ags-sub"><?= $h($L['subtitle']) ?></p>
        </div>
    </div>

    <!-- Notice -->
    <div class="ags-notice mb-3" role="alert">
        <i class="fa-solid fa-triangle-exclamation fa-lg" aria-hidden="true"></i>
        <span><?= $h($L['hint_important']) ?></span>
    </div>

    <div class="row g-3">

        <!-- Form -->
        <div class="col-lg-8 order-2 order-lg-1">
            <div class="ags-card">
                <h2 class="ags-sec">
                    <i class="fa-solid fa-pen-to-square text-primary" aria-hidden="true"></i>
                    <?= $h($L['sec_form']) ?>
                </h2>

                <form id="staffForm" method="post" action="<?= $h_script ?>">
                    <input type="hidden" name="my_post_key" value="<?= $postCode ?>">
                    <input type="hidden" name="returnto"    value="<?= $h_returnto ?>">

                    <div class="ags-body">
                        <div class="mb-3">
                            <label for="subject" class="form-label fw-semibold"><?= $h($L['lbl_subject']) ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-tag" aria-hidden="true"></i></span>
                                <input type="text" class="form-control" id="subject" name="subject"
                                       value="<?= $h_subject ?>" maxlength="<?= STF_SUBJECT_MAX ?>"
                                       placeholder="<?= $h($L['ph_subject']) ?>"
                                       aria-describedby="subjectHint" required>
                            </div>
                            <div id="subjectHint" class="form-text">
                                <i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><?= $h(ags_fmt($L['hint_subject'], STF_SUBJECT_MAX)) ?>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="staffMessage" class="form-label fw-semibold"><?= $h($L['lbl_message']) ?></label>
                        </div>

                        <?= $editor['toolbar'] ?>

                        <div>
                            <textarea class="form-control" id="staffMessage" name="msgtext"
                                      rows="8" maxlength="<?= STF_MESSAGE_MAX ?>"
                                      placeholder="<?= $h($L['ph_message']) ?>"><?= $h($prefill) ?></textarea>
                            <div id="charCount" class="form-text text-end ags-counter"><?= $h(ags_fmt($L['js_char_count'], 0, STF_MESSAGE_MAX)) ?></div>
                        </div>
                    </div>

                    <div class="ags-actions">
                        <?php if ($returnto !== ''): ?>
                            <a href="<?= $h_returnto ?>" class="btn btn-outline-secondary ags-pill">
                                <i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i><?= $h($L['btn_back']) ?>
                            </a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <button type="submit" id="staffSubmit" class="btn btn-primary ags-pill">
                            <i id="staffSubmitIcon" class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i><span id="staffSubmitLabel"><?= $h($L['btn_send']) ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tips -->
        <div class="col-lg-4 order-1 order-lg-2">
            <div class="ags-card">
                <h2 class="ags-sec">
                    <i class="fa-solid fa-lightbulb text-warning" aria-hidden="true"></i>
                    <?= $h($L['sec_before']) ?>
                </h2>
                <div class="ags-body">
                    <div class="ags-tip">
                        <span class="ags-ico ags-ico-sm ags-soft-info"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></span>
                        <div>
                            <div class="ags-tip-title"><?= $h($L['tip_faq_title']) ?></div>
                            <p class="ags-tip-text"><?= $h($L['tip_faq_text']) ?></p>
                        </div>
                    </div>
                    <div class="ags-tip">
                        <span class="ags-ico ags-ico-sm ags-soft-success"><i class="fa-solid fa-comments" aria-hidden="true"></i></span>
                        <div>
                            <div class="ags-tip-title"><?= $h($L['tip_forum_title']) ?></div>
                            <p class="ags-tip-text"><?= $h($L['tip_forum_text']) ?></p>
                        </div>
                    </div>
                    <div class="ags-tip">
                        <span class="ags-ico ags-ico-sm ags-soft-danger"><i class="fa-solid fa-ban" aria-hidden="true"></i></span>
                        <div>
                            <div class="ags-tip-title"><?= $h($L['tip_ignore_title']) ?></div>
                            <p class="ags-tip-text"><?= $h($L['tip_ignore_text']) ?></p>
                        </div>
                    </div>
                    <div class="ags-tip">
                        <span class="ags-ico ags-ico-sm ags-soft-warning"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span>
                        <div>
                            <div class="ags-tip-title"><?= $h($L['tip_private_title']) ?></div>
                            <p class="ags-tip-text"><?= $h($L['tip_private_text']) ?></p>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="<?= $h_base ?>/faq.php" class="btn btn-sm btn-outline-info ags-pill">
                            <i class="fa-solid fa-book-open me-1" aria-hidden="true"></i><?= $h($L['btn_open_faq']) ?>
                        </a>
                        <a href="<?= $h_base ?>/tsf_forums/index.php" class="btn btn-sm btn-outline-success ags-pill">
                            <i class="fa-solid fa-comments me-1" aria-hidden="true"></i><?= $h($L['btn_open_forums']) ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?= $editor['modal'] ?>

<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $h_base ?>/scripts/contactstaff.js?ver=1"></script>

<?php stdfoot(); ?>