// Moderation Page JavaScript Functions
// This file contains all JavaScript for moderation.php pages

(function() {
    'use strict';
    
    // ============================
    // 1. COMMON UTILITY FUNCTIONS
    // ============================
    
    // Count posts from pipe-separated string
    function countPosts(postIdsString) {
        if (!postIdsString || postIdsString.trim() === '') {
            return 0;
        }

        return postIdsString
            .split('|')
            .map(id => id.trim())
            .filter(id => id !== '' && id !== '0' && !isNaN(parseInt(id)))
            .length;
    }
    
    
    // ============================
    // 2. DELETE POSTS PAGE FUNCTIONS
    // ============================
    
    function initializeDeletePosts() {
        const postIdsElement = document.getElementById('postIds');
        const postCountElement = document.getElementById('postCount');
        const deleteForm = document.querySelector('.needs-validation');

        if (!postIdsElement || !postCountElement) {
            return false; // Not on delete posts page
        }

        const postIds = postIdsElement.value;
        const postCount = countPosts(postIds);

        postCountElement.textContent = postCount;

        window.postData = {
            count: postCount,
            threadId: document.getElementById('threadId')?.value || '',
            postIds: postIds
        };

        // Form validation
        if (deleteForm) {
            deleteForm.addEventListener('submit', function(event) {
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                this.classList.add('was-validated');
            }, false);
        }

        return true;
    }
    
    function showFinalWarning() {
        const postIdsElement = document.getElementById('postIds');
        const threadIdElement = document.getElementById('threadId');

        if (!postIdsElement || !threadIdElement) {
            alert('Error: Cannot find post data.');
            return;
        }

        const postIds = postIdsElement.value;
        const postCount = countPosts(postIds);
        const threadId = threadIdElement.value;
        const postIdsArray = postIds.split('|').filter(id => id.trim() !== '');

        if (postCount === 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'No Posts Selected',
                    text: 'Please select posts to delete first.',
                    icon: 'info',
                    confirmButtonColor: '#6c757d'
                });
            } else {
                alert('No posts selected for deletion.');
            }
            return;
        }

        if (typeof Swal === 'undefined') {
            alert('SweetAlert2 is not loaded. Action aborted.');
            return;
        }

        Swal.fire({
            title: 'Review Selection',
            html: `
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-exclamation-circle fa-3x text-warning"></i>
                    </div>
                    <p>You have selected <strong>${postCount}</strong> post(s) for permanent deletion.</p>

                    <div class="alert alert-secondary mt-3">
                        <strong>Thread ID:</strong> ${threadId}
                    </div>

                    <div class="mt-3">
                        <small class="text-muted">Post IDs:</small>
                        <div class="alert alert-light mt-2 p-3">
                            ${postIdsArray.map(id =>
                                `<span class="badge bg-dark me-1 mb-1">${id}</span>`
                            ).join('')}
                        </div>
                    </div>

                    <p class="text-muted small mt-3">
                        <i class="fas fa-info-circle"></i> This action is irreversible
                    </p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `<i class="fas fa-trash-alt me-1"></i> Delete`,
            cancelButtonText: `<i class="fas fa-times me-1"></i> Cancel`,
            reverseButtons: true,
            width: 650
        }).then((result) => {
            if (result.isConfirmed) {
                const deleteButton = document.getElementById('deleteButton');
                if (deleteButton) {
                    deleteButton.click();
                }
            }
        });
    }
    
    // ============================
    // 3. MERGE POSTS PAGE FUNCTIONS
    // ============================
    
    function initializeMergePosts() {
        const postsContainer = document.getElementById('postsContainer');
        if (!postsContainer) {
            return false; // Not on merge posts page
        }
        
        selectOption('hr'); // Default selection
            
        // Style post items
        const postItems = document.querySelectorAll('.posts-list > div, .posts-list > tr');
        postItems.forEach((item, index) => {
            if (!item.classList.contains('card') && !item.classList.contains('post-item')) {
                item.classList.add('post-item');
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="post-author">
                            <i class="fas fa-user-circle me-1"></i>
                            Author ${index + 1}
                        </span>
                        <span class="post-date">
                            <i class="far fa-clock me-1"></i>
                            Just now
                        </span>
                    </div>
                    <div class="post-content">
                        ${item.innerHTML}
                    </div>
                `;
            }
        });
        
        // Add animation to merge button
        const mergeForm = document.querySelector('form');
        if (mergeForm) {
            mergeForm.addEventListener('submit', function(e) {
                const mergeButton = this.querySelector('.btn-merge');
                if (mergeButton) {
                    mergeButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Merging...';
                    mergeButton.disabled = true;
                }
            });
        }
        
        return true;
    }
    
    function selectOption(option) {
        const optionCards = document.querySelectorAll('.option-card');
        if (optionCards.length === 0) return;
        
        // Remove selected class from all options
        optionCards.forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to chosen option
        const selectedCard = option === 'hr' 
            ? document.querySelector('.hr-option') 
            : document.querySelector('.newline-option');
        
        if (selectedCard) {
            selectedCard.classList.add('selected');
        }
        
        // Check the corresponding radio button
        const radio = document.querySelector(`input[value="${option}"]`);
        if (radio) {
            radio.checked = true;
        }
        
        // Update preview
        updatePreview(option);
    }
    
    function updatePreview(option) {
        const hrPreview = document.getElementById('hrPreview');
        const newlinePreview = document.getElementById('newlinePreview');
        
        if (!hrPreview || !newlinePreview) return;
        
        if (option === 'hr') {
            hrPreview.classList.remove('d-none');
            newlinePreview.classList.add('d-none');
        } else {
            hrPreview.classList.add('d-none');
            newlinePreview.classList.remove('d-none');
        }
    }
    
    // ============================
    // 4. MOVE POSTS PAGE FUNCTIONS
    // ============================
    
    function initializeMovePosts() {
        const threadUrlInput = document.getElementById('threadUrl');
        const moveForm = document.getElementById('moveForm');
        
        if (!threadUrlInput || !moveForm) {
            return false; // Not on move posts page
        }
        
        const clearUrlBtn = document.getElementById('clearUrl');
        const threadPreview = document.getElementById('threadPreview');
        const previewContent = document.getElementById('previewContent');
        const validateBtn = document.getElementById('validateBtn');
        
        let typingTimer;
        const doneTypingInterval = 1000;
        
        // Clear URL input
        if (clearUrlBtn) {
            clearUrlBtn.addEventListener('click', function() {
                threadUrlInput.value = '';
                threadUrlInput.focus();
                if (threadPreview) {
                    threadPreview.classList.remove('show');
                }
            });
        }
        
        // Validate URL on button click
        if (validateBtn) {
            validateBtn.addEventListener('click', function() {
                validateThreadUrl(threadUrlInput.value);
            });
        }
        
        // Real-time URL validation
        threadUrlInput.addEventListener('input', function() {
            clearTimeout(typingTimer);
            
            if (threadUrlInput.value.trim() === '') {
                if (threadPreview) {
                    threadPreview.classList.remove('show');
                }
                return;
            }
            
            // Show loading state
            if (threadPreview && previewContent) {
                threadPreview.classList.add('show');
                previewContent.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Validating URL...</div>';
            }
            
            typingTimer = setTimeout(function() {
                validateThreadUrl(threadUrlInput.value);
            }, doneTypingInterval);
        });
        
        // Form submission
        moveForm.addEventListener('submit', function(e) {
            if (!validateThreadUrl(threadUrlInput.value, true)) {
                e.preventDefault();
                return;
            }
            
            // Show loading state on button
            const submitBtn = this.querySelector('.btn-move');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Moving Posts...';
                submitBtn.disabled = true;
            }
        });
        
        return true;
    }
    
    // Validate thread URL function for move posts
    function validateThreadUrl(url, showAlert = false) {
        const threadPreview = document.getElementById('threadPreview');
        const previewContent = document.getElementById('previewContent');
        
        if (!url.trim()) {
            if (showAlert) {
                showToast('Please enter a thread URL', 'error');
            }
            if (threadPreview) {
                threadPreview.classList.remove('show');
            }
            return false;
        }
        
        // Extract thread ID from URL using MyBB's logic
        const threadInfo = extractThreadInfo(url);   
        
        if (!threadInfo.tid) {
            if (showAlert) {
                showToast('Invalid thread URL format. Please use a valid thread link.', 'error');
            }
            if (previewContent && threadPreview) {
                previewContent.innerHTML = '<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i><strong>Invalid URL format</strong><p class="mb-0 mt-2 small">Please use a valid thread URL containing thread ID.</p></div>';
                threadPreview.classList.add('show');
            }
            return false;
        }
        
        const threadId = threadInfo.tid;
        
        // Check if trying to move to same thread
        const currentThreadId = document.querySelector('input[name="tid"]')?.value || '';
        if (threadId == currentThreadId) {
            if (showAlert) {
                showToast('Cannot move posts to the same thread. Please choose a different thread.', 'error');
            }
            if (previewContent && threadPreview) {
                previewContent.innerHTML = '<div class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i><strong>Same Thread Detected</strong><p class="mb-0 mt-2 small">You are trying to move posts to the same thread. Please select a different destination thread.</p></div>';
                threadPreview.classList.add('show');
            }
            return false;
        }
        
        // Simulate thread info fetching
        simulateThreadInfoFetch(threadId);
        
        return true;
    }
    
    // Extract thread info from URL using MyBB's logic
    function extractThreadInfo(url) {
        const result = { tid: null, pid: null };
        
        if (!url) return result;
        
        // Remove anchor part (after #)
        const cleanUrl = url.split('#')[0];
        
        // Check if it's a SEO URL (ends with .html)
        if (cleanUrl.endsWith('.html')) {
            // Extract thread ID from SEO URL: thread-{tid}.html or thread-{tid}-page-{page}.html
            const threadMatch = cleanUrl.match(/thread-(\d+)/i);
            if (threadMatch && threadMatch[1]) {
                result.tid = threadMatch[1];
            }
            
            // Extract post ID from SEO URL: post-{pid}.html
            const postMatch = cleanUrl.match(/post-(\d+)/i);
            if (postMatch && postMatch[1]) {
                result.pid = postMatch[1];
            }
        } else {
            // Regular URL parsing
            try {
                const urlObj = new URL(cleanUrl);
                const params = new URLSearchParams(urlObj.search);
                
                // Get tid parameter
                if (params.has('tid')) {
                    result.tid = params.get('tid');
                }
                
                // Get pid parameter
                if (params.has('pid')) {
                    result.pid = params.get('pid');
                }
                
                // Alternative parsing for malformed URLs
                if (!result.tid && !result.pid) {
                    // Try to extract from query string manually
                    const queryMatch = cleanUrl.match(/[?&](tid|pid)=(\d+)/g);
                    if (queryMatch) {
                        queryMatch.forEach(match => {
                            const [key, value] = match.substring(1).split('=');
                            if (key === 'tid') result.tid = value;
                            if (key === 'pid') result.pid = value;
                        });
                    }
                }
            } catch (e) {
                // Fallback for invalid URLs - try simple regex
                const tidMatch = cleanUrl.match(/tid=(\d+)/i);
                if (tidMatch && tidMatch[1]) {
                    result.tid = tidMatch[1];
                }
                
                const pidMatch = cleanUrl.match(/pid=(\d+)/i);
                if (pidMatch && pidMatch[1]) {
                    result.pid = pidMatch[1];
                }
            }
        }
        
        return result;
    }
    
    // Simulate thread info fetching
    function simulateThreadInfoFetch(threadId) {
        const threadPreview = document.getElementById('threadPreview');
        const previewContent = document.getElementById('previewContent');
        
        if (!threadPreview || !previewContent) return;
        
        threadPreview.classList.add('show');
        previewContent.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Validating thread...</div>';
        
        // Simulate API call delay
        setTimeout(() => {
            // This is mock data - in real app, fetch from server
            const mockThreads = {
                '123': {
                    title: 'Welcome to the Forum!',
                    author: 'Admin',
                    posts: 45,
                    lastpost: '2 hours ago',
                    forum: 'General Discussion'
                },
                '456': {
                    title: 'Technical Support Thread',
                    author: 'SupportTeam',
                    posts: 128,
                    lastpost: '30 minutes ago',
                    forum: 'Support'
                },
                '789': {
                    title: 'General Discussion Thread',
                    author: 'Community',
                    posts: 312,
                    lastpost: '5 minutes ago',
                    forum: 'General'
                }
            };
            
            const threadInfo = mockThreads[threadId] || {
                title: 'Thread #' + threadId,
                author: 'Unknown Author',
                posts: Math.floor(Math.random() * 100) + 1,
                lastpost: 'Recently',
                forum: 'Unknown Forum'
            };
            
            previewContent.innerHTML = 
                '<div>' +
                '<div class="mb-3">' +
                '<strong>Thread Title:</strong> ' + threadInfo.title +
                '</div>' +
                '<div class="row">' +
                '<div class="col-md-6 mb-2">' +
                '<strong>Author:</strong> ' + threadInfo.author +
                '</div>' +
                '<div class="col-md-6 mb-2">' +
                '<strong>Total Posts:</strong> ' + threadInfo.posts +
                '</div>' +
                '<div class="col-md-6 mb-2">' +
                '<strong>Last Post:</strong> ' + threadInfo.lastpost +
                '</div>' +
                '<div class="col-md-6 mb-2">' +
                '<strong>Forum:</strong> ' + threadInfo.forum +
                '</div>' +
                '</div>' +
                '<div class="mt-3 text-success small">' +
                '<i class="fas fa-check-circle me-2"></i>' +
                'Valid thread URL detected. Ready to move posts.' +
                '</div>' +
                '</div>';
            threadPreview.classList.add('show');
        }, 500);
    }
    
    // ============================
    // 5. DELETE THREAD PAGE FUNCTIONS
    // ============================
    
    function initializeDeleteThread() {
        const confirmCheckbox = document.getElementById('confirmDelete');
        const backupCheckbox = document.getElementById('confirmBackup');
        const deleteButton = document.getElementById('deleteThreadBtn');
        const deleteForm = document.getElementById('threadDeleteForm');

        if (!confirmCheckbox || !backupCheckbox || !deleteButton || !deleteForm) {
            return false; // Not on delete thread page
        }

        let allowSubmit = false;

        function updateDeleteButton() {
            deleteButton.disabled = !(confirmCheckbox.checked && backupCheckbox.checked);
        }

        confirmCheckbox.addEventListener('change', updateDeleteButton);
        backupCheckbox.addEventListener('change', updateDeleteButton);
        updateDeleteButton();

        deleteForm.addEventListener('submit', function(e) {
            if (allowSubmit) {
                return true;
            }

            e.preventDefault();

            if (!confirmCheckbox.checked || !backupCheckbox.checked) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Confirmation Required',
                        text: 'Please check both confirmation boxes before deleting.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6',
                    });
                } else {
                    alert('Please check both confirmation boxes before deleting.');
                }
                return;
            }

            const threadSubject = deleteForm.querySelector('input[name="subject"]')?.value || 'Unknown Thread';
            const threadId = deleteForm.querySelector('input[name="tid"]')?.value || '0';

            const proceed = () => {
                allowSubmit = true;

                const originalHTML = deleteButton.innerHTML;
                deleteButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';
                deleteButton.disabled = true;

                // Submit form
                HTMLFormElement.prototype.submit.call(deleteForm);

                setTimeout(() => {
                    deleteButton.innerHTML = originalHTML;
                    deleteButton.disabled = false;
                    allowSubmit = false;
                }, 3000);
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '<strong class="text-danger">⚠️ Final Warning</strong>',
                    html: `
                        <div class="text-start">
                            <div class="alert alert-danger border-danger mb-3">
                                <h6 class="alert-heading mb-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    This action <strong>cannot</strong> be undone!
                                </h6>
                                <p class="mb-0">You are about to <strong class="text-danger">permanently delete</strong> this thread.</p>
                            </div>
                            
                            <div class="card border-danger mb-3">
                                <div class="card-header bg-danger bg-opacity-10 text-danger py-2">
                                    <i class="fas fa-file-alt me-2"></i><strong>Thread to delete:</strong>
                                </div>
                                <div class="card-body py-3">
                                    <h6 class="mb-1">${threadSubject}</h6>
                                    <small class="text-muted">
                                        <i class="fas fa-hashtag me-1"></i>ID: ${threadId}
                                    </small>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <h6 class="mb-2"><i class="fas fa-trash-alt me-2"></i>What will be deleted:</h6>
                                <ul class="mb-0 ps-3">
                                    <li>All posts and replies</li>
                                    <li>All attachments and files</li>
                                    <li>Poll data and votes</li>
                                    <li>Thread statistics and history</li>
                                </ul>
                            </div>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e53e3e',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: `
                        <i class="fas fa-trash-alt me-2"></i>
                        Yes, delete permanently
                    `,
                    cancelButtonText: `
                        <i class="fas fa-times me-2"></i>
                        Cancel
                    `,
                    reverseButtons: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        proceed();
                    }
                });
            } else {
                if (confirm('Are you sure you want to permanently delete this thread?\n\nThis action cannot be undone!')) {
                    proceed();
                }
            }
        });
        
        return true;
    }
    
    // ============================
    // 6. GENERAL MODERATION FUNCTIONS
    // ============================
    
    function initializeGeneralModeration() {
        // Add confirmation to merge threads form
        const mergeThreadForm = document.querySelector('form');
        if (mergeThreadForm && mergeThreadForm.querySelector('input[name="threadurl"]')) {
            const submitBtn = mergeThreadForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.addEventListener('click', function(e) {
                    const threadUrl = mergeThreadForm.querySelector('input[name="threadurl"]').value;
                    
                    if (!threadUrl) {
                        e.preventDefault();
                        alert('Please enter the thread URL to merge.');
                        return;
                    }
                    
                    if (!confirm('Are you sure you want to merge these threads?\n\nThis action is permanent and cannot be undone.')) {
                        e.preventDefault();
                    }
                });
                
                // Highlight required fields
                const requiredInputs = mergeThreadForm.querySelectorAll('input[required]');
                requiredInputs.forEach(function(input) {
                    input.addEventListener('blur', function() {
                        if (!this.value) {
                            this.classList.add('is-invalid');
                        } else {
                            this.classList.remove('is-invalid');
                        }
                    });
                });
            }
        }
    }
    
    // ============================
    // 7. MAIN INITIALIZATION
    // ============================
    
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize based on which page we're on
        let pageInitialized = false;
        
        // Try to initialize each page type
        pageInitialized = initializeDeletePosts();
        if (!pageInitialized) pageInitialized = initializeMergePosts();
        if (!pageInitialized) pageInitialized = initializeMovePosts();
        if (!pageInitialized) pageInitialized = initializeDeleteThread();
        
        // Initialize general moderation functions
        initializeGeneralModeration();
        
        // Make global functions available
        window.showFinalWarning = showFinalWarning;
        window.selectOption = selectOption;
        window.updatePreview = updatePreview;
        window.validateThreadUrl = validateThreadUrl;
        window.extractThreadInfo = extractThreadInfo;
        window.simulateThreadInfoFetch = simulateThreadInfoFetch;
    });
    
})();