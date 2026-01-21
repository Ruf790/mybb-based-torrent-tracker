<?php

declare(strict_types=1);

if (!defined('IN_CRON')) {
    exit();
}


define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);
require_once INC_PATH . "/tsf_functions.php";


require_once INC_PATH . "/class_moderation.php";
$moderation = new Moderation;

// Iterate through all our delayed moderation actions
$query = $db->simple_select("delayedmoderation", "*", "delaydateline <= '" . TIMENOW . "'");

while ($delayedmoderation = $db->fetch_array($query)) {
    if (is_object($plugins)) {
        $args = [
            'task' => &$task,
            'delayedmoderation' => &$delayedmoderation,
        ];
        $plugins->run_hooks('task_delayedmoderation', $args);
    }

    $tids = explode(',', $delayedmoderation['tids']);
    $input = my_unserialize($delayedmoderation['inputs']);

    if (str_contains($delayedmoderation['type'], "modtool")) {
        // Custom moderation tools (commented out)
        // list(, $custom_id) = explode('_', $delayedmoderation['type'], 2);
        // $custommod->execute($custom_id, $tids);
    } else {
        match ($delayedmoderation['type']) {
            "openclosethread" => handleOpenCloseThread($moderation, $delayedmoderation['tids'], $db),
            "deletethread" => handleDeleteThread($moderation, $tids),
            "move" => handleMoveThread($moderation, $tids, $input['new_forum'] ?? null),
            "stick" => handleStickThread($moderation, $delayedmoderation['tids'], $db),
            "merge" => handleMergeThread($moderation, $delayedmoderation['tids'], $input, $db),
            "removeredirects" => handleRemoveRedirects($moderation, $tids),
            "removesubscriptions" => handleRemoveSubscriptions($moderation, $tids),
            "approveunapprovethread" => handleApproveThread($moderation, $delayedmoderation['tids'], $db),
            default => null,
        };
    }

    $db->delete_query("delayedmoderation", "did='" . $delayedmoderation['did'] . "'");
}

savelog('The delayed moderation task successfully ran');

++$CQueryCount;

// ====================
// HELPER FUNCTIONS
// ====================

/**
 * Handle open/close thread moderation
 */
function handleOpenCloseThread(Moderation $moderation, string $threadIds, object $db): void
{
    $closedTids = $openTids = [];
    
    $query = $db->simple_select("tsf_threads", "tid,closed", "tid IN({$threadIds})");
	

	
	
    while ($thread = $db->fetch_array($query)) {
        if ((int)$thread['closed'] === 1) {
            $closedTids[] = (int)$thread['tid'];
        } else {
            $openTids[] = (int)$thread['tid'];
        }
    }

    if (!empty($closedTids)) {
        $moderation->open_threads($closedTids);
    }

    if (!empty($openTids)) {
        $moderation->close_threads($openTids);
    }
}

/**
 * Handle thread deletion
 */
function handleDeleteThread(Moderation $moderation, array $tids): void
{
    foreach ($tids as $tid) {
        $moderation->delete_thread((int)$tid);
    }
}

/**
 * Handle thread move
 */
function handleMoveThread(Moderation $moderation, array $tids, ?string $newForum): void
{
    if (!$newForum) {
        return;
    }
    
    foreach ($tids as $tid) {
        $moderation->move_thread((int)$tid, (int)$newForum);
    }
}

/**
 * Handle stick/unstick thread
 */
function handleStickThread(Moderation $moderation, string $threadIds, object $db): void
{
    $unstuckTids = $stuckTids = [];
    
    $query = $db->simple_select("tsf_threads", "tid,sticky", "tid IN({$threadIds})");
	
    
    while ($thread = $db->fetch_array($query)) {
        if ((int)$thread['sticky'] === 1) {
            $stuckTids[] = (int)$thread['tid'];
        } else {
            $unstuckTids[] = (int)$thread['tid'];
        }
    }

    if (!empty($stuckTids)) {
        $moderation->unstick_threads($stuckTids);
    }

    if (!empty($unstuckTids)) {
        $moderation->stick_threads($unstuckTids);
    }
}

/**
 * Handle thread merge
 */
function handleMergeThread(Moderation $moderation, string $threadIds, array $input, object $db): void
{
    $tids = explode(',', $threadIds);
    
    // Should be a single tid
    if (count($tids) !== 1) {
        return;
    }

    $sourceTid = (int)$tids[0];
    $threadUrl = $input['threadurl'] ?? '';
    $subject = $input['subject'] ?? '';

    // Parse thread URL to get target thread ID
    $mergeTid = parseThreadUrlForTid($threadUrl, $db);

    if (!$mergeTid || $mergeTid === $sourceTid) {
        return;
    }

    $mergeThread = get_thread($mergeTid);
    if (!$mergeThread) {
        return;
    }

    // If no subject provided, get from source thread
    if (empty($subject)) {
        $query = $db->simple_select("tsf_threads", "subject", "tid='{$sourceTid}'");
		
		
        $subject = $db->fetch_field($query, "subject") ?? '';
        
    }

    $moderation->merge_threads($mergeTid, $sourceTid, $subject);
}

/**
 * Parse thread URL to extract thread ID
 */
function parseThreadUrlForTid(string $url, object $db): ?int
{
    // Remove anchor part
    $realUrl = explode("#", $url)[0];
    
    // Check for SEO URL
    if (str_ends_with($realUrl, ".html")) {
        // SEO URL format
        preg_match("#thread-([0-9]+)#i", $realUrl, $threadMatch);
        preg_match("#post-([0-9]+)#i", $realUrl, $postMatch);

        if (!empty($threadMatch[1])) {
            return (int)$threadMatch[1];
        }
        
        if (!empty($postMatch[1])) {
            $post = get_post((int)$postMatch[1]);
            return $post['tid'] ?? null;
        }
        
        return null;
    }
    
    // Regular URL format
    $parameters = [];
    $queryString = parse_url($realUrl, PHP_URL_QUERY);
    
    if ($queryString) {
        parse_str($queryString, $parameters);
    }

    if (!empty($parameters['pid']) && empty($parameters['tid'])) {
        $post = get_post((int)$parameters['pid']);
        return $post['tid'] ?? null;
    }

    return !empty($parameters['tid']) ? (int)$parameters['tid'] : null;
}

/**
 * Handle remove redirects
 */
function handleRemoveRedirects(Moderation $moderation, array $tids): void
{
    foreach ($tids as $tid) {
        $moderation->remove_redirects((int)$tid);
    }
}

/**
 * Handle remove subscriptions
 */
function handleRemoveSubscriptions(Moderation $moderation, array $tids): void
{
    $moderation->remove_thread_subscriptions(array_map('intval', $tids), true);
}

/**
 * Handle approve/unapprove thread
 */
function handleApproveThread(Moderation $moderation, string $threadIds, object $db): void
{
    $approvedTids = $unapprovedTids = [];
    
    $query = $db->simple_select("tsf_threads", "tid,visible", "tid IN({$threadIds})");
	
	
    
  
    
    while ($thread = $db->fetch_array($query)) {
        if ((int)$thread['visible'] === 1) {
            $approvedTids[] = (int)$thread['tid'];
        } else {
            $unapprovedTids[] = (int)$thread['tid'];
        }
    }

    if (!empty($approvedTids)) {
        $moderation->unapprove_threads($approvedTids);
    }

    if (!empty($unapprovedTids)) {
        $moderation->approve_threads($unapprovedTids);
    }
}