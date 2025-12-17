<?php
/**
 * Database operations and plugin activation
 */

if (!defined('ABSPATH')) exit;

class CP_Database {

    public function __construct() {
        // Ensure any new columns are present for existing installs
        $this->upgrade_user_schema();
    }
    
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
            email varchar(255),
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
        
        // Calendar slots table (from booking-calendar plugin)
        $calendar_table = $wpdb->prefix . 'booking_calendar_slots';
        $sql_calendar = "CREATE TABLE {$calendar_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slot_date date NOT NULL,
            slot_hour tinyint NOT NULL,
            status varchar(20) DEFAULT 'blocked',
            booked_by int(11) DEFAULT NULL,
            booked_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slot_unique (slot_date, slot_hour),
            KEY slot_date (slot_date),
            KEY status (status),
            KEY booked_by (booked_by)
        ) $charset_collate;";
        dbDelta($sql_calendar);

        // Run calendar schema upgrade (for existing installations)
        $this->upgrade_calendar_schema();
        $this->upgrade_user_schema();

        // Survey assignments table
        $survey_assignments_table = $wpdb->prefix . 'cp_survey_assignments';
        $sql_survey_assignments = "CREATE TABLE {$survey_assignments_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            survey_id varchar(100) NOT NULL,
            status varchar(50) DEFAULT 'assigned',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY survey_id (survey_id),
            UNIQUE KEY user_survey (user_id, survey_id)
        ) $charset_collate;";
        dbDelta($sql_survey_assignments);

        // Survey results table
        $survey_results_table = $wpdb->prefix . 'cp_survey_results';
        $sql_survey_results = "CREATE TABLE {$survey_results_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            survey_id varchar(100) NOT NULL,
            answers_json longtext,
            total_score int DEFAULT 0,
            dimension_scores_json text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY survey_id (survey_id)
        ) $charset_collate;";
        dbDelta($sql_survey_results);

        // Surveys table (for supplement_feedback and other dynamic surveys)
        $surveys_table = $wpdb->prefix . 'cp_surveys';
        $sql_surveys = "CREATE TABLE {$surveys_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type)
        ) $charset_collate;";
        dbDelta($sql_surveys);

        // Survey supplements table
        $survey_supplements_table = $wpdb->prefix . 'cp_survey_supplements';
        $sql_survey_supplements = "CREATE TABLE {$survey_supplements_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            survey_id mediumint(9) NOT NULL,
            name varchar(255) NOT NULL,
            sort_order int DEFAULT 0,
            PRIMARY KEY (id),
            KEY survey_id (survey_id)
        ) $charset_collate;";
        dbDelta($sql_survey_supplements);

        // Survey supplement comments table
        $survey_supplement_comments_table = $wpdb->prefix . 'cp_survey_supplement_comments';
        $sql_survey_supplement_comments = "CREATE TABLE {$survey_supplement_comments_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            survey_id mediumint(9) NOT NULL,
            supplement_id mediumint(9) NOT NULL,
            user_id mediumint(9) NOT NULL,
            comment_text text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY survey_id (survey_id),
            KEY supplement_id (supplement_id),
            KEY user_id (user_id),
            UNIQUE KEY user_supplement (user_id, supplement_id)
        ) $charset_collate;";
        dbDelta($sql_survey_supplement_comments);

        // Default options
        add_option('cp_telegram_bot_token', '');
        add_option('cp_telegram_bot_username', '');
        add_option('cp_google_client_id', '');
        add_option('cp_google_client_secret', '');
        add_option('cp_google_refresh_token', '');
        add_option('cp_calendar_version', '1.0');
    }

    /**
     * Upgrade calendar schema from old booking-calendar plugin
     */
    public function upgrade_calendar_schema() {
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        if (!$table_exists) {
            return; // Table will be created by dbDelta
        }

        // Check if booked_by column exists
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'booked_by'");

        if (empty($columns)) {
            // Add new columns
            $wpdb->query("ALTER TABLE {$table}
                ADD COLUMN booked_by INT(11) DEFAULT NULL AFTER status,
                ADD COLUMN booked_at DATETIME DEFAULT NULL AFTER booked_by,
                ADD KEY booked_by (booked_by)");
        }

        // Migrate existing data: 'busy' -> 'blocked'
        $wpdb->query("UPDATE {$table} SET status = 'blocked' WHERE status = 'busy'");
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
     * Update user email (with schema safety)
     */
    public function update_user_email($user_id, $email) {
        if (empty($email)) return;
        $this->upgrade_user_schema();

        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_users';
        $wpdb->update(
            $table,
            array('email' => $email),
            array('id' => $user_id)
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

    /**
     * CALENDAR METHODS
     */

    /**
     * Get calendar slots with booking info
     */
    public function get_calendar_slots($start_date, $end_date, $user_id = null) {
        global $wpdb;
        $calendar_table = $wpdb->prefix . 'booking_calendar_slots';
        $users_table = $wpdb->prefix . 'customer_portal_users';

        $sql = "SELECT s.*, u.first_name, u.last_name";
        if ($user_id) {
            $sql .= ", IF(s.booked_by = %d, 1, 0) as is_mine";
        }
        $sql .= " FROM {$calendar_table} s
                 LEFT JOIN {$users_table} u ON s.booked_by = u.id
                 WHERE s.slot_date >= %s AND s.slot_date <= %s
                 ORDER BY s.slot_date ASC, s.slot_hour ASC";

        if ($user_id) {
            return $wpdb->get_results($wpdb->prepare($sql, $user_id, $start_date, $end_date));
        } else {
            return $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date));
        }
    }

    /**
     * Book a slot (with race condition protection)
     */
    public function book_slot($date, $hour, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';

        // Validate past slot
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $slot_datetime = new DateTime("{$date} {$hour}:00:00", new DateTimeZone('UTC'));
        if ($slot_datetime < $now) {
            return array('success' => false, 'message' => 'Cannot book past slots');
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        // Lock row for update
        $slot = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE slot_date = %s AND slot_hour = %d FOR UPDATE",
            $date,
            $hour
        ));

        if (!$slot) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'message' => 'Slot not found');
        }

        if ($slot->status !== 'free') {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'message' => 'Slot is not available');
        }

        // Update slot
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'booked',
                'booked_by' => $user_id,
                'booked_at' => current_time('mysql')
            ),
            array('slot_date' => $date, 'slot_hour' => $hour)
        );

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'message' => 'Database error');
        }

        $wpdb->query('COMMIT');
        return array('success' => true, 'message' => 'Booking confirmed!');
    }

    /**
     * Cancel booking (customer)
     */
    public function cancel_booking($date, $hour, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';

        // Verify ownership
        $slot = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE slot_date = %s AND slot_hour = %d",
            $date,
            $hour
        ));

        if (!$slot) {
            return array('success' => false, 'message' => 'Slot not found');
        }

        if ($slot->booked_by != $user_id) {
            return array('success' => false, 'message' => 'Not authorized');
        }

        // Cancel booking
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'free',
                'booked_by' => null,
                'booked_at' => null
            ),
            array('slot_date' => $date, 'slot_hour' => $hour, 'booked_by' => $user_id)
        );

        if ($result === false) {
            return array('success' => false, 'message' => 'Database error');
        }

        return array('success' => true, 'message' => 'Booking cancelled');
    }

    /**
     * Admin cancel booking
     */
    public function admin_cancel_booking($date, $hour) {
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';

        $result = $wpdb->update(
            $table,
            array(
                'status' => 'free',
                'booked_by' => null,
                'booked_at' => null
            ),
            array('slot_date' => $date, 'slot_hour' => $hour)
        );

        if ($result === false) {
            return array('success' => false, 'message' => 'Database error');
        }

        return array('success' => true, 'message' => 'Booking cancelled');
    }

    /**
     * Toggle slot availability (admin only)
     */
    public function toggle_slot_availability($date, $hour) {
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';

        // Get current status
        $slot = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE slot_date = %s AND slot_hour = %d",
            $date,
            $hour
        ));

        if (!$slot) {
            // Create slot if doesn't exist
            $wpdb->insert(
                $table,
                array(
                    'slot_date' => $date,
                    'slot_hour' => $hour,
                    'status' => 'free'
                )
            );
            return array('success' => true, 'status' => 'free');
        }

        // Cannot toggle booked slots
        if ($slot->status === 'booked') {
            return array('success' => false, 'message' => 'Cannot modify booked slots. Cancel booking first.');
        }

        // Toggle between free and blocked
        $new_status = ($slot->status === 'free') ? 'blocked' : 'free';

        $wpdb->update(
            $table,
            array('status' => $new_status),
            array('slot_date' => $date, 'slot_hour' => $hour)
        );

        return array('success' => true, 'status' => $new_status);
    }

    /**
     * Get all bookings with filters
     */
    public function get_all_bookings($filters = array()) {
        global $wpdb;
        $calendar_table = $wpdb->prefix . 'booking_calendar_slots';
        $users_table = $wpdb->prefix . 'customer_portal_users';

        $where = array("s.status = 'booked'");
        $params = array();

        if (!empty($filters['start_date'])) {
            $where[] = "s.slot_date >= %s";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "s.slot_date <= %s";
            $params[] = $filters['end_date'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = "s.booked_by = %d";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['upcoming_only'])) {
            $where[] = "s.slot_date >= CURDATE()";
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT s.*, u.first_name, u.last_name, u.telegram_id
                FROM {$calendar_table} s
                LEFT JOIN {$users_table} u ON s.booked_by = u.id
                WHERE {$where_clause}
                ORDER BY s.slot_date ASC, s.slot_hour ASC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_results($sql);
        }
    }

    /**
     * SURVEY METHODS
     */

    /**
     * Assign a survey to a user
     */
    public function assign_survey($user_id, $survey_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cp_survey_assignments';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND survey_id = %s",
            $user_id,
            $survey_id
        ));

        if ($existing) {
            return false; // Already assigned
        }

        $result = $wpdb->insert($table, array(
            'user_id' => $user_id,
            'survey_id' => $survey_id,
            'status' => 'assigned'
        ));

        return $result !== false;
    }

    /**
     * Remove survey assignment
     */
    public function remove_survey_assignment($assignment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cp_survey_assignments';

        return $wpdb->delete($table, array('id' => $assignment_id));
    }

    /**
     * Get user's assigned surveys
     */
    public function get_user_assigned_surveys($user_id) {
        global $wpdb;
        $assignments_table = $wpdb->prefix . 'cp_survey_assignments';
        $results_table = $wpdb->prefix . 'cp_survey_results';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM {$results_table} r WHERE r.user_id = a.user_id AND r.survey_id = a.survey_id) as completion_count,
                    (SELECT MAX(created_at) FROM {$results_table} r WHERE r.user_id = a.user_id AND r.survey_id = a.survey_id) as last_completed_at
             FROM {$assignments_table} a
             WHERE a.user_id = %d
             ORDER BY a.created_at DESC",
            $user_id
        ));
    }

    /**
     * Get all survey assignments (admin)
     */
    public function get_all_survey_assignments() {
        global $wpdb;
        $assignments_table = $wpdb->prefix . 'cp_survey_assignments';
        $users_table = $wpdb->prefix . 'customer_portal_users';

        return $wpdb->get_results(
            "SELECT a.*, u.first_name, u.last_name, u.telegram_id
             FROM {$assignments_table} a
             LEFT JOIN {$users_table} u ON a.user_id = u.id
             ORDER BY a.created_at DESC"
        );
    }

    /**
     * Save survey result
     */
    public function save_survey_result($user_id, $survey_id, $answers, $total_score, $dimension_scores) {
        global $wpdb;
        $table = $wpdb->prefix . 'cp_survey_results';

        $result = $wpdb->insert($table, array(
            'user_id' => $user_id,
            'survey_id' => $survey_id,
            'answers_json' => json_encode($answers),
            'total_score' => $total_score,
            'dimension_scores_json' => json_encode($dimension_scores)
        ));

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Get all survey results (admin)
     */
    public function get_all_survey_results($filters = array()) {
        global $wpdb;
        $results_table = $wpdb->prefix . 'cp_survey_results';
        $users_table = $wpdb->prefix . 'customer_portal_users';

        $where = array('1=1');
        $params = array();

        if (!empty($filters['user_id'])) {
            $where[] = "r.user_id = %d";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['survey_id'])) {
            $where[] = "r.survey_id = %s";
            $params[] = $filters['survey_id'];
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT r.*, u.first_name, u.last_name, u.telegram_id
                FROM {$results_table} r
                LEFT JOIN {$users_table} u ON r.user_id = u.id
                WHERE {$where_clause}
                ORDER BY r.created_at DESC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_results($sql);
        }
    }

    /**
     * Get single survey result by ID
     */
    public function get_survey_result($result_id) {
        global $wpdb;
        $results_table = $wpdb->prefix . 'cp_survey_results';
        $users_table = $wpdb->prefix . 'customer_portal_users';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, u.first_name, u.last_name, u.telegram_id
             FROM {$results_table} r
             LEFT JOIN {$users_table} u ON r.user_id = u.id
             WHERE r.id = %d",
            $result_id
        ));
    }

    /**
     * Get user's survey history
     */
    public function get_user_survey_results($user_id, $survey_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'cp_survey_results';

        if ($survey_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND survey_id = %s ORDER BY created_at DESC",
                $user_id,
                $survey_id
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            ));
        }
    }

    /**
     * Upgrade user schema to add email column if missing
     */
    private function upgrade_user_schema() {
        global $wpdb;
        $table = $wpdb->prefix . 'customer_portal_users';

        $column = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND TABLE_SCHEMA = %s AND COLUMN_NAME = 'email'",
            $table,
            $wpdb->dbname
        ));

        if (!$column) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN email varchar(255) NULL AFTER username");
        }
    }

    /**
     * SUPPLEMENT FEEDBACK SURVEY METHODS
     */

    /**
     * Create or update a supplement feedback survey
     */
    public function save_supplement_survey($survey_id, $title, $supplements) {
        global $wpdb;
        $surveys_table = $wpdb->prefix . 'cp_surveys';
        $supplements_table = $wpdb->prefix . 'cp_survey_supplements';

        // Check for duplicate title (excluding current survey if editing)
        $duplicate_check = $wpdb->prepare(
            "SELECT id FROM {$surveys_table} WHERE title = %s AND type = 'supplement_feedback' AND id != %d",
            $title,
            $survey_id ? $survey_id : 0
        );
        $existing = $wpdb->get_var($duplicate_check);

        if ($existing) {
            // Duplicate title found
            return false;
        }

        if ($survey_id) {
            // Update existing survey
            $wpdb->update(
                $surveys_table,
                array('title' => $title),
                array('id' => $survey_id, 'type' => 'supplement_feedback')
            );
        } else {
            // Create new survey
            $wpdb->insert($surveys_table, array(
                'title' => $title,
                'type' => 'supplement_feedback'
            ));
            $survey_id = $wpdb->insert_id;
        }

        // Delete existing supplements for this survey
        $wpdb->delete($supplements_table, array('survey_id' => $survey_id));

        // Insert new supplements
        $sort_order = 0;
        foreach ($supplements as $supplement_name) {
            $supplement_name = trim($supplement_name);
            if (!empty($supplement_name)) {
                $wpdb->insert($supplements_table, array(
                    'survey_id' => $survey_id,
                    'name' => $supplement_name,
                    'sort_order' => $sort_order++
                ));
            }
        }

        return $survey_id;
    }

    /**
     * Get all supplement feedback surveys
     */
    public function get_supplement_surveys() {
        global $wpdb;
        $table = $wpdb->prefix . 'cp_surveys';

        return $wpdb->get_results(
            "SELECT * FROM {$table} WHERE type = 'supplement_feedback' ORDER BY created_at DESC"
        );
    }

    /**
     * Get a single supplement survey with its supplements
     */
    public function get_supplement_survey($survey_id) {
        global $wpdb;
        $surveys_table = $wpdb->prefix . 'cp_surveys';
        $supplements_table = $wpdb->prefix . 'cp_survey_supplements';

        $survey = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$surveys_table} WHERE id = %d AND type = 'supplement_feedback'",
            $survey_id
        ));

        if ($survey) {
            $survey->supplements = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$supplements_table} WHERE survey_id = %d ORDER BY sort_order ASC",
                $survey_id
            ));
        }

        return $survey;
    }

    /**
     * Delete a supplement survey
     */
    public function delete_supplement_survey($survey_id) {
        global $wpdb;
        $surveys_table = $wpdb->prefix . 'cp_surveys';
        $supplements_table = $wpdb->prefix . 'cp_survey_supplements';
        $comments_table = $wpdb->prefix . 'cp_survey_supplement_comments';

        // Delete comments
        $wpdb->delete($comments_table, array('survey_id' => $survey_id));

        // Delete supplements
        $wpdb->delete($supplements_table, array('survey_id' => $survey_id));

        // Delete survey
        return $wpdb->delete($surveys_table, array('id' => $survey_id, 'type' => 'supplement_feedback'));
    }

    /**
     * Save or update a supplement comment
     */
    public function save_supplement_comment($survey_id, $supplement_id, $user_id, $comment_text) {
        global $wpdb;
        $table = $wpdb->prefix . 'cp_survey_supplement_comments';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND supplement_id = %d",
            $user_id,
            $supplement_id
        ));

        if ($existing) {
            // Update existing comment
            return $wpdb->update(
                $table,
                array('comment_text' => $comment_text),
                array('id' => $existing->id)
            );
        } else {
            // Insert new comment
            return $wpdb->insert($table, array(
                'survey_id' => $survey_id,
                'supplement_id' => $supplement_id,
                'user_id' => $user_id,
                'comment_text' => $comment_text
            ));
        }
    }

    /**
     * Get user's comments for a survey
     */
    public function get_user_supplement_comments($user_id, $survey_id) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'cp_survey_supplement_comments';
        $supplements_table = $wpdb->prefix . 'cp_survey_supplements';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, s.name as supplement_name, s.sort_order
             FROM {$comments_table} c
             INNER JOIN {$supplements_table} s ON c.supplement_id = s.id
             WHERE c.user_id = %d AND c.survey_id = %d
             ORDER BY s.sort_order ASC",
            $user_id,
            $survey_id
        ));
    }

    /**
     * Get all comments for a survey with user info (admin)
     */
    public function get_survey_all_comments($survey_id) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'cp_survey_supplement_comments';
        $supplements_table = $wpdb->prefix . 'cp_survey_supplements';
        $users_table = $wpdb->prefix . 'customer_portal_users';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, s.name as supplement_name, s.sort_order, u.first_name, u.last_name
             FROM {$comments_table} c
             INNER JOIN {$supplements_table} s ON c.supplement_id = s.id
             INNER JOIN {$users_table} u ON c.user_id = u.id
             WHERE c.survey_id = %d
             ORDER BY u.first_name ASC, s.sort_order ASC",
            $survey_id
        ));
    }

    /**
     * Get users who have commented on a survey
     */
    public function get_survey_commenters($survey_id) {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'cp_survey_supplement_comments';
        $users_table = $wpdb->prefix . 'customer_portal_users';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.id, u.first_name, u.last_name, u.telegram_id
             FROM {$users_table} u
             INNER JOIN {$comments_table} c ON u.id = c.user_id
             WHERE c.survey_id = %d
             ORDER BY u.first_name ASC",
            $survey_id
        ));
    }
}
