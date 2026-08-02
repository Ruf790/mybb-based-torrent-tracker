<?php


declare(strict_types=1);


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

    /**
     * Реальное число affected_rows последнего write-запроса, сохранённое
     * сразу внутри sql_query_prepared() (пока значение точно верное), а не
     * читаемое заново из состояния соединения при отдельном последующем
     * вызове affected_rows() - такое отложенное чтение оказалось ненадёжным.
     */
    private int $last_affected_rows = 0;

    public array  $connections = [];
    public string $database;
    public string $explain;
    public string $version;
    public string $table_type    = "myisam";
    public string $engine        = "mysqli";
    public bool   $can_search    = true;
    public string $db_encoding   = "utf8mb4";
    public float  $query_time    = 0.0;
    public bool   $force_display_errors = false;
    public bool   $has_errors    = false;
    public bool   $db_initialized = false;

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

        if ($write_query) {
            $this->last_affected_rows = mysqli_affected_rows($this->current_link);
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
	
	
	
	
	
	
	
    /**
     * Общий хвост статистики/логирования для sql_query_prepared() - вынесен
     * в отдельный метод, чтобы не дублировать один и тот же блок на всех
     * трёх return-путях (успех / mysqli-исключение / error_number()).
     * Заодно кэширует проверку "мы внутри query_explain.php?" через static -
     * $_SERVER['SCRIPT_NAME'] не меняется в течение запроса, пересчитывать
     * strtolower()/str_contains() на каждый вызов SQL смысла нет.
     */
    private function record_query_stats(string $log_query, float $t0): float
    {
        $query_time = max(0, microtime(true) - $t0);
        $this->query_time  += $query_time;
        $this->query_count++;
        $this->querylist[] = ['query' => $log_query, 'time' => $query_time];

        static $isQueryExplain = null;
        if ($isQueryExplain === null) {
            $isQueryExplain = str_contains(strtolower($_SERVER['SCRIPT_NAME'] ?? ''), 'query_explain.php');
        }

        if (!$isQueryExplain) {
            if (!isset($GLOBALS['queries']) || !is_array($GLOBALS['queries'])) {
                $GLOBALS['queries'] = [];
            }
            $GLOBALS['queries'][] = [
                'query_time' => (float)$query_time,
                'query'      => trim($log_query),
            ];
        }

        return $query_time;
    }

    public function sql_query_prepared(string $query, array $params = [], int $hide_errors = 0): object|bool
    {
        $t0 = microtime(true);

        $is_write_query = preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|RENAME|TRUNCATE|LOAD|COPY|GRANT|REVOKE|LOCK|UNLOCK|OPTIMIZE|ANALYZE)/i', $query);

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

        // Форматируем запрос для логов - один проход через explode()/implode()
        // вместо N отдельных preg_replace() (по одному на каждый '?'), что
        // особенно заметно на batch-запросах с большим числом плейсхолдеров
        // (массовое удаление, IN (?,?,?...) и т.п.).
        if ($params) {
            $parts     = explode('?', $query);
            $log_query = array_shift($parts);
            foreach ($params as $i => $param) {
                $log_query .= match(true) {
                    is_null($param)                    => 'NULL',
                    is_int($param) || is_float($param)  => (string)$param,
                    default                             => "'" . $this->escape_string((string)$param) . "'",
                };
                $log_query .= $parts[$i] ?? '';
            }
        } else {
            $log_query = $query;
        }

        $stmt = null;

        try {
            // Быстрый путь: запрос без параметров не нуждается в PREPARE -
            // связывать нечего, а PREPARE это лишний round-trip к серверу
            // по сравнению с обычным mysqli_query(). Затрагивает большинство
            // статических запросов (SELECT VERSION(), SHOW STATUS и т.п.).
            if (!$params) {
                $result = mysqli_query($link, $query);
            } else {
                $stmt = mysqli_prepare($link, $query);
                if (!$stmt) {
                    throw new mysqli_sql_exception("Unable to prepare statement: " . mysqli_error($link), mysqli_errno($link));
                }

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
                unset($param);

                if (!mysqli_stmt_bind_param($stmt, $types, ...$bind_params)) {
                    throw new mysqli_sql_exception("Unable to bind parameters: " . mysqli_stmt_error($stmt), mysqli_stmt_errno($stmt));
                }

                if (!mysqli_stmt_execute($stmt)) {
                    throw new mysqli_sql_exception("Unable to execute statement: " . mysqli_stmt_error($stmt), mysqli_stmt_errno($stmt));
                }

                // Для write-запросов (DELETE/UPDATE/INSERT/...) набора строк
                // нет вообще - вызывать get_result() тут бессмысленно и
                // рискованно (может помешать корректному чтению
                // affected_rows ниже). Просто оставляем $result = false,
                // ветка ниже (не instanceof mysqli_result) отработает как
                // обычный write-путь через mysqli_stmt_affected_rows().
                $result = $is_write_query ? false : mysqli_stmt_get_result($stmt);
            }

        } catch (mysqli_sql_exception $e) {
            if (!$hide_errors) {
                $this->output_error(MYBB_SQL, [
                    'error_no' => $e->getCode(),
                    'error'    => $e->getMessage(),
                    'query'    => $log_query,
                ], __FILE__, __LINE__);
            }

            $this->record_query_stats($log_query, $t0);
            return false;
        }

        if ($this->error_number() && !$hide_errors) {
            $this->error($log_query);
            $this->record_query_stats($log_query, $t0);
            return false;
        }

        $this->record_query_stats($log_query, $t0);

        if ($result instanceof mysqli_result) {
            if ($stmt === null) {
                // Быстрый путь без PREPARE - но обёртку {result, stmt} всё
                // равно строим (stmt=null), а не возвращаем сырой результат.
                // Часть кода в проекте обращается к ->result/->stmt напрямую,
                // минуя fetch_array(), и ожидает, что обёртка есть всегда
                // (см. seedbonus.php/threadviews.php/optimizedb.php).
                $wrappedResult         = new stdClass();
                $wrappedResult->result = $result;
                $wrappedResult->stmt   = null;
                return $wrappedResult;
            }
            $wrappedResult         = new stdClass();
            $wrappedResult->result = $result;
            $wrappedResult->stmt   = $stmt;
            return $wrappedResult;
        }

        if ($stmt === null) {
            // Write-запрос без параметров и без PREPARE (например TRUNCATE)
            $this->last_affected_rows = mysqli_affected_rows($link);
            return $this->last_affected_rows >= 0;
        }

        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        $this->last_affected_rows = $affected_rows;

        return $affected_rows >= 0;
    }

    function write_query(string $query, int $hide_errors = 0): object|bool
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
        return $this->last_affected_rows;
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
        global $SITENAME, $BASEURL, $charset, $usergroups, $CURUSER;

        $title    = "System Error";
        $charset  = $charset ?? 'UTF-8';
        $bbname   = $SITENAME;
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
            $log_message .= " | Error ID: ERR-{$randomId}";
            write_log($log_message);
        } else {
            // Fallback: ошибки подключения (CONNECT_SQL) или ошибки до инициализации БД
            // не могут писаться через write_log() (она сама зависит от БД), поэтому
            // пишем напрямую в файл error_logs/connect_errors.log — иначе Error ID,
            // показанный гостю на экране, невозможно найти ни в одном логе.
            $fallback_message  = "[" . ($type == CONNECT_SQL ? "CONNECT" : ($type == MYBB_SQL ? "SQL" : "PHP")) . " ERROR]";
            $fallback_message .= " File: {$caller_file} | Line: {$caller_line}";
            if (is_array($message)) {
                $error_no      = $message['error_no'] ?? '';
                $error_text    = $message['error'] ?? '';
                $fallback_message .= " | Error #{$error_no}: {$error_text}";
                if (!empty($message['query'])) {
                    $clean_query       = preg_replace('/\s+/', ' ', trim((string)$message['query']));
                    $fallback_message .= " | Query: {$clean_query}";
                }
            } else {
                $fallback_message .= " | Message: {$message}";
            }
            $fallback_message .= " | Error ID: ERR-{$randomId}";
            $fallback_message .= " | Timestamp: " . date('d-m-Y H:i:s');

            $root   = rtrim(dirname(__DIR__), '/\\');
            $logDir = "{$root}/error_logs";

            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }

            $logFile = "{$logDir}/connect_errors.log";
            @file_put_contents($logFile, $fallback_message . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        // Определяем, можно ли показывать подробности (запрос, пути, текст
        // ошибки MySQL) — это должны видеть только стафф, не гости/юзеры.
        // Обёрнуто в try/catch: сама проверка не должна уметь упасть.
        $isStaff = false;
        try {
            if (function_exists('is_staff_error_viewer')) {
                $isStaff = is_staff_error_viewer();
            } elseif (!empty($usergroups) && is_array($usergroups) && function_exists('is_mod')) {
                $isStaff = is_mod($usergroups);
            } elseif (!empty($CURUSER['id']) && !empty($usergroups['cansettingspanel'])) {
                $isStaff = true;
            }
        } catch (\Throwable) {
            $isStaff = false;
        }

        if (!$isStaff) {
            // Гость/обычный юзер — только факт ошибки и ID для поддержки.
            $error_type    = "System Error";
            $error_icon    = "bi-tools";
            $error_details = "
                <div class='alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25'>
                    <i class='bi bi-exclamation-triangle-fill me-2'></i>
                    Something went wrong on our end. Our team has already been notified.
                </div>";
        }

        // Заголовки
        if (!headers_sent()) {
            @header('HTTP/1.1 503 Service Temporarily Unavailable');
            @header('Status: 503 Service Temporarily Unavailable');
            @header('Retry-After: 1800');
            @header("Content-type: text/html; charset={$charset}");
        }

        $locationBlock = $isStaff ? "
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
      </div>" : "";

        $headerTitle = $isStaff ? "{$error_type} in {$currentFile}" : $error_type;

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
        <h2 class="mb-0">{$headerTitle}</h2>
        <p class="mb-0 opacity-75">{$bbname} System Protection</p>
      </div>
    </div>
    <div class="card-body">
      {$error_details}
      {$locationBlock}
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
    public function begin_transaction(int $flags = 0, string $name = '', bool $hide_errors = false): bool
    {
        $link = $this->write_link ?? $this->current_link;
        if (!$link) return false;
        try {
            return $name !== ''
                ? mysqli_begin_transaction($link, $flags, $name)
                : mysqli_begin_transaction($link, $flags);
        } catch (\mysqli_sql_exception $e) {
            if (!$hide_errors) {
                $this->sql_error(MYBB_SQL, ['error_no' => $e->getCode(), 'error' => $e->getMessage(), 'query' => 'begin_transaction()'], __FILE__, __LINE__);
            }
        }
        return false;
    }

    public function commit(int $flags = 0, string $name = '', bool $hide_errors = false): bool
    {
        $link = $this->write_link ?? $this->current_link;
        if (!$link) return false;
        try {
            return $name !== ''
                ? mysqli_commit($link, $flags, $name)
                : mysqli_commit($link);
        } catch (\mysqli_sql_exception $e) {
            if (!$hide_errors) {
                $this->sql_error(MYBB_SQL, ['error_no' => $e->getCode(), 'error' => $e->getMessage(), 'query' => 'commit()'], __FILE__, __LINE__);
            }
        }
        return false;
    }

    public function rollback(int $flags = 0, string $name = '', bool $hide_errors = false): bool
    {
        $link = $this->write_link ?? $this->current_link;
        if (!$link) return false;
        try {
            return $name !== ''
                ? mysqli_rollback($link, $flags, $name)
                : mysqli_rollback($link);
        } catch (\mysqli_sql_exception $e) {
            if (!$hide_errors) {
                $this->sql_error(MYBB_SQL, ['error_no' => $e->getCode(), 'error' => $e->getMessage(), 'query' => 'rollback()'], __FILE__, __LINE__);
            }
        }
        return false;
    }

    // ----------------------------
    // ВЕРСИЯ И СТРУКТУРА
    // ----------------------------
    function get_version(): string
    {
        if (isset($this->version)) return $this->version;

        $query = $this->sql_query_prepared("SELECT VERSION() as version");
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

    function optimize_table(string $table): object|bool
    {
        return $this->sql_query_prepared("OPTIMIZE TABLE " . $table);
    }

    function analyze_table(string $table): object|bool
    {
        return $this->sql_query_prepared("ANALYZE TABLE " . $table);
    }

    function show_create_table(string $table): string
    {
        $query     = $this->sql_query_prepared("SHOW CREATE TABLE " . $table);
        $structure = $this->fetch_array($query);
        return $structure['Create Table'] ?? '';
    }

    function show_fields_from(string $table): array
    {
        // information_schema — обычная таблица, не SHOW-псевдокоманда, поэтому
        // полностью совместима с prepared-протоколом без всяких оговорок.
        $query = $this->sql_query_prepared(
            "SELECT 
                COLUMN_NAME    AS `Field`,
                COLUMN_TYPE    AS `Type`,
                IS_NULLABLE    AS `Null`,
                COLUMN_KEY     AS `Key`,
                COLUMN_DEFAULT AS `Default`,
                EXTRA          AS `Extra`
             FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME   = ?
             ORDER BY ORDINAL_POSITION",
            [$table]
        );
        $field_info = [];
        while ($query && ($field = $this->fetch_array($query))) {
            $field_info[] = $field;
        }
        return $field_info;
    }

    function list_tables(string $database): array
    {
        $query = $this->sql_query_prepared(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
            [$database]
        );

        $tables = [];
        while ($query && ($table = $this->fetch_array($query, MYSQLI_NUM))) {
            $tables[] = $table[0];
        }
        return $tables;
    }

    function table_exists(string $table): bool
    {
        $query = $this->sql_query_prepared(
            "SELECT 1 FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME   = ? 
             LIMIT 1",
            [$table]
        );
        return $query ? $this->num_rows($query) > 0 : false;
    }

    function field_exists(string $field, string $table): bool
    {
        $query = $this->sql_query_prepared(
            "SELECT 1 FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME   = ? 
               AND COLUMN_NAME  = ? 
             LIMIT 1",
            [$table, $field]
        );
        return $query ? $this->num_rows($query) > 0 : false;
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
        $query      = $this->sql_query_prepared(
            "SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = ?
             LIMIT 1",
            [$table]
        );
        $status     = $query ? $this->fetch_array($query) : null;
        $table_type = mb_strtoupper($status['ENGINE'] ?? '');

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
        if ($table !== '') {
            $query = $this->sql_query_prepared(
                "SELECT DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            );
        } else {
            $query = $this->sql_query_prepared(
                "SELECT DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()"
            );
        }

        $total = 0;
        while ($query && ($row = $this->fetch_array($query))) {
            $total += $row['DATA_LENGTH'] + $row['INDEX_LENGTH'];
        }
        return $total;
    }

    /**
     * @deprecated
     */
    function get_execution_time(): ?float
    {
        return get_execution_time();
    }

}