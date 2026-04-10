<?php


declare(strict_types=1);

class MyBB
{
    public string $version      = "1.8.38";
    public int    $version_code = 1838;
    public string $cwd          = ".";
    public array  $input        = [];
    public array  $cookies      = [];
    public array  $user         = [];
    public array  $usergroup    = [];
    public array  $settings     = [];
    public bool   $seo_support  = false;
    public array  $config       = [];
    public string $request_method = "";
    public bool   $safemode     = false;
    public bool   $dev_mode     = false;
    public bool   $use_shutdown = true;
    public bool   $debug_mode   = false;
    public ?string $asset_url   = null;
    public mixed $session = null;
    public string $post_code    = '';
    public mixed  $admin        = null;
    public mixed  $cache        = null;

    public array $ignore_clean_variables = [];

    public array $clean_variables = [
        "int" => [
            "tid", "pid", "uid",
            "eid", "pmid", "fid",
            "aid", "rid", "sid",
            "vid", "cid", "bid",
            "hid", "gid", "mid",
            "wid", "lid", "iid",
            "did", "qid", "id"
        ],
        "pos" => [
            "page", "perpage"
        ],
        "a-z" => [
            "sortby", "order"
        ]
    ];

    public array $binary_fields = [
        'adminlog'      => ['ipaddress' => true],
        'adminsessions' => ['ip' => true],
        'maillogs'      => ['ipaddress' => true],
        'moderatorlog'  => ['ipaddress' => true],
        'sitelog'       => ['ipaddress' => true],
        'pollvotes'     => ['ipaddress' => true],
        'tsf_pollvotes' => ['ipaddress' => true],
        'posts'         => ['ipaddress' => true],
        'tsf_posts'     => ['ipaddress' => true],
        'privatemessages' => ['ipaddress' => true],
        'searchlog'     => ['ipaddress' => true],
        'sessions'      => ['ip' => true],
        'threadratings' => ['ipaddress' => true],
        'users'         => ['regip' => true, 'lastip' => true],
        'spamlog'       => ['ipaddress' => true],
    ];

    const INPUT_STRING = 0;
    const INPUT_INT    = 1;
    const INPUT_ARRAY  = 2;
    const INPUT_FLOAT  = 3;
    const INPUT_BOOL   = 4;

    // ----------------------------
    // КОНСТРУКТОР
    // ----------------------------
    function __construct()
    {
        $protected = ["_GET", "_POST", "_SERVER", "_COOKIE", "_FILES", "_ENV", "GLOBALS"];
        foreach ($protected as $var) {
            if (isset($_POST[$var]) || isset($_GET[$var]) || isset($_COOKIE[$var]) || isset($_FILES[$var])) {
                die("Hacking attempt");
            }
        }

        if (defined("IGNORE_CLEAN_VARS")) {
            $this->ignore_clean_variables = is_array(IGNORE_CLEAN_VARS)
                ? IGNORE_CLEAN_VARS
                : [IGNORE_CLEAN_VARS];
        }

        $this->parse_incoming($_GET);
        $this->parse_incoming($_POST);

        $this->request_method = match($_SERVER['REQUEST_METHOD'] ?? '') {
            'POST' => 'post',
            'GET'  => 'get',
            default => ''
        };

        $this->clean_input();

        $safe_mode_status = @ini_get("safe_mode");
        if ($safe_mode_status == 1 || strtolower((string)$safe_mode_status) === 'on') {
            $this->safemode = true;
        }

        if (isset($_SERVER['MYBB_DEV_MODE']) && $_SERVER['MYBB_DEV_MODE'] == 1) {
            $this->dev_mode = true;
        }

        if (isset($this->input['debug']) && $this->input['debug'] == 1) {
            $this->debug_mode = true;
        }

        if (isset($this->input['action']) && $this->input['action'] === "mybb_logo") {
            require_once dirname(__FILE__) . "/mybb_group.php";
            output_logo();
        }

        if (isset($this->input['intcheck']) && $this->input['intcheck'] == 1) {
            die("&#077;&#089;&#066;&#066;");
        }
    }

    // ----------------------------
    // ВХОДНЫЕ ДАННЫЕ
    // ----------------------------
    function parse_incoming(array $array): void
    {
        foreach ($array as $key => $val) {
            $this->input[$key] = $val;
        }
    }

    function parse_cookies(): void
    {
        global $cookieprefix;

        if (!is_array($_COOKIE)) {
            return;
        }

        $cookieprefix   = "";
        $prefix_length  = strlen($cookieprefix);

        foreach ($_COOKIE as $key => $val) {
            if ($prefix_length && str_starts_with($key, $cookieprefix)) {
                $key = substr($key, $prefix_length);
                if (isset($this->cookies[$key])) {
                    unset($this->cookies[$key]);
                }
            }

            if (empty($this->cookies[$key])) {
                $this->cookies[$key] = $val;
            }
        }
    }

    function clean_input(): void
    {
        foreach ($this->clean_variables as $type => $variables) {
            foreach ($variables as $var) {
                if (in_array($var, $this->ignore_clean_variables)) {
                    continue;
                }

                if (!isset($this->input[$var])) {
                    continue;
                }

                $this->input[$var] = match($type) {
                    'int' => $this->get_input($var, self::INPUT_INT),
                    'a-z' => preg_replace("#[^a-z\.\-_]#i", "", $this->get_input($var)),
                    'pos' => (
                        ($this->input[$var] < 0 && $var !== "page") ||
                        ($var === "page" && $this->input[$var] !== "last" && $this->input[$var] < 0)
                    ) ? 0 : $this->input[$var],
                    default => $this->input[$var]
                };
            }
        }
    }

    function get_input(string $name, int $type = self::INPUT_STRING): int|float|array|string|bool
    {
        return match($type) {
            self::INPUT_ARRAY => (isset($this->input[$name]) && is_array($this->input[$name]))
                ? $this->input[$name]
                : [],

            self::INPUT_INT => (isset($this->input[$name]) && is_numeric($this->input[$name]))
                ? (int)$this->input[$name]
                : 0,

            self::INPUT_FLOAT => (isset($this->input[$name]) && is_numeric($this->input[$name]))
                ? (float)$this->input[$name]
                : 0.0,

            self::INPUT_BOOL => (isset($this->input[$name]) && is_scalar($this->input[$name]))
                ? (bool)$this->input[$name]
                : false,

            default => (isset($this->input[$name]) && is_scalar($this->input[$name]))
                ? $this->input[$name]
                : ''
        };
    }

    // ----------------------------
    // ASSETS
    // ----------------------------
    public function get_asset_url(string $path = '', bool $use_cdn = true): string
    {
        global $BASEURL;

        $cdnurl  = "";
        $usecdn  = "0";

        $path = ltrim((string)$path, '/');

        if (!str_starts_with($path, 'http')) {
            if (str_starts_with($path, './')) {
                $path = substr($path, 2);
            }

            $base_path = ($use_cdn && $usecdn && !empty($cdnurl))
                ? rtrim($cdnurl, '/')
                : rtrim((string)$BASEURL, '/');

            return !empty($path)
                ? $base_path . '/' . $path
                : $base_path;
        }

        return $path;
    }

    // ----------------------------
    // ОШИБКИ
    // ----------------------------
    function trigger_generic_error(string $code): void
    {
        global $error_handler;

        [$message, $error_code] = match($code) {
            'cache_no_write'      => ["The data cache directory (cache/) needs to exist and be writable by the web server.", MYBB_CACHE_NO_WRITE],
            'install_directory'   => ["The install directory (install/) still exists on your server and is not locked.", MYBB_INSTALL_DIR_EXISTS],
            'board_not_installed' => ["Your board has not yet been installed and configured.", MYBB_NOT_INSTALLED],
            'board_not_upgraded'  => ["Your board has not yet been upgraded.", MYBB_NOT_UPGRADED],
            'sql_load_error'      => ["MyBB was unable to load the SQL extension. <a href=\"https://mybb.com\">MyBB Website</a>", MYBB_SQL_LOAD_ERROR],
            'apc_load_error'      => ["APC needs to be configured with PHP to use the APC cache support.", MYBB_CACHEHANDLER_LOAD_ERROR],
            'apcu_load_error'     => ["APCu needs to be configured with PHP to use the APCu cache support.", MYBB_CACHEHANDLER_LOAD_ERROR],
            'memcache_load_error' => ["Your server does not have memcache support enabled.", MYBB_CACHEHANDLER_LOAD_ERROR],
            'memcached_load_error'=> ["Your server does not have memcached support enabled.", MYBB_CACHEHANDLER_LOAD_ERROR],
            'redis_load_error'    => ["Your server does not have redis support enabled.", MYBB_CACHEHANDLER_LOAD_ERROR],
            default               => ["MyBB has experienced an internal error. <a href=\"https://mybb.com\">MyBB Website</a>", MYBB_GENERAL]
        };

        $error_handler->trigger($message, $error_code);
    }

    function __destruct()
    {
        if (function_exists("run_shutdown")) {
            run_shutdown();
        }
    }
}

// ----------------------------
// ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ГРУПП
// ----------------------------
$grouppermignore = ["gid", "type", "title", "description", "namestyle"];

$groupzerogreater = [
    'maxposts',
    'attachquota',
    'edittimelimit',
    'maxreputationsperthread',
    'maxreputationsperuser',
    'maxreputationsday',
    'maxwarningsday',
    'pmquota',
    'maxpmrecipients',
    'maxemails',
];

$groupzerolesser = [
    'canusesigxposts',
    'emailfloodtime',
];

$groupxgreater = [
    'reputationpower' => 0,
];

$grouppermbyswitch = [
    'maxposts'        => ['canpostthreads', 'canpostreplys'],
    'attachquota'     => 'canpostattachments',
    'edittimelimit'   => 'caneditposts',
    'pmquota'         => 'canusepms',
    'maxpmrecipients' => 'canusepms',
    'maxemails'       => 'cansendemail',
    'emailfloodtime'  => 'cansendemail',
];

$displaygroupfields = ["title", "description", "namestyle"];

$fpermfields = [
    'canview',
    'canviewthreads',
    'candlattachments',
    'canpostthreads',
    'canpostreplys',
    'canpostattachments',
    'canratethreads',
    'caneditposts',
    'candeleteposts',
    'candeletethreads',
    'caneditattachments',
    'canviewdeletionnotice',
    'modposts',
    'modthreads',
    'modattachments',
    'mod_edit_posts',
    'canpostpolls',
    'canvotepolls',
    'cansearch'
];