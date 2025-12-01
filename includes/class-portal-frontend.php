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
        
        // JS
        wp_enqueue_script(
            'cp-portal',
            CP_PLUGIN_URL . 'assets/js/portal.js',
            array(),
            CP_VERSION,
            true
        );
        
        // Pass data to JS
        wp_localize_script('cp-portal', 'cpConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cp_nonce'),
            'botUsername' => get_option('cp_telegram_bot_username'),
            'debug' => false
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
                <h2>Welcome, <span id="user-name"></span>!</h2>
                
                <div class="portal-tabs">
                    <button class="tab-button active" data-tab="files">Files</button>
                    <button class="tab-button" data-tab="checklists">Checklists</button>
                    <button class="tab-button" data-tab="links">Links</button>
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
                
                <button id="logout-btn" class="button">Logout</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
