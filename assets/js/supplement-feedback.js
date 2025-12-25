/**
 * Supplement Feedback Module JavaScript - V2 (Mobile-first + Append Notes)
 */

(function() {
    'use strict';

    var CONFIG = window.cpConfig || {};
    var currentUser = null;
    var currentSurvey = null;
    var currentSupplementIndex = 0;
    var autosaveTimer = null;
    var lastSavedText = '';

    /**
     * Initialize supplement feedback
     */
    window.cpInitSupplementFeedback = function(user) {
        currentUser = user;
    };

    /**
     * Start a supplement survey (called from main survey list)
     */
    window.cpStartSupplementSurvey = function(surveyId) {
        // Hide surveys list view
        var listView = document.getElementById('surveys-list-view');
        if (listView) listView.style.display = 'none';

        // Show detail view
        var detailView = document.getElementById('survey-detail-view');
        if (detailView) {
            detailView.style.display = 'block';
            detailView.innerHTML = '<p>Loading supplement survey...</p>';
        }

        // Load the survey
        loadSupplementSurveyV2(surveyId);
    };

    /**
     * Load supplement survey with V2 endpoint
     */
    function loadSupplementSurveyV2(surveyId) {
        var telegramId = window.cpGetUserTelegramId ? window.cpGetUserTelegramId() : null;
        if (!telegramId) {
            alert('Please log in first');
            if (window.cpExitSurvey) window.cpExitSurvey();
            return;
        }

        ajaxPost({
            action: 'cp_get_supplement_survey_v2',
            survey_id: surveyId,
            telegram_id: telegramId,
            nonce: CONFIG.nonce
        }, function(response) {
            currentSurvey = response.data.survey;
            showSupplementListView();
        }, function(error) {
            alert('Failed to load survey: ' + error);
            if (window.cpExitSurvey) window.cpExitSurvey();
        });
    }

    /**
     * SUPPLEMENT SURVEY LIST VIEW
     * Shows all supplements with completion indicators
     */
    function showSupplementListView() {
        var container = document.getElementById('survey-detail-view');
        if (!container || !currentSurvey) return;

        var html = '';
        html += '<div class="supplement-survey-v2">';

        // Header
        html += '<button class="button back-to-surveys-btn" style="margin-bottom: 20px;">← Back to Surveys</button>';
        html += '<h3>' + escapeHtml(currentSurvey.title) + '</h3>';
        html += '<p class="supplement-survey-description">Add your feedback for each supplement:</p>';

        // Supplements list
        html += '<div class="supplements-list-v2">';

        if (!currentSurvey.supplements || currentSurvey.supplements.length === 0) {
            html += '<p>No supplements in this survey.</p>';
        } else {
            // Sort supplements by most recent note first
            var sortedSupplements = currentSurvey.supplements.slice().sort(function(a, b) {
                var aLatest = null;
                var bLatest = null;

                if (a.notes && a.notes.length > 0) {
                    var lastNoteA = a.notes[a.notes.length - 1];
                    aLatest = lastNoteA.created_at ? new Date(lastNoteA.created_at) : null;
                }

                if (b.notes && b.notes.length > 0) {
                    var lastNoteB = b.notes[b.notes.length - 1];
                    bLatest = lastNoteB.created_at ? new Date(lastNoteB.created_at) : null;
                }

                // Sort: most recent first, nulls last
                if (aLatest && bLatest) {
                    return bLatest - aLatest; // Descending order
                } else if (aLatest) {
                    return -1; // a has notes, b doesn't - a comes first
                } else if (bLatest) {
                    return 1; // b has notes, a doesn't - b comes first
                } else {
                    return 0; // Neither has notes - maintain original order
                }
            });

            sortedSupplements.forEach(function(supplement, index) {
                var hasNotes = supplement.has_notes || false;
                var lastNotePreview = '';

                if (hasNotes && supplement.notes && supplement.notes.length > 0) {
                    var lastNote = supplement.notes[supplement.notes.length - 1];
                    lastNotePreview = lastNote.text ? lastNote.text.substring(0, 60) + (lastNote.text.length > 60 ? '...' : '') : '';
                }

                html += '<div class="supplement-list-item-v2" data-index="' + index + '">';

                // Header row: name on left, button on right
                html += '<div class="supplement-list-header-v2">';
                html += '<div class="supplement-header-left">';
                html += '<span class="supplement-status-dot ' + (hasNotes ? 'has-notes' : 'no-notes') + '"></span>';
                html += '<strong class="supplement-list-name-v2">' + escapeHtml(supplement.name) + '</strong>';
                html += '</div>';
                html += '<button class="button supplement-add-btn" data-index="' + index + '">';
                html += 'Add comment';
                html += '</button>';
                html += '</div>';

                // Expandable editor section (hidden by default)
                html += '<div class="supplement-editor-section-v2" style="display: none;">';

                // Show admin context if available
                if (supplement.admin_context) {
                    html += '<div class="supplement-admin-context-inline">';
                    html += '<small>' + escapeHtml(supplement.admin_context) + '</small>';
                    html += '</div>';
                }

                html += '<textarea class="supplement-textarea-v2" data-index="' + index + '" ';
                html += 'placeholder="Jūtams efekts? Slikta reakcija? Cik ilgi lieto? Cik daudz?" rows="4"></textarea>';
                html += '<div class="supplement-editor-actions">';
                html += '<button class="button button-primary supplement-save-btn" data-index="' + index + '">Save</button>';
                html += '<button class="button supplement-cancel-btn" data-index="' + index + '">Cancel</button>';
                html += '</div>';

                // Show existing notes (read-only)
                if (supplement.notes && supplement.notes.length > 0) {
                    html += '<div class="supplement-existing-notes-inline">';
                    html += '<h5>Previous notes:</h5>';
                    supplement.notes.forEach(function(note) {
                        var noteDate = note.created_at ? new Date(note.created_at).toLocaleDateString() : '';
                        html += '<div class="existing-note-inline">';
                        html += '<div class="existing-note-meta-inline">' + noteDate + '</div>';
                        html += '<div class="existing-note-text-inline">' + escapeHtml(note.text).replace(/\n/g, '<br>') + '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }

                html += '</div>'; // .supplement-editor-section-v2

                html += '</div>'; // .supplement-list-item-v2
            });
        }

        html += '</div>'; // .supplements-list-v2

        // Bottom actions
        html += '<div class="supplement-list-actions">';
        html += '<button class="button button-primary finish-survey-btn">Finish & Notify</button>';
        html += '</div>';

        html += '</div>'; // .supplement-survey-v2

        container.innerHTML = html;

        // Attach event listeners
        attachListViewListeners();
    }

    /**
     * Attach event listeners for list view
     */
    function attachListViewListeners() {
        var container = document.getElementById('survey-detail-view');
        if (!container) return;

        // Back button
        var backBtn = container.querySelector('.back-to-surveys-btn');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                if (window.cpExitSurvey) window.cpExitSurvey();
            });
        }

        // Add comment buttons - expand editor
        var addBtns = container.querySelectorAll('.supplement-add-btn');
        for (var i = 0; i < addBtns.length; i++) {
            addBtns[i].addEventListener('click', function(e) {
                e.stopPropagation();
                var index = parseInt(this.getAttribute('data-index'));
                expandSupplementEditor(index);
            });
        }

        // Save buttons
        var saveBtns = container.querySelectorAll('.supplement-save-btn');
        for (var i = 0; i < saveBtns.length; i++) {
            saveBtns[i].addEventListener('click', function(e) {
                e.stopPropagation();
                var index = parseInt(this.getAttribute('data-index'));
                saveAndCollapseEditor(index);
            });
        }

        // Cancel buttons
        var cancelBtns = container.querySelectorAll('.supplement-cancel-btn');
        for (var i = 0; i < cancelBtns.length; i++) {
            cancelBtns[i].addEventListener('click', function(e) {
                e.stopPropagation();
                var index = parseInt(this.getAttribute('data-index'));
                collapseSupplementEditor(index);
            });
        }

        // Finish & Notify button
        var finishBtn = container.querySelector('.finish-survey-btn');
        if (finishBtn) {
            finishBtn.addEventListener('click', submitSupplementSurvey);
        }
    }

    /**
     * Expand supplement editor inline
     */
    function expandSupplementEditor(index) {
        // Reload survey data to get latest notes before expanding
        reloadSurveyData(function() {
            // First collapse all other editors and show their buttons
            var allItems = document.querySelectorAll('.supplement-list-item-v2');
            for (var i = 0; i < allItems.length; i++) {
                var editor = allItems[i].querySelector('.supplement-editor-section-v2');
                var button = allItems[i].querySelector('.supplement-add-btn');
                if (editor) editor.style.display = 'none';
                if (button) button.style.display = 'block';
            }

            // Refresh the list view with latest data
            showSupplementListView();

            // Now expand the selected editor and hide its button
            var items = document.querySelectorAll('.supplement-list-item-v2');
            if (items[index]) {
                var editor = items[index].querySelector('.supplement-editor-section-v2');
                var textarea = items[index].querySelector('.supplement-textarea-v2');
                var button = items[index].querySelector('.supplement-add-btn');

                if (editor && textarea) {
                    editor.style.display = 'block';
                    textarea.focus();
                }
                if (button) {
                    button.style.display = 'none';
                }
            }
        });
    }

    /**
     * Reload survey data to get latest notes
     */
    function reloadSurveyData(callback) {
        if (!currentSurvey) {
            if (callback) callback();
            return;
        }

        var telegramId = window.cpGetUserTelegramId ? window.cpGetUserTelegramId() : null;
        if (!telegramId) {
            if (callback) callback();
            return;
        }

        ajaxPost({
            action: 'cp_get_supplement_survey_v2',
            survey_id: currentSurvey.id,
            telegram_id: telegramId,
            nonce: CONFIG.nonce
        }, function(response) {
            currentSurvey = response.data.survey;
            if (callback) callback();
        }, function(error) {
            console.error('Failed to reload survey:', error);
            if (callback) callback();
        });
    }

    /**
     * Collapse supplement editor
     */
    function collapseSupplementEditor(index) {
        var items = document.querySelectorAll('.supplement-list-item-v2');
        if (items[index]) {
            var editor = items[index].querySelector('.supplement-editor-section-v2');
            var textarea = items[index].querySelector('.supplement-textarea-v2');
            var button = items[index].querySelector('.supplement-add-btn');

            if (editor && textarea) {
                editor.style.display = 'none';
                textarea.value = '';
            }
            if (button) {
                button.style.display = 'block';
            }
        }
    }

    /**
     * Save note and collapse, then open next supplement
     */
    function saveAndCollapseEditor(index) {
        var items = document.querySelectorAll('.supplement-list-item-v2');
        if (!items[index]) return;

        var textarea = items[index].querySelector('.supplement-textarea-v2');
        if (!textarea) return;

        var noteText = textarea.value.trim();
        if (!noteText) {
            alert('Please enter some feedback before saving.');
            return;
        }

        var supplement = currentSurvey.supplements[index];
        if (!supplement) return;

        // Save the note
        saveSupplementNote(supplement.id, noteText, function() {
            // Mark as having notes
            supplement.has_notes = true;

            // Collapse current editor
            collapseSupplementEditor(index);

            // Update status dot
            var statusDot = items[index].querySelector('.supplement-status-dot');
            if (statusDot) {
                statusDot.classList.remove('no-notes');
                statusDot.classList.add('has-notes');
            }

            // Show notification
            showNotification('Feedback saved!', 'success');

            // Auto-expand next supplement
            var nextIndex = index + 1;
            if (nextIndex < currentSurvey.supplements.length) {
                setTimeout(function() {
                    expandSupplementEditor(nextIndex);
                }, 300);
            }
        });
    }

    /**
     * PRODUCT DETAIL VIEW
     * One supplement per screen with autosave
     */
    function showSupplementDetailView(index) {
        currentSupplementIndex = index;
        var supplement = currentSurvey.supplements[index];
        if (!supplement) return;

        var container = document.getElementById('survey-detail-view');
        if (!container) return;

        var html = '';
        html += '<div class="supplement-detail-view">';

        // Header with back button
        html += '<div class="supplement-detail-header">';
        html += '<button class="button back-to-list-btn">← Back to list</button>';
        html += '</div>';

        // Progress bar
        html += '<div class="supplement-progress-bar">';
        for (var i = 0; i < currentSurvey.supplements.length; i++) {
            var hasNotes = currentSurvey.supplements[i].has_notes || false;
            var isActive = i === index;
            var segmentClass = 'progress-segment';
            if (hasNotes) segmentClass += ' completed';
            if (isActive) segmentClass += ' active';
            html += '<div class="' + segmentClass + '"></div>';
        }
        html += '</div>';

        // Supplement name and context
        html += '<div class="supplement-detail-content">';
        html += '<h3 class="supplement-detail-name">' + escapeHtml(supplement.name) + '</h3>';

        if (supplement.admin_context) {
            html += '<div class="supplement-admin-context">';
            html += '<p>' + escapeHtml(supplement.admin_context) + '</p>';
            html += '</div>';
        }

        // Textarea for note
        html += '<div class="supplement-note-editor">';
        html += '<textarea id="supplement-note-textarea" class="supplement-note-textarea" rows="8" ';
        html += 'placeholder="Jūtams efekts? Slikta reakcija? Cik ilgi lieto? Cik daudz?"></textarea>';
        html += '<div class="autosave-indicator" id="autosave-indicator" style="display: none;">';
        html += '<span class="autosave-text">Saved ✓</span>';
        html += '</div>';
        html += '</div>';

        // Show existing notes (read-only)
        if (supplement.notes && supplement.notes.length > 0) {
            html += '<div class="supplement-existing-notes">';
            html += '<h4>Previous notes:</h4>';
            supplement.notes.forEach(function(note) {
                var noteDate = note.created_at ? new Date(note.created_at).toLocaleDateString() : '';
                html += '<div class="existing-note">';
                html += '<div class="existing-note-meta">' + noteDate + '</div>';
                html += '<div class="existing-note-text">' + escapeHtml(note.text).replace(/\n/g, '<br>') + '</div>';
                html += '</div>';
            });
            html += '</div>';
        }

        // Navigation buttons
        html += '<div class="supplement-detail-actions">';
        var hasText = supplement.notes && supplement.notes.length > 0;
        var isLast = index === currentSurvey.supplements.length - 1;

        if (hasText) {
            html += '<button class="button button-primary save-and-next-btn">Save and next</button>';
        } else {
            html += '<button class="button skip-and-next-btn">Skip and next</button>';
        }
        html += '</div>';

        html += '</div>'; // .supplement-detail-content
        html += '</div>'; // .supplement-detail-view

        container.innerHTML = html;

        // Attach event listeners
        attachDetailViewListeners();

        // Focus textarea
        var textarea = document.getElementById('supplement-note-textarea');
        if (textarea) {
            textarea.focus();
        }
    }

    /**
     * Attach event listeners for detail view
     */
    function attachDetailViewListeners() {
        var container = document.getElementById('survey-detail-view');
        if (!container) return;

        // Back to list button
        var backBtn = container.querySelector('.back-to-list-btn');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                clearAutosaveTimer();
                showSupplementListView();
            });
        }

        // Textarea autosave
        var textarea = document.getElementById('supplement-note-textarea');
        if (textarea) {
            // Save on blur
            textarea.addEventListener('blur', function() {
                autosaveNote();
            });

            // Save on typing (debounced)
            textarea.addEventListener('input', function() {
                scheduleAutosave();
                updateNavigationButton();
            });
        }

        // Navigation buttons
        var saveNextBtn = container.querySelector('.save-and-next-btn');
        var skipNextBtn = container.querySelector('.skip-and-next-btn');

        if (saveNextBtn) {
            saveNextBtn.addEventListener('click', saveAndNavigateNext);
        }

        if (skipNextBtn) {
            skipNextBtn.addEventListener('click', skipAndNavigateNext);
        }
    }

    /**
     * Update navigation button based on textarea content
     */
    function updateNavigationButton() {
        var textarea = document.getElementById('supplement-note-textarea');
        var container = document.getElementById('survey-detail-view');
        if (!textarea || !container) return;

        var hasText = textarea.value.trim().length > 0;
        var actions = container.querySelector('.supplement-detail-actions');
        if (!actions) return;

        var isLast = currentSupplementIndex === currentSurvey.supplements.length - 1;

        if (hasText) {
            actions.innerHTML = '<button class="button button-primary save-and-next-btn">' +
                (isLast ? 'Save and finish' : 'Save and next') + '</button>';
            var btn = actions.querySelector('.save-and-next-btn');
            if (btn) btn.addEventListener('click', saveAndNavigateNext);
        } else {
            actions.innerHTML = '<button class="button skip-and-next-btn">' +
                (isLast ? 'Finish' : 'Skip and next') + '</button>';
            var btn = actions.querySelector('.skip-and-next-btn');
            if (btn) btn.addEventListener('click', skipAndNavigateNext);
        }
    }

    /**
     * Schedule autosave (debounced)
     */
    function scheduleAutosave() {
        clearAutosaveTimer();
        autosaveTimer = setTimeout(function() {
            autosaveNote();
        }, 500); // 500ms debounce
    }

    /**
     * Clear autosave timer
     */
    function clearAutosaveTimer() {
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }
    }

    /**
     * Autosave note
     */
    function autosaveNote() {
        clearAutosaveTimer();

        var textarea = document.getElementById('supplement-note-textarea');
        if (!textarea) return;

        var noteText = textarea.value.trim();

        // Don't save if text hasn't changed
        if (noteText === lastSavedText) return;

        // Don't save empty notes via autosave
        if (!noteText) return;

        lastSavedText = noteText;

        var supplement = currentSurvey.supplements[currentSupplementIndex];
        if (!supplement) return;

        saveSupplementNote(supplement.id, noteText, function() {
            showAutosaveIndicator();
            // Update survey data (mark as having notes)
            supplement.has_notes = true;
        });
    }

    /**
     * Show "Saved ✓" indicator briefly
     */
    function showAutosaveIndicator() {
        var indicator = document.getElementById('autosave-indicator');
        if (!indicator) return;

        indicator.style.display = 'block';
        setTimeout(function() {
            indicator.style.display = 'none';
        }, 2000);
    }

    /**
     * Save and navigate to next supplement
     */
    function saveAndNavigateNext() {
        var textarea = document.getElementById('supplement-note-textarea');
        if (!textarea) return;

        var noteText = textarea.value.trim();
        var supplement = currentSurvey.supplements[currentSupplementIndex];
        if (!supplement) return;

        if (noteText) {
            // Save note and navigate
            saveSupplementNote(supplement.id, noteText, function() {
                supplement.has_notes = true;
                navigateNext();
            });
        } else {
            navigateNext();
        }
    }

    /**
     * Skip and navigate to next supplement
     */
    function skipAndNavigateNext() {
        navigateNext();
    }

    /**
     * Navigate to next supplement or return to list
     */
    function navigateNext() {
        clearAutosaveTimer();
        lastSavedText = '';

        if (currentSupplementIndex < currentSurvey.supplements.length - 1) {
            // Go to next supplement
            showSupplementDetailView(currentSupplementIndex + 1);
        } else {
            // Last supplement - return to list view
            showSupplementListView();
        }
    }

    /**
     * Save supplement note via AJAX
     */
    function saveSupplementNote(supplementId, noteText, callback) {
        var telegramId = window.cpGetUserTelegramId ? window.cpGetUserTelegramId() : null;
        if (!telegramId) {
            alert('User not authenticated');
            return;
        }

        ajaxPost({
            action: 'cp_add_supplement_note',
            survey_id: currentSurvey.id,
            supplement_id: supplementId,
            telegram_id: telegramId,
            note_text: noteText,
            note_type: 'note',
            nonce: CONFIG.nonce
        }, function(response) {
            if (callback) callback();
        }, function(error) {
            console.error('Failed to save note:', error);
        });
    }

    /**
     * Submit survey (mark as submitted)
     */
    function submitSupplementSurvey() {
        var telegramId = window.cpGetUserTelegramId ? window.cpGetUserTelegramId() : null;
        if (!telegramId) {
            alert('User not authenticated');
            return;
        }

        if (!confirm('Submit this survey? This will notify the admin.')) {
            return;
        }

        ajaxPost({
            action: 'cp_submit_supplement_survey',
            survey_id: currentSurvey.id,
            telegram_id: telegramId,
            nonce: CONFIG.nonce
        }, function(response) {
            showNotification('Survey submitted successfully!', 'success');
            setTimeout(function() {
                if (window.cpExitSurvey) window.cpExitSurvey();
            }, 1500);
        }, function(error) {
            alert('Failed to submit survey: ' + error);
        });
    }

    /**
     * Show notification message
     */
    function showNotification(message, type) {
        var notification = document.createElement('div');
        notification.className = 'cp-notification cp-notification-' + (type || 'info');
        notification.textContent = message;
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; padding: 15px 20px; background: #4CAF50; color: white; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 10000;';

        document.body.appendChild(notification);

        setTimeout(function() {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s';
            setTimeout(function() {
                if (notification.parentNode) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    /**
     * Helper: AJAX POST request
     */
    function ajaxPost(data, successCallback, errorCallback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', CONFIG.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        successCallback(response);
                    } else {
                        errorCallback(response.data && response.data.message ? response.data.message : 'Request failed');
                    }
                } catch (e) {
                    errorCallback('Invalid response format');
                }
            } else {
                errorCallback('HTTP error: ' + xhr.status);
            }
        };

        xhr.onerror = function() {
            errorCallback('Network error');
        };

        var formData = [];
        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                formData.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
            }
        }

        xhr.send(formData.join('&'));
    }

    /**
     * Helper: Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
