<?php

declare(strict_types=1);

class Session
{
    private const ACTIVITY_INTERVAL = 900;   // секунд до "нового визита"
    private const LOCATION_MAX_LEN  = 150;
    private const USERAGENT_MAX_LEN = 200;
    private const SID_PATTERN       = '/^[a-f0-9]{32}$/';
    private const GC_PROBABILITY    = 100;   // чистка в среднем раз на N запросов

    public string $sid = '';
    public int $uid = 0;
    public string $ipaddress = '';
    public string $packedip = '';
    public string $useragent = '';

    /**
     * Поддержка поисковых ботов удалена. Свойство оставлено всегда false
     * только для совместимости: его читает showthread.php.
     */
    public bool $is_spider = false;

    /**
     * Случайный идентификатор гостя (cookie gtok). Гости в таблицу sessions
     * не пишутся; токен нужен только для привязки my_post_key к посетителю.
     */
    public string $guest_token = '';

    /**
     * Сколько секунд бездействия сессия считается действительной.
     * После этого cookie-sid игнорируется и выдаётся новый.
     */
    public int $idle_timeout = 3600;

    /**
     * Через сколько секунд строки из таблицы sessions удаляются сборщиком мусора.
     */
    public int $gc_lifetime = 86400;

    /**
     * Привязывать сессию к User-Agent, с которым она была создана.
     */
    public bool $bind_user_agent = true;

    /**
     * Привязывать сессию к подсети IP (IPv4 /24, IPv6 /48).
     * По умолчанию выключено: у мобильных пользователей IP часто меняется.
     */
    public bool $bind_ip = false;

    /**
     * Разрешить нескольким устройствам одного пользователя иметь свои сессии.
     * Если false, при создании новой сессии старые сессии пользователя удаляются.
     */
    public bool $allow_multiple_sessions = true;

    /**
     * Request parameters that are to be ignored for location storage
     */
    public array $ignore_parameters = [
        'my_post_key',
        'logoutkey',
    ];

    /**
     * uid, которому принадлежит сессия, найденная по cookie (0 = гостевая)
     */
    private int $session_uid = 0;

    /* ------------------------------------------------------------------ */
    /* Инициализация                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Initialize a session
     */
    public function init(): void
    {
        global $db, $mybb, $plugins;

        // Get our visitor's IP
        $this->ipaddress = get_ip();
        $this->packedip  = my_inet_pton($this->ipaddress);
        $this->useragent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Attempt to find a valid session id in the cookies
        $cookie_sid = $mybb->cookies['sid'] ?? '';
        if (
            is_string($cookie_sid)
            && preg_match(self::SID_PATTERN, $cookie_sid) === 1
            && !defined('IN_UPGRADE')
        ) {
            $session = $this->fetch($db->sql_query_prepared(
                "SELECT sid, uid, ip, time, useragent FROM sessions WHERE sid = ?",
                [$cookie_sid]
            ));

            if ($session) {
                if ($this->is_session_valid($session)) {
                    $this->sid         = (string)$session['sid'];
                    $this->session_uid = (int)$session['uid'];
                } else {
                    // Просрочена или не совпал UA/IP — удаляем, выдадим новую
                    $this->delete_session($cookie_sid);
                }
            }
        }

        $plugins?->run_hooks('pre_session_load', $this);

        // If we have a valid session id and user id, load that users session
        $mybbuser = $mybb->cookies['mybbuser'] ?? '';
        if (is_string($mybbuser) && $mybbuser !== '') {
            $logon = explode('_', $mybbuser, 2);
            $this->load_user((int)$logon[0], $logon[1] ?? '');
        }

        // If no user still, then we have a guest
        if (!isset($mybb->user['id'])) {
            $this->load_guest();
        }

        // Give the user a cookie
        if (
            $this->sid !== ''
            && (!isset($mybb->cookies['sid']) || $mybb->cookies['sid'] !== $this->sid)
        ) {
            my_setcookie('sid', $this->sid, -1, true);
        }

        $plugins?->run_hooks('post_session_load', $this);

        $this->garbage_collect();

        // Activate cron
        $GLOBALS['cron_image'] = !defined('SKIP_CRON_JOBS');
    }

    /* ------------------------------------------------------------------ */
    /* Загрузка пользователя / гостя                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Load a user via the user credentials
     */
    public function load_user(int $uid, string $loginkey = ''): bool
    {
        global $mybb, $db, $cache, $mybbgroups, $timeformat, $dateformat, $f_postsperpage, $f_threadsperpage;

        $user = $this->fetch($db->sql_query_prepared(
            "SELECT u.* FROM users u WHERE u.id = ? LIMIT 1",
            [$uid]
        ));

        // Проверяем ключ входа (сравнение в постоянное время)
        if (
            !$user
            || $loginkey === ''
            || !hash_equals((string)($user['loginkey'] ?? ''), $loginkey)
        ) {
            $mybb->user = [];
            $this->uid  = 0;
            return false;
        }

        $mybb->user = $user;
        $this->uid  = (int)$user['id'];

        // Сессия, найденная по cookie, принадлежит другому пользователю — не используем её
        if ($this->sid !== '' && $this->session_uid > 0 && $this->session_uid !== $this->uid) {
            $this->sid         = '';
            $this->session_uid = 0;
        }

        $mybb->user['logoutkey'] = $this->make_logoutkey((string)$user['loginkey']);

        $enablepms = '1';

        // Sort out the private message count for this user
        if (
            $enablepms != 0
            && (($mybb->user['totalpms'] ?? 0) == -1 || ($mybb->user['unreadpms'] ?? 0) == -1)
        ) {
            $update = 0;
            if (($mybb->user['totalpms'] ?? 0) == -1) {
                $update += 1;
            }
            if (($mybb->user['unreadpms'] ?? 0) == -1) {
                $update += 2;
            }

            require_once INC_PATH . '/functions_user.php';
            $pmcount = update_pm_count('', $update);
            if (is_array($pmcount)) {
                $mybb->user = array_merge($mybb->user, $pmcount);
            }
        }

        $mybb->user['pms_total']  = $mybb->user['totalpms'] ?? 0;
        $mybb->user['pms_unread'] = $mybb->user['unreadpms'] ?? 0;

        $this->update_user_activity();

        // Set user preferences
        if (!empty($mybb->user['dateformat'])) {
            global $date_formats;
            if (!empty($date_formats[$mybb->user['dateformat']])) {
                $dateformat = $date_formats[$mybb->user['dateformat']];
            }
        }

        if (!empty($mybb->user['timeformat'])) {
            global $time_formats;
            if (!empty($time_formats[$mybb->user['timeformat']])) {
                $timeformat = $time_formats[$mybb->user['timeformat']];
            }
        }

        // Set pagination preferences
        if (!empty($mybb->user['threadsperpages'])) {
            $f_threadsperpage = $mybb->user['threadsperpages'];
        }
        if (!empty($mybb->user['postsperpage'])) {
            $f_postsperpage = $mybb->user['postsperpage'];
        }

        $this->check_ban();

        // Права и оформление (стиль ника, звание, иконка) - по основной группе
        $mybbgroups = (string)max(1, (int)($mybb->user['usergroup'] ?? 1));

        $mybb->usergroup = usergroup_permissions($mybbgroups);

        if (empty($mybb->user['usertitle'])) {
            $mybb->user['usertitle'] = $mybb->usergroup['usertitle'] ?? '';
        }

        $GLOBALS['CURUSER']    = $mybb->user;
        $GLOBALS['usergroups'] = $mybb->usergroup ?? [];

        // Validate user access
        $script_name   = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        $is_activation = $mybb->get_input('action') === 'activate' && $script_name === 'member.php';

        if (
            ($mybb->usergroup['isbannedgroup'] ?? 0) == '1'
            || ($mybb->user['enabled'] ?? '') === 'no'
            || (($mybb->user['ustatus'] ?? '') === 'pending' && !$is_activation)
        ) {
            print_no_permission(false, true, $mybb->user['notifs'] ?? '');
            exit();
        }

        // Update or create the session
        if (!defined('NO_ONLINE') && !defined('IN_UPGRADE')) {
            if ($this->sid !== '' && $this->session_uid === $this->uid) {
                $this->update_session($this->sid, $this->uid);
            } else {
                // Была гостевая сессия (или чужая): выдаём новый sid при входе,
                // чтобы подброшенный заранее sid не стал сессией пользователя
                if ($this->sid !== '') {
                    $this->delete_session($this->sid);
                    $this->sid = '';
                }
                $this->create_session($this->uid);
            }
        }

        return true;
    }

    /**
     * Load a guest user.
     * Гость нужен только как контекст (группа 1) для страниц логина,
     * регистрации и ошибок. В таблицу sessions он НЕ записывается.
     */
    public function load_guest(): void
    {
        global $mybb;

        // Раньше эта сессия принадлежала пользователю (например, после выхода) — не переиспользуем её
        if ($this->sid !== '') {
            $this->delete_session($this->sid);
            $this->sid         = '';
            $this->session_uid = 0;
        }

        $mybb->user = [
            'usergroup'          => 1,
            'username'           => '',
            'id'                 => 0,
            'invisible'          => 0,
            'moderateposts'      => 0,
            'showquickreply'     => 1,
            'signature'          => '',
            'sourceeditor'       => 0,
            'subscriptionmethod' => 0,
            'suspendposting'     => 0,
            'lastactive'         => TIMENOW,
            'lastvisit'          => TIMENOW,
        ];

        // Gather permissions for guest
        $mybb->usergroup = usergroup_permissions('1');

        // Токен гостя для my_post_key (cookie, не БД)
        $tok = $mybb->cookies['gtok'] ?? '';
        if (!is_string($tok) || preg_match('/^[a-f0-9]{32}$/', $tok) !== 1) {
            $tok = bin2hex(random_bytes(16));
            my_setcookie('gtok', $tok, -1, true, 'lax');
        }
        $this->guest_token = $tok;

        $GLOBALS['CURUSER']    = $mybb->user;
        $GLOBALS['usergroups'] = $mybb->usergroup ?? [];
    }

    /* ------------------------------------------------------------------ */
    /* Работа с таблицей sessions                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Update a user session
     */
    public function update_session(string $sid, int $uid = 0): void
    {
        global $db, $mybb;

        $speciallocs = $this->get_special_locations();

        $onlinedata = [
            'uid'          => $uid,
            'time'         => TIMENOW,
            'ip'           => $this->packedip,
            'location'     => self::truncate(get_current_location(false, $this->ignore_parameters), self::LOCATION_MAX_LEN),
            'useragent'    => self::truncate($this->useragent, self::USERAGENT_MAX_LEN),
            'anonymous'    => (int)($mybb->user['invisible'] ?? 0),
            'location1'    => $speciallocs[1],
            'location2'    => $speciallocs[2],
            'nopermission' => 0,
        ];

        $set    = implode(', ', array_map(static fn(string $c): string => "`{$c}` = ?", array_keys($onlinedata)));
        $params = array_values($onlinedata);
        $params[] = $sid;

        $db->sql_query_prepared("UPDATE sessions SET {$set} WHERE sid = ?", $params);
    }

    /**
     * Create a new session
     */
    public function create_session(int $uid = 0): void
    {
        global $db, $mybb, $plugins;

        $speciallocs = $this->get_special_locations();

        // Delete existing sessions of this user (if only one session is allowed)
        if ($uid > 0 && !$this->allow_multiple_sessions) {
            $db->sql_query_prepared("DELETE FROM sessions WHERE uid = ?", [$uid]);
        }

        // Generate session ID
        $sid = $this->generate_sid();

        $onlinedata = [
            'sid'          => $sid,
            'uid'          => $uid,
            'time'         => TIMENOW,
            'ip'           => $this->packedip,
            'location'     => self::truncate(get_current_location(false, $this->ignore_parameters), self::LOCATION_MAX_LEN),
            'useragent'    => self::truncate($this->useragent, self::USERAGENT_MAX_LEN),
            'anonymous'    => (int)($mybb->user['invisible'] ?? 0),
            'location1'    => $speciallocs[1],
            'location2'    => $speciallocs[2],
            'nopermission' => 0,
        ];

        $set = implode(', ', array_map(static fn(string $c): string => "`{$c}` = ?", array_keys($onlinedata)));

        $db->sql_query_prepared("REPLACE INTO sessions SET {$set}", array_values($onlinedata));

        $this->sid         = $sid;
        $this->uid         = $uid;
        $this->session_uid = $uid;

        $plugins?->run_hooks('post_session_create', $this);
    }

    /**
     * Delete one session by its id
     */
    public function delete_session(string $sid): void
    {
        global $db;

        if ($sid !== '') {
            $db->sql_query_prepared("DELETE FROM sessions WHERE sid = ?", [$sid]);
        }
    }

    /**
     * Destroy the current session (call on logout)
     */
    public function destroy_session(): void
    {
        global $plugins;

        $plugins?->run_hooks('pre_session_destroy', $this);

        $this->delete_session($this->sid);

        if (function_exists('my_unsetcookie')) {
            my_unsetcookie('sid');
        }

        $this->sid         = '';
        $this->uid         = 0;
        $this->session_uid = 0;
    }

    /**
     * Destroy all sessions of a user ("log out everywhere")
     */
    public function destroy_user_sessions(int $uid): void
    {
        global $db;

        if ($uid > 0) {
            $db->sql_query_prepared("DELETE FROM sessions WHERE uid = ?", [$uid]);
        }
    }

    /**
     * Replace the current session id with a new one (call right after login)
     */
    public function regenerate_session(int $uid = 0): void
    {
        if ($this->sid !== '') {
            $this->delete_session($this->sid);
        }

        $this->sid = '';
        $this->create_session($uid);

        my_setcookie('sid', $this->sid, -1, true);
    }

    /**
     * Find out the special locations
     *
     * @return array{1:int,2:int}
     */
    public function get_special_locations(): array
    {
        global $mybb, $db;

        $array = [1 => 0, 2 => 0];

        $scriptName = (string)($_SERVER['PHP_SELF'] ?? '');

        if (str_contains($scriptName, 'forumdisplay.php')) {
            $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
            if ($fid > 0 && $fid < 4294967296) {
                $array[1] = $fid;
            }
        } elseif (str_contains($scriptName, 'showthread.php')) {
            $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
            if ($tid > 0 && $tid < 4294967296) {
                $array[2] = $tid;
            } elseif (!empty($mybb->input['pid'])) {
                $post = $this->fetch($db->sql_query_prepared(
                    "SELECT tid FROM posts WHERE pid = ? LIMIT 1",
                    [$mybb->get_input('pid', MyBB::INPUT_INT)]
                ));
                if ($post) {
                    $array[2] = (int)$post['tid'];
                }
            }

            if ($array[2] > 0) {
                $thread = get_thread($array[2]);
                if ($thread) {
                    $array[1] = (int)$thread['fid'];
                }
            }
        }

        return $array;
    }

    /* ------------------------------------------------------------------ */
    /* Вспомогательные методы                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Обновление активности пользователя одним запросом
     * (lastactive, lastvisit/timeonline, last_login, passkey, lastip)
     */
    private function update_user_activity(): void
    {
        global $mybb, $db;

        $user = $mybb->user;
        $time = TIMENOW;

        $lastactive = (int)($user['lastactive'] ?? 0);
        $new_visit  = ($time - $lastactive) > self::ACTIVITY_INTERVAL;

        $set    = ['lastactive = ?'];
        $params = [$time];

        if ($new_visit) {
            $set[]    = 'lastvisit = ?';
            $params[] = $lastactive;
        } else {
            $set[]    = 'timeonline = timeonline + ?';
            $params[] = $time - $lastactive;
        }

        // Update last login if needed
        if (self::ACTIVITY_INTERVAL < $time - (int)($user['last_login'] ?? 0)) {
            $set[]    = 'last_login = ?';
            $params[] = $lastactive;
        }

        // Generate passkey if needed
        if (strlen((string)($user['passkey'] ?? '')) !== 32) {
            $passkey = generate_passkey($user['username'], $user['loginkey']);
            if ($passkey !== false) {
                $set[]    = 'passkey = ?';
                $params[] = $passkey;
            }
        }

        // Update IP if changed
        if (
            array_key_exists('lastip', $user)
            && (string)$user['lastip'] !== $this->packedip
            && !defined('IN_UPGRADE')
        ) {
            $set[]    = 'lastip = ?';
            $params[] = $this->packedip;
        }

        $params[] = (int)$user['id'];

        $db->sql_query_prepared(
            'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?',
            $params
        );

        if ($new_visit) {
            $mybb->user['lastvisit'] = $lastactive;
            require_once INC_PATH . '/functions_user.php';
            update_pm_count('', 2);
        }
    }

    /**
     * Проверка бана и возврат прежней группы после истечения срока
     */
    private function check_ban(): void
    {
        global $mybb, $db, $cache;

        $time = TIMENOW;

        $usergroups  = $cache->read('usergroups');
        $userGroupId = (int)($mybb->user['usergroup'] ?? 0);

        if (!empty($usergroups[$userGroupId]) && $usergroups[$userGroupId]['isbannedgroup'] == 1) {
            $ban = $this->fetch($db->sql_query_prepared(
                "SELECT * FROM banned WHERE uid = ? LIMIT 1",
                [(int)$mybb->user['id']]
            ));

            if ($ban) {
                $mybb->user['banned']       = 1;
                $mybb->user['bandate']      = $ban['dateline'];
                $mybb->user['banlifted']    = $ban['lifted'];
                $mybb->user['banoldgroup']  = $ban['oldgroup'];
                $mybb->user['banreason']    = $ban['reason'];
            } else {
                $mybb->user['banned'] = 0;
            }
        }

        // Срок бана истёк - возвращаем прежнюю группу
        if (
            !empty($mybb->user['bandate'])
            && !empty($mybb->user['banlifted'])
            && $mybb->user['banlifted'] < $time
        ) {
            $db->sql_query_prepared(
                "UPDATE users SET usergroup = ? WHERE id = ?",
                [(int)($mybb->user['banoldgroup'] ?? 0), (int)$mybb->user['id']]
            );
            $db->sql_query_prepared("DELETE FROM banned WHERE uid = ?", [(int)$mybb->user['id']]);

            $mybb->user['usergroup'] = $mybb->user['banoldgroup'];
        }
    }

    /**
     * Валидна ли найденная по cookie сессия
     */
    private function is_session_valid(array $session): bool
    {
        if (TIMENOW - (int)$session['time'] > $this->idle_timeout) {
            return false;
        }

        if ($this->bind_user_agent) {
            $stored  = self::truncate((string)$session['useragent'], self::USERAGENT_MAX_LEN);
            $current = self::truncate($this->useragent, self::USERAGENT_MAX_LEN);
            if (!hash_equals($stored, $current)) {
                return false;
            }
        }

        if ($this->bind_ip && !$this->ip_matches((string)$session['ip'], $this->packedip)) {
            return false;
        }

        return true;
    }

    /**
     * Совпадение IP с точностью до подсети (IPv4 /24, IPv6 /48)
     */
    private function ip_matches(string $stored, string $current): bool
    {
        if ($stored === $current) {
            return true;
        }
        if (strlen($stored) !== strlen($current)) {
            return false;
        }

        $prefix = strlen($stored) === 4 ? 3 : (strlen($stored) === 16 ? 6 : 0);

        return $prefix > 0 && substr($stored, 0, $prefix) === substr($current, 0, $prefix);
    }

    /**
     * Ключ выхода. Если определена константа LOGOUTKEY_SECRET, используется HMAC,
     * иначе прежний md5 (совместимость). Код, проверяющий logoutkey, должен
     * использовать этот же метод.
     */
    public function make_logoutkey(string $loginkey): string
    {
        if (defined('LOGOUTKEY_SECRET')) {
            return hash_hmac('sha256', $loginkey, (string)LOGOUTKEY_SECRET);
        }

        return md5($loginkey);
    }

    /**
     * Случайный идентификатор сессии, 32 hex-символа (влезает в varchar(32))
     */
    private function generate_sid(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Удаление старых строк из sessions (примерно раз в GC_PROBABILITY запросов)
     */
    private function garbage_collect(): void
    {
        global $db;

        if (defined('IN_UPGRADE') || random_int(1, self::GC_PROBABILITY) !== 1) {
            return;
        }

        $db->sql_query_prepared(
            "DELETE FROM sessions WHERE time < ?",
            [TIMENOW - max($this->gc_lifetime, $this->idle_timeout)]
        );
    }

    /**
     * Получить строку из результата запроса. Поддерживает и объект с ->result,
     * и обычный ресурс/объект результата.
     */
    private function fetch(mixed $query): ?array
    {
        global $db;

        if (!$query) {
            return null;
        }

        $result = (is_object($query) && isset($query->result)) ? $query->result : $query;
        $row    = $db->fetch_array($result);

        return is_array($row) ? $row : null;
    }

    /**
     * Безопасная обрезка строки по байтам без разрыва UTF-8 символа
     */
    private static function truncate(string $value, int $max): string
    {
        if (function_exists('mb_scrub')) {
            $value = mb_scrub($value);
        }

        return function_exists('mb_strcut')
            ? mb_strcut($value, 0, $max, 'UTF-8')
            : substr($value, 0, $max);
    }
}