<?php

declare(strict_types=1);

// ============================================================
//  Helpers
// ============================================================

function native_unserialize(string $str): mixed
{
    return unserialize($str, ['allowed_classes' => false]);
}

// ============================================================
//  Cache-store enum  (PHP 8.1+)
// ============================================================

enum CacheStore: string
{
    case Files        = 'files';
    case Memcache     = 'memcache';
    case Memcached    = 'memcached';
    case EAccelerator = 'eaccelerator';
    case XCache       = 'xcache';
    case APC          = 'apc';
    case APCu         = 'apcu';
    case Redis        = 'redis';
    case Database     = 'database'; // fallback

    /** Returns the handler class-file path relative to TSDIR. */
    public function handlerFile(): ?string
    {
        return match($this) {
            self::Files        => '/include/cachehandlers/disk.php',
            self::Memcache     => '/include/cachehandlers/memcache.php',
            self::Memcached    => '/include/cachehandlers/memcached.php',
            self::EAccelerator => '/include/cachehandlers/eaccelerator.php',
            self::XCache       => '/include/cachehandlers/xcache.php',
            self::APC          => '/include/cachehandlers/apc.php',
            self::APCu         => '/include/cachehandlers/apcu.php',
            self::Redis        => '/include/cachehandlers/redis.php',
            self::Database     => null,
        };
    }

    /** Returns the handler class name, or null for DB-only mode. */
    public function handlerClass(): ?string
    {
        return match($this) {
            self::Files        => 'diskCacheHandler',
            self::Memcache     => 'memcacheCacheHandler',
            self::Memcached    => 'memcachedCacheHandler',
            self::EAccelerator => 'eacceleratorCacheHandler',
            self::XCache       => 'xcacheCacheHandler',
            self::APC          => 'apcCacheHandler',
            self::APCu         => 'apcuCacheHandler',
            self::Redis        => 'redisCacheHandler',
            self::Database     => null,
        };
    }
}

// ============================================================
//  datacache
// ============================================================

class datacache
{
    /** In-memory cache store. */
    public array $cache = [];

    /** Active cache handler (null = database-only). */
    public ?CacheHandlerInterface $handler = null;

    /** Total number of handler calls performed. */
    public int $call_count = 0;

    /** Log of every handler call (for debug mode). */
    public array $calllist = [];

    /** Cumulative time spent in handler calls (seconds). */
    public float $call_time = 0.0;

    /** Last rendered debug block. */
    public string $cache_debug = '';

    // Internal state for build helpers
    private array $moderators                    = [];
    private array $built_moderators              = [0 => []];
    private array $moderators_forum_cache        = [];
    private array $forum_permissions             = [0 => []];
    private array $built_forum_permissions       = [0 => []];
    private array $forum_permissions_forum_cache = [];

    // ── Constructor ──────────────────────────────────────────

    public function __construct()
    {
        global $db, $config;

        require_once TSDIR . '/include/cachehandlers/interface.php';

        $store = CacheStore::tryFrom((string)($config['cache_store'] ?? ''))
            ?? CacheStore::Database;

        if ($store !== CacheStore::Database) {
            $file  = $store->handlerFile();
            $class = $store->handlerClass();

            require_once TSDIR . $file;
            /** @var CacheHandlerInterface $handler */
            $handler = new $class();

            if ($handler instanceof CacheHandlerInterface && $handler->connect()) {
                $this->handler = $handler;
            }
            // Connection failed -> fall through to DB-only mode
        }

        // DB-only mode: pre-load the whole datacache table once
        if ($this->handler === null) {
            $query = $db->simple_select('datacache', 'title,cache');
            while ($row = $db->fetch_array($query)) {
                $this->cache[$row['title']] = native_unserialize($row['cache']);
            }
        }
    }

    /**
     * @deprecated Initialisation now happens in __construct().
     *             Kept for backward compat with MyBB calling `$cache->cache()`.
     */
    public function cache(): void {}

    // ── Core API ─────────────────────────────────────────────

    /**
     * Read a cache entry.
     *
     * @param bool $hard When true, always fetches from the handler/DB even if
     *                   an in-memory copy exists.
     */
    public function read(string $name, bool|int $hard = false): mixed
    {
        global $db, $mybb;

        if (isset($this->cache[$name]) && !$hard) {
            return $this->cache[$name];
        }

        if (!$hard && !($this->handler instanceof CacheHandlerInterface)) {
            return false;
        }

        $data = false;

        if ($this->handler instanceof CacheHandlerInterface) {
            $data = $this->handler->fetch($name);

            if ($data === false) {
                // Rebuild from DB, then push back to handler
                $data = $this->fetchFromDb($name);

                if ($data !== false) {
                    $this->timedHandlerCall('set', $name, fn() => $this->handler->put($name, $data));
                }
            }
        } else {
            $data = $this->fetchFromDb($name);
        }

        $this->cache[$name] = $data;

        return $data;
    }

    /** Update (or create) a cache entry. */
    public function update(string $name, mixed $contents): void
    {
        global $db, $mybb;

        $this->cache[$name] = $contents;

        // Always persist to DB as the authoritative store
        $db->replace_query('datacache', [
            'title' => $db->escape_string($name),
            'cache' => $db->escape_string(my_serialize($contents)),
        ], '', false);

        if ($this->handler instanceof CacheHandlerInterface) {
            $this->timedHandlerCall('update', $name, fn() => $this->handler->put($name, $contents));
        }
    }

    /**
     * Delete a cache entry.
     *
     * @param bool $greedy When true, also deletes every key starting with "{$name}_".
     */
    public function delete(string $name, bool $greedy = false): void
    {
        global $db, $mybb, $cache;

        $dbname = $db->escape_string($name);
        $where  = "title = '{$dbname}'";

        if ($this->handler instanceof CacheHandlerInterface) {
            $this->timedHandlerCall('delete', $name, fn() => $this->handler->delete($name));
        }

        if ($greedy) {
            $prefix = $name . '_';
            $names  = [];

            // Collect in-memory keys
            foreach (array_keys($cache->cache ?? []) as $key) {
                if (str_starts_with($key, $prefix)) {
                    $names[$key] = 0;
                }
            }

            $ldbname = strtr($dbname, ['%' => '=%', '=' => '==', '_' => '=_']);
            $where  .= " OR title LIKE '{$ldbname}=_%' ESCAPE '='";

            if ($this->handler instanceof CacheHandlerInterface) {
                $query = $db->simple_select('datacache', 'title', $where);
                while ($row = $db->fetch_array($query)) {
                    $names[$row['title']] = 0;
                }

                // Collect disk-cache files
                $start = strlen(TSDIR . '/cache/');
                foreach ((array)@glob(TSDIR . "/cache/{$prefix}*.php") as $filename) {
                    if ($filename) {
                        $key = substr($filename, $start, strlen($filename) - 4 - $start);
                        $names[$key] = 0;
                    }
                }

                foreach (array_keys($names) as $key) {
                    $this->timedHandlerCall('delete', $key, fn() => $this->handler->delete($key));
                }
            }
        }

        $db->delete_query('datacache', $where);
    }

    /** Return the byte-size of a named entry (or the whole table). */
    public function size_of(string $name = ''): int
    {
        global $db;

        if ($this->handler instanceof CacheHandlerInterface) {
            $size = $this->handler->size_of($name);
            if ($size) {
                return (int)$size;
            }
        }

        if ($name !== '') {
            $query = $db->simple_select('datacache', 'cache', "title='{$name}'");
            return strlen((string)$db->fetch_field($query, 'cache'));
        }

        return (int)$db->fetch_size('datacache');
    }

    // ── update_* methods ─────────────────────────────────────

    public function update_tasks(): void
    {
        global $db;

        $query     = $db->simple_select('tasks', 'nextrun', 'enabled=1', [
            'order_by'  => 'nextrun',
            'order_dir' => 'asc',
            'limit'     => 1,
        ]);
        $next_task = $db->fetch_array($query);

        $task_cache = $this->read('tasks');
        if (!is_array($task_cache)) {
            $task_cache = [];
        }

        $task_cache['nextrun'] = (int)($next_task['nextrun'] ?? 0) ?: TIMENOW + 3600;

        $this->update('tasks', $task_cache);
    }

    public function update_bannedips(): void
    {
        global $db;

        $banned_ips = [];
        $query = $db->simple_select('banfilters', 'fid,filter', 'type=1');
        while ($row = $db->fetch_array($query)) {
            $banned_ips[$row['fid']] = $row;
        }
        $this->update('bannedips', $banned_ips);
    }

    public function update_bannedemails(): void
    {
        global $db;

        $banned_emails = [];
        $query = $db->simple_select('banfilters', 'fid,filter', "type='3'");
        while ($row = $db->fetch_array($query)) {
            $banned_emails[$row['fid']] = $row;
        }
        $this->update('bannedemails', $banned_emails);
    }

    public function update_attachtypes(): void
    {
        global $db;

        $types = [];
        $query = $db->simple_select('attachtypes', '*', 'enabled=1');
        while ($type = $db->fetch_array($query)) {
            $type['extension'] = my_strtolower($type['extension']);
            $types[$type['extension']] = $type;
        }
        $this->update('attachtypes', $types);
    }

    public function update_smilies(): void
    {
        global $db;

        $smilies = [];
        $query   = $db->sql_query('SELECT stext, spath FROM smilies ORDER BY sorder, stitle');
        while ($smilie = $db->fetch_array($query)) {
            $smilies[$smilie['stext']] = $smilie['spath'];
        }
        $this->update('smilies', $smilies);
    }

    public function update_badwords(): void
    {
        global $db;

        $badwords = [];
        $query    = $db->simple_select('badwords', '*');
        while ($badword = $db->fetch_array($query)) {
            $badwords[$badword['bid']] = $badword;
        }
        $this->update('badwords', $badwords);
    }

    public function update_usergroups(): void
    {
        global $db;

        $groups = [];
        $query  = $db->simple_select('usergroups');
        while ($g = $db->fetch_array($query)) {
            $groups[$g['gid']] = $g;
        }
        $this->update('usergroups', $groups);
    }

    public function update_forumpermissions(): bool
    {
        global $forum_cache, $db;

        $this->forum_permissions              = [0 => []];
        $this->built_forum_permissions        = [0 => []];
        $this->forum_permissions_forum_cache  = [];

        cache_forums(true);
        if (!is_array($forum_cache)) {
            return false;
        }

        foreach ($forum_cache as $forum) {
            $this->forum_permissions_forum_cache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
        }

        $query = $db->simple_select('forumpermissions');
        while ($perm = $db->fetch_array($query)) {
            $this->forum_permissions[$perm['fid']][$perm['gid']] = $perm;
        }

        $this->build_forum_permissions();
        $this->update('forumpermissions', $this->built_forum_permissions);

        return true;
    }

    public function update_moderators(): bool
    {
        global $forum_cache, $db;

        $this->built_moderators       = [0 => []];
        $this->moderators             = [];
        $this->moderators_forum_cache = [];

        cache_forums(true);
        if (!is_array($forum_cache)) {
            return false;
        }

        foreach ($forum_cache as $forum) {
            $this->moderators_forum_cache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
        }

        // Individual moderators
        $query = $db->sql_query("
            SELECT m.*, u.username, u.usergroup, u.displaygroup
            FROM moderators m
            LEFT JOIN users u ON m.id = u.id
            WHERE m.isgroup = '0'
            ORDER BY u.username
        ");
        while ($mod = $db->fetch_array($query)) {
            $this->moderators[$mod['fid']]['users'][$mod['id']] = $mod;
        }

        // Group moderators
        $query = $db->sql_query("
            SELECT m.*, u.title
            FROM moderators m
            LEFT JOIN usergroups u ON m.id = u.gid
            WHERE m.isgroup = '1'
            ORDER BY u.title
        ");
        while ($mod = $db->fetch_array($query)) {
            $this->moderators[$mod['fid']]['usergroups'][$mod['id']] = $mod;
        }

        foreach (array_keys($this->moderators) as $fid) {
            if (isset($this->moderators[$fid]['users'])) {
                uasort(
                    $this->moderators[$fid]['users'],
                    static fn(array $a, array $b): int => strcasecmp($a['username'], $b['username'])
                );
            }
        }

        $this->build_moderators();
        $this->update('moderators', $this->built_moderators);

        return true;
    }

    public function update_awaitingactivation(): void
    {
        global $db;

        $query = $db->simple_select('users', 'COUNT(id) AS awaitingusers', "ustatus='pending'");
        $count = (int)$db->fetch_field($query, 'awaitingusers');

        $this->update('awaitingactivation', [
            'users' => $count,
            'time'  => TIMENOW,
        ]);
    }

    public function update_forums(): void
    {
        global $db;

        $exclude = ['threads', 'posts', 'lastpost', 'lastposter', 'lastposttid', 'lastposteruid', 'lastpostsubject'];
        $forums  = [];

        $query = $db->simple_select('tsf_forums', '*', '', ['order_by' => 'pid,disporder']);
        while ($forum = $db->fetch_array($query)) {
            foreach ($exclude as $key) {
                unset($forum[$key]);
            }
            $forums[$forum['fid']] = $forum;
        }

        $this->update('forums', $forums);
    }

    public function update_most_replied_threads(): void
    {
        $this->update('most_replied_threads', $this->fetchThreadStats('tid,subject,replies,fid,uid', 'replies'));
    }

    public function update_most_viewed_threads(): void
    {
        $this->update('most_viewed_threads', $this->fetchThreadStats('tid,subject,views,fid,uid', 'views'));
    }

    public function update_stats(): void
    {
        require_once INC_PATH . '/functions_rebuild.php';
        rebuild_stats();
    }

    public function update_statistics(): void
    {
        global $db;

        $since = TIMENOW - 86400;

        $query = $db->sql_query("
            SELECT u.id, u.username, COUNT(*) AS poststoday
            FROM tsf_posts p
            LEFT JOIN users u ON p.uid = u.id
            WHERE p.dateline > {$since} AND p.visible = 1
            GROUP BY u.id, u.username
            ORDER BY poststoday DESC
        ");

        $topposter  = [];
        $most_posts = 0;
        while ($user = $db->fetch_array($query)) {
            if ((int)$user['poststoday'] > $most_posts) {
                $most_posts = (int)$user['poststoday'];
                $topposter  = $user;
            }
        }

        $query   = $db->simple_select('users', 'COUNT(id) AS posters', 'postnum>0');
        $posters = (int)$db->fetch_field($query, 'posters');

        $this->update('statistics', [
            'time'         => TIMENOW,
            'top_referrer' => [],
            'top_poster'   => $topposter,
            'posters'      => $posters,
        ]);
    }

    public function update_reportedcontent(): void
    {
        global $db;

        $query  = $db->simple_select('reportedcontent', "COUNT(rid) AS unreadcount", "reportstatus='0'");
        $unread = (int)$db->fetch_field($query, 'unreadcount');

        $query = $db->simple_select('reportedcontent', 'COUNT(rid) AS reportcount');
        $total = (int)$db->fetch_field($query, 'reportcount');

        $query    = $db->simple_select('reportedcontent', 'dateline', "reportstatus='0'", [
            'order_by'  => 'dateline',
            'order_dir' => 'DESC',
            'limit'     => 1,
        ]);
        $dateline = (int)$db->fetch_field($query, 'dateline');

        $this->update('reportedcontent', [
            'unread'       => $unread,
            'total'        => $total,
            'lastdateline' => $dateline,
        ]);
    }

    public function update_mailqueue(int $last_run = 0, int $lock_time = 0): void
    {
        global $db;

        $query      = $db->simple_select('mailqueue', 'COUNT(*) AS queue_size');
        $queue_size = (int)$db->fetch_field($query, 'queue_size');

        $mailqueue = $this->read('mailqueue');
        if (!is_array($mailqueue)) {
            $mailqueue = [];
        }

        $mailqueue['queue_size'] = $queue_size;
        $mailqueue['locked']     = $lock_time;

        if ($last_run > 0) {
            $mailqueue['last_run'] = $last_run;
        }

        $this->update('mailqueue', $mailqueue);
    }

    public function update_news(): void
    {
        global $db;

        $news  = [];
        $query = $db->sql_query('SELECT id,userid,added,body,title FROM news ORDER BY added DESC LIMIT 1');
        while ($row = $db->fetch_array($query)) {
            $news[$row['id']] = $row;
        }
        $this->update('news', $news);
    }

    public function update_torrents(): void
    {
        global $db;

        $exclude    = ['comments', 'hits', 'times_completed'];
        $showimages = 'yes';
        $limit      = 15;

        $image_field = $showimages === 'yes' ? ',t.t_image,'     : ',t.seeders,t.leechers,';
        $image_cond  = $showimages === 'yes' ? " AND t.t_image != ''" : '';

        $sql = "SELECT t.id, t.name{$image_field}t.owner, t.anonymous,
                       t.descr, u.username, u.usergroup, t.added, t.tags
                FROM torrents t
                LEFT JOIN users u ON t.owner = u.id
                LEFT JOIN categories c ON t.category = c.id
                WHERE t.visible = 'yes' AND t.banned = 'no'{$image_cond}
                ORDER BY added DESC
                LIMIT ?, ?";

        $query    = $db->sql_query_prepared($sql, [0, $limit]);
        $torrents = [];

        while ($torrent = $db->fetch_array($query)) {
            foreach ($exclude as $key) {
                unset($torrent[$key]);
            }
            $torrents[$torrent['id']] = $torrent;
        }

        $this->update('torrents', $torrents);
    }

    public function update_birthdays(): void
    {
        global $db;

        $now       = TIMENOW;
        $today     = my_datee('j-n', $now,         '', 0);
        $tomorrow  = my_datee('j-n', $now + 86400, '', 0);
        $yesterday = my_datee('j-n', $now - 86400, '', 0);

        $query = $db->simple_select(
            'users',
            'id,username,usergroup,displaygroup,birthday,birthdayprivacy',
            "birthday LIKE '{$today}-%' OR birthday LIKE '{$yesterday}-%' OR birthday LIKE '{$tomorrow}-%'"
        );

        $birthdays = [];
        while ($bday = $db->fetch_array($query)) {
            $parts = explode('-', $bday['birthday']);
            array_pop($parts); // strip year
            $key = implode('-', $parts);

            if ($bday['birthdayprivacy'] !== 'all') {
                $birthdays[$key]['hiddencount'] = ($birthdays[$key]['hiddencount'] ?? 0) + 1;
                continue;
            }

            unset($bday['birthdayprivacy']);
            $bday['bday']               = $key;
            $birthdays[$key]['users'][] = $bday;
        }

        $this->update('birthdays', $birthdays);
    }

    public function update_forumsdisplay(): void
    {
        global $db;

        $fd = [];

        // Active announcements
        $query = $db->simple_select(
            'tsf_announcements',
            'fid',
            "enddate = '0' OR enddate > '" . TIMENOW . "'",
            ['order_by' => 'aid']
        );
        while ($row = $db->fetch_array($query)) {
            $fd[$row['fid']]['announcements'] ??= 1;
        }

        // Mod tools
        $query = $db->simple_select('modtools', 'forums,tid', '', ['order_by' => 'tid']);
        while ($tool = $db->fetch_array($query)) {
            foreach (explode(',', $tool['forums']) as $fid) {
                $fid = $fid !== '' ? (int)$fid : -1;
                $fd[$fid]['modtools'] ??= 1;
            }
        }

        $this->update('forumsdisplay', $fd);
    }

    public function reload_mostonline(): void
    {
        global $db;

        $query = $db->simple_select('datacache', 'cache', "title='mostonline'");
        $this->update('mostonline', my_unserialize($db->fetch_field($query, 'cache')));
    }

    // ── Debug ─────────────────────────────────────────────────

    public function debug_call(string $string, float $qtime, bool $hit): void
    {
        global $mybb, $plugins;

        $plugin_extra = $plugins->current_hook
            ? '<div style="float:right">(Plugin Hook: ' . $plugins->current_hook . ')</div>'
            : '';

        $hit_label               = $hit ? 'HIT' : 'MISS';
        [$method, $key]          = explode(':', $string, 2) + [1 => ''];

        $this->cache_debug = sprintf(
            '<table style="background-color:#666" width="95%%" cellpadding="4" cellspacing="1" align="center">
<tr><td style="background-color:#ccc">%s<div><strong>#%d - %s Call</strong></div></td></tr>
<tr style="background-color:#fefefe"><td><span style="font-family:Courier;font-size:14px">(%s) [%s] %s</span></td></tr>
<tr><td bgcolor="#ffffff">Call Time: %s</td></tr>
</table><br>%s',
            $plugin_extra,
            $this->call_count,
            ucfirst($method),
            $mybb->config['cache_store'],
            $hit_label,
            htmlspecialchars_uni($key),
            format_time_duration($qtime),
            "\n"
        );

        $this->calllist[$this->call_count] = ['key' => $string, 'time' => $qtime];
    }

    // ── Private helpers ───────────────────────────────────────

    /** Fetch a single entry directly from the datacache table. */
    private function fetchFromDb(string $name): mixed
    {
        global $db;

        $query = $db->simple_select(
            'datacache',
            'title,cache',
            "title='" . $db->escape_string($name) . "'"
        );
        $row = $db->fetch_array($query);

        return $row ? native_unserialize($row['cache']) : false;
    }

    /**
     * Run a handler call, measure elapsed time, update counters and debug log.
     *
     * @param \Closure(): bool $call
     */
    private function timedHandlerCall(string $verb, string $key, \Closure $call): bool
    {
        global $mybb;

        $start   = microtime(true);
        $hit     = $call();
        $elapsed = microtime(true) - $start;

        $this->call_time += $elapsed;
        $this->call_count++;

        if (!empty($mybb->debug_mode)) {
            $this->debug_call("{$verb}:{$key}", $elapsed, $hit);
        }

        return (bool)$hit;
    }

    /** Shared query for most-replied / most-viewed thread stats. */
    private function fetchThreadStats(string $fields, string $order_by, int $limit = 15): array
    {
        global $db;

        $threads = [];
        $query   = $db->simple_select('tsf_threads', $fields, "visible='1'", [
            'order_by'    => $order_by,
            'order_dir'   => 'DESC',
            'limit_start' => 0,
            'limit'       => $limit,
        ]);
        while ($row = $db->fetch_array($query)) {
            $threads[] = $row;
        }
        return $threads;
    }

    private function build_forum_permissions(array $permissions = [], int|string $pid = 0): void
    {
        $usergroups = array_keys($this->read('usergroups', true));

        foreach ($this->forum_permissions_forum_cache[$pid] ?? [] as $main) {
            foreach ($main as $forum) {
                $fid   = (int)$forum['fid'];
                $perms = $permissions;
                foreach ($usergroups as $gid) {
                    if (!empty($this->forum_permissions[$fid][$gid])) {
                        $perms[$gid]        = $this->forum_permissions[$fid][$gid];
                        $perms[$gid]['fid'] = $fid;
                        $this->built_forum_permissions[$fid][$gid] = $perms[$gid];
                    }
                }
                $this->build_forum_permissions($perms, $fid);
            }
        }
    }

    private function build_moderators(array $moderators = [], int|string $pid = 0): void
    {
        foreach ($this->moderators_forum_cache[$pid] ?? [] as $main) {
            foreach ($main as $forum) {
                $fid        = (int)$forum['fid'];
                $forum_mods = $moderators;

                if (isset($this->moderators[$fid])) {
                    $forum_mods = $forum_mods
                        ? array_merge($forum_mods, $this->moderators[$fid])
                        : $this->moderators[$fid];
                }

                $this->built_moderators[$fid] = $forum_mods;
                $this->build_moderators($forum_mods, $fid);
            }
        }
    }
}