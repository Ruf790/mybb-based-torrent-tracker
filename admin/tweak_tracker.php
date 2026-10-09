<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}

define('TT_VERSION', '3.0');

// DAY_IN_SECONDS в контексте admin/index.php не определён - своя константа.
const TT_DAY          = 86400;
const TT_BATCH        = 1000;
const TT_LOCK_NAME    = 'ag_tweak_tracker';
const TT_NAMES_SHOWN  = 10;               // сколько имён файлов показывать в отчёте
const TT_MIN_FILE_AGE = 3600;             // файлы моложе часа на диске не трогаем (гонка upload -> INSERT)
const TT_ASSET_VER    = 2;

// Служебные файлы, которые сканер диска не удаляет никогда.
const TT_PROTECTED_FILES = ['index.html', 'index.htm', 'index.php', '.htaccess', 'web.config', '.gitkeep'];

if (!defined('ADMIN_DIR')) {
    define('ADMIN_DIR', TSDIR . '/admin/');
}

global $lang;
$lang->load('tweak_tracker');

// {1}, {2}... и %1$s (во что $lang->load() превращает {1}) -> аргументы.
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach (array_values($args) as $i => $a) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$a;
            $map['%' . $n . '$s'] = (string)$a;
        }
        return $map ? strtr($str, $map) : $str;
    }
}

/** Строка из ланга tweak_tracker с подстановкой {1}, {2}... */
function tt_t(string $key, string|int|float ...$args): string
{
    global $lang;
    return ags_fmt((string)($lang->tweak_tracker[$key] ?? $key), ...$args);
}

/**
 * Отложенный перевод: в отчёт пишется ключ + аргументы, текст собирается
 * при выводе на языке того, кто смотрит страницу.
 */
function tt_msg(string $key, string|int|float ...$args): array
{
    return ['k' => $key, 'a' => array_values($args)];
}

/** Текст подписи/примечания шага: tt_msg()-массив или готовая строка (ошибки, имена файлов, старые отчёты). */
function tt_text(array|string $m): string
{
    if (is_string($m)) return $m;
    return tt_t((string)($m['k'] ?? ''), ...array_values((array)($m['a'] ?? [])));
}

// ── Настройки ─────────────────────────────────────────────
$TT_CFG = [
    'backup_dir'        => ADMIN_DIR . 'backup',
    'backup_keep_days'  => 30,
    'draft_max_age'     => 2 * TT_DAY,               // черновики вложений
    'torrent_dir'       => TSDIR . '/torrents',      // {id}.torrent
    'screens_dir'       => TSDIR . '/torrents/screens',
    'images_dir'        => TSDIR . '/torrents/images',   // постеры: torrents.t_image / t_image2
    'optimize_min_free' => 10 * 1048576,             // OPTIMIZE только если DATA_FREE >= 10 MB
    'report_file'       => TSDIR . '/cache/tweak_tracker_last.php',
];

// ── Группы операций (порядок = порядок выполнения) ─────────
$TT_GROUPS = [
    'db_orphans' => [
        'icon'    => 'fa-link-slash',
        'title'   => tt_t('grp_db_orphans_title'),
        'desc'    => tt_t('grp_db_orphans_desc'),
        'default' => true,
    ],
    'file_records' => [
        'icon'    => 'fa-paperclip',
        'title'   => tt_t('grp_file_records_title'),
        'desc'    => tt_t('grp_file_records_desc'),
        'default' => true,
    ],
    'disk_scan' => [
        'icon'    => 'fa-hard-drive',
        'title'   => tt_t('grp_disk_scan_title'),
        'desc'    => tt_t('grp_disk_scan_desc'),
        'default' => true,
    ],
    'time_cleanup' => [
        'icon'    => 'fa-clock-rotate-left',
        'title'   => tt_t('grp_time_cleanup_title'),
        'desc'    => tt_t('grp_time_cleanup_desc'),
        'default' => true,
    ],
    'backups' => [
        'icon'    => 'fa-box-archive',
        'title'   => tt_t('grp_backups_title'),
        'desc'    => tt_t('grp_backups_desc', $TT_CFG['backup_keep_days']),
        'default' => true,
    ],
    'recount' => [
        'icon'    => 'fa-calculator',
        'title'   => tt_t('grp_recount_title'),
        'desc'    => tt_t('grp_recount_desc'),
        'default' => true,
    ],
    'optimize' => [
        'icon'    => 'fa-gauge-high',
        'title'   => tt_t('grp_optimize_title'),
        'desc'    => tt_t('grp_optimize_desc'),
        'default' => false,
    ],
];

// ── Правила для сирот в БД ────────────────────────────────
// [таблица, [[колонка, ref_таблица, ref_колонка, zero_ok?], ...], 'any'|'all']
// 'any' - удалить, если битая хотя бы одна ссылка; 'all' - только если битые все.
// zero_ok = true: значение 0 считается допустимым (гость/система).
// NULL всегда считается "не привязано" и не удаляется.
// Правила с несуществующей таблицей/колонкой пропускаются и видны в отчёте
// как "Skipped" - поэтому угаданные имена ниже безопасны, проверь их в dry run.
// Порядок важен: comments идёт раньше comment_likes.
$TT_ORPHAN_RULES = [
    ['bookmarks',           [['userid', 'users', 'id'], ['torrentid', 'torrents', 'id']]],
    ['cheat_attempts',      [['uid', 'users', 'id'], ['torrentid', 'torrents', 'id']]],
    ['comments',            [['user', 'users', 'id'], ['torrent', 'torrents', 'id']]],
    ['notconnectablepmlog', [['user', 'users', 'id']]],
    ['peers',               [['userid', 'users', 'id'], ['torrent', 'torrents', 'id']]],
    ['reports',             [['addedby', 'users', 'id']]],
    ['snatched',            [['userid', 'users', 'id'], ['torrentid', 'torrents', 'id']]],
    ['staffmessages',       [['sender', 'users', 'id']]],
    ['hit_and_run',         [['userid', 'users', 'id'], ['torrentid', 'torrents', 'id']]],
    ['inactivity',          [['userid', 'users', 'id']]],
    // В MyBB каждая строка ЛС - копия в ящике владельца uid. Ящик удалённого
    // пользователя удаляем; копии в "Отправленных" живых пользователей остаются.
    ['privatemessages',     [['uid', 'users', 'id']]],

    // MyBB
    ['threadsread',         [['uid', 'users', 'id'], ['tid', 'threads', 'tid']]],
    ['forumsread',          [['uid', 'users', 'id']]],
    ['threadsubscriptions', [['uid', 'users', 'id'], ['tid', 'threads', 'tid']]],
    ['forumsubscriptions',  [['uid', 'users', 'id']]],
    ['pollvotes',           [['uid', 'users', 'id', true], ['pid', 'polls', 'pid']]],
    ['buddyrequests',       [['uid', 'users', 'id'], ['touid', 'users', 'id']]],

    // Трекер (имена сверены со схемой 2026-09-30)
    ['torrent_ratings',       [['torrent_id', 'torrents', 'id'], ['user_id', 'users', 'id']]],
    ['threadratings',         [['tid', 'threads', 'tid'], ['user_id', 'users', 'id']]],
    ['torrents_nfo',          [['torrent_id', 'torrents', 'id']]],
    ['request_votes',         [['request_id', 'requests', 'id'], ['user_id', 'users', 'id']]],
    ['request_comments',      [['request_id', 'requests', 'id'], ['user_id', 'users', 'id']]],
    ['offer_votes',           [['offer_id', 'offers', 'id'], ['user_id', 'users', 'id']]],
    ['offer_comments',        [['offer_id', 'offers', 'id'], ['user_id', 'users', 'id']]],
    ['auto_vip',              [['userid', 'users', 'id']]],
    ['2fa',                   [['uid', 'users', 'id']]],
    ['user_devices',          [['uid', 'users', 'id']]],
    ['password_reset_tokens', [['userid', 'users', 'id']]],
];

// ── Истёкшие записи: [таблица, колонка-время, дней] ────────
// 0 дней = "колонка хранит момент истечения, удалить всё, что уже истекло".
$TT_TIME_RULES = [
    ['sessions',              'time',       30],
    ['searchlog',             'dateline',   7],
    ['loginattempts',         'added',      30],
    ['2fa_pending',           'created_at', 1],   // токен живёт минуты, сутки - с запасом
    ['password_reset_tokens', 'expires_at', 0],
    ['mailerrors',            'dateline',   90],
    ['cheat_attempts',        'added',      180],
];

// ═════════════════════════════════════════════════════════
//  Хелперы
// ═════════════════════════════════════════════════════════

/** $localized = false - английские единицы для write_log(). */
function tt_size(int $bytes, bool $localized = true): string
{
    [$num, $unit] = match (true) {
        $bytes >= 1073741824 => [round($bytes / 1073741824, 2), 'gb'],
        $bytes >= 1048576    => [round($bytes / 1048576, 2), 'mb'],
        $bytes >= 1024       => [round($bytes / 1024, 2), 'kb'],
        default              => [$bytes, 'b'],
    };
    return $localized
        ? tt_t('unit_' . $unit, $num)
        : $num . ' ' . strtoupper($unit);
}

function tt_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function tt_step(string $group, array|string $label, int $rows = 0, int $bytes = 0, string $status = 'ok', array|string $note = ''): array
{
    return compact('group', 'label', 'rows', 'bytes', 'status', 'note');
}

function tt_has(string $table, ?string $field = null): bool
{
    global $db;
    if (!$db->table_exists($table)) return false;
    return $field === null || $db->field_exists($field, $table);
}

/** DATETIME/TIMESTAMP-колонку нельзя сравнивать с unix-временем напрямую. */
function tt_is_datetime(string $table, string $field): bool
{
    global $db;
    $row = $db->fetch_array($db->sql_query_prepared(
        'SELECT DATA_TYPE AS t FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $field]
    ));
    return in_array(strtolower((string)($row['t'] ?? '')), ['datetime', 'timestamp', 'date'], true);
}

function tt_count(string $sql, array $params = []): int
{
    global $db;
    $row = $db->fetch_array($db->sql_query_prepared($sql, $params));
    return (int)($row['c'] ?? 0);
}

function tt_delete_ids(string $table, string $idCol, array $ids): void
{
    global $db;
    foreach (array_chunk($ids, TT_BATCH) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $db->sql_query_prepared("DELETE FROM `{$table}` WHERE `{$idCol}` IN ({$ph})", $chunk);
    }
}

// Вложения комментариев лежат плоско в uploads/attachments/, вложения постов
// форума - в uploads/{YYYYMM}/, и attachname для них уже содержит этот префикс.
function tt_attach_path(string $attachname): string
{
    return str_contains($attachname, '/')
        ? TSDIR . '/uploads/' . $attachname
        : TSDIR . '/uploads/attachments/' . $attachname;
}

/**
 * Выбирает строки, удаляет их файлы и сами строки пачками.
 * $paths(row) возвращает список путей к файлам этой строки.
 */
function tt_purge_with_files(string $select, array $params, string $table, string $idCol, callable $paths, bool $dry): array
{
    global $db;

    $q     = $db->sql_query_prepared($select, $params);
    $rows  = 0;
    $bytes = 0;
    $batch = [];

    while ($r = $db->fetch_array($q)) {
        foreach ($paths($r) as $p) {
            if ($p !== '' && is_file($p)) {
                $bytes += (int)filesize($p);
                if (!$dry) @unlink($p);
            }
        }
        $rows++;
        if (!$dry) {
            $batch[] = (int)$r[$idCol];
            if (count($batch) >= TT_BATCH) {
                tt_delete_ids($table, $idCol, $batch);
                $batch = [];
            }
        }
    }
    if (!$dry && $batch) tt_delete_ids($table, $idCol, $batch);

    return [$rows, $bytes];
}

/**
 * Файлы в папке, на которые ничего не ссылается.
 * $isKept(имя_файла) -> true, если файл нужен.
 */
function tt_scan_dir(string $dir, callable $isKept, bool $dry): array
{
    if (!is_dir($dir) || !($h = opendir($dir))) return [0, 0, []];

    $count = 0;
    $bytes = 0;
    $names = [];
    $fresh = TIMENOW - TT_MIN_FILE_AGE;

    while (($file = readdir($h)) !== false) {
        if ($file === '.' || $file === '..') continue;
        if (in_array(strtolower($file), TT_PROTECTED_FILES, true)) continue;

        $path = $dir . '/' . $file;
        if (!is_file($path)) continue;
        if ((int)filemtime($path) > $fresh) continue;
        if ($isKept($file)) continue;

        $bytes += (int)filesize($path);
        if (!$dry) @unlink($path);
        $count++;
        if (count($names) < TT_NAMES_SHOWN) $names[] = $file;
    }
    closedir($h);

    return [$count, $bytes, $names];
}

/** "a.jpg, b.jpg (+3 more)" для колонки Status. */
function tt_names_note(array $names, int $total): array|string
{
    if (!$names) return '';
    $list = implode(', ', $names);
    $more = $total - count($names);
    return $more > 0 ? tt_msg('note_names_more', $list, $more) : $list;
}

/** Скан папки как шаг отчёта: отсутствующая папка - Skipped, а не "0, OK". */
function tt_scan_step(array $label, string $dir, callable $isKept, bool $dry): array
{
    if (!is_dir($dir)) {
        return tt_step('disk_scan', $label, 0, 0, 'skip', tt_msg('note_folder_not_found', $dir));
    }
    [$n, $b, $names] = tt_scan_dir($dir, $isKept, $dry);
    return tt_step('disk_scan', $label, $n, $b, 'ok', tt_names_note($names, $n));
}

/** FROM ... WHERE ... для правила сирот, либо null + причина пропуска. */
function tt_orphan_sql(array $rule, array &$skip): ?string
{
    [$table, $refs] = $rule;
    $mode = $rule[2] ?? 'any';

    if (!tt_has($table)) { $skip = tt_msg('note_table_not_found'); return null; }

    $joins = [];
    $conds = [];
    foreach ($refs as $i => $ref) {
        [$col, $rt, $rc] = $ref;
        $zeroOk = $ref[3] ?? false;

        if (!tt_has($table, $col)) { $skip = tt_msg('note_column_not_found', $col); return null; }
        if (!tt_has($rt, $rc))     { $skip = tt_msg('note_ref_not_found', $rt, $rc); return null; }

        $a       = "r{$i}";
        $joins[] = "LEFT JOIN `{$rt}` {$a} ON {$a}.`{$rc}` = x.`{$col}`";
        $c       = "({$a}.`{$rc}` IS NULL AND x.`{$col}` IS NOT NULL";
        if ($zeroOk) $c .= " AND x.`{$col}` <> 0";
        $conds[] = $c . ')';
    }

    return "FROM `{$table}` x " . implode(' ', $joins)
         . ' WHERE ' . implode($mode === 'all' ? ' AND ' : ' OR ', $conds);
}

function tt_load_report(string $file): ?array
{
    if (!is_file($file)) return null;
    $raw = (string)file_get_contents($file);
    $pos = strpos($raw, "\n");
    if ($pos === false) return null;
    $data = json_decode(substr($raw, $pos + 1), true);
    return is_array($data) ? $data : null;
}

function tt_save_report(string $file, array $data): void
{
    // PHP-заглушка первой строкой: файл в cache/ нельзя прочитать через веб.
    @file_put_contents($file, "<?php exit; ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ═════════════════════════════════════════════════════════
//  Операции
// ═════════════════════════════════════════════════════════

function tt_run_db_orphans(array $rules, bool $dry): array
{
    global $db;
    $out = [];

    foreach ($rules as $rule) {
        $table = $rule[0];
        $label = tt_msg('lbl_orphans_in', $table);
        $skip  = [];
        $fw    = tt_orphan_sql($rule, $skip);

        if ($fw === null) {
            $out[] = tt_step('db_orphans', $label, 0, 0, 'skip', $skip);
            continue;
        }
        if ($dry) {
            $n = tt_count("SELECT COUNT(*) AS c {$fw}");
        } else {
            $db->sql_query_prepared("DELETE x {$fw}");
            $n = (int)$db->affected_rows();
        }
        $out[] = tt_step('db_orphans', $label, $n);
    }
    return $out;
}

function tt_run_file_records(array $cfg, bool $dry): array
{
    $out = [];

    // ── comment_files: висячие ссылки + ни к чему не привязанные ──
    if (tt_has('comment_files', 'file_path')) {
        $joins  = [];
        $conds  = [];
        $params = [];

        if (tt_has('comment_files', 'user_id')) {
            $joins[] = 'LEFT JOIN users u ON u.id = cf.user_id';
            $conds[] = '(cf.user_id IS NULL OR u.id IS NULL)';
        }

        $refs = [
            'torrent_id'  => ['torrents', 'id'],
            'comment_id'  => ['comments', 'id'],
            'post_id'     => ['posts', 'pid'],
            'messages_id' => ['privatemessages', 'pmid'],
            'news_id'     => ['news', 'id'],
        ];
        $present = [];
        $i = 0;
        foreach ($refs as $col => [$rt, $rc]) {
            if (!tt_has('comment_files', $col)) continue;
            $present[] = $col;
            if (!tt_has($rt, $rc)) continue;
            $a       = 'j' . $i++;
            $joins[] = "LEFT JOIN `{$rt}` {$a} ON {$a}.`{$rc}` = cf.`{$col}`";
            $conds[] = "(cf.`{$col}` IS NOT NULL AND cf.`{$col}` <> 0 AND {$a}.`{$rc}` IS NULL)";
        }

        // Ни к чему не привязан. Если есть колонка времени - только старше 48 ч,
        // чтобы не удалить файл, который прямо сейчас прикрепляют к сообщению.
        if ($present) {
            $unattached = implode(' AND ', array_map(fn(string $c) => "cf.`{$c}` IS NULL", $present));
            foreach (['created_at', 'uploaded_at', 'dateline', 'added'] as $tc) {
                if (tt_has('comment_files', $tc)) {
                    // uploaded_at у тебя DATETIME: без FROM_UNIXTIME() сравнение
                    // с числом 17xxxxxxxx никогда не истинно и файлы не чистятся.
                    $unattached .= tt_is_datetime('comment_files', $tc)
                        ? " AND cf.`{$tc}` < FROM_UNIXTIME(?)"
                        : " AND cf.`{$tc}` < ?";
                    $params[]    = TIMENOW - $cfg['draft_max_age'];
                    break;
                }
            }
            $conds[] = "({$unattached})";
        }

        if ($conds) {
            [$n, $b] = tt_purge_with_files(
                'SELECT cf.id, cf.file_path FROM comment_files cf ' . implode(' ', $joins)
                . ' WHERE ' . implode(' OR ', $conds),
                $params, 'comment_files', 'id',
                fn(array $r) => [(string)$r['file_path']],
                $dry
            );
            $out[] = tt_step('file_records', tt_msg('lbl_comment_files'), $n, $b);
        }
    } else {
        $out[] = tt_step('file_records', tt_msg('lbl_comment_files'), 0, 0, 'skip', tt_msg('note_table_not_found'));
    }

    // ── attachments ──
    if (tt_has('attachments', 'comment_id')) {
        $attachPaths = function (array $r): array {
            $p = [];
            if (!empty($r['attachname'])) $p[] = tt_attach_path((string)$r['attachname']);
            if (!empty($r['thumbnail']) && $r['thumbnail'] !== 'SMALL') $p[] = tt_attach_path((string)$r['thumbnail']);
            return $p;
        };

        // Черновики: pid = 0 AND comment_id = 0, старше 48 ч
        [$n, $b] = tt_purge_with_files(
            'SELECT aid, attachname, thumbnail FROM attachments
             WHERE pid = 0 AND comment_id = 0 AND dateuploaded < ?',
            [TIMENOW - $cfg['draft_max_age']], 'attachments', 'aid', $attachPaths, $dry
        );
        $out[] = tt_step('file_records', tt_msg('lbl_draft_attach'), $n, $b);

        // Привязаны к удалённому посту или комментарию
        [$n, $b] = tt_purge_with_files(
            'SELECT a.aid, a.attachname, a.thumbnail FROM attachments a
             LEFT JOIN posts p    ON p.pid = a.pid
             LEFT JOIN comments c ON c.id  = a.comment_id
             WHERE (a.pid > 0 AND p.pid IS NULL) OR (a.comment_id > 0 AND c.id IS NULL)',
            [], 'attachments', 'aid', $attachPaths, $dry
        );
        $out[] = tt_step('file_records', tt_msg('lbl_attach_deleted'), $n, $b);
    } else {
        $out[] = tt_step('file_records', tt_msg('lbl_attach_orphaned'), 0, 0, 'skip', tt_msg('note_ref_not_found', 'attachments', 'comment_id'));
    }

    // ── screenshots ──
    if (tt_has('screenshots', 'torrent_id')) {
        $dir = $cfg['screens_dir'];
        [$n, $b] = tt_purge_with_files(
            'SELECT s.id, s.filename FROM screenshots s
             LEFT JOIN torrents t ON t.id = s.torrent_id
             WHERE s.torrent_id IS NULL OR t.id IS NULL',
            [], 'screenshots', 'id',
            fn(array $r) => empty($r['filename']) ? [] : [$dir . '/' . basename((string)$r['filename'])],
            $dry
        );
        $out[] = tt_step('file_records', tt_msg('lbl_screens_deleted'), $n, $b);
    }

    return $out;
}

function tt_run_disk_scan(array $cfg, bool $dry): array
{
    global $db;
    $out = [];

    // uploads/ (корень) <- comment_files.file_path
    if (tt_has('comment_files', 'file_path')) {
        $keep = [];
        $q = $db->sql_query_prepared('SELECT file_path FROM comment_files');
        while ($r = $db->fetch_array($q)) {
            if (!empty($r['file_path'])) $keep[basename((string)$r['file_path'])] = true;
        }
        $out[] = tt_scan_step(tt_msg('lbl_scan_uploads'), TSDIR . '/uploads', fn(string $f) => isset($keep[$f]), $dry);
    }

    // uploads/attachments/ и uploads/YYYYMM/ <- attachments.attachname / thumbnail
    if (tt_has('attachments')) {
        $keep = [];
        $q = $db->sql_query_prepared('SELECT attachname, thumbnail FROM attachments');
        while ($r = $db->fetch_array($q)) {
            if (!empty($r['attachname'])) $keep[(string)$r['attachname']] = true;
            if (!empty($r['thumbnail']) && $r['thumbnail'] !== 'SMALL') $keep[(string)$r['thumbnail']] = true;
        }

        [$n, $b, $names] = tt_scan_dir(TSDIR . '/uploads/attachments', fn(string $f) => isset($keep[$f]), $dry);
        foreach ((glob(TSDIR . '/uploads/[0-9][0-9][0-9][0-9][0-9][0-9]', GLOB_ONLYDIR) ?: []) as $monthDir) {
            $prefix = basename($monthDir) . '/';
            [$mn, $mb, $mnames] = tt_scan_dir($monthDir, fn(string $f) => isset($keep[$prefix . $f]), $dry);
            $n += $mn;
            $b += $mb;
            foreach ($mnames as $mf) $names[] = $prefix . $mf;
        }
        $out[] = tt_step('disk_scan', tt_msg('lbl_scan_attach'), $n, $b, 'ok',
            tt_names_note(array_slice($names, 0, TT_NAMES_SHOWN), $n));
    }

    // uploads/avatars/ <- users.avatar
    // MyBB хранит "./uploads/avatars/avatar_5.jpg?dateline=..." - query string
    // обязательно отрезать, иначе ни одно имя не совпадёт с файлом на диске.
    if (tt_has('users', 'avatar')) {
        $keep = [];
        $q = $db->sql_query_prepared("SELECT avatar FROM users WHERE avatar <> ''");
        while ($r = $db->fetch_array($q)) {
            $av = (string)$r['avatar'];
            if (preg_match('~^https?://~i', $av)) continue;
            $keep[basename((string)strtok($av, '?'))] = true;
        }
        $out[] = tt_scan_step(tt_msg('lbl_scan_avatars'), TSDIR . '/uploads/avatars', fn(string $f) => isset($keep[$f]), $dry);
    }

    // torrents/screens/ <- screenshots.filename
    if (tt_has('screenshots', 'filename')) {
        $keep = [];
        $q = $db->sql_query_prepared('SELECT filename FROM screenshots');
        while ($r = $db->fetch_array($q)) {
            if (!empty($r['filename'])) $keep[basename((string)$r['filename'])] = true;
        }
        $out[] = tt_scan_step(tt_msg('lbl_scan_screens'), $cfg['screens_dir'], fn(string $f) => isset($keep[$f]), $dry);
    }

    // torrents/images/ <- torrents.t_image / t_image2
    // Значение может быть голым именем, локальным путём или полным URL -
    // в любом случае берём basename без query string. Лишнее попадание
    // в $keep только сохраняет файл, поэтому так безопасно.
    // Сравнение без учёта регистра: NTFS регистр не различает, а в БД
    // может быть "Poster.JPG" при файле "poster.jpg".
    $imgCols = array_values(array_filter(['t_image', 't_image2'], fn(string $c) => tt_has('torrents', $c)));
    if ($imgCols) {
        $keep = [];
        $q = $db->sql_query_prepared(
            'SELECT ' . implode(', ', array_map(fn(string $c) => "`{$c}`", $imgCols)) . ' FROM torrents'
        );
        while ($r = $db->fetch_array($q)) {
            foreach ($imgCols as $c) {
                $v = trim((string)($r[$c] ?? ''));
                if ($v !== '') $keep[strtolower(basename((string)strtok($v, '?')))] = true;
            }
        }
        $out[] = tt_scan_step(
            tt_msg('lbl_scan_posters'),
            $cfg['images_dir'],
            fn(string $f) => isset($keep[strtolower($f)]),
            $dry
        );
    }

    // torrents/{id}.torrent <- torrents.id
    // Трогаем ТОЛЬКО файлы строго вида "123.torrent", всё остальное в папке остаётся.
    $keep = [];
    $q = $db->sql_query_prepared('SELECT id FROM torrents');
    while ($r = $db->fetch_array($q)) $keep[(int)$r['id']] = true;

    $out[] = tt_scan_step(
        tt_msg('lbl_scan_torrents'),
        $cfg['torrent_dir'],
        fn(string $f) => !preg_match('~^(\d+)\.torrent$~i', $f, $m) || isset($keep[(int)$m[1]]),
        $dry
    );

    return $out;
}

function tt_run_time_cleanup(array $rules, bool $dry): array
{
    global $db;
    $out = [];

    foreach ($rules as [$table, $col, $days]) {
        $label = match (true) {
            $days === 0 => tt_msg('lbl_time_expired', $table),
            $days === 1 => tt_msg('lbl_time_older_1', $table, $days),
            default     => tt_msg('lbl_time_older_n', $table, $days),
        };
        if (!tt_has($table, $col)) {
            $out[] = tt_step('time_cleanup', $label, 0, 0, 'skip', tt_msg('note_ref_not_found', $table, $col));
            continue;
        }
        $cutoff = TIMENOW - $days * TT_DAY;
        if ($dry) {
            $n = tt_count("SELECT COUNT(*) AS c FROM `{$table}` WHERE `{$col}` < ?", [$cutoff]);
        } else {
            $db->sql_query_prepared("DELETE FROM `{$table}` WHERE `{$col}` < ?", [$cutoff]);
            $n = (int)$db->affected_rows();
        }
        $out[] = tt_step('time_cleanup', $label, $n);
    }
    return $out;
}

function tt_run_backups(array $cfg, bool $dry): array
{
    $dir    = $cfg['backup_dir'];
    $cutoff = TIMENOW - $cfg['backup_keep_days'] * TT_DAY;
    $label  = tt_msg('lbl_backups_older', $cfg['backup_keep_days']);

    if (!is_dir($dir)) return [tt_step('backups', $label, 0, 0, 'skip', tt_msg('note_backup_folder'))];

    $n = 0;
    $b = 0;
    foreach (array_merge(glob($dir . '/*.sql') ?: [], glob($dir . '/*.gz') ?: []) as $file) {
        if (is_file($file) && (int)filemtime($file) < $cutoff) {
            $b += (int)filesize($file);
            if (!$dry) @unlink($file);
            $n++;
        }
    }
    return [tt_step('backups', $label, $n, $b)];
}

function tt_run_recount(bool $dry): array
{
    global $db;

    if (!tt_has('torrents', 'comments') || !tt_has('comments', 'torrent')) {
        return [tt_step('recount', tt_msg('lbl_recount'), 0, 0, 'skip', tt_msg('note_column_missing'))];
    }

    $sub = 'LEFT JOIN (SELECT torrent, COUNT(*) AS cnt FROM comments GROUP BY torrent) x ON x.torrent = t.id';
    if ($dry) {
        $n = tt_count("SELECT COUNT(*) AS c FROM torrents t {$sub} WHERE t.comments <> COALESCE(x.cnt, 0)");
    } else {
        $db->sql_query_prepared("UPDATE torrents t {$sub} SET t.comments = COALESCE(x.cnt, 0) WHERE t.comments <> COALESCE(x.cnt, 0)");
        $n = (int)$db->affected_rows();
    }
    return [tt_step('recount', tt_msg('lbl_recount_fixed'), $n)];
}

function tt_run_optimize(array $tables, array $cfg, bool $dry): array
{
    global $db;
    $out    = [];
    $tables = array_values(array_unique(array_filter($tables, fn(string $t) => tt_has($t))));
    if (!$tables) return $out;

    // В MySQL 8+ information_schema кэширует статистику на сутки - просим свежую.
    $db->sql_query_prepared('SET SESSION information_schema_stats_expiry = 0');

    $ph = implode(',', array_fill(0, count($tables), '?'));
    $q  = $db->sql_query_prepared(
        "SELECT TABLE_NAME AS t, DATA_FREE AS f FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$ph})",
        $tables
    );

    $free = [];
    while ($r = $db->fetch_array($q)) $free[(string)$r['t']] = (int)$r['f'];

    foreach ($tables as $t) {
        $f = $free[$t] ?? 0;
        if ($f < $cfg['optimize_min_free']) continue;
        if (!$dry) $db->sql_query_prepared("OPTIMIZE TABLE `{$t}`");
        $out[] = tt_step('optimize', tt_msg('lbl_optimize_table', $t), 0, $f, 'ok', tt_msg('note_reclaimable'));
    }
    if (!$out) {
        $out[] = tt_step('optimize', tt_msg('lbl_optimize_none', tt_size($cfg['optimize_min_free'])), 0, 0, 'skip', tt_msg('note_nothing_to_optimize'));
    }
    return $out;
}

// ═════════════════════════════════════════════════════════
//  POST: выполнение -> отчёт в файл -> редирект (PRG)
// ═════════════════════════════════════════════════════════
global $mybb, $db, $BASEURL, $lang;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tt_action'] ?? '') === 'run') {
    if (!verify_post_check((string)$mybb->get_input('my_post_key'), true)) {
        http_response_code(403);
        stderr(tt_t('err_title'), tt_t('err_token'));
    }

    // mode_js ставит JS после подтверждения; без JS приходит значение кнопки.
    // Всё, что не 'run', считается пробным прогоном.
    $modeRaw = (string)($_POST['mode_js'] ?? '') ?: (string)($_POST['mode'] ?? '');
    $dry     = $modeRaw !== 'run';

    $selected = array_values(array_intersect(
        array_keys($TT_GROUPS),
        array_map('strval', (array)($_POST['groups'] ?? []))
    ));
    if (!$selected) {
        stderr(tt_t('err_title'), tt_t('err_no_groups'));
    }

    $lock = $db->fetch_array($db->sql_query_prepared('SELECT GET_LOCK(?, 0) AS l', [TT_LOCK_NAME]));
    if ((int)($lock['l'] ?? 0) !== 1) {
        stderr(tt_t('err_title'), tt_t('err_locked'));
    }

    set_time_limit(0);
    ignore_user_abort(true);

    $start = microtime(true);
    $steps = [];

    try {
        foreach ($selected as $g) {
            try {
                $steps = array_merge($steps, match ($g) {
                    'db_orphans'   => tt_run_db_orphans($TT_ORPHAN_RULES, $dry),
                    'file_records' => tt_run_file_records($TT_CFG, $dry),
                    'disk_scan'    => tt_run_disk_scan($TT_CFG, $dry),
                    'time_cleanup' => tt_run_time_cleanup($TT_TIME_RULES, $dry),
                    'backups'      => tt_run_backups($TT_CFG, $dry),
                    'recount'      => tt_run_recount($dry),
                    'optimize'     => tt_run_optimize(
                        array_merge(
                            array_column($TT_ORPHAN_RULES, 0),
                            array_column($TT_TIME_RULES, 0),
                            ['comment_files', 'attachments', 'screenshots']
                        ),
                        $TT_CFG,
                        $dry
                    ),
                });
            } catch (Throwable $e) {
                $steps[] = tt_step($g, tt_msg('grp_' . $g . '_title'), 0, 0, 'error', $e->getMessage());
            }
        }
    } finally {
        $db->sql_query_prepared('SELECT RELEASE_LOCK(?)', [TT_LOCK_NAME]);
    }

    $rows  = array_sum(array_column($steps, 'rows'));
    // Место от OPTIMIZE - оценка DATA_FREE, в "освобождено на диске" не суммируем.
    $bytes = array_sum(array_map(fn(array $s) => $s['group'] === 'optimize' ? 0 : $s['bytes'], $steps));
    $opt   = count(array_filter($steps, fn(array $s) => $s['group'] === 'optimize' && $s['status'] === 'ok'));

    $report = [
        'mode'      => $dry ? 'dry' : 'run',
        'at'        => TIMENOW,
        'by'        => (string)($mybb->user['username'] ?? ''),
        'duration'  => round(microtime(true) - $start, 2),
        'rows'      => (int)$rows,
        'bytes'     => (int)$bytes,
        'optimized' => $opt,
        'groups'    => $selected,
        'steps'     => $steps,
    ];
    tt_save_report($TT_CFG['report_file'], $report);

    if (!$dry) {
        write_log(sprintf(
            'Tweak Tracker run by %s: %d records, %s on disk, %d tables optimized, %.2fs (%s)',
            $report['by'], $rows, tt_size((int)$bytes, false), $opt, $report['duration'], implode(', ', $selected)
        ));
    }

    header('Location: ' . $_this_script_);
    exit;
}

// ═════════════════════════════════════════════════════════
//  GET: страница
// ═════════════════════════════════════════════════════════
$report  = tt_load_report($TT_CFG['report_file']);
$checked = $report['groups'] ?? array_keys(array_filter($TT_GROUPS, fn(array $g) => $g['default']));
$isDry   = ($report['mode'] ?? '') === 'dry';

stdhead();

$v = TT_ASSET_VER;

// js_* -> массив без префикса для AGS_LANG
$ttJsLang = [];
foreach ((array)$lang->tweak_tracker as $k => $val) {
    if (str_starts_with((string)$k, 'js_')) $ttJsLang[substr((string)$k, 3)] = (string)$val;
}
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/tweak_tracker.css?ver=<?= $v ?>">

<div class="tt-page container mt-3 mb-5">

    <div class="tt-head">
        <div class="tt-head-icon"><i class="fa-solid fa-broom"></i></div>
        <div class="tt-head-text">
            <h1><?= tt_e(tt_t('page_title')) ?></h1>
            <p><?= tt_e(tt_t('page_subtitle')) ?></p>
        </div>
        <span class="tt-version">v<?= TT_VERSION ?></span>
    </div>

    <div class="tt-kpis">
        <div class="tt-kpi">
            <div class="tt-kpi-icon tt-c-primary"><i class="fa-solid fa-database"></i></div>
            <div><div class="tt-kpi-val"><?= $report ? number_format($report['rows']) : '—' ?></div>
                 <div class="tt-kpi-lbl"><?= tt_e(tt_t($isDry ? 'kpi_rows_dry' : 'kpi_rows_run')) ?></div></div>
        </div>
        <div class="tt-kpi">
            <div class="tt-kpi-icon tt-c-success"><i class="fa-solid fa-hard-drive"></i></div>
            <div><div class="tt-kpi-val"><?= $report ? tt_e(tt_size((int)$report['bytes'])) : '—' ?></div>
                 <div class="tt-kpi-lbl"><?= tt_e(tt_t($isDry ? 'kpi_bytes_dry' : 'kpi_bytes_run')) ?></div></div>
        </div>
        <div class="tt-kpi">
            <div class="tt-kpi-icon tt-c-warning"><i class="fa-solid fa-stopwatch"></i></div>
            <div><div class="tt-kpi-val"><?= $report ? tt_e(tt_t('unit_sec', $report['duration'])) : '—' ?></div>
                 <div class="tt-kpi-lbl"><?= tt_e(tt_t('kpi_duration')) ?></div></div>
        </div>
        <div class="tt-kpi">
            <div class="tt-kpi-icon tt-c-info"><i class="fa-solid fa-gauge-high"></i></div>
            <div><div class="tt-kpi-val"><?= $report ? (int)$report['optimized'] : '—' ?></div>
                 <div class="tt-kpi-lbl"><?= tt_e(tt_t($isDry ? 'kpi_opt_dry' : 'kpi_opt_run')) ?></div></div>
        </div>
    </div>

    <?php if ($report): ?>
    <div class="tt-card tt-report<?= $isDry ? ' is-dry' : '' ?>">
        <div class="tt-report-head">
            <div>
                <?php if ($isDry): ?>
                    <span class="tt-badge tt-badge-dry"><i class="fa-solid fa-eye me-1"></i><?= tt_e(tt_t('badge_dry')) ?></span>
                <?php else: ?>
                    <span class="tt-badge tt-badge-run"><i class="fa-solid fa-check me-1"></i><?= tt_e(tt_t('badge_run')) ?></span>
                <?php endif; ?>
                <span class="tt-meta">
                    <?= tt_e(tt_t('meta_run_at', date('Y-m-d H:i', (int)$report['at']), (string)$report['by'])) ?>
                </span>
            </div>
            <label class="tt-switch">
                <input type="checkbox" id="ttShowAll"> <?= tt_e(tt_t('lbl_show_empty')) ?>
            </label>
        </div>
        <?php if ($isDry): ?>
            <p class="tt-note"><?= tt_e(tt_t('hint_dry_counts')) ?></p>
        <?php endif; ?>
        <div class="tt-table-wrap">
            <table class="tt-table">
                <thead><tr><th><?= tt_e(tt_t('th_action')) ?></th><th class="text-end"><?= tt_e(tt_t('th_records')) ?></th><th class="text-end"><?= tt_e(tt_t('th_size')) ?></th><th><?= tt_e(tt_t('th_status')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($report['steps'] as $s):
                    $empty = $s['status'] === 'ok' && $s['rows'] === 0 && $s['bytes'] === 0;
                    $cls   = $s['status'] !== 'ok' ? ' tt-row-' . $s['status'] : ($empty ? ' tt-row-empty' : '');
                    $note  = tt_text($s['note']);
                ?>
                    <tr class="tt-row<?= $cls ?>">
                        <td>
                            <i class="fa-solid <?= tt_e($TT_GROUPS[$s['group']]['icon'] ?? 'fa-circle') ?> tt-row-icon"></i>
                            <?= tt_e(tt_text($s['label'])) ?>
                        </td>
                        <td class="text-end"><?= $s['rows'] ? number_format($s['rows']) : '—' ?></td>
                        <td class="text-end"><?= $s['bytes'] ? tt_e(tt_size((int)$s['bytes'])) : '—' ?></td>
                        <td>
                            <?php if ($s['status'] === 'skip'): ?>
                                <span class="tt-status tt-status-skip"><?= tt_e(tt_t('status_skip')) ?></span>
                            <?php elseif ($s['status'] === 'error'): ?>
                                <span class="tt-status tt-status-error"><?= tt_e(tt_t('status_error')) ?></span>
                            <?php else: ?>
                                <span class="tt-status tt-status-ok"><?= tt_e(tt_t('status_ok')) ?></span>
                            <?php endif; ?>
                            <?php if ($note !== ''): ?><span class="tt-status-note"><?= tt_e($note) ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= tt_e($_this_script_) ?>" id="ttForm">
        <input type="hidden" name="my_post_key" value="<?= tt_e((string)$mybb->post_code) ?>">
        <input type="hidden" name="tt_action" value="run">
        <input type="hidden" name="mode_js" value="">

        <div class="tt-card">
            <div class="tt-groups-head">
                <h2><?= tt_e(tt_t('sec_operations')) ?></h2>
                <button type="button" class="tt-btn tt-btn-ghost" id="ttToggleAll">
                    <i class="fa-solid fa-list-check me-1"></i><span><?= tt_e(tt_t('btn_select_all')) ?></span>
                </button>
            </div>
            <div class="tt-groups">
                <?php foreach ($TT_GROUPS as $key => $g): ?>
                    <label class="tt-group<?= $key === 'optimize' ? ' tt-group-heavy' : '' ?>">
                        <input type="checkbox" name="groups[]" value="<?= tt_e($key) ?>"
                               data-title="<?= tt_e($g['title']) ?>"
                               <?= in_array($key, $checked, true) ? 'checked' : '' ?>>
                        <span class="tt-group-icon"><i class="fa-solid <?= tt_e($g['icon']) ?>"></i></span>
                        <span class="tt-group-body">
                            <span class="tt-group-title"><?= tt_e($g['title']) ?></span>
                            <span class="tt-group-desc"><?= tt_e($g['desc']) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tt-actionbar">
            <span class="tt-actionbar-hint"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= tt_e(tt_t('hint_backup')) ?></span>
            <div class="tt-actionbar-btns">
                <button type="submit" name="mode" value="dry" class="tt-btn tt-btn-outline" id="ttDry">
                    <i class="fa-solid fa-eye me-1"></i><?= tt_e(tt_t('btn_dry')) ?>
                </button>
                <button type="submit" name="mode" value="run" class="tt-btn tt-btn-danger" id="ttRun">
                    <i class="fa-solid fa-broom me-1"></i><?= tt_e(tt_t('btn_run')) ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script>const AGS_LANG = <?= json_encode($ttJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/admin/scripts/tweak_tracker.js?ver=<?= $v ?>"></script>
<?php
stdfoot();