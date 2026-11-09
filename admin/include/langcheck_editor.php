<?php
declare(strict_types=1);

/**
 * Lang Checker — вкладка «Редактор» (только Sysop).
 * Подключается из admin/langcheck.php, ДО сканирования проекта.
 *
 * Ланг-файлы — это PHP, поэтому:
 *  - файл не исполняется, значения читаются токенайзером;
 *  - при сохранении заменяется только один строковый литерал, комментарии и порядок ключей остаются;
 *  - новое значение пишется только через var_export();
 *  - перед записью результат проверяется разбором (TOKEN_PARSE).
 */

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert"><strong>Access denied.</strong> Direct initialization of this file is not allowed.</div>');
}

use function htmlspecialchars as e;

const LCE_MAX_VALUE    = 65535;
const LCE_SEARCH_LIMIT = 200;

/** Значение строкового литерала PHP (токен T_CONSTANT_ENCAPSED_STRING) */
function lce_decode(string $raw): string
{
    if ($raw[0] === 'b' || $raw[0] === 'B') {
        $raw = substr($raw, 1);
    }
    $body = substr($raw, 1, -1);
    if ($raw[0] === "'") {
        return strtr($body, ['\\\\' => '\\', "\\'" => "'"]);
    }
    $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"'];
    return (string)preg_replace_callback(
        '/\\\\(?:([nrtvef\\\\$"])|([0-7]{1,3})|x([0-9A-Fa-f]{1,2})|u\{([0-9A-Fa-f]+)\})/',
        static function (array $m) use ($map): string {
            if (($m[1] ?? '') !== '') return $map[$m[1]];
            if (($m[2] ?? '') !== '') return chr(octdec($m[2]) & 0xFF);
            if (($m[3] ?? '') !== '') return chr(hexdec($m[3]));
            return (string)mb_chr((int)hexdec($m[4]), 'UTF-8');
        },
        $body
    );
}

function lce_rev(string $raw): string
{
    return substr(sha1($raw), 0, 12);
}

/**
 * Разбор ланг-файла без исполнения.
 * null — формат не поддерживается (нет ровно одного $language['x'] = array(...)).
 * items[key] = value, raw, off, len (литерал значения), ro, key_off, after, comma, sec.
 */
function lce_parse(string $src): ?array
{
    if (!mb_check_encoding($src, 'UTF-8')) return null;
    try {
        $tokens = token_get_all($src);
    } catch (\Throwable) {
        return null;
    }

    $sig = [];        // значимые токены: [id, текст, смещение]
    $notes = [];      // индекс следующего значимого токена => текст раздела из комментария
    $off = 0;
    foreach ($tokens as $t) {
        [$id, $txt] = is_array($t) ? [$t[0], $t[1]] : [$t, $t];
        if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
            if (preg_match('/──\s*(.+?)\s*──/u', $txt, $m)) $notes[count($sig)] = $m[1];
        } elseif ($id !== T_WHITESPACE) {
            $sig[] = [$id, $txt, $off];
        }
        $off += strlen($txt);
    }

    $n = count($sig);
    $starts = [];
    for ($i = 0; $i + 6 < $n; $i++) {
        if ($sig[$i][0] !== T_VARIABLE || $sig[$i][1] !== '$language' || $sig[$i + 1][1] !== '['
            || $sig[$i + 2][0] !== T_CONSTANT_ENCAPSED_STRING || $sig[$i + 3][1] !== ']' || $sig[$i + 4][1] !== '=') {
            continue;
        }
        if ($sig[$i + 5][0] === T_ARRAY && $sig[$i + 6][1] === '(') {
            $starts[] = $i + 7;
        } elseif ($sig[$i + 5][1] === '[') {
            $starts[] = $i + 6;
        }
    }
    if (count($starts) !== 1) return null;

    $items = [];
    $sec   = null;
    $open  = [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, '(', '[', '{'];
    $i     = $starts[0];
    $close = null;

    while ($i < $n) {
        $start = $i;
        $depth = 0;
        $comma = false;
        for (; $i < $n; $i++) {
            $id = $sig[$i][0];
            if (in_array($id, $open, true)) { $depth++; continue; }
            if ($id === ')' || $id === ']' || $id === '}') {
                if ($depth === 0) { $close = $sig[$i][2]; break; }
                $depth--;
                continue;
            }
            if ($id === ',' && $depth === 0) { $comma = true; break; }
        }
        if ($i >= $n) return null;                                    // массив не закрыт

        foreach ($notes as $at => $label) {                           // комментарии-разделы перед элементом
            if ($at <= $start) { $sec = $label; unset($notes[$at]); }
        }

        $count = $i - $start;
        if ($count >= 3 && $sig[$start][0] === T_CONSTANT_ENCAPSED_STRING && $sig[$start + 1][0] === T_DOUBLE_ARROW) {
            $key  = lce_decode($sig[$start][1]);
            $last = $sig[$i - 1];
            $lit  = $count === 3 && $sig[$start + 2][0] === T_CONSTANT_ENCAPSED_STRING ? $sig[$start + 2] : null;
            $items[$key] = [
                'value'   => $lit !== null ? lce_decode($lit[1]) : null,
                'raw'     => $lit[1] ?? '',
                'off'     => $lit[2] ?? 0,
                'len'     => $lit !== null ? strlen($lit[1]) : 0,
                'ro'      => $lit === null,
                'key_off' => $sig[$start][2],
                'after'   => $comma ? $sig[$i][2] + 1 : $last[2] + strlen($last[1]),
                'comma'   => $comma,
                'sec'     => $sec,
            ];
            $sec = null;
        }
        if (!$comma) break;
        $i++;
    }

    return $close === null ? null : ['items' => $items, 'close' => $close];
}

/** Вставка нового ключа: после ближайшего предыдущего ключа эталонного порядка, иначе в конец массива */
function lce_insert(string $src, array $parsed, string $key, string $literal, array $order): string
{
    $eol   = str_contains($src, "\r\n") ? "\r\n" : "\n";
    $items = $parsed['items'];
    $entry = var_export($key, true) . ' => ' . $literal . ',';

    $anchor = null;
    $pos = array_search($key, $order, true);
    if ($pos !== false) {
        for ($j = (int)$pos - 1; $j >= 0 && $anchor === null; $j--) {
            $anchor = $items[$order[$j]] ?? null;
        }
    }
    if ($anchor === null && $items) {
        $anchor = $items[array_key_last($items)];
    }
    if ($anchor === null) {
        return substr_replace($src, "\t" . $entry . $eol, $parsed['close'], 0);
    }

    $lineStart = strrpos(substr($src, 0, $anchor['key_off']), "\n");
    $lineStart = $lineStart === false ? 0 : $lineStart + 1;
    preg_match('/^[ \t]*/', substr($src, $lineStart, $anchor['key_off'] - $lineStart), $ind);

    $at = $anchor['after'];
    if (!$anchor['comma']) {
        $src = substr_replace($src, ',', $at, 0);
        $at++;
    }
    // Комментарий на той же строке остаётся у своего ключа
    if (preg_match('~\G[ \t]*(?://|#(?!\[))[^\r\n]*~', $src, $m, 0, $at)) {
        $at += strlen($m[0]);
    }
    return substr_replace($src, $eol . $ind[0] . $entry, $at, 0);
}

/** Подпись плейсхолдеров: номера {1} / %1$s и количество простых %s / %d */
function lce_specs(string $s): string
{
    $s = str_replace('%%', '', $s);
    preg_match_all('/\{(\d+)\}|%(\d+)\$[sd]/', $s, $m);
    $nums = array_map('intval', array_filter(array_merge($m[1], $m[2]), 'strlen'));
    $nums = array_values(array_unique($nums));
    sort($nums);
    $bare = preg_match_all('/%[sd]/', (string)preg_replace('/%\d+\$[sd]/', '', $s));
    return implode(',', $nums) . '|' . $bare;
}

function lce_has_stray_percent(string $s): bool
{
    return str_contains((string)preg_replace('/%%|%\d+\$[sd]|%[sd]/', '', $s), '%');
}

/**
 * Проверка значения. $bases — старое значение и значения других языков.
 * Лишний плейсхолдер ломает подстановку, а одиночный % в строке для sprintf() роняет страницу (ValueError).
 * null — всё в порядке, иначе [код ошибки, данные для текста].
 */
function lce_validate(string $value, array $bases): ?array
{
    if (!mb_check_encoding($value, 'UTF-8')) return ['err_ed_control', []];
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) return ['err_ed_control', []];
    if (strlen($value) > LCE_MAX_VALUE) return ['err_ed_too_long', [LCE_MAX_VALUE]];
    if ($value === '') return null;

    $bases = array_values(array_filter($bases, static fn($b) => is_string($b) && $b !== ''));
    if (!$bases) return null;

    $spec    = lce_specs($value);
    $allowed = array_unique(array_map('lce_specs', $bases));
    if (!in_array($spec, $allowed, true)) {
        return ['err_ed_placeholders', [lce_specs_text($allowed[0])]];
    }
    if ($spec !== '|0' && lce_has_stray_percent($value)
        && !array_filter($bases, 'lce_has_stray_percent')) {
        return ['err_ed_percent', []];
    }
    return null;
}

function lce_specs_text(string $spec): string
{
    [$nums, $bare] = explode('|', $spec);
    $out = $nums === '' ? [] : array_map(static fn($n) => '{' . $n . '}', explode(',', $nums));
    for ($i = 0; $i < (int)$bare; $i++) $out[] = '%s';
    return $out ? implode(' ', $out) : '—';
}

function lce_path(string $langDir, string $l, string $page): string
{
    return "{$langDir}/{$l}/{$page}.lang.php";
}

function lce_read(string $file): ?array
{
    if (!is_file($file)) return null;
    $src = @file_get_contents($file);
    return $src === false ? null : lce_parse($src);
}

/** Запись через временный файл (как ags_write_file в managesettings.php) */
function lce_write(string $path, string $content): bool
{
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    $ok  = file_put_contents($tmp, $content, LOCK_EX) !== false;
    if ($ok && !@rename($tmp, $path)) {
        // Windows может не дать заменить открытый файл — тогда прямая запись
        @unlink($tmp);
        $ok = file_put_contents($path, $content, LOCK_EX) !== false;
    } elseif (!$ok) {
        @unlink($tmp);
    }
    if ($ok) {
        clearstatcache(true, $path);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($path, true);
    }
    return $ok;
}

/** Список страниц (объединение всех языков) */
function lce_pages(string $langDir, array $langs): array
{
    $out = [];
    foreach ($langs as $l) {
        foreach (glob("{$langDir}/{$l}/*.lang.php") ?: [] as $f) $out[basename($f, '.lang.php')] = true;
    }
    $out = array_map('strval', array_keys($out));
    sort($out);
    return $out;
}

/** Строки для таблицы: ключи в порядке эталона, затем ключи, которых в нём нет */
function lce_file_data(string $langDir, array $langs, string $page): array
{
    $files = [];
    $state = [];
    foreach ($langs as $l) {
        $path = lce_path($langDir, $l, $page);
        if (!is_file($path)) { $state[$l] = 'nofile'; continue; }
        $parsed = lce_read($path);
        if ($parsed === null) { $state[$l] = 'unsupported'; continue; }
        $state[$l] = 'ok';
        $files[$l] = $parsed['items'];
    }

    $keys = [];
    foreach ($files as $items) {
        foreach ($items as $k => $it) $keys[(string)$k] ??= $it['sec'];
    }

    $rows = [];
    foreach ($keys as $k => $sec) {
        $k = (string)$k;
        $cells = [];
        foreach ($langs as $l) {
            if (!isset($files[$l])) continue;
            $it = $files[$l][$k] ?? null;
            $cells[$l] = $it === null
                ? ['v' => null, 'r' => '', 'ro' => false]
                : ['v' => $it['value'], 'r' => lce_rev($it['raw']), 'ro' => $it['ro']];
        }
        $rows[] = ['k' => $k, 'sec' => $sec, 'c' => $cells];
    }
    return ['ok' => true, 'page' => $page, 'state' => $state, 'rows' => $rows];
}

/** Поиск по ключам и текстам всех файлов */
function lce_search(string $langDir, array $langs, string $q): array
{
    $hits = [];
    $more = false;
    foreach (lce_pages($langDir, $langs) as $page) {
        foreach ($langs as $l) {
            $parsed = lce_read(lce_path($langDir, $l, $page));
            if ($parsed === null) continue;
            foreach ($parsed['items'] as $k => $it) {
                $k = (string)$k;
                $id = $page . '|' . $k;
                if (isset($hits[$id])) continue;
                $value = (string)($it['value'] ?? '');
                if (mb_stripos($k, $q) === false && mb_stripos($value, $q) === false) continue;
                if (count($hits) >= LCE_SEARCH_LIMIT) { $more = true; break 3; }
                $hits[$id] = ['page' => $page, 'key' => $k, 'lang' => $l, 'text' => mb_strimwidth($value, 0, 160, '…')];
            }
        }
    }
    return ['ok' => true, 'hits' => array_values($hits), 'more' => $more];
}

/**
 * Сохранение одного ключа. Под блокировкой: файл перечитывается, поэтому правки разных ключей не затирают друг друга,
 * а $rev показывает, не поменял ли кто-то этот же ключ.
 * $rev === '' — ключа не было, он добавляется.
 */
function lce_save(string $langDir, array $langs, string $ref, string $page, string $l, string $key, string $value, string $rev): array
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $path  = lce_path($langDir, $l, $page);
    if (!is_file($path)) return ['ok' => false, 'code' => 'err_ed_unsupported'];

    $lock = @fopen(sys_get_temp_dir() . '/ag_lang_editor.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) return ['ok' => false, 'code' => 'err_ed_write'];

    try {
        $src    = (string)file_get_contents($path);
        $parsed = lce_parse($src);
        if ($parsed === null) return ['ok' => false, 'code' => 'err_ed_unsupported'];

        $item = $parsed['items'][$key] ?? null;
        if ($item !== null && $item['ro']) return ['ok' => false, 'code' => 'err_ed_readonly'];

        $currentRev = $item !== null ? lce_rev($item['raw']) : '';
        if ($rev !== $currentRev) {
            return ['ok' => false, 'code' => 'conflict', 'rev' => $currentRev, 'current' => $item['value'] ?? null];
        }

        $others = [];
        $order  = [];
        foreach ($langs as $other) {
            if ($other === $l) continue;
            $p = lce_read(lce_path($langDir, $other, $page));
            if ($p === null) continue;
            if ($other === $ref || !$order) $order = array_map('strval', array_keys($p['items']));
            if (isset($p['items'][$key])) $others[] = $p['items'][$key]['value'];
        }

        if ($item === null) {
            // Новые ключи не создаются: только те, что уже есть в другом языке
            if (!$others) return ['ok' => false, 'code' => 'err_ed_no_key'];
            if ($value === '') return ['ok' => false, 'code' => 'err_ed_empty_new'];
        }

        $bad = lce_validate($value, array_merge($others, [$item['value'] ?? null]));
        if ($bad !== null) return ['ok' => false, 'code' => $bad[0], 'args' => $bad[1]];

        if ($item !== null && $item['value'] === $value) {
            return ['ok' => true, 'rev' => $currentRev, 'value' => $value];
        }

        $literal = var_export($value, true);
        $new = $item !== null
            ? substr_replace($src, $literal, $item['off'], $item['len'])
            : lce_insert($src, $parsed, $key, $literal, $order);

        try {
            token_get_all($new, TOKEN_PARSE);
        } catch (\Throwable) {
            return ['ok' => false, 'code' => 'err_ed_write'];
        }
        $check = lce_parse($new);
        if ($check === null
            || count($check['items']) !== count($parsed['items']) + ($item === null ? 1 : 0)
            || ($check['items'][$key]['value'] ?? null) !== $value) {
            return ['ok' => false, 'code' => 'err_ed_write'];
        }

        if (!lce_write($path, $new)) return ['ok' => false, 'code' => 'err_ed_write'];

        return ['ok' => true, 'rev' => lce_rev($literal), 'value' => $value, 'added' => $item === null];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function lce_json(array $data, int $status = 200): never
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function lce_run(?string $langDir, array $langs, string $ref, string $self): void
{
    global $lang, $mybb, $CURUSER, $BASEURL;

    $lang->load('langcheck');
    $L    = $lang->langcheck;
    $ajax = (string)($_GET['ajax'] ?? '');
    $page = (string)($_REQUEST['page'] ?? '');
    $sysop = (int)($CURUSER['usergroup'] ?? 0) === UC_SYSOP;

    // Группы в staffpanel могут открыть сам Lang Checker, а редактор — только Sysop
    if (!$sysop) {
        if ($ajax !== '') lce_json(['ok' => false, 'error' => $L['err_ed_perm'] ?? 'Sysop only.'], 403);
        print_no_permission(true);
    }

    if ($ajax !== '') {
        $pageOk = preg_match('/^[A-Za-z0-9_\-]+$/', $page) === 1;
        if ($langDir === null) lce_json(['ok' => false, 'error' => $L['err_no_lang_dir'] ?? 'Language folder not found.']);

        if ($ajax === 'file') {
            if (!$pageOk) lce_json(['ok' => false, 'error' => $L['err_ed_bad_request'] ?? 'Bad request.'], 400);
            lce_json(lce_file_data($langDir, $langs, $page));
        }

        if ($ajax === 'search') {
            $q = trim((string)($_GET['q'] ?? ''));
            if (mb_strlen($q) < 2) lce_json(['ok' => true, 'hits' => [], 'more' => false]);
            lce_json(lce_search($langDir, $langs, $q));
        }

        if ($ajax === 'save') {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                lce_json(['ok' => false, 'error' => $L['err_ed_bad_request'] ?? 'Bad request.'], 405);
            }
            if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
                lce_json(['ok' => false, 'error' => $L['err_ed_csrf'] ?? 'Security token expired. Reload the page.'], 403);
            }
            $l     = (string)($_POST['lang'] ?? '');
            $key   = (string)($_POST['key'] ?? '');
            $value = (string)($_POST['value'] ?? '');
            $rev   = (string)($_POST['rev'] ?? '');
            if (!$pageOk || !in_array($l, $langs, true) || $key === '' || strlen($key) > 255) {
                lce_json(['ok' => false, 'error' => $L['err_ed_bad_request'] ?? 'Bad request.'], 400);
            }

            $messages = [
                'err_ed_control'      => $L['err_ed_control']      ?? 'The text contains invalid characters.',
                'err_ed_too_long'     => $L['err_ed_too_long']     ?? 'The text is too long (limit: {1} bytes).',
                'err_ed_placeholders' => $L['err_ed_placeholders'] ?? 'Placeholders do not match. Expected: {1}',
                'err_ed_percent'      => $L['err_ed_percent']      ?? 'A single % can break this string. Write %% instead.',
                'err_ed_readonly'     => $L['err_ed_readonly']     ?? 'This value is built in code and can only be changed in the file.',
                'err_ed_write'        => $L['err_ed_write']        ?? 'Could not write the file. Nothing was changed.',
                'err_ed_unsupported'  => $L['err_ed_unsupported']  ?? 'This file has a different format and can only be edited by hand.',
                'err_ed_no_key'       => $L['err_ed_no_key']       ?? 'This key does not exist in any language. New keys are added in code.',
                'err_ed_empty_new'    => $L['err_ed_empty_new']    ?? 'Enter the text for the new key.',
            ];

            $res = lce_save($langDir, $langs, $ref, $page, $l, $key, $value, $rev);

            if ($res['ok'] && isset($res['added'])) {
                write_log(sprintf('Lang editor: %s.lang.php [%s] key "%s" %s', $page, $l, $key, $res['added'] ? 'added' : 'changed'), 'settings', 1);
            }
            if (!$res['ok'] && $res['code'] !== 'conflict') {
                $msg = $messages[$res['code']] ?? $res['code'];
                foreach ($res['args'] ?? [] as $i => $arg) {
                    $msg = strtr($msg, ['{' . ($i + 1) . '}' => (string)$arg, '%' . ($i + 1) . '$s' => (string)$arg]);
                }
                $res['error'] = $msg;
            }
            lce_json($res);
        }

        lce_json(['ok' => false, 'error' => $L['err_ed_bad_request'] ?? 'Bad request.'], 400);
    }

    $pages = $langDir !== null ? lce_pages($langDir, $langs) : [];
    if (!in_array($page, $pages, true)) $page = $pages[0] ?? '';

    $jsLang = [];
    foreach ($L as $k => $v) {
        if (str_starts_with((string)$k, 'js_ed_')) $jsLang[substr((string)$k, 3)] = (string)$v;
    }

    stdhead($L['lbl_ed_title'] ?? 'Language Editor');
    ?>
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/langcheck.css?v=<?= LC_VERSION ?>">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">

<div class="container mt-3 mb-4 lc" id="lceApp"
     data-url="<?= e($self . '&show=editor') ?>"
     data-key="<?= e((string)($mybb->post_code ?? '')) ?>"
     data-langs="<?= e(implode(',', $langs)) ?>"
     data-focus-key="<?= e((string)($_GET['key'] ?? '')) ?>">

    <div class="lc-card mb-3"><div class="lc-head">
        <span class="lc-head-icon"><i class="fa-solid fa-pen-to-square"></i></span>
        <div class="lc-min0">
            <h1 class="lc-title"><?= e($L['lbl_ed_title'] ?? 'Language Editor') ?></h1>
            <div class="lc-sub"><?= e($L['lbl_ed_subtitle'] ?? 'Edit lang strings side by side. Changes are written to the files right away.') ?></div>
        </div>
    </div></div>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-file-lines',           'ic-blue',  $L['lbl_ed_kpi_files']   ?? 'Lang files',     count($pages), ''],
            ['fa-key',                  'ic-slate', $L['lbl_ed_kpi_keys']    ?? 'Keys in file',   '—',           'lceKpiKeys'],
            ['fa-circle-half-stroke',   'ic-amber', $L['lbl_ed_kpi_empty']   ?? 'Empty',          '—',           'lceKpiEmpty'],
            ['fa-circle-xmark',         'ic-red',   $L['lbl_ed_kpi_missing'] ?? 'Missing',        '—',           'lceKpiMissing'],
        ] as [$ic, $cls, $label, $val, $id]): ?>
        <div class="col-6 col-lg-3"><div class="lc-card lc-kpi"><span class="lc-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="lc-kpi-label"><?= e($label) ?></div><div class="lc-kpi-value"<?= $id !== '' ? ' id="' . $id . '"' : '' ?>><?= e((string)$val) ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <div class="lc-card mb-3 lce-tools">
        <div class="lce-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="lceSearch" autocomplete="off"
                   placeholder="<?= e($L['lbl_ed_search'] ?? 'Search all files by text or key…') ?>"
                   aria-label="<?= e($L['lbl_ed_search'] ?? 'Search all files by text or key…') ?>">
        </div>
        <div class="lce-results" id="lceResults" hidden></div>
        <div class="lce-file-row">
            <label class="lc-muted" for="lceFile"><?= e($L['lbl_ed_file'] ?? 'File') ?></label>
            <select class="form-select" id="lceFile">
                <?php foreach ($pages as $p): ?>
                <option value="<?= e($p) ?>"<?= $p === $page ? ' selected' : '' ?>><?= e($p) ?>.lang.php</option>
                <?php endforeach; ?>
            </select>
            <input type="search" class="form-control" id="lceFilter" autocomplete="off"
                   placeholder="<?= e($L['lbl_ed_filter'] ?? 'Filter this file…') ?>"
                   aria-label="<?= e($L['lbl_ed_filter'] ?? 'Filter this file…') ?>">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="lceProblems">
                <label class="form-check-label" for="lceProblems"><?= e($L['lbl_ed_problems'] ?? 'Empty and missing only') ?></label>
            </div>
        </div>
    </div>

    <div class="lc-card overflow-hidden mb-3">
        <div class="lce-table" id="lceTable" style="--lce-langs: <?= max(1, count($langs)) ?>"></div>
    </div>

    <div class="lc-bar">
        <div class="lc-muted d-none d-md-block"><i class="fa-solid fa-circle-info me-1"></i><?= e($L['hint_ed_rules'] ?? 'Click a value to edit it. Ctrl+Enter saves, Esc cancels. Keep numbers in curly braces unchanged.') ?></div>
        <a href="<?= e($self) ?>" class="btn btn-sm lc-pill ms-auto"><i class="fa-solid fa-arrow-left me-1"></i><?= e($L['btn_ed_back'] ?? 'Back to checker') ?></a>
    </div>
</div>
<script>const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/langcheck_editor.js?v=<?= LC_VERSION ?>"></script>
<?php
    stdfoot();
}
