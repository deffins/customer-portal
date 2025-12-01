<?php
/**
 * Frontend - shortcode and scripts
 */

if (!defined('ABSPATH')) exit;

class BC_Frontend {

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!is_page()) return;

        $post = get_post();
        if (!$post || !has_shortcode($post->post_content, 'booking_calendar')) return;

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
            'isAdmin' => false
        ));
    }

    /**
     * Calendar shortcode
     */
    public function calendar_shortcode() {
        ob_start();
        ?>
        <div class="bc-calendar-wrapper">
            <h2>Booking Calendar</h2>
            <p>View available time slots. Green = available, Red = busy.</p>

            <div id="bc-calendar-container"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
