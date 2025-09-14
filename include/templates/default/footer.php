<?php
/* TS SE Default Template (Footer) by xam - Version 1.2.1
+--------------------------------------------------------------------------
|   TS Special Edition v.7.3
|   ========================================
|   by xam
|   (c) 2005 - 2010 Template Shares Services
|   http://templateshares.net
|   ========================================
|   Web: http://templateshares.net
|   Time: $_ts_date_
|   Signature Key: $_ts_signature_key_
|   Email: contact@templateshares.net
|   TS SE IS NOT FREE SOFTWARE!
+-------------------------------------------------------------------------------------------
| You have no permission to modify this file unless you purchase a Brading Free Product!
+-------------------------------------------------------------------------------------------
*/
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$QueryForm = '';

$memory_usage = get_memory_usage();

if($memory_usage)
{
    $memory_usage = sprintf($lang->global['debug_memory_usage'], mksize($memory_usage));
}
else
{
    $memory_usage = '';
}

$totaltime = format_time_duration($maintimer->stop());
$phptime = $maintimer->totaltime - $db->query_time;
$query_time = $db->query_time;

if($maintimer->totaltime > 0)
{
    $percentphp = number_format((($phptime/$maintimer->totaltime) * 100), 2);
    $percentsql = number_format((($query_time/$maintimer->totaltime) * 100), 2);
}
else
{
    // if we've got a super fast script...  all we can do is assume something
    $percentphp = 0;
    $percentsql = 0;
}

$serverload = get_server_load();

// MySQLi is still MySQL, so present it that way to the user
$database_server = $db->short_title;

if($database_server == 'MySQLi')
{
    $database_server = 'MySQL';
}

$generated_in = sprintf($lang->global['debug_generated_in'], $totaltime);
$debug_weight = sprintf($lang->global['debug_weight'], $percentphp, $percentsql, $database_server);
///$sql_queries = sprintf($lang->global['debug_sql_queries'], $GLOBALS['totalqueries']);



$queries_count = isset($db->query_count) ? (int)$db->query_count
    : ((isset($GLOBALS['queries']) && is_array($GLOBALS['queries'])) ? count($GLOBALS['queries']) : 0);
$sql_queries = sprintf($lang->global['debug_sql_queries'], $queries_count);






$server_load = sprintf($lang->global['debug_server_load'], $serverload);

if (isset($GLOBALS['ts_start_time']))
{
    $GLOBALS['totaltime'] = round((array_sum(explode(' ',microtime())) - $GLOBALS['ts_start_time'] ),4);
}

// Defensive check to avoid error if $usergroups or key not set



if (isset($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == 1)
{
    $csrfField = '';
    if (isset($mybb) && !empty($mybb->post_code)) {
        $csrfField = '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code, ENT_QUOTES).'">';
    } elseif (function_exists('csrf_field')) {
        $csrfField = csrf_field();
    }

    $queriesInputs = '';
    if (isset($GLOBALS['queries'])) {
        if (is_array($GLOBALS['queries'])) {
            foreach ($GLOBALS['queries'] as $q) {
                $sql  = is_array($q) ? ($q['query'] ?? ($q[1] ?? '')) : (string)$q;
                $time = (float)(is_array($q) ? ($q['query_time'] ?? ($q[0] ?? 0)) : 0);
                $payload = base64_encode(substr(sprintf('%.4F',$time),0,8).','.base64_encode($sql));
                $queriesInputs .= '<input type="hidden" name="queries[]" value="'.htmlspecialchars($payload,ENT_QUOTES,'UTF-8').'">';
            }
        } else {
            $queriesInputs = (string)$GLOBALS['queries'];
        }
    }

   $QueryForm = '
<form method="post" action="'.$BASEURL.'/admin/ts_query_explain.php" name="ts_queries" id="ts_queries">
  '.(isset($GLOBALS['totaltime']) ? '<input type="hidden" name="totaltime" value="'.$GLOBALS['totaltime'].'" />' : '').'
  '.$csrfField.'
  <input type="hidden" name="deep" id="deep_input" value="0">
  '.$queriesInputs.'
</form>';


}






echo '

<script>
(function(){
  window.submitExplain = function(mode){
    var f = document.forms.ts_queries || document.getElementById("ts_queries");
    if(!f) return false;
    var d = f.querySelector("#deep_input");
    if(!d){
      d = document.createElement("input");
      d.type = "hidden";
      d.name = "deep";
      d.id = "deep_input";
      f.appendChild(d);
    }
    d.value = mode ? "1" : "0"; // 1 = Deep(ANALYZE), 0 = обычный
    f.submit();
    return false;
  };
})();
</script>';




echo '
    <br />
    </div>
    </div>
    
    '.(isset($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == 1 && !defined('SKIP_SHOW_QUERIES') ? '
    <div class="container-md"><div id="debug" class="card shadow-sm p-3 mt-4">
      <div class="row">
        <div class="col align-self-center">'.$generated_in.' '.$debug_weight.' - '.$sql_queries.' / '.$server_load.' / '.$memory_usage.'</div>
        <div class="col-auto align-self-center text-end">
          <a href="#" onclick="submitExplain(0);return false;" class="btn btn-sm btn-secondary">'.$lang->global['debug_advanced_details'].'</a>
          <a href="#" onclick="submitExplain(1);return false;" class="btn btn-sm btn-outline-primary ms-2">Deep (ANALYZE)</a>
        </div>
      </div>
    </div></div>' : '').'
   
        
    </div>
    
'.(isset($GLOBALS['ts_cron_image']) ? '
<!-- TS Auto Cronjobs code -->
    <img src="'.$BASEURL.'/ts_cron.php?rand='.TIMENOW.'" alt="" title="" width="1" height="1" border="0" />
<!-- TS Auto Cronjobs code -->
' : '').'
'.$QueryForm.'
</body>
</html>
';

/*
+-------------------------------------------------------------------------------------------
| You have no permission to modify this file unless you purchase a Brading Free Product!
+-------------------------------------------------------------------------------------------
*/     
