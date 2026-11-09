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
const LCE_MAX_FILE     = 4194304;
const LCE_HISTORY_KEEP = 30;          // версий на файл и язык

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
 * items[key] = value, raw, off, len (литерал значения), ro, expr, key_off, after, comma, sec.
 * head / tail — токены до и после массива, name — имя массива, bad — смещения элементов не вида 'ключ' => значение.
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
    $stmt   = 0;
    $name   = '';
    for ($i = 0; $i + 6 < $n; $i++) {
        if ($sig[$i][0] !== T_VARIABLE || $sig[$i][1] !== '$language' || $sig[$i + 1][1] !== '['
            || $sig[$i + 2][0] !== T_CONSTANT_ENCAPSED_STRING || $sig[$i + 3][1] !== ']' || $sig[$i + 4][1] !== '=') {
            continue;
        }
        if ($sig[$i + 5][0] === T_ARRAY && $sig[$i + 6][1] === '(') {
            $starts[] = $i + 7;
        } elseif ($sig[$i + 5][1] === '[') {
            $starts[] = $i + 6;
        } else {
            continue;
        }
        $stmt = $i;
        $name = lce_decode($sig[$i + 2][1]);
    }
    if (count($starts) !== 1) return null;

    $items = [];
    $bad   = [];
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
                'expr'    => $lit === null ? substr($src, $sig[$start + 2][2], $last[2] + strlen($last[1]) - $sig[$start + 2][2]) : '',
                'key_off' => $sig[$start][2],
                'after'   => $comma ? $sig[$i][2] + 1 : $last[2] + strlen($last[1]),
                'comma'   => $comma,
                'sec'     => $sec,
            ];
            $sec = null;
        } elseif ($count > 0) {
            $bad[] = $sig[$start][2];
        }
        if (!$comma) break;
        $i++;
    }

    if ($close === null) return null;

    $join = static fn(array $part): string => implode('', array_column($part, 1));
    return [
        'items' => $items,
        'close' => $close,
        'bad'   => $bad,
        'name'  => $name,
        'head'  => $join(array_slice($sig, 0, $stmt)),
        'tail'  => $join(array_slice($sig, $i + 1)),
    ];
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

/** История лежит в cache/ — туда закрыт доступ из веба (cache/.htaccess), сканер проекта её не читает */
function lce_history_dir(string $l): string
{
    return TSDIR . '/cache/lang_history/' . $l;
}

function lce_history_read(string $l, string $page): array
{
    $file = lce_history_dir($l) . "/{$page}.json";
    if (!is_file($file)) return [];
    $list = json_decode((string)@file_get_contents($file), true);
    return is_array($list) ? $list : [];
}

/**
 * Копия файла перед записью. false — копию сделать не удалось, запись тогда отменяется:
 * правка без возможности отката хуже, чем сообщение об ошибке.
 * $what описывает правку, которая идёт следом.
 */
function lce_snapshot(string $l, string $page, string $src, array $what): bool
{
    global $CURUSER;

    $dir = lce_history_dir($l);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return false;

    $id = time() . '-' . bin2hex(random_bytes(4));
    if (@file_put_contents("{$dir}/{$page}.{$id}.bak", $src, LOCK_EX) === false) return false;

    $list = lce_history_read($l, $page);
    array_unshift($list, ['id' => $id, 't' => time(), 'user' => (string)($CURUSER['username'] ?? ''), 'what' => $what]);
    foreach (array_splice($list, LCE_HISTORY_KEEP) as $old) {
        if (preg_match('/^\d+-[0-9a-f]{8}$/', (string)($old['id'] ?? ''))) @unlink("{$dir}/{$page}.{$old['id']}.bak");
    }
    return lce_write("{$dir}/{$page}.json", (string)json_encode($list, JSON_UNESCAPED_UNICODE));
}

function lce_history_src(string $l, string $page, string $id): ?string
{
    if (preg_match('/^\d+-[0-9a-f]{8}$/', $id) !== 1) return null;
    $file = lce_history_dir($l) . "/{$page}.{$id}.bak";
    if (!is_file($file)) return null;
    $src = @file_get_contents($file);
    return $src === false ? null : $src;
}

/** Что изменится при откате: текущее значение → значение этой версии */
function lce_history_diff(string $current, string $version): ?array
{
    $cur = lce_parse($current);
    $ver = lce_parse($version);
    if ($cur === null || $ver === null) return null;

    $out = [];
    foreach ($cur['items'] + $ver['items'] as $k => $unused) {
        $from = $cur['items'][$k]['value'] ?? null;
        $to   = $ver['items'][$k]['value'] ?? null;
        if ($from !== $to) $out[] = ['k' => (string)$k, 'from' => $from, 'to' => $to];
    }
    return $out;
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

        if (!lce_snapshot($l, $page, $src, ['t' => $item === null ? 'add' : 'key', 'k' => $key])) {
            return ['ok' => false, 'code' => 'err_ed_history'];
        }
        if (!lce_write($path, $new)) return ['ok' => false, 'code' => 'err_ed_write'];

        return ['ok' => true, 'rev' => lce_rev($literal), 'value' => $value, 'added' => $item === null];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function lce_line(string $src, int $offset): int
{
    return substr_count($src, "\n", 0, $offset) + 1;
}

/**
 * Сохранение файла целиком. Записать можно только то, что безопасно исполнять:
 * начало и конец файла как были, внутри только 'ключ' => 'текст' и комментарии.
 * $confirmRemoved — пользователь подтвердил удаление (или переименование) ключей.
 * $restoring — id версии при откате: тексты тогда не сверяются с другим языком, он мог уйти вперёд.
 */
function lce_save_raw(string $langDir, array $langs, string $page, string $l, string $src, string $hash, bool $confirmRemoved, ?string $restoring = null): array
{
    $path = lce_path($langDir, $l, $page);
    if (!is_file($path)) return ['ok' => false, 'code' => 'err_ed_unsupported'];
    if (strlen($src) > LCE_MAX_FILE) return ['ok' => false, 'code' => 'err_ed_too_long', 'args' => [LCE_MAX_FILE]];
    if (!mb_check_encoding($src, 'UTF-8')) return ['ok' => false, 'code' => 'err_ed_control'];

    $lock = @fopen(sys_get_temp_dir() . '/ag_lang_editor.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) return ['ok' => false, 'code' => 'err_ed_write'];

    try {
        $current = (string)file_get_contents($path);
        if (sha1($current) !== $hash) return ['ok' => false, 'code' => 'conflict_file'];

        $cur = lce_parse($current);
        if ($cur === null) return ['ok' => false, 'code' => 'err_ed_unsupported'];

        $src = str_replace(["\r\n", "\r"], "\n", $src);
        if ($src === str_replace(["\r\n", "\r"], "\n", $current)) {
            return ['ok' => true, 'hash' => $hash];
        }

        try {
            token_get_all($src, TOKEN_PARSE);
        } catch (\ParseError $ex) {
            return ['ok' => false, 'code' => 'err_ed_syntax', 'args' => [$ex->getLine(), $ex->getMessage()], 'line' => $ex->getLine()];
        }

        $new   = lce_parse($src);
        $strip = static fn(string $s): string => (string)preg_replace('/\s+/', '', $s);
        if ($new === null || $new['name'] !== $cur['name']
            || $strip($new['head']) !== $strip($cur['head']) || $strip($new['tail']) !== $strip($cur['tail'])) {
            return ['ok' => false, 'code' => 'err_ed_structure_file'];
        }
        if ($new['bad']) {
            $line = lce_line($src, $new['bad'][0]);
            return ['ok' => false, 'code' => 'err_ed_structure', 'args' => [$line], 'line' => $line];
        }

        $others = [];
        foreach ($langs as $other) {
            if ($other === $l) continue;
            $p = lce_read(lce_path($langDir, $other, $page));
            if ($p !== null) $others[] = $p['items'];
        }

        $changed = 0;
        foreach ($new['items'] as $key => $item) {
            $key  = (string)$key;
            $old  = $cur['items'][$key] ?? null;
            $line = lce_line($src, $item['key_off']);

            // Выражения PHP можно только оставить как есть
            if ($item['ro'] && ($old === null || !$old['ro'] || $strip($old['expr']) !== $strip($item['expr']))) {
                return ['ok' => false, 'code' => 'err_ed_structure', 'args' => [$line], 'line' => $line];
            }
            if ($item['ro']) continue;

            if ($restoring !== null) continue;

            if ($old === null && preg_match('/^[A-Za-z0-9_]+$/', $key) !== 1) {
                return ['ok' => false, 'code' => 'err_ed_bad_key', 'args' => [$line, $key], 'line' => $line];
            }
            if ($old !== null && $old['value'] === $item['value']) continue;
            if ($old !== null) $changed++;

            $bases = [$old['value'] ?? null];
            foreach ($others as $items) $bases[] = $items[$key]['value'] ?? null;
            $bad = lce_validate($item['value'], $bases);
            if ($bad !== null) {
                return ['ok' => false, 'code' => $bad[0], 'args' => $bad[1], 'line' => $line, 'key' => $key];
            }
        }

        $removed = array_map('strval', array_keys(array_diff_key($cur['items'], $new['items'])));
        if ($removed && !$confirmRemoved) {
            return ['ok' => false, 'code' => 'confirm_removed', 'removed' => $removed];
        }

        // Переводы строк — как были у файла
        $crlf = substr_count($current, "\r\n");
        if ($crlf > substr_count($current, "\n") - $crlf) {
            $src = str_replace("\n", "\r\n", $src);
        }
        $added = count(array_diff_key($new['items'], $cur['items']));
        $what  = $restoring !== null
            ? ['t' => 'restore']
            : ['t' => 'file', 'c' => $changed, 'a' => $added, 'r' => count($removed)];
        if (!lce_snapshot($l, $page, $current, $what)) return ['ok' => false, 'code' => 'err_ed_history'];
        if (!lce_write($path, $src)) return ['ok' => false, 'code' => 'err_ed_write'];

        return ['ok' => true, 'hash' => sha1($src), 'saved' => true, 'removed' => count($removed), 'added' => count(array_diff_key($new['items'], $cur['items']))];
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

        if ($ajax === 'raw') {
            $l = (string)($_GET['lang'] ?? '');
            if (!$pageOk || !in_array($l, $langs, true)) lce_json(['ok' => false, 'error' => $L['err_ed_bad_request'] ?? 'Bad request.'], 400);
            $path = lce_path($langDir, $l, $page);
            $src  = is_file($path) ? @file_get_contents($path) : false;
            if ($src === false || lce_parse($src) === null) {
                lce_json(['ok' => false, 'error' => $L['err_ed_unsupported'] ?? 'This file has a different format and can only be edited by hand.']);
            }
            lce_json(['ok' => true, 'src' => $src, 'hash' => sha1($src)]);
        }

        if ($ajax === 'history') {
            if (!$pageOk) lce_json(['ok' => false, 'error' => $L['err_ed_bad_request'] ?? 'Bad request.'], 400);
            $entries = [];
            foreach ($langs as $l) {
                foreach (lce_history_read($l, $page) as $row) $entries[] = $row + ['lang' => $l];
            }
            usort($entries, static fn(array $a, array $b): int => (int)$b['t'] <=> (int)$a['t']);
            lce_json(['ok' => true, 'entries' => $entries]);
        }

        if ($ajax === 'histdiff') {
            $l  = (string)($_GET['lang'] ?? '');
            $id = (string)($_GET['id'] ?? '');
            $version = $pageOk && in_array($l, $langs, true) ? lce_history_src($l, $page, $id) : null;
            $current = $version !== null ? @file_get_contents(lce_path($langDir, $l, $page)) : false;
            $diff = $current !== false && $version !== null ? lce_history_diff($current, $version) : null;
            if ($diff === null) lce_json(['ok' => false, 'error' => $L['err_ed_version'] ?? 'This version is no longer available.']);
            lce_json(['ok' => true, 'total' => count($diff), 'changes' => array_slice($diff, 0, 10)]);
        }

        if ($ajax === 'save' || $ajax === 'saveraw' || $ajax === 'restore') {
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
            $bad   = !$pageOk || !in_array($l, $langs, true);
            $id    = (string)($_POST['id'] ?? '');
            if ($bad || ($ajax === 'save' && ($key === '' || strlen($key) > 255))) {
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
                'err_ed_syntax'       => $L['err_ed_syntax']       ?? 'Syntax error on line {1}: {2}',
                'err_ed_structure'    => $L['err_ed_structure']    ?? 'Line {1}: only \'key\' => \'text\' entries are allowed here.',
                'err_ed_structure_file' => $L['err_ed_structure_file'] ?? 'The beginning or the end of the file was changed. Only the entries of the array can be edited.',
                'err_ed_bad_key'      => $L['err_ed_bad_key']      ?? 'Line {1}: invalid key name "{2}". Use a-z, 0-9 and _.',
                'err_ed_history'      => $L['err_ed_history']      ?? 'Could not keep a copy of the file, so nothing was changed. Check that cache/ is writable.',
                'err_ed_version'      => $L['err_ed_version']      ?? 'This version is no longer available.',
                'conflict_file'       => $L['err_ed_changed']      ?? 'The file has just been changed. Try again.',
            ];

            if ($ajax === 'restore') {
                $version = lce_history_src($l, $page, $id);
                $path    = lce_path($langDir, $l, $page);
                if ($version === null || !is_file($path)) {
                    $res = ['ok' => false, 'code' => 'err_ed_version'];
                } else {
                    $res = lce_save_raw($langDir, $langs, $page, $l, $version, sha1((string)file_get_contents($path)), true, $id);
                }
                if ($res['ok'] && isset($res['saved'])) {
                    write_log(sprintf('Lang editor: %s.lang.php [%s] rolled back to version %s', $page, $l, $id), 'settings', 1);
                }
            } elseif ($ajax === 'saveraw') {
                $res = lce_save_raw($langDir, $langs, $page, $l, (string)($_POST['src'] ?? ''), (string)($_POST['hash'] ?? ''), ($_POST['confirm_removed'] ?? '') === '1');
                if ($res['ok'] && isset($res['saved'])) {
                    write_log(sprintf('Lang editor: %s.lang.php [%s] file saved (%d keys added, %d removed)', $page, $l, $res['added'], $res['removed']), 'settings', 1);
                }
            } else {
                $res = lce_save($langDir, $langs, $ref, $page, $l, $key, $value, $rev);
                if ($res['ok'] && isset($res['added'])) {
                    write_log(sprintf('Lang editor: %s.lang.php [%s] key "%s" %s', $page, $l, $key, $res['added'] ? 'added' : 'changed'), 'settings', 1);
                }
            }
            $handledByJs = $ajax === 'restore' ? [] : ['conflict', 'conflict_file', 'confirm_removed'];
            if (!$res['ok'] && !in_array($res['code'], $handledByJs, true)) {
                $msg = $messages[$res['code']] ?? $res['code'];
                foreach ($res['args'] ?? [] as $i => $arg) {
                    $msg = strtr($msg, ['{' . ($i + 1) . '}' => (string)$arg, '%' . ($i + 1) . '$s' => (string)$arg]);
                }
                if (isset($res['key'])) {
                    $where = $L['lbl_ed_at_key'] ?? 'Line {1}, key {2}';
                    $msg = strtr($where, ['{1}' => (string)$res['line'], '%1$s' => (string)$res['line'], '{2}' => $res['key'], '%2$s' => $res['key']]) . ': ' . $msg;
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
     data-focus-key="<?= e((string)($_GET['key'] ?? '')) ?>"
     data-ui-lang="<?= e((string)$lang->language) ?>">

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
        <div class="lce-results lce-history" id="lceHistory" hidden></div>
        <div class="lce-file-row">
            <div class="d-flex gap-1" role="group" aria-label="<?= e($L['lbl_ed_view'] ?? 'View') ?>">
                <button type="button" class="btn btn-sm lc-pill" id="lceViewTable"><i class="fa-solid fa-table-list me-1"></i><?= e($L['lbl_ed_view_table'] ?? 'Table') ?></button>
                <button type="button" class="btn btn-sm lc-pill" id="lceViewRaw"><i class="fa-solid fa-code me-1"></i><?= e($L['lbl_ed_view_raw'] ?? 'Whole file') ?></button>
            </div>
            <label class="lc-muted" for="lceFile"><?= e($L['lbl_ed_file'] ?? 'File') ?></label>
            <select class="form-select" id="lceFile">
                <?php foreach ($pages as $p): ?>
                <option value="<?= e($p) ?>"<?= $p === $page ? ' selected' : '' ?>><?= e($p) ?>.lang.php</option>
                <?php endforeach; ?>
            </select>
            <input type="search" class="form-control" id="lceFilter" autocomplete="off"
                   placeholder="<?= e($L['lbl_ed_filter'] ?? 'Filter this file…') ?>"
                   aria-label="<?= e($L['lbl_ed_filter'] ?? 'Filter this file…') ?>">
            <select class="form-select lce-raw-lang" id="lceRawLang" aria-label="<?= e($L['lbl_ed_lang'] ?? 'Language') ?>" hidden>
                <?php foreach ($langs as $l): ?><option value="<?= e($l) ?>"><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm lc-pill" id="lceHistoryBtn" aria-expanded="false"><i class="fa-solid fa-clock-rotate-left me-1"></i><?= e($L['btn_ed_history'] ?? 'History') ?></button>
            <div class="form-check form-switch mb-0" id="lceProblemsWrap">
                <input class="form-check-input" type="checkbox" role="switch" id="lceProblems">
                <label class="form-check-label" for="lceProblems"><?= e($L['lbl_ed_problems'] ?? 'Empty and missing only') ?></label>
            </div>
        </div>
    </div>

    <div class="lc-card overflow-hidden mb-3">
        <div class="lce-table" id="lceTable" style="--lce-langs: <?= max(1, count($langs)) ?>"></div>
    </div>

    <div class="lc-card mb-3 lce-raw" id="lceRaw" hidden>
        <div class="lce-raw-head">
            <div class="lc-muted"><i class="fa-solid fa-shield-halved me-1"></i><?= e($L['hint_ed_raw'] ?? 'Only entries and comments can be saved here: PHP code and syntax errors are rejected.') ?></div>
            <button type="button" class="btn btn-sm lc-pill lc-go ms-auto" id="lceRawSave"><i class="fa-solid fa-floppy-disk me-1"></i><?= e($L['btn_ed_save_file'] ?? 'Save file') ?></button>
        </div>
        <div class="lce-error lce-raw-error" id="lceRawError" role="alert" hidden></div>
        <textarea class="form-control lce-raw-text" id="lceRawText" spellcheck="false" aria-label="<?= e($L['lbl_ed_view_raw'] ?? 'Whole file') ?>"></textarea>
    </div>

    <div class="lc-bar">
        <div class="lc-muted d-none d-md-block" id="lceTableHint"><i class="fa-solid fa-circle-info me-1"></i><?= e($L['hint_ed_rules'] ?? 'Click a value to edit it. Ctrl+Enter saves, Esc cancels. Keep numbers in curly braces unchanged.') ?></div>
        <a href="<?= e($self) ?>" class="btn btn-sm lc-pill ms-auto"><i class="fa-solid fa-arrow-left me-1"></i><?= e($L['btn_ed_back'] ?? 'Back to checker') ?></a>
    </div>
</div>
<script>const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/langcheck_editor.js?v=<?= LC_VERSION ?>"></script>
<?php
    stdfoot();
}
