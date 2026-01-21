<?php


declare(strict_types=1);

if(!defined('IN_TRACKER')) die('Hacking attempt!');



$QueryForm = '';

// Memory usage with null coalescing
$memory_usage = get_memory_usage();
$memory_display = $memory_usage 
    ? sprintf($lang->global['debug_memory_usage'] ?? 'Memory: %s', mksize($memory_usage))
    : '';

// Performance calculations
$totaltime = format_time_duration($maintimer->stop());
$phptime = $maintimer->totaltime - $db->query_time;
$query_time = $db->query_time;

// Time distribution percentages
if($maintimer->totaltime > 0) {
    $percentphp = number_format(($phptime / $maintimer->totaltime) * 100, 2);
    $percentsql = number_format(($query_time / $maintimer->totaltime) * 100, 2);
} else {
    $percentphp = 0;
    $percentsql = 0;
}

// Server information
$serverload = get_server_load();
$database_server = ($db->short_title == 'MySQLi') ? 'MySQL' : $db->short_title;

// Formatted display strings
$generated_in = sprintf($lang->global['debug_generated_in'] ?? 'Generated in %s', $totaltime);
$debug_weight = sprintf(
    $lang->global['debug_weight'] ?? '(%s%% PHP / %s%% %s)',
    $percentphp, 
    $percentsql, 
    $database_server
);

// Query counting with modern null-safe operations
$queries_count = $db->query_count 
    ?? ((isset($GLOBALS['queries']) && is_array($GLOBALS['queries'])) ? count($GLOBALS['queries']) : 0);

$sql_queries = sprintf($lang->global['debug_sql_queries'] ?? 'SQL Queries: %s', $queries_count);
$server_load = sprintf($lang->global['debug_server_load'] ?? 'Server Load: %s', $serverload);

// Total time calculation
if (isset($GLOBALS['ts_start_time'])) {
    $GLOBALS['totaltime'] = round(microtime(true) - $GLOBALS['ts_start_time'], 4);
}

// SQL Analysis Form (Admin only)
if (($usergroups['cansettingspanel'] ?? 0) == 1) {
    // CSRF protection
    $csrfField = '';
    if (isset($mybb->post_code) && !empty($mybb->post_code)) {
        $csrfField = '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code, ENT_QUOTES) . '">';
    } elseif (function_exists('csrf_field')) {
        $csrfField = csrf_field();
    }

    // Collect SQL queries for analysis
    $queriesInputs = '';
    if (isset($GLOBALS['queries']) && is_array($GLOBALS['queries'])) {
        foreach ($GLOBALS['queries'] as $q) {
            if (is_array($q)) {
                $sql = $q['query'] ?? $q[1] ?? '';
                $time = (float)($q['query_time'] ?? $q[0] ?? 0);
            } else {
                $sql = (string)$q;
                $time = 0.0;
            }
            
            $payload = base64_encode(
                substr(sprintf('%.4F', $time), 0, 8) . ',' . 
                base64_encode($sql)
            );
            
            $queriesInputs .= '<input type="hidden" name="queries[]" value="' . 
                htmlspecialchars($payload, ENT_QUOTES, 'UTF-8') . '">';
        }
    }

    $totalTimeInput = '';
    if (isset($GLOBALS['totaltime']) && $GLOBALS['totaltime']) {
        $totalTimeInput = '<input type="hidden" name="totaltime" value="' . $GLOBALS['totaltime'] . '" />';
    }

    $QueryForm = '
<form method="post" action="' . $BASEURL . '/admin/query_explain.php" name="ts_queries" id="ts_queries">
  ' . $totalTimeInput . '
  ' . $csrfField . '
  <input type="hidden" name="deep" id="deep_input" value="0">
  ' . $queriesInputs . '
</form>';
}

// JavaScript for form handling
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
    d.value = mode ? "1" : "0";
    f.submit();
    return false;
  };
})();
</script>';

// Main content output
$debug_block = '';
if (($usergroups['cansettingspanel'] ?? 0) == 1 && !defined('SKIP_SHOW_QUERIES')) {
    $debug_block = '
    <div class="container-md">
        <div id="debug" class="card shadow-sm p-3 mt-4">
            <div class="row">
                <div class="col align-self-center">' . $generated_in . ' ' . $debug_weight . ' - ' . $sql_queries . ' / ' . $server_load . ' / ' . $memory_display . '</div>
                <div class="col-auto align-self-center text-end">
                    <a href="#" onclick="return submitExplain(0);" class="btn btn-sm btn-secondary">' . ($lang->global['debug_advanced_details'] ?? 'Advanced Details') . '</a>
                    <a href="#" onclick="return submitExplain(1);" class="btn btn-sm btn-outline-primary ms-2">Deep (ANALYZE)</a>
                </div>
            </div>
        </div>
    </div>';
}

$cron_code = '';
if (isset($GLOBALS['ts_cron_image'])) {
    $cron_code = '
<!-- TS Auto Cronjobs code -->
    <img src="' . $BASEURL . '/ts_cron.php?rand=' . TIMENOW . '" alt="" width="1" height="1" border="0">
<!-- TS Auto Cronjobs code -->';
}

// Final output
echo '
    <br />
    </div>
    </div>
    ' . $debug_block . '
    </div>
    ' . $cron_code . '
    ' . $QueryForm . '
</body>
</html>';