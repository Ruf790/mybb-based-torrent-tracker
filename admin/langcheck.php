<?php
declare(strict_types=1);

/**
 * Lang Checker — сверка ключей из кода с ланг-файлами всех языков.
 * Только чтение файлов: в БД ничего не пишет, ничего не меняет.
 */

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert"><strong>Access denied.</strong> Direct initialization of this file is not allowed.</div>');
}

$lang->load('langcheck');

use function htmlspecialchars as e;

const LC_VERSION  = '3.2';
const LC_REF_LANG = 'english';
/** Сканируется весь проект рекурсивно; пропускаются только папки без кода (по имени, на любом уровне) */
const LC_SKIP_DIRS = ['.git', '.svn', '.idea', 'node_modules', 'vendor', 'cache', 'uploads', 'torrents',
                      'attachments', 'avatars', 'screenshots', 'backups', 'logs'];

if (!function_exists('ags_fmt')) {
    /** {1}, {2}… — и %1$s, %2$s… (в них их превращает $lang->load()) */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $a) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$a;
            $map['%' . $n . '$s'] = (string)$a;
        }
        return strtr($str, $map);
    }
}

function lc_root(): string
{
    return str_replace('\\', '/', rtrim(defined('TSDIR') ? (string)TSDIR : dirname(__DIR__), '/\\'));
}

/**
 * Папка с лангами: $lang->path (если класс его хранит), иначе типовые места.
 * Подходит первая, где есть <язык>/*.lang.php. null — не найдено.
 */
function lc_lang_dir(string $root): ?string
{
    global $lang;
    $cand = [];
    $p = (is_object($lang) && isset($lang->path) && is_string($lang->path)) ? str_replace('\\', '/', rtrim($lang->path, '/\\')) : '';
    if ($p !== '') {
        $cand[] = $p;
        $cand[] = $root . '/' . preg_replace('#^(\./|\.\./)+#', '', $p);
    }
    foreach (['languages', 'include/languages', 'inc/languages', 'admin/languages'] as $d) $cand[] = $root . '/' . $d;
    foreach ($cand as $d) {
        if (is_dir($d) && glob($d . '/*/*.lang.php')) return str_replace('\\', '/', (string)realpath($d)) ?: $d;
    }
    return null;
}

/** Языки: подпапки, где есть хотя бы один *.lang.php; эталон — первым */
function lc_langs(?string $dir): array
{
    $out = [];
    if ($dir === null) return $out;
    foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $d) {
        if (glob($d . '/*.lang.php')) $out[] = basename($d);
    }
    sort($out);
    usort($out, fn($a, $b) => ($b === LC_REF_LANG) <=> ($a === LC_REF_LANG));
    return $out;
}

/**
 * Все PHP-файлы проекта: [относительный путь => содержимое].
 * Пропуск: папки из LC_SKIP_DIRS и сама папка с лангами ($skipAbs).
 */
function lc_scan_php(string $root, ?string $skipAbs): array
{
    $skipAbs = $skipAbs !== null ? strtolower(rtrim(str_replace('\\', '/', $skipAbs), '/')) : null;
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function (SplFileInfo $f) use ($skipAbs): bool {
            if ($f->isDir()) {
                if (in_array(strtolower($f->getFilename()), LC_SKIP_DIRS, true)) return false;
                return $skipAbs === null || strtolower(str_replace('\\', '/', $f->getPathname())) !== $skipAbs;
            }
            return str_ends_with(strtolower($f->getFilename()), '.php');
        }
    ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);   // нет прав на папку — пропустить

    $files = [];
    foreach ($it as $f) {
        $p = str_replace('\\', '/', $f->getPathname());
        if ($f->getSize() > 2 * 1024 * 1024) continue;
        $code = @file_get_contents($p);
        if ($code === false) continue;
        $files[substr($p, strlen($root) + 1)] = $code;
    }
    ksort($files);
    return $files;
}

/** Загрузка ланг-файла в изолированной области видимости */
function lc_load_lang(string $file, string $page): array
{
    // Гард ланг-файла (if(!defined('IN_TRACKER')) die(...)) обрывает всю страницу,
    // а die() не поймать — определяем константу гарда заранее, какой бы она ни была.
    $src = (string)file_get_contents($file);
    if (preg_match_all('/if\s*\(\s*!\s*defined\s*\(\s*[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]\s*\)\s*\)/', $src, $g)) {
        foreach ($g[1] as $const) if (!defined($const)) define($const, true);
    }
    $language = [];
    ob_start();
    try {
        include $file;
    } catch (Throwable $ex) {
        ob_end_clean();
        return ['ok' => false, 'error' => $ex->getMessage() . ' (line ' . $ex->getLine() . ')', 'keys' => [], 'values' => [], 'dups' => [], 'alias' => null];
    }
    ob_end_clean();
    $alias = null;
    if (!isset($language[$page]) || !is_array($language[$page])) {
        // Массив назван иначе ($language['announcements'] в announcements2.lang.php) — берём его и предупреждаем
        $arrays = array_filter($language, 'is_array');
        if (count($arrays) !== 1) {
            return ['ok' => false, 'error' => "\$language['{$page}'] is not defined", 'keys' => [], 'values' => [], 'dups' => [], 'alias' => null];
        }
        $alias = (string)array_key_first($arrays);
        $language[$page] = $arrays[$alias];
    }
    preg_match_all('/^\s*([\'"])([^\'"]+)\1\s*=>/m', $src, $m);
    $dups = array_keys(array_filter(array_count_values($m[2]), fn($c) => $c > 1));
    return ['ok' => true, 'error' => null, 'keys' => array_map('strval', array_keys($language[$page])), 'values' => $language[$page], 'dups' => $dups, 'alias' => $alias];
}

/**
 * Ключи из выражения внутри [...]. Строки после ===, !==, == — сравнения; строка перед «.» — префикс.
 * $optional: обращение с ?? или внутри isset()/empty() — отсутствие ключа не даёт TypeError.
 * $used[key] = 'req' | 'opt' (хоть одно обязательное обращение делает ключ обязательным)
 */
function lc_expr_keys(string $expr, array &$used, bool &$dynamic, bool $optional = false): array
{
    if (str_contains($expr, '.') || preg_match('/^\s*\$\w+\s*$/', $expr)) $dynamic = true;
    preg_match_all('/(?<![=!]=\s)(?<!===\s)([\'"])([A-Za-z0-9_]+)\1(?!\s*\.)/', $expr, $k);
    foreach ($k[2] as $x) $used[$x] = (!$optional || ($used[$x] ?? 'opt') === 'req') ? 'req' : 'opt';
    return $k[2];
}

/** Номер строки по смещению */
function lc_line(string $code, int $offset): int
{
    return substr_count($code, "\n", 0, $offset) + 1;
}

/**
 * JS без комментариев: посимвольно, с учётом строк '…' "…" `…` и экранирования.
 * Переводы строк внутри комментариев сохраняются — номера строк не съезжают.
 */
function lc_strip_js_comments(string $code): string
{
    $out = ''; $n = strlen($code); $q = null;
    for ($i = 0; $i < $n; $i++) {
        $c = $code[$i];
        if ($q !== null) {                                   // внутри строки
            $out .= $c;
            if ($c === '\\' && $i + 1 < $n) { $out .= $code[++$i]; continue; }
            if ($c === $q) $q = null;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') { $q = $c; $out .= $c; continue; }
        if ($c === '/' && $i + 1 < $n && $code[$i + 1] === '/') { // // до конца строки
            while ($i < $n && $code[$i] !== "\n") $i++;
            if ($i < $n) $out .= "\n";
            continue;
        }
        if ($c === '/' && $i + 1 < $n && $code[$i + 1] === '*') { // /* … */
            $end = strpos($code, '*/', $i + 2);
            $end = $end === false ? $n : $end + 2;
            $out .= str_repeat("\n", substr_count($code, "\n", $i, $end - $i));
            $i = $end - 1;
            continue;
        }
        $out .= $c;
    }
    return $out;
}

/** Все обращения $var[...] в коде с признаком «необязательное» (?? / isset / empty) */
function lc_scan_access(string $code, string $varRe, array &$used, bool &$dynamic, array &$loc, string $file): void
{
    preg_match_all('/' . $varRe . '\s*\[([^\]]+)\](\s*\?\?)?/', $code, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($m as $hit) {
        $before = substr($code, max(0, $hit[0][1] - 12), min(12, $hit[0][1]));
        $opt    = !empty($hit[2][0]) || preg_match('/(?:isset|empty)\s*\(\s*$/', $before) === 1;
        foreach (lc_expr_keys($hit[1][0], $used, $dynamic, $opt) as $k) {
            $loc[$k] ??= $file . ':' . lc_line($code, (int)$hit[0][1]);
        }
    }
}

/**
 * Ключи из JS-кода: t('key' / tf('key' (не t('prefix_' + x)), AGS_LANG.key / AGS_LANG['key'].
 * $baseLine — смещение строк (для <script> внутри PHP-файла), $label — что писать в «файл:строка».
 */
function lc_js_scan(string $code, string $label, int $baseLine, array &$used, array &$loc, bool &$dynamic): void
{
    $code = lc_strip_js_comments($code);              // // t('key', fallback) в комментарии — не использование
    preg_match_all('/\btf?\(\s*([\'"])([A-Za-z0-9_]+)\1(?!\s*\+)/', $code, $k, PREG_OFFSET_CAPTURE);
    foreach ($k[2] as [$x, $off]) { $used['js_' . $x] = 'req'; $loc['js_' . $x] ??= $label . ':' . ($baseLine + lc_line($code, (int)$off)); }
    // Прямой доступ без fallback — тоже обязательный
    preg_match_all('/\bAGS_LANG\s*(?:\.\s*([A-Za-z_]\w*)|\[\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\])/', $code, $d);
    foreach (array_filter(array_merge($d[1], $d[2]), 'strlen') as $x) $used['js_' . $x] = 'req';
    // Динамика: t('prefix_' + x) или t(variable)
    if (preg_match('/\btf?\(\s*(?:[\'"][A-Za-z0-9_]*[\'"]\s*\+|[A-Za-z_$][\w$.]*\s*[,)])/', $code)) $dynamic = true;
}

/** Ключи, которые использует код страницы (PHP + JS с AGS_LANG) */
function lc_used_keys(string $root, string $page, array $phpFiles): array
{
    $used = []; $dynamic = false; $js = []; $loc = []; $jsInline = [];
    $p = preg_quote($page, '/');
    foreach ($phpFiles as $file => $code) {
        $file = (string)$file;
        lc_scan_access($code, '\$lang->' . $p, $used, $dynamic, $loc, $file);

        // Алиасы — переменная, которой присвоен ВЕСЬ массив, напрямую или через обёртку:
        //   $L = $lang->page;   $L = array_map('at_e', $lang->page);   $L = array_merge($lang->page, …);
        // ( =(?![=>]) — не сравнение и не «=>» из foreach; (?!\s*\[) — не отдельный ключ )
        preg_match_all('/(\$\w+)\s*=(?![=>])\s*&?[^;\n]*?\$lang->' . $p . '\b(?!\s*\[)[^;\n]*;/', $code, $a);
        foreach (array_unique($a[1]) as $alias) lc_scan_access($code, preg_quote($alias, '/'), $used, $dynamic, $loc, $file);

        // JS-ключи принадлежат этой странице, только если ИМЕННО её массив выгружается в AGS_LANG
        // (foreach ($lang->page as …) / array_*($lang->page, …)); иначе скрипт — чужой
        // Плюс любой способ выгрузки: если в файле есть AGS_LANG и весь массив страницы куда-то передаётся
        // (ags_js_lang($lang->page), (array)$lang->page, $L = $lang->page; foreach ($L …) и т.п.)
        $exports = preg_match('/(?:foreach\s*\(\s*|array_\w+\s*\(\s*)\$lang->' . $p . '\s*(?:as\b|,|\))/', $code)
                || (str_contains($code, 'AGS_LANG') && preg_match('/\$lang->' . $p . '\b(?!\s*\[)/', $code));
        if ($exports) {
            // Встроенные <script> без src прямо в PHP-файле (announcements.php и т.п.)
            preg_match_all('#<script\b(?![^>]*\bsrc\s*=)[^>]*>(.*?)</script>#is', $code, $sb, PREG_OFFSET_CAPTURE);
            $inline = false;
            foreach ($sb[1] as [$block, $off]) {
                if (!preg_match('/\btf?\(|AGS_LANG\s*[.\[]/', $block)) continue;
                lc_js_scan($block, $file, lc_line($code, (int)$off) - 1, $used, $loc, $dynamic);
                $inline = true;
            }
            if ($inline) $jsInline[] = $file . ' (inline)';
            preg_match_all('#/?((?:admin/)?scripts/[\w.\-]+\.js)#', $code, $s);
            foreach ($s[1] as $src) if (!str_ends_with($src, '.min.js')) $js[$src] = true;
            foreach (["admin/scripts/{$page}.js", "scripts/{$page}.js"] as $conv) $js[$conv] = true;
        }
    }

    $jsFound = [];
    foreach (array_keys($js) as $src) {
        $abs = $root . '/' . $src;
        if (!is_file($abs)) continue;
        $code = (string)file_get_contents($abs);
        if (!str_contains($code, 'AGS_LANG')) continue;   // t() из чужого хелпера — не наш
        $jsFound[] = $src;
        lc_js_scan($code, $src, 0, $used, $loc, $dynamic);
    }
    $jsFound = array_merge($jsInline, $jsFound);
    $req = array_keys(array_filter($used, fn($v) => $v === 'req'));
    $opt = array_keys(array_filter($used, fn($v) => $v === 'opt'));
    return ['keys' => array_map('strval', array_keys($used)), 'req' => array_map('strval', $req), 'opt' => array_map('strval', $opt), 'dynamic' => $dynamic, 'js' => $jsFound, 'loc' => $loc];
}

/** Номера плейсхолдеров: {1} и %1$s */
function lc_placeholders(string $s): array
{
    preg_match_all('/\{(\d+)\}|%(\d+)\$s/', $s, $m);
    $n = array_map('intval', array_filter(array_merge($m[1], $m[2]), 'strlen'));
    $n = array_values(array_unique($n));
    sort($n);
    return $n;
}

// ── Сканирование ──────────────────────────────────────────────────────
$t0    = hrtime(true);
$root  = lc_root();
$langDir = lc_lang_dir($root);
$langs   = lc_langs($langDir);
$ref   = in_array(LC_REF_LANG, $langs, true) ? LC_REF_LANG : ($langs[0] ?? LC_REF_LANG);
$php   = lc_scan_php($root, $langDir);

// Какие файлы грузят какую страницу
$loadedBy = [];
foreach ($php as $rel => $code) {
    preg_match_all('/\$lang->load\(\s*[\'"]([\w\-]+)[\'"]/', $code, $m);
    foreach (array_unique($m[1]) as $pg) $loadedBy[$pg][] = $rel;
}

$pageNames = [];
$fileSets  = [];   // язык => [страница, ...] — для сводки по файлам
foreach ($langs as $l) {
    $fileSets[$l] = [];
    foreach (glob("{$langDir}/{$l}/*.lang.php") ?: [] as $f) {
        $pageNames[basename($f, '.lang.php')] = true;
        $fileSets[$l][] = basename($f, '.lang.php');
    }
    sort($fileSets[$l]);
}
// Каких файлов нет в каждом языке (относительно всех языков вместе)
$allFiles = $fileSets ? array_values(array_unique(array_merge(...array_values($fileSets)))) : [];
$fileGaps = [];
foreach ($fileSets as $l => $set) {
    if ($gap = array_values(array_diff($allFiles, $set))) { sort($gap); $fileGaps[$l] = $gap; }
}
foreach (array_keys($loadedBy) as $pg) $pageNames[$pg] = true;
$pageNames = array_keys($pageNames);

$pages = [];
foreach ($pageNames as $page) {
    $page   = (string)$page;
    $files  = $loadedBy[$page] ?? [];
    $usage  = $files ? lc_used_keys($root, $page, array_intersect_key($php, array_flip($files))) : ['keys' => [], 'req' => [], 'opt' => [], 'dynamic' => false, 'js' => [], 'loc' => []];
    $issues = [];   // [severity, lang|null, code, items[]]
    $data   = [];

    foreach ($langs as $l) {
        $file = "{$langDir}/{$l}/{$page}.lang.php";
        if (!is_file($file)) { $issues[] = ['error', $l, 'no_file', []]; continue; }
        $data[$l] = lc_load_lang($file, $page);
        if (!$data[$l]['ok']) $issues[] = ['error', $l, 'bad_file', [$data[$l]['error']]];
        elseif ($data[$l]['alias'] !== null) $issues[] = ['warn', $l, 'other_name', ["\$language['{$data[$l]['alias']}']"]];
    }
    $refKeys = isset($data[$ref]) && $data[$ref]['ok'] ? $data[$ref]['keys'] : null;

    foreach ($data as $l => $d) {
        if (!$d['ok']) continue;
        $missing = $files ? array_values(array_diff($usage['req'], $d['keys'])) : [];
        $withLoc = fn(array $keys) => array_map(fn($k) => isset($usage['loc'][$k]) ? $k . ' (' . $usage['loc'][$k] . ')' : $k, $keys);
        // js_* в PHP не читаются: в JS у t() английский fallback — это непереведённый текст, а не падение
        $missJs  = array_values(array_filter($missing, fn($k) => str_starts_with($k, 'js_')));
        $missPhp = array_values(array_diff($missing, $missJs));
        if ($missPhp) $issues[] = ['error', $l, 'missing', $withLoc($missPhp)];
        if ($missJs)  $issues[] = ['warn',  $l, 'missing_js', $withLoc($missJs)];
        $missOpt = $files ? array_values(array_diff($usage['opt'], $d['keys'])) : [];
        if ($missOpt) $issues[] = ['info', $l, 'missing_opt', $withLoc($missOpt)];

        if ($l !== $ref && $refKeys !== null) {
            if ($x = array_values(array_diff($refKeys, $d['keys'], $missing, $missOpt))) $issues[] = ['error', $l, 'missing_ref', $x];
            if ($x = array_values(array_diff($d['keys'], $refKeys)))           $issues[] = ['warn',  $l, 'extra_ref',   $x];
            $ph = [];
            foreach (array_intersect($d['keys'], $refKeys) as $k) {
                $a = $data[$ref]['values'][$k] ?? ''; $b = $d['values'][$k] ?? '';
                if (!is_string($a) || !is_string($b)) continue;
                $pa = lc_placeholders($a); $pb = lc_placeholders($b);
                if ($pa !== $pb) $ph[] = $k . ': ' . ($pa ? '{' . implode('},{', $pa) . '}' : '∅') . ' → ' . ($pb ? '{' . implode('},{', $pb) . '}' : '∅');
            }
            if ($ph) $issues[] = ['warn', $l, 'placeholders', $ph];
        }
        if ($files && !$usage['dynamic'] && ($l === $ref || $refKeys === null)) {
            if ($x = array_values(array_diff($d['keys'], $usage['keys']))) $issues[] = ['warn', $l, 'unused', $x];
        } elseif ($files && $usage['dynamic'] && ($l === $ref || $refKeys === null)) {
            if ($x = array_values(array_diff($d['keys'], $usage['keys']))) $issues[] = ['info', $l, 'unused_dynamic', $x];
        }
        if ($x = array_keys(array_filter($d['values'], fn($v) => !is_string($v)))) $issues[] = ['error', $l, 'non_string', array_map('strval', $x)];
        if ($d['dups']) $issues[] = ['warn', $l, 'dups', $d['dups']];
    }
    if (!$files) $issues[] = ['info', null, 'not_loaded', []];
    if (!$langs) $issues[] = ['error', null, 'no_lang_dir', []];   // сверять не с чем — это не «OK»

    $sevs   = array_column($issues, 0);
    $status = in_array('error', $sevs, true) ? 'error' : (in_array('warn', $sevs, true) ? 'warn' : 'ok');
    $counts = [];
    foreach ($langs as $l) $counts[$l] = isset($data[$l]) && $data[$l]['ok'] ? count($data[$l]['keys']) : null;
    $pages[] = ['page' => $page, 'status' => $status, 'files' => $files, 'js' => $usage['js'], 'used' => count($usage['keys']), 'counts' => $counts, 'issues' => $issues];
}

$rank = ['error' => 0, 'warn' => 1, 'ok' => 2];
usort($pages, fn($a, $b) => [$rank[$a['status']], $a['page']] <=> [$rank[$b['status']], $b['page']]);
$ms = (int)round((hrtime(true) - $t0) / 1e6);

$stat = ['ok' => 0, 'warn' => 0, 'error' => 0];
foreach ($pages as $pg) $stat[$pg['status']]++;

$show  = ($_GET['show'] ?? '') === 'problems' ? 'problems' : 'all';
$shown = $show === 'problems' ? array_values(array_filter($pages, fn($p) => $p['status'] !== 'ok')) : $pages;

$self = (string)$_this_script_;
$sep  = str_contains($self, '?') ? '&' : '?';
$url  = fn(string $s) => $self . $sep . 'show=' . $s;

$issueLabel = fn(string $code, string $pg = '') => match ($code) {
    'no_file'        => $lang->langcheck['issue_no_file'],
    'bad_file'       => $lang->langcheck['issue_bad_file'],
    'missing'        => $lang->langcheck['issue_missing'],
    'missing_ref'    => ags_fmt($lang->langcheck['issue_missing_ref'], $ref),
    'extra_ref'      => ags_fmt($lang->langcheck['issue_extra_ref'], $ref),
    'placeholders'   => ags_fmt($lang->langcheck['issue_placeholders'], $ref),
    'unused'         => $lang->langcheck['issue_unused'],
    'unused_dynamic' => $lang->langcheck['issue_unused_dynamic'],
    'non_string'     => $lang->langcheck['issue_non_string'],
    'dups'           => $lang->langcheck['issue_dups'],
    'not_loaded'     => $lang->langcheck['issue_not_loaded'],
    'no_lang_dir'    => $lang->langcheck['issue_no_lang_dir'],
    'missing_opt'    => $lang->langcheck['issue_missing_opt'],
    'missing_js'     => $lang->langcheck['issue_missing_js'],
    'other_name'     => ags_fmt($lang->langcheck['issue_other_name'], $pg),
    default          => $code,
};
$sevIcon  = ['error' => 'fa-circle-xmark', 'warn' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];
$badgeTxt = ['ok' => $lang->langcheck['badge_ok'], 'warn' => $lang->langcheck['badge_warn'], 'error' => $lang->langcheck['badge_error']];

// ── Текстовый отчёт (кнопка «Скопировать отчёт»): только проблемы ──
const LC_REPORT_MAX = 50;   // ключей на одну проблему, дальше «+N ещё»
$repList = function (array $items) use ($lang): string {
    $more = count($items) - LC_REPORT_MAX;
    return implode(', ', array_slice($items, 0, LC_REPORT_MAX)) . ($more > 0 ? ' ' . ags_fmt($lang->langcheck['rep_more'], $more) : '');
};
$rep   = [];
$rep[] = $lang->langcheck['page_title'] . ' v' . LC_VERSION . ' — ' . date('Y-m-d H:i');
$rep[] = ags_fmt($lang->langcheck['lbl_lang_dir'], $langDir ?? '—') . ' · ' . ags_fmt($lang->langcheck['lbl_reference'], $ref);
$rep[] = $lang->langcheck['kpi_pages'] . ': ' . count($pages) . ' · ' . $lang->langcheck['kpi_clean'] . ': ' . $stat['ok']
       . ' · ' . $lang->langcheck['kpi_warn'] . ': ' . $stat['warn'] . ' · ' . $lang->langcheck['kpi_error'] . ': ' . $stat['error'];
if (count($fileSets) > 1) {
    $rep[] = '';
    $rep[] = '== ' . $lang->langcheck['sec_files'] . ' ==';
    $rep[] = implode(' · ', array_map(fn($l) => $l . ' ' . count($fileSets[$l]), array_keys($fileSets)));
    foreach ($fileGaps as $l => $gap) {
        $rep[] = '[' . $l . '] ' . $lang->langcheck['lbl_files_missing'] . ' (' . count($gap) . '): '
               . implode(', ', array_map(fn($g) => $g . '.lang.php' . (isset($loadedBy[$g]) ? '' : ' ⚠'), $gap));
    }
    if ($fileGaps) $rep[] = $lang->langcheck['hint_orphan'];
}
$rep[] = '';
$rep[] = '== ' . $lang->langcheck['sec_pages'] . ' ==';
$problems = array_filter($pages, fn($p) => $p['status'] !== 'ok');
if (!$problems) $rep[] = $lang->langcheck['empty_problems'];
foreach ($problems as $pg) {
    $rep[] = '';
    $rep[] = '[' . strtoupper($pg['status']) . '] ' . $pg['page'] . ' — ' . $lang->langcheck['lbl_files'] . ': ' . ($pg['files'] ? implode(', ', $pg['files']) : '—')
           . ($pg['js'] ? ' · ' . $lang->langcheck['lbl_js'] . ': ' . implode(', ', $pg['js']) : '');
    foreach ($pg['issues'] as [$sev, $l, $code, $items]) {
        $rep[] = '  - ' . ($l !== null ? '[' . $l . '] ' : '') . $issueLabel($code, $pg['page'])
               . ($items ? ' (' . count($items) . '): ' . $repList(array_map('strval', $items)) : '');
    }
}
$report = implode("\n", $rep) . "\n";

$lcJsLang = [];
foreach ($lang->langcheck as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) $lcJsLang[substr((string)$k, 3)] = (string)$v;
}

stdhead($lang->langcheck['page_title']);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/langcheck.css?v=<?= LC_VERSION ?>">

<div class="container mt-3 mb-4 lc">

    <div class="lc-card mb-3"><div class="lc-head">
        <span class="lc-head-icon"><i class="fa-solid fa-language"></i></span>
        <div class="lc-min0">
            <h1 class="lc-title"><?= e($lang->langcheck['page_title']) ?></h1>
            <div class="lc-sub"><?= e($lang->langcheck['page_subtitle']) ?></div>
        </div>
        <span class="ms-auto lc-muted text-end"><i class="fa-solid fa-code-branch me-1"></i>v<?= LC_VERSION ?><br>
            <small><?= e(ags_fmt($lang->langcheck['lbl_reference'], $ref)) ?></small><br>
            <small><?= e(ags_fmt($lang->langcheck['lbl_lang_dir'], $langDir ?? '—')) ?></small></span>
    </div></div>

    <?php if (!$langs): ?>
    <div class="alert alert-danger d-flex gap-2 rounded-4"><i class="fa-solid fa-circle-exclamation mt-1"></i><div>
        <strong><?= e($lang->langcheck['err_no_lang_dir']) ?></strong><br>
        <?= e(ags_fmt($lang->langcheck['err_no_lang_dir_hint'], $root)) ?></div></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-file-lines',           'ic-blue',  $lang->langcheck['kpi_pages'], count($pages)],
            ['fa-circle-check',         'ic-green', $lang->langcheck['kpi_clean'], $stat['ok']],
            ['fa-triangle-exclamation', 'ic-amber', $lang->langcheck['kpi_warn'],  $stat['warn']],
            ['fa-circle-xmark',         'ic-red',   $lang->langcheck['kpi_error'], $stat['error']],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="lc-card lc-kpi"><span class="lc-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="lc-kpi-label"><?= e($label) ?></div><div class="lc-kpi-value"><?= number_format((int)$val) ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <?php if (count($fileSets) > 1): ?>
    <div class="lc-card overflow-hidden mb-3">
        <div class="lc-sec-head"><span class="lc-sec-icon <?= $fileGaps ? 'ic-red' : 'ic-green' ?>"><i class="fa-solid fa-folder-tree"></i></span>
            <div class="flex-grow-1"><h2 class="lc-sec-title"><?= e($lang->langcheck['sec_files']) ?></h2>
            <div class="lc-counts mt-1"><?php foreach ($fileSets as $l => $set): ?><span class="lc-chip<?= isset($fileGaps[$l]) ? ' is-bad' : '' ?>"><?= e($l) ?> <b><?= count($set) ?></b></span><?php endforeach; ?></div></div></div>
        <div class="lc-body pt-3">
            <?php if (!$fileGaps): ?>
            <div class="lc-ok"><i class="fa-solid fa-circle-check me-1"></i><?= e($lang->langcheck['msg_files_ok']) ?></div>
            <?php endif; ?>
            <?php foreach ($fileGaps as $l => $gap): ?>
            <div class="lc-issue is-error">
                <div class="lc-issue-head"><i class="fa-solid fa-circle-xmark"></i><span class="lc-lang"><?= e($l) ?></span>
                    <span><?= e($lang->langcheck['lbl_files_missing']) ?></span><span class="lc-muted">(<?= count($gap) ?>)</span></div>
                <div class="lc-keys"><?php foreach ($gap as $pgName): $orphan = !isset($loadedBy[$pgName]); ?><code<?= $orphan ? ' class="lc-orphan" title="' . e($lang->langcheck['tip_orphan']) . '"' : '' ?>><?= e($pgName) ?>.lang.php<?= $orphan ? ' ⚠' : '' ?></code><?php endforeach; ?></div>
            </div>
            <?php endforeach; ?>
            <?php if ($fileGaps): ?><div class="lc-muted"><i class="fa-solid fa-circle-info me-1"></i><?= e($lang->langcheck['hint_orphan']) ?></div><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="lc-card overflow-hidden mb-3">
        <div class="lc-sec-head"><span class="lc-sec-icon ic-slate"><i class="fa-solid fa-list-check"></i></span>
            <div><h2 class="lc-sec-title"><?= e($lang->langcheck['sec_pages']) ?></h2>
            <div class="lc-muted"><?= e(implode(' · ', $langs) ?: '—') ?></div></div></div>

        <?php if (!$pages): ?>
        <div class="lc-muted px-4 py-3"><?= e(ags_fmt($lang->langcheck['empty_pages'], $langDir ?? $root)) ?></div>
        <?php elseif (!$shown): ?>
        <div class="lc-empty px-4 py-4"><i class="fa-solid fa-circle-check me-2"></i><?= e($lang->langcheck['empty_problems']) ?></div>
        <?php else: ?>
        <ul class="lc-list">
            <?php foreach ($shown as $pg): ?>
            <li class="lc-item is-<?= $pg['status'] ?>">
                <details>
                    <summary>
                        <span class="lc-badge is-<?= $pg['status'] ?>"><?= e($badgeTxt[$pg['status']]) ?></span>
                        <code class="lc-page"><?= e($pg['page']) ?></code>
                        <span class="lc-counts">
                            <?php foreach ($pg['counts'] as $l => $c): ?>
                            <span class="lc-chip<?= $c === null ? ' is-bad' : '' ?>"><?= e($l) ?> <b><?= $c === null ? '—' : (int)$c ?></b></span>
                            <?php endforeach; ?>
                            <?php if ($pg['files']): ?><span class="lc-chip"><?= e($lang->langcheck['lbl_used']) ?> <b><?= (int)$pg['used'] ?></b></span><?php endif; ?>
                        </span>
                        <?php $sc = array_count_values(array_column($pg['issues'], 0)); ?>
                        <span class="lc-sevs">
                            <?php foreach (['error' => 'fa-circle-xmark', 'warn' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'] as $sv => $si): if (!empty($sc[$sv])): ?>
                            <span class="lc-sev is-<?= $sv ?>"><i class="fa-solid <?= $si ?>"></i><?= (int)$sc[$sv] ?></span>
                            <?php endif; endforeach; ?>
                        </span>
                        <i class="fa-solid fa-chevron-down lc-caret"></i>
                    </summary>
                    <div class="lc-body">
                        <div class="lc-files">
                            <span class="lc-muted"><i class="fa-solid fa-file-code me-1"></i><?= e($lang->langcheck['lbl_files']) ?>:</span>
                            <?php foreach ($pg['files'] ?: ['—'] as $f): ?><code><?= e($f) ?></code><?php endforeach; ?>
                            <?php if ($pg['js']): ?>
                            <span class="lc-muted ms-2"><i class="fa-brands fa-js me-1"></i><?= e($lang->langcheck['lbl_js']) ?>:</span>
                            <?php foreach ($pg['js'] as $f): ?><code><?= e($f) ?></code><?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!$pg['issues']): ?>
                        <div class="lc-ok"><i class="fa-solid fa-circle-check me-1"></i><?= e($lang->langcheck['msg_page_ok']) ?></div>
                        <?php endif; ?>
                        <?php foreach ($pg['issues'] as [$sev, $l, $code, $items]): ?>
                        <div class="lc-issue is-<?= $sev ?>">
                            <div class="lc-issue-head"><i class="fa-solid <?= $sevIcon[$sev] ?>"></i>
                                <?php if ($l !== null): ?><span class="lc-lang"><?= e($l) ?></span><?php endif; ?>
                                <span><?= e($issueLabel($code, $pg['page'])) ?></span>
                                <?php if ($items): ?><span class="lc-muted">(<?= count($items) ?>)</span><?php endif; ?></div>
                            <?php if ($items): ?><div class="lc-keys"><?php foreach ($items as $k): ?><code><?= e((string)$k) ?></code><?php endforeach; ?></div><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <textarea id="lcReport" class="lc-report form-control mb-3" rows="14" readonly hidden><?= e($report) ?></textarea>

    <div class="lc-bar">
        <div class="lc-muted"><i class="fa-solid fa-stopwatch me-1"></i><?= e(ags_fmt($lang->langcheck['hint_scan'], number_format(count($php)), $ms)) ?>
            <span title="<?= e(implode(', ', LC_SKIP_DIRS)) ?>"> · <?= e(ags_fmt($lang->langcheck['hint_skipped'], count(LC_SKIP_DIRS))) ?></span></div>
        <div class="d-flex flex-wrap gap-2 ms-auto" role="group" aria-label="<?= e($lang->langcheck['aria_filter']) ?>">
            <a href="<?= e($url('all')) ?>" class="btn btn-sm lc-pill<?= $show === 'all' ? ' active' : '' ?>"><?= e($lang->langcheck['opt_show_all']) ?></a>
            <a href="<?= e($url('problems')) ?>" class="btn btn-sm lc-pill<?= $show === 'problems' ? ' active' : '' ?>"><?= e($lang->langcheck['opt_show_problems']) ?> <b><?= $stat['warn'] + $stat['error'] ?></b></a>
            <?php if ($shown): ?>
            <button type="button" class="btn btn-sm lc-pill" id="lcToggleAll" data-expand="<?= e($lang->langcheck['btn_expand_all']) ?>" data-collapse="<?= e($lang->langcheck['btn_collapse_all']) ?>"><i class="fa-solid fa-up-right-and-down-left-from-center me-1"></i><span><?= e($lang->langcheck['btn_expand_all']) ?></span></button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm lc-pill" id="lcCopy"><i class="fa-regular fa-copy me-1"></i><span id="lcCopyText"><?= e($lang->langcheck['btn_copy_report']) ?></span></button>
            <a href="<?= e($url($show)) ?>" class="btn btn-sm lc-pill lc-go"><i class="fa-solid fa-rotate me-1"></i><?= e($lang->langcheck['btn_rescan']) ?></a>
        </div>
    </div>
</div>
<script>const AGS_LANG = <?= json_encode($lcJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/admin/scripts/langcheck.js?v=<?= LC_VERSION ?>"></script>
<?php
stdfoot();