<?php

declare(strict_types=1);



if (!defined('IN_CRON')) {
    exit();
}

$wrapped = $db->sql_query_prepared(
    'SELECT tid, COUNT(tid) AS views FROM threadviews GROUP BY tid',
    []
);
++$CQueryCount;

$updatedThreads = 0;

if ($wrapped && $wrapped->result) {
    while ($threadView = mysqli_fetch_array($wrapped->result, MYSQLI_BOTH)) {
        $tid   = (int)$threadView['tid'];
        $views = (int)$threadView['views'];

        if ($tid > 0 && $views > 0) {
            $db->sql_query_prepared(
                'UPDATE threads SET views = views + ? WHERE tid = ? LIMIT 1',
                [$views, $tid]
            );
            ++$CQueryCount;
            ++$updatedThreads;
        }
    }
    mysqli_free_result($wrapped->result);
}
if ($wrapped && $wrapped->stmt) {
    mysqli_stmt_close($wrapped->stmt);
}

$db->sql_query_prepared('TRUNCATE TABLE threadviews', []);
++$CQueryCount;

if (isset($plugins) && is_object($plugins)) {
    $plugins->run_hooks('task_threadviews', $task);
}

savelog("Thread views task completed. Updated {$updatedThreads} threads.");
++$CQueryCount;
