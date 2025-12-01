<?php
/**
 * Database operations and plugin activation
 */

if (!defined('ABSPATH')) exit;

class CP_Database {
    
    /**
     * Plugin activation - create tables
     */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Users table
        $users_table = $wpdb->prefix . 'customer_portal_users';
        $sql_users = "CREATE TABLE {$users_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            telegram_id bigint(20) NOT NULL,
            first_name varchar(255),
            last_name varchar(255),
            username varchar(255),
            drive_folder_id varchar(255),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY telegram_id (telegram_id)
        ) $charset_collate;";
        dbDelta($sql_users);
        
        // Checklists table
        $checklists_table = $wpdb->prefix . 'customer_portal_checklists';
        $sql_checklists = "CREATE TABLE {$checklists_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            title varchar(255) NOT NULL,
            type varchar(50) DEFAULT 'veikals',
            status varchar(50) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_checklists);
        
        // Checklist items table
        $items_table = $wpdb->prefix . 'customer_portal_checklist_items';
        $sql_items = "CREATE TABLE {$items_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            checklist_id mediumint(9) NOT NULL,
            product_name varchar(255) NOT NULL,
            description text,
            link varchar(500),
            store_name varchar(100),
            discount_code varchar(50),
            is_checked tinyint(1) DEFAULT 0,
            sort_order int DEFAULT 0,
            PRIMARY KEY (id),
            KEY checklist_id (checklist_id)
        ) $charset_collate;";
        dbDelta($sql_items);
        
        // Links table
        $links_table = $wpdb->prefix . 'customer_portal_links';
        $sql_links = "CREATE TABLE {$links_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            url varchar(500) NOT NULL,
            description varchar(255) NOT NULL,
            sort_order int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_links);
        
        // Default options
        add_option('cp_telegram_bot_token', '');
        add_option('cp_telegram_bot_username', '');
        add_option('cp_google_client_id', '');
        add_option('cp_google_client_secret', '');
        add_option('cp_google_refresh_token', '');
    }
    
    /**
     * Get user by telegram ID
     */
    public function get_user_by_telegram_id($telegram_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE telegram_id = %d AND is_active = 1",
            intval($telegram_id)
        ));
    }
    
    /**
     * Get all active users
     */
    public function get_active_users() {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_users';
        
        return $wpdb->get_results(
            "SELECT id, telegram_id, first_name, last_name FROM {$table} WHERE is_active = 1 ORDER BY first_name"
        );
    }
    
    /**
     * Get all users
     */
    public function get_all_users() {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_users';
        
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
    }
    
    /**
     * Insert or update user
     */
    public function save_user($telegram_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_users';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE telegram_id = %d",
            $telegram_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $table,
                $data,
                array('telegram_id' => $telegram_id)
            );
            return $existing->id;
        } else {
            $data['telegram_id'] = $telegram_id;
            $wpdb->insert($table, $data);
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Toggle user status
     */
    public function toggle_user_status($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_users';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = NOT is_active WHERE id = %d",
            $user_id
        ));
    }
    
    /**
     * Update user folder
     */
    public function update_user_folder($user_id, $folder_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_users';
        
        $wpdb->update(
            $table,
            array('drive_folder_id' => $folder_id),
            array('id' => $user_id)
        );
    }
    
    /**
     * Get user checklists
     */
    public function get_user_checklists($user_id) {
        global $wpdb;
        $checklists_table = $wpdb->prefix . 'customer_portal_checklists';
        $items_table = $wpdb->prefix . 'customer_portal_checklist_items';
        
        $checklists = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, COUNT(i.id) as total_items, SUM(i.is_checked) as checked_items
             FROM {$checklists_table} c
             LEFT JOIN {$items_table} i ON c.id = i.checklist_id
             WHERE c.user_id = %d AND c.status = 'active'
             GROUP BY c.id
             ORDER BY c.created_at DESC",
            $user_id
        ));
        
        foreach ($checklists as $checklist) {
            $checklist->items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$items_table} WHERE checklist_id = %d ORDER BY sort_order ASC",
                $checklist->id
            ));
        }
        
        return $checklists;
    }
    
    /**
     * Get user links
     */
    public function get_user_links($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_links';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, url, description FROM {$table} WHERE user_id = %d ORDER BY sort_order ASC",
            $user_id
        ));
    }
    
    /**
     * Add link
     */
    public function add_link($user_id, $url, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_links';
        
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(sort_order) FROM {$table} WHERE user_id = %d",
            $user_id
        ));
        
        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'url' => $url,
            'description' => $description,
            'sort_order' => ($max_order ? $max_order + 1 : 0)
        ));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Delete link
     */
    public function delete_link($link_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_links';
        
        $wpdb->delete($table, array('id' => $link_id));
    }
    
    /**
     * Toggle checklist item
     */
    public function toggle_checklist_item($item_id, $is_checked) {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_checklist_items';
        
        $wpdb->update(
            $table,
            array('is_checked' => $is_checked),
            array('id' => $item_id)
        );
    }
    
    /**
     * Archive checklist
     */
    public function archive_checklist($checklist_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_checklists';
        
        $wpdb->update(
            $table,
            array('status' => 'archived'),
            array('id' => $checklist_id)
        );
    }
}
