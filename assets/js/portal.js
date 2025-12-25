/**
 * Customer Portal JavaScript
 * Version: 2.0
 */

(function() {
    'use strict';
    
    // ========================================
    // CONFIGURATION (from wp_localize_script)
    // ========================================
    var CONFIG = window.cpConfig || {
        ajaxUrl: '',
        nonce: '',
        botUsername: '',
        debug: false
    };
    
    // ========================================
    // DEBUG UTILITIES
    // ========================================
    function debugLog(message, data) {
        if (!CONFIG.debug) return;
        
        var timestamp = new Date().toLocaleTimeString();
        var logEntry = '[' + timestamp + '] ' + message;
        
        if (data !== undefined) {
            try {
                logEntry += ': ' + JSON.stringify(data);
            } catch(e) {
                logEntry += ': [Object]';
            }
        }
        
        console.log('CP Debug:', logEntry);
        
        var debugPanel = document.getElementById('cp-debug');
        var debugLogEl = document.getElementById('cp-debug-log');
        if (debugPanel) {
            if (debugLogEl) {
                debugPanel.style.display = 'block';
                debugLogEl.innerHTML += '<div>' + escapeHtml(logEntry) + '</div>';
            }
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ========================================
    // STORAGE UTILITIES (iOS Safari compatible)
    // ========================================
    var Storage = {
        isAvailable: function(type) {
            var storage;
            var test = '__storage_test__';
            try {
                storage = window[type];
                if (!storage) return false;
                storage.setItem(test, test);
                storage.removeItem(test);
                return true;
            } catch(e) {
                debugLog('Storage not available: ' + type, e.message);
                return false;
            }
        },
        
        set: function(key, value) {
            var strValue = typeof value === 'string' ? value : JSON.stringify(value);
            
            if (this.isAvailable('sessionStorage')) {
                try {
                    sessionStorage.setItem(key, strValue);
                    return true;
                } catch(e) {}
            }
            
            if (this.isAvailable('localStorage')) {
                try {
                    localStorage.setItem(key, strValue);
                    return true;
                } catch(e) {}
            }
            
            try {
                this.setCookie(key, strValue, 1);
                return true;
            } catch(e) {}
            
            return false;
        },
        
        get: function(key) {
            var value = null;
            
            if (this.isAvailable('sessionStorage')) {
                value = sessionStorage.getItem(key);
                if (value) return value;
            }
            
            if (this.isAvailable('localStorage')) {
                value = localStorage.getItem(key);
                if (value) return value;
            }
            
            value = this.getCookie(key);
            if (value) return value;
            
            return null;
        },
        
        remove: function(key) {
            if (this.isAvailable('sessionStorage')) {
                sessionStorage.removeItem(key);
            }
            if (this.isAvailable('localStorage')) {
                localStorage.removeItem(key);
            }
            this.deleteCookie(key);
        },
        
        setCookie: function(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
        },
        
        getCookie: function(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');
            for(var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) {
                    return decodeURIComponent(c.substring(nameEQ.length, c.length));
                }
            }
            return null;
        },
        
        deleteCookie: function(name) {
            document.cookie = name + '=; Max-Age=-99999999; path=/';
        }
    };
    
    // ========================================
    // AJAX HELPER
    // ========================================
    function ajaxPost(data, onSuccess, onError) {
        debugLog('AJAX Request', data);
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', CONFIG.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            
            debugLog('AJAX Response status', xhr.status);
            
            if (xhr.status !== 200) {
                debugLog('AJAX HTTP Error', xhr.status);
                if (onError) onError('Network error: ' + xhr.status);
                return;
            }
            
            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch(e) {
                debugLog('AJAX JSON Parse Error', e.message);
                if (onError) onError('Invalid server response');
                return;
            }
            
            if (response.success) {
                if (onSuccess) onSuccess(response);
                return;
            }
            
            var errorMsg = 'Unknown error';
            if (response.data) {
                if (typeof response.data.message === 'string') {
                    errorMsg = response.data.message;
                }
            }
            debugLog('AJAX Error (server)', errorMsg);
            if (onError) onError(errorMsg);
        };
        
        xhr.onerror = function() {
            debugLog('AJAX Network Error');
            if (onError) onError('Network connection failed');
        };
        
        xhr.timeout = 30000;
        xhr.ontimeout = function() {
            debugLog('AJAX Timeout');
            if (onError) onError('Request timed out');
        };
        
        // Build form data
        var formData = [];
        var keys = Object.keys(data);
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            var value = data[key];
            if (typeof value === 'object' && value !== null) {
                var subKeys = Object.keys(value);
                for (var j = 0; j < subKeys.length; j++) {
                    var subKey = subKeys[j];
                    formData.push(encodeURIComponent(key + '[' + subKey + ']') + '=' + encodeURIComponent(value[subKey]));
                }
            } else {
                formData.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
            }
        }
        
        xhr.send(formData.join('&'));
    }
    
    // ========================================
    // TELEGRAM AUTH CALLBACK
    // ========================================
    window.onTelegramAuth = function(user) {
        debugLog('Telegram auth callback received', user);
        
        var statusEl = document.getElementById('login-status');
        if (statusEl) {
            statusEl.textContent = 'Verifying authentication...';
            statusEl.style.color = '#0073aa';
        }
        
        ajaxPost({
            action: 'verify_telegram_auth',
            nonce: CONFIG.nonce,
            'user[id]': user.id,
            'user[first_name]': user.first_name ? user.first_name : '',
            'user[last_name]': user.last_name ? user.last_name : '',
            'user[username]': user.username ? user.username : '',
            'user[photo_url]': user.photo_url ? user.photo_url : '',
            'user[auth_date]': user.auth_date,
            'user[hash]': user.hash
        }, function(response) {
            debugLog('Auth verified successfully');
            Storage.set('cp_user', JSON.stringify(user));
            showPortal(user);
        }, function(error) {
            debugLog('Auth verification failed', error);
            if (statusEl) {
                statusEl.textContent = 'Authentication failed: ' + error;
                statusEl.style.color = '#dc3232';
            }
            alert('Authentication failed: ' + error);
        });
    };
    
    // ========================================
    // PORTAL FUNCTIONS
    // ========================================
    function showPortal(user) {
        debugLog('Showing portal for user', user.first_name);

        document.getElementById('login-section').style.display = 'none';
        document.getElementById('user-name').textContent = user.first_name;
        document.getElementById('portal-section').style.display = 'block';

        // For Google OAuth users, use cp_user_id; for Telegram users, use id (telegram_id)
        var userId = user.cp_user_id || user.id;

        // Prime user email from profile (and cache)
        loadUserProfile(userId);

        loadFiles(userId, user.cp_user_id);
        loadChecklists(userId, user.cp_user_id);
        loadLinks(userId, user.cp_user_id);
        loadSurveys(userId, user.cp_user_id);

        // Initialize supplement feedback if available
        if (window.cpInitSupplementFeedback) {
            window.cpInitSupplementFeedback(user);
        }

        // Calendar will auto-load via calendar.js init when tab is rendered
    }
    
    function loadFiles(telegramId, cpUserId) {
        debugLog('Loading files for', telegramId);

        var params = {
            action: 'get_customer_files',
            nonce: CONFIG.nonce
        };

        // Send user_id if it's a Google user (cpUserId exists), otherwise telegram_id
        if (cpUserId) {
            params.user_id = cpUserId;
        } else {
            params.telegram_id = telegramId;
        }

        ajaxPost(params, function(response) {
            displayFiles(response.data.files);
        }, function(error) {
            document.getElementById('files-container').innerHTML = '<p>Failed to load files: ' + escapeHtml(error) + '</p>';
        });
    }

    function loadChecklists(telegramId, cpUserId) {
        debugLog('Loading checklists for', telegramId);

        var params = {
            action: 'get_customer_checklists',
            nonce: CONFIG.nonce
        };

        // Send user_id if it's a Google user (cpUserId exists), otherwise telegram_id
        if (cpUserId) {
            params.user_id = cpUserId;
        } else {
            params.telegram_id = telegramId;
        }

        ajaxPost(params, function(response) {
            displayChecklists(response.data.checklists);
        }, function(error) {
            document.getElementById('checklists-container').innerHTML = '<p>Failed to load checklists: ' + escapeHtml(error) + '</p>';
        });
    }

    function loadLinks(telegramId, cpUserId) {
        debugLog('Loading links for', telegramId);

        var params = {
            action: 'get_customer_links',
            nonce: CONFIG.nonce
        };

        // Send user_id if it's a Google user (cpUserId exists), otherwise telegram_id
        if (cpUserId) {
            params.user_id = cpUserId;
        } else {
            params.telegram_id = telegramId;
        }

        ajaxPost(params, function(response) {
            displayLinks(response.data.links);
        }, function(error) {
            document.getElementById('links-container').innerHTML = '<p>Failed to load links: ' + escapeHtml(error) + '</p>';
        });
    }

    function loadSurveys(telegramId, cpUserId) {
        debugLog('Loading surveys for', telegramId);

        var params = {
            action: 'cp_get_assigned_surveys',
            nonce: CONFIG.nonce
        };

        // Send user_id if it's a Google user (cpUserId exists), otherwise telegram_id
        if (cpUserId) {
            params.user_id = cpUserId;
        } else {
            params.telegram_id = telegramId;
        }

        ajaxPost(params, function(response) {
            // The surveys wizard will handle display
            if (window.cpDisplaySurveys) {
                window.cpDisplaySurveys(response.data.surveys);
            }
        }, function(error) {
            document.getElementById('surveys-container').innerHTML = '<p>Failed to load surveys: ' + escapeHtml(error) + '</p>';
        });
    }
    
    // ========================================
    // DISPLAY FUNCTIONS
    // ========================================
    function displayFiles(files) {
        var html = '<div class="files-list">';
        if (!files) files = [];
        
        if (files.length > 0) {
            files.forEach(function(file) {
                html += '<div class="file-item">';
                html += '<span class="file-name">' + escapeHtml(file.name) + '</span>';
                html += '<a href="' + escapeHtml(file.webViewLink) + '" target="_blank" rel="noopener" class="button">View</a>';
                if (file.webContentLink) {
                    html += '<a href="' + escapeHtml(file.webContentLink) + '" class="button">Download</a>';
                }
                html += '</div>';
            });
        } else {
            html += '<p>No files available yet.</p>';
        }
        html += '</div>';
        document.getElementById('files-container').innerHTML = html;
    }
    
    function displayChecklists(checklists) {
        var html = '';
        if (!checklists) checklists = [];
        
        if (checklists.length > 0) {
            checklists.forEach(function(checklist) {
                var rawTotal = parseInt(checklist.total_items);
                var rawChecked = parseInt(checklist.checked_items);
                var totalItems = isNaN(rawTotal) ? 0 : rawTotal;
                var checkedItems = isNaN(rawChecked) ? 0 : rawChecked;
                var percentage = totalItems > 0 ? Math.round((checkedItems / totalItems) * 100) : 0;
                
                html += '<div class="checklist-card" data-checklist-id="' + checklist.id + '">';
                html += '<div class="checklist-header">';
                html += '<h4>' + escapeHtml(checklist.title) + '</h4>';
                html += '<button class="checklist-delete-btn" data-id="' + checklist.id + '"><span>✓ Complete</span></button>';
                html += '</div>';
                
                html += '<div class="checklist-progress">';
                html += '<div class="progress-bar"><div class="progress-fill" style="width: ' + percentage + '%;"></div></div>';
                html += '<span class="progress-text">' + checkedItems + '/' + totalItems + ' completed</span>';
                html += '</div>';
                
                var isBagatinatajs = (checklist.type === 'bagatinatajs');
                var hasItems = (checklist.items ? checklist.items.length > 0 : false);
                
                if (isBagatinatajs && hasItems) {
                    var stores = {};
                    checklist.items.forEach(function(item) {
                        var storeName = item.store_name ? item.store_name : 'Other';
                        if (!stores[storeName]) {
                            stores[storeName] = { discount: item.discount_code ? item.discount_code : '', items: [] };
                        }
                        stores[storeName].items.push(item);
                    });
                    
                    html += '<div class="checklist-stores">';
                    Object.keys(stores).forEach(function(storeName) {
                        var store = stores[storeName];
                        html += '<div class="store-group">';
                        html += '<div class="store-header">';
                        html += '<h5>' + escapeHtml(storeName) + '</h5>';
                        if (store.discount) {
                            html += '<span class="discount-badge">Code: ' + escapeHtml(store.discount) + '</span>';
                        }
                        html += '</div>';
                        html += '<div class="store-items">';
                        store.items.forEach(function(item) {
                            html += renderChecklistItem(item, checklist.id);
                        });
                        html += '</div></div>';
                    });
                    html += '</div>';
                } else {
                    html += '<div class="checklist-items">';
                    if (hasItems) {
                        checklist.items.forEach(function(item) {
                            html += renderChecklistItem(item, checklist.id);
                        });
                    } else {
                        html += '<p>No items yet.</p>';
                    }
                    html += '</div>';
                }
                
                html += '</div>';
            });
        } else {
            html = '<p>No checklists available yet.</p>';
        }
        
        document.getElementById('checklists-container').innerHTML = html;
        attachChecklistListeners();
    }
    
    function renderChecklistItem(item, checklistId) {
        var checkedClass = item.is_checked == 1 ? 'checked' : '';
        var checkedAttr = item.is_checked == 1 ? 'checked' : '';
        var html = '<div class="checklist-item ' + checkedClass + '" data-item-id="' + item.id + '" data-checklist-id="' + checklistId + '">';
        html += '<label>';
        html += '<input type="checkbox" ' + checkedAttr + ' class="checklist-checkbox">';
        html += '<span>' + escapeHtml(item.product_name) + '</span>';
        html += '</label>';
        if (item.link) {
            html += '<a href="' + escapeHtml(item.link) + '" target="_blank" rel="noopener" class="product-link">View →</a>';
        }
        html += '</div>';
        return html;
    }
    
    function displayLinks(links) {
        var html = '<div class="links-list">';
        if (!links) links = [];
        
        if (links.length > 0) {
            links.forEach(function(link) {
                var icon = getLinkIcon(link.url);
                html += '<div class="link-item">';
                html += '<span class="link-icon">' + icon + '</span>';
                html += '<div class="link-content">';
                html += '<a href="' + escapeHtml(link.url) + '" target="_blank" rel="noopener" class="link-title">' + escapeHtml(link.description) + '</a>';
                html += '<span class="link-url">' + escapeHtml(getDomain(link.url)) + '</span>';
                html += '</div>';
                html += '</div>';
            });
        } else {
            html += '<p>No links available yet.</p>';
        }
        html += '</div>';
        document.getElementById('links-container').innerHTML = html;
    }
    
    function getLinkIcon(url) {
        if (!url) return '🔗';
        var u = url.toLowerCase();
        if (u.indexOf('youtube.com') !== -1 || u.indexOf('youtu.be') !== -1) return '▶️';
        if (u.indexOf('spotify.com') !== -1) return '🎵';
        if (u.indexOf('apple.com/podcast') !== -1 || u.indexOf('podcast') !== -1) return '🎙️';
        if (u.indexOf('instagram.com') !== -1) return '📷';
        if (u.indexOf('facebook.com') !== -1) return '📘';
        if (u.indexOf('twitter.com') !== -1 || u.indexOf('x.com') !== -1) return '🐦';
        if (u.indexOf('tiktok.com') !== -1) return '🎵';
        if (u.indexOf('linkedin.com') !== -1) return '💼';
        if (u.indexOf('.pdf') !== -1) return '📄';
        if (u.indexOf('docs.google') !== -1) return '📝';
        if (u.indexOf('drive.google') !== -1) return '📁';
        return '🔗';
    }
    
    function getDomain(url) {
        if (!url) return '';
        try {
            var a = document.createElement('a');
            a.href = url;
            return a.hostname;
        } catch(e) {
            return url;
        }
    }
    
    // ========================================
    // CHECKLIST INTERACTIONS
    // ========================================
    function attachChecklistListeners() {
        document.querySelectorAll('.checklist-checkbox').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                var item = this.closest('.checklist-item');
                var itemId = item.getAttribute('data-item-id');
                var checklistId = item.getAttribute('data-checklist-id');
                toggleChecklistItem(itemId, this.checked, checklistId);
            });
        });
        
        document.querySelectorAll('.checklist-delete-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                deleteChecklist(this.getAttribute('data-id'));
            });
        });
    }
    
    var updateQueue = {};
    function toggleChecklistItem(itemId, isChecked, checklistId) {
        var item = document.querySelector('.checklist-item[data-item-id="' + itemId + '"]');
        var card = document.querySelector('.checklist-card[data-checklist-id="' + checklistId + '"]');
        
        if (isChecked) {
            item.classList.add('checked');
        } else {
            item.classList.remove('checked');
        }
        updateProgressBar(card);
        
        if (updateQueue[itemId]) {
            clearTimeout(updateQueue[itemId]);
        }
        
        updateQueue[itemId] = setTimeout(function() {
            ajaxPost({
                action: 'toggle_checklist_item',
                item_id: itemId,
                is_checked: isChecked ? 1 : 0,
                nonce: CONFIG.nonce
            }, function() {
                debugLog('Item updated');
            }, function() {
                // Revert on error
                if (isChecked) {
                    item.classList.remove('checked');
                    item.querySelector('input').checked = false;
                } else {
                    item.classList.add('checked');
                    item.querySelector('input').checked = true;
                }
                updateProgressBar(card);
            });
            delete updateQueue[itemId];
        }, 300);
    }
    
    function updateProgressBar(card) {
        if (!card) return;
        var total = card.querySelectorAll('.checklist-item').length;
        var checked = card.querySelectorAll('.checklist-item.checked').length;
        var percentage = total > 0 ? Math.round((checked / total) * 100) : 0;
        
        var fill = card.querySelector('.progress-fill');
        var text = card.querySelector('.progress-text');
        if (fill) fill.style.width = percentage + '%';
        if (text) text.textContent = checked + '/' + total + ' completed';
    }
    
    function deleteChecklist(checklistId) {
        if (!confirm('Mark this checklist as complete? It will be archived.')) return;

        var card = document.querySelector('.checklist-card[data-checklist-id="' + checklistId + '"]');
        card.style.opacity = '0.5';

        ajaxPost({
            action: 'delete_checklist',
            checklist_id: checklistId,
            nonce: CONFIG.nonce
        }, function(response) {
            // Check if a survey was created
            if (response.data && response.data.survey_created) {
                alert('Checklist completed!\n\nA feedback survey has been created for you. Check the Surveys tab to provide feedback on your supplements.');
            }

            card.style.transition = 'all 0.3s';
            card.style.height = card.offsetHeight + 'px';
            setTimeout(function() {
                card.style.height = '0';
                card.style.padding = '0';
                card.style.margin = '0';
                card.style.overflow = 'hidden';
            }, 10);
            setTimeout(function() {
                card.remove();
                if (document.querySelectorAll('.checklist-card').length === 0) {
                    document.getElementById('checklists-container').innerHTML = '<p>No checklists available yet.</p>';
                }
            }, 310);
        }, function() {
            card.style.opacity = '1';
            alert('Failed to archive checklist.');
        });
    }
    
    // ========================================
    // CALENDAR & BOOKING FUNCTIONS
    // ========================================

    var pendingBooking = null; // Holds the slot while collecting optional email
    var userEmail = null; // Stored email from profile (skip modal when available)

    // Expose function to get telegram ID for calendar.js
    window.cpGetUserTelegramId = function() {
        var savedUserJson = Storage.get('cp_user');
        if (savedUserJson) {
            try {
                var user = JSON.parse(savedUserJson);
                return user.id;
            } catch(e) {
                return null;
            }
        }
        return null;
    };

    // Load user profile (to retrieve stored email)
    function loadUserProfile(telegramId) {
        if (!telegramId) return;

        ajaxPost({
            action: 'cp_get_user_profile',
            nonce: CONFIG.nonce,
            telegram_id: telegramId
        }, function(response) {
            if (response.data && response.data.user) {
                var email = response.data.user.email;
                if (email && isValidEmail(email)) {
                    userEmail = email;
                    Storage.set('cp_last_email', email);
                }
            }
            // If no email on profile but we have a cached one, use it
            if (!userEmail) {
                var cached = Storage.get('cp_last_email');
                if (cached && isValidEmail(cached)) {
                    userEmail = cached;
                }
            }
        }, function() {
            // Ignore errors; just keep existing cached email if any
            var cached = Storage.get('cp_last_email');
            if (cached && isValidEmail(cached)) {
                userEmail = cached;
            }
        });
    }

    // Show booking modal
    window.cpShowBookingModal = function(action, date, hour) {
        var modal = document.getElementById('booking-modal');
        var titleEl = document.getElementById('modal-title');
        var messageEl = document.getElementById('modal-message');
        var confirmBtn = document.getElementById('modal-confirm');

        if (!modal || !titleEl || !messageEl || !confirmBtn) return;

        // Format date and time
        var dateObj = new Date(date + 'T00:00:00');
        var dateStr = dateObj.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var timeStr = hour.toString().padStart(2, '0') + ':00';

        if (action === 'book') {
            titleEl.textContent = 'Confirm Booking';
            messageEl.textContent = 'Book appointment for ' + dateStr + ' at ' + timeStr + '?';
            confirmBtn.textContent = 'Book Appointment';
            confirmBtn.onclick = function() {
                // If we already have an email on file, skip the email modal
                if (userEmail && isValidEmail(userEmail)) {
                    bookSlot(date, hour, userEmail);
                    return;
                }
                // Move to email capture step
                pendingBooking = { date: date, hour: hour };
                hideBookingModal();
                showEmailModal();
            };
        } else if (action === 'cancel') {
            titleEl.textContent = 'Cancel Booking';
            messageEl.textContent = 'Cancel your booking for ' + dateStr + ' at ' + timeStr + '?';
            confirmBtn.textContent = 'Cancel Booking';
            confirmBtn.onclick = function() {
                cancelBooking(date, hour);
            };
        }

        modal.style.display = 'flex';
    };

    // Hide booking modal
    function hideBookingModal() {
        var modal = document.getElementById('booking-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Show email capture modal (optional)
    function showEmailModal() {
        var modal = document.getElementById('email-modal');
        var input = document.getElementById('email-input');
        if (!modal || !input) return;

        var savedEmail = Storage.get('cp_last_email');
        input.value = savedEmail ? savedEmail : '';
        modal.style.display = 'flex';
        input.focus();
    }

    function hideEmailModal() {
        var modal = document.getElementById('email-modal');
        if (modal) {
            modal.style.display = 'none';
        }
        // Clear the notes textarea for next booking
        var bookingNotesInput = document.getElementById('booking-notes');
        if (bookingNotesInput) {
            bookingNotesInput.value = '';
        }
    }

    function isValidEmail(email) {
        if (!email) return true; // optional
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // Book a slot
    function bookSlot(date, hour, clientEmail, bookingNotes) {
        var telegramId = window.cpGetUserTelegramId();
        if (!telegramId) {
            alert('Please log in to book appointments');
            hideBookingModal();
            return;
        }

        debugLog('Booking slot', date, hour);

        // Close modals immediately for snappy UX
        hideBookingModal();
        hideEmailModal();

        // Optimistic update - mark as updating
        var slotKey = date + '_' + hour;
        if (window.cpMarkSlotUpdating) {
            window.cpMarkSlotUpdating(slotKey);
        }

        // Disable the clicked slot temporarily
        var cell = document.querySelector('.bc-slot-cell[data-date="' + date + '"][data-hour="' + hour + '"]');
        if (cell) {
            cell.style.opacity = '0.6';
            cell.style.pointerEvents = 'none';
            cell.style.transition = 'opacity 0.3s ease';
        }

        ajaxPost({
            action: 'cp_book_slot',
            telegram_id: telegramId,
            date: date,
            hour: hour,
            client_email: clientEmail ? clientEmail : '',
            booking_notes: bookingNotes ? bookingNotes : '',
            nonce: CONFIG.nonce
        }, function(response) {
            // Persist email for convenience if provided
            if (clientEmail) {
                Storage.set('cp_last_email', clientEmail);
                userEmail = clientEmail;
            }
            // Success - reload to confirm
            if (window.cpReloadCalendar) {
                window.cpReloadCalendar();
            }
            if (window.cpReloadMyAppointments) {
                window.cpReloadMyAppointments();
            }
        }, function(error) {
            // Error - reload to revert optimistic update
            if (window.cpReloadCalendar) {
                window.cpReloadCalendar();
            }
            alert('Booking failed: ' + escapeHtml(error));
        });
    }

    // Cancel a booking
    function cancelBooking(date, hour) {
        var telegramId = window.cpGetUserTelegramId();
        if (!telegramId) {
            alert('Please log in to cancel bookings');
            hideBookingModal();
            return;
        }

        debugLog('Canceling booking', date, hour);

        // Close modal immediately for snappy UX
        hideBookingModal();

        // Optimistic update - mark as updating
        var slotKey = date + '_' + hour;
        if (window.cpMarkSlotUpdating) {
            window.cpMarkSlotUpdating(slotKey);
        }

        // Disable the clicked slot temporarily
        var cell = document.querySelector('.bc-slot-cell[data-date="' + date + '"][data-hour="' + hour + '"]');
        if (cell) {
            cell.style.opacity = '0.6';
            cell.style.pointerEvents = 'none';
            cell.style.transition = 'opacity 0.3s ease';
        }

        ajaxPost({
            action: 'cp_cancel_booking',
            telegram_id: telegramId,
            date: date,
            hour: hour,
            nonce: CONFIG.nonce
        }, function(response) {
            // Success - reload to confirm
            if (window.cpReloadCalendar) {
                window.cpReloadCalendar();
            }
            if (window.cpReloadMyAppointments) {
                window.cpReloadMyAppointments();
            }
        }, function(error) {
            // Error - reload to revert optimistic update
            if (window.cpReloadCalendar) {
                window.cpReloadCalendar();
            }
            alert('Cancel failed: ' + escapeHtml(error));
        });
    }

    // ========================================
    // INITIALIZATION
    // ========================================
    function init() {
        debugLog('Initializing Customer Portal');
        debugLog('User Agent', navigator.userAgent);

        // Check for saved user
        var savedUser = null;
        var savedUserJson = Storage.get('cp_user');
        if (savedUserJson) {
            try {
                savedUser = JSON.parse(savedUserJson);
                debugLog('Found saved user', savedUser.first_name);
            } catch(e) {
                debugLog('Failed to parse saved user', e.message);
                Storage.remove('cp_user');
            }
        }

        // If no saved user, check if user is logged in via WordPress (Google OAuth)
        if (!savedUser) {
            debugLog('No saved user, checking WordPress authentication');
            ajaxPost({
                action: 'get_current_portal_user',
                nonce: CONFIG.nonce
            }, function(response) {
                debugLog('WordPress user found', response.data.user);
                var user = response.data.user;
                // Save to storage
                Storage.set('cp_user', JSON.stringify(user));
                // Show portal
                showPortal(user);
            }, function(error) {
                debugLog('No WordPress user authenticated', error.message);
                // User is not logged in, load Telegram widget
                debugLog('Loading Telegram widget');
                var telegramContainer = document.getElementById('telegram-login');

                if (telegramContainer && CONFIG.botUsername) {
                    var script = document.createElement('script');
                    script.async = true;
                    script.src = 'https://telegram.org/js/telegram-widget.js?22';
                    script.setAttribute('data-telegram-login', CONFIG.botUsername);
                    script.setAttribute('data-size', 'large');
                    script.setAttribute('data-onauth', 'onTelegramAuth(user)');
                    script.setAttribute('data-request-access', 'write');

                    script.onload = function() {
                        debugLog('Telegram widget loaded');
                    };
                    script.onerror = function() {
                        debugLog('Failed to load Telegram widget');
                        telegramContainer.innerHTML = '<p style="color:red;">Failed to load Telegram login.</p>';
                    };

                    telegramContainer.appendChild(script);
                } else {
                    debugLog('Bot username missing or container not found');
                }
            });
        }

        // Logout button
        var logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                Storage.remove('cp_user');
                location.reload();
            });
        }
        
        // Tab switching
        document.querySelectorAll('.tab-button').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tab = this.getAttribute('data-tab');
                document.querySelectorAll('.tab-button').forEach(function(b) { b.classList.remove('active'); });
                document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById(tab + '-tab').classList.add('active');

                // Trigger calendar initialization when calendar tab is clicked
                if (tab === 'calendar' && window.cpReloadMyAppointments) {
                    window.cpReloadMyAppointments();
                }
            });
        });

        // Modal cancel button
        var modalCancelBtn = document.getElementById('modal-cancel');
        if (modalCancelBtn) {
            modalCancelBtn.addEventListener('click', hideBookingModal);
        }

        // Modal overlay click to close (both modals)
        document.querySelectorAll('.cp-modal-overlay').forEach(function(overlay) {
            overlay.addEventListener('click', function() {
                hideBookingModal();
                hideEmailModal();
                pendingBooking = null;
            });
        });

        // Email modal buttons
        var emailSkipBtn = document.getElementById('email-skip');
        var emailSaveBtn = document.getElementById('email-save');
        var emailInput = document.getElementById('email-input');
        var bookingNotesInput = document.getElementById('booking-notes');

        if (emailSkipBtn) {
            emailSkipBtn.addEventListener('click', function() {
                if (!pendingBooking) {
                    hideEmailModal();
                    return;
                }
                var notes = bookingNotesInput ? bookingNotesInput.value.trim() : '';
                bookSlot(pendingBooking.date, pendingBooking.hour, null, notes || null);
                pendingBooking = null;
            });
        }

        if (emailSaveBtn) {
            emailSaveBtn.addEventListener('click', function() {
                if (!pendingBooking) {
                    hideEmailModal();
                    return;
                }
                var email = emailInput ? emailInput.value.trim() : '';
                if (email && !isValidEmail(email)) {
                    alert('Please enter a valid email address or leave it blank.');
                    return;
                }
                var notes = bookingNotesInput ? bookingNotesInput.value.trim() : '';
                bookSlot(pendingBooking.date, pendingBooking.hour, email || null, notes || null);
                pendingBooking = null;
            });
        }

        // Load Telegram widget (will only load if WordPress auth check fails)
        // This is now called from within the WordPress auth error callback

        // If we have a saved user, show portal immediately
        if (savedUser) {
            debugLog('Showing portal with saved user');
            showPortal(savedUser);
        }
    }
    
    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
