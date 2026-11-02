-- Reactions with the site's smilies (include/functions_reactions.php).
-- One table for every kind of item: type 'chat' = chat_messages.id ('comment' reserved for torrent comments).
-- rkey is the smilie code (smilies.stext); utf8mb4_bin because :D and :d are different smilies.

CREATE TABLE IF NOT EXISTS `reactions` (
  `type` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `item_id` int unsigned NOT NULL,
  `uid` int unsigned NOT NULL,
  `rkey` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `added` int unsigned NOT NULL,
  PRIMARY KEY (`type`,`item_id`,`uid`,`rkey`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- When a message last got / lost a reaction: the chat sends such messages again to everybody
ALTER TABLE `chat_messages`
  ADD COLUMN `react_at` int unsigned NOT NULL DEFAULT '0' AFTER `edited_by`,
  ADD KEY `react_at` (`react_at`);
