/**
 * Supplement Feedback Module JavaScript
 */

(function() {
    'use strict';

    var CONFIG = window.cpConfig || {};
    var currentUser = null;

    /**
     * Initialize supplement feedback
     */
    window.cpInitSupplementFeedback = function(user) {
        currentUser = user;
        // No longer auto-loading separate section
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
        loadAndDisplaySupplementSurvey(surveyId);
    };

    /**
     * Load and display a specific supplement survey
     */
    function loadAndDisplaySupplementSurvey(surveyId) {
        if (!currentUser) {
            alert('Please log in first');
            return;
        }

        ajaxPost({
            action: 'cp_get_supplement_survey',
            survey_id: surveyId,
            nonce: CONFIG.nonce
        }, function(response) {
            var survey = response.data.survey;
            loadUserCommentsAndRender(survey);
        }, function(error) {
            alert('Failed to load survey: ' + error);
            if (window.cpExitSurvey) window.cpExitSurvey();
        });
    }

    /**
     * Load supplement surveys assigned to user
     */
    function loadSupplementSurveys() {
        var container = document.getElementById('supplement-surveys-container');
        if (!container) return;

        if (!currentUser) {
            container.innerHTML = '<p>Please log in to view supplement surveys.</p>';
            return;
        }

        container.innerHTML = '<p>Loading supplement surveys...</p>';

        // Get assignments that match supplement_feedback type
        ajaxPost({
            action: 'cp_get_assigned_surveys',
            telegram_id: currentUser.id,
            nonce: CONFIG.nonce
        }, function(response) {
            // Filter for supplement_feedback type surveys and get survey details
            var supplementSurveys = [];
            if (response.data && response.data.surveys) {
                response.data.surveys.forEach(function(assignment) {
                    // Check if this is a supplement feedback survey (survey_id will be like "supplement_1")
                    if (assignment.survey_id && assignment.survey_id.indexOf('supplement_') === 0) {
                        var surveyId = parseInt(assignment.survey_id.replace('supplement_', ''));
                        if (!isNaN(surveyId)) {
                            supplementSurveys.push({
                                id: surveyId,
                                title: assignment.title || 'Supplement Survey',
                                status: assignment.status || 'Not Started'
                            });
                        }
                    }
                });
            }

            if (supplementSurveys.length > 0) {
                displaySupplementSurveysList(supplementSurveys);
            } else {
                // Hide the entire supplement surveys section if no surveys are assigned
                var section = document.getElementById('supplement-surveys-section');
                if (section) {
                    section.style.display = 'none';
                }
            }
        }, function(error) {
            console.error('Failed to load assignments:', error);
            container.innerHTML = '<p>Error loading supplement surveys.</p>';
        });
    }

    /**
     * Display list of supplement surveys as clickable cards
     */
    function displaySupplementSurveysList(surveys) {
        var container = document.getElementById('supplement-surveys-container');
        if (!container) return;

        var html = '<div class="supplement-surveys-list">';
        surveys.forEach(function(survey) {
            var statusLabel = survey.status === 'completed' ? 'Completed' : 'Not Started';
            var statusClass = survey.status === 'completed' ? 'completed' : 'pending';

            html += '<div class="survey-card supplement-survey-card" data-survey-id="' + survey.id + '">';
            html += '<h4>' + escapeHtml(survey.title) + '</h4>';
            html += '<div class="survey-status ' + statusClass + '">' + statusLabel + '</div>';
            html += '<button class="button button-primary start-supplement-survey-btn" data-survey-id="' + survey.id + '">Start Survey</button>';
            html += '</div>';
        });
        html += '</div>';

        // Add hidden container for the survey form
        html += '<div id="supplement-survey-form-container" style="display: none;"></div>';

        container.innerHTML = html;

        // Attach event listeners to survey buttons
        var startButtons = container.querySelectorAll('.start-supplement-survey-btn');
        for (var i = 0; i < startButtons.length; i++) {
            startButtons[i].addEventListener('click', function() {
                var surveyId = parseInt(this.getAttribute('data-survey-id'));
                showSupplementSurveyForm(surveyId);
            });
        }
    }

    /**
     * Show the supplement survey feedback form
     */
    function showSupplementSurveyForm(surveyId) {
        // Hide the surveys list
        var listContainer = document.querySelector('.supplement-surveys-list');
        if (listContainer) {
            listContainer.style.display = 'none';
        }

        // Show the form container
        var formContainer = document.getElementById('supplement-survey-form-container');
        if (formContainer) {
            formContainer.style.display = 'block';
        }

        // Load the survey
        cpDisplaySupplementSurvey(surveyId);
    }

    /**
     * Display a supplement feedback survey
     */
    window.cpDisplaySupplementSurvey = function(surveyId) {
        ajaxPost({
            action: 'cp_get_supplement_survey',
            survey_id: surveyId,
            nonce: CONFIG.nonce
        }, function(response) {
            var survey = response.data.survey;
            loadUserComments(survey);
        }, function(error) {
            alert('Failed to load survey: ' + error);
        });
    };

    /**
     * Load user's existing comments and render the survey form
     */
    function loadUserCommentsAndRender(survey) {
        if (!currentUser) {
            console.error('No current user set');
            return;
        }

        ajaxPost({
            action: 'cp_get_user_supplement_comments',
            survey_id: survey.id,
            user_id: currentUser.id,
            nonce: CONFIG.nonce
        }, function(response) {
            var comments = response.data.comments;
            renderSupplementSurvey(survey, comments);
        }, function(error) {
            console.error('Failed to load comments:', error);
            renderSupplementSurvey(survey, {});
        });
    }

    /**
     * Render supplement survey with all supplements
     */
    function renderSupplementSurvey(survey, existingComments) {
        var container = document.getElementById('survey-detail-view');
        if (!container) return;

        var html = '';
        html += '<div class="supplement-survey">';

        // Add back button
        html += '<button class="button back-to-surveys-btn" style="margin-bottom: 20px;">← Back to Surveys</button>';

        html += '<h3>' + escapeHtml(survey.title) + '</h3>';
        html += '<p class="supplement-survey-description">Add your feedback for each supplement:</p>';

        html += '<div class="supplements-list">';

        if (!survey.supplements || survey.supplements.length === 0) {
            html += '<p>No supplements in this survey.</p>';
        } else {
            survey.supplements.forEach(function(supplement) {
                var hasComment = existingComments.hasOwnProperty(supplement.id);
                var commentText = hasComment ? existingComments[supplement.id] : '';

                html += '<div class="supplement-item" data-supplement-id="' + supplement.id + '">';
                html += '<div class="supplement-header">';
                html += '<strong class="supplement-name">' + escapeHtml(supplement.name) + '</strong>';

                if (hasComment) {
                    html += '<button class="button button-small edit-comment-btn" data-supplement-id="' + supplement.id + '">Edit comment</button>';
                } else {
                    html += '<button class="button button-small button-primary add-comment-btn" data-supplement-id="' + supplement.id + '">Add comment</button>';
                }
                html += '</div>';

                // Show existing comment if present
                if (hasComment) {
                    html += '<div class="supplement-comment-display" id="comment-display-' + supplement.id + '">';
                    html += '<p>' + escapeHtml(commentText).replace(/\n/g, '<br>') + '</p>';
                    html += '</div>';
                }

                // Comment editor (hidden by default)
                html += '<div class="supplement-comment-editor" id="comment-editor-' + supplement.id + '" style="display: none;">';
                html += '<textarea class="supplement-comment-textarea" rows="4" placeholder="Enter your feedback...">' + escapeHtml(commentText) + '</textarea>';
                html += '<div class="comment-actions">';
                html += '<button class="button button-primary save-comment-btn" data-supplement-id="' + supplement.id + '" data-survey-id="' + survey.id + '">Save</button>';
                html += '<button class="button cancel-comment-btn" data-supplement-id="' + supplement.id + '">Cancel</button>';
                html += '</div>';
                html += '</div>';

                html += '</div>'; // .supplement-item
            });
        }

        html += '</div>'; // .supplements-list
        html += '</div>'; // .supplement-survey

        container.innerHTML = html;

        // Attach event listeners
        attachSupplementEventListeners(survey);

        // Attach back button listener
        var backBtn = container.querySelector('.back-to-surveys-btn');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                if (window.cpExitSurvey) {
                    window.cpExitSurvey();
                }
            });
        }
    }

    /**
     * Hide the supplement survey form and show the surveys list
     */
    function hideSupplementSurveyForm() {
        // Hide the form container
        var formContainer = document.getElementById('supplement-survey-form-container');
        if (formContainer) {
            formContainer.style.display = 'none';
        }

        // Show the surveys list
        var listContainer = document.querySelector('.supplement-surveys-list');
        if (listContainer) {
            listContainer.style.display = 'block';
        }
    }

    /**
     * Attach event listeners to supplement buttons
     */
    function attachSupplementEventListeners(survey) {
        var container = document.getElementById('supplement-surveys-container');

        // Add comment buttons
        var addButtons = container.querySelectorAll('.add-comment-btn');
        for (var i = 0; i < addButtons.length; i++) {
            addButtons[i].addEventListener('click', function() {
                var supplementId = this.getAttribute('data-supplement-id');
                showCommentEditor(supplementId);
            });
        }

        // Edit comment buttons
        var editButtons = container.querySelectorAll('.edit-comment-btn');
        for (var i = 0; i < editButtons.length; i++) {
            editButtons[i].addEventListener('click', function() {
                var supplementId = this.getAttribute('data-supplement-id');
                showCommentEditor(supplementId);
            });
        }

        // Save comment buttons
        var saveButtons = container.querySelectorAll('.save-comment-btn');
        for (var i = 0; i < saveButtons.length; i++) {
            saveButtons[i].addEventListener('click', function() {
                var supplementId = this.getAttribute('data-supplement-id');
                var surveyId = this.getAttribute('data-survey-id');
                saveComment(surveyId, supplementId);
            });
        }

        // Cancel buttons
        var cancelButtons = container.querySelectorAll('.cancel-comment-btn');
        for (var i = 0; i < cancelButtons.length; i++) {
            cancelButtons[i].addEventListener('click', function() {
                var supplementId = this.getAttribute('data-supplement-id');
                hideCommentEditor(supplementId);
            });
        }
    }

    /**
     * Show comment editor for a supplement
     */
    function showCommentEditor(supplementId) {
        var editor = document.getElementById('comment-editor-' + supplementId);
        var display = document.getElementById('comment-display-' + supplementId);

        if (editor) {
            editor.style.display = 'block';
        }
        if (display) {
            display.style.display = 'none';
        }

        // Focus on textarea
        var textarea = editor ? editor.querySelector('textarea') : null;
        if (textarea) {
            textarea.focus();
        }
    }

    /**
     * Hide comment editor for a supplement
     */
    function hideCommentEditor(supplementId) {
        var editor = document.getElementById('comment-editor-' + supplementId);
        var display = document.getElementById('comment-display-' + supplementId);

        if (editor) {
            editor.style.display = 'none';
        }
        if (display) {
            display.style.display = 'block';
        }
    }

    /**
     * Save comment for a supplement
     */
    function saveComment(surveyId, supplementId) {
        if (!currentUser) {
            alert('User not authenticated');
            return;
        }

        var editor = document.getElementById('comment-editor-' + supplementId);
        if (!editor) return;

        var textarea = editor.querySelector('textarea');
        if (!textarea) return;

        var commentText = textarea.value.trim();

        if (!commentText) {
            alert('Please enter a comment');
            return;
        }

        // Show loading state
        var saveBtn = editor.querySelector('.save-comment-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        ajaxPost({
            action: 'cp_save_supplement_comment',
            survey_id: surveyId,
            supplement_id: supplementId,
            user_id: currentUser.id,
            comment_text: commentText,
            nonce: CONFIG.nonce
        }, function(response) {
            // Update UI to show saved comment
            updateCommentDisplay(supplementId, commentText);
            hideCommentEditor(supplementId);

            // Show success message
            showNotification('Comment saved successfully!', 'success');

            // Reset button
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            }
        }, function(error) {
            alert('Failed to save comment: ' + error);
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            }
        });
    }

    /**
     * Update comment display after saving
     */
    function updateCommentDisplay(supplementId, commentText) {
        var item = document.querySelector('.supplement-item[data-supplement-id="' + supplementId + '"]');
        if (!item) return;

        var header = item.querySelector('.supplement-header');
        var display = document.getElementById('comment-display-' + supplementId);

        // Update button from "Add" to "Edit"
        var addBtn = header.querySelector('.add-comment-btn');
        if (addBtn) {
            addBtn.className = 'button button-small edit-comment-btn';
            addBtn.textContent = 'Edit comment';
            addBtn.setAttribute('data-supplement-id', supplementId);
        }

        // Update or create comment display
        if (!display) {
            display = document.createElement('div');
            display.className = 'supplement-comment-display';
            display.id = 'comment-display-' + supplementId;
            var editor = document.getElementById('comment-editor-' + supplementId);
            item.insertBefore(display, editor);
        }

        display.innerHTML = '<p>' + escapeHtml(commentText).replace(/\n/g, '<br>') + '</p>';
        display.style.display = 'block';
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
                document.body.removeChild(notification);
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
