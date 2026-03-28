<?php

require_once('global.php');

define('SL_VERSION', '0.4');



// Проверяем что язык передан
if(empty($_GET['language']))
{
    header('Location: index.php');
    exit();
}

$language = fix_url($_GET['language']);

// Защита от path traversal
$language = str_replace(array('/', '\\', '..'), '', trim($language));

// Проверяем что такой язык существует
$lang_path = INC_PATH . '/languages/' . $language;
if(!is_dir($lang_path))
{
    header('Location: index.php');
    exit();
}

// Сохраняем в куки
setcookie('ts_language', $language, time() + 60 * 60 * 24 * 365, '/');

// Сохраняем в БД если пользователь залогинен
if(isset($CURUSER) && !empty($CURUSER['id']))
{
    $db->sql_query("UPDATE users SET language = '" . $db->escape_string($language) . "' WHERE id = " . (int)$CURUSER['id']);
}

// Редирект обратно
if(isset($_GET['redirect']) && $_GET['redirect'] == 'yes')
{
    $to = !empty($_GET['from'])
        ? fix_url($_GET['from'])
        : (!empty($_SERVER['HTTP_REFERER'])
            ? fix_url($_SERVER['HTTP_REFERER'])
            : 'index.php');
    header('Location: ' . $to);
    exit();
}

// Если редирект не нужен — показываем сообщение
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Update Language</title>
    <style type="text/css">
        body, td, th { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px; }
        .msg { color: #006600; font-weight: bold; }
    </style>
    <script type="text/javascript">
        setTimeout(function(){ window.close(); }, 2000);
        if(window.opener) { window.opener.location.reload(); }
    </script>
</head>
<body>
    <span class="msg">Language has been updated to: <?php echo htmlspecialchars($language); ?></span>
</body>
</html>
<?php
exit();
?>