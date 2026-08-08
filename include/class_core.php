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
    public bool   $use_shutdown = true;
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
        'posts'         => ['ipaddress' => true],
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
    // ОШИБКИ
    // ----------------------------
    function trigger_generic_error(string $code): void
    {
        $message = match($code) {
            'cache_no_write'       => 'The data cache directory (cache/) needs to exist and be writable by the web server.',
            'install_directory'    => 'The install directory (install/) still exists on your server and is not locked.',
            'board_not_installed'  => 'Your board has not yet been installed and configured.',
            'board_not_upgraded'   => 'Your board has not yet been upgraded.',
            'sql_load_error'       => 'Unable to load the SQL extension.',
            'apc_load_error'       => 'APC needs to be configured with PHP to use the APC cache support.',
            'apcu_load_error'      => 'APCu needs to be configured with PHP to use the APCu cache support.',
            'memcache_load_error'  => 'Your server does not have memcache support enabled.',
            'memcached_load_error' => 'Your server does not have memcached support enabled.',
            'redis_load_error'     => 'Your server does not have redis support enabled.',
            'eaccelerator_load_error' => 'Your server does not have eAccelerator support enabled.',
            'xcache_load_error'    => 'Your server does not have XCache support enabled.',
            default                => 'An internal configuration error occurred.',
        };

        stderr($message, 'Configuration Error', 500, 'general');
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
    'attachquota',
    'edittimelimit',
    'pmquota',
    'maxpmrecipients',
    'maxemails',
];

$groupzerolesser = [
    'emailfloodtime',
];

$groupxgreater = [
];

$grouppermbyswitch = [
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
    'caneditposts',
    'candeleteposts',
    'candeletethreads',
    'caneditattachments',
    'modposts',
    'modthreads',
    'modattachments',
    'mod_edit_posts',
    'canpostpolls',
    'canvotepolls',
    'cansearch'
];