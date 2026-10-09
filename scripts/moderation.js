// Moderation Page JavaScript Functions
// This file contains all JavaScript for moderation.php pages

(function() {
    'use strict';

    // ============================
    // 0. I18N + SAFE DOM HELPERS
    // ============================
    // AGS_LANG (js_* lang keys, prefix stripped) is printed by moderation.php before this script.
    // English fallbacks below are used when a key is missing. Translations are inserted as text only.
    function t(key, fallback, ...args) {
        let s = (typeof AGS_LANG === 'object' && AGS_LANG !== null && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
        args.forEach(function (a, i) { s = s.split('{' + (i + 1) + '}').join(String(a)); });
        return s;
    }

    function clearNode(node) {
        while (node.firstChild) node.removeChild(node.firstChild);
    }

    function el(tag, className, text) {
        const n = document.createElement(tag);
        if (className) n.className = className;
        if (text !== undefined) n.textContent = text;
        return n;
    }

    // <i class="{iconClass}"></i> + " " + text (text node), replaces the node content
    function setIconLabel(node, iconClass, text) {
        clearNode(node);
        node.appendChild(el('i', iconClass));
        node.appendChild(document.createTextNode(' ' + text));
    }

    // <div class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>text</div>
    function showSpinner(container, text) {
        clearNode(container);
        const box = el('div', 'text-center text-muted');
        box.appendChild(el('i', 'fas fa-spinner fa-spin me-2'));
        box.appendChild(document.createTextNode(text));
        container.appendChild(box);
    }

    // <div class="{cls}"><i class="{icon}"></i><strong>title</strong><p class="mb-0 mt-2 small">text</p></div>
    function showNotice(container, cls, iconClass, title, text) {
        clearNode(container);
        const box = el('div', cls);
        box.appendChild(el('i', iconClass));
        box.appendChild(el('strong', '', title));
        box.appendChild(el('p', 'mb-0 mt-2 small', text));
        container.appendChild(box);
    }

    // Server-provided values (thread title, author, ...) keep their previous HTML handling.
    function appendHtml(parent, html) {
        const s = document.createElement('span');
        s.innerHTML = html;
        parent.appendChild(s);
    }

    // <div class="{cls}"><strong>label</strong> {server value}</div>
    function labelRow(cls, label, value) {
        const row = el('div', cls);
        row.appendChild(el('strong', '', label));
        row.appendChild(document.createTextNode(' '));
        appendHtml(row, value);
        return row;
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
                showSpinner(previewContent, t('validating_url', 'Validating URL...'));
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
                setIconLabel(submitBtn, 'fas fa-spinner fa-spin me-2', t('moving_posts', 'Moving Posts...'));
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
                showToast(t('toast_enter_url', 'Please enter a thread URL'), 'error');
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
                showToast(t('toast_invalid_url', 'Invalid thread URL format. Please use a valid thread link.'), 'error');
            }
            if (previewContent && threadPreview) {
                showNotice(previewContent, 'text-danger', 'fas fa-exclamation-circle me-2',
                    t('invalid_url_title', 'Invalid URL format'),
                    t('invalid_url_text', 'Please use a valid thread URL containing thread ID.'));
                threadPreview.classList.add('show');
            }
            return false;
        }
        
        const threadId = threadInfo.tid;
        
        // Check if trying to move to same thread
        const currentThreadId = document.querySelector('input[name="tid"]')?.value || '';
        if (threadId == currentThreadId) {
            if (showAlert) {
                showToast(t('toast_same_thread', 'Cannot move posts to the same thread. Please choose a different thread.'), 'error');
            }
            if (previewContent && threadPreview) {
                showNotice(previewContent, 'text-warning', 'fas fa-exclamation-triangle me-2',
                    t('same_thread_title', 'Same Thread Detected'),
                    t('same_thread_text', 'You are trying to move posts to the same thread. Please select a different destination thread.'));
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
        showSpinner(previewContent, t('validating_thread', 'Validating thread...'));

        fetch('xmlhttp.php?action=get_thread_info&tid=' + encodeURIComponent(threadId))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    const serverMessage = data.errors && data.errors[0];
                    const errBox = el('div', 'text-danger');
                    errBox.appendChild(el('i', 'fas fa-exclamation-circle me-2'));
                    if (serverMessage) {
                        appendHtml(errBox, serverMessage);
                    } else {
                        errBox.appendChild(document.createTextNode(t('thread_load_failed', 'Could not load thread info')));
                    }
                    clearNode(previewContent);
                    previewContent.appendChild(errBox);
                    return;
                }

                const info = el('div');
                info.appendChild(labelRow('mb-3', t('thread_title', 'Thread Title:'), data.title));

                const grid = el('div', 'row');
                grid.appendChild(labelRow('col-md-6 mb-2', t('thread_author', 'Author:'), data.author));
                grid.appendChild(labelRow('col-md-6 mb-2', t('thread_posts', 'Total Posts:'), data.posts));
                grid.appendChild(labelRow('col-md-6 mb-2', t('thread_lastpost', 'Last Post:'), data.lastpost));
                grid.appendChild(labelRow('col-md-6 mb-2', t('lbl_forum', 'Forum:'), data.forum));
                info.appendChild(grid);

                const ok = el('div', 'mt-3 text-success small');
                ok.appendChild(el('i', 'fas fa-check-circle me-2'));
                ok.appendChild(document.createTextNode(t('thread_valid', 'Valid thread URL detected. Ready to move posts.')));
                info.appendChild(ok);

                clearNode(previewContent);
                previewContent.appendChild(info);
            })
            .catch(() => {
                clearNode(previewContent);
                const failBox = el('div', 'text-danger');
                failBox.appendChild(el('i', 'fas fa-exclamation-circle me-2'));
                failBox.appendChild(document.createTextNode(t('thread_load_error', 'Error loading thread info')));
                previewContent.appendChild(failBox);
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
                        alert(t('merge_enter_url', 'Please enter the thread URL to merge.'));
                        return;
                    }
                    
                    if (!confirm(t('merge_confirm', 'Are you sure you want to merge these threads?') + '\n\n' + t('merge_confirm_note', 'This action is permanent and cannot be undone.'))) {
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