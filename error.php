<?php
declare(strict_types=1);



require_once __DIR__ . '/global.php';
require_once INC_PATH . '/settings.php';

/**
 * Define application version
 */
define('E_VERSION', '0.2');

/**
 * Display error page
 */
function show_error(
    string $errorMessage, 
    string $title = 'An error has occurred!', 
    string $errorTitle = 'An error has occurred!'
): void {
    global $rootpath;
    
    // Escape output for security
    $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $escapedIp = htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
    $escapedUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
    $currentDate = date('F j, Y, g:i a');
    
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$escapedTitle} => IP: {$escapedIp} --- Date: {$currentDate} -- URL: {$escapedUri} <=</title>
    </head>
    <body>
        {$errorMessage}
    </body>
    </html>
HTML;
}

/**
 * Get error ID from request or constants
 */
$errorId = $_GET['errorid'] ?? 0;
$errorId = (int) $errorId;
if ($errorId === 0 && defined('errorid')) {
    $errorId = (int) constant('errorid');
}

/**
 * Error messages configuration
 */
$errorMessages = [
    // Custom errors
    0 => 'An unknown error has occurred, please contact us.',
    
    1 => 'Request tainting attempted!',
    
    2 => 'In order to accept POST request originating from this domain, the admin must add this domain to the whitelist.',
    
    3 => <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{$BASEURL}/include/templates/default/style/bootstrap.min.css" media="all">
</head>
<body>
    <section class="bg-light">
        <div class="container-fluid">
            <div class="row row-cols-1 justify-content-center py-5">
                <div class="col-xxl-7 mb-4">
                    <div class="lc-block">
                        <script src="{$BASEURL}/scripts/lottie-player.js"></script>
                        <lottie-player src="{$BASEURL}/scripts/lf20_u1xuufn3.json" 
                            class="mx-auto" 
                            background="transparent" 
                            speed="1" 
                            loop 
                            autoplay>
                        </lottie-player>
                    </div>
                </div>
                <div class="col text-center">
                    <div class="lc-block">
                        <div class="lc-block mb-4">
                            <div editable="rich">
                                <h2>Missing or Corrupted language file!</h2>
                            </div>
                        </div>
                        <div class="lc-block">
                            <a class="btn btn-lg btn-primary" href="{$BASEURL}" role="button">
                                Back to homepage
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script defer src="{$BASEURL}/scripts/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML,
    
    4 => 'Please refresh the page and try again!',
    
    5 => 'MySQL Error!',
    
    6 => <<<HTML
The server is too busy at the moment. Please try again later.<br>
Click <a href="JavaScript:location.reload(true);">here</a> to refresh this page.
HTML,
    
    7 => 'Prefetching is not allowed due to the various privacy issues that arise.',
    
    
    9 => 'Your account has either been suspended or you have been banned from accessing this tracker.',
    
    // HTTP errors
    400 => <<<HTML
<strong>400 Bad Request</strong> -- This means that a request for a URL has been made 
but the server is not configured or capable of responding to it. This might be the case 
for URLs that are handed-off to a servlet engine where no default document or servlet is 
configured, or the HTTP request method is not implemented.
HTML,
    
    401 => <<<HTML
<strong>401 Authorization Required</strong> -- "Authorization is required to view this page. 
You have not provided valid username/password information." This means that the required 
username and/or password was not properly entered to access a password protected page or 
area of the web site space.
HTML,
    
    403 => <<<HTML
<strong>403 Forbidden22222</strong> -- "You are not allowed to access this page." 
(This error refers to pages that the server is finding, i.e., they do exist, but the 
permissions on the file are not sufficient to allow the webserver to "serve" the page 
to any end user with or without a password.)
HTML,
    
    404 => <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{$BASEURL}/include/templates/default/style/bootstrap.min.css" media="all">
</head>
<body>
    <section class="bg-light">
        <div class="container-fluid">
            <div class="row row-cols-1 justify-content-center py-5">
                <div class="col-xxl-7 mb-4">
                    <div class="lc-block">
                        <script src="{$BASEURL}/scripts/lottie-player.js"></script>
                        <lottie-player src="{$BASEURL}/scripts/lf20_u1xuufn3.json" 
                            class="mx-auto" 
                            background="transparent" 
                            speed="1" 
                            loop 
                            autoplay>
                        </lottie-player>
                    </div>
                </div>
                <div class="col text-center">
                    <div class="lc-block">
                        <div class="lc-block mb-4">
                            <div editable="rich">
                                <p class="rfs-11 fw-light">
                                    The page you are looking for was moved, removed or might never existed.
                                </p>
                            </div>
                        </div>
                        <div class="lc-block">
                            <a class="btn btn-lg btn-primary" href="{$BASEURL}" role="button">
                                Back to homepage
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script defer src="{$BASEURL}/scripts/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML,
    
    500 => <<<HTML
<strong>500 Internal Server Error</strong> -- "The server encountered an internal error 
or misconfiguration and was unable to complete your request. Please contact the server 
administrator and inform them of the time the error occurred, and anything you might 
have done to produce this error."
HTML
];

/**
 * Display appropriate error message
 */
if (isset($errorMessages[$errorId])) {
    show_error($errorMessages[$errorId]);
} else {
    show_error('An unknown error has occurred, please contact us.');
}

exit(0);