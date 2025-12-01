<?php
/**
 * Plugin Name: Booking Calendar
 * Description: Simple week-view calendar for booking time slots (Admin: toggle free/busy, Client: read-only view)
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: booking-calendar
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('BC_VERSION', '1.0.0');
define('BC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once BC_PLUGIN_DIR . 'includes/class-calendar-database.php';
require_once BC_PLUGIN_DIR . 'includes/class-calendar-ajax.php';
require_once BC_PLUGIN_DIR . 'includes/class-calendar-admin.php';
require_once BC_PLUGIN_DIR . 'includes/class-calendar-frontend.php';

/**
 * Main Plugin Class
 */
class BookingCalendar {

    private static $instance = null;

    public $database;
    public $ajax;
    public $admin;
    public $frontend;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->init_classes();
        $this->init_hooks();
    }

    private function init_classes() {
        $this->database = new BC_Database();
        $this->ajax = new BC_Ajax();
        $this->admin = new BC_Admin();
        $this->frontend = new BC_Frontend();
    }

    private function init_hooks() {
        // Activation hook
        register_activation_hook(__FILE__, array($this->database, 'activate'));

        // Admin menu
        add_action('admin_menu', array($this->admin, 'add_admin_menu'));

        // Shortcodes
        add_shortcode('booking_calendar', array($this->frontend, 'calendar_shortcode'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this->frontend, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
    }
}

/**
 * Returns the main instance
 */
function BC() {
    return BookingCalendar::instance();
}

// Initialize
BC();
