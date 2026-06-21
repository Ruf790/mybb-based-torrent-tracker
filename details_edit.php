<?php
if (!isset($Torrent) || !is_array($Torrent)) return;

$t_link = $Torrent['t_link'] ?? '';
if ($t_link && preg_match('@https:\/\/www\.imdb\.com\/title\/(.*)\/@isU', $t_link, $result)) {
    $t_link = $result[0];
}

require(INC_PATH . '/functions_category.php');
$category = intval($Torrent['category'] ?? 0);
$caats    = ts_category_list('category', $category);
$is_mod   = $is_mod ?? false;
?>

<style>
.et-modal .modal-content   { border: none; border-radius: 16px; overflow: hidden; font-size: 1rem; }
.et-modal .modal-header    { background: #1a56db; padding: 1rem 1.25rem; border-bottom: none; }
.et-modal .modal-title     { color: #fff; font-size: 1.1rem; font-weight: 600; }
.et-modal .btn-close       { filter: brightness(0) invert(1); opacity: .7; }
.et-modal .modal-body      { padding: 1.5rem; background: #f8f9fa; }
.et-modal .modal-footer    { background: #f8f9fa; border-top: 1px solid #e9ecef; padding: .75rem 1.25rem; }

.et-section {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
}
.et-section-title {
    font-size: .95rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #6c757d;
    margin-bottom: .75rem;
}

.et-field        { margin-bottom: .85rem; }
.et-field label  { font-size: 1rem; font-weight: 500; color: #495057; margin-bottom: .35rem; display: block; }
.et-field .form-control,
.et-field .form-select { font-size: 1rem; border-radius: 8px; border: 1px solid #dee2e6; }
.et-field .form-control:focus,
.et-field .form-select:focus { border-color: #1a56db; box-shadow: 0 0 0 3px rgba(26,86,219,.1); }
.et-field .input-group-text { background: #f8f9fa; border-color: #dee2e6; font-size: 1rem; border-radius: 8px 0 0 8px; }

.et-img-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: .75rem; }
.et-img-tab  { padding: 6px 14px; border-radius: 20px; font-size: .95rem; font-weight: 500;
    border: 1px solid #dee2e6; background: #fff; color: #6c757d; cursor: pointer; transition: all .15s; }
.et-img-tab.active,
.et-img-tab:hover { background: #1a56db; color: #fff; border-color: #1a56db; }

.et-img-panel  { display: none; }
.et-img-panel.active { display: block; }

.et-preview    { margin-top: .75rem; text-align: center; }
.et-preview img { max-height: 180px; border-radius: 10px; border: 1px solid #dee2e6; display: none; }

.et-switch-row { display: flex; align-items: center; justify-content: space-between;
    padding: .6rem 0; border-bottom: 1px solid #f1f3f5; font-size: 1rem; }
.et-switch-row:last-child { border-bottom: none; }
.et-switch-row label { color: #495057; margin: 0; }
.et-switch-row .form-check-input { width: 2.2em; height: 1.1em; cursor: pointer; }
.et-switch-row .form-check-input:checked { background-color: #1a56db; border-color: #1a56db; }

.et-nukereason { margin-top: .5rem; }
.et-nukereason input { font-size: 1rem; border-radius: 8px; }

.et-btn-save { background: #1a56db; color: #fff; border: none; border-radius: 8px;
    padding: 9px 26px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all .15s; }
.et-btn-save:hover { background: #1648c0; }
.et-btn-save:disabled { opacity: .6; cursor: not-allowed; }
.et-btn-cancel { border-radius: 8px; font-size: 1rem; }
</style>

<div class="modal fade et-modal" id="add_data_Modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
            <i class="bi bi-pencil-square me-2"></i>Edit: <?= htmlspecialchars(mb_substr($Torrent['name'] ?? '', 0, 50)) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form method="post" id="insert_form" enctype="multipart/form-data" onsubmit="return etSubmit(event)">

          <!-- Basic info -->
          <div class="et-section">
            <div class="et-section-title"><i class="bi bi-info-circle me-1"></i>Basic information</div>

            <div class="et-field">
              <label for="name">Torrent name</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-type"></i></span>
                <input type="text" name="name" id="name" class="form-control"
                       value="<?= htmlspecialchars($Torrent['name'] ?? '') ?>" required>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6 et-field">
                <label for="category">Category</label>
                <?= $caats ?? '<select name="category" class="form-select"><option>Select...</option></select>' ?>
              </div>
              <div class="col-md-6 et-field">
                <label for="t_link">IMDB link</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fab fa-imdb"></i></span>
                  <input type="text" name="t_link" id="t_link" class="form-control"
                         value="<?= htmlspecialchars($t_link ?? '') ?>" placeholder="https://www.imdb.com/title/...">
                </div>
              </div>
            </div>

            <div class="et-field mb-0">
              <label for="descr">Description</label>
              <textarea name="descr" id="descr" class="form-control" rows="7"
                        style="resize:vertical;font-size:.875rem;border-radius:8px" required><?= htmlspecialchars($Torrent['descr'] ?? '') ?></textarea>
            </div>
          </div>

          <!-- Images -->
          <div class="et-section">
            <div class="et-section-title"><i class="bi bi-images me-1"></i>Cover images</div>

            <div class="row g-3">
              <!-- Image 1 -->
              <div class="col-md-6">
                <div class="fw-semibold mb-2" style="font-size:.85rem">Cover image 1</div>
                <div class="et-img-tabs">
                  <span class="et-img-tab active" onclick="etImgTab(1,'url',this)">URL</span>
                  <span class="et-img-tab" onclick="etImgTab(1,'file',this)">Upload</span>
                </div>
                <div id="et-img1-url" class="et-img-panel active">
                  <input type="text" name="t_image_url" class="form-control"
                         placeholder="https://..."
                         value="<?= htmlspecialchars($Torrent['t_image'] ?? '') ?>"
                         oninput="etPreview(1,this.value)">
                </div>
                <div id="et-img1-file" class="et-img-panel">
                  <input type="file" name="t_image_file" class="form-control" accept="image/*"
                         onchange="etPreviewFile(1,this)">
                </div>
                <div class="et-preview">
                  <img id="et-prev1" src="<?= htmlspecialchars($Torrent['t_image'] ?? '') ?>"
                       style="<?= !empty($Torrent['t_image']) ? 'display:block' : '' ?>">
                </div>
              </div>

              <!-- Image 2 -->
              <div class="col-md-6">
                <div class="fw-semibold mb-2" style="font-size:.85rem">Cover image 2</div>
                <div class="et-img-tabs">
                  <span class="et-img-tab active" onclick="etImgTab(2,'url',this)">URL</span>
                  <span class="et-img-tab" onclick="etImgTab(2,'file',this)">Upload</span>
                </div>
                <div id="et-img2-url" class="et-img-panel active">
                  <input type="text" name="t_image_url2" class="form-control"
                         placeholder="https://..."
                         value="<?= htmlspecialchars($Torrent['t_image2'] ?? '') ?>"
                         oninput="etPreview(2,this.value)">
                </div>
                <div id="et-img2-file" class="et-img-panel">
                  <input type="file" name="t_image_file2" class="form-control" accept="image/*"
                         onchange="etPreviewFile(2,this)">
                </div>
                <div class="et-preview">
                  <img id="et-prev2" src="<?= htmlspecialchars($Torrent['t_image2'] ?? '') ?>"
                       style="<?= !empty($Torrent['t_image2']) ? 'display:block' : '' ?>">
                </div>
              </div>
            </div>
          </div>

          <?php if ($is_mod): ?>
          <!-- Moderator options -->
          <div class="et-section">
            <div class="et-section-title"><i class="bi bi-shield-check me-1"></i>Moderator options</div>

            <div class="row g-0">
              <div class="col-md-6" style="padding-right:.75rem">
                <?php
                $switches_left = [
                    ['free',         $lang->upload['free1']??'Free',         $lang->upload['free2']??''],
                    ['silver',       $lang->upload['silver1']??'Silver',     $lang->upload['silver2']??''],
                    ['thirtypercent',$lang->upload['thirtypercent1']??'30% Leech', $lang->upload['thirtypercent2']??''],
                    ['doubleupload', $lang->upload['doubleupload1']??'Double Upload', $lang->upload['doubleupload2']??''],
                ];
                foreach ($switches_left as [$name, $label, $desc]):
                    $checked = ($Torrent[$name] ?? '') === 'yes' ? 'checked' : '';
                ?>
                <div class="et-switch-row">
                  <div>
                    <span class="fw-semibold"><?= $label ?></span>
                    <?php if ($desc): ?><br><small class="text-muted"><?= $desc ?></small><?php endif; ?>
                  </div>
                  <div class="form-check form-switch mb-0">
                    <input type="checkbox" class="form-check-input" name="<?= $name ?>" value="yes" <?= $checked ?>>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <div class="col-md-6" style="padding-left:.75rem;border-left:1px solid #f1f3f5">
                <?php
                $switches_right = [
                    ['allowcomments', $lang->upload['allowcomments1']??'Allow Comments', $lang->upload['allowcomments2']??'', 'no'],
                    ['sticky',        $lang->upload['sticky1']??'Sticky',               $lang->upload['sticky2']??'', 'yes'],
                ];
                foreach ($switches_right as [$name, $label, $desc, $val]):
                    $checked = ($Torrent[$name] ?? '') === $val ? 'checked' : '';
                ?>
                <div class="et-switch-row">
                  <div>
                    <span class="fw-semibold"><?= $label ?></span>
                    <?php if ($desc): ?><br><small class="text-muted"><?= $desc ?></small><?php endif; ?>
                  </div>
                  <div class="form-check form-switch mb-0">
                    <input type="checkbox" class="form-check-input" name="<?= $name ?>" value="<?= $val ?>" <?= $checked ?>>
                  </div>
                </div>
                <?php endforeach; ?>

                <!-- Nuked -->
                <div class="et-switch-row">
                  <div>
                    <span class="fw-semibold"><?= $lang->upload['nuked1']??'Nuked' ?></span>
                    <?php if (!empty($lang->upload['nuked2'])): ?>
                    <br><small class="text-muted"><?= $lang->upload['nuked2'] ?></small>
                    <?php endif; ?>
                  </div>
                  <div class="form-check form-switch mb-0">
                    <input type="checkbox" class="form-check-input" name="isnuked" value="yes"
                           id="isnuked" onchange="etToggleNuke()"
                           <?= ($Torrent['isnuked']??'')==='yes' ? 'checked' : '' ?>>
                  </div>
                </div>
                <div id="et-nukereason" class="et-nukereason"
                     style="display:<?= ($Torrent['isnuked']??'')==='yes'?'block':'none' ?>">
                  <input type="text" name="WhyNuked" class="form-control form-control-sm"
                         placeholder="<?= $lang->upload['nreason']??'Reason for nuke' ?>"
                         value="<?= htmlspecialchars($Torrent['WhyNuked']??'') ?>">
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <button type="submit" id="et-save" class="et-btn-save">
            <i class="bi bi-check2 me-1"></i>Save changes
          </button>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary et-btn-cancel" data-bs-dismiss="modal">
          Cancel
        </button>
      </div>

    </div>
  </div>
</div>

<script>
function etImgTab(num, type, el) {
    document.querySelectorAll(`#et-img${num}-url, #et-img${num}-file`).forEach(p => p.classList.remove('active'));
    document.getElementById(`et-img${num}-${type}`).classList.add('active');
    el.closest('.et-img-tabs').querySelectorAll('.et-img-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
}

function etPreview(num, url) {
    const img = document.getElementById('et-prev' + num);
    if (url && url.length > 10) { img.src = url; img.style.display = 'block'; }
    else img.style.display = 'none';
}

function etPreviewFile(num, input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('et-prev' + num);
        img.src = e.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}

function etToggleNuke() {
    const cb = document.getElementById('isnuked');
    document.getElementById('et-nukereason').style.display = cb.checked ? 'block' : 'none';
}

function etSubmit(event) {
    event.preventDefault();
    const btn = document.getElementById('et-save');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving...';

    const formData = new FormData(document.getElementById('insert_form'));
    formData.append('id', '<?= htmlspecialchars($Torrent['id'] ?? '') ?>');
    formData.append('ajax', '1');

    fetch('<?= $BASEURL ?>/xmlhttp.php?action=edit_torrent', {
        method: 'POST', body: formData, credentials: 'same-origin'
    })
    .then(r => r.text())
    .then(text => {
        let data;
        try { data = JSON.parse(text); } catch(e) { throw new Error('Server error'); }
        if (data.success) {
            showToast('Torrent updated!', 'success');
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('add_data_Modal'))?.hide();
                setTimeout(() => window.location.reload(), 800);
            }, 800);
        } else {
            showToast('Error: ' + (data.message || 'Unknown error'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Save changes';
        }
    })
    .catch(err => {
        showToast('Error: ' + err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Save changes';
    });

    return false;
}
</script>