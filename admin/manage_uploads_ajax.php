<?php

declare(strict_types=1);

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    exit('<div class="alert alert-danger">' . htmlspecialchars($lang->manage_uploads['err_no_permission']) . '</div>');
}

// ── Helpers ──────────────────────────────────────────────
function getFileTypeClass($ext)
{
    $ext = strtolower((string)$ext);
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'], true)) return 'image';
    if (in_array($ext, ['doc', 'docx', 'odt'], true)) return 'doc';
    if ($ext === 'pdf') return 'pdf';
    if (in_array($ext, ['zip', 'rar', '7z'], true)) return 'zip';
    return 'other';
}

/** [иконка FA, тон] для типа файла */
function mu_file_icon(string $class): array
{
    return match ($class) {
        'image' => ['fa-file-image',  'info'],
        'pdf'   => ['fa-file-pdf',    'danger'],
        'doc'   => ['fa-file-word',   'primary'],
        'zip'   => ['fa-file-zipper', 'warning'],
        default => ['fa-file',        'secondary'],
    };
}

function isNewFile($date)
{
    return (time() - (int)strtotime((string)$date)) < 86400; // 24 часа
}

// ── Prefetch ссылок (вместо запроса на каждую строку) ────
$mu_comment_torrent = [];
$mu_post_tid        = [];

$mu_cids = array_values(array_unique(array_filter(array_map(static fn($f) => (int)($f['comment_id'] ?? 0), $files))));
if ($mu_cids) {
    $ph = implode(',', array_fill(0, count($mu_cids), '?'));
    $r  = $db->sql_query_prepared("SELECT id, torrent FROM comments WHERE id IN ({$ph})", $mu_cids);
    while ($r && ($row = $db->fetch_array($r))) {
        $mu_comment_torrent[(int)$row['id']] = (int)$row['torrent'];
    }
}

$mu_pids = array_values(array_unique(array_filter(array_map(static fn($f) => (int)($f['post_id'] ?? 0), $files))));
if ($mu_pids) {
    $ph = implode(',', array_fill(0, count($mu_pids), '?'));
    $r  = $db->sql_query_prepared("SELECT pid, tid FROM posts WHERE pid IN ({$ph})", $mu_pids);
    while ($r && ($row = $db->fetch_array($r))) {
        $mu_post_tid[(int)$row['pid']] = (int)$row['tid'];
    }
}
?>

<div class="table-responsive">
    <table class="table table-hover align-middle mu-table">
        <thead>
            <tr>
                <th style="width:44px"><input type="checkbox" id="selectAll" class="form-check-input" title="<?= htmlspecialchars($lang->manage_uploads['th_select_all']) ?>"></th>
                <th><i class="fa-solid fa-eye"></i><?= htmlspecialchars($lang->manage_uploads['th_preview']) ?></th>
                <th style="min-width:230px">
                    <div class="d-flex flex-column">
                        <span><i class="fa-solid fa-file"></i><?= htmlspecialchars($lang->manage_uploads['th_file']) ?></span>
                        <input type="text" class="form-control form-control-sm mu-input mt-2" placeholder="<?= htmlspecialchars($lang->manage_uploads['ph_filter_name']) ?>" id="nameFilter" style="border-radius:50rem">
                    </div>
                </th>
                <th><i class="fa-solid fa-expand"></i><?= htmlspecialchars($lang->manage_uploads['th_dimensions']) ?></th>
                <th>
                    <div class="d-flex flex-column">
                        <span><i class="fa-solid fa-link"></i><?= htmlspecialchars($lang->manage_uploads['th_linked']) ?></span>
                        <select class="form-select form-select-sm mu-input mt-2" id="typeFilter" style="border-radius:50rem">
                            <option value=""><?= htmlspecialchars($lang->manage_uploads['filter_all_types']) ?></option>
                            <option value="torrent"  <?= $typeFilter === 'torrent'  ? 'selected' : '' ?>><?= htmlspecialchars($lang->manage_uploads['filter_torrent']) ?></option>
                            <option value="news"     <?= $typeFilter === 'news'     ? 'selected' : '' ?>><?= htmlspecialchars($lang->manage_uploads['filter_news']) ?></option>
                            <option value="comment"  <?= $typeFilter === 'comment'  ? 'selected' : '' ?>><?= htmlspecialchars($lang->manage_uploads['filter_comment']) ?></option>
                            <option value="post"     <?= $typeFilter === 'post'     ? 'selected' : '' ?>><?= htmlspecialchars($lang->manage_uploads['filter_post']) ?></option>
                            <option value="message"  <?= $typeFilter === 'message'  ? 'selected' : '' ?>><?= htmlspecialchars($lang->manage_uploads['filter_message']) ?></option>
                            <option value="unlinked" <?= $typeFilter === 'unlinked' ? 'selected' : '' ?>><?= htmlspecialchars($lang->manage_uploads['filter_unlinked']) ?></option>
                        </select>
                    </div>
                </th>
                <th><i class="fa-solid fa-user"></i><?= htmlspecialchars($lang->manage_uploads['th_uploader']) ?></th>
                <th>
                    <div class="d-flex flex-column">
                        <span><i class="fa-solid fa-calendar-days"></i><?= htmlspecialchars($lang->manage_uploads['th_uploaded']) ?></span>
                        <select class="form-select form-select-sm mu-input mt-2" id="dateFilter" style="border-radius:50rem">
                            <option value=""><?= htmlspecialchars($lang->manage_uploads['filter_all_dates']) ?></option>
                            <option value="today"><?= htmlspecialchars($lang->manage_uploads['filter_today']) ?></option>
                            <option value="week"><?= htmlspecialchars($lang->manage_uploads['filter_week']) ?></option>
                            <option value="month"><?= htmlspecialchars($lang->manage_uploads['filter_month']) ?></option>
                        </select>
                    </div>
                </th>
                <th class="text-end"><?= htmlspecialchars($lang->manage_uploads['th_actions']) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file):
                $is_image        = strpos((string)$file['file_type'], 'image/') === 0;
                $dimensions      = $is_image ? getFileDimensions($file['file_path']) : 'N/A';
                $file_ext        = strtolower(pathinfo((string)$file['file_name'], PATHINFO_EXTENSION));
                $file_type_class = getFileTypeClass($file_ext);
                [$ficon, $ftone] = mu_file_icon($file_type_class);

                $file_path_on_disk = $_SERVER['DOCUMENT_ROOT'] . parse_url((string)$file['file_url'], PHP_URL_PATH);
                $image_exists      = $is_image && is_file($file_path_on_disk);

                $row_type = $file['comment_id'] ? 'comment'
                    : ($file['news_id'] ? 'news'
                    : ($file['torrent_id'] ? 'torrent'
                    : ($file['post_id'] ? 'post'
                    : ($file['messages_id'] ? 'message' : ''))));

                $f_url  = htmlspecialchars((string)$file['file_url']);
                $f_name = htmlspecialchars((string)$file['file_name']);
            ?>
            <tr data-id="<?= $file['id'] ?>" data-file-type="<?= $row_type ?>" data-upload-date="<?= $file['uploaded_at'] ?>"
                data-file-name="<?= $f_name ?>" data-file-size="<?= (int)$file['file_size'] ?>">
                <td>
                    <input type="checkbox" class="form-check-input file-checkbox" name="selected_files[]" value="<?= $file['id'] ?>">
                </td>

                <!-- Preview -->
                <td>
                    <?php if ($is_image && $image_exists): ?>
                        <img src="<?= $f_url ?>"
                             class="img-preview mu-thumb"
                             loading="lazy"
                             alt="<?= $f_name ?>"
                             data-bs-toggle="modal"
                             data-bs-target="#universalImageModal"
                             data-img-src="<?= $f_url ?>"
                             data-title="<?= $f_name ?>">
                    <?php elseif ($is_image): ?>
                        <div class="mu-thumb-ph mu-tone-danger" title="<?= htmlspecialchars($lang->manage_uploads['tip_missing_on_disk']) ?>">
                            <i class="fa-solid fa-image"></i>
                            <small><?= htmlspecialchars($lang->manage_uploads['lbl_not_found']) ?></small>
                        </div>
                    <?php else: ?>
                        <div class="mu-thumb-ph mu-tone-<?= $ftone ?>">
                            <i class="fa-solid <?= $ficon ?>"></i>
                            <small><?= htmlspecialchars($file_ext !== '' ? strtoupper($file_ext) : $lang->manage_uploads['lbl_no_ext']) ?></small>
                        </div>
                    <?php endif; ?>
                </td>

                <!-- File info -->
                <td>
                    <div class="d-flex align-items-start gap-2">
                        <span class="mu-ftype mu-tone-<?= $ftone ?>"><i class="fa-solid <?= $ficon ?>"></i></span>
                        <div class="min-w-0">
                            <div class="mu-fname" title="<?= $f_name ?>"><?= htmlspecialchars((string)cutename($file['file_name'], 27)) ?></div>
                            <div class="mu-meta mt-1">
                                <span><i class="fa-solid fa-weight-hanging"></i><?= mksize($file['file_size']) ?></span>
                                <span><i class="fa-solid fa-tag"></i><?= htmlspecialchars((string)$file['file_type']) ?></span>
                            </div>
                        </div>
                    </div>
                </td>

                <!-- Dimensions -->
                <td>
                    <?php if ($dimensions !== 'N/A'): ?>
                        <span class="mu-badge mu-tone-secondary"><i class="fa-solid fa-expand"></i><?= $dimensions ?></span>
                    <?php else: ?>
                        <span class="text-body-secondary small">—</span>
                    <?php endif; ?>
                </td>

                <!-- Linked to -->
                <td>
                    <div class="d-flex flex-column align-items-start gap-1">
                    <?php if ($file['comment_id']):
                        $cid        = (int)$file['comment_id'];
                        $tid_of_cmt = $mu_comment_torrent[$cid] ?? 0;
                        $comment_link = $tid_of_cmt > 0
                            ? $BASEURL . '/torrent-' . $tid_of_cmt . '-comment-' . $cid . '.html#pid' . $cid
                            : $BASEURL . '/comment-' . $cid . '.html#pid' . $cid;
                    ?>
                        <span class="mu-badge mu-tone-success">
                            <i class="fa-solid fa-comment"></i>
                            <a href="<?= htmlspecialchars($comment_link) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(ags_fmt($lang->manage_uploads['badge_comment'], $cid)) ?></a>
                        </span>
                    <?php endif; ?>

                    <?php if ($file['news_id']): ?>
                        <span class="mu-badge mu-tone-warning">
                            <i class="fa-solid fa-newspaper"></i> <?= htmlspecialchars(ags_fmt($lang->manage_uploads['badge_news'], (int)$file['news_id'])) ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($file['torrent_id']): ?>
                        <span class="mu-badge mu-tone-info">
                            <i class="fa-solid fa-download"></i>
                            <a href="<?= htmlspecialchars($BASEURL . '/' . get_torrent_link($file['torrent_id'])) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(ags_fmt($lang->manage_uploads['badge_torrent'], (int)$file['torrent_id'])) ?></a>
                        </span>
                    <?php endif; ?>

                    <?php if ($file['post_id']):
                        $pid       = (int)$file['post_id'];
                        $post_link = $BASEURL . '/' . get_post_link($file['post_id'], $mu_post_tid[$pid] ?? 0) . '#pid' . $pid;
                    ?>
                        <span class="mu-badge mu-tone-primary">
                            <i class="fa-solid fa-file-lines"></i>
                            <a href="<?= htmlspecialchars($post_link) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(ags_fmt($lang->manage_uploads['badge_post'], $pid)) ?></a>
                        </span>
                    <?php endif; ?>

                    <?php if ($file['messages_id']): ?>
                        <span class="mu-badge mu-tone-secondary">
                            <i class="fa-solid fa-envelope-open-text"></i> <?= htmlspecialchars(ags_fmt($lang->manage_uploads['badge_message'], (int)$file['messages_id'])) ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($row_type === ''): ?>
                        <span class="mu-badge mu-tone-danger" title="<?= htmlspecialchars($lang->manage_uploads['tip_unlinked']) ?>">
                            <i class="fa-solid fa-link-slash"></i> <?= htmlspecialchars($lang->manage_uploads['filter_unlinked']) ?>
                        </span>
                    <?php endif; ?>
                    </div>
                </td>

                <!-- Uploader -->
                <td>
                    <?php if (!empty($file['username'])):
                        $useravatar = format_avatar($file['avatar'], $file['avatardimensions']);
                        $profileUrl = $BASEURL . '/' . get_profile_link($file['user_id']);
                    ?>
                        <div class="mu-user">
                            <a href="<?= htmlspecialchars($profileUrl) ?>">
                                <img src="<?= htmlspecialchars((string)$useravatar['image']) ?>" alt="<?= htmlspecialchars((string)$file['username']) ?>" loading="lazy">
                            </a>
                            <a href="<?= htmlspecialchars($profileUrl) ?>">
                                <?= format_name(htmlspecialchars((string)$file['username']), $file['usergroup']) ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <span class="text-body-secondary small"><i class="fa-solid fa-user-slash me-1"></i><?= htmlspecialchars($lang->manage_uploads['lbl_unknown_user']) ?></span>
                    <?php endif; ?>
                </td>

                <!-- Uploaded -->
                <td>
                    <?php if (isNewFile($file['uploaded_at'])): ?>
                        <span class="mu-new mu-tone-danger"><i class="fa-solid fa-bolt me-1"></i><?= htmlspecialchars($lang->manage_uploads['lbl_new']) ?></span>
                    <?php endif; ?>
                    <div class="mu-date">
                        <div><i class="fa-regular fa-calendar"></i><?= htmlspecialchars(date($lang->manage_uploads['fmt_date'], (int)strtotime((string)$file['uploaded_at']))) ?></div>
                        <div><i class="fa-regular fa-clock"></i><?= date('H:i', strtotime((string)$file['uploaded_at'])) ?></div>
                    </div>
                </td>

                <!-- Actions -->
                <td>
                    <div class="mu-actions">
                        <a class="btn mu-iconbtn" href="<?= $f_url ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars($lang->manage_uploads['tip_open_tab']) ?>">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                        <div class="dropdown">
                            <button class="btn mu-iconbtn dropdown-toggle"
                                    type="button"
                                    id="dropdownMenu<?= $file['id'] ?>"
                                    data-bs-toggle="dropdown"
                                    data-bs-popper-config='{"strategy":"fixed"}'
                                    aria-expanded="false"
                                    title="<?= htmlspecialchars($lang->manage_uploads['tip_more_actions']) ?>">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenu<?= $file['id'] ?>">
                                <li>
                                    <a class="dropdown-item edit-btn"
                                       href="#"
                                       data-bs-toggle="modal"
                                       data-bs-target="#editModal"
                                       data-id="<?= $file['id'] ?>"
                                       data-file-name="<?= $f_name ?>"
                                       data-comment-id="<?= $file['comment_id'] ?? '' ?>"
                                       data-news-id="<?= $file['news_id'] ?? '' ?>"
                                       data-torrent-id="<?= $file['torrent_id'] ?? '' ?>"
                                       data-post-id="<?= $file['post_id'] ?? '' ?>"
                                       data-user-id="<?= $file['user_id'] ?? '' ?>">
                                        <i class="fa-solid fa-pen text-warning"></i><?= htmlspecialchars($lang->manage_uploads['act_edit']) ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item move-btn"
                                       href="#"
                                       data-bs-toggle="modal"
                                       data-bs-target="#moveModal"
                                       data-id="<?= $file['id'] ?>"
                                       data-file-name="<?= $f_name ?>"
                                       data-file-url="<?= $f_url ?>">
                                        <i class="fa-solid fa-arrows-up-down-left-right text-primary"></i><?= htmlspecialchars($lang->manage_uploads['act_move']) ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item copy-btn"
                                       href="#"
                                       data-bs-toggle="modal"
                                       data-bs-target="#copyModal"
                                       data-id="<?= $file['id'] ?>"
                                       data-file-name="<?= $f_name ?>"
                                       data-file-url="<?= $f_url ?>">
                                        <i class="fa-solid fa-copy text-info"></i><?= htmlspecialchars($lang->manage_uploads['act_copy']) ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item"
                                       href="<?= $f_url ?>"
                                       download="<?= $f_name ?>">
                                        <i class="fa-solid fa-download text-success"></i><?= htmlspecialchars($lang->manage_uploads['act_download']) ?>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item btn-delete text-danger"
                                       href="#"
                                       data-id="<?= htmlspecialchars((string)$file['id']) ?>">
                                        <i class="fa-solid fa-trash-can"></i><?= htmlspecialchars($lang->manage_uploads['act_delete']) ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($files)): ?>
            <tr>
                <td colspan="8">
                    <div class="mu-empty">
                        <div class="mu-empty-icon mu-tone-secondary"><i class="fa-solid fa-images"></i></div>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($lang->manage_uploads['empty_title']) ?></h5>
                        <?php if ($search || $typeFilter): ?>
                            <p class="text-body-secondary small mb-3"><?= htmlspecialchars($lang->manage_uploads['empty_filtered']) ?></p>
                            <a href="index.php?act=manage_uploads" class="btn btn-outline-secondary btn-sm" style="border-radius:50rem">
                                <i class="fa-solid fa-filter-circle-xmark me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_clear_filters']) ?>
                            </a>
                        <?php else: ?>
                            <p class="text-body-secondary small mb-0"><?= htmlspecialchars($lang->manage_uploads['empty_hint']) ?></p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$this_script2 = "index.php?act=manage_uploads"
    . ($search     ? "&search=" . urlencode($search)     : "")
    . ($typeFilter ? "&type="   . urlencode($typeFilter) : "");
?>

<?php if ($total_pages > 1): ?>
<div class="mu-pager d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span class="text-body-secondary small">
        <i class="fa-solid fa-layer-group me-1"></i>
        <?= htmlspecialchars(ags_fmt($lang->manage_uploads['pager_info'], (int)$page, (int)$total_pages, ts_nf((int)$total_files))) ?>
    </span>
    <div><?= multipage((int)$total_files, (int)$per_page, (int)$page, $this_script2) ?></div>
</div>
<?php endif; ?>