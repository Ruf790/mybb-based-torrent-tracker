// Moderation Page JavaScript Functions
// This file contains all JavaScript for moderation.php pages

(function() {
    'use strict';

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
        fetchThreadInfo(threadId);
        
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
    function fetchThreadInfo(threadId) {
        const threadPreview = document.getElementById('threadPreview');
        const previewContent = document.getElementById('previewContent');

        if (!threadPreview || !previewContent) return;

        threadPreview.classList.add('show');
        previewContent.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Validating thread...</div>';

        fetch('xmlhttp.php?action=get_thread_info&tid=' + encodeURIComponent(threadId))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    const message = (data.errors && data.errors[0]) || 'Could not load thread info';
                    previewContent.innerHTML =
                        '<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>' + message + '</div>';
                    return;
                }

                previewContent.innerHTML =
                    '<div>' +
                    '<div class="mb-3">' +
                    '<strong>Thread Title:</strong> ' + data.title +
                    '</div>' +
                    '<div class="row">' +
                    '<div class="col-md-6 mb-2">' +
                    '<strong>Author:</strong> ' + data.author +
                    '</div>' +
                    '<div class="col-md-6 mb-2">' +
                    '<strong>Total Posts:</strong> ' + data.posts +
                    '</div>' +
                    '<div class="col-md-6 mb-2">' +
                    '<strong>Last Post:</strong> ' + data.lastpost +
                    '</div>' +
                    '<div class="col-md-6 mb-2">' +
                    '<strong>Forum:</strong> ' + data.forum +
                    '</div>' +
                    '</div>' +
                    '<div class="mt-3 text-success small">' +
                    '<i class="fas fa-check-circle me-2"></i>' +
                    'Valid thread URL detected. Ready to move posts.' +
                    '</div>' +
                    '</div>';
            })
            .catch(() => {
                previewContent.innerHTML =
                    '<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading thread info</div>';
            });
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
        // Initialize move posts page (only surviving page type in this file)
        initializeMovePosts();

        // Initialize general moderation functions
        initializeGeneralModeration();

        // Make global functions available
        window.validateThreadUrl = validateThreadUrl;
        window.extractThreadInfo = extractThreadInfo;
        window.fetchThreadInfo = fetchThreadInfo;
    });
    
})();