<?php


$user_id = 0;
if (isset($CURUSER) && is_array($CURUSER) && isset($CURUSER['id'])) 
{
    $user_id = (int)$CURUSER['id'];
}

?>

<!-- ========== COMMENT MODALS ========== -->

<!-- Delete Comment Modal -->
<div class="modal fade" id="deleteCommentModal" tabindex="-1" aria-labelledby="deleteCommentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteCommentModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this comment? This action cannot be undone.
      </div>
      <div class="modal-body" id="errorModalBody">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmDeleteComment" type="button" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Comment Modal -->
<div class="modal fade" id="editCommentModal" tabindex="-1" aria-labelledby="editCommentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="editCommentModalLabel">Edit Comment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="mb-2">
          <!-- Text Styles -->
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[b]', '[/b]')"><b>B</b></button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[i]', '[/i]')"><i>I</i></button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[u]', '[/u]')"><u>U</u></button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[s]', '[/s]')"><s>S</s></button>

          <!-- Alignment -->
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[left]', '[/left]')">Left</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[center]', '[/center]')">Center</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[right]', '[/right]')">Right</button>

          <!-- Color & Size -->
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[color=red]', '[/color]')">Red</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[size=18]', '[/size]')">Size</button>

          <!-- Links & Media -->
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[url]', '[/url]')">URL</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[img]', '[/img]')">IMG</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[video]', '[/video]')">Video</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[youtube]', '[/youtube]')">YouTube</button>

          <!-- Quote & Code -->
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[quote]', '[/quote]')">Quote</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[code]', '[/code]')">Code</button>

          <!-- Lists -->
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[list]\n[*]', '\n[/list]')">List</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[list=1]\n[*]', '\n[/list]')">#List</button>
        </div>

        <textarea id="editCommentText" class="form-control mb-3" rows="6" placeholder="Edit your comment..."></textarea>

        <h6>Live Preview</h6>
        <div id="bbcodePreview" class="border p-2 bg-light rounded" style="min-height: 100px;"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmEditComment" type="button" class="btn btn-primary">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Mass Delete comment(s) Confirm Modal -->
<div class="modal fade" id="massDeleteConfirmModal" tabindex="-1" aria-labelledby="massDeleteConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="massDeleteConfirmModalLabel">Confirm Mass Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete <span id="selectedCommentsCount" class="fw-bold">0</span> comment(s)? This action cannot be undone.
      </div>
      <div class="modal-body">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmMassDelete" type="button" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>













<!-- Modal Report Comment -->
<div class="modal fade" id="reportCommentModal" tabindex="-1" aria-labelledby="reportCommentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <!-- Заголовок с синим градиентом -->
            <div class="modal-header bg-gradient bg-primary text-white">
                <h5 class="modal-title fw-semibold" id="reportCommentModalLabel">
                    <i class="bi bi-flag-fill me-2"></i>Report Comment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Форма репорта -->
            <form id="reportCommentForm" action="takereport.php" method="POST">
                <div class="modal-body">
                    <!-- Скрытые поля -->
                    <input type="hidden" name="type" id="commentReportType" value="comment">
                    <input type="hidden" name="reported_id" id="commentReportedId" value="">
                    <input type="hidden" name="addedby" id="commentAddedBy" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="parent_id" id="commentParentId" value="">
					
					<input type="hidden" name="reported_user_id" id="commentReportedUserId" value="">
                    
                    <!-- Тип репорта -->
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Reporting: <strong id="reportingComment">Comment</strong>
                    </div>
                    
                    <!-- Предпросмотр комментария -->
                    <div class="card border mb-4">
                        <div class="card-header bg-light py-2">
                            <small class="text-muted fw-medium">
                                <i class="bi bi-chat-text me-1"></i>Comment Preview
                            </small>
                        </div>
                        <div class="card-body py-3">
                            <div class="d-flex align-items-start">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <i class="bi bi-person"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <span class="fw-medium" id="commentAuthorPreview">User</span>
                                            <span class="text-muted small ms-2" id="commentDatePreview"></span>
                                        </div>
                                        <span class="badge bg-primary">Comment</span>
                                    </div>
                                    <p class="mb-0 text-muted" id="commentPreviewText">Comment text will appear here...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Причина репорта -->
                    <div class="mb-4">
                        <label for="commentReportReason" class="form-label fw-medium">
                            <i class="bi bi-exclamation-triangle me-1"></i>Reason for Report
                        </label>
                        <select class="form-select form-select-lg" id="commentReportReason" name="reason" required>
                            <option value="" selected disabled>Select a reason...</option>
                            <optgroup label="Content Issues">
                                <option value="spam">Spam / Advertising</option>
                                <option value="offensive">Offensive / Abusive Language</option>
                                <option value="harassment">Harassment / Bullying</option>
                                <option value="hate_speech">Hate Speech / Discrimination</option>
                                <option value="inappropriate">Inappropriate Content</option>
                            </optgroup>
                            <optgroup label="Other Issues">
                                <option value="spoiler">Spoiler / Leaked Content</option>
                                <option value="misinformation">Misinformation / Fake News</option>
                                <option value="off_topic">Off Topic / Irrelevant</option>
                                <option value="personal_info">Personal Information</option>
                                <option value="other">Other Reason</option>
                            </optgroup>
                        </select>
                        <div class="form-text">Please select the most appropriate reason</div>
                    </div>
                    
                    <!-- Дополнительные детали -->
                    <div class="mb-3">
                        <label for="commentReportDetails" class="form-label fw-medium">
                            <i class="bi bi-chat-text me-1"></i>Additional Details
                        </label>
                        <textarea class="form-control" id="commentReportDetails" name="description" 
                                  rows="4" placeholder="Please explain why this comment should be removed..."
                                  maxlength="2000"></textarea>
                        <div class="form-text d-flex justify-content-between mt-1">
                            <span>Optional but very helpful for moderators</span>
                            <span id="commentCharCount">0/2000</span>
                        </div>
                    </div>
                    
                    <!-- Контактная информация (опционально) -->
                    <div class="mb-3">
                        <label for="commentReportEmail" class="form-label fw-medium">
                            <i class="bi bi-envelope me-1"></i>Contact Email (Optional)
                        </label>
                        <input type="email" class="form-control" id="commentReportEmail" 
                               name="email" placeholder="your@email.com">
                        <div class="form-text">Only used if we need more information</div>
                    </div>
                    
                    <!-- Капча -->
                    <div class="captcha-container mb-3" id="commentCaptchaSection">
                        <div class="d-flex align-items-center mb-2">
                            <label class="form-label fw-medium mb-0">
                                <i class="bi bi-shield-check me-1"></i>Security Check
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" 
                                    id="commentRefreshCaptcha">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="bg-light border rounded p-2 text-center fw-bold fs-4" 
                                     id="commentCaptchaDisplay">ABC123</div>
                            </div>
                            <div class="col-6">
                                <input type="text" class="form-control" 
                                       id="commentCaptchaInput" name="captcha_response" placeholder="Enter code">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Предупреждение -->
                    <div class="alert alert-warning small">
    <div class="d-flex">
        <i class="bi bi-exclamation-triangle me-2 fs-5"></i>
        <div>
            <strong>Important:</strong> Please only report comments that violate our 
            <a href="/rules.php" class="alert-link">community guidelines</a>. 
            False reports may result in penalties.
        </div>
    </div>
</div>
                </div>
                
                <!-- Футер модалки -->
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary px-4" id="submitCommentReport">
                        <i class="bi bi-send me-1"></i>Submit Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- ========== TORRENT MODALS ========== -->

<!-- Modal for Delete Confirmation -->
<div class="modal fade" id="deleteTorrentModal" tabindex="-1" aria-labelledby="deleteTorrentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <!-- Header -->
            <div class="modal-header bg-danger text-white">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <h5 class="modal-title fw-bold" id="deleteTorrentModalLabel">Delete Torrent</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Body -->
            <div class="modal-body py-4">
                <div class="text-center mb-3">
                    <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                        <i class="bi bi-trash3-fill text-danger fs-2"></i>
                    </div>
                    <h6 class="fw-bold text-danger mb-2">Are you sure you want to delete this torrent?</h6>
                    <p class="text-muted mb-3" id="torrentNamePreview">Torrent name will appear here</p>
                    
                    <!-- Warning Box -->
                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 small">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-exclamation-circle me-2 mt-1 text-warning"></i>
                            <div>
                                <strong>Warning:</strong> This action cannot be undone. All torrent data, 
                                including files and statistics, will be permanently removed.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Confirmation Checkbox -->
                    <div class="form-check text-start mt-3">
                        <input class="form-check-input" type="checkbox" id="confirmDelete">
                        <label class="form-check-label small text-muted" for="confirmDelete">
                            I understand this action is permanent and cannot be reversed
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger btn-sm px-4" id="confirmDeleteBtn" disabled>
                    <i class="bi bi-trash3 me-1"></i>Delete Torrent
                </button>
            </div>
        </div>
    </div>
</div>



<!-- Universal Image Preview Modal -->
<div class="modal fade" id="universalImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2" id="universalImageModalTitle">
                    <i class="bi bi-image text-primary"></i> Image Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center bg-light p-0">
                <div style="position: relative; height: 70vh; overflow: hidden;">
                    <img src="" id="universalImagePreview" class="img-fluid"
                         style="max-height: 100%; max-width: 100%; object-fit: contain;">
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100">
                    <div class="text-start">
                        <span class="text-muted fw-medium" id="universalImageDimensions"></span>
                        <span class="text-muted mx-2">•</span>
                        <span class="text-muted fw-medium" id="universalImageSize"></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="universalFullscreenBtn">
                            <i class="bi bi-arrows-angle-expand me-1"></i> Fullscreen
                        </button>
                        <a href="#" class="btn btn-primary" id="universalDownloadBtn" download>
                            <i class="bi bi-download me-1"></i> Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Modal Torrent Report -->

<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <!-- Заголовок с градиентом -->
            <div class="modal-header bg-gradient bg-danger text-white">
                <h5 class="modal-title fw-semibold" id="reportModalLabel">
                    <i class="bi bi-flag-fill me-2"></i>Report Torrent
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Форма репорта -->
            <form id="reportForm" action="takereport.php" method="POST">
                <div class="modal-body">
                    <!-- Скрытое поле ID торрента -->
                     <input type="hidden" name="type" id="reportType" value="torrent">
                    <input type="hidden" name="reported_id" id="reportedId" value="">
                    <input type="hidden" name="addedby" id="addedBy" value="<?php echo $user_id; ?>">
					<input type="hidden" name="reported_user_id" id="reportUserid" value="">
					
					<!-- Тип репорта (автоматически определяется) -->
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Reporting: <strong id="reportingWhat">Torrent</strong>
                    </div>
					
                    
                    <!-- Причина репорта -->
                    <div class="mb-4">
                        <label for="reportReason" class="form-label fw-medium">
                            <i class="bi bi-exclamation-triangle me-1"></i>Reason for Report
                        </label>
                        <select class="form-select form-select-lg" id="reportReason" name="reason" required>
                            <option value="" selected disabled>Select a reason...</option>
                            <option value="copyright">Copyright Infringement</option>
                            <option value="malware">Malware/Virus</option>
                            <option value="fake">Fake/Incorrect Content</option>
                            <option value="broken">Broken/Dead Torrent</option>
                            <option value="inappropriate">Inappropriate Content</option>
                            <option value="other">Other Reason</option>
                        </select>
                        <div class="form-text">Please select the most appropriate reason</div>
                    </div>
                    
                    <!-- Дополнительные детали -->
                    <div class="mb-3">
                        <label for="reportDescription" class="form-label fw-medium">
                            <i class="bi bi-chat-text me-1"></i>Additional Details
                        </label>
                        <textarea class="form-control" id="reportDescription" name="description" 
                                  rows="4" placeholder="Please provide more details about the issue..."
                                  maxlength="2000"></textarea>
                        <div class="form-text d-flex justify-content-between mt-1">
                            <span>Optional but helpful for our moderators</span>
                            <span id="charCount">0/2000</span>
                        </div>
                    </div>
                    
                    <!-- Контактная информация (опционально) -->
                    <div class="mb-3">
                        <label for="reportEmail" class="form-label fw-medium">
                            <i class="bi bi-envelope me-1"></i>Contact Email (Optional)
                        </label>
                        <input type="email" class="form-control" id="reportEmail" 
                               name="email" placeholder="your@email.com">
                        <div class="form-text">Only used if we need more information</div>
                    </div>
                    
                    <!-- Капча (при необходимости) -->
                    <div class="captcha-container mb-3" id="captchaSection">
                        <div class="d-flex align-items-center mb-2">
                            <label class="form-label fw-medium mb-0">
                                <i class="bi bi-shield-check me-1"></i>Security Check
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" 
                                    id="refreshCaptcha">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="bg-light border rounded p-2 text-center fw-bold fs-4" 
                                     id="captchaDisplay">1234</div>
                            </div>
                            <div class="col-6">
                                <input type="text" class="form-control" 
                                       id="captchaInput" placeholder="Enter code">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Футер модалки -->
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger px-4" id="submitReport">
                        <i class="bi bi-send me-1"></i>Submit Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


