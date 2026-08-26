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
            $query = $db->sql_query_prepared("SELECT title, cache FROM datacache");
            while ($query && ($row = $db->fetch_array($query))) {
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
            $data = $this->timedHandlerCall('get', $name, fn() => $this->handler->fetch($name));

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
        $db->sql_query_prepared(
            "REPLACE INTO datacache SET `title` = ?, `cache` = ?",
            [$name, my_serialize($contents)]
        );

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

        $where  = "title = ?";
        $params = [$name];

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

            $like_pattern = strtr($name, ['%' => '=%', '=' => '==', '_' => '=_']) . '=_%';
            $where       .= " OR title LIKE ? ESCAPE '='";
            $params[]     = $like_pattern;

            if ($this->handler instanceof CacheHandlerInterface) {
                $query = $db->sql_query_prepared("SELECT title FROM datacache WHERE {$where}", $params);
                while ($query && ($row = $db->fetch_array($query))) {
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

        $db->sql_query_prepared("DELETE FROM datacache WHERE {$where}", $params);
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
            $query = $db->sql_query_prepared("SELECT cache FROM datacache WHERE title = ?", [$name]);
            return $query ? strlen((string)$db->fetch_field($query, 'cache')) : 0;
        }

        return (int)$db->fetch_size('datacache');
    }

    // ── update_* methods ─────────────────────────────────────
    public function update_bannedips(): void
    {
        global $db;

        $banned_ips = [];
        $query = $db->sql_query_prepared("SELECT fid, filter FROM banfilters WHERE type = 1");
        while ($query && ($row = $db->fetch_array($query))) {
            $banned_ips[$row['fid']] = $row;
        }
        $this->update('bannedips', $banned_ips);
    }

    public function update_bannedemails(): void
    {
        global $db;

        $banned_emails = [];
        $query = $db->sql_query_prepared("SELECT fid, filter FROM banfilters WHERE type = '3'");
        while ($query && ($row = $db->fetch_array($query))) {
            $banned_emails[$row['fid']] = $row;
        }
        $this->update('bannedemails', $banned_emails);
    }

    public function update_attachtypes(): void
    {
        global $db;

        $types = [];
        $query = $db->sql_query_prepared("SELECT * FROM attachtypes WHERE enabled = 1");
        while ($query && ($type = $db->fetch_array($query))) {
            $type['extension'] = my_strtolower($type['extension']);
            $types[$type['extension']] = $type;
        }
        $this->update('attachtypes', $types);
    }

    public function update_smilies(): void
    {
        global $db;

        $smilies = [];
        $query   = $db->sql_query_prepared('SELECT stext, spath FROM smilies ORDER BY sorder, stitle');
        while ($query && ($smilie = $db->fetch_array($query))) {
            $smilies[$smilie['stext']] = $smilie['spath'];
        }
        $this->update('smilies', $smilies);
    }

    public function update_badwords(): void
    {
        global $db;

        $badwords = [];
        $query    = $db->sql_query_prepared("SELECT * FROM badwords");
        while ($query && ($badword = $db->fetch_array($query))) {
            $badwords[$badword['bid']] = $badword;
        }
        $this->update('badwords', $badwords);
    }

    public function update_usergroups(): void
    {
        global $db;

        $groups = [];
        $query  = $db->sql_query_prepared("SELECT * FROM usergroups");
        while ($query && ($g = $db->fetch_array($query))) {
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

        $query = $db->sql_query_prepared("SELECT * FROM forumpermissions");
        while ($query && ($perm = $db->fetch_array($query))) {
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
        $query = $db->sql_query_prepared("
            SELECT m.*, u.username, u.usergroup, u.displaygroup
            FROM moderators m
            LEFT JOIN users u ON m.id = u.id
            WHERE m.isgroup = '0'
            ORDER BY u.username
        ");
        while ($query && ($mod = $db->fetch_array($query))) {
            $this->moderators[$mod['fid']]['users'][$mod['id']] = $mod;
        }

        // Group moderators
        $query = $db->sql_query_prepared("
            SELECT m.*, u.title
            FROM moderators m
            LEFT JOIN usergroups u ON m.id = u.gid
            WHERE m.isgroup = '1'
            ORDER BY u.title
        ");
        while ($query && ($mod = $db->fetch_array($query))) {
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

        $query = $db->sql_query_prepared("SELECT COUNT(id) AS awaitingusers FROM users WHERE ustatus = 'pending'");
        $count = $query ? (int)$db->fetch_field($query, 'awaitingusers') : 0;

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

        $query = $db->sql_query_prepared("SELECT * FROM forums ORDER BY pid,disporder");
        while ($query && ($forum = $db->fetch_array($query))) {
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

        $query = $db->sql_query_prepared("
            SELECT u.id, u.username, COUNT(*) AS poststoday
            FROM posts p
            LEFT JOIN users u ON p.uid = u.id
            WHERE p.dateline > ? AND p.visible = 1
            GROUP BY u.id, u.username
            ORDER BY poststoday DESC
        ", [$since]);

        $topposter  = [];
        $most_posts = 0;
        while ($query && ($user = $db->fetch_array($query))) {
            if ((int)$user['poststoday'] > $most_posts) {
                $most_posts = (int)$user['poststoday'];
                $topposter  = $user;
            }
        }

        $query   = $db->sql_query_prepared("SELECT COUNT(id) AS posters FROM users WHERE postnum > 0");
        $posters = $query ? (int)$db->fetch_field($query, 'posters') : 0;

        $this->update('statistics', [
            'time'         => TIMENOW,
            'top_referrer' => [],
            'top_poster'   => $topposter,
            'posters'      => $posters,
        ]);
    }


    public function update_mailqueue(int $last_run = 0, int $lock_time = 0): void
    {
        global $db;

        $query      = $db->sql_query_prepared("SELECT COUNT(*) AS queue_size FROM mailqueue");
        $queue_size = $query ? (int)$db->fetch_field($query, 'queue_size') : 0;

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


    public function update_birthdays(): void
    {
        global $db;

        $now       = TIMENOW;
        $today     = my_datee('j-n', $now,         '', 0);
        $tomorrow  = my_datee('j-n', $now + 86400, '', 0);
        $yesterday = my_datee('j-n', $now - 86400, '', 0);

        $query = $db->sql_query_prepared(
            "SELECT id,username,usergroup,displaygroup,birthday,birthdayprivacy FROM users
             WHERE birthday LIKE ? OR birthday LIKE ? OR birthday LIKE ?",
            ["{$today}-%", "{$yesterday}-%", "{$tomorrow}-%"]
        );

        $birthdays = [];
        while ($query && ($bday = $db->fetch_array($query))) {
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
        $query = $db->sql_query_prepared(
            "SELECT fid FROM announcements WHERE type IN ('forum', 'global') AND (enddate = '0' OR enddate > ?) ORDER BY id",
            [TIMENOW]
        );

        while ($query && ($row = $db->fetch_array($query))) {
            $fd[$row['fid']]['announcements'] ??= 1;
        }


        $this->update('forumsdisplay', $fd);
    }

    public function reload_mostonline(): void
    {
        global $db;

        $query = $db->sql_query_prepared("SELECT cache FROM datacache WHERE title = 'mostonline'");
        $this->update('mostonline', my_unserialize($query ? $db->fetch_field($query, 'cache') : null));
    }

    // ── Private helpers ───────────────────────────────────────

    /** Fetch a single entry directly from the datacache table. */
    private function fetchFromDb(string $name): mixed
    {
        global $db;

        $query = $db->sql_query_prepared("SELECT title, cache FROM datacache WHERE title = ?", [$name]);
        $row = $query ? $db->fetch_array($query) : null;

        return $row ? native_unserialize($row['cache']) : false;
    }

    // ── Debug ─────────────────────────────────────────────────

    /**
     * Run a handler call, measure elapsed time, update counters.
     * Данные копятся всегда (не только при debug_mode) - они уходят через
     * скрытые поля формы в footer.php и рендерятся по клику "Advanced
     * Details" в query_explain.php, тем же путём, что и SQL-запросы.
     */
    private function timedHandlerCall(string $verb, string $key, \Closure $call): mixed
    {
        $start   = microtime(true);
        $result  = $call();
        $elapsed = microtime(true) - $start;

        $this->call_time += $elapsed;
        $this->call_count++;

        // HIT/MISS определяем отдельно от возвращаемого значения - для
        // read() результат это реальные данные (могут быть false при
        // промахе), для set/update/delete это признак успеха операции.
        $this->calllist[$this->call_count] = [
            'verb' => $verb,
            'key'  => $key,
            'time' => $elapsed,
            'hit'  => $result !== false,
        ];

        return $result;
    }

    /** Shared query for most-replied / most-viewed thread stats. */
    private function fetchThreadStats(string $fields, string $order_by, int $limit = 15): array
    {
        global $db;

        $threads = [];
        $query   = $db->sql_query_prepared(
            "SELECT {$fields} FROM threads WHERE visible = '1' ORDER BY {$order_by} DESC LIMIT 0, ?",
            [$limit]
        );
        while ($query && ($row = $db->fetch_array($query))) {
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