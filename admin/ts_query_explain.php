<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function server_load ()
  {
    if (strtolower (substr (PHP_OS, 0, 3)) === 'win')
    {
      if (class_exists ('COM'))
      {
        $wmi = new COM ('WinMgmts:\\\\.');
        $cpus = $wmi->InstancesOf ('Win32_Processor');
        $cpuload = 0;
        $i = 0;
        if (version_compare ('4.50.0', PHP_VERSION) == 1)
        {
          while ($cpu = $cpus->Next ())
          {
            $cpuload += $cpu->LoadPercentage;
            ++$i;
          }
        }
        else
        {
          foreach ($cpus as $cpu)
          {
            $cpuload += $cpu->LoadPercentage;
            ++$i;
          }
        }

        $cpuload = round ($cpuload / $i, 2);
        return '' . $cpuload . '%';
      }

      return 'Unknown';
    }

    if (@file_exists ('/proc/loadavg'))
    {
      $load = @file_get_contents ('/proc/loadavg');
      $serverload = explode (' ', $load);
      $serverload[0] = round ($serverload[0], 4);
      if (!$serverload)
      {
        $load = @exec ('uptime');
        $load = split ('load averages?: ', $load);
        $serverload = explode (',', $load[1]);
      }
    }
    else
    {
      $load = @exec ('uptime');
      $load = split ('load averages?: ', $load);
      $serverload = explode (',', $load[1]);
    }

    $returnload = trim ($serverload[0]);
    if (!$returnload)
    {
      $returnload = 'Unknown';
    }

    return $returnload;
  }

  function calctime($time)
{
    $stat = round($time * 100, 3);
    $val  = sprintf('%.4f', (float)$time);

    if ($stat <= 40) {
        return $val.' <span class="badge bg-success">Excellent</span>';
    }
    if ($stat <= 70) {
        // более мягкий зелёный/граница — лучше видно на светлом фоне
        return $val.' <span class="badge bg-success-subtle text-success border">Good</span>';
    }
    if ($stat <= 98) {
        return $val.' <span class="badge bg-warning text-dark">Regular</span>';
    }
    return $val.' <span class="badge bg-danger">Bad</span>';
}


   //function explain_query ($sql, $executiontime)


// безопасный htmlspecialchars с фоллбэком
if (!function_exists('hsafe')) {
    function hsafe($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}








// Хелпер: ищем точку с запятой только ВНЕ кавычек
// 1) Хелпер — принимает ?string и сразу отсекает пустоту
function has_unquoted_semicolon(?string $sql): bool {
    if ($sql === null || $sql === '') {
        return false;
    }
    $len = strlen($sql);
    $inSingle = false;
    $inDouble = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];

        if ($inSingle) {
            if ($ch === "'") {
                if ($i+1 < $len && $sql[$i+1] === "'") { $i++; continue; } // '' = экранирование
                $inSingle = false;
            } elseif ($ch === "\\") { $i++; }
            continue;
        }
        if ($inDouble) {
            if ($ch === '"') {
                if ($i+1 < $len && $sql[$i+1] === '"') { $i++; continue; }
                $inDouble = false;
            } elseif ($ch === "\\") { $i++; }
            continue;
        }

        if ($ch === "'") { $inSingle = true; continue; }
        if ($ch === '"') { $inDouble = true; continue; }

        if ($ch === ';') { return true; } // ; вне кавычек
    }
    return false;
}


/**
 * Пояснение SQL-запроса.
 * @param string     $sql       исходный SQL
 * @param float|null $execTime  время выполнения (сек), если есть
 * @param bool       $deep      EXPLAIN ANALYZE (MySQL 8+, только для SELECT)
 * @return string    HTML-блок
 */


	
// --- helpers: убрать ведущие комментарии и надёжно распознать SELECT/WITH ---
if (!function_exists('sql_strip_leading_comments')) 
{
    function sql_strip_leading_comments(string $s): string {
        $s = ltrim($s);

        // /* ... */ (многострочные) в начале
        while (preg_match('/^\/\*.*?\*\//s', $s, $m)) 
		{
            $s = ltrim(substr($s, strlen($m[0])));
        }

        // -- ...\n и # ...\n (однострочные) в начале
        while (preg_match('/^(?:--|#)[^\r\n]*(?:\r?\n|$)/', $s, $m)) 
		{
            $s = ltrim(substr($s, strlen($m[0])));
        }

        return ltrim($s);
    }
}

if (!function_exists('sql_is_select')) 
{
    function sql_is_select(string $sql): bool {
        $lead = sql_strip_leading_comments($sql);
        return (bool)preg_match('/^\s*(SELECT|WITH)\b/i', $lead);
    }
}
	
	
	
	
function explain_query(string $sql, ?float $execTime = null, bool $deep = false): string
{
    global $id, $db;

    // --- линк с фоллбэком ---
    $link = $db->current_link ?? null;
    if (!$link && !empty($db->write_link)) $link = $db->write_link;
    if (!$link && !empty($db->read_link))  $link = $db->read_link;

    // --- время/оформление ---
    $t = (float)($execTime ?? 0.0);
    $calcTime = calctime($t);

    // --- нормализация ---
    $sql_clean = rtrim((string)$sql);
    $tmp = preg_replace('/;+\s*$/', '', $sql_clean);
    if ($tmp !== null) $sql_clean = $tmp;

    if ($sql_clean === '') {
        return '<div class="alert alert-warning">Empty query.</div>';
    }

    // мульти-стейтменты — блокируем как и раньше
    if (has_unquoted_semicolon($sql_clean)) {
        return '<div class="alert alert-danger">Multiple statements are not allowed.</div>'
             . '<div class="card-body"><pre>'.hsafe($sql).'</pre></div>';
    }

    // тип запроса (+ поддержка WITH), с учётом удаления ведущих комментариев
    $lead = sql_strip_leading_comments($sql_clean);
    $is_select = (bool)preg_match('/^\s*(SELECT|WITH)\b/i', $lead);
    $query_type = strtoupper(preg_replace('/^\s*([a-zA-Z]+).*$/s', '$1', $lead));

    // === ВАЖНО: в режиме DEEP показываем ТОЛЬКО SELECT/WITH ===
    if ($deep && !$is_select) {
        return ''; // не рисуем write/DDL в Deep-режиме
    }

    // ===== Deep (EXPLAIN ANALYZE) — только для SELECT/WITH =====
    if ($deep && $is_select) {
        @mysqli_query($link, "SET SESSION MAX_EXECUTION_TIME=3000");
        $sql_hint = preg_replace('/^\s*select\b/i', 'SELECT /*+ MAX_EXECUTION_TIME(3000) */', $sql_clean, 1);
        $an = @mysqli_query($link, "EXPLAIN ANALYZE $sql_hint");
        if ($an !== false) {
            $lines = [];
            while ($row = mysqli_fetch_array($an)) {
                $lines[] = (string)($row[0] ?? ($row['EXPLAIN'] ?? ''));
            }
            $txt = trim(implode("\n", $lines));

            $warn_html = '';
            if ($wRes = @mysqli_query($link, "SHOW WARNINGS")) {
                $buf = '';
                while ($w = mysqli_fetch_assoc($wRes)) {
                    $buf .= '<tr><td>'.hsafe($w['Level'] ?? '').'</td><td>'.hsafe($w['Code'] ?? '').'</td><td>'.hsafe($w['Message'] ?? '').'</td></tr>';
                }
                if ($buf) {
                    $warn_html = '
                    <div class="table-responsive mt-3">
                      <table class="table table-sm table-bordered">
                        <thead><tr><th>Level</th><th>Code</th><th>Message</th></tr></thead>
                        <tbody>'.$buf.'</tbody>
                      </table>
                    </div>';
                }
            }

            return '
            <div class="card-header"><div><strong>#'.(int)$id.' - Deep Plan (EXPLAIN ANALYZE)</strong></div></div>
            <div class="card-body">
              <div class="mb-2"><pre>'.hsafe($sql).'</pre></div>
              <pre class="mb-0" style="white-space:pre-wrap">'.hsafe($txt).'</pre>
              '.$warn_html.'
            </div>
            <div class="card-footer">Measured time: '.$calcTime.' • Executed with ANALYZE</div>';
        }
        // если ANALYZE не сработал — пойдём на обычный EXPLAIN ниже
    }

    // === обычный EXPLAIN — только для SELECT/WITH ===
    if ($is_select) {
        $res = mysqli_query($link, "EXPLAIN $sql_clean");
        if ($res !== false) {
            $rows=[]; $cols=[];
            while ($r = mysqli_fetch_assoc($res)) { $rows[]=$r; foreach ($r as $k=>$v){ $cols[$k]=true; } }
            $cols = array_keys($cols);
            $preferred = ['id','select_type','table','partitions','type','possible_keys','key','key_len','ref','rows','filtered','Extra'];
            $order = array_values(array_unique(array_merge($preferred, $cols)));

            $thead=''; foreach($order as $c){ $thead.='<th>'.hsafe($c).'</th>'; }
            $tbody='';
            foreach ($rows as $r) {
                $tbody .= '<tr>';
                foreach ($order as $c) $tbody .= '<td>'.hsafe($r[$c] ?? '').'</td>';
                $tbody .= '</tr>';
            }

            return '
            <div class="card-header"><div><strong>#'.(int)$id.' - Select Query</strong></div></div>
            <div class="card-body"><pre>'.hsafe($sql).'</pre></div>
            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead><tr>'.$thead.'</tr></thead>
                <tbody>'.$tbody.'</tbody>
              </table>
            </div>
            <div class="card-footer">Measured time: '.$calcTime.'</div>';
        }

        $err = mysqli_error($link);
        return '
        <div class="card-header"><div><strong>#'.(int)$id.' - Read Query</strong></div></div>
        <div class="card-body">
          <pre>'.hsafe($sql).'</pre>'.
          ($err ? '<div class="alert alert-warning mt-2">EXPLAIN failed: '.hsafe($err).'</div>' : '').'
        </div>
        <div class="card-footer">Measured time: '.$calcTime.'</div>';
    }

    // === НЕ SELECT и не deep: показываем как Write Query (без EXPLAIN) ===
    $aff = @mysqli_affected_rows($link);
    $ins = @mysqli_insert_id($link);
    $meta = [];
    if ($aff >= 0) $meta[] = 'Affected rows: '.$aff;
    if ($ins > 0)  $meta[] = 'Insert ID: '.$ins;

    return '
    <div class="card-header"><div><strong>#'.(int)$id.' - '.hsafe($query_type).' Query</strong></div></div>
    <div class="card-body"><pre>'.hsafe($sql).'</pre></div>
    <div class="card-footer">Measured time: '.$calcTime.($meta ? ' • '.hsafe(implode(' | ', $meta)) : '').'</div>';
}
	
	
	
	
	
	
	
	
	
	
	





	
	

  
  
  function splitsql($sql)
  {
    $map = [
        '/\bstraight_join\b/i' => '<b>STRAIGHT_JOIN</b>',
        '/\bjoin\b/i'          => '<b>JOIN</b>',
        '/\bselect\b/i'        => '<b>SELECT</b>',
        '/\bdelete\b/i'        => '<b>DELETE</b>',
        '/\bupdate\b/i'        => '<b>UPDATE</b>',
        '/\bfrom\b/i'          => '<br><b>FROM</b>',
        '/\bwhere\b/i'         => '<br><b>WHERE</b>',
        '/\bgroup\s+by\b/i'    => '<br><b>GROUP BY</b>',
        '/\bhaving\b/i'        => '<br><b>HAVING</b>',
        '/\border\s+by\b/i'    => '<br><b>ORDER BY</b>',
    ];
    return preg_replace(array_keys($map), array_values($map), strtolower((string)$sql));
  }

  

  $rootpath = './../';
  define ('TQE_VERSION', '0.4 by xam');
  define ('DEBUGMODE', false);
  require_once $rootpath . 'global.php';
  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  gzip ();
  maxsysop ();
  if ($usergroups['cansettingspanel'] !== '1')
  {
    print_no_permission (true);
  }

  if (function_exists ('memory_get_usage'))
  {
    $memory_usage = ' - <b>Memory Usage:</b> ' . mksize (memory_get_usage ());
  }
  
  
  $deep = !empty($_POST['deep']); // 1 = EXPLAIN ANALYZE, 0 = обычный EXPLAIN
  

  $queries = $_POST['queries'];
  
  
  
  $str = '';
$id = 1;
$querytime = 0.0;

// Безопасно читаем totaltime как float
$totaltime = isset($_POST['totaltime']) ? (float)$_POST['totaltime'] : 0.0;



if (!empty($_POST['queries']) && is_array($_POST['queries'])) 
{
    $printed = 0;   // сколько реально показали карточек
    $skipped = 0;   // сколько скрыли (в deep не-SELECT/WITH)

    foreach ($_POST['queries'] as $q => $v) 
    {
        // payload: base64("time,base64(sql)")
        $decoded = base64_decode($v, true);
        if ($decoded === false) {
            continue;
        }

        $parts = explode(',', $decoded, 2);
        $exec  = isset($parts[0]) ? (float)$parts[0] : 0.0;
        $sql64 = $parts[1] ?? '';
        $query = base64_decode($sql64, true);
        if ($query === false) {
            $query = '';
        }

        // В Deep-режиме показываем ТОЛЬКО SELECT/WITH
        if ($deep && !sql_is_select($query)) {
            $skipped++;
            continue; // НЕ увеличиваем $id, НЕ добавляем время
        }

        $html = explain_query($query, $exec, $deep);

        // Если функция решила ничего не рендерить — тоже пропускаем
        if ($html === '' || $html === null) {
            $skipped++;
            continue;
        }

        $str .= '
        <div class="container mt-3">
          <div class="card shadow-sm">'.$html.'</div>
        </div>';

        $id++;
        $querytime += $exec;
        $printed++;
    }

    // Итоги считаем по реально ПЕЧАТАЕМЫМ карточкам
    $phptime    = $totaltime - $querytime;
    $percentphp = $totaltime > 0 ? number_format(($phptime / $totaltime) * 100, 2) : '0.00';
    $percentsql = $totaltime > 0 ? number_format(($querytime / $totaltime) * 100, 2) : '0.00';

    $included_files = str_replace('\\', '/', get_included_files());

    $str .= '
    <div class="container mt-3">
      <div class="card shadow-sm">
        <div class="card-header">
          <b>Generated in</b> '.hsafe($totaltime).' seconds ('.$percentphp.'% PHP / '.$percentsql.'% MySQL)<br />
          <b>MySQL Queries:</b> '.(int)$printed.' / <b>Global Parsing Time:</b> '.hsafe($querytime).(isset($memory_usage) ? hsafe($memory_usage) : '').'<br />
          <b>PHP version:</b> '.hsafe(phpversion()).' / <b>Server Load:</b> '.hsafe(server_load()).' / <b>GZip Compression:</b> '.(($gzipcompress ?? '') === 'yes' ? 'Enabled' : 'Disabled').'
        </div>
        <div class="card-body">'.implode('<br>', array_map('hsafe', $included_files)).'</div>
      </div>
    </div>';

    // Индикация скрытых запросов в Deep
    if ($deep && $skipped > 0) {
        $str .= '<div class="container mt-2"><div class="alert alert-info">Hidden non-SELECT queries: '.(int)$skipped.'</div></div>';
    }

    stdhead('DEBUG MODE');
    echo $str;
    stdfoot();
    exit;
}







 
else 
{
    stdhead('DEBUG MODE');
    echo '<div class="container mt-4"><div class="alert alert-warning">There is no query to show..</div></div>';
    stdfoot();
    exit;
}

  
  
  
  
  
  
  
  
  
  
  
  
  
  

  stdfoot ();
?>
