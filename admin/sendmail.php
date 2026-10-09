<?php

declare(strict_types=1);

// Security check
if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

global $mybb, $BASEURL, $SITENAME, $CURUSER, $lang;

$lang->load('sendmail');

if (!defined('SM_VERSION')) {
    define('SM_VERSION', '0.5');
}
const SM_ASSET_VER = 3;

if (!function_exists('ags_fmt')) {
    /**
     * Substitute {1}, {2}… (and %1$s, %2$s… produced by $lang->load()) in a lang string
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $v) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string)$v;
            $map['%' . $n . '$s'] = (string)$v;
        }
        return strtr($str, $map);
    }
}

if (!function_exists('strip_selected_tags')) {
    /**
     * Strip selected HTML tags from text
     */
    function strip_selected_tags(string $text, array $tags = []): string
    {
        $args = func_get_args();
        $text = array_shift($args);
        $tags = func_num_args() > 2 ? array_diff($args, [$text]) : $tags;

        foreach ($tags as $tag) {
            while (preg_match('/<' . $tag . '(|\W[^>]*)>(.*)<\/' . $tag . '>/iusU', $text, $found)) {
                $text = str_replace($found[0], $found[2], $text);
            }
        }

        $pattern = '/(<(' . implode('|', $tags) . ')(|\W.*)\/>)/iusU';
        return preg_replace($pattern, '', $text);
    }
}

if (!function_exists('html2txt')) {
    /**
     * Convert HTML to plain text
     */
    function html2txt(string $document): string
    {
        $patterns = [
            '@<script[^>]*?>.*?</script>@si',
            '@<style[^>]*?>.*?</style>@siU',
            '@<![\s\S]*?--[\t\n\r]*>@',
        ];

        return preg_replace($patterns, '', $document);
    }
}

// ---------------------------------------------------------------------------
// Input
// ---------------------------------------------------------------------------
$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$pageUrl    = $scriptName . '?act=sendmail';

$email   = trim((string)($_POST['email'] ?? $_GET['email'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$msgtext = trim((string)($_POST['message'] ?? ''));
$errors  = [];

// ---------------------------------------------------------------------------
// Form submission (Post/Redirect/Get on success)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_post_check((string)$mybb->get_input('my_post_key'), true)) {
        $errors[] = $lang->sendmail['err_token'];
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = $lang->sendmail['err_email'];
    }
    if ($subject === '') {
        $errors[] = $lang->sendmail['err_subject'];
    }
    if (mb_strlen($msgtext) < 6) {
        $errors[] = ags_fmt($lang->sendmail['err_message_short'], 6);
    }

    if (!$errors) {
        $sent = my_mail($email, $subject, $msgtext, '', '', '', false, 'html', '');

        if ($sent) {
            write_log(sprintf(
                'Staff email sent to %s by %s (subject: %s)',
                $email,
                (string)$CURUSER['username'],
                $subject
            ));
            header('Location: ' . $pageUrl . '&sent=1');
            exit;
        }

        $errors[] = $lang->sendmail['err_mail_server'];
    }
}

$justSent = ($_GET['sent'] ?? '') === '1';

$e = static fn(string $s): string => htmlspecialchars_uni($s);

// JS strings: js_* keys without the prefix
$jsLang = [];
foreach ($lang->sendmail as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = (string)$v;
    }
}

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------
stdhead($lang->sendmail['page_title'], true, '', '');
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/sendmail.css?ver=<?= SM_ASSET_VER ?>">

<div class="sm-page container-xl py-3">

    <!-- Header -->
    <div class="sm-head mb-3">
        <div class="sm-head-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1><?= $e($lang->sendmail['head_title']) ?></h1>
            <p><?= $e(ags_fmt($lang->sendmail['head_subtitle'], (string)$SITENAME)) ?></p>
        </div>
        <span class="sm-version d-none d-sm-inline-flex"><i class="fa-solid fa-code-branch"></i> v<?= SM_VERSION ?></span>
    </div>

    <?php if ($justSent): ?>
        <div class="sm-alert sm-alert-success mb-3" role="status">
            <i class="fa-solid fa-circle-check"></i>
            <div><strong><?= $e($lang->sendmail['flash_sent_title']) ?></strong> <?= $e($lang->sendmail['flash_sent_text']) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="sm-alert sm-alert-danger mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong><?= $e($lang->sendmail['flash_fail_title']) ?></strong>
                <ul class="mb-0 mt-1 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= $e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= $e($scriptName) ?>" id="mailForm" class="needs-validation" novalidate>
        <input type="hidden" name="act" value="sendmail">
        <input type="hidden" name="my_post_key" value="<?= $e((string)$mybb->post_code) ?>">

        <div class="row g-3">

            <!-- Compose -->
            <div class="col-lg-7">
                <section class="sm-panel h-100">
                    <header class="sm-panel-head">
                        <span class="sm-chip sm-chip-primary"><i class="fa-solid fa-pen-nib"></i></span>
                        <?= $e($lang->sendmail['pane_compose']) ?>
                    </header>

                    <div class="sm-panel-body">
                        <div class="mb-3">
                            <label for="emailInput" class="form-label"><?= $e($lang->sendmail['lbl_recipient']) ?></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                <input type="email" name="email" id="emailInput"
                                       value="<?= $e($email) ?>"
                                       class="form-control" placeholder="<?= $e($lang->sendmail['ph_recipient']) ?>"
                                       autocomplete="off" required>
                                <div class="invalid-feedback"><?= $e($lang->sendmail['inv_email']) ?></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="subjectInput" class="form-label"><?= $e($lang->sendmail['lbl_subject']) ?></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="fa-solid fa-heading"></i></span>
                                <input type="text" name="subject" id="subjectInput"
                                       value="<?= $e($subject) ?>"
                                       class="form-control" placeholder="<?= $e($lang->sendmail['ph_subject']) ?>"
                                       maxlength="200" required>
                                <div class="invalid-feedback"><?= $e($lang->sendmail['inv_subject']) ?></div>
                            </div>
                        </div>

                        <div>
                            <div class="d-flex align-items-end justify-content-between mb-2">
                                <label for="messageInput" class="form-label mb-0"><?= $e($lang->sendmail['lbl_message']) ?></label>
                                <span class="sm-counter" id="smCounter">
                                    <i class="fa-solid fa-keyboard"></i>
                                    <span id="smCount">0</span> <?= $e($lang->sendmail['lbl_counter_suffix']) ?>
                                </span>
                            </div>

                            <div class="sm-toolbar" role="toolbar" aria-label="<?= $e($lang->sendmail['aria_toolbar']) ?>">
                                <button type="button" class="btn" data-sm-tag="bold" title="<?= $e($lang->sendmail['tip_bold']) ?>"><i class="fa-solid fa-bold"></i></button>
                                <button type="button" class="btn" data-sm-tag="italic" title="<?= $e($lang->sendmail['tip_italic']) ?>"><i class="fa-solid fa-italic"></i></button>
                                <button type="button" class="btn" data-sm-tag="underline" title="<?= $e($lang->sendmail['tip_underline']) ?>"><i class="fa-solid fa-underline"></i></button>
                                <span class="sm-sep"></span>
                                <button type="button" class="btn" data-sm-tag="heading" title="<?= $e($lang->sendmail['tip_heading']) ?>"><i class="fa-solid fa-heading"></i></button>
                                <button type="button" class="btn" data-sm-tag="paragraph" title="<?= $e($lang->sendmail['tip_paragraph']) ?>"><i class="fa-solid fa-paragraph"></i></button>
                                <button type="button" class="btn" data-sm-tag="list" title="<?= $e($lang->sendmail['tip_list']) ?>"><i class="fa-solid fa-list-ul"></i></button>
                                <span class="sm-sep"></span>
                                <button type="button" class="btn" data-sm-tag="link" title="<?= $e($lang->sendmail['tip_link']) ?>"><i class="fa-solid fa-link"></i></button>
                                <button type="button" class="btn" data-sm-tag="image" title="<?= $e($lang->sendmail['tip_image']) ?>"><i class="fa-regular fa-image"></i></button>
                                <button type="button" class="btn" data-sm-tag="hr" title="<?= $e($lang->sendmail['tip_hr']) ?>"><i class="fa-solid fa-minus"></i></button>
                                <button type="button" class="btn" data-sm-tag="br" title="<?= $e($lang->sendmail['tip_br']) ?>"><i class="fa-solid fa-turn-down fa-flip-horizontal"></i></button>
                            </div>
                            <textarea name="message" id="messageInput" rows="12"
                                      class="form-control sm-editor"
                                      placeholder="<?= $e($lang->sendmail['ph_message']) ?>"
                                      minlength="6" required><?= $e($msgtext) ?></textarea>
                            <div class="invalid-feedback"><?= $e(ags_fmt($lang->sendmail['inv_message'], 6)) ?></div>
                            <div class="sm-help mt-2">
                                <i class="fa-solid fa-circle-info"></i>
                                <?= $e($lang->sendmail['hint_html']) ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Live preview -->
            <div class="col-lg-5 sm-preview-col">
                <section class="sm-panel sm-mail">
                    <div class="sm-airmail" aria-hidden="true"></div>
                    <header class="sm-panel-head">
                        <span class="sm-chip sm-chip-info"><i class="fa-solid fa-eye"></i></span>
                        <?= $e($lang->sendmail['pane_preview']) ?>
                    </header>
                    <dl class="sm-meta">
                        <dt><i class="fa-solid fa-user-shield"></i> <?= $e($lang->sendmail['lbl_from']) ?></dt>
                        <dd><?= $e((string)$SITENAME) ?></dd>
                        <dt><i class="fa-solid fa-inbox"></i> <?= $e($lang->sendmail['lbl_to']) ?></dt>
                        <dd id="smPvTo"></dd>
                        <dt><i class="fa-solid fa-tag"></i> <?= $e($lang->sendmail['lbl_subject']) ?></dt>
                        <dd id="smPvSubject" class="sm-subject"></dd>
                    </dl>
                    <div class="sm-frame-wrap">
                        <iframe id="smPvFrame" sandbox="" title="<?= $e($lang->sendmail['title_preview_frame']) ?>" loading="lazy"></iframe>
                    </div>
                </section>
            </div>
        </div>

        <!-- Sticky action bar -->
        <div class="sm-actionbar">
            <span class="sm-hint">
                <i class="fa-solid fa-bolt"></i>
                <?= $lang->sendmail['hint_send_html'] ?>
            </span>
            <button type="button" id="smReset" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fa-solid fa-rotate-left me-2"></i><?= $e($lang->sendmail['btn_reset']) ?>
            </button>
            <button type="submit" id="smSend" class="btn btn-primary rounded-pill px-4">
                <i class="fa-solid fa-paper-plane me-2"></i><?= $e($lang->sendmail['btn_send']) ?>
            </button>
        </div>
    </form>

    <!-- Loading overlay (id kept for ts_show) -->
    <div id="loading-layer" class="loading-overlay" role="status" aria-live="polite">
        <div class="loading-content">
            <i class="fa-solid fa-paper-plane sm-fly"></i>
            <div class="loading-text fw-semibold" id="loading-layer-text"><?= $e($lang->sendmail['loading_sending']) ?></div>
        </div>
    </div>
</div>

<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/admin/scripts/sendmail.js?ver=<?= SM_ASSET_VER ?>"></script>
<?php
stdfoot();