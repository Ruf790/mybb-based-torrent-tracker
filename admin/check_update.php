<?php
declare(strict_types=1);

/**
 * Update Check — сверка трекера с репозиторием GitHub (Ruf790/mybb-based-torrent-tracker, ветка main).
 *
 * Релизов/тегов в репозитории нет, поэтому сравниваются коммиты:
 *   • установленная версия = HEAD из локального .git, а если его нет — settings.update_commit
 *     (кнопка «Отметить как актуальную» или SHA, введённый вручную);
 *   • новые коммиты и изменённые файлы — GitHub Compare API;
 *   • миграции — sql/migrations на GitHub, локальная папка и settings.schema_version (как в upgrade.php);
 *   • «Проверить файлы» — git-хэши локальных файлов против дерева ветки (CRLF/LF учитываются).
 *
 * Файлы трекера не скачивает и не меняет. Пишет только settings.update_commit и кэш
 * cache/update_check.json (10 минут). Токен GitHub (необязательно, поднимает лимит
 * с 60 до 5000 запросов в час): $config['github_token'] в include/config.php или env GITHUB_TOKEN.
 */

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert"><strong>Access denied.</strong> Direct initialization of this file is not allowed.</div>');
}

// Сравнение файлов сервера и запись версии — только для сисопа, независимо от групп в staffpanel.
if ((int)($CURUSER['usergroup'] ?? 0) !== UC_SYSOP) {
    print_no_permission(true);
    exit;
}

$lang->load('check_update');

use function htmlspecialchars as e;

const CU_VERSION     = '1.0';
const CU_OWNER       = 'Ruf790';
const CU_REPO        = 'mybb-based-torrent-tracker';
const CU_BRANCH      = 'main';
const CU_CACHE_TTL   = 600;
const CU_SETTING     = 'update_commit';
const CU_BASE_SCHEMA = 1;
/** Всегда отличаются от GitHub — генерируются установщиком */
const CU_SKIP_FILES  = ['include/config.php', 'include/settings.php', 'include/config_announce.php',
                        'config/STAFFTEAM', 'install.lock', 'install_error.log'];
/** Пользовательские данные и не-код */
const CU_SKIP_DIRS   = ['cache/', 'uploads/', 'torrents/', 'error_logs/', 'docs/', '.git/', '.github/'];

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

function cu_t(string $key, string|int|float ...$args): string
{
    global $lang;
    return ags_fmt((string)($lang->check_update[$key] ?? $key), ...$args);
}

function cu_root(): string
{
    return rtrim(str_replace('\\', '/', defined('TSDIR') ? (string)TSDIR : dirname(__DIR__)), '/');
}

function cu_cache_file(): string
{
    return cu_root() . '/cache/update_check.json';
}

function cu_token(): string
{
    global $mybb;
    $t = (string)($mybb->config['github_token'] ?? '');
    if ($t === '') $t = (string)(getenv('GITHUB_TOKEN') ?: '');
    return trim($t);
}

// ── settings ─────────────────────────────────────────────────────────────────

function cu_setting_get(string $name): ?string
{
    global $db;
    $q = $db->sql_query_prepared('SELECT `value` FROM `settings` WHERE `name` = ? LIMIT 1', [$name]);
    $row = $q ? $db->fetch_array($q) : null;
    return is_array($row) ? (string)$row['value'] : null;
}

function cu_setting_set(string $name, string $value): bool
{
    global $db;
    try {
        $r = $db->sql_query_prepared(
            'INSERT INTO `settings` (`name`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$name, $value]
        );
        return $r !== false;
    } catch (Throwable $ex) {
        error_log('[check_update] save failed: ' . $ex->getMessage());
        return false;
    }
}

// ── Установленная версия ─────────────────────────────────────────────────────

/** HEAD из локального .git (сайт развёрнут через git), иначе null */
function cu_git_head(): ?string
{
    $g = cu_root() . '/.git';
    if (!is_file($g . '/HEAD')) return null;
    $head = trim((string)@file_get_contents($g . '/HEAD'));
    if (preg_match('/^[0-9a-f]{40}$/', $head)) return $head;
    if (!preg_match('#^ref:\s*(refs/[A-Za-z0-9._/\-]+)$#', $head, $m) || str_contains($m[1], '..')) return null;
    if (is_file($g . '/' . $m[1])) {
        $s = trim((string)@file_get_contents($g . '/' . $m[1]));
        return preg_match('/^[0-9a-f]{40}$/', $s) ? $s : null;
    }
    foreach (@file($g . '/packed-refs', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^([0-9a-f]{40}) (\S+)$/', $line, $p) && $p[2] === $m[1]) return $p[1];
    }
    return null;
}

/** @return array{0: ?string, 1: string} [sha, источник: git|setting|none] */
function cu_baseline(): array
{
    if (($g = cu_git_head()) !== null) return [$g, 'git'];
    $s = cu_setting_get(CU_SETTING);
    if ($s !== null && preg_match('/^[0-9a-f]{7,40}$/', $s)) return [$s, 'setting'];
    return [null, 'none'];
}

function cu_same_sha(string $a, string $b): bool
{
    $a = strtolower($a);
    $b = strtolower($b);
    return strlen($a) >= 7 && strlen($b) >= 7 && (str_starts_with($a, $b) || str_starts_with($b, $a));
}

// ── GitHub API ───────────────────────────────────────────────────────────────

function cu_repo(): string
{
    return '/repos/' . CU_OWNER . '/' . CU_REPO;
}

function cu_gh(string $sub): string
{
    return 'https://github.com/' . CU_OWNER . '/' . CU_REPO . $sub;
}

function cu_gh_path(string $path): string
{
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

/** GET к api.github.com. 404 → null, прочие ошибки → RuntimeException с текстом для показа */
function cu_api(string $path): ?array
{
    global $cuRate;
    if (!function_exists('curl_init')) throw new RuntimeException(cu_t('err_no_curl'));
    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: ' . CU_REPO . '-update-check',
    ];
    if (($tok = cu_token()) !== '') $headers[] = 'Authorization: Bearer ' . $tok;

    $resp = [];
    $ch = curl_init('https://api.github.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HTTPHEADER      => $headers,
        CURLOPT_CONNECTTIMEOUT  => 6,
        CURLOPT_TIMEOUT         => 30,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 3,
        CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_ENCODING        => '',
        CURLOPT_HEADERFUNCTION  => static function ($ch, string $line) use (&$resp): int {
            $p = strpos($line, ':');
            if ($p !== false) $resp[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
            return strlen($line);
        },
    ]);
    $body  = curl_exec($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    unset($ch); // curl_close() в PHP 8.5 устарел

    if (isset($resp['x-ratelimit-remaining'])) {
        $cuRate = ['n' => (int)$resp['x-ratelimit-remaining'], 'limit' => (int)($resp['x-ratelimit-limit'] ?? 0)];
    }
    if ($body === false || $errno !== 0) {
        error_log('[check_update] curl #' . $errno . ' ' . $err . ' for ' . $path);
        throw new RuntimeException(cu_t('err_network', $err !== '' ? $err : '#' . $errno));
    }
    if ($code === 404) return null;
    if (in_array($code, [403, 429], true) && ($resp['x-ratelimit-remaining'] ?? '') === '0') {
        throw new RuntimeException(cu_t('err_rate_limit', date('H:i', (int)($resp['x-ratelimit-reset'] ?? TIMENOW))));
    }
    if ($code === 401) throw new RuntimeException(cu_t('err_token'));
    $data = json_decode((string)$body, true);
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        error_log('[check_update] HTTP ' . $code . ' for ' . $path . ': ' . mb_substr((string)$body, 0, 500));
        throw new RuntimeException(cu_t('err_api', $code));
    }
    return $data;
}

function cu_commit_brief(array $c): array
{
    $sha = (string)($c['sha'] ?? '');
    return [
        'sha'    => $sha,
        'short'  => substr($sha, 0, 7),
        'msg'    => trim(strtok((string)($c['commit']['message'] ?? ''), "\n") ?: ''),
        'date'   => (string)($c['commit']['committer']['date'] ?? $c['commit']['author']['date'] ?? ''),
        'author' => (string)($c['author']['login'] ?? $c['commit']['author']['name'] ?? ''),
        'url'    => (string)($c['html_url'] ?? ''),
    ];
}

/** Состояние репозитория относительно установленной версии (кэш CU_CACHE_TTL) */
function cu_collect(?string $base, bool $force): array
{
    global $cuRate;
    $cacheFile = cu_cache_file();
    if (!$force && is_file($cacheFile)) {
        $c = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($c) && ($c['base'] ?? null) === $base && (TIMENOW - (int)($c['time'] ?? 0)) < CU_CACHE_TTL) {
            return $c;
        }
    }
    $r   = cu_repo();
    $tip = cu_api($r . '/commits/' . rawurlencode(CU_BRANCH));
    if ($tip === null) throw new RuntimeException(cu_t('err_no_branch', CU_BRANCH));

    $out = ['time' => TIMENOW, 'base' => $base, 'latest' => cu_commit_brief($tip), 'compare' => null, 'migrations' => [], 'rate' => null];

    if ($base !== null && !cu_same_sha($base, $out['latest']['sha'])) {
        $cmp = cu_api($r . '/compare/' . rawurlencode($base) . '...' . $out['latest']['sha']);
        if ($cmp === null) {
            $out['compare'] = ['error' => 'base_unknown'];
        } else {
            $files = [];
            foreach ((array)($cmp['files'] ?? []) as $f) {
                $files[] = [
                    'name'   => (string)($f['filename'] ?? ''),
                    'prev'   => (string)($f['previous_filename'] ?? ''),
                    'status' => (string)($f['status'] ?? ''),
                    'add'    => (int)($f['additions'] ?? 0),
                    'del'    => (int)($f['deletions'] ?? 0),
                ];
            }
            $out['compare'] = [
                'ahead'   => (int)($cmp['ahead_by'] ?? 0),
                'behind'  => (int)($cmp['behind_by'] ?? 0),
                'commits' => array_reverse(array_map('cu_commit_brief', (array)($cmp['commits'] ?? []))),
                'files'   => $files,
                'url'     => (string)($cmp['html_url'] ?? ''),
            ];
        }
    }

    foreach ((array)cu_api($r . '/contents/sql/migrations?ref=' . rawurlencode(CU_BRANCH)) as $e) {
        if (is_array($e) && ($e['type'] ?? '') === 'file'
            && preg_match('/^(\d{1,6})_[A-Za-z0-9_\-]+\.sql$/', (string)($e['name'] ?? ''), $m)) {
            $out['migrations'][] = ['ver' => (int)$m[1], 'name' => (string)$e['name'], 'url' => (string)($e['html_url'] ?? '')];
        }
    }
    usort($out['migrations'], static fn($a, $b) => $a['ver'] <=> $b['ver']);
    $out['rate'] = $cuRate;

    if (!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0755, true);
    @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $out;
}

// ── Миграции и итог ──────────────────────────────────────────────────────────

/** @return array<int, string> версия => имя файла */
function cu_local_migrations(): array
{
    $out = [];
    foreach (glob(cu_root() . '/sql/migrations/*.sql') ?: [] as $f) {
        if (preg_match('/^(\d{1,6})_[A-Za-z0-9_\-]+\.sql$/', basename($f), $m)) $out[(int)$m[1]] = basename($f);
    }
    ksort($out);
    return $out;
}

function cu_summary(?array $d, ?string $base): array
{
    $v      = cu_setting_get('schema_version');
    $schema = ($v !== null && ctype_digit($v)) ? (int)$v : CU_BASE_SCHEMA;
    $local  = cu_local_migrations();
    $remoteNew = [];
    $pending   = [];
    foreach ((array)($d['migrations'] ?? []) as $m) {
        if ($m['ver'] > $schema && !isset($local[$m['ver']])) $remoteNew[] = $m;
    }
    foreach ($local as $ver => $name) {
        if ($ver > $schema) $pending[] = $name;
    }

    $cmp   = $d['compare'] ?? null;
    $state = 'unknown';
    if ($d !== null && $base !== null) {
        if (cu_same_sha($base, (string)$d['latest']['sha'])) $state = 'current';
        elseif (isset($cmp['error']))                       $state = 'base_unknown';
        elseif (($cmp['ahead'] ?? 0) > 0)                   $state = 'behind';
        elseif (($cmp['behind'] ?? 0) > 0)                  $state = 'ahead_local';
        else                                                $state = 'current';
    }
    return [
        'schema'      => $schema,
        'state'       => $state,
        'new_commits' => (int)($cmp['ahead'] ?? 0),
        'diverged'    => (int)($cmp['behind'] ?? 0),
        'files'       => is_array($cmp) && !isset($cmp['error']) ? count($cmp['files']) : 0,
        'mig_new'     => $remoteNew,
        'mig_pending' => $pending,
    ];
}

// ── Сравнение файлов с деревом ветки ─────────────────────────────────────────

function cu_blob_sha(string $d): string
{
    return sha1('blob ' . strlen($d) . "\0" . $d);
}

function cu_skip(string $path): bool
{
    if (in_array($path, CU_SKIP_FILES, true) || basename($path) === '.htaccess') return true;
    foreach (CU_SKIP_DIRS as $d) {
        if (str_starts_with($path, $d)) return true;
    }
    return false;
}

function cu_scan(string $sha): array
{
    @set_time_limit(300);
    $tree = cu_api(cu_repo() . '/git/trees/' . rawurlencode($sha) . '?recursive=1');
    if ($tree === null) throw new RuntimeException(cu_t('err_api', 404));

    $root = cu_root();
    $res  = ['time' => TIMENOW, 'sha' => $sha, 'truncated' => !empty($tree['truncated']), 'same' => 0, 'skipped' => 0, 'modified' => [], 'missing' => []];
    foreach ((array)($tree['tree'] ?? []) as $e) {
        if (!is_array($e) || ($e['type'] ?? '') !== 'blob') continue;
        $path = (string)($e['path'] ?? '');
        if ($path === '' || str_contains($path, '..') || cu_skip($path)) { $res['skipped']++; continue; }
        $local = $root . '/' . $path;
        if (!is_file($local)) { $res['missing'][] = $path; continue; }
        $data = @file_get_contents($local);
        $want = strtolower((string)($e['sha'] ?? ''));
        if ($data !== false && (
            cu_blob_sha($data) === $want
            // Windows: файл мог прийти с CRLF при LF в репозитории (и наоборот)
            || (str_contains($data, "\r\n") && cu_blob_sha(str_replace("\r\n", "\n", $data)) === $want)
            || (!str_contains($data, "\r\n") && str_contains($data, "\n") && cu_blob_sha(str_replace("\n", "\r\n", $data)) === $want)
        )) {
            $res['same']++;
        } else {
            $res['modified'][] = $path;
        }
    }
    return $res;
}

function cu_date(string $iso): string
{
    $t = strtotime($iso);
    return $t ? date('Y-m-d H:i', $t) : '';
}

// ═════════════════════════════════════════════════════════════════════════════
//  POST (Post/Redirect/Get)
// ═════════════════════════════════════════════════════════════════════════════

$cuRate = null;
$cuSelf = 'index.php?act=check_update';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        flash_message(cu_t('flash_bad_key'), 'error');
        admin_redirect($cuSelf);
    }
    $do = (string)($_POST['do'] ?? '');
    try {
        [$base, $src] = cu_baseline();
        switch ($do) {
            case 'refresh':
                @unlink(cu_cache_file());
                break;

            case 'mark_latest':
            case 'set_sha':
                if ($src === 'git') break;
                if ($do === 'mark_latest') {
                    $sha = (string)cu_collect($base, true)['latest']['sha'];
                } else {
                    $in = strtolower(trim((string)($_POST['sha'] ?? '')));
                    if (!preg_match('/^[0-9a-f]{7,40}$/', $in)) {
                        flash_message(cu_t('flash_bad_sha'), 'error');
                        break;
                    }
                    $cm = cu_api(cu_repo() . '/commits/' . $in);
                    if ($cm === null || empty($cm['sha'])) {
                        flash_message(cu_t('flash_sha_404', $in), 'error');
                        break;
                    }
                    $sha = (string)$cm['sha'];
                }
                if (cu_setting_set(CU_SETTING, $sha)) {
                    @unlink(cu_cache_file());
                    write_log('Update check: installed commit set to ' . substr($sha, 0, 7)
                        . ' (was ' . ($base !== null ? substr($base, 0, 7) : 'unknown') . ') by ' . $CURUSER['username']);
                    flash_message(cu_t('flash_saved', substr($sha, 0, 7)), 'success');
                } else {
                    flash_message(cu_t('flash_save_fail'), 'error');
                }
                break;

            case 'scan':
                $_SESSION['cu_scan'] = cu_scan((string)cu_collect($base, false)['latest']['sha']);
                break;

            case 'scan_clear':
                unset($_SESSION['cu_scan']);
                break;
        }
    } catch (Throwable $ex) {
        flash_message($ex->getMessage(), 'error');
    }
    admin_redirect($cuSelf);
}

// ═════════════════════════════════════════════════════════════════════════════
//  Данные для страницы
// ═════════════════════════════════════════════════════════════════════════════

[$base, $src] = cu_baseline();
$d   = null;
$err = '';
try {
    $d = cu_collect($base, false);
} catch (Throwable $ex) {
    $err = $ex->getMessage();
}
$sum  = cu_summary($d, $base);
$scan = is_array($_SESSION['cu_scan'] ?? null) ? $_SESSION['cu_scan'] : null;
$cmp  = (is_array($d['compare'] ?? null) && !isset($d['compare']['error'])) ? $d['compare'] : null;
$key  = e((string)$mybb->post_code);

$stateBox = match ($sum['state']) {
    'current'      => ['is-ok',    'fa-circle-check',         cu_t('state_current')],
    'behind'       => ['is-warn',  'fa-cloud-arrow-down',     cu_t('state_behind', $sum['new_commits'])
                                                              . ($sum['diverged'] > 0 ? ' ' . cu_t('state_diverged', $sum['diverged'], CU_BRANCH) : '')],
    'ahead_local'  => ['is-info',  'fa-circle-info',          cu_t('state_ahead_local', CU_BRANCH)],
    'base_unknown' => ['is-error', 'fa-circle-xmark',         cu_t('state_base_unknown', substr((string)$base, 0, 7))],
    default        => $d !== null ? ['is-info', 'fa-circle-question', cu_t('state_no_base')] : null,
};
$fileBadge = ['added' => 'is-ok', 'removed' => 'is-error', 'modified' => 'is-warn', 'renamed' => 'is-info'];

$cuJsLang = [];
foreach ($lang->check_update as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) $cuJsLang[substr((string)$k, 3)] = (string)$v;
}

stdhead(cu_t('page_title'));
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/check_update.css?v=<?= CU_VERSION ?>">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">

<div class="container mt-3 mb-4 cu">

    <div class="cu-card mb-3"><div class="cu-head">
        <span class="cu-head-icon"><i class="fa-brands fa-github"></i></span>
        <div class="cu-min0">
            <h1 class="cu-title"><?= e(cu_t('page_title')) ?></h1>
            <div class="cu-sub"><?= e(cu_t('page_subtitle')) ?></div>
        </div>
        <span class="ms-auto cu-muted text-end"><i class="fa-solid fa-code-branch me-1"></i>v<?= CU_VERSION ?><br>
            <a href="<?= e(cu_gh('/tree/' . CU_BRANCH)) ?>" target="_blank" rel="noopener"><small><?= e(CU_OWNER . '/' . CU_REPO) ?> · <?= e(CU_BRANCH) ?></small></a></span>
    </div></div>

    <?php if ($err !== ''): ?>
    <div class="alert alert-danger d-flex gap-2 rounded-4"><i class="fa-solid fa-circle-exclamation mt-1"></i><div><?= e($err) ?></div></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-code-commit',          $sum['new_commits'] > 0 ? 'ic-amber' : 'ic-green', cu_t('kpi_commits'),    $cmp !== null ? (string)$sum['new_commits'] : '—'],
            ['fa-file-code',            $sum['files'] > 0 ? 'ic-blue' : 'ic-slate',        cu_t('kpi_files'),      $cmp !== null ? (string)$sum['files'] : '—'],
            ['fa-database',             ($sum['mig_new'] || $sum['mig_pending']) ? 'ic-red' : 'ic-green', cu_t('kpi_migrations'), (string)(count($sum['mig_new']) + count($sum['mig_pending']))],
            ['fa-magnifying-glass',     $scan === null ? 'ic-slate' : (($scan['modified'] || $scan['missing']) ? 'ic-amber' : 'ic-green'),
                                        cu_t('kpi_scan'),       $scan === null ? '—' : (string)(count($scan['modified']) + count($scan['missing']))],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="cu-card cu-kpi"><span class="cu-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="cu-kpi-label"><?= e($label) ?></div><div class="cu-kpi-value"><?= e($val) ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <!-- Версии -->
    <div class="cu-card mb-3">
        <div class="cu-sec-head"><span class="cu-sec-icon ic-blue"><i class="fa-solid fa-code-compare"></i></span>
            <h2 class="cu-sec-title"><?= e(cu_t('sec_version')) ?></h2></div>
        <div class="cu-body">
            <dl class="cu-dl">
                <?php if ($d !== null): $l = $d['latest']; ?>
                <dt><?= e(cu_t('lbl_latest')) ?></dt>
                <dd><a class="cu-sha" href="<?= e($l['url']) ?>" target="_blank" rel="noopener"><?= e($l['short']) ?></a>
                    <?= e($l['msg']) ?> <span class="cu-muted">· <?= e(cu_date($l['date'])) ?></span></dd>
                <?php endif; ?>
                <dt><?= e(cu_t('lbl_installed')) ?></dt>
                <dd><?php if ($base !== null): ?>
                    <a class="cu-sha" href="<?= e(cu_gh('/commit/' . $base)) ?>" target="_blank" rel="noopener"><?= e(substr($base, 0, 7)) ?></a>
                    <span class="cu-chip"><?= e(cu_t($src === 'git' ? 'src_git' : 'src_setting')) ?></span>
                    <?php else: ?><span class="cu-muted"><?= e(cu_t('lbl_unknown')) ?></span><?php endif; ?></dd>
            </dl>

            <?php if ($stateBox !== null): ?>
            <div class="cu-state <?= $stateBox[0] ?>"><i class="fa-solid <?= $stateBox[1] ?>"></i><span><?= e($stateBox[2]) ?></span></div>
            <?php endif; ?>

            <?php if ($src === 'git'): ?>
            <div class="cu-muted"><i class="fa-solid fa-circle-info me-1"></i><?= e(cu_t('hint_git')) ?></div>
            <?php else: ?>
            <details class="cu-details">
                <summary><i class="fa-solid fa-pen me-1"></i><?= e(cu_t('lbl_custom_sha')) ?></summary>
                <form method="post" action="<?= e($cuSelf) ?>" class="cu-sha-form" data-confirm="set_sha">
                    <input type="hidden" name="my_post_key" value="<?= $key ?>">
                    <input type="hidden" name="do" value="set_sha">
                    <input type="text" name="sha" class="form-control form-control-sm cu-mono" required
                           pattern="[0-9a-fA-F]{7,40}" placeholder="a1b2c3d" value="<?= e((string)($base ?? '')) ?>"
                           aria-label="<?= e(cu_t('lbl_custom_sha')) ?>">
                    <button type="submit" class="btn btn-sm cu-pill"><i class="fa-solid fa-floppy-disk me-1"></i><?= e(cu_t('btn_save_sha')) ?></button>
                </form>
                <div class="cu-muted mt-1"><?= e(cu_t('hint_custom_sha')) ?></div>
            </details>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($cmp !== null): ?>
    <!-- Что нового -->
    <?php if ($cmp['commits']): ?>
    <div class="cu-card overflow-hidden mb-3">
        <div class="cu-sec-head"><span class="cu-sec-icon ic-amber"><i class="fa-solid fa-code-commit"></i></span>
            <h2 class="cu-sec-title"><?= e(cu_t('sec_commits')) ?></h2><span class="cu-badge is-warn ms-auto"><?= (int)$cmp['ahead'] ?></span></div>
        <ul class="cu-list cu-scroll">
            <?php foreach ($cmp['commits'] as $cm): ?>
            <li><a href="<?= e($cm['url']) ?>" target="_blank" rel="noopener">
                <span class="cu-sha"><?= e($cm['short']) ?></span>
                <span class="cu-grow"><?= e($cm['msg']) ?></span>
                <span class="cu-muted text-nowrap"><?= e($cm['author']) ?> · <?= e(cu_date($cm['date'])) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($cmp['files']): ?>
    <div class="cu-card overflow-hidden mb-3">
        <div class="cu-sec-head"><span class="cu-sec-icon ic-blue"><i class="fa-solid fa-file-code"></i></span>
            <div class="cu-min0"><h2 class="cu-sec-title"><?= e(cu_t('sec_files')) ?></h2>
            <div class="cu-muted"><?= e(cu_t('hint_cmp_limit')) ?> <a href="<?= e($cmp['url']) ?>" target="_blank" rel="noopener"><?= e(cu_t('lnk_full_diff')) ?></a></div></div>
            <span class="cu-badge is-info ms-auto"><?= count($cmp['files']) ?></span></div>
        <ul class="cu-list cu-scroll">
            <?php foreach ($cmp['files'] as $f):
                $ref = $f['status'] === 'removed' ? (string)$base : (string)$d['latest']['sha'];
                $st  = isset($fileBadge[$f['status']]) ? $f['status'] : ''; ?>
            <li><a href="<?= e(cu_gh('/blob/' . $ref . '/' . cu_gh_path($f['name']))) ?>" target="_blank" rel="noopener">
                <span class="cu-grow cu-mono"><?= $f['prev'] !== '' ? e($f['prev']) . ' → ' : '' ?><?= e($f['name']) ?></span>
                <span class="cu-add">+<?= (int)$f['add'] ?></span><span class="cu-del">−<?= (int)$f['del'] ?></span>
                <span class="cu-badge <?= $st !== '' ? $fileBadge[$st] : '' ?>"><?= e($st !== '' ? cu_t('st_' . $st) : $f['status']) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Миграции -->
    <div class="cu-card mb-3">
        <div class="cu-sec-head"><span class="cu-sec-icon <?= ($sum['mig_new'] || $sum['mig_pending']) ? 'ic-red' : 'ic-green' ?>"><i class="fa-solid fa-database"></i></span>
            <div class="cu-min0"><h2 class="cu-sec-title"><?= e(cu_t('sec_migrations')) ?></h2>
            <div class="cu-muted"><?= e(cu_t('lbl_schema', $sum['schema'])) ?></div></div></div>
        <div class="cu-body">
            <?php if ($sum['mig_new']): ?>
            <div class="cu-issue is-warn"><div class="cu-issue-head"><i class="fa-solid fa-cloud-arrow-down"></i><?= e(cu_t('mig_new')) ?></div>
                <div class="cu-keys"><?php foreach ($sum['mig_new'] as $m): ?><a href="<?= e($m['url']) ?>" target="_blank" rel="noopener"><code><?= e($m['name']) ?></code></a><?php endforeach; ?></div></div>
            <?php endif; ?>
            <?php if ($sum['mig_pending']): ?>
            <div class="cu-issue is-error"><div class="cu-issue-head"><i class="fa-solid fa-triangle-exclamation"></i><?= e(cu_t('mig_pending')) ?></div>
                <div class="cu-keys"><?php foreach ($sum['mig_pending'] as $n): ?><code><?= e($n) ?></code><?php endforeach; ?></div></div>
            <?php endif; ?>
            <?php if (!$sum['mig_new'] && !$sum['mig_pending']): ?>
            <div class="cu-ok"><i class="fa-solid fa-circle-check me-1"></i><?= e(cu_t('mig_ok')) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Сравнение файлов -->
    <div class="cu-card mb-3">
        <div class="cu-sec-head"><span class="cu-sec-icon ic-slate"><i class="fa-solid fa-magnifying-glass"></i></span>
            <div class="cu-min0"><h2 class="cu-sec-title"><?= e(cu_t('sec_scan')) ?></h2>
            <div class="cu-muted"><?= e(cu_t('hint_scan', CU_BRANCH)) ?></div></div></div>
        <div class="cu-body">
            <?php if ($scan === null): ?>
            <div class="cu-muted"><?= e(cu_t('scan_none')) ?></div>
            <?php else: ?>
            <div class="cu-muted"><?= e(cu_t('scan_info', substr($scan['sha'], 0, 7), date('H:i', (int)$scan['time']), $scan['same'], $scan['skipped'])) ?></div>
            <?php if ($scan['truncated']): ?><div class="cu-issue is-warn"><div class="cu-issue-head"><i class="fa-solid fa-triangle-exclamation"></i><?= e(cu_t('scan_truncated')) ?></div></div><?php endif; ?>
            <?php foreach (['modified' => ['scan_modified', 'is-warn', 'fa-pen-to-square'], 'missing' => ['scan_missing', 'is-error', 'fa-file-circle-xmark']] as $k => [$label, $cls, $ic]): ?>
            <?php if ($scan[$k]): ?>
            <div class="cu-issue <?= $cls ?>"><div class="cu-issue-head"><i class="fa-solid <?= $ic ?>"></i><?= e(cu_t($label, count($scan[$k]))) ?></div>
                <div class="cu-keys cu-scroll"><?php foreach ($scan[$k] as $p): ?><a href="<?= e(cu_gh('/blob/' . $scan['sha'] . '/' . cu_gh_path($p))) ?>" target="_blank" rel="noopener"><code><?= e($p) ?></code></a><?php endforeach; ?></div></div>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$scan['modified'] && !$scan['missing']): ?><div class="cu-ok"><i class="fa-solid fa-circle-check me-1"></i><?= e(cu_t('scan_clean')) ?></div><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Нижняя панель -->
    <div class="cu-bar">
        <div class="cu-muted"><?php if ($d !== null): ?><i class="fa-solid fa-clock me-1"></i><?= e(cu_t('hint_checked', date('H:i', (int)$d['time']), (int)(CU_CACHE_TTL / 60))) ?>
            <?php if (is_array($d['rate'] ?? null)): ?> · <?= e(cu_t('hint_rate', (int)$d['rate']['n'], (int)$d['rate']['limit'])) ?><?php endif; ?><?php endif; ?></div>
        <div class="d-flex flex-wrap gap-2 ms-auto">
            <a class="btn btn-sm cu-pill" href="<?= e(cu_gh('/archive/refs/heads/' . CU_BRANCH . '.zip')) ?>"><i class="fa-solid fa-file-zipper me-1"></i><?= e(cu_t('btn_zip')) ?></a>
            <form method="post" action="<?= e($cuSelf) ?>" class="d-inline" data-busy="1">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <input type="hidden" name="do" value="<?= $scan === null ? 'scan' : 'scan_clear' ?>">
                <button type="submit" class="btn btn-sm cu-pill"><i class="fa-solid <?= $scan === null ? 'fa-magnifying-glass' : 'fa-eye-slash' ?> me-1"></i><?= e(cu_t($scan === null ? 'btn_scan' : 'btn_scan_clear')) ?></button>
            </form>
            <?php if ($src !== 'git' && $d !== null && $sum['state'] !== 'current'): ?>
            <form method="post" action="<?= e($cuSelf) ?>" class="d-inline" data-confirm="mark_latest" data-sha="<?= e($d['latest']['short']) ?>">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <input type="hidden" name="do" value="mark_latest">
                <button type="submit" class="btn btn-sm cu-pill cu-ok-btn"><i class="fa-solid fa-check-double me-1"></i><?= e(cu_t('btn_mark')) ?></button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= e($cuSelf) ?>" class="d-inline" data-busy="1">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <input type="hidden" name="do" value="refresh">
                <button type="submit" class="btn btn-sm cu-pill cu-go"><i class="fa-solid fa-rotate me-1"></i><?= e(cu_t('btn_refresh')) ?></button>
            </form>
        </div>
    </div>
</div>
<script>const AGS_LANG = <?= json_encode($cuJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/check_update.js?v=<?= CU_VERSION ?>"></script>
<?php
stdfoot();
