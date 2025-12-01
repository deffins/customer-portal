<?php
/**
 * AJAX handlers
 */

if (!defined('ABSPATH')) exit;

class BC_Ajax {

    public function __construct() {
        // Get slots (both admin and client)
        add_action('wp_ajax_bc_get_slots', array($this, 'get_slots'));
        add_action('wp_ajax_nopriv_bc_get_slots', array($this, 'get_slots'));

        // Toggle slot (admin only)
        add_action('wp_ajax_bc_toggle_slot', array($this, 'toggle_slot'));
    }

    /**
     * Get slots for a date range
     */
    public function get_slots() {
        check_ajax_referer('bc_nonce', 'nonce');

        if (!isset($_POST['start_date']) || !isset($_POST['end_date'])) {
            wp_send_json_error(array('message' => 'Missing date parameters'));
            return;
        }

        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        // Validate dates
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            wp_send_json_error(array('message' => 'Invalid date format'));
            return;
        }

        $slots = BC()->database->get_slots($start_date, $end_date);

        wp_send_json_success(array('slots' => $slots));
    }

    /**
     * Toggle slot status (admin only)
     */
    public function toggle_slot() {
        check_ajax_referer('bc_nonce', 'nonce');

        // Check admin permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        if (!isset($_POST['date']) || !isset($_POST['hour'])) {
            wp_send_json_error(array('message' => 'Missing parameters'));
            return;
        }

        $date = sanitize_text_field($_POST['date']);
        $hour = intval($_POST['hour']);

        // Validate
        if (!$this->validate_date($date) || $hour < 8 || $hour > 20) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
            return;
        }

        // Don't allow toggling past slots
        $slot_datetime = new DateTime($date . ' ' . $hour . ':00:00');
        $now = new DateTime();
        if ($slot_datetime < $now) {
            wp_send_json_error(array('message' => 'Cannot modify past slots'));
            return;
        }

        $new_status = BC()->database->toggle_slot($date, $hour);

        wp_send_json_success(array(
            'status' => $new_status,
            'date' => $date,
            'hour' => $hour
        ));
    }

    /**
     * Validate date format (YYYY-MM-DD)
     */
    private function validate_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
