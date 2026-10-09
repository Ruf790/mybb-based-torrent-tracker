<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert"><strong>Access denied.</strong> Direct initialization of this file is not allowed.</div>');
}

use function htmlspecialchars as e;

global $lang;
$lang->load('ratio');

/**
 * Подстановка {1}, {2}… (и %1$s — в такой формат их переводит $lang->load()).
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $a) {
            $n = $i + 1;
            $map['{' . $n . '}'] = (string)$a;
            $map['%' . $n . '$s'] = (string)$a;
        }
        return strtr($str, $map);
    }
}

/**
 * «1.5 GB», «512MB», «1073741824», «2 TiB» → байты. null — если не разобрать.
 * Раньше подсказки в форме обещали ввод с единицами («1GB»), а сервер принимал
 * только голые числа и отвечал «must be numeric».
 */
function rt_parse_bytes(string $input): ?int
{
    $input = str_replace([',', ' '], ['.', ''], trim($input));
    if (!preg_match('/^(\d+(?:\.\d+)?)(B|KB|KIB|MB|MIB|GB|GIB|TB|TIB|PB|PIB)?$/i', $input, $m)) {
        return null;
    }
    $pow = ['B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4, 'PB' => 5][str_replace('I', '', strtoupper($m[2] ?? 'B'))] ?? 0;
    $bytes = (float)$m[1] * (1024 ** $pow);
    return $bytes > PHP_INT_MAX ? null : (int)round($bytes);
}

function rt_ratio(float $up, float $down): string
{
    return $down > 0 ? number_format($up / $down, 2) : '∞';
}

// ── AJAX: автокомплит ника ────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'search_user') {
    header('Content-Type: application/json; charset=utf-8');
    $term = trim((string)($_GET['term'] ?? ''));
    if (mb_strlen($term) < 2) { echo json_encode([]); exit; }

    $like  = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    $query = $db->sql_query_prepared(
        "SELECT id, username, usergroup, uploaded, downloaded FROM users WHERE username LIKE ? ORDER BY username ASC LIMIT 20",
        ['%' . $like . '%']
    );
    $results = [];
    while ($query && ($row = $db->fetch_array($query))) {
        $results[] = [
            'id'         => (int)$row['id'],
            'username'   => (string)$row['username'],
            'name_html'  => function_exists('format_name') ? format_name(e((string)$row['username']), (int)$row['usergroup']) : e((string)$row['username']),
            'uploaded'   => (int)$row['uploaded'],
            'downloaded' => (int)$row['downloaded'],
            'up_h'       => mksize((float)$row['uploaded']),
            'down_h'     => mksize((float)$row['downloaded']),
            'ratio'      => rt_ratio((float)$row['uploaded'], (float)$row['downloaded']),
        ];
    }
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

$error = null;
// Итог прошлого сохранения — показывается один раз после перенаправления
$done  = $_SESSION['rt_done'] ?? null;
unset($_SESSION['rt_done']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    try {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            throw new InvalidArgumentException($lang->ratio['err_csrf']);
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $upRaw    = trim((string)($_POST['uploaded'] ?? ''));
        $downRaw  = trim((string)($_POST['downloaded'] ?? ''));
        $mode     = in_array($_POST['mode'] ?? 'set', ['set', 'add', 'sub'], true) ? $_POST['mode'] : 'set';

        // Раньше empty() считал «0» пустым значением — обнулить downloaded было невозможно
        if ($username === '') {
            throw new InvalidArgumentException($lang->ratio['err_no_user']);
        }
        if ($upRaw === '' && $downRaw === '') {
            throw new InvalidArgumentException($lang->ratio['err_no_value']);
        }
        $upVal   = $upRaw   === '' ? null : rt_parse_bytes($upRaw);
        $downVal = $downRaw === '' ? null : rt_parse_bytes($downRaw);
        if (($upRaw !== '' && $upVal === null) || ($downRaw !== '' && $downVal === null)) {
            throw new InvalidArgumentException($lang->ratio['err_bad_value']);
        }

        $bq     = $db->sql_query_prepared("SELECT id, username, uploaded, downloaded FROM users WHERE username = ?", [$username]);
        $before = $bq ? $db->fetch_array($bq) : null;
        if (!$before) {
            throw new RuntimeException($lang->ratio['err_user_not_found']);
        }

        $oldUp   = (int)$before['uploaded'];
        $oldDown = (int)$before['downloaded'];
        $apply = static fn(int $old, ?int $v): int => $v === null ? $old : match ($mode) {
            'add'   => $old + $v,
            'sub'   => max(0, $old - $v),
            default => $v,
        };
        $newUp   = $apply($oldUp, $upVal);
        $newDown = $apply($oldDown, $downVal);

        if ($db->sql_query_prepared("UPDATE users SET uploaded = ?, downloaded = ? WHERE id = ?", [$newUp, $newDown, (int)$before['id']]) === false) {
            throw new RuntimeException($lang->ratio['err_db']);
        }

        write_log(sprintf(
            'Ratio Manager: %s changed stats of "%s" (UID %d): uploaded %s -> %s, downloaded %s -> %s',
            $CURUSER['username'] ?? 'staff', $before['username'], (int)$before['id'],
            mksize((float)$oldUp), mksize((float)$newUp), mksize((float)$oldDown), mksize((float)$newDown)
        ), 'ratio');

        $done = [
            'id' => (int)$before['id'], 'username' => (string)$before['username'],
            'oldUp' => $oldUp, 'newUp' => $newUp, 'oldDown' => $oldDown, 'newDown' => $newDown,
        ];

        // Post/Redirect/Get: раньше итог показывался прямо в ответ на POST, и F5
        // отправлял форму повторно — в режимах Add/Subtract изменение применялось ещё раз
        $_SESSION['rt_done'] = $done;
        function_exists('admin_redirect') ? admin_redirect($_this_script_) : header('Location: ' . $_this_script_);
        exit;
    } catch (InvalidArgumentException | RuntimeException $ex) {
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        $error = $lang->ratio['err_unexpected'];
        write_log('Ratio Manager error: ' . $ex->getMessage(), 'error');
    }
}

stdhead($lang->ratio['page_title']);
$self = (string)$_this_script_;
$v = static fn(string $p): string => (string)(defined('TSDIR') ? (@filemtime(TSDIR . $p) ?: 1) : 1);

// Строки для JS: js_* из ланга → без префикса
$jsLang = [];
foreach ((array)$lang->ratio as $k => $val) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = (string)$val;
    }
}
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/ratio.css?v=<?= $v('/include/templates/default/style/ratio.css') ?>">

<div class="container mt-3 mb-4 rt" data-self="<?= e($self) ?>">

    <div class="rt-card mb-3"><div class="rt-head">
        <span class="rt-head-icon"><i class="fa-solid fa-scale-balanced"></i></span>
        <div style="min-width:0">
            <h1 class="rt-title"><?= e($lang->ratio['page_title']) ?></h1>
            <div class="rt-sub"><?= e($lang->ratio['page_sub']) ?></div>
        </div>
    </div></div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex gap-2 rounded-4"><i class="fa-solid fa-circle-exclamation mt-1"></i><div><?= e($error) ?></div></div>
    <?php endif; ?>

    <?php if ($done):
        $profile = $BASEURL . '/' . get_profile_link($done['id']); ?>
    <!-- Раньше: сообщение и автоматический переход в профиль через 2 секунды,
         JS-редирект печатался ещё ДО шапки страницы -->
    <div class="rt-card rt-done mb-3">
        <span class="rt-done-icon"><i class="fa-solid fa-circle-check"></i></span>
        <h2 class="h5 fw-bold mb-1"><?= e(ags_fmt($lang->ratio['flash_done'], (string)$done['username'])) ?></h2>
        <div class="rt-muted mb-2">
            <i class="fa-solid fa-upload me-1"></i><?= mksize((float)$done['oldUp']) ?> → <strong><?= mksize((float)$done['newUp']) ?></strong>
            <span class="mx-2">·</span>
            <i class="fa-solid fa-download me-1"></i><?= mksize((float)$done['oldDown']) ?> → <strong><?= mksize((float)$done['newDown']) ?></strong>
            <span class="mx-2">·</span>
            <i class="fa-solid fa-scale-balanced me-1"></i><?= rt_ratio((float)$done['oldUp'], (float)$done['oldDown']) ?> → <strong><?= rt_ratio((float)$done['newUp'], (float)$done['newDown']) ?></strong>
        </div>
        <a href="<?= e($profile) ?>" class="btn btn-sm btn-outline-primary px-3"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= e($lang->ratio['btn_open_profile']) ?></a>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="post" action="<?= e($self) ?>" id="updateForm" class="rt-card h-100" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="my_post_key" value="<?= e((string)($mybb->post_code ?? '')) ?>">
                <div class="rt-sec-head"><span class="rt-sec-icon ic-blue"><i class="fa-solid fa-user-pen"></i></span>
                    <div><h2 class="rt-sec-title"><?= e($lang->ratio['sec_change']) ?></h2><div class="rt-muted"><?= e($lang->ratio['hint_units']) ?></div></div></div>
                <div class="p-3 p-md-4">
                    <div class="mb-3 position-relative">
                        <label for="username" class="form-label"><i class="fa-solid fa-user"></i><?= e($lang->ratio['lbl_user']) ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" name="username" id="username" value="<?= e((string)($_POST['username'] ?? '')) ?>" placeholder="<?= e($lang->ratio['ph_user']) ?>" autocomplete="off" required autofocus>
                        </div>
                        <div id="usernameSuggestions" class="list-group position-absolute w-100 d-none"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block"><i class="fa-solid fa-sliders"></i><?= e($lang->ratio['lbl_operation']) ?></label>
                        <div class="rt-seg" role="radiogroup">
                            <?php $m = $_POST['mode'] ?? 'set';
                            foreach (['set' => ['fa-equals', $lang->ratio['opt_mode_set']], 'add' => ['fa-plus', $lang->ratio['opt_mode_add']], 'sub' => ['fa-minus', $lang->ratio['opt_mode_sub']]] as $k => [$ic, $lbl]): ?>
                            <input type="radio" name="mode" id="mode_<?= $k ?>" value="<?= $k ?>" <?= $m === $k ? 'checked' : '' ?>><label for="mode_<?= $k ?>"><i class="fa-solid <?= $ic ?>"></i><?= e($lbl) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?php foreach (['uploaded' => ['fa-upload', $lang->ratio['lbl_uploaded'], 'text-success'], 'downloaded' => ['fa-download', $lang->ratio['lbl_downloaded'], 'text-danger']] as $f => [$ic, $lbl, $cls]): ?>
                        <div class="col-md-6">
                            <label for="<?= $f ?>" class="form-label"><i class="fa-solid <?= $ic ?> <?= $cls ?>"></i><?= e($lbl) ?></label>
                            <input type="text" class="form-control font-monospace" name="<?= $f ?>" id="<?= $f ?>" value="<?= e((string)($_POST[$f] ?? '')) ?>" placeholder="<?= e($lang->ratio['ph_value']) ?>" autocomplete="off">
                            <div class="rt-hint" id="<?= $f ?>Hint"><?= e($lang->ratio['hint_empty']) ?></div>
                            <div class="rt-chips" data-for="<?= $f ?>">
                                <?php foreach (['0', '1 GB', '10 GB', '50 GB', '100 GB', '1 TB'] as $v): ?><button type="button" class="rt-chip" data-v="<?= $v ?>"><?= $v ?></button><?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i><?= e($lang->ratio['btn_reset']) ?></button>
                        <button type="submit" class="btn btn-primary px-4" id="rtSave"><i class="fa-solid fa-floppy-disk me-1"></i><?= e($lang->ratio['btn_save']) ?></button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Превью: было «Current Database: MySQL» и «Last Updated» = текущее время -->
        <div class="col-lg-5">
            <div class="rt-card h-100">
                <div class="rt-sec-head"><span class="rt-sec-icon ic-amber"><i class="fa-solid fa-eye"></i></span>
                    <div><h2 class="rt-sec-title"><?= e($lang->ratio['sec_preview']) ?></h2><div class="rt-muted"><?= e($lang->ratio['sec_preview_sub']) ?></div></div></div>
                <div class="p-3 p-md-4">
                    <div id="rtEmpty" class="rt-empty"><i class="fa-solid fa-user-large"></i><?= e($lang->ratio['preview_empty']) ?></div>
                    <div id="rtPreview" hidden>
                        <div class="rt-user">
                            <span class="rt-avatar" id="rtAv">?</span>
                            <div style="min-width:0"><div class="fw-bold" id="rtName"></div><div class="rt-muted" id="rtMeta"></div></div>
                        </div>
                        <div class="rt-cmp">
                            <span class="l"><i class="fa-solid fa-upload text-success me-1"></i><?= e($lang->ratio['lbl_up_short']) ?></span><span class="v" id="rtUpOld"></span><span class="arrow"><i class="fa-solid fa-arrow-right-long"></i></span><span class="v" id="rtUpNew"></span>
                            <span class="l"><i class="fa-solid fa-download text-danger me-1"></i><?= e($lang->ratio['lbl_down_short']) ?></span><span class="v" id="rtDownOld"></span><span class="arrow"><i class="fa-solid fa-arrow-right-long"></i></span><span class="v" id="rtDownNew"></span>
                        </div>
                        <div class="rt-ratio-big">
                            <div class="text-center"><div class="k"><?= e($lang->ratio['lbl_ratio_now']) ?></div><div class="n" id="rtRatioOld">—</div></div>
                            <i class="fa-solid fa-arrow-right-long fa-lg text-body-secondary"></i>
                            <div class="text-center"><div class="k"><?= e($lang->ratio['lbl_ratio_after']) ?></div><div class="n" id="rtRatioNew">—</div></div>
                        </div>
                    </div>
                    <div class="alert alert-warning d-flex gap-2 rounded-4 mt-3 mb-0 small"><i class="fa-solid fa-triangle-exclamation mt-1"></i><div><?= e($lang->ratio['note_logged']) ?></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script>const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/admin/scripts/ratio.js?v=3"></script>
<?php
stdfoot();