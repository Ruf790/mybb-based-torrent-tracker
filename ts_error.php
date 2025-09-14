<?
/***********************************************/
/*     TS Special Edition v.5.4.1 [Nulled]     */
/*              Special Thanks To              */
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  require_once "global.php";
  
  require_once INC_PATH . '/settings.php';
  
  global $BASEURL;
  
  function show__error ($errormessage, $title = 'An error has occured!', $errortitle = 'An error has occured!')
  {
    global $rootpath;
    
	echo '
	
	<title>' . $title . ' => IP: ' . htmlspecialchars ($_SERVER['REMOTE_ADDR']) . ' --- Date: ' . date ('F j, Y, g:i a') . ' -- URL: ' . htmlspecialchars ($_SERVER['REQUEST_URI']) . ' <=</title>
	' . $errormessage . '';
	
  }

  define ('TSE_VERSION', '0.2 ');
  $errorid = (isset ($_GET['errorid']) ? intval ($_GET['errorid']) : (defined ('errorid') ? intval (errorid) : 0));
  $errormessages = array (0 => 'An unknown error has occured, please contact us.', 
  1 => 'Request tainting attempted!', 
  2 => 'In order to accept POST request originating from this domain, the admin must add this domain to the whitelist.', 
  3 => 
  
  '
  
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap.min.css" media="all">

</head>

<body>


    <section class="bg-light">
        <div class="container-fluid">
            <div class="row row-cols-1 justify-content-center py-5">
                <div class="col-xxl-7 mb-4">
                    <div class="lc-block">
                        <script src="'.$BASEURL.'/scripts/lottie-player.js"></script>
                        <lottie-player src="'.$BASEURL.'/scripts/lf20_u1xuufn3.json" class="mx-auto" background="transparent" speed="1" loop="" autoplay=""></lottie-player>
                    </div><!-- /lc-block -->
                </div><!-- /col -->
                <div class="col text-center">
                    <div class="lc-block">
                        <!-- /lc-block -->
                        <div class="lc-block mb-4">
                            <div editable="rich">
                                <p class="rfs-11 fw-light"><h2>Missing or Corrupted language file!</h2></p>
                            </div>
                        </div><!-- /lc-block -->
                        <div class="lc-block">
                            <a class="btn btn-lg btn-primary" href="'.$BASEURL.'" role="button">Back to homepage</a>
                        </div><!-- /lc-block -->
                    </div><!-- /lc-block -->
                </div><!-- /col -->
            </div>
        </div>
    </section>

    <script defer src="'.$BASEURL.'/scripts/bootstrap.bundle.min.js"></script>

</body></html>',
  
  

  
  
  
  
  
  4 => 'Please refresh the page and try again!', 
  5 => 'MySQL Error!', 
  6 => 'The server is too busy at the moment. Please try again later.<br />Click <a href="JavaScript:location.reload(true);">here</a> to refresh this page.', 
  7 => 'Prefetching is not allowed due to the various privacy issues that arise.', 
  8 => 'Script Error! (SE-I). TS SE is not installed correctly. Please contact us to fix this issue. <a href="http://templateshares.net/special/supportdesk.php?act=submitticket">http://templateshares.net/special/supportdesk.php?act=submitticket</a>', 
  9 => 'Your account has either been suspended or you have been banned from accessing this tracker.!', 
  
  400 => '
  <strong>400 Bad request</strong> -- This means that a request for a URL
  has been made but the server is not configured or capable of responding to it. This might be the case for URLs 
  that are handed-off to a servlet engine where no default document or servlet is configured, 
  or the HTTP request method is not implemented.', 
  
  
  401 => '<strong>401 Authorization Required</strong> -- "Authorization is required to view this page. You have not provided valid username/password information." This means that the required username and/or password was not properly entered to access a password protected page or area of the web site space.', 403 => '<strong>403 Forbidden</strong> -- "You are not allowed to access this page." (This error refers to pages that the server is finding, ie. they do exist, but the permissions on the file are not sufficient to allow the webserver to "serve" the page to any end user with or without a password.)', 
  
  404 => '
  
 
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap.min.css" media="all">

</head>

<body>


    <section class="bg-light">
        <div class="container-fluid">
            <div class="row row-cols-1 justify-content-center py-5">
                <div class="col-xxl-7 mb-4">
                    <div class="lc-block">
                         <script src="'.$BASEURL.'/scripts/lottie-player.js"></script>
                        <lottie-player src="'.$BASEURL.'/scripts/lf20_u1xuufn3.json" class="mx-auto" background="transparent" speed="1" loop="" autoplay=""></lottie-player>
                    </div><!-- /lc-block -->
                </div><!-- /col -->
                <div class="col text-center">
                    <div class="lc-block">
                        <!-- /lc-block -->
                        <div class="lc-block mb-4">
                            <div editable="rich">
                                <p class="rfs-11 fw-light"> The page you are looking for was moved, removed or might never existed.</p>
                            </div>
                        </div><!-- /lc-block -->
                        <div class="lc-block">
                            <a class="btn btn-lg btn-primary" href="'.$BASEURL.'" role="button">Back to homepage</a>
                        </div><!-- /lc-block -->
                    </div><!-- /lc-block -->
                </div><!-- /col -->
            </div>
        </div>
    </section>

    <script defer src="'.$BASEURL.'/scripts/bootstrap.bundle.min.js"></script>

</body></html>',
  
  

  
  
  
 
  
  
  
  500 => '<strong>500 Internal Server Error</strong> -- "The server encountered an internal error or misconfiguration and was unable to complete your request. Please contact the server administrator and inform them of the time the error occurred, and anything you might have done to produce this error."');
  if (!empty ($errormessages[$errorid]))
  {
    show__error ($errormessages[$errorid]);
  }
  else
  {
    show__error ('An unknown error has occured, please contact us.');
  }

  exit ();
?>