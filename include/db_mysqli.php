<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

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

    // Just starting timer, init and return
    if(!$time_start)
    {
        $time_start = $time;
        return null;
    }
    // Timer has run, return execution time
    else
    {
        $total = $time - $time_start;
        if($total < 0) $total = 0;
        $time_start = 0;
        return $total;
    }
}

function format_time_duration(mixed $time): string
{
    global $lang;

    if(!is_numeric($time))
    {
        return 'na';
    }

    $time = (float)$time;
    
    return match(true) {
        round(1000000 * $time, 2) < 1000 => number_format(round(1000000 * $time, 2))." μs",
        round(1000000 * $time, 2) >= 1000 && round(1000000 * $time, 2) < 1000000 => number_format(round((1000 * $time), 2))." ms",
        default => round($time, 3)." seconds"
    };
}

function validate_utf8_string(string $input, bool $allow_mb4 = true, bool $return = true): string|bool
{
    // Valid UTF-8 sequence?
    if(!preg_match('##u', $input))
    {
        $string = '';
        $len = strlen($input);
        for($i = 0; $i < $len; $i++)
        {
            $c = ord($input[$i]);
            if($c > 128)
            {
                if($c > 247 || $c <= 191)
                {
                    if($return)
                    {
                        $string .= '?';
                        continue;
                    }
                    else
                    {
                        return false;
                    }
                }
                
                $bytes = match(true) {
                    $c > 239 => 4,
                    $c > 223 => 3,
                    $c > 191 => 2,
                    default => 0
                };
                
                if(($i + $bytes) > $len)
                {
                    if($return)
                    {
                        $string .= '?';
                        break;
                    }
                    else
                    {
                        return false;
                    }
                }
                $valid = true;
                $multibytes = $input[$i];
                while($bytes > 1)
                {
                    $i++;
                    $b = ord($input[$i]);
                    if($b < 128 || $b > 191)
                    {
                        if($return)
                        {
                            $valid = false;
                            $string .= '?';
                            break;
                        }
                        else
                        {
                            return false;
                        }
                    }
                    else
                    {
                        $multibytes .= $input[$i];
                    }
                    $bytes--;
                }
                if($valid)
                {
                    $string .= $multibytes;
                }
            }
            else
            {
                $string .= $input[$i];
            }
        }
        $input = $string;
    }
    
    if($return)
    {
        return $allow_mb4 
            ? $input 
            : preg_replace("#[^\\x00-\\x7F][\\x80-\\xBF]{3,}#", '?', $input);
    }
    else
    {
        return $allow_mb4 
            ? true 
            : !preg_match("#[^\\x00-\\x7F][\\x80-\\xBF]{3,}#", $input);
    }
}

define("MYBB_SQL", 20);

class DB_MySQLi implements DB_Base
{
    /**
     * The title of this layer.
     */
    public string $title = "MySQLi";

    /**
     * The short title of this layer.
     */
    public string $short_title = "MySQLi";

    /**
     * The type of db software being used.
     */
    public string $type;

    /**
     * A count of the number of queries.
     */
    public int $query_count = 0;

    /**
     * A list of the performed queries.
     */
    public array $querylist = [];

    /**
     * 1 if error reporting enabled, 0 if disabled.
     */
    public bool $error_reporting = true;

    /**
     * The read database connection resource.
     */
    public ?mysqli $read_link = null;

    /**
     * The write database connection resource
     */
    public ?mysqli $write_link = null;

    /**
     * Reference to the last database connection resource used.
     */
    public ?mysqli $current_link = null;

    /**
     * @var array
     */
    public array $connections = [];

    /**
     * The database name.
     */
    public string $database;

    /**
     * Explanation of a query.
     */
    public string $explain;

    /**
     * The current version of MySQL.
     */
    public string $version;

    /**
     * The current table type in use (myisam/innodb)
     */
    public string $table_type = "myisam";

    /**
     * The table prefix used for simple select, update, insert and delete queries
     */
    public string $table_prefix;

    /**
     * The extension used to run the SQL database
     */
    public string $engine = "mysqli";

    /**
     * Weather or not this engine can use the search functionality
     */
    public bool $can_search = true;

    /**
     * The database encoding currently in use (if supported)
     */
    public string $db_encoding = "utf8";

    /**
     * The time spent performing queries
     */
    public float $query_time = 0.0;

    /**
     * Stores previous run query type: 1 => write; 0 => read
     */
    protected int $last_query_type = 0;
    
    public bool $force_display_errors = false;
    
    public bool $has_errors = false;

    /**
     * Connect to the database server.
     *
     * @param array $config Array of DBMS connection details.
     * @return mysqli|false The DB connection resource. Returns false on fail or -1 on a db connect failure.
     */
    function connect(array $config): mysqli|false
    {
        $connections = [];

        // Simple connection to one server
        if(array_key_exists('hostname', $config))
        {
            $connections['read'][] = $config;
        }
        else
        // Connecting to more than one server
        {
            // Specified multiple servers, but no specific read/write servers
            if(!array_key_exists('read', $config))
            {
                foreach($config as $key => $settings)
                {
                    if(is_int($key))
                    {
                        $connections['read'][] = $settings;
                    }
                }
            }
            // Specified both read & write servers
            else
            {
                $connections = $config;
            }
        }

        if(isset($config['encoding']))
        {
            $this->db_encoding = $config['encoding'];
        }

        // Actually connect to the specified servers
        foreach(['read', 'write'] as $type)
        {
            if(!isset($connections[$type]) || !is_array($connections[$type]))
            {
                break;
            }

            if(array_key_exists('hostname', $connections[$type]))
            {
                $details = $connections[$type];
                unset($connections[$type]);
                $connections[$type][] = $details;
            }

            // Shuffle the connections
            shuffle($connections[$type]);

            // Loop-de-loop
            foreach($connections[$type] as $single_connection)
            {
                $connect_function = "mysqli_connect";
                $persist = "";
                if(!empty($single_connection['pconnect']))
                {
                    $persist = 'p:';
                }

                $link = "{$type}_link";

                get_execution_time();

                // Specified a custom port for this connection?
                $port = 0;
                if(str_contains($single_connection['hostname'], ':'))
                {
                    [$hostname, $port] = explode(":", $single_connection['hostname'], 2);
                }

                if($port)
                {
                    $this->$link = @$connect_function($persist.$hostname, $single_connection['username'], $single_connection['password'], "", (int)$port);
                }
                else
                {
                    $this->$link = @$connect_function($persist.$single_connection['hostname'], $single_connection['username'], $single_connection['password']);
                }

                $time_spent = get_execution_time();
                $this->query_time += $time_spent;

                // Successful connection? break down brother!
                if($this->$link)
                {
                    $this->connections[] = "[".strtoupper($type)."] {$single_connection['username']}@{$single_connection['hostname']} (Connected in ".format_time_duration($time_spent).")";
                    break;
                }
                else
                {
                    $this->connections[] = "<span style=\"color: red\">[FAILED] [".strtoupper($type)."] {$single_connection['username']}@{$single_connection['hostname']}</span>";
                }
            }
        }

        // No write server was specified (simple connection or just multiple servers) - mirror write link
        if(!array_key_exists('write', $connections))
        {
            $this->write_link = &$this->read_link;
        }

        // Have no read connection?
        if(!$this->read_link)
        {
            $this->error("[READ] Unable to connect to MySQL server");
            return false;
        }
        // No write?
        else if(!$this->write_link)
        {
            $this->error("[WRITE] Unable to connect to MySQL server");
            return false;
        }

        // Select databases
        if(!$this->select_db($config['database']))
        {
            return -1;
        }

        $this->current_link = &$this->read_link;
        return $this->read_link;
    }

    /**
     * Selects the database to use.
     */
    function select_db(string $database): bool
    {
        $this->database = $database;

        $master_success = @mysqli_select_db($this->read_link, $database) or $this->error("[READ] Unable to select database", $this->read_link);
        if($this->write_link)
        {
            $slave_success = @mysqli_select_db($this->write_link, $database) or $this->error("[WRITE] Unable to select slave database", $this->write_link);
            $success = ($master_success && $slave_success);
        }
        else
        {
            $success = $master_success;
        }

        if($success && $this->db_encoding)
        {
            @mysqli_set_charset($this->read_link, $this->db_encoding);

            if($slave_success && count($this->connections) > 1)
            {
                @mysqli_set_charset($this->write_link, $this->db_encoding);
            }
        }
        return $success;
    }

    function sql_query(string $_run_query, int $hide_errors = 0, int $write_query = 0): mysqli_result|bool
    {
        global $db, $mybb;

        // старт таймера
        $t0 = microtime(true);

        // выбираем соединение
        if ($write_query && $this->write_link) 
        {
            $this->current_link = &$this->write_link;
        } 
        else 
        {
            $this->current_link = &$this->read_link;
        }

        // сам запрос
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

        // ошибка без исключения?
        if ($this->error_number() && !$hide_errors) 
        {
            $this->error($_run_query);
            return false;
        }

        // время запроса + счётчики
        $query_time = microtime(true) - $t0;
        if ($query_time < 0) $query_time = 0;

        $this->query_time  += $query_time;
        $this->query_count++;
        $this->querylist[] = ['query' => $_run_query, 'time' => $query_time];

        // === ЛОГИ ДЛЯ ts_query_explain.php: ВСЕГДА пишем (без DEBUGMODE) ===
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_contains(strtolower($script), 'ts_query_explain.php')) 
        {
            if (!isset($GLOBALS['queries']) || !is_array($GLOBALS['queries'])) 
            {
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
        global $mybb;

        // старт таймера
        $t0 = microtime(true);

        // Определяем тип запроса для выбора соединения
        $is_write_query = preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|RENAME|TRUNCATE|LOAD|COPY|GRANT|REVOKE|LOCK|UNLOCK)/i', $query);
        
        if ($is_write_query && $this->write_link) {
            $this->current_link = $this->write_link;
            $link = $this->write_link;
        } else {
            $this->current_link = $this->read_link;
            $link = $this->read_link;
        }

        // Проверяем, что соединение установлено
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

        // сам запрос
        try {
            $stmt = mysqli_prepare($link, $query);
            if (!$stmt) {
                throw new mysqli_sql_exception("Unable to prepare statement: " . mysqli_error($link), mysqli_errno($link));
            }

            // Если есть параметры → биндим
            if (!empty($params)) {
                $types = '';
                $bind_params = [];
                
                foreach ($params as &$param) {
                    $types .= match(true) {
                        is_int($param) => 'i',
                        is_float($param) => 'd',
                        is_null($param) => 's',
                        default => 's'
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

            // Пытаемся получить результат для SELECT запросов
            $result = mysqli_stmt_get_result($stmt);

        } catch (mysqli_sql_exception $e) {
            if (!$hide_errors) {
                $this->output_error(MYBB_SQL, [
                    'error_no' => $e->getCode(),
                    'error'    => $e->getMessage(),
                    'query'    => $log_query,
                ], __FILE__, __LINE__);
            }
            
            // время запроса + счётчики даже при ошибке
            $query_time = microtime(true) - $t0;
            if ($query_time < 0) $query_time = 0;

            $this->query_time += $query_time;
            $this->query_count++;
            $this->querylist[] = ['query' => $log_query, 'time' => $query_time];
            
            return false;
        }

        // ошибка без исключения?
        if ($this->error_number() && !$hide_errors) {
            $this->error($log_query);
            
            // время запроса + счётчики
            $query_time = microtime(true) - $t0;
            if ($query_time < 0) $query_time = 0;

            $this->query_time += $query_time;
            $this->query_count++;
            $this->querylist[] = ['query' => $log_query, 'time' => $query_time];
            
            return false;
        }

        // время запроса + счётчики
        $query_time = microtime(true) - $t0;
        if ($query_time < 0) $query_time = 0;

        $this->query_time += $query_time;
        $this->query_count++;
        $this->querylist[] = ['query' => $log_query, 'time' => $query_time];

        // === ЛОГИ ДЛЯ ts_query_explain.php: ВСЕГДА пишем (без DEBUGMODE) ===
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_contains(strtolower($script), 'ts_query_explain.php')) {
            if (!isset($GLOBALS['queries']) || !is_array($GLOBALS['queries'])) {
                $GLOBALS['queries'] = [];
            }
            
            $GLOBALS['queries'][] = [
                'query_time' => (float)$query_time,
                'query'      => trim($log_query),
            ];
        }

        if ($result instanceof mysqli_result) {
            // Для SELECT запросов возвращаем объект-обертку
            $wrappedResult = new stdClass();
            $wrappedResult->result = $result;
            $wrappedResult->stmt = $stmt;
            return $wrappedResult;
        }
        
        // Для не-SELECT запросов
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        // Возвращаем true для успешных запросов, false для неудачных
        return $affected_rows >= 0;
    }

function num_rows(object $query): int
{
    // Если это объект-обертка
    if (property_exists($query, 'result')) {
        if ($query->result instanceof mysqli_result) {
            return mysqli_num_rows($query->result);
        }
        return 0;
    }

    // Если обычный mysqli_result
    if ($query instanceof mysqli_result) {
        return mysqli_num_rows($query);
    }

    return 0;
}

    
	
	
function fetch_array(object $query, int $resulttype = MYSQLI_ASSOC): ?array
{
    // Если это объект-обертка (SELECT с prepared statement)
    if (property_exists($query, 'result')) {
        if ($query->result instanceof mysqli_result) {
            return mysqli_fetch_array($query->result, $resulttype) ?: null;
        }
        return null;
    }

    // Если обычный результат mysqli_query()
    if ($query instanceof mysqli_result) {
        return mysqli_fetch_array($query, $resulttype) ?: null;
    }

    return null;
}
	
	
	

function data_seek(object $query, int $row): bool
{
    // Если объект-обёртка, берем mysqli_result
    if (property_exists($query, 'result')) {
        $query = $query->result;
    }

    return mysqli_data_seek($query, $row);
}

	
	
function fetch_field(object $query, string $field, int|bool $row = false): mixed
{
    // Если передан объект-обёртка, берем из него mysqli_result
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
    // Подготовленный запрос
    if (property_exists($query, 'stmt')) 
    {
        if ($query->stmt instanceof mysqli_stmt) 
        {
            $query->stmt->close(); // закрываем stmt
        }
        return true; // не вызываем mysqli_free_result на stdClass
    }

    // Обычный mysqli_result
    if ($query instanceof mysqli_result) 
    {
        mysqli_free_result($query);
    }

    return true;
}
	
	
	

  
    
    function sqlesc(mixed $value): string
    {
        //if (get_magic_quotes_gpc ())
        //{
          //$value = stripslashes ($value);
        //}

        if (!is_numeric($value))
        {
            $value = '\'' . mysqli_real_escape_string($this->read_link, (string)$value) . '\'';
        }

        return (string)$value;
    }
    
    function write_query(string $query, int $hide_errors = 0): mysqli_result|bool
    {
        return $this->sql_query($query, $hide_errors, 1);
    }

    /**
     * Return the last id number of inserted data.
     */
    function insert_id(): int
    {
        return mysqli_insert_id($this->current_link);
    }

    /**
     * Close the connection with the DBMS.
     */
    function close(): void
    {
        @mysqli_close($this->read_link);
        if($this->write_link)
        {
            @mysqli_close($this->write_link);
        }
    }

    /**
     * Return an error number.
     */
    function error_number(): int
    {
        return $this->current_link
            ? mysqli_errno($this->current_link)
            : mysqli_connect_errno();
    }

    /**
     * Return an error string.
     */
    function error_string(): string
    {
        return $this->current_link
            ? mysqli_error($this->current_link)
            : mysqli_connect_error();
    }

    /**
     * Output a database error.
     */
    function error(string $string = ""): bool
    {
        if($this->error_reporting)
        {
            $error = [
                "error_no" => $this->error_number(),
                "error" => $this->error_string(),
                "query" => $string
            ];
            $this->sql_error(MYBB_SQL, $error);

            return true;
        }
        else
        {
            return false;
        }
    }
    
    function sql_error(int $type, array $message, ?string $file = null, int $line = 0, bool $allow_output = true): bool
    {
        global $mybb;

        $this->output_error($type, $message, $file ?? '', $line);

        return true;
    }

    function output_error(int $type, array|string $message, string $file, int $line): never 
    {
        global $SITENAME, $BASEURL, $charset;

        $title = "System Error";
        $charset = $charset ?? 'UTF-8';
        $bbname = $SITENAME ?? "Ruff Tracker";
        $randomId = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach($backtrace as $trace) 
        {
            if (isset($trace['file']) && !str_contains($trace['file'], 'db_mysqli.php')) 
            {
                $file = $trace['file'];
                $line = $trace['line'];
                break;
            }
        }
        $currentFile = basename($file);
        
        // Определяем тип ошибки и иконку
        if ($type == MYBB_SQL) 
        {
            $error_type = "Database Error";
            $error_icon = "bi-database-exclamation";
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
        } 
        else 
        {
            $error_type = "Application Error";
            $error_icon = "bi-bug-fill";
            $error_details = "
                <div class='alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25'>
                    <i class='bi bi-exclamation-triangle-fill me-2'></i>
                    <strong>Error Message:</strong> {$message}
                </div>";
        }

        // Логирование ошибки
        if (function_exists('write_log')) {
            $log_message = "[".($type == MYBB_SQL ? "SQL" : "PHP")." ERROR]";
            $log_message .= " File: {$file} | Line: {$line}";
            if ($type == MYBB_SQL) {
                $log_message .= " | Error: {$message['error']} | Query: {$message['query']}";
            } else {
                $log_message .= " | Message: {$message}";
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

        // HTML вывод
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
    :root {
      --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }
    
    body {
      background-color: #f8f9fa;
      font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
      background-image: radial-gradient(circle at 10% 20%, rgba(248, 249, 250, 0.9) 0%, rgba(248, 249, 250, 0.8) 90%);
    }
    
    .error-card {
      max-width: 750px;
      width: 100%;
      border: none;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(220, 53, 69, 0.2);
      animation: pulse 2s infinite;
    }
    
    .card-header {
      background: var(--danger-gradient);
      color: white;
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .error-icon {
      font-size: 2.5rem;
      animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.02); }
      100% { transform: scale(1); }
    }
    
    .file-path {
      background: rgba(13, 110, 253, 0.1);
      padding: 8px 12px;
      border-radius: 4px;
      font-family: monospace;
      word-break: break-all;
    }
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
        <div class='file-path'>
          <strong>File:</strong> {$file}<br>
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
  <script>
    document.getElementById('timestamp').textContent = new Date().toLocaleString();
  </script>
</body>
</html>
HTML;

        exit(1);
    }

    /**
     * Returns the number of affected rows in a query.
     */
    function affected_rows(): int
    {
        // Если у нас есть активный prepared statement, используем его
        if (isset($this->current_stmt) && is_object($this->current_stmt)) {
            return mysqli_stmt_affected_rows($this->current_stmt);
        }
        
        // Для обычных запросов
        return mysqli_affected_rows($this->current_link);
    }

    /**
     * Return the number of fields.
     */
    function num_fields(mysqli_result $query): int
    {
        return mysqli_num_fields($query);
    }
    
    function list_tables(string $database, string $prefix = ''): array
    {
        if($prefix)
        {
            if(version_compare($this->get_version(), '5.0.2', '>='))
            {
                $query = $this->sql_query("SHOW FULL TABLES FROM `$database` WHERE table_type = 'BASE TABLE' AND `Tables_in_$database` LIKE '".$this->escape_string($prefix)."%'");
            }
            else
            {
                $query = $this->sql_query("SHOW TABLES FROM `$database` LIKE '".$this->escape_string($prefix)."%'");
            }
        }
        else
        {
            if(version_compare($this->get_version(), '5.0.2', '>='))
            {
                $query = $this->sql_query("SHOW FULL TABLES FROM `$database` WHERE table_type = 'BASE TABLE'");
            }
            else
            {
                $query = $this->sql_query("SHOW TABLES FROM `$database`");
            }
        }

        $tables = [];
        while($table = mysqli_fetch_array($query))
        {
            $tables[] = $table[0];
        }

        return $tables;
    }

    /**
     * Check if a table exists in a database.
     */
    function table_exists(string $table): bool
    {
        // Execute on master server to ensure if we've just created a table that we get the correct result
        if(version_compare($this->get_version(), '5.0.2', '>='))
        {
            $query = $this->sql_query("SHOW FULL TABLES FROM `".$this->database."` WHERE table_type = 'BASE TABLE' AND `Tables_in_".$this->database."` = '{$this->table_prefix}$table'");
        }
        else
        {
            $query = $this->sql_query("SHOW TABLES LIKE '{$this->table_prefix}$table'");
        }

        return $this->num_rows($query) > 0;
    }

    /**
     * Check if a field exists in a database.
     */
    function field_exists(string $field, string $table): bool
    {
        $query = $this->write_query("
            SHOW COLUMNS
            FROM {$this->table_prefix}$table
            LIKE '$field'
        ");
        return $this->num_rows($query) > 0;
    }

    /**
     * Add a shutdown query.
     */
function shutdown_query(string|mysqli_result $query, string $name = ''): void
{
    global $shutdown_queries;
    if($name)
    {
        $shutdown_queries[$name] = $query;
    }
    else
    {
        $shutdown_queries[] = $query;
    }
}

    /**
     * Performs a simple select query.
     */
    function simple_select(string $table, string $fields = "*", string $conditions = "", array $options = []): mysqli_result|bool
    {
        $query = "SELECT ".$fields." FROM ".$this->table_prefix.$table;

        if($conditions != "")
        {
            $query .= " WHERE ".$conditions;
        }

        if(isset($options['group_by']))
        {
            $query .= " GROUP BY ".$options['group_by'];
        }

        if(isset($options['order_by']))
        {
            $query .= " ORDER BY ".$options['order_by'];
            if(isset($options['order_dir']))
            {
                $query .= " ".my_strtoupper($options['order_dir']);
            }
        }

        if(isset($options['limit_start']) && isset($options['limit']))
        {
            $query .= " LIMIT ".$options['limit_start'].", ".$options['limit'];
        }
        else if(isset($options['limit']))
        {
            $query .= " LIMIT ".$options['limit'];
        }

        return $this->sql_query($query);
    }

    /**
     * Build an insert query from an array.
     */
    function insert_query(string $table, array $array): int|false
    {
        global $mybb;

        if(empty($array))
        {
            return false;
        }

        foreach($array as $field => &$value)
        {
            if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
            {
                if(!str_starts_with($value, 'X')) // Not escaped?
                {
                    $value = $this->escape_binary($value);
                }
            }
            else
            {
                $value = $this->quote_val($value);
            }
        }

        $fields = "`".implode("`,`", array_keys($array))."`";
        $values = implode(",", $array);
        $this->write_query("
            INSERT
            INTO {$this->table_prefix}{$table} (".$fields.")
            VALUES (".$values.")
        ");
        return $this->insert_id();
    }

    /**
     * Build one query for multiple inserts from a multidimensional array.
     */
    function insert_query_multiple(string $table, array $array): void
    {
        global $mybb;

        if(empty($array))
        {
            return;
        }
        // Field names
        $fields = array_keys($array[0]);
        $fields = "`".implode("`,`", $fields)."`";

        $insert_rows = [];
        foreach($array as $values)
        {
            foreach($values as $field => &$value)
            {
                if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
                {
                    if(!str_starts_with($value, 'X')) // Not escaped?
                    {
                        $value = $this->escape_binary($value);
                    }
                }
                else
                {
                    $value = $this->quote_val($value);
                }
            }
            $insert_rows[] = "(".implode(",", $values).")";
        }
        $insert_rows = implode(", ", $insert_rows);

        $this->write_query("
            INSERT
            INTO {$this->table_prefix}{$table} ({$fields})
            VALUES {$insert_rows}
        ");
    }

    /**
     * Build an update query from an array.
     */
    function update_query(string $table, array $array, string $where = "", string $limit = "", bool $no_quote = false): mysqli_result|bool
    {
        global $mybb;

        if(empty($array))
        {
            return false;
        }

        $comma = "";
        $query = "";
        $quote = "'";

        if($no_quote)
        {
            $quote = "";
        }

        foreach($array as $field => $value)
        {
            if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
            {
                if(!str_starts_with($value, 'X')) // Not escaped?
                {
                    $value = $this->escape_binary($value);
                }
                
                $query .= $comma."`".$field."`={$value}";
            }
            else
            {
                $quoted_value = $this->quote_val($value, $quote);
                $query .= $comma."`".$field."`={$quoted_value}";
            }
            $comma = ', ';
        }

        if(!empty($where))
        {
            $query .= " WHERE $where";
        }

        if(!empty($limit))
        {
            $query .= " LIMIT $limit";
        }

        return $this->write_query("
            UPDATE {$this->table_prefix}$table
            SET $query
        ");
    }

    /**
     * @param mixed $value
     */
    private function quote_val($value, string $quote = "'"): string|int
    {
        return is_int($value)
            ? $value
            : $quote . $value . $quote;
    }

    /**
     * Build a delete query.
     */
    function delete_query(string $table, string $where = "", string $limit = ""): mysqli_result|bool
    {
        $query = "";
        if(!empty($where))
        {
            $query .= " WHERE $where";
        }
        if(!empty($limit))
        {
            $query .= " LIMIT $limit";
        }
        return $this->write_query("DELETE FROM {$this->table_prefix}$table $query");
    }

    /**
     * Escape a string according to the MySQL escape format.
     */
    function escape_string(mixed $string): string
    {
        // Преобразуем массив в строку
        if (is_array($string)) {
            $string = implode(',', $string);
        }
        // Приводим к строке всё остальное
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

    /**
     * Escape a string used within a like command.
     */
    function escape_string_like(string $string): string
    {
        return $this->escape_string(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $string));
    }

    /**
     * Gets the current version of MySQL.
     */
    
function get_version(): string
{
    if (isset($this->version)) {
        return $this->version;
    }

    $query = $this->sql_query("SELECT VERSION() as version");

    if (!$query) {
        $this->version = "0.0.0";
        return $this->version;
    }

    $ver = $this->fetch_array($query);
    $version = $ver['version'] ?? '';

    if ($version) {
        $version = explode(".", $version, 3);
        $this->version = (int)$version[0] . "." . (int)$version[1] . "." . (int)($version[2] ?? 0);
    } else {
        $this->version = "0.0.0";
    }

    return $this->version;
}


    /**
     * Optimizes a specific table.
     */
    function optimize_table(string $table): mysqli_result|bool
    {
        return $this->write_query("OPTIMIZE TABLE ".$this->table_prefix.$table."");
    }

    /**
     * Analyzes a specific table.
     */
    function analyze_table(string $table): mysqli_result|bool
    {
        return $this->write_query("ANALYZE TABLE ".$this->table_prefix.$table."");
    }

    /**
     * Show the "create table" command for a specific table.
     */
    function show_create_table(string $table): string
    {
        $query = $this->write_query("SHOW CREATE TABLE ".$this->table_prefix.$table."");
        $structure = $this->fetch_array($query);

        return $structure['Create Table'] ?? '';
    }

    /**
     * Show the "show fields from" command for a specific table.
     */
    function show_fields_from(string $table): array
    {
        $query = $this->write_query("SHOW FIELDS FROM ".$this->table_prefix.$table."");
        $field_info = [];
        while($field = $this->fetch_array($query))
        {
            $field_info[] = $field;
        }
        return $field_info;
    }

    /**
     * Returns whether or not the table contains a fulltext index.
     */
    function is_fulltext(string $table, string $index = ""): bool
    {
        $structure = $this->show_create_table($table);
        if($index != "")
        {
            return (bool)preg_match("#FULLTEXT KEY (`?)$index(`?)#i", $structure);
        }
        return (bool)preg_match('#FULLTEXT KEY#i', $structure);
    }

    /**
     * Returns whether or not this database engine supports fulltext indexing.
     */
    function supports_fulltext(string $table): bool
    {
        $version = $this->get_version();
        $query = $this->write_query("SHOW TABLE STATUS LIKE '{$this->table_prefix}$table'");
        $status = $this->fetch_array($query);
        $table_type = my_strtoupper($status['Engine'] ?? '');
        
        return match(true) {
            version_compare($version, '3.23.23', '>=') && in_array($table_type, ['MYISAM', 'ARIA']) => true,
            version_compare($version, '5.6', '>=') && $table_type == 'INNODB' => true,
            default => false
        };
    }

    /**
     * Returns whether or not this database engine supports boolean fulltext matching.
     */
    function supports_fulltext_boolean(string $table): bool
    {
        $version = $this->get_version();
        $supports_fulltext = $this->supports_fulltext($table);
        
        return version_compare($version, '4.0.1', '>=') && $supports_fulltext;
    }

    /**
     * Checks to see if an index exists on a specified table
     */
    function index_exists(string $table, string $index): bool
    {
        $query = $this->write_query("SHOW INDEX FROM {$this->table_prefix}{$table}");
        while($ukey = $this->fetch_array($query))
        {
            if($ukey['Key_name'] == $index)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a fulltext index on the specified column in the specified table with optional index name.
     */
    function create_fulltext_index(string $table, string $column, string $name = ""): mysqli_result|bool
    {
        return $this->write_query("ALTER TABLE {$this->table_prefix}$table ADD FULLTEXT $name ($column)");
    }

    /**
     * Drop an index with the specified name from the specified table
     */
    function drop_index(string $table, string $name): mysqli_result|bool
    {
        return $this->write_query("ALTER TABLE {$this->table_prefix}$table DROP INDEX $name");
    }

    /**
     * Drop an table with the specified table
     */
    function drop_table(string $table, bool $hard = false, bool $table_prefix = true): mysqli_result|bool
    {
        $prefix = $table_prefix ? $this->table_prefix : "";

        return $hard
            ? $this->write_query('DROP TABLE '.$prefix.$table)
            : $this->write_query('DROP TABLE IF EXISTS '.$prefix.$table);
    }

    /**
     * Renames a table
     */
    function rename_table(string $old_table, string $new_table, bool $table_prefix = true): mysqli_result|bool
    {
        $prefix = $table_prefix ? $this->table_prefix : "";

        return $this->write_query("RENAME TABLE {$prefix}{$old_table} TO {$prefix}{$new_table}");
    }

    /**
     * Replace contents of table with values
     */
    function replace_query(string $table, array $replacements = [], string|array $default_field = "", bool $insert_id = true): mysqli_result|bool
    {
        global $mybb;

        $values = '';
        $comma = '';
        foreach($replacements as $column => $value)
        {
            if(isset($mybb->binary_fields[$table][$column]) && $mybb->binary_fields[$table][$column])
            {
                if(!str_starts_with($value, 'X')) // Not escaped?
                {
                    $value = $this->escape_binary($value);
                }
                
                $values .= $comma."`".$column."`=".$value;
            }
            else
            {
                $values .= $comma."`".$column."`=".$this->quote_val($value);
            }

            $comma = ',';
        }

        if(empty($replacements))
        {
             return false;
        }

        return $this->write_query("REPLACE INTO {$this->table_prefix}{$table} SET {$values}");
    }

    /**
     * Drops a column
     */
    function drop_column(string $table, string $column): mysqli_result|bool
    {
        $column = trim($column, '`');

        return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} DROP `{$column}`");
    }

    /**
     * Adds a column
     */
    function add_column(string $table, string $column, string $definition): mysqli_result|bool
    {
        $column = trim($column, '`');

        return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ADD `{$column}` {$definition}");
    }

    /**
     * Modifies a column
     */
    function modify_column(string $table, string $column, string $new_definition, bool|string $new_not_null = false, bool|string $new_default_value = false): bool
    {
        $column = trim($column, '`');

        $not_null = match($new_not_null) {
            'set' => 'NOT NULL',
            'drop' => 'NULL',
            default => ''
        };

        $default = $new_default_value !== false ? "DEFAULT ".$new_default_value : '';

        return (bool)$this->write_query("ALTER TABLE {$this->table_prefix}{$table} MODIFY `{$column}` {$new_definition} {$not_null} {$default}");
    }

    /**
     * Renames a column
     */
    function rename_column(string $table, string $old_column, string $new_column, string $new_definition, bool|string $new_not_null = false, bool|string $new_default_value = false): bool
    {
        $old_column = trim($old_column, '`');
        $new_column = trim($new_column, '`');

        $not_null = match($new_not_null) {
            'set' => 'NOT NULL',
            'drop' => 'NULL',
            default => ''
        };

        $default = $new_default_value !== false ? "DEFAULT ".$new_default_value : '';

        return (bool)$this->write_query("ALTER TABLE {$this->table_prefix}{$table} CHANGE `{$old_column}` `{$new_column}` {$new_definition} {$not_null} {$default}");
    }

    /**
     * Sets the table prefix used by the simple select, insert, update and delete functions
     */
    function set_table_prefix(string $prefix): void
    {
        $this->table_prefix = $prefix;
    }

    /**
     * Fetched the total size of all mysql tables or a specific table
     */
    function fetch_size(string $table = ''): int
    {
        if($table != '')
        {
            $query = $this->query("SHOW TABLE STATUS LIKE '".$this->table_prefix.$table."'");
        }
        else
        {
            $query = $this->query("SHOW TABLE STATUS");
        }
        $total = 0;
        while($table = $this->fetch_array($query))
        {
            $total += $table['Data_length'] + $table['Index_length'];
        }
        return $total;
    }

    /**
     * Fetch a list of database character sets this DBMS supports
     */
    function fetch_db_charsets(): array|false
    {
        if($this->write_link && version_compare($this->get_version(), "4.1", "<"))
        {
            return false;
        }
        return [
            'big5' => 'Big5 Traditional Chinese',
            'dec8' => 'DEC West European',
            'cp850' => 'DOS West European',
            'hp8' => 'HP West European',
            'koi8r' => 'KOI8-R Relcom Russian',
            'latin1' => 'ISO 8859-1 Latin 1',
            'latin2' => 'ISO 8859-2 Central European',
            'swe7' => '7bit Swedish',
            'ascii' => 'US ASCII',
            'ujis' => 'EUC-JP Japanese',
            'sjis' => 'Shift-JIS Japanese',
            'hebrew' => 'ISO 8859-8 Hebrew',
            'tis620' => 'TIS620 Thai',
            'euckr' => 'EUC-KR Korean',
            'koi8u' => 'KOI8-U Ukrainian',
            'gb2312' => 'GB2312 Simplified Chinese',
            'greek' => 'ISO 8859-7 Greek',
            'cp1250' => 'Windows Central European',
            'gbk' => 'GBK Simplified Chinese',
            'latin5' => 'ISO 8859-9 Turkish',
            'armscii8' => 'ARMSCII-8 Armenian',
            'utf8' => 'UTF-8 Unicode',
            'utf8mb4' => '4-Byte UTF-8 Unicode (requires MySQL 5.5.3 or above)',
            'ucs2' => 'UCS-2 Unicode',
            'cp866' => 'DOS Russian',
            'keybcs2' => 'DOS Kamenicky Czech-Slovak',
            'macce' => 'Mac Central European',
            'macroman' => 'Mac West European',
            'cp852' => 'DOS Central European',
            'latin7' => 'ISO 8859-13 Baltic',
            'cp1251' => 'Windows Cyrillic',
            'cp1256' => 'Windows Arabic',
            'cp1257' => 'Windows Baltic',
            'geostd8' => 'GEOSTD8 Georgian',
            'cp932' => 'SJIS for Windows Japanese',
            'eucjpms' => 'UJIS for Windows Japanese',
        ];
    }

    /**
     * Fetch a database collation for a particular database character set
     */
    function fetch_charset_collation(string $charset): string|false
    {
        $collations = [
            'big5' => 'big5_chinese_ci',
            'dec8' => 'dec8_swedish_ci',
            'cp850' => 'cp850_general_ci',
            'hp8' => 'hp8_english_ci',
            'koi8r' => 'koi8r_general_ci',
            'latin1' => 'latin1_swedish_ci',
            'latin2' => 'latin2_general_ci',
            'swe7' => 'swe7_swedish_ci',
            'ascii' => 'ascii_general_ci',
            'ujis' => 'ujis_japanese_ci',
            'sjis' => 'sjis_japanese_ci',
            'hebrew' => 'hebrew_general_ci',
            'tis620' => 'tis620_thai_ci',
            'euckr' => 'euckr_korean_ci',
            'koi8u' => 'koi8u_general_ci',
            'gb2312' => 'gb2312_chinese_ci',
            'greek' => 'greek_general_ci',
            'cp1250' => 'cp1250_general_ci',
            'gbk' => 'gbk_chinese_ci',
            'latin5' => 'latin5_turkish_ci',
            'armscii8' => 'armscii8_general_ci',
            'utf8' => 'utf8_general_ci',
            'utf8mb4' => 'utf8mb4_general_ci',
            'ucs2' => 'ucs2_general_ci',
            'cp866' => 'cp866_general_ci',
            'keybcs2' => 'keybcs2_general_ci',
            'macce' => 'macce_general_ci',
            'macroman' => 'macroman_general_ci',
            'cp852' => 'cp852_general_ci',
            'latin7' => 'latin7_general_ci',
            'cp1251' => 'cp1251_general_ci',
            'cp1256' => 'cp1256_general_ci',
            'cp1257' => 'cp1257_general_ci',
            'geostd8' => 'geostd8_general_ci',
            'cp932' => 'cp932_japanese_ci',
            'eucjpms' => 'eucjpms_japanese_ci',
        ];
        return $collations[$charset] ?? false;
    }

    /**
     * Fetch a character set/collation string for use with CREATE TABLE statements. Uses current DB encoding
     */
    function build_create_table_collation(): string
    {
        if(!$this->db_encoding)
        {
            return '';
        }

        $collation = $this->fetch_charset_collation($this->db_encoding);
        if(!$collation)
        {
            return '';
        }
        return " CHARACTER SET {$this->db_encoding} COLLATE {$collation}";
    }

    /**
     * Time how long it takes for a particular piece of code to run. Place calls above & below the block of code.
     *
     * @deprecated
     */
    function get_execution_time(): ?float
    {
        return get_execution_time();
    }

    /**
     * Binary database fields require special attention.
     */
    function escape_binary(string $string): string
    {
        return "X'".$this->escape_string(bin2hex($string))."'";
    }

    /**
     * Unescape binary data.
     */
    function unescape_binary(string $string): string
    {
        // Nothing to do
        return $string;
    }
}