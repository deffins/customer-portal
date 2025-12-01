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
        
        loadFiles(user.id);
        loadChecklists(user.id);
        loadLinks(user.id);
    }
    
    function loadFiles(telegramId) {
        debugLog('Loading files for', telegramId);
        
        ajaxPost({
            action: 'get_customer_files',
            telegram_id: telegramId,
            nonce: CONFIG.nonce
        }, function(response) {
            displayFiles(response.data.files);
        }, function(error) {
            document.getElementById('files-container').innerHTML = '<p>Failed to load files: ' + escapeHtml(error) + '</p>';
        });
    }
    
    function loadChecklists(telegramId) {
        debugLog('Loading checklists for', telegramId);
        
        ajaxPost({
            action: 'get_customer_checklists',
            telegram_id: telegramId,
            nonce: CONFIG.nonce
        }, function(response) {
            displayChecklists(response.data.checklists);
        }, function(error) {
            document.getElementById('checklists-container').innerHTML = '<p>Failed to load checklists: ' + escapeHtml(error) + '</p>';
        });
    }
    
    function loadLinks(telegramId) {
        debugLog('Loading links for', telegramId);
        
        ajaxPost({
            action: 'get_customer_links',
            telegram_id: telegramId,
            nonce: CONFIG.nonce
        }, function(response) {
            displayLinks(response.data.links);
        }, function(error) {
            document.getElementById('links-container').innerHTML = '<p>Failed to load links: ' + escapeHtml(error) + '</p>';
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
        }, function() {
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
    // INITIALIZATION
    // ========================================
    function init() {
        debugLog('Initializing Customer Portal');
        debugLog('User Agent', navigator.userAgent);
        
        // Check for saved user
        var savedUserJson = Storage.get('cp_user');
        if (savedUserJson) {
            try {
                var savedUser = JSON.parse(savedUserJson);
                debugLog('Found saved user', savedUser.first_name);
                showPortal(savedUser);
                return;
            } catch(e) {
                debugLog('Failed to parse saved user', e.message);
                Storage.remove('cp_user');
            }
        }
        
        // Load Telegram widget
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
            });
        });
    }
    
    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
