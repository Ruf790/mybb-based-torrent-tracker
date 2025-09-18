<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */


function my_strtoupper($string)
{
	if(function_exists("mb_strtoupper"))
	{
		$string = mb_strtoupper($string);
	}
	else
	{
		$string = strtoupper($string);
	}

	return $string;
}

function get_execution_time()
{
	static $time_start;

	$time = microtime(true);

	// Just starting timer, init and return
	if(!$time_start)
	{
		$time_start = $time;
		return;
	}
	// Timer has run, return execution time
	else
	{
		$total = $time-$time_start;
		if($total < 0) $total = 0;
		$time_start = 0;
		return $total;
	}
}

function format_time_duration($time)
{
	global $lang;

	if(!is_numeric($time))
	{
		return 'na';
	}

	if(round(1000000 * $time, 2) < 1000)
	{
		$time = number_format(round(1000000 * $time, 2))." μs";
	}
	elseif(round(1000000 * $time, 2) >= 1000 && round(1000000 * $time, 2) < 1000000)
	{
		$time = number_format(round((1000 * $time), 2))." ms";
	}
	else
	{
		$time = round($time, 3)." seconds";
	}

	return $time;
}

function validate_utf8_string($input, $allow_mb4=true, $return=true)
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
				elseif($c > 239)
				{
					$bytes = 4;
				}
				elseif($c > 223)
				{
					$bytes = 3;
				}
				elseif($c > 191)
				{
					$bytes = 2;
				}
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
		if($allow_mb4)
		{
			return $input;
		}
		else
		{
			return preg_replace("#[^\\x00-\\x7F][\\x80-\\xBF]{3,}#", '?', $input);
		}
	}
	else
	{
		if($allow_mb4)
		{
			return true;
		}
		else
		{
			return !preg_match("#[^\\x00-\\x7F][\\x80-\\xBF]{3,}#", $input);
		}
	}
}

define("MYBB_SQL", 20);

class DB_MySQLi implements DB_Base
{
	/**
	 * The title of this layer.
	 *
	 * @var string
	 */
	public $title = "MySQLi";

	/**
	 * The short title of this layer.
	 *
	 * @var string
	 */
	public $short_title = "MySQLi";

	/**
	 * The type of db software being used.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * A count of the number of queries.
	 *
	 * @var int
	 */
	public $query_count = 0;

	/**
	 * A list of the performed queries.
	 *
	 * @var array
	 */
	public $querylist = array();

	/**
	 * 1 if error reporting enabled, 0 if disabled.
	 *
	 * @var boolean
	 */
	public $error_reporting = 1;

	/**
	 * The read database connection resource.
	 *
	 * @var mysqli
	 */
	public $read_link;

	/**
	 * The write database connection resource
	 *
	 * @var mysqli
	 */
	public $write_link;

	/**
	 * Reference to the last database connection resource used.
	 *
	 * @var mysqli
	 */
	public $current_link;

	/**
	 * @var array
	 */
	public $connections = array();

	/**
	 * The database name.
	 *
	 * @var string
	 */
	public $database;

	/**
	 * Explanation of a query.
	 *
	 * @var string
	 */
	public $explain;

	/**
	 * The current version of MySQL.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * The current table type in use (myisam/innodb)
	 *
	 * @var string
	 */
	public $table_type = "myisam";

	/**
	 * The table prefix used for simple select, update, insert and delete queries
	 *
	 * @var string
	 */
	public $table_prefix;

	/**
	 * The extension used to run the SQL database
	 *
	 * @var string
	 */
	public $engine = "mysqli";

	/**
	 * Weather or not this engine can use the search functionality
	 *
	 * @var boolean
	 */
	public $can_search = true;

	/**
	 * The database encoding currently in use (if supported)
	 *
	 * @var string
	 */
	public $db_encoding = "utf8";

	/**
	 * The time spent performing queries
	 *
	 * @var float
	 */
	public $query_time = 0;

	/**
	 * Stores previous run query type: 1 => write; 0 => read
	 *
	 * @var int
	 */
	protected $last_query_type = 0;
	
	public $force_display_errors = false;
	
	public $has_errors = false;
	
	

	/**
	 * Connect to the database server.
	 *
	 * @param array $config Array of DBMS connection details.
	 * @return mysqli The DB connection resource. Returns false on fail or -1 on a db connect failure.
	 */
	function connect($config)
	{
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
		foreach(array('read', 'write') as $type)
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
				if(!empty($single_connection['pconnect']) && version_compare(PHP_VERSION, '5.3.0', '>='))
				{
					$persist = 'p:';
				}

				$link = "{$type}_link";

				get_execution_time();

				// Specified a custom port for this connection?
				$port = 0;
				if(strstr($single_connection['hostname'],':'))
				{
					list($hostname, $port) = explode(":", $single_connection['hostname'], 2);
				}

				if($port)
				{
					$this->$link = @$connect_function($persist.$hostname, $single_connection['username'], $single_connection['password'], "", $port);
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
	 *
	 * @param string $database The database name.
	 * @return boolean True when successfully connected, false if not.
	 */
	function select_db($database)
	{
		$this->database = $database;

		$master_success = @mysqli_select_db($this->read_link, $database) or $this->error("[READ] Unable to select database", $this->read_link);
		if($this->write_link)
		{
			$slave_success = @mysqli_select_db($this->write_link, $database) or $this->error("[WRITE] Unable to select slave database", $this->write_link);

			$success = ($master_success && $slave_success ? true : false);
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














function sql_query($_run_query, $hide_errors = 0, $write_query = 0)
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
    // не логируем сам ts_query_explain.php, чтобы не собирать его EXPLAIN'ы
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (stripos($script, 'ts_query_explain.php') === false) 
	{
        if (!isset($GLOBALS['queries']) || !is_array($GLOBALS['queries'])) 
		{
            $GLOBALS['queries'] = [];
        }
		
        $GLOBALS['queries'][] = [
            'query_time' => (float)$query_time,
            'query'      => trim((string)$_run_query),
        ];
    }

    return $__return;
}









public function sql_query_prepared($query, $params = array(), $hide_errors = 0)
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
                $log_query = preg_replace('/\?/', $param, $log_query, 1);
            } else {
                $log_query = preg_replace('/\?/', "'" . $this->escape_string($param) . "'", $log_query, 1);
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
            $bind_params = array();
            
            foreach ($params as &$param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_null($param)) {
                    $types .= 's';
                    $param = null;
                } else {
                    $types .= 's';
                }
                $bind_params[] = &$param;
            }

            array_unshift($bind_params, $types);
            
            if (!call_user_func_array(array($stmt, 'bind_param'), $bind_params)) {
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
    if (stripos($script, 'ts_query_explain.php') === false) {
        if (!isset($GLOBALS['queries']) || !is_array($GLOBALS['queries'])) {
            $GLOBALS['queries'] = [];
        }
        
        $GLOBALS['queries'][] = [
            'query_time' => (float)$query_time,
            'query'      => trim((string)$log_query),
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




function num_rows($query)
{
    // Если это объект-обертка
    if (is_object($query) && property_exists($query, 'result')) {
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

function fetch_array($query, $resulttype = MYSQLI_ASSOC)
{
    // Если это объект-обертка (SELECT с prepared statement)
    if (is_object($query) && property_exists($query, 'result')) {
        if ($query->result instanceof mysqli_result) {
            return mysqli_fetch_array($query->result, $resulttype);
        }
        return false;
    }

    // Если обычный результат mysqli_query()
    if ($query instanceof mysqli_result) {
        return mysqli_fetch_array($query, $resulttype);
    }

    return false;
}



function fetch_field($query, $field, $row=false)
{
    // Если передан объект-обёртка, берем из него mysqli_result
    if (is_object($query) && property_exists($query, 'result')) {
        $query = $query;
    }

    if ($row !== false) {
        $this->data_seek($query, $row);
    }

    $array = $this->fetch_array($query);
    if ($array !== null) {
        return $array[$field];
    }
    return null;
}



function data_seek($query, $row)
{
    // Если объект-обёртка, берем mysqli_result
    if (is_object($query) && property_exists($query, 'result')) {
        $query = $query->result;
    }

    return mysqli_data_seek($query, $row);
}




function free_result($query)
{
    // Подготовленный запрос
    if (is_object($query) && property_exists($query, 'stmt')) 
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




	
	
	
	function sqlesc($value)
    {
    //if (get_magic_quotes_gpc ())
    //{
      //$value = stripslashes ($value);
    //}

    if (!is_numeric ($value))
    {
      $value = '\'' . mysqli_real_escape_string ($this->read_link,$value) . '\'';
    }

    return $value;
    }
  
  
	

	
	function write_query($query, $hide_errors=0)
	{
		return $this->sql_query($query, $hide_errors, 1);
	}




	/**
	 * Return the last id number of inserted data.
	 *
	 * @return int The id number.
	 */
	function insert_id()
	{
		$id = mysqli_insert_id($this->current_link);
		return $id;
	}

	/**
	 * Close the connection with the DBMS.
	 *
	 */
	function close()
	{
		@mysqli_close($this->read_link);
		if($this->write_link)
		{
			@mysqli_close($this->write_link);
		}
	}

	/**
	 * Return an error number.
	 *
	 * @return int The error number of the current error.
	 */
	function error_number()
	{
		if($this->current_link)
		{
			return mysqli_errno($this->current_link);
		}
		else
		{
			return mysqli_connect_errno();
		}
	}

	/**
	 * Return an error string.
	 *
	 * @return string The explanation for the current error.
	 */
	function error_string()
	{
		if($this->current_link)
		{
			return mysqli_error($this->current_link);
		}
		else
		{
			return mysqli_connect_error();
		}
	}

	/**
	 * Output a database error.
	 *
	 * @param string $string The string to present as an error.
	 * @return bool Whether error reporting is enabled or not
	 */
	function error($string="")
	{
		if($this->error_reporting)
		{
			//if(class_exists("errorHandler"))
			//{
				global $error_handler;

				//if(!is_object($error_handler))
				//{
					//require_once MYBB_ROOT."inc/class_error.php";
					//require_once INC_PATH . '/class_error.php';
					//$error_handler = new errorHandler();
				//}

				$error = array(
					"error_no" => $this->error_number(),
					"error" => $this->error_string(),
					"query" => $string
				);
				//$error_handler->output_error2(MYBB_SQL, $error);
				$this->sql_error(MYBB_SQL, $error);
			//}
			//else
			//{
			//	trigger_error("<strong>[SQL] [".$this->error_number()."] ".$this->error_string()."</strong><br />{$string}", E_USER_ERROR);
			//}

			return true;
		}
		else
		{
			return false;
		}
	}
	
	
	function sql_error($type, $message, $file=null, $line=0, $allow_output=true)
	{
		global $mybb;

		$this->output_error($type, $message, $file, $line);

		return true;
	}
	
	
	
	












	

	
	
	
	

function output_error($type, $message, $file, $line) 
{
    global $SITENAME, $BASEURL, $charset;

    $title = "System Error";
    $charset = $charset ?? 'UTF-8';
    $bbname = $SITENAME ?? "Ruff Tracker";
    $randomId = strtoupper(substr(md5(uniqid('', true)), 0, 8));
	
	
	$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5); // глубина до 5 вызовов
    foreach ($backtrace as $trace) 
	{
    
	if (isset($trace['file']) && strpos($trace['file'], 'db_mysqli.php') === false) 
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
      
      <div class="mb-4">
        <h5 class="mb-2">Error Location:</h5>
        <div class="file-path">
          <strong>File:</strong> {$file}<br>
          <strong>Line:</strong> {$line}
        </div>
      </div>
      
      <div class="d-flex flex-column flex-sm-row gap-3">
        <button onclick="history.back()" class="btn btn-outline-danger flex-grow-1">
          <i class="bi bi-arrow-left me-2"></i> Go Back
        </button>
        <a href="/" class="btn btn-danger flex-grow-1">
          <i class="bi bi-house me-2"></i> Home Page
        </a>
      </div>
      
      <div class="mt-4 text-center text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        Error ID: <strong>ERR-{$randomId}</strong> • 
        <span id="timestamp"></span>
      </div>
    </div>
  </div>

<script src="{$BASEURL}/scripts/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById("timestamp").textContent = new Date().toLocaleString();
  </script>
</body>
</html>
HTML;

    exit(1);
}


	


	
	
	
	
	
	

	/**
	 * Returns the number of affected rows in a query.
	 *
	 * @return int The number of affected rows.
	 */
	function affected_rows()
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
	 *
	 * @param mysqli_result $query The query data.
	 * @return int The number of fields.
	 */
	function num_fields($query)
	{
		return mysqli_num_fields($query);
	}
	
	
	
	
	
	function list_tables($database, $prefix='')
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

		$tables = array();
		while(list($table) = mysqli_fetch_array($query))
		{
			$tables[] = $table;
		}

		return $tables;
	}
	
	
	
	
	
	

	/**
	 * Lists all tables in the database.
	 *
	 * @param string $database The database name.
	 * @param string $prefix Prefix of the table (optional)
	 * @return array The table list.
	 */
	
	/**
	 * Check if a table exists in a database.
	 *
	 * @param string $table The table name.
	 * @return boolean True when exists, false if not.
	 */
	function table_exists($table)
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

		$exists = $this->num_rows($query);
		if($exists > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Check if a field exists in a database.
	 *
	 * @param string $field The field name.
	 * @param string $table The table name.
	 * @return boolean True when exists, false if not.
	 */
	function field_exists($field, $table)
	{
		$query = $this->write_query("
			SHOW COLUMNS
			FROM {$this->table_prefix}$table
			LIKE '$field'
		");
		$exists = $this->num_rows($query);

		if($exists > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Add a shutdown query.
	 *
	 * @param mysqli_result $query The query data.
	 * @param string $name An optional name for the query.
	 */
	function shutdown_query($query, $name="")
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
	 *
	 * @param string $table The table name to be queried.
	 * @param string $fields Comma delimetered list of fields to be selected.
	 * @param string $conditions SQL formatted list of conditions to be matched.
	 * @param array $options List of options: group by, order by, order direction, limit, limit start.
	 * @return mysqli_result The query data.
	 */
	function simple_select($table, $fields="*", $conditions="", $options=array())
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
	 *
	 * @param string $table The table name to perform the query on.
	 * @param array $array An array of fields and their values.
	 * @return int The insert ID if available
	 */
	function insert_query($table, $array)
	{
		global $mybb;

		if(!is_array($array))
		{
			return false;
		}

		foreach($array as $field => $value)
		{
			if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
			{
				if($value[0] != 'X') // Not escaped?
				{
					$value = $this->escape_binary($value);
				}
				
				$array[$field] = $value;
			}
			else
			{
				$array[$field] = $this->quote_val($value);
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
	 *
	 * @param string $table The table name to perform the query on.
	 * @param array $array An array of inserts.
	 * @return void
	 */
	function insert_query_multiple($table, $array)
	{
		global $mybb;

		if(!is_array($array))
		{
			return;
		}
		// Field names
		$fields = array_keys($array[0]);
		$fields = "`".implode("`,`", $fields)."`";

		$insert_rows = array();
		foreach($array as $values)
		{
			foreach($values as $field => $value)
			{
				if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
				{
					if($value[0] != 'X') // Not escaped?
					{
						$value = $this->escape_binary($value);
					}
				
					$values[$field] = $value;
				}
				else
				{
					$values[$field] = $this->quote_val($value);
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
	 *
	 * @param string $table The table name to perform the query on.
	 * @param array $array An array of fields and their values.
	 * @param string $where An optional where clause for the query.
	 * @param string $limit An optional limit clause for the query.
	 * @param boolean $no_quote An option to quote incoming values of the array.
	 * @return mysqli_result The query data.
	 */
	function update_query($table, $array, $where="", $limit="", $no_quote=false)
	{
		global $mybb;

		if(!is_array($array))
		{
			return false;
		}

		$comma = "";
		$query = "";
		$quote = "'";

		if($no_quote == true)
		{
			$quote = "";
		}

		foreach($array as $field => $value)
		{
			if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
			{
				if($value[0] != 'X') // Not escaped?
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
	 * @param int|string $value
	 * @param string $quote
	 *
	 * @return int|string
	 */
	private function quote_val($value, $quote="'")
	{
		if(is_int($value))
		{
			$quoted = $value;
		}
		else
		{
			$quoted = $quote . $value . $quote;
		}

		return $quoted;
	}

	/**
	 * Build a delete query.
	 *
	 * @param string $table The table name to perform the query on.
	 * @param string $where An optional where clause for the query.
	 * @param string $limit An optional limit clause for the query.
	 * @return mysqli_result The query data.
	 */
	function delete_query($table, $where="", $limit="")
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
	 *
	 * @param string $string The string to be escaped.
	 * @return string The escaped string.
	 */

	
	
	function escape_string($string)
{
    // Преобразуем массив в строку
    if (is_array($string)) {
        $string = implode(',', $string);
    }
    // Приводим к строке всё остальное
    if (!is_string($string)) {
        $string = (string)$string;
    }

    if ($this->db_encoding == 'utf8') {
        $string = validate_utf8_string($string, false);
    } elseif ($this->db_encoding == 'utf8mb4') {
        $string = validate_utf8_string($string);
    }

    if (function_exists("mysqli_real_escape_string") && $this->read_link) {
        $string = mysqli_real_escape_string($this->read_link, $string);
    } else {
        $string = addslashes($string);
    }
    return $string;
}
	
	

	
	
	

	

	/**
	 * Escape a string used within a like command.
	 *
	 * @param string $string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string_like($string)
	{
		return $this->escape_string(str_replace(array('\\', '%', '_') , array('\\\\', '\\%' , '\\_') , $string));
	}

	/**
	 * Gets the current version of MySQL.
	 *
	 * @return string Version of MySQL.
	 */
	function get_version()
	{
		if($this->version)
		{
			return $this->version;
		}

		$query = $this->sql_query("SELECT VERSION() as version");
		$ver = $this->fetch_array($query);
		$version = $ver['version'];

		if($version)
		{
			$version = explode(".", $version, 3);
			$this->version = (int)$version[0].".".(int)$version[1].".".(int)$version[2];
		}
		return $this->version;
	}

	/**
	 * Optimizes a specific table.
	 *
	 * @param string $table The name of the table to be optimized.
	 */
	function optimize_table($table)
	{
		$this->write_query("OPTIMIZE TABLE ".$this->table_prefix.$table."");
	}

	/**
	 * Analyzes a specific table.
	 *
	 * @param string $table The name of the table to be analyzed.
	 */
	function analyze_table($table)
	{
		$this->write_query("ANALYZE TABLE ".$this->table_prefix.$table."");
	}

	/**
	 * Show the "create table" command for a specific table.
	 *
	 * @param string $table The name of the table.
	 * @return string The MySQL command to create the specified table.
	 */
	function show_create_table($table)
	{
		$query = $this->write_query("SHOW CREATE TABLE ".$this->table_prefix.$table."");
		$structure = $this->fetch_array($query);

		return $structure['Create Table'];
	}

	/**
	 * Show the "show fields from" command for a specific table.
	 *
	 * @param string $table The name of the table.
	 * @return array Field info for that table
	 */
	function show_fields_from($table)
	{
		$query = $this->write_query("SHOW FIELDS FROM ".$this->table_prefix.$table."");
		$field_info = array();
		while($field = $this->fetch_array($query))
		{
			$field_info[] = $field;
		}
		return $field_info;
	}

	/**
	 * Returns whether or not the table contains a fulltext index.
	 *
	 * @param string $table The name of the table.
	 * @param string $index Optionally specify the name of the index.
	 * @return boolean True or false if the table has a fulltext index or not.
	 */
	function is_fulltext($table, $index="")
	{
		$structure = $this->show_create_table($table);
		if($index != "")
		{
			if(preg_match("#FULLTEXT KEY (`?)$index(`?)#i", $structure))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		if(preg_match('#FULLTEXT KEY#i', $structure))
		{
			return true;
		}
		return false;
	}

	/**
	 * Returns whether or not this database engine supports fulltext indexing.
	 *
	 * @param string $table The table to be checked.
	 * @return boolean True or false if supported or not.
	 */

	function supports_fulltext($table)
	{
		$version = $this->get_version();
		$query = $this->write_query("SHOW TABLE STATUS LIKE '{$this->table_prefix}$table'");
		$status = $this->fetch_array($query);
		$table_type = my_strtoupper($status['Engine']);
		if(version_compare($version, '3.23.23', '>=') && ($table_type == 'MYISAM' || $table_type == 'ARIA'))
		{
			return true;
		}
		elseif(version_compare($version, '5.6', '>=') && $table_type == 'INNODB')
		{
			return true;
		}
		return false;
	}

	/**
	 * Returns whether or not this database engine supports boolean fulltext matching.
	 *
	 * @param string $table The table to be checked.
	 * @return boolean True or false if supported or not.
	 */
	function supports_fulltext_boolean($table)
	{
		$version = $this->get_version();
		$supports_fulltext = $this->supports_fulltext($table);
		if(version_compare($version, '4.0.1', '>=') && $supports_fulltext == true)
		{
			return true;
		}
		return false;
	}

	/**
	 * Checks to see if an index exists on a specified table
	 *
	 * @param string $table The name of the table.
	 * @param string $index The name of the index.
	 * @return bool Returns whether index exists
	 */
	function index_exists($table, $index)
	{
		$index_exists = false;
		$query = $this->write_query("SHOW INDEX FROM {$this->table_prefix}{$table}");
		while($ukey = $this->fetch_array($query))
		{
			if($ukey['Key_name'] == $index)
			{
				$index_exists = true;
				break;
			}
		}

		if($index_exists)
		{
			return true;
		}

		return false;
	}

	/**
	 * Creates a fulltext index on the specified column in the specified table with optional index name.
	 *
	 * @param string $table The name of the table.
	 * @param string $column Name of the column to be indexed.
	 * @param string $name The index name, optional.
	 */
	function create_fulltext_index($table, $column, $name="")
	{
		$this->write_query("ALTER TABLE {$this->table_prefix}$table ADD FULLTEXT $name ($column)");
	}

	/**
	 * Drop an index with the specified name from the specified table
	 *
	 * @param string $table The name of the table.
	 * @param string $name The name of the index.
	 */
	function drop_index($table, $name)
	{
		$this->write_query("ALTER TABLE {$this->table_prefix}$table DROP INDEX $name");
	}

	/**
	 * Drop an table with the specified table
	 *
	 * @param string $table The table to drop
	 * @param boolean $hard hard drop - no checking
	 * @param boolean $table_prefix use table prefix
	 */
	function drop_table($table, $hard=false, $table_prefix=true)
	{
		if($table_prefix == false)
		{
			$table_prefix = "";
		}
		else
		{
			$table_prefix = $this->table_prefix;
		}

		if($hard == false)
		{
			$this->write_query('DROP TABLE IF EXISTS '.$table_prefix.$table);
		}
		else
		{
			$this->write_query('DROP TABLE '.$table_prefix.$table);
		}
	}

	/**
	 * Renames a table
	 *
	 * @param string $old_table The old table name
	 * @param string $new_table the new table name
	 * @param boolean $table_prefix use table prefix
	 * @return mysqli_result
	 */
	function rename_table($old_table, $new_table, $table_prefix=true)
	{
		if($table_prefix == false)
		{
			$table_prefix = "";
		}
		else
		{
			$table_prefix = $this->table_prefix;
		}

		return $this->write_query("RENAME TABLE {$table_prefix}{$old_table} TO {$table_prefix}{$new_table}");
	}

	/**
	 * Replace contents of table with values
	 *
	 * @param string $table The table
	 * @param array $replacements The replacements
	 * @param string|array $default_field The default field(s)
	 * @param boolean $insert_id Whether or not to return an insert id. True by default
	 * @return mysqli_result|bool
	 */
	function replace_query($table, $replacements=array(), $default_field="", $insert_id=true)
	{
		global $mybb;

		$values = '';
		$comma = '';
		foreach($replacements as $column => $value)
		{
			if(isset($mybb->binary_fields[$table][$column]) && $mybb->binary_fields[$table][$column])
			{
				if($value[0] != 'X') // Not escaped?
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
	 *
	 * @param string $table The table
	 * @param string $column The column name
	 * @return mysqli_result
	 */
	function drop_column($table, $column)
	{
		$column = trim($column, '`');

		return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} DROP `{$column}`");
	}

	/**
	 * Adds a column
	 *
	 * @param string $table The table
	 * @param string $column The column name
	 * @param string $definition the new column definition
	 * @return mysqli_result
	 */
	function add_column($table, $column, $definition)
	{
		$column = trim($column, '`');

		return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ADD `{$column}` {$definition}");
	}

	/**
	 * Modifies a column
	 *
	 * @param string $table The table
	 * @param string $column The column name
	 * @param string $new_definition the new column definition
	 * @param boolean|string $new_not_null Whether to "drop" or "set" the NOT NULL attribute (no change if false)
	 * @param boolean|string $new_default_value The new default value, or false to drop the attribute
	 * @return bool Returns true if all queries are executed successfully or false if one of them failed
	 */
	function modify_column($table, $column, $new_definition, $new_not_null=false, $new_default_value=false)
	{
		$column = trim($column, '`');

		if($new_not_null !== false)
		{
			if(strtolower($new_not_null) == "set")
			{
				$not_null = "NOT NULL";
			}
			else
			{
				$not_null = "NULL";
			}
		}
		else
		{
			$not_null = '';
		}

		if($new_default_value !== false)
		{
			$default = "DEFAULT ".$new_default_value;
		}
		else
		{
			$default = '';
		}

		return (bool)$this->write_query("ALTER TABLE {$this->table_prefix}{$table} MODIFY `{$column}` {$new_definition} {$not_null} {$default}");
	}

	/**
	 * Renames a column
	 *
	 * @param string $table The table
	 * @param string $old_column The old column name
	 * @param string $new_column the new column name
	 * @param string $new_definition the new column definition
	 * @param boolean|string $new_not_null Whether to "drop" or "set" the NOT NULL attribute (no change if false)
	 * @param boolean|string $new_default_value The new default value, or false to drop the attribute
	 * @return bool Returns true if all queries are executed successfully
	 */
	function rename_column($table, $old_column, $new_column, $new_definition, $new_not_null=false, $new_default_value=false)
	{
		$old_column = trim($old_column, '`');
		$new_column = trim($new_column, '`');

		if($new_not_null !== false)
		{
			if(strtolower($new_not_null) == "set")
			{
				$not_null = "NOT NULL";
			}
			else
			{
				$not_null = "NULL";
			}
		}
		else
		{
			$not_null = '';
		}

		if($new_default_value !== false)
		{
			$default = "DEFAULT ".$new_default_value;
		}
		else
		{
			$default = '';
		}

		return (bool)$this->write_query("ALTER TABLE {$this->table_prefix}{$table} CHANGE `{$old_column}` `{$new_column}` {$new_definition} {$not_null} {$default}");
	}

	/**
	 * Sets the table prefix used by the simple select, insert, update and delete functions
	 *
	 * @param string $prefix The new table prefix
	 */
	function set_table_prefix($prefix)
	{
		$this->table_prefix = $prefix;
	}

	/**
	 * Fetched the total size of all mysql tables or a specific table
	 *
	 * @param string $table The table (optional)
	 * @return integer the total size of all mysql tables or a specific table
	 */
	function fetch_size($table='')
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
			$total += $table['Data_length']+$table['Index_length'];
		}
		return $total;
	}

	/**
	 * Fetch a list of database character sets this DBMS supports
	 *
	 * @return array|bool Array of supported character sets with array key being the name, array value being display name. False if unsupported
	 */
	function fetch_db_charsets()
	{
		if($this->write_link && version_compare($this->get_version(), "4.1", "<"))
		{
			return false;
		}
		return array(
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
		);
	}

	/**
	 * Fetch a database collation for a particular database character set
	 *
	 * @param string $charset The database character set
	 * @return string|bool The matching database collation, false if unsupported
	 */
	function fetch_charset_collation($charset)
	{
		$collations = array(
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
		);
		if($collations[$charset])
		{
			return $collations[$charset];
		}
		return false;
	}

	/**
	 * Fetch a character set/collation string for use with CREATE TABLE statements. Uses current DB encoding
	 *
	 * @return string The built string, empty if unsupported
	 */
	function build_create_table_collation()
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
	function get_execution_time()
	{
		return get_execution_time();
	}

	/**
	 * Binary database fields require special attention.
	 *
	 * @param string $string Binary value
	 * @return string Encoded binary value
	 */
	function escape_binary($string)
	{
		return "X'".$this->escape_string(bin2hex($string))."'";
	}

	/**
	 * Unescape binary data.
	 *
	 * @param string $string Binary value
	 * @return string Encoded binary value
	 */
	function unescape_binary($string)
	{
		// Nothing to do
		return $string;
	}
}

