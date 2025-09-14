<?php


define("THIS_SCRIPT", "takeflush.php");
require "./global.php";
require INC_PATH . '/readconfig_announce.php';

define("TF_VERSION", "0.3.3 by xam");

$id = isset($_GET["id"]) ? (int)$_GET["id"] : (int)$CURUSER["id"];
if ($id <= 0) {
    stderr($lang->global['error'], "Invalid ID!");
    exit;
}

int_check($id);
$lang->load("takeflush");


$is_mod = is_mod($usergroups);


// Функция для вычисления "мертвого времени"
function deadtime(): int {
    global $announce_interval;
    return TIMENOW - (int)floor($announce_interval * 1.3);
}

if (($is_mod || $CURUSER["id"] === $id) && isset($CURUSER) && $CURUSER["id"] > 0) {
    $deadtime = deadtime();

    $db->sql_query("DELETE FROM peers WHERE last_action < {$deadtime} AND userid=" . $db->sqlesc($id));
    

    if ($db->affected_rows()) {
        stderr($lang->takeflush["done"], $lang->takeflush["done2"]);
    } else {
        stderr($lang->global["error"], $lang->takeflush["noghost"]);
    }
} else {
    print_no_permission();
}
?>

