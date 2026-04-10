<?php


declare(strict_types=1);

function my_strtoupper(string $string): string
{
    return function_exists("mb_strtoupper")
        ? mb_strtoupper($string)
        : strtoupper($string);
}

function get_execution_time(): ?float
{
    static $time_start;

    $time = microtime(true);

    if (!$time_start) {
        $time_start = $time;
        return null;
    } else {
        $total = $time - $time_start;
        if ($total < 0) $total = 0;
        $time_start = 0;
        return $total;
    }
}

function format_time_duration(mixed $time): string
{
    if (!is_numeric($time)) {
        return 'na';
    }

    $time = (float)$time;

    return match(true) {
        round(1000000 * $time, 2) < 1000 => number_format(round(1000000 * $time, 2)) . " μs",
        round(1000000 * $time, 2) >= 1000 && round(1000000 * $time, 2) < 1000000 => number_format(round((1000 * $time), 2)) . " ms",
        default => round($time, 3) . " seconds"
    };
}

function validate_utf8_string(string $input, bool $allow_mb4 = true, bool $return = true): string|bool
{
    if (!preg_match('##u', $input)) {
        $string = '';
        $len = strlen($input);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($input[$i]);
            if ($c > 128) {
                if ($c > 247 || $c <= 191) {
                    if ($return) {
                        $string .= '?';
                        continue;
                    } else {
                        return false;
                    }
                }

                $bytes = match(true) {
                    $c > 239 => 4,
                    $c > 223 => 3,
                    $c > 191 => 2,
                    default  => 0
                };

                if (($i + $bytes) > $len) {
                    if ($return) {
                        $string .= '?';
                        break;
                    } else {
                        return false;
                    }
                }

                $valid      = true;
                $multibytes = $input[$i];
                while ($bytes > 1) {
                    $i++;
                    $b = ord($input[$i]);
                    if ($b < 128 || $b > 191) {
                        if ($return) {
                            $valid   = false;
                            $string .= '?';
                            break;
                        } else {
                            return false;
                        }
                    } else {
                        $multibytes .= $input[$i];
                    }
                    $bytes--;
                }
                if ($valid) {
                    $string .= $multibytes;
                }
            } else {
                $string .= $input[$i];
            }
        }
        $input = $string;
    }

    if ($return) {
        return $allow_mb4
            ? $input
            : preg_replace("#[^\\x00-\\x7F][\\x80-\\xBF]{3,}#", '?', $input);
    } else {
        return $allow_mb4
            ? true
            : !preg_match("#[^\\x00-\\x7F][\\x80-\\xBF]{3,}#", $input);
    }
}

define("MYBB_SQL",    20);
define("CONNECT_SQL", 21);

class DB_MySQLi implements DB_Base
{
    public string $title       = "MySQLi";
    public string $short_title = "MySQLi";
    public string $type;
    public int    $query_count = 0;
    public array  $querylist   = [];
    public bool   $error_reporting = true;

    public mysqli|bool|null $read_link    = null;
    public mysqli|bool|null $write_link   = null;
    public mysqli|bool|null $current_link = null;

    public array  $connections = [];
    public string $database;
    public string $explain;
    public string $version;
    public string $table_type    = "myisam";
    public string $engine        = "mysqli";
    public bool   $can_search    = true;
    public string $db_encoding   = "utf8";
    public float  $query_time    = 0.0;
    public bool   $force_display_errors = false;
    public bool   $has_errors    = false;
    public bool   $db_initialized = false; // флаг вместо table_prefix

    protected int $last_query_type = 0;

    // ----------------------------
    // ПОДКЛЮЧЕНИЕ
    // ----------------------------
    function connect(array $config): mysqli|false
    {
        // Валидация обязательных параметров
        $required = ['hostname', 'username', 'password', 'database'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                $this->sql_error(CONNECT_SQL, [
                    'error_no' => 0,
                    'error'    => "Database configuration error: '{$field}' is empty. Please check config.php.",
                    'query'    => 'connect()',
                ], __FILE__, __LINE__);
            }
        }

        $connections = [];

        if (array_key_exists('hostname', $config)) {
            $connections['read'][] = $config;
        } else {
            if (!array_key_exists('read', $config)) {
                foreach ($config as $key => $settings) {
                    if (is_int($key)) {
                        $connections['read'][] = $settings;
                    }
                }
            } else {
                $connections = $config;
            }
        }

        if (isset($config['encoding'])) {
            $this->db_encoding = $config['encoding'];
        }

        foreach (['read', 'write'] as $type) {
            if (!isset($connections[$type]) || !is_array($connections[$type])) {
                break;
            }

            if (array_key_exists('hostname', $connections[$type])) {
                $details = $connections[$type];
                unset($connections[$type]);
                $connections[$type][] = $details;
            }

            shuffle($connections[$type]);

            foreach ($connections[$type] as $single_connection) {
                $connect_function = "mysqli_connect";
                $persist          = "";

                if (!empty($single_connection['pconnect'])) {
                    $persist = 'p:';
                }

                $link = "{$type}_link";
                get_execution_time();

                $port = 0;
                if (str_contains($single_connection['hostname'], ':')) {
                    [$hostname, $port] = explode(":", $single_connection['hostname'], 2);
                }

                try {
                    if ($port) {
                        $this->$link = @$connect_function($persist . $hostname, $single_connection['username'], $single_connection['password'], "", (int)$port);
                    } else {
                        $this->$link = @$connect_function($persist . $single_connection['hostname'], $single_connection['username'], $single_connection['password']);
                    }
                } catch (\mysqli_sql_exception $e) {
                    $this->$link = false;
                }

                $time_spent = get_execution_time();
                $this->query_time += $time_spent;

                if ($this->$link) {
                    $this->connections[] = "[" . strtoupper($type) . "] {$single_connection['username']}@{$single_connection['hostname']} (Connected in " . format_time_duration($time_spent) . ")";
                    break;
                } else {
                    $this->connections[] = "<span style=\"color: red\">[FAILED] [" . strtoupper($type) . "] {$single_connection['username']}@{$single_connection['hostname']}</span>";
                }
            }
        }

        if (!array_key_exists('write', $connections)) {
            $this->write_link = &$this->read_link;
        }

        if (!$this->read_link) {
            $this->sql_error(CONNECT_SQL, [
                'error_no' => mysqli_connect_errno(),
                'error'    => mysqli_connect_error(),
                'query'    => 'mysqli_connect()',
            ], __FILE__, __LINE__);
            return false;
        } elseif (!$this->write_link) {
            $this->sql_error(CONNECT_SQL, [
                'error_no' => mysqli_connect_errno(),
                'error'    => mysqli_connect_error(),
                'query'    => 'mysqli_connect()',
            ], __FILE__, __LINE__);
            return false;
        }

        if (!$this->select_db($config['database'])) {
            return false;
        }

        $this->current_link   = &$this->read_link;
        $this->db_initialized = true;

        return $this->read_link;
    }

    // ----------------------------
    // ВЫБОР БД
    // ----------------------------
    function select_db(string $database): bool
    {
        $this->database = $database;

        try {
            $master_success = @mysqli_select_db($this->read_link, $database);
        } catch (\mysqli_sql_exception $e) {
            $master_success = false;
        }

        if (!$master_success) {
            $this->sql_error(CONNECT_SQL, [
                'error_no' => mysqli_errno($this->read_link),
                'error'    => '[READ] Unable to select database "' . htmlspecialchars($database) . '": ' . mysqli_error($this->read_link),
                'query'    => 'mysqli_select_db()',
            ], __FILE__, __LINE__);
        }

        $slave_success = true;
        if ($this->write_link) {
            try {
                $slave_success = @mysqli_select_db($this->write_link, $database);
            } catch (\mysqli_sql_exception $e) {
                $slave_success = false;
            }

            if (!$slave_success) {
                $this->sql_error(CONNECT_SQL, [
                    'error_no' => mysqli_errno($this->write_link),
                    'error'    => '[WRITE] Unable to select slave database "' . htmlspecialchars($database) . '": ' . mysqli_error($this->write_link),
                    'query'    => 'mysqli_select_db()',
                ], __FILE__, __LINE__);
            }
        }

        $success = ($master_success && $slave_success);

        if ($success && $this->db_encoding) {
            @mysqli_set_charset($this->read_link, $this->db_encoding);

            if ($slave_success && count($this->connections) > 1) {
                @mysqli_set_charset($this->write_link, $this->db_encoding);
            }
        }

        return $success;
    }

    // ----------------------------
    // ЗАПРОСЫ
    // ----------------------------
    function sql_query(string $_run_query, int $hide_errors = 0, int $write_query = 0): mysqli_result|bool
    {
        global $db, $mybb;

        $t0 = microtime(true);

        if ($write_query && $this->write_link) {
            $this->current_link = &$this->write_link;
        } else {
            $this->current_link = &$this->read_link;
        }

        try {
            $__return = mysqli_query($this->current_link, $_run_query);
        } catch (mysqli_sql_exception $e) {
            if (!$hide_errors) {
                $this->output_error(MYBB_SQL, [
                    'error_no' => $e->getCode(),
                    'error'    => $e->getMessage(),
                    'query'    => $_run_query,
                ], __FILE__, __LINE__);
            }
            return false;
        }

        if ($this->error_number() && !$hide_errors) {
            $this->error($_run_query);
            return false;
        }

        $query_time = microtime(true) - $t0;
        if ($query_time < 0) $query_time = 0;

        $this->query_time  += $query_time;
        $this->query_count++;
        $this->querylist[] = ['query' => $_run_query, 'time' => $query_time];

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_contains(strtolower($script), 'query_explain.php')) {
            if (!isset($GLOBALS['queries']) || !is_array($GLOBALS['queries'])) {
                $GLOBALS['queries'] = [];
            }
            $GLOBALS['queries'][] = [
                'query_time' => (float)$query_time,
                'query'      => trim($_run_query),
            ];
        }

        return $__return;
    }

    public function sql_query_prepared(string $query, array $params = [], int $hide_errors = 0): object|bool
    {
        $t0 = microtime(true);

        $is_write_query = preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|RENAME|TRUNCATE|LOAD|COPY|GRANT|REVOKE|LOCK|UNLOCK)/i', $query);

        if ($is_write_query && $this->write_link) {
            $this->current_link = $this->write_link;
            $link               = $this->write_link;
        } else {
            $this->current_link = $this->read_link;
            $link               = $this->read_link;
        }

        if (!$link) {
            $this->error("Database connection is not established");
            return false;
        }

        // Форматируем запрос для логов
        $log_query = $query;
        if (!empty($params)) {
            foreach ($params as $param) {
                if (is_null($param)) {
                    $log_query = preg_replace('/\?/', 'NULL', $log_query, 1);
                } elseif (is_int($param) || is_float($param)) {
                    $log_query = preg_replace('/\?/', (string)$param, $log_query, 1);
                } else {
                    $log_query = preg_replace('/\?/', "'" . $this->escape_string((string)$param) . "'", $log_query, 1);
                }
            }
        }

        try {
            $stmt = mysqli_prepare($link, $query);
            if (!$stmt) {
                throw new mysqli_sql_exception("Unable to prepare statement: " . mysqli_error($link), mysqli_errno($link));
            }

            if (!empty($params)) {
                $types       = '';
                $bind_params = [];

                foreach ($params as &$param) {
                    $types .= match(true) {
                        is_int($param)   => 'i',
                        is_float($param) => 'd',
                        is_null($param)  => 's',
                        default          => 's'
                    };
                    $bind_params[] = &$param;
                }

                array_unshift($bind_params, $types);

                if (!call_user_func_array([$stmt, 'bind_param'], $bind_params)) {
                    throw new mysqli_sql_exception("Unable to bind parameters: " . mysqli_stmt_error($stmt), mysqli_stmt_errno($stmt));
                }
            }

            if (!mysqli_stmt_execute($stmt)) {
                throw new mysqli_sql_exception("Unable to execute statement: " . mysqli_stmt_error($stmt), mysqli_stmt_errno($stmt));
            }

            $result = mysqli_stmt_get_result($stmt);

        } catch (mysqli_sql_exception $e) {
            if (!$hide_errors) {
                $this->output_error(MYBB_SQL, [
                    'error_no' => $e->getCode(),
                    'error'    => $e->getMessage(),
                    'query'    => $log_query,
                ], __FILE__, __LINE__);
            }

            $query_time = max(0, microtime(true) - $t0);
            $this->query_time  += $query_time;
            $this->query_count++;
            $this->querylist[] = ['query' => $log_query, 'time' => $query_time];

            return false;
        }

        if ($this->error_number() && !$hide_errors) {
            $this->error($log_query);

            $query_time = max(0, microtime(true) - $t0);
            $this->query_time  += $query_time;
            $this->query_count++;
            $this->querylist[] = ['query' => $log_query, 'time' => $query_time];

            return false;
        }

        $query_time = max(0, microtime(true) - $t0);
        $this->query_time  += $query_time;
        $this->query_count++;
        $this->querylist[] = ['query' => $log_query, 'time' => $query_time];

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_contains(strtolower($script), 'query_explain.php')) {
            if (!isset($GLOBALS['queries']) || !is_array($GLOBALS['queries'])) {
                $GLOBALS['queries'] = [];
            }
            $GLOBALS['queries'][] = [
                'query_time' => (float)$query_time,
                'query'      => trim($log_query),
            ];
        }

        if ($result instanceof mysqli_result) {
            $wrappedResult         = new stdClass();
            $wrappedResult->result = $result;
            $wrappedResult->stmt   = $stmt;
            return $wrappedResult;
        }

        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        return $affected_rows >= 0;
    }

    function write_query(string $query, int $hide_errors = 0): mysqli_result|bool
    {
        return $this->sql_query($query, $hide_errors, 1);
    }

    // ----------------------------
    // РЕЗУЛЬТАТЫ
    // ----------------------------
    function num_rows(object $query): int
    {
        if (property_exists($query, 'result')) {
            return ($query->result instanceof mysqli_result) ? mysqli_num_rows($query->result) : 0;
        }
        return ($query instanceof mysqli_result) ? mysqli_num_rows($query) : 0;
    }

    function fetch_array(object $query, int $resulttype = MYSQLI_ASSOC): ?array
    {
        if (property_exists($query, 'result')) {
            return ($query->result instanceof mysqli_result) ? (mysqli_fetch_array($query->result, $resulttype) ?: null) : null;
        }
        return ($query instanceof mysqli_result) ? (mysqli_fetch_array($query, $resulttype) ?: null) : null;
    }

    function data_seek(object $query, int $row): bool
    {
        if (property_exists($query, 'result')) {
            $query = $query->result;
        }
        return mysqli_data_seek($query, $row);
    }

    function fetch_field(object $query, string $field, int|bool $row = false): mixed
    {
        if (property_exists($query, 'result')) {
            $query = $query->result;
        }
        if ($row !== false) {
            $this->data_seek($query, (int)$row);
        }
        $array = $this->fetch_array($query);
        return $array[$field] ?? null;
    }

    function free_result(object $query): bool
    {
        if (property_exists($query, 'stmt')) {
            if ($query->stmt instanceof mysqli_stmt) {
                $query->stmt->close();
            }
            return true;
        }
        if ($query instanceof mysqli_result) {
            mysqli_free_result($query);
        }
        return true;
    }

    function insert_id(): int
    {
        return mysqli_insert_id($this->current_link);
    }

    function affected_rows(): int
    {
        if (isset($this->current_stmt) && is_object($this->current_stmt)) {
            return mysqli_stmt_affected_rows($this->current_stmt);
        }
        $link = $this->write_link ?? $this->current_link;
        return mysqli_affected_rows($link);
    }

    function num_fields(mysqli_result $query): int
    {
        return mysqli_num_fields($query);
    }

    // ----------------------------
    // ЗАКРЫТИЕ
    // ----------------------------
    function close(): void
    {
        @mysqli_close($this->read_link);
        if ($this->write_link) {
            @mysqli_close($this->write_link);
        }
    }

    // ----------------------------
    // ОШИБКИ
    // ----------------------------
    function error_number(): int
    {
        return $this->current_link
            ? mysqli_errno($this->current_link)
            : mysqli_connect_errno();
    }

    function error_string(): string
    {
        return $this->current_link
            ? mysqli_error($this->current_link)
            : mysqli_connect_error();
    }

    function error(string $string = ""): bool
    {
        if ($this->error_reporting) {
            $error = [
                "error_no" => $this->error_number(),
                "error"    => $this->error_string(),
                "query"    => $string
            ];
            $this->sql_error(MYBB_SQL, $error);
            return true;
        }
        return false;
    }

    function sql_error(int $type, array $message, ?string $file = null, int $line = 0, bool $allow_output = true): bool
    {
        $this->output_error($type, $message, $file ?? '', $line);
        return true;
    }

    function output_error(int $type, array|string $message, string $file, int $line): never
    {
        global $SITENAME, $BASEURL, $charset;

        $title    = "System Error";
        $charset  = $charset ?? 'UTF-8';
        $bbname   = $SITENAME ?? "Ruff Tracker";
        $randomId = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        // Backtrace для лога — ищем реальный caller
        $backtrace   = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $caller_file = $file;
        $caller_line = $line;
        $skip        = ['db_mysqli.php'];
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && !in_array(basename($trace['file']), $skip)) {
                $caller_file = $trace['file'];
                $caller_line = $trace['line'];
                break;
            }
        }

        $currentFile = basename($file);

        // Тип ошибки
        if ($type == MYBB_SQL || $type == CONNECT_SQL) {
            $error_type    = "Database Error";
            $error_icon    = "bi-database-exclamation";
            $error_details = "
                <div class='alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25'>
                    <i class='bi bi-exclamation-triangle-fill me-2'></i>
                    <strong>SQL Error #{$message['error_no']}:</strong> {$message['error']}
                </div>
                <div class='mb-3'>
                    <h5 class='mb-2'>Failed Query:</h5>
                    <div class='p-3 bg-light rounded overflow-auto'>
                        <code>{$message['query']}</code>
                    </div>
                </div>";
        } else {
            $error_type    = "Application Error";
            $error_icon    = "bi-bug-fill";
            $error_message = is_array($message)
                ? "SQL Error #{$message['error_no']}: {$message['error']}"
                : $message;
            $error_details = "
                <div class='alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25'>
                    <i class='bi bi-exclamation-triangle-fill me-2'></i>
                    <strong>Error Message:</strong> {$error_message}
                </div>";
        }

        // Логирование — только если БД инициализирована и это не ошибка подключения
        if (function_exists('write_log') && $this->db_initialized && $type !== CONNECT_SQL) {
            $log_message  = "[" . ($type == MYBB_SQL ? "SQL" : "PHP") . " ERROR]";
            $log_message .= " File: {$caller_file} | Line: {$caller_line}";
            if (is_array($message) && $type == MYBB_SQL) {
                $clean_query  = preg_replace('/\s+/', ' ', trim($message['query']));
                $log_message .= " | Error: {$message['error']} | Query: {$clean_query}";
            } else {
                $log_message .= " | Message: " . (is_array($message) ? json_encode($message) : $message);
            }
            write_log($log_message);
        }

        // Заголовки
        if (!headers_sent()) {
            @header('HTTP/1.1 503 Service Temporarily Unavailable');
            @header('Status: 503 Service Temporarily Unavailable');
            @header('Retry-After: 1800');
            @header("Content-type: text/html; charset={$charset}");
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="{$charset}">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title} | {$bbname}</title>
  <link href="{$BASEURL}/include/templates/default/style/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="{$BASEURL}/include/templates/default/style/bootstrap-icons.css">
  <style>
    :root { --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
    body { background-color: #f8f9fa; font-family: "Segoe UI", system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; background-image: radial-gradient(circle at 10% 20%, rgba(248,249,250,0.9) 0%, rgba(248,249,250,0.8) 90%); }
    .error-card { max-width: 750px; width: 100%; border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(220,53,69,0.2); animation: pulse 2s infinite; }
    .card-header { background: var(--danger-gradient); color: white; padding: 20px; display: flex; align-items: center; gap: 15px; }
    .error-icon { font-size: 2.5rem; animation: float 3s ease-in-out infinite; }
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.02); } 100% { transform: scale(1); } }
    .file-path { background: rgba(13,110,253,0.1); padding: 8px 12px; border-radius: 4px; font-family: monospace; word-break: break-all; }
  </style>
</head>
<body>
  <div class="card error-card">
    <div class="card-header">
      <i class="bi {$error_icon} error-icon"></i>
      <div>
        <h2 class="mb-0">{$error_type} in {$currentFile}</h2>
        <p class="mb-0 opacity-75">{$bbname} System Protection</p>
      </div>
    </div>
    <div class="card-body">
      {$error_details}
      <div class='mb-4'>
        <h5 class='mb-2'>Error Location:</h5>
        <div class='file-path mb-2'>
          <strong>Called from:</strong> {$caller_file}<br>
          <strong>Line:</strong> {$caller_line}
        </div>
        <div class='file-path'>
          <strong>DB Layer:</strong> {$file}<br>
          <strong>Line:</strong> {$line}
        </div>
      </div>
      <div class='d-flex flex-column flex-sm-row gap-3'>
        <button onclick='history.back()' class='btn btn-outline-danger flex-grow-1'>
          <i class='bi bi-arrow-left me-2'></i> Go Back
        </button>
        <a href='/' class='btn btn-danger flex-grow-1'>
          <i class='bi bi-house me-2'></i> Home Page
        </a>
      </div>
      <div class='mt-4 text-center text-muted small'>
        <i class='bi bi-info-circle me-1'></i>
        Error ID: <strong>ERR-{$randomId}</strong> •
        <span id='timestamp'></span>
      </div>
    </div>
  </div>
  <script src='{$BASEURL}/scripts/bootstrap.bundle.min.js'></script>
  <script>document.getElementById('timestamp').textContent = new Date().toLocaleString();</script>
</body>
</html>
HTML;

        exit(1);
    }

    // ----------------------------
    // ESCAPE
    // ----------------------------
    function escape_string(mixed $string): string
    {
        if (is_array($string)) {
            $string = implode(',', $string);
        }
        $string = (string)$string;

        if ($this->db_encoding == 'utf8') {
            $string = validate_utf8_string($string, false);
        } elseif ($this->db_encoding == 'utf8mb4') {
            $string = validate_utf8_string($string);
        }

        return function_exists("mysqli_real_escape_string") && $this->read_link
            ? mysqli_real_escape_string($this->read_link, $string)
            : addslashes($string);
    }

    function escape_string_like(string $string): string
    {
        return $this->escape_string(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $string));
    }

    function sqlesc(mixed $value): string
    {
        if (!is_numeric($value)) {
            $value = '\'' . mysqli_real_escape_string($this->read_link, (string)$value) . '\'';
        }
        return (string)$value;
    }

    function escape_binary(string $string): string
    {
        return "X'" . $this->escape_string(bin2hex($string)) . "'";
    }

    function unescape_binary(string $string): string
    {
        return $string;
    }

    // ----------------------------
    // ТРАНЗАКЦИИ
    // ----------------------------
    public function begin_transaction(int $flags = 0, string $name = ''): bool
    {
        $link = $this->write_link ?? $this->current_link;
        if (!$link) return false;
        try {
            return $name !== ''
                ? mysqli_begin_transaction($link, $flags, $name)
                : mysqli_begin_transaction($link, $flags);
        } catch (\mysqli_sql_exception $e) {
            $this->sql_error(MYBB_SQL, ['error_no' => $e->getCode(), 'error' => $e->getMessage(), 'query' => 'begin_transaction()'], __FILE__, __LINE__);
        }
        return false;
    }

    public function commit(int $flags = 0, string $name = ''): bool
    {
        $link = $this->write_link ?? $this->current_link;
        if (!$link) return false;
        try {
            return $name !== ''
                ? mysqli_commit($link, $flags, $name)
                : mysqli_commit($link);
        } catch (\mysqli_sql_exception $e) {
            $this->sql_error(MYBB_SQL, ['error_no' => $e->getCode(), 'error' => $e->getMessage(), 'query' => 'commit()'], __FILE__, __LINE__);
        }
        return false;
    }

    public function rollback(int $flags = 0, string $name = ''): bool
    {
        $link = $this->write_link ?? $this->current_link;
        if (!$link) return false;
        try {
            return $name !== ''
                ? mysqli_rollback($link, $flags, $name)
                : mysqli_rollback($link);
        } catch (\mysqli_sql_exception $e) {
            $this->sql_error(MYBB_SQL, ['error_no' => $e->getCode(), 'error' => $e->getMessage(), 'query' => 'rollback()'], __FILE__, __LINE__);
        }
        return false;
    }

    // ----------------------------
    // ТАБЛИЦЫ (без префикса)
    // ----------------------------
    function simple_select(string $table, string $fields = "*", string $conditions = "", array $options = []): mysqli_result|bool
    {
        $query = "SELECT " . $fields . " FROM " . $table;

        if ($conditions != "") {
            $query .= " WHERE " . $conditions;
        }
        if (isset($options['group_by'])) {
            $query .= " GROUP BY " . $options['group_by'];
        }
        if (isset($options['order_by'])) {
            $query .= " ORDER BY " . $options['order_by'];
            if (isset($options['order_dir'])) {
                $query .= " " . my_strtoupper($options['order_dir']);
            }
        }
        if (isset($options['limit_start']) && isset($options['limit'])) {
            $query .= " LIMIT " . $options['limit_start'] . ", " . $options['limit'];
        } elseif (isset($options['limit'])) {
            $query .= " LIMIT " . $options['limit'];
        }

        return $this->sql_query($query);
    }

    function insert_query(string $table, array $array): int|false
    {
        global $mybb;

        if (empty($array)) return false;

        foreach ($array as $field => &$value) {
            if (isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field]) {
                if (!str_starts_with($value, 'X')) {
                    $value = $this->escape_binary($value);
                }
            } else {
                $value = $this->quote_val($value);
            }
        }

        $fields = "`" . implode("`,`", array_keys($array)) . "`";
        $values = implode(",", $array);
        $this->write_query("INSERT INTO {$table} ({$fields}) VALUES ({$values})");
        return $this->insert_id();
    }

    function insert_query_multiple(string $table, array $array): void
    {
        global $mybb;

        if (empty($array)) return;

        $fields = "`" . implode("`,`", array_keys($array[0])) . "`";

        $insert_rows = [];
        foreach ($array as $values) {
            foreach ($values as $field => &$value) {
                if (isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field]) {
                    if (!str_starts_with($value, 'X')) {
                        $value = $this->escape_binary($value);
                    }
                } else {
                    $value = $this->quote_val($value);
                }
            }
            $insert_rows[] = "(" . implode(",", $values) . ")";
        }

        $this->write_query("INSERT INTO {$table} ({$fields}) VALUES " . implode(", ", $insert_rows));
    }

    function update_query(string $table, array $array, string $where = "", string $limit = "", bool $no_quote = false): mysqli_result|bool
    {
        global $mybb;

        if (empty($array)) return false;

        $comma = "";
        $query = "";
        $quote = $no_quote ? "" : "'";

        foreach ($array as $field => $value) {
            if (isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field]) {
                if (!str_starts_with($value, 'X')) {
                    $value = $this->escape_binary($value);
                }
                $query .= $comma . "`" . $field . "`={$value}";
            } else {
                $query .= $comma . "`" . $field . "`=" . $this->quote_val($value, $quote);
            }
            $comma = ', ';
        }

        if (!empty($where)) $query .= " WHERE $where";
        if (!empty($limit)) $query .= " LIMIT $limit";

        return $this->write_query("UPDATE {$table} SET $query");
    }

    function delete_query(string $table, string $where = "", string $limit = ""): mysqli_result|bool
    {
        $query = "";
        if (!empty($where)) $query .= " WHERE $where";
        if (!empty($limit)) $query .= " LIMIT $limit";
        return $this->write_query("DELETE FROM {$table} $query");
    }

    function replace_query(string $table, array $replacements = [], string|array $default_field = "", bool $insert_id = true): mysqli_result|bool
    {
        global $mybb;

        if (empty($replacements)) return false;

        $values = '';
        $comma  = '';
        foreach ($replacements as $column => $value) {
            if (isset($mybb->binary_fields[$table][$column]) && $mybb->binary_fields[$table][$column]) {
                if (!str_starts_with($value, 'X')) {
                    $value = $this->escape_binary($value);
                }
                $values .= $comma . "`" . $column . "`=" . $value;
            } else {
                $values .= $comma . "`" . $column . "`=" . $this->quote_val($value);
            }
            $comma = ',';
        }

        return $this->write_query("REPLACE INTO {$table} SET {$values}");
    }

    private function quote_val($value, string $quote = "'"): string|int
    {
        return is_int($value) ? $value : $quote . $value . $quote;
    }

    // ----------------------------
    // ВЕРСИЯ И СТРУКТУРА
    // ----------------------------
    function get_version(): string
    {
        if (isset($this->version)) return $this->version;

        $query = $this->sql_query("SELECT VERSION() as version");
        if (!$query) {
            $this->version = "0.0.0";
            return $this->version;
        }

        $ver     = $this->fetch_array($query);
        $version = $ver['version'] ?? '';

        if ($version) {
            $version       = explode(".", $version, 3);
            $this->version = (int)$version[0] . "." . (int)$version[1] . "." . (int)($version[2] ?? 0);
        } else {
            $this->version = "0.0.0";
        }

        return $this->version;
    }

    function optimize_table(string $table): mysqli_result|bool
    {
        return $this->write_query("OPTIMIZE TABLE " . $table);
    }

    function analyze_table(string $table): mysqli_result|bool
    {
        return $this->write_query("ANALYZE TABLE " . $table);
    }

    function show_create_table(string $table): string
    {
        $query     = $this->write_query("SHOW CREATE TABLE " . $table);
        $structure = $this->fetch_array($query);
        return $structure['Create Table'] ?? '';
    }

    function show_fields_from(string $table): array
    {
        $query      = $this->write_query("SHOW FIELDS FROM " . $table);
        $field_info = [];
        while ($field = $this->fetch_array($query)) {
            $field_info[] = $field;
        }
        return $field_info;
    }

    function list_tables(string $database, string $prefix = ''): array
    {
        if ($prefix) {
            $query = $this->sql_query("SHOW FULL TABLES FROM `$database` WHERE table_type = 'BASE TABLE' AND `Tables_in_$database` LIKE '" . $this->escape_string($prefix) . "%'");
        } else {
            $query = $this->sql_query("SHOW FULL TABLES FROM `$database` WHERE table_type = 'BASE TABLE'");
        }

        $tables = [];
        while ($table = mysqli_fetch_array($query)) {
            $tables[] = $table[0];
        }
        return $tables;
    }

    function table_exists(string $table): bool
    {
        $query = $this->sql_query("SHOW FULL TABLES FROM `" . $this->database . "` WHERE table_type = 'BASE TABLE' AND `Tables_in_" . $this->database . "` = '" . $this->escape_string($table) . "'");
        return $this->num_rows($query) > 0;
    }

    function field_exists(string $field, string $table): bool
    {
        $query = $this->write_query("SHOW COLUMNS FROM {$table} LIKE '$field'");
        return $this->num_rows($query) > 0;
    }

    function index_exists(string $table, string $index): bool
    {
        $query = $this->write_query("SHOW INDEX FROM {$table}");
        while ($ukey = $this->fetch_array($query)) {
            if ($ukey['Key_name'] == $index) return true;
        }
        return false;
    }

    function create_fulltext_index(string $table, string $column, string $name = ""): mysqli_result|bool
    {
        return $this->write_query("ALTER TABLE {$table} ADD FULLTEXT $name ($column)");
    }

    function drop_index(string $table, string $name): mysqli_result|bool
    {
        return $this->write_query("ALTER TABLE {$table} DROP INDEX $name");
    }

    function drop_table(string $table, bool $hard = false, bool $table_prefix = true): mysqli_result|bool
    {
        return $hard
            ? $this->write_query('DROP TABLE ' . $table)
            : $this->write_query('DROP TABLE IF EXISTS ' . $table);
    }

    function rename_table(string $old_table, string $new_table, bool $table_prefix = true): mysqli_result|bool
    {
        return $this->write_query("RENAME TABLE {$old_table} TO {$new_table}");
    }

    function drop_column(string $table, string $column): mysqli_result|bool
    {
        $column = trim($column, '`');
        return $this->write_query("ALTER TABLE {$table} DROP `{$column}`");
    }

    function add_column(string $table, string $column, string $definition): mysqli_result|bool
    {
        $column = trim($column, '`');
        return $this->write_query("ALTER TABLE {$table} ADD `{$column}` {$definition}");
    }

    function modify_column(string $table, string $column, string $new_definition, bool|string $new_not_null = false, bool|string $new_default_value = false): bool
    {
        $column   = trim($column, '`');
        $not_null = match($new_not_null) { 'set' => 'NOT NULL', 'drop' => 'NULL', default => '' };
        $default  = $new_default_value !== false ? "DEFAULT " . $new_default_value : '';
        return (bool)$this->write_query("ALTER TABLE {$table} MODIFY `{$column}` {$new_definition} {$not_null} {$default}");
    }

    function rename_column(string $table, string $old_column, string $new_column, string $new_definition, bool|string $new_not_null = false, bool|string $new_default_value = false): bool
    {
        $old_column = trim($old_column, '`');
        $new_column = trim($new_column, '`');
        $not_null   = match($new_not_null) { 'set' => 'NOT NULL', 'drop' => 'NULL', default => '' };
        $default    = $new_default_value !== false ? "DEFAULT " . $new_default_value : '';
        return (bool)$this->write_query("ALTER TABLE {$table} CHANGE `{$old_column}` `{$new_column}` {$new_definition} {$not_null} {$default}");
    }

    function is_fulltext(string $table, string $index = ""): bool
    {
        $structure = $this->show_create_table($table);
        if ($index != "") return (bool)preg_match("#FULLTEXT KEY (`?)$index(`?)#i", $structure);
        return (bool)preg_match('#FULLTEXT KEY#i', $structure);
    }

    function supports_fulltext(string $table): bool
    {
        $version    = $this->get_version();
        $query      = $this->write_query("SHOW TABLE STATUS LIKE '{$table}'");
        $status     = $this->fetch_array($query);
        $table_type = my_strtoupper($status['Engine'] ?? '');

        return match(true) {
            version_compare($version, '3.23.23', '>=') && in_array($table_type, ['MYISAM', 'ARIA']) => true,
            version_compare($version, '5.6', '>=') && $table_type == 'INNODB'                       => true,
            default => false
        };
    }

    function supports_fulltext_boolean(string $table): bool
    {
        return version_compare($this->get_version(), '4.0.1', '>=') && $this->supports_fulltext($table);
    }

    // ----------------------------
    // ПРОЧЕЕ
    // ----------------------------
    function shutdown_query(string|mysqli_result $query, string $name = ''): void
    {
        global $shutdown_queries;
        if ($name) {
            $shutdown_queries[$name] = $query;
        } else {
            $shutdown_queries[] = $query;
        }
    }

    function fetch_size(string $table = ''): int
    {
        $query = $table != ''
            ? $this->sql_query("SHOW TABLE STATUS LIKE '" . $table . "'")
            : $this->sql_query("SHOW TABLE STATUS");

        $total = 0;
        while ($row = $this->fetch_array($query)) {
            $total += $row['Data_length'] + $row['Index_length'];
        }
        return $total;
    }

    function fetch_db_charsets(): array|false
    {
        if ($this->write_link && version_compare($this->get_version(), "4.1", "<")) {
            return false;
        }
        return [
            'utf8'    => 'UTF-8 Unicode',
            'utf8mb4' => '4-Byte UTF-8 Unicode (requires MySQL 5.5.3 or above)',
            'latin1'  => 'ISO 8859-1 Latin 1',
            'latin2'  => 'ISO 8859-2 Central European',
            'ascii'   => 'US ASCII',
            'cp1251'  => 'Windows Cyrillic',
            'cp1256'  => 'Windows Arabic',
            'cp1257'  => 'Windows Baltic',
            'greek'   => 'ISO 8859-7 Greek',
            'hebrew'  => 'ISO 8859-8 Hebrew',
            'big5'    => 'Big5 Traditional Chinese',
            'gb2312'  => 'GB2312 Simplified Chinese',
            'gbk'     => 'GBK Simplified Chinese',
            'ujis'    => 'EUC-JP Japanese',
            'sjis'    => 'Shift-JIS Japanese',
            'euckr'   => 'EUC-KR Korean',
            'koi8r'   => 'KOI8-R Relcom Russian',
            'koi8u'   => 'KOI8-U Ukrainian',
        ];
    }

    function fetch_charset_collation(string $charset): string|false
    {
        $collations = [
            'utf8'    => 'utf8_general_ci',
            'utf8mb4' => 'utf8mb4_general_ci',
            'latin1'  => 'latin1_swedish_ci',
            'latin2'  => 'latin2_general_ci',
            'ascii'   => 'ascii_general_ci',
            'cp1251'  => 'cp1251_general_ci',
            'cp1256'  => 'cp1256_general_ci',
            'cp1257'  => 'cp1257_general_ci',
            'greek'   => 'greek_general_ci',
            'hebrew'  => 'hebrew_general_ci',
            'big5'    => 'big5_chinese_ci',
            'gb2312'  => 'gb2312_chinese_ci',
            'gbk'     => 'gbk_chinese_ci',
            'ujis'    => 'ujis_japanese_ci',
            'sjis'    => 'sjis_japanese_ci',
            'euckr'   => 'euckr_korean_ci',
            'koi8r'   => 'koi8r_general_ci',
            'koi8u'   => 'koi8u_general_ci',
        ];
        return $collations[$charset] ?? false;
    }

    function build_create_table_collation(): string
    {
        if (!$this->db_encoding) return '';
        $collation = $this->fetch_charset_collation($this->db_encoding);
        if (!$collation) return '';
        return " CHARACTER SET {$this->db_encoding} COLLATE {$collation}";
    }

    /**
     * @deprecated
     */
    function get_execution_time(): ?float
    {
        return get_execution_time();
    }

    /**
     * Compatibility stub — no prefix used
     */
    function set_table_prefix(string $prefix): void
    {
        // Префиксы не используются — метод оставлен для совместимости
    }
}