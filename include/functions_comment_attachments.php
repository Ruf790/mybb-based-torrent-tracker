<?php

declare(strict_types=1);

/**
 * functions_comment_attachments.php
 * Функции для работы с вложениями в комментариях
 * Подключить: require_once INC_PATH . '/functions_comment_attachments.php';
 */

/**
 * Привязать загруженные вложения к комментарию
 */
 
 
 
function get_attachment_icon2(string $ext): string
{
    global $cache, $attachtypes;

    if (!$attachtypes) {
        $attachtypes = $cache->read('attachtypes');
    }

    $ext  = my_strtolower($ext);
    $name = htmlspecialchars_uni($attachtypes[$ext]['name'] ?? $ext);

    if (!empty($attachtypes[$ext]['icon'])) {
        $icon = trim($attachtypes[$ext]['icon']);

        if (str_starts_with($icon, '<')) {
            if (!str_contains($icon, 'title=')) {
                $pos  = strpos($icon, '>');
                $icon = $pos !== false
                    ? substr($icon, 0, $pos) . " title=\"{$name}\">" . substr($icon, $pos + 1)
                    : $icon;
            }
            if (!str_contains($icon, 'font-size:')) {
                if (str_contains($icon, 'style=')) {
                    $icon = str_replace('style="', 'style="font-size:16px; ', $icon);
                } else {
                    $pos  = strpos($icon, '>');
                    $icon = $pos !== false
                        ? substr($icon, 0, $pos) . ' style="font-size:16px;">' . substr($icon, $pos + 1)
                        : $icon;
                }
            }
            return $icon;
        }
    }

    return "<i class=\"fas fa-file\" title=\"{$name}\" style=\"font-size:16px;color:#ccc;\"></i>";
} 
 
 
 
 
function attach_to_comment(string $posthash, int $comment_id, int $user_id): void
{
    global $db, $maxattachments;
    if (empty($posthash) || !$comment_id) return;

	
	// Та же семантика, что и у постов на форуме: $maxattachments > 0 && ... — 0 пропускает проверку (без лимита)
    $limit = (int)($maxattachments ?? 0);
    $hasLimit = $limit > 0;
	
    $limitSql = '';

    if ($hasLimit) {
        // Сколько уже реально прикреплено к этому комментарию (актуально при редактировании)
        $existingCount = (int)$db->fetch_field(
            $db->sql_query_prepared("SELECT COUNT(aid) as cnt FROM attachments WHERE comment_id = ?", [$comment_id]),
            'cnt'
        );

        $slotsLeft = $limit - $existingCount;
        if ($slotsLeft <= 0) {
            return; // лимит уже достигнут — новые черновики не прикрепляем
        }
        $limitSql = " LIMIT ?";
    }

    // Прикрепляем черновики по posthash (при наличии лимита — не больше, чем осталось свободных слотов)
    $params = [$posthash, $user_id];
    if ($hasLimit) {
        $params[] = $slotsLeft;
    }

    $query = $db->sql_query_prepared(
        "SELECT aid FROM attachments
         WHERE posthash = ? AND uid = ? AND comment_id = 0 AND pid = 0
         ORDER BY dateuploaded ASC" . $limitSql,
        $params
    );

    $aids = [];
    while ($row = $db->fetch_array($query)) {
        $aids[] = (int)$row['aid'];
    }

    if (empty($aids)) return;

    $placeholders = implode(',', array_fill(0, count($aids), '?'));
    $db->sql_query_prepared(
        "UPDATE attachments SET pid = 0, comment_id = ? WHERE aid IN ({$placeholders})",
        [$comment_id, ...$aids]
    );
}

/**
 * Получить вложения комментария
 */
function get_comment_attachments(int $comment_id): array
{
    global $db;
    $result = $db->sql_query_prepared(
        "SELECT * FROM attachments 
         WHERE comment_id = ? AND visible = 1
         ORDER BY dateuploaded ASC",
        [$comment_id]
    );
    $atts = [];
    while ($row = $db->fetch_array($result)) {
        $atts[] = $row;
    }
    return $atts;
}


/**
 * Удалить все вложения комментария
 */
function delete_comment_attachments(int $comment_id): void
{
    global $db;
    
    require_once INC_PATH . '/functions_upload.php';
    
    $uploadDir = TSDIR . '/uploads/attachments/';
    $result = $db->sql_query_prepared(
        "SELECT attachname, thumbnail FROM attachments WHERE comment_id = ?",
        [$comment_id]
    );
    while ($row = $db->fetch_array($result)) {
        delete_uploaded_file($uploadDir . $row['attachname']);
        if (!empty($row['thumbnail']) && $row['thumbnail'] !== 'SMALL') {
            delete_uploaded_file($uploadDir . $row['thumbnail']);
        }
    }
    $db->sql_query_prepared("DELETE FROM attachments WHERE comment_id = ?", [$comment_id]);
}

/**
 * Render: виджет загрузки (вставить в форму комментария)
 */
function render_attachment_uploader(string $posthash, int $user_id, array $existing = [], int $comment_id = 0): string
{
    global $BASEURL, $mybb;
    $post_key = $mybb->post_code ?? '';

    static $js_loaded = false;

    ob_start();
    ?>
    <?php if (!$js_loaded): $js_loaded = true; ?>
    <script src="<?= htmlspecialchars($BASEURL) ?>/scripts/comment_attachments.js"></script>
    <?php endif; ?>

    <div class="comment-attachments-uploader mt-3" data-posthash="<?= htmlspecialchars($posthash) ?>">
        <input type="hidden" name="posthash" value="<?= htmlspecialchars($posthash) ?>">

        <!-- Drop zone -->
        <div class="att-dropzone" id="attDropzone-<?= htmlspecialchars($posthash) ?>">
            <div class="att-dropzone-inner">
                <i class="fas fa-paperclip att-dropzone-icon"></i>
                <div class="att-dropzone-text">
                    <strong>Drag & drop files here</strong>
                    <span>or <label class="att-browse-label">browse<input type="file" class="att-file-input" multiple accept="image/*,.pdf,.zip,.rar,.txt"></label></span>
                </div>
                <div class="att-dropzone-hint">Images, PDF, ZIP, RAR, TXT · max 10MB each</div>
            </div>
        </div>

        <!-- Preview list -->
        <div class="att-preview-list" id="attPreviewList-<?= htmlspecialchars($posthash) ?>">
            <?php foreach ($existing as $att): ?>
            <?= render_attachment_preview_item($att, $BASEURL, true) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    initAttachmentUploader(
        <?= json_encode($posthash) ?>,
        <?= json_encode($post_key) ?>,
        <?= json_encode($BASEURL . '/upload_attachment.php') ?>,
        <?= json_encode($comment_id) ?>
    );
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Render: отображение вложений под комментарием
 */
function render_comment_attachments(int $comment_id): string
{
    global $BASEURL;
    $atts = get_comment_attachments($comment_id);
    if (empty($atts)) return '';

    ob_start();
    ?>
    <div class="att-display-list">
        <?php foreach ($atts as $att): ?>
        <?= render_attachment_preview_item($att, $BASEURL, false) ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render: одно вложение (preview)
 */
function render_attachment_preview_item(array $att, string $BASEURL, bool $deletable): string
{
    $mime     = $att['filetype'] ?? '';
    $isImage  = str_starts_with($mime, 'image/');
    $isVideo  = str_starts_with($mime, 'video/');
    $isAudio  = str_starts_with($mime, 'audio/');
    $url      = $BASEURL . '/uploads/attachments/' . htmlspecialchars($att['attachname']);
    $thumbUrl = (!empty($att['thumbnail']) && $att['thumbnail'] !== 'SMALL')
        ? $BASEURL . '/uploads/attachments/' . htmlspecialchars($att['thumbnail'])
        : $url;
    $name     = htmlspecialchars($att['filename']);
    $size     = format_filesize((int)$att['filesize']);
    $aid      = (int)$att['aid'];
    $ext      = strtolower(pathinfo($att['filename'], PATHINFO_EXTENSION));

    ob_start();
    ?>
    <div class="att-item<?= $isAudio ? ' att-item--audio' : '' ?>" data-aid="<?= $aid ?>">
        <div class="att-item-thumb">
            <?php if ($isImage): ?>
                <a href="<?= $url ?>" target="_blank" class="att-thumb-link">
                    <img src="<?= $thumbUrl ?>" class="att-thumb-img" alt="<?= $name ?>">
                </a>
            <?php elseif ($isVideo): ?>
                <a href="<?= $url ?>" target="_blank" class="att-thumb-link att-video-link">
                    <i class="fas fa-play-circle att-thumb-icon" style="color:#0d6efd;font-size:2rem;"></i>
                </a>
            <?php elseif ($isAudio): ?>
                <i class="fas fa-music att-thumb-icon" style="color:#6f42c1;font-size:2rem;"></i>
            <?php else: ?>
                <?= get_attachment_icon2($ext) ?>
            <?php endif; ?>
        </div>
        <div class="att-item-info">
            <div class="att-item-name">
                <a href="<?= $url ?>" target="_blank"><?= $name ?></a>
            </div>
            <div class="att-item-size"><?= $size ?></div>
            <?php if ($isAudio): ?>
            <audio controls class="att-audio-player mt-1" style="width:100%;height:28px;">
                <source src="<?= $url ?>" type="<?= htmlspecialchars($mime) ?>">
            </audio>
            <?php endif; ?>
        </div>
        <?php if ($deletable): ?>
        <button type="button" class="att-delete-btn" title="Remove">
            <i class="fas fa-times"></i>
        </button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render attachments from pre-fetched array (bulk mode — no DB query)
 */
function render_comment_attachments_from_array(array $atts): string
{
    global $BASEURL;
    if (empty($atts)) return '';

    ob_start();
    echo '<div class="att-display-list">';
    foreach ($atts as $att) {
        echo render_attachment_preview_item($att, $BASEURL, false);
    }
    echo '</div>';
    return ob_get_clean();
}

function format_filesize(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}