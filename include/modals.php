<?php


$user_id = 0;
if (isset($CURUSER) && is_array($CURUSER) && isset($CURUSER['id'])) 
{
    $user_id = (int)$CURUSER['id'];
}



$magnetModal = '

<div class="modal fade" id="magnetModal" tabindex="-1" aria-labelledby="magnetModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg overflow-hidden">
      
      
      <div class="modal-header border-0 bg-primary text-white position-relative" 
           style="background: linear-gradient(145deg, #0d6efd 0%, #0b5ed7 100%) !important;">
        
        <!-- Декоративные элементы -->
        <div class="position-absolute top-0 end-0 opacity-10">
          <i class="fas fa-magnet fa-8x" style="transform: rotate(15deg);"></i>
        </div>
        <div class="position-absolute bottom-0 start-0 opacity-10">
          <i class="fas fa-download fa-6x" style="transform: rotate(-15deg);"></i>
        </div>
        
        <!-- Верхний блик -->
        <div class="position-absolute top-0 start-0 w-100 h-25" 
             style="background: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, transparent 100%);"></div>
        
        <!-- Содержимое заголовка -->
        <div class="position-relative z-index-1">
          <h5 class="modal-title text-white fw-bold" id="magnetModalLabel">
            <i class="fas fa-magnet me-2 fa-spin-slow"></i>Magnet Link
          </h5>
          <p class="text-white-75 small mb-0 mt-1">Download with your torrent client</p>
        </div>
        
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-3 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
        
        <!-- Прогресс-бар в шапке (анимированный) -->
        <div class="position-absolute bottom-0 start-0 w-100" style="height: 3px;">
          <div class="h-100 bg-white" style="width: 100%; animation: shrinkWidth 5s linear forwards;"></div>
        </div>
      </div>
      
      <!-- Body с иконкой и контентом -->
      <div class="modal-body text-center py-5 position-relative">
        
        <!-- Декоративные точки -->
        <div class="particles">
          <div class="particle" style="top: 20%; left: 10%; background: #0d6efd;"></div>
          <div class="particle" style="top: 70%; left: 85%; background: #0b5ed7;"></div>
          <div class="particle" style="top: 40%; left: 90%; background: #0d6efd;"></div>
          <div class="particle" style="top: 80%; left: 15%; background: #0b5ed7;"></div>
        </div>
        
        <!-- Анимированная иконка магнита -->
        <div class="magnet-icon mb-4 position-relative">
          <div class="icon-circle mx-auto position-relative" 
               style="background: linear-gradient(145deg, #e6f0ff 0%, #cfe2ff 100%); box-shadow: 0 10px 30px rgba(13,110,253,0.2);">
            <div class="glow-effect" style="background: radial-gradient(circle, rgba(13,110,253,0.2) 0%, transparent 70%);"></div>
            <i class="fas fa-magnet fa-4x text-primary"></i>
          </div>
          
          <!-- Пульсирующие кольца -->
          <div class="pulse-ring" style="border-color: rgba(13,110,253,0.2);"></div>
          <div class="pulse-ring" style="border-color: rgba(13,110,253,0.1); animation-delay: 0.5s;"></div>
        </div>
        
        <!-- Инструкция в стиле Bootstrap -->
        <div class="d-flex align-items-center justify-content-center gap-3 mb-4">
          <div class="d-flex align-items-center">
            <span class="badge bg-primary rounded-circle p-2 me-2">1</span>
            <span class="text-muted small">Copy link</span>
          </div>
          <i class="fas fa-arrow-right text-primary"></i>
          <div class="d-flex align-items-center">
            <span class="badge bg-primary rounded-circle p-2 me-2">2</span>
            <span class="text-muted small">Launch client</span>
          </div>
        </div>
        
        <!-- Input group с кнопкой копирования -->
        <div class="input-group mb-3 shadow-sm">
          <span class="input-group-text bg-light border-end-0" id="basic-addon1">
            <i class="fas fa-link text-primary"></i>
          </span>
          <input type="text" id="magnetInput" class="form-control form-control-lg border-start-0 border-end-0" 
                 readonly value="magnet:?xt=urn:btih:..." 
                 style="font-family: "Fira Code", monospace; font-size: 0.9rem; background: #fff;">
          <button class="btn2 btn-primary2" type="button" id="copyMagnetBtn">
            <i class="fas fa-copy me-1"></i>Copy
          </button>
        </div>
        
        <!-- Success message -->
        <div class="copy-success alert alert-primary mt-2 py-2 small d-none fade-in-up" id="copySuccess" 
             style="border-left: 4px solid #0d6efd; background: #e6f0ff;">
          <i class="fas fa-check-circle me-1 text-primary"></i> 
          <span class="fw-medium">✓ Copied to clipboard!</span>
        </div>
        
        <!-- Быстрые подсказки -->
        <div class="d-flex align-items-center justify-content-center gap-3 mt-3">
          <div class="d-flex align-items-center gap-1">
            <i class="fas fa-clock text-primary small"></i>
            <span class="small text-muted">Auto-closes in 5s</span>
          </div>
          <span class="text-primary small">•</span>
          <div class="d-flex align-items-center gap-1">
            <i class="fas fa-shield-alt text-primary small"></i>
            <span class="small text-muted">Secure</span>
          </div>
        </div>
        
        <!-- External torrent badge -->
        <div class="external-badge mt-3">
          <span class="badge bg-primary bg-opacity-10 text-primary px-4 py-2 rounded-pill border border-primary border-opacity-25">
            <i class="fas fa-globe me-2"></i>
            <span class="fw-semibold">External Torrent</span>
          </span>
        </div>
      </div>
      
      <!-- Footer с кнопками -->
      <div class="modal-footer border-0 justify-content-center pb-4 gap-3 bg-light bg-opacity-50">
        <button type="button" class="btn2 btn-outline-primary2 px-5 py-2 rounded-pill" data-bs-dismiss="modal">
          <i class="fas fa-times me-2"></i>Close
        </button>
        
        <button type="button" class="btn2 btn-primary2 px-5 py-2 rounded-pill position-relative" id="openMagnetBtn"
                style="box-shadow: 0 8px 20px rgba(13,110,253,0.3);">
          <span class="position-relative z-index-1">
            <i class="fas fa-play me-2"></i>Launch Client
          </span>
          <span class="position-absolute top-0 start-0 w-100 h-100 rounded-pill" 
                style="background: inherit; filter: blur(10px); opacity: 0.5; z-index: 0;"></span>
        </button>
      </div>
    </div>
  </div>
</div>';




?>

<!-- ========== COMMENT MODALS ========== -->

<!-- Delete Comment Modal -->
<div class="modal fade" id="deleteCommentModal" tabindex="-1" aria-labelledby="deleteCommentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteCommentModalLabel">
          <i class="fa-solid fa-trash me-2"></i>Delete Comment
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div class="text-center mb-3">
          <i class="fa-solid fa-triangle-exclamation text-warning fa-3x mb-3"></i>
          <h6 class="fw-bold">Are you sure you want to delete this comment?</h6>
          <p class="text-muted mb-0 small">This action cannot be undone.</p>
        </div>

        <!-- Превью комментария -->
        <div class="card border-danger border-opacity-25 mb-3">
          <div class="card-header py-2 px-3 bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">
            <span class="small fw-bold text-danger">
              <i class="fas fa-user me-1"></i><span id="commentPreviewAuthor">—</span>
            </span>
            <div class="d-flex gap-2 align-items-center">
              <span class="text-muted small" id="commentPreviewDate"></span>
              <span class="badge bg-secondary" id="commentPreviewId"></span>
            </div>
          </div>
          <div class="card-body py-2 px-3">
            <div class="small" style="max-height: 350px; overflow-y: auto;">
              <p class="mb-0 text-muted" id="commentPreviewText"></p>
            </div>
          </div>
        </div>

      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
          <i class="fa-solid fa-xmark me-1"></i>Cancel
        </button>
        <button id="confirmDeleteComment" type="button" class="btn btn-danger btn-sm px-4">
          <i class="fa-solid fa-trash me-1"></i>Delete Comment
        </button>
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
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="massDeleteConfirmModalLabel">
          <i class="fa-solid fa-trash me-2"></i>Confirm Mass Delete
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="text-center mb-3">
          <i class="fa-solid fa-triangle-exclamation text-warning fa-3x mb-3"></i>
          <h6 class="fw-bold">Are you sure you want to delete <span id="selectedCommentsCount" class="text-danger">0</span> comment(s)?</h6>
          <p class="text-muted small mb-0">This action cannot be undone.</p>
        </div>

        <!-- Превью выбранных комментариев -->
        <h6 class="text-muted mb-2">
          <i class="fas fa-eye me-1"></i>Comments to be deleted:
        </h6>
        <div id="massDeletePreviewList" style="max-height: 450px; overflow-y: auto;"></div>
      </div>

      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
          <i class="fa-solid fa-xmark me-1"></i>Cancel
        </button>
        <button id="confirmMassDelete" type="button" class="btn btn-danger btn-sm px-4">
          <i class="fa-solid fa-trash me-1"></i>Delete All Selected
        </button>
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
        <div class="modal-content" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                <div id="universalImageModalTitle" class="text-dark">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-image-fill text-primary me-2" style="font-size: 1.5rem;"></i>
                        <div>
                            <h5 class="mb-0 fw-semibold">Image Viewer</h5>
                            <small class="text-muted">Press ESC to close</small>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-0 position-relative">
                <!-- Loading spinner -->
                <div id="imageLoadingSpinner" class="position-absolute top-50 start-50 translate-middle" style="display: none; z-index: 10;">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                
                <!-- Error message -->
                <div id="imageErrorMessage" class="position-absolute top-50 start-50 translate-middle text-center" style="display: none; z-index: 10;">
                    <div class="bg-danger text-white p-3 rounded-3 shadow-lg">
                        <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                        <p class="mb-0 mt-2"><span></span></p>
                    </div>
                </div>
                
                <!-- Image container -->
                <div class="text-center overflow-auto" style="max-height: 80vh; background: #f4f4f4; padding: 10px;">
                    <img id="universalImagePreview" 
                         class="img-fluid rounded-3 shadow-sm" 
                         style="transition: transform 0.3s ease; cursor: zoom-in; background: white;"
                         alt="Preview">
                </div>
            </div>
            
            <div class="modal-footer" style="border-top: 1px solid rgba(0,0,0,0.05); background: rgba(255,255,255,0.8);">
                <div class="d-flex gap-2 align-items-center">
                    <!-- Image information -->
                    <span id="universalImageSize" class="text-muted small bg-light px-3 py-2 rounded-pill">
                        <i class="bi bi-database me-1"></i>
                        <span class="fw-semibold">—</span>
                    </span>
                    <span id="universalImageDimensions" class="text-muted small bg-light px-3 py-2 rounded-pill">
                        <i class="bi bi-arrows-angle-expand me-1"></i>
                        <span class="fw-semibold">—</span>
                    </span>
                </div>
                
                <div class="d-flex gap-2">
                    <!-- Zoom level -->
                    <span id="zoomLevel" class="badge bg-light text-dark rounded-pill px-3 py-2 align-self-center shadow-sm" style="font-size: 0.85rem;">
                        <i class="bi bi-percent me-1"></i>100%
                    </span>
                    
                    <!-- Control buttons -->
                    <button id="zoomOutBtn" class="btn btn-sm btn-light rounded-pill px-3" title="Zoom out (Ctrl+-)" style="border: 1px solid #dee2e6;">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <button id="zoomInBtn" class="btn btn-sm btn-light rounded-pill px-3" title="Zoom in (Ctrl++)" style="border: 1px solid #dee2e6;">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    <button id="rotateBtn" class="btn btn-sm btn-light rounded-pill px-3" title="Rotate (R)" style="border: 1px solid #dee2e6;">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <button id="universalFullscreenBtn" class="btn btn-sm btn-light rounded-pill px-3" title="Fullscreen (F)" style="border: 1px solid #dee2e6;">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>
                    <a id="universalDownloadBtn" class="btn btn-sm btn-primary rounded-pill px-4" download title="Download" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="bi bi-download me-1"></i>
                        Download
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional styles for light theme */
#universalImageModal .modal-content {
    border-radius: 20px;
    overflow: hidden;
}

#universalImageModal .modal-header {
    padding: 1rem 1.5rem;
    background: white;
}

#universalImageModal .modal-footer {
    padding: 1rem 1.5rem;
    backdrop-filter: blur(10px);
}

#universalImageModal .btn-light {
    background: white;
    color: #495057;
    transition: all 0.2s ease;
}

#universalImageModal .btn-light:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    color: #667eea;
}

#universalImageModal .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.3);
}

#universalImageModal .bg-light {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    border: 1px solid rgba(255,255,255,0.5);
}

#universalImagePreview {
    max-width: 100%;
    max-height: calc(80vh - 20px);
    object-fit: contain;
}

/* Animation for buttons */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

#universalImageModal .btn-primary:active {
    animation: pulse 0.3s ease;
}

/* Scrollbar styles */
#universalImageModal .overflow-auto::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

#universalImageModal .overflow-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

#universalImageModal .overflow-auto::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 4px;
}

#universalImageModal .overflow-auto::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}
</style>





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


