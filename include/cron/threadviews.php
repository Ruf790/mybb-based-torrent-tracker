<?php

declare(strict_types=1);

/**
 * TS Special Edition / MyBB Thread Views Updater
 * Compatible with PHP 8.4
 */

if (!defined('IN_CRON')) {
    exit();
}

$query = $db->simple_select(
    'tsf_threadviews',
    'tid, COUNT(tid) AS views',
    '',
    ['group_by' => 'tid']
);
++$CQueryCount;


$updatedThreads = 0;

while ($threadView = $db->fetch_array($query)) {
    $tid   = (int)$threadView['tid'];
    $views = (int)$threadView['views'];

    if ($tid > 0 && $views > 0) {
        $db->update_query(
            'tsf_threads',
            ['views' => "views+{$views}"],
            "tid='{$tid}'",
            '1',
            true
        );
        ++$CQueryCount;
        ++$updatedThreads;
    }
}

$db->write_query('TRUNCATE TABLE tsf_threadviews');
++$CQueryCount;

if (isset($plugins) && is_object($plugins)) {
    $plugins->run_hooks('task_threadviews', $task);
}


savelog("Thread views task completed. Updated {$updatedThreads} threads.");
++$CQueryCount;