<?php
declare(strict_types=1);

require_once 'global.php';
require_once 'cache/smilies.php';
require_once INC_PATH . '/class_parser.php';

$parser         = new postParser;
$parser_options = [
    'allow_html'      => 1, 'allow_mycode'    => 1,
    'allow_smilies'   => 1, 'allow_imgcode'   => 1,
    'allow_videocode' => 1, 'filter_badwords' => 1,
];

gzip();
maxsysop();
define('STF_VERSION', '0.6');

$lang->load('contactstaff');

$BASE = htmlspecialchars($BASEURL, ENT_QUOTES, 'UTF-8');



// ── AJAX POST ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // FIX: CSRF проверка
    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        echo json_encode(['success' => false, 'message' => $lang->global['invalid_post_code'] ?? 'Invalid request.']);
        exit;
    }

    $msgtext = trim($_POST['msgtext'] ?? '');
    $subject = trim($_POST['subject'] ?? '');

    if ($msgtext === '' || $subject === '') {
        echo json_encode(['success' => false, 'message' => $lang->global['dontleavefieldsblank'] ?? 'Please fill in all fields.']);
        exit;
    }

    // FIX: flood check внутри POST, а не до него
    $q = $db->sql_query(
        'SELECT added FROM staffmessages WHERE sender = ' . (int)$CURUSER['id'] . ' ORDER BY added DESC LIMIT 1'
    );
    if ($db->num_rows($q) > 0) {
        $row = $db->fetch_array($q);
        flood_check($lang->contactstaff['floodcomment'] ?? '', (string)$row['added']);
    }

    $db->insert_query('staffmessages', [
        'sender'  => (int)$CURUSER['id'],
        'added'   => TIMENOW,
        'msg'     => $db->escape_string($msgtext),
        'subject' => $db->escape_string($subject),
    ]);

    echo json_encode(['success' => true, 'message' => $lang->global['msgsend'] ?? 'Message sent.']);
    exit;
}

// ── Предзаполнение subject из GET ────────────────────────────────────────────
$subject = '';
if (($_GET['subject'] ?? '') === 'invalid_link') {
    $link = $_GET['link'] ?? '';
    // FIX: проверяем что ссылка — реально URL, а не произвольная строка
    if (filter_var($link, FILTER_VALIDATE_URL) && str_starts_with($link, 'http')) {
        $link    = htmlspecialchars_uni(str_replace('http://referhide.com/?g=', '', $link));
        $subject = sprintf($lang->contactstaff['invalidlink'] ?? 'Invalid link: %s', $link);
    }
}

// ── returnto ─────────────────────────────────────────────────────────────────
$returnto = isset($_GET['returnto'])
    ? fix_url($_GET['returnto'])
    : fix_url($_SERVER['HTTP_REFERER'] ?? '');

// ── Вывод страницы ────────────────────────────────────────────────────────────
stdhead($lang->contactstaff['contactstaff'] ?? 'Contact Staff');
;

$postCode   = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');
$h_subject  = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$h_returnto = htmlspecialchars($returnto, ENT_QUOTES, 'UTF-8');
$h_script   = htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8');
$js_smilies = json_encode($smilies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div id="formToast" class="toast align-items-center text-white border-0" role="alert"
         aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="formToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<div class="container mt-3">

    <div class="alert alert-warning mb-3" role="alert">
        <?= $lang->contactstaff['info'] ?? '' ?>
    </div>

    <form id="staffForm" method="post" action="<?= $h_script ?>">
        <input type="hidden" name="my_post_key" value="<?= $postCode ?>">
        <input type="hidden" name="returnto"    value="<?= $h_returnto ?>">

        <div class="mb-3">
            <label for="subject" class="form-label fw-semibold">
                <?= htmlspecialchars($lang->contactstaff['subject'] ?? 'Subject', ENT_QUOTES, 'UTF-8') ?>
            </label>
            <input type="text" class="form-control" id="subject" name="subject"
                   value="<?= $h_subject ?>" maxlength="200" required>
        </div>

        <div class="mb-2">
            <label class="form-label fw-semibold">
                <?= htmlspecialchars($lang->contactstaff['message'] ?? 'Message', ENT_QUOTES, 'UTF-8') ?>
            </label>
        </div>

        <link rel="stylesheet" href="<?= $BASE ?>/include/templates/default/style/bbcode.css">
        <script>const smilies = <?= $js_smilies ?>;</script>
        <script src="<?= $BASE ?>/scripts/bbcode_tools.js"></script>

        <!-- BBCode toolbar -->
        <div class="mb-2 d-flex flex-wrap gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[b]','[/b]','staffMessage')"><b>B</b></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[i]','[/i]','staffMessage')"><i>I</i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[u]','[/u]','staffMessage')"><u>U</u></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[s]','[/s]','staffMessage')"><s>S</s></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[url]','[/url]','staffMessage')">URL</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[img]','[/img]','staffMessage')">IMG</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[quote]','[/quote]','staffMessage')">Quote</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[code]','[/code]','staffMessage')">Code</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[spoiler]','[/spoiler]','staffMessage')">Spoiler</button>
            <div class="btn-group position-relative">
                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle bbcode-color-btn"
                        data-textarea="staffMessage">🎨</button>
                <div class="color-palette d-none"></div>
            </div>
            <div class="btn-group position-relative">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="smileyBtn">😊</button>
                <div class="smiley-panel d-none border p-2 bg-body shadow-sm position-absolute"
                     id="smileyPanel" style="z-index:1000;"></div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="togglePreviewBtn">Preview</button>
        </div>

        <div class="mb-3">
            <textarea class="form-control" id="staffMessage" name="msgtext"
                      rows="8" maxlength="1000"
                      placeholder="<?= htmlspecialchars($lang->contactstaff['write_message'] ?? 'Write your message…', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($_POST['msgtext'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            <div id="charCount" class="form-text text-end">0 / 1000</div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane me-2"></i>
            <?= htmlspecialchars($lang->contactstaff['sendmessage'] ?? 'Send Message', ENT_QUOTES, 'UTF-8') ?>
        </button>
    </form>

</div>

<script>
(function () {
    'use strict';

    // ── Счётчик символов ──────────────────────────────────────────────────
    var textarea  = document.getElementById('staffMessage');
    var charCount = document.getElementById('charCount');
    var maxLen    = parseInt(textarea.getAttribute('maxlength'), 10) || 1000;

    function updateCount() {
        var len = textarea.value.length;
        charCount.textContent = len + ' / ' + maxLen;
        charCount.classList.toggle('text-danger', len >= maxLen);
    }
    textarea.addEventListener('input', updateCount);
    updateCount();

    // ── AJAX отправка формы ───────────────────────────────────────────────
    document.getElementById('staffForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var form     = this;
        var formData = new FormData(form);
        formData.append('ajax', '1');

        fetch(form.action, { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                showToast(data.message, data.success ? 'success' : 'danger');
                if (data.success) { form.reset(); updateCount(); }
            })
            .catch(function () {
                showToast('Submission failed. Please try again.', 'danger');
            });
    });

    // ── Toast ─────────────────────────────────────────────────────────────
    function showToast(message, type) {
        var el   = document.getElementById('formToast');
        var body = document.getElementById('formToastBody');
        el.className = 'toast align-items-center text-white bg-' + (type || 'success') + ' border-0';
        body.textContent = message;
        new bootstrap.Toast(el).show();
    }

}());
</script>

<?php stdfoot(); ?>