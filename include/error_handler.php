<?php

declare(strict_types=1);

// Отключаем стандартный вывод ошибок PHP
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Polyfills для PHP < 8.5
if (!function_exists('get_error_handler')) {
    function get_error_handler(): ?callable 
    {
        $currentHandler = set_error_handler(static fn() => false);
        restore_error_handler();
        return $currentHandler;
    }
}

if (!function_exists('get_exception_handler')) {
    function get_exception_handler(): ?callable
    {
        $currentHandler = set_exception_handler(static fn() => false);
        restore_exception_handler();
        return $currentHandler;
    }
}


set_error_handler('GlobalErrorHandler');
register_shutdown_function('FatalErrorHandler');


/**
 * Определяет, можно ли текущему посетителю показывать подробности ошибки
 * (файл/строка/сообщение/стектрейс). Обычные гости их видеть не должны —
 * это утечка структуры кода и путей на сервере.
 *
 * Специально обёрнуто в try/catch: обработчик ошибок не имеет права
 * сам упасть с ошибкой при попытке проверить права.
 */
function is_staff_error_viewer(): bool
{
    try {
        global $usergroups, $CURUSER;

        if (!empty($usergroups) && is_array($usergroups) && function_exists('is_mod')) {
            if (is_mod($usergroups)) {
                return true;
            }
        }

        // Резервная проверка, если $usergroups почему-то недоступен на момент фатала
        if (!empty($CURUSER['id']) && !empty($usergroups['cansettingspanel'])) {
            return true;
        }
    } catch (\Throwable) {
        // Если проверка сама упала — по умолчанию считаем гостем (безопаснее).
    }

    return false;
}

/**
 * Global error handler with PHP 8.5 features and handler detection
 */
function GlobalErrorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    // Ignore errors suppressed with @
    if (!(error_reporting() & $errno)) {
        return true;
    }

    // Log current error handler for debugging
    $currentHandler = get_error_handler();
    $handlerType = 'system';
    if ($currentHandler === null) {
        $handlerType = 'system';
    } elseif (is_string($currentHandler)) {
        $handlerType = "function:{$currentHandler}";
    } elseif (is_array($currentHandler)) {
        $handlerType = 'object_method';
    } else {
        $handlerType = 'callable';
    }

    $timestamp = date('d-m-Y H:i:s');
    
    // Safe variable access for older PHP versions
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '-';
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '-';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '-';

    // Script detection for older PHP
    //$script = 'unknown.php';
    //if (isset($_SERVER['SCRIPT_NAME'])) {
    //    $script = basename($_SERVER['SCRIPT_NAME']);
    //} elseif (isset($_SERVER['PHP_SELF'])) {
    //    $script = basename($_SERVER['PHP_SELF']);
    //} elseif (PHP_SAPI === 'cli' && isset($_SERVER['argv'][0])) {
    //    $script = basename($_SERVER['argv'][0]);
    //}
	$script = $errfile !== 'Unknown file' ? basename($errfile) : 'unknown.php';

    $memoryUsage = number_format(memory_get_usage(true));
    $phpVersion = PHP_VERSION;
    $os = PHP_OS;

    $message = "
        
        ------------------------------------------
        {$timestamp}
        [{$errno}] {$errstr}
        PHP Error on line {$errline} in file {$errfile}
        Error Handler: {$handlerType}
        SCRIPT: {$script}
        HOST: {$host}
        URI: {$uri}
        IP: {$ip}
        UA: {$userAgent}
        REF: {$referer}
        MEM: {$memoryUsage} bytes
        PHP: {$phpVersion} | OS: {$os}
        ------------------------------------------
        ";

    // Modern directory handling
    $root = rtrim(dirname(__DIR__), '/\\');
    $logDir = "{$root}/error_logs";
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = "{$logDir}/{$script}.log";

    // Use modern file_put_contents with error suppression
    @file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);

    return true; // Prevent default error handler
}

/**
 * Fatal error handler with PHP 8.5 features and handler inspection
 */
function FatalErrorHandler(): void
{
    global $BASEURL;
    
    $error = error_get_last();
    
    // Error type checking for older PHP
    $isFatal = false;
    if ($error && isset($error['type'])) {
        $errorType = $error['type'];
        if (in_array($errorType, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $isFatal = true;
        }
    }

    if (!$isFatal) {
        return;
    }

    // Get current handlers for debugging
    $errorHandler = get_error_handler();
    $exceptionHandler = get_exception_handler();
    
    $errorHandlerType = $errorHandler ? gettype($errorHandler) : 'system';
    $exceptionHandlerType = $exceptionHandler ? gettype($exceptionHandler) : 'system';
    
    $handlerInfo = "Error Handler: {$errorHandlerType} | Exception Handler: {$exceptionHandlerType}";

    // Safe array access
    $errno = $error['type'] ?? 0;
    $errstr = $error['message'] ?? 'Unknown error';
    $errfile = $error['file'] ?? 'Unknown file';
    $errline = $error['line'] ?? 0;

    // URL detection for older PHP
    $protocol = 'http';
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $protocol = 'https';
    }
    
    if (!isset($BASEURL) && isset($_SERVER['HTTP_HOST'])) {
        $BASEURL = "{$protocol}://{$_SERVER['HTTP_HOST']}";
    }
    
    $BASEURL = $BASEURL ?? '';
	
	
    $phpVersion = PHP_VERSION;

    $title = "Fatal Error";

    // Send headers if not already sent
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/html; charset=UTF-8');
    }

    // Уникальный ID ошибки — можно называть при обращении в поддержку,
    // не раскрывая гостю сам файл/строку/сообщение.
    $errorId = strtoupper(substr(md5($errfile . $errline . $errstr), 0, 8));

    $isStaff = is_staff_error_viewer();

    if ($isStaff) {
        // Стафф видит полную картину — как и раньше.
        $errorMessage = "File: <strong>{$errfile}</strong><br>Line: <strong>{$errline}</strong><br>Message: <strong>{$errstr}</strong><br><small class='text-muted'>{$handlerInfo}</small>";
        $alertHeading = 'System Error Detected';
    } else {
        // Гость видит только факт ошибки и ID для поддержки — без путей,
        // текста сообщения и информации об обработчиках.
        $errorMessage = "Something went wrong on our end. Our team has already been notified.<br>If you need help, please reference Error ID <strong>ERR-{$errorId}</strong> when contacting support.";
        $alertHeading = 'Something Went Wrong';
        $handlerInfo = '';
    }

    // Modern HTML with heredoc and variables
    $handlerInfoBlock = $handlerInfo !== ''
        ? "<div class=\"handler-info mt-3\"><i class=\"bi bi-gear me-1\"></i>Current Error Handler: <code>{$handlerInfo}</code></div>"
        : '';

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
<body class="bg-light">
    <div class="container py-5">
        <div class="error-card">
            <div class="card-header22">
                <i class="bi bi-exclamation-triangle-fill error-icon"></i>
                <div>
                    <h1 class="h2 mb-1">Fatal Error</h1>
                    <p class="mb-0 opacity-85">A critical problem occurred while processing your request</p>
                </div>
            </div>
            
            <div class="card-body">
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-circle-fill text-danger me-3 fs-5"></i>
                        <div>
                            <h3 class="h5 alert-heading text-danger mb-2">{$alertHeading}</h3>
                            <div>
                                {$errorMessage}
                            </div>
                        </div>
                    </div>
                </div>

                {$handlerInfoBlock}

                <div class="mt-4 pt-2 border-top">
                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <button onclick="history.back()" 
                                class="btn btn-outline-danger flex-fill d-flex align-items-center justify-content-center">
                            <i class="bi bi-arrow-left me-2"></i> 
                            Go Back
                        </button>
                        
                        <a href="{$BASEURL}/" 
                           class="btn btn-danger flex-fill d-flex align-items-center justify-content-center">
                            <i class="bi bi-house me-2"></i> 
                            Home Page
                        </a>
                        
                        <button onclick="location.reload()" 
                                class="btn btn-outline-secondary flex-fill d-flex align-items-center justify-content-center">
                            <i class="bi bi-arrow-clockwise me-2"></i> 
                            Retry
                        </button>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Error ID: <code>ERR-{$errorId}</code> • 
                        PHP: <strong>{$phpVersion}</strong> • 
                        <span id="timestamp"></span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="{$BASEURL}/scripts/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('timestamp').textContent = 
            'Generated at ' + new Date().toLocaleString();
            
        document.addEventListener('DOMContentLoaded', function() {
            var buttons = document.querySelectorAll('.btn');
            buttons.forEach(function(btn) {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>
HTML;

    exit(1);
}

/**
 * Additional utility functions for modern error handling
 */

/**
 * Log exception with handler context
 */
function log_exception(Throwable $exception, $context = null): void
{
    $timestamp = date('d-m-Y H:i:s');
    $message = $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    $trace = $exception->getTraceAsString();
    $type = get_class($exception);
    
    // Get current handlers for context
    $errorHandler = get_error_handler();
    $exceptionHandler = get_exception_handler();

    $errorHandlerType = $errorHandler ? gettype($errorHandler) : 'system';
    $exceptionHandlerType = $exceptionHandler ? gettype($exceptionHandler) : 'system';
    $contextInfo = $context ? $context : 'No context';

    $logEntry = "
        
        ==========================================
        {$timestamp} - EXCEPTION: {$type}
        Message: {$message}
        File: {$file} (Line: {$line})
        Context: {$contextInfo}
        Error Handler: {$errorHandlerType}
        Exception Handler: {$exceptionHandlerType}
        Stack Trace:
        {$trace}
        ==========================================
        ";

    $root = rtrim(dirname(__DIR__), '/\\');
    $logDir = "{$root}/error_logs";
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = "{$logDir}/exceptions.log";
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Debug function to check current error handling setup
 */
function debug_error_handlers(): array
{
    return [
        'error_handler' => get_error_handler(),
        'exception_handler' => get_exception_handler(),
        'error_reporting' => error_reporting(),
        'display_errors' => ini_get('display_errors'),
        'log_errors' => ini_get('log_errors'),
        'error_log' => ini_get('error_log'),
        'php_version' => PHP_VERSION,
    ];
}