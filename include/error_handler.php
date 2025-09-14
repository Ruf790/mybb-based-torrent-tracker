<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

@set_error_handler("GlobalErrorHandler");
register_shutdown_function("FatalErrorHandler");




function GlobalErrorHandler($errno, $errstr, $errfile, $errline)
{
    // не логируем подавленные через @ ошибки
    if (!(error_reporting() & $errno)) 
	{
        return true;
    }

    $ts = date('d-m-Y H:i:s');

    $host   = $_SERVER['HTTP_HOST']        ?? '-';
    $uri    = $_SERVER['REQUEST_URI']      ?? '-';
    $ip     = $_SERVER['REMOTE_ADDR']      ?? '-';
    $ua     = $_SERVER['HTTP_USER_AGENT']  ?? '-';
    $ref    = $_SERVER['HTTP_REFERER']     ?? '-';

    $script = basename(
        $_SERVER['SCRIPT_NAME']
        ?? $_SERVER['PHP_SELF']
        ?? (PHP_SAPI === 'cli' ? ($_SERVER['argv'][0] ?? 'cli') : 'unknown.php')
    );

    $Message = "\r\n------------------------------------------\r\n"
             . "$ts\r\n[$errno] $errstr\r\n"
             . "PHP Error on line " . (int)$errline . " in file $errfile\r\n"
             . "SCRIPT: $script\r\n"
             . "HOST: $host\r\nURI: $uri\r\nIP: $ip\r\nUA: $ua\r\nREF: $ref\r\n"
             . "MEM: " . number_format(memory_get_usage(true)) . " bytes\r\n"
             . PHP_VERSION . " " . PHP_OS . "\r\n"
             . "------------------------------------------";

   
    $root   = rtrim(dirname(__DIR__), '/\\');
    $logDir = $root . '/error_logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }

    $logFile = $logDir . '/' . $script . '.log';

    @file_put_contents($logFile, $Message, FILE_APPEND | LOCK_EX);

    return true; // чтобы не срабатывал стандартный обработчик
}




function FatalErrorHandler()
{
    
	global $BASEURL;
	$error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) 
	{
        $errno = $error['type'];
        $errstr = $error['message'];
        $errfile = $error['file'];
        $errline = $error['line'];
        
        $BASEURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $title = "Fatal Error";
        
        if (!headers_sent()) 
		{
            @header('HTTP/1.1 500 Internal Server Error');
            @header("Content-type: text/html; charset=UTF-8");
        }
        
        $error_message = "File: <strong>{$errfile}</strong><br>Line: <strong>{$errline}</strong><br>Message: <strong>{$errstr}</strong>";

        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
  <title>{$title}</title>
  <link href="{$BASEURL}/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
  <link href="{$BASEURL}/include/templates/default/style/errorss.css" rel="stylesheet">
  <link href="{$BASEURL}/include/templates/default/style/bootstrap.min.css" rel="stylesheet">
  
  

  <style>
    .card-header22 {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 20px;
      background-color: #dc3545;
      color: white;
      border-radius: 5px 5px 0 0 !important;
    }
    .error-icon {
      font-size: 2rem;
    }
    .error-card {
      border: none;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      border-radius: 5px;
      overflow: hidden;
      margin-top: 2rem;
    }
    .card-body {
      padding: 25px;
    }
  </style>
</head>
<body>
  <div class="container mt-3">
    <div class="card error-card">
      <div class="card-header22">
        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
        <div>
          <h2 class="mb-0">Fatal Error</h2>
          <p class="mb-0 opacity-75">A critical problem occurred while processing your request</p>
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-danger" role="alert">
          {$error_message}
        </div>

        <div class="d-flex flex-column flex-sm-row gap-3">
          <button onclick="history.back()" class="btn btn-outline-danger flex-grow-1">
            <i class="bi bi-arrow-left me-2"></i> Go Back
          </button>
          <a href="{$BASEURL}/" class="btn btn-danger flex-grow-1">
            <i class="bi bi-house me-2"></i> Home Page
          </a>
        </div>
      </div>
    </div>
  </div>
  
  <script src="{$BASEURL}/scripts/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;

        exit(1);
    }
}