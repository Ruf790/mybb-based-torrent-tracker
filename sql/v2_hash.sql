-- BitTorrent v2 / hybrid torrents (BEP 52)
-- Clients announce a hybrid torrent twice: with the v1 info-hash (SHA-1) and with the v2
-- info-hash truncated to 20 bytes (first 40 hex chars of the SHA-256). announce.php looks the
-- torrent up by either; v2_hash holds the truncated v2 hash, NULL for v1-only torrents.
-- Run once, then fill existing torrents with: php sql/fill_v2_hashes.php

ALTER TABLE torrents
    ADD COLUMN v2_hash CHAR(40) NULL DEFAULT NULL AFTER info_hash,
    ADD INDEX idx_v2_hash (v2_hash);
