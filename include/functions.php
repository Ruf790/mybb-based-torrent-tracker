<?php


declare(strict_types=1);




function get_user_by_username2(string $username, array $options = []): array
{
    global $mybb, $db;

    $username = $db->escape_string(my_strtolower($username));
    $username_method = (int)($options['username_method'] ?? 0);

    // Современный синтаксис match вместо switch
    [$field, $efield] = match($db->type) {
        'mysql', 'mysqli' => ['username', 'email'],
        default => ['LOWER(username)', 'LOWER(email)']
    };

    // Современный синтаксис для условий WHERE
    $sqlwhere = match($username_method) {
        1 => "{$efield} = '{$username}'",
        2 => "{$field} = '{$username}' OR {$efield} = '{$username}'",
        default => "{$field} = '{$username}'"
    };

    // Обработка полей
    $fields = 'id';
    if (isset($options['fields'])) {
        if ($options['fields'] === '*') {
            $fields = '*';
        } else {
            $requested_fields = (array)$options['fields'];
            if (!in_array('id', $requested_fields, true)) {
                $requested_fields[] = 'id';
            }
            $fields = implode(',', array_unique($requested_fields));
        }
    }

    $query = $db->simple_select('users', $fields, $sqlwhere, ['limit' => 1]);

    // Проблема: нельзя вернуть bool при объявленном array
    if (isset($options['exists']) && $options['exists']) {
        // Для проверки существования нужна отдельная функция
        throw new InvalidArgumentException('Option "exists" is not supported. Use user_exists_by_username() instead.');
    }

    $result = $db->fetch_array($query);
    
    return is_array($result) ? $result : [];
}








/////////////Functions.php////////////////////////////////////////////////////////////
function stdhead(string $title = '', bool $msgalert = true, string $script = '', string $script2 = '', string $incCSS = ''): void
{
    global $CURUSER, $SITEONLINE, $SITENAME, $SITEEMAIL, $session, $mail_handler, $header, $showownunapproved, $enableattachments, $templates, $templatelist, $date_formats, $f_threadsperpage, $f_postsperpage, $use_xmlhttprequest, $time_formats, $offline_minutes, $parser, $plugins, $cache, $BASEURL, $db, $mybb, $jumptopagemultipage, $maxmultipagelinks, $offlinemsg, $disablerightclick, $autorefreshtime, $autorefresh, $leftmenu, $gzipcompress, $delay, $url, $cookiedomain, $cookiepath, $cookieprefix, $cookiesamesiteflag, $cookiesecureflag, $rootpath, $pic_base_url, $charset, $metadesc, $metakeywords, $lang, $slogan, $groupscache, $usergroups, $leechwarn_remove_ratio, $cache, $dateformat, $timeformat, $cachetime, $checkconnectable, $timezoneoffset;

    // Проверка автоматического включения сайта
    if ($SITEONLINE === 'no' && isset($offline_minutes)) {
        // Для limited-режима (когда установлен числовой timestamp)
        if (is_numeric($offline_minutes) && $offline_minutes > 0) {
            if (time() > (int)$offline_minutes) {
                $db->sql_query("START TRANSACTION");
                try {
                    $db->sql_query("UPDATE settings SET value = 'yes' WHERE name = 'SITEONLINE'");
                    $db->sql_query("UPDATE settings SET value = '0' WHERE name = 'offline_minutes'");
                    $db->sql_query("COMMIT");
                    
                    rebuild_settings();
                    write_log("[MAINTENANCE] Automatically switched to online - time expired");
                    
                    // Обновляем глобальные переменные
                    $SITEONLINE = 'yes';
                    $offline_minutes = 0;
                    
                } catch (Exception $e) {
                    $db->sql_query("ROLLBACK");
                    write_log("[ERROR] Failed to auto-enable site: " . $e->getMessage());
                }
            }
        }
        // Для unlimited-режима ничего не делаем - остается в оффлайне
    }

    if ($SITEONLINE != 'yes' && $CURUSER) {
        if ($usergroups['canviewboardclosed'] != '1') {
            require_once INC_PATH . '/maintenance_page.php';
            render_maintenance_page();
            exit;
        } else {
            $offlinemsg = true;
        }
    }

    $lang->load('header');
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    
    $ts_tzoffset = $CURUSER['timezone'] ?? $timezoneoffset;

    $title = $SITENAME.' :: '.($title != '' ? htmlspecialchars_uni($title) : TS_MESSAGE);

    if ($CURUSER) {
        include_once(INC_PATH.'/functions_ratio.php');
      
        $uploaded = $CURUSER['uploaded'] ?? 0;
        $downloaded = $CURUSER['downloaded'] ?? 0;
        $ratio = get_user_ratio($CURUSER['uploaded'] ?? 0, $CURUSER['downloaded'] ?? 0, true);
        
       
	   
	    $medaldon = (!empty($CURUSER['donor'] ?? null) && $CURUSER['donor'] === 'yes')
    ? '<i class="fa-solid fa-star"></i>'
    : '';

$warn = (!empty($CURUSER['warned'] ?? null) && $CURUSER['warned'] === 'yes')
    ? '<i class="fa-solid fa-triangle-exclamation fa-bounce text-danger" title="'.$lang->global['imgwarned'].'"></i>'
    : '';

$lwarn = (!empty($CURUSER['leechwarn'] ?? null) && $CURUSER['leechwarn'] === 'yes')
    ? '<i class="fa-solid fa-triangle-exclamation fa-bounce" style="color:#FFD43B;" title="LeechWarned"></i>'
    : '';
	   
	   
	   

        if ($checkconnectable == 'yes') {
            $connectablequery = $db->sql_query("SELECT userid FROM peers WHERE connectable = 'no' AND userid = ".sqlesc($CURUSER['id']));
            $c_count = $db->num_rows($connectablequery);
            if ($c_count > 0) {
                $connectablealert = sprintf($lang->global['connectablealert'], $c_count, $BASEURL.'/tsf_forums/', $BASEURL.'/faq.php');
                $warnmessages[] = $connectablealert;
            }
        }
    }
    
    include(INC_PATH.'/templates/default/header.php');    
}

function stdfoot(): void
{    
    global $SITENAME, $BASEURL, $CURUSER, $rootpath, $lang, $usergroups, $db, $mybb, $maintimer, $templates, $templatelist, $session;    
    
    include(INC_PATH.'/templates/default/footer.php');    
}

function jumpbutton(array|string $where): string
{
    // Если передали строку, оборачиваем в массив
    if (!is_array($where)) {
        $where = [$where];
    }

    $str = '<div class="hoptobuttons d-flex flex-wrap gap-2 justify-content-center">';

    foreach ($where as $value => $jump) {
        if (!empty($value) && !empty($jump)) {
            $str .= '<a href="' . htmlspecialchars($jump ?? '') . '" class="btn btn-primary">'
                 . htmlspecialchars($value) . '</a>';
        }
    }

    $str .= '</div>';

    return $str;
}

function tr(string $x, string $y, bool $noesc = false, string $relation = ''): void
{
    if ($noesc) {
        $a = $y;
    } else {
        $a = htmlspecialchars_uni($y);
        $a = str_replace("\n", "<br />\n", $a);
    }
    echo "<tr".($relation ? " relation = \"$relation\"" : "")."><td class=\"heading\" valign=\"top\" align=\"right\" width=\"20%\">$x</td><td valign=\"top\" align=\"left\" width=\"80%\">$a</td></tr>\n";
}

function my_validate_url(string $url, bool $relative_path = false, bool $allow_local = false): bool
{
    if($allow_local) {
        $regex = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:localhost|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,}))\.?))(?::\d{2,5})?(?:[/?#]\S*)?$_iuS';
    } else {
        $regex = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,}))\.?)(?::\d{2,5})?(?:[/?#]\S*)?$_iuS';
    }

    if($relative_path && str_starts_with($url, '/') || preg_match($regex, $url)) {
        return true;
    }
    return false;
}



function redirect(string $url, string $message = "", string $title = "", bool $force_redirect = false): void
{
    global $header, $footer, $mybb, $theme, $headerinclude, $templates, $lang, $plugins, $redirects, $charset, $CURUSER, $BASEURL, $SITENAME;

    $redirect_args = ['url' => &$url, 'message' => &$message, 'title' => &$title];
    $plugins->run_hooks("redirect", $redirect_args);

    if($mybb->get_input('ajax', MyBB::INPUT_INT)) {
        $data = "<script type=\"text/javascript\">\n";
        if($message != "") {
            $data .=  'alert("'.addslashes($message).'");';
        }
        $url = str_replace("#", "&#", $url);
        $url = htmlspecialchars_decode($url);
        $url = str_replace(["\n","\r",";"], "", $url);
        $data .=  'window.location = "'.addslashes($url).'";'."\n";
        $data .= "</script>\n";

        @header("Content-type: application/json; charset={$charset}");
        echo json_encode(["data" => $data]);
        exit;
    }

    if(!$message) {
        $message = 'You will now be redirected';
    }

    $time = TIMENOW;
    $timenow = my_datee('relative', $time);

    if(!$title) {
        $title = $SITENAME;
    }

    if ($force_redirect === true || 
        ($redirects == 1 && 
        (!isset($CURUSER['id']) || 
        (isset($CURUSER['showredirect']) && $CURUSER['showredirect'] == 1)))
    ) {
        $url = str_replace("&amp;", "&", $url);
        $url = htmlspecialchars_uni($url);

        $redirectpage = '
<html>
<head>
<title>' . $title . '</title>
<meta http-equiv="refresh" content="2;URL=' . $url . '" />
<link rel="stylesheet" type="text/css" href="' . $BASEURL . '/include/templates/default/style/bootstrap.min.css" />
</head>
<body>
<div class="container-md pt-5">
<div class="card pt-5 border-0" style="max-width: 100%">
<div class="card-body pt-5">
    <div class="text-dark"><h3 class="mb-0 fw-bold">' . $title . '</h3></div>
    <div class="alert bg-nav mt-2 mb-2" style="font-size: 16px">
        ' . $message . ' </div>
    <div class="text-center" style="font-size: 16px">
        <a href="' . $url . '">Click here if you don\'t want to wait any longer</a>
    </div>
</div>
</div>
</body>
</html>';
        
        echo $redirectpage;
    } else {
        $url = htmlspecialchars_decode($url);
        $url = str_replace(["\n","\r",";"], "", $url);

        run_shutdown();

        if(!my_validate_url($url, true, true)) {
            header("Location: {$BASEURL}/{$url}");
        } else {
            header("Location: {$url}");
        }
    }

    exit;
}

function stdmsg(string $heading = '', string $text = '', bool $htmlstrip = true, string $div = 'error'): void
{
    if ($htmlstrip) {
        $heading = htmlspecialchars_uni($heading);
        $text = htmlspecialchars_uni($text);
    }
    echo show_notice($text, ($div == 'error'), $heading);
}








function stderr(string $error = "", string $title = "", int $errorCode = 400, string $errorType = "general"): void
{
    global $SITENAME, $BASEURL, $header, $footer, $theme, $headerinclude, $db, $templates, $lang, $mybb, $plugins;

    if (empty($error)) {
        $error = 'An unknown error occurred';
    }

    if (empty($title)) {
        $title = $SITENAME . ' - Error';
    }

    // Маппинг типов ошибок на иконки и заголовки
    $errorTypes = [
        '404' => [
            'code' => 404,
            'title' => 'Not Found',
            'icon' => 'bi-exclamation-triangle-fill',
            'messageIcon' => 'bi-database-slash',
            'description' => 'The page or resource you\'re looking for doesn\'t exist'
        ],
        '403' => [
            'code' => 403,
            'title' => 'Access Denied',
            'icon' => 'bi-shield-lock-fill',
            'messageIcon' => 'bi-lock-fill',
            'description' => 'You don\'t have permission to access this resource'
        ],
        '401' => [
            'code' => 401,
            'title' => 'Unauthorized',
            'icon' => 'bi-person-x-fill',
            'messageIcon' => 'bi-key-fill',
            'description' => 'Please login to access this resource'
        ],
        '500' => [
            'code' => 500,
            'title' => 'Server Error',
            'icon' => 'bi-gear-fill',
            'messageIcon' => 'bi-exclamation-octagon-fill',
            'description' => 'An internal server error occurred'
        ],
        '403upload' => [
            'code' => 403,
            'title' => 'Upload Forbidden',
            'icon' => 'bi-cloud-upload-fill',
            'messageIcon' => 'bi-ban-fill',
            'description' => 'You don\'t have permission to upload'
        ],
        'torrent' => [
            'code' => 404,
            'title' => 'Torrent Not Found',
            'icon' => 'bi-file-zip-fill',
            'messageIcon' => 'bi-database-slash',
            'description' => 'The requested torrent could not be found'
        ],
        'general' => [
            'code' => $errorCode,
            'title' => 'Error',
            'icon' => 'bi-exclamation-triangle-fill',
            'messageIcon' => 'bi-info-circle-fill',
            'description' => 'A problem occurred while processing your request'
        ]
    ];

    // Определяем тип ошибки
    $type = isset($errorTypes[$errorType]) ? $errorTypes[$errorType] : $errorTypes['general'];
    
    // Устанавливаем HTTP код ответа
    http_response_code($type['code']);

    // Очищаем буферы вывода
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Подключаем стандартный заголовок если нужно
    if (function_exists('stdhead')) {
        stdhead();
    }

    // Формируем страницу ошибки
    $errorpage = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <link href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
	<link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="error-card-wrapper">
            <div class="error-card card border-0 shadow-lg overflow-hidden">
                <div class="error-bg-pattern"></div>
                <div class="gradient-line"></div>
                
                <div class="card-body p-4 p-md-5 position-relative">
                    <div class="error-icon-wrapper mb-4 text-center">
                        <div class="error-icon-circle mx-auto">
                            <i class="bi ' . $type['icon'] . ' error-icon"></i>
                        </div>
                        <div class="error-pulse"></div>
                    </div>
                    
                    <div class="text-center mb-4">
                        <h1 class="error-title display-4 fw-bold mb-2">' . $type['code'] . '</h1>
                        <h2 class="error-subtitle h3 mb-2">' . htmlspecialchars($type['title']) . '</h2>
                        <p class="error-description text-muted mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            ' . htmlspecialchars($type['description']) . '
                        </p>
                    </div>

                    <div class="error-message-box mb-4">
                        <div class="d-flex align-items-start">
                            <div class="message-icon me-3">
                                <i class="bi ' . $type['messageIcon'] . '"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="mb-2">' . $error . '</h5>
                                <p class="text-muted mb-0 small">
                                    ' . ($errorType === '403upload' ? 'You need special permissions to upload torrents.' : 'Please check your input and try again.') . '
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="error-details mb-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="detail-item p-3 rounded-3">
                                    <i class="bi bi-clock-history text-danger mb-2"></i>
                                    <h6 class="mb-1">Request Time</h6>
                                    <small class="text-muted">' . date('H:i:s') . '</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="detail-item p-3 rounded-3">
                                    <i class="bi bi-calendar-check text-danger mb-2"></i>
                                    <h6 class="mb-1">Request Date</h6>
                                    <small class="text-muted">' . date('Y-m-d') . '</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="actions-wrapper">
                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <button onclick="history.back()" class="btn btn-outline-danger btn-lg flex-grow-1 hover-lift">
                                <i class="bi bi-arrow-left me-2"></i> 
                                <span>Go Back</span>
                            </button>
                            <a href="' . $BASEURL . '/" class="btn btn-danger btn-lg flex-grow-1 hover-lift">
                                <i class="bi bi-house-door me-2"></i> 
                                <span>Home Page</span>
                            </a>
                        </div>
                        
                        <div class="quick-links mt-4 pt-3 border-top">
                            <small class="text-muted d-block mb-2">Quick Links:</small>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="' . $BASEURL . '/browse.php" class="quick-link">
                                    <i class="bi bi-grid"></i> Browse
                                </a>
                                <a href="' . $BASEURL . '/search.php" class="quick-link">
                                    <i class="bi bi-search"></i> Search
                                </a>
                                <a href="' . $BASEURL . '/upload.php" class="quick-link">
                                    <i class="bi bi-cloud-upload"></i> Upload
                                </a>
                                <a href="' . $BASEURL . '/index2.php" class="quick-link">
                                    <i class="bi bi-chat"></i> Forum
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-transparent border-0 text-center py-3">
                    <small class="text-muted">
                        <i class="bi bi-bug me-1"></i>
                        If you believe this is a mistake, please contact 
                        <a href="' . $BASEURL . '/contact.php" class="text-danger text-decoration-none">support</a>
                    </small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';

    echo $errorpage;
    
    if (function_exists('stdfoot')) {
        stdfoot();
    }
    
    exit;
}






function stdok(string $message = "", string $title = "Success", string $subtitle = "Operation completed without errors"): void
{
    global $SITENAME, $BASEURL;

    if (empty($message)) {
        $message = 'Operation completed successfully.';
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    if (function_exists('stdhead')) {
        stdhead();
    }

    $e    = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $time = date('H:i:s');
    $date = date('Y-m-d');

    $okpage = '
<link href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="' . $BASEURL . '/include/templates/default/style/errorss.css" rel="stylesheet">

<div class="container">
    <div class="error-card-wrapper">
        <div class="error-card-ok card border-0 overflow-hidden">
            <div class="error-bg-pattern-ok"></div>
            <div class="gradient-line-ok"></div>

            <div class="card-body p-4 p-md-5 position-relative">

                <div class="error-icon-wrapper mb-4 text-center">
                    <div class="ok-icon-circle mx-auto">
                        <i class="bi bi-check-lg ok-icon"></i>
                    </div>
                    <div class="ok-pulse"></div>
                </div>

                <div class="text-center mb-4">
                    <h1 class="ok-title display-4 fw-bold mb-2">Success</h1>
                    <h2 class="ok-subtitle h3 mb-2">' . $e($title) . '</h2>
                    <p class="ok-description text-muted mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        ' . $e($subtitle) . '
                    </p>
                </div>

                <div class="ok-message-box mb-4">
                    <div class="d-flex align-items-start">
                        <div class="ok-message-icon me-3">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-2">' . $e($message) . '</h5>
                            <p class="text-muted mb-0 small">No further action is required.</p>
                        </div>
                    </div>
                </div>

                <div class="ok-details mb-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="ok-detail-item p-3 rounded-3">
                                <i class="bi bi-clock-history text-success mb-2"></i>
                                <h6 class="mb-1">Completed at</h6>
                                <small class="text-muted">' . $time . '</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="ok-detail-item p-3 rounded-3">
                                <i class="bi bi-calendar-check text-success mb-2"></i>
                                <h6 class="mb-1">Date</h6>
                                <small class="text-muted">' . $date . '</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="actions-wrapper">
                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <button onclick="history.back()" class="btn btn-outline-success btn-lg flex-grow-1 hover-lift-ok">
                            <i class="bi bi-arrow-left me-2"></i>
                            <span>Go Back</span>
                        </button>
                        <a href="' . $e($BASEURL) . '/" class="btn btn-success btn-lg flex-grow-1 hover-lift-ok">
                            <i class="bi bi-house-door me-2"></i>
                            <span>Home Page</span>
                        </a>
                    </div>

                    <div class="quick-links-ok mt-4 pt-3 border-top">
                        <small class="text-muted d-block mb-2">Quick Links:</small>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="' . $e($BASEURL) . '/browse.php" class="quick-link-ok">
                                <i class="bi bi-grid"></i> Browse
                            </a>
                            <a href="' . $e($BASEURL) . '/search.php" class="quick-link-ok">
                                <i class="bi bi-search"></i> Search
                            </a>
                            <a href="' . $e($BASEURL) . '/upload.php" class="quick-link-ok">
                                <i class="bi bi-cloud-upload"></i> Upload
                            </a>
                            <a href="' . $e($BASEURL) . '/index2.php" class="quick-link-ok">
                                <i class="bi bi-chat"></i> Forum
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-0 text-center py-3">
                <small class="text-muted">
                    <i class="bi bi-question-circle me-1"></i>
                    Need help? Visit our
                    <a href="' . $e($BASEURL) . '/faq.php" class="text-success text-decoration-none">FAQ</a>
                    or contact
                    <a href="' . $e($BASEURL) . '/contact.php" class="text-success text-decoration-none">support</a>
                </small>
            </div>
        </div>
    </div>
</div>';

    echo $okpage;

    if (function_exists('stdfoot')) {
        stdfoot();
    }

    exit;
}





function my_inet_pton(string $ip): string|false
{
    if(function_exists('inet_pton')) {
        return @inet_pton($ip);
    } else {
        $r = ip2long($ip);
        if($r !== false && $r != -1) {
            return pack('N', $r);
        }

        $delim_count = substr_count($ip, ':');
        if($delim_count < 1 || $delim_count > 7) {
            return false;
        }

        $r = explode(':', $ip);
        $rcount = count($r);
        if(($doub = array_search('', $r, true)) !== false) {
            $length = (!$doub || $doub == $rcount - 1 ? 2 : 1);
            array_splice($r, $doub, $length, array_fill(0, 8 + $length - $rcount, 0));
        }

        $r = array_map('hexdec', $r);
        array_unshift($r, 'n*');
        $r = call_user_func_array('pack', $r);

        return $r;
    }
}


function my_inet_ntop(string $ip): string|false
{
    if(function_exists('inet_ntop')) {
        return @inet_ntop($ip);
    } else {
        switch(strlen($ip)) {
            case 4:
                [, $r] = unpack('N', $ip);
                return long2ip($r);
            case 16:
                $r = substr(chunk_split(bin2hex($ip), 4, ':'), 0, -1);
                $r = preg_replace(
                    ['/(?::?\b0+\b:?){2,}/', '/\b0+([^0])/e'],
                    ['::', '(int)"$1"?"$1":"0$1"'],
                    $r
                );
                return $r;
        }
        return false;
    }
}

function get_ip(): string
{
    global $mybb, $plugins;

    $ip = strtolower($_SERVER['REMOTE_ADDR'] ?? '');

    $ip_forwarded_check = "0";
    
    if($ip_forwarded_check) {
        $addresses = [];

        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $addresses = explode(',', strtolower($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif(isset($_SERVER['HTTP_X_REAL_IP'])) {
            $addresses = explode(',', strtolower($_SERVER['HTTP_X_REAL_IP']));
        }

        if(is_array($addresses)) {
            foreach($addresses as $val) {
                $val = trim($val);
                // Validate IP address and exclude private addresses
                if(my_inet_ntop(my_inet_pton($val)) == $val && !preg_match("#^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.|fe80:|fe[c-f][0-f]:|f[c-d][0-f]{2}:)#", $val)) {
                    $ip = $val;
                    break;
                }
            }
        }
    }

    if(!$ip && isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = strtolower($_SERVER['HTTP_CLIENT_IP']);
    }

    if($plugins) {
        $ip_array = ["ip" => &$ip];
        $plugins->run_hooks("get_ip", $ip_array);
    }

    return $ip;
}

function fetch_ip_range(string $ipaddress): array|string|false
{
    // Wildcard
    if(str_contains($ipaddress, '*')) {
        if(str_contains($ipaddress, ':')) {
            // IPv6
            $upper = str_replace('*', 'ffff', $ipaddress);
            $lower = str_replace('*', '0', $ipaddress);
        } else {
            // IPv4
            $ip_bits = count(explode('.', $ipaddress));
            if($ip_bits < 4) {
                // Support for 127.0.*
                $replacement = str_repeat('.*', 4-$ip_bits);
                $ipaddress = substr_replace($ipaddress, $replacement, strrpos($ipaddress, '*')+1, 0);
            }
            $upper = str_replace('*', '255', $ipaddress);
            $lower = str_replace('*', '0', $ipaddress);
        }
        $upper = my_inet_pton($upper);
        $lower = my_inet_pton($lower);
        if($upper === false || $lower === false) {
            return false;
        }
        return [$lower, $upper];
    }
    // CIDR notation
    elseif(str_contains($ipaddress, '/')) {
        $ipaddress = explode('/', $ipaddress);
        $ip_address = $ipaddress[0] ?? '';
        $ip_range = (int)($ipaddress[1] ?? 0);

        if(empty($ip_address) || empty($ip_range)) {
            return false;
        } else {
            $ip_address = my_inet_pton($ip_address);

            if(!$ip_address) {
                return false;
            }
        }

        /**
         * Taken from: https://github.com/NewEraCracker/php_work/blob/master/ipRangeCalculate.php
         * Author: NewEraCracker
         * License: Public Domain
         */

        // Pack IP, Set some vars
        $ip_pack = $ip_address;
        $ip_pack_size = strlen($ip_pack);
        $ip_bits_size = $ip_pack_size*8;

        // IP bits (lots of 0's and 1's)
        $ip_bits = '';
        for($i = 0; $i < $ip_pack_size; $i = $i+1) {
            $bit = decbin(ord($ip_pack[$i]));
            $bit = str_pad($bit, 8, '0', STR_PAD_LEFT);
            $ip_bits .= $bit;
        }

        // Significative bits (from the ip range)
        $ip_bits = substr($ip_bits, 0, $ip_range);

        // Some calculations
        $ip_lower_bits = str_pad($ip_bits, $ip_bits_size, '0', STR_PAD_RIGHT);
        $ip_higher_bits = str_pad($ip_bits, $ip_bits_size, '1', STR_PAD_RIGHT);

        // Lower IP
        $ip_lower_pack = '';
        for($i=0; $i < $ip_bits_size; $i=$i+8) {
            $chr = substr($ip_lower_bits, $i, 8);
            $chr = chr(bindec($chr));
            $ip_lower_pack .= $chr;
        }

        // Higher IP
        $ip_higher_pack = '';
        for($i=0; $i < $ip_bits_size; $i=$i+8) {
            $chr = substr($ip_higher_bits, $i, 8);
            $chr = chr(bindec($chr));
            $ip_higher_pack .= $chr;
        }

        return [$ip_lower_pack, $ip_higher_pack];
    }
    // Just on IP address
    else {
        return my_inet_pton($ipaddress);
    }
}

function is_banned_ip(string $ip_address, bool $update_lastuse = false): bool
{
    global $db, $cache;

    $banned_ips = $cache->read("bannedips");
    if(!is_array($banned_ips)) {
        return false;
    }

    $ip_address = my_inet_pton($ip_address);
    foreach($banned_ips as $banned_ip) {
        if(!$banned_ip['filter']) {
            continue;
        }

        $banned = false;

        $ip_range = fetch_ip_range($banned_ip['filter']);
        if(is_array($ip_range)) {
            if(strcmp($ip_range[0], $ip_address) <= 0 && strcmp($ip_range[1], $ip_address) >= 0) {
                $banned = true;
            }
        } elseif($ip_address == $ip_range) {
            $banned = true;
        }
        if($banned) {
            // Updating last use
            if($update_lastuse == true) {
                $db->update_query("banfilters", ["lastuse" => TIMENOW], "fid='{$banned_ip['fid']}'");
            }
            return true;
        }
    }

    return false;
}

function is_super_admin(int $uid): bool
{
    static $super_admins;

    if(!isset($super_admins)) {
        global $mybb;
        $super_admins = str_replace(" ", "", $mybb->config['super_admins'] ?? '');
    }

    return str_contains(",{$super_admins},", ",{$uid},");
}

function my_escape_csv(string $string, bool $escape_active_content = true): string
{
    if($escape_active_content) {
        $active_content_triggers = ['=', '+', '-', '@'];
        $delimiters = [',', ';', ':', '|', '^', "\n", "\t", " "];

        $first_character = mb_substr($string, 0, 1);

        if(
            in_array($first_character, $active_content_triggers, true) ||
            in_array($first_character, $delimiters, true)
        ) {
            $string = "'".$string;
        }

        foreach($delimiters as $delimiter) {
            foreach($active_content_triggers as $trigger) {
                $string = str_replace($delimiter.$trigger, $delimiter."'".$trigger, $string);
            }
        }
    }

    $string = str_replace('"', '""', $string);

    return $string;
}

function my_hash_equals(string $known_string, string $user_string): bool
{
    if(version_compare(PHP_VERSION, '5.6.0', '>=')) {
        return hash_equals($known_string, $user_string);
    } else {
        $known_string_length = my_strlen($known_string);
        $user_string_length = my_strlen($user_string);

        if($user_string_length != $known_string_length) {
            return false;
        }

        $result = 0;

        for($i = 0; $i < $known_string_length; $i++) {
            $result |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }

        return $result === 0;
    }
}

function add_shutdown(callable $name, array $arguments = []): bool
{
    global $shutdown_functions;

    if(!is_array($shutdown_functions)) {
        $shutdown_functions = [];
    }

    if(!is_array($arguments)) {
        $arguments = [$arguments];
    }

    if(is_array($name) && method_exists($name[0], $name[1])) {
        $shutdown_functions[] = ['function' => $name, 'arguments' => $arguments];
        return true;
    } elseif(!is_array($name) && function_exists($name)) {
        $shutdown_functions[] = ['function' => $name, 'arguments' => $arguments];
        return true;
    }

    return false;
}






function update_stats(array $changes = [], bool $force = false): void
{
    global $cache, $db;
    static $stats_changes = [];

    // При первом вызове регистрируем отложенное выполнение
    if (empty($stats_changes)) {
        if (function_exists('add_shutdown')) {
            add_shutdown('update_stats', [[], true]);
        }
    }

    // Инициализация
    if (empty($stats_changes) || ($stats_changes['inserted'] ?? false)) {
        $stats_changes = [
            'numthreads' => '+0',
            'numposts' => '+0',
            'numusers' => '+0',
            'numunapprovedthreads' => '+0',
            'numunapprovedposts' => '+0',
            'numdeletedposts' => '+0',
            'numdeletedthreads' => '+0',
            'inserted' => false
        ];
        $stats = $stats_changes;
    } else {
        $stats = $stats_changes;
    }

    if ($force) {
        if (!empty($changes)) {
            update_stats($changes);
        }
        $stats = $cache->read("stats") ?? [];
        $changes = $stats_changes;
    }

    $new_stats = [];
    $counters = [
        'numthreads',
        'numunapprovedthreads',
        'numposts',
        'numunapprovedposts',
        'numusers',
        'numdeletedposts',
        'numdeletedthreads'
    ];

    foreach ($counters as $counter) {
        if (array_key_exists($counter, $changes)) {
            $val = (string)$changes[$counter];

            if (str_starts_with($val, "+-")) {
                $val = substr($val, 1);
            }

            if (str_starts_with($val, "+") || str_starts_with($val, "-")) {
                $delta = (int)$val;
                if ($delta !== 0) {
                    $oldVal = is_numeric($stats[$counter] ?? 0) ? (int)$stats[$counter] : 0;
                    $newVal = $oldVal + $delta;

                    if (!$force && (isset($stats[$counter][0]) && ($stats[$counter][0] === "+" || $stats[$counter][0] === "-"))) {
                        $new_stats[$counter] = ($newVal >= 0) ? "+{$newVal}" : 0;
                    } else {
                        $new_stats[$counter] = max(0, $newVal);
                    }
                }
            } else {
                $newVal = (int)$val;
                $new_stats[$counter] = max(0, $newVal);
            }
        }
    }

    if (!$force) {
        $stats_changes = array_merge($stats, $new_stats);
        return;
    }

    // Обновляем lastmember, если изменился numusers
    if (array_key_exists('numusers', $changes)) {
        $query = $db->simple_select("users", "id, username", "", [
            'order_by' => 'added',
            'order_dir' => 'DESC',
            'limit' => 1
        ]);
        $lastmember = $db->fetch_array($query);
        if ($lastmember) {
            $new_stats['lastuid'] = (int)$lastmember['id'];
            $new_stats['lastusername'] = htmlspecialchars_uni($lastmember['username'] ?? '');
        }
    }

    if (!empty($new_stats)) {
        if (is_array($stats)) {
            $stats = array_merge($stats, $new_stats);
        } else {
            $stats = $new_stats;
        }
    }

    // Подсчёты
    $torrents = tsrowcount('id', 'torrents');

    $query = $db->sql_query("SELECT COUNT(id) as totalseeders FROM peers WHERE seeder = 'yes'");
    $Result = $db->fetch_array($query);
    $stats['seeders'] = ts_nf((int)($Result['totalseeders'] ?? 0));

    $query = $db->sql_query("SELECT COUNT(id) as totalleechers FROM peers WHERE seeder = 'no'");
    $Result = $db->fetch_array($query);
    $stats['leechers'] = ts_nf((int)($Result['totalleechers'] ?? 0));

    $stats['peers'] = ts_nf(((int)$stats['seeders']) + ((int)$stats['leechers']));
    $stats['torrents'] = (int)$torrents;

    $result = $db->sql_query("SELECT SUM(downloaded) AS totaldl, SUM(uploaded) AS totalul FROM users");
    $row = $db->fetch_array($result);
    $stats['totaldownloaded'] = (int)($row['totaldl'] ?? 0);
    $stats['totaluploaded'] = (int)($row['totalul'] ?? 0);

    // Обновляем строку статистики за сегодня - исправляем mktime()
    $current_month = (int)date("m");
    $current_day = (int)date("j");
    $current_year = (int)date("Y");
    
    $todays_stats = [
        "dateline" => mktime(0, 0, 0, $current_month, $current_day, $current_year),
        "numusers" => (int)($stats['numusers'] ?? 0),
        "numthreads" => (int)($stats['numthreads'] ?? 0),
        "numposts" => (int)($stats['numposts'] ?? 0),
        "torrents" => (int)($stats['torrents'] ?? 0),
        "seeders" => (int)($stats['seeders'] ?? 0),
        "leechers" => (int)($stats['leechers'] ?? 0),
        "peers" => (int)($stats['peers'] ?? 0),
        "totaldownloaded" => (int)($stats['totaldownloaded'] ?? 0),
        "totaluploaded" => (int)($stats['totaluploaded'] ?? 0)
    ];

    $db->replace_query("stats", $todays_stats, "dateline");

    $cache->update("stats", $stats, "dateline");
    $stats_changes['inserted'] = true;
}







function unichr(int $c): string|false
{
    if($c <= 0x7F) {
        return chr($c);
    } elseif($c <= 0x7FF) {
        return chr(0xC0 | $c >> 6) . chr(0x80 | $c & 0x3F);
    } elseif($c <= 0xFFFF) {
        return chr(0xE0 | $c >> 12) . chr(0x80 | $c >> 6 & 0x3F)
                                . chr(0x80 | $c & 0x3F);
    } elseif($c <= 0x10FFFF) {
        return chr(0xF0 | $c >> 18) . chr(0x80 | $c >> 12 & 0x3F)
                                . chr(0x80 | $c >> 6 & 0x3F)
                                . chr(0x80 | $c & 0x3F);
    } else {
        return false;
    }
}

function email_already_in_use(string $email, int $uid = 0): bool
{
    global $db;

    $uid_string = "";
    if($uid) {
        $uid_string = " AND id != '".(int)$uid."'";
    }
    $query = $db->simple_select("users", "COUNT(email) as emails", "email = '".$db->escape_string($email)."'{$uid_string}");

    return $db->fetch_field($query, "emails") > 0;
}

function is_banned_username(string $username, bool $update_lastuse = false): bool
{
    global $db;
    $query = $db->simple_select('banfilters', 'filter, fid', "type='2'");
    while($banned_username = $db->fetch_array($query)) {
        // Make regular expression * match
        $banned_username['filter'] = str_replace('\*', '(.*)', preg_quote($banned_username['filter'], '#'));
        if(preg_match("#(^|\b){$banned_username['filter']}($|\b)#i", $username)) {
            // Updating last use
            if($update_lastuse == true) {
                $db->update_query("banfilters", ["lastuse" => TIMENOW], "fid='{$banned_username['fid']}'");
            }
            return true;
        }
    }
    return false;
}

function is_banned_email(string $email, bool $update_lastuse = false): bool
{
    global $cache, $db;

    $banned_cache = $cache->read("bannedemails");

    if($banned_cache === false) {
        // Failed to read cache, see if we can rebuild it
        $cache->update_bannedemails();
        $banned_cache = $cache->read("bannedemails");
    }

    if(is_array($banned_cache) && !empty($banned_cache)) {
        foreach($banned_cache as $banned_email) {
            // Make regular expression * match
            $banned_email['filter'] = str_replace('\*', '(.*)', preg_quote($banned_email['filter'], '#'));

            if(preg_match("#{$banned_email['filter']}#i", $email)) {
                // Updating last use
                if($update_lastuse == true) {
                    $db->update_query("banfilters", ["lastuse" => TIMENOW], "fid='{$banned_email['fid']}'");
                }
                return true;
            }
        }
    }

    return false;
}





function get_user(int $uid): array
{
    global $mybb, $db, $CURUSER;
    static $user_cache = [];

    // Проверяем текущего пользователя
    if(!empty($CURUSER) && $uid == $CURUSER['id']) {
        return is_array($CURUSER) ? $CURUSER : [];
    } 
    
    // Проверяем кэш
    elseif(isset($user_cache[$uid])) {
        return is_array($user_cache[$uid]) ? $user_cache[$uid] : [];
    } 
    
    // Загружаем из базы
    elseif($uid > 0) {
        $query = $db->simple_select("users", "*", "id = '{$uid}'");
        
        if ($db->num_rows($query) > 0) {
            $user_data = $db->fetch_array($query);
            $user_cache[$uid] = is_array($user_data) ? $user_data : [];
        } else {
            $user_cache[$uid] = [];
        }

        return $user_cache[$uid];
    }
    
    // Возвращаем пустой массив для невалидных ID
    return [];
}
















function user_permissions(?int $uid = null): array
{
    global $mybb, $cache, $groupscache, $user_cache, $CURUSER;

    // If no user id is specified, assume it is the current user
    if($uid === null) {
        $uid = $CURUSER['id'];
    }

    // Its a guest. Return the group permissions directly from cache
    if($uid == 0) {
        return $groupscache[1] ?? [];
    }

    // User id does not match current user, fetch permissions
    if($uid != $CURUSER['id']) {
        // We've already cached permissions for this user, return them.
        if(!empty($user_cache[$uid]['permissions'])) {
            return $user_cache[$uid]['permissions'];
        }

        // This user was not already cached, fetch their user information.
        if(empty($user_cache[$uid])) {
            $user_cache[$uid] = get_user($uid);
        }

        // Collect group permissions.
        $gid = $user_cache[$uid]['usergroup'].",".$user_cache[$uid]['additionalgroups'];
        $groupperms = usergroup_permissions($gid);

        // Store group permissions in user cache.
        $user_cache[$uid]['permissions'] = $groupperms;
        return $groupperms;
    }
    // This user is the current user, return their permissions
    else {
        return $mybb->usergroup;
    }
}

function usergroup_permissions(int|string $gid = 0): array
{
    global $cache, $groupscache, $grouppermignore, $groupzerogreater, $groupzerolesser, $groupxgreater, $grouppermbyswitch;

    if(!is_array($groupscache)) {
        $groupscache = $cache->read("usergroups") ?? [];
    }

    $groups = explode(",", (string)$gid);

    if(count($groups) == 1) {
        $groupscache[$gid]['all_usergroups'] = $gid;
        return $groupscache[$gid] ?? [];
    }

    $usergroup = [];
    $usergroup['all_usergroups'] = $gid;

    // Get those switch permissions from the first valid group.
    $permswitches_usergroup = [];
    $grouppermswitches = [];
    foreach(array_values($grouppermbyswitch) as $permvalue) {
        if(is_array($permvalue)) {
            foreach($permvalue as $perm) {
                $grouppermswitches[] = $perm;
            }
        } else {
            $grouppermswitches[] = $permvalue;
        }
    }
    $grouppermswitches = array_unique($grouppermswitches);
    foreach($groups as $gid) {
        if(trim($gid) == "" || empty($groupscache[$gid])) {
            continue;
        }
        foreach($grouppermswitches as $perm) {
            $permswitches_usergroup[$perm] = $groupscache[$gid][$perm] ?? null;
        }
        break;    // Only retieve the first available group's permissions as how following action does.
    }

    foreach($groups as $gid) {
        if(trim($gid) == "" || empty($groupscache[$gid])) {
            continue;
        }

        foreach($groupscache[$gid] as $perm => $access) {
            if(!in_array($perm, $grouppermignore)) {
                if(isset($usergroup[$perm])) {
                    $permbit = $usergroup[$perm];
                } else {
                    $permbit = "";
                }

                // permission type: 0 not a numerical permission, otherwise a numerical permission.
                // Positive value is for `greater is more` permission, negative for `lesser is more`.
                $perm_is_numerical = 0;
                $perm_numerical_lowerbound = 0;

                // 0 represents unlimited for most numerical group permissions (i.e. private message limit) so take that into account.
                if(in_array($perm, $groupzerogreater)) {
                    // 1 means a `0 or greater` permission. Value 0 means unlimited.
                    $perm_is_numerical = 1;
                }
                // Less is more for some numerical group permissions (i.e. post count required for using signature) so take that into account, too.
                else if(in_array($perm, $groupzerolesser)) {
                    // -1 means a `0 or lesser` permission. Value 0 means unlimited.
                    $perm_is_numerical = -1;
                }
                // Greater is more, but with a lower bound.
                else if(array_key_exists($perm, $groupxgreater)) {
                    // 2 means a general `greater` permission. Value 0 just means 0.
                    $perm_is_numerical = 2;
                    $perm_numerical_lowerbound = $groupxgreater[$perm];
                }

                if($perm_is_numerical != 0) {
                    $update_current_perm = true;

                    // Ensure it's an integer.
                    $access = (int)$access;
                    // Check if this permission should be activatived by another switch permission in current group.
                    if(array_key_exists($perm, $grouppermbyswitch)) {
                        if(!is_array($grouppermbyswitch[$perm])) {
                            $grouppermbyswitch[$perm] = [$grouppermbyswitch[$perm]];
                        }

                        $update_current_perm = $group_current_perm_enabled = $group_perm_enabled = false;
                        foreach($grouppermbyswitch[$perm] as $permswitch) {
                            if(!isset($groupscache[$gid][$permswitch])) {
                                continue;
                            }
                            $permswitches_current = $groupscache[$gid][$permswitch];

                            // Determin if the permission is enabled by switches from current group.
                            if($permswitches_current == 1 || $permswitches_current == "yes") {
                                $group_current_perm_enabled = true;
                            }
                            // Determin if the permission is enabled by switches from previously handled groups.
                            if($permswitches_usergroup[$permswitch] == 1 || $permswitches_usergroup[$permswitch] == "yes") {
                                $group_perm_enabled = true;
                            }
                        }

                        // Set this permission if not set yet.
                        if(!isset($usergroup[$perm])) {
                            $usergroup[$perm] = $access;
                        }

                        // If current group's setting enables the permission, we may need to update the user's permission.
                        if($group_current_perm_enabled) {
                            // Only update this permission if both its switch and current group switch are on.
                            if($group_perm_enabled) {
                                $update_current_perm = true;
                            }
                            // Override old useless value with value from current group.
                            else {
                                $usergroup[$perm] = $access;
                            }
                        }
                    }

                    // No switch controls this permission, or permission needs an update.
                    if($update_current_perm) {
                        switch($perm_is_numerical) {
                            case 1:
                            case -1:
                                if($access == 0 || $permbit === 0) {
                                    $usergroup[$perm] = 0;
                                    break;
                                }
                            default:
                                if($perm_is_numerical > 0 && $access > $permbit || $perm_is_numerical < 0 && $access < $permbit) {
                                    $usergroup[$perm] = $access;
                                }
                                break;
                        }
                    }

                    // Maybe oversubtle, database uses Unsigned on them, but enables usage of permission value with a lower bound.
                    if($usergroup[$perm] < $perm_numerical_lowerbound) {
                        $usergroup[$perm] = $perm_numerical_lowerbound;
                    }

                    // Work is done for numerical permissions.
                    continue;
                }

                if($access > $permbit || ($access == "yes" && $permbit == "no") || !$permbit) {
                    $usergroup[$perm] = $access;
                }
            }
        }

        foreach($permswitches_usergroup as $perm => $value) {
            $permswitches_usergroup[$perm] = $usergroup[$perm];
        }
    }

    return $usergroup;
}

function &get_my_mailhandler(bool $use_builtin = false): object
{
    global $mybb, $plugins, $mail_handler, $mail_message_id;
    static $my_mailhandler;
    static $my_mailhandler_builtin;

    $mail_parameters = "";
    
    if($use_builtin) {
        // If our built-in mail handler doesn't exist, create it.
        if(!is_object($my_mailhandler_builtin)) {
            require_once INC_PATH . "/class_mailhandler.php";

            // Using SMTP.
            if(isset($mail_handler) && $mail_handler == 'smtp') {
                require_once INC_PATH . "/mailhandlers/smtp.php";
                $my_mailhandler_builtin = new SmtpMail();
            }
            // Using PHP mail().
            else {
                require_once INC_PATH . "/mailhandlers/php.php";
                $my_mailhandler_builtin = new PhpMail();
                if(!empty($mail_parameters)) {
                    $my_mailhandler_builtin->additional_parameters = $mail_parameters;
                }
            }
        }

        if(isset($plugins) && is_object($plugins)) {
            $plugins->run_hooks('my_mailhandler_builtin_after_init', $my_mailhandler_builtin);
        }

        return $my_mailhandler_builtin;
    }

    // If our mail handler doesn't exist, create it.
    if(!is_object($my_mailhandler)) {
        require_once INC_PATH . "/class_mailhandler.php";

        if(isset($plugins) && is_object($plugins)) {
            $plugins->run_hooks('my_mailhandler_init', $my_mailhandler);
        }

        // If no plugin has ever created the mail handler, resort to use the built-in one.
        if(!is_object($my_mailhandler) || !($my_mailhandler instanceof MailHandler)) {
            $my_mailhandler = &get_my_mailhandler(true);
        }
    }

    return $my_mailhandler;
}

function my_mail(string $to, string $subject, string $message, string $from = "", string $charset = "", string $headers = "", bool $keep_alive = false, string $format = "text", string $message_text = "", string $return_email = ""): bool
{
    global $mybb, $plugins, $mail_handler;

    // Get our mail handler.
    $mail = &get_my_mailhandler();
    
    // If MyBB's built-in SMTP mail handler is used, set the keep alive bit accordingly.
    if($keep_alive == true && isset($mail->keep_alive) && isset($mail_handler) && $mail_handler == 'smtp') {
        require_once INC_PATH . "/class_mailhandler.php";
        require_once INC_PATH . "/mailhandlers/smtp.php";
        if($mail instanceof MailHandler && $mail instanceof SmtpMail) {
            $mail->keep_alive = true;
        }
    }

    // Following variables will help sequential plugins to determine how to process plugin hooks.
    // Mark this variable true if the hooked plugin has sent the mail, otherwise don't modify it.
    $is_mail_sent = false;
    // Mark this variable false if the hooked plugin doesn't suggest sequential plugins to continue processing.
    $continue_process = true;

    $my_mail_parameters = [
        'to' => &$to,
        'subject' => &$subject,
        'message' => &$message,
        'from' => &$from,
        'charset' => &$charset,
        'headers' => &$headers,
        'keep_alive' => &$keep_alive,
        'format' => &$format,
        'message_text' => &$message_text,
        'return_email' => &$return_email,
        'is_mail_sent' => &$is_mail_sent,
        'continue_process' => &$continue_process,
    ];

    if(isset($plugins) && is_object($plugins)) {
        $plugins->run_hooks('my_mail_pre_build_message', $my_mail_parameters);
    }

    // Build the mail message.
    $mail->build_message($to, $subject, $message, $from, $charset, $headers, $format, $message_text, $return_email);

    if(isset($plugins) && is_object($plugins)) {
        $plugins->run_hooks('my_mail_pre_send', $my_mail_parameters);
    }

    // Check if the hooked plugins still suggest to send the mail.
    if($continue_process) {
        $is_mail_sent = $mail->send();
    }

    if(isset($plugins) && is_object($plugins)) {
        $plugins->run_hooks('my_mail_post_send', $my_mail_parameters);
    }

    return $is_mail_sent;
}

function validate_email_format(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function get_bdays(int $in): array
{
    return [
        31,
        ($in % 4 == 0 && ($in % 100 > 0 || $in % 400 == 0) ? 29 : 28),
        31,
        30,
        31,
        30,
        31,
        31,
        30,
        31,
        30,
        31
    ];
}


define('MAX_SERIALIZED_INPUT_LENGTH', 10240);
define('MAX_SERIALIZED_ARRAY_LENGTH', 256);
define('MAX_SERIALIZED_ARRAY_DEPTH', 5);


function _safe_unserialize(string $str, bool $unlimited = true): mixed
{
    if(!$unlimited && strlen($str) > MAX_SERIALIZED_INPUT_LENGTH) {
        // input exceeds MAX_SERIALIZED_INPUT_LENGTH
        return false;
    }

    if(empty($str) || !is_string($str)) {
        return false;
    }

    $stack = $list = $expected = [];

    /*
     * states:
     *   0 - initial state, expecting a single value or array
     *   1 - terminal state
     *   2 - in array, expecting end of array or a key
     *   3 - in array, expecting value or another array
     */
    $state = 0;
    while($state != 1) {
        $type = $str[0] ?? '';

        if($type == '}') {
            $str = substr($str, 1);
        } else if($type == 'N' && ($str[1] ?? '') == ';') {
            $value = null;
            $str = substr($str, 2);
        } else if($type == 'b' && preg_match('/^b:([01]);/', $str, $matches)) {
            $value = $matches[1] == '1';
            $str = substr($str, 4);
        } else if($type == 'i' && preg_match('/^i:(-?[0-9]+);(.*)/s', $str, $matches)) {
            $value = (int)$matches[1];
            $str = $matches[2];
        } else if($type == 'd' && preg_match('/^d:(-?[0-9]+\.?[0-9]*(E[+-][0-9]+)?);(.*)/s', $str, $matches)) {
            $value = (float)$matches[1];
            $str = $matches[3];
        } else if($type == 's' && preg_match('/^s:([0-9]+):"(.*)/s', $str, $matches) && substr($matches[2], (int)$matches[1], 2) == '";') {
            $value = substr($matches[2], 0, (int)$matches[1]);
            $str = substr($matches[2], (int)$matches[1] + 2);
        } else if(
            $type == 'a' &&
            preg_match('/^a:([0-9]+):{(.*)/s', $str, $matches) &&
            ($unlimited || $matches[1] < MAX_SERIALIZED_ARRAY_LENGTH)
        ) {
            $expectedLength = (int)$matches[1];
            $str = $matches[2];
        } else {
            // object or unknown/malformed type
            return false;
        }

        switch($state) {
            case 3: // in array, expecting value or another array
                if($type == 'a') {
                    if(!$unlimited && count($stack) >= MAX_SERIALIZED_ARRAY_DEPTH) {
                        // array nesting exceeds MAX_SERIALIZED_ARRAY_DEPTH
                        return false;
                    }

                    $stack[] = &$list;
                    $list[$key] = [];
                    $list = &$list[$key];
                    $expected[] = $expectedLength;
                    $state = 2;
                    break;
                }
                if($type != '}') {
                    $list[$key] = $value;
                    $state = 2;
                    break;
                }

                // missing array value
                return false;

            case 2: // in array, expecting end of array or a key
                if($type == '}') {
                    if(count($list) < end($expected)) {
                        // array size less than expected
                        return false;
                    }

                    unset($list);
                    $list = &$stack[count($stack)-1];
                    array_pop($stack);

                    // go to terminal state if we're at the end of the root array
                    array_pop($expected);
                    if(count($expected) == 0) {
                        $state = 1;
                    }
                    break;
                }
                if($type == 'i' || $type == 's') {
                    if(!$unlimited && count($list) >= MAX_SERIALIZED_ARRAY_LENGTH) {
                        // array size exceeds MAX_SERIALIZED_ARRAY_LENGTH
                        return false;
                    }
                    if(count($list) >= end($expected)) {
                        // array size exceeds expected length
                        return false;
                    }

                    $key = $value;
                    $state = 3;
                    break;
                }

                // illegal array index type
                return false;

            case 0: // expecting array or value
                if($type == 'a') {
                    if(!$unlimited && count($stack) >= MAX_SERIALIZED_ARRAY_DEPTH) {
                        // array nesting exceeds MAX_SERIALIZED_ARRAY_DEPTH
                        return false;
                    }

                    $data = [];
                    $list = &$data;
                    $expected[] = $expectedLength;
                    $state = 2;
                    break;
                }
                if($type != '}') {
                    $data = $value;
                    $state = 1;
                    break;
                }

                // not in array
                return false;
        }
    }

    if(!empty($str)) {
        // trailing data in input
        return false;
    }
    return $data;
}

function my_unserialize(string $str, bool $unlimited = true): mixed
{
    // Ensure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
    if(function_exists('mb_internal_encoding') && (((int)ini_get('mbstring.func_overload')) & 2)) {
        $mbIntEnc = mb_internal_encoding();
        mb_internal_encoding('ASCII');
    }

    $out = _safe_unserialize($str, $unlimited);

    if(isset($mbIntEnc)) {
        mb_internal_encoding($mbIntEnc);
    }

    return $out;
}



function my_set_array_cookie(string $name, string $id, mixed $value, string|int $expires = ""): void
{
    global $mybb;

    if(isset($mybb->cookies['mybb'][$name])) {
        $newcookie = my_unserialize($mybb->cookies['mybb'][$name], false);
    } else {
        $newcookie = [];
    }

    $newcookie[$id] = $value;
    $newcookie = my_serialize($newcookie);
    my_setcookie("mybb[$name]", addslashes($newcookie), $expires);

    if(isset($mybb->cookies['mybb']) && !is_array($mybb->cookies['mybb'])) {
        $mybb->cookies['mybb'] = [];
    }

    // Make sure our current viarables are up-to-date as well
    $mybb->cookies['mybb'][$name] = $newcookie;
}

function my_get_array_cookie(string $name, string $id): mixed
{
    global $mybb;

    if(!isset($mybb->cookies['mybb'][$name])) {
        return false;
    }

    $cookie = my_unserialize($mybb->cookies['mybb'][$name], false);

    if(is_array($cookie) && isset($cookie[$id])) {
        return $cookie[$id];
    } else {
        return 0;
    }
}

function my_unsetcookie(string $name): void
{
    global $mybb;

    $expires = -3600;
    my_setcookie($name, "", $expires);

    unset($mybb->cookies[$name]);
}





function my_setcookie(string $name, string $value = "", string|int|null $expires = "", bool $httponly = false, string $samesite = ""): void
{
    global $mybb, $cookiedomain, $cookiepath, $cookieprefix, $cookiesamesiteflag, $cookiesecureflag;

    if(!$cookiepath) {
        $cookiepath = "/";
    }

    // Handle null expires value
    if ($expires === null) {
        $expires = "";
    }

    if($expires == -1) {
        $expires = 0;
    } elseif($expires == "" || $expires == null) {
        $expires = TIMENOW + (60*60*24*365); // Make the cookie expire in a years time
    } else {
        $expires = TIMENOW + (int)$expires;
    }

    $cookiepath = str_replace(["\n","\r"], "", $cookiepath);
    $cookiedomain = str_replace(["\n","\r"], "", $cookiedomain);
    $cookieprefix = str_replace(["\n","\r", " "], "", $cookieprefix);

    // Versions of PHP prior to 5.2 do not support HttpOnly cookies and IE is buggy when specifying a blank domain so set the cookie manually
    $cookie = "Set-Cookie: {$cookieprefix}{$name}=".urlencode($value);

    if($expires > 0) {
        $cookie .= "; expires=".@gmdate('D, d-M-Y H:i:s \\G\\M\\T', $expires);
    }

    if(!empty($cookiepath)) {
        $cookie .= "; path={$cookiepath}";
    }

    if(!empty($cookiedomain)) {
        $cookie .= "; domain={$cookiedomain}";
    }

    if($httponly) {
        $cookie .= "; HttpOnly";
    }

    if($samesite != "" && $cookiesamesiteflag) {
        $samesite = strtolower($samesite);

        if($samesite == "lax" || $samesite == "strict") {
            $cookie .= "; SameSite=".$samesite;
        }
    }

    if($cookiesecureflag) {
        $cookie .= "; Secure";
    }

    $mybb->cookies[$name] = $value;

    header($cookie, false);
}





function trim_blank_chrs(string $string, string $charlist = ""): string
{
    $hex_chrs = [
        0x09 => 1, // \x{0009}
        0x0A => 1, // \x{000A}
        0x0B => 1, // \x{000B}
        0x0D => 1, // \x{000D}
        0x20 => 1, // \x{0020}
        0xC2 => [0x81 => 1, 0x8D => 1, 0x90 => 1, 0x9D => 1, 0xA0 => 1, 0xAD => 1], // \x{0081}, \x{008D}, \x{0090}, \x{009D}, \x{00A0}, \x{00AD}
        0xCC => [0xB7 => 1, 0xB8 => 1], // \x{0337}, \x{0338}
        0xE1 => [0x85 => [0x9F => 1, 0xA0 => 1], 0x9A => [0x80 => 1], 0xA0 => [0x8E => 1]], // \x{115F}, \x{1160}, \x{1680}, \x{180E}
        0xE2 => [0x80 => [0x80 => 1, 0x81 => 1, 0x82 => 1, 0x83 => 1, 0x84 => 1, 0x85 => 1, 0x86 => 1, 0x87 => 1, 0x88 => 1, 0x89 => 1, 0x8A => 1, 0x8B => 1, 0x8C => 1, 0x8D => 1, 0x8E => 1, 0x8F => 1, // \x{2000} - \x{200F}
            0xA8 => 1, 0xA9 => 1, 0xAA => 1, 0xAB => 1, 0xAC => 1, 0xAD => 1, 0xAE => 1, 0xAF => 1], // \x{2028} - \x{202F}
            0x81 => [0x9F => 1]], // \x{205F}
        0xE3 => [0x80 => [0x80 => 1], // \x{3000}
            0x85 => [0xA4 => 1]], // \x{3164}
        0xEF => [0xBB => [0xBF => 1], // \x{FEFF}
            0xBE => [0xA0 => 1], // \x{FFA0}
            0xBF => [0xB9 => 1, 0xBA => 1, 0xBB => 1]], // \x{FFF9} - \x{FFFB}
    ];

    $hex_chrs_rev = [
        0x09 => 1, // \x{0009}
        0x0A => 1, // \x{000A}
        0x0B => 1, // \x{000B}
        0x0D => 1, // \x{000D}
        0x20 => 1, // \x{0020}
        0x81 => [0xC2 => 1, 0x80 => [0xE2 => 1]], // \x{0081}, \x{2001}
        0x8D => [0xC2 => 1, 0x80 => [0xE2 => 1]], // \x{008D}, \x{200D}
        0x90 => [0xC2 => 1], // \x{0090}
        0x9D => [0xC2 => 1], // \x{009D}
        0xA0 => [0xC2 => 1, 0x85 => [0xE1 => 1], 0x81 => [0xE2 => 1], 0xBE => [0xEF => 1]], // \x{00A0}, \x{1160}, \x{2060}, \x{FFA0}
        0xAD => [0xC2 => 1, 0x80 => [0xE2 => 1]], // \x{00AD}, \x{202D}
        0xB8 => [0xCC => 1], // \x{0338}
        0xB7 => [0xCC => 1], // \x{0337}
        0x9F => [0x85 => [0xE1 => 1], 0x81 => [0xE2 => 1]], // \x{115F}, \x{205F}
        0x80 => [0x9A => [0xE1 => 1], 0x80 => [0xE2 => 1, 0xE3 => 1]], // \x{1680}, \x{2000}, \x{3000}
        0x8E => [0xA0 => [0xE1 => 1], 0x80 => [0xE2 => 1]], // \x{180E}, \x{200E}
        0x82 => [0x80 => [0xE2 => 1]], // \x{2002}
        0x83 => [0x80 => [0xE2 => 1]], // \x{2003}
        0x84 => [0x80 => [0xE2 => 1]], // \x{2004}
        0x85 => [0x80 => [0xE2 => 1]], // \x{2005}
        0x86 => [0x80 => [0xE2 => 1]], // \x{2006}
        0x87 => [0x80 => [0xE2 => 1]], // \x{2007}
        0x88 => [0x80 => [0xE2 => 1]], // \x{2008}
        0x89 => [0x80 => [0xE2 => 1]], // \x{2009}
        0x8A => [0x80 => [0xE2 => 1]], // \x{200A}
        0x8B => [0x80 => [0xE2 => 1]], // \x{200B}
        0x8C => [0x80 => [0xE2 => 1]], // \x{200C}
        0x8F => [0x80 => [0xE2 => 1]], // \x{200F}
        0xA8 => [0x80 => [0xE2 => 1]], // \x{2028}
        0xA9 => [0x80 => [0xE2 => 1]], // \x{2029}
        0xAA => [0x80 => [0xE2 => 1]], // \x{202A}
        0xAB => [0x80 => [0xE2 => 1]], // \x{202B}
        0xAC => [0x80 => [0xE2 => 1]], // \x{202C}
        0xAE => [0x80 => [0xE2 => 1]], // \x{202E}
        0xAF => [0x80 => [0xE2 => 1]], // \x{202F}
        0xA4 => [0x85 => [0xE3 => 1]], // \x{3164}
        0xBF => [0xBB => [0xEF => 1]], // \x{FEFF}
        0xB9 => [0xBF => [0xEF => 1]], // \x{FFF9}
        0xBA => [0xBF => [0xEF => 1]], // \x{FFFA}
        0xBB => [0xBF => [0xEF => 1]], // \x{FFFB}
    ];

    // Start from the beginning and work our way in
    $i = 0;
    do {
        // Check to see if we have matched a first character in our utf-8 array
        $offset = match_sequence($string, $hex_chrs);
        if(!$offset) {
            // If not, then we must have a "good" character and we don't need to do anymore processing
            break;
        }
        $string = substr($string, $offset);
    } while(++$i);

    // Start from the end and work our way in
    $string = strrev($string);
    $i = 0;
    do {
        // Check to see if we have matched a first character in our utf-8 array
        $offset = match_sequence($string, $hex_chrs_rev);
        if(!$offset) {
            // If not, then we must have a "good" character and we don't need to do anymore processing
            break;
        }
        $string = substr($string, $offset);
    } while(++$i);
    $string = strrev($string);

    if($charlist) {
        $string = trim($string, $charlist);
    } else {
        $string = trim($string);
    }

    return $string;
}

function match_sequence(string $string, array $array, int $i = 0, int $n = 0): int
{
    if($string === "") {
        return 0;
    }

    $ord = ord($string[$i]);
    if(array_key_exists($ord, $array)) {
        $level = $array[$ord];
        ++$n;
        if(is_array($level)) {
            ++$i;
            return match_sequence($string, $level, $i, $n);
        }
        return $n;
    }

    return 0;
}

function get_memory_usage(): int|false
{
    if(function_exists('memory_get_peak_usage')) {
        return memory_get_peak_usage(true);
    } elseif(function_exists('memory_get_usage')) {
        return memory_get_usage(true);
    }
    return false;
}

function get_server_load(): string
{
    global $mybb, $lang;

    $serverload = [];

    // DIRECTORY_SEPARATOR checks if running windows
    if(DIRECTORY_SEPARATOR != '\\') {
        if(function_exists("sys_getloadavg")) {
            // sys_getloadavg() will return an array with [0] being load within the last minute.
            $serverload = sys_getloadavg();
            $serverload[0] = round($serverload[0], 4);
        } else if(@file_exists("/proc/loadavg") && $load = @file_get_contents("/proc/loadavg")) {
            $serverload = explode(" ", $load);
            $serverload[0] = round($serverload[0], 4);
        }
        if(!is_numeric($serverload[0] ?? null)) {
            if($mybb->safemode ?? false) {
                return 'unknown';
            }

            // Suhosin likes to throw a warning if exec is disabled then die - weird
            if($func_blacklist = @ini_get('suhosin.executor.func.blacklist')) {
                if(str_contains(",".$func_blacklist.",", 'exec')) {
                    return 'unknown';
                }
            }
            // PHP disabled functions?
            if($func_blacklist = @ini_get('disable_functions')) {
                if(str_contains(",".$func_blacklist.",", 'exec')) {
                    return 'unknown';
                }
            }

            $load = @exec("uptime");
            $load = explode("load average: ", $load);
            $serverload = explode(",", $load[1] ?? '');
            if(!is_array($serverload)) {
                return 'unknown';
            }
        }
    } else {
        return 'unknown';
    }

    $returnload = trim($serverload[0] ?? '');

    return $returnload;
}

function _safe_serialize(mixed $value): string|false
{
    if(is_null($value)) {
        return 'N;';
    }

    if(is_bool($value)) {
        return 'b:'.(int)$value.';';
    }

    if(is_int($value)) {
        return 'i:'.$value.';';
    }

    if(is_float($value)) {
        return 'd:'.str_replace(',', '.', $value).';';
    }

    if(is_string($value)) {
        return 's:'.strlen($value).':"'.$value.'";';
    }

    if(is_array($value)) {
        $out = '';
        foreach($value as $k => $v) {
            $out .= _safe_serialize($k) . _safe_serialize($v);
        }

        return 'a:'.count($value).':{'.$out.'}';
    }

    // safe_serialize cannot my_serialize resources or objects
    return false;
}

function my_serialize(mixed $value): string|false
{
    // ensure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
    if(function_exists('mb_internal_encoding') && (((int)ini_get('mbstring.func_overload')) & 2)) {
        $mbIntEnc = mb_internal_encoding();
        mb_internal_encoding('ASCII');
    }

    $out = _safe_serialize($value);
    if(isset($mbIntEnc)) {
        mb_internal_encoding($mbIntEnc);
    }

    return $out;
}

function get_current_location(bool $fields = false, array $ignore = [], bool $quick = false): string|array
{
    global $mybb;

    if(defined("MYBB_LOCATION")) {
        return MYBB_LOCATION;
    }

    if(!empty($_SERVER['SCRIPT_NAME'])) {
        $location = htmlspecialchars_uni($_SERVER['SCRIPT_NAME']);
    } elseif(!empty($_SERVER['PHP_SELF'])) {
        $location = htmlspecialchars_uni($_SERVER['PHP_SELF']);
    } elseif(!empty($_ENV['PHP_SELF'])) {
        $location = htmlspecialchars_uni($_ENV['PHP_SELF']);
    } elseif(!empty($_SERVER['PATH_INFO'])) {
        $location = htmlspecialchars_uni($_SERVER['PATH_INFO']);
    } else {
        $location = htmlspecialchars_uni($_ENV['PATH_INFO'] ?? '');
    }

    if($quick) {
        return $location;
    }

    if($fields == true) {
        $form_html = '';
        if(!empty($mybb->input)) {
            foreach($mybb->input as $name => $value) {
                if(in_array($name, $ignore) || is_array($name) || is_array($value)) {
                    continue;
                }

                $form_html .= "<input type=\"hidden\" name=\"".htmlspecialchars_uni($name)."\" value=\"".htmlspecialchars_uni($value)."\" />\n";
            }
        }

        return ['location' => $location, 'form_html' => $form_html, 'form_method' => $mybb->request_method];
    } else {
        $parameters = [];

        if(isset($_SERVER['QUERY_STRING'])) {
            $current_query_string = $_SERVER['QUERY_STRING'];
        } else if(isset($_ENV['QUERY_STRING'])) {
            $current_query_string = $_ENV['QUERY_STRING'];
        } else {
            $current_query_string = '';
        }

        parse_str($current_query_string, $current_parameters);

        foreach($current_parameters as $name => $value) {
            if(!in_array($name, $ignore)) {
                $parameters[$name] = $value;
            }
        }

        if($mybb->request_method === 'post') {
            $post_array = ['action', 'fid', 'pid', 'tid', 'uid', 'eid'];

            foreach($post_array as $var) {
                if(isset($_POST[$var]) && !in_array($var, $ignore)) {
                    $parameters[$var] = $_POST[$var];
                }
            }
        }

        if(!empty($parameters)) {
            $location .= '?'.http_build_query($parameters, '', '&amp;');
        }

        return $location;
    }
}

function dec_to_utf8(int $src): string|false
{
    $dest = '';

    if($src < 0) {
        return false;
    } elseif($src <= 0x007f) {
        $dest .= chr($src);
    } elseif($src <= 0x07ff) {
        $dest .= chr(0xc0 | ($src >> 6));
        $dest .= chr(0x80 | ($src & 0x003f));
    } elseif($src <= 0xffff) {
        $dest .= chr(0xe0 | ($src >> 12));
        $dest .= chr(0x80 | (($src >> 6) & 0x003f));
        $dest .= chr(0x80 | ($src & 0x003f));
    } elseif($src <= 0x10ffff) {
        $dest .= chr(0xf0 | ($src >> 18));
        $dest .= chr(0x80 | (($src >> 12) & 0x3f));
        $dest .= chr(0x80 | (($src >> 6) & 0x3f));
        $dest .= chr(0x80 | ($src & 0x3f));
    } else {
        // Out of range
        return false;
    }

    return $dest;
}




function my_strlen(?string $string): int
{
    global $lang, $charset;

    $string = $string ?? '';
    
    $string = preg_replace("#&\#([0-9]+);#", "-", $string);

    if(strtolower($charset) == "utf-8") {
        // Get rid of any excess RTL and LTR override for they are the workings of the devil
        $string = str_replace(dec_to_utf8(8238), "", $string);
        $string = str_replace(dec_to_utf8(8237), "", $string);

        // Remove dodgy whitespaces
        $string = str_replace(chr(0xCA), "", $string);
    }
    $string = trim($string);

    if(function_exists("mb_strlen")) {
        $string_length = mb_strlen($string);
    } else {
        $string_length = strlen($string);
    }

    return $string_length;
}



function generate_post_check(int $rotation_shift = 0): string
{
    global $mybb, $session, $CURUSER;

    $rotation_interval = 6 * 3600;
    $rotation = floor(TIMENOW / $rotation_interval) + $rotation_shift;

    $seed = (string)$rotation;

    if (isset($CURUSER) && isset($CURUSER['id'])) {
        $seed .= $CURUSER['loginkey'].$CURUSER['salt'].$CURUSER['added'];
    } else {
       $seed .= $session->sid;
    }

    if(defined('IN_ADMINCP')) {
        $seed .= 'ADMINCP';
    }

    $seed .= 'i3SenbCqQPM26ZRpoQOQghYaYQFYFn2Z';

    return md5($seed);
}

function verify_post_check(string $code, bool $silent = false): bool
{
    global $lang;
    if(
        generate_post_check() !== $code &&
        generate_post_check(-1) !== $code &&
        generate_post_check(-2) !== $code &&
        generate_post_check(-3) !== $code
    ) {
        if($silent == true) {
            return false;
        } else {
            if(defined("IN_ADMINCP")) {
                return false;
            } else {
                stderr('Authorization code mismatch. Are you accessing this function correctly? Please go back and try again');
            }
        }
    } else {
        return true;
    }
}

function secure_binary_seed_rng(int $bytes): ?string
{
    $output = null;

    if(version_compare(PHP_VERSION, '7.0', '>=')) {
        try {
            $output = random_bytes($bytes);
        } catch (Exception $e) {
        }
    }

    if(strlen($output ?? '') < $bytes) {
        if(@is_readable('/dev/urandom') && ($handle = @fopen('/dev/urandom', 'rb'))) {
            $output = @fread($handle, $bytes);
            @fclose($handle);
        }
    } else {
        return $output;
    }

    if(strlen($output ?? '') < $bytes) {
        if(function_exists('mcrypt_create_iv')) {
            if (DIRECTORY_SEPARATOR == '/') {
                $source = MCRYPT_DEV_URANDOM;
            } else {
                $source = MCRYPT_RAND;
            }

            $output = @mcrypt_create_iv($bytes, $source);
        }
    } else {
        return $output;
    }

    if(strlen($output ?? '') < $bytes) {
        if(function_exists('openssl_random_pseudo_bytes')) {
            // PHP <5.3.4 had a bug which makes that function unusable on Windows
            if ((DIRECTORY_SEPARATOR == '/') || version_compare(PHP_VERSION, '5.3.4', '>=')) {
                $output = openssl_random_pseudo_bytes($bytes, $crypto_strong);
                if ($crypto_strong == false) {
                    $output = null;
                }
            }
        }
    } else {
        return $output;
    }

    if(strlen($output ?? '') < $bytes) {
        if(class_exists('COM')) {
            try {
                $CAPI_Util = new COM('CAPICOM.Utilities.1');
                if(is_callable([$CAPI_Util, 'GetRandom'])) {
                    $output = $CAPI_Util->GetRandom($bytes, 0);
                }
            } catch (Exception $e) {
            }
        }
    } else {
        return $output;
    }

    if(strlen($output ?? '') < $bytes) {
        // Close to what PHP basically uses internally to seed, but not quite.
        $unique_state = microtime().@getmypid();

        $rounds = ceil($bytes / 16);

        for($i = 0; $i < $rounds; $i++) {
            $unique_state = md5(microtime().$unique_state);
            $output .= md5($unique_state);
        }

        $output = substr($output, 0, ($bytes * 2));

        $output = pack('H*', $output);

        return $output;
    } else {
        return $output;
    }
}

function secure_seed_rng(): int
{
    $bytes = PHP_INT_SIZE;

    do {
        $output = secure_binary_seed_rng($bytes);

        // convert binary data to a decimal number
        if ($bytes == 4) {
            $elements = unpack('i', $output ?? '');
            $output = abs($elements[1] ?? 0);
        } else {
            $elements = unpack('N2', $output ?? '');
            $output = abs($elements[1] << 32 | $elements[2] ?? 0);
        }

    } while($output > PHP_INT_MAX);

    return $output;
}

function my_rand(int $min = 0, int $max = PHP_INT_MAX): int
{
    // backward compatibility
    if($max < $min) {
        $min = 0;
        $max = PHP_INT_MAX;
    }

    if(version_compare(PHP_VERSION, '7.0', '>=')) {
        try {
            $result = random_int($min, $max);
        } catch (Exception $e) {
        }

        if(isset($result)) {
            return $result;
        }
    }

    $seed = secure_seed_rng();

    $distance = $max - $min;
    return $min + floor($distance * ($seed / PHP_INT_MAX) );
}

function random_str(int $length = 8, bool $complex = false): string
{
    $set = array_merge(range(0, 9), range('A', 'Z'), range('a', 'z'));
    $str = [];

    // Complex strings have always at least 3 characters, even if $length < 3
    if($complex == true) {
        // At least one number
        $str[] = $set[my_rand(0, 9)];

        // At least one big letter
        $str[] = $set[my_rand(10, 35)];

        // At least one small letter
        $str[] = $set[my_rand(36, 61)];

        $length -= 3;
    }

    for($i = 0; $i < $length; ++$i) {
        $str[] = $set[my_rand(0, 61)];
    }

    // Make sure they're in random order and convert them to a string
    shuffle($str);

    return implode($str);
}

function alt_trow(bool $reset = false): string
{
    global $alttrow;

    if($alttrow == "trow1" && !$reset) {
        $trow = "trow2";
    } else {
        $trow = "trow1";
    }

    $alttrow = $trow;

    return $trow;
}

function rebuild_settings(): void
{
    global $db, $mybb;

    $query = $db->simple_select("settings", "value, name", "", [
        'order_by' => 'sid',
        'order_dir' => 'ASC',
    ]);

    $settings = '';
    while($setting = $db->fetch_array($query)) {
        $setting['name'] = addcslashes($setting['name'], "\\'");
        $setting['value'] = addcslashes($setting['value'], '\\"$');
        $settings .= "\${$setting['name']} = \"{$setting['value']}\";\n";
    }

    $settings = "<"."?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n";

    file_put_contents(INC_PATH.'/settings.php', $settings, LOCK_EX);
}

function get_comment_link(mixed $pid, mixed $tid = 0): string
{
    $pid = (int)$pid;
    $tid = (int)$tid;
    
    $link = match(true) {
        $tid > 0 => str_replace(
            ["{id}", "{pid}"], 
            [(string)$tid, (string)$pid], 
            TORRENT_URL_COMMENT
        ),
        default => str_replace("{pid}", (string)$pid, COMMENT_URL)
    };
    
    return htmlspecialchars_uni($link);
}





function get_torrent_link(int|string $tid = 0, int $page = 0, string $action = ''): string
{
    $tid = (int)$tid;
    
    if($page > 1) {
        if($action) {
            $link = TORRENT_URL_ACTION;
            $link = str_replace("{action}", $action, $link);
        } else {
            $link = TORRENT_URL_PAGED;
        }
        $link = str_replace("{id}", (string)$tid, $link);
        $link = str_replace("{page}", (string)$page, $link);
        return htmlspecialchars_uni($link);
    } else {
        if($action) {
            $link = TORRENT_URL_ACTION;
            $link = str_replace("{action}", $action, $link);
        } else {
            $link = TORRENT_URL;
        }
        $link = str_replace("{id}", (string)$tid, $link);
        return htmlspecialchars_uni($link);
    }
}





function get_profile_link(int|string $uid = 0, int $page = 0, string $action = ''): string
{
    $uid = (int)$uid;
    
    if($page > 1) {
        if($action) {
            $link = PROFILE_URL_ACTION;
            $link = str_replace("{action}", $action, $link);
        } else {
            $link = PROFILE_URL_PAGED;
        }
        $link = str_replace("{id}", (string)$uid, $link);
        $link = str_replace("{page}", (string)$page, $link);
        return htmlspecialchars_uni($link);
    } else {
        if($action) {
            $link = PROFILE_URL_ACTION;
            $link = str_replace("{action}", $action, $link);
        } else {
            $link = PROFILE_URL;
        }
        $link = str_replace("{id}", (string)$uid, $link);
        return htmlspecialchars_uni($link);
    }
}




function get_download_link(int|string $uid = 0): string
{
    $uid = (int)$uid;
    $link = str_replace("{id}", (string)$uid, DOWNLOAD_URL);
    return htmlspecialchars_uni($link);
}



function build_profile_link(?string $username = "", int|string $uid = 0, string $target = "", string $onclick = ""): string
{
    global $mybb, $lang, $BASEURL;

    // Если username равен null, устанавливаем пустую строку
    $username = $username ?? "";
    $uid = (int)$uid;

    if(!$username && $uid == 0) {
        // Return Guest phrase for no UID, no guest nickname
        return htmlspecialchars_uni('guest');
    } elseif($uid == 0) {
        // Return the guest's nickname if user is a guest but has a nickname
        return $username;
    } else {
        // Build the profile link for the registered user
        if(!empty($target)) {
            $target = " target=\"{$target}\"";
        }

        if(!empty($onclick)) {
            $onclick = " onclick=\"{$onclick}\"";
        }

        return "<a href=\"{$BASEURL}/".get_profile_link($uid)."\"{$target}{$onclick}>{$username}</a>";
    }
}




function cache_forums(bool $force = false): array
{
    global $forum_cache, $cache;

    if($force == true) {
        $forum_cache = $cache->read("forums", 1);
        return $forum_cache ?? [];
    }

    if(!$forum_cache) {
        $forum_cache = $cache->read("forums");
        if(!$forum_cache) {
            $cache->update_forums();
            $forum_cache = $cache->read("forums", 1);
        }
    }
    return $forum_cache ?? [];
}






function my_strpos(string $haystack, string $needle, int $offset = 0): int|false
{
    if($needle == '') {
        return false;
    }

    if(function_exists("mb_strpos")) {
        $position = mb_strpos($haystack, $needle, $offset);
    } else {
        $position = strpos($haystack, $needle, $offset);
    }

    return $position;
}

function my_strtolower(string $string): string
{
    if(function_exists("mb_strtolower")) {
        $string = mb_strtolower($string);
    } else {
        $string = strtolower($string);
    }

    return $string;
}


function run_shutdown(): void
{
    global $config, $db, $cache, $plugins, $error_handler, $shutdown_functions, $shutdown_queries, $done_shutdown, $mybb;

    if($done_shutdown == true || !$config || (isset($error_handler) && $error_handler->has_errors)) {
        return;
    }

    if(empty($shutdown_queries) && empty($shutdown_functions)) {
        // Nothing to do
        return;
    }

    // Missing the core? Build
    if(!is_object($mybb)) {
        require_once INC_PATH."/class_core.php";
        $mybb = new MyBB;

        // Load the settings
        require INC_PATH."/settings.php";
    }

    // If our DB has been deconstructed already (bad PHP 5.2.0), reconstruct
    if(!is_object($db)) {
        if(!isset($config) || empty($config['database']['type'])) {
            require INC_PATH."/config.php";
        }

        if(isset($config)) {
            // Load DB interface
            require_once INC_PATH."/db_base.php";
            require_once INC_PATH . '/AbstractPdoDbDriver.php';

            require_once INC_PATH."/db_".$config['database']['type'].".php";
            switch($config['database']['type']) {
                case "sqlite":
                    $db = new DB_SQLite;
                    break;
                case "pgsql":
                    $db = new DB_PgSQL;
                    break;
                case "pgsql_pdo":
                    $db = new PostgresPdoDbDriver();
                    break;
                case "mysqli":
                    $db = new DB_MySQLi;
                    break;
                case "mysql_pdo":
                    $db = new MysqlPdoDbDriver();
                    break;
                default:
                    $db = new DB_MySQL;
            }

            $db->connect($config['database']);
            if(!defined("TABLE_PREFIX")) {
                define("TABLE_PREFIX", $config['database']['table_prefix']);
            }
            $db->set_table_prefix(TABLE_PREFIX);
        }
    }

    // Cache object deconstructed? reconstruct
    if(!is_object($cache)) {
        require_once INC_PATH."/class_datacache.php";
        $cache = new datacache;
        $cache->cache();
    }
    
    $no_plugins = "0";
    
    // And finally.. plugins
    if(!is_object($plugins) && !defined("NO_PLUGINS") && !($no_plugins == 1)) {
        require_once INC_PATH."/class_plugins.php";
        $plugins = new pluginSystem;
        $plugins->load();
    }

    // We have some shutdown queries needing to be run
    if(is_array($shutdown_queries)) {
        // Loop through and run them all
        foreach($shutdown_queries as $query) {
            $db->write_query($query);
        }
    }

    // Run any shutdown functions if we have them
    if(is_array($shutdown_functions)) {
        foreach($shutdown_functions as $function) {
            call_user_func_array($function['function'], $function['arguments']);
        }
    }

    $done_shutdown = true;
}

function usergroup_displaygroup(int $gid): array
{
    global $cache, $groupscache, $displaygroupfields;

    if(!is_array($groupscache)) {
        $groupscache = $cache->read("usergroups") ?? [];
    }

    $displaygroup = [];
    $group = $groupscache[$gid] ?? [];

    foreach($displaygroupfields as $field) {
        $displaygroup[$field] = $group[$field] ?? null;
    }

    return $displaygroup;
}





function format_name(string $username, int|string $usergroup, int|string|null $displaygroup = 0): string
{
    global $groupscache, $cache, $plugins;

    static $formattednames = [];

    if(!isset($formattednames[$username])) {
        if(!is_array($groupscache)) {
            $groupscache = $cache->read("usergroups") ?? [];
        }

        // Convert to integers, handle null
        $usergroup = (int)$usergroup;
        $displaygroup = $displaygroup === null ? 0 : (int)$displaygroup;

        if($displaygroup != 0) {
            $usergroup = $displaygroup;
        }

        $format = "{username}";

        if(isset($groupscache[$usergroup])) {
            $ugroup = $groupscache[$usergroup];

            if(str_contains($ugroup['namestyle'], "{username}")) {
                $format = $ugroup['namestyle'];
            }
        }

        $format = stripslashes($format);

        $parameters = compact('username', 'usergroup', 'displaygroup', 'format');

        $parameters = $plugins->run_hooks('format_name', $parameters);

        $format = $parameters['format'];

        $formattednames[$username] = str_replace("{username}", $username, $format);
    }

    return $formattednames[$username];
}



function TS_Match(string $string, string $find): bool
{
    return str_contains($string, $find);
}

function TS_Global(string $name = ""): string|array
{
    return isset($_GET[$name]) ? 
        (!is_array($_GET[$name]) ? trim($_GET[$name]) : $_GET[$name]) : 
        (isset($_POST[$name]) ? 
            (!is_array($_POST[$name]) ? trim($_POST[$name]) : $_POST[$name]) : 
            "");
}

function show_notice(string $notice = '', bool $iserror = false, string $title = '', string $BR = '<br />'): string
{
    global $BASEURL, $lang;
    
    $imagepath = $BASEURL . '/include/templates/default/images/';
    $lastword = $iserror ? 'e' : 'n';
    $uniqeid = md5((string)time());
    
    return '
    <script type="text/javascript">
        function ts_show_tag(id, status)
        {
            if (TSGetID(id)) {
                if (status === true || status === false) {
                    TSGetID(id).style.display = status ? "none" : "";
                } else {
                    TSGetID(id).style.display = TSGetID(id).style.display === "" ? "none" : "";
                }
            }
        }
    </script>
    <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/notification.css" type="text/css" media="screen" />
    <div class="notification-border-' . $lastword . '" id="notification_' . $uniqeid . '" align="center">
        <table class="notification-th-' . $lastword . '" border="0" cellpadding="2" cellspacing="0">
            <tbody>
                <tr>
                    <td align="left" width="100%" class="none">
                    &nbsp;<img src="' . $imagepath . 'notification_' . $lastword . '.gif" alt="" align="top" border="0" height="14" width="14" />&nbsp;<span class="notification-title-' . $lastword . '" />' . ($title ?: $lang->global['sys_message']) . '</span>
                    </td>
                    <td class="none"><img src="' . $imagepath . 'notification_close.gif" alt="" onclick="ts_show_tag(\'notification_' . $uniqeid . '\', true);" class="hand" border="0" height="13" width="13" /></td>
                </tr>
            </tbody>
        </table>
        <div class="notification-body">
            ' . $notice . '
        </div>
    </div>
    ' . $BR;
}




function maxsysop(): void
{
    global $CURUSER, $mybb, $lang;

    if (is_mod($mybb->usergroup)) 
	{
        $staff_file = CONFIG_DIR . '/STAFFTEAM';
        if (file_exists($staff_file)) 
		{
            $results = explode(',', file_get_contents($staff_file));
            if (!in_array($CURUSER['username'] . ':' . $CURUSER['id'], $results, true))
			{
                require_once INC_PATH . '/functions_pm.php';
				require_once INC_PATH . '/datahandler.php';
               
			   $ip = getip();
			   $reasons = 'not in STAFFTEAM file';
				
				
				$msg = "Fake Staff Detected:"
         . " Username={$CURUSER['username']}"
         . " ID={$CURUSER['id']}"
         . " IP={$ip}"
         . " Reasons=[{$reasons}]";
				
				$pm = [
                  'subject' => 'Security Alert: Fake Staff Account',
                  'message' => $msg,
                  'touid'   => '1',
                ];
                $pm['sender']['uid'] = -1;
                send_pm($pm, -1, true);
				
				
				
				
                write_log('Fake Account Detected: Username: ' . $CURUSER['username'] . ' - UserID: ' . $CURUSER['id'] . ' - UserIP : ' . getip(), 'Warning: Fake Account Detected!');
                stderr($lang->global['fakeaccount']);
            }
        }
    }
}





function fix_url(string $url): string
{
    $url = htmlspecialchars($url);
    return str_replace(['&amp;', ' '], ['&', '&nbsp;'], $url);
}

function htmlspecialchars_uni(?string $message): string
{
    $message = $message ?? '';
    $message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message);
    $message = str_replace("<", "&lt;", $message);
    $message = str_replace(">", "&gt;", $message);
    $message = str_replace("\"", "&quot;", $message);
    return $message;
}

function tsrowcount(string $column, string $table, string|array $where = ''): int
{
    global $db;

    // Очистка имени колонки и таблицы
    $column = preg_replace('/[^a-zA-Z0-9_*]/', '', $column);
    $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

    $sql = "SELECT COUNT($column) AS cnt FROM $table";

    // Если условия переданы массивом, строим безопасный WHERE
    if (is_array($where) && !empty($where)) {
        $conds = [];
        foreach ($where as $key => $value) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key); // чистим имя колонки
            if (is_int($value)) {
                $conds[] = "$key=$value";
            } else {
                $conds[] = "$key='" . $db->sql_escape($value) . "'";
            }
        }
        $sql .= " WHERE " . implode(' AND ', $conds);
    }
    // Если строка передана напрямую, используем как есть (должна быть безопасной!)
    elseif (is_string($where) && $where !== '') {
        $sql .= " WHERE $where";
    }

    $res = $db->sql_query($sql);
    if (!$res) return 0;

    $row = $db->fetch_array($res);
    return (int)($row['cnt'] ?? 0);
}




function write_log(string $Text, string $category = '', int $level = 0): void
{
    global $db, $CURUSER, $session;

    // Определяем category автоматически если не передана
    if (empty($category)) {
        $text_lower = strtolower($Text);

        if (strpos($text_lower, 'screenshot') !== false) {
            $category = 'screenshot';
        } elseif (strpos($text_lower, 'torrent') !== false || strpos($text_lower, 'uploaded') !== false) {
            $category = 'torrent';
        } elseif (strpos($text_lower, 'seedbonus') !== false) {
            $category = 'cron';
        } elseif (strpos($text_lower, 'sql error') !== false || strpos($text_lower, '[sql error]') !== false) {
            $category = 'error';
            $level = 2; // автоматически danger
        } elseif (strpos($text_lower, 'attempt') !== false || strpos($text_lower, 'unwanted') !== false) {
            $category = 'security';
            $level = 2;
        } elseif (strpos($text_lower, 'settings updated') !== false) {
            $category = 'settings';
            $level = 1;
        } elseif (strpos($text_lower, 'banned') !== false) {
            $category = 'ban';
            $level = 1;
        } elseif (strpos($text_lower, 'deleted') !== false) {
            $category = 'deletion';
            $level = 1;
        } elseif (strpos($text_lower, 'mail') !== false) {
            $category = 'mail';
        } elseif (strpos($text_lower, 'cron') !== false || strpos($text_lower, 'task') !== false) {
            $category = 'cron';
        } elseif (strpos($text_lower, 'warning') !== false) {
            $category = 'warning';
            $level = 1;
        } else {
            $category = 'general';
        }
    }

    // Определяем uid
    $uid = !empty($CURUSER['id']) ? (int)$CURUSER['id'] : 0;


    $db->insert_query("sitelog", [
        "added"     => TIMENOW,
        "uid"       => $uid,
        "ipaddress" => $db->escape_binary(my_inet_pton(get_ip())),
        "txt"       => $db->escape_string($Text),
        "category"  => $db->escape_string($category),
        "level"     => $level,
    ]);
}















function kps(string $Type = '+', float|string|int $Points = 1.0, int|string $ID = 0): void
{
    global $bonus, $cache, $db;
    
    $kpscache = $cache->read("KPS");
    $bonus = $kpscache['bonus'] ?? '';

    if (($bonus == 'enable' || $bonus == 'disablesave')) {
        $Points = (float)$Points;
        $ID = (int)$ID;
        $db->sql_query('UPDATE users SET seedbonus = seedbonus ' . $Type . ' \'' . $Points . '\' WHERE id = \'' . $ID . '\'');
    }
}






function isvalidip(string $IP): bool
{
    return (bool)preg_match('/^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$/', $IP);
}




function mksize(float|string|int $bytes = 0): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
    $bytes = (float)$bytes;

    // Если <= 0, сразу возвращаем "0 B"
    if ($bytes <= 0) {
        return '0 B';
    }

    // Считаем степень
    $power = floor(log($bytes, 1024));
    $power = max(0, min($power, count($units) - 1));

    // Рассчитываем значение
    $value = $bytes / pow(1024, $power);

    // Ограничиваем числа больше EB
    if ($power === count($units) - 1 && $bytes >= pow(1024, $power + 1)) {
        return number_format($value, 2) . ' ' . $units[$power] . '+';
    }

    return number_format($value, 2) . ' ' . $units[$power];
}




function mksecret(int $length = 20, bool $UseNumbers = true): string
{
    if ($UseNumbers) {
        $set = ["a", "A", "b", "B", "c", "C", "d", "D", "e", "E", "f", "F", "g", "G", "h", "H", "i", "I", "j", "J", "k", "K", "l", "L", "m", "M", "n", "N", "o", "O", "p", "P", "q", "Q", "r", "R", "s", "S", "t", "T", "u", "U", "v", "V", "w", "W", "x", "X", "y", "Y", "z", "Z", "1", "2", "3", "4", "5", "6", "7", "8", "9"];
    } else {
        $set = ["a", "A", "b", "B", "c", "C", "d", "D", "e", "E", "f", "F", "g", "G", "h", "H", "i", "I", "j", "J", "k", "K", "l", "L", "m", "M", "n", "N", "o", "O", "p", "P", "q", "Q", "r", "R", "s", "S", "t", "T", "u", "U", "v", "V", "w", "W", "x", "X", "y", "Y", "z", "Z"];
    }
    
    $str = "";
    for ($i = 1; $i <= $length; $i++) {
        $ch = my_rand(0, count($set) - 1);
        $str .= $set[$ch];
    }
    return $str;
}

function getip(): string
{
    $alt_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $alt_ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        if ((isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))) {
            foreach ($matches[0] as $ip) {
                if (!preg_match('#^(10|172\\.16|192\\.168)\\.#', $ip)) {
                    $alt_ip = $ip;
                    break;
                }
            }
        } else {
            if (isset($_SERVER['HTTP_FROM'])) {
                $alt_ip = $_SERVER['HTTP_FROM'];
            }
        }
    }

    return htmlspecialchars($alt_ip);
}

function securehash(?string $var = null): string
{
    global $SITENAME, $securehash;
    return md5(md5($var ?? '') . getip() . md5($securehash . $SITENAME));
}

function generate_passkey(string $username, string $loginkey): string|false
{ 
    global $securehash, $SITENAME;

    if (empty($username) || empty($loginkey)) {
        return false;
    }

    // Формируем ключ без IP, чтобы он оставался стабильным
    $passkey = md5($username . TIMENOW . $loginkey . md5($securehash . $SITENAME));

    return $passkey;
}

function gzip(bool $use = false): void
{
    global $gzipcompress;
    
    if (
        (($gzipcompress == 'yes' || $use) && 
        @extension_loaded('zlib')) && 
        @ini_get('zlib.output_compression') != '1' && 
        @ini_get('output_handler') != 'ob_gzhandler'
    ) {
        @ob_start('ob_gzhandler');
    }
}

function warn_donor(int $s, int $warnday = 3): bool
{
    if ($s < 0) {
        $s = 0;
    }

    $t = [];
    foreach (['60:sec', '60:min', '24:hour', '0:day'] as $x) {
        $y = explode(':', $x);
        if (1 < $y[0]) {
            $v = $s % (int)$y[0];
            $s = floor($s / (int)$y[0]);
        } else {
            $v = $s;
        }

        $t[$y[1]] = $v;
    }

    if ($t['day'] < $warnday) {
        return true;
    }

    return false;
}




function cutename(string $name, int $max = 35): string
{
    return htmlspecialchars_uni($max < strlen($name) ? substr($name, 0, $max) . '...' : $name);
}




function cutename2(string $name, int $max = 25): string
{
    return htmlspecialchars_uni($max < strlen($name) ? substr($name, 0, $max) . '...' : $name);
}


function get_extension(string $filename): string
{
    $pos = strrpos($filename, '.');
    return $pos !== false ? strtolower(substr($filename, $pos + 1)) : '';
}



function dir_list(string $dir): array
{
    $dl = [];
    
    if (!file_exists($dir)) {
        error('Directory not found');
    }

    if ($hd = opendir($dir)) {
        while ($sz = readdir($hd)) {
            $ext = get_extension($sz);
            if ((!str_starts_with($sz, '.') && $ext != 'php')) {
                $dl[] = $sz;
                continue;
            }
        }

        closedir($hd);
        asort($dl);
        return $dl;
    }

    error('', 'Couldn\'t open storage folder! Please check the path.');
    return [];
}







function ts_nf(int|float|string|null $number): string
{
    if ($number === null || !is_numeric($number)) {
        return '0';
    }
    return number_format((float)$number, 0, '.', ',');
}




function is_mod(?array $user = []): bool
{
    $user = $user ?? [];
    
    return isset($user["cansettingspanel"]) && $user["cansettingspanel"] === '1' || 
           isset($user["issupermod"]) && $user["issupermod"] === '1' || 
           isset($user["canstaffpanel"]) && $user["canstaffpanel"] === '1';
}




function highlight(string $search, string $subject, string $hlstart = '<b><font color=\'#f7071d\'>', string $hlend = '</font></b>'): string
{
    $srchlen = strlen($search);
    if ($srchlen == 0) {
        return $subject;
    }

    $find = $subject;
    while ($find = stristr($find, $search)) {
        $srchtxt = substr($find, 0, $srchlen);
        $find = substr($find, $srchlen);
        $subject = str_replace($srchtxt, $hlstart . $srchtxt . $hlend, $subject);
    }

    return $subject;
}

function get_user_color(string $username, string $namestyle, bool $white = false): string
{
    if ($white) {
        $new_username = '<font color="#ffffff">' . $username . '</font>';
    } else {
        $new_username = str_replace('{username}', $username, $namestyle);
    }

    return $new_username;
}

function int_check(mixed $value, bool $stdhead = false, bool $stdfood = true, bool $die = true, bool $log = true): ?bool
{
    global $CURUSER, $BASEURL, $lang, $db;
    
    $msg = sprintf($lang->global['invalididlogmsg'], htmlspecialchars_uni($_SERVER['REQUEST_URI'] ?? ''), '<a href="' . $BASEURL . '/userdetails.php?id=' . $CURUSER['id'] . '">' . $CURUSER['username'] . '</a>', get_ip(), get_date_time());
    
    if (is_array($value)) {
        foreach ($value as $val) {
            int_check($val, $stdhead, $stdfood, $die, $log);
        }
        return null;
    }

    if (!is_valid_id($value)) {
        if ($stdhead) {
            if ($log) {
                write_log($msg);
            }
            stderr($lang->global['invalididlogged']);
        } else {
            echo $lang->global['invalididlogged2'];
            if ($log) {
                write_log($msg);
            }
        }

        if ($stdfood) {
            stdfoot();
        }

        if ($die) {
            exit();
        }
        return false;
    } else {
        return true;
    }
}

function is_valid_id(mixed $id): bool
{
    return is_numeric($id) && $id > 0 && floor((float)$id) == $id;
}





function flood_check(string $type = '', ?string $last = null, bool $shoutbox = false): ?string
{
    global $lang, $usergroups, $CURUSER;
    
    // Преобразуем floodlimit в integer
    $floodlimit = (int)($usergroups['floodlimit'] ?? 0);
    $timecut = TIMENOW - $floodlimit;
    
    // Обрабатываем случай когда $last = null
    if ($last === null) {
        $last = '';
    }
    
    if (str_contains($last, '-')) {
        $last = strtotime($last);
    }

    if (($timecut <= $last && $floodlimit != 0)) {
        $remaining_time = $floodlimit - (TIMENOW - $last);
        if (!$shoutbox) {
            stderr(sprintf($lang->global['flooderror'], $floodlimit, $type, $remaining_time), false);
            return null;
        }

        $msg = '<font color="#9f040b" size="2">' . sprintf($lang->global['flooderror'], $floodlimit, $type, $remaining_time) . '</font>';
        return $msg;
    }

    return null;
}








function error(string $error = "", string $title = ""): void
{
    global $header, $footer, $theme, $headerinclude, $db, $templates, $lang, $mybb, $plugins, $charset, $BASEURL, $SITENAME;

    $error = $plugins->run_hooks("error", $error);
    if(!$error) {
        $error = 'unknown_error';
    }

    // AJAX error message?
    if($mybb->get_input('ajax', MyBB::INPUT_INT)) {
        @header("Content-type: application/json; charset={$charset}");
        echo json_encode(["errors" => [$error]]);
        exit;
    }

    if(!$title) {
        $title = $SITENAME;
    }

    $timenow = my_datee('relative', TIMENOW);
	
	$current_time = date('H:i:s');
    $current_date = date('Y-m-d');
	
    
    eval("\$errorpage = \"".$templates->get("error")."\";");
    
    echo $errorpage;
    exit;
}

function error_no_permission(): void
{
    global $mybb, $theme, $templates, $db, $lang, $plugins, $session, $charset, $CURUSER;

    $time = TIMENOW;
    $plugins->run_hooks("no_permission");

    $noperm_array = [
        "nopermission" => '1',
        "location1" => 0,
        "location2" => 0
    ];

    $db->update_query("sessions", $noperm_array, "sid='{$session->sid}'");

    if($mybb->get_input('ajax', MyBB::INPUT_INT)) {
        header("Content-type: application/json; charset={$charset}");
        echo json_encode(["errors" => [$lang->error_nopermission_user_ajax]]);
        exit;
    }

    if (!empty($CURUSER['id'] ?? 0)) 
	{
        $error_nopermission_user_username = sprintf('You are currently logged in with the username: '.htmlspecialchars_uni($CURUSER['username'] ?? '').'');
        eval("\$errorpage = \"".$templates->get("error_nopermission_loggedin")."\";");
    } else {
        $redirect_url = $_SERVER['PHP_SELF'] ?? '';
        if($_SERVER['QUERY_STRING'] ?? '') {
            $redirect_url .= '?'.$_SERVER['QUERY_STRING'];
        }

        $redirect_url = htmlspecialchars_uni($redirect_url);
        
        $username_method = "0";

        switch($username_method) {
            case 0:
                $lang_username = 'username';
                break;
            case 1:
                $lang_username = 'username1';
                break;
            case 2:
                $lang_username = 'username2';
                break;
            default:
                $lang_username = 'username';
                break;
        }
        eval("\$errorpage = \"".$templates->get("error_nopermission")."\";");
    }

    
	error($errorpage);
}




function print_no_permission(bool $log = false, bool $stdhead = true, string $extra = ''): void
{
    global $lang, $SITENAME, $BASEURL, $CURUSER;
    
    if ($log) {
        $page = htmlspecialchars_uni($_SERVER['SCRIPT_NAME'] ?? '');
        $query = htmlspecialchars_uni($_SERVER['QUERY_STRING'] ?? '');
        $message = sprintf($lang->global['permissionlogmessage'], $page, $query, '<a href="' . $BASEURL . '/userdetails.php?id=' . $CURUSER['id'] . '">' . $CURUSER['username'] . '</a>', $CURUSER['ip'] ?? '');
        write_log($message);
    }

    if ($stdhead) {
        stderr(sprintf($lang->global['print_no_permission'], $SITENAME, ($extra != '' ? '<b>' . $extra . '</b>' : $lang->global['print_no_permission_i'])));
        stdfoot();
    } else {
        stderr(sprintf($lang->global['print_no_permission'], $SITENAME, ($extra != '' ? '<b>' . $extra . '</b>' : $lang->global['print_no_permission_i'])));
        stdfoot();
    }

    exit();
}





// ── inline_error ──────────────────────────────────────────────────────────────
function inline_error(array|string $errors, string $title = '', array $json_data = []): string
{
    global $mybb, $charset;

    if (empty($title)) {
        $title = 'Please correct the following errors:';
    }

    if (!is_array($errors)) {
        $errors = [$errors];
    }

    $errors = array_filter($errors);

    if (empty($errors)) {
        return '';
    }

    if (!empty($mybb->input['ajax'])) {
        header("Content-type: application/json; charset={$charset}");
        echo json_encode(array_merge(['errors' => $errors], $json_data));
        exit;
    }

    $error_items = '';
    foreach ($errors as $error) {
        $error_output = strip_tags((string)$error) !== (string)$error
            ? $error
            : htmlspecialchars_uni((string)$error);

        $error_items .= '
        <div class="error-message-box mb-2">
            <div class="d-flex align-items-start gap-3">
                <div class="message-icon">
                    <i class="bi bi-exclamation-circle-fill"></i>
                </div>
                <div class="flex-grow-1">
                    <p class="mb-0 fw-semibold">' . $error_output . '</p>
                </div>
            </div>
        </div>';
    }

    $count = count($errors);
    $title_escaped = htmlspecialchars_uni($title);

    return <<<HTML
    <style>
        .error-card-wrapper { max-width: 100%; margin: 0 auto; animation: slideUp 0.5s ease-out; }
        .error-card { position: relative; background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); border-radius: 20px !important; transition: transform 0.3s ease; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .error-card:hover { transform: translateY(-5px); }
        .gradient-line { position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #ff6b6b, #dc3545, #c82333); }
        .error-bg-pattern { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: radial-gradient(circle at 10px 10px, rgba(220,53,69,0.05) 2px, transparent 2px); background-size: 30px 30px; opacity: 0.5; pointer-events: none; }
        .card-header-error { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px; display: flex; align-items: center; gap: 15px; }
        .error-icon2 { font-size: 2.5rem; animation: float 3s ease-in-out infinite; }
        .error-message-box { background: rgba(220,53,69,0.05); border-left: 4px solid #dc3545; border-radius: 10px; padding: 1rem; transition: all 0.3s ease; }
        .error-message-box:hover { background: rgba(220,53,69,0.08); transform: translateX(5px); }
        .message-icon { width: 40px; height: 40px; background: rgba(220,53,69,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #dc3545; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        @media (prefers-color-scheme: dark) {
            .error-card { background: linear-gradient(135deg, #2b2b2b 0%, #1a1a1a 100%); }
            .error-message-box { background: rgba(220,53,69,0.1); }
        }
        @media (max-width: 768px) {
            .card-header-error { padding: 15px; }
            .error-icon2 { font-size: 2rem; }
            .error-message-box { padding: 0.75rem; }
            .message-icon { width: 32px; height: 32px; font-size: 1rem; }
        }
    </style>
    <div class="error-card-wrapper">
        <div class="error-card">
            <div class="gradient-line"></div>
            <div class="error-bg-pattern"></div>
            <div class="card-header-error">
                <i class="bi bi-exclamation-triangle-fill error-icon2"></i>
                <div>
                    <h2 class="mb-0 fw-bold" style="color:white;">{$title_escaped}</h2>
                    <p class="mb-0 opacity-75">Please review and fix the issues below</p>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-list-check fs-4" style="color:#dc3545;"></i>
                        <h5 class="mb-0 fw-semibold">Issues Found ({$count})</h5>
                    </div>
                    {$error_items}
                </div>
            </div>
        </div>
    </div>
    HTML;
}



























function submit_disable(string $formname = '', string $buttonname = '', string $text = ''): string
{
    global $lang;
    $value = 'onsubmit="document.' . $formname . '.' . $buttonname . '.value=\'' . ($text ?: $lang->global['pleasewait']) . '\';document.' . $formname . '.' . $buttonname . '.disabled=true"';
    return $value;
}


function unichr_callback1(array $matches): string
{
    return unichr(hexdec($matches[1]));
}

function unichr_callback2(array $matches): string
{
    return unichr((int)$matches[1]);
}

function unhtmlentities(string $string): string
{
    // Replace numeric entities
    $string = preg_replace_callback('~&#x([0-9a-f]+);~i', 'unichr_callback1', $string);
    $string = preg_replace_callback('~&#([0-9]+);~', 'unichr_callback2', $string);

    // Replace literal entities
    $trans_tbl = get_html_translation_table(HTML_ENTITIES);
    $trans_tbl = array_flip($trans_tbl);

    return strtr($string, $trans_tbl);
}

function my_substr(string $string, int $start, ?int $length = null, bool $handle_entities = false): string
{
    if($handle_entities) {
        $string = unhtmlentities($string);
    }
    
    if(function_exists("mb_substr")) {
        if($length !== null) {
            $cut_string = mb_substr($string, $start, $length);
        } else {
            $cut_string = mb_substr($string, $start);
        }
    } else {
        if($length !== null) {
            $cut_string = substr($string, $start, $length);
        } else {
            $cut_string = substr($string, $start);
        }
    }

    if($handle_entities) {
        $cut_string = htmlspecialchars_uni($cut_string);
    }
    
    return $cut_string;
}


    
	
	
	
	




function my_datee(?string $format = null, int|string|float $stamp = 0, string $offset = "", int $ty = 1, bool $adodb = false): string
{
    global $mybb, $lang, $plugins, $CURUSER, $dateformat, $timeformat, $regdateformat, $timezoneoffset, $dstcorrection, $datetimesep;
    
    // Если формат не указан, используем формат даты по умолчанию
    if ($format === null) {
        $format = $dateformat;
    }
    
    // Convert to integer (handle float, string, etc.)
    $stamp = (int)$stamp;
    
    // If the stamp isn't set, use TIME_NOW
    if(empty($stamp)) {
        $stamp = TIMENOW;
    }

    if(!$offset && $offset != '0') {
        if(isset($CURUSER['id']) && $CURUSER['id'] != 0 && array_key_exists("timezone", $CURUSER)) {
            $offset = (float)$CURUSER['timezone'];
            $dstcorrection = $CURUSER['dst'] ?? 0;
        } else {
            $offset = (float)$timezoneoffset;
            $dstcorrection = $dstcorrection ?? 0;
        }

        // If DST correction is enabled, add an additional hour to the timezone.
        if($dstcorrection == 1) {
            ++$offset;
            if(!str_starts_with((string)$offset, "-")) {
                $offset = "+".$offset;
            }
        }
    }

    if($offset == "-") {
        $offset = 0;
    }

    // Using ADOdb?
    if($adodb && !function_exists('adodb_date')) {
        $adodb = false;
    }

    $todaysdate = $yesterdaysdate = '';
    if($ty && ($format == $dateformat || $format == 'relative' || $format == 'normal')) {
        $_stamp = TIMENOW;
        if($adodb) {
            $date = adodb_date($dateformat, $stamp + ($offset * 3600));
            $todaysdate = adodb_date($dateformat, $_stamp + ($offset * 3600));
            $yesterdaysdate = adodb_date($dateformat, ($_stamp - 86400) + ($offset * 3600));
        } else {
            $date = gmdate($dateformat, (int)($stamp + ($offset * 3600)));
            $todaysdate = gmdate($dateformat, (int)($_stamp + ($offset * 3600)));
            $yesterdaysdate = gmdate($dateformat, (int)(($_stamp - 86400) + ($offset * 3600)));
        }
    }

    if($format == 'relative') {
        // Relative formats both date and time
        $real_date = $real_time = '';
        if($adodb) {
            $real_date = adodb_date($dateformat, $stamp + ($offset * 3600));
            $real_time = $datetimesep;
            $real_time .= adodb_date($timeformat, $stamp + ($offset * 3600));
        } else {
            $real_date = gmdate($dateformat, (int)($stamp + ($offset * 3600)));
            $real_time = $datetimesep;
            $real_time .= gmdate($timeformat, (int)($stamp + ($offset * 3600)));
        }

        if($ty != 2 && abs(TIMENOW - $stamp) < 3600) {
            $diff = TIMENOW - $stamp;
            $relative = ['prefix' => '', 'minute' => 0, 'plural' => 'minutes ', 'suffix' => 'ago'];

            if($diff < 0) {
                $diff = abs($diff);
                $relative['suffix'] = '';
                $relative['prefix'] = $lang->global['rel_in'];
            }

            $relative['minute'] = floor($diff / 60);

            if($relative['minute'] <= 1) {
                $relative['minute'] = 1;
                $relative['plural'] = $lang->global['rel_minutes_single'];
            }

            if($diff <= 60) {
                // Less than a minute
                $relative['prefix'] = $lang->global['rel_less_than'];
            }

            $date = sprintf($lang->global['rel_time'], $relative['prefix'], $relative['minute'], $relative['plural'], $relative['suffix'], $real_date, $real_time);
        } elseif($ty != 2 && abs(TIMENOW - $stamp) < 43200) {
            $diff = TIMENOW - $stamp;
            $relative = ['prefix' => '', 'hour' => 0, 'plural' => $lang->global['rel_hours_plural'], 'suffix' => $lang->global['rel_ago']];

            if($diff < 0) {
                $diff = abs($diff);
                $relative['suffix'] = '';
                $relative['prefix'] = $lang->global['rel_in'];
            }

            $relative['hour'] = floor($diff / 3600);

            if($relative['hour'] <= 1) {
                $relative['hour'] = 1;
                $relative['plural'] = $lang->global['rel_hours_single'];
            }

            $date = sprintf($lang->global['rel_time'], $relative['prefix'], $relative['hour'], $relative['plural'], $relative['suffix'], $real_date, $real_time);
        } else {
            if($ty) {
                if($todaysdate == $date) {
                    $date = sprintf($lang->global['today_rel'], $real_date);
                } elseif($yesterdaysdate == $date) {
                    $date = sprintf($lang->global['yesterday_rel'], $real_date);
                }
            }

            $date .= $datetimesep;
            if($adodb) {
                $date .= adodb_date($timeformat, $stamp + ($offset * 3600));
            } else {
                $date .= gmdate($timeformat, (int)($stamp + ($offset * 3600)));
            }
        }
    } elseif($format == 'normal') {
        // Normal format both date and time
        if($ty != 2) {
            if($todaysdate == $date) {
                $date = $lang->global['today'];
            } elseif($yesterdaysdate == $date) {
                $date = $lang->global['yesterday'];
            }
        }

        $date .= $datetimesep;
        if($adodb) {
            $date .= adodb_date($timeformat, $stamp + ($offset * 3600));
        } else {
            $date .= gmdate($timeformat, (int)($stamp + ($offset * 3600)));
        }
    } else {
        if($ty && $format == $dateformat) {
            if($todaysdate == $date) {
                $date = $lang->global['today'];
            } elseif($yesterdaysdate == $date) {
                $date = $lang->global['yesterday'];
            }
        } else {
            if($adodb) {
                $date = adodb_date($format, $stamp + ($offset * 3600));
            } else {
                $date = gmdate($format, (int)($stamp + ($offset * 3600)));
            }
        }
    }

    if(is_object($plugins)) {
        $date = $plugins->run_hooks("my_datee", $date);
    }

    return $date;
}








function my_substrr(string $string, int $start, int $length = 0): string
{
    if(function_exists('mb_substr')) {
        if($length != 0) {
            $cut_string = mb_substr($string, $start, $length);
        } else {
            $cut_string = mb_substr($string, $start);
        }
    } else {
        if($length != 0) {
            $cut_string = substr($string, $start, $length);
        } else {
            $cut_string = substr($string, $start);
        }
    }

    return $cut_string;
}

function get_date_time(int $timestamp = 0): string
{
    if($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    return date('Y-m-d H:i:s');
}

function gmtime(): int
{
    return strtotime(get_date_time());
}

function unhtmlspecialchars(string $text, bool $doUniCode = false): string
{
    if($doUniCode) {
        $text = preg_replace_callback('/&#([0-9]+);/U', function($matches) {
            return convert_int_to_utf8($matches[1]);
        }, $text);
    }

    return str_replace(['&lt;', '&gt;', '&quot;', '&amp;'], ['<', '>', '"', '&'], $text);
}

function check_email(string $email): bool
{
    return (bool)preg_match('#^[a-z0-9.!\\#$%&\'*+-/=?^_`{|}~]+@([0-9.]+|([^\\s\'"<>]+\\.+[a-z]{2,6}))$#si', $email);
}

function parse_email(string $link = '', string $text = ''): string
{
    $rightlink = trim($link);
    if(empty($rightlink)) {
        $rightlink = trim($text);
    }

    $rightlink = str_replace(['`', '"', '\'', '['], ['&#96;', '&quot;', '&#39;', '&#91;'], $rightlink);
    if((!trim($link) || $text == $rightlink)) {
        $tmp = unhtmlspecialchars($rightlink);
        if(strlen($tmp) > 55) {
            $text = htmlspecialchars_uni(substr($tmp, 0, 36) . '...' . substr($tmp, -14));
        }
    }

    $rightlink = str_replace('  ', '', $rightlink);
    if(check_email($rightlink)) {
        return '<a href="mailto:' . $rightlink . '">' . $text . '</a>';
    }

    return $text;
}

function format_urls(string $s, string $target = '_blank'): string
{
    return preg_replace('/(\\A|[^=\\]\'"a-zA-Z0-9])((http|ftp|https|ftps|irc):\\/\\/[^()<>\\s]+)/i', '\\1<a href="\\2" target="' . $target . '">\\2</a>', $s);
}

// Вспомогательная функция для convert_int_to_utf8
function convert_int_to_utf8(string $code): string
{
    $code = (int)$code;
    if($code < 0x80) {
        return chr($code);
    } elseif($code < 0x800) {
        return chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 0x3F));
    } elseif($code < 0x10000) {
        return chr(0xE0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F));
    } elseif($code < 0x200000) {
        return chr(0xF0 | ($code >> 18)) . chr(0x80 | (($code >> 12) & 0x3F)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F));
    }
    return '';
}



// Функция format_avatar (уже обновленная ранее)
function scale_images(int $width, int $height, int $maxwidth, int $maxheight): array
{
    // Если размеры уже меньше максимальных - возвращаем как есть
    if ($width <= $maxwidth && $height <= $maxheight) {
        return ['width' => $width, 'height' => $height];
    }
    
    // Вычисляем коэффициенты масштабирования
    $width_ratio = $maxwidth / $width;
    $height_ratio = $maxheight / $height;
    
    // Берём меньший коэффициент, чтобы изображение влезло полностью
    $ratio = min($width_ratio, $height_ratio);
    
    $newwidth = (int)round($width * $ratio);
    $newheight = (int)round($height * $ratio);
    
    return ['width' => $newwidth, 'height' => $newheight];
}

/**
 * Функция для форматирования аватара (PHP 8.4)
 */
function format_avatar(
    ?string $avatar,
    ?string $dimensions = '',
    ?string $max_dimensions = ''
): array {
    global $mybb, $allowremoteavatars, $maxavatardims;

    // 1) Пусто -> SVG заглушка
    if (empty($avatar)) {
        $html = '<svg class="avatar-ring2" width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">'
              . '<circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>'
              . '<text x="50" y="55" text-anchor="middle" font-size="16" fill="#666">No Avatar</text>'
              . '</svg>';
        return [
            'image'         => $html,
            'width_height'  => '',
            'html'          => $html,
            'is_html'       => true
        ];
    }

    // 2. Проверка удалённых аватаров
    $is_remote = preg_match('~^(https?:)?//~i', $avatar) || str_starts_with($avatar, 'data:image/');
    if ($is_remote && empty($allowremoteavatars)) {
        $html = '<div class="no-avatar">Remote Avatar Not Allowed</div>';
        return [
            'image'         => $html,
            'width_height'  => '',
            'html'          => $html,
            'is_html'       => true,
        ];
    }

    // 3. Парсим размеры
    $width = $height = null;
    $max_width = $max_height = null;
    
    // Желаемые размеры
    if (!empty($dimensions)) {
        $parts = preg_split('/[|x]/', $dimensions);
        if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $width = (int)$parts[0];
            $height = (int)$parts[1];
        }
    }
    
    // Максимальные размеры
    $max_dims = !empty($max_dimensions) ? $max_dimensions : ($maxavatardims ?? '');
    if (!empty($max_dims)) {
        $parts = preg_split('/[|x]/', $max_dims);
        if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $max_width = (int)$parts[0];
            $max_height = (int)$parts[1];
        }
    }

    // 4. Определяем финальные размеры
    $final_width = $width;
    $final_height = $height;
    
    // Если есть и желаемые и максимальные размеры - масштабируем
    if ($width && $height && $max_width && $max_height) {
        // Масштабируем только если текущие размеры превышают максимальные
        if ($width > $max_width || $height > $max_height) {
            $scaled = scale_images($width, $height, $max_width, $max_height);
            $final_width = $scaled['width'];
            $final_height = $scaled['height'];
        }
    }
    // Если есть только максимальные размеры - используем их
    elseif ($max_width && $max_height) {
        $final_width = $max_width;
        $final_height = $max_height;
    }
    // Если нет размеров - используем разумные по умолчанию
    elseif (!$width && !$height) {
        $final_width = 100;
        $final_height = 100;
    }

    // 5. Формируем URL (используем MyBB функцию если доступна)
    if (function_exists('htmlspecialchars_uni') && isset($mybb)) {
        $url = htmlspecialchars_uni($mybb->get_asset_url($avatar));
    } else {
        $url = htmlspecialchars($avatar, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    // 6. Формируем HTML
    $width_height = ($final_width && $final_height) 
        ? sprintf('width="%d" height="%d"', $final_width, $final_height) 
        : '';

    $html = '<img src="' . $url . '" ' . $width_height . ' alt="avatar" class="rounded img-fluid" loading="lazy">';

    return [
        'image'         => $url,
        'width_height'  => $width_height,
        'width'         => $final_width,
        'height'        => $final_height,
        'html'          => $html,
        'is_html'       => false,
    ];
}




function get_thread3333(int|string $tid, bool $recache = false): array|false
{
    global $db;
    static $thread_cache = [];

    $tid = (int)$tid;

    if(isset($thread_cache[$tid]) && !$recache) {
        return $thread_cache[$tid];
    } else {
        $query = $db->simple_select("tsf_threads", "*", "tid = '{$tid}'");
        $thread = $db->fetch_array($query);

        if($thread) {
            $thread_cache[$tid] = $thread;
            return $thread;
        } else {
            $thread_cache[$tid] = false;
            return false;
        }
    }
}






if (!defined('APP_INITIALIZED')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization000 of this file is not allowed.</font>');
}
?>