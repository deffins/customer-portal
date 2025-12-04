/**
 * Surveys Module JavaScript
 */

(function() {
    'use strict';

    var CONFIG = window.cpConfig || {};
    var currentSurvey = null;
    var currentQuestionIndex = 0;
    var answers = {};

    /**
     * Display surveys list
     */
    window.cpDisplaySurveys = function(surveys) {
        var container = document.getElementById('surveys-container');
        if (!container) return;

        if (!surveys || surveys.length === 0) {
            container.innerHTML = '<p>No surveys assigned yet.</p>';
            return;
        }

        var html = '<div class="surveys-list">';
        surveys.forEach(function(survey) {
            var statusLabel = survey.status === 'completed' ? 'Completed' : 'Not Started';
            var statusClass = survey.status === 'completed' ? 'completed' : 'pending';
            var lastCompletedText = '';
            if (survey.last_completed_at) {
                var date = new Date(survey.last_completed_at);
                lastCompletedText = '<div class="survey-last-completed">Last completed: ' + date.toLocaleDateString() + '</div>';
            }

            html += '<div class="survey-card">';
            html += '<h4>' + escapeHtml(survey.title) + '</h4>';
            html += '<p class="survey-description">' + escapeHtml(survey.description) + '</p>';
            html += '<div class="survey-status ' + statusClass + '">' + statusLabel + '</div>';
            html += lastCompletedText;
            html += '<button class="button button-primary start-survey-btn" data-survey-id="' + escapeHtml(survey.survey_id) + '">Start Survey</button>';
            html += '</div>';
        });
        html += '</div>';

        container.innerHTML = html;

        // Attach event listeners
        var startButtons = container.querySelectorAll('.start-survey-btn');
        for (var i = 0; i < startButtons.length; i++) {
            startButtons[i].addEventListener('click', function() {
                var surveyId = this.getAttribute('data-survey-id');
                startSurvey(surveyId);
            });
        }
    };

    /**
     * Start a survey
     */
    function startSurvey(surveyId) {
        // Fetch survey definition
        ajaxPost({
            action: 'cp_get_survey_definition',
            survey_id: surveyId,
            nonce: CONFIG.nonce
        }, function(response) {
            currentSurvey = response.data.survey;
            currentQuestionIndex = 0;
            answers = {};
            showSurveyWizard();
        }, function(error) {
            alert('Failed to load survey: ' + error);
        });
    }

    /**
     * Show survey wizard
     */
    function showSurveyWizard() {
        document.getElementById('surveys-container').style.display = 'none';
        var wizard = document.getElementById('survey-wizard');
        wizard.style.display = 'block';
        renderQuestion();
    }

    /**
     * Render current question
     */
    function renderQuestion() {
        if (!currentSurvey || !currentSurvey.questions) return;

        var question = currentSurvey.questions[currentQuestionIndex];
        var wizard = document.getElementById('survey-wizard');

        var totalQuestions = currentSurvey.questions.length;
        var progressPercent = Math.round((currentQuestionIndex / totalQuestions) * 100);

        var html = '';
        html += '<div class="survey-wizard-header">';
        html += '<button id="survey-exit-btn" class="button">← Back to Surveys</button>';
        html += '<h3>' + escapeHtml(currentSurvey.title) + '</h3>';
        html += '</div>';

        html += '<div class="survey-progress">';
        html += '<div class="survey-progress-bar">';
        html += '<div class="survey-progress-fill" style="width: ' + progressPercent + '%;"></div>';
        html += '</div>';
        html += '<span class="survey-progress-text">Question ' + (currentQuestionIndex + 1) + ' of ' + totalQuestions + '</span>';
        html += '</div>';

        html += '<div class="survey-question-container">';
        html += '<label class="survey-question-label">' + escapeHtml(question.label) + '</label>';

        // Render input based on question type
        if (question.type === 'slider') {
            var currentValue = answers[question.id] !== undefined ? answers[question.id] : Math.floor((question.min + question.max) / 2);
            html += '<div class="survey-slider-container">';
            html += '<input type="range" id="survey-input" min="' + question.min + '" max="' + question.max + '" value="' + currentValue + '" class="survey-slider">';
            html += '<div class="survey-slider-labels">';
            html += '<span>' + question.min + '</span>';
            html += '<span id="slider-value" class="slider-current-value">' + currentValue + '</span>';
            html += '<span>' + question.max + '</span>';
            html += '</div>';
            html += '</div>';
        } else if (question.type === 'single_choice') {
            var currentValue = answers[question.id] || '';
            html += '<div class="survey-options">';
            question.options.forEach(function(option) {
                var checked = currentValue === option.value ? 'checked' : '';
                html += '<label class="survey-option">';
                html += '<input type="radio" name="survey-answer" value="' + escapeHtml(option.value) + '" ' + checked + '>';
                html += '<span>' + escapeHtml(option.label) + '</span>';
                html += '</label>';
            });
            html += '</div>';
        } else if (question.type === 'text') {
            var currentValue = answers[question.id] || '';
            html += '<textarea id="survey-input" rows="4" class="survey-textarea" placeholder="Your answer...">' + escapeHtml(currentValue) + '</textarea>';
        }

        html += '</div>';

        html += '<div class="survey-navigation">';
        if (currentQuestionIndex > 0) {
            html += '<button id="survey-prev-btn" class="button">← Previous</button>';
        } else {
            html += '<button class="button" disabled style="visibility:hidden;">← Previous</button>';
        }

        if (currentQuestionIndex < totalQuestions - 1) {
            html += '<button id="survey-next-btn" class="button button-primary">Next →</button>';
        } else {
            html += '<button id="survey-submit-btn" class="button button-primary">Submit Survey</button>';
        }
        html += '</div>';

        wizard.innerHTML = html;

        // Attach event listeners
        attachQuestionEventListeners();
    }

    /**
     * Attach event listeners to wizard
     */
    function attachQuestionEventListeners() {
        var question = currentSurvey.questions[currentQuestionIndex];

        // Slider input
        if (question.type === 'slider') {
            var slider = document.getElementById('survey-input');
            var valueDisplay = document.getElementById('slider-value');
            if (slider && valueDisplay) {
                slider.addEventListener('input', function() {
                    valueDisplay.textContent = this.value;
                });
            }
        }

        // Exit button
        var exitBtn = document.getElementById('survey-exit-btn');
        if (exitBtn) {
            exitBtn.addEventListener('click', exitSurvey);
        }

        // Previous button
        var prevBtn = document.getElementById('survey-prev-btn');
        if (prevBtn) {
            prevBtn.addEventListener('click', previousQuestion);
        }

        // Next button
        var nextBtn = document.getElementById('survey-next-btn');
        if (nextBtn) {
            nextBtn.addEventListener('click', nextQuestion);
        }

        // Submit button
        var submitBtn = document.getElementById('survey-submit-btn');
        if (submitBtn) {
            submitBtn.addEventListener('click', submitSurvey);
        }
    }

    /**
     * Save current answer
     */
    function saveCurrentAnswer() {
        var question = currentSurvey.questions[currentQuestionIndex];
        var value = null;

        if (question.type === 'slider') {
            var slider = document.getElementById('survey-input');
            if (slider) value = parseInt(slider.value);
        } else if (question.type === 'single_choice') {
            var selected = document.querySelector('input[name="survey-answer"]:checked');
            if (selected) value = selected.value;
        } else if (question.type === 'text') {
            var textarea = document.getElementById('survey-input');
            if (textarea) value = textarea.value;
        }

        if (value !== null && value !== '') {
            answers[question.id] = value;
        }
    }

    /**
     * Previous question
     */
    function previousQuestion() {
        saveCurrentAnswer();
        if (currentQuestionIndex > 0) {
            currentQuestionIndex--;
            renderQuestion();
        }
    }

    /**
     * Next question
     */
    function nextQuestion() {
        saveCurrentAnswer();
        if (currentQuestionIndex < currentSurvey.questions.length - 1) {
            currentQuestionIndex++;
            renderQuestion();
        }
    }

    /**
     * Submit survey
     */
    function submitSurvey() {
        saveCurrentAnswer();

        if (!confirm('Submit survey? You can fill it again later if needed.')) {
            return;
        }

        var telegramId = window.cpGetUserTelegramId ? window.cpGetUserTelegramId() : null;
        if (!telegramId) {
            alert('Please log in to submit survey');
            return;
        }

        // Build form data with answers
        var formData = {
            action: 'cp_submit_survey',
            telegram_id: telegramId,
            survey_id: currentSurvey.id,
            nonce: CONFIG.nonce
        };

        // Add answers
        Object.keys(answers).forEach(function(key) {
            formData['answers[' + key + ']'] = answers[key];
        });

        ajaxPost(formData, function(response) {
            alert('Survey submitted successfully! Thank you.');
            exitSurvey();
            // Reload surveys to update status
            var user = window.cpGetUserTelegramId();
            if (user) {
                ajaxPost({
                    action: 'cp_get_assigned_surveys',
                    telegram_id: user,
                    nonce: CONFIG.nonce
                }, function(response) {
                    window.cpDisplaySurveys(response.data.surveys);
                }, function() {});
            }
        }, function(error) {
            alert('Failed to submit survey: ' + error);
        });
    }

    /**
     * Exit survey
     */
    function exitSurvey() {
        document.getElementById('survey-wizard').style.display = 'none';
        document.getElementById('surveys-container').style.display = 'block';
        currentSurvey = null;
        currentQuestionIndex = 0;
        answers = {};
    }

    /**
     * AJAX helper
     */
    function ajaxPost(data, onSuccess, onError) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', CONFIG.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;

            if (xhr.status !== 200) {
                if (onError) onError('Network error');
                return;
            }

            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                if (onError) onError('Invalid response');
                return;
            }

            if (response.success) {
                if (onSuccess) onSuccess(response);
            } else {
                var msg = (response.data && response.data.message) ? response.data.message : 'Unknown error';
                if (onError) onError(msg);
            }
        };

        xhr.onerror = function() {
            if (onError) onError('Connection failed');
        };

        // Build form data
        var formDataPairs = [];
        Object.keys(data).forEach(function(key) {
            formDataPairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
        });

        xhr.send(formDataPairs.join('&'));
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
