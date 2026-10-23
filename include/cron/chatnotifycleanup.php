<?php

declare(strict_types=1);

if (!defined('IN_CRON')) {
    exit();
}

require_once INC_PATH . '/functions_notify.php';

// Settings (Staff Panel → Settings → Cleanup → Chat / Notifications); read from the DB so a change
// applies on the next run without depending on the settings cache
$cfg = [
    'chat_keep_deleted_days' => 30,   // soft-deleted chat messages are removed after N days
    'chat_keep_days'         => 0,    // all chat messages older than N days; 0 = keep forever (archive)
    'notify_keep_read_days'  => 60,
    'notify_keep_all_days'   => 180,
    'notify_max_per_user'    => 300,
];
$ph = implode(',', array_fill(0, count($cfg), '?'));
$q  = $db->sql_query_prepared("SELECT name, value FROM settings WHERE name IN ({$ph})", array_keys($cfg));
$CQueryCount++;
while ($q && ($r = $db->fetch_array($q))) {
    if (is_numeric($r['value'])) {
        $cfg[$r['name']] = (int)$r['value'];
    }
}
$keepDeletedDays = max(1, $cfg['chat_keep_deleted_days']);
$keepChatDays    = max(0, $cfg['chat_keep_days']);
$chunk           = 5000; // rows per DELETE, so the table is never locked for long

// ── Notifications ──
$n = notify_prune($cfg['notify_keep_read_days'], $cfg['notify_keep_all_days'], $cfg['notify_max_per_user']);
$CQueryCount++;

// ── Chat: expired temporary bans (until = 0 is permanent) ──
$db->sql_query_prepared('DELETE FROM chat_bans WHERE until > 0 AND until < ?', [TIMENOW]);
$bans = max(0, $db->affected_rows());
$CQueryCount++;

// ── Chat: presence rows of users who haven't opened the chat for a day ──
$db->sql_query_prepared('DELETE FROM chat_presence WHERE last_seen < ?', [TIMENOW - 86400]);
$presence = max(0, $db->affected_rows());
$CQueryCount++;

/**
 * Deletes chat messages matching $where in chunks, together with the pictures uploaded for them
 * (the files in /uploads/ and their comment_files rows). Returns [messages, pictures].
 */
$purge = static function (string $where, array $params) use ($db, $chunk, &$CQueryCount): array {
    $msgs = $pics = 0;
    do {
        $q   = $db->sql_query_prepared("SELECT id FROM chat_messages WHERE {$where} ORDER BY id LIMIT {$chunk}", $params);
        $ids = [];
        while ($q && ($r = $db->fetch_array($q))) $ids[] = (int)$r['id'];
        $CQueryCount++;
        if (!$ids) break;
        $ph = implode(',', array_fill(0, count($ids), '?'));

        // Pictures first: only real editor uploads (…/uploads/<32 hex>.<ext>) are unlinked
        try {
            $fq = $db->sql_query_prepared("SELECT id, file_path FROM comment_files WHERE chat_id IN ({$ph})", $ids);
            $fids = [];
            while ($fq && ($f = $db->fetch_array($fq))) {
                $fids[] = (int)$f['id'];
                $path   = str_replace('\\', '/', (string)$f['file_path']);
                if (preg_match('~/uploads/[a-f0-9]{32}\.[a-z0-9]{2,5}$~i', $path) && is_file($path)) {
                    @unlink($path);
                }
            }
            if ($fids) {
                $db->sql_query_prepared('DELETE FROM comment_files WHERE id IN (' . implode(',', array_fill(0, count($fids), '?')) . ')', $fids);
                $pics += count($fids);
            }
            $CQueryCount += 2;
        } catch (\Throwable $e) {
            // comment_files.chat_id not added yet: delete the messages anyway
        }

        $db->sql_query_prepared("DELETE FROM chat_messages WHERE id IN ({$ph})", $ids);
        $msgs += count($ids);
        $CQueryCount++;
    } while (count($ids) === $chunk);
    return [$msgs, $pics];
};

// ── Chat: soft-deleted messages (a message with an open report stays until staff handles it) ──
[$deleted, $picsDeleted] = $purge(
    'deleted = 1 AND edited_at < ? AND id NOT IN (SELECT msg_id FROM chat_reports WHERE handled_at = 0)',
    [TIMENOW - $keepDeletedDays * 86400]
);

// ── Chat: old messages (off by default — the archive keeps everything) ──
$old = $picsOld = 0;
if ($keepChatDays > 0) {
    [$old, $picsOld] = $purge(
        'added < ? AND id NOT IN (SELECT msg_id FROM chat_reports WHERE handled_at = 0)',
        [TIMENOW - $keepChatDays * 86400]
    );
}
$pictures = $picsDeleted + $picsOld;

// ── Chat: reports. Handled ones are kept 90 days; a report whose message is gone is useless ──
$db->sql_query_prepared(
    'DELETE FROM chat_reports
      WHERE (handled_at > 0 AND handled_at < ?)
         OR msg_id NOT IN (SELECT id FROM chat_messages)',
    [TIMENOW - 90 * 86400]
);
$reports = max(0, $db->affected_rows());
$CQueryCount++;

$total = $n['read'] + $n['old'] + $n['cap'] + $bans + $presence + $deleted + $old + $reports + $pictures;
if ($total > 0) {
    savelog(sprintf(
        'Chat/notifications cleanup: notifications read=%d old=%d cap=%d; chat bans=%d presence=%d deleted=%d old=%d reports=%d pictures=%d',
        $n['read'], $n['old'], $n['cap'], $bans, $presence, $deleted, $old, $reports, $pictures
    ));
    $CQueryCount++;
}
