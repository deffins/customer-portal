<?php
/**
 * Plugin Name: Customer Portal with Telegram Auth
 * Description: Customer file sharing portal with Telegram authentication, Google Drive integration, and appointment booking
 * Version: 2.1.0
 * Author: Your Name
 * Text Domain: customer-portal
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('CP_VERSION', '2.1.0');
define('CP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once CP_PLUGIN_DIR . 'includes/class-portal-database.php';
require_once CP_PLUGIN_DIR . 'includes/class-portal-surveys.php';
require_once CP_PLUGIN_DIR . 'includes/class-portal-ajax.php';
require_once CP_PLUGIN_DIR . 'includes/class-portal-admin.php';
require_once CP_PLUGIN_DIR . 'includes/class-portal-frontend.php';

/**
 * Main Plugin Class
 */
class CustomerPortal {
    
    private static $instance = null;

    public $database;
    public $surveys;
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
        $this->database = new CP_Database();
        $this->surveys = new CP_Surveys();
        $this->ajax = new CP_Ajax();
        $this->admin = new CP_Admin();
        $this->frontend = new CP_Frontend();
    }
    
    private function init_hooks() {
        // Activation hook
        register_activation_hook(__FILE__, array($this->database, 'activate'));
        
        // Admin menu
        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        
        // Shortcodes
        add_shortcode('customer_portal', array($this->frontend, 'portal_shortcode'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this->frontend, 'enqueue_scripts'));
    }
    
    /**
     * Get users table name
     */
    public function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'customer_portal_users';
    }
}

/**
 * Returns the main instance of CustomerPortal
 */
function CP() {
    return CustomerPortal::instance();
}

// Initialize 
CP();
