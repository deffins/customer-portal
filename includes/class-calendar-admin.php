<?php
/**
 * Admin interface
 */

if (!defined('ABSPATH')) exit;

class BC_Admin {

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Booking Calendar',
            'Calendar',
            'manage_options',
            'booking-calendar',
            array($this, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_booking-calendar') return;

        wp_enqueue_style(
            'bc-calendar',
            BC_PLUGIN_URL . 'assets/css/calendar.css',
            array(),
            BC_VERSION
        );

        wp_enqueue_script(
            'bc-calendar',
            BC_PLUGIN_URL . 'assets/js/calendar.js',
            array(),
            BC_VERSION,
            true
        );

        wp_localize_script('bc-calendar', 'bcConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bc_nonce'),
            'isAdmin' => true
        ));
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Booking Calendar - Admin View</h1>
            <p>Click any time slot to toggle between <strong>free</strong> and <strong>busy</strong>. All slots are busy by default.</p>

            <div id="bc-calendar-container"></div>
        </div>
        <?php
    }
}
