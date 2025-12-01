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
        $bot_token = get_option('cp_telegram_bot_token');
        
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
}
