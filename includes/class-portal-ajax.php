<?php
/**
 * AJAX handlers
 */

if (!defined('ABSPATH')) exit;

class CP_Ajax {
    
    public function __construct() {
        // Auth
        add_action('wp_ajax_verify_telegram_auth', array($this, 'verify_telegram_auth'));
        add_action('wp_ajax_nopriv_verify_telegram_auth', array($this, 'verify_telegram_auth'));
        
        // Files
        add_action('wp_ajax_get_customer_files', array($this, 'get_customer_files'));
        add_action('wp_ajax_nopriv_get_customer_files', array($this, 'get_customer_files'));
        
        // Checklists
        add_action('wp_ajax_get_customer_checklists', array($this, 'get_customer_checklists'));
        add_action('wp_ajax_nopriv_get_customer_checklists', array($this, 'get_customer_checklists'));
        add_action('wp_ajax_toggle_checklist_item', array($this, 'toggle_checklist_item'));
        add_action('wp_ajax_nopriv_toggle_checklist_item', array($this, 'toggle_checklist_item'));
        add_action('wp_ajax_delete_checklist', array($this, 'delete_checklist'));
        add_action('wp_ajax_nopriv_delete_checklist', array($this, 'delete_checklist'));
        
        // Links
        add_action('wp_ajax_get_customer_links', array($this, 'get_customer_links'));
        add_action('wp_ajax_nopriv_get_customer_links', array($this, 'get_customer_links'));

        // Calendar
        add_action('wp_ajax_cp_get_calendar_slots', array($this, 'get_calendar_slots'));
        add_action('wp_ajax_nopriv_cp_get_calendar_slots', array($this, 'get_calendar_slots'));
        add_action('wp_ajax_cp_book_slot', array($this, 'book_slot'));
        add_action('wp_ajax_nopriv_cp_book_slot', array($this, 'book_slot'));
        add_action('wp_ajax_cp_cancel_booking', array($this, 'cancel_booking'));
        add_action('wp_ajax_nopriv_cp_cancel_booking', array($this, 'cancel_booking'));
        add_action('wp_ajax_cp_admin_cancel_booking', array($this, 'admin_cancel_booking'));
        add_action('wp_ajax_cp_toggle_slot_availability', array($this, 'toggle_slot_availability'));
        add_action('wp_ajax_cp_get_my_appointments', array($this, 'get_my_appointments'));
        add_action('wp_ajax_nopriv_cp_get_my_appointments', array($this, 'get_my_appointments'));
        add_action('wp_ajax_cp_get_user_profile', array($this, 'get_user_profile'));
        add_action('wp_ajax_nopriv_cp_get_user_profile', array($this, 'get_user_profile'));

        // Surveys
        add_action('wp_ajax_cp_get_assigned_surveys', array($this, 'get_assigned_surveys'));
        add_action('wp_ajax_nopriv_cp_get_assigned_surveys', array($this, 'get_assigned_surveys'));
        add_action('wp_ajax_cp_get_survey_definition', array($this, 'get_survey_definition'));
        add_action('wp_ajax_nopriv_cp_get_survey_definition', array($this, 'get_survey_definition'));
        add_action('wp_ajax_cp_submit_survey', array($this, 'submit_survey'));
        add_action('wp_ajax_nopriv_cp_submit_survey', array($this, 'submit_survey'));

        // Supplement Feedback
        add_action('wp_ajax_cp_get_supplement_survey', array($this, 'get_supplement_survey'));
        add_action('wp_ajax_nopriv_cp_get_supplement_survey', array($this, 'get_supplement_survey'));
        add_action('wp_ajax_cp_save_supplement_comment', array($this, 'save_supplement_comment'));
        add_action('wp_ajax_nopriv_cp_save_supplement_comment', array($this, 'save_supplement_comment'));
        add_action('wp_ajax_cp_get_user_supplement_comments', array($this, 'get_user_supplement_comments'));
        add_action('wp_ajax_nopriv_cp_get_user_supplement_comments', array($this, 'get_user_supplement_comments'));
        add_action('wp_ajax_cp_delete_supplement_comment', array($this, 'delete_supplement_comment'));
        add_action('wp_ajax_nopriv_cp_delete_supplement_comment', array($this, 'delete_supplement_comment'));
    }
    
    /**
     * Verify Telegram authentication
     */
    public function verify_telegram_auth() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!isset($_POST['user']) || !is_array($_POST['user'])) {
            wp_send_json_error(array('message' => 'User data missing'));
            return;
        }
        
        $user_data = array_map('sanitize_text_field', $_POST['user']);
        $bot_token = CP()->get_bot_token();
        
        if (!isset($user_data['hash'])) {
            wp_send_json_error(array('message' => 'Invalid authentication data'));
            return;
        }
        
        // Verify hash
        $check_hash = $user_data['hash'];
        unset($user_data['hash']);
        
        $data_check_arr = array();
        foreach ($user_data as $key => $value) {
            if ($value !== '') {
                $data_check_arr[] = $key . '=' . $value;
            }
        }
        sort($data_check_arr);
        
        $data_check_string = implode("\n", $data_check_arr);
        $secret_key = hash('sha256', $bot_token, true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);
        
        if (!hash_equals($hash, $check_hash)) {
            wp_send_json_error(array('message' => 'Invalid authentication'));
            return;
        }
        
        // Check if auth is recent
        if (!isset($user_data['auth_date']) || (time() - intval($user_data['auth_date'])) > 86400) {
            wp_send_json_error(array('message' => 'Authentication expired'));
            return;
        }
        
        // Save user
        $telegram_id = intval($user_data['id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_users';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE telegram_id = %d",
            $telegram_id
        ));
        
        if ($existing) {
            if (!$existing->is_active) {
                wp_send_json_error(array('message' => 'Account disabled'));
                return;
            }
            // Update
            $wpdb->update($table, array(
                'first_name' => $user_data['first_name'] ?? '',
                'last_name' => $user_data['last_name'] ?? '',
                'username' => $user_data['username'] ?? ''
            ), array('telegram_id' => $telegram_id));
        } else {
            // Insert
            $wpdb->insert($table, array(
                'telegram_id' => $telegram_id,
                'first_name' => $user_data['first_name'] ?? '',
                'last_name' => $user_data['last_name'] ?? '',
                'username' => $user_data['username'] ?? ''
            ));
        }
        
        wp_send_json_success(array('message' => 'Authenticated successfully'));
    }
    
    /**
     * Get customer files from Google Drive
     */
    public function get_customer_files() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!isset($_POST['telegram_id'])) {
            wp_send_json_error(array('message' => 'Telegram ID missing'));
            return;
        }
        
        $telegram_id = intval($_POST['telegram_id']);
        $user = CP()->database->get_user_by_telegram_id($telegram_id);
        
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }
        
        if (!$user->drive_folder_id) {
            wp_send_json_success(array('files' => array()));
            return;
        }
        
        $files = $this->get_drive_files($user->drive_folder_id);
        wp_send_json_success(array('files' => $files));
    }
    
    /**
     * Get files from Google Drive
     */
    private function get_drive_files($folder_id) {
        $client_id = get_option('cp_google_client_id');
        $client_secret = get_option('cp_google_client_secret');
        $refresh_token = get_option('cp_google_refresh_token');
        
        if (!$client_id || !$client_secret || !$refresh_token) {
            return array();
        }
        
        // Get access token
        $token_response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            )
        ));
        
        if (is_wp_error($token_response)) {
            return array();
        }
        
        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        
        if (!isset($token_data['access_token'])) {
            return array();
        }
        
        // Get files
        $files_response = wp_remote_get(
            'https://www.googleapis.com/drive/v3/files?q=' . urlencode("'{$folder_id}' in parents and trashed=false") . '&fields=files(id,name,mimeType,webViewLink,webContentLink)',
            array('headers' => array('Authorization' => 'Bearer ' . $token_data['access_token']))
        );
        
        if (is_wp_error($files_response)) {
            return array();
        }
        
        $files_data = json_decode(wp_remote_retrieve_body($files_response), true);
        
        return isset($files_data['files']) ? $files_data['files'] : array();
    }
    
    /**
     * Get customer checklists
     */
    public function get_customer_checklists() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!isset($_POST['telegram_id'])) {
            wp_send_json_error(array('message' => 'Telegram ID missing'));
            return;
        }
        
        $telegram_id = intval($_POST['telegram_id']);
        $user = CP()->database->get_user_by_telegram_id($telegram_id);
        
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }
        
        $checklists = CP()->database->get_user_checklists($user->id);
        wp_send_json_success(array('checklists' => $checklists));
    }
    
    /**
     * Toggle checklist item
     */
    public function toggle_checklist_item() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!isset($_POST['item_id']) || !isset($_POST['is_checked'])) {
            wp_send_json_error(array('message' => 'Missing parameters'));
            return;
        }
        
        $item_id = intval($_POST['item_id']);
        $is_checked = intval($_POST['is_checked']);
        
        CP()->database->toggle_checklist_item($item_id, $is_checked);
        wp_send_json_success(array('message' => 'Updated'));
    }
    
    /**
     * Delete (archive) checklist
     */
    public function delete_checklist() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!isset($_POST['checklist_id'])) {
            wp_send_json_error(array('message' => 'Checklist ID missing'));
            return;
        }
        
        $checklist_id = intval($_POST['checklist_id']);
        CP()->database->archive_checklist($checklist_id);
        wp_send_json_success(array('message' => 'Archived'));
    }
    
    /**
     * Get customer links
     */
    public function get_customer_links() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['telegram_id'])) {
            wp_send_json_error(array('message' => 'Telegram ID missing'));
            return;
        }

        $telegram_id = intval($_POST['telegram_id']);
        $user = CP()->database->get_user_by_telegram_id($telegram_id);

        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }

        $links = CP()->database->get_user_links($user->id);
        wp_send_json_success(array('links' => $links));
    }

    /**
     * CALENDAR AJAX HANDLERS
     */

    /**
     * Get calendar slots
     */
    public function get_calendar_slots() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['start_date']) || !isset($_POST['end_date'])) {
            wp_send_json_error(array('message' => 'Date range missing'));
            return;
        }

        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            wp_send_json_error(array('message' => 'Invalid date format'));
            return;
        }

        $user_id = null;
        if (isset($_POST['telegram_id'])) {
            $telegram_id = intval($_POST['telegram_id']);
            $user = CP()->database->get_user_by_telegram_id($telegram_id);
            if ($user) {
                $user_id = $user->id;
            }
        }

        $slots = CP()->database->get_calendar_slots($start_date, $end_date, $user_id);

        // Format for frontend
        $formatted_slots = array();
        foreach ($slots as $slot) {
            $formatted_slots[] = array(
                'slot_date' => $slot->slot_date,
                'slot_hour' => intval($slot->slot_hour),
                'status' => $slot->status,
                'booked_by' => $slot->booked_by ? intval($slot->booked_by) : null,
                'booked_at' => $slot->booked_at,
                'customer_name' => $slot->booked_by ? trim($slot->first_name . ' ' . $slot->last_name) : null,
                'is_mine' => isset($slot->is_mine) ? (bool)$slot->is_mine : false
            );
        }

        wp_send_json_success(array('slots' => $formatted_slots));
    }

    /**
     * Book a slot
     */
    public function book_slot() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['telegram_id']) || !isset($_POST['date']) || !isset($_POST['hour'])) {
            wp_send_json_error(array('message' => 'Missing parameters'));
            return;
        }

        $telegram_id = intval($_POST['telegram_id']);
        $date = sanitize_text_field($_POST['date']);
        $hour = intval($_POST['hour']);
        $client_email = isset($_POST['client_email']) ? sanitize_text_field($_POST['client_email']) : '';
        $booking_notes = isset($_POST['booking_notes']) ? sanitize_textarea_field($_POST['booking_notes']) : '';

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(array('message' => 'Invalid date format'));
            return;
        }

        // Validate hour range
        if ($hour < 8 || $hour > 20) {
            wp_send_json_error(array('message' => 'Invalid hour'));
            return;
        }

        // Validate optional email
        if (!empty($client_email) && !is_email($client_email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
            return;
        }

        // Get user
        $user = CP()->database->get_user_by_telegram_id($telegram_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }

        // Book slot
        $result = CP()->database->book_slot($date, $hour, $user->id);

        if ($result['success']) {
            $response = array(
                'message' => $result['message'],
                'slot' => array('slot_date' => $date, 'slot_hour' => $hour)
            );

            // Create Google Calendar event if email provided and Google is configured
            if (!empty($client_email)) {
                // Save email on the user record for admin visibility
                CP()->database->update_user_email($user->id, $client_email);

                $event_result = $this->create_google_event($date, $hour, $client_email, $user, $booking_notes);
                if ($event_result['success']) {
                    $response['google_event_id'] = $event_result['event_id'];
                    if (!empty($event_result['meet_link'])) {
                        $response['meet_link'] = $event_result['meet_link'];
                    }
                } else {
                    // Do not fail booking if calendar fails; surface message and log for admin
                    $response['google_calendar_warning'] = $event_result['message'];
                    error_log('[Customer Portal] Google Calendar create_event failed: ' . $event_result['message']);
                }
            }

            wp_send_json_success($response);
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Cancel booking (customer)
     */
    public function cancel_booking() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['telegram_id']) || !isset($_POST['date']) || !isset($_POST['hour'])) {
            wp_send_json_error(array('message' => 'Missing parameters'));
            return;
        }

        $telegram_id = intval($_POST['telegram_id']);
        $date = sanitize_text_field($_POST['date']);
        $hour = intval($_POST['hour']);

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(array('message' => 'Invalid date format'));
            return;
        }

        // Get user
        $user = CP()->database->get_user_by_telegram_id($telegram_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }

        // Cancel booking
        $result = CP()->database->cancel_booking($date, $hour, $user->id);

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Admin cancel booking
     */
    public function admin_cancel_booking() {
        check_ajax_referer('cp_nonce', 'nonce');

        // Check admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        if (!isset($_POST['date']) || !isset($_POST['hour'])) {
            wp_send_json_error(array('message' => 'Missing parameters'));
            return;
        }

        $date = sanitize_text_field($_POST['date']);
        $hour = intval($_POST['hour']);

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(array('message' => 'Invalid date format'));
            return;
        }

        // Cancel booking
        $result = CP()->database->admin_cancel_booking($date, $hour);

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Toggle slot availability (admin only)
     */
    public function toggle_slot_availability() {
        check_ajax_referer('cp_nonce', 'nonce');

        // Check admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        if (!isset($_POST['date']) || !isset($_POST['hour'])) {
            wp_send_json_error(array('message' => 'Missing parameters'));
            return;
        }

        $date = sanitize_text_field($_POST['date']);
        $hour = intval($_POST['hour']);

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(array('message' => 'Invalid date format'));
            return;
        }

        // Validate hour range
        if ($hour < 8 || $hour > 20) {
            wp_send_json_error(array('message' => 'Invalid hour'));
            return;
        }

        // Toggle slot
        $result = CP()->database->toggle_slot_availability($date, $hour);

        if ($result['success']) {
            wp_send_json_success(array(
                'status' => $result['status'],
                'date' => $date,
                'hour' => $hour
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * SURVEY AJAX HANDLERS
     */

    /**
     * Get assigned surveys for a user
     */
    public function get_assigned_surveys() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['telegram_id'])) {
            wp_send_json_error(array('message' => 'Telegram ID missing'));
            return;
        }

        $telegram_id = intval($_POST['telegram_id']);
        $user = CP()->database->get_user_by_telegram_id($telegram_id);

        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }

        $assignments = CP()->database->get_user_assigned_surveys($user->id);
        $surveys_module = CP()->surveys;
        $available_surveys = $surveys_module->get_available_surveys();

        // Add supplement surveys from database
        $supplement_surveys = CP()->database->get_supplement_surveys();
        foreach ($supplement_surveys as $survey) {
            $available_surveys['supplement_' . $survey->id] = array(
                'id' => 'supplement_' . $survey->id,
                'title' => $survey->title,
                'description' => '',
                'type' => 'supplement_feedback'
            );
        }

        // Format assignments for frontend
        $formatted_assignments = array();
        foreach ($assignments as $assignment) {
            $survey_info = isset($available_surveys[$assignment->survey_id]) ? $available_surveys[$assignment->survey_id] : null;

            $status = 'not_started';
            if ($assignment->completion_count > 0) {
                $status = 'completed';
            }

            $formatted_assignments[] = array(
                'assignment_id' => $assignment->id,
                'survey_id' => $assignment->survey_id,
                'title' => $survey_info ? $survey_info['title'] : $assignment->survey_id,
                'description' => $survey_info ? $survey_info['description'] : '',
                'status' => $status,
                'completion_count' => intval($assignment->completion_count),
                'last_completed_at' => $assignment->last_completed_at
            );
        }

        wp_send_json_success(array('surveys' => $formatted_assignments));
    }

    /**
     * Get survey definition
     */
    public function get_survey_definition() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['survey_id'])) {
            wp_send_json_error(array('message' => 'Survey ID missing'));
            return;
        }

        $survey_id = sanitize_text_field($_POST['survey_id']);
        $surveys_module = CP()->surveys;
        $survey = $surveys_module->get_survey_definition($survey_id);

        if (!$survey) {
            wp_send_json_error(array('message' => 'Survey not found'));
            return;
        }

        wp_send_json_success(array('survey' => $survey));
    }

    /**
     * Submit survey answers
     */
    public function submit_survey() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['telegram_id']) || !isset($_POST['survey_id']) || !isset($_POST['answers'])) {
            wp_send_json_error(array('message' => 'Missing parameters'));
            return;
        }

        $telegram_id = intval($_POST['telegram_id']);
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $answers = $_POST['answers']; // Already an array from JSON

        // Get user
        $user = CP()->database->get_user_by_telegram_id($telegram_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }

        // Verify user has this survey assigned
        $assignments = CP()->database->get_user_assigned_surveys($user->id);
        $has_assignment = false;
        foreach ($assignments as $assignment) {
            if ($assignment->survey_id === $survey_id) {
                $has_assignment = true;
                break;
            }
        }

        if (!$has_assignment) {
            wp_send_json_error(array('message' => 'Survey not assigned to this user'));
            return;
        }

        // Calculate scores
        $surveys_module = CP()->surveys;
        $scores = $surveys_module->calculate_scores($survey_id, $answers);

        // Save result
        $result_id = CP()->database->save_survey_result(
            $user->id,
            $survey_id,
            $answers,
            $scores['total_score'],
            $scores['dimension_scores']
        );

        if ($result_id) {
            wp_send_json_success(array(
                'message' => 'Survey submitted successfully',
                'result_id' => $result_id,
                'total_score' => $scores['total_score']
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save survey result'));
        }
    }

    /**
     * Get user's upcoming appointments
     */
    public function get_my_appointments() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['telegram_id'])) {
            wp_send_json_error(array('message' => 'Telegram ID missing'));
            return;
        }

        $telegram_id = intval($_POST['telegram_id']);
        $user = CP()->database->get_user_by_telegram_id($telegram_id);

        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }

        // Get user's booked slots starting from today
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';
        $today = date('Y-m-d');

        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT slot_date, slot_hour
             FROM {$table}
             WHERE booked_by = %d
             AND status = 'booked'
             AND slot_date >= %s
             ORDER BY slot_date ASC, slot_hour ASC
             LIMIT 10",
            $user->id,
            $today
        ));

        wp_send_json_success(array('appointments' => $appointments));
    }

    /**
     * Create Google Calendar event with Meet link for a booking
     */
    private function create_google_event($date, $hour, $client_email, $user, $booking_notes = '') {
        // Ensure Google credentials exist
        $token_info = $this->get_google_access_token();
        if (empty($token_info['token'])) {
            $message = isset($token_info['error']) ? $token_info['error'] : 'Google Calendar not configured';
            return array('success' => false, 'message' => $message);
        }
        $access_token = $token_info['token'];

        // Build start/end times (1 hour duration) using site timezone
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $start_dt = new DateTime("{$date} {$hour}:00:00", $timezone);
        $end_dt = clone $start_dt;
        $end_dt->modify('+1 hour');

        $summary = 'Call with deffo.pro';
        $description = 'Booking for ' . trim($user->first_name . ' ' . $user->last_name);

        // Add booking notes if provided
        if (!empty($booking_notes)) {
            $description .= "\n\n" . $booking_notes;
        }

        $payload = array(
            'summary' => $summary,
            'description' => $description,
            'start' => array(
                'dateTime' => $start_dt->format(DateTime::RFC3339),
                'timeZone' => $timezone->getName()
            ),
            'end' => array(
                'dateTime' => $end_dt->format(DateTime::RFC3339),
                'timeZone' => $timezone->getName()
            ),
            'attendees' => array(array('email' => $client_email)),
            'conferenceData' => array(
                'createRequest' => array(
                    'requestId' => uniqid('cp_', true)
                )
            )
        );

        $response = wp_remote_post(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1&sendUpdates=all',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($payload),
                'timeout' => 20
            )
        );

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Google Calendar request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300 || !isset($body['id'])) {
            $msg = isset($body['error']['message']) ? $body['error']['message'] : 'Google Calendar error';
            return array('success' => false, 'message' => $msg);
        }

        $meet_link = null;
        if (!empty($body['hangoutLink'])) {
            $meet_link = $body['hangoutLink'];
        } elseif (!empty($body['conferenceData']['entryPoints'])) {
            foreach ($body['conferenceData']['entryPoints'] as $entry) {
                if (isset($entry['entryPointType']) && $entry['entryPointType'] === 'video' && !empty($entry['uri'])) {
                    $meet_link = $entry['uri'];
                    break;
                }
            }
        }

        return array(
            'success' => true,
            'event_id' => $body['id'],
            'meet_link' => $meet_link
        );
    }

    /**
     * Get Google OAuth access token using stored refresh token
     */
    private function get_google_access_token() {
        $client_id = get_option('cp_google_client_id');
        $client_secret = get_option('cp_google_client_secret');
        $refresh_token = get_option('cp_google_refresh_token');

        if (!$client_id || !$client_secret || !$refresh_token) {
            return array('token' => null, 'error' => 'Missing Google client credentials or refresh token');
        }

        $token_response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            )
        ));

        if (is_wp_error($token_response)) {
            return array('token' => null, 'error' => 'Token request failed: ' . $token_response->get_error_message());
        }

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);

        if (!isset($token_data['access_token'])) {
            $err = isset($token_data['error_description']) ? $token_data['error_description'] : (isset($token_data['error']) ? $token_data['error'] : 'Unknown token error');
            return array('token' => null, 'error' => 'No access token: ' . $err);
        }

        return array('token' => $token_data['access_token']);
    }

    /**
     * Get user profile (basic info + email)
     */
    public function get_user_profile() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['telegram_id'])) {
            wp_send_json_error(array('message' => 'Telegram ID missing'));
            return;
        }

        $telegram_id = intval($_POST['telegram_id']);
        $user = CP()->database->get_user_by_telegram_id($telegram_id);

        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }

        wp_send_json_success(array(
            'user' => array(
                'id' => intval($user->id),
                'telegram_id' => intval($user->telegram_id),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email
            )
        ));
    }

    /**
     * SUPPLEMENT FEEDBACK AJAX HANDLERS
     */

    /**
     * Get supplement survey data
     */
    public function get_supplement_survey() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['survey_id'])) {
            wp_send_json_error(array('message' => 'Survey ID missing'));
            return;
        }

        $survey_id = intval($_POST['survey_id']);
        $survey = CP()->database->get_supplement_survey($survey_id);

        if (!$survey) {
            wp_send_json_error(array('message' => 'Survey not found'));
            return;
        }

        wp_send_json_success(array(
            'survey' => $survey
        ));
    }

    /**
     * Save supplement comment
     */
    public function save_supplement_comment() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['survey_id']) || !isset($_POST['supplement_id'])) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $survey_id = intval($_POST['survey_id']);
        $supplement_id = intval($_POST['supplement_id']);
        $comment_text = sanitize_textarea_field($_POST['comment_text']);

        // Accept either user_id or telegram_id
        if (isset($_POST['telegram_id'])) {
            $telegram_id = intval($_POST['telegram_id']);
            $user = CP()->database->get_user_by_telegram_id($telegram_id);
            if (!$user) {
                wp_send_json_error(array('message' => 'User not found'));
                return;
            }
            $user_id = $user->id;
        } elseif (isset($_POST['user_id'])) {
            $user_id = intval($_POST['user_id']);
        } else {
            wp_send_json_error(array('message' => 'Missing user_id or telegram_id'));
            return;
        }

        $result = CP()->database->save_supplement_comment($survey_id, $supplement_id, $user_id, $comment_text);

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Comment saved!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save comment'));
        }
    }

    /**
     * Delete supplement comment
     */
    public function delete_supplement_comment() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['supplement_id'])) {
            wp_send_json_error(array('message' => 'Missing supplement_id'));
            return;
        }

        $supplement_id = intval($_POST['supplement_id']);

        // Accept either user_id or telegram_id
        if (isset($_POST['telegram_id'])) {
            $telegram_id = intval($_POST['telegram_id']);
            $user = CP()->database->get_user_by_telegram_id($telegram_id);
            if (!$user) {
                wp_send_json_error(array('message' => 'User not found'));
                return;
            }
            $user_id = $user->id;
        } elseif (isset($_POST['user_id'])) {
            $user_id = intval($_POST['user_id']);
        } else {
            wp_send_json_error(array('message' => 'Missing user_id or telegram_id'));
            return;
        }

        $result = CP()->database->delete_supplement_comment($user_id, $supplement_id);

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Comment deleted!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete comment'));
        }
    }

    /**
     * Get user's supplement comments
     */
    public function get_user_supplement_comments() {
        check_ajax_referer('cp_nonce', 'nonce');

        if (!isset($_POST['survey_id'])) {
            wp_send_json_error(array('message' => 'Missing survey_id'));
            return;
        }

        $survey_id = intval($_POST['survey_id']);

        // Accept either user_id or telegram_id
        if (isset($_POST['telegram_id'])) {
            $telegram_id = intval($_POST['telegram_id']);
            $user = CP()->database->get_user_by_telegram_id($telegram_id);
            if (!$user) {
                wp_send_json_error(array('message' => 'User not found'));
                return;
            }
            $user_id = $user->id;
        } elseif (isset($_POST['user_id'])) {
            $user_id = intval($_POST['user_id']);
        } else {
            wp_send_json_error(array('message' => 'Missing user_id or telegram_id'));
            return;
        }

        $comments = CP()->database->get_user_supplement_comments($user_id, $survey_id);

        // Convert to associative array by supplement_id for easy lookup
        $comments_map = array();
        foreach ($comments as $comment) {
            $comments_map[$comment->supplement_id] = $comment->comment_text;
        }

        wp_send_json_success(array(
            'comments' => $comments_map
        ));
    }
}
