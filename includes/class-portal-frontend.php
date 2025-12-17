<?php
/**
 * Frontend - shortcode and scripts
 */

if (!defined('ABSPATH')) exit;

class CP_Frontend {
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_page()) return;
        
        $post = get_post();
        if (!$post || !has_shortcode($post->post_content, 'customer_portal')) return;
        
        // CSS
        wp_enqueue_style(
            'cp-portal',
            CP_PLUGIN_URL . 'assets/css/portal.css',
            array(),
            CP_VERSION
        );

        wp_enqueue_style(
            'cp-calendar',
            CP_PLUGIN_URL . 'assets/css/calendar.css',
            array(),
            CP_VERSION
        );

        // JS
        wp_enqueue_script(
            'cp-portal',
            CP_PLUGIN_URL . 'assets/js/portal.js',
            array(),
            CP_VERSION,
            true
        );

        wp_enqueue_script(
            'cp-calendar',
            CP_PLUGIN_URL . 'assets/js/calendar.js',
            array(),
            CP_VERSION,
            true
        );

        wp_enqueue_style(
            'cp-surveys',
            CP_PLUGIN_URL . 'assets/css/surveys.css',
            array(),
            CP_VERSION
        );

        wp_enqueue_script(
            'cp-surveys',
            CP_PLUGIN_URL . 'assets/js/surveys.js',
            array(),
            CP_VERSION,
            true
        );

        wp_enqueue_script(
            'cp-supplement-feedback',
            CP_PLUGIN_URL . 'assets/js/supplement-feedback.js',
            array(),
            CP_VERSION,
            true
        );

        // Pass data to JS
        wp_localize_script('cp-portal', 'cpConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cp_nonce'),
            'botUsername' => CP()->get_bot_username(),
            'debug' => false
        ));

        // Calendar config for customer view
        wp_localize_script('cp-calendar', 'cpCalendarConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cp_nonce'),
            'isAdmin' => false,
            'isCustomer' => true
        ));
    }
    
    /**
     * Portal shortcode
     */
    public function portal_shortcode() {
        ob_start();
        ?>
        <div id="customer-portal">
            <!-- Debug panel -->
            <div id="cp-debug" style="display:none; background:#ffe0e0; padding:10px; margin-bottom:20px; border-radius:5px; font-family:monospace; font-size:12px;">
                <strong>Debug Log:</strong>
                <div id="cp-debug-log"></div>
            </div>
            
            <div id="login-section">
                <h2>Customer Portal Login</h2>
                <p>Please login with your Telegram account to access your files.</p>
                <div id="telegram-login"></div>
                <p id="login-status" style="color: #666; font-size: 14px; margin-top: 10px;"></p>
            </div>
            
            <div id="portal-section" style="display:none;">
                <div class="portal-header">
                    <h2>Welcome, <span id="user-name"></span>!</h2>
                    <button id="logout-btn" class="button">Logout</button>
                </div>

                <div class="portal-tabs">
                    <button class="tab-button active" data-tab="files">Files</button>
                    <button class="tab-button" data-tab="checklists">Checklists</button>
                    <button class="tab-button" data-tab="links">Links</button>
                    <button class="tab-button" data-tab="calendar">Calendar</button>
                    <button class="tab-button" data-tab="surveys">Surveys</button>
                </div>

                <div id="files-tab" class="tab-content active">
                    <h3>Your Files</h3>
                    <div id="files-container">
                        <p>Loading your files...</p>
                    </div>
                </div>

                <div id="checklists-tab" class="tab-content">
                    <h3>Your Checklists</h3>
                    <div id="checklists-container">
                        <p>Loading your checklists...</p>
                    </div>
                </div>

                <div id="links-tab" class="tab-content">
                    <h3>Useful Links</h3>
                    <div id="links-container">
                        <p>Loading your links...</p>
                    </div>
                </div>

                <div id="calendar-tab" class="tab-content">
                    <h3>Book an Appointment</h3>

                    <!-- My Appointments Section -->
                    <div id="my-appointments-section" style="margin-bottom: 30px;">
                        <h4 style="color: #3B4F3D; margin-bottom: 15px;">My Upcoming Appointments</h4>
                        <div id="my-appointments-list">
                            <p style="color: #999; font-style: italic;">Loading...</p>
                        </div>
                    </div>

                    <div class="calendar-legend">
                        <span class="legend-item"><span class="dot dot-free"></span> Available</span>
                        <span class="legend-item"><span class="dot dot-booked-me"></span> Your Booking</span>
                        <span class="legend-item"><span class="dot dot-booked-other"></span> Unavailable</span>
                        <span class="legend-item"><span class="dot dot-blocked"></span> Not Available</span>
                    </div>
                    <div id="bc-calendar-container">
                        <p>Loading calendar...</p>
                    </div>
                </div>

                <div id="surveys-tab" class="tab-content">
                    <div id="surveys-list-view">
                        <h3>Surveys</h3>
                        <div id="surveys-container">
                            <p>Loading surveys...</p>
                        </div>
                    </div>
                    <div id="survey-detail-view" style="display:none;">
                        <!-- Survey wizard or supplement feedback content will be inserted here -->
                    </div>
                </div>
            </div>

            <!-- Booking Modal -->
            <div id="booking-modal" class="cp-modal" style="display: none;">
                <div class="cp-modal-overlay"></div>
                <div class="cp-modal-content">
                    <h4 id="modal-title"></h4>
                    <p id="modal-message"></p>
                    <div class="cp-modal-actions">
                        <button id="modal-cancel" class="button">Cancel</button>
                        <button id="modal-confirm" class="button button-primary">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Optional Email Modal -->
            <div id="email-modal" class="cp-modal" style="display: none;">
                <div class="cp-modal-overlay"></div>
                <div class="cp-modal-content">
                    <h4 style="margin-bottom: 10px;">Add Your Email (Optional)</h4>
                    <p style="margin-bottom: 10px; color: #555;">Share an email to get a calendar invite with a Meet link, or skip to book without email.</p>
                    <input id="email-input" type="email" placeholder="your@email.com" style="width: 100%; padding: 10px; margin-bottom: 15px;">

                    <h4 style="margin-bottom: 10px;">Comments or Questions (Optional)</h4>
                    <textarea id="booking-notes" placeholder="Add any comments or questions about the appointment..." style="width: 100%; padding: 10px; margin-bottom: 15px; min-height: 80px; font-family: inherit; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>

                    <div class="cp-modal-actions" style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button id="email-skip" class="button">Skip, Book Without Email</button>
                        <button id="email-save" class="button button-primary">Save Email &amp; Confirm Booking</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
